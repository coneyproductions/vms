<?php

/**
 * VMS Admin Schedule
 *
 * - Monthly calendar view (similar spirit to Vendor Availability).
 * - Shows every month inside the schedule window (no missing months).
 * - Closed days still render (grayed) and can still show Event Plans.
 * - “Create” action validates the date is inside the configured window.
 */

if (!defined('ABSPATH')) {
    exit;
}
require_once __DIR__ . '/../schedule/helpers.php';
require_once __DIR__ . '/../schedule/schedule.php';

add_action('admin_post_vms_create_event_plan', 'vms_handle_create_event_plan');

/**
 * Normalize the schedule window bounds.
 *
 * Accepts multiple return shapes from vms_get_schedule_window_bounds():
 * - ['start_ymd' => 'YYYY-mm-dd', 'end_ymd' => 'YYYY-mm-dd']
 * - ['start' => 'YYYY-mm-dd', 'end' => 'YYYY-mm-dd']
 * - ['start_date' => 'YYYY-mm-dd', 'end_date' => 'YYYY-mm-dd']
 * - [0 => 'YYYY-mm-dd', 1 => 'YYYY-mm-dd']
 * - Same keys but values may be DateTimeInterface objects
 */
function vms_sch_get_window_bounds(int $venue_id): array
{
    $start = '';
    $end   = '';

    if (function_exists('bvmgr_get_schedule_window_bounds')) {
        $raw = bvmgr_get_schedule_window_bounds($venue_id);
        if (is_array($raw)) {
            $start = $raw['start_ymd'] ?? $raw['start'] ?? $raw['start_date'] ?? ($raw[0] ?? '');
            $end   = $raw['end_ymd']   ?? $raw['end']   ?? $raw['end_date']   ?? ($raw[1] ?? '');
        }

        if ($start instanceof DateTimeInterface) {
            $start = $start->format('Y-m-d');
        }
        if ($end instanceof DateTimeInterface) {
            $end = $end->format('Y-m-d');
        }
    }

    // Fallback window: first day of current month through end of month +24 months.
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $end)) {
        $start = current_time('Y-m-01');
        $start_dt = vms_sch_parse_ymd($start);
        $end = $start_dt ? $start_dt->modify('+24 months')->format('Y-m-t') : $start;
    }

    // Safety: ensure start <= end
    if (strtotime($start) > strtotime($end)) {
        $tmp = $start;
        $start = $end;
        $end = $tmp;
    }

    return array(
        'start_ymd' => $start,
        'end_ymd'   => $end,
    );
}

function vms_sch_parse_ymd(string $ymd): ?DateTimeImmutable
{
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $ymd, wp_timezone());
    if (!$dt instanceof DateTimeImmutable || $dt->format('Y-m-d') !== $ymd) {
        return null;
    }

    return $dt;
}

function vms_sch_allowed_html(): array
{
    return array(
        'a' => array(
            'class' => true,
            'href'  => true,
        ),
        'br' => array(),
        'div' => array(
            'class' => true,
        ),
        'span' => array(
            'class' => true,
        ),
    );
}

/**
 * View window for schedule rendering (separate from creation window).
 *
 * Default: 12 months back and 12 months forward from the current month,
 * expanded to always include the configured creation window.
 */
function vms_sch_get_view_window_bounds(string $create_start_ymd, string $create_end_ymd, int $months_back = 12, int $months_ahead = 12): array
{
    $base_start = current_time('Y-m-01');
    $base_dt    = vms_sch_parse_ymd($base_start);

    // Clamp to sane bounds (avoid accidental huge renders)
    $months_back  = max(0, min(24, (int) $months_back));
    $months_ahead = max(1, min(24, (int) $months_ahead));

    $view_start = $base_dt ? $base_dt->modify('-' . $months_back . ' months')->format('Y-m-01') : $base_start;
    $view_end   = $base_dt ? $base_dt->modify('+' . $months_ahead . ' months')->format('Y-m-t') : $base_start;

    // Ensure the view always includes the configured creation window.
    $create_start_dt = vms_sch_parse_ymd($create_start_ymd);
    $create_end_dt   = vms_sch_parse_ymd($create_end_ymd);

    if ($create_start_dt && $create_start_ymd < $view_start) {
        $view_start = $create_start_dt->format('Y-m-01');
    }
    if ($create_end_dt && $create_end_ymd > $view_end) {
        $view_end = $create_end_dt->format('Y-m-t');
    }

    return array(
        'start_ymd' => $view_start,
        'end_ymd'   => $view_end,
    );
}

function vms_sch_format_time_ampm(string $hhmm): string
{
    $hhmm = trim($hhmm);
    if ($hhmm === '') return '';

    $dt = DateTime::createFromFormat('H:i', $hhmm, wp_timezone());
    if (!$dt) return '';

    // Example: 7:00pm
    return strtolower($dt->format('g:ia'));
}

function vms_sch_plan_label($plan_id)
{
    $plan_id = (int) $plan_id;
    if ($plan_id <= 0) {
        return 'TBD - draft';
    }

    // Single source of truth for Schedule labeling (list + calendar).
    if (function_exists('bvmgr_event_plan_compact_label')) {
        return (string) bvmgr_event_plan_compact_label($plan_id);
    }

    return 'Event Plan';
}




/**
 * Validate a venue post ID for Schedule context.
 * - Must be a vms_venue post.
 * - Must not be missing, trashed, or auto-draft.
 */
function vms_sch_is_valid_venue_post_id(int $venue_id): bool
{
    if ($venue_id <= 0) {
        return false;
    }

    if (get_post_type($venue_id) !== 'vms_venue') {
        return false;
    }

    $st = get_post_status($venue_id);
    if ($st === false || $st === 'trash' || $st === 'auto-draft') {
        return false;
    }

    return true;
}


function vms_handle_create_event_plan(): void
{
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions.');
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The create-event-plan link verifies an action-specific nonce before any mutation occurs.
    $ymd = bvmgr_request_read_text_field($_GET, 'date');
    if (!$ymd) {
        wp_die('Missing date.');
    }
    if (!function_exists('vms_sch_is_valid_ymd') || !vms_sch_is_valid_ymd($ymd)) {
        wp_die('Invalid date.');
    }

    $nonce = (isset($_GET['_wpnonce']) && !is_array($_GET['_wpnonce']))
        ? sanitize_text_field(wp_unslash((string) $_GET['_wpnonce']))
        : '';
    if (!wp_verify_nonce($nonce, 'vms_create_event_plan_' . $ymd)) {
        wp_die('Invalid nonce.');
    }

    // Prefer explicit venue_id from the calendar link; fallback to current selected venue.
    $venue_id = bvmgr_request_read_absint($_GET, 'venue_id');
    if ($venue_id <= 0 && function_exists('bvmgr_get_current_venue_id')) {
        $venue_id = (int) bvmgr_get_current_venue_id();
    }
    if ($venue_id <= 0) {
        wp_die('Select a venue first.');
    }

    if (get_post_status((int) $venue_id) !== 'publish') {
        wp_die('Venue must be published before creating Event Plans.');
    }

    $today_ymd = function_exists('current_time') ? (string) current_time('Y-m-d') : (string) gmdate('Y-m-d');
    $is_past   = ($ymd < $today_ymd);

    $is_blackout = false;
    if (function_exists('vms_sch_season_is_blackout_date')) {
        $is_blackout = (bool) vms_sch_season_is_blackout_date((int) $venue_id, (string) $ymd);
    } elseif (function_exists('vms_sch_season_get_rules')) {
        $rules = vms_sch_season_get_rules((int) $venue_id);
        if (is_array($rules)) {
            foreach ($rules as $r) {
                if (!is_array($r)) {
                    continue;
                }
                if (($r['type'] ?? '') !== 'blackout_date') {
                    continue;
                }
                $enabled = $r['enabled'] ?? 1;
                if ($enabled === 0 || $enabled === false || (string) $enabled === '0') {
                    continue;
                }
                $d = isset($r['date_ymd']) ? (string) $r['date_ymd'] : '';
                if ($d && $d === $ymd) {
                    $is_blackout = true;
                    break;
                }
            }
        }
    }

    if ($is_past || $is_blackout) {
        $ref = wp_get_referer();
        $redirect = $ref ? $ref : admin_url('admin.php?page=vms-schedule');

        $redirect = add_query_arg(
            [
                'vms_error'  => 'schedule_blocked_day',
                'vms_reason' => $is_past ? 'past' : 'blackout',
                'ymd'        => (string) $ymd,
            ],
            $redirect
        );

        wp_safe_redirect($redirect);
        exit;
    }

    $bounds = vms_sch_get_window_bounds((int) $venue_id);
    $start_ymd = (string) ($bounds['start_ymd'] ?? '');
    $end_ymd   = (string) ($bounds['end_ymd'] ?? '');

    if (!vms_sch_is_date_in_window($ymd, $start_ymd, $end_ymd)) {
        wp_die('That date is outside the configured schedule window.');
    }

    $allow_add = isset($_GET['add']) && (string) $_GET['add'] === '1';

    $existing = get_posts(array(
        'post_type'      => 'vms_event_plan',
        'post_status'    => array('publish', 'draft', 'pending', 'future', 'private'),
        'posts_per_page' => -1,
        'fields'         => 'ids',
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Duplicate prevention must inspect every plan matching this exact validated date-and-venue pair.
        'meta_query'     => array(
            'relation' => 'AND',
            array(
                'key'     => '_vms_event_date',
                'value'   => $ymd,
                'compare' => '=',
            ),
            array(
                'key'     => '_vms_venue_id',
                'value'   => (int) $venue_id,
                'compare' => '=',
            ),
        ),
    ));

    if (!empty($existing) && !$allow_add) {
        $edit_link = get_edit_post_link((int) $existing[0], '');
        wp_safe_redirect($edit_link ?: admin_url('edit.php?post_type=vms_event_plan'));
        exit;
    }

    // NOTE: Use RAW post_title (no the_title filters) so we don't store HTML entities like "&#8211;".
    // (Those often come from wptexturize() via get_the_title().)
    $venue_title_raw = (string) get_post_field('post_title', (int) $venue_id, 'raw');
    $venue_title = trim(wp_strip_all_tags($venue_title_raw));

    $title = 'Event Plan — ' . $ymd;
    if ($venue_title !== '') {
        $title = $venue_title . ' — ' . $ymd;
    }

    if (!empty($existing)) {
        $seq = count($existing) + 1;
        $title .= ' #' . $seq;
    }

    $plan_id = wp_insert_post(array(
        'post_type'   => 'vms_event_plan',
        'post_status' => 'draft',
        'post_title'  => $title,
    ));

    if (is_wp_error($plan_id) || !$plan_id) {
        wp_die('Failed to create Event Plan.');
    }

    update_post_meta((int) $plan_id, '_vms_event_date', $ymd);
    update_post_meta((int) $plan_id, '_vms_venue_id', (int) $venue_id);

    // STAFF-01: seed structured staffing slots from the best matching template.
    if (function_exists('vms_staffing_seed_event_slots_from_template')) {
        vms_staffing_seed_event_slots_from_template((int) $plan_id, false, (int) get_current_user_id());
    }

    $edit_link = get_edit_post_link((int) $plan_id, '');
    wp_safe_redirect($edit_link ?: admin_url('edit.php?post_type=vms_event_plan'));
    exit;
}

/**
 * Render: Schedule page
 */
function vms_render_schedule_page(): void
{
    $event_plans_url = function_exists('bvmgr_admin_ui_post_type_url')
        ? bvmgr_admin_ui_post_type_url('vms_event_plan')
        : admin_url('edit.php?post_type=vms_event_plan');
    $new_event_plan_url = admin_url('post-new.php?post_type=vms_event_plan');
    $actions_html = '<a class="button" href="' . esc_url($event_plans_url) . '">' . esc_html__('Event Plans', 'backstage-venue-manager') . '</a>';
    $actions_html .= '<a class="button button-primary" href="' . esc_url($new_event_plan_url) . '">' . esc_html__('New Event Plan', 'backstage-venue-manager') . '</a>';

    if (function_exists('bvmgr_admin_ui_render_shell')) {
        bvmgr_admin_ui_render_shell(
            array(
                'title' => __('Schedule', 'backstage-venue-manager'),
                'subtitle' => __('Plan dates, venues, and event readiness from a single calendar command center.', 'backstage-venue-manager'),
                'actions_html' => $actions_html,
                'content_class' => 'vms-admin-shell__content--schedule',
            ),
            'vms_render_schedule_page_content'
        );
        return;
    }

    echo '<div class="wrap"><h1>Schedule</h1>';
    vms_render_schedule_page_content();
    echo '</div>';
}

function vms_render_schedule_page_content(): void
{
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions.');
    }

    $user_id = (int) get_current_user_id();

    // Canonical schedule "current venue" key (helpers.php defines this constant).
    $sch_venue_meta_key = defined('BVMGR_SCH_CURRENT_VENUE_META_KEY')
        ? (string) BVMGR_SCH_CURRENT_VENUE_META_KEY
        : '_vms_current_venue_id';

    // View + scope come from URL (view mode only; do NOT store as current venue).
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only Schedule view selection only changes the current admin display mode.
    $view = bvmgr_request_read_text_field($_GET, 'view');
    if ($view === '') {
        $view = 'calendar';
    }
    if ($view !== 'list' && $view !== 'calendar') {
        $view = 'calendar';
    }

    $scope_meta_key = defined('BVMGR_SCH_CURRENT_SCOPE_META_KEY')
        ? (string) BVMGR_SCH_CURRENT_SCOPE_META_KEY
        : '_vms_schedule_scope';

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Current-user Schedule scope selection only affects this admin view and stored user preference state.
    $incoming_scope = bvmgr_request_read_key($_GET, 'scope');
    if ($incoming_scope !== 'venue' && $incoming_scope !== 'all') {
        $incoming_scope = '';
    }

    // Scope persists per-user so returning to Schedule keeps the last 'This venue' vs 'All venues' selection.
    $scope = $incoming_scope !== ''
        ? $incoming_scope
        : sanitize_key((string) get_user_meta($user_id, $scope_meta_key, true));

    if ($scope !== 'venue' && $scope !== 'all') {
        $scope = 'venue';
    }

    if ($incoming_scope !== '') {
        update_user_meta($user_id, $scope_meta_key, $scope);
    }

    // If URL explicitly specifies a venue_id, save it as the new numeric "current venue".
    // IMPORTANT: This never saves 'all'. All-venues is controlled by $scope only.
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Current-user Schedule venue selection only updates the viewer's own admin preference state.
    $requested_venue_id = bvmgr_request_read_absint($_GET, 'venue_id');
    if ($requested_venue_id > 0) {
        if (vms_sch_is_valid_venue_post_id($requested_venue_id)) {
            update_user_meta($user_id, $sch_venue_meta_key, (string) $requested_venue_id);
        }
    }

    // Read current venue as numeric only.
    $venue_id = absint(get_user_meta($user_id, $sch_venue_meta_key, true));

    // If a saved venue is no longer valid (missing/trashed), treat it as unset for this view.
    if ($venue_id > 0 && !vms_sch_is_valid_venue_post_id((int) $venue_id)) {
        $venue_id = 0;
    }

    // Fallback to Default Venue if none selected yet.
    if ($venue_id <= 0 && function_exists('bvmgr_get_default_venue_id')) {
        $fallback_default = (int) bvmgr_get_default_venue_id();
        if (vms_sch_is_valid_venue_post_id((int) $fallback_default)) {
            $venue_id = $fallback_default;
        }
    }

    // Multi-venue installs: if no current venue has ever been selected and no default venue exists,
    // fall back deterministically to the first available venue (by title), matching the dropdown selector.
    // This prevents a confusing blank schedule where the selector shows a venue but the page still thinks none is selected.
    if ($venue_id <= 0 && function_exists('bvmgr_get_current_venue_id')) {
        $fallback_first = (int) bvmgr_get_current_venue_id();
        if (vms_sch_is_valid_venue_post_id((int) $fallback_first)) {
            $venue_id = $fallback_first;
            update_user_meta($user_id, $sch_venue_meta_key, (string) $venue_id);
        }
    }

    // Single-venue installs: auto-select the only venue so the schedule renders immediately.
    // This avoids "blank until clicking All Venues" when user meta + default venue are empty.
    // IMPORTANT: Do NOT auto-pick an arbitrary venue when multiple venues exist.
    if (
        $venue_id <= 0
        && function_exists('vms_sch_get_schedule_venue_candidates')
        && function_exists('vms_sch_pick_single_venue_candidate')
    ) {
        $single_candidate = (int) vms_sch_pick_single_venue_candidate(
            (array) vms_sch_get_schedule_venue_candidates()
        );

        if ($single_candidate > 0 && vms_sch_is_valid_venue_post_id($single_candidate)) {
            $venue_id = $single_candidate;
            update_user_meta($user_id, $sch_venue_meta_key, (string) $venue_id);
        }
    }

    // Calendar/List view lookback (per-user): None / 1 month / 12 months.
    // This controls how far back the rendered schedule window starts.
    $lb_key = '_vms_admin_schedule_lookback_months';
    $months_back = (int) get_user_meta($user_id, $lb_key, true);
    if (!in_array($months_back, array(0, 1, 12), true)) {
        $months_back = 12;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Current-user Schedule lookback selection only updates the viewer's own admin preference state.
    $requested_lb_raw = bvmgr_request_read_scalar($_GET, 'lb');
    if ($requested_lb_raw !== '') {
        $requested_lb = absint($requested_lb_raw);
        if (in_array($requested_lb, array(0, 1, 12), true)) {
            $months_back = (int) $requested_lb;
            update_user_meta($user_id, $lb_key, (string) $months_back);
        }
    }

    // Schedule visibility toggle: include Draft/Ready in Schedule views (persist per-user).
    // Default: ON (Draft/Ready should appear in Schedule as Draft/Ready).
    $has_inc = function_exists('bvmgr_user_pref_has_include_drafts')
        ? (bool) bvmgr_user_pref_has_include_drafts($user_id)
        : (bool) metadata_exists('user', $user_id, '_vms_include_drafts');

    $include_drafts = $has_inc
        ? ((function_exists('bvmgr_user_pref_get_include_drafts')) ? (bool) bvmgr_user_pref_get_include_drafts((int) $user_id) : false)
        : true;

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Current-user Schedule visibility selection only updates the viewer's own admin preference state.
    $include_drafts_raw = bvmgr_request_read_scalar($_GET, 'include_drafts');
    if ($include_drafts_raw !== '') {
        $include_drafts = (absint($include_drafts_raw) === 1);
        if (function_exists('bvmgr_user_pref_set_include_drafts')) {
            bvmgr_user_pref_set_include_drafts((bool) $include_drafts, (int) $user_id);
        } else {
            update_user_meta((int) $user_id, '_vms_include_drafts', $include_drafts ? '1' : '0');
        }
    }

    echo '<div class="vms-admin-schedule-content">';

    if (function_exists('bvmgr_render_current_venue_selector')) {
        bvmgr_render_current_venue_selector();
    }

    $base_url = remove_query_arg(array('view', 'scope'));
    $scope_venue_url = add_query_arg(array('scope' => 'venue', 'view' => $view), $base_url);
    $scope_all_url   = add_query_arg(array('scope' => 'all',   'view' => $view), $base_url);

    echo '<div class="vms-portal-nav vms-sch-nav">';
    echo '<a class="' . ($scope === 'venue' ? 'is-active' : '') . '" href="' . esc_url($scope_venue_url) . '">This venue</a>';
    echo '<a class="' . ($scope === 'all' ? 'is-active' : '') . '" href="' . esc_url($scope_all_url) . '">All venues</a>';
    echo '</div>';

    echo '<p>High-level view of dates, booked vendors, and event plan status.</p>';

    // Lookback control (mirrors vendor portal lookback selector)
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only Schedule form routing only preserves the current admin page slug.
    $page_slug = bvmgr_request_read_key($_GET, 'page');
    if ($page_slug === '') {
        $page_slug = 'vms-schedule';
    }
    echo '<form method="get" class="vms-sch-lookback vms-js-auto-submit-form">';
    echo '<input type="hidden" name="page" value="' . esc_attr($page_slug) . '">';
    echo '<input type="hidden" name="scope" value="' . esc_attr($scope) . '">';
    echo '<input type="hidden" name="view" value="' . esc_attr($view) . '">';
    if ((int) $venue_id > 0) {
        echo '<input type="hidden" name="venue_id" value="' . esc_attr((string) $venue_id) . '">';
    }
    // Persisted what-if default (unchecked=0).
    echo '<input type="hidden" name="include_drafts" value="0">';
    echo '<label for="vms-sch-lb">' . esc_html__('Show past:', 'backstage-venue-manager') . '</label>';
    echo '<select id="vms-sch-lb" name="lb" class="vms-js-auto-submit-field">';
    echo '<option value="0"' . selected(0, $months_back, false) . '>' . esc_html__('None', 'backstage-venue-manager') . '</option>';
    echo '<option value="1"' . selected(1, $months_back, false) . '>' . esc_html__('1 month', 'backstage-venue-manager') . '</option>';
    echo '<option value="12"' . selected(12, $months_back, false) . '>' . esc_html__('12 months', 'backstage-venue-manager') . '</option>';
    echo '</select>';
    echo '<span class="vms-sch-muted">' . esc_html__('Past months are view-only.', 'backstage-venue-manager') . '</span>';
    echo '<label class="vms-sch-whatif"><input type="checkbox" name="include_drafts" value="1" class="vms-js-auto-submit-field"' . checked($include_drafts, true, false) . '> ' . esc_html__('Include Draft/Ready', 'backstage-venue-manager') . '</label>';
    echo '</form>';

    // Single-venue guardrail: if the only venue exists but is not published, call it out loudly.
    $only_unpublished_id = 0;
    $only_unpublished_status = '';

    $venue_candidates = function_exists('vms_sch_get_schedule_venue_candidates')
        ? (array) vms_sch_get_schedule_venue_candidates(2)
        : array();
    $venue_candidates = array_values(array_unique(array_filter(array_map('intval', (array) $venue_candidates))));

    if (count($venue_candidates) === 1) {
        $only_id = (int) $venue_candidates[0];
        $st = (string) get_post_status($only_id);
        if (!in_array($st, array('publish', 'private'), true)) {
            $only_unpublished_id = $only_id;
            $only_unpublished_status = $st;
        }
    }

    if ($only_unpublished_id > 0) {
        $title = (string) get_the_title($only_unpublished_id);
        $edit  = get_edit_post_link($only_unpublished_id, 'raw');
        if (empty($edit)) {
            $edit = admin_url('post.php?post=' . (int) $only_unpublished_id . '&action=edit');
        }

        $unpublished_notice_context = vms_schedule_get_unpublished_venue_notice_context(true, 'single_unpublished', $title, $only_unpublished_status, $edit);
        vms_schedule_render_unpublished_venue_notice($unpublished_notice_context);

        // Stop here to avoid a confusing blank calendar when the only venue is unpublished.
        echo '</div>';
        return;
    }

    // If the selected venue is not published, warn and stop.
    if ($scope === 'venue' && (int) $venue_id > 0) {
        $selected_status = (string) get_post_status((int) $venue_id);
        if (!in_array($selected_status, array('publish', 'private'), true)) {
            $title = (string) get_the_title((int) $venue_id);
            $edit  = get_edit_post_link((int) $venue_id, 'raw');
            if (empty($edit)) {
                $edit = admin_url('post.php?post=' . (int) $venue_id . '&action=edit');
            }

            $unpublished_notice_context = vms_schedule_get_unpublished_venue_notice_context(true, 'selected_unpublished', $title, $selected_status, $edit);
            vms_schedule_render_unpublished_venue_notice($unpublished_notice_context);

            echo '</div>';
            return;
        }
    }


    $scope_warning_notice_context = vms_schedule_get_scope_warning_notice_context($scope === 'venue' && (int) $venue_id <= 0, 'no_selection');
    if (!empty($scope_warning_notice_context['show'])) {
        vms_schedule_render_scope_warning_notice($scope_warning_notice_context);
        echo '</div>';
        return;
    }

    if ($scope === 'all') {
        $venue_ids = vms_sch_get_all_venue_ids();
        $scope_warning_notice_context = vms_schedule_get_scope_warning_notice_context(empty($venue_ids), 'no_venues');
        if (!empty($scope_warning_notice_context['show'])) {
            vms_schedule_render_scope_warning_notice($scope_warning_notice_context);
            echo '</div>';
            return;
        }
        echo '<div class="vms-sch-scope vms-sch-scope-' . esc_attr($scope) . '">';

        $bounds_venue_id = (int) $venue_ids[0];

        $bounds = vms_sch_get_window_bounds($bounds_venue_id);
        $create_start_ymd = (string) $bounds['start_ymd'];
        $create_end_ymd   = (string) $bounds['end_ymd'];

        $view_bounds = vms_sch_get_view_window_bounds($create_start_ymd, $create_end_ymd, $months_back, 12);
        $start_ymd = (string) $view_bounds['start_ymd'];
        $end_ymd   = (string) $view_bounds['end_ymd'];

        $venue_name_map = vms_sch_get_venue_name_map($venue_ids);

        // Open/closed state is venue-specific — skip it in All Venues
        $open_map = array();

        $plans_by_date = vms_sch_get_plans_by_date_all(
            $venue_ids,
            $start_ymd,
            $end_ymd,
            (bool) $include_drafts,
            array('context' => 'schedule_admin', 'include_cancelled' => true)
        );
    } else {

        $bounds = vms_sch_get_window_bounds($venue_id);
        $create_start_ymd = (string) $bounds['start_ymd'];
        $create_end_ymd   = (string) $bounds['end_ymd'];

        $view_bounds = vms_sch_get_view_window_bounds($create_start_ymd, $create_end_ymd, $months_back, 12);
        $start_ymd = (string) $view_bounds['start_ymd'];
        $end_ymd   = (string) $view_bounds['end_ymd'];

        $open_map  = vms_sch_get_open_map($venue_id, $start_ymd, $end_ymd);
        $plans_by_date = vms_sch_get_plans_by_date(
            $venue_id,
            $start_ymd,
            $end_ymd,
            (bool) $include_drafts,
            array('context' => 'schedule_admin', 'include_cancelled' => true)
        );

        $venue_name_map = array(
            (int) $venue_id => get_the_title($venue_id)
        );
    }

    $base_url = remove_query_arg(array('view'));
    $cal_url  = add_query_arg('view', 'calendar', $base_url);
    $list_url = add_query_arg('view', 'list', $base_url);

    echo '<div class="vms-portal-nav vms-sch-nav">';
    echo '<a class="' . ($view === 'calendar' ? 'is-active' : '') . '" href="' . esc_url($cal_url) . '">Calendar</a>';
    echo '<a class="' . ($view === 'list' ? 'is-active' : '') . '" href="' . esc_url($list_url) . '">List</a>';
    echo '</div>';

    if ($view === 'list') {
        if ($scope === 'all') {
            vms_render_schedule_list_view_all($start_ymd, $end_ymd, $plans_by_date, $venue_name_map);
        } else {
            vms_render_schedule_list_view($venue_id, $start_ymd, $end_ymd, $open_map, $plans_by_date, $create_start_ymd, $create_end_ymd);
        }
    } else {
        if ($scope === 'all') {
            vms_render_schedule_calendar_view_all($start_ymd, $end_ymd, $plans_by_date, $venue_name_map);
        } else {
            vms_render_schedule_calendar_view($venue_id, $start_ymd, $end_ymd, $open_map, $plans_by_date, $create_start_ymd, $create_end_ymd);
        }
    }

    echo '</div>';
}

function vms_schedule_get_invalid_bounds_notice_context(bool $show): array
{
    return array(
        'show' => $show,
    );
}

function vms_schedule_get_scope_warning_notice_context(bool $show, string $variant): array
{
    $variant = in_array($variant, array('no_selection', 'no_venues'), true) ? $variant : '';

    return array(
        'show' => $show,
        'variant' => $variant,
    );
}

function vms_schedule_get_unpublished_venue_notice_context(bool $show, string $variant, string $title, string $status, string $edit_url): array
{
    $variant = in_array($variant, array('single_unpublished', 'selected_unpublished'), true) ? $variant : '';
    $show = $show && $variant !== '';

    return array(
        'show' => $show,
        'variant' => $variant,
        'show_title' => $show && $title !== '',
        'title' => $show ? $title : '',
        'status' => $show ? $status : '',
        'edit_url' => $show ? $edit_url : '',
    );
}

function vms_schedule_render_invalid_bounds_notice(array $context): void
{
    if (empty($context['show'])) {
        return;
    }

    echo '<div class="notice notice-error"><p>Schedule window bounds were invalid.</p></div>';
}

function vms_schedule_render_scope_warning_notice(array $context): void
{
    if (empty($context['show'])) {
        return;
    }

    $variant = isset($context['variant']) ? (string) $context['variant'] : '';
    if ($variant === 'no_selection') {
        echo '<div class="notice notice-warning"><p>Select a venue to view its schedule.</p></div>';
        return;
    }

    if ($variant === 'no_venues') {
        echo '<div class="notice notice-warning"><p>No venues found to display.</p></div>';
    }
}

function vms_schedule_render_unpublished_venue_notice(array $context): void
{
    if (empty($context['show'])) {
        return;
    }

    $variant = isset($context['variant']) ? (string) $context['variant'] : '';
    if (!in_array($variant, array('single_unpublished', 'selected_unpublished'), true)) {
        return;
    }

    $show_title = !empty($context['show_title']);
    $title = isset($context['title']) ? (string) $context['title'] : '';
    $status = isset($context['status']) ? (string) $context['status'] : '';
    $edit_url = isset($context['edit_url']) ? (string) $context['edit_url'] : '';

    if ($variant === 'single_unpublished') {
        echo '<div class="notice notice-error"><p><strong>' . esc_html__('Action required:', 'backstage-venue-manager') . '</strong> ';
        echo esc_html__('Your only venue is not published, so Schedule cannot load availability.', 'backstage-venue-manager') . ' ';
        if ($show_title) {
            echo '<span class="vms-muted">' . esc_html($title) . '</span> ';
        }
        echo '<span class="vms-muted">(' . esc_html($status) . ')</span>';
        echo '</p><p><a class="button button-primary" href="' . esc_url($edit_url) . '">' . esc_html__('Open venue to publish', 'backstage-venue-manager') . '</a></p></div>';
        return;
    }

    echo '<div class="notice notice-error"><p><strong>' . esc_html__('Venue is not published:', 'backstage-venue-manager') . '</strong> ';
    if ($show_title) {
        echo '<span class="vms-muted">' . esc_html($title) . '</span> ';
    }
    echo '<span class="vms-muted">(' . esc_html($status) . ')</span> ';
    echo esc_html__('Publish this venue to enable schedule availability.', 'backstage-venue-manager') . '</p><p>';
    echo '<a class="button button-primary" href="' . esc_url($edit_url) . '">' . esc_html__('Open venue to publish', 'backstage-venue-manager') . '</a>';
    echo '</p></div>';
}


function vms_render_schedule_list_view(int $venue_id, string $start_ymd, string $end_ymd, array $open_map, array $plans_by_date, string $create_start_ymd, string $create_end_ymd): void
{

    $venue_id_param = (int) $venue_id;

    $start_dt = vms_sch_parse_ymd($start_ymd);
    $end_dt   = vms_sch_parse_ymd($end_ymd);
    $invalid_bounds_notice_context = vms_schedule_get_invalid_bounds_notice_context(!$start_dt || !$end_dt);

    if (!empty($invalid_bounds_notice_context['show'])) {
        vms_schedule_render_invalid_bounds_notice($invalid_bounds_notice_context);
        return;
    }

    // $show_past = isset($_GET['show_past']) && (string) $_GET['show_past'] === '1';


    $opts = (array) get_option('vms_settings', array());
    $hide_past_default = array_key_exists('sch_hide_past_default', $opts) ? (int) $opts['sch_hide_past_default'] : 1;

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only Schedule filters only affect which dates are shown in the current admin view.
    $show_past = bvmgr_request_read_absint($_GET, 'show_past');
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only Schedule filters only affect which dates are shown in the current admin view.
    $force_hide_past = bvmgr_request_read_absint($_GET, 'hide_past');

    // show_past wins if both are present
    if ($show_past === 1) {
        $hide_past = false;
    } elseif ($force_hide_past === 1) {
        $hide_past = true;
    } else {
        $hide_past = ($hide_past_default === 1);
    }


    $current_url = add_query_arg(null, null);

    // URLs
    $show_past_url = add_query_arg(array('show_past' => 1), remove_query_arg('hide_past', $current_url));
    $hide_past_url = add_query_arg(array('hide_past' => 1), remove_query_arg('show_past', $current_url));
    $reset_url     = remove_query_arg(array('show_past', 'hide_past'), $current_url);

    // Button + label
    echo '<div class="vms-sch-past-toggle">';

    if ($hide_past) {
        echo '<span class="description vms-mr-10">Past dates: <strong>Hidden</strong></span>';
        echo '<a class="button button-secondary" href="' . esc_url($show_past_url) . '">Show past dates</a>';
        // Optional reset back to “default behavior”
        echo ' <a class="button button-link" href="' . esc_url($reset_url) . '">Reset</a>';
    } else {
        echo '<span class="description vms-mr-10">Past dates: <strong>Visible</strong></span>';
        echo '<a class="button button-secondary" href="' . esc_url($hide_past_url) . '">Hide past dates</a>';
        echo ' <a class="button button-link" href="' . esc_url($reset_url) . '">Reset</a>';
    }

    echo '</div>';

    echo '<table class="widefat striped vms-sch-list">';
    echo '<thead><tr>';
    echo '<th class="vms-col-date">Date</th>';
    echo '<th class="vms-col-holidays">Holidays</th>';
    // echo '<th style="width:120px">Open/Closed</th>'; // DISABLED TEMPORARILY FOR CLEANER LOOK
    echo '<th>Event Plans</th>';
    echo '<th class="vms-col-actions">Actions</th>';
    echo '</tr></thead><tbody>';

    // Blackout notes map (range-capable) for this window.
    $blackout_notes_map = function_exists('vms_sch_season_get_blackout_notes_map')
        ? vms_sch_season_get_blackout_notes_map((int) $venue_id, $start_ymd, $end_ymd)
        : array();

    $today_ymd = function_exists('current_time') ? (string) current_time('Y-m-d') : (string) gmdate('Y-m-d');

    for ($cursor = $start_dt; $cursor <= $end_dt; $cursor = $cursor->modify('+1 day')) {
        $ymd = $cursor->format('Y-m-d');
        $blackout_notes = isset($blackout_notes_map[$ymd]) && is_array($blackout_notes_map[$ymd]) ? $blackout_notes_map[$ymd] : array();
        $has_blackout = !empty($blackout_notes);
        $event_status = '';
        $statuses = [];
        $has_plans = !empty($plans_by_date[$ymd]);
        $is_open = $has_plans || isset($open_map[$ymd]);

        $is_past   = ((string) $ymd < $today_ymd);
        if ($hide_past && $is_past) {
            continue;
        }

        // Creation window is separate from the view window (we can still *view* past/future months).
        $in_create_window = vms_sch_is_date_in_window($ymd, $create_start_ymd, $create_end_ymd);

        // Holidays (single venue): show only the holiday name(s) in a dedicated column.
        $holiday_html = '';
        $holiday_forces_open = function_exists('vms_sch_holiday_forces_open') ? vms_sch_holiday_forces_open($venue_id_param, $ymd) : false;
        if (function_exists('vms_sch_get_holidays_for_date')) {
            $holidays = vms_sch_get_holidays_for_date($venue_id_param, $ymd);
            if (!empty($holidays)) {
                $parts = array();
                foreach ($holidays as $h) {
                    if (!is_array($h)) {
                        continue;
                    }
                    $name = trim((string) ($h['name'] ?? ''));
                    if ($name === '') {
                        continue;
                    }
                    $parts[] = '<span class="vms-sch-holiday">' . esc_html($name) . '</span>';
                }
                if (!empty($parts)) {
                    $holiday_html = '<div class="vms-sch-holiday-stack">' . implode('', $parts) . '</div>';
                }
            }
        }

        // Blackout note pills (same column as holidays in list view).
        $blackout_html = '';
        if ($has_blackout) {
            $p = array();
            foreach ($blackout_notes as $n) {
                $n = trim((string) $n);
                // Note can be blank; still show pill to explain the gray cell.
                $label = ($n === '') ? 'Blackout' : $n;
                $p[] = '<span class="vms-sch-blackout">' . esc_html($label) . '</span>';
            }
            if (!empty($p)) {
                $blackout_html = '<div class="vms-sch-blackout-stack">' . implode('', $p) . '</div>';
            }
        }

        // Schedulable means: inside creation window, open per open_map OR open holiday, not past.
        // Blackout blocks scheduling only when there is no open holiday override.
        $is_open = $has_plans || isset($open_map[$ymd]) || $holiday_forces_open;
        $is_schedulable = $in_create_window
            && ($holiday_forces_open || isset($open_map[$ymd]))
            && !$is_past
            && (!$has_blackout || $holiday_forces_open);

        // Row state: Holiday always wins. If holiday opens the venue, show open (green) even if blackout exists.
        $row_state_class = $holiday_forces_open ? 'vms-venue-open' : ($has_blackout ? 'vms-venue-blackout' : ($is_open ? 'vms-venue-open' : 'vms-venue-closed'));
        $past_class = $is_past ? ' vms-venue-past' : '';

        // Badge for the list (since Open/Closed column is hidden).
        $badge = $has_blackout && !$holiday_forces_open
            ? '<span class="vms-sch-badge is-blackout">Blackout</span>'
            : ($is_open
                ? '<span class="vms-sch-badge is-open">Open</span>'
                : '<span class="vms-sch-badge is-closed">Closed</span>');

        $plans_html = '';

        if (!empty($plans_by_date[$ymd])) {
            $links = array();

            foreach ($plans_by_date[$ymd] as $row) {

                $plan_id = bvmgr_get_plan_id_from_row($row);
                if ($plan_id <= 0) continue;

                $status = '';
                if (is_array($row) && isset($row['plan_status'])) {
                    $status = sanitize_key((string) $row['plan_status']);
                }
                if ($status === 'canceled') {
                    $status = 'cancelled';
                }

                // Fallback for legacy rows that do not carry canonical plan_status.
                if ($status === '') {
                    $status = sanitize_key((string) bvmgr_map_plan_status($plan_id));
                    if ($status === 'canceled') {
                        $status = 'cancelled';
                    }
                }

                // Keep Schedule row accents stable: green (published/confirmed), yellow (draft/ready), red (cancelled).
                if ($status === 'cancelled') {
                    $statuses[] = 'cancelled';
                } elseif (in_array($status, array('published', 'confirmed'), true)) {
                    $statuses[] = 'confirmed';
                } elseif (in_array($status, array('draft', 'ready', 'tentative', 'future', 'pending'), true)) {
                    $statuses[] = 'draft';
                }

                $venue_id = (int) ($row['venue_id'] ?? 0);
                $venue_name = ($venue_id > 0 && isset($venue_name_map[$venue_id])) ? (string) $venue_name_map[$venue_id] : '';

                $html = bvmgr_get_plan_headliner_link_html($plan_id);
                if ($html !== '') {
                    if ($venue_name !== '') {
                        $html = '<span class="vms-muted">' . esc_html($venue_name) . ':</span> ' . $html;
                    }
                    $links[] = $html;
                }
            }
            $is_open = $has_plans || isset($open_map[$ymd]);

            if (in_array('cancelled', $statuses, true)) {
                $event_status = 'cancelled';
            } elseif (in_array('draft', $statuses, true)) {
                $event_status = 'draft';
            } elseif (in_array('confirmed', $statuses, true)) {
                $event_status = 'confirmed';
            }

            $plans_html = !empty($links)
                ? implode('<br>', $links)
                : '<span class="vms-muted">No plan</span>';
        } else {
            $plans_html = '<span class="vms-muted">No plan</span>';
        }

        $create_html = '';

        if ($is_schedulable) {
            $btn_label = $has_plans ? 'Add' : 'Create';

            $create_args = array(
                'action'    => 'vms_create_event_plan',
                'date'      => (string) $ymd,
                'venue_id'  => (int) $venue_id_param,
                '_wpnonce'  => wp_create_nonce('vms_create_event_plan_' . $ymd),
            );

            if ($has_plans) {
                $create_args['add'] = '1';
            }

            $create_url = add_query_arg($create_args, admin_url('admin-post.php'));

            $create_html = '<a class="button" href="' . esc_url($create_url) . '">' . esc_html($btn_label) . '</a>';
        }

        $status_class = '';

        if ($event_status === 'draft') {
            $status_class = 'vms-status-draft';
        } elseif ($event_status === 'confirmed') {
            $status_class = 'vms-status-confirmed';
        } elseif ($event_status === 'cancelled') {
            $status_class = 'vms-status-cancelled';
        }

        $row_state_class = $holiday_forces_open ? 'vms-venue-open' : ($has_blackout ? 'vms-venue-blackout' : ($is_open ? 'vms-venue-open' : 'vms-venue-closed'));
        $past_class = $is_past ? ' vms-venue-past' : '';

        echo '<tr class="' . esc_attr($row_state_class . $past_class . ' ' . $status_class) . '">';

        echo '<td>' . esc_html(wp_date('D, M j, Y', $cursor->getTimestamp(), $cursor->getTimezone()));

        // $badge is clunky! Needs it's own row on desktop or hidden on mobile.
        // if ($badge !== '') {
        //     echo '<div style="margin-top:6px">' . $badge . '</div>';
        // }
        echo '</td>';

        echo '<td>' . wp_kses($holiday_html . $blackout_html, vms_sch_allowed_html()) . '</td>';
        echo '<td>' . wp_kses($plans_html, vms_sch_allowed_html()) . '</td>';
        echo '<td>' . wp_kses($create_html, vms_sch_allowed_html()) . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
}

function vms_render_schedule_calendar_view(int $venue_id, string $start_ymd, string $end_ymd, array $open_map, array $plans_by_date, string $create_start_ymd, string $create_end_ymd): void
{
    $start_ts = strtotime($start_ymd);
    $end_ts   = strtotime($end_ymd);
    $invalid_bounds_notice_context = vms_schedule_get_invalid_bounds_notice_context(!$start_ts || !$end_ts);

    if (!empty($invalid_bounds_notice_context['show'])) {
        vms_schedule_render_invalid_bounds_notice($invalid_bounds_notice_context);
        return;
    }

    // Blackout notes map (range-capable) for this window.
    $blackout_notes_map = function_exists('vms_sch_season_get_blackout_notes_map')
        ? vms_sch_season_get_blackout_notes_map((int) $venue_id, $start_ymd, $end_ymd)
        : array();

    $tz = wp_timezone();
    $start_dt = new DateTime($start_ymd, $tz);
    $end_dt   = new DateTime($end_ymd, $tz);

    // Iterate month-by-month
    $cursor = new DateTime($start_dt->format('Y-m-01'), $tz);
    $end_month = new DateTime($end_dt->format('Y-m-01'), $tz);


    while ($cursor <= $end_month) {
        $month_start = new DateTime($cursor->format('Y-m-01'), $tz);
        $month_end   = new DateTime($cursor->format('Y-m-t'), $tz);

        $month_label = $month_start->format('F Y');

        $month_key = $month_start->format('Y-m'); // YYYY-MM
$scope_key = 'single';

        echo '<details class="vms-panel vms-panel-month" data-vms-month="' . esc_attr($month_key) . '" data-vms-scope="' . esc_attr($scope_key) . '">';
        echo '<summary class="vms-panel-summary">' . esc_html($month_label) . '</summary>';
        echo '<div class="vms-panel-body vms-sch-month-body">';

        // NOTE: If DOW header disappears, check CSS for rules hiding <thead> inside .vms-panel-body.
        // Calendar table uses class .vms-sch-grid; override should target that only.

        echo '<table class="widefat vms-av-grid vms-sch-grid">';

        echo '<thead><tr>';
        foreach (array('Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat') as $dow) {
            echo '<th class="vms-text-center">' . esc_html($dow) . '</th>';
        }
        echo '</tr></thead><tbody>';


        $first_w = (int) $month_start->format('w'); // 0 Sunday
        $days_in_month = (int) $month_start->format('t');

        $cell = 0;
        echo '<tr>';

        // Leading blanks
        for ($i = 0; $i < $first_w; $i++) {
            echo '<td><div class="vms-sch-cell is-outside"></div></td>';
            $cell++;
        }

        for ($day = 1; $day <= $days_in_month; $day++) {
            $ymd = $month_start->format('Y-m-') . str_pad((string) $day, 2, '0', STR_PAD_LEFT);
            $in_window = true; // this month/day is inside the view range by construction
            $in_create_window = vms_sch_is_date_in_window($ymd, $create_start_ymd, $create_end_ymd);

            $classes = array('vms-sch-cell');
            if (!$in_window) {
                $classes[] = 'is-outside';
            }

            $has_plans = $in_window && !empty($plans_by_date[$ymd]);

            $blackout_notes = ($in_window && isset($blackout_notes_map[$ymd]) && is_array($blackout_notes_map[$ymd])) ? $blackout_notes_map[$ymd] : array();
            $has_blackout = !empty($blackout_notes);
            $holiday_forces_open = function_exists('vms_sch_holiday_forces_open') ? vms_sch_holiday_forces_open((int) $venue_id, $ymd) : false;

            // Open status must reflect Season Dates (not "has plan"), plus Holiday override.
            $is_open = ($in_window && (isset($open_map[$ymd]) || $holiday_forces_open));
            if ($has_blackout && !$holiday_forces_open) {
                $is_open = false;
                $classes[] = 'is-blackout';
            }
            if ($has_blackout && $holiday_forces_open) {
                $classes[] = 'has-blackout';
            }

            if ($is_open) {
                $classes[] = 'is-open';
            }

            if ($in_window && !$is_open) {
                $classes[] = 'is-closed';
            }

            $today_ymd = (new DateTime('now', $tz))->format('Y-m-d');

            if ($ymd === $today_ymd) {
                $classes[] = 'is-today';
            } elseif ($ymd < $today_ymd) {
                $classes[] = 'is-past';
            }

            $badge = '';
            if ($in_window) {
                if ($has_blackout && !$holiday_forces_open) {
                    $badge = '<span class="vms-sch-badge is-blackout">Blackout</span>';
                } else {
                    $badge = $is_open
                        ? '<span class="vms-sch-badge is-open">Open</span>'
                        : '<span class="vms-sch-badge is-closed">Closed</span>';
                }
            }

            // Holidays: show only the holiday name(s) next to the day number.
            $holiday_html = '';
            if (function_exists('vms_sch_get_holidays_for_date')) {
                $holidays = vms_sch_get_holidays_for_date((int) $venue_id, $ymd);
                if (!empty($holidays)) {
                    $parts = array();
                    foreach ($holidays as $h) {
                        if (!is_array($h)) {
                            continue;
                        }
                        $name = trim((string) ($h['name'] ?? ''));
                        if ($name === '') {
                            continue;
                        }
                        $parts[] = '<span class="vms-sch-holiday">' . esc_html($name) . '</span>';
                    }
                    if (!empty($parts)) {
                        $holiday_html = '<div class="vms-sch-holidays">' . implode('', $parts) . '</div>';
                    }
                }
            }

            // Blackout note pills (same space as holidays; gray pill; does not force closed when holiday opens).
            $blackout_html = '';
            if ($has_blackout && $holiday_html === '') {
                $p = array();
                foreach ($blackout_notes as $n) {
                    $n = trim((string) $n);
                    $label = ($n === '') ? 'Blackout' : $n;
                    $p[] = '<span class="vms-sch-blackout">' . esc_html($label) . '</span>';
                }
                if (!empty($p)) {
                    $blackout_html = '<div class="vms-sch-blackout-stack">' . implode('', $p) . '</div>';
                }
            }

            $plans_html = '';
            if (!empty($plans_by_date[$ymd])) {

                $items = array();

                foreach ($plans_by_date[$ymd] as $row) {

                    $pid = bvmgr_get_plan_id_from_row($row);
                    if ($pid <= 0) {
                        continue;
                    }

                    // Always link to the plan edit screen (no TEC routing yet).
                    $url = get_edit_post_link((int) $pid, 'raw');
                    if (empty($url)) {
                        $url = admin_url('post.php?post=' . (int) $pid . '&action=edit');
                    }

                    $items[] = '<div class="vms-sch-plan"><a href="' . esc_url($url) . '">' . esc_html(vms_sch_plan_label((int) $pid)) . '</a></div>';
                }

                $plans_html = '<div class="vms-sch-plans">' . implode('', $items) . '</div>';
            }



            $create_html = '';

            $today_ymd = function_exists('current_time') ? (string) current_time('Y-m-d') : (string) gmdate('Y-m-d');
            $is_past   = ((string) $ymd < $today_ymd);

            // Allow scheduling if open by Season Dates OR open holiday, and not past.
            // Blackout blocks only when there is no open holiday override.
            if ($in_create_window && !$is_past && ($holiday_forces_open || isset($open_map[$ymd])) && (!$has_blackout || $holiday_forces_open)) {
                $btn_label = $has_plans ? 'Add' : 'Create';

                $create_args = array(
                    'action'    => 'vms_create_event_plan',
                    'date'      => (string) $ymd,
                    'venue_id'  => (int) $venue_id,
                    '_wpnonce'  => wp_create_nonce('vms_create_event_plan_' . $ymd),
                );

                if ($has_plans) {
                    $create_args['add'] = '1';
                }

                $create_url = add_query_arg($create_args, admin_url('admin-post.php'));

                $create_html = '<div class="vms-mt-8"><a class="button button-small" href="' . esc_url($create_url) . '">' . esc_html($btn_label) . '</a></div>';
            }


            echo '<td>';
            echo '<div class="' . esc_attr(implode(' ', $classes)) . '">';
            echo '<div class="vms-sch-top"><div class="vms-sch-daynum">' . esc_html((string) $day) . '</div>' . wp_kses($holiday_html . $blackout_html, vms_sch_allowed_html()) . '</div>';
            echo wp_kses($badge, vms_sch_allowed_html());
            echo wp_kses($plans_html, vms_sch_allowed_html());
            echo wp_kses($create_html, vms_sch_allowed_html());
            echo '</div>';
            echo '</td>';

            $cell++;

            if ($cell % 7 === 0 && $day !== $days_in_month) {
                echo '</tr><tr>';
            }
        }

        // Trailing blanks
        while ($cell % 7 !== 0) {
            echo '<td><div class="vms-sch-cell is-outside"></div></td>';
            $cell++;
        }

        echo '</tr>';
        echo '</tbody></table>';

        echo '</div>';      // closes .vms-panel-body
        echo '</details>';

        $cursor = $cursor->modify('first day of next month');
    }

    // Month accordion behavior is handled centrally in vms-admin-ui.js.
}

function vms_render_schedule_list_view_all(string $start_ymd, string $end_ymd, array $plans_by_date, array $venue_name_map): void
{
    $start_dt = vms_sch_parse_ymd($start_ymd);
    $end_dt   = vms_sch_parse_ymd($end_ymd);
    $invalid_bounds_notice_context = vms_schedule_get_invalid_bounds_notice_context(!$start_dt || !$end_dt);

    if (!empty($invalid_bounds_notice_context['show'])) {
        vms_schedule_render_invalid_bounds_notice($invalid_bounds_notice_context);
        return;
    }

    $venue_ids = function_exists('vms_sch_get_all_venue_ids') ? vms_sch_get_all_venue_ids() : array_keys($venue_name_map);
    $venue_ids = array_values(array_filter(array_map('intval', (array) $venue_ids)));

    // Precompute blackout notes for this list window (range-capable).
    $blackout_notes_by_venue = array();
    if (function_exists('vms_sch_season_get_blackout_notes_map') && !empty($venue_ids)) {
        foreach ($venue_ids as $vid) {
            $vid = (int) $vid;
            if ($vid <= 0) {
                continue;
            }
            $blackout_notes_by_venue[$vid] = vms_sch_season_get_blackout_notes_map($vid, $start_ymd, $end_ymd);
        }
    }

    echo '<table class="widefat striped vms-sch-list-all">';
    echo '<thead><tr>';
    echo '<th class="vms-col-date">Date</th>';
    echo '<th class="vms-col-venue">Venue</th>';
    echo '<th class="vms-col-holidays">Holidays</th>';
    echo '<th>Event Plans</th>';
    echo '</tr></thead><tbody>';

    for ($cursor = $start_dt; $cursor <= $end_dt; $cursor = $cursor->modify('+1 day')) {
        $ymd = $cursor->format('Y-m-d');

        $venues_html = '<span class="vms-muted">—</span>';
        $holidays_html = '<span class="vms-muted">—</span>';
        $plans_html  = '<span class="vms-muted">No plan</span>';

        // Holidays + Blackouts (all venues list): stack in the same "Holidays" column.
        // - Holidays keep the "Holiday:" label (as requested).
        // - Blackouts show as "Blackout:" lines (gray pill in calendar; label here for clarity).
        $info_lines = array();

        if (!empty($venue_ids)) {
            foreach ($venue_ids as $vid) {
                $vid = (int) $vid;
                if ($vid <= 0) {
                    continue;
                }

                $venue_label = ($vid > 0 && isset($venue_name_map[$vid])) ? (string) $venue_name_map[$vid] : ('Venue #' . $vid);

                // Holidays
                if (function_exists('vms_sch_get_holidays_for_date')) {
                    $entries = vms_sch_get_holidays_for_date($vid, $ymd);
                    if (!empty($entries)) {
                        foreach ($entries as $h) {
                            if (!is_array($h)) {
                                continue;
                            }
                            $name = trim((string) ($h['name'] ?? ''));
                            if ($name === '') {
                                continue;
                            }
                            $info_lines[] = '<span class="vms-sch-venue-tag">' . esc_html($venue_label) . '</span> <span class="vms-sch-holiday-label">Holiday:</span> ' . esc_html($name);
                        }
                    }
                }

                // Blackouts (notes)
                $bn = isset($blackout_notes_by_venue[$vid][$ymd]) && is_array($blackout_notes_by_venue[$vid][$ymd]) ? $blackout_notes_by_venue[$vid][$ymd] : array();
                if (!empty($bn)) {
                    foreach ($bn as $n) {
                        $n = trim((string) $n);
                        $label = ($n === '') ? 'Blackout' : $n;
                        $info_lines[] = '<span class="vms-sch-venue-tag">' . esc_html($venue_label) . '</span> <span class="vms-sch-blackout-label">Blackout:</span> ' . esc_html($label);
                    }
                }
            }
        }

        if (!empty($info_lines)) {
            $holidays_html = implode('<br>', $info_lines);
        }

        if (!empty($plans_by_date[$ymd])) {
            $venue_lines = array();
            $plan_lines  = array();

            foreach ($plans_by_date[$ymd] as $row) {
                $plan_id = bvmgr_get_plan_id_from_row($row);
                if ($plan_id <= 0) {
                    continue;
                }

                $venue_id = (int) ($row['venue_id'] ?? 0);
                $venue_name = ($venue_id > 0 && isset($venue_name_map[$venue_id])) ? (string) $venue_name_map[$venue_id] : '';
                $venue_lines[] = ($venue_name !== '') ? esc_html($venue_name) : '<span class="vms-muted">(unknown)</span>';

                $html = bvmgr_get_plan_headliner_link_html($plan_id);
                $plan_lines[] = ($html !== '') ? $html : '<span class="vms-muted">(untitled)</span>';
            }

            if (!empty($venue_lines)) {
                $venues_html = implode('<br>', $venue_lines);
            }
            if (!empty($plan_lines)) {
                $plans_html = implode('<br>', $plan_lines);
            }
        }

        echo '<tr>';
        echo '<td>' . esc_html(wp_date('D, M j, Y', $cursor->getTimestamp(), $cursor->getTimezone())) . '</td>';
        echo '<td>' . wp_kses($venues_html, vms_sch_allowed_html()) . '</td>';
        echo '<td>' . wp_kses($holidays_html, vms_sch_allowed_html()) . '</td>';
        echo '<td>' . wp_kses($plans_html, vms_sch_allowed_html()) . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
}


function vms_render_schedule_calendar_view_all(string $start_ymd, string $end_ymd, array $plans_by_date, array $venue_name_map): void
{
    $today_ymd = wp_date('Y-m-d'); // uses WP timezone settings

    $start_dt = vms_sch_parse_ymd($start_ymd);
    $end_dt   = vms_sch_parse_ymd($end_ymd);
    $invalid_bounds_notice_context = vms_schedule_get_invalid_bounds_notice_context(!$start_dt || !$end_dt);

    if (!empty($invalid_bounds_notice_context['show'])) {
        vms_schedule_render_invalid_bounds_notice($invalid_bounds_notice_context);
        return;
    }

    $venue_ids = function_exists('vms_sch_get_all_venue_ids') ? vms_sch_get_all_venue_ids() : array_keys($venue_name_map);
    $venue_ids = array_values(array_filter(array_map('intval', (array) $venue_ids)));

    // Normalize to first day of start month
    $cursor    = $start_dt->modify('first day of this month');
    $end_month = $end_dt->modify('first day of this month');

    while ($cursor <= $end_month) {
        $month_label = wp_date('F Y', $cursor->getTimestamp(), $cursor->getTimezone());
        $month_start = $cursor;
        $month_end   = $cursor->modify('last day of this month');

        // Precompute blackout notes for this month slice (range-capable).
        $month_from_ymd = ($month_start < $start_dt ? $start_dt : $month_start)->format('Y-m-d');
        $month_to_ymd   = ($month_end > $end_dt ? $end_dt : $month_end)->format('Y-m-d');
        $blackout_notes_by_venue = array();
        if (function_exists('vms_sch_season_get_blackout_notes_map') && !empty($venue_ids)) {
            foreach ($venue_ids as $vid) {
                $vid = (int) $vid;
                if ($vid <= 0) continue;
                $blackout_notes_by_venue[$vid] = vms_sch_season_get_blackout_notes_map($vid, $month_from_ymd, $month_to_ymd);
            }
        }

        $month_key = $cursor->format('Y-m'); // YYYY-MM

        echo '<details class="vms-av-method vms-sch-month" data-vms-month="' . esc_attr($month_key) . '" data-vms-scope="all">';
        echo '<summary><span>' . esc_html($month_label) . '</span></summary>';
        echo '<div class="vms-sch-month-body">';

        echo '<table class="widefat vms-av-grid vms-sch-grid">';
        echo '<thead><tr>';
        foreach (array('Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat') as $dow) {
            echo '<th class="vms-text-center">' . esc_html($dow) . '</th>';
        }
        echo '</tr></thead><tbody>';

        $first_dow = (int) $month_start->format('w'); // 0=Sun
        $day_dt    = $month_start;

        $cell = 0;
        echo '<tr>';

        // Leading blanks
        for ($i = 0; $i < $first_dow; $i++) {
            echo '<td>&nbsp;</td>';
            $cell++;
        }

        while ($day_dt <= $month_end) {
            $ymd = $day_dt->format('Y-m-d');

            $in_window = ($ymd >= $start_ymd && $ymd <= $end_ymd);

            $cell_classes = 'vms-sch-cell';
            if (!$in_window) {
                $cell_classes .= ' is-outside';
            } else {
                if ($ymd === $today_ymd) {
                    $cell_classes .= ' is-today';
                } elseif ($ymd < $today_ymd) {
                    $cell_classes .= ' is-past';
                }
            }

            echo '<td class="vms-valign-top">';
            echo '<div class="' . esc_attr($cell_classes) . '">';
            echo '<div class="vms-sch-daynum">' . esc_html($day_dt->format('j')) . '</div>';

            // Collect plans by venue
            $by_venue = array();
            if (!empty($plans_by_date[$ymd])) {
                foreach ($plans_by_date[$ymd] as $row) {
                    $pid = (int) ($row['plan_id'] ?? 0);
                    $vid = (int) ($row['venue_id'] ?? 0);
                    if ($pid <= 0 || $vid <= 0) {
                        continue;
                    }
                    if (!isset($by_venue[$vid])) {
                        $by_venue[$vid] = array();
                    }
                    $by_venue[$vid][] = $pid;
                }
            }

            // Collect holidays by venue (all venues calendar)
            $holidays_by_venue = array();
            if ($in_window && function_exists('vms_sch_get_holidays_for_date') && !empty($venue_ids)) {
                foreach ($venue_ids as $vid) {
                    $vid = (int) $vid;
                    if ($vid <= 0) {
                        continue;
                    }
                    $entries = vms_sch_get_holidays_for_date($vid, $ymd);
                    if (empty($entries)) {
                        continue;
                    }
                    foreach ($entries as $h) {
                        if (!is_array($h)) {
                            continue;
                        }
                        $name = trim((string) ($h['name'] ?? ''));
                        if ($name === '') {
                            continue;
                        }
                        if (!isset($holidays_by_venue[$vid])) {
                            $holidays_by_venue[$vid] = array();
                        }
                        $holidays_by_venue[$vid][] = $name;
                    }
                }
            }

            $all_vids = array_unique(array_merge(array_keys($by_venue), array_keys($holidays_by_venue)));
            sort($all_vids);

            if (!empty($all_vids)) {
                foreach ($all_vids as $vid) {
                    $venue_label = $venue_name_map[$vid] ?? ('Venue #' . $vid);

                    echo '<div class="vms-sch-planline">';
                    echo '<span class="vms-sch-venue-tag">' . esc_html($venue_label) . '</span>';

                    // Holiday items first
                    if (!empty($holidays_by_venue[$vid])) {
                        foreach ($holidays_by_venue[$vid] as $hname) {
                            echo '<div class="vms-sch-planitem vms-sch-holidayitem"><span class="vms-sch-holiday-label">Holiday:</span> ' . esc_html($hname) . '</div>';
                        }
                    }

                    // Blackout notes (range-capable)
                    $bn = isset($blackout_notes_by_venue[$vid][$ymd]) && is_array($blackout_notes_by_venue[$vid][$ymd]) ? $blackout_notes_by_venue[$vid][$ymd] : array();
                    if (!empty($bn)) {
                        foreach ($bn as $n) {
                            $n = trim((string) $n);
                            $label = ($n === '') ? 'Blackout' : $n;
                            echo '<div class="vms-sch-planitem vms-sch-blackoutitem"><span class="vms-sch-blackout-label">Blackout:</span> ' . esc_html($label) . '</div>';
                        }
                    }

                    // Then plan links
                    if (!empty($by_venue[$vid])) {
                        foreach ($by_venue[$vid] as $pid) {
                            echo '<div class="vms-sch-planitem">';
                            echo '<a class="vms-sch-planlink" href="' . esc_url(get_edit_post_link($pid, '')) . '">' 
                                . esc_html(vms_sch_plan_label($pid))
                                . '</a>';
                            echo '</div>';
                        }
                    }

                    echo '</div>';
                }
            } elseif ($in_window) {
                echo '<div class="vms-muted"> </div>';
            }

            echo '</div>';
            echo '</td>';

            $cell++;
            if ($cell % 7 === 0 && $day_dt < $month_end) {
                echo '</tr><tr>';
            }

            $day_dt = $day_dt->modify('+1 day');
        }

        // Trailing blanks
        while ($cell % 7 !== 0) {
            echo '<td>&nbsp;</td>';
            $cell++;
        }

        echo '</tr>';
        echo '</tbody></table>';
        echo '</div></details>';

        $cursor = $cursor->modify('first day of next month');
    }

    // Month accordion behavior is handled centrally in vms-admin-ui.js.
}
