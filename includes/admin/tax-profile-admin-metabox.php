<?php
if (!defined('ABSPATH')) exit;

/**
 * VMS – Admin Tax Profile Metabox (Vendor + Staff)
 * Lets admins enter/edit tax-profile fields + upload W-9 on behalf of the person.
 *
 * NO SSN/EIN stored here.
 */

add_action('add_meta_boxes', function () {
    $screens = ['vms_vendor', 'vms_staff'];

    foreach ($screens as $screen) {
        add_meta_box(
            'vms_tax_profile_admin_box',
            __('Tax Profile (Admin)', 'vms'),
            'vms_render_tax_profile_admin_metabox',
            $screen,
            'normal',
            'default'
        );
    }
});

add_action('save_post', function ($post_id, $post) {
    if (!is_object($post)) return;

    if (!in_array($post->post_type, ['vms_vendor', 'vms_staff'], true)) return;

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;
    if (!current_user_can('edit_post', $post_id)) return;

    if (
        empty($_POST['vms_tax_admin_nonce']) ||
        !wp_verify_nonce($_POST['vms_tax_admin_nonce'], 'vms_tax_admin_save')
    ) {
        return;
    }

    $t = function ($key) {
        return isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : '';
    };

    // Save safe W-9-ish fields (NO SSN/EIN)
    $payee_legal = $t('vms_payee_legal_name');
    $dba         = $t('vms_payee_dba');
    $entity      = $t('vms_entity_type');

    $addr1 = $t('vms_addr1');
    $addr2 = $t('vms_addr2');
    $city  = $t('vms_city');
    $state = strtoupper($t('vms_state'));
    $zip   = $t('vms_zip');

    if (strlen($state) > 2) $state = substr($state, 0, 2);

    update_post_meta($post_id, '_vms_payee_legal_name', $payee_legal);
    update_post_meta($post_id, '_vms_payee_dba', $dba);
    update_post_meta($post_id, '_vms_entity_type', $entity);

    update_post_meta($post_id, '_vms_addr1', $addr1);
    update_post_meta($post_id, '_vms_addr2', $addr2);
    update_post_meta($post_id, '_vms_city',  $city);
    update_post_meta($post_id, '_vms_state', $state);
    update_post_meta($post_id, '_vms_zip',   $zip);

    // Handle W-9 upload from admin screen
    if (!empty($_FILES['vms_w9_upload']['name'])) {
        if (!function_exists('media_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $allowed = array('application/pdf', 'image/jpeg', 'image/png', 'image/webp');
        $type = isset($_FILES['vms_w9_upload']['type']) ? (string) $_FILES['vms_w9_upload']['type'] : '';

        if ($type && !in_array($type, $allowed, true)) {
            // No hard fail here; just skip. (Could add admin notice later.)
        } else {
            $attach_id = media_handle_upload('vms_w9_upload', 0);
            if (!is_wp_error($attach_id)) {
                update_post_meta($post_id, '_vms_w9_upload_id', (int) $attach_id);
                update_post_meta($post_id, '_vms_w9_received_date', date('Y-m-d'));
            }
        }
    }

    // Completion stamp (optional)
    if (function_exists('vms_vendor_tax_profile_is_complete')) {
        if (vms_vendor_tax_profile_is_complete((int)$post_id)) {
            if (!(int) get_post_meta($post_id, '_vms_tax_profile_completed_at', true)) {
                update_post_meta($post_id, '_vms_tax_profile_completed_at', time());
            }
        }
    }

}, 20, 2);

function vms_render_tax_profile_admin_metabox($post)
{
    $id = (int) $post->ID;

    $m = function ($key, $default = '') use ($id) {
        $v = get_post_meta($id, $key, true);
        return ($v === '' || $v === null) ? $default : $v;
    };

    $payee_legal = $m('_vms_payee_legal_name');
    $dba         = $m('_vms_payee_dba');
    $entity      = $m('_vms_entity_type');

    $addr1 = $m('_vms_addr1');
    $addr2 = $m('_vms_addr2');
    $city  = $m('_vms_city');
    $state = $m('_vms_state');
    $zip   = $m('_vms_zip');

    $w9_upload_id = (int) $m('_vms_w9_upload_id', 0);
    $w9_url = $w9_upload_id ? wp_get_attachment_url($w9_upload_id) : '';
    $w9_label = $w9_upload_id ? get_the_title($w9_upload_id) : '';

    $is_complete = function_exists('vms_vendor_tax_profile_is_complete')
        ? vms_vendor_tax_profile_is_complete($id)
        : false;

    $entity_types = [
        ''            => __('— Select —', 'vms'),
        'individual'  => __('Individual / Sole Proprietor', 'vms'),
        'single_llc'  => __('Single-member LLC', 'vms'),
        'llc'         => __('LLC (multi-member)', 'vms'),
        'partnership' => __('Partnership', 'vms'),
        's_corp'      => __('S-Corp', 'vms'),
        'c_corp'      => __('C-Corp', 'vms'),
        'nonprofit'   => __('Nonprofit / Exempt', 'vms'),
        'other'       => __('Other', 'vms'),
    ];

    wp_nonce_field('vms_tax_admin_save', 'vms_tax_admin_nonce');

    echo '<style>
.vms-note{background:#f8fafc;border:1px solid #e5e7eb;border-radius:12px;padding:10px 12px;margin:10px 0;max-width:900px;}
.vms-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px 16px;max-width:900px;}
@media (max-width:980px){.vms-grid{grid-template-columns:1fr;}}
.vms-field label{font-weight:700;display:block;margin-bottom:4px;}
.vms-field input[type="text"], .vms-field select{width:100%;max-width:520px;}
.vms-help{margin:6px 0 0;color:#6b7280;font-size:12px;}
.vms-badge{display:inline-block;padding:3px 8px;border-radius:999px;font-size:12px;font-weight:700;}
.vms-badge-ok{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;}
.vms-badge-miss{background:#fff7ed;color:#9a3412;border:1px solid #fed7aa;}
</style>';

    echo '<p style="margin-top:0;">';
    echo $is_complete
        ? '<span class="vms-badge vms-badge-ok">' . esc_html__('Complete', 'vms') . '</span>'
        : '<span class="vms-badge vms-badge-miss">' . esc_html__('Incomplete', 'vms') . '</span>';
    echo '</p>';

    echo '<div class="vms-note"><strong>' . esc_html__('Privacy note:', 'vms') . '</strong> ' .
        esc_html__('Do NOT type SSN/EIN into WordPress. Use the signed W-9 upload for that.', 'vms') .
        '</div>';

    echo '<h3 style="margin:12px 0 8px;">' . esc_html__('Payee & Entity', 'vms') . '</h3>';

    echo '<div class="vms-grid">';
    echo '<div class="vms-field" style="grid-column:1/-1;">';
    echo '<label for="vms_payee_legal_name">' . esc_html__('Legal / Payee Name (as on W-9)', 'vms') . '</label>';
    echo '<input type="text" id="vms_payee_legal_name" name="vms_payee_legal_name" value="' . esc_attr($payee_legal) . '" required>';
    echo '</div>';

    echo '<div class="vms-field" style="grid-column:1/-1;">';
    echo '<label for="vms_payee_dba">' . esc_html__('Business Name / DBA (optional)', 'vms') . '</label>';
    echo '<input type="text" id="vms_payee_dba" name="vms_payee_dba" value="' . esc_attr($dba) . '">';
    echo '</div>';

    echo '<div class="vms-field">';
    echo '<label for="vms_entity_type">' . esc_html__('Entity Type', 'vms') . '</label>';
    echo '<select id="vms_entity_type" name="vms_entity_type" required>';
    foreach ($entity_types as $k => $label) {
        echo '<option value="' . esc_attr($k) . '" ' . selected($entity, $k, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select>';
    echo '</div>';
    echo '</div>';

    echo '<h3 style="margin:16px 0 8px;">' . esc_html__('Mailing Address', 'vms') . '</h3>';

    echo '<div class="vms-grid">';
    echo '<div class="vms-field" style="grid-column:1/-1;">';
    echo '<label for="vms_addr1">' . esc_html__('Address Line 1', 'vms') . '</label>';
    echo '<input type="text" id="vms_addr1" name="vms_addr1" value="' . esc_attr($addr1) . '" required>';
    echo '</div>';

    echo '<div class="vms-field" style="grid-column:1/-1;">';
    echo '<label for="vms_addr2">' . esc_html__('Address Line 2 (optional)', 'vms') . '</label>';
    echo '<input type="text" id="vms_addr2" name="vms_addr2" value="' . esc_attr($addr2) . '">';
    echo '</div>';

    echo '<div class="vms-field">';
    echo '<label for="vms_city">' . esc_html__('City', 'vms') . '</label>';
    echo '<input type="text" id="vms_city" name="vms_city" value="' . esc_attr($city) . '" required>';
    echo '</div>';

    echo '<div class="vms-field">';
    echo '<label for="vms_state">' . esc_html__('State', 'vms') . '</label>';
    echo '<input type="text" id="vms_state" name="vms_state" value="' . esc_attr($state) . '" maxlength="2" placeholder="TX" required>';
    echo '</div>';

    echo '<div class="vms-field">';
    echo '<label for="vms_zip">' . esc_html__('ZIP', 'vms') . '</label>';
    echo '<input type="text" id="vms_zip" name="vms_zip" value="' . esc_attr($zip) . '" required>';
    echo '</div>';
    echo '</div>';

    echo '<h3 style="margin:16px 0 8px;">' . esc_html__('Signed W-9 Upload', 'vms') . '</h3>';

    if ($w9_upload_id > 0 && $w9_url) {
        echo '<p class="description" style="margin:0 0 10px;">' .
            esc_html__('On file:', 'vms') . ' <a href="' . esc_url($w9_url) . '" target="_blank" rel="noopener">' .
            esc_html($w9_label ? $w9_label : __('View uploaded W-9', 'vms')) .
            '</a></p>';
    } else {
        echo '<p class="description" style="margin:0 0 10px;">' .
            esc_html__('No W-9 uploaded yet. Upload a signed W-9 PDF/image.', 'vms') .
            '</p>';
    }

    echo '<p style="margin:0 0 10px;">';
    echo '<input type="file" name="vms_w9_upload" accept="application/pdf,image/jpeg,image/png,image/webp">';
    echo '<div class="vms-help">' . esc_html__('Accepted: PDF, JPG, PNG, WEBP.', 'vms') . '</div>';
    echo '</p>';

    echo '<p class="description">' .
        esc_html__('Save/Update the post to store changes.', 'vms') .
        '</p>';
}