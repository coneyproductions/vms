<?php

defined('ABSPATH') || exit;

if (!class_exists('VMSX_Weather_Risk_Cache')) {
	class VMSX_Weather_Risk_Cache {
		public static function provider_key(string $provider_slug, array $location, array $window): string
		{
			$hash = md5(wp_json_encode(array(
				'provider' => sanitize_key($provider_slug),
				'lat' => isset($location['latitude']) ? (float) $location['latitude'] : null,
				'lon' => isset($location['longitude']) ? (float) $location['longitude'] : null,
				'start' => (int) ($window['window_start_utc'] ?? 0),
				'end' => (int) ($window['window_end_utc'] ?? 0),
			)));

			return VMSX_WR_TRANSIENT_PROVIDER_PREFIX . $hash;
		}

		public static function get_provider(string $provider_slug, array $location, array $window): ?array
		{
			$key = self::provider_key($provider_slug, $location, $window);
			$value = get_transient($key);
			return is_array($value) ? $value : null;
		}

		public static function set_provider(string $provider_slug, array $location, array $window, array $payload, int $ttl_minutes): void
		{
			$key = self::provider_key($provider_slug, $location, $window);
			set_transient($key, $payload, max(5, $ttl_minutes) * MINUTE_IN_SECONDS);
		}

		public static function get_snapshot(int $event_plan_id): ?array
		{
			$raw = get_post_meta($event_plan_id, VMSX_WR_META_SNAPSHOT, true);
			return is_array($raw) ? $raw : null;
		}

		public static function set_snapshot(int $event_plan_id, array $snapshot): void
		{
			update_post_meta($event_plan_id, VMSX_WR_META_SNAPSHOT, $snapshot);
		}

		public static function delete_snapshot(int $event_plan_id): void
		{
			delete_post_meta($event_plan_id, VMSX_WR_META_SNAPSHOT);
		}
	}
}
