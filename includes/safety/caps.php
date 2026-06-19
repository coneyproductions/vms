<?php

defined('ABSPATH') || exit;

if (!defined('VMS_CAP_SAFETY_MANAGE')) {
	define('VMS_CAP_SAFETY_MANAGE', 'vms_manage_safety');
}
if (!defined('VMS_CAP_SAFETY_VIEW')) {
	define('VMS_CAP_SAFETY_VIEW', 'vms_view_safety');
}
if (!defined('VMS_CAP_SAFETY_EXPORT')) {
	define('VMS_CAP_SAFETY_EXPORT', 'vms_export_safety');
}
if (!defined('VMS_CAP_SAFETY_TEMPLATES')) {
	define('VMS_CAP_SAFETY_TEMPLATES', 'vms_manage_safety_templates');
}

if (!function_exists('vms_safety_manage_capability')) {
	function vms_safety_manage_capability(): string
	{
		return (string) VMS_CAP_SAFETY_MANAGE;
	}
}

if (!function_exists('vms_safety_view_capability')) {
	function vms_safety_view_capability(): string
	{
		return (string) VMS_CAP_SAFETY_VIEW;
	}
}

if (!function_exists('vms_safety_export_capability')) {
	function vms_safety_export_capability(): string
	{
		return (string) VMS_CAP_SAFETY_EXPORT;
	}
}

if (!function_exists('vms_safety_menu_capability')) {
	function vms_safety_menu_capability(): string
	{
		// Ensure administrators can always discover the Safety menu even if
		// custom role caps drift on a local/dev install.
		if (current_user_can('manage_options')) {
			return 'manage_options';
		}
		return vms_safety_view_capability();
	}
}

if (!function_exists('vms_safety_user_can_view')) {
	function vms_safety_user_can_view(): bool
	{
		return current_user_can(vms_safety_view_capability()) || current_user_can('manage_options');
	}
}

if (!function_exists('vms_safety_user_can_manage')) {
	function vms_safety_user_can_manage(): bool
	{
		return current_user_can(vms_safety_manage_capability()) || current_user_can('manage_options');
	}
}

if (!function_exists('vms_safety_user_can_export')) {
	function vms_safety_user_can_export(): bool
	{
		return current_user_can(vms_safety_export_capability()) || current_user_can('manage_options');
	}
}

if (!function_exists('vms_safety_default_cap_matrix')) {
	/**
	 * @return array<string,array<int,string>>
	 */
	function vms_safety_default_cap_matrix(): array
	{
		return array(
			'administrator' => array(VMS_CAP_SAFETY_MANAGE, VMS_CAP_SAFETY_VIEW, VMS_CAP_SAFETY_EXPORT),
			'editor' => array(VMS_CAP_SAFETY_MANAGE, VMS_CAP_SAFETY_VIEW, VMS_CAP_SAFETY_EXPORT),
			'shop_manager' => array(VMS_CAP_SAFETY_MANAGE, VMS_CAP_SAFETY_VIEW, VMS_CAP_SAFETY_EXPORT),
			'vms_manager' => array(VMS_CAP_SAFETY_MANAGE, VMS_CAP_SAFETY_VIEW, VMS_CAP_SAFETY_EXPORT),
		);
	}
}

if (!function_exists('vms_safety_install_caps')) {
	function vms_safety_install_caps(): void
	{
		$opt_key = 'vms_safety_caps_installed_v1';
		$installed = (string) get_option($opt_key, '');

		// Repair mode: if install flag says complete but admin role is missing
		// required caps, re-apply mappings.
		$admin_needs_repair = true;
		$admin_role = get_role('administrator');
		if ($admin_role instanceof WP_Role) {
			$admin_needs_repair =
				!$admin_role->has_cap(VMS_CAP_SAFETY_MANAGE) ||
				!$admin_role->has_cap(VMS_CAP_SAFETY_VIEW) ||
				!$admin_role->has_cap(VMS_CAP_SAFETY_EXPORT);
		}

		if ($installed === '1' && !$admin_needs_repair) {
			return;
		}

		foreach (vms_safety_default_cap_matrix() as $role_name => $caps) {
			$role = get_role($role_name);
			if (!$role instanceof WP_Role) {
				continue;
			}
			foreach ($caps as $cap) {
				$role->add_cap($cap);
			}
		}

		update_option($opt_key, '1', false);
	}
}
add_action('init', 'vms_safety_install_caps', 30);
