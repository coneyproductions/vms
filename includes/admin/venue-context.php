<?php
if (!defined('ABSPATH')) exit;

/**
 * Get the current venue ID for the current admin user.
 * Fallback order:
 *  1) user_meta _vms_current_venue_id
 *  2) Settings: vms_settings[default_venue_id]
 *  3) first available venue by title
 */
function vms_get_current_venue_id(): int {
    $user_id = get_current_user_id();

    // 1) Current venue from user meta
    $venue_id = (int) get_user_meta($user_id, '_vms_current_venue_id', true);
    if ($venue_id > 0 && get_post_type($venue_id) === 'vms_venue') {
        return $venue_id;
    }

    // 2) Default venue from VMS Settings (site-wide fallback)
    $opts = (array) get_option('vms_settings', array());
    $default_id = isset($opts['default_venue_id']) ? (int) $opts['default_venue_id'] : 0;

    if ($default_id > 0 && get_post_type($default_id) === 'vms_venue') {
        // Save as the user's current venue so UI stays consistent
        update_user_meta($user_id, '_vms_current_venue_id', $default_id);
        return $default_id;
    }

    // 3) Last resort: first venue by title
    $venues = get_posts(array(
        'post_type'      => 'vms_venue',
        'posts_per_page' => 1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'fields'         => 'ids',
    ));

    $fallback = !empty($venues) ? (int) $venues[0] : 0;

    if ($fallback > 0) {
        update_user_meta($user_id, '_vms_current_venue_id', $fallback);
    }

    return $fallback;
}


function vms_set_current_venue_id(int $venue_id): void {
    $user_id = get_current_user_id();
    if ($venue_id > 0 && get_post_type($venue_id) === 'vms_venue') {
        update_user_meta($user_id, '_vms_current_venue_id', $venue_id);
    }
}

/**
 * Render a reusable venue selector form (admin-only).
 * Include this at the top of VMS admin pages.
 */
function vms_render_current_venue_selector(): void {
    if (!current_user_can('manage_options')) return;

    $current = vms_get_current_venue_id();

    $venues = get_posts(array(
        'post_type'      => 'vms_venue',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ));

    if (empty($venues)) {
        echo '<div class="notice notice-warning"><p><strong>VMS:</strong> No venues exist yet. Create one under VMS → Venues.</p></div>';
        return;
    }

    $action = esc_url(admin_url('admin-post.php'));

    echo '<form method="post" action="' . $action . '" style="margin: 12px 0 16px; padding: 12px; background:#fff; border:1px solid #dcdcde; border-radius:8px;">';
    echo '<input type="hidden" name="action" value="vms_set_current_venue">';
    wp_nonce_field('vms_set_current_venue', 'vms_current_venue_nonce');

    echo '<label for="vms_current_venue_id" style="font-weight:600; margin-right:8px;">Current Venue:</label>';
    echo '<select id="vms_current_venue_id" name="venue_id" style="min-width:240px;">';
    foreach ($venues as $v) {
        echo '<option value="' . esc_attr($v->ID) . '" ' . selected($current, $v->ID, false) . '>' . esc_html($v->post_title) . '</option>';
    }
    echo '</select> ';
    submit_button(__('Switch', 'vms'), 'secondary', 'submit', false);
    echo '</form>';
}

/**
 * Handle selector POST.
 */
add_action('admin_post_vms_set_current_venue', function () {
    if (!current_user_can('manage_options')) {
        wp_die('Not allowed');
    }
    if (empty($_POST['vms_current_venue_nonce']) || !wp_verify_nonce($_POST['vms_current_venue_nonce'], 'vms_set_current_venue')) {
        wp_die('Bad nonce');
    }

    $venue_id = isset($_POST['venue_id']) ? absint($_POST['venue_id']) : 0;
    vms_set_current_venue_id($venue_id);

    $redirect = wp_get_referer() ?: admin_url('admin.php?page=vms');
    wp_safe_redirect($redirect);
    exit;
});
