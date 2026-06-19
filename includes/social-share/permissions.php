<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_social_manage_capability')) {
	function vms_social_manage_capability(): string
	{
		return defined('VMS_CAP_SOCIAL_MANAGE') ? (string) VMS_CAP_SOCIAL_MANAGE : 'vms_social_manage';
	}
}

if (!function_exists('vms_social_operator_capability')) {
	function vms_social_operator_capability(): string
	{
		$cap = apply_filters('vms_social_operator_capability', 'edit_posts');
		return is_string($cap) && $cap !== '' ? $cap : 'edit_posts';
	}
}

if (!function_exists('vms_social_ensure_capability_mapping')) {
	function vms_social_ensure_capability_mapping(): void
	{
		$cap = vms_social_manage_capability();
		$role = get_role('administrator');
		if ($role instanceof WP_Role && !$role->has_cap($cap)) {
			$role->add_cap($cap);
		}
	}
}
add_action('init', 'vms_social_ensure_capability_mapping', 20);

if (!function_exists('vms_social_current_user_can_manage')) {
	function vms_social_current_user_can_manage(): bool
	{
		$cap = vms_social_manage_capability();
		if (current_user_can($cap)) {
			return true;
		}

		// Backward compatibility for existing operator flows that still rely on manage_options.
		if (current_user_can('manage_options')) {
			return true;
		}

		// Operator parity fallback: users who can edit Event Plans should retain access.
		return current_user_can(vms_social_operator_capability());
	}
}

if (!function_exists('vms_social_require_manage_capability')) {
	function vms_social_require_manage_capability(): void
	{
		if (vms_social_current_user_can_manage()) {
			return;
		}

		wp_die(esc_html__('You do not have permission to manage social sharing.', 'vms'));
	}
}
