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
    return new DateTimeZone(vms_get_timezone_id());
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
 *        'rules' => [ 'vendor' => [...], 'bar' => [...], ... ] // future
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
