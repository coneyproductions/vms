<?php

defined('ABSPATH') || exit;

if (!class_exists('VMS_Tours_Screen')) {
	class VMS_Tours_Screen
	{
		/**
		 * @return string
		 */
		public function resolve_screen_key(): string
		{
			if (is_admin()) {
				return $this->resolve_admin_screen_key();
			}

			$key = (string) apply_filters('vms_tours_frontend_screen_key', 'frontend:unknown');
			$key = $this->sanitize_screen_key($key);
			if ($key === '' || $key === 'frontend') {
				return 'frontend:unknown';
			}

			return $key;
		}

		public function resolve_admin_screen_key(): string
		{
			$page = vms_request_read_text_field($_GET, 'page'); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Passive tours screen resolution only selects read-only admin context and remains nonce-free.
			if ($page !== '') {
				if ($page === 'vms') {
					$page = 'vms-dashboard';
				}

				return 'admin:' . $this->sanitize_admin_token($page);
			}

			$screen_id = '';
			if (function_exists('get_current_screen')) {
				$screen = get_current_screen();
				if (is_object($screen) && isset($screen->id)) {
					$screen_id = (string) $screen->id;
				}
			}

			$screen_id = $this->sanitize_admin_token($screen_id);
			if ($screen_id === '') {
				$screen_id = 'unknown';
			}

			return 'admin:' . $screen_id;
		}

		public function is_vms_admin_screen(): bool
		{
			if (!is_admin()) {
				return false;
			}

			$page = vms_request_read_key($_GET, 'page'); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Passive tours screen detection only selects read-only admin context and remains nonce-free.
			if ($page === 'vms' || $page === 'vms-dashboard' || strpos($page, 'vms-') === 0) {
				return true;
			}

			if (function_exists('get_current_screen')) {
				$screen = get_current_screen();
				if (is_object($screen)) {
					$post_type = isset($screen->post_type) ? sanitize_key((string) $screen->post_type) : '';
					if ($post_type !== '' && strpos($post_type, 'vms_') === 0) {
						return true;
					}

					$screen_id = isset($screen->id) ? (string) $screen->id : '';
					if ($screen_id !== '' && strpos($screen_id, 'vms') !== false) {
						return true;
					}
				}
			}

			return false;
		}

		public function sanitize_screen_key(string $key): string
		{
			$key = strtolower(trim($key));
			if ($key === '') {
				return '';
			}

			$sanitized = preg_replace('/[^a-z0-9:_\-]/', '', $key);
			return is_string($sanitized) ? $sanitized : '';
		}

		private function sanitize_admin_token(string $token): string
		{
			$token = strtolower(trim($token));
			if ($token === '') {
				return '';
			}

			$sanitized = preg_replace('/[^a-z0-9_\-]/', '', $token);
			return is_string($sanitized) ? $sanitized : '';
		}
	}
}
