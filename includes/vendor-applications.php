<?php
  
add_action('init', 'vms_register_vendor_application_cpt');
function vms_register_vendor_application_cpt()
{
    register_post_type('vms_vendor_app', array(
        'labels' => array(
            'name'          => __('Vendor Applications', 'vms'),
            'singular_name' => __('Vendor Application', 'vms'),
            'menu_name'     => __('Vendor Applications', 'vms'),
        ),
        'public'        => false,
        'show_ui'       => true,
        'show_in_menu'  => 'vms',
        'menu_icon'     => 'dashicons-forms',
        'supports' => array('title', 'editor', 'thumbnail'),
        'capability_type' => 'post',
    ));
}

add_shortcode('vms_vendor_apply', 'vms_vendor_apply_shortcode');

function vms_vendor_apply_shortcode()
{
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

function vms_handle_vendor_application_submit()
{
    if (
        !isset($_POST['vms_vendor_apply_nonce']) ||
        !wp_verify_nonce($_POST['vms_vendor_apply_nonce'], 'vms_vendor_apply')
    ) {
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

add_action('add_meta_boxes', function () {
    add_meta_box(
        'vms_vendor_app_details',
        __('Application Details', 'vms'),
        'vms_render_vendor_app_details_metabox',
        'vms_vendor_app',
        'normal',
        'high'
    );
});

function vms_render_vendor_app_details_metabox($post)
{
    $email    = get_post_meta($post->ID, '_vms_app_email', true);
    $type     = get_post_meta($post->ID, '_vms_app_type', true);
    $location = get_post_meta($post->ID, '_vms_app_location', true);
    $rate     = get_post_meta($post->ID, '_vms_app_rate', true);
    $epk      = get_post_meta($post->ID, '_vms_app_epk', true);
    $social   = get_post_meta($post->ID, '_vms_app_social', true);
    $notes    = get_post_meta($post->ID, '_vms_app_notes', true);
    $status   = get_post_meta($post->ID, '_vms_app_status', true);
    if (!$status) $status = 'pending';

    $status_label = ucfirst($status);

    echo '<p><strong>Status:</strong> ' . esc_html($status_label) . '</p>';
    echo '<hr>';

    echo '<table class="form-table">';
    echo '<tr><th>Email</th><td>' . esc_html($email) . '</td></tr>';
    echo '<tr><th>Type</th><td>' . esc_html($type) . '</td></tr>';
    echo '<tr><th>Location</th><td>' . esc_html($location) . '</td></tr>';
    echo '<tr><th>Typical Rate</th><td>' . esc_html($rate) . '</td></tr>';
    echo '<tr><th>EPK / Website</th><td>';
    if ($epk) {
        echo '<a href="' . esc_url($epk) . '" target="_blank" rel="noopener">' . esc_html($epk) . '</a>';
    } else {
        echo '—';
    }
    echo '</td></tr>';

    echo '<tr><th>Social Links</th><td><pre style="white-space:pre-wrap;margin:0;">' . esc_html($social) . '</pre></td></tr>';
    echo '<tr><th>Notes</th><td><pre style="white-space:pre-wrap;margin:0;">' . esc_html($notes) . '</pre></td></tr>';
    echo '</table>';

    echo '<hr>';

    // Actions
    if (!current_user_can('manage_options')) {
        echo '<p><em>You do not have permission to approve applications.</em></p>';
        return;
    }

    // Approve URL
    $approve_url = wp_nonce_url(
        admin_url('admin-post.php?action=vms_approve_vendor_app&app_id=' . $post->ID),
        'vms_approve_vendor_app_' . $post->ID
    );

    // Reject URL (optional)
    $reject_url = wp_nonce_url(
        admin_url('admin-post.php?action=vms_reject_vendor_app&app_id=' . $post->ID),
        'vms_reject_vendor_app_' . $post->ID
    );

    echo '<p>';
    echo '<a class="button button-primary" href="' . esc_url($approve_url) . '">Approve & Create Portal</a> ';
    echo '<a class="button" style="margin-left:6px;" href="' . esc_url($reject_url) . '">Reject</a>';
    echo '</p>';

    echo '<p class="description">Approve creates a Vendor profile + user login and emails the vendor a password setup link.</p>';
}

add_action('admin_post_vms_approve_vendor_app', 'vms_handle_approve_vendor_app');

function vms_handle_approve_vendor_app()
{
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions.');
    }

    $app_id = isset($_GET['app_id']) ? absint($_GET['app_id']) : 0;
    if (!$app_id) {
        wp_die('Missing application ID.');
    }

    if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'vms_approve_vendor_app_' . $app_id)) {
        wp_die('Invalid nonce.');
    }

    $app = get_post($app_id);
    if (!$app || $app->post_type !== 'vms_vendor_app') {
        wp_die('Application not found.');
    }

    $status = get_post_meta($app_id, '_vms_app_status', true);
    if ($status === 'approved') {
        vms_vendor_app_redirect($app_id, 'already_approved');
        return;
    }

    // Pull fields
    $vendor_name = $app->post_title;
    $email       = sanitize_email(get_post_meta($app_id, '_vms_app_email', true));
    $type        = sanitize_key(get_post_meta($app_id, '_vms_app_type', true));
    $location    = sanitize_text_field(get_post_meta($app_id, '_vms_app_location', true));
    $rate        = sanitize_text_field(get_post_meta($app_id, '_vms_app_rate', true));
    $epk         = esc_url_raw(get_post_meta($app_id, '_vms_app_epk', true));
    $social      = sanitize_textarea_field(get_post_meta($app_id, '_vms_app_social', true));
    $notes       = sanitize_textarea_field(get_post_meta($app_id, '_vms_app_notes', true));

    if (!$email) {
        vms_vendor_app_redirect($app_id, 'missing_email');
        return;
    }

    // 1) Create or find WP user
    $user_id = email_exists($email);
    $created_user = false;

    if (!$user_id) {
        $username_base = sanitize_user(strtolower(preg_replace('/\s+/', '', $vendor_name)));
        if (!$username_base) $username_base = 'vendor';

        $username = $username_base;
        $i = 1;
        while (username_exists($username)) {
            $username = $username_base . $i;
            $i++;
        }

        $random_password = wp_generate_password(20, true);
        $user_id = wp_create_user($username, $random_password, $email);

        if (is_wp_error($user_id)) {
            vms_vendor_app_redirect($app_id, 'user_create_failed');
            return;
        }

        $created_user = true;

        // Set role
        $user = get_user_by('id', $user_id);
        if ($user) {
            $user->set_role('vms_vendor');
        }
    } else {
        // Ensure role is at least vms_vendor (doesn't remove other roles)
        $user = get_user_by('id', $user_id);
        if ($user && !in_array('vms_vendor', (array) $user->roles, true)) {
            $user->add_role('vms_vendor');
        }
    }

    // 2) Create Vendor post
    $vendor_id = wp_insert_post(array(
        'post_type'   => 'vms_vendor',
        'post_status' => 'publish',
        'post_title'  => $vendor_name,
    ));

    if (!$vendor_id || is_wp_error($vendor_id)) {
        vms_vendor_app_redirect($app_id, 'vendor_create_failed');
        return;
    }

    // 3) Copy fields into vendor meta
    // Adjust these meta keys to match your existing vendor schema.
    update_post_meta($vendor_id, '_vms_vendor_type', $type);         // e.g. 'band' or 'food_truck'
    update_post_meta($vendor_id, '_vms_vendor_location', $location);
    update_post_meta($vendor_id, '_vms_vendor_rate', $rate);
    update_post_meta($vendor_id, '_vms_vendor_epk', $epk);
    update_post_meta($vendor_id, '_vms_vendor_social', $social);
    update_post_meta($vendor_id, '_vms_vendor_notes', $notes);

    // 4) Link user ↔ vendor
    update_user_meta($user_id, '_vms_vendor_id', (int) $vendor_id);
    update_post_meta($vendor_id, '_vms_user_id', (int) $user_id);

    // 5) Mark application approved + record linkage
    update_post_meta($app_id, '_vms_app_status', 'approved');
    update_post_meta($app_id, '_vms_approved_vendor_id', (int) $vendor_id);
    update_post_meta($app_id, '_vms_approved_user_id', (int) $user_id);

    // 6) Email vendor login / password setup
    // If user already existed, they likely already have a password.
    // Still helpful to send the login page link; for created users, send password setup.
    $portal_url = site_url('/vendor-portal/'); // change if your portal page slug differs

    if ($created_user) {
        // WordPress will send the user a set-password email
        if (function_exists('wp_new_user_notification')) {
            wp_new_user_notification($user_id, null, 'user');
        }

        // Follow up with portal link
        wp_mail(
            $email,
            'Your Vendor Portal is Approved',
            "You're approved! 🎉\n\nNext step: check your email for a password setup link.\n\nVendor Portal:\n{$portal_url}\n"
        );
    } else {
        wp_mail(
            $email,
            'Your Vendor Portal is Approved',
            "You're approved! 🎉\n\nLog into the Vendor Portal here:\n{$portal_url}\n\nIf you forgot your password, use the password reset link on the login screen."
        );
    }

    vms_vendor_app_redirect($app_id, 'approved');
}

add_action('admin_notices', function () {
    if (!is_admin() || !isset($_GET['vms_app_result'])) return;
    if (!current_user_can('manage_options')) return;

    $result = sanitize_key($_GET['vms_app_result']);

    $map = array(
        'approved'          => array('success', 'Application approved. Vendor + portal created.'),
        'already_approved'  => array('info',    'This application is already approved.'),
        'missing_email'     => array('error',   'Cannot approve: application is missing an email.'),
        'user_create_failed' => array('error',   'User creation failed. Check if email/username conflicts exist.'),
        'vendor_create_failed' => array('error', 'Vendor profile creation failed.'),
        'rejected' => array('info', 'Application marked as rejected.')

    );

    if (!isset($map[$result])) return;

    [$type, $msg] = $map[$result];
    echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>' . esc_html($msg) . '</p></div>';
});

add_action('admin_post_vms_reject_vendor_app', function () {
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions.');
    }

    $app_id = isset($_GET['app_id']) ? absint($_GET['app_id']) : 0;
    if (!$app_id) wp_die('Missing application ID.');

    if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'vms_reject_vendor_app_' . $app_id)) {
        wp_die('Invalid nonce.');
    }

    update_post_meta($app_id, '_vms_app_status', 'rejected');
    vms_vendor_app_redirect($app_id, 'rejected');
});
