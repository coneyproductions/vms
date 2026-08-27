<?php
if (!defined('ABSPATH')) exit;

/**
 * Integrity: Calendar Links (review-first)
 *
 * Goal:
 * - Identify Event Plans that reference missing, trashed, or unpublished TEC events
 * - Identify Published or Ready Event Plans that are unlinked (no TEC event ID)
 * - Provide explicit, review-first actions that do not silently create or publish TEC events.
 */

add_action('admin_post_vms_integrity_calendar_links_action', 'vms_handle_integrity_calendar_links_action');
function vms_handle_integrity_calendar_links_action(): void
{
  if (!current_user_can('manage_options')) {
    wp_die('Insufficient permissions.');
  }

  check_admin_referer('vms_integrity_calendar_links_action');

  $action = vms_request_read_key($_POST, 'vms_action');
  $plan_ids = (isset($_POST['plan_ids']) && is_array($_POST['plan_ids']))
    ? array_values(array_filter(array_map('absint', (array) wp_unslash($_POST['plan_ids']))))
    : array();

  // Safety: bound bulk operations.
  if (count($plan_ids) > 500) {
    $plan_ids = array_slice($plan_ids, 0, 500);
  }

  $redirect_base = admin_url('admin.php?page=vms-integrity-calendar-links');

  if ($action === '' || empty($plan_ids)) {
    wp_safe_redirect(add_query_arg(array('vms_msg' => 'nothing_selected'), $redirect_base));
    exit;
  }

  // Step gate: require explicit confirm checkbox for any writes.
  $confirmed = vms_request_read_bool_flag($_POST, 'vms_confirm');
  if (!$confirmed) {
    wp_safe_redirect(add_query_arg(array('vms_msg' => 'confirm_required'), $redirect_base));
    exit;
  }

  $changed = 0;

  if ($action === 'restore_calendar_events') {
    // Restore referenced TEC events from Trash, but force them to Draft for review.
    foreach ($plan_ids as $pid) {
      $tec_event_id = (int) get_post_meta($pid, '_vms_tec_event_id', true);
      if ($tec_event_id <= 0) continue;

      $tp = get_post($tec_event_id);
      if (!$tp || $tp->post_type !== 'tribe_events') continue;
      if ($tp->post_status !== 'trash') continue;

      wp_untrash_post($tec_event_id);

      // Force Draft for review (do not auto-publish).
      $after = get_post($tec_event_id);
      if ($after && $after->post_status !== 'draft') {
        wp_update_post(array('ID' => $tec_event_id, 'post_status' => 'draft'));
      }

      $changed++;
    }
  }

  if ($action === 'clear_plan_calendar_links') {
    // Clear the TEC link on the selected Event Plans.
    foreach ($plan_ids as $pid) {
      $tec_event_id = (int) get_post_meta($pid, '_vms_tec_event_id', true);
      $tec_event_url = (string) get_post_meta($pid, '_vms_tec_event_url', true);

      if ($tec_event_id <= 0 && $tec_event_url === '') continue;

      update_post_meta($pid, '_vms_tec_event_id', 0);
      if ($tec_event_url !== '') update_post_meta($pid, '_vms_tec_event_url', '');
      $changed++;
    }
  }

  if ($action === 'relink_plan_calendar_links') {
    $new_tec_event_id = isset($_POST['new_tec_event_id']) ? absint($_POST['new_tec_event_id']) : 0;
    if ($new_tec_event_id > 0) {
      $tp = get_post($new_tec_event_id);
      if ($tp && $tp->post_type === 'tribe_events' && $tp->post_status !== 'trash') {
        $url = get_permalink($new_tec_event_id);
        foreach ($plan_ids as $pid) {
          update_post_meta($pid, '_vms_tec_event_id', $new_tec_event_id);
          if (!empty($url)) update_post_meta($pid, '_vms_tec_event_url', $url);
          $changed++;
        }
      }
    }
  }

  if ($action === 'clear_calendar_integrity_flag') {
    // Clear integrity flag only if it is a calendar-related flag.
    $k_issue = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'integrity_issue') : '_vms_integrity_issue';
    $k_ts    = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'integrity_ts') : '_vms_integrity_ts';

    $calendar_issues = array(
      'calendar_event_unlinked',
      'missing_calendar_event',
      'trashed_calendar_event',
      'calendar_event_unpublished',
    );

    foreach ($plan_ids as $pid) {
      $existing = (string) get_post_meta($pid, $k_issue, true);
      if (!in_array($existing, $calendar_issues, true)) continue;

      update_post_meta($pid, $k_issue, '');
      update_post_meta($pid, $k_ts, 0);
      $changed++;
    }
  }

  if ($action === 'suppress_calendar_unpublished_warning' || $action === 'unsuppress_calendar_unpublished_warning') {
    $k_sup = function_exists('bvmgr_meta_key')
      ? (bvmgr_meta_key('event_plan', 'calendar_unpublished_suppress') ?: '_vms_calendar_unpublished_suppress')
      : '_vms_calendar_unpublished_suppress';

    $k_issue = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'integrity_issue') : '_vms_integrity_issue';
    $k_ts    = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'integrity_ts') : '_vms_integrity_ts';

    foreach ($plan_ids as $pid) {
      if ($action === 'suppress_calendar_unpublished_warning') {
        update_post_meta($pid, $k_sup, '1');

        // If currently flagged as unpublished, clear the flag immediately.
        $existing = (string) get_post_meta($pid, $k_issue, true);
        if ($existing === 'calendar_event_unpublished') {
          update_post_meta($pid, $k_issue, '');
          update_post_meta($pid, $k_ts, 0);
        }
        $changed++;
      } else {
        delete_post_meta($pid, $k_sup);
        $changed++;
      }
    }
  }


  wp_safe_redirect(add_query_arg(array(
    'vms_msg' => 'done',
    'vms_changed' => $changed,
  ), $redirect_base));
  exit;
}


function vms_render_integrity_calendar_reconcile_notice(): void
{
  // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only integrity notice state only affects admin messaging.
  $msg = vms_request_read_key($_GET, 'vms_msg');
  // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only integrity notice state only affects admin messaging.
  $changed = vms_request_read_absint($_GET, 'vms_changed');

  if ($msg === 'confirm_required') {
    echo '<div class="notice notice-warning"><p><strong>Confirmation required.</strong> Check the confirmation box before running an action.</p></div>';
  } elseif ($msg === 'nothing_selected') {
    echo '<div class="notice notice-warning"><p><strong>Nothing selected.</strong> Select one or more Event Plans first.</p></div>';
  } elseif ($msg === 'done') {
    echo '<div class="notice notice-success"><p><strong>Action complete.</strong> Changed: ' . (int) $changed . '</p></div>';
  }
}

function vms_render_integrity_calendar_reconcile_page_intro(): void
{
  echo '<p class="description">Review Event Plans that reference missing, trashed, or unpublished TEC events, and Published or Ready Event Plans that are not linked to any TEC event. You can also suppress only the unpublished warning per Event Plan. This is intentionally review-first and does not create or publish TEC events.</p>';
}

function vms_render_integrity_calendar_reconcile_page(): void
{
  if (!current_user_can('manage_options')) {
    wp_die('Insufficient permissions.');
  }

  $event_plans_url = function_exists('vms_admin_ui_post_type_url')
    ? vms_admin_ui_post_type_url('vms_event_plan')
    : admin_url('edit.php?post_type=vms_event_plan');
  $settings_url = function_exists('vms_admin_ui_page_url')
    ? vms_admin_ui_page_url('vms-settings')
    : admin_url('admin.php?page=vms-settings');
  $actions_html = '<a class="button" href="' . esc_url($event_plans_url) . '">' . esc_html__('Event Plans', 'backstage-venue-manager') . '</a>';
  $actions_html .= '<a class="button button-primary" href="' . esc_url($settings_url) . '">' . esc_html__('Settings & Scan', 'backstage-venue-manager') . '</a>';

  if (function_exists('bvmgr_admin_ui_render_shell')) {
    bvmgr_admin_ui_render_shell(
      array(
        'title' => __('Integrity: Calendar Links', 'backstage-venue-manager'),
        'actions_html' => $actions_html,
        'content_class' => 'vms-admin-shell__content--integrity',
        'rich_notices_callback' => 'vms_render_integrity_calendar_reconcile_notice',
      ),
      'vms_render_integrity_calendar_reconcile_page_content'
    );
    return;
  }

  echo '<div class="wrap"><h1>' . esc_html__('Integrity: Calendar Links', 'backstage-venue-manager') . '</h1>';
  vms_render_integrity_calendar_reconcile_page_intro();
  vms_render_integrity_calendar_reconcile_notice();
  vms_render_integrity_calendar_reconcile_page_sections();
  echo '</div>';
}

function vms_render_integrity_calendar_reconcile_page_content(): void
{
  vms_render_integrity_calendar_reconcile_page_intro();
  vms_render_integrity_calendar_reconcile_page_sections();
}

function vms_render_integrity_calendar_reconcile_page_sections(): void
{
  // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only integrity filters only bound the diagnostic result size.
  $limit = vms_request_read_absint($_GET, 'limit');
  if ($limit < 1) $limit = 500;
  if ($limit > 5000) $limit = 5000;

  $issues = function_exists('vms_integrity_list_event_plans_with_calendar_issues')
    ? (array) vms_integrity_list_event_plans_with_calendar_issues($limit)
    : array();

  $trashed = isset($issues['trashed']) ? (array) $issues['trashed'] : array();
  $missing = isset($issues['missing']) ? (array) $issues['missing'] : array();
  $unpublished = isset($issues['unpublished']) ? (array) $issues['unpublished'] : array();
  $unlinked = isset($issues['unlinked']) ? (array) $issues['unlinked'] : array();

  $total = count($trashed) + count($missing) + count($unpublished) + count($unlinked);
  echo '<p><strong>Total affected Event Plans:</strong> ' . (int) $total . '</p>';

  $action_url = admin_url('admin-post.php');

  echo '<form method="post" action="' . esc_url($action_url) . '">';
  wp_nonce_field('vms_integrity_calendar_links_action');
  echo '<input type="hidden" name="action" value="vms_integrity_calendar_links_action" />';

  // Section: Trashed
  echo '<div class="vms-card-wide">';
  echo '<h2>Trashed TEC Event References</h2>';
  echo '<p class="description">These Event Plans reference a TEC event currently in the Trash. Recommended path: restore the TEC event for review, then decide whether to relink, replace, or clear the link.</p>';

  if (empty($trashed)) {
    echo '<p>None found.</p>';
  } else {
    echo '<table class="widefat striped">';
    echo '<thead><tr>';
    echo '<th class="check-column"><input type="checkbox" data-vms-select-all="plan_ids[]" aria-label="' . esc_attr__('Select all Event Plans', 'backstage-venue-manager') . '" /></th>';
    echo '<th>Event Plan</th>';
    echo '<th>TEC Event (in Trash)</th>';
    echo '<th>TEC ID</th>';
    echo '</tr></thead><tbody>';

    foreach ($trashed as $row) {
      $pid = (int) ($row['plan_id'] ?? 0);
      $eid = (int) ($row['tec_event_id'] ?? 0);
      if ($pid <= 0 || $eid <= 0) continue;

      $plan_link = get_edit_post_link($pid, '');
      $tec_link = get_edit_post_link($eid, '');
      $plan_title = (string) ($row['plan_title'] ?? ('#' . $pid));
      $tec_title = (string) ($row['tec_event_title'] ?? ('#' . $eid));

      echo '<tr>';
      echo '<th scope="row" class="check-column"><input type="checkbox" name="plan_ids[]" value="' . esc_attr($pid) . '" /></th>';
      echo '<td><a href="' . esc_url($plan_link) . '"><strong>' . esc_html($plan_title) . '</strong></a> <code>#' . (int) $pid . '</code></td>';
      echo '<td><a href="' . esc_url($tec_link) . '">' . esc_html($tec_title) . '</a></td>';
      echo '<td><code>' . (int) $eid . '</code></td>';
      echo '</tr>';
    }

    echo '</tbody></table>';
  }

  // Section: Missing
  echo '<hr class="vms-hr-spaced">';
  echo '<h2>Missing or Invalid TEC Event References</h2>';
  echo '<p class="description">These Event Plans reference a TEC event ID that no longer exists or is not a TEC event. Recommended path: relink to a valid TEC event ID, or clear the link.</p>';

  if (empty($missing)) {
    echo '<p>None found.</p>';
  } else {
    echo '<table class="widefat striped">';
    echo '<thead><tr>';
    echo '<th class="check-column"></th>';
    echo '<th>Event Plan</th>';
    echo '<th>Broken TEC ID</th>';
    echo '</tr></thead><tbody>';

    foreach ($missing as $row) {
      $pid = (int) ($row['plan_id'] ?? 0);
      $eid = (int) ($row['tec_event_id'] ?? 0);
      if ($pid <= 0 || $eid <= 0) continue;

      $plan_link = get_edit_post_link($pid, '');
      $plan_title = (string) ($row['plan_title'] ?? ('#' . $pid));

      echo '<tr>';
      echo '<th scope="row" class="check-column"><input type="checkbox" name="plan_ids[]" value="' . esc_attr($pid) . '" /></th>';
      echo '<td><a href="' . esc_url($plan_link) . '"><strong>' . esc_html($plan_title) . '</strong></a> <code>#' . (int) $pid . '</code></td>';
      echo '<td><code>' . (int) $eid . '</code></td>';
      echo '</tr>';
    }

    echo '</tbody></table>';
  }

  // Section: Unpublished
  echo '<hr class="vms-hr-spaced">';
  echo '<h2>Unpublished TEC Event References</h2>';
  echo '<p class="description">These Event Plans reference a TEC event that is not published (and not scheduled). You can publish the TEC event intentionally in TEC, relink, clear the link, or suppress only this warning per Event Plan.</p>';

  if (empty($unpublished)) {
    echo '<p>None found.</p>';
  } else {
    echo '<table class="widefat striped">';
    echo '<thead><tr>';
    echo '<th class="check-column"></th>';
    echo '<th>Event Plan</th>';
    echo '<th>TEC Event</th>';
    echo '<th>TEC Status</th>';
    echo '<th>TEC ID</th>';
    echo '</tr></thead><tbody>';

    foreach ($unpublished as $row) {
      $pid = (int) ($row['plan_id'] ?? 0);
      $eid = (int) ($row['tec_event_id'] ?? 0);
      if ($pid <= 0 || $eid <= 0) continue;

      $plan_link = get_edit_post_link($pid, '');
      $tec_link = get_edit_post_link($eid, '');
      $plan_title = (string) ($row['plan_title'] ?? ('#' . $pid));
      $tec_title = (string) ($row['tec_event_title'] ?? ('#' . $eid));
      $tec_status = (string) ($row['tec_event_status'] ?? '');

      echo '<tr>';
      echo '<th scope="row" class="check-column"><input type="checkbox" name="plan_ids[]" value="' . esc_attr($pid) . '" /></th>';
      echo '<td><a href="' . esc_url($plan_link) . '"><strong>' . esc_html($plan_title) . '</strong></a> <code>#' . (int) $pid . '</code></td>';
      echo '<td><a href="' . esc_url($tec_link) . '">' . esc_html($tec_title) . '</a></td>';
      echo '<td><code>' . esc_html($tec_status) . '</code></td>';
      echo '<td><code>' . (int) $eid . '</code></td>';
      echo '</tr>';
    }

    echo '</tbody></table>';
  }

  // Section: Suppressed Unpublished Warnings
  $k_sup = function_exists('bvmgr_meta_key')
    ? (bvmgr_meta_key('event_plan', 'calendar_unpublished_suppress') ?: '_vms_calendar_unpublished_suppress')
    : '_vms_calendar_unpublished_suppress';

  $suppressed_ids = get_posts(array(
    'post_type'      => 'vms_event_plan',
    'post_status'    => array('publish', 'draft'),
    'posts_per_page' => ($limit > 0 ? $limit : 500),
    'fields'         => 'ids',
    'no_found_rows'  => true,
    'orderby'        => 'ID',
    'order'          => 'DESC',
    // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- The diagnostic list is capped by the request limit clamped to 1–5000 and filters the exact plugin suppression marker.
    'meta_query'     => array(
      array(
        'key'     => $k_sup,
        'value'   => '1',
        'compare' => '=',
      ),
    ),
  ));

  echo '<hr class="vms-hr-spaced">';
  echo '<h2>Suppressed “Unpublished” Warnings</h2>';
  echo '<p class="description">These Event Plans have an operator override enabled to suppress only the “calendar event is not published” warning. This does not hide missing, trashed, or invalid calendar links.</p>';

  if (empty($suppressed_ids)) {
    echo '<p>None found.</p>';
  } else {
    echo '<table class="widefat striped">';
    echo '<thead><tr>';
    echo '<th class="check-column"></th>';
    echo '<th>Event Plan</th>';
    echo '<th>Internal Status</th>';
    echo '<th>TEC Status</th>';
    echo '<th>TEC ID</th>';
    echo '</tr></thead><tbody>';

    foreach ($suppressed_ids as $pid) {
      $pid = (int) $pid;
      if ($pid <= 0) continue;

      $plan_link = get_edit_post_link($pid, '');
      $plan_title = (string) get_the_title($pid);

      $internal_status = (string) get_post_meta($pid, '_vms_event_plan_status', true);
      if ($internal_status === '') $internal_status = 'draft';

      $eid = (int) get_post_meta($pid, '_vms_tec_event_id', true);
      $tec_status = '';
      if ($eid > 0) {
        $tp = get_post($eid);
        if ($tp) $tec_status = (string) $tp->post_status;
      }

      echo '<tr>';
      echo '<th scope="row" class="check-column"><input type="checkbox" name="plan_ids[]" value="' . esc_attr($pid) . '" /></th>';
      echo '<td><a href="' . esc_url($plan_link) . '"><strong>' . esc_html($plan_title ?: ('#' . $pid)) . '</strong></a> <code>#' . (int) $pid . '</code></td>';
      echo '<td><code>' . esc_html($internal_status) . '</code></td>';
      echo '<td><code>' . esc_html($tec_status) . '</code></td>';
      echo '<td><code>' . (int) $eid . '</code></td>';
      echo '</tr>';
    }

    echo '</tbody></table>';
  }

  // Section: Unlinked
  echo '<hr class="vms-hr-spaced">';
  echo '<h2>Unlinked Published or Ready Event Plans</h2>';
  echo '<p class="description">These Event Plans are marked Published or Ready but have no TEC event ID. Recommended path: relink to an existing TEC event ID, or leave as Draft until you intentionally create a TEC event.</p>';

  if (empty($unlinked)) {
    echo '<p>None found.</p>';
  } else {
    echo '<table class="widefat striped">';
    echo '<thead><tr>';
    echo '<th class="check-column"></th>';
    echo '<th>Event Plan</th>';
    echo '<th>Internal Status</th>';
    echo '</tr></thead><tbody>';

    foreach ($unlinked as $row) {
      $pid = (int) ($row['plan_id'] ?? 0);
      if ($pid <= 0) continue;

      $plan_link = get_edit_post_link($pid, '');
      $plan_title = (string) ($row['plan_title'] ?? ('#' . $pid));
      $internal_status = (string) ($row['internal_status'] ?? '');

      echo '<tr>';
      echo '<th scope="row" class="check-column"><input type="checkbox" name="plan_ids[]" value="' . esc_attr($pid) . '" /></th>';
      echo '<td><a href="' . esc_url($plan_link) . '"><strong>' . esc_html($plan_title) . '</strong></a> <code>#' . (int) $pid . '</code></td>';
      echo '<td><code>' . esc_html($internal_status) . '</code></td>';
      echo '</tr>';
    }

    echo '</tbody></table>';
  }

  echo '<hr class="vms-hr-spaced">';
  echo '<h2>Actions</h2>';

  echo '<p>';
  echo '<label><input type="checkbox" name="vms_confirm" value="1" /> <strong>I understand this will modify data.</strong></label>';
  echo '</p>';

  echo '<p><label for="vms_action"><strong>Action</strong></label><br />';
  echo '<select id="vms_action" name="vms_action">';
  echo '<option value="restore_calendar_events">Restore referenced TEC events (force Draft for review)</option>';
  echo '<option value="suppress_calendar_unpublished_warning">Suppress “unpublished calendar” warning (per Event Plan)</option>';
  echo '<option value="unsuppress_calendar_unpublished_warning">Remove suppression for “unpublished calendar” warning</option>';

  echo '<option value="relink_plan_calendar_links">Relink selected Event Plans to a TEC Event ID</option>';
  echo '<option value="clear_plan_calendar_links">Clear TEC link on selected Event Plans</option>';
  echo '<option value="clear_calendar_integrity_flag">Clear calendar integrity flag on selected Event Plans</option>';
  echo '</select></p>';

  echo '<p><label for="new_tec_event_id"><strong>Relink To (TEC Event ID)</strong></label><br />';
  echo '<input type="number" min="1" step="1" id="new_tec_event_id" name="new_tec_event_id" value="0" class="vms-integrity-tec-id-input" />';
  echo '<span class="description">Only used for “Relink selected Event Plans” action.</span>';
  echo '</p>';

  submit_button('Run action on selected Event Plans', 'primary', 'submit', false);
  echo '</div>';

  echo '</form>';

  echo '<p class="description">Tip: run the Integrity Scan (Backstage Venue Manager → Settings) first to force any impacted Event Plans back to Draft, then reconcile links here.</p>';
}
