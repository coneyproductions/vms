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
