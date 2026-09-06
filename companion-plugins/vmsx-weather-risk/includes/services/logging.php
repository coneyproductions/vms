<?php

defined('ABSPATH') || exit;

if (!class_exists('VMSX_Weather_Risk_Logging')) {
	class VMSX_Weather_Risk_Logging {
		private const MAX_ITEMS = 200;

		public static function write(string $message, array $context = array(), string $level = 'info'): void
		{
			$message = trim($message);
			if ($message === '') {
				return;
			}

			$items = get_option(VMSX_WR_OPTION_LOG, array());
			if (!is_array($items)) {
				$items = array();
			}

			array_unshift($items, array(
				'timestamp' => time(),
				'level' => sanitize_key($level),
				'message' => sanitize_text_field($message),
				'context' => self::sanitize_context($context),
			));

			if (count($items) > self::MAX_ITEMS) {
				$items = array_slice($items, 0, self::MAX_ITEMS);
			}

			update_option(VMSX_WR_OPTION_LOG, $items, false);
		}

		public static function recent(int $limit = 50, int $event_plan_id = 0): array
		{
			$items = get_option(VMSX_WR_OPTION_LOG, array());
			if (!is_array($items)) {
				return array();
			}

			$filtered = array();
			foreach ($items as $item) {
				if (!is_array($item)) {
					continue;
				}

				$item_event_id = (int) (($item['context']['event_plan_id'] ?? 0));
				if ($event_plan_id > 0 && $item_event_id !== $event_plan_id) {
					continue;
				}
				$filtered[] = $item;
				if (count($filtered) >= $limit) {
					break;
				}
			}

			return $filtered;
		}

		private static function sanitize_context(array $context): array
		{
			$out = array();
			foreach ($context as $key => $value) {
				$key = sanitize_key((string) $key);
				if ($key === '') {
					continue;
				}

				if (is_scalar($value) || $value === null) {
					$scalar = is_bool($value) ? ($value ? '1' : '0') : trim((string) $value);
					if (strpos($key, 'key') !== false || strpos($key, 'token') !== false || strpos($key, 'secret') !== false) {
						$scalar = VMSX_Weather_Risk_Helpers::mask_secret($scalar);
					}
					$out[$key] = sanitize_text_field($scalar);
					continue;
				}

				if (is_array($value)) {
					$out[$key] = self::sanitize_context($value);
					continue;
				}

				$out[$key] = sanitize_text_field(gettype($value));
			}

			return $out;
		}
	}
}
