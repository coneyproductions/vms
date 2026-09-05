<?php
if (!defined('ABSPATH')) exit;

/**
 * Find the Event Plan associated with a TEC event.
 *
 * @param int $tec_event_id tribe_events post ID
 * @return int|null Event Plan ID or null if none
 */

function bvmgr_label(string $key, string $default): string
{
    $opts = (array) get_option('vms_settings', array());
    $val  = isset($opts["label_$key"]) ? trim((string)$opts["label_$key"]) : '';
    return ($val !== '') ? $val : $default;
}

function bvmgr_ui_text(string $key, string $default): string
{
    $opts = (array) get_option('vms_settings', array());
    $val  = isset($opts["ui_$key"]) ? trim((string)$opts["ui_$key"]) : '';
    return ($val !== '') ? $val : $default;
}

if (!function_exists('bvmgr_asset_relative_path')) {
    function bvmgr_asset_relative_path(string $asset_rel): string
    {
        return ltrim($asset_rel, '/');
    }
}

if (!function_exists('bvmgr_plugin_root_path')) {
    function bvmgr_plugin_root_path(): string
    {
        if (defined('BVMGR_PLUGIN_PATH') && is_string(BVMGR_PLUGIN_PATH) && BVMGR_PLUGIN_PATH !== '') {
            return trailingslashit(BVMGR_PLUGIN_PATH);
        }

        return trailingslashit(dirname(__DIR__));
    }
}

if (!function_exists('bvmgr_plugin_main_file')) {
    function bvmgr_plugin_main_file(): string
    {
        if (defined('BVMGR_PLUGIN_FILE') && is_string(BVMGR_PLUGIN_FILE) && BVMGR_PLUGIN_FILE !== '') {
            return BVMGR_PLUGIN_FILE;
        }

        return bvmgr_plugin_root_path() . 'backstage-venue-manager.php';
    }
}

if (!function_exists('bvmgr_asset_path')) {
    function bvmgr_asset_path(string $asset_rel): string
    {
        return bvmgr_plugin_root_path() . bvmgr_asset_relative_path($asset_rel);
    }
}

if (!function_exists('bvmgr_asset_url')) {
    function bvmgr_asset_url(string $asset_rel): string
    {
        $asset_rel = bvmgr_asset_relative_path($asset_rel);

        if (defined('BVMGR_PLUGIN_URL') && is_string(BVMGR_PLUGIN_URL) && BVMGR_PLUGIN_URL !== '') {
            return trailingslashit(BVMGR_PLUGIN_URL) . $asset_rel;
        }

        return plugins_url($asset_rel, bvmgr_plugin_main_file());
    }
}

if (!function_exists('bvmgr_asset_version_for')) {
    function bvmgr_asset_version_for(string $asset_rel): string
    {
        if (function_exists('bvmgr_asset_version')) {
            $version = trim((string) bvmgr_asset_version());
            if ($version !== '') {
                return $version;
            }
        }

        $asset_file = bvmgr_asset_path($asset_rel);
        if (file_exists($asset_file)) {
            return (string) filemtime($asset_file);
        }

        return '';
    }
}

if (!function_exists('bvmgr_enqueue_style_asset')) {
    function bvmgr_enqueue_style_asset(string $handle, string $asset_rel, array $deps = array()): void
    {
        wp_enqueue_style(
            $handle,
            bvmgr_asset_url($asset_rel),
            $deps,
            bvmgr_asset_version_for($asset_rel)
        );
    }
}

if (!function_exists('bvmgr_enqueue_public_style_stack')) {
    function bvmgr_enqueue_public_style_stack(): void
    {
        bvmgr_enqueue_style_asset('bvmgr-shared', 'assets/css/vms-shared.css');
        bvmgr_enqueue_style_asset('bvmgr-ui', 'assets/css/vms-ui.css', array('bvmgr-shared'));
    }
}

if (!function_exists('bvmgr_enqueue_admin_style_stack')) {
    function bvmgr_enqueue_admin_style_stack(): void
    {
        bvmgr_enqueue_public_style_stack();
        bvmgr_enqueue_style_asset('bvmgr-admin', 'assets/css/vms-admin.css', array('bvmgr-ui'));
    }
}

if (!function_exists('bvmgr_enqueue_portal_style_stack')) {
    function bvmgr_enqueue_portal_style_stack(): void
    {
        bvmgr_enqueue_public_style_stack();
        bvmgr_enqueue_style_asset('bvmgr-portal', 'assets/css/vms-portal.css', array('bvmgr-ui'));
    }
}

/**
 * Admin help mode
 *
 * Values:
 * - off
 * - basic
 * - guided
 */
if (!function_exists('bvmgr_get_help_mode')) {
    function bvmgr_get_help_mode(): string
    {
        $opts = (array) get_option('vms_settings', array());
        $mode = isset($opts['help_mode']) ? sanitize_key((string) $opts['help_mode']) : 'basic';
        if (!in_array($mode, array('off', 'basic', 'guided'), true)) {
            $mode = 'basic';
        }
        return $mode;
    }
}

if (!function_exists('bvmgr_help_is_enabled')) {
    function bvmgr_help_is_enabled(): bool
    {
        return bvmgr_get_help_mode() !== 'off';
    }
}

if (!function_exists('bvmgr_help_icon')) {
    function bvmgr_help_icon(string $help_text, string $aria_label = 'Help'): void
    {
        if (!function_exists('esc_attr') || !function_exists('esc_html')) {
            return;
        }
        if (!function_exists('bvmgr_help_is_enabled') || !bvmgr_help_is_enabled()) {
            return;
        }
        $help_text = trim($help_text);
        if ($help_text === '') {
            return;
        }

        // Render as a real button so it works on click/tap (not just hover).
        // The tooltip text is stored in a data attribute and rendered by a small JS helper.
        echo '<button type="button" class="vms-help-icon" aria-label="' . esc_attr($aria_label) . '" aria-expanded="false" data-vms-help="' . esc_attr($help_text) . '">?</button>';
    }
}


if (!function_exists('bvmgr_event_plan_get_rescheduled_child_plan_ids')) {
    function bvmgr_event_plan_get_rescheduled_child_plan_ids(int $plan_id): array
    {
        $plan_id = absint($plan_id);
        if ($plan_id <= 0) {
            return array();
        }

        $meta_key = function_exists('bvmgr_meta_key')
            ? (string) (bvmgr_meta_key('event_plan', 'rescheduled_to_plan_ids') ?: '_vms_rescheduled_to_plan_ids')
            : '_vms_rescheduled_to_plan_ids';

        $value = get_post_meta($plan_id, $meta_key, true);
        if (function_exists('bvmgr_event_plan_normalize_related_plan_ids')) {
            return bvmgr_event_plan_normalize_related_plan_ids($value);
        }

        if (!is_array($value)) {
            $value = array();
        }

        $ids = array_map('absint', $value);
        $ids = array_values(array_unique(array_filter($ids, static function ($id) {
            return $id > 0 && get_post_type($id) === 'vms_event_plan';
        })));

        return $ids;
    }
}

if (!function_exists('bvmgr_event_plan_get_public_event_payload')) {
    function bvmgr_event_plan_get_public_event_payload(int $plan_id): array
    {
        $plan_id = absint($plan_id);
        if ($plan_id <= 0 || get_post_type($plan_id) !== 'vms_event_plan') {
            return array();
        }

        $event_id = 0;
        if (function_exists('bvmgr_ticketing_b_get_linked_tec_event_id')) {
            $event_id = absint(bvmgr_ticketing_b_get_linked_tec_event_id($plan_id));
        }
        if ($event_id <= 0) {
            $event_id = absint(get_post_meta($plan_id, '_vms_tec_event_id', true));
        }

        $url = function_exists('bvmgr_event_plan_resolve_public_calendar_url')
            ? bvmgr_event_plan_resolve_public_calendar_url($plan_id)
            : '';
        if ($url === '') {
            return array();
        }

        $date_raw = trim((string) get_post_meta($plan_id, '_vms_event_date', true));
        $timestamp = $date_raw !== '' ? strtotime($date_raw . ' 12:00:00') : false;
        $date_label = ($timestamp !== false)
            ? wp_date(get_option('date_format') ?: 'F j, Y', $timestamp, wp_timezone())
            : $date_raw;

        $title = '';
        if ($event_id > 0) {
            $title = trim((string) get_the_title($event_id));
        }
        if ($title === '') {
            $title = trim((string) get_the_title($plan_id));
        }

        return array(
            'plan_id' => $plan_id,
            'event_id' => $event_id,
            'url' => $url,
            'title' => $title,
            'date_raw' => $date_raw,
            'date_label' => $date_label,
            'status' => (function_exists('bvmgr_event_plan_get_status') ? bvmgr_event_plan_get_status($plan_id, 'generic') : sanitize_key((string) get_post_meta($plan_id, (function_exists('bvmgr_meta_key') ? (string) (bvmgr_meta_key('event_plan', 'status') ?: '_vms_event_plan_status') : '_vms_event_plan_status'), true))),
        );
    }
}

if (!function_exists('bvmgr_event_plan_get_public_reschedule_destination')) {
    function bvmgr_event_plan_get_public_reschedule_destination(int $source_plan_id): array
    {
        $source_plan_id = absint($source_plan_id);
        if ($source_plan_id <= 0) {
            return array();
        }

        $child_ids = bvmgr_event_plan_get_rescheduled_child_plan_ids($source_plan_id);
        if (empty($child_ids)) {
            return array();
        }

        $today = (string) wp_date('Y-m-d', time(), wp_timezone());
        $candidates = array();

        foreach ($child_ids as $child_id) {
            $status = function_exists('bvmgr_event_plan_get_status') ? bvmgr_event_plan_get_status($child_id, 'generic') : sanitize_key((string) get_post_meta($child_id, (function_exists('bvmgr_meta_key') ? (string) (bvmgr_meta_key('event_plan', 'status') ?: '_vms_event_plan_status') : '_vms_event_plan_status'), true));
            if ($status === 'cancelled') {
                continue;
            }

            $payload = bvmgr_event_plan_get_public_event_payload($child_id);
            if (empty($payload['url'])) {
                continue;
            }

            $date_raw = (string) ($payload['date_raw'] ?? '');
            $future_rank = 1;
            if ($date_raw !== '' && $date_raw >= $today) {
                $future_rank = 0;
            }

            $candidates[] = array(
                'sort_future' => $future_rank,
                'sort_date' => $date_raw !== '' ? $date_raw : '9999-12-31',
                'sort_id' => $child_id,
                'payload' => $payload,
            );
        }

        if (empty($candidates)) {
            return array();
        }

        usort($candidates, static function (array $a, array $b): int {
            if ($a['sort_future'] !== $b['sort_future']) {
                return $a['sort_future'] <=> $b['sort_future'];
            }
            if ($a['sort_date'] !== $b['sort_date']) {
                return strcmp((string) $a['sort_date'], (string) $b['sort_date']);
            }
            return ((int) $b['sort_id']) <=> ((int) $a['sort_id']);
        });

        return (array) ($candidates[0]['payload'] ?? array());
    }
}

/**
 * Resolve the current VMS Event Plan for a linked TEC event.
 *
 * A TEC event can occasionally have more than one Event Plan pointing at it after
 * rebuilds, imports, restore attempts, or manual repair passes. Public ticketing,
 * guest passes, secondary vendor display, and other TEC-facing renderers must not
 * use the first arbitrary match because that can surface stale template/config data.
 *
 * Ranking favors the most usable/current plan:
 * 1. non-cancelled plans over cancelled plans
 * 2. published plans over drafts/pending/private
 * 3. plans with saved ticketing v2 config
 * 4. most recently modified plan
 * 5. highest post ID as a final deterministic tie-breaker
 *
 * @param int $tec_event_id TEC event post ID.
 * @return int Event Plan ID, or 0 when no valid link exists.
 */
function bvmgr_resolve_event_plan_for_tec_event($tec_event_id)
{
    $tec_event_id = absint($tec_event_id);
    if ($tec_event_id <= 0) {
        return 0;
    }

    $plans = get_posts(array(
        'post_type'      => 'vms_event_plan',
        'posts_per_page' => -1,
        'post_status'    => array('publish', 'draft', 'pending', 'private'),
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- The lifecycle resolver examines every Event Plan linked to one TEC event so deterministic ranking can select the current record.
        'meta_query'     => array(
            array(
                'key'     => '_vms_tec_event_id',
                'value'   => (string) $tec_event_id,
                'compare' => '=',
            ),
        ),
        'fields'         => 'ids',
        'orderby'        => 'modified',
        'order'          => 'DESC',
        'no_found_rows'  => true,
    ));

    if (empty($plans)) {
        return 0;
    }

    $ticketing_config_key = function_exists('bvmgr_meta_key')
        ? (string) bvmgr_meta_key('event_plan', 'ticketing_config_v2')
        : '_vms_ticketing_config_v2';
    if ($ticketing_config_key === '') {
        $ticketing_config_key = '_vms_ticketing_config_v2';
    }

    $status_key = function_exists('bvmgr_meta_key')
        ? (string) bvmgr_meta_key('event_plan', 'status')
        : '_vms_event_plan_status';
    if ($status_key === '') {
        $status_key = '_vms_event_plan_status';
    }

    $ranked = array();
    foreach ($plans as $plan_id) {
        $plan_id = absint($plan_id);
        if ($plan_id <= 0) {
            continue;
        }

        $post = get_post($plan_id);
        if (!($post instanceof WP_Post) || $post->post_type !== 'vms_event_plan') {
            continue;
        }

        $workflow_status = function_exists('bvmgr_event_plan_get_status')
            ? sanitize_key((string) bvmgr_event_plan_get_status($plan_id, 'generic'))
            : sanitize_key((string) get_post_meta($plan_id, $status_key, true));

        $saved_ticketing_config = get_post_meta($plan_id, $ticketing_config_key, true);
        $has_ticketing_config = is_array($saved_ticketing_config) && !empty($saved_ticketing_config);

        $modified_ts = strtotime((string) $post->post_modified_gmt);
        if (!$modified_ts) {
            $modified_ts = strtotime((string) $post->post_modified);
        }
        if (!$modified_ts) {
            $modified_ts = 0;
        }

        $ranked[] = array(
            'id'                   => $plan_id,
            'is_cancelled'         => ($workflow_status === 'cancelled') ? 1 : 0,
            'post_status_priority' => ($post->post_status === 'publish') ? 0 : (($post->post_status === 'private') ? 1 : 2),
            'missing_config'       => $has_ticketing_config ? 0 : 1,
            'modified_ts'          => $modified_ts,
        );
    }

    if (empty($ranked)) {
        return 0;
    }

    usort($ranked, static function (array $a, array $b): int {
        foreach (array('is_cancelled', 'post_status_priority', 'missing_config') as $key) {
            if ((int) $a[$key] !== (int) $b[$key]) {
                return (int) $a[$key] <=> (int) $b[$key];
            }
        }
        if ((int) $a['modified_ts'] !== (int) $b['modified_ts']) {
            return (int) $b['modified_ts'] <=> (int) $a['modified_ts'];
        }
        return (int) $b['id'] <=> (int) $a['id'];
    });

    return (int) $ranked[0]['id'];
}

function bvmgr_get_event_plan_for_tec_event($tec_event_id)
{
    $plan_id = function_exists('bvmgr_resolve_event_plan_for_tec_event')
        ? (int) bvmgr_resolve_event_plan_for_tec_event($tec_event_id)
        : 0;

    return $plan_id > 0 ? $plan_id : null;
}

/**
 * Get Woo ticket product IDs for a TEC event.
 *
 * @param int $event_id
 * @return int[]
 */
function bvmgr_get_ticket_product_ids_for_event($event_id)
{
    $event_id = (int) $event_id;
    if (!$event_id) {
        return array();
    }

    $tickets = get_posts(array(
        'post_type'      => 'product',
        'posts_per_page' => -1,
        'post_status'    => array('publish','future','draft','pending','private'),
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Ticket lookup intentionally returns every product linked to this one TEC event for complete downstream handling.
        'meta_query'     => array(
            array(
                'key'   => '_tribe_wooticket_for_event',
                'value' => $event_id,
            ),
        ),
        'fields'         => 'ids',
    ));

    return array_map('intval', $tickets);
}

function bvmgr_vendor_app_redirect($app_id, $result)
{
    $url = add_query_arg(array(
        'post'   => $app_id,
        'action' => 'edit',
        'vms_app_result' => $result,
    ), admin_url('post.php'));

    wp_safe_redirect($url);
    exit;
}

add_filter('get_avatar_url', 'bvmgr_vendor_avatar_from_logo', 10, 3);
function bvmgr_vendor_avatar_from_logo($url, $id_or_email, $args)
{
    // Identify user ID from the avatar call
    $user = false;

    if (is_numeric($id_or_email)) {
        $user = get_user_by('id', (int) $id_or_email);
    } elseif (is_object($id_or_email) && !empty($id_or_email->user_id)) {
        $user = get_user_by('id', (int) $id_or_email->user_id);
    } elseif (is_string($id_or_email)) {
        $user = get_user_by('email', $id_or_email);
    }

    if (!$user) return $url;

    // Only override for vendor users that are linked to a vendor profile
    $vendor_id = 0;
    if (function_exists('bvmgr_get_primary_vendor_id_for_user')) {
        $vendor_id = bvmgr_get_primary_vendor_id_for_user((int) $user->ID);
    } else {
        $vendor_id = (int) get_user_meta($user->ID, '_vms_vendor_id', true);
    }
    if (!$vendor_id) return $url;

    $thumb_id = get_post_thumbnail_id($vendor_id);
    if (!$thumb_id) return $url;

    $custom = wp_get_attachment_image_url($thumb_id, 'thumbnail');
    return $custom ? $custom : $url;
}

function bvmgr_get_timezone_id(): string
{
    $opts = (array) get_option('vms_settings', array());
    $tz = isset($opts['timezone']) ? trim((string)$opts['timezone']) : '';

    if ($tz !== '') return $tz;

    $wp_tz = (string) get_option('timezone_string');
    if ($wp_tz !== '') return $wp_tz;

    return 'UTC';
}

function bvmgr_get_timezone(): DateTimeZone
{
    $opts = (array) get_option('vms_settings', array());
    $tz = isset($opts['timezone']) ? trim((string) $opts['timezone']) : '';

    if ($tz !== '') {
        return new DateTimeZone($tz);
    }

    // Always fall back to WP's timezone object (handles city tz and UTC offsets correctly)
    if (function_exists('wp_timezone')) {
        return wp_timezone();
    }

    return new DateTimeZone('UTC');
}

function bvmgr_get_event_titles_by_date(array $active_dates): array
{
    $active_dates = array_values(array_filter(array_map('trim', $active_dates)));
    if (!$active_dates) return array();

    $active_set = array_fill_keys($active_dates, true);
    $map = array();
    $tz  = bvmgr_get_timezone();

    $q = new WP_Query(array(
        'post_type'      => 'tribe_events',
        'post_status'    => array('publish', 'draft', 'pending'),
        'posts_per_page' => -1,
        'fields'         => 'ids',
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- This legacy helper scans every dated TEC event, then filters against the caller's finite active-date set in PHP.
        'meta_query'     => array(
            array('key' => '_EventStartDate', 'compare' => 'EXISTS'),
        ),
    ));

    foreach ($q->posts as $event_id) {
        $start = (string) get_post_meta($event_id, '_EventStartDate', true);
        if ($start === '') continue;

        try {
            $dt = new DateTime($start, $tz);
        } catch (Exception $e) {
            continue;
        }

        $ymd = $dt->format('Y-m-d');
        if (!isset($active_set[$ymd])) continue;

        $map[$ymd] = get_the_title($event_id);
    }

    wp_reset_postdata();
    return $map;
}

if (!function_exists('bvmgr_event_plan_build_auto_title')) {
    /**
     * Build the canonical auto-generated Event Plan title.
     *
     * UX-06 rule: automatic titles are band-only (no date suffix).
     */
    function bvmgr_event_plan_build_auto_title(string $band_name): string
    {
        return trim(wp_strip_all_tags((string) $band_name));
    }
}

if (!function_exists('bvmgr_event_plan_build_fallback_title')) {
    /**
     * Build a non-empty fallback Event Plan title for save-time safety.
     *
     * Priority:
     * 1) Band title
     * 2) Venue title
     * 3) Event Plan #{post_id}
     */
    function bvmgr_event_plan_build_fallback_title(int $post_id, string $band_name = '', string $venue_name = ''): string
    {
        $title = function_exists('bvmgr_event_plan_build_auto_title')
            ? bvmgr_event_plan_build_auto_title($band_name)
            : trim(wp_strip_all_tags((string) $band_name));
        if ($title !== '') {
            return $title;
        }

        $venue_title = trim(wp_strip_all_tags((string) $venue_name));
        if ($venue_title !== '') {
            return $venue_title;
        }

        return 'Event Plan #' . (int) $post_id;
    }
}

/** VENDOR COMP PACKAGES
 * -------------------------------------------------
 * Functions for vendor comp packages feature
 * -------------------------------------------------
 */

/**
 * Fetch comp packages for a venue (and optionally global packages).
 */
function bvmgr_get_comp_packages_for_venue(int $venue_id, bool $include_global = true): array
{
    if ($include_global) {
        $meta_query = array(
            'relation' => 'OR',
            array(
                'key'     => '_vms_venue_id',
                'value'   => $venue_id,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ),
            array(
                'key'     => '_vms_venue_id',
                'value'   => 0,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ),
            array(
                'key'     => '_vms_venue_id',
                'compare' => 'NOT EXISTS',
            ),
        );
    } else {
        $meta_query = array(
            array(
                'key'     => '_vms_venue_id',
                'value'   => $venue_id,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ),
        );
    }

    return get_posts(array(
        'post_type'      => 'vms_comp_package',
        'post_status'    => array('publish', 'draft'),
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- The selector returns every published or draft package in the requested venue and optional global scope so choices remain complete.
        'meta_query'     => $meta_query,
    ));
}

if (!function_exists('bvmgr_comp_supported_structures')) {
    /**
     * @return string[]
     */
    function bvmgr_comp_supported_structures(): array
    {
        return array('flat_fee', 'door_split', 'flat_fee_door_split', 'attendance_bonus');
    }
}

if (!function_exists('bvmgr_comp_structure_is_supported')) {
    function bvmgr_comp_structure_is_supported(string $structure): bool
    {
        return in_array(sanitize_key($structure), bvmgr_comp_supported_structures(), true);
    }
}

if (!function_exists('bvmgr_attendance_bonus_supported_modes')) {
    /**
     * @return string[]
     */
    function bvmgr_attendance_bonus_supported_modes(): array
    {
        return array('step', 'continuous');
    }
}

if (!function_exists('bvmgr_normalize_attendance_bonus_mode')) {
    function bvmgr_normalize_attendance_bonus_mode(string $mode): string
    {
        $mode = sanitize_key($mode);
        return in_array($mode, bvmgr_attendance_bonus_supported_modes(), true) ? $mode : '';
    }
}

if (!function_exists('bvmgr_comp_structure_uses_flat_amount')) {
    function bvmgr_comp_structure_uses_flat_amount(string $structure): bool
    {
        return in_array(sanitize_key($structure), array('flat_fee', 'flat_fee_door_split', 'attendance_bonus'), true);
    }
}

if (!function_exists('bvmgr_comp_structure_uses_door_split')) {
    function bvmgr_comp_structure_uses_door_split(string $structure): bool
    {
        return in_array(sanitize_key($structure), array('door_split', 'flat_fee_door_split'), true);
    }
}

if (!function_exists('bvmgr_normalize_comp_nonnegative_float')) {
    function bvmgr_normalize_comp_nonnegative_float($value): ?float
    {
        if ($value === '' || $value === null || !is_numeric($value)) {
            return null;
        }

        $normalized = (float) $value;
        if ($normalized < 0) {
            $normalized = 0.0;
        }

        return $normalized;
    }
}

if (!function_exists('bvmgr_normalize_comp_nonnegative_int')) {
    function bvmgr_normalize_comp_nonnegative_int($value): ?int
    {
        if ($value === '' || $value === null || !is_numeric($value)) {
            return null;
        }

        $normalized = (int) floor((float) $value);
        if ($normalized < 0) {
            $normalized = 0;
        }

        return $normalized;
    }
}


if (!function_exists('bvmgr_normalize_agent_fee_mode')) {
    function bvmgr_normalize_agent_fee_mode($value): string
    {
        $mode = sanitize_key((string) $value);
        return in_array($mode, array('artist_fee', 'gross'), true) ? $mode : '';
    }
}

if (!function_exists('bvmgr_normalize_agent_fee_percent')) {
    function bvmgr_normalize_agent_fee_percent($value): ?float
    {
        $pct = bvmgr_normalize_comp_nonnegative_float($value);
        if ($pct === null) {
            return null;
        }
        if ($pct > 100.0) {
            $pct = 100.0;
        }
        return $pct;
    }
}

if (!function_exists('bvmgr_get_vendor_default_agent_fee_terms')) {
    function bvmgr_get_vendor_default_agent_fee_terms(int $vendor_id): array
    {
        if ($vendor_id <= 0) {
            return array();
        }

        $k_pct = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('vendor', 'default_commission_percent') ?: '_vms_default_commission_percent') : '_vms_default_commission_percent';
        $k_mode = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('vendor', 'default_commission_mode') ?: '_vms_default_commission_mode') : '_vms_default_commission_mode';

        $pct = bvmgr_normalize_agent_fee_percent(get_post_meta($vendor_id, $k_pct, true));
        $mode = bvmgr_normalize_agent_fee_mode(get_post_meta($vendor_id, $k_mode, true));
        if ($pct === null || $pct <= 0) {
            return array();
        }
        if ($mode === '') {
            $mode = 'artist_fee';
        }

        return array(
            'commission_percent' => $pct,
            'commission_mode' => $mode,
        );
    }
}

if (!function_exists('bvmgr_calculate_agent_fee_amount')) {
    function bvmgr_calculate_agent_fee_amount($base_amount, $commission_percent, string $commission_mode = 'artist_fee'): ?float
    {
        $base = bvmgr_normalize_comp_nonnegative_float($base_amount);
        $pct = bvmgr_normalize_agent_fee_percent($commission_percent);
        $mode = bvmgr_normalize_agent_fee_mode($commission_mode);

        if ($base === null || $pct === null || $pct <= 0 || $mode !== 'artist_fee') {
            return null;
        }

        return round(($base * $pct) / 100, 2);
    }
}


if (!function_exists('bvmgr_comp_deposit_status_options')) {
    function bvmgr_comp_deposit_status_options(): array
    {
        return array(
            'not_required' => __('Not required', 'backstage-venue-manager'),
            'unpaid'       => __('Unpaid', 'backstage-venue-manager'),
            'paid'         => __('Paid', 'backstage-venue-manager'),
            'waived'       => __('Waived', 'backstage-venue-manager'),
            'refunded'     => __('Refunded', 'backstage-venue-manager'),
        );
    }
}

if (!function_exists('bvmgr_comp_deposit_treatment_options')) {
    function bvmgr_comp_deposit_treatment_options(): array
    {
        return array(
            'creditable'    => __('Applies toward total payment', 'backstage-venue-manager'),
            'refundable'    => __('Refundable', 'backstage-venue-manager'),
            'nonrefundable' => __('Non-refundable', 'backstage-venue-manager'),
        );
    }
}

if (!function_exists('bvmgr_normalize_comp_deposit_status')) {
    function bvmgr_normalize_comp_deposit_status($value): string
    {
        $status = sanitize_key((string) $value);
        return array_key_exists($status, bvmgr_comp_deposit_status_options()) ? $status : 'not_required';
    }
}

if (!function_exists('bvmgr_normalize_comp_deposit_treatment')) {
    function bvmgr_normalize_comp_deposit_treatment($value): string
    {
        $treatment = sanitize_key((string) $value);
        return array_key_exists($treatment, bvmgr_comp_deposit_treatment_options()) ? $treatment : 'creditable';
    }
}

if (!function_exists('bvmgr_normalize_comp_deposit_date')) {
    function bvmgr_normalize_comp_deposit_date($value): string
    {
        $date = trim((string) $value);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : '';
    }
}

if (!function_exists('bvmgr_get_event_plan_deposit_terms')) {
    function bvmgr_get_event_plan_deposit_terms(int $plan_id): array
    {
        return array(
            'deposit_amount'    => get_post_meta($plan_id, '_vms_deposit_amount', true),
            'deposit_status'    => (string) get_post_meta($plan_id, '_vms_deposit_status', true),
            'deposit_treatment' => (string) get_post_meta($plan_id, '_vms_deposit_treatment', true),
            'deposit_due_date'  => (string) get_post_meta($plan_id, '_vms_deposit_due_date', true),
            'deposit_paid_date' => (string) get_post_meta($plan_id, '_vms_deposit_paid_date', true),
            'deposit_notes'     => (string) get_post_meta($plan_id, '_vms_deposit_notes', true),
        );
    }
}

if (!function_exists('bvmgr_event_plan_save_deposit_terms')) {
    function bvmgr_event_plan_save_deposit_terms(int $plan_id, array $terms): void
    {
        $amount = bvmgr_normalize_comp_nonnegative_float($terms['deposit_amount'] ?? null);
        $status = bvmgr_normalize_comp_deposit_status($terms['deposit_status'] ?? '');
        $treatment = bvmgr_normalize_comp_deposit_treatment($terms['deposit_treatment'] ?? '');
        $due_date = bvmgr_normalize_comp_deposit_date($terms['deposit_due_date'] ?? '');
        $paid_date = bvmgr_normalize_comp_deposit_date($terms['deposit_paid_date'] ?? '');
        $notes = isset($terms['deposit_notes']) ? sanitize_textarea_field((string) $terms['deposit_notes']) : '';

        if ($amount !== null && $amount > 0 && $status === 'not_required') {
            $status = 'unpaid';
        }

        $has_deposit = (
            ($amount !== null && $amount > 0)
            || $status !== 'not_required'
            || $due_date !== ''
            || $paid_date !== ''
            || $notes !== ''
        );

        $keys = array(
            '_vms_deposit_amount',
            '_vms_deposit_status',
            '_vms_deposit_treatment',
            '_vms_deposit_due_date',
            '_vms_deposit_paid_date',
            '_vms_deposit_notes',
        );

        if (!$has_deposit) {
            foreach ($keys as $key) {
                delete_post_meta($plan_id, $key);
            }
            return;
        }

        if ($amount === null) {
            delete_post_meta($plan_id, '_vms_deposit_amount');
        } else {
            update_post_meta($plan_id, '_vms_deposit_amount', (float) $amount);
        }

        update_post_meta($plan_id, '_vms_deposit_status', $status);
        update_post_meta($plan_id, '_vms_deposit_treatment', $treatment);

        if ($due_date === '') delete_post_meta($plan_id, '_vms_deposit_due_date');
        else update_post_meta($plan_id, '_vms_deposit_due_date', $due_date);

        if ($paid_date === '') delete_post_meta($plan_id, '_vms_deposit_paid_date');
        else update_post_meta($plan_id, '_vms_deposit_paid_date', $paid_date);

        if ($notes === '') delete_post_meta($plan_id, '_vms_deposit_notes');
        else update_post_meta($plan_id, '_vms_deposit_notes', $notes);
    }
}

if (!function_exists('bvmgr_comp_deposit_summary_part')) {
    function bvmgr_comp_deposit_summary_part(array $terms): string
    {
        $amount = bvmgr_normalize_comp_nonnegative_float($terms['deposit_amount'] ?? null);
        $status = bvmgr_normalize_comp_deposit_status($terms['deposit_status'] ?? '');
        $treatment = bvmgr_normalize_comp_deposit_treatment($terms['deposit_treatment'] ?? '');
        $due_date = bvmgr_normalize_comp_deposit_date($terms['deposit_due_date'] ?? '');
        $paid_date = bvmgr_normalize_comp_deposit_date($terms['deposit_paid_date'] ?? '');

        if (($amount === null || $amount <= 0) && $status === 'not_required' && $due_date === '' && $paid_date === '') {
            return '';
        }

        $status_options = bvmgr_comp_deposit_status_options();
        $treatment_options = bvmgr_comp_deposit_treatment_options();
        $parts = array();
        $parts[] = 'Deposit: ' . (($amount !== null && $amount > 0) ? '$' . number_format($amount, 2) : 'No amount set');
        $parts[] = $status_options[$status] ?? $status;
        $parts[] = $treatment_options[$treatment] ?? $treatment;
        if ($due_date !== '') {
            $parts[] = 'Due ' . $due_date;
        }
        if ($paid_date !== '') {
            $parts[] = 'Paid ' . $paid_date;
        }

        return implode(', ', $parts);
    }
}


if (!function_exists('bvmgr_comp_final_payment_timing_options')) {
    function bvmgr_comp_final_payment_timing_options(): array
    {
        return array(
            'not_set'       => __('Not set', 'backstage-venue-manager'),
            'in_advance'    => __('In advance', 'backstage-venue-manager'),
            'day_of_event'  => __('Day of event', 'backstage-venue-manager'),
            'days_after'    => __('N days after event', 'backstage-venue-manager'),
            'fixed_date'    => __('Specific date', 'backstage-venue-manager'),
            'custom'        => __('Custom timing', 'backstage-venue-manager'),
        );
    }
}

if (!function_exists('bvmgr_comp_final_payment_method_options')) {
    function bvmgr_comp_final_payment_method_options(): array
    {
        return array(
            'not_set'            => __('Not set', 'backstage-venue-manager'),
            'check'              => __('Check', 'backstage-venue-manager'),
            'cash'               => __('Cash', 'backstage-venue-manager'),
            'ach_direct_deposit' => __('ACH / Direct Deposit', 'backstage-venue-manager'),
            'zelle'              => __('Zelle', 'backstage-venue-manager'),
            'venmo'              => __('Venmo', 'backstage-venue-manager'),
            'paypal'             => __('PayPal', 'backstage-venue-manager'),
            'other'              => __('Other', 'backstage-venue-manager'),
        );
    }
}

if (!function_exists('bvmgr_normalize_comp_final_payment_timing')) {
    function bvmgr_normalize_comp_final_payment_timing($value): string
    {
        $timing = sanitize_key((string) $value);
        return array_key_exists($timing, bvmgr_comp_final_payment_timing_options()) ? $timing : 'not_set';
    }
}

if (!function_exists('bvmgr_normalize_comp_final_payment_method')) {
    function bvmgr_normalize_comp_final_payment_method($value): string
    {
        $method = sanitize_key((string) $value);
        return array_key_exists($method, bvmgr_comp_final_payment_method_options()) ? $method : 'not_set';
    }
}

if (!function_exists('bvmgr_normalize_comp_final_payment_days_after')) {
    function bvmgr_normalize_comp_final_payment_days_after($value): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return '';
        }
        $raw = preg_replace('/[^0-9]/', '', $raw);
        if ($raw === '') {
            return '';
        }
        $days = absint($raw);
        return (string) min($days, 365);
    }
}

if (!function_exists('bvmgr_normalize_comp_final_payment_date')) {
    function bvmgr_normalize_comp_final_payment_date($value): string
    {
        $date = trim((string) $value);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : '';
    }
}

if (!function_exists('bvmgr_get_event_plan_final_payment_terms')) {
    function bvmgr_get_event_plan_final_payment_terms(int $plan_id): array
    {
        return array(
            'final_payment_timing'       => (string) get_post_meta($plan_id, '_vms_final_payment_timing', true),
            'final_payment_days_after'   => (string) get_post_meta($plan_id, '_vms_final_payment_days_after', true),
            'final_payment_date'         => (string) get_post_meta($plan_id, '_vms_final_payment_date', true),
            'final_payment_custom_text'  => (string) get_post_meta($plan_id, '_vms_final_payment_custom_text', true),
            'final_payment_method'       => (string) get_post_meta($plan_id, '_vms_final_payment_method', true),
            'final_payment_method_other' => (string) get_post_meta($plan_id, '_vms_final_payment_method_other', true),
        );
    }
}

if (!function_exists('bvmgr_event_plan_save_final_payment_terms')) {
    function bvmgr_event_plan_save_final_payment_terms(int $plan_id, array $terms): void
    {
        $timing = bvmgr_normalize_comp_final_payment_timing($terms['final_payment_timing'] ?? '');
        $days_after = bvmgr_normalize_comp_final_payment_days_after($terms['final_payment_days_after'] ?? '');
        $fixed_date = bvmgr_normalize_comp_final_payment_date($terms['final_payment_date'] ?? '');
        $custom_text = isset($terms['final_payment_custom_text']) ? sanitize_text_field((string) $terms['final_payment_custom_text']) : '';
        $method = bvmgr_normalize_comp_final_payment_method($terms['final_payment_method'] ?? '');
        $method_other = isset($terms['final_payment_method_other']) ? sanitize_text_field((string) $terms['final_payment_method_other']) : '';

        if ($timing !== 'days_after') {
            $days_after = '';
        }
        if ($timing !== 'fixed_date') {
            $fixed_date = '';
        }
        if ($timing !== 'custom') {
            $custom_text = '';
        }
        if ($method !== 'other') {
            $method_other = '';
        }

        $has_terms = (
            $timing !== 'not_set'
            || $method !== 'not_set'
            || $days_after !== ''
            || $fixed_date !== ''
            || $custom_text !== ''
            || $method_other !== ''
        );

        $keys = array(
            '_vms_final_payment_timing',
            '_vms_final_payment_days_after',
            '_vms_final_payment_date',
            '_vms_final_payment_custom_text',
            '_vms_final_payment_method',
            '_vms_final_payment_method_other',
        );

        if (!$has_terms) {
            foreach ($keys as $key) {
                delete_post_meta($plan_id, $key);
            }
            return;
        }

        update_post_meta($plan_id, '_vms_final_payment_timing', $timing);
        update_post_meta($plan_id, '_vms_final_payment_method', $method);

        if ($days_after === '') delete_post_meta($plan_id, '_vms_final_payment_days_after');
        else update_post_meta($plan_id, '_vms_final_payment_days_after', $days_after);

        if ($fixed_date === '') delete_post_meta($plan_id, '_vms_final_payment_date');
        else update_post_meta($plan_id, '_vms_final_payment_date', $fixed_date);

        if ($custom_text === '') delete_post_meta($plan_id, '_vms_final_payment_custom_text');
        else update_post_meta($plan_id, '_vms_final_payment_custom_text', $custom_text);

        if ($method_other === '') delete_post_meta($plan_id, '_vms_final_payment_method_other');
        else update_post_meta($plan_id, '_vms_final_payment_method_other', $method_other);
    }
}

if (!function_exists('bvmgr_comp_final_payment_timing_label')) {
    function bvmgr_comp_final_payment_timing_label(array $terms): string
    {
        $timing = bvmgr_normalize_comp_final_payment_timing($terms['final_payment_timing'] ?? '');
        $days_after = bvmgr_normalize_comp_final_payment_days_after($terms['final_payment_days_after'] ?? '');
        $fixed_date = bvmgr_normalize_comp_final_payment_date($terms['final_payment_date'] ?? '');
        $custom_text = isset($terms['final_payment_custom_text']) ? trim((string) $terms['final_payment_custom_text']) : '';

        if ($timing === 'in_advance') {
            return __('In advance', 'backstage-venue-manager');
        }
        if ($timing === 'day_of_event') {
            return __('Day of event', 'backstage-venue-manager');
        }
        if ($timing === 'days_after') {
            if ($days_after !== '') {
                /* translators: %s: human-readable value used in this message. */
                return sprintf(_n('%s day after event', '%s days after event', (int) $days_after, 'backstage-venue-manager'), $days_after);
            }
            return __('After event - number of days not set', 'backstage-venue-manager');
        }
        if ($timing === 'fixed_date') {
            /* translators: %s: specific date. */
            return $fixed_date !== '' ? sprintf(__('Specific date: %s', 'backstage-venue-manager'), $fixed_date) : __('Specific date not set', 'backstage-venue-manager');
        }
        if ($timing === 'custom') {
            return $custom_text !== '' ? $custom_text : __('Custom timing not set', 'backstage-venue-manager');
        }

        return '';
    }
}

if (!function_exists('bvmgr_comp_final_payment_method_label')) {
    function bvmgr_comp_final_payment_method_label(array $terms): string
    {
        $method = bvmgr_normalize_comp_final_payment_method($terms['final_payment_method'] ?? '');
        $other = isset($terms['final_payment_method_other']) ? trim((string) $terms['final_payment_method_other']) : '';
        if ($method === 'other') {
            return $other !== '' ? $other : __('Other method not specified', 'backstage-venue-manager');
        }
        if ($method === 'not_set') {
            return '';
        }
        $options = bvmgr_comp_final_payment_method_options();
        return (string) ($options[$method] ?? ucwords(str_replace('_', ' ', $method)));
    }
}

if (!function_exists('bvmgr_comp_final_payment_summary_part')) {
    function bvmgr_comp_final_payment_summary_part(array $terms): string
    {
        $timing_label = bvmgr_comp_final_payment_timing_label($terms);
        $method_label = bvmgr_comp_final_payment_method_label($terms);
        $parts = array();
        if ($timing_label !== '') {
            $parts[] = __('Expected final payment:', 'backstage-venue-manager') . ' ' . $timing_label;
        }
        if ($method_label !== '') {
            $parts[] = __('Payment method:', 'backstage-venue-manager') . ' ' . $method_label;
        }
        return implode('; ', $parts);
    }
}

if (!function_exists('bvmgr_comp_hash_from_terms')) {
    function bvmgr_comp_hash_from_terms(array $terms): string
    {
        $structure = isset($terms['structure']) ? sanitize_key((string) $terms['structure']) : '';
        if (!bvmgr_comp_structure_is_supported($structure)) {
            $structure = 'flat_fee';
        }

        $flat = bvmgr_normalize_comp_nonnegative_float($terms['flat_fee_amount'] ?? null);
        $split = bvmgr_normalize_comp_nonnegative_float($terms['door_split_percent'] ?? null);
        if ($split !== null && $split > 100.0) {
            $split = 100.0;
        }

        $commission_percent = bvmgr_normalize_agent_fee_percent($terms['commission_percent'] ?? null);
        $commission_mode = bvmgr_normalize_agent_fee_mode($terms['commission_mode'] ?? '');
        $deposit_amount = bvmgr_normalize_comp_nonnegative_float($terms['deposit_amount'] ?? null);
        $deposit_status = bvmgr_normalize_comp_deposit_status($terms['deposit_status'] ?? '');
        $deposit_treatment = bvmgr_normalize_comp_deposit_treatment($terms['deposit_treatment'] ?? '');
        $deposit_due_date = bvmgr_normalize_comp_deposit_date($terms['deposit_due_date'] ?? '');
        $deposit_paid_date = bvmgr_normalize_comp_deposit_date($terms['deposit_paid_date'] ?? '');
        $deposit_notes = isset($terms['deposit_notes']) ? sanitize_textarea_field((string) $terms['deposit_notes']) : '';
        $final_payment_timing = bvmgr_normalize_comp_final_payment_timing($terms['final_payment_timing'] ?? '');
        $final_payment_days_after = bvmgr_normalize_comp_final_payment_days_after($terms['final_payment_days_after'] ?? '');
        $final_payment_date = bvmgr_normalize_comp_final_payment_date($terms['final_payment_date'] ?? '');
        $final_payment_custom_text = isset($terms['final_payment_custom_text']) ? sanitize_text_field((string) $terms['final_payment_custom_text']) : '';
        $final_payment_method = bvmgr_normalize_comp_final_payment_method($terms['final_payment_method'] ?? '');
        $final_payment_method_other = isset($terms['final_payment_method_other']) ? sanitize_text_field((string) $terms['final_payment_method_other']) : '';

        if ($final_payment_timing !== 'days_after') {
            $final_payment_days_after = '';
        }
        if ($final_payment_timing !== 'fixed_date') {
            $final_payment_date = '';
        }
        if ($final_payment_timing !== 'custom') {
            $final_payment_custom_text = '';
        }
        if ($final_payment_method !== 'other') {
            $final_payment_method_other = '';
        }

        if ($deposit_amount !== null && $deposit_amount > 0 && $deposit_status === 'not_required') {
            $deposit_status = 'unpaid';
        }

        $payload = array(
            'structure' => $structure,
            'flat' => $flat === null ? '' : number_format($flat, 2, '.', ''),
            'split' => ($split === null || !bvmgr_comp_structure_uses_door_split($structure))
                ? ''
                : rtrim(rtrim((string) $split, '0'), '.'),
            'attendance_bonus_mode' => '',
            'attendance_bonus_start_count' => '',
            'attendance_bonus_step_size' => '',
            'attendance_bonus_step_bonus' => '',
            'attendance_bonus_per_ticket_rate' => '',
            'attendance_bonus_max_bonus' => '',
            'commission_percent' => $commission_percent === null ? '' : number_format($commission_percent, 2, '.', ''),
            'commission_mode' => $commission_percent === null ? '' : ($commission_mode === '' ? 'artist_fee' : $commission_mode),
            'deposit_amount' => $deposit_amount === null ? '' : number_format($deposit_amount, 2, '.', ''),
            'deposit_status' => ($deposit_amount === null && $deposit_status === 'not_required' && $deposit_due_date === '' && $deposit_paid_date === '' && $deposit_notes === '') ? '' : $deposit_status,
            'deposit_treatment' => ($deposit_amount === null && $deposit_status === 'not_required' && $deposit_due_date === '' && $deposit_paid_date === '' && $deposit_notes === '') ? '' : $deposit_treatment,
            'deposit_due_date' => $deposit_due_date,
            'deposit_paid_date' => $deposit_paid_date,
            'deposit_notes' => $deposit_notes,
            'final_payment_timing' => $final_payment_timing === 'not_set' ? '' : $final_payment_timing,
            'final_payment_days_after' => $final_payment_days_after,
            'final_payment_date' => $final_payment_date,
            'final_payment_custom_text' => $final_payment_custom_text,
            'final_payment_method' => $final_payment_method === 'not_set' ? '' : $final_payment_method,
            'final_payment_method_other' => $final_payment_method_other,
        );

        if ($structure === 'attendance_bonus') {
            $mode = bvmgr_normalize_attendance_bonus_mode((string) ($terms['attendance_bonus_mode'] ?? ''));
            $start_count = bvmgr_normalize_comp_nonnegative_int($terms['attendance_bonus_start_count'] ?? null);
            $step_size = bvmgr_normalize_comp_nonnegative_int($terms['attendance_bonus_step_size'] ?? null);
            $step_bonus = bvmgr_normalize_comp_nonnegative_float($terms['attendance_bonus_step_bonus'] ?? null);
            $per_ticket_rate = bvmgr_normalize_comp_nonnegative_float($terms['attendance_bonus_per_ticket_rate'] ?? null);
            $max_bonus = bvmgr_normalize_comp_nonnegative_float($terms['attendance_bonus_max_bonus'] ?? null);

            $payload['attendance_bonus_mode'] = $mode;
            $payload['attendance_bonus_start_count'] = $start_count === null ? '' : (string) $start_count;
            $payload['attendance_bonus_max_bonus'] = $max_bonus === null ? '' : number_format($max_bonus, 2, '.', '');

            if ($mode === 'step') {
                $payload['attendance_bonus_step_size'] = ($step_size === null || $step_size < 1) ? '' : (string) $step_size;
                $payload['attendance_bonus_step_bonus'] = $step_bonus === null ? '' : number_format($step_bonus, 2, '.', '');
            } elseif ($mode === 'continuous') {
                $payload['attendance_bonus_per_ticket_rate'] = $per_ticket_rate === null ? '' : number_format($per_ticket_rate, 2, '.', '');
            }
        }

        return hash('sha256', wp_json_encode($payload));
    }
}

if (!function_exists('bvmgr_get_event_plan_comp_terms')) {
    function bvmgr_get_event_plan_comp_terms(int $plan_id): array
    {
        $terms = array(
            'structure' => (string) get_post_meta($plan_id, '_vms_comp_structure', true),
            'flat_fee_amount' => get_post_meta($plan_id, '_vms_flat_fee_amount', true),
            'door_split_percent' => get_post_meta($plan_id, '_vms_door_split_percent', true),
            'attendance_bonus_mode' => (string) get_post_meta($plan_id, '_vms_attendance_bonus_mode', true),
            'attendance_bonus_start_count' => get_post_meta($plan_id, '_vms_attendance_bonus_start_count', true),
            'attendance_bonus_step_size' => get_post_meta($plan_id, '_vms_attendance_bonus_step_size', true),
            'attendance_bonus_step_bonus' => get_post_meta($plan_id, '_vms_attendance_bonus_step_bonus', true),
            'attendance_bonus_per_ticket_rate' => get_post_meta($plan_id, '_vms_attendance_bonus_per_ticket_rate', true),
            'attendance_bonus_max_bonus' => get_post_meta($plan_id, '_vms_attendance_bonus_max_bonus', true),
            'commission_percent' => get_post_meta($plan_id, '_vms_commission_percent', true),
            'commission_mode' => (string) get_post_meta($plan_id, '_vms_commission_mode', true),
        );

        if (function_exists('bvmgr_get_event_plan_deposit_terms')) {
            $terms = array_merge($terms, bvmgr_get_event_plan_deposit_terms($plan_id));
        }

        if (function_exists('bvmgr_get_event_plan_final_payment_terms')) {
            $terms = array_merge($terms, bvmgr_get_event_plan_final_payment_terms($plan_id));
        }

        $structure = sanitize_key((string) ($terms['structure'] ?? ''));
        if (!bvmgr_comp_structure_is_supported($structure)) {
            $terms['structure'] = 'flat_fee';
        }

        return $terms;
    }
}

if (!function_exists('bvmgr_event_plan_apply_comp_terms')) {
    function bvmgr_event_plan_apply_comp_terms(int $plan_id, array $terms): bool
    {
        $terms = bvmgr_normalize_comp_terms($terms);
        $structure = isset($terms['structure']) ? sanitize_key((string) $terms['structure']) : '';
        if ($structure === '' || !bvmgr_comp_structure_is_supported($structure)) {
            return false;
        }

        update_post_meta($plan_id, '_vms_comp_structure', $structure);

        if (array_key_exists('flat_fee_amount', $terms)) {
            update_post_meta($plan_id, '_vms_flat_fee_amount', (float) $terms['flat_fee_amount']);
        } else {
            delete_post_meta($plan_id, '_vms_flat_fee_amount');
        }

        if (array_key_exists('door_split_percent', $terms) && bvmgr_comp_structure_uses_door_split($structure)) {
            update_post_meta($plan_id, '_vms_door_split_percent', (float) $terms['door_split_percent']);
        } else {
            delete_post_meta($plan_id, '_vms_door_split_percent');
        }

        $k_commission_override_none = function_exists('bvmgr_meta_key')
            ? (bvmgr_meta_key('event_plan', 'commission_override_none') ?: '_vms_commission_override_none')
            : '_vms_commission_override_none';

        $commission_percent = bvmgr_normalize_agent_fee_percent($terms['commission_percent'] ?? null);
        $commission_mode = bvmgr_normalize_agent_fee_mode($terms['commission_mode'] ?? '');
        if ($commission_percent !== null && $commission_percent > 0) {
            update_post_meta($plan_id, '_vms_commission_percent', (float) $commission_percent);
            update_post_meta($plan_id, '_vms_commission_mode', $commission_mode !== '' ? $commission_mode : 'artist_fee');
            delete_post_meta($plan_id, $k_commission_override_none);
        } else {
            delete_post_meta($plan_id, '_vms_commission_percent');
            delete_post_meta($plan_id, '_vms_commission_mode');
            if (array_key_exists('commission_percent', $terms)) {
                update_post_meta($plan_id, $k_commission_override_none, '1');
            }
        }

        $has_deposit_keys = false;
        foreach (array('deposit_amount', 'deposit_status', 'deposit_treatment', 'deposit_due_date', 'deposit_paid_date', 'deposit_notes') as $deposit_key) {
            if (array_key_exists($deposit_key, $terms)) {
                $has_deposit_keys = true;
                break;
            }
        }
        if ($has_deposit_keys && function_exists('bvmgr_event_plan_save_deposit_terms')) {
            bvmgr_event_plan_save_deposit_terms($plan_id, $terms);
        }

        $has_final_payment_keys = false;
        foreach (array('final_payment_timing', 'final_payment_days_after', 'final_payment_date', 'final_payment_custom_text', 'final_payment_method', 'final_payment_method_other') as $final_payment_key) {
            if (array_key_exists($final_payment_key, $terms)) {
                $has_final_payment_keys = true;
                break;
            }
        }
        if ($has_final_payment_keys && function_exists('bvmgr_event_plan_save_final_payment_terms')) {
            bvmgr_event_plan_save_final_payment_terms($plan_id, $terms);
        }

        if ($structure === 'attendance_bonus') {
            update_post_meta($plan_id, '_vms_attendance_bonus_mode', (string) ($terms['attendance_bonus_mode'] ?? ''));
            update_post_meta($plan_id, '_vms_attendance_bonus_start_count', (int) ($terms['attendance_bonus_start_count'] ?? 0));
            if (array_key_exists('attendance_bonus_max_bonus', $terms) && $terms['attendance_bonus_max_bonus'] !== null && $terms['attendance_bonus_max_bonus'] !== '') {
                update_post_meta($plan_id, '_vms_attendance_bonus_max_bonus', (float) $terms['attendance_bonus_max_bonus']);
            } else {
                delete_post_meta($plan_id, '_vms_attendance_bonus_max_bonus');
            }

            if (($terms['attendance_bonus_mode'] ?? '') === 'step') {
                update_post_meta($plan_id, '_vms_attendance_bonus_step_size', (int) ($terms['attendance_bonus_step_size'] ?? 0));
                update_post_meta($plan_id, '_vms_attendance_bonus_step_bonus', (float) ($terms['attendance_bonus_step_bonus'] ?? 0));
                delete_post_meta($plan_id, '_vms_attendance_bonus_per_ticket_rate');
            } else {
                update_post_meta($plan_id, '_vms_attendance_bonus_per_ticket_rate', (float) ($terms['attendance_bonus_per_ticket_rate'] ?? 0));
                delete_post_meta($plan_id, '_vms_attendance_bonus_step_size');
                delete_post_meta($plan_id, '_vms_attendance_bonus_step_bonus');
            }
        } else {
            delete_post_meta($plan_id, '_vms_attendance_bonus_mode');
            delete_post_meta($plan_id, '_vms_attendance_bonus_start_count');
            delete_post_meta($plan_id, '_vms_attendance_bonus_step_size');
            delete_post_meta($plan_id, '_vms_attendance_bonus_step_bonus');
            delete_post_meta($plan_id, '_vms_attendance_bonus_per_ticket_rate');
            delete_post_meta($plan_id, '_vms_attendance_bonus_max_bonus');
        }

        return true;
    }
}

if (!function_exists('bvmgr_calculate_attendance_bonus_payout')) {
    /**
     * @return array<string,int|float|string>
     */
    function bvmgr_calculate_attendance_bonus_payout(array $terms, int $attendance_count): array
    {
        $base_pay = bvmgr_normalize_comp_nonnegative_float($terms['flat_fee_amount'] ?? null);
        if ($base_pay === null) {
            $base_pay = 0.0;
        }

        $attendance_count = max(0, $attendance_count);
        $mode = bvmgr_normalize_attendance_bonus_mode((string) ($terms['attendance_bonus_mode'] ?? ''));
        $start_count = bvmgr_normalize_comp_nonnegative_int($terms['attendance_bonus_start_count'] ?? null);
        $max_bonus = bvmgr_normalize_comp_nonnegative_float($terms['attendance_bonus_max_bonus'] ?? null);
        if ($start_count === null) {
            $start_count = 0;
        }

        $bonus = 0.0;
        $steps_reached = 0;

        if ($mode === 'step') {
            $step_size = bvmgr_normalize_comp_nonnegative_int($terms['attendance_bonus_step_size'] ?? null);
            $step_bonus = bvmgr_normalize_comp_nonnegative_float($terms['attendance_bonus_step_bonus'] ?? null);

            if ($step_size !== null && $step_size >= 1 && $step_bonus !== null) {
                $steps_reached = (int) floor(max(0, $attendance_count - $start_count) / $step_size);
                $bonus = (float) $steps_reached * (float) $step_bonus;
            }
        } elseif ($mode === 'continuous') {
            $per_ticket_rate = bvmgr_normalize_comp_nonnegative_float($terms['attendance_bonus_per_ticket_rate'] ?? null);
            if ($per_ticket_rate !== null) {
                $bonus_tickets = max(0, $attendance_count - $start_count);
                $bonus = (float) $bonus_tickets * (float) $per_ticket_rate;
            }
        }

        if ($max_bonus !== null) {
            $bonus = min((float) $max_bonus, (float) $bonus);
        }

        return array(
            'base_pay' => (float) $base_pay,
            'bonus' => (float) $bonus,
            'total_payout' => (float) $base_pay + (float) $bonus,
            'attendance_count' => $attendance_count,
            'mode' => $mode,
            'steps_reached' => $steps_reached,
            'max_bonus' => $max_bonus,
        );
    }
}
if (!function_exists('bvmgr_get_attendance_bonus_progress_snapshot')) {
    /**
     * Build a vendor-safe progress snapshot for attendance bonus compensation.
     *
     * Notes:
     * - Uses the canonical payout helper so admin and portal math stay aligned.
     * - Returns a meter only when there is a meaningful finite target.
     *
     * @return array<string,mixed>
     */
    function bvmgr_get_attendance_bonus_progress_snapshot(array $terms, int $attendance_count): array
    {
        $terms = function_exists('bvmgr_normalize_comp_terms') ? bvmgr_normalize_comp_terms($terms) : $terms;

        $structure = sanitize_key((string) ($terms['structure'] ?? ''));
        $mode = bvmgr_normalize_attendance_bonus_mode((string) ($terms['attendance_bonus_mode'] ?? ''));
        $attendance_count = max(0, $attendance_count);
        $payout = bvmgr_calculate_attendance_bonus_payout($terms, $attendance_count);

        $base_pay = (float) ($payout['base_pay'] ?? 0.0);
        $current_bonus = (float) ($payout['bonus'] ?? 0.0);
        $projected_total = (float) ($payout['total_payout'] ?? ($base_pay + $current_bonus));
        $start_count = bvmgr_normalize_comp_nonnegative_int($terms['attendance_bonus_start_count'] ?? null);
        $max_bonus = bvmgr_normalize_comp_nonnegative_float($terms['attendance_bonus_max_bonus'] ?? null);

        if ($start_count === null) {
            $start_count = 0;
        }

        $snapshot = array(
            'eligible' => ($structure === 'attendance_bonus' && $mode !== ''),
            'structure' => $structure,
            'mode' => $mode,
            'attendance_count' => $attendance_count,
            'base_pay' => $base_pay,
            'current_bonus' => $current_bonus,
            'projected_total' => $projected_total,
            'bonus_start_count' => $start_count,
            'max_bonus' => $max_bonus,
            'max_reached' => ($max_bonus !== null && $current_bonus >= $max_bonus),
            'meter_enabled' => false,
            'meter_percent' => 0.0,
            'meter_start_count' => 0,
            'meter_target_count' => 0,
            'tickets_to_next' => 0,
            'current_threshold_count' => 0,
            'next_threshold_count' => null,
            'current_bonus_target' => $current_bonus,
            'next_bonus_target' => null,
            'step_size' => null,
            'step_bonus' => null,
            'per_ticket_rate' => null,
            'steps_reached' => (int) ($payout['steps_reached'] ?? 0),
            'message' => '',
        );

        if (!$snapshot['eligible']) {
            return $snapshot;
        }

        if ($mode === 'step') {
            $step_size = bvmgr_normalize_comp_nonnegative_int($terms['attendance_bonus_step_size'] ?? null);
            $step_bonus = bvmgr_normalize_comp_nonnegative_float($terms['attendance_bonus_step_bonus'] ?? null);

            $snapshot['step_size'] = $step_size;
            $snapshot['step_bonus'] = $step_bonus;

            if ($step_size === null || $step_size < 1 || $step_bonus === null || $step_bonus <= 0) {
                $snapshot['message'] = __('Attendance bonus tiers are not fully configured yet.', 'backstage-venue-manager');
                return $snapshot;
            }

            $first_threshold_count = $start_count + $step_size;
            $steps_reached = max(0, (int) ($payout['steps_reached'] ?? 0));
            $max_steps_to_cap = null;
            if ($max_bonus !== null && $step_bonus > 0) {
                $max_steps_to_cap = (int) ceil($max_bonus / $step_bonus);
            }

            $max_reached = ($max_steps_to_cap !== null && $steps_reached >= $max_steps_to_cap);
            if ($max_bonus !== null && $current_bonus >= $max_bonus) {
                $max_reached = true;
            }
            $snapshot['max_reached'] = $max_reached;

            if ($max_reached) {
                $snapshot['meter_enabled'] = true;
                $snapshot['meter_percent'] = 1.0;
                $snapshot['current_threshold_count'] = max($first_threshold_count, $start_count + max(1, $steps_reached) * $step_size);
                $snapshot['meter_start_count'] = max(0, $snapshot['current_threshold_count'] - $step_size);
                $snapshot['meter_target_count'] = max($snapshot['current_threshold_count'], $snapshot['meter_start_count'] + $step_size);
                $snapshot['current_bonus_target'] = $current_bonus;
                $snapshot['next_bonus_target'] = $current_bonus;
                $snapshot['next_threshold_count'] = null;
                $snapshot['tickets_to_next'] = 0;
                return $snapshot;
            }

            $next_step = $steps_reached + 1;
            $next_threshold_count = $start_count + ($next_step * $step_size);
            $next_bonus_target = (float) ($next_step * $step_bonus);
            if ($max_bonus !== null) {
                $next_bonus_target = min($next_bonus_target, $max_bonus);
            }

            if ($attendance_count < $first_threshold_count) {
                $meter_start_count = 0;
                $current_threshold_count = 0;
            } else {
                $meter_start_count = $start_count + ($steps_reached * $step_size);
                $current_threshold_count = $meter_start_count;
            }

            $denom = max(1, $next_threshold_count - $meter_start_count);
            $meter_percent = ($attendance_count - $meter_start_count) / $denom;
            $meter_percent = max(0.0, min(1.0, (float) $meter_percent));

            $snapshot['meter_enabled'] = true;
            $snapshot['meter_percent'] = $meter_percent;
            $snapshot['meter_start_count'] = max(0, $meter_start_count);
            $snapshot['meter_target_count'] = $next_threshold_count;
            $snapshot['current_threshold_count'] = max(0, $current_threshold_count);
            $snapshot['next_threshold_count'] = $next_threshold_count;
            $snapshot['tickets_to_next'] = max(0, $next_threshold_count - $attendance_count);
            $snapshot['current_bonus_target'] = $current_bonus;
            $snapshot['next_bonus_target'] = $next_bonus_target;

            return $snapshot;
        }

        if ($mode === 'continuous') {
            $per_ticket_rate = bvmgr_normalize_comp_nonnegative_float($terms['attendance_bonus_per_ticket_rate'] ?? null);
            $snapshot['per_ticket_rate'] = $per_ticket_rate;

            if ($per_ticket_rate === null || $per_ticket_rate <= 0) {
                $snapshot['message'] = __('Attendance bonus accrual is not fully configured yet.', 'backstage-venue-manager');
                return $snapshot;
            }

            if ($attendance_count < $start_count && $start_count > 0) {
                $snapshot['meter_enabled'] = true;
                $snapshot['meter_percent'] = max(0.0, min(1.0, ((float) $attendance_count / (float) $start_count)));
                $snapshot['meter_start_count'] = 0;
                $snapshot['meter_target_count'] = $start_count;
                $snapshot['current_threshold_count'] = 0;
                $snapshot['next_threshold_count'] = $start_count;
                $snapshot['tickets_to_next'] = max(0, $start_count - $attendance_count);
                $snapshot['current_bonus_target'] = 0.0;
                $snapshot['next_bonus_target'] = 0.0;
                return $snapshot;
            }

            if ($max_bonus !== null && $max_bonus > 0) {
                $tickets_until_cap = (int) ceil($max_bonus / $per_ticket_rate);
                $max_threshold_count = $start_count + max(0, $tickets_until_cap);
                $snapshot['meter_enabled'] = true;
                $snapshot['meter_percent'] = max(0.0, min(1.0, ((float) $current_bonus / (float) $max_bonus)));
                $snapshot['meter_start_count'] = $start_count;
                $snapshot['meter_target_count'] = $max_threshold_count;
                $snapshot['current_threshold_count'] = $start_count;
                $snapshot['next_threshold_count'] = $max_threshold_count;
                $snapshot['tickets_to_next'] = max(0, $max_threshold_count - $attendance_count);
                $snapshot['current_bonus_target'] = $current_bonus;
                $snapshot['next_bonus_target'] = $max_bonus;
                $snapshot['max_reached'] = ($current_bonus >= $max_bonus);
                return $snapshot;
            }

            $snapshot['message'] = __('This bonus grows ticket by ticket and does not have a fixed cap.', 'backstage-venue-manager');
            return $snapshot;
        }

        return $snapshot;
    }
}

/**
 * Apply a comp package to an event plan AND write a snapshot (so packages can change later
 * without altering already-agreed plans unless you re-apply).
 */
function bvmgr_apply_comp_package_to_plan(int $plan_id, int $package_id): bool
{
    if ($package_id <= 0) return false;

    $pkg = get_post($package_id);
    if (!$pkg || $pkg->post_type !== 'vms_comp_package') return false;

    $terms = bvmgr_get_comp_package_terms($package_id);
    $structure = isset($terms['structure']) ? (string) $terms['structure'] : '';
    $flat = $terms['flat_fee_amount'] ?? null;
    $split = $terms['door_split_percent'] ?? null;
    $commission_pct  = get_post_meta($package_id, '_vms_commission_percent', true);        // numeric (ex: 15)
    $commission_mode = (string) get_post_meta($package_id, '_vms_commission_mode', true);  // artist_fee | gross (optional)

    // Reasonable defaults
    if ($structure === '') $structure = 'flat_fee';
    if ($commission_mode === '') $commission_mode = 'artist_fee';

    if (!bvmgr_event_plan_apply_comp_terms($plan_id, $terms)) {
        return false;
    }

    if ($commission_pct !== '' && $commission_pct !== null) {
        update_post_meta($plan_id, '_vms_commission_percent', (float) $commission_pct);
    }

    update_post_meta($plan_id, '_vms_commission_mode', sanitize_text_field($commission_mode));

    // Store selected package id on the plan
    update_post_meta($plan_id, '_vms_comp_package_id', $package_id);

    // Snapshot (this is the “source of truth” for what was agreed when applied)
    $deposit_terms = function_exists('bvmgr_get_event_plan_deposit_terms') ? (array) bvmgr_get_event_plan_deposit_terms((int) $plan_id) : array();
    $final_payment_terms = function_exists('bvmgr_get_event_plan_final_payment_terms') ? (array) bvmgr_get_event_plan_final_payment_terms((int) $plan_id) : array();

    $snapshot = array(
        'package_id'         => $package_id,
        'package_title'      => (string) get_the_title($package_id),
        'applied_at'         => current_time('mysql'),
        'structure'          => $structure,
        'flat_fee_amount'    => ($flat !== '' && $flat !== null) ? (float)$flat : null,
        'door_split_percent' => ($split !== '' && $split !== null) ? (float)$split : null,
        'attendance_bonus_mode' => $terms['attendance_bonus_mode'] ?? null,
        'attendance_bonus_start_count' => $terms['attendance_bonus_start_count'] ?? null,
        'attendance_bonus_step_size' => $terms['attendance_bonus_step_size'] ?? null,
        'attendance_bonus_step_bonus' => $terms['attendance_bonus_step_bonus'] ?? null,
        'attendance_bonus_per_ticket_rate' => $terms['attendance_bonus_per_ticket_rate'] ?? null,
        'attendance_bonus_max_bonus' => $terms['attendance_bonus_max_bonus'] ?? null,
        'commission_percent' => ($commission_pct !== '' && $commission_pct !== null) ? (float)$commission_pct : null,
        'commission_mode'    => $commission_mode,

        // NEW:
        'source'             => 'comp_package', // optional but nice
        'comp_hash'          => function_exists('bvmgr_comp_hash_for_plan') ? bvmgr_comp_hash_for_plan((int)$plan_id) : '',
    );

    if (!empty($deposit_terms)) {
        $snapshot = array_merge($snapshot, $deposit_terms);
    }
    if (!empty($final_payment_terms)) {
        $snapshot = array_merge($snapshot, $final_payment_terms);
    }

    update_post_meta($plan_id, '_vms_comp_snapshot', $snapshot);
    delete_post_meta($plan_id, '_vms_comp_needs_snapshot');

    return true;
}

function bvmgr_comp_hash_for_plan(int $plan_id): string
{
    return bvmgr_comp_hash_from_terms(bvmgr_get_event_plan_comp_terms($plan_id));
}

/**
 * Guaranteed payout helper for comp terms.
 * Returns the guaranteed amount (flat fee) for structures that include a flat fee.
 */
function bvmgr_comp_guaranteed_amount(array $terms): float
{
    $structure = isset($terms['structure']) ? (string) $terms['structure'] : 'flat_fee';

    $flat = null;
    if (array_key_exists('flat_fee_amount', $terms)) {
        $flat = $terms['flat_fee_amount'];
    } elseif (array_key_exists('flat', $terms)) {
        $flat = $terms['flat'];
    }

    $flat = ($flat === '' || $flat === null) ? 0.0 : (float) $flat;
    if ($flat < 0) $flat = 0.0;

    if ($structure === 'door_split') {
        return 0.0;
    }

    return (float) $flat;
}

/**
 * Vendor default compensation overrides keyed by venue ID.
 *
 * Stored on vendor post meta as:
 * _vms_vendor_default_comp_by_venue = [
 *   123 => [ 'structure' => 'flat_fee', 'flat_fee_amount' => 500, 'door_split_percent' => 80 ],
 * ]
 */
function bvmgr_get_vendor_default_comp_by_venue_map(int $vendor_id): array
{
    if ($vendor_id <= 0) return array();

    $k_by_venue = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('vendor', 'default_comp_by_venue') ?: '_vms_vendor_default_comp_by_venue') : '_vms_vendor_default_comp_by_venue';
    $saved = get_post_meta($vendor_id, $k_by_venue, true);
    if (!is_array($saved)) {
        return array();
    }

    $out = array();
    foreach ($saved as $venue_key => $row_raw) {
        $venue_id = absint($venue_key);
        if ($venue_id <= 0 || !is_array($row_raw)) {
            continue;
        }

        $row = bvmgr_normalize_comp_terms($row_raw);

        if (!empty($row)) {
            $out[$venue_id] = $row;
        }
    }

    return $out;
}

/**
 * Vendor default compensation overrides keyed by venue ID + day-of-week.
 *
 * Stored on vendor post meta as:
 * _vms_vendor_default_comp_by_venue_dow = [
 *   123 => [
 *     1 => [ 'structure' => 'flat_fee', 'flat_fee_amount' => 400, 'door_split_percent' => 75 ], // Monday
 *   ],
 * ]
 */
function bvmgr_get_vendor_default_comp_by_venue_dow_map(int $vendor_id): array
{
    if ($vendor_id <= 0) return array();

    $k = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('vendor', 'default_comp_by_venue_dow') ?: '_vms_vendor_default_comp_by_venue_dow') : '_vms_vendor_default_comp_by_venue_dow';
    $saved = get_post_meta($vendor_id, $k, true);
    if (!is_array($saved)) {
        return array();
    }

    $out = array();
    foreach ($saved as $venue_key => $dow_rows_raw) {
        $venue_id = absint($venue_key);
        if ($venue_id <= 0 || !is_array($dow_rows_raw)) {
            continue;
        }

        $venue_rows = array();
        foreach ($dow_rows_raw as $dow_key => $row_raw) {
            $dow = (int) $dow_key;
            if ($dow < 0 || $dow > 6 || !is_array($row_raw)) {
                continue;
            }

            $row = bvmgr_normalize_comp_terms($row_raw);

            if (!empty($row)) {
                $venue_rows[$dow] = $row;
            }
        }

        if (!empty($venue_rows)) {
            $out[$venue_id] = $venue_rows;
        }
    }

    return $out;
}

/**
 * Vendor default compensation terms.
 */
function bvmgr_get_vendor_default_comp_terms(int $vendor_id, int $venue_id = 0, string $event_date = ''): array
{
    if ($vendor_id <= 0) return array();
    $venue_id = absint($venue_id);
    $event_date = trim((string) $event_date);

    $k_structure = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('vendor', 'default_comp_structure') ?: '_vms_default_comp_structure') : '_vms_default_comp_structure';
    $k_flat      = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('vendor', 'default_flat_fee_amount') ?: '_vms_default_flat_fee_amount') : '_vms_default_flat_fee_amount';
    $k_split     = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('vendor', 'default_door_split_percent') ?: '_vms_default_door_split_percent') : '_vms_default_door_split_percent';
    $k_bonus_mode = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('vendor', 'default_attendance_bonus_mode') ?: '_vms_default_attendance_bonus_mode') : '_vms_default_attendance_bonus_mode';
    $k_bonus_start = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('vendor', 'default_attendance_bonus_start_count') ?: '_vms_default_attendance_bonus_start_count') : '_vms_default_attendance_bonus_start_count';
    $k_bonus_step_size = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('vendor', 'default_attendance_bonus_step_size') ?: '_vms_default_attendance_bonus_step_size') : '_vms_default_attendance_bonus_step_size';
    $k_bonus_step_bonus = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('vendor', 'default_attendance_bonus_step_bonus') ?: '_vms_default_attendance_bonus_step_bonus') : '_vms_default_attendance_bonus_step_bonus';
    $k_bonus_per_ticket = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('vendor', 'default_attendance_bonus_per_ticket_rate') ?: '_vms_default_attendance_bonus_per_ticket_rate') : '_vms_default_attendance_bonus_per_ticket_rate';
    $k_bonus_max = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('vendor', 'default_attendance_bonus_max_bonus') ?: '_vms_default_attendance_bonus_max_bonus') : '_vms_default_attendance_bonus_max_bonus';

    $out = array();

    $package_id = function_exists('bvmgr_get_vendor_default_comp_package_id')
        ? bvmgr_get_vendor_default_comp_package_id($vendor_id)
        : 0;
    if ($package_id > 0) {
        $package_terms = bvmgr_get_comp_package_terms($package_id);
        if (!empty($package_terms)) {
            $out = array_merge($out, $package_terms);
        }

        $package_pct = bvmgr_normalize_agent_fee_percent(get_post_meta($package_id, '_vms_commission_percent', true));
        $package_mode = bvmgr_normalize_agent_fee_mode(get_post_meta($package_id, '_vms_commission_mode', true));
        if ($package_pct !== null && $package_pct > 0) {
            $out['commission_percent'] = $package_pct;
            $out['commission_mode'] = ($package_mode !== '') ? $package_mode : 'artist_fee';
        }
    }

    $vendor_terms = bvmgr_normalize_comp_terms(array(
        'structure' => (string) get_post_meta($vendor_id, $k_structure, true),
        'flat_fee_amount' => get_post_meta($vendor_id, $k_flat, true),
        'door_split_percent' => get_post_meta($vendor_id, $k_split, true),
        'attendance_bonus_mode' => (string) get_post_meta($vendor_id, $k_bonus_mode, true),
        'attendance_bonus_start_count' => get_post_meta($vendor_id, $k_bonus_start, true),
        'attendance_bonus_step_size' => get_post_meta($vendor_id, $k_bonus_step_size, true),
        'attendance_bonus_step_bonus' => get_post_meta($vendor_id, $k_bonus_step_bonus, true),
        'attendance_bonus_per_ticket_rate' => get_post_meta($vendor_id, $k_bonus_per_ticket, true),
        'attendance_bonus_max_bonus' => get_post_meta($vendor_id, $k_bonus_max, true),
    ));

    $vendor_has_numeric_terms = (
        array_key_exists('flat_fee_amount', $vendor_terms)
        || array_key_exists('door_split_percent', $vendor_terms)
        || array_key_exists('attendance_bonus_start_count', $vendor_terms)
        || array_key_exists('attendance_bonus_step_size', $vendor_terms)
        || array_key_exists('attendance_bonus_step_bonus', $vendor_terms)
        || array_key_exists('attendance_bonus_per_ticket_rate', $vendor_terms)
        || array_key_exists('attendance_bonus_max_bonus', $vendor_terms)
    );
    if (!$vendor_has_numeric_terms && count($vendor_terms) === 1 && (($vendor_terms['structure'] ?? '') === 'flat_fee')) {
        $vendor_terms = array();
    }

    if (!empty($vendor_terms)) {
        $out = array_merge($out, $vendor_terms);
    }

    if (function_exists('bvmgr_get_vendor_default_agent_fee_terms')) {
        $out = array_merge($out, bvmgr_get_vendor_default_agent_fee_terms($vendor_id));
    }

    if (empty($out)) {
        $out = array(
            'structure' => 'flat_fee',
        );
    }

    if ($venue_id > 0) {
        $by_venue = bvmgr_get_vendor_default_comp_by_venue_map($vendor_id);
        if (isset($by_venue[$venue_id]) && is_array($by_venue[$venue_id])) {
            $out = array_merge($out, $by_venue[$venue_id]);
        }
    }

    if ($venue_id > 0 && preg_match('/^\d{4}-\d{2}-\d{2}$/', $event_date)) {
        $tz = function_exists('bvmgr_get_timezone') ? bvmgr_get_timezone() : wp_timezone();
        if (!$tz instanceof DateTimeZone) {
            $tz = wp_timezone();
        }

        try {
            $dt = new DateTimeImmutable($event_date, $tz);
        } catch (Exception $e) {
            $dt = null;
        }

        if ($dt instanceof DateTimeImmutable) {
            $dow = (int) $dt->format('w'); // 0..6 (Sun..Sat)
            $by_venue_dow = bvmgr_get_vendor_default_comp_by_venue_dow_map($vendor_id);
            if (isset($by_venue_dow[$venue_id]) && is_array($by_venue_dow[$venue_id])) {
                $venue_dow_rows = $by_venue_dow[$venue_id];
                if (isset($venue_dow_rows[$dow]) && is_array($venue_dow_rows[$dow])) {
                    $out = array_merge($out, $venue_dow_rows[$dow]);
                }
            }
        }
    }

    return bvmgr_normalize_comp_terms($out);
}

/**
 * Vendor supporting-act default compensation terms.
 *
 * Intentionally lightweight for lineup supporting slots so operators can
 * automate common opener/support deals without mirroring the full primary
 * compensation engine.
 */
function bvmgr_get_vendor_supporting_default_comp_terms(int $vendor_id): array
{
    if ($vendor_id <= 0) return array();

    $k_support_flat = function_exists('bvmgr_meta_key')
        ? (bvmgr_meta_key('vendor', 'default_supporting_flat_fee_amount') ?: '_vms_default_supporting_flat_fee_amount')
        : '_vms_default_supporting_flat_fee_amount';

    $raw_flat = get_post_meta($vendor_id, $k_support_flat, true);
    if ($raw_flat === '' || $raw_flat === null) {
        return array();
    }

    $flat = bvmgr_normalize_comp_nonnegative_float($raw_flat);
    if ($flat === null) {
        return array();
    }

    return array(
        'structure' => 'flat_fee',
        'flat_fee_amount' => (float) $flat,
    );
}

/**
 * Comp package terms.
 */
function bvmgr_get_comp_package_terms(int $package_id): array
{
    if ($package_id <= 0) return array();
    $pkg = get_post($package_id);
    if (!$pkg || $pkg->post_type !== "vms_comp_package") return array();

    /*
     * Packages use their own meta schema (vendor-comp-packages.php).
     * Map package terms into Event Plan Draft Pay term keys.
     *
     * Current package schema:
     * - _vms_comp_type: flat | flat_plus_split | door_split | attendance_bonus
     * - _vms_flat_fee
     * - _vms_split_percent_artist
     *
     * Legacy fallback schema (if present):
     * - _vms_comp_structure
     * - _vms_flat_fee_amount
     * - _vms_door_split_percent
     */

    $bonus_terms = array(
        'attendance_bonus_mode' => (string) get_post_meta($package_id, '_vms_attendance_bonus_mode', true),
        'attendance_bonus_start_count' => get_post_meta($package_id, '_vms_attendance_bonus_start_count', true),
        'attendance_bonus_step_size' => get_post_meta($package_id, '_vms_attendance_bonus_step_size', true),
        'attendance_bonus_step_bonus' => get_post_meta($package_id, '_vms_attendance_bonus_step_bonus', true),
        'attendance_bonus_per_ticket_rate' => get_post_meta($package_id, '_vms_attendance_bonus_per_ticket_rate', true),
        'attendance_bonus_max_bonus' => get_post_meta($package_id, '_vms_attendance_bonus_max_bonus', true),
    );

    $type = (string) get_post_meta($package_id, "_vms_comp_type", true);

    // Legacy fallback
    if ($type === "") {
        $legacy_structure = (string) get_post_meta($package_id, "_vms_comp_structure", true);
        if ($legacy_structure !== "") {
            return bvmgr_normalize_comp_terms(array_merge(array(
                'structure' => $legacy_structure,
                'flat_fee_amount' => get_post_meta($package_id, "_vms_flat_fee_amount", true),
                'door_split_percent' => get_post_meta($package_id, "_vms_door_split_percent", true),
            ), $bonus_terms));
        }
    }

    // Current schema
    $structure = "flat_fee";
    if ($type === "door_split") {
        $structure = "door_split";
    } elseif ($type === "flat_plus_split") {
        $structure = "flat_fee_door_split";
    } elseif ($type === 'attendance_bonus') {
        $structure = 'attendance_bonus';
    }

    $flat  = get_post_meta($package_id, "_vms_flat_fee", true);
    if ($flat === "" || $flat === null) {
        // Some older builds stored flat fee under the legacy key even when _vms_comp_type exists.
        $flat = get_post_meta($package_id, "_vms_flat_fee_amount", true);
    }

    $split = get_post_meta($package_id, "_vms_split_percent_artist", true);
    if ($split === "" || $split === null) {
        // Fallbacks for older/alternate key names.
        $split = get_post_meta($package_id, "_vms_split_percent", true);
    }
    if ($split === "" || $split === null) {
        $split = get_post_meta($package_id, "_vms_door_split_percent", true);
    }

    return bvmgr_normalize_comp_terms(array_merge(array(
        "structure" => $structure,
        "flat_fee_amount" => ($flat !== "" && $flat !== null) ? (float) $flat : null,
        "door_split_percent" => ($split !== "" && $split !== null) ? (float) $split : null,
    ), $bonus_terms));
}


/**
 * Vendor default Comp Package template ID.
 */
function bvmgr_get_vendor_default_comp_package_id(int $vendor_id): int
{
    if ($vendor_id <= 0) {
        return 0;
    }

    $key = function_exists('bvmgr_meta_key')
        ? (bvmgr_meta_key('vendor', 'default_comp_package_id') ?: '_vms_default_comp_package_id')
        : '_vms_default_comp_package_id';

    $package_id = absint(get_post_meta($vendor_id, $key, true));
    if ($package_id <= 0) {
        return 0;
    }

    $pkg = get_post($package_id);
    if (!$pkg || $pkg->post_type !== 'vms_comp_package') {
        return 0;
    }

    return $package_id;
}

/**
 * Normalize a draft/default compensation terms array into canonical keys.
 *
 * Output keys:
 * - structure (flat_fee|door_split|flat_fee_door_split|attendance_bonus)
 * - flat_fee_amount (float, optional)
 * - door_split_percent (float, optional)
 * - attendance_bonus_mode (step|continuous, attendance_bonus only)
 * - attendance_bonus_start_count (int, attendance_bonus only)
 * - attendance_bonus_step_size (int, step mode only)
 * - attendance_bonus_step_bonus (float, step mode only)
 * - attendance_bonus_per_ticket_rate (float, continuous mode only)
 * - attendance_bonus_max_bonus (float, optional cap across both modes)
 */
function bvmgr_normalize_comp_terms(array $terms): array
{
    $out = array();

    $structure = isset($terms['structure']) ? sanitize_key((string) $terms['structure']) : '';
    if (bvmgr_comp_structure_is_supported($structure)) {
        $out['structure'] = $structure;
    }

    if (array_key_exists('flat_fee_amount', $terms)) {
        $flat = bvmgr_normalize_comp_nonnegative_float($terms['flat_fee_amount']);
        if ($flat !== null) {
            $out['flat_fee_amount'] = $flat;
        }
    }

    if (array_key_exists('door_split_percent', $terms)) {
        $split = bvmgr_normalize_comp_nonnegative_float($terms['door_split_percent']);
        if ($split !== null) {
            if ($split > 100.0) {
                $split = 100.0;
            }
            $out['door_split_percent'] = $split;
        }
    }

    if (array_key_exists('commission_percent', $terms)) {
        $commission_percent = bvmgr_normalize_agent_fee_percent($terms['commission_percent']);
        if ($commission_percent !== null) {
            $out['commission_percent'] = $commission_percent;
            $out['commission_mode'] = bvmgr_normalize_agent_fee_mode($terms['commission_mode'] ?? 'artist_fee');
        }
    }

    if (($out['structure'] ?? '') === 'attendance_bonus') {
        $mode = bvmgr_normalize_attendance_bonus_mode((string) ($terms['attendance_bonus_mode'] ?? ''));
        $start_count = bvmgr_normalize_comp_nonnegative_int($terms['attendance_bonus_start_count'] ?? null);
        $step_size = bvmgr_normalize_comp_nonnegative_int($terms['attendance_bonus_step_size'] ?? null);
        $step_bonus = bvmgr_normalize_comp_nonnegative_float($terms['attendance_bonus_step_bonus'] ?? null);
        $per_ticket_rate = bvmgr_normalize_comp_nonnegative_float($terms['attendance_bonus_per_ticket_rate'] ?? null);
        $max_bonus = bvmgr_normalize_comp_nonnegative_float($terms['attendance_bonus_max_bonus'] ?? null);

        if ($mode === 'step' && $start_count !== null && $step_size !== null && $step_size >= 1 && $step_bonus !== null) {
            $out['attendance_bonus_mode'] = 'step';
            $out['attendance_bonus_start_count'] = $start_count;
            $out['attendance_bonus_step_size'] = $step_size;
            $out['attendance_bonus_step_bonus'] = $step_bonus;
            if ($max_bonus !== null) {
                $out['attendance_bonus_max_bonus'] = $max_bonus;
            }
        } elseif ($mode === 'continuous' && $start_count !== null && $per_ticket_rate !== null) {
            $out['attendance_bonus_mode'] = 'continuous';
            $out['attendance_bonus_start_count'] = $start_count;
            $out['attendance_bonus_per_ticket_rate'] = $per_ticket_rate;
            if ($max_bonus !== null) {
                $out['attendance_bonus_max_bonus'] = $max_bonus;
            }
        } else {
            unset($out['structure']);
            unset($out['flat_fee_amount']);
        }
    }

    $has_deposit_keys = false;
    foreach (array('deposit_amount', 'deposit_status', 'deposit_treatment', 'deposit_due_date', 'deposit_paid_date', 'deposit_notes') as $deposit_key) {
        if (array_key_exists($deposit_key, $terms)) {
            $has_deposit_keys = true;
            break;
        }
    }

    if ($has_deposit_keys) {
        $deposit_amount = bvmgr_normalize_comp_nonnegative_float($terms['deposit_amount'] ?? null);
        $deposit_status = bvmgr_normalize_comp_deposit_status($terms['deposit_status'] ?? '');
        $deposit_treatment = bvmgr_normalize_comp_deposit_treatment($terms['deposit_treatment'] ?? '');
        $deposit_due_date = bvmgr_normalize_comp_deposit_date($terms['deposit_due_date'] ?? '');
        $deposit_paid_date = bvmgr_normalize_comp_deposit_date($terms['deposit_paid_date'] ?? '');
        $deposit_notes = isset($terms['deposit_notes']) ? sanitize_textarea_field((string) $terms['deposit_notes']) : '';

        if ($deposit_amount !== null && $deposit_amount > 0) {
            $out['deposit_amount'] = $deposit_amount;
            if ($deposit_status === 'not_required') {
                $deposit_status = 'unpaid';
            }
        }
        if ($deposit_status !== 'not_required' || $deposit_amount !== null || $deposit_due_date !== '' || $deposit_paid_date !== '' || $deposit_notes !== '') {
            $out['deposit_status'] = $deposit_status;
            $out['deposit_treatment'] = $deposit_treatment;
            $out['deposit_due_date'] = $deposit_due_date;
            $out['deposit_paid_date'] = $deposit_paid_date;
            $out['deposit_notes'] = $deposit_notes;
        }
    }

    $has_final_payment_keys = false;
    foreach (array('final_payment_timing', 'final_payment_days_after', 'final_payment_date', 'final_payment_custom_text', 'final_payment_method', 'final_payment_method_other') as $final_payment_key) {
        if (array_key_exists($final_payment_key, $terms)) {
            $has_final_payment_keys = true;
            break;
        }
    }

    if ($has_final_payment_keys) {
        $timing = bvmgr_normalize_comp_final_payment_timing($terms['final_payment_timing'] ?? '');
        $days_after = bvmgr_normalize_comp_final_payment_days_after($terms['final_payment_days_after'] ?? '');
        $fixed_date = bvmgr_normalize_comp_final_payment_date($terms['final_payment_date'] ?? '');
        $custom_text = isset($terms['final_payment_custom_text']) ? sanitize_text_field((string) $terms['final_payment_custom_text']) : '';
        $method = bvmgr_normalize_comp_final_payment_method($terms['final_payment_method'] ?? '');
        $method_other = isset($terms['final_payment_method_other']) ? sanitize_text_field((string) $terms['final_payment_method_other']) : '';

        if ($timing !== 'days_after') {
            $days_after = '';
        }
        if ($timing !== 'fixed_date') {
            $fixed_date = '';
        }
        if ($timing !== 'custom') {
            $custom_text = '';
        }
        if ($method !== 'other') {
            $method_other = '';
        }

        if ($timing !== 'not_set' || $method !== 'not_set' || $days_after !== '' || $fixed_date !== '' || $custom_text !== '' || $method_other !== '') {
            $out['final_payment_timing'] = $timing;
            $out['final_payment_days_after'] = $days_after;
            $out['final_payment_date'] = $fixed_date;
            $out['final_payment_custom_text'] = $custom_text;
            $out['final_payment_method'] = $method;
            $out['final_payment_method_other'] = $method_other;
        }
    }

    return $out;
}

/**
 * Resolve effective Event Plan default pay terms.
 *
 * Precedence is deterministic:
 * 1) Holiday vendor override terms
 * 2) Venue day-of-week defaults
 *
 * @param array<string,mixed> $holiday_terms
 * @param array<string,mixed> $venue_terms
 * @return array<string,mixed>
 */
function bvmgr_resolve_event_plan_comp_default(array $holiday_terms, array $venue_terms, string $holiday_name = ''): array
{
    $out = array(
        'source' => '',
        'label' => '',
        'holiday_name' => '',
        'structure' => '',
        'flat_fee_amount' => null,
        'door_split_percent' => null,
        'attendance_bonus_mode' => null,
        'attendance_bonus_start_count' => null,
        'attendance_bonus_step_size' => null,
        'attendance_bonus_step_bonus' => null,
        'attendance_bonus_per_ticket_rate' => null,
        'attendance_bonus_max_bonus' => null,
        'commission_percent' => null,
        'commission_mode' => null,
        'has_default' => false,
    );

    $holiday_terms = bvmgr_normalize_comp_terms($holiday_terms);
    $venue_terms = bvmgr_normalize_comp_terms($venue_terms);

    $holiday_has_structure = isset($holiday_terms['structure']) && $holiday_terms['structure'] !== '';
    $venue_has_structure = isset($venue_terms['structure']) && $venue_terms['structure'] !== '';

    if ($holiday_has_structure) {
        $safe_holiday_name = trim($holiday_name);
        if ($safe_holiday_name === '') {
            $safe_holiday_name = 'Holiday';
        }

        $out['source'] = 'holiday';
        $out['label'] = 'Holiday: ' . $safe_holiday_name;
        $out['holiday_name'] = $safe_holiday_name;
        $out['structure'] = (string) $holiday_terms['structure'];
        $out['flat_fee_amount'] = array_key_exists('flat_fee_amount', $holiday_terms) ? (float) $holiday_terms['flat_fee_amount'] : null;
        $out['door_split_percent'] = array_key_exists('door_split_percent', $holiday_terms) ? (float) $holiday_terms['door_split_percent'] : null;
        $out['attendance_bonus_mode'] = $holiday_terms['attendance_bonus_mode'] ?? null;
        $out['attendance_bonus_start_count'] = $holiday_terms['attendance_bonus_start_count'] ?? null;
        $out['attendance_bonus_step_size'] = $holiday_terms['attendance_bonus_step_size'] ?? null;
        $out['attendance_bonus_step_bonus'] = $holiday_terms['attendance_bonus_step_bonus'] ?? null;
        $out['attendance_bonus_per_ticket_rate'] = $holiday_terms['attendance_bonus_per_ticket_rate'] ?? null;
        $out['attendance_bonus_max_bonus'] = $holiday_terms['attendance_bonus_max_bonus'] ?? null;
        $out['commission_percent'] = $holiday_terms['commission_percent'] ?? null;
        $out['commission_mode'] = $holiday_terms['commission_mode'] ?? null;
        $out['has_default'] = true;
        return $out;
    }

    if ($venue_has_structure) {
        $out['source'] = 'venue';
        $out['label'] = 'Venue Defaults';
        $out['structure'] = (string) $venue_terms['structure'];
        $out['flat_fee_amount'] = array_key_exists('flat_fee_amount', $venue_terms) ? (float) $venue_terms['flat_fee_amount'] : null;
        $out['door_split_percent'] = array_key_exists('door_split_percent', $venue_terms) ? (float) $venue_terms['door_split_percent'] : null;
        $out['attendance_bonus_mode'] = $venue_terms['attendance_bonus_mode'] ?? null;
        $out['attendance_bonus_start_count'] = $venue_terms['attendance_bonus_start_count'] ?? null;
        $out['attendance_bonus_step_size'] = $venue_terms['attendance_bonus_step_size'] ?? null;
        $out['attendance_bonus_step_bonus'] = $venue_terms['attendance_bonus_step_bonus'] ?? null;
        $out['attendance_bonus_per_ticket_rate'] = $venue_terms['attendance_bonus_per_ticket_rate'] ?? null;
        $out['attendance_bonus_max_bonus'] = $venue_terms['attendance_bonus_max_bonus'] ?? null;
        $out['commission_percent'] = $venue_terms['commission_percent'] ?? null;
        $out['commission_mode'] = $venue_terms['commission_mode'] ?? null;
        $out['has_default'] = true;
        return $out;
    }

    return $out;
}

/**
 * Compute effective default pay terms for an Event Plan date context.
 *
 * Returns:
 * [
 *   'source' => ''|'holiday'|'venue',
 *   'label' => '',
 *   'holiday_name' => '',
 *   'structure' => '',
 *   'flat_fee_amount' => float|null,
 *   'door_split_percent' => float|null,
 *   'attendance_bonus_mode' => string|null,
 *   'attendance_bonus_start_count' => int|null,
 *   'attendance_bonus_step_size' => int|null,
 *   'attendance_bonus_step_bonus' => float|null,
 *   'attendance_bonus_per_ticket_rate' => float|null,
 *   'attendance_bonus_max_bonus' => float|null,
 *   'has_default' => bool,
 * ]
 */
function bvmgr_get_event_plan_effective_comp_default(int $venue_id, string $event_date): array
{
    $venue_id = (int) $venue_id;
    $event_date = trim((string) $event_date);

    if ($venue_id <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $event_date)) {
        return array(
            'source' => '',
            'label' => '',
            'holiday_name' => '',
            'structure' => '',
            'flat_fee_amount' => null,
            'door_split_percent' => null,
            'attendance_bonus_mode' => null,
            'attendance_bonus_start_count' => null,
            'attendance_bonus_step_size' => null,
            'attendance_bonus_step_bonus' => null,
            'attendance_bonus_per_ticket_rate' => null,
            'attendance_bonus_max_bonus' => null,
            'commission_percent' => null,
            'commission_mode' => null,
            'has_default' => false,
        );
    }

    $holiday_name = '';
    $holiday = function_exists('bvmgr_get_venue_holiday_for_date') ? bvmgr_get_venue_holiday_for_date($venue_id, $event_date) : null;
    if (is_array($holiday)) {
        $holiday_name = bvmgr_holiday_name_for_badge($holiday);
    }

    $holiday_terms = function_exists('bvmgr_get_venue_holiday_vendor_pay_defaults')
        ? (array) bvmgr_get_venue_holiday_vendor_pay_defaults($venue_id, $event_date)
        : array();

    $venue_terms = array();
    if (function_exists('bvmgr_get_venue_default_comp_for_date')) {
        $row = bvmgr_get_venue_default_comp_for_date($venue_id, $event_date);
        if (is_array($row)) {
            $venue_terms = $row;
        }
    }

    return bvmgr_resolve_event_plan_comp_default($holiday_terms, $venue_terms, $holiday_name);
}

/**
 * Build all available compensation options for an Event Plan context.
 *
 * Returns:
 * [
 *   'defaults' => [ 'venue' => ..., 'vendor' => ..., 'holiday' => ... ],
 *   'packages' => [ ... ],
 *   'max_guarantee' => 500.00
 * ]
 */
function bvmgr_get_event_plan_comp_options(int $venue_id, string $event_date, int $vendor_id = 0): array
{
    $opts = array(
        'defaults' => array(),
        'packages' => array(),
        'max_guarantee' => 0.0,
    );

    $venue_id = (int) $venue_id;
    $vendor_id = (int) $vendor_id;
    $event_date = trim((string) $event_date);

    $has_date = (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $event_date);

    // Venue defaults
    $venue_terms = array();
    $venue_row = array();
    if ($venue_id > 0 && $has_date && function_exists('bvmgr_get_venue_default_comp_for_date')) {
        $row = bvmgr_get_venue_default_comp_for_date($venue_id, $event_date);
        if (is_array($row) && !empty($row['structure'])) {
            $venue_row = $row;
            $venue_terms = bvmgr_normalize_comp_terms($row);
            $venue_pct = bvmgr_normalize_agent_fee_percent($row['commission_percent'] ?? null);
            $venue_mode = bvmgr_normalize_agent_fee_mode($row['commission_mode'] ?? '');
            if ($venue_pct !== null && $venue_pct > 0) {
                $venue_terms['commission_percent'] = $venue_pct;
                $venue_terms['commission_mode'] = ($venue_mode !== '') ? $venue_mode : 'artist_fee';
            }
        }
    }

    // Holiday vendor overrides
    $holiday = null;
    $holiday_name = '';
    $holiday_terms = array();
    $holiday_row = array();

    if ($venue_id > 0 && $has_date && function_exists('bvmgr_get_venue_holiday_for_date')) {
        $holiday = bvmgr_get_venue_holiday_for_date($venue_id, $event_date);
        if (is_array($holiday)) {
            $holiday_name = trim((string)($holiday['name'] ?? ''));
            if ($holiday_name === '') $holiday_name = 'Holiday';
        }
    }

    if ($venue_id > 0 && $has_date && function_exists('bvmgr_get_venue_holiday_vendor_pay_defaults')) {
        $holiday_row = (array) bvmgr_get_venue_holiday_vendor_pay_defaults($venue_id, $event_date);
        $holiday_terms = bvmgr_normalize_comp_terms($holiday_row);
        $holiday_pct = bvmgr_normalize_agent_fee_percent($holiday_row['commission_percent'] ?? null);
        $holiday_mode = bvmgr_normalize_agent_fee_mode($holiday_row['commission_mode'] ?? '');
        if ($holiday_pct !== null && $holiday_pct > 0) {
            $holiday_terms['commission_percent'] = $holiday_pct;
            $holiday_terms['commission_mode'] = ($holiday_mode !== '') ? $holiday_mode : 'artist_fee';
        }
    }

    // Vendor defaults (venue/day-scoped when available).
    $vendor_terms = ($vendor_id > 0) ? bvmgr_normalize_comp_terms(bvmgr_get_vendor_default_comp_terms($vendor_id, $venue_id, $event_date)) : array();
    if ($vendor_id > 0) {
        $vendor_agent_fee = bvmgr_get_vendor_default_agent_fee_terms($vendor_id);
        if (!empty($vendor_agent_fee)) {
            $vendor_terms = array_merge($vendor_terms, $vendor_agent_fee);
        }
    }
    $vendor_has_venue_override = false;
    $vendor_has_dow_override = false;
    if ($vendor_id > 0 && $venue_id > 0 && function_exists('bvmgr_get_vendor_default_comp_by_venue_map')) {
        $vendor_by_venue = bvmgr_get_vendor_default_comp_by_venue_map($vendor_id);
        $vendor_has_venue_override = isset($vendor_by_venue[$venue_id]) && is_array($vendor_by_venue[$venue_id]) && !empty($vendor_by_venue[$venue_id]);
    }
    if ($vendor_id > 0 && $venue_id > 0 && $has_date && function_exists('bvmgr_get_vendor_default_comp_by_venue_dow_map')) {
        $vendor_by_venue_dow = bvmgr_get_vendor_default_comp_by_venue_dow_map($vendor_id);
        if (isset($vendor_by_venue_dow[$venue_id]) && is_array($vendor_by_venue_dow[$venue_id])) {
            $tz = function_exists('bvmgr_get_timezone') ? bvmgr_get_timezone() : wp_timezone();
            if (!$tz instanceof DateTimeZone) $tz = wp_timezone();
            try {
                $dt = new DateTimeImmutable($event_date, $tz);
            } catch (Exception $e) {
                $dt = null;
            }
            if ($dt instanceof DateTimeImmutable) {
                $dow = (int) $dt->format('w');
                $vendor_has_dow_override = isset($vendor_by_venue_dow[$venue_id][$dow]) && is_array($vendor_by_venue_dow[$venue_id][$dow]) && !empty($vendor_by_venue_dow[$venue_id][$dow]);
            }
        }
    }

    $opts['defaults']['venue'] = array(
        'key' => 'venue',
        'title' => __('Venue Defaults', 'backstage-venue-manager'),
        'subtitle' => ($venue_id > 0 && $has_date)
            ? (empty($venue_terms) ? __('Not set for this day', 'backstage-venue-manager') : __('For this day', 'backstage-venue-manager'))
            : __('Select Venue + Date', 'backstage-venue-manager'),
        'enabled' => (!empty($venue_terms)),
        'terms' => $venue_terms,
        'guarantee' => (!empty($venue_terms)) ? bvmgr_comp_guaranteed_amount($venue_terms) : 0.0,
    );

    $opts['defaults']['vendor'] = array(
        'key' => 'vendor',
        'title' => __('Vendor Defaults', 'backstage-venue-manager'),
        'subtitle' => ($vendor_id > 0)
            ? (empty($vendor_terms)
                ? __('Not set on vendor', 'backstage-venue-manager')
                : ($vendor_has_dow_override
                    ? __('Venue + day defaults', 'backstage-venue-manager')
                    : ($vendor_has_venue_override ? __('Venue-specific defaults', 'backstage-venue-manager') : __('Global vendor defaults', 'backstage-venue-manager'))))
            : __('Select Music Vendor', 'backstage-venue-manager'),
        'enabled' => (!empty($vendor_terms) && $vendor_id > 0),
        'terms' => $vendor_terms,
        'guarantee' => (!empty($vendor_terms)) ? bvmgr_comp_guaranteed_amount($vendor_terms) : 0.0,
    );

    // Holiday tile is always shown.
    $holiday_enabled = (!empty($holiday_terms));
    $holiday_sub = __('No holiday', 'backstage-venue-manager');
    if ($venue_id <= 0 || !$has_date) {
        $holiday_sub = __('Select Venue + Date', 'backstage-venue-manager');
    } elseif ($holiday) {
        if ($holiday_enabled) {
            $holiday_sub = $holiday_name;
        } else {
            $holiday_sub = $holiday_name . ' (no vendor pay override)';
        }
    }

    $opts['defaults']['holiday'] = array(
        'key' => 'holiday',
        'title' => __('Holiday Defaults', 'backstage-venue-manager'),
        'subtitle' => $holiday_sub,
        'enabled' => $holiday_enabled,
        'terms' => $holiday_enabled ? $holiday_terms : array(),
        'guarantee' => $holiday_enabled ? bvmgr_comp_guaranteed_amount($holiday_terms) : 0.0,
        'holiday_name' => $holiday ? $holiday_name : '',
    );

    // Packages
    $packages = array();
    if ($venue_id > 0 && function_exists('bvmgr_get_comp_packages_for_venue')) {
        $packages = (array) bvmgr_get_comp_packages_for_venue($venue_id, true);
    }

    foreach ($packages as $pkg) {
        if (!is_object($pkg) || empty($pkg->ID)) continue;
        $terms = bvmgr_get_comp_package_terms((int) $pkg->ID);
        $pkg_pct = bvmgr_normalize_agent_fee_percent(get_post_meta((int) $pkg->ID, '_vms_commission_percent', true));
        $pkg_mode = bvmgr_normalize_agent_fee_mode(get_post_meta((int) $pkg->ID, '_vms_commission_mode', true));
        if ($pkg_pct !== null && $pkg_pct > 0) {
            $terms['commission_percent'] = $pkg_pct;
            $terms['commission_mode'] = ($pkg_mode !== '') ? $pkg_mode : 'artist_fee';
        }
        $opts['packages'][] = array(
            'key' => 'package',
            'id' => (int) $pkg->ID,
            'title' => (string) $pkg->post_title,
            'subtitle' => (($terms['structure'] ?? '') === 'attendance_bonus')
                ? __('Guaranteed payout + variable attendance bonus', 'backstage-venue-manager')
                : __('Package', 'backstage-venue-manager'),
            'enabled' => !empty($terms),
            'terms' => $terms,
            'guarantee' => !empty($terms) ? bvmgr_comp_guaranteed_amount($terms) : 0.0,
        );
    }

    // Max guaranteed across enabled options
    $max = 0.0;
    foreach ($opts['defaults'] as $d) {
        if (!empty($d['enabled'])) {
            $max = max($max, (float) ($d['guarantee'] ?? 0));
        }
    }
    foreach ($opts['packages'] as $p) {
        if (!empty($p['enabled'])) {
            $max = max($max, (float) ($p['guarantee'] ?? 0));
        }
    }
    $opts['max_guarantee'] = (float) $max;

    return $opts;
}

function bvmgr_render_collapsible_panel(string $title, callable $render_cb, array $args = []): void
{
    $open    = !empty($args['open']);
    $accent  = isset($args['accent']) ? (string)$args['accent'] : '#4f46e5';
    $desc    = isset($args['desc']) ? (string)$args['desc'] : '';
    $classes = isset($args['class']) ? (string)$args['class'] : '';

    echo '<details class="vms-panel vms-panel-accent ' . esc_attr($classes) . '" style="--vms-accent:' . esc_attr($accent) . ';"' . ($open ? ' open' : '') . '>';
    echo '<summary>' . esc_html($title);

    if ($desc !== '') {
        echo '<span style="font-weight:400;opacity:.75;margin-left:8px;">' . esc_html($desc) . '</span>';
    }

    echo '</summary>';
    echo '<div class="vms-panel-body">';

    $render_cb();

    echo '</div></details>';
}

/**
 * Check whether a vendor has completed required tax profile fields.
 * NOTE: Does NOT include SSN/EIN on purpose.
 */
function bvmgr_is_vendor_tax_profile_complete(int $vendor_id): bool
{
    /*
     * Compatibility wrapper.
     * Canonical truth is vms_vendor_tax_profile_missing_items() (and its helper
     * vms_vendor_tax_profile_is_complete()).
     *
     * This function name is kept because other parts of the system call it.
     * NOTE: No SSN/EIN stored here.
     */
    if (function_exists('bvmgr_vendor_tax_profile_is_complete')) {
        return bvmgr_vendor_tax_profile_is_complete((int) $vendor_id);
    }

    if (function_exists('bvmgr_vendor_tax_profile_missing_items')) {
        return count((array) bvmgr_vendor_tax_profile_missing_items((int) $vendor_id)) === 0;
    }

    // Fallback: legacy required keys (older builds)
    $required_meta = array(
        '_vms_w9_name',
        '_vms_w9_address1',
        '_vms_w9_city',
        '_vms_w9_state',
        '_vms_w9_zip',
        '_vms_w9_email',
    );

    foreach ($required_meta as $key) {
        $val = trim((string) get_post_meta($vendor_id, $key, true));
        if ($val === '') return false;
    }

    return true;
}

/**
 * Return missing tax-profile requirements for a vendor.
 * Empty array = complete.
 *
 * Uses your current portal keys (vendor-tax-profile.php):
 *  - _vms_payee_legal_name
 *  - _vms_entity_type
 *  - _vms_addr1/_vms_city/_vms_state/_vms_zip
 *  - _vms_w9_upload_id (uploaded W-9 file)
 */
function bvmgr_vendor_tax_profile_missing_items(int $vendor_id): array
{
    // NOTE:
    // This function is used by admin-only readiness checks (event plan editor).
    // It MUST stay aligned with the admin vendor list checks.

    $missing = array();
    // Staff employee: use Employee Packet requirements (W-4 + I-9). Contractors use W-9 tax profile.
    if (get_post_type($vendor_id) === 'vms_staff') {
        $k_worker = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('staff', 'worker_type') : '_vms_staff_worker_type';
        if ($k_worker === '') $k_worker = '_vms_staff_worker_type';
        $wt = sanitize_key((string) get_post_meta($vendor_id, $k_worker, true));
        if ($wt === 'employee') {
            $emp_missing = array();
            $w4 = (int) get_post_meta($vendor_id, '_vms_employee_w4_received', true) ? 1 : 0;
            $i9 = (int) get_post_meta($vendor_id, '_vms_employee_i9_verified', true) ? 1 : 0;
            if (!$w4) $emp_missing[] = __('W-4 received', 'backstage-venue-manager');
            if (!$i9) $emp_missing[] = __('I-9 verified', 'backstage-venue-manager');
            return $emp_missing;
        }
    }


    // Meta keys (canonical)
    $k_done   = bvmgr_meta_key('vendor', 'tax_profile_completed_at');

    $k_legal  = bvmgr_meta_key('vendor', 'payee_legal_name');
    $k_entity = bvmgr_meta_key('vendor', 'entity_type');

    $k_addr1 = bvmgr_meta_key('vendor', 'addr1');
    $k_city  = bvmgr_meta_key('vendor', 'city');
    $k_state = bvmgr_meta_key('vendor', 'state');
    $k_zip   = bvmgr_meta_key('vendor', 'zip');

    $k_upload = bvmgr_meta_key('vendor', 'w9_upload_id');
    $k_recv   = bvmgr_meta_key('vendor', 'w9_received_date');
    $k_attest = bvmgr_meta_key('vendor', 'w9_attested_at');
    $k_prov   = bvmgr_meta_key('vendor', 'w9_provider');

    // If an admin explicitly marked the profile complete, treat it as complete.
    // This supports email-based W-9 workflows where the signed W-9 is not uploaded into VMS.
    $done_at = (int) get_post_meta($vendor_id, (string) $k_done, true);
    if ($done_at > 0) {
        return array();
    }

    // Helper: safe scalar meta read
    $m = function (int $id, string $key): string {
        $v = get_post_meta($id, $key, true);
        if (is_array($v) || is_object($v)) {
            if (function_exists('bvmgr_record_operational_issue')) {
                bvmgr_record_operational_issue(
                    'tax_profile_meta_shape_invalid',
                    array(
                        'entity_id' => $id,
                        'entity_type' => 'vendor',
                        'operation' => 'read_meta',
                        'status' => 'invalid',
                    )
                );
            }
            return '';
        }
        return trim((string) $v);
    };

    $legal  = $m($vendor_id, (string) $k_legal);
    $entity = $m($vendor_id, (string) $k_entity);

    $addr1 = $m($vendor_id, (string) $k_addr1);
    $city  = $m($vendor_id, (string) $k_city);
    $state = $m($vendor_id, (string) $k_state);
    $zip   = $m($vendor_id, (string) $k_zip);

    if ($legal === '')  $missing[] = 'Legal/Payee Name';
    if ($entity === '') $missing[] = 'Entity Type';

    if ($addr1 === '') $missing[] = 'Mailing Address (line 1)';
    if ($city === '')  $missing[] = 'Mailing Address (city)';
    if ($state === '') $missing[] = 'Mailing Address (state)';
    if ($zip === '')   $missing[] = 'Mailing Address (ZIP)';

    // W-9 requirement depends on provider mode (global setting)
    $settings = get_option('vms_settings', array());
    $settings = is_array($settings) ? $settings : array();

    $provider = isset($settings['tax_w9_provider']) ? (string) $settings['tax_w9_provider'] : '';
    if (!in_array($provider, array('upload', 'quickbooks_email', 'tax1099_email'), true)) {
        $provider = 'upload';
    }

    $upload_id = (int) get_post_meta($vendor_id, (string) $k_upload, true);
    $recv_date = $m($vendor_id, (string) $k_recv);
    $attested_at = (int) get_post_meta($vendor_id, (string) $k_attest, true);

    $provider_label = 'Upload';
    if ($provider === 'quickbooks_email') {
        $provider_label = 'QuickBooks Online';
    } elseif ($provider === 'tax1099_email') {
        $provider_label = 'Tax1099';
    }

    if ($provider === 'upload') {
        if ($upload_id <= 0 && $recv_date === '') {
            $missing[] = 'Signed W-9 Upload (or Received Date)';
        }
    } else {
        // Off-site providers remain incomplete until the venue confirms completion,
        // unless a real received-date sync or upload exists.
        if ($upload_id <= 0 && $recv_date === '') {
            if ($attested_at > 0) {
                $missing[] = sprintf('%s completion pending admin confirmation', $provider_label);
            } else {
                $missing[] = sprintf('%s completion not yet confirmed by staff', $provider_label);
            }
        }
    }

    return $missing;
}


/**
 * Convenience wrapper used by admin UI metaboxes.
 * True when there are no missing tax-profile requirements.
 */
if (!function_exists('bvmgr_vendor_tax_profile_is_complete')) {
    function bvmgr_vendor_tax_profile_is_complete(int $vendor_id): bool
    {
        return count(bvmgr_vendor_tax_profile_missing_items($vendor_id)) === 0;
    }
}

/**
 * Venue default time helpers
 */

function bvmgr_get_venue_default_times(int $venue_id): array
{
    $start = trim((string) get_post_meta($venue_id, '_vms_default_start_time', true));
    $end   = trim((string) get_post_meta($venue_id, '_vms_default_end_time', true));
    $dur   = (int) get_post_meta($venue_id, '_vms_default_duration_min', true);

    if ($start !== '' && !preg_match('/^\d{2}:\d{2}$/', $start)) $start = '';
    if ($end !== '' && !preg_match('/^\d{2}:\d{2}$/', $end)) $end = '';

    return [
        'start' => $start,
        'end'   => $end,
        'dur'   => max(0, $dur),
    ];
}

/**
 * Add minutes to HH:MM and return HH:MM (24h).
 */
function bvmgr_time_add_minutes(string $hhmm, int $minutes): string
{
    if (!preg_match('/^\d{2}:\d{2}$/', $hhmm)) return '';
    $minutes = (int) $minutes;

    [$h, $m] = array_map('intval', explode(':', $hhmm));
    $total = ($h * 60) + $m + $minutes;

    // Wrap around 24h (optional but safe)
    $total = $total % (24 * 60);
    if ($total < 0) $total += (24 * 60);

    $nh = floor($total / 60);
    $nm = $total % 60;

    return sprintf('%02d:%02d', $nh, $nm);
}



// =======================================
// Holidays (venue-scoped) helpers
// =======================================

/**
 * Stored format (option: vms_holidays):
 * [
 *   123 => [ // venue_id
 *     '2026-05-25' => [
 *        'name' => 'Memorial Day',
 *        'status' => 'open'|'closed',
 *        'rules' => [ 'vendor' => [. . .], 'bar' => [. . .], . . . ] // future
 *     ],
 *   ],
 * ]
 */
function bvmgr_get_holidays_option(): array
{
    $raw = get_option('vms_holidays', array());
    return is_array($raw) ? $raw : array();
}

function bvmgr_get_venue_holidays(int $venue_id): array
{
    if ($venue_id <= 0) return array();

    $all = bvmgr_get_holidays_option();
    if (!isset($all[$venue_id]) || !is_array($all[$venue_id])) return array();

    return $all[$venue_id];
}

/**
 * Returns holiday array or null.
 */
function bvmgr_get_venue_holiday_for_date(int $venue_id, string $date_yyyy_mm_dd): ?array
{
    if ($venue_id <= 0) return null;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_yyyy_mm_dd)) return null;

    $holidays = bvmgr_get_venue_holidays($venue_id);
    if (!isset($holidays[$date_yyyy_mm_dd]) || !is_array($holidays[$date_yyyy_mm_dd])) return null;

    $h = $holidays[$date_yyyy_mm_dd];

    $name   = isset($h['name']) ? (string) $h['name'] : '';
    $status = isset($h['status']) ? (string) $h['status'] : '';

    if ($status !== 'open' && $status !== 'closed') $status = 'open';

    return array(
        'date'   => $date_yyyy_mm_dd,
        'name'   => $name,
        'status' => $status,
        'rules'  => (isset($h['rules']) && is_array($h['rules'])) ? $h['rules'] : array(),
    );
}

function bvmgr_is_venue_closed_on_date(int $venue_id, string $date_yyyy_mm_dd): bool
{
    $h = bvmgr_get_venue_holiday_for_date($venue_id, $date_yyyy_mm_dd);
    return ($h && $h['status'] === 'closed');
}

/**
 * For UI labeling.
 */
function bvmgr_holiday_name_for_badge(array $holiday): string
{
    $name = trim((string)($holiday['name'] ?? ''));
    return $name !== '' ? $name : 'Holiday';
}

// =======================================
// Holiday pay helpers (venue-scoped)
// =======================================

/**
 * Returns vendor pay defaults for a specific venue holiday date, if configured.
 *
 * Expected stored location:
 * option vms_holidays[venue_id][YYYY-MM-DD]['rules']['vendor'] = [
 *   'structure' => 'flat_fee'|'door_split'|'flat_fee_door_split'|'attendance_bonus',
 *   'flat_fee_amount' => 500,
 *   'door_split_percent' => 80,
 *   'attendance_bonus_mode' => 'step'|'continuous',
 *   'attendance_bonus_start_count' => 100,
 *   'attendance_bonus_step_size' => 50,
 *   'attendance_bonus_step_bonus' => 250,
 *   'attendance_bonus_per_ticket_rate' => 5,
 *   'attendance_bonus_max_bonus' => 10000
 * ]
 */
function bvmgr_get_venue_holiday_vendor_pay_defaults(int $venue_id, string $date_yyyy_mm_dd): array
{
    $h = bvmgr_get_venue_holiday_for_date($venue_id, $date_yyyy_mm_dd);
    if (!$h) return array();

    $rules  = (isset($h['rules']) && is_array($h['rules'])) ? $h['rules'] : array();
    $vendor = (isset($rules['vendor']) && is_array($rules['vendor'])) ? $rules['vendor'] : array();
    if (empty($vendor)) {
        return array();
    }

    return function_exists('bvmgr_normalize_comp_terms')
        ? bvmgr_normalize_comp_terms($vendor)
        : $vendor;
}

/**
 * Flag the admin screen to scroll back to the Compensation section
 * after the page reloads.
 *
 * Usage:
 *   vms_admin_scroll_to_compensation( $post_id );
 *
 * The actual scrolling JS reads this meta and then deletes it
 * so it only happens once.
 */
function bvmgr_admin_scroll_to_compensation(int $post_id): void
{
    if ($post_id <= 0) {
        return;
    }

    update_post_meta($post_id, '_vms_admin_scroll_to', 'vms-compensation');
}

/**
 * Format a snapshot datetime (stored as "Y-m-d H:i:s") into a friendly admin string.
 * Example output: "Sat Jan 10, 2026 8:27 PM"
 */


// Helper: build a readable snapshot summary line
/**
 * Build a human-readable snapshot summary of PAY TERMS only.
 * IMPORTANT: This intentionally does NOT include package title or applied date.
 * Those belong in the UI as separate lines to avoid duplication.
 */

function bvmgr_snapshot_summary_line(array $snap): string
{
    $structure = isset($snap['structure']) ? sanitize_key((string) $snap['structure']) : '';
    if ($structure === 'attendance_bonus') {
        $base = is_numeric($snap['flat_fee_amount'] ?? null) ? max(0.0, (float) $snap['flat_fee_amount']) : 0.0;
        $mode = bvmgr_normalize_attendance_bonus_mode((string) ($snap['attendance_bonus_mode'] ?? ''));
        $start_count = bvmgr_normalize_comp_nonnegative_int($snap['attendance_bonus_start_count'] ?? null);
        $max_bonus = bvmgr_normalize_comp_nonnegative_float($snap['attendance_bonus_max_bonus'] ?? null);
        $prefix = 'Base $' . number_format($base, 2);

        if ($mode === 'step') {
            $step_size = bvmgr_normalize_comp_nonnegative_int($snap['attendance_bonus_step_size'] ?? null);
            $step_bonus = bvmgr_normalize_comp_nonnegative_float($snap['attendance_bonus_step_bonus'] ?? null);
            if ($start_count !== null && $step_size !== null && $step_size >= 1 && $step_bonus !== null) {
                $summary = sprintf(
                    'Base $%1$s + attendance bonus: +$%2$s every %3$d tickets after %4$d',
                    number_format($base, 2),
                    number_format($step_bonus, 2),
                    $step_size,
                    $start_count
                );
                if ($max_bonus !== null) {
                    $summary .= sprintf(' (capped at +$%s)', number_format($max_bonus, 2));
                }
                $deposit_summary = function_exists('bvmgr_comp_deposit_summary_part') ? bvmgr_comp_deposit_summary_part($snap) : '';
                $final_payment_summary = function_exists('bvmgr_comp_final_payment_summary_part') ? bvmgr_comp_final_payment_summary_part($snap) : '';
                if ($deposit_summary !== '') $summary .= ' | ' . $deposit_summary;
                if ($final_payment_summary !== '') $summary .= ' | ' . $final_payment_summary;
                return $summary;
            }
        } elseif ($mode === 'continuous') {
            $per_ticket_rate = bvmgr_normalize_comp_nonnegative_float($snap['attendance_bonus_per_ticket_rate'] ?? null);
            if ($start_count !== null && $per_ticket_rate !== null) {
                $summary = sprintf(
                    'Base $%1$s + attendance bonus: +$%2$s per ticket after %3$d',
                    number_format($base, 2),
                    number_format($per_ticket_rate, 2),
                    $start_count
                );
                if ($max_bonus !== null) {
                    $summary .= sprintf(' (capped at +$%s)', number_format($max_bonus, 2));
                }
                $deposit_summary = function_exists('bvmgr_comp_deposit_summary_part') ? bvmgr_comp_deposit_summary_part($snap) : '';
                $final_payment_summary = function_exists('bvmgr_comp_final_payment_summary_part') ? bvmgr_comp_final_payment_summary_part($snap) : '';
                if ($deposit_summary !== '') $summary .= ' | ' . $deposit_summary;
                if ($final_payment_summary !== '') $summary .= ' | ' . $final_payment_summary;
                return $summary;
            }
        }

        $deposit_summary = function_exists('bvmgr_comp_deposit_summary_part') ? bvmgr_comp_deposit_summary_part($snap) : '';
        $final_payment_summary = function_exists('bvmgr_comp_final_payment_summary_part') ? bvmgr_comp_final_payment_summary_part($snap) : '';
        $summary = $prefix . ' + attendance bonus';
        if ($deposit_summary !== '') $summary .= ' | ' . $deposit_summary;
        if ($final_payment_summary !== '') $summary .= ' | ' . $final_payment_summary;
        return $summary;
    }

    $parts = [];

    if ($structure !== '') {
        $parts[] = 'Structure: ' . bvmgr_pretty_structure_label($structure);
    }

    if (array_key_exists('flat_fee_amount', $snap) && $snap['flat_fee_amount'] !== null && $snap['flat_fee_amount'] !== '') {
        $parts[] = 'Flat: $' . number_format((float) $snap['flat_fee_amount'], 2);
    }

    if (array_key_exists('door_split_percent', $snap) && $snap['door_split_percent'] !== null && $snap['door_split_percent'] !== '') {
        $pct = rtrim(rtrim((string) $snap['door_split_percent'], '0'), '.');
        $parts[] = 'Split: ' . $pct . '%';
    }

    if (array_key_exists('commission_percent', $snap) && $snap['commission_percent'] !== null && $snap['commission_percent'] !== '') {
        $pct  = rtrim(rtrim((string) $snap['commission_percent'], '0'), '.');
        $mode = !empty($snap['commission_mode']) ? (string) $snap['commission_mode'] : 'artist_fee';
        $parts[] = 'Agent fee: ' . $pct . '% (' . ($mode === 'gross' ? 'gross/settlement' : 'added on top') . ')';
    }

    $deposit_summary = function_exists('bvmgr_comp_deposit_summary_part') ? bvmgr_comp_deposit_summary_part($snap) : '';
    if ($deposit_summary !== '') {
        $parts[] = $deposit_summary;
    }
    $final_payment_summary = function_exists('bvmgr_comp_final_payment_summary_part') ? bvmgr_comp_final_payment_summary_part($snap) : '';
    if ($final_payment_summary !== '') {
        $parts[] = $final_payment_summary;
    }

    return implode(' | ', $parts);
}

function bvmgr_format_snapshot_datetime($value): string
{
    $raw = is_string($value) ? trim($value) : '';
    if ($raw === '') return '—';

    // Stored format is typically "2026-01-10 20:27:09" (site timezone).
    $ts = strtotime($raw);
    if (!$ts) return $raw;

    // Use WordPress timezone + localization
    // D = day short (Sat), M = month short (Jan), j = day number, Y = year, g:i A = 12h time
    if (function_exists('wp_date')) {
        return wp_date('D M j, Y g:i A', $ts);
    }

    // Fallback for older installs
    return date_i18n('D M j, Y g:i A', $ts);
}

function bvmgr_required_public_pages(): array
{
    return [
        'vendor_application' => [
            'slug'    => 'vendor-application',
            'title'   => 'Vendor Application',
            'content' => "[vms_vendor_apply]\n",
        ],
        'vendor_portal' => [
            'slug'    => 'vendor-portal',
            'title'   => 'Vendor Portal',
            'content' => "[vms_vendor_portal]\n",
        ],
        'staff_portal' => [
            'slug'    => 'staff-portal',
            'title'   => 'Staff Portal',
            'content' => "[vms_staff_portal]\n",
        ],
        'public_calendar' => [
            'slug'    => 'events-calendar',
            'title'   => 'Public Calendar',
            'content' => "[vms_public_calendar]\n",
        ],
    ];
}
if (!function_exists('bvmgr_normalize_public_event_calendar_url')) {
    /**
     * Normalize an operator-entered public calendar URL.
     *
     * Supports absolute URLs and site-relative paths so operators are not forced
     * to type a full URL for same-site pages.
     */
    function bvmgr_normalize_public_event_calendar_url(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        if (strpos($url, '//') === 0) {
            $url = (is_ssl() ? 'https:' : 'http:') . $url;
        } elseif ($url[0] === '/') {
            $url = home_url($url);
        } elseif (!preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
            $url = home_url('/' . ltrim($url, '/'));
        }

        $url = esc_url_raw($url);
        return is_string($url) ? $url : '';
    }
}

if (!function_exists('bvmgr_get_published_page_url')) {
    /**
     * Return a permalink only for a published WordPress page.
     */
    function bvmgr_get_published_page_url(int $page_id): string
    {
        if ($page_id <= 0) {
            return '';
        }

        if (get_post_type($page_id) !== 'page' || get_post_status($page_id) !== 'publish') {
            return '';
        }

        $url = get_permalink($page_id);
        return is_string($url) ? $url : '';
    }
}

if (!function_exists('bvmgr_detect_public_event_calendar_url')) {
    /**
     * Detect the best available public event calendar URL without operator input.
     */
    function bvmgr_detect_public_event_calendar_url(): string
    {
        $stored_page_id = absint(get_option('vms_page_public_calendar', 0));
        $stored_url = bvmgr_get_published_page_url($stored_page_id);
        if ($stored_url !== '') {
            return $stored_url;
        }

        $required_pages = function_exists('bvmgr_required_public_pages') ? (array) bvmgr_required_public_pages() : array();
        $calendar_slug = isset($required_pages['public_calendar']['slug']) ? sanitize_title((string) $required_pages['public_calendar']['slug']) : 'events-calendar';
        if ($calendar_slug !== '') {
            $page = get_page_by_path($calendar_slug);
            if ($page instanceof WP_Post) {
                $slug_url = bvmgr_get_published_page_url((int) $page->ID);
                if ($slug_url !== '') {
                    return $slug_url;
                }
            }
        }

        $shortcode_pages = get_posts(array(
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'orderby'        => 'ID',
            'order'          => 'ASC',
            's'              => 'vms_public_calendar',
            'no_found_rows'  => true,
            'fields'         => 'ids',
        ));
        if (!empty($shortcode_pages[0])) {
            $shortcode_url = bvmgr_get_published_page_url(absint($shortcode_pages[0]));
            if ($shortcode_url !== '') {
                return $shortcode_url;
            }
        }

        if (function_exists('tribe_get_events_link')) {
            $tec_url = (string) tribe_get_events_link();
            if ($tec_url !== '') {
                return $tec_url;
            }
        }

        return home_url('/');
    }
}

if (!function_exists('bvmgr_get_public_event_calendar_url')) {
    /**
     * Resolve the customer-facing events/calendar URL used in public notices.
     *
     * Order:
     * 1. Advanced custom URL override.
     * 2. Selected WordPress Page dropdown.
     * 3. Auto-detected VMS public calendar page.
     * 4. TEC events archive.
     * 5. Home page.
     */
    function bvmgr_get_public_event_calendar_url(): string
    {
        $settings = (array) get_option('vms_settings', array());

        $custom_url = isset($settings['public_calendar_custom_url'])
            ? bvmgr_normalize_public_event_calendar_url((string) $settings['public_calendar_custom_url'])
            : '';
        if ($custom_url !== '') {
            return (string) apply_filters('vms_public_event_calendar_url', $custom_url, 'custom_url');
        }

        $page_id = isset($settings['public_calendar_page_id']) ? absint($settings['public_calendar_page_id']) : 0;
        $page_url = bvmgr_get_published_page_url($page_id);
        if ($page_url !== '') {
            return (string) apply_filters('vms_public_event_calendar_url', $page_url, 'selected_page');
        }

        $detected_url = bvmgr_detect_public_event_calendar_url();
        return (string) apply_filters('vms_public_event_calendar_url', $detected_url, 'auto_detect');
    }
}

add_filter('manage_users_columns', function ($cols) {
    $cols['user_id'] = 'User ID';
    return $cols;
});

add_filter('manage_users_custom_column', function ($value, $column, $user_id) {
    if ($column === 'user_id') {
        return (int) $user_id;
    }
    return $value;
}, 10, 3);

/**
 * Get active schedule dates for a venue.
 *
 * Season is OPTIONAL:
 * - If venue season start/end exist, use them.
 * - If not, default to the current calendar year (Jan 1 → Dec 31).
 *
 * Returns array of YYYY-MM-DD strings.
 */
function bvmgr_normalize_int_array($value): array
{
    if (empty($value)) {
        return array();
    }
    if (is_string($value)) {
        $value = maybe_unserialize($value);
    }
    if (!is_array($value)) {
        return array();
    }
    $out = array();
    foreach ($value as $v) {
        $v = intval($v);
        if ($v < 0 || $v > 6) {
            continue;
        }
        $out[$v] = $v;
    }
    ksort($out);
    return array_values($out);
}

/**
 * User preference: include Draft/Ready (what-if) across Schedule + Event Plans list + Dashboard.
 *
 * Storage:
 * - user_meta key: _vms_include_drafts
 * - values: '1' or '0'
 */
function bvmgr_user_meta_key_include_drafts(): string
{
    return '_vms_include_drafts';
}

function bvmgr_user_pref_has_include_drafts(int $user_id = 0): bool
{
    $user_id = $user_id > 0 ? $user_id : (int) get_current_user_id();
    if ($user_id <= 0) {
        return false;
    }

    return (bool) metadata_exists('user', $user_id, bvmgr_user_meta_key_include_drafts());
}

function bvmgr_user_pref_get_include_drafts(int $user_id = 0): bool
{
    $user_id = $user_id > 0 ? $user_id : (int) get_current_user_id();
    if ($user_id <= 0) {
        return false;
    }

    $raw = get_user_meta($user_id, bvmgr_user_meta_key_include_drafts(), true);
    $raw = strtolower(trim((string) $raw));
    return in_array($raw, array('1', 'true', 'yes', 'on'), true);
}

function bvmgr_user_pref_set_include_drafts(bool $include_drafts, int $user_id = 0): void
{
    $user_id = $user_id > 0 ? $user_id : (int) get_current_user_id();
    if ($user_id <= 0) {
        return;
    }

    update_user_meta($user_id, bvmgr_user_meta_key_include_drafts(), $include_drafts ? '1' : '0');
}

function bvmgr_get_venue_schedule_config(int $venue_id): array
{
    $open_days = bvmgr_normalize_int_array(get_post_meta($venue_id, '_vms_venue_open_days', true));
    $open_year_round = get_post_meta($venue_id, '_vms_venue_open_year_round', true);
    $open_year_round = !empty($open_year_round) && $open_year_round !== '0';

    $seasons = get_post_meta($venue_id, '_vms_venue_seasons', true);
    if (is_string($seasons)) {
        $seasons = maybe_unserialize($seasons);
    }
    if (!is_array($seasons)) {
        $seasons = array();
    }

    $normalized_seasons = array();
    foreach ($seasons as $s) {
        if (!is_array($s)) {
            continue;
        }
        $start = isset($s['start']) ? sanitize_text_field($s['start']) : '';
        $end   = isset($s['end']) ? sanitize_text_field($s['end']) : '';
        if (!$start || !$end) {
            continue;
        }
        // Normalize to Y-m-d.
        $start = gmdate('Y-m-d', strtotime($start));
        $end   = gmdate('Y-m-d', strtotime($end));
        if ($start > $end) {
            continue;
        }
        $normalized_seasons[] = array('start' => $start, 'end' => $end);
    }

    return array(
        'open_days' => $open_days,
        'open_year_round' => $open_year_round,
        'seasons' => $normalized_seasons,
    );
}

function bvmgr_venue_is_in_season(int $venue_id, string $ymd): bool
{
    $cfg = bvmgr_get_venue_schedule_config($venue_id);
    if (!empty($cfg['open_year_round'])) {
        return true;
    }

    // If venue has explicit seasons, use them.
    if (!empty($cfg['seasons'])) {
        foreach ($cfg['seasons'] as $s) {
            if ($ymd >= $s['start'] && $ymd <= $s['end']) {
                return true;
            }
        }
        return false;
    }

    // Fallback to global season rules (if configured).
    $global_active = bvmgr_get_active_season_dates($venue_id);
    if (empty($global_active)) {
        $global_active = bvmgr_get_active_season_dates(0); // optional legacy fallback
    }

    // Fallback to Season Dates generated payload
    $active = bvmgr_get_active_season_dates($venue_id);
    if (empty($active)) {
        $active = bvmgr_get_active_season_dates(0); // optional legacy global fallback
    }

    if (!empty($active)) {
        return in_array($ymd, $active, true);
    }

    // No seasons configured anywhere → treat as closed until configured.
    return false;
}

function bvmgr_venue_is_open_on_date(int $venue_id, string $ymd): bool
{
    $cfg = bvmgr_get_venue_schedule_config($venue_id);
    $open_days = $cfg['open_days'];
    if (empty($open_days)) {
        return false; // closed until configured
    }

    $w = intval(gmdate('w', strtotime($ymd))); // 0=Sun..6=Sat
    if (!in_array($w, $open_days, true)) {
        return false;
    }

    return bvmgr_venue_is_in_season($venue_id, $ymd);
}

function bvmgr_get_active_dates_for_venue(int $venue_id, int $months_ahead = 24): array
{
    if ($venue_id <= 0) {
        return array();
    }

    $cfg = bvmgr_get_venue_schedule_config($venue_id);
    $open_days = $cfg['open_days'];
    if (empty($open_days)) {
        return array(); // closed until configured
    }

    $today = new DateTime('today');
    $end = (new DateTime('today'))->modify('+' . intval($months_ahead) . ' months');

    // If Season Dates generated payload exists for this venue, it is authoritative.
    // It already includes pattern weekday constraints and blackout overrides.
    $season_active = bvmgr_get_active_season_dates($venue_id);
    if (is_array($season_active) && !empty($season_active)) {
        $today_ymd = $today->format('Y-m-d');
        $end_ymd   = $end->format('Y-m-d');

        $out = [];
        foreach ($season_active as $d) {
            $d = (string) $d;
            if ($d >= $today_ymd && $d <= $end_ymd) {
                $out[] = $d;
            }
        }
        sort($out);
        return $out;
    }

    $dates = array();

    // Determine the date set we should consider "in season".
    // - If venue is open year-round: all dates in window qualify.
    // - Else if venue has explicit seasons: only dates inside seasons qualify.
    // - Else: fall back to global season rules (if any).
    $has_explicit_seasons = !empty($cfg['seasons']);

    // Season Dates v1 override (generated active dates).
    // If present, it takes precedence over legacy per-venue seasons meta.
    $season_override = bvmgr_get_active_season_dates($venue_id);
    $season_override_set = array();
    if (is_array($season_override) && !empty($season_override)) {
        foreach ($season_override as $d) {
            $d = (string) $d;
            if ($d !== '') {
                $season_override_set[$d] = true;
            }
        }
    }

    // Legacy global fallback (venue_id=0) only applies if there is no override
    // and no explicit per-venue seasons in the venue config.
    $global_active_set = array();
    if (empty($season_override_set) && !$cfg['open_year_round'] && !$has_explicit_seasons) {
        $global_active = bvmgr_get_active_season_dates(0);
        if (is_array($global_active) && !empty($global_active)) {
            foreach ($global_active as $d) {
                $d = (string) $d;
                if ($d !== '') {
                    $global_active_set[$d] = true;
                }
            }
        }
    }

    $cur = clone $today;
    while ($cur <= $end) {
        $ymd = $cur->format('Y-m-d');
        $w = intval($cur->format('w'));

        if (in_array($w, $open_days, true)) {
            $in_season = false;
            if (!empty($season_override_set)) {
                $in_season = isset($season_override_set[$ymd]);
            } elseif ($cfg['open_year_round']) {
                $in_season = true;
            } elseif ($has_explicit_seasons) {
                foreach ($cfg['seasons'] as $s) {
                    if ($ymd >= $s['start'] && $ymd <= $s['end']) {
                        $in_season = true;
                        break;
                    }
                }
            } else {
                $in_season = !empty($global_active_set) && isset($global_active_set[$ymd]);
            }

            if ($in_season) {
                $dates[] = $ymd;
            }
        }

        $cur->modify('+1 day');
    }

    return $dates;
}


if (!function_exists('bvmgr_pretty_structure_label')) {
    function bvmgr_pretty_structure_label($s): string
    {
        $s = (string) $s;

        // If you ever change meta keys later, this keeps output decent.
        return match ($s) {
            'flat_fee'            => 'Flat Fee',
            'door_split'          => 'Door Split',
            'flat_fee_door_split' => 'Flat Fee + Door Split',
            'attendance_bonus'    => 'Base + Attendance Bonus',
            default               => ($s !== '' ? strtoupper($s) : '—'),
        };
    }
}

/**
 * ==========================================================
 * Vendor Profile Change Tracking (No-Email “Option A”)
 * ==========================================================
 *
 * Meta keys used on vendor posts (post_type: vms_vendor)
 * - _vms_vendor_profile_last_updated_gmt   (string mysql GMT)
 * - _vms_vendor_profile_last_updated_by    (int user ID)
 * - _vms_vendor_profile_last_reviewed_gmt  (string mysql GMT)
 * - _vms_vendor_profile_last_reviewed_by   (int user ID)
 * - _vms_vendor_profile_needs_review       ('1' or '0')
 */

/**
 * Mark vendor profile as updated and optionally record what changed.
 *
 * Stores:
 *  - _vms_vendor_last_updated_at
 *  - _vms_vendor_last_updated_by
 *  - _vms_vendor_last_updated_fields (array)
 */
/**
 * Mark vendor as updated (needs admin review).
 * This is the ONLY function vendor-portal save handlers should call.
 *
 * Canonical meta schema used by admin UI:
 * - _vms_vendor_profile_last_updated_gmt (mysql GMT)
 * - _vms_vendor_profile_last_updated_by  (int)
 * - _vms_vendor_profile_needs_review     ('1' or '0')
 * Optional:
 * - _vms_vendor_profile_last_updated_fields (array of field keys)
 * - _vms_vendor_profile_last_update_context (string)
 */
function bvmgr_vendor_mark_profile_updated($vendor_id, $user_id = 0, $changed_fields = array(), $context = '')
{
    $vendor_id = (int) $vendor_id;
    if ($vendor_id <= 0) return;

    $user_id = (int) $user_id;
    if ($user_id <= 0 && is_user_logged_in()) {
        $user_id = (int) get_current_user_id();
    }

    $now_gmt = current_time('mysql', true);

    update_post_meta($vendor_id, '_vms_vendor_profile_last_updated_gmt', $now_gmt);

    if ($user_id > 0) {
        update_post_meta($vendor_id, '_vms_vendor_profile_last_updated_by', $user_id);
    }

    // ✅ This is the flag your admin column/metabox reads
    update_post_meta($vendor_id, '_vms_vendor_profile_needs_review', '1');

    // Optional extras (safe)
    if (is_array($changed_fields) && !empty($changed_fields)) {
        update_post_meta($vendor_id, '_vms_vendor_profile_last_updated_fields', array_values($changed_fields));
    }

    if ($context !== '') {
        update_post_meta($vendor_id, '_vms_vendor_profile_last_update_context', sanitize_key($context));
    }
}

/**
 * Compare "before" vs "after" and return the meta keys that changed.
 * Only compares keys you provide (so you can ignore noisy fields).
 */
function bvmgr_vendor_diff_meta_keys($vendor_id, array $keys, array $new_values_by_key)
{
    $changed = array();

    foreach ($keys as $k) {
        $old = get_post_meta($vendor_id, $k, true);
        $new = array_key_exists($k, $new_values_by_key) ? $new_values_by_key[$k] : null;

        // normalize a bit
        $old_norm = is_string($old) ? trim($old) : $old;
        $new_norm = is_string($new) ? trim($new) : $new;

        if ($old_norm != $new_norm) {
            $changed[] = $k;
        }
    }

    return $changed;
}

function bvmgr_vendor_mark_profile_reviewed($vendor_id, $user_id = null)
{
    $vendor_id = (int) $vendor_id;
    if ($vendor_id <= 0) return;

    if ($user_id === null) {
        $user_id = get_current_user_id();
    }

    $now_gmt = current_time('mysql', true);

    update_post_meta($vendor_id, '_vms_vendor_profile_last_reviewed_gmt', $now_gmt);
    update_post_meta($vendor_id, '_vms_vendor_profile_last_reviewed_by', (int) $user_id);

    // Clear the flag
    update_post_meta($vendor_id, '_vms_vendor_profile_needs_review', '0');
}

function bvmgr_vendor_profile_needs_review($vendor_id)
{
    $flag = get_post_meta((int) $vendor_id, '_vms_vendor_profile_needs_review', true);
    return ($flag === '1');
}

/**
 * Admin: Add “Updates” column to Vendors list
 */
add_filter('manage_vms_vendor_posts_columns', 'bvmgr_vendor_add_updates_column');
function bvmgr_vendor_add_updates_column($columns)
{
    $new = array();

    foreach ($columns as $key => $label) {
        // Put it before date if possible
        if ($key === 'date') {
            $new['vms_vendor_updates'] = __('Updates', 'backstage-venue-manager');
        }
        $new[$key] = $label;
    }

    if (!isset($new['vms_vendor_updates'])) {
        $new['vms_vendor_updates'] = __('Updates', 'backstage-venue-manager');
    }

    return $new;
}

add_action('manage_vms_vendor_posts_custom_column', 'bvmgr_vendor_render_updates_column', 10, 2);
function bvmgr_vendor_render_updates_column($column, $post_id)
{
    if ($column !== 'vms_vendor_updates') return;

    $needs = bvmgr_vendor_profile_needs_review($post_id);

    $last_gmt = get_post_meta($post_id, '_vms_vendor_profile_last_updated_gmt', true);
    $last_context = sanitize_key((string) get_post_meta($post_id, '_vms_vendor_profile_last_update_context', true));
    $context_label = function_exists('bvmgr_vendor_submission_context_label')
        ? bvmgr_vendor_submission_context_label($last_context)
        : ($last_context !== '' ? ucwords(str_replace(array('-', '_'), ' ', $last_context)) : '');
    $time_ago = '';

    if ($last_gmt) {
        $ts = strtotime($last_gmt . ' GMT');
        if ($ts) {
            $time_ago = human_time_diff($ts, time()) . ' ago';
        }
    }

    if ($needs) {
        echo '<span style="display:inline-block;padding:2px 8px;border-radius:999px;background:#fee2e2;color:#991b1b;font-weight:700;font-size:11px;letter-spacing:.02em;text-transform:uppercase;">Needs review</span>';
        if ($context_label !== '') {
            echo '<div style="margin-top:4px;font-size:12px;opacity:.85;">' . esc_html($context_label) . '</div>';
        }
        if ($time_ago) {
            echo '<div style="margin-top:4px;font-size:12px;opacity:.85;">' . esc_html($time_ago) . '</div>';
        }
    } else {
        echo '<span style="display:inline-block;padding:2px 8px;border-radius:999px;background:#e5e7eb;color:#374151;font-weight:700;font-size:11px;letter-spacing:.02em;text-transform:uppercase;">OK</span>';
        if ($context_label !== '') {
            echo '<div style="margin-top:4px;font-size:12px;opacity:.85;">Last update: ' . esc_html($context_label) . '</div>';
        }
        if ($time_ago) {
            echo '<div style="margin-top:4px;font-size:12px;opacity:.85;">Updated ' . esc_html($time_ago) . '</div>';
        }
    }
}

/**
 * Admin: Metabox on vendor edit screen to show status + “Mark Reviewed”
 */
add_action('add_meta_boxes', 'bvmgr_vendor_add_change_tracking_metabox');
function bvmgr_vendor_add_change_tracking_metabox()
{
    add_meta_box(
        'vms_vendor_change_tracking',
        __('Vendor Updates', 'backstage-venue-manager'),
        'bvmgr_vendor_change_tracking_metabox_cb',
        'vms_vendor',
        'side',
        'high'
    );
}

function bvmgr_vendor_change_tracking_metabox_cb($post)
{
    $vendor_id = (int) $post->ID;

    $needs = bvmgr_vendor_profile_needs_review($vendor_id);

    $updated_gmt = get_post_meta($vendor_id, '_vms_vendor_profile_last_updated_gmt', true);
    $updated_by  = (int) get_post_meta($vendor_id, '_vms_vendor_profile_last_updated_by', true);
    $update_context = sanitize_key((string) get_post_meta($vendor_id, '_vms_vendor_profile_last_update_context', true));
    $update_context_label = function_exists('bvmgr_vendor_submission_context_label')
        ? bvmgr_vendor_submission_context_label($update_context)
        : ($update_context !== '' ? ucwords(str_replace(array('-', '_'), ' ', $update_context)) : '');

    $reviewed_gmt = get_post_meta($vendor_id, '_vms_vendor_profile_last_reviewed_gmt', true);
    $reviewed_by  = (int) get_post_meta($vendor_id, '_vms_vendor_profile_last_reviewed_by', true);

    echo '<div style="line-height:1.35;">';

    if ($needs) {
        echo '<div style="margin:0 0 8px 0;"><strong style="color:#991b1b;">Needs review</strong></div>';
    } else {
        echo '<div style="margin:0 0 8px 0;"><strong>All caught up</strong></div>';
    }

    if ($updated_gmt) {
        $ts = strtotime($updated_gmt . ' GMT');
        $when = $ts ? human_time_diff($ts, time()) . ' ago' : $updated_gmt;

        $who = $updated_by ? get_user_by('id', $updated_by) : null;
        $who_name = $who ? $who->display_name : '';

        echo '<div style="margin:0 0 8px 0;">';
        echo '<div><strong>Last vendor update:</strong></div>';
        echo '<div>' . esc_html($when) . ($who_name ? ' <span style="opacity:.8;">(' . esc_html($who_name) . ')</span>' : '') . '</div>';
        if ($update_context_label !== '') {
            echo '<div style="margin-top:4px;opacity:.85;"><strong>' . esc_html__('Update type:', 'backstage-venue-manager') . '</strong> ' . esc_html($update_context_label) . '</div>';
        }
        echo '</div>';
    } else {
        echo '<div style="margin:0 0 8px 0; opacity:.85;">No update history yet.</div>';
    }

    if ($reviewed_gmt) {
        $ts = strtotime($reviewed_gmt . ' GMT');
        $when = $ts ? human_time_diff($ts, time()) . ' ago' : $reviewed_gmt;

        $who = $reviewed_by ? get_user_by('id', $reviewed_by) : null;
        $who_name = $who ? $who->display_name : '';

        echo '<div style="margin:0 0 10px 0;">';
        echo '<div><strong>Last reviewed:</strong></div>';
        echo '<div>' . esc_html($when) . ($who_name ? ' <span style="opacity:.8;">(' . esc_html($who_name) . ')</span>' : '') . '</div>';
        echo '</div>';
    }

    if ($needs) {
        $nonce = wp_create_nonce('bvmgr_vendor_mark_reviewed_' . $vendor_id);
        $url = admin_url('admin-post.php?action=vms_vendor_mark_reviewed&vendor_id=' . $vendor_id . '&_wpnonce=' . $nonce);

        echo '<p style="margin:0;"><a class="button button-primary" href="' . esc_url($url) . '">Mark as Reviewed</a></p>';
    }

    echo '</div>';
}

/**
 * Admin handler: mark reviewed
 */
add_action('admin_post_vms_vendor_mark_reviewed', 'bvmgr_vendor_handle_mark_reviewed');
function bvmgr_vendor_handle_mark_reviewed()
{
    $vendor_id = isset($_GET['vendor_id']) ? (int) $_GET['vendor_id'] : 0;
    if ($vendor_id <= 0) {
        wp_die('Invalid vendor.');
    }
    if (!current_user_can('edit_post', $vendor_id)) {
        wp_die('Permission denied.');
    }

    check_admin_referer(bvmgr_nonce_action_for_request('bvmgr_vendor_mark_reviewed_' . $vendor_id, '_wpnonce'), '_wpnonce');

    bvmgr_vendor_mark_profile_reviewed($vendor_id, get_current_user_id());

    // Redirect back to vendor edit screen
    wp_safe_redirect(admin_url('post.php?post=' . $vendor_id . '&action=edit'));
    exit;
}


/**
 * Active season dates with venue override and global fallback.
 *
 * Priority:
 *  1) Venue override (if venue has its own season range configured)
 *  2) Global Season Dates admin UI (vms_active_dates)
 *  3) Generate from global rules (vms_season_rules) if needed
 *
 * Returns: string[] of YYYY-MM-DD
 */
if (!function_exists('bvmgr_get_active_season_dates')) {
    function bvmgr_get_active_season_dates(int $venue_id = 0): array
    {
        // v1 payload (per-venue) lives in wp_options: vms_season_active_dates_v1
        $all = get_option('vms_season_active_dates_v1', []);
        if (is_string($all)) {
            $all = maybe_unserialize($all);
        }

        if (is_array($all) && !empty($all)) {
            $payload = $all[(string)$venue_id] ?? $all[$venue_id] ?? [];

            if (is_array($payload) && !empty($payload)) {
                $dates = [];
                foreach (['open_ymd', 'dates_ymd', 'active_ymd', 'dates'] as $k) {
                    if (isset($payload[$k]) && is_array($payload[$k])) {
                        $dates = $payload[$k];
                        break;
                    }
                }

                if (!empty($dates) && is_array($dates)) {
                    $out = array_values(array_unique(array_map('sanitize_text_field', $dates)));
                    sort($out);
                    return $out;
                }


                if (isset($payload['dates_map']) && is_array($payload['dates_map']) && !empty($payload['dates_map'])) {
                    $out = array_keys($payload['dates_map']);
                    $out = array_values(array_unique(array_map('sanitize_text_field', $out)));
                    sort($out);
                    return $out;
                }

                // v1 mode exists but no usable date list in payload.
                // Do NOT auto-generate here (explicit generation is a locked rule).
                return [];
            }
        }

        // Legacy fallback: vms_active_dates (global)
        $active = get_option('vms_active_dates', array());
        if (is_string($active)) {
            $active = maybe_unserialize($active);
        }
        if (is_array($active) && !empty($active)) {
            return array_values(array_unique(array_map('sanitize_text_field', $active)));
        }

        // Legacy fallback: generate from vms_season_rules (global legacy rule format)
        $rules = get_option('vms_season_rules', array());
        if (is_string($rules)) {
            $rules = maybe_unserialize($rules);
        }
        if (!is_array($rules) || empty($rules)) {
            return array();
        }

        $dates = [];

        // v1 canonical: dates_map is a map like ['YYYY-MM-DD' => true]
        if (isset($payload['dates_map']) && is_array($payload['dates_map'])) {
            foreach ($payload['dates_map'] as $d => $is_open) {
                $d = is_string($d) ? trim($d) : '';
                if ($d !== '' && !empty($is_open)) {
                    $dates[] = $d;
                }
            }
        } else {
            // Transitional fallback (optional)
            foreach (['open_ymd', 'dates_ymd', 'active_ymd', 'dates'] as $k) {
                if (isset($payload[$k]) && is_array($payload[$k])) {
                    $dates = $payload[$k];
                    break;
                }
            }
        }

        if (!empty($dates)) {
            $out = array_values(array_unique(array_map('sanitize_text_field', $dates)));
            sort($out);
            return $out;
        }

        // v1 payload exists but no usable date list, do not auto-generate here
        return [];

        $dates = array();
        foreach ($rules as $rule) {
            if (!is_array($rule)) {
                continue;
            }
            $start = isset($rule['start']) ? sanitize_text_field($rule['start']) : '';
            $end   = isset($rule['end']) ? sanitize_text_field($rule['end']) : '';
            $days  = isset($rule['days']) ? $rule['days'] : array();
            $days  = bvmgr_normalize_int_array($days);

            if (!$start || !$end || empty($days)) {
                continue;
            }

            $start_dt = new DateTime($start);
            $end_dt   = new DateTime($end);

            $cur = clone $start_dt;
            while ($cur <= $end_dt) {
                $w = intval($cur->format('w'));
                if (in_array($w, $days, true)) {
                    $dates[] = $cur->format('Y-m-d');
                }
                $cur->modify('+1 day');
            }
        }

        $dates = array_values(array_unique($dates));
        sort($dates);
        return $dates;
    }
}

if (!function_exists('bvmgr_parse_local_ymd')) {
    function bvmgr_parse_local_ymd(string $ymd): ?DateTimeImmutable
    {
        $ymd = trim($ymd);
        if ($ymd === '') {
            return null;
        }

        $timezone = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $ymd, $timezone);
        return ($dt instanceof DateTimeImmutable) ? $dt : null;
    }
}

if (!function_exists('bvmgr_format_local_ymd')) {
    function bvmgr_format_local_ymd(string $ymd, string $format = 'M j, Y'): string
    {
        $ymd = trim($ymd);
        if ($ymd === '') {
            return '';
        }

        $dt = function_exists('bvmgr_parse_local_ymd') ? bvmgr_parse_local_ymd($ymd) : null;
        if ($dt instanceof DateTimeImmutable) {
            return $dt->format($format);
        }

        return $ymd;
    }
}

if (!function_exists('bvmgr_local_ymd_dow')) {
    function bvmgr_local_ymd_dow(string $ymd): ?int
    {
        $dt = function_exists('bvmgr_parse_local_ymd') ? bvmgr_parse_local_ymd($ymd) : null;
        if ($dt instanceof DateTimeImmutable) {
            return (int) $dt->format('w');
        }

        return null;
    }
}


if (!function_exists('bvmgr_get_schedule_window_bounds')) {
    /**
     * Returns schedule window bounds as DateTimeImmutable objects.
     * Start: first day of this month
     * End: later of (Dec 31 next year) or (start + 18 months - 1 day), capped at 24 months.
     *
     * @return array{start: DateTimeImmutable, end: DateTimeImmutable}
     */
    function bvmgr_get_schedule_window_bounds(): array
    {
        $tz = wp_timezone();

        $start = new DateTimeImmutable('first day of this month 00:00:00', $tz);

        $end1 = (new DateTimeImmutable('now', $tz))
            ->modify('last day of December next year')
            ->setTime(23, 59, 59);

        $end2 = $start
            ->modify('+18 months')
            ->modify('-1 day')
            ->setTime(23, 59, 59);

        $end = ($end2 > $end1) ? $end2 : $end1;

        $cap = $start
            ->modify('+24 months')
            ->modify('-1 day')
            ->setTime(23, 59, 59);

        if ($end > $cap) $end = $cap;

        return array('start' => $start, 'end' => $end);
    }
}

/**
 * Schedule window (admin Schedule page)
 *
 * Returns an array:
 *  - start (Y-m-d)
 *  - end   (Y-m-d)
 *  - months
 */
function bvmgr_get_schedule_window_bounds($venue_id = 0)
{
    $months = (int) apply_filters('vms_schedule_window_months', 24, (int) $venue_id);
    if ($months < 1) $months = 24;

    $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
    $now = new DateTime('now', $tz);

    // Window starts at the first day of the current month.
    $start = (clone $now)->modify('first day of this month')->setTime(0, 0, 0);

    // Window ends at the last day of the month (months-1 ahead).
    $end = (clone $start)
        ->modify('+' . ($months - 1) . ' months')
        ->modify('last day of this month')
        ->setTime(23, 59, 59);

    return array(
        'start'  => $start->format('Y-m-d'),
        'end'    => $end->format('Y-m-d'),
        'months' => $months,
    );
}

/**
 * Aliases (in case older code checks a different helper name).
 */
function bvmgr_get_schedule_window($venue_id = 0)
{
    return bvmgr_get_schedule_window_bounds($venue_id);
}
function bvmgr_get_schedule_window_range($venue_id = 0)
{
    return bvmgr_get_schedule_window_bounds($venue_id);
}
function bvmgr_get_schedule_window_start_end($venue_id = 0)
{
    return bvmgr_get_schedule_window_bounds($venue_id);
}

function bvmgr_render_schedule_day_cell(int $venue_id, string $ymd, bool $in_window): void
{
    $day_num = (int) substr($ymd, 8, 2);

    // TODO: replace these with your real helpers:
    $is_open = function_exists('bvmgr_venue_is_open_on_date') ? (bool) bvmgr_venue_is_open_on_date($venue_id, $ymd) : true;
    $plan_id = function_exists('vms_get_event_plan_id_by_date') ? (int) vms_get_event_plan_id_by_date($venue_id, $ymd) : 0;

    $classes = ['vms-sch-cell'];
    if (!$in_window) $classes[] = 'is-outside';
    if (!$is_open)   $classes[] = 'is-closed';
    if ($plan_id)    $classes[] = 'has-plan';

    echo '<div class="' . esc_attr(implode(' ', $classes)) . '">';
    echo '<div class="vms-sch-daynum">' . esc_html((string)$day_num) . '</div>';

    if (!$in_window) {
        echo '<div class="vms-sch-muted">Outside window</div>';
        echo '</div>';
        return;
    }

    if ($plan_id > 0) {
        $edit = get_edit_post_link($plan_id, ''); // assumes event plans are posts
        echo '<div class="vms-sch-badge">Plan</div>';
        if ($edit) {
            echo '<a class="button button-small" href="' . esc_url($edit) . '">Open</a>';
        }
    } else {
        // Even if closed, you can decide whether to allow "Create"
        $url = wp_nonce_url(
            admin_url('admin-post.php?action=vms_create_event_plan&date=' . rawurlencode($ymd)),
            'bvmgr_create_event_plan_' . $ymd
        );
        echo '<div class="vms-sch-badge ' . ($is_open ? 'is-open' : 'is-closed') . '">' . ($is_open ? 'Open' : 'Closed') . '</div>';
        echo '<a class="button button-small" href="' . esc_url($url) . '">Create</a>';
    }

    echo '</div>';
}
// ** DASHBOARD ** VENUE SELECTOR ONLY
function bvmgr_dash_render_venue_selector(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }

    // Include non-published venues so we can warn accurately when venues exist but none are Published.
    $venues_all = get_posts(array(
        'post_type'      => 'vms_venue',
        'post_status'    => array('publish', 'private', 'draft', 'pending'),
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'no_found_rows'  => true,
    ));

    if (empty($venues_all)) {
        echo '<div class="notice notice-warning"><p><strong>Backstage Venue Manager:</strong> No venues exist yet. Create one under Backstage Venue Manager → Venues.</p></div>';
        return;
    }

    // “Active” venues are those that can drive Schedule and dashboard views.
    // Draft venues are intentionally treated as NOT READY.
    $venues_active = array();
    foreach ($venues_all as $v) {
        $st = isset($v->post_status) ? (string) $v->post_status : '';
        if ($st === 'publish' || $st === 'private') {
            $venues_active[] = $v;
        }
    }

    if (empty($venues_active)) {
        $list_url = admin_url('edit.php?post_type=vms_venue');
        $only = (count($venues_all) === 1) ? $venues_all[0] : null;
        $only_id = ($only && isset($only->ID)) ? (int) $only->ID : 0;
        $only_title = ($only && isset($only->post_title)) ? (string) $only->post_title : '';
        $only_status = ($only && isset($only->post_status)) ? (string) $only->post_status : '';
        $only_status_label = $only_status !== '' ? ucfirst($only_status) : 'Draft';
        $edit_url = ($only_id > 0) ? get_edit_post_link($only_id, '') : '';

        echo '<div class="notice notice-error">';
        echo '<p><strong>Backstage Venue Manager:</strong> Action required — no Published venues are available.</p>';
        if ($only_id > 0) {
            echo '<p><strong>Your only venue is currently ' . esc_html($only_status_label) . ':</strong> ' . esc_html($only_title) . '</p>';
        } else {
            echo '<p><strong>Your venues exist, but none are Published.</strong></p>';
        }
        echo '<p>';
        if ($edit_url) {
            echo '<a class="button button-primary" href="' . esc_url($edit_url) . '">Open venue</a> ';
        }
        echo '<a class="button" href="' . esc_url($list_url) . '">View venues</a>';
        echo '</p>';
        echo '<p class="description">Publish at least one venue to enable Schedule, Season Dates, and dashboard venue views.</p>';
        echo '</div>';
        return;
    }

    $venues = $venues_active;
    $user_id = (int) get_current_user_id();

    // Persisted dashboard context (admin user meta).
    $raw_scope = sanitize_key((string) get_user_meta($user_id, '_vms_dash_scope', true));
    $has_scope_pref = ($raw_scope === 'venue' || $raw_scope === 'all');

    // Default to All Venues if the user has never set a preference.
    // This keeps the dashboard in a “global view” mode until the operator opts into a specific venue.
    $saved_scope = $has_scope_pref ? $raw_scope : 'all';

    $saved_vid = absint(get_user_meta($user_id, '_vms_dash_venue_id', true));

    // Default venue fallback (used if no saved venue, or saved venue is invalid).
    $default_vid = function_exists('bvmgr_get_default_venue_id')
        ? (int) bvmgr_get_default_venue_id()
        : 0;

    if ($default_vid <= 0 && isset($venues[0]->ID)) {
        $default_vid = (int) $venues[0]->ID;
    }

    // Validate saved venue against existing venues.
    $valid_vids = array();
    foreach ($venues as $v) {
        $valid_vids[(int) $v->ID] = true;
    }

    $initial_vid = ($saved_vid > 0 && isset($valid_vids[$saved_vid]))
        ? (int) $saved_vid
        : (int) $default_vid;

    $has_pref = ($has_scope_pref || $saved_vid > 0) ? '1' : '0';

    echo '<div class="vms-dash-selector" data-has-pref="' . esc_attr($has_pref) . '">';

    $action = esc_url(admin_url('admin-post.php'));
    $current_redirect = bvmgr_request_local_redirect(
        admin_url('admin.php?page=vms-dashboard'),
        bvmgr_request_current_uri()
    );
    echo '<form method="post" action="' . $action . '" class="vms-dash-selector__row" id="vms-dash-pref-form">';
    echo '<input type="hidden" name="action" value="vms_set_dashboard_venue">';
    wp_nonce_field('bvmgr_set_dashboard_venue', 'bvmgr_dash_venue_nonce');
    echo '<input type="hidden" name="redirect_to" value="' . esc_attr($current_redirect) . '">';

    echo '<label class="vms-dash-selector__label">Dashboard:</label>';

    echo '<select id="vms-dash-scope" name="dash_scope" class="vms-dash-selector__scope">';
    echo '<option value="venue"' . selected($saved_scope, 'venue', false) . '>This venue</option>';
    echo '<option value="all"' . selected($saved_scope, 'all', false) . '>All venues</option>';
    echo '</select>';

    echo '<select id="vms-dash-venue-select" name="dash_venue_id" class="vms-dash-selector__venue">';
    foreach ($venues as $v) {
        $vid = (int) $v->ID;
        echo '<option value="' . esc_attr((string) $vid) . '"' . selected($initial_vid, $vid, false) . '>';
        echo esc_html($v->post_title);
        echo '</option>';
    }
    echo '</select>';

    echo '</form>';
    echo '<p class="description vms-dash-selector__desc">Filters Today and This Week instantly (no page reload).</p>';
    echo '</div>';
}



// Legacy fallback assets.
// Canonical boot uses includes/core/plugin.php for asset loading.
if (!function_exists('bvmgr_core')) {
    add_action('admin_enqueue_scripts', function () {
        $page = bvmgr_request_read_key($_GET, 'page'); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Passive legacy admin asset page state only scopes read-only styling and remains nonce-free.
        if ($page === '' || strpos($page, 'vms') !== 0) {
            return;
        }

        bvmgr_enqueue_admin_style_stack();
    });

    add_action('wp_enqueue_scripts', function () {
        if (!is_page('vendor-portal')) {
            return;
        }

        bvmgr_enqueue_portal_style_stack();
    });
}

function bvmgr_map_plan_status(int $plan_id): string
{
    $wp_status = get_post_status($plan_id);

    if ($wp_status === 'draft') return 'draft';
    if ($wp_status === 'publish') return 'confirmed';

    // Use your real cancellation status if you have one.
    // If you store cancellation in meta, handle it here.
    // Example:
    // if (get_post_meta($plan_id, '_vms_cancelled', true)) return 'cancelled';

    return ''; // unknown / none
}

// Default Venue (global fallback)
if (!function_exists('bvmgr_get_default_venue_id')) {
    function bvmgr_get_default_venue_id(): int
    {
        $opts = (array) get_option('vms_settings', array());
        return isset($opts['default_venue_id']) ? (int) $opts['default_venue_id'] : 0;
    }
}

// Schedule "Current Venue" (user context)
// Keep legacy key, but treat as schedule-owned.
if (!defined('BVMGR_SCH_CURRENT_VENUE_META_KEY')) {
    define('BVMGR_SCH_CURRENT_VENUE_META_KEY', '_vms_current_venue_id');
}

if (!function_exists('bvmgr_sch_get_current_venue_id')) {
    function bvmgr_sch_get_current_venue_id(int $user_id = 0): int
    {
        $uid = $user_id > 0 ? $user_id : (int) get_current_user_id();
        if ($uid <= 0) {
            return 0;
        }
        return (int) get_user_meta($uid, BVMGR_SCH_CURRENT_VENUE_META_KEY, true);
    }
}

if (!function_exists('bvmgr_sch_set_current_venue_id')) {
    function bvmgr_sch_set_current_venue_id(int $venue_id, int $user_id = 0): void
    {
        $uid = $user_id > 0 ? $user_id : (int) get_current_user_id();
        if ($uid <= 0) {
            return;
        }
        update_user_meta($uid, BVMGR_SCH_CURRENT_VENUE_META_KEY, (int) $venue_id);
    }
}




// Schedule "Current Scope" (user context)
// Values: venue|all
if (!defined('BVMGR_SCH_CURRENT_SCOPE_META_KEY')) {
    define('BVMGR_SCH_CURRENT_SCOPE_META_KEY', '_vms_schedule_scope');
}

if (!function_exists('bvmgr_sch_get_current_scope')) {
    function bvmgr_sch_get_current_scope(int $user_id = 0): string
    {
        $uid = $user_id > 0 ? $user_id : (int) get_current_user_id();
        if ($uid <= 0) {
            return 'venue';
        }

        $raw = sanitize_key((string) get_user_meta($uid, BVMGR_SCH_CURRENT_SCOPE_META_KEY, true));
        return ($raw === 'all') ? 'all' : 'venue';
    }
}

if (!function_exists('bvmgr_sch_set_current_scope')) {
    function bvmgr_sch_set_current_scope(string $scope, int $user_id = 0): void
    {
        $uid = $user_id > 0 ? $user_id : (int) get_current_user_id();
        if ($uid <= 0) {
            return;
        }

        $s = sanitize_key($scope);
        $s = ($s === 'all') ? 'all' : 'venue';
        update_user_meta($uid, BVMGR_SCH_CURRENT_SCOPE_META_KEY, $s);
    }
}
// ======================================================
// Public Vendor Profiles (front-end)
// ======================================================

if (!function_exists('bvmgr_vendor_profile_base_slug')) {
    function bvmgr_vendor_profile_base_slug(): string
    {
        $base = defined('BVMGR_VENDOR_PROFILE_BASE_SLUG') ? (string) BVMGR_VENDOR_PROFILE_BASE_SLUG : 'vendor';
        $base = sanitize_title($base);
        $base = $base !== '' ? $base : 'vendor';

        /**
         * Filter: change the public vendor profile base slug.
         * Example: return 'performers' or 'talent'.
         */
        return (string) apply_filters('vms_vendor_profile_base_slug', $base);
    }
}

if (!function_exists('bvmgr_vendor_profile_url_by_slug')) {
    function bvmgr_vendor_profile_url_by_slug(string $vendor_slug): string
    {
        $slug = sanitize_title($vendor_slug);
        if ($slug === '') {
            return home_url('/');
        }

        $base = trim(bvmgr_vendor_profile_base_slug(), '/');
        return home_url('/' . $base . '/' . $slug . '/');
    }
}

if (!function_exists('bvmgr_vendor_profile_url')) {
    function bvmgr_vendor_profile_url(int $vendor_id): string
    {
        $vendor_id = (int) $vendor_id;
        if ($vendor_id <= 0) {
            return home_url('/');
        }
        $slug = get_post_field('post_name', $vendor_id);
        return bvmgr_vendor_profile_url_by_slug((string) $slug);
    }
}

if (!function_exists('bvmgr_vendor_profile_is_enabled')) {
    function bvmgr_vendor_profile_is_enabled(int $vendor_id): bool
    {
        $vendor_id = (int) $vendor_id;
        if ($vendor_id <= 0) {
            return false;
        }

        $key = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('vendor', 'public_profile_enabled') : '_vms_vendor_public_profile_enabled';
        $val = get_post_meta($vendor_id, $key, true);

        $enabled = ($val === '1' || $val === 1 || $val === true || $val === 'yes' || $val === 'on');
        if (!$enabled) {
            return false;
        }

        if (function_exists('bvmgr_vendor_has_public_profile_type') && !bvmgr_vendor_has_public_profile_type($vendor_id)) {
            return false;
        }

        return true;
    }
}


/**
 * Apply vendor (band) compensation defaults onto an Event Plan's Draft Pay fields.
 *
 * Used by the Event Plan editor "Apply Band Defaults" action.
 *
 * Notes:
 * - This only updates Draft Pay fields (structure + amounts).
 * - It does not lock pay. Locking remains an explicit operator action.
 */
function bvmgr_apply_band_comp_defaults_to_plan(int $event_plan_id): bool
{
	$band_id = (int) get_post_meta($event_plan_id, '_vms_band_vendor_id', true);
	if ($band_id <= 0) {
		return false;
	}

	$k_venue_id = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'venue_id') ?: '_vms_venue_id') : '_vms_venue_id';
	$venue_id = (int) get_post_meta($event_plan_id, $k_venue_id, true);
	$k_event_date = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'date') ?: '_vms_event_date') : '_vms_event_date';
	$event_date = (string) get_post_meta($event_plan_id, $k_event_date, true);
	$terms = bvmgr_get_vendor_default_comp_terms($band_id, $venue_id, $event_date);
	$k_commission_override_none = function_exists('bvmgr_meta_key')
		? (bvmgr_meta_key('event_plan', 'commission_override_none') ?: '_vms_commission_override_none')
		: '_vms_commission_override_none';
	$commission_override_none = ((string) get_post_meta($event_plan_id, $k_commission_override_none, true) === '1');
	if ($commission_override_none) {
		unset($terms['commission_percent'], $terms['commission_mode']);
	}

	if (empty($terms)) {
		$terms = array(
			'structure' => 'flat_fee',
		);
	}

	return bvmgr_event_plan_apply_comp_terms($event_plan_id, $terms);
}

/**
 * Apply band defaults only if Draft Pay is empty/unset.
 *
 * This is intentionally conservative to avoid overwriting operator edits.
 */
function bvmgr_maybe_apply_band_comp_defaults_to_plan(int $event_plan_id): bool
{
	$cur_structure = (string) get_post_meta($event_plan_id, '_vms_comp_structure', true);
	$cur_flat = get_post_meta($event_plan_id, '_vms_flat_fee_amount', true);
	$cur_split = get_post_meta($event_plan_id, '_vms_door_split_percent', true);
	$cur_bonus_mode = (string) get_post_meta($event_plan_id, '_vms_attendance_bonus_mode', true);
	$cur_bonus_start = get_post_meta($event_plan_id, '_vms_attendance_bonus_start_count', true);
	$cur_bonus_step_size = get_post_meta($event_plan_id, '_vms_attendance_bonus_step_size', true);
	$cur_bonus_step_bonus = get_post_meta($event_plan_id, '_vms_attendance_bonus_step_bonus', true);
	$cur_bonus_per_ticket = get_post_meta($event_plan_id, '_vms_attendance_bonus_per_ticket_rate', true);
	$cur_bonus_max = get_post_meta($event_plan_id, '_vms_attendance_bonus_max_bonus', true);
	$cur_commission_percent = get_post_meta($event_plan_id, '_vms_commission_percent', true);

	$has_any = false;
	if ($cur_structure !== '') $has_any = true;
	if (is_numeric($cur_flat) && (float) $cur_flat > 0) $has_any = true;
	if (is_numeric($cur_split) && (float) $cur_split > 0) $has_any = true;
	if ($cur_bonus_mode !== '') $has_any = true;
	if (is_numeric($cur_bonus_start) && (int) $cur_bonus_start >= 0) $has_any = true;
	if (is_numeric($cur_bonus_step_size) && (int) $cur_bonus_step_size > 0) $has_any = true;
	if (is_numeric($cur_bonus_step_bonus) && (float) $cur_bonus_step_bonus >= 0) $has_any = true;
	if (is_numeric($cur_bonus_per_ticket) && (float) $cur_bonus_per_ticket >= 0) $has_any = true;
	if (is_numeric($cur_bonus_max) && (float) $cur_bonus_max >= 0) $has_any = true;
	if (is_numeric($cur_commission_percent) && (float) $cur_commission_percent > 0) $has_any = true;

	if ($has_any) {
		return false;
	}

	return bvmgr_apply_band_comp_defaults_to_plan($event_plan_id);
}

/**
 * Resolve an Event Plan meta key while keeping standalone/test loads safe.
 */
function bvmgr_event_plan_ticketing_meta_key(string $name, string $fallback): string
{
	if (function_exists('bvmgr_meta_key')) {
		$key = (string) bvmgr_meta_key('event_plan', $name);
		if ($key !== '') {
			return $key;
		}
	}

	return $fallback;
}

/**
 * Normalize the public ticket-sales mode. Missing and unknown legacy values are native.
 */
function bvmgr_event_plan_normalize_ticketing_sales_mode($value): string
{
	if (!is_scalar($value)) {
		return 'serenade_range';
	}
	$value = function_exists('sanitize_key') ? sanitize_key((string) $value) : strtolower(trim((string) $value));
	return $value === 'external' ? 'external' : 'serenade_range';
}

function bvmgr_event_plan_get_ticketing_sales_mode(int $event_plan_id): string
{
	$key = bvmgr_event_plan_ticketing_meta_key('ticketing_sales_mode', '_vms_ticketing_sales_mode');
	return bvmgr_event_plan_normalize_ticketing_sales_mode(get_post_meta($event_plan_id, $key, true));
}

function bvmgr_event_plan_is_externally_ticketed(int $event_plan_id): bool
{
	return $event_plan_id > 0 && bvmgr_event_plan_get_ticketing_sales_mode($event_plan_id) === 'external';
}

/**
 * Sanitize an absolute public HTTP(S) URL used by an Event Plan.
 */
function bvmgr_event_plan_sanitize_http_url($value): string
{
	if (!is_scalar($value)) {
		return '';
	}
	$value = trim((string) $value);
	if ($value === '') {
		return '';
	}

	$url = function_exists('esc_url_raw') ? (string) esc_url_raw($value, array('http', 'https')) : (string) filter_var($value, FILTER_SANITIZE_URL);
	$parts = function_exists('wp_parse_url') ? wp_parse_url($url) : parse_url($url); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Supported runtime always provides wp_parse_url(); this fallback keeps the pure helper fail-closed outside WordPress.
	if (!is_array($parts)) {
		return '';
	}

	$scheme = strtolower((string) ($parts['scheme'] ?? ''));
	$host = trim((string) ($parts['host'] ?? ''));
	if (!in_array($scheme, array('http', 'https'), true) || $host === '') {
		return '';
	}

	return $url;
}

/**
 * Sanitize an external ticket destination. Only absolute HTTP(S) URLs are accepted.
 */
function bvmgr_event_plan_sanitize_external_ticket_url($value): string
{
	return bvmgr_event_plan_sanitize_http_url($value);
}

function bvmgr_event_plan_get_external_ticket_url(int $event_plan_id): string
{
	$key = bvmgr_event_plan_ticketing_meta_key('external_ticket_url', '_vms_external_ticket_url');
	return bvmgr_event_plan_sanitize_external_ticket_url(get_post_meta($event_plan_id, $key, true));
}

function bvmgr_event_plan_get_external_ticket_provider(int $event_plan_id, bool $with_fallback = false): string
{
	$key = bvmgr_event_plan_ticketing_meta_key('external_ticket_provider', '_vms_external_ticket_provider');
	$raw_label = get_post_meta($event_plan_id, $key, true);
	$label = is_scalar($raw_label) ? trim((string) $raw_label) : '';
	if ($label !== '') {
		return function_exists('sanitize_text_field') ? sanitize_text_field($label) : strip_tags($label); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Supported runtime always provides sanitize_text_field(); this fallback keeps the pure helper fail-closed outside WordPress.
	}

	return $with_fallback ? (string) __('external ticket provider', 'backstage-venue-manager') : '';
}

function bvmgr_event_plan_normalize_relationship($value): string
{
	if (!is_scalar($value)) {
		return 'serenade_range_produced';
	}
	$value = function_exists('sanitize_key') ? sanitize_key((string) $value) : strtolower(trim((string) $value));
	return $value === 'hosted_third_party' ? 'hosted_third_party' : 'serenade_range_produced';
}

function bvmgr_event_plan_get_relationship(int $event_plan_id): string
{
	$key = bvmgr_event_plan_ticketing_meta_key('event_relationship', '_vms_event_relationship');
	return bvmgr_event_plan_normalize_relationship(get_post_meta($event_plan_id, $key, true));
}

function bvmgr_event_plan_is_hosted_third_party(int $event_plan_id): bool
{
	return $event_plan_id > 0 && bvmgr_event_plan_get_relationship($event_plan_id) === 'hosted_third_party';
}

function bvmgr_event_plan_get_external_event_producer(int $event_plan_id): string
{
	$key = bvmgr_event_plan_ticketing_meta_key('external_event_producer', '_vms_external_event_producer');
	$raw_label = get_post_meta($event_plan_id, $key, true);
	$label = is_scalar($raw_label) ? trim((string) $raw_label) : '';
	return function_exists('sanitize_text_field') ? sanitize_text_field($label) : strip_tags($label); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Supported runtime always provides sanitize_text_field(); this fallback keeps the pure helper fail-closed outside WordPress.
}

function bvmgr_event_plan_sanitize_external_event_producer_website($value): string
{
	return bvmgr_event_plan_sanitize_http_url($value);
}

function bvmgr_event_plan_get_external_event_producer_website(int $event_plan_id): string
{
	$key = bvmgr_event_plan_ticketing_meta_key('external_event_producer_website', '_vms_external_event_producer_website');
	return bvmgr_event_plan_sanitize_external_event_producer_website(get_post_meta($event_plan_id, $key, true));
}

/**
 * Return the canonical public purchase destination for the current context.
 * The caller supplies its unchanged native destination; external mode overrides it.
 *
 * @return array{mode:string,is_external:bool,url:string,provider:string,relationship:string,producer:string,producer_website:string}
 */
function bvmgr_event_plan_get_ticket_destination(int $event_plan_id, string $native_url = ''): array
{
	$is_external = bvmgr_event_plan_is_externally_ticketed($event_plan_id);
	$url = $is_external ? bvmgr_event_plan_get_external_ticket_url($event_plan_id) : trim($native_url);
	if ($url !== '' && function_exists('esc_url_raw')) {
		$url = (string) esc_url_raw($url, array('http', 'https'));
	}

	return array(
		'mode' => $is_external ? 'external' : 'serenade_range',
		'is_external' => $is_external,
		'url' => $url,
		'provider' => $is_external ? bvmgr_event_plan_get_external_ticket_provider($event_plan_id, true) : '',
		'relationship' => bvmgr_event_plan_get_relationship($event_plan_id),
		'producer' => bvmgr_event_plan_get_external_event_producer($event_plan_id),
		'producer_website' => bvmgr_event_plan_get_external_event_producer_website($event_plan_id),
	);
}

/**
 * Detect preserved native definitions/product mappings for the admin mode warning.
 */
function bvmgr_event_plan_has_native_ticket_records(int $event_plan_id): bool
{
	$keys = array(
		'_vms_wc_product_map',
		'_vms_ticket_product_ids_v1',
		'_vms_ticket_manual_product_ids_v1',
		'_vms_ticket_tier_map_v1',
		'_vms_ticketing_config_v2',
		'_vms_ticketing_sync_v2',
	);

	foreach ($keys as $key) {
		$value = get_post_meta($event_plan_id, $key, true);
		if (is_array($value) && !empty($value)) {
			return true;
		}
		if (!is_array($value) && $value !== '' && $value !== null && $value !== false) {
			return true;
		}
	}

	return false;
}

/**
 * Compute effective native Serenade Range ticketing state for an Event Plan.
 * External mode always disables new native purchasing without deleting its records.
 *
 * Priority for native mode:
 * 1) Per-event override (_vms_ticketing_enabled_override): on|off
 * 2) Global operator default (vms_settings[ticketing_enabled_default])
 */
function bvmgr_event_plan_is_ticketing_enabled(int $event_plan_id): bool
{
	if (bvmgr_event_plan_is_externally_ticketed($event_plan_id)) {
		return false;
	}

	$override = (string) get_post_meta($event_plan_id, '_vms_ticketing_enabled_override', true);
	if ($override === 'on') return true;
	if ($override === 'off') return false;

	$settings = (array) get_option('vms_settings', array());
	return !empty($settings['ticketing_enabled_default']);
}

function bvmgr_event_plan_native_ticket_purchasing_allowed(int $event_plan_id): bool
{
	return $event_plan_id > 0 && bvmgr_event_plan_is_ticketing_enabled($event_plan_id);
}


if (!function_exists('bvmgr_ticketing_ui_help_default_text')) {
	function bvmgr_ticketing_ui_help_default_text(string $section): string
	{
		$section = sanitize_key($section);
		if ($section === 'addons') {
			return (string) __('Fire pits, tables, and other extras are optional. Add one only if you want to reserve that amenity for your group.', 'backstage-venue-manager');
		}

		return '';
	}
}

if (!function_exists('bvmgr_ticketing_ui_addons_section_heading_default')) {
	function bvmgr_ticketing_ui_addons_section_heading_default(): string
	{
		return (string) __('Fire Pits & Tables', 'backstage-venue-manager');
	}
}

if (!function_exists('bvmgr_ticketing_ui_addons_section_subtext_default')) {
	function bvmgr_ticketing_ui_addons_section_subtext_default(): string
	{
		return (string) __('Click here to add a fire pit or table to your order.', 'backstage-venue-manager');
	}
}

if (!function_exists('bvmgr_ticketing_ui_addons_section_heading')) {
	function bvmgr_ticketing_ui_addons_section_heading(): string
	{
		$settings = (array) get_option('vms_settings', array());
		$value = isset($settings['ticket_ui_addons_heading']) ? trim(html_entity_decode((string) $settings['ticket_ui_addons_heading'], ENT_QUOTES | ENT_HTML5, 'UTF-8')) : '';
		return $value !== '' ? $value : bvmgr_ticketing_ui_addons_section_heading_default();
	}
}

if (!function_exists('bvmgr_ticketing_ui_addons_section_subtext')) {
	function bvmgr_ticketing_ui_addons_section_subtext(): string
	{
		$settings = (array) get_option('vms_settings', array());
		$value = isset($settings['ticket_ui_addons_subtext']) ? trim(html_entity_decode((string) $settings['ticket_ui_addons_subtext'], ENT_QUOTES | ENT_HTML5, 'UTF-8')) : '';
		return $value !== '' ? $value : bvmgr_ticketing_ui_addons_section_subtext_default();
	}
}

if (!function_exists('bvmgr_ticketing_ui_addons_section_heading_effective')) {
	function bvmgr_ticketing_ui_addons_section_heading_effective(int $event_plan_id): string
	{
		$event_plan_id = absint($event_plan_id);
		if ($event_plan_id > 0) {
			$override = trim(html_entity_decode((string) get_post_meta($event_plan_id, '_vms_ticket_ui_addons_heading_override', true), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
			if ($override !== '') {
				return $override;
			}
		}

		return bvmgr_ticketing_ui_addons_section_heading();
	}
}

if (!function_exists('bvmgr_ticketing_ui_addons_section_subtext_effective')) {
	function bvmgr_ticketing_ui_addons_section_subtext_effective(int $event_plan_id): string
	{
		$event_plan_id = absint($event_plan_id);
		if ($event_plan_id > 0) {
			$override = trim(html_entity_decode((string) get_post_meta($event_plan_id, '_vms_ticket_ui_addons_subtext_override', true), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
			if ($override !== '') {
				return $override;
			}
		}

		return bvmgr_ticketing_ui_addons_section_subtext();
	}
}

if (!function_exists('bvmgr_ticketing_ui_help_is_legacy_ticket_copy')) {
	function bvmgr_ticketing_ui_help_is_legacy_ticket_copy(string $value): bool
	{
		$plain = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($value)) ?: '');
		$legacy_values = array(
			'Step 1: Select General Admission tickets for each person attending. Step 2: If you want a fire pit or reserved add-on, add it separately below.',
			'Choose the ticket type for each guest. Do not add a General Admission ticket for someone using a free or qualified ticket—just pick the ticket type that applies to that person.',
			'Choose the ticket type for each guest. Do not add a General Admission ticket for someone using a free or qualified ticket--just pick the ticket type that applies to that person.',
		);
		foreach ($legacy_values as $legacy) {
			if (strcasecmp($plain, $legacy) === 0) {
				return true;
			}
		}
		return false;
	}
}

if (!function_exists('bvmgr_ticketing_ui_help_settings_key')) {
	function bvmgr_ticketing_ui_help_settings_key(string $section): string
	{
		$section = sanitize_key($section);
		return ($section === 'addons')
			? 'ticket_ui_help_addons_default'
			: 'ticket_ui_help_tickets_default';
	}
}

if (!function_exists('bvmgr_ticketing_ui_help_meta_key')) {
	function bvmgr_ticketing_ui_help_meta_key(string $section): string
	{
		$section = sanitize_key($section);
		return ($section === 'addons')
			? '_vms_ticket_ui_help_addons_override'
			: '_vms_ticket_ui_help_tickets_override';
	}
}

if (!function_exists('bvmgr_ticketing_ui_help_global_text')) {
	function bvmgr_ticketing_ui_help_global_text(string $section): string
	{
		$settings = (array) get_option('vms_settings', array());
		$key = bvmgr_ticketing_ui_help_settings_key($section);
		$value = isset($settings[$key]) ? trim((string) $settings[$key]) : '';
		if ($value !== '') {
			if ($section === 'tickets' && function_exists('bvmgr_ticketing_ui_help_is_legacy_ticket_copy') && bvmgr_ticketing_ui_help_is_legacy_ticket_copy($value)) {
				return wpautop(esc_html(bvmgr_ticketing_ui_help_default_text($section)));
			}
			return wp_kses_post($value);
		}
		return wpautop(esc_html(bvmgr_ticketing_ui_help_default_text($section)));
	}
}

if (!function_exists('bvmgr_ticketing_ui_help_effective_text')) {
	function bvmgr_ticketing_ui_help_effective_text(int $event_plan_id, string $section): string
	{
		$event_plan_id = absint($event_plan_id);
		if ($event_plan_id > 0) {
			$meta_key = bvmgr_ticketing_ui_help_meta_key($section);
			$override = trim((string) get_post_meta($event_plan_id, $meta_key, true));
			if ($override !== '') {
				if ($section === 'tickets' && function_exists('bvmgr_ticketing_ui_help_is_legacy_ticket_copy') && bvmgr_ticketing_ui_help_is_legacy_ticket_copy($override)) {
					return wpautop(esc_html(bvmgr_ticketing_ui_help_default_text($section)));
				}
				return wp_kses_post($override);
			}
		}

		return bvmgr_ticketing_ui_help_global_text($section);
	}
}

if (!function_exists('bvmgr_ticketing_ui_help_has_event_override')) {
	function bvmgr_ticketing_ui_help_has_event_override(int $event_plan_id, string $section): bool
	{
		$event_plan_id = absint($event_plan_id);
		if ($event_plan_id <= 0) {
			return false;
		}

		$meta_key = bvmgr_ticketing_ui_help_meta_key($section);
		return trim((string) get_post_meta($event_plan_id, $meta_key, true)) !== '';
	}
}

if (!function_exists('bvmgr_ticketing_ui_help_should_render')) {
	function bvmgr_ticketing_ui_help_should_render(int $event_plan_id, string $section): bool
	{
		if (function_exists('bvmgr_ticketing_ui_help_has_event_override') && bvmgr_ticketing_ui_help_has_event_override($event_plan_id, $section)) {
			return true;
		}

		return function_exists('bvmgr_ticketing_ui_help_global_enabled') ? bvmgr_ticketing_ui_help_global_enabled($section) : true;
	}
}

if (!function_exists('bvmgr_ticketing_ui_help_global_enabled')) {
	function bvmgr_ticketing_ui_help_global_enabled(string $section): bool
	{
		$section = sanitize_key($section);
		$settings = (array) get_option('vms_settings', array());
		$key = ($section === 'addons') ? 'ticket_ui_help_addons_enabled' : 'ticket_ui_help_tickets_enabled';
		if (!array_key_exists($key, $settings)) {
			return true;
		}
		return !empty($settings[$key]);
	}
}

if (!function_exists('bvmgr_ticketing_ui_help_global_style')) {
	function bvmgr_ticketing_ui_help_global_style(string $section): array
	{
		return array();
	}
}

/**
 * Canonical internal status for an Event Plan.
 *
 * Uses core status semantics when available, with a safe fallback for older loads.
 */
if (!function_exists('bvmgr_event_plan_current_internal_status')) {
	function bvmgr_event_plan_current_internal_status(int $event_plan_id, string $context = 'financial'): string
	{
		$event_plan_id = absint($event_plan_id);
		if ($event_plan_id <= 0) {
			return 'draft';
		}

		if (function_exists('bvmgr_event_plan_get_status')) {
			$status = (string) bvmgr_event_plan_get_status($event_plan_id, $context);
			$status = sanitize_key($status);
			if ($status !== '') {
				return $status;
			}
		}

		$k_status = function_exists('bvmgr_meta_key')
			? (string) bvmgr_meta_key('event_plan', 'status')
			: '_vms_event_plan_status';
		$status = sanitize_key((string) get_post_meta($event_plan_id, $k_status, true));
		return $status !== '' ? $status : 'draft';
	}
}

/**
 * Auto-money actions are allowed only on explicit publish paths.
 *
 * Draft/Ready save flows must never trigger automated money-impacting product writes.
 */
if (!function_exists('bvmgr_event_plan_ticketing_auto_money_allowed')) {
	function bvmgr_event_plan_ticketing_auto_money_allowed(int $event_plan_id, string $requested_action, string $current_status = ''): bool
	{
		$requested_action = sanitize_key($requested_action);
		if ($requested_action !== 'publish_now') {
			return false;
		}

		$status = sanitize_key($current_status);
		if ($status === '') {
			$status = function_exists('bvmgr_event_plan_current_internal_status')
				? bvmgr_event_plan_current_internal_status($event_plan_id, 'financial')
				: 'draft';
		}

		return in_array($status, array('ready', 'published'), true);
	}
}
