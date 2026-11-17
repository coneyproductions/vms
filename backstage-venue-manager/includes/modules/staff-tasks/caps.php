<?php
defined('ABSPATH') || exit;

if (!function_exists('bvmgr_tasks_cap_manage_templates')) {
	function bvmgr_tasks_cap_manage_templates(): string
	{
		return defined('BVMGR_CAP_TASKS_MANAGE_TEMPLATES') ? (string) BVMGR_CAP_TASKS_MANAGE_TEMPLATES : 'vms_manage_task_templates';
	}
}

if (!function_exists('bvmgr_tasks_cap_manage_checklists')) {
	function bvmgr_tasks_cap_manage_checklists(): string
	{
		return defined('BVMGR_CAP_TASKS_MANAGE_CHECKLISTS') ? (string) BVMGR_CAP_TASKS_MANAGE_CHECKLISTS : 'vms_manage_checklist_templates';
	}
}

if (!function_exists('bvmgr_tasks_cap_manage_all')) {
	function bvmgr_tasks_cap_manage_all(): string
	{
		return defined('BVMGR_CAP_TASKS_MANAGE_ALL') ? (string) BVMGR_CAP_TASKS_MANAGE_ALL : 'vms_manage_tasks_all';
	}
}

if (!function_exists('bvmgr_tasks_cap_complete_self')) {
	function bvmgr_tasks_cap_complete_self(): string
	{
		return defined('BVMGR_CAP_TASKS_COMPLETE_SELF') ? (string) BVMGR_CAP_TASKS_COMPLETE_SELF : 'vms_complete_tasks_self';
	}
}

if (!function_exists('bvmgr_tasks_cap_view_self')) {
	function bvmgr_tasks_cap_view_self(): string
	{
		return defined('BVMGR_CAP_TASKS_VIEW_SELF') ? (string) BVMGR_CAP_TASKS_VIEW_SELF : 'vms_view_tasks_self';
	}
}

if (!function_exists('bvmgr_tasks_current_user_has_admin_fallback')) {
	function bvmgr_tasks_current_user_has_admin_fallback(): bool
	{
		if (current_user_can('manage_options')) {
			return true;
		}

		if (function_exists('bvmgr_admin_ui_current_user_can_data_tools') && bvmgr_admin_ui_current_user_can_data_tools()) {
			return true;
		}

		if (function_exists('bvmgr_admin_ui_current_user_can_ops') && bvmgr_admin_ui_current_user_can_ops()) {
			return true;
		}

		return false;
	}
}

if (!function_exists('bvmgr_tasks_current_user_can_manage_all')) {
	function bvmgr_tasks_current_user_can_manage_all(): bool
	{
		return current_user_can(bvmgr_tasks_cap_manage_all()) || bvmgr_tasks_current_user_has_admin_fallback();
	}
}

if (!function_exists('bvmgr_tasks_current_user_can_manage_templates')) {
	function bvmgr_tasks_current_user_can_manage_templates(): bool
	{
		return current_user_can(bvmgr_tasks_cap_manage_templates()) || bvmgr_tasks_current_user_can_manage_all();
	}
}

if (!function_exists('bvmgr_tasks_current_user_can_manage_checklists')) {
	function bvmgr_tasks_current_user_can_manage_checklists(): bool
	{
		return current_user_can(bvmgr_tasks_cap_manage_checklists()) || bvmgr_tasks_current_user_can_manage_all();
	}
}

if (!function_exists('bvmgr_tasks_current_user_can_view_self')) {
	function bvmgr_tasks_current_user_can_view_self(): bool
	{
		return current_user_can(bvmgr_tasks_cap_view_self()) || bvmgr_tasks_current_user_can_manage_all();
	}
}

if (!function_exists('bvmgr_tasks_current_user_can_complete_self')) {
	function bvmgr_tasks_current_user_can_complete_self(): bool
	{
		return current_user_can(bvmgr_tasks_cap_complete_self()) || bvmgr_tasks_current_user_can_manage_all();
	}
}

if (!function_exists('bvmgr_tasks_ensure_capability_mapping')) {
	function bvmgr_tasks_ensure_capability_mapping(): void
	{
		$caps_all = array(
			bvmgr_tasks_cap_manage_templates(),
			bvmgr_tasks_cap_manage_checklists(),
			bvmgr_tasks_cap_manage_all(),
			bvmgr_tasks_cap_complete_self(),
			bvmgr_tasks_cap_view_self(),
		);

		$admin = get_role('administrator');
		if ($admin instanceof WP_Role) {
			foreach ($caps_all as $cap) {
				if (!$admin->has_cap($cap)) {
					$admin->add_cap($cap);
				}
			}
		}

		$editor = get_role('editor');
		if ($editor instanceof WP_Role) {
			foreach (array(bvmgr_tasks_cap_complete_self(), bvmgr_tasks_cap_view_self()) as $cap) {
				if (!$editor->has_cap($cap)) {
					$editor->add_cap($cap);
				}
			}
		}

		$staff_roles = array('vms_door_staff', 'event_manager', 'vms_event_manager');
		foreach ($staff_roles as $role_slug) {
			$role = get_role($role_slug);
			if (!($role instanceof WP_Role)) {
				continue;
			}
			foreach (array(bvmgr_tasks_cap_complete_self(), bvmgr_tasks_cap_view_self()) as $cap) {
				if (!$role->has_cap($cap)) {
					$role->add_cap($cap);
				}
			}
		}
	}
}
