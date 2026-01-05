<?php
if (! defined('ABSPATH')) {
    exit;
}
 
/**
 * Admin settings for VMS.
 */
class VMS_Admin_Settings
{

    public function __construct()
    {
        add_action('admin_menu', array($this, 'register_settings_menu'));
    }

    /**
     * Register the main VMS settings menu.
     */
    public function register_settings_menu()
    {
        add_menu_page(
            __('VMS Settings', 'vms'),
            __('VMS Settings', 'vms'),
            'manage_options',
            'vms-settings',
            array($this, 'render_settings_page'),
            'dashicons-admin-generic',
            25
        );

        // For now, just one page. Later we'll add "Season", "Notifications" etc.
    }

    /**
     * Render the settings page.
     */
    public function render_settings_page()
    {
        echo '<div class="wrap"><h1>' . esc_html__('Vendor Management System Settings', 'vms') . '</h1>';
        echo '<p>' . esc_html__('Configure system-wide settings, including season schedules.', 'vms') . '</p>';
        echo '</div>';
    }
}
