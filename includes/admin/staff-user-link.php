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

    if (!isset($_POST['vms_staff_user_link_nonce']) || !wp_verify_nonce((string) $_POST['vms_staff_user_link_nonce'], 'vms_staff_user_link_save')) {
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
    $other_staff_ids = get_posts(array(
        'post_type'      => 'vms_staff',
        'post_status'    => 'any',
        'numberposts'    => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'meta_query'     => array(
            array(
                'key'     => '_vms_linked_user_id',
                'value'   => $new_user_id,
                'compare' => '=',
                'type'    => 'NUMERIC',
            )
        ),
    ));

    foreach ($other_staff_ids as $osid) {
        $osid = (int) $osid;
        if ($osid <= 0 || $osid === $staff_id) continue;
        delete_post_meta($osid, '_vms_linked_user_id');
    }

    update_post_meta($staff_id, '_vms_linked_user_id', $new_user_id);
    update_user_meta($new_user_id, '_vms_staff_id', $staff_id);

}, 20, 3);
