<?php

defined('ABSPATH') || exit;

if (!class_exists('VMS_Addons_Manifest')) {
	class VMS_Addons_Manifest {
		public const OPTION_CACHE = 'vms_addons_manifest_cache';
		private const CACHE_TTL = 900;

		public static function entries(bool $force_refresh = false): array
		{
			$cached = (array) get_option(self::OPTION_CACHE, array());
			$now = time();
			$path = VMS_PLUGIN_PATH . 'assets/admin/addons/manifest-addons.json';
			$manifest_mtime = file_exists($path) ? (int) filemtime($path) : 0;
			$cache_valid = !$force_refresh
				&& !empty($cached['timestamp'])
				&& ($now - (int) $cached['timestamp'] < self::CACHE_TTL)
				&& !empty($cached['entries'])
				&& (int) ($cached['manifest_mtime'] ?? 0) === $manifest_mtime;

			if ($cache_valid) {
				$entries = (array) $cached['entries'];
			} else {
				$raw = file_exists($path) ? file_get_contents($path) : false;
				$entries = array();
				if (is_string($raw) && $raw !== '') {
					$parsed = json_decode($raw, true);
					if (is_array($parsed)) {
						$entries = $parsed;
					}
				}

				$entries = self::normalize_entries($entries);
				update_option(self::OPTION_CACHE, array(
					'timestamp' => $now,
					'manifest_mtime' => $manifest_mtime,
					'entries' => $entries,
				), false);
			}

			$entries = (array) apply_filters('vms_addons_manifest_entries', $entries);
			return self::normalize_entries($entries);
		}

		public static function by_slug(string $slug): ?array
		{
			$slug = sanitize_key($slug);
			foreach (self::entries() as $entry) {
				if (($entry['slug'] ?? '') === $slug) {
					return $entry;
				}
			}
			return null;
		}

		private static function normalize_entry($entry): array
		{
			if (!is_array($entry) || empty($entry['slug']) || empty($entry['name']) || empty($entry['plugin_file'])) {
				return array();
			}
			$slug = sanitize_key((string) $entry['slug']);
			return array(
				'slug' => $slug,
				'name' => sanitize_text_field((string) $entry['name']),
				'description_short' => sanitize_text_field((string) ($entry['description_short'] ?? '')),
				'category' => sanitize_text_field((string) ($entry['category'] ?? 'General')),
				'icon' => sanitize_text_field((string) ($entry['icon'] ?? 'dashicons-admin-plugins')),
				'plugin_file' => ltrim(sanitize_text_field((string) $entry['plugin_file']), '/'),
				'settings_url' => esc_url_raw((string) ($entry['settings_url'] ?? '')),
				'docs_url' => esc_url_raw((string) ($entry['docs_url'] ?? '')),
				'requires' => array_map('sanitize_key', (array) ($entry['requires'] ?? array())),
				'freemius' => is_array($entry['freemius'] ?? null) ? array('product_id' => absint($entry['freemius']['product_id'] ?? 0)) : array(),
				'install' => array(
					'method' => sanitize_key((string) (($entry['install']['method'] ?? 'zip_upload'))),
					'notes' => sanitize_text_field((string) (($entry['install']['notes'] ?? ''))),
				),
				'safe_remove' => !empty($entry['safe_remove']),
			);
		}

		private static function normalize_entries(array $entries): array
		{
			return array_values(array_filter(array_map(array(__CLASS__, 'normalize_entry'), $entries)));
		}
	}
}
