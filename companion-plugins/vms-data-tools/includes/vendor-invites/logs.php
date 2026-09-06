<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_dt_vio_log_invite_event')) {
    /**
     * @param array<string,mixed> $data
     * @return int|WP_Error
     */
    function vms_dt_vio_log_invite_event(array $data)
    {
        global $wpdb;

        $table = vms_dt_vio_invite_log_table();
        $defaults = array(
            'event_type' => 'invite_send',
            'vendor_id' => 0,
            'mode' => 'general',
            'lang' => vms_dt_vio_site_default_lang(),
            'mailpoet_subscriber_id' => null,
            'mailpoet_tag' => '',
            'mailpoet_template_key' => null,
            'sent_at' => vms_dt_vio_now_gmt_mysql(),
            'sent_by_user_id' => absint(get_current_user_id()),
            'open_dates_count' => 0,
            'open_dates_payload_hash' => null,
            'claim_token_id' => null,
            'status' => 'queued',
            'error_message' => null,
        );

        $row = wp_parse_args($data, $defaults);

        $row['event_type'] = vms_dt_vio_normalize_log_event_type((string) $row['event_type']);

        $row['vendor_id'] = absint($row['vendor_id']);
        if ($row['vendor_id'] <= 0) {
            return new WP_Error('vms_dt_vio_log_vendor_missing', __('Cannot write invite log without vendor.', 'vms-data-tools'));
        }

        $mode = sanitize_key((string) $row['mode']);
        if (!in_array($mode, array('general', 'intent'), true)) {
            $mode = 'general';
        }
        $row['mode'] = $mode;

        $row['lang'] = vms_dt_vio_sanitize_lang((string) $row['lang']);
        if ($row['lang'] === '') {
            $row['lang'] = vms_dt_vio_site_default_lang();
        }

        $row['mailpoet_subscriber_id'] = absint($row['mailpoet_subscriber_id']) ?: null;
        $row['mailpoet_tag'] = sanitize_key((string) $row['mailpoet_tag']);
        $row['mailpoet_template_key'] = sanitize_key((string) $row['mailpoet_template_key']);
        if ($row['mailpoet_template_key'] === '') {
            $row['mailpoet_template_key'] = null;
        }

        $row['sent_at'] = trim((string) $row['sent_at']);
        if ($row['sent_at'] === '') {
            $row['sent_at'] = vms_dt_vio_now_gmt_mysql();
        }

        $row['sent_by_user_id'] = absint($row['sent_by_user_id']);
        $row['open_dates_count'] = max(0, absint($row['open_dates_count']));

        $payload_hash = trim((string) $row['open_dates_payload_hash']);
        $row['open_dates_payload_hash'] = ($payload_hash !== '') ? $payload_hash : null;

        $row['claim_token_id'] = absint($row['claim_token_id']) ?: null;

        $status = sanitize_key((string) $row['status']);
        if (!in_array($status, array('queued', 'sent', 'failed'), true)) {
            $status = 'failed';
        }
        $row['status'] = $status;

        $error_message = trim((string) $row['error_message']);
        $row['error_message'] = ($error_message !== '') ? $error_message : null;

        $inserted = $wpdb->insert(
            $table,
            array(
                'event_type' => $row['event_type'],
                'vendor_id' => $row['vendor_id'],
                'mode' => $row['mode'],
                'lang' => $row['lang'],
                'mailpoet_subscriber_id' => $row['mailpoet_subscriber_id'],
                'mailpoet_tag' => $row['mailpoet_tag'],
                'mailpoet_template_key' => $row['mailpoet_template_key'],
                'sent_at' => $row['sent_at'],
                'sent_by_user_id' => $row['sent_by_user_id'],
                'open_dates_count' => $row['open_dates_count'],
                'open_dates_payload_hash' => $row['open_dates_payload_hash'],
                'claim_token_id' => $row['claim_token_id'],
                'status' => $row['status'],
                'error_message' => $row['error_message'],
            ),
            array('%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%s')
        );

        if (!$inserted) {
            return new WP_Error('vms_dt_vio_log_insert_failed', __('Unable to write invite log record.', 'vms-data-tools'));
        }

        return (int) $wpdb->insert_id;
    }
}

if (!function_exists('vms_dt_vio_log_claim_attempt')) {
    function vms_dt_vio_log_claim_attempt(int $vendor_id, int $token_id, string $status, string $message = '', array $context = array()): void
    {
        $vendor_id = absint($vendor_id);
        if ($vendor_id <= 0) {
            return;
        }

        $mode = sanitize_key((string) ($context['mode'] ?? 'general'));
        if (!in_array($mode, array('general', 'intent'), true)) {
            $mode = 'general';
        }

        $lang = vms_dt_vio_sanitize_lang((string) ($context['lang'] ?? ''));
        if ($lang === '') {
            $lang = vms_dt_vio_site_default_lang();
        }

        $log_result = vms_dt_vio_log_invite_event(array(
            'event_type' => 'claim_attempt',
            'vendor_id' => $vendor_id,
            'mode' => $mode,
            'lang' => $lang,
            'mailpoet_tag' => '',
            'sent_by_user_id' => absint(get_current_user_id()),
            'claim_token_id' => absint($token_id),
            'status' => $status,
            'error_message' => $message,
        ));

        if (is_wp_error($log_result)) {
            error_log('[VIO] Claim attempt log insert failed: ' . $log_result->get_error_message());
        }
    }
}

if (!function_exists('vms_dt_vio_increment_vendor_invite_counters')) {
    function vms_dt_vio_increment_vendor_invite_counters(int $vendor_id, string $mode, bool $mark_sent = false): void
    {
        if (!$mark_sent) {
            return;
        }

        $vendor_id = absint($vendor_id);
        if ($vendor_id <= 0) {
            return;
        }

        $keys = vms_dt_vio_vendor_meta_keys();

        $count = (int) get_post_meta($vendor_id, $keys['invite_count'], true);
        update_post_meta($vendor_id, $keys['invite_count'], max(0, $count) + 1);
        update_post_meta($vendor_id, $keys['last_invite_at'], vms_dt_vio_now_gmt_mysql());
        update_post_meta($vendor_id, $keys['last_invite_mode'], sanitize_key($mode));

        if (vms_dt_vio_get_vendor_portal_status($vendor_id) === 'unclaimed') {
            vms_dt_vio_set_vendor_portal_status($vendor_id, 'invited');
        }
    }
}

if (!function_exists('vms_dt_vio_get_last_invite_at_ts')) {
    function vms_dt_vio_get_last_invite_at_ts(int $vendor_id): int
    {
        $vendor_id = absint($vendor_id);
        if ($vendor_id <= 0) {
            return 0;
        }

        global $wpdb;
        if (isset($wpdb) && $wpdb instanceof wpdb) {
            $table = vms_dt_vio_invite_log_table();
            $latest_any = $wpdb->get_var($wpdb->prepare(
                "SELECT sent_at FROM {$table} WHERE vendor_id = %d AND event_type = %s ORDER BY sent_at DESC LIMIT 1",
                $vendor_id,
                'invite_send'
            ));
            if (is_string($latest_any) && trim($latest_any) !== '') {
                $latest_sent = $wpdb->get_var($wpdb->prepare(
                    "SELECT sent_at FROM {$table} WHERE vendor_id = %d AND event_type = %s AND status = %s ORDER BY sent_at DESC LIMIT 1",
                    $vendor_id,
                    'invite_send',
                    'sent'
                ));
                if (is_string($latest_sent) && trim($latest_sent) !== '') {
                    $ts = strtotime($latest_sent . ' UTC');
                    return ($ts !== false) ? (int) $ts : 0;
                }

                // If the vendor has invite attempts but none with status=sent,
                // do not enforce cooldown from legacy meta timestamps.
                return 0;
            }
        }

        $keys = vms_dt_vio_vendor_meta_keys();
        $raw = (string) get_post_meta($vendor_id, $keys['last_invite_at'], true);
        if ($raw === '') {
            return 0;
        }

        $ts = strtotime($raw . ' UTC');
        return ($ts !== false) ? (int) $ts : 0;
    }
}

if (!function_exists('vms_dt_vio_get_logs')) {
    /**
     * @param array<string,mixed> $filters
     * @return array<int,array<string,mixed>>
     */
    function vms_dt_vio_get_logs(array $filters = array()): array
    {
        global $wpdb;

        $log_table = vms_dt_vio_invite_log_table();
        $token_table = vms_dt_vio_claim_tokens_table();

        $where = array('1=1');
        $params = array();

        $event_type = sanitize_key((string) ($filters['event_type'] ?? ''));
        if ($event_type !== '') {
            $where[] = 'l.event_type = %s';
            $params[] = $event_type;
        }

        $vendor_id = absint($filters['vendor_id'] ?? 0);
        if ($vendor_id > 0) {
            $where[] = 'l.vendor_id = %d';
            $params[] = $vendor_id;
        }

        $mode = sanitize_key((string) ($filters['mode'] ?? ''));
        if ($mode !== '') {
            $where[] = 'l.mode = %s';
            $params[] = $mode;
        }

        $status = sanitize_key((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $where[] = 'l.status = %s';
            $params[] = $status;
        }

        $retention_state = vms_dt_vio_normalize_retention_filter((string) ($filters['retention_state'] ?? 'active'));
        if ($retention_state !== 'all') {
            $where[] = 'l.retention_state = %s';
            $params[] = vms_dt_vio_normalize_retention_state($retention_state);
        }

        $date_from = trim((string) ($filters['date_from'] ?? ''));
        if ($date_from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
            $where[] = 'l.sent_at >= %s';
            $params[] = $date_from . ' 00:00:00';
        }

        $date_to = trim((string) ($filters['date_to'] ?? ''));
        if ($date_to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
            $where[] = 'l.sent_at <= %s';
            $params[] = $date_to . ' 23:59:59';
        }

        $limit = absint($filters['limit'] ?? 100);
        if ($limit <= 0) {
            $limit = 100;
        }
        if ($limit > 500) {
            $limit = 500;
        }

        $sql = "
            SELECT
                l.*,
                t.used_at AS claim_used_at,
                t.expires_at AS claim_expires_at,
                p.post_title AS vendor_name
            FROM {$log_table} l
            LEFT JOIN {$token_table} t ON t.id = l.claim_token_id
            LEFT JOIN {$wpdb->posts} p ON p.ID = l.vendor_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY l.id DESC
            LIMIT %d
        ";

        $params[] = $limit;
        $prepared = $wpdb->prepare($sql, $params);
        if (!is_string($prepared) || $prepared === '') {
            return array();
        }

        $rows = $wpdb->get_results($prepared, ARRAY_A);
        return is_array($rows) ? $rows : array();
    }
}

if (!function_exists('vms_dt_vio_get_log_row')) {
    /**
     * @return array<string,mixed>|null
     */
    function vms_dt_vio_get_log_row(int $log_id): ?array
    {
        global $wpdb;

        $log_id = absint($log_id);
        if ($log_id <= 0) {
            return null;
        }

        $table = vms_dt_vio_invite_log_table();
        $token_table = vms_dt_vio_claim_tokens_table();
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT
                l.*,
                t.used_at AS claim_used_at,
                t.expires_at AS claim_expires_at,
                p.post_title AS vendor_name
             FROM {$table} l
             LEFT JOIN {$token_table} t ON t.id = l.claim_token_id
             LEFT JOIN {$wpdb->posts} p ON p.ID = l.vendor_id
             WHERE l.id = %d
             LIMIT 1",
            $log_id
        ), ARRAY_A);

        return is_array($row) ? $row : null;
    }
}

if (!function_exists('vms_dt_vio_refresh_vendor_invite_counters_from_logs')) {
    function vms_dt_vio_refresh_vendor_invite_counters_from_logs(int $vendor_id): void
    {
        global $wpdb;

        $vendor_id = absint($vendor_id);
        if ($vendor_id <= 0 || !isset($wpdb) || !($wpdb instanceof wpdb)) {
            return;
        }

        $table = vms_dt_vio_invite_log_table();
        $keys = vms_dt_vio_vendor_meta_keys();

        $count_sent = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE vendor_id = %d AND event_type = %s AND status = %s",
            $vendor_id,
            'invite_send',
            'sent'
        ));

        if ($count_sent <= 0) {
            delete_post_meta($vendor_id, $keys['invite_count']);
            delete_post_meta($vendor_id, $keys['last_invite_at']);
            delete_post_meta($vendor_id, $keys['last_invite_mode']);
            return;
        }

        $latest_row = $wpdb->get_row($wpdb->prepare(
            "SELECT sent_at, mode
             FROM {$table}
             WHERE vendor_id = %d AND event_type = %s AND status = %s
             ORDER BY sent_at DESC, id DESC
             LIMIT 1",
            $vendor_id,
            'invite_send',
            'sent'
        ), ARRAY_A);

        update_post_meta($vendor_id, $keys['invite_count'], $count_sent);

        if (is_array($latest_row)) {
            $latest_at = trim((string) ($latest_row['sent_at'] ?? ''));
            $latest_mode = sanitize_key((string) ($latest_row['mode'] ?? ''));

            if ($latest_at !== '') {
                update_post_meta($vendor_id, $keys['last_invite_at'], $latest_at);
            } else {
                delete_post_meta($vendor_id, $keys['last_invite_at']);
            }

            if ($latest_mode !== '') {
                update_post_meta($vendor_id, $keys['last_invite_mode'], $latest_mode);
            } else {
                delete_post_meta($vendor_id, $keys['last_invite_mode']);
            }
        }
    }
}

if (!function_exists('vms_dt_vio_delete_failed_invite_log')) {
    /**
     * @return array<string,int>|WP_Error
     */
    function vms_dt_vio_delete_failed_invite_log(int $log_id)
    {
        $log_id = absint($log_id);
        if ($log_id <= 0) {
            return new WP_Error('vms_dt_vio_log_delete_invalid', __('Invalid log ID.', 'vms-data-tools'));
        }

        return new WP_Error(
            'vms_dt_vio_log_delete_disabled',
            __('Direct failed-log deletion is disabled. Use Invite Logs -> Retention Controls instead.', 'vms-data-tools'),
        );
    }
}
