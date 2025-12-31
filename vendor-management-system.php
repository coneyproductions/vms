<?php

/**
 * Plugin Name: Vendor Management System (VMS)
 * Description: A vendor management, booking, and scheduling system for venues and events.
 * Author: Coney Productions
 * Version: 0.1.0
 * Text Domain: vms
 * Domain Path: /languages
 */

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Plugin constants.
define('VMS_VERSION', '0.1.0');
define('VMS_PLUGIN_FILE', __FILE__);
define('VMS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('VMS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SR_EVENT_PLAN_STATUS_KEY', '_sr_event_plan_status'); // draft|ready|published

// Adjust these to match your existing meta keys:
define('SR_EVENT_PLAN_BAND_KEY',   '_sr_event_plan_band_vendor_id');
define('SR_EVENT_PLAN_START_KEY',  '_sr_event_plan_start_time');   // e.g. "19:00"
define('SR_EVENT_PLAN_END_KEY',    '_sr_event_plan_end_time');     // e.g. "22:00"

// Autoload basic classes (simple manual loader for now).
spl_autoload_register(function ($class) {
    // Only load our own classes.
    if (strpos($class, 'VMS_') !== 0) {
        return;
    }

    $file = strtolower(str_replace('_', '-', $class));
    $file = VMS_PLUGIN_DIR . 'includes/class-' . $file . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

// Boot the plugin.
function vms_boot_plugin()
{
    // Ensure we only run after plugins_loaded.
    if (! did_action('plugins_loaded')) {
        add_action('plugins_loaded', 'vms_boot_plugin');
        return;
    }

    // Create the main plugin instance.
    $GLOBALS['vms_plugin'] = new VMS_Plugin();
}
vms_boot_plugin();

// Activation / deactivation hooks.
register_activation_hook(__FILE__, 'vms_activate_plugin');
register_deactivation_hook(__FILE__, 'vms_deactivate_plugin');

/**
 * Runs on plugin activation.
 */
function vms_activate_plugin()
{
    // Initialize plugin once to register post types, etc.
    $plugin = new VMS_Plugin();
    $plugin->init();

    // Flush rewrite rules so custom post types' permalinks work.
    flush_rewrite_rules();
}

/**
 * Runs on plugin deactivation.
 */
function vms_deactivate_plugin()
{
    // Flush rewrite rules to clean up.
    flush_rewrite_rules();
}

add_action('init', 'vms_register_vendor_cpt');
function vms_register_vendor_cpt() {

    $labels = array(
        'name'               => __('Vendors', 'vms'),
        'singular_name'      => __('Vendor', 'vms'),
        'menu_name'          => __('Vendors', 'vms'),
        'name_admin_bar'     => __('Vendor', 'vms'),
        'add_new'            => __('Add New', 'vms'),
        'add_new_item'       => __('Add New Vendor', 'vms'),
        'new_item'           => __('New Vendor', 'vms'),
        'edit_item'          => __('Edit Vendor', 'vms'),
        'view_item'          => __('View Vendor', 'vms'),
        'all_items'          => __('All Vendors', 'vms'),
        'search_items'       => __('Search Vendors', 'vms'),
        'not_found'          => __('No vendors found.', 'vms'),
        'not_found_in_trash' => __('No vendors found in Trash.', 'vms'),
    );

    $args = array(
        'labels'             => $labels,
        'public'             => false,            // not public-facing (for MVP)
        'show_ui'            => true,             // visible in admin
        'show_in_menu'       => true,
        'menu_position'      => 25,
        'menu_icon'          => 'dashicons-groups',
        'supports'           => array('title', 'thumbnail'), // title = band name
        'has_archive'        => false,
        'capability_type'    => 'post',
    );

    register_post_type('vms_vendor', $args);
}
