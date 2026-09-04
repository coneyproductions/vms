<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_outreach_db_option_key')) {
	function vms_outreach_db_option_key(): string
	{
		return 'vms_outreach_db_version';
	}
}

if (!function_exists('vms_outreach_db_version_target')) {
	function vms_outreach_db_version_target(): string
	{
		return '1.1.0';
	}
}

if (!function_exists('vms_outreach_table_contacts')) {
	function vms_outreach_table_contacts(): string
	{
		global $wpdb;
		return $wpdb->prefix . 'vms_outreach_contacts';
	}
}

if (!function_exists('vms_outreach_table_suppressions')) {
	function vms_outreach_table_suppressions(): string
	{
		global $wpdb;
		return $wpdb->prefix . 'vms_outreach_suppressions';
	}
}

if (!function_exists('vms_outreach_maybe_add_index')) {
	function vms_outreach_maybe_add_index(string $table, string $index_name, string $column_name): void
	{
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time schema introspection for an additive companion index.
		$exists = $wpdb->get_var($wpdb->prepare('SHOW INDEX FROM %i WHERE Key_name = %s', $table, $index_name));
		if ($exists !== null) {
			return;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange -- Additive, idempotence-guarded index creation on the BVM claims table.
		$wpdb->query($wpdb->prepare('ALTER TABLE %i ADD KEY %i (%i)', $table, $index_name, $column_name));
	}
}

if (!function_exists('vms_outreach_maybe_upgrade_schema')) {
	function vms_outreach_maybe_upgrade_schema(): void
	{
		$current = (string) get_option(vms_outreach_db_option_key(), '');
		$target = vms_outreach_db_version_target();
		if ($current === $target) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$contacts = vms_outreach_table_contacts();
		$suppressions = vms_outreach_table_suppressions();
		$campaigns = vms_admission_table_pass_outreach_campaigns();
		$recipients = vms_admission_table_pass_outreach_recipients();
		$claims = vms_admission_table_pass_claims();

		$sql_contacts = "CREATE TABLE {$contacts} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			business_name VARCHAR(190) NULL,
			contact_name VARCHAR(190) NOT NULL DEFAULT '',
			first_name VARCHAR(120) NULL,
			last_name VARCHAR(120) NULL,
			email VARCHAR(190) NOT NULL,
			email_norm VARCHAR(190) NOT NULL,
			phone VARCHAR(60) NULL,
			phone_norm VARCHAR(40) NULL,
			website VARCHAR(255) NULL,
			facebook_url VARCHAR(255) NULL,
			instagram_url VARCHAR(255) NULL,
			city VARCHAR(120) NULL,
			state VARCHAR(120) NULL,
			company_group VARCHAR(190) NULL,
			contact_type VARCHAR(60) NOT NULL DEFAULT 'other',
			tags TEXT NULL,
			source VARCHAR(190) NULL,
			status VARCHAR(60) NOT NULL DEFAULT 'new',
			notes LONGTEXT NULL,
			created_by BIGINT(20) UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			updated_by BIGINT(20) UNSIGNED NULL,
			updated_at DATETIME NULL,
			PRIMARY KEY (id),
			UNIQUE KEY email_norm (email_norm),
			KEY contact_type (contact_type),
			KEY contact_status (status),
			KEY city_state (city, state),
			KEY updated_at (updated_at)
		) {$charset_collate};";

		$sql_suppressions = "CREATE TABLE {$suppressions} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			email VARCHAR(190) NOT NULL,
			email_norm VARCHAR(190) NOT NULL,
			reason VARCHAR(120) NOT NULL DEFAULT 'manual_admin',
			scope VARCHAR(60) NOT NULL DEFAULT 'global_outreach',
			source_contact_id BIGINT(20) UNSIGNED NULL,
			source_campaign_id BIGINT(20) UNSIGNED NULL,
			source_label VARCHAR(190) NULL,
			suppressed_at DATETIME NOT NULL,
			notes LONGTEXT NULL,
			created_by BIGINT(20) UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			updated_by BIGINT(20) UNSIGNED NULL,
			updated_at DATETIME NULL,
			PRIMARY KEY (id),
			UNIQUE KEY scope_email (scope, email_norm),
			KEY email_norm (email_norm),
			KEY suppressed_at (suppressed_at),
			KEY source_contact_id (source_contact_id),
			KEY source_campaign_id (source_campaign_id)
		) {$charset_collate};";

		$sql_campaigns = "CREATE TABLE {$campaigns} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			campaign_name VARCHAR(190) NOT NULL,
			campaign_purpose VARCHAR(60) NOT NULL DEFAULT 'guest_pass_invitation',
			email_subject VARCHAR(255) NULL,
			message_template LONGTEXT NULL,
			internal_notes LONGTEXT NULL,
			purpose_config_json LONGTEXT NULL,
			related_source_id BIGINT(20) UNSIGNED NULL,
			related_batch_id BIGINT(20) UNSIGNED NULL,
			validity_type VARCHAR(20) NOT NULL DEFAULT 'batch_default',
			single_event_plan_id BIGINT(20) UNSIGNED NULL,
			start_date DATE NULL,
			end_date DATE NULL,
			season_label VARCHAR(120) NULL,
			expires_at DATETIME NULL,
			admissions_per_recipient SMALLINT(5) UNSIGNED NOT NULL DEFAULT 1,
			total_admission_cap INT(10) UNSIGNED NOT NULL DEFAULT 0,
			status VARCHAR(20) NOT NULL DEFAULT 'draft',
			eligibility_mode VARCHAR(40) NOT NULL DEFAULT 'anyone_with_invite',
			created_by BIGINT(20) UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL,
			updated_by BIGINT(20) UNSIGNED NULL,
			updated_at DATETIME NULL,
			PRIMARY KEY (id),
			KEY campaign_status (status, id),
			KEY source_status (related_source_id, status),
			KEY batch_status (related_batch_id, status),
			KEY validity_dates (validity_type, start_date, end_date)
		) {$charset_collate};";

		$sql_recipients = "CREATE TABLE {$recipients} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			campaign_id BIGINT(20) UNSIGNED NOT NULL,
			pass_token_id BIGINT(20) UNSIGNED NULL,
			pass_claim_id BIGINT(20) UNSIGNED NULL,
			reservation_entry_id BIGINT(20) UNSIGNED NULL,
			contact_id BIGINT(20) UNSIGNED NULL,
			first_name VARCHAR(120) NULL,
			last_name VARCHAR(120) NULL,
			full_name VARCHAR(190) NULL,
			email VARCHAR(190) NULL,
			email_norm VARCHAR(190) NULL,
			phone VARCHAR(40) NULL,
			phone_norm VARCHAR(40) NULL,
			company VARCHAR(190) NULL,
			group_label VARCHAR(190) NULL,
			notes LONGTEXT NULL,
			invite_token VARCHAR(120) NOT NULL,
			send_status VARCHAR(30) NOT NULL DEFAULT 'not_sent',
			sent_at DATETIME NULL,
			sent_by BIGINT(20) UNSIGNED NULL,
			send_method VARCHAR(40) NULL,
			last_send_error LONGTEXT NULL,
			last_contacted_at DATETIME NULL,
			claimed_at DATETIME NULL,
			revoked_at DATETIME NULL,
			expires_at DATETIME NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'ready',
			claimed_headcount SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0,
			created_by BIGINT(20) UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL,
			updated_by BIGINT(20) UNSIGNED NULL,
			updated_at DATETIME NULL,
			PRIMARY KEY (id),
			UNIQUE KEY invite_token (invite_token),
			KEY campaign_status (campaign_id, status),
			KEY campaign_send_status (campaign_id, send_status),
			KEY pass_token (pass_token_id),
			KEY pass_claim (pass_claim_id),
			KEY contact_id (contact_id),
			KEY recipient_email (email_norm),
			KEY recipient_phone (phone_norm),
			KEY last_contacted_at (last_contacted_at)
		) {$charset_collate};";

		dbDelta($sql_contacts);
		dbDelta($sql_suppressions);
		dbDelta($sql_campaigns);
		dbDelta($sql_recipients);

		// The core claims table remains BVM-owned. These historical attribution
		// columns are added only when absent; existing rows and IDs are untouched.
		maybe_add_column($claims, 'outreach_campaign_id', "ALTER TABLE {$claims} ADD outreach_campaign_id BIGINT(20) UNSIGNED NULL");
		maybe_add_column($claims, 'outreach_recipient_id', "ALTER TABLE {$claims} ADD outreach_recipient_id BIGINT(20) UNSIGNED NULL");
		vms_outreach_maybe_add_index($claims, 'outreach_campaign', 'outreach_campaign_id');
		vms_outreach_maybe_add_index($claims, 'outreach_recipient', 'outreach_recipient_id');

		// Preserve the original migration/backfill semantics for pre-purpose and
		// pre-delivery-status rows. No records are deleted or duplicated.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Additive migration of companion-owned historical rows.
		$wpdb->query("UPDATE {$campaigns} SET campaign_purpose = 'guest_pass_invitation' WHERE campaign_purpose IS NULL OR campaign_purpose = ''");
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Additive migration of companion-owned historical rows.
		$wpdb->query(
			"UPDATE {$recipients}
			SET send_status = CASE
				WHEN send_status IN ('not_sent', 'queued', 'sent', 'failed', 'suppressed', 'do_not_contact') THEN send_status
				WHEN sent_at IS NOT NULL OR status = 'sent' THEN 'sent'
				ELSE 'not_sent'
			END"
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Additive migration of companion-owned historical rows.
		$wpdb->query("UPDATE {$recipients} SET last_contacted_at = sent_at WHERE last_contacted_at IS NULL AND sent_at IS NOT NULL");

		update_option(vms_outreach_db_option_key(), $target, false);
	}
}
