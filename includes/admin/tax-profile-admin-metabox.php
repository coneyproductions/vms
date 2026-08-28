<?php
if (!defined('ABSPATH')) exit;

/**
 * VMS – Admin Tax Profile Metabox (Vendor + Staff)
 * Lets admins enter/edit tax-profile fields + upload W-9 on behalf of hired people.
 *
 * NO SSN/EIN stored here.
 */
 
add_action('add_meta_boxes', function () {
    $screens = ['vms_vendor', 'vms_staff'];

    foreach ($screens as $screen) {
        add_meta_box(
            'vms_tax_profile_admin_box',
            __('Tax Profile (Admin)', 'backstage-venue-manager'),
            'bvmgr_render_tax_profile_admin_metabox',
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

    $nonce = (isset($_POST['bvmgr_tax_admin_nonce']) && !is_array($_POST['bvmgr_tax_admin_nonce']))
        ? sanitize_text_field(wp_unslash((string) $_POST['bvmgr_tax_admin_nonce']))
        : '';
    if ($nonce === '' || !bvmgr_verify_nonce_compat($nonce, 'bvmgr_tax_admin_save')) {
        return;
    }

    // Staff employees use the Employee Packet workflow; do not save W-9 fields here.
    if ($post->post_type === 'vms_staff') {
        $wt = function_exists('bvmgr_staff_get_worker_type') ? (string) bvmgr_staff_get_worker_type((int) $post_id) : '';
        if ($wt === '') {
            $k_wt = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('staff', 'worker_type') : '_vms_staff_worker_type';
            if ($k_wt === '') $k_wt = '_vms_staff_worker_type';
            $wt = sanitize_key((string) get_post_meta((int) $post_id, $k_wt, true));
        }
        if ($wt === 'employee') {
            return;
        }
    }

    $t = function ($key) {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- This save handler verifies vms_tax_admin_save before reading tax-profile fields.
        return bvmgr_request_read_text_field($_POST, (string) $key);
    };
    $k = function ($field, $fallback) {
        if (!function_exists('bvmgr_meta_key')) {
            return $fallback;
        }
        $mapped = (string) bvmgr_meta_key('vendor', $field);
        return ($mapped !== '') ? $mapped : $fallback;
    };

    $k_payee_legal = $k('payee_legal_name', '_vms_payee_legal_name');
    $k_payee_dba   = $k('payee_dba', '_vms_payee_dba');
    $k_entity      = $k('entity_type', '_vms_entity_type');
    $k_addr1       = $k('addr1', '_vms_addr1');
    $k_addr2       = $k('addr2', '_vms_addr2');
    $k_city        = $k('city', '_vms_city');
    $k_state       = $k('state', '_vms_state');
    $k_zip         = $k('zip', '_vms_zip');
    $k_w9_upload   = $k('w9_upload_id', '_vms_w9_upload_id');
    $k_w9_upload_kind = function_exists('bvmgr_private_w9_storage_kind_meta_key') ? bvmgr_private_w9_storage_kind_meta_key() : '_vms_w9_upload_storage_kind';
    $k_w9_recv     = $k('w9_received_date', '_vms_w9_received_date');
    $k_done        = $k('tax_profile_completed_at', '_vms_tax_profile_completed_at');

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

    update_post_meta($post_id, $k_payee_legal, $payee_legal);
    update_post_meta($post_id, $k_payee_dba, $dba);
    update_post_meta($post_id, $k_entity, $entity);

    update_post_meta($post_id, $k_addr1, $addr1);
    update_post_meta($post_id, $k_addr2, $addr2);
    update_post_meta($post_id, $k_city,  $city);
    update_post_meta($post_id, $k_state, $state);
    update_post_meta($post_id, $k_zip,   $zip);

    // Handle W-9 upload from admin screen
    if (bvmgr_upload_request_has_file($_FILES, 'vms_w9_upload')) {
        $previous_upload_id = (int) get_post_meta($post_id, $k_w9_upload, true);
        $previous_kind = sanitize_key((string) get_post_meta($post_id, $k_w9_upload_kind, true));
        $file_id = function_exists('bvmgr_private_w9_store_upload')
            ? bvmgr_private_w9_store_upload((int) $post_id, $_FILES)
            : new WP_Error('w9_upload_unavailable', __('The W-9 upload handler is unavailable.', 'backstage-venue-manager'));

        if (!is_wp_error($file_id)) {
            update_post_meta($post_id, $k_w9_upload, (int) $file_id);
            update_post_meta($post_id, $k_w9_upload_kind, 'private_file');
            update_post_meta($post_id, $k_w9_recv, wp_date('Y-m-d', time(), wp_timezone()));
            if ($previous_kind === 'private_file' && $previous_upload_id > 0 && $previous_upload_id !== (int) $file_id && function_exists('bvmgr_private_files_delete')) {
                bvmgr_private_files_delete($previous_upload_id);
            }
        }
    }

    // Completion stamp (optional)
    if (function_exists('bvmgr_vendor_tax_profile_is_complete')) {
        if (bvmgr_vendor_tax_profile_is_complete((int)$post_id)) {
            if (!(int) get_post_meta($post_id, $k_done, true)) {
                update_post_meta($post_id, $k_done, time());
            }
        }
    }

}, 20, 2);

function bvmgr_render_tax_profile_admin_metabox($post)
{
    $id = (int) $post->ID;
    $is_staff_employee = false;
    if (($post instanceof WP_Post) && $post->post_type === "vms_staff") {
        $wt = function_exists("bvmgr_staff_get_worker_type") ? (string) bvmgr_staff_get_worker_type($id) : "";
        if ($wt === "") {
            $k_wt = function_exists("bvmgr_meta_key") ? (string) bvmgr_meta_key("staff", "worker_type") : "_vms_staff_worker_type";
            if ($k_wt === "") $k_wt = "_vms_staff_worker_type";
            $wt = sanitize_key((string) get_post_meta($id, $k_wt, true));
        }
        $is_staff_employee = ($wt === "employee");
    }

    $k = function ($field, $fallback) {
        if (!function_exists('bvmgr_meta_key')) {
            return $fallback;
        }
        $mapped = (string) bvmgr_meta_key('vendor', $field);
        return ($mapped !== '') ? $mapped : $fallback;
    };

    $m = function ($key, $default = '') use ($id) {
        $v = get_post_meta($id, $key, true);
        return ($v === '' || $v === null) ? $default : $v;
    };

    $payee_legal = $m($k('payee_legal_name', '_vms_payee_legal_name'));
    $dba         = $m($k('payee_dba', '_vms_payee_dba'));
    $entity      = $m($k('entity_type', '_vms_entity_type'));

    $addr1 = $m($k('addr1', '_vms_addr1'));
    $addr2 = $m($k('addr2', '_vms_addr2'));
    $city  = $m($k('city', '_vms_city'));
    $state = $m($k('state', '_vms_state'));
    $zip   = $m($k('zip', '_vms_zip'));

    $w9_upload_id = (int) $m($k('w9_upload_id', '_vms_w9_upload_id'), 0);
    $w9_url = $w9_upload_id && function_exists('bvmgr_private_w9_download_url') ? bvmgr_private_w9_download_url($id) : '';
    $w9_label = $w9_upload_id && function_exists('bvmgr_private_w9_file_label') ? bvmgr_private_w9_file_label($id) : '';

    $is_complete = function_exists('bvmgr_vendor_tax_profile_is_complete')
        ? bvmgr_vendor_tax_profile_is_complete($id)
        : false;

    // Staff employee: show employee packet status instead of W-9 tax profile UI.
    if ($is_staff_employee) {
        $missing_emp = function_exists('bvmgr_staff_employee_packet_missing_items')
            ? (array) bvmgr_staff_employee_packet_missing_items($id)
            : array(__('W-4 received', 'backstage-venue-manager'), __('I-9 verified', 'backstage-venue-manager'));

        $emp_complete = empty($missing_emp);

        echo '<p class="vms-tax-profile-status">';
        echo $emp_complete
            ? '<span class="vms-badge vms-badge-ok">' . esc_html__('Employee packet complete', 'backstage-venue-manager') . '</span>'
            : '<span class="vms-badge vms-badge-miss">' . esc_html__('Employee packet incomplete', 'backstage-venue-manager') . '</span>';
        echo '</p>';

        echo '<div class="vms-note"><strong>' . esc_html__('W-2 employee:', 'backstage-venue-manager') . '</strong> ' .
            esc_html__('Use the Employee Packet checklist in the sidebar (W-4 + I-9). No SSN is entered into WordPress.', 'backstage-venue-manager') .
            '</div>';

        if (!$emp_complete) {
            echo '<p class="description">' . esc_html__('Missing required items:', 'backstage-venue-manager') . '</p>';
            echo '<ul class="vms-missing">';
            foreach ($missing_emp as $m) {
                echo '<li>' . esc_html((string) $m) . '</li>';
            }
            echo '</ul>';
        }
        return;
    }


    $entity_types = [
        ''            => __('— Select —', 'backstage-venue-manager'),
        'individual'  => __('Individual / Sole Proprietor', 'backstage-venue-manager'),
        'single_llc'  => __('Single-member LLC', 'backstage-venue-manager'),
        'llc'         => __('LLC (multi-member)', 'backstage-venue-manager'),
        'partnership' => __('Partnership', 'backstage-venue-manager'),
        's_corp'      => __('S-Corp', 'backstage-venue-manager'),
        'c_corp'      => __('C-Corp', 'backstage-venue-manager'),
        'nonprofit'   => __('Nonprofit / Exempt', 'backstage-venue-manager'),
        'other'       => __('Other', 'backstage-venue-manager'),
    ];

    wp_nonce_field('bvmgr_tax_admin_save', 'bvmgr_tax_admin_nonce');

    $provider = function_exists('bvmgr_tax_settings_get_provider') ? (string) bvmgr_tax_settings_get_provider() : 'upload';
    if (!in_array($provider, array('upload', 'quickbooks_email', 'tax1099_email'), true)) {
        $provider = 'upload';
    }
    $provider_label = function_exists('bvmgr_tax_provider_label')
        ? (string) bvmgr_tax_provider_label($provider)
        : (($provider === 'quickbooks_email') ? 'QuickBooks Online' : (($provider === 'tax1099_email') ? 'Tax1099' : 'Upload'));

    $k_attest = $k('w9_attested_at', '_vms_w9_external_vendor_attested_at');
    $k_done = $k('tax_profile_completed_at', '_vms_tax_profile_completed_at');
    $attested_at = (int) get_post_meta($id, $k_attest, true);
    $done_at = (int) get_post_meta($id, $k_done, true);

    echo '<div class="vms-tax-profile-admin">';

    echo '<p class="vms-tax-profile-status">';
    if ($is_complete) {
        echo '<span class="vms-badge vms-badge-ok">' . esc_html__('Complete', 'backstage-venue-manager') . '</span>';
    } elseif ($provider !== 'upload' && $attested_at > 0) {
        echo '<span class="vms-badge vms-badge-warn">' . esc_html__('Submitted', 'backstage-venue-manager') . '</span>';
    } else {
        echo '<span class="vms-badge vms-badge-miss">' . esc_html__('Incomplete', 'backstage-venue-manager') . '</span>';
    }
    echo '</p>';

    echo '<div class="vms-note"><strong>' . esc_html__('Active W-9 source of truth:', 'backstage-venue-manager') . '</strong> ' . esc_html($provider_label) . '</div>';
    echo '<div class="vms-note"><strong>' . esc_html__('Privacy note:', 'backstage-venue-manager') . '</strong> ' .
        esc_html__('Do NOT type SSN/EIN into WordPress.', 'backstage-venue-manager') .
        '</div>';

    if ($provider !== 'upload') {
        echo '<p class="description">' . esc_html__('This record uses the secure off-site workflow as the W-9 source of truth. Use the Tax Profile Status box in the sidebar to confirm or clear admin completion without using the temporary bypass.', 'backstage-venue-manager') . '</p>';
        if ($attested_at > 0 && $done_at <= 0) {
            echo '<p class="description">' . esc_html__('Staff/vendor has already indicated they completed the off-site step and is waiting on admin confirmation.', 'backstage-venue-manager') . '</p>';
        }
    }

    echo '<h3 class="vms-tax-profile-subhead vms-tax-profile-subhead-first">' . esc_html__('Payee & Entity', 'backstage-venue-manager') . '</h3>';

    echo '<div class="vms-grid">';
    echo '<div class="vms-field vms-tax-profile-field-full">';
    echo '<label for="vms_payee_legal_name">' . esc_html__('Legal / Payee Name (as on W-9)', 'backstage-venue-manager') . '</label>';
    echo '<input type="text" id="vms_payee_legal_name" name="vms_payee_legal_name" value="' . esc_attr($payee_legal) . '" required>';
    echo '</div>';

    echo '<div class="vms-field vms-tax-profile-field-full">';
    echo '<label for="vms_payee_dba">' . esc_html__('Business Name / DBA (optional)', 'backstage-venue-manager') . '</label>';
    echo '<input type="text" id="vms_payee_dba" name="vms_payee_dba" value="' . esc_attr($dba) . '">';
    echo '</div>';

    echo '<div class="vms-field">';
    echo '<label for="vms_entity_type">' . esc_html__('Entity Type', 'backstage-venue-manager') . '</label>';
    echo '<select id="vms_entity_type" name="vms_entity_type" required>';
    foreach ($entity_types as $k => $label) {
        echo '<option value="' . esc_attr($k) . '" ' . selected($entity, $k, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select>';
    echo '</div>';
    echo '</div>';

    echo '<h3 class="vms-tax-profile-subhead">' . esc_html__('Mailing Address', 'backstage-venue-manager') . '</h3>';

    echo '<div class="vms-grid">';
    echo '<div class="vms-field vms-tax-profile-field-full">';
    echo '<label for="vms_addr1">' . esc_html__('Address Line 1', 'backstage-venue-manager') . '</label>';
    echo '<input type="text" id="vms_addr1" name="vms_addr1" value="' . esc_attr($addr1) . '" required>';
    echo '</div>';

    echo '<div class="vms-field vms-tax-profile-field-full">';
    echo '<label for="vms_addr2">' . esc_html__('Address Line 2 (optional)', 'backstage-venue-manager') . '</label>';
    echo '<input type="text" id="vms_addr2" name="vms_addr2" value="' . esc_attr($addr2) . '">';
    echo '</div>';

    echo '<div class="vms-field">';
    echo '<label for="vms_city">' . esc_html__('City', 'backstage-venue-manager') . '</label>';
    echo '<input type="text" id="vms_city" name="vms_city" value="' . esc_attr($city) . '" required>';
    echo '</div>';

    echo '<div class="vms-field">';
    echo '<label for="vms_state">' . esc_html__('State', 'backstage-venue-manager') . '</label>';
    echo '<input type="text" id="vms_state" name="vms_state" value="' . esc_attr($state) . '" maxlength="2" placeholder="TX" required>';
    echo '</div>';

    echo '<div class="vms-field">';
    echo '<label for="vms_zip">' . esc_html__('ZIP', 'backstage-venue-manager') . '</label>';
    echo '<input type="text" id="vms_zip" name="vms_zip" value="' . esc_attr($zip) . '" required>';
    echo '</div>';
    echo '</div>';

    echo '<h3 class="vms-tax-profile-subhead">' . esc_html__('W-9', 'backstage-venue-manager') . ' (' . esc_html($provider_label) . ')</h3>';

    if ($w9_upload_id > 0 && $w9_url) {
        echo '<p class="description vms-tax-profile-upload-note">' .
            esc_html__('On file:', 'backstage-venue-manager') . ' <a href="' . esc_url($w9_url) . '" target="_blank" rel="noopener">' .
            esc_html($w9_label ? $w9_label : __('View uploaded W-9', 'backstage-venue-manager')) .
            '</a></p>';
    }

    if ($provider === 'upload') {
        if (!($w9_upload_id > 0 && $w9_url)) {
            echo '<p class="description vms-tax-profile-upload-note">' .
                esc_html__('No W-9 uploaded yet. Upload a signed W-9 PDF/image.', 'backstage-venue-manager') .
                '</p>';
        }
        echo '<p class="vms-tax-profile-upload-field">';
        echo '<input type="file" name="vms_w9_upload" accept="application/pdf,image/jpeg,image/png,image/webp">';
        echo '<div class="vms-help">' . esc_html__('Accepted: PDF, JPG, PNG, WEBP.', 'backstage-venue-manager') . '</div>';
        echo '</p>';
    } else {
        echo '<p class="description">' . esc_html__('Primary workflow is off-site. Uploading a file here is optional and is not the source of truth for this mode.', 'backstage-venue-manager') . '</p>';
        if ($attested_at > 0) {
            echo '<p class="description">' . esc_html__('Portal confirmation recorded:', 'backstage-venue-manager') . ' ' . esc_html(wp_date('M j, Y g:ia', $attested_at, wp_timezone())) . '</p>';
        }
        if ($done_at > 0) {
            echo '<p class="description">' . esc_html__('Completion recorded:', 'backstage-venue-manager') . ' ' . esc_html(wp_date('M j, Y g:ia', $done_at, wp_timezone())) . '</p>';
        }
    }

    echo '<p class="description">' .
        esc_html__('Save/Update the post to store changes.', 'backstage-venue-manager') .
        '</p>';
    echo '</div>';
}
