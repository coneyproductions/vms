<?php
/**
 * Plugin Name: VMS – Venue Management System
 * Plugin URI: https://coneyproductions.booklivetalent.com/vms/
 * Description: Manage venue operations, event plans, vendor records, and optional ticketing workflows from WordPress.
 * Version: 1.0.0
 * Requires at least: 6.8
 * Requires PHP: 8.3
 * Author: Coney Productions
 * Author URI: https://coneyproductions.booklivetalent.com/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: vms
 * Domain Path: /languages
 */

defined('ABSPATH') || exit;
 
define('VMS_PLUGIN_FILE', __FILE__);
define('VMS_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('VMS_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Activation hooks are allowed here, but the functions they call must be loaded.
 * If your activation function lives in includes/activation.php, keep it loaded via bootstrap.
 */

require_once VMS_PLUGIN_PATH . 'includes/runtime-guards.php';
require_once VMS_PLUGIN_PATH . 'includes/activation.php';
register_activation_hook(__FILE__, 'vms_activate_plugin');
register_deactivation_hook(__FILE__, 'vms_deactivate_plugin');

if (!function_exists('vms_load_textdomain')) {
	function vms_load_textdomain(): void
	{
		load_plugin_textdomain('vms', false, dirname(plugin_basename(__FILE__)) . '/languages');
	}
}
add_action('init', 'vms_load_textdomain', 1);

require_once VMS_PLUGIN_PATH . 'includes/bootstrap.php';
  
 
