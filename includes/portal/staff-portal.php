<?php
if (!defined('ABSPATH')) exit;

/**
 * VMS – Staff Portal Shortcode
 * Uses user_meta: _vms_staff_id (links WP user to a vms_staff post)
 *
 * Tabs:
 *  - dashboard
 *  - tax-profile (required)
 *  - availability (manual only)
 */

add_shortcode('vms_staff_portal', 'vms_staff_portal_shortcode');

function vms_staff_portal_shortcode()
{
    $base_url = get_permalink();

    $url_dashboard    = add_query_arg('tab', 'dashboard', $base_url);
    $url_tax_profile  = add_query_arg('tab', 'tax-profile', $base_url);
    $url_availability = add_query_arg('tab', 'availability', $base_url);

    if (!is_user_logged_in()) {
        ob_start();
        echo '<p>' . esc_html__('Please log in to access the staff portal.', 'vms') . '</p>';
        echo wp_login_form(array('echo' => false, 'redirect' => esc_url(get_permalink())));
        return ob_get_clean();
    }

    $user_id  = get_current_user_id();
    $staff_id = (int) get_user_meta($user_id, '_vms_staff_id', true);

    if (!$staff_id) {
        return '<p>' . esc_html__('Your account is not linked to a staff profile yet. Please contact the admin.', 'vms') . '</p>';
    }

    $staff = get_post($staff_id);
    if (!$staff || $staff->post_type !== 'vms_staff') {
        return '<p>' . esc_html__('Your linked staff profile could not be found. Please contact the admin.', 'vms') . '</p>';
    }

    $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'dashboard';

    ob_start();

    echo '<style>
.vms-portal-card{background:#fff;border:1px solid #e5e5e5;border-radius:14px;padding:14px;margin:0 0 14px;}
.vms-portal-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;}
@media (max-width:820px){.vms-portal-grid{grid-template-columns:1fr;}}
.vms-muted{opacity:.8}
.vms-portal-nav a{margin-right:12px;}
</style>';

    echo '<div class="vms-portal">';
    echo '<h2>' . esc_html__('Staff Portal:', 'vms') . ' ' . esc_html($staff->post_title) . '</h2>';

    echo '<nav class="vms-portal-nav" style="margin:12px 0;">';
    echo '<a href="' . esc_url($url_dashboard) . '">' . esc_html__('Dashboard', 'vms') . '</a>';
    echo '<a href="' . esc_url($url_tax_profile) . '">' . esc_html__('Tax Profile', 'vms') . '</a>';
    echo '<a href="' . esc_url($url_availability) . '">' . esc_html__('Availability', 'vms') . '</a>';
    echo '</nav>';

    if ($tab === 'dashboard') {
        $missing = function_exists('vms_vendor_tax_profile_missing_items') ? vms_vendor_tax_profile_missing_items($staff_id) : array();
        $is_complete = empty($missing);

        echo '<div class="vms-portal-card">';
        echo '<p class="vms-muted">' . esc_html__('Use the tabs to complete your info.', 'vms') . '</p>';
        echo '</div>';

        echo '<div class="vms-portal-grid">';

        echo '<div class="vms-portal-card">
  <h3 style="margin-top:0;">' . esc_html__('Tax Profile', 'vms') . '</h3>
  <p class="vms-muted">' . ($is_complete ? esc_html__('✅ Complete', 'vms') : esc_html__('⚠️ Incomplete (required)', 'vms')) . '</p>
  <p><a class="button" href="' . esc_url($url_tax_profile) . '">' . esc_html__('Open Tax Profile', 'vms') . '</a></p>
</div>';

        echo '<div class="vms-portal-card">
  <h3 style="margin-top:0;">' . esc_html__('Availability', 'vms') . '</h3>
  <p class="vms-muted">' . esc_html__('Mark dates you can work.', 'vms') . '</p>
  <p><a class="button" href="' . esc_url($url_availability) . '">' . esc_html__('Update Availability', 'vms') . '</a></p>
</div>';

        echo '<div class="vms-portal-card">
  <h3 style="margin-top:0;">' . esc_html__('Notes', 'vms') . '</h3>
  <p class="vms-muted">' . esc_html__('If you need changes, contact the venue admin.', 'vms') . '</p>
</div>';

        echo '</div>';

    } elseif ($tab === 'tax-profile') {

        // Uses same meta keys as your vendor tax profile:
        // _vms_payee_legal_name, _vms_payee_dba, _vms_entity_type
        // _vms_addr1/_vms_addr2/_vms_city/_vms_state/_vms_zip
        // _vms_w9_upload_id
        vms_staff_portal_render_tax_profile($staff_id);

    } elseif ($tab === 'availability') {

        vms_staff_portal_render_availability_manual($staff_id);

    } else {
        echo '<p>' . esc_html__('Unknown tab.', 'vms') . '</p>';
    }

    echo '</div>';
    return ob_get_clean();
}

/**
 * Staff Tax Profile (same safe approach you chose for vendors).
 * - NO SSN/EIN typed into site
 * - Collect payee, address, entity, and require W-9 upload
 */
function vms_staff_portal_render_tax_profile($staff_id)
{
    $staff_id = (int) $staff_id;

    // Ensure media handling exists on front-end.
    if (!function_exists('media_handle_upload')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vms_staff_tax_save'])) {

        if (
            empty($_POST['vms_staff_tax_nonce']) ||
            !wp_verify_nonce($_POST['vms_staff_tax_nonce'], 'vms_staff_tax_save')
        ) {
            echo function_exists('vms_portal_notice')
                ? vms_portal_notice('error', __('Security check failed.', 'vms'))
                : '<p>' . esc_html__('Security check failed.', 'vms') . '</p>';
        } else {

            $t = function ($key) {
                return isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : '';
            };

            $payee_legal = $t('vms_payee_legal_name');
            $dba         = $t('vms_payee_dba');
            $entity      = $t('vms_entity_type');

            $addr1 = $t('vms_addr1');
            $addr2 = $t('vms_addr2');
            $city  = $t('vms_city');
            $state = strtoupper($t('vms_state'));
            $zip   = $t('vms_zip');

            if (strlen($state) > 2) $state = substr($state, 0, 2);

            update_post_meta($staff_id, '_vms_payee_legal_name', $payee_legal);
            update_post_meta($staff_id, '_vms_payee_dba', $dba);
            update_post_meta($staff_id, '_vms_entity_type', $entity);

            update_post_meta($staff_id, '_vms_addr1', $addr1);
            update_post_meta($staff_id, '_vms_addr2', $addr2);
            update_post_meta($staff_id, '_vms_city',  $city);
            update_post_meta($staff_id, '_vms_state', $state);
            update_post_meta($staff_id, '_vms_zip',   $zip);

            // W-9 upload required
            if (!empty($_FILES['vms_w9_upload']['name'])) {
                $allowed = array('application/pdf', 'image/jpeg', 'image/png', 'image/webp');
                $type = isset($_FILES['vms_w9_upload']['type']) ? (string) $_FILES['vms_w9_upload']['type'] : '';

                if ($type && !in_array($type, $allowed, true)) {
                    echo function_exists('vms_portal_notice')
                        ? vms_portal_notice('error', __('Upload must be a PDF or image (JPG/PNG/WEBP).', 'vms'))
                        : '<p>' . esc_html__('Upload must be a PDF or image.', 'vms') . '</p>';
                } else {
                    $attach_id = media_handle_upload('vms_w9_upload', 0);
                    if (is_wp_error($attach_id)) {
                        echo function_exists('vms_portal_notice')
                            ? vms_portal_notice('error', __('W-9 upload failed: ', 'vms') . $attach_id->get_error_message())
                            : '<p>' . esc_html__('W-9 upload failed.', 'vms') . '</p>';
                    } else {
                        update_post_meta($staff_id, '_vms_w9_upload_id', (int) $attach_id);
                        update_post_meta($staff_id, '_vms_w9_received_date', date('Y-m-d'));
                    }
                }
            }

            // Completion stamp
            if (function_exists('vms_vendor_tax_profile_is_complete') && vms_vendor_tax_profile_is_complete($staff_id)) {
                if (!(int) get_post_meta($staff_id, '_vms_tax_profile_completed_at', true)) {
                    update_post_meta($staff_id, '_vms_tax_profile_completed_at', time());
                }
            }

            echo function_exists('vms_portal_notice')
                ? vms_portal_notice('success', __('Tax Profile saved.', 'vms'))
                : '<p>' . esc_html__('Tax Profile saved.', 'vms') . '</p>';
        }
    }

    $m = function ($key, $default = '') use ($staff_id) {
        $v = get_post_meta($staff_id, $key, true);
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

    $w9_upload_id = (int) get_post_meta($staff_id, '_vms_w9_upload_id', true);
    $w9_url   = $w9_upload_id ? wp_get_attachment_url($w9_upload_id) : '';
    $w9_label = $w9_upload_id ? get_the_title($w9_upload_id) : '';

    $missing = function_exists('vms_vendor_tax_profile_missing_items') ? vms_vendor_tax_profile_missing_items($staff_id) : array();
    $is_complete = empty($missing);

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
.vms-field input[type="text"],.vms-field input[type="file"],.vms-field select{width:100%;max-width:520px;}
.vms-help{margin:6px 0 0;color:#6b7280;font-size:12px;}
.vms-note{background:#f8fafc;border:1px solid #e5e7eb;border-radius:12px;padding:10px 12px;margin:10px 0 0;max-width:860px;}
</style>';

    echo '<details class="vms-panel" open>';
    echo '<summary class="vms-panel-summary">';
    echo '<span>' . esc_html__('Tax Profile (Required)', 'vms') . '</span>';
    echo $is_complete
        ? '<span class="vms-badge vms-badge-ok">' . esc_html__('Complete', 'vms') . '</span>'
        : '<span class="vms-badge vms-badge-miss">' . esc_html__('Incomplete', 'vms') . '</span>';
    echo '</summary>';

    echo '<div style="padding:14px 14px 16px;">';

    if (!$is_complete && !empty($missing)) {
        echo '<div class="vms-note"><strong>' . esc_html__('Missing:', 'vms') . '</strong> ' . esc_html(implode(', ', $missing)) . '</div>';
    }

    echo '<p class="description" style="margin:0 0 12px;max-width:860px;">' .
        esc_html__('Please complete this once so we have everything needed for year-end 1099 processing. For security, do NOT enter SSN/EIN on this website — upload your signed W-9 PDF/image instead.', 'vms') .
        '</p>';

    echo '<form method="post" enctype="multipart/form-data">';
    wp_nonce_field('vms_staff_tax_save', 'vms_staff_tax_nonce');

    echo '<div class="vms-note"><strong>' . esc_html__('Privacy note:', 'vms') . '</strong> ' .
        esc_html__('Do not type SSN/EIN here. Upload your signed W-9 PDF/image instead.', 'vms') .
        '</div>';

    echo '<h3 style="margin:14px 0 8px;">' . esc_html__('Payee & Entity', 'vms') . '</h3>';
    echo '<div class="vms-grid">';

    echo '<div class="vms-field full">';
    echo '<label for="vms_payee_legal_name">' . esc_html__('Legal / Payee Name (as on W-9)', 'vms') . ' *</label>';
    echo '<input type="text" id="vms_payee_legal_name" name="vms_payee_legal_name" value="' . esc_attr($payee_legal) . '" required>';
    echo '</div>';

    echo '<div class="vms-field full">';
    echo '<label for="vms_payee_dba">' . esc_html__('Business Name / DBA (optional)', 'vms') . '</label>';
    echo '<input type="text" id="vms_payee_dba" name="vms_payee_dba" value="' . esc_attr($dba) . '">';
    echo '</div>';

    echo '<div class="vms-field">';
    echo '<label for="vms_entity_type">' . esc_html__('Entity Type', 'vms') . ' *</label>';
    echo '<select id="vms_entity_type" name="vms_entity_type" required>';
    foreach ($entity_types as $k => $label) {
        echo '<option value="' . esc_attr($k) . '" ' . selected($entity, $k, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select>';
    echo '</div>';

    echo '</div>';

    echo '<h3 style="margin:18px 0 8px;">' . esc_html__('Mailing Address', 'vms') . '</h3>';
    echo '<div class="vms-grid">';

    echo '<div class="vms-field full">';
    echo '<label for="vms_addr1">' . esc_html__('Address Line 1', 'vms') . ' *</label>';
    echo '<input type="text" id="vms_addr1" name="vms_addr1" value="' . esc_attr($addr1) . '" required>';
    echo '</div>';

    echo '<div class="vms-field full">';
    echo '<label for="vms_addr2">' . esc_html__('Address Line 2 (optional)', 'vms') . '</label>';
    echo '<input type="text" id="vms_addr2" name="vms_addr2" value="' . esc_attr($addr2) . '">';
    echo '</div>';

    echo '<div class="vms-field">';
    echo '<label for="vms_city">' . esc_html__('City', 'vms') . ' *</label>';
    echo '<input type="text" id="vms_city" name="vms_city" value="' . esc_attr($city) . '" required>';
    echo '</div>';

    echo '<div class="vms-field">';
    echo '<label for="vms_state">' . esc_html__('State', 'vms') . ' *</label>';
    echo '<input type="text" id="vms_state" name="vms_state" value="' . esc_attr($state) . '" maxlength="2" placeholder="TX" required>';
    echo '</div>';

    echo '<div class="vms-field">';
    echo '<label for="vms_zip">' . esc_html__('ZIP', 'vms') . ' *</label>';
    echo '<input type="text" id="vms_zip" name="vms_zip" value="' . esc_attr($zip) . '" required>';
    echo '</div>';

    echo '</div>';

    echo '<h3 style="margin:18px 0 8px;">' . esc_html__('Upload Signed W-9', 'vms') . ' *</h3>';

    if ($w9_upload_id > 0 && $w9_url) {
        echo '<p class="description" style="margin:0 0 10px;">' .
            esc_html__('On file:', 'vms') . ' <a href="' . esc_url($w9_url) . '" target="_blank" rel="noopener">' .
            esc_html($w9_label ? $w9_label : __('View uploaded W-9', 'vms')) . '</a></p>';
    } else {
        echo '<p class="description" style="margin:0 0 10px;">' .
            esc_html__('No W-9 uploaded yet. Please upload a signed W-9 PDF/image.', 'vms') . '</p>';
    }

    echo '<p style="margin:0 0 10px;">';
    echo '<input type="file" name="vms_w9_upload" accept="application/pdf,image/jpeg,image/png,image/webp">';
    echo '<div class="vms-help">' . esc_html__('Accepted: PDF, JPG, PNG, WEBP.', 'vms') . '</div>';
    echo '</p>';

    echo '<p style="margin:14px 0 0;">';
    echo '<button type="submit" class="button button-primary" name="vms_staff_tax_save" value="1">' . esc_html__('Save Tax Profile', 'vms') . '</button>';
    echo '</p>';

    echo '</form>';
    echo '</div></details>';
}

/**
 * Manual-only Availability (reuses your season dates + the same meta keys as vendors).
 * Saves into: _vms_availability_manual (array date => available/unavailable)
 */
function vms_staff_portal_render_availability_manual($staff_id)
{
    $staff_id = (int) $staff_id;

    $active_dates = get_option('vms_active_dates', array());
    $manual = get_post_meta($staff_id, '_vms_availability_manual', true);
    if (!is_array($manual)) $manual = array();

    // Save
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vms_staff_save_availability'])) {

        if (empty($_POST['vms_staff_avail_nonce']) || !wp_verify_nonce($_POST['vms_staff_avail_nonce'], 'vms_staff_save_availability')) {
            echo function_exists('vms_portal_notice')
                ? vms_portal_notice('error', __('Security check failed.', 'vms'))
                : '<p>' . esc_html__('Security check failed.', 'vms') . '</p>';
        } else {
            $incoming = isset($_POST['vms_availability']) && is_array($_POST['vms_availability']) ? $_POST['vms_availability'] : array();
            $clean = array();

            foreach ($incoming as $date => $state) {
                $date  = sanitize_text_field($date);
                $state = sanitize_text_field($state);
                if ($state === 'available' || $state === 'unavailable') {
                    $clean[$date] = $state;
                }
            }

            update_post_meta($staff_id, '_vms_availability_manual', $clean);
            $manual = $clean;

            echo function_exists('vms_portal_notice')
                ? vms_portal_notice('success', __('Availability saved.', 'vms'))
                : '<p>' . esc_html__('Availability saved.', 'vms') . '</p>';
        }
    }

    if (empty($active_dates)) {
        echo '<p>' . esc_html__('No season dates are configured yet.', 'vms') . '</p>';
        return;
    }

    // UI: same collapse-by-month style you like
    echo '<style>
.vms-panel{border-radius:14px;border:1px solid #e5e5e5;background:#fff;margin:12px 0;}
.vms-panel > summary{list-style:none;}
.vms-panel > summary::-webkit-details-marker{display:none;}
.vms-panel-summary{cursor:pointer;font-weight:800;font-size:15px;padding:12px 14px;}
</style>';

    echo '<details class="vms-panel" open>';
    echo '<summary class="vms-panel-summary">' . esc_html__('Availability', 'vms') . '</summary>';
    echo '<div style="padding:14px 14px 16px;">';

    echo '<p class="description" style="margin:0 0 12px;max-width:860px;">' .
        esc_html__('Mark the dates you are available or unavailable to work.', 'vms') .
        '</p>';

    echo '<form method="post">';
    wp_nonce_field('vms_staff_save_availability', 'vms_staff_avail_nonce');

    // Group by month
    $dates_by_month = array();
    foreach ($active_dates as $date_str) {
        $ts = strtotime($date_str);
        if (!$ts) continue;
        $month_label = date_i18n('F Y', $ts);
        if (!isset($dates_by_month[$month_label])) $dates_by_month[$month_label] = array();
        $dates_by_month[$month_label][] = $date_str;
    }

    $current_month_label = date_i18n('F Y', current_time('timestamp'));
    $has_current_month   = isset($dates_by_month[$current_month_label]);
    $opened_first        = false;

    echo '<p style="margin:8px 0 14px;">
  <button type="button" class="button" onclick="document.querySelectorAll(\'.vms-portal-month\').forEach(d=>d.open=true);">' . esc_html__('Expand all', 'vms') . '</button>
  <button type="button" class="button" onclick="document.querySelectorAll(\'.vms-portal-month\').forEach(d=>d.open=false);">' . esc_html__('Collapse all', 'vms') . '</button>
</p>';

    foreach ($dates_by_month as $month_label => $month_dates) {

        $open_attr = '';
        if ($has_current_month) {
            if ($month_label === $current_month_label) $open_attr = ' open';
        } else {
            if (!$opened_first) { $open_attr = ' open'; $opened_first = true; }
        }

        echo '<details class="vms-portal-month"' . $open_attr . ' style="margin:0 0 14px;background:#fff;border:1px solid #e5e5e5;border-radius:14px;padding:10px 12px;">';
        echo '<summary style="cursor:pointer;font-weight:700;font-size:15px;">' . esc_html($month_label) . '</summary>';

        echo '<table class="widefat striped" style="margin-top:10px;">';
        echo '<thead><tr>
            <th>' . esc_html__('Date', 'vms') . '</th>
            <th>' . esc_html__('Day', 'vms') . '</th>
            <th>' . esc_html__('Status', 'vms') . '</th>
        </tr></thead><tbody>';

        foreach ($month_dates as $date_str) {
            $ts = strtotime($date_str);
            if (!$ts) continue;

            $day  = date_i18n('D', $ts);
            $nice = date_i18n('M j, Y', $ts);

            $val = '';
            if (isset($manual[$date_str]) && ($manual[$date_str] === 'available' || $manual[$date_str] === 'unavailable')) {
                $val = $manual[$date_str];
            }

            echo '<tr>';
            echo '<td>' . esc_html($nice) . '</td>';
            echo '<td>' . esc_html($day) . '</td>';
            echo '<td>';
            echo '<select name="vms_availability[' . esc_attr($date_str) . ']">';
            echo '<option value="" ' . selected($val, '', false) . '>' . esc_html__('—', 'vms') . '</option>';
            echo '<option value="available" ' . selected($val, 'available', false) . '>' . esc_html__('Available', 'vms') . '</option>';
            echo '<option value="unavailable" ' . selected($val, 'unavailable', false) . '>' . esc_html__('Not Available', 'vms') . '</option>';
            echo '</select>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</details>';
    }

    echo '<p style="margin:14px 0 0;">
        <button type="submit" class="button button-primary" name="vms_staff_save_availability" value="1">' . esc_html__('Save Availability', 'vms') . '</button>
    </p>';

    echo '</form>';
    echo '</div></details>';
}