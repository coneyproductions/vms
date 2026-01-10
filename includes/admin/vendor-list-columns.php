<?php
if (!defined('ABSPATH')) exit;

/**
 * VMS – Vendor Admin List Columns + Filters
 * Adds W-9 / 1099 / Payee columns to vms_vendor list view,
 * plus dropdown filters.
 */

/** -----------------------------
 * Columns
 * ----------------------------- */
add_filter('manage_vms_vendor_posts_columns', function ($columns) {
    $new = [];

    foreach ($columns as $key => $label) {
        // Insert after Title
        $new[$key] = $label;

        if ($key === 'title') {
            $new['vms_payee']   = __('Payee (Legal)', 'vms');
            $new['vms_w9']      = __('W-9', 'vms');
            $new['vms_1099']    = __('1099', 'vms');
        }
    }

    // If for some reason title didn't exist, append
    if (!isset($new['vms_w9'])) {
        $new['vms_payee'] = __('Payee (Legal)', 'vms');
        $new['vms_w9']    = __('W-9', 'vms');
        $new['vms_1099']  = __('1099', 'vms');
    }

    return $new;
});

add_action('manage_vms_vendor_posts_custom_column', function ($column, $post_id) {
    if ($column === 'vms_payee') {
        $payee = (string) get_post_meta($post_id, '_vms_payee_legal_name', true);
        echo $payee ? esc_html($payee) : '<span style="color:#646970;">—</span>';
        return;
    }

    if ($column === 'vms_w9') {
        $status = (string) get_post_meta($post_id, '_vms_w9_status', true);
        if (!$status) $status = 'not_requested';

        echo vms_render_w9_pill($status);
        return;
    }

    if ($column === 'vms_1099') {
        $req = (string) get_post_meta($post_id, '_vms_requires_1099', true);
        if (!$req) $req = 'unknown';

        echo vms_render_1099_pill($req);
        return;
    }
}, 10, 2);

/** -----------------------------
 * Sortable columns
 * ----------------------------- */
add_filter('manage_edit-vms_vendor_sortable_columns', function ($sortable) {
    $sortable['vms_payee'] = 'vms_payee';
    $sortable['vms_w9']    = 'vms_w9';
    $sortable['vms_1099']  = 'vms_1099';
    return $sortable;
});

add_action('pre_get_posts', function ($query) {
    if (!is_admin() || !$query->is_main_query()) return;
    if ($query->get('post_type') !== 'vms_vendor') return;

    $orderby = (string) $query->get('orderby');

    if ($orderby === 'vms_payee') {
        $query->set('meta_key', '_vms_payee_legal_name');
        $query->set('orderby', 'meta_value');
        return;
    }

    if ($orderby === 'vms_w9') {
        $query->set('meta_key', '_vms_w9_status');
        $query->set('orderby', 'meta_value');
        return;
    }

    if ($orderby === 'vms_1099') {
        $query->set('meta_key', '_vms_requires_1099');
        $query->set('orderby', 'meta_value');
        return;
    }
});

/**
 * Vendor list columns: add Tax Status
 */

add_filter('manage_vms_vendor_posts_columns', function ($cols) {
    // Put Tax Status near the title
    $new = [];

    foreach ($cols as $k => $label) {
        $new[$k] = $label;

        if ($k === 'title') {
            $new['vms_tax_status'] = __('Tax Status', 'vms');
        }
    }

    // Fallback if title wasn't found for any reason
    if (!isset($new['vms_tax_status'])) {
        $new['vms_tax_status'] = __('Tax Status', 'vms');
    }

    return $new;
}, 20);

add_action('manage_vms_vendor_posts_custom_column', function ($col, $post_id) {
    if ($col !== 'vms_tax_status') return;

    if (!function_exists('vms_vendor_tax_profile_is_complete')) {
        echo '—';
        return;
    }

    $complete = vms_vendor_tax_profile_is_complete((int)$post_id);

    if ($complete) {
        echo '<span style="display:inline-block;padding:2px 8px;border-radius:999px;border:1px solid #a7f3d0;background:#ecfdf5;color:#065f46;font-weight:700;font-size:12px;">✅ ' .
            esc_html__('Complete', 'vms') .
        '</span>';
    } else {
        $missing = function_exists('vms_vendor_tax_profile_missing_items')
            ? vms_vendor_tax_profile_missing_items((int)$post_id)
            : [];

        $title = !empty($missing)
            ? esc_attr__('Missing: ', 'vms') . esc_attr(implode(', ', $missing))
            : esc_attr__('Incomplete', 'vms');

        echo '<span title="' . $title . '" style="display:inline-block;padding:2px 8px;border-radius:999px;border:1px solid #fed7aa;background:#fff7ed;color:#9a3412;font-weight:700;font-size:12px;">⚠️ ' .
            esc_html__('Incomplete', 'vms') .
        '</span>';
    }
}, 20, 2);

add_filter('manage_edit-vms_vendor_sortable_columns', function ($cols) {
    // Optional: sortable column stub (we’d need a meta query to truly sort)
    // Leaving it non-sortable is fine.
    return $cols;
});
/** -----------------------------
 * Filters (dropdowns)
 * ----------------------------- */
add_action('restrict_manage_posts', function () {
    global $typenow;
    if ($typenow !== 'vms_vendor') return;

    $w9 = isset($_GET['vms_w9_status']) ? sanitize_text_field(wp_unslash($_GET['vms_w9_status'])) : '';
    $r1099 = isset($_GET['vms_requires_1099']) ? sanitize_text_field(wp_unslash($_GET['vms_requires_1099'])) : '';

    $w9_opts = [
        ''              => __('All W-9 statuses', 'vms'),
        'not_requested' => __('W-9: Not Requested', 'vms'),
        'requested'     => __('W-9: Requested', 'vms'),
        'received'      => __('W-9: Received', 'vms'),
        'on_file'       => __('W-9: On File', 'vms'),
        'exempt'        => __('W-9: Exempt', 'vms'),
    ];

    echo '<select name="vms_w9_status" style="max-width:220px;">';
    foreach ($w9_opts as $val => $label) {
        printf(
            '<option value="%s"%s>%s</option>',
            esc_attr($val),
            selected($w9, $val, false),
            esc_html($label)
        );
    }
    echo '</select>';

    $r_opts = [
        ''        => __('All 1099 flags', 'vms'),
        'unknown' => __('1099: Unknown', 'vms'),
        'yes'     => __('1099: Yes', 'vms'),
        'no'      => __('1099: No', 'vms'),
    ];

    echo '&nbsp;<select name="vms_requires_1099" style="max-width:160px;">';
    foreach ($r_opts as $val => $label) {
        printf(
            '<option value="%s"%s>%s</option>',
            esc_attr($val),
            selected($r1099, $val, false),
            esc_html($label)
        );
    }
    echo '</select>';
});

add_action('pre_get_posts', function ($query) {
    if (!is_admin() || !$query->is_main_query()) return;
    if ($query->get('post_type') !== 'vms_vendor') return;

    $meta_query = (array) $query->get('meta_query');

    if (!empty($_GET['vms_w9_status'])) {
        $w9 = sanitize_text_field(wp_unslash($_GET['vms_w9_status']));
        $meta_query[] = [
            'key'     => '_vms_w9_status',
            'value'   => $w9,
            'compare' => '=',
        ];
    }

    if (!empty($_GET['vms_requires_1099'])) {
        $r = sanitize_text_field(wp_unslash($_GET['vms_requires_1099']));
        $meta_query[] = [
            'key'     => '_vms_requires_1099',
            'value'   => $r,
            'compare' => '=',
        ];
    }

    if (!empty($meta_query)) {
        $query->set('meta_query', $meta_query);
    }
});

/** -----------------------------
 * Little “pill” UI helpers
 * ----------------------------- */
function vms_render_w9_pill(string $status): string
{
    $map = [
        'not_requested' => ['Not Requested', '#f0f0f1', '#1d2327'],
        'requested'     => ['Requested',     '#fff8e5', '#7a4d00'],
        'received'      => ['Received',      '#e7f5ff', '#004b7a'],
        'on_file'       => ['On File',       '#e6f6ea', '#0a5f2a'],
        'exempt'        => ['Exempt',        '#f3e8ff', '#5b21b6'],
    ];
    $d = $map[$status] ?? [ucfirst($status), '#f0f0f1', '#1d2327'];

    return sprintf(
        '<span style="display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:600;background:%s;color:%s;">%s</span>',
        esc_attr($d[1]),
        esc_attr($d[2]),
        esc_html($d[0])
    );
}

function vms_render_1099_pill(string $req): string
{
    $map = [
        'unknown' => ['Unknown', '#f0f0f1', '#1d2327'],
        'yes'     => ['Yes',     '#e6f6ea', '#0a5f2a'],
        'no'      => ['No',      '#e7f5ff', '#004b7a'],
    ];
    $d = $map[$req] ?? [ucfirst($req), '#f0f0f1', '#1d2327'];

    return sprintf(
        '<span style="display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:600;background:%s;color:%s;">%s</span>',
        esc_attr($d[1]),
        esc_attr($d[2]),
        esc_html($d[0])
    );
}
