<?php

add_shortcode('vms_vendor_portal', 'vms_vendor_portal_shortcode');

function vms_vendor_portal_shortcode() {
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
        vms_vendor_portal_render_profile($vendor_id);
    } elseif ($tab === 'availability') {
        vms_vendor_portal_render_availability($vendor_id);
    } elseif ($tab === 'tech') {
        vms_vendor_portal_render_tech_docs($vendor_id);
    } else {
        echo '<p>Choose a tab to update your information.</p>';
    }

    echo '</div>';
    return ob_get_clean();
}

function vms_vendor_portal_render_availability($vendor_id) {
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

