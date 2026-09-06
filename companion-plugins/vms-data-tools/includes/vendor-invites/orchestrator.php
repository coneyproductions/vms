<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_dt_vio_sanitize_send_args')) {
    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    function vms_dt_vio_sanitize_send_args(array $input): array
    {
        $settings = vms_dt_vio_get_settings();

        $mode = sanitize_key((string) ($input['invite_mode'] ?? 'general'));
        if (!in_array($mode, array('general', 'intent'), true)) {
            $mode = 'general';
        }

        $vendor_status = sanitize_key((string) ($input['vendor_status'] ?? 'unclaimed'));
        if (!in_array($vendor_status, array('unclaimed', 'invited', 'claimed', 'disabled', 'all'), true)) {
            $vendor_status = 'unclaimed';
        }

        $language_mode = sanitize_key((string) ($input['language_mode'] ?? 'auto'));
        if (!in_array($language_mode, array('auto', 'force'), true)) {
            $language_mode = 'auto';
        }

        $force_lang = vms_dt_vio_sanitize_lang((string) ($input['force_lang'] ?? ''));
        if ($language_mode !== 'force') {
            $force_lang = '';
        }

        $args = array(
            'vendor_type' => sanitize_key((string) ($input['vendor_type'] ?? '')),
            'vendor_status' => $vendor_status,
            'invite_mode' => $mode,
            'language_mode' => $language_mode,
            'force_lang' => $force_lang,
            'next_n' => max(1, min(30, absint($input['next_n'] ?? $settings['default_next_n']))),
            'lookahead_days' => max(1, min(365, absint($input['lookahead_days'] ?? $settings['default_lookahead_days']))),
            'venue_ids' => vms_dt_vio_parse_id_list($input['venue_ids'] ?? array()),
            'include_primary_vendor' => !empty($input['include_primary_vendor']),
            'include_tentative' => !empty($input['include_tentative']),
        );

        return (array) apply_filters('vms_dt_vio_sanitized_send_args', $args, $input);
    }
}

if (!function_exists('vms_dt_vio_query_vendor_ids')) {
    /**
     * @param array<string,mixed> $args
     * @return int[]
     */
    function vms_dt_vio_query_vendor_ids(array $args): array
    {
        $vendor_type = sanitize_key((string) ($args['vendor_type'] ?? ''));
        $vendor_status = sanitize_key((string) ($args['vendor_status'] ?? 'unclaimed'));

        $query = array(
            'post_type' => 'vms_vendor',
            'post_status' => array('publish', 'private', 'draft', 'pending', 'future'),
            'posts_per_page' => -1,
            'fields' => 'ids',
            'orderby' => 'title',
            'order' => 'ASC',
            'no_found_rows' => true,
            'update_post_meta_cache' => true,
            'update_post_term_cache' => false,
        );

        if ($vendor_type !== '') {
            $query['tax_query'] = array(
                array(
                    'taxonomy' => 'vms_vendor_type',
                    'field' => 'slug',
                    'terms' => array($vendor_type),
                ),
            );
        }

        if ($vendor_status !== '' && $vendor_status !== 'all') {
            $keys = vms_dt_vio_vendor_meta_keys();
            if ($vendor_status === 'unclaimed') {
                $query['meta_query'] = array(
                    'relation' => 'OR',
                    array(
                        'key' => $keys['portal_status'],
                        'compare' => 'NOT EXISTS',
                    ),
                    array(
                        'key' => $keys['portal_status'],
                        'value' => '',
                        'compare' => '=',
                    ),
                    array(
                        'key' => $keys['portal_status'],
                        'value' => 'unclaimed',
                        'compare' => '=',
                    ),
                );
            } else {
                $query['meta_query'] = array(
                    array(
                        'key' => $keys['portal_status'],
                        'value' => $vendor_status,
                        'compare' => '=',
                    ),
                );
            }
        }

        $ids = get_posts($query);
        if (!is_array($ids)) {
            return array();
        }

        return array_values(array_unique(array_filter(array_map('absint', $ids))));
    }
}

if (!function_exists('vms_dt_vio_build_mailpoet_tag')) {
    function vms_dt_vio_build_mailpoet_tag(string $mode, string $lang, string $vendor_type, array $settings): string
    {
        $mode = sanitize_key($mode);
        $lang = vms_dt_vio_sanitize_lang($lang);
        $vendor_type = sanitize_key($vendor_type);

        if ($lang === '') {
            $lang = vms_dt_vio_site_default_lang();
        }

        if ($mode === 'intent') {
            $base = sanitize_key((string) ($settings['tag_base_intent'] ?? 'vms_invite_intent'));
            $suffix = ($vendor_type !== '') ? '_' . $vendor_type : '';
            return sanitize_key($base . $suffix . '_' . $lang);
        }

        $base = sanitize_key((string) ($settings['tag_base_general'] ?? 'vms_invite_general'));
        return sanitize_key($base . '_' . $lang);
    }
}

if (!function_exists('vms_dt_vio_build_preview')) {
    /**
     * @param array<string,mixed> $raw_args
     * @return array<string,mixed>
     */
    function vms_dt_vio_build_preview(array $raw_args): array
    {
        $args = vms_dt_vio_sanitize_send_args($raw_args);
        $vendor_ids = vms_dt_vio_query_vendor_ids($args);

        $preview = array(
            'args' => $args,
            'created_at' => vms_dt_vio_now_gmt_mysql(),
            'summary' => array(
                'total' => 0,
                'includable' => 0,
                'missing_email' => 0,
                'invalid_email' => 0,
                'missing_vendor_type' => 0,
            ),
            'rows' => array(),
        );

        if (empty($vendor_ids)) {
            return $preview;
        }

        $open_cache = array();
        $settings = vms_dt_vio_get_settings();

        foreach ($vendor_ids as $vendor_id) {
            $preview['summary']['total']++;

            $vendor_name = (string) get_the_title($vendor_id);
            if ($vendor_name === '') {
                $vendor_name = sprintf(
                    /* translators: %d is a vendor post ID. */
                    __('Vendor #%d', 'vms-data-tools'),
                    $vendor_id
                );
            }

            $vendor_type = sanitize_key((string) ($args['vendor_type'] ?? ''));
            if ($vendor_type === '') {
                $vendor_type = vms_dt_vio_get_vendor_type_slug($vendor_id);
            }

            $email = vms_dt_vio_get_vendor_email($vendor_id);
            $email_valid = ($email !== '' && is_email($email));

            $lang = vms_dt_vio_resolve_vendor_language($vendor_id, (string) ($args['force_lang'] ?? ''));

            $open_dates = array();
            $open_dates_snippet = '';
            if (($args['invite_mode'] ?? 'general') === 'intent') {
                if ($vendor_type !== '') {
                    $cache_key = md5(wp_json_encode(array($vendor_type, $lang, $args['next_n'], $args['lookahead_days'], $args['venue_ids'], $args['include_primary_vendor'], $args['include_tentative'])));
                    if (!isset($open_cache[$cache_key])) {
                        $open_cache[$cache_key] = vms_dt_vio_find_open_dates(array(
                            'vendor_type' => $vendor_type,
                            'next_n' => $args['next_n'],
                            'lookahead_days' => $args['lookahead_days'],
                            'venue_ids' => $args['venue_ids'],
                            'include_primary_vendor' => $args['include_primary_vendor'],
                            'include_tentative' => $args['include_tentative'],
                        ));
                    }
                    $open_dates = (array) $open_cache[$cache_key];
                    $open_dates_snippet = vms_dt_vio_render_open_dates_snippet($open_dates, $lang, array(
                        'include_primary_vendor' => $args['include_primary_vendor'],
                        'include_tentative' => $args['include_tentative'],
                    ));
                } else {
                    $open_dates_snippet = '<p>' . esc_html__('Vendor type is missing. Open dates cannot be generated for intent invite mode.', 'vms-data-tools') . '</p>';
                }
            }

            $tag = vms_dt_vio_build_mailpoet_tag((string) $args['invite_mode'], $lang, $vendor_type, $settings);

            $warnings = array();
            if ($email === '') {
                $preview['summary']['missing_email']++;
                $warnings[] = __('Missing email address. Row will be excluded from commit.', 'vms-data-tools');
            } elseif (!$email_valid) {
                $preview['summary']['invalid_email']++;
                $warnings[] = __('Email address is invalid. Row will be excluded from commit.', 'vms-data-tools');
            }

            $includable = $email_valid;
            if (($args['invite_mode'] ?? 'general') === 'intent' && $vendor_type === '') {
                $preview['summary']['missing_vendor_type']++;
                $warnings[] = __('Vendor type is missing. Intent invites require a vendor type and will be excluded from commit.', 'vms-data-tools');
                $includable = false;
            }

            if ($includable) {
                $preview['summary']['includable']++;
            }

            $preview['rows'][] = array(
                'vendor_id' => $vendor_id,
                'vendor_name' => $vendor_name,
                'vendor_type' => $vendor_type,
                'portal_status' => vms_dt_vio_get_vendor_portal_status($vendor_id),
                'email' => $email,
                'email_valid' => $email_valid,
                'lang' => $lang,
                'mode' => (string) $args['invite_mode'],
                'claim_link_masked' => vms_dt_vio_mask_claim_url(vms_dt_vio_claim_url('preview-token-hidden')),
                'open_dates_count' => count($open_dates),
                'open_dates_snippet' => $open_dates_snippet,
                'mailpoet_tag' => $tag,
                'includable' => $includable,
                'warnings' => $warnings,
            );
        }

        return $preview;
    }
}

if (!function_exists('vms_dt_vio_store_preview')) {
    function vms_dt_vio_store_preview(array $preview): string
    {
        $token = wp_generate_password(24, false, false);
        set_transient(VMS_DT_VIO_PREVIEW_KEY_PREFIX . $token, $preview, VMS_DT_VIO_PREVIEW_TTL);
        return $token;
    }
}

if (!function_exists('vms_dt_vio_load_preview')) {
    /**
     * @return array<string,mixed>|WP_Error
     */
    function vms_dt_vio_load_preview(string $token)
    {
        $token = trim($token);
        if ($token === '') {
            return new WP_Error('vms_dt_vio_preview_token_missing', __('Preview token is missing.', 'vms-data-tools'));
        }

        $preview = get_transient(VMS_DT_VIO_PREVIEW_KEY_PREFIX . $token);
        if (!is_array($preview)) {
            return new WP_Error('vms_dt_vio_preview_expired', __('Preview token expired. Run preview again.', 'vms-data-tools'));
        }

        return $preview;
    }
}

if (!function_exists('vms_dt_vio_send_invite_for_row')) {
    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $args
     * @param array<string,mixed> $settings
     * @return array<string,mixed>
     */
    function vms_dt_vio_send_invite_for_row(array $row, array $args, array $settings, bool $force_resend = false): array
    {
        $vendor_id = absint($row['vendor_id'] ?? 0);
        $email = sanitize_email((string) ($row['email'] ?? ''));
        $lang = vms_dt_vio_sanitize_lang((string) ($row['lang'] ?? ''));
        $mode = sanitize_key((string) ($row['mode'] ?? ($args['invite_mode'] ?? 'general')));
        $vendor_type = sanitize_key((string) ($row['vendor_type'] ?? ''));
        $vendor_name = (string) ($row['vendor_name'] ?? get_the_title($vendor_id));

        if ($lang === '') {
            $lang = vms_dt_vio_site_default_lang();
        }
        if (!in_array($mode, array('general', 'intent'), true)) {
            $mode = 'general';
        }

        $result = array(
            'vendor_id' => $vendor_id,
            'vendor_name' => $vendor_name,
            'email' => $email,
            'mode' => $mode,
            'lang' => $lang,
            'status' => 'failed',
            'message' => '',
            'log_id' => 0,
            'claim_token_id' => 0,
            'claim_link' => '',
            'mailpoet_subscriber_id' => 0,
            'mailpoet_tag' => '',
        );

        $record_failed = static function (string $message) use (&$result, $vendor_id, $mode, $lang): array {
            $result['message'] = $message;

            if ($vendor_id > 0) {
                $failed_log = vms_dt_vio_log_invite_event(array(
                    'vendor_id' => $vendor_id,
                    'mode' => $mode,
                    'lang' => $lang,
                    'mailpoet_tag' => '',
                    'status' => 'failed',
                    'error_message' => $message,
                    'sent_by_user_id' => absint(get_current_user_id()),
                ));
                if (!is_wp_error($failed_log)) {
                    $result['log_id'] = (int) $failed_log;
                }
            }

            return $result;
        };

        if ($vendor_id <= 0 || get_post_type($vendor_id) !== 'vms_vendor') {
            $result['message'] = __('Vendor is missing or invalid.', 'vms-data-tools');
            return $result;
        }

        if ($email === '' || !is_email($email)) {
            return $record_failed(__('Vendor email is missing or invalid.', 'vms-data-tools'));
        }

        if ($mode === 'intent' && $vendor_type === '') {
            return $record_failed(__('Intent invites require a vendor type before they can be sent.', 'vms-data-tools'));
        }

        if (empty($settings['mailpoet_enabled'])) {
            return $record_failed(__('MailPoet delivery is disabled in Vendor Invite settings. Enable it before committing invites.', 'vms-data-tools'));
        }

        if (!vms_dt_vio_mailpoet_is_active()) {
            return $record_failed(__('MailPoet is not active. Invite delivery is unavailable until MailPoet is installed and active.', 'vms-data-tools'));
        }

        $cooldown_days = absint($settings['resend_cooldown_days'] ?? 3);
        $last_invite_ts = vms_dt_vio_get_last_invite_at_ts($vendor_id);
        if (!$force_resend && $cooldown_days > 0 && $last_invite_ts > 0) {
            $cooldown_end = $last_invite_ts + ($cooldown_days * DAY_IN_SECONDS);
            if ($cooldown_end > time()) {
                return $record_failed(sprintf(
                    /* translators: %d is a number of days. */
                    __('Cooldown active. Resend is blocked for %d day(s) unless forced.', 'vms-data-tools'),
                    $cooldown_days
                ));
            }
        }

        $token_result = vms_dt_vio_create_claim_token($vendor_id, array(
            'created_by_user_id' => absint(get_current_user_id()),
            'token_expiration_days' => absint($settings['token_expiration_days'] ?? 14),
        ));
        if (is_wp_error($token_result)) {
            return $record_failed($token_result->get_error_message());
        }

        $claim_token_id = absint($token_result['token_id'] ?? 0);
        $claim_url = (string) ($token_result['claim_url'] ?? '');
        $result['claim_token_id'] = $claim_token_id;
        $result['claim_link'] = $claim_url;

        $open_dates_snippet = ($mode === 'intent') ? (string) ($row['open_dates_snippet'] ?? '') : '';
        $open_dates_count = absint($row['open_dates_count'] ?? 0);
        $payload_hash = ($open_dates_snippet !== '') ? hash('sha256', $open_dates_snippet) : null;

        $tag = vms_dt_vio_build_mailpoet_tag($mode, $lang, $vendor_type, $settings);
        $result['mailpoet_tag'] = $tag;

        $sync = vms_dt_vio_mailpoet_sync_invite(
            $email,
            $vendor_name,
            $tag,
            $claim_url,
            $open_dates_snippet,
            $settings
        );

        if (is_wp_error($sync)) {
            $result['message'] = $sync->get_error_message();

            $log_id = vms_dt_vio_log_invite_event(array(
                'vendor_id' => $vendor_id,
                'mode' => $mode,
                'lang' => $lang,
                'mailpoet_tag' => $tag,
                'open_dates_count' => $open_dates_count,
                'open_dates_payload_hash' => $payload_hash,
                'claim_token_id' => $claim_token_id,
                'status' => 'failed',
                'error_message' => $result['message'],
                'sent_by_user_id' => absint(get_current_user_id()),
            ));

            if (!is_wp_error($log_id)) {
                $result['log_id'] = (int) $log_id;
                vms_dt_vio_attach_token_to_log($claim_token_id, (int) $log_id);
            }

            vms_dt_vio_increment_vendor_invite_counters($vendor_id, $mode);
            return $result;
        }

        $subscriber_id = absint($sync['subscriber_id'] ?? 0);
        $result['mailpoet_subscriber_id'] = $subscriber_id;

        $log_id = vms_dt_vio_log_invite_event(array(
            'vendor_id' => $vendor_id,
            'mode' => $mode,
            'lang' => $lang,
            'mailpoet_subscriber_id' => $subscriber_id,
            'mailpoet_tag' => $tag,
            'open_dates_count' => $open_dates_count,
            'open_dates_payload_hash' => $payload_hash,
            'claim_token_id' => $claim_token_id,
            'status' => 'sent',
            'error_message' => null,
            'sent_by_user_id' => absint(get_current_user_id()),
        ));

        if (is_wp_error($log_id)) {
            $result['status'] = 'failed';
            $result['message'] = $log_id->get_error_message();
            vms_dt_vio_increment_vendor_invite_counters($vendor_id, $mode);
            return $result;
        }

        $result['status'] = 'sent';
        $result['message'] = __('Invite committed successfully.', 'vms-data-tools');
        $result['log_id'] = (int) $log_id;
        vms_dt_vio_attach_token_to_log($claim_token_id, (int) $log_id);

        vms_dt_vio_increment_vendor_invite_counters($vendor_id, $mode, true);
        vms_dt_vio_set_vendor_portal_status($vendor_id, 'invited');

        return $result;
    }
}

if (!function_exists('vms_dt_vio_commit_preview')) {
    /**
     * @param int[] $include_vendor_ids
     * @return array<string,mixed>|WP_Error
     */
    function vms_dt_vio_commit_preview(string $preview_token, array $include_vendor_ids, bool $force_resend = false)
    {
        $preview = vms_dt_vio_load_preview($preview_token);
        if (is_wp_error($preview)) {
            return $preview;
        }

        $settings = vms_dt_vio_get_settings();
        if (empty($settings['mailpoet_enabled'])) {
            return new WP_Error('vms_dt_vio_mailpoet_disabled', __('MailPoet delivery is disabled in Vendor Invite settings. Enable it before committing invites.', 'vms-data-tools'));
        }

        if (!vms_dt_vio_mailpoet_is_active()) {
            return new WP_Error('vms_dt_vio_mailpoet_required', __('MailPoet is required for commit when MailPoet integration is enabled.', 'vms-data-tools'));
        }

        $ids = array_values(array_unique(array_filter(array_map('absint', $include_vendor_ids))));
        if (empty($ids)) {
            return new WP_Error('vms_dt_vio_commit_none_selected', __('No vendors were selected for commit.', 'vms-data-tools'));
        }

        $row_map = array();
        foreach ((array) ($preview['rows'] ?? array()) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $vendor_id = absint($row['vendor_id'] ?? 0);
            if ($vendor_id <= 0) {
                continue;
            }
            $row_map[$vendor_id] = $row;
        }

        $summary = array(
            'selected' => count($ids),
            'processed' => 0,
            'sent' => 0,
            'failed' => 0,
            'skipped' => 0,
        );

        $results = array();

        $per_minute = max(1, absint($settings['invites_per_minute'] ?? 20));
        $throttle_us = (int) floor((60 / $per_minute) * 1000000);

        foreach ($ids as $vendor_id) {
            if (!isset($row_map[$vendor_id])) {
                $summary['skipped']++;
                continue;
            }

            $row = (array) $row_map[$vendor_id];
            $summary['processed']++;

            $sent = vms_dt_vio_send_invite_for_row($row, (array) ($preview['args'] ?? array()), $settings, $force_resend);
            $results[] = $sent;

            if (($sent['status'] ?? 'failed') === 'sent') {
                $summary['sent']++;
            } else {
                $summary['failed']++;
            }

            if ($throttle_us > 0) {
                usleep($throttle_us);
            }
        }

        delete_transient(VMS_DT_VIO_PREVIEW_KEY_PREFIX . $preview_token);

        return array(
            'summary' => $summary,
            'results' => $results,
            'args' => $preview['args'] ?? array(),
            'committed_at' => vms_dt_vio_now_gmt_mysql(),
        );
    }
}

if (!function_exists('vms_dt_vio_generate_claim_link_for_vendor')) {
    /**
     * @return array<string,mixed>|WP_Error
     */
    function vms_dt_vio_generate_claim_link_for_vendor(int $vendor_id)
    {
        $vendor_id = absint($vendor_id);
        $token = vms_dt_vio_create_claim_token($vendor_id, array(
            'created_by_user_id' => absint(get_current_user_id()),
        ));
        if (is_wp_error($token)) {
            return $token;
        }

        $keys = vms_dt_vio_vendor_meta_keys();
        $mode = sanitize_key((string) get_post_meta($vendor_id, $keys['last_invite_mode'], true));
        if (!in_array($mode, array('general', 'intent'), true)) {
            $mode = 'general';
        }

        $log_id = vms_dt_vio_log_invite_event(array(
            'event_type' => 'claim_link_generate',
            'vendor_id' => $vendor_id,
            'mode' => $mode,
            'lang' => vms_dt_vio_resolve_vendor_language($vendor_id),
            'claim_token_id' => absint($token['token_id'] ?? 0),
            'status' => 'sent',
            'sent_by_user_id' => absint(get_current_user_id()),
        ));

        if (!is_wp_error($log_id)) {
            $token['log_id'] = (int) $log_id;
            vms_dt_vio_attach_token_to_log(absint($token['token_id'] ?? 0), (int) $log_id);
        }

        return $token;
    }
}

if (!function_exists('vms_dt_vio_resend_from_log')) {
    /**
     * @return array<string,mixed>|WP_Error
     */
    function vms_dt_vio_resend_from_log(int $log_id, bool $force_resend = false)
    {
        $log = vms_dt_vio_get_log_row($log_id);
        if (!is_array($log)) {
            return new WP_Error('vms_dt_vio_log_not_found', __('Invite log row not found.', 'vms-data-tools'));
        }

        $vendor_id = absint($log['vendor_id'] ?? 0);
        if ($vendor_id <= 0 || get_post_type($vendor_id) !== 'vms_vendor') {
            return new WP_Error('vms_dt_vio_vendor_missing', __('Vendor is missing for this log row.', 'vms-data-tools'));
        }

        $mode = sanitize_key((string) ($log['mode'] ?? 'general'));
        if (!in_array($mode, array('general', 'intent'), true)) {
            $mode = 'general';
        }

        $lang = vms_dt_vio_sanitize_lang((string) ($log['lang'] ?? ''));
        if ($lang === '') {
            $lang = vms_dt_vio_site_default_lang();
        }

        $settings = vms_dt_vio_get_settings();
        $vendor_type = vms_dt_vio_get_vendor_type_slug($vendor_id);

        $row = array(
            'vendor_id' => $vendor_id,
            'vendor_name' => (string) get_the_title($vendor_id),
            'vendor_type' => $vendor_type,
            'email' => vms_dt_vio_get_vendor_email($vendor_id),
            'lang' => $lang,
            'mode' => $mode,
            'open_dates_count' => 0,
            'open_dates_snippet' => '',
        );

        if ($mode === 'intent' && $vendor_type !== '') {
            $open_dates = vms_dt_vio_find_open_dates(array(
                'vendor_type' => $vendor_type,
                'next_n' => $settings['default_next_n'] ?? 8,
                'lookahead_days' => $settings['default_lookahead_days'] ?? 90,
                'venue_ids' => array(),
                'include_primary_vendor' => false,
                'include_tentative' => false,
            ));
            $row['open_dates_count'] = count($open_dates);
            $row['open_dates_snippet'] = vms_dt_vio_render_open_dates_snippet($open_dates, $lang, array(
                'include_primary_vendor' => false,
                'include_tentative' => false,
            ));
        }

        $sent = vms_dt_vio_send_invite_for_row($row, array('invite_mode' => $mode), $settings, $force_resend);
        return array(
            'log' => $log,
            'result' => $sent,
        );
    }
}

if (!function_exists('vms_dt_vio_mark_vendor_claimed')) {
    function vms_dt_vio_mark_vendor_claimed(int $vendor_id, int $user_id): void
    {
        $vendor_id = absint($vendor_id);
        $user_id = absint($user_id);
        if ($vendor_id <= 0 || $user_id <= 0) {
            return;
        }

        $keys = vms_dt_vio_vendor_meta_keys();
        update_post_meta($vendor_id, $keys['claim_user_id'], $user_id);
        vms_dt_vio_set_vendor_portal_status($vendor_id, 'claimed');

        // Keep compatibility with VMS portal linkage.
        update_post_meta(
            $vendor_id,
            (string) vms_dt_core_constant('VMS_VENDOR_PRIMARY_USER_META_KEY', '_vms_vendor_user_id'),
            $user_id
        );
        update_user_meta(
            $user_id,
            (string) vms_dt_core_constant('VMS_USER_PRIMARY_VENDOR_META_KEY', '_vms_vendor_id'),
            $vendor_id
        );
    }
}
