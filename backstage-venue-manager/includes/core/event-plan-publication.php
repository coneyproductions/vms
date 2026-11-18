<?php
/** Durable, per-plan publication outbox. WP-Cron is a wake-up, not the job record. */
defined('ABSPATH') || exit;

function bvmgr_ep_publication_key(int $id): string { return 'vms_ep_publish_pending_' . absint($id); }
function bvmgr_ep_publication_read(int $id): array {
    global $wpdb;
    $raw = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s", bvmgr_ep_publication_key($id)));
    if ($wpdb->last_error !== '') { throw new RuntimeException('Publication outbox cannot be read.'); }
    if ($raw === null) { return array(); }
    $job = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($job) || empty($job['generation']) || (int) ($job['plan_id'] ?? 0) !== $id) { throw new RuntimeException('Publication outbox is malformed; administrator review is required for plan ' . $id); }
    return $job;
}
function bvmgr_ep_publication_context(int $id): string {
    return hash('sha256', wp_json_encode(array_map(static fn($key) => (string) get_post_meta($id, $key, true), array('_vms_tec_event_id', '_vms_event_date', '_vms_start_time', '_vms_end_time'))));
}
/** Connection-owned lock, also shared by editor writes that can cancel the job. */
function bvmgr_ep_publication_lock(int $id): bool {
    global $wpdb;
    if (isset($GLOBALS['bvmgr_ep_publication_locks'][$id])) { bvmgr_ep_publication_assert_lock($id); ++$GLOBALS['bvmgr_ep_publication_locks'][$id]['depth']; return true; }
    $name = 'bvm_pub_' . md5($wpdb->prefix . ':' . $id);
    $row = $wpdb->get_row($wpdb->prepare('SELECT CONNECTION_ID() AS owner, GET_LOCK(%s,0) AS acquired', $name), ARRAY_A);
    if (!is_array($row) || (string) $row['acquired'] !== '1') { return false; }
    $GLOBALS['bvmgr_ep_publication_locks'][$id] = array('name' => $name, 'owner_id' => (int) $row['owner'], 'depth' => 1);
    return true;
}
function bvmgr_ep_publication_assert_lock(int $id): void {
    global $wpdb;
    $lock = $GLOBALS['bvmgr_ep_publication_locks'][$id] ?? null;
    $owned = false;
    try {
        if ($lock && $wpdb->dbh instanceof mysqli && mysqli_thread_id($wpdb->dbh) === $lock['owner_id']) {
            $r = mysqli_query($wpdb->dbh, $wpdb->prepare('SELECT IS_USED_LOCK(%s) = CONNECTION_ID()', $lock['name']));
            if ($r instanceof mysqli_result) { $owned = (string) (mysqli_fetch_row($r)[0] ?? '') === '1'; mysqli_free_result($r); }
        }
    } catch (Throwable $error) { $owned = false; }
    if (!$owned) { $GLOBALS['bvmgr_ep_publication_poisoned'] = true; throw new RuntimeException('Publication ownership was lost. This request stopped; reload saved state before retrying.'); }
}
// Fence every write under a publication/editor mutex, including a wpdb reconnect/retry.
// Reuse the reviewed R23 SQL rewriter unchanged; its own ticket lock remains independent.
add_filter('query', static function($sql) {
    if (!empty($GLOBALS['bvmgr_ep_publication_poisoned'])) {
        $words = bvmgr_ticketing_v2_sql_words($sql);
        return in_array($words[0]['word'] ?? '', array('SELECT','SHOW','DESCRIBE','EXPLAIN'), true) ? $sql : 'SELECT 0 WHERE 0=1';
    }
    if (empty($GLOBALS['bvmgr_ep_publication_locks']) || !function_exists('bvmgr_ticketing_v2_fence_query')) { return $sql; }
    $original = $GLOBALS['bvmgr_ticketing_commit_fence'] ?? null;
    foreach ($GLOBALS['bvmgr_ep_publication_locks'] as $id => $lock) {
        if ($original && $original['name'] === $lock['name']) { continue; } // Existing R23 filter applies this exact predicate.
        try {
            $GLOBALS['bvmgr_ticketing_commit_fence'] = array('name' => $lock['name'], 'owner_id' => $lock['owner_id'], 'lost' => false);
            $sql = bvmgr_ticketing_v2_fence_query($sql);
        } catch (Throwable $error) {
            $GLOBALS['bvmgr_ep_publication_poisoned'] = true;
            throw $error;
        } finally {
            if ($original === null) { unset($GLOBALS['bvmgr_ticketing_commit_fence']); }
            else { $GLOBALS['bvmgr_ticketing_commit_fence'] = $original; }
        }
    }
    return $sql;
}, PHP_INT_MAX - 1);
function bvmgr_ep_publication_unlock(int $id): void {
    global $wpdb;
    $lock = $GLOBALS['bvmgr_ep_publication_locks'][$id] ?? null;
    if (!$lock || --$GLOBALS['bvmgr_ep_publication_locks'][$id]['depth'] > 0) { return; }
    try { bvmgr_ep_publication_assert_lock($id); } catch (Throwable $error) { $GLOBALS['bvmgr_ep_publication_poisoned'] = true; }
    // Never reconnect to release a different process's lock.
    try { if ($wpdb->dbh instanceof mysqli && mysqli_thread_id($wpdb->dbh) === $lock['owner_id']) { $r = mysqli_query($wpdb->dbh, $wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock['name'])); if ($r instanceof mysqli_result) { mysqli_free_result($r); } } } catch (Throwable $error) { /* Connection loss releases its own lock. */ }
    unset($GLOBALS['bvmgr_ep_publication_locks'][$id]);
}
function bvmgr_ep_publication_store(int $id, array $job): void {
    global $wpdb;
    bvmgr_ep_publication_assert_lock($id);
    $job['updated_at'] = time();
    $encoded = wp_json_encode($job);
    update_option(bvmgr_ep_publication_key($id), $encoded, false);
    bvmgr_ep_publication_assert_lock($id);
    if (bvmgr_ep_publication_read($id) !== $job) { throw new RuntimeException('Publication outbox write was not persisted.'); }
    update_post_meta($id, '_vms_calendar_publish_job', $job);
    update_post_meta($id, '_vms_calendar_publish_queue_state', $job['state']);
    if (!empty($job['error'])) { update_post_meta($id, '_vms_calendar_publish_last_error', $job['error']); }
    else { delete_post_meta($id, '_vms_calendar_publish_last_error'); }
    bvmgr_ep_publication_assert_lock($id);
    wp_cache_delete($id, 'post_meta');
    if (get_post_meta($id, '_vms_calendar_publish_queue_state', true) !== $job['state'] || (string) get_post_meta($id, '_vms_calendar_publish_last_error', true) !== (string) ($job['error'] ?? '')) { throw new RuntimeException('Publication status/diagnostic was not persisted.'); }
    if (!in_array($job['state'], array('queued', 'running'), true)) {
        // Receipt first, then remove the active outbox. A crash in between is harmless.
        wp_cache_delete($id, 'post_meta');
        if (get_post_meta($id, '_vms_calendar_publish_job', true) !== $job) { throw new RuntimeException('Publication receipt was not persisted.'); }
        delete_option(bvmgr_ep_publication_key($id));
        bvmgr_ep_publication_assert_lock($id);
        if (bvmgr_ep_publication_read($id)) { throw new RuntimeException('Terminal publication outbox could not be removed; the minute worker will reconcile its receipt.'); }
    }
    $GLOBALS['bvmgr_ep_publication_outcomes'][$id] = array('generation' => $job['generation'], 'state' => $job['state'], 'event_id' => $job['event_id'], 'error' => $job['error'] ?? '');
}
function bvmgr_ep_publication_schedule_job(int $id, array $job): bool {
    $args = array($id, $job['generation']);
    if (wp_next_scheduled('vms_event_plan_deferred_calendar_publish', $args)) { return true; }
    $result = wp_schedule_single_event(max(time(), (int) $job['due_at']), 'vms_event_plan_deferred_calendar_publish', $args, true);
    return !is_wp_error($result) && $result === true && (bool) wp_next_scheduled('vms_event_plan_deferred_calendar_publish', $args);
}
function bvmgr_event_plan_schedule_deferred_calendar_publish(int $post_id, string $reason = 'publish_now'): bool {
    $id = absint($post_id);
    if (!$id || get_post_type($id) !== 'vms_event_plan' || !bvmgr_ep_publication_lock($id)) { return false; }
    try {
        wp_cache_delete($id, 'post_meta'); clean_post_cache($id);
        $prior = (array) get_post_meta($id, '_vms_calendar_publish_job', true);
        if (!empty($prior['requires_reconciliation']) && !(int) get_post_meta($id, '_vms_tec_event_id', true)) { return false; }
        $job = bvmgr_ep_publication_read($id);
        if ($job && in_array($job['state'], array('queued', 'running'), true) && $job['context'] === bvmgr_ep_publication_context($id)) {
            // A pending transient must never suppress replacement of a missing cron entry.
            if ($job['state'] === 'running') {
                if ((int) $job['due_at'] > time()) { return false; }
                $job['state'] = 'queued'; $job['error'] = 'Interrupted publication is queued for automatic dispatch.'; bvmgr_ep_publication_store($id, $job);
            }
            if (bvmgr_ep_publication_schedule_job($id, $job)) { return true; }
        } else {
            if ($job) { wp_clear_scheduled_hook('vms_event_plan_deferred_calendar_publish', array($id, $job['generation'])); }
            $job = array('version' => 1, 'plan_id' => $id, 'event_id' => (int) get_post_meta($id, '_vms_tec_event_id', true), 'generation' => wp_generate_uuid4(), 'context' => bvmgr_ep_publication_context($id), 'actor_id' => bvmgr_event_plan_capture_actor_user_id($id, get_current_user_id(), 'deferred_calendar_publish_schedule'), 'reason' => sanitize_key($reason), 'created_at' => time(), 'due_at' => time() + 180, 'state' => 'queued', 'attempts' => 0, 'error' => '');
            bvmgr_ep_publication_store($id, $job);
            $GLOBALS['bvmgr_ep_publication_producing'][$id] = $job['generation'];
            update_post_meta($id, '_vms_calendar_publish_queued_at', $job['created_at']);
            update_post_meta($id, '_vms_calendar_publish_queue_reason', $job['reason']);
            if (bvmgr_ep_publication_schedule_job($id, $job)) { return true; }
        }
        $job['state'] = 'failed';
        $job['error'] = 'Calendar publication could not be scheduled. Reload and retry publication; no calendar publication was reported complete.';
        bvmgr_ep_publication_store($id, $job);
        return false;
    } finally { bvmgr_ep_publication_unlock($id); }
}
function bvmgr_event_plan_run_deferred_calendar_publish(int $post_id, string $generation = ''): void {
    $id = absint($post_id);
    if (!$id || !bvmgr_ep_publication_lock($id)) { return; }
    $fenced = false;
    try {
        wp_cache_delete($id, 'post_meta'); clean_post_cache($id);
        if ($generation === '') { bvmgr_ep_publication_adopt_legacy($id); return; }
        $job = bvmgr_ep_publication_read($id);
        // Old deliveries are never authority to publish a newer/cancelled generation.
        if (!$job || $generation !== ($job['generation'] ?? '') || !in_array($job['state'], array('queued', 'running'), true) || (int) $job['due_at'] > time()) { return; }
        if (!empty($GLOBALS['bvmgr_ticketing_commit_fence']) || !function_exists('bvmgr_ticketing_v2_assert_commit_lock')) { throw new RuntimeException('Publication ownership fence is unavailable; retry after the active Commit completes.'); }
        $lock = $GLOBALS['bvmgr_ep_publication_locks'][$id];
        $GLOBALS['bvmgr_ticketing_commit_fence'] = array('name' => $lock['name'], 'owner_id' => $lock['owner_id'], 'lost' => false); $fenced = true;
        $GLOBALS['bvmgr_ep_publication_active'] = $id;
        bvmgr_ticketing_v2_assert_commit_lock();
        $post = get_post($id);
        if (!$post || $post->post_type !== 'vms_event_plan' || $post->post_status !== 'publish' || get_post_meta($id, '_vms_event_plan_status', true) !== 'published' || $job['context'] !== bvmgr_ep_publication_context($id)) {
            $job['state'] = 'cancelled'; $job['error'] = 'Publication cancelled because the saved plan status, occurrence or linked event changed. Review the saved plan and Publish again.'; bvmgr_ep_publication_store($id, $job); return;
        }
        if ((int) $job['attempts'] > 0 && !(int) $job['event_id']) { $job['requires_reconciliation'] = true; throw new RuntimeException('Interrupted calendar creation has no persisted canonical link. An administrator must reconcile any partially created event and link its canonical ID before retrying.'); }
        if ((int) $job['attempts'] >= 3) { throw new RuntimeException('Publication was interrupted three times. Review the linked event and use Retry calendar publication.'); }
        if (!user_can((int) $job['actor_id'], 'edit_post', $id)) { throw new RuntimeException('The publication operator no longer has permission. An authorized operator must retry publication.'); }
        $errors = bvmgr_validate_event_plan($id);
        if ($errors) { throw new RuntimeException('Publication readiness failed: ' . implode(' ', array_map('sanitize_text_field', $errors))); }
        $job['state'] = 'running'; ++$job['attempts']; $job['started_at'] = time(); $job['due_at'] = time() + 300;
        bvmgr_ep_publication_store($id, $job);
        update_post_meta($id, '_vms_calendar_publish_started_at', $job['started_at']);
        if (!bvmgr_publish_event_to_calendar($id, $post)) { throw new RuntimeException('Calendar publication failed. Review the linked calendar event before retrying.'); }
        bvmgr_ticketing_v2_assert_commit_lock();
        clean_post_cache($id);
        $event = (int) get_post_meta($id, '_vms_tec_event_id', true);
        clean_post_cache($event);
        if (!$event || get_post_status($event) !== 'publish' || !get_permalink($event)) { throw new RuntimeException('The calendar event is not publicly published. Review the saved calendar state and retry publication.'); }
        $job['state'] = 'complete'; $job['completed_at'] = time(); $job['event_id'] = $event; $job['error'] = '';
        bvmgr_ep_publication_store($id, $job);
        update_post_meta($id, '_vms_calendar_publish_completed_at', $job['completed_at']);
    } catch (Throwable $error) {
        // A poisoned/reconnected process must never checkpoint its stale state.
        if ($fenced) { bvmgr_ticketing_v2_assert_commit_lock(); }
        if (!empty($job)) { if ((int) ($job['attempts'] ?? 0) > 0 && !(int) ($job['event_id'] ?? 0) && !(int) get_post_meta($id, '_vms_tec_event_id', true)) { $job['requires_reconciliation'] = true; } $job['state'] = 'failed'; $job['error'] = substr(sanitize_text_field($error->getMessage()), 0, 1000); bvmgr_ep_publication_store($id, $job); }
    } finally {
        unset($GLOBALS['bvmgr_ep_publication_active']);
        if ($fenced && empty($GLOBALS['bvmgr_ticketing_commit_fence']['lost'])) { unset($GLOBALS['bvmgr_ticketing_commit_fence']); }
        bvmgr_ep_publication_unlock($id);
    }
}
/** Adopt only an unreceipted R23 queued intent with one unambiguous linked event. */
function bvmgr_ep_publication_adopt_legacy(int $only_id = 0): void {
    global $wpdb;
    $sql = "SELECT DISTINCT q.post_id FROM {$wpdb->postmeta} q JOIN {$wpdb->posts} p ON p.ID=q.post_id LEFT JOIN {$wpdb->postmeta} j ON j.post_id=q.post_id AND j.meta_key='_vms_calendar_publish_job' WHERE p.post_type='vms_event_plan' AND q.meta_key='_vms_calendar_publish_queue_state' AND q.meta_value IN ('queued','running') AND j.meta_id IS NULL";
    if ($only_id) { $sql .= $wpdb->prepare(' AND q.post_id=%d', $only_id); }
    $ids = $wpdb->get_col($sql . ' ORDER BY q.post_id LIMIT 51');
    if ($wpdb->last_error !== '' || count($ids) > 50) { throw new RuntimeException('Legacy publication inventory is unreadable or exceeds its bound.'); }
    foreach ($ids as $value) {
        $id = (int) $value;
        if (!bvmgr_ep_publication_lock($id)) { continue; }
        try {
            wp_cache_delete($id, 'post_meta'); clean_post_cache($id);
            if (bvmgr_ep_publication_read($id) || get_post_meta($id, '_vms_calendar_publish_job', true)) { continue; }
            $event = (int) get_post_meta($id, '_vms_tec_event_id', true);
            $owners = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT m.post_id FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID=m.post_id WHERE p.post_type='vms_event_plan' AND m.meta_key='_vms_tec_event_id' AND m.meta_value=%s", (string) $event));
            if ($wpdb->last_error !== '') { throw new RuntimeException('Legacy calendar ownership cannot be read.'); }
            $actor = bvmgr_event_plan_get_captured_actor_user_id($id);
            $job = array('version' => 1, 'plan_id' => $id, 'event_id' => $event, 'generation' => wp_generate_uuid4(), 'context' => bvmgr_ep_publication_context($id), 'actor_id' => $actor, 'reason' => 'legacy_recovery', 'created_at' => (int) get_post_meta($id, '_vms_calendar_publish_queued_at', true), 'due_at' => max(time(), (int) get_post_meta($id, '_vms_calendar_publish_queued_at', true) + 180), 'state' => 'queued', 'attempts' => 0, 'error' => '');
            $reverse = (int) get_post_meta($event, '_vms_event_plan_id', true);
            if (get_post_status($id) !== 'publish' || get_post_meta($id, '_vms_event_plan_status', true) !== 'published') { $job['state'] = 'cancelled'; $job['error'] = 'Legacy publication cancelled because the plan is no longer Published. Review the saved plan before publishing.'; }
            elseif (!$event) { $job['state'] = 'failed'; $job['requires_reconciliation'] = true; $job['error'] = 'Legacy publication has no linked calendar event. Use the supported Preview or Re-sync to Calendar workflow to establish its linked event, then retry publication.'; }
            elseif (get_post_type($event) !== 'tribe_events' || get_post_status($event) === 'trash' || count($owners) !== 1 || (int) $owners[0] !== $id || ($reverse && $reverse !== $id)) { $job['state'] = 'failed'; $job['error'] = 'Legacy publication has ambiguous calendar ownership. Reconcile the linked event before retrying.'; }
            elseif (get_post_status($event) === 'publish') { $job['state'] = 'complete'; $job['completed_at'] = time(); }
            elseif (!user_can($actor, 'edit_post', $id) || bvmgr_validate_event_plan($id)) { $job['state'] = 'failed'; $job['error'] = 'Legacy publication readiness or operator authorization failed. Review the saved plan and retry as an authorized operator.'; }
            bvmgr_ep_publication_store($id, $job);
        } finally { bvmgr_ep_publication_unlock($id); }
    }
}
/** Called by the dedicated worker before its normal WP-CLI due-now dispatch. */
function bvmgr_ep_publication_reconcile(): array {
    global $wpdb;
    bvmgr_ep_publication_adopt_legacy();
    $keys = $wpdb->get_col($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_id LIMIT 501", $wpdb->esc_like('vms_ep_publish_pending_') . '%'));
    if ($wpdb->last_error !== '' || count($keys) > 500) { throw new RuntimeException('Publication outbox exceeds the bounded worker inventory; operator review is required.'); }
    $result = array('checked' => 0, 'repaired' => 0, 'busy' => 0);
    foreach ($keys as $key) {
        $id = (int) substr($key, strlen('vms_ep_publish_pending_'));
        if (!$id || !bvmgr_ep_publication_lock($id)) { ++$result['busy']; continue; }
        try {
            $job = bvmgr_ep_publication_read($id); ++$result['checked'];
            if (!$job || (int) ($job['plan_id'] ?? 0) !== $id || empty($job['generation'])) { throw new RuntimeException('Malformed publication outbox for plan ' . $id); }
            if (!in_array($job['state'], array('queued', 'running'), true)) { bvmgr_ep_publication_store($id, $job); continue; }
            if ($job['state'] === 'running' && (int) $job['due_at'] > time()) { continue; }
            if ($job['state'] === 'running') { $job['state'] = 'queued'; $job['error'] = 'Previous publication worker ended without a completion receipt; automatic dispatch is retrying.'; bvmgr_ep_publication_store($id, $job); }
            $args = array($id, $job['generation']); $existing = wp_next_scheduled('vms_event_plan_deferred_calendar_publish', $args);
            if (!$existing) {
                if (bvmgr_ep_publication_schedule_job($id, $job)) { ++$result['repaired']; }
                else { $job['state'] = 'failed'; $job['error'] = 'The minute worker could not restore the publication schedule. Use Retry calendar publication after reviewing the scheduler.'; bvmgr_ep_publication_store($id, $job); }
            }
        } finally { bvmgr_ep_publication_unlock($id); }
    }
    return $result;
}
/** Hold the same plan lock through editor/status writes; no changes to unrelated plans. */
function bvmgr_ep_publication_guard_save(int $id): void {
    static $held = array();
    if (!$id || ($GLOBALS['bvmgr_ep_publication_active'] ?? 0) === $id) { return; }
    if (isset($held[$id])) { bvmgr_ep_publication_assert_lock($id); return; }
    if (!bvmgr_ep_publication_lock($id)) { wp_die(esc_html__('Calendar publication is running. Wait for its result, then reload before saving this plan.', 'backstage-venue-manager'), '', array('response' => 409)); }
    $held[$id] = true;
    register_shutdown_function(static function() use ($id) { bvmgr_ep_publication_unlock($id); });
}
add_filter('wp_insert_post_data', static function($data, $postarr) { if (($data['post_type'] ?? '') === 'vms_event_plan') { bvmgr_ep_publication_guard_save(absint($postarr['ID'] ?? 0)); } return $data; }, 1, 2);
$bvmgr_publication_guard_meta = static function($check, $id, $key) { if (in_array($key, array('_vms_event_plan_status', '_vms_tec_event_id', '_vms_event_date', '_vms_start_time', '_vms_end_time'), true) && get_post_type($id) === 'vms_event_plan') { bvmgr_ep_publication_guard_save((int) $id); } return $check; };
foreach (array('update_post_metadata', 'add_post_metadata', 'delete_post_metadata') as $filter) { add_filter($filter, $bvmgr_publication_guard_meta, 1, 3); }
unset($bvmgr_publication_guard_meta);
function bvmgr_ep_publication_cancel_changed(int $id): void {
    if (!bvmgr_ep_publication_lock($id)) { wp_die('Calendar publication is running. Reload before saving this plan.', '', array('response' => 409)); }
    try {
    $job = bvmgr_ep_publication_read($id);
    if (($GLOBALS['bvmgr_ep_publication_active'] ?? 0) === $id || !$job || !in_array($job['state'], array('queued', 'running'), true)) { return; }
    if (get_post_status($id) === 'publish' && get_post_meta($id, '_vms_event_plan_status', true) === 'published' && $job['context'] === bvmgr_ep_publication_context($id)) { return; }
    // Publishing itself writes the workflow after enqueue; only this ready->published window is allowed.
    if (($GLOBALS['bvmgr_ep_publication_producing'][$id] ?? '') === $job['generation'] && get_post_status($id) === 'publish' && get_post_meta($id, '_vms_event_plan_status', true) === 'ready' && $job['context'] === bvmgr_ep_publication_context($id)) { return; }
    $job['state'] = 'cancelled'; $job['error'] = 'Publication cancelled after a saved plan status, occurrence or linked event change. Review the saved plan and Publish again.';
    bvmgr_ep_publication_store($id, $job);
    wp_clear_scheduled_hook('vms_event_plan_deferred_calendar_publish', array($id, $job['generation']));
    } finally { bvmgr_ep_publication_unlock($id); }
}
$bvmgr_publication_changed_meta = static function($mid, $id, $key) { if (in_array($key, array('_vms_event_plan_status', '_vms_tec_event_id', '_vms_event_date', '_vms_start_time', '_vms_end_time'), true) && get_post_type($id) === 'vms_event_plan') { bvmgr_ep_publication_cancel_changed((int) $id); } };
foreach (array('updated_post_meta', 'added_post_meta', 'deleted_post_meta') as $hook) { add_action($hook, $bvmgr_publication_changed_meta, 20, 3); }
unset($bvmgr_publication_changed_meta);
add_action('transition_post_status', static function($new, $old, $post) { if ($post->post_type === 'vms_event_plan' && $new !== 'publish') { bvmgr_ep_publication_cancel_changed((int) $post->ID); } }, 20, 3);

add_action('admin_notices', static function() {
    $id = absint($_GET['post'] ?? 0);
    if (!$id || get_post_type($id) !== 'vms_event_plan' || !current_user_can('edit_post', $id)) { return; }
    $job = bvmgr_ep_publication_read($id) ?: (array) get_post_meta($id, '_vms_calendar_publish_job', true);
    if (!$job || $job['state'] === 'complete') { return; }
    $late = in_array($job['state'], array('queued', 'running'), true) && time() > (int) $job['due_at'] + 120;
    $message = $late ? 'Calendar publication is delayed. Reload for the latest outcome or retry dispatch. If this repeats, ask an administrator to check the dedicated minute worker.' : ($job['error'] ?: 'Calendar publication is ' . $job['state'] . '. Reload after the scheduled UTC time: ' . gmdate('Y-m-d H:i:s', (int) $job['due_at']) . '.');
    echo '<div class="notice notice-warning"><p>' . esc_html($message) . '</p>';
    if ((empty($job['requires_reconciliation']) || (int) get_post_meta($id, '_vms_tec_event_id', true) > 0) && ($late || in_array($job['state'], array('failed', 'cancelled'), true)) && get_post_meta($id, '_vms_event_plan_status', true) === 'published') {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="vms_retry_calendar_publication"><input type="hidden" name="plan_id" value="' . (int) $id . '">';
        wp_nonce_field('vms_retry_calendar_publication_' . $id);
        submit_button('Retry calendar publication', 'secondary', 'submit', false); echo '</form>';
    }
    echo '</div>';
});
add_action('admin_post_vms_retry_calendar_publication', static function() {
    $id = absint($_POST['plan_id'] ?? 0);
    if (!$id || !current_user_can('edit_post', $id)) { wp_die('Publication retry is not authorized.', '', array('response' => 403)); }
    check_admin_referer('vms_retry_calendar_publication_' . $id);
    if (get_post_status($id) !== 'publish' || get_post_meta($id, '_vms_event_plan_status', true) !== 'published' || bvmgr_validate_event_plan($id)) { wp_die('Review the saved plan, Mark Ready and Publish before retrying.', '', array('response' => 409)); }
    if (!bvmgr_event_plan_schedule_deferred_calendar_publish($id, 'operator_retry')) { wp_die('Publication could not be queued. Reload the plan for its saved diagnostic.', '', array('response' => 409)); }
    wp_safe_redirect(get_edit_post_link($id, 'raw')); exit;
});
