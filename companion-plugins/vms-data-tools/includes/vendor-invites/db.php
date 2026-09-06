<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_dt_vio_claim_tokens_table')) {
    function vms_dt_vio_claim_tokens_table(): string
    {
        global $wpdb;
        return (string) $wpdb->prefix . 'vms_vendor_claim_tokens';
    }
}

if (!function_exists('vms_dt_vio_invite_log_table')) {
    function vms_dt_vio_invite_log_table(): string
    {
        global $wpdb;
        return (string) $wpdb->prefix . 'vms_vendor_invite_log';
    }
}

if (!function_exists('vms_dt_vio_opportunity_submissions_table')) {
    function vms_dt_vio_opportunity_submissions_table(): string
    {
        global $wpdb;
        return (string) $wpdb->prefix . 'vms_vendor_opportunity_submissions';
    }
}

if (!function_exists('vms_dt_vio_get_retention_audit_table')) {
    function vms_dt_vio_get_retention_audit_table(): string
    {
        global $wpdb;
        return (string) $wpdb->prefix . 'vms_vendor_invite_retention_audit';
    }
}

if (!function_exists('vms_dt_vio_db_version')) {
    function vms_dt_vio_db_version(): string
    {
        return '1.1.0';
    }
}

if (!function_exists('vms_dt_vio_db_schema_ready')) {
    function vms_dt_vio_db_schema_ready(): bool
    {
        global $wpdb;

        if (!isset($wpdb) || !($wpdb instanceof wpdb)) {
            return false;
        }

        $log_table = vms_dt_vio_invite_log_table();
        $audit_table = vms_dt_vio_get_retention_audit_table();
        $required_columns = array(
            'retention_state',
            'archived_at',
            'archived_by_user_id',
            'archived_reason',
            'retention_batch_id',
        );

        foreach ($required_columns as $column) {
            $found = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$log_table} LIKE %s", $column));
            if (!is_string($found) || $found === '') {
                return false;
            }
        }

        $audit_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $audit_table));
        return is_string($audit_exists) && $audit_exists === $audit_table;
    }
}

if (!function_exists('vms_dt_vio_install_or_upgrade')) {
    /**
     * @return array<string,mixed>
     */
    function vms_dt_vio_install_or_upgrade(): array
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $tokens_table = vms_dt_vio_claim_tokens_table();
        $log_table = vms_dt_vio_invite_log_table();
        $submissions_table = vms_dt_vio_opportunity_submissions_table();
        $retention_audit_table = vms_dt_vio_get_retention_audit_table();

        $sql_tokens = "CREATE TABLE {$tokens_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            vendor_id BIGINT UNSIGNED NOT NULL,
            token_hash CHAR(64) NOT NULL,
            created_at DATETIME NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            used_ip VARCHAR(64) NULL,
            used_user_agent VARCHAR(255) NULL,
            created_by_user_id BIGINT UNSIGNED NULL,
            invite_log_id BIGINT UNSIGNED NULL,
            PRIMARY KEY  (id),
            KEY vendor_id (vendor_id),
            KEY token_hash (token_hash),
            KEY expires_at (expires_at),
            KEY used_at (used_at)
        ) {$charset_collate};";

        $sql_log = "CREATE TABLE {$log_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_type VARCHAR(32) NOT NULL DEFAULT 'invite_send',
            vendor_id BIGINT UNSIGNED NOT NULL,
            mode VARCHAR(20) NOT NULL,
            lang VARCHAR(12) NOT NULL,
            mailpoet_subscriber_id BIGINT UNSIGNED NULL,
            mailpoet_tag VARCHAR(190) NOT NULL,
            mailpoet_template_key VARCHAR(190) NULL,
            sent_at DATETIME NOT NULL,
            sent_by_user_id BIGINT UNSIGNED NOT NULL,
            open_dates_count INT UNSIGNED NULL DEFAULT 0,
            open_dates_payload_hash CHAR(64) NULL,
            claim_token_id BIGINT UNSIGNED NULL,
            status VARCHAR(20) NOT NULL,
            error_message TEXT NULL,
            retention_state VARCHAR(20) NOT NULL DEFAULT 'active',
            archived_at DATETIME NULL,
            archived_by_user_id BIGINT UNSIGNED NULL,
            archived_reason VARCHAR(190) NULL,
            retention_batch_id CHAR(32) NULL,
            PRIMARY KEY  (id),
            KEY event_type (event_type),
            KEY vendor_id (vendor_id),
            KEY mode (mode),
            KEY lang (lang),
            KEY status (status),
            KEY sent_at (sent_at),
            KEY claim_token_id (claim_token_id),
            KEY retention_state (retention_state),
            KEY archived_at (archived_at),
            KEY retention_batch_id (retention_batch_id)
        ) {$charset_collate};";

        $sql_submissions = "CREATE TABLE {$submissions_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            vendor_id BIGINT UNSIGNED NOT NULL,
            event_plan_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            submitted_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            note TEXT NULL,
            submitted_by_user_id BIGINT UNSIGNED NULL,
            updated_by_user_id BIGINT UNSIGNED NULL,
            PRIMARY KEY  (id),
            KEY vendor_id (vendor_id),
            KEY event_plan_id (event_plan_id),
            KEY status (status),
            KEY submitted_at (submitted_at)
        ) {$charset_collate};";

        $sql_retention_audit = "CREATE TABLE {$retention_audit_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            batch_id CHAR(32) NOT NULL,
            action_type VARCHAR(20) NOT NULL,
            scope_type VARCHAR(20) NOT NULL,
            log_id BIGINT UNSIGNED NULL,
            vendor_id BIGINT UNSIGNED NOT NULL,
            event_type VARCHAR(32) NOT NULL,
            log_status VARCHAR(20) NOT NULL,
            retention_state_before VARCHAR(20) NOT NULL,
            sent_at DATETIME NOT NULL,
            claim_token_id BIGINT UNSIGNED NULL,
            claim_token_used_at DATETIME NULL,
            claim_token_expires_at DATETIME NULL,
            performed_at DATETIME NOT NULL,
            performed_by_user_id BIGINT UNSIGNED NOT NULL,
            reason_code VARCHAR(32) NOT NULL DEFAULT 'manual_cleanup',
            operator_note TEXT NULL,
            snapshot_json LONGTEXT NULL,
            PRIMARY KEY  (id),
            KEY batch_id (batch_id),
            KEY action_type (action_type),
            KEY log_id (log_id),
            KEY vendor_id (vendor_id),
            KEY performed_at (performed_at)
        ) {$charset_collate};";

        dbDelta($sql_tokens);
        dbDelta($sql_log);
        dbDelta($sql_submissions);
        dbDelta($sql_retention_audit);

        // Ensure settings exist with deterministic defaults.
        if (!is_array(get_option(VMS_DT_VIO_SETTINGS_OPTION, null))) {
            add_option(VMS_DT_VIO_SETTINGS_OPTION, vms_dt_vio_default_settings(), '', false);
        } else {
            $current_settings = vms_dt_vio_get_settings();
            update_option(VMS_DT_VIO_SETTINGS_OPTION, $current_settings, false);
        }

        update_option(VMS_DT_VIO_DB_VERSION_OPTION, vms_dt_vio_db_version(), false);

        return array(
            'ok' => true,
            'db_version' => vms_dt_vio_db_version(),
            'tokens_table' => $tokens_table,
            'log_table' => $log_table,
            'submissions_table' => $submissions_table,
            'retention_audit_table' => $retention_audit_table,
        );
    }
}

if (!function_exists('vms_dt_vio_maybe_upgrade_db')) {
    function vms_dt_vio_maybe_upgrade_db(): void
    {
        $stored = (string) get_option(VMS_DT_VIO_DB_VERSION_OPTION, '');
        if ($stored !== vms_dt_vio_db_version() || !vms_dt_vio_db_schema_ready()) {
            vms_dt_vio_install_or_upgrade();
        }
    }
}

if (!function_exists('vms_dt_vio_activate')) {
    function vms_dt_vio_activate(): void
    {
        vms_dt_vio_install_or_upgrade();

        if (function_exists('vms_dt_vio_register_claim_endpoint')) {
            vms_dt_vio_register_claim_endpoint();
        }

        flush_rewrite_rules(false);
    }
}

if (!function_exists('vms_dt_vio_deactivate')) {
    function vms_dt_vio_deactivate(): void
    {
        if (function_exists('vms_dt_vio_register_claim_endpoint')) {
            vms_dt_vio_register_claim_endpoint();
        }

        flush_rewrite_rules(false);
    }
}
