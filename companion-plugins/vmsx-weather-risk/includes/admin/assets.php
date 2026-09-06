<?php

defined('ABSPATH') || exit;

if (!class_exists('VMSX_Weather_Risk_Admin_Assets')) {
	class VMSX_Weather_Risk_Admin_Assets {
		public static function init(): void
		{
			add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue'));
		}

		public static function enqueue(string $hook_suffix): void
		{
			unset($hook_suffix);

			$screen = function_exists('get_current_screen') ? get_current_screen() : null;
			$page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';
			$on_event_plan = $screen && isset($screen->post_type) && $screen->post_type === 'vms_event_plan';
			$on_plugin_page = in_array($page, array('vms-weather-risk', 'vms-weather-risk-settings'), true);

			if (!$on_event_plan && !$on_plugin_page) {
				return;
			}

			$css_ver = file_exists(VMSX_WR_PATH . 'assets/admin/weather-risk.css')
				? (string) filemtime(VMSX_WR_PATH . 'assets/admin/weather-risk.css')
				: VMSX_WR_VERSION;
			$js_ver = file_exists(VMSX_WR_PATH . 'assets/admin/weather-risk.js')
				? (string) filemtime(VMSX_WR_PATH . 'assets/admin/weather-risk.js')
				: VMSX_WR_VERSION;

			wp_enqueue_style(
				'vmsx-weather-risk-admin',
				VMSX_WR_URL . 'assets/admin/weather-risk.css',
				array(),
				$css_ver
			);
			wp_enqueue_script(
				'vmsx-weather-risk-admin',
				VMSX_WR_URL . 'assets/admin/weather-risk.js',
				array(),
				$js_ver,
				true
			);
			wp_localize_script('vmsx-weather-risk-admin', 'VMSXWeatherRisk', array(
				'ajaxUrl' => admin_url('admin-ajax.php'),
				'nonce' => wp_create_nonce('vmsx_weather_risk_refresh'),
				'action' => VMSX_WR_AJAX_REFRESH,
				'detailsPage' => $page === 'vms-weather-risk' ? 1 : 0,
				'refreshingText' => __('Refreshing Show Risk…', 'vmsx-weather-risk'),
				'errorText' => __('Unable to refresh Show Risk right now.', 'vmsx-weather-risk'),
				'successText' => __('Show Risk refreshed.', 'vmsx-weather-risk'),
			));
		}
	}
}
