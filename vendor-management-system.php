<?php
/**
 * Plugin Name: Vendor Management System (VMS)
 * Description: A vendor management, booking, and scheduling system for venues and events.
 * Author: Coney Productions
 * Version: 0.1.0
 * Text Domain: vms
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Plugin constants.
define( 'VMS_VERSION', '0.1.0' );
define( 'VMS_PLUGIN_FILE', __FILE__ );
define( 'VMS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'VMS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Autoload basic classes (simple manual loader for now).
spl_autoload_register( function ( $class ) {
    // Only load our own classes.
    if ( strpos( $class, 'VMS_' ) !== 0 ) {
        return;
    }

    $file = strtolower( str_replace( '_', '-', $class ) );
    $file = VMS_PLUGIN_DIR . 'includes/class-' . $file . '.php';

    if ( file_exists( $file ) ) {
        require_once $file;
    }
} );

// Boot the plugin.
function vms_boot_plugin() {
    // Ensure we only run after plugins_loaded.
    if ( ! did_action( 'plugins_loaded' ) ) {
        add_action( 'plugins_loaded', 'vms_boot_plugin' );
        return;
    }

    // Create the main plugin instance.
    $GLOBALS['vms_plugin'] = new VMS_Plugin();
}
vms_boot_plugin();

// Activation / deactivation hooks.
register_activation_hook( __FILE__, 'vms_activate_plugin' );
register_deactivation_hook( __FILE__, 'vms_deactivate_plugin' );

/**
 * Runs on plugin activation.
 */
function vms_activate_plugin() {
    // Initialize plugin once to register post types, etc.
    $plugin = new VMS_Plugin();
    $plugin->init();

    // Flush rewrite rules so custom post types' permalinks work.
    flush_rewrite_rules();
}

/**
 * Runs on plugin deactivation.
 */
function vms_deactivate_plugin() {
    // Flush rewrite rules to clean up.
    flush_rewrite_rules();
}
