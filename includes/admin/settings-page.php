<?php
if (!defined('ABSPATH')) exit;

add_action('admin_menu', function () {
    add_submenu_page(
        'vms-dashboard',
        __('Settings', 'vms'),
        __('Settings', 'vms'),
        'manage_options',
        'vms-settings',
        'vms_render_settings_page'
    );
});

add_action('admin_init', function () {
    register_setting('vms_settings_group', 'vms_settings', array(
        'type' => 'array',
        'sanitize_callback' => 'vms_sanitize_settings',
        'default' => array(),
    ));

    add_settings_section(
        'vms_settings_general',
        __('General', 'vms'),
        '__return_false',
        'vms-settings'
    );

    add_settings_field(
        'timezone',
        __('VMS Timezone', 'vms'),
        'vms_field_timezone',
        'vms-settings',
        'vms_settings_general'
    );
});

function vms_sanitize_settings($input) {
    $out = array();

    $tz = isset($input['timezone']) ? sanitize_text_field($input['timezone']) : '';
    $out['timezone'] = $tz;

    return $out;
}

function vms_field_timezone() {
    $opts = (array) get_option('vms_settings', array());
    $saved = isset($opts['timezone']) ? (string) $opts['timezone'] : '';

    // Build a list of PHP timezone identifiers
    $tzs = DateTimeZone::listIdentifiers();

    // Suggest WP site timezone as default choice
    $wp_tz = get_option('timezone_string');
    if (!$saved && $wp_tz) $saved = $wp_tz;

    echo '<select name="vms_settings[timezone]" style="min-width:320px;">';
    echo '<option value="">' . esc_html__('(Use WordPress Site Timezone)', 'vms') . '</option>';

    foreach ($tzs as $tz) {
        echo '<option value="' . esc_attr($tz) . '" ' . selected($saved, $tz, false) . '>' . esc_html($tz) . '</option>';
    }
    echo '</select>';

    echo '<p class="description">';
    echo esc_html__('Use a named timezone (e.g., America/Chicago) to handle daylight saving time correctly.', 'vms');
    echo '</p>';

    // If WP is using UTC offset, warn (offsets are DST-unsafe)
    $wp_offset = get_option('gmt_offset');
    if (empty($wp_tz) && $wp_offset !== '') {
        echo '<p class="description" style="color:#b32d2e;">';
        echo esc_html__('Warning: Your WordPress site timezone is set as a UTC offset. Consider switching WP to a named timezone for best results.', 'vms');
        echo '</p>';
    }
}

function vms_render_settings_page() {
    echo '<div class="wrap"><h1>' . esc_html__('VMS Settings', 'vms') . '</h1>';
    echo '<form method="post" action="options.php">';
    settings_fields('vms_settings_group');
    do_settings_sections('vms-settings');
    submit_button();
    echo '</form></div>';
}
