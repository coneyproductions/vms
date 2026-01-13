<?php
if (!defined('ABSPATH')) exit;

/**
 * ==========================================================
 * VMS — Vendor ↔ WP User Linking (Admin)
 * ==========================================================
 *
 * Purpose:
 * - Allow an admin to link a WordPress user account to a Vendor profile (vms_vendor).
 * - This makes the Vendor Portal work immediately for that user.
 *
 * Data model (MVP):
 * - Vendor post meta: _vms_vendor_user_id = (int) user_id
 *
 * Notes for future "agency managers":
 * - Later, we can evolve to _vms_vendor_user_ids (array of user IDs)
 *   while keeping _vms_vendor_user_id as the "primary" login link.
 * - The helper functions below are written to be easy to expand.
 */

/** Meta key for the "primary" linked user. */
const VMS_VENDOR_USER_META_KEY = '_vms_vendor_user_id';

/**
 * Add the metabox on Vendor edit screen.
 */
add_action('add_meta_boxes', function () {
    add_meta_box(
        'vms_vendor_user_link',
        __('Vendor Login User', 'vms'),
        'vms_render_vendor_user_link_metabox',
        'vms_vendor',
        'side',
        'high'
    );
});

/**
 * Render the metabox UI.
 */
function vms_render_vendor_user_link_metabox(WP_Post $post): void
{
    if (!current_user_can('edit_post', $post->ID)) {
        return;
    }

    wp_nonce_field('vms_vendor_user_link_save', 'vms_vendor_user_link_nonce');

    $linked_user_id = (int) get_post_meta($post->ID, VMS_VENDOR_USER_META_KEY, true);

    echo '<p class="description" style="margin:0 0 10px;">';
    echo 'Link a WordPress user to this vendor so they can access the Vendor Portal.';
    echo '</p>';

    if ($linked_user_id > 0) {
        $u = get_user_by('id', $linked_user_id);

        echo '<div style="padding:10px 12px;border:1px solid #d1d5db;border-radius:10px;background:#f9fafb;margin:0 0 10px;">';
        echo '<strong>Currently linked:</strong><br>';

        if ($u) {
            echo esc_html($u->display_name) . '<br>';
            echo '<span class="description">' . esc_html($u->user_email) . '</span><br>';
            echo '<a class="button" style="margin-top:8px;" href="' . esc_url(get_edit_user_link($u->ID)) . '">Edit User</a>';
        } else {
            // User got deleted but meta remains
            echo '<span class="description">User ID #' . esc_html((string)$linked_user_id) . ' (user not found)</span>';
        }
        echo '</div>';
    }

    /**
     * Keep UI simple: a dropdown of users.
     * If you want a search UI later, we can upgrade this to Select2/AJAX.
     */
    $users = get_users([
        'orderby' => 'display_name',
        'order'   => 'ASC',
        'fields'  => ['ID', 'display_name', 'user_email'],
        // Optional: limit to roles you want.
        // 'role__in' => ['subscriber','vendor','administrator'],
    ]);

    echo '<label for="vms_vendor_link_user_id"><strong>Link user account</strong></label><br>';
    echo '<select name="vms_vendor_link_user_id" id="vms_vendor_link_user_id" style="width:100%;max-width:100%;">';
    echo '<option value="0">— Not linked —</option>';

    foreach ($users as $u) {
        $label = $u->display_name;
        if (!empty($u->user_email)) $label .= ' — ' . $u->user_email;

        printf(
            '<option value="%d" %s>%s</option>',
            (int) $u->ID,
            selected($linked_user_id, (int)$u->ID, false),
            esc_html($label)
        );
    }

    echo '</select>';

    echo '<p class="description" style="margin-top:8px;">';
    echo 'Tip: If this user is already linked to another vendor, saving will move the link here (one-to-one).';
    echo '</p>';
}

/**
 * Save handler for linking/unlinking.
 */
add_action('save_post_vms_vendor', function (int $post_id, WP_Post $post) {

    // ==========================================================
    // VMS — Vendor ↔ User Link (Admin Save)
    // ----------------------------------------------------------
    // We store the relationship in TWO places on purpose:
    //
    // 1) Vendor post meta:   VMS_VENDOR_USER_META_KEY => user_id
    //    - Makes it easy to see on the vendor record.
    //
    // 2) User meta:          _vms_vendor_id => vendor_post_id
    //    - This is what the Vendor Portal uses to determine access.
    //
    // Rule (for now): 1 user ↔ 1 vendor (until agency feature later).
    // ==========================================================

    // Safety checks
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if ($post->post_type !== 'vms_vendor') return;
    if (!current_user_can('edit_post', $post_id)) return;

    // Nonce check
    if (
        !isset($_POST['vms_vendor_user_link_nonce']) ||
        !wp_verify_nonce((string) $_POST['vms_vendor_user_link_nonce'], 'vms_vendor_user_link_save')
    ) {
        return;
    }

    // Incoming selection from the metabox dropdown
    $new_user_id = isset($_POST['vms_vendor_link_user_id']) ? absint($_POST['vms_vendor_link_user_id']) : 0;

    // Currently stored link on the vendor post
    $old_user_id = (int) get_post_meta($post_id, VMS_VENDOR_USER_META_KEY, true);

    // ----------------------------
    // Helper: unlink old user from this vendor
    // ----------------------------
    $unlink_old_user = function (int $vendor_id, int $user_id): void {
        if ($user_id <= 0) return;

        // Remove vendor → user link
        delete_post_meta($vendor_id, VMS_VENDOR_USER_META_KEY);

        // Remove user → vendor link ONLY if it points to this vendor
        $current_vendor = (int) get_user_meta($user_id, '_vms_vendor_id', true);
        if ($current_vendor === $vendor_id) {
            delete_user_meta($user_id, '_vms_vendor_id');
        }
    };

    // ----------------------------
    // CASE 1: Unlink requested (dropdown set to "— none —")
    // ----------------------------
    if ($new_user_id <= 0) {
        if ($old_user_id > 0) {
            $unlink_old_user($post_id, $old_user_id);
        }
        return;
    }

    // Validate user exists
    $u = get_user_by('id', $new_user_id);
    if (!$u) {
        // Invalid user ID submitted: clear vendor-side link to avoid stale data
        if ($old_user_id > 0) {
            $unlink_old_user($post_id, $old_user_id);
        } else {
            delete_post_meta($post_id, VMS_VENDOR_USER_META_KEY);
        }
        return;
    }

    // If the vendor already had a different user linked, cleanly detach that old user.
    if ($old_user_id > 0 && $old_user_id !== $new_user_id) {
        $unlink_old_user($post_id, $old_user_id);
    }

    // ----------------------------
    // Enforce 1:1 mapping (for now)
    // If this user is already linked to another vendor, detach that vendor.
    // ----------------------------
    $previous_vendor_id = (int) get_user_meta($new_user_id, '_vms_vendor_id', true);
    if ($previous_vendor_id > 0 && $previous_vendor_id !== $post_id) {
        // Remove the other vendor's "linked user" pointer if it points to this user.
        $prev_vendor_user = (int) get_post_meta($previous_vendor_id, VMS_VENDOR_USER_META_KEY, true);
        if ($prev_vendor_user === $new_user_id) {
            delete_post_meta($previous_vendor_id, VMS_VENDOR_USER_META_KEY);
        }

        // Clear user meta so we can set it to THIS vendor below.
        delete_user_meta($new_user_id, '_vms_vendor_id');
    }

    // ----------------------------
    // Write BOTH directions (this is the missing piece)
    // ----------------------------
    update_post_meta($post_id, VMS_VENDOR_USER_META_KEY, (int) $new_user_id);
    update_user_meta($new_user_id, '_vms_vendor_id', (int) $post_id);
}, 10, 2);

/**
 * Helper: find vendor linked to a WP user (one-to-one).
 *
 * NOTE: This is intentionally written as a helper so later we can:
 * - return multiple vendors for agencies
 * - support an array meta key
 */
function vms_get_vendor_id_for_user(int $user_id): int
{
    if ($user_id <= 0) return 0;

    $q = new WP_Query([
        'post_type'      => 'vms_vendor',
        'post_status'    => ['publish', 'draft', 'private'],
        'fields'         => 'ids',
        'posts_per_page' => 1,
        'meta_query'     => [
            [
                'key'   => VMS_VENDOR_USER_META_KEY,
                'value' => (string) $user_id,
            ],
        ],
        'no_found_rows'  => true,
    ]);

    if (!empty($q->posts[0])) {
        return (int) $q->posts[0];
    }
    return 0;
}

/**
 * Helper: current user’s linked vendor ID.
 * Use this inside your portal shortcode.
 */
function vms_get_vendor_id_for_current_user(): int
{
    $user_id = get_current_user_id();
    return $user_id ? vms_get_vendor_id_for_user((int)$user_id) : 0;
}
