<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);

$GLOBALS['t1a_staffing_filters'] = array();

final class WP_User
{
	public int $ID;

	public function __construct(int $id)
	{
		$this->ID = $id;
	}
}

function t1a_staffing_assert(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function t1a_staffing_same($expected, $actual, string $message): void
{
	t1a_staffing_assert($expected === $actual, $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
}

function absint($value): int
{
	return abs((int) $value);
}

function sanitize_key(string $value): string
{
	return preg_replace('/[^a-z0-9_-]/', '', strtolower($value)) ?? '';
}

function sanitize_text_field(string $value): string
{
	return trim(strip_tags($value));
}

function add_filter(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): void
{
	$GLOBALS['t1a_staffing_filters'][$hook][$priority][] = array($callback, $accepted_args);
}

function apply_filters(string $hook, $value, ...$args)
{
	$callbacks = $GLOBALS['t1a_staffing_filters'][$hook] ?? array();
	ksort($callbacks);
	foreach ($callbacks as $rows) {
		foreach ($rows as [$callback, $accepted_args]) {
			$value = $callback(...array_slice(array_merge(array($value), $args), 0, $accepted_args));
		}
	}
	return $value;
}

function get_post_type(int $post_id): string
{
	return $post_id === 501 ? 'vms_event_plan' : 'post';
}

function bvmgr_operational_context_timestamp($value = null): ?DateTimeImmutable
{
	return is_string($value) ? new DateTimeImmutable($value, new DateTimeZone('UTC')) : null;
}

function bvmgr_staffing_role_map_by_id(bool $include_inactive = true): array
{
	unset($include_inactive);
	return array(
		7 => array('slug' => 'venue-operations', 'name' => 'Venue Operations'),
		8 => array('slug' => 'runner', 'name' => 'Runner'),
		9 => array('slug' => 'bar-lead', 'name' => 'Bar Lead'),
		10 => array('slug' => 'event-manager', 'name' => 'Event Manager'),
	);
}

function bvmgr_staffing_get_event_slots(int $event_plan_id, bool $include_canceled = false): array
{
	unset($event_plan_id, $include_canceled);
	return array(
		array(
			'slot_id' => 100,
			'role_id' => 7,
			'assignments' => array(
				array('assignment_id' => 1, 'staff_id' => 101, 'status' => 'confirmed'),
				array('assignment_id' => 2, 'staff_id' => 102, 'status' => 'checked_in'),
				array('assignment_id' => 3, 'staff_id' => 103, 'status' => 'proposed'),
				array('assignment_id' => 4, 'staff_id' => 104, 'status' => 'confirmed'),
			),
		),
		array(
			'slot_id' => 101,
			'role_id' => 7,
			'assignments' => array(
				array('assignment_id' => 5, 'staff_id' => 105, 'status' => 'confirmed'),
			),
		),
		array(
			'slot_id' => 102,
			'role_id' => 8,
			'assignments' => array(array('assignment_id' => 6, 'staff_id' => 106, 'status' => 'confirmed')),
		),
		array(
			'slot_id' => 103,
			'role_id' => 10,
			'assignments' => array(array('assignment_id' => 7, 'staff_id' => 107, 'status' => 'confirmed')),
		),
	);
}

function bvmgr_staffing_resolve_slot_window(int $event_plan_id, array $slot): array
{
	unset($event_plan_id);
	$start = new DateTimeImmutable('2026-09-18 18:00:00', new DateTimeZone('UTC'));
	$end = (int) ($slot['slot_id'] ?? 0) === 103
		? $start
		: new DateTimeImmutable('2026-09-18 22:00:00', new DateTimeZone('UTC'));
	return array('start_ts' => $start->getTimestamp(), 'end_ts' => $end->getTimestamp(), 'start_local' => $start, 'end_local' => $end);
}

function bvmgr_staffing_get_staff_user(int $staff_id): ?WP_User
{
	$map = array(101 => 11, 102 => 12, 103 => 13, 105 => 11, 106 => 14, 107 => 15);
	return isset($map[$staff_id]) ? new WP_User($map[$staff_id]) : null;
}

require dirname(__DIR__) . '/includes/core/staffing-dispatch.php';

$resolved = bvmgr_staffing_resolve_dispatch_assignments(501, 7);
t1a_staffing_same('venue-operations', $resolved['role_slug'], 'Role slug did not resolve from the staffing taxonomy.');
t1a_staffing_same(5, count($resolved['assignments']), 'All matching role assignments must remain visible.');
t1a_staffing_same(3, count($resolved['eligible_assignments']), 'Confirmed and checked-in linked assignments should be eligible.');
t1a_staffing_same(array(11, 12), $resolved['eligible_user_ids'], 'Eligible users must be unique while assignments remain complete.');
t1a_staffing_assert(in_array('assignment_state_not_dispatchable', $resolved['warning_codes'], true), 'Proposed assignment warning is missing.');
t1a_staffing_assert(in_array('staff_user_not_linked', $resolved['warning_codes'], true), 'Unlinked staff warning is missing.');

$by_id = array_column($resolved['assignments'], null, 'assignment_id');
t1a_staffing_same(false, $by_id[3]['dispatch_eligible'], 'Proposed assignment became dispatchable by default.');
t1a_staffing_same(0, $by_id[4]['user_id'], 'Unlinked staff must retain a zero user ID.');
t1a_staffing_same('staff_user_not_linked', $by_id[4]['ineligibility_reason'], 'Unlinked staff reason changed.');
t1a_staffing_same(true, $by_id[1]['shift_window_valid'], 'Resolved shift validity is missing.');
t1a_staffing_same('2026-09-18 18:00:00', $by_id[1]['shift_start_local'], 'Resolved shift start is missing.');

add_filter('bvmgr_staffing_dispatch_statuses', static function (array $statuses): array {
	$statuses[] = 'proposed';
	return $statuses;
});
$with_proposed = bvmgr_staffing_resolve_dispatch_assignments(501, 7);
t1a_staffing_same(4, count($with_proposed['eligible_assignments']), 'Dispatch status extension point did not add proposed assignments.');
t1a_staffing_same(array(11, 12, 13), $with_proposed['eligible_user_ids'], 'Proposed linked user was not added after explicit policy extension.');

$outside_shift = bvmgr_staffing_resolve_dispatch_assignments(501, 7, array('at' => '2026-09-18 17:00:00', 'require_active_shift' => true));
t1a_staffing_same(0, count($outside_shift['eligible_assignments']), 'Assignments outside their resolved shift must not dispatch when required.');
t1a_staffing_assert(in_array('outside_resolved_shift', $outside_shift['warning_codes'], true), 'Outside-shift warning is missing.');

$zero = bvmgr_staffing_resolve_dispatch_assignments(501, 9);
t1a_staffing_same(array(), $zero['assignments'], 'Unknown role unexpectedly resolved assignments.');
t1a_staffing_assert(in_array('no_role_assignments', $zero['warning_codes'], true), 'Zero-assignment role warning is missing.');

$one = bvmgr_staffing_resolve_dispatch_assignments(501, 8);
t1a_staffing_same(1, count($one['eligible_assignments']), 'Single-assignment role did not resolve exactly one eligible responder.');

$invalid_plan = bvmgr_staffing_resolve_dispatch_assignments(999, 7);
t1a_staffing_assert(in_array('invalid_dispatch_context', $invalid_plan['warning_codes'], true), 'Non-Event-Plan dispatch context must fail closed.');

$invalid_shift = bvmgr_staffing_resolve_dispatch_assignments(501, 10, array('at' => '2026-09-18 18:30:00', 'require_active_shift' => true));
t1a_staffing_same(false, $invalid_shift['assignments'][0]['shift_window_valid'], 'Invalid resolved shift window must be explicit.');
t1a_staffing_same('shift_window_unresolved', $invalid_shift['assignments'][0]['ineligibility_reason'], 'Invalid resolved shift reason changed.');
t1a_staffing_assert(in_array('shift_window_unresolved', $invalid_shift['warning_codes'], true), 'Invalid resolved shift warning is missing.');

add_filter('bvmgr_staffing_dispatch_result', static function (array $result): array {
	$result['eligible_assignments'] = array();
	$result['consumer_marker'] = 'present';
	return $result;
});
$guarded = bvmgr_staffing_resolve_dispatch_assignments(501, 8);
t1a_staffing_same(1, count($guarded['eligible_assignments']), 'Final dispatch filter must not replace canonical eligibility.');
t1a_staffing_same('present', $guarded['consumer_marker'], 'Final dispatch filter should retain non-canonical consumer data.');

echo "Tranche 1A staffing-dispatch tests passed.\n";
