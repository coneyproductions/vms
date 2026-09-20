<?php
defined('ABSPATH') || exit;

/**
 * Central authorization/scope extension point for operational features.
 *
 * Core currently has no persisted per-venue ACL. The default preserves current
 * capability behavior, while the filter provides one fail-closed place for a
 * future venue/event scope policy to narrow access.
 */

if (!function_exists('bvmgr_operational_scope_decision')) {
	/** @return array<string,mixed> */
	function bvmgr_operational_scope_decision(int $user_id, string $capability, int $event_plan_id = 0, int $venue_id = 0, array $context = array()): array
	{
		$user_id = absint($user_id);
		$capability = sanitize_key($capability);
		$event_plan_id = absint($event_plan_id);
		$venue_id = absint($venue_id);
		$decision = array(
			'allowed' => false,
			'reason' => 'invalid_request',
			'user_id' => $user_id,
			'capability' => $capability,
			'event_plan_id' => $event_plan_id,
			'venue_id' => $venue_id,
			'base_capability_allowed' => false,
		);
		if ($user_id <= 0 || $capability === '') {
			return $decision;
		}

		$base_allowed = function_exists('user_can') && user_can($user_id, $capability);
		$decision['base_capability_allowed'] = $base_allowed;
		if (!$base_allowed) {
			$decision['reason'] = 'missing_capability';
			return $decision;
		}

		if ($event_plan_id > 0) {
			if (function_exists('get_post_type') && get_post_type($event_plan_id) !== 'vms_event_plan') {
				$decision['reason'] = 'invalid_event_plan';
				return $decision;
			}
			$event_venue_id = absint(get_post_meta($event_plan_id, '_vms_venue_id', true));
			if ($venue_id > 0 && $event_venue_id > 0 && $venue_id !== $event_venue_id) {
				$decision['reason'] = 'event_venue_mismatch';
				return $decision;
			}
			if ($venue_id <= 0) {
				$venue_id = $event_venue_id;
				$decision['venue_id'] = $venue_id;
			}
		}

		$decision['allowed'] = true;
		$decision['reason'] = 'capability_default';
		/**
		 * Narrow operational access for a user, event, and venue.
		 *
		 * The base WordPress capability is mandatory and cannot be granted by this
		 * filter. Implementations should return allowed=false when the requested
		 * venue/event is outside the user's operational scope.
		 *
		 * @param array<string,mixed> $decision Scope decision.
		 * @param array               $context  Consumer context.
		 */
		$filtered = apply_filters('bvmgr_operational_scope_decision', $decision, $context);
		if (is_array($filtered)) {
			$decision = array_merge($decision, $filtered);
		}

		$decision['allowed'] = $base_allowed && !empty($decision['allowed']);
		if (!$decision['allowed'] && $decision['reason'] === 'capability_default') {
			$decision['reason'] = 'outside_operational_scope';
		}

		return $decision;
	}
}

if (!function_exists('bvmgr_current_user_can_operate_context')) {
	function bvmgr_current_user_can_operate_context(string $capability, int $event_plan_id = 0, int $venue_id = 0, array $context = array()): bool
	{
		$user_id = function_exists('get_current_user_id') ? absint(get_current_user_id()) : 0;
		$decision = bvmgr_operational_scope_decision($user_id, $capability, $event_plan_id, $venue_id, $context);
		return !empty($decision['allowed']);
	}
}
