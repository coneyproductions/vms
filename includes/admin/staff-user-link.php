<?php
if (!defined('ABSPATH')) exit;

/**
 * VMS – Staff ↔ WP User Linking (Admin)
 *
 * Goal: Let a single staff profile drive permissions/roles while the WP user is only the login.
 *
 * Canonical storage:
 * - user_meta: _vms_staff_id (WP User → Staff)
 * Convenience cache:
 * - staff post_meta: _vms_linked_user_id (Staff → WP User)
 */

add_action('add_meta_boxes', function (): void {
    add_meta_box(
        'vms_staff_user_link',
        __('Portal User', 'backstage-venue-manager'),
        'vms_staff_user_link_metabox_render',
        'vms_staff',
        'side',
        'default'
    );
});

function vms_staff_user_link_metabox_render($post): void
{
    if (!($post instanceof WP_Post)) return;

    $staff_id = (int) $post->ID;
    $linked_user_id = (int) get_post_meta($staff_id, '_vms_linked_user_id', true);

    // Auto-suggest by email when not linked.
    if ($linked_user_id <= 0) {
        $staff_email = (string) get_post_meta($staff_id, '_vms_contact_email', true);
        $staff_email = sanitize_email($staff_email);
        if ($staff_email !== '') {
            $u = get_user_by('email', $staff_email);
            if ($u && $u->ID) {
                $linked_user_id = (int) $u->ID;
            }
        }
    }

    wp_nonce_field('vms_staff_user_link_save', 'vms_staff_user_link_nonce');

    echo '<p class="description">' . esc_html__('Pick the WordPress user account that logs in as this staff member (Ops Console, Staff Portal, alerts).', 'backstage-venue-manager') . '</p>';

    $users = get_users(array(
        'orderby' => 'display_name',
        'order'   => 'ASC',
        'fields'  => array('ID', 'display_name', 'user_email'),
    ));

    echo '<select name="vms_linked_user_id" style="width:100%;">';
    echo '<option value="0">— ' . esc_html__('Not linked', 'backstage-venue-manager') . ' —</option>';
    foreach ($users as $u) {
        $label = (string) $u->display_name;
        if (!empty($u->user_email)) {
            $label .= ' — ' . (string) $u->user_email;
        }
        printf(
            '<option value="%d" %s>%s</option>',
            (int) $u->ID,
            selected((int) $linked_user_id, (int) $u->ID, false),
            esc_html($label)
        );
    }
    echo '</select>';

    if ($linked_user_id > 0) {
        $user = get_user_by('id', $linked_user_id);
        if ($user) {
            echo '<p style="margin-top:10px;" class="description">' .
                esc_html__('Currently linked:', 'backstage-venue-manager') . ' <strong>' . esc_html($user->display_name) . '</strong>' .
                '</p>';
        }
    }
}

add_action('save_post_vms_staff', function (int $post_id, WP_Post $post, bool $update): void {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $nonce = (isset($_POST['vms_staff_user_link_nonce']) && !is_array($_POST['vms_staff_user_link_nonce']))
        ? sanitize_text_field(wp_unslash((string) $_POST['vms_staff_user_link_nonce']))
        : '';
    if ($nonce === '' || !wp_verify_nonce($nonce, 'vms_staff_user_link_save')) {
        return;
    }

    $staff_id = (int) $post_id;
    $new_user_id = isset($_POST['vms_linked_user_id']) ? (int) $_POST['vms_linked_user_id'] : 0;
    if ($new_user_id < 0) $new_user_id = 0;

    $old_user_id = (int) get_post_meta($staff_id, '_vms_linked_user_id', true);
    if ($old_user_id === $new_user_id) return;

    // Unlink old user → staff pointer.
    if ($old_user_id > 0) {
        $old_staff_on_user = (int) get_user_meta($old_user_id, '_vms_staff_id', true);
        if ($old_staff_on_user === $staff_id) {
            delete_user_meta($old_user_id, '_vms_staff_id');
        }
    }

    if ($new_user_id <= 0) {
        delete_post_meta($staff_id, '_vms_linked_user_id');
        return;
    }

    // Validate user exists.
    if (!get_user_by('id', $new_user_id)) {
        return;
    }

    // Enforce uniqueness: user can only be linked to one staff.
    $prior_staff_on_user = (int) get_user_meta($new_user_id, '_vms_staff_id', true);
    if ($prior_staff_on_user > 0 && $prior_staff_on_user !== $staff_id) {
        delete_post_meta($prior_staff_on_user, '_vms_linked_user_id');
    }

    // Enforce uniqueness: if another staff post already points at this user, clear it.
    global $wpdb;
    $other_staff_ids = array();
    if (is_object($wpdb) && method_exists($wpdb, 'get_col') && method_exists($wpdb, 'prepare')) {
        $t_posts = (isset($wpdb->posts) && is_string($wpdb->posts) && $wpdb->posts !== '') ? $wpdb->posts : '';
        $t_postmeta = (isset($wpdb->postmeta) && is_string($wpdb->postmeta) && $wpdb->postmeta !== '') ? $wpdb->postmeta : '';
        if ($t_posts !== '' && $t_postmeta !== '') {
            /* phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Staff/user link cleanup reads request-fresh reverse postmeta pointers with prepared identifiers and filters so duplicate links clear immediately after admin edits. */
            $other_staff_ids = $wpdb->get_col($wpdb->prepare('SELECT pm.post_id FROM %i AS pm INNER JOIN %i AS p ON p.ID = pm.post_id WHERE pm.meta_key = %s AND pm.meta_value = %s AND p.post_type = %s ORDER BY pm.meta_id ASC', $t_postmeta, $t_posts, '_vms_linked_user_id', (string) $new_user_id, 'vms_staff'));
        }
    }
    if (!is_array($other_staff_ids)) {
        $other_staff_ids = array();
    }

    foreach ($other_staff_ids as $osid) {
        $osid = (int) $osid;
        if ($osid <= 0 || $osid === $staff_id) continue;
        delete_post_meta($osid, '_vms_linked_user_id');
    }

    update_post_meta($staff_id, '_vms_linked_user_id', $new_user_id);
    update_user_meta($new_user_id, '_vms_staff_id', $staff_id);

}, 20, 3);
