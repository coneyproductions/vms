<?php
defined('ABSPATH') || exit;

if (!function_exists('bvmgr_add_dispatch_db_option_key')) {
	function bvmgr_add_dispatch_db_option_key(): string
	{
		return 'vms_add_dispatch_db_schema_version';
	}
}

if (!function_exists('bvmgr_add_dispatch_db_target')) {
	function bvmgr_add_dispatch_db_target(): string
	{
		return '1.0.0';
	}
}

if (!function_exists('bvmgr_add_dispatch_table_name')) {
	function bvmgr_add_dispatch_table_name(string $kind): string
	{
		global $wpdb;
		$map = array(
			'requests' => 'vms_add_dispatch_requests',
			'responses' => 'vms_add_dispatch_responses',
			'logs' => 'vms_add_dispatch_logs',
		);

		$suffix = isset($map[$kind]) ? (string) $map[$kind] : '';
		if ($suffix === '') {
			return '';
		}

		return $wpdb->prefix . $suffix;
	}
}

if (!function_exists('bvmgr_add_dispatch_maybe_upgrade_schema')) {
	function bvmgr_add_dispatch_maybe_upgrade_schema(): void
	{
		$current = (string) get_option(bvmgr_add_dispatch_db_option_key(), '');
		$target = bvmgr_add_dispatch_db_target();
		if ($current === $target) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$requests = bvmgr_add_dispatch_table_name('requests');
		$responses = bvmgr_add_dispatch_table_name('responses');
		$logs = bvmgr_add_dispatch_table_name('logs');

		$sql_requests = "CREATE TABLE {$requests} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			event_plan_id BIGINT(20) UNSIGNED NOT NULL,
			venue_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			event_date DATE NOT NULL,
			request_mode VARCHAR(20) NOT NULL DEFAULT 'single_event',
			target_mode VARCHAR(20) NOT NULL DEFAULT 'secondary',
			vendor_type VARCHAR(120) NULL,
			message TEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			include_unknown TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
			include_tentative TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
			include_previously_contacted TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
			recipient_count INT(10) UNSIGNED NOT NULL DEFAULT 0,
			context_json LONGTEXT NULL,
			created_by BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NULL,
			closed_at DATETIME NULL,
			PRIMARY KEY (id),
			KEY plan_status (event_plan_id, status),
			KEY event_date (event_date),
			KEY created_at (created_at)
		) {$charset_collate};";

		$sql_responses = "CREATE TABLE {$responses} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			request_id BIGINT(20) UNSIGNED NOT NULL,
			vendor_id BIGINT(20) UNSIGNED NOT NULL,
			event_plan_id BIGINT(20) UNSIGNED NOT NULL,
			venue_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			event_date DATE NOT NULL,
			vendor_email VARCHAR(190) NOT NULL,
			response_status VARCHAR(20) NOT NULL DEFAULT 'requested',
			responded_at DATETIME NULL,
			response_source VARCHAR(20) NULL,
			note TEXT NULL,
			availability_written TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
			availability_written_at DATETIME NULL,
			availability_before VARCHAR(20) NULL,
			availability_after VARCHAR(20) NULL,
			token_public_key VARCHAR(64) NOT NULL,
			token_hash CHAR(64) NOT NULL,
			token_expires_at DATETIME NULL,
			last_sent_at DATETIME NULL,
			send_count SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0,
			assigned_at DATETIME NULL,
			assigned_by BIGINT(20) UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NULL,
			PRIMARY KEY (id),
			UNIQUE KEY token_public_key (token_public_key),
			UNIQUE KEY request_vendor (request_id, vendor_id),
			KEY request_status (request_id, response_status),
			KEY plan_vendor (event_plan_id, vendor_id),
			KEY expires_at (token_expires_at)
		) {$charset_collate};";

		$sql_logs = "CREATE TABLE {$logs} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			request_id BIGINT(20) UNSIGNED NULL,
			response_id BIGINT(20) UNSIGNED NULL,
			vendor_id BIGINT(20) UNSIGNED NULL,
			event_plan_id BIGINT(20) UNSIGNED NULL,
			event_date DATE NULL,
			action VARCHAR(60) NOT NULL,
			previous_value VARCHAR(60) NULL,
			new_value VARCHAR(60) NULL,
			source VARCHAR(40) NOT NULL,
			actor_user_id BIGINT(20) UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			details_json LONGTEXT NULL,
			PRIMARY KEY (id),
			KEY request_action (request_id, action),
			KEY vendor_date (vendor_id, event_date),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta($sql_requests);
		dbDelta($sql_responses);
		dbDelta($sql_logs);

		update_option(bvmgr_add_dispatch_db_option_key(), $target, false);
	}
}
