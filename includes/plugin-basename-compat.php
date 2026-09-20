<?php
defined('ABSPATH') || exit;

if (!function_exists('bvmgr_plugin_basename_from_file')) {
	function bvmgr_plugin_basename_from_file(string $plugin_file): string
	{
		$plugin_file = str_replace('\\', '/', $plugin_file);
		if (function_exists('plugin_basename')) {
			return trim(str_replace('\\', '/', (string) plugin_basename($plugin_file)), '/');
		}

		return basename(dirname($plugin_file)) . '/' . basename($plugin_file);
	}
}

if (!function_exists('bvmgr_plugin_lifecycle_basename')) {
	function bvmgr_plugin_lifecycle_basename(): string
	{
		if (defined('BVMGR_LEGACY_PLUGIN_FILE') && is_string(BVMGR_LEGACY_PLUGIN_FILE) && BVMGR_LEGACY_PLUGIN_FILE !== '') {
			return bvmgr_plugin_basename_from_file(BVMGR_LEGACY_PLUGIN_FILE);
		}
		if (defined('BVMGR_PLUGIN_FILE') && is_string(BVMGR_PLUGIN_FILE) && BVMGR_PLUGIN_FILE !== '') {
			return bvmgr_plugin_basename_from_file(BVMGR_PLUGIN_FILE);
		}

		return basename(dirname(__DIR__)) . '/backstage-venue-manager.php';
	}
}

if (!function_exists('bvmgr_recognized_plugin_lifecycle_basenames')) {
	function bvmgr_recognized_plugin_lifecycle_basenames(): array
	{
		$basenames = array(
			'backstage-venue-manager/backstage-venue-manager.php',
			'backstage-venue-manager/vendor-management-system.php',
			'vms/backstage-venue-manager.php',
			'vms/vendor-management-system.php',
		);
		$current_basename = bvmgr_plugin_lifecycle_basename();
		$current_directory = dirname($current_basename);
		if ($current_directory !== '' && $current_directory !== '.') {
			$basenames[] = $current_directory . '/backstage-venue-manager.php';
			$basenames[] = $current_directory . '/vendor-management-system.php';
		}

		return array_values(array_unique($basenames));
	}
}
