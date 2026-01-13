<?php
if (!defined('ABSPATH')) exit;

/**
 * ==========================================================
 * VMS — Venue Duplication + Templates (Admin UX)
 * ==========================================================
 *
 * What this adds:
 *  1) Duplicate Venue:
 *     - Venues list row action: "Duplicate"
 *     - Venue edit screen button: "Duplicate Venue"
 *     - Duplicates the venue into a new DRAFT (copies meta/terms/thumbnail)
 *
 *  2) Venue Templates:
 *     - Checkbox "Use as Template" on Venue edit screen
 *     - "Create Venue from Template" panel on Add New Venue screen
 *     - Templates are excluded from normal venue queries everywhere
 *       EXCEPT the Venues admin screens (so templates are only used intentionally).
 *
 *  3) Future-proof child duplication:
 *     - Fires action: vms_duplicate_venue_children($source_id, $new_id)
 *       so we can later copy comp defaults, holidays, etc.
 *
 * Notes:
 * - This file is admin-only behavior. It won’t run on the frontend.
 * - Uses nonces + capability checks.
 */

class VMS_Admin_Venue_Duplicate_Templates
{
    const TEMPLATE_META_KEY = '_vms_is_template';

    public static function init(): void
    {
        if (!is_admin()) return;

        // Venue list row actions
        add_filter('post_row_actions', [__CLASS__, 'add_duplicate_row_action'], 10, 2);

        // Submitbox button on venue edit screen
        add_action('post_submitbox_misc_actions', [__CLASS__, 'add_duplicate_submitbox_button']);

        // Duplicate handler
        add_action('admin_action_vms_duplicate_venue', [__CLASS__, 'handle_duplicate_venue']);

        // Template checkbox metabox
        add_action('add_meta_boxes', [__CLASS__, 'add_template_metabox']);
        add_action('save_post_vms_venue', [__CLASS__, 'save_template_metabox'], 10, 2);

        // "Create from Template" panel on add-new venue screen
        add_action('edit_form_top', [__CLASS__, 'render_create_from_template_panel']);
        add_action('admin_action_vms_create_venue_from_template', [__CLASS__, 'handle_create_from_template']);

        // Exclude templates from normal venue queries (everywhere except Venue admin screens)
        add_action('pre_get_posts', [__CLASS__, 'exclude_templates_from_venue_queries']);

        // Admin notices for duplication/template creation
        add_action('admin_notices', [__CLASS__, 'render_admin_notices']);
    }

    /**
     * ----------------------------------------------------------
     * 1) Duplicate Venue — Row Action (Venues list)
     * ----------------------------------------------------------
     */
    public static function add_duplicate_row_action(array $actions, $post): array
    {
        if (!($post instanceof WP_Post)) return $actions;
        if ($post->post_type !== 'vms_venue') return $actions;
        if (!current_user_can('edit_post', $post->ID)) return $actions;

        $url = wp_nonce_url(
            admin_url('admin.php?action=vms_duplicate_venue&post=' . (int)$post->ID),
            'vms_duplicate_venue_' . (int)$post->ID
        );

        // Place it near the top for visibility.
        $new = [];
        $new['vms_duplicate_venue'] = '<a href="' . esc_url($url) . '">Duplicate</a>';

        // Keep existing actions
        return $new + $actions;
    }

    /**
     * ----------------------------------------------------------
     * 2) Duplicate Venue — Button on edit screen submitbox
     * ----------------------------------------------------------
     */
    public static function add_duplicate_submitbox_button(): void
    {
        global $post;
        if (!($post instanceof WP_Post)) return;
        if ($post->post_type !== 'vms_venue') return;
        if (!current_user_can('edit_post', $post->ID)) return;

        $url = wp_nonce_url(
            admin_url('admin.php?action=vms_duplicate_venue&post=' . (int)$post->ID),
            'vms_duplicate_venue_' . (int)$post->ID
        );

        echo '<div class="misc-pub-section" style="padding-top:10px;">';
        echo '<a class="button" href="' . esc_url($url) . '">Duplicate Venue</a>';
        echo '<p class="description" style="margin:6px 0 0;">Creates a draft copy of this venue you can tweak and publish.</p>';
        echo '</div>';
    }

    /**
     * ----------------------------------------------------------
     * 3) Duplicate Venue — Handler
     * ----------------------------------------------------------
     */
    public static function handle_duplicate_venue(): void
    {
        if (!current_user_can('edit_posts')) {
            wp_die('Not allowed.');
        }

        $source_id = isset($_GET['post']) ? absint($_GET['post']) : 0;
        if ($source_id <= 0) {
            wp_die('Missing venue ID.');
        }

        check_admin_referer('vms_duplicate_venue_' . $source_id);

        $source = get_post($source_id);
        if (!$source || $source->post_type !== 'vms_venue') {
            wp_die('Invalid venue.');
        }

        if (!current_user_can('edit_post', $source_id)) {
            wp_die('Not allowed.');
        }

        $new_id = self::duplicate_post_as_draft($source_id);
        if (!$new_id) {
            self::set_notice('Failed to duplicate venue.', 'error');
            wp_safe_redirect(admin_url('edit.php?post_type=vms_venue'));
            exit;
        }

        self::set_notice('Venue duplicated. Update details and publish when ready.', 'success');

        // Redirect directly to the new draft
        wp_safe_redirect(get_edit_post_link($new_id, ''));
        exit;
    }

    /**
     * Core duplication routine.
     */
    private static function duplicate_post_as_draft(int $source_id): int
    {
        $source = get_post($source_id);
        if (!$source) return 0;

        $new_post = [
            'post_type'    => $source->post_type,
            'post_status'  => 'draft',
            'post_title'   => self::build_copy_title($source->post_title),
            'post_content' => $source->post_content,
            'post_excerpt' => $source->post_excerpt,
            'post_author'  => get_current_user_id(),
        ];

        $new_id = wp_insert_post($new_post, true);
        if (is_wp_error($new_id) || !$new_id) return 0;

        // Copy featured image
        $thumb_id = get_post_thumbnail_id($source_id);
        if ($thumb_id) {
            set_post_thumbnail($new_id, $thumb_id);
        }

        // Copy taxonomies/terms (if your venues have any)
        $taxes = get_object_taxonomies($source->post_type, 'names');
        if (!empty($taxes)) {
            foreach ($taxes as $tax) {
                $terms = wp_get_object_terms($source_id, $tax, ['fields' => 'ids']);
                if (!is_wp_error($terms)) {
                    wp_set_object_terms($new_id, $terms, $tax, false);
                }
            }
        }

        // Copy ALL post meta except WordPress internals
        $meta = get_post_meta($source_id);
        $skip_keys = [
            '_edit_lock',
            '_edit_last',
        ];

        foreach ($meta as $key => $vals) {
            if (in_array($key, $skip_keys, true)) continue;

            // By default we DO copy template flag too.
            // If you prefer duplicates to NOT be templates automatically,
            // uncomment the next line:
            // if ($key === self::TEMPLATE_META_KEY) continue;

            foreach ($vals as $v) {
                // get_post_meta returns arrays of strings; keep raw
                add_post_meta($new_id, $key, maybe_unserialize($v));
            }
        }

        /**
         * Hook for future: duplicate "child" data that belongs to a venue
         * (comp defaults, holiday schedules, etc.)
         */
        do_action('vms_duplicate_venue_children', $source_id, $new_id);

        return (int)$new_id;
    }

    private static function build_copy_title(string $title): string
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
    public static function add_template_metabox(): void
    {
        add_meta_box(
            'vms_venue_template_box',
            __('Venue Template', 'vms'),
            [__CLASS__, 'render_template_metabox'],
            'vms_venue',
            'side',
            'high'
        );
    }

    public static function render_template_metabox(WP_Post $post): void
    {
        wp_nonce_field('vms_save_venue_template', 'vms_venue_template_nonce');

        $is_template = get_post_meta($post->ID, self::TEMPLATE_META_KEY, true) === '1';

        echo '<p style="margin:0 0 10px;" class="description">';
        echo 'Templates are used as starting points when creating new venues.';
        echo '</p>';

        echo '<label style="display:flex;gap:10px;align-items:flex-start;">';
        echo '<input type="checkbox" name="vms_is_template" value="1" ' . checked($is_template, true, false) . ' />';
        echo '<span><strong>Use this venue as a template</strong><br><span class="description">Exclude from normal venue pickers.</span></span>';
        echo '</label>';
    }

    public static function save_template_metabox(int $post_id, WP_Post $post): void
    {
        if ($post->post_type !== 'vms_venue') return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        if (!isset($_POST['vms_venue_template_nonce']) ||
            !wp_verify_nonce($_POST['vms_venue_template_nonce'], 'vms_save_venue_template')
        ) {
            return;
        }

        $is_template = isset($_POST['vms_is_template']) ? '1' : '0';

        if ($is_template === '1') {
            update_post_meta($post_id, self::TEMPLATE_META_KEY, '1');
        } else {
            delete_post_meta($post_id, self::TEMPLATE_META_KEY);
        }
    }

    /**
     * ----------------------------------------------------------
     * 5) Templates — Create from Template panel (Add New Venue)
     * ----------------------------------------------------------
     */
    public static function render_create_from_template_panel(WP_Post $post): void
    {
        // Only on "Add New Venue" screen.
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen) return;

        // post-new.php for vms_venue
        if ($screen->base !== 'post' || $screen->post_type !== 'vms_venue') return;
        if (!current_user_can('edit_posts')) return;

        // Only show on Add New (not edit existing)
        if (!empty($post->ID) && $post->post_status !== 'auto-draft') return;

        $templates = get_posts([
            'post_type'      => 'vms_venue',
            'post_status'    => ['publish', 'draft', 'private'],
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'meta_key'       => self::TEMPLATE_META_KEY,
            'meta_value'     => '1',
        ]);

        if (empty($templates)) {
            // No templates yet; nothing to render.
            return;
        }

        $action_url = wp_nonce_url(
            admin_url('admin.php?action=vms_create_venue_from_template'),
            'vms_create_venue_from_template'
        );

        echo '<div class="notice notice-info" style="padding:14px 16px;">';
        echo '<div style="display:flex;gap:12px;align-items:flex-start;">';
        echo '<div style="font-size:22px;line-height:1;">🧩</div>';
        echo '<div style="flex:1;">';
        echo '<p style="margin:0 0 8px;"><strong>Create Venue from Template</strong></p>';
        echo '<p class="description" style="margin:0 0 10px;">Pick a template to copy its settings into a new draft venue.</p>';

        echo '<form method="post" action="' . esc_url($action_url) . '">';
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

    public static function handle_create_from_template(): void
    {
        if (!current_user_can('edit_posts')) {
            wp_die('Not allowed.');
        }

        check_admin_referer('vms_create_venue_from_template');

        $template_id = isset($_POST['vms_template_id']) ? absint($_POST['vms_template_id']) : 0;
        if ($template_id <= 0) {
            wp_die('Missing template.');
        }

        $template = get_post($template_id);
        if (!$template || $template->post_type !== 'vms_venue') {
            wp_die('Invalid template.');
        }

        $is_template = get_post_meta($template_id, self::TEMPLATE_META_KEY, true) === '1';
        if (!$is_template) {
            wp_die('Selected venue is not marked as a template.');
        }

        $new_id = self::duplicate_post_as_draft($template_id);
        if (!$new_id) {
            self::set_notice('Failed to create venue from template.', 'error');
            wp_safe_redirect(admin_url('post-new.php?post_type=vms_venue'));
            exit;
        }

        // IMPORTANT: A venue created from template should NOT itself be a template by default.
        delete_post_meta($new_id, self::TEMPLATE_META_KEY);

        // Nicer default name: "New Venue — {Template Name}"
        wp_update_post([
            'ID'         => $new_id,
            'post_title' => 'New Venue — ' . $template->post_title,
            'post_name'  => sanitize_title('New Venue — ' . $template->post_title),
        ]);

        self::set_notice('Venue created from template. Update details and publish when ready.', 'success');
        wp_safe_redirect(get_edit_post_link($new_id, ''));
        exit;
    }

    /**
     * ----------------------------------------------------------
     * 6) Exclude templates from venue queries (safe + targeted)
     * ----------------------------------------------------------
     *
     * Goal: Templates shouldn't show up in normal venue pickers.
     * BUT: We DO want templates visible on:
     *  - Venues list (edit.php?post_type=vms_venue)
     *  - Venue edit screen
     *  - Add New Venue screen (to pick templates)
     */
    public static function exclude_templates_from_venue_queries(WP_Query $query): void
    {
        if (!$query->is_main_query() && !is_admin()) {
            // Frontend: still exclude templates.
            // (We also exclude on admin, but controlled below.)
        }

        $pt = $query->get('post_type');
        if ($pt !== 'vms_venue') return;

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;

        // If we're in the Venues admin screens, do NOT exclude templates.
        if (is_admin() && $screen) {
            $allowed = [
                'edit-vms_venue', // Venues list
                'vms_venue',      // Venue edit/add screen
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
                'key'     => self::TEMPLATE_META_KEY,
                'compare' => 'NOT EXISTS',
            ],
            [
                'key'     => self::TEMPLATE_META_KEY,
                'value'   => '1',
                'compare' => '!=',
            ],
        ];
        $query->set('meta_query', $meta_query);
    }

    /**
     * ----------------------------------------------------------
     * 7) Admin Notices (persist across redirect)
     * ----------------------------------------------------------
     */
    private static function set_notice(string $message, string $type = 'success'): void
    {
        $user_id = get_current_user_id();
        if (!$user_id) return;

        $notices = get_transient('vms_admin_notices_' . $user_id);
        if (!is_array($notices)) $notices = [];

        $notices[] = [
            'type' => $type, // success|error|warning|info
            'msg'  => $message,
        ];

        set_transient('vms_admin_notices_' . $user_id, $notices, 60);
    }

    public static function render_admin_notices(): void
    {
        $user_id = get_current_user_id();
        if (!$user_id) return;

        $notices = get_transient('vms_admin_notices_' . $user_id);
        if (!is_array($notices) || empty($notices)) return;

        delete_transient('vms_admin_notices_' . $user_id);

        foreach ($notices as $n) {
            $type = isset($n['type']) ? (string)$n['type'] : 'success';
            $msg  = isset($n['msg']) ? (string)$n['msg'] : '';

            $class = 'notice notice-success';
            if ($type === 'error') $class = 'notice notice-error';
            if ($type === 'warning') $class = 'notice notice-warning';
            if ($type === 'info') $class = 'notice notice-info';

            echo '<div class="' . esc_attr($class) . '"><p>' . esc_html($msg) . '</p></div>';
        }
    }
}

// Boot it.
VMS_Admin_Venue_Duplicate_Templates::init();