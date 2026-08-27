<?php
defined('ABSPATH') || exit;

/**
 * STATUS-02 foundation: cancellation orchestration helpers.
 *
 * This file intentionally provides data-model + orchestration primitives only.
 * Provider adapters (TEC/Event Tickets/Woo) are implemented separately.
 */

if (!function_exists('vms_cancellation_policy_options')) {
	function vms_cancellation_policy_options(): array
	{
		return array(
			'status_only' => __('Status only', 'backstage-venue-manager'),
			'stop_sales' => __('Stop sales', 'backstage-venue-manager'),
			'stop_sales_queue_refunds' => __('Stop sales + queue refunds', 'backstage-venue-manager'),
			'stop_sales_auto_refund' => __('Stop sales + auto refund', 'backstage-venue-manager'),
			'stop_sales_auto_refund_remove_attendees' => __('Stop sales + auto refund + attendee cleanup', 'backstage-venue-manager'),
		);
	}
}

if (!function_exists('vms_cancellation_reason_options')) {
	function vms_cancellation_reason_options(): array
	{
		return array(
			'weather' => __('Weather', 'backstage-venue-manager'),
			'low_sales' => __('Low sales', 'backstage-venue-manager'),
			'artist_cancelled' => __('Artist cancelled', 'backstage-venue-manager'),
			'venue_issue' => __('Venue issue', 'backstage-venue-manager'),
			'logistics' => __('Logistics issue', 'backstage-venue-manager'),
			'compliance' => __('Compliance issue', 'backstage-venue-manager'),
			'other' => __('Other', 'backstage-venue-manager'),
		);
	}
}

if (!function_exists('vms_cancellation_job_statuses')) {
	function vms_cancellation_job_statuses(): array
	{
		return array(
			'queued' => __('Queued', 'backstage-venue-manager'),
			'running' => __('Running', 'backstage-venue-manager'),
			'completed' => __('Completed', 'backstage-venue-manager'),
			'completed_with_errors' => __('Completed with errors', 'backstage-venue-manager'),
			'failed' => __('Failed', 'backstage-venue-manager'),
		);
	}
}
 
if (!function_exists('vms_cancellation_step_statuses')) {
	function vms_cancellation_step_statuses(): array
	{
		return array(
			'pending' => __('Pending', 'backstage-venue-manager'),
			'running' => __('Running', 'backstage-venue-manager'),
			'done' => __('Done', 'backstage-venue-manager'),
			'failed' => __('Failed', 'backstage-venue-manager'),
			'blocked' => __('Blocked', 'backstage-venue-manager'),
		);
	}
}

if (!function_exists('vms_cancellation_step_labels')) {
	function vms_cancellation_step_labels(): array
	{
		return array(
			'policy_capture' => __('Policy capture', 'backstage-venue-manager'),
			'provider_sales_stop' => __('Provider sales stop', 'backstage-venue-manager'),
			'refund_discovery' => __('Refund discovery', 'backstage-venue-manager'),
			'refund_execution' => __('Refund execution', 'backstage-venue-manager'),
			'notifications' => __('Notifications', 'backstage-venue-manager'),
		);
	}
}

if (!function_exists('vms_cancellation_step_keys')) {
	function vms_cancellation_step_keys(): array
	{
		$labels = function_exists('vms_cancellation_step_labels')
			? (array) vms_cancellation_step_labels()
			: array(
				'policy_capture' => '',
				'provider_sales_stop' => '',
				'refund_discovery' => '',
				'refund_execution' => '',
				'notifications' => '',
			);
		return array_values(array_unique(array_filter(array_map('sanitize_key', array_keys($labels)))));
	}
}

if (!function_exists('vms_cancellation_default_steps')) {
	function vms_cancellation_default_steps(string $policy = 'status_only'): array
	{
		$policy = sanitize_key($policy);
		$out = array(
			'policy_capture' => array(
				'key' => 'policy_capture',
				'status' => 'done',
				'message' => 'policy_captured',
				'data' => array('policy' => $policy),
			),
			'provider_sales_stop' => array(
				'key' => 'provider_sales_stop',
				'status' => 'pending',
			),
			'refund_discovery' => array(
				'key' => 'refund_discovery',
				'status' => 'pending',
			),
			'refund_execution' => array(
				'key' => 'refund_execution',
				'status' => 'pending',
			),
			'notifications' => array(
				'key' => 'notifications',
				'status' => 'pending',
			),
		);

		$ordered = array();
		$keys = function_exists('vms_cancellation_step_keys')
			? (array) vms_cancellation_step_keys()
			: array_keys($out);
		foreach ($keys as $key) {
			$key = sanitize_key((string) $key);
			if ($key !== '' && isset($out[$key])) {
				$ordered[] = $out[$key];
			}
		}
		return $ordered;
	}
}

if (!function_exists('vms_cancellation_compute_step_totals')) {
	/**
	 * @return array{done:int,failed:int,blocked:int,pending:int,running:int}
	 */
	function vms_cancellation_compute_step_totals(array $steps): array
	{
		$totals = array(
			'done' => 0,
			'failed' => 0,
			'blocked' => 0,
			'pending' => 0,
			'running' => 0,
		);
		$allowed = array_keys($totals);
		foreach ($steps as $step) {
			if (!is_array($step)) {
				continue;
			}
			$status = sanitize_key((string) ($step['status'] ?? 'pending'));
			if (!in_array($status, $allowed, true)) {
				$status = 'pending';
			}
			$totals[$status]++;
		}
		return $totals;
	}
}

if (!function_exists('vms_cancellation_normalize_steps')) {
	/**
	 * Repairs malformed/missing step rows into canonical order/shape.
	 *
	 * @return array{steps:array<int,array<string,mixed>>,changed:bool,issues:array<int,string>}
	 */
	function vms_cancellation_normalize_steps(array $steps, string $policy = 'status_only'): array
	{
		$policy = sanitize_key($policy);
		$defaults = function_exists('vms_cancellation_default_steps')
			? (array) vms_cancellation_default_steps($policy)
			: array();
		$default_map = array();
		foreach ($defaults as $row) {
			if (!is_array($row)) {
				continue;
			}
			$key = sanitize_key((string) ($row['key'] ?? ''));
			if ($key === '') {
				continue;
			}
			$default_map[$key] = $row;
		}

		$allowed_statuses = array('pending', 'running', 'done', 'failed', 'blocked');
		$row_by_key = array();
		$issues = array();
		$scalar_fields = array('updated_at_gmt', 'last_attempt_at_gmt', 'retry_requested_at_gmt');
		$int_fields = array('retry_requested_by_user_id', 'attempt_count');
		$array_fields = array('data', 'previous_result');

		foreach ($steps as $idx => $step) {
			if (!is_array($step)) {
				$issues[] = 'non_array_step:' . (int) $idx;
				continue;
			}

			$key = sanitize_key((string) ($step['key'] ?? ''));
			if ($key === '' || !isset($default_map[$key])) {
				$issues[] = 'unknown_step_key:' . ($key !== '' ? $key : ('row_' . (int) $idx));
				continue;
			}

			$status = sanitize_key((string) ($step['status'] ?? ($default_map[$key]['status'] ?? 'pending')));
			if (!in_array($status, $allowed_statuses, true)) {
				$issues[] = 'invalid_step_status:' . $key . ':' . $status;
				$status = sanitize_key((string) ($default_map[$key]['status'] ?? 'pending'));
			}

			$row = $default_map[$key];
			$row['status'] = $status;
			$row['key'] = $key;
			$row['message'] = isset($step['message']) ? sanitize_text_field((string) $step['message']) : (string) ($row['message'] ?? '');

			foreach ($array_fields as $field_key) {
				if (isset($step[$field_key]) && is_array($step[$field_key])) {
					$row[$field_key] = $step[$field_key];
				}
			}
			foreach ($scalar_fields as $field_key) {
				if (isset($step[$field_key])) {
					$row[$field_key] = sanitize_text_field((string) $step[$field_key]);
				}
			}
			foreach ($int_fields as $field_key) {
				if (isset($step[$field_key])) {
					$row[$field_key] = max(0, absint($step[$field_key]));
				}
			}

			$incoming_updated = isset($row['updated_at_gmt']) ? strtotime((string) $row['updated_at_gmt'] . ' GMT') : 0;
			$existing_updated = isset($row_by_key[$key]['updated_at_gmt']) ? strtotime((string) $row_by_key[$key]['updated_at_gmt'] . ' GMT') : 0;
			if (isset($row_by_key[$key])) {
				$issues[] = 'duplicate_step_key:' . $key;
				if ($incoming_updated > 0 && $incoming_updated >= $existing_updated) {
					$row_by_key[$key] = $row;
				}
				continue;
			}

			$row_by_key[$key] = $row;
		}

		$ordered = array();
		foreach (array_keys($default_map) as $key) {
			$key = sanitize_key($key);
			if ($key === '') {
				continue;
			}
			if (isset($row_by_key[$key])) {
				$ordered[] = $row_by_key[$key];
				continue;
			}
			$ordered[] = $default_map[$key];
			$issues[] = 'missing_step_key:' . $key;
		}

		return array(
			'steps' => $ordered,
			'changed' => !empty($issues),
			'issues' => array_values(array_unique(array_map('sanitize_text_field', $issues))),
		);
	}
}

if (!function_exists('vms_cancellation_backfill_legacy_job')) {
	/**
	 * Creates a safe no-op cancellation envelope for legacy cancelled plans.
	 *
	 * This backfill intentionally does not execute adapters/refunds/notifications.
	 *
	 * @return array{
	 *   ok:bool,
	 *   created:bool,
	 *   job_id:string,
	 *   state:string,
	 *   error?:string,
	 *   summary:array<string,mixed>
	 * }
	 */
	function vms_cancellation_backfill_legacy_job(int $event_plan_id, array $args = array()): array
	{
		$event_plan_id = absint($event_plan_id);
		if ($event_plan_id <= 0) {
			return array(
				'ok' => false,
				'created' => false,
				'job_id' => '',
				'state' => 'failed',
				'error' => 'invalid_event_plan_id',
				'summary' => array(),
			);
		}

		$k_status = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'status') ?: '_vms_event_plan_status') : '_vms_event_plan_status';
		$k_job_id = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'cancel_job_id') ?: '_vms_cancel_job_id') : '_vms_cancel_job_id';
		$k_job_state = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'cancel_job_state') ?: '_vms_cancel_job_state') : '_vms_cancel_job_state';
		$k_job_summary = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'cancel_job_summary') ?: '_vms_cancel_job_summary') : '_vms_cancel_job_summary';
		$k_cancel_review = function_exists('bvmgr_meta_key')
			? (bvmgr_meta_key('event_plan', 'cancel_requires_operator_review') ?: '_vms_cancel_requires_operator_review')
			: '_vms_cancel_requires_operator_review';
		$k_cancel_policy = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'cancel_policy') ?: '_vms_cancel_policy') : '_vms_cancel_policy';
		$k_cancel_reason_code = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'cancel_reason_code') ?: '_vms_cancel_reason_code') : '_vms_cancel_reason_code';
		$k_cancel_reason_note = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'cancel_reason_note') ?: '_vms_cancel_reason_note') : '_vms_cancel_reason_note';
		$k_cancel_vendor_message = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'cancel_vendor_message') ?: '_vms_cancel_vendor_message') : '_vms_cancel_vendor_message';
		$k_cancelled_at_gmt = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'cancelled_at_gmt') ?: '_vms_cancelled_at_gmt') : '_vms_cancelled_at_gmt';
		$k_cancelled_by_user_id = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'cancelled_by_user_id') ?: '_vms_cancelled_by_user_id') : '_vms_cancelled_by_user_id';

		$plan_status = sanitize_key((string) get_post_meta($event_plan_id, $k_status, true));
		if ($plan_status !== 'cancelled') {
			return array(
				'ok' => false,
				'created' => false,
				'job_id' => '',
				'state' => 'failed',
				'error' => 'plan_not_cancelled',
				'summary' => array(),
			);
		}

		$existing_job_id = sanitize_text_field((string) get_post_meta($event_plan_id, $k_job_id, true));
		$existing_state = sanitize_key((string) get_post_meta($event_plan_id, $k_job_state, true));
		$existing_summary = get_post_meta($event_plan_id, $k_job_summary, true);
		if (!is_array($existing_summary)) {
			$existing_summary = array();
		}
		$has_steps = isset($existing_summary['steps']) && is_array($existing_summary['steps']) && !empty($existing_summary['steps']);
		if ($existing_job_id !== '' && $has_steps) {
			return array(
				'ok' => true,
				'created' => false,
				'job_id' => $existing_job_id,
				'state' => ($existing_state !== '' ? $existing_state : 'queued'),
				'summary' => $existing_summary,
			);
		}

		$policy = sanitize_key((string) get_post_meta($event_plan_id, $k_cancel_policy, true));
		$valid_policies = function_exists('vms_cancellation_policy_options')
			? array_keys((array) vms_cancellation_policy_options())
			: array('status_only');
		if (!in_array($policy, $valid_policies, true)) {
			$policy = 'status_only';
		}

		$reason_code = sanitize_key((string) get_post_meta($event_plan_id, $k_cancel_reason_code, true));
		$valid_reasons = function_exists('vms_cancellation_reason_options')
			? array_keys((array) vms_cancellation_reason_options())
			: array('other');
		if ($reason_code !== '' && !in_array($reason_code, $valid_reasons, true)) {
			$reason_code = 'other';
		}
		$reason_note = sanitize_textarea_field((string) get_post_meta($event_plan_id, $k_cancel_reason_note, true));
		$vendor_message = sanitize_textarea_field((string) get_post_meta($event_plan_id, $k_cancel_vendor_message, true));

		$cancelled_at_gmt = sanitize_text_field((string) get_post_meta($event_plan_id, $k_cancelled_at_gmt, true));
		if ($cancelled_at_gmt === '') {
			$cancelled_at_gmt = gmdate('Y-m-d H:i:s');
		}
		$cancelled_by_user_id = absint(get_post_meta($event_plan_id, $k_cancelled_by_user_id, true));
		$backfill_at_gmt = gmdate('Y-m-d H:i:s');
		$backfill_source = sanitize_key((string) ($args['source'] ?? 'legacy_cancelled_backfill'));
		if ($backfill_source === '') {
			$backfill_source = 'legacy_cancelled_backfill';
		}
		$backfill_by_user_id = isset($args['backfill_by_user_id'])
			? absint($args['backfill_by_user_id'])
			: absint(get_current_user_id());

		$job_id = ($existing_job_id !== '') ? $existing_job_id : vms_cancellation_generate_job_id();
		$steps = function_exists('vms_cancellation_default_steps')
			? (array) vms_cancellation_default_steps($policy)
			: array(
				array('key' => 'policy_capture', 'status' => 'done'),
				array('key' => 'provider_sales_stop', 'status' => 'done'),
				array('key' => 'refund_discovery', 'status' => 'done'),
				array('key' => 'refund_execution', 'status' => 'done'),
				array('key' => 'notifications', 'status' => 'done'),
			);

		foreach ($steps as $i => $step) {
			if (!is_array($step)) {
				continue;
			}
			$step_key = sanitize_key((string) ($step['key'] ?? ''));
			if ($step_key === '') {
				continue;
			}

			$steps[$i]['status'] = 'done';
			$steps[$i]['updated_at_gmt'] = $backfill_at_gmt;
			$steps[$i]['last_attempt_at_gmt'] = $backfill_at_gmt;
			$steps[$i]['attempt_count'] = max(1, absint($step['attempt_count'] ?? 1));

			if ($step_key === 'policy_capture') {
				$steps[$i]['message'] = 'policy_captured_backfill';
				$steps[$i]['data'] = array(
					'policy' => $policy,
					'backfill' => true,
					'source' => $backfill_source,
				);
			} else {
				$steps[$i]['message'] = 'backfill_skipped_legacy_cancelled_plan';
				$steps[$i]['data'] = array(
					'backfill' => true,
					'source' => $backfill_source,
					'skipped_reason' => 'legacy_cancelled_plan',
				);
			}
		}

		$recovery_log = isset($existing_summary['recovery_log']) && is_array($existing_summary['recovery_log'])
			? $existing_summary['recovery_log']
			: array();
		$recovery_log[] = array(
			'type' => 'legacy_cancelled_job_backfilled',
			'at_gmt' => $backfill_at_gmt,
			'by_user_id' => $backfill_by_user_id,
			'source' => $backfill_source,
		);

		$summary = $existing_summary;
		$summary['job_id'] = $job_id;
		$summary['event_plan_id'] = $event_plan_id;
		$summary['policy'] = $policy;
		$summary['reason_code'] = $reason_code;
		$summary['reason_note'] = $reason_note;
		$summary['vendor_message'] = $vendor_message;
		$summary['created_at_gmt'] = sanitize_text_field((string) ($summary['created_at_gmt'] ?? $cancelled_at_gmt));
		$summary['created_by_user_id'] = absint($summary['created_by_user_id'] ?? ($cancelled_by_user_id > 0 ? $cancelled_by_user_id : $backfill_by_user_id));
		$summary['steps'] = $steps;
		$summary['final_state'] = 'completed';
		$summary['last_run_at_gmt'] = $backfill_at_gmt;
		$summary['step_totals'] = array(
			'done' => count($steps),
			'failed' => 0,
			'blocked' => 0,
			'pending' => 0,
			'running' => 0,
		);
		$summary['active_run'] = array();
		$summary['backfilled_at_gmt'] = $backfill_at_gmt;
		$summary['backfill_source'] = $backfill_source;
		$summary['recovery_log'] = $recovery_log;

		update_post_meta($event_plan_id, $k_job_id, $job_id);
		update_post_meta($event_plan_id, $k_job_state, 'completed');
		update_post_meta($event_plan_id, $k_job_summary, $summary);
		update_post_meta($event_plan_id, $k_cancel_review, '1');

		do_action('vms_cancellation_job_backfilled', $event_plan_id, $summary, $args);

		return array(
			'ok' => true,
			'created' => true,
			'job_id' => $job_id,
			'state' => 'completed',
			'summary' => $summary,
		);
	}
}

if (!function_exists('vms_cancellation_auto_refund_policies')) {
	function vms_cancellation_auto_refund_policies(): array
	{
		return array(
			'stop_sales_auto_refund',
			'stop_sales_auto_refund_remove_attendees',
		);
	}
}

if (!function_exists('vms_cancellation_auto_refund_guard')) {
	/**
	 * Central guardrail for auto-refund execution.
	 *
	 * Defaults:
	 * - Capability required: manage_options
	 * - Non-dev envs run in dry-run mode unless explicitly enabled via filter.
	 *
	 * @return array{
	 *   allowed:bool,
	 *   dry_run:bool,
	 *   reason:string,
	 *   environment:string,
	 *   required_capability:string,
	 *   has_capability:bool,
	 *   allow_non_dev:bool,
	 *   user_id:int
	 * }
	 */
	function vms_cancellation_auto_refund_guard(int $event_plan_id, string $policy, array $summary = array(), array $args = array()): array
	{
		$event_plan_id = absint($event_plan_id);
		$policy = sanitize_key($policy);
		$summary = is_array($summary) ? $summary : array();

		$env = function_exists('wp_get_environment_type') ? (string) wp_get_environment_type() : 'production';
		$env = sanitize_key($env);
		if ($env === '') {
			$env = 'production';
		}

		$user_id = isset($args['user_id']) ? absint($args['user_id']) : absint(get_current_user_id());
		$required_capability = (string) apply_filters(
			'vms_cancellation_auto_refund_required_capability',
			'manage_options',
			$event_plan_id,
			$policy,
			$summary,
			$user_id
		);
		$required_capability = sanitize_key($required_capability);

		$has_capability = true;
		if ($required_capability !== '') {
			$has_capability = ($user_id > 0) ? user_can($user_id, $required_capability) : current_user_can($required_capability);
		}

		$auto_policies = function_exists('vms_cancellation_auto_refund_policies')
			? (array) vms_cancellation_auto_refund_policies()
			: array('stop_sales_auto_refund', 'stop_sales_auto_refund_remove_attendees');
		$is_auto_policy = in_array($policy, $auto_policies, true);

		$is_non_dev_env = !in_array($env, array('local', 'development'), true);
		$allow_non_dev = (bool) apply_filters(
			'vms_cancellation_auto_refund_allow_non_dev',
			true,
			$event_plan_id,
			$policy,
			$summary,
			$env
		);
		$require_confirmation = (bool) apply_filters(
			'vms_cancellation_auto_refund_require_confirmation',
			true,
			$event_plan_id,
			$policy,
			$summary,
			$env
		);
		$has_live_confirmation = !empty($summary['auto_refund_confirmed']);
		$dry_run = $is_auto_policy && $is_non_dev_env && !$allow_non_dev;
		$dry_run = (bool) apply_filters(
			'vms_cancellation_auto_refund_dry_run_mode',
			$dry_run,
			$event_plan_id,
			$policy,
			$summary,
			$env
		);

		$allowed = $has_capability && (!$is_auto_policy || (!$dry_run && (!$require_confirmation || $has_live_confirmation)));
		$reason = 'auto_refund_enabled';
		if (!$is_auto_policy) {
			$reason = 'not_auto_refund_policy';
		} elseif (!$has_capability) {
			$reason = 'missing_required_capability';
		} elseif ($require_confirmation && !$has_live_confirmation) {
			$reason = 'missing_live_refund_confirmation';
		} elseif ($dry_run) {
			$reason = 'non_dev_dry_run_guard';
		}

		$guard = array(
			'allowed' => (bool) $allowed,
			'dry_run' => (bool) $dry_run,
			'reason' => $reason,
			'environment' => $env,
			'required_capability' => $required_capability,
			'has_capability' => (bool) $has_capability,
			'allow_non_dev' => (bool) $allow_non_dev,
			'require_confirmation' => (bool) $require_confirmation,
			'has_live_confirmation' => (bool) $has_live_confirmation,
			'user_id' => $user_id,
		);

		return (array) apply_filters('vms_cancellation_auto_refund_guard', $guard, $event_plan_id, $policy, $summary, $args);
	}
}

if (!function_exists('vms_cancellation_generate_job_id')) {
	function vms_cancellation_generate_job_id(): string
	{
		return 'cancel_' . wp_generate_uuid4();
	}
}

if (!function_exists('vms_cancellation_create_job')) {
	/**
	 * Creates a cancellation job envelope on the Event Plan.
	 *
	 * @return array{ok:bool,job_id:string,state:string,summary:array<string,mixed>}
	 */
	function vms_cancellation_create_job(int $event_plan_id, array $args = array()): array
	{
		$event_plan_id = absint($event_plan_id);
		if ($event_plan_id <= 0) {
			return array(
				'ok' => false,
				'job_id' => '',
				'state' => 'failed',
				'summary' => array('error' => 'invalid_event_plan_id'),
			);
		}

		$job_id = isset($args['job_id']) ? sanitize_text_field((string) $args['job_id']) : '';
		if ($job_id === '') {
			$job_id = vms_cancellation_generate_job_id();
		}

		$policy = isset($args['policy']) ? sanitize_key((string) $args['policy']) : '';
		$policies = array_keys(vms_cancellation_policy_options());
		if (!in_array($policy, $policies, true)) {
			$policy = 'status_only';
		}

		$reason_code = isset($args['reason_code']) ? sanitize_key((string) $args['reason_code']) : '';
		$reason_codes = array_keys(vms_cancellation_reason_options());
		if ($reason_code !== '' && !in_array($reason_code, $reason_codes, true)) {
			$reason_code = 'other';
		}
		$reason_note = isset($args['reason_note']) ? sanitize_textarea_field((string) $args['reason_note']) : '';
		$vendor_message = isset($args['vendor_message']) ? sanitize_textarea_field((string) $args['vendor_message']) : '';
		$cancelled_by_user_id = isset($args['cancelled_by_user_id']) ? absint($args['cancelled_by_user_id']) : absint(get_current_user_id());
		$cancelled_at_gmt = gmdate('Y-m-d H:i:s');
		$steps = function_exists('vms_cancellation_default_steps')
			? (array) vms_cancellation_default_steps($policy)
			: array(
				array('key' => 'policy_capture', 'status' => 'done'),
				array('key' => 'provider_sales_stop', 'status' => 'pending'),
				array('key' => 'refund_discovery', 'status' => 'pending'),
				array('key' => 'refund_execution', 'status' => 'pending'),
				array('key' => 'notifications', 'status' => 'pending'),
			);
		$step_totals = function_exists('vms_cancellation_compute_step_totals')
			? (array) vms_cancellation_compute_step_totals($steps)
			: array(
				'done' => 1,
				'failed' => 0,
				'blocked' => 0,
				'pending' => 4,
				'running' => 0,
			);

		$summary = array(
				'job_id' => $job_id,
				'event_plan_id' => $event_plan_id,
				'policy' => $policy,
				'reason_code' => $reason_code,
				'reason_note' => $reason_note,
				'vendor_message' => $vendor_message,
				'auto_refund_confirmed' => !empty($args['auto_refund_confirmed']) ? 1 : 0,
				'created_at_gmt' => $cancelled_at_gmt,
				'created_by_user_id' => $cancelled_by_user_id,
				'steps' => $steps,
				'final_state' => 'queued',
				'step_totals' => $step_totals,
				'runs' => array(),
				'retry_log' => array(),
				'recovery_log' => array(),
				'active_run' => array(),
		);

		$k_job_id = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'cancel_job_id') ?: '_vms_cancel_job_id') : '_vms_cancel_job_id';
		$k_job_state = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'cancel_job_state') ?: '_vms_cancel_job_state') : '_vms_cancel_job_state';
		$k_job_summary = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'cancel_job_summary') ?: '_vms_cancel_job_summary') : '_vms_cancel_job_summary';
		$k_cancel_policy = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'cancel_policy') ?: '_vms_cancel_policy') : '_vms_cancel_policy';
		$k_cancel_reason_code = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'cancel_reason_code') ?: '_vms_cancel_reason_code') : '_vms_cancel_reason_code';
		$k_cancel_reason_note = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'cancel_reason_note') ?: '_vms_cancel_reason_note') : '_vms_cancel_reason_note';
		$k_cancelled_at_gmt = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'cancelled_at_gmt') ?: '_vms_cancelled_at_gmt') : '_vms_cancelled_at_gmt';
		$k_cancelled_by_user_id = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'cancelled_by_user_id') ?: '_vms_cancelled_by_user_id') : '_vms_cancelled_by_user_id';

		update_post_meta($event_plan_id, $k_job_id, $job_id);
		update_post_meta($event_plan_id, $k_job_state, 'queued');
		update_post_meta($event_plan_id, $k_job_summary, $summary);
		update_post_meta($event_plan_id, $k_cancel_policy, $policy);
		update_post_meta($event_plan_id, $k_cancel_reason_code, $reason_code);
		update_post_meta($event_plan_id, $k_cancel_reason_note, $reason_note);
		if ($vendor_message === '') {
			delete_post_meta($event_plan_id, $k_cancel_vendor_message);
		} else {
			update_post_meta($event_plan_id, $k_cancel_vendor_message, $vendor_message);
		}
		update_post_meta($event_plan_id, $k_cancelled_at_gmt, $cancelled_at_gmt);
		update_post_meta($event_plan_id, $k_cancelled_by_user_id, $cancelled_by_user_id);

		do_action('vms_cancellation_job_created', $event_plan_id, $summary);

		return array(
			'ok' => true,
			'job_id' => $job_id,
			'state' => 'queued',
			'summary' => $summary,
		);
	}
}

if (!function_exists('vms_cancellation_run_step')) {
	/**
	 * Runs one cancellation step with safe defaults.
	 *
	 * @return array{status:string,message:string,data:array<string,mixed>}
	 */
	function vms_cancellation_run_step(int $event_plan_id, string $policy, string $step_key, array $summary): array
	{
		$event_plan_id = absint($event_plan_id);
		$policy = sanitize_key($policy);
		$step_key = sanitize_key($step_key);

		$skip = array(
			'status' => 'done',
			'message' => 'skipped_by_policy',
			'data' => array(),
		);

		if ($step_key === 'policy_capture') {
			return array(
				'status' => 'done',
				'message' => 'policy_captured',
				'data' => array('policy' => $policy),
			);
		}

		if ($policy === 'status_only' && in_array($step_key, array('provider_sales_stop', 'refund_discovery', 'refund_execution'), true)) {
			return $skip;
		}

		$default_blocked = array(
			'status' => 'blocked',
			'message' => 'adapter_not_implemented',
			'data' => array('step_key' => $step_key),
		);

		try {
			$result = apply_filters('vms_cancellation_run_step', $default_blocked, $event_plan_id, $policy, $step_key, $summary);
		} catch (Throwable $e) {
			return array(
				'status' => 'failed',
				'message' => 'adapter_exception',
				'data' => array(
					'step_key' => $step_key,
					'error' => sanitize_text_field((string) $e->getMessage()),
				),
			);
		}
		if (!is_array($result)) {
			return $default_blocked;
		}

		$status = isset($result['status']) ? sanitize_key((string) $result['status']) : '';
		$allowed = array('pending', 'running', 'done', 'failed', 'blocked');
		if (!in_array($status, $allowed, true)) {
			$status = 'blocked';
		}

		return array(
			'status' => $status,
			'message' => isset($result['message']) ? sanitize_text_field((string) $result['message']) : '',
			'data' => isset($result['data']) && is_array($result['data']) ? $result['data'] : array(),
		);
	}
}

if (!function_exists('vms_cancellation_retry_step')) {
	/**
	 * Resets a failed/blocked step (and dependent downstream steps) so it can be re-run safely.
	 *
	 * @return array{ok:bool,state:string,step_key:string,reset_steps?:array<int,string>,error?:string,summary:array<string,mixed>}
	 */
	function vms_cancellation_retry_step(int $event_plan_id, string $step_key, array $args = array()): array
	{
		$event_plan_id = absint($event_plan_id);
		$step_key = sanitize_key($step_key);
		if ($event_plan_id <= 0 || $step_key === '') {
			return array('ok' => false, 'state' => 'failed', 'step_key' => $step_key, 'error' => 'invalid_retry_request', 'summary' => array());
		}

		$k_job_id = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'cancel_job_id') ?: '_vms_cancel_job_id') : '_vms_cancel_job_id';
		$k_job_state = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'cancel_job_state') ?: '_vms_cancel_job_state') : '_vms_cancel_job_state';
		$k_job_summary = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'cancel_job_summary') ?: '_vms_cancel_job_summary') : '_vms_cancel_job_summary';
		$k_cancel_review = function_exists('bvmgr_meta_key')
			? (bvmgr_meta_key('event_plan', 'cancel_requires_operator_review') ?: '_vms_cancel_requires_operator_review')
			: '_vms_cancel_requires_operator_review';

		$job_id_actual = sanitize_text_field((string) get_post_meta($event_plan_id, $k_job_id, true));
		if ($job_id_actual === '') {
			return array('ok' => false, 'state' => 'failed', 'step_key' => $step_key, 'error' => 'job_missing', 'summary' => array());
		}

		$summary = get_post_meta($event_plan_id, $k_job_summary, true);
		if (!is_array($summary)) {
			return array('ok' => false, 'state' => 'failed', 'step_key' => $step_key, 'error' => 'job_summary_missing', 'summary' => array());
		}
		$policy = sanitize_key((string) ($summary['policy'] ?? 'status_only'));
		$normalized = function_exists('vms_cancellation_normalize_steps')
			? (array) vms_cancellation_normalize_steps(isset($summary['steps']) && is_array($summary['steps']) ? $summary['steps'] : array(), $policy)
			: array('steps' => isset($summary['steps']) && is_array($summary['steps']) ? $summary['steps'] : array(), 'changed' => false, 'issues' => array());
		$steps = isset($normalized['steps']) && is_array($normalized['steps']) ? $normalized['steps'] : array();
		if (!empty($normalized['changed'])) {
			$summary['steps'] = $steps;
			$recovery_log = isset($summary['recovery_log']) && is_array($summary['recovery_log']) ? $summary['recovery_log'] : array();
			$recovery_log[] = array(
				'type' => 'step_shape_normalized',
				'at_gmt' => gmdate('Y-m-d H:i:s'),
				'by_user_id' => absint(get_current_user_id()),
				'issues' => isset($normalized['issues']) && is_array($normalized['issues']) ? $normalized['issues'] : array(),
				'context' => 'retry_step',
			);
			$summary['recovery_log'] = $recovery_log;
			update_post_meta($event_plan_id, $k_job_summary, $summary);
		}
		$job_state = sanitize_key((string) get_post_meta($event_plan_id, $k_job_state, true));
		if ($job_state === 'running') {
			return array('ok' => false, 'state' => 'running', 'step_key' => $step_key, 'error' => 'job_running', 'summary' => $summary);
		}
		if (empty($steps)) {
			return array('ok' => false, 'state' => 'failed', 'step_key' => $step_key, 'error' => 'steps_missing', 'summary' => $summary);
		}

		$target_index = -1;
		$target_status = '';
		foreach ($steps as $i => $step) {
			if (!is_array($step)) {
				continue;
			}
			$cur_key = sanitize_key((string) ($step['key'] ?? ''));
			if ($cur_key !== $step_key) {
				continue;
			}
			$target_index = (int) $i;
			$target_status = sanitize_key((string) ($step['status'] ?? 'pending'));
			break;
		}
		if ($target_index < 0) {
			return array('ok' => false, 'state' => 'failed', 'step_key' => $step_key, 'error' => 'step_not_found', 'summary' => $summary);
		}
		if ($step_key === 'policy_capture') {
			return array('ok' => false, 'state' => 'failed', 'step_key' => $step_key, 'error' => 'step_not_retryable', 'summary' => $summary);
		}
		if (!in_array($target_status, array('failed', 'blocked'), true)) {
			return array('ok' => false, 'state' => 'failed', 'step_key' => $step_key, 'error' => 'step_not_in_retry_state', 'summary' => $summary);
		}

		$retry_at_gmt = gmdate('Y-m-d H:i:s');
		$retry_by_user_id = isset($args['retry_by_user_id']) ? absint($args['retry_by_user_id']) : absint(get_current_user_id());
		$retry_log = isset($summary['retry_log']) && is_array($summary['retry_log']) ? $summary['retry_log'] : array();
		$retry_request_id = 'retry_' . wp_generate_uuid4();
		$reset_steps = array();

		foreach ($steps as $i => $step) {
			if (!is_array($step)) {
				continue;
			}
			$cur_key = sanitize_key((string) ($step['key'] ?? ''));
			if ($cur_key === '') {
				continue;
			}
			$cur_status = sanitize_key((string) ($step['status'] ?? 'pending'));
			$reset = false;

			if ($cur_key === $step_key) {
				$reset = true;
			} elseif ($step_key === 'provider_sales_stop' && in_array($cur_key, array('refund_discovery', 'refund_execution', 'notifications'), true)) {
				$reset = true;
			} elseif ($step_key === 'refund_discovery' && in_array($cur_key, array('refund_execution', 'notifications'), true)) {
				$reset = true;
			} elseif ($step_key === 'refund_execution' && $cur_key === 'notifications') {
				$reset = true;
			}

			if (!$reset || $cur_key === 'policy_capture' || $cur_status === 'running') {
				continue;
			}

			// Idempotency guard: keep a successful notifications step done unless
			// notifications itself is explicitly being retried.
			if ($cur_key === 'notifications' && $cur_status === 'done' && $step_key !== 'notifications') {
				continue;
			}

			$steps[$i]['status'] = 'pending';
			$steps[$i]['message'] = 'retry_requested';
			$steps[$i]['updated_at_gmt'] = $retry_at_gmt;
			$steps[$i]['retry_requested_at_gmt'] = $retry_at_gmt;
			$steps[$i]['retry_requested_by_user_id'] = $retry_by_user_id;

			if (!empty($step['message']) || !empty($step['data']) || !empty($step['updated_at_gmt'])) {
				$steps[$i]['previous_result'] = array(
					'status' => $cur_status,
					'message' => isset($step['message']) ? sanitize_text_field((string) $step['message']) : '',
					'data' => isset($step['data']) && is_array($step['data']) ? $step['data'] : array(),
					'updated_at_gmt' => isset($step['updated_at_gmt']) ? sanitize_text_field((string) $step['updated_at_gmt']) : '',
				);
			}
			$steps[$i]['data'] = array();
			$reset_steps[] = $cur_key;
		}
		$reset_steps = array_values(array_unique(array_filter(array_map('sanitize_key', $reset_steps))));
		$retry_log[] = array(
			'retry_request_id' => $retry_request_id,
			'step_key' => $step_key,
			'at_gmt' => $retry_at_gmt,
			'by_user_id' => $retry_by_user_id,
			'previous_status' => $target_status,
			'reset_steps' => $reset_steps,
			'trigger' => 'single_step',
		);
		$summary['retry_log'] = $retry_log;
		$summary['last_retry_request'] = array(
			'retry_request_id' => $retry_request_id,
			'step_key' => $step_key,
			'at_gmt' => $retry_at_gmt,
			'by_user_id' => $retry_by_user_id,
			'reset_steps' => $reset_steps,
			'trigger' => 'single_step',
		);

		$summary['steps'] = $steps;
		update_post_meta($event_plan_id, $k_job_summary, $summary);
		update_post_meta($event_plan_id, $k_job_state, 'queued');
		update_post_meta($event_plan_id, $k_cancel_review, '1');

		return array(
			'ok' => true,
			'state' => 'queued',
			'step_key' => $step_key,
			'reset_steps' => $reset_steps,
			'summary' => $summary,
		);
	}
}

if (!function_exists('vms_cancellation_retry_all_failed_steps')) {
	/**
	 * Bulk retry helper: resets from the earliest failed/blocked retryable step through downstream steps.
	 *
	 * @return array{ok:bool,state:string,retried_steps:array<int,string>,reset_steps?:array<int,string>,error?:string,summary:array<string,mixed>}
	 */
	function vms_cancellation_retry_all_failed_steps(int $event_plan_id, array $args = array()): array
	{
		$event_plan_id = absint($event_plan_id);
		if ($event_plan_id <= 0) {
			return array('ok' => false, 'state' => 'failed', 'retried_steps' => array(), 'error' => 'invalid_retry_request', 'summary' => array());
		}

		$k_job_state = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'cancel_job_state') ?: '_vms_cancel_job_state') : '_vms_cancel_job_state';
		$k_job_summary = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'cancel_job_summary') ?: '_vms_cancel_job_summary') : '_vms_cancel_job_summary';
		$k_cancel_review = function_exists('bvmgr_meta_key')
			? (bvmgr_meta_key('event_plan', 'cancel_requires_operator_review') ?: '_vms_cancel_requires_operator_review')
			: '_vms_cancel_requires_operator_review';

		$summary = get_post_meta($event_plan_id, $k_job_summary, true);
		if (!is_array($summary)) {
			return array('ok' => false, 'state' => 'failed', 'retried_steps' => array(), 'error' => 'job_summary_missing', 'summary' => array());
		}
		$policy = sanitize_key((string) ($summary['policy'] ?? 'status_only'));
		$normalized = function_exists('vms_cancellation_normalize_steps')
			? (array) vms_cancellation_normalize_steps(isset($summary['steps']) && is_array($summary['steps']) ? $summary['steps'] : array(), $policy)
			: array('steps' => isset($summary['steps']) && is_array($summary['steps']) ? $summary['steps'] : array(), 'changed' => false, 'issues' => array());
		$steps = isset($normalized['steps']) && is_array($normalized['steps']) ? $normalized['steps'] : array();
		if (!empty($normalized['changed'])) {
			$summary['steps'] = $steps;
			$recovery_log = isset($summary['recovery_log']) && is_array($summary['recovery_log']) ? $summary['recovery_log'] : array();
			$recovery_log[] = array(
				'type' => 'step_shape_normalized',
				'at_gmt' => gmdate('Y-m-d H:i:s'),
				'by_user_id' => absint(get_current_user_id()),
				'issues' => isset($normalized['issues']) && is_array($normalized['issues']) ? $normalized['issues'] : array(),
				'context' => 'retry_all_failed_steps',
			);
			$summary['recovery_log'] = $recovery_log;
			update_post_meta($event_plan_id, $k_job_summary, $summary);
		}
		$job_state = sanitize_key((string) get_post_meta($event_plan_id, $k_job_state, true));
		if ($job_state === 'running') {
			return array('ok' => false, 'state' => 'running', 'retried_steps' => array(), 'error' => 'job_running', 'summary' => $summary);
		}
		if (empty($steps)) {
			return array('ok' => false, 'state' => 'failed', 'retried_steps' => array(), 'error' => 'steps_missing', 'summary' => $summary);
		}

		$earliest_retry_index = -1;
		$retried_steps = array();
		$reset_from_step = '';
		foreach ($steps as $i => $step) {
			if (!is_array($step)) {
				continue;
			}
			$step_key = sanitize_key((string) ($step['key'] ?? ''));
			$step_status = sanitize_key((string) ($step['status'] ?? 'pending'));
			if ($step_key === '' || $step_key === 'policy_capture') {
				continue;
			}
			if (!in_array($step_status, array('failed', 'blocked'), true)) {
				continue;
			}
			if ($earliest_retry_index < 0) {
				$earliest_retry_index = (int) $i;
				$reset_from_step = $step_key;
			}
			$retried_steps[] = $step_key;
		}

		if ($earliest_retry_index < 0) {
			return array('ok' => false, 'state' => 'failed', 'retried_steps' => array(), 'error' => 'no_failed_or_blocked_steps', 'summary' => $summary);
		}

		$retry_at_gmt = gmdate('Y-m-d H:i:s');
		$retry_by_user_id = isset($args['retry_by_user_id']) ? absint($args['retry_by_user_id']) : absint(get_current_user_id());
		$retry_log = isset($summary['retry_log']) && is_array($summary['retry_log']) ? $summary['retry_log'] : array();
		$retry_request_id = 'retry_' . wp_generate_uuid4();
		$reset_steps = array();

		foreach ($steps as $i => $step) {
			if (!is_array($step) || $i < $earliest_retry_index) {
				continue;
			}
			$cur_key = sanitize_key((string) ($step['key'] ?? ''));
			$cur_status = sanitize_key((string) ($step['status'] ?? 'pending'));
			if ($cur_key === '' || $cur_key === 'policy_capture' || $cur_status === 'running') {
				continue;
			}

			// Idempotency guard: keep a successful notifications step done during
			// bulk retry so we do not send duplicate cancellation notifications.
			if ($cur_key === 'notifications' && $cur_status === 'done') {
				continue;
			}

			$steps[$i]['status'] = 'pending';
			$steps[$i]['message'] = 'retry_requested';
			$steps[$i]['updated_at_gmt'] = $retry_at_gmt;
			$steps[$i]['retry_requested_at_gmt'] = $retry_at_gmt;
			$steps[$i]['retry_requested_by_user_id'] = $retry_by_user_id;

			if (!empty($step['message']) || !empty($step['data']) || !empty($step['updated_at_gmt'])) {
				$steps[$i]['previous_result'] = array(
					'status' => $cur_status,
					'message' => isset($step['message']) ? sanitize_text_field((string) $step['message']) : '',
					'data' => isset($step['data']) && is_array($step['data']) ? $step['data'] : array(),
					'updated_at_gmt' => isset($step['updated_at_gmt']) ? sanitize_text_field((string) $step['updated_at_gmt']) : '',
				);
			}
			$steps[$i]['data'] = array();
			$reset_steps[] = $cur_key;
		}
		$retried_steps = array_values(array_unique(array_filter(array_map('sanitize_key', $retried_steps))));
		$reset_steps = array_values(array_unique(array_filter(array_map('sanitize_key', $reset_steps))));
		$retry_log[] = array(
			'retry_request_id' => $retry_request_id,
			'step_key' => 'bulk_failed_blocked',
			'at_gmt' => $retry_at_gmt,
			'by_user_id' => $retry_by_user_id,
			'retried_steps' => $retried_steps,
			'reset_steps' => $reset_steps,
			'reset_from_step' => sanitize_key($reset_from_step),
			'trigger' => 'bulk_failed_blocked',
		);
		$summary['retry_log'] = $retry_log;
		$summary['last_retry_request'] = array(
			'retry_request_id' => $retry_request_id,
			'step_key' => 'bulk_failed_blocked',
			'at_gmt' => $retry_at_gmt,
			'by_user_id' => $retry_by_user_id,
			'retried_steps' => $retried_steps,
			'reset_steps' => $reset_steps,
			'reset_from_step' => sanitize_key($reset_from_step),
			'trigger' => 'bulk_failed_blocked',
		);

		$summary['steps'] = $steps;
		update_post_meta($event_plan_id, $k_job_summary, $summary);
		update_post_meta($event_plan_id, $k_job_state, 'queued');
		update_post_meta($event_plan_id, $k_cancel_review, '1');

		return array(
			'ok' => true,
			'state' => 'queued',
			'retried_steps' => $retried_steps,
			'reset_steps' => $reset_steps,
			'summary' => $summary,
		);
	}
}


if (!function_exists('vms_cancellation_request_live_refund_run')) {
	/**
	 * Re-queues refund discovery/execution for an already-cancelled Event Plan and immediately runs the job.
	 *
	 * This is intended for plans that were cancelled before true live refund execution existed,
	 * or plans that queued refunds for manual review and now need a safe live-refund attempt.
	 *
	 * @return array{ok:bool,state:string,target_policy:string,reset_steps:array<int,string>,policy_changed?:bool,error?:string,summary:array<string,mixed>}
	 */
	function vms_cancellation_request_live_refund_run(int $event_plan_id, array $args = array()): array
	{
		$event_plan_id = absint($event_plan_id);
		if ($event_plan_id <= 0) {
			return array('ok' => false, 'state' => 'failed', 'target_policy' => '', 'reset_steps' => array(), 'error' => 'invalid_event_plan_id', 'summary' => array());
		}

		$k_job_id = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'cancel_job_id') ?: '_vms_cancel_job_id') : '_vms_cancel_job_id';
		$k_job_state = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'cancel_job_state') ?: '_vms_cancel_job_state') : '_vms_cancel_job_state';
		$k_job_summary = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'cancel_job_summary') ?: '_vms_cancel_job_summary') : '_vms_cancel_job_summary';
		$k_cancel_policy = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'cancel_policy') ?: '_vms_cancel_policy') : '_vms_cancel_policy';
		$k_cancel_review = function_exists('bvmgr_meta_key')
			? (bvmgr_meta_key('event_plan', 'cancel_requires_operator_review') ?: '_vms_cancel_requires_operator_review')
			: '_vms_cancel_requires_operator_review';
		$k_plan_status = function_exists('bvmgr_meta_key')
			? (bvmgr_meta_key('event_plan', 'status') ?: '_vms_event_plan_status')
			: '_vms_event_plan_status';

		$plan_status = sanitize_key((string) get_post_meta($event_plan_id, $k_plan_status, true));
		if ($plan_status !== 'cancelled') {
			return array('ok' => false, 'state' => 'failed', 'target_policy' => '', 'reset_steps' => array(), 'error' => 'event_plan_not_cancelled', 'summary' => array());
		}

		$job_id_actual = sanitize_text_field((string) get_post_meta($event_plan_id, $k_job_id, true));
		if ($job_id_actual === '') {
			return array('ok' => false, 'state' => 'failed', 'target_policy' => '', 'reset_steps' => array(), 'error' => 'job_missing', 'summary' => array());
		}

		$summary = get_post_meta($event_plan_id, $k_job_summary, true);
		if (!is_array($summary)) {
			return array('ok' => false, 'state' => 'failed', 'target_policy' => '', 'reset_steps' => array(), 'error' => 'job_summary_missing', 'summary' => array());
		}

		$job_state = sanitize_key((string) get_post_meta($event_plan_id, $k_job_state, true));
		if ($job_state === 'running') {
			return array('ok' => false, 'state' => 'running', 'target_policy' => '', 'reset_steps' => array(), 'error' => 'job_running', 'summary' => $summary);
		}

		$current_policy = sanitize_key((string) ($summary['policy'] ?? get_post_meta($event_plan_id, $k_cancel_policy, true)));
		$queue_only_policies = array('stop_sales_queue_refunds');
		$auto_policies = function_exists('vms_cancellation_auto_refund_policies')
			? array_values(array_unique(array_filter(array_map('sanitize_key', (array) vms_cancellation_auto_refund_policies()))))
			: array('stop_sales_auto_refund', 'stop_sales_auto_refund_remove_attendees');
		$refund_capable_policies = array_values(array_unique(array_merge($queue_only_policies, $auto_policies)));
		$policy_override = isset($args['policy_override']) ? sanitize_key((string) $args['policy_override']) : '';
		$target_policy = $current_policy;

		if (in_array($policy_override, $auto_policies, true)) {
			$target_policy = $policy_override;
		} elseif (in_array($current_policy, $queue_only_policies, true)) {
			$target_policy = 'stop_sales_auto_refund';
		}

		if (!in_array($current_policy, $refund_capable_policies, true) && !in_array($policy_override, $auto_policies, true)) {
			return array('ok' => false, 'state' => 'failed', 'target_policy' => '', 'reset_steps' => array(), 'error' => 'policy_not_refund_capable', 'summary' => $summary);
		}
		if (!in_array($target_policy, $auto_policies, true)) {
			$target_policy = 'stop_sales_auto_refund';
		}

		$normalized = function_exists('vms_cancellation_normalize_steps')
			? (array) vms_cancellation_normalize_steps(isset($summary['steps']) && is_array($summary['steps']) ? $summary['steps'] : array(), $target_policy)
			: array('steps' => isset($summary['steps']) && is_array($summary['steps']) ? $summary['steps'] : array(), 'changed' => false, 'issues' => array());
		$steps = isset($normalized['steps']) && is_array($normalized['steps']) ? $normalized['steps'] : array();
		if (empty($steps)) {
			return array('ok' => false, 'state' => 'failed', 'target_policy' => $target_policy, 'reset_steps' => array(), 'error' => 'steps_missing', 'summary' => $summary);
		}

		$requested_at_gmt = gmdate('Y-m-d H:i:s');
		$requested_by_user_id = isset($args['requested_by_user_id']) ? absint($args['requested_by_user_id']) : absint(get_current_user_id());
		$reset_steps = array();
		$provider_status = '';
		foreach ($steps as $step) {
			if (!is_array($step)) {
				continue;
			}
			$scan_key = sanitize_key((string) ($step['key'] ?? ''));
			if ($scan_key === 'provider_sales_stop') {
				$provider_status = sanitize_key((string) ($step['status'] ?? 'pending'));
				break;
			}
		}

		foreach ($steps as $i => $step) {
			if (!is_array($step)) {
				continue;
			}
			$step_key = sanitize_key((string) ($step['key'] ?? ''));
			if ($step_key === '') {
				continue;
			}
			$step_status = sanitize_key((string) ($step['status'] ?? 'pending'));
			$should_reset = false;
			if ($step_key === 'refund_discovery' || $step_key === 'refund_execution') {
				$should_reset = true;
			} elseif ($step_key === 'provider_sales_stop' && $provider_status !== 'done') {
				$should_reset = true;
			}
			if (!$should_reset || $step_status === 'running') {
				continue;
			}

			if (!empty($step['message']) || !empty($step['data']) || !empty($step['updated_at_gmt'])) {
				$steps[$i]['previous_result'] = array(
					'status' => $step_status,
					'message' => isset($step['message']) ? sanitize_text_field((string) $step['message']) : '',
					'data' => isset($step['data']) && is_array($step['data']) ? $step['data'] : array(),
					'updated_at_gmt' => isset($step['updated_at_gmt']) ? sanitize_text_field((string) $step['updated_at_gmt']) : '',
				);
			}
			$steps[$i]['status'] = 'pending';
			$steps[$i]['message'] = 'manual_live_refund_requested';
			$steps[$i]['data'] = array();
			$steps[$i]['updated_at_gmt'] = $requested_at_gmt;
			$steps[$i]['retry_requested_at_gmt'] = $requested_at_gmt;
			$steps[$i]['retry_requested_by_user_id'] = $requested_by_user_id;
			$reset_steps[] = $step_key;
		}

		$reset_steps = array_values(array_unique(array_filter(array_map('sanitize_key', $reset_steps))));
		if (empty($reset_steps)) {
			return array('ok' => false, 'state' => 'failed', 'target_policy' => $target_policy, 'reset_steps' => array(), 'error' => 'no_refund_steps_available', 'summary' => $summary);
		}

		$policy_changed = ($target_policy !== $current_policy);
		if ($policy_changed && empty($summary['policy_before_manual_live_refund'])) {
			$summary['policy_before_manual_live_refund'] = $current_policy;
		}
		$summary['policy'] = $target_policy;
		$summary['auto_refund_confirmed'] = 1;
		$summary['manual_live_refund_requested_at_gmt'] = $requested_at_gmt;
		$summary['manual_live_refund_requested_by_user_id'] = $requested_by_user_id;
		$summary['manual_live_refund_request_count'] = max(1, absint($summary['manual_live_refund_request_count'] ?? 0) + 1);
		$summary['steps'] = $steps;
		$summary['retry_log'] = isset($summary['retry_log']) && is_array($summary['retry_log']) ? $summary['retry_log'] : array();
		$retry_request_id = 'retry_' . wp_generate_uuid4();
		$summary['retry_log'][] = array(
			'retry_request_id' => $retry_request_id,
			'step_key' => 'manual_live_refund_request',
			'at_gmt' => $requested_at_gmt,
			'by_user_id' => $requested_by_user_id,
			'reset_steps' => $reset_steps,
			'target_policy' => $target_policy,
			'previous_policy' => $current_policy,
			'trigger' => 'manual_live_refund_request',
		);
		$summary['last_retry_request'] = array(
			'retry_request_id' => $retry_request_id,
			'step_key' => 'manual_live_refund_request',
			'at_gmt' => $requested_at_gmt,
			'by_user_id' => $requested_by_user_id,
			'reset_steps' => $reset_steps,
			'target_policy' => $target_policy,
			'previous_policy' => $current_policy,
			'trigger' => 'manual_live_refund_request',
		);

		update_post_meta($event_plan_id, $k_job_summary, $summary);
		update_post_meta($event_plan_id, $k_job_state, 'queued');
		update_post_meta($event_plan_id, $k_cancel_review, '1');
		if ($policy_changed) {
			update_post_meta($event_plan_id, $k_cancel_policy, $target_policy);
		}

		$run = function_exists('vms_cancellation_run_job')
			? (array) vms_cancellation_run_job($event_plan_id, array('job_id' => $job_id_actual))
			: array('ok' => false, 'state' => 'failed', 'summary' => $summary + array('error' => 'run_helper_missing'));
		$run['target_policy'] = $target_policy;
		$run['reset_steps'] = $reset_steps;
		$run['policy_changed'] = $policy_changed;
		return $run;
	}
}

if (!function_exists('vms_cancellation_run_job')) {
	/**
	 * Executes queued/pending cancellation steps in a deterministic, safe manner.
	 *
	 * @return array{ok:bool,state:string,summary:array<string,mixed>}
	 */
	function vms_cancellation_run_job(int $event_plan_id, array $args = array()): array
	{
		$event_plan_id = absint($event_plan_id);
		if ($event_plan_id <= 0) {
			return array('ok' => false, 'state' => 'failed', 'summary' => array('error' => 'invalid_event_plan_id'));
		}

		$k_job_id = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'cancel_job_id') ?: '_vms_cancel_job_id') : '_vms_cancel_job_id';
		$k_job_state = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'cancel_job_state') ?: '_vms_cancel_job_state') : '_vms_cancel_job_state';
		$k_job_summary = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'cancel_job_summary') ?: '_vms_cancel_job_summary') : '_vms_cancel_job_summary';
		$k_cancel_review = function_exists('bvmgr_meta_key')
			? (bvmgr_meta_key('event_plan', 'cancel_requires_operator_review') ?: '_vms_cancel_requires_operator_review')
			: '_vms_cancel_requires_operator_review';

		$job_id_expected = isset($args['job_id']) ? sanitize_text_field((string) $args['job_id']) : '';
		$job_id_actual = (string) get_post_meta($event_plan_id, $k_job_id, true);
		if ($job_id_expected !== '' && $job_id_actual !== '' && $job_id_expected !== $job_id_actual) {
			return array('ok' => false, 'state' => 'failed', 'summary' => array('error' => 'job_id_mismatch'));
		}

		$summary = get_post_meta($event_plan_id, $k_job_summary, true);
		if (!is_array($summary)) {
			$summary = array();
		}

		if ($job_id_actual === '') {
			return array('ok' => false, 'state' => 'failed', 'summary' => array('error' => 'job_missing'));
		}

		$policy = sanitize_key((string) ($summary['policy'] ?? 'status_only'));
		$valid_policies = array_keys(vms_cancellation_policy_options());
		$invalid_policy = !in_array($policy, $valid_policies, true);
		if ($invalid_policy) {
			$policy = 'status_only';
			$summary['policy'] = $policy;
		}
		$normalized = function_exists('vms_cancellation_normalize_steps')
			? (array) vms_cancellation_normalize_steps(isset($summary['steps']) && is_array($summary['steps']) ? $summary['steps'] : array(), $policy)
			: array('steps' => isset($summary['steps']) && is_array($summary['steps']) ? $summary['steps'] : array(), 'changed' => false, 'issues' => array());
		if (!empty($normalized['changed'])) {
			$summary['steps'] = isset($normalized['steps']) && is_array($normalized['steps']) ? $normalized['steps'] : array();
			$recovery_log = isset($summary['recovery_log']) && is_array($summary['recovery_log']) ? $summary['recovery_log'] : array();
			$recovery_log[] = array(
				'type' => 'step_shape_normalized',
				'at_gmt' => gmdate('Y-m-d H:i:s'),
				'by_user_id' => absint(get_current_user_id()),
				'issues' => isset($normalized['issues']) && is_array($normalized['issues']) ? $normalized['issues'] : array(),
				'context' => 'run_job',
			);
			$summary['recovery_log'] = $recovery_log;
			update_post_meta($event_plan_id, $k_job_summary, $summary);
		}
		$run_started = gmdate('Y-m-d H:i:s');
		$run_by_user_id = absint(get_current_user_id());
		$runs = isset($summary['runs']) && is_array($summary['runs']) ? $summary['runs'] : array();
		$current_run = array(
			'at_gmt' => $run_started,
			'by_user_id' => $run_by_user_id,
			'steps' => array(),
		);
		if ($invalid_policy) {
			$current_run['warnings'] = array('invalid_policy_fallback_to_status_only');
		}

		$running_timeout = (int) apply_filters('vms_cancellation_running_timeout_seconds', 900, $event_plan_id, $summary);
		if ($running_timeout < 60) {
			$running_timeout = 60;
		}

		$current_state = sanitize_key((string) get_post_meta($event_plan_id, $k_job_state, true));
		if ($current_state === 'running') {
			$active_run = isset($summary['active_run']) && is_array($summary['active_run']) ? $summary['active_run'] : array();
			$active_started_gmt = sanitize_text_field((string) ($active_run['started_at_gmt'] ?? ''));
			$active_started_ts = $active_started_gmt !== '' ? strtotime($active_started_gmt . ' GMT') : 0;
			$active_age = $active_started_ts > 0 ? max(0, time() - (int) $active_started_ts) : 0;
			$is_stale_running = ($active_started_ts > 0 && $active_age >= $running_timeout);

			if (!$is_stale_running) {
				$summary_with_error = $summary;
				$summary_with_error['error'] = 'job_already_running';
				$summary_with_error['active_run_age_seconds'] = $active_age;
				return array(
					'ok' => false,
					'state' => 'running',
					'summary' => $summary_with_error,
				);
			}

			$recovery_log = isset($summary['recovery_log']) && is_array($summary['recovery_log']) ? $summary['recovery_log'] : array();
			$recovery_log[] = array(
				'type' => 'stale_running_recovered',
				'at_gmt' => $run_started,
				'by_user_id' => $run_by_user_id,
				'active_run_started_at_gmt' => $active_started_gmt,
				'running_timeout_seconds' => $running_timeout,
			);
			$summary['recovery_log'] = $recovery_log;
			$summary['active_run'] = array();
			update_post_meta($event_plan_id, $k_job_summary, $summary);
			update_post_meta($event_plan_id, $k_job_state, 'queued', 'running');

			$current_state = sanitize_key((string) get_post_meta($event_plan_id, $k_job_state, true));
			if ($current_state === 'running') {
				$summary_with_error = $summary;
				$summary_with_error['error'] = 'stale_run_recovery_failed';
				return array(
					'ok' => false,
					'state' => 'running',
					'summary' => $summary_with_error,
				);
			}
		}

		if ($current_state === '') {
			update_post_meta($event_plan_id, $k_job_state, 'queued');
			$current_state = 'queued';
		}
		$current_run['state_before'] = $current_state;

		$claimed_running = update_post_meta($event_plan_id, $k_job_state, 'running', $current_state);
		if (!$claimed_running) {
			$state_now = sanitize_key((string) get_post_meta($event_plan_id, $k_job_state, true));
			if ($state_now === 'running') {
				$summary_with_error = $summary;
				$summary_with_error['error'] = 'job_already_running';
				return array(
					'ok' => false,
					'state' => 'running',
					'summary' => $summary_with_error,
				);
			}
			$claimed_running = update_post_meta($event_plan_id, $k_job_state, 'running', $state_now);
			if (!$claimed_running) {
				$summary_with_error = $summary;
				$summary_with_error['error'] = 'unable_to_claim_running_state';
				return array(
					'ok' => false,
					'state' => $state_now !== '' ? $state_now : 'queued',
					'summary' => $summary_with_error,
				);
			}
		}

		$run_id = 'run_' . wp_generate_uuid4();
		$current_run['run_id'] = $run_id;
		$current_run['state_after_claim'] = 'running';
		$summary['active_run'] = array(
			'run_id' => $run_id,
			'started_at_gmt' => $run_started,
			'by_user_id' => $run_by_user_id,
		);
		update_post_meta($event_plan_id, $k_job_summary, $summary);

		$steps = isset($summary['steps']) && is_array($summary['steps']) ? $summary['steps'] : array();
		if (empty($steps)) {
			$summary['last_run_at_gmt'] = gmdate('Y-m-d H:i:s');
			$summary['final_state'] = 'failed';
			$summary['step_totals'] = array(
				'done' => 0,
				'failed' => 0,
				'blocked' => 0,
				'pending' => 0,
				'running' => 0,
			);
			$summary['error'] = 'missing_steps';
			$summary['active_run'] = array();
			$current_run['error'] = 'missing_steps';
			$runs[] = $current_run;
			$summary['runs'] = $runs;

			update_post_meta($event_plan_id, $k_job_summary, $summary);
			update_post_meta($event_plan_id, $k_job_state, 'failed');
			update_post_meta($event_plan_id, $k_cancel_review, '1');

			try {
				do_action('vms_cancellation_job_ran', $event_plan_id, 'failed', $summary);
			} catch (Throwable $e) {
				$summary['hook_errors'] = isset($summary['hook_errors']) && is_array($summary['hook_errors']) ? $summary['hook_errors'] : array();
				$summary['hook_errors'][] = array(
					'hook' => 'vms_cancellation_job_ran',
					'error' => sanitize_text_field((string) $e->getMessage()),
					'at_gmt' => gmdate('Y-m-d H:i:s'),
				);
				update_post_meta($event_plan_id, $k_job_summary, $summary);
			}

			return array(
				'ok' => false,
				'state' => 'failed',
				'summary' => $summary,
			);
		}

		$current_run['preflight_steps'] = array();
		foreach ($steps as $step) {
			if (!is_array($step)) {
				continue;
			}
			$current_run['preflight_steps'][] = array(
				'key' => sanitize_key((string) ($step['key'] ?? '')),
				'status' => sanitize_key((string) ($step['status'] ?? 'pending')),
				'message' => sanitize_text_field((string) ($step['message'] ?? '')),
				'updated_at_gmt' => sanitize_text_field((string) ($step['updated_at_gmt'] ?? '')),
				'attempt_count' => isset($step['attempt_count']) ? max(0, (int) $step['attempt_count']) : 0,
			);
		}

		$refund_policies = array('stop_sales_queue_refunds', 'stop_sales_auto_refund', 'stop_sales_auto_refund_remove_attendees');
		$dependency_rules = array(
			'provider_sales_stop' => array(
				array('key' => 'policy_capture', 'allowed' => array('done')),
			),
			'refund_discovery' => array(
				array('key' => 'provider_sales_stop', 'allowed' => array('done')),
			),
			'refund_execution' => array(
				array('key' => 'refund_discovery', 'allowed' => array('done')),
			),
			'notifications' => array(
				array('key' => 'provider_sales_stop', 'allowed' => array('done')),
			),
		);
		if (in_array($policy, $refund_policies, true)) {
			$dependency_rules['notifications'][] = array('key' => 'refund_execution', 'allowed' => array('done', 'failed', 'blocked'));
		}

		$get_step_status = static function (array $rows, string $lookup_key): string {
			$lookup_key = sanitize_key($lookup_key);
			foreach ($rows as $row) {
				if (!is_array($row)) {
					continue;
				}
				$key = sanitize_key((string) ($row['key'] ?? ''));
				if ($key !== $lookup_key) {
					continue;
				}
				return sanitize_key((string) ($row['status'] ?? 'pending'));
			}
			return '';
		};

		$blocked_count = 0;
		$failed_count = 0;
		$done_count = 0;
		$pending_count = 0;
		$running_count = 0;

		foreach ($steps as $i => $step) {
			if (!is_array($step)) {
				continue;
			}
			$step_key = sanitize_key((string) ($step['key'] ?? ''));
			if ($step_key === '') {
				continue;
			}

			$cur_status = sanitize_key((string) ($step['status'] ?? 'pending'));
			if (in_array($cur_status, array('done', 'failed', 'blocked', 'running'), true)) {
				if ($cur_status === 'done') $done_count++;
				if ($cur_status === 'failed') $failed_count++;
				if ($cur_status === 'blocked') $blocked_count++;
				continue;
			}

			$dependency_block = null;
			$rules = isset($dependency_rules[$step_key]) && is_array($dependency_rules[$step_key]) ? $dependency_rules[$step_key] : array();
			foreach ($rules as $rule) {
				if (!is_array($rule)) {
					continue;
				}
				$dep_key = sanitize_key((string) ($rule['key'] ?? ''));
				$allowed = isset($rule['allowed']) && is_array($rule['allowed']) ? array_values(array_unique(array_filter(array_map('sanitize_key', $rule['allowed'])))) : array('done');
				if ($dep_key === '') {
					continue;
				}
				$dep_status = $get_step_status($steps, $dep_key);
				if ($dep_status === '') {
					$dependency_block = array(
						'status' => 'blocked',
						'message' => 'missing_dependency_step',
						'data' => array(
							'step_key' => $step_key,
							'dependency_step' => $dep_key,
							'allowed_statuses' => $allowed,
							'dependency_status' => '',
							'requires_operator_review' => true,
						),
					);
					break;
				}
				if (!in_array($dep_status, $allowed, true)) {
					$dependency_block = array(
						'status' => 'blocked',
						'message' => 'dependency_not_satisfied',
						'data' => array(
							'step_key' => $step_key,
							'dependency_step' => $dep_key,
							'allowed_statuses' => $allowed,
							'dependency_status' => $dep_status,
							'requires_operator_review' => true,
						),
					);
					break;
				}
			}

			$attempted_at_gmt = gmdate('Y-m-d H:i:s');
			$attempt_count = isset($steps[$i]['attempt_count']) ? max(0, (int) $steps[$i]['attempt_count']) + 1 : 1;

			if (is_array($dependency_block)) {
				$steps[$i]['status'] = 'blocked';
				$steps[$i]['message'] = (string) $dependency_block['message'];
				$steps[$i]['data'] = isset($dependency_block['data']) && is_array($dependency_block['data']) ? $dependency_block['data'] : array();
				$steps[$i]['updated_at_gmt'] = $attempted_at_gmt;
				$steps[$i]['last_attempt_at_gmt'] = $attempted_at_gmt;
				$steps[$i]['attempt_count'] = $attempt_count;

				$current_run['steps'][] = array(
					'key' => $step_key,
					'status' => 'blocked',
					'message' => (string) $dependency_block['message'],
					'data' => $steps[$i]['data'],
					'attempt_count' => $attempt_count,
				);
				$blocked_count++;
				continue;
			}

			$summary_for_step = $summary;
			$summary_for_step['steps'] = $steps;
			$res = vms_cancellation_run_step($event_plan_id, $policy, $step_key, $summary_for_step);
			$steps[$i]['status'] = (string) $res['status'];
			$steps[$i]['message'] = (string) $res['message'];
			$steps[$i]['data'] = (array) $res['data'];
			$steps[$i]['updated_at_gmt'] = $attempted_at_gmt;
			$steps[$i]['last_attempt_at_gmt'] = $attempted_at_gmt;
			$steps[$i]['attempt_count'] = $attempt_count;

			$current_run['steps'][] = array(
				'key' => $step_key,
				'status' => (string) $res['status'],
				'message' => (string) $res['message'],
				'data' => (array) $res['data'],
				'attempt_count' => $attempt_count,
			);

			if ($res['status'] === 'done') $done_count++;
			if ($res['status'] === 'failed') $failed_count++;
			if ($res['status'] === 'blocked') $blocked_count++;
		}

		// Include unchanged step statuses in totals (e.g., pre-existing pending/running).
		foreach ($steps as $step) {
			if (!is_array($step)) {
				continue;
			}
			$step_key = sanitize_key((string) ($step['key'] ?? ''));
			if ($step_key === '') {
				continue;
			}
			$status = sanitize_key((string) ($step['status'] ?? 'pending'));
			if ($status === 'pending') {
				$pending_count++;
			} elseif ($status === 'running') {
				$running_count++;
			}
		}

		$summary['steps'] = $steps;
		$summary['last_run_at_gmt'] = gmdate('Y-m-d H:i:s');
		$summary['active_run'] = array();

		$final_state = 'completed';
		if ($failed_count > 0) {
			$final_state = 'failed';
		} elseif ($blocked_count > 0) {
			$final_state = 'completed_with_errors';
		} elseif ($pending_count > 0 || $running_count > 0) {
			$final_state = 'queued';
		}

		$summary['final_state'] = $final_state;
		$summary['step_totals'] = array(
			'done' => $done_count,
			'failed' => $failed_count,
			'blocked' => $blocked_count,
			'pending' => $pending_count,
			'running' => $running_count,
		);
		$current_run['postflight_step_totals'] = $summary['step_totals'];
		$current_run['state_after'] = $final_state;
		$runs[] = $current_run;
		$summary['runs'] = $runs;

		$requires_review = false;
		foreach ($steps as $step) {
			if (!is_array($step)) {
				continue;
			}
			$step_data = isset($step['data']) && is_array($step['data']) ? $step['data'] : array();
			if (!empty($step_data['requires_operator_review'])) {
				$requires_review = true;
				break;
			}
		}
		if ($final_state !== 'completed') {
			$requires_review = true;
		}

		update_post_meta($event_plan_id, $k_job_summary, $summary);
		update_post_meta($event_plan_id, $k_job_state, $final_state);
		update_post_meta($event_plan_id, $k_cancel_review, $requires_review ? '1' : '0');

		try {
			do_action('vms_cancellation_job_ran', $event_plan_id, $final_state, $summary);
		} catch (Throwable $e) {
			$summary['hook_errors'] = isset($summary['hook_errors']) && is_array($summary['hook_errors']) ? $summary['hook_errors'] : array();
			$summary['hook_errors'][] = array(
				'hook' => 'vms_cancellation_job_ran',
				'error' => sanitize_text_field((string) $e->getMessage()),
				'at_gmt' => gmdate('Y-m-d H:i:s'),
			);
			update_post_meta($event_plan_id, $k_job_summary, $summary);
		}

		return array(
			'ok' => true,
			'state' => $final_state,
			'summary' => $summary,
		);
	}
}

add_action('vms_cancellation_job_created', function ($event_plan_id, $summary) {
	$event_plan_id = absint($event_plan_id);
	if ($event_plan_id <= 0) {
		return;
	}

	$auto = (bool) apply_filters('vms_cancellation_auto_run_enabled', true, $event_plan_id, $summary);
	if (!$auto) {
		return;
	}

	$job_id = '';
	if (is_array($summary) && !empty($summary['job_id'])) {
		$job_id = sanitize_text_field((string) $summary['job_id']);
	}

	if (function_exists('vms_cancellation_run_job')) {
		vms_cancellation_run_job($event_plan_id, array('job_id' => $job_id));
	}
}, 10, 2);
