<?php
if (!defined('ABSPATH')) exit;

/**
 * Find the Event Plan associated with a TEC event.
 *
 * @param int $tec_event_id tribe_events post ID
 * @return int|null Event Plan ID or null if none
 */

function vms_label(string $key, string $default): string
{
    $opts = (array) get_option('vms_settings', array());
    $val  = isset($opts["label_$key"]) ? trim((string)$opts["label_$key"]) : '';
    return ($val !== '') ? $val : $default;
}

function vms_ui_text(string $key, string $default): string
{
    $opts = (array) get_option('vms_settings', array());
    $val  = isset($opts["ui_$key"]) ? trim((string)$opts["ui_$key"]) : '';
    return ($val !== '') ? $val : $default;
}

function vms_get_event_plan_for_tec_event($tec_event_id)
{
    $tec_event_id = (int) $tec_event_id;
    if (!$tec_event_id) {
        return null;
    }

    $plans = get_posts(array(
        'post_type'      => 'vms_event_plan',
        'posts_per_page' => 1,
        'post_status'    => array('publish', 'draft', 'pending'),
        'meta_query'     => array(
            array(
                'key'   => '_vms_tec_event_id',
                'value' => $tec_event_id,
            ),
        ),
        'fields'         => 'ids',
    ));

    if (empty($plans)) {
        return null;
    }

    return (int) $plans[0];
}

/**
 * Get Woo ticket product IDs for a TEC event.
 *
 * @param int $event_id
 * @return int[]
 */
function vms_get_ticket_product_ids_for_event($event_id)
{
    $event_id = (int) $event_id;
    if (!$event_id) {
        return array();
    }

    $tickets = get_posts(array(
        'post_type'      => 'product',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
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

function vms_vendor_app_redirect($app_id, $result)
{
    $url = add_query_arg(array(
        'post'   => $app_id,
        'action' => 'edit',
        'vms_app_result' => $result,
    ), admin_url('post.php'));

    wp_safe_redirect($url);
    exit;
}

add_filter('get_avatar_url', 'vms_vendor_avatar_from_logo', 10, 3);
function vms_vendor_avatar_from_logo($url, $id_or_email, $args)
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
    $vendor_id = (int) get_user_meta($user->ID, '_vms_vendor_id', true);
    if (!$vendor_id) return $url;

    $thumb_id = get_post_thumbnail_id($vendor_id);
    if (!$thumb_id) return $url;

    $custom = wp_get_attachment_image_url($thumb_id, 'thumbnail');
    return $custom ? $custom : $url;
}

function vms_get_timezone_id(): string
{
    $opts = (array) get_option('vms_settings', array());
    $tz = isset($opts['timezone']) ? trim((string)$opts['timezone']) : '';

    if ($tz !== '') return $tz;

    $wp_tz = (string) get_option('timezone_string');
    if ($wp_tz !== '') return $wp_tz;

    return 'UTC';
}

function vms_get_timezone(): DateTimeZone
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

function vms_get_event_titles_by_date(array $active_dates): array
{
    $active_dates = array_values(array_filter(array_map('trim', $active_dates)));
    if (!$active_dates) return array();

    $active_set = array_fill_keys($active_dates, true);
    $map = array();
    $tz  = vms_get_timezone();

    $q = new WP_Query(array(
        'post_type'      => 'tribe_events',
        'post_status'    => array('publish', 'draft', 'pending'),
        'posts_per_page' => -1,
        'fields'         => 'ids',
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

/** VENDOR COMP PACKAGES
 * -------------------------------------------------
 * Functions for vendor comp packages feature
 * -------------------------------------------------
 */

/**
 * Fetch comp packages for a venue (and optionally global packages).
 */
function vms_get_comp_packages_for_venue(int $venue_id, bool $include_global = true): array
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
        'meta_query'     => $meta_query,
    ));
}
/**
 * Apply a comp package to an event plan AND write a snapshot (so packages can change later
 * without altering already-agreed plans unless you re-apply).
 */
function vms_apply_comp_package_to_plan(int $plan_id, int $package_id): bool
{
    if ($package_id <= 0) return false;

    $pkg = get_post($package_id);
    if (!$pkg || $pkg->post_type !== 'vms_comp_package') return false;

    // Package meta (define these keys in your packages UI)
    $structure = (string) get_post_meta($package_id, '_vms_comp_structure', true);          // flat_fee | door_split | flat_fee_door_split
    $flat      = get_post_meta($package_id, '_vms_flat_fee_amount', true);                 // numeric
    $split     = get_post_meta($package_id, '_vms_door_split_percent', true);              // numeric
    $commission_pct  = get_post_meta($package_id, '_vms_commission_percent', true);        // numeric (ex: 15)
    $commission_mode = (string) get_post_meta($package_id, '_vms_commission_mode', true);  // artist_fee | gross (optional)

    // Reasonable defaults
    if ($structure === '') $structure = 'flat_fee';
    if ($commission_mode === '') $commission_mode = 'artist_fee';

    // Apply to plan (these are the editable fields you already have on the plan)
    update_post_meta($plan_id, '_vms_comp_structure', sanitize_text_field($structure));

    // Only write numeric meta if package has a value (lets you build sparse packages)
    if ($flat !== '' && $flat !== null) {
        update_post_meta($plan_id, '_vms_flat_fee_amount', (float) $flat);
    }

    if ($split !== '' && $split !== null) {
        update_post_meta($plan_id, '_vms_door_split_percent', (float) $split);
    }

    if ($commission_pct !== '' && $commission_pct !== null) {
        update_post_meta($plan_id, '_vms_commission_percent', (float) $commission_pct);
    }

    update_post_meta($plan_id, '_vms_commission_mode', sanitize_text_field($commission_mode));

    // Store selected package id on the plan
    update_post_meta($plan_id, '_vms_comp_package_id', $package_id);

    // Snapshot (this is the “source of truth” for what was agreed when applied)
    $snapshot = array(
        'package_id'         => $package_id,
        'package_title'      => (string) get_the_title($package_id),
        'applied_at'         => current_time('mysql'),
        'structure'          => $structure,
        'flat_fee_amount'    => ($flat !== '' && $flat !== null) ? (float)$flat : null,
        'door_split_percent' => ($split !== '' && $split !== null) ? (float)$split : null,
        'commission_percent' => ($commission_pct !== '' && $commission_pct !== null) ? (float)$commission_pct : null,
        'commission_mode'    => $commission_mode,

        // NEW:
        'source'             => 'comp_package', // optional but nice
        'comp_hash'          => function_exists('vms_comp_hash_for_plan') ? vms_comp_hash_for_plan((int)$plan_id) : '',
    );

    update_post_meta($plan_id, '_vms_comp_snapshot', $snapshot);
    delete_post_meta($plan_id, '_vms_comp_needs_snapshot');

    return true;
}

function vms_comp_hash_for_plan(int $plan_id): string
{
    $structure = (string) get_post_meta($plan_id, '_vms_comp_structure', true);
    $flat      = get_post_meta($plan_id, '_vms_flat_fee_amount', true);
    $split     = get_post_meta($plan_id, '_vms_door_split_percent', true);

    // normalize
    $structure = $structure ?: 'flat_fee';
    $flat = ($flat === '' || $flat === null) ? '' : number_format((float)$flat, 2, '.', '');
    $split = ($split === '' || $split === null) ? '' : rtrim(rtrim((string)(float)$split, '0'), '.');

    return hash('sha256', wp_json_encode([
        'structure' => $structure,
        'flat'      => $flat,
        'split'     => $split,
    ]));
}

function vms_render_collapsible_panel(string $title, callable $render_cb, array $args = []): void
{
    $open    = !empty($args['open']);
    $accent  = isset($args['accent']) ? (string)$args['accent'] : '#4f46e5';
    $desc    = isset($args['desc']) ? (string)$args['desc'] : '';
    $classes = isset($args['class']) ? (string)$args['class'] : '';

    echo '<style>
.vms-panel{border-radius:14px;border:1px solid #e5e5e5;background:#fff;margin:12px 0;}
.vms-panel>summary{list-style:none;cursor:pointer;font-weight:700;font-size:15px;padding:10px 12px;border-radius:14px;}
.vms-panel>summary::-webkit-details-marker{display:none;}
.vms-panel-body{padding:12px 12px 14px;}
.vms-panel-accent{border-left:6px solid var(--vms-accent,#4f46e5);}
</style>';

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
function vms_is_vendor_tax_profile_complete(int $vendor_id): bool
{

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
        if ($val === '') {
            return false;
        }
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
function vms_vendor_tax_profile_missing_items(int $vendor_id): array
{
    $missing = [];

    $legal = trim((string) get_post_meta($vendor_id, '_vms_payee_legal_name', true));
    $entity = trim((string) get_post_meta($vendor_id, '_vms_entity_type', true));

    $addr1 = trim((string) get_post_meta($vendor_id, '_vms_addr1', true));
    $city  = trim((string) get_post_meta($vendor_id, '_vms_city', true));
    $state = trim((string) get_post_meta($vendor_id, '_vms_state', true));
    $zip   = trim((string) get_post_meta($vendor_id, '_vms_zip', true));

    $w9_upload_id = (int) get_post_meta($vendor_id, '_vms_w9_upload_id', true);

    if ($legal === '') $missing[] = 'Legal/Payee Name';
    if ($entity === '') $missing[] = 'Entity Type';

    // Address
    if ($addr1 === '') $missing[] = 'Mailing Address (line 1)';
    if ($city === '')  $missing[] = 'Mailing Address (city)';
    if ($state === '') $missing[] = 'Mailing Address (state)';
    if ($zip === '')   $missing[] = 'Mailing Address (ZIP)';

    // W-9 upload required (per your “no SSN/EIN typed into site” rule)
    if ($w9_upload_id <= 0) $missing[] = 'Signed W-9 Upload';

    return $missing;
}

/**
 * Venue default time helpers
 */

function vms_get_venue_default_times(int $venue_id): array
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
function vms_time_add_minutes(string $hhmm, int $minutes): string
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
 *        'rules' => [ 'vendor' => […], 'bar' => […], … ] // future
 *     ],
 *   ],
 * ]
 */
function vms_get_holidays_option(): array
{
    $raw = get_option('vms_holidays', array());
    return is_array($raw) ? $raw : array();
}

function vms_get_venue_holidays(int $venue_id): array
{
    if ($venue_id <= 0) return array();

    $all = vms_get_holidays_option();
    if (!isset($all[$venue_id]) || !is_array($all[$venue_id])) return array();

    return $all[$venue_id];
}

/**
 * Returns holiday array or null.
 */
function vms_get_venue_holiday_for_date(int $venue_id, string $date_yyyy_mm_dd): ?array
{
    if ($venue_id <= 0) return null;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_yyyy_mm_dd)) return null;

    $holidays = vms_get_venue_holidays($venue_id);
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

function vms_is_venue_closed_on_date(int $venue_id, string $date_yyyy_mm_dd): bool
{
    $h = vms_get_venue_holiday_for_date($venue_id, $date_yyyy_mm_dd);
    return ($h && $h['status'] === 'closed');
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
function vms_admin_scroll_to_compensation(int $post_id): void
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

function vms_snapshot_summary_line(array $snap): string
{
    $parts = [];

    if (!empty($snap['structure'])) {
        $parts[] = 'Structure: ' . vms_pretty_structure_label($snap['structure']);
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
        $parts[] = 'Commission: ' . $pct . '% (' . $mode . ')';
    }

    return implode(' | ', $parts);
}

function vms_format_snapshot_datetime($value): string
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

function vms_required_public_pages(): array
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
    ];
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
function vms_get_active_dates_for_venue(int $venue_id): array
{
    // Delegate to the season-rule engine (venue rules if present, otherwise global fallback).
    if (function_exists('vms_get_active_season_dates')) {
        $dates = vms_get_active_season_dates($venue_id);
        return is_array($dates) ? $dates : array();
    }

    return array();
}


if (!function_exists('vms_pretty_structure_label')) {
    function vms_pretty_structure_label($s): string
    {
        $s = (string) $s;

        // If you ever change meta keys later, this keeps output decent.
        return match ($s) {
            'flat_fee'            => 'Flat Fee',
            'door_split'          => 'Door Split',
            'flat_fee_door_split' => 'Flat Fee + Door Split',
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
function vms_vendor_mark_profile_updated($vendor_id, $user_id = 0, $changed_fields = array(), $context = '')
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
function vms_vendor_diff_meta_keys($vendor_id, array $keys, array $new_values_by_key) {
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

function vms_vendor_mark_profile_reviewed($vendor_id, $user_id = null)
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

function vms_vendor_profile_needs_review($vendor_id)
{
    $flag = get_post_meta((int) $vendor_id, '_vms_vendor_profile_needs_review', true);
    return ($flag === '1');
}

/**
 * Admin: Add “Updates” column to Vendors list
 */
add_filter('manage_vms_vendor_posts_columns', 'vms_vendor_add_updates_column');
function vms_vendor_add_updates_column($columns)
{
    $new = array();

    foreach ($columns as $key => $label) {
        // Put it before date if possible
        if ($key === 'date') {
            $new['vms_vendor_updates'] = __('Updates', 'vms');
        }
        $new[$key] = $label;
    }

    if (!isset($new['vms_vendor_updates'])) {
        $new['vms_vendor_updates'] = __('Updates', 'vms');
    }

    return $new;
}

add_action('manage_vms_vendor_posts_custom_column', 'vms_vendor_render_updates_column', 10, 2);
function vms_vendor_render_updates_column($column, $post_id)
{
    if ($column !== 'vms_vendor_updates') return;

    $needs = vms_vendor_profile_needs_review($post_id);

    $last_gmt = get_post_meta($post_id, '_vms_vendor_profile_last_updated_gmt', true);
    $time_ago = '';

    if ($last_gmt) {
        $ts = strtotime($last_gmt . ' GMT');
        if ($ts) {
            $time_ago = human_time_diff($ts, time()) . ' ago';
        }
    }

    if ($needs) {
        echo '<span style="display:inline-block;padding:2px 8px;border-radius:999px;background:#fee2e2;color:#991b1b;font-weight:700;font-size:11px;letter-spacing:.02em;text-transform:uppercase;">Needs review</span>';
        if ($time_ago) {
            echo '<div style="margin-top:4px;font-size:12px;opacity:.85;">' . esc_html($time_ago) . '</div>';
        }
    } else {
        echo '<span style="display:inline-block;padding:2px 8px;border-radius:999px;background:#e5e7eb;color:#374151;font-weight:700;font-size:11px;letter-spacing:.02em;text-transform:uppercase;">OK</span>';
        if ($time_ago) {
            echo '<div style="margin-top:4px;font-size:12px;opacity:.85;">Last update: ' . esc_html($time_ago) . '</div>';
        }
    }
}

/**
 * Admin: Metabox on vendor edit screen to show status + “Mark Reviewed”
 */
add_action('add_meta_boxes', 'vms_vendor_add_change_tracking_metabox');
function vms_vendor_add_change_tracking_metabox()
{
    add_meta_box(
        'vms_vendor_change_tracking',
        __('Vendor Updates', 'vms'),
        'vms_vendor_change_tracking_metabox_cb',
        'vms_vendor',
        'side',
        'high'
    );
}

function vms_vendor_change_tracking_metabox_cb($post)
{
    $vendor_id = (int) $post->ID;

    $needs = vms_vendor_profile_needs_review($vendor_id);

    $updated_gmt = get_post_meta($vendor_id, '_vms_vendor_profile_last_updated_gmt', true);
    $updated_by  = (int) get_post_meta($vendor_id, '_vms_vendor_profile_last_updated_by', true);

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
        $nonce = wp_create_nonce('vms_vendor_mark_reviewed_' . $vendor_id);
        $url = admin_url('admin-post.php?action=vms_vendor_mark_reviewed&vendor_id=' . $vendor_id . '&_wpnonce=' . $nonce);

        echo '<p style="margin:0;"><a class="button button-primary" href="' . esc_url($url) . '">Mark as Reviewed</a></p>';
    }

    echo '</div>';
}

/**
 * Admin handler: mark reviewed
 */
add_action('admin_post_vms_vendor_mark_reviewed', 'vms_vendor_handle_mark_reviewed');
function vms_vendor_handle_mark_reviewed()
{
    if (!current_user_can('edit_posts')) {
        wp_die('Permission denied.');
    }

    $vendor_id = isset($_GET['vendor_id']) ? (int) $_GET['vendor_id'] : 0;
    if ($vendor_id <= 0) {
        wp_die('Invalid vendor.');
    }

    check_admin_referer('vms_vendor_mark_reviewed_' . $vendor_id);

    vms_vendor_mark_profile_reviewed($vendor_id, get_current_user_id());

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
if (!function_exists('vms_get_active_season_dates')) {
    function vms_get_active_season_dates(int $venue_id = 0): array
    {
        // 1) Venue override if the venue has explicit season range configured
        if ($venue_id > 0 && get_post_type($venue_id) === 'vms_venue') {
            $start = (string) get_post_meta($venue_id, '_vms_season_start', true);
            $end   = (string) get_post_meta($venue_id, '_vms_season_end', true);

            if ($start !== '' && $end !== '' && function_exists('vms_get_active_dates_for_venue')) {
                $dates = (array) vms_get_active_dates_for_venue($venue_id);
                $dates = array_values(array_filter(array_map('sanitize_text_field', $dates)));
                if (!empty($dates)) return $dates;
            }
        }

        // 2) Global pre-generated active dates (from Season Dates admin UI)
        $active = get_option('vms_active_dates', array());
        if (is_array($active) && !empty($active)) {
            $active = array_values(array_filter(array_map('sanitize_text_field', $active)));
            if (!empty($active)) return $active;
        }

        // 3) Generate from global rules if active dates are missing
        $rules = get_option('vms_season_rules', array());
        if (function_exists('vms_generate_active_dates') && is_array($rules) && !empty($rules)) {
            $gen = (array) vms_generate_active_dates($rules);
            $gen = array_values(array_filter(array_map('sanitize_text_field', $gen)));
            if (!empty($gen)) return $gen;
        }

        return array();
    }
}

function vms_is_in_season($ymd, $start_md, $end_md, DateTimeZone $tz) {
    try {
        $y = substr($ymd, 0, 4);
        $start = new DateTimeImmutable($y . '-' . $start_md . ' 00:00:00', $tz);
        $end   = new DateTimeImmutable($y . '-' . $end_md   . ' 23:59:59', $tz);
        $dt    = new DateTimeImmutable($ymd . ' 12:00:00', $tz);
        return ($dt >= $start && $dt <= $end);
    } catch (Exception $e) {
        return true;
    }
}

/**
 * ==========================================================
 * Venue Schedule Helpers (Open Days + Optional Seasons)
 * ==========================================================
 *
 * This powers:
 * - Admin “Schedule” view (formerly “Season Board”)
 * - Venue-aware date windows (up to 24 months)
 *
 * Rules:
 * - CLOSED by default until at least one Open Day is selected on the venue.
 * - Manual date overrides win first.
 * - Then weekly Open Days.
 * - If “Open year-round” is enabled, seasons are ignored.
 * - If seasons exist and year-round is off, date must land inside a season.
 */

if (!function_exists('vms_get_schedule_window_bounds')) {
    function vms_get_schedule_window_bounds(): array
    {
        $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');

        // Start at first day of the current month (venue schedule is month-oriented).
        $start = new DateTimeImmutable('first day of this month 00:00:00', $tz);

        // Minimum horizon: 18 months from the start month.
        $min_end = $start->modify('+18 months')->modify('-1 day')->setTime(23, 59, 59);

        // Also ensure we can see through Dec of next year.
        $dec_next_year = (new DateTimeImmutable('now', $tz))
            ->modify('last day of December next year')
            ->setTime(23, 59, 59);

        $end = ($min_end > $dec_next_year) ? $min_end : $dec_next_year;

        // Hard cap at 24 months.
        $cap = $start->modify('+24 months')->modify('-1 day')->setTime(23, 59, 59);
        if ($end > $cap) $end = $cap;

        return array(
            'tz'        => $tz,
            'start'     => $start,
            'end'       => $end,
            'start_ymd' => $start->format('Y-m-d'),
            'end_ymd'   => $end->format('Y-m-d'),
        );
    }
}

if (!function_exists('vms_venue_get_open_days')) {
    function vms_venue_get_open_days(int $venue_id): array
    {
        $days = get_post_meta($venue_id, '_vms_venue_open_days', true);
        if (!is_array($days)) $days = array();

        $days = array_values(array_unique(array_map('intval', $days)));
        $days = array_values(array_filter($days, function ($d) {
            return is_int($d) && $d >= 0 && $d <= 6;
        }));

        sort($days);
        return $days;
    }
}

if (!function_exists('vms_venue_get_seasons')) {
    function vms_venue_get_seasons(int $venue_id): array
    {
        $seasons = get_post_meta($venue_id, '_vms_venue_seasons', true);
        if (!is_array($seasons)) $seasons = array();

        $out = array();
        foreach ($seasons as $row) {
            if (!is_array($row)) continue;
            $start = isset($row['start']) ? trim((string) $row['start']) : '';
            $end   = isset($row['end'])   ? trim((string) $row['end'])   : '';

            if ($start === '' || $end === '') continue;
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) continue;
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) continue;

            if ($end < $start) {
                $tmp = $start;
                $start = $end;
                $end = $tmp;
            }

            $out[] = array('start' => $start, 'end' => $end);
        }

        // Back-compat: if old single-season meta exists, include it.
        $legacy_start = (string) get_post_meta($venue_id, '_vms_season_start', true);
        $legacy_end   = (string) get_post_meta($venue_id, '_vms_season_end', true);
        if (
            $legacy_start !== '' && $legacy_end !== '' &&
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $legacy_start) &&
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $legacy_end)
        ) {
            if ($legacy_end < $legacy_start) {
                $tmp = $legacy_start;
                $legacy_start = $legacy_end;
                $legacy_end = $tmp;
            }
            $out[] = array('start' => $legacy_start, 'end' => $legacy_end);
        }

        return $out;
    }
}

if (!function_exists('vms_venue_get_date_overrides')) {
    function vms_venue_get_date_overrides(int $venue_id): array
    {
        $ov = get_post_meta($venue_id, '_vms_venue_date_overrides', true);
        if (!is_array($ov)) return array();

        $clean = array();
        foreach ($ov as $ymd => $state) {
            $ymd = (string) $ymd;
            $state = (string) $state;
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) continue;
            if ($state !== 'open' && $state !== 'closed') continue;
            $clean[$ymd] = $state;
        }

        return $clean;
    }
}

if (!function_exists('vms_venue_get_open_state')) {
    function vms_venue_get_open_state(int $venue_id, string $ymd): array
    {
        $venue_id = (int) $venue_id;
        $ymd = trim($ymd);

        $out = array(
            'open'   => false,
            'reason' => 'closed',
            'src'    => '',
        );

        if ($venue_id <= 0) return $out;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) return $out;

        $open_days = vms_venue_get_open_days($venue_id);
        if (empty($open_days)) {
            $out['reason'] = 'not_configured';
            return $out;
        }

        // Manual date override wins first.
        $overrides = vms_venue_get_date_overrides($venue_id);
        if (isset($overrides[$ymd])) {
            if ($overrides[$ymd] === 'open') {
                $out['open'] = true;
                $out['reason'] = 'override_open';
                $out['src'] = 'override';
                return $out;
            }

            $out['open'] = false;
            $out['reason'] = 'override_closed';
            $out['src'] = 'override';
            return $out;
        }

        $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
        try {
            // Midday avoids DST edge cases.
            $dt = new DateTimeImmutable($ymd . ' 12:00:00', $tz);
        } catch (Exception $e) {
            return $out;
        }

        $dow = (int) $dt->format('w');
        if (!in_array($dow, $open_days, true)) {
            $out['reason'] = 'closed_day';
            $out['src'] = 'pattern';
            return $out;
        }

        $year_round = (int) get_post_meta($venue_id, '_vms_venue_open_year_round', true);
        if ($year_round === 1) {
            $out['open'] = true;
            $out['reason'] = 'open_day';
            $out['src'] = 'pattern';
            return $out;
        }

        $seasons = vms_venue_get_seasons($venue_id);
        if (empty($seasons)) {
            // No seasons configured means year-round scheduling based on Open Days.
            $out['open'] = true;
            $out['reason'] = 'open_day';
            $out['src'] = 'pattern';
            return $out;
        }

        foreach ($seasons as $s) {
            $start = $s['start'];
            $end   = $s['end'];

            $a = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $start . ' 00:00:00', $tz);
            $b = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $end   . ' 23:59:59', $tz);
            if (!$a || !$b) continue;

            if ($b < $a) {
                $tmp = $a;
                $a = $b;
                $b = $tmp;
            }

            if ($dt >= $a && $dt <= $b) {
                $out['open'] = true;
                $out['reason'] = 'in_season';
                $out['src'] = 'season';
                return $out;
            }
        }

        $out['reason'] = 'out_of_season';
        $out['src'] = 'season';
        return $out;
    }
}

if (!function_exists('vms_venue_is_open_on_date')) {
    function vms_venue_is_open_on_date(int $venue_id, string $ymd): bool
    {
        $s = vms_venue_get_open_state($venue_id, $ymd);
        return !empty($s['open']);
    }
}

if (!function_exists('vms_get_active_dates_for_venue')) {
    function vms_get_active_dates_for_venue(int $venue_id): array
    {
        $venue_id = (int) $venue_id;
        if ($venue_id <= 0) return array();

        $bounds = vms_get_schedule_window_bounds();
        $cur = $bounds['start'];
        $end = $bounds['end'];

        $dates = array();
        while ($cur <= $end) {
            $ymd = $cur->format('Y-m-d');
            if (vms_venue_is_open_on_date($venue_id, $ymd)) {
                $dates[] = $ymd;
            }
            $cur = $cur->modify('+1 day');
        }

        return $dates;
    }
}
