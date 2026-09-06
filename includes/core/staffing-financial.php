<?php
/** Current staffing cost estimates; neither commitments nor plans prove payroll actuals. */
defined('ABSPATH') || exit;

function bvmgr_staffing_financial_unavailable(string $reason): array
{
    return array('availability' => 'unavailable', 'source' => 'normalized_staffing_lifecycle',
        'planned_cents' => null, 'committed_cents' => null, 'proposed_cents' => null, 'actual_cents' => null,
        'basis' => array('planned' => 'planned_slot_headcount', 'committed' => 'confirmed_assignment_estimate',
            'proposed' => 'proposed_assignment_estimate', 'actual' => 'unavailable'),
        'evidence' => array('reason' => $reason, 'authority' => 'unavailable'));
}

/** Pure projection of a coherent repository read. Rounding occurs per slot/person. */
function bvmgr_staffing_build_financial_labor(array $slots, array $roles, array $windows, bool $legacy_present = false): array
{
    $out = bvmgr_staffing_financial_unavailable('');
    if (!$slots && $legacy_present) return bvmgr_staffing_financial_unavailable('legacy_staffing_has_no_lifecycle_cost_authority');
    $out['availability'] = 'available';
    $out['planned_cents'] = $out['committed_cents'] = $out['proposed_cents'] = 0;
    $out['evidence'] = array('reason' => '', 'authority' => $slots ? 'normalized' : 'empty',
        'legacy_ignored' => $slots && $legacy_present, 'planned_headcount' => 0, 'proposed_headcount' => 0,
        'confirmed_headcount' => 0, 'excluded_assignment_count' => 0, 'assignment_revisions' => array(),
        'rate_precedence' => 'assignment_override_then_slot_then_role', 'actual_reason' => 'no_verified_payroll_authority');
    foreach ($slots as $slot) {
        if (($slot['status'] ?? 'active') !== 'active') continue;
        $role = $roles[(int) ($slot['role_id'] ?? 0)] ?? array();
        $window = $windows[(int) ($slot['slot_id'] ?? 0)] ?? array();
        $need = max(0, (int) ($slot['headcount_needed'] ?? 0));
        $planned = bvmgr_staffing_financial_estimate($slot, $role, $window, $need);
        if ($planned === null) $out['planned_cents'] = null;
        elseif ($out['planned_cents'] !== null) $out['planned_cents'] += $planned;
        $out['evidence']['planned_headcount'] += $need;
        $seen = array();
        foreach (($slot['assignments'] ?? array()) as $assignment) {
            $status = (string) ($assignment['status'] ?? '');
            if (in_array($status, array('declined', 'canceled'), true)) {
                $out['evidence']['excluded_assignment_count']++;
                continue;
            }
            if (!in_array($status, array('proposed', 'confirmed'), true)) return bvmgr_staffing_financial_unavailable('unknown_assignment_status');
            $staff = (int) ($assignment['staff_id'] ?? 0);
            if ($staff <= 0 || isset($seen[$staff])) return bvmgr_staffing_financial_unavailable('duplicate_or_invalid_active_assignment');
            $seen[$staff] = true;
            $key = $status === 'confirmed' ? 'committed_cents' : 'proposed_cents';
            $out['evidence'][$status . '_headcount']++;
            $out['evidence']['assignment_revisions'][(int) $assignment['assignment_id']] = (int) ($assignment['revision'] ?? 0);
            $priced = $slot;
            if (isset($assignment['pay_type_override']) && $assignment['pay_type_override'] !== '') {
                $priced['pay_type'] = $assignment['pay_type_override'];
            }
            if (isset($assignment['pay_rate_override']) && $assignment['pay_rate_override'] !== '') {
                $priced['pay_rate'] = $assignment['pay_rate_override'];
            }
            $cost = bvmgr_staffing_financial_estimate($priced, $role, $window, 1);
            if ($cost === null) $out[$key] = null;
            elseif ($out[$key] !== null) $out[$key] += $cost;
        }
    }
    if ($out['planned_cents'] === null || $out['committed_cents'] === null || $out['proposed_cents'] === null) {
        $out['evidence']['reason'] = 'rate_or_window_unavailable';
    }
    $out['evidence']['fingerprint'] = hash('sha256', json_encode(array($slots, $roles, $windows)));
    return $out;
}

/** Same planned-headcount/rate/window model as the canonical slot estimator. */
function bvmgr_staffing_financial_estimate(array $slot, array $role, array $window, int $count): ?int
{
    if ($count === 0) return 0;
    $type = (string) ($slot['pay_type'] ?? 'inherit_role');
    if ($type === 'inherit_role') $type = (string) ($role['default_pay_type'] ?? '');
    if ($type === 'none') return 0;
    if (!in_array($type, array('flat', 'hourly'), true)) return null;
    $rate = $slot['pay_rate'] ?? null;
    if ($rate === '' || $rate === null) $rate = $role['default_rate'] ?? null;
    if (!is_numeric($rate) || !is_finite((float) $rate) || (float) $rate < 0) return null;
    $hours = 1.0;
    if ($type === 'hourly') {
        $minutes = $window['duration_minutes'] ?? null;
        if (!is_numeric($minutes) || (float) $minutes <= 0) return null;
        $hours = (float) $minutes / 60;
    }
    $cents = (float) $rate * $hours * $count * 100;
    return is_finite($cents) && $cents <= PHP_INT_MAX ? (int) round($cents) : null;
}

/**
 * A read-only repeatable snapshot plus the lifecycle writer lock. Never reads or
 * recomputes stored rollups. Stock wpdb's reconnect/replay is disabled here too.
 */
function bvmgr_staffing_get_financial_labor(int $plan_id): array
{
    global $wpdb;
    if ($plan_id <= 0 || !class_exists('BVMGR_Staffing_Transaction_DB') || !isset($wpdb) || get_class($wpdb) !== 'wpdb') {
        return bvmgr_staffing_financial_unavailable('staffing_authority_unavailable');
    }
    if (bvmgr_staffing_transaction_active()) return bvmgr_staffing_financial_unavailable('staffing_write_in_progress');
    $original = $wpdb;
    $original->flush();
    $wpdb = new BVMGR_Staffing_Transaction_DB($original);
    $locked = $started = false;
    $metadata_filter = null;
    $lock = bvmgr_staffing_lock_name();
    try {
        if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lock)) !== 1) throw new BVMGR_Staffing_Failure('staffing_busy');
        $locked = true;
        if ((int) $wpdb->get_var('SELECT @@autocommit') !== 1) throw new BVMGR_Staffing_Failure('external_transaction');
        $wpdb->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        bvmgr_staffing_require_transaction_schema();
        foreach (array($wpdb->termmeta, $wpdb->term_taxonomy) as $table) {
            $engine = $wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $table));
            if (strtolower((string) $engine) !== 'innodb') throw new BVMGR_Staffing_Failure('transactional_engine_required');
        }
        $wpdb->query('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
        $started = true;
        $plan_type = $wpdb->get_var($wpdb->prepare('SELECT post_type FROM %i WHERE ID=%d', $wpdb->posts, $plan_id));
        if ($plan_type !== 'vms_event_plan') throw new BVMGR_Staffing_Failure('invalid_event_plan');
        $slots = $wpdb->get_results($wpdb->prepare('SELECT * FROM %i WHERE event_plan_id=%d ORDER BY slot_id', bvmgr_staffing_table_name('event_slots'), $plan_id), ARRAY_A);
        $assignments = $wpdb->get_results($wpdb->prepare('SELECT a.* FROM %i a INNER JOIN %i s ON s.slot_id=a.slot_id WHERE s.event_plan_id=%d ORDER BY a.assignment_id', bvmgr_staffing_table_name('assignments'), bvmgr_staffing_table_name('event_slots'), $plan_id), ARRAY_A);
        $orphans = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM %i a INNER JOIN %i s ON s.slot_id=a.slot_id LEFT JOIN %i p ON p.ID=a.staff_id AND p.post_type=%s WHERE s.event_plan_id=%d AND s.status='active' AND a.status IN ('proposed','confirmed') AND p.ID IS NULL", bvmgr_staffing_table_name('assignments'), bvmgr_staffing_table_name('event_slots'), $wpdb->posts, 'vms_staff', $plan_id));
        if ((int) $orphans > 0) throw new BVMGR_Staffing_Failure('staffing_identity_unavailable');
        $by_slot = array();
        foreach ($assignments as $row) $by_slot[(int) $row['slot_id']][] = $row;
        // Keep canonical window resolution on this SQL snapshot even when a
        // persistent metadata cache is populated concurrently by another request.
        $metadata = array();
        $meta_rows = $wpdb->get_results($wpdb->prepare('SELECT meta_key,meta_value FROM %i WHERE post_id=%d ORDER BY meta_id', $wpdb->postmeta, $plan_id), ARRAY_A);
        foreach ($meta_rows as $meta) $metadata[$meta['meta_key']][] = maybe_unserialize($meta['meta_value']);
        $metadata_filter = static function ($value, $id, $key, $single) use ($plan_id, $metadata) {
            if ((int) $id !== $plan_id) return $value;
            return $single ? array($metadata[$key][0] ?? '') : ($metadata[$key] ?? array());
        };
        add_filter('get_post_metadata', $metadata_filter, PHP_INT_MAX, 4);
        $legacy = (array) ($metadata['_vms_staff_assignments'][0] ?? array());
        $legacy_present = false;
        foreach ($legacy as $ids) if (is_array($ids) && array_filter(array_map('absint', $ids))) $legacy_present = true;
        $roles = $windows = array();
        foreach ($slots as &$slot) {
            $sid = (int) $slot['slot_id'];
            $rid = (int) $slot['role_id'];
            $slot['assignments'] = $by_slot[$sid] ?? array();
            if ($slot['status'] !== 'active') continue;
            // Read raw rate metadata: the UI getter normalizes invalid rates to zero.
            if (!isset($roles[$rid])) {
                $role_exists = $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE term_id=%d AND taxonomy=%s', $wpdb->term_taxonomy, $rid, 'vms_staff_role'));
                if ((int) $role_exists !== 1) throw new BVMGR_Staffing_Failure('staffing_role_unavailable');
                $raw_role = $wpdb->get_results($wpdb->prepare('SELECT meta_key,meta_value FROM %i WHERE term_id=%d ORDER BY meta_id', $wpdb->termmeta, $rid), ARRAY_A);
                $role_meta = array();
                foreach ($raw_role as $meta) if (!array_key_exists($meta['meta_key'], $role_meta)) $role_meta[$meta['meta_key']] = $meta['meta_value'];
                $roles[$rid] = array('default_pay_type' => $role_meta['_vms_staff_role_default_pay_type'] ?? '',
                    'default_rate' => $role_meta['_vms_staff_role_default_rate'] ?? null);
                if ($roles[$rid]['default_pay_type'] === '') $roles[$rid]['default_pay_type'] = 'none';
            }
            $windows[$sid] = bvmgr_staffing_lifecycle_window(array('event_plan_id' => $plan_id, 'slot_id' => $sid));
        }
        unset($slot);
        $out = bvmgr_staffing_build_financial_labor($slots, $roles, $windows, $legacy_present);
        if ((int) $wpdb->get_var($wpdb->prepare('SELECT IS_USED_LOCK(%s)=CONNECTION_ID()', $lock)) !== 1) throw new BVMGR_Staffing_Failure('staffing_lock_lost');
        $wpdb->query('ROLLBACK'); // End a read-only snapshot without a write commit.
        $started = false;
        return $out;
    } catch (Throwable $error) {
        return bvmgr_staffing_financial_unavailable($error instanceof BVMGR_Staffing_Failure ? $error->getMessage() : 'staffing_read_failed');
    } finally {
        if ($started) { try { $wpdb->query('ROLLBACK'); } catch (Throwable $ignored) {} }
        if ($locked) { try { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); } catch (Throwable $ignored) {} }
        if ($metadata_filter !== null) remove_filter('get_post_metadata', $metadata_filter, PHP_INT_MAX);
        $wpdb = $original;
    }
}
