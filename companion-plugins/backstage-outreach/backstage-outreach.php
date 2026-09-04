<?php
/**
 * Plugin Name: Backstage Outreach
 * Description: Restores the Guest Pass Outreach campaign and recipient workflow for Backstage Venue Manager.
 * Version: 1.0.0
 * Requires at least: 6.8
 * Requires PHP: 8.3
 * Requires Plugins: backstage-venue-manager
 * Author: Coney Productions
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: backstage-outreach
 */

defined('ABSPATH') || exit;

define('BACKSTAGE_OUTREACH_VERSION', '1.0.0');
define('BACKSTAGE_OUTREACH_PLUGIN_FILE', __FILE__);
define('BACKSTAGE_OUTREACH_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('BACKSTAGE_OUTREACH_PLUGIN_URL', plugin_dir_url(__FILE__));

if (!function_exists('backstage_outreach_activate')) {
	function backstage_outreach_activate(): void
	{
		update_option('backstage_outreach_flush_rewrite', '1', false);
	}
}
register_activation_hook(__FILE__, 'backstage_outreach_activate');

if (!function_exists('backstage_outreach_dependency_error')) {
	function backstage_outreach_dependency_error(): string
	{
		if (defined('VMS_PLUGIN_FILE')) {
			return __('Backstage Outreach cannot run while the legacy VMS plugin is active. Keep VMS inactive and use Backstage Venue Manager 1.2.0 or newer.', 'backstage-outreach');
		}
		if (!defined('BVMGR_VERSION') || !function_exists('bvmgr_register_admin_page')) {
			return __('Backstage Outreach requires Backstage Venue Manager 1.2.0 or newer.', 'backstage-outreach');
		}
		if (version_compare((string) BVMGR_VERSION, '1.2.0', '<')) {
			return __('Backstage Outreach requires Backstage Venue Manager 1.2.0 or newer.', 'backstage-outreach');
		}
		return '';
	}
}

if (!function_exists('backstage_outreach_admin_dependency_notice')) {
	function backstage_outreach_admin_dependency_notice(): void
	{
		$message = backstage_outreach_dependency_error();
		if ($message === '' || !current_user_can('activate_plugins')) {
			return;
		}
		echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
	}
}

if (!function_exists('backstage_outreach_boot')) {
	function backstage_outreach_boot(): void
	{
		if (backstage_outreach_dependency_error() !== '') {
			add_action('admin_notices', 'backstage_outreach_admin_dependency_notice');
			return;
		}

		load_plugin_textdomain('backstage-outreach', false, dirname(plugin_basename(__FILE__)) . '/languages');

		require_once BACKSTAGE_OUTREACH_PLUGIN_PATH . 'includes/compat-bvm.php';
		require_once BACKSTAGE_OUTREACH_PLUGIN_PATH . 'includes/outreach/outreach.php';
		require_once BACKSTAGE_OUTREACH_PLUGIN_PATH . 'includes/admissions/outreach.php';
		require_once BACKSTAGE_OUTREACH_PLUGIN_PATH . 'includes/admissions/outreach-recipients.php';
		require_once BACKSTAGE_OUTREACH_PLUGIN_PATH . 'includes/integration-bvm.php';

		// This file is loaded after plugins_loaded, so boot the recovered module directly.
		if (function_exists('vms_outreach_module_boot')) {
			vms_outreach_module_boot();
		}
	}
}
add_action('plugins_loaded', 'backstage_outreach_boot', 30);
