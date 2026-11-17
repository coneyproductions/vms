<?php
defined('ABSPATH') || exit;

if (!function_exists('bvmgr_social_sanitize_details')) {
	/**
	 * @param mixed $value
	 * @return mixed
	 */
	function bvmgr_social_sanitize_details($value)
	{
		$secret_markers = array('token', 'secret', 'password', 'authorization', 'cookie', 'client_secret');

		if (is_array($value)) {
			$out = array();
			foreach ($value as $k => $v) {
				$key = strtolower((string) $k);
				$is_secret = false;
				foreach ($secret_markers as $needle) {
					if (strpos($key, $needle) !== false) {
						$is_secret = true;
						break;
					}
				}
				$out[$k] = $is_secret ? '[redacted]' : bvmgr_social_sanitize_details($v);
			}
			return $out;
		}

		if (is_object($value)) {
			return bvmgr_social_sanitize_details((array) $value);
		}

		if (is_string($value)) {
			$trimmed = trim($value);
			if (strlen($trimmed) > 1200) {
				$trimmed = substr($trimmed, 0, 1200) . '…';
			}
			return $trimmed;
		}

		if (is_bool($value) || is_numeric($value) || $value === null) {
			return $value;
		}

		return (string) $value;
	}
}

if (!function_exists('bvmgr_social_audit_log')) {
	/**
	 * @param array<string,mixed> $details
	 */
	function bvmgr_social_audit_log(string $action, array $details = array(), int $queue_id = 0, string $platform = '', ?int $actor_user_id = null): void
	{
		global $wpdb;
		$table = bvmgr_social_table_audit();

		$action = sanitize_key($action);
		if ($action === '') {
			$action = 'unknown';
		}

		$actor = $actor_user_id;
		if ($actor === null) {
			$actor = get_current_user_id();
		}
		$actor = max(0, (int) $actor);

		$sanitized_details = bvmgr_social_sanitize_details($details);
		$details_json = wp_json_encode($sanitized_details);
		if (!is_string($details_json)) {
			$details_json = '{}';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Social audit writes append an authoritative row to the plugin-owned audit table; no core persistence API owns this repository.
		$wpdb->insert(
			$table,
			array(
				'actor_user_id' => $actor,
				'action' => $action,
				'queue_id' => $queue_id > 0 ? $queue_id : null,
				'platform' => $platform !== '' ? sanitize_key($platform) : null,
				'details_json' => $details_json,
				'created_at' => bvmgr_social_now_mysql_utc(),
			),
			array('%d', '%s', '%d', '%s', '%s', '%s')
		);
	}
}

if (!function_exists('bvmgr_social_audit_recent')) {
	/**
	 * @return array<int,array<string,mixed>>
	 */
	function bvmgr_social_audit_recent(int $limit = 100, string $search = ''): array
	{
		global $wpdb;
		$table = bvmgr_social_table_audit();
		$limit = max(1, min(500, $limit));
		$search = trim($search);

		if ($search === '') {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Recent audit history must read request-fresh plugin-owned rows after queue and provider mutations.
			$rows = $wpdb->get_results(
				$wpdb->prepare('SELECT * FROM %i ORDER BY id DESC LIMIT %d', $table, $limit),
				ARRAY_A
			);
		} else {
			$like = '%' . $wpdb->esc_like($search) . '%';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Filtered audit history must read request-fresh plugin-owned rows while preserving the existing three-field search.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE action LIKE %s OR platform LIKE %s OR details_json LIKE %s ORDER BY id DESC LIMIT %d',
					$table,
					$like,
					$like,
					$like,
					$limit
				),
				ARRAY_A
			);
		}

		return is_array($rows) ? $rows : array();
	}
}
