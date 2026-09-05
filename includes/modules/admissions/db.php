<?php
defined('ABSPATH') || exit;

if (!function_exists('bvmgr_admission_db_option_key')) {
	function bvmgr_admission_db_option_key(): string
	{
		return 'vms_admission_db_version';
	}
}

if (!function_exists('bvmgr_admission_db_version_target')) {
	function bvmgr_admission_db_version_target(): string
	{
		return '1.4.0';
	}
}

if (!function_exists('bvmgr_admission_table_entries')) {
	function bvmgr_admission_table_entries(): string
	{
		global $wpdb;
		return $wpdb->prefix . 'vms_admission_entries';
	}
}

if (!function_exists('bvmgr_admission_table_audit')) {
	function bvmgr_admission_table_audit(): string
	{
		global $wpdb;
		return $wpdb->prefix . 'vms_admission_audit';
	}
}

if (!function_exists('bvmgr_admission_table_pass_sources')) {
	function bvmgr_admission_table_pass_sources(): string
	{
		global $wpdb;
		return $wpdb->prefix . 'vms_pass_sources';
	}
}

if (!function_exists('bvmgr_admission_table_pass_batches')) {
	function bvmgr_admission_table_pass_batches(): string
	{
		global $wpdb;
		return $wpdb->prefix . 'vms_pass_batches';
	}
}

if (!function_exists('bvmgr_admission_table_pass_tokens')) {
	function bvmgr_admission_table_pass_tokens(): string
	{
		global $wpdb;
		return $wpdb->prefix . 'vms_pass_tokens';
	}
}

if (!function_exists('bvmgr_admission_table_pass_claims')) {
	function bvmgr_admission_table_pass_claims(): string
	{
		global $wpdb;
		return $wpdb->prefix . 'vms_pass_claims';
	}
}

if (!function_exists('bvmgr_admission_maybe_upgrade_schema')) {
	function bvmgr_admission_maybe_upgrade_schema(): void
	{
		$current = (string) get_option(bvmgr_admission_db_option_key(), '');
		$target = bvmgr_admission_db_version_target();
		if ($current === $target) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$entries = bvmgr_admission_table_entries();
		$audit = bvmgr_admission_table_audit();
		$sources = bvmgr_admission_table_pass_sources();
		$batches = bvmgr_admission_table_pass_batches();
		$tokens = bvmgr_admission_table_pass_tokens();
		$claims = bvmgr_admission_table_pass_claims();

		$sql_entries = "CREATE TABLE {$entries} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			event_plan_id BIGINT(20) UNSIGNED NOT NULL,
			venue_id BIGINT(20) UNSIGNED NOT NULL,
			admission_kind VARCHAR(20) NOT NULL,
			source VARCHAR(20) NOT NULL,
			owner_vendor_id BIGINT(20) UNSIGNED NULL,
			guest_name VARCHAR(200) NOT NULL,
			guest_name_norm VARCHAR(220) NOT NULL,
			guest_email VARCHAR(190) NULL,
			guest_email_norm VARCHAR(190) NULL,
			party_size SMALLINT(5) UNSIGNED NOT NULL DEFAULT 1,
			checked_in_qty SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0,
			phone VARCHAR(40) NULL,
			phone_norm VARCHAR(40) NULL,
			notes TEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			pass_source_id BIGINT(20) UNSIGNED NULL,
			pass_batch_id BIGINT(20) UNSIGNED NULL,
			pass_token_id BIGINT(20) UNSIGNED NULL,
			pass_claim_id BIGINT(20) UNSIGNED NULL,
			discount_type VARCHAR(20) NULL,
			discount_value DECIMAL(10,2) NULL,
			claim_reference VARCHAR(120) NULL,
			admission_token VARCHAR(80) NOT NULL DEFAULT '',
			admission_token_hash CHAR(64) NULL,
			admission_emailed_at DATETIME NULL,
			claim_meta LONGTEXT NULL,
			created_by BIGINT(20) UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL,
			updated_by BIGINT(20) UNSIGNED NULL,
			updated_at DATETIME NULL,
			checked_in_by BIGINT(20) UNSIGNED NULL,
			checked_in_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY event_plan_status (event_plan_id, status),
			KEY event_plan_norm (event_plan_id, guest_name_norm),
			KEY event_plan_email (event_plan_id, guest_email_norm),
			KEY event_plan_phone (event_plan_id, phone_norm),
			KEY venue_plan (venue_id, event_plan_id),
			KEY pass_batch (pass_batch_id),
			KEY pass_token (pass_token_id),
			KEY claim_reference (claim_reference),
			KEY admission_token (admission_token),
			KEY admission_token_hash (admission_token_hash)
		) {$charset_collate};";

		$sql_audit = "CREATE TABLE {$audit} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			event_plan_id BIGINT(20) UNSIGNED NOT NULL,
			entry_id BIGINT(20) UNSIGNED NULL,
			action VARCHAR(40) NOT NULL,
			actor_user_id BIGINT(20) UNSIGNED NOT NULL,
			actor_context VARCHAR(40) NOT NULL,
			created_at DATETIME NOT NULL,
			details LONGTEXT NULL,
			PRIMARY KEY  (id),
			KEY plan_time (event_plan_id, created_at),
			KEY entry_time (entry_id, created_at)
		) {$charset_collate};";

		$sql_sources = "CREATE TABLE {$sources} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			source_name VARCHAR(190) NOT NULL,
			contact_name VARCHAR(190) NULL,
			phone VARCHAR(40) NULL,
			email VARCHAR(190) NULL,
			notes TEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			created_by BIGINT(20) UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL,
			updated_by BIGINT(20) UNSIGNED NULL,
			updated_at DATETIME NULL,
			PRIMARY KEY (id),
			KEY source_status (status, source_name)
		) {$charset_collate};";

		$sql_batches = "CREATE TABLE {$batches} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			source_id BIGINT(20) UNSIGNED NOT NULL,
			batch_name VARCHAR(190) NOT NULL,
			quantity INT(10) UNSIGNED NOT NULL,
			admissions_per_link SMALLINT(5) UNSIGNED NOT NULL DEFAULT 1,
			total_admission_cap INT(10) UNSIGNED NOT NULL DEFAULT 0,
			validity_type VARCHAR(20) NOT NULL,
			single_event_plan_id BIGINT(20) UNSIGNED NULL,
			start_date DATE NULL,
			end_date DATE NULL,
			season_label VARCHAR(120) NULL,
			venue_ids_json LONGTEXT NULL,
			value_type VARCHAR(20) NOT NULL,
			value_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
			applies_to VARCHAR(20) NOT NULL DEFAULT 'entry_only',
			expires_at DATETIME NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			checkin_open_mode VARCHAR(20) NOT NULL DEFAULT 'same_day',
			max_per_phone SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0,
			max_per_email SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0,
			notes TEXT NULL,
			generated_count INT(10) UNSIGNED NOT NULL DEFAULT 0,
			generated_at DATETIME NULL,
			created_by BIGINT(20) UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL,
			updated_by BIGINT(20) UNSIGNED NULL,
			updated_at DATETIME NULL,
			PRIMARY KEY (id),
			KEY source_status (source_id, status),
			KEY validity_dates (validity_type, start_date, end_date)
		) {$charset_collate};";

		$sql_tokens = "CREATE TABLE {$tokens} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			batch_id BIGINT(20) UNSIGNED NOT NULL,
			source_id BIGINT(20) UNSIGNED NOT NULL,
			token_public_key VARCHAR(64) NOT NULL,
			token_hash CHAR(64) NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'unclaimed',
			claimed_at DATETIME NULL,
			claim_id BIGINT(20) UNSIGNED NULL,
			reservation_entry_id BIGINT(20) UNSIGNED NULL,
			voided_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			created_by BIGINT(20) UNSIGNED NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY token_public_key (token_public_key),
			KEY token_status (status),
			KEY batch_status (batch_id, status)
		) {$charset_collate};";

		$sql_claims = "CREATE TABLE {$claims} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			token_id BIGINT(20) UNSIGNED NOT NULL,
			batch_id BIGINT(20) UNSIGNED NOT NULL,
			source_id BIGINT(20) UNSIGNED NOT NULL,
			event_plan_id BIGINT(20) UNSIGNED NOT NULL,
			reservation_entry_id BIGINT(20) UNSIGNED NULL,
			first_name VARCHAR(120) NOT NULL,
			last_name VARCHAR(120) NOT NULL,
			phone VARCHAR(40) NOT NULL,
			phone_norm VARCHAR(40) NOT NULL,
			email VARCHAR(190) NULL,
			opt_in TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
			ip VARCHAR(64) NULL,
			user_agent TEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY batch_phone (batch_id, phone_norm),
			KEY event_claims (event_plan_id, created_at)
		) {$charset_collate};";

		dbDelta($sql_entries);
		dbDelta($sql_audit);
		dbDelta($sql_sources);
		dbDelta($sql_batches);
		dbDelta($sql_tokens);
		dbDelta($sql_claims);

		// Backfill: checked-in rows from older schema should count as fully checked in.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema backfills write the plugin-owned admissions table directly because no core API exposes these custom rows during upgrades.
		$wpdb->query($wpdb->prepare("UPDATE %i SET checked_in_qty = party_size WHERE status = 'checked_in' AND (checked_in_qty IS NULL OR checked_in_qty = 0)", $entries));

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema backfills read request-fresh admissions rows with missing normalized identity fields from the plugin-owned table before repair writes.
		$rows = $wpdb->get_results($wpdb->prepare("SELECT id, guest_email, phone FROM %i WHERE (guest_email IS NOT NULL AND guest_email <> '' AND (guest_email_norm IS NULL OR guest_email_norm = '')) OR (phone IS NOT NULL AND phone <> '' AND (phone_norm IS NULL OR phone_norm = ''))", $entries), ARRAY_A);
		foreach ((array) $rows as $row) {
			$entry_id = isset($row['id']) ? (int) $row['id'] : 0;
			if ($entry_id <= 0) {
				continue;
			}
			$email_norm = bvmgr_admission_normalize_email((string) ($row['guest_email'] ?? ''));
			$phone_norm = bvmgr_admission_normalize_phone((string) ($row['phone'] ?? ''));
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema backfills write normalized identity fields directly to the plugin-owned admissions table during upgrades.
			$wpdb->update(
				$entries,
				array(
					'guest_email_norm' => $email_norm !== '' ? $email_norm : null,
					'phone_norm' => $phone_norm !== '' ? $phone_norm : null,
				),
				array('id' => $entry_id),
				array('%s', '%s'),
				array('%d')
			);
		}

		// Backfill native admission scan tokens for existing VMS admissions.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema backfills read a bounded set of admissions rows that still need scan tokens from the plugin-owned table before calling the token repair helper.
		$token_rows = $wpdb->get_results($wpdb->prepare("SELECT id FROM %i WHERE admission_token = '' OR admission_token IS NULL LIMIT 5000", $entries), ARRAY_A);
		foreach ((array) $token_rows as $token_row) {
			$entry_id = isset($token_row['id']) ? (int) $token_row['id'] : 0;
			if ($entry_id > 0 && function_exists('bvmgr_admission_ensure_entry_token')) {
				bvmgr_admission_ensure_entry_token($entry_id);
			}
		}

		update_option(bvmgr_admission_db_option_key(), $target);
	}
}
