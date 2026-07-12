<?php

defined('ABSPATH') || exit;


if (!function_exists('vms_admin_ui_enqueue_global_menu_assets')) {
	function vms_admin_ui_enqueue_global_menu_assets(): void
	{
		wp_enqueue_style(
			'vms-admin-menu',
			VMS_PLUGIN_URL . 'assets/css/vms-admin-menu.css',
			array(),
			vms_admin_ui_asset_version()
		);
	}
}
add_action('admin_enqueue_scripts', 'vms_admin_ui_enqueue_global_menu_assets', 5);

if (!function_exists('vms_admin_ui_enqueue_assets')) {
	function vms_admin_ui_enqueue_assets(): void
	{
		if (!vms_admin_ui_is_vms_screen()) {
			return;
		}
 
		$deps = array();
		if (wp_style_is('vms-admin', 'registered') || wp_style_is('vms-admin', 'enqueued')) {
			$deps[] = 'vms-admin';
		}

		wp_enqueue_style(
			'vms-admin-ui',
			VMS_PLUGIN_URL . 'assets/css/vms-admin-ui.css',
			$deps,
			vms_admin_ui_asset_version()
		);

		wp_enqueue_script(
			'vms-admin-ui',
			VMS_PLUGIN_URL . 'assets/js/vms-admin-ui.js',
			array(),
			vms_admin_ui_asset_version(),
			true
		);

		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		$is_event_plan_screen = $screen
			&& in_array((string) $screen->base, array('post', 'post-new'), true)
			&& (string) ($screen->post_type ?? '') === 'vms_event_plan';

		if ($is_event_plan_screen) {
			wp_enqueue_script(
				'vms-event-plan-shell',
				VMS_PLUGIN_URL . 'assets/js/vms-event-plan-shell.js',
				array(),
				vms_admin_ui_asset_version(),
				true
			);

			wp_enqueue_script(
				'vms-lineup-schedule-admin',
				VMS_PLUGIN_URL . 'assets/js/vms-lineup-schedule-admin.js',
				array('vms-admin-ui'),
				vms_admin_ui_asset_version(),
				true
			);
		}
	}
}
add_action('admin_enqueue_scripts', 'vms_admin_ui_enqueue_assets', 40);
