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

    // Single option array for VMS settings.
    register_setting('vms_settings_group', 'vms_settings', array(
        'type'              => 'array',
        'sanitize_callback' => 'vms_sanitize_settings',
        'default'           => array(),
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

    add_settings_field(
        'default_venue_id',
        __('Default Venue', 'vms'),
        'vms_field_default_venue',
        'vms-settings',
        'vms_settings_general'
    );

    add_settings_field(
        'season_dates_link',
        __('Season Dates', 'vms'),
        'vms_field_season_dates_link',
        'vms-settings',
        'vms_settings_general'
    );

    add_settings_field(
        'enable_woo',
        __('Enable Woo Features', 'vms'),
        'vms_field_enable_woo',
        'vms-settings',
        'vms_settings_general'
    );

    add_settings_field(
        'enable_tec_publish',
        __('Enable TEC Publishing', 'vms'),
        'vms_field_enable_tec_publish',
        'vms-settings',
        'vms_settings_general'
    );
});

function vms_sanitize_settings($input) {
    $out = array();

    // timezone
    $out['timezone'] = isset($input['timezone']) ? sanitize_text_field($input['timezone']) : '';

    // toggles
    $out['enable_woo'] = !empty($input['enable_woo']) ? 1 : 0;
    $out['enable_tec_publish'] = array_key_exists('enable_tec_publish', (array)$input)
        ? (!empty($input['enable_tec_publish']) ? 1 : 0)
        : 1;

    // default venue
    $out['default_venue_id'] = isset($input['default_venue_id']) ? absint($input['default_venue_id']) : 0;

    return $out;
}

function vms_field_timezone() {
    $opts  = (array) get_option('vms_settings', array());
    $saved = isset($opts['timezone']) ? (string) $opts['timezone'] : '';

    $tzs = DateTimeZone::listIdentifiers();

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

    $wp_offset = get_option('gmt_offset');
    if (empty($wp_tz) && $wp_offset !== '') {
        echo '<p class="description" style="color:#b32d2e;">';
        echo esc_html__('Warning: Your WordPress site timezone is set as a UTC offset. Consider switching WP to a named timezone for best results.', 'vms');
        echo '</p>';
    }
}

function vms_field_enable_woo() {
    $opts = (array) get_option('vms_settings', array());
    $val  = !empty($opts['enable_woo']) ? 1 : 0;

    echo '<label>';
    echo '<input type="checkbox" name="vms_settings[enable_woo]" value="1" ' . checked($val, 1, false) . ' /> ';
    echo esc_html__('Enable WooCommerce product publishing + attendance integration', 'vms');
    echo '</label>';
}

function vms_field_enable_tec_publish() {
    $opts = (array) get_option('vms_settings', array());
    $val  = array_key_exists('enable_tec_publish', $opts) ? (int) $opts['enable_tec_publish'] : 1;

    echo '<label>';
    echo '<input type="checkbox" name="vms_settings[enable_tec_publish]" value="1" ' . checked($val, 1, false) . ' /> ';
    echo esc_html__('Allow “Publish to Calendar” actions', 'vms');
    echo '</label>';
}

function vms_field_default_venue() {
    $opts  = (array) get_option('vms_settings', array());
    $saved = isset($opts['default_venue_id']) ? (int) $opts['default_venue_id'] : 0;

    $venues = get_posts(array(
        'post_type'      => 'vms_venue',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'fields'         => 'ids',
    ));

    echo '<select name="vms_settings[default_venue_id]" style="min-width:320px;">';
    echo '<option value="0">' . esc_html__('— None —', 'vms') . '</option>';

    foreach ($venues as $vid) {
        echo '<option value="' . esc_attr($vid) . '" ' . selected($saved, $vid, false) . '>';
        echo esc_html(get_the_title($vid));
        echo '</option>';
    }

    echo '</select>';
    echo '<p class="description">' . esc_html__('Used when no venue is selected in context.', 'vms') . '</p>';
}

function vms_field_season_dates_link() {
    $url = admin_url('admin.php?page=vms-season-dates');

    echo '<a class="button button-secondary" href="' . esc_url($url) . '">';
    echo esc_html__('Manage Season Dates', 'vms');
    echo '</a>';

    $rules = get_option('vms_season_rules', array());
    $active_dates = get_option('vms_active_dates', array());

    $rules_count = is_array($rules) ? count($rules) : 0;
    $dates_count = is_array($active_dates) ? count($active_dates) : 0;

    echo '<p class="description">';
    echo esc_html(sprintf('Season rules: %d | Active dates generated: %d', $rules_count, $dates_count));
    echo '</p>';
}

function vms_render_settings_page() {
    echo '<div class="wrap"><h1>' . esc_html__('VMS Settings', 'vms') . '</h1>';
    echo '<form method="post" action="options.php">';
    settings_fields('vms_settings_group');
    do_settings_sections('vms-settings');
    submit_button();
    echo '</form></div>';
}
