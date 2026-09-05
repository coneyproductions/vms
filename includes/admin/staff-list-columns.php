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

if (!function_exists('bvmgr_staff_admin_list_role_names')) {
    function bvmgr_staff_admin_list_role_names(int $post_id): array
    {
        $role_names = array();

        if (taxonomy_exists('vms_staff_role')) {
            $role_names = wp_get_post_terms($post_id, 'vms_staff_role', array('fields' => 'names'));
            if (is_wp_error($role_names) || !is_array($role_names)) {
                $role_names = array();
            }
        }

        if (empty($role_names)) {
            $legacy_role = trim((string) get_post_meta($post_id, '_vms_staff_role', true));
            if ($legacy_role !== '') {
                $role_names = preg_split('/\s*,\s*/', $legacy_role);
            }
        }

        $role_names = array_values(array_unique(array_filter(array_map(static function ($name): string {
            return trim((string) $name);
        }, $role_names))));

        if (!empty($role_names)) {
            natcasesort($role_names);
            $role_names = array_values($role_names);
        }

        return $role_names;
    }
}

if (!function_exists('bvmgr_staff_admin_list_linked_user_id')) {
    function bvmgr_staff_admin_list_linked_user_id(int $post_id): int
    {
        $user_id = (int) get_post_meta($post_id, '_vms_linked_user_id', true);
        if ($user_id <= 0) {
            $user_id = (int) get_post_meta($post_id, '_vms_user_id', true);
        }
        if ($user_id > 0) {
            return $user_id;
        }

        global $wpdb;
        if (!is_object($wpdb) || !method_exists($wpdb, 'get_var') || !method_exists($wpdb, 'prepare')) {
            return 0;
        }
        $t_usermeta = (isset($wpdb->usermeta) && is_string($wpdb->usermeta) && $wpdb->usermeta !== '') ? $wpdb->usermeta : ((isset($wpdb->prefix) && is_string($wpdb->prefix)) ? $wpdb->prefix . 'usermeta' : '');
        if ($t_usermeta === '') {
            return 0;
        }
        /* phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin linked-user fallback reads one normalized reverse usermeta pointer with prepared identifier/filter values, and staff-link edits must remain request-fresh. */
        $user_id = (int) $wpdb->get_var($wpdb->prepare('SELECT user_id FROM %i WHERE meta_key = %s AND meta_value = %s ORDER BY umeta_id ASC LIMIT 1', $t_usermeta, '_vms_staff_id', (string) $post_id));
        return $user_id > 0 ? $user_id : 0;
    }
}

add_filter('manage_edit-vms_staff_columns', function ($cols) {

    // Keep checkbox + title from WP, but insert our useful columns.
    $new = [];

    if (isset($cols['cb'])) $new['cb'] = $cols['cb'];
    $new['title'] = __('Staff', 'backstage-venue-manager');

    $new['vms_staff_role']   = __('Role', 'backstage-venue-manager');
    $new['vms_dualhat']      = __('Dual-Hat', 'backstage-venue-manager');
    $new['vms_tax']          = __('Tax Profile', 'backstage-venue-manager');
    $new['vms_certifications'] = __('Certifications', 'backstage-venue-manager');
    $new['vms_contact']      = __('Contact', 'backstage-venue-manager');
    $new['vms_linked_user']  = __('Portal User', 'backstage-venue-manager');
    $new['date']             = __('Date', 'backstage-venue-manager');

    return $new;
}, 20);

add_action('manage_vms_staff_posts_custom_column', function ($col, $post_id) {

    switch ($col) {

        case 'vms_staff_role': {
            $role_names = bvmgr_staff_admin_list_role_names((int) $post_id);
            echo !empty($role_names) ? esc_html(implode(', ', $role_names)) : '—';
            break;
        }

        case 'vms_dualhat': {
            if (!function_exists('bvmgr_staff_linked_vendor_meta_key')) {
                echo '—';
                break;
            }

            $vendor_id = (int) get_post_meta((int) $post_id, bvmgr_staff_linked_vendor_meta_key(), true);
            if ($vendor_id <= 0) {
                echo '—';
                break;
            }

            $vp = get_post($vendor_id);
            if (!$vp || $vp->post_type !== 'vms_vendor') {
                echo '—';
                break;
            }

            $label = get_the_title($vendor_id);
            if (!is_string($label) || $label === '') {
                $label = 'Vendor #' . (string) $vendor_id;
            }

            $edit_url = get_edit_post_link($vendor_id, '');
            echo '<span class="vms-badge vms-badge-dualhat">' . esc_html__('Dual-Hat', 'backstage-venue-manager') . '</span>';
            if (is_string($edit_url) && $edit_url !== '') {
                echo ' <a href="' . esc_url($edit_url) . '">' . esc_html($label) . '</a>';
            } else {
                echo ' ' . esc_html($label);
            }

            break;
        }

        case 'vms_tax': {
            // Reuse the vendor tax validation helpers (you said staff should follow same requirements).
            if (function_exists('bvmgr_vendor_tax_profile_missing_items')) {
                $missing = bvmgr_vendor_tax_profile_missing_items((int)$post_id);

                if (empty($missing)) {
                    echo '<span class="vms-badge vms-badge-ok">' . esc_html__('Complete', 'backstage-venue-manager') . '</span>';
                } else {
                    echo '<span class="vms-badge vms-badge-miss">' . esc_html__('Incomplete', 'backstage-venue-manager') . '</span>';
                    echo '<div class="description vms-staff-tax-missing">' .
                        esc_html(implode(', ', $missing)) .
                        '</div>';
                }
            } else {
                echo '—';
            }
            break;
        }

        case 'vms_certifications': {
            if (!function_exists('bvmgr_staffing_staff_qualification_status_counts')) {
                echo '—';
                break;
            }

            $counts = bvmgr_staffing_staff_qualification_status_counts((int) $post_id);
            $pending = (int) ($counts['pending_verification'] ?? 0);
            $approved = (int) ($counts['active'] ?? 0);
            $expired = (int) ($counts['expired'] ?? 0);
            $rejected = (int) ($counts['rejected'] ?? 0);

            if ($pending > 0) {
                /* translators: %d: number of staff certifications pending verification. */
                echo '<a class="vms-badge vms-badge-warn" href="' . esc_url(bvmgr_staffing_staff_qualification_review_url((int) $post_id)) . '">' . esc_html(sprintf(_n('%d Pending', '%d Pending', $pending, 'backstage-venue-manager'), $pending)) . '</a>';
            } elseif ($approved > 0) {
                /* translators: %d: number of approved staff certifications. */
                echo '<span class="vms-badge vms-badge-ok">' . esc_html(sprintf(_n('%d Approved', '%d Approved', $approved, 'backstage-venue-manager'), $approved)) . '</span>';
            } else {
                echo '—';
            }

            $meta_bits = array();
            if ($expired > 0) {
                /* translators: %d: number of expired staff certifications. */
                $meta_bits[] = sprintf(_n('%d expired', '%d expired', $expired, 'backstage-venue-manager'), $expired);
            }
            if ($rejected > 0) {
                /* translators: %d: number of rejected staff certifications. */
                $meta_bits[] = sprintf(_n('%d rejected', '%d rejected', $rejected, 'backstage-venue-manager'), $rejected);
            }
            if (!empty($meta_bits)) {
                echo '<div class="description">' . esc_html(implode(' · ', $meta_bits)) . '</div>';
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

            if (!empty($lines)) {
                echo wp_kses(implode('<br>', $lines), array(
                    'a' => array(
                        'href' => true,
                    ),
                    'br' => array(),
                    'strong' => array(),
                ));
            } else {
                echo '—';
            }
            break;
        }

        case 'vms_linked_user': {
            $user_id = bvmgr_staff_admin_list_linked_user_id((int) $post_id);

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
        $q->set('vms_staff_role_sort', 1);
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

add_filter('posts_clauses', function (array $clauses, WP_Query $q): array {
    if (!is_admin() || !$q->is_main_query()) {
        return $clauses;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->id !== 'edit-vms_staff') {
        return $clauses;
    }

    if ((int) $q->get('vms_staff_role_sort') !== 1) {
        return $clauses;
    }

    global $wpdb;

    $join = $clauses['join'] ?? '';
    $groupby = $clauses['groupby'] ?? '';
    $order = strtoupper((string) $q->get('order')) === 'DESC' ? 'DESC' : 'ASC';

    if (strpos($join, 'vms_staff_role_terms') === false) {
        $join .= " LEFT JOIN {$wpdb->term_relationships} AS vms_staff_role_rel ON {$wpdb->posts}.ID = vms_staff_role_rel.object_id";
        $join .= " LEFT JOIN {$wpdb->term_taxonomy} AS vms_staff_role_tax ON vms_staff_role_rel.term_taxonomy_id = vms_staff_role_tax.term_taxonomy_id AND vms_staff_role_tax.taxonomy = 'vms_staff_role'";
        $join .= " LEFT JOIN {$wpdb->terms} AS vms_staff_role_terms ON vms_staff_role_tax.term_id = vms_staff_role_terms.term_id";
    }

    if ($groupby === '') {
        $groupby = "{$wpdb->posts}.ID";
    } elseif (strpos($groupby, "{$wpdb->posts}.ID") === false) {
        $groupby .= ", {$wpdb->posts}.ID";
    }

    $clauses['join'] = $join;
    $clauses['groupby'] = $groupby;
    $clauses['orderby'] = "MIN(vms_staff_role_terms.name) {$order}, {$wpdb->posts}.post_title {$order}";

    return $clauses;
}, 10, 2);

/**
 * Tiny badge styling (matches what we used elsewhere).
 * Loaded only on staff list screen.
 */
add_action('admin_head', function () {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->id !== 'edit-vms_staff') return;

});
