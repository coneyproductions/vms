<?php
if (!defined('ABSPATH')) exit;

/**
 * Parent menu slug used by VMS (your existing top-level menu).
 * If this ever changes, update this constant in ONE place.
 */
if (!defined('VMS_ADMIN_PARENT_SLUG')) {
    define('VMS_ADMIN_PARENT_SLUG', 'vms-season-board');
}

/**
 * Post type slug for vendor applications.
 */
if (!defined('VMS_VENDOR_APP_CPT')) {
    define('VMS_VENDOR_APP_CPT', 'vms_vendor_app');
}

/**
 * Vendor CPT slug (must match your vendors system).
 */
if (!defined('VMS_VENDOR_CPT')) {
    define('VMS_VENDOR_CPT', 'vms_vendor');
}

add_action('init', 'vms_register_venue_cpt');

function vms_register_venue_cpt()
{

    $labels = array(
        'name'               => __('Venues', 'vms'),
        'singular_name'      => __('Venue', 'vms'),
        'menu_name'          => __('Venues', 'vms'),
        'add_new'            => __('Add New', 'vms'),
        'add_new_item'       => __('Add New Venue', 'vms'),
        'edit_item'          => __('Edit Venue', 'vms'),
        'new_item'           => __('New Venue', 'vms'),
        'view_item'          => __('View Venue', 'vms'),
        'search_items'       => __('Search Venues', 'vms'),
        'not_found'          => __('No venues found', 'vms'),
        'not_found_in_trash' => __('No venues found in Trash', 'vms'),
    );

    $args = array(
        'labels'             => $labels,
        'public'             => false,
        'show_ui'            => true,
        'show_in_menu'       => 'vms',
        'menu_position'      => 26,
        'menu_icon'          => 'dashicons-location-alt',
        'supports'           => array('title', 'editor', 'thumbnail'),
        'capability_type'    => 'post',
        'has_archive'        => false,
        'rewrite'            => false,
    );

    register_post_type('vms_venue', $args);
}

/**
 * Venue Default Event Times
 * - Default start time
 * - Default duration (minutes)
 * - Optional default end time
 */

add_action('add_meta_boxes', function () {
    add_meta_box(
        'vms_venue_default_times',
        __('Default Event Times', 'vms'),
        'vms_render_venue_default_times_box',
        'vms_venue',
        'side',
        'default'
    );
});

function vms_render_venue_default_times_box($post) {
    wp_nonce_field('vms_save_venue_default_times', 'vms_venue_default_times_nonce');

    $start = (string) get_post_meta($post->ID, '_vms_default_start_time', true);
    $dur   = (string) get_post_meta($post->ID, '_vms_default_duration_min', true);
    $end   = (string) get_post_meta($post->ID, '_vms_default_end_time', true);

    echo '<p class="description" style="margin-top:0;">' .
        esc_html__('Used to pre-fill Start/End time when creating a new Event Plan for this venue.', 'vms') .
    '</p>';

    echo '<p style="margin:0 0 10px;">';
    echo '<label for="vms_default_start_time"><strong>' . esc_html__('Default Start', 'vms') . '</strong></label><br>';
    echo '<input type="time" id="vms_default_start_time" name="vms_default_start_time" value="' . esc_attr($start) . '" style="width:100%;">';
    echo '</p>';

    echo '<p style="margin:0 0 10px;">';
    echo '<label for="vms_default_duration_min"><strong>' . esc_html__('Default Duration (minutes)', 'vms') . '</strong></label><br>';
    echo '<input type="number" min="0" step="1" id="vms_default_duration_min" name="vms_default_duration_min" value="' . esc_attr($dur) . '" style="width:100%;" placeholder="120">';
    echo '</p>';

    echo '<p style="margin:0 0 10px;">';
    echo '<label for="vms_default_end_time"><strong>' . esc_html__('Default End (optional)', 'vms') . '</strong></label><br>';
    echo '<input type="time" id="vms_default_end_time" name="vms_default_end_time" value="' . esc_attr($end) . '" style="width:100%;">';
    echo '<span class="description">' . esc_html__('If set, this overrides duration-based end time defaults.', 'vms') . '</span>';
    echo '</p>';
}

add_action('save_post_vms_venue', function ($post_id, $post) {
    if ($post->post_type !== 'vms_venue') return;

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;
    if (!current_user_can('edit_post', $post_id)) return;

    if (
        empty($_POST['vms_venue_default_times_nonce']) ||
        !wp_verify_nonce($_POST['vms_venue_default_times_nonce'], 'vms_save_venue_default_times')
    ) {
        return;
    }

    $start = isset($_POST['vms_default_start_time']) ? sanitize_text_field(wp_unslash($_POST['vms_default_start_time'])) : '';
    $end   = isset($_POST['vms_default_end_time']) ? sanitize_text_field(wp_unslash($_POST['vms_default_end_time'])) : '';
    $dur   = isset($_POST['vms_default_duration_min']) ? absint($_POST['vms_default_duration_min']) : 0;

    // Basic time format guard: allow '' or HH:MM
    if ($start !== '' && !preg_match('/^\d{2}:\d{2}$/', $start)) $start = '';
    if ($end !== '' && !preg_match('/^\d{2}:\d{2}$/', $end)) $end = '';

    update_post_meta($post_id, '_vms_default_start_time', $start);
    update_post_meta($post_id, '_vms_default_end_time', $end);
    update_post_meta($post_id, '_vms_default_duration_min', $dur);
}, 20, 2);