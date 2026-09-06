<?php
declare(strict_types=1);

/**
 * Characterization of checkpoint gaps, NOT lifecycle acceptance tests.
 * Runs actual extracted repository functions against an in-memory wpdb double.
 * No WordPress bootstrap, database connection, filesystem mutation, or delivery.
 * Replace gap expectations with safety invariants when implementing the service.
 */
define('ARRAY_A', 'ARRAY_A');

function lifecycle_extract(string $source, string $name): string
{
    $start = strpos($source, 'function ' . $name . '(');
    if ($start === false) throw new RuntimeException('Missing function: ' . $name);
    $tokens = token_get_all('<?php ' . substr($source, $start));
    $body = '';
    $depth = 0;
    $opened = false;
    foreach (array_slice($tokens, 1) as $token) {
        $body .= is_array($token) ? $token[1] : $token;
        if ($token === '{') { $depth++; $opened = true; }
        if ($token === '}') $depth--;
        if ($opened && $depth === 0) return $body;
    }
    throw new RuntimeException('Unclosed function: ' . $name);
}

function absint($value): int { return abs((int) $value); }
function sanitize_key($value): string { return strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $value)); }
function wp_json_encode($value): string { return json_encode($value, JSON_THROW_ON_ERROR); }
function get_current_user_id(): int { return 99; }
function current_time($type, $gmt): string { return '2026-09-06 16:00:00'; }
function bvmgr_staffing_table_name(string $kind): string { return $kind; }
function bvmgr_staffing_role_map_by_id(bool $all): array { return array(10 => array('default_headcount' => 1)); }
function bvmgr_staffing_sync_assignment_shift_timestamps_for_slot(int $slot): void {}
function bvmgr_staffing_build_legacy_staff_assignments_from_slots(int $plan): array { return array(); }
function delete_post_meta($id, $key): void {}
function update_post_meta($id, $key, $value): void {}
function bvmgr_staffing_mark_rollup_dirty(int $plan, string $reason): void {}
function bvmgr_staffing_compute_rollup(int $plan): array { return array(); }
function bvmgr_staffing_get_event_slots(int $plan, bool $all): array { return array(); }
function do_action($hook, ...$args): void {}

final class Lifecycle_Checkpoint_DB
{
    public array $rows = array();
    public array $audit = array();
    public int $insert_id = 0;
    public int $writes = 0;
    public bool $fail_audit = false;
    public $after_read = null;

    public function prepare($sql, ...$args): array { return array($sql, $args); }
    public function get_results(array $query, $format): array
    {
        if ($query[1][0] === 'event_slots') {
            return array(array('slot_id' => 1, 'role_id' => 10, 'status' => 'active'));
        }
        if ($query[1][0] !== 'assignments') throw new RuntimeException('Unexpected read');
        $snapshot = array_values($this->rows);
        if ($this->after_read !== null) {
            $callback = $this->after_read;
            $this->after_read = null;
            $callback($this);
        }
        return $snapshot;
    }
    public function update($table, $data, $where, ...$formats): int
    {
        $this->writes++;
        if ($table === 'event_slots') return 1;
        if ($table !== 'assignments') throw new RuntimeException('Unexpected update');
        foreach ($this->rows as &$row) {
            foreach ($where as $key => $value) {
                if (($row[$key] ?? null) !== $value) continue 2;
            }
            $row = array_merge($row, $data);
            return 1;
        }
        return 0;
    }
    public function insert($table, $data, ...$formats)
    {
        $this->writes++;
        if ($table === 'audit') {
            if ($this->fail_audit) return false;
            $this->audit[] = $data;
            return 1;
        }
        if ($table !== 'assignments') throw new RuntimeException('Unexpected insert');
        $this->insert_id = empty($this->rows) ? 1 : max(array_keys($this->rows)) + 1;
        $this->rows[$this->insert_id] = array_merge($data, array('assignment_id' => $this->insert_id));
        return 1;
    }
}

$source = file_get_contents(dirname(__DIR__) . '/includes/core/staffing.php');
foreach (array('bvmgr_staffing_now_mysql_utc', 'bvmgr_staffing_audit_log',
    'bvmgr_staffing_reconcile_existing_assignment_rows', 'bvmgr_staffing_save_event_roles_matrix') as $name) {
    eval(lifecycle_extract($source, $name));
}

$checks = 0;
function lifecycle_check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
function lifecycle_row(string $status): array
{
    return array('assignment_id' => 1, 'slot_id' => 1, 'staff_id' => 7, 'status' => $status);
}
function lifecycle_save(array $staff = array(7), bool $noop = false): array
{
    // A real matrix change (for example another headcount field) bypasses its
    // signature no-op guard. Assignment membership stays the same in most cases.
    return bvmgr_staffing_save_event_roles_matrix(100, array(10 => 2), array(10 => $staff),
        array(), array(), array(), array(), array(), array(), array(), array(), 99,
        array('before_slots' => array(), 'current_signature' => array(),
            'desired_signature' => $noop ? array() : array('changed')));
}

$wpdb = new Lifecycle_Checkpoint_DB();
$result = lifecycle_save();
lifecycle_check($result['ok'] && $wpdb->rows[1]['status'] === 'proposed', 'Proposal creation control');
lifecycle_check(count($wpdb->audit) === 1 && $wpdb->audit[0]['action'] === 'event_staffing_save', 'Batch audit control');

$wpdb = new Lifecycle_Checkpoint_DB();
$wpdb->rows[1] = lifecycle_row('confirmed');
lifecycle_save();
lifecycle_check($wpdb->rows[1]['status'] === 'confirmed', 'Accepted P0 sequential Confirmed retention');

$wpdb = new Lifecycle_Checkpoint_DB();
$result = lifecycle_save(array(7), true);
lifecycle_check(!empty($result['noop']) && $wpdb->writes === 0, 'Unchanged matrix control');

foreach (array('confirmed', 'declined', 'canceled') as $concurrent_status) {
    $wpdb = new Lifecycle_Checkpoint_DB();
    $wpdb->rows[1] = lifecycle_row('proposed');
    $wpdb->after_read = static function ($db) use ($concurrent_status): void {
        $db->rows[1]['status'] = $concurrent_status;
    };
    lifecycle_save();
    lifecycle_check($wpdb->rows[1]['status'] === 'proposed', 'Checkpoint stale read overwrites ' . $concurrent_status);
}

$wpdb = new Lifecycle_Checkpoint_DB();
$wpdb->rows[1] = lifecycle_row('declined');
lifecycle_save(array());
lifecycle_check($wpdb->rows[1]['status'] === 'canceled', 'Checkpoint unrelated matrix change overwrites Declined history');

$wpdb = new Lifecycle_Checkpoint_DB();
$wpdb->after_read = static function ($db): void { $db->rows[1] = lifecycle_row('proposed'); };
lifecycle_save();
lifecycle_check(count($wpdb->rows) === 2, 'Checkpoint read/insert interleaving permits duplicate association');

$wpdb = new Lifecycle_Checkpoint_DB();
$wpdb->fail_audit = true;
$result = lifecycle_save();
lifecycle_check($result['ok'] && count($wpdb->rows) === 1 && $wpdb->audit === array(), 'Checkpoint succeeds after failed audit insert');

// Model ONLY: no confirmation service exists at this checkpoint. Even adding
// per-assignment compare-and-set cannot serialize confirmations of DIFFERENT rows.
$rows = array(1 => 'proposed', 2 => 'proposed');
$a_saw_clear = !in_array('confirmed', $rows, true);
$b_saw_clear = !in_array('confirmed', $rows, true);
if ($a_saw_clear && $rows[1] === 'proposed') $rows[1] = 'confirmed';
if ($b_saw_clear && $rows[2] === 'proposed') $rows[2] = 'confirmed';
lifecycle_check($rows === array(1 => 'confirmed', 2 => 'confirmed'), 'Independent compare-and-set does not protect a shared staff schedule');

echo "PASS: {$checks} checkpoint characterization assertions; known gaps reproduced, NOT lifecycle acceptance.\n";
