<?php

add_shortcode('vms_vendor_portal', 'vms_vendor_portal_shortcode');

function vms_vendor_portal_shortcode()
{
    if (!is_user_logged_in()) {
        // Use WP's login form and redirect back here
        return wp_login_form(array(
            'echo'     => false,
            'redirect' => esc_url(get_permalink()),
        ));
    }

    $user_id   = get_current_user_id();
    $vendor_id = (int) get_user_meta($user_id, '_vms_vendor_id', true);

    if (!$vendor_id) {
        return '<p>Your account is not linked to a vendor profile yet. Please contact the venue admin.</p>';
    }

    $vendor = get_post($vendor_id);
    if (!$vendor || $vendor->post_type !== 'vms_vendor') {
        return '<p>Your linked vendor profile could not be found. Please contact the venue admin.</p>';
    }

    // Simple tab routing
    $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'dashboard';

    ob_start();
    echo '<div class="vms-portal">';
    echo '<h2>Vendor Portal: ' . esc_html($vendor->post_title) . '</h2>';

    echo '<nav style="margin:12px 0;">';
    echo '<a style="margin-right:10px;" href="' . esc_url(add_query_arg('tab', 'dashboard')) . '">Dashboard</a>';
    echo '<a style="margin-right:10px;" href="' . esc_url(add_query_arg('tab', 'profile')) . '">Profile</a>';
    echo '<a style="margin-right:10px;" href="' . esc_url(add_query_arg('tab', 'availability')) . '">Availability</a>';
    echo '<a style="margin-right:10px;" href="' . esc_url(add_query_arg('tab', 'tech')) . '">Tech Docs</a>';
    echo '</nav>';

    if ($tab === 'profile') {
        if (function_exists('vms_vendor_portal_render_profile')) {
            vms_vendor_portal_render_profile($vendor_id);
        } else {
            echo '<p><strong>Profile module is not loaded.</strong> (Missing vms_vendor_portal_render_profile)</p>';
        }
    } elseif ($tab === 'availability') {
        vms_vendor_portal_render_availability($vendor_id);
    } elseif ($tab === 'tech') {
        if (function_exists('vms_vendor_portal_render_tech_docs')) {
            vms_vendor_portal_render_tech_docs($vendor_id);
        } else {
            echo '<p><strong>Tech Docs module is not loaded.</strong> (Missing vms_vendor_portal_render_tech_docs)</p>';
        }
    } else {
        echo '<p>Choose a tab to update your information.</p>';
    }

    echo '</div>';
    return ob_get_clean();
}

function vms_vendor_portal_render_availability($vendor_id)
{
    $active_dates = get_option('vms_active_dates', array());
    $availability = get_post_meta($vendor_id, '_vms_availability', true);
    if (!is_array($availability)) $availability = array();

    // Handle save
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vms_save_availability'])) {
        if (!isset($_POST['vms_avail_nonce']) || !wp_verify_nonce($_POST['vms_avail_nonce'], 'vms_save_availability')) {
            echo '<p>Security check failed.</p>';
            return;
        }

        $incoming = isset($_POST['vms_availability']) && is_array($_POST['vms_availability']) ? $_POST['vms_availability'] : array();
        $clean = array();

        foreach ($incoming as $date => $state) {
            $date  = sanitize_text_field($date);
            $state = sanitize_text_field($state);
            if ($state === 'available' || $state === 'unavailable') {
                $clean[$date] = $state;
            }
        }

        update_post_meta($vendor_id, '_vms_availability', $clean);
        $availability = $clean;

        echo '<div class="notice notice-success"><p>Availability saved.</p></div>';
    }

    if (empty($active_dates)) {
        echo '<p>No season dates have been configured yet.</p>';
        return;
    }

    echo '<h3>Availability</h3>';
    echo '<form method="post">';
    wp_nonce_field('vms_save_availability', 'vms_avail_nonce');

    echo '<table class="widefat striped" style="max-width:750px;">';
    echo '<thead><tr><th>Date</th><th>Day</th><th>Status</th></tr></thead><tbody>';

    foreach ($active_dates as $date_str) {
        $ts   = strtotime($date_str);
        $day  = $ts ? date_i18n('D', $ts) : '';
        $nice = $ts ? date_i18n('M j, Y', $ts) : $date_str;
        $val  = isset($availability[$date_str]) ? $availability[$date_str] : '';

        echo '<tr>';
        echo '<td>' . esc_html($nice) . '</td>';
        echo '<td>' . esc_html($day) . '</td>';
        echo '<td>';
        echo '<select name="vms_availability[' . esc_attr($date_str) . ']">';
        echo '<option value="" ' . selected($val, '', false) . '>-- Unknown --</option>';
        echo '<option value="available" ' . selected($val, 'available', false) . '>Available</option>';
        echo '<option value="unavailable" ' . selected($val, 'unavailable', false) . '>Not Available</option>';
        echo '</select>';
        echo '</td></tr>';
    }

    echo '</tbody></table>';

    echo '<p style="margin-top:12px;"><button class="button button-primary" type="submit" name="vms_save_availability">Save Availability</button></p>';
    echo '</form>';
}

function vms_vendor_portal_render_profile($vendor_id)
{
    $vendor_id = (int) $vendor_id;

    // Ensure media handling functions are available on front-end
    if (!function_exists('media_handle_upload')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }

    // Save handler
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vms_vendor_profile_save'])) {
        if (
            !isset($_POST['vms_vendor_profile_nonce']) ||
            !wp_verify_nonce($_POST['vms_vendor_profile_nonce'], 'vms_vendor_profile_save')
        ) {
            echo '<div class="notice notice-error"><p>Security check failed.</p></div>';
        } else {
            $contact_name  = isset($_POST['vms_contact_name']) ? sanitize_text_field($_POST['vms_contact_name']) : '';
            $contact_email = isset($_POST['vms_contact_email']) ? sanitize_email($_POST['vms_contact_email']) : '';
            $contact_phone = isset($_POST['vms_contact_phone']) ? sanitize_text_field($_POST['vms_contact_phone']) : '';
            $location      = isset($_POST['vms_location']) ? sanitize_text_field($_POST['vms_location']) : '';
            $epk_url       = isset($_POST['vms_epk_url']) ? esc_url_raw($_POST['vms_epk_url']) : '';
            $social_links  = isset($_POST['vms_social_links']) ? sanitize_textarea_field($_POST['vms_social_links']) : '';

            // Logo upload (sets Vendor featured image)
            if (!empty($_FILES['vms_vendor_logo']['name'])) {
                $attach_id = media_handle_upload('vms_vendor_logo', 0);
                if (!is_wp_error($attach_id)) {
                    set_post_thumbnail($vendor_id, (int) $attach_id);
                } else {
                    echo '<div class="notice notice-error"><p>Logo upload failed: ' . esc_html($attach_id->get_error_message()) . '</p></div>';
                }
            }

            // Store in vendor meta (adjust keys later if you already have a schema)
            update_post_meta($vendor_id, '_vms_contact_name', $contact_name);
            update_post_meta($vendor_id, '_vms_contact_email', $contact_email);
            update_post_meta($vendor_id, '_vms_contact_phone', $contact_phone);
            update_post_meta($vendor_id, '_vms_vendor_location', $location);
            update_post_meta($vendor_id, '_vms_vendor_epk', $epk_url);
            update_post_meta($vendor_id, '_vms_vendor_social', $social_links);

            echo '<div class="notice notice-success"><p>Profile saved.</p></div>';
        }
    }

    // Current values
    $contact_name  = get_post_meta($vendor_id, '_vms_contact_name', true);
    $contact_email = get_post_meta($vendor_id, '_vms_contact_email', true);
    $contact_phone = get_post_meta($vendor_id, '_vms_contact_phone', true);
    $location      = get_post_meta($vendor_id, '_vms_vendor_location', true);
    $epk_url       = get_post_meta($vendor_id, '_vms_vendor_epk', true);
    $social_links  = get_post_meta($vendor_id, '_vms_vendor_social', true);

    $thumb_id  = get_post_thumbnail_id($vendor_id);
    $thumb_url = $thumb_id ? wp_get_attachment_image_url($thumb_id, 'medium') : '';


    echo '<h3>Profile</h3>';

    echo '<form method="post" enctype="multipart/form-data">';
    wp_nonce_field('vms_vendor_profile_save', 'vms_vendor_profile_nonce');

    // LOGO
    echo '<h4 style="margin-top:18px;">Logo</h4>';

    if ($thumb_url) {
        echo '<p><img src="' . esc_url($thumb_url) . '" alt="Vendor Logo" style="max-width:180px;height:auto;border:1px solid #ddd;border-radius:8px;padding:6px;background:#fff;"></p>';
    } else {
        echo '<p><em>No logo uploaded yet.</em></p>';
    }

    echo '<p><label><strong>Upload / Replace Logo</strong></label><br>';
    echo '<input type="file" name="vms_vendor_logo" accept=".png,.jpg,.jpeg,.webp,.pdf"></p>';
    // /LOGO

    echo '<p><label><strong>Contact Name</strong></label><br>';
    echo '<input type="text" name="vms_contact_name" value="' . esc_attr($contact_name) . '" style="width:100%;max-width:520px;"></p>';

    echo '<p><label><strong>Contact Email</strong></label><br>';
    echo '<input type="email" name="vms_contact_email" value="' . esc_attr($contact_email) . '" style="width:100%;max-width:520px;"></p>';

    echo '<p><label><strong>Contact Phone</strong></label><br>';
    echo '<input type="text" name="vms_contact_phone" value="' . esc_attr($contact_phone) . '" style="width:100%;max-width:520px;"></p>';

    echo '<p><label><strong>Home Base (City/State)</strong></label><br>';
    echo '<input type="text" name="vms_location" value="' . esc_attr($location) . '" style="width:100%;max-width:520px;"></p>';

    echo '<p><label><strong>EPK / Website Link</strong></label><br>';
    echo '<input type="url" name="vms_epk_url" value="' . esc_attr($epk_url) . '" style="width:100%;max-width:520px;"></p>';

    echo '<p><label><strong>Social Links</strong></label><br>';
    echo '<textarea name="vms_social_links" rows="4" style="width:100%;max-width:520px;" placeholder="Instagram, Facebook, Spotify, YouTube...">'
        . esc_textarea($social_links) . '</textarea></p>';

    echo '<p><button type="submit" name="vms_vendor_profile_save" class="button button-primary">Save Profile</button></p>';
    echo '</form>';
}

function vms_vendor_portal_render_tech_docs($vendor_id)
{
    $vendor_id = (int) $vendor_id;

    echo '<h3>Tech Docs</h3>';
    echo '<p>Upload your current stage plot and input list (PDF or image). You can replace them any time.</p>';

    // Ensure media handling functions are available on front-end
    if (!function_exists('media_handle_upload')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }

    // Handle uploads
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vms_techdocs_save'])) {
        if (
            !isset($_POST['vms_techdocs_nonce']) ||
            !wp_verify_nonce($_POST['vms_techdocs_nonce'], 'vms_techdocs_save')
        ) {
            echo '<div class="notice notice-error"><p>Security check failed.</p></div>';
        } else {

            $updated = false;

            if (!empty($_FILES['vms_stage_plot']['name'])) {
                $attach_id = media_handle_upload('vms_stage_plot', 0);
                if (!is_wp_error($attach_id)) {
                    update_post_meta($vendor_id, '_vms_stage_plot_attachment_id', (int) $attach_id);
                    $updated = true;
                } else {
                    echo '<div class="notice notice-error"><p>Stage plot upload failed: ' . esc_html($attach_id->get_error_message()) . '</p></div>';
                }
            }

            if (!empty($_FILES['vms_input_list']['name'])) {
                $attach_id = media_handle_upload('vms_input_list', 0);
                if (!is_wp_error($attach_id)) {
                    update_post_meta($vendor_id, '_vms_input_list_attachment_id', (int) $attach_id);
                    $updated = true;
                } else {
                    echo '<div class="notice notice-error"><p>Input list upload failed: ' . esc_html($attach_id->get_error_message()) . '</p></div>';
                }
            }

            if ($updated) {
                echo '<div class="notice notice-success"><p>Tech docs updated.</p></div>';
            }
        }
    }

    $stage_id = (int) get_post_meta($vendor_id, '_vms_stage_plot_attachment_id', true);
    $input_id = (int) get_post_meta($vendor_id, '_vms_input_list_attachment_id', true);

    $stage_url = $stage_id ? wp_get_attachment_url($stage_id) : '';
    $input_url = $input_id ? wp_get_attachment_url($input_id) : '';

    echo '<ul>';
    echo '<li><strong>Stage Plot:</strong> ' . ($stage_url ? '<a target="_blank" rel="noopener" href="' . esc_url($stage_url) . '">View current</a>' : 'None uploaded') . '</li>';
    echo '<li><strong>Input List:</strong> ' . ($input_url ? '<a target="_blank" rel="noopener" href="' . esc_url($input_url) . '">View current</a>' : 'None uploaded') . '</li>';
    echo '</ul>';

    echo '<form method="post" enctype="multipart/form-data">';
    wp_nonce_field('vms_techdocs_save', 'vms_techdocs_nonce');

    echo '<p><label><strong>Upload / Replace Stage Plot</strong></label><br>';
    echo '<input type="file" name="vms_stage_plot" accept=".pdf,.png,.jpg,.jpeg,.webp"></p>';

    echo '<p><label><strong>Upload / Replace Input List</strong></label><br>';
    echo '<input type="file" name="vms_input_list" accept=".pdf,.png,.jpg,.jpeg,.webp"></p>';

    echo '<p><button type="submit" name="vms_techdocs_save" class="button button-primary">Save Tech Docs</button></p>';
    echo '</form>';
}
