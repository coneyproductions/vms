<?php
defined('ABSPATH') || exit;

if (!defined('VMS_DT_VIO_SETTINGS_OPTION')) {
    define('VMS_DT_VIO_SETTINGS_OPTION', 'vms_dt_vio_settings_v1');
}

if (!defined('VMS_DT_VIO_DB_VERSION_OPTION')) {
    define('VMS_DT_VIO_DB_VERSION_OPTION', 'vms_dt_vio_db_version');
}

if (!defined('VMS_DT_VIO_PREVIEW_TTL')) {
    define('VMS_DT_VIO_PREVIEW_TTL', 30 * MINUTE_IN_SECONDS);
}

if (!defined('VMS_DT_VIO_PREVIEW_KEY_PREFIX')) {
    define('VMS_DT_VIO_PREVIEW_KEY_PREFIX', 'vms_dt_vio_preview_');
}

if (!defined('VMS_DT_VIO_RETENTION_PREVIEW_KEY_PREFIX')) {
    define('VMS_DT_VIO_RETENTION_PREVIEW_KEY_PREFIX', 'vms_dt_vio_retention_preview_');
}

if (!function_exists('vms_dt_vio_help_tour_id')) {
    function vms_dt_vio_help_tour_id(): string
    {
        return 'vms_dt_vendor_invites_overview';
    }
}

if (!function_exists('vms_dt_vio_required_cap')) {
    function vms_dt_vio_required_cap(): string
    {
        if (function_exists('vms_dt_manage_capability')) {
            return vms_dt_manage_capability();
        }

        $core_capability = vms_dt_core_constant('VMS_CAP_MANAGE_DATA_TOOLS', '');
        if (is_string($core_capability) && $core_capability !== '') {
            return $core_capability;
        }

        if (defined('VMS_DT_CAP_IMPORT_VENDORS') && is_string(VMS_DT_CAP_IMPORT_VENDORS) && VMS_DT_CAP_IMPORT_VENDORS !== '') {
            return (string) VMS_DT_CAP_IMPORT_VENDORS;
        }

        return 'manage_options';
    }
}

if (!function_exists('vms_dt_vio_current_user_can_manage')) {
    function vms_dt_vio_current_user_can_manage(): bool
    {
        return current_user_can(vms_dt_vio_required_cap());
    }
}

if (!function_exists('vms_dt_vio_supported_languages')) {
    /**
     * @return array<string,string> code => label
     */
    function vms_dt_vio_supported_languages(): array
    {
        $langs = array(
            'en' => __('English', 'vms-data-tools'),
            'es' => __('Spanish', 'vms-data-tools'),
        );

        $filtered = apply_filters('vms_dt_vio_supported_languages', $langs);
        if (!is_array($filtered)) {
            return $langs;
        }

        $out = array();
        foreach ($filtered as $code => $label) {
            $lang = vms_dt_vio_sanitize_lang((string) $code);
            if ($lang === '') {
                continue;
            }
            $out[$lang] = trim((string) $label) !== '' ? (string) $label : strtoupper($lang);
        }

        return !empty($out) ? $out : $langs;
    }
}

if (!function_exists('vms_dt_vio_lang_locale_map')) {
    /**
     * @return array<string,string>
     */
    function vms_dt_vio_lang_locale_map(): array
    {
        $map = array(
            'en' => 'en_US',
            'es' => 'es_ES',
        );

        $filtered = apply_filters('vms_dt_vio_lang_locale_map', $map);
        return is_array($filtered) ? $filtered : $map;
    }
}

if (!function_exists('vms_dt_vio_sanitize_lang')) {
    function vms_dt_vio_sanitize_lang(string $raw): string
    {
        $raw = trim(strtolower($raw));
        if ($raw === '') {
            return '';
        }

        // Accept locale forms (en_US, es-MX), collapse to base language code.
        $raw = str_replace('_', '-', $raw);
        $parts = explode('-', $raw);
        $base = isset($parts[0]) ? sanitize_key((string) $parts[0]) : '';
        if ($base === '' || !preg_match('/^[a-z]{2,5}$/', $base)) {
            return '';
        }

        return $base;
    }
}

if (!function_exists('vms_dt_vio_site_default_lang')) {
    function vms_dt_vio_site_default_lang(): string
    {
        $locale = (string) get_locale();
        $lang = vms_dt_vio_sanitize_lang($locale);
        return $lang !== '' ? $lang : 'en';
    }
}

if (!function_exists('vms_dt_vio_with_language_locale')) {
    /**
     * @template T
     * @param callable():T $callback
     * @return T
     */
    function vms_dt_vio_with_language_locale(string $lang, callable $callback)
    {
        $lang = vms_dt_vio_sanitize_lang($lang);
        $map = vms_dt_vio_lang_locale_map();
        $locale = isset($map[$lang]) ? (string) $map[$lang] : '';

        $switched = false;
        if ($locale !== '' && function_exists('switch_to_locale')) {
            $switched = (bool) switch_to_locale($locale);
        }

        try {
            return $callback();
        } finally {
            if ($switched && function_exists('restore_previous_locale')) {
                restore_previous_locale();
            }
        }
    }
}

if (!function_exists('vms_dt_vio_vendor_meta_keys')) {
    /**
     * @return array<string,string>
     */
    function vms_dt_vio_vendor_meta_keys(): array
    {
        return array(
            'portal_status'     => 'vms_vendor_portal_status',
            'preferred_lang'    => 'vms_vendor_preferred_lang',
            'claim_user_id'     => 'vms_vendor_claim_user_id',
            'last_invite_at'    => 'vms_vendor_last_invite_at',
            'last_invite_mode'  => 'vms_vendor_last_invite_mode',
            'invite_count'      => 'vms_vendor_invite_count',
        );
    }
}

if (!function_exists('vms_dt_vio_log_event_type_labels')) {
    /**
     * @return array<string,string>
     */
    function vms_dt_vio_log_event_type_labels(): array
    {
        return array(
            'invite_send' => __('Invite Send', 'vms-data-tools'),
            'claim_attempt' => __('Claim Attempt', 'vms-data-tools'),
            'claim_link_generate' => __('Claim Link Generate', 'vms-data-tools'),
        );
    }
}

if (!function_exists('vms_dt_vio_normalize_log_event_type')) {
    function vms_dt_vio_normalize_log_event_type(string $event_type): string
    {
        $event_type = sanitize_key($event_type);
        $labels = vms_dt_vio_log_event_type_labels();
        return isset($labels[$event_type]) ? $event_type : 'invite_send';
    }
}

if (!function_exists('vms_dt_vio_normalize_portal_status')) {
    function vms_dt_vio_normalize_portal_status(string $status): string
    {
        $status = sanitize_key($status);
        $allowed = array('unclaimed', 'invited', 'claimed', 'disabled');
        return in_array($status, $allowed, true) ? $status : 'unclaimed';
    }
}

if (!function_exists('vms_dt_vio_get_vendor_portal_status')) {
    function vms_dt_vio_get_vendor_portal_status(int $vendor_id): string
    {
        $vendor_id = absint($vendor_id);
        if ($vendor_id <= 0) {
            return 'unclaimed';
        }

        $keys = vms_dt_vio_vendor_meta_keys();
        $status = (string) get_post_meta($vendor_id, $keys['portal_status'], true);
        if ($status === '') {
            $legacy_user_link_key = (string) vms_dt_core_constant('VMS_VENDOR_PRIMARY_USER_META_KEY', '_vms_vendor_user_id');
            $linked = (int) get_post_meta($vendor_id, $legacy_user_link_key, true);
            if ($linked > 0) {
                return 'claimed';
            }
            return 'unclaimed';
        }

        return vms_dt_vio_normalize_portal_status($status);
    }
}

if (!function_exists('vms_dt_vio_set_vendor_portal_status')) {
    function vms_dt_vio_set_vendor_portal_status(int $vendor_id, string $status): void
    {
        $vendor_id = absint($vendor_id);
        if ($vendor_id <= 0) {
            return;
        }

        $keys = vms_dt_vio_vendor_meta_keys();
        update_post_meta($vendor_id, $keys['portal_status'], vms_dt_vio_normalize_portal_status($status));
    }
}

if (!function_exists('vms_dt_vio_get_vendor_preferred_lang')) {
    function vms_dt_vio_get_vendor_preferred_lang(int $vendor_id): string
    {
        $vendor_id = absint($vendor_id);
        if ($vendor_id <= 0) {
            return '';
        }

        $keys = vms_dt_vio_vendor_meta_keys();
        $lang = (string) get_post_meta($vendor_id, $keys['preferred_lang'], true);
        return vms_dt_vio_sanitize_lang($lang);
    }
}

if (!function_exists('vms_dt_vio_set_vendor_preferred_lang')) {
    function vms_dt_vio_set_vendor_preferred_lang(int $vendor_id, string $lang): void
    {
        $vendor_id = absint($vendor_id);
        if ($vendor_id <= 0) {
            return;
        }

        $lang = vms_dt_vio_sanitize_lang($lang);
        $keys = vms_dt_vio_vendor_meta_keys();

        if ($lang === '') {
            delete_post_meta($vendor_id, $keys['preferred_lang']);
            return;
        }

        update_post_meta($vendor_id, $keys['preferred_lang'], $lang);
    }
}

if (!function_exists('vms_dt_vio_resolve_vendor_language')) {
    function vms_dt_vio_resolve_vendor_language(int $vendor_id, string $forced_lang = ''): string
    {
        $forced = vms_dt_vio_sanitize_lang($forced_lang);
        if ($forced !== '') {
            return $forced;
        }

        $preferred = vms_dt_vio_get_vendor_preferred_lang($vendor_id);
        if ($preferred !== '') {
            return $preferred;
        }

        return vms_dt_vio_site_default_lang();
    }
}

if (!function_exists('vms_dt_vio_get_vendor_email')) {
    function vms_dt_vio_get_vendor_email(int $vendor_id): string
    {
        $vendor_id = absint($vendor_id);
        if ($vendor_id <= 0) {
            return '';
        }

        $k_primary = vms_dt_has_core_function('vms_meta_key') ? (string) vms_dt_call_core_function('vms_meta_key', 'vendor', 'primary_email') : '_vms_vendor_primary_email';
        $k_legacy = vms_dt_has_core_function('vms_meta_key') ? (string) vms_dt_call_core_function('vms_meta_key', 'vendor', 'email') : '_vms_vendor_email';

        $email = trim((string) get_post_meta($vendor_id, $k_primary, true));
        if ($email === '') {
            $email = trim((string) get_post_meta($vendor_id, $k_legacy, true));
        }

        return sanitize_email($email);
    }
}

if (!function_exists('vms_dt_vio_get_vendor_type_slug')) {
    function vms_dt_vio_get_vendor_type_slug(int $vendor_id): string
    {
        $vendor_id = absint($vendor_id);
        if ($vendor_id <= 0) {
            return '';
        }

        $terms = wp_get_post_terms($vendor_id, 'vms_vendor_type');
        if (is_wp_error($terms) || empty($terms)) {
            return '';
        }

        $slug = sanitize_key((string) ($terms[0]->slug ?? ''));
        return $slug;
    }
}

if (!function_exists('vms_dt_vio_now_gmt_mysql')) {
    function vms_dt_vio_now_gmt_mysql(): string
    {
        return (string) current_time('mysql', true);
    }
}

if (!function_exists('vms_dt_vio_claim_base_path')) {
    function vms_dt_vio_claim_base_path(): string
    {
        $base = (string) apply_filters('vms_dt_vio_claim_base_path', 'vendor-claim');
        $base = trim($base, " \t\n\r\0\x0B/");
        return $base !== '' ? $base : 'vendor-claim';
    }
}

if (!function_exists('vms_dt_vio_claim_url')) {
    function vms_dt_vio_claim_url(string $token = ''): string
    {
        $base = vms_dt_vio_claim_base_path();
        $url = home_url('/' . $base . '/');
        if ($token !== '') {
            $url = add_query_arg('token', rawurlencode($token), $url);
        }

        $url = (string) apply_filters('vms_dt_vio_claim_url', $url, $token);
        return $url;
    }
}

if (!function_exists('vms_dt_vio_mask_claim_url')) {
    function vms_dt_vio_mask_claim_url(string $url): string
    {
        $parts = wp_parse_url($url);
        if (!is_array($parts) || empty($parts['query'])) {
            return $url;
        }

        parse_str((string) $parts['query'], $q);
        if (!isset($q['token'])) {
            return $url;
        }

        $token = (string) $q['token'];
        $len = strlen($token);
        $mask = str_repeat('*', max(8, $len));
        if ($len > 8) {
            $mask = substr($token, 0, 4) . str_repeat('*', max(4, $len - 8)) . substr($token, -4);
        }
        $q['token'] = $mask;

        $rebuilt = add_query_arg($q, home_url('/' . trim((string) ($parts['path'] ?? ''), '/') . '/'));
        return (string) $rebuilt;
    }
}

if (!function_exists('vms_dt_vio_default_settings')) {
    /**
     * @return array<string,mixed>
     */
    function vms_dt_vio_default_settings(): array
    {
        return array(
            'mailpoet_enabled' => 1,
            'create_subscribers_on_invite' => 1,
            'tag_base_general' => 'vms_invite_general',
            'tag_base_intent' => 'vms_invite_intent',
            'tag_claimed' => 'vms_vendor_claimed',
            'claim_field_key' => 'vms_claim_link',
            'open_dates_field_key' => 'vms_open_dates_snippet',
            'invites_per_minute' => 20,
            'token_expiration_days' => 14,
            'resend_cooldown_days' => 3,
            'default_next_n' => 8,
            'default_lookahead_days' => 90,
        );
    }
}

if (!function_exists('vms_dt_vio_sanitize_settings')) {
    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    function vms_dt_vio_sanitize_settings(array $input): array
    {
        $defaults = vms_dt_vio_default_settings();

        $settings = $defaults;
        $settings['mailpoet_enabled'] = !empty($input['mailpoet_enabled']) ? 1 : 0;
        $settings['create_subscribers_on_invite'] = !empty($input['create_subscribers_on_invite']) ? 1 : 0;

        $settings['tag_base_general'] = sanitize_key((string) ($input['tag_base_general'] ?? $defaults['tag_base_general']));
        if ($settings['tag_base_general'] === '') {
            $settings['tag_base_general'] = (string) $defaults['tag_base_general'];
        }

        $settings['tag_base_intent'] = sanitize_key((string) ($input['tag_base_intent'] ?? $defaults['tag_base_intent']));
        if ($settings['tag_base_intent'] === '') {
            $settings['tag_base_intent'] = (string) $defaults['tag_base_intent'];
        }

        $settings['tag_claimed'] = sanitize_key((string) ($input['tag_claimed'] ?? $defaults['tag_claimed']));
        if ($settings['tag_claimed'] === '') {
            $settings['tag_claimed'] = (string) $defaults['tag_claimed'];
        }

        $settings['claim_field_key'] = sanitize_key((string) ($input['claim_field_key'] ?? $defaults['claim_field_key']));
        if ($settings['claim_field_key'] === '') {
            $settings['claim_field_key'] = (string) $defaults['claim_field_key'];
        }

        $settings['open_dates_field_key'] = sanitize_key((string) ($input['open_dates_field_key'] ?? $defaults['open_dates_field_key']));
        if ($settings['open_dates_field_key'] === '') {
            $settings['open_dates_field_key'] = (string) $defaults['open_dates_field_key'];
        }

        $settings['invites_per_minute'] = max(1, min(240, absint($input['invites_per_minute'] ?? $defaults['invites_per_minute'])));
        $settings['token_expiration_days'] = max(1, min(90, absint($input['token_expiration_days'] ?? $defaults['token_expiration_days'])));
        $settings['resend_cooldown_days'] = max(0, min(30, absint($input['resend_cooldown_days'] ?? $defaults['resend_cooldown_days'])));
        $settings['default_next_n'] = max(1, min(30, absint($input['default_next_n'] ?? $defaults['default_next_n'])));
        $settings['default_lookahead_days'] = max(1, min(365, absint($input['default_lookahead_days'] ?? $defaults['default_lookahead_days'])));

        return (array) apply_filters('vms_dt_vio_sanitized_settings', $settings, $input);
    }
}

if (!function_exists('vms_dt_vio_get_settings')) {
    /**
     * @return array<string,mixed>
     */
    function vms_dt_vio_get_settings(): array
    {
        $raw = get_option(VMS_DT_VIO_SETTINGS_OPTION, array());
        if (!is_array($raw)) {
            $raw = array();
        }

        return vms_dt_vio_sanitize_settings($raw);
    }
}

if (!function_exists('vms_dt_vio_update_settings')) {
    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    function vms_dt_vio_update_settings(array $input): array
    {
        $settings = vms_dt_vio_sanitize_settings($input);
        update_option(VMS_DT_VIO_SETTINGS_OPTION, $settings, false);
        return $settings;
    }
}

if (!function_exists('vms_dt_vio_parse_id_list')) {
    /**
     * @return int[]
     */
    function vms_dt_vio_parse_id_list($value): array
    {
        if (is_array($value)) {
            $parts = $value;
        } else {
            $parts = preg_split('/[^0-9]+/', (string) $value);
        }

        $ids = array();
        foreach ((array) $parts as $part) {
            $id = absint($part);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }
}
