<?php

/**
 * Plugin Name: VMS Data Tools
 * Description: Data movement tools for VMS (Vendor Import, exports, sync, etc.).
 * Version: 0.5.56
 * Author: Coney Productions LLC
 * Text Domain: vms-data-tools
 */

defined('ABSPATH') || exit;

if (defined('VMS_DT_PLUGIN_FILE') && realpath((string) VMS_DT_PLUGIN_FILE) !== realpath(__FILE__)) {
    add_action('admin_notices', static function (): void {
        if (!current_user_can('activate_plugins')) {
            return;
        }
        echo '<div class="notice notice-error"><p><strong>VMS Data Tools</strong> detected another active copy loaded from <code>' . esc_html((string) VMS_DT_PLUGIN_FILE) . '</code>. Deactivate/delete the duplicate Data Tools plugin folder, then activate this copy.</p></div>';
    });
    return;
}

define('VMS_DT_VERSION', '0.5.56');
define('VMS_DT_PLUGIN_FILE', __FILE__);
define('VMS_DT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('VMS_DT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('VMS_DT_INCLUDES_DIR', VMS_DT_PLUGIN_DIR . 'includes/');
define('VMS_DT_ADMIN_DIR', VMS_DT_PLUGIN_DIR . 'includes/admin/');
define('VMS_DT_SERVICES_DIR', VMS_DT_PLUGIN_DIR . 'includes/services/');

require_once VMS_DT_INCLUDES_DIR . 'core-compat.php';
require_once VMS_DT_INCLUDES_DIR . 'bootstrap.php';

register_activation_hook(__FILE__, 'vms_dt_activate');
register_deactivation_hook(__FILE__, 'vms_dt_deactivate');
register_activation_hook(__FILE__, 'vms_dt_add_caps');

function vms_dt_add_caps(): void
{
    $role = get_role('administrator');
    if ($role && !$role->has_cap('vms_import_vendors')) {
        $role->add_cap('vms_import_vendors');
    }
}

function vms_dt_boot_plugin(): void
{
    load_plugin_textdomain('vms-data-tools', false, dirname(plugin_basename(__FILE__)) . '/languages');
    vms_dt_init();
}

add_action('init', 'vms_dt_boot_plugin', 9);
