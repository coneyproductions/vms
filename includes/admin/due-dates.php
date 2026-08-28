<?php
/**
 * Admin: Due Dates / Compliance Obligations
 *
 * Menu slug: vms-due-dates
 */

defined('ABSPATH') || exit;

// ---------------------------
// Submenu
// ---------------------------
add_action('admin_menu', function () {
  add_submenu_page(
    'vms-dashboard',
    'Due Dates',
    'Due Dates',
    'manage_options',
    'vms-due-dates',
    'bvmgr_render_due_dates_admin_page'
  );
}, 30);

// ---------------------------
// Admin-post handlers
// ---------------------------
add_action('admin_post_vms_due_add_payee', 'bvmgr_due_admin_post_add_payee');
add_action('admin_post_vms_due_edit_payee', 'bvmgr_due_admin_post_edit_payee');
add_action('admin_post_vms_due_archive_payee', 'bvmgr_due_admin_post_archive_payee');

add_action('admin_post_vms_due_add_obligation', 'bvmgr_due_admin_post_add_obligation');
add_action('admin_post_vms_due_edit_obligation', 'bvmgr_due_admin_post_edit_obligation');
add_action('admin_post_vms_due_archive_obligation', 'bvmgr_due_admin_post_archive_obligation');
add_action('admin_post_vms_due_complete', 'bvmgr_due_admin_post_complete');
add_action('admin_post_vms_due_uncomplete', 'bvmgr_due_admin_post_uncomplete');

add_action('admin_post_vms_due_seed_templates', 'bvmgr_due_admin_post_seed_templates');

function bvmgr_due_admin_redirect(string $msg = '', array $extra_query = [], string $fragment = ''): void {
  $url = admin_url('admin.php?page=vms-due-dates');
  $query = [];
  if ($msg !== '') {
    $query['vms_due_msg'] = sanitize_key($msg);
  }
  foreach ($extra_query as $k => $v) {
    if (is_array($v) || is_object($v)) continue;
    $k = sanitize_key((string) $k);
    if ($k === '') continue;
    $query[$k] = sanitize_text_field((string) $v);
  }
  if (!empty($query)) {
    $url = add_query_arg($query, $url);
  }
  if ($fragment !== '') {
    $frag = preg_replace('/[^A-Za-z0-9\-_]/', '', $fragment);
    if (is_string($frag) && $frag !== '') {
      $url .= '#' . $frag;
    }
  }
  wp_safe_redirect($url);
  exit;
}

function bvmgr_due_parse_identifiers(string $raw): array {
  $raw = (string) $raw;
  $lines = preg_split('/\r\n|\r|\n/', $raw);
  $out = [];

  foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '') continue;

    // Accept either "Label: Value" or just "Value".
    $label = '';
    $value = $line;

    if (strpos($line, ':') !== false) {
      $parts = explode(':', $line, 2);
      $label = sanitize_text_field(trim($parts[0]));
      $value = sanitize_text_field(trim($parts[1]));
    } else {
      $value = sanitize_text_field($value);
    }

    if ($value === '') continue;
    $out[] = [
      'label' => $label,
      'value' => $value,
    ];
  }

  return $out;
}

function bvmgr_due_identifiers_to_textarea(array $identifiers): string {
  $lines = [];
  foreach ($identifiers as $row) {
    if (!is_array($row)) continue;
    $lab = trim((string) ($row['label'] ?? ''));
    $val = trim((string) ($row['value'] ?? ''));
    if ($val === '') continue;
    $lines[] = ($lab !== '') ? ($lab . ': ' . $val) : $val;
  }
  return implode("\n", $lines);
}

function bvmgr_due_admin_collect_instance_filters(array $src): array {
  $status = sanitize_key((string) ($src['due_status'] ?? 'open'));
  $cadence = sanitize_key((string) ($src['due_cadence'] ?? 'all'));
  $payee = sanitize_text_field((string) ($src['due_payee'] ?? 'all'));
  $include_archived = !empty($src['due_include_archived']) ? 1 : 0;

  $allowed_status = ['open', 'completed', 'overdue', 'due_soon', 'upcoming', 'all'];
  if (!in_array($status, $allowed_status, true)) {
    $status = 'open';
  }

  $allowed_cadence = ['all', 'monthly', 'quarterly', 'annual', 'one_time'];
  if (!in_array($cadence, $allowed_cadence, true)) {
    $cadence = 'all';
  }

  if ($payee !== 'all' && $payee !== 'none') {
    $payee = sanitize_key($payee);
    if ($payee === '') {
      $payee = 'all';
    }
  }

  return [
    'status' => $status,
    'cadence' => $cadence,
    'payee' => $payee,
    'include_archived' => $include_archived,
  ];
}

function bvmgr_due_admin_instance_filter_query_args(array $filters): array {
  $out = [];
  $status = sanitize_key((string) ($filters['status'] ?? 'open'));
  $cadence = sanitize_key((string) ($filters['cadence'] ?? 'all'));
  $payee = sanitize_text_field((string) ($filters['payee'] ?? 'all'));
  $include_archived = !empty($filters['include_archived']) ? 1 : 0;

  if ($status !== 'open') {
    $out['due_status'] = $status;
  }
  if ($cadence !== 'all') {
    $out['due_cadence'] = $cadence;
  }
  if ($payee !== 'all') {
    $out['due_payee'] = $payee;
  }
  if ($include_archived) {
    $out['due_include_archived'] = '1';
  }

  return $out;
}

function bvmgr_due_admin_render_instance_filter_hidden_inputs(array $filters): void {
  $status = sanitize_key((string) ($filters['status'] ?? 'open'));
  $cadence = sanitize_key((string) ($filters['cadence'] ?? 'all'));
  $payee = sanitize_text_field((string) ($filters['payee'] ?? 'all'));
  $include_archived = !empty($filters['include_archived']) ? 1 : 0;

  echo '<input type="hidden" name="due_status" value="' . esc_attr($status) . '" />';
  echo '<input type="hidden" name="due_cadence" value="' . esc_attr($cadence) . '" />';
  echo '<input type="hidden" name="due_payee" value="' . esc_attr($payee) . '" />';
  if ($include_archived) {
    echo '<input type="hidden" name="due_include_archived" value="1" />';
  }
}

function bvmgr_due_admin_query_arg(string $key): string {
  // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended -- Read-only admin routing/message parameters only affect page state.
  if (!isset($_GET[$key])) {
    return '';
  }

  // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only admin routing/message parameters only affect page state.
  return (string) wp_unslash($_GET[$key]);
}

function bvmgr_due_admin_post_add_payee(): void {
  if (!current_user_can('manage_options')) wp_die('Forbidden');
  bvmgr_check_admin_referer_compat('bvmgr_due_add_payee');

  $name = sanitize_text_field(wp_unslash((string) ($_POST['payee_name'] ?? '')));
  $account_number = sanitize_text_field(wp_unslash((string) ($_POST['payee_account_number'] ?? '')));
  $account_id = sanitize_text_field(wp_unslash((string) ($_POST['payee_account_id'] ?? '')));
  $notes = sanitize_textarea_field(wp_unslash((string) ($_POST['payee_notes'] ?? '')));
  $identifiers = bvmgr_due_parse_identifiers(sanitize_textarea_field(wp_unslash((string) ($_POST['payee_identifiers'] ?? ''))));

  if ($name === '') {
    bvmgr_due_admin_redirect('payee_missing_name');
  }

  $payees = bvmgr_due_get_payees();
  $id = bvmgr_due_new_id('payee');

  $payees[$id] = [
    'id' => $id,
    'name' => $name,
    'account_number' => $account_number,
    'account_id' => $account_id,
    'notes' => $notes,
    'identifiers' => $identifiers,
    'is_active' => 1,
  ];

  bvmgr_due_save_payees($payees);
  bvmgr_due_admin_redirect('payee_added');
}

function bvmgr_due_admin_post_edit_payee(): void {
  if (!current_user_can('manage_options')) wp_die('Forbidden');
  bvmgr_check_admin_referer_compat('bvmgr_due_edit_payee');

  $id = sanitize_key(wp_unslash((string) ($_POST['payee_id'] ?? '')));
  if ($id === '') {
    bvmgr_due_admin_redirect('payee_missing_id');
  }

  $name = sanitize_text_field(wp_unslash((string) ($_POST['payee_name'] ?? '')));
  $account_number = sanitize_text_field(wp_unslash((string) ($_POST['payee_account_number'] ?? '')));
  $account_id = sanitize_text_field(wp_unslash((string) ($_POST['payee_account_id'] ?? '')));
  $notes = sanitize_textarea_field(wp_unslash((string) ($_POST['payee_notes'] ?? '')));
  $identifiers = bvmgr_due_parse_identifiers(sanitize_textarea_field(wp_unslash((string) ($_POST['payee_identifiers'] ?? ''))));

  if ($name === '') {
    bvmgr_due_admin_redirect('payee_missing_name');
  }

  $payees = bvmgr_due_get_payees();
  if (empty($payees[$id]) || !is_array($payees[$id])) {
    bvmgr_due_admin_redirect('payee_not_found');
  }

  $existing = $payees[$id];
  $payees[$id] = [
    'id' => $id,
    'name' => $name,
    'account_number' => $account_number,
    'account_id' => $account_id,
    'notes' => $notes,
    'identifiers' => $identifiers,
    'is_active' => isset($existing['is_active']) ? (int) $existing['is_active'] : 1,
  ];

  bvmgr_due_save_payees($payees);
  bvmgr_due_admin_redirect('payee_updated');
}

function bvmgr_due_admin_post_archive_payee(): void {
  if (!current_user_can('manage_options')) wp_die('Forbidden');
  bvmgr_check_admin_referer_compat('bvmgr_due_archive_payee');

  $id = sanitize_key((string) ($_GET['id'] ?? ''));
  $payees = bvmgr_due_get_payees();
  if (!empty($payees[$id]) && is_array($payees[$id])) {
    $payees[$id]['is_active'] = 0;
    bvmgr_due_save_payees($payees);
  }

  bvmgr_due_admin_redirect('payee_archived');
}

function bvmgr_due_admin_post_add_obligation(): void {
  if (!current_user_can('manage_options')) wp_die('Forbidden');
  bvmgr_check_admin_referer_compat('bvmgr_due_add_obligation');

  $title = sanitize_text_field(wp_unslash((string) ($_POST['ob_title'] ?? '')));
  $payee_id = sanitize_key(wp_unslash((string) ($_POST['ob_payee_id'] ?? '')));
  // Payee is usually required, but some obligations are general reminders (e.g., filing 1099s).
  // When checked, a blank payee will not be treated as a configuration error on the dashboard.
  $payee_optional = !empty($_POST['ob_payee_optional']);
  $cadence = sanitize_key(wp_unslash((string) ($_POST['ob_cadence'] ?? 'monthly')));

  $day = absint(wp_unslash((string) ($_POST['ob_day'] ?? '1')));
  $month = absint(wp_unslash((string) ($_POST['ob_month'] ?? '1')));
  $q_month = absint(wp_unslash((string) ($_POST['ob_quarter_month'] ?? '1')));
  $eom = !empty($_POST['ob_eom']);
  $one_time_date = sanitize_text_field(wp_unslash((string) ($_POST['ob_due_date'] ?? '')));

  $start_date = sanitize_text_field(wp_unslash((string) ($_POST['ob_start_date'] ?? '')));
  $end_date = sanitize_text_field(wp_unslash((string) ($_POST['ob_end_date'] ?? '')));

  $remind_days = absint(wp_unslash((string) ($_POST['ob_remind_days'] ?? '14')));
  $remind_days = max(0, min(365, $remind_days));

  if ($title === '') {
    bvmgr_due_admin_redirect('ob_missing_title');
  }

  // Normalize cadence
  $allowed = ['monthly', 'quarterly', 'annual', 'one_time'];
  if (!in_array($cadence, $allowed, true)) $cadence = 'monthly';

  // Normalize one-time date
  if ($cadence === 'one_time') {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $one_time_date)) {
      $one_time_date = '';
    }
  } else {
    $one_time_date = '';
  }

  // EOM is meaningful only for recurring cadences.
  if ($cadence === 'one_time') {
    $eom = false;
  }

  $today_ymd = wp_date('Y-m-d', time(), wp_timezone());

  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
    $start_date = '';
  }
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
    $end_date = '';
  }

  if ($start_date === '') {
    if ($cadence === 'one_time' && $one_time_date !== '') {
      $start_date = $one_time_date;
    } else {
      $start_date = $today_ymd;
    }
  }

  if ($end_date !== '' && $end_date < $start_date) {
    $end_date = '';
  }

  $obs = bvmgr_due_get_obligations();
  $id = bvmgr_due_new_id('ob');

  $row = [
    'id' => $id,
    'title' => $title,
    'payee_id' => $payee_id,
    // Storage: vms_due_obligations_v1 (per-obligation).
    // 1 = payee required (default), 0 = payee optional (general reminder).
    'payee_required' => $payee_optional ? 0 : 1,
    'cadence' => $cadence,
    'day' => max(1, min(31, $day)),
    'month' => max(1, min(12, $month)),
    'quarter_month' => max(1, min(3, $q_month)),
    'eom' => $eom ? 1 : 0,
    'due_date' => $one_time_date,
    'start_date' => $start_date,
    'end_date' => $end_date,
    'remind_days' => $remind_days,
    'is_active' => 1,
  ];

  $obs[$id] = $row;
  bvmgr_due_save_obligations($obs);

  bvmgr_due_admin_redirect('ob_added');
}

function bvmgr_due_admin_post_edit_obligation(): void {
  if (!current_user_can('manage_options')) wp_die('Forbidden');
  bvmgr_check_admin_referer_compat('bvmgr_due_edit_obligation');

  $id = sanitize_key(wp_unslash((string) ($_POST['ob_id'] ?? '')));
  if ($id === '') {
    bvmgr_due_admin_redirect('ob_missing_id');
  }

  $title = sanitize_text_field(wp_unslash((string) ($_POST['ob_title'] ?? '')));
  $payee_id = sanitize_key(wp_unslash((string) ($_POST['ob_payee_id'] ?? '')));
  $payee_optional = !empty($_POST['ob_payee_optional']);
  $cadence = sanitize_key(wp_unslash((string) ($_POST['ob_cadence'] ?? 'monthly')));

  $day = absint(wp_unslash((string) ($_POST['ob_day'] ?? '1')));
  $month = absint(wp_unslash((string) ($_POST['ob_month'] ?? '1')));
  $q_month = absint(wp_unslash((string) ($_POST['ob_quarter_month'] ?? '1')));
  $eom = !empty($_POST['ob_eom']);
  $one_time_date = sanitize_text_field(wp_unslash((string) ($_POST['ob_due_date'] ?? '')));

  $start_date = sanitize_text_field(wp_unslash((string) ($_POST['ob_start_date'] ?? '')));
  $end_date = sanitize_text_field(wp_unslash((string) ($_POST['ob_end_date'] ?? '')));

  $remind_days = absint(wp_unslash((string) ($_POST['ob_remind_days'] ?? '14')));
  $remind_days = max(0, min(365, $remind_days));

  if ($title === '') {
    bvmgr_due_admin_redirect('ob_missing_title');
  }

  // Normalize cadence
  $allowed = ['monthly', 'quarterly', 'annual', 'one_time'];
  if (!in_array($cadence, $allowed, true)) $cadence = 'monthly';

  // Normalize one-time date
  if ($cadence === 'one_time') {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $one_time_date)) {
      $one_time_date = '';
    }
  } else {
    $one_time_date = '';
  }

  // EOM is meaningful only for recurring cadences.
  if ($cadence === 'one_time') {
    $eom = false;
  }

  $today_ymd = wp_date('Y-m-d', time(), wp_timezone());

  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
    $start_date = '';
  }
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
    $end_date = '';
  }

  if ($start_date === '') {
    if ($cadence === 'one_time' && $one_time_date !== '') {
      $start_date = $one_time_date;
    } else {
      $start_date = $today_ymd;
    }
  }

  if ($end_date !== '' && $end_date < $start_date) {
    $end_date = '';
  }

  $obs = bvmgr_due_get_obligations();
  if (empty($obs[$id]) || !is_array($obs[$id])) {
    bvmgr_due_admin_redirect('ob_not_found');
  }

  $existing = $obs[$id];
  $obs[$id] = [
    'id' => $id,
    'title' => $title,
    'payee_id' => $payee_id,
    'payee_required' => $payee_optional ? 0 : 1,
    'cadence' => $cadence,
    'day' => max(1, min(31, $day)),
    'month' => max(1, min(12, $month)),
    'quarter_month' => max(1, min(3, $q_month)),
    'eom' => $eom ? 1 : 0,
    'due_date' => $one_time_date,
    'start_date' => $start_date,
    'end_date' => $end_date,
    'remind_days' => $remind_days,
    'is_active' => isset($existing['is_active']) ? (int) $existing['is_active'] : 1,
  ];

  bvmgr_due_save_obligations($obs);
  bvmgr_due_admin_redirect('ob_updated');
}

function bvmgr_due_admin_post_archive_obligation(): void {
  if (!current_user_can('manage_options')) wp_die('Forbidden');
  bvmgr_check_admin_referer_compat('bvmgr_due_archive_obligation');

  $id = sanitize_key((string) ($_GET['id'] ?? ''));
  $obs = bvmgr_due_get_obligations();
  if (!empty($obs[$id]) && is_array($obs[$id])) {
    $obs[$id]['is_active'] = 0;
    bvmgr_due_save_obligations($obs);
  }

  bvmgr_due_admin_redirect('ob_archived');
}

function bvmgr_due_admin_post_complete(): void {
  if (!current_user_can('manage_options')) wp_die('Forbidden');
  bvmgr_check_admin_referer_compat('bvmgr_due_complete');

  $oid = sanitize_key(wp_unslash((string) ($_POST['obligation_id'] ?? '')));
  $due = sanitize_text_field(wp_unslash((string) ($_POST['due_date'] ?? '')));
  $notes = sanitize_textarea_field(wp_unslash((string) ($_POST['notes'] ?? '')));
  $proof = esc_url_raw(wp_unslash((string) ($_POST['proof_url'] ?? '')));

  $filters = bvmgr_due_admin_collect_instance_filters((array) $_POST);
  $query = bvmgr_due_admin_instance_filter_query_args($filters);

  if (!function_exists('bvmgr_due_safe_complete')) {
    bvmgr_due_admin_redirect('due_complete_error_helper_missing', $query, 'vms-due-instances');
  }

  $result = (array) bvmgr_due_safe_complete($oid, $due, $notes, $proof);
  if (!empty($result['ok'])) {
    if (!empty($result['already'])) {
      bvmgr_due_admin_redirect('due_already_completed', $query, 'vms-due-instances');
    }
    bvmgr_due_admin_redirect('due_completed', $query, 'vms-due-instances');
  }

  $err = sanitize_key((string) ($result['error'] ?? 'unknown'));
  bvmgr_due_admin_redirect('due_complete_error_' . ($err !== '' ? $err : 'unknown'), $query, 'vms-due-instances');
}

function bvmgr_due_admin_post_uncomplete(): void {
  if (!current_user_can('manage_options')) wp_die('Forbidden');
  bvmgr_check_admin_referer_compat('bvmgr_due_uncomplete');

  $oid = sanitize_key(wp_unslash((string) ($_POST['obligation_id'] ?? '')));
  $due = sanitize_text_field(wp_unslash((string) ($_POST['due_date'] ?? '')));
  $notes = sanitize_textarea_field(wp_unslash((string) ($_POST['notes'] ?? '')));
  $proof = esc_url_raw(wp_unslash((string) ($_POST['proof_url'] ?? '')));

  $filters = bvmgr_due_admin_collect_instance_filters((array) $_POST);
  $query = bvmgr_due_admin_instance_filter_query_args($filters);

  if (!function_exists('bvmgr_due_safe_uncomplete')) {
    bvmgr_due_admin_redirect('due_reopen_error_helper_missing', $query, 'vms-due-instances');
  }

  $result = (array) bvmgr_due_safe_uncomplete($oid, $due, $notes, $proof);
  if (!empty($result['ok'])) {
    if (!empty($result['already'])) {
      bvmgr_due_admin_redirect('due_already_open', $query, 'vms-due-instances');
    }
    bvmgr_due_admin_redirect('due_reopened', $query, 'vms-due-instances');
  }

  $err = sanitize_key((string) ($result['error'] ?? 'unknown'));
  bvmgr_due_admin_redirect('due_reopen_error_' . ($err !== '' ? $err : 'unknown'), $query, 'vms-due-instances');
}

function bvmgr_due_admin_post_seed_templates(): void {
  if (!current_user_can('manage_options')) wp_die('Forbidden');
  bvmgr_check_admin_referer_compat('bvmgr_due_seed_templates');

  // Seed a few safe, generic templates. Operator can edit later.
  $payees = bvmgr_due_get_payees();
  $obs = bvmgr_due_get_obligations();

  $today_ymd = wp_date('Y-m-d', time(), wp_timezone());

  $seedPayeeId = '';
  foreach ($payees as $pid => $p) {
    if (is_array($p) && isset($p['name']) && $p['name'] === 'IRS') {
      $seedPayeeId = $pid;
      break;
    }
  }

  if ($seedPayeeId === '') {
    $seedPayeeId = bvmgr_due_new_id('payee');
    $payees[$seedPayeeId] = [
      'id' => $seedPayeeId,
      'name' => 'IRS',
      'account_number' => '',
      'account_id' => '',
      'notes' => '',
      'identifiers' => [],
      'is_active' => 1,
    ];
  }

  // IRS Estimated Tax - Quarterly (month 1 of quarter) on day 15.
  $id1 = bvmgr_due_new_id('ob');
  $obs[$id1] = [
    'id' => $id1,
    'title' => 'Estimated Tax Payment',
    'payee_id' => $seedPayeeId,
    'payee_required' => 1,
    'cadence' => 'quarterly',
    'day' => 15,
    'month' => 1,
    'quarter_month' => 1,
    'eom' => 0,
    'due_date' => '',
    'start_date' => $today_ymd,
    'end_date' => '',
    'remind_days' => 14,
    'is_active' => 1,
  ];

  bvmgr_due_save_payees($payees);
  bvmgr_due_save_obligations($obs);

  bvmgr_due_admin_redirect('templates_seeded');
}

// ---------------------------
// Render
// ---------------------------
function bvmgr_due_render_admin_notices(): void {
  $msg = sanitize_key(bvmgr_due_admin_query_arg('vms_due_msg'));
  if ($msg === '') {
    return;
  }

  $is_error = (strpos($msg, 'error') !== false);
  echo '<div class="notice ' . ($is_error ? 'notice-error' : 'notice-success') . ' is-dismissible"><p>';
  echo esc_html(str_replace('_', ' ', $msg));
  echo '</p></div>';
}

function bvmgr_render_due_dates_admin_page(): void {
  if (function_exists('bvmgr_admin_ui_render_shell')) {
    bvmgr_admin_ui_render_shell(
      array(
        'title' => __('Due Dates', 'backstage-venue-manager'),
        'notices_callback' => 'bvmgr_due_render_admin_notices',
      ),
      'bvmgr_render_due_dates_admin_page_content'
    );
    return;
  }

  echo '<div class="wrap"><h1>Due Dates</h1>';
  bvmgr_render_due_dates_admin_page_content();
  echo '</div>';
}

function bvmgr_render_due_dates_admin_page_content(): void {
  if (!current_user_can('manage_options')) {
    wp_die('Forbidden');
  }

  $payees = bvmgr_due_get_payees();
  $obs = bvmgr_due_get_obligations();
  $log = bvmgr_due_get_log();
  $log_idx = bvmgr_due_log_index($log);

  $edit_payee_id = sanitize_key(bvmgr_due_admin_query_arg('edit_payee'));
  $edit_ob_id = sanitize_key(bvmgr_due_admin_query_arg('edit_ob'));

  // Build a simple map of payee names for display.
  $payeeNames = [];
  foreach ($payees as $pid => $p) {
    if (!is_array($p)) continue;
    $payeeNames[$pid] = (string) ($p['name'] ?? '');
  }

  // For "next due" display.
  $dash_items = bvmgr_due_build_dashboard_items(120);
  $nextByOb = [];
  foreach ($dash_items as $it) {
    if (!is_array($it)) continue;
    if ((string) ($it['status'] ?? '') === 'overdue') continue;
    $oid = (string) ($it['obligation_id'] ?? '');
    if ($oid === '') continue;
    if (!isset($nextByOb[$oid])) {
      $nextByOb[$oid] = (string) ($it['due_date'] ?? '');
    }
  }

  $instance_filters = bvmgr_due_admin_collect_instance_filters([
    'due_status' => bvmgr_due_admin_query_arg('due_status'),
    'due_cadence' => bvmgr_due_admin_query_arg('due_cadence'),
    'due_payee' => bvmgr_due_admin_query_arg('due_payee'),
    'due_include_archived' => bvmgr_due_admin_query_arg('due_include_archived'),
  ]);
  $instance_resp = bvmgr_due_build_obligations_list_response([
    'status' => $instance_filters['status'],
    'cadence' => $instance_filters['cadence'],
    'payee_id' => $instance_filters['payee'],
    'include_archived' => !empty($instance_filters['include_archived']),
    'lookback_days' => 120,
    'lookahead_days' => 120,
    'limit' => 500,
  ]);
  $instance_counts = is_array($instance_resp['counts'] ?? null) ? $instance_resp['counts'] : [];
  $instance_items = is_array($instance_resp['items'] ?? null) ? $instance_resp['items'] : [];
  $instance_window = is_array($instance_resp['window'] ?? null) ? $instance_resp['window'] : [];

  echo '<p class="description">Track recurring compliance and other obligations separately from Vendors. Dashboard shows upcoming / overdue items, and completions are logged (append-only).</p>';

  // Seed templates
  echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-due-seed-form">';
  wp_nonce_field('bvmgr_due_seed_templates');
  echo '<input type="hidden" name="action" value="vms_due_seed_templates" />';
  echo '<button type="submit" class="button">Add sample templates</button>';
  echo '</form>';

  // ---------------- Payees ----------------
  echo '<hr />';
  echo '<h2>Payees</h2>';
  echo '<p class="description">Payees are who you pay (state agency, IRS, PRO, etc.). You can store an Account Number / Account ID, plus any extra IDs as "Label: Value" lines.</p>';

  $editingPayee = ($edit_payee_id !== '' && !empty($payees[$edit_payee_id]) && is_array($payees[$edit_payee_id]));
  $payeeEdit = $editingPayee ? $payees[$edit_payee_id] : null;

  if ($editingPayee) {
    $cancel_url = admin_url('admin.php?page=vms-due-dates#vms-payee-' . rawurlencode($edit_payee_id));
    echo '<h3 id="vms-due-edit-payee">Edit Payee</h3>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('bvmgr_due_edit_payee');
    echo '<input type="hidden" name="action" value="vms_due_edit_payee" />';
    echo '<input type="hidden" name="payee_id" value="' . esc_attr($edit_payee_id) . '" />';
    echo '<table class="form-table" role="presentation">';
    echo '<tr><th scope="row"><label for="payee_name">Name</label></th><td><input name="payee_name" id="payee_name" class="regular-text" required value="' . esc_attr((string) ($payeeEdit['name'] ?? '')) . '" /></td></tr>';
    echo '<tr><th scope="row"><label for="payee_account_number">Account Number</label></th><td><input name="payee_account_number" id="payee_account_number" class="regular-text" value="' . esc_attr((string) ($payeeEdit['account_number'] ?? '')) . '" /></td></tr>';
    echo '<tr><th scope="row"><label for="payee_account_id">Account ID</label></th><td><input name="payee_account_id" id="payee_account_id" class="regular-text" value="' . esc_attr((string) ($payeeEdit['account_id'] ?? '')) . '" /></td></tr>';
    echo '<tr><th scope="row"><label for="payee_identifiers">Additional IDs</label></th><td><textarea name="payee_identifiers" id="payee_identifiers" class="large-text" rows="3" placeholder="e.g. Permit: ABC-999">' . esc_textarea(bvmgr_due_identifiers_to_textarea((array) ($payeeEdit['identifiers'] ?? []))) . '</textarea></td></tr>';
    echo '<tr><th scope="row"><label for="payee_notes">Notes</label></th><td><textarea name="payee_notes" id="payee_notes" class="large-text" rows="2">' . esc_textarea((string) ($payeeEdit['notes'] ?? '')) . '</textarea></td></tr>';
    echo '</table>';
    echo '<p>';
    echo '<button type="submit" class="button button-primary">Save Payee</button> ';
    echo '<a class="button" href="' . esc_url($cancel_url) . '">Cancel</a>';
    echo '</p>';
    echo '</form>';
  } else {
    echo '<h3>Add Payee</h3>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" >';
    wp_nonce_field('bvmgr_due_add_payee');
    echo '<input type="hidden" name="action" value="vms_due_add_payee" />';
    echo '<table class="form-table" role="presentation">';
    echo '<tr><th scope="row"><label for="payee_name">Name</label></th><td><input name="payee_name" id="payee_name" class="regular-text" required /></td></tr>';
    echo '<tr><th scope="row"><label for="payee_account_number">Account Number</label></th><td><input name="payee_account_number" id="payee_account_number" class="regular-text" /></td></tr>';
    echo '<tr><th scope="row"><label for="payee_account_id">Account ID</label></th><td><input name="payee_account_id" id="payee_account_id" class="regular-text" /></td></tr>';
    echo '<tr><th scope="row"><label for="payee_identifiers">Additional IDs</label></th><td><textarea name="payee_identifiers" id="payee_identifiers" class="large-text" rows="3" placeholder="e.g. Permit: ABC-999"></textarea></td></tr>';
    echo '<tr><th scope="row"><label for="payee_notes">Notes</label></th><td><textarea name="payee_notes" id="payee_notes" class="large-text" rows="2"></textarea></td></tr>';
    echo '</table>';
    echo '<p><button type="submit" class="button button-primary">Add Payee</button></p>';
    echo '</form>';
  }

  echo '<h3>Existing Payees</h3>';
  if (empty($payees)) {
    echo '<div class="vms-empty-state"><strong>No payees yet.</strong><br>Add your first payee above to start tracking obligations.</div>';
  } else {
    echo '<ul class="vms-due-list">';
    foreach ($payees as $pid => $p) {
      if (!is_array($p)) continue;
      $pid = sanitize_key((string) ($p['id'] ?? $pid));
      $name = (string) ($p['name'] ?? '');
      $active = !empty($p['is_active']);

      $parts = [];

      $acct_num = trim((string) ($p['account_number'] ?? ''));
      $acct_id  = trim((string) ($p['account_id'] ?? ''));
      if ($acct_num !== '') $parts[] = 'Acct#: ' . $acct_num;
      if ($acct_id !== '')  $parts[] = 'Acct ID: ' . $acct_id;

      if (!empty($p['identifiers']) && is_array($p['identifiers'])) {
        foreach ($p['identifiers'] as $idrow) {
          if (!is_array($idrow)) continue;
          $lab = trim((string) ($idrow['label'] ?? ''));
          $val = trim((string) ($idrow['value'] ?? ''));
          if ($val === '') continue;
          $parts[] = ($lab ? $lab . ': ' : '') . $val;
        }
      }

      $ident = implode(' · ', $parts);

      echo '<li class="vms-due-li" id="vms-payee-' . esc_attr($pid) . '">';
      echo '<strong>' . esc_html($name) . '</strong>';
      if (!$active) echo ' <span class="description">(archived)</span>';
      if ($ident) echo '<div class="description">' . esc_html($ident) . '</div>';

      $edit_url = admin_url('admin.php?page=vms-due-dates&edit_payee=' . rawurlencode($pid) . '#vms-due-edit-payee');
      echo '<div>';
      echo '<a class="button button-small" href="' . esc_url($edit_url) . '">Edit</a> ';

      if ($active) {
        $aurl = wp_nonce_url(admin_url('admin-post.php?action=vms_due_archive_payee&id=' . rawurlencode($pid)), 'bvmgr_due_archive_payee');
        echo '<a class="button button-small" href="' . esc_url($aurl) . '">Archive</a>';
      }
      echo '</div>';

      echo '</li>';
    }
    echo '</ul>';
  }

  // ---------------- Obligations ----------------
  echo '<hr />';
  echo '<h2>Obligations</h2>';
  echo '<p class="description">Obligations are the recurring (or one-time) due dates you want surfaced on the dashboard.</p>';

  $editingOb = ($edit_ob_id !== '' && !empty($obs[$edit_ob_id]) && is_array($obs[$edit_ob_id]));
  $obEdit = $editingOb ? $obs[$edit_ob_id] : null;

  if ($editingOb) {
    $cancel_ob_url = admin_url('admin.php?page=vms-due-dates#vms-ob-' . rawurlencode($edit_ob_id));

    $title = (string) ($obEdit['title'] ?? '');
    $payee_id = sanitize_key((string) ($obEdit['payee_id'] ?? ''));
    $cadence = sanitize_key((string) ($obEdit['cadence'] ?? 'monthly'));
    $payee_required = array_key_exists('payee_required', $obEdit) ? ((int) $obEdit['payee_required'] ? 1 : 0) : 1;
    $is_optional = ($payee_required === 0);

    $day = (int) ($obEdit['day'] ?? 1);
    $month = (int) ($obEdit['month'] ?? 1);
    $q_month = (int) ($obEdit['quarter_month'] ?? 1);
    $eom = !empty($obEdit['eom']);
    $due_date = sanitize_text_field((string) ($obEdit['due_date'] ?? ''));
    $start = sanitize_text_field((string) ($obEdit['start_date'] ?? ''));
    $end = sanitize_text_field((string) ($obEdit['end_date'] ?? ''));
    $remind = (int) ($obEdit['remind_days'] ?? 14);

    echo '<h3 id="vms-due-edit-obligation">Edit Obligation</h3>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('bvmgr_due_edit_obligation');
    echo '<input type="hidden" name="action" value="vms_due_edit_obligation" />';
    echo '<input type="hidden" name="ob_id" value="' . esc_attr($edit_ob_id) . '" />';
    echo '<table class="form-table" role="presentation">';
    echo '<tr><th scope="row"><label for="ob_title">Title</label></th><td><input name="ob_title" id="ob_title" class="regular-text" required value="' . esc_attr($title) . '" /></td></tr>';

    echo '<tr><th scope="row"><label for="ob_payee_id">Payee</label></th><td><select name="ob_payee_id" id="ob_payee_id">';
    echo '<option value=""' . selected($payee_id, '', false) . '>(none)</option>';
    foreach ($payeeNames as $pid => $pname) {
      echo '<option value="' . esc_attr($pid) . '"' . selected($payee_id, $pid, false) . '>' . esc_html($pname) . '</option>';
    }
    echo '</select></td></tr>';

    echo '<tr><th scope="row">Payee requirement</th><td>';
    echo '<label><input type="checkbox" name="ob_payee_optional" value="1"' . checked($is_optional, true, false) . ' /> No payee required (general reminder)</label>';
    echo '<div class="description">Leave Payee blank for items like filing 1099s, renewing permits, and other tasks that do not have a single payee.</div>';
    echo '</td></tr>';

    echo '<tr><th scope="row"><label for="ob_cadence">Cadence</label></th><td><select name="ob_cadence" id="ob_cadence">';
    echo '<option value="monthly"' . selected($cadence, 'monthly', false) . '>Monthly</option>';
    echo '<option value="quarterly"' . selected($cadence, 'quarterly', false) . '>Quarterly</option>';
    echo '<option value="annual"' . selected($cadence, 'annual', false) . '>Annual</option>';
    echo '<option value="one_time"' . selected($cadence, 'one_time', false) . '>One-time</option>';
    echo '</select></td></tr>';

    echo '<tr><th scope="row">Rule</th><td>';
    echo '<div class="vms-due-rule-fields">';

    echo '<div class="vms-due-rule-field">';
    echo '<label>Day of month <input name="ob_day" type="number" min="1" max="31" value="' . esc_attr((string) $day) . '" class="vms-input-narrow" /></label>';
    echo '<div class="description">Used for Monthly, Quarterly, and Annual unless End of month is checked.</div>';
    echo '</div>';

    echo '<div class="vms-due-rule-field">';
    echo '<label>Month (annual) <input name="ob_month" type="number" min="1" max="12" value="' . esc_attr((string) $month) . '" class="vms-input-narrow" /></label>';
    echo '<div class="description">Annual only. Example: 4 = April.</div>';
    echo '</div>';

    echo '<div class="vms-due-rule-field">';
    echo '<label>Month in quarter <input name="ob_quarter_month" type="number" min="1" max="3" value="' . esc_attr((string) $q_month) . '" class="vms-input-narrow" /></label>';
    echo '<div class="description">Quarterly only. 1 = Jan/Apr/Jul/Oct, 2 = Feb/May/Aug/Nov, 3 = Mar/Jun/Sep/Dec.</div>';
    echo '</div>';

    echo '<div class="vms-due-rule-field">';
    echo '<label><input type="checkbox" name="ob_eom" value="1"' . checked($eom, true, false) . ' /> End of month (EOM)</label>';
    echo '<div class="description">When checked, the due date is the last calendar day of the month (ignores Day of month).</div>';
    echo '</div>';

    echo '<div class="vms-due-rule-field vms-due-onetime-field">';
    echo '<label>One-time due date <input name="ob_due_date" type="date" value="' . esc_attr($due_date) . '" /></label>';
    echo '<div class="description">One-time only. For one-off deadlines.</div>';
    echo '</div>';

    echo '</div>';
    echo '<div class="description vms-due-rule-desc">Monthly: Month + (Day or EOM). Quarterly: Quarter month + (Day or EOM). Annual: Month + (Day or EOM). One-time: uses Date.</div>';
    echo '</td></tr>';

    echo '<tr><th scope="row">Active range</th><td>';
    echo '<label>Start <input name="ob_start_date" type="date" value="' . esc_attr($start) . '" /></label>';
    echo ' &nbsp; ';
    echo '<label>End <input name="ob_end_date" type="date" value="' . esc_attr($end) . '" /></label>';
    echo '<div class="description">Start date prevents implied overdue history. Leave End blank for ongoing obligations.</div>';
    echo '</td></tr>';

    echo '<tr><th scope="row"><label for="ob_remind_days">Remind (days)</label></th><td><input name="ob_remind_days" id="ob_remind_days" type="number" min="0" max="365" value="' . esc_attr((string) $remind) . '" class="vms-input-narrow" /> <span class="description">Used for "due soon" status.</span></td></tr>';

    echo '</table>';
    echo '<p>';
    echo '<button type="submit" class="button button-primary">Save Obligation</button> ';
    echo '<a class="button" href="' . esc_url($cancel_ob_url) . '">Cancel</a>';
    echo '</p>';
    echo '</form>';
  } else {
    echo '<h3>Add Obligation</h3>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('bvmgr_due_add_obligation');
    echo '<input type="hidden" name="action" value="vms_due_add_obligation" />';
    echo '<table class="form-table" role="presentation">';
    echo '<tr><th scope="row"><label for="ob_title">Title</label></th><td><input name="ob_title" id="ob_title" class="regular-text" required placeholder="e.g. Sales Tax Report" /></td></tr>';

    echo '<tr><th scope="row"><label for="ob_payee_id">Payee</label></th><td><select name="ob_payee_id" id="ob_payee_id">';
    echo '<option value="">(none)</option>';
    foreach ($payeeNames as $pid => $pname) {
      echo '<option value="' . esc_attr($pid) . '">' . esc_html($pname) . '</option>';
    }
    echo '</select></td></tr>';

    echo '<tr><th scope="row">Payee requirement</th><td>';
    echo '<label><input type="checkbox" name="ob_payee_optional" value="1" /> No payee required (general reminder)</label>';
    echo '<div class="description">Leave Payee blank for items like filing 1099s, renewing permits, and other tasks that do not have a single payee.</div>';
    echo '</td></tr>';

    echo '<tr><th scope="row"><label for="ob_cadence">Cadence</label></th><td><select name="ob_cadence" id="ob_cadence">';
    echo '<option value="monthly">Monthly</option>';
    echo '<option value="quarterly">Quarterly</option>';
    echo '<option value="annual">Annual</option>';
    echo '<option value="one_time">One-time</option>';
    echo '</select></td></tr>';

    echo '<tr><th scope="row">Rule</th><td>';

    echo '<div class="vms-due-rule-fields">';

    echo '<div class="vms-due-rule-field">';
    echo '<label>Day of month <input name="ob_day" type="number" min="1" max="31" value="15" class="vms-input-narrow" /></label>';
    echo '<div class="description">Used for Monthly, Quarterly, and Annual unless End of month is checked.</div>';
    echo '</div>';

    echo '<div class="vms-due-rule-field">';
    echo '<label>Month (annual) <input name="ob_month" type="number" min="1" max="12" value="4" class="vms-input-narrow" /></label>';
    echo '<div class="description">Annual only. Example: 4 = April.</div>';
    echo '</div>';

    echo '<div class="vms-due-rule-field">';
    echo '<label>Month in quarter <input name="ob_quarter_month" type="number" min="1" max="3" value="1" class="vms-input-narrow" /></label>';
    echo '<div class="description">Quarterly only. 1 = Jan/Apr/Jul/Oct, 2 = Feb/May/Aug/Nov, 3 = Mar/Jun/Sep/Dec.</div>';
    echo '</div>';

    echo '<div class="vms-due-rule-field">';
    echo '<label><input type="checkbox" name="ob_eom" value="1" /> End of month (EOM)</label>';
    echo '<div class="description">When checked, the due date is the last calendar day of the month (ignores Day of month).</div>';
    echo '</div>';

    echo '<div class="vms-due-rule-field vms-due-onetime-field">';
    echo '<label>One-time due date <input name="ob_due_date" type="date" /></label>';
    echo '<div class="description">One-time only. For one-off deadlines.</div>';
    echo '</div>';

    echo '</div>';

    echo '<div class="description vms-due-rule-desc">Monthly: Month + (Day or EOM). Quarterly: Quarter month + (Day or EOM). Annual: Month + (Day or EOM). One-time: uses Date.</div>';

    echo '</td></tr>';

    $today_ymd = wp_date('Y-m-d', time(), wp_timezone());
    echo '<tr><th scope="row">Active range</th><td>';
    echo '<label>Start <input name="ob_start_date" type="date" value="' . esc_attr($today_ymd) . '" /></label>';
    echo ' &nbsp; ';
    echo '<label>End <input name="ob_end_date" type="date" /></label>';
    echo '<div class="description">Start date prevents implied overdue history. Leave End blank for ongoing obligations.</div>';
    echo '</td></tr>';

    echo '<tr><th scope="row"><label for="ob_remind_days">Remind (days)</label></th><td><input name="ob_remind_days" id="ob_remind_days" type="number" min="0" max="365" value="14" class="vms-input-narrow" /> <span class="description">Used for "due soon" status.</span></td></tr>';

    echo '</table>';
    echo '<p><button type="submit" class="button button-primary">Add Obligation</button></p>';
    echo '</form>';
  }

  echo '<h3>Existing Obligations</h3>';
  if (empty($obs)) {
    echo '<div class="vms-empty-state"><strong>No obligations yet.</strong><br>Create an obligation to surface due dates and reminders on the dashboard.</div>';
  } else {
    echo '<ul class="vms-due-list">';
    foreach ($obs as $oid => $ob) {
      if (!is_array($ob)) continue;
      $oid = sanitize_key((string) ($ob['id'] ?? $oid));
      $active = !empty($ob['is_active']);
      $title = (string) ($ob['title'] ?? '');
      $cadence = sanitize_key((string) ($ob['cadence'] ?? 'monthly'));
      $payee = (string) ($payeeNames[(string) ($ob['payee_id'] ?? '')] ?? '');
      if ($payee === '') $payee = '(no payee)';

      $rule = '';
      if ($cadence === 'one_time') {
        $rule = 'One-time: ' . (string) ($ob['due_date'] ?? '');
      } elseif ($cadence === 'annual') {
        $rule = 'Annual: ' . (int) ($ob['month'] ?? 1) . '/' . (int) ($ob['day'] ?? 1);
      } elseif ($cadence === 'quarterly') {
        $rule = 'Quarterly: day ' . (int) ($ob['day'] ?? 1) . ' (month ' . (int) ($ob['quarter_month'] ?? 1) . ' of quarter)';
      } else {
        $rule = 'Monthly: day ' . (int) ($ob['day'] ?? 1);
      }

      $next = isset($nextByOb[$oid]) ? (string) $nextByOb[$oid] : '';
      $nextLine = $next ? ('Next: ' . $next) : 'Next: (none in next 120 days)';

      $start = sanitize_text_field((string) ($ob['start_date'] ?? ''));
      $end = sanitize_text_field((string) ($ob['end_date'] ?? ''));
      $rangeLine = '';
      if ($start !== '') {
        $rangeLine = 'Active: ' . $start . ($end ? (' to ' . $end) : ' onward');
      }

      echo '<li class="vms-due-li" id="vms-ob-' . esc_attr($oid) . '">';
      echo '<strong>' . esc_html($title) . '</strong>';
      if (!$active) echo ' <span class="description">(archived)</span>';
      $desc = $payee . ' · ' . $rule;
      if ($rangeLine) {
        $desc .= ' · ' . $rangeLine;
      }
      $desc .= ' · ' . $nextLine;
      echo '<div class="description">' . esc_html($desc) . '</div>';

      $edit_ob_url = admin_url('admin.php?page=vms-due-dates&edit_ob=' . rawurlencode($oid) . '#vms-due-edit-obligation');
      echo '<div>';
      echo '<a class="button button-small" href="' . esc_url($edit_ob_url) . '">Edit</a> ';

      if ($active) {
        $aurl = wp_nonce_url(admin_url('admin-post.php?action=vms_due_archive_obligation&id=' . rawurlencode($oid)), 'bvmgr_due_archive_obligation');
        echo '<a class="button button-small" href="' . esc_url($aurl) . '">Archive</a>';
      }
      echo '</div>';

      echo '</li>';
    }
    echo '</ul>';
  }

  // ---------------- Due instance list ----------------
  echo '<hr />';
  echo '<h2 id="vms-due-instances">Obligation Due Instances</h2>';
  echo '<p class="description">Generated due dates with deterministic filters. Use actions to mark complete or reopen while preserving append-only history.</p>';

  $status_labels = [
    'open' => 'Open',
    'completed' => 'Completed',
    'overdue' => 'Overdue',
    'due_soon' => 'Due Soon',
    'upcoming' => 'Upcoming',
    'all' => 'All',
  ];
  $cadence_labels = [
    'all' => 'All',
    'monthly' => 'Monthly',
    'quarterly' => 'Quarterly',
    'annual' => 'Annual',
    'one_time' => 'One-time',
  ];

  echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '" class="vms-due-seed-form">';
  echo '<input type="hidden" name="page" value="vms-due-dates" />';
  echo '<label for="due_status"><strong>Status</strong></label> ';
  echo '<select id="due_status" name="due_status">';
  foreach ($status_labels as $k => $label) {
    echo '<option value="' . esc_attr($k) . '"' . selected($instance_filters['status'], $k, false) . '>' . esc_html($label) . '</option>';
  }
  echo '</select> &nbsp; ';

  echo '<label for="due_cadence"><strong>Cadence</strong></label> ';
  echo '<select id="due_cadence" name="due_cadence">';
  foreach ($cadence_labels as $k => $label) {
    echo '<option value="' . esc_attr($k) . '"' . selected($instance_filters['cadence'], $k, false) . '>' . esc_html($label) . '</option>';
  }
  echo '</select> &nbsp; ';

  echo '<label for="due_payee"><strong>Payee</strong></label> ';
  echo '<select id="due_payee" name="due_payee">';
  echo '<option value="all"' . selected($instance_filters['payee'], 'all', false) . '>All payees</option>';
  echo '<option value="none"' . selected($instance_filters['payee'], 'none', false) . '>(no payee)</option>';
  foreach ($payeeNames as $pid => $pname) {
    echo '<option value="' . esc_attr($pid) . '"' . selected($instance_filters['payee'], (string) $pid, false) . '>' . esc_html($pname) . '</option>';
  }
  echo '</select> &nbsp; ';

  echo '<label><input type="checkbox" name="due_include_archived" value="1"' . checked(!empty($instance_filters['include_archived']), true, false) . ' /> Include archived obligations</label> ';
  echo '<button type="submit" class="button button-secondary">Apply filters</button> ';
  echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=vms-due-dates#vms-due-instances')) . '">Reset</a>';
  echo '</form>';

  $count_open = (int) ($instance_counts['open'] ?? 0);
  $count_overdue = (int) ($instance_counts['overdue'] ?? 0);
  $count_due_soon = (int) ($instance_counts['due_soon'] ?? 0);
  $count_upcoming = (int) ($instance_counts['upcoming'] ?? 0);
  $count_completed = (int) ($instance_counts['completed'] ?? 0);
  $window_start = sanitize_text_field((string) ($instance_window['start_ymd'] ?? ''));
  $window_end = sanitize_text_field((string) ($instance_window['end_ymd'] ?? ''));
  $window_label = ($window_start !== '' && $window_end !== '') ? ($window_start . ' to ' . $window_end) : '';

  echo '<p class="description">';
  echo esc_html('Window: ' . ($window_label !== '' ? $window_label : '(n/a)') . ' · Open: ' . $count_open . ' · Overdue: ' . $count_overdue . ' · Due soon: ' . $count_due_soon . ' · Upcoming: ' . $count_upcoming . ' · Completed: ' . $count_completed);
  echo '</p>';

  if (empty($instance_items)) {
    echo '<p><em>No due instances match the current filters.</em></p>';
  } else {
    echo '<table class="widefat striped"><thead><tr>';
    echo '<th scope="col">Due Date</th>';
    echo '<th scope="col">Obligation</th>';
    echo '<th scope="col">Payee</th>';
    echo '<th scope="col">Status</th>';
    echo '<th scope="col">Action</th>';
    echo '</tr></thead><tbody>';

    foreach ($instance_items as $it) {
      if (!is_array($it)) continue;
      $oid = sanitize_key((string) ($it['obligation_id'] ?? ''));
      $due = sanitize_text_field((string) ($it['due_date'] ?? ''));
      $title = sanitize_text_field((string) ($it['title'] ?? ''));
      $payee = sanitize_text_field((string) ($it['payee_name'] ?? '(no payee)'));
      $cadence = sanitize_key((string) ($it['cadence'] ?? 'monthly'));
      $status = sanitize_key((string) ($it['status'] ?? 'upcoming'));
      $days_until = (int) ($it['days_until'] ?? 0);
      $needs_attention = !empty($it['needs_attention']);

      $timing = '';
      if ($status === 'completed') {
        $timing = 'Completed';
      } elseif ($days_until < 0) {
        $timing = abs($days_until) . ' day(s) overdue';
      } elseif ($days_until === 0) {
        $timing = 'Due today';
      } else {
        $timing = 'Due in ' . $days_until . ' day(s)';
      }

      echo '<tr>';
      echo '<td><strong>' . esc_html($due) . '</strong><div class="description">' . esc_html($timing) . '</div></td>';
      echo '<td><strong>' . esc_html($title) . '</strong><div class="description">' . esc_html($cadence_labels[$cadence] ?? ucfirst(str_replace('_', ' ', $cadence))) . '</div></td>';
      echo '<td>' . esc_html($payee);
      if ($needs_attention) {
        echo '<div class="description">Needs payee configuration</div>';
      }
      echo '</td>';
      echo '<td>' . esc_html($status_labels[$status] ?? ucfirst(str_replace('_', ' ', $status))) . '</td>';
      echo '<td>';
      if ($oid !== '' && $due !== '') {
        if ($status === 'completed') {
          echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" onsubmit="return window.confirm(\'Reopen this due date? This writes an append-only uncomplete entry.\');">';
          wp_nonce_field('bvmgr_due_uncomplete');
          echo '<input type="hidden" name="action" value="vms_due_uncomplete" />';
          echo '<input type="hidden" name="obligation_id" value="' . esc_attr($oid) . '" />';
          echo '<input type="hidden" name="due_date" value="' . esc_attr($due) . '" />';
          echo '<input type="hidden" name="notes" value="Reopened from obligations list" />';
          bvmgr_due_admin_render_instance_filter_hidden_inputs($instance_filters);
          echo '<button type="submit" class="button button-small">Reopen</button>';
          echo '</form>';
        } else {
          echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" onsubmit="return window.confirm(\'Mark this due date as complete? This writes an append-only completion entry.\');">';
          wp_nonce_field('bvmgr_due_complete');
          echo '<input type="hidden" name="action" value="vms_due_complete" />';
          echo '<input type="hidden" name="obligation_id" value="' . esc_attr($oid) . '" />';
          echo '<input type="hidden" name="due_date" value="' . esc_attr($due) . '" />';
          echo '<input type="hidden" name="notes" value="Completed from obligations list" />';
          bvmgr_due_admin_render_instance_filter_hidden_inputs($instance_filters);
          echo '<button type="submit" class="button button-small button-primary">Mark complete</button>';
          echo '</form>';
        }
      } else {
        echo '<span class="description">Unavailable</span>';
      }
      echo '</td>';
      echo '</tr>';
    }

    echo '</tbody></table>';

    if (!empty($instance_resp['truncated'])) {
      echo '<p class="description">Result list truncated to 500 rows. Narrow filters to view fewer items.</p>';
    }
  }

  // ---------------- Recent log ----------------
  echo '<hr />';
  echo '<h2 id="vms-due-log">Recent Completion Activity</h2>';
  if (empty($log)) {
    echo '<div class="vms-empty-state"><strong>No completion activity yet.</strong><br>Completion and reopen activity appears here once due dates are updated.</div>';
  } else {
    $log = array_reverse($log);
    $log = array_slice($log, 0, 20);
    echo '<ul class="vms-due-list">';
    foreach ($log as $row) {
      if (!is_array($row)) continue;
      $oid = sanitize_key((string) ($row['obligation_id'] ?? ''));
      $due = sanitize_text_field((string) ($row['due_date'] ?? ''));
      $action = sanitize_key((string) ($row['action'] ?? ''));
      if ($action !== 'uncomplete') {
        $action = 'complete';
      }
      $ts = (int) ($row['ts'] ?? 0);
      $when = $ts ? wp_date('Y-m-d H:i', $ts, wp_timezone()) : '';
      $note = sanitize_text_field((string) ($row['notes'] ?? ''));
      $proof_url = esc_url_raw((string) ($row['proof_url'] ?? ''));

      $title = isset($obs[$oid]['title']) ? (string) $obs[$oid]['title'] : $oid;
      $is_completed_now = false;
      if ($oid !== '' && $due !== '') {
        $is_completed_now = !empty($log_idx[$oid . '|' . $due]);
      }
      $activity_label = ($action === 'uncomplete') ? 'Reopened' : 'Completed';

      echo '<li class="vms-due-li">';
      echo '<strong>' . esc_html($title) . '</strong>';
      echo '<div class="description">' . esc_html($activity_label . ' due date ' . $due) . ($when ? (' · ' . esc_html($when)) : '') . '</div>';
      if ($note !== '') {
        echo '<div class="description">Note: ' . esc_html($note) . '</div>';
      }
      if ($proof_url !== '') {
        echo '<div class="description">Proof: <a href="' . esc_url($proof_url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($proof_url) . '</a></div>';
      }
      if ($action === 'complete' && $oid !== '' && $due !== '' && $is_completed_now) {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-due-seed-form" onsubmit="return window.confirm(\'Reopen this due date? This writes an append-only uncomplete entry.\');">';
        wp_nonce_field('bvmgr_due_uncomplete');
        echo '<input type="hidden" name="action" value="vms_due_uncomplete" />';
        echo '<input type="hidden" name="obligation_id" value="' . esc_attr($oid) . '" />';
        echo '<input type="hidden" name="due_date" value="' . esc_attr($due) . '" />';
        echo '<input type="hidden" name="notes" value="Reopened from Due Dates log" />';
        echo '<button type="submit" class="button button-small">Reopen Due Date</button>';
        echo '</form>';
      }
      echo '</li>';
    }
    echo '</ul>';
  }
}
