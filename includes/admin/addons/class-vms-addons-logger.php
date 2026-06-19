<?php

defined('ABSPATH') || exit;

if (!class_exists('VMS_Addons_Logger')) {
	class VMS_Addons_Logger {
		public const OPTION_LOG = 'vms_addons_log';
		private const MAX_ENTRIES = 200;

		public static function log(string $level, string $action, string $message, string $slug = '', array $context = array()): void
		{
			$entries = (array) get_option(self::OPTION_LOG, array());
			$entries[] = array(
				'timestamp' => wp_date('Y-m-d H:i:s', null, wp_timezone()),
				'level' => sanitize_key($level),
				'action' => sanitize_key($action),
				'slug' => sanitize_key($slug),
				'message' => sanitize_text_field($message),
				'context' => self::redact_context($context),
			);

			if (count($entries) > self::MAX_ENTRIES) {
				$entries = array_slice($entries, -self::MAX_ENTRIES);
			}

			update_option(self::OPTION_LOG, $entries, false);
		}

		public static function recent(int $limit = 50, string $slug = ''): array
		{
			$entries = array_reverse((array) get_option(self::OPTION_LOG, array()));
			if ($slug !== '') {
				$slug = sanitize_key($slug);
				$entries = array_values(array_filter($entries, static function ($row) use ($slug) {
					return isset($row['slug']) && $row['slug'] === $slug;
				}));
			}
			return array_slice($entries, 0, max(1, $limit));
		}

		private static function redact_context(array $context): array
		{
			$json = wp_json_encode($context);
			if (!is_string($json) || $json === '') {
				return array();
			}

			$decoded = json_decode($json, true);
			if (!is_array($decoded)) {
				return array();
			}

			$walk = static function (&$item, $key) {
				$k = strtolower((string) $key);
				if (in_array($k, array('license_key', 'token', 'access_token', 'password'), true)) {
					$item = '[REDACTED]';
				}
			};
			array_walk_recursive($decoded, $walk);
			return $decoded;
		}
	}
}
