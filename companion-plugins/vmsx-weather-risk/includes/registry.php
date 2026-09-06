<?php

defined('ABSPATH') || exit;

if (!defined('VMSX_WR_MIN_VMS_VERSION')) {
	define('VMSX_WR_MIN_VMS_VERSION', '0.2.24.505');
}
if (!defined('VMSX_WR_MODULE_SLUG')) {
	define('VMSX_WR_MODULE_SLUG', 'weather_risk');
}
if (!defined('VMSX_WR_OPTION_SETTINGS')) {
	define('VMSX_WR_OPTION_SETTINGS', 'vmsx_weather_risk_settings');
}
if (!defined('VMSX_WR_OPTION_LOG')) {
	define('VMSX_WR_OPTION_LOG', 'vmsx_weather_risk_log');
}
if (!defined('VMSX_WR_META_SNAPSHOT')) {
	define('VMSX_WR_META_SNAPSHOT', 'vmsx_weather_risk_snapshot');
}
if (!defined('VMSX_WR_CRON_HOOK')) {
	define('VMSX_WR_CRON_HOOK', 'vmsx_weather_risk_refresh_cron');
}
if (!defined('VMSX_WR_AJAX_REFRESH')) {
	define('VMSX_WR_AJAX_REFRESH', 'vmsx_weather_risk_refresh');
}
if (!defined('VMSX_WR_TRANSIENT_PROVIDER_PREFIX')) {
	define('VMSX_WR_TRANSIENT_PROVIDER_PREFIX', 'vmsx_wr_provider_');
}

if (!function_exists('vmsx_weather_risk_manifest_entry')) {
	function vmsx_weather_risk_manifest_entry(): array
	{
		return array(
			'slug' => 'vmsx-weather-risk',
			'name' => 'Show Risk Advisor',
			'description_short' => 'Cached weather + ticket context advisory for outdoor event decisions.',
			'category' => 'Operations',
			'icon' => 'dashicons-cloud',
			'plugin_file' => 'vmsx-weather-risk/vmsx-weather-risk.php',
			'settings_url' => 'admin.php?page=vms-weather-risk-settings',
			'docs_url' => '',
			'requires' => array('vms'),
			'freemius' => array(
				'product_id' => 0,
			),
			'install' => array(
				'method' => 'zip_upload',
				'notes' => 'Upload the packaged vmsx-weather-risk zip to install or upgrade in place.',
			),
			'safe_remove' => false,
		);
	}
}
