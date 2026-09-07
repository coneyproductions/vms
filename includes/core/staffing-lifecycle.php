<?php
/** Atomic staffing repository. Schema installation is explicit; never done on a page read. */
defined('ABSPATH') || exit;

final class BVMGR_Staffing_Failure extends RuntimeException {}

/** Adopt wpdb's connection, but never reconnect/replay a transactional query. */
final class BVMGR_Staffing_Transaction_DB extends wpdb
{
    public function __construct(wpdb $source)
    {
        foreach (get_object_vars($source) as $key => $value) $this->$key = $value;
        $this->suppress_errors(true);
    }
    private bool $boundary_control = false;
    public function finish_transaction(string $statement)
    {
        if (!in_array($statement, array('COMMIT', 'ROLLBACK'), true)) throw new BVMGR_Staffing_Failure('invalid_transaction_control');
        $this->boundary_control = true;
        try { return $this->query($statement); }
        finally { $this->boundary_control = false; }
    }
    public function check_connection($allow_bail = true) { return false; }
    public function insert($table, $data, $format = null) { $r = parent::insert($table, $data, $format); if ($r === false) throw new BVMGR_Staffing_Failure('database_error'); return $r; }
    public function update($table, $data, $where, $format = null, $where_format = null) { $r = parent::update($table, $data, $where, $format, $where_format); if ($r === false) throw new BVMGR_Staffing_Failure('database_error'); return $r; }
    public function delete($table, $where, $where_format = null) { $r = parent::delete($table, $where, $where_format); if ($r === false) throw new BVMGR_Staffing_Failure('database_error'); return $r; }

    public function query($query)
    {
        // Any additional table enlisted by a caller (for example rescheduling)
        // must also support rollback. DDL is never allowed in this boundary.
        if (bvmgr_staffing_transaction_active()) {
            if (!$this->boundary_control && preg_match('/^\s*(BEGIN|START|COMMIT|ROLLBACK|SAVEPOINT|RELEASE|SET)\b/i', $query)) throw new BVMGR_Staffing_Failure('nested_transaction_control');
            if (preg_match('/^\s*(ALTER|CREATE|DROP|TRUNCATE|RENAME|LOCK|UNLOCK)\b/i', $query)) throw new BVMGR_Staffing_Failure('ddl_in_transaction');
            if (preg_match('/^\s*(?:INSERT(?: IGNORE)? INTO|REPLACE INTO|UPDATE|DELETE FROM)\s+(`[^`]+`|[a-zA-Z0-9_]+)/i', $query, $match)) {
                $table = trim($match[1], '`');
                $engine = $this->get_var($this->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table));
                if (strtolower((string) $engine) !== 'innodb') throw new BVMGR_Staffing_Failure('transactional_engine_required');
            }
        }
        $result = parent::query($query);
        if ($result === false) throw new BVMGR_Staffing_Failure('database_error');
        return $result;
    }
}

function bvmgr_staffing_transaction_active(): bool
{
    return !empty($GLOBALS['bvmgr_staffing_transaction']);
}

function bvmgr_staffing_lock_name(): string
{
    global $wpdb;
    return 'bvm_staffing_' . substr(hash('sha256', $wpdb->dbname . ':' . $wpdb->prefix), 0, 48);
}

function bvmgr_staffing_transaction_tables(): array
{
    global $wpdb;
    return array(bvmgr_staffing_table_name('assignments'), bvmgr_staffing_table_name('event_slots'),
        bvmgr_staffing_table_name('audit'), bvmgr_staffing_table_name('rollups'), $wpdb->posts, $wpdb->postmeta);
}

function bvmgr_staffing_require_transaction_schema(bool $columns = true): void
{
    global $wpdb;
    foreach (bvmgr_staffing_transaction_tables() as $table) {
        $engine = $wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table));
        if (strtolower((string) $engine) !== 'innodb') throw new BVMGR_Staffing_Failure('transactional_engine_required');
    }
    if ($columns) {
        $expected = array('assignments' => array('revision' => array('/^bigint(?:\(\d+\))? unsigned$/i', 'NO')),
            'audit' => array('assignment_id' => array('/^bigint(?:\(\d+\))? unsigned$/i', 'YES'), 'operation_id' => array('/^varchar\(64\)$/i', 'YES')));
        foreach ($expected as $kind => $names) {
            $present = $wpdb->get_results($wpdb->prepare('SHOW COLUMNS FROM %i', bvmgr_staffing_table_name($kind)), ARRAY_A);
            $present = array_column($present, null, 'Field');
            foreach ($names as $name => $shape) {
                if (!isset($present[$name]) || !preg_match($shape[0], $present[$name]['Type']) || $present[$name]['Null'] !== $shape[1]) throw new BVMGR_Staffing_Failure('lifecycle_migration_required');
            }
        }
        $indexes = $wpdb->get_results($wpdb->prepare('SHOW INDEX FROM %i', bvmgr_staffing_table_name('audit')), ARRAY_A);
        foreach (array('lifecycle_operation' => array('operation_id', 0), 'lifecycle_assignment' => array('assignment_id', 1)) as $name => $shape) {
            $index = array_values(array_filter($indexes, static function ($row) use ($name): bool { return $row['Key_name'] === $name; }));
            if (count($index) !== 1 || $index[0]['Column_name'] !== $shape[0] || (int) $index[0]['Non_unique'] !== $shape[1] || $index[0]['Sub_part'] !== null) throw new BVMGR_Staffing_Failure('lifecycle_migration_required');
        }
    }
}

/** All staffing writers share this connection-scoped lock and transaction. */
function bvmgr_staffing_atomic(callable $operation): array
{
    global $wpdb;
    if (bvmgr_staffing_transaction_active()) return $operation();
    if (get_class($wpdb) !== 'wpdb') return array('ok' => false, 'error' => 'unsupported_database_adapter');
    $original = $wpdb;
    $original->flush(); // Do not share a mysqli_result resource between adapters.
    $wpdb = new BVMGR_Staffing_Transaction_DB($original);
    $lock = bvmgr_staffing_lock_name();
    $locked = false;
    $started = false;
    $committing = false;
    $events = array();
    $saved_events = array();
    $plans = array();
    $result = array();
    try {
        if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lock)) !== 1) throw new BVMGR_Staffing_Failure('staffing_busy');
        $locked = true;
        if ((int) $wpdb->get_var('SELECT @@autocommit') !== 1) throw new BVMGR_Staffing_Failure('external_transaction');
        bvmgr_staffing_require_transaction_schema();
        // Both MySQL and MariaDB reject SET TRANSACTION inside an existing
        // transaction, even before its first write. Never implicitly commit it.
        // Keep this adjacent to START: MySQL can consume next-transaction
        // isolation on the schema reads above, reverting the actual staffing
        // transaction to the connection's default REPEATABLE READ isolation.
        try { $wpdb->query('SET TRANSACTION ISOLATION LEVEL READ COMMITTED'); }
        catch (BVMGR_Staffing_Failure $e) { throw new BVMGR_Staffing_Failure('external_transaction'); }
        $wpdb->query('START TRANSACTION');
        $started = true;
        $GLOBALS['bvmgr_staffing_transaction'] = array('events' => array(), 'plans' => array(), 'saved_events' => array());
        $result = $operation();
        if (empty($result['ok'])) throw new BVMGR_Staffing_Failure((string) ($result['error'] ?? 'invalid_operation'));
        $events = $GLOBALS['bvmgr_staffing_transaction']['events'];
        $plans = $GLOBALS['bvmgr_staffing_transaction']['plans'];
        $saved_events = $GLOBALS['bvmgr_staffing_transaction']['saved_events'];
        if ((int) $wpdb->get_var($wpdb->prepare('SELECT IS_USED_LOCK(%s) = CONNECTION_ID()', $lock)) !== 1) throw new BVMGR_Staffing_Failure('staffing_lock_lost');
        $committing = true;
        $wpdb->finish_transaction('COMMIT');
        $committing = false;
        $started = false;
    } catch (Throwable $e) {
        $plans = $GLOBALS['bvmgr_staffing_transaction']['plans'] ?? array();
        $rolled_back = false;
        if ($started) {
            try { $wpdb->finish_transaction('ROLLBACK'); $rolled_back = true; } catch (Throwable $ignored) {}
        }
        $events = array();
        $saved_events = array();
        $result = array_merge($result, array('ok' => false, 'rolled_back' => $rolled_back && !$committing, 'error' => $committing ? 'commit_outcome_unknown' : ($e instanceof BVMGR_Staffing_Failure ? $e->getMessage() : 'database_error')));
    } finally {
        unset($GLOBALS['bvmgr_staffing_transaction']);
        if ($locked) {
            try { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); } catch (Throwable $ignored) {}
        }
        $wpdb = $original;
        foreach (array_unique($plans) as $plan_id) clean_post_cache($plan_id);
    }
    // Commit has already succeeded. Delivery is deliberately not implemented.
    foreach ($events as $event) {
        try { do_action('vms_staffing_assignment_transitioned', $event); }
        catch (Throwable $e) { $result['event_warning'] = 'subscriber_failed_after_commit'; }
    }
    foreach (array_unique($saved_events) as $plan) {
        try { do_action('vms_staffing_event_saved', $plan); }
        catch (Throwable $e) { $result['event_warning'] = 'subscriber_failed_after_commit'; }
    }
    return $result;
}

function bvmgr_staffing_defer_event_saved(int $plan): void
{
    if (!bvmgr_staffing_transaction_active()) throw new BVMGR_Staffing_Failure('transaction_required');
    $GLOBALS['bvmgr_staffing_transaction']['saved_events'][] = $plan;
}

function bvmgr_staffing_lifecycle_row(int $id): ?array
{
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare('SELECT a.*, s.event_plan_id, s.role_id, s.status AS slot_status FROM %i a INNER JOIN %i s ON s.slot_id = a.slot_id WHERE a.assignment_id = %d',
        bvmgr_staffing_table_name('assignments'), bvmgr_staffing_table_name('event_slots'), $id), ARRAY_A);
}

function bvmgr_staffing_lifecycle_window(array $row): array
{
    global $wpdb;
    wp_cache_delete((int) $row['event_plan_id'], 'post_meta');
    $slot = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE slot_id = %d', bvmgr_staffing_table_name('event_slots'), $row['slot_id']), ARRAY_A);
    if (!$slot) return array();
    $date = (string) get_post_meta((int) $row['event_plan_id'], '_vms_event_date', true);
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, wp_timezone());
    if (!$parsed || $parsed->format('Y-m-d') !== $date) return array();
    $clocks = array($slot['shift_start_local'] ?? '', $slot['shift_end_local'] ?? '',
        get_post_meta((int) $row['event_plan_id'], '_vms_start_time', true), get_post_meta((int) $row['event_plan_id'], '_vms_end_time', true));
    foreach ($clocks as $clock) {
        if ($clock === '' || $clock === null) continue;
        if (!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', (string) $clock)) return array();
        $local = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $clock, wp_timezone());
        if (!$local || $local->format('Y-m-d H:i') !== $date . ' ' . $clock) return array();
    }
    return bvmgr_staffing_resolve_slot_window((int) $row['event_plan_id'], $slot);
}

/** One overlap policy, including different roles within the same event. */
function bvmgr_staffing_assignment_overlaps(array $row, array $window, bool $require_valid_confirmed = false): array
{
    global $wpdb;
    $result = array('hard' => array(), 'soft' => array());
    $start = (int) ($window['start_ts'] ?? 0);
    $end = (int) ($window['end_ts'] ?? 0);
    if ($start <= 0 || $end <= $start) return $result;
    $others = $wpdb->get_results($wpdb->prepare("SELECT a.*, s.event_plan_id, s.role_id FROM %i a INNER JOIN %i s ON s.slot_id = a.slot_id WHERE a.staff_id = %d AND a.assignment_id <> %d AND a.status IN ('proposed','confirmed') AND s.status = 'active'",
        bvmgr_staffing_table_name('assignments'), bvmgr_staffing_table_name('event_slots'), $row['staff_id'], $row['assignment_id'] ?? 0), ARRAY_A);
    foreach ($others as $other) {
        // Resolve canonical slot context, not potentially stale denormalized timestamps.
        $other_window = bvmgr_staffing_lifecycle_window($other);
        $other_start = (int) ($other_window['start_ts'] ?? 0);
        $other_end = (int) ($other_window['end_ts'] ?? 0);
        if ($require_valid_confirmed && $other['status'] === 'confirmed' && ($other_start <= 0 || $other_end <= $other_start)) throw new BVMGR_Staffing_Failure('invalid_confirmed_window');
        if ($other_start > 0 && $other_end > $other_start && $other_start < $end && $other_end > $start) {
            $result[$other['status'] === 'confirmed' ? 'hard' : 'soft'][] = (int) $other['assignment_id'];
        }
    }
    return $result;
}

function bvmgr_staffing_lifecycle_dirty(int $plan_id, int $staff_id): void
{
    global $wpdb;
    $plans = $wpdb->get_col($wpdb->prepare('SELECT DISTINCT s.event_plan_id FROM %i s INNER JOIN %i a ON a.slot_id = s.slot_id WHERE a.staff_id = %d',
        bvmgr_staffing_table_name('event_slots'), bvmgr_staffing_table_name('assignments'), $staff_id));
    $plans[] = $plan_id;
    foreach (array_unique(array_map('intval', $plans)) as $id) {
        $GLOBALS['bvmgr_staffing_transaction']['plans'][] = $id;
        bvmgr_staffing_mark_rollup_dirty($id, 'assignment_lifecycle');
    }
}

/** Called only under the shared boundary, for state changes and proposal creation. */
function bvmgr_staffing_lifecycle_record(array $before, array $after, string $context, string $reason, string $operation_id): void
{
    global $wpdb;
    if (!bvmgr_staffing_transaction_active()) throw new BVMGR_Staffing_Failure('transaction_required');
    $payload = array('schema_version' => 1, 'assignment_id' => (int) $after['assignment_id'], 'slot_id' => (int) $after['slot_id'],
        'event_plan_id' => (int) $after['event_plan_id'], 'staff_id' => (int) $after['staff_id'], 'previous_status' => $before['status'] ?? null,
        'status' => $after['status'], 'revision' => (int) $after['revision'], 'actor_user_id' => get_current_user_id(),
        'actor_context' => $context, 'reason' => $reason, 'operation_id' => $operation_id, 'created_at' => bvmgr_staffing_now_mysql_utc());
    $wpdb->insert(bvmgr_staffing_table_name('audit'), array('event_plan_id' => $payload['event_plan_id'], 'actor_user_id' => $payload['actor_user_id'],
        'assignment_id' => $payload['assignment_id'], 'operation_id' => $operation_id, 'action' => 'assignment_transition',
        'before_json' => wp_json_encode($before), 'after_json' => wp_json_encode($payload), 'created_at' => $payload['created_at']));
    $payload['audit_id'] = (int) $wpdb->insert_id;
    $GLOBALS['bvmgr_staffing_transaction']['events'][] = $payload;
    bvmgr_staffing_lifecycle_dirty($payload['event_plan_id'], $payload['staff_id']);
}

function bvmgr_staffing_transition_assignment(int $id, string $target, array $options): array
{
    return bvmgr_staffing_atomic(static function () use ($id, $target, $options): array {
        global $wpdb;
        $row = bvmgr_staffing_lifecycle_row($id);
        if (!$row) throw new BVMGR_Staffing_Failure('assignment_unavailable');
        $plan = (int) $row['event_plan_id'];
        clean_post_cache($plan);
        clean_post_cache((int) $row['staff_id']);
        wp_cache_delete(get_current_user_id(), 'user_meta');
        $context = $options['context'] ?? '';
        $actor = get_current_user_id();
        if (!$actor || get_post_type($plan) !== 'vms_event_plan' || get_post_type((int) $row['staff_id']) !== 'vms_staff') throw new BVMGR_Staffing_Failure('forbidden');
        if ($context === 'operator') {
            if (!current_user_can('edit_post', $plan) || !in_array($target, array('confirmed', 'canceled', 'proposed'), true)) throw new BVMGR_Staffing_Failure('forbidden');
        } elseif ($context === 'staff') {
            if ((int) get_user_meta($actor, '_vms_staff_id', true) !== (int) $row['staff_id'] || !in_array($target, array('confirmed', 'declined'), true)) throw new BVMGR_Staffing_Failure('forbidden');
        } else { throw new BVMGR_Staffing_Failure('forbidden'); }
        if ((int) ($options['event_plan_id'] ?? 0) !== $plan) throw new BVMGR_Staffing_Failure('forbidden');
        $operation = (string) ($options['operation_id'] ?? '');
        if (!preg_match('/^[a-zA-Z0-9-]{16,64}$/D', $operation)) throw new BVMGR_Staffing_Failure('invalid_operation_id');
        $prior = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE operation_id = %s', bvmgr_staffing_table_name('audit'), $operation), ARRAY_A);
        if ($prior) {
            $saved = json_decode($prior['after_json'], true);
            if ((int) $prior['assignment_id'] !== $id || (int) $prior['actor_user_id'] !== $actor || ($saved['status'] ?? '') !== $target || ($saved['actor_context'] ?? '') !== $context) throw new BVMGR_Staffing_Failure('operation_reused');
            return array('ok' => true, 'noop' => true, 'assignment' => $row, 'audit_id' => (int) $prior['log_id']);
        }
        if (!isset($options['revision']) || (int) $options['revision'] !== (int) $row['revision']) throw new BVMGR_Staffing_Failure('stale_assignment');
        $allowed = array('proposed' => array('confirmed', 'declined', 'canceled'), 'confirmed' => array('canceled'), 'declined' => array('proposed'), 'canceled' => array('proposed'));
        if (!in_array($target, $allowed[$row['status']] ?? array(), true)) throw new BVMGR_Staffing_Failure('invalid_transition');
        if ($context === 'staff' && $row['status'] !== 'proposed') throw new BVMGR_Staffing_Failure('invalid_transition');
        $reason = substr(sanitize_textarea_field((string) ($options['reason'] ?? '')), 0, 500);
        if (($target === 'proposed' || ($target === 'canceled' && $row['status'] === 'confirmed')) && $reason === '') throw new BVMGR_Staffing_Failure('reason_required');
        $window = bvmgr_staffing_lifecycle_window($row);
        if ($target === 'canceled' && (int) ($window['end_ts'] ?? 0) <= time() && $reason === '') throw new BVMGR_Staffing_Failure('reason_required');
        if ($target !== 'canceled') {
            if ($row['slot_status'] !== 'active' || (int) ($window['end_ts'] ?? 0) <= time() || (int) ($window['start_ts'] ?? 0) <= 0 || (int) $window['end_ts'] <= (int) $window['start_ts']) throw new BVMGR_Staffing_Failure('assignment_expired');
            $date = (string) get_post_meta($plan, '_vms_event_date', true);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date) || $date < wp_date('Y-m-d')) throw new BVMGR_Staffing_Failure('assignment_expired');
            if ($context === 'staff' && !in_array(bvmgr_event_plan_get_status($plan, 'dashboard'), array('ready','published','tentative','confirmed'), true)) throw new BVMGR_Staffing_Failure('assignment_unavailable');
        }
        if (in_array($target, array('proposed','confirmed'), true)) {
            $candidate = bvmgr_staffing_staff_candidate_status_for_role((int) $row['staff_id'], (int) $row['role_id']);
            if (empty($candidate['eligible'])) throw new BVMGR_Staffing_Failure('staff_ineligible');
        }
        $overlaps = in_array($target, array('proposed', 'confirmed'), true) ? bvmgr_staffing_assignment_overlaps($row, $window, $target === 'confirmed') : array('hard' => array(), 'soft' => array());
        if ($target === 'confirmed' && $overlaps['hard']) throw new BVMGR_Staffing_Failure('confirmed_overlap');
        $duplicates = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM %i WHERE slot_id = %d AND staff_id = %d AND assignment_id <> %d AND status IN ('proposed','confirmed')", bvmgr_staffing_table_name('assignments'), $row['slot_id'], $row['staff_id'], $id));
        if (in_array($target, array('proposed','confirmed'), true) && $duplicates) throw new BVMGR_Staffing_Failure('duplicate_review_required');
        $after = array_merge($row, array('status' => $target, 'revision' => (int) $row['revision'] + 1, 'shift_start_ts' => $window['start_ts'] ?? $row['shift_start_ts'], 'shift_end_ts' => $window['end_ts'] ?? $row['shift_end_ts']));
        $changed = $wpdb->update(bvmgr_staffing_table_name('assignments'), array('status' => $target, 'revision' => $after['revision'],
            'shift_start_ts' => $after['shift_start_ts'], 'shift_end_ts' => $after['shift_end_ts'],
            'updated_at' => bvmgr_staffing_now_mysql_utc(), 'updated_by' => $actor), array('assignment_id' => $id, 'revision' => $row['revision']));
        if ($changed !== 1) throw new BVMGR_Staffing_Failure('stale_assignment');
        bvmgr_staffing_lifecycle_record($row, $after, $context, $reason, $operation);
        return array('ok' => true, 'assignment' => $after, 'warnings' => $target === 'proposed' ? array_merge($overlaps['soft'], $overlaps['hard']) : $overlaps['soft']);
    });
}

/** Matrix membership creates proposals; lifecycle actions own all later changes. */
function bvmgr_staffing_matrix_proposals(int $slot_id, array $staff_ids): int
{
    global $wpdb;
    if (!bvmgr_staffing_transaction_active()) throw new BVMGR_Staffing_Failure('transaction_required');
    $staff_ids = array_values(array_unique(array_filter(array_map('absint', $staff_ids))));
    $slot = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE slot_id = %d', bvmgr_staffing_table_name('event_slots'), $slot_id), ARRAY_A);
    if (!$slot || !current_user_can('edit_post', (int) $slot['event_plan_id'])) throw new BVMGR_Staffing_Failure('forbidden');
    $table = bvmgr_staffing_table_name('assignments');
    $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM %i WHERE slot_id = %d ORDER BY assignment_id', $table, $slot_id), ARRAY_A);
    $existing = bvmgr_staffing_reconcile_existing_assignment_rows($rows)['primary_by_staff'];
    foreach ($rows as $row) {
        if (in_array($row['status'], array('proposed','confirmed'), true) && !in_array((int) $row['staff_id'], $staff_ids, true)) throw new BVMGR_Staffing_Failure('explicit_cancellation_required');
    }
    foreach ($staff_ids as $staff_id) {
        $active_count = count(array_filter($rows, static function ($r) use ($staff_id): bool { return (int) $r['staff_id'] === $staff_id && in_array($r['status'], array('proposed', 'confirmed'), true); }));
        if ($active_count > 1) throw new BVMGR_Staffing_Failure('duplicate_review_required');
        if (isset($existing[$staff_id])) {
            // A stale selected checkbox never revives terminal history.
            continue;
        }
        if ($slot['status'] !== 'active') throw new BVMGR_Staffing_Failure('assignment_unavailable');
        if (get_post_type($staff_id) !== 'vms_staff' || empty(bvmgr_staffing_staff_candidate_status_for_role($staff_id, (int) $slot['role_id'])['eligible'])) throw new BVMGR_Staffing_Failure('staff_ineligible');
        $now = bvmgr_staffing_now_mysql_utc();
        $wpdb->insert($table, array('slot_id' => $slot_id, 'staff_id' => $staff_id, 'status' => 'proposed', 'revision' => 0,
            'created_at' => $now, 'updated_at' => $now, 'created_by' => get_current_user_id(), 'updated_by' => get_current_user_id()));
        $row = bvmgr_staffing_lifecycle_row((int) $wpdb->insert_id);
        bvmgr_staffing_lifecycle_record(array(), $row, 'operator', 'matrix_proposal', wp_generate_uuid4());
    }
    return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM %i WHERE slot_id = %d AND status IN ('proposed','confirmed')", $table, $slot_id));
}

function bvmgr_staffing_sync_lifecycle_window(int $slot_id): void
{
    global $wpdb;
    if (!bvmgr_staffing_transaction_active()) {
        $result = bvmgr_staffing_atomic(static function () use ($slot_id): array { bvmgr_staffing_sync_lifecycle_window($slot_id); return array('ok' => true); });
        if (empty($result['ok'])) throw new BVMGR_Staffing_Failure($result['error']);
        return;
    }
    $ids = $wpdb->get_col($wpdb->prepare("SELECT assignment_id FROM %i WHERE slot_id = %d AND status IN ('proposed','confirmed')", bvmgr_staffing_table_name('assignments'), $slot_id));
    foreach ($ids as $id) {
        $row = bvmgr_staffing_lifecycle_row((int) $id);
        $window = bvmgr_staffing_lifecycle_window($row);
        if ($row['status'] === 'confirmed') {
            if ((int) ($window['start_ts'] ?? 0) <= 0 || (int) ($window['end_ts'] ?? 0) <= (int) $window['start_ts']) throw new BVMGR_Staffing_Failure('invalid_confirmed_window');
            if (bvmgr_staffing_assignment_overlaps($row, $window, true)['hard']) throw new BVMGR_Staffing_Failure('confirmed_overlap');
        }
        if (($row['shift_start_ts'] === null ? null : (int) $row['shift_start_ts']) === ($window['start_ts'] ?? null) && ($row['shift_end_ts'] === null ? null : (int) $row['shift_end_ts']) === ($window['end_ts'] ?? null)) continue;
        $wpdb->update(bvmgr_staffing_table_name('assignments'), array('shift_start_ts' => $window['start_ts'] ?? null, 'shift_end_ts' => $window['end_ts'] ?? null,
            'revision' => (int) $row['revision'] + 1, 'updated_at' => bvmgr_staffing_now_mysql_utc(), 'updated_by' => get_current_user_id()), array('assignment_id' => $id));
        // Context edits invalidate old forms but are not lifecycle transitions.
        bvmgr_staffing_audit_log('assignment_window_change', (int) $row['event_plan_id'], $row, array('assignment_id' => (int) $id, 'revision' => (int) $row['revision'] + 1, 'window' => $window));
        bvmgr_staffing_lifecycle_dirty((int) $row['event_plan_id'], (int) $row['staff_id']);
    }
}

function bvmgr_staffing_event_overlap_warnings(int $plan_id, array $slots): array
{
    $warnings = array();
    foreach ($slots as $slot) {
        if (($slot['status'] ?? '') !== 'active') continue;
        foreach (($slot['assignments'] ?? array()) as $row) {
            if (!in_array($row['status'], array('proposed','confirmed'), true)) continue;
            $window = bvmgr_staffing_resolve_slot_window($plan_id, $slot);
            $overlap = bvmgr_staffing_assignment_overlaps($row, $window);
            $soft = $row['status'] === 'proposed' ? array_merge($overlap['soft'], $overlap['hard']) : $overlap['soft'];
            if ($soft) $warnings[] = array('assignment_id' => (int) $row['assignment_id'], 'overlapping_assignment_ids' => $soft);
        }
    }
    return $warnings;
}

/** Event-time metadata must not race a staffing commitment. */
function bvmgr_staffing_guard_time_metadata($check, $id, $key, $value, $previous)
{
    return bvmgr_staffing_guard_time_mutation($check, (int) $id, (string) $key, static function () use ($id, $key, $value, $previous) { return update_post_meta($id, $key, $value, $previous); });
}
add_filter('update_post_metadata', 'bvmgr_staffing_guard_time_metadata', 20, 5);

function bvmgr_staffing_guard_time_add($check, $id, $key, $value, $unique)
{
    return bvmgr_staffing_guard_time_mutation($check, (int) $id, (string) $key, static function () use ($id, $key, $value, $unique) { return add_post_meta($id, $key, $value, $unique); });
}
function bvmgr_staffing_guard_time_delete($check, $id, $key, $value, $all)
{
    if ($all && in_array($key, array('_vms_event_date','_vms_start_time','_vms_end_time','_vms_event_a1_time','_vms_event_a2_time','_vms_event_a3_time','_vms_event_a4_time'), true)) return false;
    return bvmgr_staffing_guard_time_mutation($check, (int) $id, (string) $key, static function () use ($id, $key, $value) { return delete_post_meta($id, $key, $value); });
}
function bvmgr_staffing_guard_time_mutation($check, int $id, string $key, callable $mutation)
{
    $keys = array('_vms_event_date','_vms_start_time','_vms_end_time','_vms_event_a1_time','_vms_event_a2_time','_vms_event_a3_time','_vms_event_a4_time');
    if ($check !== null || !in_array($key, $keys, true) || get_post_type($id) !== 'vms_event_plan' || bvmgr_staffing_transaction_active()) return $check;
    $result = bvmgr_staffing_atomic(static function () use ($id, $mutation): array {
        $GLOBALS['bvmgr_staffing_transaction']['plans'][] = $id;
        $changed = $mutation();
        foreach (bvmgr_staffing_get_event_slots($id) as $slot) bvmgr_staffing_sync_lifecycle_window((int) $slot['slot_id']);
        return array('ok' => true, 'meta_result' => $changed);
    });
    if (empty($result['ok']) && function_exists('bvmgr_add_admin_notice')) bvmgr_add_admin_notice(bvmgr_staffing_lifecycle_message($result['error']), 'error');
    return !empty($result['ok']) ? $result['meta_result'] : false;
}
add_filter('add_post_metadata', 'bvmgr_staffing_guard_time_add', 20, 5);
add_filter('delete_post_metadata', 'bvmgr_staffing_guard_time_delete', 20, 5);

function bvmgr_staffing_guard_time_update_by_mid($check, $mid, $value, $key)
{
    if ($check !== null || bvmgr_staffing_transaction_active()) return $check;
    $meta = get_metadata_by_mid('post', $mid);
    if (!$meta) return $check;
    $target = $key === false ? $meta->meta_key : (string) $key;
    $mutation = static function () use ($mid, $value, $key) { return update_metadata_by_mid('post', $mid, $value, $key); };
    $result = bvmgr_staffing_guard_time_mutation($check, (int) $meta->post_id, $meta->meta_key, $mutation);
    return $result !== null ? $result : bvmgr_staffing_guard_time_mutation($check, (int) $meta->post_id, $target, $mutation);
}
function bvmgr_staffing_guard_time_delete_by_mid($check, $mid)
{
    if ($check !== null || bvmgr_staffing_transaction_active()) return $check;
    $meta = get_metadata_by_mid('post', $mid);
    if (!$meta) return $check;
    return bvmgr_staffing_guard_time_mutation($check, (int) $meta->post_id, $meta->meta_key, static function () use ($mid) { return delete_metadata_by_mid('post', $mid); });
}
add_filter('update_post_metadata_by_mid', 'bvmgr_staffing_guard_time_update_by_mid', 20, 4);
add_filter('delete_post_metadata_by_mid', 'bvmgr_staffing_guard_time_delete_by_mid', 20, 2);
