<?php

/**
 * VMS Vendor Portal (Procedural)
 *
 * Shortcode: [vms_vendor_portal]
 *
 * Tabs:
 *  - dashboard
 *  - profile
 *  - tax-profile
 *  - availability
 *  - tech
 *
 * Notes:
 *  - Mobile-first availability UI: tap a day to cycle (— → Available → Unavailable)
 *  - Stores manual overrides in: _vms_availability_manual (array: YYYY-MM-DD => available|unavailable)
 *  - Optional ICS data:
 *      _vms_ics_url (string)
 *      _vms_ics_autosync (0|1)
 *      _vms_ics_last_sync (timestamp)
 *      _vms_ics_unavailable (array of YYYY-MM-DD)  (if your ICS sync module stores this)
 *  - “Preferred method” (for collapsing sections):
 *      _vms_availability_preferred_method = manual|pattern|ics
 */

if (!defined('ABSPATH')) {
    exit;
}
   
// Optional module includes
$bvmgr_vendor_tax_profile_file = plugin_dir_path(__FILE__) . 'vendor-tax-profile.php';
if (file_exists($bvmgr_vendor_tax_profile_file)) {
    require_once $bvmgr_vendor_tax_profile_file;
}

/**
 * Small, theme-agnostic notices for the portal.
 */
if (!function_exists('bvmgr_portal_notice')) {
    function bvmgr_portal_notice(string $type, string $msg): string
    {
        $type = ($type === 'success' || $type === 'warning') ? $type : 'error';
        return '<div class="vms-notice vms-notice-' . esc_attr($type) . '">' . esc_html($msg) . '</div>';
    }
}

/**
 * Flag vendor updates in a consistent way.
 * (Uses whichever helper exists in your project; falls back safely.)
 */

if (!function_exists('vms_vendor_portal_admin_can_preview')) {
    function vms_vendor_portal_admin_can_preview(): bool
    {
        return is_user_logged_in() && current_user_can('manage_options');
    }
}

if (!function_exists('vms_vendor_portal_preview_request_absint')) {
    function vms_vendor_portal_preview_request_absint(string $key): int
    {
        return bvmgr_request_read_absint($_REQUEST, $key); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin preview vendor selection is read-only request state and separately nonce-verified.
    }
}

if (!function_exists('vms_vendor_portal_query_key')) {
    function vms_vendor_portal_query_key(string $key): string
    {
        return bvmgr_request_read_key($_GET, $key); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Vendor portal tab and filter query args are read-only navigation state.
    }
}

if (!function_exists('vms_vendor_portal_query_absint')) {
    function vms_vendor_portal_query_absint(string $key): int
    {
        return bvmgr_request_read_absint($_GET, $key); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Vendor portal tab and filter query args are read-only navigation state.
    }
}

if (!function_exists('vms_vendor_portal_post_absint')) {
    function vms_vendor_portal_post_absint(string $key): int
    {
        return bvmgr_request_read_absint($_POST, $key); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Portal action fields are only used inside action-specific nonce-verified submission handlers.
    }
}

if (!function_exists('vms_vendor_portal_post_return_url')) {
    function vms_vendor_portal_post_return_url(string $fallback): string
    {
        return bvmgr_request_local_redirect($fallback, bvmgr_request_read_scalar($_POST, 'return_url')); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Portal return URLs are only used inside action-specific nonce-verified submission handlers.
    }
}

if (!function_exists('vms_vendor_portal_post_array')) {
    function vms_vendor_portal_post_array(string $key): array
    {
        // phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $value = isset($_POST[$key]) && is_array($_POST[$key]) ? wp_unslash($_POST[$key]) : array();
        // phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        return is_array($value) ? $value : array();
    }
}

if (!function_exists('vms_vendor_portal_read_uploaded_file_request')) {
    function vms_vendor_portal_read_uploaded_file_request(string $field_name)
    {
        return bvmgr_upload_read_file($_FILES, $field_name); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Upload validation helpers are only reached from nonce-verified portal submission handlers.
    }
}

if (!function_exists('vms_vendor_portal_get_preview_vendor_id')) {
    function vms_vendor_portal_get_preview_vendor_id(): int
    {
        if (!function_exists('vms_vendor_portal_admin_can_preview') || !vms_vendor_portal_admin_can_preview()) {
            return 0;
        }

        $raw_vendor_id = 0;
        if (array_key_exists('vms_preview_vendor', $_REQUEST)) {
            $raw_vendor_id = vms_vendor_portal_preview_request_absint('vms_preview_vendor');
        } elseif (array_key_exists('preview_vendor_id', $_REQUEST)) {
            $raw_vendor_id = vms_vendor_portal_preview_request_absint('preview_vendor_id');
        }

        if ($raw_vendor_id <= 0) {
            return 0;
        }

        $raw_nonce = (isset($_REQUEST['vms_preview_nonce']) && !is_array($_REQUEST['vms_preview_nonce']))
            ? sanitize_text_field(wp_unslash((string) $_REQUEST['vms_preview_nonce']))
            : '';

        if ($raw_nonce === '' || !wp_verify_nonce($raw_nonce, 'vms_preview_vendor_portal_' . $raw_vendor_id)) {
            return 0;
        }

        $vendor = get_post($raw_vendor_id);
        if (!$vendor || $vendor->post_type !== 'vms_vendor' || $vendor->post_status === 'trash') {
            return 0;
        }

        return $raw_vendor_id;
    }
}

if (!function_exists('vms_vendor_portal_get_preview_query_args')) {
    function vms_vendor_portal_get_preview_query_args(int $vendor_id = 0): array
    {
        $vendor_id = $vendor_id > 0 ? $vendor_id : vms_vendor_portal_get_preview_vendor_id();
        if ($vendor_id <= 0 || !function_exists('vms_vendor_portal_admin_can_preview') || !vms_vendor_portal_admin_can_preview()) {
            return array();
        }

        return array(
            'vendor_id' => $vendor_id,
            'vms_preview_vendor' => $vendor_id,
            'vms_preview_nonce' => wp_create_nonce('vms_preview_vendor_portal_' . $vendor_id),
        );
    }
}

if (!function_exists('vms_vendor_portal_page_url')) {
    function vms_vendor_portal_page_url(array $query_args = array()): string
    {
        $slug = 'vendor-portal';
        if (function_exists('bvmgr_required_public_pages')) {
            $pages = (array) bvmgr_required_public_pages();
            $slug = sanitize_title((string) ($pages['vendor_portal']['slug'] ?? $slug));
            if ($slug === '') {
                $slug = 'vendor-portal';
            }
        }

        $url = '';
        $page = get_page_by_path($slug);
        if ($page instanceof WP_Post) {
            $url = get_permalink($page);
        }
        if (!is_string($url) || $url === '') {
            $url = home_url('/' . trim($slug, '/') . '/');
        }

        if (!empty($query_args)) {
            $url = add_query_arg($query_args, $url);
        }

        return (string) $url;
    }
}

if (!function_exists('vms_vendor_portal_application_page_url')) {
    function vms_vendor_portal_application_page_url(array $query_args = array()): string
    {
        if (function_exists('vms_vendor_app_get_application_page_url')) {
            return (string) vms_vendor_app_get_application_page_url($query_args);
        }

        $url = home_url('/vendor-application/');
        if (!empty($query_args)) {
            $url = add_query_arg($query_args, $url);
        }

        return (string) $url;
    }
}

if (!function_exists('vms_vendor_portal_login_redirect_url')) {
    function vms_vendor_portal_login_redirect_url(bool $with_marker = true): string
    {
        $query_args = array();
        if ($with_marker) {
            $query_args['vms_vendor_portal_login'] = '1';
        }

        return vms_vendor_portal_page_url($query_args);
    }
}

if (!function_exists('vms_vendor_portal_user_has_active_links')) {
    function vms_vendor_portal_user_has_active_links(int $user_id): bool
    {
        $user_id = (int) $user_id;
        if ($user_id <= 0 || !function_exists('vms_get_active_vendor_ids_for_user')) {
            return false;
        }

        return !empty(vms_get_active_vendor_ids_for_user($user_id));
    }
}

if (!function_exists('vms_vendor_portal_requested_login_origin')) {
    function vms_vendor_portal_requested_login_origin(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        $query = wp_parse_url($url, PHP_URL_QUERY);
        if (!is_string($query) || $query === '') {
            return false;
        }

        parse_str($query, $args);
        return isset($args['vms_vendor_portal_login']) && (string) $args['vms_vendor_portal_login'] === '1';
    }
}

if (!function_exists('vms_vendor_portal_filter_login_redirect')) {
    function vms_vendor_portal_filter_login_redirect(string $redirect_to, string $requested_redirect_to, $user): string
    {
        if (!($user instanceof WP_User)) {
            return $redirect_to;
        }

        $requested_redirect_to = is_string($requested_redirect_to) ? $requested_redirect_to : '';
        $redirect_to = is_string($redirect_to) ? $redirect_to : '';

        $from_vendor_portal = vms_vendor_portal_requested_login_origin($requested_redirect_to)
            || vms_vendor_portal_requested_login_origin($redirect_to);
        if (!$from_vendor_portal) {
            return $redirect_to;
        }

        if (user_can($user, 'manage_options')) {
            return $redirect_to;
        }

        if (function_exists('vms_vendor_portal_user_has_active_links') && vms_vendor_portal_user_has_active_links((int) $user->ID)) {
            return vms_vendor_portal_page_url(array('tab' => 'dashboard'));
        }

        return vms_vendor_portal_page_url();
    }
}
add_filter('login_redirect', 'vms_vendor_portal_filter_login_redirect', 20, 3);

if (!function_exists('vms_vendor_portal_render_my_account_notice')) {
    function vms_vendor_portal_render_my_account_notice(): void
    {
        if (!is_user_logged_in()) {
            return;
        }

        $user_id = (int) get_current_user_id();
        if (!function_exists('vms_vendor_portal_user_has_active_links') || !vms_vendor_portal_user_has_active_links($user_id)) {
            return;
        }

        $portal_url = function_exists('vms_vendor_portal_page_url')
            ? vms_vendor_portal_page_url(array('tab' => 'dashboard'))
            : home_url('/vendor-portal/?tab=dashboard');

        echo '<section class="vms-vendor-account-guide vms-notice vms-notice-success">';
        echo '<p><strong>' . esc_html__('Looking for your Vendor Portal?', 'backstage-venue-manager') . '</strong></p>';
        echo '<p>' . esc_html__('Vendor tools and updates live in the Vendor Portal. This My Account area still remains available for normal customer orders, tickets, and account settings.', 'backstage-venue-manager') . '</p>';
        echo '<p class="vms-vendor-account-guide__actions"><a class="button" href="' . esc_url($portal_url) . '">' . esc_html__('Open Vendor Portal', 'backstage-venue-manager') . '</a></p>';
        echo '</section>';
    }
}
add_action('woocommerce_account_dashboard', 'vms_vendor_portal_render_my_account_notice', 5);

if (!function_exists('vms_vendor_portal_render_preview_hidden_fields')) {
    function vms_vendor_portal_render_preview_hidden_fields(int $vendor_id = 0): void
    {
        $args = function_exists('vms_vendor_portal_get_preview_query_args')
            ? vms_vendor_portal_get_preview_query_args($vendor_id)
            : array();

        if (empty($args)) {
            return;
        }

        foreach ($args as $key => $value) {
            if ($key === 'vendor_id') {
                continue;
            }
            echo '<input type="hidden" name="' . esc_attr((string) $key) . '" value="' . esc_attr((string) $value) . '">';
        }
    }
}

if (!function_exists('vms_vendor_portal_allowed_tabs')) {
    function vms_vendor_portal_allowed_tabs(): array
    {
        $tabs = apply_filters(
            'vms_vendor_portal_allowed_tabs',
            array('dashboard', 'profile', 'tax-profile', 'history', 'availability', 'opportunities', 'all-vendors', 'tech')
        );

        if (!is_array($tabs)) {
            $tabs = array();
        }

        $tabs = array_values(array_unique(array_filter(array_map('sanitize_key', $tabs))));
        if (empty($tabs)) {
            $tabs = array('dashboard');
        }

        return $tabs;
    }
}

if (!function_exists('vms_vendor_portal_get_requested_tab')) {
    function vms_vendor_portal_get_requested_tab(string $default = 'dashboard'): string
    {
        $allowed_tabs = vms_vendor_portal_allowed_tabs();
        $default = sanitize_key($default);
        if ($default === '' || !in_array($default, $allowed_tabs, true)) {
            $default = in_array('dashboard', $allowed_tabs, true) ? 'dashboard' : (string) $allowed_tabs[0];
        }

        $requested_tab = vms_vendor_portal_query_key('tab');
        if ($requested_tab === '') {
            return $default;
        }

        return in_array($requested_tab, $allowed_tabs, true) ? $requested_tab : $default;
    }
}

if (!function_exists('vms_vendor_portal_get_requested_vendor_id')) {
    function vms_vendor_portal_get_requested_vendor_id(): int
    {
        return vms_vendor_portal_query_absint('vendor_id');
    }
}

if (!function_exists('vms_vendor_portal_get_requested_lookback')) {
    function vms_vendor_portal_get_requested_lookback(array $allowed, int $default): int
    {
        $allowed = array_values(array_unique(array_map('absint', $allowed)));
        if (!in_array($default, $allowed, true)) {
            $default = !empty($allowed) ? (int) $allowed[0] : 0;
        }

        $requested = vms_vendor_portal_query_absint('lb');
        if ($requested <= 0) {
            return $default;
        }

        return in_array($requested, $allowed, true) ? $requested : $default;
    }
}


if (!function_exists('vms_vendor_flag_vendor_update')) {
    function vms_vendor_flag_vendor_update($vendor_id, $context = '', array $meta = array()): void
    {
        $vendor_id = (int) $vendor_id;
        if ($vendor_id <= 0) return;

        $context = sanitize_key((string) $context);
        $user_id = (int) get_current_user_id();

        // Preferred helper (newer)
        if (function_exists('vms_vendor_flag_updated')) {
            vms_vendor_flag_updated($vendor_id, $user_id, (string) $context);
        } elseif (function_exists('bvmgr_vendor_mark_profile_updated')) {
            bvmgr_vendor_mark_profile_updated($vendor_id, $user_id, array(), (string) $context);
        } else {
            // Absolute fallback (won’t break anything)
            update_post_meta($vendor_id, '_vms_vendor_last_updated_at', current_time('mysql'));
            update_post_meta($vendor_id, '_vms_vendor_last_updated_by', $user_id);
            update_post_meta($vendor_id, '_vms_vendor_needs_review', 1);
            if ($context !== '') {
                update_post_meta($vendor_id, '_vms_vendor_last_update_context', sanitize_key($context));
            }
        }

        if ($context !== '' && function_exists('vms_vendor_submission_dispatch_alert') && function_exists('vms_vendor_submission_context_is_document') && vms_vendor_submission_context_is_document($context)) {
            $meta['submitted_by_user_id'] = $user_id;
            vms_vendor_submission_dispatch_alert($vendor_id, $context, $meta);
        }
    }
}

/**
 * Canonical vendor-availability mapping from Event Plan status.
 *
 * VEND-07 mapping:
 * - Draft/Ready => Tentative
 * - Published   => Booked
 *
 * Legacy/fallback handling is intentionally preserved:
 * - tentative/confirmed/unknown => tentative (defensive no-double-booking behavior)
 * - cancelled/archived => no busy marker
 */
if (!function_exists('vms_vendor_availability_busy_source_from_plan_status')) {
    function vms_vendor_availability_busy_source_from_plan_status(string $status): string
    {
        $status = sanitize_key((string) $status);
        if ($status === 'canceled') {
            $status = 'cancelled';
        }

        if ($status === 'published') {
            return 'booked';
        }

        if ($status === 'draft' || $status === 'ready') {
            return 'tentative';
        }

        if ($status === 'cancelled' || $status === 'archived') {
            return '';
        }

        if (in_array($status, array('tentative', 'confirmed'), true)) {
            return 'tentative';
        }

        return 'tentative';
    }
}

if (!function_exists('vms_vendor_availability_busy_source_for_plan')) {
    function vms_vendor_availability_busy_source_for_plan(int $plan_id): string
    {
        $plan_id = absint($plan_id);
        if ($plan_id <= 0) {
            return '';
        }

        $status = '';
        if (function_exists('bvmgr_event_plan_get_status')) {
            $status = (string) bvmgr_event_plan_get_status($plan_id, 'schedule_admin');
        }

        if ($status === '') {
            $k_status = function_exists('bvmgr_meta_key')
                ? (string) bvmgr_meta_key('event_plan', 'status')
                : '_vms_event_plan_status';
            if ($k_status === '') {
                $k_status = '_vms_event_plan_status';
            }
            $status = (string) get_post_meta($plan_id, $k_status, true);
        }

        return vms_vendor_availability_busy_source_from_plan_status($status);
    }
}



if (!function_exists('vms_vendor_portal_format_money')) {
    function vms_vendor_portal_format_money(float $amount): string
    {
        if (function_exists('bvmgr_ticketing_format_money')) {
            return (string) bvmgr_ticketing_format_money($amount);
        }
        if (function_exists('wc_price')) {
            return (string) wc_price($amount);
        }
        return '$' . number_format($amount, 2, '.', ',');
    }
}

if (!function_exists('vms_vendor_portal_get_ticket_stats_payload')) {
    function vms_vendor_portal_get_ticket_stats_payload(int $plan_id): array
    {
        $key = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'ticket_stats') : '_vms_ticket_stats_v1';
        if ($key === '') {
            $key = '_vms_ticket_stats_v1';
        }

        $raw = get_post_meta($plan_id, $key, true);
        return is_array($raw) ? $raw : array();
    }
}

if (!function_exists('vms_vendor_portal_format_stats_updated_label')) {
    function vms_vendor_portal_format_stats_updated_label(array $stats_payload): string
    {
        $timezone = wp_timezone();
        $timestamp = 0;

        foreach (array('computed_at_gmt', 'updated_at_gmt', 'pulled_at_gmt', 'computed_at', 'updated_at', 'pulled_at') as $key) {
            if (!array_key_exists($key, $stats_payload)) {
                continue;
            }

            $value = $stats_payload[$key];
            $is_gmt = (substr((string) $key, -4) === '_gmt');

            if (is_numeric($value)) {
                $candidate = (int) round((float) $value);
                if ($candidate > 999999999999) {
                    $candidate = (int) floor($candidate / 1000);
                }
                if ($candidate > 0) {
                    $timestamp = $candidate;
                    break;
                }
            }

            if (is_string($value)) {
                $value = trim($value);
                if ($value === '') {
                    continue;
                }

                try {
                    if ($is_gmt) {
                        $dt = new DateTimeImmutable($value, new DateTimeZone('UTC'));
                    } else {
                        $dt = new DateTimeImmutable($value, $timezone);
                    }
                    $candidate = $dt->getTimestamp();
                    if ($candidate > 0) {
                        $timestamp = $candidate;
                        break;
                    }
                } catch (Exception $e) {
                    $candidate = strtotime($value . ($is_gmt ? ' UTC' : ''));
                    if ($candidate) {
                        $timestamp = (int) $candidate;
                        break;
                    }
                }
            }
        }

        if ($timestamp <= 0) {
            return '';
        }

        return wp_date('M j, Y g:ia', $timestamp, $timezone);
    }
}


if (!function_exists('vms_vendor_portal_get_admissions_headcount')) {
    function vms_vendor_portal_get_admissions_headcount(int $plan_id): int
    {
        $plan_id = absint($plan_id);
        if ($plan_id <= 0 || !function_exists('vms_admission_table_entries')) {
            return 0;
        }

        global $wpdb;
        $table = vms_admission_table_entries();
        if (!$wpdb || !is_string($table) || $table === '') {
            return 0;
        }

        static $table_exists_cache = array();
        if (!array_key_exists($table, $table_exists_cache)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Admissions schema readiness is reused only within this request, and the prepared SHOW probe gates the plugin-owned aggregate table.
            $table_exists_cache[$table] = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table);
        }
        if (empty($table_exists_cache[$table])) {
            return 0;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Portal headcount must read current plugin-owned admission rows; the prepared aggregate intentionally has no persistent cache.
        return max(0, (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(CASE WHEN status <> 'canceled' THEN party_size ELSE 0 END), 0)
             FROM %i
             WHERE event_plan_id = %d",
            $table,
            $plan_id
        )));
    }
}

if (!function_exists('vms_vendor_portal_get_ticket_product_ids')) {
    function vms_vendor_portal_get_ticket_product_ids(int $plan_id, array $stats_payload = array()): array
    {
        $plan_id = absint($plan_id);
        if ($plan_id <= 0) {
            return array();
        }

        static $cache = array();
        if (isset($cache[$plan_id])) {
            return $cache[$plan_id];
        }

        $k_pids = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'ticket_product_ids') : '_vms_ticket_product_ids_v1';
        if ($k_pids === '') {
            $k_pids = '_vms_ticket_product_ids_v1';
        }

        $pids = get_post_meta($plan_id, $k_pids, true);
        if (!is_array($pids)) {
            $pids = array();
        }

        if (isset($stats_payload['ticket_product_ids']) && is_array($stats_payload['ticket_product_ids'])) {
            $pids = array_merge($pids, $stats_payload['ticket_product_ids']);
        }
        if (isset($stats_payload['detected_product_ids']) && is_array($stats_payload['detected_product_ids'])) {
            $pids = array_merge($pids, $stats_payload['detected_product_ids']);
        }
        if (isset($stats_payload['manual_product_ids']) && is_array($stats_payload['manual_product_ids'])) {
            $pids = array_merge($pids, $stats_payload['manual_product_ids']);
        }

        if (function_exists('bvmgr_ticketing_get_manual_product_ids')) {
            $pids = array_merge($pids, bvmgr_ticketing_get_manual_product_ids($plan_id));
        } else {
            $k_manual = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'ticket_manual_product_ids') : '_vms_ticket_manual_product_ids_v1';
            if ($k_manual === '') {
                $k_manual = '_vms_ticket_manual_product_ids_v1';
            }
            $manual = get_post_meta($plan_id, $k_manual, true);
            if (is_array($manual)) {
                $pids = array_merge($pids, $manual);
            }
        }

        $tec_id = 0;
        if (function_exists('bvmgr_ticketing_b_get_linked_tec_event_id')) {
            $tec_id = (int) bvmgr_ticketing_b_get_linked_tec_event_id($plan_id);
        }
        if ($tec_id <= 0) {
            $k_tec = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'tec_event_id') : '_vms_tec_event_id';
            if ($k_tec === '') {
                $k_tec = '_vms_tec_event_id';
            }
            $tec_id = (int) get_post_meta($plan_id, $k_tec, true);
        }

        if ($tec_id > 0) {
            if (function_exists('bvmgr_ticketing_b_get_event_ticket_products')) {
                $pids = array_merge($pids, bvmgr_ticketing_b_get_event_ticket_products($tec_id));
            } elseif (function_exists('bvmgr_ticketing_get_ticket_product_ids_for_tec_event')) {
                $pids = array_merge($pids, bvmgr_ticketing_get_ticket_product_ids_for_tec_event($tec_id));
            }
        }

        $pids = array_values(array_unique(array_filter(array_map('absint', $pids))));
        sort($pids, SORT_NUMERIC);
        $cache[$plan_id] = $pids;

        return $cache[$plan_id];
    }
}

if (!function_exists('vms_vendor_portal_product_is_paid_admission')) {
    function vms_vendor_portal_product_is_paid_admission(int $product_id): bool
    {
        $product_id = absint($product_id);
        if ($product_id <= 0) {
            return false;
        }

        if (function_exists('bvmgr_ticketing_v2_product_is_entitlement') && bvmgr_ticketing_v2_product_is_entitlement($product_id)) {
            return false;
        }

        if (function_exists('bvmgr_ticketing_v2_meta_get')) {
            $sr_type = (string) bvmgr_ticketing_v2_meta_get($product_id, '_sr_addon_type');
            $sr_req  = (string) bvmgr_ticketing_v2_meta_get($product_id, '_sr_required_qualifiers_per_unit');
            $sr_unit = (string) bvmgr_ticketing_v2_meta_get($product_id, '_sr_addon_unit_label');
            if ($sr_type !== '' || $sr_req !== '' || $sr_unit !== '') {
                return false;
            }
        }

        if (!function_exists('wc_get_product')) {
            return true;
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            $parent_id = absint(wp_get_post_parent_id($product_id));
            if ($parent_id > 0) {
                $product = wc_get_product($parent_id);
            }
        }
        if (!$product) {
            return true;
        }

        return ((float) $product->get_price()) > 0.0;
    }
}

if (!function_exists('vms_vendor_portal_get_paid_ticket_product_ids')) {
    function vms_vendor_portal_get_paid_ticket_product_ids(array $product_ids): array
    {
        $product_ids = array_values(array_unique(array_filter(array_map('absint', $product_ids))));
        if (empty($product_ids)) {
            return array();
        }

        $filtered = array();
        foreach ($product_ids as $product_id) {
            if (!vms_vendor_portal_product_is_paid_admission($product_id)) {
                continue;
            }
            $filtered[] = $product_id;
        }

        $filtered = array_values(array_unique($filtered));
        sort($filtered, SORT_NUMERIC);
        return $filtered;
    }
}

if (!function_exists('vms_vendor_portal_get_ticket_sales_snapshot')) {
    function vms_vendor_portal_get_ticket_sales_snapshot(int $plan_id): array
    {
        $plan_id = absint($plan_id);
        if ($plan_id <= 0) {
            return array();
        }

        static $cache = array();
        if (isset($cache[$plan_id])) {
            return $cache[$plan_id];
        }

        $stats_payload = vms_vendor_portal_get_ticket_stats_payload($plan_id);

        $cached_qty = null;
        if (array_key_exists('qty_sold', $stats_payload) && is_numeric($stats_payload['qty_sold'])) {
            $cached_qty = max(0, (int) $stats_payload['qty_sold']);
        } elseif (array_key_exists('qty', $stats_payload) && is_numeric($stats_payload['qty'])) {
            $cached_qty = max(0, (int) $stats_payload['qty']);
        }

        $resolved = $stats_payload;
        $resolved['source_mode'] = 'cached';
        $resolved['count_basis'] = 'paid_admission_tickets';

        $all_pids = vms_vendor_portal_get_ticket_product_ids($plan_id, $stats_payload);
        $countable_pids = vms_vendor_portal_get_paid_ticket_product_ids($all_pids);
        if (!empty($countable_pids) && function_exists('bvmgr_ticketing_compute_stats')) {
            $live = (array) bvmgr_ticketing_compute_stats($countable_pids);
            $live_qty = null;
            if (array_key_exists('qty_sold', $live) && is_numeric($live['qty_sold'])) {
                $live_qty = max(0, (int) $live['qty_sold']);
            } elseif (array_key_exists('qty', $live) && is_numeric($live['qty'])) {
                $live_qty = max(0, (int) $live['qty']);
            }

            $provider = sanitize_key((string) ($stats_payload['provider'] ?? ''));
            $should_prefer_live = ($live_qty !== null) && (
                $cached_qty === null
                || $provider === 'pending_refresh'
                || $live_qty !== $cached_qty
            );

            if ($should_prefer_live) {
                $resolved = array_merge($stats_payload, $live);
                $resolved['ticket_product_ids'] = $countable_pids;
                $resolved['source_mode'] = 'live';
            }
        }

        if (!isset($resolved['ticket_product_ids']) || !is_array($resolved['ticket_product_ids'])) {
            $resolved['ticket_product_ids'] = $countable_pids;
        }

        $resolved['all_ticket_product_ids'] = $all_pids;
        $resolved['excluded_ticket_product_ids'] = array_values(array_diff($all_pids, $countable_pids));

        $cache[$plan_id] = $resolved;
        return $cache[$plan_id];
    }
}


if (!function_exists('vms_vendor_portal_maybe_load_data_tools_reporting')) {
    function vms_vendor_portal_maybe_load_data_tools_reporting(): bool
    {
        static $attempted = false;
        static $loaded = false;

        if ($loaded) {
            return true;
        }
        if ($attempted) {
            return false;
        }
        $attempted = true;

        if (function_exists('vms_dt_reporting_build_website_detail_rows') && function_exists('vms_dt_reporting_build_square_line_evidence')) {
            $loaded = true;
            return true;
        }

        $admin_dir = '';
        if (defined('VMS_DT_ADMIN_DIR') && is_string(VMS_DT_ADMIN_DIR) && VMS_DT_ADMIN_DIR !== '') {
            $admin_dir = untrailingslashit(VMS_DT_ADMIN_DIR);
        } elseif (defined('WP_PLUGIN_DIR') && is_string(WP_PLUGIN_DIR) && WP_PLUGIN_DIR !== '') {
            $candidate = untrailingslashit(WP_PLUGIN_DIR) . '/vms-data-tools/includes/admin';
            if (is_dir($candidate)) {
                $admin_dir = $candidate;
            }
        }

        if ($admin_dir === '') {
            return false;
        }

        $revenue_file = $admin_dir . '/page-revenue-intelligence.php';
        $reporting_file = $admin_dir . '/page-reporting-module.php';
        if (is_readable($revenue_file)) {
            require_once $revenue_file;
        }
        if (is_readable($reporting_file)) {
            require_once $reporting_file;
        }

        $loaded = function_exists('vms_dt_reporting_build_website_detail_rows') && function_exists('vms_dt_reporting_build_square_line_evidence');
        return $loaded;
    }
}

if (!function_exists('vms_vendor_portal_get_data_tools_sales_snapshot')) {
    function vms_vendor_portal_get_data_tools_sales_snapshot(int $plan_id): array
    {
        $plan_id = absint($plan_id);
        if ($plan_id <= 0) {
            return array();
        }

        static $cache = array();
        if (isset($cache[$plan_id])) {
            return $cache[$plan_id];
        }

        if (!vms_vendor_portal_maybe_load_data_tools_reporting()) {
            $cache[$plan_id] = array();
            return $cache[$plan_id];
        }

        $event_date = (string) get_post_meta($plan_id, '_vms_event_date', true);
        $website = (array) vms_dt_reporting_build_website_detail_rows($plan_id);
        $website_ticket_rows = array();

        foreach ((array) ($website['ticket_rows'] ?? array()) as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $sold_date = (string) ($entry['sold_date'] ?? '');
            if ($sold_date === '') {
                $sold_date = substr((string) ($entry['sold_datetime'] ?? ''), 0, 10);
            }
            if ($sold_date !== '' && $event_date !== '' && $sold_date > $event_date) {
                continue;
            }
            $website_ticket_rows[] = $entry;
        }

        $square_filters = array(
            'event_plan_id' => $plan_id,
            'square_scope_mode' => 'full_day',
            'sold_from' => '',
            'sold_to' => '',
        );
        $square = (array) vms_dt_reporting_build_square_line_evidence($plan_id, $square_filters);
        $ticket_sources = function_exists('vms_dt_reporting_build_ticket_source_rollup')
            ? (array) vms_dt_reporting_build_ticket_source_rollup(
                array(),
                array(
                    'website' => array('ticket_rows' => $website_ticket_rows),
                    'square' => $square,
                )
            )
            : array();

        $online_qty = max(0, (int) ($ticket_sources['website_paid_ticket_qty'] ?? 0));
        $online_net_cents = max(0, (int) ($ticket_sources['website_paid_ticket_revenue_cents'] ?? 0));
        $excluded_free_online_qty = max(0, (int) ($ticket_sources['website_free_ticket_qty'] ?? 0));
        $door_qty = max(0, (int) ($ticket_sources['square_ticket_qty'] ?? 0));
        $door_paid_qty = max(0, (int) ($ticket_sources['square_paid_ticket_qty'] ?? 0));
        $door_free_qty = max(0, (int) ($ticket_sources['square_free_ticket_qty'] ?? 0));
        $door_gross_cents = max(0, (int) ($ticket_sources['square_paid_ticket_revenue_cents'] ?? 0));
        $website_rows_seen = max(0, (int) ($ticket_sources['website_rows_seen'] ?? count($website_ticket_rows)));
        $door_rows_seen = max(0, (int) ($ticket_sources['square_rows_seen'] ?? 0));

        $headcount = max(0, (int) ($ticket_sources['ticketed_attendance_qty'] ?? ($online_qty + $excluded_free_online_qty + $door_qty)));
        $sales_cents = max(0, (int) ($ticket_sources['paid_ticket_revenue_cents'] ?? ($online_net_cents + $door_gross_cents)));
        $free_ticket_qty_total = max(0, (int) ($ticket_sources['free_ticket_qty_total'] ?? ($excluded_free_online_qty + $door_free_qty)));
        $has_countable_data = !empty($ticket_sources['has_countable_data'])
            || ($headcount > 0)
            || ($website_rows_seen > 0)
            || ($door_rows_seen > 0)
            || ($free_ticket_qty_total > 0);

        $label = __('Paid ticket sales', 'backstage-venue-manager');
        if ($free_ticket_qty_total > 0) {
            $label = __('Ticketed attendance', 'backstage-venue-manager');
        } elseif ($door_paid_qty > 0) {
            $label = __('Paid ticket sales + counted door sales', 'backstage-venue-manager');
        }

        $cache[$plan_id] = array(
            'headcount' => $headcount,
            'online_qty' => max(0, $online_qty),
            'online_net_cents' => max(0, $online_net_cents),
            'door_qty' => max(0, $door_qty),
            'door_paid_qty' => $door_paid_qty,
            'door_free_qty' => $door_free_qty,
            'door_gross_cents' => max(0, $door_gross_cents),
            'sales_cents' => $sales_cents,
            'excluded_free_online_qty' => max(0, $excluded_free_online_qty),
            'excluded_free_ticket_qty_total' => $free_ticket_qty_total,
            'paid_ticket_qty_total' => max(0, (int) ($ticket_sources['paid_ticket_qty_total'] ?? ($online_qty + $door_paid_qty))),
            'free_ticket_qty_total' => $free_ticket_qty_total,
            'ticketed_attendance_qty' => $headcount,
            'has_countable_data' => $has_countable_data,
            'source_mode' => 'data_tools_live',
            'source' => 'data_tools_merged_ticket_sales',
            'label' => $label,
            'updated_label' => wp_date('M j, Y g:ia', current_time('timestamp'), wp_timezone()),
            'warnings' => array_values(array_unique(array_filter(array_map('strval', (array) ($square['warnings'] ?? array()))))),
            'errors' => array_values(array_unique(array_filter(array_map('strval', (array) ($square['errors'] ?? array()))))),
        );

        return $cache[$plan_id];
    }
}


if (!function_exists('vms_vendor_portal_get_guest_admissions_count')) {
    function vms_vendor_portal_get_guest_admissions_count(int $plan_id): int
    {
        $plan_id = absint($plan_id);
        if ($plan_id <= 0 || !function_exists('vms_admission_table_entries')) {
            return 0;
        }

        static $cache = array();
        if (isset($cache[$plan_id])) {
            return $cache[$plan_id];
        }

        global $wpdb;
        $guest_qty = 0;
        $table = vms_admission_table_entries();
        if ($wpdb && is_string($table) && $table !== '') {
            static $table_exists_cache = array();
            if (!array_key_exists($table, $table_exists_cache)) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Guest-admission schema readiness is reused only within this request, and the prepared SHOW probe gates the plugin-owned aggregate table.
                $table_exists_cache[$table] = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table);
            }
            if (!empty($table_exists_cache[$table])) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- The first per-plan guest count reads current plugin-owned admission rows before the existing request-local result cache is populated.
                $guest_qty = max(0, (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COALESCE(SUM(CASE WHEN status <> 'canceled' THEN party_size ELSE 0 END), 0)
                     FROM %i
                     WHERE event_plan_id = %d",
                    $table,
                    $plan_id
                )));
            }
        }

        $cache[$plan_id] = $guest_qty;
        return $cache[$plan_id];
    }
}

if (!function_exists('bvmgr_vendor_portal_get_count_breakdown')) {
    function bvmgr_vendor_portal_get_count_breakdown(int $plan_id, array $headcount_context = array()): array
    {
        $plan_id = absint($plan_id);
        if ($plan_id <= 0) {
            return array(
                'presales' => 0,
                'door_sales' => 0,
                'comp_guest' => 0,
                'lines' => array(),
                'has_any' => false,
            );
        }

        $stats_payload = isset($headcount_context['stats_payload']) && is_array($headcount_context['stats_payload'])
            ? (array) $headcount_context['stats_payload']
            : vms_vendor_portal_get_ticket_sales_snapshot($plan_id);
        $merged_snapshot = isset($headcount_context['merged_snapshot']) && is_array($headcount_context['merged_snapshot'])
            ? (array) $headcount_context['merged_snapshot']
            : vms_vendor_portal_get_data_tools_sales_snapshot($plan_id);

        $presales = 0;
        if (!empty($merged_snapshot) && array_key_exists('online_qty', $merged_snapshot) && is_numeric($merged_snapshot['online_qty'])) {
            $presales = max(0, (int) $merged_snapshot['online_qty']);
        } elseif (array_key_exists('qty_sold', $stats_payload) && is_numeric($stats_payload['qty_sold'])) {
            $presales = max(0, (int) $stats_payload['qty_sold']);
        } elseif (array_key_exists('qty', $stats_payload) && is_numeric($stats_payload['qty'])) {
            $presales = max(0, (int) $stats_payload['qty']);
        }

        $door_sales = (!empty($merged_snapshot) && array_key_exists('door_paid_qty', $merged_snapshot) && is_numeric($merged_snapshot['door_paid_qty']))
            ? max(0, (int) $merged_snapshot['door_paid_qty'])
            : ((!empty($merged_snapshot) && array_key_exists('door_qty', $merged_snapshot) && is_numeric($merged_snapshot['door_qty']))
                ? max(0, (int) $merged_snapshot['door_qty'])
                : 0);
        $free_online = (!empty($merged_snapshot) && array_key_exists('excluded_free_online_qty', $merged_snapshot) && is_numeric($merged_snapshot['excluded_free_online_qty']))
            ? max(0, (int) $merged_snapshot['excluded_free_online_qty'])
            : 0;
        $free_door = (!empty($merged_snapshot) && array_key_exists('door_free_qty', $merged_snapshot) && is_numeric($merged_snapshot['door_free_qty']))
            ? max(0, (int) $merged_snapshot['door_free_qty'])
            : 0;
        $guest_admissions = vms_vendor_portal_get_guest_admissions_count($plan_id);
        $comp_guest = max(0, $free_online + $free_door + $guest_admissions);

        $lines = array(
            array(
                'key' => 'presales',
                'label' => __('Presales', 'backstage-venue-manager'),
                'qty' => $presales,
            ),
            array(
                'key' => 'door_sales',
                'label' => __('Door sales', 'backstage-venue-manager'),
                'qty' => $door_sales,
            ),
            array(
                'key' => 'comp_guest',
                'label' => __('Comped / guest list', 'backstage-venue-manager'),
                'qty' => $comp_guest,
            ),
        );

        return array(
            'presales' => $presales,
            'door_sales' => $door_sales,
            'comp_guest' => $comp_guest,
            'lines' => $lines,
            'has_any' => ($presales > 0) || ($door_sales > 0) || ($comp_guest > 0) || !empty($merged_snapshot) || !empty($stats_payload),
        );
    }
}

if (!function_exists('vms_vendor_portal_render_count_breakdown_markup')) {
    function vms_vendor_portal_render_count_breakdown_markup(array $count_breakdown): string
    {
        $lines = isset($count_breakdown['lines']) && is_array($count_breakdown['lines'])
            ? (array) $count_breakdown['lines']
            : array();
        if (empty($lines)) {
            return '';
        }

        ob_start();
        echo '<div class="vms-vp-progress-breakdown">';
        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }
            $label = trim((string) ($line['label'] ?? ''));
            $qty = max(0, (int) ($line['qty'] ?? 0));
            if ($label === '') {
                continue;
            }
            echo '<div class="vms-vp-progress-breakdown__row">';
            echo '<span class="vms-vp-progress-breakdown__label">' . esc_html($label) . '</span>';
            echo '<strong>' . esc_html(number_format_i18n($qty)) . '</strong>';
            echo '</div>';
        }
        echo '</div>';
        return (string) ob_get_clean();
    }
}

if (!function_exists('bvmgr_vendor_portal_get_progress_headcount_context')) {
    function bvmgr_vendor_portal_get_progress_headcount_context(int $plan_id): array
    {
        $plan_id = absint($plan_id);
        $fallback = function_exists('vms_staffing_get_event_plan_headcount_context')
            ? (array) vms_staffing_get_event_plan_headcount_context($plan_id)
            : array(
                'wired' => false,
                'headcount' => 0,
                'source' => 'none',
                'label' => __('Sales not wired yet', 'backstage-venue-manager'),
            );

        if ($plan_id <= 0) {
            return $fallback;
        }

        $stats_payload = vms_vendor_portal_get_ticket_sales_snapshot($plan_id);
        $merged_snapshot = vms_vendor_portal_get_data_tools_sales_snapshot($plan_id);
        if (!empty($merged_snapshot) && !empty($merged_snapshot['has_countable_data'])) {
            return array(
                'wired' => true,
                'headcount' => max(0, (int) ($merged_snapshot['headcount'] ?? 0)),
                'source' => (string) ($merged_snapshot['source'] ?? 'data_tools_merged_ticket_sales'),
                'label' => (string) ($merged_snapshot['label'] ?? __('Paid ticket sales', 'backstage-venue-manager')),
                'stats_payload' => $stats_payload,
                'merged_snapshot' => $merged_snapshot,
                'updated_label' => (string) ($merged_snapshot['updated_label'] ?? ''),
            );
        }

        $ticket_qty = 0;
        if (array_key_exists('qty_sold', $stats_payload) && is_numeric($stats_payload['qty_sold'])) {
            $ticket_qty = max(0, (int) $stats_payload['qty_sold']);
        } elseif (array_key_exists('qty', $stats_payload) && is_numeric($stats_payload['qty'])) {
            $ticket_qty = max(0, (int) $stats_payload['qty']);
        }

        $source_mode = sanitize_key((string) ($stats_payload['source_mode'] ?? 'cached'));
        $ticket_label = ($source_mode === 'live') ? __('Live paid ticket sales', 'backstage-venue-manager') : __('Paid ticket sales', 'backstage-venue-manager');
        $has_ticket_linkage = !empty($stats_payload['ticket_product_ids']) || !empty($stats_payload['all_ticket_product_ids']);

        if ($ticket_qty > 0 || $has_ticket_linkage) {
            return array(
                'wired' => true,
                'headcount' => max(0, $ticket_qty),
                'source' => (($source_mode === 'live') ? 'live_paid_ticket_sales' : 'paid_ticket_sales'),
                'label' => $ticket_label,
                'stats_payload' => $stats_payload,
            );
        }

        $event_date = (string) get_post_meta($plan_id, '_vms_event_date', true);
        $today = wp_date('Y-m-d', current_time('timestamp'), wp_timezone());
        $is_past_event = ($event_date !== '' && $event_date < $today);
        if ($is_past_event && ($fallback['source'] ?? '') === 'true_headcount' && max(0, (int) ($fallback['headcount'] ?? 0)) > 0) {
            $fallback['stats_payload'] = $stats_payload;
            return $fallback;
        }

        $fallback['stats_payload'] = $stats_payload;
        return $fallback;
    }
}

if (!function_exists('vms_vendor_portal_secondary_sales_visibility_enabled')) {
    function vms_vendor_portal_secondary_sales_visibility_enabled(): bool
    {
        $settings = (array) get_option('vms_settings', array());
        return !empty($settings['vendor_portal_show_secondary_ticket_sales']);
    }
}

if (!function_exists('vms_vendor_portal_build_secondary_sales_snapshot_card')) {
    function vms_vendor_portal_build_secondary_sales_snapshot_card(int $plan_id): array
    {
        $plan_id = absint($plan_id);
        if ($plan_id <= 0) {
            return array();
        }

        $status = function_exists('bvmgr_event_plan_get_status')
            ? (string) bvmgr_event_plan_get_status($plan_id, 'dashboard')
            : 'draft';
        if ($status !== 'published') {
            return array();
        }

        $headcount_context = bvmgr_vendor_portal_get_progress_headcount_context($plan_id);
        $count_breakdown = bvmgr_vendor_portal_get_count_breakdown($plan_id, $headcount_context);
        $is_wired = !empty($headcount_context['wired']);
        if (!$is_wired && empty($count_breakdown['has_any'])) {
            return array();
        }

        $attendance_count = max(0, (int) ($headcount_context['headcount'] ?? 0));
        $source_label = isset($headcount_context['label']) ? (string) ($headcount_context['label'] ?? '') : __('Ticket sales', 'backstage-venue-manager');

        $event_date = (string) get_post_meta($plan_id, '_vms_event_date', true);
        $event_date_label = $event_date !== ''
            ? bvmgr_format_local_ymd($event_date, 'D, M j, Y')
            : '';

        $stats_payload = isset($headcount_context['stats_payload']) && is_array($headcount_context['stats_payload'])
            ? (array) $headcount_context['stats_payload']
            : vms_vendor_portal_get_ticket_sales_snapshot($plan_id);
        $updated_label = isset($headcount_context['updated_label'])
            ? trim((string) ($headcount_context['updated_label'] ?? ''))
            : vms_vendor_portal_format_stats_updated_label($stats_payload);

        return array(
            'card_kind' => 'sales_snapshot',
            'plan_id' => $plan_id,
            'title' => get_the_title($plan_id),
            'event_date' => $event_date,
            'event_date_label' => $event_date_label,
            'attendance_count' => $attendance_count,
            'source_label' => $source_label !== '' ? $source_label : __('Ticket sales', 'backstage-venue-manager'),
            'count_breakdown' => $count_breakdown,
            'updated_label' => $updated_label,
            'is_history' => false,
            'stats_payload' => $stats_payload,
        );
    }
}

if (!function_exists('vms_vendor_portal_build_bonus_progress_card')) {
    function vms_vendor_portal_build_bonus_progress_card(int $plan_id, bool $is_history = false): array
    {
        $plan_id = absint($plan_id);
        if ($plan_id <= 0) {
            return array();
        }

        $status = function_exists('bvmgr_event_plan_get_status')
            ? (string) bvmgr_event_plan_get_status($plan_id, 'dashboard')
            : 'draft';
        if ($status !== 'published') {
            return array();
        }

        $terms = function_exists('bvmgr_get_event_plan_comp_terms')
            ? (array) bvmgr_get_event_plan_comp_terms($plan_id)
            : array();
        $structure = sanitize_key((string) ($terms['structure'] ?? ''));

        $headcount_context = bvmgr_vendor_portal_get_progress_headcount_context($plan_id);
        $attendance_count = max(0, (int) ($headcount_context['headcount'] ?? 0));
        $source_label = isset($headcount_context['label']) ? (string) ($headcount_context['label']) : __('Ticket sales', 'backstage-venue-manager');

        if ($structure === 'attendance_bonus') {
            $snapshot = function_exists('bvmgr_get_attendance_bonus_progress_snapshot')
                ? (array) bvmgr_get_attendance_bonus_progress_snapshot($terms, $attendance_count)
                : array();
            if (empty($snapshot['eligible'])) {
                return array();
            }
        } else {
            if (!$is_history) {
                return array();
            }

            $base_pay = 0.0;
            if (function_exists('bvmgr_normalize_comp_nonnegative_float')) {
                $normalized_base_pay = bvmgr_normalize_comp_nonnegative_float($terms['flat_fee_amount'] ?? null);
                if ($normalized_base_pay !== null) {
                    $base_pay = (float) $normalized_base_pay;
                }
            } elseif (isset($terms['flat_fee_amount']) && is_numeric($terms['flat_fee_amount'])) {
                $base_pay = max(0.0, (float) $terms['flat_fee_amount']);
            }

            $snapshot = array(
                'eligible' => true,
                'structure' => $structure,
                'mode' => '',
                'attendance_count' => $attendance_count,
                'base_pay' => $base_pay,
                'current_bonus' => 0.0,
                'projected_total' => $base_pay,
                'bonus_start_count' => 0,
                'max_bonus' => null,
                'max_reached' => false,
                'meter_enabled' => false,
                'meter_percent' => 0.0,
                'meter_start_count' => 0,
                'meter_target_count' => 0,
                'tickets_to_next' => 0,
                'current_threshold_count' => 0,
                'next_threshold_count' => null,
                'current_bonus_target' => 0.0,
                'next_bonus_target' => null,
                'step_size' => null,
                'step_bonus' => null,
                'per_ticket_rate' => null,
                'steps_reached' => 0,
                'message' => __('No attendance bonus was configured for this event.', 'backstage-venue-manager'),
            );
        }

        $event_date = (string) get_post_meta($plan_id, '_vms_event_date', true);
        $event_date_label = $event_date !== ''
            ? bvmgr_format_local_ymd($event_date, 'D, M j, Y')
            : '';

        $stats_payload = isset($headcount_context['stats_payload']) && is_array($headcount_context['stats_payload'])
            ? $headcount_context['stats_payload']
            : vms_vendor_portal_get_ticket_sales_snapshot($plan_id);
        $updated_label = isset($headcount_context['updated_label'])
            ? trim((string) ($headcount_context['updated_label'] ?? ''))
            : vms_vendor_portal_format_stats_updated_label($stats_payload);

        $count_breakdown = bvmgr_vendor_portal_get_count_breakdown($plan_id, $headcount_context);

        return array(
            'plan_id' => $plan_id,
            'title' => get_the_title($plan_id),
            'event_date' => $event_date,
            'event_date_label' => $event_date_label,
            'attendance_count' => $attendance_count,
            'source_label' => $source_label,
            'count_breakdown' => $count_breakdown,
            'snapshot' => $snapshot,
            'updated_label' => $updated_label,
            'is_history' => $is_history,
            'stats_payload' => $stats_payload,
        );
    }
}

if (!function_exists('vms_vendor_portal_get_bonus_progress_cards')) {
    /**
     * Build vendor-facing bonus progress cards for the portal.
     *
     * Rules:
     * - Published plans only.
     * - Primary paid vendor only (band/headliner slot) so portal math does not imply
     *   compensation terms on secondary vendors that may follow different arrangements.
     * - Attendance-bonus structures only.
     *
     * @return array<int,array<string,mixed>>
     */
    function vms_vendor_portal_get_bonus_progress_cards(int $vendor_id, int $limit = 6): array
    {
        $vendor_id = absint($vendor_id);
        $limit = max(1, $limit);
        if ($vendor_id <= 0) {
            return array();
        }

        $today = wp_date('Y-m-d', current_time('timestamp'), wp_timezone());
        $k_band_vendor_id = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'band_vendor_id') : '_vms_band_vendor_id';
        if ($k_band_vendor_id === '') {
            $k_band_vendor_id = '_vms_band_vendor_id';
        }

        $query = new WP_Query(array(
            'post_type' => 'vms_event_plan',
            'post_status' => array('publish', 'draft', 'pending', 'private'),
            'posts_per_page' => $limit * 3,
            'orderby' => 'meta_value',
            'meta_key' => '_vms_event_date', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Bonus progress intentionally orders the bounded upcoming Event Plan query by its canonical event-date metadata.
            'order' => 'ASC',
            'no_found_rows' => true,
            'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Bonus progress requires canonical event-date and primary-vendor assignment metadata; no equivalent indexed domain fields exist.
                'relation' => 'AND',
                array(
                    'key' => '_vms_event_date',
                    'value' => $today,
                    'compare' => '>=',
                    'type' => 'DATE',
                ),
                array(
                    'key' => $k_band_vendor_id,
                    'value' => $vendor_id,
                    'compare' => '=',
                    'type' => 'NUMERIC',
                ),
            ),
        ));

        $cards = array();
        foreach ((array) ($query->posts ?? array()) as $plan_post) {
            $plan_id = (int) ($plan_post->ID ?? 0);
            if ($plan_id <= 0) {
                continue;
            }

            $card = vms_vendor_portal_build_bonus_progress_card($plan_id, false);
            if (empty($card)) {
                continue;
            }

            $cards[] = $card;
            if (count($cards) >= $limit) {
                break;
            }
        }
        wp_reset_postdata();

        return $cards;
    }
}

if (!function_exists('vms_vendor_portal_get_recent_bonus_history_cards')) {
    function vms_vendor_portal_get_recent_bonus_history_cards(int $vendor_id, int $limit = 4): array
    {
        $vendor_id = absint($vendor_id);
        $limit = max(1, $limit);
        if ($vendor_id <= 0) {
            return array();
        }

        $today = wp_date('Y-m-d', current_time('timestamp'), wp_timezone());
        $k_band_vendor_id = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'band_vendor_id') : '_vms_band_vendor_id';
        if ($k_band_vendor_id === '') {
            $k_band_vendor_id = '_vms_band_vendor_id';
        }

        $query = new WP_Query(array(
            'post_type' => 'vms_event_plan',
            'post_status' => array('publish', 'draft', 'pending', 'private'),
            'posts_per_page' => $limit * 4,
            'orderby' => 'meta_value',
            'meta_key' => '_vms_event_date', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Bonus history intentionally orders the bounded past Event Plan query by its canonical event-date metadata.
            'order' => 'DESC',
            'no_found_rows' => true,
            'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Bonus history requires canonical event-date and primary-vendor assignment metadata; no equivalent indexed domain fields exist.
                'relation' => 'AND',
                array(
                    'key' => '_vms_event_date',
                    'value' => $today,
                    'compare' => '<',
                    'type' => 'DATE',
                ),
                array(
                    'key' => $k_band_vendor_id,
                    'value' => $vendor_id,
                    'compare' => '=',
                    'type' => 'NUMERIC',
                ),
            ),
        ));

        $cards = array();
        foreach ((array) ($query->posts ?? array()) as $plan_post) {
            $plan_id = (int) ($plan_post->ID ?? 0);
            if ($plan_id <= 0) {
                continue;
            }

            $card = vms_vendor_portal_build_bonus_progress_card($plan_id, true);
            if (empty($card)) {
                continue;
            }

            $cards[] = $card;
            if (count($cards) >= $limit) {
                break;
            }
        }
        wp_reset_postdata();

        return $cards;
    }
}

if (!function_exists('vms_vendor_portal_render_progress_cards_section')) {
    function vms_vendor_portal_render_progress_cards_section(array $cards, string $section_title, bool $history_mode = false, bool $tour_enabled = true): void
    {
        if (empty($cards)) {
            return;
        }

        echo '<div class="vms-portal-card vms-vp-progress-section"';
        if ($tour_enabled) {
            echo ' data-vms-tour="vendor-progress.help"';
        }
        echo '>';
        echo '<div class="vms-vp-progress-section__header">';
        echo '<div>';
        echo '<h3>' . esc_html($section_title) . '</h3>';
        echo '</div>';
        echo '</div>';

        echo '<div class="vms-vp-progress-list">';
        foreach ($cards as $index => $card) {
            $card_kind = sanitize_key((string) ($card['card_kind'] ?? 'bonus_progress'));
            $is_sales_snapshot = ($card_kind === 'sales_snapshot');
            $snapshot = (array) ($card['snapshot'] ?? array());
            $attendance_count = max(0, (int) ($card['attendance_count'] ?? 0));
            $projected_total = (float) ($snapshot['projected_total'] ?? 0.0);
            $current_bonus = (float) ($snapshot['current_bonus'] ?? 0.0);
            $base_pay = (float) ($snapshot['base_pay'] ?? 0.0);
            $next_bonus = $snapshot['next_bonus_target'] ?? null;
            $next_threshold = $snapshot['next_threshold_count'] ?? null;
            $tickets_to_next = max(0, (int) ($snapshot['tickets_to_next'] ?? 0));
            $mode = sanitize_key((string) ($snapshot['mode'] ?? ''));
            $meter_enabled = !$is_sales_snapshot && !$history_mode && !empty($snapshot['meter_enabled']);
            $max_reached = !empty($snapshot['max_reached']);
            $progress_percent = max(0.0, min(100.0, ((float) ($snapshot['meter_percent'] ?? 0.0)) * 100));
            $source_label = (string) ($card['source_label'] ?? __('Ticket sales', 'backstage-venue-manager'));
            $count_breakdown = isset($card['count_breakdown']) && is_array($card['count_breakdown'])
                ? (array) $card['count_breakdown']
                : array();
            $count_breakdown_markup = vms_vendor_portal_render_count_breakdown_markup($count_breakdown);
            $updated_label = trim((string) ($card['updated_label'] ?? ''));
            $event_date_label = trim((string) ($card['event_date_label'] ?? ''));

            echo '<article class="vms-vp-progress-card"';
            if ($tour_enabled && !$is_sales_snapshot) {
                echo ' data-vms-tour="vendor-progress.cards"';
            }
            echo '>';
            echo '<div class="vms-vp-progress-card__top">';
            echo '<div class="vms-vp-progress-card__titles">';
            echo '<h4 class="vms-vp-progress-card__title">' . esc_html((string) ($card['title'] ?? '')) . '</h4>';
            if ($event_date_label !== '') {
                echo '<div class="vms-vp-progress-card__date">' . esc_html($event_date_label) . '</div>';
            }
            echo '</div>';
            if (!$is_sales_snapshot) {
                echo '<div class="vms-vp-progress-card__amount">';
                echo '<span class="vms-vp-progress-card__amount-label">' . esc_html($history_mode ? __('Final payout', 'backstage-venue-manager') : __('Projected payout', 'backstage-venue-manager')) . '</span>';
                echo '<strong>' . wp_kses_post(vms_vendor_portal_format_money($projected_total)) . '</strong>';
                echo '</div>';
            }
            echo '</div>';

            echo '<div class="vms-vp-progress-card__countline">';
            /* translators: %d: attendance count for this vendor event card. */
            echo '<strong>' . esc_html(sprintf($history_mode ? __('Final count: %d', 'backstage-venue-manager') : __('Current count: %d', 'backstage-venue-manager'), $attendance_count)) . '</strong>';
            echo '</div>';

            if ($meter_enabled) {
                echo '<div class="vms-vp-progress-meter" data-vms-tour="vendor-progress.bar" aria-hidden="true">';
                echo '<span class="vms-vp-progress-meter__fill" style="width:' . esc_attr(number_format($progress_percent, 2, '.', '')) . '%"></span>';
                echo '</div>';
            }

            echo '<div class="vms-vp-progress-stats">';

            if ($is_sales_snapshot) {
                echo '<div class="vms-vp-progress-stat">';
                echo '<span class="vms-vp-progress-stat__label">' . esc_html__('Status', 'backstage-venue-manager') . '</span>';
                echo '<strong>' . esc_html($source_label) . '</strong>';
                echo '</div>';

                if (!empty($count_breakdown['has_any'])) {
                    echo '<div class="vms-vp-progress-stat vms-vp-progress-stat--breakdown">';
                    echo '<span class="vms-vp-progress-stat__label">' . esc_html__('Count source', 'backstage-venue-manager') . '</span>';
                    if ($count_breakdown_markup !== '') {
                        echo wp_kses_post($count_breakdown_markup);
                    } else {
                        echo '<strong>' . esc_html($source_label) . '</strong>';
                    }
                    echo '</div>';
                }
            } else {
                echo '<div class="vms-vp-progress-stat">';
                echo '<span class="vms-vp-progress-stat__label">' . esc_html__('Base pay', 'backstage-venue-manager') . '</span>';
                echo '<strong>' . wp_kses_post(vms_vendor_portal_format_money($base_pay)) . '</strong>';
                echo '</div>';

                echo '<div class="vms-vp-progress-stat">';
                echo '<span class="vms-vp-progress-stat__label">' . esc_html($history_mode ? __('Bonus earned', 'backstage-venue-manager') : __('Current bonus', 'backstage-venue-manager')) . '</span>';
                echo '<strong>' . wp_kses_post(vms_vendor_portal_format_money($current_bonus)) . '</strong>';
                echo '</div>';

                echo '<div class="vms-vp-progress-stat">';
                if ($history_mode) {
                    echo '<span class="vms-vp-progress-stat__label">' . esc_html__('Result', 'backstage-venue-manager') . '</span>';
                    if ($max_reached) {
                        echo '<strong>' . esc_html__('Max bonus reached', 'backstage-venue-manager') . '</strong>';
                    } elseif ($current_bonus > 0) {
                        echo '<strong>' . esc_html__('Bonus unlocked', 'backstage-venue-manager') . '</strong>';
                    } else {
                        echo '<strong>' . esc_html__('No bonus unlocked', 'backstage-venue-manager') . '</strong>';
                    }
                } elseif ($max_reached) {
                    echo '<span class="vms-vp-progress-stat__label">' . esc_html__('Status', 'backstage-venue-manager') . '</span>';
                    echo '<strong>' . esc_html__('Max bonus reached', 'backstage-venue-manager') . '</strong>';
                } elseif ($mode === 'step' && $next_bonus !== null && $next_threshold !== null) {
                    echo '<span class="vms-vp-progress-stat__label">' . esc_html__('Next bonus', 'backstage-venue-manager') . '</span>';
                    /* translators: %d: attendance threshold needed for the next bonus step. */
                    echo '<strong>' . wp_kses_post(vms_vendor_portal_format_money((float) $next_bonus)) . ' <span class="vms-vp-progress-inline-note">' . esc_html(sprintf(__('at %d', 'backstage-venue-manager'), (int) $next_threshold)) . '</span></strong>';
                } elseif ($mode === 'continuous' && $next_threshold !== null && !empty($snapshot['max_bonus'])) {
                    echo '<span class="vms-vp-progress-stat__label">' . esc_html__('Bonus cap', 'backstage-venue-manager') . '</span>';
                    /* translators: %d: attendance threshold where the bonus cap is reached. */
                    echo '<strong>' . wp_kses_post(vms_vendor_portal_format_money((float) ($snapshot['max_bonus'] ?? 0.0))) . ' <span class="vms-vp-progress-inline-note">' . esc_html(sprintf(__('by %d', 'backstage-venue-manager'), (int) $next_threshold)) . '</span></strong>';
                } elseif ($mode === 'continuous' && !empty($snapshot['per_ticket_rate'])) {
                    echo '<span class="vms-vp-progress-stat__label">' . esc_html__('Bonus rate', 'backstage-venue-manager') . '</span>';
                    echo '<strong>' . wp_kses_post(vms_vendor_portal_format_money((float) $snapshot['per_ticket_rate'])) . ' <span class="vms-vp-progress-inline-note">' . esc_html__('per ticket', 'backstage-venue-manager') . '</span></strong>';
                } else {
                    echo '<span class="vms-vp-progress-stat__label">' . esc_html__('Next step', 'backstage-venue-manager') . '</span>';
                    $fallback_message = trim((string) ($snapshot['message'] ?? ''));
                    echo '<strong>' . esc_html($fallback_message !== '' ? $fallback_message : __('Waiting for current attendance wiring.', 'backstage-venue-manager')) . '</strong>';
                }
                echo '</div>';

                echo '<div class="vms-vp-progress-stat">';
                if ($history_mode) {
                    echo '<span class="vms-vp-progress-stat__label">' . esc_html__('Count source', 'backstage-venue-manager') . '</span>';
                    if ($count_breakdown_markup !== '') {
                        echo wp_kses_post($count_breakdown_markup);
                    } else {
                        echo '<strong>' . esc_html($source_label) . '</strong>';
                    }
                } elseif ($max_reached) {
                    echo '<span class="vms-vp-progress-stat__label">' . esc_html__('To go', 'backstage-venue-manager') . '</span>';
                    echo '<strong>' . esc_html__('Goal reached', 'backstage-venue-manager') . '</strong>';
                } elseif ($tickets_to_next > 0) {
                    echo '<span class="vms-vp-progress-stat__label">' . esc_html__('To go', 'backstage-venue-manager') . '</span>';
                    /* translators: %d: remaining ticket count before the next threshold. */
                    echo '<strong>' . esc_html(sprintf(_n('%d more ticket', '%d more tickets', $tickets_to_next, 'backstage-venue-manager'), $tickets_to_next)) . '</strong>';
                } else {
                    echo '<span class="vms-vp-progress-stat__label">' . esc_html__('To go', 'backstage-venue-manager') . '</span>';
                    echo '<strong>' . esc_html__('Live now', 'backstage-venue-manager') . '</strong>';
                }
                echo '</div>';

                if (!$history_mode && !empty($count_breakdown['has_any'])) {
                    echo '<div class="vms-vp-progress-stat vms-vp-progress-stat--breakdown">';
                    echo '<span class="vms-vp-progress-stat__label">' . esc_html__('Count source', 'backstage-venue-manager') . '</span>';
                    if ($count_breakdown_markup !== '') {
                        echo wp_kses_post($count_breakdown_markup);
                    } else {
                        echo '<strong>' . esc_html($source_label) . '</strong>';
                    }
                    echo '</div>';
                }
            }

            echo '</div>';

            if ($updated_label !== '') {
                echo '<div class="vms-vp-progress-card__foot vms-muted">' . esc_html__('Updated', 'backstage-venue-manager') . ': ' . esc_html($updated_label) . '</div>';
            }
            echo '</article>';
        }
        echo '</div>';
        echo '</div>';
    }
}

if (!function_exists('vms_vendor_portal_get_secondary_sales_snapshot_cards')) {
    function vms_vendor_portal_get_secondary_sales_snapshot_cards(int $vendor_id, int $limit = 6): array
    {
        $vendor_id = absint($vendor_id);
        $limit = max(1, $limit);
        if ($vendor_id <= 0 || !vms_vendor_portal_secondary_sales_visibility_enabled()) {
            return array();
        }

        $today = wp_date('Y-m-d', current_time('timestamp'), wp_timezone());
        $k_secondary_vendor_id = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'secondary_vendor_id') : '_vms_secondary_vendor_id';
        if ($k_secondary_vendor_id === '') {
            $k_secondary_vendor_id = '_vms_secondary_vendor_id';
        }

        $query = new WP_Query(array(
            'post_type' => 'vms_event_plan',
            'post_status' => array('publish', 'draft', 'pending', 'private'),
            'posts_per_page' => $limit * 4,
            'orderby' => 'meta_value',
            'meta_key' => '_vms_event_date', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Secondary sales snapshots intentionally order the bounded upcoming Event Plan query by canonical event-date metadata.
            'order' => 'ASC',
            'no_found_rows' => true,
            'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Secondary sales snapshots require canonical event-date and secondary-vendor assignment metadata; no equivalent indexed domain fields exist.
                'relation' => 'AND',
                array(
                    'key' => '_vms_event_date',
                    'value' => $today,
                    'compare' => '>=',
                    'type' => 'DATE',
                ),
                array(
                    'key' => $k_secondary_vendor_id,
                    'value' => $vendor_id,
                    'compare' => '=',
                    'type' => 'NUMERIC',
                ),
            ),
        ));

        $cards = array();
        foreach ((array) ($query->posts ?? array()) as $plan_post) {
            $plan_id = (int) ($plan_post->ID ?? 0);
            if ($plan_id <= 0) {
                continue;
            }

            $card = vms_vendor_portal_build_secondary_sales_snapshot_card($plan_id);
            if (empty($card)) {
                continue;
            }

            $cards[] = $card;
            if (count($cards) >= $limit) {
                break;
            }
        }
        wp_reset_postdata();

        return $cards;
    }
}

if (!function_exists('vms_vendor_portal_render_secondary_sales_snapshot_section')) {
    function vms_vendor_portal_render_secondary_sales_snapshot_section(int $vendor_id, string $context = 'dashboard'): void
    {
        $vendor_id = absint($vendor_id);
        if ($vendor_id <= 0) {
            return;
        }

        $cards = vms_vendor_portal_get_secondary_sales_snapshot_cards($vendor_id, ($context === 'profile' ? 6 : 4));
        if (empty($cards)) {
            return;
        }

        vms_vendor_portal_render_progress_cards_section($cards, __('Ticket Snapshot', 'backstage-venue-manager'), false, false);
    }
}

if (!function_exists('vms_vendor_portal_render_bonus_progress_section')) {
    function vms_vendor_portal_render_bonus_progress_section(int $vendor_id, string $context = 'dashboard'): void
    {
        $vendor_id = absint($vendor_id);
        if ($vendor_id <= 0) {
            return;
        }

        $cards = vms_vendor_portal_get_bonus_progress_cards($vendor_id);
        if (empty($cards)) {
            return;
        }

        vms_vendor_portal_render_progress_cards_section($cards, __('Bonus Progress', 'backstage-venue-manager'), false);
    }
}


if (!function_exists('vms_vendor_portal_get_secondary_sales_history_cards')) {
    function vms_vendor_portal_get_secondary_sales_history_cards(int $vendor_id, int $limit = 6): array
    {
        $vendor_id = absint($vendor_id);
        $limit = max(1, $limit);
        if ($vendor_id <= 0 || !vms_vendor_portal_secondary_sales_visibility_enabled()) {
            return array();
        }

        $today = wp_date('Y-m-d', current_time('timestamp'), wp_timezone());
        $k_secondary_vendor_id = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'secondary_vendor_id') : '_vms_secondary_vendor_id';
        if ($k_secondary_vendor_id === '') {
            $k_secondary_vendor_id = '_vms_secondary_vendor_id';
        }

        $query = new WP_Query(array(
            'post_type' => 'vms_event_plan',
            'post_status' => array('publish', 'draft', 'pending', 'private'),
            'posts_per_page' => $limit * 4,
            'orderby' => 'meta_value',
            'meta_key' => '_vms_event_date', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Secondary sales history intentionally orders the bounded past Event Plan query by canonical event-date metadata.
            'order' => 'DESC',
            'no_found_rows' => true,
            'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Secondary sales history requires canonical event-date and secondary-vendor assignment metadata; no equivalent indexed domain fields exist.
                'relation' => 'AND',
                array(
                    'key' => '_vms_event_date',
                    'value' => $today,
                    'compare' => '<',
                    'type' => 'DATE',
                ),
                array(
                    'key' => $k_secondary_vendor_id,
                    'value' => $vendor_id,
                    'compare' => '=',
                    'type' => 'NUMERIC',
                ),
            ),
        ));

        $cards = array();
        foreach ((array) ($query->posts ?? array()) as $plan_post) {
            $plan_id = (int) ($plan_post->ID ?? 0);
            if ($plan_id <= 0) {
                continue;
            }

            $card = vms_vendor_portal_build_secondary_sales_snapshot_card($plan_id);
            if (empty($card)) {
                continue;
            }

            $cards[] = $card;
            if (count($cards) >= $limit) {
                break;
            }
        }
        wp_reset_postdata();

        return $cards;
    }
}

if (!function_exists('vms_vendor_portal_get_past_assigned_event_rows')) {
    function vms_vendor_portal_get_past_assigned_event_rows(int $vendor_id, int $limit = 12): array
    {
        $vendor_id = absint($vendor_id);
        $limit = max(1, $limit);
        if ($vendor_id <= 0) {
            return array();
        }

        $today = wp_date('Y-m-d', current_time('timestamp'), wp_timezone());
        $k_band_vendor_id = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'band_vendor_id') : '_vms_band_vendor_id';
        $k_secondary_vendor_id = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'secondary_vendor_id') : '_vms_secondary_vendor_id';
        $k_lineup_vendor_id = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'lineup_entry_vendor_id') : '_vms_lineup_entry_vendor_id';
        if ($k_band_vendor_id === '') {
            $k_band_vendor_id = '_vms_band_vendor_id';
        }
        if ($k_secondary_vendor_id === '') {
            $k_secondary_vendor_id = '_vms_secondary_vendor_id';
        }
        if ($k_lineup_vendor_id === '') {
            $k_lineup_vendor_id = '_vms_lineup_entry_vendor_id';
        }

        $query = new WP_Query(array(
            'post_type' => 'vms_event_plan',
            'post_status' => array('publish', 'draft', 'pending', 'private'),
            'posts_per_page' => $limit * 5,
            'orderby' => 'meta_value',
            'meta_key' => '_vms_event_date', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Assigned-event history intentionally orders the bounded past Event Plan query by canonical event-date metadata.
            'order' => 'DESC',
            'no_found_rows' => true,
            'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Assigned-event history requires canonical date plus primary, secondary, or lineup vendor metadata; no equivalent indexed relationship exists.
                'relation' => 'AND',
                array(
                    'key' => '_vms_event_date',
                    'value' => $today,
                    'compare' => '<',
                    'type' => 'DATE',
                ),
                array(
                    'relation' => 'OR',
                    array(
                        'key' => $k_band_vendor_id,
                        'value' => $vendor_id,
                        'compare' => '=',
                        'type' => 'NUMERIC',
                    ),
                    array(
                        'key' => $k_secondary_vendor_id,
                        'value' => $vendor_id,
                        'compare' => '=',
                        'type' => 'NUMERIC',
                    ),
                    array(
                        'key' => $k_lineup_vendor_id,
                        'value' => $vendor_id,
                        'compare' => '=',
                        'type' => 'NUMERIC',
                    ),
                ),
            ),
        ));

        $rows = array();
        foreach ((array) ($query->posts ?? array()) as $plan_post) {
            $plan_id = (int) ($plan_post->ID ?? 0);
            if ($plan_id <= 0) {
                continue;
            }

            $status = function_exists('bvmgr_event_plan_get_status')
                ? (string) bvmgr_event_plan_get_status($plan_id, 'dashboard')
                : 'draft';
            if ($status !== 'published') {
                continue;
            }

            $band_vendor_id = (int) get_post_meta($plan_id, $k_band_vendor_id, true);
            $secondary_vendor_id = (int) get_post_meta($plan_id, $k_secondary_vendor_id, true);
            $lineup_vendor_ids = array_values(array_unique(array_filter(array_map('absint', (array) get_post_meta($plan_id, $k_lineup_vendor_id, false)))));

            $role_key = 'assigned';
            $role_label = __('Assigned', 'backstage-venue-manager');
            if ($band_vendor_id === $vendor_id) {
                $role_key = 'primary';
                $role_label = __('Primary', 'backstage-venue-manager');
            } elseif ($secondary_vendor_id === $vendor_id) {
                $role_key = 'supporting';
                $role_label = __('Supporting', 'backstage-venue-manager');
            } elseif (in_array($vendor_id, $lineup_vendor_ids, true)) {
                $role_key = 'lineup';
                $role_label = __('Lineup', 'backstage-venue-manager');
            }

            $event_date = (string) get_post_meta($plan_id, '_vms_event_date', true);
            $event_date_label = $event_date !== ''
                ? bvmgr_format_local_ymd($event_date, 'D, M j, Y')
                : '';

            $show_counts = ($role_key === 'primary') || vms_vendor_portal_secondary_sales_visibility_enabled();
            $attendance_count = null;
            $count_breakdown = array();
            if ($show_counts) {
                $headcount_context = bvmgr_vendor_portal_get_progress_headcount_context($plan_id);
                $attendance_count = max(0, (int) ($headcount_context['headcount'] ?? 0));
                $count_breakdown = bvmgr_vendor_portal_get_count_breakdown($plan_id, $headcount_context);
            }

            $rows[] = array(
                'plan_id' => $plan_id,
                'title' => get_the_title($plan_id),
                'event_date_label' => $event_date_label,
                'role_key' => $role_key,
                'role_label' => $role_label,
                'attendance_count' => $attendance_count,
                'count_breakdown' => $count_breakdown,
            );

            if (count($rows) >= $limit) {
                break;
            }
        }
        wp_reset_postdata();

        return $rows;
    }
}

if (!function_exists('vms_vendor_portal_vendor_has_event_history')) {
    function vms_vendor_portal_vendor_has_event_history(int $vendor_id): bool
    {
        return !empty(vms_vendor_portal_get_past_assigned_event_rows($vendor_id, 1));
    }
}

if (!function_exists('vms_vendor_portal_render_event_history_tab')) {
    function vms_vendor_portal_render_event_history_tab(int $vendor_id): void
    {
        $vendor_id = absint($vendor_id);
        if ($vendor_id <= 0) {
            return;
        }

        $primary_cards = function_exists('vms_vendor_portal_get_recent_bonus_history_cards')
            ? vms_vendor_portal_get_recent_bonus_history_cards($vendor_id, 12)
            : array();
        $secondary_cards = function_exists('vms_vendor_portal_get_secondary_sales_history_cards')
            ? vms_vendor_portal_get_secondary_sales_history_cards($vendor_id, 12)
            : array();
        $rows = vms_vendor_portal_get_past_assigned_event_rows($vendor_id, 20);

        echo '<h3>' . esc_html__('Event History', 'backstage-venue-manager') . '</h3>';

        $rendered_any = false;

        if (!empty($primary_cards)) {
            vms_vendor_portal_render_progress_cards_section($primary_cards, __('Past Show Performance', 'backstage-venue-manager'), true, false);
            $rendered_any = true;
        }

        if (!empty($secondary_cards)) {
            vms_vendor_portal_render_progress_cards_section($secondary_cards, __('Past Ticket Snapshot', 'backstage-venue-manager'), true, false);
            $rendered_any = true;
        }

        if (!empty($rows)) {
            $rendered_any = true;
            echo '<div class="vms-portal-card">';
            echo '<h3>' . esc_html__('Past Shows', 'backstage-venue-manager') . '</h3>';
            echo '<ul class="vms-dash-list">';
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $title = trim((string) ($row['title'] ?? ''));
                $event_date_label = trim((string) ($row['event_date_label'] ?? ''));
                $role_label = trim((string) ($row['role_label'] ?? ''));
                $attendance_count = isset($row['attendance_count']) && is_numeric($row['attendance_count'])
                    ? max(0, (int) $row['attendance_count'])
                    : null;
                $count_breakdown = isset($row['count_breakdown']) && is_array($row['count_breakdown'])
                    ? (array) $row['count_breakdown']
                    : array();

                $meta_bits = array();
                if ($role_label !== '') {
                    $meta_bits[] = $role_label;
                }
                if ($attendance_count !== null) {
                    /* translators: %d: final attendance count for the completed event. */
                    $meta_bits[] = sprintf(__('Final count: %d', 'backstage-venue-manager'), $attendance_count);
                }

                echo '<li>';
                if ($event_date_label !== '') {
                    echo '<strong>' . esc_html($event_date_label) . '</strong> ';
                }
                echo '<span>' . esc_html($title) . '</span>';
                if (!empty($meta_bits)) {
                    echo ' <span class="vms-muted">— ' . esc_html(implode(' · ', $meta_bits)) . '</span>';
                }
                if (!empty($count_breakdown['has_any'])) {
                    $breakdown_markup = vms_vendor_portal_render_count_breakdown_markup($count_breakdown);
                    if ($breakdown_markup !== '') {
                        echo '<div class="vms-mt-8">' . wp_kses_post($breakdown_markup) . '</div>';
                    }
                }
                echo '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }

        if (!$rendered_any) {
            echo '<div class="vms-portal-card">';
            echo '<p class="vms-muted vms-m0">' . esc_html__('No completed event history is available yet.', 'backstage-venue-manager') . '</p>';
            echo '</div>';
        }
    }
}

if (!function_exists('vms_vendor_portal_render_recent_performance_section')) {
    function vms_vendor_portal_render_recent_performance_section(int $vendor_id, string $context = 'dashboard'): void
    {
        $vendor_id = absint($vendor_id);
        if ($vendor_id <= 0) {
            return;
        }

        $cards = vms_vendor_portal_get_recent_bonus_history_cards($vendor_id, ($context === 'profile' ? 3 : 4));
        if (empty($cards)) {
            return;
        }

        vms_vendor_portal_render_progress_cards_section($cards, __('Recent Performance', 'backstage-venue-manager'), true);
    }
}

if (!function_exists('vms_vendor_portal_frontend_tour_screen_key')) {
    function vms_vendor_portal_frontend_tour_screen_key(string $screen_key): string
    {
        if (is_admin()) {
            return $screen_key;
        }

        if (!is_user_logged_in() || !is_page('vendor-portal')) {
            return $screen_key;
        }

        $tab = vms_vendor_portal_get_requested_tab('dashboard');
        if ($tab === 'dashboard') {
            return 'frontend:vms-vendor-portal-dashboard';
        }

        return 'frontend:vms-vendor-portal';
    }
}
add_filter('vms_tours_frontend_screen_key', 'vms_vendor_portal_frontend_tour_screen_key', 40);

if (!function_exists('vms_vendor_portal_register_tours')) {
    /**
     * @param array<int,array<string,mixed>> $tours
     * @return array<int,array<string,mixed>>
     */
    function vms_vendor_portal_register_tours(array $tours): array
    {
        $tours[] = array(
            'id' => 'vms.vendor.portal.progress',
            'title' => __('Vendor Portal Bonus Progress', 'backstage-venue-manager'),
            'screen' => 'frontend:vms-vendor-portal-dashboard',
            'version' => '1.0.0',
            'level' => 'beginner',
            'description' => __('Understand how close a paid vendor is to attendance-bonus targets.', 'backstage-venue-manager'),
            'audience' => array(
                'capabilities_any' => array('read'),
                'capabilities_all' => array(),
                'roles_any' => array(),
                'roles_all' => array(),
            ),
            'auto_run' => true,
            'auto_run_delay_ms' => 700,
            'priority' => 8,
            'steps' => array(
                array(
                    'id' => 'vendor_progress_help',
                    'selector' => '[data-vms-tour="vendor-progress.help"]',
                    'title' => __('Projected, Not Final', 'backstage-venue-manager'),
                    'body' => wp_kses_post(__('This section shows your live progress toward bonus goals for each upcoming event.', 'backstage-venue-manager')),
                    'placement' => 'top',
                ),
                array(
                    'id' => 'vendor_progress_cards',
                    'selector' => '[data-vms-tour="vendor-progress.cards"]',
                    'title' => __('Read Each Event Card', 'backstage-venue-manager'),
                    'body' => wp_kses_post(__('Each card shows your current counted attendance, your current unlocked bonus, the next target, and the projected payout for that event.', 'backstage-venue-manager')),
                    'placement' => 'top',
                ),
                array(
                    'id' => 'vendor_progress_bar',
                    'selector' => '[data-vms-tour="vendor-progress.bar"]',
                    'title' => __('See How Close The Next Goal Is', 'backstage-venue-manager'),
                    'body' => wp_kses_post(__('The bar fills toward the next meaningful target so you can tell at a glance whether the next bonus is close, reached, or already capped.', 'backstage-venue-manager')),
                    'placement' => 'top',
                ),
            ),
        );

        return $tours;
    }
}
add_filter('vms_tours_register', 'vms_vendor_portal_register_tours');

if (!function_exists('vms_vendor_normalize_manual_availability')) {
    function vms_vendor_normalize_manual_availability(int $vendor_id): array
    {
        $manual = get_post_meta($vendor_id, '_vms_availability_manual', true);
        if (!is_array($manual)) {
            $manual = array();
        }

        $normalized = array();
        foreach ($manual as $date => $state) {
            $date = sanitize_text_field((string) $date);
            $state = sanitize_key((string) $state);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                continue;
            }
            if (!in_array($state, array('available', 'unavailable'), true)) {
                continue;
            }
            $normalized[$date] = $state;
        }

        return $normalized;
    }
}

if (!function_exists('vms_vendor_normalize_pattern_days')) {
    function vms_vendor_normalize_pattern_days(int $vendor_id): array
    {
        $pattern_days = get_post_meta($vendor_id, '_vms_pattern_days', true);
        if (!is_array($pattern_days)) {
            $pattern_days = array();
        }

        $pattern_days = array_values(array_unique(array_filter(array_map('intval', $pattern_days), static function ($d) {
            return $d >= 0 && $d <= 6;
        })));
        sort($pattern_days);

        return $pattern_days;
    }
}

if (!function_exists('vms_vendor_normalize_ics_unavailable')) {
    function vms_vendor_normalize_ics_unavailable(int $vendor_id): array
    {
        $ics_unavailable = get_post_meta($vendor_id, '_vms_ics_unavailable', true);
        if (!is_array($ics_unavailable)) {
            $ics_unavailable = array();
        }

        $is_list = (array_keys($ics_unavailable) === range(0, max(0, count($ics_unavailable) - 1)));
        if (!$is_list && !empty($ics_unavailable)) {
            $ics_unavailable = array_keys($ics_unavailable);
        } elseif (empty($ics_unavailable)) {
            $ics_layer = get_post_meta($vendor_id, '_vms_availability_ics', true);
            if (is_array($ics_layer) && !empty($ics_layer)) {
                $ics_unavailable = array_keys($ics_layer);
            }
        }

        return array_values(array_unique(array_filter(array_map('sanitize_text_field', $ics_unavailable), static function ($date) {
            return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date);
        })));
    }
}

if (!function_exists('vms_vendor_has_availability_setup')) {
    function vms_vendor_has_availability_setup(int $vendor_id): bool
    {
        $manual = vms_vendor_normalize_manual_availability($vendor_id);
        $pattern_enabled = (int) get_post_meta($vendor_id, '_vms_pattern_enabled', true);
        $pattern_days = vms_vendor_normalize_pattern_days($vendor_id);
        $ics_url = trim((string) get_post_meta($vendor_id, '_vms_ics_url', true));
        $ics_unavailable = vms_vendor_normalize_ics_unavailable($vendor_id);

        return !empty($manual)
            || ($pattern_enabled && !empty($pattern_days))
            || $ics_url !== ''
            || !empty($ics_unavailable);
    }
}

if (!function_exists('vms_vendor_availability_source_label')) {
    function vms_vendor_availability_source_label(string $reason): string
    {
        $reason = sanitize_key($reason);
        $map = array(
            'assigned_here' => __('Current assignment', 'backstage-venue-manager'),
            'manual' => __('Manual', 'backstage-venue-manager'),
            'pattern' => __('Pattern', 'backstage-venue-manager'),
            'ics' => __('ICS', 'backstage-venue-manager'),
            'assigned_elsewhere' => __('Conflict', 'backstage-venue-manager'),
            'no_response' => __('No reply', 'backstage-venue-manager'),
            'invalid' => __('Unknown', 'backstage-venue-manager'),
        );

        return $map[$reason] ?? __('Unknown', 'backstage-venue-manager');
    }
}

if (!function_exists('vms_vendor_effective_availability_for_date')) {
    /**
     * Shared effective availability resolver used by vendor portal + admin modules.
     * Manual overrides beat pattern/ICS. Existing booked/tentative assignments beat everything.
     *
     * @param int    $vendor_id
     * @param string $date YYYY-MM-DD
     * @param array  $args Optional context: busy_source, assigned_here, assigned_role.
     * @return array{state:string,label:string,reason:string,detail:string,source:string,conflict:bool,assignable:bool,visual_state:string}
     */
    function vms_vendor_effective_availability_for_date(int $vendor_id, string $date, array $args = array()): array
    {
        $vendor_id = absint($vendor_id);
        $busy_source = sanitize_key((string) ($args['busy_source'] ?? ''));
        $assigned_here = !empty($args['assigned_here']);
        $assigned_role = sanitize_text_field((string) ($args['assigned_role'] ?? ''));

        if ($vendor_id <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return array(
                'state' => 'no-response',
                'label' => __('No reply', 'backstage-venue-manager'),
                'reason' => 'invalid',
                'detail' => __('Date or vendor record is invalid.', 'backstage-venue-manager'),
                'source' => vms_vendor_availability_source_label('invalid'),
                'conflict' => false,
                'assignable' => false,
                'visual_state' => '',
            );
        }

        if ($assigned_here) {
            $detail = $assigned_role !== ''
                ? sprintf(
                    /* translators: %s: assigned role label for the vendor on this Event Plan. */
                    __('Assigned on this Event Plan as %s.', 'backstage-venue-manager'),
                    strtolower($assigned_role)
                )
                : __('Assigned on this Event Plan.', 'backstage-venue-manager');

            return array(
                'state' => 'current',
                'label' => __('Current assignment', 'backstage-venue-manager'),
                'reason' => 'assigned_here',
                'detail' => $detail,
                'source' => vms_vendor_availability_source_label('assigned_here'),
                'conflict' => false,
                'assignable' => false,
                'visual_state' => 'unavailable',
            );
        }

        if ($busy_source === 'booked') {
            return array(
                'state' => 'booked',
                'label' => __('Booked', 'backstage-venue-manager'),
                'reason' => 'assigned_elsewhere',
                'detail' => __('Booked on another Event Plan for this same date.', 'backstage-venue-manager'),
                'source' => vms_vendor_availability_source_label('assigned_elsewhere'),
                'conflict' => true,
                'assignable' => false,
                'visual_state' => 'unavailable',
            );
        }
        if ($busy_source === 'tentative') {
            return array(
                'state' => 'tentative',
                'label' => __('Tentative', 'backstage-venue-manager'),
                'reason' => 'assigned_elsewhere',
                'detail' => __('Tentatively assigned on another Event Plan for this same date.', 'backstage-venue-manager'),
                'source' => vms_vendor_availability_source_label('assigned_elsewhere'),
                'conflict' => true,
                'assignable' => false,
                'visual_state' => 'unavailable',
            );
        }

        $manual = vms_vendor_normalize_manual_availability($vendor_id);
        $manual_state = isset($manual[$date]) ? sanitize_key((string) $manual[$date]) : '';
        if ($manual_state === 'available') {
            return array(
                'state' => 'available',
                'label' => __('Available', 'backstage-venue-manager'),
                'reason' => 'manual',
                'detail' => __('Marked available manually for this date.', 'backstage-venue-manager'),
                'source' => vms_vendor_availability_source_label('manual'),
                'conflict' => false,
                'assignable' => true,
                'visual_state' => 'available',
            );
        }
        if ($manual_state === 'unavailable') {
            return array(
                'state' => 'unavailable',
                'label' => __('Unavailable', 'backstage-venue-manager'),
                'reason' => 'manual',
                'detail' => __('Marked unavailable manually for this date.', 'backstage-venue-manager'),
                'source' => vms_vendor_availability_source_label('manual'),
                'conflict' => false,
                'assignable' => false,
                'visual_state' => 'unavailable',
            );
        }

        $pattern_enabled = (int) get_post_meta($vendor_id, '_vms_pattern_enabled', true);
        $pattern_days = vms_vendor_normalize_pattern_days($vendor_id);
        $pattern_matches = false;
        if ($pattern_enabled && !empty($pattern_days)) {
            try {
                $dt = new DateTimeImmutable($date . ' 12:00:00', function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC'));
                $dow = (int) $dt->format('w');
                if (!in_array($dow, $pattern_days, true)) {
                    return array(
                        'state' => 'unavailable',
                        'label' => __('Unavailable', 'backstage-venue-manager'),
                        'reason' => 'pattern',
                        'detail' => __('Outside the vendor\'s weekly availability pattern.', 'backstage-venue-manager'),
                        'source' => vms_vendor_availability_source_label('pattern'),
                        'conflict' => false,
                        'assignable' => false,
                        'visual_state' => 'unavailable',
                    );
                }
                $pattern_matches = true;
            } catch (Exception $e) {
                $pattern_matches = false;
            }
        }

        $ics_unavailable = vms_vendor_normalize_ics_unavailable($vendor_id);
        if (in_array($date, $ics_unavailable, true)) {
            return array(
                'state' => 'unavailable',
                'label' => __('Unavailable', 'backstage-venue-manager'),
                'reason' => 'ics',
                'detail' => __('Blocked by synced calendar (ICS).', 'backstage-venue-manager'),
                'source' => vms_vendor_availability_source_label('ics'),
                'conflict' => false,
                'assignable' => false,
                'visual_state' => 'unavailable',
            );
        }

        if ($pattern_matches) {
            return array(
                'state' => 'available',
                'label' => __('Available', 'backstage-venue-manager'),
                'reason' => 'pattern',
                'detail' => __('Matches the vendor\'s weekly availability pattern.', 'backstage-venue-manager'),
                'source' => vms_vendor_availability_source_label('pattern'),
                'conflict' => false,
                'assignable' => true,
                'visual_state' => 'available',
            );
        }

        $detail = vms_vendor_has_availability_setup($vendor_id)
            ? __('No explicit availability signal was found for this date.', 'backstage-venue-manager')
            : __('Vendor has not set availability yet.', 'backstage-venue-manager');

        return array(
            'state' => 'no-response',
            'label' => __('No reply', 'backstage-venue-manager'),
            'reason' => 'no_response',
            'detail' => $detail,
            'source' => vms_vendor_availability_source_label('no_response'),
            'conflict' => false,
            'assignable' => true,
            'visual_state' => '',
        );
    }
}

/**
 * Best-effort venue id lookup for vendor (supports future multi-venue behavior).
 *
 * @return int Venue post ID or 0 when unknown
 */
function vms_vendor_guess_venue_id(int $vendor_id): int
{
    $vendor_id = (int) $vendor_id;
    if ($vendor_id <= 0) return 0;

    $keys = array(
        '_vms_home_venue_id',
        '_vms_primary_venue_id',
        '_vms_venue_id',
        'vms_venue_id',
    );

    foreach ($keys as $k) {
        $val = (int) get_post_meta($vendor_id, $k, true);
        if ($val > 0) return $val;
    }

    return 0;
}

/**
 * Season dates (global fallback plus optional per-venue override).
 *
 * If your core plugin already defines vms_get_active_season_dates(), that version wins.
 * This is only a safe fallback to prevent fatals and keep one source of truth.
 *
 * @return string[] YYYY-MM-DD
 */
if (!function_exists('bvmgr_get_active_season_dates')) {

    /**
     * Normalize a day-of-week value into 0..6 (Sun..Sat). Accepts ints, "sun", "Sunday", etc.
     */
    function vms_norm_dow($v): ?int
    {
        if ($v === null || $v === '') return null;
        if (is_numeric($v)) {
            $n = (int) $v;
            // Accept 0..6 (Sun..Sat) or 1..7 (Mon..Sun) common formats.
            if ($n >= 0 && $n <= 6) return $n;
            if ($n >= 1 && $n <= 7) return ($n % 7); // 7 -> 0
            return null;
        }
        $s = strtolower(trim((string) $v));
        $s = preg_replace('/[^a-z]/', '', $s);
        $map = array(
            'sun' => 0,
            'sunday' => 0,
            'mon' => 1,
            'monday' => 1,
            'tue' => 2,
            'tues' => 2,
            'tuesday' => 2,
            'wed' => 3,
            'wednesday' => 3,
            'thu' => 4,
            'thur' => 4,
            'thurs' => 4,
            'thursday' => 4,
            'fri' => 5,
            'friday' => 5,
            'sat' => 6,
            'saturday' => 6,
        );
        return $map[$s] ?? null;
    }

    /**
     * Parse a date string in common formats into DateTimeImmutable (site timezone).
     */
    function vms_parse_date_any($v): ?DateTimeImmutable
    {
        if ($v === null || $v === '') return null;

        $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');

        // Timestamp
        if (is_numeric($v)) {
            try {
                return (new DateTimeImmutable('@' . (int) $v))->setTimezone($tz);
            } catch (Exception $e) {
                return null;
            }
        }

        $s = trim((string) $v);

        $formats = array('Y-m-d', 'm/d/Y', 'n/j/Y', 'm-d-Y', 'n-j-Y');
        foreach ($formats as $fmt) {
            $dt = DateTimeImmutable::createFromFormat($fmt, $s, $tz);
            if ($dt instanceof DateTimeImmutable) {
                // createFromFormat can succeed with warnings; guard by re-format check
                if ($dt->format($fmt) === $s) return $dt;
            }
        }

        // Fallback: strtotime
        $ts = strtotime($s);
        if ($ts !== false) {
            try {
                return (new DateTimeImmutable('@' . $ts))->setTimezone($tz);
            } catch (Exception $e) {
                return null;
            }
        }

        return null;
    }

    /**
     * Get a reasonable month-window (bounds) to generate active dates within.
     * Uses the same month window as the availability grid when possible.
     */
    function vms_av_get_window_bounds(): array
    {
        $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');

        if (function_exists('vms_av_build_month_window')) {
            $months = (array) vms_av_build_month_window();
            $months = array_values(array_filter($months, function ($m) {
                return is_string($m) && preg_match('/^\d{4}-\d{2}$/', $m);
            }));
            if (!empty($months)) {
                $first = $months[0] . '-01';
                $last  = $months[count($months) - 1] . '-01';
                $start = new DateTimeImmutable($first, $tz);
                $end   = (new DateTimeImmutable($last, $tz))->modify('last day of this month');
                return array($start, $end);
            }
        }

        $start = (new DateTimeImmutable('first day of this month', $tz))->setTime(0, 0, 0);
        $end   = $start->modify('+18 months')->modify('last day of this month')->setTime(23, 59, 59);
        return array($start, $end);
    }

    /**
     * Normalize any supported "season rules" structure into a list of seasons:
     *   [ ['start' => DateTimeImmutable, 'end' => DateTimeImmutable, 'dows' => [0..6]], … ]
     */
    function vms_normalize_season_rules($raw): array
    {
        $seasons = array();

        if (!is_array($raw) || empty($raw)) return $seasons;

        // If wrapped
        if (isset($raw['seasons']) && is_array($raw['seasons'])) $raw = $raw['seasons'];

        // Case: list of season arrays
        foreach ($raw as $k => $season) {
            if (!is_array($season)) continue;

            $start_raw = $season['start'] ?? $season['start_date'] ?? $season['from'] ?? $season['begin'] ?? null;
            $end_raw   = $season['end']   ?? $season['end_date']   ?? $season['to']   ?? $season['finish'] ?? null;

            // Some UIs store as ['start' => ['y'=>..,'m'=>..,'d'=>..]]
            if (is_array($start_raw) && isset($start_raw['y'], $start_raw['m'], $start_raw['d'])) $start_raw = sprintf('%04d-%02d-%02d', (int)$start_raw['y'], (int)$start_raw['m'], (int)$start_raw['d']);
            if (is_array($end_raw)   && isset($end_raw['y'], $end_raw['m'], $end_raw['d']))     $end_raw   = sprintf('%04d-%02d-%02d', (int)$end_raw['y'],   (int)$end_raw['m'],   (int)$end_raw['d']);

            $start = vms_parse_date_any($start_raw);
            $end   = vms_parse_date_any($end_raw);
            if (!$start || !$end) continue;

            // Days of week
            $days_raw = $season['days'] ?? $season['days_of_week'] ?? $season['dow'] ?? $season['dows'] ?? $season['weekdays'] ?? array();
            if (!is_array($days_raw)) $days_raw = array($days_raw);

            $dows = array();
            foreach ($days_raw as $dv) {
                $n = vms_norm_dow($dv);
                if ($n === null) continue;
                $dows[$n] = true;
            }

            // Require at least 1 day selected
            if (empty($dows)) continue;

            $seasons[] = array('start' => $start->setTime(0, 0, 0), 'end' => $end->setTime(23, 59, 59), 'dows' => array_keys($dows));
        }

        // Case: associative "season1_*" keys
        if (empty($seasons)) {
            for ($i = 1; $i <= 10; $i++) {
                $start_raw = $raw["season{$i}_start"] ?? $raw["season{$i}_start_date"] ?? $raw["season_{$i}_start"] ?? $raw["season_{$i}_start_date"] ?? null;
                $end_raw   = $raw["season{$i}_end"]   ?? $raw["season{$i}_end_date"]   ?? $raw["season_{$i}_end"]   ?? $raw["season_{$i}_end_date"]   ?? null;
                $days_raw  = $raw["season{$i}_days"]  ?? $raw["season{$i}_dows"]       ?? $raw["season_{$i}_days"]  ?? $raw["season_{$i}_dows"]       ?? null;

                if ($start_raw === null && $end_raw === null && $days_raw === null) continue;

                $start = vms_parse_date_any($start_raw);
                $end   = vms_parse_date_any($end_raw);
                if (!$start || !$end) continue;

                if (!is_array($days_raw)) $days_raw = $days_raw !== null ? array($days_raw) : array();

                $dows = array();
                foreach ($days_raw as $dv) {
                    $n = vms_norm_dow($dv);
                    if ($n === null) continue;
                    $dows[$n] = true;
                }
                if (empty($dows)) continue;

                $seasons[] = array('start' => $start->setTime(0, 0, 0), 'end' => $end->setTime(23, 59, 59), 'dows' => array_keys($dows));
            }
        }

        // Single season object
        if (empty($seasons) && (isset($raw['start']) || isset($raw['start_date']))) {
            $single = array($raw);
            return vms_normalize_season_rules($single);
        }

        return $seasons;
    }

    /**
     * Generate active YYYY-MM-DD dates from season rules within the availability window.
     */
    function vms_generate_active_dates_from_rules($raw_rules): array
    {
        $seasons = vms_normalize_season_rules($raw_rules);
        if (empty($seasons)) return array();

        list($win_start, $win_end) = vms_av_get_window_bounds();

        $out = array();
        foreach ($seasons as $s) {
            /** @var DateTimeImmutable $s_start */
            $s_start = $s['start'];
            /** @var DateTimeImmutable $s_end */
            $s_end   = $s['end'];
            $dows    = $s['dows'];

            if ($s_end < $win_start || $s_start > $win_end) continue;

            $start = $s_start < $win_start ? $win_start : $s_start;
            $end   = $s_end   > $win_end   ? $win_end   : $s_end;

            $cur = $start->setTime(0, 0, 0);
            $end_day = $end->setTime(0, 0, 0);

            while ($cur <= $end_day) {
                $dow = (int) $cur->format('w'); // 0..6 (Sun..Sat)
                if (in_array($dow, $dows, true)) {
                    $out[$cur->format('Y-m-d')] = true;
                }
                $cur = $cur->modify('+1 day');
            }
        }

        $dates = array_keys($out);
        sort($dates);
        return $dates;
    }

    // function vms_get_active_season_dates(int $venue_id = 0): array
    // {
    //     $venue_id = (int) $venue_id;
    //     if ($venue_id <= 0) {
    //         return [];
    //     }

    //     // 1) Canonical v1 active payload (stored in vms_season_active_dates_v1 keyed by venue_id)
    //     if (function_exists('vms_sch_season_get_active_payload')) {
    //         $payload = vms_sch_season_get_active_payload($venue_id);

    //         $dates = [];

    //         // Common payload shapes
    //         if (is_array($payload)) {
    //             $keys = ['dates', 'dates_ymd', 'active_dates', 'ymd_list', 'list', 'ymd'];
    //             foreach ($keys as $k) {
    //                 if (isset($payload[$k]) && is_array($payload[$k])) {
    //                     $dates = $payload[$k];
    //                     break;
    //                 }
    //             }

    //             // Payload itself may just be a list of strings
    //             if (empty($dates)) {
    //                 $all_strings = true;
    //                 foreach ($payload as $v) {
    //                     if (!is_string($v)) {
    //                         $all_strings = false;
    //                         break;
    //                     }
    //                 }
    //                 if ($all_strings) {
    //                     $dates = $payload;
    //                 }
    //             }
    //         }

    //         $dates = array_values(array_unique(array_filter(array_map('strval', $dates))));

    //         if (function_exists('vms_sch_season_is_valid_ymd')) {
    //             $dates = array_values(array_filter($dates, 'vms_sch_season_is_valid_ymd'));
    //         }

    //         if (!empty($dates)) {
    //             return $dates;
    //         }
    //     }

    //     // 2) Legacy fallback (keep, but do NOT prefer it)
    //     $legacy = [];

    //     $opt = get_option('vms_season_active_dates_' . $venue_id, []);
    //     if (is_array($opt) && !empty($opt)) {
    //         $legacy = $opt;
    //     }

    //     // Some older installs stored a global map; accept map[venue_id] if present
    //     if (empty($legacy)) {
    //         $opt2 = get_option('vms_season_active_dates', []);
    //         if (is_array($opt2) && isset($opt2[$venue_id]) && is_array($opt2[$venue_id])) {
    //             $legacy = $opt2[$venue_id];
    //         }
    //     }

    //     $legacy = array_values(array_unique(array_filter(array_map('strval', $legacy))));
    //     if (function_exists('vms_sch_season_is_valid_ymd')) {
    //         $legacy = array_values(array_filter($legacy, 'vms_sch_season_is_valid_ymd'));
    //     }

    //     if (!empty($legacy)) {
    //         error_log('VMS: Vendor portal used legacy active dates for venue_id=' . $venue_id . ' (v1 payload empty).');
    //         return $legacy;
    //     }

    //     return [];
    // }
}

/**
 * Call vms_get_active_season_dates safely, whether it expects (venue_id) or no arguments.
 *
 * @return string[] YYYY-MM-DD
 */
function vms_vendor_try_get_active_season_dates(int $venue_id = 0): array
{
    if (!function_exists('bvmgr_get_active_season_dates')) return array();

    try {
        $rf = new ReflectionFunction('bvmgr_get_active_season_dates');
        $n  = $rf->getNumberOfParameters();

        $out = ($n >= 1) ? bvmgr_get_active_season_dates($venue_id) : bvmgr_get_active_season_dates();
        if (!is_array($out)) $out = array();
        $out = array_values(array_filter(array_map('sanitize_text_field', $out)));
        return $out;
    } catch (Throwable $e) {
        return array();
    }
}

/**
 * Build a list of months to render (always show months, even when out of season).
 *
 * @return array<string,bool> map of 'YYYY-MM' => true
 */
if (!function_exists('vms_av_build_month_window')) {
    function vms_av_build_month_window(int $months_ahead = 13, int $months_back = 12): array
    {
        $months_ahead = (int) $months_ahead;
        $months_back  = (int) $months_back;

        // Guardrails (cap to keep the UI + payload reasonable)
        if ($months_ahead < 1) $months_ahead = 13;
        if ($months_back < 0) $months_back = 12;

        if ($months_ahead > 24) $months_ahead = 24;
        if ($months_back > 24) $months_back = 24;

        $tz  = wp_timezone();
        $cur = new DateTimeImmutable('first day of this month 00:00:00', $tz);

        if ($months_back > 0) {
            $cur = $cur->modify('-' . $months_back . ' months');
        }

        $total = $months_ahead + $months_back;

        $months = array();
        for ($i = 0; $i < $total; $i++) {
            $ym = $cur->format('Y-m');
            $months[$ym] = true;
            $cur = $cur->modify('+1 month');
        }

        return $months;
    }
}

/**
 * Year-round availability dates.
 * - Uses vms_get_active_season_dates() if configured
 * - Otherwise generates a rolling window of days (default 12 months, cap 24)
 *
 * @return string[] YYYY-MM-DD
 */
function vms_vendor_get_active_dates_or_rolling_window(int $months_ahead = 12, int $venue_id = 0): array
{
    $months_ahead = (int) $months_ahead;
    if ($months_ahead < 1) $months_ahead = 12;
    if ($months_ahead > 24) $months_ahead = 24;

    // Season dates (if configured via admin UI, with optional per-venue override)
    $season = vms_vendor_try_get_active_season_dates((int) $venue_id);

    // Rolling window dates (fallback when no season dates exist)
    $tz = wp_timezone();
    $start = new DateTime('today', $tz);
    $end   = (clone $start)->modify('+' . $months_ahead . ' months');

    $rolling = array();
    $cur = clone $start;
    while ($cur < $end) {
        $rolling[] = $cur->format('Y-m-d');
        $cur->modify('+1 day');
    }

    // If season dates exist, they define what is toggleable (open); months still render separately.
    if (!empty($season)) {
        sort($season);
        return $season;
    }

    return $rolling;
}

if (!function_exists('vms_vendor_portal_flash_key')) {
    function vms_vendor_portal_flash_key(int $user_id): string
    {
        return 'vms_vendor_portal_flash_' . max(0, $user_id);
    }
}

if (!function_exists('vms_vendor_portal_set_flash')) {
    function vms_vendor_portal_set_flash(int $user_id, array $payload): void
    {
        if ($user_id <= 0) {
            return;
        }
        set_transient(vms_vendor_portal_flash_key($user_id), $payload, 120);
    }
}

if (!function_exists('vms_vendor_portal_pull_flash')) {
    function vms_vendor_portal_pull_flash(int $user_id): array
    {
        if ($user_id <= 0) {
            return array();
        }
        $key = vms_vendor_portal_flash_key($user_id);
        $data = get_transient($key);
        delete_transient($key);
        return is_array($data) ? $data : array();
    }
}

if (!function_exists('vms_vendor_portal_is_primary_type_slug')) {
    function vms_vendor_portal_is_primary_type_slug(string $slug): bool
    {
        $slug = sanitize_title($slug);
        if ($slug === '') {
            return false;
        }
        if (function_exists('vms_add_dispatch_is_primary_vendor_type_slug')) {
            return (bool) vms_add_dispatch_is_primary_vendor_type_slug($slug);
        }
        return in_array($slug, array('artist', 'band', 'bands', 'headliner', 'musician', 'performer', 'performers', 'talent'), true);
    }
}

if (!function_exists('vms_vendor_portal_current_type_slug')) {
    function vms_vendor_portal_current_type_slug(int $vendor_id): string
    {
        return function_exists('bvmgr_calendar_vendor_primary_type')
            ? sanitize_title((string) (bvmgr_calendar_vendor_primary_type($vendor_id)['slug'] ?? ''))
            : '';
    }
}


if (!function_exists('vms_vendor_portal_headliner_promo_video_meta_key')) {
    function vms_vendor_portal_headliner_promo_video_meta_key(string $field): string
    {
        $field = sanitize_key($field);
        $fallbacks = array(
            'attachment_id' => '_vms_headliner_promo_video_attachment_id',
            'hidden' => '_vms_headliner_promo_video_hidden',
            'uploaded_at_gmt' => '_vms_headliner_promo_video_uploaded_at_gmt',
            'uploaded_by' => '_vms_headliner_promo_video_uploaded_by',
            'source_type' => '_vms_headliner_promo_video_source_type',
            'external_url' => '_vms_headliner_promo_video_external_url',
            'pending_attachment_id' => '_vms_headliner_promo_video_pending_attachment_id',
            'pending_uploaded_at_gmt' => '_vms_headliner_promo_video_pending_uploaded_at_gmt',
            'pending_uploaded_by' => '_vms_headliner_promo_video_pending_uploaded_by',
        );
        if (!isset($fallbacks[$field])) {
            return '';
        }

        $key_map = array(
            'attachment_id' => 'headliner_promo_video_attachment_id',
            'hidden' => 'headliner_promo_video_hidden',
            'uploaded_at_gmt' => 'headliner_promo_video_uploaded_at_gmt',
            'uploaded_by' => 'headliner_promo_video_uploaded_by',
            'source_type' => 'headliner_promo_video_source_type',
            'external_url' => 'headliner_promo_video_external_url',
            'pending_attachment_id' => 'headliner_promo_video_pending_attachment_id',
            'pending_uploaded_at_gmt' => 'headliner_promo_video_pending_uploaded_at_gmt',
            'pending_uploaded_by' => 'headliner_promo_video_pending_uploaded_by',
        );

        if (function_exists('bvmgr_meta_key')) {
            $resolved = (string) bvmgr_meta_key('event_plan', $key_map[$field]);
            if ($resolved !== '') {
                return $resolved;
            }
        }

        return $fallbacks[$field];
    }
}

if (!function_exists('vms_vendor_portal_headliner_promo_video_allowed_mimes')) {
    function vms_vendor_portal_headliner_promo_video_allowed_mimes(): array
    {
        return array(
            'mp4'  => 'video/mp4',
            'm4v'  => 'video/mp4',
            'mov'  => 'video/quicktime',
            'webm' => 'video/webm',
        );
    }
}

if (!function_exists('vms_vendor_portal_headliner_promo_video_max_bytes')) {
    function vms_vendor_portal_headliner_promo_video_max_bytes(): int
    {
        $configured = 100 * 1024 * 1024;
        $wp_limit = (int) wp_max_upload_size();
        if ($wp_limit > 0) {
            return max(1, min($configured, $wp_limit));
        }
        return $configured;
    }
}

if (!function_exists('vms_vendor_portal_public_image_allowed_mimes')) {
    function vms_vendor_portal_public_image_allowed_mimes(): array
    {
        return array(
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
        );
    }
}

if (!function_exists('vms_vendor_portal_public_image_max_bytes')) {
    function vms_vendor_portal_public_image_max_bytes(): int
    {
        $configured = 10 * 1024 * 1024;
        $wp_limit = (int) wp_max_upload_size();
        if ($wp_limit > 0) {
            return max(1, min($configured, $wp_limit));
        }

        return $configured;
    }
}

if (!function_exists('vms_vendor_portal_validate_public_image_upload')) {
    /**
     * @return array<string,mixed>|WP_Error
     */
    function vms_vendor_portal_validate_public_image_upload(string $field_name)
    {
        $upload = vms_vendor_portal_read_uploaded_file_request($field_name);
        if (is_wp_error($upload)) {
            return $upload;
        }

        return bvmgr_validate_uploaded_file(
            $upload,
            array(
                'allowed_mimes' => vms_vendor_portal_public_image_allowed_mimes(),
                'max_bytes' => vms_vendor_portal_public_image_max_bytes(),
                'type_message' => __('Please upload a JPG, PNG, or WEBP image.', 'backstage-venue-manager'),
                'empty_message' => __('That image appears to be empty.', 'backstage-venue-manager'),
                'too_large_message' => __('That image is too large.', 'backstage-venue-manager'),
                'tmp_invalid_message' => __('The uploaded image could not be verified.', 'backstage-venue-manager'),
            )
        );
    }
}

if (!function_exists('vms_vendor_portal_handle_public_image_upload')) {
    /**
     * @return int|WP_Error
     */
    function vms_vendor_portal_handle_public_image_upload(string $field_name, int $post_id = 0)
    {
        $validated = vms_vendor_portal_validate_public_image_upload($field_name);
        if (is_wp_error($validated)) {
            return $validated;
        }

        if (!function_exists('media_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        return media_handle_upload($field_name, $post_id, array(), array(
            'test_form' => false,
            'mimes' => vms_vendor_portal_public_image_allowed_mimes(),
        ));
    }
}

if (!function_exists('vms_vendor_portal_validate_headliner_promo_video_upload')) {
    /**
     * @return array<string,mixed>|WP_Error
     */
    function vms_vendor_portal_validate_headliner_promo_video_upload(string $field_name)
    {
        $upload = vms_vendor_portal_read_uploaded_file_request($field_name);
        if (is_wp_error($upload)) {
            return $upload;
        }

        return bvmgr_validate_uploaded_file(
            $upload,
            array(
                'allowed_mimes' => vms_vendor_portal_headliner_promo_video_allowed_mimes(),
                'max_bytes' => vms_vendor_portal_headliner_promo_video_max_bytes(),
                'type_message' => __('Please upload an MP4, MOV, or WebM video.', 'backstage-venue-manager'),
                'empty_message' => __('That file appears to be empty.', 'backstage-venue-manager'),
                'too_large_message' => sprintf(
                    /* translators: %s: current upload size limit for the promo video. */ __('That file is too large. Please keep it under %s.', 'backstage-venue-manager'),
                    function_exists('size_format') ? size_format(vms_vendor_portal_headliner_promo_video_max_bytes(), 0) : (string) vms_vendor_portal_headliner_promo_video_max_bytes()
                ),
                'tmp_invalid_message' => __('We could not verify the uploaded file. Please try again.', 'backstage-venue-manager'),
            )
        );
    }
}

if (!function_exists('vms_vendor_portal_handle_headliner_promo_video_media_upload')) {
    /**
     * @return int|WP_Error
     */
    function vms_vendor_portal_handle_headliner_promo_video_media_upload(string $field_name, int $plan_id)
    {
        $validated = vms_vendor_portal_validate_headliner_promo_video_upload($field_name);
        if (is_wp_error($validated)) {
            return $validated;
        }

        if (!function_exists('media_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        return media_handle_upload($field_name, $plan_id, array(), array(
            'test_form' => false,
            'mimes' => vms_vendor_portal_headliner_promo_video_allowed_mimes(),
        ));
    }
}

if (!function_exists('vms_vendor_portal_headliner_promo_video_accept_attr')) {
    function vms_vendor_portal_headliner_promo_video_accept_attr(): string
    {
        return 'video/mp4,video/quicktime,video/webm,.mp4,.m4v,.mov,.webm';
    }
}


if (!function_exists('vms_vendor_portal_headliner_promo_video_allowed_external_hosts')) {
    function vms_vendor_portal_headliner_promo_video_allowed_external_hosts(): array
    {
        return array(
            'youtube.com',
            'www.youtube.com',
            'm.youtube.com',
            'youtu.be',
            'www.youtu.be',
            'vimeo.com',
            'www.vimeo.com',
            'player.vimeo.com',
            'facebook.com',
            'www.facebook.com',
            'm.facebook.com',
            'fb.watch',
            'www.fb.watch',
            'instagram.com',
            'www.instagram.com',
        );
    }
}

if (!function_exists('vms_vendor_portal_headliner_promo_video_allowed_html')) {
    function vms_vendor_portal_headliner_promo_video_allowed_html(): array
    {
        $allowed = wp_kses_allowed_html('post');
        $allowed['iframe'] = array(
            'allow' => true,
            'allowfullscreen' => true,
            'class' => true,
            'frameborder' => true,
            'height' => true,
            'loading' => true,
            'name' => true,
            'referrerpolicy' => true,
            'sandbox' => true,
            'src' => true,
            'title' => true,
            'width' => true,
        );
        $allowed['video'] = array(
            'class' => true,
            'controls' => true,
            'loop' => true,
            'muted' => true,
            'playsinline' => true,
            'poster' => true,
            'preload' => true,
        );
        $allowed['source'] = array(
            'src' => true,
            'type' => true,
        );

        return $allowed;
    }
}

if (!function_exists('vms_vendor_portal_normalize_promo_video_external_url')) {
    function vms_vendor_portal_normalize_promo_video_external_url(string $url): string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }
        $url = esc_url_raw($url, array('http', 'https'));
        if ($url === '' || !wp_http_validate_url($url)) {
            return '';
        }
        $parts = wp_parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '') {
            return '';
        }
        if (!in_array($host, vms_vendor_portal_headliner_promo_video_allowed_external_hosts(), true)) {
            return '';
        }
        return $url;
    }
}

if (!function_exists('vms_vendor_portal_promo_video_provider_from_url')) {
    function vms_vendor_portal_promo_video_provider_from_url(string $url): string
    {
        $host = strtolower((string) (wp_parse_url($url, PHP_URL_HOST) ?: ''));
        if ($host === '') {
            return 'link';
        }
        if (strpos($host, 'youtu') !== false) {
            return 'youtube';
        }
        if (strpos($host, 'vimeo') !== false) {
            return 'vimeo';
        }
        if (strpos($host, 'instagram') !== false) {
            return 'instagram';
        }
        if (strpos($host, 'facebook') !== false || strpos($host, 'fb.watch') !== false) {
            return 'facebook';
        }
        return 'link';
    }
}

if (!function_exists('vms_vendor_portal_promo_video_provider_label')) {
    function vms_vendor_portal_promo_video_provider_label(string $provider): string
    {
        $provider = sanitize_key($provider);
        $labels = array(
            'youtube' => __('YouTube link', 'backstage-venue-manager'),
            'vimeo' => __('Vimeo link', 'backstage-venue-manager'),
            'facebook' => __('Facebook link', 'backstage-venue-manager'),
            'instagram' => __('Instagram link', 'backstage-venue-manager'),
            'link' => __('External video link', 'backstage-venue-manager'),
        );
        return (string) ($labels[$provider] ?? __('External video link', 'backstage-venue-manager'));
    }
}

if (!function_exists('vms_vendor_portal_get_headliner_promo_video_attachment_payload')) {
    function vms_vendor_portal_get_headliner_promo_video_attachment_payload(int $attachment_id): array
    {
        $attachment_id = absint($attachment_id);
        if ($attachment_id <= 0) {
            return array('attachment_id' => 0);
        }

        $url = (string) wp_get_attachment_url($attachment_id);
        if ($url === '') {
            return array('attachment_id' => 0);
        }

        $mime = (string) get_post_mime_type($attachment_id);
        $duration_seconds = 0;
        $meta = wp_get_attachment_metadata($attachment_id);
        if (is_array($meta) && !empty($meta['length'])) {
            $duration_seconds = (int) $meta['length'];
        }

        return array(
            'attachment_id' => $attachment_id,
            'url' => $url,
            'mime' => $mime,
            'title' => (string) get_the_title($attachment_id),
            'duration_seconds' => $duration_seconds,
        );
    }
}

if (!function_exists('vms_vendor_portal_render_headliner_promo_video_markup_from_data')) {
    function vms_vendor_portal_render_headliner_promo_video_markup_from_data(array $data, array $args = array()): string
    {
        $context = isset($args['context']) ? sanitize_key((string) $args['context']) : 'public';
        if (empty($data)) {
            return '';
        }
        if ($context === 'public' && !empty($data['hidden'])) {
            return '';
        }

        $source_type = sanitize_key((string) ($data['source_type'] ?? ''));
        $heading = isset($args['heading']) ? trim((string) $args['heading']) : '';
        $description = isset($args['description']) ? trim((string) $args['description']) : '';
        $wrap_class = isset($args['wrap_class']) ? trim((string) $args['wrap_class']) : '';
        $classes = trim('vms-headliner-promo-video ' . $wrap_class);
        $provider_label = '';
        $body_markup = '';

        if ($source_type === 'attachment' && !empty($data['url'])) {
            $mime = !empty($data['mime']) ? (string) $data['mime'] : 'video/mp4';
            $url = (string) $data['url'];
            $body_markup = '<div class="vms-headliner-promo-video__frame"><video class="vms-headliner-promo-video__player" controls preload="metadata" playsinline><source src="' . esc_url($url) . '" type="' . esc_attr($mime) . '"><a href="' . esc_url($url) . '" target="_blank" rel="noopener">' . esc_html__('Watch promo video', 'backstage-venue-manager') . '</a></video></div>';
        } elseif ($source_type === 'external' && !empty($data['external_url'])) {
            $external_url = (string) $data['external_url'];
            $provider = sanitize_key((string) ($data['provider'] ?? 'link'));
            $provider_label = vms_vendor_portal_promo_video_provider_label($provider);
            $oembed = wp_oembed_get($external_url, array('width' => 960));
            if (is_string($oembed) && $oembed !== '') {
                $body_markup = '<div class="vms-headliner-promo-video__frame vms-headliner-promo-video__frame--embed">' . $oembed . '</div>';
            } else {
                $body_markup = '<div class="vms-headliner-promo-video__frame vms-headliner-promo-video__frame--link"><a class="button" href="' . esc_url($external_url) . '" target="_blank" rel="noopener">' . esc_html__('Open promo video', 'backstage-venue-manager') . '</a></div>';
            }
        }

        if ($body_markup === '') {
            return '';
        }

        ob_start();
        ?>
        <div class="<?php echo esc_attr($classes); ?>">
            <?php if ($heading !== '') : ?>
                <p class="vms-headliner-promo-video__eyebrow"><?php echo esc_html($heading); ?></p>
            <?php endif; ?>
            <?php if ($description !== '') : ?>
                <p class="vms-headliner-promo-video__description"><?php echo esc_html($description); ?></p>
            <?php endif; ?>
            <?php if ($provider_label !== '') : ?>
                <p class="vms-headliner-promo-video__description"><?php echo esc_html($provider_label); ?></p>
            <?php endif; ?>
            <?php echo wp_kses($body_markup, vms_vendor_portal_headliner_promo_video_allowed_html()); ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}

if (!function_exists('vms_vendor_portal_is_headliner_for_plan')) {
    function vms_vendor_portal_is_headliner_for_plan(int $plan_id, int $vendor_id): bool
    {
        $plan_id = (int) $plan_id;
        $vendor_id = (int) $vendor_id;
        if ($plan_id <= 0 || $vendor_id <= 0) {
            return false;
        }

        $band_key = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'band_vendor_id') : '_vms_band_vendor_id';
        if ($band_key === '') {
            $band_key = '_vms_band_vendor_id';
        }

        return ((int) get_post_meta($plan_id, $band_key, true) === $vendor_id);
    }
}

if (!function_exists('vms_vendor_portal_get_next_headliner_booking')) {
    function vms_vendor_portal_get_next_headliner_booking(int $vendor_id): array
    {
        $vendor_id = (int) $vendor_id;
        if ($vendor_id <= 0) {
            return array();
        }

        $date_key = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'date') : '_vms_event_date';
        if ($date_key === '') {
            $date_key = '_vms_event_date';
        }
        $band_key = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'band_vendor_id') : '_vms_band_vendor_id';
        if ($band_key === '') {
            $band_key = '_vms_band_vendor_id';
        }
        $tec_key = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'tec_event_id') : '_vms_tec_event_id';
        if ($tec_key === '') {
            $tec_key = '_vms_tec_event_id';
        }

        $today = function_exists('wp_date')
            ? wp_date('Y-m-d', time(), function_exists('wp_timezone') ? wp_timezone() : null)
            : gmdate('Y-m-d');

        $plans = get_posts(array(
            'post_type'      => 'vms_event_plan',
            'post_status'    => array('publish', 'draft', 'pending', 'private'),
            'posts_per_page' => 1,
            'orderby'        => 'meta_value',
            'meta_key'       => $date_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- The next-headliner lookup intentionally orders one upcoming Event Plan by the canonical event-date metadata.
            'order'          => 'ASC',
            'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- The next-headliner lookup requires canonical date and primary-vendor assignment metadata; no equivalent indexed domain fields exist.
                'relation' => 'AND',
                array(
                    'key'   => $band_key,
                    'value' => (string) $vendor_id,
                ),
                array(
                    'key'     => $date_key,
                    'value'   => $today,
                    'compare' => '>=',
                    'type'    => 'DATE',
                ),
            ),
        ));

        if (empty($plans) || !isset($plans[0]) || !($plans[0] instanceof WP_Post)) {
            return array();
        }

        $plan = $plans[0];
        $plan_id = (int) $plan->ID;
        $event_date = trim((string) get_post_meta($plan_id, $date_key, true));
        $date_label = $event_date !== ''
            ? (function_exists('bvmgr_format_local_ymd') ? bvmgr_format_local_ymd($event_date, 'F j, Y') : $event_date)
            : '';

        $tec_event_id = (int) get_post_meta($plan_id, $tec_key, true);
        $event_url = '';
        $event_title = (string) get_the_title($plan_id);
        if ($tec_event_id > 0) {
            $tec_post = get_post($tec_event_id);
            if ($tec_post instanceof WP_Post && $tec_post->post_status === 'publish') {
                $event_url = (string) get_permalink($tec_event_id);
                $event_title = (string) get_the_title($tec_event_id);
            }
        }

        return array(
            'plan_id'      => $plan_id,
            'tec_event_id' => $tec_event_id,
            'title'        => $event_title,
            'date'         => $event_date,
            'date_label'   => $date_label,
            'event_url'    => $event_url,
        );
    }
}

if (!function_exists('vms_vendor_portal_get_headliner_promo_video_data')) {
    function vms_vendor_portal_get_headliner_promo_video_data(int $plan_id): array
    {
        $plan_id = (int) $plan_id;
        if ($plan_id <= 0) {
            return array();
        }

        $attachment_key = vms_vendor_portal_headliner_promo_video_meta_key('attachment_id');
        $hidden_key = vms_vendor_portal_headliner_promo_video_meta_key('hidden');
        $uploaded_key = vms_vendor_portal_headliner_promo_video_meta_key('uploaded_at_gmt');
        $source_key = vms_vendor_portal_headliner_promo_video_meta_key('source_type');
        $external_key = vms_vendor_portal_headliner_promo_video_meta_key('external_url');

        $attachment_id = (int) get_post_meta($plan_id, $attachment_key, true);
        $hidden = ((string) get_post_meta($plan_id, $hidden_key, true) === '1');
        $uploaded_at_gmt = (string) get_post_meta($plan_id, $uploaded_key, true);
        $source_type = sanitize_key((string) get_post_meta($plan_id, $source_key, true));
        $external_url = vms_vendor_portal_normalize_promo_video_external_url((string) get_post_meta($plan_id, $external_key, true));

        if ($source_type === '') {
            if ($external_url !== '') {
                $source_type = 'external';
            } elseif ($attachment_id > 0) {
                $source_type = 'attachment';
            }
        }

        if ($source_type === 'external' && $external_url !== '') {
            $provider = vms_vendor_portal_promo_video_provider_from_url($external_url);
            return array(
                'source_type' => 'external',
                'attachment_id' => $attachment_id,
                'external_url' => $external_url,
                'provider' => $provider,
                'provider_label' => vms_vendor_portal_promo_video_provider_label($provider),
                'hidden' => $hidden,
                'uploaded_at_gmt' => $uploaded_at_gmt,
            );
        }

        $attachment = vms_vendor_portal_get_headliner_promo_video_attachment_payload($attachment_id);
        if (!empty($attachment['attachment_id'])) {
            $attachment['source_type'] = 'attachment';
            $attachment['external_url'] = '';
            $attachment['provider'] = '';
            $attachment['provider_label'] = '';
            $attachment['hidden'] = $hidden;
            $attachment['uploaded_at_gmt'] = $uploaded_at_gmt;
            return $attachment;
        }

        return array(
            'source_type' => 'none',
            'attachment_id' => 0,
            'external_url' => '',
            'hidden' => $hidden,
            'uploaded_at_gmt' => $uploaded_at_gmt,
        );
    }
}

if (!function_exists('vms_vendor_portal_get_headliner_promo_video_submission_data')) {
    function vms_vendor_portal_get_headliner_promo_video_submission_data(int $plan_id): array
    {
        $plan_id = (int) $plan_id;
        if ($plan_id <= 0) {
            return array();
        }

        $attachment_id = (int) get_post_meta($plan_id, vms_vendor_portal_headliner_promo_video_meta_key('pending_attachment_id'), true);
        if ($attachment_id <= 0) {
            return array('attachment_id' => 0);
        }

        $attachment = vms_vendor_portal_get_headliner_promo_video_attachment_payload($attachment_id);
        if (empty($attachment['attachment_id'])) {
            return array('attachment_id' => 0);
        }

        $attachment['source_type'] = 'attachment';
        $attachment['hidden'] = false;
        $attachment['uploaded_at_gmt'] = (string) get_post_meta($plan_id, vms_vendor_portal_headliner_promo_video_meta_key('pending_uploaded_at_gmt'), true);
        $attachment['uploaded_by'] = (int) get_post_meta($plan_id, vms_vendor_portal_headliner_promo_video_meta_key('pending_uploaded_by'), true);
        return $attachment;
    }
}

if (!function_exists('vms_vendor_portal_render_headliner_promo_video_player')) {
    function vms_vendor_portal_render_headliner_promo_video_player(int $plan_id, array $args = array()): string
    {
        $plan_id = (int) $plan_id;
        if ($plan_id <= 0) {
            return '';
        }

        $data = vms_vendor_portal_get_headliner_promo_video_data($plan_id);
        return vms_vendor_portal_render_headliner_promo_video_markup_from_data($data, $args);
    }
}

if (!function_exists('vms_vendor_portal_render_headliner_promo_video_card')) {
    function vms_vendor_portal_render_headliner_promo_video_card(int $vendor_id, int $user_id, string $base_url = ''): void
    {
        $vendor_id = (int) $vendor_id;
        $user_id = (int) $user_id;
        if ($vendor_id <= 0 || $user_id <= 0) {
            return;
        }

        $next = vms_vendor_portal_get_next_headliner_booking($vendor_id);
        if (empty($next['plan_id'])) {
            return;
        }

        $plan_id = (int) $next['plan_id'];
        $return_url = $base_url !== ''
            ? add_query_arg(array('tab' => 'dashboard', 'vendor_id' => $vendor_id), $base_url)
            : home_url('/vendor-portal/?tab=dashboard');
        $video = vms_vendor_portal_get_headliner_promo_video_data($plan_id);
        $submitted = vms_vendor_portal_get_headliner_promo_video_submission_data($plan_id);
        $max_bytes = vms_vendor_portal_headliner_promo_video_max_bytes();
        $max_label = function_exists('size_format') ? size_format($max_bytes, 0) : (string) $max_bytes;
        $wp_limit = (int) wp_max_upload_size();

        echo '<div class="vms-portal-card vms-portal-promo-video-card">';
        echo '<div class="vms-portal-promo-video-card__header">';
        echo '<div>';
        echo '<h3>' . esc_html__('Intro Video for Your Next Show', 'backstage-venue-manager') . '</h3>';
        if (!empty($next['title'])) {
            echo '<p class="vms-muted vms-m0"><strong>' . esc_html((string) $next['title']) . '</strong>';
            if (!empty($next['date_label'])) {
                echo ' · ' . esc_html((string) $next['date_label']);
            }
            echo '</p>';
        }
        echo '</div>';
        if (!empty($next['event_url'])) {
            echo '<a class="button" target="_blank" rel="noopener" href="' . esc_url((string) $next['event_url']) . '">' . esc_html__('View Event Page', 'backstage-venue-manager') . '</a>';
        }
        echo '</div>';

        echo '<p class="vms-muted">';
        echo esc_html__('Record a quick “we’re coming to the show” clip right on your phone and submit it here. iPhone video is okay. We review it before it goes live so the public event page stays clean and browser-safe.', 'backstage-venue-manager');
        echo '</p>';
        echo '<p class="vms-muted vms-portal-promo-video-card__limits">';
        /* translators: %s: maximum recommended upload size. */
        echo esc_html(sprintf(__('Recommended formats: MP4, MOV, or WebM. Maximum upload: %s. Aim for 30–60 seconds.', 'backstage-venue-manager'), $max_label));
        if ($wp_limit > 0 && $wp_limit < $max_bytes && function_exists('size_format')) {
            /* translators: %s: current site upload size limit. */
            echo ' ' . esc_html(sprintf(__('Your current site upload limit is %s.', 'backstage-venue-manager'), size_format($wp_limit, 0)));
        }
        echo '</p>';

        if (function_exists('vms_vendor_booking_onboarding_get_vendor_plan_status')) {
            $booking_status = (array) vms_vendor_booking_onboarding_get_vendor_plan_status($plan_id, $vendor_id);
            if (!empty($booking_status['video_required'])) {
                echo '<p class="vms-muted vms-m0">';
                echo esc_html((string) ($booking_status['video_label'] ?? __('Video needed', 'backstage-venue-manager')));
                if (!empty($booking_status['video_waived'])) {
                    echo ' ' . esc_html__('An operator has waived it for this show, so you can skip it if needed.', 'backstage-venue-manager');
                } elseif (!empty($booking_status['initial_sent_at_gmt'])) {
                    $requested_ts = strtotime((string) $booking_status['initial_sent_at_gmt'] . ' GMT');
                    if ($requested_ts) {
                        $requested_label = function_exists('wp_date')
                            ? wp_date('M j, Y g:ia', $requested_ts, function_exists('wp_timezone') ? wp_timezone() : null)
                            : date_i18n('M j, Y g:ia', $requested_ts);
                        /* translators: %s: date and time from the vendor booking email request. */
                        echo ' ' . esc_html(sprintf(__('Requested in your booking email on %s.', 'backstage-venue-manager'), $requested_label));
                    }
                }
                echo '</p>';
            }
        }

        if (!empty($video['source_type']) && $video['source_type'] !== 'none') {
            echo wp_kses(vms_vendor_portal_render_headliner_promo_video_markup_from_data($video, array(
                'context' => 'portal',
                'heading' => __('Current public promo', 'backstage-venue-manager'),
                'wrap_class' => 'vms-headliner-promo-video--portal',
            )), vms_vendor_portal_headliner_promo_video_allowed_html());
            if (!empty($video['uploaded_at_gmt'])) {
                $uploaded_ts = strtotime((string) $video['uploaded_at_gmt'] . ' GMT');
                if ($uploaded_ts) {
                    $uploaded_label = function_exists('wp_date')
                        ? wp_date('M j, Y g:ia', $uploaded_ts, function_exists('wp_timezone') ? wp_timezone() : null)
                        : date_i18n('M j, Y g:ia', $uploaded_ts);
                    /* translators: %s: date and time when the current public promo video was last updated. */
                    echo '<p class="vms-muted vms-m0">' . esc_html(sprintf(__('Current public video updated: %s', 'backstage-venue-manager'), $uploaded_label)) . '</p>';
                }
            }
        }

        if (!empty($submitted['attachment_id'])) {
            echo wp_kses(vms_vendor_portal_render_headliner_promo_video_markup_from_data($submitted, array(
                'context' => 'portal',
                'heading' => __('Submitted for review', 'backstage-venue-manager'),
                'description' => __('Thanks — your clip is in for operator review. It will not appear publicly until we approve it or swap in a browser-safe version.', 'backstage-venue-manager'),
                'wrap_class' => 'vms-headliner-promo-video--portal',
            )), vms_vendor_portal_headliner_promo_video_allowed_html());
            if (!empty($submitted['uploaded_at_gmt'])) {
                $submitted_ts = strtotime((string) $submitted['uploaded_at_gmt'] . ' GMT');
                if ($submitted_ts) {
                    $submitted_label = function_exists('wp_date')
                        ? wp_date('M j, Y g:ia', $submitted_ts, function_exists('wp_timezone') ? wp_timezone() : null)
                        : date_i18n('M j, Y g:ia', $submitted_ts);
                    /* translators: %s: date and time when the promo video was submitted for review. */
                    echo '<p class="vms-muted vms-m0">' . esc_html(sprintf(__('Submitted: %s', 'backstage-venue-manager'), $submitted_label)) . '</p>';
                }
            }
        }

        if ((empty($video['source_type']) || $video['source_type'] === 'none') && empty($submitted['attachment_id'])) {
            echo '<p class="vms-muted vms-portal-promo-video-card__empty">' . esc_html__('No intro video has been submitted for this show yet.', 'backstage-venue-manager') . '</p>';
        }

        echo '<form class="vms-portal-promo-video-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '" enctype="multipart/form-data">';
        echo '<input type="hidden" name="action" value="vms_vendor_portal_headliner_promo_video_upload">';
        echo '<input type="hidden" name="vendor_id" value="' . esc_attr((string) $vendor_id) . '">';
        echo '<input type="hidden" name="plan_id" value="' . esc_attr((string) $plan_id) . '">';
        echo '<input type="hidden" name="return_url" value="' . esc_attr($return_url) . '">';
        wp_nonce_field('vms_vendor_portal_headliner_promo_video_upload_' . $plan_id, '_vms_headliner_promo_video_nonce');
        echo '<label class="vms-field">';
        echo '<span><strong>' . esc_html__('Submit or replace intro video', 'backstage-venue-manager') . '</strong></span>';
        echo '<input type="file" name="vms_headliner_promo_video" accept="' . esc_attr(vms_vendor_portal_headliner_promo_video_accept_attr()) . '" required>';
        echo '</label>';
        echo '<div class="vms-portal-promo-video-card__actions">';
        echo '<button type="submit" class="button button-primary">' . esc_html(!empty($submitted['attachment_id']) ? __('Replace Submitted Video', 'backstage-venue-manager') : __('Submit Intro Video', 'backstage-venue-manager')) . '</button>';
        echo '</div>';
        echo '</form>';

        if (!empty($submitted['attachment_id']) || (!empty($video['source_type']) && $video['source_type'] !== 'none')) {
            echo '<form class="vms-portal-promo-video-remove" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            echo '<input type="hidden" name="action" value="vms_vendor_portal_headliner_promo_video_remove">';
            echo '<input type="hidden" name="vendor_id" value="' . esc_attr((string) $vendor_id) . '">';
            echo '<input type="hidden" name="plan_id" value="' . esc_attr((string) $plan_id) . '">';
            echo '<input type="hidden" name="return_url" value="' . esc_attr($return_url) . '">';
            wp_nonce_field('vms_vendor_portal_headliner_promo_video_remove_' . $plan_id, '_vms_headliner_promo_video_remove_nonce');
            $remove_label = !empty($submitted['attachment_id']) ? __('Remove Submitted Video', 'backstage-venue-manager') : __('Remove Current Video', 'backstage-venue-manager');
            echo '<button type="submit" class="button button-secondary">' . esc_html($remove_label) . '</button>';
            echo '</form>';
        }

        echo '</div>';
    }
}

if (!function_exists('vms_vendor_portal_can_manage_headliner_promo_video')) {
    function vms_vendor_portal_can_manage_headliner_promo_video(int $user_id, int $vendor_id, int $plan_id): bool
    {
        $user_id = (int) $user_id;
        $vendor_id = (int) $vendor_id;
        $plan_id = (int) $plan_id;

        if ($user_id <= 0 || $vendor_id <= 0 || $plan_id <= 0) {
            return false;
        }
        if (!vms_vendor_portal_is_headliner_for_plan($plan_id, $vendor_id)) {
            return false;
        }
        if (current_user_can('edit_post', $plan_id)) {
            return true;
        }
        if (function_exists('vms_user_can_access_vendor') && vms_user_can_access_vendor($user_id, $vendor_id)) {
            return true;
        }
        $linked_vendor_ids = function_exists('vms_get_active_vendor_ids_for_user') ? (array) vms_get_active_vendor_ids_for_user($user_id) : array();
        return in_array($vendor_id, array_map('absint', $linked_vendor_ids), true);
    }
}

if (!function_exists('vms_vendor_portal_handle_headliner_promo_video_upload')) {
    function vms_vendor_portal_handle_headliner_promo_video_upload(): void
    {
        if (!is_user_logged_in()) {
            wp_die(esc_html__('Please log in to upload a promo video.', 'backstage-venue-manager'));
        }

        $user_id = (int) get_current_user_id();
        $plan_id = vms_vendor_portal_post_absint('plan_id');
        $nonce = (isset($_POST['_vms_headliner_promo_video_nonce']) && !is_array($_POST['_vms_headliner_promo_video_nonce']))
            ? sanitize_text_field(wp_unslash((string) $_POST['_vms_headliner_promo_video_nonce']))
            : '';

        if ($plan_id <= 0 || $nonce === '' || !wp_verify_nonce($nonce, 'vms_vendor_portal_headliner_promo_video_upload_' . $plan_id)) {
            wp_die(esc_html__('Security check failed.', 'backstage-venue-manager'));
        }

        $vendor_id = vms_vendor_portal_post_absint('vendor_id');
        $return_url = vms_vendor_portal_post_return_url(home_url('/vendor-portal/?tab=dashboard'));
        if ($vendor_id <= 0) {
            wp_die(esc_html__('Security check failed.', 'backstage-venue-manager'));
        }

        if (!vms_vendor_portal_can_manage_headliner_promo_video($user_id, $vendor_id, $plan_id)) {
            vms_vendor_portal_set_flash($user_id, array('type' => 'error', 'message' => __('You do not have permission to manage that promo video.', 'backstage-venue-manager')));
            wp_safe_redirect($return_url);
            exit;
        }

        if (empty($_FILES['vms_headliner_promo_video']) || !is_array($_FILES['vms_headliner_promo_video'])) {
            vms_vendor_portal_set_flash($user_id, array('type' => 'error', 'message' => __('Please choose a video file to upload.', 'backstage-venue-manager')));
            wp_safe_redirect($return_url);
            exit;
        }

        $attachment_id = function_exists('vms_vendor_portal_handle_headliner_promo_video_media_upload')
            ? vms_vendor_portal_handle_headliner_promo_video_media_upload('vms_headliner_promo_video', $plan_id)
            : new WP_Error('promo_video_upload_unavailable', __('The promo video upload handler is unavailable.', 'backstage-venue-manager'));

        if (is_wp_error($attachment_id)) {
            /* translators: %s: media library upload error message. */
            vms_vendor_portal_set_flash($user_id, array('type' => 'error', 'message' => sprintf(__('Upload failed: %s', 'backstage-venue-manager'), $attachment_id->get_error_message())));
            wp_safe_redirect($return_url);
            exit;
        }

        update_post_meta($plan_id, vms_vendor_portal_headliner_promo_video_meta_key('pending_attachment_id'), (int) $attachment_id);
        update_post_meta($plan_id, vms_vendor_portal_headliner_promo_video_meta_key('pending_uploaded_at_gmt'), current_time('mysql', true));
        update_post_meta($plan_id, vms_vendor_portal_headliner_promo_video_meta_key('pending_uploaded_by'), $user_id);

        if (function_exists('vms_vendor_flag_vendor_update')) {
            vms_vendor_flag_vendor_update($vendor_id, 'headliner_promo_video', array('plan_id' => $plan_id));
        }

        $duration_notice = '';
        $data = vms_vendor_portal_get_headliner_promo_video_submission_data($plan_id);
        if (!empty($data['duration_seconds']) && (int) $data['duration_seconds'] > 90) {
            $duration_notice = ' ' . __('Heads up: that clip is longer than 90 seconds, so a shorter cut may perform better.', 'backstage-venue-manager');
        }

        vms_vendor_portal_set_flash($user_id, array(
            'type' => 'success',
            'message' => __('Intro video submitted. We will review it before it goes live on your upcoming show.', 'backstage-venue-manager') . $duration_notice,
        ));
        wp_safe_redirect($return_url);
        exit;
    }
}
add_action('admin_post_vms_vendor_portal_headliner_promo_video_upload', 'vms_vendor_portal_handle_headliner_promo_video_upload');

if (!function_exists('vms_vendor_portal_handle_headliner_promo_video_remove')) {
    function vms_vendor_portal_handle_headliner_promo_video_remove(): void
    {
        if (!is_user_logged_in()) {
            wp_die(esc_html__('Please log in to remove a promo video.', 'backstage-venue-manager'));
        }

        $user_id = (int) get_current_user_id();
        $plan_id = vms_vendor_portal_post_absint('plan_id');
        $nonce = (isset($_POST['_vms_headliner_promo_video_remove_nonce']) && !is_array($_POST['_vms_headliner_promo_video_remove_nonce']))
            ? sanitize_text_field(wp_unslash((string) $_POST['_vms_headliner_promo_video_remove_nonce']))
            : '';

        if ($plan_id <= 0 || $nonce === '' || !wp_verify_nonce($nonce, 'vms_vendor_portal_headliner_promo_video_remove_' . $plan_id)) {
            wp_die(esc_html__('Security check failed.', 'backstage-venue-manager'));
        }

        $vendor_id = vms_vendor_portal_post_absint('vendor_id');
        $return_url = vms_vendor_portal_post_return_url(home_url('/vendor-portal/?tab=dashboard'));
        if ($vendor_id <= 0) {
            wp_die(esc_html__('Security check failed.', 'backstage-venue-manager'));
        }

        if (!vms_vendor_portal_can_manage_headliner_promo_video($user_id, $vendor_id, $plan_id)) {
            vms_vendor_portal_set_flash($user_id, array('type' => 'error', 'message' => __('You do not have permission to manage that promo video.', 'backstage-venue-manager')));
            wp_safe_redirect($return_url);
            exit;
        }

        $removed_submission = false;
        if ((int) get_post_meta($plan_id, vms_vendor_portal_headliner_promo_video_meta_key('pending_attachment_id'), true) > 0) {
            delete_post_meta($plan_id, vms_vendor_portal_headliner_promo_video_meta_key('pending_attachment_id'));
            delete_post_meta($plan_id, vms_vendor_portal_headliner_promo_video_meta_key('pending_uploaded_at_gmt'));
            delete_post_meta($plan_id, vms_vendor_portal_headliner_promo_video_meta_key('pending_uploaded_by'));
            $removed_submission = true;
        } else {
            delete_post_meta($plan_id, vms_vendor_portal_headliner_promo_video_meta_key('attachment_id'));
            delete_post_meta($plan_id, vms_vendor_portal_headliner_promo_video_meta_key('source_type'));
            delete_post_meta($plan_id, vms_vendor_portal_headliner_promo_video_meta_key('external_url'));
            delete_post_meta($plan_id, vms_vendor_portal_headliner_promo_video_meta_key('hidden'));
            delete_post_meta($plan_id, vms_vendor_portal_headliner_promo_video_meta_key('uploaded_at_gmt'));
            delete_post_meta($plan_id, vms_vendor_portal_headliner_promo_video_meta_key('uploaded_by'));
        }

        if (function_exists('vms_vendor_flag_vendor_update')) {
            vms_vendor_flag_vendor_update($vendor_id, 'headliner_promo_video', array('plan_id' => $plan_id));
        }

        $message = $removed_submission
            ? __('Submitted intro video removed from this show.', 'backstage-venue-manager')
            : __('Current promo video removed from this upcoming show.', 'backstage-venue-manager');
        vms_vendor_portal_set_flash($user_id, array('type' => 'success', 'message' => $message));
        wp_safe_redirect($return_url);
        exit;
    }
}
add_action('admin_post_vms_vendor_portal_headliner_promo_video_remove', 'vms_vendor_portal_handle_headliner_promo_video_remove');


if (!function_exists('vms_vendor_portal_normalize_compare_label')) {
    function vms_vendor_portal_normalize_compare_label(string $text): string
    {
        $text = wp_strip_all_tags($text);
        $text = strtolower($text);
        $text = str_replace(array('&', '@'), array(' and ', ' at '), $text);
        $text = preg_replace('/[^a-z0-9]+/', ' ', $text);
        $text = preg_replace('/\s+/', ' ', (string) $text);
        return trim((string) $text);
    }
}

if (!function_exists('vms_vendor_portal_labels_are_effectively_same')) {
    function vms_vendor_portal_labels_are_effectively_same(string $left, string $right): bool
    {
        $left_norm = vms_vendor_portal_normalize_compare_label($left);
        $right_norm = vms_vendor_portal_normalize_compare_label($right);
        if ($left_norm === '' || $right_norm === '') {
            return false;
        }
        if ($left_norm === $right_norm) {
            return true;
        }
        if (strlen($left_norm) >= 6 && strpos($right_norm, $left_norm) !== false) {
            return true;
        }
        if (strlen($right_norm) >= 6 && strpos($left_norm, $right_norm) !== false) {
            return true;
        }
        similar_text($left_norm, $right_norm, $percent);
        return $percent >= 88.0;
    }
}

if (!function_exists('vms_vendor_portal_format_modal_time_label')) {
    function vms_vendor_portal_format_modal_time_label(string $start_local, string $end_local = ''): string
    {
        $format_one = static function (string $value): string {
            $value = trim($value);
            if ($value === '') {
                return '';
            }
            try {
                $dt = new DateTimeImmutable($value);
                return $dt->format('g:ia');
            } catch (Exception $e) {
                return '';
            }
        };

        $start_label = $format_one($start_local);
        $end_label = $format_one($end_local);
        if ($start_label !== '' && $end_label !== '') {
            return $start_label . ' - ' . $end_label;
        }
        return $start_label !== '' ? $start_label : $end_label;
    }
}

if (!function_exists('vms_vendor_portal_modal_date_label')) {
    function vms_vendor_portal_modal_date_label(string $date_key): string
    {
        $date_key = trim($date_key);
        if ($date_key === '') {
            return '';
        }
        return function_exists('bvmgr_format_local_ymd')
            ? bvmgr_format_local_ymd($date_key, 'F j, Y')
            : $date_key;
    }
}

if (!function_exists('vms_vendor_portal_opportunity_target_meta')) {
    function vms_vendor_portal_opportunity_target_meta(string $viewer_type, array $groups, array $context = array()): array
    {
        $viewer_type = sanitize_key($viewer_type);
        $icon_map = function_exists('vms_calendar_vendor_type_icons') ? (array) vms_calendar_vendor_type_icons() : array();
        $secondary_type = sanitize_key((string) ($context['secondary_vendor_type'] ?? ''));
        $secondary_label = trim((string) ($context['secondary_vendor_type_label'] ?? ''));

        if ($viewer_type !== '' && function_exists('vms_vendor_portal_is_primary_type_slug') && vms_vendor_portal_is_primary_type_slug($viewer_type)) {
            $icon = trim((string) ($icon_map[$viewer_type] ?? ($icon_map['talent'] ?? '')));
            $label = function_exists('vms_add_dispatch_type_label') ? trim((string) vms_add_dispatch_type_label($viewer_type)) : '';
            if ($label === '' || sanitize_key($label) === 'talent') {
                $label = __('Primary Vendor', 'backstage-venue-manager');
            }
            return array(
                'icon' => $icon,
                'label' => $label,
            );
        }

        if ($secondary_type !== '') {
            return array(
                'icon' => trim((string) ($icon_map[$secondary_type] ?? ($icon_map[$viewer_type] ?? ''))),
                'label' => $secondary_label !== '' ? $secondary_label : (function_exists('vms_add_dispatch_type_label') ? (string) vms_add_dispatch_type_label($secondary_type) : __('Vendor', 'backstage-venue-manager')),
            );
        }

        return array(
            'icon' => trim((string) ($icon_map[$viewer_type] ?? '')),
            'label' => function_exists('vms_add_dispatch_type_label') ? (string) vms_add_dispatch_type_label($viewer_type) : __('Vendor', 'backstage-venue-manager'),
        );
    }
}

if (!function_exists('vms_vendor_portal_submit_application_label')) {
    function vms_vendor_portal_submit_application_label(string $type_label): string
    {
        $type_label = trim($type_label);
        if ($type_label === '') {
            return __('Submit Application', 'backstage-venue-manager');
        }
        /* translators: %s: vendor type label for the application button text. */
        return sprintf(__('Submit %s Application', 'backstage-venue-manager'), $type_label);
    }
}

if (!function_exists('vms_vendor_portal_calendar_event_lines')) {
    function vms_vendor_portal_calendar_event_lines(array $event, string $viewer_type, int $viewer_vendor_id): array
    {
        $plan_id = absint($event['event_plan_id'] ?? 0);
        $title = trim((string) ($event['title'] ?? ''));
        $public_url = trim((string) ($event['public_url'] ?? ''));
        $line_items = array();
        $icon_map = function_exists('vms_calendar_vendor_type_icons') ? (array) vms_calendar_vendor_type_icons() : array();

        $start_local = isset($event['start_local']) ? (string) $event['start_local'] : '';
        $end_local = isset($event['end_local']) ? (string) ($event['end_local'] ?? '') : '';
        $time_label = function_exists('vms_vendor_portal_format_modal_time_label')
            ? vms_vendor_portal_format_modal_time_label($start_local, $end_local)
            : '';
        $date_label = function_exists('vms_vendor_portal_modal_date_label')
            ? vms_vendor_portal_modal_date_label((string) ($event['date_key'] ?? ''))
            : '';

        $event_label = $title !== '' ? $title : __('Event', 'backstage-venue-manager');
        if ($time_label !== '') {
            $event_label .= ' @ ' . $time_label;
        }

        $modal_base = array(
            'event_plan_id' => $plan_id,
            'modal_title' => $title !== '' ? $title : __('Event', 'backstage-venue-manager'),
            'modal_date_label' => $date_label,
            'modal_time_label' => $time_label,
            'modal_excerpt' => trim((string) ($event['excerpt'] ?? '')),
            'modal_image_url' => trim((string) ($event['image_url'] ?? '')),
            'modal_view_url' => $public_url,
            'modal_venue_name' => trim((string) ($event['venue_name'] ?? '')),
        );

        if ($plan_id <= 0 || !function_exists('bvmgr_calendar_plan_vendor_ids')) {
            $line_items[] = array_merge(array(
                'kind' => 'event',
                'text' => $event_label,
                'url'  => $public_url,
            ), $modal_base);
            return $line_items;
        }

        $vendor_ids = (array) bvmgr_calendar_plan_vendor_ids($plan_id);
        $primary_vendor_id = absint($vendor_ids['band_id'] ?? 0);
        $secondary_vendor_ids = array_values(array_unique(array_filter(array_map('absint', (array) ($vendor_ids['secondary_ids'] ?? array())))));
        $lineup_vendor_ids = array_values(array_unique(array_filter(array_map('absint', (array) ($vendor_ids['lineup_ids'] ?? array())))));

        $event_icon = '';
        $primary_name = $primary_vendor_id > 0 ? trim((string) get_the_title($primary_vendor_id)) : '';
        if ($primary_vendor_id > 0) {
            $primary_type = function_exists('bvmgr_calendar_vendor_primary_type') ? (array) bvmgr_calendar_vendor_primary_type($primary_vendor_id) : array();
            $primary_slug = sanitize_key((string) ($primary_type['slug'] ?? 'talent'));
            if ($primary_slug === '') {
                $primary_slug = 'talent';
            }
            $primary_icon = trim((string) ($icon_map[$primary_slug] ?? ($icon_map['talent'] ?? '')));
            if ($primary_name !== '' && vms_vendor_portal_labels_are_effectively_same($primary_name, $title)) {
                $event_icon = $primary_icon;
            } elseif ($primary_name !== '') {
                $line_items[] = array(
                    'kind' => 'vendor',
                    'text' => trim(($primary_icon !== '' ? ($primary_icon . ' ') : '') . $primary_name),
                    'url'  => '',
                    'event_plan_id' => $plan_id,
                );
            }
        }

        $line_items = array_merge(array(array_merge(array(
            'kind' => 'event',
            'text' => trim(($event_icon !== '' ? ($event_icon . ' ') : '') . $event_label),
            'url'  => $public_url,
        ), $modal_base)), $line_items);

        foreach ($secondary_vendor_ids as $secondary_vendor_id) {
            if ($secondary_vendor_id <= 0) {
                continue;
            }
            $secondary_name = trim((string) get_the_title($secondary_vendor_id));
            if ($secondary_name === '') {
                continue;
            }
            if ($secondary_vendor_id === $viewer_vendor_id && $viewer_type !== '' && !vms_vendor_portal_is_primary_type_slug($viewer_type)) {
                continue;
            }
            $secondary_type = function_exists('bvmgr_calendar_vendor_primary_type') ? (array) bvmgr_calendar_vendor_primary_type($secondary_vendor_id) : array();
            $secondary_slug = sanitize_key((string) ($secondary_type['slug'] ?? ''));
            $secondary_icon = trim((string) ($icon_map[$secondary_slug] ?? ''));
            $line_items[] = array(
                'kind' => 'vendor',
                'text' => trim(($secondary_icon !== '' ? ($secondary_icon . ' ') : '') . $secondary_name),
                'url'  => '',
                'event_plan_id' => $plan_id,
            );
        }

        foreach ($lineup_vendor_ids as $lineup_vendor_id) {
            if ($lineup_vendor_id <= 0 || $lineup_vendor_id === $primary_vendor_id || in_array($lineup_vendor_id, $secondary_vendor_ids, true)) {
                continue;
            }
            $lineup_name = trim((string) get_the_title($lineup_vendor_id));
            if ($lineup_name === '') {
                continue;
            }
            $lineup_type = function_exists('bvmgr_calendar_vendor_primary_type') ? (array) bvmgr_calendar_vendor_primary_type($lineup_vendor_id) : array();
            $lineup_slug = sanitize_key((string) ($lineup_type['slug'] ?? ''));
            $lineup_icon = trim((string) ($icon_map[$lineup_slug] ?? ($icon_map['talent'] ?? '')));
            $line_items[] = array(
                'kind' => 'vendor',
                'text' => trim(($lineup_icon !== '' ? ($lineup_icon . ' ') : '') . $lineup_name),
                'url'  => '',
                'event_plan_id' => $plan_id,
            );
        }

        if (function_exists('vms_add_dispatch_get_event_plan_context')) {
            $context = (array) vms_add_dispatch_get_event_plan_context($plan_id);
            $missing_slots = array_map('sanitize_key', (array) ($context['missing_slots'] ?? array()));
            $secondary_type_slug = sanitize_key((string) ($context['secondary_vendor_type'] ?? ''));
            if (vms_vendor_portal_is_primary_type_slug($viewer_type)) {
                if (in_array('secondary', $missing_slots, true) && $secondary_type_slug !== '' && empty($secondary_vendor_ids)) {
                    $secondary_icon = trim((string) ($icon_map[$secondary_type_slug] ?? ''));
                    $line_items[] = array(
                        'kind' => 'slot',
                        'text' => trim(($secondary_icon !== '' ? ($secondary_icon . ' ') : '') . __('Open', 'backstage-venue-manager')),
                        'url'  => '',
                        'event_plan_id' => $plan_id,
                    );
                }
            } elseif ($viewer_type !== '' && in_array('primary', $missing_slots, true) && $primary_vendor_id <= 0) {
                $primary_icon = trim((string) ($icon_map['talent'] ?? ($icon_map[$viewer_type] ?? '')));
                $line_items[] = array(
                    'kind' => 'slot',
                    'text' => trim(($primary_icon !== '' ? ($primary_icon . ' ') : '') . __('Open', 'backstage-venue-manager')),
                    'url'  => '',
                    'event_plan_id' => $plan_id,
                );
            }
        }

        return $line_items;
    }
}

if (!function_exists('vms_vendor_portal_interest_event_ids')) {
    function vms_vendor_portal_interest_event_ids(int $vendor_id): array
    {
        if ($vendor_id <= 0 || !function_exists('vms_add_dispatch_get_vendor_portal_interest_rows')) {
            return array();
        }
        $rows = (array) vms_add_dispatch_get_vendor_portal_interest_rows($vendor_id, 50);
        $ids = array();
        foreach ($rows as $row) {
            $event_plan_id = absint($row['event_plan_id'] ?? 0);
            if ($event_plan_id > 0) {
                $ids[] = $event_plan_id;
            }
        }
        return array_values(array_unique($ids));
    }
}

if (!function_exists('vms_vendor_portal_opportunity_status')) {
    function vms_vendor_portal_opportunity_status(int $vendor_id, int $event_plan_id): array
    {
        $context = function_exists('vms_add_dispatch_get_event_plan_context')
            ? vms_add_dispatch_get_event_plan_context($event_plan_id)
            : null;
        if (!$context || !empty($context['is_past_event'])) {
            return array(
                'visible' => false,
                'status' => '',
                'label' => '',
                'can_submit' => false,
                'can_withdraw' => false,
                'submitted_at' => '',
            );
        }

        $viewer_type = vms_vendor_portal_current_type_slug($vendor_id);
        $assigned_ids = array_values(array_unique(array_filter(array_merge(
            array((int) ($context['primary_vendor_id'] ?? 0)),
            array_map('absint', (array) ($context['secondary_vendor_ids'] ?? array()))
        ), static function (int $assigned_vendor_id): bool {
            return $assigned_vendor_id > 0;
        })));

        if (in_array($vendor_id, $assigned_ids, true)) {
            return array(
                'visible' => true,
                'status' => 'accepted',
                'label' => __('Booked', 'backstage-venue-manager'),
                'can_submit' => false,
                'can_withdraw' => false,
                'submitted_at' => '',
            );
        }

        $missing = array_map('sanitize_key', (array) ($context['missing_slots'] ?? array()));
        $secondary_type = sanitize_title((string) ($context['secondary_vendor_type'] ?? ''));
        $is_open = false;
        if ($viewer_type !== '') {
            if (in_array('primary', $missing, true) && vms_vendor_portal_is_primary_type_slug($viewer_type)) {
                $is_open = true;
            } elseif (in_array('secondary', $missing, true) && $secondary_type !== '' && $secondary_type === $viewer_type) {
                $is_open = true;
            }
        }

        $interest = function_exists('vms_add_dispatch_get_portal_interest_response')
            ? vms_add_dispatch_get_portal_interest_response($event_plan_id, $vendor_id, false)
            : null;
        if (is_array($interest)) {
            $assigned_at = trim((string) ($interest['assigned_at'] ?? ''));
            if ($assigned_at !== '') {
                return array(
                    'visible' => true,
                    'status' => 'accepted',
                    'label' => __('Booked', 'backstage-venue-manager'),
                    'can_submit' => false,
                    'can_withdraw' => false,
                    'submitted_at' => (string) (($interest['responded_at'] ?? '') ?: ($interest['created_at'] ?? '')),
                );
            }

            $request_status = sanitize_key((string) ($interest['request_status'] ?? 'active'));
            $response_status = sanitize_key((string) ($interest['response_status'] ?? 'requested'));
            $submitted_at = (string) (($interest['responded_at'] ?? '') ?: ($interest['created_at'] ?? ''));

            if ($response_status === 'unavailable') {
                return array(
                    'visible' => true,
                    'status' => 'withdrawn',
                    'label' => __('Withdrawn', 'backstage-venue-manager'),
                    'can_submit' => $is_open,
                    'can_withdraw' => false,
                    'submitted_at' => $submitted_at,
                );
            }

            return array(
                'visible' => true,
                'status' => $request_status === 'active' ? 'pending' : 'reviewed',
                'label' => $request_status === 'active' ? __('Requested', 'backstage-venue-manager') : __('Submitted', 'backstage-venue-manager'),
                'can_submit' => false,
                'can_withdraw' => $request_status === 'active' && $response_status === 'available',
                'submitted_at' => $submitted_at,
            );
        }

        return array(
            'visible' => $is_open || $viewer_type !== '',
            'status' => $is_open ? 'open' : 'booked',
            'label' => $is_open ? __('Open', 'backstage-venue-manager') : __('Booked', 'backstage-venue-manager'),
            'can_submit' => $is_open,
            'can_withdraw' => false,
            'submitted_at' => '',
        );
    }
}

if (!function_exists('vms_vendor_portal_render_opportunities')) {
    function vms_vendor_portal_render_opportunities(array $portal_context): void
    {
        $vendor_id = (int) ($portal_context['vendor_id'] ?? 0);
        $user_id = (int) ($portal_context['user_id'] ?? 0);
        $flash = vms_vendor_portal_pull_flash($user_id);

        echo '<div class="vms-portal-card vms-vio-opps-card">';
        echo '<h3>' . esc_html__('Opportunities', 'backstage-venue-manager') . '</h3>';
        if (!empty($flash['message'])) {
            echo wp_kses_post(bvmgr_portal_notice(!empty($flash['type']) ? (string) $flash['type'] : 'success', (string) $flash['message']));
        }

        $today = wp_date('Y-m-d', time(), wp_timezone());
        $end = wp_date('Y-m-d', strtotime('+12 months', strtotime($today)), wp_timezone());
        $events = function_exists('bvmgr_get_calendar_events')
            ? (array) bvmgr_get_calendar_events(array(
                'start_date' => $today,
                'end_date' => $end,
                'context' => 'vendor',
                'viewer_vendor_id' => $vendor_id,
                'include_statuses' => function_exists('vms_calendar_vendor_context_default_statuses')
                    ? (array) vms_calendar_vendor_context_default_statuses()
                    : array('published'),
            ))
            : array();

        $items = array();
        foreach ($events as $event) {
            $plan_id = absint($event['event_plan_id'] ?? 0);
            if ($plan_id <= 0) {
                continue;
            }
            $status = vms_vendor_portal_opportunity_status($vendor_id, $plan_id);
            if (empty($status['visible'])) {
                continue;
            }
            $event_date = (string) ($event['date_key'] ?? '');
            if ($event_date === '' || $event_date < $today) {
                continue;
            }
            $sort_rank = ($status['status'] ?? '') === 'open' ? 1 : ((($status['status'] ?? '') === 'pending') ? 2 : 3);
            $items[] = array('event' => $event, 'status' => $status, 'sort_rank' => $sort_rank);
        }

        $existing_plan_ids = array();
        foreach ($items as $existing_item) {
            $existing_plan_ids[] = absint($existing_item['event']['event_plan_id'] ?? 0);
        }
        $existing_plan_ids = array_values(array_unique(array_filter($existing_plan_ids)));

        $interest_plan_ids = function_exists('vms_vendor_portal_interest_event_ids')
            ? vms_vendor_portal_interest_event_ids($vendor_id)
            : array();
        foreach ($interest_plan_ids as $interest_plan_id) {
            $interest_plan_id = absint($interest_plan_id);
            if ($interest_plan_id <= 0 || in_array($interest_plan_id, $existing_plan_ids, true)) {
                continue;
            }
            $status = vms_vendor_portal_opportunity_status($vendor_id, $interest_plan_id);
            if (empty($status['visible'])) {
                continue;
            }
            $context = function_exists('vms_add_dispatch_get_event_plan_context')
                ? vms_add_dispatch_get_event_plan_context($interest_plan_id)
                : null;
            if (!is_array($context)) {
                continue;
            }
            $event_date = (string) ($context['event_date'] ?? '');
            if ($event_date === '' || $event_date < $today) {
                continue;
            }
            $items[] = array(
                'event' => array(
                    'event_plan_id' => $interest_plan_id,
                    'date_key' => $event_date,
                    'venue_name' => (string) ($context['venue_name'] ?? ''),
                    'title' => (string) ($context['event_title'] ?? get_the_title($interest_plan_id)),
                    'start_local' => '',
                ),
                'status' => $status,
                'sort_rank' => ($status['status'] ?? '') === 'open' ? 1 : ((($status['status'] ?? '') === 'pending') ? 2 : 3),
            );
            $existing_plan_ids[] = $interest_plan_id;
        }

        usort($items, static function (array $a, array $b): int {
            $date_cmp = strcmp((string) (($a['event']['date_key'] ?? '')), (string) (($b['event']['date_key'] ?? '')));
            if ($date_cmp !== 0) {
                return $date_cmp;
            }
            return strcmp((string) (($a['event']['title'] ?? '')), (string) (($b['event']['title'] ?? '')));
        });

        if (empty($items)) {
            echo '<p class="vms-muted vms-m0">' . esc_html__('No upcoming opportunities are available for your vendor type right now.', 'backstage-venue-manager') . '</p>';
            echo '</div>';
            return;
        }

        echo '<div class="vms-vio-opps-list">';
        foreach ($items as $item) {
            $event = (array) ($item['event'] ?? array());
            $status = (array) ($item['status'] ?? array());
            $plan_id = absint($event['event_plan_id'] ?? 0);
            $date_key = (string) ($event['date_key'] ?? '');
            $venue_name = trim((string) ($event['venue_name'] ?? ''));
            $title = trim((string) ($event['title'] ?? ''));
            $status_key = sanitize_html_class((string) ($status['status'] ?? 'booked'));
            $status_label = (string) ($status['label'] ?? __('Booked', 'backstage-venue-manager'));
            $time_label = '';
            $start_local = isset($event['start_local']) ? (string) $event['start_local'] : '';
            if ($start_local !== '') {
                try {
                    $dt = new DateTimeImmutable($start_local);
                    $time_label = $dt->format('g:ia');
                } catch (Exception $e) {
                    $time_label = '';
                }
            }
            $pretty_date = $date_key !== ''
                ? (function_exists('bvmgr_format_local_ymd') ? bvmgr_format_local_ymd($date_key, 'D, M j, Y') : $date_key)
                : '';
            echo '<article class="vms-vio-opps-item">';
            echo '<div class="vms-vio-opps-item__meta">';
            echo '<div class="vms-vio-opps-item__date">' . esc_html($pretty_date) . '</div>';
            if ($time_label !== '') {
                echo '<div class="vms-vio-opps-item__venue">' . esc_html($time_label) . '</div>';
            }
            if ($venue_name !== '') {
                echo '<div class="vms-vio-opps-item__venue">' . esc_html($venue_name) . '</div>';
            }
            echo '</div>';
            echo '<div class="vms-vio-opps-item__body">';
            echo '<h4 class="vms-vio-opps-item__title">' . esc_html($title !== '' ? $title : __('Event', 'backstage-venue-manager')) . '</h4>';
            echo '<div class="vms-vio-opps-item__actions">';
            echo '<span class="vms-vio-opps-status vms-vio-opps-status--' . esc_attr($status_key) . '">' . esc_html($status_label) . '</span>';
            $submitted_at = trim((string) ($status['submitted_at'] ?? ''));
            if ($submitted_at !== '') {
                echo '<span class="vms-vio-opps-submitted">' . esc_html__('Submitted:', 'backstage-venue-manager') . ' ' . esc_html($submitted_at) . '</span>';
            }
            if (!empty($status['can_submit']) && $plan_id > 0) {
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
                echo '<input type="hidden" name="action" value="vms_vendor_portal_interest_submit">';
                echo '<input type="hidden" name="vendor_id" value="' . esc_attr((string) $vendor_id) . '">';
                echo '<input type="hidden" name="event_plan_id" value="' . esc_attr((string) $plan_id) . '">';
                echo '<input type="hidden" name="return_url" value="' . esc_attr(add_query_arg(array('vendor_id' => $vendor_id, 'tab' => 'availability'), (string) ($portal_context['base_url'] ?? get_permalink()))) . '">';
                wp_nonce_field('vms_vendor_portal_interest_submit', '_vms_vendor_interest_nonce');
                echo '<button type="submit" class="button button-primary">' . esc_html__('Apply', 'backstage-venue-manager') . '</button>';
                echo '</form>';
            }
            if (!empty($status['can_withdraw']) && $plan_id > 0) {
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
                echo '<input type="hidden" name="action" value="vms_vendor_portal_interest_withdraw">';
                echo '<input type="hidden" name="vendor_id" value="' . esc_attr((string) $vendor_id) . '">';
                echo '<input type="hidden" name="event_plan_id" value="' . esc_attr((string) $plan_id) . '">';
                echo '<input type="hidden" name="return_url" value="' . esc_attr(add_query_arg(array('vendor_id' => $vendor_id, 'tab' => 'availability'), (string) ($portal_context['base_url'] ?? get_permalink()))) . '">';
                wp_nonce_field('vms_vendor_portal_interest_withdraw', '_vms_vendor_withdraw_nonce');
                echo '<button type="submit" class="button">' . esc_html__('Withdraw', 'backstage-venue-manager') . '</button>';
                echo '</form>';
            }
            echo '</div>';
            echo '</div>';
            echo '</article>';
        }
        echo '</div>';
        echo '</div>';
    }
}

if (!function_exists('vms_vendor_portal_handle_interest_submit')) {
    function vms_vendor_portal_handle_interest_submit(): void
    {
        if (!is_user_logged_in()) {
            wp_die(esc_html__('Please log in to submit interest.', 'backstage-venue-manager'));
        }
        $nonce = (isset($_REQUEST['_vms_vendor_interest_nonce']) && !is_array($_REQUEST['_vms_vendor_interest_nonce']))
            ? sanitize_text_field(wp_unslash((string) $_REQUEST['_vms_vendor_interest_nonce']))
            : '';
        if (!wp_verify_nonce($nonce, 'vms_vendor_portal_interest_submit')) {
            wp_die(esc_html__('Security check failed.', 'backstage-venue-manager'));
        }

        $user_id = get_current_user_id();
        $vendor_id = bvmgr_request_read_absint($_REQUEST, 'vendor_id');
        $event_plan_id = bvmgr_request_read_absint($_REQUEST, 'event_plan_id');
        $return_url = vms_vendor_portal_post_return_url(home_url('/vendor-portal/?tab=availability'));

        $can_access = false;
        if (function_exists('vms_user_can_access_vendor')) {
            $can_access = vms_user_can_access_vendor($user_id, $vendor_id);
        }
        if (!$can_access) {
            vms_vendor_portal_set_flash($user_id, array('type' => 'error', 'message' => __('You do not have permission to submit interest for that vendor.', 'backstage-venue-manager')));
            wp_safe_redirect($return_url);
            exit;
        }

        $result = function_exists('vms_add_dispatch_create_portal_interest')
            ? vms_add_dispatch_create_portal_interest($event_plan_id, $vendor_id)
            : new WP_Error('add_dispatch_unavailable', __('ADD is not available in this build.', 'backstage-venue-manager'));

        if (is_wp_error($result)) {
            vms_vendor_portal_set_flash($user_id, array('type' => 'error', 'message' => $result->get_error_message()));
        } else {
            vms_vendor_portal_set_flash($user_id, array(
                'type' => 'success',
                'message' => !empty($result['already_exists'])
                    ? __('Your interest was already on file for this date.', 'backstage-venue-manager')
                    : __('Your interest has been sent to the venue.', 'backstage-venue-manager'),
            ));
        }
        wp_safe_redirect($return_url);
        exit;
    }
}
add_action('admin_post_vms_vendor_portal_interest_submit', 'vms_vendor_portal_handle_interest_submit');

if (!function_exists('vms_vendor_portal_handle_interest_withdraw')) {
    function vms_vendor_portal_handle_interest_withdraw(): void
    {
        if (!is_user_logged_in()) {
            wp_die(esc_html__('Please log in to manage your interest.', 'backstage-venue-manager'));
        }
        $nonce = (isset($_REQUEST['_vms_vendor_withdraw_nonce']) && !is_array($_REQUEST['_vms_vendor_withdraw_nonce']))
            ? sanitize_text_field(wp_unslash((string) $_REQUEST['_vms_vendor_withdraw_nonce']))
            : '';
        if (!wp_verify_nonce($nonce, 'vms_vendor_portal_interest_withdraw')) {
            wp_die(esc_html__('Security check failed.', 'backstage-venue-manager'));
        }

        $user_id = get_current_user_id();
        $vendor_id = bvmgr_request_read_absint($_REQUEST, 'vendor_id');
        $event_plan_id = bvmgr_request_read_absint($_REQUEST, 'event_plan_id');
        $return_url = vms_vendor_portal_post_return_url(home_url('/vendor-portal/?tab=availability'));

        $can_access = false;
        if (function_exists('vms_user_can_access_vendor')) {
            $can_access = vms_user_can_access_vendor($user_id, $vendor_id);
        }
        if (!$can_access) {
            vms_vendor_portal_set_flash($user_id, array('type' => 'error', 'message' => __('You do not have permission to update that request.', 'backstage-venue-manager')));
            wp_safe_redirect($return_url);
            exit;
        }

        $result = function_exists('vms_add_dispatch_withdraw_portal_interest')
            ? vms_add_dispatch_withdraw_portal_interest($event_plan_id, $vendor_id)
            : new WP_Error('add_dispatch_unavailable', __('This request could not be updated right now.', 'backstage-venue-manager'));

        if (is_wp_error($result)) {
            vms_vendor_portal_set_flash($user_id, array('type' => 'error', 'message' => $result->get_error_message()));
        } else {
            vms_vendor_portal_set_flash($user_id, array(
                'type' => 'success',
                'message' => __('Your request has been withdrawn.', 'backstage-venue-manager'),
            ));
        }
        wp_safe_redirect($return_url);
        exit;
    }
}
add_action('admin_post_vms_vendor_portal_interest_withdraw', 'vms_vendor_portal_handle_interest_withdraw');

if (!function_exists('vms_vendor_portal_handle_link_request_submit')) {
    function vms_vendor_portal_handle_link_request_submit(): void
    {
        if (!is_user_logged_in()) {
            wp_die(esc_html__('Please log in to request vendor portal access.', 'backstage-venue-manager'));
        }

        $nonce = (isset($_REQUEST['_vms_vendor_link_request_nonce']) && !is_array($_REQUEST['_vms_vendor_link_request_nonce']))
            ? sanitize_text_field(wp_unslash((string) $_REQUEST['_vms_vendor_link_request_nonce']))
            : '';
        if (!wp_verify_nonce($nonce, 'vms_vendor_portal_link_request_submit')) {
            wp_die(esc_html__('Security check failed.', 'backstage-venue-manager'));
        }

        $user_id = get_current_user_id();
        $vendor_id = bvmgr_request_read_absint($_REQUEST, 'vendor_id');
        $return_url = vms_vendor_portal_post_return_url(home_url('/vendor-portal/'));
        $user = get_userdata($user_id);
        $user_email = $user instanceof WP_User ? sanitize_email((string) $user->user_email) : '';
        $matched_vendor_ids = function_exists('vms_vendor_user_link_find_vendor_matches_for_email')
            ? (array) vms_vendor_user_link_find_vendor_matches_for_email($user_email)
            : array();

        if ($vendor_id <= 0 || !in_array($vendor_id, $matched_vendor_ids, true)) {
            vms_vendor_portal_set_flash($user_id, array(
                'type' => 'error',
                'message' => __('That vendor profile is not available for self-service linking from this account.', 'backstage-venue-manager'),
            ));
            wp_safe_redirect($return_url);
            exit;
        }

        if (function_exists('vms_user_can_access_vendor') && vms_user_can_access_vendor($user_id, $vendor_id)) {
            vms_vendor_portal_set_flash($user_id, array(
                'type' => 'success',
                'message' => __('Your account is already linked to that vendor profile.', 'backstage-venue-manager'),
            ));
            wp_safe_redirect($return_url);
            exit;
        }

        $created = function_exists('vms_vendor_user_link_store_request')
            ? vms_vendor_user_link_store_request($user_id, $vendor_id, array('source' => 'vendor_portal'))
            : false;

        if ($created) {
            do_action('vms_vendor_user_link_requested', array(
                'vendor_id' => $vendor_id,
                'user_id' => $user_id,
                'requested_at_gmt' => current_time('mysql', true),
                'source' => 'vendor_portal',
            ));

            vms_vendor_portal_set_flash($user_id, array(
                'type' => 'success',
                'message' => __('Your portal access request has been sent to the venue.', 'backstage-venue-manager'),
            ));
        } else {
            vms_vendor_portal_set_flash($user_id, array(
                'type' => 'success',
                'message' => __('That portal access request is already pending review.', 'backstage-venue-manager'),
            ));
        }

        wp_safe_redirect($return_url);
        exit;
    }
}
add_action('admin_post_vms_vendor_portal_link_request', 'vms_vendor_portal_handle_link_request_submit');

if (!function_exists('vms_vendor_portal_render_link_request_panel')) {
    function vms_vendor_portal_render_link_request_panel(int $user_id, string $base_url = ''): string
    {
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return '<p>' . esc_html__('Your account is not linked to a vendor profile yet. Please contact the venue admin.', 'backstage-venue-manager') . '</p>';
        }

        $user = get_userdata($user_id);
        $user_email = $user instanceof WP_User ? sanitize_email((string) $user->user_email) : '';
        $matched_vendor_ids = ($user_email !== '' && function_exists('vms_vendor_user_link_find_vendor_matches_for_email'))
            ? (array) vms_vendor_user_link_find_vendor_matches_for_email($user_email)
            : array();
        $pending_requests = function_exists('vms_vendor_user_link_get_requests_for_user')
            ? (array) vms_vendor_user_link_get_requests_for_user($user_id)
            : array();

        $candidate_vendor_ids = array();
        foreach (array_merge($matched_vendor_ids, array_map('absint', array_keys($pending_requests))) as $candidate_vendor_id) {
            $candidate_vendor_id = (int) $candidate_vendor_id;
            if ($candidate_vendor_id > 0 && !in_array($candidate_vendor_id, $candidate_vendor_ids, true)) {
                $candidate_vendor_ids[] = $candidate_vendor_id;
            }
        }

        $flash = vms_vendor_portal_pull_flash($user_id);
        $return_url = $base_url !== '' ? $base_url : home_url('/vendor-portal/');

        ob_start();
        if (!empty($flash['message'])) {
            echo wp_kses_post(bvmgr_portal_notice(!empty($flash['type']) ? (string) $flash['type'] : 'success', (string) $flash['message']));
        }

        echo '<div class="vms-portal-auth-wrap">';
        echo '<div class="vms-portal-auth-col vms-portal-auth-apply">';
        echo '<h2>' . esc_html__('Vendor profile link needed', 'backstage-venue-manager') . '</h2>';
        echo '<p>' . esc_html__('Your website account is active, but it is not linked to a vendor profile yet.', 'backstage-venue-manager') . '</p>';
        if ($user_email !== '') {
            echo '<p><strong>' . esc_html__('Signed in as:', 'backstage-venue-manager') . '</strong> ' . esc_html($user_email) . '</p>';
        }

        if (empty($candidate_vendor_ids)) {
            echo wp_kses_post(bvmgr_portal_notice('warning', __('We could not find a vendor profile using this website account email yet.', 'backstage-venue-manager')));
            echo '<p>' . esc_html__('If your vendor profile uses a different email address, please contact the venue so they can connect the correct record manually.', 'backstage-venue-manager') . '</p>';
        } else {
            echo '<p>' . esc_html__('We found the following vendor profile matches for this account. Send a request for the one that should be linked to you.', 'backstage-venue-manager') . '</p>';
            foreach ($candidate_vendor_ids as $candidate_vendor_id) {
                $vendor = get_post($candidate_vendor_id);
                if (!$vendor || $vendor->post_type !== 'vms_vendor') {
                    continue;
                }

                $vendor_title = get_the_title($candidate_vendor_id);
                if (!is_string($vendor_title) || $vendor_title === '') {
                    /* translators: %d: vendor post ID. */
                    $vendor_title = sprintf(__('Vendor #%d', 'backstage-venue-manager'), $candidate_vendor_id);
                }

                $vendor_type = function_exists('vms_vendor_user_link_vendor_type_label')
                    ? (string) vms_vendor_user_link_vendor_type_label($candidate_vendor_id)
                    : '';
                $pending_row = isset($pending_requests[$candidate_vendor_id]) && is_array($pending_requests[$candidate_vendor_id])
                    ? $pending_requests[$candidate_vendor_id]
                    : array();
                $is_pending = !empty($pending_row) && sanitize_key((string) ($pending_row['status'] ?? 'pending')) === 'pending';
                $requested_label = '';
                $requested_at_gmt = sanitize_text_field((string) ($pending_row['requested_at_gmt'] ?? ''));
                if ($requested_at_gmt !== '') {
                    $requested_ts = strtotime($requested_at_gmt . ' GMT');
                    if ($requested_ts) {
                        $requested_label = wp_date(get_option('date_format', 'F j, Y') . ' ' . get_option('time_format', 'g:i a'), (int) $requested_ts);
                    }
                }

                echo '<div class="vms-card">';
                echo '<div><strong>' . esc_html(wp_specialchars_decode($vendor_title, ENT_QUOTES)) . '</strong></div>';
                if ($vendor_type !== '') {
                    echo '<div class="description">' . esc_html($vendor_type) . '</div>';
                }

                if ($is_pending) {
                    $pending_message = $requested_label !== ''
                        ? sprintf(
                            /* translators: %s: date and time when the vendor access request was sent. */
                            __('Request sent on %s. The venue still needs to review it.', 'backstage-venue-manager'),
                            $requested_label
                        )
                        : __('Your request is already pending review.', 'backstage-venue-manager');
                    echo wp_kses_post(bvmgr_portal_notice('warning', $pending_message));
                } else {
                    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
                    echo '<input type="hidden" name="action" value="vms_vendor_portal_link_request">';
                    echo '<input type="hidden" name="vendor_id" value="' . esc_attr((string) $candidate_vendor_id) . '">';
                    echo '<input type="hidden" name="return_url" value="' . esc_attr($return_url) . '">';
                    echo wp_kses(
                        wp_nonce_field('vms_vendor_portal_link_request_submit', '_vms_vendor_link_request_nonce', true, false),
                        array(
                            'input' => array(
                                'id' => true,
                                'name' => true,
                                'type' => true,
                                'value' => true,
                            ),
                        )
                    );
                    echo '<button type="submit" class="button button-primary">' . esc_html__('Request portal access', 'backstage-venue-manager') . '</button>';
                    echo '</form>';
                }
                echo '</div>';
            }
        }

        echo '</div>';
        echo '</div>';

        return (string) ob_get_clean();
    }
}

function vms_vendor_portal_shortcode($atts = []): string
{
    ob_start();

    // Enqueue portal assets when the shortcode renders.
    // This avoids fragile has_shortcode() detection in themes/page builders.
    if (function_exists('wp_enqueue_style')) {
        wp_enqueue_style('vms-portal');
    }
    if (function_exists('wp_enqueue_script')) {
        $calendar_script_ver = function_exists('bvmgr_asset_version') ? bvmgr_asset_version() : (defined('BVMGR_VERSION') ? (string) BVMGR_VERSION : null);
        if (defined('BVMGR_PLUGIN_PATH')) {
            $calendar_script_file = BVMGR_PLUGIN_PATH . 'assets/js/vms-public-calendar.js';
            if (file_exists($calendar_script_file)) {
                $calendar_script_ver = (string) @filemtime($calendar_script_file);
            }
        }
        wp_enqueue_script(
            'vms-public-calendar',
            BVMGR_PLUGIN_URL . 'assets/js/vms-public-calendar.js',
            array(),
            $calendar_script_ver,
            true
        );
        $portal_script_src = function_exists('bvmgr_asset_url')
            ? bvmgr_asset_url('assets/js/vms-vendor-portal.js')
            : BVMGR_PLUGIN_URL . 'assets/js/vms-vendor-portal.js';
        $portal_script_ver = function_exists('bvmgr_asset_version_for')
            ? bvmgr_asset_version_for('assets/js/vms-vendor-portal.js')
            : (function_exists('bvmgr_asset_version') ? bvmgr_asset_version() : (defined('BVMGR_VERSION') ? (string) BVMGR_VERSION : ''));
        wp_enqueue_script('vms-vendor-portal', $portal_script_src, array(), $portal_script_ver, true);
    }

    $base_url = get_permalink(); // page where shortcode lives

    // Logged-out view
    if (!is_user_logged_in()) {
        $apply_url = function_exists('vms_vendor_portal_application_page_url')
            ? vms_vendor_portal_application_page_url()
            : site_url('/vendor-application/');
        $login_redirect_url = function_exists('vms_vendor_portal_login_redirect_url')
            ? vms_vendor_portal_login_redirect_url(true)
            : add_query_arg('vms_vendor_portal_login', '1', get_permalink());

?>
        <div class="vms-portal vms-portal-auth-shell" id="vms-portal-root">
            <div class="vms-portal-auth-header">
                <span class="vms-portal-auth-kicker"><?php echo esc_html__('Vendor Portal', 'backstage-venue-manager'); ?></span>
                <h1><?php echo esc_html__('Choose Your Vendor Path', 'backstage-venue-manager'); ?></h1>
                <p><?php echo esc_html__('Already-approved vendors can sign in below. New vendors should apply for access first and wait for manual review before portal tools become available.', 'backstage-venue-manager'); ?></p>
            </div>

            <div class="vms-portal-auth-wrap">
                <section class="vms-portal-auth-col vms-portal-auth-login">
                    <span class="vms-portal-auth-eyebrow"><?php echo esc_html__('Already approved', 'backstage-venue-manager'); ?></span>
                    <h2><?php echo esc_html__('Approved Vendor Login', 'backstage-venue-manager'); ?></h2>
                    <p class="vms-portal-auth-copy"><?php echo esc_html__('Use this login only if your vendor has already been approved and linked to a website account.', 'backstage-venue-manager'); ?></p>
                    <?php
                    echo wp_login_form(array(
                        'echo'     => false,
                        'redirect' => esc_url($login_redirect_url),
                    ));
                    ?>
                    <p class="vms-portal-auth-link-row">
                        <a href="<?php echo esc_url(wp_lostpassword_url($login_redirect_url)); ?>"><?php echo esc_html__('Forgot password or need a reset?', 'backstage-venue-manager'); ?></a>
                    </p>
                    <p class="vms-portal-auth-hint"><?php echo esc_html__('Vendor tools live in the Vendor Portal. If you sign into WooCommerce My Account, you may still see customer and ticket information there as well.', 'backstage-venue-manager'); ?></p>
                </section>

                <section class="vms-portal-auth-col vms-portal-auth-apply">
                    <span class="vms-portal-auth-eyebrow"><?php echo esc_html__('New vendor', 'backstage-venue-manager'); ?></span>
                    <h2><?php echo esc_html__('Apply for Vendor Access', 'backstage-venue-manager'); ?></h2>
                    <p class="vms-portal-auth-copy"><?php echo esc_html__('Start here if you are not approved yet. Applications are reviewed by an operator and approval is not instant.', 'backstage-venue-manager'); ?></p>
                    <ul class="vms-portal-auth-list">
                        <li><?php echo esc_html__('Complete the vendor application form.', 'backstage-venue-manager'); ?></li>
                        <li><?php echo esc_html__('Wait for an approval email with next-step instructions.', 'backstage-venue-manager'); ?></li>
                        <li><?php echo esc_html__('Use the Vendor Portal after you are approved.', 'backstage-venue-manager'); ?></li>
                    </ul>
                    <p>
                        <a class="button button-primary" href="<?php echo esc_url($apply_url); ?>">
                            <?php echo esc_html__('Apply for Vendor Access', 'backstage-venue-manager'); ?>
                        </a>
                    </p>
                    <p class="vms-portal-auth-hint"><?php echo esc_html__('Already applied? Watch your email, including spam or junk folders, for the review outcome and portal guidance.', 'backstage-venue-manager'); ?></p>
                </section>
            </div>
        </div>
    <?php
        return (string) ob_get_clean();
    }

    // Logged-in: resolve vendor context (many-to-many)
    $user_id = get_current_user_id();

    $tab = vms_vendor_portal_get_requested_tab('dashboard');

    $requested_vendor_id = vms_vendor_portal_get_requested_vendor_id();
    $preview_vendor_id   = function_exists('vms_vendor_portal_get_preview_vendor_id')
        ? (int) vms_vendor_portal_get_preview_vendor_id()
        : 0;
    $is_preview          = ($preview_vendor_id > 0);

    $vendor_ids = array();
    $vendor_id  = 0;

    if ($is_preview) {
        $vendor_id = $preview_vendor_id;
        $vendor_ids = array($vendor_id);
    } else {
        if (function_exists('vms_get_active_vendor_ids_for_user')) {
            $vendor_ids = vms_get_active_vendor_ids_for_user($user_id);
        }

        // If a vendor_id was requested, honor it only if the user is linked to that vendor.
        if ($requested_vendor_id > 0) {
            $can = false;
            if (function_exists('vms_user_can_access_vendor')) {
                $can = vms_user_can_access_vendor($user_id, $requested_vendor_id);
            } else {
                $can = in_array($requested_vendor_id, $vendor_ids, true);
            }

            if ($can) {
                $vendor_id = $requested_vendor_id;

                // Persist as the user's primary vendor pointer (convenience for future portal loads).
                if (function_exists('vms_vendor_user_links_set_primary_for_user')) {
                    vms_vendor_user_links_set_primary_for_user($user_id, $vendor_id, $user_id);
                } else {
                    update_user_meta($user_id, '_vms_vendor_id', $vendor_id);
                }
            }
        }

        // Otherwise, fall back to the user's primary/default vendor.
        if ($vendor_id <= 0) {
            if (function_exists('vms_get_primary_vendor_id_for_user')) {
                $vendor_id = vms_get_primary_vendor_id_for_user($user_id);
            } else {
                $vendor_id = (int) get_user_meta($user_id, '_vms_vendor_id', true);
            }
        }
    }

    if ($vendor_id <= 0) {
        if (function_exists('vms_vendor_app_render_portal_applicant_panel')) {
            $applicant_panel = (string) vms_vendor_app_render_portal_applicant_panel($user_id, (string) $base_url);
            if ($applicant_panel !== '') {
                return $applicant_panel;
            }
        }
        return function_exists('vms_vendor_portal_render_link_request_panel')
            ? vms_vendor_portal_render_link_request_panel($user_id, (string) $base_url)
            : '<p>' . esc_html__('Your account is not linked to a vendor profile yet. Please contact the venue admin.', 'backstage-venue-manager') . '</p>';
    }

    // Ensure vendor exists
    $vendor = get_post($vendor_id);
    if (!$vendor || $vendor->post_type !== 'vms_vendor') {
        return '<p>' . esc_html__('Your linked vendor profile could not be found. Please contact the venue admin.', 'backstage-venue-manager') . '</p>';
    }

    // Ensure we have vendor_ids for the switcher even if the relationship table is not available.
    if (empty($vendor_ids)) {
        $vendor_ids = array((int) $vendor_id);
    }

    // Pre-build URLs
    $nav_base_args = $is_preview && function_exists('vms_vendor_portal_get_preview_query_args')
        ? (array) vms_vendor_portal_get_preview_query_args((int) $vendor_id)
        : array();

    $active_tab = ($tab === 'opportunities') ? 'availability' : $tab;

    $url_dashboard    = add_query_arg(array_merge($nav_base_args, array('tab' => 'dashboard')), $base_url);
    $url_profile      = add_query_arg(array_merge($nav_base_args, array('tab' => 'profile')), $base_url);
    $url_tax_profile  = add_query_arg(array_merge($nav_base_args, array('tab' => 'tax-profile')), $base_url);
    $url_history      = add_query_arg(array_merge($nav_base_args, array('tab' => 'history')), $base_url);
    $url_availability = add_query_arg(array_merge($nav_base_args, array('vendor_id' => $vendor_id, 'tab' => 'availability')), $base_url);
    $url_all_vendors  = add_query_arg(array_merge($nav_base_args, array('vendor_id' => $vendor_id, 'tab' => 'all-vendors')), $base_url);
    $url_tech         = add_query_arg(array_merge($nav_base_args, array('tab' => 'tech')), $base_url);
    $has_event_history = function_exists('vms_vendor_portal_vendor_has_event_history')
        ? vms_vendor_portal_vendor_has_event_history((int) $vendor_id)
        : false;
    $portal_context   = array(
        'base_url' => $base_url,
        'tab' => $active_tab,
        'vendor_id' => (int) $vendor_id,
        'vendor_ids' => array_values(array_unique(array_filter(array_map('absint', (array) $vendor_ids)))),
        'user_id' => (int) $user_id,
        'vendor_post' => $vendor,
        'is_preview' => (bool) $is_preview,
    );

    // Header + nav (shown on all tabs)
    // Fallback: if theme/builder skipped normal wp_head style output, print canonical CSS handles here.
    if (function_exists('wp_style_is') && !wp_style_is('vms-portal', 'done') && !wp_style_is('vms-portal', 'enqueued')) {
        if (function_exists('wp_enqueue_style')) {
            wp_enqueue_style('vms-portal');
        }
        if (function_exists('wp_print_styles')) {
            wp_print_styles(array('vms-shared', 'vms-ui', 'vms-portal'));
        }
    }

    $portal_classes = 'vms-portal';
    if (function_exists('wp_is_mobile') && wp_is_mobile()) {
        $portal_classes .= ' vms-portal--mobile';
    }
    echo '<div id="vms-portal-root" class="' . esc_attr($portal_classes) . '">';
    echo '<div class="vms-portal-header">';

    // Vendor switcher (only shown when the user manages multiple vendors)
    if (is_array($vendor_ids) && count($vendor_ids) > 1) {
        echo '<form method="get" class="vms-portal-vendor-switch">';
        echo '<input type="hidden" name="tab" value="' . esc_attr($active_tab) . '">';
        if ($is_preview && function_exists('vms_vendor_portal_render_preview_hidden_fields')) {
            vms_vendor_portal_render_preview_hidden_fields((int) $vendor_id);
        }
        echo '<label class="vms-portal-vendor-switch-label">' . esc_html__('Vendor', 'backstage-venue-manager') . '</label>';
        echo '<select name="vendor_id" class="vms-portal-vendor-switch-select" data-vms-portal-submit-on-change="1">';
        foreach ($vendor_ids as $vid) {
            $vid = (int) $vid;
            if ($vid <= 0) continue;

            $p = get_post($vid);
            if (!$p || $p->post_type !== 'vms_vendor') continue;

            echo '<option value="' . esc_attr((string) $vid) . '"';
            if ($vendor_id === $vid) {
                echo ' selected="selected"';
            }
            echo '>' . esc_html($p->post_title) . '</option>';
        }
        echo '</select>';
        echo '</form>';
    }

    echo '<h2>' . esc_html(function_exists('bvmgr_ui_text') ? bvmgr_ui_text('portal_title_prefix', __('Vendor Portal:', 'backstage-venue-manager')) : __('Vendor Portal:', 'backstage-venue-manager')) . ' ' . esc_html($vendor->post_title) . '</h2>';

    echo '<nav class="vms-portal-nav">';
    echo '<a class="' . ($active_tab === 'dashboard' ? 'is-active' : '') . '" href="' . esc_url($url_dashboard) . '">' . esc_html__('Dashboard', 'backstage-venue-manager') . '</a>';
    echo '<a class="' . ($active_tab === 'profile' ? 'is-active' : '') . '" href="' . esc_url($url_profile) . '">' . esc_html__('Profile', 'backstage-venue-manager') . '</a>';
    echo '<a class="' . ($active_tab === 'tax-profile' ? 'is-active' : '') . '" href="' . esc_url($url_tax_profile) . '">' . esc_html__('Tax Profile', 'backstage-venue-manager') . '</a>';
    if ($has_event_history) {
        echo '<a class="' . ($active_tab === 'history' ? 'is-active' : '') . '" href="' . esc_url($url_history) . '">' . esc_html__('Event History', 'backstage-venue-manager') . '</a>';
    }
    echo '<a class="' . ($active_tab === 'availability' ? 'is-active' : '') . '" href="' . esc_url($url_availability) . '">' . esc_html__('Availability', 'backstage-venue-manager') . '</a>';
    echo '<a class="' . ($active_tab === 'tech' ? 'is-active' : '') . '" href="' . esc_url($url_tech) . '">' . esc_html__('Tech Docs', 'backstage-venue-manager') . '</a>';
    do_action('vms_vendor_portal_nav_links', $active_tab, $portal_context);
    if (!$is_preview) {
        $apply_url = function_exists('vms_vendor_app_get_application_page_url')
            ? vms_vendor_app_get_application_page_url(array('vms_from_portal' => '1'))
            : home_url('/vendor-application/?vms_from_portal=1');
        echo '<a href="' . esc_url($apply_url) . '">' . esc_html__('Add a Business', 'backstage-venue-manager') . '</a>';
    }
    echo '</nav>';
    echo '</div>'; // header
    if ($is_preview) {
        $preview_back_url = admin_url('post.php?post=' . (int) $vendor_id . '&action=edit');
        $preview_exit_url = esc_url($base_url);

        echo '<div class="vms-portal-preview-banner">';
        echo '<div class="vms-portal-preview-banner__copy">';
        echo '<strong>' . esc_html__('Admin Preview Mode', 'backstage-venue-manager') . '</strong> ';
        echo esc_html__('You are previewing this vendor portal as an operator using the vendor’s real portal data.', 'backstage-venue-manager');
        echo '</div>';
        echo '<div class="vms-portal-preview-banner__actions">';
        echo '<a class="button button-secondary" href="' . esc_url($preview_back_url) . '">' . esc_html__('Back to Vendor', 'backstage-venue-manager') . '</a>';
        echo '<a class="button" href="' . esc_url($preview_exit_url) . '">' . esc_html__('Exit Preview', 'backstage-venue-manager') . '</a>';
        echo '</div>';
        echo '</div>';
    }
    echo '<div class="vms-portal-body">';

    $flash = vms_vendor_portal_pull_flash($user_id);
    if (!empty($flash['message'])) {
        echo wp_kses_post(bvmgr_portal_notice(!empty($flash['type']) ? (string) $flash['type'] : 'success', (string) $flash['message']));
    }

    // Route
    if ($tab === 'dashboard') {

        $tz   = wp_timezone();
        $now  = (int) current_time('timestamp');
        $today = wp_date('Y-m-d', $now, $tz);

        // ------------------------------------------------------
        // Upcoming bookings (Event Plans)
        // ------------------------------------------------------
        $k_band_vendor_id = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'band_vendor_id') : '_vms_band_vendor_id';
        $k_secondary_vendor_id = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'secondary_vendor_id') : '_vms_secondary_vendor_id';
        $k_lineup_vendor_id = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'lineup_entry_vendor_id') : '_vms_lineup_entry_vendor_id';

        $upcoming = get_posts(array(
            'post_type'      => 'vms_event_plan',
            'post_status'    => array('publish', 'draft', 'pending', 'private'),
            'posts_per_page' => 5,
            'orderby'        => 'meta_value',
            'meta_key'       => '_vms_event_date', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- The portal dashboard intentionally orders its five upcoming bookings by canonical Event Plan date metadata.
            'order'          => 'ASC',
            'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Upcoming bookings require canonical date plus primary, secondary, or lineup vendor metadata; no equivalent indexed relationship exists.
                'relation' => 'AND',
                array(
                    'key'     => '_vms_event_date',
                    'value'   => $today,
                    'compare' => '>=',
                    'type'    => 'DATE',
                ),
                array(
                    'relation' => 'OR',
                    array(
                        'key'     => $k_band_vendor_id,
                        'value'   => (int) $vendor_id,
                        'compare' => '=',
                        'type'    => 'NUMERIC',
                    ),
                    array(
                        'key'     => $k_secondary_vendor_id,
                        'value'   => (int) $vendor_id,
                        'compare' => '=',
                        'type'    => 'NUMERIC',
                    ),
                    array(
                        'key'     => $k_lineup_vendor_id,
                        'value'   => (int) $vendor_id,
                        'compare' => '=',
                        'type'    => 'NUMERIC',
                    ),
                ),
            ),
        ));

        $next_booking = !empty($upcoming) ? $upcoming[0] : null;

        // ------------------------------------------------------
        // Availability setup status
        // ------------------------------------------------------
        $ics_url      = (string) get_post_meta($vendor_id, '_vms_ics_url', true);
        $ics_autosync = (int) get_post_meta($vendor_id, '_vms_ics_autosync', true);
        $ics_last     = (int) get_post_meta($vendor_id, '_vms_ics_last_sync', true);

        $ics_enabled  = !empty($ics_url);
        $ics_last_h   = $ics_last ? wp_date('M j, Y g:ia', $ics_last, $tz) : '';
        $ics_stale_days = ($ics_enabled && $ics_last) ? (int) floor(($now - $ics_last) / DAY_IN_SECONDS) : 0;

        // Pattern meta (assumes you added these keys)
        $pattern_enabled = (int) get_post_meta($vendor_id, '_vms_pattern_enabled', true);
        $pattern_days    = get_post_meta($vendor_id, '_vms_pattern_days', true);
        if (!is_array($pattern_days)) $pattern_days = array();
        $pattern_days = array_values(array_unique(array_filter(array_map('intval', $pattern_days), function ($d) {
            return $d >= 0 && $d <= 6;
        })));
        sort($pattern_days);
        if (empty($pattern_days)) $pattern_enabled = 0;

        $dow_labels = array('Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat');
        $pattern_label = 'Off';
        if ($pattern_enabled) {
            $picked = array();
            foreach ($pattern_days as $d) {
                if (isset($dow_labels[$d])) $picked[] = $dow_labels[$d];
            }
            $pattern_label = 'On' . (!empty($picked) ? ' | ' . implode(', ', $picked) : '');
        }

        // Manual overrides count
        $manual = get_post_meta($vendor_id, '_vms_availability_manual', true);
        if (!is_array($manual)) $manual = array();

        

        // Used to lock past dates (view-only)
        $today_date = wp_date('Y-m-d', time(), wp_timezone());
$manual_future = 0;
        foreach ($manual as $d => $state) {
            if (!is_string($d)) continue;
            if ($d >= $today) $manual_future++;
        }

        // ------------------------------------------------------
        // Action Needed signals
        // ------------------------------------------------------
        $needs_av_setup = (!$ics_enabled && !$pattern_enabled && empty($manual));
        $needs_ics_sync = ($ics_enabled && $ics_last && $ics_stale_days >= 7);

        if (function_exists('vms_vendor_portal_render_bonus_progress_section')) {
            vms_vendor_portal_render_bonus_progress_section((int) $vendor_id, 'dashboard');
        }
        if (function_exists('vms_vendor_portal_render_secondary_sales_snapshot_section')) {
            vms_vendor_portal_render_secondary_sales_snapshot_section((int) $vendor_id, 'dashboard');
        }
        echo '<div class="vms-dash-grid">';

        // LEFT: Bookings
        echo '<div>';

        echo '<div class="vms-portal-card">';
        echo '<h3>' . esc_html__('Next Booking', 'backstage-venue-manager') . '</h3>';

        if ($next_booking) {
            $d = (string) get_post_meta($next_booking->ID, '_vms_event_date', true);
            $t = (string) get_post_meta($next_booking->ID, '_vms_start_time', true);

            $time_label = '';
            if ($d && $t) {
                $time_label = '';
                if ($d && $t) {
                    try {
                        $dt = new DateTimeImmutable(trim($d . ' ' . $t), $tz);
                        $time_label = $dt->format('g:ia');
                    } catch (Exception $e) {
                        $time_label = '';
                    }
                }
            }

            $date_label = $d !== ''
                ? (function_exists('bvmgr_format_local_ymd') ? bvmgr_format_local_ymd($d, 'D, M j, Y') : $d)
                : '';
            echo '<div class="vms-portal-hero-value">' . esc_html($date_label) . '</div>';
            if ($time_label) {
                echo '<div class="vms-muted vms-mt-2">' . esc_html__('Set time:', 'backstage-venue-manager') . ' ' . esc_html($time_label) . '</div>';
            }
            echo '<div class="vms-muted vms-mt-8">' . esc_html__('Event Plan:', 'backstage-venue-manager') . ' ' . esc_html(get_the_title($next_booking)) . '</div>';
        } else {
            echo '<p class="vms-muted vms-m0">' . esc_html__('No upcoming bookings found yet.', 'backstage-venue-manager') . '</p>';
        }

        echo '</div>';

        if (function_exists('vms_vendor_portal_render_headliner_promo_video_card')) {
            vms_vendor_portal_render_headliner_promo_video_card((int) $vendor_id, (int) $user_id, (string) $base_url);
        }

        echo '<div class="vms-portal-card">';
        echo '<h3>' . esc_html__('Upcoming Bookings', 'backstage-venue-manager') . '</h3>';

        if (!empty($upcoming)) {
            echo '<ul class="vms-dash-list">';
            foreach ($upcoming as $p) {
                $d = (string) get_post_meta($p->ID, '_vms_event_date', true);
                $t = (string) get_post_meta($p->ID, '_vms_start_time', true);

                $date_label = $d !== ''
                    ? (function_exists('bvmgr_format_local_ymd') ? bvmgr_format_local_ymd($d, 'M j') : $d)
                    : '';
                $time_label = '';
                if ($d && $t) {
                    $time_label = '';
                    if ($d && $t) {
                        try {
                            $dt = new DateTimeImmutable(trim($d . ' ' . $t), $tz);
                            $time_label = $dt->format('g:ia');
                        } catch (Exception $e) {
                            $time_label = '';
                        }
                    }
                }

                $line = trim($date_label . ($time_label ? ' @ ' . $time_label : ''));
                echo '<li><strong>' . esc_html($line) . '</strong> <span class="vms-muted">— ' . esc_html(get_the_title($p)) . '</span></li>';
            }
            echo '</ul>';
        } else {
            echo '<p class="vms-muted vms-m0">' . esc_html__('Nothing scheduled yet.', 'backstage-venue-manager') . '</p>';
        }

        echo '<div class="vms-dash-actions">';
        echo '<a class="button button-primary" href="' . esc_url($url_availability) . '">' . esc_html__('Update Availability', 'backstage-venue-manager') . '</a>';
        echo '<a class="button" href="' . esc_url($url_profile) . '">' . esc_html__('Edit Profile', 'backstage-venue-manager') . '</a>';
        echo '</div>';

        echo '</div>'; // bookings card

        echo '</div>'; // left col

        // RIGHT: Status + Actions needed
        echo '<div>';

        echo '<div class="vms-portal-card">';
        echo '<h3>' . esc_html__('Availability Setup', 'backstage-venue-manager') . '</h3>';

        echo '<div class="vms-dash-kpis">';
        echo '<div class="vms-dash-kpi"><b>' . esc_html__('Manual', 'backstage-venue-manager') . '</b><span>' . esc_html($manual_future) . '</span> <span class="vms-muted">' . esc_html__('future overrides', 'backstage-venue-manager') . '</span></div>';

        $ics_label = $ics_enabled ? 'On' : 'Off';
        if ($ics_enabled && $ics_last_h) $ics_label .= ' | ' . $ics_last_h;
        echo '<div class="vms-dash-kpi"><b>' . esc_html__('ICS', 'backstage-venue-manager') . '</b><span>' . esc_html($ics_label) . '</span></div>';

        echo '<div class="vms-dash-kpi"><b>' . esc_html__('Pattern', 'backstage-venue-manager') . '</b><span>' . esc_html($pattern_label) . '</span></div>';
        echo '</div>';

        echo '<div class="vms-muted vms-mt-10">';
        echo esc_html__('Priority order:', 'backstage-venue-manager') . ' ';
        echo esc_html__('Manual overrides Pattern, and Pattern overrides ICS.', 'backstage-venue-manager');
        echo '</div>';

        echo '</div>';

        echo '<div class="vms-portal-card">';
        echo '<h3>' . esc_html__('Action Needed', 'backstage-venue-manager') . '</h3>';

        $any_actions = false;

        if ($needs_av_setup) {
            $any_actions = true;
            echo wp_kses_post(bvmgr_portal_notice('warning', __('You have not set up availability yet. Enable Pattern, connect ICS, or set a few manual dates.', 'backstage-venue-manager')));
        }

        if ($needs_ics_sync) {
            $any_actions = true;
            /* translators: %d: number of days since the vendor ICS calendar was last synced. */
            echo wp_kses_post(bvmgr_portal_notice('warning', sprintf(__('Your calendar sync is %d days old. Open Availability and tap “Sync Now”.', 'backstage-venue-manager'), $ics_stale_days)));
        }

        if (!$any_actions) {
            echo '<p class="vms-muted vms-m0">' . esc_html__('You’re all set.', 'backstage-venue-manager') . '</p>';
        }

        echo '<div class="vms-dash-actions">';
        echo '<a class="button" href="' . esc_url($url_availability) . '#ics">' . esc_html__('Calendar Sync (ICS)', 'backstage-venue-manager') . '</a>';
        echo '<a class="button" href="' . esc_url($url_tax_profile) . '">' . esc_html__('Tax Profile', 'backstage-venue-manager') . '</a>';
        echo '</div>';

        echo '</div>';

        echo '</div>'; // right col

        echo '</div>'; // grid

        /**
         * Stable dashboard extension point for premium/companion add-ons.
         *
         * Add-ons should render self-contained cards/sections only; do not open
         * forms here because the vendor portal dashboard may contain its own
         * future action controls.
         *
         *  array $portal_context Current vendor portal context.
         */
        do_action('vms_vendor_portal_dashboard_after_cards', $portal_context);

    } elseif ($tab === 'history') {
        if (function_exists('vms_vendor_portal_render_event_history_tab')) {
            vms_vendor_portal_render_event_history_tab($vendor_id);
        } else {
            echo wp_kses_post(bvmgr_portal_notice('error', __('Event History is not available right now.', 'backstage-venue-manager')));
        }
    } elseif ($tab === 'profile') {
        if (function_exists('vms_vendor_portal_render_profile')) {
            vms_vendor_portal_render_profile($vendor_id);
        } else {
            echo wp_kses_post(bvmgr_portal_notice('error', __('Profile module is not loaded.', 'backstage-venue-manager')));
        }
    } elseif ($tab === 'tax-profile') {
        if (function_exists('vms_vendor_portal_render_tax_profile')) {
            vms_vendor_portal_render_tax_profile($vendor_id);
        } else {
            echo wp_kses_post(bvmgr_portal_notice('error', __('Tax Profile module is not loaded.', 'backstage-venue-manager')));
        }
    } elseif ($tab === 'availability' || $tab === 'opportunities') {
        $venue_id = vms_vendor_guess_venue_id($vendor_id);
        $active_dates = vms_vendor_get_active_dates_or_rolling_window(12, $venue_id); // season-aware; months still render year-round
        vms_vendor_portal_render_availability($vendor_id, $active_dates);
    } elseif ($tab === 'all-vendors') {
        if (function_exists('vms_vendor_portal_render_all_vendors_availability')) {
            vms_vendor_portal_render_all_vendors_availability($vendor_ids, $base_url, $vendor_id);
        } else {
            echo wp_kses_post(bvmgr_portal_notice('error', __('All Vendors view is not available.', 'backstage-venue-manager')));
        }
    } elseif ($tab === 'tech') {
        if (function_exists('vms_vendor_portal_render_tech_docs')) {
            vms_vendor_portal_render_tech_docs($vendor_id);
        } else {
            echo wp_kses_post(bvmgr_portal_notice('error', __('Tech Docs module is not loaded.', 'backstage-venue-manager')));
        }
    } else {
        $custom_rendered = (bool) apply_filters('vms_vendor_portal_render_custom_tab', false, $tab, $portal_context);
        if (!$custom_rendered) {
            echo wp_kses_post(bvmgr_portal_notice('error', __('That portal section is not available.', 'backstage-venue-manager')));
        }
    }

    echo '</div>'; // body
    echo '</div>'; // vms-portal
    return (string) ob_get_clean();
}

// Register shortcode (override any previous registration).
add_shortcode('vms_vendor_portal', 'vms_vendor_portal_shortcode');

/* ==========================================================
 * Availability (Calendar UI — tap-to-toggle, mobile-first)
 * ========================================================== */

if (!function_exists('vms_vendor_portal_render_availability')) {
    function vms_vendor_portal_render_availability($vendor_id, $active_dates = array())
    {
        $vendor_id = (int) $vendor_id;

        if ($vendor_id <= 0) {
            echo wp_kses_post(bvmgr_portal_notice('error', __('Invalid vendor.', 'backstage-venue-manager')));
            return;
        }

        if (!is_array($active_dates)) $active_dates = array();

        // Normalize active dates
        $active_dates = array_values(array_filter(array_map(function ($d) {
            $d = trim((string) $d);
            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? $d : null;
        }, $active_dates)));

        $active_lookup = array_flip($active_dates);

        // Canonical "today" marker used to add vms-av-past classes in month cells.
        $today_date = wp_date('Y-m-d', time(), wp_timezone());


        // Load existing values
        $manual = get_post_meta($vendor_id, '_vms_availability_manual', true);
        if (!is_array($manual)) $manual = array();

        $ics_url      = (string) get_post_meta($vendor_id, '_vms_ics_url', true);
        $ics_autosync = (int) get_post_meta($vendor_id, '_vms_ics_autosync', true);
        $ics_last     = (int) get_post_meta($vendor_id, '_vms_ics_last_sync', true);
        $ics_meta = '';
        if (!empty($ics_url)) {
            $ics_meta = __('Enabled', 'backstage-venue-manager');
            if (!empty($ics_last)) {
                $ics_meta .= ' | ' . __('Last sync', 'backstage-venue-manager') . ' ' . wp_date('M j, Y g:ia', (int)$ics_last, wp_timezone());
            }
        } else {
            $ics_meta = __('Not set', 'backstage-venue-manager');
        }

        // Optional: dates marked unavailable from ICS sync module
        $ics_unavailable = get_post_meta($vendor_id, '_vms_ics_unavailable', true);
        if (!is_array($ics_unavailable)) $ics_unavailable = array();

        // Backward/alternate storage support:
        // - if it's a map (date => 'unavailable'), use keys
        // - if it's empty, fall back to the canonical ICS layer meta (also a map)
        $is_list = (array_keys($ics_unavailable) === range(0, max(0, count($ics_unavailable) - 1)));
        if (!$is_list && !empty($ics_unavailable)) {
            $ics_unavailable = array_keys($ics_unavailable);
        } elseif (empty($ics_unavailable)) {
            $ics_layer = get_post_meta($vendor_id, '_vms_availability_ics', true);
            if (is_array($ics_layer) && !empty($ics_layer)) {
                $ics_unavailable = array_keys($ics_layer);
            }
        }

        $ics_unavailable = array_values(array_unique(array_filter(array_map('sanitize_text_field', $ics_unavailable))));
        $ics_lookup = array_fill_keys($ics_unavailable, true);
        $preferred = (string) get_post_meta($vendor_id, '_vms_availability_preferred_method', true);
        if (!in_array($preferred, array('manual', 'ics', 'pattern'), true)) {
            $preferred = 'manual';
        }

        // ----------------------------------------------------------
        // POST handling
        // ----------------------------------------------------------
        if (bvmgr_request_method() === 'post') {

            // 1) Save ICS Settings
            if (isset($_POST['vms_save_ics_settings'])) {
                $nonce = (isset($_POST['vms_ics_nonce']) && !is_array($_POST['vms_ics_nonce']))
                    ? sanitize_text_field(wp_unslash((string) $_POST['vms_ics_nonce']))
                    : '';
                if ($nonce === '' || !wp_verify_nonce($nonce, 'vms_ics_settings')) {
                    echo wp_kses_post(bvmgr_portal_notice('error', __('Security check failed.', 'backstage-venue-manager')));
                } else {
                    $new_url = esc_url_raw(bvmgr_request_read_scalar($_POST, 'vms_ics_url'));
                    $new_autosync = bvmgr_request_read_bool_flag($_POST, 'vms_ics_autosync') ? 1 : 0;

                    $changed = (($new_url !== $ics_url) || ((int) $new_autosync !== (int) $ics_autosync));

                    update_post_meta($vendor_id, '_vms_ics_url', $new_url);
                    update_post_meta($vendor_id, '_vms_ics_autosync', (int) $new_autosync);

                    $ics_url      = $new_url;
                    $ics_autosync = (int) $new_autosync;

                    update_post_meta($vendor_id, '_vms_availability_preferred_method', 'ics');
                    $preferred = 'ics';

                    if ($changed) {
                        vms_vendor_flag_vendor_update($vendor_id, 'availability_ics_settings');
                    }

                    echo wp_kses_post(bvmgr_portal_notice('success', __('Calendar settings saved.', 'backstage-venue-manager')));
                }
            }

            // 2) Save Manual Availability
            if (isset($_POST['vms_save_availability'])) {
                $nonce = (isset($_POST['vms_avail_nonce']) && !is_array($_POST['vms_avail_nonce']))
                    ? sanitize_text_field(wp_unslash((string) $_POST['vms_avail_nonce']))
                    : '';
                if ($nonce === '' || !wp_verify_nonce($nonce, 'vms_save_availability')) {
                    echo wp_kses_post(bvmgr_portal_notice('error', __('Security check failed.', 'backstage-venue-manager')));
                } else {
                    $incoming = vms_vendor_portal_post_array('vms_availability');

                    $active_lookup = array_flip($active_dates);
                    $new_manual = array();

                    foreach ($incoming as $date => $state) {
                        if (!is_scalar($state)) {
                            continue;
                        }
                        $date  = sanitize_text_field((string) $date);
                        $state = sanitize_text_field((string) $state);

                        if (!isset($active_lookup[$date])) continue;

                        if ($state === 'available' || $state === 'unavailable') {
                            $new_manual[$date] = $state;
                        }
                    }

                    $changed = (serialize($new_manual) !== serialize($manual));

                    update_post_meta($vendor_id, '_vms_availability_manual', $new_manual);
                    $manual = $new_manual;

                    update_post_meta($vendor_id, '_vms_availability_preferred_method', 'manual');
                    $preferred = 'manual';

                    if ($changed) {
                        vms_vendor_flag_vendor_update($vendor_id, 'availability_manual');
                    }

                    echo wp_kses_post(bvmgr_portal_notice('success', __('Availability saved.', 'backstage-venue-manager')));
                }
            }

            // 2b) Save Pattern Availability
            if (isset($_POST['vms_save_pattern'])) {
                if (
                    !isset($_POST['vms_pattern_nonce']) ||
                    is_array($_POST['vms_pattern_nonce']) ||
                    !wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST['vms_pattern_nonce'])), 'vms_pattern_settings')
                ) {
                    echo wp_kses_post(bvmgr_portal_notice('error', __('Security check failed.', 'backstage-venue-manager')));
                } else {
                    $days = array();
                    foreach (vms_vendor_portal_post_array('vms_pattern_days') as $d) {
                        if (!is_scalar($d)) {
                            continue;
                        }
                        $d = (int) $d;
                        if ($d >= 0 && $d <= 6) $days[] = $d;
                    }
                    $days = array_values(array_unique($days));

                    $enabled = bvmgr_request_read_bool_flag($_POST, 'vms_pattern_enabled') ? 1 : 0;

                    // QoL guardrail: if any pattern day was selected, treat that as an intent to enable
                    // pattern availability even if the operator forgot to tick the separate enable box.
                    if (!empty($days)) {
                        $enabled = 1;
                    }

                    if (!$enabled) {
                        $days = array();
                    }

                    update_post_meta($vendor_id, '_vms_pattern_enabled', $enabled);
                    update_post_meta($vendor_id, '_vms_pattern_days', $days);

                    update_post_meta($vendor_id, '_vms_availability_preferred_method', 'pattern');
                    $preferred = 'pattern';

                    vms_vendor_flag_vendor_update($vendor_id, 'availability_pattern');

                    echo wp_kses_post(bvmgr_portal_notice('success', __('Pattern availability saved.', 'backstage-venue-manager')));
                }
            }

            // 3) Sync ICS Now
            if (isset($_POST['vms_sync_ics_now'])) {
                $nonce = (isset($_POST['vms_ics_nonce']) && !is_array($_POST['vms_ics_nonce']))
                    ? sanitize_text_field(wp_unslash((string) $_POST['vms_ics_nonce']))
                    : '';
                if ($nonce === '' || !wp_verify_nonce($nonce, 'vms_ics_settings')) {
                    echo wp_kses_post(bvmgr_portal_notice('error', __('Security check failed.', 'backstage-venue-manager')));
                } else {
                    update_post_meta($vendor_id, '_vms_availability_preferred_method', 'ics');
                    $preferred = 'ics';

                    if (empty($ics_url)) {
                        echo wp_kses_post(bvmgr_portal_notice('warning', __('Please paste your calendar feed (ICS) URL first.', 'backstage-venue-manager')));
                    } elseif (!function_exists('vms_vendor_ics_sync_now')) {
                        echo wp_kses_post(bvmgr_portal_notice('error', __('ICS sync module is not loaded.', 'backstage-venue-manager')));
                    } else {
                        $result = vms_vendor_ics_sync_now($vendor_id, $active_dates);

                        if (!empty($result['ok'])) {

                            // If your sync returns a list, persist it for UI/source labels.
                            if (isset($result['ics_unavailable']) && is_array($result['ics_unavailable'])) {
                                $raw = $result['ics_unavailable'];

                                // Accept either:
                                // - list: ['YYYY-MM-DD', 'YYYY-MM-DD']
                                // - map:  ['YYYY-MM-DD' => 'unavailable']
                                $raw_is_list = (array_keys($raw) === range(0, max(0, count($raw) - 1)));
                                if (!$raw_is_list && !empty($raw)) {
                                    $raw = array_keys($raw);
                                }

                                $ics_unavailable = array_values(array_unique(array_filter(array_map('sanitize_text_field', $raw))));
                                update_post_meta($vendor_id, '_vms_ics_unavailable', $ics_unavailable);
                                $ics_lookup = array_fill_keys($ics_unavailable, true);
                            }
                            update_post_meta($vendor_id, '_vms_ics_last_sync', time());
                            $ics_last = time();

                            vms_vendor_flag_vendor_update($vendor_id, 'availability_ics_sync');

                            $count = isset($result['ics_unavailable']) && is_array($result['ics_unavailable'])
                                ? count($result['ics_unavailable'])
                                : 0;

                            /* translators: %d: number of unavailable dates imported from the ICS calendar. */
                            echo wp_kses_post(bvmgr_portal_notice('success', sprintf(__('Calendar synced. %d date(s) marked unavailable.', 'backstage-venue-manager'), $count)));
                        } else {
                            $msg = !empty($result['error']) ? (string) $result['error'] : __('Calendar sync failed.', 'backstage-venue-manager');
                            echo wp_kses_post(bvmgr_portal_notice('error', $msg));
                        }
                    }
                }
            }
        }

        // ----------------------------------------------------------
        // Render UI
        // ----------------------------------------------------------
        echo '<h3>' . esc_html__('Availability', 'backstage-venue-manager') . '</h3>';

        if (empty($active_dates)) {
            echo wp_kses_post(bvmgr_portal_notice('warning', __('No season dates configured yet.', 'backstage-venue-manager')));
            return;
        }
        // Always render a fixed month window (prevents months from disappearing when out of season)
        // Vendor portal lookback (view-only months). Stored per user.
        $months_ahead = 13;

        $months_back_default = 1; // vendors usually only need a short lookback
        $months_back_allowed = array(0, 1, 12);

        $months_back = $months_back_default;
        $uid = (int) get_current_user_id();
        if ($uid > 0) {
            $saved = get_user_meta($uid, '_vms_portal_av_lookback', true);
            $saved = is_numeric($saved) ? (int) $saved : $months_back_default;
            if (in_array($saved, $months_back_allowed, true)) {
                $months_back = $saved;
            }
        }

        $requested_lookback = vms_vendor_portal_get_requested_lookback($months_back_allowed, $months_back);
        if ($requested_lookback !== $months_back) {
            $months_back = $requested_lookback;
            if ($uid > 0) {
                update_user_meta($uid, '_vms_portal_av_lookback', $months_back);
            }
        }

        $months = vms_av_build_month_window($months_ahead, $months_back);
// Which month should be open by default? (current month)
        $today_ym = wp_date('Y-m');
        $default_open_ym = $today_ym;

        // echo '<div class="vms-av-wrap">'; // replaced 20260114 @ 9:17am
        echo '<div class="vms-av-wrap" id="vms-av" data-today-ym="' . esc_attr(wp_date('Y-m')) . '">';

        // Collapsible: ICS
        $ics_is_open = ($preferred === 'ics');
        echo '<details class="vms-av-method" data-method="ics"';
        if ($ics_is_open) {
            echo ' open';
        }
        echo '>';
        echo '<summary>';
        echo '<span>Calendar Sync (ICS)</span>';
        echo '<span class="vms-av-summarymeta" data-summarymeta="ics">' . esc_html($ics_meta) . '</span>';
        echo '</summary>';
        echo '<div class="vms-pt-12">';

        echo '<form method="post" class="vms-av-row">';
        wp_nonce_field('vms_ics_settings', 'vms_ics_nonce');

        echo '<div class="field">';
        echo '<label><strong>' . esc_html__('ICS Feed URL', 'backstage-venue-manager') . '</strong></label><br>';
        echo '<input type="url" name="vms_ics_url" value="' . esc_attr($ics_url) . '" class="vms-w-100">';
        echo '</div>';

        echo '<div class="field vms-field-tight">';
        echo '<label class="vms-label-block">';
        echo '<input type="checkbox" name="vms_ics_autosync" value="1" ' . checked(1, $ics_autosync, false) . '> ';
        echo esc_html__('Auto-sync this calendar periodically (optional)', 'backstage-venue-manager');
        echo '</label>';

        echo '<div class="vms-av-actions">';
        echo '<button class="button button-primary" type="submit" name="vms_save_ics_settings">' . esc_html__('Save Calendar Settings', 'backstage-venue-manager') . '</button>';
        echo '<button class="button" type="submit" name="vms_sync_ics_now">' . esc_html__('Sync Now', 'backstage-venue-manager') . '</button>';
        echo '</div>';

        // if ($ics_last) {
        //     echo '<div class="vms-av-muted vms-mt-8">' . esc_html__('Last sync:', 'vms') . ' ' . esc_html(wp_date('M j, Y g:ia', $ics_last, wp_timezone())) . '</div>';
        // }

        echo '</div>'; // field
        echo '</form>';

        echo '</div></details>'; // /ICS

        // Collapsible: Pattern
        $pattern_is_open = ($preferred === 'pattern');

        $pattern_enabled = (int) get_post_meta($vendor_id, '_vms_pattern_enabled', true);

        $pattern_days = get_post_meta($vendor_id, '_vms_pattern_days', true);
        if (!is_array($pattern_days)) $pattern_days = array();

        // Normalize: ints 0–6 only, unique, sorted
        $pattern_days = array_values(array_unique(array_filter(array_map('intval', $pattern_days), function ($d) {
            return $d >= 0 && $d <= 6;
        })));
        sort($pattern_days);

        $pattern_meta = __('Off', 'backstage-venue-manager');
        if ($pattern_enabled && !empty($pattern_days)) {
            $labels = array('Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat');
            $picked = array();
            foreach ($pattern_days as $d) {
                if (isset($labels[(int)$d])) $picked[] = $labels[(int)$d];
            }
            $pattern_meta = __('Enabled', 'backstage-venue-manager') . ' | ' . implode(', ', $picked);
        }

        // If none selected, treat as disabled
        if (empty($pattern_days)) $pattern_enabled = 0;

        echo '<details class="vms-av-method" data-method="pattern"';
        if ($pattern_is_open) {
            echo ' open';
        }
        echo '>';
        echo '<summary>';
        echo '<span>Pattern Availability</span>';
        echo '<span class="vms-av-summarymeta" data-summarymeta="pattern">' . esc_html($pattern_meta) . '</span>';
        echo '</summary>';
        echo '<div class="vms-av-card vms-av-card--plain">';

        echo '<p class="vms-av-muted vms-m0 vms-mb-10">';
        echo esc_html__('Choose the days you’re usually available. All other days will be marked Unavailable.', 'backstage-venue-manager');
        echo '<br>';
        echo esc_html__('You can still tap any date in the calendar to override.', 'backstage-venue-manager');
        echo '<br>';
        echo esc_html__('Selecting any day here will automatically enable pattern availability when you save.', 'backstage-venue-manager');
        echo '</p>';

        echo '<form method="post">';
        wp_nonce_field('vms_pattern_settings', 'vms_pattern_nonce');

        echo '<label class="vms-flex vms-gap-8 vms-ai-center vms-m0 vms-mb-12">';
        echo '<input type="checkbox" name="vms_pattern_enabled" value="1" ' . checked(1, $pattern_enabled, false) . '>';
        echo '<strong>' . esc_html__('Enable pattern availability', 'backstage-venue-manager') . '</strong>';
        echo '</label>';

        $dows = array(
            0 => __('Sun', 'backstage-venue-manager'),
            1 => __('Mon', 'backstage-venue-manager'),
            2 => __('Tue', 'backstage-venue-manager'),
            3 => __('Wed', 'backstage-venue-manager'),
            4 => __('Thu', 'backstage-venue-manager'),
            5 => __('Fri', 'backstage-venue-manager'),
            6 => __('Sat', 'backstage-venue-manager'),
        );

        echo '<div class="vms-flex vms-gap-10 vms-wrap vms-m0 vms-mb-12">';
        foreach ($dows as $i => $lbl) {
            $is_checked = in_array((int) $i, array_map('intval', $pattern_days), true);
            echo '<label class="vms-flex vms-gap-6 vms-ai-center">';
            echo '<input type="checkbox" name="vms_pattern_days[]" value="' . esc_attr($i) . '" ' . checked(true, $is_checked, false) . '>';
            echo '<span>' . esc_html($lbl) . '</span>';
            echo '</label>';
        }
        echo '</div>';

        echo '<button class="button button-primary" type="submit" name="vms_save_pattern">';
        echo esc_html__('Save Pattern', 'backstage-venue-manager');
        echo '</button>';

        echo '</form>';
        echo '</div></details>';

        // Collapsible: Manual
        $manual_is_open = ($preferred === 'manual');
        echo '<details class="vms-av-method" data-method="manual"';
        if ($manual_is_open) {
            echo ' open';
        }
        echo '>';
        echo '<summary>';
        echo '<span>Manual Availability</span>';
        echo '<span class="vms-av-summarymeta" data-summarymeta="manual"></span>';
        echo '</summary>';
        echo '<div class="vms-pt-12">';
        echo '<p class="vms-av-help">' . esc_html__('Tap a date to toggle: Unset > Available > Unavailable. Then click Save Availability.', 'backstage-venue-manager') . '</p>';
        echo '<div class="vms-av-opps-intro" id="vms-opportunities"><strong>' . esc_html__('Opportunities', 'backstage-venue-manager') . '</strong><span class="vms-av-muted">' . esc_html__('Hover or focus an event title for details. Apply on open dates.', 'backstage-venue-manager') . '</span></div>';

        // Lookback selector (view-only months)
        echo '<form method="get" class="vms-av-lookback">';
        echo '<input type="hidden" name="vendor_id" value="' . esc_attr((string) $vendor_id) . '">';
        echo '<input type="hidden" name="tab" value="availability">';
        if (function_exists('vms_vendor_portal_render_preview_hidden_fields')) {
            vms_vendor_portal_render_preview_hidden_fields((int) $vendor_id);
        }
        echo '<label for="vms-av-lb">' . esc_html__('Show past:', 'backstage-venue-manager') . '</label>';
        echo '<select id="vms-av-lb" name="lb" data-vms-portal-submit-on-change="1">';
        echo '<option value="0"' . selected(0, $months_back, false) . '>' . esc_html__('None', 'backstage-venue-manager') . '</option>';
        echo '<option value="1"' . selected(1, $months_back, false) . '>' . esc_html__('1 month', 'backstage-venue-manager') . '</option>';
        echo '<option value="12"' . selected(12, $months_back, false) . '>' . esc_html__('12 months', 'backstage-venue-manager') . '</option>';
        echo '</select>';
        echo '<span class="vms-av-muted">' . esc_html__('Past months are view-only.', 'backstage-venue-manager') . '</span>';
        echo '</form>';

        // Small legend (quick scan for what the colors mean)
        echo '<div class="vms-av-legend" role="note" aria-label="' . esc_attr__('Availability legend', 'backstage-venue-manager') . '">';
        echo '<span class="vms-av-leg-item"><span class="vms-av-swatch is-unset" aria-hidden="true"></span>' . esc_html__('Unset', 'backstage-venue-manager') . '</span>';
        echo '<span class="vms-av-leg-item"><span class="vms-av-swatch is-available" aria-hidden="true"></span>' . esc_html__('Available', 'backstage-venue-manager') . '</span>';
        echo '<span class="vms-av-leg-item"><span class="vms-av-swatch is-unavailable" aria-hidden="true"></span>' . esc_html__('Unavailable', 'backstage-venue-manager') . '</span>';
        echo '<span class="vms-av-leg-item"><span class="vms-av-swatch is-tentative" aria-hidden="true"></span>' . esc_html__('Tentative', 'backstage-venue-manager') . '</span>';
        echo '<span class="vms-av-leg-item"><span class="vms-av-swatch is-booked" aria-hidden="true"></span>' . esc_html__('Booked', 'backstage-venue-manager') . '</span>';
        echo '</div>';

        echo '<form method="post" id="vms-av-form">';
        wp_nonce_field('vms_save_availability', 'vms_avail_nonce');

        $calendar_settings = (array) get_option('vms_settings', array());
        $show_event_overlay = !array_key_exists('calendar_vendor_show_event_overlay', $calendar_settings)
            || !empty($calendar_settings['calendar_vendor_show_event_overlay']);

        foreach ($months as $ym => $_unused) {
            $month_tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
            $month_dt = DateTimeImmutable::createFromFormat('!Y-m', $ym, $month_tz);
            $month_label = ($month_dt instanceof DateTimeImmutable)
                ? wp_date('F Y', $month_dt->getTimestamp(), $month_tz)
                : $ym;

            $matrix = vms_av_build_month_matrix($ym);


            // Build a list of active (toggleable) dates in this month (season-aware)
            $dates_in_month = array();
            $days_in_this_month = ($month_dt instanceof DateTimeImmutable) ? (int) $month_dt->format('t') : 0;

            for ($day_i = 1; $day_i <= $days_in_this_month; $day_i++) {
                $d = $ym . '-' . str_pad((string) $day_i, 2, '0', STR_PAD_LEFT);
                if (isset($active_lookup[$d])) $dates_in_month[] = $d;
            }

            // Canonical feed for monthly vendor calendar overlays.
            $events_by_date = array();
            $opportunity_rows_by_date = array();
            $viewer_type = function_exists('vms_vendor_portal_current_type_slug')
                ? vms_vendor_portal_current_type_slug((int) $vendor_id)
                : '';
            $busy_src_by_date = array(); // date => 'booked' | 'tentative' for this viewer only

            $month_start = $ym . '-01';
            $month_end_exclusive = gmdate('Y-m-d', strtotime('+1 month', strtotime($month_start)));
            $month_end_inclusive = gmdate('Y-m-d', strtotime('-1 day', strtotime($month_end_exclusive)));

            $month_events = array();
            if (function_exists('bvmgr_get_calendar_events')) {
                $feed_args = array(
                    'start_date' => $month_start,
                    'end_date' => $month_end_inclusive,
                    'context' => 'vendor',
                    'viewer_vendor_id' => (int) $vendor_id,
                    'include_past' => true,
                    'include_statuses' => array('published', 'ready', 'draft', 'tentative', 'confirmed'),
                );
                if (!empty($venue_id)) {
                    $feed_args['venue_ids'] = array((int) $venue_id);
                }
                $month_events = (array) bvmgr_get_calendar_events($feed_args);
            }

            foreach ($month_events as $event) {
                $d = isset($event['date_key']) ? (string) $event['date_key'] : '';
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
                    continue;
                }

                $viewer_status = isset($event['viewer_status']) && is_array($event['viewer_status']) ? $event['viewer_status'] : array();
                $is_assigned = !empty($viewer_status['assigned']);
                $assignment_status = isset($viewer_status['assignment_status']) ? sanitize_key((string) $viewer_status['assignment_status']) : '';

                if ($is_assigned && ($assignment_status === 'booked' || $assignment_status === 'tentative')) {
                    // booked beats tentative when multiple assignments collide on the same date.
                    if (!isset($busy_src_by_date[$d]) || $busy_src_by_date[$d] !== 'booked') {
                        $busy_src_by_date[$d] = $assignment_status;
                    }
                    if ($assignment_status === 'booked') {
                        $busy_src_by_date[$d] = 'booked';
                    }
                }

                $plan_id = absint($event['event_plan_id'] ?? 0);
                $title = isset($event['title']) ? trim((string) $event['title']) : '';
                if ($title === '') {
                    $title = __('Event', 'backstage-venue-manager');
                }

                $groups = isset($event['vendor_groups']) && is_array($event['vendor_groups']) ? $event['vendor_groups'] : array();
                $line_items = vms_vendor_portal_calendar_event_lines($event, (string) $viewer_type, (int) $vendor_id);

                $opportunity_status = vms_vendor_portal_opportunity_status((int) $vendor_id, $plan_id);
                $show_opportunity_row = !empty($opportunity_status['visible'])
                    && in_array((string) ($opportunity_status['status'] ?? ''), array('open', 'pending', 'reviewed', 'withdrawn'), true);
                if ($show_opportunity_row) {
                    $opportunity_context = function_exists('vms_add_dispatch_get_event_plan_context')
                        ? (array) vms_add_dispatch_get_event_plan_context($plan_id)
                        : array();
                    $opportunity_target = function_exists('vms_vendor_portal_opportunity_target_meta')
                        ? (array) vms_vendor_portal_opportunity_target_meta((string) $viewer_type, $groups, $opportunity_context)
                        : array('icon' => '', 'label' => '');
                    $opportunity_icon = trim((string) ($opportunity_target['icon'] ?? ''));
                    $opportunity_type_label = trim((string) ($opportunity_target['label'] ?? ''));

                    $status_key = sanitize_key((string) ($opportunity_status['status'] ?? ''));
                    $status_label = (string) ($opportunity_status['label'] ?? __('Opportunity', 'backstage-venue-manager'));
                    if ($status_key === 'open') {
                        $status_label = __('Open', 'backstage-venue-manager');
                    } elseif ($status_key === 'pending') {
                        $status_label = __('Requested', 'backstage-venue-manager');
                    }

                    if (!isset($opportunity_rows_by_date[$d])) {
                        $opportunity_rows_by_date[$d] = array();
                    }
                    $opportunity_rows_by_date[$d][] = array(
                        'event_plan_id' => $plan_id,
                        'status' => $status_key,
                        'label' => $status_label,
                        'icon' => $opportunity_icon,
                        'type_label' => $opportunity_type_label,
                        'submit_label' => function_exists('vms_vendor_portal_submit_application_label')
                            ? vms_vendor_portal_submit_application_label($opportunity_type_label)
                            : __('Submit Application', 'backstage-venue-manager'),
                        'submitted_at' => (string) ($opportunity_status['submitted_at'] ?? ''),
                        'can_submit' => !empty($opportunity_status['can_submit']),
                        'can_withdraw' => !empty($opportunity_status['can_withdraw']),
                    );
                }

                if (!isset($events_by_date[$d])) {
                    $events_by_date[$d] = array();
                }
                foreach ($line_items as $line_item) {
                    $line_text = trim((string) ($line_item['text'] ?? ''));
                    if ($line_text === '') {
                        continue;
                    }
                    $events_by_date[$d][] = array(
                        'kind' => sanitize_key((string) ($line_item['kind'] ?? 'event')),
                        'text' => $line_text,
                        'url'  => esc_url_raw((string) ($line_item['url'] ?? '')),
                        'event_plan_id' => absint($line_item['event_plan_id'] ?? 0),
                        'modal_title' => (string) ($line_item['modal_title'] ?? ''),
                        'modal_date_label' => (string) ($line_item['modal_date_label'] ?? ''),
                        'modal_time_label' => (string) ($line_item['modal_time_label'] ?? ''),
                        'modal_excerpt' => (string) ($line_item['modal_excerpt'] ?? ''),
                        'modal_image_url' => (string) ($line_item['modal_image_url'] ?? ''),
                        'modal_view_url' => (string) ($line_item['modal_view_url'] ?? ''),
                        'modal_venue_name' => (string) ($line_item['modal_venue_name'] ?? ''),
                    );
                }
            }

            $cnt_na = 0;
            $cnt_a = 0;
            $cnt_active = count($dates_in_month);
            foreach ($dates_in_month as $d) {
                $availability = vms_vendor_effective_availability_for_date((int) $vendor_id, $d, array(
                    'busy_source' => isset($busy_src_by_date[$d]) ? (string) $busy_src_by_date[$d] : '',
                ));
                $visual_state = sanitize_key((string) ($availability['visual_state'] ?? ''));
                $state = sanitize_key((string) ($availability['state'] ?? 'no-response'));
                if ($state === 'available' || $visual_state === 'available') {
                    $cnt_a++;
                } elseif (in_array($state, array('booked', 'tentative', 'unavailable'), true) || $visual_state === 'unavailable') {
                    $cnt_na++;
                }
            }

            $month_is_open = ($ym === $default_open_ym);
            echo '<div class="vms-av-month" data-ym="' . esc_attr($ym) . '">';
            echo '<details';
            if ($month_is_open) {
                echo ' open';
            }
            echo '>';
            echo '<summary>';
            echo '<span class="vms-av-monthlabel">';
            echo '<span class="vms-av-monthname">' . esc_html($month_label) . '</span>';
            echo '<span class="vms-av-howto">' . esc_html__('Tap days to toggle availability', 'backstage-venue-manager') . '</span>';
            echo '</span>';
            echo '<span class="vms-av-counts vms-av-muted" data-active="' . esc_attr((string) $cnt_active) . '">' . esc_html(sprintf('%d active | %d NA | %d A', $cnt_active, $cnt_na, $cnt_a)) . '</span>';
            echo '</summary>';

            echo '<table class="vms-av-grid">';
            echo '<thead><tr class="vms-av-dow">';
            $dow = array(
                __('Sun', 'backstage-venue-manager'),
                __('Mon', 'backstage-venue-manager'),
                __('Tue', 'backstage-venue-manager'),
                __('Wed', 'backstage-venue-manager'),
                __('Thu', 'backstage-venue-manager'),
                __('Fri', 'backstage-venue-manager'),
                __('Sat', 'backstage-venue-manager')
            );
            foreach ($dow as $lbl) {
                echo '<th scope="col">' . esc_html($lbl) . '</th>';
            }
            echo '</tr></thead><tbody>';

            foreach ($matrix as $week) {
                echo '<tr>';
                foreach ($week as $cell) {
                    $date = $cell['date'];
                    $daynum = $cell['day'];

                    if (!$date) {
                        echo '<td class="vms-av-inactive"></td>';
                        continue;
                    }

                    $is_active = isset($active_lookup[$date]);

                    $is_past = (!empty($today_date) && is_string($today_date) && $date < $today_date);

                    $manual_state = isset($manual[$date]) ? (string) $manual[$date] : '';
                    $availability = vms_vendor_effective_availability_for_date((int) $vendor_id, $date, array(
                        'busy_source' => isset($busy_src_by_date[$date]) ? (string) $busy_src_by_date[$date] : '',
                    ));

                    $availability_state = sanitize_key((string) ($availability['state'] ?? 'no-response'));
                    $visual_state = sanitize_key((string) ($availability['visual_state'] ?? ''));
                    $base_state = ($visual_state === 'available' || $visual_state === 'unavailable') ? $visual_state : '';
                    $base_src = sanitize_key((string) ($availability['reason'] ?? ''));
                    if (in_array($base_src, array('no_response', 'invalid', 'assigned_here'), true)) {
                        $base_src = '';
                    }

                    $busy_src = ($availability_state === 'booked' || $availability_state === 'tentative') ? $availability_state : '';
                    $is_busy = ($busy_src === 'booked' || $busy_src === 'tentative');

                    $status_key = ($base_state === 'available' || $base_state === 'unavailable') ? $base_state : 'unset';
                    $status_label = trim((string) ($availability['label'] ?? ''));
                    if ($status_label === '') {
                        $status_label = ($status_key === 'available') ? __('Available', 'backstage-venue-manager') : (($status_key === 'unavailable') ? __('Unavailable', 'backstage-venue-manager') : __('Unset', 'backstage-venue-manager'));
                    }

                    $show_status_badge = ($is_active && empty($is_busy));

                    $td_classes = array();
                    if (!$is_active) $td_classes[] = 'vms-av-inactive';
                    if (!empty($is_past)) $td_classes[] = 'vms-av-past';
                    echo '<td' . (!empty($td_classes) ? ' class="' . esc_attr(implode(' ', $td_classes)) . '"' : '') . '>';
                    echo '<div class="vms-av-cell-badges">';
                    if (!empty($is_busy)) {
                        if ($busy_src === 'booked') {
                            echo '<span class="vms-av-badge-booked">' . esc_html__('Booked', 'backstage-venue-manager') . '</span>';
                        } else {
                            echo '<span class="vms-av-badge-tentative">' . esc_html__('Tentative', 'backstage-venue-manager') . '</span>';
                        }
                    }
                    if ($show_status_badge) {
                        echo '<span class="vms-av-badge-status is-' . esc_attr($status_key) . '">' . esc_html($status_label) . '</span>';
                    }
                    echo '</div>';

                    echo '<div class="vms-av-day"><span class="vms-av-daynum">' . esc_html((string) $daynum) . '</span></div>';

                    // Toggle is allowed only when:
                    // - within the active date set
                    // - not in the past (view-only)
                    // - not booked (booked days are locked)
                    $is_toggleable = ($is_active && empty($is_past) && empty($is_busy));

                    if ($is_toggleable) {
                        // Hidden input that actually gets saved (manual only)
                        echo '<input type="hidden"
  name="vms_availability[' . esc_attr($date) . ']"
  value="' . esc_attr($manual_state) . '"
  data-date="' . esc_attr($date) . '"
  class="vms-av-hidden">';

                        echo '<button type="button"
  class="vms-av-btn"
  data-date="' . esc_attr($date) . '"
  data-state="' . esc_attr($manual_state) . '"
  data-base="' . esc_attr($base_state) . '"
  data-base-src="' . esc_attr($base_src) . '"
  data-visual="' . esc_attr($visual_state) . '"
  data-src="' . esc_attr($src) . '">';

                        // Show source icon only when NOT manual (and only when there is a source)
                        if ($manual_state === '' && $base_src !== '') {
                            // Easy find/replace later if you want different icons
                            $icon = ($base_src === 'ics') ? '📅' : (($base_src === 'pattern') ? '🗓️' : (($base_src === 'tentative') ? '⏳' : '🎟️'));
                            echo '<span class="vms-av-src" aria-hidden="true" data-src-type="' . esc_attr($base_src) . '">' . esc_html($icon) . '</span>';
                        }

                        echo '</button>';
                    } else {
                        // Read-only display (does NOT submit values, so it cannot wipe older data)
                        $ro_class = 'vms-av-readonly';
                        if ($visual_state === 'available') {
                            $ro_class .= ' is-available';
                        } elseif ($visual_state === 'unavailable') {
                            $ro_class .= ' is-unavailable';
                        }

                        echo '<div class="' . esc_attr($ro_class) . '" data-visual="' . esc_attr($visual_state) . '">';

                        if ($manual_state === '' && $base_src !== '') {
                            $icon = ($base_src === 'ics') ? '📅' : (($base_src === 'pattern') ? '🗓️' : (($base_src === 'tentative') ? '⏳' : '🎟️'));
                            echo '<span class="vms-av-src" aria-hidden="true" data-src-type="' . esc_attr($base_src) . '">' . esc_html($icon) . '</span>';
                        }

                        echo '</div>';
                    }

// PATCH 20260114 @ 09:25am
                    $render_opportunity_rows = array();
                    $opportunity_plan_map = array();
                    if (isset($opportunity_rows_by_date[$date]) && !empty($opportunity_rows_by_date[$date])) {
                        foreach ((array) $opportunity_rows_by_date[$date] as $opp_row) {
                            $opp_plan_id = absint($opp_row['event_plan_id'] ?? 0);
                            $opp_return_url = add_query_arg(
                                array(
                                    'vendor_id' => (int) $vendor_id,
                                    'tab' => 'availability',
                                ),
                                (string) get_permalink()
                            );
                            $opp_submit_url = '';
                            $opp_withdraw_url = '';
                            if ($opp_plan_id > 0) {
                                $opp_submit_url = wp_nonce_url(
                                    add_query_arg(array(
                                        'action' => 'vms_vendor_portal_interest_submit',
                                        'vendor_id' => (int) $vendor_id,
                                        'event_plan_id' => $opp_plan_id,
                                        'return_url' => $opp_return_url,
                                    ), admin_url('admin-post.php')),
                                    'vms_vendor_portal_interest_submit',
                                    '_vms_vendor_interest_nonce'
                                );
                                $opp_withdraw_url = wp_nonce_url(
                                    add_query_arg(array(
                                        'action' => 'vms_vendor_portal_interest_withdraw',
                                        'vendor_id' => (int) $vendor_id,
                                        'event_plan_id' => $opp_plan_id,
                                        'return_url' => $opp_return_url,
                                    ), admin_url('admin-post.php')),
                                    'vms_vendor_portal_interest_withdraw',
                                    '_vms_vendor_withdraw_nonce'
                                );
                            }
                            $render_row = $opp_row;
                            $render_row['submit_url'] = $opp_submit_url;
                            $render_row['withdraw_url'] = $opp_withdraw_url;
                            $render_opportunity_rows[] = $render_row;
                            if ($opp_plan_id > 0) {
                                $opportunity_plan_map[$opp_plan_id] = $render_row;
                            }
                        }
                    }

                    if ($show_event_overlay && isset($events_by_date[$date]) && !empty($events_by_date[$date])) {
                        $items = array();
                        foreach ((array) $events_by_date[$date] as $row) {
                            if (is_array($row)) {
                                $row_text = trim((string) ($row['text'] ?? ''));
                                if ($row_text === '') {
                                    continue;
                                }
                                $items[] = array(
                                    'kind' => sanitize_key((string) ($row['kind'] ?? 'event')),
                                    'text' => $row_text,
                                    'url'  => esc_url_raw((string) ($row['url'] ?? '')),
                                    'event_plan_id' => absint($row['event_plan_id'] ?? 0),
                                    'modal_title' => (string) ($row['modal_title'] ?? ''),
                                    'modal_date_label' => (string) ($row['modal_date_label'] ?? ''),
                                    'modal_time_label' => (string) ($row['modal_time_label'] ?? ''),
                                    'modal_excerpt' => (string) ($row['modal_excerpt'] ?? ''),
                                    'modal_image_url' => (string) ($row['modal_image_url'] ?? ''),
                                    'modal_view_url' => (string) ($row['modal_view_url'] ?? ''),
                                    'modal_venue_name' => (string) ($row['modal_venue_name'] ?? ''),
                                );
                            } else {
                                $row_text = trim((string) $row);
                                if ($row_text === '') {
                                    continue;
                                }
                                $items[] = array(
                                    'kind' => 'event',
                                    'text' => $row_text,
                                    'url'  => '',
                                    'event_plan_id' => 0,
                                );
                            }
                        }

                        $lines = array_slice($items, 0, 3);
                        $more  = count($items) - count($lines);

                        $title_parts = array();
                        foreach ($items as $row) {
                            $title_parts[] = (string) $row['text'];
                        }
                        $title_attr = implode(' | ', $title_parts);

                        echo '<div class="vms-av-event-title vms-public-cal" title="' . esc_attr($title_attr) . '">';
                        foreach ($lines as $row) {
                            $line_text = (string) ($row['text'] ?? '');
                            $line_kind = sanitize_key((string) ($row['kind'] ?? 'event'));
                            $line_url  = esc_url((string) ($row['url'] ?? ''));
                            $line_plan_id = absint($row['event_plan_id'] ?? 0);

                            $line_class = 'vms-av-meta-line';
                            if ($line_kind !== '') {
                                $line_class .= ' is-' . $line_kind;
                            }

                            if ($line_kind === 'event') {
                                $modal_primary_label = '';
                                $modal_primary_url = '';
                                $modal_secondary_label = '';
                                $modal_secondary_url = '';
                                if ($line_plan_id > 0 && isset($opportunity_plan_map[$line_plan_id]) && is_array($opportunity_plan_map[$line_plan_id])) {
                                    $modal_opp = $opportunity_plan_map[$line_plan_id];
                                    if (!empty($modal_opp['can_submit']) && !empty($modal_opp['submit_url'])) {
                                        $modal_primary_label = (string) ($modal_opp['submit_label'] ?? __('Submit Application', 'backstage-venue-manager'));
                                        $modal_primary_url = (string) ($modal_opp['submit_url'] ?? '');
                                        if ($line_url !== '') {
                                            $modal_secondary_label = __('View Event Page', 'backstage-venue-manager');
                                            $modal_secondary_url = $line_url;
                                        }
                                    } elseif (!empty($modal_opp['can_withdraw']) && !empty($modal_opp['withdraw_url'])) {
                                        $modal_primary_label = __('Withdraw Request', 'backstage-venue-manager');
                                        $modal_primary_url = (string) ($modal_opp['withdraw_url'] ?? '');
                                        if ($line_url !== '') {
                                            $modal_secondary_label = __('View Event Page', 'backstage-venue-manager');
                                            $modal_secondary_url = $line_url;
                                        }
                                    } elseif ($line_url !== '') {
                                        $modal_primary_label = __('View Event Page', 'backstage-venue-manager');
                                        $modal_primary_url = $line_url;
                                    }
                                } elseif ($line_url !== '') {
                                    $modal_primary_label = __('View Event Page', 'backstage-venue-manager');
                                    $modal_primary_url = $line_url;
                                }

                                $modal_fallback_title = trim((string) ($row['modal_title'] ?? ''));
                                if ($modal_fallback_title === '') {
                                    $modal_fallback_title = trim((string) preg_replace('/\s*@\s*\d{1,2}:\d{2}(?:am|pm)(?:\s*-\s*\d{1,2}:\d{2}(?:am|pm))?$/i', '', wp_strip_all_tags($line_text)));
                                }
                                $event_href = trim((string) ($row['modal_view_url'] ?? ''));
                                if ($event_href === '') {
                                    $event_href = trim((string) ($modal_primary_url !== '' ? $modal_primary_url : $line_url));
                                }
                                if ($event_href === '' && $modal_secondary_url !== '') {
                                    $event_href = trim((string) $modal_secondary_url);
                                }
                                $popover_title = $modal_fallback_title !== '' ? $modal_fallback_title : wp_strip_all_tags($line_text);
                                $popover_date = (string) ($row['modal_date_label'] ?? (function_exists('bvmgr_format_local_ymd') ? bvmgr_format_local_ymd($date, 'D, M j, Y') : $date));
                                $popover_time = (string) ($row['modal_time_label'] ?? '');
                                $popover_excerpt = (string) ($row['modal_excerpt'] ?? '');
                                $popover_image = (string) ($row['modal_image_url'] ?? '');
                                $popover_venue = (string) ($row['modal_venue_name'] ?? '');
                                $event_view_url = trim((string) ($row['modal_view_url'] ?? ''));
                                if ($event_view_url === '') {
                                    $event_view_url = trim((string) $line_url);
                                }
                                $visible_url = $event_view_url !== '' ? $event_view_url : '';
                                echo '<div class="' . esc_attr($line_class . ' is-trigger vms-cal-entry') . '"' . ($visible_url === '' ? ' tabindex="0"' : '') . '>';
                                echo '<div class="vms-cal-entry-vendors">';
                                if ($visible_url !== '') {
                                    echo '<a class="vms-cal-vendor-row vms-cal-entry-vendor is-primary" href="' . esc_url($visible_url) . '"><span class="vms-cal-vendor-name">' . esc_html($line_text) . '</span></a>';
                                } else {
                                    echo '<span class="vms-cal-vendor-row vms-cal-entry-vendor is-primary"><span class="vms-cal-vendor-name">' . esc_html($line_text) . '</span></span>';
                                }
                                echo '</div>';
                                echo '<div class="vms-cal-pop">';
                                echo '<div class="vms-cal-pop-body">';
                                if ($popover_image !== '') {
                                    if ($event_view_url !== '') {
                                        echo '<a class="vms-cal-pop-media" href="' . esc_url($event_view_url) . '"><img src="' . esc_url($popover_image) . '" alt="" loading="lazy"></a>';
                                    } else {
                                        echo '<div class="vms-cal-pop-media"><img src="' . esc_url($popover_image) . '" alt="" loading="lazy"></div>';
                                    }
                                }
                                echo '<div class="vms-cal-pop-vendors">';
                                if ($event_view_url !== '') {
                                    echo '<a class="vms-cal-vendor-row vms-cal-pop-vendor is-primary" href="' . esc_url($event_view_url) . '"><span class="vms-cal-vendor-name">' . esc_html($popover_title) . '</span></a>';
                                } else {
                                    echo '<span class="vms-cal-vendor-row vms-cal-pop-vendor is-primary"><span class="vms-cal-vendor-name">' . esc_html($popover_title) . '</span></span>';
                                }
                                if ($popover_venue !== '') {
                                    echo '<div class="vms-cal-vendor-row vms-cal-pop-vendor is-secondary"><span class="vms-cal-vendor-name">' . esc_html($popover_venue) . '</span></div>';
                                }
                                echo '</div>';
                                if ($popover_date !== '') {
                                    echo '<div class="vms-cal-pop-date">' . esc_html($popover_date) . '</div>';
                                }
                                if ($popover_time !== '') {
                                    echo '<div class="vms-cal-pop-time">' . esc_html($popover_time) . '</div>';
                                }
                                if ($popover_excerpt !== '') {
                                    echo '<div class="vms-cal-pop-excerpt">' . esc_html($popover_excerpt) . '</div>';
                                }
                                if ($modal_primary_url !== '' || $modal_secondary_url !== '') {
                                    echo '<div class="vms-cal-pop-actions">';
                                    if ($modal_primary_label !== '' && $modal_primary_url !== '') {
                                        echo '<a class="vms-cal-pop-ticket" href="' . esc_url($modal_primary_url) . '">' . esc_html($modal_primary_label) . '</a>';
                                    }
                                    if ($modal_secondary_label !== '' && $modal_secondary_url !== '') {
                                        echo '<a class="vms-cal-pop-secondary" href="' . esc_url($modal_secondary_url) . '">' . esc_html($modal_secondary_label) . '</a>';
                                    }
                                    echo '</div>';
                                }
                                echo '</div>';
                                echo '</div>';
                                echo '</div>';
                            } elseif ($line_url !== '') {
                                echo '<span class="' . esc_attr($line_class . ' is-link') . '"><a href="' . esc_url($line_url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($line_text) . '</a></span>';
                            } else {
                                echo '<span class="' . esc_attr($line_class) . '">' . esc_html($line_text) . '</span>';
                            }
                        }
                        if ($more > 0) {
                            echo '<span class="vms-av-meta-more">+' . (int) $more . '</span>';
                        }
                        echo '</div>';
                    }

                    if (!empty($render_opportunity_rows)) {
                        echo '<div class="vms-av-opportunity-list">';
                        foreach ((array) $render_opportunity_rows as $opp_row) {
                            $opp_status = sanitize_html_class((string) ($opp_row['status'] ?? 'open'));
                            $opp_label = trim((string) ($opp_row['label'] ?? __('Opportunity', 'backstage-venue-manager')));
                            $opp_icon = trim((string) ($opp_row['icon'] ?? ''));
                            $opp_submitted_raw = trim((string) ($opp_row['submitted_at'] ?? ''));
                            $opp_submitted_label = '';
                            if ($opp_submitted_raw !== '') {
                                $opp_submitted_ts = strtotime($opp_submitted_raw);
                                if ($opp_submitted_ts) {
                                    $opp_submitted_label = wp_date('M j', $opp_submitted_ts, wp_timezone());
                                }
                            }
                            $opp_submit_url = (string) ($opp_row['submit_url'] ?? '');
                            $opp_withdraw_url = (string) ($opp_row['withdraw_url'] ?? '');

                            echo '<div class="vms-av-opportunity-row is-' . esc_attr($opp_status) . '">';
                            echo '<span class="vms-av-opportunity-pill is-' . esc_attr($opp_status) . '">' . esc_html(trim(($opp_icon !== '' ? $opp_icon . ' ' : '') . $opp_label)) . '</span>';
                            if ($opp_submitted_label !== '') {
                                echo '<span class="vms-av-opportunity-date">' . esc_html($opp_submitted_label) . '</span>';
                            }
                            if (!empty($opp_row['can_submit']) && $opp_submit_url !== '') {
                                echo '<a class="button button-small button-primary vms-av-opportunity-action" href="' . esc_url($opp_submit_url) . '">' . esc_html__('Apply', 'backstage-venue-manager') . '</a>';
                            }
                            if (!empty($opp_row['can_withdraw']) && $opp_withdraw_url !== '') {
                                echo '<a class="button button-small vms-av-opportunity-action" href="' . esc_url($opp_withdraw_url) . '">' . esc_html__('Withdraw', 'backstage-venue-manager') . '</a>';
                            }
                            echo '</div>';
                        }
                        echo '</div>';
                    }
                    echo '</td>';
                }
                echo '</tr>';
            }

            echo '</tbody></table>';
            echo '</details>';
            echo '</div>';
        }

        echo '<p class="vms-m0 vms-mt-14">';
        echo '<button class="button button-primary" type="submit" name="vms_save_availability">' . esc_html__('Save Availability', 'backstage-venue-manager') . '</button>';
        echo '</p>';

        echo '</form>';

        $avail_ajax_nonce = wp_create_nonce('vms_avail_ajax');
        $preview_vendor_id = function_exists('vms_vendor_portal_get_preview_vendor_id')
            ? (int) vms_vendor_portal_get_preview_vendor_id()
            : 0;
        $preview_query_args = ($preview_vendor_id > 0 && function_exists('vms_vendor_portal_get_preview_query_args'))
            ? (array) vms_vendor_portal_get_preview_query_args((int) $preview_vendor_id)
            : array();
        $autosave_config = array(
            'ajax' => (string) admin_url('admin-ajax.php'),
            'token' => (string) $avail_ajax_nonce,
            'previewId' => (int) $preview_vendor_id,
            'previewToken' => $preview_vendor_id > 0
                ? (string) ($preview_query_args['vms_preview_nonce'] ?? '')
                : '',
        );
        $autosave_config_id = function_exists('wp_unique_id')
            ? wp_unique_id('vms-vendor-portal-config-')
            : uniqid('vms-vendor-portal-config-', false);
        $autosave_config_json = wp_json_encode($autosave_config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        if (!is_string($autosave_config_json) || $autosave_config_json === '') {
            $autosave_config_json = '{}';
        }
        echo '<div class="vms-av-autosave" aria-live="polite"></div>';
        echo '<script type="application/json" id="' . esc_attr($autosave_config_id) . '" data-vms-portal-config="availability">' . $autosave_config_json . '</script>';

        echo '</div></details>'; // /Manual
        echo '</div>'; // wrap
    }
}


if (!function_exists('vms_vendor_portal_render_all_vendors_availability')) {
    /**
     * All Vendors — MVP view-only calendar.
     * Shows bookings (vendor name + start time) grouped by venue, using the same month-grid
     * structure as the admin Schedule (All Venues) view.
     *
     * NOTE: This view is intentionally read-only (no toggles, no links).
     */
    function vms_vendor_portal_render_all_vendors_availability($vendor_ids, $base_url, $current_vendor_id = 0)
    {
        echo '<h3>' . esc_html__('All Vendors', 'backstage-venue-manager') . '</h3>';

        if (!is_array($vendor_ids) || count($vendor_ids) < 2) {
            echo wp_kses_post(bvmgr_portal_notice('warning', __('You do not have multiple vendors linked to this account.', 'backstage-venue-manager')));
            return;
        }

        // Normalize vendor ids
        $vendor_ids = array_values(array_unique(array_filter(array_map('intval', $vendor_ids), function ($v) {
            return $v > 0;
        })));

        if (empty($vendor_ids)) {
            echo wp_kses_post(bvmgr_portal_notice('warning', __('No vendors available for this view.', 'backstage-venue-manager')));
            return;
        }

        // ------------------------------------------------------------------
        // Lookback selector (same UX as Availability, separate saved value)
        // ------------------------------------------------------------------
        $months_ahead = 12;
        $months_back_default = 0;
        $months_back_allowed = array(0, 1, 12);

        $months_back = $months_back_default;
        $uid = (int) get_current_user_id();
        if ($uid > 0) {
            $saved = get_user_meta($uid, '_vms_portal_all_vendors_lookback', true);
            $saved = is_numeric($saved) ? (int) $saved : $months_back_default;
            if (in_array($saved, $months_back_allowed, true)) {
                $months_back = $saved;
            }
        }

        $requested_lookback = vms_vendor_portal_get_requested_lookback($months_back_allowed, $months_back);
        if ($requested_lookback !== $months_back) {
            $months_back = $requested_lookback;
            if ($uid > 0) {
                update_user_meta($uid, '_vms_portal_all_vendors_lookback', $months_back);
            }
        }

        $months = function_exists('vms_av_build_month_window')
            ? vms_av_build_month_window($months_ahead, $months_back)
            : array();

        if (empty($months)) {
            echo wp_kses_post(bvmgr_portal_notice('warning', __('No months available for this view.', 'backstage-venue-manager')));
            return;
        }

        echo '<form method="get" class="vms-av-lookback">';
        echo '<input type="hidden" name="tab" value="all-vendors">';
        if ((int) $current_vendor_id > 0) {
            echo '<input type="hidden" name="vendor_id" value="' . esc_attr((string) (int) $current_vendor_id) . '">';
        }
        if (function_exists('vms_vendor_portal_render_preview_hidden_fields')) {
            vms_vendor_portal_render_preview_hidden_fields((int) $current_vendor_id);
        }

        echo '<label for="vms-av-lb-all">' . esc_html__('Show past:', 'backstage-venue-manager') . '</label>';
        echo '<select id="vms-av-lb-all" name="lb" data-vms-portal-submit-on-change="1">';
        $opts = array(
            0  => __('None', 'backstage-venue-manager'),
            1  => __('1 month', 'backstage-venue-manager'),
            12 => __('12 months', 'backstage-venue-manager'),
        );
        foreach ($opts as $val => $label) {
            echo '<option value="' . esc_attr((string) $val) . '" ' . selected($months_back, (int) $val, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '</form>';

        // ------------------------------------------------------------------
        // Venue name map is built after canonical feed query.
        // ------------------------------------------------------------------

        // ------------------------------------------------------------------
        // Render window for bookings query (inclusive start, exclusive end)
        // ------------------------------------------------------------------
        $month_keys = array_keys($months);
        sort($month_keys);

        $window_start = !empty($month_keys) ? ($month_keys[0] . '-01') : wp_date('Y-m-01');
        $window_end   = $window_start;

        if (!empty($month_keys)) {
            $last_ym = $month_keys[count($month_keys) - 1] . '-01';
            $window_end = gmdate('Y-m-d', strtotime('+1 month', strtotime($last_ym))); // exclusive end
        }

        // ------------------------------------------------------------------
        // Canonical feed for bookings across all vendors in this view.
        // Grouped as: $bookings[YYYY-MM-DD][venue_id][] = label
        // ------------------------------------------------------------------
        $bookings = array();
        $venue_ids_seen = array();

        if (!empty($window_start) && !empty($window_end)) {
            $window_end_inclusive = gmdate('Y-m-d', strtotime('-1 day', strtotime($window_end)));
            $vendor_filter = array_fill_keys(array_values(array_unique(array_map('absint', (array) $vendor_ids))), true);

            $window_events = function_exists('bvmgr_get_calendar_events')
                ? (array) bvmgr_get_calendar_events(array(
                    'start_date' => $window_start,
                    'end_date' => $window_end_inclusive,
                    'context' => 'admin',
                    'include_past' => true,
                    'include_statuses' => array('published', 'ready', 'draft', 'tentative', 'confirmed'),
                ))
                : array();

            foreach ($window_events as $event) {
                $date = isset($event['date_key']) ? (string) $event['date_key'] : '';
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    continue;
                }

                // Keep only events that involve one of the vendors in this tab.
                $matches_filter = false;
                $groups = isset($event['vendor_groups']) && is_array($event['vendor_groups']) ? $event['vendor_groups'] : array();
                foreach ($groups as $group) {
                    if (!is_array($group) || empty($group['vendors']) || !is_array($group['vendors'])) {
                        continue;
                    }
                    foreach ($group['vendors'] as $vendor_row) {
                        $vid = absint($vendor_row['vendor_id'] ?? 0);
                        if ($vid > 0 && isset($vendor_filter[$vid])) {
                            $matches_filter = true;
                            break 2;
                        }
                    }
                }
                if (!$matches_filter) {
                    continue;
                }

                $venue_id = absint($event['venue_id'] ?? 0);
                $title = isset($event['title']) ? trim((string) $event['title']) : '';
                if ($title === '') {
                    $title = __('(Event)', 'backstage-venue-manager');
                }

                $time_txt = '';
                $start_local = isset($event['start_local']) ? (string) $event['start_local'] : '';
                if ($start_local !== '') {
                    try {
                        $dt = new DateTimeImmutable($start_local);
                        $time_txt = $dt->format('g:ia');
                    } catch (Exception $e) {
                        $time_txt = '';
                    }
                }

                $label = $title;
                if ($time_txt !== '') {
                    $label .= ' @ ' . $time_txt;
                }

                $status = isset($event['plan_status']) ? sanitize_key((string) $event['plan_status']) : '';
                $busy_src = function_exists('bvmgr_calendar_assignment_status_for_plan')
                    ? (string) (bvmgr_calendar_assignment_status_for_plan($status) ?? '')
                    : '';
                if ($busy_src === 'tentative') {
                    $label = __('Tentative', 'backstage-venue-manager') . ': ' . $label;
                } elseif ($busy_src === 'booked') {
                    $label = __('Booked', 'backstage-venue-manager') . ': ' . $label;
                }

                if (!isset($bookings[$date])) {
                    $bookings[$date] = array();
                }
                if (!isset($bookings[$date][$venue_id])) {
                    $bookings[$date][$venue_id] = array();
                }
                $bookings[$date][$venue_id][] = $label;

                if ($venue_id > 0) {
                    $venue_ids_seen[$venue_id] = true;
                }
            }
        }

        // Venue name map (only venues seen in bookings)
        $venue_name = array();
        if (!empty($venue_ids_seen)) {
            $venue_posts = get_posts(array(
                'post_type'      => 'vms_venue',
                'post_status'    => array('publish', 'draft', 'pending', 'private'),
                'posts_per_page' => -1,
                'post__in'       => array_keys($venue_ids_seen),
                'orderby'        => 'post_title',
                'order'          => 'ASC',
            ));

            foreach ($venue_posts as $p) {
                $venue_name[(int) $p->ID] = (string) $p->post_title;
            }
        }

        // ------------------------------------------------------------------
        // Render (month accordion + grid)
        // ------------------------------------------------------------------
        $today = wp_date('Y-m-d', time(), wp_timezone());
        echo '<div class="vms-av-allvendors-wrap">';

        foreach ($months as $ym => $_unused) {
            $month_tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
            $month_dt = DateTimeImmutable::createFromFormat('!Y-m', $ym, $month_tz);
            $month_label = ($month_dt instanceof DateTimeImmutable)
                ? wp_date('F Y', $month_dt->getTimestamp(), $month_tz)
                : $ym;

            $matrix = function_exists('vms_av_build_month_matrix')
                ? vms_av_build_month_matrix($ym)
                : array();

            $month_is_open = ($ym === wp_date('Y-m'));

            echo '<details class="vms-sch-month vms-av-method"';
            if ($month_is_open) {
                echo ' open';
            }
            echo '>';
            echo '<summary>';
            echo '<span class="vms-sch-monthlabel">' . esc_html($month_label) . '</span>';
            echo '</summary>';

            echo '<table class="widefat vms-av-grid vms-sch-grid">';
            echo '<thead><tr class="vms-av-dow">';
            $dow = array(__('Sun', 'backstage-venue-manager'), __('Mon', 'backstage-venue-manager'), __('Tue', 'backstage-venue-manager'), __('Wed', 'backstage-venue-manager'), __('Thu', 'backstage-venue-manager'), __('Fri', 'backstage-venue-manager'), __('Sat', 'backstage-venue-manager'));
            foreach ($dow as $d) {
                echo '<th class="vms-sch-dow">' . esc_html($d) . '</th>';
            }
            echo '</tr></thead>';
            echo '<tbody>';

            foreach ($matrix as $week) {
                echo '<tr>';
                foreach ($week as $cell) {
                    $date = isset($cell['date']) ? (string) $cell['date'] : '';
                    $day  = isset($cell['day']) ? (int) $cell['day'] : 0;

                    if ($date === '' || $day <= 0) {
                        echo '<td class="vms-av-empty"></td>';
                        continue;
                    }

                    $cell_classes = array();

                    if ($today !== '' && $date < $today) $cell_classes[] = 'is-past';
                    if ($today !== '' && $date === $today) $cell_classes[] = 'is-today';

                    if (!empty($cell_classes)) {
                        echo '<td class="' . esc_attr(implode(' ', $cell_classes)) . '">';
                    } else {
                        echo '<td>';
                    }
                    echo '<div class="vms-sch-cell">';
                    echo '<div class="vms-sch-daynum">' . esc_html((string) $day) . '</div>';

                    if (!empty($bookings[$date]) && is_array($bookings[$date])) {
                        // Group by venue (like admin All Venues)
                        foreach ($bookings[$date] as $venue_id => $labels) {
                            $venue_id = (int) $venue_id;

                            $vlabel = '';
                            if ($venue_id > 0 && isset($venue_name[$venue_id]) && $venue_name[$venue_id] !== '') {
                                $vlabel = $venue_name[$venue_id];
                            } elseif ($venue_id > 0) {
                                $vlabel = __('(Venue)', 'backstage-venue-manager');
                            } else {
                                $vlabel = __('(No venue)', 'backstage-venue-manager');
                            }

                            echo '<div class="vms-sch-planline">';
                            echo '<span class="vms-sch-venue-tag">' . esc_html($vlabel) . '</span>';

                            if (is_array($labels)) {
                                foreach ($labels as $lbl) {
                                    $lbl = trim((string) $lbl);
                                    if ($lbl === '') continue;
                                    echo '<div class="vms-sch-planitem">' . esc_html($lbl) . '</div>';
                                }
                            }

                            echo '</div>';
                        }
                    }

                    echo '</div>'; // cell
                    echo '</td>';
                }
                echo '</tr>';
            }

            echo '</tbody></table>';
            echo '</details>';
        }

        echo '</div>'; // wrap

    }
}
/**
 * Group a list of YYYY-MM-DD dates into [YYYY-MM => [dates. . .]].
 */
if (!function_exists('vms_av_group_dates_by_month')) {
    function vms_av_group_dates_by_month(array $dates): array
    {
        $out = array();
        foreach ($dates as $d) {
            $ym = substr($d, 0, 7);
            if (!isset($out[$ym])) $out[$ym] = array();
            $out[$ym][] = $d;
        }
        ksort($out);
        return $out;
    }
}

/**
 * Build a calendar matrix for a month (YYYY-MM).
 * Week starts Sunday.
 */
if (!function_exists('vms_av_build_month_matrix')) {
    function vms_av_build_month_matrix(string $ym): array
    {
        $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
        $first_dt = DateTimeImmutable::createFromFormat('!Y-m', $ym, $tz);
        if (!$first_dt instanceof DateTimeImmutable) return array();

        $days_in_month = (int) $first_dt->format('t');
        $first_wday    = (int) $first_dt->format('w'); // 0=Sun..6=Sat

        $weeks = array();
        $week  = array();

        for ($i = 0; $i < $first_wday; $i++) {
            $week[] = array('date' => null, 'day' => null);
        }

        for ($day = 1; $day <= $days_in_month; $day++) {
            $date = sprintf('%s-%02d', $ym, $day);
            $week[] = array('date' => $date, 'day' => $day);

            if (count($week) === 7) {
                $weeks[] = $week;
                $week = array();
            }
        }

        if (!empty($week)) {
            while (count($week) < 7) {
                $week[] = array('date' => null, 'day' => null);
            }
            $weeks[] = $week;
        }

        return $weeks;
    }
}

/* ==========================================================
 * Tech Docs (procedural) — FIXED (no “. . . .”, no broken echo)
 * ========================================================== */

if (!function_exists('vms_vendor_portal_tech_doc_allowed_mimes')) {
    function vms_vendor_portal_tech_doc_allowed_mimes(): array
    {
        return array(
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
        );
    }
}

if (!function_exists('vms_vendor_portal_tech_doc_max_bytes')) {
    function vms_vendor_portal_tech_doc_max_bytes(): int
    {
        $configured = 15 * 1024 * 1024;
        $wp_limit = (int) wp_max_upload_size();
        if ($wp_limit > 0) {
            return max(1, min($configured, $wp_limit));
        }

        return $configured;
    }
}

if (!function_exists('vms_vendor_portal_tech_doc_meta_key')) {
    function vms_vendor_portal_tech_doc_meta_key(string $doc_key): string
    {
        if ($doc_key === 'input_list') {
            return '_vms_input_list_attachment_id';
        }

        return '_vms_stage_plot_attachment_id';
    }
}

if (!function_exists('vms_vendor_portal_tech_doc_storage_kind_meta_key')) {
    function vms_vendor_portal_tech_doc_storage_kind_meta_key(string $doc_key): string
    {
        if ($doc_key === 'input_list') {
            return '_vms_input_list_storage_kind';
        }

        return '_vms_stage_plot_storage_kind';
    }
}

if (!function_exists('vms_vendor_portal_tech_doc_label')) {
    function vms_vendor_portal_tech_doc_label(string $doc_key): string
    {
        return $doc_key === 'input_list'
            ? __('Input list', 'backstage-venue-manager')
            : __('Stage plot', 'backstage-venue-manager');
    }
}

if (!function_exists('vms_vendor_portal_tech_doc_download_url')) {
    function vms_vendor_portal_tech_doc_download_url(int $vendor_id, string $doc_key, int $plan_id = 0): string
    {
        $vendor_id = absint($vendor_id);
        $doc_key = sanitize_key($doc_key);
        $plan_id = absint($plan_id);

        return wp_nonce_url(
            add_query_arg(
                array(
                    'action' => 'vms_vendor_portal_download_tech_doc',
                    'vendor_id' => $vendor_id,
                    'doc_key' => $doc_key,
                    'plan_id' => $plan_id,
                ),
                admin_url('admin-post.php')
            ),
            'vms_vendor_portal_download_tech_doc_' . $vendor_id . '_' . $doc_key . '_' . $plan_id
        );
    }
}

if (!function_exists('vms_vendor_portal_tech_doc_payload')) {
    /**
     * @return array<string,string|int>|WP_Error
     */
    function vms_vendor_portal_tech_doc_payload(int $vendor_id, string $doc_key)
    {
        $vendor_id = absint($vendor_id);
        $doc_key = sanitize_key($doc_key);
        if ($vendor_id <= 0 || !in_array($doc_key, array('stage_plot', 'input_list'), true)) {
            return new WP_Error('tech_doc_missing', __('Requested file is not available.', 'backstage-venue-manager'));
        }

        $meta_key = vms_vendor_portal_tech_doc_meta_key($doc_key);
        $storage_key = vms_vendor_portal_tech_doc_storage_kind_meta_key($doc_key);
        $file_id = absint(get_post_meta($vendor_id, $meta_key, true));
        if ($file_id <= 0) {
            return new WP_Error('tech_doc_missing', __('Requested file is not available.', 'backstage-venue-manager'));
        }

        $storage_kind = sanitize_key((string) get_post_meta($vendor_id, $storage_key, true));
        if ($storage_kind === 'private_file') {
            $row = function_exists('bvmgr_private_file_get') ? bvmgr_private_file_get($file_id) : null;
            if (!is_array($row)) {
                return new WP_Error('tech_doc_missing', __('Requested file is not available.', 'backstage-venue-manager'));
            }

            $path = function_exists('bvmgr_private_file_path') ? bvmgr_private_file_path((string) ($row['stored_filename'] ?? '')) : '';
            if ($path === '' || !function_exists('bvmgr_private_files_path_is_safe') || !bvmgr_private_files_path_is_safe($path)) {
                return new WP_Error('tech_doc_missing', __('Requested file is not available.', 'backstage-venue-manager'));
            }

            return array(
                'path' => $path,
                'mime' => (string) ($row['mime_type'] ?? 'application/octet-stream'),
                'filename' => (string) ($row['original_filename'] ?? $doc_key),
                'storage_kind' => 'private_file',
                'file_id' => $file_id,
            );
        }

        return function_exists('bvmgr_private_files_attachment_payload')
            ? bvmgr_private_files_attachment_payload($file_id)
            : new WP_Error('tech_doc_missing', __('Requested file is not available.', 'backstage-venue-manager'));
    }
}

if (!function_exists('vms_vendor_portal_store_tech_doc_upload')) {
    /**
     * @return int|WP_Error
     */
    function vms_vendor_portal_store_tech_doc_upload(int $vendor_id, string $field_name)
    {
        $vendor_id = absint($vendor_id);
        $field_name = trim($field_name);
        $upload = vms_vendor_portal_read_uploaded_file_request($field_name);
        if (is_wp_error($upload)) {
            return $upload;
        }

        $allowed_mimes = vms_vendor_portal_tech_doc_allowed_mimes();
        $validated = bvmgr_validate_uploaded_file(
            $upload,
            array(
                'allowed_mimes' => $allowed_mimes,
                'max_bytes' => vms_vendor_portal_tech_doc_max_bytes(),
                'type_message' => __('Please upload a PDF, JPG, PNG, or WEBP file.', 'backstage-venue-manager'),
                'empty_message' => __('The uploaded file is empty.', 'backstage-venue-manager'),
                'too_large_message' => __('The uploaded file is too large.', 'backstage-venue-manager'),
                'tmp_invalid_message' => __('The uploaded file could not be verified.', 'backstage-venue-manager'),
            )
        );
        if (is_wp_error($validated)) {
            return $validated;
        }

        return function_exists('bvmgr_private_files_store_validated_upload')
            ? bvmgr_private_files_store_validated_upload(
                $validated,
                array(
                    'allowed_mimes' => $allowed_mimes,
                    'bucket' => 'vendor-tech-docs',
                    'related_post_type' => 'vms_vendor',
                    'related_post_id' => $vendor_id,
                )
            )
            : new WP_Error('tech_doc_storage_unavailable', __('Private document storage is unavailable.', 'backstage-venue-manager'));
    }
}

if (!function_exists('vms_vendor_portal_user_can_download_tech_doc')) {
    function vms_vendor_portal_user_can_download_tech_doc(int $vendor_id, string $doc_key, int $plan_id = 0): bool
    {
        $vendor_id = absint($vendor_id);
        $plan_id = absint($plan_id);
        $doc_key = sanitize_key($doc_key);
        if ($vendor_id <= 0 || !in_array($doc_key, array('stage_plot', 'input_list'), true)) {
            return false;
        }

        if (current_user_can('edit_post', $vendor_id)) {
            return true;
        }
        if (!is_user_logged_in()) {
            return false;
        }

        $user_id = get_current_user_id();
        if (function_exists('vms_user_can_access_vendor') && vms_user_can_access_vendor($user_id, $vendor_id)) {
            return true;
        }
        if (function_exists('vms_get_active_vendor_ids_for_user')) {
            $vendor_ids = array_map('absint', (array) vms_get_active_vendor_ids_for_user($user_id));
            if (in_array($vendor_id, $vendor_ids, true)) {
                return true;
            }
        }

        if ($plan_id > 0 && function_exists('vms_staff_portal_get_assignment_rows') && function_exists('vms_staff_portal_assignment_can_view_docs')) {
            $staff_id = (int) get_user_meta($user_id, '_vms_staff_id', true);
            if ($staff_id > 0) {
                $assignments = (array) vms_staff_portal_get_assignment_rows($staff_id, 250);
                foreach ($assignments as $assignment) {
                    if (!is_array($assignment)) {
                        continue;
                    }
                    if (absint($assignment['event_plan_id'] ?? 0) !== $plan_id) {
                        continue;
                    }
                    if (vms_staff_portal_assignment_can_view_docs($assignment)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}

if (!function_exists('vms_vendor_portal_download_tech_doc_handler')) {
    function vms_vendor_portal_download_tech_doc_handler(): void
    {
        $vendor_id = isset($_GET['vendor_id']) && !is_array($_GET['vendor_id']) ? absint($_GET['vendor_id']) : 0;
        $doc_key = isset($_GET['doc_key']) && !is_array($_GET['doc_key']) ? sanitize_key((string) $_GET['doc_key']) : '';
        $plan_id = isset($_GET['plan_id']) && !is_array($_GET['plan_id']) ? absint($_GET['plan_id']) : 0;
        if ($vendor_id <= 0 || !in_array($doc_key, array('stage_plot', 'input_list'), true)) {
            wp_die(esc_html__('Requested file is not available.', 'backstage-venue-manager'));
        }

        check_admin_referer('vms_vendor_portal_download_tech_doc_' . $vendor_id . '_' . $doc_key . '_' . $plan_id);
        if (!vms_vendor_portal_user_can_download_tech_doc($vendor_id, $doc_key, $plan_id)) {
            wp_die(esc_html__('You do not have permission to download this file.', 'backstage-venue-manager'));
        }

        $payload = vms_vendor_portal_tech_doc_payload($vendor_id, $doc_key);
        if (is_wp_error($payload)) {
            wp_die(esc_html($payload->get_error_message()));
        }

        $mime = trim((string) ($payload['mime'] ?? ''));
        $allowed_mimes = array_values(vms_vendor_portal_tech_doc_allowed_mimes());
        if (!in_array($mime, $allowed_mimes, true)) {
            $mime = 'application/octet-stream';
        }

        bvmgr_private_files_stream_path(
            (string) ($payload['path'] ?? ''),
            (string) ($payload['filename'] ?? 'tech-doc'),
            $mime
        );
    }
}
add_action('admin_post_vms_vendor_portal_download_tech_doc', 'vms_vendor_portal_download_tech_doc_handler');

if (!function_exists('vms_vendor_portal_render_tech_docs')) {
    function vms_vendor_portal_render_tech_docs($vendor_id)
    {
        $vendor_id = (int) $vendor_id;

        echo '<h3>' . esc_html__('Tech Docs', 'backstage-venue-manager') . '</h3>';
        echo '<p class="vms-muted">' . esc_html__('Upload your current stage plot and input list (PDF or image). You can replace them any time.', 'backstage-venue-manager') . '</p>';

        // Handle uploads
        if (bvmgr_request_method() === 'post' && isset($_POST['vms_techdocs_save'])) {
            $nonce = (isset($_POST['vms_techdocs_nonce']) && !is_array($_POST['vms_techdocs_nonce']))
                ? sanitize_text_field(wp_unslash((string) $_POST['vms_techdocs_nonce']))
                : '';
            if ($nonce === '' || !wp_verify_nonce($nonce, 'vms_techdocs_save')) {
                echo wp_kses_post(bvmgr_portal_notice('error', __('Security check failed.', 'backstage-venue-manager')));
            } else {

                $updated = false;

                if (bvmgr_upload_request_has_file($_FILES, 'vms_stage_plot')) {
                    $previous_id = (int) get_post_meta($vendor_id, '_vms_stage_plot_attachment_id', true);
                    $previous_kind = sanitize_key((string) get_post_meta($vendor_id, '_vms_stage_plot_storage_kind', true));
                    $file_id = vms_vendor_portal_store_tech_doc_upload($vendor_id, 'vms_stage_plot');
                    if (!is_wp_error($file_id)) {
                        update_post_meta($vendor_id, '_vms_stage_plot_attachment_id', (int) $file_id);
                        update_post_meta($vendor_id, '_vms_stage_plot_storage_kind', 'private_file');
                        if ($previous_kind === 'private_file' && $previous_id > 0 && $previous_id !== (int) $file_id && function_exists('bvmgr_private_files_delete')) {
                            bvmgr_private_files_delete($previous_id);
                        }
                        $updated = true;
                    } else {
                        /* translators: %s: media upload error message. */
                        echo wp_kses_post(bvmgr_portal_notice('error', sprintf(__('Stage plot upload failed: %s', 'backstage-venue-manager'), $file_id->get_error_message())));
                    }
                }

                if (bvmgr_upload_request_has_file($_FILES, 'vms_input_list')) {
                    $previous_id = (int) get_post_meta($vendor_id, '_vms_input_list_attachment_id', true);
                    $previous_kind = sanitize_key((string) get_post_meta($vendor_id, '_vms_input_list_storage_kind', true));
                    $file_id = vms_vendor_portal_store_tech_doc_upload($vendor_id, 'vms_input_list');
                    if (!is_wp_error($file_id)) {
                        update_post_meta($vendor_id, '_vms_input_list_attachment_id', (int) $file_id);
                        update_post_meta($vendor_id, '_vms_input_list_storage_kind', 'private_file');
                        if ($previous_kind === 'private_file' && $previous_id > 0 && $previous_id !== (int) $file_id && function_exists('bvmgr_private_files_delete')) {
                            bvmgr_private_files_delete($previous_id);
                        }
                        $updated = true;
                    } else {
                        /* translators: %s: media upload error message. */
                        echo wp_kses_post(bvmgr_portal_notice('error', sprintf(__('Input list upload failed: %s', 'backstage-venue-manager'), $file_id->get_error_message())));
                    }
                }

                if ($updated) {
                    vms_vendor_flag_vendor_update($vendor_id, 'tech_docs');
                    echo wp_kses_post(bvmgr_portal_notice('success', __('Tech docs updated.', 'backstage-venue-manager')));
                }
            }
        }

        $stage_id = (int) get_post_meta($vendor_id, '_vms_stage_plot_attachment_id', true);
        $input_id = (int) get_post_meta($vendor_id, '_vms_input_list_attachment_id', true);

        $stage_url = $stage_id ? vms_vendor_portal_tech_doc_download_url($vendor_id, 'stage_plot') : '';
        $input_url = $input_id ? vms_vendor_portal_tech_doc_download_url($vendor_id, 'input_list') : '';

        echo '<div class="vms-portal-card">';
        echo '<ul class="vms-m0 vms-pl-18">';
        echo '<li><strong>' . esc_html__('Stage Plot:', 'backstage-venue-manager') . '</strong> ' . ($stage_url ? '<a target="_blank" rel="noopener" href="' . esc_url($stage_url) . '">' . esc_html__('Download current', 'backstage-venue-manager') . '</a>' : esc_html__('None uploaded', 'backstage-venue-manager')) . '</li>';
        echo '<li><strong>' . esc_html__('Input List:', 'backstage-venue-manager') . '</strong> ' . ($input_url ? '<a target="_blank" rel="noopener" href="' . esc_url($input_url) . '">' . esc_html__('Download current', 'backstage-venue-manager') . '</a>' : esc_html__('None uploaded', 'backstage-venue-manager')) . '</li>';
        echo '</ul>';
        echo '</div>';

        echo '<div class="vms-portal-card">';
        echo '<form method="post" enctype="multipart/form-data">';
        wp_nonce_field('vms_techdocs_save', 'vms_techdocs_nonce');

        echo '<div class="vms-field">';
        echo '<label><strong>' . esc_html__('Upload / Replace Stage Plot', 'backstage-venue-manager') . '</strong></label>';
        echo '<input type="file" name="vms_stage_plot" accept=".pdf,.png,.jpg,.jpeg,.webp">';
        echo '</div>';

        echo '<div class="vms-field">';
        echo '<label><strong>' . esc_html__('Upload / Replace Input List', 'backstage-venue-manager') . '</strong></label>';
        echo '<input type="file" name="vms_input_list" accept=".pdf,.png,.jpg,.jpeg,.webp">';
        echo '</div>';

        echo '<p class="vms-m0"><button type="submit" name="vms_techdocs_save" class="button button-primary">' . esc_html__('Save Tech Docs', 'backstage-venue-manager') . '</button></p>';
        echo '</form>';
        echo '</div>';
    }
}

if (!function_exists('vms_vendor_portal_render_profile')) {
    function vms_vendor_portal_render_profile($vendor_id)
    {
        $vendor_id = (int) $vendor_id;

        if ($vendor_id <= 0) {
            echo wp_kses_post(bvmgr_portal_notice('error', __('Invalid vendor.', 'backstage-venue-manager')));
            return;
        }

        // Ensure media handling functions are available on front-end
        if (!function_exists('media_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        // Canonical meta keys (single source of truth).
        $k_contact_name  = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('vendor', 'contact_name') : '_vms_contact_name';
        $k_primary_email = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('vendor', 'primary_email') : '_vms_vendor_primary_email';
        $k_primary_phone = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('vendor', 'primary_phone') : '_vms_vendor_primary_phone';
        $k_website       = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('vendor', 'website') : '_vms_vendor_website';
        $k_city          = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('vendor', 'city') : '_vms_city';
        $k_state         = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('vendor', 'state') : '_vms_state';
        $social_meta_map = array(
            'facebook'  => '_vms_vendor_social_facebook',
            'instagram' => '_vms_vendor_social_instagram',
            'x'         => '_vms_vendor_social_x',
            'tiktok'    => '_vms_vendor_social_tiktok',
            'youtube'   => '_vms_vendor_social_youtube',
            'spotify'   => '_vms_vendor_social_spotify',
        );
        $featured_video_key = '_vms_vendor_featured_video_url';

        $vendor_post = get_post($vendor_id);

        // Save handler
        if (bvmgr_request_method() === 'post' && isset($_POST['vms_vendor_profile_save'])) {

            $nonce = (isset($_POST['vms_vendor_profile_nonce']) && !is_array($_POST['vms_vendor_profile_nonce']))
                ? sanitize_text_field(wp_unslash((string) $_POST['vms_vendor_profile_nonce']))
                : '';
            if ($nonce === '' || !wp_verify_nonce($nonce, 'vms_vendor_profile_save')) {
                echo wp_kses_post(bvmgr_portal_notice('error', __('Security check failed.', 'backstage-venue-manager')));
            } else {

                $display_name  = bvmgr_request_read_text_field($_POST, 'vms_vendor_display_name');
                $about         = wp_kses_post(bvmgr_request_read_scalar($_POST, 'vms_vendor_about'));

                $contact_name  = bvmgr_request_read_text_field($_POST, 'vms_contact_name');
                $primary_email = bvmgr_request_read_email($_POST, 'vms_primary_email');
                $primary_phone = bvmgr_request_read_text_field($_POST, 'vms_primary_phone');

                $website_url   = esc_url_raw(bvmgr_request_read_scalar($_POST, 'vms_website_url'));
                $city          = bvmgr_request_read_text_field($_POST, 'vms_city');
                $state         = bvmgr_request_read_text_field($_POST, 'vms_state');
                $featured_video_url = esc_url_raw(bvmgr_request_read_scalar($_POST, 'vms_vendor_featured_video_url'));
                $social_values = array();
                $posted_social = vms_vendor_portal_post_array('vms_vendor_social');
                foreach ($social_meta_map as $social_slug => $social_meta_key) {
                    $social_raw = isset($posted_social[$social_slug]) && is_scalar($posted_social[$social_slug])
                        ? trim((string) $posted_social[$social_slug])
                        : '';
                    $social_values[$social_meta_key] = esc_url_raw($social_raw);
                }
                $gallery_urls = array();
                for ($i = 1; $i <= 5; $i++) {
                    $gallery_urls[$i] = esc_url_raw(bvmgr_request_read_scalar($_POST, 'vms_vendor_gallery_image_' . $i));
                }

                // Logo upload (sets Vendor featured image)
                if (!empty($_FILES['vms_vendor_logo']['name'])) {
                    $attach_id = function_exists('vms_vendor_portal_handle_public_image_upload')
                        ? vms_vendor_portal_handle_public_image_upload('vms_vendor_logo', 0)
                        : new WP_Error('vendor_logo_upload_unavailable', __('The logo upload handler is unavailable.', 'backstage-venue-manager'));
                    if (!is_wp_error($attach_id)) {
                        set_post_thumbnail($vendor_id, (int) $attach_id);
                    } else {
                        /* translators: %s: media upload error message. */
                        echo wp_kses_post(bvmgr_portal_notice('error', sprintf(__('Logo upload failed: %s', 'backstage-venue-manager'), $attach_id->get_error_message())));
                    }
                }

                // Update post title/content (vendor name + about).
                if ($vendor_post) {
                    $update = array('ID' => $vendor_id);

                    if ($display_name !== '') {
                        $update['post_title'] = $display_name;
                    }

                    // About can be empty (allow clearing).
                    $update['post_content'] = $about;

                    $r = wp_update_post($update, true);
                    if (is_wp_error($r)) {
                        /* translators: %s: WordPress post update error message. */
                        echo wp_kses_post(bvmgr_portal_notice('error', sprintf(__('Profile save failed: %s', 'backstage-venue-manager'), $r->get_error_message())));
                    }
                }

                update_post_meta($vendor_id, $k_contact_name, $contact_name);
                update_post_meta($vendor_id, $k_primary_email, $primary_email);
                update_post_meta($vendor_id, $k_primary_phone, $primary_phone);
                update_post_meta($vendor_id, $k_website, $website_url);
                update_post_meta($vendor_id, $k_city, $city);
                update_post_meta($vendor_id, $k_state, $state);

                foreach ($social_values as $social_meta_key => $social_value) {
                    if ($social_value === '') {
                        delete_post_meta($vendor_id, $social_meta_key);
                    } else {
                        update_post_meta($vendor_id, $social_meta_key, $social_value);
                    }
                }

                if ($featured_video_url === '') {
                    delete_post_meta($vendor_id, $featured_video_key);
                } else {
                    update_post_meta($vendor_id, $featured_video_key, $featured_video_url);
                }

                for ($i = 1; $i <= 5; $i++) {
                    $file_field = 'vms_vendor_gallery_upload_' . $i;
                    $meta_key   = '_vms_vendor_gallery_image_' . $i;
                    $gallery_value = $gallery_urls[$i] ?? '';

                    if (!empty($_FILES[$file_field]['name'])) {
                        $attach_id = function_exists('vms_vendor_portal_handle_public_image_upload')
                            ? vms_vendor_portal_handle_public_image_upload($file_field, 0)
                            : new WP_Error('vendor_gallery_upload_unavailable', __('The gallery upload handler is unavailable.', 'backstage-venue-manager'));
                        if (!is_wp_error($attach_id)) {
                            $uploaded_url = wp_get_attachment_url((int) $attach_id);
                            if ($uploaded_url) {
                                $gallery_value = (string) $uploaded_url;
                            }
                        } else {
                            /* translators: 1: gallery slot number, 2: media upload error message. */
                            echo wp_kses_post(bvmgr_portal_notice('error', sprintf(__('Gallery photo %1$d upload failed: %2$s', 'backstage-venue-manager'), $i, $attach_id->get_error_message())));
                        }
                    }

                    if ($gallery_value === '') {
                        delete_post_meta($vendor_id, $meta_key);
                    } else {
                        update_post_meta($vendor_id, $meta_key, $gallery_value);
                    }
                }

                // Flag update for admin review
                if (function_exists('vms_vendor_flag_vendor_update')) {
                    vms_vendor_flag_vendor_update($vendor_id, 'profile');
                }

                echo wp_kses_post(bvmgr_portal_notice('success', __('Profile saved.', 'backstage-venue-manager')));
            }
        }

        // Current values
        $vendor_post   = get_post($vendor_id);
        $display_name  = $vendor_post ? (string) $vendor_post->post_title : '';
        $about         = $vendor_post ? (string) $vendor_post->post_content : '';

        $contact_name  = (string) get_post_meta($vendor_id, $k_contact_name, true);
        $primary_email = (string) get_post_meta($vendor_id, $k_primary_email, true);
        $primary_phone = (string) get_post_meta($vendor_id, $k_primary_phone, true);
        $website_url   = (string) get_post_meta($vendor_id, $k_website, true);
        $city          = (string) get_post_meta($vendor_id, $k_city, true);
        $state         = (string) get_post_meta($vendor_id, $k_state, true);
        $featured_video_url = (string) get_post_meta($vendor_id, $featured_video_key, true);
        $social_values_current = array();
        foreach ($social_meta_map as $social_slug => $social_meta_key) {
            $social_values_current[$social_slug] = (string) get_post_meta($vendor_id, $social_meta_key, true);
        }
        $gallery_values_current = array();
        for ($i = 1; $i <= 5; $i++) {
            $gallery_values_current[$i] = (string) get_post_meta($vendor_id, '_vms_vendor_gallery_image_' . $i, true);
        }

        // Legacy fallbacks (transitional): pull existing values if canonical is empty.
        $legacy_email = (string) get_post_meta($vendor_id, '_vms_contact_email', true);
        $legacy_phone = (string) get_post_meta($vendor_id, '_vms_contact_phone', true);
        $legacy_web   = (string) get_post_meta($vendor_id, '_vms_website_url', true);
        $legacy_loc   = (string) get_post_meta($vendor_id, '_vms_vendor_location', true);

        if ($primary_email === '' && $legacy_email !== '') {
            $primary_email = $legacy_email;
        }
        if ($primary_phone === '' && $legacy_phone !== '') {
            $primary_phone = $legacy_phone;
        }
        if ($website_url === '' && $legacy_web !== '') {
            $website_url = $legacy_web;
        }
        if (($city === '' || $state === '') && $legacy_loc !== '') {
            $parts = array_map('trim', explode(',', $legacy_loc, 2));
            if ($city === '' && isset($parts[0]) && $parts[0] !== '') {
                $city = $parts[0];
            }
            if ($state === '' && isset($parts[1]) && $parts[1] !== '') {
                $state = $parts[1];
            }
        }


        $thumb_id  = get_post_thumbnail_id($vendor_id);
        $thumb_url = $thumb_id ? wp_get_attachment_image_url($thumb_id, 'medium') : '';

        echo '<h3>' . esc_html__('Profile', 'backstage-venue-manager') . '</h3>';

        echo '<form method="post" enctype="multipart/form-data">';
        wp_nonce_field('vms_vendor_profile_save', 'vms_vendor_profile_nonce');

        echo '<div class="vms-portal-card">';

        // LOGO
        echo '<h4 class="vms-mt-0">' . esc_html__('Logo', 'backstage-venue-manager') . '</h4>';

        if ($thumb_url) {
            echo '<p><img src="' . esc_url($thumb_url) . '" alt="Vendor Logo" class="vms-portal-logo-thumb"></p>';
        } else {
            echo '<p><em>' . esc_html__('No logo uploaded yet.', 'backstage-venue-manager') . '</em></p>';
        }

        echo '<div class="vms-field">';
        echo '<label><strong>' . esc_html__('Upload / Replace Logo', 'backstage-venue-manager') . '</strong></label>';
        echo '<input type="file" name="vms_vendor_logo" accept=".png,.jpg,.jpeg,.webp">';
        echo '</div>';

        echo '</div>';

        echo '<div class="vms-portal-card">';

        echo '<div class="vms-field">';
        echo '<label><strong>' . esc_html__('Display Name', 'backstage-venue-manager') . '</strong></label>';
        echo '<input type="text" name="vms_vendor_display_name" value="' . esc_attr($display_name) . '">';
        echo '</div>';

        echo '<div class="vms-field">';
        echo '<label><strong>' . esc_html__('About / Bio', 'backstage-venue-manager') . '</strong></label>';
        echo '<textarea name="vms_vendor_about" rows="7">' . esc_textarea($about) . '</textarea>';
        echo '<div class="description">' . esc_html__('This appears on your public profile.', 'backstage-venue-manager') . '</div>';
        echo '</div>';

        echo '</div>';

        echo '<div class="vms-portal-card">';

        echo '<h4 class="vms-mt-0">' . esc_html__('Contact', 'backstage-venue-manager') . '</h4>';

        echo '<div class="vms-field">';
        echo '<label><strong>' . esc_html__('Primary Contact Name (optional)', 'backstage-venue-manager') . '</strong></label>';
        echo '<input type="text" name="vms_contact_name" value="' . esc_attr($contact_name) . '">';
        echo '</div>';

        echo '<div class="vms-field">';
        echo '<label><strong>' . esc_html__('Primary Contact Email', 'backstage-venue-manager') . '</strong></label>';
        echo '<input type="email" name="vms_primary_email" value="' . esc_attr($primary_email) . '">';
        echo '</div>';

        echo '<div class="vms-field">';
        echo '<label><strong>' . esc_html__('Primary Contact Phone', 'backstage-venue-manager') . '</strong></label>';
        echo '<input type="tel" name="vms_primary_phone" id="vms_primary_phone" inputmode="tel" autocomplete="tel" placeholder="(###) ###-####" maxlength="14" value="' . esc_attr($primary_phone) . '">';
        echo '</div>';

        echo '<div class="vms-field">';
        echo '<label><strong>' . esc_html__('Website URL', 'backstage-venue-manager') . '</strong></label>';
        echo '<input type="url" name="vms_website_url" value="' . esc_attr($website_url) . '">';
        echo '</div>';

        echo '<div class="vms-field">';
        echo '<label><strong>' . esc_html__('City', 'backstage-venue-manager') . '</strong></label>';
        echo '<input type="text" name="vms_city" value="' . esc_attr($city) . '">';
        echo '</div>';

        echo '<div class="vms-field">';
        echo '<label><strong>' . esc_html__('State', 'backstage-venue-manager') . '</strong></label>';
        echo '<input type="text" name="vms_state" value="' . esc_attr($state) . '">';
        echo '</div>';

        echo '</div>';

        echo '<div class="vms-portal-card">';
        echo '<h4 class="vms-mt-0">' . esc_html__('Public Profile', 'backstage-venue-manager') . '</h4>';
        echo '<p class="description">' . esc_html__('These items appear on your public profile page when the venue has enabled your public profile.', 'backstage-venue-manager') . '</p>';

        foreach ($social_meta_map as $social_slug => $social_meta_key) {
            $social_label = ucfirst($social_slug);
            if ($social_slug === 'x') {
                $social_label = 'X / Twitter';
            } elseif ($social_slug === 'tiktok') {
                $social_label = 'TikTok';
            } elseif ($social_slug == 'youtube') {
                $social_label = 'YouTube';
            }
            echo '<div class="vms-field">';
            echo '<label><strong>' . esc_html($social_label . ' URL') . '</strong></label>';
            echo '<input type="url" name="vms_vendor_social[' . esc_attr($social_slug) . ']" value="' . esc_attr($social_values_current[$social_slug] ?? '') . '" placeholder="https://">';
            echo '</div>';
        }

        echo '<div class="vms-field">';
        echo '<label><strong>' . esc_html__('Featured video URL', 'backstage-venue-manager') . '</strong></label>';
        echo '<input type="url" name="vms_vendor_featured_video_url" value="' . esc_attr($featured_video_url) . '" placeholder="https://www.youtube.com/watch?v=...">';
        echo '<div class="description">' . esc_html__('YouTube works best. Facebook video links may work depending on your site setup.', 'backstage-venue-manager') . '</div>';
        echo '</div>';

        echo '<h4>' . esc_html__('Gallery Photos', 'backstage-venue-manager') . '</h4>';
        echo '<p class="description">' . esc_html__('Upload up to 5 photos for your public profile. You can also paste an image URL instead.', 'backstage-venue-manager') . '</p>';
        for ($i = 1; $i <= 5; $i++) {
            $photo_number = (string) $i;
            echo '<div class="vms-field">';
            echo '<label><strong>' . sprintf(
                /* translators: %s: gallery photo slot number. */
                esc_html__('Photo %s', 'backstage-venue-manager'),
                esc_html($photo_number)
            ) . '</strong></label>';
            if (!empty($gallery_values_current[$i])) {
                echo '<div class="vms-portal-gallery-preview"><img src="' . esc_url($gallery_values_current[$i]) . '" alt="' . esc_attr(sprintf(
                    /* translators: %s: gallery photo slot number. */
                    __('Photo %s preview', 'backstage-venue-manager'),
                    $photo_number
                )) . '"></div>';
            }
            echo '<input type="file" name="vms_vendor_gallery_upload_' . esc_attr((string) $i) . '" accept=".png,.jpg,.jpeg,.webp">';
            echo '<input type="url" name="vms_vendor_gallery_image_' . esc_attr((string) $i) . '" value="' . esc_attr($gallery_values_current[$i] ?? '') . '" placeholder="https://">';
            echo '</div>';
        }

        echo '<p class="vms-m0"><button type="submit" name="vms_vendor_profile_save" class="button button-primary">' . esc_html__('Save Profile', 'backstage-venue-manager') . '</button></p>';
        echo '</div>';

        echo '</form>';
    }
}


add_action('wp_ajax_vms_save_manual_availability_day', 'vms_save_manual_availability_day_ajax');

function vms_save_manual_availability_day_ajax(): void
{
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'Not logged in.'), 403);
    }

    check_ajax_referer('vms_avail_ajax', 'nonce');

    $user_id           = (int) get_current_user_id();
    $preview_vendor_id = function_exists('vms_vendor_portal_get_preview_vendor_id')
        ? (int) vms_vendor_portal_get_preview_vendor_id()
        : 0;
    $vendor_id         = $preview_vendor_id > 0
        ? $preview_vendor_id
        : (int) get_user_meta($user_id, '_vms_vendor_id', true);

    if ($vendor_id <= 0 || get_post_type($vendor_id) !== 'vms_vendor') {
        wp_send_json_error(array('message' => 'Vendor not linked.'), 400);
    }

    $date  = bvmgr_request_read_text_field($_POST, 'date');
    $state = bvmgr_request_read_text_field($_POST, 'state');

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        wp_send_json_error(array('message' => 'Invalid date.'), 400);
    }

    if (!in_array($state, array('', 'available', 'unavailable'), true)) {
        wp_send_json_error(array('message' => 'Invalid state.'), 400);
    }

    $active_dates  = function_exists('vms_vendor_get_active_dates_or_rolling_window')
        ? vms_vendor_get_active_dates_or_rolling_window(12)
        : array();

    $active_lookup = array_flip($active_dates);
    if (!isset($active_lookup[$date])) {
        wp_send_json_error(array('message' => 'Date not in active range.'), 400);
    }

    $manual = get_post_meta($vendor_id, '_vms_availability_manual', true);
    if (!is_array($manual)) $manual = array();

    if ($state === '') {
        unset($manual[$date]);
    } else {
        $manual[$date] = $state;
    }

    update_post_meta($vendor_id, '_vms_availability_manual', $manual);
    update_post_meta($vendor_id, '_vms_availability_preferred_method', 'manual');

    if (function_exists('vms_vendor_flag_vendor_update')) {
        vms_vendor_flag_vendor_update($vendor_id, 'availability_manual');
    }

    wp_send_json_success(array(
        'date'  => $date,
        'state' => $state
    ));
}
