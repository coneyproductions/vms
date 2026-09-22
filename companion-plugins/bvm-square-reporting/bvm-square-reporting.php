<?php
/**
 * Plugin Name: BVM Square Reporting
 * Description: Maps BVM online-only commerce lines to stable Square reporting categories without syncing event-specific products.
 * Version: 0.1.2
 * Author: Coney Productions
 * Text Domain: bvm-square-reporting
 */

defined('ABSPATH') || exit;

define('BVM_SQR_VERSION', '0.1.2');
define('BVM_SQR_FILE', __FILE__);
define('BVM_SQR_PATH', plugin_dir_path(__FILE__));

require_once BVM_SQR_PATH . 'includes/core.php';
require_once BVM_SQR_PATH . 'includes/admin.php';
