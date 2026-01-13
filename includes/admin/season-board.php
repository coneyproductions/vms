<?php

add_action('admin_menu', function () {
  add_submenu_page(
    'vms',
    __('Season Board', 'vms'),
    __('Season Board', 'vms'),
    'manage_options',
    'vms-season-board',
    'vms_render_season_board_page'
  );
});

add_action('admin_post_vms_create_event_plan', 'vms_handle_create_event_plan');

function vms_handle_create_event_plan()
{
  if (!current_user_can('manage_options')) {
    wp_die('Insufficient permissions.');
  }

  $date = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : '';
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    wp_die('Invalid date.');
  }

  // Nonce check
  $nonce = isset($_GET['_wpnonce']) ? (string) $_GET['_wpnonce'] : '';
  if (!wp_verify_nonce($nonce, 'vms_create_event_plan_' . $date)) {
    wp_die('Invalid nonce.');
  }

  // Optional: ensure the date is in active season dates (prevents random URL use)
  $venue_id = function_exists('vms_get_current_venue_id') ? (int) vms_get_current_venue_id() : 0;

  // If we have a venue context, enforce schedule window.
  // If not, we won’t block creation (safer than false negatives).
  if ($venue_id > 0 && function_exists('vms_get_active_dates_for_venue')) {
    $active_dates = vms_get_active_dates_for_venue($venue_id);

    if (!in_array($date, $active_dates, true)) {
      wp_die('That date is outside this venue’s configured schedule window.');
    }
  }

  // Optional venue scoping (only if your helper exists)
  $venue_id = 0;
  if (function_exists('vms_get_current_venue_id')) {
    $venue_id = (int) vms_get_current_venue_id();
  }

  // If a plan already exists for this date (+ venue if applicable), open it
  $meta_query = array(
    array(
      'key' => '_vms_event_date',
      'value' => $date,
    ),
  );

  if ($venue_id > 0) {
    $meta_query[] = array(
      'key' => '_vms_venue_id',
      'value' => $venue_id,
    );
  }

  $existing = get_posts(array(
    'post_type' => 'vms_event_plan',
    'posts_per_page' => 1,
    'post_status' => array('publish', 'draft', 'pending'),
    'meta_query' => $meta_query,
    'fields' => 'ids',
  ));

  if (!empty($existing)) {
    wp_safe_redirect(get_edit_post_link((int) $existing[0], ''));
    exit;
  }

  // Create the plan (draft)
  $title = 'Event Plan — ' . $date;

  $plan_id = wp_insert_post(array(
    'post_type' => 'vms_event_plan',
    'post_status' => 'draft',
    'post_title' => $title,
  ));

  if (!$plan_id || is_wp_error($plan_id)) {
    wp_die('Failed to create event plan.');
  }

  update_post_meta($plan_id, '_vms_event_date', $date);

  if ($venue_id > 0) {
    update_post_meta($plan_id, '_vms_venue_id', $venue_id);
  }

  // Optional: initialize your custom status meta if you rely on it elsewhere
  // update_post_meta($plan_id, '_vms_event_plan_status', 'draft');

  wp_safe_redirect(get_edit_post_link((int) $plan_id, ''));
  exit;
}

function vms_render_season_board_page()
{
  if (!current_user_can('manage_options')) {
    wp_die('Insufficient permissions.');
  }

  $venue_id = function_exists('vms_get_current_venue_id') ? (int) vms_get_current_venue_id() : 0;
  $active_dates = ($venue_id > 0 && function_exists('vms_get_active_dates_for_venue'))
    ? vms_get_active_dates_for_venue($venue_id)
    : array();


  echo '<div class="wrap">';
  echo '<h1>Season Board</h1>';
  vms_render_current_venue_selector();
  echo '<p>Broad season view of dates, booked vendors, and event plan status.</p>';

  if (empty($active_dates)) {
    echo '<p><em>No schedule dates available for this venue yet.</em></p>';
    echo '<p class="description">Tip: Set optional “Season Dates” on the venue, or we’ll default to the current year once configured.</p>';
    echo '</div>';
    return;
  }
  // Build a lookup: date => plan_id
  $venue_id = vms_get_current_venue_id(); // whatever helper you already have

  $meta_query = array(
    array(
      'key' => '_vms_event_date',
      'compare' => 'EXISTS',
    ),
  );

  if ($venue_id > 0) {
    $meta_query[] = array(
      'key' => '_vms_venue_id',
      'value' => $venue_id,
    );
  }

  $plans = get_posts(array(
    'post_type' => 'vms_event_plan',
    'posts_per_page' => -1,
    'post_status' => array('publish', 'draft', 'pending'),
    'meta_query' => $meta_query,
    'fields' => 'ids',
  ));

  $plan_by_date = array();
  foreach ($plans as $plan_id) {
    $d = get_post_meta($plan_id, '_vms_event_date', true);
    if ($d)
      $plan_by_date[$d] = (int) $plan_id;
  }

  // Month collapse controls
  echo '<p style="margin:10px 0 12px;">
  <button type="button" class="button" id="vms-expand-all">Expand all</button>
  <button type="button" class="button" id="vms-collapse-all" style="margin-left:6px;">Collapse all</button>
</p>';

  echo '<script>
document.addEventListener("DOMContentLoaded", function(){

  function setMonthCollapsed(monthKey, collapsed){
    // Hide/show ONLY the date rows for this month
    document.querySelectorAll("tr.vms-month-row[data-month=\\"" + monthKey + "\\"]")
      .forEach(function(tr){ tr.style.display = collapsed ? "none" : ""; });

    // Update hint on header
    var header = document.querySelector("tr.vms-month-header-row[data-month=\\"" + monthKey + "\\"]");
    if (header){
      var hint = header.querySelector(".vms-month-hint");
      if (hint){
        hint.textContent = collapsed ? "(collapsed — click Expand all to view dates)" : "";
      }
    }
  }

  function setAll(collapsed){
    var months = Array.from(document.querySelectorAll("tr.vms-month-header-row")).map(function(h){
      return h.dataset.month;
    });
    months.forEach(function(m){ setMonthCollapsed(m, collapsed); });
  }

  function currentMonthKey(){
    var now = new Date();
    return now.getFullYear() + "-" + String(now.getMonth()+1).padStart(2,"0");
  }

  function pickDefaultMonth(){
    var cur = currentMonthKey();
    if (document.querySelector("tr.vms-month-header-row[data-month=\\"" + cur + "\\"]")) return cur;

    var first = document.querySelector("tr.vms-month-header-row");
    return (first && first.dataset && first.dataset.month) ? first.dataset.month : null;
  }

  // Initial: collapse all date rows, BUT headers remain visible
  setAll(true);

  // Open one default month so page isn’t “empty”
  var def = pickDefaultMonth();
  if (def) setMonthCollapsed(def, false);

  var btnExpand = document.getElementById("vms-expand-all");
  var btnCollapse = document.getElementById("vms-collapse-all");

  if (btnExpand) btnExpand.addEventListener("click", function(){ setAll(false); });

  if (btnCollapse) btnCollapse.addEventListener("click", function(){
    setAll(true);
    var d = pickDefaultMonth();
    if (d) setMonthCollapsed(d, false);
  });

});
</script>';

  echo '<table class="widefat striped" style="max-width:1100px;">';
  echo '<thead><tr>';
  echo '<th style="width:160px;">Date</th>';
  echo '<th style="width:70px;">Day</th>';
  echo '<th style="width:140px;">Event Plan</th>';
  echo '<th>Band</th>';
  echo '<th style="width:170px;">Plan Status</th>';
  echo '<th>Food Truck</th>';
  echo '<th style="width:150px;">Publish</th>';
  echo '</tr></thead>';

  $current_month_key = '';

  foreach ($active_dates as $date_str) {
    $ts = strtotime($date_str);
    if (!$ts)
      continue;

    // Month key like "2026-03" (stable for JS + grouping)
    $month_key = date('Y-m', $ts);
    $month_label = date_i18n('F Y', $ts);

    // Open a new month group
    if ($month_key !== $current_month_key) {

      // Close previous tbody if needed
      if ($current_month_key !== '') {
        echo '</tbody>';
      }

      $current_month_key = $month_key;

      echo '<tbody class="vms-month-group" data-month="' . esc_attr($month_key) . '">';

      // Month header row (inside the tbody)
      // echo '<tr class="vms-month-header">';
      // echo '<td colspan="7" style="background:#f6f7f7;"><strong>' . esc_html($month_label) . '</strong></td>';
      // echo '</tr>';

      echo '<tr class="vms-month-header-row" data-month="' . esc_attr($month_key) . '">';
      echo '<td colspan="7" style="background:#f6f7f7;"><strong>' . esc_html($month_label) . '</strong>';
      echo '<span class="vms-month-hint" style="opacity:.7;font-weight:400;margin-left:8px;"></span>';
      echo '</td>';
      echo '</tr>';
    }

    $day_short = date_i18n('D', $ts);
    $nice_date = date_i18n('M j, Y', $ts);

    $plan_id = isset($plan_by_date[$date_str]) ? (int) $plan_by_date[$date_str] : 0;

    // IMPORTANT: reset per row to avoid "carry-over" bugs
    $band_name = '—';
    $food_name = '—';
    $plan_status = '—';
    $publish_info = '—';

    if ($plan_id) {
      $edit_link = get_edit_post_link($plan_id);
      $plan_status = get_post_meta($plan_id, '_vms_event_plan_status', true);
      if (!$plan_status)
        $plan_status = get_post_status($plan_id);

      $band_id = (int) get_post_meta($plan_id, '_vms_band_vendor_id', true);
      if ($band_id) {
        $band_name = '<a href="' . esc_url(get_edit_post_link($band_id)) . '">' . esc_html(get_the_title($band_id)) . '</a>';
      }

      $food_id = (int) get_post_meta($plan_id, '_vms_food_vendor_id', true);
      if ($food_id) {
        $food_name = '<a href="' . esc_url(get_edit_post_link($food_id)) . '">' . esc_html(get_the_title($food_id)) . '</a>';
      }

      // Publish info
      $publish_on = (string) get_post_meta($plan_id, '_vms_publish_on', true);
      $tec_id = (int) get_post_meta($plan_id, '_vms_tec_event_id', true);

      $publish_info_parts = array();
      if ($publish_on)
        $publish_info_parts[] = 'Publish on: ' . esc_html($publish_on);
      if ($tec_id)
        $publish_info_parts[] = 'TEC: <a href="' . esc_url(get_edit_post_link($tec_id)) . '">edit</a>';

      if (!empty($publish_info_parts)) {
        $publish_info = implode('<br>', $publish_info_parts);
      }

      $plan_cell = $edit_link ? '<a class="button button-small" href="' . esc_url($edit_link) . '">Open</a>' : 'Open';
    } else {
      $plan_cell = '<em>—</em>';
    }

    echo '<tr class="vms-month-row" data-month="' . esc_attr($month_key) . '">';
    echo '<td>' . esc_html($nice_date) . '</td>';
    echo '<td>' . esc_html($day_short) . '</td>';
    if ($plan_id) {
      echo '<td>' . $plan_cell . '</td>';
    } else {
      $new_link = add_query_arg(
        array(
          'post_type'        => 'vms_event_plan',
          'vms_event_date'   => $date_str, // YYYY-MM-DD from the loop
          '_vms_venue_id'    => vms_get_current_venue_id(), // optional but recommended
        ),
        admin_url('post-new.php')
      );
      // Create link: admin-post handler with date + nonce
      $new_link = add_query_arg(
        array(
          'action' => 'vms_create_event_plan',
          'date'   => $date_str,
          '_wpnonce' => wp_create_nonce('vms_create_event_plan_' . $date_str),
        ),
        admin_url('admin-post.php')
      );

      echo '<td><a class="button button-primary button-small" href="' . esc_url($new_link) . '">Create</a></td>';
    }
    echo '<td>' . $band_name . '</td>';
    echo '<td>' . esc_html(ucfirst((string) $plan_status)) . '</td>';
    echo '<td>' . $food_name . '</td>';
    echo '<td>' . $publish_info . '</td>';
    echo '</tr>';
  }

  // Close final tbody if opened
  if ($current_month_key !== '') {
    echo '</tbody>';
  }

  echo '</table>';

  echo '<script>
document.addEventListener("DOMContentLoaded", function(){

  function rowsFor(monthKey){
    return Array.from(document.querySelectorAll("tr.vms-month-row[data-month=\\"" + monthKey + "\\"]"));
  }

  function isCollapsed(monthKey){
    var rows = rowsFor(monthKey);
    if (!rows.length) return true;
    // if first row is hidden, treat as collapsed
    return rows[0].style.display === "none";
  }

  function setCollapsed(monthKey, collapsed){
    rowsFor(monthKey).forEach(function(tr){
      tr.style.display = collapsed ? "none" : "";
    });

    var header = document.querySelector("tr.vms-month-header-row[data-month=\\"" + monthKey + "\\"]");
    if (header){
      var badge = header.querySelector(".vms-month-toggle");
      if (badge){
        badge.textContent = collapsed ? "Expand ▾" : "Collapse ▴";
      }
    }
  }

  function setAll(collapsed){
    document.querySelectorAll("tr.vms-month-header-row").forEach(function(h){
      setCollapsed(h.dataset.month, collapsed);
    });
  }

  function currentMonthKey(){
    var now = new Date();
    return now.getFullYear() + "-" + String(now.getMonth()+1).padStart(2,"0");
  }

  function pickDefaultMonth(){
    var cur = currentMonthKey();
    if (document.querySelector("tr.vms-month-header-row[data-month=\\"" + cur + "\\"]")) return cur;
    var first = document.querySelector("tr.vms-month-header-row");
    return first ? first.dataset.month : null;
  }

  // Init: collapse all, open default month, and set button labels
  setAll(true);
  var def = pickDefaultMonth();
  if (def) setCollapsed(def, false);

  // Month header click toggles that month
  document.querySelectorAll("tr.vms-month-header-row").forEach(function(header){
    header.addEventListener("click", function(){
      var m = header.dataset.month;
      setCollapsed(m, !isCollapsed(m));
    });
  });

  // Expand/Collapse all buttons if present
  var btnExpand = document.getElementById("vms-expand-all");
  var btnCollapse = document.getElementById("vms-collapse-all");

  if (btnExpand) btnExpand.addEventListener("click", function(){ setAll(false); });
  if (btnCollapse) btnCollapse.addEventListener("click", function(){
    setAll(true);
    var d = pickDefaultMonth();
    if (d) setCollapsed(d, false);
  });

});
</script>';

  echo '<style>
tr.vms-month-header-row td { user-select:none; }
tr.vms-month-header-row:hover td { background:#eef0f2 !important; }
</style>';
}
