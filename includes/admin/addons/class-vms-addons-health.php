<?php

defined('ABSPATH') || exit;

if (!class_exists('VMS_Addons_Health')) {
	class VMS_Addons_Health {
		public const OPTION_LAST_HEALTH = 'vms_addons_last_health';

		public static function check(): array
		{
			require_once ABSPATH . 'wp-admin/includes/file.php';
			$fs_ok = (bool) WP_Filesystem();
			$fs_method = get_filesystem_method();

			$freemius = wp_remote_get('https://api.freemius.com', array('timeout' => 10));
			$freemius_ok = !is_wp_error($freemius) && (int) wp_remote_retrieve_response_code($freemius) < 500;

			$result = array(
				'timestamp' => wp_date('Y-m-d H:i:s', null, wp_timezone()),
				'freemius_reachable' => $freemius_ok,
				'filesystem_ok' => $fs_ok,
				'filesystem_method' => $fs_method,
				'system_status' => ($freemius_ok && $fs_ok) ? 'all_good' : 'action_needed',
			);
			update_option(self::OPTION_LAST_HEALTH, $result, false);
			return $result;
		}

		public static function export_payload(array $state): array
		{
			$theme = wp_get_theme();
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			$active_plugins = (array) get_option('active_plugins', array());
			$licenses = VMS_Addons_Licensing::store();
			foreach ($licenses as $slug => $entry) {
				if (!empty($entry['license_key'])) {
					$licenses[$slug]['license_key'] = VMS_Addons_Licensing::masked_key((string) $entry['license_key']);
				}
			}

			return array(
				'vms_version' => defined('VMS_VERSION') ? VMS_VERSION : '',
				'wp_version' => get_bloginfo('version'),
				'php_version' => PHP_VERSION,
				'active_theme' => array('name' => $theme->get('Name'), 'version' => $theme->get('Version')),
				'active_plugins' => $active_plugins,
				'manifest_state' => $state,
				'license_statuses' => $licenses,
				'last_errors' => array_values(array_filter(VMS_Addons_Logger::recent(50), static function ($row) {
					return ($row['level'] ?? '') === 'error';
				})),
			);
		}
	}
}
