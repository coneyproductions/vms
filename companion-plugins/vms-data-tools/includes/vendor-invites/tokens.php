<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_dt_vio_hash_token')) {
    function vms_dt_vio_hash_token(string $raw_token): string
    {
        return hash('sha256', wp_salt('auth') . '|' . $raw_token);
    }
}

if (!function_exists('vms_dt_vio_generate_raw_token')) {
    function vms_dt_vio_generate_raw_token(): string
    {
        try {
            $bytes = random_bytes(32);
        } catch (Exception $e) {
            $bytes = wp_generate_password(48, true, true);
            return sanitize_text_field((string) $bytes);
        }

        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}

if (!function_exists('vms_dt_vio_invalidate_open_tokens_for_vendor')) {
    function vms_dt_vio_invalidate_open_tokens_for_vendor(int $vendor_id): void
    {
        global $wpdb;

        $vendor_id = absint($vendor_id);
        if ($vendor_id <= 0) {
            return;
        }

        $table = vms_dt_vio_claim_tokens_table();
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table}
                 SET used_at = %s
                 WHERE vendor_id = %d
                   AND used_at IS NULL",
                vms_dt_vio_now_gmt_mysql(),
                $vendor_id
            )
        );
    }
}

if (!function_exists('vms_dt_vio_create_claim_token')) {
    /**
     * @param array<string,mixed> $args
     * @return array<string,mixed>|WP_Error
     */
    function vms_dt_vio_create_claim_token(int $vendor_id, array $args = array())
    {
        global $wpdb;

        $vendor_id = absint($vendor_id);
        if ($vendor_id <= 0) {
            return new WP_Error('vms_dt_vio_vendor_missing', __('Invalid vendor ID.', 'vms-data-tools'));
        }

        $vendor = get_post($vendor_id);
        if (!$vendor instanceof WP_Post || $vendor->post_type !== 'vms_vendor') {
            return new WP_Error('vms_dt_vio_vendor_invalid', __('Vendor record not found.', 'vms-data-tools'));
        }

        $settings = vms_dt_vio_get_settings();
        $token_days = absint($args['token_expiration_days'] ?? $settings['token_expiration_days']);
        if ($token_days <= 0) {
            $token_days = 14;
        }

        $created_at = vms_dt_vio_now_gmt_mysql();
        $expires_at = gmdate('Y-m-d H:i:s', time() + ($token_days * DAY_IN_SECONDS));
        $raw_token = vms_dt_vio_generate_raw_token();
        $token_hash = vms_dt_vio_hash_token($raw_token);

        vms_dt_vio_invalidate_open_tokens_for_vendor($vendor_id);

        $inserted = $wpdb->insert(
            vms_dt_vio_claim_tokens_table(),
            array(
                'vendor_id' => $vendor_id,
                'token_hash' => $token_hash,
                'created_at' => $created_at,
                'expires_at' => $expires_at,
                'used_at' => null,
                'used_ip' => null,
                'used_user_agent' => null,
                'created_by_user_id' => absint($args['created_by_user_id'] ?? get_current_user_id()),
                'invite_log_id' => absint($args['invite_log_id'] ?? 0) ?: null,
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d')
        );

        if (!$inserted) {
            return new WP_Error('vms_dt_vio_token_insert_failed', __('Failed to create claim token.', 'vms-data-tools'));
        }

        $token_id = (int) $wpdb->insert_id;
        $claim_url = vms_dt_vio_claim_url($raw_token);

        return array(
            'token_id' => $token_id,
            'token' => $raw_token,
            'claim_url' => $claim_url,
            'claim_url_masked' => vms_dt_vio_mask_claim_url($claim_url),
            'created_at' => $created_at,
            'expires_at' => $expires_at,
            'vendor_id' => $vendor_id,
        );
    }
}

if (!function_exists('vms_dt_vio_get_token_record_by_hash')) {
    /**
     * @return array<string,mixed>|null
     */
    function vms_dt_vio_get_token_record_by_hash(string $token_hash): ?array
    {
        global $wpdb;

        $token_hash = trim($token_hash);
        if ($token_hash === '') {
            return null;
        }

        $table = vms_dt_vio_claim_tokens_table();
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE token_hash = %s ORDER BY id DESC LIMIT 1",
                $token_hash
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }
}

if (!function_exists('vms_dt_vio_validate_claim_token')) {
    /**
     * @return array<string,mixed>|WP_Error
     */
    function vms_dt_vio_validate_claim_token(string $raw_token)
    {
        $raw_token = trim($raw_token);
        if ($raw_token === '') {
            return new WP_Error('vms_dt_vio_token_missing', __('Claim token is required.', 'vms-data-tools'));
        }

        $row = vms_dt_vio_get_token_record_by_hash(vms_dt_vio_hash_token($raw_token));
        if (!is_array($row)) {
            return new WP_Error('vms_dt_vio_token_invalid', __('Claim token is invalid.', 'vms-data-tools'));
        }

        $vendor_id = absint($row['vendor_id'] ?? 0);
        if ($vendor_id <= 0 || get_post_type($vendor_id) !== 'vms_vendor') {
            return new WP_Error('vms_dt_vio_token_vendor_missing', __('Vendor could not be resolved for this token.', 'vms-data-tools'));
        }

        $used_at = trim((string) ($row['used_at'] ?? ''));
        if ($used_at !== '' && $used_at !== '0000-00-00 00:00:00') {
            return new WP_Error('vms_dt_vio_token_used', __('This claim link has already been used.', 'vms-data-tools'));
        }

        $expires_at = trim((string) ($row['expires_at'] ?? ''));
        if ($expires_at === '' || strtotime($expires_at . ' UTC') === false) {
            return new WP_Error('vms_dt_vio_token_invalid_expiry', __('Token expiry is invalid.', 'vms-data-tools'));
        }

        if (strtotime($expires_at . ' UTC') < time()) {
            return new WP_Error('vms_dt_vio_token_expired', __('This claim link has expired.', 'vms-data-tools'));
        }

        $email = vms_dt_vio_get_vendor_email($vendor_id);

        return array(
            'token_row' => $row,
            'vendor_id' => $vendor_id,
            'vendor_name' => (string) get_the_title($vendor_id),
            'vendor_email' => $email,
            'expires_at' => $expires_at,
            'invite_log_id' => absint($row['invite_log_id'] ?? 0),
        );
    }
}

if (!function_exists('vms_dt_vio_mark_token_used')) {
    function vms_dt_vio_mark_token_used(int $token_id): bool
    {
        global $wpdb;

        $token_id = absint($token_id);
        if ($token_id <= 0) {
            return false;
        }

        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field((string) wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field((string) wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';

        $updated = $wpdb->update(
            vms_dt_vio_claim_tokens_table(),
            array(
                'used_at' => vms_dt_vio_now_gmt_mysql(),
                'used_ip' => $ip,
                'used_user_agent' => $ua,
            ),
            array('id' => $token_id),
            array('%s', '%s', '%s'),
            array('%d')
        );

        return ($updated !== false);
    }
}

if (!function_exists('vms_dt_vio_attach_token_to_log')) {
    function vms_dt_vio_attach_token_to_log(int $token_id, int $invite_log_id): void
    {
        global $wpdb;

        $token_id = absint($token_id);
        $invite_log_id = absint($invite_log_id);
        if ($token_id <= 0 || $invite_log_id <= 0) {
            return;
        }

        $wpdb->update(
            vms_dt_vio_claim_tokens_table(),
            array('invite_log_id' => $invite_log_id),
            array('id' => $token_id),
            array('%d'),
            array('%d')
        );
    }
}

if (!function_exists('vms_dt_vio_get_latest_active_token_row_for_vendor')) {
    /**
     * @return array<string,mixed>|null
     */
    function vms_dt_vio_get_latest_active_token_row_for_vendor(int $vendor_id): ?array
    {
        global $wpdb;

        $vendor_id = absint($vendor_id);
        if ($vendor_id <= 0) {
            return null;
        }

        $table = vms_dt_vio_claim_tokens_table();
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
                 FROM {$table}
                 WHERE vendor_id = %d
                   AND used_at IS NULL
                   AND expires_at >= %s
                 ORDER BY id DESC
                 LIMIT 1",
                $vendor_id,
                vms_dt_vio_now_gmt_mysql()
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }
}
