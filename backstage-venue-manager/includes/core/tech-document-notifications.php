<?php
/** Event technicians use normalized staffing; delivery and history use BVM notifications. */
defined('ABSPATH') || exit;

function bvmgr_tech_doc_types(): array
{
    return array(
        'stage_plot' => __('Stage plot', 'backstage-venue-manager'),
        'input_list' => __('Input list', 'backstage-venue-manager'),
        'tech_rider' => __('Technical / production rider', 'backstage-venue-manager'),
    );
}

function bvmgr_tech_doc_role_is_technical(array $role): bool
{
    $explicit = get_term_meta((int) ($role['role_id'] ?? 0), '_bvmgr_receives_tech_docs', true);
    if ($explicit !== '') {
        return $explicit === '1';
    }
    $names = array('sound', 'audio', 'sound-tech', 'sound-technician', 'audio-tech', 'audio-technician',
        'production-tech', 'production-technician', 'foh', 'foh-engineer', 'front-of-house-engineer',
        'monitor-engineer', 'monitors-engineer', 'technical-lead', 'sound-engineer', 'audio-engineer');
    return in_array(sanitize_title((string) ($role['slug'] ?? '')), $names, true)
        || in_array(sanitize_title((string) ($role['name'] ?? '')), $names, true);
}

function bvmgr_tech_doc_vendor_ids(int $plan_id): array
{
    $bundle = bvmgr_calendar_plan_vendor_ids($plan_id);
    $ids = array_merge(array($bundle['band_id'] ?? 0), (array) ($bundle['secondary_ids'] ?? array()), (array) ($bundle['lineup_ids'] ?? array()));
    $ids = array_values(array_unique(array_filter(array_map('absint', $ids))));
    sort($ids, SORT_NUMERIC);
    return $ids;
}

function bvmgr_tech_doc_plan_is_current(int $plan_id): bool
{
    return get_post_type($plan_id) === 'vms_event_plan'
        && !in_array(get_post_status($plan_id), array('trash', 'auto-draft'), true)
        && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) get_post_meta($plan_id, '_vms_event_date', true))
        && get_post_meta($plan_id, '_vms_event_date', true) >= wp_date('Y-m-d', time(), wp_timezone())
        && in_array(bvmgr_event_plan_get_status($plan_id, 'dashboard'), bvmgr_staff_portal_visible_event_statuses(), true);
}

/** A shared artist document applies to every current, portal-visible linked show. */
function bvmgr_tech_doc_event_ids(int $vendor_id): array
{
    $found = array();
    $page = 1;
    do {
        $ids = get_posts(array('post_type' => 'vms_event_plan', 'post_status' => array('publish', 'private', 'draft', 'pending', 'future'),
            'fields' => 'ids', 'posts_per_page' => 200, 'paged' => $page++, 'orderby' => 'ID', 'order' => 'ASC',
            'meta_query' => array(array('key' => '_vms_event_date', 'value' => wp_date('Y-m-d', time(), wp_timezone()), 'compare' => '>=', 'type' => 'DATE'))));
        foreach ($ids as $id) {
            if (bvmgr_tech_doc_plan_is_current((int) $id) && in_array($vendor_id, bvmgr_tech_doc_vendor_ids((int) $id), true)) {
                $found[] = (int) $id;
            }
        }
    } while (count($ids) === 200);
    return $found;
}

/** This adapter requires the separately qualified non-public storage authority. */
function bvmgr_tech_doc_storage_ready(): bool
{
    return function_exists('bvmgr_private_storage_config') && function_exists('bvmgr_private_storage_safe_file')
        && !is_wp_error(bvmgr_private_storage_config());
}

/** Pure document read: only safe display metadata leaves this function. */
function bvmgr_tech_doc_snapshot(int $vendor_id, string $key): array
{
    if (!isset(bvmgr_tech_doc_types()[$key])) {
        return array('key' => $key, 'error' => 'non_technical_document');
    }
    if (!bvmgr_tech_doc_storage_ready()) {
        return array('key' => $key, 'error' => 'private_storage_not_ready');
    }
    $payload = bvmgr_vendor_portal_tech_doc_payload($vendor_id, $key);
    if (is_wp_error($payload)) {
        return array('key' => $key, 'error' => 'document_unavailable');
    }
    $path = (string) ($payload['path'] ?? '');
    if ($path === '' || !is_file($path) || !is_readable($path) || !bvmgr_private_storage_safe_file($path)) {
        return array('key' => $key, 'error' => 'document_unavailable');
    }
    $hash = hash_file('sha256', $path);
    if (!is_string($hash)) {
        return array('key' => $key, 'error' => 'document_unavailable');
    }
    return array('key' => $key, 'hash' => $hash, 'file_id' => (int) ($payload['file_id'] ?? 0),
        'name' => sanitize_file_name(basename((string) ($payload['filename'] ?? $key))), 'type' => bvmgr_tech_doc_types()[$key]);
}

/** Preserve contact precedence without inheriting the cancellation resolver's first-row user fallback. */
function bvmgr_tech_doc_staff_identity(int $staff_id): array
{
    $row = array('staff_id' => $staff_id, 'user_id' => 0, 'label' => get_the_title($staff_id), 'email' => '', 'email_source' => '', 'error' => '');
    if (get_post_type($staff_id) !== 'vms_staff' || in_array(get_post_status($staff_id), array('trash', 'auto-draft'), true)) {
        $row['error'] = 'invalid_staff';
        return $row;
    }
    $user_id = absint(get_post_meta($staff_id, '_vms_linked_user_id', true));
    $user = $user_id ? get_userdata($user_id) : false;
    if (!$user) {
        $ids = get_users(array('meta_key' => '_vms_staff_id', 'meta_value' => (string) $staff_id, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC'));
        if (count($ids) > 1) {
            $row['error'] = 'ambiguous_staff_user';
            return $row;
        }
        $user = count($ids) === 1 ? get_userdata((int) $ids[0]) : false;
    }
    $row['user_id'] = $user ? (int) $user->ID : 0;
    $candidates = array('linked_user' => $user ? $user->user_email : '', 'staff_contact_email' => get_post_meta($staff_id, '_vms_contact_email', true),
        'staff_primary_email' => get_post_meta($staff_id, '_vms_vendor_primary_email', true), 'staff_legacy_email' => get_post_meta($staff_id, '_vms_vendor_email', true));
    foreach ($candidates as $source => $value) {
        $email = sanitize_email((string) $value);
        if (is_email($email)) {
            $row['email'] = strtolower($email);
            $row['email_source'] = $source;
            break;
        }
    }
    if ($row['email'] === '') {
        $row['error'] = 'missing_email';
    }
    return $row;
}

function bvmgr_tech_doc_access_url(int $plan_id, int $vendor_id): string
{
    return admin_url('admin-post.php?action=bvmgr_tech_documents&plan_id=' . $plan_id . '&vendor_id=' . $vendor_id);
}

/** Reuse the actual download authorization rule, under the intended user's identity. */
function bvmgr_tech_doc_recipient_can_access(int $user_id, int $plan_id, int $vendor_id): bool
{
    if ($user_id <= 0 || !in_array($vendor_id, bvmgr_tech_doc_vendor_ids($plan_id), true)) {
        return false;
    }
    $actor = get_current_user_id();
    try {
        wp_set_current_user($user_id);
        return bvmgr_vendor_portal_user_can_download_tech_doc($vendor_id, 'stage_plot', $plan_id);
    } finally {
        wp_set_current_user($actor);
    }
}

function bvmgr_tech_doc_recipients(int $plan_id, int $vendor_id): array
{
    $people = array();
    foreach (bvmgr_staffing_get_event_slots($plan_id) as $slot) {
        if (($slot['status'] ?? '') !== 'active' || !bvmgr_tech_doc_role_is_technical((array) ($slot['role_meta'] ?? array()))) {
            continue;
        }
        foreach ((array) ($slot['assignments'] ?? array()) as $assignment) {
            if (($assignment['status'] ?? '') !== 'confirmed') {
                continue;
            }
            $staff_id = absint($assignment['staff_id'] ?? 0);
            if (!isset($people[$staff_id])) {
                $people[$staff_id] = bvmgr_tech_doc_staff_identity($staff_id);
                $people[$staff_id]['assignments'] = array();
            }
            $people[$staff_id]['assignments'][] = array('assignment_id' => (int) $assignment['assignment_id'], 'slot_id' => (int) $slot['slot_id'],
                'role_id' => (int) $slot['role_id'], 'role' => (string) $slot['role_name'], 'status' => 'confirmed');
        }
    }
    ksort($people, SORT_NUMERIC);
    $result = array();
    foreach ($people as $person) {
        if ($person['error'] === '' && !bvmgr_tech_doc_recipient_can_access($person['user_id'], $plan_id, $vendor_id)) {
            $person['error'] = 'no_authorized_access';
        }
        $key = $person['error'] === '' ? $person['email'] : 'staff:' . $person['staff_id'];
        if (!isset($result[$key])) {
            $person['staff_ids'] = array($person['staff_id']);
            $person['reason'] = 'confirmed_assignment_in_active_technical_role';
            $result[$key] = $person;
        } else {
            $result[$key]['staff_ids'][] = $person['staff_id'];
            $result[$key]['assignments'] = array_merge($result[$key]['assignments'], $person['assignments']);
        }
    }
    ksort($result, SORT_STRING);
    return array_values($result);
}

function bvmgr_tech_doc_logs(int $plan_id): array
{
    global $wpdb;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Current event history and deduplication read the existing notification repository.
    $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM %i WHERE source = %s AND event_key = %s ORDER BY id DESC',
        bvmgr_notify_log_table_name(), 'vms_tech_docs', 'tech_documents_plan_' . $plan_id), ARRAY_A);
    return is_array($rows) ? $rows : array();
}

function bvmgr_tech_doc_log(int $plan_id, int $vendor_id, array $docs, array $person, string $status, string $reason, string $attempt = '', string $trigger = 'upload'): bool
{
    return bvmgr_notify_insert_log(array('source' => 'vms_tech_docs', 'event_key' => 'tech_documents_plan_' . $plan_id,
        'recipient_user_id' => $person['user_id'] ?? 0, 'recipient_address' => $person['email'] ?? '', 'channel' => 'email',
        'template_key' => 'tech_docs.technician_alert', 'provider' => 'core_email', 'status' => $status, 'error_message' => $reason,
        'payload' => array('plan_id' => $plan_id, 'vendor_id' => $vendor_id, 'documents' => array_values($docs), 'recipient' => $person,
            'actor_user_id' => get_current_user_id(), 'attempt_id' => $attempt, 'trigger' => $trigger,
            'dedupe_identity' => hash('sha256', wp_json_encode(array($plan_id, $vendor_id, $person['email'] ?? '', array_column($docs, 'hash', 'key')))),
            'retry' => $status === 'failed' ? 'manual_current_recipients' : ($status === 'queued' ? 'await_terminal_result' : 'none'))));
}

/** Existing history is the dedupe authority. An unclosed intent must never be resent automatically. */
function bvmgr_tech_doc_delivery_state(array $logs, int $vendor_id, string $email, array $doc): string
{
    $terminal = array();
    foreach ($logs as $log) {
        $p = json_decode((string) $log['payload_json'], true);
        if ((int) ($p['vendor_id'] ?? 0) !== $vendor_id || strtolower((string) $log['recipient_address']) !== strtolower($email)) {
            continue;
        }
        $attempt = (string) ($p['attempt_id'] ?? '');
        if (in_array($log['status'], array('sent', 'failed'), true) && $attempt !== '') {
            $terminal[$attempt] = true;
        }
        foreach ((array) ($p['documents'] ?? array()) as $old) {
            if (($old['key'] ?? '') !== $doc['key'] || ($old['hash'] ?? '') !== ($doc['hash'] ?? '')) {
                continue;
            }
            if ($log['status'] === 'sent') {
                return 'sent';
            }
            if ($log['status'] === 'queued' && !isset($terminal[$attempt])) {
                return 'uncertain';
            }
            if ($log['status'] === 'failed' && strtotime($log['created_at'] . ' UTC') > time() - 60) {
                return 'cooldown';
            }
        }
    }
    return 'unsent';
}

/** Called only by a successful upload or an explicit protected operator POST. */
function bvmgr_tech_doc_dispatch(int $plan_id, int $vendor_id, array $changes, string $trigger = 'upload'): void
{
    global $wpdb;
    if (!bvmgr_tech_doc_plan_is_current($plan_id) || !in_array($vendor_id, bvmgr_tech_doc_vendor_ids($plan_id), true)) {
        bvmgr_tech_doc_log($plan_id, $vendor_id, array(), array(), 'failed', 'event_or_vendor_not_current', '', $trigger);
        return;
    }
    $lock = 'bvm_tech_' . hash('sha256', $wpdb->dbname . ':' . $wpdb->prefix . ':' . $plan_id . ':' . $vendor_id);
    $lock = substr($lock, 0, 64);
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Connection-scoped delivery lock prevents concurrent duplicate sends without schema or option writes.
    if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $lock)) !== 1) {
        bvmgr_tech_doc_log($plan_id, $vendor_id, array(), array(), 'failed', 'delivery_busy_retry_manually', '', $trigger);
        return;
    }
    try {
        $docs = array();
        foreach ($changes as $key => $change) {
            if (!isset(bvmgr_tech_doc_types()[$key])) {
                continue;
            }
            $doc = bvmgr_tech_doc_snapshot($vendor_id, $key);
            $doc['change'] = in_array($change, array('new', 'updated'), true) ? $change : 'current';
            if (!empty($doc['error'])) {
                bvmgr_tech_doc_log($plan_id, $vendor_id, array($doc), array(), 'failed', $doc['error'], '', $trigger);
                continue;
            }
            $docs[$key] = $doc;
        }
        if (!$docs) {
            return;
        }
        ksort($docs);
        $people = bvmgr_tech_doc_recipients($plan_id, $vendor_id);
        if (!$people) {
            bvmgr_tech_doc_log($plan_id, $vendor_id, $docs, array(), 'failed', 'no_confirmed_technician', '', $trigger);
            return;
        }
        $logs = bvmgr_tech_doc_logs($plan_id);
        foreach ($people as $person) {
            if ($person['error'] !== '') {
                bvmgr_tech_doc_log($plan_id, $vendor_id, $docs, $person, 'failed', $person['error'], '', $trigger);
                continue;
            }
            $pending = array();
            foreach ($docs as $key => $doc) {
                $state = bvmgr_tech_doc_delivery_state($logs, $vendor_id, $person['email'], $doc);
                if ($state === 'unsent') {
                    $pending[$key] = $doc;
                } else {
                    bvmgr_tech_doc_log($plan_id, $vendor_id, array($doc), $person, 'skipped', 'duplicate_suppressed_' . $state, '', $trigger);
                }
            }
            if (!$pending) {
                continue;
            }
            $url = bvmgr_tech_doc_access_url($plan_id, $vendor_id);
            if (!in_array(wp_parse_url($url, PHP_URL_SCHEME), array('http', 'https'), true) || wp_parse_url($url, PHP_URL_HOST) !== wp_parse_url(admin_url(), PHP_URL_HOST)) {
                bvmgr_tech_doc_log($plan_id, $vendor_id, $pending, $person, 'failed', 'invalid_access_route', '', $trigger);
                continue;
            }
            $attempt = wp_generate_uuid4();
            if (!bvmgr_tech_doc_log($plan_id, $vendor_id, $pending, $person, 'queued', '', $attempt, $trigger)) {
                continue; // A document remains saved even when the audit service cannot accept an intent.
            }
            $lines = array(__('Technical documents are ready for your assigned event.', 'backstage-venue-manager'),
                get_the_title($plan_id) . ' (#' . $plan_id . ')',
                get_post_meta($plan_id, '_vms_event_date', true) . ' ' . get_post_meta($plan_id, '_vms_start_time', true) . ' (' . wp_timezone_string() . ')',
                __('Artist / vendor: ', 'backstage-venue-manager') . get_the_title($vendor_id), '');
            foreach ($pending as $doc) {
                $lines[] = $doc['type'] . ': ' . $doc['name'] . ' (' . $doc['change'] . ')';
            }
            $lines[] = '';
            $lines[] = __('Open securely after signing in to your BVM account:', 'backstage-venue-manager');
            $lines[] = $url;
            try {
                $result = bvmgr_notify_provider_core_email_send(array('to' => $person['email'],
                    'subject' => __('Technical documents: ', 'backstage-venue-manager') . get_the_title($plan_id), 'body_text' => implode("\n", $lines)));
            } catch (Throwable $error) {
                bvmgr_tech_doc_log($plan_id, $vendor_id, $pending, $person, 'skipped', 'Email provider outcome unknown; investigate before retry.', $attempt, $trigger);
                continue;
            }
            bvmgr_tech_doc_log($plan_id, $vendor_id, $pending, $person, !empty($result['success']) ? 'sent' : 'failed',
                (string) ($result['error_message'] ?? ''), $attempt, $trigger);
        }
    } finally {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Release only this connection's delivery lock.
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
    }
}

function bvmgr_tech_doc_uploaded(int $vendor_id, array $changes): void
{
    if (!$changes) {
        return;
    }
    $plans = bvmgr_tech_doc_event_ids($vendor_id);
    if (!$plans) {
        bvmgr_tech_doc_log(0, $vendor_id, array(), array(), 'failed', 'no_current_linked_event');
    }
    foreach ($plans as $plan_id) {
        bvmgr_tech_doc_dispatch($plan_id, $vendor_id, $changes);
    }
}
