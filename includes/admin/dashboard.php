<?php

/**
 * VMS Admin Dashboard Page (procedural)
 */
if (!defined('ABSPATH')) exit;

function vms_dashboard_query_arg(string $key): string
{
  // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only dashboard page detection only controls admin asset loading.
  if (!isset($_GET[$key])) {
    return '';
  }

  // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only dashboard page detection is unslashed here and sanitized by the caller.
  return (string) wp_unslash($_GET[$key]);
}

function vms_dashboard_register_menu()
{
  add_submenu_page(
    'vms',                 // parent slug (your VMS top-level menu slug)
    'Dashboard',
    'Dashboard',
    'manage_options',
    'vms-dashboard',
    'vms_render_dashboard_page'
  );
}
if (!function_exists('vms_render_dashboard_page')) {
  add_action('admin_menu', 'vms_dashboard_register_menu');
}

function vms_dashboard_enqueue_assets($hook)
{
  // Dashboard page is the VMS top-level (page=vms). Some installs may also have page=vms-dashboard.
  $page = sanitize_key(vms_dashboard_query_arg('page'));
  if ($page !== 'vms' && $page !== 'vms-dashboard') return;

  $plugin_file = function_exists('vms_plugin_main_file')
    ? vms_plugin_main_file()
    : dirname(__DIR__, 2) . '/backstage-venue-manager.php';
  $src         = plugin_dir_url($plugin_file) . 'assets/admin-dashboard.js';
  $ver         = function_exists('vms_asset_version') ? vms_asset_version() : (defined('BVMGR_VERSION') ? (string) BVMGR_VERSION : '');
  wp_enqueue_script(
    'vms-admin-dashboard',
    $src,
    ['jquery'],
    $ver,
    true
  );

  // Dashboard preference persistence (scope + venue). Kept as a small, isolated
  // script so persistence stays reliable even if the main dashboard script evolves.
  $prefs_src        = plugin_dir_url($plugin_file) . 'assets/admin-dashboard-prefs.js';
  wp_enqueue_script(
    'vms-admin-dashboard-prefs',
    $prefs_src,
    ['jquery', 'vms-admin-dashboard'],
    $ver,
    true
  );

  wp_localize_script('vms-admin-dashboard', 'VMS_DASH', [
    'restUrl' => esc_url_raw(rest_url('vms/v1/dashboard')),
    'dueCompleteUrl' => esc_url_raw(rest_url('vms/v1/due-dates/complete')),
    'dueAllUrl' => esc_url_raw(admin_url('admin.php?page=vms-due-dates')),
    'ajaxUrl' => esc_url_raw(admin_url('admin-ajax.php')),
    'dashPrefNonce' => wp_create_nonce('vms_set_dashboard_prefs'),
    'nonce'   => wp_create_nonce('wp_rest'),
  ]);
}

// Enqueue dashboard assets regardless of whether the dashboard page renderer is
// defined elsewhere (it is defined in includes/admin/menu.php).
add_action('admin_enqueue_scripts', 'vms_dashboard_enqueue_assets');

function vms_dashboard_render_today_week_block(): void
{
?>
  <div class="vms-dashboard-panels" data-vms-tour="dashboard-panels">

    <section id="vms-dashboard-financial" data-vms-tour="dashboard-financial">
      <h2>Financial Snapshot</h2>
      <div class="vms-panel-body" data-panel="financial">Loading…</div>
    </section>
    <?php if (function_exists('vms_goals_render_dashboard_panel')) : ?>
      <?php vms_goals_render_dashboard_panel(); ?>
    <?php endif; ?>
    <?php if (class_exists('BVMGR_Tours') && is_callable(array('BVMGR_Tours', 'render_dashboard_tile'))) : ?>
      <?php BVMGR_Tours::render_dashboard_tile(); ?>
    <?php endif; ?>

    <section id="vms-dashboard-today">
      <h2>Today</h2>
      <div class="vms-panel-body" data-panel="today">Loading…</div>
    </section>

    <section id="vms-dashboard-week">
      <h2>This Week</h2>
      <div class="vms-panel-body" data-panel="week">Loading…</div>
    </section>

    <section id="vms-dashboard-staffing" data-vms-tour="dashboard-staffing">
      <h2>Staffing Readiness</h2>
      <div class="vms-staffing-controls">
        <label class="vms-staffing-control">
          Next
          <select id="vms-staffing-n">
            <option value="5">5</option>
            <option value="10" selected>10</option>
            <option value="20">20</option>
          </select>
          events
        </label>
        <label class="vms-staffing-control">
          <input type="checkbox" id="vms-staffing-include-drafts" value="1">
          Include Draft/Ready
        </label>
      </div>
      <div class="vms-panel-body" data-panel="staffing">Loading…</div>
    </section>

    <section id="vms-dashboard-bills">
      <h2>Upcoming Bills</h2>
      <div class="vms-panel-body" data-panel="bills">Loading…</div>
    </section>

    <section id="vms-dashboard-due">
      <h2>Due Dates</h2>
      <div class="vms-panel-body" data-panel="due">Loading…</div>
    </section>

    </div>
<?php
}
