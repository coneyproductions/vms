<?php
defined('ABSPATH') || exit;

/**
 * Resolve Event Plan staffing assignments for operational dispatch.
 */

if (!function_exists('bvmgr_staffing_dispatch_statuses')) {
	/** @return string[] */
	function bvmgr_staffing_dispatch_statuses(int $event_plan_id = 0, int $role_id = 0): array
	{
		$statuses = array('confirmed', 'checked_in');
		/**
		 * Filter staffing assignment states eligible for operational dispatch.
		 *
		 * @param string[] $statuses      Dispatchable assignment states.
		 * @param int      $event_plan_id Event Plan post ID.
		 * @param int      $role_id       Staffing role term ID.
		 */
		$filtered = apply_filters('bvmgr_staffing_dispatch_statuses', $statuses, $event_plan_id, $role_id);
		return array_values(array_unique(array_filter(array_map('sanitize_key', is_array($filtered) ? $filtered : $statuses))));
	}
}

if (!function_exists('bvmgr_staffing_dispatch_role')) {
	/** @return array<string,mixed> */
	function bvmgr_staffing_dispatch_role(int $role_id): array
	{
		$role_id = absint($role_id);
		$role = array('role_id' => $role_id, 'role_slug' => '', 'role_name' => '');
		$role_map_callback = function_exists('bvmgr_staffing_role_map_by_id')
			? 'bvmgr_staffing_role_map_by_id'
			: (function_exists('vms_staffing_role_map_by_id') ? 'vms_staffing_role_map_by_id' : '');
		if ($role_id <= 0 || $role_map_callback === '') {
			return $role;
		}

		$map = call_user_func($role_map_callback, true);
		$row = isset($map[$role_id]) && is_array($map[$role_id]) ? $map[$role_id] : array();
		$role['role_slug'] = sanitize_key((string) ($row['slug'] ?? ''));
		$role['role_name'] = sanitize_text_field((string) ($row['name'] ?? ''));
		return $role;
	}
}

if (!function_exists('bvmgr_staffing_resolve_dispatch_assignments')) {
	/**
	 * Resolve all staffing assignments for an Event Plan role.
	 *
	 * Rows without linked users remain in assignments with dispatch_eligible=false
	 * so dispatchers can see and correct the configuration gap.
	 *
	 * @return array<string,mixed>
	 */
	function bvmgr_staffing_resolve_dispatch_assignments(int $event_plan_id, int $role_id, array $args = array()): array
	{
		$event_plan_id = absint($event_plan_id);
		$role_id = absint($role_id);
		$role = bvmgr_staffing_dispatch_role($role_id);
		$result = array(
			'event_plan_id' => $event_plan_id,
			'role_id' => $role_id,
			'role_slug' => (string) $role['role_slug'],
			'role_name' => (string) $role['role_name'],
			'dispatchable_statuses' => bvmgr_staffing_dispatch_statuses($event_plan_id, $role_id),
			'assignments' => array(),
			'eligible_assignments' => array(),
			'eligible_user_ids' => array(),
			'warning_codes' => array(),
		);
		$get_slots_callback = function_exists('bvmgr_staffing_get_event_slots')
			? 'bvmgr_staffing_get_event_slots'
			: (function_exists('vms_staffing_get_event_slots') ? 'vms_staffing_get_event_slots' : '');
		$resolve_window_callback = function_exists('bvmgr_staffing_resolve_slot_window')
			? 'bvmgr_staffing_resolve_slot_window'
			: (function_exists('vms_staffing_resolve_slot_window') ? 'vms_staffing_resolve_slot_window' : '');
		$get_staff_user_callback = function_exists('bvmgr_staffing_get_staff_user')
			? 'bvmgr_staffing_get_staff_user'
			: (function_exists('vms_staffing_get_staff_user') ? 'vms_staffing_get_staff_user' : '');
		if ($event_plan_id <= 0
			|| $role_id <= 0
			|| $get_slots_callback === ''
			|| (function_exists('get_post_type') && get_post_type($event_plan_id) !== 'vms_event_plan')) {
			$result['warning_codes'][] = 'invalid_dispatch_context';
			return $result;
		}

		$at = isset($args['at']) && function_exists('bvmgr_operational_context_timestamp')
			? bvmgr_operational_context_timestamp($args['at'])
			: null;
		$require_active_shift = !empty($args['require_active_shift']);
		foreach ((array) call_user_func($get_slots_callback, $event_plan_id, false) as $slot) {
			if (!is_array($slot) || absint($slot['role_id'] ?? 0) !== $role_id) {
				continue;
			}
			$slot_id = absint($slot['slot_id'] ?? 0);
			$window = $resolve_window_callback !== ''
				? call_user_func($resolve_window_callback, $event_plan_id, $slot)
				: array('start_ts' => null, 'end_ts' => null, 'start_local' => null, 'end_local' => null);
			$shift_window_valid = is_numeric($window['start_ts'] ?? null)
				&& is_numeric($window['end_ts'] ?? null)
				&& (int) $window['end_ts'] > (int) $window['start_ts'];
			$shift_active = null;
			if ($at instanceof DateTimeImmutable && $shift_window_valid) {
				$shift_active = $at->getTimestamp() >= (int) $window['start_ts'] && $at->getTimestamp() < (int) $window['end_ts'];
			}
			if (!$shift_window_valid && !empty($slot['assignments'])) {
				$result['warning_codes'][] = 'shift_window_unresolved';
			}

			foreach ((array) ($slot['assignments'] ?? array()) as $assignment) {
				if (!is_array($assignment)) {
					continue;
				}
				$assignment_id = absint($assignment['assignment_id'] ?? 0);
				$staff_id = absint($assignment['staff_id'] ?? 0);
				$status = sanitize_key((string) ($assignment['status'] ?? ''));
				$user = $staff_id > 0 && $get_staff_user_callback !== '' ? call_user_func($get_staff_user_callback, $staff_id) : null;
				$user_id = is_object($user) && isset($user->ID) ? absint($user->ID) : 0;
				$state_dispatchable = in_array($status, $result['dispatchable_statuses'], true);
				$dispatch_eligible = $state_dispatchable && $user_id > 0 && (!$require_active_shift || $shift_active === true);
				$reason = '';
				if (!$state_dispatchable) {
					$reason = 'assignment_state_not_dispatchable';
				} elseif ($user_id <= 0) {
					$reason = 'staff_user_not_linked';
				} elseif ($require_active_shift && !$shift_window_valid) {
					$reason = 'shift_window_unresolved';
				} elseif ($require_active_shift && $shift_active !== true) {
					$reason = 'outside_resolved_shift';
				}

				$row = array(
					'assignment_id' => $assignment_id,
					'slot_id' => $slot_id,
					'staff_id' => $staff_id,
					'user_id' => $user_id,
					'role_id' => $role_id,
					'role_slug' => (string) $role['role_slug'],
					'role_name' => (string) $role['role_name'],
					'assignment_state' => $status,
					'state_dispatchable' => $state_dispatchable,
					'dispatch_eligible' => $dispatch_eligible,
					'ineligibility_reason' => $reason,
					'shift_window_valid' => $shift_window_valid,
					'shift_active' => $shift_active,
					'shift_start_timestamp' => is_numeric($window['start_ts'] ?? null) ? (int) $window['start_ts'] : null,
					'shift_end_timestamp' => is_numeric($window['end_ts'] ?? null) ? (int) $window['end_ts'] : null,
					'shift_start_local' => ($window['start_local'] ?? null) instanceof DateTimeInterface ? $window['start_local']->format('Y-m-d H:i:s') : '',
					'shift_end_local' => ($window['end_local'] ?? null) instanceof DateTimeInterface ? $window['end_local']->format('Y-m-d H:i:s') : '',
				);
				$result['assignments'][] = $row;
				if ($dispatch_eligible) {
					$result['eligible_assignments'][] = $row;
					$result['eligible_user_ids'][] = $user_id;
				} elseif ($reason !== '') {
					$result['warning_codes'][] = $reason;
				}
			}
		}

		$result['eligible_user_ids'] = array_values(array_unique(array_filter(array_map('absint', $result['eligible_user_ids']))));
		$result['warning_codes'] = array_values(array_unique(array_filter(array_map('sanitize_key', $result['warning_codes']))));
		if (empty($result['assignments'])) {
			$result['warning_codes'][] = 'no_role_assignments';
		} elseif (empty($result['eligible_assignments'])) {
			$result['warning_codes'][] = 'no_dispatchable_users';
		}

		/**
		 * Add consumer-specific data to the read-only staffing dispatch result.
		 * Canonical fields are restored after this filter.
		 *
		 * @param array<string,mixed> $result        Dispatch result.
		 * @param int                 $event_plan_id Event Plan post ID.
		 * @param int                 $role_id       Staffing role term ID.
		 * @param array               $args          Resolver arguments.
		 */
		$filtered = apply_filters('bvmgr_staffing_dispatch_result', $result, $event_plan_id, $role_id, $args);
		return is_array($filtered) ? array_merge($filtered, $result) : $result;
	}
}
