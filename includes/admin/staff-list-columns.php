<?php
if (!defined('ABSPATH')) exit;

/**
 * VMS – Staff List Columns (Admin)
 * Mirrors the "Vendors" list UX: tax badge, linked user, contact, etc.
 *
 * Assumptions (adjust if your staff meta keys differ):
 * - Staff CPT: vms_staff
 * - Staff contact meta:
 *    _vms_contact_name, _vms_contact_email, _vms_contact_phone
 * - Linked WP user meta:
 *    _vms_user_id  (int)
 * - Tax profile uses SAME keys/requirements as vendors (recommended):
 *    vms_vendor_tax_profile_missing_items() / vms_vendor_tax_profile_is_complete()
 */

add_filter('manage_edit-vms_staff_columns', function ($cols) {

    // Keep checkbox + title from WP, but insert our useful columns.
    $new = [];

    if (isset($cols['cb'])) $new['cb'] = $cols['cb'];
    $new['title'] = __('Staff', 'vms');

    $new['vms_staff_role']   = __('Role', 'vms');
    $new['vms_tax']          = __('Tax Profile', 'vms');
    $new['vms_contact']      = __('Contact', 'vms');
    $new['vms_linked_user']  = __('Portal User', 'vms');
    $new['date']             = __('Date', 'vms');

    return $new;
}, 20);

add_action('manage_vms_staff_posts_custom_column', function ($col, $post_id) {

    switch ($col) {

        case 'vms_staff_role': {
            // If you store role as meta, read it here.
            // Common meta key idea: _vms_staff_role
            $role = (string) get_post_meta($post_id, '_vms_staff_role', true);
            echo $role !== '' ? esc_html($role) : '—';
            break;
        }

        case 'vms_tax': {
            // Reuse the vendor tax validation helpers (you said staff should follow same requirements).
            if (function_exists('vms_vendor_tax_profile_missing_items')) {
                $missing = vms_vendor_tax_profile_missing_items((int)$post_id);

                if (empty($missing)) {
                    echo '<span class="vms-badge vms-badge-ok">' . esc_html__('Complete', 'vms') . '</span>';
                } else {
                    echo '<span class="vms-badge vms-badge-miss">' . esc_html__('Incomplete', 'vms') . '</span>';
                    echo '<div class="description" style="margin-top:4px;">' .
                        esc_html(implode(', ', $missing)) .
                        '</div>';
                }
            } else {
                echo '—';
            }
            break;
        }

        case 'vms_contact': {
            $name  = (string) get_post_meta($post_id, '_vms_contact_name', true);
            $email = (string) get_post_meta($post_id, '_vms_contact_email', true);
            $phone = (string) get_post_meta($post_id, '_vms_contact_phone', true);

            $lines = [];

            if ($name !== '') {
                $lines[] = '<strong>' . esc_html($name) . '</strong>';
            }

            if ($email !== '') {
                $lines[] = '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';
            }

            if ($phone !== '') {
                $lines[] = '<a href="tel:' . esc_attr($phone) . '">' . esc_html($phone) . '</a>';
            }

            echo !empty($lines) ? implode('<br>', $lines) : '—';
            break;
        }

        case 'vms_linked_user': {
            // Meta key for the staff’s WP user account link.
            // If yours differs, change it here.
            $user_id = (int) get_post_meta($post_id, '_vms_user_id', true);

            if ($user_id <= 0) {
                echo '—';
                break;
            }

            $u = get_user_by('id', $user_id);
            if (!$u) {
                echo esc_html('#' . $user_id);
                break;
            }

            $label = $u->display_name ?: $u->user_login;
            $edit_url = get_edit_user_link($user_id);

            echo '<a href="' . esc_url($edit_url) . '">' . esc_html($label) . '</a>';
            echo '<div class="description">' . esc_html($u->user_email) . '</div>';
            break;
        }
    }

}, 10, 2);

/**
 * Optional: make Role + Tax sortable (role only if you store it as meta).
 */
add_filter('manage_edit-vms_staff_sortable_columns', function ($cols) {
    $cols['vms_staff_role'] = 'vms_staff_role';
    $cols['vms_tax'] = 'vms_tax';
    return $cols;
});

add_action('pre_get_posts', function ($q) {
    if (!is_admin() || !$q->is_main_query()) return;

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->id !== 'edit-vms_staff') return;

    $orderby = (string) $q->get('orderby');

    if ($orderby === 'vms_staff_role') {
        $q->set('meta_key', '_vms_staff_role');
        $q->set('orderby', 'meta_value');
        $q->set('meta_type', 'CHAR');
    }

    // Tax sort: “Complete” first, then incomplete (simple heuristic).
    // We sort by presence of the W-9 upload ID (required field).
    if ($orderby === 'vms_tax') {
        $q->set('meta_key', '_vms_w9_upload_id');
        $q->set('orderby', 'meta_value_num');
        $q->set('meta_type', 'NUMERIC');
        $q->set('order', 'DESC');
    }
});

/**
 * Tiny badge styling (matches what we used elsewhere).
 * Loaded only on staff list screen.
 */
add_action('admin_head', function () {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->id !== 'edit-vms_staff') return;

    echo '<style>
.vms-badge{display:inline-block;padding:3px 8px;border-radius:999px;font-size:12px;font-weight:700;line-height:1.2;}
.vms-badge-ok{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;}
.vms-badge-miss{background:#fff7ed;color:#9a3412;border:1px solid #fed7aa;}
</style>';
});