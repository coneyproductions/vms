<?php

defined('ABSPATH') || exit;

if (!class_exists('VMS_Tours_Storage')) {
	class VMS_Tours_Storage
	{
		const USER_META_PREFS = 'vms_tours_prefs';
		const USER_META_STATE = 'vms_tours_state';

		const OPTION_SETTINGS = 'vms_tours_settings';
		const OPTION_SEEN_SCREENS = 'vms_tours_seen_screens';

		/**
		 * @return array<string,mixed>
		 */
		public function get_site_settings(): array
		{
			$raw = get_option(self::OPTION_SETTINGS, array());
			if (!is_array($raw)) {
				$raw = array();
			}

			$settings = wp_parse_args($raw, $this->default_site_settings());
			return $this->sanitize_site_settings($settings);
		}

		/**
		 * @param array<string,mixed> $settings
		 * @return array<string,mixed>
		 */
		public function save_site_settings(array $settings): array
		{
			$sanitized = $this->sanitize_site_settings($settings);
			update_option(self::OPTION_SETTINGS, $sanitized, false);
			return $sanitized;
		}

		/**
		 * @return array<string,mixed>
		 */
		public function get_user_prefs(int $user_id): array
		{
			$settings = $this->get_site_settings();
			$defaults = $this->default_user_prefs($settings);

			$raw = get_user_meta($user_id, self::USER_META_PREFS, true);
			if (!is_array($raw)) {
				$raw = array();
			}

			$prefs = wp_parse_args($raw, $defaults);
			return $this->sanitize_user_prefs($prefs, $settings);
		}

		/**
		 * @param array<string,mixed> $prefs
		 * @return array<string,mixed>
		 */
		public function save_user_prefs(int $user_id, array $prefs): array
		{
			$settings = $this->get_site_settings();
			$defaults = $this->default_user_prefs($settings);
			$merged = wp_parse_args($prefs, $defaults);
			$sanitized = $this->sanitize_user_prefs($merged, $settings);
			update_user_meta($user_id, self::USER_META_PREFS, $sanitized);
			return $sanitized;
		}

		/**
		 * @return array<string,mixed>
		 */
		public function get_user_state(int $user_id): array
		{
			$raw = get_user_meta($user_id, self::USER_META_STATE, true);
			if (is_string($raw) && $raw !== '') {
				$decoded = json_decode($raw, true);
				if (is_array($decoded)) {
					$raw = $decoded;
				}
			}
			if (!is_array($raw)) {
				$raw = array();
			}

			$state = array();
			foreach ($raw as $tour_id => $row) {
				$id = $this->sanitize_id((string) $tour_id);
				if ($id === '' || !is_array($row)) {
					continue;
				}

				$state[$id] = array(
					'completed_version' => isset($row['completed_version']) ? (string) $row['completed_version'] : '',
					'completed_at' => isset($row['completed_at']) ? sanitize_text_field((string) $row['completed_at']) : '',
					'last_seen_at' => isset($row['last_seen_at']) ? sanitize_text_field((string) $row['last_seen_at']) : '',
				);
			}

			return $state;
		}

		/**
		 * @param array<string,mixed> $state
		 */
		public function save_user_state(int $user_id, array $state): void
		{
			update_user_meta($user_id, self::USER_META_STATE, $state);
		}

		public function mark_tour_seen(int $user_id, string $tour_id): void
		{
			$tour_id = $this->sanitize_id($tour_id);
			if ($tour_id === '') {
				return;
			}

			$state = $this->get_user_state($user_id);
			$row = isset($state[$tour_id]) && is_array($state[$tour_id]) ? $state[$tour_id] : array();
			$row['completed_version'] = isset($row['completed_version']) ? (string) $row['completed_version'] : '';
			$row['completed_at'] = isset($row['completed_at']) ? (string) $row['completed_at'] : '';
			$row['last_seen_at'] = $this->now();
			$state[$tour_id] = $row;
			$this->save_user_state($user_id, $state);
		}

		public function mark_tour_complete(int $user_id, string $tour_id, string $version): void
		{
			$tour_id = $this->sanitize_id($tour_id);
			$version = trim((string) $version);
			if ($tour_id === '' || $version === '') {
				return;
			}

			$state = $this->get_user_state($user_id);
			$timestamp = $this->now();
			$state[$tour_id] = array(
				'completed_version' => $version,
				'completed_at' => $timestamp,
				'last_seen_at' => $timestamp,
			);
			$this->save_user_state($user_id, $state);
		}

		public function reset_user_state(int $user_id): void
		{
			$settings = $this->get_site_settings();
			$prefs = $this->get_user_prefs($user_id);
			$prefs['dismissed_tours'] = array();

			update_user_meta($user_id, self::USER_META_PREFS, $this->sanitize_user_prefs($prefs, $settings));
			delete_user_meta($user_id, self::USER_META_STATE);
		}

		public function remember_seen_screen(string $screen_key): void
		{
			$screen_key = $this->sanitize_screen_key($screen_key);
			if ($screen_key === '' || $screen_key === 'admin:unknown' || $screen_key === 'frontend:unknown') {
				return;
			}

			$seen = get_option(self::OPTION_SEEN_SCREENS, array());
			if (!is_array($seen)) {
				$seen = array();
			}

			$seen[$screen_key] = $this->now();
			update_option(self::OPTION_SEEN_SCREENS, $seen, false);
		}

		/**
		 * @return array<string,string>
		 */
		public function get_seen_screens(): array
		{
			$raw = get_option(self::OPTION_SEEN_SCREENS, array());
			if (!is_array($raw)) {
				return array();
			}

			$out = array();
			foreach ($raw as $screen => $timestamp) {
				$key = $this->sanitize_screen_key((string) $screen);
				if ($key === '') {
					continue;
				}
				$out[$key] = sanitize_text_field((string) $timestamp);
			}

			return $out;
		}

		/**
		 * @return array<string,mixed>
		 */
		public function default_site_settings(): array
		{
			return array(
				'global_enabled' => true,
				'default_level' => 'beginner',
				'auto_run_default' => true,
				'auto_run_delay_ms' => 400,
				'max_auto_run_per_page_load' => 1,
				'help_button_enabled' => true,
				'debug_log_enabled' => false,
			);
		}

		/**
		 * @param array<string,mixed> $settings
		 * @return array<string,mixed>
		 */
		private function sanitize_site_settings(array $settings): array
		{
			$defaults = $this->default_site_settings();
			$settings = wp_parse_args($settings, $defaults);

			$level = $this->sanitize_level((string) ($settings['default_level'] ?? 'beginner'));

			$delay = isset($settings['auto_run_delay_ms']) ? (int) $settings['auto_run_delay_ms'] : 400;
			if ($delay < 0) {
				$delay = 0;
			}

			$max_auto = isset($settings['max_auto_run_per_page_load']) ? (int) $settings['max_auto_run_per_page_load'] : 1;
			if ($max_auto < 1) {
				$max_auto = 1;
			}
			if ($max_auto > 5) {
				$max_auto = 5;
			}

			return array(
				'global_enabled' => !empty($settings['global_enabled']),
				'default_level' => $level,
				'auto_run_default' => !empty($settings['auto_run_default']),
				'auto_run_delay_ms' => $delay,
				'max_auto_run_per_page_load' => $max_auto,
				'help_button_enabled' => !empty($settings['help_button_enabled']),
				'debug_log_enabled' => !empty($settings['debug_log_enabled']),
			);
		}

		/**
		 * @param array<string,mixed> $settings
		 * @return array<string,mixed>
		 */
		private function default_user_prefs(array $settings): array
		{
			return array(
				'auto_run_enabled' => !empty($settings['auto_run_default']),
				'level' => $this->sanitize_level((string) ($settings['default_level'] ?? 'beginner')),
				'dismissed_tours' => array(),
			);
		}

		/**
		 * @param array<string,mixed> $prefs
		 * @param array<string,mixed> $settings
		 * @return array<string,mixed>
		 */
		private function sanitize_user_prefs(array $prefs, array $settings): array
		{
			$defaults = $this->default_user_prefs($settings);
			$prefs = wp_parse_args($prefs, $defaults);

			$dismissed = array();
			$raw_dismissed = isset($prefs['dismissed_tours']) && is_array($prefs['dismissed_tours']) ? $prefs['dismissed_tours'] : array();
			foreach ($raw_dismissed as $tour_id => $flag) {
				$id = $this->sanitize_id((string) $tour_id);
				if ($id === '') {
					continue;
				}
				$dismissed[$id] = !empty($flag);
			}

			return array(
				'auto_run_enabled' => !empty($prefs['auto_run_enabled']),
				'level' => $this->sanitize_level((string) ($prefs['level'] ?? $defaults['level'])),
				'dismissed_tours' => $dismissed,
			);
		}

		private function sanitize_level(string $level): string
		{
			$level = sanitize_key($level);
			if (!in_array($level, array('beginner', 'standard', 'advanced'), true)) {
				$level = 'beginner';
			}
			return $level;
		}

		private function sanitize_id(string $id): string
		{
			$id = strtolower(trim($id));
			if ($id === '') {
				return '';
			}

			$sanitized = preg_replace('/[^a-z0-9._\-]/', '', $id);
			return is_string($sanitized) ? $sanitized : '';
		}

		private function sanitize_screen_key(string $screen_key): string
		{
			$screen_key = strtolower(trim($screen_key));
			if ($screen_key === '') {
				return '';
			}

			$sanitized = preg_replace('/[^a-z0-9:_\-]/', '', $screen_key);
			return is_string($sanitized) ? $sanitized : '';
		}

		private function now(): string
		{
			return wp_date('Y-m-d H:i:s', time(), wp_timezone());
		}
	}
}
