<?php
/**
 * Plugin Name: Backstage Venue Manager
 * Plugin URI: https://coneyproductions.booklivetalent.com/vms/
 * Description: Manage venue operations, event plans, vendor records, and optional ticketing workflows from WordPress.
 * Version: 1.2.0
 * Requires at least: 6.8
 * Requires PHP: 8.3
 * Author: Coney Productions
 * Author URI: https://coneyproductions.booklivetalent.com/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: backstage-venue-manager
 */

defined('ABSPATH') || exit;

// Detect incompatible parallel installations before loading shared declarations.
// Activation and deactivation remain explicit WordPress administrator actions.
$bvmgr_current_basename = plugin_basename(__FILE__);
$bvmgr_active_basenames = (array) get_option('active_plugins', array());
if (is_multisite()) {
    $bvmgr_active_basenames = array_merge($bvmgr_active_basenames, array_keys((array) get_site_option('active_sitewide_plugins', array())));
}
$bvmgr_conflict = defined('BVMGR_PLUGIN_FILE') && BVMGR_PLUGIN_FILE !== __FILE__;
foreach ($bvmgr_active_basenames as $bvmgr_active_basename) {
    if (in_array(basename((string) $bvmgr_active_basename), array('vendor-management-system.php', 'backstage-venue-manager.php', 'vms.php'), true)
        && dirname((string) $bvmgr_active_basename) !== dirname($bvmgr_current_basename)) {
        $bvmgr_conflict = true;
    }
}
if ($bvmgr_conflict) {
    add_action('admin_notices', static function (): void {
        if (!current_user_can('activate_plugins')) return;
        echo '<div class="notice notice-error"><p>' . esc_html__('Backstage Venue Manager is paused because another BVM or legacy VMS installation is active. Deactivate the other installation in Plugins (or Network Admin), then activate this installation. Your venue data is retained.', 'backstage-venue-manager') . '</p></div>';
    });
    add_action('network_admin_notices', static function (): void {
        if (!current_user_can('manage_network_plugins')) return;
        echo '<div class="notice notice-error"><p>' . esc_html__('Backstage Venue Manager requires one active installation. Deactivate the legacy VMS or duplicate BVM installation before activating this copy. Venue data is retained.', 'backstage-venue-manager') . '</p></div>';
    });
    register_activation_hook(__FILE__, static function (): void {
        wp_die(esc_html__('Deactivate the other BVM or legacy VMS installation in WordPress before activating Backstage Venue Manager. Venue data is retained.', 'backstage-venue-manager'));
    });
    unset($bvmgr_current_basename, $bvmgr_active_basenames, $bvmgr_active_basename, $bvmgr_conflict);
    return;
}
unset($bvmgr_current_basename, $bvmgr_active_basenames, $bvmgr_active_basename, $bvmgr_conflict);

define('BVMGR_PLUGIN_FILE', __FILE__);
define('BVMGR_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('BVMGR_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Activation hooks are allowed here, but the functions they call must be loaded.
 * If your activation function lives in includes/activation.php, keep it loaded via bootstrap.
 */

require_once BVMGR_PLUGIN_PATH . 'includes/plugin-basename-compat.php';
require_once BVMGR_PLUGIN_PATH . 'includes/core/prefix-b4-compat.php';
require_once BVMGR_PLUGIN_PATH . 'includes/runtime-guards.php';
require_once BVMGR_PLUGIN_PATH . 'includes/activation.php';
register_activation_hook(__FILE__, 'bvmgr_activate_plugin');
register_deactivation_hook(__FILE__, 'bvmgr_deactivate_plugin');

require_once BVMGR_PLUGIN_PATH . 'includes/bootstrap.php';
