<?php
if (!defined('ABSPATH')) exit;

/**
 * VMS – Staff ↔ Vendor (Dual-hat) Linking (Admin)
 *
 * Mirror UI of vendor-staff-link.php so operators can navigate either direction.
 *
 * Storage:
 * - Staff post_meta:  vms_meta_key('staff','linked_vendor_id') fallback '_vms_linked_vendor_id'
 * - Vendor post_meta: vms_meta_key('vendor','linked_staff_id') fallback '_vms_linked_staff_id'
 */

if (!function_exists('vms_vendor_linked_staff_meta_key')) {
    function vms_vendor_linked_staff_meta_key(): string
    {
        if (function_exists('vms_meta_key')) {
            $k = (string) vms_meta_key('vendor', 'linked_staff_id');
            if ($k !== '') return $k;
        }
        return '_vms_linked_staff_id';
    }
}

if (!function_exists('vms_staff_linked_vendor_meta_key')) {
    function vms_staff_linked_vendor_meta_key(): string
    {
        if (function_exists('vms_meta_key')) {
            $k = (string) vms_meta_key('staff', 'linked_vendor_id');
            if ($k !== '') return $k;
        }
        return '_vms_linked_vendor_id';
    }
}

add_action('add_meta_boxes', function (): void {
    add_meta_box(
        'vms_staff_vendor_link',
        __('Linked Vendor', 'backstage-venue-manager'),
        'vms_staff_vendor_link_metabox_render',
        'vms_staff',
        'side',
        'default'
    );
});

function vms_staff_vendor_link_metabox_render($post): void
{
    if (!($post instanceof WP_Post)) return;

    $staff_id = (int) $post->ID;
    $current_vendor_id = (int) get_post_meta($staff_id, vms_staff_linked_vendor_meta_key(), true);

    wp_nonce_field('vms_staff_vendor_link_save', 'vms_staff_vendor_link_nonce');

    echo '<p class="description">' . esc_html__('Link this staff profile to a Vendor record when the same person is also a payee (contractor, performer, vendor account).', 'backstage-venue-manager') . '</p>';

    $vendor_posts = get_posts(array(
        'post_type'      => 'vms_vendor',
        'post_status'    => 'any',
        'numberposts'    => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'no_found_rows'  => true,
        'fields'         => 'ids',
    ));

    echo '<select name="vms_linked_vendor_id" style="width:100%;">';
    echo '<option value="0">— ' . esc_html__('Not linked', 'backstage-venue-manager') . ' —</option>';

    foreach ($vendor_posts as $vid) {
        $vid = (int) $vid;
        if ($vid <= 0) continue;
        $title = (string) get_the_title($vid);
        if ($title === '') $title = 'Vendor #' . (string) $vid;
        printf(
            '<option value="%d" %s>%s</option>',
            $vid,
            selected($current_vendor_id, $vid, false),
            esc_html($title)
        );
    }

    echo '</select>';

    if ($current_vendor_id > 0) {
        $edit_vendor = get_edit_post_link($current_vendor_id, '');
        if ($edit_vendor) {
            echo '<p style="margin-top:10px;">';
            echo '<a class="button button-secondary" href="' . esc_url($edit_vendor) . '">' . esc_html__('Edit linked vendor', 'backstage-venue-manager') . '</a>';
            echo '</p>';
        }
    }
}

add_action('save_post_vms_staff', function (int $post_id, WP_Post $post, bool $update): void {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;
    if (!current_user_can('edit_post', $post_id)) return;

    if (!isset($_POST['vms_staff_vendor_link_nonce']) || !wp_verify_nonce((string) $_POST['vms_staff_vendor_link_nonce'], 'vms_staff_vendor_link_save')) {
        return;
    }

    $staff_id = (int) $post_id;
    $k_staff_vendor = vms_staff_linked_vendor_meta_key();
    $k_vendor_staff = vms_vendor_linked_staff_meta_key();

    $old_vendor_id = (int) get_post_meta($staff_id, $k_staff_vendor, true);
    $new_vendor_id = isset($_POST['vms_linked_vendor_id']) ? (int) $_POST['vms_linked_vendor_id'] : 0;
    if ($new_vendor_id < 0) $new_vendor_id = 0;

    // Normalize: only allow real vendor posts.
    if ($new_vendor_id > 0) {
        $vp = get_post($new_vendor_id);
        if (!$vp || $vp->post_type !== 'vms_vendor') {
            $new_vendor_id = 0;
        }
    }

    if ($old_vendor_id === $new_vendor_id) return;

    // Unlink prior reverse pointer.
    if ($old_vendor_id > 0) {
        $linked_staff_now = (int) get_post_meta($old_vendor_id, $k_vendor_staff, true);
        if ($linked_staff_now === $staff_id) {
            delete_post_meta($old_vendor_id, $k_vendor_staff);
        }
    }

    if ($new_vendor_id <= 0) {
        delete_post_meta($staff_id, $k_staff_vendor);
        return;
    }

    // Enforce uniqueness: if another staff already links to this vendor, unlink it.
    $other_staff_ids = get_posts(array(
        'post_type'      => 'vms_staff',
        'post_status'    => 'any',
        'numberposts'    => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'meta_query'     => array(
            array(
                'key'     => $k_staff_vendor,
                'value'   => $new_vendor_id,
                'compare' => '=',
                'type'    => 'NUMERIC',
            )
        ),
    ));

    foreach ($other_staff_ids as $osid) {
        $osid = (int) $osid;
        if ($osid <= 0 || $osid === $staff_id) continue;
        delete_post_meta($osid, $k_staff_vendor);
    }

    // Enforce uniqueness: if vendor was linked to another staff, unlink that staff.
    $prior_staff_id = (int) get_post_meta($new_vendor_id, $k_vendor_staff, true);
    if ($prior_staff_id > 0 && $prior_staff_id !== $staff_id) {
        delete_post_meta($prior_staff_id, $k_staff_vendor);
    }

    update_post_meta($staff_id, $k_staff_vendor, $new_vendor_id);
    update_post_meta($new_vendor_id, $k_vendor_staff, $staff_id);

}, 20, 3);
