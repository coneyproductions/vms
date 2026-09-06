<?php

defined('ABSPATH') || exit;

if (!class_exists('VMSX_Weather_Risk_Admin_Menu')) {
	class VMSX_Weather_Risk_Admin_Menu {
		public const DETAILS_SLUG = 'vms-weather-risk';
		public const SETTINGS_SLUG = 'vms-weather-risk-settings';
		public const LEGACY_DETAILS_SLUG = 'vmsx-weather-risk';
		public const LEGACY_SETTINGS_SLUG = 'vmsx-weather-risk-settings';

		public static function init(): void
		{
			add_action('admin_menu', array(__CLASS__, 'register_pages'), 40);
			add_action('admin_init', array(__CLASS__, 'maybe_redirect_legacy_pages'), 20);
			add_filter('vms_admin_ui_nav_cluster_items', array(__CLASS__, 'register_compact_nav_items'), 10, 3);
			add_filter('vms_admin_ui_active_cluster', array(__CLASS__, 'filter_active_cluster'), 10, 3);
			add_filter('vms_admin_ui_shell_pages', array(__CLASS__, 'register_shell_pages'));
		}

		public static function register_pages(): void
		{
			$parent = apply_filters('vms_admin_parent_slug', 'vms-dashboard');

			add_submenu_page(
				$parent,
				__('Show Risk Advisor', 'vmsx-weather-risk'),
				__('Show Risk Advisor', 'vmsx-weather-risk'),
				VMSX_Weather_Risk_Capabilities::settings_capability(),
				self::DETAILS_SLUG,
				array('VMSX_Weather_Risk_Page_Event_Risk', 'render')
			);

			add_submenu_page(
				$parent,
				__('Show Risk Settings', 'vmsx-weather-risk'),
				__('Show Risk Settings', 'vmsx-weather-risk'),
				VMSX_Weather_Risk_Capabilities::settings_capability(),
				self::SETTINGS_SLUG,
				array('VMSX_Weather_Risk_Page_Settings', 'render')
			);
		}

		public static function maybe_redirect_legacy_pages(): void
		{
			if (!is_admin()) {
				return;
			}

			$page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';
			if ($page !== self::LEGACY_DETAILS_SLUG && $page !== self::LEGACY_SETTINGS_SLUG) {
				return;
			}

			$args = array();
			if ($page === self::LEGACY_DETAILS_SLUG) {
				$args['page'] = self::DETAILS_SLUG;
				$event_plan_id = isset($_GET['event_plan_id']) ? absint($_GET['event_plan_id']) : 0;
				if ($event_plan_id > 0) {
					$args['event_plan_id'] = $event_plan_id;
				}
			} else {
				$args['page'] = self::SETTINGS_SLUG;
				$notice = isset($_GET['vmsx_weather_risk_notice']) ? sanitize_key((string) $_GET['vmsx_weather_risk_notice']) : '';
				if ($notice !== '') {
					$args['vmsx_weather_risk_notice'] = $notice;
				}
			}

			wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
			exit;
		}

		/**
		 * @param array<int,array<string,string>> $items
		 * @param string                          $cluster_key
		 * @param array<string,mixed>             $cluster
		 * @return array<int,array<string,string>>
		 */
		public static function register_compact_nav_items(array $items, string $cluster_key, array $cluster): array
		{
			unset($cluster);

			if (!VMSX_Weather_Risk_Capabilities::can_manage_settings()) {
				return $items;
			}

			if ($cluster_key === 'planning') {
				$items[] = array(
					'label' => __('Show Risk Advisor', 'vmsx-weather-risk'),
					'url'   => self::details_url(),
				);
			}

			if ($cluster_key === 'settings') {
				$items[] = array(
					'label' => __('Show Risk Settings', 'vmsx-weather-risk'),
					'url'   => self::settings_url(),
				);
			}

			return $items;
		}

		public static function filter_active_cluster(string $cluster, string $page, string $post_type): string
		{
			unset($post_type);

			if ($cluster !== '') {
				return $cluster;
			}

			if ($page === self::DETAILS_SLUG) {
				return 'planning';
			}

			if ($page === self::SETTINGS_SLUG) {
				return 'settings';
			}

			return '';
		}

		/**
		 * @param array<int,string> $shell_pages
		 * @return array<int,string>
		 */
		public static function register_shell_pages(array $shell_pages): array
		{
			$shell_pages[] = self::DETAILS_SLUG;
			$shell_pages[] = self::SETTINGS_SLUG;
			return array_values(array_unique(array_filter($shell_pages)));
		}

		public static function details_url(int $event_plan_id = 0): string
		{
			$args = array('page' => self::DETAILS_SLUG);
			if ($event_plan_id > 0) {
				$args['event_plan_id'] = $event_plan_id;
			}

			return add_query_arg($args, admin_url('admin.php'));
		}

		public static function settings_url(): string
		{
			return add_query_arg(array('page' => self::SETTINGS_SLUG), admin_url('admin.php'));
		}
	}
}
