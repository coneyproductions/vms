<?php

/**
 * Vendor Applications (Procedural)
 * - CPT: vms_vendor_app
 * - Shortcode: [vms_vendor_apply]
 * - Admin UI: list + pending bubble + approve/reject
 */

if (!defined('ABSPATH')) exit;

/**
 * Parent menu slug used by VMS (your existing top-level menu).
 * If this ever changes, update this constant in ONE place.
 */
if (!defined('VMS_ADMIN_PARENT_SLUG')) {
    define('VMS_ADMIN_PARENT_SLUG', 'vms-season-board');
}

/**
 * Post type slug for vendor applications.
 */
if (!defined('VMS_VENDOR_APP_CPT')) {
    define('VMS_VENDOR_APP_CPT', 'vms_vendor_app');
}

/**
 * Vendor CPT slug (must match your vendors system).
 */
if (!defined('VMS_VENDOR_CPT')) {
    define('VMS_VENDOR_CPT', 'vms_vendor');
}

/**
 * Application statuses stored in post meta.
 */
function vms_vendor_app_statuses(): array
{
    return array(
        'pending'  => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    );
}

/**
 * Get application status (meta-based).
 */
function vms_vendor_app_get_status(int $app_id): string
{
    $s = (string) get_post_meta($app_id, '_vms_app_status', true);
    if (!$s) $s = 'pending';
    $all = vms_vendor_app_statuses();
    return isset($all[$s]) ? $s : 'pending';
}

/**
 * Set application status.
 */
function vms_vendor_app_set_status(int $app_id, string $status): void
{
    $all = vms_vendor_app_statuses();
    if (!isset($all[$status])) $status = 'pending';
    update_post_meta($app_id, '_vms_app_status', $status);
}

/**
 * Count pending applications.
 */
function vms_vendor_app_count_pending(): int
{
    $q = new WP_Query(array(
        'post_type'      => VMS_VENDOR_APP_CPT,
        'post_status'    => array('publish', 'draft', 'pending', 'private'),
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_query'     => array(
            array(
                'key'     => '_vms_app_status',
                'value'   => 'pending',
                'compare' => '=',
            ),
        ),
    ));
    return (int) $q->found_posts;
}

/**
 * Register the Vendor Applications CPT.
 */
add_action('init', 'vms_register_vendor_applications_cpt');
function vms_register_vendor_applications_cpt(): void
{
    $labels = array(
        'name'               => __('Vendor Applications', 'vms'),
        'singular_name'      => __('Vendor Application', 'vms'),
        'add_new'            => __('Add New', 'vms'),
        'add_new_item'       => __('Add New Application', 'vms'),
        'edit_item'          => __('Edit Application', 'vms'),
        'new_item'           => __('New Application', 'vms'),
        'view_item'          => __('View Application', 'vms'),
        'search_items'       => __('Search Applications', 'vms'),
        'not_found'          => __('No applications found.', 'vms'),
        'not_found_in_trash' => __('No applications found in Trash.', 'vms'),
        'menu_name'          => __('Applications', 'vms'),
    );

    register_post_type(VMS_VENDOR_APP_CPT, array(
        'labels'             => $labels,
        'public'             => false,
        'show_ui'            => true,
        'show_in_menu'       => 'vms', // attach under VMS menu
        // 'show_in_menu'       => VMS_ADMIN_PARENT_SLUG, // attach under VMS menu
        'capability_type'    => 'post',
        'supports'           => array('title'),
        'menu_position'      => 25,
        'has_archive'        => false,
        'rewrite'            => false,
    ));
}

/**
 * Ensure submenu label shows pending bubble count.
 * (This runs on admin_menu and rewrites the submenu label.)
 */
add_action('admin_menu', 'vms_vendor_applications_add_pending_bubble', 999);
function vms_vendor_applications_add_pending_bubble(): void
{
    global $submenu;

    if (!isset($submenu[VMS_ADMIN_PARENT_SLUG]) || !is_array($submenu[VMS_ADMIN_PARENT_SLUG])) {
        return;
    }

    $pending = vms_vendor_app_count_pending();
    if ($pending <= 0) return;

    foreach ($submenu[VMS_ADMIN_PARENT_SLUG] as $i => $item) {
        // $item[2] is the slug
        // For CPT submenus, slug often looks like "edit.php?post_type=xxx"
        if (!empty($item[2]) && strpos($item[2], 'edit.php?post_type=' . VMS_VENDOR_APP_CPT) !== false) {
            $submenu[VMS_ADMIN_PARENT_SLUG][$i][0] = $item[0] . ' <span class="awaiting-mod count-' . (int)$pending . '"><span class="pending-count">' . (int)$pending . '</span></span>';
            break;
        }
    }
}

/**
 * Admin columns for applications list.
 */
add_filter('manage_' . VMS_VENDOR_APP_CPT . '_posts_columns', 'vms_vendor_applications_columns');
function vms_vendor_applications_columns($cols)
{
    $new = array();
    foreach ($cols as $k => $label) {
        if ($k === 'title') {
            $new[$k] = $label;
            $new['vms_app_type']   = __('Type', 'vms');
            $new['vms_app_email']  = __('Email', 'vms');
            $new['vms_app_status'] = __('Status', 'vms');
        } else {
            $new[$k] = $label;
        }
    }
    return $new;
}

add_action('manage_' . VMS_VENDOR_APP_CPT . '_posts_custom_column', 'vms_vendor_applications_render_columns', 10, 2);
function vms_vendor_applications_render_columns($col, $post_id)
{
    if ($col === 'vms_app_type') {
        echo esc_html(get_post_meta($post_id, '_vms_app_vendor_type', true));
        return;
    }
    if ($col === 'vms_app_email') {
        $email = (string) get_post_meta($post_id, '_vms_app_email', true);
        if ($email) echo esc_html($email);
        return;
    }
    if ($col === 'vms_app_status') {
        $status = vms_vendor_app_get_status((int)$post_id);
        $labels = vms_vendor_app_statuses();
        $label  = $labels[$status] ?? ucfirst($status);

        $class = 'vms-pill-grey';
        if ($status === 'pending')  $class = 'vms-pill-yellow';
        if ($status === 'approved') $class = 'vms-pill-green';
        if ($status === 'rejected') $class = 'vms-pill-red';

        echo '<span class="vms-status-pill ' . esc_attr($class) . '">' . esc_html($label) . '</span>';
        return;
    }
}

/**
 * Admin CSS for pills.
 */
add_action('admin_head-edit.php', 'vms_vendor_applications_admin_css');
function vms_vendor_applications_admin_css(): void
{
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->post_type !== VMS_VENDOR_APP_CPT) return;
?>
    <style>
        .vms-status-pill {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            line-height: 1.4;
            text-transform: uppercase;
            letter-spacing: .03em;
        }

        .vms-pill-grey {
            background: #e5e7eb;
            color: #374151;
        }

        .vms-pill-yellow {
            background: #fef3c7;
            color: #92400e;
        }

        .vms-pill-green {
            background: #d1fae5;
            color: #065f46;
        }

        .vms-pill-red {
            background: #fee2e2;
            color: #991b1b;
        }

        .column-vms_app_status {
            width: 120px;
        }

        .column-vms_app_email {
            width: 220px;
        }

        .column-vms_app_type {
            width: 120px;
        }
    </style>
<?php
}

/**
 * Add Approve/Reject row actions for pending applications.
 */
add_filter('post_row_actions', 'vms_vendor_applications_row_actions', 10, 2);
function vms_vendor_applications_row_actions($actions, $post)
{
    if (empty($post->post_type) || $post->post_type !== VMS_VENDOR_APP_CPT) return $actions;
    if (!current_user_can('edit_posts')) return $actions;

    $app_id  = (int) $post->ID;
    $status  = vms_vendor_app_get_status($app_id);

    $vendor_id = (int) get_post_meta($app_id, '_vms_vendor_id', true);
    $vendor_ok = ($vendor_id > 0 && get_post_type($vendor_id) === VMS_VENDOR_CPT);

    // Normal pending actions
    if ($status === 'pending') {
        $approve_url = wp_nonce_url(
            admin_url('admin-post.php?action=vms_vendor_app_approve&app_id=' . $app_id),
            'vms_vendor_app_approve_' . $app_id
        );
        $reject_url = wp_nonce_url(
            admin_url('admin-post.php?action=vms_vendor_app_reject&app_id=' . $app_id),
            'vms_vendor_app_reject_' . $app_id
        );

        $actions['vms_approve'] = '<a href="' . esc_url($approve_url) . '">' . esc_html__('Approve', 'vms') . '</a>';
        $actions['vms_reject']  = '<a href="' . esc_url($reject_url) . '" style="color:#b91c1c;">' . esc_html__('Reject', 'vms') . '</a>';
        return $actions;
    }

    // NEW: repair action if approved but vendor missing
    if ($status === 'approved' && !$vendor_ok) {
        $repair_url = wp_nonce_url(
            admin_url('admin-post.php?action=vms_vendor_app_repair_vendor&app_id=' . $app_id),
            'vms_vendor_app_repair_vendor_' . $app_id
        );

        $actions['vms_repair_vendor'] = '<a href="' . esc_url($repair_url) . '">' . esc_html__('Create Vendor', 'vms') . '</a>';
    }

    // NEW: resync action if approved and vendor exists
    if ($status === 'approved' && $vendor_ok) {
        $resync_url = wp_nonce_url(
            admin_url('admin-post.php?action=vms_vendor_app_resync_vendor&app_id=' . $app_id),
            'vms_vendor_app_resync_vendor_' . $app_id
        );
        $actions['vms_resync_vendor'] = '<a href="' . esc_url($resync_url) . '">' . esc_html__('Re-sync Vendor Data', 'vms') . '</a>';
    }

    // Optional: if approved but vendor missing, you can still offer resync (it will create + sync)
    if ($status === 'approved' && !$vendor_ok) {
        $resync_url = wp_nonce_url(
            admin_url('admin-post.php?action=vms_vendor_app_resync_vendor&app_id=' . $app_id),
            'vms_vendor_app_resync_vendor_' . $app_id
        );
        $actions['vms_resync_vendor'] = '<a href="' . esc_url($resync_url) . '">' . esc_html__('Create + Sync Vendor', 'vms') . '</a>';
    }

    return $actions;
}


/**
 * Metabox on application edit screen (Approve/Reject).
 */
add_action('add_meta_boxes', 'vms_vendor_applications_metaboxes');
function vms_vendor_applications_metaboxes(): void
{
    add_meta_box(
        'vms_app_details',
        __('Application Details', 'vms'),
        'vms_vendor_applications_metabox_details',
        VMS_VENDOR_APP_CPT,
        'normal',
        'high'
    );

    add_meta_box(
        'vms_app_actions',
        __('Application Actions', 'vms'),
        'vms_vendor_applications_metabox_actions',
        VMS_VENDOR_APP_CPT,
        'side',
        'high'
    );
}

function vms_vendor_applications_metabox_details($post): void
{
    $fields = array(
        'Vendor Type' => '_vms_app_vendor_type',
        'Email'       => '_vms_app_email',
        'Location'    => '_vms_app_location',
        'Rate'        => '_vms_app_rate',
        'EPK'         => '_vms_app_epk',
        'Cuisine'     => '_vms_app_cuisine',
        'Menu'        => '_vms_app_menu',
        'Social'      => '_vms_app_social',
        'Notes'       => '_vms_app_notes',
    );

    echo '<table class="widefat striped" style="margin-top:8px;">';
    foreach ($fields as $label => $key) {
        $val = get_post_meta($post->ID, $key, true);
        if ($val === '' || $val === null) continue;

        echo '<tr><th style="width:160px;">' . esc_html($label) . '</th><td>';
        if (in_array($key, array('_vms_app_epk', '_vms_app_menu'), true) && filter_var($val, FILTER_VALIDATE_URL)) {
            echo '<a href="' . esc_url($val) . '" target="_blank" rel="noopener">' . esc_html($val) . '</a>';
        } else {
            echo nl2br(esc_html((string)$val));
        }
        echo '</td></tr>';
    }
    echo '</table>';
}

function vms_vendor_applications_metabox_actions($post): void
{
    $status = vms_vendor_app_get_status((int)$post->ID);
    $labels = vms_vendor_app_statuses();
    $vendor_id = (int) get_post_meta($post->ID, '_vms_vendor_id', true);

    echo '<p><strong>' . esc_html__('Status:', 'vms') . '</strong> ' . esc_html($labels[$status] ?? $status) . '</p>';

    if ($vendor_id > 0) {
        $edit_vendor = get_edit_post_link($vendor_id, '');
        if ($edit_vendor) {
            echo '<p><strong>' . esc_html__('Linked Vendor:', 'vms') . '</strong><br>';
            echo '<a href="' . esc_url($edit_vendor) . '">' . esc_html(get_the_title($vendor_id)) . '</a></p>';
        }
    }

    $vendor_ok = ($vendor_id > 0 && get_post_type($vendor_id) === VMS_VENDOR_CPT);

    if ($status === 'approved' && !$vendor_ok) {
        $repair_url = wp_nonce_url(
            admin_url('admin-post.php?action=vms_vendor_app_repair_vendor&app_id=' . (int)$post->ID),
            'vms_vendor_app_repair_vendor_' . (int)$post->ID
        );
        echo '<p><a class="button button-primary" href="' . esc_url($repair_url) . '">'
            . esc_html__('Create Vendor Now', 'vms')
            . '</a></p>';
        echo '<p style="color:#92400e;margin-top:-6px;">'
            . esc_html__('This application is approved, but no vendor exists yet.', 'vms')
            . '</p>';
    }

    if (!current_user_can('edit_posts')) {
        echo '<p>' . esc_html__('You do not have permission to approve applications.', 'vms') . '</p>';
        return;
    }

    if ($status === 'pending') {
        $approve_url = wp_nonce_url(
            admin_url('admin-post.php?action=vms_vendor_app_approve&app_id=' . (int)$post->ID),
            'vms_vendor_app_approve_' . (int)$post->ID
        );
        $reject_url = wp_nonce_url(
            admin_url('admin-post.php?action=vms_vendor_app_reject&app_id=' . (int)$post->ID),
            'vms_vendor_app_reject_' . (int)$post->ID
        );

        echo '<p><a class="button button-primary" href="' . esc_url($approve_url) . '">' . esc_html__('Approve', 'vms') . '</a></p>';
        echo '<p><a class="button" style="border-color:#b91c1c;color:#b91c1c;" href="' . esc_url($reject_url) . '">' . esc_html__('Reject', 'vms') . '</a></p>';
    }
}

/**
 * Admin-post handlers: approve / reject
 */
add_action('admin_post_vms_vendor_app_approve', 'vms_vendor_applications_handle_approve');
function vms_vendor_applications_handle_approve(): void
{
    if (!current_user_can('edit_posts')) wp_die('Forbidden');

    $app_id = isset($_GET['app_id']) ? (int) $_GET['app_id'] : 0;
    if ($app_id <= 0) wp_die('Missing app_id');

    check_admin_referer('vms_vendor_app_approve_' . $app_id);

    $app = get_post($app_id);
    if (!$app || $app->post_type !== VMS_VENDOR_APP_CPT) wp_die('Invalid application');

    // If already linked, just mark approved.
    $existing_vendor_id = (int) get_post_meta($app_id, '_vms_vendor_id', true);

    if ($existing_vendor_id <= 0) {
        // Create vendor post
        $vendor_id = wp_insert_post(array(
            'post_type'   => VMS_VENDOR_CPT,
            'post_title'  => $app->post_title,
            'post_status' => 'publish',
        ), true);

        if (!is_wp_error($vendor_id) && $vendor_id > 0) {
            update_post_meta($app_id, '_vms_vendor_id', (int)$vendor_id);
            update_post_meta((int)$vendor_id, '_vms_application_id', (int)$app_id);

            // Copy some useful meta over (safe, non-destructive)
            $copy_keys = array(
                '_vms_app_email'    => '_vms_vendor_email',
                '_vms_app_location' => '_vms_vendor_location',
                '_vms_app_epk'      => '_vms_vendor_epk',
                '_vms_app_social'   => '_vms_vendor_social',
            );
            foreach ($copy_keys as $from => $to) {
                $val = get_post_meta($app_id, $from, true);
                if ($val !== '' && $val !== null) {
                    update_post_meta((int)$vendor_id, $to, $val);
                }
            }
        }
    }

    vms_vendor_app_set_status($app_id, 'approved');

    wp_safe_redirect(admin_url('edit.php?post_type=' . VMS_VENDOR_APP_CPT));
    exit;
}

add_action('admin_post_vms_vendor_app_reject', 'vms_vendor_applications_handle_reject');
function vms_vendor_applications_handle_reject(): void
{
    if (!current_user_can('edit_posts')) wp_die('Forbidden');

    $app_id = isset($_GET['app_id']) ? (int) $_GET['app_id'] : 0;
    if ($app_id <= 0) wp_die('Missing app_id');

    check_admin_referer('vms_vendor_app_reject_' . $app_id);

    $app = get_post($app_id);
    if (!$app || $app->post_type !== VMS_VENDOR_APP_CPT) wp_die('Invalid application');

    vms_vendor_app_set_status($app_id, 'rejected');

    wp_safe_redirect(admin_url('edit.php?post_type=' . VMS_VENDOR_APP_CPT));
    exit;
}

add_action('admin_post_vms_vendor_app_repair_vendor', 'vms_vendor_applications_handle_repair_vendor');
function vms_vendor_applications_handle_repair_vendor(): void
{
    if (!current_user_can('edit_posts')) wp_die('Forbidden');

    $app_id = isset($_GET['app_id']) ? (int) $_GET['app_id'] : 0;
    if ($app_id <= 0) wp_die('Missing app_id');

    check_admin_referer('vms_vendor_app_repair_vendor_' . $app_id);

    $app = get_post($app_id);
    if (!$app || $app->post_type !== VMS_VENDOR_APP_CPT) wp_die('Invalid application');

    // Only makes sense for approved apps
    $status = vms_vendor_app_get_status($app_id);
    if ($status !== 'approved') {
        wp_safe_redirect(admin_url('post.php?post=' . $app_id . '&action=edit'));
        exit;
    }

    $vendor_id = (int) get_post_meta($app_id, '_vms_vendor_id', true);
    $vendor_ok = ($vendor_id > 0 && get_post_type($vendor_id) === VMS_VENDOR_CPT);

    if (!$vendor_ok) {
        $vendor_id = wp_insert_post(array(
            'post_type'   => VMS_VENDOR_CPT,
            'post_title'  => $app->post_title,
            'post_status' => 'publish',
        ), true);

        if (!is_wp_error($vendor_id) && $vendor_id > 0) {
            update_post_meta($app_id, '_vms_vendor_id', (int)$vendor_id);
            update_post_meta((int)$vendor_id, '_vms_application_id', (int)$app_id);

            // Copy useful meta again (safe)
            $copy_keys = array(
                '_vms_app_email'    => '_vms_vendor_email',
                '_vms_app_location' => '_vms_vendor_location',
                '_vms_app_epk'      => '_vms_vendor_epk',
                '_vms_app_social'   => '_vms_vendor_social',
            );
            foreach ($copy_keys as $from => $to) {
                $val = get_post_meta($app_id, $from, true);
                if ($val !== '' && $val !== null) {
                    update_post_meta((int)$vendor_id, $to, $val);
                }
            }
        }
    }

    // Back to the applications list
    wp_safe_redirect(admin_url('edit.php?post_type=' . VMS_VENDOR_APP_CPT));
    exit;
}

add_action('admin_post_vms_vendor_app_resync_vendor', 'vms_vendor_applications_handle_resync_vendor');
function vms_vendor_applications_handle_resync_vendor(): void
{
    if (!current_user_can('edit_posts')) wp_die('Forbidden');

    $app_id = isset($_GET['app_id']) ? (int) $_GET['app_id'] : 0;
    if ($app_id <= 0) wp_die('Missing app_id');

    check_admin_referer('vms_vendor_app_resync_vendor_' . $app_id);

    $app = get_post($app_id);
    if (!$app || $app->post_type !== VMS_VENDOR_APP_CPT) wp_die('Invalid application');

    $status = vms_vendor_app_get_status($app_id);
    if ($status !== 'approved') {
        // Only resync approved applications (keeps workflow clean)
        wp_safe_redirect(admin_url('post.php?post=' . $app_id . '&action=edit'));
        exit;
    }

    // Ensure vendor exists (create if missing)
    $vendor_id = (int) get_post_meta($app_id, '_vms_vendor_id', true);
    $vendor_ok = ($vendor_id > 0 && get_post_type($vendor_id) === VMS_VENDOR_CPT);

    if (!$vendor_ok) {
        $vendor_id = wp_insert_post(array(
            'post_type'   => VMS_VENDOR_CPT,
            'post_title'  => $app->post_title,
            'post_status' => 'publish',
        ), true);

        if (is_wp_error($vendor_id) || $vendor_id <= 0) {
            // If you have a notice helper, use it; otherwise fail quietly back to list
            if (function_exists('vms_add_admin_notice')) {
                vms_add_admin_notice('Failed to create vendor during sync.', 'error');
            }
            wp_safe_redirect(admin_url('edit.php?post_type=' . VMS_VENDOR_APP_CPT));
            exit;
        }

        update_post_meta($app_id, '_vms_vendor_id', (int)$vendor_id);
        update_post_meta((int)$vendor_id, '_vms_application_id', (int)$app_id);
    }

    // Copy app meta -> vendor meta (idempotent)
    $copied = vms_vendor_app_sync_vendor_from_application($app_id, (int)$vendor_id);

    // Optional admin notice
    if (function_exists('vms_add_admin_notice')) {
        vms_add_admin_notice(
            sprintf('Vendor data synced. %d fields updated.', (int)$copied),
            'success'
        );
    }

    wp_safe_redirect(admin_url('edit.php?post_type=' . VMS_VENDOR_APP_CPT));
    exit;
}

/**
 * Shortcode: [vms_vendor_apply]
 */
add_shortcode('vms_vendor_apply', 'vms_vendor_apply_shortcode');
function vms_vendor_apply_shortcode($atts = array(), $content = ''): string
{
    // Handle submission first
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vms_vendor_apply_submit'])) {
        return vms_vendor_apply_handle_frontend_post();
    }

    // Show confirmation banner if redirected back
    $msg = '';
    if (isset($_GET['vms_app']) && $_GET['vms_app'] === 'success') {
        $msg = '<div class="vms-notice vms-notice-success" style="padding:12px 14px;border:1px solid #bbf7d0;background:#ecfdf5;color:#065f46;border-radius:10px;margin:14px 0;">
            <strong>Application received!</strong> Thanks — we’ll be in touch soon.
        </div>';
    } elseif (isset($_GET['vms_app']) && $_GET['vms_app'] === 'error') {
        $msg = '<div class="vms-notice vms-notice-error" style="padding:12px 14px;border:1px solid #fecaca;background:#fef2f2;color:#991b1b;border-radius:10px;margin:14px 0;">
            <strong>Something went wrong.</strong> Please try again or email us.
        </div>';
    } elseif (isset($_GET['vms_app']) && $_GET['vms_app'] === 'nonce') {
        $msg = '<div class="vms-notice vms-notice-error" style="padding:12px 14px;border:1px solid #fecaca;background:#fef2f2;color:#991b1b;border-radius:10px;margin:14px 0;">
            <strong>Security check failed.</strong> Please refresh and try again.
        </div>';
    }

    ob_start();
    echo $msg;
?>
    <form method="post">
        <?php wp_nonce_field('vms_vendor_apply', 'vms_vendor_apply_nonce'); ?>

        <p>
            <label><strong><?php echo esc_html__('Vendor Type', 'vms'); ?></strong></label><br>
            <select name="vms_app_vendor_type" id="vms-app-vendor-type" required>
                <option value=""><?php echo esc_html__('Select…', 'vms'); ?></option>
                <option value="band"><?php echo esc_html__('Band / Artist', 'vms'); ?></option>
                <option value="food_truck"><?php echo esc_html__('Food Truck', 'vms'); ?></option>
            </select>
        </p>

        <p>
            <label><strong>Band / Vendor Name</strong></label><br>
            <input type="text" name="vms_app_name" required style="width:100%;max-width:520px;">
        </p>

        <p>
            <label><strong>Email</strong></label><br>
            <input type="email" name="vms_app_email" required style="width:100%;max-width:520px;">
        </p>

        <p>
            <label><strong>Home Base (City/State)</strong></label><br>
            <input type="text" name="vms_app_location" style="width:100%;max-width:520px;">
        </p>

        <div class="vms-app-fields vms-app-band" style="display:none;">
            <p>
                <label><strong>Typical Rate (optional)</strong></label><br>
                <input type="text" name="vms_app_rate" placeholder="e.g. $800 flat, or door split" style="width:100%;max-width:520px;">
            </p>

            <p>
                <label><strong>EPK / Website Link</strong></label><br>
                <input type="url" name="vms_app_epk" style="width:100%;max-width:520px;">
            </p>
        </div>

        <div class="vms-app-fields vms-app-food" style="display:none;">
            <p>
                <label><strong><?php echo esc_html__('Cuisine / Food Type', 'vms'); ?></strong></label><br>
                <input type="text" name="vms_app_cuisine" style="width:100%;max-width:520px;" placeholder="<?php echo esc_attr__('Tacos, BBQ, Burgers, Coffee, etc.', 'vms'); ?>">
            </p>

            <p>
                <label><strong><?php echo esc_html__('Menu Link (optional)', 'vms'); ?></strong></label><br>
                <input type="url" name="vms_app_menu" style="width:100%;max-width:520px;" placeholder="<?php echo esc_attr__('https://…', 'vms'); ?>">
            </p>
        </div>

        <p>
            <label><strong><?php echo esc_html__('Social Links (optional)', 'vms'); ?></strong></label><br>
            <textarea name="vms_app_social" rows="3" style="width:100%;max-width:520px;"
                placeholder="<?php echo esc_attr__('Instagram, Facebook, Spotify, YouTube, etc.', 'vms'); ?>"></textarea>
        </p>

        <p>
            <label><strong>Anything else we should know?</strong></label><br>
            <textarea name="vms_app_notes" rows="4" style="width:100%;max-width:520px;"></textarea>
        </p>

        <p>
            <button class="button button-primary" type="submit" name="vms_vendor_apply_submit" value="1">
                Submit Application
            </button>
        </p>

        <script>
            (function() {
                var sel = document.getElementById('vms-app-vendor-type');
                if (!sel) return;

                function toggle() {
                    var v = sel.value;

                    document.querySelectorAll('.vms-app-fields').forEach(function(el) {
                        el.style.display = 'none';
                    });

                    if (v === 'band') {
                        document.querySelectorAll('.vms-app-band').forEach(function(el) {
                            el.style.display = 'block';
                        });
                    } else if (v === 'food_truck') {
                        document.querySelectorAll('.vms-app-food').forEach(function(el) {
                            el.style.display = 'block';
                        });
                    }
                }

                sel.addEventListener('change', toggle);
                toggle();
            })();
        </script>
    </form>
<?php
    return (string) ob_get_clean();
}

/**
 * Handle POST from frontend shortcode and redirect back with success/error flags.
 * IMPORTANT: do NOT echo here—shortcode context needs redirect to avoid resubmit.
 */
function vms_vendor_apply_handle_frontend_post(): string
{
    $nonce = isset($_POST['vms_vendor_apply_nonce']) ? (string) $_POST['vms_vendor_apply_nonce'] : '';
    if (!$nonce || !wp_verify_nonce($nonce, 'vms_vendor_apply')) {
        vms_vendor_apply_frontend_redirect('nonce');
        return '';
    }

    $vendor_type = isset($_POST['vms_app_vendor_type']) ? sanitize_text_field((string)$_POST['vms_app_vendor_type']) : '';
    $name        = isset($_POST['vms_app_name']) ? sanitize_text_field((string)$_POST['vms_app_name']) : '';
    $email       = isset($_POST['vms_app_email']) ? sanitize_email((string)$_POST['vms_app_email']) : '';

    if (!$vendor_type || !$name || !$email) {
        vms_vendor_apply_frontend_redirect('error');
        return '';
    }

    $location = isset($_POST['vms_app_location']) ? sanitize_text_field((string)$_POST['vms_app_location']) : '';
    $rate     = isset($_POST['vms_app_rate']) ? sanitize_text_field((string)$_POST['vms_app_rate']) : '';
    $epk      = isset($_POST['vms_app_epk']) ? esc_url_raw((string)$_POST['vms_app_epk']) : '';
    $cuisine  = isset($_POST['vms_app_cuisine']) ? sanitize_text_field((string)$_POST['vms_app_cuisine']) : '';
    $menu     = isset($_POST['vms_app_menu']) ? esc_url_raw((string)$_POST['vms_app_menu']) : '';
    $social   = isset($_POST['vms_app_social']) ? sanitize_textarea_field((string)$_POST['vms_app_social']) : '';
    $notes    = isset($_POST['vms_app_notes']) ? sanitize_textarea_field((string)$_POST['vms_app_notes']) : '';

    $app_id = wp_insert_post(array(
        'post_type'   => VMS_VENDOR_APP_CPT,
        'post_title'  => $name,
        'post_status' => 'publish',
    ), true);

    if (is_wp_error($app_id) || !$app_id) {
        vms_vendor_apply_frontend_redirect('error');
        return '';
    }

    update_post_meta($app_id, '_vms_app_vendor_type', $vendor_type);
    update_post_meta($app_id, '_vms_app_email', $email);
    update_post_meta($app_id, '_vms_app_location', $location);
    update_post_meta($app_id, '_vms_app_rate', $rate);
    update_post_meta($app_id, '_vms_app_epk', $epk);
    update_post_meta($app_id, '_vms_app_cuisine', $cuisine);
    update_post_meta($app_id, '_vms_app_menu', $menu);
    update_post_meta($app_id, '_vms_app_social', $social);
    update_post_meta($app_id, '_vms_app_notes', $notes);

    vms_vendor_app_set_status((int)$app_id, 'pending');
    update_post_meta($app_id, '_vms_app_submitted_at', current_time('mysql'));

    // Email notify
    $to = apply_filters('vms_vendor_app_notify_email', get_option('admin_email'));
    $subject = 'New Vendor Application: ' . $name;
    $body = "A new vendor application was submitted.\n\n"
        . "Name: {$name}\n"
        . "Type: {$vendor_type}\n"
        . "Email: {$email}\n"
        . "Location: {$location}\n"
        . "Rate: {$rate}\n"
        . "EPK: {$epk}\n"
        . "Cuisine: {$cuisine}\n"
        . "Menu: {$menu}\n"
        . "Social:\n{$social}\n\n"
        . "Notes:\n{$notes}\n\n"
        . "Admin link: " . admin_url('post.php?post=' . (int)$app_id . '&action=edit');

    @wp_mail($to, $subject, $body);

    vms_vendor_apply_frontend_redirect('success');
    return '';
}

/**
 * Redirect back to the application page with a status flag.
 */
function vms_vendor_apply_frontend_redirect(string $flag): void
{
    $ref = wp_get_referer();

    if (!$ref) {
        $qid = function_exists('get_queried_object_id') ? (int) get_queried_object_id() : 0;
        $ref = $qid ? get_permalink($qid) : home_url('/');
    }

    $url = add_query_arg('vms_app', $flag, $ref);
    wp_safe_redirect($url);
    exit;
}

/**
 * Sync vendor fields from an approved application into the vendor post.
 * Returns number of fields updated.
 */
function vms_vendor_app_sync_vendor_from_application(int $app_id, int $vendor_id): int
{
    if ($app_id <= 0 || $vendor_id <= 0) return 0;

    $app = get_post($app_id);
    $vendor = get_post($vendor_id);
    if (!$app || !$vendor) return 0;
    if ($app->post_type !== VMS_VENDOR_APP_CPT) return 0;
    if ($vendor->post_type !== VMS_VENDOR_CPT) return 0;

    $updated = 0;

    // 1) Keep vendor title aligned (optional, but usually desired)
    $app_title = trim((string) $app->post_title);
    if ($app_title !== '' && $vendor->post_title !== $app_title) {
        wp_update_post(array(
            'ID'         => $vendor_id,
            'post_title' => $app_title,
            'post_name'  => sanitize_title($app_title),
        ));
    }

    // 2) Map application meta -> vendor meta
    // Adjust these keys to match your vendors.php expectations
    $map = array(
        '_vms_app_vendor_type' => '_vms_vendor_type',
        '_vms_app_email'       => '_vms_vendor_email',
        '_vms_app_location'    => '_vms_vendor_location',
        '_vms_app_rate'        => '_vms_vendor_rate',
        '_vms_app_epk'         => '_vms_vendor_epk',
        '_vms_app_cuisine'     => '_vms_vendor_cuisine',
        '_vms_app_menu'        => '_vms_vendor_menu',
        '_vms_app_social'      => '_vms_vendor_social',
        '_vms_app_notes'       => '_vms_vendor_notes',
    );

    foreach ($map as $from => $to) {
        $val = get_post_meta($app_id, $from, true);

        // Normalize strings
        if (is_string($val)) $val = trim($val);

        // If app field is blank, don't overwrite vendor (prevents accidental wiping)
        if ($val === '' || $val === null) {
            continue;
        }

        $existing = get_post_meta($vendor_id, $to, true);

        // Only update if different
        if ((string) $existing !== (string) $val) {
            update_post_meta($vendor_id, $to, $val);
            $updated++;
        }
    }

    // 3) Always ensure cross-link meta exists
    $existing_app_link = (int) get_post_meta($vendor_id, '_vms_application_id', true);
    if ($existing_app_link !== $app_id) {
        update_post_meta($vendor_id, '_vms_application_id', $app_id);
        $updated++;
    }

    return $updated;
}
