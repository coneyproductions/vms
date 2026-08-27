<?php
defined('ABSPATH') || exit;

if (!function_exists('bvmgr_admission_now_mysql')) {
	function bvmgr_admission_now_mysql(): string
	{
		return (string) wp_date('Y-m-d H:i:s', time(), wp_timezone());
	}
}

if (!function_exists('bvmgr_admission_settings')) {
	function bvmgr_admission_settings(): array
	{
		$opts = (array) get_option('vms_settings', array());
		$settings = array(
			'max_party_size' => isset($opts['vms_admission_max_party_size']) ? (int) $opts['vms_admission_max_party_size'] : 6,
			'allow_uncheckin' => !empty($opts['vms_admission_allow_uncheckin']) ? 1 : 0,
			'allow_uncheckin_for_door' => !empty($opts['vms_admission_allow_uncheckin_for_door']) ? 1 : 0,
			'door_show_phone' => !empty($opts['vms_admission_door_show_phone']) ? 1 : 0,
		);

		if ($settings['max_party_size'] < 1) {
			$settings['max_party_size'] = 6;
		}
		if ($settings['max_party_size'] > 100) {
			$settings['max_party_size'] = 100;
		}

		return (array) apply_filters('vms_admission_settings', $settings);
	}
}

if (!function_exists('bvmgr_admission_audit_log')) {
	function bvmgr_admission_audit_log(int $event_plan_id, ?int $entry_id, string $action, int $actor_user_id, string $actor_context, array $details = array()): bool
	{
		global $wpdb;

		$table = bvmgr_admission_table_audit();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Admissions audit logging writes directly to the plugin-owned audit table because no core API exposes this repository.
		$result = $wpdb->insert(
			$table,
			array(
				'event_plan_id' => $event_plan_id,
				'entry_id' => $entry_id ?: null,
				'action' => sanitize_key($action),
				'actor_user_id' => $actor_user_id,
				'actor_context' => sanitize_key($actor_context),
				'created_at' => bvmgr_admission_now_mysql(),
				'details' => !empty($details) ? wp_json_encode($details) : null,
			),
			array('%d', '%d', '%s', '%d', '%s', '%s', '%s')
		);

		return $result !== false;
	}
}
