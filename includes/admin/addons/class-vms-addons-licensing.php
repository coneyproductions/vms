<?php

defined('ABSPATH') || exit;

if (!class_exists('VMS_Addons_Licensing')) {
	class VMS_Addons_Licensing {
		public const OPTION_STORE = 'vms_addons_license_store';
		private const OPTION_UID = 'vms_addons_uid';

		public static function store(): array
		{
			$store = get_option(self::OPTION_STORE, array());
			return is_array($store) ? $store : array();
		}

		public static function save_entry(string $slug, array $entry): array
		{
			$slug = sanitize_key($slug);
			$store = self::store();
			$current = isset($store[$slug]) && is_array($store[$slug]) ? $store[$slug] : array();
			$merged = array_merge($current, $entry);
			$merged['slug'] = $slug;
			$merged['provider'] = 'freemius';
			$merged['license_key'] = sanitize_text_field((string) ($merged['license_key'] ?? ''));
			$merged['status'] = sanitize_key((string) ($merged['status'] ?? 'unknown'));
			$merged['status_message'] = sanitize_text_field((string) ($merged['status_message'] ?? ''));
			$merged['last_validated'] = wp_date('Y-m-d H:i:s', null, wp_timezone());
			$merged['meta'] = is_array($merged['meta'] ?? null) ? $merged['meta'] : array();
			$store[$slug] = $merged;
			update_option(self::OPTION_STORE, $store, false);
			return $merged;
		}

		public static function uid(): string
		{
			$uid = (string) get_option(self::OPTION_UID, '');
			if ($uid !== '') {
				return $uid;
			}
			$uid = substr(md5(site_url() . '|' . wp_salt('auth') . '|vms_addons_uid'), 0, 32);
			update_option(self::OPTION_UID, $uid, false);
			return $uid;
		}

		public static function reset_uid(): string
		{
			delete_option(self::OPTION_UID);
			return self::uid();
		}

		public static function masked_key(string $key): string
		{
			$key = trim($key);
			if ($key === '') {
				return '';
			}
			$tail = substr($key, -4);
			return str_repeat('*', max(0, strlen($key) - 4)) . $tail;
		}

		public static function activate(array $entry, array $manifest_entry)
		{
			$product_id = absint($manifest_entry['freemius']['product_id'] ?? 0);
			$license_key = (string) ($entry['license_key'] ?? '');
			if ($product_id < 1 || $license_key === '') {
				return new WP_Error('missing_license_data', __('Product ID or license key is missing.', 'vms'));
			}

			$url = sprintf('https://api.freemius.com/v1/products/%d/licenses/activate.json', $product_id);
			$payload = array(
				'uid' => self::uid(),
				'license_key' => $license_key,
				'url' => site_url(),
				'title' => get_bloginfo('name'),
				'version' => self::plugin_version($manifest_entry['plugin_file'] ?? ''),
			);

			$response = wp_remote_post($url, array(
				'timeout' => 15,
				'headers' => array('Content-Type' => 'application/json'),
				'body' => wp_json_encode($payload),
			));

			return self::parse_freemius_response($response, 'activate');
		}

		public static function validate(array $entry, array $manifest_entry)
		{
			$product_id = absint($manifest_entry['freemius']['product_id'] ?? 0);
			$license_key = (string) ($entry['license_key'] ?? '');
			$install_id = absint($entry['install_id'] ?? 0);
			if ($product_id < 1 || $license_key === '' || $install_id < 1) {
				return new WP_Error('missing_license_data', __('Product ID, install ID, or license key is missing.', 'vms'));
			}

			$url = sprintf(
				'https://api.freemius.com/v1/products/%d/installs/%d/license.json?uid=%s&license_key=%s',
				$product_id,
				$install_id,
				rawurlencode(self::uid()),
				rawurlencode($license_key)
			);
			$response = wp_remote_get($url, array('timeout' => 15));
			return self::parse_freemius_response($response, 'validate');
		}

		public static function deactivate(array $entry, array $manifest_entry)
		{
			$product_id = absint($manifest_entry['freemius']['product_id'] ?? 0);
			$license_key = (string) ($entry['license_key'] ?? '');
			$install_id = absint($entry['install_id'] ?? 0);
			if ($product_id < 1 || $license_key === '' || $install_id < 1) {
				return new WP_Error('missing_license_data', __('Product ID, install ID, or license key is missing.', 'vms'));
			}

			$url = sprintf(
				'https://api.freemius.com/v1/products/%d/licenses/deactivate.json?uid=%s&install_id=%d&license_key=%s',
				$product_id,
				rawurlencode(self::uid()),
				$install_id,
				rawurlencode($license_key)
			);
			$response = wp_remote_post($url, array('timeout' => 15));
			return self::parse_freemius_response($response, 'deactivate');
		}

		private static function parse_freemius_response($response, string $op)
		{
			if (is_wp_error($response)) {
				return new WP_Error('freemius_unreachable', __('Could not reach licensing server.', 'vms'));
			}

			$code = (int) wp_remote_retrieve_response_code($response);
			$body = json_decode((string) wp_remote_retrieve_body($response), true);
			if ($code < 200 || $code >= 300) {
				$message = is_array($body) && !empty($body['error']['message']) ? (string) $body['error']['message'] : __('Licensing request failed.', 'vms');
				return new WP_Error('freemius_error', sanitize_text_field($message));
			}

			$status = 'active';
			$status_message = __('License is active.', 'vms');
			$install_id = absint($body['install_id'] ?? $body['install']['id'] ?? 0);

			if ($op === 'deactivate') {
				$status = 'missing';
				$status_message = __('License deactivated.', 'vms');
			}
			if ($op === 'validate') {
				$is_active = (bool) ($body['is_active'] ?? $body['license']['is_active'] ?? true);
				$expired = (bool) ($body['is_expired'] ?? $body['license']['is_expired'] ?? false);
				if ($expired) {
					$status = 'expired';
					$status_message = __('License is expired.', 'vms');
				} elseif (!$is_active) {
					$status = 'missing';
					$status_message = __('License is not active.', 'vms');
				}
			}

			return array(
				'status' => $status,
				'status_message' => $status_message,
				'install_id' => $install_id,
				'meta' => array('response' => is_array($body) ? $body : array()),
			);
		}

		private static function plugin_version(string $plugin_file): string
		{
			if ($plugin_file === '') {
				return VMS_VERSION;
			}
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			$plugins = get_plugins();
			if (!isset($plugins[$plugin_file])) {
				return VMS_VERSION;
			}
			return (string) ($plugins[$plugin_file]['Version'] ?? VMS_VERSION);
		}
	}
}
