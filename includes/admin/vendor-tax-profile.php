<?php
if (!defined('ABSPATH')) exit;

/**
 * Vendor Tax Profile (Admin Only)
 * Non-sensitive W-9–like information
 */

/**
 * Register metabox
 */
add_action('add_meta_boxes', function () {
    add_meta_box(
        'vms_vendor_tax_profile',
        __('Tax Profile (1099)', 'vms'),
        'vms_render_vendor_tax_profile_metabox',
        'vms_vendor',
        'side',
        'high'
    );
});

/**
 * Render metabox
 */
function vms_render_vendor_tax_profile_metabox($post) {

    wp_nonce_field('vms_vendor_tax_profile_save', 'vms_vendor_tax_profile_nonce');

    $meta = function($key) use ($post) {
        return get_post_meta($post->ID, $key, true);
    };

    $fields = [
        '_vms_tax_legal_name'   => __('Legal Name', 'vms'),
        '_vms_tax_business'     => __('Business Name (if different)', 'vms'),
        '_vms_tax_class'        => __('Tax Classification', 'vms'),
        '_vms_tax_address'      => __('Address', 'vms'),
        '_vms_tax_city'         => __('City', 'vms'),
        '_vms_tax_state'        => __('State', 'vms'),
        '_vms_tax_zip'          => __('ZIP', 'vms'),
    ];

    // Completion check
    $required_keys = [
        '_vms_tax_legal_name',
        '_vms_tax_class',
        '_vms_tax_address',
        '_vms_tax_city',
        '_vms_tax_state',
        '_vms_tax_zip',
    ];

    $complete = true;
    foreach ($required_keys as $k) {
        if (trim((string)$meta($k)) === '') {
            $complete = false;
            break;
        }
    }

    echo '<p><strong>Status:</strong> ';
    echo $complete
        ? '<span style="color:#15803d;">✔ Complete</span>'
        : '<span style="color:#b91c1c;">✖ Incomplete</span>';
    echo '</p><hr>';

    foreach ($fields as $key => $label) {
        echo '<p>';
        echo '<label><strong>' . esc_html($label) . '</strong></label><br>';
        echo '<input type="text" name="' . esc_attr($key) . '" value="' . esc_attr($meta($key)) . '" style="width:100%;">';
        echo '</p>';
    }

    echo '<p class="description" style="margin-top:8px;">';
    echo esc_html__('SSN / EIN is intentionally NOT collected here. Enter it manually when filing 1099s or collect via signed W-9.', 'vms');
    echo '</p>';
}

/**
 * Save handler
 */
add_action('save_post_vms_vendor', function ($post_id, $post) {

    if ($post->post_type !== 'vms_vendor') return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;

    if (
        empty($_POST['vms_vendor_tax_profile_nonce']) ||
        !wp_verify_nonce($_POST['vms_vendor_tax_profile_nonce'], 'vms_vendor_tax_profile_save')
    ) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) return;

    $keys = [
        '_vms_tax_legal_name',
        '_vms_tax_business',
        '_vms_tax_class',
        '_vms_tax_address',
        '_vms_tax_city',
        '_vms_tax_state',
        '_vms_tax_zip',
    ];

    foreach ($keys as $key) {
        $val = isset($_POST[$key]) ? sanitize_text_field($_POST[$key]) : '';
        update_post_meta($post_id, $key, $val);
    }

}, 20, 2);