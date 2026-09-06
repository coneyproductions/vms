<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_dt_vio_normalize_retention_state')) {
    function vms_dt_vio_normalize_retention_state(string $state): string
    {
        $state = sanitize_key($state);
        return in_array($state, array('active', 'archived'), true) ? $state : 'active';
    }
}

if (!function_exists('vms_dt_vio_normalize_retention_filter')) {
    function vms_dt_vio_normalize_retention_filter(string $state): string
    {
        $state = sanitize_key($state);
        return in_array($state, array('active', 'archived', 'all'), true) ? $state : 'active';
    }
}

if (!function_exists('vms_dt_vio_normalize_retention_action')) {
    function vms_dt_vio_normalize_retention_action(string $action): string
    {
        $action = sanitize_key($action);
        return in_array($action, array('archive', 'restore', 'purge'), true) ? $action : '';
    }
}

if (!function_exists('vms_dt_vio_normalize_retention_scope')) {
    function vms_dt_vio_normalize_retention_scope(string $scope): string
    {
        $scope = sanitize_key($scope);
        return in_array($scope, array('filtered', 'single'), true) ? $scope : 'filtered';
    }
}

if (!function_exists('vms_dt_vio_get_retention_action_labels')) {
    /**
     * @return array<string,string>
     */
    function vms_dt_vio_get_retention_action_labels(): array
    {
        return array(
            'archive' => __('Archive', 'vms-data-tools'),
            'restore' => __('Restore', 'vms-data-tools'),
            'purge' => __('Purge Eligible Archived Logs', 'vms-data-tools'),
        );
    }
}

if (!function_exists('vms_dt_vio_get_retention_state_labels')) {
    /**
     * @return array<string,string>
     */
    function vms_dt_vio_get_retention_state_labels(): array
    {
        return array(
            'active' => __('Active', 'vms-data-tools'),
            'archived' => __('Archived', 'vms-data-tools'),
        );
    }
}

if (!function_exists('vms_dt_vio_generate_retention_batch_id')) {
    function vms_dt_vio_generate_retention_batch_id(): string
    {
        try {
            return bin2hex(random_bytes(16));
        } catch (Exception $e) {
            return substr(md5(uniqid('vms_dt_vio_retention_', true)), 0, 32);
        }
    }
}

if (!function_exists('vms_dt_vio_find_logs_for_retention')) {
    /**
     * @param array<string,mixed> $filters
     * @return array<int,array<string,mixed>>
     */
    function vms_dt_vio_find_logs_for_retention(array $filters, int $limit = 500): array
    {
        $query = array(
            'event_type' => sanitize_key((string) ($filters['event_type'] ?? '')),
            'vendor_id' => absint($filters['vendor_id'] ?? 0),
            'mode' => sanitize_key((string) ($filters['mode'] ?? '')),
            'status' => sanitize_key((string) ($filters['status'] ?? '')),
            'date_from' => sanitize_text_field((string) ($filters['date_from'] ?? '')),
            'date_to' => sanitize_text_field((string) ($filters['date_to'] ?? '')),
            'retention_state' => vms_dt_vio_normalize_retention_filter((string) ($filters['retention_state'] ?? 'active')),
            'limit' => max(1, min(500, absint($limit))),
        );

        return vms_dt_vio_get_logs($query);
    }
}

if (!function_exists('vms_dt_vio_get_retention_token_row')) {
    /**
     * @return array<string,mixed>|null
     */
    function vms_dt_vio_get_retention_token_row(int $token_id): ?array
    {
        global $wpdb;

        $token_id = absint($token_id);
        if ($token_id <= 0) {
            return null;
        }

        $table = vms_dt_vio_claim_tokens_table();
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, vendor_id, invite_log_id, created_at, expires_at, used_at
                 FROM {$table}
                 WHERE id = %d
                 LIMIT 1",
                $token_id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }
}

if (!function_exists('vms_dt_vio_build_retention_snapshot')) {
    /**
     * @param array<string,mixed> $log
     * @param array<string,mixed>|null $token
     * @return array<string,mixed>
     */
    function vms_dt_vio_build_retention_snapshot(array $log, ?array $token): array
    {
        $snapshot = array(
            'log' => array(
                'id' => absint($log['id'] ?? 0),
                'event_type' => sanitize_key((string) ($log['event_type'] ?? '')),
                'vendor_id' => absint($log['vendor_id'] ?? 0),
                'mode' => sanitize_key((string) ($log['mode'] ?? '')),
                'lang' => sanitize_key((string) ($log['lang'] ?? '')),
                'mailpoet_subscriber_id' => absint($log['mailpoet_subscriber_id'] ?? 0),
                'mailpoet_tag' => sanitize_key((string) ($log['mailpoet_tag'] ?? '')),
                'mailpoet_template_key' => sanitize_key((string) ($log['mailpoet_template_key'] ?? '')),
                'sent_at' => (string) ($log['sent_at'] ?? ''),
                'sent_by_user_id' => absint($log['sent_by_user_id'] ?? 0),
                'open_dates_count' => absint($log['open_dates_count'] ?? 0),
                'open_dates_payload_hash' => (string) ($log['open_dates_payload_hash'] ?? ''),
                'claim_token_id' => absint($log['claim_token_id'] ?? 0),
                'status' => sanitize_key((string) ($log['status'] ?? '')),
                'error_message' => (string) ($log['error_message'] ?? ''),
                'retention_state' => vms_dt_vio_normalize_retention_state((string) ($log['retention_state'] ?? 'active')),
                'archived_at' => (string) ($log['archived_at'] ?? ''),
                'archived_by_user_id' => absint($log['archived_by_user_id'] ?? 0),
                'archived_reason' => (string) ($log['archived_reason'] ?? ''),
                'retention_batch_id' => (string) ($log['retention_batch_id'] ?? ''),
            ),
        );

        if (is_array($token)) {
            $snapshot['token'] = array(
                'id' => absint($token['id'] ?? 0),
                'vendor_id' => absint($token['vendor_id'] ?? 0),
                'invite_log_id' => absint($token['invite_log_id'] ?? 0),
                'created_at' => (string) ($token['created_at'] ?? ''),
                'expires_at' => (string) ($token['expires_at'] ?? ''),
                'used_at' => (string) ($token['used_at'] ?? ''),
            );
        }

        return $snapshot;
    }
}

if (!function_exists('vms_dt_vio_make_retention_reason_text')) {
    function vms_dt_vio_make_retention_reason_text(string $reason_code, string $operator_note = ''): string
    {
        $reason_code = sanitize_key($reason_code);
        if ($reason_code === '') {
            $reason_code = 'manual_cleanup';
        }

        $operator_note = trim($operator_note);
        $text = ($operator_note !== '') ? $operator_note : $reason_code;

        if (function_exists('mb_substr')) {
            return (string) mb_substr($text, 0, 190);
        }

        return substr($text, 0, 190);
    }
}

if (!function_exists('vms_dt_vio_make_retention_result')) {
    /**
     * @return array<string,mixed>
     */
    function vms_dt_vio_make_retention_result(string $action, string $scope, string $batch_id): array
    {
        return array(
            'action' => $action,
            'scope' => $scope,
            'batch_id' => $batch_id,
            'summary' => array(
                'archived' => 0,
                'restored' => 0,
                'purged' => 0,
                'deleted_tokens' => 0,
                'skipped' => 0,
                'skip_reasons' => array(),
            ),
            'results' => array(),
        );
    }
}

if (!function_exists('vms_dt_vio_add_retention_skip')) {
    /**
     * @param array<string,mixed> $result
     * @param array<string,mixed> $row_result
     * @return array<string,mixed>
     */
    function vms_dt_vio_add_retention_skip(array $result, string $reason, array $row_result = array()): array
    {
        $reason = trim($reason);
        if ($reason === '') {
            $reason = __('Row was skipped.', 'vms-data-tools');
        }

        $result['summary']['skipped'] = absint($result['summary']['skipped'] ?? 0) + 1;
        $reasons = isset($result['summary']['skip_reasons']) && is_array($result['summary']['skip_reasons'])
            ? $result['summary']['skip_reasons']
            : array();
        $reasons[$reason] = absint($reasons[$reason] ?? 0) + 1;
        $result['summary']['skip_reasons'] = $reasons;

        $row_result['status'] = 'skipped';
        $row_result['message'] = $reason;
        $result['results'][] = $row_result;

        return $result;
    }
}

if (!function_exists('vms_dt_vio_evaluate_purge_eligibility')) {
    /**
     * @param array<string,mixed> $log
     * @return array<string,mixed>
     */
    function vms_dt_vio_evaluate_purge_eligibility(array $log): array
    {
        $reasons = array();
        $token_delete_count = 0;
        $used_token_blocked = false;
        $claim_token_id = absint($log['claim_token_id'] ?? 0);
        $retention_state = vms_dt_vio_normalize_retention_state((string) ($log['retention_state'] ?? 'active'));
        $event_type = sanitize_key((string) ($log['event_type'] ?? ''));
        $status = sanitize_key((string) ($log['status'] ?? ''));
        $token = null;

        if ($retention_state !== 'archived') {
            $reasons[] = __('Log is not archived yet.', 'vms-data-tools');
        }

        if ($event_type === 'invite_send' && $status === 'sent') {
            $reasons[] = __('Successful invite-send logs cannot be purged.', 'vms-data-tools');
        }

        if ($event_type === 'claim_attempt' && $status === 'sent') {
            $reasons[] = __('Successful claim-completion logs cannot be purged.', 'vms-data-tools');
        }

        if ($claim_token_id > 0) {
            $token = vms_dt_vio_get_retention_token_row($claim_token_id);
            if (!is_array($token)) {
                $reasons[] = __('Linked claim token could not be verified.', 'vms-data-tools');
            } else {
                $used_at = trim((string) ($token['used_at'] ?? ''));
                if ($used_at !== '' && $used_at !== '0000-00-00 00:00:00') {
                    $reasons[] = __('Linked claim token was already used.', 'vms-data-tools');
                    $used_token_blocked = true;
                } else {
                    $token_delete_count = 1;
                }
            }
        }

        $eligible = empty($reasons);
        $token_impact = __('No token deletion', 'vms-data-tools');
        if ($eligible && $token_delete_count > 0) {
            $token_impact = __('Delete unused linked token', 'vms-data-tools');
        } elseif (!$eligible && $used_token_blocked) {
            $token_impact = __('Linked token already used', 'vms-data-tools');
        } elseif (!$eligible && $claim_token_id > 0 && !is_array($token)) {
            $token_impact = __('Linked token missing', 'vms-data-tools');
        }

        return array(
            'eligible' => $eligible,
            'reasons' => $reasons,
            'token_delete_count' => $token_delete_count,
            'used_token_blocked' => $used_token_blocked,
            'token_impact' => $token_impact,
            'token_row' => $token,
        );
    }
}

if (!function_exists('vms_dt_vio_evaluate_retention_action')) {
    /**
     * @param array<string,mixed> $log
     * @return array<string,mixed>
     */
    function vms_dt_vio_evaluate_retention_action(string $action, array $log): array
    {
        $action = vms_dt_vio_normalize_retention_action($action);
        $retention_state = vms_dt_vio_normalize_retention_state((string) ($log['retention_state'] ?? 'active'));

        if ($action === 'archive') {
            if ($retention_state === 'archived') {
                return array(
                    'eligible' => false,
                    'reasons' => array(__('Log is already archived.', 'vms-data-tools')),
                    'token_delete_count' => 0,
                    'used_token_blocked' => false,
                    'token_impact' => __('No token deletion', 'vms-data-tools'),
                    'outcome_detail' => __('Already archived.', 'vms-data-tools'),
                    'token_row' => null,
                );
            }

            return array(
                'eligible' => true,
                'reasons' => array(),
                'token_delete_count' => 0,
                'used_token_blocked' => false,
                'token_impact' => __('No token deletion', 'vms-data-tools'),
                'outcome_detail' => __('Will move to Archived view on commit.', 'vms-data-tools'),
                'token_row' => null,
            );
        }

        if ($action === 'restore') {
            if ($retention_state !== 'archived') {
                return array(
                    'eligible' => false,
                    'reasons' => array(__('Log is not archived yet.', 'vms-data-tools')),
                    'token_delete_count' => 0,
                    'used_token_blocked' => false,
                    'token_impact' => __('No token deletion', 'vms-data-tools'),
                    'outcome_detail' => __('Only archived rows can be restored.', 'vms-data-tools'),
                    'token_row' => null,
                );
            }

            return array(
                'eligible' => true,
                'reasons' => array(),
                'token_delete_count' => 0,
                'used_token_blocked' => false,
                'token_impact' => __('No token deletion', 'vms-data-tools'),
                'outcome_detail' => __('Will return to Active view on commit.', 'vms-data-tools'),
                'token_row' => null,
            );
        }

        $purge = vms_dt_vio_evaluate_purge_eligibility($log);
        $purge['outcome_detail'] = $purge['eligible']
            ? __('Will purge archived log on commit.', 'vms-data-tools')
            : implode(' ', array_map('trim', (array) ($purge['reasons'] ?? array())));

        return $purge;
    }
}

if (!function_exists('vms_dt_vio_build_retention_preview')) {
    /**
     * @param array<string,mixed> $args
     * @return array<string,mixed>|WP_Error
     */
    function vms_dt_vio_build_retention_preview(array $args)
    {
        $action = vms_dt_vio_normalize_retention_action((string) ($args['action'] ?? ''));
        if ($action === '') {
            return new WP_Error('vms_dt_vio_retention_action_invalid', __('Choose a retention action before building a preview.', 'vms-data-tools'));
        }

        $scope = vms_dt_vio_normalize_retention_scope((string) ($args['scope'] ?? 'filtered'));
        $filters = is_array($args['filters'] ?? null) ? (array) $args['filters'] : array();
        $filters['retention_state'] = vms_dt_vio_normalize_retention_filter((string) ($filters['retention_state'] ?? 'active'));

        $rows = array();
        if ($scope === 'single') {
            $raw_log_ids = array();
            if (!empty($args['log_ids']) && is_array($args['log_ids'])) {
                $raw_log_ids = (array) $args['log_ids'];
            } elseif (!empty($args['log_id'])) {
                $raw_log_ids = array($args['log_id']);
            }

            $log_ids = array_values(array_unique(array_filter(array_map('absint', $raw_log_ids))));
            foreach ($log_ids as $log_id) {
                $row = vms_dt_vio_get_log_row($log_id);
                if (is_array($row)) {
                    $rows[] = $row;
                }
            }
        } else {
            $rows = vms_dt_vio_find_logs_for_retention($filters, 500);
        }

        $action_labels = vms_dt_vio_get_retention_action_labels();
        $state_labels = vms_dt_vio_get_retention_state_labels();
        $event_labels = vms_dt_vio_log_event_type_labels();

        $preview = array(
            'action' => $action,
            'action_label' => (string) ($action_labels[$action] ?? ucfirst($action)),
            'scope' => $scope,
            'filters' => array(
                'event_type' => sanitize_key((string) ($filters['event_type'] ?? '')),
                'vendor_id' => absint($filters['vendor_id'] ?? 0),
                'mode' => sanitize_key((string) ($filters['mode'] ?? '')),
                'status' => sanitize_key((string) ($filters['status'] ?? '')),
                'date_from' => sanitize_text_field((string) ($filters['date_from'] ?? '')),
                'date_to' => sanitize_text_field((string) ($filters['date_to'] ?? '')),
                'retention_state' => vms_dt_vio_normalize_retention_filter((string) ($filters['retention_state'] ?? 'active')),
            ),
            'created_at' => vms_dt_vio_now_gmt_mysql(),
            'summary' => array(
                'candidate_count' => 0,
                'eligible_count' => 0,
                'skipped_count' => 0,
                'token_delete_count' => 0,
                'blocked_used_tokens' => 0,
                'skip_reasons' => array(),
            ),
            'rows' => array(),
            'eligible_log_ids' => array(),
            'candidate_log_ids' => array(),
        );

        foreach ($rows as $log) {
            if (!is_array($log)) {
                continue;
            }

            $log_id = absint($log['id'] ?? 0);
            if ($log_id <= 0) {
                continue;
            }

            $preview['summary']['candidate_count']++;
            $preview['candidate_log_ids'][] = $log_id;

            $evaluation = vms_dt_vio_evaluate_retention_action($action, $log);
            $eligible = !empty($evaluation['eligible']);
            $reasons = isset($evaluation['reasons']) && is_array($evaluation['reasons']) ? array_map('strval', $evaluation['reasons']) : array();

            if ($eligible) {
                $preview['summary']['eligible_count']++;
                $preview['eligible_log_ids'][] = $log_id;
                $preview['summary']['token_delete_count'] += absint($evaluation['token_delete_count'] ?? 0);
            } else {
                $preview['summary']['skipped_count']++;
                foreach ($reasons as $reason) {
                    $preview['summary']['skip_reasons'][$reason] = absint($preview['summary']['skip_reasons'][$reason] ?? 0) + 1;
                }
                if (!empty($evaluation['used_token_blocked'])) {
                    $preview['summary']['blocked_used_tokens']++;
                }
            }

            if (count($preview['rows']) >= 20) {
                continue;
            }

            $retention_state = vms_dt_vio_normalize_retention_state((string) ($log['retention_state'] ?? 'active'));
            $event_type = sanitize_key((string) ($log['event_type'] ?? ''));
            $vendor_name = trim((string) ($log['vendor_name'] ?? ''));
            $vendor_id = absint($log['vendor_id'] ?? 0);

            if ($vendor_name === '') {
                $vendor_name = sprintf(
                    /* translators: %d is a vendor ID. */
                    __('Vendor #%d', 'vms-data-tools'),
                    $vendor_id
                );
            }

            $preview['rows'][] = array(
                'log_id' => $log_id,
                'sent_at' => (string) ($log['sent_at'] ?? ''),
                'event_type' => $event_type,
                'event_label' => (string) ($event_labels[$event_type] ?? $event_type),
                'vendor_id' => $vendor_id,
                'vendor_name' => $vendor_name,
                'status' => sanitize_key((string) ($log['status'] ?? '')),
                'retention_state' => $retention_state,
                'retention_label' => (string) ($state_labels[$retention_state] ?? ucfirst($retention_state)),
                'archived_at' => (string) ($log['archived_at'] ?? ''),
                'token_impact' => (string) ($evaluation['token_impact'] ?? __('No token deletion', 'vms-data-tools')),
                'outcome' => $eligible ? __('Eligible', 'vms-data-tools') : __('Skip', 'vms-data-tools'),
                'outcome_detail' => $eligible
                    ? (string) ($evaluation['outcome_detail'] ?? __('Eligible for commit.', 'vms-data-tools'))
                    : implode(' ', $reasons),
            );
        }

        return $preview;
    }
}

if (!function_exists('vms_dt_vio_store_retention_preview')) {
    function vms_dt_vio_store_retention_preview(array $preview): string
    {
        $token = wp_generate_password(24, false, false);
        set_transient(VMS_DT_VIO_RETENTION_PREVIEW_KEY_PREFIX . $token, $preview, VMS_DT_VIO_PREVIEW_TTL);
        return $token;
    }
}

if (!function_exists('vms_dt_vio_get_retention_preview')) {
    /**
     * @return array<string,mixed>|WP_Error
     */
    function vms_dt_vio_get_retention_preview(string $token)
    {
        $token = trim($token);
        if ($token === '') {
            return new WP_Error('vms_dt_vio_retention_preview_token_missing', __('Retention preview token is missing.', 'vms-data-tools'));
        }

        $preview = get_transient(VMS_DT_VIO_RETENTION_PREVIEW_KEY_PREFIX . $token);
        if (!is_array($preview)) {
            return new WP_Error('vms_dt_vio_retention_preview_expired', __('Retention preview expired. Build the preview again.', 'vms-data-tools'));
        }

        return $preview;
    }
}

if (!function_exists('vms_dt_vio_commit_retention_preview')) {
    /**
     * @param array<string,mixed> $confirm_args
     * @return array<string,mixed>|WP_Error
     */
    function vms_dt_vio_commit_retention_preview(string $token, array $confirm_args)
    {
        $preview = vms_dt_vio_get_retention_preview($token);
        if (is_wp_error($preview)) {
            return $preview;
        }

        $action = vms_dt_vio_normalize_retention_action((string) ($preview['action'] ?? ''));
        if ($action === '') {
            return new WP_Error('vms_dt_vio_retention_action_missing', __('Retention preview is missing its action.', 'vms-data-tools'));
        }

        $user_id = absint($confirm_args['user_id'] ?? get_current_user_id());
        if ($user_id <= 0) {
            return new WP_Error('vms_dt_vio_retention_user_missing', __('A valid operator is required to commit retention changes.', 'vms-data-tools'));
        }

        if (empty($confirm_args['acknowledge'])) {
            return new WP_Error('vms_dt_vio_retention_ack_missing', __('Please confirm that you understand this retention action before committing.', 'vms-data-tools'));
        }

        $operator_note = sanitize_textarea_field((string) ($confirm_args['operator_note'] ?? ''));
        $reason_code = sanitize_key((string) ($confirm_args['reason_code'] ?? 'manual_cleanup'));
        if ($reason_code === '') {
            $reason_code = 'manual_cleanup';
        }

        if ($action === 'purge') {
            $purge_phrase = trim((string) ($confirm_args['purge_phrase'] ?? ''));
            if ($purge_phrase !== 'PURGE') {
                return new WP_Error('vms_dt_vio_retention_purge_phrase_invalid', __('Type PURGE to confirm an irreversible purge.', 'vms-data-tools'));
            }
        }

        $log_ids = array_values(array_unique(array_filter(array_map('absint', (array) ($preview['eligible_log_ids'] ?? array())))));
        if (empty($log_ids)) {
            return new WP_Error('vms_dt_vio_retention_preview_empty', __('This retention preview has no eligible rows to commit.', 'vms-data-tools'));
        }

        if ($action === 'archive') {
            $result = vms_dt_vio_archive_logs($log_ids, $user_id, $reason_code, $operator_note);
        } elseif ($action === 'restore') {
            $result = vms_dt_vio_restore_logs($log_ids, $user_id, $reason_code, $operator_note);
        } else {
            $result = vms_dt_vio_purge_logs($log_ids, $user_id, $reason_code, $operator_note);
        }

        if (is_wp_error($result)) {
            return $result;
        }

        $result['preview'] = $preview;
        delete_transient(VMS_DT_VIO_RETENTION_PREVIEW_KEY_PREFIX . trim($token));

        return $result;
    }
}

if (!function_exists('vms_dt_vio_archive_logs')) {
    /**
     * @param int[] $log_ids
     * @return array<string,mixed>|WP_Error
     */
    function vms_dt_vio_archive_logs(array $log_ids, int $user_id, string $reason_code, string $operator_note = '')
    {
        global $wpdb;

        if (!isset($wpdb) || !($wpdb instanceof wpdb)) {
            return new WP_Error('vms_dt_vio_retention_db_missing', __('Database is unavailable for archiving invite logs.', 'vms-data-tools'));
        }

        $log_ids = array_values(array_unique(array_filter(array_map('absint', $log_ids))));
        if (empty($log_ids)) {
            return new WP_Error('vms_dt_vio_archive_empty', __('Choose at least one invite log to archive.', 'vms-data-tools'));
        }

        $scope = (count($log_ids) === 1) ? 'single' : 'filtered';
        $batch_id = vms_dt_vio_generate_retention_batch_id();
        $reason_text = vms_dt_vio_make_retention_reason_text($reason_code, $operator_note);
        $table = vms_dt_vio_invite_log_table();
        $now = vms_dt_vio_now_gmt_mysql();
        $result = vms_dt_vio_make_retention_result('archive', $scope, $batch_id);

        foreach ($log_ids as $log_id) {
            $log = vms_dt_vio_get_log_row($log_id);
            $row_result = array(
                'log_id' => $log_id,
                'vendor_id' => absint(is_array($log) ? ($log['vendor_id'] ?? 0) : 0),
                'vendor_name' => is_array($log) ? (string) ($log['vendor_name'] ?? '') : '',
            );

            if (!is_array($log)) {
                $result = vms_dt_vio_add_retention_skip($result, __('Log row was not found.', 'vms-data-tools'), $row_result);
                continue;
            }

            if (vms_dt_vio_normalize_retention_state((string) ($log['retention_state'] ?? 'active')) === 'archived') {
                $result = vms_dt_vio_add_retention_skip($result, __('Log is already archived.', 'vms-data-tools'), $row_result);
                continue;
            }

            $updated = $wpdb->update(
                $table,
                array(
                    'retention_state' => 'archived',
                    'archived_at' => $now,
                    'archived_by_user_id' => $user_id,
                    'archived_reason' => $reason_text,
                    'retention_batch_id' => $batch_id,
                ),
                array('id' => $log_id),
                array('%s', '%s', '%d', '%s', '%s'),
                array('%d')
            );

            if ($updated === false) {
                return new WP_Error('vms_dt_vio_archive_update_failed', __('Unable to archive one or more invite logs.', 'vms-data-tools'));
            }

            $audit = vms_dt_vio_write_retention_audit_rows(
                array(
                    array(
                        'log' => $log,
                        'token' => vms_dt_vio_get_retention_token_row(absint($log['claim_token_id'] ?? 0)),
                    ),
                ),
                'archive',
                $scope,
                $batch_id,
                $user_id,
                $reason_code,
                $operator_note
            );

            if (is_wp_error($audit)) {
                return $audit;
            }

            $result['summary']['archived'] = absint($result['summary']['archived'] ?? 0) + 1;
            $row_result['status'] = 'archived';
            $row_result['message'] = __('Archived.', 'vms-data-tools');
            $result['results'][] = $row_result;
        }

        return $result;
    }
}

if (!function_exists('vms_dt_vio_restore_logs')) {
    /**
     * @param int[] $log_ids
     * @return array<string,mixed>|WP_Error
     */
    function vms_dt_vio_restore_logs(array $log_ids, int $user_id, string $reason_code, string $operator_note = '')
    {
        global $wpdb;

        if (!isset($wpdb) || !($wpdb instanceof wpdb)) {
            return new WP_Error('vms_dt_vio_retention_db_missing', __('Database is unavailable for restoring invite logs.', 'vms-data-tools'));
        }

        $log_ids = array_values(array_unique(array_filter(array_map('absint', $log_ids))));
        if (empty($log_ids)) {
            return new WP_Error('vms_dt_vio_restore_empty', __('Choose at least one archived log to restore.', 'vms-data-tools'));
        }

        $scope = (count($log_ids) === 1) ? 'single' : 'filtered';
        $batch_id = vms_dt_vio_generate_retention_batch_id();
        $table = vms_dt_vio_invite_log_table();
        $result = vms_dt_vio_make_retention_result('restore', $scope, $batch_id);

        foreach ($log_ids as $log_id) {
            $log = vms_dt_vio_get_log_row($log_id);
            $row_result = array(
                'log_id' => $log_id,
                'vendor_id' => absint(is_array($log) ? ($log['vendor_id'] ?? 0) : 0),
                'vendor_name' => is_array($log) ? (string) ($log['vendor_name'] ?? '') : '',
            );

            if (!is_array($log)) {
                $result = vms_dt_vio_add_retention_skip($result, __('Log row was not found.', 'vms-data-tools'), $row_result);
                continue;
            }

            if (vms_dt_vio_normalize_retention_state((string) ($log['retention_state'] ?? 'active')) !== 'archived') {
                $result = vms_dt_vio_add_retention_skip($result, __('Log is not archived yet.', 'vms-data-tools'), $row_result);
                continue;
            }

            $updated = $wpdb->update(
                $table,
                array(
                    'retention_state' => 'active',
                    'archived_at' => null,
                    'archived_by_user_id' => null,
                    'archived_reason' => null,
                    'retention_batch_id' => null,
                ),
                array('id' => $log_id),
                array('%s', '%s', '%d', '%s', '%s'),
                array('%d')
            );

            if ($updated === false) {
                return new WP_Error('vms_dt_vio_restore_update_failed', __('Unable to restore one or more archived logs.', 'vms-data-tools'));
            }

            $audit = vms_dt_vio_write_retention_audit_rows(
                array(
                    array(
                        'log' => $log,
                        'token' => vms_dt_vio_get_retention_token_row(absint($log['claim_token_id'] ?? 0)),
                    ),
                ),
                'restore',
                $scope,
                $batch_id,
                $user_id,
                $reason_code,
                $operator_note
            );

            if (is_wp_error($audit)) {
                return $audit;
            }

            $result['summary']['restored'] = absint($result['summary']['restored'] ?? 0) + 1;
            $row_result['status'] = 'restored';
            $row_result['message'] = __('Restored.', 'vms-data-tools');
            $result['results'][] = $row_result;
        }

        return $result;
    }
}

if (!function_exists('vms_dt_vio_purge_logs')) {
    /**
     * @param int[] $log_ids
     * @return array<string,mixed>|WP_Error
     */
    function vms_dt_vio_purge_logs(array $log_ids, int $user_id, string $reason_code, string $operator_note = '')
    {
        global $wpdb;

        if (!isset($wpdb) || !($wpdb instanceof wpdb)) {
            return new WP_Error('vms_dt_vio_retention_db_missing', __('Database is unavailable for purging invite logs.', 'vms-data-tools'));
        }

        $log_ids = array_values(array_unique(array_filter(array_map('absint', $log_ids))));
        if (empty($log_ids)) {
            return new WP_Error('vms_dt_vio_purge_empty', __('Choose at least one archived log to purge.', 'vms-data-tools'));
        }

        $scope = (count($log_ids) === 1) ? 'single' : 'filtered';
        $batch_id = vms_dt_vio_generate_retention_batch_id();
        $log_table = vms_dt_vio_invite_log_table();
        $token_table = vms_dt_vio_claim_tokens_table();
        $affected_vendor_ids = array();
        $result = vms_dt_vio_make_retention_result('purge', $scope, $batch_id);

        foreach ($log_ids as $log_id) {
            $log = vms_dt_vio_get_log_row($log_id);
            $row_result = array(
                'log_id' => $log_id,
                'vendor_id' => absint(is_array($log) ? ($log['vendor_id'] ?? 0) : 0),
                'vendor_name' => is_array($log) ? (string) ($log['vendor_name'] ?? '') : '',
            );

            if (!is_array($log)) {
                $result = vms_dt_vio_add_retention_skip($result, __('Log row was not found.', 'vms-data-tools'), $row_result);
                continue;
            }

            $evaluation = vms_dt_vio_evaluate_purge_eligibility($log);
            if (empty($evaluation['eligible'])) {
                $reasons = isset($evaluation['reasons']) && is_array($evaluation['reasons'])
                    ? implode(' ', array_map('strval', $evaluation['reasons']))
                    : __('Row is not eligible for purge.', 'vms-data-tools');
                $result = vms_dt_vio_add_retention_skip($result, $reasons, $row_result);
                continue;
            }

            $claim_token_id = absint($log['claim_token_id'] ?? 0);
            $token_row = is_array($evaluation['token_row'] ?? null)
                ? (array) $evaluation['token_row']
                : vms_dt_vio_get_retention_token_row($claim_token_id);

            $audit = vms_dt_vio_write_retention_audit_rows(
                array(
                    array(
                        'log' => $log,
                        'token' => $token_row,
                    ),
                ),
                'purge',
                $scope,
                $batch_id,
                $user_id,
                $reason_code,
                $operator_note
            );

            if (is_wp_error($audit)) {
                return $audit;
            }

            if ($claim_token_id > 0 && absint($evaluation['token_delete_count'] ?? 0) > 0) {
                $token_deleted = $wpdb->delete($token_table, array('id' => $claim_token_id), array('%d'));
                if ($token_deleted === false) {
                    return new WP_Error('vms_dt_vio_purge_token_delete_failed', __('Unable to delete an unused claim token during purge.', 'vms-data-tools'));
                }
                if ((int) $token_deleted > 0) {
                    $result['summary']['deleted_tokens'] = absint($result['summary']['deleted_tokens'] ?? 0) + 1;
                }
            }

            $deleted = $wpdb->delete($log_table, array('id' => $log_id), array('%d'));
            if ($deleted === false) {
                return new WP_Error('vms_dt_vio_purge_log_delete_failed', __('Unable to purge one or more archived invite logs.', 'vms-data-tools'));
            }

            if ((int) $deleted > 0) {
                $result['summary']['purged'] = absint($result['summary']['purged'] ?? 0) + 1;
                $vendor_id = absint($log['vendor_id'] ?? 0);
                if ($vendor_id > 0) {
                    $affected_vendor_ids[$vendor_id] = $vendor_id;
                }
                $row_result['status'] = 'purged';
                $row_result['message'] = __('Purged.', 'vms-data-tools');
                $result['results'][] = $row_result;
            }
        }

        foreach ($affected_vendor_ids as $vendor_id) {
            vms_dt_vio_refresh_vendor_invite_counters_from_logs((int) $vendor_id);
        }

        return $result;
    }
}

if (!function_exists('vms_dt_vio_write_retention_audit_rows')) {
    /**
     * @param array<int,array<string,mixed>> $items
     * @return int|WP_Error
     */
    function vms_dt_vio_write_retention_audit_rows(array $items, string $action_type, string $scope_type, string $batch_id, int $user_id, string $reason_code, string $operator_note = '')
    {
        global $wpdb;

        if (!isset($wpdb) || !($wpdb instanceof wpdb)) {
            return new WP_Error('vms_dt_vio_retention_audit_db_missing', __('Database is unavailable for retention audit writes.', 'vms-data-tools'));
        }

        $action_type = vms_dt_vio_normalize_retention_action($action_type);
        if ($action_type === '') {
            return new WP_Error('vms_dt_vio_retention_audit_action_invalid', __('Retention audit action is invalid.', 'vms-data-tools'));
        }

        $scope_type = vms_dt_vio_normalize_retention_scope($scope_type);
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return new WP_Error('vms_dt_vio_retention_audit_user_missing', __('A valid operator is required for retention audit logging.', 'vms-data-tools'));
        }

        $reason_code = sanitize_key($reason_code);
        if ($reason_code === '') {
            $reason_code = 'manual_cleanup';
        }

        $table = vms_dt_vio_get_retention_audit_table();
        $performed_at = vms_dt_vio_now_gmt_mysql();
        $inserted = 0;

        foreach ($items as $item) {
            $log = isset($item['log']) && is_array($item['log']) ? (array) $item['log'] : array();
            if (empty($log)) {
                continue;
            }

            $token = isset($item['token']) && is_array($item['token']) ? (array) $item['token'] : null;
            $snapshot = vms_dt_vio_build_retention_snapshot($log, $token);

            $saved = $wpdb->insert(
                $table,
                array(
                    'batch_id' => $batch_id,
                    'action_type' => $action_type,
                    'scope_type' => $scope_type,
                    'log_id' => absint($log['id'] ?? 0) ?: null,
                    'vendor_id' => absint($log['vendor_id'] ?? 0),
                    'event_type' => sanitize_key((string) ($log['event_type'] ?? 'invite_send')),
                    'log_status' => sanitize_key((string) ($log['status'] ?? 'failed')),
                    'retention_state_before' => vms_dt_vio_normalize_retention_state((string) ($log['retention_state'] ?? 'active')),
                    'sent_at' => (string) ($log['sent_at'] ?? vms_dt_vio_now_gmt_mysql()),
                    'claim_token_id' => absint($log['claim_token_id'] ?? 0) ?: null,
                    'claim_token_used_at' => is_array($token) ? ((string) ($token['used_at'] ?? '') ?: null) : null,
                    'claim_token_expires_at' => is_array($token) ? ((string) ($token['expires_at'] ?? '') ?: null) : null,
                    'performed_at' => $performed_at,
                    'performed_by_user_id' => $user_id,
                    'reason_code' => $reason_code,
                    'operator_note' => ($operator_note !== '') ? $operator_note : null,
                    'snapshot_json' => wp_json_encode($snapshot),
                ),
                array('%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s')
            );

            if (!$saved) {
                return new WP_Error('vms_dt_vio_retention_audit_insert_failed', __('Unable to write retention audit rows.', 'vms-data-tools'));
            }

            $inserted++;
        }

        return $inserted;
    }
}
