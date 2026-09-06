<?php
if (!defined('ABSPATH')) {
    exit;
}

/*
 * Deprecated legacy admin shell.
 *
 * This page still hosts the older Data Tools "Events + Tickets CSV Import" flow for
 * backward compatibility. The Event Plan CSV Import v2 lives in VMS core, not this
 * plugin, at:
 * - wp-content/plugins/vms/includes/admin/data-tools/page-event-plan-import.php
 * - wp-content/plugins/vms/includes/services/event-plan-import/event-plan-import-engine.php
 */

function vms_dt_register_events_import_page()
{
$parent_slug = 'vms-data-tools';

$cap = function_exists('vms_dt_manage_capability') ? vms_dt_manage_capability() : 'manage_options';

add_submenu_page(
    $parent_slug,
    'Events + Tickets CSV Import',
    'Events CSV Import',
    $cap,
    'vms-dt-events-import',
    'vms_dt_render_events_import_page'
);
}

function vms_dt_render_events_import_page()
{
    if (!function_exists('vms_dt_current_user_can_manage_tools') || !vms_dt_current_user_can_manage_tools()) {
        return;
    }

    if (!function_exists('vms_dt_events_import_engine')) {
        echo '<div class="notice notice-error"><p>Events import engine is not loaded.</p></div>';
        return;
    }

    $engine = vms_dt_events_import_engine();

    $state = $engine->handle_request_and_get_view_state();

    echo '<div class="wrap">';
    echo '<h1>Events + Tickets CSV Import</h1>';

    if (!empty($state['notices'])) {
        foreach ($state['notices'] as $notice) {
            $class = !empty($notice['class']) ? $notice['class'] : 'notice notice-info';
            $msg   = !empty($notice['message']) ? $notice['message'] : '';
            echo '<div class="' . esc_attr($class) . '"><p>' . esc_html($msg) . '</p></div>';
        }
    }

    echo $engine->render_page($state);

    echo '</div>';
}
