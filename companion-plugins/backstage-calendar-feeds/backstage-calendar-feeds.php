<?php
/**
 * Plugin Name: Backstage Calendar Feeds
 * Description: Generate secure calendar and availability feeds from registered Backstage-compatible calendar providers.
 * Version: 0.1.4
 * Author: Coney Productions
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Text Domain: backstage-calendar-feeds
 */

namespace ConeyProductions\BackstageCalendarFeeds;

defined( 'ABSPATH' ) || exit;

define( 'BCF_VERSION', '0.1.4' );
define( 'BCF_PLUGIN_FILE', __FILE__ );
define( 'BCF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once BCF_PLUGIN_DIR . 'includes/provider-interface.php';
require_once BCF_PLUGIN_DIR . 'includes/profile.php';
require_once BCF_PLUGIN_DIR . 'includes/class-drm-calendar-intake-provider.php';
require_once BCF_PLUGIN_DIR . 'includes/class-strict-availability-policy.php';
require_once BCF_PLUGIN_DIR . 'includes/class-supersession-resolver.php';
require_once BCF_PLUGIN_DIR . 'includes/class-publication-ledger.php';
require_once BCF_PLUGIN_DIR . 'includes/class-duplicate-detector.php';
require_once BCF_PLUGIN_DIR . 'includes/class-ics-formatter.php';
require_once BCF_PLUGIN_DIR . 'includes/class-feed-service.php';
require_once BCF_PLUGIN_DIR . 'includes/class-secret-store.php';
require_once BCF_PLUGIN_DIR . 'includes/class-admin-authorization.php';
require_once BCF_PLUGIN_DIR . 'includes/class-plugin.php';

add_action( 'plugins_loaded', array( Plugin::class, 'boot' ) );
register_activation_hook( __FILE__, array( Plugin::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Plugin::class, 'deactivate' ) );
