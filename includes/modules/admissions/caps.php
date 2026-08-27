<?php
defined('ABSPATH') || exit;

if (!function_exists('bvmgr_admission_manage_capability')) {
	function bvmgr_admission_manage_capability(): string
	{
		return 'vms_admission_manage';
	}
}

if (!function_exists('bvmgr_admission_door_capability')) {
	function bvmgr_admission_door_capability(): string
	{
		return 'vms_door_checkin';
	}
}

if (!function_exists('bvmgr_admission_phone_view_capability')) {
	function bvmgr_admission_phone_view_capability(): string
	{
		return 'vms_admission_view_phone';
	}
}

if (!function_exists('bvmgr_admission_ensure_capability_mapping')) {
	function bvmgr_admission_ensure_capability_mapping(): void
	{
		$manage_cap = bvmgr_admission_manage_capability();
		$door_cap = bvmgr_admission_door_capability();
		$phone_cap = bvmgr_admission_phone_view_capability();

		$admin = get_role('administrator');
		if ($admin instanceof WP_Role) {
			if (!$admin->has_cap($manage_cap)) {
				$admin->add_cap($manage_cap);
			}
			if (!$admin->has_cap($door_cap)) {
				$admin->add_cap($door_cap);
			}
			if (!$admin->has_cap($phone_cap)) {
				$admin->add_cap($phone_cap);
			}
		}

		$door_role_slugs = array('vms_door_staff', 'door_staff', 'event_manager', 'vms_event_manager');
		$door_role_found = false;
		foreach ($door_role_slugs as $slug) {
			$role = get_role($slug);
			if (!($role instanceof WP_Role)) {
				continue;
			}
			$door_role_found = true;
			if (!$role->has_cap($door_cap)) {
				$role->add_cap($door_cap);
			}
		}

		if (!$door_role_found) {
			$existing = get_role('vms_door_staff');
			if (!($existing instanceof WP_Role)) {
				add_role('vms_door_staff', __('Backstage Venue Manager Door Staff', 'backstage-venue-manager'), array(
					'read' => true,
					$door_cap => true,
				));
			}
		}
	}
}

if (!function_exists('bvmgr_admission_current_user_can_manage')) {
	function bvmgr_admission_current_user_can_manage(): bool
	{
		return current_user_can(bvmgr_admission_manage_capability());
	}
}

if (!function_exists('bvmgr_admission_current_user_can_checkin')) {
	function bvmgr_admission_current_user_can_checkin(): bool
	{
		return current_user_can(bvmgr_admission_door_capability()) || bvmgr_admission_current_user_can_manage();
	}
}
