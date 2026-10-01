<?php

defined('ABSPATH') || exit;

if (!function_exists('bvmgr_admin_ui_local_asset_version')) {
	function bvmgr_admin_ui_local_asset_version(string $relative_path): string
	{
		$version = bvmgr_admin_ui_asset_version();
		$environment = function_exists('wp_get_environment_type') ? (string) wp_get_environment_type() : 'production';
		if (!in_array($environment, array('local', 'development'), true) || !defined('BVMGR_PLUGIN_PATH')) {
			return $version;
		}

		$asset_path = rtrim((string) BVMGR_PLUGIN_PATH, '/\\') . '/' . ltrim($relative_path, '/\\');
		if (!is_file($asset_path)) {
			return $version;
		}

		$asset_mtime = filemtime($asset_path);
		return $asset_mtime === false ? $version : $version . '-local-' . (string) $asset_mtime;
	}
}


if (!function_exists('bvmgr_admin_ui_enqueue_global_menu_assets')) {
	function bvmgr_admin_ui_enqueue_global_menu_assets(): void
	{
		wp_enqueue_style(
			'bvmgr-admin-menu',
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
		if (wp_style_is('bvmgr-admin', 'registered') || wp_style_is('bvmgr-admin', 'enqueued')) {
			$deps[] = 'bvmgr-admin';
		}

		wp_enqueue_style(
			'bvmgr-admin-ui',
			BVMGR_PLUGIN_URL . 'assets/css/vms-admin-ui.css',
			$deps,
			bvmgr_admin_ui_asset_version()
		);

		wp_enqueue_script(
			'bvmgr-admin-ui',
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
			$event_plan_shell_version = bvmgr_admin_ui_local_asset_version('assets/js/vms-event-plan-shell.js');
			$event_plan_admin_style_version = bvmgr_admin_ui_local_asset_version('assets/css/vms-admin.css');
			$event_plan_ticketing_version = bvmgr_admin_ui_local_asset_version('assets/admin-ticketing.js');
			$registered_styles = wp_styles();
			if (isset($registered_styles->registered['bvmgr-admin'])) {
				$registered_styles->registered['bvmgr-admin']->ver = $event_plan_admin_style_version;
			}
			$registered_scripts = wp_scripts();
			if (isset($registered_scripts->registered['bvmgr-admin-ticketing'])) {
				$registered_scripts->registered['bvmgr-admin-ticketing']->ver = $event_plan_ticketing_version;
			}

			wp_enqueue_script(
				'bvmgr-event-plan-shell',
				BVMGR_PLUGIN_URL . 'assets/js/vms-event-plan-shell.js',
				array(),
				$event_plan_shell_version,
				true
			);

			wp_enqueue_script(
				'bvmgr-event-plan-staff',
				BVMGR_PLUGIN_URL . 'assets/js/vms-event-plan-staff.js',
				array(),
				bvmgr_admin_ui_asset_version(),
				true
			);

			wp_enqueue_script(
				'bvmgr-event-plan-title',
				BVMGR_PLUGIN_URL . 'assets/js/vms-event-plan-title.js',
				array(),
				bvmgr_admin_ui_asset_version(),
				true
			);

			wp_enqueue_script(
				'bvmgr-event-plan-primary-vendor',
				BVMGR_PLUGIN_URL . 'assets/js/vms-event-plan-primary-vendor.js',
				array(),
				bvmgr_admin_ui_asset_version(),
				true
			);

			wp_enqueue_script(
				'bvmgr-event-plan-workflow',
				BVMGR_PLUGIN_URL . 'assets/js/vms-event-plan-workflow.js',
				array(),
				bvmgr_admin_ui_asset_version(),
				true
			);

			wp_enqueue_script(
				'bvmgr-event-plan-compensation',
				BVMGR_PLUGIN_URL . 'assets/js/vms-event-plan-compensation.js',
				array(),
				bvmgr_admin_ui_asset_version(),
				true
			);

			wp_enqueue_script(
				'bvmgr-event-plan-secondary-vendors',
				BVMGR_PLUGIN_URL . 'assets/js/vms-event-plan-secondary-vendors.js',
				array(),
				bvmgr_admin_ui_asset_version() . '-participation-v1',
				true
			);

			wp_enqueue_script(
				'bvmgr-lineup-schedule-admin',
				BVMGR_PLUGIN_URL . 'assets/js/vms-lineup-schedule-admin.js',
				array('bvmgr-admin-ui'),
				bvmgr_admin_ui_asset_version(),
				true
			);
		}
	}
}
add_action('admin_enqueue_scripts', 'bvmgr_admin_ui_enqueue_assets', 40);
