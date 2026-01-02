<?php

add_action('init', 'vms_register_vendor_application_cpt');
function vms_register_vendor_application_cpt() {
    register_post_type('vms_vendor_app', array(
        'labels' => array(
            'name'          => __('Vendor Applications', 'vms'),
            'singular_name' => __('Vendor Application', 'vms'),
            'menu_name'     => __('Vendor Applications', 'vms'),
        ),
        'public'        => false,
        'show_ui'       => true,
        'show_in_menu'  => true,
        'menu_icon'     => 'dashicons-forms',
        'supports'      => array('title'),
        'capability_type' => 'post',
    ));
}

add_shortcode('vms_vendor_apply', 'vms_vendor_apply_shortcode');

function vms_vendor_apply_shortcode() {
    $message = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vms_vendor_apply_submit'])) {
        $message = vms_handle_vendor_application_submit();
    }

    ob_start();

    if ($message) {
        echo '<div class="vms-apply-message">' . wp_kses_post($message) . '</div>';
    }

    ?>
    <form method="post">
        <?php wp_nonce_field('vms_vendor_apply', 'vms_vendor_apply_nonce'); ?>

        <p>
            <label><strong>Band / Vendor Name</strong></label><br>
            <input type="text" name="vms_app_name" required style="width:100%;max-width:520px;">
        </p>

        <p>
            <label><strong>Email</strong></label><br>
            <input type="email" name="vms_app_email" required style="width:100%;max-width:520px;">
        </p>

        <p>
            <label><strong>Vendor Type</strong></label><br>
            <select name="vms_app_type" required>
                <option value="">-- Select --</option>
                <option value="band">Band</option>
                <option value="food_truck">Food Truck</option>
            </select>
        </p>

        <p>
            <label><strong>Home Base (City/State)</strong></label><br>
            <input type="text" name="vms_app_location" style="width:100%;max-width:520px;">
        </p>

        <p>
            <label><strong>Typical Rate (optional)</strong></label><br>
            <input type="text" name="vms_app_rate" placeholder="e.g. $800 flat, or door split" style="width:100%;max-width:520px;">
        </p>

        <p>
            <label><strong>EPK / Website Link</strong></label><br>
            <input type="url" name="vms_app_epk" required style="width:100%;max-width:520px;">
        </p>

        <p>
            <label><strong>Social Links (optional)</strong></label><br>
            <textarea name="vms_app_social" rows="3" style="width:100%;max-width:520px;"
                placeholder="Instagram, Facebook, Spotify, YouTube, etc."></textarea>
        </p>

        <p>
            <label><strong>Anything else we should know?</strong></label><br>
            <textarea name="vms_app_notes" rows="4" style="width:100%;max-width:520px;"></textarea>
        </p>

        <p>
            <button class="button button-primary" type="submit" name="vms_vendor_apply_submit">
                Submit Application
            </button>
        </p>
    </form>
    <?php

    return ob_get_clean();
}

function vms_handle_vendor_application_submit() {
    if (!isset($_POST['vms_vendor_apply_nonce']) ||
        !wp_verify_nonce($_POST['vms_vendor_apply_nonce'], 'vms_vendor_apply')) {
        return '<p>Security check failed. Please refresh and try again.</p>';
    }

    $name     = isset($_POST['vms_app_name']) ? sanitize_text_field($_POST['vms_app_name']) : '';
    $email    = isset($_POST['vms_app_email']) ? sanitize_email($_POST['vms_app_email']) : '';
    $type     = isset($_POST['vms_app_type']) ? sanitize_key($_POST['vms_app_type']) : '';
    $location = isset($_POST['vms_app_location']) ? sanitize_text_field($_POST['vms_app_location']) : '';
    $rate     = isset($_POST['vms_app_rate']) ? sanitize_text_field($_POST['vms_app_rate']) : '';
    $epk      = isset($_POST['vms_app_epk']) ? esc_url_raw($_POST['vms_app_epk']) : '';
    $social   = isset($_POST['vms_app_social']) ? sanitize_textarea_field($_POST['vms_app_social']) : '';
    $notes    = isset($_POST['vms_app_notes']) ? sanitize_textarea_field($_POST['vms_app_notes']) : '';

    if (!$name || !$email || !$type || !$epk) {
        return '<p>Please fill in the required fields.</p>';
    }

    // Basic anti-duplicate: existing pending app with same email
    $existing = get_posts(array(
        'post_type'      => 'vms_vendor_app',
        'posts_per_page' => 1,
        'post_status'    => array('publish', 'draft', 'pending'),
        'meta_query'     => array(
            array('key' => '_vms_app_email', 'value' => $email),
        ),
        'fields' => 'ids',
    ));

    if (!empty($existing)) {
        return '<p>We already have an application on file for that email. If you need to update it, please contact us.</p>';
    }

    $app_id = wp_insert_post(array(
        'post_type'   => 'vms_vendor_app',
        'post_status' => 'publish',
        'post_title'  => $name,
    ));

    if (!$app_id || is_wp_error($app_id)) {
        return '<p>Sorry — something went wrong. Please try again later.</p>';
    }

    update_post_meta($app_id, '_vms_app_email', $email);
    update_post_meta($app_id, '_vms_app_type', $type);
    update_post_meta($app_id, '_vms_app_location', $location);
    update_post_meta($app_id, '_vms_app_rate', $rate);
    update_post_meta($app_id, '_vms_app_epk', $epk);
    update_post_meta($app_id, '_vms_app_social', $social);
    update_post_meta($app_id, '_vms_app_notes', $notes);
    update_post_meta($app_id, '_vms_app_status', 'pending');

    // Optional: notify admin (simple email)
    $admin_email = get_option('admin_email');
    wp_mail(
        $admin_email,
        'New Vendor Application: ' . $name,
        "A new vendor application was submitted.\n\nName: {$name}\nEmail: {$email}\nEPK: {$epk}\n\nReview in WP Admin → Vendor Applications."
    );

    return '<p><strong>Application received!</strong> We’ll review it and reach out if it’s a good fit.</p>';
}

