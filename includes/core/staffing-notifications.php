<?php
/** Delivery follows committed staffing audit; existing notification ledger is the outbox. */
defined('ABSPATH') || exit;

function bvmgr_staffing_notify_floor(): int
{
    $value = get_option('bvmgr_staffing_notify_after_audit', null);
    return is_scalar($value) && preg_match('/^\d+$/D', (string) $value) ? (int) $value : -1;
}

/** Explicit administrator cutover; never called by a read or ordinary bootstrap. */
function bvmgr_staffing_notify_enable(): bool
{
    global $wpdb;
    if (!current_user_can('manage_options') || bvmgr_staffing_transaction_active()) return false;
    if (bvmgr_staffing_notify_floor() >= 0) return true;
    $lock = bvmgr_staffing_lock_name();
    if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lock)) !== 1) return false;
    try {
        $floor = $wpdb->get_var($wpdb->prepare('SELECT MAX(log_id) FROM %i', bvmgr_staffing_table_name('audit')));
        if ($wpdb->last_error !== '') return false;
        return add_option('bvmgr_staffing_notify_after_audit', (int) $floor, '', false);
    } finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); }
}

function bvmgr_staffing_notify_key(int $audit_id): string { return 'staffing_audit_' . $audit_id; }

/** Reads never repair or create notification work. */
function bvmgr_staffing_notify_audits(int $plan = 0, bool $pending = false): array
{
    global $wpdb;
    $floor = bvmgr_staffing_notify_floor();
    if ($floor < 0) return array();
    $missing = $pending ? " AND NOT EXISTS (SELECT 1 FROM %i n WHERE n.source='vms_staffing' AND n.event_key=CONCAT('staffing_audit_',a.log_id))" : '';
    $sql = "SELECT a.* FROM %i a WHERE a.log_id>%d AND a.action IN ('assignment_transition','assignment_window_change') AND (%d=0 OR a.event_plan_id=%d)" . $missing . ' ORDER BY a.log_id ' . ($pending ? 'ASC' : 'DESC') . ' LIMIT 100';
    $args = array(bvmgr_staffing_table_name('audit'), $floor, $plan, $plan);
    if ($pending) $args[] = bvmgr_notify_log_table_name();
    return (array) $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A);
}

function bvmgr_staffing_notify_logs(string $key): array
{
    global $wpdb;
    return (array) $wpdb->get_results($wpdb->prepare("SELECT * FROM %i WHERE source='vms_staffing' AND event_key=%s ORDER BY id DESC", bvmgr_notify_log_table_name(), $key), ARRAY_A);
}

function bvmgr_staffing_notify_record(array $work, string $status, string $phase, string $error = ''): bool
{
    $work['phase'] = $phase;
    return bvmgr_notify_insert_log(array('source' => 'vms_staffing', 'event_key' => bvmgr_staffing_notify_key((int) $work['audit_id']),
        'recipient_user_id' => $work['recipient']['user_id'] ?? 0, 'recipient_address' => $work['recipient']['email'] ?? '',
        'channel' => 'email', 'template_key' => 'staffing.assignment_changed', 'provider' => 'core_email',
        'status' => $status, 'error_message' => $error, 'payload' => $work));
}

/** One recipient for the canonical person; never substitute a manager/review address. */
function bvmgr_staffing_notify_recipient(int $staff): array
{
    $identity = bvmgr_tech_doc_staff_identity($staff);
    // Reject malformed contact values instead of silently repairing them into a different mailbox.
    if ($identity['email'] !== '') {
        $source = $identity['email_source'];
        $raw = $source === 'linked_user' ? (get_userdata((int) $identity['user_id'])->user_email ?? '') : get_post_meta($staff,
            array('staff_contact_email' => '_vms_contact_email', 'staff_primary_email' => '_vms_vendor_primary_email', 'staff_legacy_email' => '_vms_vendor_email')[$source] ?? '', true);
        if (strtolower(trim((string) $raw)) !== $identity['email']) $identity['error'] = 'invalid_email';
    }
    if ((int) $identity['user_id'] > 0 && !bvmgr_notify_user_channel_enabled((int) $identity['user_id'], 'email')) $identity['error'] = 'email_channel_disabled';
    return $identity;
}

function bvmgr_staffing_notify_work(array $audit): array
{
    $after = json_decode((string) $audit['after_json'], true) ?: array();
    $before = json_decode((string) $audit['before_json'], true) ?: array();
    $id = (int) ($after['assignment_id'] ?? $audit['assignment_id'] ?? 0);
    $row = bvmgr_staffing_lifecycle_row($id);
    $staff = (int) ($after['staff_id'] ?? $before['staff_id'] ?? 0);
    $plan = (int) $audit['event_plan_id'];
    $role_id = (int) ($after['role_id'] ?? $before['role_id'] ?? $row['role_id'] ?? 0);
    $role = get_term($role_id, 'vms_staff_role');
    $window = $row ? bvmgr_staffing_lifecycle_window($row) : array();
    $venue = (int) get_post_meta($plan, '_vms_venue_id', true);
    return array('audit_id' => (int) $audit['log_id'], 'operation_id' => (string) ($audit['operation_id'] ?? ''),
        'assignment_id' => $id, 'plan_id' => $plan, 'staff_id' => $staff, 'revision' => (int) ($after['revision'] ?? -1),
        'status' => (string) ($after['status'] ?? $before['status'] ?? ''), 'action' => $audit['action'],
        'previous_status' => $after['previous_status'] ?? $before['status'] ?? null, 'committed_at' => $audit['created_at'],
        'event' => get_the_title($plan), 'date' => get_post_meta($plan, '_vms_event_date', true),
        'start' => !empty($window['start_ts']) ? wp_date('Y-m-d H:i', (int) $window['start_ts']) : '',
        'end' => !empty($window['end_ts']) ? wp_date('Y-m-d H:i', (int) $window['end_ts']) : '',
        'timezone' => wp_timezone_string(), 'role' => $role instanceof WP_Term ? $role->name : __('Assigned role', 'backstage-venue-manager'),
        'venue' => $venue > 0 ? get_the_title($venue) : '',
        'recipient' => bvmgr_staffing_notify_recipient($staff), 'attempts' => 0);
}

function bvmgr_staffing_notify_obsolete(array $work): bool
{
    $row = bvmgr_staffing_lifecycle_row((int) $work['assignment_id']);
    return !$row || (int) $row['staff_id'] !== (int) $work['staff_id'] || (int) $row['event_plan_id'] !== (int) $work['plan_id']
        || (int) $row['revision'] !== (int) $work['revision'] || $row['status'] !== $work['status']
        || get_post_type((int) $work['plan_id']) !== 'vms_event_plan' || get_post_status((int) $work['plan_id']) === 'trash';
}

function bvmgr_staffing_notify_template(array $work): array
{
    $status = bvmgr_staffing_status_label((string) $work['status']);
    $action = $work['action'] === 'assignment_window_change' ? $status . ' · ' . __('Shift time updated', 'backstage-venue-manager') : $status;
    $url = admin_url('admin-post.php?action=bvmgr_staffing_assignment&assignment_id=' . (int) $work['assignment_id']);
    $lines = array($work['event'], $work['role'] . ' — ' . $action, $work['start'] . ' – ' . $work['end'] . ' (' . $work['timezone'] . ')');
    if ($work['venue'] !== '') $lines[] = $work['venue'];
    $lines[] = $work['status'] === 'proposed' ? __('Please sign in to review and accept or decline this proposal.', 'backstage-venue-manager') : __('This records your staffing assignment update. Sign in to view its current state.', 'backstage-venue-manager');
    if (!empty($work['recipient']['user_id'])) $lines[] = $url;
    else $lines[] = __('Contact the event operator to respond; no portal account is linked to this staff record.', 'backstage-venue-manager');
    $message = apply_filters('vms_notify_template_payload', null, 'staffing_assignment_changed', 'staffing.assignment_changed',
        bvmgr_notify_user_locale((int) ($work['recipient']['user_id'] ?? 0)), $work, (int) ($work['recipient']['user_id'] ?? 0));
    if ($message === null) return array('subject' => __('Staffing update: ', 'backstage-venue-manager') . $work['event'], 'body_text' => implode("\n", $lines));
    if (!is_array($message) || empty($message['subject']) || empty($message['body_text'])) throw new RuntimeException('invalid_staffing_template');
    return $message;
}

/** Called only under the delivery lock, after staffing released its transaction and lock. */
function bvmgr_staffing_notify_deliver(array $latest, bool $manual = false): void
{
    $work = json_decode((string) $latest['payload_json'], true);
    if (!is_array($work) || !isset($work['audit_id'], $work['assignment_id'], $work['revision'])) return;
    if (in_array($latest['status'], array('sent', 'skipped'), true) || ($work['phase'] ?? '') === 'attempt') return;
    if ($latest['status'] === 'failed' && ((!$manual && (int) $work['attempts'] >= 3) || time() - strtotime($latest['created_at'] . ' UTC') < 60)) return;
    if (bvmgr_staffing_notify_obsolete($work)) { bvmgr_staffing_notify_record($work, 'skipped', 'superseded', 'superseded_by_current_assignment'); return; }
    $person = bvmgr_staffing_notify_recipient((int) $work['staff_id']);
    if (!empty($work['recipient']['email']) && ($person['email'] !== $work['recipient']['email'] || (int) $person['user_id'] !== (int) $work['recipient']['user_id'])) {
        bvmgr_staffing_notify_record($work, 'skipped', 'superseded', 'recipient_identity_changed'); return;
    }
    $work['recipient'] = $person;
    $work['attempts'] = (int) $work['attempts'] + 1;
    if ($person['error'] !== '') { bvmgr_staffing_notify_record($work, 'failed', 'failed', $person['error']); return; }
    try { $message = bvmgr_staffing_notify_template($work); }
    catch (Throwable $e) { bvmgr_staffing_notify_record($work, 'failed', 'failed', 'template_render_failed'); return; }
    // Durable marker BEFORE transport: a crash/unknown result must never auto-resend.
    if (!bvmgr_staffing_notify_record($work, 'queued', 'attempt', 'delivery_outcome_unknown')) return;
    try { $result = bvmgr_notify_provider_core_email_send(array_merge($message, array('to' => $person['email']))); }
    catch (Throwable $e) { return; }
    bvmgr_staffing_notify_record($work, !empty($result['success']) ? 'sent' : 'failed', 'terminal', (string) ($result['error_message'] ?? ''));
}

/** Recover post-cutover committed audits, then process the existing ledger. No read hook calls this. */
function bvmgr_staffing_notify_tick(int $plan = 0, int $retry_audit = 0): void
{
    global $wpdb;
    if (bvmgr_staffing_transaction_active() || bvmgr_staffing_notify_floor() < 0 || get_class($wpdb) !== 'wpdb') return;
    $original = $wpdb;
    $original->flush();
    // Reuse the accepted no-reconnect connection adapter, without starting a transaction.
    $wpdb = new BVMGR_Staffing_Transaction_DB($original);
    $locked = false;
    $lock = 'bvm_staffing_delivery_' . substr(hash('sha256', $wpdb->dbname . ':' . $wpdb->prefix), 0, 40);
    try {
        if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $lock)) !== 1) return;
        $locked = true;
        foreach (bvmgr_staffing_notify_audits($plan, true) as $audit) {
            $work = bvmgr_staffing_notify_work($audit);
            if (!bvmgr_staffing_notify_record($work, 'queued', 'work')) break;
        }
        $sql = "SELECT n.* FROM %i n INNER JOIN (SELECT event_key,MAX(id) id FROM %i WHERE source='vms_staffing' GROUP BY event_key) current ON current.id=n.id WHERE n.status IN ('queued','failed') AND n.error_message<>'delivery_outcome_unknown' ORDER BY n.id ASC";
        $rows = (array) $wpdb->get_results($wpdb->prepare($sql, bvmgr_notify_log_table_name(), bvmgr_notify_log_table_name()), ARRAY_A);
        $processed = 0;
        foreach ($rows as $latest) {
            $work = json_decode((string) $latest['payload_json'], true);
            if (!is_array($work) || ($plan && (int) ($work['plan_id'] ?? 0) !== $plan) || ($retry_audit && (int) ($work['audit_id'] ?? 0) !== $retry_audit)) continue;
            if (!$retry_audit && (int) ($work['attempts'] ?? 0) >= 3) continue;
            bvmgr_staffing_notify_deliver($latest, $retry_audit > 0);
            if (++$processed >= 100) break;
        }
    } catch (Throwable $error) {
        // Committed audit (or the durable attempt marker) remains the recovery authority.
        error_log('[BVM staffing delivery] processing_failed; inspect committed audit and notification history');
    } finally {
        if ($locked) { try { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); } catch (Throwable $ignored) {} }
        $wpdb = $original;
    }
}

add_action('vms_staffing_committed', static function (): void { bvmgr_staffing_notify_tick(); });
// Existing hourly event, independently of whether the optional staff-task digest is enabled.
add_action('vms_notify_digest_tick_cron', static function (): void { bvmgr_staffing_notify_tick(); }, 20);
