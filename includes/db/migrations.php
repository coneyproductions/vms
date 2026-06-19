<?php
defined('ABSPATH') || exit;

function vms_db_migrate_vendor_core_v1(): void
{
	global $wpdb;

	$ver = get_option('vms_db_schema_version', '');
	// If we're already at v1 or later, no work needed.
	if ($ver === 'vendor_core_v1' || $ver === 'vendor_core_v2' || $ver === 'vendor_core_v3' || $ver === 'vendor_core_v4' || $ver === 'vendor_core_v5' || $ver === 'vendor_core_v6' || $ver === 'vendor_core_v7') return;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset_collate = $wpdb->get_charset_collate();

	$t_vendors  = $wpdb->prefix . 'vms_vendors';
	$t_contacts = $wpdb->prefix . 'vms_vendor_contacts';
	$t_meta     = $wpdb->prefix . 'vms_vendor_meta';
	$t_ev       = $wpdb->prefix . 'vms_event_vendors';

	$sql_vendors = "CREATE TABLE {$t_vendors} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		uuid CHAR(36) NOT NULL,
		vendor_type VARCHAR(32) NOT NULL,
		display_name VARCHAR(190) NOT NULL,
		status VARCHAR(20) NOT NULL DEFAULT 'active',

		primary_email VARCHAR(190) NULL,
		primary_phone VARCHAR(40) NULL,
		external_ref VARCHAR(190) NULL,
		slug VARCHAR(190) NULL,

		city VARCHAR(120) NULL,
		state VARCHAR(50) NULL,
		postal_code VARCHAR(20) NULL,
		country VARCHAR(2) NULL DEFAULT 'US',

		created_at DATETIME NOT NULL,
		updated_at DATETIME NOT NULL,

		PRIMARY KEY  (id),
		UNIQUE KEY uuid (uuid),
		UNIQUE KEY slug (slug),
		KEY vendor_type (vendor_type),
		KEY display_name (display_name),
		KEY status (status),
		KEY primary_email (primary_email),
		KEY primary_phone (primary_phone),
		KEY external_ref (external_ref),
		KEY city (city),
		KEY state (state)
	) {$charset_collate};";

	$sql_contacts = "CREATE TABLE {$t_contacts} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		vendor_id BIGINT(20) UNSIGNED NOT NULL,
		name VARCHAR(190) NOT NULL,
		role VARCHAR(50) NULL,
		email VARCHAR(190) NULL,
		phone VARCHAR(40) NULL,
		is_primary TINYINT(1) NOT NULL DEFAULT 0,
		notes TEXT NULL,

		PRIMARY KEY (id),
		KEY vendor_id (vendor_id),
		KEY email (email),
		KEY phone (phone),
		KEY is_primary (is_primary)
	) {$charset_collate};";

	$sql_meta = "CREATE TABLE {$t_meta} (
		vendor_id BIGINT(20) UNSIGNED NOT NULL,
		meta_key VARCHAR(190) NOT NULL,
		meta_value LONGTEXT NULL,
		updated_at DATETIME NOT NULL,

		PRIMARY KEY (vendor_id, meta_key),
		KEY meta_key (meta_key)
	) {$charset_collate};";

	// Optional now, but included so schema is complete
	$sql_event_vendors = "CREATE TABLE {$t_ev} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		event_id BIGINT(20) UNSIGNED NOT NULL,
		vendor_id BIGINT(20) UNSIGNED NOT NULL,
		role VARCHAR(50) NULL,
		status VARCHAR(20) NOT NULL DEFAULT 'tentative',
		sort_order INT(11) NOT NULL DEFAULT 0,
		notes TEXT NULL,

		PRIMARY KEY (id),
		UNIQUE KEY event_vendor_role (event_id, vendor_id, role),
		KEY event_id (event_id),
		KEY vendor_id (vendor_id),
		KEY status (status)
	) {$charset_collate};";

	dbDelta($sql_vendors);
	dbDelta($sql_contacts);
	dbDelta($sql_meta);
	dbDelta($sql_event_vendors);


	// Store schema version for future migrations
	update_option('vms_db_schema_version', 'vendor_core_v1');
}

function vms_db_migrate_vendor_core_v2(): void
{
	global $wpdb;

	$ver = get_option('vms_db_schema_version', '');
	if ($ver === 'vendor_core_v2' || $ver === 'vendor_core_v3' || $ver === 'vendor_core_v4' || $ver === 'vendor_core_v5' || $ver === 'vendor_core_v6' || $ver === 'vendor_core_v7') return;

	// Ensure v1 tables exist first.
	if (function_exists('vms_db_migrate_vendor_core_v1')) {
		vms_db_migrate_vendor_core_v1();
	}

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset_collate = $wpdb->get_charset_collate();
	$t_links = $wpdb->prefix . (defined('VMS_DB_TABLE_VENDOR_USER_LINKS_SUFFIX') ? VMS_DB_TABLE_VENDOR_USER_LINKS_SUFFIX : 'vms_vendor_user_links');

	$sql_links = "CREATE TABLE {$t_links} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		vendor_id bigint(20) unsigned NOT NULL,
		user_id bigint(20) unsigned NOT NULL,
		user_role varchar(32) NOT NULL DEFAULT 'manager',
		link_status varchar(20) NOT NULL DEFAULT 'active',
		is_primary tinyint(1) NOT NULL DEFAULT 0,
		notes text NULL,
		created_at datetime NOT NULL,
		created_by bigint(20) unsigned NULL,
		updated_at datetime NOT NULL,
		updated_by bigint(20) unsigned NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY vendor_user (vendor_id, user_id),
		KEY user_id (user_id),
		KEY vendor_id (vendor_id),
		KEY link_status (link_status),
		KEY is_primary (is_primary)
	) {$charset_collate};";

	dbDelta($sql_links);

	// Best-effort backfill from legacy pointers (idempotent).
	vms_db_backfill_vendor_user_links_from_legacy($t_links);

	update_option('vms_db_schema_version', 'vendor_core_v2');
}

function vms_db_migrate_vendor_core_v3(): void
{
	global $wpdb;

	$ver = get_option('vms_db_schema_version', '');
	if ($ver === 'vendor_core_v3' || $ver === 'vendor_core_v4' || $ver === 'vendor_core_v5' || $ver === 'vendor_core_v6' || $ver === 'vendor_core_v7') return;

	// Ensure v2 tables exist first.
	if (function_exists('vms_db_migrate_vendor_core_v2')) {
		vms_db_migrate_vendor_core_v2();
	}

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset_collate = $wpdb->get_charset_collate();

	$t_templates = $wpdb->prefix . (defined('VMS_DB_TABLE_STAFFING_TEMPLATES_SUFFIX') ? VMS_DB_TABLE_STAFFING_TEMPLATES_SUFFIX : 'vms_staffing_templates');
	$t_template_slots = $wpdb->prefix . (defined('VMS_DB_TABLE_STAFFING_TEMPLATE_SLOTS_SUFFIX') ? VMS_DB_TABLE_STAFFING_TEMPLATE_SLOTS_SUFFIX : 'vms_staffing_template_slots');
	$t_event_slots = $wpdb->prefix . (defined('VMS_DB_TABLE_EVENT_ROLE_SLOTS_SUFFIX') ? VMS_DB_TABLE_EVENT_ROLE_SLOTS_SUFFIX : 'vms_event_role_slots');
	$t_assignments = $wpdb->prefix . (defined('VMS_DB_TABLE_EVENT_ROLE_ASSIGNMENTS_SUFFIX') ? VMS_DB_TABLE_EVENT_ROLE_ASSIGNMENTS_SUFFIX : 'vms_event_role_assignments');
	$t_rollups = $wpdb->prefix . (defined('VMS_DB_TABLE_STAFFING_EVENT_ROLLUPS_SUFFIX') ? VMS_DB_TABLE_STAFFING_EVENT_ROLLUPS_SUFFIX : 'vms_staffing_event_rollups');
	$t_audit = $wpdb->prefix . (defined('VMS_DB_TABLE_STAFFING_AUDIT_LOG_SUFFIX') ? VMS_DB_TABLE_STAFFING_AUDIT_LOG_SUFFIX : 'vms_staffing_audit_log');

	$sql_templates = "CREATE TABLE {$t_templates} (
		template_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		name VARCHAR(190) NOT NULL,
		scope_venue_id BIGINT(20) UNSIGNED NULL,
		scope_day_of_week TINYINT UNSIGNED NULL,
		scope_event_type VARCHAR(64) NULL,
		priority INT(11) NOT NULL DEFAULT 100,
		is_active TINYINT(1) NOT NULL DEFAULT 1,
		auto_apply_on_event_create TINYINT(1) NOT NULL DEFAULT 1,
		created_at DATETIME NOT NULL,
		created_by BIGINT(20) UNSIGNED NULL,
		updated_at DATETIME NOT NULL,
		updated_by BIGINT(20) UNSIGNED NULL,
		PRIMARY KEY (template_id),
		KEY scope_venue_id (scope_venue_id),
		KEY scope_day_of_week (scope_day_of_week),
		KEY scope_event_type (scope_event_type),
		KEY is_active (is_active),
		KEY priority (priority),
		KEY auto_apply_on_event_create (auto_apply_on_event_create)
	) {$charset_collate};";

	$sql_template_slots = "CREATE TABLE {$t_template_slots} (
		template_slot_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		template_id BIGINT(20) UNSIGNED NOT NULL,
		role_id BIGINT(20) UNSIGNED NOT NULL,
		base_headcount INT(11) NOT NULL DEFAULT 1,
		shift_time_mode VARCHAR(20) NOT NULL DEFAULT 'absolute',
		shift_start_local VARCHAR(5) NULL,
		shift_end_local VARCHAR(5) NULL,
		start_anchor_key VARCHAR(20) NULL,
		start_offset_minutes INT(11) NOT NULL DEFAULT 0,
		end_anchor_key VARCHAR(20) NULL,
		end_offset_minutes INT(11) NOT NULL DEFAULT 0,
		duration_minutes INT(11) NULL,
		break_minutes INT(11) NOT NULL DEFAULT 0,
		pay_type VARCHAR(20) NOT NULL DEFAULT 'inherit_role',
		pay_rate DECIMAL(12,2) NULL,
		notes TEXT NULL,
		is_optional TINYINT(1) NOT NULL DEFAULT 0,
		created_at DATETIME NOT NULL,
		created_by BIGINT(20) UNSIGNED NULL,
		updated_at DATETIME NOT NULL,
		updated_by BIGINT(20) UNSIGNED NULL,
		PRIMARY KEY (template_slot_id),
		KEY template_id (template_id),
		KEY role_id (role_id),
		KEY time_mode (shift_time_mode)
	) {$charset_collate};";

	$sql_event_slots = "CREATE TABLE {$t_event_slots} (
		slot_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		event_plan_id BIGINT(20) UNSIGNED NOT NULL,
		role_id BIGINT(20) UNSIGNED NOT NULL,
		headcount_needed INT(11) NOT NULL DEFAULT 0,
		shift_time_mode VARCHAR(20) NOT NULL DEFAULT 'absolute',
		shift_start_local VARCHAR(5) NULL,
		shift_end_local VARCHAR(5) NULL,
		start_anchor_key VARCHAR(20) NULL,
		start_offset_minutes INT(11) NOT NULL DEFAULT 0,
		end_anchor_key VARCHAR(20) NULL,
		end_offset_minutes INT(11) NOT NULL DEFAULT 0,
		duration_minutes INT(11) NULL,
		break_minutes INT(11) NOT NULL DEFAULT 0,
		pay_type VARCHAR(20) NOT NULL DEFAULT 'inherit_role',
		pay_rate DECIMAL(12,2) NULL,
		display_label_override VARCHAR(190) NULL,
		notes TEXT NULL,
		status VARCHAR(20) NOT NULL DEFAULT 'active',
		created_at DATETIME NOT NULL,
		created_by BIGINT(20) UNSIGNED NULL,
		updated_at DATETIME NOT NULL,
		updated_by BIGINT(20) UNSIGNED NULL,
		PRIMARY KEY (slot_id),
		KEY event_plan_status (event_plan_id, status),
		KEY event_plan_role (event_plan_id, role_id),
		KEY role_id (role_id)
	) {$charset_collate};";

	$sql_assignments = "CREATE TABLE {$t_assignments} (
		assignment_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		slot_id BIGINT(20) UNSIGNED NOT NULL,
		staff_id BIGINT(20) UNSIGNED NOT NULL,
		status VARCHAR(20) NOT NULL DEFAULT 'proposed',
		pay_type_override VARCHAR(20) NULL,
		pay_rate_override DECIMAL(12,2) NULL,
		notes TEXT NULL,
		shift_start_ts BIGINT(20) NULL,
		shift_end_ts BIGINT(20) NULL,
		actual_start_local DATETIME NULL,
		actual_end_local DATETIME NULL,
		created_at DATETIME NOT NULL,
		created_by BIGINT(20) UNSIGNED NULL,
		updated_at DATETIME NOT NULL,
		updated_by BIGINT(20) UNSIGNED NULL,
		PRIMARY KEY (assignment_id),
		KEY slot_status (slot_id, status),
		KEY staff_status (staff_id, status),
		KEY staff_status_shift (staff_id, status, shift_start_ts, shift_end_ts)
	) {$charset_collate};";

	$sql_rollups = "CREATE TABLE {$t_rollups} (
		event_plan_id BIGINT(20) UNSIGNED NOT NULL,
		venue_id BIGINT(20) UNSIGNED NULL,
		event_status VARCHAR(20) NOT NULL DEFAULT 'draft',
		event_start_local DATETIME NULL,
		slots_total INT(11) NOT NULL DEFAULT 0,
		headcount_needed_total INT(11) NOT NULL DEFAULT 0,
		headcount_filled_total INT(11) NOT NULL DEFAULT 0,
		open_headcount_total INT(11) NOT NULL DEFAULT 0,
		open_slots_count INT(11) NOT NULL DEFAULT 0,
		critical_slots_total INT(11) NOT NULL DEFAULT 0,
		critical_open_headcount INT(11) NOT NULL DEFAULT 0,
		critical_open_slots_count INT(11) NOT NULL DEFAULT 0,
		conflict_count INT(11) NOT NULL DEFAULT 0,
		unavailable_assigned_count INT(11) NOT NULL DEFAULT 0,
		red_flag_reason_mask INT(11) NOT NULL DEFAULT 0,
		readiness_status VARCHAR(32) NOT NULL DEFAULT 'not_applicable',
		est_labor_cost_total DECIMAL(12,2) NULL,
		est_hours_total DECIMAL(12,2) NULL,
		missing_summary_json LONGTEXT NULL,
		conflict_summary_json LONGTEXT NULL,
		calc_version VARCHAR(32) NOT NULL DEFAULT 'staffing_v1',
		calc_hash CHAR(32) NULL,
		computed_at DATETIME NULL,
		dirty TINYINT(1) NOT NULL DEFAULT 1,
		dirty_reason VARCHAR(190) NULL,
		PRIMARY KEY (event_plan_id),
		KEY venue_event_status_start (venue_id, event_status, event_start_local),
		KEY venue_readiness_start (venue_id, readiness_status, event_start_local),
		KEY dirty_start (dirty, event_start_local)
	) {$charset_collate};";

	$sql_audit = "CREATE TABLE {$t_audit} (
		log_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		event_plan_id BIGINT(20) UNSIGNED NULL,
		actor_user_id BIGINT(20) UNSIGNED NULL,
		action VARCHAR(80) NOT NULL,
		before_json LONGTEXT NULL,
		after_json LONGTEXT NULL,
		created_at DATETIME NOT NULL,
		PRIMARY KEY (log_id),
		KEY event_plan_id (event_plan_id),
		KEY actor_user_id (actor_user_id),
		KEY action (action),
		KEY created_at (created_at)
	) {$charset_collate};";

	dbDelta($sql_templates);
	dbDelta($sql_template_slots);
	dbDelta($sql_event_slots);
	dbDelta($sql_assignments);
	dbDelta($sql_rollups);
	dbDelta($sql_audit);

	update_option('vms_db_schema_version', 'vendor_core_v3');
}

function vms_db_migrate_vendor_core_v4(): void
{
	global $wpdb;

	$ver = get_option('vms_db_schema_version', '');
	if ($ver === 'vendor_core_v4' || $ver === 'vendor_core_v5' || $ver === 'vendor_core_v6' || $ver === 'vendor_core_v7') {
		return;
	}

	if (function_exists('vms_db_migrate_vendor_core_v3')) {
		vms_db_migrate_vendor_core_v3();
	}

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset_collate = $wpdb->get_charset_collate();
	$t_goals = $wpdb->prefix . 'vms_goals';

	$sql_goals = "CREATE TABLE {$t_goals} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		is_active TINYINT(1) NOT NULL DEFAULT 0,
		venue_id BIGINT(20) UNSIGNED NULL,
		name VARCHAR(190) NOT NULL,
		metric VARCHAR(50) NOT NULL DEFAULT 'true_profit',
		period_type VARCHAR(20) NOT NULL DEFAULT 'month',
		period_start_local DATETIME NOT NULL,
		period_end_local DATETIME NOT NULL,
		target_cents BIGINT(20) NOT NULL DEFAULT 0,
		allocation_mode VARCHAR(20) NOT NULL DEFAULT 'even',
		weight_mode VARCHAR(30) NOT NULL DEFAULT 'none',
		created_at_utc DATETIME NOT NULL,
		updated_at_utc DATETIME NOT NULL,
		PRIMARY KEY (id),
		KEY is_active (is_active),
		KEY venue_id (venue_id),
		KEY metric (metric),
		KEY period_bounds (period_start_local, period_end_local),
		KEY updated_at_utc (updated_at_utc)
	) {$charset_collate};";

	dbDelta($sql_goals);
	update_option('vms_db_schema_version', 'vendor_core_v4');
}

function vms_db_migrate_vendor_core_v5(): void
{
	global $wpdb;

	$ver = get_option('vms_db_schema_version', '');
	if ($ver === 'vendor_core_v5' || $ver === 'vendor_core_v6' || $ver === 'vendor_core_v7') {
		return;
	}

	if (function_exists('vms_db_migrate_vendor_core_v4')) {
		vms_db_migrate_vendor_core_v4();
	}

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset_collate = $wpdb->get_charset_collate();
	$t_templates = $wpdb->prefix . (defined('VMS_DB_TABLE_STAFFING_TEMPLATES_SUFFIX') ? VMS_DB_TABLE_STAFFING_TEMPLATES_SUFFIX : 'vms_staffing_templates');

	$sql_templates = "CREATE TABLE {$t_templates} (
		template_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		name VARCHAR(190) NOT NULL,
		scope_venue_id BIGINT(20) UNSIGNED NULL,
		scope_day_of_week TINYINT UNSIGNED NULL,
		scope_event_type VARCHAR(64) NULL,
		priority INT(11) NOT NULL DEFAULT 100,
		is_active TINYINT(1) NOT NULL DEFAULT 1,
		auto_apply_on_event_create TINYINT(1) NOT NULL DEFAULT 1,
		min_headcount INT(11) NULL,
		max_headcount INT(11) NULL,
		created_at DATETIME NOT NULL,
		created_by BIGINT(20) UNSIGNED NULL,
		updated_at DATETIME NOT NULL,
		updated_by BIGINT(20) UNSIGNED NULL,
		PRIMARY KEY (template_id),
		KEY scope_venue_id (scope_venue_id),
		KEY scope_day_of_week (scope_day_of_week),
		KEY scope_event_type (scope_event_type),
		KEY is_active (is_active),
		KEY priority (priority),
		KEY auto_apply_on_event_create (auto_apply_on_event_create),
		KEY min_headcount (min_headcount),
		KEY max_headcount (max_headcount)
	) {$charset_collate};";

	dbDelta($sql_templates);
	update_option('vms_db_schema_version', 'vendor_core_v5');
}

function vms_db_migrate_vendor_core_v6(): void
{
	global $wpdb;

	$ver = get_option('vms_db_schema_version', '');
	if ($ver === 'vendor_core_v6' || $ver === 'vendor_core_v7') {
		return;
	}

	if (function_exists('vms_db_migrate_vendor_core_v5')) {
		vms_db_migrate_vendor_core_v5();
	}

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset_collate = $wpdb->get_charset_collate();
	$t_template_slots = $wpdb->prefix . (defined('VMS_DB_TABLE_STAFFING_TEMPLATE_SLOTS_SUFFIX') ? VMS_DB_TABLE_STAFFING_TEMPLATE_SLOTS_SUFFIX : 'vms_staffing_template_slots');

	$sql_template_slots = "CREATE TABLE {$t_template_slots} (
		template_slot_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		template_id BIGINT(20) UNSIGNED NOT NULL,
		role_id BIGINT(20) UNSIGNED NOT NULL,
		base_headcount INT(11) NOT NULL DEFAULT 1,
		activation_threshold INT(11) NOT NULL DEFAULT 1,
		shift_time_mode VARCHAR(20) NOT NULL DEFAULT 'absolute',
		shift_start_local VARCHAR(5) NULL,
		shift_end_local VARCHAR(5) NULL,
		start_anchor_key VARCHAR(20) NULL,
		start_offset_minutes INT(11) NOT NULL DEFAULT 0,
		end_anchor_key VARCHAR(20) NULL,
		end_offset_minutes INT(11) NOT NULL DEFAULT 0,
		duration_minutes INT(11) NULL,
		break_minutes INT(11) NOT NULL DEFAULT 0,
		pay_type VARCHAR(20) NOT NULL DEFAULT 'inherit_role',
		pay_rate DECIMAL(12,2) NULL,
		notes TEXT NULL,
		is_optional TINYINT(1) NOT NULL DEFAULT 0,
		created_at DATETIME NOT NULL,
		created_by BIGINT(20) UNSIGNED NULL,
		updated_at DATETIME NOT NULL,
		updated_by BIGINT(20) UNSIGNED NULL,
		PRIMARY KEY (template_slot_id),
		KEY template_id (template_id),
		KEY role_id (role_id),
		KEY activation_threshold (activation_threshold),
		KEY time_mode (shift_time_mode)
	) {$charset_collate};";

	dbDelta($sql_template_slots);
	update_option('vms_db_schema_version', 'vendor_core_v6');
}

function vms_db_migrate_vendor_core_v7(): void
{
	global $wpdb;

	$ver = get_option('vms_db_schema_version', '');
	if ($ver === 'vendor_core_v7') {
		return;
	}

	if (function_exists('vms_db_migrate_vendor_core_v6')) {
		vms_db_migrate_vendor_core_v6();
	}

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset_collate = $wpdb->get_charset_collate();
	$t_confirm_tokens = $wpdb->prefix . (defined('VMS_DB_TABLE_VENDOR_APP_CONFIRM_TOKENS_SUFFIX') ? VMS_DB_TABLE_VENDOR_APP_CONFIRM_TOKENS_SUFFIX : 'vms_vendor_app_confirm_tokens');

	$sql_confirm_tokens = "CREATE TABLE {$t_confirm_tokens} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		application_id BIGINT(20) UNSIGNED NOT NULL,
		email VARCHAR(190) NOT NULL,
		token_hash CHAR(64) NOT NULL,
		created_at DATETIME NOT NULL,
		expires_at DATETIME NOT NULL,
		sent_at DATETIME NULL,
		consumed_at DATETIME NULL,
		invalidated_at DATETIME NULL,
		invalidated_reason VARCHAR(64) NULL,
		resolved_user_id BIGINT(20) UNSIGNED NULL,
		created_by_user_id BIGINT(20) UNSIGNED NULL,
		consumed_ip VARCHAR(64) NULL,
		consumed_user_agent VARCHAR(255) NULL,
		PRIMARY KEY (id),
		KEY application_id (application_id),
		KEY email (email),
		KEY token_hash (token_hash),
		KEY expires_at (expires_at),
		KEY sent_at (sent_at),
		KEY consumed_at (consumed_at),
		KEY invalidated_at (invalidated_at)
	) {$charset_collate};";

	dbDelta($sql_confirm_tokens);
	update_option('vms_db_schema_version', 'vendor_core_v7');
}

/**
 * Backfill vendor↔user links from legacy single-link pointers.
 *
 * Sources:
 * - Vendor post_meta: _vms_vendor_user_id (primary contact user for vendor)
 * - User meta: _vms_vendor_id (primary/default vendor pointer)
 *
 * This is safe to run multiple times.
 */
function vms_db_backfill_vendor_user_links_from_legacy(string $t_links): void
{
	global $wpdb;

	$vendor_user_meta_key = defined('VMS_VENDOR_PRIMARY_USER_META_KEY') ? VMS_VENDOR_PRIMARY_USER_META_KEY : '_vms_vendor_user_id';
	$user_primary_vendor_meta_key = defined('VMS_USER_PRIMARY_VENDOR_META_KEY') ? VMS_USER_PRIMARY_VENDOR_META_KEY : '_vms_vendor_id';

	$now = current_time('mysql', true);

	// A) Vendor → user pointer
	$q = new WP_Query(array(
		'post_type'      => 'vms_vendor',
		'post_status'    => array('publish', 'draft', 'private'),
		'fields'         => 'ids',
		'posts_per_page' => -1,
		'meta_query'     => array(
			array(
				'key'     => $vendor_user_meta_key,
				'compare' => 'EXISTS',
			),
		),
		'no_found_rows'  => true,
	));

	if (!empty($q->posts)) {
		foreach ($q->posts as $vendor_id_raw) {
			$vendor_id = (int) $vendor_id_raw;
			if ($vendor_id <= 0) continue;

			$user_id = absint(get_post_meta($vendor_id, $vendor_user_meta_key, true));
			if ($user_id <= 0) continue;

			$user = get_user_by('id', $user_id);
			if (!$user) continue;

			$is_primary = 0;
			$primary_vendor = (int) get_user_meta($user_id, $user_primary_vendor_meta_key, true);
			if ($primary_vendor === $vendor_id) {
				$is_primary = 1;
			}

			$sql = $wpdb->prepare(
				"INSERT INTO {$t_links}
					(vendor_id, user_id, user_role, link_status, is_primary, created_at, created_by, updated_at, updated_by)
				 VALUES
					(%d, %d, %s, %s, %d, %s, %d, %s, %d)
				 ON DUPLICATE KEY UPDATE
					user_role   = VALUES(user_role),
					link_status = VALUES(link_status),
					is_primary  = IF(VALUES(is_primary)=1, 1, is_primary),
					updated_at  = VALUES(updated_at),
					updated_by  = VALUES(updated_by)",
				$vendor_id,
				$user_id,
				'primary_contact',
				'active',
				$is_primary,
				$now,
				0,
				$now,
				0
			);

			$wpdb->query($sql);
		}
	}

	// B) User → vendor pointer (primary vendor)
	$users = get_users(array(
		'fields'    => array('ID'),
		'meta_key'  => $user_primary_vendor_meta_key,
		'orderby'   => 'ID',
		'order'     => 'ASC',
	));

	if (!empty($users)) {
		foreach ($users as $u) {
			$user_id = isset($u->ID) ? (int) $u->ID : 0;
			if ($user_id <= 0) continue;

			$vendor_id = absint(get_user_meta($user_id, $user_primary_vendor_meta_key, true));
			if ($vendor_id <= 0) continue;

			$p = get_post($vendor_id);
			if (!$p || $p->post_type !== 'vms_vendor') continue;

			$sql = $wpdb->prepare(
				"INSERT INTO {$t_links}
					(vendor_id, user_id, user_role, link_status, is_primary, created_at, created_by, updated_at, updated_by)
				 VALUES
					(%d, %d, %s, %s, %d, %s, %d, %s, %d)
				 ON DUPLICATE KEY UPDATE
					is_primary  = 1,
					link_status = 'active',
					updated_at  = VALUES(updated_at),
					updated_by  = VALUES(updated_by)",
				$vendor_id,
				$user_id,
				'manager',
				'active',
				1,
				$now,
				0,
				$now,
				0
			);

			$wpdb->query($sql);

			// Enforce single primary per user.
			$wpdb->query($wpdb->prepare(
				"UPDATE {$t_links} SET is_primary = 0, updated_at = %s, updated_by = %d WHERE user_id = %d AND vendor_id <> %d",
				$now,
				0,
				$user_id,
				$vendor_id
			));
		}
	}
}
