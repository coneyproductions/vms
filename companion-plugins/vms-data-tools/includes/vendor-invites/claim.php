<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_dt_vio_register_claim_endpoint')) {
    function vms_dt_vio_register_claim_endpoint(): void
    {
        add_rewrite_tag('%vms_vendor_claim%', '1');

        $base = vms_dt_vio_claim_base_path();
        add_rewrite_rule('^' . preg_quote($base, '/') . '/?$', 'index.php?vms_vendor_claim=1', 'top');
    }
}
add_action('init', 'vms_dt_vio_register_claim_endpoint');

if (!function_exists('vms_dt_vio_maybe_flush_claim_rewrite')) {
    function vms_dt_vio_maybe_flush_claim_rewrite(): void
    {
        $marker_key = 'vms_dt_vio_claim_rewrite_flushed';
        $target = vms_dt_vio_db_version();
        $current = (string) get_option($marker_key, '');
        if ($current === $target) {
            return;
        }

        flush_rewrite_rules(false);
        update_option($marker_key, $target, false);
    }
}
add_action('init', 'vms_dt_vio_maybe_flush_claim_rewrite', 20);

if (!function_exists('vms_dt_vio_add_claim_query_var')) {
    /**
     * @param string[] $vars
     * @return string[]
     */
    function vms_dt_vio_add_claim_query_var(array $vars): array
    {
        $vars[] = 'vms_vendor_claim';
        return $vars;
    }
}
add_filter('query_vars', 'vms_dt_vio_add_claim_query_var');

if (!function_exists('vms_dt_vio_is_claim_request')) {
    function vms_dt_vio_is_claim_request(): bool
    {
        $qv = get_query_var('vms_vendor_claim');
        if ((string) $qv === '1') {
            return true;
        }

        return isset($_GET['vms_vendor_claim']) && (string) wp_unslash($_GET['vms_vendor_claim']) === '1';
    }
}

if (!function_exists('vms_dt_vio_claim_attempt_bucket_key')) {
    function vms_dt_vio_claim_attempt_bucket_key(string $token): string
    {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) wp_unslash($_SERVER['REMOTE_ADDR']) : 'unknown';
        return 'vms_dt_vio_claim_rate_' . md5($ip . '|' . vms_dt_vio_hash_token($token));
    }
}

if (!function_exists('vms_dt_vio_claim_rate_limited')) {
    function vms_dt_vio_claim_rate_limited(string $token): bool
    {
        $token = trim($token);
        if ($token === '') {
            return false;
        }

        $key = vms_dt_vio_claim_attempt_bucket_key($token);
        $bucket = get_transient($key);
        if (!is_array($bucket)) {
            return false;
        }

        $count = absint($bucket['count'] ?? 0);
        return ($count >= 20);
    }
}

if (!function_exists('vms_dt_vio_claim_rate_note_failure')) {
    function vms_dt_vio_claim_rate_note_failure(string $token): void
    {
        $token = trim($token);
        if ($token === '') {
            return;
        }

        $key = vms_dt_vio_claim_attempt_bucket_key($token);
        $bucket = get_transient($key);
        if (!is_array($bucket)) {
            $bucket = array('count' => 0);
        }

        $bucket['count'] = absint($bucket['count'] ?? 0) + 1;
        set_transient($key, $bucket, 10 * MINUTE_IN_SECONDS);
    }
}

if (!function_exists('vms_dt_vio_claim_log_error_by_token')) {
    function vms_dt_vio_claim_log_error_by_token(string $token, WP_Error $error): void
    {
        $row = vms_dt_vio_get_token_record_by_hash(vms_dt_vio_hash_token($token));
        if (!is_array($row)) {
            error_log('[VIO] Claim failed for unknown token: ' . $error->get_error_code() . ' - ' . $error->get_error_message());
            return;
        }

        $vendor_id = absint($row['vendor_id'] ?? 0);
        $token_id = absint($row['id'] ?? 0);
        $context = array();
        $invite_log_id = absint($row['invite_log_id'] ?? 0);
        if ($invite_log_id > 0) {
            $invite_log = vms_dt_vio_get_log_row($invite_log_id);
            if (is_array($invite_log)) {
                $context['mode'] = sanitize_key((string) ($invite_log['mode'] ?? 'general'));
                $context['lang'] = vms_dt_vio_sanitize_lang((string) ($invite_log['lang'] ?? ''));
            }
        }

        vms_dt_vio_log_claim_attempt(
            $vendor_id,
            $token_id,
            'failed',
            $error->get_error_code() . ': ' . $error->get_error_message(),
            $context
        );
    }
}

if (!function_exists('vms_dt_vio_claim_generate_username')) {
    function vms_dt_vio_claim_generate_username(string $email): string
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

if (!function_exists('vms_dt_vio_process_claim')) {
    /**
     * @return array<string,mixed>|WP_Error
     */
    function vms_dt_vio_process_claim(string $token, string $password, string $password_confirm, string $preferred_lang)
    {
        $validation = vms_dt_vio_validate_claim_token($token);
        if (is_wp_error($validation)) {
            return $validation;
        }

        $vendor_id = absint($validation['vendor_id'] ?? 0);
        $vendor_email = sanitize_email((string) ($validation['vendor_email'] ?? ''));
        if ($vendor_id <= 0 || $vendor_email === '' || !is_email($vendor_email)) {
            return new WP_Error('vms_dt_vio_claim_vendor_email_missing', __('Unable to resolve a valid vendor email for this claim token.', 'vms-data-tools'));
        }

        $existing_user = get_user_by('email', $vendor_email);
        $user_id = 0;

        if ($existing_user instanceof WP_User) {
            $user_id = (int) $existing_user->ID;

            $password = (string) $password;
            $password_confirm = (string) $password_confirm;
            if ($password !== '' || $password_confirm !== '') {
                if ($password !== $password_confirm) {
                    return new WP_Error('vms_dt_vio_claim_password_mismatch', __('Passwords do not match.', 'vms-data-tools'));
                }
                if (strlen($password) < 8) {
                    return new WP_Error('vms_dt_vio_claim_password_short', __('Password must be at least 8 characters.', 'vms-data-tools'));
                }

                wp_set_password($password, $user_id);
            }
        } else {
            if ($password !== $password_confirm) {
                return new WP_Error('vms_dt_vio_claim_password_mismatch', __('Passwords do not match.', 'vms-data-tools'));
            }
            if (strlen($password) < 8) {
                return new WP_Error('vms_dt_vio_claim_password_short', __('Password must be at least 8 characters.', 'vms-data-tools'));
            }

            $username = vms_dt_vio_claim_generate_username($vendor_email);
            $created = wp_create_user($username, $password, $vendor_email);
            if (is_wp_error($created)) {
                return new WP_Error('vms_dt_vio_claim_user_create_failed', $created->get_error_message());
            }

            $user_id = absint($created);
        }

        if ($user_id <= 0) {
            return new WP_Error('vms_dt_vio_claim_user_invalid', __('Unable to create or resolve the account for this claim.', 'vms-data-tools'));
        }

        vms_dt_vio_mark_vendor_claimed($vendor_id, $user_id);

        $lang = vms_dt_vio_sanitize_lang($preferred_lang);
        if ($lang !== '') {
            vms_dt_vio_set_vendor_preferred_lang($vendor_id, $lang);
        }

        $token_row = (array) ($validation['token_row'] ?? array());
        $token_id = absint($token_row['id'] ?? 0);
        if ($token_id > 0) {
            vms_dt_vio_mark_token_used($token_id);

            $context = array();
            $invite_log_id = absint($token_row['invite_log_id'] ?? 0);
            if ($invite_log_id > 0) {
                $invite_log = vms_dt_vio_get_log_row($invite_log_id);
                if (is_array($invite_log)) {
                    $context['mode'] = sanitize_key((string) ($invite_log['mode'] ?? 'general'));
                    $context['lang'] = vms_dt_vio_sanitize_lang((string) ($invite_log['lang'] ?? ''));
                }
            }

            vms_dt_vio_log_claim_attempt($vendor_id, $token_id, 'sent', __('Vendor claim completed.', 'vms-data-tools'), $context);
        }

        $settings = vms_dt_vio_get_settings();
        $claimed_tag = sanitize_key((string) ($settings['tag_claimed'] ?? ''));
        if ($claimed_tag !== '' && !empty($settings['mailpoet_enabled']) && vms_dt_vio_mailpoet_is_active()) {
            $sync = vms_dt_vio_mailpoet_sync_invite(
                $vendor_email,
                (string) get_the_title($vendor_id),
                $claimed_tag,
                '',
                '',
                $settings
            );
            if (is_wp_error($sync)) {
                error_log('[VIO] Claim tag sync failed: ' . $sync->get_error_message());
            }
        }

        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);

        $portal_url = '';
        $portal_page_id = absint(get_option('vms_page_vendor_portal', 0));
        if ($portal_page_id > 0) {
            $portal_url = (string) get_permalink($portal_page_id);
        }
        if ($portal_url === '') {
            $portal_url = admin_url();
        }

        return array(
            'ok' => true,
            'vendor_id' => $vendor_id,
            'user_id' => $user_id,
            'portal_url' => $portal_url,
        );
    }
}

if (!function_exists('vms_dt_vio_render_claim_page')) {
    function vms_dt_vio_render_claim_page(): void
    {
        $token = '';
        if (isset($_REQUEST['token'])) {
            $token = sanitize_text_field((string) wp_unslash($_REQUEST['token']));
        }

        $notice = '';
        $notice_class = 'notice-info';
        $claim_data = null;
        $claim_success = null;

        if ($token !== '') {
            $claim_data = vms_dt_vio_validate_claim_token($token);
            if (is_wp_error($claim_data)) {
                $notice = $claim_data->get_error_message();
                $notice_class = 'notice-error';
                vms_dt_vio_claim_log_error_by_token($token, $claim_data);
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vms_dt_vio_claim_action'])) {
            check_admin_referer('vms_dt_vio_claim_submit');

            if ($token === '') {
                $notice = __('Claim token is missing.', 'vms-data-tools');
                $notice_class = 'notice-error';
            } elseif (vms_dt_vio_claim_rate_limited($token)) {
                $notice = __('Too many failed attempts. Please wait a few minutes and try again.', 'vms-data-tools');
                $notice_class = 'notice-error';
            } else {
                $password = isset($_POST['claim_password']) ? (string) wp_unslash($_POST['claim_password']) : '';
                $password_confirm = isset($_POST['claim_password_confirm']) ? (string) wp_unslash($_POST['claim_password_confirm']) : '';
                $preferred_lang = isset($_POST['claim_preferred_lang']) ? (string) wp_unslash($_POST['claim_preferred_lang']) : '';

                $claim_success = vms_dt_vio_process_claim($token, $password, $password_confirm, $preferred_lang);
                if (is_wp_error($claim_success)) {
                    $notice = $claim_success->get_error_message();
                    $notice_class = 'notice-error';
                    vms_dt_vio_claim_rate_note_failure($token);
                    vms_dt_vio_claim_log_error_by_token($token, $claim_success);
                } else {
                    $notice = __('Your vendor portal access has been claimed successfully.', 'vms-data-tools');
                    $notice_class = 'notice-success';
                    $claim_data = null;
                }
            }
        }

        status_header(200);
        nocache_headers();

        echo '<!doctype html>';
        echo '<html ' . get_language_attributes() . '>';
        echo '<head>';
        echo '<meta charset="' . esc_attr(get_bloginfo('charset')) . '">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>' . esc_html__('Vendor Claim', 'vms-data-tools') . '</title>';
        wp_head();
        echo '</head>';
        echo '<body class="vms-dt-vendor-claim">';
        echo '<main id="vms-dt-vendor-claim-main">';
        echo '<h1>' . esc_html__('Vendor Portal Claim', 'vms-data-tools') . '</h1>';

        if ($notice !== '') {
            echo '<div class="notice ' . esc_attr($notice_class) . '"><p>' . esc_html($notice) . '</p></div>';
        }

        if (is_array($claim_success)) {
            $portal_url = (string) ($claim_success['portal_url'] ?? '');
            echo '<p>' . esc_html__('You can now continue to your vendor portal.', 'vms-data-tools') . '</p>';
            if ($portal_url !== '') {
                echo '<p><a class="button button-primary" href="' . esc_url($portal_url) . '">' . esc_html__('Open Vendor Portal', 'vms-data-tools') . '</a></p>';
            }
        } elseif (is_array($claim_data)) {
            $vendor_email = (string) ($claim_data['vendor_email'] ?? '');
            $vendor_id = absint($claim_data['vendor_id'] ?? 0);
            $preferred_lang = vms_dt_vio_get_vendor_preferred_lang($vendor_id);
            $langs = vms_dt_vio_supported_languages();

            echo '<p>' . esc_html__('Set a password to claim portal access for your vendor account.', 'vms-data-tools') . '</p>';

            echo '<form method="post">';
            wp_nonce_field('vms_dt_vio_claim_submit');
            echo '<input type="hidden" name="vms_dt_vio_claim_action" value="submit">';
            echo '<input type="hidden" name="token" value="' . esc_attr($token) . '">';

            echo '<p>';
            echo '<label for="claim_email"><strong>' . esc_html__('Email', 'vms-data-tools') . '</strong></label><br>';
            echo '<input id="claim_email" type="email" value="' . esc_attr($vendor_email) . '" readonly>'; 
            echo '</p>';

            echo '<p>';
            echo '<label for="claim_password"><strong>' . esc_html__('Password', 'vms-data-tools') . '</strong></label><br>';
            echo '<input id="claim_password" name="claim_password" type="password" autocomplete="new-password">';
            echo '</p>';

            echo '<p>';
            echo '<label for="claim_password_confirm"><strong>' . esc_html__('Confirm Password', 'vms-data-tools') . '</strong></label><br>';
            echo '<input id="claim_password_confirm" name="claim_password_confirm" type="password" autocomplete="new-password">';
            echo '</p>';

            echo '<p>';
            echo '<label for="claim_preferred_lang"><strong>' . esc_html__('Preferred Language', 'vms-data-tools') . '</strong></label><br>';
            echo '<select id="claim_preferred_lang" name="claim_preferred_lang">';
            echo '<option value="">' . esc_html__('Use site default', 'vms-data-tools') . '</option>';
            foreach ($langs as $code => $label) {
                echo '<option value="' . esc_attr($code) . '" ' . selected($preferred_lang, $code, false) . '>' . esc_html($label) . '</option>';
            }
            echo '</select>';
            echo '</p>';

            echo '<p><button type="submit" class="button button-primary">' . esc_html__('Claim Access', 'vms-data-tools') . '</button></p>';
            echo '</form>';
        } else {
            echo '<p>' . esc_html__('This claim link is invalid or expired. Contact the venue admin to request a new invite.', 'vms-data-tools') . '</p>';
        }

        echo '</main>';
        wp_footer();
        echo '</body>';
        echo '</html>';
        exit;
    }
}

if (!function_exists('vms_dt_vio_maybe_render_claim_page')) {
    function vms_dt_vio_maybe_render_claim_page(): void
    {
        if (!vms_dt_vio_is_claim_request()) {
            return;
        }

        vms_dt_vio_render_claim_page();
    }
}
add_action('template_redirect', 'vms_dt_vio_maybe_render_claim_page', 1);
