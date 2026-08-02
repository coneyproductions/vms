<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_tasks_db_option_key')) {
	function vms_tasks_db_option_key(): string
	{
		return defined('VMS_OPT_TASKS_DB_SCHEMA_VERSION') ? (string) VMS_OPT_TASKS_DB_SCHEMA_VERSION : 'vms_tasks_db_schema_version';
	}
}

if (!function_exists('vms_tasks_db_schema_target')) {
	function vms_tasks_db_schema_target(): string
	{
		// 1.2.0 adds recurring cadence fields on task instances.
		return '1.2.0';
	}
}

if (!function_exists('vms_tasks_table_name')) {
	function vms_tasks_table_name(string $kind): string
	{
		global $wpdb;
		$map = array(
			'task_templates' => defined('VMS_DB_TABLE_TASK_TEMPLATES_SUFFIX') ? VMS_DB_TABLE_TASK_TEMPLATES_SUFFIX : 'vms_task_templates',
			'checklist_templates' => defined('VMS_DB_TABLE_CHECKLIST_TEMPLATES_SUFFIX') ? VMS_DB_TABLE_CHECKLIST_TEMPLATES_SUFFIX : 'vms_checklist_templates',
			'checklist_items' => defined('VMS_DB_TABLE_CHECKLIST_ITEMS_SUFFIX') ? VMS_DB_TABLE_CHECKLIST_ITEMS_SUFFIX : 'vms_checklist_items',
			'task_instances' => defined('VMS_DB_TABLE_TASK_INSTANCES_SUFFIX') ? VMS_DB_TABLE_TASK_INSTANCES_SUFFIX : 'vms_task_instances',
			'task_logs' => defined('VMS_DB_TABLE_TASK_LOGS_SUFFIX') ? VMS_DB_TABLE_TASK_LOGS_SUFFIX : 'vms_task_logs',
		);
		$suffix = isset($map[$kind]) ? (string) $map[$kind] : '';
		if ($suffix === '') {
			return '';
		}
		return $wpdb->prefix . $suffix;
	}
}

if (!function_exists('vms_tasks_db_ready')) {
	function vms_tasks_db_ready(): bool
	{
		global $wpdb;
		$required = array(
			vms_tasks_table_name('task_templates'),
			vms_tasks_table_name('checklist_templates'),
			vms_tasks_table_name('checklist_items'),
			vms_tasks_table_name('task_instances'),
			vms_tasks_table_name('task_logs'),
		);

			foreach ($required as $table) {
				if ($table === '') {
					return false;
				}
				$table_like = $wpdb->esc_like($table);
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Staff Tasks schema readiness probes custom tables directly because no core API exposes these module tables, and upgrade/runtime checks must observe the latest schema state.
				$exists = (string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_like));
				if ($exists !== $table) {
					return false;
				}
		}

		return true;
	}
}

if (!function_exists('vms_tasks_maybe_upgrade_schema')) {
	function vms_tasks_maybe_upgrade_schema(): void
	{
		$current = (string) get_option(vms_tasks_db_option_key(), '');
		$target = vms_tasks_db_schema_target();
		if ($current === $target && vms_tasks_db_ready()) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$t_task_templates = vms_tasks_table_name('task_templates');
		$t_checklist_templates = vms_tasks_table_name('checklist_templates');
		$t_checklist_items = vms_tasks_table_name('checklist_items');
		$t_task_instances = vms_tasks_table_name('task_instances');
		$t_task_logs = vms_tasks_table_name('task_logs');

		$sql_task_templates = "CREATE TABLE {$t_task_templates} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			title VARCHAR(190) NOT NULL,
			instructions LONGTEXT NULL,
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			priority VARCHAR(20) NOT NULL DEFAULT 'normal',
			required_default TINYINT(1) NOT NULL DEFAULT 1,
			scope VARCHAR(20) NOT NULL DEFAULT 'event',
			due_mode VARCHAR(20) NOT NULL DEFAULT 'none',
			due_offset_minutes INT(11) NULL,
			due_time_local VARCHAR(5) NULL,
			assignment_mode VARCHAR(30) NOT NULL DEFAULT 'role',
			role_key VARCHAR(100) NULL,
			assignee_user_id BIGINT(20) UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY is_active (is_active),
			KEY scope (scope),
			KEY assignment_mode (assignment_mode),
			KEY role_key (role_key),
			KEY assignee_user_id (assignee_user_id)
		) {$charset_collate};";

		$sql_checklist_templates = "CREATE TABLE {$t_checklist_templates} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(190) NOT NULL,
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			priority_order INT(11) NOT NULL DEFAULT 100,
			scope VARCHAR(20) NOT NULL DEFAULT 'event',
			apply_mode VARCHAR(30) NOT NULL DEFAULT 'default_all_events',
			venue_id BIGINT(20) UNSIGNED NULL,
			event_type VARCHAR(100) NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY is_active (is_active),
			KEY priority_order (priority_order),
			KEY scope (scope),
			KEY apply_mode (apply_mode),
			KEY venue_id (venue_id),
			KEY event_type (event_type)
		) {$charset_collate};";

		$sql_checklist_items = "CREATE TABLE {$t_checklist_items} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			checklist_id BIGINT(20) UNSIGNED NOT NULL,
			task_template_id BIGINT(20) UNSIGNED NOT NULL,
			sort_order INT(11) NOT NULL DEFAULT 0,
			overrides_json LONGTEXT NULL,
			PRIMARY KEY (id),
			KEY checklist_id (checklist_id),
			KEY task_template_id (task_template_id),
			KEY checklist_sort (checklist_id, sort_order)
		) {$charset_collate};";

		$sql_task_instances = "CREATE TABLE {$t_task_instances} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			task_template_id BIGINT(20) UNSIGNED NULL,
			origin_checklist_id BIGINT(20) UNSIGNED NULL,
			event_id BIGINT(20) UNSIGNED NULL,
			venue_id BIGINT(20) UNSIGNED NULL,
			event_type VARCHAR(100) NULL,
			title VARCHAR(190) NOT NULL,
			instructions LONGTEXT NULL,
			priority VARCHAR(20) NOT NULL DEFAULT 'normal',
			is_required TINYINT(1) NOT NULL DEFAULT 1,
			due_at_local DATETIME NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'open',
			assignment_mode VARCHAR(30) NOT NULL DEFAULT 'role',
			role_key VARCHAR(100) NULL,
			assignee_user_id BIGINT(20) UNSIGNED NULL,
			assignment_locked TINYINT(1) NOT NULL DEFAULT 0,
			completed_by_user_id BIGINT(20) UNSIGNED NULL,
			completed_at_local DATETIME NULL,
			skip_reason VARCHAR(255) NULL,
			cancel_reason VARCHAR(255) NULL,
			superseded_by_instance_id BIGINT(20) UNSIGNED NULL,
			recurrence_pattern VARCHAR(30) NOT NULL DEFAULT 'none',
			recurrence_every_n_days INT(11) NULL,
			recurrence_root_instance_id BIGINT(20) UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY event_id (event_id),
			KEY assignee_user_id (assignee_user_id),
			KEY due_at_local (due_at_local),
			KEY status (status),
			KEY recurrence_pattern (recurrence_pattern),
			KEY recurrence_root_instance_id (recurrence_root_instance_id),
			KEY event_status_due (event_id, status, due_at_local),
			KEY template_origin (task_template_id, origin_checklist_id),
			KEY event_template_due (event_id, task_template_id, origin_checklist_id, due_at_local)
		) {$charset_collate};";

		$sql_task_logs = "CREATE TABLE {$t_task_logs} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			task_instance_id BIGINT(20) UNSIGNED NOT NULL,
			action VARCHAR(50) NOT NULL,
			actor_user_id BIGINT(20) UNSIGNED NULL,
			details LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY task_instance_id (task_instance_id),
			KEY created_at (created_at),
			KEY action (action)
		) {$charset_collate};";

		dbDelta($sql_task_templates);
		dbDelta($sql_checklist_templates);
		dbDelta($sql_checklist_items);
		dbDelta($sql_task_instances);
		dbDelta($sql_task_logs);

		update_option(vms_tasks_db_option_key(), $target, false);
		if (function_exists('vms_tasks_settings_defaults') && function_exists('vms_tasks_settings_option_key')) {
			$existing = get_option(vms_tasks_settings_option_key(), null);
			if (!is_array($existing)) {
				update_option(vms_tasks_settings_option_key(), vms_tasks_settings_defaults(), false);
			}
		}
	}
}
