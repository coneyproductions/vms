<?php

defined('ABSPATH') || exit;

require_once VMSX_WR_PATH . 'includes/registry.php';
require_once VMSX_WR_PATH . 'includes/compatibility.php';
require_once VMSX_WR_PATH . 'includes/helpers.php';
require_once VMSX_WR_PATH . 'includes/capabilities.php';
require_once VMSX_WR_PATH . 'includes/settings.php';
require_once VMSX_WR_PATH . 'includes/cache/cache.php';
require_once VMSX_WR_PATH . 'includes/services/logging.php';
require_once VMSX_WR_PATH . 'includes/services/decision-window.php';
require_once VMSX_WR_PATH . 'includes/services/venue-location.php';
require_once VMSX_WR_PATH . 'includes/services/ticket-context.php';
require_once VMSX_WR_PATH . 'includes/services/dt-enrichment.php';
require_once VMSX_WR_PATH . 'includes/services/weather-normalizer.php';
require_once VMSX_WR_PATH . 'includes/providers/interface-provider.php';
require_once VMSX_WR_PATH . 'includes/providers/provider-noaa.php';
require_once VMSX_WR_PATH . 'includes/providers/provider-openmeteo.php';
require_once VMSX_WR_PATH . 'includes/providers/provider-openweather.php';
require_once VMSX_WR_PATH . 'includes/providers/provider-weatherapi.php';
require_once VMSX_WR_PATH . 'includes/services/advisory-engine.php';
require_once VMSX_WR_PATH . 'includes/cache/scheduler.php';
require_once VMSX_WR_PATH . 'includes/admin/load.php';
require_once VMSX_WR_PATH . 'includes/ajax/refresh.php';

if (!class_exists('VMSX_Weather_Risk')) {
	class VMSX_Weather_Risk {
		public static function init(): void
		{
			VMSX_Weather_Risk_Settings::init();
			add_action('plugins_loaded', array(__CLASS__, 'register_module'), 20);
			add_action('plugins_loaded', array(__CLASS__, 'boot'), 25);
			add_action('admin_notices', array('VMSX_Weather_Risk_Compatibility', 'render_admin_notice'));
			add_filter('vms_addons_manifest_entries', array(__CLASS__, 'filter_manifest_entries'));
		}

		public static function activate(): void
		{
			if (!VMSX_Weather_Risk_Compatibility::is_ready()) {
				return;
			}

			VMSX_Weather_Risk_Settings::seed_defaults();
			VMSX_Weather_Risk_Scheduler::activate();
		}

		public static function deactivate(): void
		{
			VMSX_Weather_Risk_Scheduler::deactivate();
		}

		public static function register_module(): void
		{
			$register_module = VMSX_Weather_Risk_Compatibility::core_function('vms_register_module');
			if ($register_module !== '') {
				$register_module(array(
					'slug' => VMSX_WR_MODULE_SLUG,
					'name' => 'Show Risk Advisor',
					'version' => VMSX_WR_VERSION,
					'premium' => true,
					'description' => 'Explainable weather risk advisory for outdoor Event Plans.',
					'source' => 'vmsx-weather-risk',
				));
			}

			add_action('vms_register_docs_sources', array(__CLASS__, 'register_docs_source'));
		}

		public static function register_docs_source(callable $register): void
		{
			$register(array(
				'module' => 'vmsx_weather_risk',
				'label' => 'Show Risk Advisor',
				'path' => VMSX_WR_PATH . 'docs',
				'public_base' => 'vmsx-weather-risk',
			));
		}

		public static function boot(): void
		{
			if (!VMSX_Weather_Risk_Compatibility::is_ready()) {
				return;
			}

			VMSX_Weather_Risk_Scheduler::init();
			VMSX_Weather_Risk_Venue_Location::init();
			VMSX_Weather_Risk_Admin::init();
			VMSX_Weather_Risk_Ajax_Refresh::init();
		}

		public static function filter_manifest_entries(array $entries): array
		{
			$entry = vmsx_weather_risk_manifest_entry();
			$slug = sanitize_key((string) ($entry['slug'] ?? ''));
			if ($slug === '') {
				return $entries;
			}

			$replaced = false;
			foreach ($entries as $index => $candidate) {
				if (!is_array($candidate)) {
					continue;
				}

				$candidate_slug = sanitize_key((string) ($candidate['slug'] ?? ''));
				if ($candidate_slug !== $slug) {
					continue;
				}

				$entries[$index] = array_merge($candidate, $entry);
				$replaced = true;
				break;
			}

			if (!$replaced) {
				$entries[] = $entry;
			}

			return $entries;
		}
	}
}
