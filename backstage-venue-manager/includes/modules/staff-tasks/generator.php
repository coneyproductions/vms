<?php
defined('ABSPATH') || exit;

if (!function_exists('bvmgr_tasks_signature_meta_key')) {
	function bvmgr_tasks_signature_meta_key(): string
	{
		return '_vms_tasks_event_signature_v1';
	}
}

if (!function_exists('bvmgr_tasks_pending_signature_meta_key')) {
	function bvmgr_tasks_pending_signature_meta_key(): string
	{
		return '_vms_tasks_event_pending_signature_v1';
	}
}

if (!function_exists('bvmgr_tasks_event_signature_json')) {
	function bvmgr_tasks_event_signature_json(array $event_context): string
	{
		$json = wp_json_encode(bvmgr_tasks_build_event_signature($event_context));
		return is_string($json) ? $json : '';
	}
}

if (!function_exists('bvmgr_tasks_compute_due_at_local')) {
	function bvmgr_tasks_compute_due_at_local(array $event_context, string $due_mode, ?int $due_offset_minutes, string $due_time_local = ''): ?string
	{
		$due_mode = bvmgr_tasks_sanitize_due_mode($due_mode);
		$event_start_local = (string) ($event_context['event_start_local'] ?? '');
		if ($event_start_local === '') {
			return null;
		}

		$tz = wp_timezone();
		try {
			$base = new DateTimeImmutable($event_start_local, $tz);
		} catch (Exception $e) {
			return null;
		}

		if ($due_mode === 'none') {
			return null;
		}

		if ($due_mode === 'event_offset') {
			$offset = (int) $due_offset_minutes;
			if ($offset === 0) {
				return $base->format('Y-m-d H:i:s');
			}
			$modifier = ($offset > 0 ? '+' : '') . $offset . ' minutes';
			return $base->modify($modifier)->format('Y-m-d H:i:s');
		}

		if ($due_mode === 'fixed_datetime') {
			$date = $base->format('Y-m-d');
			$time = trim($due_time_local);
			if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
				$time = '10:00';
			}
			try {
				$dt = new DateTimeImmutable($date . ' ' . $time . ':00', $tz);
				return $dt->format('Y-m-d H:i:s');
			} catch (Exception $e) {
				return null;
			}
		}

		return null;
	}
}

if (!function_exists('bvmgr_tasks_merge_template_with_overrides')) {
	/**
	 * @param array<string,mixed> $template
	 * @param array<string,mixed> $overrides
	 * @return array<string,mixed>
	 */
	function bvmgr_tasks_merge_template_with_overrides(array $template, array $overrides): array
	{
		$effective = array(
			'title' => (string) ($template['title'] ?? ''),
			'instructions' => (string) ($template['instructions'] ?? ''),
			'priority' => bvmgr_tasks_sanitize_priority((string) ($template['priority'] ?? 'normal')),
			'is_required' => !empty($template['required_default']) ? 1 : 0,
			'due_mode' => bvmgr_tasks_sanitize_due_mode((string) ($template['due_mode'] ?? 'none')),
			'due_offset_minutes' => (($template['due_offset_minutes'] ?? null) !== null ? (int) $template['due_offset_minutes'] : null),
			'due_time_local' => (string) ($template['due_time_local'] ?? ''),
			'assignment_mode' => bvmgr_tasks_sanitize_assignment_mode((string) ($template['assignment_mode'] ?? 'role')),
			'role_key' => sanitize_key((string) ($template['role_key'] ?? '')),
			'assignee_user_id' => absint($template['assignee_user_id'] ?? 0),
		);

		if (array_key_exists('required_default', $overrides)) {
			$effective['is_required'] = !empty($overrides['required_default']) ? 1 : 0;
		}
		if (array_key_exists('priority', $overrides)) {
			$effective['priority'] = bvmgr_tasks_sanitize_priority((string) $overrides['priority']);
		}
		if (array_key_exists('assignment_mode', $overrides)) {
			$effective['assignment_mode'] = bvmgr_tasks_sanitize_assignment_mode((string) $overrides['assignment_mode']);
		}
		if (array_key_exists('role_key', $overrides)) {
			$effective['role_key'] = sanitize_key((string) $overrides['role_key']);
		}
		if (array_key_exists('assignee_user_id', $overrides)) {
			$effective['assignee_user_id'] = absint($overrides['assignee_user_id']);
		}
		if (array_key_exists('due_offset_minutes', $overrides)) {
			$effective['due_offset_minutes'] = (int) $overrides['due_offset_minutes'];
		}

		if ($effective['assignee_user_id'] <= 0) {
			$effective['assignee_user_id'] = 0;
		}

		return $effective;
	}
}

if (!function_exists('bvmgr_tasks_resolve_assignment_for_instance')) {
	/**
	 * @param array<string,mixed> $effective
	 * @return array<string,mixed>
	 */
	function bvmgr_tasks_resolve_assignment_for_instance(int $event_id, array $effective): array
	{
		$mode = bvmgr_tasks_sanitize_assignment_mode((string) ($effective['assignment_mode'] ?? 'role'));
		$role_key = sanitize_key((string) ($effective['role_key'] ?? ''));
		$user_id = absint($effective['assignee_user_id'] ?? 0);
		$result = array(
			'assignment_mode' => $mode,
			'role_key' => $role_key,
			'assignee_user_id' => 0,
			'resolution_action' => '',
		);

		if ($mode === 'person') {
			$result['assignee_user_id'] = $user_id > 0 ? $user_id : 0;
			return $result;
		}

		if ($mode === 'scheduled_role') {
			$resolved = bvmgr_tasks_resolve_scheduled_role_user_id($event_id, $role_key);
			$status = (string) ($resolved['status'] ?? 'none');
			if ($status === 'single') {
				$result['assignee_user_id'] = absint($resolved['assignee_user_id'] ?? 0);
				$result['resolution_action'] = 'assignment_resolved_from_scheduled_role';
			} elseif ($status === 'multiple') {
				$result['resolution_action'] = 'assignment_multiple_scheduled';
			} else {
				$result['resolution_action'] = 'assignment_none_scheduled';
			}
			return $result;
		}

		return $result;
	}
}

if (!function_exists('bvmgr_tasks_build_event_signature')) {
	/** @return array<string,mixed> */
	function bvmgr_tasks_build_event_signature(array $event_context): array
	{
		return array(
			'date_ymd' => (string) ($event_context['date_ymd'] ?? ''),
			'venue_id' => absint($event_context['venue_id'] ?? 0),
			'event_type' => sanitize_key((string) ($event_context['event_type'] ?? '')),
		);
	}
}

if (!function_exists('bvmgr_tasks_decode_stored_event_signature')) {
	/**
	 * @param mixed $raw
	 * @return array{state:string,signature:array<string,mixed>,reason:string}
	 */
	function bvmgr_tasks_decode_stored_event_signature($raw): array
	{
		if ($raw === null) {
			return array(
				'state' => 'missing',
				'signature' => array(),
				'reason' => 'missing_value',
			);
		}
		if (!is_string($raw)) {
			return array(
				'state' => 'invalid',
				'signature' => array(),
				'reason' => 'non_string',
			);
		}

		$raw = trim($raw);
		if ($raw === '') {
			return array(
				'state' => 'missing',
				'signature' => array(),
				'reason' => 'blank_value',
			);
		}
		if ($raw[0] !== '{') {
			return array(
				'state' => 'invalid',
				'signature' => array(),
				'reason' => 'non_object_json',
			);
		}

		$decoded = json_decode($raw, true, 8);
		$error = json_last_error();
		if ($error !== JSON_ERROR_NONE) {
			$reason = 'json_decode_failed';
			if ($error === JSON_ERROR_DEPTH) {
				$reason = 'json_depth';
			} elseif ($error === JSON_ERROR_UTF8) {
				$reason = 'json_utf8';
			} elseif ($error === JSON_ERROR_SYNTAX) {
				$reason = 'json_syntax';
			}

			return array(
				'state' => 'invalid',
				'signature' => array(),
				'reason' => $reason,
			);
		}
		if (!is_array($decoded)) {
			return array(
				'state' => 'invalid',
				'signature' => array(),
				'reason' => 'non_array',
			);
		}

		$keys = array_keys($decoded);
		if ($decoded !== array() && $keys === range(0, count($decoded) - 1)) {
			return array(
				'state' => 'invalid',
				'signature' => array(),
				'reason' => 'list_json',
			);
		}
		if (!array_key_exists('date_ymd', $decoded) || !array_key_exists('venue_id', $decoded) || !array_key_exists('event_type', $decoded)) {
			return array(
				'state' => 'invalid',
				'signature' => array(),
				'reason' => 'missing_required_fields',
			);
		}
		if (!is_string($decoded['date_ymd'])) {
			return array(
				'state' => 'invalid',
				'signature' => array(),
				'reason' => 'date_ymd_type',
			);
		}

		$venue_id = $decoded['venue_id'];
		$is_numeric_string = is_string($venue_id) && preg_match('/^-?\d+$/', trim($venue_id)) === 1;
		if (!is_int($venue_id) && !$is_numeric_string) {
			return array(
				'state' => 'invalid',
				'signature' => array(),
				'reason' => 'venue_id_type',
			);
		}
		if (!is_string($decoded['event_type'])) {
			return array(
				'state' => 'invalid',
				'signature' => array(),
				'reason' => 'event_type_type',
			);
		}

		return array(
			'state' => 'valid',
			'signature' => array(
				'date_ymd' => trim($decoded['date_ymd']),
				'venue_id' => absint($venue_id),
				'event_type' => sanitize_key($decoded['event_type']),
			),
			'reason' => 'valid',
		);
	}
}

if (!function_exists('bvmgr_tasks_should_allow_supersede')) {
	function bvmgr_tasks_should_allow_supersede(int $event_id, array $event_context, array $settings): bool
	{
		$event_id = absint($event_id);
		if ($event_id <= 0) {
			return true;
		}
		$decoded_prev = bvmgr_tasks_decode_stored_event_signature(get_post_meta($event_id, bvmgr_tasks_signature_meta_key(), true));
		if (($decoded_prev['state'] ?? '') === 'missing') {
			return true;
		}
		if (($decoded_prev['state'] ?? '') !== 'valid') {
			return false;
		}

		$prev = is_array($decoded_prev['signature'] ?? null) ? $decoded_prev['signature'] : array();
		$current = bvmgr_tasks_build_event_signature($event_context);
		$changed_date = (string) ($prev['date_ymd'] ?? '') !== (string) ($current['date_ymd'] ?? '');
		$changed_venue = absint($prev['venue_id'] ?? 0) !== absint($current['venue_id'] ?? 0);
		$changed_type = sanitize_key((string) ($prev['event_type'] ?? '')) !== sanitize_key((string) ($current['event_type'] ?? ''));

		if ($changed_date && !empty($settings['regenerate_on_event_date_change'])) {
			return true;
		}
		if ($changed_venue && !empty($settings['regenerate_on_venue_change'])) {
			return true;
		}
		if ($changed_type && !empty($settings['regenerate_on_event_type_change'])) {
			return true;
		}

		return false;
	}
}

if (!function_exists('bvmgr_tasks_generate_for_event')) {
	/**
	 * @param array<string,mixed> $args
	 * @return array<string,mixed>|WP_Error
	 */
	function bvmgr_tasks_generate_for_event(int $event_id, array $args = array())
	{
        return bvmgr_tasks_generate_committed_event($event_id,$args);
	}
}

if (!function_exists('bvmgr_tasks_resolve_assignments_for_event')) {
	function bvmgr_tasks_resolve_assignments_for_event(int $event_id): array
	{
        return bvmgr_tasks_reconcile_event($event_id);
	}
}

if (!function_exists('bvmgr_tasks_collect_upcoming_event_ids')) {
	/** @return int[] */
	function bvmgr_tasks_collect_upcoming_event_ids(int $horizon_days): array
	{
		$horizon_days = max(1, min(365, $horizon_days));
		$tz = wp_timezone();
		$today = wp_date('Y-m-d', time(), $tz);
		$end = wp_date('Y-m-d', time() + ($horizon_days * DAY_IN_SECONDS), $tz);

		$k_date = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'date') : '_vms_event_date';
		if ($k_date === '') {
			$k_date = '_vms_event_date';
		}

		global $wpdb;
		$q = array();
		$t_posts = (is_object($wpdb) && isset($wpdb->posts) && is_string($wpdb->posts) && $wpdb->posts !== '') ? $wpdb->posts : '';
		$t_postmeta = (is_object($wpdb) && isset($wpdb->postmeta) && is_string($wpdb->postmeta) && $wpdb->postmeta !== '') ? $wpdb->postmeta : '';
		if ($t_posts !== '' && $t_postmeta !== '' && method_exists($wpdb, 'get_col') && method_exists($wpdb, 'prepare')) {
			/* phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Staff Tasks event-horizon reads query request-fresh event-date postmeta with prepared identifiers and bounded date filters so generator runs observe immediate Event Plan date edits. */
			$q = $wpdb->get_col($wpdb->prepare('SELECT p.ID FROM %i AS pm INNER JOIN %i AS p ON p.ID = pm.post_id WHERE p.post_type = %s AND p.post_status IN (%s, %s, %s, %s, %s) AND pm.meta_key = %s AND pm.meta_value >= %s AND pm.meta_value <= %s ORDER BY pm.meta_value ASC, p.ID ASC', $t_postmeta, $t_posts, 'vms_event_plan', 'publish', 'private', 'draft', 'pending', 'future', $k_date, $today, $end));
		}

		if (!is_array($q)) {
			return array();
		}
		return array_values(array_unique(array_filter(array_map('absint', $q))));
	}
}

if (!function_exists('bvmgr_tasks_run_nightly_generator')) {
	function bvmgr_tasks_run_nightly_generator(): void
	{
		if (!bvmgr_tasks_db_ready()) {
			bvmgr_record_operational_issue(
				'staff_tasks_schema_not_ready',
				array(
					'service' => 'staff_tasks',
					'operation' => 'nightly_generate',
					'status' => 'skipped',
				)
			);
			return;
		}

		$settings = bvmgr_tasks_get_settings();
		$plan_ids = bvmgr_tasks_collect_upcoming_event_ids((int) ($settings['horizon_days'] ?? 60));

		$summary = array(
			'events_checked' => 0,
			'instances_created' => 0,
			'instances_superseded' => 0,
			'assignment_resolutions_applied' => 0,
			'warnings' => 0,
		);

		foreach ($plan_ids as $event_id) {
			$run = bvmgr_tasks_generate_for_event($event_id, array('allow_supersede' => false));
			if (is_wp_error($run)) {
				bvmgr_record_operational_issue(
					'staff_tasks_nightly_event_failed',
					array(
						'service' => 'staff_tasks',
						'operation' => 'nightly_generate',
						'status' => 'failed',
						'plan_id' => absint($event_id),
					),
					$run
				);
				$summary['warnings']++;
				continue;
			}
			$summary['events_checked'] += absint($run['events_checked'] ?? 0);
			$summary['instances_created'] += absint($run['instances_created'] ?? 0);
			$summary['instances_superseded'] += absint($run['instances_superseded'] ?? 0);
			$summary['assignment_resolutions_applied'] += absint($run['assignment_resolutions_applied'] ?? 0);
			$summary['warnings'] += is_array($run['warnings'] ?? null) ? count((array) $run['warnings']) : 0;
		}

	}
}

if (!function_exists('bvmgr_tasks_schedule_nightly_generator')) {
	function bvmgr_tasks_schedule_nightly_generator(): void
	{
		$hook = defined('BVMGR_CRON_TASKS_NIGHTLY') ? (string) BVMGR_CRON_TASKS_NIGHTLY : 'vms_tasks_nightly_generator';
		if (wp_next_scheduled($hook)) {
			return;
		}

		$tz = wp_timezone();
		$now = new DateTimeImmutable('now', $tz);
		$run = $now->setTime(3, 10, 0);
		if ($run <= $now) {
			$run = $run->modify('+1 day');
		}
		wp_schedule_event($run->getTimestamp(), 'daily', $hook);
	}
}

if (!function_exists('bvmgr_tasks_generate_for_event_safe')) {
	function bvmgr_tasks_generate_for_event_safe(int $post_id, int $actor_user_id = 0): void
	{
		if (!bvmgr_tasks_db_ready()) {
			return;
		}

		$event_context = bvmgr_tasks_get_event_context($post_id);
		if (!is_array($event_context)) {
			return;
		}

		$settings = bvmgr_tasks_get_settings();
		$allow_supersede = bvmgr_tasks_should_allow_supersede($post_id, $event_context, $settings);
		$run = bvmgr_tasks_generate_for_event($post_id, array(
			'allow_supersede' => $allow_supersede,
			'actor_user_id' => $actor_user_id,
		));
		if (is_wp_error($run)) {
			bvmgr_record_operational_issue(
				'staff_tasks_event_generation_failed',
				array(
					'service' => 'staff_tasks',
					'operation' => 'generate_for_event',
					'status' => 'failed',
					'plan_id' => absint($post_id),
				),
				$run
			);
		}
	}
}

if (!function_exists('bvmgr_tasks_queue_generate_for_event')) {
	function bvmgr_tasks_queue_generate_for_event(int $post_id, int $actor_user_id = 0, string $reason = 'event_plan_save'): void
	{
		$post_id = absint($post_id);
		if ($post_id <= 0) {
			return;
		}

		$trace = function_exists('bvmgr_event_plan_perf_span_start')
			? bvmgr_event_plan_perf_span_start(
				'vms_tasks_queue_generate_for_event',
				$post_id,
				array(
					'job_name' => 'staff_tasks_generation',
					'reason' => $reason,
				)
			)
			: '';
		$actor_user_id = function_exists('bvmgr_event_plan_capture_actor_user_id')
			? bvmgr_event_plan_capture_actor_user_id($post_id, $actor_user_id, 'staff_tasks_queue')
			: absint($actor_user_id);


		$hook = 'vms_tasks_generate_for_event_queued';
		$args = array($post_id);
		$already_scheduled = (bool) wp_next_scheduled($hook, $args);
		$already_locked = function_exists('bvmgr_event_plan_perf_job_has_lock')
			? bvmgr_event_plan_perf_job_has_lock('staff_tasks_generation', $post_id)
			: false;
		$scheduled_now = false;
		if (!$already_locked && !$already_scheduled) {
			wp_schedule_single_event(time() + 240, $hook, $args);
			$scheduled_now = true;
			if (function_exists('bvmgr_event_plan_perf_job_set_lock')) {
				bvmgr_event_plan_perf_job_set_lock('staff_tasks_generation', $post_id, 'pending', 20 * MINUTE_IN_SECONDS);
			}
		}

		if (function_exists('bvmgr_event_plan_save_profiler_note')) {
			if ($already_locked) {
				bvmgr_event_plan_save_profiler_note('staff_tasks_queue', 'already_locked');
			} else {
				bvmgr_event_plan_save_profiler_note('staff_tasks_queue', $already_scheduled ? 'already_scheduled' : 'scheduled');
			}
		}
		if (function_exists('bvmgr_event_plan_perf_log')) {
			bvmgr_event_plan_perf_log(
				'vms_tasks_queue_generate_for_event',
				$post_id,
				array(
					'job_name' => 'staff_tasks_generation',
					'reason' => $reason,
					'actor_user_id' => $actor_user_id,
					'already_scheduled' => $already_scheduled ? 1 : 0,
					'already_locked' => $already_locked ? 1 : 0,
					'scheduled_now' => $scheduled_now ? 1 : 0,
				)
			);
		}

		// Avoid rewriting queue metadata on repeated editor saves while the same
		// generation job is already pending. This keeps title-only/Draft/Ready saves
		// from touching four staff-task meta rows over and over.
		if ($already_scheduled && sanitize_key((string) get_post_meta($post_id, '_vms_tasks_generation_queue_state', true)) === 'queued') {
			if (function_exists('bvmgr_event_plan_perf_span_finish')) {
				bvmgr_event_plan_perf_span_finish(
					'vms_tasks_queue_generate_for_event',
					$post_id,
					$trace,
					array(
						'job_name' => 'staff_tasks_generation',
						'reason' => $reason,
					)
				);
			}
			return;
		}

		update_post_meta($post_id, '_vms_tasks_generation_queue_state', 'queued');
		update_post_meta($post_id, '_vms_tasks_generation_queued_at', time());
		update_post_meta($post_id, '_vms_tasks_generation_actor_user_id', absint($actor_user_id));
		update_post_meta($post_id, '_vms_tasks_generation_queue_reason', sanitize_key($reason));

		if (function_exists('bvmgr_event_plan_perf_span_finish')) {
			bvmgr_event_plan_perf_span_finish(
				'vms_tasks_queue_generate_for_event',
				$post_id,
				$trace,
				array(
					'job_name' => 'staff_tasks_generation',
					'reason' => $reason,
				)
			);
		}
	}
}

if (!function_exists('bvmgr_tasks_run_queued_event_generation')) {
	function bvmgr_tasks_run_queued_event_generation(int $post_id): void
	{
		$post_id = absint($post_id);
		$trace = function_exists('bvmgr_event_plan_perf_span_start')
			? bvmgr_event_plan_perf_span_start('vms_tasks_run_queued_event_generation', $post_id, array('job_name' => 'staff_tasks_generation'))
			: '';
		if ($post_id <= 0 || get_post_type($post_id) !== 'vms_event_plan') {
			if (function_exists('bvmgr_event_plan_perf_job_clear_lock')) {
				bvmgr_event_plan_perf_job_clear_lock('staff_tasks_generation', $post_id);
			}
			if (function_exists('bvmgr_event_plan_perf_span_finish')) {
				bvmgr_event_plan_perf_span_finish('vms_tasks_run_queued_event_generation', $post_id, $trace, array('job_name' => 'staff_tasks_generation', 'skipped' => 1));
			}
			return;
		}

		$lock = function_exists('bvmgr_event_plan_perf_job_get_lock')
			? bvmgr_event_plan_perf_job_get_lock('staff_tasks_generation', $post_id)
			: array();
		if (($lock['state'] ?? '') === 'running') {
			if (function_exists('bvmgr_event_plan_perf_log')) {
				bvmgr_event_plan_perf_log(
					'vms_tasks_run_queued_event_generation',
					$post_id,
					array(
						'job_name' => 'staff_tasks_generation',
						'skipped' => 1,
						'skip_reason' => 'job_already_running',
					)
				);
			}
			if (function_exists('bvmgr_event_plan_perf_span_finish')) {
				bvmgr_event_plan_perf_span_finish('vms_tasks_run_queued_event_generation', $post_id, $trace, array('job_name' => 'staff_tasks_generation', 'skipped' => 1));
			}
			return;
		}

		if (function_exists('bvmgr_event_plan_perf_job_set_lock')) {
			bvmgr_event_plan_perf_job_set_lock('staff_tasks_generation', $post_id, 'running', 20 * MINUTE_IN_SECONDS);
		}

		$actor_user_id = absint(get_post_meta($post_id, '_vms_tasks_generation_actor_user_id', true));
		try {
			update_post_meta($post_id, '_vms_tasks_generation_queue_state', 'running');
            $run=bvmgr_tasks_generate_for_event($post_id,array('actor_user_id'=>$actor_user_id));
            if (is_wp_error($run)) { update_post_meta($post_id,'_vms_tasks_generation_queue_state','failed'); update_post_meta($post_id,'_vms_tasks_generation_error',$run->get_error_code()); return; }
            delete_post_meta($post_id,'_vms_tasks_generation_error');
			update_post_meta($post_id, '_vms_tasks_generation_queue_state', 'complete');
			update_post_meta($post_id, '_vms_tasks_generation_completed_at', time());
			delete_post_meta($post_id, bvmgr_tasks_pending_signature_meta_key());
		} finally {
			if (function_exists('bvmgr_event_plan_perf_job_clear_lock')) {
				bvmgr_event_plan_perf_job_clear_lock('staff_tasks_generation', $post_id);
			}
			if (function_exists('bvmgr_event_plan_perf_span_finish')) {
				bvmgr_event_plan_perf_span_finish('vms_tasks_run_queued_event_generation', $post_id, $trace, array('job_name' => 'staff_tasks_generation'));
			}
		}
	}
}

if (!function_exists('bvmgr_tasks_maybe_generate_on_event_save')) {
	function bvmgr_tasks_maybe_generate_on_event_save(int $post_id, WP_Post $post, bool $update): void
	{
		$deferred_state = function_exists('bvmgr_event_plan_save_profiler_deferred_state_for_post')
			? bvmgr_event_plan_save_profiler_deferred_state_for_post($post_id)
			: array();
		$deferred_context = is_array($deferred_state['context'] ?? null) ? $deferred_state['context'] : array();
		$trace = function_exists('bvmgr_event_plan_perf_span_start')
			? bvmgr_event_plan_perf_span_start(
				'vms_tasks_maybe_generate_on_event_save',
				$post_id,
				array(
					'job_name' => 'staff_tasks_generation',
					'create' => $update ? 0 : 1,
					'update' => $update ? 1 : 0,
					'old_status' => sanitize_key((string) ($deferred_context['transition_old_status'] ?? '')),
					'new_status' => sanitize_key((string) ($deferred_context['transition_new_status'] ?? $post->post_status)),
				)
			)
			: '';
		if ($post->post_type !== 'vms_event_plan') {
			if (function_exists('bvmgr_event_plan_perf_span_finish')) {
				bvmgr_event_plan_perf_span_finish('vms_tasks_maybe_generate_on_event_save', $post_id, $trace, array('job_name' => 'staff_tasks_generation', 'skipped' => 1));
			}
			return;
		}
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			if (function_exists('bvmgr_event_plan_perf_span_finish')) {
				bvmgr_event_plan_perf_span_finish('vms_tasks_maybe_generate_on_event_save', $post_id, $trace, array('job_name' => 'staff_tasks_generation', 'skipped' => 1));
			}
			return;
		}
		if (wp_is_post_revision($post_id)) {
			if (function_exists('bvmgr_event_plan_perf_span_finish')) {
				bvmgr_event_plan_perf_span_finish('vms_tasks_maybe_generate_on_event_save', $post_id, $trace, array('job_name' => 'staff_tasks_generation', 'skipped' => 1));
			}
			return;
		}
		if (!current_user_can('edit_post', $post_id)) {
			if (function_exists('bvmgr_event_plan_perf_span_finish')) {
				bvmgr_event_plan_perf_span_finish('vms_tasks_maybe_generate_on_event_save', $post_id, $trace, array('job_name' => 'staff_tasks_generation', 'skipped' => 1));
			}
			return;
		}
		if (
			function_exists('bvmgr_event_plan_save_profiler_is_featured_image_only')
			&& bvmgr_event_plan_save_profiler_is_featured_image_only((int) $post_id)
		) {
			if (function_exists('bvmgr_event_plan_save_profiler_note')) {
				bvmgr_event_plan_save_profiler_note('staff_tasks_queue', 'skipped_featured_image_only');
			}
			if (function_exists('bvmgr_event_plan_save_profiler_note_heavy_action')) {
				bvmgr_event_plan_save_profiler_note_heavy_action('staff_tasks_generation', 'skipped', 'featured_image_only');
			}
			if (function_exists('bvmgr_event_plan_perf_span_finish')) {
				bvmgr_event_plan_perf_span_finish('vms_tasks_maybe_generate_on_event_save', $post_id, $trace, array(
					'job_name' => 'staff_tasks_generation',
					'skipped' => 1,
					'skip_reason' => 'featured_image_only',
				));
			}
			return;
		}
		if (function_exists('bvmgr_event_plan_capture_actor_user_id')) {
			bvmgr_event_plan_capture_actor_user_id((int) $post_id, (int) get_current_user_id(), 'staff_tasks_save');
		}

		$event_context = function_exists('bvmgr_tasks_get_event_context') ? bvmgr_tasks_get_event_context((int) $post_id) : null;
		if (is_array($event_context)) {
			$current_signature = bvmgr_tasks_event_signature_json($event_context);
			$saved_signature = (string) get_post_meta((int) $post_id, bvmgr_tasks_signature_meta_key(), true);
			$pending_signature = (string) get_post_meta((int) $post_id, bvmgr_tasks_pending_signature_meta_key(), true);
			if ($current_signature !== '' && ($current_signature === $saved_signature || $current_signature === $pending_signature)) {
				if (function_exists('bvmgr_event_plan_save_profiler_note')) {
					bvmgr_event_plan_save_profiler_note('staff_tasks_queue', 'skipped_unchanged_signature');
				}
				if (function_exists('bvmgr_event_plan_perf_span_finish')) {
					bvmgr_event_plan_perf_span_finish('vms_tasks_maybe_generate_on_event_save', $post_id, $trace, array('job_name' => 'staff_tasks_generation', 'skipped' => 1));
				}
				return;
			}
			if ($current_signature !== '') {
				update_post_meta((int) $post_id, bvmgr_tasks_pending_signature_meta_key(), $current_signature);
			}
		}

		$defer = (bool) apply_filters('vms_tasks_defer_event_generation_on_save', true, absint($post_id), $post, (bool) $update);
		if ($defer && function_exists('bvmgr_tasks_queue_generate_for_event')) {
			bvmgr_tasks_queue_generate_for_event((int) $post_id, (int) get_current_user_id(), 'event_plan_save');
			if (function_exists('bvmgr_event_plan_perf_span_finish')) {
				bvmgr_event_plan_perf_span_finish('vms_tasks_maybe_generate_on_event_save', $post_id, $trace, array('job_name' => 'staff_tasks_generation'));
			}
			return;
		}

		try {
			bvmgr_tasks_generate_for_event_safe((int) $post_id, (int) get_current_user_id());
		} finally {
			if (function_exists('bvmgr_event_plan_perf_span_finish')) {
				bvmgr_event_plan_perf_span_finish('vms_tasks_maybe_generate_on_event_save', $post_id, $trace, array('job_name' => 'staff_tasks_generation'));
			}
		}
	}
}

add_action('save_post_vms_event_plan', 'bvmgr_tasks_maybe_generate_on_event_save', 30, 3);
add_action('vms_tasks_generate_for_event_queued', 'bvmgr_tasks_run_queued_event_generation', 10, 1);
add_action(defined('BVMGR_CRON_TASKS_NIGHTLY') ? (string) BVMGR_CRON_TASKS_NIGHTLY : 'vms_tasks_nightly_generator', 'bvmgr_tasks_run_nightly_generator');
// Cron scheduling is explicit; ordinary page reads never repair schedules.
add_action('vms_staffing_event_saved', 'bvmgr_tasks_resolve_assignments_for_event', 20, 1);
