<?php
defined('ABSPATH') || exit;

if (!function_exists('bvmgr_tasks_now_utc_mysql')) {
	function bvmgr_tasks_now_utc_mysql(): string
	{
		return current_time('mysql', true);
	}
}

if (!function_exists('bvmgr_tasks_now_local_mysql')) {
	function bvmgr_tasks_now_local_mysql(): string
	{
		return current_time('mysql', false);
	}
}

if (!function_exists('bvmgr_tasks_allowed_priorities')) {
	/** @return string[] */
	function bvmgr_tasks_allowed_priorities(): array
	{
		return array('low', 'normal', 'high');
	}
}

if (!function_exists('bvmgr_tasks_sanitize_priority')) {
	function bvmgr_tasks_sanitize_priority(string $priority): string
	{
		$priority = sanitize_key($priority);
		return in_array($priority, bvmgr_tasks_allowed_priorities(), true) ? $priority : 'normal';
	}
}

if (!function_exists('bvmgr_tasks_sanitize_scope')) {
	function bvmgr_tasks_sanitize_scope(string $scope): string
	{
		$scope = sanitize_key($scope);
		if ($scope === 'calendar') {
			$scope = 'general';
		}
		return in_array($scope, array('event', 'general'), true) ? $scope : 'event';
	}
}

if (!function_exists('bvmgr_tasks_sanitize_due_mode')) {
	function bvmgr_tasks_sanitize_due_mode(string $mode): string
	{
		$mode = sanitize_key($mode);
		return in_array($mode, array('event_offset', 'fixed_datetime', 'none'), true) ? $mode : 'none';
	}
}

if (!function_exists('bvmgr_tasks_sanitize_assignment_mode')) {
	function bvmgr_tasks_sanitize_assignment_mode(string $mode): string
	{
		$mode = sanitize_key($mode);
		return in_array($mode, array('role', 'person', 'scheduled_role'), true) ? $mode : 'role';
	}
}

if (!function_exists('bvmgr_tasks_sanitize_apply_mode')) {
	function bvmgr_tasks_sanitize_apply_mode(string $mode): string
	{
		$mode = sanitize_key($mode);
		return in_array($mode, array('default_all_events', 'by_venue', 'by_event_type'), true) ? $mode : 'default_all_events';
	}
}

if (!function_exists('bvmgr_tasks_sanitize_status')) {
	function bvmgr_tasks_sanitize_status(string $status): string
	{
		$status = sanitize_key($status);
		return in_array($status, array('open', 'done', 'skipped', 'canceled', 'superseded'), true) ? $status : 'open';
	}
}

if (!function_exists('bvmgr_tasks_allowed_recurrence_patterns')) {
	/** @return string[] */
	function bvmgr_tasks_allowed_recurrence_patterns(): array
	{
		return array('none', 'daily', 'every_n_days', 'weekly', 'monthly', 'quarterly', 'semi_annual', 'annual');
	}
}

if (!function_exists('bvmgr_tasks_sanitize_recurrence_pattern')) {
	function bvmgr_tasks_sanitize_recurrence_pattern(string $pattern): string
	{
		$pattern = sanitize_key($pattern);
		return in_array($pattern, bvmgr_tasks_allowed_recurrence_patterns(), true) ? $pattern : 'none';
	}
}

if (!function_exists('bvmgr_tasks_normalize_recurrence_every_n_days')) {
	function bvmgr_tasks_normalize_recurrence_every_n_days(string $pattern, $value): ?int
	{
		$pattern = bvmgr_tasks_sanitize_recurrence_pattern($pattern);
		if ($pattern !== 'every_n_days') {
			return null;
		}

		$days = (int) $value;
		if ($days < 2) {
			$days = 2;
		}
		if ($days > 365) {
			$days = 365;
		}

		return $days;
	}
}

if (!function_exists('bvmgr_tasks_recurrence_label')) {
	function bvmgr_tasks_recurrence_label(string $pattern, int $every_n_days = 0): string
	{
		$pattern = bvmgr_tasks_sanitize_recurrence_pattern($pattern);
		if ($pattern === 'daily') {
			return __('Daily', 'backstage-venue-manager');
		}
		if ($pattern === 'every_n_days') {
			$days = (int) bvmgr_tasks_normalize_recurrence_every_n_days($pattern, $every_n_days);
			return sprintf(
				/* translators: %d is a number of days. */
				__('Every %d days', 'backstage-venue-manager'),
				$days
			);
		}
		if ($pattern === 'weekly') {
			return __('Weekly', 'backstage-venue-manager');
		}
		if ($pattern === 'monthly') {
			return __('Monthly', 'backstage-venue-manager');
		}
		if ($pattern === 'quarterly') {
			return __('Quarterly', 'backstage-venue-manager');
		}
		if ($pattern === 'semi_annual') {
			return __('Semi-annually', 'backstage-venue-manager');
		}
		if ($pattern === 'annual') {
			return __('Annually', 'backstage-venue-manager');
		}
		return __('Does not repeat', 'backstage-venue-manager');
	}
}

if (!function_exists('bvmgr_tasks_recurrence_next_due_local')) {
	function bvmgr_tasks_recurrence_next_due_local(string $due_at_local, string $pattern, ?int $every_n_days = null): ?string
	{
		$due_at_local = sanitize_text_field($due_at_local);
		if ($due_at_local === '') {
			return null;
		}

		$pattern = bvmgr_tasks_sanitize_recurrence_pattern($pattern);
		if ($pattern === 'none') {
			return null;
		}

		$tz = wp_timezone();
		try {
			$base = new DateTimeImmutable($due_at_local, $tz);
		} catch (Exception $e) {
			return null;
		}

		if ($pattern === 'daily') {
			return $base->modify('+1 day')->format('Y-m-d H:i:s');
		}
		if ($pattern === 'every_n_days') {
			$days = (int) bvmgr_tasks_normalize_recurrence_every_n_days($pattern, $every_n_days);
			return $base->modify('+' . $days . ' days')->format('Y-m-d H:i:s');
		}
		if ($pattern === 'weekly') {
			return $base->modify('+1 week')->format('Y-m-d H:i:s');
		}
		if ($pattern === 'monthly') {
			return $base->modify('+1 month')->format('Y-m-d H:i:s');
		}
		if ($pattern === 'quarterly') {
			return $base->modify('+3 months')->format('Y-m-d H:i:s');
		}
		if ($pattern === 'semi_annual') {
			return $base->modify('+6 months')->format('Y-m-d H:i:s');
		}
		if ($pattern === 'annual') {
			return $base->modify('+1 year')->format('Y-m-d H:i:s');
		}

		return null;
	}
}

if (!function_exists('bvmgr_tasks_log_task_action')) {
	function bvmgr_tasks_log_task_action(int $task_instance_id, string $action, ?int $actor_user_id = null, string $details = ''): void
	{
		global $wpdb;
		$table = bvmgr_tasks_table_name('task_logs');
		if ($table === '') {
			return;
		}

		$task_instance_id = absint($task_instance_id);
		if ($task_instance_id <= 0) {
			return;
		}

		$action = sanitize_key($action);
		if ($action === '') {
			$action = 'unknown';
		}

		if ($actor_user_id === null) {
			$actor_user_id = absint(get_current_user_id());
		}
		$actor_user_id = absint($actor_user_id);
		if ($actor_user_id <= 0) {
			$actor_user_id = null;
		}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Staff Tasks task-action logs write normalized custom-table audit rows through wpdb::insert(); no core API preserves this repository contract.
			$wpdb->insert(
				$table,
				array(
					'task_instance_id' => $task_instance_id,
				'action' => $action,
				'actor_user_id' => $actor_user_id,
				'details' => ($details !== '' ? $details : null),
				'created_at' => bvmgr_tasks_now_utc_mysql(),
			),
			array('%d', '%s', '%d', '%s', '%s')
		);
	}
}

if (!function_exists('bvmgr_tasks_has_task_action_log')) {
	function bvmgr_tasks_has_task_action_log(int $task_instance_id, string $action): bool
	{
		global $wpdb;
		$table = bvmgr_tasks_table_name('task_logs');
		$task_instance_id = absint($task_instance_id);
		$action = sanitize_key($action);
		if ($table === '' || $task_instance_id <= 0 || $action === '') {
			return false;
		}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Staff Tasks task-action log lookups read a custom repository table with %i/%d/%s-prepared values, and the generator/notification flows must observe fresh request-local state after writes.
			$found = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT id FROM %i WHERE task_instance_id = %d AND action = %s LIMIT 1',
					$table,
					$task_instance_id,
					$action
				)
		);
		return !empty($found);
	}
}

if (!function_exists('bvmgr_tasks_get_task_template')) {
	/** @return array<string,mixed>|null */
	function bvmgr_tasks_get_task_template(int $template_id): ?array
	{
		global $wpdb;
		$table = bvmgr_tasks_table_name('task_templates');
		$template_id = absint($template_id);
		if ($table === '' || $template_id <= 0) {
			return null;
		}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Staff Tasks task-template reads target a custom repository table with %i/%d-prepared identifiers and IDs, and template edits must remain immediately visible without a persistent cache layer.
			$row = $wpdb->get_row(
				$wpdb->prepare('SELECT * FROM %i WHERE id = %d LIMIT 1', $table, $template_id),
				ARRAY_A
			);
		return is_array($row) ? $row : null;
	}
}

if (!function_exists('bvmgr_tasks_get_task_templates')) {
	/**
	 * @param array<string,mixed> $filters
	 * @return array<int,array<string,mixed>>
	 */
	function bvmgr_tasks_get_task_templates(array $filters = array()): array
	{
		global $wpdb;
		$table = bvmgr_tasks_table_name('task_templates');
		if ($table === '') {
			return array();
		}

		$has_is_active = array_key_exists('is_active', $filters);
		$is_active = $has_is_active && !empty($filters['is_active']) ? 1 : 0;
		$scope = !empty($filters['scope']) ? bvmgr_tasks_sanitize_scope((string) $filters['scope']) : '';

		if ($has_is_active && $scope !== '') {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Staff Tasks task-template lists read a custom repository with prepared identifier/filter values, and admin pages must reflect task-template mutations immediately.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE 1=1 AND is_active = %d AND scope = %s ORDER BY is_active DESC, title ASC, id ASC',
					$table,
					$is_active,
					$scope
				),
				ARRAY_A
			);
			return is_array($rows) ? $rows : array();
		}

		if ($has_is_active) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Staff Tasks task-template lists read a custom repository with prepared identifier/filter values, and admin pages must reflect task-template mutations immediately.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE 1=1 AND is_active = %d ORDER BY is_active DESC, title ASC, id ASC',
					$table,
					$is_active
				),
				ARRAY_A
			);
			return is_array($rows) ? $rows : array();
		}

		if ($scope !== '') {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Staff Tasks task-template lists read a custom repository with prepared identifier/filter values, and admin pages must reflect task-template mutations immediately.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE 1=1 AND scope = %s ORDER BY is_active DESC, title ASC, id ASC',
					$table,
					$scope
				),
				ARRAY_A
			);
			return is_array($rows) ? $rows : array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Staff Tasks task-template lists read a custom repository with prepared identifier/filter values, and admin pages must reflect task-template mutations immediately.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE 1=1 ORDER BY is_active DESC, title ASC, id ASC',
				$table
			),
			ARRAY_A
		);
		return is_array($rows) ? $rows : array();
	}
}

if (!function_exists('bvmgr_tasks_upsert_task_template')) {
	/**
	 * @param array<string,mixed> $payload
	 * @return int|WP_Error
	 */
	function bvmgr_tasks_upsert_task_template(array $payload, int $template_id = 0)
	{
		global $wpdb;
		$table = bvmgr_tasks_table_name('task_templates');
		if ($table === '') {
			return new WP_Error('vms_tasks_table_missing', __('Task template table is unavailable.', 'backstage-venue-manager'));
		}

		$title = sanitize_text_field((string) ($payload['title'] ?? ''));
		if ($title === '') {
			return new WP_Error('vms_tasks_template_title_required', __('Task template title is required.', 'backstage-venue-manager'));
		}

		$instructions = wp_kses_post((string) ($payload['instructions'] ?? ''));
		$is_active = !empty($payload['is_active']) ? 1 : 0;
		$priority = bvmgr_tasks_sanitize_priority((string) ($payload['priority'] ?? 'normal'));
		$required_default = !empty($payload['required_default']) ? 1 : 0;
		$scope = bvmgr_tasks_sanitize_scope((string) ($payload['scope'] ?? 'event'));
		$due_mode = bvmgr_tasks_sanitize_due_mode((string) ($payload['due_mode'] ?? 'none'));
		$due_offset_minutes = ($payload['due_offset_minutes'] !== '' && $payload['due_offset_minutes'] !== null)
			? (int) $payload['due_offset_minutes']
			: null;
		$due_time_local = trim((string) ($payload['due_time_local'] ?? ''));
		if ($due_time_local !== '' && !preg_match('/^\d{2}:\d{2}$/', $due_time_local)) {
			$due_time_local = '';
		}
		$assignment_mode = bvmgr_tasks_sanitize_assignment_mode((string) ($payload['assignment_mode'] ?? 'role'));
		$role_key = sanitize_key((string) ($payload['role_key'] ?? ''));
		if ($role_key === '') {
			$role_key = null;
		}
		$assignee_user_id = absint($payload['assignee_user_id'] ?? 0);
		if ($assignee_user_id <= 0) {
			$assignee_user_id = null;
		}

		$now = bvmgr_tasks_now_utc_mysql();
		$data = array(
			'title' => $title,
			'instructions' => $instructions,
			'is_active' => $is_active,
			'priority' => $priority,
			'required_default' => $required_default,
			'scope' => $scope,
			'due_mode' => $due_mode,
			'due_offset_minutes' => $due_offset_minutes,
			'due_time_local' => ($due_time_local !== '' ? $due_time_local : null),
			'assignment_mode' => $assignment_mode,
			'role_key' => $role_key,
			'assignee_user_id' => $assignee_user_id,
			'updated_at' => $now,
		);
		$formats = array('%s', '%s', '%d', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s');

			$template_id = absint($template_id);
			if ($template_id > 0) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Staff Tasks task-template updates mutate a custom repository row through wpdb::update(); no core API is equivalent and no read cache applies to this immediate write path.
				$ok = $wpdb->update($table, $data, array('id' => $template_id), $formats, array('%d'));
				if ($ok === false) {
					return new WP_Error('vms_tasks_template_update_failed', __('Failed to update task template.', 'backstage-venue-manager'));
			}
			return $template_id;
			}

			$data['created_at'] = $now;
			$formats[] = '%s';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Staff Tasks task-template inserts persist normalized custom-table rows through wpdb::insert(); no core API preserves this repository lifecycle.
			$ok = $wpdb->insert($table, $data, $formats);
			if ($ok !== 1) {
				return new WP_Error('vms_tasks_template_insert_failed', __('Failed to create task template.', 'backstage-venue-manager'));
		}
		return (int) $wpdb->insert_id;
	}
}

if (!function_exists('bvmgr_tasks_get_checklist_template')) {
	/** @return array<string,mixed>|null */
	function bvmgr_tasks_get_checklist_template(int $checklist_id): ?array
	{
		global $wpdb;
		$table = bvmgr_tasks_table_name('checklist_templates');
		$checklist_id = absint($checklist_id);
		if ($table === '' || $checklist_id <= 0) {
			return null;
		}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Staff Tasks checklist-template reads target a custom repository table with %i/%d-prepared identifiers and IDs, and checklist edits must remain immediately visible without a persistent cache layer.
			$row = $wpdb->get_row(
				$wpdb->prepare('SELECT * FROM %i WHERE id = %d LIMIT 1', $table, $checklist_id),
				ARRAY_A
			);
			return is_array($row) ? $row : null;
		}
	}

if (!function_exists('bvmgr_tasks_get_checklist_templates')) {
	/**
	 * @param array<string,mixed> $filters
	 * @return array<int,array<string,mixed>>
	 */
	function bvmgr_tasks_get_checklist_templates(array $filters = array()): array
	{
		global $wpdb;
		$table = bvmgr_tasks_table_name('checklist_templates');
		if ($table === '') {
			return array();
		}

		$has_is_active = array_key_exists('is_active', $filters);
		$is_active = $has_is_active && !empty($filters['is_active']) ? 1 : 0;
		$apply_mode = !empty($filters['apply_mode']) ? bvmgr_tasks_sanitize_apply_mode((string) $filters['apply_mode']) : '';
		$scope = !empty($filters['scope']) ? bvmgr_tasks_sanitize_scope((string) $filters['scope']) : '';

		if ($has_is_active && $apply_mode !== '' && $scope !== '') {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Staff Tasks checklist-template lists read a custom repository with prepared identifier/filter values, and admin pages must reflect checklist mutations immediately.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE 1=1 AND is_active = %d AND apply_mode = %s AND scope = %s ORDER BY is_active DESC, priority_order ASC, id ASC',
					$table,
					$is_active,
					$apply_mode,
					$scope
				),
				ARRAY_A
			);
			return is_array($rows) ? $rows : array();
		}

		if ($has_is_active && $apply_mode !== '') {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Staff Tasks checklist-template lists read a custom repository with prepared identifier/filter values, and admin pages must reflect checklist mutations immediately.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE 1=1 AND is_active = %d AND apply_mode = %s ORDER BY is_active DESC, priority_order ASC, id ASC',
					$table,
					$is_active,
					$apply_mode
				),
				ARRAY_A
			);
			return is_array($rows) ? $rows : array();
		}

		if ($has_is_active && $scope !== '') {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Staff Tasks checklist-template lists read a custom repository with prepared identifier/filter values, and admin pages must reflect checklist mutations immediately.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE 1=1 AND is_active = %d AND scope = %s ORDER BY is_active DESC, priority_order ASC, id ASC',
					$table,
					$is_active,
					$scope
				),
				ARRAY_A
			);
			return is_array($rows) ? $rows : array();
		}

		if ($has_is_active) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Staff Tasks checklist-template lists read a custom repository with prepared identifier/filter values, and admin pages must reflect checklist mutations immediately.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE 1=1 AND is_active = %d ORDER BY is_active DESC, priority_order ASC, id ASC',
					$table,
					$is_active
				),
				ARRAY_A
			);
			return is_array($rows) ? $rows : array();
		}

		if ($apply_mode !== '' && $scope !== '') {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Staff Tasks checklist-template lists read a custom repository with prepared identifier/filter values, and admin pages must reflect checklist mutations immediately.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE 1=1 AND apply_mode = %s AND scope = %s ORDER BY is_active DESC, priority_order ASC, id ASC',
					$table,
					$apply_mode,
					$scope
				),
				ARRAY_A
			);
			return is_array($rows) ? $rows : array();
		}

		if ($apply_mode !== '') {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Staff Tasks checklist-template lists read a custom repository with prepared identifier/filter values, and admin pages must reflect checklist mutations immediately.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE 1=1 AND apply_mode = %s ORDER BY is_active DESC, priority_order ASC, id ASC',
					$table,
					$apply_mode
				),
				ARRAY_A
			);
			return is_array($rows) ? $rows : array();
		}

		if ($scope !== '') {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Staff Tasks checklist-template lists read a custom repository with prepared identifier/filter values, and admin pages must reflect checklist mutations immediately.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE 1=1 AND scope = %s ORDER BY is_active DESC, priority_order ASC, id ASC',
					$table,
					$scope
				),
				ARRAY_A
			);
			return is_array($rows) ? $rows : array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Staff Tasks checklist-template lists read a custom repository with prepared identifier/filter values, and admin pages must reflect checklist mutations immediately.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE 1=1 ORDER BY is_active DESC, priority_order ASC, id ASC',
				$table
			),
			ARRAY_A
		);
		return is_array($rows) ? $rows : array();
	}
}

if (!function_exists('bvmgr_tasks_upsert_checklist_template')) {
	/**
	 * @param array<string,mixed> $payload
	 * @return int|WP_Error
	 */
	function bvmgr_tasks_upsert_checklist_template(array $payload, int $checklist_id = 0)
	{
		global $wpdb;
		$table = bvmgr_tasks_table_name('checklist_templates');
		if ($table === '') {
			return new WP_Error('vms_tasks_table_missing', __('Checklist template table is unavailable.', 'backstage-venue-manager'));
		}

		$name = sanitize_text_field((string) ($payload['name'] ?? ''));
		if ($name === '') {
			return new WP_Error('vms_tasks_checklist_name_required', __('Checklist name is required.', 'backstage-venue-manager'));
		}

		$apply_mode = bvmgr_tasks_sanitize_apply_mode((string) ($payload['apply_mode'] ?? 'default_all_events'));
		$scope = bvmgr_tasks_sanitize_scope((string) ($payload['scope'] ?? 'event'));
		$venue_id = absint($payload['venue_id'] ?? 0);
		$event_type = sanitize_key((string) ($payload['event_type'] ?? ''));
		if ($scope === 'general') {
			$apply_mode = 'default_all_events';
			$venue_id = 0;
			$event_type = '';
		}

		if ($apply_mode === 'by_venue' && $venue_id <= 0) {
			return new WP_Error('vms_tasks_checklist_venue_required', __('Venue is required for venue-based checklists.', 'backstage-venue-manager'));
		}
		if ($apply_mode === 'by_event_type' && $event_type === '') {
			return new WP_Error('vms_tasks_checklist_event_type_required', __('Event type key is required for event-type checklists.', 'backstage-venue-manager'));
		}

		if ($apply_mode !== 'by_venue') {
			$venue_id = null;
		}
		if ($apply_mode !== 'by_event_type') {
			$event_type = null;
		}

		$data = array(
			'name' => $name,
			'is_active' => !empty($payload['is_active']) ? 1 : 0,
			'priority_order' => (int) ($payload['priority_order'] ?? 100),
			'scope' => $scope,
			'apply_mode' => $apply_mode,
			'venue_id' => $venue_id,
			'event_type' => $event_type,
			'updated_at' => bvmgr_tasks_now_utc_mysql(),
		);
		$formats = array('%s', '%d', '%d', '%s', '%s', '%d', '%s', '%s');

			$checklist_id = absint($checklist_id);
			if ($checklist_id > 0) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Staff Tasks checklist-template updates mutate a custom repository row through wpdb::update(); no core API is equivalent and no read cache applies to this immediate write path.
				$ok = $wpdb->update($table, $data, array('id' => $checklist_id), $formats, array('%d'));
				if ($ok === false) {
					return new WP_Error('vms_tasks_checklist_update_failed', __('Failed to update checklist template.', 'backstage-venue-manager'));
			}
			return $checklist_id;
			}

			$data['created_at'] = bvmgr_tasks_now_utc_mysql();
			$formats[] = '%s';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Staff Tasks checklist-template inserts persist normalized custom-table rows through wpdb::insert(); no core API preserves this repository lifecycle.
			$ok = $wpdb->insert($table, $data, $formats);
			if ($ok !== 1) {
				return new WP_Error('vms_tasks_checklist_insert_failed', __('Failed to create checklist template.', 'backstage-venue-manager'));
		}
		return (int) $wpdb->insert_id;
	}
}

if (!function_exists('bvmgr_tasks_replace_checklist_items')) {
	/**
	 * @param array<int,array<string,mixed>> $items
	 * @return true|WP_Error
	 */
	function bvmgr_tasks_replace_checklist_items(int $checklist_id, array $items)
	{
		global $wpdb;
		$table = bvmgr_tasks_table_name('checklist_items');
		$checklist_id = absint($checklist_id);
		if ($table === '' || $checklist_id <= 0) {
			return new WP_Error('vms_tasks_checklist_items_invalid', __('Checklist items are unavailable.', 'backstage-venue-manager'));
		}
		$checklist = bvmgr_tasks_get_checklist_template($checklist_id);
		$checklist_scope = bvmgr_tasks_sanitize_scope((string) ($checklist['scope'] ?? 'event'));

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Staff Tasks checklist-item replacement clears the custom child rows directly before reinserting the normalized ordered set, and no persistent cache contract safely spans this mutation batch.
			$wpdb->delete($table, array('checklist_id' => $checklist_id), array('%d'));

		$sort_order = 0;
		foreach ($items as $item) {
			$template_id = absint($item['task_template_id'] ?? 0);
			if ($template_id <= 0) {
				continue;
			}
			$template = bvmgr_tasks_get_task_template($template_id);
			if (!is_array($template)) {
				continue;
			}
			$template_scope = bvmgr_tasks_sanitize_scope((string) ($template['scope'] ?? 'event'));
			if ($template_scope !== $checklist_scope) {
				continue;
			}
			$sort_order++;

			$overrides = isset($item['overrides']) && is_array($item['overrides']) ? $item['overrides'] : array();
			$payload = array();
			if (array_key_exists('required_default', $overrides)) {
				$payload['required_default'] = !empty($overrides['required_default']) ? 1 : 0;
			}
			if (array_key_exists('priority', $overrides)) {
				$payload['priority'] = bvmgr_tasks_sanitize_priority((string) $overrides['priority']);
			}
			if (array_key_exists('assignment_mode', $overrides)) {
				$payload['assignment_mode'] = bvmgr_tasks_sanitize_assignment_mode((string) $overrides['assignment_mode']);
			}
			if (array_key_exists('role_key', $overrides)) {
				$payload['role_key'] = sanitize_key((string) $overrides['role_key']);
			}
			if (array_key_exists('assignee_user_id', $overrides)) {
				$payload['assignee_user_id'] = absint($overrides['assignee_user_id']);
			}
			if (array_key_exists('due_offset_minutes', $overrides)) {
				$payload['due_offset_minutes'] = (int) $overrides['due_offset_minutes'];
			}

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Staff Tasks checklist-item replacement persists normalized child rows through wpdb::insert(); no core API preserves this checklist repository contract.
				$wpdb->insert(
					$table,
					array(
						'checklist_id' => $checklist_id,
					'task_template_id' => $template_id,
					'sort_order' => isset($item['sort_order']) ? (int) $item['sort_order'] : $sort_order,
					'overrides_json' => !empty($payload) ? wp_json_encode($payload) : null,
				),
				array('%d', '%d', '%d', '%s')
			);
		}

		return true;
	}
}

if (!function_exists('bvmgr_tasks_decode_checklist_overrides')) {
	/**
	 * @param mixed $raw
	 * @return array{state:string,overrides:array<string,mixed>,reason:string}
	 */
	function bvmgr_tasks_decode_checklist_overrides($raw): array
	{
		if ($raw === null) {
			return array(
				'state' => 'missing',
				'overrides' => array(),
				'reason' => 'missing_value',
			);
		}
		if (!is_string($raw)) {
			return array(
				'state' => 'invalid',
				'overrides' => array(),
				'reason' => 'non_string',
			);
		}

		$raw = trim($raw);
		if ($raw === '') {
			return array(
				'state' => 'missing',
				'overrides' => array(),
				'reason' => 'blank_value',
			);
		}
		if ($raw[0] !== '{') {
			return array(
				'state' => 'invalid',
				'overrides' => array(),
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
				'overrides' => array(),
				'reason' => $reason,
			);
		}
		if (!is_array($decoded)) {
			return array(
				'state' => 'invalid',
				'overrides' => array(),
				'reason' => 'decoded_non_array',
			);
		}

		$allowed_keys = array(
			'required_default' => true,
			'priority' => true,
			'assignment_mode' => true,
			'role_key' => true,
			'assignee_user_id' => true,
			'due_offset_minutes' => true,
		);
		$overrides = array();

		foreach ($decoded as $key => $value) {
			if (is_int($key) || ctype_digit((string) $key)) {
				return array(
					'state' => 'invalid',
					'overrides' => array(),
					'reason' => 'numeric_key',
				);
			}
			if (!is_string($key) || !isset($allowed_keys[$key])) {
				return array(
					'state' => 'invalid',
					'overrides' => array(),
					'reason' => 'unknown_key',
				);
			}
			if (is_array($value)) {
				return array(
					'state' => 'invalid',
					'overrides' => array(),
					'reason' => 'nested_' . $key,
				);
			}

			switch ($key) {
				case 'required_default':
					if (!is_int($value) || ($value !== 0 && $value !== 1)) {
						return array(
							'state' => 'invalid',
							'overrides' => array(),
							'reason' => 'required_default_value',
						);
					}
					$overrides[$key] = $value;
					break;

				case 'priority':
					if (!is_string($value) || !in_array($value, bvmgr_tasks_allowed_priorities(), true)) {
						return array(
							'state' => 'invalid',
							'overrides' => array(),
							'reason' => 'priority_value',
						);
					}
					$overrides[$key] = $value;
					break;

				case 'assignment_mode':
					if (!is_string($value) || !in_array($value, array('role', 'person', 'scheduled_role'), true)) {
						return array(
							'state' => 'invalid',
							'overrides' => array(),
							'reason' => 'assignment_mode_value',
						);
					}
					$overrides[$key] = $value;
					break;

				case 'role_key':
					if (!is_string($value)) {
						return array(
							'state' => 'invalid',
							'overrides' => array(),
							'reason' => 'role_key_value',
						);
					}
					$sanitized_role_key = sanitize_key($value);
					if ($value !== '' && $sanitized_role_key !== $value) {
						return array(
							'state' => 'invalid',
							'overrides' => array(),
							'reason' => 'role_key_value',
						);
					}
					$overrides[$key] = $sanitized_role_key;
					break;

				case 'assignee_user_id':
					if (is_int($value) && $value >= 0) {
						$overrides[$key] = $value;
						break;
					}
					if (is_string($value) && preg_match('/^(0|[1-9][0-9]*)$/', $value) === 1) {
						$overrides[$key] = (int) $value;
						break;
					}
					return array(
						'state' => 'invalid',
						'overrides' => array(),
						'reason' => 'assignee_user_id_value',
					);

				case 'due_offset_minutes':
					if (is_int($value)) {
						$overrides[$key] = $value;
						break;
					}
					if (is_string($value) && preg_match('/^(0|-?[1-9][0-9]*)$/', $value) === 1) {
						$overrides[$key] = (int) $value;
						break;
					}
					return array(
						'state' => 'invalid',
						'overrides' => array(),
						'reason' => 'due_offset_minutes_value',
					);
			}
		}

		return array(
			'state' => 'valid',
			'overrides' => $overrides,
			'reason' => 'valid',
		);
	}
}

if (!function_exists('bvmgr_tasks_get_checklist_items')) {
	/**
	 * @return array<int,array<string,mixed>>
	 */
	function bvmgr_tasks_get_checklist_items(int $checklist_id): array
	{
		global $wpdb;
		$t_items = bvmgr_tasks_table_name('checklist_items');
		$t_templates = bvmgr_tasks_table_name('task_templates');
		$checklist_id = absint($checklist_id);
		if ($checklist_id <= 0 || $t_items === '' || $t_templates === '') {
			return array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Staff Tasks checklist-item reads join custom repository tables with %i/%d-prepared identifiers and must remain request-fresh after checklist and template edits.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT ci.*, tt.title AS template_title, tt.is_active AS template_active, tt.scope AS template_scope
				 FROM %i ci
				 LEFT JOIN %i tt ON tt.id = ci.task_template_id
				 WHERE ci.checklist_id = %d
				 ORDER BY ci.sort_order ASC, ci.id ASC',
				$t_items,
				$t_templates,
				$checklist_id
			),
			ARRAY_A
		);
		if (!is_array($rows)) {
			return array();
		}

		foreach ($rows as &$row) {
			$decoded = bvmgr_tasks_decode_checklist_overrides($row['overrides_json'] ?? null);
			$row['overrides'] = is_array($decoded['overrides'] ?? null) ? $decoded['overrides'] : array();
			$row['overrides_state'] = isset($decoded['state']) && is_string($decoded['state']) ? $decoded['state'] : 'invalid';
			$row['overrides_reason'] = isset($decoded['reason']) && is_string($decoded['reason']) ? $decoded['reason'] : 'unknown';
		}
		unset($row);

		return $rows;
	}
}

if (!function_exists('bvmgr_tasks_get_applicable_checklists')) {
	/**
	 * @return array<int,array<string,mixed>>
	 */
	function bvmgr_tasks_get_applicable_checklists(int $venue_id, string $event_type): array
	{
		global $wpdb;
		$table = bvmgr_tasks_table_name('checklist_templates');
		if ($table === '') {
			return array();
		}

		$event_type = sanitize_key($event_type);
		$venue_id = absint($venue_id);
		$has_venue = $venue_id > 0;
		$has_event_type = $event_type !== '';

		if ($has_venue && $has_event_type) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Staff Tasks applicable-checklist selection reads a custom repository with prepared identifier/filter values, and generator/admin consumers must see checklist applicability changes immediately.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE is_active = 1 AND scope = \'event\' AND (apply_mode = \'default_all_events\' OR (apply_mode = %s AND venue_id = %d) OR (apply_mode = %s AND event_type = %s)) ORDER BY priority_order ASC, id ASC',
					$table,
					'by_venue',
					$venue_id,
					'by_event_type',
					$event_type
				),
				ARRAY_A
			);
			return is_array($rows) ? $rows : array();
		}

		if ($has_venue) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Staff Tasks applicable-checklist selection reads a custom repository with prepared identifier/filter values, and generator/admin consumers must see checklist applicability changes immediately.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE is_active = 1 AND scope = \'event\' AND (apply_mode = \'default_all_events\' OR (apply_mode = %s AND venue_id = %d)) ORDER BY priority_order ASC, id ASC',
					$table,
					'by_venue',
					$venue_id
				),
				ARRAY_A
			);
			return is_array($rows) ? $rows : array();
		}

		if ($has_event_type) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Staff Tasks applicable-checklist selection reads a custom repository with prepared identifier/filter values, and generator/admin consumers must see checklist applicability changes immediately.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE is_active = 1 AND scope = \'event\' AND (apply_mode = \'default_all_events\' OR (apply_mode = %s AND event_type = %s)) ORDER BY priority_order ASC, id ASC',
					$table,
					'by_event_type',
					$event_type
				),
				ARRAY_A
			);
			return is_array($rows) ? $rows : array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Staff Tasks applicable-checklist selection reads a custom repository with prepared identifier/filter values, and generator/admin consumers must see checklist applicability changes immediately.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE is_active = 1 AND scope = \'event\' AND (apply_mode = \'default_all_events\') ORDER BY priority_order ASC, id ASC',
				$table
			),
			ARRAY_A
		);
		return is_array($rows) ? $rows : array();
	}
}

if (!function_exists('bvmgr_tasks_get_event_context')) {
	/** @return array<string,mixed>|null */
	function bvmgr_tasks_get_event_context(int $event_id): ?array
	{
		$event_id = absint($event_id);
		if ($event_id <= 0 || get_post_type($event_id) !== 'vms_event_plan') {
			return null;
		}

		$k_date = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'date') : '_vms_event_date';
		$k_venue = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'venue_id') : '_vms_venue_id';
		$k_start_dt = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'start_datetime') : '_vms_event_plan_start_datetime';

		$date_ymd = trim((string) get_post_meta($event_id, $k_date, true));
		$start_local = trim((string) get_post_meta($event_id, $k_start_dt, true));
		if ($start_local === '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_ymd)) {
			$start_local = $date_ymd . ' 12:00:00';
		}

		$venue_id = absint(get_post_meta($event_id, $k_venue, true));
		$event_type = sanitize_key((string) get_post_meta($event_id, '_vms_event_type', true));
		if ($event_type === '' && function_exists('bvmgr_staffing_pick_template_event_type')) {
			$event_type = sanitize_key((string) bvmgr_staffing_pick_template_event_type($event_id));
		}

		$tz = wp_timezone();
		$start_dt = null;
		if ($start_local !== '') {
			try {
				$start_dt = new DateTimeImmutable($start_local, $tz);
			} catch (Exception $e) {
				$start_dt = null;
			}
		}
		if (!($start_dt instanceof DateTimeImmutable)) {
			return null;
		}

		return array(
			'event_id' => $event_id,
			'event_title' => (string) get_the_title($event_id),
			'venue_id' => $venue_id,
			'event_type' => $event_type,
			'date_ymd' => $date_ymd,
			'event_start_local' => $start_dt->format('Y-m-d H:i:s'),
			'event_start_ts' => $start_dt->getTimestamp(),
		);
	}
}

if (!function_exists('bvmgr_tasks_get_instance')) {
	/** @return array<string,mixed>|null */
		function bvmgr_tasks_get_instance(int $instance_id): ?array
		{
			global $wpdb;
			$table = bvmgr_tasks_table_name('task_instances');
			$instance_id = absint($instance_id);
			if ($table === '' || $instance_id <= 0) {
				return null;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Task-instance reads target the normalized custom repository with %i/%d-prepared identifiers and IDs, and assignment/status flows must observe fresh state after writes.
			$row = $wpdb->get_row(
				$wpdb->prepare('SELECT * FROM %i WHERE id = %d LIMIT 1', $table, $instance_id),
				ARRAY_A
			);
			return is_array($row) ? $row : null;
		}
	}

if (!function_exists('bvmgr_tasks_get_instances_for_event')) {
	/**
	 * @return array<int,array<string,mixed>>
	 */
	function bvmgr_tasks_get_instances_for_event(int $event_id, bool $include_superseded = true): array
	{
		$event_id = absint($event_id);
		if ($event_id <= 0) {
			return array();
		}
		$filters = array('event_id' => $event_id, 'limit' => 1000);
		if (!$include_superseded) {
			$filters['exclude_status'] = 'superseded';
		}
		return bvmgr_tasks_get_instances($filters);
	}
}

if (!function_exists('bvmgr_tasks_get_instances')) {
	/**
	 * @param array<string,mixed> $filters
	 * @return array<int,array<string,mixed>>
	 */
		function bvmgr_tasks_get_instances(array $filters = array()): array
		{
			global $wpdb;
			$t_instances = bvmgr_tasks_table_name('task_instances');
			if ($t_instances === '') {
				return array();
			}

			$has_task_instance_id = !empty($filters['task_instance_id']) ? 1 : 0;
			$task_instance_id = $has_task_instance_id ? absint($filters['task_instance_id']) : 0;
			$has_event_id = !empty($filters['event_id']) ? 1 : 0;
			$event_id = $has_event_id ? absint($filters['event_id']) : 0;
			$event_linkage = !empty($filters['event_linkage']) ? sanitize_key((string) $filters['event_linkage']) : '';
			if (!in_array($event_linkage, array('event', 'non_event'), true)) {
				$event_linkage = '';
			}
			$has_status = !empty($filters['status']) ? 1 : 0;
			$status = $has_status ? bvmgr_tasks_sanitize_status((string) $filters['status']) : '';
			$has_exclude_status = !empty($filters['exclude_status']) ? 1 : 0;
			$exclude_status = $has_exclude_status ? bvmgr_tasks_sanitize_status((string) $filters['exclude_status']) : '';
			$has_assignee_user_id = !empty($filters['assignee_user_id']) ? 1 : 0;
			$assignee_user_id = $has_assignee_user_id ? absint($filters['assignee_user_id']) : 0;
			$has_role_key = !empty($filters['role_key']) ? 1 : 0;
			$role_key = $has_role_key ? sanitize_key((string) $filters['role_key']) : '';
			$has_venue_id = !empty($filters['venue_id']) ? 1 : 0;
			$venue_id = $has_venue_id ? absint($filters['venue_id']) : 0;
			$required_only = !empty($filters['required_only']) ? 1 : 0;
			$has_due_before = !empty($filters['due_before']) ? 1 : 0;
			$due_before = $has_due_before ? sanitize_text_field((string) $filters['due_before']) : '';
			$has_due_after = !empty($filters['due_after']) ? 1 : 0;
			$due_after = $has_due_after ? sanitize_text_field((string) $filters['due_after']) : '';

		$limit = isset($filters['limit']) ? absint($filters['limit']) : 200;
		if ($limit <= 0) {
			$limit = 200;
		}
		$limit = min(1000, $limit);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Task-instance lists read the normalized custom repository with prepared identifier/filter values, and assignment, supersession, and recurrence flows must see request-fresh state.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i
					 WHERE (%d = 0 OR id = %d)
					   AND (%d = 0 OR event_id = %d)
					   AND (
					       %s = \'\'
					       OR (%s = \'event\' AND event_id IS NOT NULL AND event_id > 0)
					       OR (%s = \'non_event\' AND (event_id IS NULL OR event_id = 0))
					   )
					   AND (%d = 0 OR status = %s)
					   AND (%d = 0 OR status <> %s)
					   AND (%d = 0 OR assignee_user_id = %d)
					   AND (%d = 0 OR role_key = %s)
					   AND (%d = 0 OR venue_id = %d)
					   AND (%d = 0 OR is_required = 1)
					   AND (%d = 0 OR (due_at_local IS NOT NULL AND due_at_local <= %s))
					   AND (%d = 0 OR (due_at_local IS NOT NULL AND due_at_local >= %s))
					 ORDER BY (due_at_local IS NULL) ASC, due_at_local ASC, id ASC
					 LIMIT %d',
					$t_instances,
					$has_task_instance_id,
					$task_instance_id,
					$has_event_id,
					$event_id,
					$event_linkage,
					$event_linkage,
					$event_linkage,
					$has_status,
					$status,
					$has_exclude_status,
					$exclude_status,
					$has_assignee_user_id,
					$assignee_user_id,
					$has_role_key,
					$role_key,
					$has_venue_id,
					$venue_id,
					$required_only,
					$has_due_before,
					$due_before,
					$has_due_after,
					$due_after,
					$limit
				),
				ARRAY_A
			);
			return is_array($rows) ? $rows : array();
		}
	}

if (!function_exists('bvmgr_tasks_count_instances')) {
	/**
	 * @param array<string,mixed> $filters
	 */
		function bvmgr_tasks_count_instances(array $filters = array()): int
		{
			global $wpdb;
			$t_instances = bvmgr_tasks_table_name('task_instances');
			if ($t_instances === '') {
				return 0;
			}

			$has_task_instance_id = !empty($filters['task_instance_id']) ? 1 : 0;
			$task_instance_id = $has_task_instance_id ? absint($filters['task_instance_id']) : 0;
			$has_event_id = !empty($filters['event_id']) ? 1 : 0;
			$event_id = $has_event_id ? absint($filters['event_id']) : 0;
			$event_linkage = !empty($filters['event_linkage']) ? sanitize_key((string) $filters['event_linkage']) : '';
			if (!in_array($event_linkage, array('event', 'non_event'), true)) {
				$event_linkage = '';
			}
			$has_status = !empty($filters['status']) ? 1 : 0;
			$status = $has_status ? bvmgr_tasks_sanitize_status((string) $filters['status']) : '';
			$has_exclude_status = !empty($filters['exclude_status']) ? 1 : 0;
			$exclude_status = $has_exclude_status ? bvmgr_tasks_sanitize_status((string) $filters['exclude_status']) : '';
			$has_assignee_user_id = !empty($filters['assignee_user_id']) ? 1 : 0;
			$assignee_user_id = $has_assignee_user_id ? absint($filters['assignee_user_id']) : 0;
			$has_role_key = !empty($filters['role_key']) ? 1 : 0;
			$role_key = $has_role_key ? sanitize_key((string) $filters['role_key']) : '';
			$has_venue_id = !empty($filters['venue_id']) ? 1 : 0;
			$venue_id = $has_venue_id ? absint($filters['venue_id']) : 0;
			$required_only = !empty($filters['required_only']) ? 1 : 0;
			$has_due_before = !empty($filters['due_before']) ? 1 : 0;
			$due_before = $has_due_before ? sanitize_text_field((string) $filters['due_before']) : '';
			$has_due_after = !empty($filters['due_after']) ? 1 : 0;
			$due_after = $has_due_after ? sanitize_text_field((string) $filters['due_after']) : '';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Task-instance counts read the normalized custom repository with prepared identifier/filter values, and assignment, supersession, and recurrence flows must see request-fresh state.
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM %i
					 WHERE (%d = 0 OR id = %d)
					   AND (%d = 0 OR event_id = %d)
					   AND (
					       %s = \'\'
					       OR (%s = \'event\' AND event_id IS NOT NULL AND event_id > 0)
					       OR (%s = \'non_event\' AND (event_id IS NULL OR event_id = 0))
					   )
					   AND (%d = 0 OR status = %s)
					   AND (%d = 0 OR status <> %s)
					   AND (%d = 0 OR assignee_user_id = %d)
					   AND (%d = 0 OR role_key = %s)
					   AND (%d = 0 OR venue_id = %d)
					   AND (%d = 0 OR is_required = 1)
					   AND (%d = 0 OR (due_at_local IS NOT NULL AND due_at_local <= %s))
					   AND (%d = 0 OR (due_at_local IS NOT NULL AND due_at_local >= %s))',
					$t_instances,
					$has_task_instance_id,
					$task_instance_id,
					$has_event_id,
					$event_id,
					$event_linkage,
					$event_linkage,
					$event_linkage,
					$has_status,
					$status,
					$has_exclude_status,
					$exclude_status,
					$has_assignee_user_id,
					$assignee_user_id,
					$has_role_key,
					$role_key,
					$has_venue_id,
					$venue_id,
					$required_only,
					$has_due_before,
					$due_before,
					$has_due_after,
					$due_after
				)
			);
		}
	}

if (!function_exists('bvmgr_tasks_insert_instance')) {
	/**
	 * @param array<string,mixed> $payload
	 * @return int|WP_Error
	 */
	function bvmgr_tasks_insert_instance(array $payload)
	{
		global $wpdb;
		$table = bvmgr_tasks_table_name('task_instances');
		if ($table === '') {
			return new WP_Error('vms_tasks_instances_table_missing', __('Task instances table is unavailable.', 'backstage-venue-manager'));
		}

		$title = sanitize_text_field((string) ($payload['title'] ?? ''));
		if ($title === '') {
			return new WP_Error('vms_tasks_instance_title_required', __('Task instance title is required.', 'backstage-venue-manager'));
		}

		$event_id = !empty($payload['event_id']) ? absint($payload['event_id']) : null;
		$due_at_local = !empty($payload['due_at_local']) ? sanitize_text_field((string) $payload['due_at_local']) : null;
		$recurrence_pattern = bvmgr_tasks_sanitize_recurrence_pattern((string) ($payload['recurrence_pattern'] ?? 'none'));
		$recurrence_every_n_days = bvmgr_tasks_normalize_recurrence_every_n_days(
			$recurrence_pattern,
			$payload['recurrence_every_n_days'] ?? 0
		);
		$recurrence_root_instance_id = !empty($payload['recurrence_root_instance_id']) ? absint($payload['recurrence_root_instance_id']) : null;
		if ($recurrence_root_instance_id !== null && $recurrence_root_instance_id <= 0) {
			$recurrence_root_instance_id = null;
		}

		// Event-linked tasks already recur per-event via templates/checklists.
		if ($event_id !== null && $event_id > 0) {
			$recurrence_pattern = 'none';
			$recurrence_every_n_days = null;
			$recurrence_root_instance_id = null;
		}
		if ($recurrence_pattern !== 'none' && $due_at_local === null) {
			return new WP_Error('vms_tasks_recurrence_due_required', __('Recurring tasks require a due date/time.', 'backstage-venue-manager'));
		}

		$now = bvmgr_tasks_now_utc_mysql();
		$data = array(
			'task_template_id' => !empty($payload['task_template_id']) ? absint($payload['task_template_id']) : null,
			'origin_checklist_id' => !empty($payload['origin_checklist_id']) ? absint($payload['origin_checklist_id']) : null,
			'event_id' => $event_id,
			'venue_id' => !empty($payload['venue_id']) ? absint($payload['venue_id']) : null,
			'event_type' => sanitize_key((string) ($payload['event_type'] ?? '')),
			'title' => $title,
			'instructions' => wp_kses_post((string) ($payload['instructions'] ?? '')),
			'priority' => bvmgr_tasks_sanitize_priority((string) ($payload['priority'] ?? 'normal')),
			'is_required' => !empty($payload['is_required']) ? 1 : 0,
			'due_at_local' => $due_at_local,
			'status' => bvmgr_tasks_sanitize_status((string) ($payload['status'] ?? 'open')),
			'assignment_mode' => bvmgr_tasks_sanitize_assignment_mode((string) ($payload['assignment_mode'] ?? 'role')),
			'role_key' => sanitize_key((string) ($payload['role_key'] ?? '')),
			'assignee_user_id' => !empty($payload['assignee_user_id']) ? absint($payload['assignee_user_id']) : null,
			'assignment_locked' => !empty($payload['assignment_locked']) ? 1 : 0,
			'completed_by_user_id' => !empty($payload['completed_by_user_id']) ? absint($payload['completed_by_user_id']) : null,
			'completed_at_local' => !empty($payload['completed_at_local']) ? sanitize_text_field((string) $payload['completed_at_local']) : null,
			'skip_reason' => !empty($payload['skip_reason']) ? sanitize_text_field((string) $payload['skip_reason']) : null,
			'cancel_reason' => !empty($payload['cancel_reason']) ? sanitize_text_field((string) $payload['cancel_reason']) : null,
			'superseded_by_instance_id' => !empty($payload['superseded_by_instance_id']) ? absint($payload['superseded_by_instance_id']) : null,
			'recurrence_pattern' => $recurrence_pattern,
			'recurrence_every_n_days' => $recurrence_every_n_days,
			'recurrence_root_instance_id' => $recurrence_root_instance_id,
			'created_at' => $now,
			'updated_at' => $now,
		);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Task-instance inserts persist normalized custom-table rows through wpdb::insert(); no core API preserves this repository lifecycle.
			$ok = $wpdb->insert(
				$table,
				$data,
				array('%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%s', '%s')
			);
		if ($ok !== 1) {
			return new WP_Error('vms_tasks_instance_insert_failed', __('Failed to create task instance.', 'backstage-venue-manager'));
		}
		return (int) $wpdb->insert_id;
	}
}

if (!function_exists('bvmgr_tasks_update_instance_assignment')) {
	function bvmgr_tasks_update_instance_assignment(
		int $instance_id,
		int $assignee_user_id,
		bool $lock = true,
		?int $actor_user_id = null,
		?string $assignment_mode = null,
		?string $role_key = null
	): bool
	{
		global $wpdb;
		$table = bvmgr_tasks_table_name('task_instances');
		$instance_id = absint($instance_id);
		if ($table === '' || $instance_id <= 0) {
			return false;
		}
		$assignee_user_id = absint($assignee_user_id);
		if ($assignee_user_id <= 0) {
			$assignee_user_id = null;
		}

		$data = array(
			'assignee_user_id' => $assignee_user_id,
			'assignment_locked' => $lock ? 1 : 0,
			'updated_at' => bvmgr_tasks_now_utc_mysql(),
		);
		$formats = array('%d', '%d', '%s');
		$log_payload = array(
			'assignee_user_id' => $assignee_user_id,
			'assignment_locked' => ($lock ? 1 : 0),
		);

		if ($assignment_mode !== null || $role_key !== null) {
			$mode = bvmgr_tasks_sanitize_assignment_mode((string) $assignment_mode);
			$effective_role = sanitize_key((string) $role_key);
			if ($mode === 'person') {
				$effective_role = '';
			}
			$data['assignment_mode'] = $mode;
			$data['role_key'] = $effective_role;
			$formats[] = '%s';
			$formats[] = '%s';
			$log_payload['assignment_mode'] = $mode;
			$log_payload['role_key'] = $effective_role;
		}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Task-instance assignment updates mutate a custom repository row through wpdb::update(); no core API is equivalent and no read cache applies to this immediate write path.
			$updated = $wpdb->update(
				$table,
				$data,
				array('id' => $instance_id),
				$formats,
			array('%d')
		);
		if ($updated === false) {
			return false;
		}

		bvmgr_tasks_log_task_action(
			$instance_id,
			'assigned',
			$actor_user_id,
			wp_json_encode($log_payload)
		);
		if (function_exists('bvmgr_tasks_emit_assignment_notification')) {
			$latest = bvmgr_tasks_get_instance($instance_id);
			if (is_array($latest)) {
				bvmgr_tasks_emit_assignment_notification($latest);
			}
		}
		return true;
	}
}

if (!function_exists('bvmgr_tasks_set_instance_assignment')) {
	/** @return true|WP_Error */
	function bvmgr_tasks_set_instance_assignment(
		int $instance_id,
		int $assignee_user_id,
		bool $assignment_locked = true,
		?int $actor_user_id = null,
		?string $assignment_mode = null,
		?string $role_key = null
	)
	{
		$instance_id = absint($instance_id);
		if ($instance_id <= 0) {
			return new WP_Error('vms_tasks_instance_invalid', __('Task instance is invalid.', 'backstage-venue-manager'));
		}

		$row = bvmgr_tasks_get_instance($instance_id);
		if (!is_array($row)) {
			return new WP_Error('vms_tasks_instance_missing', __('Task instance was not found.', 'backstage-venue-manager'));
		}

		$status = bvmgr_tasks_sanitize_status((string) ($row['status'] ?? 'open'));
		if ($status === 'superseded') {
			return new WP_Error('vms_tasks_instance_superseded', __('Superseded tasks are read-only.', 'backstage-venue-manager'));
		}

		if ($actor_user_id === null) {
			$actor_user_id = absint(get_current_user_id());
		}
		$actor_user_id = absint($actor_user_id);
		if ($actor_user_id <= 0) {
			$actor_user_id = null;
		}

		$set_rule = ($assignment_mode !== null || $role_key !== null);
		$mode = null;
		$effective_role_key = null;
		if ($set_rule) {
			$mode = bvmgr_tasks_sanitize_assignment_mode((string) $assignment_mode);
			$effective_role_key = sanitize_key((string) $role_key);

			if ($mode === 'role' && absint($assignee_user_id) > 0) {
				$mode = 'person';
				$effective_role_key = '';
			}
			if ($mode === 'person') {
				$effective_role_key = '';
			}
			if (in_array($mode, array('role', 'scheduled_role'), true) && $effective_role_key === '') {
				return new WP_Error('vms_tasks_role_required', __('Role is required for role-based assignments.', 'backstage-venue-manager'));
			}
		}

		$ok = bvmgr_tasks_update_instance_assignment(
			$instance_id,
			$assignee_user_id,
			$assignment_locked,
			$actor_user_id,
			$mode,
			$effective_role_key
		);
		if (!$ok) {
			return new WP_Error('vms_tasks_assignment_update_failed', __('Failed to update task assignment.', 'backstage-venue-manager'));
		}

		return true;
	}
}

if (!function_exists('bvmgr_tasks_transition_instance_status')) {
	/** @return true|WP_Error */
	function bvmgr_tasks_transition_instance_status(int $instance_id, string $new_status, string $reason = '', ?int $actor_user_id = null)
	{
		global $wpdb;
		$table = bvmgr_tasks_table_name('task_instances');
		$instance_id = absint($instance_id);
		if ($table === '' || $instance_id <= 0) {
			return new WP_Error('vms_tasks_instance_invalid', __('Task instance is invalid.', 'backstage-venue-manager'));
		}

		$row = bvmgr_tasks_get_instance($instance_id);
		if (!is_array($row)) {
			return new WP_Error('vms_tasks_instance_missing', __('Task instance was not found.', 'backstage-venue-manager'));
		}

		$current = bvmgr_tasks_sanitize_status((string) ($row['status'] ?? 'open'));
		$new_status = bvmgr_tasks_sanitize_status($new_status);
		$reason = sanitize_text_field($reason);
		if ($new_status === $current) {
			return true;
		}
		if ($current === 'superseded') {
			return new WP_Error('vms_tasks_instance_superseded', __('Superseded tasks are read-only.', 'backstage-venue-manager'));
		}
		if (!in_array($new_status, array('open', 'done', 'skipped', 'canceled'), true)) {
			return new WP_Error('vms_tasks_status_invalid', __('Invalid status transition.', 'backstage-venue-manager'));
		}

		$allowed = false;
		if ($current === 'open' && in_array($new_status, array('done', 'skipped', 'canceled'), true)) {
			$allowed = true;
		}
		if (in_array($current, array('done', 'skipped', 'canceled'), true) && $new_status === 'open') {
			$allowed = true;
		}
		if (!$allowed) {
			return new WP_Error('vms_tasks_status_forbidden', __('This status transition is not allowed.', 'backstage-venue-manager'));
		}

		if (in_array($new_status, array('skipped', 'canceled'), true) && $reason === '') {
			return new WP_Error('vms_tasks_reason_required', __('A reason is required for skipped or canceled tasks.', 'backstage-venue-manager'));
		}

		if ($actor_user_id === null) {
			$actor_user_id = absint(get_current_user_id());
		}
		$actor_user_id = absint($actor_user_id);
		if ($actor_user_id <= 0) {
			$actor_user_id = null;
		}

		$data = array(
			'status' => $new_status,
			'updated_at' => bvmgr_tasks_now_utc_mysql(),
		);
		$formats = array('%s', '%s');

		if ($new_status === 'done') {
			$data['completed_by_user_id'] = $actor_user_id;
			$data['completed_at_local'] = bvmgr_tasks_now_local_mysql();
			$data['skip_reason'] = null;
			$data['cancel_reason'] = null;
			$formats = array('%s', '%s', '%d', '%s', '%s', '%s');
		} elseif ($new_status === 'skipped') {
			$data['skip_reason'] = $reason;
			$formats[] = '%s';
		} elseif ($new_status === 'canceled') {
			$data['cancel_reason'] = $reason;
			$formats[] = '%s';
		} elseif ($new_status === 'open') {
			$data['completed_by_user_id'] = null;
			$data['completed_at_local'] = null;
			$formats[] = '%d';
			$formats[] = '%s';
		}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Task-instance status transitions mutate a custom repository row through wpdb::update(); no core API is equivalent and no read cache applies to this immediate write path.
			$ok = $wpdb->update($table, $data, array('id' => $instance_id), $formats, array('%d'));
			if ($ok === false) {
				return new WP_Error('vms_tasks_status_update_failed', __('Failed to update task status.', 'backstage-venue-manager'));
			}

		$action = 'status_changed';
		if ($new_status === 'done') {
			$action = 'marked_done';
		} elseif ($new_status === 'skipped') {
			$action = 'marked_skipped';
		} elseif ($new_status === 'canceled') {
			$action = 'marked_canceled';
		} elseif ($new_status === 'open') {
			$action = 'reopened';
		}

		bvmgr_tasks_log_task_action($instance_id, $action, $actor_user_id, wp_json_encode(array(
			'from' => $current,
			'to' => $new_status,
			'reason' => $reason,
		)));
		if (in_array($new_status, array('done', 'skipped', 'canceled'), true)) {
			$spawned = bvmgr_tasks_spawn_next_recurrence_instance($instance_id, $actor_user_id);
			if (is_wp_error($spawned)) {
				bvmgr_tasks_log_task_action($instance_id, 'recurrence_generation_failed', $actor_user_id, wp_json_encode(array(
					'error' => $spawned->get_error_message(),
					'to_status' => $new_status,
				)));
			}
		}

		return true;
	}
}

if (!function_exists('bvmgr_tasks_spawn_next_recurrence_instance')) {
	/** @return int|WP_Error */
	function bvmgr_tasks_spawn_next_recurrence_instance(int $instance_id, ?int $actor_user_id = null)
	{
		global $wpdb;
		$table = bvmgr_tasks_table_name('task_instances');
		$instance_id = absint($instance_id);
		if ($table === '' || $instance_id <= 0) {
			return 0;
		}

		$row = bvmgr_tasks_get_instance($instance_id);
		if (!is_array($row)) {
			return 0;
		}

		$status = bvmgr_tasks_sanitize_status((string) ($row['status'] ?? 'open'));
		if (!in_array($status, array('done', 'skipped', 'canceled'), true)) {
			return 0;
		}

		if (absint($row['event_id'] ?? 0) > 0) {
			return 0;
		}

		$pattern = bvmgr_tasks_sanitize_recurrence_pattern((string) ($row['recurrence_pattern'] ?? 'none'));
		if ($pattern === 'none') {
			return 0;
		}

		$due_at_local = sanitize_text_field((string) ($row['due_at_local'] ?? ''));
		if ($due_at_local === '') {
			return new WP_Error('vms_tasks_recurrence_due_missing', __('Recurring task is missing its due date/time.', 'backstage-venue-manager'));
		}

		$every_n_days = bvmgr_tasks_normalize_recurrence_every_n_days($pattern, $row['recurrence_every_n_days'] ?? 0);
		$next_due = bvmgr_tasks_recurrence_next_due_local($due_at_local, $pattern, $every_n_days);
		if ($next_due === null) {
			return new WP_Error('vms_tasks_recurrence_compute_failed', __('Failed to compute next recurring due date/time.', 'backstage-venue-manager'));
		}

		$root_id = absint($row['recurrence_root_instance_id'] ?? 0);
		if ($root_id <= 0) {
			$root_id = $instance_id;
		}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Task-instance recurrence checks read the normalized custom repository with prepared identifier/filter values, and completion flows must detect existing successors immediately.
			$existing_id = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT id FROM %i
					 WHERE (id = %d OR recurrence_root_instance_id = %d)
					   AND due_at_local = %s
					   AND status <> %s
					 LIMIT 1',
					$table,
					$root_id,
					$root_id,
					$next_due,
					'superseded'
				)
			);
		if ($existing_id > 0) {
			return $existing_id;
		}

		$created = bvmgr_tasks_insert_instance(array(
			'task_template_id' => absint($row['task_template_id'] ?? 0),
			'origin_checklist_id' => absint($row['origin_checklist_id'] ?? 0),
			'event_id' => null,
			'venue_id' => absint($row['venue_id'] ?? 0),
			'event_type' => sanitize_key((string) ($row['event_type'] ?? '')),
			'title' => (string) ($row['title'] ?? ''),
			'instructions' => (string) ($row['instructions'] ?? ''),
			'priority' => (string) ($row['priority'] ?? 'normal'),
			'is_required' => !empty($row['is_required']) ? 1 : 0,
			'due_at_local' => $next_due,
			'status' => 'open',
			'assignment_mode' => (string) ($row['assignment_mode'] ?? 'role'),
			'role_key' => (string) ($row['role_key'] ?? ''),
			'assignee_user_id' => absint($row['assignee_user_id'] ?? 0),
			'assignment_locked' => !empty($row['assignment_locked']) ? 1 : 0,
			'recurrence_pattern' => $pattern,
			'recurrence_every_n_days' => $every_n_days,
			'recurrence_root_instance_id' => $root_id,
		));
		if (is_wp_error($created)) {
			return $created;
		}

		$new_id = absint($created);
		bvmgr_tasks_log_task_action($new_id, 'created_from_recurrence', $actor_user_id, wp_json_encode(array(
			'source_instance_id' => $instance_id,
			'recurrence_root_instance_id' => $root_id,
			'recurrence_pattern' => $pattern,
			'recurrence_every_n_days' => $every_n_days,
			'due_at_local' => $next_due,
		)));
		bvmgr_tasks_log_task_action($instance_id, 'recurrence_next_created', $actor_user_id, wp_json_encode(array(
			'next_instance_id' => $new_id,
			'due_at_local' => $next_due,
			'recurrence_pattern' => $pattern,
			'recurrence_every_n_days' => $every_n_days,
		)));

		if (function_exists('bvmgr_tasks_emit_assignment_notification')) {
			$latest = bvmgr_tasks_get_instance($new_id);
			if (is_array($latest) && absint($latest['assignee_user_id'] ?? 0) > 0) {
				bvmgr_tasks_emit_assignment_notification($latest);
			}
		}

		return $new_id;
	}
}

if (!function_exists('bvmgr_tasks_resolve_scheduled_role_user_id')) {
	/**
	 * @return array<string,mixed>{status:string,assignee_user_id:int,staff_ids:int[]}
	 */
	function bvmgr_tasks_resolve_scheduled_role_user_id(int $event_id, string $role_key): array
	{
		$event_id = absint($event_id);
		$role_key = sanitize_key($role_key);
		if ($event_id <= 0 || $role_key === '') {
			return array('status' => 'none', 'assignee_user_id' => 0, 'staff_ids' => array());
		}

		if (!taxonomy_exists('vms_staff_role')) {
			return array('status' => 'none', 'assignee_user_id' => 0, 'staff_ids' => array());
		}
		$term = get_term_by('slug', $role_key, 'vms_staff_role');
		$role_id = ($term instanceof WP_Term) ? absint($term->term_id) : 0;
		if ($role_id <= 0) {
			return array('status' => 'none', 'assignee_user_id' => 0, 'staff_ids' => array());
		}

			global $wpdb;
			$t_slots = function_exists('bvmgr_staffing_table_name') ? (string) bvmgr_staffing_table_name('event_slots') : '';
			$t_assign = function_exists('bvmgr_staffing_table_name') ? (string) bvmgr_staffing_table_name('assignments') : '';
			if ($t_slots === '' || $t_assign === '') {
				$t_slots = $wpdb->prefix . (defined('BVMGR_DB_TABLE_EVENT_ROLE_SLOTS_SUFFIX') ? BVMGR_DB_TABLE_EVENT_ROLE_SLOTS_SUFFIX : 'vms_event_role_slots');
				$t_assign = $wpdb->prefix . (defined('BVMGR_DB_TABLE_EVENT_ROLE_ASSIGNMENTS_SUFFIX') ? BVMGR_DB_TABLE_EVENT_ROLE_ASSIGNMENTS_SUFFIX : 'vms_event_role_assignments');
			}
			$t_usermeta = (property_exists($wpdb, 'usermeta') && is_string($wpdb->usermeta) && $wpdb->usermeta !== '')
				? $wpdb->usermeta
				: $wpdb->prefix . 'usermeta';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Scheduled-role resolution reads the staffing repositories with prepared identifier/filter values, and portal/task assignment flows must observe immediate staffing changes.
			$staff_ids = $wpdb->get_col(
				$wpdb->prepare(
					'SELECT DISTINCT a.staff_id
					 FROM %i s
					 INNER JOIN %i a ON a.slot_id = s.slot_id
					 WHERE s.event_plan_id = %d
					   AND s.role_id = %d
					   AND s.status = %s
					   AND a.status IN (%s, %s, %s)',
					$t_slots,
					$t_assign,
					$event_id,
					$role_id,
					'active',
					'proposed',
					'confirmed',
					'checked_in'
				)
			);

		$staff_ids = array_values(array_unique(array_filter(array_map('absint', is_array($staff_ids) ? $staff_ids : array()))));
		if (count($staff_ids) === 0) {
			return array('status' => 'none', 'assignee_user_id' => 0, 'staff_ids' => array());
		}
		if (count($staff_ids) > 1) {
			return array('status' => 'multiple', 'assignee_user_id' => 0, 'staff_ids' => $staff_ids);
		}

			$staff_id = $staff_ids[0];
			$user_id = absint(get_post_meta($staff_id, '_vms_linked_user_id', true));
			if ($user_id <= 0) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Scheduled-role fallback reads a single normalized usermeta link with prepared identifier/filter values, and portal/task assignment flows must observe immediate link changes.
				$user_id = (int) $wpdb->get_var(
					$wpdb->prepare(
						'SELECT user_id FROM %i WHERE meta_key = %s AND meta_value = %s ORDER BY umeta_id ASC LIMIT 1',
						$t_usermeta,
						'_vms_staff_id',
						(string) $staff_id
					)
				);
			}

		if ($user_id <= 0) {
			return array('status' => 'none', 'assignee_user_id' => 0, 'staff_ids' => $staff_ids);
		}

		return array('status' => 'single', 'assignee_user_id' => $user_id, 'staff_ids' => $staff_ids);
	}
}

if (!function_exists('bvmgr_tasks_select_existing_open_instance')) {
	/** @return array<string,mixed>|null */
	function bvmgr_tasks_select_existing_open_instance(int $event_id, int $template_id, int $origin_checklist_id, ?string $due_at_local, bool $strict_due = true): ?array
	{
		global $wpdb;
		$table = bvmgr_tasks_table_name('task_instances');
		if ($table === '') {
			return null;
		}

		$event_id = absint($event_id);
		$template_id = absint($template_id);
		$origin_checklist_id = absint($origin_checklist_id);
		if ($event_id <= 0 || $template_id <= 0) {
			return null;
		}

			if ($strict_due) {
				if ($due_at_local === null || $due_at_local === '') {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Existing-open-instance selection reads the normalized custom repository with prepared identifier/filter values, and regeneration/supersession flows must observe the latest request-local state.
					$row = $wpdb->get_row(
						$wpdb->prepare(
							'SELECT * FROM %i WHERE event_id = %d AND task_template_id = %d AND origin_checklist_id = %d AND status = %s AND due_at_local IS NULL ORDER BY id DESC LIMIT 1',
							$table,
							$event_id,
							$template_id,
							$origin_checklist_id,
							'open'
						),
						ARRAY_A
					);
				} else {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Existing-open-instance selection reads the normalized custom repository with prepared identifier/filter values, and regeneration/supersession flows must observe the latest request-local state.
					$row = $wpdb->get_row(
						$wpdb->prepare(
							'SELECT * FROM %i WHERE event_id = %d AND task_template_id = %d AND origin_checklist_id = %d AND status = %s AND due_at_local = %s ORDER BY id DESC LIMIT 1',
							$table,
							$event_id,
							$template_id,
							$origin_checklist_id,
							'open',
							$due_at_local
						),
						ARRAY_A
					);
				}
			} else {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Existing-open-instance selection reads the normalized custom repository with prepared identifier/filter values, and regeneration/supersession flows must observe the latest request-local state.
				$row = $wpdb->get_row(
					$wpdb->prepare(
						'SELECT * FROM %i WHERE event_id = %d AND task_template_id = %d AND origin_checklist_id = %d AND status = %s ORDER BY id DESC LIMIT 1',
						$table,
						$event_id,
						$template_id,
						$origin_checklist_id,
						'open'
					),
					ARRAY_A
				);
			}

		return is_array($row) ? $row : null;
	}
}

if (!function_exists('bvmgr_tasks_supersede_open_instances')) {
	/** @return int rows superseded */
	function bvmgr_tasks_supersede_open_instances(int $event_id, int $template_id, int $origin_checklist_id, int $new_instance_id, ?int $actor_user_id = null): int
	{
		global $wpdb;
		$table = bvmgr_tasks_table_name('task_instances');
		if ($table === '') {
			return 0;
		}

		$event_id = absint($event_id);
		$template_id = absint($template_id);
		$origin_checklist_id = absint($origin_checklist_id);
		$new_instance_id = absint($new_instance_id);
		if ($event_id <= 0 || $template_id <= 0 || $new_instance_id <= 0) {
			return 0;
		}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Open-instance supersession reads the normalized custom repository with prepared identifier/filter values, and regeneration flows must observe the latest request-local state before mutating siblings.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT id FROM %i WHERE event_id = %d AND task_template_id = %d AND origin_checklist_id = %d AND status = %s AND id <> %d',
					$table,
					$event_id,
					$template_id,
					$origin_checklist_id,
					'open',
					$new_instance_id
				),
				ARRAY_A
			);

		if (!is_array($rows) || empty($rows)) {
			return 0;
		}

		$count = 0;
		foreach ($rows as $row) {
			$instance_id = absint($row['id'] ?? 0);
			if ($instance_id <= 0) {
				continue;
			}
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Open-instance supersession mutates sibling repository rows through wpdb::update(); no core API is equivalent and no read cache applies to this immediate write path.
				$updated = $wpdb->update(
					$table,
					array(
						'status' => 'superseded',
					'superseded_by_instance_id' => $new_instance_id,
					'updated_at' => bvmgr_tasks_now_utc_mysql(),
				),
				array('id' => $instance_id),
				array('%s', '%d', '%s'),
				array('%d')
			);
			if ($updated !== false && (int) $updated > 0) {
				$count++;
				bvmgr_tasks_log_task_action($instance_id, 'regenerated_and_superseded', $actor_user_id, wp_json_encode(array(
					'superseded_by_instance_id' => $new_instance_id,
				)));
			}
		}

		return $count;
	}
}
