<?php
if (!defined('ABSPATH')) exit;

if (!function_exists('vms_admin_render_season_dates_page')) {
  function vms_admin_render_season_dates_page(): void
  {
    if (function_exists('vms_render_season_dates_page')) {
      vms_render_season_dates_page();
      return;
    }

    echo '<div class="wrap">';
    echo '<h1>Season Dates</h1>';
    echo '<p>Season Dates page renderer is missing. Expected function <code>vms_render_season_dates_page()</code>.</p>';
    echo '</div>';

    if (defined('WP_DEBUG') && WP_DEBUG) {
      error_log('VMS: Season Dates renderer missing. Expected vms_render_season_dates_page().');
    }
  }
}

if (!function_exists('vms_render_dashboard_phase2_placeholder')) {
  function vms_render_dashboard_phase2_placeholder(string $title): void
  {
    echo '<div class="wrap">';
    echo '<h1>' . esc_html($title) . '</h1>';
    echo '<p>This dashboard view is reserved for Phase 2.</p>';
    echo '</div>';
  }
}

if (!function_exists('vms_render_dashboard_operations_page')) {
  function vms_render_dashboard_operations_page(): void
  {
    vms_render_dashboard_phase2_placeholder('Dashboard: Operations');
  }
}

if (!function_exists('vms_render_dashboard_finance_page')) {
  function vms_render_dashboard_finance_page(): void
  {
    vms_render_dashboard_phase2_placeholder('Dashboard: Finance');
  }
}

if (!function_exists('vms_render_dashboard_health_page')) {
  function vms_render_dashboard_health_page(): void
  {
    if (function_exists('vms_render_resource_fingerprint_admin_screen')) {
      vms_render_resource_fingerprint_admin_screen();
      return;
    }

    vms_render_dashboard_phase2_placeholder('Dashboard: Onboarding & Health');
  }
}

if (!function_exists('vms_admin_menu_allowed_help_html')) {
  /**
   * @return array<string,array<string,bool>>
   */
  function vms_admin_menu_allowed_help_html(): array
  {
    return array(
      'button' => array(
        'type' => true,
        'class' => true,
        'style' => true,
        'data-vms-tour' => true,
        'data-vms-tour-start' => true,
        'data-vms-help-action' => true,
        'data-vms-help-open' => true,
      ),
      'details' => array(
        'class' => true,
        'style' => true,
        'data-vms-tour' => true,
      ),
      'div' => array(
        'class' => true,
        'style' => true,
      ),
      'summary' => array(
        'class' => true,
        'style' => true,
      ),
    );
  }
}


add_action('admin_menu', function () {

  /* 
  Comp Packages
  Venues
  Vendors
  Ratings
  Staff
  Schedule
  Season Dates
  Event Plans
  Settings
  Docs
  Data Tools
  */
  $parent_slug = 'vms-dashboard';
  $capability  = 'manage_options';

  // Top-level menu → Dashboard
  add_menu_page(
    __('Vendor Management System', 'backstage-venue-manager'),
    (function_exists('vms_staff_certifications_pending_count') && function_exists('vms_staff_certifications_admin_badge_markup') ? 'VMS' . vms_staff_certifications_admin_badge_markup(vms_staff_certifications_pending_count()) : 'VMS'),
    $capability,
    $parent_slug,
    'vms_render_dashboard_page',
    'dashicons-calendar-alt',
    26
  );

  // Dashboard (must match parent slug)
  add_submenu_page(
    $parent_slug,
    __('Dashboard', 'backstage-venue-manager'),
    __('Dashboard', 'backstage-venue-manager'),
    $capability,
    $parent_slug,
    'vms_render_dashboard_page'
  );

  add_submenu_page(
    $parent_slug,
    __('Dashboard: Operations', 'backstage-venue-manager'),
    __('Dashboard: Operations', 'backstage-venue-manager'),
    $capability,
    'vms-dashboard-operations',
    'vms_render_dashboard_operations_page'
  );

  add_submenu_page(
    $parent_slug,
    __('Dashboard: Finance', 'backstage-venue-manager'),
    __('Dashboard: Finance', 'backstage-venue-manager'),
    $capability,
    'vms-dashboard-finance',
    'vms_render_dashboard_finance_page'
  );

  add_submenu_page(
    $parent_slug,
    __('Dashboard: Onboarding & Health', 'backstage-venue-manager'),
    __('Dashboard: Onboarding & Health', 'backstage-venue-manager'),
    $capability,
    'vms-dashboard-health',
    'vms_render_dashboard_health_page'
  );

  // Budget Forecast Calculator (decision-support)
  if (function_exists('vms_render_budget_calculator_page')) {
    add_submenu_page(
      $parent_slug,
      __('Budget Calculator', 'backstage-venue-manager'),
      __('Budget Calculator', 'backstage-venue-manager'),
      $capability,
      'vms-budget-calculator',
      'vms_render_budget_calculator_page'
    );
  }


  // Core custom admin pages
  if (function_exists('vms_render_schedule_page')) {
    add_submenu_page(
      $parent_slug,
      __('Schedule', 'backstage-venue-manager'),
      __('Schedule', 'backstage-venue-manager'),
      $capability,
      'vms-schedule',
      'vms_render_schedule_page'
    );
  }

  add_submenu_page(
    $parent_slug,
    __('Event Plans', 'backstage-venue-manager'),
    __('Event Plans', 'backstage-venue-manager'),
    $capability,
    'edit.php?post_type=vms_event_plan'
  );

  add_submenu_page(
    $parent_slug,
    __('Vendor Command Center', 'backstage-venue-manager'),
    __('Vendor Command Center', 'backstage-venue-manager'),
    $capability,
    'vms-vendor-command-center',
    'vms_render_vendor_command_center_page'
  );

  add_submenu_page(
    $parent_slug,
    __('Vendor Availability', 'backstage-venue-manager'),
    __('Vendor Availability', 'backstage-venue-manager'),
    $capability,
    'vms-vendor-availability',
    'vms_render_vendor_availability_page'
  );

  // CPT Lists (core objects)
  add_submenu_page(
    $parent_slug,
    __('Vendors', 'backstage-venue-manager'),
    __('Vendors', 'backstage-venue-manager'),
    $capability,
    'edit.php?post_type=vms_vendor'
  );

  add_submenu_page(
    $parent_slug,
    __('Comp Packages', 'backstage-venue-manager'),
    __('Comp Packages', 'backstage-venue-manager'),
    $capability,
    'edit.php?post_type=vms_comp_package'
  );

  // Optional CPTs (only if they exist)
  if (post_type_exists('vms_rating')) {
    add_submenu_page(
      $parent_slug,
      __('Ratings', 'backstage-venue-manager'),
      __('Ratings', 'backstage-venue-manager'),
      $capability,
      'edit.php?post_type=vms_rating'
    );
  }

  add_submenu_page(
    $parent_slug,
    __('Staff', 'backstage-venue-manager'),
    __('Staff', 'backstage-venue-manager'),
    $capability,
    'edit.php?post_type=vms_staff'
  );

  if (function_exists('vms_render_staff_certifications_admin_page')) {
    add_submenu_page(
      $parent_slug,
      __('Staff Certifications', 'backstage-venue-manager'),
      function_exists('vms_staff_certifications_admin_menu_label') ? vms_staff_certifications_admin_menu_label(__('Staff Certifications', 'backstage-venue-manager')) : __('Staff Certifications', 'backstage-venue-manager'),
      $capability,
      'vms-staff-certifications',
      'vms_render_staff_certifications_admin_page'
    );
  }

  add_submenu_page(
    $parent_slug,
    __('Venues', 'backstage-venue-manager'),
    __('Venues', 'backstage-venue-manager'),
    $capability,
    'edit.php?post_type=vms_venue'
  );


  if ((defined('VMS_VENDOR_APP_CPT') && post_type_exists(VMS_VENDOR_APP_CPT)) || post_type_exists('vms_vendor_application')) {
    add_submenu_page(
      $parent_slug,
      __('Vendor Applications', 'backstage-venue-manager'),
      __('Vendor Applications', 'backstage-venue-manager'),
      $capability,
      defined('VMS_VENDOR_APP_CPT') ? ('edit.php?post_type=' . VMS_VENDOR_APP_CPT) : 'edit.php?post_type=vms_vendor_app'
    );
  }

  add_submenu_page(
    $parent_slug,
    __('Season Dates', 'backstage-venue-manager'),
    __('Season Dates', 'backstage-venue-manager'),
    $capability,
    'vms-season-dates',
    'vms_admin_render_season_dates_page'
  );

  add_submenu_page(
    $parent_slug,
    __('Holidays', 'backstage-venue-manager'),
    __('Holidays', 'backstage-venue-manager'),
    $capability,
    'vms-holidays',
    'vms_admin_holidays_page'
  );

  if (function_exists('vms_render_settings_page')) {
    add_submenu_page(
      $parent_slug,
      __('Settings', 'backstage-venue-manager'),
      __('Settings', 'backstage-venue-manager'),
      $capability,
      'vms-settings',
      'vms_render_settings_page'
    );
  }

  if (function_exists('vms_status_notice_render_admin_page')) {
    add_submenu_page(
      $parent_slug,
      __('Status Notices', 'backstage-venue-manager'),
      __('Status Notices', 'backstage-venue-manager'),
      $capability,
      'vms-status-notices',
      'vms_status_notice_render_admin_page'
    );
  }

  if (function_exists('vms_pass_claims_render_admin_page')) {
    add_submenu_page(
      $parent_slug,
      __('Guest Passes', 'backstage-venue-manager'),
      __('Guest Passes', 'backstage-venue-manager'),
      $capability,
      'vms-passes',
      'vms_pass_claims_render_admin_page'
    );
  }

  // Integrity: Venue link reconciliation (review-first)
  if (function_exists('vms_render_integrity_venue_reconcile_page')) {
    add_submenu_page(
      $parent_slug,
      __('Integrity: Venue Links', 'backstage-venue-manager'),
      __('Integrity: Venue Links', 'backstage-venue-manager'),
      $capability,
      'vms-integrity-venue-links',
      'vms_render_integrity_venue_reconcile_page'
    );
  }
  

  // Integrity: Calendar link reconciliation (review-first)
  if (function_exists('vms_render_integrity_calendar_reconcile_page')) {
    add_submenu_page(
      $parent_slug,
      __('Integrity: Calendar Links', 'backstage-venue-manager'),
      __('Integrity: Calendar Links', 'backstage-venue-manager'),
      $capability,
      'vms-integrity-calendar-links',
      'vms_render_integrity_calendar_reconcile_page'
    );
  }
add_submenu_page(
    $parent_slug, // Use your existing VMS parent slug variable/value here (same one used by other VMS submenus).
    __('Continuity Binder', 'backstage-venue-manager'),
    __('Continuity Binder', 'backstage-venue-manager'),
    'manage_options',
    'vms-continuity-binder',
    'vms_render_continuity_binder_page'
);

  // Docs (optional custom page)
  if (function_exists('vms_render_docs_admin_page')) {
    add_submenu_page(
      $parent_slug,
      __('Docs', 'backstage-venue-manager'),
      __('Docs', 'backstage-venue-manager'),
      $capability,
      'vms-docs',
      'vms_render_docs_admin_page'
    );
  }
   
  add_submenu_page(
  'vms-dashboard',
  __('Reference: Keys + Identifiers', 'backstage-venue-manager'),
  __('Reference: Keys + Identifiers', 'backstage-venue-manager'),
  'manage_options',
  'vms-reference-keys-map',
  'vms_admin_reference_keys_map_page'
);

}, 5);



add_action('admin_head', function () {
  if (!is_admin()) return;

  $page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';
  if ($page === '') return;

  $known = [
    'vms-dashboard'         => 'vms-dashboard',
    'vms-dashboard-operations' => 'vms-dashboard-operations',
    'vms-dashboard-finance' => 'vms-dashboard-finance',
    'vms-dashboard-health' => 'vms-dashboard-health',
    'vms-budget-calculator' => 'vms-budget-calculator',
    'vms-event-profitability' => 'vms-event-profitability',
    'vms-vendor-command-center' => 'vms-vendor-command-center',
    'vms-vendor-availability' => 'vms-vendor-availability',
    'vms-schedule'          => 'vms-schedule',
    'vms-event-command-center' => 'vms-event-command-center',
    'vms-season-dates'      => 'vms-season-dates',
    'vms-settings'          => 'vms-settings',
    'vms-guided-tours'     => 'vms-guided-tours',
    'vms-status-notices'    => 'vms-status-notices',
    'vms-passes'           => 'vms-passes',
    'vms-due-dates'        => 'vms-due-dates',
    'vms-tour-maintenance' => 'vms-tour-maintenance',
    'vms-social-sharing'   => 'vms-social-sharing',
    'vms-marketing-social' => 'vms-marketing-social',
    'vms-email-followups' => 'vms-email-followups',
    'vms-tasks'            => 'vms-tasks',
    'vms-task-templates'   => 'vms-task-templates',
    'vms-checklist-templates' => 'vms-checklist-templates',
    'vms-task-settings'    => 'vms-task-settings',
    'vms-my-tasks'         => 'vms-my-tasks',
    'vms-staffing-templates' => 'vms-staffing-templates',
    'vms-staffing-rollups' => 'vms-staffing-rollups',
    'vms-staff-certifications' => 'vms-staff-certifications',
	    'vms-ops-console-teams' => 'vms-teams',
	    'vms-ops-console-presets' => 'vms-alert-presets',
	    'vms-ops-console-id-scans' => 'vms-ops-console',
	    'vms-teams'            => 'vms-teams',
	    'vms-alert-presets'    => 'vms-alert-presets',
    'vms-ops-console-hub'  => 'vms-ops-console-hub',
    'vms-ticket-integrity' => 'vms-ticket-integrity',
    'vms-square-sync-protection' => 'vms-square-sync-protection',
    'vms-event-feedback' => 'vms-admin-pages',
    'vms-approvals'        => 'vms-approvals',
    'vms-verifications'    => 'vms-verifications',
  ];

  if (!isset($known[$page])) return;

  global $parent_file, $submenu_file;
  $parent_file  = 'vms-dashboard';
  $submenu_file = $known[$page];
});
 
function vms_render_dashboard_page(): void
{
  if (function_exists('vms_admin_ui_render_shell')) {
    vms_admin_ui_render_shell(
      array(
        'title' => __('Dashboard', 'backstage-venue-manager'),
        'subtitle' => __('Operational overview and quick launch actions for planning, staffing, and finance.', 'backstage-venue-manager'),
        'shell_id' => 'vms-dashboard-wrap',
      ),
      'vms_render_dashboard_page_content'
    );
    return;
  }

  echo '<div class="wrap" id="vms-dashboard-wrap">';
  echo '<h1>' . esc_html__('Dashboard', 'backstage-venue-manager') . '</h1>';
  vms_render_dashboard_page_content();
  echo '</div>';
}

function vms_render_dashboard_page_content(): void
{
  echo '<p class="vms-dashboard-welcome" data-vms-tour="dashboard_welcome">' . esc_html__('Welcome to the Venue Management System dashboard—tune the filters below before reviewing cards and tour health signals.', 'backstage-venue-manager') . '</p>';

  $user_id = (int) get_current_user_id();
  $has_inc_drafts = (function_exists('vms_user_pref_has_include_drafts'))
    ? (bool) vms_user_pref_has_include_drafts($user_id)
    : (bool) metadata_exists('user', $user_id, '_vms_include_drafts');

  // Keep parity with Schedule + Event Plans list:
  // default Include Draft/Ready ON when no per-user preference exists yet.
  $inc_drafts = $has_inc_drafts
    ? ((function_exists('vms_user_pref_get_include_drafts'))
      ? (bool) vms_user_pref_get_include_drafts($user_id)
      : false)
    : true;

  // Keep parity with Schedule + Event Plans list:
  // default Include canceled ON when no per-user preference exists yet.
  $has_inc_canceled = metadata_exists('user', $user_id, '_vms_dash_include_canceled');
  $inc_canceled = $has_inc_canceled
    ? ((string) get_user_meta($user_id, '_vms_dash_include_canceled', true) === '1')
    : true;

  echo '<div id="vms-dashboard-filters"';
  echo ' data-vms-tour="dashboard-filters"';
  echo ' data-has-include-drafts="' . esc_attr($has_inc_drafts ? '1' : '0') . '"';
  echo ' data-include-drafts="' . esc_attr($inc_drafts ? '1' : '0') . '"';
  echo ' data-has-include-canceled="' . esc_attr($has_inc_canceled ? '1' : '0') . '"';
  echo ' data-include-canceled="' . esc_attr($inc_canceled ? '1' : '0') . '"';
  echo '>';


  if (function_exists('vms_dash_render_venue_selector')) {
    vms_dash_render_venue_selector();
  }

  // One canonical checkbox bar:
  echo '<label class="vms-dash-filter"><input type="checkbox" id="vms-only-open" checked> Show only Open</label>';
  echo '<label class="vms-dash-filter"><input type="checkbox" id="vms-include-canceled"' . checked(true, $inc_canceled, false) . '> Include canceled</label>';
  echo '<label class="vms-dash-filter"><input type="checkbox" id="vms-include-drafts"' . checked(true, $inc_drafts, false) . '> Include Draft/Ready</label>';

  echo '</div>';

  $start_venue_url = admin_url('post-new.php?post_type=vms_venue');
  $start_event_plan_url = admin_url('post-new.php?post_type=vms_event_plan');
  $start_vendor_url = admin_url('post-new.php?post_type=vms_vendor');
  $schedule_url = admin_url('admin.php?page=vms-schedule');

  echo '<div class="vms-dashboard-quick-actions" data-vms-tour="dashboard_quick_actions">';
  echo '<h2>' . esc_html__('Quick Actions', 'backstage-venue-manager') . '</h2>';
  echo '<p class="description">' . esc_html__('Jump directly into the most common setup and planning workflows.', 'backstage-venue-manager') . '</p>';
  echo '<div class="vms-dashboard-quick-actions__buttons">';
  echo '<a class="button button-primary" href="' . esc_url($start_event_plan_url) . '">' . esc_html__('Add Event Plan', 'backstage-venue-manager') . '</a>';
  echo '<a class="button" href="' . esc_url($schedule_url) . '">' . esc_html__('View Schedule', 'backstage-venue-manager') . '</a>';
  echo '<a class="button" href="' . esc_url($start_vendor_url) . '">' . esc_html__('Add Vendor', 'backstage-venue-manager') . '</a>';
  echo '<a class="button" href="' . esc_url($start_venue_url) . '" data-vms-tour="dashboard_start_venue">' . esc_html__('Add Venue', 'backstage-venue-manager') . '</a>';
  echo '</div>';
  echo '</div>';

  $guided_tours_url = admin_url('admin.php?page=vms-guided-tours');
  $dashboard_tour_button = '<button type="button" class="button button-secondary vms-tour-help-trigger" data-vms-tour-start="vms.dashboard.basics" data-vms-tour="dashboard_help_start">' . esc_html__('Start Guided Tour', 'backstage-venue-manager') . '</button>';
  if (function_exists('vms_render_help_button')) {
    $dashboard_tour_button = vms_render_help_button(array(
      'tour_id' => 'vms.dashboard.basics',
      'anchor' => 'dashboard_help_start',
      'label' => __('Start Guided Tour', 'backstage-venue-manager'),
      'class' => 'button-secondary',
    ));
  }
  echo '<div class="vms-dashboard-health" data-vms-tour="dashboard_health">';
  echo '<p>' . esc_html__('Need help on this page? Start the tour here or use the floating Help button.', 'backstage-venue-manager') . '</p>';
  echo '<p data-vms-tour="dashboard_help_action">' . wp_kses($dashboard_tour_button, vms_admin_menu_allowed_help_html()) . '</p>';
  /* translators: %s: Guided Tours settings admin URL. */
  echo '<p class="description">' . wp_kses_post(sprintf(__('Manage guided tour defaults and reset progress in <a href=\"%s\">Guided Tours settings</a>.', 'backstage-venue-manager'), esc_url($guided_tours_url))) . '</p>';
  echo '</div>';

  if (function_exists('vms_approvals_queue_render_dashboard_card')) {
    vms_approvals_queue_render_dashboard_card();
  }

  if (function_exists('vms_add_dispatch_render_dashboard_card')) {
    vms_add_dispatch_render_dashboard_card();
  }

  if (function_exists('vms_tasks_render_dashboard_cards')) {
    vms_tasks_render_dashboard_cards();
  }

  if (function_exists('vms_ticket_integrity_render_dashboard_panel')) {
    vms_ticket_integrity_render_dashboard_panel();
  }

  echo '<div id="vms-dashboard">';
  vms_dashboard_render_today_week_block();
  echo '</div>';
}
