<?php
if (!defined('ABSPATH')) exit;

/**
 * Repair / recreate VMS public pages.
 * Safe: does not duplicate pages; restores from trash if needed.
 */
add_action('admin_post_vms_repair_pages', function () {
    if (!current_user_can('manage_options')) {
        wp_die('Not allowed.');
    }

    // Nonce check first (before doing anything)
    check_admin_referer(bvmgr_nonce_action_for_request('bvmgr_repair_pages', '_wpnonce'), '_wpnonce');

    // IMPORTANT: do not output anything in this handler.
    // No echo/print/var_dump, no stray whitespace outside <?php tags.

    $pages = function_exists('bvmgr_required_public_pages') ? bvmgr_required_public_pages() : [];
    $created = 0;
    $restored = 0;
    $ok = 0;

    foreach ($pages as $key => $spec) {
        if (empty($spec['slug']) || empty($spec['title'])) {
            continue;
        }

        // Detect trashed state BEFORE ensure, for reporting
        $existing = get_page_by_path($spec['slug'], OBJECT, 'page');
        $was_trashed = ($existing && $existing->post_status === 'trash');

        if (!function_exists('bvmgr_ensure_page_exists')) {
            // Bail gracefully: can't ensure without helper
            break;
        }

        $spec['managed_key'] = sanitize_key((string) $key);
        $spec['repair_existing'] = true;
        $page_id = (int) bvmgr_ensure_page_exists($spec);

        if ($page_id > 0) {
            update_option('vms_page_' . sanitize_key($key), $page_id);
            $ok++;

            if ($was_trashed) {
                $restored++;
            } elseif (!$existing) {
                $created++;
            }
        }
    }

    // Store a one-time notice WITHOUT requiring any helper function
    $user_id = get_current_user_id();
    if ($user_id) {
        $k = 'vms_admin_notices_' . $user_id;
        $notices = get_transient($k);
        if (!is_array($notices)) $notices = [];

        $notices[] = [
            'type' => 'success',
            'msg'  => sprintf(
                'Backstage Venue Manager pages repaired. OK: %d. Created: %d. Restored: %d.',
                (int) $ok,
                (int) $created,
                (int) $restored
            ),
        ];

        set_transient($k, $notices, 60);
    }

    // Redirect back to Settings
    wp_safe_redirect(admin_url('admin.php?page=vms-settings'));
    exit;
});
