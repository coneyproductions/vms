<?php
if (!defined('ABSPATH')) exit;

/**
 * Get the current venue ID for the current admin user.
 *
 * NOTE:
 * - This returns a numeric venue ID (int-like).
 * - "All venues" is a separate concept controlled by page-level scope (e.g., Schedule uses ?scope=all).
 *
 * Fallback order:
 *  1) user_meta _vms_current_venue_id
 *  2) Settings: vms_settings[default_venue_id]
 *  3) first available venue by title
 */
function vms_get_current_venue_id()
{
    $user_id = (int) get_current_user_id();

    // Prefer canonical constant if present.
    $meta_key = defined('VMS_SCH_CURRENT_VENUE_META_KEY')
        ? (string) VMS_SCH_CURRENT_VENUE_META_KEY
        : '_vms_current_venue_id';

    $raw = get_user_meta($user_id, $meta_key, true);
    $venue_id = absint($raw);
    if ($venue_id > 0 && get_post_type($venue_id) === 'vms_venue') {
        return (int) $venue_id;
    }

    // Fallback to Default Venue.
    if (function_exists('vms_get_default_venue_id')) {
        $default_id = (int) vms_get_default_venue_id();
        if ($default_id > 0 && get_post_type($default_id) === 'vms_venue') {
            return (int) $default_id;
        }
    }

    // Deterministic fallback: first available venue by title.
    $venues = get_posts(array(
        'post_type'      => 'vms_venue',
        'post_status'    => array('publish', 'private', 'draft'),
        'fields'         => 'ids',
        'posts_per_page' => 1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'no_found_rows'  => true,
    ));

    if (!empty($venues) && isset($venues[0])) {
        return (int) $venues[0];
    }

    return 0;
}



function vms_set_current_venue_id(int $venue_id): void
{
    $user_id = (int) get_current_user_id();

    // Prefer canonical constant if present.
    $meta_key = defined('VMS_SCH_CURRENT_VENUE_META_KEY')
        ? (string) VMS_SCH_CURRENT_VENUE_META_KEY
        : '_vms_current_venue_id';

    if ($venue_id > 0 && get_post_type($venue_id) === 'vms_venue') {
        update_user_meta($user_id, $meta_key, (string) $venue_id);
    }
}

/**
 * Render a reusable venue selector form (admin-only).
 * Include this at the top of VMS admin pages.
 */
function vms_render_current_venue_selector(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $user_id = (int) get_current_user_id();

    // Prefer canonical constant if present.
    $meta_key = defined('VMS_SCH_CURRENT_VENUE_META_KEY')
        ? (string) VMS_SCH_CURRENT_VENUE_META_KEY
        : '_vms_current_venue_id';

    // Prefer explicit venue_id in URL, else user meta.
    $current = isset($_GET['venue_id'])
        ? absint($_GET['venue_id'])
        : absint(get_user_meta($user_id, $meta_key, true));

    // Fallback to Default Venue if none selected yet.
    if ($current <= 0 && function_exists('vms_get_default_venue_id')) {
        $current = (int) vms_get_default_venue_id();
    }

    $venues = get_posts(array(
        'post_type'      => 'vms_venue',
        'post_status'    => array('publish', 'private', 'draft'),
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'no_found_rows'  => true,
    ));

    if (empty($venues)) {
        echo '<div class="notice notice-warning"><p><strong>VMS:</strong> No venues exist yet. Create one under VMS → Venues.</p></div>';
        return;
    }

    // If still no valid selection, pick the first venue deterministically.
    if ($current <= 0 && isset($venues[0]->ID)) {
        $current = (int) $venues[0]->ID;
    }

    //
    // THIS SELECTOR IS FOR SCHEDULE/CALENDAR VIEW
    //
    
    $action = esc_url(admin_url('admin-post.php'));

    echo '<form method="post" action="' . $action . '" class="vms-venue-selector">';
    echo '<input type="hidden" name="action" value="vms_set_current_venue">';
    echo '<input type="hidden" name="vms_context" value="schedule">';

    wp_nonce_field('vms_set_current_venue', 'vms_current_venue_nonce');

    echo '<label for="vms-venue-select" class="vms-venue-selector__label">Current Venue:</label>';

    echo '<select id="vms-venue-select" name="venue_id" class="vms-venue-selector__select" onchange="this.form.submit();">';

    foreach ($venues as $v) {
        $vid = (int) $v->ID;

        $label = (string) $v->post_title;
        $status = (string) get_post_status($vid);

        if ($status !== '' && $status !== 'publish') {
            $label .= ' (' . ucfirst($status) . ')';
        }

        echo '<option value="' . esc_attr((string) $vid) . '"' . selected($current, $vid, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select>';
    echo '<input type="hidden" name="redirect_to" value="' . esc_attr(wp_unslash($_SERVER['REQUEST_URI'])) . '">';
    echo '</form>';
}



/**
 * Handle Schedule "Current Venue" selector POST (schedule-owned).
 * IMPORTANT:
 * - Dashboard must NOT post to this action.
 * - Saves ONLY a numeric venue_id (no 'all').
 */
/**
 * Handle Schedule "Current Venue" selector POST (schedule-owned).
 * Saves ONLY a numeric venue_id (no 'all').
 */
add_action('admin_post_vms_set_current_venue', function () {
    if (!current_user_can('manage_options')) {
        wp_die('Not allowed');
    }

    if (empty($_POST['vms_current_venue_nonce']) || !wp_verify_nonce($_POST['vms_current_venue_nonce'], 'vms_set_current_venue')) {
        wp_die('Bad nonce');
    }

    // Hard scope: only allow from Schedule pages.
    $context = isset($_POST['vms_context']) ? sanitize_text_field((string) $_POST['vms_context']) : '';
    if ($context !== 'schedule') {
        wp_die('Not allowed');
    }

    $user_id = (int) get_current_user_id();

    // Prefer canonical constant if present.
    $meta_key = defined('VMS_SCH_CURRENT_VENUE_META_KEY')
        ? (string) VMS_SCH_CURRENT_VENUE_META_KEY
        : '_vms_current_venue_id';

    // Numeric only.
    $venue_id = isset($_POST['venue_id']) ? absint($_POST['venue_id']) : 0;

    if ($venue_id > 0) {
        update_user_meta($user_id, $meta_key, (string) $venue_id);
    } else {
        delete_user_meta($user_id, $meta_key);
    }

    $redirect = !empty($_POST['redirect_to'])
        ? esc_url_raw((string) $_POST['redirect_to'])
        : (wp_get_referer() ?: admin_url('admin.php?page=vms-schedule'));

    // Ensure redirect reflects the newly selected venue_id.
    // If redirect_to still contains the prior venue_id, schedule.php can re-persist the old value on load.
    if ($venue_id > 0) {
        $redirect = add_query_arg('venue_id', (string) $venue_id, $redirect);
    } else {
        $redirect = remove_query_arg('venue_id', $redirect);
    }
    $redirect = esc_url_raw($redirect);

    wp_safe_redirect($redirect);
    exit;
});

/**
 * Dashboard venue/scope selector (dashboard-owned).
 * Stores:
 *  - _vms_dash_scope = 'venue' | 'all'
 *  - _vms_dash_venue_id = numeric string (only meaningful when scope=venue)
 */
add_action('admin_post_vms_set_dashboard_venue', function () {

    if (!current_user_can('manage_options')) {
        wp_die('Not allowed');
    }

    if (empty($_POST['vms_dash_venue_nonce']) || !wp_verify_nonce($_POST['vms_dash_venue_nonce'], 'vms_set_dashboard_venue')) {
        wp_die('Bad nonce');
    }

    $user_id = (int) get_current_user_id();

    $scope = isset($_POST['dash_scope']) ? sanitize_key((string) $_POST['dash_scope']) : 'venue';
    if ($scope !== 'venue' && $scope !== 'all') {
        $scope = 'venue';
    }

    update_user_meta($user_id, '_vms_dash_scope', $scope);

    $venue_id = isset($_POST['dash_venue_id']) ? absint($_POST['dash_venue_id']) : 0;

    // Enforce: all-venues scope implies venue_id=0.
    if ($scope === 'all') {
        $venue_id = 0;
    }
    if ($venue_id > 0) {
        update_user_meta($user_id, '_vms_dash_venue_id', (string) $venue_id);
    } else {
        delete_user_meta($user_id, '_vms_dash_venue_id');
    }

    $redirect = !empty($_POST['redirect_to'])
        ? esc_url_raw((string) $_POST['redirect_to'])
        : (wp_get_referer() ?: admin_url('admin.php?page=vms-dashboard'));

    wp_safe_redirect($redirect);
    exit;
});


/**
 * Dashboard venue/scope persistence (AJAX, no page reload).
 *
 * Action: vms_set_dashboard_prefs
 * Stores:
 *  - _vms_dash_scope = 'venue' | 'all'
 *  - _vms_dash_venue_id = numeric string (only meaningful when scope=venue)
 *
 * POST params:
 *  - nonce (required) wp_create_nonce('vms_set_dashboard_prefs')
 *  - dash_scope ('venue' | 'all')
 *  - dash_venue_id (numeric string; ignored when scope=all)
 */
add_action('wp_ajax_vms_set_dashboard_prefs', function () {

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Not allowed'), 403);
    }

    $nonce = isset($_POST['nonce']) ? (string) $_POST['nonce'] : '';
    if (!$nonce || !wp_verify_nonce($nonce, 'vms_set_dashboard_prefs')) {
        wp_send_json_error(array('message' => 'Bad nonce'), 403);
    }

    $user_id = (int) get_current_user_id();

    // Optional: persist the global what-if toggle (Draft/Ready).
    if (isset($_POST['include_drafts'])) {
        $include_drafts = (absint($_POST['include_drafts']) === 1);

        if (function_exists('vms_user_pref_set_include_drafts')) {
            vms_user_pref_set_include_drafts((bool) $include_drafts, $user_id);
        } else {
            update_user_meta($user_id, '_vms_include_drafts', $include_drafts ? '1' : '0');
        }
    }

    // Optional: persist dashboard Include canceled toggle.
    if (isset($_POST['include_canceled'])) {
        $include_canceled = (absint($_POST['include_canceled']) === 1);
        update_user_meta($user_id, '_vms_dash_include_canceled', $include_canceled ? '1' : '0');
    }

    // Existing (stored) preferences.
    $scope_existing = (string) get_user_meta($user_id, '_vms_dash_scope', true);
    if ($scope_existing !== 'venue' && $scope_existing !== 'all') {
        $scope_existing = 'venue';
    }

    $venue_existing = absint(get_user_meta($user_id, '_vms_dash_venue_id', true));

    // Optional: update scope.
    $scope = $scope_existing;
    $touch_scope = isset($_POST['dash_scope']);
    if ($touch_scope) {
        $scope = isset($_POST['dash_scope']) ? sanitize_key((string) $_POST['dash_scope']) : 'venue';
        if ($scope !== 'venue' && $scope !== 'all') {
            $scope = 'venue';
        }

        update_user_meta($user_id, '_vms_dash_scope', $scope);
    }

    // Optional: update venue.
    $venue_id = $venue_existing;
    $touch_venue = isset($_POST['dash_venue_id']);

    if ($touch_venue) {
        $venue_id = absint($_POST['dash_venue_id']);
    }

    // Enforce: all-venues scope implies venue_id=0.
    if ($scope === 'all') {
        $venue_id = 0;
        $touch_venue = true; // ensure we clear stored venue_id when scope flips to all
    }

    if ($touch_venue) {
        if ($venue_id > 0 && get_post_type($venue_id) === 'vms_venue') {
            update_user_meta($user_id, '_vms_dash_venue_id', (string) $venue_id);
        } else {
            delete_user_meta($user_id, '_vms_dash_venue_id');
            $venue_id = 0;
        }
    }

    $include_drafts_now = (function_exists('vms_user_pref_get_include_drafts'))
        ? (bool) vms_user_pref_get_include_drafts($user_id)
        : false;
    $include_canceled_now = ((string) get_user_meta($user_id, '_vms_dash_include_canceled', true) === '1');

    wp_send_json_success(array(
        'scope'          => $scope,
        'venue_id'       => (string) $venue_id,
        'include_drafts' => $include_drafts_now ? 1 : 0,
        'include_canceled' => $include_canceled_now ? 1 : 0,
    ));
});
