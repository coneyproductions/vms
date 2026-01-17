<?php
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

add_action('init', 'vms_register_venue_cpt');

function vms_register_venue_cpt()
{

    $labels = array(
        'name'               => __('Venues', 'vms'),
        'singular_name'      => __('Venue', 'vms'),
        'menu_name'          => __('Venues', 'vms'),
        'add_new'            => __('Add New', 'vms'),
        'add_new_item'       => __('Add New Venue', 'vms'),
        'edit_item'          => __('Edit Venue', 'vms'),
        'new_item'           => __('New Venue', 'vms'),
        'view_item'          => __('View Venue', 'vms'),
        'search_items'       => __('Search Venues', 'vms'),
        'not_found'          => __('No venues found', 'vms'),
        'not_found_in_trash' => __('No venues found in Trash', 'vms'),
    );

    $args = array(
        'labels'             => $labels,
        'public'             => false,
        'show_ui'            => true,
        'show_in_menu'       => 'vms',
        'menu_position'      => 26,
        'menu_icon'          => 'dashicons-location-alt',
        'supports'           => array('title', 'editor', 'thumbnail'),
        'capability_type'    => 'post',
        'has_archive'        => false,
        'rewrite'            => false,
    );

    register_post_type('vms_venue', $args);
}

/**
 * Venue Default Event Times
 * - Default start time
 * - Default duration (minutes)
 * - Optional default end time
 */

add_action('add_meta_boxes', function () {
    add_meta_box(
        'vms_venue_default_times',
        __('Default Event Times', 'vms'),
        'vms_render_venue_default_times_box',
        'vms_venue',
        'side',
        'default'
    );
});

function vms_render_venue_default_times_box($post) {
    wp_nonce_field('vms_save_venue_default_times', 'vms_venue_default_times_nonce');

    $start = (string) get_post_meta($post->ID, '_vms_default_start_time', true);
    $dur   = (string) get_post_meta($post->ID, '_vms_default_duration_min', true);
    $end   = (string) get_post_meta($post->ID, '_vms_default_end_time', true);

    echo '<p class="description" style="margin-top:0;">' .
        esc_html__('Used to pre-fill Start/End time when creating a new Event Plan for this venue.', 'vms') .
    '</p>';

    echo '<p style="margin:0 0 10px;">';
    echo '<label for="vms_default_start_time"><strong>' . esc_html__('Default Start', 'vms') . '</strong></label><br>';
    echo '<input type="time" id="vms_default_start_time" name="vms_default_start_time" value="' . esc_attr($start) . '" style="width:100%;">';
    echo '</p>';

    echo '<p style="margin:0 0 10px;">';
    echo '<label for="vms_default_duration_min"><strong>' . esc_html__('Default Duration (minutes)', 'vms') . '</strong></label><br>';
    echo '<input type="number" min="0" step="1" id="vms_default_duration_min" name="vms_default_duration_min" value="' . esc_attr($dur) . '" style="width:100%;" placeholder="120">';
    echo '</p>';

    echo '<p style="margin:0 0 10px;">';
    echo '<label for="vms_default_end_time"><strong>' . esc_html__('Default End (optional)', 'vms') . '</strong></label><br>';
    echo '<input type="time" id="vms_default_end_time" name="vms_default_end_time" value="' . esc_attr($end) . '" style="width:100%;">';
    echo '<span class="description">' . esc_html__('If set, this overrides duration-based end time defaults.', 'vms') . '</span>';
    echo '</p>';
}

add_action('save_post_vms_venue', function ($post_id, $post) {
    if ($post->post_type !== 'vms_venue') return;

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;
    if (!current_user_can('edit_post', $post_id)) return;

    if (
        empty($_POST['vms_venue_default_times_nonce']) ||
        !wp_verify_nonce($_POST['vms_venue_default_times_nonce'], 'vms_save_venue_default_times')
    ) {
        return;
    }

    $start = isset($_POST['vms_default_start_time']) ? sanitize_text_field(wp_unslash($_POST['vms_default_start_time'])) : '';
    $end   = isset($_POST['vms_default_end_time']) ? sanitize_text_field(wp_unslash($_POST['vms_default_end_time'])) : '';
    $dur   = isset($_POST['vms_default_duration_min']) ? absint($_POST['vms_default_duration_min']) : 0;

    // Basic time format guard: allow '' or HH:MM
    if ($start !== '' && !preg_match('/^\d{2}:\d{2}$/', $start)) $start = '';
    if ($end !== '' && !preg_match('/^\d{2}:\d{2}$/', $end)) $end = '';

    update_post_meta($post_id, '_vms_default_start_time', $start);
    update_post_meta($post_id, '_vms_default_end_time', $end);
    update_post_meta($post_id, '_vms_default_duration_min', $dur);
}, 20, 2);

/**
 * ==========================================================
 * Venue Schedule (Open Days + Optional Seasons)
 * ==========================================================
 *
 * Rules:
 * - CLOSED 24/7 by default until at least one Open Day is selected.
 * - If Open Year-Round is enabled, seasons are ignored.
 * - If Open Year-Round is disabled and at least one season is configured,
 *   the venue is open only within those ranges (and only on Open Days).
 * - Date overrides (open/closed) are stored separately and applied first.
 */

add_action('add_meta_boxes', function () {
    add_meta_box(
        'vms_venue_schedule',
        __('Venue Schedule', 'vms'),
        'vms_render_venue_schedule_box',
        'vms_venue',
        'normal',
        'default'
    );
});

function vms_render_venue_schedule_box($post)
{
    wp_nonce_field('vms_save_venue_schedule', 'vms_venue_schedule_nonce');

    $open_days = get_post_meta($post->ID, '_vms_venue_open_days', true);
    if (!is_array($open_days)) $open_days = array();
    $open_days = array_values(array_unique(array_map('intval', $open_days)));

    $year_round = (int) get_post_meta($post->ID, '_vms_venue_open_year_round', true);

    $seasons = get_post_meta($post->ID, '_vms_venue_seasons', true);
    if (!is_array($seasons)) $seasons = array();

    // Normalize to exactly 2 season slots
    $slots = array(
        array('start' => '', 'end' => ''),
        array('start' => '', 'end' => ''),
    );
    for ($i = 0; $i < 2; $i++) {
        if (isset($seasons[$i]) && is_array($seasons[$i])) {
            $slots[$i]['start'] = isset($seasons[$i]['start']) ? (string) $seasons[$i]['start'] : '';
            $slots[$i]['end']   = isset($seasons[$i]['end'])   ? (string) $seasons[$i]['end']   : '';
        }
    }

    $days = array(
        0 => __('Sunday', 'vms'),
        1 => __('Monday', 'vms'),
        2 => __('Tuesday', 'vms'),
        3 => __('Wednesday', 'vms'),
        4 => __('Thursday', 'vms'),
        5 => __('Friday', 'vms'),
        6 => __('Saturday', 'vms'),
    );

    echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;align-items:start;max-width:900px;">';

    // Open Days
    echo '<div>';
    echo '<h3 style="margin:0 0 8px;">' . esc_html__('Weekly Open Days', 'vms') . '</h3>';
    echo '<p class="description" style="margin:0 0 10px;">' .
        esc_html__('Select the days this venue is normally open. If no days are selected, the venue is treated as closed until configured.', 'vms') .
    '</p>';

    echo '<div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:6px;max-width:360px;">';
    foreach ($days as $num => $label) {
        $checked = in_array((int) $num, $open_days, true) ? 'checked' : '';
        echo '<label style="display:flex;gap:8px;align-items:center;">';
        echo '<input type="checkbox" name="vms_venue_open_days[]" value="' . esc_attr((int) $num) . '" ' . $checked . '>';
        echo '<span>' . esc_html($label) . '</span>';
        echo '</label>';
    }
    echo '</div>';

    echo '<div style="margin-top:12px;">';
    echo '<label style="display:flex;gap:10px;align-items:flex-start;">';
    echo '<input type="checkbox" name="vms_venue_open_year_round" value="1" ' . checked(1, $year_round, false) . '>';
    echo '<span><strong>' . esc_html__('Open year-round', 'vms') . '</strong><br>';
    echo '<span class="description">' . esc_html__('If enabled, seasons are ignored.', 'vms') . '</span></span>';
    echo '</label>';
    echo '</div>';

    echo '</div>';

    // Seasons
    echo '<div>';
    echo '<h3 style="margin:0 0 8px;">' . esc_html__('Optional Seasons', 'vms') . '</h3>';
    echo '<p class="description" style="margin:0 0 10px;">' .
        esc_html__('If Open year-round is off, you can optionally limit booking to one or two date ranges. Leave blank for year-round scheduling (based on Open Days).', 'vms') .
    '</p>';

    for ($i = 0; $i < 2; $i++) {
        $idx = $i + 1;
        $start = $slots[$i]['start'];
        $end   = $slots[$i]['end'];

        echo '<div style="border:1px solid #e5e5e5;border-radius:12px;padding:12px;margin:0 0 10px;background:#fff;">';
        echo '<div style="font-weight:800;margin:0 0 8px;">' . esc_html(sprintf(__('Season %d', 'vms'), $idx)) . '</div>';
        echo '<div style="display:flex;gap:10px;flex-wrap:wrap;">';

        echo '<div style="min-width:220px;flex:1;">';
        echo '<label><strong>' . esc_html__('Start', 'vms') . '</strong></label><br>';
        echo '<input type="date" name="vms_venue_season_start[' . esc_attr($i) . ']" value="' . esc_attr($start) . '" style="width:100%;max-width:260px;">';
        echo '</div>';

        echo '<div style="min-width:220px;flex:1;">';
        echo '<label><strong>' . esc_html__('End', 'vms') . '</strong></label><br>';
        echo '<input type="date" name="vms_venue_season_end[' . esc_attr($i) . ']" value="' . esc_attr($end) . '" style="width:100%;max-width:260px;">';
        echo '</div>';

        echo '</div>';
        echo '</div>';
    }

    echo '<p class="description" style="margin:0;">' .
        esc_html__('Tip: Use the Schedule page to mark individual exceptions (open/closed) once we add overrides.', 'vms') .
    '</p>';

    echo '</div>';

    echo '</div>';
}

add_action('save_post_vms_venue', function ($post_id, $post) {
    if ($post->post_type !== 'vms_venue') return;

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;
    if (!current_user_can('edit_post', $post_id)) return;

    if (
        empty($_POST['vms_venue_schedule_nonce']) ||
        !wp_verify_nonce($_POST['vms_venue_schedule_nonce'], 'vms_save_venue_schedule')
    ) {
        return;
    }

    $open_days = isset($_POST['vms_venue_open_days']) ? (array) $_POST['vms_venue_open_days'] : array();
    $open_days = array_values(array_unique(array_map('intval', $open_days)));
    $open_days = array_values(array_filter($open_days, fn($d) => $d >= 0 && $d <= 6));

    $year_round = !empty($_POST['vms_venue_open_year_round']) ? 1 : 0;

    $starts = isset($_POST['vms_venue_season_start']) ? (array) $_POST['vms_venue_season_start'] : array();
    $ends   = isset($_POST['vms_venue_season_end'])   ? (array) $_POST['vms_venue_season_end']   : array();

    $seasons = array();
    for ($i = 0; $i < 2; $i++) {
        $start = isset($starts[$i]) ? sanitize_text_field(wp_unslash($starts[$i])) : '';
        $end   = isset($ends[$i])   ? sanitize_text_field(wp_unslash($ends[$i]))   : '';

        // Allow blanks; if one is set, both must be valid YYYY-MM-DD
        if ($start === '' && $end === '') continue;

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) $start = '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end))   $end = '';

        if ($start !== '' && $end !== '') {
            if ($end < $start) {
                $tmp = $start;
                $start = $end;
                $end = $tmp;
            }
            $seasons[] = array('start' => $start, 'end' => $end);
        }
    }

    update_post_meta($post_id, '_vms_venue_open_days', $open_days);
    update_post_meta($post_id, '_vms_venue_open_year_round', $year_round);
    update_post_meta($post_id, '_vms_venue_seasons', $seasons);

}, 21, 2);

/**
 * Enforce CLOSED-by-default: prevent publishing until at least one Open Day is selected.
 */
add_filter('wp_insert_post_data', function ($data, $postarr) {
    if (empty($data['post_type']) || $data['post_type'] !== 'vms_venue') return $data;

    // Only block publish.
    if (empty($data['post_status']) || $data['post_status'] !== 'publish') return $data;

    $post_id = isset($postarr['ID']) ? (int) $postarr['ID'] : 0;
    if ($post_id <= 0) return $data;

    $open_days = get_post_meta($post_id, '_vms_venue_open_days', true);
    if (!is_array($open_days)) $open_days = array();
    $open_days = array_values(array_filter(array_map('intval', $open_days)));

    if (empty($open_days)) {
        $data['post_status'] = 'draft';

        // Store a short-lived notice keyed to this post.
        set_transient('vms_venue_schedule_notice_' . $post_id, 1, 60);
    }

    return $data;
}, 10, 2);

add_action('admin_notices', function () {
    if (!is_admin()) return;

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->base !== 'post' || $screen->post_type !== 'vms_venue') return;

    $post_id = isset($_GET['post']) ? (int) $_GET['post'] : 0;
    if ($post_id <= 0) return;

    $key = 'vms_venue_schedule_notice_' . $post_id;
    if (!get_transient($key)) return;

    delete_transient($key);

    echo '<div class="notice notice-warning is-dismissible">';
    echo '<p><strong>' . esc_html__('Venue not published.', 'vms') . '</strong> ' . esc_html__('Select at least one Weekly Open Day in Venue Schedule, then publish again.', 'vms') . '</p>';
    echo '</div>';
});
