<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_dt_vio_opportunity_status_labels')) {
    /**
     * @return array<string,string>
     */
    function vms_dt_vio_opportunity_status_labels(): array
    {
        return array(
            'pending' => __('Pending', 'vms-data-tools'),
            'accepted' => __('Accepted', 'vms-data-tools'),
            'declined' => __('Declined', 'vms-data-tools'),
            'withdrawn' => __('Withdrawn', 'vms-data-tools'),
        );
    }
}

if (!function_exists('vms_dt_vio_normalize_opportunity_status')) {
    function vms_dt_vio_normalize_opportunity_status(string $status): string
    {
        $status = sanitize_key($status);
        $labels = vms_dt_vio_opportunity_status_labels();
        return isset($labels[$status]) ? $status : 'pending';
    }
}

if (!function_exists('vms_dt_vio_opportunity_status_rank')) {
    function vms_dt_vio_opportunity_status_rank(string $status): int
    {
        $status = vms_dt_vio_normalize_opportunity_status($status);
        if ($status === 'pending') {
            return 0;
        }
        if ($status === 'accepted') {
            return 1;
        }
        if ($status === 'declined') {
            return 2;
        }
        return 3;
    }
}

if (!function_exists('vms_dt_vio_get_opportunity_submission_row')) {
    /**
     * @return array<string,mixed>|null
     */
    function vms_dt_vio_get_opportunity_submission_row(int $submission_id): ?array
    {
        global $wpdb;

        $submission_id = absint($submission_id);
        if ($submission_id <= 0) {
            return null;
        }

        $table = vms_dt_vio_opportunity_submissions_table();
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $submission_id),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }
}

if (!function_exists('vms_dt_vio_enrich_opportunity_submission_row')) {
    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    function vms_dt_vio_enrich_opportunity_submission_row(array $row): array
    {
        $vendor_id = absint($row['vendor_id'] ?? 0);
        $event_plan_id = absint($row['event_plan_id'] ?? 0);
        $vendor_type = ($vendor_id > 0) ? vms_dt_vio_get_vendor_type_slug($vendor_id) : '';
        $status = vms_dt_vio_normalize_opportunity_status((string) ($row['status'] ?? 'pending'));

        $keys = vms_dt_vio_event_plan_meta_keys();
        $event_date = ($event_plan_id > 0) ? (string) get_post_meta($event_plan_id, $keys['date'], true) : '';
        $venue_id = ($event_plan_id > 0) ? absint(get_post_meta($event_plan_id, $keys['venue_id'], true)) : 0;
        $event_status = '';
        if ($event_plan_id > 0) {
            $event_status = vms_dt_has_core_function('vms_event_plan_get_status')
                ? (string) vms_dt_call_core_function('vms_event_plan_get_status', $event_plan_id, 'schedule_admin')
                : sanitize_key((string) get_post_meta($event_plan_id, $keys['status'], true));
            if (vms_dt_has_core_function('vms_event_plan_status_normalize')) {
                $event_status = (string) vms_dt_call_core_function('vms_event_plan_status_normalize', $event_status);
            } else {
                $event_status = sanitize_key($event_status);
            }
        }

        $row['id'] = absint($row['id'] ?? 0);
        $row['vendor_id'] = $vendor_id;
        $row['event_plan_id'] = $event_plan_id;
        $row['status'] = $status;
        $row['status_label'] = (string) (vms_dt_vio_opportunity_status_labels()[$status] ?? ucfirst($status));
        $row['vendor_type'] = $vendor_type;
        $row['vendor_type_label'] = '';
        if ($vendor_type !== '') {
            $term = get_term_by('slug', $vendor_type, 'vms_vendor_type');
            if ($term instanceof WP_Term) {
                $row['vendor_type_label'] = (string) $term->name;
            }
        }
        $row['vendor_name'] = ($vendor_id > 0) ? (string) get_the_title($vendor_id) : '';
        $row['event_title'] = ($event_plan_id > 0) ? (string) get_the_title($event_plan_id) : '';
        $row['event_date'] = $event_date;
        $row['event_ts'] = 0;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $event_date)) {
            $ts = strtotime($event_date . ' 12:00:00');
            if ($ts !== false) {
                $row['event_ts'] = (int) $ts;
            }
        }
        $row['venue_id'] = $venue_id;
        $row['venue_name'] = ($venue_id > 0) ? (string) get_the_title($venue_id) : '';
        $row['event_status'] = $event_status;
        $row['event_status_label'] = ($event_status !== '' && vms_dt_has_core_function('vms_event_plan_status_label'))
            ? (string) vms_dt_call_core_function('vms_event_plan_status_label', $event_status)
            : ucfirst((string) $event_status);
        $row['note'] = trim((string) ($row['note'] ?? ''));
        $row['submitted_at'] = trim((string) ($row['submitted_at'] ?? ''));
        $row['updated_at'] = trim((string) ($row['updated_at'] ?? ''));

        if ($row['vendor_name'] === '' && $vendor_id > 0) {
            $row['vendor_name'] = sprintf(
                /* translators: %d is a vendor ID. */
                __('Vendor #%d', 'vms-data-tools'),
                $vendor_id
            );
        }

        if ($row['event_title'] === '' && $event_plan_id > 0) {
            $row['event_title'] = sprintf(
                /* translators: %d is an event plan ID. */
                __('Event Plan #%d', 'vms-data-tools'),
                $event_plan_id
            );
        }

        return $row;
    }
}

if (!function_exists('vms_dt_vio_list_opportunity_submissions')) {
    /**
     * @param array<string,mixed> $filters
     * @return array<int,array<string,mixed>>
     */
    function vms_dt_vio_list_opportunity_submissions(array $filters = array()): array
    {
        global $wpdb;

        $table = vms_dt_vio_opportunity_submissions_table();
        $where = array('1=1');
        $params = array();

        $status = sanitize_key((string) ($filters['status'] ?? ''));
        if ($status !== '' && $status !== 'all') {
            $where[] = 'status = %s';
            $params[] = vms_dt_vio_normalize_opportunity_status($status);
        }

        $vendor_id = absint($filters['vendor_id'] ?? 0);
        if ($vendor_id > 0) {
            $where[] = 'vendor_id = %d';
            $params[] = $vendor_id;
        }

        $event_plan_id = absint($filters['event_plan_id'] ?? 0);
        if ($event_plan_id > 0) {
            $where[] = 'event_plan_id = %d';
            $params[] = $event_plan_id;
        }

        $limit = absint($filters['limit'] ?? 300);
        if ($limit <= 0) {
            $limit = 300;
        }
        if ($limit > 1000) {
            $limit = 1000;
        }

        $params[] = $limit;
        $prepared = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE " . implode(' AND ', $where) . " ORDER BY submitted_at ASC, id ASC LIMIT %d",
            $params
        );
        if (!is_string($prepared) || $prepared === '') {
            return array();
        }

        $rows = $wpdb->get_results($prepared, ARRAY_A);
        if (!is_array($rows) || empty($rows)) {
            return array();
        }

        $vendor_type_filter = sanitize_key((string) ($filters['vendor_type'] ?? ''));
        $date_from = trim((string) ($filters['date_from'] ?? ''));
        $date_to = trim((string) ($filters['date_to'] ?? ''));

        $items = array();
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $item = vms_dt_vio_enrich_opportunity_submission_row($row);

            if ($vendor_type_filter !== '' && (string) ($item['vendor_type'] ?? '') !== $vendor_type_filter) {
                continue;
            }

            $event_date = (string) ($item['event_date'] ?? '');
            if ($date_from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from) && $event_date !== '' && $event_date < $date_from) {
                continue;
            }
            if ($date_to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to) && $event_date !== '' && $event_date > $date_to) {
                continue;
            }

            $items[] = $item;
        }

        usort($items, static function (array $a, array $b): int {
            $a_ts = absint($a['event_ts'] ?? 0);
            $b_ts = absint($b['event_ts'] ?? 0);
            if ($a_ts !== $b_ts) {
                return $a_ts <=> $b_ts;
            }

            $a_rank = vms_dt_vio_opportunity_status_rank((string) ($a['status'] ?? 'pending'));
            $b_rank = vms_dt_vio_opportunity_status_rank((string) ($b['status'] ?? 'pending'));
            if ($a_rank !== $b_rank) {
                return $a_rank <=> $b_rank;
            }

            return strcmp((string) ($a['submitted_at'] ?? ''), (string) ($b['submitted_at'] ?? ''));
        });

        return $items;
    }
}

if (!function_exists('vms_dt_vio_group_opportunity_submissions_by_event')) {
    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    function vms_dt_vio_group_opportunity_submissions_by_event(array $rows): array
    {
        $groups = array();

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $event_plan_id = absint($row['event_plan_id'] ?? 0);
            if ($event_plan_id <= 0) {
                continue;
            }

            $vendor_type = sanitize_key((string) ($row['vendor_type'] ?? ''));
            $group_key = $event_plan_id . ':' . $vendor_type;

            if (!isset($groups[$group_key])) {
                $groups[$group_key] = array(
                    'group_key' => $group_key,
                    'event_plan_id' => $event_plan_id,
                    'event_title' => (string) ($row['event_title'] ?? ''),
                    'event_date' => (string) ($row['event_date'] ?? ''),
                    'event_ts' => absint($row['event_ts'] ?? 0),
                    'event_status' => (string) ($row['event_status'] ?? ''),
                    'event_status_label' => (string) ($row['event_status_label'] ?? ''),
                    'venue_name' => (string) ($row['venue_name'] ?? ''),
                    'vendor_type' => $vendor_type,
                    'vendor_type_label' => (string) ($row['vendor_type_label'] ?? ''),
                    'submissions' => array(),
                );
            }

            $groups[$group_key]['submissions'][] = $row;
        }

        foreach ($groups as &$group) {
            usort($group['submissions'], static function (array $a, array $b): int {
                $a_rank = vms_dt_vio_opportunity_status_rank((string) ($a['status'] ?? 'pending'));
                $b_rank = vms_dt_vio_opportunity_status_rank((string) ($b['status'] ?? 'pending'));
                if ($a_rank !== $b_rank) {
                    return $a_rank <=> $b_rank;
                }

                return strcmp((string) ($a['submitted_at'] ?? ''), (string) ($b['submitted_at'] ?? ''));
            });
        }
        unset($group);

        uasort($groups, static function (array $a, array $b): int {
            $a_ts = absint($a['event_ts'] ?? 0);
            $b_ts = absint($b['event_ts'] ?? 0);
            if ($a_ts !== $b_ts) {
                return $a_ts <=> $b_ts;
            }

            return strcmp((string) ($a['vendor_type'] ?? ''), (string) ($b['vendor_type'] ?? ''));
        });

        return array_values($groups);
    }
}

if (!function_exists('vms_dt_vio_get_vendor_submission_map')) {
    /**
     * @param int[] $event_plan_ids
     * @return array<int,array<string,mixed>>
     */
    function vms_dt_vio_get_vendor_submission_map(int $vendor_id, array $event_plan_ids = array()): array
    {
        global $wpdb;

        $vendor_id = absint($vendor_id);
        if ($vendor_id <= 0) {
            return array();
        }

        $where = array('vendor_id = %d');
        $params = array($vendor_id);
        $ids = array_values(array_unique(array_filter(array_map('absint', $event_plan_ids))));
        if (!empty($ids)) {
            $placeholders = implode(', ', array_fill(0, count($ids), '%d'));
            $where[] = "event_plan_id IN ({$placeholders})";
            foreach ($ids as $event_plan_id) {
                $params[] = $event_plan_id;
            }
        }

        $table = vms_dt_vio_opportunity_submissions_table();
        $prepared = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE " . implode(' AND ', $where) . " ORDER BY submitted_at DESC, id DESC",
            $params
        );
        if (!is_string($prepared) || $prepared === '') {
            return array();
        }

        $rows = $wpdb->get_results($prepared, ARRAY_A);
        if (!is_array($rows)) {
            return array();
        }

        $map = array();
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $event_plan_id = absint($row['event_plan_id'] ?? 0);
            if ($event_plan_id <= 0 || isset($map[$event_plan_id])) {
                continue;
            }

            $map[$event_plan_id] = vms_dt_vio_enrich_opportunity_submission_row($row);
        }

        return $map;
    }
}

if (!function_exists('vms_dt_vio_create_interest_submission')) {
    /**
     * @param array<string,mixed> $args
     * @return array<string,mixed>|WP_Error
     */
    function vms_dt_vio_create_interest_submission(int $vendor_id, int $event_plan_id, array $args = array())
    {
        global $wpdb;

        $vendor_id = absint($vendor_id);
        $event_plan_id = absint($event_plan_id);
        if ($vendor_id <= 0 || get_post_type($vendor_id) !== 'vms_vendor') {
            return new WP_Error('vms_dt_vio_interest_vendor_invalid', __('Your vendor profile could not be resolved for this opportunity.', 'vms-data-tools'));
        }
        if ($event_plan_id <= 0 || get_post_type($event_plan_id) !== 'vms_event_plan') {
            return new WP_Error('vms_dt_vio_interest_event_invalid', __('This opportunity could not be found.', 'vms-data-tools'));
        }

        $vendor_type = vms_dt_vio_get_vendor_type_slug($vendor_id);
        if ($vendor_type === '') {
            return new WP_Error('vms_dt_vio_interest_vendor_type_missing', __('Your vendor profile is missing a vendor type, so interest cannot be submitted yet.', 'vms-data-tools'));
        }

        $opportunity = vms_dt_vio_get_event_plan_opportunity_row($event_plan_id, $vendor_type, array(
            'include_primary_vendor' => false,
            'include_tentative' => false,
        ));
        if (!is_array($opportunity)) {
            return new WP_Error('vms_dt_vio_interest_closed', __('This opportunity is no longer open.', 'vms-data-tools'));
        }

        $table = vms_dt_vio_opportunity_submissions_table();
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE vendor_id = %d AND event_plan_id = %d AND status IN (%s, %s) ORDER BY id DESC LIMIT 1",
                $vendor_id,
                $event_plan_id,
                'pending',
                'accepted'
            ),
            ARRAY_A
        );

        if (is_array($existing)) {
            $existing_status = vms_dt_vio_normalize_opportunity_status((string) ($existing['status'] ?? 'pending'));
            return array(
                'submission_id' => absint($existing['id'] ?? 0),
                'status' => $existing_status,
                'duplicate' => true,
                'submission' => vms_dt_vio_enrich_opportunity_submission_row($existing),
            );
        }

        $note = isset($args['note']) ? sanitize_textarea_field((string) $args['note']) : '';
        $now = vms_dt_vio_now_gmt_mysql();
        $actor_user_id = absint($args['actor_user_id'] ?? get_current_user_id());

        $inserted = $wpdb->insert(
            $table,
            array(
                'vendor_id' => $vendor_id,
                'event_plan_id' => $event_plan_id,
                'status' => 'pending',
                'submitted_at' => $now,
                'updated_at' => $now,
                'note' => ($note !== '') ? $note : null,
                'submitted_by_user_id' => $actor_user_id ?: null,
                'updated_by_user_id' => $actor_user_id ?: null,
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s', '%d', '%d')
        );

        if (!$inserted) {
            return new WP_Error('vms_dt_vio_interest_insert_failed', __('Unable to save your interest right now. Please try again.', 'vms-data-tools'));
        }

        return array(
            'submission_id' => (int) $wpdb->insert_id,
            'status' => 'pending',
            'duplicate' => false,
            'submission' => vms_dt_vio_get_opportunity_submission_row((int) $wpdb->insert_id),
        );
    }
}

if (!function_exists('vms_dt_vio_update_interest_submission_status')) {
    /**
     * @param array<string,mixed> $args
     * @return array<string,mixed>|WP_Error
     */
    function vms_dt_vio_update_interest_submission_status(int $submission_id, string $status, array $args = array())
    {
        global $wpdb;

        $row = vms_dt_vio_get_opportunity_submission_row($submission_id);
        if (!is_array($row)) {
            return new WP_Error('vms_dt_vio_interest_missing', __('The interest submission could not be found.', 'vms-data-tools'));
        }

        $status = vms_dt_vio_normalize_opportunity_status($status);
        $note = array_key_exists('note', $args) ? sanitize_textarea_field((string) $args['note']) : trim((string) ($row['note'] ?? ''));
        $actor_user_id = absint($args['actor_user_id'] ?? get_current_user_id());
        $updated = $wpdb->update(
            vms_dt_vio_opportunity_submissions_table(),
            array(
                'status' => $status,
                'updated_at' => vms_dt_vio_now_gmt_mysql(),
                'note' => ($note !== '') ? $note : null,
                'updated_by_user_id' => $actor_user_id ?: null,
            ),
            array('id' => absint($row['id'] ?? 0)),
            array('%s', '%s', '%s', '%d'),
            array('%d')
        );

        if ($updated === false) {
            return new WP_Error('vms_dt_vio_interest_update_failed', __('Unable to update the interest submission.', 'vms-data-tools'));
        }

        $fresh = vms_dt_vio_get_opportunity_submission_row($submission_id);
        return is_array($fresh) ? vms_dt_vio_enrich_opportunity_submission_row($fresh) : array();
    }
}

if (!function_exists('vms_dt_vio_assign_interest_submission')) {
    /**
     * @param array<string,mixed> $args
     * @return array<string,mixed>|WP_Error
     */
    function vms_dt_vio_assign_interest_submission(int $submission_id, array $args = array())
    {
        global $wpdb;

        $row = vms_dt_vio_get_opportunity_submission_row($submission_id);
        if (!is_array($row)) {
            return new WP_Error('vms_dt_vio_interest_missing', __('The selected interest submission could not be found.', 'vms-data-tools'));
        }

        $row = vms_dt_vio_enrich_opportunity_submission_row($row);
        $vendor_id = absint($row['vendor_id'] ?? 0);
        $event_plan_id = absint($row['event_plan_id'] ?? 0);
        $vendor_type = sanitize_key((string) ($row['vendor_type'] ?? ''));

        if ($vendor_id <= 0 || $event_plan_id <= 0 || $vendor_type === '') {
            return new WP_Error('vms_dt_vio_interest_assign_invalid', __('The selected submission is missing required vendor or event details.', 'vms-data-tools'));
        }

        if (!vms_dt_has_core_function('vms_event_plan_set_secondary_vendors')) {
            return new WP_Error('vms_dt_vio_interest_assign_helper_missing', __('The core Event Plan assignment helper is unavailable.', 'vms-data-tools'));
        }

        $keys = vms_dt_vio_event_plan_meta_keys();
        $current_type = sanitize_key((string) get_post_meta($event_plan_id, $keys['secondary_vendor_type'], true));
        $current_ids = get_post_meta($event_plan_id, $keys['secondary_vendor_ids'], true);
        if (!is_array($current_ids)) {
            $current_ids = get_post_meta($event_plan_id, $keys['secondary_vendor_id'], false);
        }
        $current_ids = array_values(array_unique(array_filter(array_map('absint', (array) $current_ids))));

        if ($current_type !== '' && $current_type !== $vendor_type && !empty($current_ids)) {
            return new WP_Error('vms_dt_vio_interest_assign_type_conflict', __('This Event Plan already has a different secondary vendor type assigned. Review the Event Plan before assigning from the queue.', 'vms-data-tools'));
        }

        $opportunity = vms_dt_vio_get_event_plan_opportunity_row($event_plan_id, $vendor_type, array(
            'include_primary_vendor' => false,
            'include_tentative' => true,
        ));

        $already_assigned = in_array($vendor_id, $current_ids, true);
        if (!is_array($opportunity) && !$already_assigned) {
            return new WP_Error('vms_dt_vio_interest_assign_closed', __('This opportunity is no longer open for assignment.', 'vms-data-tools'));
        }

        $target_ids = $current_ids;
        if (!in_array($vendor_id, $target_ids, true)) {
            $target_ids[] = $vendor_id;
        }
        $target_ids = array_values(array_unique(array_filter(array_map('absint', $target_ids))));

        $assigned = vms_dt_call_core_function('vms_event_plan_set_secondary_vendors', $event_plan_id, $vendor_type, $target_ids);
        if (is_wp_error($assigned)) {
            return $assigned;
        }

        $accepted = vms_dt_vio_update_interest_submission_status($submission_id, 'accepted', array(
            'actor_user_id' => absint($args['actor_user_id'] ?? get_current_user_id()),
        ));
        if (is_wp_error($accepted)) {
            return $accepted;
        }

        $post_assign_ids = isset($assigned['secondary_ids']) && is_array($assigned['secondary_ids'])
            ? array_values(array_unique(array_filter(array_map('absint', (array) $assigned['secondary_ids']))))
            : $target_ids;
        $post_assign_count = count($post_assign_ids);
        $slot_limit = vms_dt_vio_extract_slot_limit_for_type($event_plan_id, absint(get_post_meta($event_plan_id, $keys['venue_id'], true)), $vendor_type);
        $has_remaining_capacity = ($slot_limit > 0) ? ($post_assign_count < $slot_limit) : false;

        $declined_count = 0;
        if (!$has_remaining_capacity) {
            $table = vms_dt_vio_opportunity_submissions_table();
            $pending_rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE event_plan_id = %d AND status = %s AND id <> %d ORDER BY submitted_at ASC, id ASC",
                    $event_plan_id,
                    'pending',
                    $submission_id
                ),
                ARRAY_A
            );

            if (is_array($pending_rows)) {
                foreach ($pending_rows as $pending_row) {
                    if (!is_array($pending_row)) {
                        continue;
                    }

                    $pending_vendor_id = absint($pending_row['vendor_id'] ?? 0);
                    if ($pending_vendor_id <= 0 || vms_dt_vio_get_vendor_type_slug($pending_vendor_id) !== $vendor_type) {
                        continue;
                    }

                    $declined = vms_dt_vio_update_interest_submission_status((int) $pending_row['id'], 'declined', array(
                        'actor_user_id' => absint($args['actor_user_id'] ?? get_current_user_id()),
                    ));
                    if (!is_wp_error($declined)) {
                        $declined_count++;
                    }
                }
            }
        }

        return array(
            'accepted_submission' => $accepted,
            'assignment' => $assigned,
            'declined_count' => $declined_count,
        );
    }
}
