<?php
if (!defined('ABSPATH')) exit;

add_action('admin_menu', function () {

    // Top-level menu now points to a real Dashboard page
    add_menu_page(
        __('Vendor Management System', 'vms'),
        'VMS',
        'manage_options',
        'vms-dashboard',
        'vms_render_dashboard_page',
        'dashicons-calendar-alt',
        26
    );

    // First submenu: Dashboard (so it appears and is clearly the landing page)
    add_submenu_page(
        'vms-dashboard',
        __('Dashboard', 'vms'),
        __('Dashboard', 'vms'),
        'manage_options',
        'vms-dashboard',
        'vms_render_dashboard_page'
    );

    // Other submenus under the same parent slug
    add_submenu_page(
        'vms-dashboard',
        __('Season Dates', 'vms'),
        __('Season Dates', 'vms'),
        'manage_options',
        'vms-season-dates',
        'vms_render_season_dates_page'
    );

    add_submenu_page(
        'vms-dashboard',
        __('Season Board', 'vms'),
        __('Season Board', 'vms'),
        'manage_options',
        'vms-season-board',
        'vms_render_season_board_page'
    );
}, 5);

function vms_render_dashboard_stub()
{
    echo '<div class="wrap"><h1>VMS</h1>';
    vms_render_current_venue_selector();
    echo '<p>Select an item from the menu.</p></div>';
}

function vms_render_dashboard_page()
{
    echo '<p><small>Build: ' . esc_html(VMS_BUILD_ID) . '</small></p>';

    echo '<div class="wrap"><h1>VMS Dashboard</h1>';

    if (function_exists('vms_render_current_venue_selector')) {
        vms_render_current_venue_selector();
    }

    echo '<p>Select an item from the left menu to manage this venue.</p>';
    echo '</div>';
}
