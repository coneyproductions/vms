<?php

defined('ABSPATH') || exit;


if (!function_exists('bvmgr_admin_ui_enqueue_global_menu_assets')) {
	function bvmgr_admin_ui_enqueue_global_menu_assets(): void
	{
		wp_enqueue_style(
			'vms-admin-menu',
			BVMGR_PLUGIN_URL . 'assets/css/vms-admin-menu.css',
			array(),
			bvmgr_admin_ui_asset_version()
		);
	}
}
add_action('admin_enqueue_scripts', 'bvmgr_admin_ui_enqueue_global_menu_assets', 5);

if (!function_exists('bvmgr_admin_ui_enqueue_assets')) {
	function bvmgr_admin_ui_enqueue_assets(): void
	{
		if (!bvmgr_admin_ui_is_vms_screen()) {
			return;
		}
 
		$deps = array();
		if (wp_style_is('vms-admin', 'registered') || wp_style_is('vms-admin', 'enqueued')) {
			$deps[] = 'vms-admin';
		}

		wp_enqueue_style(
			'vms-admin-ui',
			BVMGR_PLUGIN_URL . 'assets/css/vms-admin-ui.css',
			$deps,
			bvmgr_admin_ui_asset_version()
		);

		wp_enqueue_script(
			'vms-admin-ui',
			BVMGR_PLUGIN_URL . 'assets/js/vms-admin-ui.js',
			array(),
			bvmgr_admin_ui_asset_version(),
			true
		);

		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		$is_event_plan_screen = $screen
			&& in_array((string) $screen->base, array('post', 'post-new'), true)
			&& (string) ($screen->post_type ?? '') === 'vms_event_plan';

		if ($is_event_plan_screen) {
			wp_enqueue_script(
				'vms-event-plan-shell',
				BVMGR_PLUGIN_URL . 'assets/js/vms-event-plan-shell.js',
				array(),
				bvmgr_admin_ui_asset_version(),
				true
			);

			wp_enqueue_script(
				'vms-event-plan-staff',
				BVMGR_PLUGIN_URL . 'assets/js/vms-event-plan-staff.js',
				array(),
				bvmgr_admin_ui_asset_version(),
				true
			);

			wp_enqueue_script(
				'vms-event-plan-title',
				BVMGR_PLUGIN_URL . 'assets/js/vms-event-plan-title.js',
				array(),
				bvmgr_admin_ui_asset_version(),
				true
			);

			wp_enqueue_script(
				'vms-event-plan-primary-vendor',
				BVMGR_PLUGIN_URL . 'assets/js/vms-event-plan-primary-vendor.js',
				array(),
				bvmgr_admin_ui_asset_version(),
				true
			);

			wp_enqueue_script(
				'vms-event-plan-workflow',
				BVMGR_PLUGIN_URL . 'assets/js/vms-event-plan-workflow.js',
				array(),
				bvmgr_admin_ui_asset_version(),
				true
			);

			wp_enqueue_script(
				'vms-event-plan-compensation',
				BVMGR_PLUGIN_URL . 'assets/js/vms-event-plan-compensation.js',
				array(),
				bvmgr_admin_ui_asset_version(),
				true
			);

			wp_enqueue_script(
				'vms-event-plan-secondary-vendors',
				BVMGR_PLUGIN_URL . 'assets/js/vms-event-plan-secondary-vendors.js',
				array(),
				bvmgr_admin_ui_asset_version(),
				true
			);

			wp_enqueue_script(
				'vms-lineup-schedule-admin',
				BVMGR_PLUGIN_URL . 'assets/js/vms-lineup-schedule-admin.js',
				array('vms-admin-ui'),
				bvmgr_admin_ui_asset_version(),
				true
			);
		}
	}
}
add_action('admin_enqueue_scripts', 'bvmgr_admin_ui_enqueue_assets', 40);
