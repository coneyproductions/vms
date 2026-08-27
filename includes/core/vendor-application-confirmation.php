<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_vendor_app_confirm_tokens_table')) {
    function vms_vendor_app_confirm_tokens_table(): string
    {
        global $wpdb;
        return (string) $wpdb->prefix . (defined('BVMGR_DB_TABLE_VENDOR_APP_CONFIRM_TOKENS_SUFFIX') ? BVMGR_DB_TABLE_VENDOR_APP_CONFIRM_TOKENS_SUFFIX : 'vms_vendor_app_confirm_tokens');
    }
}

if (!function_exists('vms_vendor_app_confirmation_bypass_enabled')) {
    function vms_vendor_app_confirmation_bypass_enabled(): bool
    {
        $enabled = defined('VMS_VENDOR_APP_CONFIRMATION_BYPASS') && (bool) VMS_VENDOR_APP_CONFIRMATION_BYPASS;
        return (bool) apply_filters('vms_vendor_app_confirmation_bypass_enabled', $enabled);
    }
}

if (!function_exists('vms_vendor_app_confirmation_states')) {
    function vms_vendor_app_confirmation_states(): array
    {
        return array(
            'unconfirmed' => __('Awaiting Email Confirmation', 'backstage-venue-manager'),
            'confirmed' => __('Confirmed', 'backstage-venue-manager'),
            'expired' => __('Confirmation Expired', 'backstage-venue-manager'),
        );
    }
}

if (!function_exists('vms_vendor_app_confirmation_state_label')) {
    function vms_vendor_app_confirmation_state_label(string $state): string
    {
        $all = vms_vendor_app_confirmation_states();
        $state = sanitize_key($state);
        return (string) ($all[$state] ?? __('Confirmed', 'backstage-venue-manager'));
    }
}

if (!function_exists('vms_vendor_app_confirmation_window_seconds')) {
    function vms_vendor_app_confirmation_window_seconds(): int
    {
        return 48 * HOUR_IN_SECONDS;
    }
}

if (!function_exists('vms_vendor_app_confirmation_cooldown_seconds')) {
    function vms_vendor_app_confirmation_cooldown_seconds(): int
    {
        return 10 * MINUTE_IN_SECONDS;
    }
}

if (!function_exists('vms_vendor_app_confirmation_daily_send_cap')) {
    function vms_vendor_app_confirmation_daily_send_cap(): int
    {
        return 5;
    }
}

if (!function_exists('vms_vendor_app_confirmation_ip_window_seconds')) {
    function vms_vendor_app_confirmation_ip_window_seconds(): int
    {
        return HOUR_IN_SECONDS;
    }
}

if (!function_exists('vms_vendor_app_confirmation_ip_send_cap')) {
    function vms_vendor_app_confirmation_ip_send_cap(): int
    {
        return 10;
    }
}

if (!function_exists('vms_vendor_app_confirmation_attempt_window_seconds')) {
    function vms_vendor_app_confirmation_attempt_window_seconds(): int
    {
        return 10 * MINUTE_IN_SECONDS;
    }
}

if (!function_exists('vms_vendor_app_confirmation_attempt_cap')) {
    function vms_vendor_app_confirmation_attempt_cap(): int
    {
        return 20;
    }
}

if (!function_exists('vms_vendor_app_local_mysql_to_utc_timestamp')) {
    function vms_vendor_app_local_mysql_to_utc_timestamp(string $value)
    {
        $value = trim($value);
        if ($value === '' || $value === '0000-00-00 00:00:00') {
            return false;
        }

        $gmt = get_gmt_from_date($value, 'Y-m-d H:i:s');
        if (!is_string($gmt) || $gmt === '' || $gmt === '0000-00-00 00:00:00') {
            return false;
        }

        return strtotime($gmt . ' UTC');
    }
}

if (!function_exists('vms_vendor_app_confirmation_endpoint_slug')) {
    function vms_vendor_app_confirmation_endpoint_slug(): string
    {
        return (string) apply_filters('vms_vendor_app_confirmation_endpoint_slug', 'vendor-application-confirm');
    }
}

if (!function_exists('vms_vendor_app_confirmation_endpoint_url')) {
    function vms_vendor_app_confirmation_endpoint_url(array $query_args = array()): string
    {
        $url = home_url('/' . trim(vms_vendor_app_confirmation_endpoint_slug(), '/') . '/');
        if (!empty($query_args)) {
            $url = add_query_arg($query_args, $url);
        }
        return (string) $url;
    }
}

if (!function_exists('vms_vendor_app_confirmation_reset_url')) {
    function vms_vendor_app_confirmation_reset_url(): string
    {
        $login_redirect_url = function_exists('vms_vendor_portal_login_redirect_url')
            ? vms_vendor_portal_login_redirect_url(true)
            : home_url('/vendor-portal/?vms_vendor_portal_login=1');
        return (string) wp_lostpassword_url($login_redirect_url);
    }
}

if (!function_exists('vms_vendor_app_register_confirmation_endpoint')) {
    function vms_vendor_app_register_confirmation_endpoint(): void
    {
        add_rewrite_tag('%vms_vendor_app_confirm%', '([^&]+)');
        add_rewrite_rule('^' . preg_quote(vms_vendor_app_confirmation_endpoint_slug(), '/') . '/?$', 'index.php?vms_vendor_app_confirm=1', 'top');
    }
}
add_action('init', 'vms_vendor_app_register_confirmation_endpoint');

if (!function_exists('vms_vendor_app_add_confirmation_query_var')) {
    function vms_vendor_app_add_confirmation_query_var(array $vars): array
    {
        $vars[] = 'vms_vendor_app_confirm';
        return $vars;
    }
}
add_filter('query_vars', 'vms_vendor_app_add_confirmation_query_var');

if (!function_exists('vms_vendor_app_confirmation_query_value')) {
    function vms_vendor_app_confirmation_query_value(string $key): string
    {
        return bvmgr_request_read_scalar($_GET, $key); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public confirmation links use read-only URL parameters for routing and one-time token lookup.
    }
}

if (!function_exists('vms_vendor_app_resend_request_text_field')) {
    function vms_vendor_app_resend_request_text_field(string $key): string
    {
        return bvmgr_request_read_text_field($_POST, $key); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The resend form lookup key only scopes the nonce action before the request is verified.
    }
}

if (!function_exists('vms_vendor_app_maybe_flush_confirmation_rewrite')) {
    function vms_vendor_app_maybe_flush_confirmation_rewrite(): void
    {
        $marker_key = 'vms_vendor_app_confirm_rewrite_flushed';
        $target = defined('BVMGR_VERSION') ? (string) BVMGR_VERSION : 'vendor-app-confirm-v1';
        $current = (string) get_option($marker_key, '');
        if ($current === $target) {
            return;
        }

        flush_rewrite_rules(false);
        update_option($marker_key, $target, false);
    }
}
add_action('init', 'vms_vendor_app_maybe_flush_confirmation_rewrite', 20);

if (!function_exists('vms_vendor_app_is_confirmation_request')) {
    function vms_vendor_app_is_confirmation_request(): bool
    {
        $qv = get_query_var('vms_vendor_app_confirm');
        if ((string) $qv === '1') {
            return true;
        }

        return vms_vendor_app_confirmation_query_value('vms_vendor_app_confirm') === '1';
    }
}

if (!function_exists('vms_vendor_app_get_confirmation_state_raw')) {
    function vms_vendor_app_get_confirmation_state_raw(int $app_id): string
    {
        $key = vms_vendor_app_meta_key('confirmation_state');
        if ($key === '') {
            $key = '_vms_app_confirmation_state';
        }

        $state = sanitize_key((string) get_post_meta($app_id, $key, true));
        if ($state === '') {
            return 'confirmed';
        }

        return $state;
    }
}

if (!function_exists('vms_vendor_app_maybe_ensure_public_lookup_key')) {
    function vms_vendor_app_maybe_ensure_public_lookup_key(int $app_id): string
    {
        $app_id = (int) $app_id;
        if ($app_id <= 0) {
            return '';
        }

        $key_name = vms_vendor_app_meta_key('public_lookup_key');
        if ($key_name === '') {
            $key_name = '_vms_app_public_lookup_key';
        }

        $key = sanitize_text_field((string) get_post_meta($app_id, $key_name, true));
        if ($key !== '') {
            return $key;
        }

        try {
            $bytes = random_bytes(24);
            $key = rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
        } catch (Exception $e) {
            $key = wp_generate_password(36, false, false);
        }

        $key = sanitize_text_field((string) $key);
        update_post_meta($app_id, $key_name, $key);
        return $key;
    }
}

if (!function_exists('vms_vendor_app_get_public_lookup_key')) {
    function vms_vendor_app_get_public_lookup_key(int $app_id): string
    {
        return vms_vendor_app_maybe_ensure_public_lookup_key($app_id);
    }
}

if (!function_exists('vms_vendor_app_find_application_by_public_lookup_key')) {
    function vms_vendor_app_find_application_by_public_lookup_key(string $lookup_key): int
    {
        $lookup_key = sanitize_text_field($lookup_key);
        if ($lookup_key === '') {
            return 0;
        }

        $key_name = vms_vendor_app_meta_key('public_lookup_key');
        if ($key_name === '') {
            $key_name = '_vms_app_public_lookup_key';
        }

        $app_ids = get_posts(array(
            'post_type' => vms_vendor_app_cpt_slugs(),
            'post_status' => array('publish', 'draft', 'pending', 'private'),
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Public confirmation and resend routes require an exact lookup across the finite application lookup-key metadata, bounded to one application ID.
                array(
                    'key' => $key_name,
                    'value' => $lookup_key,
                    'compare' => '=',
                ),
            ),
        ));

        return !empty($app_ids) ? (int) $app_ids[0] : 0;
    }
}

if (!function_exists('vms_vendor_app_hash_confirmation_token')) {
    function vms_vendor_app_hash_confirmation_token(string $raw_token): string
    {
        return hash('sha256', wp_salt('auth') . '|vms_vendor_app_confirm|' . trim($raw_token));
    }
}

if (!function_exists('vms_vendor_app_generate_raw_confirmation_token')) {
    function vms_vendor_app_generate_raw_confirmation_token(): string
    {
        try {
            $bytes = random_bytes(32);
        } catch (Exception $e) {
            return sanitize_text_field((string) wp_generate_password(48, true, true));
        }

        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}

if (!function_exists('vms_vendor_app_generate_username_from_email')) {
    function vms_vendor_app_generate_username_from_email(string $email): string
    {
        $email = sanitize_email($email);
        $base = sanitize_user(strstr($email, '@', true) ?: 'vendor', true);
        if ($base === '') {
            $base = 'vendor';
        }

        $candidate = $base;
        $suffix = 1;
        while (username_exists($candidate)) {
            $suffix++;
            $candidate = $base . $suffix;
            if ($suffix > 9999) {
                $candidate = $base . wp_generate_password(6, false, false);
                break;
            }
        }

        return $candidate;
    }
}

if (!function_exists('vms_vendor_app_get_confirmation_email')) {
    function vms_vendor_app_get_confirmation_email(int $app_id): string
    {
        $email_key = vms_vendor_app_meta_key('email');
        if ($email_key === '') {
            $email_key = '_vms_app_email';
        }
        return sanitize_email((string) get_post_meta($app_id, $email_key, true));
    }
}

if (!function_exists('vms_vendor_app_get_latest_confirmation_token_row')) {
    function vms_vendor_app_get_latest_confirmation_token_row(int $app_id): ?array
    {
        global $wpdb;

        $app_id = (int) $app_id;
        if ($app_id <= 0) {
            return null;
        }

        $table = vms_vendor_app_confirm_tokens_table();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Confirmation state reads query the plugin-owned token repository with prepared identifier/value placeholders and must observe immediate lifecycle mutations.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
                 FROM %i
                 WHERE application_id = %d
                 ORDER BY id DESC
                 LIMIT 1",
                $table,
                $app_id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }
}

if (!function_exists('vms_vendor_app_get_latest_open_confirmation_token_row')) {
    function vms_vendor_app_get_latest_open_confirmation_token_row(int $app_id, bool $require_unexpired = false): ?array
    {
        global $wpdb;

        $app_id = (int) $app_id;
        if ($app_id <= 0) {
            return null;
        }

        if ($require_unexpired) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Resend gating reads request-fresh open token state from the plugin-owned repository and applies prepared identifier, application, and expiry values.
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT *
                     FROM %i
                     WHERE application_id = %d
                       AND consumed_at IS NULL
                       AND invalidated_at IS NULL
                       AND expires_at >= %s
                     ORDER BY id DESC
                     LIMIT 1",
                    vms_vendor_app_confirm_tokens_table(),
                    $app_id,
                    current_time('mysql', true)
                ),
                ARRAY_A
            );
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Resend and state refresh paths read request-fresh open token state from the plugin-owned repository with prepared identifier and application values.
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT *
                     FROM %i
                     WHERE application_id = %d
                       AND consumed_at IS NULL
                       AND invalidated_at IS NULL
                     ORDER BY id DESC
                     LIMIT 1",
                    vms_vendor_app_confirm_tokens_table(),
                    $app_id
                ),
                ARRAY_A
            );
        }

        return is_array($row) ? $row : null;
    }
}

if (!function_exists('vms_vendor_app_get_confirmation_token_row_by_hash')) {
    function vms_vendor_app_get_confirmation_token_row_by_hash(string $token_hash): ?array
    {
        global $wpdb;

        $token_hash = trim($token_hash);
        if ($token_hash === '') {
            return null;
        }

        $table = vms_vendor_app_confirm_tokens_table();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Public one-time-token validation reads request-fresh lifecycle state from the plugin-owned repository so consumed or invalidated tokens cannot be replayed.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
                 FROM %i
                 WHERE token_hash = %s
                 ORDER BY id DESC
                 LIMIT 1",
                $table,
                $token_hash
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }
}

if (!function_exists('vms_vendor_app_invalidate_confirmation_token')) {
    function vms_vendor_app_invalidate_confirmation_token(int $token_id, string $reason = 'rotated'): void
    {
        global $wpdb;

        $token_id = (int) $token_id;
        if ($token_id <= 0) {
            return;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Token invalidation writes the authoritative plugin-owned lifecycle row and subsequent validation must observe it in the same request.
        $wpdb->update(
            vms_vendor_app_confirm_tokens_table(),
            array(
                'invalidated_at' => current_time('mysql', true),
                'invalidated_reason' => sanitize_key($reason),
            ),
            array(
                'id' => $token_id,
                'invalidated_at' => null,
                'consumed_at' => null,
            ),
            array('%s', '%s'),
            array('%d', '%s', '%s')
        );
    }
}

if (!function_exists('vms_vendor_app_invalidate_open_confirmation_tokens')) {
    function vms_vendor_app_invalidate_open_confirmation_tokens(int $app_id, string $reason = 'rotated'): void
    {
        global $wpdb;

        $app_id = (int) $app_id;
        if ($app_id <= 0) {
            return;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Token rotation and confirmation invalidate all request-fresh open rows in the plugin-owned repository before the next lifecycle mutation.
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE %i
                 SET invalidated_at = %s,
                     invalidated_reason = %s
                 WHERE application_id = %d
                   AND consumed_at IS NULL
                   AND invalidated_at IS NULL",
                vms_vendor_app_confirm_tokens_table(),
                current_time('mysql', true),
                sanitize_key($reason),
                $app_id
            )
        );
    }
}

if (!function_exists('vms_vendor_app_create_confirmation_token')) {
    function vms_vendor_app_create_confirmation_token(int $app_id, array $args = array())
    {
        global $wpdb;

        $app_id = (int) $app_id;
        if ($app_id <= 0) {
            return new WP_Error('vms_vendor_app_confirm_app_missing', __('Invalid application.', 'backstage-venue-manager'));
        }

        $email = sanitize_email((string) ($args['email'] ?? vms_vendor_app_get_confirmation_email($app_id)));
        if ($email === '' || !is_email($email)) {
            return new WP_Error('vms_vendor_app_confirm_email_invalid', __('A valid application email is required before sending confirmation.', 'backstage-venue-manager'));
        }

        vms_vendor_app_invalidate_open_confirmation_tokens($app_id, (string) ($args['invalidate_reason'] ?? 'rotated'));

        $raw_token = vms_vendor_app_generate_raw_confirmation_token();
        $token_hash = vms_vendor_app_hash_confirmation_token($raw_token);
        $created_at = current_time('mysql', true);
        $expires_at = gmdate('Y-m-d H:i:s', time() + vms_vendor_app_confirmation_window_seconds());

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Confirmation creation persists a normalized one-time-token row in the plugin-owned repository; no WordPress core data API represents this lifecycle record.
        $inserted = $wpdb->insert(
            vms_vendor_app_confirm_tokens_table(),
            array(
                'application_id' => $app_id,
                'email' => $email,
                'token_hash' => $token_hash,
                'created_at' => $created_at,
                'expires_at' => $expires_at,
                'sent_at' => null,
                'consumed_at' => null,
                'invalidated_at' => null,
                'invalidated_reason' => null,
                'resolved_user_id' => null,
                'created_by_user_id' => absint($args['created_by_user_id'] ?? get_current_user_id()),
                'consumed_ip' => null,
                'consumed_user_agent' => null,
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s')
        );

        if (!$inserted) {
            return new WP_Error('vms_vendor_app_confirm_token_create_failed', __('The confirmation token could not be created.', 'backstage-venue-manager'));
        }

        return array(
            'token_id' => (int) $wpdb->insert_id,
            'token' => $raw_token,
            'created_at' => $created_at,
            'expires_at' => $expires_at,
            'confirm_url' => vms_vendor_app_confirmation_endpoint_url(array('token' => $raw_token)),
        );
    }
}

if (!function_exists('vms_vendor_app_mark_confirmation_token_sent')) {
    function vms_vendor_app_mark_confirmation_token_sent(int $token_id): void
    {
        global $wpdb;

        $token_id = (int) $token_id;
        if ($token_id <= 0) {
            return;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Delivery state is stored on the authoritative plugin-owned token row and must be visible to resend throttling immediately.
        $wpdb->update(
            vms_vendor_app_confirm_tokens_table(),
            array('sent_at' => current_time('mysql', true)),
            array('id' => $token_id),
            array('%s'),
            array('%d')
        );
    }
}

if (!function_exists('vms_vendor_app_mark_confirmation_token_consumed')) {
    function vms_vendor_app_mark_confirmation_token_consumed(int $token_id, int $resolved_user_id = 0): void
    {
        global $wpdb;

        $token_id = (int) $token_id;
        if ($token_id <= 0) {
            return;
        }

        $ip = bvmgr_request_remote_addr();
        $ua = bvmgr_request_user_agent();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Consumption atomically records the authoritative token lifecycle result and request context before remaining open tokens are invalidated.
        $wpdb->update(
            vms_vendor_app_confirm_tokens_table(),
            array(
                'consumed_at' => current_time('mysql', true),
                'resolved_user_id' => max(0, $resolved_user_id),
                'consumed_ip' => $ip,
                'consumed_user_agent' => $ua,
            ),
            array('id' => $token_id),
            array('%s', '%d', '%s', '%s'),
            array('%d')
        );
    }
}

if (!function_exists('vms_vendor_app_reset_confirmation_send_window_if_needed')) {
    function vms_vendor_app_reset_confirmation_send_window_if_needed(int $app_id): void
    {
        $count_key = vms_vendor_app_meta_key('confirmation_send_count') ?: '_vms_app_confirmation_send_count';
        $window_key = vms_vendor_app_meta_key('confirmation_send_window_started_at') ?: '_vms_app_confirmation_send_window_started_at';

        $window_started_at = trim((string) get_post_meta($app_id, $window_key, true));
        $window_ts = vms_vendor_app_local_mysql_to_utc_timestamp($window_started_at);
        if ($window_ts === false || ((time() - $window_ts) >= DAY_IN_SECONDS)) {
            update_post_meta($app_id, $count_key, 0);
            update_post_meta($app_id, $window_key, current_time('mysql'));
        }
    }
}

if (!function_exists('vms_vendor_app_note_confirmation_send')) {
    function vms_vendor_app_note_confirmation_send(int $app_id, string $source = 'confirmation_email'): void
    {
        $app_id = (int) $app_id;
        if ($app_id <= 0) {
            return;
        }

        vms_vendor_app_reset_confirmation_send_window_if_needed($app_id);

        $last_sent_key = vms_vendor_app_meta_key('confirmation_last_sent_at') ?: '_vms_app_confirmation_last_sent_at';
        $count_key = vms_vendor_app_meta_key('confirmation_send_count') ?: '_vms_app_confirmation_send_count';
        $window_key = vms_vendor_app_meta_key('confirmation_send_window_started_at') ?: '_vms_app_confirmation_send_window_started_at';
        $source_key = vms_vendor_app_meta_key('confirmation_source') ?: '_vms_app_confirmation_source';

        $count = absint(get_post_meta($app_id, $count_key, true));
        update_post_meta($app_id, $last_sent_key, current_time('mysql'));
        update_post_meta($app_id, $count_key, $count + 1);
        if (trim((string) get_post_meta($app_id, $window_key, true)) === '') {
            update_post_meta($app_id, $window_key, current_time('mysql'));
        }
        update_post_meta($app_id, $source_key, sanitize_key($source));
    }
}

if (!function_exists('vms_vendor_app_confirmation_ip_bucket_key')) {
    function vms_vendor_app_confirmation_ip_bucket_key(int $app_id): string
    {
        $ip = bvmgr_request_remote_addr();
        if ($ip === '') {
            $ip = 'unknown';
        }
        return 'vms_vendor_app_confirm_ip_' . md5($app_id . '|' . $ip);
    }
}

if (!function_exists('vms_vendor_app_confirmation_ip_rate_limited')) {
    function vms_vendor_app_confirmation_ip_rate_limited(int $app_id): bool
    {
        $bucket = get_transient(vms_vendor_app_confirmation_ip_bucket_key($app_id));
        if (!is_array($bucket)) {
            return false;
        }

        return absint($bucket['count'] ?? 0) >= vms_vendor_app_confirmation_ip_send_cap();
    }
}

if (!function_exists('vms_vendor_app_note_confirmation_ip_send')) {
    function vms_vendor_app_note_confirmation_ip_send(int $app_id): void
    {
        $key = vms_vendor_app_confirmation_ip_bucket_key($app_id);
        $bucket = get_transient($key);
        if (!is_array($bucket)) {
            $bucket = array('count' => 0);
        }

        $bucket['count'] = absint($bucket['count'] ?? 0) + 1;
        set_transient($key, $bucket, vms_vendor_app_confirmation_ip_window_seconds());
    }
}

if (!function_exists('vms_vendor_app_can_send_confirmation_email')) {
    function vms_vendor_app_can_send_confirmation_email(int $app_id)
    {
        $app_id = (int) $app_id;
        if ($app_id <= 0) {
            return new WP_Error('vms_vendor_app_confirm_invalid_app', __('Invalid application.', 'backstage-venue-manager'));
        }

        $state = vms_vendor_app_get_confirmation_state($app_id);
        if ($state === 'confirmed') {
            return new WP_Error('vms_vendor_app_confirm_already_confirmed', __('This application is already confirmed and ready for review.', 'backstage-venue-manager'));
        }

        $last_sent_key = vms_vendor_app_meta_key('confirmation_last_sent_at') ?: '_vms_app_confirmation_last_sent_at';
        $count_key = vms_vendor_app_meta_key('confirmation_send_count') ?: '_vms_app_confirmation_send_count';
        $window_key = vms_vendor_app_meta_key('confirmation_send_window_started_at') ?: '_vms_app_confirmation_send_window_started_at';

        vms_vendor_app_reset_confirmation_send_window_if_needed($app_id);

        $last_sent_at = trim((string) get_post_meta($app_id, $last_sent_key, true));
        if ($last_sent_at !== '') {
            $last_sent_ts = vms_vendor_app_local_mysql_to_utc_timestamp($last_sent_at);
            if ($last_sent_ts !== false && ((time() - $last_sent_ts) < vms_vendor_app_confirmation_cooldown_seconds())) {
                return new WP_Error('vms_vendor_app_confirm_cooldown', __('We recently sent a confirmation email. Please wait a few minutes before requesting another one.', 'backstage-venue-manager'));
            }
        }

        $window_started_at = trim((string) get_post_meta($app_id, $window_key, true));
        $window_ts = vms_vendor_app_local_mysql_to_utc_timestamp($window_started_at);
        $send_count = absint(get_post_meta($app_id, $count_key, true));
        if ($window_ts !== false && ((time() - $window_ts) < DAY_IN_SECONDS) && $send_count >= vms_vendor_app_confirmation_daily_send_cap()) {
            return new WP_Error('vms_vendor_app_confirm_daily_cap', __('We have already sent the maximum number of confirmation emails for this application today. Please try again later.', 'backstage-venue-manager'));
        }

        if (vms_vendor_app_confirmation_ip_rate_limited($app_id)) {
            return new WP_Error('vms_vendor_app_confirm_ip_throttle', __('Too many confirmation email requests came from this connection. Please wait and try again later.', 'backstage-venue-manager'));
        }

        return true;
    }
}

if (!function_exists('vms_vendor_app_send_confirmation_email')) {
    function vms_vendor_app_send_confirmation_email(int $app_id, array $args = array())
    {
        $app_id = (int) $app_id;
        if ($app_id <= 0) {
            return new WP_Error('vms_vendor_app_confirm_invalid_app', __('Invalid application.', 'backstage-venue-manager'));
        }

        $allowed = vms_vendor_app_can_send_confirmation_email($app_id);
        if (is_wp_error($allowed)) {
            return $allowed;
        }

        vms_vendor_app_set_confirmation_state($app_id, 'unconfirmed', array(
            'source' => sanitize_key((string) ($args['source'] ?? 'confirmation_email')),
        ));

        $token = vms_vendor_app_create_confirmation_token($app_id, array(
            'created_by_user_id' => absint($args['created_by_user_id'] ?? get_current_user_id()),
            'invalidate_reason' => sanitize_key((string) ($args['invalidate_reason'] ?? 'rotated')),
        ));
        if (is_wp_error($token)) {
            return $token;
        }

        $email = vms_vendor_app_get_confirmation_email($app_id);
        $name = trim((string) get_the_title($app_id));
        $portal_url = function_exists('vms_vendor_app_get_portal_page_url')
            ? vms_vendor_app_get_portal_page_url()
            : home_url('/vendor-portal/');
        $subject = __('Confirm your vendor application email', 'backstage-venue-manager');
        if ($name !== '') {
            /* translators: %s: human-readable value used in this message. */
            $subject = sprintf(__('Confirm your vendor application for %s', 'backstage-venue-manager'), $name);
        }

        $body_lines = array(
            __('Please confirm your email to submit your vendor application for review.', 'backstage-venue-manager'),
            '',
            __('Your application will not be reviewed until this step is complete.', 'backstage-venue-manager'),
            __('Confirm your email here:', 'backstage-venue-manager'),
            (string) ($token['confirm_url'] ?? ''),
            '',
            __('If you already have a website account with this email, we will attach the application to that account after confirmation.', 'backstage-venue-manager'),
            __('If you do not have a website account yet, we will prepare one for this email after confirmation and you can use the normal password reset flow later if needed.', 'backstage-venue-manager'),
            '',
            /* translators: %s: vendor portal URL. */
            sprintf(__('Vendor Portal: %s', 'backstage-venue-manager'), $portal_url),
            __('Please also check your spam or junk folder if you do not see future updates.', 'backstage-venue-manager'),
        );

        $sent = wp_mail($email, $subject, implode("\n", $body_lines));
        if (!$sent) {
            vms_vendor_app_invalidate_confirmation_token((int) ($token['token_id'] ?? 0), 'mail_failed');
            return new WP_Error('vms_vendor_app_confirm_mail_failed', __('We saved the application, but the confirmation email could not be sent right now.', 'backstage-venue-manager'));
        }

        vms_vendor_app_mark_confirmation_token_sent((int) ($token['token_id'] ?? 0));
        vms_vendor_app_note_confirmation_send($app_id, sanitize_key((string) ($args['source'] ?? 'confirmation_email')));
        vms_vendor_app_note_confirmation_ip_send($app_id);

        return $token;
    }
}

if (!function_exists('vms_vendor_app_refresh_confirmation_state')) {
    function vms_vendor_app_refresh_confirmation_state(int $app_id): string
    {
        $app_id = (int) $app_id;
        if ($app_id <= 0) {
            return 'confirmed';
        }

        $state = vms_vendor_app_get_confirmation_state_raw($app_id);
        if ($state !== 'unconfirmed') {
            return $state;
        }

        $latest_row = vms_vendor_app_get_latest_open_confirmation_token_row($app_id, false);
        if (is_array($latest_row)) {
            $expires_at = trim((string) ($latest_row['expires_at'] ?? ''));
            $expires_ts = $expires_at !== '' ? strtotime($expires_at . ' UTC') : false;
            if ($expires_ts !== false && $expires_ts < time()) {
                update_post_meta($app_id, vms_vendor_app_meta_key('confirmation_state') ?: '_vms_app_confirmation_state', 'expired');
                return 'expired';
            }
            return 'unconfirmed';
        }

        $last_sent_key = vms_vendor_app_meta_key('confirmation_last_sent_at') ?: '_vms_app_confirmation_last_sent_at';
        $last_sent_at = trim((string) get_post_meta($app_id, $last_sent_key, true));
        $last_sent_ts = vms_vendor_app_local_mysql_to_utc_timestamp($last_sent_at);
        if ($last_sent_ts !== false && ((time() - $last_sent_ts) >= vms_vendor_app_confirmation_window_seconds())) {
            update_post_meta($app_id, vms_vendor_app_meta_key('confirmation_state') ?: '_vms_app_confirmation_state', 'expired');
            return 'expired';
        }

        return 'unconfirmed';
    }
}

if (!function_exists('vms_vendor_app_get_confirmation_state')) {
    function vms_vendor_app_get_confirmation_state(int $app_id): string
    {
        $state = vms_vendor_app_refresh_confirmation_state($app_id);
        $all = vms_vendor_app_confirmation_states();
        return isset($all[$state]) ? $state : 'confirmed';
    }
}

if (!function_exists('vms_vendor_app_set_confirmation_state')) {
    function vms_vendor_app_set_confirmation_state(int $app_id, string $state, array $args = array()): void
    {
        $app_id = (int) $app_id;
        if ($app_id <= 0) {
            return;
        }

        $states = vms_vendor_app_confirmation_states();
        $state = sanitize_key($state);
        if (!isset($states[$state])) {
            $state = 'confirmed';
        }

        $state_key = vms_vendor_app_meta_key('confirmation_state') ?: '_vms_app_confirmation_state';
        $source_key = vms_vendor_app_meta_key('confirmation_source') ?: '_vms_app_confirmation_source';
        $confirmed_at_key = vms_vendor_app_meta_key('email_confirmed_at') ?: '_vms_app_email_confirmed_at';
        $review_ready_key = vms_vendor_app_meta_key('review_ready_at') ?: '_vms_app_review_ready_at';

        update_post_meta($app_id, $state_key, $state);
        if (!empty($args['source'])) {
            update_post_meta($app_id, $source_key, sanitize_key((string) $args['source']));
        }
        if ($state === 'confirmed') {
            update_post_meta($app_id, $confirmed_at_key, current_time('mysql'));
            update_post_meta($app_id, $review_ready_key, current_time('mysql'));
        }
    }
}

if (!function_exists('vms_vendor_app_mark_review_ready')) {
    function vms_vendor_app_mark_review_ready(int $app_id, string $source = 'email_token', int $resolved_user_id = 0): void
    {
        $app_id = (int) $app_id;
        if ($app_id <= 0) {
            return;
        }

        if ($resolved_user_id > 0) {
            vms_vendor_app_set_submitting_user_id($app_id, $resolved_user_id);
        }

        vms_vendor_app_set_confirmation_state($app_id, 'confirmed', array('source' => $source));
    }
}

if (!function_exists('vms_vendor_app_is_review_ready')) {
    function vms_vendor_app_is_review_ready(int $app_id): bool
    {
        return vms_vendor_app_get_status($app_id) === 'pending' && vms_vendor_app_get_confirmation_state($app_id) === 'confirmed';
    }
}

if (!function_exists('vms_vendor_app_current_user_matches_email')) {
    function vms_vendor_app_current_user_matches_email(string $email): bool
    {
        if (!is_user_logged_in()) {
            return false;
        }

        $email = sanitize_email($email);
        if ($email === '') {
            return false;
        }

        $user = wp_get_current_user();
        if (!($user instanceof WP_User)) {
            return false;
        }

        return strtolower($email) === strtolower(sanitize_email((string) $user->user_email));
    }
}

if (!function_exists('vms_vendor_app_resolve_or_create_user_for_email')) {
    function vms_vendor_app_resolve_or_create_user_for_email(int $app_id, string $email)
    {
        $email = sanitize_email($email);
        if ($email === '' || !is_email($email)) {
            return new WP_Error('vms_vendor_app_confirm_email_invalid', __('A valid email address is required.', 'backstage-venue-manager'));
        }

        $existing_user = get_user_by('email', $email);
        if ($existing_user instanceof WP_User) {
            return $existing_user;
        }

        $username = vms_vendor_app_generate_username_from_email($email);
        $password = wp_generate_password(32, true, true);
        $created = wp_create_user($username, $password, $email);
        if (is_wp_error($created)) {
            return new WP_Error('vms_vendor_app_confirm_user_create_failed', $created->get_error_message());
        }

        $user = get_user_by('id', (int) $created);
        if (!($user instanceof WP_User)) {
            return new WP_Error('vms_vendor_app_confirm_user_missing', __('The website account could not be loaded after creation.', 'backstage-venue-manager'));
        }

        $display_name = trim((string) get_post_meta($app_id, vms_vendor_app_meta_key('contact_name') ?: '_vms_app_contact_name', true));
        if ($display_name === '') {
            $display_name = trim((string) get_the_title($app_id));
        }
        if ($display_name !== '') {
            wp_update_user(array(
                'ID' => (int) $user->ID,
                'display_name' => $display_name,
                'nickname' => $display_name,
            ));
            $user = get_user_by('id', (int) $user->ID);
        }

        return $user;
    }
}

if (!function_exists('vms_vendor_app_send_review_ready_admin_notification')) {
    function vms_vendor_app_send_review_ready_admin_notification(int $app_id): bool
    {
        $app_id = (int) $app_id;
        if ($app_id <= 0) {
            return false;
        }

        $email = vms_vendor_app_get_confirmation_email($app_id);
        $name = trim((string) get_the_title($app_id));
        if ($name === '') {
            /* translators: %d: application ID. */
            $name = sprintf(__('Application #%d', 'backstage-venue-manager'), $app_id);
        }

        $to = apply_filters('vms_vendor_app_notify_email', get_option('admin_email'));
        /* translators: %s: vendor application ready for review. */
        $subject = sprintf(__('Vendor Application Ready for Review: %s', 'backstage-venue-manager'), $name);
        $vendor_type = sanitize_key((string) get_post_meta($app_id, '_vms_app_vendor_type', true));
        $contact_name = trim((string) get_post_meta($app_id, vms_vendor_app_meta_key('contact_name') ?: '_vms_app_contact_name', true));
        $submitted_user_id = vms_vendor_app_get_submitting_user_id($app_id);
        $submitted_user = $submitted_user_id > 0 ? get_userdata($submitted_user_id) : null;

        $body_lines = array(
            __('A vendor application is now ready for operator review.', 'backstage-venue-manager'),
            '',
            /* translators: %s: name. */
            sprintf(__('Name: %s', 'backstage-venue-manager'), $name),
            /* translators: %s: type. */
            sprintf(__('Type: %s', 'backstage-venue-manager'), vms_vendor_app_vendor_type_label($vendor_type)),
            /* translators: %s: email address. */
            sprintf(__('Email: %s', 'backstage-venue-manager'), $email),
            /* translators: %s: confirmation state. */
            sprintf(__('Confirmation State: %s', 'backstage-venue-manager'), vms_vendor_app_confirmation_state_label(vms_vendor_app_get_confirmation_state($app_id))),
        );

        if ($contact_name !== '') {
            /* translators: %s: primary contact. */
            $body_lines[] = sprintf(__('Primary Contact: %s', 'backstage-venue-manager'), $contact_name);
        }
        if ($submitted_user instanceof WP_User) {
            /* translators: 1: website username, 2: website user ID. */
            $body_lines[] = sprintf(__('Resolved Website User: %1$s (#%2$d)', 'backstage-venue-manager'), $submitted_user->user_login, (int) $submitted_user->ID);
        }

        $body_lines[] = '';
        $body_lines[] = __('Admin link:', 'backstage-venue-manager') . ' ' . admin_url('post.php?post=' . $app_id . '&action=edit');

        $sent = wp_mail($to, $subject, implode("\n", $body_lines));
        if (!$sent) {
            bvmgr_record_operational_issue('vendor_app_review_ready_mail_failed', array(
                'service'     => 'wp_mail',
                'operation'   => 'review_ready_notification',
                'status'      => 'failed',
                'entity_type' => 'vendor_application',
                'post_id'     => $app_id,
            ));
        }

        return (bool) $sent;
    }
}

if (!function_exists('vms_vendor_app_maybe_notify_review_ready')) {
    function vms_vendor_app_maybe_notify_review_ready(int $app_id): bool
    {
        $app_id = (int) $app_id;
        if ($app_id <= 0 || !vms_vendor_app_is_review_ready($app_id)) {
            return false;
        }

        $notified_key = vms_vendor_app_meta_key('review_ready_notified_at') ?: '_vms_app_review_ready_notified_at';
        $already_notified = trim((string) get_post_meta($app_id, $notified_key, true));
        if ($already_notified !== '') {
            return true;
        }

        $sent = vms_vendor_app_send_review_ready_admin_notification($app_id);
        if ($sent) {
            update_post_meta($app_id, $notified_key, current_time('mysql'));
        }

        return $sent;
    }
}

if (!function_exists('vms_vendor_app_confirmation_attempt_bucket_key')) {
    function vms_vendor_app_confirmation_attempt_bucket_key(string $raw_token): string
    {
        $ip = bvmgr_request_remote_addr();
        if ($ip === '') {
            $ip = 'unknown';
        }
        return 'vms_vendor_app_confirm_attempt_' . md5($ip . '|' . vms_vendor_app_hash_confirmation_token($raw_token));
    }
}

if (!function_exists('vms_vendor_app_confirmation_attempt_rate_limited')) {
    function vms_vendor_app_confirmation_attempt_rate_limited(string $raw_token): bool
    {
        $bucket = get_transient(vms_vendor_app_confirmation_attempt_bucket_key($raw_token));
        if (!is_array($bucket)) {
            return false;
        }
        return absint($bucket['count'] ?? 0) >= vms_vendor_app_confirmation_attempt_cap();
    }
}

if (!function_exists('vms_vendor_app_note_confirmation_attempt_failure')) {
    function vms_vendor_app_note_confirmation_attempt_failure(string $raw_token): void
    {
        $key = vms_vendor_app_confirmation_attempt_bucket_key($raw_token);
        $bucket = get_transient($key);
        if (!is_array($bucket)) {
            $bucket = array('count' => 0);
        }
        $bucket['count'] = absint($bucket['count'] ?? 0) + 1;
        set_transient($key, $bucket, vms_vendor_app_confirmation_attempt_window_seconds());
    }
}

if (!function_exists('vms_vendor_app_clear_confirmation_attempt_failures')) {
    function vms_vendor_app_clear_confirmation_attempt_failures(string $raw_token): void
    {
        delete_transient(vms_vendor_app_confirmation_attempt_bucket_key($raw_token));
    }
}

if (!function_exists('vms_vendor_app_validate_confirmation_token')) {
    function vms_vendor_app_validate_confirmation_token(string $raw_token)
    {
        $raw_token = trim($raw_token);
        if ($raw_token === '') {
            return new WP_Error('vms_vendor_app_confirm_token_missing', __('The confirmation link is missing or incomplete.', 'backstage-venue-manager'));
        }

        $row = vms_vendor_app_get_confirmation_token_row_by_hash(vms_vendor_app_hash_confirmation_token($raw_token));
        if (!is_array($row)) {
            return new WP_Error('vms_vendor_app_confirm_token_invalid', __('This confirmation link is invalid.', 'backstage-venue-manager'));
        }

        $app_id = absint($row['application_id'] ?? 0);
        if ($app_id <= 0) {
            $error = new WP_Error('vms_vendor_app_confirm_app_missing', __('This confirmation link is no longer valid.', 'backstage-venue-manager'));
            $error->add_data(array('app_id' => 0));
            return $error;
        }

        $consumed_at = trim((string) ($row['consumed_at'] ?? ''));
        if ($consumed_at !== '' && $consumed_at !== '0000-00-00 00:00:00') {
            $error = new WP_Error('vms_vendor_app_confirm_token_used', __('This confirmation link has already been used.', 'backstage-venue-manager'));
            $error->add_data(array('app_id' => $app_id));
            return $error;
        }

        $invalidated_at = trim((string) ($row['invalidated_at'] ?? ''));
        if ($invalidated_at !== '' && $invalidated_at !== '0000-00-00 00:00:00') {
            $error = new WP_Error('vms_vendor_app_confirm_token_invalidated', __('This confirmation link is no longer active.', 'backstage-venue-manager'));
            $error->add_data(array(
                'app_id' => $app_id,
                'reason' => sanitize_key((string) ($row['invalidated_reason'] ?? '')),
            ));
            return $error;
        }

        $expires_at = trim((string) ($row['expires_at'] ?? ''));
        $expires_ts = $expires_at !== '' ? strtotime($expires_at . ' UTC') : false;
        if ($expires_ts === false || $expires_ts < time()) {
            update_post_meta($app_id, vms_vendor_app_meta_key('confirmation_state') ?: '_vms_app_confirmation_state', 'expired');
            $error = new WP_Error('vms_vendor_app_confirm_token_expired', __('This confirmation link has expired.', 'backstage-venue-manager'));
            $error->add_data(array('app_id' => $app_id));
            return $error;
        }

        return array(
            'token_row' => $row,
            'app_id' => $app_id,
            'email' => sanitize_email((string) ($row['email'] ?? vms_vendor_app_get_confirmation_email($app_id))),
        );
    }
}

if (!function_exists('vms_vendor_app_process_confirmation')) {
    function vms_vendor_app_process_confirmation(string $raw_token)
    {
        if (vms_vendor_app_confirmation_attempt_rate_limited($raw_token)) {
            return new WP_Error('vms_vendor_app_confirm_rate_limited', __('Too many failed confirmation attempts came from this connection. Please wait a few minutes and try again.', 'backstage-venue-manager'));
        }

        $validation = vms_vendor_app_validate_confirmation_token($raw_token);
        if (is_wp_error($validation)) {
            vms_vendor_app_note_confirmation_attempt_failure($raw_token);
            return $validation;
        }

        $app_id = absint($validation['app_id'] ?? 0);
        $email = sanitize_email((string) ($validation['email'] ?? ''));
        $token_row = (array) ($validation['token_row'] ?? array());
        if ($app_id <= 0 || $email === '') {
            vms_vendor_app_note_confirmation_attempt_failure($raw_token);
            return new WP_Error('vms_vendor_app_confirm_invalid_context', __('The confirmation link is no longer valid.', 'backstage-venue-manager'));
        }

        $had_existing_user = get_user_by('email', $email) instanceof WP_User;
        $user = vms_vendor_app_resolve_or_create_user_for_email($app_id, $email);
        if (is_wp_error($user)) {
            vms_vendor_app_note_confirmation_attempt_failure($raw_token);
            return $user;
        }

        vms_vendor_app_mark_confirmation_token_consumed(absint($token_row['id'] ?? 0), (int) $user->ID);
        vms_vendor_app_invalidate_open_confirmation_tokens($app_id, 'confirmed');
        vms_vendor_app_mark_review_ready($app_id, 'email_token', (int) $user->ID);
        vms_vendor_app_maybe_notify_review_ready($app_id);
        vms_vendor_app_clear_confirmation_attempt_failures($raw_token);

        return array(
            'app_id' => $app_id,
            'user_id' => (int) $user->ID,
            'email' => $email,
            'reset_url' => vms_vendor_app_confirmation_reset_url(),
            'portal_url' => function_exists('vms_vendor_portal_page_url')
                ? vms_vendor_portal_page_url()
                : home_url('/vendor-portal/'),
            'had_existing_user' => $had_existing_user,
        );
    }
}

if (!function_exists('vms_vendor_app_normalize_dedupe_business_name')) {
    function vms_vendor_app_normalize_dedupe_business_name(string $name): string
    {
        $name = strtolower(trim(wp_strip_all_tags($name)));
        $name = preg_replace('/\s+/', ' ', $name);
        return (string) $name;
    }
}

if (!function_exists('vms_vendor_app_find_duplicate_open_application')) {
    function vms_vendor_app_find_duplicate_open_application(string $email, string $business_name): array
    {
        $email = sanitize_email($email);
        $business_name = vms_vendor_app_normalize_dedupe_business_name($business_name);
        if ($email === '' || $business_name === '') {
            return array();
        }

        $email_key = vms_vendor_app_meta_key('email') ?: '_vms_app_email';
        $app_ids = get_posts(array(
            'post_type' => vms_vendor_app_cpt_slugs(),
            'post_status' => array('publish', 'draft', 'pending', 'private'),
            'posts_per_page' => -1,
            'fields' => 'ids',
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => true,
            'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Duplicate prevention first performs one bounded exact email-meta lookup across application posts before comparing normalized business names and lifecycle states.
                array(
                    'key' => $email_key,
                    'value' => $email,
                    'compare' => '=',
                ),
            ),
        ));

        foreach ((array) $app_ids as $candidate_id) {
            $candidate_id = (int) $candidate_id;
            if ($candidate_id <= 0) {
                continue;
            }

            if (vms_vendor_app_normalize_dedupe_business_name((string) get_the_title($candidate_id)) !== $business_name) {
                continue;
            }

            $status = vms_vendor_app_get_status($candidate_id);
            if ($status === 'rejected') {
                continue;
            }

            $confirmation_state = vms_vendor_app_get_confirmation_state($candidate_id);
            if ($status === 'pending' && ($confirmation_state === 'unconfirmed' || $confirmation_state === 'expired')) {
                return array(
                    'app_id' => $candidate_id,
                    'status' => $status,
                    'confirmation_state' => $confirmation_state,
                    'duplicate_kind' => 'unconfirmed',
                );
            }

            if ($status === 'pending' && $confirmation_state === 'confirmed') {
                return array(
                    'app_id' => $candidate_id,
                    'status' => $status,
                    'confirmation_state' => $confirmation_state,
                    'duplicate_kind' => 'pending',
                );
            }

            if ($status === 'holding' && $confirmation_state === 'confirmed') {
                return array(
                    'app_id' => $candidate_id,
                    'status' => $status,
                    'confirmation_state' => $confirmation_state,
                    'duplicate_kind' => 'holding',
                );
            }

            if ($status === 'approved') {
                return array(
                    'app_id' => $candidate_id,
                    'status' => $status,
                    'confirmation_state' => $confirmation_state,
                    'duplicate_kind' => 'approved',
                );
            }
        }

        return array();
    }
}

if (!function_exists('vms_vendor_app_find_recent_application_for_user')) {
    function vms_vendor_app_find_recent_application_for_user(int $user_id): array
    {
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return array();
        }

        $user = get_userdata($user_id);
        if (!($user instanceof WP_User)) {
            return array();
        }

        $user_email = sanitize_email((string) $user->user_email);
        if ($user_email === '') {
            return array();
        }

        $email_key = vms_vendor_app_meta_key('email') ?: '_vms_app_email';
        $app_ids = get_posts(array(
            'post_type' => vms_vendor_app_cpt_slugs(),
            'post_status' => array('publish', 'draft', 'pending', 'private'),
            'posts_per_page' => -1,
            'fields' => 'ids',
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => true,
            'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Applicant continuity requires the finite application set for the authenticated user's exact email before evaluating current status and confirmation state.
                array(
                    'key' => $email_key,
                    'value' => $user_email,
                    'compare' => '=',
                ),
            ),
        ));

        foreach ((array) $app_ids as $app_id) {
            $app_id = (int) $app_id;
            if ($app_id <= 0) {
                continue;
            }

            $status = vms_vendor_app_get_status($app_id);
            $confirmation_state = vms_vendor_app_get_confirmation_state($app_id);

            if ($status === 'pending' && ($confirmation_state === 'unconfirmed' || $confirmation_state === 'expired')) {
                return array(
                    'kind' => 'unconfirmed',
                    'app_id' => $app_id,
                    'status' => $status,
                    'confirmation_state' => $confirmation_state,
                );
            }

            if ($status === 'pending' && $confirmation_state === 'confirmed') {
                return array(
                    'kind' => 'pending_review',
                    'app_id' => $app_id,
                    'status' => $status,
                    'confirmation_state' => $confirmation_state,
                );
            }

            if ($status === 'holding' && $confirmation_state === 'confirmed') {
                return array(
                    'kind' => 'holding',
                    'app_id' => $app_id,
                    'status' => $status,
                    'confirmation_state' => $confirmation_state,
                );
            }
        }

        return array();
    }
}

if (!function_exists('vms_vendor_app_public_state_url')) {
    function vms_vendor_app_public_state_url(string $flag, int $app_id = 0, array $extra_args = array()): string
    {
        $url = function_exists('vms_vendor_app_get_application_page_url')
            ? vms_vendor_app_get_application_page_url()
            : home_url('/vendor-application/');
        $args = array_merge(array('vms_app' => $flag), $extra_args);
        if ($app_id > 0) {
            $args['vms_app_ref'] = vms_vendor_app_get_public_lookup_key($app_id);
        }
        return (string) add_query_arg($args, $url);
    }
}

if (!function_exists('vms_vendor_app_confirmation_allowed_html')) {
    function vms_vendor_app_confirmation_allowed_html(): array
    {
        return array(
            'a' => array(
                'class' => true,
                'href' => true,
            ),
            'button' => array(
                'class' => true,
                'type' => true,
            ),
            'div' => array(
                'class' => true,
            ),
            'form' => array(
                'action' => true,
                'class' => true,
                'method' => true,
            ),
            'h2' => array(),
            'input' => array(
                'id' => true,
                'name' => true,
                'type' => true,
                'value' => true,
            ),
            'li' => array(),
            'ol' => array(
                'class' => true,
            ),
            'p' => array(
                'class' => true,
            ),
            'section' => array(
                'class' => true,
            ),
            'span' => array(
                'class' => true,
            ),
            'strong' => array(),
        );
    }
}

if (!function_exists('vms_vendor_app_render_resend_confirmation_form')) {
    function vms_vendor_app_render_resend_confirmation_form(int $app_id, string $return_url = '', string $button_label = ''): string
    {
        $app_id = (int) $app_id;
        if ($app_id <= 0 || vms_vendor_app_get_confirmation_state($app_id) === 'confirmed') {
            return '';
        }

        $return_url = trim($return_url);
        if ($return_url === '') {
            $return_url = vms_vendor_app_public_state_url('confirm_pending', $app_id);
        }
        $app_ref = vms_vendor_app_get_public_lookup_key($app_id);
        $button_label = $button_label !== '' ? $button_label : __('Resend confirmation email', 'backstage-venue-manager');

        ob_start();
        ?>
        <form class="vms-vendor-apply-confirmation__resend" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="vms_vendor_app_resend_confirmation">
            <input type="hidden" name="vms_app_ref" value="<?php echo esc_attr($app_ref); ?>">
            <input type="hidden" name="return_url" value="<?php echo esc_attr($return_url); ?>">
            <?php wp_nonce_field('vms_vendor_app_resend_confirmation_' . $app_ref, '_vms_vendor_app_resend_nonce'); ?>
            <button type="submit" class="button button-secondary"><?php echo esc_html($button_label); ?></button>
        </form>
        <?php
        return wp_kses((string) ob_get_clean(), vms_vendor_app_confirmation_allowed_html());
    }
}

if (!function_exists('vms_vendor_apply_render_confirmation_pending_screen')) {
    function vms_vendor_apply_render_confirmation_pending_screen(int $app_id, array $args = array()): string
    {
        $app_id = (int) $app_id;
        if ($app_id <= 0) {
            return vms_vendor_apply_render_notice('error', __('We could not find that application.', 'backstage-venue-manager'), __('Please submit the form again if needed.', 'backstage-venue-manager'));
        }

        $notice_key = sanitize_key((string) ($args['notice'] ?? 'sent'));
        $state = vms_vendor_app_get_confirmation_state($app_id);
        $portal_url = function_exists('vms_vendor_portal_page_url')
            ? vms_vendor_portal_page_url()
            : home_url('/vendor-portal/');
        $apply_url = function_exists('vms_vendor_app_get_application_page_url')
            ? vms_vendor_app_get_application_page_url()
            : home_url('/vendor-application/');

        $notice_headline = __('Check your email to continue.', 'backstage-venue-manager');
        $notice_body = __('We sent a confirmation link to the email address on the application.', 'backstage-venue-manager');
        if ($notice_key === 'resent') {
            $notice_body = __('We sent a new confirmation link to the email address on the application.', 'backstage-venue-manager');
        } elseif ($notice_key === 'cooldown') {
            $notice_headline = __('A confirmation email was sent recently.', 'backstage-venue-manager');
            $notice_body = __('Please wait a few minutes before requesting another confirmation email.', 'backstage-venue-manager');
        } elseif ($notice_key === 'daily_cap') {
            $notice_headline = __('Confirmation email limit reached.', 'backstage-venue-manager');
            $notice_body = __('We have already sent the maximum number of confirmation emails for this application today. Please try again later.', 'backstage-venue-manager');
        } elseif ($notice_key === 'ip_throttle') {
            $notice_headline = __('Too many confirmation requests came from this connection.', 'backstage-venue-manager');
            $notice_body = __('Please wait and try again later.', 'backstage-venue-manager');
        } elseif ($notice_key === 'mail_failed') {
            $notice_headline = __('We saved the application, but the confirmation email could not be sent.', 'backstage-venue-manager');
            $notice_body = __('Please try the resend button below, or contact us if the message still does not arrive.', 'backstage-venue-manager');
        } elseif ($notice_key === 'expired' || $state === 'expired') {
            $notice_headline = __('That confirmation link has expired.', 'backstage-venue-manager');
            $notice_body = __('Please request a new confirmation email below so we can move your application into review.', 'backstage-venue-manager');
        }

        ob_start();
        ?>
        <section class="vms-vendor-apply-confirmation">
            <div class="vms-vendor-apply-confirmation__notice vms-notice vms-notice-warning">
                <p><strong><?php echo esc_html($notice_headline); ?></strong></p>
                <p><?php echo esc_html($notice_body); ?></p>
            </div>

            <div class="vms-vendor-apply-confirmation__card">
                <span class="vms-vendor-apply-confirmation__kicker"><?php echo esc_html__('One more step', 'backstage-venue-manager'); ?></span>
                <h2><?php echo esc_html__('Confirm your email to submit your vendor application for review', 'backstage-venue-manager'); ?></h2>
                <p><?php echo esc_html__('Your application will not be reviewed until you confirm the email address entered on the form.', 'backstage-venue-manager'); ?></p>
                <ol class="vms-vendor-apply-confirmation__steps">
                    <li><?php echo esc_html__('Open the confirmation email and click the confirmation link.', 'backstage-venue-manager'); ?></li>
                    <li><?php echo esc_html__('Watch your spam or junk folder if the message does not show up right away.', 'backstage-venue-manager'); ?></li>
                    <li><?php echo esc_html__('After confirmation, the application moves into the real operator review queue. This does not mean approved.', 'backstage-venue-manager'); ?></li>
                </ol>
                <div class="vms-vendor-apply-confirmation__actions">
                    <?php echo wp_kses(vms_vendor_app_render_resend_confirmation_form($app_id, vms_vendor_app_public_state_url('confirm_pending', $app_id), __('Resend confirmation email', 'backstage-venue-manager')), vms_vendor_app_confirmation_allowed_html()); ?>
                    <a class="button" href="<?php echo esc_url($apply_url); ?>"><?php echo esc_html__('View Application Form', 'backstage-venue-manager'); ?></a>
                    <a class="button" href="<?php echo esc_url($portal_url); ?>"><?php echo esc_html__('Open Vendor Portal', 'backstage-venue-manager'); ?></a>
                </div>
            </div>
        </section>
        <?php
        return wp_kses((string) ob_get_clean(), vms_vendor_app_confirmation_allowed_html());
    }
}

if (!function_exists('vms_vendor_apply_render_existing_status_screen')) {
    function vms_vendor_apply_render_existing_status_screen(int $app_id, string $kind): string
    {
        $app_id = (int) $app_id;
        $kind = sanitize_key($kind);
        $portal_url = function_exists('vms_vendor_portal_page_url')
            ? vms_vendor_portal_page_url()
            : home_url('/vendor-portal/');
        $apply_url = function_exists('vms_vendor_app_get_application_page_url')
            ? vms_vendor_app_get_application_page_url()
            : home_url('/vendor-application/');

        $headline = __('We already have your application.', 'backstage-venue-manager');
        $body = __('Please watch your email for updates.', 'backstage-venue-manager');
        if ($kind === 'pending') {
            $body = __('We already have this vendor application and it is pending operator review.', 'backstage-venue-manager');
        } elseif ($kind === 'holding') {
            $body = __('We already have this vendor application on file. Please watch your email for any follow-up.', 'backstage-venue-manager');
        } elseif ($kind === 'approved') {
            $headline = __('We already have an approved application for this business.', 'backstage-venue-manager');
            $body = __('If you already have Vendor Portal access, use the Vendor Portal. If your portal access is not connected yet, please reply to the most recent onboarding email or contact us for help.', 'backstage-venue-manager');
        }

        ob_start();
        ?>
        <section class="vms-vendor-apply-confirmation">
            <div class="vms-vendor-apply-confirmation__notice vms-notice vms-notice-success">
                <p><strong><?php echo esc_html($headline); ?></strong></p>
                <p><?php echo esc_html($body); ?></p>
            </div>
            <div class="vms-vendor-apply-confirmation__card">
                <span class="vms-vendor-apply-confirmation__kicker"><?php echo esc_html__('Current status', 'backstage-venue-manager'); ?></span>
                <h2><?php echo esc_html(vms_vendor_app_statuses()[vms_vendor_app_get_status($app_id)] ?? __('Application', 'backstage-venue-manager')); ?></h2>
                <p><?php echo esc_html__('Vendor tools live in the Vendor Portal. WooCommerce My Account can still show customer or ticket information and is not the vendor workflow screen.', 'backstage-venue-manager'); ?></p>
                <div class="vms-vendor-apply-confirmation__actions">
                    <a class="button" href="<?php echo esc_url($portal_url); ?>"><?php echo esc_html__('Open Vendor Portal', 'backstage-venue-manager'); ?></a>
                    <a class="button button-secondary" href="<?php echo esc_url($apply_url); ?>"><?php echo esc_html__('Back to Application Form', 'backstage-venue-manager'); ?></a>
                </div>
            </div>
        </section>
        <?php
        return wp_kses((string) ob_get_clean(), vms_vendor_app_confirmation_allowed_html());
    }
}

if (!function_exists('vms_vendor_app_render_portal_applicant_panel')) {
    function vms_vendor_app_render_portal_applicant_panel(int $user_id, string $base_url = ''): string
    {
        $state = vms_vendor_app_find_recent_application_for_user($user_id);
        if (empty($state['kind'])) {
            return '';
        }

        $app_id = (int) ($state['app_id'] ?? 0);
        $return_url = $base_url !== '' ? $base_url : home_url('/vendor-portal/');
        $portal_url = function_exists('vms_vendor_portal_page_url')
            ? vms_vendor_portal_page_url()
            : home_url('/vendor-portal/');

        ob_start();
        echo '<div class="vms-portal-auth-wrap">';
        echo '<div class="vms-portal-auth-col vms-portal-auth-apply vms-vendor-applicant-state">';
        if ($state['kind'] === 'unconfirmed') {
            echo '<span class="vms-portal-auth-eyebrow">' . esc_html__('Application awaiting confirmation', 'backstage-venue-manager') . '</span>';
            echo '<h2>' . esc_html__('Confirm your email before we can review your application', 'backstage-venue-manager') . '</h2>';
            echo '<p class="vms-portal-auth-copy">' . esc_html__('Your application is saved, but it does not enter the operator review queue until you confirm the email address used on the form.', 'backstage-venue-manager') . '</p>';
            echo '<p class="vms-portal-auth-hint">' . esc_html__('Please check your inbox, spam, and junk folders. If needed, request another confirmation email below.', 'backstage-venue-manager') . '</p>';
            echo '<div class="vms-vendor-apply-confirmation__actions">';
            echo wp_kses(vms_vendor_app_render_resend_confirmation_form($app_id, $return_url, __('Resend confirmation email', 'backstage-venue-manager')), vms_vendor_app_confirmation_allowed_html());
            echo '<a class="button" href="' . esc_url($portal_url) . '">' . esc_html__('Vendor Portal Home', 'backstage-venue-manager') . '</a>';
            echo '</div>';
        } elseif ($state['kind'] === 'pending_review') {
            echo '<span class="vms-portal-auth-eyebrow">' . esc_html__('Application in review', 'backstage-venue-manager') . '</span>';
            echo '<h2>' . esc_html__('Application pending review', 'backstage-venue-manager') . '</h2>';
            echo '<p class="vms-portal-auth-copy">' . esc_html__('Your email is confirmed and the application is now in the operator review queue. This does not mean approved yet.', 'backstage-venue-manager') . '</p>';
            echo '<p class="vms-portal-auth-hint">' . esc_html__('Please watch your email for the review outcome and next-step instructions.', 'backstage-venue-manager') . '</p>';
        } elseif ($state['kind'] === 'holding') {
            echo '<span class="vms-portal-auth-eyebrow">' . esc_html__('Application on file', 'backstage-venue-manager') . '</span>';
            echo '<h2>' . esc_html__('Application currently on file', 'backstage-venue-manager') . '</h2>';
            echo '<p class="vms-portal-auth-copy">' . esc_html__('We still have your application on file. Please watch your email for any future follow-up from the venue.', 'backstage-venue-manager') . '</p>';
        }
        echo '</div>';
        echo '</div>';
        return wp_kses((string) ob_get_clean(), vms_vendor_app_confirmation_allowed_html());
    }
}

if (!function_exists('vms_vendor_app_redirect_after_resend')) {
    function vms_vendor_app_redirect_after_resend(int $app_id, string $return_url, string $notice_key, string $message = '', string $type = 'success'): void
    {
        $return_url = bvmgr_request_local_redirect(
            vms_vendor_app_public_state_url('confirm_pending', $app_id, array('vms_app_notice' => $notice_key)),
            $return_url
        );

        if (is_user_logged_in() && function_exists('vms_vendor_portal_set_flash')) {
            $user_id = get_current_user_id();
            if ($user_id > 0) {
                $default_messages = array(
                    'resent' => __('We sent a new confirmation email. Please check your inbox, spam, or junk folders.', 'backstage-venue-manager'),
                    'cooldown' => __('A confirmation email was sent recently. Please wait a few minutes before trying again.', 'backstage-venue-manager'),
                    'daily_cap' => __('We already sent the maximum number of confirmation emails for this application today. Please try again later.', 'backstage-venue-manager'),
                    'ip_throttle' => __('Too many confirmation requests came from this connection. Please wait and try again later.', 'backstage-venue-manager'),
                    'mail_failed' => __('We could not send the confirmation email right now. Please try again later.', 'backstage-venue-manager'),
                    'already_confirmed' => __('This application is already confirmed and ready for review.', 'backstage-venue-manager'),
                );
                if ($message === '') {
                    $message = (string) ($default_messages[$notice_key] ?? __('Confirmation email status updated.', 'backstage-venue-manager'));
                }
                vms_vendor_portal_set_flash($user_id, array('type' => $type, 'message' => $message));
                wp_safe_redirect($return_url);
                exit;
            }
        }

        $redirect_url = add_query_arg(array(
            'vms_app' => 'confirm_pending',
            'vms_app_notice' => $notice_key,
            'vms_app_ref' => vms_vendor_app_get_public_lookup_key($app_id),
        ), $return_url);
        wp_safe_redirect($redirect_url);
        exit;
    }
}

if (!function_exists('vms_vendor_app_handle_resend_confirmation')) {
    function vms_vendor_app_handle_resend_confirmation(): void
    {
        if (strtoupper(bvmgr_request_method('get')) !== 'POST') {
            wp_die(esc_html__('Security check failed.', 'backstage-venue-manager'));
        }

        $app_ref = vms_vendor_app_resend_request_text_field('vms_app_ref');
        $nonce = (isset($_POST['_vms_vendor_app_resend_nonce']) && !is_array($_POST['_vms_vendor_app_resend_nonce']))
            ? sanitize_text_field(wp_unslash((string) $_POST['_vms_vendor_app_resend_nonce']))
            : '';

        if ($app_ref === '' || !$nonce || !wp_verify_nonce($nonce, 'vms_vendor_app_resend_confirmation_' . $app_ref)) {
            wp_die(esc_html__('Security check failed.', 'backstage-venue-manager'));
        }

        $return_url = bvmgr_request_local_redirect('', bvmgr_request_read_scalar($_POST, 'return_url'));
        $app_id = vms_vendor_app_find_application_by_public_lookup_key($app_ref);
        if ($app_id <= 0) {
            wp_safe_redirect(function_exists('vms_vendor_app_get_application_page_url') ? vms_vendor_app_get_application_page_url() : home_url('/vendor-application/'));
            exit;
        }

        if (vms_vendor_app_get_confirmation_state($app_id) === 'confirmed') {
            vms_vendor_app_redirect_after_resend($app_id, $return_url, 'already_confirmed');
        }

        $result = vms_vendor_app_send_confirmation_email($app_id, array(
            'source' => 'resend_confirmation',
            'invalidate_reason' => 'resend_rotation',
        ));

        if (is_wp_error($result)) {
            $code = $result->get_error_code();
            $notice_key = 'mail_failed';
            if ($code === 'vms_vendor_app_confirm_cooldown') {
                $notice_key = 'cooldown';
            } elseif ($code === 'vms_vendor_app_confirm_daily_cap') {
                $notice_key = 'daily_cap';
            } elseif ($code === 'vms_vendor_app_confirm_ip_throttle') {
                $notice_key = 'ip_throttle';
            } elseif ($code === 'vms_vendor_app_confirm_already_confirmed') {
                $notice_key = 'already_confirmed';
            }
            vms_vendor_app_redirect_after_resend($app_id, $return_url, $notice_key, $result->get_error_message(), 'warning');
        }

        vms_vendor_app_redirect_after_resend($app_id, $return_url, 'resent');
    }
}
add_action('admin_post_nopriv_vms_vendor_app_resend_confirmation', 'vms_vendor_app_handle_resend_confirmation');
add_action('admin_post_vms_vendor_app_resend_confirmation', 'vms_vendor_app_handle_resend_confirmation');

if (!function_exists('vms_vendor_app_render_confirmation_shell')) {
    function vms_vendor_app_render_confirmation_shell(string $title, string $content): void
    {
        status_header(200);
        nocache_headers();

        if (function_exists('wp_enqueue_style')) {
            wp_enqueue_style('vms-portal');
        }

        echo '<!doctype html>';
        echo '<html ';
        language_attributes();
        echo '>';
        echo '<head>';
        echo '<meta charset="' . esc_attr(get_bloginfo('charset')) . '">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<meta name="robots" content="noindex,nofollow,noarchive">';
        echo '<title>' . esc_html($title) . '</title>';
        wp_head();
        echo '</head>';
        echo '<body class="vms-vendor-app-confirmation-page">';
        echo '<main class="vms-vendor-apply-flow vms-vendor-app-confirmation-page__main">';
        echo wp_kses($content, vms_vendor_app_confirmation_allowed_html());
        echo '</main>';
        wp_footer();
        echo '</body></html>';
        exit;
    }
}

if (!function_exists('vms_vendor_app_maybe_render_confirmation_page')) {
    function vms_vendor_app_maybe_render_confirmation_page(): void
    {
        if (!vms_vendor_app_is_confirmation_request()) {
            return;
        }

        $request_method = strtoupper(bvmgr_request_method('get'));
        if ($request_method === 'HEAD') {
            status_header(200);
            nocache_headers();
            header('X-Robots-Tag: noindex, nofollow, noarchive', true);
            exit;
        }
        if ($request_method !== 'GET') {
            status_header(405);
            nocache_headers();
            header('Allow: GET, HEAD', true);
            header('X-Robots-Tag: noindex, nofollow, noarchive', true);
            exit;
        }

        $token = sanitize_text_field(vms_vendor_app_confirmation_query_value('token'));
        $title = __('Vendor Application Confirmation', 'backstage-venue-manager');
        $content = '';

        if ($token === '') {
            $content = vms_vendor_apply_render_notice('error', __('This confirmation link is incomplete.', 'backstage-venue-manager'), __('Please use the latest confirmation email or request another confirmation link from the application screen.', 'backstage-venue-manager'));
            vms_vendor_app_render_confirmation_shell($title, $content);
        }

        $result = vms_vendor_app_process_confirmation($token);
        if (is_wp_error($result)) {
            $data = $result->get_error_data();
            $app_id = is_array($data) ? absint($data['app_id'] ?? 0) : 0;
            $code = $result->get_error_code();

            if ($code === 'vms_vendor_app_confirm_token_expired') {
                $content = vms_vendor_apply_render_confirmation_pending_screen($app_id, array('notice' => 'expired'));
            } elseif ($code === 'vms_vendor_app_confirm_token_used' || ($code === 'vms_vendor_app_confirm_token_invalidated' && is_array($data) && sanitize_key((string) ($data['reason'] ?? '')) === 'confirmed')) {
                $portal_url = function_exists('vms_vendor_portal_page_url') ? vms_vendor_portal_page_url() : home_url('/vendor-portal/');
                $reset_url = vms_vendor_app_confirmation_reset_url();
                ob_start();
                ?>
                <section class="vms-vendor-apply-confirmation">
                    <div class="vms-vendor-apply-confirmation__notice vms-notice vms-notice-success">
                        <p><strong><?php echo esc_html__('That email is already confirmed.', 'backstage-venue-manager'); ?></strong></p>
                        <p><?php echo esc_html__('Your application has already passed the email confirmation step. This does not mean approved yet.', 'backstage-venue-manager'); ?></p>
                    </div>
                    <div class="vms-vendor-apply-confirmation__card">
                        <span class="vms-vendor-apply-confirmation__kicker"><?php echo esc_html__('Next steps', 'backstage-venue-manager'); ?></span>
                        <h2><?php echo esc_html__('Application already confirmed', 'backstage-venue-manager'); ?></h2>
                        <p><?php echo esc_html__('You can sign in to the Vendor Portal to check for pending-review messaging, or use the normal password reset flow if needed.', 'backstage-venue-manager'); ?></p>
                        <div class="vms-vendor-apply-confirmation__actions">
                            <a class="button" href="<?php echo esc_url($portal_url); ?>"><?php echo esc_html__('Open Vendor Portal', 'backstage-venue-manager'); ?></a>
                            <a class="button button-secondary" href="<?php echo esc_url($reset_url); ?>"><?php echo esc_html__('Reset Password', 'backstage-venue-manager'); ?></a>
                        </div>
                    </div>
                </section>
                <?php
                $content = (string) ob_get_clean();
            } elseif ($code === 'vms_vendor_app_confirm_token_invalidated' && $app_id > 0) {
                $content = vms_vendor_apply_render_confirmation_pending_screen($app_id, array('notice' => 'expired'));
            } else {
                $content = vms_vendor_apply_render_notice('error', __('This confirmation link is not valid.', 'backstage-venue-manager'), $result->get_error_message());
            }

            vms_vendor_app_render_confirmation_shell($title, $content);
        }

        $portal_url = function_exists('vms_vendor_portal_page_url')
            ? vms_vendor_portal_page_url()
            : home_url('/vendor-portal/');
        $reset_url = (string) ($result['reset_url'] ?? vms_vendor_app_confirmation_reset_url());
        $had_existing_user = !empty($result['had_existing_user']);

        ob_start();
        ?>
        <section class="vms-vendor-apply-confirmation">
            <div class="vms-vendor-apply-confirmation__notice vms-notice vms-notice-success">
                <p><strong><?php echo esc_html__('Email confirmed. Your application is now ready for review.', 'backstage-venue-manager'); ?></strong></p>
                <p><?php echo esc_html__('This confirms the email step only. It does not mean approved yet.', 'backstage-venue-manager'); ?></p>
            </div>

            <div class="vms-vendor-apply-confirmation__card">
                <span class="vms-vendor-apply-confirmation__kicker"><?php echo esc_html__('Review queue', 'backstage-venue-manager'); ?></span>
                <h2><?php echo esc_html__('Application ready for review', 'backstage-venue-manager'); ?></h2>
                <p><?php echo esc_html__('The application is now in the operator review queue. Please watch your email, including spam or junk folders, for the review outcome and next-step instructions.', 'backstage-venue-manager'); ?></p>
                <ol class="vms-vendor-apply-confirmation__steps">
                    <?php if ($had_existing_user) : ?>
                        <li><?php echo esc_html__('Use your existing website account for this email if you want to sign in to the Vendor Portal while the application is under review.', 'backstage-venue-manager'); ?></li>
                    <?php else : ?>
                        <li><?php echo esc_html__('A website account was prepared for this email. Use the normal password reset flow below the first time you want to sign in to the Vendor Portal.', 'backstage-venue-manager'); ?></li>
                    <?php endif; ?>
                    <li><?php echo esc_html__('Vendor tools live in the Vendor Portal after approval. WooCommerce My Account can still show customer or ticket information.', 'backstage-venue-manager'); ?></li>
                </ol>
                <div class="vms-vendor-apply-confirmation__actions">
                    <a class="button" href="<?php echo esc_url($portal_url); ?>"><?php echo esc_html__('Open Vendor Portal', 'backstage-venue-manager'); ?></a>
                    <a class="button button-secondary" href="<?php echo esc_url($reset_url); ?>"><?php echo esc_html__('Reset Password', 'backstage-venue-manager'); ?></a>
                </div>
            </div>
        </section>
        <?php
        $content = (string) ob_get_clean();
        vms_vendor_app_render_confirmation_shell($title, $content);
    }
}
add_action('template_redirect', 'vms_vendor_app_maybe_render_confirmation_page', 1);

if (!function_exists('vms_vendor_app_expire_stale_confirmations')) {
    function vms_vendor_app_expire_stale_confirmations(): void
    {
        $lock_key = 'vms_vendor_app_expire_stale_confirmations_lock';
        if (get_transient($lock_key)) {
            return;
        }
        set_transient($lock_key, '1', 15 * MINUTE_IN_SECONDS);

        $state_key = vms_vendor_app_meta_key('confirmation_state') ?: '_vms_app_confirmation_state';
        $last_sent_key = vms_vendor_app_meta_key('confirmation_last_sent_at') ?: '_vms_app_confirmation_last_sent_at';
        $threshold = gmdate('Y-m-d H:i:s', time() - vms_vendor_app_confirmation_window_seconds());

        $app_ids = get_posts(array(
            'post_type' => vms_vendor_app_cpt_slugs(),
            'post_status' => array('publish', 'draft', 'pending', 'private'),
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- The scheduled expiry transition must find all unconfirmed applications whose bounded last-sent timestamp is older than the confirmation window.
                'relation' => 'AND',
                array(
                    'key' => $state_key,
                    'value' => 'unconfirmed',
                    'compare' => '=',
                ),
                array(
                    'key' => $last_sent_key,
                    'value' => $threshold,
                    'type' => 'DATETIME',
                    'compare' => '<=',
                ),
            ),
        ));

        foreach ((array) $app_ids as $app_id) {
            update_post_meta((int) $app_id, $state_key, 'expired');
        }
    }
}
add_action('init', 'vms_vendor_app_expire_stale_confirmations', 21);

if (!function_exists('vms_vendor_app_backfill_confirmation_state_once')) {
    function vms_vendor_app_backfill_confirmation_state_once(): void
    {
        $marker = 'vms_vendor_app_confirmation_backfill_02424710';
        if (get_option($marker)) {
            return;
        }

        $state_key = vms_vendor_app_meta_key('confirmation_state') ?: '_vms_app_confirmation_state';
        $confirmed_at_key = vms_vendor_app_meta_key('email_confirmed_at') ?: '_vms_app_email_confirmed_at';
        $review_ready_key = vms_vendor_app_meta_key('review_ready_at') ?: '_vms_app_review_ready_at';
        $source_key = vms_vendor_app_meta_key('confirmation_source') ?: '_vms_app_confirmation_source';

        $app_ids = get_posts(array(
            'post_type' => vms_vendor_app_cpt_slugs(),
            'post_status' => 'any',
            'fields' => 'ids',
            'posts_per_page' => -1,
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ));

        foreach ((array) $app_ids as $app_id) {
            $app_id = (int) $app_id;
            if ($app_id <= 0) {
                continue;
            }

            $existing_state = trim((string) get_post_meta($app_id, $state_key, true));
            if ($existing_state === '') {
                update_post_meta($app_id, $state_key, 'confirmed');
            }

            $submitted_at = trim((string) get_post_meta($app_id, '_vms_app_submitted_at', true));
            if ($submitted_at === '') {
                $post = get_post($app_id);
                $submitted_at = ($post instanceof WP_Post && !empty($post->post_date)) ? (string) $post->post_date : current_time('mysql');
            }

            if (trim((string) get_post_meta($app_id, $confirmed_at_key, true)) === '') {
                update_post_meta($app_id, $confirmed_at_key, $submitted_at);
            }
            if (trim((string) get_post_meta($app_id, $review_ready_key, true)) === '') {
                update_post_meta($app_id, $review_ready_key, $submitted_at);
            }
            if (trim((string) get_post_meta($app_id, $source_key, true)) === '') {
                update_post_meta($app_id, $source_key, 'legacy_backfill');
            }

            if (vms_vendor_app_get_submitting_user_id($app_id) <= 0) {
                $vendor_id = (int) get_post_meta($app_id, '_vms_vendor_id', true);
                $candidate_user_ids = array();
                if ($vendor_id > 0 && function_exists('vms_vendor_user_links_get_by_vendor')) {
                    foreach ((array) vms_vendor_user_links_get_by_vendor($vendor_id, false) as $row) {
                        $candidate_user_id = absint($row['user_id'] ?? 0);
                        if ($candidate_user_id > 0 && !in_array($candidate_user_id, $candidate_user_ids, true)) {
                            $candidate_user_ids[] = $candidate_user_id;
                        }
                    }
                }
                if ($vendor_id > 0) {
                    $legacy_user_id = (int) get_post_meta($vendor_id, defined('BVMGR_VENDOR_PRIMARY_USER_META_KEY') ? BVMGR_VENDOR_PRIMARY_USER_META_KEY : '_vms_vendor_user_id', true);
                    if ($legacy_user_id > 0 && !in_array($legacy_user_id, $candidate_user_ids, true)) {
                        $candidate_user_ids[] = $legacy_user_id;
                    }
                }
                if (count($candidate_user_ids) === 1) {
                    vms_vendor_app_set_submitting_user_id($app_id, (int) $candidate_user_ids[0]);
                }
            }
        }

        add_option($marker, current_time('mysql'), '', false);
    }
}
add_action('admin_init', 'vms_vendor_app_backfill_confirmation_state_once', 18);

if (!function_exists('vms_vendor_app_invalidate_tokens_when_removed')) {
    function vms_vendor_app_invalidate_tokens_when_removed(int $post_id): void
    {
        $post = get_post($post_id);
        if (!($post instanceof WP_Post) || !in_array((string) $post->post_type, vms_vendor_app_cpt_slugs(), true)) {
            return;
        }

        vms_vendor_app_invalidate_open_confirmation_tokens((int) $post_id, 'removed');
    }
}
add_action('trashed_post', 'vms_vendor_app_invalidate_tokens_when_removed');
add_action('before_delete_post', 'vms_vendor_app_invalidate_tokens_when_removed');
