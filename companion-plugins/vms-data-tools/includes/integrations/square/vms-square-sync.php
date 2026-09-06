<?php

/**
 * VMS Square Sync v1.4 (procedural, drop-in)
 *
 * Pulls Square orders into VMS for item-level reporting, budgeting, and door splits.
 * Stores raw order JSON and extracted totals in custom tables.
 *
 * v1.4 adds:
 * - Datepicker for all date inputs in this module
 * - Door Split (what-if) remembers last used date/location/percent after submit
 * - Event Plan metabox to link Square Location + Business Date and snapshot door split numbers
 * - POS actuals windowed summary API + bucket mapping + event snapshot action
 * - Reporting-category-aware selector refresh and richer snapshot window audit details
 */

if (!defined('ABSPATH')) {
  exit;
}

if (defined('VMS_SQUARE_SYNC_LOADED')) {
  return;
}
define('VMS_SQUARE_SYNC_LOADED', true);

define('VMS_SQUARE_SYNC_VERSION', '1.4.0');
define('VMS_SQUARE_API_VERSION', '2026-01-22'); // Matches Square-Version header.
define('VMS_SQUARE_SCHEMA_VERSION', '1.0.0');

register_activation_hook(__FILE__, 'vms_square_sync_install');
function vms_square_sync_install(): void
{
  // Create tables only on activation / schema upgrade (never on every request).
  vms_square_sync_maybe_create_tables(true);
}


add_action('init', 'vms_square_sync_bootstrap');
add_action('admin_menu', 'vms_square_sync_admin_menu');
add_action('admin_enqueue_scripts', 'vms_square_sync_admin_enqueue');
add_action('admin_init', 'vms_square_sync_maybe_create_tables');

add_action('vms_square_nightly_sync', 'vms_square_sync_run_cron');
add_action('vms_square_retry_sync', 'vms_square_sync_run_cron');

add_action('add_meta_boxes', 'vms_square_event_plan_add_metabox');
add_action('save_post', 'vms_square_event_plan_save_metabox', 10, 2);
add_action('admin_post_vms_square_snapshot_event_actuals', 'vms_square_admin_post_snapshot_event_actuals');

/**
 * Bootstrap
 */
function vms_square_sync_bootstrap(): void
{
  // NOTE: Tables are created on activation (and on admin schema-version checks), not on every request.
  vms_square_sync_maybe_schedule();
}

/**
 * Settings storage
 */
function vms_square_settings_defaults(): array
{
  return array(
    'env' => 'production', // production|sandbox
    'access_token' => '',
    'location_ids' => array(), // array of strings
    'lookback_days' => 2, // int
    'door_category_ids' => array(), // array of strings (Square reporting category ids; legacy catalog category ids still supported)
    'bucket_category_ids' => array(), // bucket key => array of reporting/category ids
    'bucket_labels' => array(), // bucket key => label
    'event_window_default_mode' => 'business_date', // business_date|event_window
    'event_window_default_start_offset_minutes' => 0,
    'event_window_default_end_offset_minutes' => 240,
    'locations_cache' => array(),
    'categories_cache' => array(),
    'cache_updated_at_utc' => '',
  );
}

function vms_square_settings_get_all(): array
{
  $defaults = vms_square_settings_defaults();
  $saved = get_option('vms_square_settings', array());
  if (!is_array($saved)) {
    $saved = array();
  }
  return array_merge($defaults, $saved);
}

function vms_square_settings_update(array $new): void
{
  $current = vms_square_settings_get_all();
  $merged = array_merge($current, $new);
  update_option('vms_square_settings', $merged, false);
}

function vms_square_setting(string $key, $default = null)
{
  $all = vms_square_settings_get_all();
  return array_key_exists($key, $all) ? $all[$key] : $default;
}

/**
 * Effective settings (constants override UI options)
 */
function vms_square_effective_env(): string
{
  if (defined('VMS_SQUARE_ENV') && is_string(VMS_SQUARE_ENV) && VMS_SQUARE_ENV) {
    return (string) VMS_SQUARE_ENV;
  }
  $env = (string) vms_square_setting('env', 'production');
  return in_array($env, array('production', 'sandbox'), true) ? $env : 'production';
}

function vms_square_effective_access_token(): string
{
  if (defined('VMS_SQUARE_ACCESS_TOKEN') && is_string(VMS_SQUARE_ACCESS_TOKEN) && VMS_SQUARE_ACCESS_TOKEN) {
    return (string) VMS_SQUARE_ACCESS_TOKEN;
  }
  $tok = (string) vms_square_setting('access_token', '');
  return $tok;
}

function vms_square_effective_location_ids(): array
{
  if (defined('VMS_SQUARE_LOCATION_IDS') && is_string(VMS_SQUARE_LOCATION_IDS) && VMS_SQUARE_LOCATION_IDS) {
    $raw = (string) VMS_SQUARE_LOCATION_IDS;
    $parts = array_filter(array_map('trim', explode(',', $raw)));
    return array_values($parts);
  }
  $ids = vms_square_setting('location_ids', array());
  if (!is_array($ids)) {
    return array();
  }
  $ids = array_values(array_filter(array_map('trim', $ids)));
  return $ids;
}

function vms_square_effective_lookback_days(): int
{
  if (defined('VMS_SQUARE_LOOKBACK_DAYS')) {
    return max(0, (int) VMS_SQUARE_LOOKBACK_DAYS);
  }
  return max(0, (int) vms_square_setting('lookback_days', 2));
}

function vms_square_effective_door_category_ids(): array
{
  $ids = vms_square_setting('door_category_ids', array());
  if (!is_array($ids)) {
    return array();
  }
  $ids = array_values(array_filter(array_map('trim', $ids)));
  return $ids;
}

function vms_square_bucket_key_sanitize(string $key): string
{
  $key = strtolower(trim($key));
  $key = preg_replace('/[^a-z0-9_]/', '_', $key);
  $key = preg_replace('/_+/', '_', $key);
  $key = trim((string)$key, '_');
  return is_string($key) ? $key : '';
}

function vms_square_effective_bucket_category_ids(): array
{
  $raw = vms_square_setting('bucket_category_ids', array());
  if (!is_array($raw)) {
    return array();
  }

  $out = array();
  foreach ($raw as $bucket_key => $cat_ids) {
    $key = vms_square_bucket_key_sanitize((string)$bucket_key);
    if ($key === '' || !is_array($cat_ids)) {
      continue;
    }
    $ids = array_values(array_unique(array_filter(array_map('trim', $cat_ids))));
    if (!empty($ids)) {
      $out[$key] = $ids;
    }
  }

  return $out;
}

function vms_square_effective_bucket_labels(): array
{
  $raw = vms_square_setting('bucket_labels', array());
  if (!is_array($raw)) {
    $raw = array();
  }

  $out = array();
  foreach (vms_square_effective_bucket_category_ids() as $bucket_key => $cat_ids) {
    $label = isset($raw[$bucket_key]) ? sanitize_text_field((string)$raw[$bucket_key]) : '';
    $out[$bucket_key] = ($label !== '') ? $label : ucwords(str_replace('_', ' ', $bucket_key));
  }
  return $out;
}

function vms_square_effective_event_window_default_mode(): string
{
  $mode = (string)vms_square_setting('event_window_default_mode', 'business_date');
  return in_array($mode, array('business_date', 'event_window'), true) ? $mode : 'business_date';
}

function vms_square_effective_event_window_default_start_offset_minutes(): int
{
  return (int)vms_square_setting('event_window_default_start_offset_minutes', 0);
}

function vms_square_effective_event_window_default_end_offset_minutes(): int
{
  return (int)vms_square_setting('event_window_default_end_offset_minutes', 240);
}

function vms_square_manage_capability(): string
{
  if (function_exists('vms_dt_manage_capability')) {
    return vms_dt_manage_capability();
  }
  $core_capability = vms_dt_core_constant('VMS_CAP_MANAGE_DATA_TOOLS', '');
  if (is_string($core_capability) && $core_capability !== '') {
    return $core_capability;
  }
  if (defined('VMS_DT_CAP_IMPORT_VENDORS') && is_string(VMS_DT_CAP_IMPORT_VENDORS) && VMS_DT_CAP_IMPORT_VENDORS !== '') {
    return (string)VMS_DT_CAP_IMPORT_VENDORS;
  }
  return 'manage_options';
}

function vms_square_current_user_can_manage(): bool
{
  return current_user_can(vms_square_manage_capability()) || current_user_can('manage_options');
}

function vms_square_sync_base_url(): string
{
  return (vms_square_effective_env() === 'sandbox')
    ? 'https://connect.squareupsandbox.com'
    : 'https://connect.squareup.com';
}

/**
 * Scheduling
 */
function vms_square_sync_maybe_schedule(): void
{
  $hook = 'vms_square_nightly_sync';

  // Prefer Action Scheduler if available (WooCommerce ships it).
  if (function_exists('as_next_scheduled_action') && function_exists('as_schedule_recurring_action')) {
    $next = as_next_scheduled_action($hook, array(), 'vms');
    if (!$next) {
      $ts = vms_square_sync_next_run_timestamp();
      as_schedule_recurring_action($ts, DAY_IN_SECONDS, $hook, array(), 'vms');
    }
    return;
  }

  // Fallback: WP-Cron
  if (!wp_next_scheduled($hook)) {
    $ts = vms_square_sync_next_run_timestamp();
    wp_schedule_event($ts, 'daily', $hook);
  }
}

function vms_square_sync_next_run_timestamp(): int
{
  $tz = wp_timezone();
  $now = new DateTime('now', $tz);
  $next = new DateTime('now', $tz);
  $next->setTime(2, 10, 0);
  if ($next <= $now) {
    $next->modify('+1 day');
  }
  return (int) $next->getTimestamp();
}

/**
 * Cron entry
 */
function vms_square_sync_run_cron(): void
{
  vms_square_sync_run(array(
    'lookback_days' => vms_square_effective_lookback_days(),
  ));
}

/**
 * Cooldown helpers
 */
function vms_square_sync_get_retry_meta(): array
{
  $meta = get_option('vms_square_retry_meta', array());
  return is_array($meta) ? $meta : array();
}

function vms_square_sync_check_cooldown(): array
{
  $cooldown_until = get_option('vms_square_api_cooldown_until_utc', '');
  if (!is_string($cooldown_until) || !$cooldown_until) {
    return array('in_cooldown' => false);
  }

  $until_ts = strtotime($cooldown_until . ' UTC');
  if (!$until_ts) {
    return array('in_cooldown' => false);
  }

  if (time() < $until_ts) {
    return array(
      'in_cooldown' => true,
      'cooldown_until_utc' => $cooldown_until,
      'retry_meta' => vms_square_sync_get_retry_meta(),
    );
  }

  delete_option('vms_square_api_cooldown_until_utc');
  return array('in_cooldown' => false);
}

/**
 * Main run
 */
function vms_square_sync_run(array $args = array()): array
{
  $token = vms_square_effective_access_token();
  $location_ids = vms_square_effective_location_ids();

  if (!$token) {
    return vms_square_sync_set_status(false, 'Missing Square access token. Set VMS_SQUARE_ACCESS_TOKEN or save it in Tools → VMS Square Sync.');
  }
  if (empty($location_ids)) {
    return vms_square_sync_set_status(false, 'No Square locations selected. Save at least one Location ID in Tools → VMS Square Sync.');
  }

  $cool = vms_square_sync_check_cooldown();
  if (!empty($cool['in_cooldown'])) {
    $msg = 'In cooldown until ' . (string)$cool['cooldown_until_utc'] . ' UTC (Square previously unavailable).';
    return vms_square_sync_set_status(false, $msg, array(
      'ok' => false,
      'message' => $msg,
      'cooldown_until_utc' => (string)$cool['cooldown_until_utc'],
      'retry_meta' => (array)($cool['retry_meta'] ?? array()),
    ));
  }

  if (get_transient('vms_square_sync_lock')) {
    return vms_square_sync_set_status(false, 'Sync already running (lock present).');
  }
  set_transient('vms_square_sync_lock', 1, 20 * MINUTE_IN_SECONDS);

  $lookback = isset($args['lookback_days']) ? max(0, (int)$args['lookback_days']) : 2;

  $stats = array(
    'ok' => true,
    'orders_upserted' => 0,
    'days_synced' => 0,
    'locations' => count($location_ids),
    'catalog_map_new' => 0,
    'door_rollups_updated' => 0,
    'message' => 'OK',
  );

  try {
    $site_tz = wp_timezone();
    $today = new DateTime('now', $site_tz);
    $today->setTime(0, 0, 0);

    $end = clone $today;
    $end->modify('-1 day');

    $start = clone $end;
    if ($lookback > 0) {
      $start->modify('-' . (int)$lookback . ' day');
    }

    foreach ($location_ids as $location_id) {
      $loc_tz = vms_square_sync_location_timezone($location_id);
      $cursor_day = clone $start;

      while ($cursor_day <= $end) {
        $biz_date = $cursor_day->format('Y-m-d');
        $res = vms_square_sync_one_day($location_id, $loc_tz, $biz_date);

        $stats['orders_upserted'] += (int)($res['orders_upserted'] ?? 0);
        $stats['catalog_map_new'] += (int)($res['catalog_map_new'] ?? 0);
        $stats['door_rollups_updated'] += (int)($res['door_rollup_updated'] ?? 0);

        $stats['days_synced']++;
        $cursor_day->modify('+1 day');
      }
    }

    vms_square_sync_set_status(true, 'Sync complete', $stats);
    update_option('vms_square_retry_meta', array(), false);
    delete_option('vms_square_api_cooldown_until_utc');
  } catch (Throwable $e) {
    $stats['ok'] = false;
    $stats['message'] = 'Error: ' . $e->getMessage();

    $is_transient = vms_square_sync_is_transient_square_error($e->getMessage());
    if ($is_transient) {
      $retry = vms_square_sync_schedule_retry($e->getMessage());
      $stats['next_retry_at_utc'] = $retry['next_retry_at_utc'];
      $stats['retry_count'] = $retry['retry_count'];
      $stats['message'] .= ' | Retry scheduled at ' . $retry['next_retry_at_utc'] . ' UTC';
    }

    vms_square_sync_set_status(false, $stats['message'], $stats);
  }

  delete_transient('vms_square_sync_lock');
  return $stats;
}

/**
 * Location timezone
 */
function vms_square_sync_location_timezone(string $location_id): DateTimeZone
{
  $cache_key = 'vms_square_loc_tz_' . $location_id;
  $cached = get_option($cache_key);
  if (is_string($cached) && $cached) {
    try {
      return new DateTimeZone($cached);
    } catch (Throwable $e) {
    }
  }

  $resp = vms_square_api_request('GET', '/v2/locations/' . rawurlencode($location_id));
  $tz = $resp['location']['timezone'] ?? 'UTC';
  if (!is_string($tz) || !$tz) {
    $tz = 'UTC';
  }

  update_option($cache_key, $tz, false);

  try {
    return new DateTimeZone($tz);
  } catch (Throwable $e) {
    return new DateTimeZone('UTC');
  }
}

/**
 * Sync one business date (per location)
 */
function vms_square_sync_one_day(string $location_id, DateTimeZone $tz, string $biz_date): array
{
  global $wpdb;

  $start = new DateTime($biz_date . ' 00:00:00', $tz);
  $end = new DateTime($biz_date . ' 00:00:00', $tz);
  $end->modify('+1 day');

  $body = array(
    'location_ids' => array($location_id),
    'query' => array(
      'filter' => array(
        'date_time_filter' => array(
          'closed_at' => array(
            'start_at' => $start->format(DATE_RFC3339),
            'end_at'   => $end->format(DATE_RFC3339),
          ),
        ),
        'state_filter' => array(
          'states' => array('COMPLETED', 'CANCELED'),
        ),
      ),
      'sort' => array(
        'sort_field' => 'CLOSED_AT',
        'sort_order' => 'ASC',
      ),
    ),
    'limit' => 500,
  );

  $cursor = null;
  $upserted = 0;

  $pairs = array(); // variation_id|version -> array('id'=>, 'v'=>)

  while (true) {
    if ($cursor) {
      $body['cursor'] = $cursor;
    } else {
      unset($body['cursor']);
    }

    $resp = vms_square_api_request('POST', '/v2/orders/search', $body);

    $orders = isset($resp['orders']) && is_array($resp['orders']) ? $resp['orders'] : array();
    foreach ($orders as $order) {
      if (!is_array($order)) {
        continue;
      }

      $order_id = (string)($order['id'] ?? '');
      if (!$order_id) {
        continue;
      }

      $closed_at = (string)($order['closed_at'] ?? '');
      $created_at = (string)($order['created_at'] ?? '');
      $state = (string)($order['state'] ?? '');

      $closed_ts = $closed_at ? strtotime($closed_at) : 0;
      $created_ts = $created_at ? strtotime($created_at) : 0;

      $total = vms_square_money_amount($order, 'total_money');
      $tax = vms_square_money_amount($order, 'total_tax_money');
      $discount = vms_square_money_amount($order, 'total_discount_money');
      $tip = vms_square_money_amount($order, 'total_tip_money');
      $svc = vms_square_money_amount($order, 'total_service_charge_money');

      $line_items = isset($order['line_items']) && is_array($order['line_items']) ? $order['line_items'] : array();
      $tenders = isset($order['tenders']) && is_array($order['tenders']) ? $order['tenders'] : array();

      foreach ($line_items as $li) {
        if (!is_array($li)) {
          continue;
        }
        $var_id = (string)($li['catalog_object_id'] ?? '');
        $ver = isset($li['catalog_version']) ? (int)$li['catalog_version'] : 0;
        if ($var_id !== '' && $ver > 0) {
          $k = $var_id . '|' . $ver;
          $pairs[$k] = array('id' => $var_id, 'v' => $ver);
        }
      }

      $table = vms_square_table_orders();

      $sql = $wpdb->prepare(
        "INSERT INTO {$table}
          (square_order_id, location_id, business_date, state,
           closed_at_rfc3339, closed_at_utc, created_at_rfc3339, created_at_utc,
           total_amount, tax_amount, discount_amount, tip_amount, service_charge_amount,
           line_items_json, tenders_json, raw_json, imported_at_utc)
         VALUES
          (%s, %s, %s, %s,
           %s, %s, %s, %s,
           %d, %d, %d, %d, %d,
           %s, %s, %s, %s)
         ON DUPLICATE KEY UPDATE
           state = VALUES(state),
           closed_at_rfc3339 = VALUES(closed_at_rfc3339),
           closed_at_utc = VALUES(closed_at_utc),
           created_at_rfc3339 = VALUES(created_at_rfc3339),
           created_at_utc = VALUES(created_at_utc),
           total_amount = VALUES(total_amount),
           tax_amount = VALUES(tax_amount),
           discount_amount = VALUES(discount_amount),
           tip_amount = VALUES(tip_amount),
           service_charge_amount = VALUES(service_charge_amount),
           line_items_json = VALUES(line_items_json),
           tenders_json = VALUES(tenders_json),
           raw_json = VALUES(raw_json),
           imported_at_utc = VALUES(imported_at_utc)
        ",
        $order_id,
        $location_id,
        $biz_date,
        $state,
        $closed_at,
        $closed_ts ? gmdate('Y-m-d H:i:s', $closed_ts) : null,
        $created_at,
        $created_ts ? gmdate('Y-m-d H:i:s', $created_ts) : null,
        (int)$total,
        (int)$tax,
        (int)$discount,
        (int)$tip,
        (int)$svc,
        wp_json_encode($line_items),
        wp_json_encode($tenders),
        wp_json_encode($order),
        gmdate('Y-m-d H:i:s')
      );

      $wpdb->query($sql);
      $upserted++;
    }

    $cursor = isset($resp['cursor']) ? (string)$resp['cursor'] : '';
    if (!$cursor) {
      break;
    }
  }

  $catalog_new = 0;
  if (!empty($pairs)) {
    $catalog_new = vms_square_catalog_map_prime(array_values($pairs));
  }

  vms_square_sync_rollup_day($location_id, $biz_date);

  return array(
    'ok' => true,
    'orders_upserted' => $upserted,
    'catalog_map_new' => (int)$catalog_new,
    'door_rollup_updated' => 1,
  );
}

/**
 * Daily rollup
 */
function vms_square_sync_rollup_day(string $location_id, string $biz_date): void
{
  global $wpdb;

  $orders = vms_square_table_orders();
  $daily = vms_square_table_daily();

  $row = $wpdb->get_row(
    $wpdb->prepare(
      "SELECT
         COUNT(*) as order_count,
         COALESCE(SUM(total_amount),0) as gross_amount,
         COALESCE(SUM(tax_amount),0) as tax_amount,
         COALESCE(SUM(discount_amount),0) as discount_amount,
         COALESCE(SUM(tip_amount),0) as tip_amount,
         COALESCE(SUM(service_charge_amount),0) as service_charge_amount
       FROM {$orders}
       WHERE location_id = %s AND business_date = %s AND state = 'COMPLETED'
      ",
      $location_id,
      $biz_date
    ),
    ARRAY_A
  );

  if (!$row) {
    return;
  }

  $door = vms_square_compute_door_totals($location_id, $biz_date);

  $sql = $wpdb->prepare(
    "INSERT INTO {$daily}
      (location_id, business_date,
       order_count, gross_amount, tax_amount, discount_amount, tip_amount, service_charge_amount,

       door_line_item_count, door_line_items_missing_category,
       door_gross_sales_amount, door_discount_amount, door_tax_amount, door_total_amount, door_net_ex_tax_amount,

       updated_at_utc)
     VALUES
      (%s, %s,
       %d, %d, %d, %d, %d, %d,

       %d, %d,
       %d, %d, %d, %d, %d,

       %s)
     ON DUPLICATE KEY UPDATE
      order_count = VALUES(order_count),
      gross_amount = VALUES(gross_amount),
      tax_amount = VALUES(tax_amount),
      discount_amount = VALUES(discount_amount),
      tip_amount = VALUES(tip_amount),
      service_charge_amount = VALUES(service_charge_amount),

      door_line_item_count = VALUES(door_line_item_count),
      door_line_items_missing_category = VALUES(door_line_items_missing_category),
      door_gross_sales_amount = VALUES(door_gross_sales_amount),
      door_discount_amount = VALUES(door_discount_amount),
      door_tax_amount = VALUES(door_tax_amount),
      door_total_amount = VALUES(door_total_amount),
      door_net_ex_tax_amount = VALUES(door_net_ex_tax_amount),

      updated_at_utc = VALUES(updated_at_utc)
    ",
    $location_id,
    $biz_date,

    (int)$row['order_count'],
    (int)$row['gross_amount'],
    (int)$row['tax_amount'],
    (int)$row['discount_amount'],
    (int)$row['tip_amount'],
    (int)$row['service_charge_amount'],

    (int)($door['door_line_item_count'] ?? 0),
    (int)($door['door_line_items_missing_category'] ?? 0),
    (int)($door['door_gross_sales_amount'] ?? 0),
    (int)($door['door_discount_amount'] ?? 0),
    (int)($door['door_tax_amount'] ?? 0),
    (int)($door['door_total_amount'] ?? 0),
    (int)($door['door_net_ex_tax_amount'] ?? 0),

    gmdate('Y-m-d H:i:s')
  );

  $wpdb->query($sql);
}

/**
 * Compute Door totals for location/date using:
 * - Orders line items
 * - Cached catalog mapping (variation + version -> reporting category)
 * - Settings: door_category_ids (reporting category ids; legacy catalog category ids still supported)
 */
function vms_square_compute_door_totals(string $location_id, string $biz_date): array
{
  global $wpdb;

  $door_category_ids = vms_square_effective_door_category_ids();
  if (empty($door_category_ids)) {
    return array(
      'door_line_item_count' => 0,
      'door_line_items_missing_category' => 0,
      'door_gross_sales_amount' => 0,
      'door_discount_amount' => 0,
      'door_tax_amount' => 0,
      'door_total_amount' => 0,
      'door_net_ex_tax_amount' => 0,
    );
  }

  $table = vms_square_table_orders();
  $rows = $wpdb->get_results(
    $wpdb->prepare(
      "SELECT line_items_json
       FROM {$table}
       WHERE location_id = %s AND business_date = %s AND state = 'COMPLETED'
      ",
      $location_id,
      $biz_date
    ),
    ARRAY_A
  );

  if (!$rows) {
    return array(
      'door_line_item_count' => 0,
      'door_line_items_missing_category' => 0,
      'door_gross_sales_amount' => 0,
      'door_discount_amount' => 0,
      'door_tax_amount' => 0,
      'door_total_amount' => 0,
      'door_net_ex_tax_amount' => 0,
    );
  }

  $door_line_item_count = 0;
  $missing_category = 0;

  $door_gross_sales = 0;
  $door_discount = 0;
  $door_tax = 0;
  $door_total = 0;
  $door_net_ex_tax = 0;

  foreach ($rows as $r) {
    $json = (string)($r['line_items_json'] ?? '');
    if ($json === '') {
      continue;
    }

    $line_items = json_decode($json, true);
    if (!is_array($line_items)) {
      continue;
    }

    foreach ($line_items as $li) {
      if (!is_array($li)) {
        continue;
      }

      $var_id = (string)($li['catalog_object_id'] ?? '');
      $ver = isset($li['catalog_version']) ? (int)$li['catalog_version'] : 0;

      if ($var_id === '' || $ver <= 0) {
        $missing_category++;
        continue;
      }

      $cat_id = vms_square_catalog_map_get_reporting_category_id($var_id, $ver);
      if ($cat_id === '') {
        $missing_category++;
        continue;
      }

      if (!in_array($cat_id, $door_category_ids, true)) {
        continue;
      }

      $door_line_item_count++;

      $gross_sales_amt = vms_square_money_amount_from_money_obj($li, 'gross_sales_money');
      $discount_amt = vms_square_money_amount_from_money_obj($li, 'total_discount_money');
      $tax_amt = vms_square_money_amount_from_money_obj($li, 'total_tax_money');
      $total_amt = vms_square_money_amount_from_money_obj($li, 'total_money');

      $door_gross_sales += (int)$gross_sales_amt;
      $door_discount += (int)$discount_amt;
      $door_tax += (int)$tax_amt;
      $door_total += (int)$total_amt;

      $door_net_ex_tax += (int)max(0, $total_amt - $tax_amt);
    }
  }

  return array(
    'door_line_item_count' => (int)$door_line_item_count,
    'door_line_items_missing_category' => (int)$missing_category,
    'door_gross_sales_amount' => (int)$door_gross_sales,
    'door_discount_amount' => (int)$door_discount,
    'door_tax_amount' => (int)$door_tax,
    'door_total_amount' => (int)$door_total,
    'door_net_ex_tax_amount' => (int)$door_net_ex_tax,
  );
}

/**
 * Money helpers
 */
function vms_square_money_amount(array $order, string $field): int
{
  if (!isset($order[$field]) || !is_array($order[$field])) {
    return 0;
  }
  $amt = $order[$field]['amount'] ?? 0;
  return (int)$amt;
}

function vms_square_money_amount_from_money_obj(array $obj, string $field): int
{
  if (!isset($obj[$field]) || !is_array($obj[$field])) {
    return 0;
  }
  $amt = $obj[$field]['amount'] ?? 0;
  return (int)$amt;
}

function vms_square_actuals_log(string $message): void
{
  error_log('[VMS Square Actuals] ' . $message);
}

function vms_square_actuals_zero_totals(): array
{
  return array(
    'gross' => 0,
    'tax' => 0,
    'discount' => 0,
    'tip' => 0,
    'service_charges' => 0,
    'net_ex_tax' => 0,
  );
}

function vms_square_actuals_bucket_zero_totals(): array
{
  return array(
    'gross' => 0,
    'tax' => 0,
    'discount' => 0,
    'tip' => 0,
    'service_charges' => 0,
    'net_ex_tax' => 0,
    'line_item_count' => 0,
    'missing_category_count' => 0,
  );
}

function vms_square_actuals_empty_result(string $location_id, string $window_start_utc, string $window_end_utc): array
{
  return array(
    'provider' => 'square',
    'location_id' => $location_id,
    'window_start_utc' => $window_start_utc,
    'window_end_utc' => $window_end_utc,
    'currency' => 'USD',
    'totals' => vms_square_actuals_zero_totals(),
    'buckets' => array(),
    'meta' => array(
      'pulled_at_utc' => gmdate('Y-m-d H:i:s'),
      'source' => 'local_orders_table',
      'orders_count' => 0,
      'errors' => array(),
      'error_codes' => array(),
      'missing_category_count_total' => 0,
      'mapping_marker' => vms_square_catalog_map_marker(),
      'bucket_labels' => vms_square_effective_bucket_labels(),
    ),
  );
}

function vms_square_parse_utc_datetime(string $value): string
{
  $value = trim($value);
  if ($value === '') {
    return '';
  }

  try {
    $dt = new DateTimeImmutable($value, new DateTimeZone('UTC'));
    return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
  } catch (Throwable $e) {
    return '';
  }
}

function vms_square_extract_currency_from_order_payload(array $order): string
{
  $paths = array(
    array('total_money', 'currency'),
    array('total_tax_money', 'currency'),
    array('total_discount_money', 'currency'),
    array('total_tip_money', 'currency'),
    array('total_service_charge_money', 'currency'),
  );

  foreach ($paths as $path) {
    $node = $order;
    $ok = true;
    foreach ($path as $part) {
      if (!is_array($node) || !array_key_exists($part, $node)) {
        $ok = false;
        break;
      }
      $node = $node[$part];
    }
    if ($ok && is_string($node) && $node !== '') {
      return strtoupper($node);
    }
  }

  return '';
}

function vms_square_extract_currency_from_raw_json(string $raw_json): string
{
  if ($raw_json === '') {
    return '';
  }

  $payload = json_decode($raw_json, true);
  if (!is_array($payload)) {
    return '';
  }

  return vms_square_extract_currency_from_order_payload($payload);
}

function vms_square_category_cache_type_label(string $type): string
{
  $type = strtoupper(trim($type));
  if ($type === 'REPORTING_CATEGORY') {
    return 'Reporting';
  }
  if ($type === 'CATEGORY') {
    return 'Category';
  }
  return '';
}

function vms_square_category_cache_normalize_row(array $row): array
{
  $id = trim(sanitize_text_field((string)($row['id'] ?? '')));
  if ($id === '') {
    return array();
  }

  $name = sanitize_text_field((string)($row['name'] ?? ''));
  if ($name === '') {
    $name = $id;
  }

  $type = strtoupper(trim(sanitize_text_field((string)($row['type'] ?? ''))));
  if (!in_array($type, array('REPORTING_CATEGORY', 'CATEGORY'), true)) {
    $type = '';
  }

  return array(
    'id' => $id,
    'name' => $name,
    'type' => $type,
  );
}

function vms_square_categories_cache_sort(array $rows): array
{
  usort($rows, function ($a, $b) {
    $priority = array(
      'REPORTING_CATEGORY' => 0,
      'CATEGORY' => 1,
      '' => 2,
    );

    $type_a = strtoupper(trim((string)($a['type'] ?? '')));
    $type_b = strtoupper(trim((string)($b['type'] ?? '')));
    $prio_a = $priority[$type_a] ?? 2;
    $prio_b = $priority[$type_b] ?? 2;

    if ($prio_a !== $prio_b) {
      return $prio_a <=> $prio_b;
    }

    $name_a = (string)($a['name'] ?? $a['id'] ?? '');
    $name_b = (string)($b['name'] ?? $b['id'] ?? '');
    $cmp = strcasecmp($name_a, $name_b);
    if ($cmp !== 0) {
      return $cmp;
    }

    return strcmp((string)($a['id'] ?? ''), (string)($b['id'] ?? ''));
  });

  return $rows;
}

function vms_square_categories_cache_get(): array
{
  $cache = vms_square_setting('categories_cache', array());
  if (!is_array($cache)) {
    return array();
  }

  $rows = array();
  $priority = array(
    'REPORTING_CATEGORY' => 0,
    'CATEGORY' => 1,
    '' => 2,
  );

  foreach ($cache as $row) {
    if (!is_array($row)) {
      continue;
    }

    $normalized = vms_square_category_cache_normalize_row($row);
    if (empty($normalized)) {
      continue;
    }

    $id = (string)$normalized['id'];
    $new_prio = $priority[(string)$normalized['type']] ?? 2;
    $old_prio = isset($rows[$id]['type']) ? ($priority[(string)$rows[$id]['type']] ?? 2) : 99;
    if (!isset($rows[$id]) || $new_prio < $old_prio) {
      $rows[$id] = $normalized;
    }
  }

  return vms_square_categories_cache_sort(array_values($rows));
}

function vms_square_category_option_label(array $row): string
{
  $normalized = vms_square_category_cache_normalize_row($row);
  if (empty($normalized)) {
    return '';
  }

  $label = (string)$normalized['name'];
  $type_label = vms_square_category_cache_type_label((string)$normalized['type']);
  if ($type_label !== '') {
    $label .= ' [' . $type_label . ']';
  }

  return $label . ' (' . (string)$normalized['id'] . ')';
}

function vms_square_catalog_list_objects_by_type(string $type): array
{
  $type = strtoupper(trim($type));
  if (!in_array($type, array('REPORTING_CATEGORY', 'CATEGORY'), true)) {
    return array();
  }

  $objects = array();
  $cursor = '';
  $seen_cursors = array();

  while (true) {
    $path = '/v2/catalog/list?types=' . rawurlencode($type);
    if ($cursor !== '') {
      $path .= '&cursor=' . rawurlencode($cursor);
      if (isset($seen_cursors[$cursor])) {
        break;
      }
      $seen_cursors[$cursor] = true;
    }

    $resp = vms_square_api_request('GET', $path);
    $batch = isset($resp['objects']) && is_array($resp['objects']) ? $resp['objects'] : array();
    foreach ($batch as $obj) {
      if (is_array($obj)) {
        $objects[] = $obj;
      }
    }

    $cursor = isset($resp['cursor']) ? trim((string)$resp['cursor']) : '';
    if ($cursor === '') {
      break;
    }
  }

  return $objects;
}

function vms_square_fetch_category_cache_from_api(): array
{
  $rows = array();

  foreach (array('REPORTING_CATEGORY', 'CATEGORY') as $type) {
    $objects = vms_square_catalog_list_objects_by_type($type);
    foreach ($objects as $obj) {
      if (!is_array($obj)) {
        continue;
      }

      $object_type = strtoupper(trim((string)($obj['type'] ?? '')));
      if ($object_type !== $type) {
        continue;
      }

      $id = trim((string)($obj['id'] ?? ''));
      if ($id === '') {
        continue;
      }

      $name = '';
      if ($type === 'REPORTING_CATEGORY' && isset($obj['reporting_category_data']) && is_array($obj['reporting_category_data'])) {
        $name = (string)($obj['reporting_category_data']['name'] ?? '');
      } elseif ($type === 'CATEGORY' && isset($obj['category_data']) && is_array($obj['category_data'])) {
        $name = (string)($obj['category_data']['name'] ?? '');
      }

      $normalized = vms_square_category_cache_normalize_row(array(
        'id' => $id,
        'name' => $name,
        'type' => $type,
      ));
      if (empty($normalized)) {
        continue;
      }

      $rows[$id] = $normalized;
    }
  }

  return vms_square_categories_cache_sort(array_values($rows));
}

function vms_square_catalog_map_marker(): string
{
  global $wpdb;
  $table = vms_square_table_catalog_map();
  $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
  if ((string)$exists !== (string)$table) {
    return '';
  }
  $max = $wpdb->get_var("SELECT MAX(updated_at_utc) FROM {$table}");
  if (!is_string($max) || $max === '') {
    return '';
  }
  return $max;
}

function vms_square_allocate_amount_by_weights(int $amount, array $weights): array
{
  $alloc = array();
  foreach ($weights as $key => $weight) {
    $alloc[(string)$key] = 0;
  }

  if ($amount === 0 || empty($weights)) {
    return $alloc;
  }

  $clean = array();
  $sum = 0;
  foreach ($weights as $key => $weight) {
    $w = max(0, (int)$weight);
    $clean[(string)$key] = $w;
    $sum += $w;
  }

  if ($sum <= 0) {
    return $alloc;
  }

  $keys = array_keys($clean);
  $count = count($keys);
  $distributed = 0;

  foreach ($keys as $idx => $key) {
    if ($idx === ($count - 1)) {
      $share = $amount - $distributed;
    } else {
      $share = (int) floor(($amount * $clean[$key]) / $sum);
      $distributed += $share;
    }
    $alloc[$key] = $share;
  }

  return $alloc;
}

/**
 * Public provider-neutral API for POS totals and configured buckets.
 */
function vms_square_get_sales_summary(string $location_id, string $window_start_utc, string $window_end_utc, array $args = array()): array
{
  global $wpdb;

  $summary = vms_square_actuals_empty_result($location_id, $window_start_utc, $window_end_utc);

  $location_id = trim($location_id);
  $window_start_utc = vms_square_parse_utc_datetime($window_start_utc);
  $window_end_utc = vms_square_parse_utc_datetime($window_end_utc);

  $summary['location_id'] = $location_id;
  $summary['window_start_utc'] = $window_start_utc;
  $summary['window_end_utc'] = $window_end_utc;

  $errors = array();
  $error_codes = array();

  if ($location_id === '') {
    $errors[] = 'Missing Square location id.';
    $error_codes[] = 'missing_location_id';
  }

  if ($window_start_utc === '') {
    $errors[] = 'Invalid window start UTC.';
    $error_codes[] = 'invalid_window_start';
  }

  if ($window_end_utc === '') {
    $errors[] = 'Invalid window end UTC.';
    $error_codes[] = 'invalid_window_end';
  }

  if ($window_start_utc !== '' && $window_end_utc !== '' && strtotime($window_end_utc . ' UTC') <= strtotime($window_start_utc . ' UTC')) {
    $errors[] = 'Window end must be after window start.';
    $error_codes[] = 'window_end_not_after_start';
  }

  if (!empty($errors)) {
    $summary['meta']['errors'] = $errors;
    $summary['meta']['error_codes'] = $error_codes;
    vms_square_actuals_log('Sales summary validation failed: ' . implode(' | ', $errors));
    return $summary;
  }

  $states = array('COMPLETED');
  if (isset($args['states']) && is_array($args['states'])) {
    $states = array();
    foreach ($args['states'] as $state) {
      $s = strtoupper(trim(sanitize_text_field((string)$state)));
      if ($s !== '') {
        $states[] = $s;
      }
    }
    $states = array_values(array_unique($states));
  } elseif (isset($args['state'])) {
    $s = strtoupper(trim(sanitize_text_field((string)$args['state'])));
    if ($s !== '') {
      $states = array($s);
    }
  }
  if (empty($states)) {
    $states = array('COMPLETED');
  }

  $orders_table = vms_square_table_orders();
  $orders_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $orders_table));
  if ((string)$orders_exists !== (string)$orders_table) {
    $summary['meta']['errors'][] = 'Square orders table is unavailable. Run Square Sync setup first.';
    $summary['meta']['error_codes'][] = 'orders_table_missing';
    vms_square_actuals_log('Orders table missing while building sales summary.');
    return $summary;
  }

  $state_placeholders = implode(',', array_fill(0, count($states), '%s'));

  $totals_sql = "SELECT
      COUNT(*) AS orders_count,
      COALESCE(SUM(total_amount),0) AS gross_amount,
      COALESCE(SUM(tax_amount),0) AS tax_amount,
      COALESCE(SUM(discount_amount),0) AS discount_amount,
      COALESCE(SUM(tip_amount),0) AS tip_amount,
      COALESCE(SUM(service_charge_amount),0) AS service_charge_amount
    FROM {$orders_table}
    WHERE location_id = %s
      AND closed_at_utc >= %s
      AND closed_at_utc < %s
      AND state IN ({$state_placeholders})";
  $totals_params = array_merge(array($location_id, $window_start_utc, $window_end_utc), $states);
  $totals_row = $wpdb->get_row($wpdb->prepare($totals_sql, ...$totals_params), ARRAY_A);
  if (!is_array($totals_row)) {
    $totals_row = array();
  }

  $gross = (int)($totals_row['gross_amount'] ?? 0);
  $tax = (int)($totals_row['tax_amount'] ?? 0);
  $discount = (int)($totals_row['discount_amount'] ?? 0);
  $tip = (int)($totals_row['tip_amount'] ?? 0);
  $service = (int)($totals_row['service_charge_amount'] ?? 0);
  $orders_count = (int)($totals_row['orders_count'] ?? 0);

  $summary['totals'] = array(
    'gross' => $gross,
    'tax' => $tax,
    'discount' => $discount,
    'tip' => $tip,
    'service_charges' => $service,
    'net_ex_tax' => max(0, $gross - $tax),
  );
  $summary['meta']['orders_count'] = $orders_count;

  $currency_sql = "SELECT raw_json
    FROM {$orders_table}
    WHERE location_id = %s
      AND closed_at_utc >= %s
      AND closed_at_utc < %s
      AND state IN ({$state_placeholders})
      AND raw_json IS NOT NULL
      AND raw_json <> ''
    ORDER BY closed_at_utc ASC
    LIMIT 1";
  $currency_params = array_merge(array($location_id, $window_start_utc, $window_end_utc), $states);
  $raw_json = $wpdb->get_var($wpdb->prepare($currency_sql, ...$currency_params));
  if (is_string($raw_json) && $raw_json !== '') {
    $currency = vms_square_extract_currency_from_raw_json($raw_json);
    if ($currency !== '') {
      $summary['currency'] = $currency;
    }
  }

  $bucket_map = vms_square_effective_bucket_category_ids();
  $bucket_labels = vms_square_effective_bucket_labels();
  $summary['meta']['bucket_labels'] = $bucket_labels;

  foreach ($bucket_map as $bucket_key => $cat_ids) {
    $summary['buckets'][$bucket_key] = vms_square_actuals_bucket_zero_totals();
  }

  if (empty($bucket_map)) {
    if ($orders_count === 0) {
      $summary['meta']['errors'][] = 'No orders found in the selected window.';
      $summary['meta']['error_codes'][] = 'no_orders_found';
    }
    return $summary;
  }

  $category_to_bucket = array();
  $duplicate_category_ids = array();
  foreach ($bucket_map as $bucket_key => $cat_ids) {
    foreach ($cat_ids as $cat_id) {
      if (!is_string($cat_id) || trim($cat_id) === '') {
        continue;
      }
      $cat_id = trim($cat_id);
      if (isset($category_to_bucket[$cat_id]) && $category_to_bucket[$cat_id] !== $bucket_key) {
        $duplicate_category_ids[$cat_id] = true;
        continue;
      }
      $category_to_bucket[$cat_id] = $bucket_key;
    }
  }

  if (!empty($duplicate_category_ids)) {
    $summary['meta']['errors'][] = 'Some category IDs are mapped to multiple buckets; the first mapping wins.';
    $summary['meta']['error_codes'][] = 'duplicate_bucket_category_ids';
  }

  $batch_size = isset($args['batch_size']) ? (int)$args['batch_size'] : 250;
  $batch_size = max(50, min(1000, $batch_size));
  $last_id = 0;
  $missing_category_total = 0;
  $missing_bucket_key = isset($summary['buckets']['other']) ? 'other' : '';

  while (true) {
    $line_sql = "SELECT id, line_items_json, raw_json, tip_amount, service_charge_amount
      FROM {$orders_table}
      WHERE location_id = %s
        AND closed_at_utc >= %s
        AND closed_at_utc < %s
        AND state IN ({$state_placeholders})
        AND id > %d
      ORDER BY id ASC
      LIMIT %d";
    $line_params = array_merge(array($location_id, $window_start_utc, $window_end_utc), $states, array($last_id, $batch_size));
    $rows = $wpdb->get_results($wpdb->prepare($line_sql, ...$line_params), ARRAY_A);
    if (!is_array($rows) || empty($rows)) {
      break;
    }

    foreach ($rows as $row) {
      $row_id = (int)($row['id'] ?? 0);
      if ($row_id > $last_id) {
        $last_id = $row_id;
      }

      if ($summary['currency'] === 'USD') {
        $row_currency = vms_square_extract_currency_from_raw_json((string)($row['raw_json'] ?? ''));
        if ($row_currency !== '') {
          $summary['currency'] = $row_currency;
        }
      }

      $line_items_json = (string)($row['line_items_json'] ?? '');
      if ($line_items_json === '') {
        continue;
      }

      $line_items = json_decode($line_items_json, true);
      if (!is_array($line_items)) {
        continue;
      }

      $order_bucket_weights = array();
      foreach (array_keys($summary['buckets']) as $bucket_key) {
        $order_bucket_weights[$bucket_key] = 0;
      }

      foreach ($line_items as $line_item) {
        if (!is_array($line_item)) {
          continue;
        }

        $variation_id = (string)($line_item['catalog_object_id'] ?? '');
        $catalog_version = isset($line_item['catalog_version']) ? (int)$line_item['catalog_version'] : 0;
        if ($variation_id === '' || $catalog_version <= 0) {
          $missing_category_total++;
          if ($missing_bucket_key !== '') {
            $summary['buckets'][$missing_bucket_key]['missing_category_count'] += 1;
          }
          continue;
        }

        $category_id = vms_square_catalog_map_get_reporting_category_id($variation_id, $catalog_version);
        if ($category_id === '') {
          $missing_category_total++;
          if ($missing_bucket_key !== '') {
            $summary['buckets'][$missing_bucket_key]['missing_category_count'] += 1;
          }
          continue;
        }

        $bucket_key = $category_to_bucket[$category_id] ?? '';
        if ($bucket_key === '' || !isset($summary['buckets'][$bucket_key])) {
          continue;
        }

        $line_total = vms_square_money_amount_from_money_obj($line_item, 'total_money');
        $line_tax = vms_square_money_amount_from_money_obj($line_item, 'total_tax_money');
        $line_discount = vms_square_money_amount_from_money_obj($line_item, 'total_discount_money');

        $summary['buckets'][$bucket_key]['gross'] += (int)$line_total;
        $summary['buckets'][$bucket_key]['tax'] += (int)$line_tax;
        $summary['buckets'][$bucket_key]['discount'] += (int)$line_discount;
        $summary['buckets'][$bucket_key]['net_ex_tax'] += (int)max(0, $line_total - $line_tax);
        $summary['buckets'][$bucket_key]['line_item_count'] += 1;
        $order_bucket_weights[$bucket_key] += max(0, (int)$line_total);
      }

      $tip_alloc = vms_square_allocate_amount_by_weights((int)($row['tip_amount'] ?? 0), $order_bucket_weights);
      foreach ($tip_alloc as $bucket_key => $alloc) {
        if (isset($summary['buckets'][$bucket_key])) {
          $summary['buckets'][$bucket_key]['tip'] += (int)$alloc;
        }
      }

      $service_alloc = vms_square_allocate_amount_by_weights((int)($row['service_charge_amount'] ?? 0), $order_bucket_weights);
      foreach ($service_alloc as $bucket_key => $alloc) {
        if (isset($summary['buckets'][$bucket_key])) {
          $summary['buckets'][$bucket_key]['service_charges'] += (int)$alloc;
        }
      }
    }
  }

  $summary['meta']['missing_category_count_total'] = (int)$missing_category_total;

  if ($orders_count === 0) {
    $summary['meta']['errors'][] = 'No orders found in the selected window.';
    $summary['meta']['error_codes'][] = 'no_orders_found';
  }

  if ($missing_category_total > 0) {
    $summary['meta']['errors'][] = 'Orders found but category mapping is missing for ' . (int)$missing_category_total . ' line item(s). Run Catalog Prime / Refresh.';
    $summary['meta']['error_codes'][] = 'missing_category_mappings';
  }

  $summary['meta']['errors'] = array_values(array_unique(array_filter(array_map('strval', (array)$summary['meta']['errors']))));
  $summary['meta']['error_codes'] = array_values(array_unique(array_filter(array_map('strval', (array)$summary['meta']['error_codes']))));

  return $summary;
}

function vms_square_parse_local_datetime(string $value, DateTimeZone $timezone): ?DateTimeImmutable
{
  $value = trim($value);
  if ($value === '') {
    return null;
  }

  $formats = array(
    'Y-m-d H:i',
    'Y-m-d H:i:s',
    'Y-m-d\TH:i',
    'Y-m-d\TH:i:s',
  );

  foreach ($formats as $format) {
    $dt = DateTimeImmutable::createFromFormat($format, $value, $timezone);
    $errors = DateTimeImmutable::getLastErrors();
    $error_count = is_array($errors) ? ((int)$errors['warning_count'] + (int)$errors['error_count']) : 0;
    if ($dt instanceof DateTimeImmutable && $error_count === 0) {
      return $dt;
    }
  }

  return null;
}

function vms_square_sanitize_local_datetime_input(string $value): string
{
  $value = trim(wp_strip_all_tags($value));
  if ($value === '') {
    return '';
  }

  $value = str_replace('T', ' ', $value);
  $value = preg_replace('/\s+/', ' ', $value);
  if (!is_string($value)) {
    return '';
  }

  $dt = vms_square_parse_local_datetime($value, wp_timezone());
  if (!$dt) {
    return '';
  }

  return $dt->format('Y-m-d H:i');
}

function vms_square_get_event_window_for_event_plan(int $event_plan_id, array $args = array()): array
{
  $tz = wp_timezone();
  $utc = new DateTimeZone('UTC');

  $default_mode = vms_square_effective_event_window_default_mode();
  $mode = isset($args['mode']) ? sanitize_text_field((string)$args['mode']) : (string)get_post_meta($event_plan_id, '_vms_pos_window_mode', true);
  if (!in_array($mode, array('business_date', 'event_window'), true)) {
    $mode = $default_mode;
  }

  $business_date = isset($args['business_date']) ? sanitize_text_field((string)$args['business_date']) : (string)get_post_meta($event_plan_id, '_vms_square_business_date', true);
  $window_start_local = isset($args['window_start_local']) ? vms_square_sanitize_local_datetime_input((string)$args['window_start_local']) : vms_square_sanitize_local_datetime_input((string)get_post_meta($event_plan_id, '_vms_pos_window_start_local', true));
  $window_end_local = isset($args['window_end_local']) ? vms_square_sanitize_local_datetime_input((string)$args['window_end_local']) : vms_square_sanitize_local_datetime_input((string)get_post_meta($event_plan_id, '_vms_pos_window_end_local', true));

  $errors = array();
  $error_codes = array();
  $start_dt = null;
  $end_dt = null;

  if ($mode === 'business_date') {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $business_date)) {
      $errors[] = 'Business Date is required for Business Date mode.';
      $error_codes[] = 'missing_business_date';
    } else {
      try {
        $start_dt = new DateTimeImmutable($business_date . ' 00:00:00', $tz);
        $end_dt = $start_dt->modify('+1 day');
        $window_start_local = $start_dt->format('Y-m-d H:i');
        $window_end_local = $end_dt->format('Y-m-d H:i');
      } catch (Throwable $e) {
        $errors[] = 'Unable to compute Business Date window.';
        $error_codes[] = 'business_date_window_parse_failed';
      }
    }
  } else {
    if (($window_start_local === '' || $window_end_local === '') && preg_match('/^\d{4}-\d{2}-\d{2}$/', $business_date)) {
      try {
        $base = new DateTimeImmutable($business_date . ' 00:00:00', $tz);
        $start_offset = (int)vms_square_effective_event_window_default_start_offset_minutes();
        $end_offset = (int)vms_square_effective_event_window_default_end_offset_minutes();

        if ($window_start_local === '') {
          $start_dt_default = $base->modify(($start_offset >= 0 ? '+' : '') . $start_offset . ' minutes');
          $window_start_local = $start_dt_default->format('Y-m-d H:i');
        }
        if ($window_end_local === '') {
          $end_dt_default = $base->modify(($end_offset >= 0 ? '+' : '') . $end_offset . ' minutes');
          $window_end_local = $end_dt_default->format('Y-m-d H:i');
        }
      } catch (Throwable $e) {
        $errors[] = 'Unable to compute default event window from Business Date.';
        $error_codes[] = 'default_event_window_compute_failed';
      }
    }

    if ($window_start_local === '') {
      $errors[] = 'Event window start is required.';
      $error_codes[] = 'missing_event_window_start';
    } else {
      $start_dt = vms_square_parse_local_datetime($window_start_local, $tz);
      if (!$start_dt) {
        $errors[] = 'Event window start is invalid.';
        $error_codes[] = 'invalid_event_window_start';
      }
    }

    if ($window_end_local === '') {
      $errors[] = 'Event window end is required.';
      $error_codes[] = 'missing_event_window_end';
    } else {
      $end_dt = vms_square_parse_local_datetime($window_end_local, $tz);
      if (!$end_dt) {
        $errors[] = 'Event window end is invalid.';
        $error_codes[] = 'invalid_event_window_end';
      }
    }
  }

  $window_start_utc = '';
  $window_end_utc = '';
  if ($start_dt instanceof DateTimeImmutable) {
    $window_start_utc = $start_dt->setTimezone($utc)->format('Y-m-d H:i:s');
  }
  if ($end_dt instanceof DateTimeImmutable) {
    $window_end_utc = $end_dt->setTimezone($utc)->format('Y-m-d H:i:s');
  }

  if ($window_start_utc !== '' && $window_end_utc !== '' && strtotime($window_end_utc . ' UTC') <= strtotime($window_start_utc . ' UTC')) {
    $errors[] = 'Event window end must be after start.';
    $error_codes[] = 'event_window_end_not_after_start';
  }

  return array(
    'mode' => $mode,
    'business_date' => $business_date,
    'window_start_local' => $window_start_local,
    'window_end_local' => $window_end_local,
    'window_start_utc' => $window_start_utc,
    'window_end_utc' => $window_end_utc,
    'errors' => array_values(array_unique(array_filter(array_map('strval', $errors)))),
    'error_codes' => array_values(array_unique(array_filter(array_map('strval', $error_codes)))),
  );
}

function vms_square_get_event_actuals(int $event_plan_id, array $args = array()): array
{
  $location_id = isset($args['location_id']) ? sanitize_text_field((string)$args['location_id']) : (string)get_post_meta($event_plan_id, '_vms_square_location_id', true);
  $window = vms_square_get_event_window_for_event_plan($event_plan_id, $args);

  $summary = vms_square_actuals_empty_result($location_id, (string)($window['window_start_utc'] ?? ''), (string)($window['window_end_utc'] ?? ''));

  if ((string)($window['window_start_utc'] ?? '') !== '' && (string)($window['window_end_utc'] ?? '') !== '') {
    $summary = vms_square_get_sales_summary(
      $location_id,
      (string)$window['window_start_utc'],
      (string)$window['window_end_utc'],
      $args
    );
  } else {
    $summary['meta']['error_codes'][] = 'window_unavailable';
    if (empty($summary['meta']['errors'])) {
      $summary['meta']['errors'][] = 'Event window is unavailable.';
    }
  }

  if (!empty($window['errors'])) {
    $summary['meta']['errors'] = array_merge((array)$summary['meta']['errors'], (array)$window['errors']);
    $summary['meta']['error_codes'] = array_merge((array)$summary['meta']['error_codes'], (array)$window['error_codes']);
  }

  $summary['meta']['event_plan_id'] = $event_plan_id;
  $summary['meta']['window_mode'] = (string)($window['mode'] ?? '');
  $summary['meta']['window_start_local'] = (string)($window['window_start_local'] ?? '');
  $summary['meta']['window_end_local'] = (string)($window['window_end_local'] ?? '');
  $summary['meta']['business_date'] = (string)($window['business_date'] ?? '');
  $summary['meta']['errors'] = array_values(array_unique(array_filter(array_map('strval', (array)$summary['meta']['errors']))));
  $summary['meta']['error_codes'] = array_values(array_unique(array_filter(array_map('strval', (array)$summary['meta']['error_codes']))));

  return $summary;
}

function vms_square_actuals_has_hard_errors(array $summary): bool
{
  $codes = isset($summary['meta']['error_codes']) && is_array($summary['meta']['error_codes'])
    ? $summary['meta']['error_codes']
    : array();

  $hard = array(
    'missing_location_id',
    'invalid_window_start',
    'invalid_window_end',
    'window_end_not_after_start',
    'orders_table_missing',
    'missing_business_date',
    'business_date_window_parse_failed',
    'default_event_window_compute_failed',
    'missing_event_window_start',
    'invalid_event_window_start',
    'missing_event_window_end',
    'invalid_event_window_end',
    'event_window_end_not_after_start',
    'window_unavailable',
  );

  foreach ($codes as $code) {
    if (in_array((string)$code, $hard, true)) {
      return true;
    }
  }
  return false;
}

function vms_square_snapshot_event_actuals(int $event_plan_id, array $args = array()): array
{
  if ($event_plan_id <= 0) {
    return array(
      'ok' => false,
      'message' => 'Invalid event plan id.',
      'data' => array(),
    );
  }

  $summary = vms_square_get_event_actuals($event_plan_id, $args);

  if (vms_square_actuals_has_hard_errors($summary)) {
    $msg = 'Unable to pull POS actuals: ' . implode(' | ', (array)($summary['meta']['errors'] ?? array('Unknown error.')));
    vms_square_actuals_log('Snapshot failed for event ' . $event_plan_id . ': ' . $msg);
    return array(
      'ok' => false,
      'message' => $msg,
      'data' => $summary,
    );
  }

  $pulled_at = (string)($summary['meta']['pulled_at_utc'] ?? gmdate('Y-m-d H:i:s'));
  $window_start_utc = (string)($summary['window_start_utc'] ?? '');
  $window_end_utc = (string)($summary['window_end_utc'] ?? '');
  $mapping_marker = (string)($summary['meta']['mapping_marker'] ?? '');

  update_post_meta($event_plan_id, '_vms_pos_actuals_provider', 'square');
  update_post_meta($event_plan_id, '_vms_pos_actuals_pulled_at_utc', $pulled_at);
  update_post_meta($event_plan_id, '_vms_pos_actuals_window_start_utc', $window_start_utc);
  update_post_meta($event_plan_id, '_vms_pos_actuals_window_end_utc', $window_end_utc);
  update_post_meta($event_plan_id, '_vms_pos_actuals_mapping_marker', $mapping_marker);

  $mode = (string)($summary['meta']['window_mode'] ?? vms_square_effective_event_window_default_mode());
  if (!in_array($mode, array('business_date', 'event_window'), true)) {
    $mode = vms_square_effective_event_window_default_mode();
  }
  update_post_meta($event_plan_id, '_vms_pos_window_mode', $mode);

  $window_start_local = (string)($summary['meta']['window_start_local'] ?? '');
  $window_end_local = (string)($summary['meta']['window_end_local'] ?? '');

  if ($window_start_local !== '') {
    update_post_meta($event_plan_id, '_vms_pos_window_start_local', $window_start_local);
  } else {
    delete_post_meta($event_plan_id, '_vms_pos_window_start_local');
  }
  if ($window_end_local !== '') {
    update_post_meta($event_plan_id, '_vms_pos_window_end_local', $window_end_local);
  } else {
    delete_post_meta($event_plan_id, '_vms_pos_window_end_local');
  }

  $snapshot_payload = array(
    'currency' => (string)($summary['currency'] ?? 'USD'),
    'source' => (string)($summary['meta']['source'] ?? 'local_orders_table'),
    'business_date' => (string)($summary['meta']['business_date'] ?? ''),
    'window_mode' => $mode,
    'window_start_local' => $window_start_local,
    'window_end_local' => $window_end_local,
    'totals' => isset($summary['totals']) && is_array($summary['totals']) ? $summary['totals'] : vms_square_actuals_zero_totals(),
    'buckets' => isset($summary['buckets']) && is_array($summary['buckets']) ? $summary['buckets'] : array(),
    'orders_count' => (int)($summary['meta']['orders_count'] ?? 0),
    'errors' => array_values(array_filter(array_map('strval', (array)($summary['meta']['errors'] ?? array())))),
    'missing_category_count_total' => (int)($summary['meta']['missing_category_count_total'] ?? 0),
    'bucket_labels' => isset($summary['meta']['bucket_labels']) && is_array($summary['meta']['bucket_labels']) ? $summary['meta']['bucket_labels'] : array(),
  );

  update_post_meta($event_plan_id, '_vms_pos_actuals_totals', $snapshot_payload);

  $message = 'POS actuals snapshot saved.';
  if (!empty($snapshot_payload['errors'])) {
    $message .= ' Warnings: ' . implode(' | ', $snapshot_payload['errors']);
  }

  return array(
    'ok' => true,
    'message' => $message,
    'data' => $summary,
  );
}

/**
 * Catalog mapping cache (variation + version -> reporting category id)
 */
function vms_square_catalog_map_prime(array $pairs): int
{
  $new = 0;

  $need = array();
  foreach ($pairs as $p) {
    if (!is_array($p)) {
      continue;
    }
    $id = (string)($p['id'] ?? '');
    $v = isset($p['v']) ? (int)$p['v'] : 0;
    if ($id === '' || $v <= 0) {
      continue;
    }

    $existing = vms_square_catalog_map_get_reporting_category_id($id, $v);
    if ($existing !== '') {
      continue;
    }

    $need[] = array('id' => $id, 'v' => $v);
  }

  if (empty($need)) {
    return 0;
  }

  $by_version = array();
  foreach ($need as $p) {
    $v = (int)$p['v'];
    $by_version[$v] = $by_version[$v] ?? array();
    $by_version[$v][] = (string)$p['id'];
  }

  foreach ($by_version as $ver => $ids) {
    $ids = array_values(array_unique(array_filter($ids)));
    if (empty($ids)) {
      continue;
    }

    $chunks = array_chunk($ids, 200);

    foreach ($chunks as $chunk) {
      $body = array(
        'object_ids' => array_values($chunk),
        'include_related_objects' => true,
        'catalog_version' => (int)$ver,
      );

      $resp = vms_square_api_request('POST', '/v2/catalog/batch-retrieve', $body);
      $new += vms_square_catalog_map_ingest_batch_retrieve_response($resp, (int)$ver);
    }
  }

  return (int)$new;
}

function vms_square_catalog_map_ingest_batch_retrieve_response(array $resp, int $catalog_version): int
{
  global $wpdb;

  $objects = isset($resp['objects']) && is_array($resp['objects']) ? $resp['objects'] : array();
  $related = isset($resp['related_objects']) && is_array($resp['related_objects']) ? $resp['related_objects'] : array();

  $items = array();
  foreach (array_merge($objects, $related) as $obj) {
    if (!is_array($obj)) {
      continue;
    }
    $type = (string)($obj['type'] ?? '');
    if ($type === 'ITEM') {
      $id = (string)($obj['id'] ?? '');
      if ($id !== '') {
        $items[$id] = $obj;
      }
    }
  }

  $table = vms_square_table_catalog_map();
  $new = 0;

  foreach ($objects as $obj) {
    if (!is_array($obj)) {
      continue;
    }
    $type = (string)($obj['type'] ?? '');
    $id = (string)($obj['id'] ?? '');
    if ($id === '') {
      continue;
    }

    if ($type === 'ITEM_VARIATION') {
      $item_id = '';
      $variation_name = '';

      if (isset($obj['item_variation_data']) && is_array($obj['item_variation_data'])) {
        $item_id = (string)($obj['item_variation_data']['item_id'] ?? '');
        $variation_name = (string)($obj['item_variation_data']['name'] ?? '');
      }

      $item_obj = $item_id && isset($items[$item_id]) ? $items[$item_id] : null;
      $item_name = '';
      $category_id = '';

      if (is_array($item_obj) && isset($item_obj['item_data']) && is_array($item_obj['item_data'])) {
        $idata = $item_obj['item_data'];
        $item_name = (string)($idata['name'] ?? '');

        if (isset($idata['reporting_category']) && is_array($idata['reporting_category'])) {
          $category_id = (string)($idata['reporting_category']['id'] ?? '');
        } elseif (isset($idata['categories']) && is_array($idata['categories']) && !empty($idata['categories'][0]) && is_array($idata['categories'][0])) {
          $category_id = (string)($idata['categories'][0]['id'] ?? '');
        } elseif (isset($idata['category_id'])) {
          $category_id = (string)$idata['category_id'];
        }
      }

      $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$table} WHERE variation_id = %s AND catalog_version = %d LIMIT 1",
        $id,
        $catalog_version
      ));

      $sql = $wpdb->prepare(
        "INSERT INTO {$table}
          (variation_id, catalog_version, item_id, reporting_category_id, item_name, variation_name, updated_at_utc)
         VALUES
          (%s, %d, %s, %s, %s, %s, %s)
         ON DUPLICATE KEY UPDATE
          item_id = VALUES(item_id),
          reporting_category_id = VALUES(reporting_category_id),
          item_name = VALUES(item_name),
          variation_name = VALUES(variation_name),
          updated_at_utc = VALUES(updated_at_utc)
        ",
        $id,
        $catalog_version,
        $item_id,
        $category_id,
        $item_name,
        $variation_name,
        gmdate('Y-m-d H:i:s')
      );

      $wpdb->query($sql);
      if (!$exists) {
        $new++;
      }
    }
  }

  return (int)$new;
}

function vms_square_catalog_map_get_reporting_category_id(string $variation_id, int $catalog_version): string
{
  global $wpdb;

  if ($variation_id === '' || $catalog_version <= 0) {
    return '';
  }

  $table = vms_square_table_catalog_map();
  $val = $wpdb->get_var($wpdb->prepare(
    "SELECT reporting_category_id
     FROM {$table}
     WHERE variation_id = %s AND catalog_version <= %d
     ORDER BY catalog_version DESC
     LIMIT 1",
    $variation_id,
    $catalog_version
  ));

  return is_string($val) ? $val : '';
}

/**
 * Square API request helper (with retry/backoff)
 */
function vms_square_api_request(string $method, string $path, ?array $body = null): array
{
  $token = vms_square_effective_access_token();
  if (!$token) {
    throw new Exception('Square access token missing');
  }

  $url = rtrim(vms_square_sync_base_url(), '/') . $path;

  $max_attempts = 4;
  $attempt = 0;
  $last_msg = '';

  while ($attempt < $max_attempts) {
    $attempt++;

    $args = array(
      'method'  => strtoupper($method),
      'timeout' => 30,
      'headers' => array(
        'Authorization' => 'Bearer ' . $token,
        'Content-Type'  => 'application/json',
        'Square-Version' => VMS_SQUARE_API_VERSION,
      ),
    );

    if ($body !== null) {
      $args['body'] = wp_json_encode($body);
    }

    $res = wp_remote_request($url, $args);

    if (is_wp_error($res)) {
      $last_msg = $res->get_error_message();
    } else {
      $code = (int) wp_remote_retrieve_response_code($res);
      $raw  = (string) wp_remote_retrieve_body($res);
      $json = $raw ? json_decode($raw, true) : array();

      if ($code >= 200 && $code < 300) {
        return is_array($json) ? $json : array();
      }

      $last_msg = 'Square API error HTTP ' . $code;
      if (is_array($json) && isset($json['errors'])) {
        $last_msg .= ' ' . wp_json_encode($json['errors']);
      }

      $is_transient = in_array($code, array(429, 500, 502, 503, 504), true);
      if ($is_transient && $attempt < $max_attempts) {
        $retry_after = wp_remote_retrieve_header($res, 'retry-after');
        $sleep_seconds = 0;

        if (is_string($retry_after) && ctype_digit($retry_after)) {
          $sleep_seconds = min(30, (int)$retry_after);
        } else {
          $backoff = array(1, 3, 7);
          $sleep_seconds = $backoff[$attempt - 1] ?? 7;
        }

        usleep(random_int(0, 250000));
        sleep($sleep_seconds);
        continue;
      }

      throw new Exception($last_msg);
    }

    if ($attempt < $max_attempts) {
      $backoff = array(1, 3, 7);
      $sleep_seconds = $backoff[$attempt - 1] ?? 7;
      usleep(random_int(0, 250000));
      sleep($sleep_seconds);
      continue;
    }

    throw new Exception('Square request failed: ' . $last_msg);
  }

  throw new Exception('Square request failed: ' . $last_msg);
}

/**
 * Tables
 */
function vms_square_table_orders(): string
{
  global $wpdb;
  return $wpdb->prefix . 'vms_square_orders';
}

function vms_square_table_daily(): string
{
  global $wpdb;
  return $wpdb->prefix . 'vms_square_daily';
}

function vms_square_table_catalog_map(): string
{
  global $wpdb;
  return $wpdb->prefix . 'vms_square_catalog_map';
}

function vms_square_sync_maybe_create_tables(bool $force = false): void
{
  // IMPORTANT:
  // - dbDelta() is expensive and can spam logs if it encounters schema parsing edge-cases.
  // - Never run schema creation on every request. We only run on activation and when the schema version changes.
  $installed = get_option('vms_square_schema_version', '');
  if (!$force && is_string($installed) && $installed === VMS_SQUARE_SCHEMA_VERSION) {
    return;
  }

  global $wpdb;

  $orders = vms_square_table_orders();
  $daily = vms_square_table_daily();
  $map = vms_square_table_catalog_map();

  $charset = $wpdb->get_charset_collate();
  require_once ABSPATH . 'wp-admin/includes/upgrade.php';

  $sql_orders = "CREATE TABLE {$orders} (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    square_order_id VARCHAR(64) NOT NULL,
    location_id VARCHAR(64) NOT NULL,
    business_date DATE NOT NULL,
    state VARCHAR(24) NOT NULL,
    closed_at_rfc3339 VARCHAR(64) NULL,
    closed_at_utc DATETIME NULL,
    created_at_rfc3339 VARCHAR(64) NULL,
    created_at_utc DATETIME NULL,
    total_amount BIGINT(20) NOT NULL DEFAULT 0,
    tax_amount BIGINT(20) NOT NULL DEFAULT 0,
    discount_amount BIGINT(20) NOT NULL DEFAULT 0,
    tip_amount BIGINT(20) NOT NULL DEFAULT 0,
    service_charge_amount BIGINT(20) NOT NULL DEFAULT 0,
    line_items_json LONGTEXT NULL,
    tenders_json LONGTEXT NULL,
    raw_json LONGTEXT NULL,
    imported_at_utc DATETIME NOT NULL,
    PRIMARY KEY  (id),
    UNIQUE KEY square_order_id (square_order_id),
    KEY loc_date (location_id, business_date),
    KEY closed_at_utc (closed_at_utc)
  ) {$charset};";

  $sql_daily = "CREATE TABLE {$daily} (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    location_id VARCHAR(64) NOT NULL,
    business_date DATE NOT NULL,
    order_count BIGINT(20) NOT NULL DEFAULT 0,
    gross_amount BIGINT(20) NOT NULL DEFAULT 0,
    tax_amount BIGINT(20) NOT NULL DEFAULT 0,
    discount_amount BIGINT(20) NOT NULL DEFAULT 0,
    tip_amount BIGINT(20) NOT NULL DEFAULT 0,
    service_charge_amount BIGINT(20) NOT NULL DEFAULT 0,

    door_line_item_count BIGINT(20) NOT NULL DEFAULT 0,
    door_line_items_missing_category BIGINT(20) NOT NULL DEFAULT 0,
    door_gross_sales_amount BIGINT(20) NOT NULL DEFAULT 0,
    door_discount_amount BIGINT(20) NOT NULL DEFAULT 0,
    door_tax_amount BIGINT(20) NOT NULL DEFAULT 0,
    door_total_amount BIGINT(20) NOT NULL DEFAULT 0,
    door_net_ex_tax_amount BIGINT(20) NOT NULL DEFAULT 0,

    updated_at_utc DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY loc_date (location_id, business_date)
  ) {$charset};";

  $sql_map = "CREATE TABLE {$map} (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    variation_id VARCHAR(64) NOT NULL,
    catalog_version BIGINT(20) NOT NULL DEFAULT 0,
    item_id VARCHAR(64) NULL,
    reporting_category_id VARCHAR(64) NULL,
    item_name VARCHAR(255) NULL,
    variation_name VARCHAR(255) NULL,
    updated_at_utc DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY var_ver (variation_id, catalog_version),
    KEY category (reporting_category_id)
  ) {$charset};";

  $create = array(
    $orders => $sql_orders,
    $daily => $sql_daily,
    $map => $sql_map,
  );

  foreach ($create as $table => $sql) {
    // Only create missing tables. Avoid dbDelta() on existing tables until we implement explicit, tested migrations.
    $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
    if ((string) $exists !== (string) $table) {
      dbDelta($sql);
    }
  }

  update_option('vms_square_schema_version', VMS_SQUARE_SCHEMA_VERSION, false);
}

/**
 * Status
 */
function vms_square_sync_set_status(bool $ok, string $message, array $stats = array()): array
{
  $payload = array(
    'ok' => $ok,
    'message' => $message,
    'ran_at_utc' => gmdate('Y-m-d H:i:s'),
    'stats' => $stats,
  );
  update_option('vms_square_sync_status', $payload, false);
  return $payload;
}

/**
 * Transient error detection + retry scheduling
 */
function vms_square_sync_is_transient_square_error(string $msg): bool
{
  $needles = array('HTTP 429', 'HTTP 500', 'HTTP 502', 'HTTP 503', 'HTTP 504', 'SERVICE_UNAVAILABLE');
  foreach ($needles as $n) {
    if (stripos($msg, $n) !== false) {
      return true;
    }
  }
  return false;
}

function vms_square_sync_schedule_retry(string $reason): array
{
  $cooldown_until = get_option('vms_square_api_cooldown_until_utc', '');
  if (is_string($cooldown_until) && $cooldown_until) {
    $until_ts = strtotime($cooldown_until . ' UTC');
    if ($until_ts && time() < $until_ts) {
      $meta = get_option('vms_square_retry_meta', array());
      $count = (int)($meta['retry_count'] ?? 1);
      return array(
        'retry_count' => max(1, $count),
        'next_retry_at_utc' => $cooldown_until,
      );
    }
  }

  $meta = get_option('vms_square_retry_meta', array());
  if (!is_array($meta)) {
    $meta = array();
  }

  $count = isset($meta['retry_count']) ? (int)$meta['retry_count'] : 0;
  $count = max(0, $count + 1);

  $plan = array(5, 15, 30, 60, 120, 240);
  $idx = min(count($plan) - 1, $count - 1);
  $delay_minutes = $plan[$idx];

  $next_ts = time() + ($delay_minutes * MINUTE_IN_SECONDS);
  $next_utc = gmdate('Y-m-d H:i:s', $next_ts);

  update_option('vms_square_api_cooldown_until_utc', $next_utc, false);

  $meta = array(
    'retry_count' => $count,
    'last_error' => $reason,
    'next_retry_at_utc' => $next_utc,
    'updated_at_utc' => gmdate('Y-m-d H:i:s'),
  );
  update_option('vms_square_retry_meta', $meta, false);

  $retry_hook = 'vms_square_retry_sync';

  if (function_exists('as_schedule_single_action')) {
    if (function_exists('as_next_scheduled_action')) {
      $next = as_next_scheduled_action($retry_hook, array(), 'vms');
      if (!$next) {
        as_schedule_single_action($next_ts, $retry_hook, array(), 'vms');
      }
    } else {
      as_schedule_single_action($next_ts, $retry_hook, array(), 'vms');
    }
  } else {
    if (!wp_next_scheduled($retry_hook)) {
      wp_schedule_single_event($next_ts, $retry_hook);
    }
  }

  return array(
    'retry_count' => $count,
    'next_retry_at_utc' => $next_utc,
  );
}

/**
 * Admin UI (Tools)
 */
function vms_square_sync_admin_menu(): void
{
  add_management_page(
    'VMS Square Sync',
    'VMS Square Sync',
    vms_square_manage_capability(),
    'vms-square-sync',
    'vms_square_sync_admin_page'
  );
}

/**
 * Datepicker rule: anywhere we output a date input in this module, it gets class="vms-date".
 * This loader enables datepicker on tools page and event plan edit screen.
 */
function vms_square_sync_admin_enqueue(string $hook): void
{
  $load = false;

  if ($hook === 'tools_page_vms-square-sync') {
    $load = true;
  }

  if ($hook === 'post.php' || $hook === 'post-new.php') {
    if (function_exists('get_current_screen')) {
      $screen = get_current_screen();
      if ($screen && isset($screen->post_type) && $screen->post_type === vms_square_event_plan_post_type()) {
        $load = true;
      }
    }
  }

  if (!$load) {
    return;
  }
  wp_enqueue_style('dashicons');

  wp_enqueue_script('jquery-ui-datepicker');
  wp_enqueue_style('vms-square-jqui-fallback', includes_url('css/jquery-ui.min.css'), array(), '1.13.2');

  $css_url = plugin_dir_url(__FILE__) . 'vms-square-sync-admin.css';
  wp_enqueue_style('vms-square-sync-admin', $css_url, array(), VMS_SQUARE_SYNC_VERSION);

  $init = <<<'JS'
jQuery(function($){
  $(".vms-date").datepicker({ dateFormat:"yy-mm-dd", changeMonth:true, changeYear:true });

  function syncEventWindowMode() {
    var mode = $('input[name="vms_pos_window_mode"]:checked').val() || 'business_date';
    $('.vms-square-event-window-fields').toggle(mode === 'event_window');
  }
  syncEventWindowMode();
  $(document).on('change', 'input[name="vms_pos_window_mode"]', syncEventWindowMode);

  $(document).on('click', '.vms-square-snapshot-trigger', function(e){
    e.preventDefault();
    var actionUrl = $(this).data('actionUrl');
    if (actionUrl) {
      window.location.href = actionUrl;
    }
  });

  function reindexBucketRows() {
    $('.vms-square-bucket-row').each(function(index){
      $(this).find('.vms-square-bucket-key').attr('name', 'vms_square_bucket_keys[' + index + ']');
      $(this).find('.vms-square-bucket-label').attr('name', 'vms_square_bucket_labels[' + index + ']');
      $(this).find('.vms-square-bucket-categories').attr('name', 'vms_square_bucket_category_ids[' + index + '][]');
    });
  }

  $(document).on('click', '.vms-square-bucket-add', function(e){
    e.preventDefault();
    var table = $('.vms-square-buckets tbody');
    if (!table.length) {
      return;
    }
    var rows = table.find('.vms-square-bucket-row');
    var source = rows.last();
    if (!source.length) {
      return;
    }
    var row = source.clone();
    row.find('input[type="text"]').val('');
    row.find('select').val([]);
    table.append(row);
    reindexBucketRows();
  });

  $(document).on('click', '.vms-square-bucket-remove', function(e){
    e.preventDefault();
    var rows = $('.vms-square-buckets .vms-square-bucket-row');
    if (rows.length <= 1) {
      rows.first().find('input[type="text"]').val('');
      rows.first().find('select').val([]);
      return;
    }
    $(this).closest('.vms-square-bucket-row').remove();
    reindexBucketRows();
  });

  reindexBucketRows();
});
JS;
  wp_add_inline_script('jquery-ui-datepicker', $init);
}

function vms_square_sync_admin_page(): void
{
  if (!vms_square_current_user_can_manage()) {
    return;
  }

  $notice = '';
  $notice_class = 'notice-success';

  if (isset($_POST['vms_square_settings_save'])) {
    check_admin_referer('vms_square_settings_save');

    $current_settings = vms_square_settings_get_all();

    $env = isset($_POST['vms_square_env']) ? sanitize_text_field((string)$_POST['vms_square_env']) : (string)$current_settings['env'];
    if (!in_array($env, array('production', 'sandbox'), true)) {
      $env = 'production';
    }

    $lookback = isset($_POST['vms_square_lookback_days']) ? (int)$_POST['vms_square_lookback_days'] : (int)$current_settings['lookback_days'];
    $lookback = max(0, $lookback);

    $token_input = isset($_POST['vms_square_access_token']) ? trim((string)$_POST['vms_square_access_token']) : null;
    $settings_update = array(
      'env' => $env,
      'lookback_days' => $lookback,
    );

    if (is_string($token_input) && $token_input !== '') {
      $settings_update['access_token'] = sanitize_text_field($token_input);
    }

    if (isset($_POST['vms_square_location_ids_present'])) {
      $loc_ids = array();
      if (isset($_POST['vms_square_location_ids']) && is_array($_POST['vms_square_location_ids'])) {
        foreach ($_POST['vms_square_location_ids'] as $id) {
          $id = trim(sanitize_text_field((string)$id));
          if ($id !== '') {
            $loc_ids[] = $id;
          }
        }
      } else {
        $raw = isset($_POST['vms_square_location_ids_text']) ? (string)$_POST['vms_square_location_ids_text'] : '';
        $parts = array_filter(array_map('trim', explode(',', $raw)));
        foreach ($parts as $p) {
          $p = sanitize_text_field((string)$p);
          if ($p !== '') {
            $loc_ids[] = $p;
          }
        }
      }
      $settings_update['location_ids'] = array_values(array_unique($loc_ids));
    }

    if (isset($_POST['vms_square_door_category_ids_present'])) {
      $cat_ids = array();
      if (isset($_POST['vms_square_door_category_ids']) && is_array($_POST['vms_square_door_category_ids'])) {
        foreach ($_POST['vms_square_door_category_ids'] as $id) {
          $id = trim(sanitize_text_field((string)$id));
          if ($id !== '') {
            $cat_ids[] = $id;
          }
        }
      }
      $settings_update['door_category_ids'] = array_values(array_unique($cat_ids));
    }

    if (isset($_POST['vms_square_bucket_map_present'])) {
      $bucket_category_ids = array();
      $bucket_labels = array();
      $bucket_keys_raw = isset($_POST['vms_square_bucket_keys']) && is_array($_POST['vms_square_bucket_keys']) ? $_POST['vms_square_bucket_keys'] : array();
      $bucket_labels_raw = isset($_POST['vms_square_bucket_labels']) && is_array($_POST['vms_square_bucket_labels']) ? $_POST['vms_square_bucket_labels'] : array();
      $bucket_cats_raw = isset($_POST['vms_square_bucket_category_ids']) && is_array($_POST['vms_square_bucket_category_ids']) ? $_POST['vms_square_bucket_category_ids'] : array();

      foreach ($bucket_keys_raw as $idx => $raw_key) {
        $bucket_key = vms_square_bucket_key_sanitize(sanitize_text_field((string)$raw_key));
        if ($bucket_key === '') {
          continue;
        }

        $label = '';
        if (array_key_exists($idx, $bucket_labels_raw)) {
          $label = sanitize_text_field((string)$bucket_labels_raw[$idx]);
        }

        $row_cat_ids = array();
        if (array_key_exists($idx, $bucket_cats_raw) && is_array($bucket_cats_raw[$idx])) {
          foreach ($bucket_cats_raw[$idx] as $cat_id) {
            $cat_id = trim(sanitize_text_field((string)$cat_id));
            if ($cat_id !== '') {
              $row_cat_ids[] = $cat_id;
            }
          }
        }

        $row_cat_ids = array_values(array_unique($row_cat_ids));
        if (!isset($bucket_category_ids[$bucket_key])) {
          $bucket_category_ids[$bucket_key] = array();
        }
        $bucket_category_ids[$bucket_key] = array_values(array_unique(array_merge($bucket_category_ids[$bucket_key], $row_cat_ids)));
        if ($label !== '') {
          $bucket_labels[$bucket_key] = $label;
        }
      }

      $settings_update['bucket_category_ids'] = $bucket_category_ids;
      $settings_update['bucket_labels'] = $bucket_labels;
    }

    if (isset($_POST['vms_square_window_defaults_present'])) {
      $event_window_default_mode = isset($_POST['vms_square_event_window_default_mode']) ? sanitize_text_field((string)$_POST['vms_square_event_window_default_mode']) : 'business_date';
      if (!in_array($event_window_default_mode, array('business_date', 'event_window'), true)) {
        $event_window_default_mode = 'business_date';
      }
      $settings_update['event_window_default_mode'] = $event_window_default_mode;

      $window_start_offset = isset($_POST['vms_square_event_window_default_start_offset_minutes']) ? (int)$_POST['vms_square_event_window_default_start_offset_minutes'] : 0;
      $window_end_offset = isset($_POST['vms_square_event_window_default_end_offset_minutes']) ? (int)$_POST['vms_square_event_window_default_end_offset_minutes'] : 240;
      $settings_update['event_window_default_start_offset_minutes'] = max(-1440, min(4320, $window_start_offset));
      $settings_update['event_window_default_end_offset_minutes'] = max(-1440, min(4320, $window_end_offset));
    }

    vms_square_settings_update($settings_update);
    $notice = 'Settings saved.';
  }

  if (isset($_POST['vms_square_test_connection'])) {
    check_admin_referer('vms_square_test_connection');
    try {
      $resp = vms_square_api_request('GET', '/v2/locations');
      $count = isset($resp['locations']) && is_array($resp['locations']) ? count($resp['locations']) : 0;
      $notice = 'Connection OK. Locations returned: ' . (int)$count;
    } catch (Throwable $e) {
      $notice_class = 'notice-error';
      $notice = 'Connection failed: ' . $e->getMessage();
    }
  }

  if (isset($_POST['vms_square_refresh_locations'])) {
    check_admin_referer('vms_square_refresh_locations');
    try {
      $resp = vms_square_api_request('GET', '/v2/locations');
      $list = array();
      if (isset($resp['locations']) && is_array($resp['locations'])) {
        foreach ($resp['locations'] as $loc) {
          if (!is_array($loc)) {
            continue;
          }
          $id = (string)($loc['id'] ?? '');
          if ($id === '') {
            continue;
          }
          $name = (string)($loc['name'] ?? $id);
          $tz = (string)($loc['timezone'] ?? '');
          $addr = '';
          if (isset($loc['address']) && is_array($loc['address'])) {
            $parts = array();
            if (!empty($loc['address']['locality'])) {
              $parts[] = (string)$loc['address']['locality'];
            }
            if (!empty($loc['address']['administrative_district_level_1'])) {
              $parts[] = (string)$loc['address']['administrative_district_level_1'];
            }
            $addr = implode(', ', $parts);
          }
          $list[] = array(
            'id' => $id,
            'name' => $name,
            'timezone' => $tz,
            'hint' => $addr,
          );
        }
      }
      vms_square_settings_update(array(
        'locations_cache' => $list,
        'cache_updated_at_utc' => gmdate('Y-m-d H:i:s'),
      ));
      $notice = 'Locations refreshed. Found: ' . count($list);
    } catch (Throwable $e) {
      $notice_class = 'notice-error';
      $notice = 'Refresh locations failed: ' . $e->getMessage();
    }
  }

  if (isset($_POST['vms_square_refresh_categories'])) {
    check_admin_referer('vms_square_refresh_categories');
    try {
      $list = vms_square_fetch_category_cache_from_api();
      $reporting_count = 0;
      $category_count = 0;
      foreach ($list as $row) {
        $type = strtoupper(trim((string)($row['type'] ?? '')));
        if ($type === 'REPORTING_CATEGORY') {
          $reporting_count++;
        } elseif ($type === 'CATEGORY') {
          $category_count++;
        }
      }

      vms_square_settings_update(array(
        'categories_cache' => $list,
        'cache_updated_at_utc' => gmdate('Y-m-d H:i:s'),
      ));
      $notice = 'Category selectors refreshed. Reporting: ' . (int)$reporting_count . '. Categories: ' . (int)$category_count . '.';
    } catch (Throwable $e) {
      $notice_class = 'notice-error';
      $notice = 'Refresh category selectors failed: ' . $e->getMessage();
    }
  }

  $status = get_option('vms_square_sync_status', array());

  if (isset($_POST['vms_square_run_now'])) {
    check_admin_referer('vms_square_run_now');
    $res = vms_square_sync_run(array(
      'lookback_days' => vms_square_effective_lookback_days(),
    ));
    $status = get_option('vms_square_sync_status', array());
    $notice = 'Run complete: ' . esc_html((string)($res['message'] ?? 'OK'));
  }

  if (isset($_POST['vms_square_backfill'])) {
    check_admin_referer('vms_square_backfill');
    $start = isset($_POST['start_date']) ? sanitize_text_field((string)$_POST['start_date']) : '';
    $end   = isset($_POST['end_date']) ? sanitize_text_field((string)$_POST['end_date']) : '';

    if ($start && $end) {
      $site_tz = wp_timezone();
      $d1 = new DateTime($start . ' 00:00:00', $site_tz);
      $d2 = new DateTime($end . ' 00:00:00', $site_tz);
      if ($d2 < $d1) {
        $notice_class = 'notice-error';
        $notice = 'Backfill error: end date is before start date.';
      } else {
        $cool = vms_square_sync_check_cooldown();
        if (!empty($cool['in_cooldown'])) {
          $notice_class = 'notice-error';
          $notice = 'Backfill blocked: in cooldown until ' . (string)$cool['cooldown_until_utc'] . ' UTC.';
        } else {
          $token = vms_square_effective_access_token();
          $locs = vms_square_effective_location_ids();
          if (!$token || empty($locs)) {
            $notice_class = 'notice-error';
            $notice = 'Backfill error: missing token or locations. Set token and locations in settings.';
          } else {
            $orders_total = 0;
            $days_total = 0;

            foreach ($locs as $location_id) {
              $loc_tz = vms_square_sync_location_timezone($location_id);
              $cursor = clone $d1;
              while ($cursor <= $d2) {
                $biz_date = $cursor->format('Y-m-d');
                $res = vms_square_sync_one_day($location_id, $loc_tz, $biz_date);
                $orders_total += (int)($res['orders_upserted'] ?? 0);
                $days_total++;
                $cursor->modify('+1 day');
              }
            }

            $stats = array(
              'ok' => true,
              'orders_upserted' => $orders_total,
              'days_synced' => $days_total,
              'locations' => count($locs),
              'message' => 'OK',
            );
            vms_square_sync_set_status(true, 'Backfill complete', $stats);
            update_option('vms_square_retry_meta', array(), false);
            delete_option('vms_square_api_cooldown_until_utc');

            $status = get_option('vms_square_sync_status', array());
            $notice = 'Backfill complete. Days: ' . (int)$days_total . '. Orders upserted: ' . (int)$orders_total . '.';
          }
        }
      }
    } else {
      $notice_class = 'notice-error';
      $notice = 'Backfill error: enter start and end dates.';
    }
  }

  // Door split calculator (what-if) + persist last-used defaults.
  $door_calc = null;
  if (isset($_POST['vms_square_door_calc'])) {
    check_admin_referer('vms_square_door_calc');

    $calc_loc = isset($_POST['door_calc_location']) ? sanitize_text_field((string)$_POST['door_calc_location']) : '';
    $calc_date = isset($_POST['door_calc_date']) ? sanitize_text_field((string)$_POST['door_calc_date']) : '';
    $pct = isset($_POST['door_calc_pct']) ? (float)$_POST['door_calc_pct'] : 0.0;
    $pct = max(0.0, min(100.0, $pct));

    if ($calc_date) {
      update_option('vms_square_last_door_calc_date', $calc_date, false);
    }
    if ($calc_loc) {
      update_option('vms_square_last_door_calc_location', $calc_loc, false);
    }
    update_option('vms_square_last_door_calc_pct', (string)$pct, false);

    if ($calc_loc && $calc_date) {
      $daily = vms_square_table_daily();
      global $wpdb;
      $row = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$daily} WHERE location_id = %s AND business_date = %s LIMIT 1",
        $calc_loc,
        $calc_date
      ), ARRAY_A);

      if ($row) {
        $net = (int)($row['door_net_ex_tax_amount'] ?? 0);
        $band = (int)round($net * ($pct / 100.0));
        $house = max(0, $net - $band);

        $door_calc = array(
          'location_id' => $calc_loc,
          'business_date' => $calc_date,
          'pct' => $pct,
          'door_net_ex_tax_amount' => $net,
          'band_amount' => $band,
          'house_amount' => $house,
          'row' => $row,
        );
      } else {
        $door_calc = array('error' => 'No daily rollup row found for that location/date yet. Run sync or backfill first.');
      }
    } else {
      $door_calc = array('error' => 'Enter a location and date.');
    }
  }

  $settings = vms_square_settings_get_all();
  $effective_env = vms_square_effective_env();
  $effective_locations = vms_square_effective_location_ids();
  $effective_lookback = vms_square_effective_lookback_days();
  $effective_door_cats = vms_square_effective_door_category_ids();
  $effective_bucket_map = vms_square_effective_bucket_category_ids();
  $effective_bucket_labels = vms_square_effective_bucket_labels();
  $effective_window_default_mode = vms_square_effective_event_window_default_mode();
  $effective_window_start_offset = vms_square_effective_event_window_default_start_offset_minutes();
  $effective_window_end_offset = vms_square_effective_event_window_default_end_offset_minutes();

  $env_is_constant = defined('VMS_SQUARE_ENV');
  $token_is_constant = defined('VMS_SQUARE_ACCESS_TOKEN');
  $locs_is_constant = defined('VMS_SQUARE_LOCATION_IDS');
  $lookback_is_constant = defined('VMS_SQUARE_LOOKBACK_DAYS');

  $locations_cache = is_array($settings['locations_cache']) ? $settings['locations_cache'] : array();
  $categories_cache = vms_square_categories_cache_get();

  $bucket_rows = array();
  foreach ($effective_bucket_map as $bucket_key => $cat_ids) {
    $bucket_rows[] = array(
      'key' => $bucket_key,
      'label' => (string)($effective_bucket_labels[$bucket_key] ?? ''),
      'category_ids' => is_array($cat_ids) ? $cat_ids : array(),
    );
  }
  if (empty($bucket_rows)) {
    $bucket_rows[] = array(
      'key' => '',
      'label' => '',
      'category_ids' => array(),
    );
  }

  $yesterday = wp_date('Y-m-d', time() - DAY_IN_SECONDS, wp_timezone());

  $last_calc_date = (string)get_option('vms_square_last_door_calc_date', '');
  $last_calc_loc  = (string)get_option('vms_square_last_door_calc_location', '');
  $last_calc_pct  = (string)get_option('vms_square_last_door_calc_pct', '80');
  if ($last_calc_date === '') {
    $last_calc_date = $yesterday;
  }

  if ($last_calc_loc === '' && !empty($effective_locations)) {
    $last_calc_loc = (string)$effective_locations[0];
  }

  echo '<div class="wrap vms-square-wrap">';
  echo '<h1>VMS Square Sync</h1>';

  if ($notice) {
    echo '<div class="notice ' . esc_attr($notice_class) . '"><p>' . esc_html($notice) . '</p></div>';
  }

  echo '<div class="vms-square-grid">';

  echo '<section class="vms-square-card">';
  echo '<h2>Square Settings</h2>';
  echo '<p class="vms-square-muted">Constants in wp-config.php override these settings. You can still use this page for Door Category mapping even if token stays in wp-config.php.</p>';

  echo '<form method="post" class="vms-square-form">';
  wp_nonce_field('vms_square_settings_save');
  echo '<input type="hidden" name="vms_square_window_defaults_present" value="1" />';

  echo '<div class="vms-square-row">';
  echo '<label><strong>Environment</strong></label>';
  echo '<select name="vms_square_env" ' . ($env_is_constant ? 'disabled' : '') . '>';
  echo '<option value="production" ' . selected($settings['env'], 'production', false) . '>Production</option>';
  echo '<option value="sandbox" ' . selected($settings['env'], 'sandbox', false) . '>Sandbox</option>';
  echo '</select>';
  if ($env_is_constant) {
    echo '<span class="vms-square-muted">(Locked by VMS_SQUARE_ENV)</span>';
  }
  echo '</div>';

  echo '<div class="vms-square-row">';
  echo '<label><strong>Access Token</strong></label>';
  if ($token_is_constant) {
    echo '<input type="password" value="********" disabled />';
    echo '<span class="vms-square-muted">(Locked by VMS_SQUARE_ACCESS_TOKEN)</span>';
  } else {
    echo '<input type="password" name="vms_square_access_token" value="" placeholder="Paste token (leave blank to keep current)" />';
    echo '<div class="vms-square-muted">Leave blank to keep the currently saved token.</div>';
  }
  echo '</div>';

  echo '<div class="vms-square-row">';
  echo '<label><strong>Lookback Days</strong></label>';
  echo '<input type="number" name="vms_square_lookback_days" value="' . esc_attr((string)$settings['lookback_days']) . '" min="0" max="30" ' . ($lookback_is_constant ? 'disabled' : '') . ' />';
  if ($lookback_is_constant) {
    echo '<span class="vms-square-muted">(Locked by VMS_SQUARE_LOOKBACK_DAYS)</span>';
  }
  echo '</div>';

  echo '<div class="vms-square-row">';
  echo '<label><strong>Default Event Window Mode</strong></label>';
  echo '<select name="vms_square_event_window_default_mode">';
  echo '<option value="business_date" ' . selected((string)$settings['event_window_default_mode'], 'business_date', false) . '>Business Date</option>';
  echo '<option value="event_window" ' . selected((string)$settings['event_window_default_mode'], 'event_window', false) . '>Event Window</option>';
  echo '</select>';
  echo '</div>';

  echo '<div class="vms-square-row">';
  echo '<label><strong>Default Event Window Start Offset (minutes)</strong></label>';
  echo '<input type="number" name="vms_square_event_window_default_start_offset_minutes" value="' . esc_attr((string)$settings['event_window_default_start_offset_minutes']) . '" min="-1440" max="4320" />';
  echo '<span class="vms-square-muted">Used when Event Window mode is selected and no start is set on the Event Plan.</span>';
  echo '</div>';

  echo '<div class="vms-square-row">';
  echo '<label><strong>Default Event Window End Offset (minutes)</strong></label>';
  echo '<input type="number" name="vms_square_event_window_default_end_offset_minutes" value="' . esc_attr((string)$settings['event_window_default_end_offset_minutes']) . '" min="-1440" max="4320" />';
  echo '<span class="vms-square-muted">Used when Event Window mode is selected and no end is set on the Event Plan.</span>';
  echo '</div>';

  echo '<div class="vms-square-row">';
  echo '<button class="button button-primary" name="vms_square_settings_save" value="1">Save settings</button>';
  echo '</div>';

  echo '</form>';

  echo '<div class="vms-square-actions">';
  echo '<form method="post">';
  wp_nonce_field('vms_square_test_connection');
  echo '<button class="button" name="vms_square_test_connection" value="1">Test connection</button>';
  echo '</form>';

  echo '<form method="post">';
  wp_nonce_field('vms_square_refresh_locations');
  echo '<button class="button" name="vms_square_refresh_locations" value="1">Refresh Locations</button>';
  echo '</form>';

  echo '<form method="post">';
  wp_nonce_field('vms_square_refresh_categories');
  echo '<button class="button" name="vms_square_refresh_categories" value="1">Refresh Category Selectors</button>';
  echo '</form>';
  echo '</div>';

  echo '</section>';

  echo '<section class="vms-square-card">';
  echo '<h2>Locations</h2>';

  if ($locs_is_constant) {
    echo '<p class="vms-square-muted">Locations are locked by VMS_SQUARE_LOCATION_IDS.</p>';
  }

  if (!empty($locations_cache)) {
    echo '<form method="post" class="vms-square-form">';
    wp_nonce_field('vms_square_settings_save');
    echo '<input type="hidden" name="vms_square_settings_save" value="1" />';
    echo '<input type="hidden" name="vms_square_location_ids_present" value="1" />';
    echo '<p>Select the Square location(s) to import from:</p>';

    foreach ($locations_cache as $loc) {
      if (!is_array($loc)) {
        continue;
      }
      $id = (string)($loc['id'] ?? '');
      if ($id === '') {
        continue;
      }
      $label = (string)($loc['name'] ?? $id);
      $hint = (string)($loc['hint'] ?? '');
      $tz = (string)($loc['timezone'] ?? '');
      $checked = in_array($id, $settings['location_ids'], true);

      echo '<label class="vms-square-check">';
      echo '<input type="checkbox" name="vms_square_location_ids[]" value="' . esc_attr($id) . '" ' . checked($checked, true, false) . ' ' . ($locs_is_constant ? 'disabled' : '') . ' /> ';
      echo '<span><strong>' . esc_html($label) . '</strong> <code>' . esc_html($id) . '</code></span>';
      if ($hint) {
        echo '<span class="vms-square-muted">(' . esc_html($hint) . ')</span>';
      }
      if ($tz) {
        echo '<span class="vms-square-muted">[' . esc_html($tz) . ']</span>';
      }
      echo '</label>';
    }

    echo '<div class="vms-square-row">';
    echo '<button class="button button-primary" ' . ($locs_is_constant ? 'disabled' : '') . '>Save settings</button>';
    echo '</div>';
    echo '</form>';
  } else {
    echo '<p>No cached locations yet. Click <strong>Refresh Locations</strong>.</p>';
    echo '<form method="post" class="vms-square-form">';
    wp_nonce_field('vms_square_settings_save');
    echo '<input type="hidden" name="vms_square_settings_save" value="1" />';
    echo '<input type="hidden" name="vms_square_location_ids_present" value="1" />';
    echo '<div class="vms-square-row">';
    echo '<label><strong>Location IDs (comma-separated)</strong></label>';
    echo '<textarea name="vms_square_location_ids_text" rows="2" ' . ($locs_is_constant ? 'disabled' : '') . '>' . esc_textarea(implode(',', (array)$settings['location_ids'])) . '</textarea>';
    echo '</div>';
    echo '<div class="vms-square-row">';
    echo '<button class="button button-primary" ' . ($locs_is_constant ? 'disabled' : '') . '>Save settings</button>';
    echo '</div>';
    echo '</form>';
  }

  echo '</section>';

  echo '<section class="vms-square-card">';
  echo '<h2>POS Buckets (Reporting Category Mapping)</h2>';
  echo '<p class="vms-square-muted">Map Square Reporting Category IDs to provider-neutral bucket keys. Example keys: <code>door</code>, <code>bar</code>, <code>food</code>, <code>merch</code>, <code>other</code>.</p>';
  echo '<form method="post" class="vms-square-form">';
  wp_nonce_field('vms_square_settings_save');
  echo '<input type="hidden" name="vms_square_settings_save" value="1" />';
  echo '<input type="hidden" name="vms_square_bucket_map_present" value="1" />';

  if (!empty($categories_cache)) {
    echo '<div class="vms-square-buckets-wrap">';
    echo '<table class="widefat striped vms-square-buckets">';
    echo '<thead><tr><th>Bucket Key</th><th>Label</th><th>Reporting Categories</th><th>Row</th></tr></thead>';
    echo '<tbody>';

    foreach ($bucket_rows as $idx => $bucket_row) {
      $row_key = is_array($bucket_row) ? (string)($bucket_row['key'] ?? '') : '';
      $row_label = is_array($bucket_row) ? (string)($bucket_row['label'] ?? '') : '';
      $row_cat_ids = (is_array($bucket_row) && isset($bucket_row['category_ids']) && is_array($bucket_row['category_ids'])) ? $bucket_row['category_ids'] : array();

      echo '<tr class="vms-square-bucket-row">';
      echo '<td><input type="text" class="vms-square-bucket-key" name="vms_square_bucket_keys[' . (int)$idx . ']" value="' . esc_attr($row_key) . '" placeholder="door" /></td>';
      echo '<td><input type="text" class="vms-square-bucket-label" name="vms_square_bucket_labels[' . (int)$idx . ']" value="' . esc_attr($row_label) . '" placeholder="Door / Tickets" /></td>';
      echo '<td>';
      echo '<select class="vms-square-bucket-categories" name="vms_square_bucket_category_ids[' . (int)$idx . '][]" multiple size="6">';
      foreach ($categories_cache as $cat) {
        if (!is_array($cat)) {
          continue;
        }
        $id = (string)($cat['id'] ?? '');
        if ($id === '') {
          continue;
        }
        $name = vms_square_category_option_label($cat);
        if ($name === '') {
          $name = (string)($cat['name'] ?? $id);
        }
        $selected = in_array($id, $row_cat_ids, true);
        echo '<option value="' . esc_attr($id) . '" ' . selected($selected, true, false) . '>' . esc_html($name) . '</option>';
      }
      echo '</select>';
      echo '</td>';
      echo '<td><button type="button" class="button button-link-delete vms-square-bucket-remove">Remove</button></td>';
      echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';
    echo '</div>';
    echo '<div class="vms-square-actions">';
    echo '<button type="button" class="button vms-square-bucket-add">Add bucket</button>';
    echo '<button class="button button-primary" name="vms_square_settings_save" value="1">Save bucket mapping</button>';
    echo '</div>';
  } else {
    echo '<p>No cached categories yet. Click <strong>Refresh Category Selectors</strong> first, then map buckets.</p>';
  }

  if (empty($effective_bucket_map)) {
    echo '<div class="notice notice-warning inline"><p>No buckets configured. Totals will still compute, but buckets will be empty.</p></div>';
  }
  echo '<p class="description">Bucket keys are sanitized to lowercase letters, numbers, and underscores.</p>';
  echo '</form>';
  echo '</section>';

  echo '<section class="vms-square-card">';
  echo '<h2>Door Category Mapping</h2>';
  echo '<p class="vms-square-muted">Select the Square reporting categories used for “Door” split calculations. Legacy catalog category IDs remain supported so older mappings do not break.</p>';

  if (!empty($categories_cache)) {
    echo '<form method="post" class="vms-square-form">';
    wp_nonce_field('vms_square_settings_save');
    echo '<input type="hidden" name="vms_square_settings_save" value="1" />';
    echo '<input type="hidden" name="vms_square_door_category_ids_present" value="1" />';

    foreach ($categories_cache as $cat) {
      if (!is_array($cat)) {
        continue;
      }
      $id = (string)($cat['id'] ?? '');
      if ($id === '') {
        continue;
      }
      $name = vms_square_category_option_label($cat);
      if ($name === '') {
        $name = (string)($cat['name'] ?? $id);
      }
      $checked = in_array($id, (array)$settings['door_category_ids'], true);

      echo '<label class="vms-square-check">';
      echo '<input type="checkbox" name="vms_square_door_category_ids[]" value="' . esc_attr($id) . '" ' . checked($checked, true, false) . ' /> ';
      echo '<span><strong>' . esc_html($name) . '</strong></span>';
      echo '</label>';
    }

    echo '<div class="vms-square-row">';
    echo '<button class="button button-primary">Save settings</button>';
    echo '</div>';
    echo '</form>';
  } else {
    echo '<p>No cached categories yet. Click <strong>Refresh Category Selectors</strong>.</p>';
  }

  echo '</section>';

  echo '<section class="vms-square-card">';
  echo '<h2>Status</h2>';
  echo '<pre class="vms-square-pre">' . esc_html(wp_json_encode($status, JSON_PRETTY_PRINT)) . '</pre>';

  echo '<h3>Effective Settings (Read Only)</h3>';
  echo '<pre class="vms-square-pre">' . esc_html(wp_json_encode(array(
    'env' => $effective_env,
    'location_ids' => $effective_locations,
    'lookback_days' => $effective_lookback,
    'door_category_ids' => $effective_door_cats,
    'bucket_category_ids' => $effective_bucket_map,
    'bucket_labels' => $effective_bucket_labels,
    'event_window_default_mode' => $effective_window_default_mode,
    'event_window_default_start_offset_minutes' => $effective_window_start_offset,
    'event_window_default_end_offset_minutes' => $effective_window_end_offset,
    'cache_updated_at_utc' => (string)$settings['cache_updated_at_utc'],
    'constants_override' => array(
      'env' => $env_is_constant,
      'token' => $token_is_constant,
      'locations' => $locs_is_constant,
      'lookback_days' => $lookback_is_constant,
    ),
  ), JSON_PRETTY_PRINT)) . '</pre>';

  echo '</section>';

  echo '<section class="vms-square-card">';
  echo '<h2>Run now</h2>';
  echo '<form method="post">';
  wp_nonce_field('vms_square_run_now');
  echo '<button class="button button-primary" name="vms_square_run_now" value="1">Run sync now (yesterday + lookback)</button>';
  echo '</form>';

  echo '<h2>Backfill date range</h2>';
  echo '<form method="post" class="vms-square-form">';
  wp_nonce_field('vms_square_backfill');
  echo '<div class="vms-square-row"><label>Start date</label><input type="text" class="vms-date" name="start_date" value="" placeholder="YYYY-MM-DD" /></div>';
  echo '<div class="vms-square-row"><label>End date</label><input type="text" class="vms-date" name="end_date" value="" placeholder="YYYY-MM-DD" /></div>';
  echo '<div class="vms-square-row"><button class="button" name="vms_square_backfill" value="1">Run backfill</button></div>';
  echo '</form>';

  echo '</section>';

  echo '<section class="vms-square-card">';
  echo '<h2>Door Split (What-If)</h2>';
  echo '<p class="vms-square-muted">Uses <strong>Door Net (excluding tax)</strong>. This matches “door split excludes sales tax.”</p>';

  echo '<form method="post" class="vms-square-form">';
  wp_nonce_field('vms_square_door_calc');
  echo '<input type="hidden" name="vms_square_door_calc" value="1" />';

  echo '<div class="vms-square-row">';
  echo '<label>Location</label>';
  echo '<select name="door_calc_location">';
  foreach ($effective_locations as $lid) {
    echo '<option value="' . esc_attr($lid) . '" ' . selected($last_calc_loc, $lid, false) . '>' . esc_html($lid) . '</option>';
  }
  echo '</select>';
  echo '</div>';

  echo '<div class="vms-square-row">';
  echo '<label>Business Date</label>';
  echo '<input type="text" class="vms-date" name="door_calc_date" value="' . esc_attr($last_calc_date) . '" />';
  echo '</div>';

  echo '<div class="vms-square-row">';
  echo '<label>Band Split %</label>';
  echo '<input type="number" step="0.01" min="0" max="100" name="door_calc_pct" value="' . esc_attr($last_calc_pct) . '" />';
  echo '</div>';

  echo '<div class="vms-square-row">';
  echo '<button class="button button-primary">Calculate</button>';
  echo '</div>';

  echo '</form>';

  if (is_array($door_calc)) {
    if (!empty($door_calc['error'])) {
      echo '<div class="notice notice-error"><p>' . esc_html((string)$door_calc['error']) . '</p></div>';
    } else {
      $net = (int)$door_calc['door_net_ex_tax_amount'];
      $band = (int)$door_calc['band_amount'];
      $house = (int)$door_calc['house_amount'];
      echo '<div class="vms-square-kpi">';
      echo '<div><strong>Door Net (ex tax)</strong><br />' . esc_html(vms_square_fmt_money($net)) . '</div>';
      echo '<div><strong>Band</strong><br />' . esc_html(vms_square_fmt_money($band)) . '</div>';
      echo '<div><strong>House</strong><br />' . esc_html(vms_square_fmt_money($house)) . '</div>';
      echo '</div>';

      echo '<details class="vms-square-details"><summary>Show daily rollup row</summary>';
      echo '<pre class="vms-square-pre">' . esc_html(wp_json_encode((array)$door_calc['row'], JSON_PRETTY_PRINT)) . '</pre>';
      echo '</details>';
    }
  }

  echo '</section>';

  echo '</div>'; // grid
  echo '</div>'; // wrap
}

function vms_square_fmt_money(int $cents): string
{
  $amt = $cents / 100;
  return '$' . number_format($amt, 2, '.', ',');
}

function vms_square_event_plan_snapshot_pull_url(int $event_plan_id): string
{
  $redirect = get_edit_post_link($event_plan_id, 'raw');
  if (!is_string($redirect) || $redirect === '') {
    $redirect = admin_url('post.php?post=' . $event_plan_id . '&action=edit');
  }

  $url = add_query_arg(
    array(
      'action' => 'vms_square_snapshot_event_actuals',
      'event_plan_id' => $event_plan_id,
      'redirect' => $redirect,
    ),
    admin_url('admin-post.php')
  );

  return wp_nonce_url($url, 'vms_square_snapshot_event_actuals_' . $event_plan_id);
}

function vms_square_admin_post_snapshot_event_actuals(): void
{
  if (!vms_square_current_user_can_manage()) {
    wp_die('Forbidden', 403);
  }

  $event_plan_id = isset($_REQUEST['event_plan_id']) ? (int)$_REQUEST['event_plan_id'] : 0;
  $redirect = isset($_REQUEST['redirect']) ? esc_url_raw((string)wp_unslash($_REQUEST['redirect'])) : '';

  if ($event_plan_id > 0 && $redirect === '') {
    $redirect = get_edit_post_link($event_plan_id, 'raw');
  }
  if (!is_string($redirect) || $redirect === '') {
    $redirect = admin_url('tools.php?page=vms-square-sync');
  }

  $redirect = wp_validate_redirect($redirect, admin_url('tools.php?page=vms-square-sync'));
  $nonce = isset($_REQUEST['_wpnonce']) ? (string)wp_unslash($_REQUEST['_wpnonce']) : '';

  if ($event_plan_id <= 0 || !wp_verify_nonce($nonce, 'vms_square_snapshot_event_actuals_' . $event_plan_id)) {
    vms_square_actuals_log('Snapshot admin-post security check failed for event_plan_id=' . $event_plan_id);
    $redirect = add_query_arg(
      array(
        'vms_square_actuals_status' => 'error',
        'vms_square_actuals_event' => $event_plan_id,
        'vms_square_actuals_message' => 'Snapshot request failed security checks.',
      ),
      $redirect
    );
    wp_safe_redirect($redirect);
    exit;
  }

  $result = vms_square_snapshot_event_actuals($event_plan_id);
  $status = !empty($result['ok']) ? 'success' : 'error';
  $message = sanitize_text_field((string)($result['message'] ?? 'Snapshot completed.'));
  if ($status === 'error') {
    vms_square_actuals_log('Snapshot admin-post failed for event_plan_id=' . $event_plan_id . ': ' . $message);
  }

  $redirect = add_query_arg(
    array(
      'vms_square_actuals_status' => $status,
      'vms_square_actuals_event' => $event_plan_id,
      'vms_square_actuals_message' => $message,
    ),
    $redirect
  );

  wp_safe_redirect($redirect);
  exit;
}

/**
 * Event Plans integration (metabox)
 * Default CPT slug is vms_event_plan, but you can override via filter:
 * add_filter('vms_square_event_plan_post_type', fn() => 'your_slug');
 */
function vms_square_event_plan_post_type(): string
{
  return (string)apply_filters('vms_square_event_plan_post_type', 'vms_event_plan');
}

function vms_square_event_plan_add_metabox(): void
{
  add_meta_box(
    'vms_square_door_split',
    'Door Split',
    'vms_square_event_plan_metabox_html',
    vms_square_event_plan_post_type(),
    'side',
    'default'
  );
}

function vms_square_event_plan_metabox_html($post): void
{
  $loc = (string)get_post_meta($post->ID, '_vms_square_location_id', true);
  $date = (string)get_post_meta($post->ID, '_vms_square_business_date', true);
  $pct = (string)get_post_meta($post->ID, '_vms_door_split_pct', true);
  $window_mode = (string)get_post_meta($post->ID, '_vms_pos_window_mode', true);
  $window_start_local = vms_square_sanitize_local_datetime_input((string)get_post_meta($post->ID, '_vms_pos_window_start_local', true));
  $window_end_local = vms_square_sanitize_local_datetime_input((string)get_post_meta($post->ID, '_vms_pos_window_end_local', true));
  $snapshot_provider = (string)get_post_meta($post->ID, '_vms_pos_actuals_provider', true);
  $snapshot_pulled_at = (string)get_post_meta($post->ID, '_vms_pos_actuals_pulled_at_utc', true);
  $snapshot_window_start_utc = (string)get_post_meta($post->ID, '_vms_pos_actuals_window_start_utc', true);
  $snapshot_window_end_utc = (string)get_post_meta($post->ID, '_vms_pos_actuals_window_end_utc', true);
  $snapshot_mapping_marker = (string)get_post_meta($post->ID, '_vms_pos_actuals_mapping_marker', true);
  $snapshot_totals = get_post_meta($post->ID, '_vms_pos_actuals_totals', true);
  if (!is_array($snapshot_totals)) {
    $snapshot_totals = array();
  }

  if (!in_array($window_mode, array('business_date', 'event_window'), true)) {
    $window_mode = vms_square_effective_event_window_default_mode();
  }

  if ($window_mode === 'event_window' && ($window_start_local === '' || $window_end_local === '') && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    try {
      $base = new DateTimeImmutable($date . ' 00:00:00', wp_timezone());
      if ($window_start_local === '') {
        $offset = (int)vms_square_effective_event_window_default_start_offset_minutes();
        $window_start_local = $base->modify(($offset >= 0 ? '+' : '') . $offset . ' minutes')->format('Y-m-d H:i');
      }
      if ($window_end_local === '') {
        $offset = (int)vms_square_effective_event_window_default_end_offset_minutes();
        $window_end_local = $base->modify(($offset >= 0 ? '+' : '') . $offset . ' minutes')->format('Y-m-d H:i');
      }
    } catch (Throwable $e) {
      // Use stored values only when defaults fail to compute.
    }
  }

  if ($pct === '') {
    $pct = '80';
  }

  $effective_locations = vms_square_effective_location_ids();
  $snapshot_pull_url = vms_square_event_plan_snapshot_pull_url((int)$post->ID);
  $notice_status = isset($_GET['vms_square_actuals_status']) ? sanitize_key((string)wp_unslash($_GET['vms_square_actuals_status'])) : '';
  $notice_event = isset($_GET['vms_square_actuals_event']) ? (int)$_GET['vms_square_actuals_event'] : 0;
  $notice_message = isset($_GET['vms_square_actuals_message']) ? sanitize_text_field((string)wp_unslash($_GET['vms_square_actuals_message'])) : '';

  wp_nonce_field('vms_square_event_plan_save', 'vms_square_event_plan_nonce');

  if ($notice_event === (int)$post->ID && in_array($notice_status, array('success', 'error'), true) && $notice_message !== '') {
    $notice_class = ($notice_status === 'success') ? 'notice-success' : 'notice-error';
    echo '<div class="notice inline ' . esc_attr($notice_class) . '"><p>' . esc_html($notice_message) . '</p></div>';
  }

  echo '<p><label><strong>POS Location</strong></label><br />';
  echo '<select name="vms_square_location_id" style="width:100%;">';
  echo '<option value="">(Select)</option>';
  foreach ($effective_locations as $lid) {
    echo '<option value="' . esc_attr($lid) . '" ' . selected($loc, $lid, false) . '>' . esc_html($lid) . '</option>';
  }
  echo '</select></p>';

  echo '<p><label><strong>Door Business Date</strong></label><br />';
  echo '<input type="text" class="vms-date" name="vms_square_business_date" value="' . esc_attr($date) . '" style="width:100%;" placeholder="YYYY-MM-DD" />';
  echo '</p>';

  echo '<p><label><strong>Band Split %</strong></label><br />';
  echo '<input type="number" step="0.01" min="0" max="100" name="vms_door_split_pct" value="' . esc_attr($pct) . '" style="width:100%;" />';
  echo '</p>';

  echo '<hr />';
  echo '<p><strong>POS Window Mode</strong></p>';
  echo '<p>';
  echo '<label><input type="radio" name="vms_pos_window_mode" value="business_date" ' . checked($window_mode, 'business_date', false) . ' /> Business Date</label><br />';
  echo '<label><input type="radio" name="vms_pos_window_mode" value="event_window" ' . checked($window_mode, 'event_window', false) . ' /> Event Window</label>';
  echo '</p>';

  echo '<div class="vms-square-event-window-fields">';
  echo '<p><label><strong>Window Start (local)</strong></label><br />';
  echo '<input type="text" name="vms_pos_window_start_local" value="' . esc_attr($window_start_local) . '" style="width:100%;" placeholder="YYYY-MM-DD HH:MM" />';
  echo '</p>';
  echo '<p><label><strong>Window End (local)</strong></label><br />';
  echo '<input type="text" name="vms_pos_window_end_local" value="' . esc_attr($window_end_local) . '" style="width:100%;" placeholder="YYYY-MM-DD HH:MM" />';
  echo '</p>';
  echo '</div>';

  if (vms_square_current_user_can_manage()) {
    echo '<p><button type="button" class="button button-secondary vms-square-snapshot-trigger" data-action-url="' . esc_url($snapshot_pull_url) . '">Pull POS Actuals Now</button></p>';
    echo '<p class="description">This uses saved Event Plan values and stores an auditable snapshot.</p>';
  } else {
    echo '<p class="description">You do not have permission to pull POS snapshots.</p>';
  }

  if ($loc && $date) {
    global $wpdb;
    $daily = vms_square_table_daily();
    $row = $wpdb->get_row($wpdb->prepare(
      "SELECT door_net_ex_tax_amount FROM {$daily} WHERE location_id = %s AND business_date = %s LIMIT 1",
      $loc,
      $date
    ), ARRAY_A);

    if ($row) {
      $net = (int)($row['door_net_ex_tax_amount'] ?? 0);
      $pctf = (float)$pct;
      $band = (int)round($net * ($pctf / 100.0));
      $house = max(0, $net - $band);

      echo '<hr />';
      echo '<p><strong>Door Net (ex tax):</strong><br />' . esc_html(vms_square_fmt_money($net)) . '</p>';
      echo '<p><strong>Band:</strong><br />' . esc_html(vms_square_fmt_money($band)) . '</p>';
      echo '<p><strong>House:</strong><br />' . esc_html(vms_square_fmt_money($house)) . '</p>';
      echo '<p class="description">Saving the Event Plan stores a snapshot for auditability.</p>';
    } else {
      echo '<p class="description">No daily rollup found yet for this location/date. Run sync/backfill, then refresh.</p>';
    }
  } else {
    echo '<p class="description">Select location + date, then save to store a snapshot.</p>';
  }

  echo '<hr />';
  echo '<p><strong>POS Actuals Snapshot</strong></p>';
  if ($snapshot_provider === '' && empty($snapshot_totals)) {
    echo '<p class="description">No POS snapshot saved yet.</p>';
  } else {
    $snapshot_currency = (string)($snapshot_totals['currency'] ?? 'USD');
    $snapshot_source = sanitize_key((string)($snapshot_totals['source'] ?? ''));
    $snapshot_business_date = sanitize_text_field((string)($snapshot_totals['business_date'] ?? ''));
    $snapshot_window_mode = sanitize_key((string)($snapshot_totals['window_mode'] ?? ''));
    $snapshot_window_start_local_saved = vms_square_sanitize_local_datetime_input((string)($snapshot_totals['window_start_local'] ?? ''));
    $snapshot_window_end_local_saved = vms_square_sanitize_local_datetime_input((string)($snapshot_totals['window_end_local'] ?? ''));
    $snapshot_orders_count = (int)($snapshot_totals['orders_count'] ?? 0);
    $snapshot_missing_categories = (int)($snapshot_totals['missing_category_count_total'] ?? 0);
    $snapshot_errors = isset($snapshot_totals['errors']) && is_array($snapshot_totals['errors']) ? $snapshot_totals['errors'] : array();
    $snapshot_bucket_labels = isset($snapshot_totals['bucket_labels']) && is_array($snapshot_totals['bucket_labels']) ? $snapshot_totals['bucket_labels'] : array();
    $totals = isset($snapshot_totals['totals']) && is_array($snapshot_totals['totals']) ? $snapshot_totals['totals'] : array();
    $buckets = isset($snapshot_totals['buckets']) && is_array($snapshot_totals['buckets']) ? $snapshot_totals['buckets'] : array();

    if ($snapshot_provider !== '') {
      echo '<p><strong>Provider:</strong> ' . esc_html($snapshot_provider) . '</p>';
    }
    if ($snapshot_pulled_at !== '') {
      echo '<p><strong>Pulled At (UTC):</strong> ' . esc_html($snapshot_pulled_at) . '</p>';
    }
    if (in_array($snapshot_window_mode, array('business_date', 'event_window'), true)) {
      $window_mode_label = ($snapshot_window_mode === 'event_window') ? 'Event Window' : 'Business Date';
      echo '<p><strong>Window Mode:</strong> ' . esc_html($window_mode_label) . '</p>';
    }
    if ($snapshot_business_date !== '') {
      echo '<p><strong>Business Date:</strong> ' . esc_html($snapshot_business_date) . '</p>';
    }
    if ($snapshot_window_start_local_saved !== '' || $snapshot_window_end_local_saved !== '') {
      echo '<p><strong>Window (Local):</strong> ' . esc_html($snapshot_window_start_local_saved . ' → ' . $snapshot_window_end_local_saved) . '</p>';
    }
    if ($snapshot_window_start_utc !== '' || $snapshot_window_end_utc !== '') {
      echo '<p><strong>Window (UTC):</strong> ' . esc_html($snapshot_window_start_utc . ' → ' . $snapshot_window_end_utc) . '</p>';
    }
    if ($snapshot_source !== '') {
      echo '<p><strong>Source:</strong> ' . esc_html(str_replace('_', ' ', $snapshot_source)) . '</p>';
    }
    if ($snapshot_mapping_marker !== '') {
      echo '<p><strong>Mapping Marker:</strong> ' . esc_html($snapshot_mapping_marker) . '</p>';
    }

    if (!empty($totals)) {
      $fields = array(
        'gross' => 'Gross',
        'tax' => 'Tax',
        'discount' => 'Discount',
        'tip' => 'Tip',
        'service_charges' => 'Service Charges',
        'net_ex_tax' => 'Net Ex Tax',
      );
      echo '<p><strong>Currency:</strong> ' . esc_html($snapshot_currency) . '</p>';
      echo '<p><strong>Orders:</strong> ' . (int)$snapshot_orders_count . '</p>';
      if ($snapshot_missing_categories > 0) {
        echo '<p><strong>Missing Category Count:</strong> ' . (int)$snapshot_missing_categories . '</p>';
      }
      foreach ($fields as $field_key => $field_label) {
        $value = isset($totals[$field_key]) ? (int)$totals[$field_key] : 0;
        echo '<p><strong>' . esc_html($field_label) . ':</strong> ' . esc_html(vms_square_fmt_money($value)) . '</p>';
      }
    }

    if (!empty($buckets)) {
      echo '<details class="vms-square-details"><summary>Show Bucket Totals</summary>';
      echo '<table class="widefat striped vms-square-mini-table"><thead><tr><th>Bucket</th><th>Gross</th><th>Tax</th><th>Discount</th><th>Tip</th><th>Service</th><th>Net Ex Tax</th><th>Lines</th><th>Missing Category</th></tr></thead><tbody>';
      foreach ($buckets as $bucket_key => $bucket_totals) {
        if (!is_array($bucket_totals)) {
          continue;
        }
        $label = isset($snapshot_bucket_labels[$bucket_key]) ? (string)$snapshot_bucket_labels[$bucket_key] : ucwords(str_replace('_', ' ', (string)$bucket_key));
        echo '<tr>';
        echo '<td>' . esc_html($label . ' (' . $bucket_key . ')') . '</td>';
        echo '<td>' . esc_html(vms_square_fmt_money((int)($bucket_totals['gross'] ?? 0))) . '</td>';
        echo '<td>' . esc_html(vms_square_fmt_money((int)($bucket_totals['tax'] ?? 0))) . '</td>';
        echo '<td>' . esc_html(vms_square_fmt_money((int)($bucket_totals['discount'] ?? 0))) . '</td>';
        echo '<td>' . esc_html(vms_square_fmt_money((int)($bucket_totals['tip'] ?? 0))) . '</td>';
        echo '<td>' . esc_html(vms_square_fmt_money((int)($bucket_totals['service_charges'] ?? 0))) . '</td>';
        echo '<td>' . esc_html(vms_square_fmt_money((int)($bucket_totals['net_ex_tax'] ?? 0))) . '</td>';
        echo '<td>' . (int)($bucket_totals['line_item_count'] ?? 0) . '</td>';
        echo '<td>' . (int)($bucket_totals['missing_category_count'] ?? 0) . '</td>';
        echo '</tr>';
      }
      echo '</tbody></table>';
      echo '</details>';
    }

    if (!empty($snapshot_errors)) {
      echo '<div class="notice notice-warning inline"><p>' . esc_html(implode(' | ', array_map('strval', $snapshot_errors))) . '</p></div>';
    }
  }
}

function vms_square_event_plan_save_metabox(int $post_id, $post): void
{
  if (!is_object($post)) {
    return;
  }
  if ($post->post_type !== vms_square_event_plan_post_type()) {
    return;
  }

  if (!isset($_POST['vms_square_event_plan_nonce']) || !wp_verify_nonce((string)$_POST['vms_square_event_plan_nonce'], 'vms_square_event_plan_save')) {
    return;
  }

  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
    return;
  }
  if (!current_user_can('edit_post', $post_id)) {
    return;
  }

  $loc = isset($_POST['vms_square_location_id']) ? sanitize_text_field((string)$_POST['vms_square_location_id']) : '';
  $date = isset($_POST['vms_square_business_date']) ? sanitize_text_field((string)$_POST['vms_square_business_date']) : '';
  $pct = isset($_POST['vms_door_split_pct']) ? (float)$_POST['vms_door_split_pct'] : 0.0;
  $window_mode = isset($_POST['vms_pos_window_mode']) ? sanitize_text_field((string)$_POST['vms_pos_window_mode']) : vms_square_effective_event_window_default_mode();
  $window_start_local = isset($_POST['vms_pos_window_start_local']) ? vms_square_sanitize_local_datetime_input((string)$_POST['vms_pos_window_start_local']) : '';
  $window_end_local = isset($_POST['vms_pos_window_end_local']) ? vms_square_sanitize_local_datetime_input((string)$_POST['vms_pos_window_end_local']) : '';

  if (!in_array($window_mode, array('business_date', 'event_window'), true)) {
    $window_mode = vms_square_effective_event_window_default_mode();
  }
  $pct = max(0.0, min(100.0, $pct));

  update_post_meta($post_id, '_vms_square_location_id', $loc);
  update_post_meta($post_id, '_vms_square_business_date', $date);
  update_post_meta($post_id, '_vms_door_split_pct', (string)$pct);
  update_post_meta($post_id, '_vms_pos_window_mode', $window_mode);

  if ($window_start_local !== '') {
    update_post_meta($post_id, '_vms_pos_window_start_local', $window_start_local);
  } else {
    delete_post_meta($post_id, '_vms_pos_window_start_local');
  }
  if ($window_end_local !== '') {
    update_post_meta($post_id, '_vms_pos_window_end_local', $window_end_local);
  } else {
    delete_post_meta($post_id, '_vms_pos_window_end_local');
  }

  if ($loc && $date) {
    global $wpdb;
    $daily = vms_square_table_daily();
    $row = $wpdb->get_row($wpdb->prepare(
      "SELECT door_net_ex_tax_amount FROM {$daily} WHERE location_id = %s AND business_date = %s LIMIT 1",
      $loc,
      $date
    ), ARRAY_A);

    if ($row) {
      $net = (int)($row['door_net_ex_tax_amount'] ?? 0);
      $band = (int)round($net * ($pct / 100.0));
      $house = max(0, $net - $band);

      update_post_meta($post_id, '_vms_door_net_ex_tax_amount', (string)$net);
      update_post_meta($post_id, '_vms_door_band_amount', (string)$band);
      update_post_meta($post_id, '_vms_door_house_amount', (string)$house);
      update_post_meta($post_id, '_vms_door_calc_ran_at_utc', gmdate('Y-m-d H:i:s'));
      delete_post_meta($post_id, '_vms_door_calc_error');
    } else {
      update_post_meta($post_id, '_vms_door_calc_error', 'No daily rollup found for selected location/date.');
    }
  }
}
