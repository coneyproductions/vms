<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_tasks_signature_meta_key')) {
	function vms_tasks_signature_meta_key(): string
	{
		return '_vms_tasks_event_signature_v1';
	}
}

if (!function_exists('vms_tasks_pending_signature_meta_key')) {
	function vms_tasks_pending_signature_meta_key(): string
	{
		return '_vms_tasks_event_pending_signature_v1';
	}
}

if (!function_exists('vms_tasks_event_signature_json')) {
	function vms_tasks_event_signature_json(array $event_context): string
	{
		$json = wp_json_encode(vms_tasks_build_event_signature($event_context));
		return is_string($json) ? $json : '';
	}
}

if (!function_exists('vms_tasks_compute_due_at_local')) {
	function vms_tasks_compute_due_at_local(array $event_context, string $due_mode, ?int $due_offset_minutes, string $due_time_local = ''): ?string
	{
		$due_mode = vms_tasks_sanitize_due_mode($due_mode);
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

if (!function_exists('vms_tasks_merge_template_with_overrides')) {
	/**
	 * @param array<string,mixed> $template
	 * @param array<string,mixed> $overrides
	 * @return array<string,mixed>
	 */
	function vms_tasks_merge_template_with_overrides(array $template, array $overrides): array
	{
		$effective = array(
			'title' => (string) ($template['title'] ?? ''),
			'instructions' => (string) ($template['instructions'] ?? ''),
			'priority' => vms_tasks_sanitize_priority((string) ($template['priority'] ?? 'normal')),
			'is_required' => !empty($template['required_default']) ? 1 : 0,
			'due_mode' => vms_tasks_sanitize_due_mode((string) ($template['due_mode'] ?? 'none')),
			'due_offset_minutes' => (($template['due_offset_minutes'] ?? null) !== null ? (int) $template['due_offset_minutes'] : null),
			'due_time_local' => (string) ($template['due_time_local'] ?? ''),
			'assignment_mode' => vms_tasks_sanitize_assignment_mode((string) ($template['assignment_mode'] ?? 'role')),
			'role_key' => sanitize_key((string) ($template['role_key'] ?? '')),
			'assignee_user_id' => absint($template['assignee_user_id'] ?? 0),
		);

		if (array_key_exists('required_default', $overrides)) {
			$effective['is_required'] = !empty($overrides['required_default']) ? 1 : 0;
		}
		if (array_key_exists('priority', $overrides)) {
			$effective['priority'] = vms_tasks_sanitize_priority((string) $overrides['priority']);
		}
		if (array_key_exists('assignment_mode', $overrides)) {
			$effective['assignment_mode'] = vms_tasks_sanitize_assignment_mode((string) $overrides['assignment_mode']);
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

if (!function_exists('vms_tasks_resolve_assignment_for_instance')) {
	/**
	 * @param array<string,mixed> $effective
	 * @return array<string,mixed>
	 */
	function vms_tasks_resolve_assignment_for_instance(int $event_id, array $effective): array
	{
		$mode = vms_tasks_sanitize_assignment_mode((string) ($effective['assignment_mode'] ?? 'role'));
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
			$resolved = vms_tasks_resolve_scheduled_role_user_id($event_id, $role_key);
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

if (!function_exists('vms_tasks_build_event_signature')) {
	/** @return array<string,mixed> */
	function vms_tasks_build_event_signature(array $event_context): array
	{
		return array(
			'date_ymd' => (string) ($event_context['date_ymd'] ?? ''),
			'venue_id' => absint($event_context['venue_id'] ?? 0),
			'event_type' => sanitize_key((string) ($event_context['event_type'] ?? '')),
		);
	}
}

if (!function_exists('vms_tasks_should_allow_supersede')) {
	function vms_tasks_should_allow_supersede(int $event_id, array $event_context, array $settings): bool
	{
		$event_id = absint($event_id);
		if ($event_id <= 0) {
			return true;
		}
		$raw_prev = (string) get_post_meta($event_id, vms_tasks_signature_meta_key(), true);
		if ($raw_prev === '') {
			return true;
		}
		$prev = json_decode($raw_prev, true);
		if (!is_array($prev)) {
			return true;
		}

		$current = vms_tasks_build_event_signature($event_context);
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

if (!function_exists('vms_tasks_generate_for_event')) {
	/**
	 * @param array<string,mixed> $args
	 * @return array<string,mixed>|WP_Error
	 */
	function vms_tasks_generate_for_event(int $event_id, array $args = array())
	{
		$event_id = absint($event_id);
		if ($event_id <= 0) {
			return new WP_Error('vms_tasks_event_invalid', __('Event is invalid for task generation.', 'backstage-venue-manager'));
		}
		if (!vms_tasks_db_ready()) {
			return new WP_Error('vms_tasks_db_not_ready', __('Task tables are not available. Tasks generation is disabled until schema setup succeeds.', 'backstage-venue-manager'));
		}

		$event_context = vms_tasks_get_event_context($event_id);
		if (!is_array($event_context)) {
			return new WP_Error('vms_tasks_event_context_missing', __('Event context is incomplete for task generation.', 'backstage-venue-manager'));
		}

		$settings = vms_tasks_get_settings();
		$actor_user_id = absint($args['actor_user_id'] ?? 0);
		$allow_supersede = array_key_exists('allow_supersede', $args)
			? !empty($args['allow_supersede'])
			: vms_tasks_should_allow_supersede($event_id, $event_context, $settings);

		$summary = array(
			'event_id' => $event_id,
			'events_checked' => 1,
			'instances_created' => 0,
			'instances_superseded' => 0,
			'assignment_resolutions_applied' => 0,
			'duplicate_suppressed' => 0,
			'warnings' => array(),
			'allow_supersede' => $allow_supersede ? 1 : 0,
		);

		$checklists = vms_tasks_get_applicable_checklists((int) $event_context['venue_id'], (string) $event_context['event_type']);
		if (empty($checklists)) {
			update_post_meta($event_id, vms_tasks_signature_meta_key(), wp_json_encode(vms_tasks_build_event_signature($event_context)));
			return $summary;
		}

		$seen_templates = array();
		$ordered_items = array();
		foreach ($checklists as $checklist) {
			$checklist_id = absint($checklist['id'] ?? 0);
			if ($checklist_id <= 0) {
				continue;
			}
			$items = vms_tasks_get_checklist_items($checklist_id);
			foreach ($items as $item) {
				$template_id = absint($item['task_template_id'] ?? 0);
				if ($template_id <= 0) {
					continue;
				}
				if (isset($seen_templates[$template_id])) {
					$summary['duplicate_suppressed']++;
					continue;
				}
				$seen_templates[$template_id] = true;
				$ordered_items[] = array(
					'checklist_id' => $checklist_id,
					'template_id' => $template_id,
					'overrides' => is_array($item['overrides'] ?? null) ? $item['overrides'] : array(),
				);
			}
		}

		foreach ($ordered_items as $entry) {
			$template = vms_tasks_get_task_template((int) $entry['template_id']);
			if (!is_array($template)) {
				$summary['warnings'][] = sprintf(
					/* translators: %d is a task template id. */
					__('Task template #%d is missing and was skipped.', 'backstage-venue-manager'),
					(int) $entry['template_id']
				);
				continue;
			}
			if (empty($template['is_active'])) {
				continue;
			}

			$effective = vms_tasks_merge_template_with_overrides($template, (array) $entry['overrides']);
			$due_at_local = vms_tasks_compute_due_at_local(
				$event_context,
				(string) ($effective['due_mode'] ?? 'none'),
				(isset($effective['due_offset_minutes']) ? (int) $effective['due_offset_minutes'] : null),
				(string) ($effective['due_time_local'] ?? '')
			);

			$assignment = vms_tasks_resolve_assignment_for_instance($event_id, $effective);
			$existing = vms_tasks_select_existing_open_instance(
				$event_id,
				(int) $entry['template_id'],
				(int) $entry['checklist_id'],
				$due_at_local,
				$allow_supersede
			);
			if (is_array($existing)) {
				if ((string) ($assignment['resolution_action'] ?? '') === 'assignment_resolved_from_scheduled_role'
					&& empty($existing['assignment_locked'])
					&& absint($existing['assignee_user_id'] ?? 0) !== absint($assignment['assignee_user_id'] ?? 0)) {
					vms_tasks_update_instance_assignment((int) $existing['id'], (int) $assignment['assignee_user_id'], false, $actor_user_id > 0 ? $actor_user_id : null);
					$summary['assignment_resolutions_applied']++;
					vms_tasks_log_task_action((int) $existing['id'], 'assignment_resolved_from_scheduled_role', $actor_user_id > 0 ? $actor_user_id : null, wp_json_encode(array(
						'assignee_user_id' => absint($assignment['assignee_user_id'] ?? 0),
						'role_key' => (string) ($assignment['role_key'] ?? ''),
					)));
				}
				continue;
			}

			$inserted = vms_tasks_insert_instance(array(
				'task_template_id' => (int) $entry['template_id'],
				'origin_checklist_id' => (int) $entry['checklist_id'],
				'event_id' => $event_id,
				'venue_id' => (int) ($event_context['venue_id'] ?? 0),
				'event_type' => (string) ($event_context['event_type'] ?? ''),
				'title' => (string) ($effective['title'] ?? ''),
				'instructions' => (string) ($effective['instructions'] ?? ''),
				'priority' => (string) ($effective['priority'] ?? 'normal'),
				'is_required' => !empty($effective['is_required']) ? 1 : 0,
				'due_at_local' => $due_at_local,
				'status' => 'open',
				'assignment_mode' => (string) ($assignment['assignment_mode'] ?? 'role'),
				'role_key' => (string) ($assignment['role_key'] ?? ''),
				'assignee_user_id' => absint($assignment['assignee_user_id'] ?? 0),
			));
			if (is_wp_error($inserted)) {
				$summary['warnings'][] = $inserted->get_error_message();
				continue;
			}
			$instance_id = absint($inserted);
			$summary['instances_created']++;

			vms_tasks_log_task_action($instance_id, 'created_from_template', $actor_user_id > 0 ? $actor_user_id : null, wp_json_encode(array(
				'task_template_id' => (int) $entry['template_id'],
				'origin_checklist_id' => (int) $entry['checklist_id'],
				'due_at_local' => $due_at_local,
				'assignment_mode' => (string) ($assignment['assignment_mode'] ?? 'role'),
				'role_key' => (string) ($assignment['role_key'] ?? ''),
				'assignee_user_id' => absint($assignment['assignee_user_id'] ?? 0),
			)));

			if ((string) ($assignment['resolution_action'] ?? '') === 'assignment_resolved_from_scheduled_role') {
				$summary['assignment_resolutions_applied']++;
				vms_tasks_log_task_action($instance_id, 'assignment_resolved_from_scheduled_role', $actor_user_id > 0 ? $actor_user_id : null, wp_json_encode(array(
					'assignee_user_id' => absint($assignment['assignee_user_id'] ?? 0),
					'role_key' => (string) ($assignment['role_key'] ?? ''),
				)));
			}

			if ($allow_supersede) {
				$summary['instances_superseded'] += vms_tasks_supersede_open_instances(
					$event_id,
					(int) $entry['template_id'],
					(int) $entry['checklist_id'],
					$instance_id,
					$actor_user_id > 0 ? $actor_user_id : null
				);
			}
		}

		update_post_meta($event_id, vms_tasks_signature_meta_key(), wp_json_encode(vms_tasks_build_event_signature($event_context)));
		return $summary;
	}
}

if (!function_exists('vms_tasks_resolve_assignments_for_event')) {
	function vms_tasks_resolve_assignments_for_event(int $event_id): array
	{
		$event_id = absint($event_id);
		$result = array(
			'event_id' => $event_id,
			'resolved' => 0,
			'multiple' => 0,
			'none' => 0,
		);
		if ($event_id <= 0 || !vms_tasks_db_ready()) {
			return $result;
		}

		$rows = vms_tasks_get_instances(array(
			'event_id' => $event_id,
			'status' => 'open',
			'limit' => 1000,
		));
		foreach ($rows as $row) {
			if (!is_array($row)) {
				continue;
			}
			if ((string) ($row['assignment_mode'] ?? '') !== 'scheduled_role') {
				continue;
			}
			if (!empty($row['assignment_locked'])) {
				continue;
			}

			$instance_id = absint($row['id'] ?? 0);
			$resolved = vms_tasks_resolve_scheduled_role_user_id($event_id, (string) ($row['role_key'] ?? ''));
			$status = (string) ($resolved['status'] ?? 'none');
			if ($status === 'single') {
				$assignee_user_id = absint($resolved['assignee_user_id'] ?? 0);
				if ($assignee_user_id > 0 && absint($row['assignee_user_id'] ?? 0) !== $assignee_user_id) {
					vms_tasks_update_instance_assignment($instance_id, $assignee_user_id, false);
					vms_tasks_log_task_action($instance_id, 'assignment_resolved_from_scheduled_role', null, wp_json_encode(array(
						'assignee_user_id' => $assignee_user_id,
						'role_key' => sanitize_key((string) ($row['role_key'] ?? '')),
					)));
					$result['resolved']++;
				}
			} elseif ($status === 'multiple') {
				$result['multiple']++;
			} else {
				$result['none']++;
			}
		}

		return $result;
	}
}

if (!function_exists('vms_tasks_collect_upcoming_event_ids')) {
	/** @return int[] */
	function vms_tasks_collect_upcoming_event_ids(int $horizon_days): array
	{
		$horizon_days = max(1, min(365, $horizon_days));
		$tz = wp_timezone();
		$today = wp_date('Y-m-d', time(), $tz);
		$end = wp_date('Y-m-d', time() + ($horizon_days * DAY_IN_SECONDS), $tz);

		$k_date = function_exists('vms_meta_key') ? (string) vms_meta_key('event_plan', 'date') : '_vms_event_date';
		if ($k_date === '') {
			$k_date = '_vms_event_date';
		}

		$q = get_posts(array(
			'post_type' => 'vms_event_plan',
			'post_status' => array('publish', 'private', 'draft', 'pending', 'future'),
			'posts_per_page' => -1,
			'fields' => 'ids',
			'meta_key' => $k_date,
			'orderby' => 'meta_value',
			'order' => 'ASC',
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'key' => $k_date,
					'value' => $today,
					'compare' => '>=',
					'type' => 'DATE',
				),
				array(
					'key' => $k_date,
					'value' => $end,
					'compare' => '<=',
					'type' => 'DATE',
				),
			),
			'no_found_rows' => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => false,
		));

		if (!is_array($q)) {
			return array();
		}
		return array_values(array_unique(array_filter(array_map('absint', $q))));
	}
}

if (!function_exists('vms_tasks_run_nightly_generator')) {
	function vms_tasks_run_nightly_generator(): void
	{
		if (!vms_tasks_db_ready()) {
			error_log('[VMS Tasks] Nightly generator skipped: DB schema not ready.');
			return;
		}

		$settings = vms_tasks_get_settings();
		$plan_ids = vms_tasks_collect_upcoming_event_ids((int) ($settings['horizon_days'] ?? 60));

		$summary = array(
			'events_checked' => 0,
			'instances_created' => 0,
			'instances_superseded' => 0,
			'assignment_resolutions_applied' => 0,
			'warnings' => 0,
		);

		foreach ($plan_ids as $event_id) {
			$run = vms_tasks_generate_for_event($event_id, array('allow_supersede' => false));
			if (is_wp_error($run)) {
				$summary['warnings']++;
				continue;
			}
			$summary['events_checked'] += absint($run['events_checked'] ?? 0);
			$summary['instances_created'] += absint($run['instances_created'] ?? 0);
			$summary['instances_superseded'] += absint($run['instances_superseded'] ?? 0);
			$summary['assignment_resolutions_applied'] += absint($run['assignment_resolutions_applied'] ?? 0);
			$summary['warnings'] += is_array($run['warnings'] ?? null) ? count((array) $run['warnings']) : 0;
		}

		error_log('[VMS Tasks] nightly_generator ' . wp_json_encode($summary));
	}
}

if (!function_exists('vms_tasks_schedule_nightly_generator')) {
	function vms_tasks_schedule_nightly_generator(): void
	{
		if (function_exists('vms_should_run_runtime_maintenance') && !vms_should_run_runtime_maintenance()) {
			return;
		}
		$hook = defined('VMS_CRON_TASKS_NIGHTLY') ? (string) VMS_CRON_TASKS_NIGHTLY : 'vms_tasks_nightly_generator';
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

if (!function_exists('vms_tasks_generate_for_event_safe')) {
	function vms_tasks_generate_for_event_safe(int $post_id, int $actor_user_id = 0): void
	{
		if (!vms_tasks_db_ready()) {
			return;
		}

		$event_context = vms_tasks_get_event_context($post_id);
		if (!is_array($event_context)) {
			return;
		}

		$settings = vms_tasks_get_settings();
		$allow_supersede = vms_tasks_should_allow_supersede($post_id, $event_context, $settings);
		$run = vms_tasks_generate_for_event($post_id, array(
			'allow_supersede' => $allow_supersede,
			'actor_user_id' => $actor_user_id,
		));
		if (is_wp_error($run)) {
			error_log('[VMS Tasks] event generation failed: ' . $run->get_error_message());
		}
	}
}

if (!function_exists('vms_tasks_queue_generate_for_event')) {
	function vms_tasks_queue_generate_for_event(int $post_id, int $actor_user_id = 0, string $reason = 'event_plan_save'): void
	{
		$post_id = absint($post_id);
		if ($post_id <= 0) {
			return;
		}

		$trace = function_exists('vms_event_plan_perf_span_start')
			? vms_event_plan_perf_span_start(
				'vms_tasks_queue_generate_for_event',
				$post_id,
				array(
					'job_name' => 'staff_tasks_generation',
					'reason' => $reason,
				)
			)
			: '';
		$actor_user_id = function_exists('vms_event_plan_capture_actor_user_id')
			? vms_event_plan_capture_actor_user_id($post_id, $actor_user_id, 'staff_tasks_queue')
			: absint($actor_user_id);

		if (function_exists('vms_event_plan_has_effective_tickets') && !vms_event_plan_has_effective_tickets($post_id)) {
			if (function_exists('vms_event_plan_perf_log')) {
				vms_event_plan_perf_log(
					'vms_tasks_queue_generate_for_event',
					$post_id,
					array(
						'job_name' => 'staff_tasks_generation',
						'reason' => $reason,
						'skipped' => 1,
						'skip_reason' => 'no_effective_tickets',
						'actor_user_id' => $actor_user_id,
					)
				);
			}
			if (function_exists('vms_event_plan_save_profiler_note')) {
				vms_event_plan_save_profiler_note('staff_tasks_queue', 'skipped_no_effective_tickets');
			}
			if (function_exists('vms_event_plan_perf_span_finish')) {
				vms_event_plan_perf_span_finish(
					'vms_tasks_queue_generate_for_event',
					$post_id,
					$trace,
					array(
						'job_name' => 'staff_tasks_generation',
						'reason' => $reason,
						'skipped' => 1,
					)
				);
			}
			return;
		}

		$hook = 'vms_tasks_generate_for_event_queued';
		$args = array($post_id);
		$already_scheduled = (bool) wp_next_scheduled($hook, $args);
		$already_locked = function_exists('vms_event_plan_perf_job_has_lock')
			? vms_event_plan_perf_job_has_lock('staff_tasks_generation', $post_id)
			: false;
		$scheduled_now = false;
		if (!$already_locked && !$already_scheduled) {
			wp_schedule_single_event(time() + 240, $hook, $args);
			$scheduled_now = true;
			if (function_exists('vms_event_plan_perf_job_set_lock')) {
				vms_event_plan_perf_job_set_lock('staff_tasks_generation', $post_id, 'pending', 20 * MINUTE_IN_SECONDS);
			}
		}

		if (function_exists('vms_event_plan_save_profiler_note')) {
			if ($already_locked) {
				vms_event_plan_save_profiler_note('staff_tasks_queue', 'already_locked');
			} else {
				vms_event_plan_save_profiler_note('staff_tasks_queue', $already_scheduled ? 'already_scheduled' : 'scheduled');
			}
		}
		if (function_exists('vms_event_plan_perf_log')) {
			vms_event_plan_perf_log(
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
			if (function_exists('vms_event_plan_perf_span_finish')) {
				vms_event_plan_perf_span_finish(
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

		if (function_exists('vms_event_plan_perf_span_finish')) {
			vms_event_plan_perf_span_finish(
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

if (!function_exists('vms_tasks_run_queued_event_generation')) {
	function vms_tasks_run_queued_event_generation(int $post_id): void
	{
		$post_id = absint($post_id);
		$trace = function_exists('vms_event_plan_perf_span_start')
			? vms_event_plan_perf_span_start('vms_tasks_run_queued_event_generation', $post_id, array('job_name' => 'staff_tasks_generation'))
			: '';
		if ($post_id <= 0 || get_post_type($post_id) !== 'vms_event_plan') {
			if (function_exists('vms_event_plan_perf_job_clear_lock')) {
				vms_event_plan_perf_job_clear_lock('staff_tasks_generation', $post_id);
			}
			if (function_exists('vms_event_plan_perf_span_finish')) {
				vms_event_plan_perf_span_finish('vms_tasks_run_queued_event_generation', $post_id, $trace, array('job_name' => 'staff_tasks_generation', 'skipped' => 1));
			}
			return;
		}

		$lock = function_exists('vms_event_plan_perf_job_get_lock')
			? vms_event_plan_perf_job_get_lock('staff_tasks_generation', $post_id)
			: array();
		if (($lock['state'] ?? '') === 'running') {
			if (function_exists('vms_event_plan_perf_log')) {
				vms_event_plan_perf_log(
					'vms_tasks_run_queued_event_generation',
					$post_id,
					array(
						'job_name' => 'staff_tasks_generation',
						'skipped' => 1,
						'skip_reason' => 'job_already_running',
					)
				);
			}
			if (function_exists('vms_event_plan_perf_span_finish')) {
				vms_event_plan_perf_span_finish('vms_tasks_run_queued_event_generation', $post_id, $trace, array('job_name' => 'staff_tasks_generation', 'skipped' => 1));
			}
			return;
		}

		if (function_exists('vms_event_plan_has_effective_tickets') && !vms_event_plan_has_effective_tickets($post_id)) {
			update_post_meta($post_id, '_vms_tasks_generation_queue_state', 'skipped');
			update_post_meta($post_id, '_vms_tasks_generation_completed_at', time());
			delete_post_meta($post_id, vms_tasks_pending_signature_meta_key());
			if (function_exists('vms_event_plan_perf_log')) {
				vms_event_plan_perf_log(
					'vms_tasks_run_queued_event_generation',
					$post_id,
					array(
						'job_name' => 'staff_tasks_generation',
						'skipped' => 1,
						'skip_reason' => 'no_effective_tickets',
					)
				);
			}
			if (function_exists('vms_event_plan_perf_job_clear_lock')) {
				vms_event_plan_perf_job_clear_lock('staff_tasks_generation', $post_id);
			}
			if (function_exists('vms_event_plan_perf_span_finish')) {
				vms_event_plan_perf_span_finish('vms_tasks_run_queued_event_generation', $post_id, $trace, array('job_name' => 'staff_tasks_generation', 'skipped' => 1));
			}
			return;
		}

		if (function_exists('vms_event_plan_perf_job_set_lock')) {
			vms_event_plan_perf_job_set_lock('staff_tasks_generation', $post_id, 'running', 20 * MINUTE_IN_SECONDS);
		}

		$actor_user_id = absint(get_post_meta($post_id, '_vms_tasks_generation_actor_user_id', true));
		try {
			update_post_meta($post_id, '_vms_tasks_generation_queue_state', 'running');
			vms_tasks_generate_for_event_safe($post_id, $actor_user_id);
			update_post_meta($post_id, '_vms_tasks_generation_queue_state', 'complete');
			update_post_meta($post_id, '_vms_tasks_generation_completed_at', time());
			delete_post_meta($post_id, vms_tasks_pending_signature_meta_key());
		} finally {
			if (function_exists('vms_event_plan_perf_job_clear_lock')) {
				vms_event_plan_perf_job_clear_lock('staff_tasks_generation', $post_id);
			}
			if (function_exists('vms_event_plan_perf_span_finish')) {
				vms_event_plan_perf_span_finish('vms_tasks_run_queued_event_generation', $post_id, $trace, array('job_name' => 'staff_tasks_generation'));
			}
		}
	}
}

if (!function_exists('vms_tasks_maybe_generate_on_event_save')) {
	function vms_tasks_maybe_generate_on_event_save(int $post_id, WP_Post $post, bool $update): void
	{
		$deferred_state = function_exists('vms_event_plan_save_profiler_deferred_state_for_post')
			? vms_event_plan_save_profiler_deferred_state_for_post($post_id)
			: array();
		$deferred_context = is_array($deferred_state['context'] ?? null) ? $deferred_state['context'] : array();
		$trace = function_exists('vms_event_plan_perf_span_start')
			? vms_event_plan_perf_span_start(
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
			if (function_exists('vms_event_plan_perf_span_finish')) {
				vms_event_plan_perf_span_finish('vms_tasks_maybe_generate_on_event_save', $post_id, $trace, array('job_name' => 'staff_tasks_generation', 'skipped' => 1));
			}
			return;
		}
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			if (function_exists('vms_event_plan_perf_span_finish')) {
				vms_event_plan_perf_span_finish('vms_tasks_maybe_generate_on_event_save', $post_id, $trace, array('job_name' => 'staff_tasks_generation', 'skipped' => 1));
			}
			return;
		}
		if (wp_is_post_revision($post_id)) {
			if (function_exists('vms_event_plan_perf_span_finish')) {
				vms_event_plan_perf_span_finish('vms_tasks_maybe_generate_on_event_save', $post_id, $trace, array('job_name' => 'staff_tasks_generation', 'skipped' => 1));
			}
			return;
		}
		if (!current_user_can('edit_post', $post_id)) {
			if (function_exists('vms_event_plan_perf_span_finish')) {
				vms_event_plan_perf_span_finish('vms_tasks_maybe_generate_on_event_save', $post_id, $trace, array('job_name' => 'staff_tasks_generation', 'skipped' => 1));
			}
			return;
		}
		if (
			function_exists('vms_event_plan_save_profiler_is_featured_image_only')
			&& vms_event_plan_save_profiler_is_featured_image_only((int) $post_id)
		) {
			if (function_exists('vms_event_plan_save_profiler_note')) {
				vms_event_plan_save_profiler_note('staff_tasks_queue', 'skipped_featured_image_only');
			}
			if (function_exists('vms_event_plan_save_profiler_note_heavy_action')) {
				vms_event_plan_save_profiler_note_heavy_action('staff_tasks_generation', 'skipped', 'featured_image_only');
			}
			if (function_exists('vms_event_plan_perf_span_finish')) {
				vms_event_plan_perf_span_finish('vms_tasks_maybe_generate_on_event_save', $post_id, $trace, array(
					'job_name' => 'staff_tasks_generation',
					'skipped' => 1,
					'skip_reason' => 'featured_image_only',
				));
			}
			return;
		}
		if (function_exists('vms_event_plan_capture_actor_user_id')) {
			vms_event_plan_capture_actor_user_id((int) $post_id, (int) get_current_user_id(), 'staff_tasks_save');
		}
		if (function_exists('vms_event_plan_has_effective_tickets') && !vms_event_plan_has_effective_tickets((int) $post_id)) {
			delete_post_meta((int) $post_id, vms_tasks_pending_signature_meta_key());
			if (function_exists('vms_event_plan_save_profiler_note')) {
				vms_event_plan_save_profiler_note('staff_tasks_queue', 'skipped_no_effective_tickets');
			}
			if (function_exists('vms_event_plan_perf_log')) {
				vms_event_plan_perf_log(
					'vms_tasks_maybe_generate_on_event_save',
					(int) $post_id,
					array(
						'job_name' => 'staff_tasks_generation',
						'skipped' => 1,
						'skip_reason' => 'no_effective_tickets',
					)
				);
			}
			if (function_exists('vms_event_plan_perf_span_finish')) {
				vms_event_plan_perf_span_finish('vms_tasks_maybe_generate_on_event_save', $post_id, $trace, array('job_name' => 'staff_tasks_generation', 'skipped' => 1));
			}
			return;
		}

		$event_context = function_exists('vms_tasks_get_event_context') ? vms_tasks_get_event_context((int) $post_id) : null;
		if (is_array($event_context)) {
			$current_signature = vms_tasks_event_signature_json($event_context);
			$saved_signature = (string) get_post_meta((int) $post_id, vms_tasks_signature_meta_key(), true);
			$pending_signature = (string) get_post_meta((int) $post_id, vms_tasks_pending_signature_meta_key(), true);
			if ($current_signature !== '' && ($current_signature === $saved_signature || $current_signature === $pending_signature)) {
				if (function_exists('vms_event_plan_save_profiler_note')) {
					vms_event_plan_save_profiler_note('staff_tasks_queue', 'skipped_unchanged_signature');
				}
				if (function_exists('vms_event_plan_perf_span_finish')) {
					vms_event_plan_perf_span_finish('vms_tasks_maybe_generate_on_event_save', $post_id, $trace, array('job_name' => 'staff_tasks_generation', 'skipped' => 1));
				}
				return;
			}
			if ($current_signature !== '') {
				update_post_meta((int) $post_id, vms_tasks_pending_signature_meta_key(), $current_signature);
			}
		}

		$defer = (bool) apply_filters('vms_tasks_defer_event_generation_on_save', true, absint($post_id), $post, (bool) $update);
		if ($defer && function_exists('vms_tasks_queue_generate_for_event')) {
			vms_tasks_queue_generate_for_event((int) $post_id, (int) get_current_user_id(), 'event_plan_save');
			if (function_exists('vms_event_plan_perf_span_finish')) {
				vms_event_plan_perf_span_finish('vms_tasks_maybe_generate_on_event_save', $post_id, $trace, array('job_name' => 'staff_tasks_generation'));
			}
			return;
		}

		try {
			vms_tasks_generate_for_event_safe((int) $post_id, (int) get_current_user_id());
		} finally {
			if (function_exists('vms_event_plan_perf_span_finish')) {
				vms_event_plan_perf_span_finish('vms_tasks_maybe_generate_on_event_save', $post_id, $trace, array('job_name' => 'staff_tasks_generation'));
			}
		}
	}
}

add_action('save_post_vms_event_plan', 'vms_tasks_maybe_generate_on_event_save', 30, 3);
add_action('vms_tasks_generate_for_event_queued', 'vms_tasks_run_queued_event_generation', 10, 1);
add_action(defined('VMS_CRON_TASKS_NIGHTLY') ? (string) VMS_CRON_TASKS_NIGHTLY : 'vms_tasks_nightly_generator', 'vms_tasks_run_nightly_generator');
add_action('init', 'vms_tasks_schedule_nightly_generator', 20);
add_action('vms_staffing_event_saved', 'vms_tasks_resolve_assignments_for_event', 20, 1);
