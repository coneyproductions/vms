<?php
if (!defined('ABSPATH')) exit;

/**
 * ==========================================================
 * VMS — Venue Duplication + Templates (Function-Based)
 * ==========================================================
 *
 * Features:
 *  1) Duplicate Venue
 *     - Row action: "Duplicate"
 *     - Submitbox button: "Duplicate Venue"
 *     - Creates a new DRAFT venue with copied meta/terms/thumbnail
 *
 *  2) Venue Templates
 *     - Sidebar checkbox: "Use this venue as a template"
 *     - "Create Venue from Template" panel on Add New Venue screen
 *     - Templates excluded from normal venue pickers everywhere else
 *
 *  3) Admin-post handlers (reliable POST routing)
 *     - admin-post.php?action=vms_create_venue_from_template
 *     - admin-post.php?action=vms_duplicate_venue
 *
 * Notes:
 * - Admin-only.
 * - Uses nonces + capability checks.
 */

define('VMS_VENUE_TEMPLATE_META_KEY', '_vms_is_template');

/**
 * Boot hooks
 */
add_action('init', 'vms_admin_venue_templates_boot');
function vms_admin_venue_templates_boot(): void
{
    if (!is_admin()) return;

    // Row action "Duplicate" in Venues list
    add_filter('post_row_actions', 'vms_add_duplicate_row_action', 10, 2);

    // Button in Publish box on venue edit screen
    add_action('post_submitbox_misc_actions', 'vms_add_duplicate_submitbox_button');

    // Template checkbox meta box
    add_action('add_meta_boxes', 'vms_add_template_metabox');
    add_action('save_post_vms_venue', 'vms_save_template_metabox', 10, 2);

    // "Create from Template" panel on Add New Venue screen
    add_action('edit_form_top', 'vms_render_create_from_template_panel');

    // Exclude templates from normal venue queries (except venue admin screens)
    add_action('pre_get_posts', 'vms_exclude_templates_from_venue_queries');

    // Handlers (admin-post)
    add_action('admin_post_vms_duplicate_venue', 'vms_handle_duplicate_venue');
    add_action('admin_post_vms_create_venue_from_template', 'vms_handle_create_venue_from_template');

    // Admin notices (transient-based)
    add_action('admin_notices', 'vms_render_admin_notices');
}

/**
 * ----------------------------------------------------------
 * Admin notices (persist across redirect)
 * ----------------------------------------------------------
 */
function vms_set_admin_notice(string $message, string $type = 'success'): void
{
    $user_id = get_current_user_id();
    if (!$user_id) return;

    $key = 'vms_admin_notices_' . $user_id;
    $notices = get_transient($key);
    if (!is_array($notices)) $notices = [];

    $notices[] = [
        'type' => $type, // success|error|warning|info
        'msg'  => $message,
    ];

    set_transient($key, $notices, 60);
}

function vms_render_admin_notices(): void
{
    $user_id = get_current_user_id();
    if (!$user_id) return;

    $key = 'vms_admin_notices_' . $user_id;
    $notices = get_transient($key);
    if (!is_array($notices) || empty($notices)) return;

    delete_transient($key);

    foreach ($notices as $n) {
        $type = isset($n['type']) ? (string)$n['type'] : 'success';
        $msg  = isset($n['msg']) ? (string)$n['msg'] : '';

        $class = 'notice notice-success';
        if ($type === 'error')   $class = 'notice notice-error';
        if ($type === 'warning') $class = 'notice notice-warning';
        if ($type === 'info')    $class = 'notice notice-info';

        echo '<div class="' . esc_attr($class) . '"><p>' . esc_html($msg) . '</p></div>';
    }
}

/**
 * ----------------------------------------------------------
 * 1) Duplicate Venue — Row Action (Venues list)
 * ----------------------------------------------------------
 */
function vms_add_duplicate_row_action(array $actions, $post): array
{
    if (!($post instanceof WP_Post)) return $actions;
    if ($post->post_type !== 'vms_venue') return $actions;
    if (!current_user_can('edit_post', $post->ID)) return $actions;

    $url = wp_nonce_url(
        admin_url('admin-post.php?action=vms_duplicate_venue&post=' . (int)$post->ID),
        'vms_duplicate_venue_' . (int)$post->ID
    );

    $new = [];
    $new['vms_duplicate_venue'] = '<a href="' . esc_url($url) . '">Duplicate</a>';

    return $new + $actions;
}

/**
 * ----------------------------------------------------------
 * 2) Duplicate Venue — Button on edit screen submitbox
 * ----------------------------------------------------------
 */
function vms_add_duplicate_submitbox_button(): void
{
    global $post;
    if (!($post instanceof WP_Post)) return;
    if ($post->post_type !== 'vms_venue') return;
    if (!current_user_can('edit_post', $post->ID)) return;

    $url = wp_nonce_url(
        admin_url('admin-post.php?action=vms_duplicate_venue&post=' . (int)$post->ID),
        'vms_duplicate_venue_' . (int)$post->ID
    );

    echo '<div class="misc-pub-section" style="padding-top:10px;">';
    echo '<a class="button" href="' . esc_url($url) . '">Duplicate Venue</a>';
    echo '<p class="description" style="margin:6px 0 0;">Creates a draft copy of this venue you can tweak and publish.</p>';
    echo '</div>';
}

/**
 * ----------------------------------------------------------
 * 3) Duplicate Venue — Handler (admin-post)
 * ----------------------------------------------------------
 */
function vms_handle_duplicate_venue(): void
{
    if (!current_user_can('edit_posts')) wp_die('Not allowed.');

    $source_id = isset($_GET['post']) ? absint($_GET['post']) : 0;
    if ($source_id <= 0) wp_die('Missing venue ID.');

    check_admin_referer('vms_duplicate_venue_' . $source_id);

    $source = get_post($source_id);
    if (!$source || $source->post_type !== 'vms_venue') wp_die('Invalid venue.');
    if (!current_user_can('edit_post', $source_id)) wp_die('Not allowed.');

    $new_id = vms_duplicate_venue_as_draft($source_id);
    if (!$new_id) {
        vms_set_admin_notice('Failed to duplicate venue.', 'error');
        wp_safe_redirect(admin_url('edit.php?post_type=vms_venue'));
        exit;
    }

    vms_set_admin_notice('Venue duplicated. Update details and publish when ready.', 'success');
    wp_safe_redirect(get_edit_post_link($new_id, ''));
    exit;
}

/**
 * Core duplication routine.
 */
function vms_duplicate_venue_as_draft(int $source_id): int
{
    $source = get_post($source_id);
    if (!$source) return 0;

    $new_post = [
        'post_type'    => $source->post_type,
        'post_status'  => 'draft',
        'post_title'   => vms_build_copy_title((string)$source->post_title),
        'post_content' => $source->post_content,
        'post_excerpt' => $source->post_excerpt,
        'post_author'  => get_current_user_id(),
    ];

    $new_id = wp_insert_post($new_post, true);
    if (is_wp_error($new_id) || !$new_id) return 0;

    // Copy featured image
    $thumb_id = get_post_thumbnail_id($source_id);
    if ($thumb_id) set_post_thumbnail($new_id, $thumb_id);

    // Copy taxonomies/terms (if any)
    $taxes = get_object_taxonomies($source->post_type, 'names');
    if (!empty($taxes)) {
        foreach ($taxes as $tax) {
            $terms = wp_get_object_terms($source_id, $tax, ['fields' => 'ids']);
            if (!is_wp_error($terms)) {
                wp_set_object_terms($new_id, $terms, $tax, false);
            }
        }
    }

    // Copy ALL post meta except some WP internals
    $meta = get_post_meta($source_id);
    $skip_keys = ['_edit_lock', '_edit_last'];

    foreach ($meta as $key => $vals) {
        if (in_array($key, $skip_keys, true)) continue;

        foreach ($vals as $v) {
            add_post_meta($new_id, $key, maybe_unserialize($v));
        }
    }

    /**
     * Hook for future: duplicate child data (comp defaults, holidays, etc.)
     */
    do_action('vms_duplicate_venue_children', $source_id, (int)$new_id);

    return (int)$new_id;
}

function vms_build_copy_title(string $title): string
{
    $title = trim($title);
    if ($title === '') $title = 'Venue';
    return 'Copy of ' . $title;
}

/**
 * ----------------------------------------------------------
 * 4) Templates — Metabox (checkbox)
 * ----------------------------------------------------------
 */
function vms_add_template_metabox(): void
{
    add_meta_box(
        'vms_venue_template_box',
        __('Venue Template', 'vms'),
        'vms_render_template_metabox',
        'vms_venue',
        'side',
        'high'
    );
}

function vms_render_template_metabox(WP_Post $post): void
{
    wp_nonce_field('vms_save_venue_template', 'vms_venue_template_nonce');

    $is_template = get_post_meta($post->ID, VMS_VENUE_TEMPLATE_META_KEY, true) === '1';

    echo '<p style="margin:0 0 10px;" class="description">';
    echo 'Templates are used as starting points when creating new venues.';
    echo '</p>';

    echo '<label style="display:flex;gap:10px;align-items:flex-start;">';
    echo '<input type="checkbox" name="vms_is_template" value="1" ' . checked($is_template, true, false) . ' />';
    echo '<span><strong>Use this venue as a template</strong><br><span class="description">Exclude from normal venue pickers.</span></span>';
    echo '</label>';
}

function vms_save_template_metabox(int $post_id, WP_Post $post): void
{
    if ($post->post_type !== 'vms_venue') return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    if (
        !isset($_POST['vms_venue_template_nonce']) ||
        !wp_verify_nonce($_POST['vms_venue_template_nonce'], 'vms_save_venue_template')
    ) {
        return;
    }

    $is_template = isset($_POST['vms_is_template']) ? '1' : '0';

    if ($is_template === '1') {
        update_post_meta($post_id, VMS_VENUE_TEMPLATE_META_KEY, '1');
    } else {
        delete_post_meta($post_id, VMS_VENUE_TEMPLATE_META_KEY);
    }
}

/**
 * ----------------------------------------------------------
 * 5) Templates — Create from Template panel (Add New Venue)
 * ----------------------------------------------------------
 */
function vms_render_create_from_template_panel(WP_Post $post): void
{
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen) return;

    // Only on Add New Venue screen (post-new.php?post_type=vms_venue)
    if ($screen->base !== 'post' || $screen->post_type !== 'vms_venue') return;
    if (!current_user_can('edit_posts')) return;

    // Only show on "Add New" (auto-draft)
    if (!empty($post->ID) && $post->post_status !== 'auto-draft') return;

    $templates = get_posts([
        'post_type'      => 'vms_venue',
        'post_status'    => ['publish', 'draft', 'private'],
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'meta_key'       => VMS_VENUE_TEMPLATE_META_KEY,
        'meta_value'     => '1',
    ]);

    if (empty($templates)) return;

    $action_url = admin_url('admin-post.php');

    echo '<div class="notice notice-info" style="padding:14px 16px;">';
    echo '<div style="display:flex;gap:12px;align-items:flex-start;">';
    echo '<div style="font-size:22px;line-height:1;">🧩</div>';
    echo '<div style="flex:1;">';
    echo '<p style="margin:0 0 8px;"><strong>Create Venue from Template</strong></p>';
    echo '<p class="description" style="margin:0 0 10px;">Pick a template to copy its settings into a new draft venue.</p>';

    echo '<form method="post" action="' . esc_url($action_url) . '">';
    echo '<input type="hidden" name="action" value="vms_create_venue_from_template" />';
    wp_nonce_field('vms_create_venue_from_template', 'vms_create_venue_from_template_nonce');

    echo '<label><strong>Template:</strong> ';
    echo '<select name="vms_template_id" style="min-width:320px;">';
    foreach ($templates as $t) {
        echo '<option value="' . esc_attr($t->ID) . '">' . esc_html($t->post_title) . '</option>';
    }
    echo '</select></label> ';
    echo '<button type="submit" class="button button-primary" style="margin-left:8px;">Create from Template</button>';
    echo '</form>';

    echo '</div></div>';
    echo '</div>';
}

/**
 * Handler (admin-post) — Create a new venue from a template
 */
function vms_handle_create_venue_from_template(): void
{
    if (!current_user_can('edit_posts')) wp_die('Not allowed.');

    if (
        !isset($_POST['vms_create_venue_from_template_nonce']) ||
        !wp_verify_nonce($_POST['vms_create_venue_from_template_nonce'], 'vms_create_venue_from_template')
    ) {
        wp_die('Invalid nonce.');
    }

    $template_id = isset($_POST['vms_template_id']) ? absint($_POST['vms_template_id']) : 0;
    if ($template_id <= 0) wp_die('Missing template.');

    $template = get_post($template_id);
    if (!$template || $template->post_type !== 'vms_venue') wp_die('Invalid template.');

    $is_template = get_post_meta($template_id, VMS_VENUE_TEMPLATE_META_KEY, true) === '1';
    if (!$is_template) wp_die('Selected venue is not marked as a template.');

    $new_id = vms_duplicate_venue_as_draft($template_id);
    if (!$new_id) {
        vms_set_admin_notice('Failed to create venue from template.', 'error');
        wp_safe_redirect(admin_url('post-new.php?post_type=vms_venue'));
        exit;
    }

    // IMPORTANT: venues created FROM templates should NOT themselves be templates
    delete_post_meta($new_id, VMS_VENUE_TEMPLATE_META_KEY);

    // Nicer default title
    wp_update_post([
        'ID'         => $new_id,
        'post_title' => 'New Venue — ' . $template->post_title,
        'post_name'  => sanitize_title('New Venue — ' . $template->post_title),
    ]);

    vms_set_admin_notice('Venue created from template. Update details and publish when ready.', 'success');
    wp_safe_redirect(get_edit_post_link($new_id, ''));
    exit;
}

/**
 * ----------------------------------------------------------
 * 6) Exclude templates from venue queries (safe + targeted)
 * ----------------------------------------------------------
 */
function vms_exclude_templates_from_venue_queries(WP_Query $query): void
{
    if (!$query->is_main_query()) return;

    $pt = $query->get('post_type');
    if ($pt !== 'vms_venue') return;

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;

    // Allow templates to show in Venue admin screens
    if (is_admin() && $screen) {
        $allowed = [
            'edit-vms_venue', // venue list
            'vms_venue',      // venue edit/add
        ];
        if (in_array($screen->id, $allowed, true)) {
            return;
        }
    }

    // Exclude templates everywhere else
    $meta_query = (array) $query->get('meta_query');
    $meta_query[] = [
        'relation' => 'OR',
        [
            'key'     => VMS_VENUE_TEMPLATE_META_KEY,
            'compare' => 'NOT EXISTS',
        ],
        [
            'key'     => VMS_VENUE_TEMPLATE_META_KEY,
            'value'   => '1',
            'compare' => '!=',
        ],
    ];

    $query->set('meta_query', $meta_query);
}