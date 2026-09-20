<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);
define('DAY_IN_SECONDS', 86400);

$GLOBALS['overnight_meta'] = array(
	501 => array(
		'_vms_event_date' => '2026-09-19',
		'_vms_start_time' => '19:00',
		'_vms_end_time' => '01:00',
	),
	502 => array(
		'_vms_event_date' => '2026-09-19',
		'_vms_start_time' => '18:00',
		'_vms_end_time' => '22:00',
	),
	503 => array(
		'_vms_event_date' => '2026-09-19',
		'_vms_start_time' => '22:00',
		'_vms_end_time' => '22:00',
	),
	504 => array(
		'_vms_start_time' => '19:00',
		'_vms_end_time' => '01:00',
	),
);

final class WP_User
{
	public int $ID;

	public function __construct(int $id)
	{
		$this->ID = $id;
	}
}

function overnight_assert(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function overnight_same($expected, $actual, string $message): void
{
	overnight_assert(
		$expected === $actual,
		$message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true)
	);
}

function overnight_local(?DateTimeInterface $value): ?string
{
	return $value instanceof DateTimeInterface ? $value->format('Y-m-d H:i:s') : null;
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

function wp_timezone(): DateTimeZone
{
	return new DateTimeZone('America/Chicago');
}

function get_post_meta(int $post_id, string $key, bool $single = true)
{
	unset($single);
	return $GLOBALS['overnight_meta'][$post_id][$key] ?? '';
}

function get_post_type(int $post_id): string
{
	return isset($GLOBALS['overnight_meta'][$post_id]) ? 'vms_event_plan' : 'post';
}

function get_current_user_id(): int
{
	return 0;
}

function add_action(...$args): void
{
	unset($args);
}

function add_filter(...$args): void
{
	unset($args);
}

function apply_filters(string $hook, $value, ...$args)
{
	unset($hook, $args);
	return $value;
}

function bvmgr_staffing_role_map_by_id(bool $include_inactive = true): array
{
	unset($include_inactive);
	return array(
		7 => array('slug' => 'overnight-ops', 'name' => 'Overnight Operations'),
		8 => array('slug' => 'equal-window', 'name' => 'Equal Window'),
		9 => array('slug' => 'malformed-window', 'name' => 'Malformed Window'),
		10 => array('slug' => 'missing-anchor', 'name' => 'Missing Anchor'),
	);
}

function bvmgr_staffing_get_event_slots(int $event_plan_id, bool $include_canceled = false): array
{
	unset($include_canceled);
	if ($event_plan_id !== 501) {
		return array();
	}

	return array(
		array(
			'slot_id' => 100,
			'role_id' => 7,
			'shift_time_mode' => 'absolute',
			'shift_start_local' => '22:00',
			'shift_end_local' => '02:00',
			'duration_minutes' => null,
			'assignments' => array(
				array('assignment_id' => 1, 'staff_id' => 101, 'status' => 'confirmed'),
				array('assignment_id' => 2, 'staff_id' => 102, 'status' => 'checked_in'),
				array('assignment_id' => 3, 'staff_id' => 103, 'status' => 'proposed'),
				array('assignment_id' => 4, 'staff_id' => 104, 'status' => 'confirmed'),
			),
		),
		array(
			'slot_id' => 101,
			'role_id' => 8,
			'shift_time_mode' => 'absolute',
			'shift_start_local' => '22:00',
			'shift_end_local' => '22:00',
			'duration_minutes' => null,
			'assignments' => array(array('assignment_id' => 5, 'staff_id' => 101, 'status' => 'confirmed')),
		),
		array(
			'slot_id' => 102,
			'role_id' => 9,
			'shift_time_mode' => 'absolute',
			'shift_start_local' => 'not-a-time',
			'shift_end_local' => '02:00',
			'duration_minutes' => null,
			'assignments' => array(array('assignment_id' => 6, 'staff_id' => 101, 'status' => 'confirmed')),
		),
		array(
			'slot_id' => 103,
			'role_id' => 10,
			'shift_time_mode' => 'relative',
			'start_anchor_key' => 'a1',
			'start_offset_minutes' => 0,
			'duration_minutes' => 60,
			'assignments' => array(array('assignment_id' => 7, 'staff_id' => 101, 'status' => 'confirmed')),
		),
	);
}

function bvmgr_staffing_get_staff_user(int $staff_id): ?WP_User
{
	$map = array(101 => 11, 102 => 12, 103 => 13);
	return isset($map[$staff_id]) ? new WP_User($map[$staff_id]) : null;
}

require dirname(__DIR__) . '/includes/helpers/checkin-close.php';
require dirname(__DIR__) . '/includes/core/event-reschedule.php';
require dirname(__DIR__) . '/includes/core/operational-context.php';
require dirname(__DIR__) . '/includes/core/staffing.php';
require dirname(__DIR__) . '/includes/core/staffing-dispatch.php';

$same_day = bvmgr_event_occurrence_from_parts('2026-09-19', '18:00', '22:00');
overnight_assert($same_day['valid'] === true, 'Same-day Event Plan occurrence became invalid.');
overnight_same('2026-09-19 22:00:00', overnight_local($same_day['end']), 'Same-day Event Plan end changed.');

$overnight_occurrence = bvmgr_event_occurrence_from_parts('2026-09-19', '19:00', '01:00');
overnight_assert($overnight_occurrence['valid'] === true, 'Overnight Event Plan occurrence did not resolve.');
overnight_same('2026-09-20 01:00:00', overnight_local($overnight_occurrence['end']), 'Overnight Event Plan end did not advance one day.');
overnight_assert(bvmgr_event_occurrence_from_parts('2026-09-19', '22:00', '22:00')['valid'] === false, 'Equal Event Plan times must remain invalid.');
overnight_same('2026-09-20 01:00:00', overnight_local(bvmgr_event_plan_end_datetime(501)), 'Event Plan end helper lost overnight normalization.');
overnight_same(null, bvmgr_event_plan_end_datetime(503), 'Event Plan end helper must reject equal start/end.');

$operational = bvmgr_event_plan_operational_window(501);
overnight_assert($operational['valid'] === true, 'Operational window did not inherit the overnight Event Plan occurrence.');
overnight_same('2026-09-20 01:00:00', $operational['end_local'], 'Operational window lost the next-day Event Plan end.');
overnight_same('incomplete_event_timing', bvmgr_event_plan_operational_window(504)['reason'], 'Missing Event Plan date must remain unresolved.');
overnight_same('invalid_event_window', bvmgr_event_plan_operational_window(503)['reason'], 'Equal Event Plan times must remain unresolved.');

$same_day_shift = bvmgr_staffing_resolve_slot_window(502, array(
	'shift_time_mode' => 'absolute',
	'shift_start_local' => '18:00',
	'shift_end_local' => '22:00',
));
overnight_same('2026-09-19 18:00:00', overnight_local($same_day_shift['start_local']), 'Same-day staffing start changed.');
overnight_same('2026-09-19 22:00:00', overnight_local($same_day_shift['end_local']), 'Same-day staffing end changed.');

$overnight_shift = bvmgr_staffing_resolve_slot_window(501, array(
	'shift_time_mode' => 'absolute',
	'shift_start_local' => '22:00',
	'shift_end_local' => '02:00',
));
overnight_same('2026-09-19 22:00:00', overnight_local($overnight_shift['start_local']), 'Overnight staffing start changed.');
overnight_same('2026-09-20 02:00:00', overnight_local($overnight_shift['end_local']), 'Overnight staffing end did not advance one day.');
overnight_same(240, $overnight_shift['duration_minutes'], 'Overnight staffing duration must be four hours.');

$equal_shift = bvmgr_staffing_resolve_slot_window(501, array(
	'shift_time_mode' => 'absolute',
	'shift_start_local' => '22:00',
	'shift_end_local' => '22:00',
));
overnight_same(0, $equal_shift['duration_minutes'], 'Equal staffing times must remain a zero-length unresolved window.');

$malformed_shift = bvmgr_staffing_resolve_slot_window(501, array(
	'shift_time_mode' => 'absolute',
	'shift_start_local' => 'not-a-time',
	'shift_end_local' => '02:00',
));
overnight_same(null, $malformed_shift['start_ts'], 'Malformed staffing time must not fall back to the Event Plan start.');

$before_start = bvmgr_staffing_resolve_slot_window(501, array(
	'shift_time_mode' => 'relative',
	'start_anchor_key' => 'event_start',
	'start_offset_minutes' => -120,
	'end_anchor_key' => 'event_start',
	'end_offset_minutes' => 0,
));
overnight_same('2026-09-19 17:00:00', overnight_local($before_start['start_local']), 'Relative pre-event start changed.');
overnight_same('2026-09-19 19:00:00', overnight_local($before_start['end_local']), 'Relative pre-event end changed.');

$after_end = bvmgr_staffing_resolve_slot_window(501, array(
	'shift_time_mode' => 'relative',
	'start_anchor_key' => 'event_end',
	'start_offset_minutes' => 0,
	'end_anchor_key' => 'event_end',
	'end_offset_minutes' => 120,
));
overnight_same('2026-09-20 01:00:00', overnight_local($after_end['start_local']), 'Relative post-event start lost the next-day boundary.');
overnight_same('2026-09-20 03:00:00', overnight_local($after_end['end_local']), 'Relative post-event end changed.');

$crossing = bvmgr_staffing_resolve_slot_window(501, array(
	'shift_time_mode' => 'relative',
	'start_anchor_key' => 'event_end',
	'start_offset_minutes' => -90,
	'end_anchor_key' => 'event_end',
	'end_offset_minutes' => 60,
));
overnight_same('2026-09-19 23:30:00', overnight_local($crossing['start_local']), 'Relative crossing start changed.');
overnight_same('2026-09-20 02:00:00', overnight_local($crossing['end_local']), 'Relative crossing end changed.');

$missing_anchor = bvmgr_staffing_resolve_slot_window(501, array(
	'shift_time_mode' => 'relative',
	'start_anchor_key' => 'a1',
	'duration_minutes' => 60,
));
overnight_same(null, $missing_anchor['start_ts'], 'Missing required anchor must remain unresolved.');

$expect_dispatch = static function (string $at, array $expected_users): array {
	$result = bvmgr_staffing_resolve_dispatch_assignments(501, 7, array(
		'at' => $at,
		'require_active_shift' => true,
	));
	overnight_same($expected_users, $result['eligible_user_ids'], 'Unexpected overnight dispatch users at ' . $at . '.');
	return $result;
};

$expect_dispatch('2026-09-19 21:59:00', array());
$at_start = $expect_dispatch('2026-09-19 22:00:00', array(11, 12));
$before_midnight = $expect_dispatch('2026-09-19 23:00:00', array(11, 12));
$after_midnight = $expect_dispatch('2026-09-20 01:30:00', array(11, 12));
$expect_dispatch('2026-09-20 02:00:00', array());
$expect_dispatch('2026-09-20 02:01:00', array());

foreach (array($at_start, $before_midnight, $after_midnight) as $result) {
	overnight_assert(!in_array('shift_window_unresolved', $result['warning_codes'], true), 'Valid overnight dispatch emitted shift_window_unresolved.');
}

$assignment_rows = array_column($before_midnight['assignments'], null, 'assignment_id');
overnight_same('assignment_state_not_dispatchable', $assignment_rows[3]['ineligibility_reason'], 'Proposed assignment dispatch behavior changed.');
overnight_same('staff_user_not_linked', $assignment_rows[4]['ineligibility_reason'], 'Unlinked staff dispatch behavior changed.');

foreach (array(8, 9, 10) as $invalid_role_id) {
	$invalid = bvmgr_staffing_resolve_dispatch_assignments(501, $invalid_role_id, array(
		'at' => '2026-09-19 23:00:00',
		'require_active_shift' => true,
	));
	overnight_same(array(), $invalid['eligible_user_ids'], 'Invalid timing unexpectedly dispatched role ' . $invalid_role_id . '.');
	overnight_assert(in_array('shift_window_unresolved', $invalid['warning_codes'], true), 'Invalid timing warning missing for role ' . $invalid_role_id . '.');
}

echo "Core overnight staffing-window regression tests passed.\n";
