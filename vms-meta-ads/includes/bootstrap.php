<?php

defined('ABSPATH') || exit;

if (!class_exists('VMS_Meta_Ads')) {
	class VMS_Meta_Ads {
		public const DB_VERSION = '1';
		public const MODULE_SLUG = 'meta_ads_builder';

		public static function init(): void
		{
			self::load_dependencies();
			add_action('plugins_loaded', array(__CLASS__, 'register_module'), 20);
			if (!self::is_module_enabled()) {
				add_action('admin_notices', array(__CLASS__, 'render_locked_notice'));
				return;
			}
			VMS_Meta_Ads_Caps::register();
			VMS_Meta_Ads_Admin::init();
			VMS_Meta_Ads_Rest::init();
			VMS_Meta_Ads_Tours::init();
			add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
		}

		public static function register_module(): void
		{
			if (!function_exists('vms_register_module')) {
				return;
			}

			vms_register_module(array(
				'slug' => self::MODULE_SLUG,
				'name' => 'Meta Ads Builder',
				'version' => VMS_MA_VERSION,
				'premium' => true,
				'description' => 'Meta Ads Builder wizard with copy pack and guarded API scaffold.',
				'source' => 'vms-meta-ads',
			));

			add_action('vms_register_docs_sources', array(__CLASS__, 'register_docs_source'));
		}

		public static function register_docs_source(callable $register): void
		{
			$register(array(
				'module' => 'vms_meta_ads',
				'label' => 'VMS Meta Ads',
				'path' => VMS_MA_PATH . 'docs',
				'public_base' => 'vms-meta-ads',
			));
		}

		public static function is_module_enabled(): bool
		{
			if (function_exists('vms_module_is_enabled')) {
				return (bool) vms_module_is_enabled(self::MODULE_SLUG);
			}
			return true;
		}

		public static function render_locked_notice(): void
		{
			if (!is_admin()) {
				return;
			}
			if (!current_user_can('manage_options')) {
				return;
			}
			echo '<div class="notice notice-warning"><p>' .
				esc_html__('VMS Meta Ads Builder is installed but locked. Enable premium license access for module slug "meta_ads_builder" to activate it.', 'vms-meta-ads') .
				'</p></div>';
		}

		public static function activate(): void
		{
			VMS_Meta_Ads_DB::install();
		}

		public static function deactivate(): void
		{
			VMS_Meta_Ads_Tours::reset_all_tours();
		}

		public static function enqueue_assets(): void
		{
			if (!is_admin()) {
				return;
			}
			$page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';
			if (!in_array($page, array('vms-ma-ads-builder', 'vms-ma-ads-promote', 'vms-ma-ads-performance', 'vms-ma-ads-settings', 'vms-ma-ads-logs'), true)) {
				return;
			}
			wp_enqueue_style('vms-ma-admin', VMS_MA_URL . 'assets/admin.css', array(), VMS_MA_VERSION);
			wp_enqueue_script('vms-ma-ads-builder', VMS_MA_URL . 'assets/ads-builder.js', array('jquery'), VMS_MA_VERSION, true);
			wp_enqueue_script('vms-ma-guidance', VMS_MA_URL . 'assets/guidance.js', array('jquery'), VMS_MA_VERSION, true);
			wp_enqueue_script('vms-ma-help-mode', VMS_MA_URL . 'assets/help-mode.js', array('jquery'), VMS_MA_VERSION, true);
			if ($page === 'vms-ma-ads-settings') {
				wp_enqueue_script('vms-ma-settings', VMS_MA_URL . 'assets/settings.js', array('jquery'), VMS_MA_VERSION, true);
			}
			if (in_array($page, array('vms-ma-ads-builder', 'vms-ma-ads-settings'), true)) {
				if (function_exists('vms_enqueue_tour_assets')) {
					vms_enqueue_tour_assets();
				} elseif (defined('VMS_PLUGIN_URL') && defined('VMS_PLUGIN_PATH')) {
					$version = defined('VMS_VERSION') ? (string) VMS_VERSION : VMS_MA_VERSION;
					if (file_exists(VMS_PLUGIN_PATH . 'assets/vendor/driverjs/driver.min.css')) {
						wp_enqueue_style('vms-driverjs', VMS_PLUGIN_URL . 'assets/vendor/driverjs/driver.min.css', array(), $version);
					}
					if (file_exists(VMS_PLUGIN_PATH . 'assets/vendor/driverjs/driver.min.js')) {
						wp_enqueue_script('vms-driverjs', VMS_PLUGIN_URL . 'assets/vendor/driverjs/driver.min.js', array(), $version, true);
					}
					wp_enqueue_style('vms-tours', VMS_PLUGIN_URL . 'assets/css/vms-tours.css', array('vms-admin'), $version);
					wp_enqueue_script('vms-tours', VMS_PLUGIN_URL . 'assets/js/vms-tours.js', array(), $version, true);
				}
				wp_enqueue_script('vms-ma-tours', VMS_MA_URL . 'assets/tours.js', array('jquery'), VMS_MA_VERSION, true);
			}
		}

		private static function load_dependencies(): void
		{
			require_once VMS_MA_PATH . 'includes/caps.php';
			require_once VMS_MA_PATH . 'includes/db.php';
			require_once VMS_MA_PATH . 'includes/logger.php';
			require_once VMS_MA_PATH . 'includes/utils.php';
			require_once VMS_MA_PATH . 'includes/services-ad-builds.php';
			require_once VMS_MA_PATH . 'includes/services-event-plans.php';
			require_once VMS_MA_PATH . 'includes/meta-api/endpoints.php';
			require_once VMS_MA_PATH . 'includes/meta-api/client.php';
			require_once VMS_MA_PATH . 'includes/shared/marketing-assets.php';
			require_once VMS_MA_PATH . 'includes/copy/access-token.php';
			require_once VMS_MA_PATH . 'includes/help/registry.php';
			require_once VMS_MA_PATH . 'includes/admin/enqueue.php';
			require_once VMS_MA_PATH . 'includes/admin/event-plan-metabox.php';
			require_once VMS_MA_PATH . 'includes/admin/menu.php';
			require_once VMS_MA_PATH . 'includes/rest/routes.php';
			require_once VMS_MA_PATH . 'includes/tours/registry.php';
		}
	}
}
