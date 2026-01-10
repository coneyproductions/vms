<?php

/**
 * VMS Vendor Portal — Tax Profile (W-9-ish, NO SSN/EIN)
 *
 * What this adds (vendor-facing):
 * - Legal/Payee name, DBA (optional)
 * - Entity type
 * - Mailing address
 * - W-9 file upload (PDF/image)
 *
 * What it does NOT collect/store:
 * - SSN / EIN / bank info
 *
 * Meta keys used:
 *  _vms_payee_legal_name
 *  _vms_payee_dba
 *  _vms_entity_type
 *  _vms_addr1, _vms_addr2, _vms_city, _vms_state, _vms_zip
 *  _vms_w9_upload_id (attachment ID)
 *  _vms_w9_received_date (YYYY-MM-DD)
 *  _vms_tax_profile_completed_at (timestamp)
 *
 * Drop this into: includes/portal/vendor-portal.php
 * Then call vms_vendor_portal_render_tax_profile($vendor_id) wherever you want it shown.
 */

if (!defined('ABSPATH')) exit;

if (!function_exists('vms_is_vendor_tax_profile_complete')) {
    function vms_is_vendor_tax_profile_complete($vendor_id) {
        return vms_vendor_tax_profile_is_complete($vendor_id);
    }
}
/**
 * Completion check helper (safe, no sensitive fields).
 */
function vms_vendor_tax_profile_is_complete(int $vendor_id): bool
{
    $legal = (string) get_post_meta($vendor_id, '_vms_payee_legal_name', true);
    $entity = (string) get_post_meta($vendor_id, '_vms_entity_type', true);

    $addr1 = (string) get_post_meta($vendor_id, '_vms_addr1', true);
    $city  = (string) get_post_meta($vendor_id, '_vms_city', true);
    $state = (string) get_post_meta($vendor_id, '_vms_state', true);
    $zip   = (string) get_post_meta($vendor_id, '_vms_zip', true);

    $w9_upload_id = (int) get_post_meta($vendor_id, '_vms_w9_upload_id', true);

    if ($legal === '') return false;
    if ($entity === '') return false;
    if ($addr1 === '' || $city === '' || $state === '' || $zip === '') return false;
    if ($w9_upload_id <= 0) return false;

    return true;
}

/**
 * Render vendor-facing Tax Profile section.
 */
function vms_vendor_portal_render_tax_profile($vendor_id)
{
    $vendor_id = (int) $vendor_id;

    // Ensure media handling exists on front-end.
    if (!function_exists('media_handle_upload')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }

    // Save handler
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vms_vendor_tax_save'])) {

        if (
            empty($_POST['vms_vendor_tax_nonce']) ||
            !wp_verify_nonce($_POST['vms_vendor_tax_nonce'], 'vms_vendor_tax_save')
        ) {
            echo vms_portal_notice('error', __('Security check failed.', 'vms'));
        } else {

            // Text helpers
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

            // Basic normalization
            if (strlen($state) > 2) $state = substr($state, 0, 2);

            update_post_meta($vendor_id, '_vms_payee_legal_name', $payee_legal);
            update_post_meta($vendor_id, '_vms_payee_dba', $dba);
            update_post_meta($vendor_id, '_vms_entity_type', $entity);

            update_post_meta($vendor_id, '_vms_addr1', $addr1);
            update_post_meta($vendor_id, '_vms_addr2', $addr2);
            update_post_meta($vendor_id, '_vms_city',  $city);
            update_post_meta($vendor_id, '_vms_state', $state);
            update_post_meta($vendor_id, '_vms_zip',   $zip);

            // Handle W-9 upload (PDF/image)
            if (!empty($_FILES['vms_w9_upload']['name'])) {
                $allowed = array('application/pdf', 'image/jpeg', 'image/png', 'image/webp');
                $type = isset($_FILES['vms_w9_upload']['type']) ? (string) $_FILES['vms_w9_upload']['type'] : '';

                if ($type && !in_array($type, $allowed, true)) {
                    echo vms_portal_notice('error', __('Upload must be a PDF or image (JPG/PNG/WEBP).', 'vms'));
                } else {
                    $attach_id = media_handle_upload('vms_w9_upload', 0);

                    if (is_wp_error($attach_id)) {
                        echo vms_portal_notice('error', __('W-9 upload failed: ', 'vms') . $attach_id->get_error_message());
                    } else {
                        update_post_meta($vendor_id, '_vms_w9_upload_id', (int) $attach_id);
                        update_post_meta($vendor_id, '_vms_w9_received_date', date('Y-m-d'));
                    }
                }
            }

            // Completion stamp
            if (vms_vendor_tax_profile_is_complete($vendor_id)) {
                if (!(int) get_post_meta($vendor_id, '_vms_tax_profile_completed_at', true)) {
                    update_post_meta($vendor_id, '_vms_tax_profile_completed_at', time());
                }
            }

            echo vms_portal_notice('success', __('Tax Profile saved.', 'vms'));
        }
    }

    // Load current values
    $m = function ($key, $default = '') use ($vendor_id) {
        $v = get_post_meta($vendor_id, $key, true);
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

    $w9_upload_id = (int) get_post_meta($vendor_id, '_vms_w9_upload_id', true);
    $w9_url = $w9_upload_id ? wp_get_attachment_url($w9_upload_id) : '';
    $w9_label = $w9_upload_id ? get_the_title($w9_upload_id) : '';

    $is_complete = vms_vendor_tax_profile_is_complete($vendor_id);

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

    // UI
    echo '<style>
.vms-panel{border-radius:14px;border:1px solid #e5e5e5;background:#fff;margin:12px 0;}
.vms-panel > summary{list-style:none;}
.vms-panel > summary::-webkit-details-marker{display:none;}
.vms-panel-summary{cursor:pointer;font-weight:800;font-size:15px;padding:12px 14px;display:flex;align-items:center;justify-content:space-between;gap:10px;}
.vms-badge{display:inline-block;padding:3px 8px;border-radius:999px;font-size:12px;font-weight:700;}
.vms-badge-ok{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;}
.vms-badge-miss{background:#fff7ed;color:#9a3412;border:1px solid #fed7aa;}
.vms-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px 16px;max-width:860px;}
.vms-grid .full{grid-column:1 / -1;}
.vms-field label{font-weight:700;display:block;margin-bottom:4px;}
.vms-field input[type="text"],.vms-field input[type="email"],.vms-field input[type="url"],.vms-field input[type="file"],.vms-field select{
  width:100%;max-width:520px;
}
.vms-help{margin:6px 0 0;color:#6b7280;font-size:12px;}
.vms-note{background:#f8fafc;border:1px solid #e5e7eb;border-radius:12px;padding:10px 12px;margin:10px 0 0;max-width:860px;}
</style>';

    echo '<details class="vms-panel" open>';
    echo '<summary class="vms-panel-summary">';
    echo '<span>' . esc_html__('Tax Profile (Required)', 'vms') . '</span>';

    if ($is_complete) {
        echo '<span class="vms-badge vms-badge-ok">' . esc_html__('Complete', 'vms') . '</span>';
    } else {
        echo '<span class="vms-badge vms-badge-miss">' . esc_html__('Incomplete', 'vms') . '</span>';
    }
    echo '</summary>';

    echo '<div style="padding:14px 14px 16px;">';

    echo '<p class="description" style="margin:0 0 12px;max-width:860px;">' .
        esc_html__('Please complete this once so we have everything needed for year-end 1099 processing. For security, do NOT enter SSN/EIN on this website — we only collect your W-9 as a file upload.', 'vms') .
        '</p>';

    echo '<form method="post" enctype="multipart/form-data">';
    wp_nonce_field('vms_vendor_tax_save', 'vms_vendor_tax_nonce');

    echo '<div class="vms-note"><strong>' . esc_html__('Privacy note:', 'vms') . '</strong> ' .
        esc_html__('Do not type SSN/EIN here. Upload your signed W-9 PDF/image instead.', 'vms') .
        '</div>';

    echo '<h3 style="margin:14px 0 8px;">' . esc_html__('Payee & Entity', 'vms') . '</h3>';

    echo '<div class="vms-grid">';

    echo '<div class="vms-field full">';
    echo '<label for="vms_payee_legal_name">' . esc_html__('Legal / Payee Name (as on W-9)', 'vms') . '</label>';
    echo '<input type="text" id="vms_payee_legal_name" name="vms_payee_legal_name" value="' . esc_attr($payee_legal) . '" required>';
    echo '<div class="vms-help">' . esc_html__('This is the name we should pay and report.', 'vms') . '</div>';
    echo '</div>';

    echo '<div class="vms-field full">';
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

    echo '</div>'; // grid

    echo '<h3 style="margin:18px 0 8px;">' . esc_html__('Mailing Address', 'vms') . '</h3>';

    echo '<div class="vms-grid">';

    echo '<div class="vms-field full">';
    echo '<label for="vms_addr1">' . esc_html__('Address Line 1', 'vms') . '</label>';
    echo '<input type="text" id="vms_addr1" name="vms_addr1" value="' . esc_attr($addr1) . '" required>';
    echo '</div>';

    echo '<div class="vms-field full">';
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

    echo '</div>'; // grid

    echo '<h3 style="margin:18px 0 8px;">' . esc_html__('Upload Signed W-9', 'vms') . '</h3>';

    if ($w9_upload_id > 0 && $w9_url) {
        echo '<p class="description" style="margin:0 0 10px;">' .
            esc_html__('On file:', 'vms') . ' <a href="' . esc_url($w9_url) . '" target="_blank" rel="noopener">' . esc_html($w9_label ? $w9_label : __('View uploaded W-9', 'vms')) . '</a>' .
            '</p>';
    } else {
        echo '<p class="description" style="margin:0 0 10px;">' .
            esc_html__('No W-9 uploaded yet. Please upload a signed W-9 PDF/image.', 'vms') .
            '</p>';
    }

    echo '<p style="margin:0 0 10px;">';
    echo '<input type="file" name="vms_w9_upload" accept="application/pdf,image/jpeg,image/png,image/webp">';
    echo '<div class="vms-help">' . esc_html__('Accepted: PDF, JPG, PNG, WEBP.', 'vms') . '</div>';
    echo '</p>';

    echo '<p style="margin:14px 0 0;">';
    echo '<button type="submit" class="button button-primary" name="vms_vendor_tax_save" value="1">' . esc_html__('Save Tax Profile', 'vms') . '</button>';
    echo '</p>';

    echo '</form>';

    echo '</div></details>';
}

/**
 * OPTIONAL: If you want to enforce completion before booking later,
 * you can use vms_vendor_tax_profile_is_complete($vendor_id) anywhere.
 */
