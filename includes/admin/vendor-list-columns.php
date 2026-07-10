<?php
if (!defined('ABSPATH')) exit;

/**
 * VMS – Vendor Admin List Columns + Filters
 * Adds W-9 / 1099 / Payee columns to vms_vendor list view,
 * plus dropdown filters.
 */

function vms_vendor_list_columns_query_arg(string $key): string
{
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only vendor admin filter state only changes list display.
    if (!isset($_GET[$key])) {
        return '';
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only vendor admin filter state is unslashed here and sanitized by the caller.
    return (string) wp_unslash($_GET[$key]);
}

function vms_vendor_list_columns_pill_allowed_html(): array
{
    return array(
        'span' => array(
            'class' => true,
            'title' => true,
        ),
    );
}

/** -----------------------------
 * Columns
 * ----------------------------- */
add_filter('manage_vms_vendor_posts_columns', function ($columns) {
    $new = [];

    foreach ($columns as $key => $label) {
        // Insert after Title
        $new[$key] = $label;

        if ($key === 'title') {
            $new['vms_payee']   = __('Payee (Legal)', 'backstage-venue-manager');
            $new['vms_w9']      = __('W-9', 'backstage-venue-manager');
            $new['vms_1099']    = __('1099', 'backstage-venue-manager');
        }
    }

    // If for some reason title didn't exist, append
    if (!isset($new['vms_w9'])) {
        $new['vms_payee'] = __('Payee (Legal)', 'backstage-venue-manager');
        $new['vms_w9']    = __('W-9', 'backstage-venue-manager');
        $new['vms_1099']  = __('1099', 'backstage-venue-manager');
    }

    return $new;
});

add_action('manage_vms_vendor_posts_custom_column', function ($column, $post_id) {
    if ($column === 'vms_payee') {
        $payee = (string) get_post_meta($post_id, '_vms_payee_legal_name', true);
        echo $payee ? esc_html($payee) : '<span class="vms-vendor-col-muted">—</span>';
        return;
    }

    if ($column === 'vms_w9') {
        $status = (string) get_post_meta($post_id, '_vms_w9_status', true);
        if (!$status) $status = 'not_requested';

        echo wp_kses(vms_render_w9_pill($status), vms_vendor_list_columns_pill_allowed_html());
        return;
    }

    if ($column === 'vms_1099') {
        $req = (string) get_post_meta($post_id, '_vms_requires_1099', true);
        if (!$req) $req = 'unknown';

        echo wp_kses(vms_render_1099_pill($req), vms_vendor_list_columns_pill_allowed_html());
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
            $new['vms_tax_status'] = __('Tax Status', 'backstage-venue-manager');
        }
    }

    // Fallback if title wasn't found for any reason
    if (!isset($new['vms_tax_status'])) {
        $new['vms_tax_status'] = __('Tax Status', 'backstage-venue-manager');
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
        $markup = '<span class="vms-vendor-tax-pill vms-vendor-tax-pill-complete">✅ ' .
            esc_html__('Complete', 'backstage-venue-manager') .
        '</span>';
        echo wp_kses($markup, vms_vendor_list_columns_pill_allowed_html());
    } else {
        $missing = function_exists('vms_vendor_tax_profile_missing_items')
            ? vms_vendor_tax_profile_missing_items((int)$post_id)
            : [];

        $title = !empty($missing)
            ? esc_attr__('Missing: ', 'backstage-venue-manager') . esc_attr(implode(', ', $missing))
            : esc_attr__('Incomplete', 'backstage-venue-manager');

        $markup = '<span title="' . $title . '" class="vms-vendor-tax-pill vms-vendor-tax-pill-incomplete">⚠️ ' .
            esc_html__('Incomplete', 'backstage-venue-manager') .
        '</span>';
        echo wp_kses($markup, vms_vendor_list_columns_pill_allowed_html());
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

    $w9 = sanitize_text_field(vms_vendor_list_columns_query_arg('vms_w9_status'));
    $r1099 = sanitize_text_field(vms_vendor_list_columns_query_arg('vms_requires_1099'));

    $w9_opts = [
        ''              => __('All W-9 statuses', 'backstage-venue-manager'),
        'not_requested' => __('W-9: Not Requested', 'backstage-venue-manager'),
        'requested'     => __('W-9: Requested', 'backstage-venue-manager'),
        'received'      => __('W-9: Received', 'backstage-venue-manager'),
        'on_file'       => __('W-9: On File', 'backstage-venue-manager'),
        'exempt'        => __('W-9: Exempt', 'backstage-venue-manager'),
    ];

    echo '<select name="vms_w9_status" class="vms-vendor-filter-w9-status">';
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
        ''        => __('All 1099 flags', 'backstage-venue-manager'),
        'unknown' => __('1099: Unknown', 'backstage-venue-manager'),
        'yes'     => __('1099: Yes', 'backstage-venue-manager'),
        'no'      => __('1099: No', 'backstage-venue-manager'),
    ];

    echo '&nbsp;<select name="vms_requires_1099" class="vms-vendor-filter-1099">';
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

    $w9 = sanitize_text_field(vms_vendor_list_columns_query_arg('vms_w9_status'));
    if ($w9 !== '') {
        $meta_query[] = [
            'key'     => '_vms_w9_status',
            'value'   => $w9,
            'compare' => '=',
        ];
    }

    $r = sanitize_text_field(vms_vendor_list_columns_query_arg('vms_requires_1099'));
    if ($r !== '') {
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
        'not_requested' => ['Not Requested', 'vms-vendor-mini-pill-not-requested'],
        'requested'     => ['Requested',     'vms-vendor-mini-pill-requested'],
        'received'      => ['Received',      'vms-vendor-mini-pill-received'],
        'on_file'       => ['On File',       'vms-vendor-mini-pill-on-file'],
        'exempt'        => ['Exempt',        'vms-vendor-mini-pill-exempt'],
    ];
    $d = $map[$status] ?? [ucfirst($status), 'vms-vendor-mini-pill-default'];

    return sprintf(
        '<span class="vms-vendor-mini-pill %s">%s</span>',
        esc_attr($d[1]),
        esc_html($d[0])
    );
}

function vms_render_1099_pill(string $req): string
{
    $map = [
        'unknown' => ['Unknown', 'vms-vendor-mini-pill-default'],
        'yes'     => ['Yes',     'vms-vendor-mini-pill-on-file'],
        'no'      => ['No',      'vms-vendor-mini-pill-received'],
    ];
    $d = $map[$req] ?? [ucfirst($req), 'vms-vendor-mini-pill-default'];

    return sprintf(
        '<span class="vms-vendor-mini-pill %s">%s</span>',
        esc_attr($d[1]),
        esc_html($d[0])
    );
}
