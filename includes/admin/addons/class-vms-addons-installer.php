<?php

defined('ABSPATH') || exit;

if (!class_exists('VMS_Addons_Installer')) {
	class VMS_Addons_Installer {
		public static function install_zip(array $file)
		{
			if (empty($file['tmp_name']) || empty($file['name'])) {
				return new WP_Error('missing_zip', __('No ZIP file was uploaded.', 'backstage-venue-manager'));
			}

			if (!class_exists('Plugin_Upgrader')) {
				require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			}
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/plugin.php';

			$check = wp_check_filetype((string) $file['name']);
			if (($check['ext'] ?? '') !== 'zip') {
				return new WP_Error('invalid_zip', __('Only ZIP files are supported for add-on installation.', 'backstage-venue-manager'));
			}

			$upgrader = new Plugin_Upgrader(new Automatic_Upgrader_Skin());
			$result = $upgrader->install((string) $file['tmp_name']);
			if (is_wp_error($result)) {
				return $result;
			}
			if (!$result) {
				return new WP_Error('install_failed', __('Plugin installation failed. Check filesystem permissions.', 'backstage-venue-manager'));
			}

			$plugin_file = '';
			$info = $upgrader->plugin_info();
			if (is_string($info) && $info !== '') {
				$plugin_file = $info;
			}

			return array('plugin_file' => $plugin_file);
		}

		public static function activate(string $plugin_file)
		{
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			$result = activate_plugin($plugin_file, '', false, false);
			if (is_wp_error($result)) {
				return $result;
			}
			return true;
		}

		public static function deactivate(string $plugin_file)
		{
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			deactivate_plugins($plugin_file, false, false);
			return true;
		}

		public static function update(string $plugin_file)
		{
			if (!class_exists('Plugin_Upgrader')) {
				require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			}
			$upgrader = new Plugin_Upgrader(new Automatic_Upgrader_Skin());
			$result = $upgrader->upgrade($plugin_file);
			if (is_wp_error($result)) {
				return $result;
			}
			if ($result === false) {
				return new WP_Error('update_failed', __('Update failed. Check filesystem credentials and plugin package integrity.', 'backstage-venue-manager'));
			}
			return true;
		}
	}
}
