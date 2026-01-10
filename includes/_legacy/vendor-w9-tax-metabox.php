<?php
if (!defined('ABSPATH')) exit;

/**
 * Vendor W-9 / Tax Metabox (Admin)
 * Stores on vms_vendor post meta:
 *  _vms_w9_legal_name
 *  _vms_w9_business_name
 *  _vms_w9_tax_class
 *  _vms_w9_tin_last4
 *  _vms_w9_address1
 *  _vms_w9_address2
 *  _vms_w9_city
 *  _vms_w9_state
 *  _vms_w9_zip
 *  _vms_w9_email
 *  _vms_w9_phone
 *  _vms_w9_status
 *  _vms_w9_requested_date
 *  _vms_w9_received_date
 *  _vms_1099_eligible
 */

add_action('add_meta_boxes', function () {
    add_meta_box(
        'vms_vendor_w9_tax',
        __('W-9 / Tax (Admin)', 'vms'),
        'vms_render_vendor_w9_tax_metabox',
        'vms_vendor',
        'normal',
        'default'
    );
});

function vms_render_vendor_w9_tax_metabox($post)
{
    wp_nonce_field('vms_save_vendor_w9_tax', 'vms_vendor_w9_tax_nonce');

    // Load saved W-9 fields
    $get = function(string $key, $default = '') use ($post) {
        $v = get_post_meta($post->ID, $key, true);
        return ($v === '' || $v === null) ? $default : $v;
    };

    $legal_name     = (string) $get('_vms_w9_legal_name');
    $business_name  = (string) $get('_vms_w9_business_name');
    $tax_class      = (string) $get('_vms_w9_tax_class');     // individual|sole_prop|llc|c_corp|s_corp|partnership|trust|other
    $tin_last4      = (string) $get('_vms_w9_tin_last4');

    $addr1          = (string) $get('_vms_w9_address1');
    $addr2          = (string) $get('_vms_w9_address2');
    $city           = (string) $get('_vms_w9_city');
    $state          = (string) $get('_vms_w9_state');
    $zip            = (string) $get('_vms_w9_zip');

    $email          = (string) $get('_vms_w9_email');
    $phone          = (string) $get('_vms_w9_phone');

    $status         = (string) $get('_vms_w9_status', 'not_requested'); // not_requested|requested|received|verified
    $requested_date = (string) $get('_vms_w9_requested_date');
    $received_date  = (string) $get('_vms_w9_received_date');

    $eligible_1099  = (int) $get('_vms_1099_eligible', 1);

    // Also load portal contact (read-only preview inside this box)
    $portal_name  = (string) get_post_meta($post->ID, '_vms_contact_name', true);
    $portal_email = (string) get_post_meta($post->ID, '_vms_contact_email', true);
    $portal_phone = (string) get_post_meta($post->ID, '_vms_contact_phone', true);
    $portal_loc   = (string) get_post_meta($post->ID, '_vms_vendor_location', true);

    $tax_class_options = array(
        ''            => __('— Select —', 'vms'),
        'individual'  => __('Individual / Sole proprietor', 'vms'),
        'llc'         => __('LLC', 'vms'),
        'c_corp'      => __('C Corporation', 'vms'),
        's_corp'      => __('S Corporation', 'vms'),
        'partnership' => __('Partnership', 'vms'),
        'trust'       => __('Trust / Estate', 'vms'),
        'other'       => __('Other', 'vms'),
    );

    $status_options = array(
        'not_requested' => __('Not requested', 'vms'),
        'requested'     => __('Requested', 'vms'),
        'received'      => __('Received', 'vms'),
        'verified'      => __('Verified', 'vms'),
    );

    echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start;">';

    // Left: W-9 core
    echo '<div style="background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:12px;">';
    echo '<h4 style="margin:0 0 10px;">' . esc_html__('W-9 Fields', 'vms') . '</h4>';

    echo '<p style="margin:0 0 10px;"><label><strong>' . esc_html__('Legal Name', 'vms') . '</strong></label><br>';
    echo '<input type="text" name="vms_w9_legal_name" value="' . esc_attr($legal_name) . '" style="width:100%;max-width:520px;"></p>';

    echo '<p style="margin:0 0 10px;"><label><strong>' . esc_html__('Business Name (DBA)', 'vms') . '</strong></label><br>';
    echo '<input type="text" name="vms_w9_business_name" value="' . esc_attr($business_name) . '" style="width:100%;max-width:520px;"></p>';

    echo '<p style="margin:0 0 10px;"><label><strong>' . esc_html__('Tax Classification', 'vms') . '</strong></label><br>';
    echo '<select name="vms_w9_tax_class" style="min-width:260px;">';
    foreach ($tax_class_options as $k => $label) {
        echo '<option value="' . esc_attr($k) . '" ' . selected($tax_class, $k, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select></p>';

    echo '<p style="margin:0 0 10px;"><label><strong>' . esc_html__('TIN/SSN (last 4 only)', 'vms') . '</strong></label><br>';
    echo '<input type="text" name="vms_w9_tin_last4" value="' . esc_attr($tin_last4) . '" style="width:120px;" maxlength="4" placeholder="1234"></p>';

    echo '<hr style="margin:12px 0;">';

    echo '<p style="margin:0 0 10px;"><label><strong>' . esc_html__('Address 1', 'vms') . '</strong></label><br>';
    echo '<input type="text" name="vms_w9_address1" value="' . esc_attr($addr1) . '" style="width:100%;max-width:520px;"></p>';

    echo '<p style="margin:0 0 10px;"><label><strong>' . esc_html__('Address 2', 'vms') . '</strong></label><br>';
    echo '<input type="text" name="vms_w9_address2" value="' . esc_attr($addr2) . '" style="width:100%;max-width:520px;"></p>';

    echo '<div style="display:grid;grid-template-columns:1fr 100px 140px;gap:10px;">';
    echo '<p style="margin:0;"><label><strong>' . esc_html__('City', 'vms') . '</strong></label><br>';
    echo '<input type="text" name="vms_w9_city" value="' . esc_attr($city) . '" style="width:100%;"></p>';

    echo '<p style="margin:0;"><label><strong>' . esc_html__('State', 'vms') . '</strong></label><br>';
    echo '<input type="text" name="vms_w9_state" value="' . esc_attr($state) . '" style="width:100%;" maxlength="2" placeholder="TX"></p>';

    echo '<p style="margin:0;"><label><strong>' . esc_html__('ZIP', 'vms') . '</strong></label><br>';
    echo '<input type="text" name="vms_w9_zip" value="' . esc_attr($zip) . '" style="width:100%;" maxlength="10" placeholder="75701"></p>';
    echo '</div>';

    echo '<hr style="margin:12px 0;">';

    echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">';
    echo '<p style="margin:0;"><label><strong>' . esc_html__('W-9 Email', 'vms') . '</strong></label><br>';
    echo '<input type="email" name="vms_w9_email" value="' . esc_attr($email) . '" style="width:100%;"></p>';

    echo '<p style="margin:0;"><label><strong>' . esc_html__('W-9 Phone', 'vms') . '</strong></label><br>';
    echo '<input type="text" name="vms_w9_phone" value="' . esc_attr($phone) . '" style="width:100%;"></p>';
    echo '</div>';

    echo '</div>'; // left card

    // Right: Status + Portal preview + helper actions
    echo '<div style="background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:12px;">';
    echo '<h4 style="margin:0 0 10px;">' . esc_html__('Status & Helpers', 'vms') . '</h4>';

    echo '<p style="margin:0 0 10px;"><label><strong>' . esc_html__('W-9 Status', 'vms') . '</strong></label><br>';
    echo '<select name="vms_w9_status" style="min-width:220px;">';
    foreach ($status_options as $k => $label) {
        echo '<option value="' . esc_attr($k) . '" ' . selected($status, $k, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select></p>';

    echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">';
    echo '<p style="margin:0 0 10px;"><label><strong>' . esc_html__('Requested Date', 'vms') . '</strong></label><br>';
    echo '<input type="date" name="vms_w9_requested_date" value="' . esc_attr($requested_date) . '"></p>';

    echo '<p style="margin:0 0 10px;"><label><strong>' . esc_html__('Received Date', 'vms') . '</strong></label><br>';
    echo '<input type="date" name="vms_w9_received_date" value="' . esc_attr($received_date) . '"></p>';
    echo '</div>';

    echo '<p style="margin:0 0 12px;"><label>';
    echo '<input type="checkbox" name="vms_1099_eligible" value="1" ' . checked($eligible_1099, 1, false) . '> ';
    echo esc_html__('1099 eligible', 'vms');
    echo '</label></p>';

    echo '<hr style="margin:12px 0;">';

    echo '<h4 style="margin:0 0 8px;">' . esc_html__('Portal Contact (Read-only)', 'vms') . '</h4>';
    echo '<table class="widefat striped" style="margin:0;"><tbody>';
    echo '<tr><th>' . esc_html__('Name', 'vms') . '</th><td>' . ($portal_name !== '' ? esc_html($portal_name) : '—') . '</td></tr>';
    echo '<tr><th>' . esc_html__('Email', 'vms') . '</th><td>' . ($portal_email !== '' ? '<a href="mailto:' . esc_attr($portal_email) . '">' . esc_html($portal_email) . '</a>' : '—') . '</td></tr>';
    echo '<tr><th>' . esc_html__('Phone', 'vms') . '</th><td>' . ($portal_phone !== '' ? '<a href="tel:' . esc_attr($portal_phone) . '">' . esc_html($portal_phone) . '</a>' : '—') . '</td></tr>';
    echo '<tr><th>' . esc_html__('Home Base', 'vms') . '</th><td>' . ($portal_loc !== '' ? esc_html($portal_loc) : '—') . '</td></tr>';
    echo '</tbody></table>';

    echo '<p class="description" style="margin:10px 0 0;">' .
        esc_html__('Helper idea: you can copy these into W-9 fields with a button (fill blanks or overwrite).', 'vms') .
    '</p>';

    echo '</div>'; // right card

    echo '</div>'; // grid wrapper
}

add_action('save_post_vms_vendor', function ($post_id, $post) {

    if ($post->post_type !== 'vms_vendor') return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;
    if (!current_user_can('edit_post', $post_id)) return;

    if (
        empty($_POST['vms_vendor_w9_tax_nonce']) ||
        !wp_verify_nonce($_POST['vms_vendor_w9_tax_nonce'], 'vms_save_vendor_w9_tax')
    ) {
        return;
    }

    // Sanitize helpers
    $sf = fn($k) => isset($_POST[$k]) ? sanitize_text_field(wp_unslash($_POST[$k])) : '';
    $se = fn($k) => isset($_POST[$k]) ? sanitize_email(wp_unslash($_POST[$k])) : '';

    $fields = array(
        '_vms_w9_legal_name'      => $sf('vms_w9_legal_name'),
        '_vms_w9_business_name'   => $sf('vms_w9_business_name'),
        '_vms_w9_tax_class'       => $sf('vms_w9_tax_class'),
        '_vms_w9_tin_last4'       => preg_replace('/\D+/', '', $sf('vms_w9_tin_last4')), // digits only

        '_vms_w9_address1'        => $sf('vms_w9_address1'),
        '_vms_w9_address2'        => $sf('vms_w9_address2'),
        '_vms_w9_city'            => $sf('vms_w9_city'),
        '_vms_w9_state'           => strtoupper($sf('vms_w9_state')),
        '_vms_w9_zip'             => $sf('vms_w9_zip'),

        '_vms_w9_email'           => $se('vms_w9_email'),
        '_vms_w9_phone'           => $sf('vms_w9_phone'),

        '_vms_w9_status'          => $sf('vms_w9_status'),
        '_vms_w9_requested_date'  => $sf('vms_w9_requested_date'),
        '_vms_w9_received_date'   => $sf('vms_w9_received_date'),

        '_vms_1099_eligible'      => !empty($_POST['vms_1099_eligible']) ? 1 : 0,
    );

    // Save (delete empties to keep DB clean)
    foreach ($fields as $meta_key => $value) {
        if ($value === '' || $value === null) {
            delete_post_meta($post_id, $meta_key);
        } else {
            update_post_meta($post_id, $meta_key, $value);
        }
    }

}, 20, 2);
