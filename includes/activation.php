<?php
if (!defined('ABSPATH')) exit;

if (!function_exists('vms_activate_plugin')) {
    function vms_activate_plugin()
    {
        // Create/ensure public pages
        vms_install_public_pages();
        // Optional: show your welcome notice
        update_option('vms_show_first_run_notice', '1');
        // Show a one-time "first run" admin notice after activation.
        update_option('vms_show_first_run_notice', '1', false);
    }
}

if (!function_exists('vms_deactivate_plugin')) {
    function vms_deactivate_plugin()
    {
        flush_rewrite_rules();
    }
}


/**
 * Ensure a WP Page exists by slug. Creates it if missing.
 * If a page exists in trash, restores it.
 *
 * @return int Page ID (0 on failure)
 */
function vms_ensure_page_exists(array $args): int
{
    $slug    = isset($args['slug']) ? sanitize_title((string)$args['slug']) : '';
    $title   = isset($args['title']) ? sanitize_text_field((string)$args['title']) : '';
    $content = isset($args['content']) ? (string)$args['content'] : '';

    if ($slug === '' || $title === '') return 0;

    // Look for an existing page by path (finds published/draft/private/etc)
    $existing = get_page_by_path($slug, OBJECT, 'page');

    if ($existing instanceof WP_Post) {
        $update = [
            'ID'           => $existing->ID,
            'post_title'   => $title,
            'post_content' => $content,
        ];

        // If trashed, restore to draft so it becomes visible again.
        if ($existing->post_status === 'trash') {
            $update['post_status'] = 'draft';
        }

        wp_update_post($update);

        return (int)$existing->ID;
    }

    // Create new page
    $new_id = wp_insert_post([
        'post_type'    => 'page',
        'post_status'  => 'publish',  // or 'draft' if you prefer
        'post_title'   => $title,
        'post_name'    => $slug,
        'post_content' => $content,
    ], true);

    if (is_wp_error($new_id) || !$new_id) return 0;

    return (int)$new_id;
}

/**
 * Create/ensure VMS public pages (Vendor Portal, Staff Portal, etc.)
 * Called from activation.
 */
function vms_install_public_pages(): void
{  
    // NOTE: Use whatever shortcodes your portal files define.
    // If your shortcode tags differ, change them here.
    $pages = [
        'vendor_application' => [
            'slug'    => 'vendor-application',
            'title'   => 'Vendor Application',
            'content' => "[vms_vendor_apply]\n",
        ],
        'vendor_portal' => [
            'slug'    => 'vendor-portal',
            'title'   => 'Vendor Portal',
            'content' => "[vms_vendor_portal]\n", // <-- adjust if needed
        ],
        'staff_portal' => [
            'slug'    => 'staff-portal',
            'title'   => 'Staff Portal',
            'content' => "[vms_staff_portal]\n", // <-- adjust if needed
        ],
    ];

    foreach ($pages as $key => $p) {
        $page_id = vms_ensure_page_exists($p);

        // Store the ID so we can link to it later, or detect it easily.
        if ($page_id > 0) {
            update_option('vms_page_' . $key, $page_id);
        }
    }
}
