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

if (!function_exists('bvmgr_plugin_basename_compatibility_pair')) {
	function bvmgr_plugin_basename_compatibility_pair(string $legacy_plugin_file, string $canonical_plugin_file): array
	{
		$legacy_basename = bvmgr_plugin_basename_from_file($legacy_plugin_file);
		$canonical_basename = bvmgr_plugin_basename_from_file($canonical_plugin_file);
		if (basename($legacy_basename) !== 'vendor-management-system.php' || basename($canonical_basename) !== 'backstage-venue-manager.php') {
			return array();
		}
		if (dirname($legacy_basename) !== dirname($canonical_basename)) {
			return array();
		}

		return array(
			'legacy_file' => $legacy_plugin_file,
			'canonical_file' => $canonical_plugin_file,
			'legacy_basename' => $legacy_basename,
			'canonical_basename' => $canonical_basename,
		);
	}
}

if (!function_exists('bvmgr_migrate_legacy_plugin_basename_values')) {
	function bvmgr_migrate_legacy_plugin_basename_values(array $active_plugins, array $network_active_plugins, string $legacy_basename, string $canonical_basename): array
	{
		$legacy_basename = trim(str_replace('\\', '/', $legacy_basename), '/');
		$canonical_basename = trim(str_replace('\\', '/', $canonical_basename), '/');
		$site_changed = false;
		$network_changed = false;
		$normalized_active_plugins = array();
		$canonical_seen = false;

		foreach ($active_plugins as $active_plugin) {
			$normalized_active_plugin = trim(str_replace('\\', '/', (string) $active_plugin), '/');
			if ($normalized_active_plugin === $legacy_basename) {
				$normalized_active_plugin = $canonical_basename;
				$site_changed = true;
			}
			if ($normalized_active_plugin !== $canonical_basename) {
				$normalized_active_plugins[] = $active_plugin;
				continue;
			}
			if ($canonical_seen) {
				$site_changed = true;
				continue;
			}
			$canonical_seen = true;
			$normalized_active_plugins[] = $canonical_basename;
		}

		if (array_key_exists($legacy_basename, $network_active_plugins)) {
			if (!array_key_exists($canonical_basename, $network_active_plugins)) {
				$network_active_plugins[$canonical_basename] = $network_active_plugins[$legacy_basename];
			}
			unset($network_active_plugins[$legacy_basename]);
			$network_changed = true;
		}

		return array(
			'active_plugins' => $normalized_active_plugins,
			'network_active_plugins' => $network_active_plugins,
			'site_changed' => $site_changed,
			'network_changed' => $network_changed,
		);
	}
}

if (!function_exists('bvmgr_migrate_legacy_plugin_basename')) {
	function bvmgr_migrate_legacy_plugin_basename(string $legacy_plugin_file, string $canonical_plugin_file): bool
	{
		$pair = bvmgr_plugin_basename_compatibility_pair($legacy_plugin_file, $canonical_plugin_file);
		if ($pair === array() || !function_exists('get_option') || !function_exists('update_option')) {
			return false;
		}

		$active_plugins = (array) get_option('active_plugins', array());
		$network_active_plugins = array();
		$multisite = function_exists('is_multisite') && is_multisite();
		if ($multisite && function_exists('get_site_option')) {
			$network_active_plugins = (array) get_site_option('active_sitewide_plugins', array());
		}

		$migrated = bvmgr_migrate_legacy_plugin_basename_values(
			$active_plugins,
			$network_active_plugins,
			(string) $pair['legacy_basename'],
			(string) $pair['canonical_basename']
		);

		if (!empty($migrated['site_changed'])) {
			update_option('active_plugins', (array) $migrated['active_plugins']);
		}
		if ($multisite && !empty($migrated['network_changed']) && function_exists('update_site_option')) {
			update_site_option('active_sitewide_plugins', (array) $migrated['network_active_plugins']);
		}

		return !empty($migrated['site_changed']) || !empty($migrated['network_changed']);
	}
}

if (!function_exists('bvmgr_plugin_basename_compatibility_pairs')) {
	function bvmgr_plugin_basename_compatibility_pairs(?array $pair = null): array
	{
		static $pairs = array();
		if (is_array($pair) && isset($pair['legacy_basename'])) {
			$pairs[(string) $pair['legacy_basename']] = $pair;
		}

		return $pairs;
	}
}

if (!function_exists('bvmgr_migrate_registered_legacy_plugin_basenames')) {
	function bvmgr_migrate_registered_legacy_plugin_basenames(): void
	{
		foreach (bvmgr_plugin_basename_compatibility_pairs() as $pair) {
			bvmgr_migrate_legacy_plugin_basename((string) $pair['legacy_file'], (string) $pair['canonical_file']);
		}
	}
}

if (!function_exists('bvmgr_maybe_migrate_activated_legacy_plugin_basename')) {
	function bvmgr_maybe_migrate_activated_legacy_plugin_basename(string $plugin_basename, bool $network_wide = false): void
	{
		$plugin_basename = trim(str_replace('\\', '/', $plugin_basename), '/');
		foreach (bvmgr_plugin_basename_compatibility_pairs() as $pair) {
			if ($plugin_basename !== (string) $pair['legacy_basename']) {
				continue;
			}
			bvmgr_migrate_legacy_plugin_basename((string) $pair['legacy_file'], (string) $pair['canonical_file']);
		}
	}
}

if (!function_exists('bvmgr_register_legacy_plugin_basename_compatibility')) {
	function bvmgr_register_legacy_plugin_basename_compatibility(string $legacy_plugin_file, string $canonical_plugin_file): void
	{
		$pair = bvmgr_plugin_basename_compatibility_pair($legacy_plugin_file, $canonical_plugin_file);
		if ($pair === array()) {
			return;
		}

		bvmgr_plugin_basename_compatibility_pairs($pair);
		static $hooks_registered = false;
		if ($hooks_registered || !function_exists('add_action')) {
			return;
		}

		add_action('plugins_loaded', 'bvmgr_migrate_registered_legacy_plugin_basenames', 1);
		add_action('activated_plugin', 'bvmgr_maybe_migrate_activated_legacy_plugin_basename', 10, 2);
		$hooks_registered = true;
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
