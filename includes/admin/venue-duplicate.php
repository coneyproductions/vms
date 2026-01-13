<?php
if (!defined('ABSPATH')) exit;

/**
 * ==========================================================
 * VMS — Duplicate Venue (Admin)
 * ==========================================================
 *
 * Adds a "Duplicate" row action on the Venues list screen.
 * Creates a new venue as a DRAFT and copies:
 * - post fields (title/content/excerpt)
 * - all post meta
 * - taxonomy terms (if any)
 *
 * Safe-by-default:
 * - nonce protected
 * - capability checks
 * - copies meta except a small denylist
 */

add_filter('post_row_actions', 'vms_add_duplicate_venue_row_action', 10, 2);
function vms_add_duplicate_venue_row_action(array $actions, WP_Post $post): array
{
    if (!is_admin()) return $actions;

    if ($post->post_type !== 'vms_venue') return $actions;

    // Keep permissions tight: if you can edit venues, you can duplicate.
    if (!current_user_can('edit_post', $post->ID)) return $actions;

    $url = wp_nonce_url(
        add_query_arg(array(
            'vms_action' => 'duplicate_venue',
            'post_id'    => $post->ID,
        ), admin_url('admin.php')),
        'vms_duplicate_venue_' . $post->ID
    );

    $actions['vms_duplicate_venue'] = '<a href="' . esc_url($url) . '">Duplicate</a>';

    return $actions;
}

add_action('admin_init', 'vms_handle_duplicate_venue_action');
function vms_handle_duplicate_venue_action(): void
{
    if (!is_admin()) return;

    if (!isset($_GET['vms_action'], $_GET['post_id'])) return;
    if ($_GET['vms_action'] !== 'duplicate_venue') return;

    $source_id = absint($_GET['post_id']);
    if ($source_id <= 0) return;

    // Verify nonce
    check_admin_referer('vms_duplicate_venue_' . $source_id);

    $source = get_post($source_id);
    if (!$source || $source->post_type !== 'vms_venue') {
        wp_die('Invalid source venue.');
    }

    if (!current_user_can('edit_post', $source_id)) {
        wp_die('Not allowed.');
    }

    $new_id = vms_duplicate_post_with_meta_and_terms($source_id, array(
        'post_status' => 'draft',
        'post_title'  => $source->post_title . ' (Copy)',
    ));

    if (!$new_id) {
        wp_die('Duplicate failed.');
    }

    // Optional: Set a “just duplicated” admin notice
    if (function_exists('vms_add_admin_notice')) {
        vms_add_admin_notice('Venue duplicated. Update the title/settings and publish when ready.', 'success');
    }

    // Go straight to edit screen for the new venue
    wp_safe_redirect(admin_url('post.php?action=edit&post=' . $new_id));
    exit;
}

/**
 * Duplicate a post (any CPT) and copy all meta + terms.
 *
 * @param int   $source_id
 * @param array $override_args e.g. ['post_status'=>'draft','post_title'=>'New...']
 * @return int New post ID (0 on failure)
 */
function vms_duplicate_post_with_meta_and_terms(int $source_id, array $override_args = array()): int
{
    $source = get_post($source_id);
    if (!$source) return 0;

    // Build new post args from source
    $args = array(
        'post_type'    => $source->post_type,
        'post_status'  => 'draft',
        'post_title'   => $source->post_title . ' (Copy)',
        'post_content' => $source->post_content,
        'post_excerpt' => $source->post_excerpt,
        'post_author'  => get_current_user_id(),
    );

    // Allow overrides
    foreach ($override_args as $k => $v) {
        $args[$k] = $v;
    }

    $new_id = wp_insert_post($args, true);
    if (is_wp_error($new_id) || !$new_id) return 0;

    // Copy meta
    $all_meta = get_post_meta($source_id);
    $denylist = array(
        '_edit_lock',
        '_edit_last',
        '_wp_old_slug',
    );

    foreach ($all_meta as $meta_key => $values) {
        if (in_array($meta_key, $denylist, true)) continue;

        // If you have any meta that MUST be unique per venue, exclude it here.
        // Example:
        // if ($meta_key === '_vms_external_calendar_id') continue;

        foreach ($values as $value) {
            // Values come serialized sometimes; WordPress handles it fine.
            add_post_meta($new_id, $meta_key, maybe_unserialize($value));
        }
    }

    // Copy taxonomy terms (future-proof)
    $taxonomies = get_object_taxonomies($source->post_type);
    if (!empty($taxonomies)) {
        foreach ($taxonomies as $tax) {
            $term_ids = wp_get_object_terms($source_id, $tax, array('fields' => 'ids'));
            if (!is_wp_error($term_ids) && !empty($term_ids)) {
                wp_set_object_terms($new_id, $term_ids, $tax, false);
            }
        }
    }

    // Copy featured image if used
    $thumb_id = get_post_thumbnail_id($source_id);
    if ($thumb_id) {
        set_post_thumbnail($new_id, $thumb_id);
    }

    return (int) $new_id;
}