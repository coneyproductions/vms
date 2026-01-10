<?php
if (!defined('ABSPATH')) exit;

/**
 * Vendor Contact Info (from Portal) + Copy-to-W9 helper
 *
 * Assumptions:
 * - Vendor portal saves contact fields as post meta on vms_vendor posts.
 * - You may need to adjust the meta keys below to match your actual portal fields.
 */

/**
 * ✅ EDIT THESE KEYS to match what your portal actually saves.
 * Left side = "portal/source" meta key
 * Right side = "admin/W9 destination" meta key
 */
function vms_get_vendor_contact_w9_meta_map(): array {
    return array(
        // Contact
        '_vms_contact_name'      => '_vms_w9_name',          // or '_vms_legal_name'
        '_vms_contact_email'     => '_vms_w9_email',
        '_vms_contact_phone'     => '_vms_w9_phone',

        // Address
        '_vms_contact_address1'  => '_vms_w9_address1',
        '_vms_contact_address2'  => '_vms_w9_address2',
        '_vms_contact_city'      => '_vms_w9_city',
        '_vms_contact_state'     => '_vms_w9_state',
        '_vms_contact_zip'       => '_vms_w9_zip',

        // Optional identifiers (only if you collect them)
        // '_vms_contact_business_name' => '_vms_w9_business_name',
        // '_vms_contact_tax_class'     => '_vms_w9_tax_class', // individual/llc/etc
    );
}

add_action('add_meta_boxes', function () {
    add_meta_box(
        'vms_vendor_contact_portal',
        __('Vendor Contact (Portal)', 'vms'),
        'vms_render_vendor_contact_portal_metabox',
        'vms_vendor',
        'side',
        'default'
    );
});

function vms_render_vendor_contact_portal_metabox($post)
{
    $map = vms_get_vendor_contact_w9_meta_map();

    // Pull the "portal/source" values
    $src = array();
    foreach ($map as $src_key => $dest_key) {
        $src[$src_key] = (string) get_post_meta($post->ID, $src_key, true);
    }

    // A little helper for display labels (feel free to tweak)
    $labels = array(
        '_vms_contact_name'     => __('Name', 'vms'),
        '_vms_contact_email'    => __('Email', 'vms'),
        '_vms_contact_phone'    => __('Phone', 'vms'),
        '_vms_contact_address1' => __('Address 1', 'vms'),
        '_vms_contact_address2' => __('Address 2', 'vms'),
        '_vms_contact_city'     => __('City', 'vms'),
        '_vms_contact_state'    => __('State', 'vms'),
        '_vms_contact_zip'      => __('ZIP', 'vms'),
    );

    wp_nonce_field('vms_vendor_contact_portal_box', 'vms_vendor_contact_portal_nonce');

    echo '<p class="description" style="margin-top:0;">' .
        esc_html__('This is what the vendor has entered in their portal (read-only).', 'vms') .
    '</p>';

    // Display table
    echo '<table class="widefat striped" style="margin:0;">';
    echo '<tbody>';

    $has_any = false;

    foreach ($src as $k => $v) {
        if ($v !== '') $has_any = true;

        $label = $labels[$k] ?? $k;

        // email/phone quick links
        $display = '—';
        if ($v !== '') {
            if (strpos($k, 'email') !== false) {
                $display = '<a href="mailto:' . esc_attr($v) . '">' . esc_html($v) . '</a>';
            } elseif (strpos($k, 'phone') !== false) {
                $display = '<a href="tel:' . esc_attr($v) . '">' . esc_html($v) . '</a>';
            } else {
                $display = esc_html($v);
            }
        }

        echo '<tr>';
        echo '<th style="width:34%;">' . esc_html($label) . '</th>';
        echo '<td>' . $display . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';

    if (!$has_any) {
        echo '<p class="description" style="margin:10px 0 0;">' .
            esc_html__('No portal contact info found yet for this vendor.', 'vms') .
        '</p>';
    }

    echo '<hr style="margin:12px 0;">';

    // Copy action
    echo '<p style="margin:0 0 8px;"><strong>' . esc_html__('W-9 Helper', 'vms') . '</strong></p>';

    echo '<label style="display:block;margin:0 0 8px;">';
    echo '<input type="checkbox" name="vms_w9_overwrite" value="1"> ';
    echo esc_html__('Overwrite existing W-9 fields (dangerous)', 'vms');
    echo '</label>';

    echo '<button type="submit" class="button button-secondary" name="vms_vendor_action" value="copy_contact_to_w9">';
    echo esc_html__('Copy portal contact → W-9', 'vms');
    echo '</button>';

    echo '<p class="description" style="margin:8px 0 0;">' .
        esc_html__('By default it fills blanks only. Check overwrite to force replace.', 'vms') .
    '</p>';
}

/**
 * Handle Copy-to-W9 action on save.
 */
add_action('save_post_vms_vendor', function ($post_id, $post) {

    // Only our post type
    if ($post->post_type !== 'vms_vendor') return;

    // Guard autosave/revisions
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;

    // Permission
    if (!current_user_can('edit_post', $post_id)) return;

    // Our nonce (metabox may not be present in some contexts)
    if (
        empty($_POST['vms_vendor_contact_portal_nonce']) ||
        !wp_verify_nonce($_POST['vms_vendor_contact_portal_nonce'], 'vms_vendor_contact_portal_box')
    ) {
        return;
    }

    // Only run if that button was clicked
    $action = isset($_POST['vms_vendor_action']) ? sanitize_text_field($_POST['vms_vendor_action']) : '';
    if ($action !== 'copy_contact_to_w9') return;

    $overwrite = !empty($_POST['vms_w9_overwrite']);

    $map = vms_get_vendor_contact_w9_meta_map();

    $changed = 0;

    foreach ($map as $src_key => $dest_key) {
        $src_val  = (string) get_post_meta($post_id, $src_key, true);
        $dest_val = (string) get_post_meta($post_id, $dest_key, true);

        if ($src_val === '') continue; // nothing to copy

        if ($overwrite) {
            update_post_meta($post_id, $dest_key, $src_val);
            $changed++;
        } else {
            // fill blanks only
            if ($dest_val === '') {
                update_post_meta($post_id, $dest_key, $src_val);
                $changed++;
            }
        }
    }

    // Optional: add a quick admin notice using your existing notice system if available
    if (function_exists('vms_add_admin_notice')) {
        vms_add_admin_notice(
            sprintf(__('Copied %d field(s) to W-9 for this vendor.', 'vms'), (int)$changed),
            'success'
        );
    }

}, 25, 2);
