<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_social_now_mysql_utc')) {
	function vms_social_now_mysql_utc(): string
	{
		return current_time('mysql', true);
	}
}

if (!function_exists('vms_social_table')) {
	function vms_social_table(string $suffix): string
	{
		global $wpdb;
		return $wpdb->prefix . $suffix;
	}
}

if (!function_exists('vms_social_table_accounts')) {
	function vms_social_table_accounts(): string
	{
		$suffix = defined('VMS_DB_TABLE_SOCIAL_ACCOUNTS_SUFFIX') ? (string) VMS_DB_TABLE_SOCIAL_ACCOUNTS_SUFFIX : 'vms_social_accounts';
		return vms_social_table($suffix);
	}
}

if (!function_exists('vms_social_table_venue_map')) {
	function vms_social_table_venue_map(): string
	{
		$suffix = defined('VMS_DB_TABLE_SOCIAL_VENUE_MAP_SUFFIX') ? (string) VMS_DB_TABLE_SOCIAL_VENUE_MAP_SUFFIX : 'vms_social_venue_map';
		return vms_social_table($suffix);
	}
}

if (!function_exists('vms_social_table_templates')) {
	function vms_social_table_templates(): string
	{
		$suffix = defined('VMS_DB_TABLE_SOCIAL_TEMPLATES_SUFFIX') ? (string) VMS_DB_TABLE_SOCIAL_TEMPLATES_SUFFIX : 'vms_social_templates';
		return vms_social_table($suffix);
	}
}

if (!function_exists('vms_social_table_queue')) {
	function vms_social_table_queue(): string
	{
		$suffix = defined('VMS_DB_TABLE_SOCIAL_QUEUE_SUFFIX') ? (string) VMS_DB_TABLE_SOCIAL_QUEUE_SUFFIX : 'vms_social_queue';
		return vms_social_table($suffix);
	}
}

if (!function_exists('vms_social_table_audit')) {
	function vms_social_table_audit(): string
	{
		$suffix = defined('VMS_DB_TABLE_SOCIAL_AUDIT_SUFFIX') ? (string) VMS_DB_TABLE_SOCIAL_AUDIT_SUFFIX : 'vms_social_audit';
		return vms_social_table($suffix);
	}
}

if (!function_exists('vms_social_default_settings')) {
	function vms_social_default_settings(): array
	{
		return array(
			'enabled' => 0,
			'kill_switch' => 1,
			'utm_enabled' => 1,
			'max_attempts' => defined('VMS_SOCIAL_MAX_ATTEMPTS_DEFAULT') ? (int) VMS_SOCIAL_MAX_ATTEMPTS_DEFAULT : 5,
		);
	}
}

if (!function_exists('vms_social_get_settings')) {
	function vms_social_get_settings(): array
	{
		$key = defined('VMS_OPT_SOCIAL_SETTINGS_V1') ? (string) VMS_OPT_SOCIAL_SETTINGS_V1 : 'vms_social_settings_v1';
		$raw = get_option($key, array());
		$raw = is_array($raw) ? $raw : array();

		$settings = wp_parse_args($raw, vms_social_default_settings());
		$settings['enabled'] = empty($settings['enabled']) ? 0 : 1;
		$settings['kill_switch'] = empty($settings['kill_switch']) ? 0 : 1;
		$settings['utm_enabled'] = empty($settings['utm_enabled']) ? 0 : 1;
		$settings['max_attempts'] = max(1, min(10, (int) ($settings['max_attempts'] ?? 5)));

		return $settings;
	}
}

if (!function_exists('vms_social_update_settings')) {
	function vms_social_update_settings(array $incoming): array
	{
		$settings = vms_social_get_settings();
		$next = array(
			'enabled' => empty($incoming['enabled']) ? 0 : 1,
			'kill_switch' => empty($incoming['kill_switch']) ? 0 : 1,
			'utm_enabled' => empty($incoming['utm_enabled']) ? 0 : 1,
			'max_attempts' => max(1, min(10, (int) ($incoming['max_attempts'] ?? $settings['max_attempts']))),
		);
		$key = defined('VMS_OPT_SOCIAL_SETTINGS_V1') ? (string) VMS_OPT_SOCIAL_SETTINGS_V1 : 'vms_social_settings_v1';
		update_option($key, $next, false);
		return $next;
	}
}

if (!function_exists('vms_social_is_enabled')) {
	function vms_social_is_enabled(): bool
	{
		$settings = vms_social_get_settings();
		return !empty($settings['enabled']);
	}
}

if (!function_exists('vms_social_kill_switch_active')) {
	function vms_social_kill_switch_active(): bool
	{
		$settings = vms_social_get_settings();
		return !empty($settings['kill_switch']);
	}
}

if (!function_exists('vms_social_db_maybe_install')) {
	function vms_social_db_maybe_install(): void
	{
		$schema_key = defined('VMS_OPT_SOCIAL_DB_SCHEMA_VERSION') ? (string) VMS_OPT_SOCIAL_DB_SCHEMA_VERSION : 'vms_social_db_schema_version';
		$target_version = defined('VMS_SOCIAL_DB_SCHEMA_VERSION') ? (string) VMS_SOCIAL_DB_SCHEMA_VERSION : 'social_v1';
		$current_version = (string) get_option($schema_key, '');
		if ($current_version === $target_version) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$accounts = vms_social_table_accounts();
		$venue_map = vms_social_table_venue_map();
		$templates = vms_social_table_templates();
		$queue = vms_social_table_queue();
		$audit = vms_social_table_audit();

		$sql_accounts = "CREATE TABLE {$accounts} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			platform VARCHAR(32) NOT NULL,
			label VARCHAR(190) NOT NULL,
			auth_state VARCHAR(20) NOT NULL DEFAULT 'error',
			token_blob_enc LONGTEXT NULL,
			meta_json LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY platform (platform),
			KEY auth_state (auth_state)
		) {$charset_collate};";

		$sql_venue_map = "CREATE TABLE {$venue_map} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			venue_id BIGINT(20) UNSIGNED NOT NULL,
			platform VARCHAR(32) NOT NULL,
			account_id BIGINT(20) UNSIGNED NOT NULL,
			destination_id VARCHAR(190) NOT NULL,
			is_enabled TINYINT(1) NOT NULL DEFAULT 1,
			default_template_id BIGINT(20) UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY venue_platform_destination (venue_id, platform, destination_id),
			KEY venue_platform_enabled (venue_id, platform, is_enabled),
			KEY account_id (account_id)
		) {$charset_collate};";

		$sql_templates = "CREATE TABLE {$templates} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			platform VARCHAR(32) NOT NULL,
			name VARCHAR(190) NOT NULL,
			body LONGTEXT NOT NULL,
			settings_json LONGTEXT NULL,
			is_default TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY platform (platform),
			KEY is_default (is_default)
		) {$charset_collate};";

		$sql_queue = "CREATE TABLE {$queue} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			event_plan_id BIGINT(20) UNSIGNED NULL,
			tec_event_id BIGINT(20) UNSIGNED NULL,
			venue_id BIGINT(20) UNSIGNED NOT NULL,
			platform VARCHAR(32) NOT NULL,
			destination_id VARCHAR(190) NOT NULL,
			template_id BIGINT(20) UNSIGNED NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'draft',
			scheduled_at_utc DATETIME NOT NULL,
			attempts INT(11) NOT NULL DEFAULT 0,
			next_attempt_at_utc DATETIME NULL,
			platform_post_id VARCHAR(190) NULL,
			payload_snapshot_json LONGTEXT NULL,
			last_error_code VARCHAR(100) NULL,
			last_error_message TEXT NULL,
			created_by BIGINT(20) UNSIGNED NULL,
			updated_by BIGINT(20) UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY status_schedule (status, scheduled_at_utc),
			KEY next_attempt (next_attempt_at_utc),
			KEY event_plan_id (event_plan_id),
			KEY venue_platform (venue_id, platform)
		) {$charset_collate};";

		$sql_audit = "CREATE TABLE {$audit} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			actor_user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			action VARCHAR(80) NOT NULL,
			queue_id BIGINT(20) UNSIGNED NULL,
			platform VARCHAR(32) NULL,
			details_json LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY action_created (action, created_at),
			KEY queue_id (queue_id),
			KEY platform (platform)
		) {$charset_collate};";

		dbDelta($sql_accounts);
		dbDelta($sql_venue_map);
		dbDelta($sql_templates);
		dbDelta($sql_queue);
		dbDelta($sql_audit);

		vms_social_seed_default_templates();
		update_option($schema_key, $target_version, false);

		$settings_key = defined('VMS_OPT_SOCIAL_SETTINGS_V1') ? (string) VMS_OPT_SOCIAL_SETTINGS_V1 : 'vms_social_settings_v1';
		if (get_option($settings_key, null) === null) {
			update_option($settings_key, vms_social_default_settings(), false);
		}
	}
}
add_action('plugins_loaded', 'vms_social_db_maybe_install', 8);

if (!function_exists('vms_social_seed_default_templates')) {
	function vms_social_seed_default_templates(): void
	{
		global $wpdb;
		$table = vms_social_table_templates();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Template seeding must inspect current plugin-owned rows once during installation before deciding whether defaults are needed.
		$count = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i', $table));
		if ($count > 0) {
			return;
		}

		$now = vms_social_now_mysql_utc();
		$seed = array(
			array(
				'platform' => 'facebook',
				'name' => 'Default Facebook',
				'body' => "{event_title}\n{event_date} {start_time}\n{venue_name}\nTickets: {ticket_url}",
				'settings_json' => wp_json_encode(array('hashtags' => '', 'character_limit' => 63206)),
			),
			array(
				'platform' => 'linkedin',
				'name' => 'Default LinkedIn',
				'body' => "{event_title} at {venue_name} on {event_date}.\nLearn more: {ticket_url}",
				'settings_json' => wp_json_encode(array('hashtags' => '', 'character_limit' => 3000)),
			),
			array(
				'platform' => 'x',
				'name' => 'Default X',
				'body' => "{event_title} | {event_date} | {venue_name} {venue_state}\n{ticket_url} {hashtags}",
				'settings_json' => wp_json_encode(array('hashtags' => '#live #events', 'character_limit' => 280)),
			),
			array(
				'platform' => 'mock',
				'name' => 'Mock Provider Default',
				'body' => "{event_title}\n{event_date} {start_time}-{end_time}\n{venue_name}\n{ticket_url}",
				'settings_json' => wp_json_encode(array('hashtags' => '', 'character_limit' => 10000)),
			),
		);

		foreach ($seed as $index => $row) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Default-template seeding appends the fixed ordered rows to the plugin-owned templates table; no core persistence API owns this repository.
			$wpdb->insert(
				$table,
				array(
					'platform' => (string) $row['platform'],
					'name' => (string) $row['name'],
					'body' => (string) $row['body'],
					'settings_json' => (string) $row['settings_json'],
					'is_default' => $index === 0 ? 1 : 0,
					'created_at' => $now,
					'updated_at' => $now,
				),
				array('%s', '%s', '%s', '%s', '%d', '%s', '%s')
			);
		}
	}
}
