<?php
/*
Plugin Name: VMS Commerce Discounts
Description: Rule-based WooCommerce discounts for regular products, TEC/Event Tickets, and VMS entitlements.
Version: 0.2.13
Author: Venue Management System
Text Domain: vms-commerce-discounts
*/

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('VMS_DISCOUNTS_VERSION')) {
    define('VMS_DISCOUNTS_VERSION', '0.2.13');
}

if (!defined('VMS_DISCOUNTS_PATH')) {
    define('VMS_DISCOUNTS_PATH', plugin_dir_path(__FILE__));
}

if (!defined('VMS_DISCOUNTS_URL')) {
    define('VMS_DISCOUNTS_URL', plugin_dir_url(__FILE__));
}

require_once VMS_DISCOUNTS_PATH . 'includes/class-vms-discounts-loader.php';

register_activation_hook(__FILE__, ['VMS_Discounts_Loader', 'activate']);

add_action('plugins_loaded', static function () {
    VMS_Discounts_Loader::instance()->boot();
}, 20);
