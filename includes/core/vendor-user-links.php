<?php
defined('ABSPATH') || exit;

/**
 * ==========================================================
 * VMS — Vendor ↔ WP User Links (Shared / REST-safe)
 * ==========================================================
 *
 * Purpose:
 * - Many-to-many mapping between Vendor (vms_vendor post) and WP users.
 * - One user can manage multiple vendors; one vendor can have multiple user managers.
 * - Includes role + status + audit trail.
 *
 * Backwards compatibility:
 * - User meta:  _vms_vendor_id (primary/default vendor for portal convenience)
 * - Vendor meta: _vms_vendor_user_id (primary contact user for vendor)
 *
 * Storage (authoritative):
 * - DB table: {$wpdb->prefix}vms_vendor_user_links
 */

if (!defined('BVMGR_USER_PRIMARY_VENDOR_META_KEY')) {
    define('BVMGR_USER_PRIMARY_VENDOR_META_KEY', '_vms_vendor_id');
}

if (!defined('BVMGR_VENDOR_PRIMARY_USER_META_KEY')) {
    define('BVMGR_VENDOR_PRIMARY_USER_META_KEY', '_vms_vendor_user_id');
}

/**
 * Legacy alias kept for existing code references.
 * This refers to the vendor post_meta key that stores the vendor's PRIMARY contact user ID.
 */
if (!defined('BVMGR_VENDOR_USER_META_KEY')) {
    define('BVMGR_VENDOR_USER_META_KEY', BVMGR_VENDOR_PRIMARY_USER_META_KEY);
}

if (!defined('BVMGR_DB_TABLE_VENDOR_USER_LINKS_SUFFIX')) {
    define('BVMGR_DB_TABLE_VENDOR_USER_LINKS_SUFFIX', 'vms_vendor_user_links');
}

/**
 * Table name (with WP prefix).
 */
function vms_vendor_user_links_table(): string
{
    global $wpdb;
    return $wpdb->prefix . BVMGR_DB_TABLE_VENDOR_USER_LINKS_SUFFIX;
}

function vms_vendor_user_links_table_exists(): bool
{
    global $wpdb;
    $t = vms_vendor_user_links_table();
    $like = $wpdb->esc_like($t);
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- This request-local schema probe gates the authoritative link repository; stale cached absence would incorrectly force legacy authorization fallbacks after migrations.
    $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $like));
    return is_string($found) && $found === $t;
}

/**
 * Normalize/validate link role.
 */
function vms_vendor_user_link_normalize_role(string $role): string
{
    $role = sanitize_key($role);
    if ($role === '') $role = 'manager';

    $allowed = array(
        'owner',
        'primary_contact',
        'manager',
        'agent',
        'accountant',
        'viewer',
    );

    if (!in_array($role, $allowed, true)) {
        $role = 'manager';
    }
    return $role;
}

/**
 * Normalize/validate link status.
 */
function vms_vendor_user_link_normalize_status(string $status): string
{
    $status = sanitize_key($status);
    if ($status === '') $status = 'active';

    $allowed = array(
        'active',
        'pending',
        'disabled',
    );

    if (!in_array($status, $allowed, true)) {
        $status = 'active';
    }
    return $status;
}

/**
 * Return link rows for a user.
 *
 * Each row:
 * - vendor_id (int)
 * - user_role (string)
 * - link_status (string)
 * - is_primary (int 0/1)
 */
function vms_vendor_user_links_get_by_user(int $user_id, bool $include_inactive = false): array
{
    $user_id = (int) $user_id;
    if ($user_id <= 0) return array();

    if (!vms_vendor_user_links_table_exists()) {
        return vms_vendor_user_links_get_by_user_legacy($user_id);
    }

    global $wpdb;
    $t = vms_vendor_user_links_table();

    if ($include_inactive) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Vendor/user authorization reads target the plugin-owned link repository with prepared identifier/value placeholders and must observe request-fresh link mutations.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT vendor_id, user_role, link_status, is_primary FROM %i WHERE user_id = %d ORDER BY is_primary DESC, vendor_id ASC',
                $t,
                $user_id
            ),
            ARRAY_A
        );
    } else {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Active vendor/user authorization reads target the plugin-owned link repository with prepared identifier/value placeholders and must observe request-fresh link mutations.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT vendor_id, user_role, link_status, is_primary FROM %i WHERE user_id = %d AND link_status = %s ORDER BY is_primary DESC, vendor_id ASC',
                $t,
                $user_id,
                'active'
            ),
            ARRAY_A
        );
    }

    if (!is_array($rows)) return array();

    $out = array();
    foreach ($rows as $r) {
        $out[] = array(
            'vendor_id'   => isset($r['vendor_id']) ? (int) $r['vendor_id'] : 0,
            'user_role'   => isset($r['user_role']) ? (string) $r['user_role'] : '',
            'link_status' => isset($r['link_status']) ? (string) $r['link_status'] : '',
            'is_primary'  => isset($r['is_primary']) ? (int) $r['is_primary'] : 0,
        );
    }

    return $out;
}

/**
 * Legacy fallback:
 * - User meta _vms_vendor_id
 * - Vendor meta _vms_vendor_user_id (reverse lookup)
 */
function vms_vendor_user_links_get_by_user_legacy(int $user_id): array
{
    $user_id = (int) $user_id;
    if ($user_id <= 0) return array();

    $rows = array();

    // 1) User meta pointer (primary vendor)
    $primary_vendor = (int) get_user_meta($user_id, BVMGR_USER_PRIMARY_VENDOR_META_KEY, true);
    if ($primary_vendor > 0) {
        $rows[] = array(
            'vendor_id'   => $primary_vendor,
            'user_role'   => 'primary_contact',
            'link_status' => 'active',
            'is_primary'  => 1,
        );
    }

    // 2) Reverse lookup: vendors whose _vms_vendor_user_id matches this user
    $q = new WP_Query(array(
        'post_type'      => 'vms_vendor',
        'post_status'    => array('publish', 'draft', 'private'),
        'fields'         => 'ids',
        'posts_per_page' => -1,
        'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Compatibility fallback runs only when the authoritative link table is unavailable and must honor the legacy vendor-primary-user pointer.
            array(
                'key'   => BVMGR_VENDOR_PRIMARY_USER_META_KEY,
                'value' => (string) $user_id,
            ),
        ),
        'no_found_rows'  => true,
    ));

    if (!empty($q->posts)) {
        foreach ($q->posts as $vid) {
            $vid = (int) $vid;
            if ($vid <= 0) continue;

            $already = false;
            foreach ($rows as $r) {
                if ((int) $r['vendor_id'] === $vid) { $already = true; break; }
            }
            if ($already) continue;

            $rows[] = array(
                'vendor_id'   => $vid,
                'user_role'   => 'primary_contact',
                'link_status' => 'active',
                'is_primary'  => ($primary_vendor === $vid) ? 1 : 0,
            );
        }
    }

    return $rows;
}

/**
 * Active vendor IDs for a user (convenience).
 */
function vms_get_active_vendor_ids_for_user(int $user_id): array
{
    $rows = vms_vendor_user_links_get_by_user($user_id, false);
    $ids = array();
    foreach ($rows as $r) {
        $vid = isset($r['vendor_id']) ? (int) $r['vendor_id'] : 0;
        if ($vid > 0) $ids[] = $vid;
    }
    $ids = array_values(array_unique(array_map('intval', $ids)));

    // Keep only vendors that actually exist.
    $out = array();
    foreach ($ids as $vid) {
        $p = get_post($vid);
        if ($p && $p->post_type === 'vms_vendor') {
            $out[] = (int) $vid;
        }
    }

    return $out;
}

/**
 * Can user access/manage this vendor (active link required).
 */
function vms_user_can_access_vendor(int $user_id, int $vendor_id): bool
{
    $user_id = (int) $user_id;
    $vendor_id = (int) $vendor_id;
    if ($user_id <= 0 || $vendor_id <= 0) return false;

    $rows = vms_vendor_user_links_get_by_user($user_id, false);
    foreach ($rows as $r) {
        if ((int) $r['vendor_id'] === $vendor_id) {
            return true;
        }
    }
    return false;
}

/**
 * Primary/default vendor for a user.
 *
 * Logic:
 * 1) If user meta pointer exists and user has access to that vendor, use it.
 * 2) Else if table has an is_primary row (active), use it and sync user meta.
 * 3) Else use first active vendor and sync user meta + mark is_primary when possible.
 */
function vms_get_primary_vendor_id_for_user(int $user_id): int
{
    $user_id = (int) $user_id;
    if ($user_id <= 0) return 0;

    $vendor_ids = vms_get_active_vendor_ids_for_user($user_id);
    if (!$vendor_ids) return 0;

    $meta_vid = (int) get_user_meta($user_id, BVMGR_USER_PRIMARY_VENDOR_META_KEY, true);
    if ($meta_vid > 0 && in_array($meta_vid, $vendor_ids, true)) {
        return (int) $meta_vid;
    }

    // Try is_primary from table
    if (vms_vendor_user_links_table_exists()) {
        $rows = vms_vendor_user_links_get_by_user($user_id, false);
        foreach ($rows as $r) {
            if (!empty($r['is_primary']) && (int) $r['vendor_id'] > 0) {
                $vid = (int) $r['vendor_id'];
                if (in_array($vid, $vendor_ids, true)) {
                    // Sync legacy pointer
                    update_user_meta($user_id, BVMGR_USER_PRIMARY_VENDOR_META_KEY, $vid);
                    return $vid;
                }
            }
        }
    }

    // Fall back to first
    $vid = (int) $vendor_ids[0];
    if ($vid > 0) {
        update_user_meta($user_id, BVMGR_USER_PRIMARY_VENDOR_META_KEY, $vid);
        if (vms_vendor_user_links_table_exists()) {
            vms_vendor_user_links_set_primary_for_user($user_id, $vid, 0);
        }
    }
    return $vid;
}

/**
 * Set user's primary vendor (updates both user_meta pointer + table is_primary flag).
 */
function vms_vendor_user_links_set_primary_for_user(int $user_id, int $vendor_id, int $actor_user_id = 0): bool
{
    $user_id = (int) $user_id;
    $vendor_id = (int) $vendor_id;

    if ($user_id <= 0 || $vendor_id <= 0) return false;
    if (!vms_user_can_access_vendor($user_id, $vendor_id)) return false;

    // Always sync legacy pointer
    update_user_meta($user_id, BVMGR_USER_PRIMARY_VENDOR_META_KEY, $vendor_id);

    if (!vms_vendor_user_links_table_exists()) return true;

    global $wpdb;
    $t = vms_vendor_user_links_table();
    $now = current_time('mysql', true);

    // Clear existing primaries for this user
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Primary reassignment writes the plugin-owned link repository directly so the following set operation observes the cleared state in the same request.
    $wpdb->query(
        $wpdb->prepare(
            'UPDATE %i SET is_primary = 0, updated_at = %s, updated_by = %d WHERE user_id = %d',
            $t,
            $now,
            (int) $actor_user_id,
            $user_id
        )
    );

    // Set for the requested vendor
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Primary reassignment immediately writes the selected plugin-owned link row after clearing prior primaries; no persistent cache spans this ordered mutation pair.
    $updated = $wpdb->query(
        $wpdb->prepare(
            'UPDATE %i SET is_primary = 1, updated_at = %s, updated_by = %d WHERE user_id = %d AND vendor_id = %d',
            $t,
            $now,
            (int) $actor_user_id,
            $user_id,
            $vendor_id
        )
    );

    return ($updated !== false);
}

/**
 * Get link rows for a vendor (admin UI support).
 */
function vms_vendor_user_links_get_by_vendor(int $vendor_id, bool $include_inactive = true): array
{
    $vendor_id = (int) $vendor_id;
    if ($vendor_id <= 0) return array();

    if (!vms_vendor_user_links_table_exists()) {
        $uid = (int) get_post_meta($vendor_id, BVMGR_VENDOR_PRIMARY_USER_META_KEY, true);
        if ($uid > 0) {
            return array(
                array(
                    'user_id'     => $uid,
                    'user_role'   => 'primary_contact',
                    'link_status' => 'active',
                ),
            );
        }
        return array();
    }

    global $wpdb;
    $t = vms_vendor_user_links_table();

    if ($include_inactive) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Vendor link administration reads the plugin-owned repository with prepared identifier/value placeholders and must reflect request-fresh link changes.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT user_id, user_role, link_status FROM %i WHERE vendor_id = %d ORDER BY link_status ASC, user_role ASC, user_id ASC',
                $t,
                $vendor_id
            ),
            ARRAY_A
        );
    } else {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Active vendor link administration reads the plugin-owned repository with prepared identifier/value placeholders and must reflect request-fresh link changes.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT user_id, user_role, link_status FROM %i WHERE vendor_id = %d AND link_status = %s ORDER BY link_status ASC, user_role ASC, user_id ASC',
                $t,
                $vendor_id,
                'active'
            ),
            ARRAY_A
        );
    }

    if (!is_array($rows)) return array();

    $out = array();
    foreach ($rows as $r) {
        $out[] = array(
            'user_id'     => isset($r['user_id']) ? (int) $r['user_id'] : 0,
            'user_role'   => isset($r['user_role']) ? (string) $r['user_role'] : '',
            'link_status' => isset($r['link_status']) ? (string) $r['link_status'] : '',
        );
    }

    return $out;
}


if (!function_exists('vms_vendor_user_link_exists')) {
    function vms_vendor_user_link_exists(int $vendor_id, int $user_id): bool
    {
        $vendor_id = (int) $vendor_id;
        $user_id = (int) $user_id;
        if ($vendor_id <= 0 || $user_id <= 0) {
            return false;
        }

        foreach ((array) vms_vendor_user_links_get_by_vendor($vendor_id, true) as $row) {
            if ((int) ($row['user_id'] ?? 0) === $user_id) {
                return true;
            }
        }

        return ((int) get_user_meta($user_id, BVMGR_USER_PRIMARY_VENDOR_META_KEY, true) === $vendor_id);
    }
}

if (!function_exists('vms_vendor_user_link_notification_enabled')) {
    function vms_vendor_user_link_notification_enabled(array $context = array()): bool
    {
        return (bool) apply_filters('vms_vendor_user_link_notification_enabled', false, $context);
    }
}

if (!function_exists('vms_vendor_user_link_request_meta_key')) {
    function vms_vendor_user_link_request_meta_key(): string
    {
        return '_vms_vendor_link_requests';
    }
}

if (!function_exists('vms_vendor_user_link_request_notification_enabled')) {
    function vms_vendor_user_link_request_notification_enabled(array $context = array()): bool
    {
        return (bool) apply_filters('vms_vendor_user_link_request_notification_enabled', true, $context);
    }
}

if (!function_exists('vms_vendor_user_link_vendor_email_meta_keys')) {
    function vms_vendor_user_link_vendor_email_meta_keys(): array
    {
        $keys = array(
            function_exists('vms_meta_key') ? (string) vms_meta_key('vendor', 'primary_email') : '_vms_vendor_primary_email',
            function_exists('vms_meta_key') ? (string) vms_meta_key('vendor', 'contact_email') : '_vms_contact_email',
            function_exists('vms_meta_key') ? (string) vms_meta_key('vendor', 'email') : '_vms_vendor_email',
        );

        $out = array();
        foreach ($keys as $key) {
            $key = trim((string) $key);
            if ($key !== '' && !in_array($key, $out, true)) {
                $out[] = $key;
            }
        }

        return $out;
    }
}

if (!function_exists('vms_vendor_user_link_find_vendor_matches_for_email')) {
    function vms_vendor_user_link_find_vendor_matches_for_email(string $email): array
    {
        $email = sanitize_email($email);
        if (!is_email($email)) {
            return array();
        }

        $meta_query = array('relation' => 'OR');
        foreach (vms_vendor_user_link_vendor_email_meta_keys() as $meta_key) {
            $meta_query[] = array(
                'key' => $meta_key,
                'value' => $email,
                'compare' => '=',
            );
        }

        if (count($meta_query) <= 1) {
            return array();
        }

        $query = new WP_Query(array(
            'post_type' => 'vms_vendor',
            'post_status' => array('publish', 'draft', 'private'),
            'posts_per_page' => -1,
            'fields' => 'ids',
            'orderby' => 'title',
            'order' => 'ASC',
            'no_found_rows' => true,
            'meta_query' => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Self-service matching must compare the normalized email against the finite legacy/current vendor-email key set and return request-fresh candidates.
        ));

        if (empty($query->posts)) {
            return array();
        }

        return array_values(array_unique(array_map('absint', (array) $query->posts)));
    }
}

if (!function_exists('vms_vendor_user_link_get_requests_for_user')) {
    function vms_vendor_user_link_get_requests_for_user(int $user_id): array
    {
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return array();
        }

        $raw = get_user_meta($user_id, vms_vendor_user_link_request_meta_key(), true);
        if (!is_array($raw)) {
            return array();
        }

        $out = array();
        foreach ($raw as $key => $row) {
            $vendor_id = 0;
            if (is_array($row)) {
                $vendor_id = absint($row['vendor_id'] ?? $key);
            } else {
                $vendor_id = absint($key);
                $row = array();
            }

            if ($vendor_id <= 0) {
                continue;
            }

            $status = sanitize_key((string) ($row['status'] ?? 'pending'));
            if ($status === '') {
                $status = 'pending';
            }

            $out[$vendor_id] = array(
                'vendor_id' => $vendor_id,
                'status' => $status,
                'requested_at_gmt' => sanitize_text_field((string) ($row['requested_at_gmt'] ?? '')),
                'requested_at_local' => sanitize_text_field((string) ($row['requested_at_local'] ?? '')),
                'requested_by_user_id' => absint($row['requested_by_user_id'] ?? $user_id),
                'source' => sanitize_key((string) ($row['source'] ?? 'vendor_portal')), 
            );
        }

        ksort($out, SORT_NUMERIC);
        return $out;
    }
}

if (!function_exists('vms_vendor_user_link_request_is_pending')) {
    function vms_vendor_user_link_request_is_pending(int $user_id, int $vendor_id): bool
    {
        $requests = vms_vendor_user_link_get_requests_for_user($user_id);
        return !empty($requests[$vendor_id]) && sanitize_key((string) ($requests[$vendor_id]['status'] ?? 'pending')) === 'pending';
    }
}

if (!function_exists('vms_vendor_user_link_store_request')) {
    function vms_vendor_user_link_store_request(int $user_id, int $vendor_id, array $args = array()): bool
    {
        $user_id = (int) $user_id;
        $vendor_id = (int) $vendor_id;
        if ($user_id <= 0 || $vendor_id <= 0) {
            return false;
        }

        $requests = vms_vendor_user_link_get_requests_for_user($user_id);
        $existing = isset($requests[$vendor_id]) && is_array($requests[$vendor_id]) ? $requests[$vendor_id] : array();
        $already_pending = !empty($existing) && sanitize_key((string) ($existing['status'] ?? 'pending')) === 'pending';
        if ($already_pending) {
            return false;
        }

        $requests[$vendor_id] = array(
            'vendor_id' => $vendor_id,
            'status' => 'pending',
            'requested_at_gmt' => current_time('mysql', true),
            'requested_at_local' => current_time('mysql'),
            'requested_by_user_id' => $user_id,
            'source' => sanitize_key((string) ($args['source'] ?? 'vendor_portal')),
        );

        update_user_meta($user_id, vms_vendor_user_link_request_meta_key(), $requests);
        return true;
    }
}

if (!function_exists('vms_vendor_user_link_clear_request')) {
    function vms_vendor_user_link_clear_request(int $user_id, int $vendor_id): void
    {
        $user_id = (int) $user_id;
        $vendor_id = (int) $vendor_id;
        if ($user_id <= 0 || $vendor_id <= 0) {
            return;
        }

        $requests = vms_vendor_user_link_get_requests_for_user($user_id);
        if (!isset($requests[$vendor_id])) {
            return;
        }

        unset($requests[$vendor_id]);
        if (empty($requests)) {
            delete_user_meta($user_id, vms_vendor_user_link_request_meta_key());
            return;
        }

        update_user_meta($user_id, vms_vendor_user_link_request_meta_key(), $requests);
    }
}

if (!function_exists('vms_vendor_user_link_notification_recipients')) {
    function vms_vendor_user_link_notification_recipients(array $context = array()): array
    {
        $emails = array();
        $admin_email = sanitize_email((string) get_option('admin_email', ''));
        if (is_email($admin_email)) {
            $emails[] = $admin_email;
        }

        $emails = (array) apply_filters('vms_vendor_user_link_notification_recipients', $emails, $context);
        $emails = array_values(array_unique(array_filter(array_map('sanitize_email', $emails), 'is_email')));
        return $emails;
    }
}

if (!function_exists('vms_vendor_user_link_vendor_type_label')) {
    function vms_vendor_user_link_vendor_type_label(int $vendor_id): string
    {
        $vendor_id = (int) $vendor_id;
        if ($vendor_id <= 0) {
            return '';
        }

        $terms = wp_get_post_terms($vendor_id, 'vms_vendor_type', array('fields' => 'names'));
        if (is_wp_error($terms) || empty($terms)) {
            return '';
        }

        $names = array_values(array_filter(array_map('sanitize_text_field', (array) $terms)));
        return !empty($names) ? implode(', ', $names) : '';
    }
}

if (!function_exists('vms_vendor_user_link_source_label')) {
    function vms_vendor_user_link_source_label(array $context): string
    {
        $source = sanitize_key((string) ($context['source'] ?? 'vendor_user_link_upsert'));
        $labels = array(
            'vendor_user_link_upsert' => __('Vendor/User link save', 'backstage-venue-manager'),
            'vendor_user_metabox' => __('Vendor edit screen', 'backstage-venue-manager'),
            'vendor_command_center' => __('Vendor Command Center', 'backstage-venue-manager'),
            'vendor_application' => __('Vendor application approval', 'backstage-venue-manager'),
        );

        return $labels[$source] ?? ucwords(str_replace('_', ' ', $source));
    }
}

if (!function_exists('vms_vendor_user_link_actor_label')) {
    function vms_vendor_user_link_actor_label(array $context): string
    {
        $actor_user_id = (int) ($context['actor_user_id'] ?? 0);
        $linked_user_id = (int) ($context['user_id'] ?? 0);

        if ($actor_user_id > 0) {
            $actor = get_userdata($actor_user_id);
            $actor_name = $actor instanceof WP_User ? trim((string) $actor->display_name) : '';
            $actor_email = ($actor instanceof WP_User) ? sanitize_email((string) $actor->user_email) : '';

            if ($actor_user_id === $linked_user_id) {
                if ($actor_name !== '' && $actor_email !== '') {
                    /* translators: 1: Actor display name, 2: Actor email address. */
                    return sprintf(__('Self-service (%1$s <%2$s>)', 'backstage-venue-manager'), $actor_name, $actor_email);
                }
                if ($actor_email !== '') {
                    /* translators: %s: Actor email address. */
                    return sprintf(__('Self-service (%s)', 'backstage-venue-manager'), $actor_email);
                }
                return __('Self-service', 'backstage-venue-manager');
            }

            if ($actor_name !== '' && $actor_email !== '') {
                return sprintf('%s <%s>', $actor_name, $actor_email);
            }
            if ($actor_name !== '') {
                return $actor_name;
            }
            if ($actor_email !== '') {
                return $actor_email;
            }
            /* translators: %d: WordPress user ID. */
            return sprintf(__('User #%d', 'backstage-venue-manager'), $actor_user_id);
        }

        return __('System / automatic', 'backstage-venue-manager');
    }
}

if (!function_exists('vms_vendor_user_link_notify_admin')) {
    function vms_vendor_user_link_notify_admin(array $context): void
    {
        if (!vms_vendor_user_link_notification_enabled($context)) {
            return;
        }

        $vendor_id = (int) ($context['vendor_id'] ?? 0);
        $user_id = (int) ($context['user_id'] ?? 0);
        if ($vendor_id <= 0 || $user_id <= 0) {
            return;
        }

        $recipients = vms_vendor_user_link_notification_recipients($context);
        if (empty($recipients)) {
            return;
        }

        $vendor_title = get_the_title($vendor_id);
        if (!is_string($vendor_title) || $vendor_title === '') {
            /* translators: %d: Vendor post ID. */
            $vendor_title = sprintf(__('Vendor #%d', 'backstage-venue-manager'), $vendor_id);
        }

        $vendor_type = vms_vendor_user_link_vendor_type_label($vendor_id);
        $linked_user = get_userdata($user_id);
        $user_name = $linked_user instanceof WP_User ? trim((string) $linked_user->display_name) : '';
        $user_email = ($linked_user instanceof WP_User) ? sanitize_email((string) $linked_user->user_email) : '';
        $role = sanitize_key((string) ($context['role'] ?? ''));
        $status = sanitize_key((string) ($context['status'] ?? ''));
        $source_label = vms_vendor_user_link_source_label($context);
        $actor_label = vms_vendor_user_link_actor_label($context);
        $primary_label = !empty($context['set_primary_for_user']) ? __('Yes', 'backstage-venue-manager') : __('No', 'backstage-venue-manager');
        $vendor_link = current_user_can('edit_post', $vendor_id) ? get_edit_post_link($vendor_id, '') : admin_url('post.php?post=' . $vendor_id . '&action=edit');
        $user_link = current_user_can('list_users') ? admin_url('user-edit.php?user_id=' . $user_id) : '';
        if ($user_name !== '') {
            $linked_user_label = wp_specialchars_decode($user_name, ENT_QUOTES);
        } else {
            /* translators: %d: WordPress user ID. */
            $linked_user_label = sprintf(__('User #%d', 'backstage-venue-manager'), $user_id);
        }

        /* translators: %s: Vendor title. */
        $subject = sprintf(__('[Backstage Venue Manager] Vendor account linked: %s', 'backstage-venue-manager'), wp_specialchars_decode($vendor_title, ENT_QUOTES));

        $lines = array(
            __('A vendor profile was linked to a website account.', 'backstage-venue-manager'),
            '',
            /* translators: %s: Vendor title. */
            sprintf(__('Vendor: %s', 'backstage-venue-manager'), wp_specialchars_decode($vendor_title, ENT_QUOTES)),
            /* translators: %d: Vendor post ID. */
            sprintf(__('Vendor ID: %d', 'backstage-venue-manager'), $vendor_id),
        );
        if ($vendor_type !== '') {
            /* translators: %s: Vendor type label. */
            $lines[] = sprintf(__('Vendor Type: %s', 'backstage-venue-manager'), $vendor_type);
        }
        /* translators: %s: Website user display label. */
        $lines[] = sprintf(__('Website User: %s', 'backstage-venue-manager'), $linked_user_label);
        /* translators: %d: WordPress user ID. */
        $lines[] = sprintf(__('User ID: %d', 'backstage-venue-manager'), $user_id);
        if ($user_email !== '') {
            /* translators: %s: Website user email address. */
            $lines[] = sprintf(__('User Email: %s', 'backstage-venue-manager'), $user_email);
        }
        if ($role !== '') {
            /* translators: %s: Vendor/user link role key. */
            $lines[] = sprintf(__('Role: %s', 'backstage-venue-manager'), $role);
        }
        if ($status !== '') {
            /* translators: %s: Vendor/user link status key. */
            $lines[] = sprintf(__('Status: %s', 'backstage-venue-manager'), $status);
        }
        /* translators: %s: Yes/No label for whether the vendor is primary for the user. */
        $lines[] = sprintf(__('Primary portal vendor for this user: %s', 'backstage-venue-manager'), $primary_label);
        /* translators: %s: Source label describing where the link originated. */
        $lines[] = sprintf(__('Source: %s', 'backstage-venue-manager'), $source_label);
        /* translators: %s: Actor label describing who created the link. */
        $lines[] = sprintf(__('Linked by: %s', 'backstage-venue-manager'), $actor_label);
        if (is_string($vendor_link) && $vendor_link !== '') {
            /* translators: %s: Admin URL for the vendor profile. */
            $lines[] = sprintf(__('Vendor Profile: %s', 'backstage-venue-manager'), $vendor_link);
        }
        if ($user_link !== '') {
            /* translators: %s: Admin URL for the linked user account. */
            $lines[] = sprintf(__('User Account: %s', 'backstage-venue-manager'), $user_link);
        }

        $body_text = implode("
", $lines);
        $body_html = nl2br(esc_html($body_text));

        foreach ($recipients as $email) {
            $result = function_exists('vms_notify_provider_core_email_send')
                ? (array) vms_notify_provider_core_email_send(array(
                    'to' => $email,
                    'subject' => $subject,
                    'body_text' => $body_text,
                    'body_html' => $body_html,
                ))
                : array(
                    'success' => wp_mail($email, $subject, $body_text),
                    'provider_message_id' => null,
                    'error_message' => '',
                );

            if (function_exists('vms_notify_insert_log')) {
                vms_notify_insert_log(array(
                    'source' => 'vms_vendor_user_links',
                    'event_key' => 'vendor_user_link_created',
                    'recipient_address' => $email,
                    'channel' => 'email',
                    'template_key' => 'vendor.account_linked_admin',
                    'payload' => array(
                        'vendor_id' => $vendor_id,
                        'user_id' => $user_id,
                        'source' => (string) ($context['source'] ?? 'vendor_user_link_upsert'),
                        'actor_user_id' => (int) ($context['actor_user_id'] ?? 0),
                    ),
                    'provider' => 'core_email',
                    'provider_message_id' => sanitize_text_field((string) ($result['provider_message_id'] ?? '')),
                    'status' => !empty($result['success']) ? 'sent' : 'failed',
                    'error_message' => sanitize_text_field((string) ($result['error_message'] ?? '')),
                ));
            }
        }
    }

}

if (!function_exists('vms_vendor_user_link_request_notify_admin')) {
    function vms_vendor_user_link_request_notify_admin(array $context): void
    {
        if (!vms_vendor_user_link_request_notification_enabled($context)) {
            return;
        }

        $vendor_id = (int) ($context['vendor_id'] ?? 0);
        $user_id = (int) ($context['user_id'] ?? 0);
        if ($vendor_id <= 0 || $user_id <= 0) {
            return;
        }

        $recipients = vms_vendor_user_link_notification_recipients($context);
        if (empty($recipients)) {
            return;
        }

        $vendor_title = get_the_title($vendor_id);
        if (!is_string($vendor_title) || $vendor_title === '') {
            /* translators: %d: Vendor post ID. */
            $vendor_title = sprintf(__('Vendor #%d', 'backstage-venue-manager'), $vendor_id);
        }

        $vendor_type = vms_vendor_user_link_vendor_type_label($vendor_id);
        $request_user = get_userdata($user_id);
        $user_name = $request_user instanceof WP_User ? trim((string) $request_user->display_name) : '';
        $user_email = ($request_user instanceof WP_User) ? sanitize_email((string) $request_user->user_email) : '';
        $requested_at_gmt = sanitize_text_field((string) ($context['requested_at_gmt'] ?? ''));
        $requested_ts = $requested_at_gmt !== '' ? strtotime($requested_at_gmt . ' GMT') : false;
        $requested_label = $requested_ts ? wp_date(get_option('date_format', 'F j, Y') . ' ' . get_option('time_format', 'g:i a'), (int) $requested_ts) : '';
        $source = sanitize_key((string) ($context['source'] ?? 'vendor_portal'));
        $source_label = ($source === 'vendor_portal') ? __('Vendor portal access request', 'backstage-venue-manager') : ucwords(str_replace('_', ' ', $source));
        $vendor_link = current_user_can('edit_post', $vendor_id) ? get_edit_post_link($vendor_id, '') : admin_url('post.php?post=' . $vendor_id . '&action=edit');
        $user_link = current_user_can('list_users') ? admin_url('user-edit.php?user_id=' . $user_id) : '';
        if ($user_name !== '') {
            $request_user_label = wp_specialchars_decode($user_name, ENT_QUOTES);
        } else {
            /* translators: %d: WordPress user ID. */
            $request_user_label = sprintf(__('User #%d', 'backstage-venue-manager'), $user_id);
        }

        /* translators: %s: Vendor title. */
        $subject = sprintf(__('[Backstage Venue Manager] Vendor portal link requested: %s', 'backstage-venue-manager'), wp_specialchars_decode($vendor_title, ENT_QUOTES));

        $lines = array(
            __('A website user requested to be linked to a vendor profile.', 'backstage-venue-manager'),
            '',
            /* translators: %s: Vendor title. */
            sprintf(__('Vendor: %s', 'backstage-venue-manager'), wp_specialchars_decode($vendor_title, ENT_QUOTES)),
            /* translators: %d: Vendor post ID. */
            sprintf(__('Vendor ID: %d', 'backstage-venue-manager'), $vendor_id),
        );
        if ($vendor_type !== '') {
            /* translators: %s: Vendor type label. */
            $lines[] = sprintf(__('Vendor Type: %s', 'backstage-venue-manager'), $vendor_type);
        }
        /* translators: %s: Website user display label. */
        $lines[] = sprintf(__('Website User: %s', 'backstage-venue-manager'), $request_user_label);
        /* translators: %d: WordPress user ID. */
        $lines[] = sprintf(__('User ID: %d', 'backstage-venue-manager'), $user_id);
        if ($user_email !== '') {
            /* translators: %s: Website user email address. */
            $lines[] = sprintf(__('User Email: %s', 'backstage-venue-manager'), $user_email);
        }
        if ($requested_label !== '') {
            /* translators: %s: Localized request timestamp. */
            $lines[] = sprintf(__('Requested At: %s', 'backstage-venue-manager'), $requested_label);
        }
        /* translators: %s: Source label describing where the request originated. */
        $lines[] = sprintf(__('Source: %s', 'backstage-venue-manager'), $source_label);
        if (is_string($vendor_link) && $vendor_link !== '') {
            /* translators: %s: Admin URL for the vendor profile. */
            $lines[] = sprintf(__('Vendor Profile: %s', 'backstage-venue-manager'), $vendor_link);
        }
        if ($user_link !== '') {
            /* translators: %s: Admin URL for the requesting user account. */
            $lines[] = sprintf(__('User Account: %s', 'backstage-venue-manager'), $user_link);
        }

        $body_text = implode("
", $lines);
        $body_html = nl2br(esc_html($body_text));

        foreach ($recipients as $email) {
            $result = function_exists('vms_notify_provider_core_email_send')
                ? (array) vms_notify_provider_core_email_send(array(
                    'to' => $email,
                    'subject' => $subject,
                    'body_text' => $body_text,
                    'body_html' => $body_html,
                ))
                : array(
                    'success' => wp_mail($email, $subject, $body_text),
                    'provider_message_id' => null,
                    'error_message' => '',
                );

            if (function_exists('vms_notify_insert_log')) {
                vms_notify_insert_log(array(
                    'source' => 'vms_vendor_user_link_requests',
                    'event_key' => 'vendor_user_link_requested',
                    'recipient_address' => $email,
                    'channel' => 'email',
                    'template_key' => 'vendor.portal_link_requested_admin',
                    'payload' => array(
                        'vendor_id' => $vendor_id,
                        'user_id' => $user_id,
                        'source' => $source,
                    ),
                    'provider' => 'core_email',
                    'provider_message_id' => sanitize_text_field((string) ($result['provider_message_id'] ?? '')),
                    'status' => !empty($result['success']) ? 'sent' : 'failed',
                    'error_message' => sanitize_text_field((string) ($result['error_message'] ?? '')),
                ));
            }
        }
    }
}

add_action('vms_vendor_user_link_requested', 'vms_vendor_user_link_request_notify_admin', 10, 1);
add_action('vms_vendor_user_link_created', 'vms_vendor_user_link_clear_pending_request_on_create', 20, 1);

if (!function_exists('vms_vendor_user_link_clear_pending_request_on_create')) {
    function vms_vendor_user_link_clear_pending_request_on_create(array $context): void
    {
        $vendor_id = (int) ($context['vendor_id'] ?? 0);
        $user_id = (int) ($context['user_id'] ?? 0);
        if ($vendor_id <= 0 || $user_id <= 0) {
            return;
        }

        vms_vendor_user_link_clear_request($user_id, $vendor_id);
    }
}

/**
 * Upsert a vendor↔user link row.
 */
function vms_vendor_user_link_upsert(int $vendor_id, int $user_id, array $args = array(), int $actor_user_id = 0): bool
{
    $vendor_id = (int) $vendor_id;
    $user_id = (int) $user_id;
    if ($vendor_id <= 0 || $user_id <= 0) return false;

    $role   = vms_vendor_user_link_normalize_role(isset($args['role']) ? (string) $args['role'] : 'manager');
    $status = vms_vendor_user_link_normalize_status(isset($args['status']) ? (string) $args['status'] : 'active');
    $set_primary_for_user = !empty($args['set_primary_for_user']);
    $source = sanitize_key((string) ($args['source'] ?? 'vendor_user_link_upsert'));
    $link_existed_before = vms_vendor_user_link_exists($vendor_id, $user_id);

    // Back-compat: ensure user meta has a primary vendor if none exists.
    $existing_primary = (int) get_user_meta($user_id, BVMGR_USER_PRIMARY_VENDOR_META_KEY, true);
    if ($existing_primary <= 0) {
        update_user_meta($user_id, BVMGR_USER_PRIMARY_VENDOR_META_KEY, $vendor_id);
        $set_primary_for_user = true;
    }

    if (!vms_vendor_user_links_table_exists()) {
        // Fall back to the legacy convenience pointers without clobbering an
        // existing primary vendor selection unless this call explicitly asked for it.
        $vendor_primary_user = (int) get_post_meta($vendor_id, BVMGR_VENDOR_PRIMARY_USER_META_KEY, true);
        if ($vendor_primary_user <= 0 || $set_primary_for_user) {
            update_post_meta($vendor_id, BVMGR_VENDOR_PRIMARY_USER_META_KEY, $user_id);
        }
        if ($set_primary_for_user || $existing_primary <= 0) {
            update_user_meta($user_id, BVMGR_USER_PRIMARY_VENDOR_META_KEY, $vendor_id);
        }

        if (!$link_existed_before) {
            do_action('vms_vendor_user_link_created', array(
                'vendor_id' => $vendor_id,
                'user_id' => $user_id,
                'actor_user_id' => (int) $actor_user_id,
                'role' => $role,
                'status' => $status,
                'set_primary_for_user' => (bool) $set_primary_for_user,
                'source' => $source,
            ));
        }

        return true;
    }

    global $wpdb;
    $t = vms_vendor_user_links_table();
    $now = current_time('mysql', true);

    $is_primary = $set_primary_for_user ? 1 : 0;

    // Insert or update in a single statement.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Vendor/user upserts persist authoritative plugin-owned link state with a prepared identifier and values; authorization and portal reads must observe the mutation immediately.
    $ok = $wpdb->query(
        $wpdb->prepare(
            'INSERT INTO %i
                (vendor_id, user_id, user_role, link_status, is_primary, created_at, created_by, updated_at, updated_by)
             VALUES
                (%d, %d, %s, %s, %d, %s, %d, %s, %d)
             ON DUPLICATE KEY UPDATE
                user_role   = VALUES(user_role),
                link_status = VALUES(link_status),
                is_primary  = IF(VALUES(is_primary)=1, 1, is_primary),
                updated_at  = VALUES(updated_at),
                updated_by  = VALUES(updated_by)',
            $t,
            $vendor_id,
            $user_id,
            $role,
            $status,
            $is_primary,
            $now,
            (int) $actor_user_id,
            $now,
            (int) $actor_user_id
        )
    );
    if ($ok === false) return false;

    // If requested, enforce unique primary for this user.
    if ($set_primary_for_user) {
        vms_vendor_user_links_set_primary_for_user($user_id, $vendor_id, $actor_user_id);
    }

    // Back-compat: if vendor has no primary contact user, set it.
    $vendor_primary_user = (int) get_post_meta($vendor_id, BVMGR_VENDOR_PRIMARY_USER_META_KEY, true);
    if ($vendor_primary_user <= 0 && $status === 'active') {
        update_post_meta($vendor_id, BVMGR_VENDOR_PRIMARY_USER_META_KEY, $user_id);
    }

    if (!$link_existed_before) {
        do_action('vms_vendor_user_link_created', array(
            'vendor_id' => $vendor_id,
            'user_id' => $user_id,
            'actor_user_id' => (int) $actor_user_id,
            'role' => $role,
            'status' => $status,
            'set_primary_for_user' => (bool) $set_primary_for_user,
            'source' => $source,
        ));
    }

    return true;
}

/**
 * Update role/status for an existing link.
 */
function vms_vendor_user_link_update(int $vendor_id, int $user_id, array $fields = array(), int $actor_user_id = 0): bool
{
    $vendor_id = (int) $vendor_id;
    $user_id = (int) $user_id;
    if ($vendor_id <= 0 || $user_id <= 0) return false;

    if (!vms_vendor_user_links_table_exists()) {
        return true;
    }

    $role = null;
    $status = null;

    if (isset($fields['role'])) {
        $role = vms_vendor_user_link_normalize_role((string) $fields['role']);
    }
    if (isset($fields['status'])) {
        $status = vms_vendor_user_link_normalize_status((string) $fields['status']);
    }

    $data = array();
    $fmt  = array();

    if (is_string($role)) {
        $data['user_role'] = $role;
        $fmt[] = '%s';
    }
    if (is_string($status)) {
        $data['link_status'] = $status;
        $fmt[] = '%s';
    }

    $data['updated_at'] = current_time('mysql', true);
    $fmt[] = '%s';
    $data['updated_by'] = (int) $actor_user_id;
    $fmt[] = '%d';

    global $wpdb;
    $t = vms_vendor_user_links_table();

    $where = array(
        'vendor_id' => $vendor_id,
        'user_id'   => $user_id,
    );
    $where_fmt = array('%d', '%d');

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Vendor/user role and status changes write the authoritative plugin-owned link row directly and must be visible to authorization checks in the same request.
    $res = $wpdb->update($t, $data, $where, $fmt, $where_fmt);
    return ($res !== false);
}

/**
 * Delete a vendor↔user link.
 */
function vms_vendor_user_link_delete(int $vendor_id, int $user_id, int $actor_user_id = 0): bool
{
    $vendor_id = (int) $vendor_id;
    $user_id = (int) $user_id;
    if ($vendor_id <= 0 || $user_id <= 0) return false;

    // Legacy cleanup
    $vendor_primary_user = (int) get_post_meta($vendor_id, BVMGR_VENDOR_PRIMARY_USER_META_KEY, true);
    if ($vendor_primary_user === $user_id) {
        delete_post_meta($vendor_id, BVMGR_VENDOR_PRIMARY_USER_META_KEY);
    }

    $user_primary_vendor = (int) get_user_meta($user_id, BVMGR_USER_PRIMARY_VENDOR_META_KEY, true);
    if ($user_primary_vendor === $vendor_id) {
        delete_user_meta($user_id, BVMGR_USER_PRIMARY_VENDOR_META_KEY);
    }

    if (!vms_vendor_user_links_table_exists()) {
        return true;
    }

    global $wpdb;
    $t = vms_vendor_user_links_table();

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Vendor/user unlinking removes the authoritative plugin-owned link row directly before selecting any replacement primary in the same request.
    $res = $wpdb->delete($t, array(
        'vendor_id' => $vendor_id,
        'user_id'   => $user_id,
    ), array('%d', '%d'));

    if ($res === false) return false;

    // If we deleted the primary vendor for the user, select another active vendor and set it.
    $still = vms_get_active_vendor_ids_for_user($user_id);
    if ($still) {
        vms_vendor_user_links_set_primary_for_user($user_id, (int)$still[0], $actor_user_id);
    }

    return true;
}

/**
 * Convenience: vendor ID for a specific user (primary/default).
 * Kept for backwards compatibility with older helper names.
 */
function vms_get_vendor_id_for_user(int $user_id): int
{
    return vms_get_primary_vendor_id_for_user($user_id);
}

/**
 * Convenience: current user's vendor ID (primary/default).
 */
function vms_get_vendor_id_for_current_user(): int
{
    $user_id = get_current_user_id();
    return $user_id ? vms_get_primary_vendor_id_for_user((int)$user_id) : 0;
}
