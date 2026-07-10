<?php
if (!defined('ABSPATH')) exit;

/**
 * Integrity: Venue Links (review-first)
 *
 * Goal:
 * - Identify Event Plans that reference Venues in the Trash (or missing)
 * - Provide an explicit, review-first workflow to reconcile those Event Plans
 *   without silently auto-publishing anything.
 */

add_action('admin_post_vms_integrity_venue_links_action', 'vms_handle_integrity_venue_links_action');
function vms_handle_integrity_venue_links_action(): void
{
  if (!current_user_can('manage_options')) {
    wp_die('Insufficient permissions.');
  }

  check_admin_referer('vms_integrity_venue_links_action');

  $action = isset($_POST['vms_action']) ? sanitize_key((string) $_POST['vms_action']) : '';
  $plan_ids = isset($_POST['plan_ids']) ? (array) $_POST['plan_ids'] : array();
  $plan_ids = array_values(array_filter(array_map('absint', $plan_ids)));

  // Safety: bound bulk operations.
  if (count($plan_ids) > 500) {
    $plan_ids = array_slice($plan_ids, 0, 500);
  }

  $redirect_base = admin_url('admin.php?page=vms-integrity-venue-links');

  if ($action === '' || empty($plan_ids)) {
    wp_safe_redirect(add_query_arg(array('vms_msg' => 'nothing_selected'), $redirect_base));
    exit;
  }

  // Step gate: require explicit confirm checkbox for any writes.
  $confirmed = !empty($_POST['vms_confirm']);
  if (!$confirmed) {
    wp_safe_redirect(add_query_arg(array('vms_msg' => 'confirm_required'), $redirect_base));
    exit;
  }

  $changed = 0;

  if ($action === 'restore_venues') {
    // Restore referenced venues from Trash, but force them to Draft for review.
    foreach ($plan_ids as $pid) {
      $venue_id = (int) get_post_meta($pid, '_vms_venue_id', true);
      if ($venue_id <= 0) continue;

      $vp = get_post($venue_id);
      if (!$vp || $vp->post_type !== 'vms_venue') continue;
      if ($vp->post_status !== 'trash') continue;

      wp_untrash_post($venue_id);
      $after = get_post($venue_id);
      if ($after && $after->post_status === 'publish') {
        wp_update_post(array('ID' => $venue_id, 'post_status' => 'draft'));
      }
      $changed++;
    }
  }

  if ($action === 'clear_plan_venues') {
    // Clear the venue reference on the selected Event Plans.
    foreach ($plan_ids as $pid) {
      $venue_id = (int) get_post_meta($pid, '_vms_venue_id', true);
      if ($venue_id <= 0) continue;
      update_post_meta($pid, '_vms_venue_id', 0);
      $changed++;
    }
  }

  if ($action === 'reassign_plan_venues') {
    $new_venue_id = isset($_POST['new_venue_id']) ? absint($_POST['new_venue_id']) : 0;
    if ($new_venue_id > 0) {
      $new_venue = get_post($new_venue_id);
      if ($new_venue && $new_venue->post_type === 'vms_venue' && $new_venue->post_status !== 'trash') {
        foreach ($plan_ids as $pid) {
          $old = (int) get_post_meta($pid, '_vms_venue_id', true);
          if ($old <= 0) continue;
          update_post_meta($pid, '_vms_venue_id', $new_venue_id);
          $changed++;
        }
      }
    }
  }

  wp_safe_redirect(add_query_arg(array(
    'vms_msg' => 'done',
    'vms_changed' => $changed,
  ), $redirect_base));
  exit;
}


function vms_render_integrity_venue_reconcile_page(): void
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

  if (function_exists('vms_admin_ui_render_shell')) {
    vms_admin_ui_render_shell(
      array(
        'title' => __('Integrity: Venue Links', 'backstage-venue-manager'),
        'actions_html' => $actions_html,
        'content_class' => 'vms-admin-shell__content--integrity',
      ),
      'vms_render_integrity_venue_reconcile_page_content'
    );
    return;
  }

  echo '<div class="wrap"><h1>' . esc_html__('Integrity: Venue Links', 'backstage-venue-manager') . '</h1>';
  vms_render_integrity_venue_reconcile_page_content();
  echo '</div>';
}

function vms_render_integrity_venue_reconcile_page_content(): void
{
  $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 500;
  if ($limit < 1) $limit = 500;
  if ($limit > 5000) $limit = 5000;

  $issues = function_exists('vms_integrity_list_event_plans_with_venue_issues')
    ? (array) vms_integrity_list_event_plans_with_venue_issues($limit)
    : array();

  $msg = isset($_GET['vms_msg']) ? sanitize_key((string) $_GET['vms_msg']) : '';
  $changed = isset($_GET['vms_changed']) ? (int) $_GET['vms_changed'] : 0;

  echo '<p class="description">Review Event Plans that reference Venues in the Trash, missing Venues, or Venues that are not published. This is intentionally review-first and does not auto-publish restored Venues.</p>';

  if ($msg === 'confirm_required') {
    echo '<div class="notice notice-warning"><p><strong>Confirmation required.</strong> Check the confirmation box before running an action.</p></div>';
  } elseif ($msg === 'nothing_selected') {
    echo '<div class="notice notice-warning"><p><strong>Nothing selected.</strong> Select one or more Event Plans first.</p></div>';
  } elseif ($msg === 'done') {
    echo '<div class="notice notice-success"><p><strong>Action complete.</strong> Changed: ' . (int) $changed . '</p></div>';
  }

  $trashed = isset($issues['trashed']) ? (array) $issues['trashed'] : array();
  $missing = isset($issues['missing']) ? (array) $issues['missing'] : array();
  $unpublished = isset($issues['unpublished']) ? (array) $issues['unpublished'] : array();

  $total = count($trashed) + count($missing) + count($unpublished);
  echo '<p><strong>Total affected Event Plans:</strong> ' . (int) $total . '</p>';

  $venues = get_posts(array(
    'post_type' => 'vms_venue',
    'posts_per_page' => -1,
    'orderby' => 'title',
    'order' => 'ASC',
    'fields' => 'ids',
    'post_status' => array('publish', 'draft', 'pending', 'private'),
  ));

  $action_url = admin_url('admin-post.php');

  echo '<form method="post" action="' . esc_url($action_url) . '">';
  wp_nonce_field('vms_integrity_venue_links_action');
  echo '<input type="hidden" name="action" value="vms_integrity_venue_links_action" />';

  echo '<div class="vms-card-wide">';
  echo '<h2>Trashed Venue References</h2>';
  echo '<p class="description">These Event Plans reference a Venue currently in the Trash. Recommended path: restore the Venue for review, then reassess whether each Event Plan should remain linked.</p>';

  if (empty($trashed)) {
    echo '<p>None found.</p>';
  } else {
    echo '<table class="widefat striped">';
    echo '<thead><tr>';
    echo '<th class="check-column"><input type="checkbox" data-vms-select-all="plan_ids[]" aria-label="' . esc_attr__('Select all Event Plans', 'backstage-venue-manager') . '" /></th>';
    echo '<th>Event Plan</th>';
    echo '<th>Venue (in Trash)</th>';
    echo '<th>Venue ID</th>';
    echo '</tr></thead><tbody>';

    foreach ($trashed as $row) {
      $pid = (int) ($row['plan_id'] ?? 0);
      $vid = (int) ($row['venue_id'] ?? 0);
      if ($pid <= 0 || $vid <= 0) continue;

      $plan_link = get_edit_post_link($pid, '');
      $venue_link = get_edit_post_link($vid, '');
      $plan_title = (string) ($row['plan_title'] ?? ('#' . $pid));
      $venue_title = (string) ($row['venue_title'] ?? ('#' . $vid));

      echo '<tr>';
      echo '<th scope="row" class="check-column"><input type="checkbox" name="plan_ids[]" value="' . esc_attr($pid) . '" /></th>';
      echo '<td><a href="' . esc_url($plan_link) . '"><strong>' . esc_html($plan_title) . '</strong></a> <code>#' . (int) $pid . '</code></td>';
      echo '<td><a href="' . esc_url($venue_link) . '">' . esc_html($venue_title) . '</a></td>';
      echo '<td><code>' . (int) $vid . '</code></td>';
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
  echo '<option value="restore_venues">Restore referenced Venues (force Draft for review)</option>';
  echo '<option value="reassign_plan_venues">Reassign selected Event Plans to a different Venue</option>';
  echo '<option value="clear_plan_venues">Clear Venue on selected Event Plans (set to none)</option>';
  echo '</select></p>';

  echo '<p><label for="new_venue_id"><strong>Reassign To</strong></label><br />';
  echo '<select id="new_venue_id" name="new_venue_id">';
  echo '<option value="0">— Select a venue —</option>';
  foreach ($venues as $vid) {
    echo '<option value="' . esc_attr((int) $vid) . '">' . esc_html(get_the_title($vid)) . ' (ID ' . (int) $vid . ')</option>';
  }
  echo '</select>'; 
  echo '<span class="description">Only used for “Reassign selected Event Plans” action.</span>';
  echo '</p>';

  submit_button('Run action on selected Event Plans', 'primary', 'submit', false);
  echo '</div>';

  echo '</form>';

  echo '<p class="description">Tip: run the Integrity Scan (VMS → Settings) first to force any impacted Event Plans back to Draft, then reconcile links here.</p>';
}
