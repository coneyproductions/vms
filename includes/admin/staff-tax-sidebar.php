<?php
if (!defined('ABSPATH')) exit;

/**
 * VMS – Staff Tax Profile Sidebar (Admin)
 * Shows tax profile completion badge + missing items list in the sidebar.
 *
 * Assumes staff uses the same tax meta keys as vendors and the same helper:
 * - vms_vendor_tax_profile_missing_items($staff_id)
 * - vms_vendor_tax_profile_is_complete($staff_id)
 */

add_action('add_meta_boxes', function () {
    add_meta_box(
        'vms_staff_tax_status',
        __('Tax Profile Status', 'vms'),
        'vms_render_staff_tax_status_metabox',
        'vms_staff',
        'side',
        'low' // keeps Publish box above it
    );
});

function vms_render_staff_tax_status_metabox($post)
{
    $staff_id = (int) $post->ID;

    // Badge styles (safe to inline here)
    echo '<style>
.vms-badge{display:inline-block;padding:3px 8px;border-radius:999px;font-size:12px;font-weight:700;line-height:1.2;}
.vms-badge-ok{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;}
.vms-badge-miss{background:#fff7ed;color:#9a3412;border:1px solid #fed7aa;}
.vms-mini{color:#646970;font-size:12px;margin:8px 0 0;}
.vms-missing{margin:10px 0 0;padding-left:18px;}
</style>';

    if (!function_exists('vms_vendor_tax_profile_missing_items')) {
        echo '<p class="description">' . esc_html__('Tax helper functions are not loaded.', 'vms') . '</p>';
        return;
    }

    $missing = vms_vendor_tax_profile_missing_items($staff_id);

    if (empty($missing)) {
        echo '<p style="margin:0 0 8px;">';
        echo '<span class="vms-badge vms-badge-ok">' . esc_html__('Complete', 'vms') . '</span>';
        echo '</p>';

        echo '<p class="vms-mini">' . esc_html__('This staff member is cleared for assignment + payment processing.', 'vms') . '</p>';
        return;
    }

    echo '<p style="margin:0 0 8px;">';
    echo '<span class="vms-badge vms-badge-miss">' . esc_html__('Incomplete', 'vms') . '</span>';
    echo '</p>';

    echo '<p class="vms-mini">' . esc_html__('Missing required items:', 'vms') . '</p>';

    echo '<ul class="vms-missing">';
    foreach ($missing as $m) {
        echo '<li>' . esc_html($m) . '</li>';
    }
    echo '</ul>';

    echo '<p class="vms-mini" style="margin-top:10px;">' .
        esc_html__('Tip: staff can complete this in their portal Tax Profile tab (if linked).', 'vms') .
        '</p>';
}