<?php
/**
 * Plugin Name: VMSX Weather Risk
 * Description: Explainable Show Risk Advisor premium add-on for VMS event plans.
 * Version: 0.1.12
 * Author: VMS
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: vmsx-weather-risk
 */

defined('ABSPATH') || exit;

if (!defined('VMSX_WR_FILE')) {
	define('VMSX_WR_FILE', __FILE__);
}
if (!defined('VMSX_WR_PATH')) {
	define('VMSX_WR_PATH', plugin_dir_path(__FILE__));
}
if (!defined('VMSX_WR_URL')) {
	define('VMSX_WR_URL', plugin_dir_url(__FILE__));
}
if (!defined('VMSX_WR_VERSION')) {
	define('VMSX_WR_VERSION', '0.1.12');
}

require_once VMSX_WR_PATH . 'includes/bootstrap.php';

register_activation_hook(__FILE__, array('VMSX_Weather_Risk', 'activate'));
register_deactivation_hook(__FILE__, array('VMSX_Weather_Risk', 'deactivate'));

VMSX_Weather_Risk::init();
