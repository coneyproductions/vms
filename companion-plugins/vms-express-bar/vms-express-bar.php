<?php
/**
 * Plugin Name: VMS Express Bar
 * Description: Standalone Express Bar add-on for VMS Event Plans and WooCommerce.
 * Version: 0.6.40
 * Author: Coney Productions
 * Text Domain: vms-express-bar
 */

defined('ABSPATH') || exit;

define('VMSEB_FILE', __FILE__);
define('VMSEB_PATH', plugin_dir_path(__FILE__));
define('VMSEB_URL', plugin_dir_url(__FILE__));
define('VMSEB_VERSION', '0.6.40');

require_once VMSEB_PATH . 'includes/bootstrap.php';
