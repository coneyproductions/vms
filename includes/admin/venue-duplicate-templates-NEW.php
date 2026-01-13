<?php
if (!defined('ABSPATH')) exit;

/**
 * Venue Template Cloner
 * - Adds UI to "Add New Venue" screen
 * - Handles POST to admin-post.php?action=vms_clone_venue
 */

add_action('add_meta_boxes_vms_venue', 'vms_register_venue_template_metabox');
function vms_register_venue_template_metabox(): void
{
    add_meta_box(
        'vms_venue_template_clone',
        __('Create Venue from Template', 'vms'),
        'vms_render_venue_template_metabox',
        'vms_venue',
        'side',
        'high'
    );
}

function vms_render_venue_template_metabox(\WP_Post $post): void
{
    if (!current_user_can('manage_options')) return;

    // Pull "template venues" however you're flagging them.
    // Example: title contains "Template" OR a meta key like _vms_is_template = 1
    $templates = get_posts(array(
        'post_type'      => 'vms_venue',
        'posts_per_page' => -1,
        'post_status'    => array('publish', 'draft'),
        'orderby'        => 'title',
        'order'          => 'ASC',
        'meta_query'     => array(
            array(
                'key'   => '_vms_is_template',
                'value' => '1',
            ),
        ),
    ));

    if (empty($templates)) {
        echo '<p class="description">No venue templates found yet.</p>';
        return;
    }

    echo '<p style="margin:0 0 10px;" class="description">';
    echo 'Pick a template to copy its settings into a new draft venue.';
    echo '</p>';

    echo '<label><strong>Template:</strong><br>';
    echo '<select name="vms_template_id" style="min-width:100%;">';
    foreach ($templates as $t) {
        echo '<option value="' . esc_attr($t->ID) . '">' . esc_html($t->post_title) . '</option>';
    }
    echo '</select>';
    echo '</label>';

    // nonce for handler
    wp_nonce_field('vms_clone_venue', 'vms_clone_venue_nonce');

    // The button submits the big WP form, but overrides destination to admin-post
    echo '<p style="margin-top:10px;">';
    echo '<button
        type="submit"
        class="button button-primary"
        formmethod="post"
        formaction="' . esc_url(admin_url('admin-post.php', 'https')) . '"
        name="action"
        value="vms_clone_venue"
    >Create from Template</button>';
    echo '</p>';
}

add_action('admin_post_vms_clone_venue', 'vms_handle_clone_venue');
function vms_handle_clone_venue(): void
{
    if (!current_user_can('manage_options')) {
        wp_die('Not allowed');
    }

    check_admin_referer('vms_clone_venue', 'vms_clone_venue_nonce');

    $template_id = isset($_POST['vms_template_id']) ? absint($_POST['vms_template_id']) : 0;
    if ($template_id <= 0) {
        wp_die('Missing template selection.');
    }

    $template = get_post($template_id);
    if (!$template || $template->post_type !== 'vms_venue') {
        wp_die('Invalid template.');
    }

    $new_id = wp_insert_post(array(
        'post_type'    => 'vms_venue',
        'post_status'  => 'draft',
        'post_title'   => $template->post_title . ' (Copy)',
        'post_content' => $template->post_content,
    ), true);

    if (is_wp_error($new_id) || !$new_id) {
        wp_die('Failed creating venue copy.');
    }

    // Copy all meta
    $meta = get_post_meta($template_id);
    foreach ($meta as $key => $values) {
        foreach ($values as $v) {
            add_post_meta($new_id, $key, maybe_unserialize($v));
        }
    }

    wp_safe_redirect(admin_url('post.php?post=' . (int)$new_id . '&action=edit'));
    exit;
}