<?php
/** Past-event notification guard; real SQL and intercepted transport only. */
require __DIR__ . '/bootstrap.php';

$checks = 0;
$mail = array();
$transport = 'success';

function png_check(bool $ok, string $label): void
{
    global $checks;
    if (!$ok) throw new RuntimeException($label);
    $checks++;
}

function png_notify_count(): int
{
    global $wpdb;
    return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}vms_notify_log WHERE source='vms_staffing'");
}

function png_latest(int $plan): array
{
    $audits = bvmgr_staffing_notify_audits($plan);
    return $audits ? (bvmgr_staffing_notify_logs(bvmgr_staffing_notify_key((int) $audits[0]['log_id']))[0] ?? array()) : array();
}

function png_fixture(string $date, string $start, string $end, string $email): array
{
    global $wpdb;
    $plan = wp_insert_post(array('post_type' => 'vms_event_plan', 'post_status' => 'publish', 'post_title' => 'Past guard ' . wp_generate_uuid4()));
    foreach (array('_vms_event_date' => $date, '_vms_start_time' => $start, '_vms_end_time' => $end, '_vms_event_plan_status' => 'confirmed') as $key => $value) {
        update_post_meta($plan, $key, $value);
    }
    $staff = wp_insert_post(array('post_type' => 'vms_staff', 'post_status' => 'publish', 'post_title' => 'Past guard staff'));
    $user = wp_insert_user(array('user_login' => 'past-guard-' . wp_generate_uuid4(), 'user_pass' => wp_generate_password(40), 'user_email' => $email, 'role' => 'subscriber'));
    png_check(!is_wp_error($user), 'fixture user created');
    update_post_meta($staff, '_vms_linked_user_id', $user);
    update_user_meta($user, '_vms_staff_id', $staff);
    $term = wp_insert_term('Past guard role ' . wp_generate_uuid4(), 'vms_staff_role');
    $role = (int) $term['term_id'];
    wp_set_post_terms($staff, array($role), 'vms_staff_role');
    $now = bvmgr_staffing_now_mysql_utc();
    $wpdb->insert(bvmgr_staffing_table_name('event_slots'), array(
        'event_plan_id' => $plan, 'role_id' => $role, 'headcount_needed' => 1, 'shift_time_mode' => 'absolute',
        'shift_start_local' => $start, 'shift_end_local' => $end, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now,
    ));
    $slot = (int) $wpdb->insert_id;
    $result = bvmgr_staffing_atomic(static function () use ($slot, $staff): array {
        bvmgr_staffing_matrix_proposals($slot, array($staff));
        bvmgr_staffing_sync_lifecycle_window($slot);
        return array('ok' => true);
    });
    png_check(!empty($result['ok']), 'proposal committed');
    $id = (int) $wpdb->get_var($wpdb->prepare('SELECT assignment_id FROM %i WHERE slot_id=%d', bvmgr_staffing_table_name('assignments'), $slot));
    return compact('plan', 'staff', 'user', 'slot', 'role', 'id');
}

function png_transition(array $fixture, string $target): array
{
    $row = bvmgr_staffing_lifecycle_row($fixture['id']);
    return bvmgr_staffing_transition_assignment($fixture['id'], $target, array(
        'context' => 'operator', 'event_plan_id' => $fixture['plan'], 'revision' => $row['revision'],
        'operation_id' => wp_generate_uuid4(), 'reason' => 'Past-event notification guard test',
    ));
}

function png_age_key(string $key): void
{
    global $wpdb;
    $latest = bvmgr_staffing_notify_logs($key)[0] ?? array();
    png_check(!empty($latest['id']), 'retry candidate exists before aging');
    $wpdb->update(bvmgr_notify_log_table_name(), array('created_at' => gmdate('Y-m-d H:i:s', time() - 65)), array('id' => (int) $latest['id']));
}

png_check(bvmgr_staffing_migrate_lifecycle()['ok'], 'lifecycle schema');
update_option('timezone_string', 'America/Chicago');
$site_now = current_datetime();
$last_month = $site_now->modify('-1 month')->format('Y-m-d');
$tomorrow = $site_now->modify('+1 day')->format('Y-m-d');
$today = $site_now->format('Y-m-d');

add_filter('pre_wp_mail', static function ($pre, $message) use (&$mail, &$transport) {
    if (!str_starts_with((string) ($message['subject'] ?? ''), 'Staffing update:')) return true;
    $mail[] = $message;
    if ($transport === 'throw') throw new RuntimeException('uncertain transport');
    return $transport !== 'failure';
}, PHP_INT_MAX, 2);

// Existing cutover behavior remains unchanged: pre-cutover audits never backfill.
$before = png_notify_count();
$precutoff = png_fixture($tomorrow, '12:00', '18:00', 'past-guard-precutoff@example.invalid');
png_check(png_notify_count() === $before && count($mail) === 0, 'delivery disabled before cutover');
png_check(bvmgr_staffing_notify_enable(), 'explicit cutover enabled');
$floor = bvmgr_staffing_notify_floor();
bvmgr_staffing_notify_tick();
png_check($floor > 0 && png_notify_count() === $before && count($mail) === 0, 'historical audit cutover still prevents backfill');

// Removing staff from a concert held last month remains editable but never emails.
$past = png_fixture($last_month, '12:00', '18:00', 'past-guard-remove@example.invalid');
$latest = png_latest($past['plan']);
png_check(($latest['status'] ?? '') === 'skipped' && ($latest['error_message'] ?? '') === 'event_already_ended', 'past proposal recorded as skipped');
$mail_before = count($mail);
$removed = png_transition($past, 'canceled');
$latest = png_latest($past['plan']);
png_check(!empty($removed['ok']) && bvmgr_staffing_lifecycle_row($past['id'])['status'] === 'canceled', 'operator can remove historical staff');
png_check(count($mail) === $mail_before && ($latest['status'] ?? '') === 'skipped' && ($latest['error_message'] ?? '') === 'event_already_ended', 'historical removal sends no email');

// A historical schedule/assignment change remains committed without delivery.
$historical_change = png_fixture($last_month, '12:00', '18:00', 'past-guard-change@example.invalid');
$mail_before = count($mail);
$changed = bvmgr_staffing_atomic(static function () use ($historical_change, $wpdb): array {
    $wpdb->update(bvmgr_staffing_table_name('event_slots'), array('shift_start_local' => '13:00', 'shift_end_local' => '19:00'), array('slot_id' => $historical_change['slot']));
    bvmgr_staffing_sync_lifecycle_window($historical_change['slot']);
    return array('ok' => true);
});
$latest = png_latest($historical_change['plan']);
$payload = json_decode((string) ($latest['payload_json'] ?? ''), true);
png_check(!empty($changed['ok']) && ($payload['action'] ?? '') === 'assignment_window_change', 'historical assignment change committed');
png_check(count($mail) === $mail_before && ($latest['status'] ?? '') === 'skipped' && ($latest['error_message'] ?? '') === 'event_already_ended', 'historical assignment change sends no email');

// A performance that ended earlier today is also terminally suppressed.
png_check((int) $site_now->format('Hi') > 1, 'test clock supports earlier-today case');
$today_past = png_fixture($today, '00:00', '00:01', 'past-guard-today@example.invalid');
$latest = png_latest($today_past['plan']);
png_check(($latest['status'] ?? '') === 'skipped' && ($latest['error_message'] ?? '') === 'event_already_ended', 'event ended earlier today is skipped');

// Upcoming and overnight performances retain ordinary delivery.
$mail_before = count($mail);
$future = png_fixture($tomorrow, '12:00', '18:00', 'past-guard-future@example.invalid');
png_check(count($mail) === $mail_before + 1 && (png_latest($future['plan'])['status'] ?? '') === 'sent', 'show that has not ended still sends');
$mail_before = count($mail);
$overnight = png_fixture($today, '23:00', '01:00', 'past-guard-overnight@example.invalid');
$timing = bvmgr_staffing_event_plan_datetime($overnight['plan']);
png_check(($timing['end_local'] ?? null) instanceof DateTimeImmutable && $timing['end_local']->format('Y-m-d') === $site_now->modify('+1 day')->format('Y-m-d'), 'past-midnight performance resolves to next-day end');
png_check(count($mail) === $mail_before + 1 && (png_latest($overnight['plan'])['status'] ?? '') === 'sent', 'past-midnight performance still sends before ending');

// Missing and malformed schedule data fail closed and cannot reach transport.
$mail_before = count($mail);
$missing = png_fixture('', '12:00', '18:00', 'past-guard-missing@example.invalid');
$latest = png_latest($missing['plan']);
png_check(count($mail) === $mail_before && ($latest['status'] ?? '') === 'skipped' && ($latest['error_message'] ?? '') === 'event_schedule_unavailable', 'missing event date fails closed');
$invalid = png_fixture('not-a-date', '25:99', '18:00', 'past-guard-invalid@example.invalid');
$latest = png_latest($invalid['plan']);
png_check(count($mail) === $mail_before && ($latest['status'] ?? '') === 'skipped' && ($latest['error_message'] ?? '') === 'event_schedule_unavailable', 'invalid event timing fails closed');

// A failure queued while upcoming becomes terminally skipped if retried after the show ends.
$transport = 'failure';
$mail_before = count($mail);
$retry = png_fixture($tomorrow, '12:00', '18:00', 'past-guard-retry@example.invalid');
$failed = png_latest($retry['plan']);
$failed_key = (string) $failed['event_key'];
$failed_payload = json_decode((string) $failed['payload_json'], true);
png_check(count($mail) === $mail_before + 1 && ($failed['status'] ?? '') === 'failed', 'upcoming delivery failure remains retryable');
$transport = 'success';
// Advance only the test clock authority without creating a newer assignment revision.
$wpdb->update($wpdb->postmeta, array('meta_value' => $last_month), array('post_id' => $retry['plan'], 'meta_key' => '_vms_event_date'));
clean_post_cache($retry['plan']);
png_age_key($failed_key);
$mail_before = count($mail);
bvmgr_staffing_notify_tick($retry['plan'], (int) $failed_payload['audit_id']);
$retried = bvmgr_staffing_notify_logs($failed_key)[0] ?? array();
png_check(count($mail) === $mail_before && ($retried['status'] ?? '') === 'skipped' && ($retried['error_message'] ?? '') === 'event_already_ended', 'retry after event end is skipped without transport');

// Upcoming cancellations continue to send, and terminal rows remain duplicate-safe.
$mail_before = count($mail);
$upcoming_cancel = png_fixture($tomorrow, '14:00', '20:00', 'past-guard-cancel@example.invalid');
png_check(count($mail) === $mail_before + 1, 'upcoming proposal sends before cancellation');
$mail_before = count($mail);
$canceled = png_transition($upcoming_cancel, 'canceled');
png_check(!empty($canceled['ok']) && count($mail) === $mail_before + 1 && (png_latest($upcoming_cancel['plan'])['status'] ?? '') === 'sent', 'upcoming-event cancellation still sends');
$mail_before = count($mail);
$logs_before = png_notify_count();
bvmgr_staffing_notify_tick();
bvmgr_staffing_notify_tick();
png_check(count($mail) === $mail_before && png_notify_count() === $logs_before, 'duplicate ticks do not resend sent or skipped work');

echo "PASS {$checks} past-event notification guard assertions; " . count($mail) . " intercepted attempts\n";
