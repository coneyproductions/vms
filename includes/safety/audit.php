<?php

defined('ABSPATH') || exit;

if (!defined('VMS_SAFETY_AUDIT_OPTION')) {
	define('VMS_SAFETY_AUDIT_OPTION', 'vms_safety_audit_log_v1');
}

if (!function_exists('vms_safety_audit_log')) {
	/**
	 * @param array<string,mixed> $context
	 */
	function vms_safety_audit_log(string $event, array $context = array()): void
	{
		$rows = get_option(VMS_SAFETY_AUDIT_OPTION, array());
		$rows = is_array($rows) ? $rows : array();

		$entry = array(
			'event' => sanitize_key($event),
			'time' => current_time('mysql'),
			'user_id' => get_current_user_id(),
			'context' => array(),
		);

		foreach ($context as $key => $value) {
			$k = sanitize_key((string) $key);
			if ($k === '') {
				continue;
			}
			if (is_scalar($value) || $value === null) {
				$entry['context'][$k] = $value;
			} else {
				$entry['context'][$k] = wp_json_encode($value);
			}
		}

		$rows[] = $entry;
		if (count($rows) > 500) {
			$rows = array_slice($rows, -500);
		}

		update_option(VMS_SAFETY_AUDIT_OPTION, $rows, false);
	}
}

if (!function_exists('vms_safety_recent_activity')) {
	/**
	 * @return array<int,array<string,mixed>>
	 */
	function vms_safety_recent_activity(int $limit = 20): array
	{
		$rows = get_option(VMS_SAFETY_AUDIT_OPTION, array());
		$rows = is_array($rows) ? $rows : array();
		if ($limit <= 0) {
			return array();
		}
		return array_reverse(array_slice($rows, -$limit));
	}
}
