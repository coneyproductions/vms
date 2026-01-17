<?php
if (!defined('ABSPATH')) exit;

/**
 * VMS — First Run (Welcome) Admin Notice
 *
 * Shows once after activation for admins, with a "Dismiss" that persists.
 *
 * Activation must set:
 *   update_option('vms_show_first_run_notice', '1', false);
 */

/**
 * Render the notice.
 */
add_action('admin_notices', function () {

    // Only in wp-admin, only for admins.
    if (!is_admin() || !current_user_can('manage_options')) {
        return;
    }

    // Only show if flagged by activation.
    if (get_option('vms_show_first_run_notice') !== '1') {
        return;   
    }

    $dismiss_url = wp_nonce_url(
        add_query_arg(array('vms_dismiss_first_run_notice' => '1')),
        'vms_dismiss_first_run_notice'
    );

    echo '<div class="notice notice-success vms-first-run-notice" style="padding:14px 16px;">';

    echo '<div style="display:flex;gap:14px;align-items:flex-start;">';
    echo '<div style="font-size:24px;line-height:1;">🎉</div>';
    echo '<div style="flex:1;">';

    echo '<p style="margin:0 0 6px;"><strong>Vendor Management System is activated.</strong></p>';
    echo '<p style="margin:0 0 10px;" class="description">';
    echo 'Here’s the recommended first-time setup checklist to get you running smoothly.';
    echo '</p>';

    echo '<ol style="margin:0 0 10px 18px;">';
    echo '<li><strong>Create a Venue</strong> (VMS → Venues)</li>';
    echo '<li><strong>Set Venue Defaults</strong> (hours, comp-by-day, holiday rules if using)</li>';
    echo '<li><strong>Create Vendors</strong> and fill out Tax Profile basics (VMS → Vendors)</li>';
    echo '<li><strong>Create Comp Packages</strong> (optional, but recommended)</li>';
    echo '<li><strong>Create your first Event Plan</strong> and try: Venue Defaults → Review → Lock Draft Pay</li>';
    echo '</ol>';

    echo '<p style="margin:0 0 10px;" class="description">';
    echo 'Tip: The “Locked Pay” snapshot protects each event from future default/package edits.';
    echo '</p>';

    echo '<p style="margin:0;">';
    echo '<a href="' . esc_url(admin_url('admin.php?page=vms-season-board')) . '" class="button button-primary">Open VMS</a> ';
    echo '<a href="' . esc_url($dismiss_url) . '" class="button">Dismiss</a>';
    echo '</p>';

    echo '</div>'; // flex child
    echo '</div>'; // flex
    echo '</div>';
});

/**
 * Handle dismiss action (nonce-protected).
 */
add_action('admin_init', function () {

    if (!is_admin() || !current_user_can('manage_options')) {
        return;
    }

    if (!isset($_GET['vms_dismiss_first_run_notice'])) {
        return;
    }

    check_admin_referer('vms_dismiss_first_run_notice');

    delete_option('vms_show_first_run_notice');

    wp_safe_redirect(remove_query_arg(array('vms_dismiss_first_run_notice', '_wpnonce')));
    exit;
});
