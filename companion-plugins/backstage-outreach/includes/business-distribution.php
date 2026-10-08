<?php
/**
 * Reusable business Sources and shared partner Guest Pass distribution.
 *
 * Outreach owns business identity, Source membership, campaign selection, and
 * distribution attribution. BVM remains the claim/admission credential owner.
 */

defined('ABSPATH') || exit;

function backstage_outreach_business_table(string $suffix): string
{
	global $wpdb;
	return $wpdb->prefix . 'vms_outreach_' . $suffix;
}

function backstage_outreach_business_schema_upgrade(): void
{
	$target = '1.2.1';
	if ((string) get_option('backstage_outreach_business_db_version', '') === $target) {
		return;
	}
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	global $wpdb;
	$collate = $wpdb->get_charset_collate();
	$businesses = backstage_outreach_business_table('businesses');
	$memberships = backstage_outreach_business_table('source_businesses');
	$distributions = backstage_outreach_business_table('campaign_businesses');
	$claims = backstage_outreach_business_table('distribution_claims');

	dbDelta("CREATE TABLE {$businesses} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		public_id CHAR(36) NOT NULL,
		business_name VARCHAR(190) NOT NULL,
		contact_name VARCHAR(190) NULL,
		email VARCHAR(190) NULL,
		phone VARCHAR(60) NULL,
		website VARCHAR(255) NULL,
		address_line VARCHAR(255) NULL,
		city VARCHAR(120) NULL,
		state VARCHAR(80) NULL,
		postal_code VARCHAR(30) NULL,
		notes LONGTEXT NULL,
		status VARCHAR(20) NOT NULL DEFAULT 'active',
		created_by BIGINT(20) UNSIGNED NOT NULL,
		created_at DATETIME NOT NULL,
		updated_by BIGINT(20) UNSIGNED NULL,
		updated_at DATETIME NULL,
		PRIMARY KEY (id),
		UNIQUE KEY public_id (public_id),
		KEY business_status (status, business_name)
	) {$collate};");

	dbDelta("CREATE TABLE {$memberships} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		source_id BIGINT(20) UNSIGNED NOT NULL,
		business_id BIGINT(20) UNSIGNED NOT NULL,
		status VARCHAR(20) NOT NULL DEFAULT 'active',
		provenance_type VARCHAR(40) NOT NULL DEFAULT 'manual',
		provenance_key CHAR(64) NULL,
		provenance_recipient_id BIGINT(20) UNSIGNED NULL,
		original_snapshot_json LONGTEXT NULL,
		created_by BIGINT(20) UNSIGNED NOT NULL,
		created_at DATETIME NOT NULL,
		updated_at DATETIME NULL,
		PRIMARY KEY (id),
		UNIQUE KEY source_business (source_id, business_id),
		UNIQUE KEY source_provenance (source_id, provenance_key),
		KEY business_status (business_id, status),
		KEY recipient_provenance (provenance_recipient_id)
	) {$collate};");

	dbDelta("CREATE TABLE {$distributions} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		campaign_id BIGINT(20) UNSIGNED NOT NULL,
		source_id BIGINT(20) UNSIGNED NOT NULL,
		business_id BIGINT(20) UNSIGNED NOT NULL,
		public_key VARCHAR(64) NOT NULL,
		token_hash CHAR(64) NOT NULL,
		status VARCHAR(20) NOT NULL DEFAULT 'active',
		distribution_type VARCHAR(24) NOT NULL DEFAULT 'complimentary',
		admission_cap INT(10) UNSIGNED NOT NULL DEFAULT 0,
		order_cap INT(10) UNSIGNED NOT NULL DEFAULT 0,
		coupon_id BIGINT(20) UNSIGNED NULL,
		coupon_code VARCHAR(100) NULL,
		eligible_event_ids_json LONGTEXT NULL,
		eligible_product_ids_json LONGTEXT NULL,
		expires_at DATETIME NULL,
		created_by BIGINT(20) UNSIGNED NOT NULL,
		created_at DATETIME NOT NULL,
		updated_by BIGINT(20) UNSIGNED NULL,
		updated_at DATETIME NULL,
		PRIMARY KEY (id),
		UNIQUE KEY campaign_business (campaign_id, business_id),
		UNIQUE KEY public_key (public_key),
		KEY campaign_status (campaign_id, status),
		KEY source_business (source_id, business_id)
	) {$collate};");

	dbDelta("CREATE TABLE {$claims} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		distribution_id BIGINT(20) UNSIGNED NOT NULL,
		campaign_id BIGINT(20) UNSIGNED NOT NULL,
		source_id BIGINT(20) UNSIGNED NOT NULL,
		business_id BIGINT(20) UNSIGNED NOT NULL,
		pass_token_id BIGINT(20) UNSIGNED NULL,
		pass_claim_id BIGINT(20) UNSIGNED NULL,
		reservation_entry_id BIGINT(20) UNSIGNED NULL,
		party_size SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0,
		submission_key_hash CHAR(64) NOT NULL,
		identity_hash CHAR(64) NOT NULL,
		status VARCHAR(20) NOT NULL DEFAULT 'pending',
		failure_code VARCHAR(80) NULL,
		created_at DATETIME NOT NULL,
		fulfilled_at DATETIME NULL,
		PRIMARY KEY (id),
		UNIQUE KEY distribution_submission (distribution_id, submission_key_hash),
		KEY campaign_identity (campaign_id, identity_hash),
		KEY campaign_business (campaign_id, business_id),
		KEY pass_claim (pass_claim_id),
		KEY reservation_entry (reservation_entry_id)
	) {$collate};");

	dbDelta("CREATE TABLE " . backstage_outreach_business_table('paid_redemptions') . " (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		distribution_id BIGINT(20) UNSIGNED NOT NULL,
		campaign_id BIGINT(20) UNSIGNED NOT NULL,
		source_id BIGINT(20) UNSIGNED NOT NULL,
		business_id BIGINT(20) UNSIGNED NOT NULL,
		coupon_id BIGINT(20) UNSIGNED NOT NULL,
		coupon_code VARCHAR(100) NOT NULL,
		order_id BIGINT(20) UNSIGNED NOT NULL,
		status VARCHAR(24) NOT NULL DEFAULT 'pending',
		ticket_quantity INT(10) UNSIGNED NOT NULL DEFAULT 0,
		discount_total DECIMAL(18,6) NOT NULL DEFAULT 0,
		eligible_ticket_gross_total DECIMAL(18,6) NOT NULL DEFAULT 0,
		eligible_ticket_net_total DECIMAL(18,6) NOT NULL DEFAULT 0,
		eligible_ticket_refunded_total DECIMAL(18,6) NOT NULL DEFAULT 0,
		order_total DECIMAL(18,6) NOT NULL DEFAULT 0,
		refunded_total DECIMAL(18,6) NOT NULL DEFAULT 0,
		settlement_review_code VARCHAR(80) NULL,
		currency VARCHAR(12) NULL,
		eligible_ticket_ids_json LONGTEXT NULL,
		created_at DATETIME NOT NULL,
		updated_at DATETIME NULL,
		paid_at DATETIME NULL,
		failed_at DATETIME NULL,
		cancelled_at DATETIME NULL,
		refunded_at DATETIME NULL,
		reservation_expires_at DATETIME NULL,
		PRIMARY KEY (id),
		UNIQUE KEY order_id (order_id),
		KEY distribution_status (distribution_id, status),
		KEY campaign_status (campaign_id, status),
		KEY coupon_id (coupon_id)
	) {$collate};");
	update_option('backstage_outreach_business_db_version', $target, false);
	update_option('backstage_outreach_flush_rewrite', '1', false);
}

function backstage_outreach_business_now(): string
{
	return function_exists('bvmgr_admission_now_mysql') ? bvmgr_admission_now_mysql() : current_time('mysql');
}

function backstage_outreach_business_page_url(array $args = array()): string
{
	return bvmgr_pass_claims_admin_page_url(array_merge(array('tab' => 'sources'), $args));
}

function backstage_outreach_business_redirect(array $args = array()): void
{
	wp_safe_redirect(backstage_outreach_business_page_url($args));
	exit;
}

function backstage_outreach_business_message(string $message, string $type = 'info'): void
{
	if (function_exists('bvmgr_pass_claims_set_user_message')) {
		bvmgr_pass_claims_set_user_message($type, $message);
	}
}

function backstage_outreach_request_text(array $source, string $key, string $default = ''): string
{
	return isset($source[$key]) && is_scalar($source[$key]) ? sanitize_text_field((string) wp_unslash($source[$key])) : $default;
}

function backstage_outreach_request_absint(array $source, string $key, int $default = 0): int
{
	return isset($source[$key]) && is_scalar($source[$key]) ? absint((string) $source[$key]) : $default;
}

function backstage_outreach_business_payload(array $raw)
{
	$read = static fn(string $key): string => isset($raw[$key]) && is_scalar($raw[$key]) ? (string) $raw[$key] : '';
	$payload = backstage_outreach_business_sanitized_payload($raw);
	if ($payload['business_name'] === '') {
		return new WP_Error('business_name_required', __('Business name is required.', 'backstage-outreach'));
	}
	if (trim($read('email')) !== '' && $payload['email'] === '') {
		return new WP_Error('business_email_invalid', __('Enter a valid email address or leave it blank.', 'backstage-outreach'));
	}
	return $payload;
}

function backstage_outreach_business_sanitized_payload(array $raw): array
{
	$read = static fn(string $key): string => isset($raw[$key]) && is_scalar($raw[$key]) ? (string) $raw[$key] : '';
	return array(
		'business_name' => sanitize_text_field($read('business_name')),
		'contact_name' => sanitize_text_field($read('contact_name')),
		'email' => sanitize_email($read('email')),
		'phone' => sanitize_text_field($read('phone')),
		'website' => esc_url_raw($read('website')),
		'address_line' => sanitize_text_field($read('address_line')),
		'city' => sanitize_text_field($read('city')),
		'state' => sanitize_text_field($read('state')),
		'postal_code' => sanitize_text_field($read('postal_code')),
		'notes' => sanitize_textarea_field($read('notes')),
	);
}

function backstage_outreach_get_business(int $business_id): ?array
{
	if ($business_id <= 0) {
		return null;
	}
	global $wpdb;
	$row = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id = %d', backstage_outreach_business_table('businesses'), $business_id), ARRAY_A);
	return is_array($row) ? $row : null;
}

function backstage_outreach_source_businesses(int $source_id, bool $include_inactive = true): array
{
	global $wpdb;
	$sql = 'SELECT b.*, m.id AS membership_id, m.status AS membership_status, m.provenance_type, m.provenance_recipient_id
		FROM %i m INNER JOIN %i b ON b.id = m.business_id WHERE m.source_id = %d';
	$params = array(backstage_outreach_business_table('source_businesses'), backstage_outreach_business_table('businesses'), $source_id);
	if (!$include_inactive) {
		$sql .= " AND m.status = 'active' AND b.status = 'active'";
	}
	$sql .= ' ORDER BY b.business_name ASC, b.id ASC';
	$rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
	return is_array($rows) ? $rows : array();
}

function backstage_outreach_all_businesses(bool $include_inactive = true): array
{
	global $wpdb;
	$sql = 'SELECT DISTINCT b.* FROM %i b INNER JOIN %i m ON m.business_id = b.id';
	$params = array(backstage_outreach_business_table('businesses'), backstage_outreach_business_table('source_businesses'));
	if (!$include_inactive) {
		$sql .= " WHERE b.status = 'active' AND m.status = 'active'";
	}
	$sql .= ' ORDER BY b.business_name ASC, b.id ASC';
	$rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
	return is_array($rows) ? $rows : array();
}

function backstage_outreach_business_belongs_to_source(int $business_id, int $source_id): bool
{
	if ($business_id <= 0 || $source_id <= 0) {
		return false;
	}
	global $wpdb;
	return (bool) $wpdb->get_var($wpdb->prepare('SELECT id FROM %i WHERE source_id = %d AND business_id = %d', backstage_outreach_business_table('source_businesses'), $source_id, $business_id));
}

function backstage_outreach_business_upsert_membership(int $source_id, int $business_id, string $type, ?string $key, int $recipient_id, array $snapshot, int $user_id): bool
{
	global $wpdb;
	$table = backstage_outreach_business_table('source_businesses');
	$existing = null;
	if ($key !== null && $key !== '') {
		$existing = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE source_id = %d AND provenance_key = %s', $table, $source_id, $key), ARRAY_A);
	}
	if (!is_array($existing)) {
		$existing = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE source_id = %d AND business_id = %d', $table, $source_id, $business_id), ARRAY_A);
	}
	if (is_array($existing)) {
		return $wpdb->update($table, array('status' => 'active', 'updated_at' => backstage_outreach_business_now()), array('id' => (int) $existing['id']), array('%s', '%s'), array('%d')) !== false;
	}
	return $wpdb->insert($table, array(
		'source_id' => $source_id,
		'business_id' => $business_id,
		'status' => 'active',
		'provenance_type' => sanitize_key($type),
		'provenance_key' => $key ?: null,
		'provenance_recipient_id' => $recipient_id ?: null,
		'original_snapshot_json' => !empty($snapshot) ? wp_json_encode($snapshot) : null,
		'created_by' => $user_id,
		'created_at' => backstage_outreach_business_now(),
	), array('%d', '%d', '%s', '%s', '%s', '%d', '%s', '%d', '%s')) !== false;
}

function backstage_outreach_insert_business(array $payload, int $user_id): int
{
	global $wpdb;
	$payload['public_id'] = wp_generate_uuid4();
	$payload['status'] = 'active';
	$payload['created_by'] = $user_id;
	$payload['created_at'] = backstage_outreach_business_now();
	$ok = $wpdb->insert(backstage_outreach_business_table('businesses'), $payload);
	return $ok === false ? 0 : (int) $wpdb->insert_id;
}

function backstage_outreach_handle_business_save(): void
{
	if (!current_user_can(vms_pass_claims_capability())) {
		wp_die(esc_html__('Access denied.', 'backstage-outreach'));
	}
	check_admin_referer('backstage_outreach_business_save');
	$source_id = backstage_outreach_request_absint($_POST, 'source_id');
	$business_id = backstage_outreach_request_absint($_POST, 'business_id');
	$reuse_business_id = backstage_outreach_request_absint($_POST, 'reuse_business_id');
	$source = bvmgr_pass_claims_get_source_by_id($source_id);
	if (!$source) {
		backstage_outreach_business_message(__('Source not found.', 'backstage-outreach'), 'error');
		backstage_outreach_business_redirect(array('source_id' => $source_id));
	}
	if ($business_id <= 0 && $reuse_business_id > 0) {
		$business = backstage_outreach_get_business($reuse_business_id);
		$saved = is_array($business) && (string) ($business['status'] ?? '') === 'active'
			&& backstage_outreach_business_upsert_membership($source_id, $reuse_business_id, 'manual', null, 0, array(), get_current_user_id());
		backstage_outreach_business_message($saved ? __('Existing business added to this Source.', 'backstage-outreach') : __('The existing business could not be added.', 'backstage-outreach'), $saved ? 'info' : 'error');
		backstage_outreach_business_redirect(array('source_id' => $source_id));
	}
	$payload = backstage_outreach_business_payload((array) wp_unslash($_POST));
	if (is_wp_error($payload)) {
		backstage_outreach_business_message($payload->get_error_message(), 'error');
		backstage_outreach_business_redirect(array('source_id' => $source_id));
	}
	global $wpdb;
	$user_id = get_current_user_id();
	if ($business_id > 0) {
		$business = backstage_outreach_get_business($business_id);
		if (!$business || !backstage_outreach_business_belongs_to_source($business_id, $source_id)) {
			backstage_outreach_business_message(__('Business not found.', 'backstage-outreach'), 'error');
			backstage_outreach_business_redirect(array('source_id' => $source_id));
		}
		$payload['updated_by'] = $user_id;
		$payload['updated_at'] = backstage_outreach_business_now();
		$saved = $wpdb->update(backstage_outreach_business_table('businesses'), $payload, array('id' => $business_id)) !== false;
	} else {
		$started = $wpdb->query('START TRANSACTION') !== false;
		$business_id = $started ? backstage_outreach_insert_business($payload, $user_id) : 0;
		$saved = $business_id > 0 && backstage_outreach_business_upsert_membership($source_id, $business_id, 'manual', null, 0, array(), $user_id);
		$committed = $saved && $wpdb->query('COMMIT') !== false;
		if (!$committed) {
			$wpdb->query('ROLLBACK');
			$saved = false;
		}
	}
	backstage_outreach_business_message($saved ? __('Business saved.', 'backstage-outreach') : __('Business could not be saved.', 'backstage-outreach'), $saved ? 'info' : 'error');
	backstage_outreach_business_redirect(array('source_id' => $source_id));
}
add_action('admin_post_backstage_outreach_business_save', 'backstage_outreach_handle_business_save');

function backstage_outreach_handle_business_status(): void
{
	if (!current_user_can(vms_pass_claims_capability())) {
		wp_die(esc_html__('Access denied.', 'backstage-outreach'));
	}
	$business_id = backstage_outreach_request_absint($_REQUEST, 'business_id');
	$source_id = backstage_outreach_request_absint($_REQUEST, 'source_id');
	$status = sanitize_key(backstage_outreach_request_text($_REQUEST, 'status', 'inactive'));
	if (!in_array($status, array('active', 'inactive'), true)) {
		$status = 'inactive';
	}
	check_admin_referer('backstage_outreach_business_status_' . $business_id . '_' . $status);
	if (!backstage_outreach_business_belongs_to_source($business_id, $source_id)) {
		wp_die(esc_html__('Business does not belong to this Source.', 'backstage-outreach'));
	}
	global $wpdb;
	$updated = $wpdb->update(backstage_outreach_business_table('businesses'), array('status' => $status, 'updated_by' => get_current_user_id(), 'updated_at' => backstage_outreach_business_now()), array('id' => $business_id), array('%s', '%d', '%s'), array('%d'));
	if ($updated !== false && function_exists('bvmgr_admission_audit_log')) {
		bvmgr_admission_audit_log(0, null, 'outreach_business_status', get_current_user_id(), 'admin', array('business_id' => $business_id, 'source_id' => $source_id, 'status' => $status));
	}
	backstage_outreach_business_message($status === 'active' ? __('Business reactivated.', 'backstage-outreach') : __('Business deactivated. Historical attribution and existing customer credentials were preserved.', 'backstage-outreach'));
	backstage_outreach_business_redirect(array('source_id' => $source_id));
}
add_action('admin_post_backstage_outreach_business_status', 'backstage_outreach_handle_business_status');

function backstage_outreach_historical_recipients(int $source_id): array
{
	if (!function_exists('vms_admission_table_pass_outreach_campaigns') || !function_exists('vms_admission_table_pass_outreach_recipients')) {
		return array();
	}
	global $wpdb;
	$rows = $wpdb->get_results($wpdb->prepare(
		'SELECT r.*, c.campaign_name FROM %i r INNER JOIN %i c ON c.id = r.campaign_id WHERE c.related_source_id = %d ORDER BY r.id ASC',
		vms_admission_table_pass_outreach_recipients(), vms_admission_table_pass_outreach_campaigns(), $source_id
	), ARRAY_A);
	return is_array($rows) ? $rows : array();
}

function backstage_outreach_handle_seed_historical(): void
{
	if (!current_user_can(vms_pass_claims_capability())) {
		wp_die(esc_html__('Access denied.', 'backstage-outreach'));
	}
	check_admin_referer('backstage_outreach_seed_historical');
	$source_id = backstage_outreach_request_absint($_POST, 'source_id');
	$mode = sanitize_key(backstage_outreach_request_text($_POST, 'seed_mode', 'preview'));
	if (!bvmgr_pass_claims_get_source_by_id($source_id)) {
		backstage_outreach_business_message(__('Source not found.', 'backstage-outreach'), 'error');
		backstage_outreach_business_redirect(array('source_id' => $source_id));
	}
	$rows = backstage_outreach_historical_recipients($source_id);
	$duplicates = array();
	$seen = array();
	foreach ($rows as $row) {
		$key = strtolower(trim((string) ($row['company'] ?? ''))) . '|' . strtolower(trim((string) ($row['email_norm'] ?? $row['email'] ?? '')));
		if ($key !== '|' && isset($seen[$key])) {
			$duplicates[] = (int) ($row['id'] ?? 0);
		}
		$seen[$key] = true;
	}
	if ($mode !== 'commit') {
		set_transient('backstage_outreach_seed_preview_' . get_current_user_id(), array(
			'source_id' => $source_id,
			'rows' => count($rows),
			'recipient_ids' => array_map(static fn($row) => absint($row['id'] ?? 0), $rows),
			'rows_digest' => hash('sha256', (string) wp_json_encode($rows)),
			'duplicates' => $duplicates,
		), 10 * MINUTE_IN_SECONDS);
		backstage_outreach_business_redirect(array('source_id' => $source_id, 'seed_preview' => 1));
	}
	$preview = get_transient('backstage_outreach_seed_preview_' . get_current_user_id());
	if (!is_array($preview) || (int) ($preview['source_id'] ?? 0) !== $source_id) {
		backstage_outreach_business_message(__('Historical conversion preview expired. Preview the recipients again.', 'backstage-outreach'), 'error');
		backstage_outreach_business_redirect(array('source_id' => $source_id));
	}
	$preview_ids = array_values(array_filter(array_map('absint', (array) ($preview['recipient_ids'] ?? array()))));
	$current_ids = array_values(array_filter(array_map(static fn($row) => absint($row['id'] ?? 0), $rows)));
	if ($preview_ids !== $current_ids || !hash_equals((string) ($preview['rows_digest'] ?? ''), hash('sha256', (string) wp_json_encode($rows)))) {
		backstage_outreach_business_message(__('Historical recipients changed after preview. Review them again before committing.', 'backstage-outreach'), 'error');
		backstage_outreach_business_redirect(array('source_id' => $source_id));
	}
	global $wpdb;
	$created = 0;
	$skipped = 0;
	$ok = $wpdb->query('START TRANSACTION') !== false;
	if (!$ok) {
		backstage_outreach_business_message(__('Historical conversion could not start safely. No changes were made.', 'backstage-outreach'), 'error');
		backstage_outreach_business_redirect(array('source_id' => $source_id));
	}
	foreach ($rows as $row) {
		$recipient_id = absint($row['id'] ?? 0);
		$key = hash('sha256', 'recipient:' . $recipient_id);
		$exists = $wpdb->get_var($wpdb->prepare('SELECT id FROM %i WHERE source_id = %d AND provenance_key = %s', backstage_outreach_business_table('source_businesses'), $source_id, $key));
		if ($exists) {
			$skipped++;
			continue;
		}
		$name = sanitize_text_field((string) ($row['company'] ?? ''));
		if ($name === '') {
			$name = sanitize_text_field((string) ($row['full_name'] ?? trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''))));
		}
		if ($name === '') {
			$name = sprintf(__('Historical recipient #%d', 'backstage-outreach'), $recipient_id);
		}
		$business_id = backstage_outreach_insert_business(array(
			'business_name' => $name,
			'contact_name' => sanitize_text_field((string) ($row['full_name'] ?? '')),
			'email' => sanitize_email((string) ($row['email'] ?? '')),
			'phone' => sanitize_text_field((string) ($row['phone'] ?? '')),
			'website' => '', 'address_line' => '', 'city' => '', 'state' => '', 'postal_code' => '',
			'notes' => sprintf(__('Proposed from historical Outreach recipient #%d; review before campaign selection.', 'backstage-outreach'), $recipient_id),
		), get_current_user_id());
		if ($business_id > 0 && backstage_outreach_business_upsert_membership($source_id, $business_id, 'historical_recipient', $key, $recipient_id, $row, get_current_user_id())) {
			$created++;
		} else {
			$ok = false;
			break;
		}
	}
	$committed = $ok && $wpdb->query('COMMIT') !== false;
	if (!$committed) {
		$wpdb->query('ROLLBACK');
		backstage_outreach_business_message(__('Historical conversion could not be completed atomically. No new businesses were retained.', 'backstage-outreach'), 'error');
		backstage_outreach_business_redirect(array('source_id' => $source_id));
	}
	backstage_outreach_business_message(sprintf(__('Historical conversion complete: %1$d created, %2$d already mapped. Original recipient rows were not changed.', 'backstage-outreach'), $created, $skipped));
	delete_transient('backstage_outreach_seed_preview_' . get_current_user_id());
	backstage_outreach_business_redirect(array('source_id' => $source_id));
}
add_action('admin_post_backstage_outreach_seed_historical', 'backstage_outreach_handle_seed_historical');

function backstage_outreach_csv_rows(string $path)
{
	$handle = fopen($path, 'rb');
	if (!$handle) {
		return new WP_Error('csv_open_failed', __('The CSV could not be opened.', 'backstage-outreach'));
	}
	$raw_headers = fgetcsv($handle, 0, ',', '"', '\\');
	if (!is_array($raw_headers)) {
		fclose($handle);
		return new WP_Error('csv_header_missing', __('The CSV must contain a header row.', 'backstage-outreach'));
	}
	$aliases = array(
		'external_id' => 'external_id',
		'business_name' => 'business_name',
		'contact_name' => 'contact_name',
		'email' => 'email',
		'phone' => 'phone',
		'website' => 'website',
		'address' => 'address_line',
		'address_line' => 'address_line',
		'city' => 'city',
		'state' => 'state',
		'postal_code' => 'postal_code',
		'notes' => 'notes',
	);
	$headers = array();
	$mapped_from = array();
	$header_errors = array();
	foreach ($raw_headers as $index => $raw_header) {
		$label = preg_replace('/^\xEF\xBB\xBF/', '', (string) $raw_header);
		$label = sanitize_text_field(trim((string) $label));
		$normalized = sanitize_key($label);
		if ($normalized === '' || !isset($aliases[$normalized])) {
			$shown = $label !== '' ? $label : sprintf(__('Column %d', 'backstage-outreach'), $index + 1);
			$header_errors[] = sprintf(__('Unsupported CSV column "%s". Remove it before committing.', 'backstage-outreach'), $shown);
			continue;
		}
		$target = $aliases[$normalized];
		if (isset($mapped_from[$target])) {
			$header_errors[] = sprintf(__('CSV columns "%1$s" and "%2$s" both map to "%3$s". Keep only one of them.', 'backstage-outreach'), $mapped_from[$target], $label, $target);
			continue;
		}
		$headers[$index] = $target;
		$mapped_from[$target] = $label;
	}
	$rows = array();
	$row_number = 1;
	while (($values = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
		$row_number++;
		if (!array_filter($values, static fn($v) => trim((string) $v) !== '')) {
			continue;
		}
		$row = array();
		foreach ($headers as $index => $header) {
			$row[$header] = (string) ($values[$index] ?? '');
		}
		$rows[] = array('row_number' => $row_number, 'values' => $row);
		if (count($rows) > 1000) {
			fclose($handle);
			return new WP_Error('csv_row_limit', __('The CSV contains more than 1,000 non-empty rows. Split it into smaller reviewed imports.', 'backstage-outreach'));
		}
	}
	fclose($handle);
	return array('rows' => $rows, 'header_errors' => $header_errors);
}

function backstage_outreach_build_business_import_preview(int $source_id, array $parsed): array
{
	$valid = array();
	$records = array();
	$row_errors = array();
	$missing_email_count = 0;
	foreach ((array) ($parsed['rows'] ?? array()) as $parsed_row) {
		$row_number = max(2, absint($parsed_row['row_number'] ?? 0));
		$row = (array) ($parsed_row['values'] ?? array());
		$payload = backstage_outreach_business_sanitized_payload($row);
		$errors = array();
		if ($payload['business_name'] === '') {
			$errors[] = __('Business name is required.', 'backstage-outreach');
		}
		$raw_email = isset($row['email']) && is_scalar($row['email']) ? trim((string) $row['email']) : '';
		if ($raw_email !== '' && $payload['email'] === '') {
			$errors[] = __('Enter a valid email address or leave it blank.', 'backstage-outreach');
		}
		$missing_email = $raw_email === '';
		if ($missing_email) {
			$missing_email_count++;
		}
		$record = array(
			'row_number' => $row_number,
			'payload' => $payload,
			'external_id' => sanitize_text_field((string) ($row['external_id'] ?? '')),
			'snapshot' => $row,
			'errors' => $errors,
			'status' => empty($errors) ? 'valid' : 'invalid',
			'missing_email' => $missing_email,
		);
		$records[] = $record;
		if (!empty($errors)) {
			foreach ($errors as $error) {
				$row_errors[] = sprintf(__('Row %1$d: %2$s', 'backstage-outreach'), $row_number, $error);
			}
			continue;
		}
		$valid[] = array(
			'payload' => $payload,
			'external_id' => $record['external_id'],
			'snapshot' => $row,
			'row_number' => $row_number,
		);
	}
	$header_errors = array_values(array_map('strval', (array) ($parsed['header_errors'] ?? array())));
	$blocking_errors = array_merge($header_errors, $row_errors);
	return array(
		'preview_version' => 2,
		'source_id' => $source_id,
		'rows' => $valid,
		'records' => $records,
		'errors' => $row_errors,
		'header_errors' => $header_errors,
		'blocking_errors' => $blocking_errors,
		'valid_count' => count($valid),
		'invalid_count' => count($records) - count($valid),
		'missing_email_count' => $missing_email_count,
	);
}

function backstage_outreach_prepare_business_import_preview(int $source_id, string $key)
{
	// A replacement attempt owns this slot immediately; a failed upload must not leave an older preview committable.
	delete_transient($key);
	$upload = bvmgr_upload_read_file($_FILES, 'business_csv');
	if (!is_wp_error($upload)) {
		$upload = bvmgr_validate_uploaded_file($upload, array('allowed_mimes' => array('csv' => 'text/csv', 'txt' => 'text/plain'), 'max_bytes' => 2 * MB_IN_BYTES));
	}
	if (is_wp_error($upload)) {
		return $upload;
	}
	$parsed = backstage_outreach_csv_rows((string) $upload['tmp_name']);
	if (is_wp_error($parsed)) {
		return $parsed;
	}
	$preview = backstage_outreach_build_business_import_preview($source_id, $parsed);
	set_transient($key, $preview, 10 * MINUTE_IN_SECONDS);
	return $preview;
}

function backstage_outreach_business_import_commit_error(array $preview, int $source_id)
{
	if ((int) ($preview['preview_version'] ?? 0) !== 2 || (int) ($preview['source_id'] ?? 0) !== $source_id) {
		return new WP_Error('business_import_preview_expired', __('Import preview expired. Preview the CSV again.', 'backstage-outreach'));
	}
	$valid_count = count((array) ($preview['rows'] ?? array()));
	if ($valid_count <= 0) {
		return new WP_Error('business_import_no_valid_rows', __('The CSV has no valid rows to import. Correct it and preview the replacement file.', 'backstage-outreach'));
	}
	if (!empty($preview['blocking_errors']) || !empty($preview['errors']) || (int) ($preview['invalid_count'] ?? 0) > 0) {
		return new WP_Error('business_import_blocked', __('The CSV still has blocking header or row errors. Correct them and preview the replacement file before committing.', 'backstage-outreach'));
	}
	return null;
}

function backstage_outreach_handle_business_import(): void
{
	if (!current_user_can(vms_pass_claims_capability())) {
		wp_die(esc_html__('Access denied.', 'backstage-outreach'));
	}
	check_admin_referer('backstage_outreach_business_import');
	$source_id = backstage_outreach_request_absint($_POST, 'source_id');
	$mode = sanitize_key(backstage_outreach_request_text($_POST, 'import_mode', 'preview'));
	if (!bvmgr_pass_claims_get_source_by_id($source_id)) {
		backstage_outreach_business_message(__('Source not found.', 'backstage-outreach'), 'error');
		backstage_outreach_business_redirect(array('source_id' => $source_id));
	}
	$key = 'backstage_outreach_business_import_' . get_current_user_id();
	if ($mode === 'preview') {
		$preview = backstage_outreach_prepare_business_import_preview($source_id, $key);
		if (is_wp_error($preview)) {
			backstage_outreach_business_message($preview->get_error_message(), 'error');
		}
		backstage_outreach_business_redirect(array('source_id' => $source_id, 'import_preview' => 1));
	}
	$preview = get_transient($key);
	$commit_error = is_array($preview) ? backstage_outreach_business_import_commit_error($preview, $source_id) : new WP_Error('business_import_preview_expired', __('Import preview expired. Preview the CSV again.', 'backstage-outreach'));
	if (is_wp_error($commit_error)) {
		backstage_outreach_business_message($commit_error->get_error_message(), 'error');
		backstage_outreach_business_redirect(array('source_id' => $source_id));
	}
	global $wpdb;
	$created = 0;
	$skipped = 0;
	$ok = $wpdb->query('START TRANSACTION') !== false;
	if (!$ok) {
		backstage_outreach_business_message(__('CSV import could not start safely. No changes were made.', 'backstage-outreach'), 'error');
		backstage_outreach_business_redirect(array('source_id' => $source_id));
	}
	foreach ((array) $preview['rows'] as $row) {
		$snapshot = (array) ($row['snapshot'] ?? array());
		$external = (string) ($row['external_id'] ?? '');
		$provenance = hash('sha256', 'csv:' . ($external !== '' ? $external : wp_json_encode($snapshot)));
		$exists = $wpdb->get_var($wpdb->prepare('SELECT id FROM %i WHERE source_id = %d AND provenance_key = %s', backstage_outreach_business_table('source_businesses'), $source_id, $provenance));
		if ($exists) {
			$skipped++;
			continue;
		}
		$business_id = backstage_outreach_insert_business((array) $row['payload'], get_current_user_id());
		if ($business_id > 0 && backstage_outreach_business_upsert_membership($source_id, $business_id, 'csv_import', $provenance, 0, $snapshot, get_current_user_id())) {
			$created++;
		} else {
			$ok = false;
			break;
		}
	}
	$committed = $ok && $wpdb->query('COMMIT') !== false;
	if (!$committed) {
		$wpdb->query('ROLLBACK');
		backstage_outreach_business_message(__('CSV import could not be completed atomically. No new businesses were retained.', 'backstage-outreach'), 'error');
		backstage_outreach_business_redirect(array('source_id' => $source_id));
	}
	delete_transient($key);
	backstage_outreach_business_message(sprintf(__('CSV import complete: %1$d created, %2$d idempotent matches skipped.', 'backstage-outreach'), $created, $skipped));
	backstage_outreach_business_redirect(array('source_id' => $source_id));
}
add_action('admin_post_backstage_outreach_business_import', 'backstage_outreach_handle_business_import');

function backstage_outreach_business_activity(int $business_id): array
{
	global $wpdb;
	$row = $wpdb->get_row($wpdb->prepare(
		"SELECT COUNT(DISTINCT dc.pass_claim_id) claims,
		COUNT(DISTINCT e.id) admissions,
		COALESCE(SUM(e.checked_in_qty),0) checked_in
		FROM %i dc LEFT JOIN %i e ON e.pass_claim_id = dc.pass_claim_id
		WHERE dc.business_id = %d AND dc.status = 'fulfilled'",
		backstage_outreach_business_table('distribution_claims'), bvmgr_admission_table_entries(), $business_id
	), ARRAY_A);
	return is_array($row) ? $row : array('claims' => 0, 'admissions' => 0, 'checked_in' => 0);
}

function backstage_outreach_business_import_value_html(string $value): string
{
	return $value === '' ? '<span class="vms-business-import-not-provided">' . esc_html__('Not provided', 'backstage-outreach') . '</span>' : esc_html($value);
}

function backstage_outreach_render_business_import_preview(array $preview, int $source_id): void
{
	$records = (array) ($preview['records'] ?? array());
	$valid_count = count((array) ($preview['rows'] ?? array()));
	$invalid_count = count(array_filter($records, static fn($record) => (string) ($record['status'] ?? '') !== 'valid'));
	$missing_email_count = count(array_filter($records, static fn($record) => !empty($record['missing_email'])));
	$commit_error = backstage_outreach_business_import_commit_error($preview, $source_id);

	echo '<div class="vms-business-import-review" data-vms-business-import-review>';
	echo '<p class="vms-business-import-review__summary"><strong>' . esc_html(sprintf(__('%1$d valid · %2$d invalid · %3$d missing email', 'backstage-outreach'), $valid_count, $invalid_count, $missing_email_count)) . '</strong></p>';
	if (!empty($preview['header_errors'])) {
		echo '<div class="notice notice-error inline"><p><strong>' . esc_html__('Unsupported or ambiguous CSV columns must be corrected before import.', 'backstage-outreach') . '</strong></p><ul class="ul-disc">';
		foreach ((array) $preview['header_errors'] as $error) {
			echo '<li>' . esc_html((string) $error) . '</li>';
		}
		echo '</ul></div>';
	}

	echo '<div class="vms-business-import-review__table-wrap" tabindex="0" aria-label="' . esc_attr__('Business import records', 'backstage-outreach') . '"><table class="widefat striped vms-business-import-review__table"><thead><tr>';
	foreach (array(
		__('CSV Row', 'backstage-outreach'),
		__('Business Name', 'backstage-outreach'),
		__('Contact Name', 'backstage-outreach'),
		__('Email', 'backstage-outreach'),
		__('Phone', 'backstage-outreach'),
		__('Website', 'backstage-outreach'),
		__('Full Address', 'backstage-outreach'),
		__('Validation Status', 'backstage-outreach'),
	) as $heading) {
		echo '<th scope="col">' . esc_html($heading) . '</th>';
	}
	echo '</tr></thead><tbody>';
	foreach ($records as $record) {
		$payload = (array) ($record['payload'] ?? array());
		$status = (string) ($record['status'] ?? '') === 'valid' ? 'valid' : 'invalid';
		$address_lines = array();
		$address_line = (string) ($payload['address_line'] ?? '');
		if ($address_line !== '') {
			$address_lines[] = $address_line;
		}
		$locality = trim(implode(' ', array_filter(array(
			(string) ($payload['city'] ?? ''),
			(string) ($payload['state'] ?? ''),
			(string) ($payload['postal_code'] ?? ''),
		), static fn($value) => $value !== '')));
		if ($locality !== '') {
			$address_lines[] = $locality;
		}
		$address_html = empty($address_lines)
			? backstage_outreach_business_import_value_html('')
			: implode('<br>', array_map('esc_html', $address_lines));
		$email_html = backstage_outreach_business_import_value_html((string) ($payload['email'] ?? ''));
		if (!empty($record['missing_email'])) {
			$email_html .= '<span class="vms-business-import-email-note">' . esc_html__('Email delivery unavailable', 'backstage-outreach') . '</span>';
		}
		$status_html = '<strong class="vms-business-import-status vms-business-import-status--' . esc_attr($status) . '">' . esc_html($status === 'valid' ? __('Valid', 'backstage-outreach') : __('Invalid', 'backstage-outreach')) . '</strong>';
		if (!empty($record['errors'])) {
			$status_html .= '<ul class="ul-disc">';
			foreach ((array) $record['errors'] as $error) {
				$status_html .= '<li>' . esc_html((string) $error) . '</li>';
			}
			$status_html .= '</ul>';
		} elseif (!empty($record['missing_email'])) {
			$status_html .= '<span class="vms-business-import-status__note">' . esc_html__('Valid; email delivery is unavailable.', 'backstage-outreach') . '</span>';
		}
		echo '<tr class="vms-business-import-record vms-business-import-record--' . esc_attr($status) . '">';
		echo '<th scope="row" data-label="' . esc_attr__('CSV Row', 'backstage-outreach') . '">' . esc_html((string) absint($record['row_number'] ?? 0)) . '</th>';
		echo '<td data-label="' . esc_attr__('Business Name', 'backstage-outreach') . '">' . backstage_outreach_business_import_value_html((string) ($payload['business_name'] ?? '')) . '</td>';
		echo '<td data-label="' . esc_attr__('Contact Name', 'backstage-outreach') . '">' . backstage_outreach_business_import_value_html((string) ($payload['contact_name'] ?? '')) . '</td>';
		echo '<td data-label="' . esc_attr__('Email', 'backstage-outreach') . '">' . $email_html . '</td>';
		echo '<td data-label="' . esc_attr__('Phone', 'backstage-outreach') . '">' . backstage_outreach_business_import_value_html((string) ($payload['phone'] ?? '')) . '</td>';
		echo '<td data-label="' . esc_attr__('Website', 'backstage-outreach') . '">' . backstage_outreach_business_import_value_html((string) ($payload['website'] ?? '')) . '</td>';
		echo '<td data-label="' . esc_attr__('Full Address', 'backstage-outreach') . '">' . $address_html . '</td>';
		echo '<td data-label="' . esc_attr__('Validation Status', 'backstage-outreach') . '">' . $status_html . '</td>';
		echo '</tr>';
		$notes = (string) ($payload['notes'] ?? '');
		echo '<tr class="vms-business-import-record-notes"><td colspan="8"><details><summary>' . esc_html(sprintf(__('Notes for CSV row %d', 'backstage-outreach'), absint($record['row_number'] ?? 0))) . '</summary><div>' . ($notes === '' ? backstage_outreach_business_import_value_html('') : nl2br(esc_html($notes))) . '</div></details></td></tr>';
	}
	if (empty($records)) {
		echo '<tr><td colspan="8">' . esc_html__('No non-empty business records were found in this CSV.', 'backstage-outreach') . '</td></tr>';
	}
	echo '</tbody></table></div>';

	echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-business-import-review__commit"><input type="hidden" name="action" value="backstage_outreach_business_import"><input type="hidden" name="source_id" value="' . esc_attr((string) $source_id) . '"><input type="hidden" name="import_mode" value="commit">';
	wp_nonce_field('backstage_outreach_business_import');
	if (is_wp_error($commit_error)) {
		echo '<p class="description vms-business-import-review__blocked">' . esc_html($commit_error->get_error_message()) . '</p>';
	}
	echo '<button class="button button-primary"' . (is_wp_error($commit_error) ? ' disabled aria-disabled="true"' : '') . '>' . esc_html__('Commit CSV Import', 'backstage-outreach') . '</button></form></div>';
}

function backstage_outreach_render_source_management(): void
{
	$source_id = backstage_outreach_request_absint($_GET, 'source_id');
	$edit_business_id = backstage_outreach_request_absint($_GET, 'business_id');
	$sources = bvmgr_pass_claims_get_sources(true);
	if ($source_id <= 0) {
		echo '<section class="vms-pass-card"><h2>' . esc_html__('Sources', 'backstage-outreach') . '</h2>';
		echo '<p class="description">' . esc_html__('Open a Source to manage its reusable businesses, historical recipients, campaigns, and activity.', 'backstage-outreach') . '</p>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Name', 'backstage-outreach') . '</th><th>' . esc_html__('Businesses', 'backstage-outreach') . '</th><th>' . esc_html__('Status', 'backstage-outreach') . '</th></tr></thead><tbody>';
		foreach ($sources as $source) {
			$id = absint($source['id'] ?? 0);
			$count = count(backstage_outreach_source_businesses($id, true));
			echo '<tr><td><a href="' . esc_url(backstage_outreach_business_page_url(array('source_id' => $id))) . '"><strong>' . esc_html((string) ($source['source_name'] ?? '')) . '</strong></a><div class="description">#' . esc_html((string) $id) . '</div></td><td>' . esc_html((string) $count) . '</td><td>' . esc_html((string) ($source['status'] ?? '')) . '</td></tr>';
		}
		if (empty($sources)) {
			echo '<tr><td colspan="3">' . esc_html__('No Sources yet.', 'backstage-outreach') . '</td></tr>';
		}
		echo '</tbody></table></section>';
		echo '<section class="vms-pass-card"><h2>' . esc_html__('Create Source', 'backstage-outreach') . '</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-pass-form"><input type="hidden" name="action" value="vms_pass_source_save">';
		wp_nonce_field('bvmgr_pass_source_save');
		echo '<div class="vms-pass-grid"><label>' . esc_html__('Source Name', 'backstage-outreach') . '<input name="source_name" required></label><label>' . esc_html__('Contact Name', 'backstage-outreach') . '<input name="contact_name"></label><label>' . esc_html__('Phone', 'backstage-outreach') . '<input name="phone"></label><label>' . esc_html__('Email', 'backstage-outreach') . '<input type="email" name="email"></label><label class="vms-pass-span-2">' . esc_html__('Notes', 'backstage-outreach') . '<textarea name="notes"></textarea></label></div><p><button class="button button-primary">' . esc_html__('Save Source', 'backstage-outreach') . '</button></p></form></section>';
		return;
	}
	$source = bvmgr_pass_claims_get_source_by_id($source_id);
	if (!$source) {
		echo '<div class="notice notice-error"><p>' . esc_html__('Source not found.', 'backstage-outreach') . '</p></div>';
		return;
	}
	$businesses = backstage_outreach_source_businesses($source_id, true);
	$reusable = array_values(array_filter(backstage_outreach_all_businesses(false), static fn($business) => !backstage_outreach_business_belongs_to_source((int) ($business['id'] ?? 0), $source_id)));
	$historical = backstage_outreach_historical_recipients($source_id);
	$edit = $edit_business_id > 0 && backstage_outreach_business_belongs_to_source($edit_business_id, $source_id) ? backstage_outreach_get_business($edit_business_id) : null;
	if ($edit_business_id > 0 && !$edit) {
		echo '<div class="notice notice-error"><p>' . esc_html__('That business does not belong to this Source.', 'backstage-outreach') . '</p></div>';
		$edit_business_id = 0;
	}
	echo '<p><a href="' . esc_url(backstage_outreach_business_page_url()) . '">&larr; ' . esc_html__('All Sources', 'backstage-outreach') . '</a></p>';
	$source_contact = array_filter(array((string) ($source['contact_name'] ?? ''), (string) ($source['phone'] ?? ''), (string) ($source['email'] ?? '')), static fn($value) => $value !== '');
	echo '<section class="vms-pass-card"><h2>' . esc_html((string) $source['source_name']) . '</h2><p class="description">' . esc_html(sprintf(__('%1$d reusable businesses · %2$d historical recipient snapshots', 'backstage-outreach'), count($businesses), count($historical))) . '</p>';
	if (!empty($source_contact)) { echo '<p><strong>' . esc_html__('Source contact:', 'backstage-outreach') . '</strong> ' . esc_html(implode(' · ', $source_contact)) . '</p>'; }
	if (!empty($source['notes'])) { echo '<p>' . nl2br(esc_html((string) $source['notes'])) . '</p>'; }
	echo '</section>';
	if (!empty($reusable)) {
		echo '<section class="vms-pass-card"><h3>' . esc_html__('Reuse an Existing Business', 'backstage-outreach') . '</h3><p class="description">' . esc_html__('Add one stable business identity to this Source without duplicating its record or changing its other Source memberships.', 'backstage-outreach') . '</p><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="backstage_outreach_business_save"><input type="hidden" name="source_id" value="' . esc_attr((string) $source_id) . '"><select name="reuse_business_id" required><option value="">' . esc_html__('Choose a business', 'backstage-outreach') . '</option>';
		foreach ($reusable as $candidate) {
			echo '<option value="' . esc_attr((string) ((int) $candidate['id'])) . '">' . esc_html((string) $candidate['business_name']) . '</option>';
		}
		echo '</select> ';
		wp_nonce_field('backstage_outreach_business_save');
		echo '<button class="button">' . esc_html__('Add to Source', 'backstage-outreach') . '</button></form></section>';
	}
	global $wpdb;
	$campaigns = $wpdb->get_results($wpdb->prepare('SELECT c.*, COALESCE(ds.partner_claims,0) partner_claims, COALESCE(ds.partner_admissions,0) partner_admissions FROM %i c LEFT JOIN (SELECT campaign_id, COUNT(DISTINCT pass_claim_id) partner_claims, COALESCE(SUM(party_size),0) partner_admissions FROM %i WHERE status=%s GROUP BY campaign_id) ds ON ds.campaign_id=c.id WHERE c.related_source_id=%d ORDER BY c.id DESC', vms_admission_table_pass_outreach_campaigns(), backstage_outreach_business_table('distribution_claims'), 'fulfilled', $source_id), ARRAY_A);
	echo '<section class="vms-pass-card"><h3>' . esc_html__('Linked Campaigns', 'backstage-outreach') . '</h3><table class="widefat striped"><thead><tr><th>' . esc_html__('Campaign', 'backstage-outreach') . '</th><th>' . esc_html__('Status', 'backstage-outreach') . '</th><th>' . esc_html__('Partner Claims', 'backstage-outreach') . '</th><th>' . esc_html__('Reserved Admissions', 'backstage-outreach') . '</th></tr></thead><tbody>';
	foreach ((array) $campaigns as $campaign_row) {
		echo '<tr><td><a href="' . esc_url(vms_pass_outreach_admin_page_url(array('campaign_id' => (int) $campaign_row['id']))) . '">' . esc_html((string) $campaign_row['campaign_name']) . '</a></td><td>' . esc_html((string) $campaign_row['status']) . '</td><td>' . esc_html((string) ((int) $campaign_row['partner_claims'])) . '</td><td>' . esc_html((string) ((int) $campaign_row['partner_admissions'])) . '</td></tr>';
	}
	if (empty($campaigns)) { echo '<tr><td colspan="4">' . esc_html__('No linked campaigns.', 'backstage-outreach') . '</td></tr>'; }
	echo '</tbody></table></section>';

	$preview = get_transient('backstage_outreach_seed_preview_' . get_current_user_id());
	if (is_array($preview) && (int) ($preview['source_id'] ?? 0) === $source_id) {
		echo '<section class="vms-pass-card"><h3>' . esc_html__('Historical conversion preview', 'backstage-outreach') . '</h3><p>' . esc_html(sprintf(__('%1$d recipient snapshots are available; %2$d possible duplicate name/email pairs require later review. Commit creates separate stable businesses and never changes recipient history.', 'backstage-outreach'), (int) $preview['rows'], count((array) $preview['duplicates']))) . '</p><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="backstage_outreach_seed_historical"><input type="hidden" name="source_id" value="' . esc_attr((string) $source_id) . '"><input type="hidden" name="seed_mode" value="commit">';
		if (!empty($preview['duplicates'])) { echo '<p class="description">' . esc_html(sprintf(__('Possible duplicate recipient IDs: %s', 'backstage-outreach'), implode(', ', array_map('absint', (array) $preview['duplicates'])))) . '</p>'; }
		wp_nonce_field('backstage_outreach_seed_historical');
		echo '<button class="button button-primary">' . esc_html__('Commit Historical Membership', 'backstage-outreach') . '</button></form></section>';
	}

	echo '<section class="vms-pass-card"><h3>' . esc_html($edit ? __('Edit Business', 'backstage-outreach') : __('Add Business', 'backstage-outreach')) . '</h3><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-pass-form"><input type="hidden" name="action" value="backstage_outreach_business_save"><input type="hidden" name="source_id" value="' . esc_attr((string) $source_id) . '"><input type="hidden" name="business_id" value="' . esc_attr((string) $edit_business_id) . '">';
	wp_nonce_field('backstage_outreach_business_save');
	$fields = array('business_name' => __('Business Name', 'backstage-outreach'), 'contact_name' => __('Contact Name', 'backstage-outreach'), 'email' => __('Email (optional)', 'backstage-outreach'), 'phone' => __('Phone', 'backstage-outreach'), 'website' => __('Website', 'backstage-outreach'), 'address_line' => __('Address', 'backstage-outreach'), 'city' => __('City', 'backstage-outreach'), 'state' => __('State', 'backstage-outreach'), 'postal_code' => __('Postal Code', 'backstage-outreach'));
	echo '<div class="vms-pass-grid">';
	foreach ($fields as $key => $label) {
		$type = $key === 'email' ? 'email' : ($key === 'website' ? 'url' : 'text');
		echo '<label>' . esc_html($label) . '<input type="' . esc_attr($type) . '" name="' . esc_attr($key) . '" value="' . esc_attr((string) ($edit[$key] ?? '')) . '"' . ($key === 'business_name' ? ' required' : '') . '></label>';
	}
	echo '<label class="vms-pass-span-2">' . esc_html__('Notes', 'backstage-outreach') . '<textarea name="notes">' . esc_textarea((string) ($edit['notes'] ?? '')) . '</textarea></label></div><p><button class="button button-primary">' . esc_html__('Save Business', 'backstage-outreach') . '</button></p></form></section>';

	echo '<section class="vms-pass-card"><h3>' . esc_html__('Import Businesses', 'backstage-outreach') . '</h3><p class="description">' . esc_html__('Preview is required. Supported headers: external_id, business_name, contact_name, email, phone, website, address_line (or address), city, state, postal_code, notes. Every column must be supported, and aliases cannot be duplicated.', 'backstage-outreach') . '</p><form method="post" enctype="multipart/form-data" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="backstage_outreach_business_import"><input type="hidden" name="source_id" value="' . esc_attr((string) $source_id) . '"><input type="hidden" name="import_mode" value="preview">';
	wp_nonce_field('backstage_outreach_business_import');
	echo '<input type="file" name="business_csv" accept=".csv,text/csv" required> <button class="button">' . esc_html__('Preview CSV', 'backstage-outreach') . '</button></form>';
	$import = get_transient('backstage_outreach_business_import_' . get_current_user_id());
	if (is_array($import) && (int) ($import['source_id'] ?? 0) === $source_id) {
		backstage_outreach_render_business_import_preview($import, $source_id);
	}
	echo '</section>';

	echo '<section class="vms-pass-card"><h3>' . esc_html__('Reusable Businesses', 'backstage-outreach') . '</h3><table class="widefat striped"><thead><tr><th>' . esc_html__('Business', 'backstage-outreach') . '</th><th>' . esc_html__('Contact', 'backstage-outreach') . '</th><th>' . esc_html__('Membership', 'backstage-outreach') . '</th><th>' . esc_html__('Claims / Admissions / Check-ins', 'backstage-outreach') . '</th><th>' . esc_html__('Actions', 'backstage-outreach') . '</th></tr></thead><tbody>';
	foreach ($businesses as $business) {
		$id = (int) $business['id'];
		$activity = backstage_outreach_business_activity($id);
		$target = (string) ($business['status'] ?? '') === 'active' ? 'inactive' : 'active';
		$status_url = wp_nonce_url(add_query_arg(array('action' => 'backstage_outreach_business_status', 'source_id' => $source_id, 'business_id' => $id, 'status' => $target), admin_url('admin-post.php')), 'backstage_outreach_business_status_' . $id . '_' . $target);
		echo '<tr><td><strong>' . esc_html((string) $business['business_name']) . '</strong><div class="description">' . esc_html((string) $business['status']) . '</div></td><td>' . esc_html(trim((string) $business['contact_name'] . ' ' . (string) $business['email'] . ' ' . (string) $business['phone'])) . '</td><td>' . esc_html((string) $business['membership_status']) . ' · ' . esc_html((string) $business['provenance_type']) . '</td><td>' . esc_html((string) ((int) $activity['claims'])) . ' / ' . esc_html((string) ((int) $activity['admissions'])) . ' / ' . esc_html((string) ((int) $activity['checked_in'])) . '</td><td><a class="button button-small" href="' . esc_url(backstage_outreach_business_page_url(array('source_id' => $source_id, 'business_id' => $id))) . '">' . esc_html__('Edit', 'backstage-outreach') . '</a> <a class="button button-small" href="' . esc_url($status_url) . '">' . esc_html($target === 'active' ? __('Reactivate', 'backstage-outreach') : __('Deactivate', 'backstage-outreach')) . '</a></td></tr>';
	}
	if (empty($businesses)) {
		echo '<tr><td colspan="5">' . esc_html__('No reusable businesses yet.', 'backstage-outreach') . '</td></tr>';
	}
	echo '</tbody></table></section>';

	echo '<section class="vms-pass-card"><h3>' . esc_html__('Historical Recipients', 'backstage-outreach') . '</h3><p class="description">' . esc_html__('Snapshots remain unchanged even when current business records are edited.', 'backstage-outreach') . '</p><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="backstage_outreach_seed_historical"><input type="hidden" name="source_id" value="' . esc_attr((string) $source_id) . '"><input type="hidden" name="seed_mode" value="preview">';
	wp_nonce_field('backstage_outreach_seed_historical');
	echo '<button class="button">' . esc_html__('Preview Membership Conversion', 'backstage-outreach') . '</button></form><table class="widefat striped"><thead><tr><th>' . esc_html__('Campaign', 'backstage-outreach') . '</th><th>' . esc_html__('Original Business / Recipient', 'backstage-outreach') . '</th><th>' . esc_html__('Delivery', 'backstage-outreach') . '</th><th>' . esc_html__('Claim', 'backstage-outreach') . '</th></tr></thead><tbody>';
	foreach ($historical as $row) {
		echo '<tr><td>' . esc_html((string) $row['campaign_name']) . '</td><td>' . esc_html((string) ($row['company'] ?: $row['full_name'])) . '<div class="description">#' . esc_html((string) $row['id']) . ' · ' . esc_html((string) $row['email']) . '</div></td><td>' . esc_html((string) $row['send_status']) . '</td><td>' . esc_html((string) $row['status']) . '</td></tr>';
	}
	if (empty($historical)) {
		echo '<tr><td colspan="4">' . esc_html__('No historical recipients are linked to this Source.', 'backstage-outreach') . '</td></tr>';
	}
	echo '</tbody></table></section>';
}

function backstage_outreach_distribution_signature(array $row): string
{
	$payload = (int) ($row['id'] ?? 0) . '|' . (int) ($row['campaign_id'] ?? 0) . '|' . (int) ($row['business_id'] ?? 0) . '|' . (string) ($row['public_key'] ?? '') . '|' . (string) ($row['created_at'] ?? '');
	return hash_hmac('sha256', $payload, wp_salt('auth'));
}

function backstage_outreach_distribution_token(array $row): string
{
	$key = sanitize_key((string) ($row['public_key'] ?? ''));
	return $key === '' ? '' : $key . '.' . backstage_outreach_distribution_signature($row);
}

function backstage_outreach_distribution_url(array $row): string
{
	return home_url('/guest-pass/partner/' . rawurlencode(backstage_outreach_distribution_token($row)));
}

function backstage_outreach_distribution_flyer_url(array $row): string
{
	return home_url('/guest-pass/business-flyer/' . rawurlencode(backstage_outreach_distribution_token($row)));
}

function backstage_outreach_flyer_design_option_key(int $campaign_id = 0): string
{
	return $campaign_id > 0
		? 'backstage_outreach_flyer_design_campaign_' . $campaign_id
		: 'backstage_outreach_flyer_design_default';
}

function backstage_outreach_flyer_branding(): array
{
	$branding = function_exists('bvmgr_pass_claims_print_branding') ? bvmgr_pass_claims_print_branding() : array();
	$site_name = sanitize_text_field((string) ($branding['site_name'] ?? get_bloginfo('name')));
	if ($site_name === '') {
		$site_name = __('Live Music Venue', 'backstage-outreach');
	}
	return array(
		'site_name' => $site_name,
		'logo_url' => esc_url_raw((string) ($branding['logo_url'] ?? '')),
		'home_url' => esc_url_raw(home_url('/')),
	);
}

function backstage_outreach_flyer_design_attachment_url(int $attachment_id): string
{
	if ($attachment_id <= 0 || !function_exists('wp_attachment_is_image') || !wp_attachment_is_image($attachment_id)) {
		return '';
	}
	$url = function_exists('wp_get_attachment_image_url') ? wp_get_attachment_image_url($attachment_id, 'full') : '';
	return is_string($url) ? esc_url_raw($url) : '';
}

function backstage_outreach_flyer_event_artwork_id(array $batch): int
{
	if (sanitize_key((string) ($batch['validity_type'] ?? '')) !== 'single_event') {
		return 0;
	}
	$event_plan_id = absint($batch['single_event_plan_id'] ?? 0);
	if ($event_plan_id <= 0) {
		return 0;
	}
	$attachment_id = function_exists('bvmgr_ticketing_v2_resolve_event_featured_image_id')
		? absint(bvmgr_ticketing_v2_resolve_event_featured_image_id($event_plan_id))
		: 0;
	if ($attachment_id <= 0 && function_exists('get_post_thumbnail_id')) {
		$attachment_id = absint(get_post_thumbnail_id($event_plan_id));
		$tec_event_id = absint(get_post_meta($event_plan_id, '_vms_tec_event_id', true));
		if ($attachment_id <= 0 && $tec_event_id > 0) {
			$attachment_id = absint(get_post_thumbnail_id($tec_event_id));
		}
	}
	return backstage_outreach_flyer_design_attachment_url($attachment_id) !== '' ? $attachment_id : 0;
}

function backstage_outreach_flyer_design_choice($value, array $allowed, string $fallback): string
{
	$value = sanitize_key((string) $value);
	return in_array($value, $allowed, true) ? $value : $fallback;
}

function backstage_outreach_flyer_design_boolean(array $stored, string $key, bool $fallback): bool
{
	return array_key_exists($key, $stored) ? !empty($stored[$key]) : $fallback;
}

function backstage_outreach_normalize_flyer_layout(string $orientation, string $composition, string $panel_position): array
{
	$orientation = backstage_outreach_flyer_design_choice($orientation, array('portrait', 'landscape'), 'portrait');
	$composition = backstage_outreach_flyer_design_choice($composition, array('full', 'panels'), 'panels');
	$panel_position = 'bottom';
	return array(
		'orientation' => $orientation,
		'composition' => $composition,
		'panel_position' => $panel_position,
	);
}

function backstage_outreach_flyer_default_design(): array
{
	$branding = backstage_outreach_flyer_branding();
	$stored = get_option(backstage_outreach_flyer_design_option_key(), array());
	$stored = is_array($stored) ? $stored : array();
	$heading = sanitize_text_field((string) ($stored['heading'] ?? ''));
	$subheading = sanitize_text_field((string) ($stored['subheading'] ?? ''));
	$layout = backstage_outreach_normalize_flyer_layout(
		(string) ($stored['orientation'] ?? ''),
		(string) ($stored['composition'] ?? ''),
		(string) ($stored['panel_position'] ?? '')
	);
	return array(
		'heading' => $heading !== '' ? $heading : sprintf(__('Live music at %s', 'backstage-outreach'), $branding['site_name']),
		'subheading' => $subheading !== '' ? $subheading : __('Concerts, community, and a special admission offer.', 'backstage-outreach'),
		'artwork_id' => absint($stored['artwork_id'] ?? 0),
		'orientation' => $layout['orientation'],
		'composition' => $layout['composition'],
		'panel_position' => $layout['panel_position'],
		'show_heading' => backstage_outreach_flyer_design_boolean($stored, 'show_heading', true),
		'show_subheading' => backstage_outreach_flyer_design_boolean($stored, 'show_subheading', true),
		'show_logo' => backstage_outreach_flyer_design_boolean($stored, 'show_logo', true),
	);
}

function backstage_outreach_flyer_campaign_design(int $campaign_id): array
{
	$stored = get_option(backstage_outreach_flyer_design_option_key($campaign_id), array());
	$stored = is_array($stored) ? $stored : array();
	$mode = sanitize_key((string) ($stored['artwork_mode'] ?? 'inherit'));
	if (!in_array($mode, array('inherit', 'custom', 'none'), true)) {
		$mode = 'inherit';
	}
	$layout_mode = backstage_outreach_flyer_design_choice($stored['layout_mode'] ?? '', array('inherit', 'custom'), 'inherit');
	$layout = backstage_outreach_normalize_flyer_layout(
		(string) ($stored['orientation'] ?? ''),
		(string) ($stored['composition'] ?? ''),
		(string) ($stored['panel_position'] ?? '')
	);
	return array(
		'heading' => sanitize_text_field((string) ($stored['heading'] ?? '')),
		'subheading' => sanitize_text_field((string) ($stored['subheading'] ?? '')),
		'artwork_mode' => $mode,
		'artwork_id' => absint($stored['artwork_id'] ?? 0),
		'layout_mode' => $layout_mode,
		'orientation' => $layout['orientation'],
		'composition' => $layout['composition'],
		'panel_position' => $layout['panel_position'],
		'show_heading' => backstage_outreach_flyer_design_boolean($stored, 'show_heading', true),
		'show_subheading' => backstage_outreach_flyer_design_boolean($stored, 'show_subheading', true),
		'show_logo' => backstage_outreach_flyer_design_boolean($stored, 'show_logo', true),
	);
}

function backstage_outreach_resolved_flyer_design(int $campaign_id, ?array $batch = null): array
{
	$defaults = backstage_outreach_flyer_default_design();
	$campaign = backstage_outreach_flyer_campaign_design($campaign_id);
	if ($batch === null && $campaign_id > 0 && function_exists('vms_pass_outreach_get_campaign_by_id') && function_exists('bvmgr_pass_claims_get_batch_by_id')) {
		$campaign_row = vms_pass_outreach_get_campaign_by_id($campaign_id);
		$batch = is_array($campaign_row) ? bvmgr_pass_claims_get_batch_by_id(absint($campaign_row['related_batch_id'] ?? 0)) : null;
	}
	$batch = is_array($batch) ? $batch : array();
	$artwork_id = 0;
	$artwork_source = 'fallback';
	$artwork_source_label = __('Branded no-artwork fallback', 'backstage-outreach');
	if ($campaign['artwork_mode'] === 'custom') {
		$artwork_id = (int) $campaign['artwork_id'];
		$artwork_source = 'campaign';
		$artwork_source_label = __('Campaign artwork override', 'backstage-outreach');
	} elseif ($campaign['artwork_mode'] === 'none') {
		$artwork_id = 0;
		$artwork_source = 'none';
		$artwork_source_label = __('Explicitly no artwork', 'backstage-outreach');
	} else {
		$event_plan_id = sanitize_key((string) ($batch['validity_type'] ?? '')) === 'single_event' ? absint($batch['single_event_plan_id'] ?? 0) : 0;
		$event_artwork_id = backstage_outreach_flyer_event_artwork_id($batch);
		if ($event_artwork_id > 0) {
			$artwork_id = $event_artwork_id;
			$artwork_source = 'event';
			$event_title = $event_plan_id > 0 ? sanitize_text_field((string) get_the_title($event_plan_id)) : '';
			$artwork_source_label = $event_title !== ''
				? sprintf(__('Selected event artwork — %s', 'backstage-outreach'), $event_title)
				: __('Selected event artwork', 'backstage-outreach');
		} elseif ((int) $defaults['artwork_id'] > 0 && backstage_outreach_flyer_design_attachment_url((int) $defaults['artwork_id']) !== '') {
			$artwork_id = (int) $defaults['artwork_id'];
			$artwork_source = 'venue';
			$artwork_source_label = __('Venue default artwork', 'backstage-outreach');
		}
	}
	$layout = $campaign['layout_mode'] === 'custom' ? $campaign : $defaults;
	return array(
		'heading' => $campaign['heading'] !== '' ? $campaign['heading'] : $defaults['heading'],
		'subheading' => $campaign['subheading'] !== '' ? $campaign['subheading'] : $defaults['subheading'],
		'artwork_id' => $artwork_id,
		'artwork_url' => backstage_outreach_flyer_design_attachment_url($artwork_id),
		'artwork_source' => $artwork_source,
		'artwork_source_label' => $artwork_source_label,
		'orientation' => (string) $layout['orientation'],
		'composition' => (string) $layout['composition'],
		'panel_position' => (string) $layout['panel_position'],
		'show_heading' => !empty($layout['show_heading']),
		'show_subheading' => !empty($layout['show_subheading']),
		'show_logo' => !empty($layout['show_logo']),
	);
}

function backstage_outreach_handle_flyer_design_save(): void
{
	if (!current_user_can(vms_pass_claims_capability())) {
		wp_die(esc_html__('Access denied.', 'backstage-outreach'));
	}
	check_admin_referer('backstage_outreach_flyer_design');
	$campaign_id = backstage_outreach_request_absint($_POST, 'campaign_id');
	$campaign = vms_pass_outreach_get_campaign_by_id($campaign_id);
	if (!is_array($campaign) || !backstage_outreach_is_reusable_business_campaign($campaign)) {
		wp_die(esc_html__('Reusable-business campaign not found.', 'backstage-outreach'));
	}
	$mode = sanitize_key(backstage_outreach_request_text($_POST, 'campaign_artwork_mode', 'inherit'));
	if (!in_array($mode, array('inherit', 'custom', 'none'), true)) {
		$mode = 'inherit';
	}
	$campaign_artwork_id = backstage_outreach_request_absint($_POST, 'campaign_artwork_id');
	if ($mode === 'custom' && backstage_outreach_flyer_design_attachment_url($campaign_artwork_id) === '') {
		backstage_outreach_business_message(__('Choose a valid Media Library image or use the venue default artwork.', 'backstage-outreach'), 'error');
		backstage_outreach_business_redirect_to_step($campaign_id, 'backstage-outreach-flyer-design');
	}
	$campaign_heading = sanitize_text_field(backstage_outreach_request_text($_POST, 'campaign_flyer_heading'));
	$campaign_subheading = sanitize_text_field(backstage_outreach_request_text($_POST, 'campaign_flyer_subheading'));
	$venue_heading = sanitize_text_field(backstage_outreach_request_text($_POST, 'venue_flyer_heading'));
	$venue_subheading = sanitize_text_field(backstage_outreach_request_text($_POST, 'venue_flyer_subheading'));
	$length = static fn(string $value): int => function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
	if ($length($campaign_heading) > 120 || $length($campaign_subheading) > 180 || $length($venue_heading) > 120 || $length($venue_subheading) > 180) {
		backstage_outreach_business_message(__('Flyer headings must be 120 characters or fewer, and subheadings must be 180 characters or fewer.', 'backstage-outreach'), 'error');
		backstage_outreach_business_redirect_to_step($campaign_id, 'backstage-outreach-flyer-design');
	}
	$campaign_layout_mode = backstage_outreach_flyer_design_choice(backstage_outreach_request_text($_POST, 'campaign_layout_mode', 'inherit'), array('inherit', 'custom'), 'inherit');
	$current_campaign_design = backstage_outreach_flyer_campaign_design($campaign_id);
	$campaign_layout = $campaign_layout_mode === 'custom'
		? backstage_outreach_normalize_flyer_layout(
			backstage_outreach_request_text($_POST, 'campaign_orientation', 'portrait'),
			backstage_outreach_request_text($_POST, 'campaign_composition', 'panels'),
			backstage_outreach_request_text($_POST, 'campaign_panel_position', 'bottom')
		)
		: backstage_outreach_normalize_flyer_layout(
			(string) $current_campaign_design['orientation'],
			(string) $current_campaign_design['composition'],
			(string) $current_campaign_design['panel_position']
		);
	$campaign_show_heading = $campaign_layout_mode === 'custom' ? isset($_POST['campaign_show_heading']) : !empty($current_campaign_design['show_heading']);
	$campaign_show_subheading = $campaign_layout_mode === 'custom' ? isset($_POST['campaign_show_subheading']) : !empty($current_campaign_design['show_subheading']);
	$campaign_show_logo = $campaign_layout_mode === 'custom' ? isset($_POST['campaign_show_logo']) : !empty($current_campaign_design['show_logo']);
	$can_save_venue_defaults = current_user_can('manage_options') && isset($_POST['venue_flyer_heading']);
	$venue_artwork_id = $can_save_venue_defaults ? backstage_outreach_request_absint($_POST, 'venue_artwork_id') : 0;
	if ($can_save_venue_defaults && $venue_artwork_id > 0 && backstage_outreach_flyer_design_attachment_url($venue_artwork_id) === '') {
		backstage_outreach_business_message(__('The venue-default artwork was not a valid Media Library image.', 'backstage-outreach'), 'error');
		backstage_outreach_business_redirect_to_step($campaign_id, 'backstage-outreach-flyer-design');
	}
	update_option(backstage_outreach_flyer_design_option_key($campaign_id), array(
		'heading' => $campaign_heading,
		'subheading' => $campaign_subheading,
		'artwork_mode' => $mode,
		'artwork_id' => $mode === 'custom' ? $campaign_artwork_id : 0,
		'layout_mode' => $campaign_layout_mode,
		'orientation' => $campaign_layout['orientation'],
		'composition' => $campaign_layout['composition'],
		'panel_position' => $campaign_layout['panel_position'],
		'show_heading' => $campaign_show_heading ? 1 : 0,
		'show_subheading' => $campaign_show_subheading ? 1 : 0,
		'show_logo' => $campaign_show_logo ? 1 : 0,
	), false);
	if ($can_save_venue_defaults) {
		$venue_layout = backstage_outreach_normalize_flyer_layout(
			backstage_outreach_request_text($_POST, 'venue_orientation', 'portrait'),
			backstage_outreach_request_text($_POST, 'venue_composition', 'panels'),
			backstage_outreach_request_text($_POST, 'venue_panel_position', 'bottom')
		);
		update_option(backstage_outreach_flyer_design_option_key(), array(
			'heading' => $venue_heading,
			'subheading' => $venue_subheading,
			'artwork_id' => $venue_artwork_id,
			'orientation' => $venue_layout['orientation'],
			'composition' => $venue_layout['composition'],
			'panel_position' => $venue_layout['panel_position'],
			'show_heading' => isset($_POST['venue_show_heading']) ? 1 : 0,
			'show_subheading' => isset($_POST['venue_show_subheading']) ? 1 : 0,
			'show_logo' => isset($_POST['venue_show_logo']) ? 1 : 0,
		), false);
	}
	backstage_outreach_business_message(__('Flyer design saved. Existing business links now use the updated presentation; their signed customer URLs did not change.', 'backstage-outreach'));
	backstage_outreach_business_redirect_to_step($campaign_id, 'backstage-outreach-flyer-design');
}
add_action('admin_post_backstage_outreach_flyer_design', 'backstage_outreach_handle_flyer_design_save');

function backstage_outreach_create_distribution(int $campaign_id, int $source_id, int $business_id, string $distribution_type, int $cap, int $order_cap, string $expires_at, array $event_ids, array $product_ids, int $user_id): int
{
	global $wpdb;
	$table = backstage_outreach_business_table('campaign_businesses');
	$existing = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE campaign_id = %d AND business_id = %d', $table, $campaign_id, $business_id), ARRAY_A);
	$now = backstage_outreach_business_now();
	$data = array(
		'source_id' => $source_id,
		'distribution_type' => $distribution_type === 'coupon_backed' ? 'coupon_backed' : 'complimentary',
		'status' => 'active',
		'admission_cap' => $cap,
		'order_cap' => $order_cap,
		'eligible_event_ids_json' => backstage_outreach_discount_encode_ids($event_ids),
		'eligible_product_ids_json' => backstage_outreach_discount_encode_ids($product_ids),
		'expires_at' => $expires_at ?: null,
		'updated_by' => $user_id,
		'updated_at' => $now,
	);
	if (is_array($existing)) {
		if ((string) ($existing['status'] ?? '') === 'revoked') {
			return 0;
		}
		$updated = $wpdb->update($table, $data, array('id' => (int) $existing['id']));
		return $updated === false ? 0 : (int) $existing['id'];
	}
	try {
		$public_key = bin2hex(random_bytes(24));
	} catch (Throwable $error) {
		return 0;
	}
	$data = array_merge($data, array('campaign_id' => $campaign_id, 'business_id' => $business_id, 'public_key' => $public_key, 'token_hash' => '', 'created_by' => $user_id, 'created_at' => $now));
	$inserted = $wpdb->insert($table, $data);
	$id = (int) $wpdb->insert_id;
	if ($inserted === false || $id <= 0) {
		return 0;
	}
	$row = array('id' => $id, 'campaign_id' => $campaign_id, 'business_id' => $business_id, 'public_key' => $public_key, 'created_at' => $now);
	$hashed = $wpdb->update($table, array('token_hash' => hash('sha256', backstage_outreach_distribution_token($row))), array('id' => $id), array('%s'), array('%d'));
	return $hashed === false ? 0 : $id;
}

function backstage_outreach_campaign_business_preview_key(int $campaign_id): string
{
	return 'backstage_outreach_partner_preview_' . get_current_user_id() . '_' . $campaign_id;
}

function backstage_outreach_campaign_business_form_key(int $campaign_id): string
{
	return 'backstage_outreach_partner_form_' . get_current_user_id() . '_' . $campaign_id;
}

function backstage_outreach_campaign_business_share_key(int $campaign_id): string
{
	return 'backstage_outreach_partner_share_' . get_current_user_id() . '_' . $campaign_id;
}

function backstage_outreach_business_membership_digest(array $business_ids): string
{
	$business_ids = array_values(array_unique(array_filter(array_map('absint', $business_ids))));
	sort($business_ids, SORT_NUMERIC);
	return hash('sha256', wp_json_encode($business_ids));
}

/**
 * Identify the explicit reusable-business route without relying on recipient count.
 */
function backstage_outreach_is_reusable_business_campaign(array $campaign): bool
{
	$campaign_id = absint($campaign['id'] ?? 0);
	if ($campaign_id <= 0) {
		return false;
	}

	global $wpdb;
	$distribution = $wpdb->get_var($wpdb->prepare(
		'SELECT id FROM %i WHERE campaign_id = %d LIMIT 1',
		backstage_outreach_business_table('campaign_businesses'),
		$campaign_id
	));
	if (absint($distribution) > 0) {
		return true;
	}

	if (!function_exists('bvmgr_admission_table_audit')) {
		return false;
	}
	$needle = '%"campaign_id":' . $wpdb->esc_like((string) $campaign_id) . ',%';
	$audit_id = $wpdb->get_var($wpdb->prepare(
		'SELECT id FROM %i WHERE action = %s AND details LIKE %s ORDER BY id DESC LIMIT 1',
		bvmgr_admission_table_audit(),
		'pass_outreach_business_campaign_create',
		$needle
	));
	return absint($audit_id) > 0;
}

function backstage_outreach_business_review_token(): string
{
	try {
		return bin2hex(random_bytes(18));
	} catch (Throwable $error) {
		return wp_generate_password(36, false, false);
	}
}

function backstage_outreach_business_format_local_datetime(string $value): string
{
	$value = sanitize_text_field($value);
	if ($value === '') {
		return '';
	}
	try {
		$date = new DateTimeImmutable($value, wp_timezone());
		return wp_date('F j, Y \a\t g:i a T', $date->getTimestamp(), wp_timezone());
	} catch (Throwable $error) {
		return $value;
	}
}

function backstage_outreach_business_form_payload(array $source): array
{
	return array(
		'business_ids' => array_values(array_unique(array_filter(array_map('absint', (array) ($source['business_ids'] ?? array()))))),
		'distribution_type' => sanitize_key((string) ($source['distribution_type'] ?? 'complimentary')),
		'admission_cap' => absint($source['admission_cap'] ?? 0),
		'order_cap' => absint($source['order_cap'] ?? 0),
		'expires_at' => sanitize_text_field((string) ($source['expires_at'] ?? '')),
	);
}

function backstage_outreach_business_redirect_to_step(int $campaign_id, string $anchor = 'backstage-outreach-partners'): void
{
	wp_safe_redirect(vms_pass_outreach_admin_page_url(array('campaign_id' => $campaign_id)) . '#' . sanitize_html_class($anchor));
	exit;
}

function backstage_outreach_handle_campaign_businesses(): void
{
	if (!current_user_can(vms_pass_claims_capability())) {
		wp_die(esc_html__('Access denied.', 'backstage-outreach'));
	}
	check_admin_referer('backstage_outreach_campaign_businesses');
	$campaign_id = backstage_outreach_request_absint($_POST, 'campaign_id');
	$campaign = vms_pass_outreach_get_campaign_by_id($campaign_id);
	if (!$campaign) {
		wp_die(esc_html__('Campaign not found.', 'backstage-outreach'));
	}
	$source_id = absint($campaign['related_source_id'] ?? 0);
	$batch_id = absint($campaign['related_batch_id'] ?? 0);
	if ($source_id <= 0 || $batch_id <= 0) {
		backstage_outreach_business_message(__('Link both a Source and a Guest Pass batch before enabling partner distribution.', 'backstage-outreach'), 'error');
		wp_safe_redirect(vms_pass_outreach_admin_page_url(array('campaign_id' => $campaign_id)));
		exit;
	}
	$batch = bvmgr_pass_claims_get_batch_by_id($batch_id);
	if (!is_array($batch)) {
		backstage_outreach_business_message(__('The linked Guest Pass batch could not be loaded.', 'backstage-outreach'), 'error');
		wp_safe_redirect(vms_pass_outreach_admin_page_url(array('campaign_id' => $campaign_id)) . '#backstage-outreach-partners');
		exit;
	}
	$selected = array_values(array_unique(array_filter(array_map(static fn($value) => is_scalar($value) ? absint((string) $value) : 0, (array) ($_POST['business_ids'] ?? array())))));
	$active = backstage_outreach_source_businesses($source_id, false);
	$allowed = array_map(static fn($row) => (int) $row['id'], $active);
	$submitted = $selected;
	$selected = array_values(array_intersect($selected, $allowed));
	$distribution_type = sanitize_key(backstage_outreach_request_text($_POST, 'distribution_type', 'complimentary'));
	if (!in_array($distribution_type, array('complimentary', 'coupon_backed'), true)) {
		$distribution_type = 'complimentary';
	}
	$cap = backstage_outreach_request_absint($_POST, 'admission_cap');
	$order_cap = backstage_outreach_request_absint($_POST, 'order_cap');
	$expires_raw = backstage_outreach_request_text($_POST, 'expires_at');
	$expires_at = function_exists('bvmgr_pass_claims_parse_local_datetime') ? bvmgr_pass_claims_parse_local_datetime($expires_raw) : $expires_raw;
	$mode = sanitize_key(backstage_outreach_request_text($_POST, 'distribution_mode', 'preview'));
	$preview_key = backstage_outreach_campaign_business_preview_key($campaign_id);
	$form_payload = backstage_outreach_business_form_payload(array(
		'business_ids' => $submitted,
		'distribution_type' => $distribution_type,
		'admission_cap' => $cap,
		'order_cap' => $order_cap,
		'expires_at' => $expires_raw,
	));
	if ($distribution_type === 'coupon_backed' && sanitize_key((string) ($batch['value_type'] ?? '')) === 'free') {
		delete_transient($preview_key);
		if ($mode !== 'commit') {
			set_transient(backstage_outreach_campaign_business_form_key($campaign_id), $form_payload, 10 * MINUTE_IN_SECONDS);
		}
		backstage_outreach_business_message(__('New paid Admission Offers require a reviewed Percentage Off or Fixed Amount Off batch. Existing 50% links backed by a Free batch remain valid, but cannot be created or re-reviewed from this form.', 'backstage-outreach'), 'error');
		backstage_outreach_business_redirect_to_step($campaign_id, 'backstage-outreach-business-selection');
	}
	if ($cap > 50000) {
		delete_transient($preview_key);
		if ($mode !== 'commit') {
			set_transient(backstage_outreach_campaign_business_form_key($campaign_id), $form_payload, 10 * MINUTE_IN_SECONDS);
		}
		backstage_outreach_business_message(__('Total admissions allowed per business cannot exceed 50,000.', 'backstage-outreach'), 'error');
		backstage_outreach_business_redirect_to_step($campaign_id, 'backstage-outreach-business-limits');
	}
	if ($mode !== 'commit') {
		if (empty($selected) || count($submitted) !== count($selected)) {
			delete_transient($preview_key);
			set_transient(backstage_outreach_campaign_business_form_key($campaign_id), $form_payload, 10 * MINUTE_IN_SECONDS);
			backstage_outreach_business_message(empty($selected) ? __('Select at least one active business before reviewing links.', 'backstage-outreach') : __('One or more selected businesses are no longer active in this Source. Review the current selection.', 'backstage-outreach'), 'error');
			backstage_outreach_business_redirect_to_step($campaign_id, 'backstage-outreach-business-selection');
		}
		if ($expires_raw !== '' && $expires_at === '') {
			delete_transient($preview_key);
			set_transient(backstage_outreach_campaign_business_form_key($campaign_id), $form_payload, 10 * MINUTE_IN_SECONDS);
			backstage_outreach_business_message(__('Enter a valid distribution expiry in the site timezone.', 'backstage-outreach'), 'error');
			backstage_outreach_business_redirect_to_step($campaign_id, 'backstage-outreach-business-expiry');
		}
		$configuration = backstage_outreach_discount_offer_configuration($campaign, $batch, $distribution_type);
		if (is_wp_error($configuration)) {
			backstage_outreach_business_message($configuration->get_error_message(), 'error');
			wp_safe_redirect(vms_pass_outreach_admin_page_url(array('campaign_id' => $campaign_id)) . '#backstage-outreach-partners');
			exit;
		}
		$review_token = backstage_outreach_business_review_token();
		set_transient($preview_key, array(
			'source_id' => $source_id,
			'batch_id' => $batch_id,
			'business_ids' => $selected,
			'distribution_type' => $distribution_type,
			'admission_cap' => $cap,
			'order_cap' => $order_cap,
			'expires_at' => $expires_at,
			'expires_input' => $expires_raw,
			'membership_digest' => backstage_outreach_business_membership_digest($allowed),
			'review_token' => $review_token,
			'reviewed_at' => time(),
			'configuration_digest' => backstage_outreach_discount_configuration_digest($configuration),
		), 30 * MINUTE_IN_SECONDS);
		delete_transient(backstage_outreach_campaign_business_form_key($campaign_id));
		backstage_outreach_business_message(sprintf(_n('%d business is ready for review. No links were changed.', '%d businesses are ready for review. No links were changed.', count($selected), 'backstage-outreach'), count($selected)));
		backstage_outreach_business_redirect_to_step($campaign_id, 'backstage-outreach-business-review');
	}
	$preview = get_transient($preview_key);
	if (!is_array($preview)) {
		backstage_outreach_business_message(__('The review expired. Review the business selection again before saving links.', 'backstage-outreach'), 'error');
		wp_safe_redirect(vms_pass_outreach_admin_page_url(array('campaign_id' => $campaign_id)) . '#backstage-outreach-partners');
		exit;
	}
	$posted_review_token = sanitize_text_field(backstage_outreach_request_text($_POST, 'review_token'));
	$stored_review_token = sanitize_text_field((string) ($preview['review_token'] ?? ''));
	if ($posted_review_token === '' || $stored_review_token === '' || !hash_equals($stored_review_token, $posted_review_token)) {
		delete_transient($preview_key);
		backstage_outreach_business_message(__('The reviewed selection is no longer current. Review the businesses and terms again.', 'backstage-outreach'), 'error');
		backstage_outreach_business_redirect_to_step($campaign_id, 'backstage-outreach-business-selection');
	}
	if (absint($preview['source_id'] ?? 0) !== $source_id || absint($preview['batch_id'] ?? 0) !== $batch_id) {
		delete_transient($preview_key);
		backstage_outreach_business_message(__('The campaign Source or offer batch changed after review. Review the current setup again before saving links.', 'backstage-outreach'), 'error');
		backstage_outreach_business_redirect_to_step($campaign_id, 'backstage-outreach-business-selection');
	}
	if (!hash_equals((string) ($preview['membership_digest'] ?? ''), backstage_outreach_business_membership_digest($allowed))) {
		delete_transient($preview_key);
		backstage_outreach_business_message(__('Active Source memberships changed after review. Review the current businesses again before saving links.', 'backstage-outreach'), 'error');
		backstage_outreach_business_redirect_to_step($campaign_id, 'backstage-outreach-business-selection');
	}
	$selected = array_values(array_map('absint', (array) ($preview['business_ids'] ?? array())));
	if (empty($selected) || count(array_intersect($selected, $allowed)) !== count($selected)) {
		delete_transient($preview_key);
		backstage_outreach_business_message(__('The reviewed business selection changed or is no longer eligible. Review it again.', 'backstage-outreach'), 'error');
		backstage_outreach_business_redirect_to_step($campaign_id, 'backstage-outreach-business-selection');
	}
	$distribution_type = sanitize_key((string) ($preview['distribution_type'] ?? 'complimentary'));
	if ($distribution_type === 'coupon_backed' && sanitize_key((string) ($batch['value_type'] ?? '')) === 'free') {
		backstage_outreach_business_message(__('New paid Admission Offers require a reviewed Percentage Off or Fixed Amount Off batch. Existing 50% links backed by a Free batch remain valid, but cannot be created or re-reviewed from this form.', 'backstage-outreach'), 'error');
		delete_transient($preview_key);
		wp_safe_redirect(vms_pass_outreach_admin_page_url(array('campaign_id' => $campaign_id)) . '#backstage-outreach-partners');
		exit;
	}
	$cap = absint($preview['admission_cap'] ?? 0);
	$order_cap = absint($preview['order_cap'] ?? 0);
	$expires_at = sanitize_text_field((string) ($preview['expires_at'] ?? ''));
	$configuration = backstage_outreach_discount_offer_configuration($campaign, $batch, $distribution_type);
	if (is_wp_error($configuration) || !hash_equals((string) ($preview['configuration_digest'] ?? ''), backstage_outreach_discount_configuration_digest(is_array($configuration) ? $configuration : array()))) {
		backstage_outreach_business_message(is_wp_error($configuration) ? $configuration->get_error_message() : __('The eligible ticket scope changed after review. Review the selection again.', 'backstage-outreach'), 'error');
		delete_transient($preview_key);
		wp_safe_redirect(vms_pass_outreach_admin_page_url(array('campaign_id' => $campaign_id)) . '#backstage-outreach-partners');
		exit;
	}
	global $wpdb;
	$table = backstage_outreach_business_table('campaign_businesses');
	$lock_name = 'bvm-pass-batch-' . $batch_id;
	if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $lock_name, 5)) !== 1) {
		backstage_outreach_business_message(__('Partner distributions are being used or updated. Try saving again.', 'backstage-outreach'), 'error');
		wp_safe_redirect(vms_pass_outreach_admin_page_url(array('campaign_id' => $campaign_id)) . '#backstage-outreach-partners');
		exit;
	}
	$ok = $wpdb->query('START TRANSACTION') !== false;
	$save_error = '';
	if (!$ok) {
		$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
		backstage_outreach_business_message(__('Partner distributions could not be started safely.', 'backstage-outreach'), 'error');
		wp_safe_redirect(vms_pass_outreach_admin_page_url(array('campaign_id' => $campaign_id)) . '#backstage-outreach-partners');
		exit;
	}
	$existing = $wpdb->get_results($wpdb->prepare('SELECT id, business_id, status, coupon_id FROM %i WHERE campaign_id = %d FOR UPDATE', $table, $campaign_id), ARRAY_A);
	$revoked_selected = false;
	foreach ((array) $existing as $row) {
		if ((string) ($row['status'] ?? '') === 'revoked' && in_array((int) $row['business_id'], $selected, true)) {
			$revoked_selected = true;
			$ok = false;
			continue;
		}
		if (!in_array((int) $row['business_id'], $selected, true)) {
			if ((string) ($row['status'] ?? '') !== 'revoked') {
				$ok = $wpdb->update($table, array('status' => 'paused', 'updated_by' => get_current_user_id(), 'updated_at' => backstage_outreach_business_now()), array('id' => (int) $row['id'])) !== false && $ok;
				$ok = backstage_outreach_discount_set_coupon_status(absint($row['coupon_id'] ?? 0), 'draft', absint($row['id'] ?? 0)) && $ok;
			}
		}
	}
	foreach ($selected as $business_id) {
		$distribution_id = backstage_outreach_create_distribution(
			$campaign_id,
			$source_id,
			$business_id,
			$distribution_type,
			$cap,
			$distribution_type === 'coupon_backed' ? $order_cap : 0,
			$expires_at,
			(array) ($configuration['event_ids'] ?? array()),
			(array) ($configuration['product_ids'] ?? array()),
			get_current_user_id()
		);
		$distribution = $distribution_id > 0 ? backstage_outreach_discount_get_distribution($distribution_id) : null;
		$coupon_result = is_array($distribution) ? backstage_outreach_discount_ensure_coupon($distribution, $campaign, $configuration) : new WP_Error('distribution_save_failed', __('A business distribution could not be saved.', 'backstage-outreach'));
		if (is_wp_error($coupon_result)) {
			$save_error = $coupon_result->get_error_message();
			$ok = false;
			continue;
		}
		$ok = $wpdb->update($table, array('coupon_id' => absint($coupon_result['coupon_id'] ?? 0) ?: null, 'coupon_code' => (string) ($coupon_result['coupon_code'] ?? '') ?: null, 'updated_at' => backstage_outreach_business_now()), array('id' => $distribution_id)) !== false && $ok;
	}
	$committed = $ok && $wpdb->query('COMMIT') !== false;
	if (!$committed) {
		$wpdb->query('ROLLBACK');
		$ok = false;
	}
	$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
	if ($ok) {
		delete_transient($preview_key);
		if (function_exists('vms_pass_outreach_business_distribution_handoff_key')) {
			delete_transient(vms_pass_outreach_business_distribution_handoff_key($campaign_id));
		}
		if (function_exists('bvmgr_admission_audit_log')) {
			bvmgr_admission_audit_log(0, null, 'outreach_partner_distributions_save', get_current_user_id(), 'admin', array('campaign_id' => $campaign_id, 'source_id' => $source_id, 'business_ids' => $selected, 'distribution_type' => $distribution_type, 'admission_cap' => $cap, 'order_cap' => $order_cap, 'eligible_event_ids' => $configuration['event_ids'] ?? array(), 'eligible_product_ids' => $configuration['product_ids'] ?? array(), 'expires_at' => $expires_at));
		}
	}
	$failure_message = $revoked_selected
		? __('A revoked distribution cannot be resumed. Remove it from the reviewed selection; a future reissue must use a newly rotated link.', 'backstage-outreach')
		: ($save_error !== '' ? $save_error : __('Partner distributions could not be saved.', 'backstage-outreach'));
	backstage_outreach_business_message($ok ? __('Partner distributions saved. No invitations were sent.', 'backstage-outreach') : $failure_message, $ok ? 'info' : 'error');
	backstage_outreach_business_redirect_to_step($campaign_id, $ok ? 'backstage-outreach-business-share' : 'backstage-outreach-business-review');
}
add_action('admin_post_backstage_outreach_campaign_businesses', 'backstage_outreach_handle_campaign_businesses');

function backstage_outreach_distribution_rows(int $campaign_id): array
{
	global $wpdb;
	$rows = $wpdb->get_results($wpdb->prepare('SELECT d.*, b.business_name, b.contact_name, b.email, b.phone FROM %i d INNER JOIN %i b ON b.id = d.business_id WHERE d.campaign_id = %d ORDER BY b.business_name ASC', backstage_outreach_business_table('campaign_businesses'), backstage_outreach_business_table('businesses'), $campaign_id), ARRAY_A);
	return is_array($rows) ? $rows : array();
}

function backstage_outreach_business_share_default_subject(): string
{
	return __('Your {business_name} admission offer links', 'backstage-outreach');
}

function backstage_outreach_business_share_default_message(): string
{
	return __("Hello {contact_name},\n\nHere are the customer offer and printable reception-desk flyer links for {business_name}. Please share them with your customers.", 'backstage-outreach');
}

function backstage_outreach_business_share_template_key(int $campaign_id): string
{
	return 'backstage_outreach_business_share_template_' . $campaign_id;
}

function backstage_outreach_business_share_template(int $campaign_id): array
{
	$stored = get_option(backstage_outreach_business_share_template_key($campaign_id), array());
	return array(
		'subject' => is_array($stored) && !empty($stored['subject']) ? sanitize_text_field((string) $stored['subject']) : backstage_outreach_business_share_default_subject(),
		'message' => is_array($stored) && !empty($stored['message']) ? sanitize_textarea_field((string) $stored['message']) : backstage_outreach_business_share_default_message(),
	);
}

function backstage_outreach_business_share_sent_map(int $campaign_id): array
{
	if ($campaign_id <= 0 || !function_exists('bvmgr_admission_table_audit')) {
		return array();
	}
	global $wpdb;
	$rows = $wpdb->get_results($wpdb->prepare(
		'SELECT details, created_at FROM %i WHERE action IN (%s,%s) AND details LIKE %s ORDER BY id DESC LIMIT 500',
		bvmgr_admission_table_audit(),
		'outreach_business_share_email_handed_off',
		'outreach_business_share_email_sent',
		'%"campaign_id":' . $wpdb->esc_like((string) $campaign_id) . ',%'
	), ARRAY_A);
	$sent = array();
	foreach ((array) $rows as $row) {
		$details = json_decode((string) ($row['details'] ?? ''), true);
		$distribution_id = is_array($details) && absint($details['campaign_id'] ?? 0) === $campaign_id ? absint($details['distribution_id'] ?? 0) : 0;
		if ($distribution_id > 0 && !isset($sent[$distribution_id])) {
			$sent[$distribution_id] = sanitize_text_field((string) ($row['created_at'] ?? ''));
		}
	}
	return $sent;
}

function backstage_outreach_business_share_configuration_digest(array $campaign, array $batch, array $rows): string
{
	return hash('sha256', wp_json_encode(array(
		'campaign' => array(
			'id' => absint($campaign['id'] ?? 0),
			'source_id' => absint($campaign['related_source_id'] ?? 0),
			'batch_id' => absint($campaign['related_batch_id'] ?? 0),
			'status' => sanitize_key((string) ($campaign['status'] ?? '')),
		),
		'batch' => array(
			'id' => absint($batch['id'] ?? 0),
			'source_id' => absint($batch['source_id'] ?? 0),
			'status' => sanitize_key((string) ($batch['status'] ?? '')),
			'value_type' => sanitize_key((string) ($batch['value_type'] ?? '')),
			'value_amount' => (string) ($batch['value_amount'] ?? ''),
			'admissions_per_link' => absint($batch['admissions_per_link'] ?? 0),
			'total_admission_cap' => absint($batch['total_admission_cap'] ?? 0),
			'validity_type' => sanitize_key((string) ($batch['validity_type'] ?? '')),
			'single_event_plan_id' => absint($batch['single_event_plan_id'] ?? 0),
			'start_date' => (string) ($batch['start_date'] ?? ''),
			'end_date' => (string) ($batch['end_date'] ?? ''),
			'season_label' => (string) ($batch['season_label'] ?? ''),
			'expires_at' => (string) ($batch['expires_at'] ?? ''),
		),
		'rows' => array_map(static function (array $row): array {
			return array(absint($row['id'] ?? 0), absint($row['business_id'] ?? 0), (string) ($row['status'] ?? ''), (string) ($row['updated_at'] ?? ''), (string) ($row['expires_at'] ?? ''));
		}, $rows),
	)));
}

function backstage_outreach_business_share_context(array $distribution, array $campaign, array $batch, string $subject_template, string $message_template): array
{
	$business_name = sanitize_text_field((string) ($distribution['business_name'] ?? ''));
	$contact_name = sanitize_text_field((string) ($distribution['contact_name'] ?? ''));
	$offer = __('Complimentary Guest Passes', 'backstage-outreach');
	if (sanitize_key((string) ($distribution['distribution_type'] ?? '')) === 'coupon_backed') {
		$offer = __('Admission Offer', 'backstage-outreach');
		$terms = function_exists('backstage_outreach_discount_terms_for_distribution')
			? backstage_outreach_discount_terms_for_distribution(array_merge($distribution, array('related_batch_id' => absint($campaign['related_batch_id'] ?? 0))))
			: null;
		if (is_array($terms) && function_exists('backstage_outreach_discount_terms_label')) {
			$offer = backstage_outreach_discount_terms_label($terms);
		}
	}
	$customer_cap = max(1, absint($batch['admissions_per_link'] ?? 1));
	$business_cap = absint($distribution['admission_cap'] ?? 0);
	$overall_cap = absint($batch['total_admission_cap'] ?? 0);
	$scope = function_exists('vms_pass_outreach_business_batch_scope_label') ? vms_pass_outreach_business_batch_scope_label($batch) : __('See offer page', 'backstage-outreach');
	$expiry = backstage_outreach_distribution_effective_expiry($distribution);
	$expiry_label = $expiry !== ''
		? backstage_outreach_business_format_local_datetime($expiry)
		: __('No separate expiry', 'backstage-outreach');
	$customer_url = backstage_outreach_distribution_url($distribution);
	$flyer_url = backstage_outreach_distribution_flyer_url($distribution);
	$replace = array(
		'{business_name}' => $business_name,
		'{contact_name}' => $contact_name !== '' ? $contact_name : $business_name,
		'{offer_terms}' => $offer,
		'{customer_url}' => $customer_url,
		'{flyer_url}' => $flyer_url,
	);
	$subject = sanitize_text_field(strtr($subject_template, $replace));
	$intro = trim(strtr($message_template, $replace));
	$business_limit = $business_cap > 0
		? sprintf(_n('%d admission through this business', '%d admissions through this business', $business_cap, 'backstage-outreach'), $business_cap)
		: __('No separate per-business maximum', 'backstage-outreach');
	$overall_limit = $overall_cap > 0
		? sprintf(_n('%d admission shared across all participating businesses', '%d admissions shared across all participating businesses', $overall_cap, 'backstage-outreach'), $overall_cap)
		: __('No stated shared admission maximum', 'backstage-outreach');
	$details = array(
		__('Offer', 'backstage-outreach') . ': ' . $offer,
		__('Admissions per customer', 'backstage-outreach') . ': ' . $customer_cap,
		__('Total admissions allowed per business', 'backstage-outreach') . ': ' . $business_limit,
		__('Total admissions available across all businesses', 'backstage-outreach') . ': ' . $overall_limit,
		__('Shared-capacity note', 'backstage-outreach') . ': ' . __('The overall pool is shared. A per-business maximum does not reserve admissions, so the pool may run out first.', 'backstage-outreach'),
		__('Applicable events / dates', 'backstage-outreach') . ': ' . $scope,
		__('Expiry', 'backstage-outreach') . ': ' . $expiry_label,
		__('Customer offer URL', 'backstage-outreach') . ': ' . $customer_url,
		__('Printable flyer URL', 'backstage-outreach') . ': ' . $flyer_url,
	);
	return array(
		'subject' => $subject,
		'message' => trim($intro . "\n\n" . implode("\n", $details)),
		'email' => sanitize_email((string) ($distribution['email'] ?? '')),
		'customer_url' => $customer_url,
		'flyer_url' => $flyer_url,
		'offer' => $offer,
		'scope' => $scope,
		'expiry' => $expiry_label,
	);
}

function backstage_outreach_attempt_business_share_email(array $row, array $campaign, array $batch, array $review, array $sent_map): array
{
	$campaign_id = absint($campaign['id'] ?? 0);
	$distribution_id = absint($row['id'] ?? 0);
	$email = sanitize_email((string) ($row['email'] ?? ''));
	$effective_expiry = backstage_outreach_distribution_effective_expiry($row);
	$is_expired = $effective_expiry !== '' && backstage_outreach_business_now() > $effective_expiry;
	$reason = '';
	if ($email === '') {
		$reason = 'missing_email';
	} elseif ($is_expired) {
		$reason = 'expired';
	} elseif (isset($sent_map[$distribution_id])) {
		$reason = 'already_sent';
	} elseif (function_exists('vms_outreach_email_is_suppressed') && vms_outreach_email_is_suppressed($email)) {
		$reason = 'suppressed';
	}
	if ($reason !== '') {
		return array('status' => 'skipped', 'code' => $reason, 'distribution_id' => $distribution_id);
	}

	$context = backstage_outreach_business_share_context($row, $campaign, $batch, (string) ($review['subject'] ?? ''), (string) ($review['message'] ?? ''));
	$headers = array('Content-Type: text/plain; charset=UTF-8');
	$from_email = sanitize_email((string) get_option('admin_email'));
	if ($from_email !== '') {
		$site_name = wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES);
		$headers[] = 'From: ' . $site_name . ' <' . $from_email . '>';
		$headers[] = 'Reply-To: ' . $from_email;
	}
	$accepted = wp_mail($email, (string) $context['subject'], (string) $context['message'], $headers);
	$action = $accepted ? 'outreach_business_share_email_handed_off' : 'outreach_business_share_email_failed';
	if (function_exists('bvmgr_admission_audit_log')) {
		$template_digest = hash('sha256', (string) ($review['subject'] ?? '') . "\n" . (string) ($review['message'] ?? ''));
		bvmgr_admission_audit_log(0, null, $action, get_current_user_id(), 'admin', array(
			'campaign_id' => $campaign_id,
			'distribution_id' => $distribution_id,
			'business_id' => absint($row['business_id'] ?? 0),
			'email' => $email,
			'template_digest' => $template_digest,
		));
	}
	return array('status' => $accepted ? 'handed_off' : 'failed', 'code' => $accepted ? 'accepted_by_mailer' : 'wp_mail_failed', 'distribution_id' => $distribution_id);
}

function backstage_outreach_handle_business_share(): void
{
	if (!current_user_can(vms_pass_claims_capability())) {
		wp_die(esc_html__('Access denied.', 'backstage-outreach'));
	}
	check_admin_referer('backstage_outreach_business_share');
	$campaign_id = backstage_outreach_request_absint($_POST, 'campaign_id');
	$campaign = vms_pass_outreach_get_campaign_by_id($campaign_id);
	$batch = is_array($campaign) ? bvmgr_pass_claims_get_batch_by_id(absint($campaign['related_batch_id'] ?? 0)) : null;
	if (!is_array($campaign) || !is_array($batch) || !backstage_outreach_is_reusable_business_campaign($campaign)) {
		wp_die(esc_html__('Reusable-business campaign not found.', 'backstage-outreach'));
	}
	$subject = sanitize_text_field(backstage_outreach_request_text($_POST, 'business_share_subject'));
	$message = sanitize_textarea_field(backstage_outreach_request_text($_POST, 'business_share_message'));
	if ($subject === '') {
		$subject = backstage_outreach_business_share_default_subject();
	}
	if ($message === '') {
		$message = backstage_outreach_business_share_default_message();
	}
	$rows = array_values(array_filter(backstage_outreach_distribution_rows($campaign_id), static fn(array $row): bool => (string) ($row['status'] ?? '') === 'active'));
	$configuration_digest = backstage_outreach_business_share_configuration_digest($campaign, $batch, $rows);
	$mode = sanitize_key(backstage_outreach_request_text($_POST, 'share_mode', 'preview'));
	$key = backstage_outreach_campaign_business_share_key($campaign_id);
	if ($mode !== 'send') {
		update_option(backstage_outreach_business_share_template_key($campaign_id), array('subject' => $subject, 'message' => $message), false);
		set_transient($key, array('token' => backstage_outreach_business_review_token(), 'subject' => $subject, 'message' => $message, 'configuration_digest' => $configuration_digest, 'reviewed_at' => time()), 30 * MINUTE_IN_SECONDS);
		backstage_outreach_business_message(sprintf(_n('%d personalized business message is ready for review. Nothing was sent.', '%d personalized business messages are ready for review. Nothing was sent.', count($rows), 'backstage-outreach'), count($rows)));
		backstage_outreach_business_redirect_to_step($campaign_id, 'backstage-outreach-business-share-review');
	}
	$review = get_transient($key);
	$token = sanitize_text_field(backstage_outreach_request_text($_POST, 'share_review_token'));
	if (!is_array($review) || $token === '' || !hash_equals((string) ($review['token'] ?? ''), $token) || !hash_equals((string) ($review['configuration_digest'] ?? ''), $configuration_digest)) {
		delete_transient($key);
		backstage_outreach_business_message(__('The personalized message review expired or linked businesses changed. Review messages again before sending.', 'backstage-outreach'), 'error');
		backstage_outreach_business_redirect_to_step($campaign_id, 'backstage-outreach-business-share');
	}
	if (sanitize_key((string) ($campaign['status'] ?? '')) !== 'active') {
		backstage_outreach_business_message(__('Activate the campaign before sending business-contact email. Copyable messages remain available while the campaign is a draft.', 'backstage-outreach'), 'error');
		backstage_outreach_business_redirect_to_step($campaign_id, 'backstage-outreach-business-share-review');
	}
	$selected = array_values(array_unique(array_filter(array_map('absint', (array) ($_POST['distribution_ids'] ?? array())))));
	if (empty($selected)) {
		backstage_outreach_business_message(__('Select at least one eligible business email after reviewing the personalized recipients and links.', 'backstage-outreach'), 'error');
		backstage_outreach_business_redirect_to_step($campaign_id, 'backstage-outreach-business-share-review');
	}
	global $wpdb;
	$lock_name = 'outreach-business-share-' . $campaign_id;
	if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $lock_name, 5)) !== 1) {
		backstage_outreach_business_message(__('Business messages are already being processed. Try again after the current send finishes.', 'backstage-outreach'), 'error');
		backstage_outreach_business_redirect_to_step($campaign_id, 'backstage-outreach-business-share-review');
	}
	$sent_map = backstage_outreach_business_share_sent_map($campaign_id);
	$handoff_count = 0;
	$failed_count = 0;
	$skipped_count = 0;
	foreach ($rows as $row) {
		$id = absint($row['id'] ?? 0);
		if (!in_array($id, $selected, true)) {
			continue;
		}
		$result = backstage_outreach_attempt_business_share_email($row, $campaign, $batch, $review, $sent_map);
		if ((string) ($result['status'] ?? '') === 'handed_off') {
			$handoff_count++;
			$sent_map[$id] = backstage_outreach_business_now();
		} elseif ((string) ($result['status'] ?? '') === 'failed') {
			$failed_count++;
		} else {
			$skipped_count++;
		}
	}
	$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
	delete_transient($key);
	backstage_outreach_business_message(sprintf(__('Business email handoff result: %1$d accepted by the mail system, %2$d failed, %3$d skipped (missing email, suppressed, or already handed off). Acceptance by the mail system is not confirmation of delivery.', 'backstage-outreach'), $handoff_count, $failed_count, $skipped_count), $failed_count > 0 ? 'error' : 'info');
	backstage_outreach_business_redirect_to_step($campaign_id, 'backstage-outreach-business-share');
}
add_action('admin_post_backstage_outreach_business_share', 'backstage_outreach_handle_business_share');

function backstage_outreach_handle_distribution_status(): void
{
	if (!current_user_can(vms_pass_claims_capability())) { wp_die(esc_html__('Access denied.', 'backstage-outreach')); }
	$id = backstage_outreach_request_absint($_REQUEST, 'distribution_id');
	$status = sanitize_key(backstage_outreach_request_text($_REQUEST, 'status', 'paused'));
	if (!in_array($status, array('active', 'paused', 'revoked'), true)) { $status = 'paused'; }
	check_admin_referer('backstage_outreach_distribution_status_' . $id . '_' . $status);
	global $wpdb;
	$row = $wpdb->get_row($wpdb->prepare('SELECT d.*, c.related_batch_id FROM %i d INNER JOIN %i c ON c.id=d.campaign_id WHERE d.id=%d', backstage_outreach_business_table('campaign_businesses'), vms_admission_table_pass_outreach_campaigns(), $id), ARRAY_A);
	$updated = false;
	$error_message = '';
	$from_status = is_array($row) ? (string) ($row['status'] ?? '') : '';
	$related_batch_id = is_array($row) ? absint($row['related_batch_id'] ?? 0) : 0;
	$lock_name = $related_batch_id > 0 ? 'bvm-pass-batch-' . $related_batch_id : '';
	$locked = $lock_name !== '' && (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $lock_name, 5)) === 1;
	if ($locked) {
		try {
			$started = $wpdb->query('START TRANSACTION') !== false;
			$row = $started ? $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d FOR UPDATE', backstage_outreach_business_table('campaign_businesses'), $id), ARRAY_A) : null;
			if ($started && is_array($row) && !((string) ($row['status'] ?? '') === 'revoked' && $status !== 'revoked')) {
				$updated = $wpdb->update(backstage_outreach_business_table('campaign_businesses'), array('status' => $status, 'updated_by' => get_current_user_id(), 'updated_at' => backstage_outreach_business_now()), array('id' => $id)) !== false;
				if ($updated && backstage_outreach_discount_distribution_type($row) === 'coupon_backed') {
					if ($status === 'active') {
						$distribution = backstage_outreach_discount_get_distribution($id);
						$campaign = vms_pass_outreach_get_campaign_by_id(absint($row['campaign_id'] ?? 0));
						$batch = is_array($campaign) ? bvmgr_pass_claims_get_batch_by_id(absint($campaign['related_batch_id'] ?? 0)) : null;
						$configuration = is_array($campaign) && is_array($batch) ? backstage_outreach_discount_offer_configuration($campaign, $batch, 'coupon_backed', true) : new WP_Error('offer_configuration_missing', __('The offer configuration could not be loaded.', 'backstage-outreach'));
						$coupon_result = is_array($distribution) && is_array($configuration) ? backstage_outreach_discount_ensure_coupon($distribution, $campaign, $configuration) : $configuration;
						if (is_wp_error($coupon_result)) {
							$error_message = $coupon_result->get_error_message();
							$updated = false;
						}
					} else {
						$updated = backstage_outreach_discount_set_coupon_status(absint($row['coupon_id'] ?? 0), 'draft', $id);
					}
				}
			}
			if ($updated && $wpdb->query('COMMIT') === false) {
				$updated = false;
			}
			if (!$updated && $started) {
				$wpdb->query('ROLLBACK');
			}
		} finally {
			$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
		}
	}
	if ($updated && function_exists('bvmgr_admission_audit_log')) {
		bvmgr_admission_audit_log(0, null, 'outreach_partner_distribution_status', get_current_user_id(), 'admin', array('distribution_id' => $id, 'campaign_id' => (int) $row['campaign_id'], 'business_id' => (int) $row['business_id'], 'from_status' => $from_status, 'to_status' => $status));
	}
	backstage_outreach_business_message($updated ? __('Distribution status updated. Existing purchased tickets and customer credentials were not changed.', 'backstage-outreach') : ($error_message !== '' ? $error_message : __('Distribution status was not changed. Revoked links cannot be resumed.', 'backstage-outreach')), $updated ? 'info' : 'error');
	wp_safe_redirect(vms_pass_outreach_admin_page_url(array('campaign_id' => absint($row['campaign_id'] ?? 0))) . '#backstage-outreach-partners');
	exit;
}
add_action('admin_post_backstage_outreach_distribution_status', 'backstage_outreach_handle_distribution_status');

function backstage_outreach_handle_distribution_print(): void
{
	if (!current_user_can(vms_pass_claims_capability())) { wp_die(esc_html__('Access denied.', 'backstage-outreach')); }
	$id = backstage_outreach_request_absint($_GET, 'distribution_id');
	check_admin_referer('backstage_outreach_distribution_print_' . $id);
	global $wpdb;
	$row = $wpdb->get_row($wpdb->prepare('SELECT d.*, b.business_name, c.campaign_name, c.admissions_per_recipient, c.related_batch_id FROM %i d INNER JOIN %i b ON b.id=d.business_id INNER JOIN %i c ON c.id=d.campaign_id WHERE d.id=%d', backstage_outreach_business_table('campaign_businesses'), backstage_outreach_business_table('businesses'), vms_admission_table_pass_outreach_campaigns(), $id), ARRAY_A);
	if (!is_array($row)) { wp_die(esc_html__('Distribution not found.', 'backstage-outreach')); }
	$url = backstage_outreach_distribution_url($row);
	$qr = bvmgr_pass_claims_claim_qr_image_url($url);
	$is_coupon = backstage_outreach_discount_distribution_type($row) === 'coupon_backed';
	$terms = $is_coupon && function_exists('backstage_outreach_discount_terms_for_distribution') ? backstage_outreach_discount_terms_for_distribution($row) : null;
	$paid_value_label = is_array($terms) && function_exists('backstage_outreach_discount_terms_label') ? backstage_outreach_discount_terms_label($terms, false) : __('Admission discount', 'backstage-outreach');
	$branding = backstage_outreach_flyer_branding();
	$logo_url = (string) $branding['logo_url'];
	$eyebrow = $is_coupon ? __('Admission Offer', 'backstage-outreach') : __('Complimentary Guest Pass', 'backstage-outreach');
	$alt = $is_coupon ? __('Reusable Admission Offer QR', 'backstage-outreach') : __('Reusable partner Guest Pass QR', 'backstage-outreach');
	$callout = $is_coupon ? sprintf(__('Scan for %1$s for up to %2$d people', 'backstage-outreach'), $paid_value_label, max(1, absint($row['admissions_per_recipient'] ?? 1))) : __('Scan to claim your own Guest Pass', 'backstage-outreach');
	$note = $is_coupon
		? __('Choose eligible tickets after scanning. The advertised discount is applied automatically.', 'backstage-outreach')
		: __('Each guest receives a separate Guest Pass after a successful claim.', 'backstage-outreach');
	nocache_headers();
	echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><meta name="robots" content="noindex,nofollow"><title>' . esc_html((string) $row['business_name']) . '</title><style>@page{size:letter portrait;margin:.5in}*{box-sizing:border-box}body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;margin:0;background:#edf3f1;color:#17202a}.actions{display:flex;justify-content:center;padding:18px}.actions button{min-height:44px;padding:9px 18px;border:0;border-radius:8px;background:#146b55;color:#fff;font:inherit;font-weight:700}.sheet{width:min(7in,calc(100% - 24px));margin:0 auto 24px;padding:.55in;text-align:center;background:#fff;border:1px solid #d9e2ef;border-radius:18px}.logo{display:block;max-width:310px;max-height:120px;width:auto;height:auto;margin:0 auto 24px}.venue{font-size:1.6rem;font-weight:800;margin:0 0 24px}.eyebrow{text-transform:uppercase;letter-spacing:.12em;color:#146b55;font-weight:800}.business{overflow-wrap:anywhere;font-size:2.4rem;line-height:1.1}.qr-wrap{display:inline-block;padding:18px;background:#fff;border:1px solid #dfe6e3;border-radius:12px}.qr{display:block;width:min(3.1in,72vw);height:auto}.callout{font-size:1.3rem}.note{color:#526174;line-height:1.5}@media print{body{background:#fff}.actions{display:none}.sheet{width:100%;margin:0;padding:.15in;border:0;border-radius:0}.logo{max-height:.85in}.qr{width:2.8in}}</style></head><body><div class="actions"><button onclick="window.print()">' . esc_html__('Print / Save as PDF', 'backstage-outreach') . '</button></div><main class="sheet">';
	if ($logo_url !== '') {
		echo '<img class="logo" src="' . esc_url($logo_url) . '" alt="' . esc_attr((string) $branding['site_name']) . '">';
	} else {
		echo '<p class="venue">' . esc_html((string) $branding['site_name']) . '</p>';
	}
	echo '<p class="eyebrow">' . esc_html($eyebrow) . '</p><h1 class="business">' . esc_html((string) $row['business_name']) . '</h1><p>' . esc_html((string) $row['campaign_name']) . '</p><div class="qr-wrap"><img class="qr" src="' . esc_attr($qr) . '" alt="' . esc_attr($alt) . '"></div><p class="callout"><strong>' . esc_html($callout) . '</strong></p><p class="note">' . esc_html($note) . '</p></main></body></html>';
	exit;
}
add_action('admin_post_backstage_outreach_distribution_print', 'backstage_outreach_handle_distribution_print');

function backstage_outreach_render_flyer_artwork_control(string $prefix, int $attachment_id, string $label): void
{
	$input_id = 'backstage-outreach-' . sanitize_html_class($prefix) . '-artwork-id';
	$preview_id = 'backstage-outreach-' . sanitize_html_class($prefix) . '-artwork-preview';
	$url = backstage_outreach_flyer_design_attachment_url($attachment_id);
	echo '<div class="vms-pass-flyer-artwork-control" data-vms-flyer-artwork-control data-vms-artwork-prefix="' . esc_attr($prefix) . '"><input type="hidden" id="' . esc_attr($input_id) . '" name="' . esc_attr($prefix . '_artwork_id') . '" value="' . esc_attr((string) $attachment_id) . '"><div id="' . esc_attr($preview_id) . '" class="vms-pass-flyer-artwork-preview"' . ($url === '' ? ' hidden' : '') . '>';
	if ($url !== '') {
		echo '<img src="' . esc_url($url) . '" alt="">';
	}
	echo '</div><p class="vms-pass-actions"><button type="button" class="button" data-vms-artwork-select data-vms-artwork-target="' . esc_attr($input_id) . '" data-vms-artwork-preview="' . esc_attr($preview_id) . '">' . esc_html($url === '' ? $label : __('Replace artwork', 'backstage-outreach')) . '</button> <button type="button" class="button-link-delete" data-vms-artwork-remove data-vms-artwork-target="' . esc_attr($input_id) . '" data-vms-artwork-preview="' . esc_attr($preview_id) . '"' . ($url === '' ? ' hidden' : '') . '>' . esc_html__('Remove artwork', 'backstage-outreach') . '</button></p></div>';
}

function backstage_outreach_render_flyer_layout_controls(string $prefix, array $design): void
{
	$name = static fn(string $field): string => $prefix . '_' . $field;
	echo '<fieldset class="vms-pass-span-2 vms-pass-flyer-layout-fields" data-vms-flyer-layout-fields="' . esc_attr($prefix) . '"><legend>' . esc_html__('Page composition', 'backstage-outreach') . '</legend><div class="vms-pass-grid">';
	echo '<label>' . esc_html__('Page orientation', 'backstage-outreach') . '<select name="' . esc_attr($name('orientation')) . '" data-vms-flyer-orientation="' . esc_attr($prefix) . '"><option value="portrait"' . selected((string) $design['orientation'], 'portrait', false) . '>' . esc_html__('US Letter portrait — 8.5 × 11 inches', 'backstage-outreach') . '</option><option value="landscape"' . selected((string) $design['orientation'], 'landscape', false) . '>' . esc_html__('US Letter landscape — 11 × 8.5 inches', 'backstage-outreach') . '</option></select></label>';
	echo '<label>' . esc_html__('Artwork composition', 'backstage-outreach') . '<select name="' . esc_attr($name('composition')) . '"><option value="panels"' . selected((string) $design['composition'], 'panels', false) . '>' . esc_html__('Separate artwork and offer panels', 'backstage-outreach') . '</option><option value="full"' . selected((string) $design['composition'], 'full', false) . '>' . esc_html__('Full-page artwork with opaque offer panel', 'backstage-outreach') . '</option></select></label>';
	echo '<input type="hidden" name="' . esc_attr($name('panel_position')) . '" value="bottom"><p class="description">' . esc_html__('Portrait places the offer below the artwork. Landscape uses a shallow full-width offer band below the artwork.', 'backstage-outreach') . '</p>';
	echo '<fieldset><legend>' . esc_html__('Optional live branding', 'backstage-outreach') . '</legend><label class="vms-pass-inline-check"><input type="checkbox" name="' . esc_attr($name('show_logo')) . '" value="1"' . checked(!empty($design['show_logo']), true, false) . '> <span>' . esc_html__('Show a separate venue logo', 'backstage-outreach') . '</span></label><label class="vms-pass-inline-check"><input type="checkbox" name="' . esc_attr($name('show_heading')) . '" value="1"' . checked(!empty($design['show_heading']), true, false) . '> <span>' . esc_html__('Show the public heading', 'backstage-outreach') . '</span></label><label class="vms-pass-inline-check"><input type="checkbox" name="' . esc_attr($name('show_subheading')) . '" value="1"' . checked(!empty($design['show_subheading']), true, false) . '> <span>' . esc_html__('Show the public subheading', 'backstage-outreach') . '</span></label></fieldset>';
	echo '</div><p class="description">' . esc_html__('Full-page artwork stays proportional and is never stretched or silently cropped. Leave clear space at the bottom for its opaque offer panel. Separate panels show the entire artwork. The Letter page keeps its selected proportions; printer-driver orientation remains under the person printing.', 'backstage-outreach') . '</p></fieldset>';
}

function backstage_outreach_render_flyer_design_panel(array $campaign): void
{
	$campaign_id = absint($campaign['id'] ?? 0);
	$defaults = backstage_outreach_flyer_default_design();
	$override = backstage_outreach_flyer_campaign_design($campaign_id);
	$resolved_batch = function_exists('bvmgr_pass_claims_get_batch_by_id') ? bvmgr_pass_claims_get_batch_by_id(absint($campaign['related_batch_id'] ?? 0)) : null;
	$resolved = backstage_outreach_resolved_flyer_design($campaign_id, is_array($resolved_batch) ? $resolved_batch : array());
	echo '<div id="backstage-outreach-flyer-design" class="vms-pass-preview-summary vms-pass-flyer-design" tabindex="-1"><h3>' . esc_html__('Flyer design', 'backstage-outreach') . '</h3><p>' . esc_html__('Set one public design for this campaign. Business name, exact offer, dates, limits, expiry, and each signed QR are added separately for every linked business.', 'backstage-outreach') . '</p>';
	echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="backstage_outreach_flyer_design"><input type="hidden" name="campaign_id" value="' . esc_attr((string) $campaign_id) . '">';
	wp_nonce_field('backstage_outreach_flyer_design');
	if (current_user_can('manage_options')) {
		echo '<details class="vms-pass-flyer-defaults"><summary>' . esc_html__('Venue flyer defaults', 'backstage-outreach') . '</summary><p class="description">' . esc_html__('Used by reusable-business campaigns that do not set a campaign override.', 'backstage-outreach') . '</p><div class="vms-pass-grid"><label>' . esc_html__('Default public heading', 'backstage-outreach') . '<input type="text" name="venue_flyer_heading" value="' . esc_attr((string) $defaults['heading']) . '" maxlength="120"><span class="description">' . esc_html__('Maximum 120 characters.', 'backstage-outreach') . '</span></label><label>' . esc_html__('Default public subheading', 'backstage-outreach') . '<input type="text" name="venue_flyer_subheading" value="' . esc_attr((string) $defaults['subheading']) . '" maxlength="180"><span class="description">' . esc_html__('Maximum 180 characters.', 'backstage-outreach') . '</span></label>';
		backstage_outreach_render_flyer_layout_controls('venue', $defaults);
		echo '<div class="vms-pass-span-2"><strong>' . esc_html__('Default artwork', 'backstage-outreach') . '</strong><p class="description">' . esc_html__('Choose a high-resolution landscape or portrait poster from the Media Library. The complete image is shown by default without stretching or silent cropping.', 'backstage-outreach') . '</p>';
		backstage_outreach_render_flyer_artwork_control('venue', (int) $defaults['artwork_id'], __('Choose default artwork', 'backstage-outreach'));
		echo '</div></div></details>';
	}
	echo '<div class="vms-pass-grid"><label>' . esc_html__('Campaign public heading', 'backstage-outreach') . '<input type="text" name="campaign_flyer_heading" value="' . esc_attr((string) $override['heading']) . '" maxlength="120" placeholder="' . esc_attr((string) $defaults['heading']) . '"><span class="description">' . esc_html__('Leave blank to use the venue default. Maximum 120 characters.', 'backstage-outreach') . '</span></label><label>' . esc_html__('Campaign public subheading', 'backstage-outreach') . '<input type="text" name="campaign_flyer_subheading" value="' . esc_attr((string) $override['subheading']) . '" maxlength="180" placeholder="' . esc_attr((string) $defaults['subheading']) . '"><span class="description">' . esc_html__('Leave blank to use the venue default. Maximum 180 characters.', 'backstage-outreach') . '</span></label><fieldset class="vms-pass-span-2"><legend>' . esc_html__('Campaign page composition', 'backstage-outreach') . '</legend><label class="vms-pass-inline-check"><input type="radio" name="campaign_layout_mode" value="inherit" data-vms-flyer-layout-mode' . checked($override['layout_mode'], 'inherit', false) . '> <span>' . esc_html__('Use venue composition defaults', 'backstage-outreach') . '</span></label><label class="vms-pass-inline-check"><input type="radio" name="campaign_layout_mode" value="custom" data-vms-flyer-layout-mode' . checked($override['layout_mode'], 'custom', false) . '> <span>' . esc_html__('Use campaign composition', 'backstage-outreach') . '</span></label></fieldset>';
	backstage_outreach_render_flyer_layout_controls('campaign', $override);
	echo '<fieldset class="vms-pass-span-2"><legend>' . esc_html__('Campaign artwork', 'backstage-outreach') . '</legend><label class="vms-pass-inline-check"><input type="radio" name="campaign_artwork_mode" value="inherit"' . checked($override['artwork_mode'], 'inherit', false) . '> <span>' . esc_html__('Automatic — selected One Event artwork, then venue default', 'backstage-outreach') . '</span></label><label class="vms-pass-inline-check"><input type="radio" name="campaign_artwork_mode" value="custom"' . checked($override['artwork_mode'], 'custom', false) . '> <span>' . esc_html__('Use campaign artwork', 'backstage-outreach') . '</span></label><label class="vms-pass-inline-check"><input type="radio" name="campaign_artwork_mode" value="none"' . checked($override['artwork_mode'], 'none', false) . '> <span>' . esc_html__('No artwork', 'backstage-outreach') . '</span></label><p class="description">' . esc_html__('Automatic mode follows the reviewed batch. A One Event batch uses that Event Plan’s linked public event artwork when available; otherwise it uses the venue default. Custom artwork and No artwork remain explicit overrides.', 'backstage-outreach') . '</p><p class="description"><strong>' . esc_html__('Current artwork source:', 'backstage-outreach') . '</strong> <span data-vms-flyer-artwork-source>' . esc_html((string) $resolved['artwork_source_label']) . '</span></p><p class="description">' . esc_html__('The poster is visual presentation only. Exact offer terms, business attribution, dates, limits, expiry, and the signed customer QR remain separate live content.', 'backstage-outreach') . '</p>';
	backstage_outreach_render_flyer_artwork_control('campaign', (int) $override['artwork_id'], __('Choose campaign artwork', 'backstage-outreach'));
	echo '</fieldset></div><p><button class="button button-primary">' . esc_html__('Save flyer design', 'backstage-outreach') . '</button></p></form>';
	if (sanitize_key((string) ($campaign['status'] ?? '')) !== 'active') {
		echo '<div class="notice notice-warning inline"><p><strong>' . esc_html__('Public links are unavailable while this campaign is a draft.', 'backstage-outreach') . '</strong> ' . esc_html__('Saving a flyer design does not activate the campaign or send anything.', 'backstage-outreach') . ' <a href="#vms-outreach-campaign-status" data-vms-open-section-target="vms-outreach-campaign-status">' . esc_html__('Go to the existing campaign status control', 'backstage-outreach') . '</a>.</p></div>';
	}
	if ((string) $resolved['artwork_url'] !== '') {
		echo '<p class="description">' . esc_html__('Current resolved design includes artwork.', 'backstage-outreach') . '</p>';
	}
	$preview_rows = backstage_outreach_distribution_rows($campaign_id);
	$active_preview_rows = array_values(array_filter($preview_rows, static fn(array $row): bool => (string) ($row['status'] ?? '') === 'active'));
	$preview_row = $active_preview_rows[0] ?? null;
	if (is_array($preview_row) && sanitize_key((string) ($campaign['status'] ?? '')) === 'active') {
		$preview_url = backstage_outreach_distribution_flyer_url($preview_row);
		echo '<div class="vms-pass-flyer-live-preview" data-vms-flyer-preview-orientation="' . esc_attr((string) $resolved['orientation']) . '"><h4>' . esc_html__('Saved flyer preview', 'backstage-outreach') . '</h4><p class="description">' . esc_html__('This is the complete signed public flyer. Preview, printing, and PDF download use this same composition.', 'backstage-outreach') . '</p><iframe src="' . esc_url($preview_url) . '" title="' . esc_attr__('Saved business flyer preview', 'backstage-outreach') . '" loading="lazy"></iframe><p><a class="button" href="' . esc_url($preview_url) . '" target="_blank" rel="noopener">' . esc_html__('Open full flyer preview', 'backstage-outreach') . '</a></p></div>';
	}
	echo '</div>';
}

function backstage_outreach_render_business_distribution_panel(array $campaign): void
{
	$campaign_id = absint($campaign['id'] ?? 0);
	$source_id = absint($campaign['related_source_id'] ?? 0);
	if ($campaign_id <= 0) {
		return;
	}
	$businesses = $source_id > 0 ? backstage_outreach_source_businesses($source_id, false) : array();
	$rows = backstage_outreach_distribution_rows($campaign_id);
	$by_business = array();
	foreach ($rows as $row) {
		$by_business[(int) $row['business_id']] = $row;
	}
	$active_row = null;
	foreach ($rows as $candidate) {
		if ((string) ($candidate['status'] ?? '') === 'active') {
			$active_row = $candidate;
			break;
		}
	}
	$batch = absint($campaign['related_batch_id'] ?? 0) > 0 ? bvmgr_pass_claims_get_batch_by_id(absint($campaign['related_batch_id'])) : null;
	$batch_value_type = is_array($batch) ? sanitize_key((string) ($batch['value_type'] ?? '')) : '';
	$batch_defaults_to_paid = in_array($batch_value_type, array('percent', 'fixed'), true);
	$default_type = $batch_defaults_to_paid ? 'coupon_backed' : 'complimentary';
	$handoff = function_exists('vms_pass_outreach_business_distribution_handoff_key') ? get_transient(vms_pass_outreach_business_distribution_handoff_key($campaign_id)) : array();
	if (!is_array($handoff)
		|| absint($handoff['source_id'] ?? 0) !== $source_id
		|| absint($handoff['batch_id'] ?? 0) !== absint($campaign['related_batch_id'] ?? 0)) {
		$handoff = array();
	}
	$default_cap = is_array($active_row) ? absint($active_row['admission_cap'] ?? 0) : absint($handoff['admission_cap'] ?? 0);
	$default_order_cap = is_array($active_row) ? absint($active_row['order_cap'] ?? 0) : 0;
	$default_expiry = is_array($active_row) && !empty($active_row['expires_at']) ? str_replace(' ', 'T', substr((string) $active_row['expires_at'], 0, 16)) : '';
	$paid_terms = is_array($batch) && function_exists('backstage_outreach_discount_batch_terms') ? backstage_outreach_discount_batch_terms($batch) : null;
	$paid_type_label = is_array($paid_terms) && function_exists('backstage_outreach_discount_terms_label')
		? backstage_outreach_discount_terms_label($paid_terms)
		: __('Admission Offer', 'backstage-outreach');
	$preview = get_transient(backstage_outreach_campaign_business_preview_key($campaign_id));
	$form_flash = get_transient(backstage_outreach_campaign_business_form_key($campaign_id));
	if (is_array($form_flash)) {
		delete_transient(backstage_outreach_campaign_business_form_key($campaign_id));
	}
	$pending = is_array($preview) ? $preview : (is_array($form_flash) ? $form_flash : array());
	if (!empty($pending)) {
		$default_type = sanitize_key((string) ($pending['distribution_type'] ?? $default_type));
		$default_cap = absint($pending['admission_cap'] ?? $default_cap);
		$default_order_cap = absint($pending['order_cap'] ?? $default_order_cap);
		$pending_expiry = sanitize_text_field((string) ($pending['expires_input'] ?? $pending['expires_at'] ?? ''));
		$default_expiry = $pending_expiry !== '' ? str_replace(' ', 'T', substr($pending_expiry, 0, 16)) : '';
	}
	$pending_ids = !empty($pending['business_ids']) ? array_map('absint', (array) $pending['business_ids']) : array();
	echo '<section id="backstage-outreach-partners" class="vms-pass-card" data-vms-tour="outreach-business-distribution"><h2>' . esc_html__('Step 5 — Generate, Print, or Export Business QRs', 'backstage-outreach') . '</h2>';
	if (function_exists('backstage_outreach_help_button')) {
		echo '<p class="vms-pass-actions">' . backstage_outreach_help_button(
			'backstage-outreach.business-qr-setup',
			'outreach-business-help',
			__('Business QR Setup Help', 'backstage-outreach')
		) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Button HTML is produced by BVM's escaped help-button renderer.
	}
	echo '<p class="description">' . esc_html__('Each selected business receives one reusable signed referral link. The linked batch determines complimentary admission or the exact managed WooCommerce percentage/fixed discount. Opening a link never counts as a completed claim or paid redemption.', 'backstage-outreach') . '</p>';
	if ($source_id <= 0 || absint($campaign['related_batch_id'] ?? 0) <= 0) {
		echo '<div class="notice notice-warning inline"><p>' . esc_html__('Save this campaign with both a Tracking Source and a Use Existing Batch / Invite Link Pool selection first. Create the missing Source or batch on the Guest Passes screen, then return here.', 'backstage-outreach') . '</p></div></section>';
		return;
	}
	if (empty($businesses)) {
		echo '<div class="notice notice-warning inline"><p>' . esc_html__('No active businesses are linked to this campaign Source. Open the Source, add or reactivate a business, then return here to select it.', 'backstage-outreach') . '</p></div>';
	}
	if (is_array($batch)) {
		echo '<div class="vms-pass-preview-summary vms-business-distribution-capacity"><table class="widefat striped"><tbody>';
		if (function_exists('vms_pass_outreach_business_batch_offer_label')) {
			echo '<tr><th scope="row">' . esc_html__('Reviewed Batch Offer', 'backstage-outreach') . '</th><td>' . esc_html(vms_pass_outreach_business_batch_offer_label($batch)) . '</td></tr>';
		}
		if (function_exists('vms_pass_outreach_business_batch_scope_label')) {
			echo '<tr><th scope="row">' . esc_html__('Scope', 'backstage-outreach') . '</th><td>' . esc_html(vms_pass_outreach_business_batch_scope_label($batch)) . '</td></tr>';
		}
		echo '<tr><th scope="row">' . esc_html__('Available Business Links / QRs', 'backstage-outreach') . '</th><td>' . esc_html((string) count($businesses)) . '</td></tr>';
		echo '<tr><th scope="row">' . esc_html__('Admissions per customer', 'backstage-outreach') . '</th><td>' . esc_html((string) max(1, absint($batch['admissions_per_link'] ?? 1))) . '</td></tr>';
		echo '<tr><th scope="row">' . esc_html__('Total admissions allowed per business', 'backstage-outreach') . '</th><td>' . esc_html($default_cap > 0 ? (string) $default_cap : __('No separate per-business limit', 'backstage-outreach')) . '</td></tr>';
		echo '<tr><th scope="row">' . esc_html__('Total admissions available across all businesses', 'backstage-outreach') . '</th><td>' . esc_html((string) absint($batch['total_admission_cap'] ?? 0)) . '</td></tr>';
		echo '</tbody></table><p class="description">' . esc_html__('Admissions per customer limits how many people one customer can claim or purchase for. The per-business and overall limits count admitted people, not customers or orders. Shared overall capacity can run out before every business reaches its individual maximum. Business QR count is based on active Source memberships and does not generate individual claim links.', 'backstage-outreach') . '</p></div>';
	}
	backstage_outreach_render_flyer_design_panel($campaign);
	echo '<ol class="vms-pass-business-steps vms-pass-business-steps--distribution" aria-label="' . esc_attr__('Business link workflow', 'backstage-outreach') . '"><li class="is-current"><span>1</span>' . esc_html__('Select businesses', 'backstage-outreach') . '</li><li' . (is_array($preview) ? ' class="is-current"' : '') . '><span>2</span>' . esc_html__('Review', 'backstage-outreach') . '</li><li' . (!empty($rows) && !is_array($preview) ? ' class="is-complete"' : '') . '><span>3</span>' . esc_html__('Save links', 'backstage-outreach') . '</li><li' . (!empty($rows) ? ' class="is-current"' : '') . '><span>4</span>' . esc_html__('Share with businesses', 'backstage-outreach') . '</li></ol>';
	echo '<form id="backstage-outreach-business-selection" method="post" action="' . esc_url(admin_url('admin-post.php')) . '" data-vms-tour="outreach-business-selection"><input type="hidden" name="action" value="backstage_outreach_campaign_businesses"><input type="hidden" name="campaign_id" value="' . esc_attr((string) $campaign_id) . '"><input type="hidden" name="distribution_mode" value="preview">';
	wp_nonce_field('backstage_outreach_campaign_businesses');
	$all_selected = !empty($businesses) && (!empty($pending_ids) ? count($pending_ids) === count($businesses) : (empty($rows) || count(array_filter($rows, static fn(array $row): bool => (string) ($row['status'] ?? '') === 'active')) === count($businesses)));
	echo '<p><label><input type="checkbox" data-backstage-select-all' . checked($all_selected, true, false) . '> ' . esc_html__('Select all active businesses', 'backstage-outreach') . '</label></p><div class="vms-pass-grid">';
	foreach ($businesses as $business) {
		$id = (int) $business['id'];
		$checked = !empty($pending_ids) ? in_array($id, $pending_ids, true) : (empty($rows) || (isset($by_business[$id]) && (string) $by_business[$id]['status'] === 'active'));
		echo '<label class="vms-pass-checkbox"><input type="checkbox" name="business_ids[]" value="' . esc_attr((string) $id) . '"' . checked($checked, true, false) . ' data-backstage-business> <span><strong>' . esc_html((string) $business['business_name']) . '</strong><br><small>' . esc_html((string) $business['contact_name']) . '</small></span></label>';
	}
	echo '<label data-vms-tour="outreach-distribution-type">' . esc_html__('Distribution type', 'backstage-outreach') . '<select name="distribution_type">';
	if ($batch_value_type === 'free') {
		echo '<option value="complimentary"' . selected($default_type, 'complimentary', false) . '>' . esc_html__('Complimentary Guest Pass', 'backstage-outreach') . '</option>';
	} else {
		echo '<option value="coupon_backed"' . selected($default_type, 'coupon_backed', false) . '>' . esc_html($paid_type_label) . '</option>';
	}
	echo '</select><span class="description">' . esc_html($batch_value_type === 'free' ? __('Free batches create complimentary claims. Existing paid 50% links backed by a Free batch remain valid, but new paid links require a reviewed Percentage Off or Fixed Amount Off batch.', 'backstage-outreach') : __('The paid Admission Offer uses the exact percentage or fixed-per-admission amount in the reviewed batch. Complimentary claims require a Free batch.', 'backstage-outreach')) . '</span></label>';
	echo '<fieldset id="backstage-outreach-business-limits" class="vms-pass-span-2 vms-pass-limit-group" data-vms-tour="outreach-business-limits"><legend>' . esc_html__('Admission limits', 'backstage-outreach') . '</legend><div class="vms-pass-grid"><label>' . esc_html__('Admissions per customer', 'backstage-outreach') . '<input type="number" value="' . esc_attr((string) max(1, absint($batch['admissions_per_link'] ?? 1))) . '" readonly><span class="description">' . esc_html__('Maximum admissions one customer can claim or purchase through a business link. Change this only by reviewing the offer batch.', 'backstage-outreach') . '</span></label><label>' . esc_html__('Total admissions allowed per business', 'backstage-outreach') . '<input type="number" min="0" max="50000" name="admission_cap" value="' . esc_attr((string) $default_cap) . '"><span class="description">' . esc_html__('For Admission Offers, this counts discounted admissions. For complimentary links, it counts Guest Pass admissions. Leave 0 for no separate per-business limit.', 'backstage-outreach') . '</span></label><label>' . esc_html__('Total admissions available across all businesses', 'backstage-outreach') . '<input type="number" value="' . esc_attr((string) absint($batch['total_admission_cap'] ?? 0)) . '" readonly><span class="description">' . esc_html__('This overall pool is shared. A per-business maximum does not reserve admissions, so the shared pool can run out before every business reaches its maximum.', 'backstage-outreach') . '</span></label></div></fieldset>';
	echo '<label>' . esc_html__('Optional paid-order cap per business', 'backstage-outreach') . '<input type="number" min="0" name="order_cap" value="' . esc_attr((string) $default_order_cap) . '"><span class="description">' . esc_html__('Applied as the managed coupon usage limit. 0 means unlimited orders subject to ticket limits.', 'backstage-outreach') . '</span></label>';
	echo '<label id="backstage-outreach-business-expiry">' . esc_html__('Optional distribution expiry', 'backstage-outreach') . '<input type="datetime-local" name="expires_at" value="' . esc_attr($default_expiry) . '"><span class="description">' . esc_html__('Interpreted in the site timezone and applied to every link in this reviewed selection.', 'backstage-outreach') . '</span></label></div>';
	if (is_array($batch)) {
		echo '<p class="description">' . esc_html(sprintf(__('Linked batch: %1$s · %2$s %3$s. Coupon-backed distributions create one managed native coupon per business for attribution and per-business order limits; unrelated coupons are never modified.', 'backstage-outreach'), (string) ($batch['batch_name'] ?? ('#' . absint($campaign['related_batch_id']))), (string) ($batch['value_type'] ?? ''), (string) ($batch['value_amount'] ?? ''))) . '</p>';
	}
	echo '<p><button class="button button-primary" data-vms-tour="outreach-review-selection">' . esc_html__('Review Selection', 'backstage-outreach') . '</button></p></form>';
	if (is_array($preview)) {
		$preview_ids = array_map('absint', (array) ($preview['business_ids'] ?? array()));
		$preview_names = array();
		foreach ($businesses as $business) {
			if (in_array((int) $business['id'], $preview_ids, true)) {
				$preview_names[] = (string) $business['business_name'];
			}
		}
		echo '<div id="backstage-outreach-business-review" class="vms-pass-preview-summary vms-pass-business-review" data-vms-tour="outreach-reviewed-preview" tabindex="-1"><h3>' . esc_html__('Review selected businesses and link terms', 'backstage-outreach') . '</h3><p>' . esc_html(sprintf(_n('%d active business link will remain or become active.', '%d active business links will remain or become active.', count($preview_names), 'backstage-outreach'), count($preview_names))) . ' ' . esc_html__('Previously linked businesses omitted from this selection will be paused. No customer invitations are sent.', 'backstage-outreach') . '</p>';
		if (!empty($preview_names)) {
			echo '<div class="vms-pass-table-scroll"><table class="widefat striped"><thead><tr><th>' . esc_html__('Business', 'backstage-outreach') . '</th><th>' . esc_html__('Contact email', 'backstage-outreach') . '</th></tr></thead><tbody>';
			foreach ($businesses as $business) {
				if (!in_array((int) $business['id'], $preview_ids, true)) {
					continue;
				}
				$email = sanitize_email((string) ($business['email'] ?? ''));
				echo '<tr><td>' . esc_html((string) $business['business_name']) . '</td><td>' . esc_html($email !== '' ? $email : __('Not provided — copyable sharing remains available', 'backstage-outreach')) . '</td></tr>';
			}
			echo '</tbody></table></div>';
		}
		$type_label = sanitize_key((string) ($preview['distribution_type'] ?? 'complimentary')) === 'coupon_backed' ? $paid_type_label : __('Complimentary Guest Pass', 'backstage-outreach');
		echo '<table class="widefat striped"><tbody><tr><th scope="row">' . esc_html__('Offer', 'backstage-outreach') . '</th><td>' . esc_html($type_label) . '</td></tr><tr><th scope="row">' . esc_html__('Admissions per customer', 'backstage-outreach') . '</th><td>' . esc_html((string) max(1, absint($batch['admissions_per_link'] ?? 1))) . '</td></tr><tr><th scope="row">' . esc_html__('Total admissions allowed per business', 'backstage-outreach') . '</th><td>' . esc_html(absint($preview['admission_cap'] ?? 0) > 0 ? (string) absint($preview['admission_cap']) : __('No separate limit', 'backstage-outreach')) . '</td></tr><tr><th scope="row">' . esc_html__('Total admissions available across all businesses', 'backstage-outreach') . '</th><td>' . esc_html((string) absint($batch['total_admission_cap'] ?? 0)) . '</td></tr><tr><th scope="row">' . esc_html__('Optional paid-order cap per business', 'backstage-outreach') . '</th><td>' . esc_html((string) absint($preview['order_cap'] ?? 0)) . '</td></tr><tr><th scope="row">' . esc_html__('Expiry', 'backstage-outreach') . '</th><td>' . esc_html((string) ($preview['expires_at'] ?? '') !== '' ? backstage_outreach_business_format_local_datetime((string) $preview['expires_at']) : __('None', 'backstage-outreach')) . '</td></tr></tbody></table><p class="description">' . esc_html__('The overall batch capacity is shared and can run out before a business reaches its individual maximum. Saving uses exactly this reviewed selection and these reviewed terms.', 'backstage-outreach') . '</p><p class="description" data-vms-business-review-stale hidden>' . esc_html__('Selection or terms changed after review. Review again before saving links.', 'backstage-outreach') . '</p><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" data-vms-business-review-commit><input type="hidden" name="action" value="backstage_outreach_campaign_businesses"><input type="hidden" name="campaign_id" value="' . esc_attr((string) $campaign_id) . '"><input type="hidden" name="distribution_mode" value="commit"><input type="hidden" name="review_token" value="' . esc_attr((string) ($preview['review_token'] ?? '')) . '">';
		wp_nonce_field('backstage_outreach_campaign_businesses');
		echo '<button class="button button-primary">' . esc_html__('Save Reviewed Links', 'backstage-outreach') . '</button></form></div>';
	}
	if (!empty($rows)) {
		echo '<table class="widefat striped vms-pass-business-results-table" data-vms-tour="outreach-business-results"><thead><tr><th>' . esc_html__('Business', 'backstage-outreach') . '</th><th>' . esc_html__('Status', 'backstage-outreach') . '</th><th>' . esc_html__('Links and controls', 'backstage-outreach') . '</th><th>' . esc_html__('Results', 'backstage-outreach') . '</th></tr></thead><tbody>';
		global $wpdb;
		foreach ($rows as $row) {
			$url = backstage_outreach_distribution_url($row);
			$flyer_url = backstage_outreach_distribution_flyer_url($row);
			$link_input_id = 'backstage-business-link-' . absint($row['id'] ?? 0);
			$flyer_input_id = 'backstage-business-flyer-' . absint($row['id'] ?? 0);
			$qr = function_exists('bvmgr_pass_claims_claim_qr_image_url') ? bvmgr_pass_claims_claim_qr_image_url($url) : '';
			$is_coupon = backstage_outreach_discount_distribution_type($row) === 'coupon_backed';
			$stats = $is_coupon ? backstage_outreach_discount_paid_stats((int) $row['id']) : $wpdb->get_row($wpdb->prepare("SELECT COUNT(DISTINCT dc.pass_claim_id) claims, COUNT(DISTINCT e.id) admissions, COALESCE(SUM(e.checked_in_qty),0) checked_in FROM %i dc LEFT JOIN %i e ON e.pass_claim_id=dc.pass_claim_id WHERE dc.distribution_id = %d AND dc.status = 'fulfilled'", backstage_outreach_business_table('distribution_claims'), bvmgr_admission_table_entries(), (int) $row['id']), ARRAY_A);
			$coupon_uses = $is_coupon && absint($row['coupon_id'] ?? 0) > 0 && class_exists('WC_Coupon') ? absint((new WC_Coupon(absint($row['coupon_id'])))->get_usage_count()) : 0;
			$row_paid_terms = $is_coupon && function_exists('backstage_outreach_discount_terms_for_distribution') ? backstage_outreach_discount_terms_for_distribution(array_merge($row, array('related_batch_id' => absint($campaign['related_batch_id'] ?? 0)))) : null;
			$row_type_label = $is_coupon && is_array($row_paid_terms) && function_exists('backstage_outreach_discount_terms_label') ? backstage_outreach_discount_terms_label($row_paid_terms) : __('Admission Offer', 'backstage-outreach');
			echo '<tr><td data-label="' . esc_attr__('Business', 'backstage-outreach') . '"><strong>' . esc_html((string) $row['business_name']) . '</strong><div class="description">' . esc_html($is_coupon ? $row_type_label : __('Complimentary Guest Pass', 'backstage-outreach')) . ($is_coupon && !empty($row['coupon_code']) ? ' | ' . esc_html((string) $row['coupon_code']) : '') . '</div></td><td data-label="' . esc_attr__('Status', 'backstage-outreach') . '">' . esc_html((string) $row['status']) . '</td><td data-label="' . esc_attr__('Links and controls', 'backstage-outreach') . '" data-vms-tour="outreach-qr-actions"><div class="vms-pass-business-action-groups"><fieldset><legend>' . esc_html__('Customer offer', 'backstage-outreach') . '</legend><p class="description">' . esc_html__('Share or download the QR for the customer-facing offer page.', 'backstage-outreach') . '</p><input id="' . esc_attr($link_input_id) . '" class="regular-text" readonly value="' . esc_attr($url) . '" data-backstage-copy-value aria-label="' . esc_attr__('Customer offer URL', 'backstage-outreach') . '"> <button type="button" class="button button-small" data-backstage-copy data-backstage-copy-target="' . esc_attr($link_input_id) . '">' . esc_html__('Copy link', 'backstage-outreach') . '</button>';
			if ($qr !== '') {
				echo ' <a class="button button-small" href="' . esc_url($qr) . '" target="_blank" rel="noopener" download>' . esc_html__('Download QR', 'backstage-outreach') . '</a>';
			}
			$print = wp_nonce_url(add_query_arg(array('action' => 'backstage_outreach_distribution_print', 'distribution_id' => (int) $row['id']), admin_url('admin-post.php')), 'backstage_outreach_distribution_print_' . (int) $row['id']);
			echo ' <a class="button button-small" href="' . esc_url($print) . '" target="_blank">' . esc_html__('Print QR', 'backstage-outreach') . '</a></fieldset><fieldset><legend>' . esc_html__('Printable flyer', 'backstage-outreach') . '</legend><p class="description">' . esc_html__('This signed public page is safe to send to the business for reception-desk printing.', 'backstage-outreach') . '</p><div class="vms-pass-business-flyer-actions"><input id="' . esc_attr($flyer_input_id) . '" class="regular-text" readonly value="' . esc_attr($flyer_url) . '" data-backstage-copy-value aria-label="' . esc_attr__('Shareable reception-desk flyer URL', 'backstage-outreach') . '"> <button type="button" class="button button-small" data-backstage-copy data-backstage-copy-target="' . esc_attr($flyer_input_id) . '">' . esc_html__('Copy link', 'backstage-outreach') . '</button> <a class="button button-small" href="' . esc_url($flyer_url) . '" target="_blank" rel="noopener">' . esc_html__('Open flyer', 'backstage-outreach') . '</a></div></fieldset><fieldset><legend>' . esc_html__('Manage', 'backstage-outreach') . '</legend><p class="description">' . esc_html__('Pause temporarily, resume a paused link, or revoke it permanently.', 'backstage-outreach') . '</p>';
			if ((string) $row['status'] !== 'revoked') {
				$next_status = (string) $row['status'] === 'active' ? 'paused' : 'active';
				$status_url = wp_nonce_url(add_query_arg(array('action' => 'backstage_outreach_distribution_status', 'distribution_id' => (int) $row['id'], 'status' => $next_status), admin_url('admin-post.php')), 'backstage_outreach_distribution_status_' . (int) $row['id'] . '_' . $next_status);
				$revoke_url = wp_nonce_url(add_query_arg(array('action' => 'backstage_outreach_distribution_status', 'distribution_id' => (int) $row['id'], 'status' => 'revoked'), admin_url('admin-post.php')), 'backstage_outreach_distribution_status_' . (int) $row['id'] . '_revoked');
				echo ' <span data-vms-tour="outreach-offer-lifecycle"><a class="button button-small" href="' . esc_url($status_url) . '">' . esc_html($next_status === 'active' ? __('Resume', 'backstage-outreach') : __('Pause', 'backstage-outreach')) . '</a> <a class="button button-small" href="' . esc_url($revoke_url) . '" onclick="return confirm(' . esc_attr(wp_json_encode(__('Revoke this reusable link? Existing customer passes remain valid.', 'backstage-outreach'))) . ');">' . esc_html__('Revoke', 'backstage-outreach') . '</a></span>';
			} else {
				echo ' <span class="description" data-vms-tour="outreach-offer-lifecycle">' . esc_html__('Revocation is permanent for this link.', 'backstage-outreach') . '</span>';
			}
			echo '</fieldset></div>';
			$currency = sanitize_text_field((string) ($stats['currency'] ?? ''));
			$revenue = trim(($currency !== '' ? $currency . ' ' : '') . number_format_i18n((float) ($stats['order_revenue'] ?? 0), 2));
			$results = $is_coupon
				? sprintf(__('%1$d coupon uses · %2$d paid orders · %3$d discounted tickets · %4$s net order revenue · %5$d review', 'backstage-outreach'), $coupon_uses, (int) ($stats['paid_orders'] ?? 0), (int) ($stats['discounted_tickets'] ?? 0), $revenue, (int) ($stats['review_required_orders'] ?? 0))
				: sprintf(__('%1$d claims · %2$d reserved admissions · %3$d checked in', 'backstage-outreach'), (int) ($stats['claims'] ?? 0), (int) ($stats['admissions'] ?? 0), (int) ($stats['checked_in'] ?? 0));
			echo '</td><td data-label="' . esc_attr__('Results', 'backstage-outreach') . '">' . esc_html($results) . '</td></tr>';
		}
		echo '</tbody></table><p><a class="button" href="' . esc_url(wp_nonce_url(add_query_arg(array('action' => 'backstage_outreach_distribution_export', 'campaign_id' => $campaign_id), admin_url('admin-post.php')), 'backstage_outreach_distribution_export_' . $campaign_id)) . '">' . esc_html__('Export Business Links / QRs', 'backstage-outreach') . '</a></p>';
	}
	$active_share_rows = array_values(array_filter($rows, static fn(array $row): bool => (string) ($row['status'] ?? '') === 'active'));
	if (!empty($active_share_rows) && is_array($batch)) {
		$stored_template = backstage_outreach_business_share_template($campaign_id);
		$stored_subject = (string) $stored_template['subject'];
		$stored_message = (string) $stored_template['message'];
		$share_review = get_transient(backstage_outreach_campaign_business_share_key($campaign_id));
		$share_subject = is_array($share_review) ? sanitize_text_field((string) ($share_review['subject'] ?? $stored_subject)) : $stored_subject;
		$share_message = is_array($share_review) ? sanitize_textarea_field((string) ($share_review['message'] ?? $stored_message)) : $stored_message;
		$sent_map = backstage_outreach_business_share_sent_map($campaign_id);
		echo '<div id="backstage-outreach-business-share" class="vms-pass-preview-summary vms-pass-business-share" tabindex="-1"><h3>' . esc_html__('Share with businesses', 'backstage-outreach') . '</h3><p>' . esc_html__('Review a campaign-specific introduction, then inspect each personalized message. Every linked business has copyable content; email delivery is available only for valid, unsuppressed addresses and requires an explicit reviewed send.', 'backstage-outreach') . '</p><p class="description">' . esc_html__('This workflow does not create Outreach recipients or individual Guest Pass claim links.', 'backstage-outreach') . '</p>';
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="backstage_outreach_business_share"><input type="hidden" name="campaign_id" value="' . esc_attr((string) $campaign_id) . '"><input type="hidden" name="share_mode" value="preview">';
		wp_nonce_field('backstage_outreach_business_share');
		echo '<div class="vms-pass-grid"><label class="vms-pass-span-2">' . esc_html__('Business-contact subject', 'backstage-outreach') . '<input type="text" name="business_share_subject" value="' . esc_attr($share_subject) . '" required><span class="description">' . esc_html__('Available tags: {business_name}, {contact_name}, {offer_terms}, {customer_url}, {flyer_url}.', 'backstage-outreach') . '</span></label><label class="vms-pass-span-2">' . esc_html__('Business-contact introduction', 'backstage-outreach') . '<textarea name="business_share_message" rows="5" required>' . esc_textarea($share_message) . '</textarea><span class="description">' . esc_html__('The exact reviewed offer, limits, dates, expiry, customer link, and flyer link are appended automatically to every message.', 'backstage-outreach') . '</span></label></div><p><button class="button button-primary">' . esc_html__('Save Template & Review Personalized Messages', 'backstage-outreach') . '</button></p></form>';
		if (is_array($share_review)) {
			echo '<div id="backstage-outreach-business-share-review" tabindex="-1"><h4>' . esc_html__('Reviewed personalized messages', 'backstage-outreach') . '</h4><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" data-vms-business-share-send><input type="hidden" name="action" value="backstage_outreach_business_share"><input type="hidden" name="campaign_id" value="' . esc_attr((string) $campaign_id) . '"><input type="hidden" name="share_mode" value="send"><input type="hidden" name="share_review_token" value="' . esc_attr((string) ($share_review['token'] ?? '')) . '">';
			wp_nonce_field('backstage_outreach_business_share');
			echo '<div class="vms-pass-table-scroll"><table class="widefat striped vms-pass-business-share-table"><thead><tr><th>' . esc_html__('Email', 'backstage-outreach') . '</th><th>' . esc_html__('Business / contact', 'backstage-outreach') . '</th><th>' . esc_html__('Personalized message and links', 'backstage-outreach') . '</th><th>' . esc_html__('Delivery status', 'backstage-outreach') . '</th></tr></thead><tbody>';
			$sendable_count = 0;
			foreach ($active_share_rows as $row) {
				$distribution_id = absint($row['id'] ?? 0);
				$context = backstage_outreach_business_share_context($row, $campaign, $batch, (string) $share_review['subject'], (string) $share_review['message']);
				$email = (string) $context['email'];
				$suppressed = $email !== '' && function_exists('vms_outreach_email_is_suppressed') && vms_outreach_email_is_suppressed($email);
				$already_sent = isset($sent_map[$distribution_id]);
				$effective_expiry = backstage_outreach_distribution_effective_expiry($row);
				$expired = $effective_expiry !== '' && backstage_outreach_business_now() > $effective_expiry;
				$sendable = $email !== '' && !$suppressed && !$already_sent && !$expired;
				if ($sendable) {
					$sendable_count++;
				}
				$subject_id = 'backstage-business-share-subject-' . $distribution_id;
				$message_id = 'backstage-business-share-message-' . $distribution_id;
				$status = $already_sent
					? sprintf(__('Handed off to mail system %s (delivery not confirmed)', 'backstage-outreach'), (string) $sent_map[$distribution_id])
					: ($expired ? __('Expired — email blocked', 'backstage-outreach') : ($suppressed ? __('Suppressed — email blocked', 'backstage-outreach') : ($email === '' ? __('No email — copy manually', 'backstage-outreach') : __('Eligible — not handed off', 'backstage-outreach'))));
				echo '<tr><td data-label="' . esc_attr__('Email', 'backstage-outreach') . '">';
				if ($sendable) {
					echo '<label><input type="checkbox" name="distribution_ids[]" value="' . esc_attr((string) $distribution_id) . '"> <span class="screen-reader-text">' . esc_html(sprintf(__('Send to %s', 'backstage-outreach'), (string) $row['business_name'])) . '</span>' . esc_html($email) . '</label>';
				} else {
					echo esc_html($email !== '' ? $email : __('Not provided', 'backstage-outreach'));
				}
				echo '</td><td data-label="' . esc_attr__('Business / contact', 'backstage-outreach') . '"><strong>' . esc_html((string) $row['business_name']) . '</strong><div class="description">' . esc_html((string) ($row['contact_name'] ?? '') !== '' ? (string) $row['contact_name'] : __('Contact name not provided', 'backstage-outreach')) . '</div></td><td data-label="' . esc_attr__('Personalized message and links', 'backstage-outreach') . '"><label class="screen-reader-text" for="' . esc_attr($subject_id) . '">' . esc_html__('Personalized subject', 'backstage-outreach') . '</label><input id="' . esc_attr($subject_id) . '" class="regular-text" readonly value="' . esc_attr((string) $context['subject']) . '"><button type="button" class="button button-small" data-backstage-copy data-backstage-copy-target="' . esc_attr($subject_id) . '">' . esc_html__('Copy subject', 'backstage-outreach') . '</button><label class="screen-reader-text" for="' . esc_attr($message_id) . '">' . esc_html__('Personalized message', 'backstage-outreach') . '</label><textarea id="' . esc_attr($message_id) . '" rows="12" readonly>' . esc_textarea((string) $context['message']) . '</textarea><button type="button" class="button button-small" data-backstage-copy data-backstage-copy-target="' . esc_attr($message_id) . '">' . esc_html__('Copy message', 'backstage-outreach') . '</button> <a class="button button-small" href="' . esc_url((string) $context['customer_url']) . '" target="_blank" rel="noopener">' . esc_html__('Open customer offer', 'backstage-outreach') . '</a> <a class="button button-small" href="' . esc_url((string) $context['flyer_url']) . '" target="_blank" rel="noopener">' . esc_html__('Open flyer', 'backstage-outreach') . '</a></td><td data-label="' . esc_attr__('Delivery status', 'backstage-outreach') . '">' . esc_html($status) . '</td></tr>';
			}
			echo '</tbody></table></div><p class="description">' . esc_html__('Only checked, visible addresses are handed to the configured mail system. Mail-system acceptance is recorded for duplicate prevention, but it does not confirm inbox delivery. Missing or suppressed addresses remain copyable.', 'backstage-outreach') . '</p><p><button class="button button-primary"' . disabled($sendable_count <= 0 || sanitize_key((string) ($campaign['status'] ?? '')) !== 'active', true, false) . '>' . esc_html__('Hand Off Reviewed Business Emails', 'backstage-outreach') . '</button>' . (sanitize_key((string) ($campaign['status'] ?? '')) !== 'active' ? ' <span class="description">' . esc_html__('Activate the campaign before email handoff. Copy actions remain available.', 'backstage-outreach') . '</span>' : '') . '</p></form></div>';
		}
		echo '</div>';
	}
	echo '<script>(function(){var section=document.getElementById("backstage-outreach-partners");if(!section){return;}var selection=section.querySelector("#backstage-outreach-business-selection");var review=section.querySelector("#backstage-outreach-business-review");var commit=section.querySelector("[data-vms-business-review-commit]");var stale=section.querySelector("[data-vms-business-review-stale]");var dirty=false;function invalidateReview(){if(!review||dirty){return;}dirty=true;review.classList.add("is-stale");if(stale){stale.hidden=false;}if(commit){commit.querySelectorAll("button,input").forEach(function(el){el.disabled=true;});}}var all=section.querySelector("[data-backstage-select-all]");if(all){all.addEventListener("change",function(){section.querySelectorAll("[data-backstage-business]").forEach(function(el){el.checked=all.checked;});invalidateReview();});}if(selection){selection.querySelectorAll("input,select").forEach(function(field){if(field.type!=="hidden"&&field.type!=="submit"){field.addEventListener("change",invalidateReview);field.addEventListener("input",invalidateReview);}});}section.querySelectorAll("[data-backstage-copy]").forEach(function(btn){btn.addEventListener("click",function(){var input=document.getElementById(btn.getAttribute("data-backstage-copy-target")||"");if(input&&navigator.clipboard){navigator.clipboard.writeText(input.value);btn.textContent="Copied";}});});var target=null;if(window.location.hash==="#backstage-outreach-business-review"){target=review;}else if(window.location.hash==="#backstage-outreach-business-share-review"){target=section.querySelector("#backstage-outreach-business-share-review");}else if(window.location.hash==="#backstage-outreach-business-share"){target=section.querySelector("#backstage-outreach-business-share");}if(target){window.setTimeout(function(){target.focus({preventScroll:true});var reduced=window.matchMedia("(prefers-reduced-motion: reduce)").matches;var top=target.getBoundingClientRect().top+window.scrollY-(document.getElementById("wpadminbar")?document.getElementById("wpadminbar").offsetHeight:0)-16;window.scrollTo({top:Math.max(0,top),behavior:reduced?"auto":"smooth"});},100);}})();</script></section>';
}

function backstage_outreach_csv_safe(string $value): string
{
	return preg_match('/^[=+\-@]/', ltrim($value)) ? "'" . $value : $value;
}

function backstage_outreach_handle_distribution_export(): void
{
	if (!current_user_can(vms_pass_claims_capability())) {
		wp_die(esc_html__('Access denied.', 'backstage-outreach'));
	}
	$campaign_id = backstage_outreach_request_absint($_GET, 'campaign_id');
	check_admin_referer('backstage_outreach_distribution_export_' . $campaign_id);
	$campaign = vms_pass_outreach_get_campaign_by_id($campaign_id);
	if (!$campaign) {
		wp_die(esc_html__('Campaign not found.', 'backstage-outreach'));
	}
	nocache_headers();
	header('Content-Type: text/csv; charset=utf-8');
	header('Content-Disposition: attachment; filename="partner-guest-pass-links-campaign-' . $campaign_id . '.csv"');
	$out = fopen('php://output', 'wb');
	fputcsv($out, array('campaign_id', 'campaign_name', 'source_id', 'business_id', 'business_name', 'distribution_type', 'offer_value_type', 'offer_value_amount', 'offer_description', 'status', 'coupon_id', 'coupon_code', 'coupon_uses', 'paid_orders', 'discounted_ticket_quantity', 'coupon_discount_total', 'eligible_ticket_gross_total', 'admission_revenue', 'order_revenue', 'refunded_total', 'refunded_orders', 'cancelled_orders', 'review_required_orders', 'currency', 'complimentary_claims', 'complimentary_admissions', 'partner_link', 'flyer_link', 'qr_image_url'));
	foreach (backstage_outreach_distribution_rows($campaign_id) as $row) {
		$url = backstage_outreach_distribution_url($row);
		$qr = function_exists('bvmgr_pass_claims_claim_qr_image_url') ? bvmgr_pass_claims_claim_qr_image_url($url) : '';
		$paid = backstage_outreach_discount_paid_stats((int) $row['id']);
		global $wpdb;
		$free = $wpdb->get_row($wpdb->prepare("SELECT COUNT(DISTINCT pass_claim_id) claims, COALESCE(SUM(party_size),0) admissions FROM %i WHERE distribution_id=%d AND status='fulfilled'", backstage_outreach_business_table('distribution_claims'), (int) $row['id']), ARRAY_A);
		$coupon_uses = absint($row['coupon_id'] ?? 0) > 0 && class_exists('WC_Coupon') ? absint((new WC_Coupon(absint($row['coupon_id'])))->get_usage_count()) : 0;
		$is_coupon = backstage_outreach_discount_distribution_type($row) === 'coupon_backed';
		$terms = $is_coupon && function_exists('backstage_outreach_discount_terms_for_distribution') ? backstage_outreach_discount_terms_for_distribution(array_merge($row, array('related_batch_id' => absint($campaign['related_batch_id'] ?? 0)))) : null;
		$offer_value_type = $is_coupon ? (is_array($terms) ? (string) $terms['value_type'] : 'unavailable') : 'free';
		$offer_value_amount = $is_coupon ? (is_array($terms) ? (float) $terms['value_amount'] : '') : 100.0;
		$offer_description = $is_coupon
			? (is_array($terms) && function_exists('backstage_outreach_discount_terms_label') ? backstage_outreach_discount_terms_label($terms) : __('Admission Offer — current batch value unavailable', 'backstage-outreach'))
			: __('Complimentary admission', 'backstage-outreach');
		fputcsv($out, array($campaign_id, backstage_outreach_csv_safe((string) $campaign['campaign_name']), (int) $row['source_id'], (int) $row['business_id'], backstage_outreach_csv_safe((string) $row['business_name']), backstage_outreach_discount_distribution_type($row), $offer_value_type, $offer_value_amount, backstage_outreach_csv_safe($offer_description), (string) $row['status'], absint($row['coupon_id'] ?? 0), backstage_outreach_csv_safe((string) ($row['coupon_code'] ?? '')), $coupon_uses, (int) ($paid['paid_orders'] ?? 0), (int) ($paid['discounted_tickets'] ?? 0), (float) ($paid['discount_total'] ?? 0), (float) ($paid['eligible_ticket_gross_total'] ?? 0), (float) ($paid['admission_revenue'] ?? 0), (float) ($paid['order_revenue'] ?? 0), (float) ($paid['refunded_total'] ?? 0), (int) ($paid['refunded_orders'] ?? 0), (int) ($paid['cancelled_orders'] ?? 0), (int) ($paid['review_required_orders'] ?? 0), backstage_outreach_csv_safe((string) ($paid['currency'] ?? '')), (int) ($free['claims'] ?? 0), (int) ($free['admissions'] ?? 0), $url, backstage_outreach_distribution_flyer_url($row), $qr));
	}
	fclose($out);
	exit;
}
add_action('admin_post_backstage_outreach_distribution_export', 'backstage_outreach_handle_distribution_export');

function backstage_outreach_register_partner_route(): void
{
	add_rewrite_tag('%backstage_outreach_partner_token%', '([^&]+)');
	add_rewrite_tag('%backstage_outreach_flyer_token%', '([^&]+)');
	add_rewrite_rule('^guest-pass/partner/([^/]+)/?$', 'index.php?backstage_outreach_partner_token=$matches[1]', 'top');
	add_rewrite_rule('^guest-pass/business-flyer/([^/]+)/?$', 'index.php?backstage_outreach_flyer_token=$matches[1]', 'top');
	if ((string) get_option('backstage_outreach_public_routes_version', '') !== 'business-flyer-v1') {
		update_option('backstage_outreach_public_routes_version', 'business-flyer-v1', false);
		update_option('backstage_outreach_flush_rewrite', '1', false);
	}
}
add_action('init', 'backstage_outreach_register_partner_route', 32);

function backstage_outreach_partner_request_token(): string
{
	$value = get_query_var('backstage_outreach_partner_token', '');
	if (is_scalar($value) && (string) $value !== '') {
		return sanitize_text_field(rawurldecode((string) $value));
	}
	$uri = function_exists('bvmgr_request_current_uri') ? bvmgr_request_current_uri() : '';
	return $uri !== '' && preg_match('~^/guest-pass/partner/([^/?#]+)~', $uri, $m) ? sanitize_text_field(rawurldecode((string) $m[1])) : '';
}

function backstage_outreach_flyer_request_token(): string
{
	$value = get_query_var('backstage_outreach_flyer_token', '');
	if (is_scalar($value) && (string) $value !== '') {
		return sanitize_text_field(rawurldecode((string) $value));
	}
	$uri = function_exists('bvmgr_request_current_uri') ? bvmgr_request_current_uri() : '';
	return $uri !== '' && preg_match('~^/guest-pass/business-flyer/([^/?#]+)~', $uri, $m) ? sanitize_text_field(rawurldecode((string) $m[1])) : '';
}

function backstage_outreach_distribution_context(string $raw_token)
{
	$parts = explode('.', $raw_token, 2);
	if (count($parts) !== 2) {
		return new WP_Error('partner_link_invalid', __('This business offer link is invalid.', 'backstage-outreach'));
	}
	global $wpdb;
	$row = $wpdb->get_row($wpdb->prepare(
		'SELECT d.*, b.business_name, b.status AS business_status, m.status AS membership_status, c.campaign_name, c.status AS campaign_status, c.related_batch_id, c.related_source_id, c.expires_at AS campaign_expires_at, c.total_admission_cap AS campaign_ticket_cap, c.admissions_per_recipient
		FROM %i d INNER JOIN %i b ON b.id = d.business_id INNER JOIN %i m ON m.source_id=d.source_id AND m.business_id=d.business_id INNER JOIN %i c ON c.id = d.campaign_id WHERE d.public_key = %s',
		backstage_outreach_business_table('campaign_businesses'), backstage_outreach_business_table('businesses'), backstage_outreach_business_table('source_businesses'), vms_admission_table_pass_outreach_campaigns(), sanitize_key($parts[0])
	), ARRAY_A);
	if (!is_array($row) || !hash_equals(backstage_outreach_distribution_signature($row), strtolower(sanitize_text_field($parts[1]))) || !hash_equals((string) $row['token_hash'], hash('sha256', $raw_token))) {
		return new WP_Error('partner_link_invalid', __('This business offer link is invalid.', 'backstage-outreach'));
	}
	$is_coupon = backstage_outreach_discount_distribution_type($row) === 'coupon_backed';
	$error_data = array('distribution_type' => $is_coupon ? 'coupon_backed' : 'complimentary');
	if ((string) $row['status'] !== 'active' || (string) $row['business_status'] !== 'active' || (string) $row['membership_status'] !== 'active') {
		return new WP_Error('partner_link_paused', $is_coupon ? __('This Admission Offer is paused or revoked.', 'backstage-outreach') : __('This partner Guest Pass link is paused or revoked.', 'backstage-outreach'), $error_data);
	}
	if ((string) $row['campaign_status'] !== 'active') {
		return new WP_Error('partner_campaign_inactive', $is_coupon ? __('This Admission Offer campaign is not currently active.', 'backstage-outreach') : __('This Guest Pass campaign is not currently active.', 'backstage-outreach'), $error_data);
	}
	if ((int) $row['source_id'] !== (int) $row['related_source_id']) {
		return new WP_Error('partner_link_mismatch', __('This partner link no longer matches its campaign Source.', 'backstage-outreach'), $error_data);
	}
	if (!empty($row['expires_at'])) {
		try {
			$expires = new DateTimeImmutable((string) $row['expires_at'], wp_timezone());
			if ($expires->getTimestamp() <= time()) {
				return new WP_Error('partner_link_expired', $is_coupon ? __('This Admission Offer has expired.', 'backstage-outreach') : __('This partner Guest Pass link has expired.', 'backstage-outreach'), $error_data);
			}
		} catch (Exception $error) {
			return new WP_Error('partner_link_expired', $is_coupon ? __('This Admission Offer has an invalid expiry.', 'backstage-outreach') : __('This partner Guest Pass link has an invalid expiry.', 'backstage-outreach'), $error_data);
		}
	}
	$batch = bvmgr_pass_claims_get_batch_by_id((int) $row['related_batch_id']);
	if (!$batch || (string) ($batch['status'] ?? '') !== 'active' || (int) ($batch['source_id'] ?? 0) !== (int) $row['source_id']) {
		return new WP_Error('partner_batch_inactive', $is_coupon ? __('This Admission Offer is not currently available.', 'backstage-outreach') : __('This Guest Pass offer is not currently available.', 'backstage-outreach'), $error_data);
	}
	if ($is_coupon) {
		$error = backstage_outreach_discount_distribution_error($row);
		if (is_wp_error($error)) {
			$error->add_data($error_data);
			return $error;
		}
		$row['batch'] = $batch;
		return $row;
	}
	if (sanitize_key((string) ($batch['value_type'] ?? '')) !== 'free') {
		return new WP_Error('partner_batch_not_complimentary', __('This partner link is not configured for a complimentary Guest Pass.', 'backstage-outreach'));
	}
	$row['batch'] = $batch;
	return $row;
}

function backstage_outreach_distribution_flyer_context(string $raw_token)
{
	$context = backstage_outreach_distribution_context($raw_token);
	if (is_wp_error($context)) {
		return $context;
	}
	$expiry_values = array(
		(string) ($context['expires_at'] ?? ''),
		(string) ($context['campaign_expires_at'] ?? ''),
		(string) (($context['batch']['expires_at'] ?? '')),
	);
	foreach ($expiry_values as $expiry_value) {
		if ($expiry_value === '' || str_starts_with($expiry_value, '0000-00-00')) {
			continue;
		}
		try {
			if ((new DateTimeImmutable($expiry_value, wp_timezone()))->getTimestamp() <= time()) {
				return new WP_Error('partner_flyer_expired', __('This business offer flyer has expired.', 'backstage-outreach'));
			}
		} catch (Exception $error) {
			return new WP_Error('partner_flyer_expired', __('This business offer flyer is unavailable.', 'backstage-outreach'));
		}
	}
	return $context;
}

function backstage_outreach_distribution_effective_expiry(array $distribution): string
{
	$candidates = array_filter(array(
		(string) ($distribution['expires_at'] ?? ''),
		(string) ($distribution['campaign_expires_at'] ?? ''),
		(string) (($distribution['batch']['expires_at'] ?? '')),
	), static fn(string $value): bool => $value !== '' && !str_starts_with($value, '0000-00-00'));
	$earliest = null;
	foreach ($candidates as $candidate) {
		try {
			$value = new DateTimeImmutable($candidate, wp_timezone());
			if (!$earliest || $value < $earliest) {
				$earliest = $value;
			}
		} catch (Exception $error) {
			continue;
		}
	}
	return $earliest ? wp_date('F j, Y \a\t g:i a T', $earliest->getTimestamp(), wp_timezone()) : '';
}

function backstage_outreach_render_public_offer_status(string $title, string $message, int $status = 410): void
{
	$status = $status === 404 ? 404 : 410;
	$branding = backstage_outreach_flyer_branding();
	status_header($status);
	nocache_headers();
	header('Content-Type: text/html; charset=' . get_option('blog_charset', 'UTF-8'));
	echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>' . esc_html($title) . '</title><style>*{box-sizing:border-box}body{margin:0;background:#edf3f1;color:#17202a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.status{width:min(42rem,calc(100% - 32px));margin:10vh auto;padding:34px;text-align:center;background:#fff;border:1px solid #d9e2ef;border-radius:18px}.logo{display:block;max-width:min(320px,80vw);max-height:120px;width:auto;height:auto;margin:0 auto 24px}.venue{font-size:1.5rem;font-weight:850;color:#163c34}.message{color:#526174;line-height:1.6}.home{display:inline-flex;min-height:44px;align-items:center;margin-top:12px;padding:8px 18px;border-radius:8px;background:#146b55;color:#fff;text-decoration:none;font-weight:800}</style></head><body><main class="status">';
	if ((string) $branding['logo_url'] !== '') {
		echo '<img class="logo" src="' . esc_url((string) $branding['logo_url']) . '" alt="' . esc_attr((string) $branding['site_name']) . '">';
	} else {
		echo '<p class="venue">' . esc_html((string) $branding['site_name']) . '</p>';
	}
	echo '<h1>' . esc_html($title) . '</h1><p class="message">' . esc_html($message) . '</p><a class="home" href="' . esc_url((string) $branding['home_url']) . '">' . esc_html__('Visit the venue homepage', 'backstage-outreach') . '</a></main></body></html>';
	exit;
}

function backstage_outreach_distribution_flyer_html(array $distribution): string
{
	$is_coupon = backstage_outreach_discount_distribution_type($distribution) === 'coupon_backed';
	$customer_url = backstage_outreach_distribution_url($distribution);
	$qr = function_exists('bvmgr_pass_claims_claim_qr_image_url') ? bvmgr_pass_claims_claim_qr_image_url($customer_url) : '';
	$batch = is_array($distribution['batch'] ?? null) ? (array) $distribution['batch'] : array();
	$per_customer = max(1, absint($batch['admissions_per_link'] ?? ($distribution['admissions_per_recipient'] ?? 1)));
	$scope = function_exists('vms_pass_outreach_business_batch_scope_label') ? vms_pass_outreach_business_batch_scope_label($batch) : __('See offer for eligible events.', 'backstage-outreach');
	$expiry = backstage_outreach_distribution_effective_expiry($distribution);
	$per_business_cap = absint($distribution['admission_cap'] ?? 0);
	$overall_cap = absint($batch['total_admission_cap'] ?? ($distribution['campaign_ticket_cap'] ?? 0));
	if ($is_coupon) {
		$terms = function_exists('backstage_outreach_discount_terms_for_distribution') ? backstage_outreach_discount_terms_for_distribution($distribution) : null;
		$offer = is_array($terms) && function_exists('backstage_outreach_discount_terms_label')
			? backstage_outreach_discount_terms_label($terms, false)
			: __('Admission discount', 'backstage-outreach');
		$headline = sprintf(__('%1$s for up to %2$d people per customer', 'backstage-outreach'), $offer, $per_customer);
	} else {
		$headline = sprintf(_n('Complimentary Guest Pass for up to %d person per customer', 'Complimentary Guest Passes for up to %d people per customer', $per_customer, 'backstage-outreach'), $per_customer);
	}
	$branding = backstage_outreach_flyer_branding();
	$logo_url = (string) $branding['logo_url'];
	$site_name = (string) $branding['site_name'];
	$design = backstage_outreach_resolved_flyer_design(absint($distribution['campaign_id'] ?? 0), $batch);
	$business_name = sanitize_text_field((string) ($distribution['business_name'] ?? ''));
	$title = sprintf(__('%s Admission Offer', 'backstage-outreach'), $business_name);
	$orientation = (string) $design['orientation'] === 'landscape' ? 'landscape' : 'portrait';
	$composition = (string) $design['composition'] === 'full' ? 'full' : 'panels';
	$panel_position = (string) $design['panel_position'] === 'right' ? 'right' : 'bottom';
	$has_artwork = (string) $design['artwork_url'] !== '';
	$filename = sanitize_file_name(sprintf('%s-admission-offer-%s.pdf', $business_name, $orientation));
	$offer_filename = sanitize_file_name(sprintf('%s-admission-offer-only-%s.pdf', $business_name, $orientation));
	$scan_instruction = $is_coupon
		? __('Scan to choose eligible tickets and apply this discount', 'backstage-outreach')
		: __('Scan to choose an eligible event and claim your passes', 'backstage-outreach');
	$followup_instruction = $is_coupon
		? __('The discount is applied automatically to eligible tickets.', 'backstage-outreach')
		: __('Follow the steps after scanning to claim your Guest Passes.', 'backstage-outreach');
	$scarcity = '';
	if ($per_business_cap > 0) {
		$scarcity = $is_coupon
			? sprintf(_n('Maximum through this business: %d discounted admission.', 'Maximum through this business: %d discounted admissions.', $per_business_cap, 'backstage-outreach'), $per_business_cap)
			: sprintf(_n('Maximum through this business: %d Guest Pass admission.', 'Maximum through this business: %d Guest Pass admissions.', $per_business_cap, 'backstage-outreach'), $per_business_cap);
		if ($overall_cap > 0) {
			$scarcity .= ' ' . sprintf(_n('Up to %d admission is available across all participating businesses, shared first come, first served.', 'Up to %d admissions are available across all participating businesses, shared first come, first served.', $overall_cap, 'backstage-outreach'), $overall_cap);
		}
	} elseif ($overall_cap > 0) {
		$scarcity = sprintf(_n('Up to %d admission is available across all participating businesses, shared first come, first served.', 'Up to %d admissions are available across all participating businesses, shared first come, first served.', $overall_cap, 'backstage-outreach'), $overall_cap);
	}

	ob_start();
	?>
	<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title><?php echo esc_html($title); ?></title><style id="backstage-outreach-flyer-styles">
	@page{size:letter <?php echo esc_html($orientation); ?>;margin:0}*{box-sizing:border-box}html,body{margin:0;min-height:100%;background:#e9efed;color:#17202a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.actions{display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:10px;padding:14px}.actions button{min-height:44px;padding:9px 18px;border:0;border-radius:9px;background:#146b55;color:#fff;font:inherit;font-weight:800;cursor:pointer}.actions button.secondary{background:#fff;color:#146b55;border:2px solid #146b55}.actions button:disabled{cursor:wait;opacity:.6}.status{flex-basis:100%;margin:0;text-align:center;color:#44554f}.stage{position:relative;margin:0 auto 24px}.sheet{position:absolute;inset:0 auto auto 0;width:var(--sheet-width);height:var(--sheet-height);padding:.25in;background:#fff;overflow:hidden;transform:scale(var(--sheet-scale,1));transform-origin:top left}.flyer-orientation-portrait{--sheet-width:8.5in;--sheet-height:11in}.flyer-orientation-landscape{--sheet-width:11in;--sheet-height:8.5in}.composition{position:relative;display:grid;width:100%;height:100%;gap:.18in;background:#fff;overflow:hidden}.flyer-panel-right .composition{grid-template-columns:minmax(0,1fr) 2.55in}.flyer-panel-bottom .composition{grid-template-rows:minmax(0,1fr) 2.62in}.art-panel{position:relative;display:flex;align-items:center;justify-content:center;min-width:0;min-height:0;overflow:hidden;border-radius:.16in;background:linear-gradient(145deg,#e7f0ed,#f9f6ed)}.artwork{display:block;width:100%;height:100%;object-fit:contain;object-position:center}.brand-card{position:absolute;z-index:2;top:.18in;left:.18in;right:.18in;max-width:5.8in;margin:auto;padding:.11in .16in;border-radius:.12in;background:rgba(255,255,255,.94);text-align:center;box-shadow:0 1px 8px rgba(23,32,42,.12)}.logo{display:block;max-width:2.5in;max-height:.58in;width:auto;height:auto;margin:0 auto .05in}.venue{margin:0;color:#163c34;font-size:14pt;font-weight:850;line-height:1.15;overflow-wrap:anywhere}.heading{margin:0;font-size:20pt;line-height:1.03;overflow-wrap:anywhere}.subheading{margin:.04in 0 0;color:#526174;font-size:9.5pt;line-height:1.25;overflow-wrap:anywhere}.no-art{display:flex;width:100%;height:100%;padding:.45in;align-items:center;justify-content:center;text-align:center;background:radial-gradient(circle at 35% 20%,#fff 0,#edf6f2 48%,#dcebe5 100%)}.no-art strong{display:block;max-width:5.5in;color:#163c34;font-size:34pt;line-height:1.05;overflow-wrap:anywhere}.offer-panel{position:relative;z-index:3;display:flex;flex-direction:column;align-items:center;justify-content:center;min-width:0;min-height:0;padding:.18in;text-align:center;border:1px solid #d9e2df;border-radius:.16in;background:rgba(255,255,255,.97);box-shadow:0 2px 12px rgba(23,32,42,.11);overflow:visible}.flyer-composition-full .composition{display:block;background:#eef4f1}.flyer-composition-full .art-panel{position:absolute;inset:0}.flyer-composition-full.flyer-panel-right .offer-panel{position:absolute;top:.18in;right:.18in;bottom:.18in;width:2.55in}.flyer-composition-full.flyer-panel-bottom .offer-panel{position:absolute;right:.18in;bottom:.18in;left:.18in;height:2.62in}.flyer-panel-bottom .offer-panel{display:grid;grid-template-columns:minmax(0,1.35fr) 1.72in minmax(0,1fr);grid-template-rows:auto 1fr;column-gap:.16in;text-align:left}.offer-brand{width:100%;margin-bottom:.08in}.offer-panel .logo{max-width:1.9in;max-height:.48in}.offer-panel .venue{font-size:11pt}.offer{margin:0;font-size:17pt;line-height:1.12;overflow-wrap:anywhere}.business{margin:.07in 0 0;color:#39534d;font-size:10pt;line-height:1.2;overflow-wrap:anywhere}.qr-block{display:flex;flex-direction:column;align-items:center;margin:.11in 0}.qr-wrap{display:inline-block;padding:.09in;background:#fff;border:1px solid #dfe6e3;border-radius:.1in}.qr{display:block;width:1.55in;height:1.55in}.scan{max-width:2.1in;margin:.06in 0 0;font-size:10.5pt;font-weight:850;line-height:1.15}.details{width:100%;margin:.06in 0 0;padding:.09in .11in;border-radius:.09in;background:#f3f6f5;text-align:left;list-style:none;font-size:8.2pt;line-height:1.28;overflow-wrap:anywhere}.details li+li{margin-top:.035in}.note{width:100%;margin:.055in 0 0;color:#44554f;font-size:7.8pt;line-height:1.25;text-align:left;overflow-wrap:anywhere}.flyer-panel-bottom .offer-copy{grid-column:1;grid-row:1 / 3;align-self:center}.flyer-panel-bottom .qr-block{grid-column:2;grid-row:1 / 3;margin:0;align-self:center}.flyer-panel-bottom .offer-details{grid-column:3;grid-row:1 / 3;align-self:center;width:100%}.flyer-panel-bottom .offer-brand{display:none}.flyer-panel-bottom .offer{font-size:16pt}.flyer-panel-bottom .business{font-size:9.5pt}.flyer-panel-bottom .details,.flyer-panel-bottom .note{font-size:7.5pt}.flyer-orientation-landscape.flyer-panel-bottom .offer-panel{height:2.35in;grid-template-columns:minmax(0,1.5fr) 1.68in minmax(0,1.25fr)}.flyer-orientation-landscape.flyer-panel-bottom .composition{grid-template-rows:minmax(0,1fr) 2.35in}.flyer-orientation-landscape.flyer-composition-full.flyer-panel-bottom .offer-panel{height:2.35in}.flyer-orientation-landscape.flyer-panel-right .composition{grid-template-columns:minmax(0,1fr) 2.7in}.flyer-orientation-landscape.flyer-composition-full.flyer-panel-right .offer-panel{width:2.7in}.asset-error{display:none;position:absolute;z-index:10;inset:.25in;padding:.3in;align-items:center;justify-content:center;background:#fff4f2;color:#8b1f16;text-align:center;font-weight:800}.sheet.has-error .asset-error{display:flex}@media(max-width:600px){.actions{position:sticky;top:0;z-index:20;background:#e9efed}.actions button{flex:1 1 135px}.status{font-size:.88rem}}@media print{html,body{width:var(--sheet-width);height:var(--sheet-height);background:#fff}.actions{display:none!important}.stage{width:var(--sheet-width)!important;height:var(--sheet-height)!important;margin:0!important}.sheet{position:relative;inset:auto;transform:none!important;page-break-inside:avoid;break-inside:avoid;page-break-after:avoid}.asset-error{display:none!important}}
	.flyer-orientation-landscape.flyer-panel-bottom .offer-panel{height:2.5in;grid-template-columns:minmax(0,1.5fr) 1.62in minmax(0,1.25fr)}.flyer-orientation-landscape.flyer-panel-bottom .composition{grid-template-rows:minmax(0,1fr) 2.5in}.flyer-orientation-landscape.flyer-composition-full.flyer-panel-bottom .offer-panel{height:2.5in}.flyer-orientation-landscape.flyer-panel-bottom .qr{width:1.45in;height:1.45in}.flyer-orientation-landscape.flyer-panel-bottom .scan{font-size:9.5pt}
	</style><style id="backstage-outreach-flyer-layout-styles">
	.actions{align-items:stretch}.action-set{display:flex;flex-wrap:wrap;gap:8px;margin:0;padding:8px 10px 10px;border:1px solid #b8c8c3;border-radius:10px}.action-set legend{padding:0 5px;color:#314b44;font-weight:800}.offer-panel{display:grid;grid-template-columns:minmax(0,1fr) 1.68in;grid-template-rows:auto auto 1fr;grid-template-areas:"brand qr" "copy qr" "details qr";align-content:start;align-items:start;justify-content:stretch;column-gap:.2in;padding:.22in;text-align:left;overflow:hidden}.offer-brand{grid-area:brand;width:100%;margin:0 0 .12in;text-align:center}.offer-panel .logo{display:block;width:90%;height:auto;max-width:none;max-height:none;margin:0 auto}.offer-copy{grid-area:copy;align-self:start}.offer-details{grid-area:details;align-self:start;width:100%}.qr-block{grid-area:qr;align-self:start;justify-self:end;margin:0;text-align:center}.scan{width:1.68in;max-width:none}.flyer-panel-bottom .offer-panel{display:grid;grid-template-columns:minmax(0,1fr) 1.68in;grid-template-rows:auto auto 1fr;grid-template-areas:"brand qr" "copy qr" "details qr";column-gap:.2in;text-align:left}.flyer-panel-bottom .offer-brand{display:block}.flyer-panel-bottom .offer-copy,.flyer-panel-bottom .offer-details,.flyer-panel-bottom .qr-block{grid-column:auto;grid-row:auto;align-self:start}.flyer-panel-bottom .offer-copy{grid-area:copy}.flyer-panel-bottom .offer-details{grid-area:details}.flyer-panel-bottom .qr-block{grid-area:qr}.flyer-orientation-portrait.flyer-panel-bottom .composition{grid-template-rows:minmax(0,1fr) 4.1in}.flyer-orientation-portrait.flyer-composition-full.flyer-panel-bottom .offer-panel{height:4.1in}.flyer-orientation-landscape.flyer-panel-bottom .composition{grid-template-rows:minmax(0,1fr) 2.5in}.flyer-orientation-landscape.flyer-panel-bottom .offer-panel,.flyer-orientation-landscape.flyer-composition-full.flyer-panel-bottom .offer-panel{height:2.5in;background:#fff}.flyer-orientation-landscape.flyer-panel-bottom .offer-panel{grid-template-columns:2.35in minmax(0,1fr) 1.72in;grid-template-rows:auto 1fr;grid-template-areas:"brand copy qr" "brand details qr";column-gap:.22in;padding:.2in .24in;background:#fff}.flyer-orientation-landscape .offer-brand{align-self:start;margin:0;padding-top:.02in}.flyer-orientation-landscape .offer-copy{align-self:start}.flyer-orientation-landscape .offer-details{align-self:start}.flyer-orientation-landscape .offer{font-size:15pt;line-height:1.1}.flyer-orientation-landscape .business{font-size:9.5pt}.flyer-orientation-landscape .details{margin-top:.08in;padding:.07in .1in;font-size:8.5pt;line-height:1.22}.flyer-orientation-landscape .note{font-size:8.2pt;line-height:1.2}.flyer-orientation-landscape .qr-block{justify-self:end;align-self:start}.flyer-orientation-landscape .qr{width:1.48in;height:1.48in}.flyer-orientation-landscape .scan{width:1.72in;font-size:9.5pt}.flyer-no-art .composition,.flyer-offer-only .composition{display:block;background:#fff}.flyer-no-art .art-panel,.flyer-offer-only .art-panel{display:none}.flyer-no-art .offer-panel,.flyer-offer-only .offer-panel{position:absolute;inset:0;width:auto!important;height:auto!important;padding:.45in;border:1px solid #d9e2df;border-radius:.16in;background:#fff;box-shadow:none;grid-template-columns:minmax(0,1fr) 2in;grid-template-areas:"brand qr" "copy qr" "details qr";column-gap:.35in;overflow:hidden}.flyer-no-art .offer-brand,.flyer-offer-only .offer-brand{width:100%;justify-self:start;margin-bottom:.28in}.flyer-no-art .qr-block,.flyer-offer-only .qr-block{justify-self:end}.flyer-no-art .qr,.flyer-offer-only .qr{width:1.8in;height:1.8in}.flyer-no-art .scan,.flyer-offer-only .scan{width:2in}.flyer-no-art .details,.flyer-offer-only .details{background:#fff;border:1px solid #dfe6e3}.flyer-orientation-landscape.flyer-no-art .offer-panel,.flyer-orientation-landscape.flyer-offer-only .offer-panel{inset:.12in .12in auto;height:2.5in!important;padding:.2in .12in;border:0;border-radius:0;grid-template-columns:2.35in minmax(0,1fr) 1.72in;grid-template-rows:auto 1fr;grid-template-areas:"brand copy qr" "brand details qr";column-gap:.22in}.flyer-orientation-landscape.flyer-no-art .offer-brand,.flyer-orientation-landscape.flyer-offer-only .offer-brand{margin:0}.flyer-orientation-landscape.flyer-no-art .qr,.flyer-orientation-landscape.flyer-offer-only .qr{width:1.48in;height:1.48in}.flyer-orientation-landscape.flyer-no-art .scan,.flyer-orientation-landscape.flyer-offer-only .scan{width:1.72in}.flyer-offer-only,.flyer-offer-only .composition,.flyer-offer-only .offer-panel{background:#fff!important}.flyer-offer-only .offer-panel{border-color:#d9e2df}.flyer-offer-only .brand-card{box-shadow:none}.offer-panel{justify-content:stretch}.offer,.business,.details,.note{max-width:none}@media(max-width:600px){.action-set{flex:1 1 100%}.action-set button{flex:1 1 135px}}@media print{.sheet.flyer-offer-only .art-panel{display:none!important}.sheet.flyer-offer-only .composition{display:block!important;background:#fff!important}.sheet.flyer-offer-only .offer-panel{position:absolute!important;background:#fff!important}.sheet.flyer-orientation-portrait.flyer-offer-only .offer-panel{inset:0!important;width:auto!important;height:auto!important}}
	</style><script defer src="<?php echo esc_url(BACKSTAGE_OUTREACH_PLUGIN_URL . 'assets/js/business-flyer.js?ver=' . rawurlencode((string) BACKSTAGE_OUTREACH_VERSION)); ?>"></script></head><body class="flyer-orientation-<?php echo esc_attr($orientation); ?>"><div class="actions"><fieldset class="action-set"><legend><?php echo esc_html__('Full flyer', 'backstage-outreach'); ?></legend><button type="button" data-flyer-print="full"><?php echo esc_html__('Print full flyer', 'backstage-outreach'); ?></button><button type="button" class="secondary" data-flyer-download="full"><?php echo esc_html__('Download full-flyer PDF', 'backstage-outreach'); ?></button></fieldset><?php if ($orientation === 'landscape') : ?><fieldset class="action-set"><legend><?php echo esc_html__('Offer only — Landscape Letter, ink saving', 'backstage-outreach'); ?></legend><button type="button" data-flyer-print="offer"><?php echo esc_html__('Print Landscape Letter offer only', 'backstage-outreach'); ?></button><button type="button" class="secondary" data-flyer-download="offer"><?php echo esc_html__('Download Landscape Letter offer-only PDF', 'backstage-outreach'); ?></button></fieldset><?php endif; ?><p class="status" data-flyer-status role="status" aria-live="polite"><?php echo esc_html__('Preparing artwork, logo, fonts, and QR…', 'backstage-outreach'); ?></p></div><div class="stage" data-flyer-stage><main class="sheet flyer-orientation-<?php echo esc_attr($orientation); ?> flyer-composition-<?php echo esc_attr($composition); ?> flyer-panel-<?php echo esc_attr($panel_position); ?><?php echo $has_artwork ? '' : ' flyer-no-art'; ?>" data-flyer-sheet data-orientation="<?php echo esc_attr($orientation); ?>" data-pdf-filename="<?php echo esc_attr($filename); ?>" data-offer-pdf-filename="<?php echo esc_attr($offer_filename); ?>" data-customer-url="<?php echo esc_url($customer_url); ?>"><div class="asset-error" data-flyer-error><?php echo esc_html__('This flyer could not be prepared because an essential image or text block did not fit. Please ask the venue to review the flyer design.', 'backstage-outreach'); ?></div><div class="composition">
	<section class="art-panel" aria-label="<?php echo esc_attr__('Venue artwork', 'backstage-outreach'); ?>"><?php if ((string) $design['artwork_url'] !== '') : ?><img class="artwork" src="<?php echo esc_url((string) $design['artwork_url']); ?>" alt="" data-flyer-asset><?php else : ?><div class="no-art"><strong data-flyer-fit data-min-font="22"><?php echo esc_html($site_name); ?></strong></div><?php endif; ?><?php if (!empty($design['show_heading']) || !empty($design['show_subheading'])) : ?><div class="brand-card"><?php if (!empty($design['show_heading'])) : ?><h1 class="heading" data-flyer-fit data-min-font="13"><?php echo esc_html((string) $design['heading']); ?></h1><?php endif; ?><?php if (!empty($design['show_subheading']) && (string) $design['subheading'] !== '') : ?><p class="subheading" data-flyer-fit data-min-font="8"><?php echo esc_html((string) $design['subheading']); ?></p><?php endif; ?></div><?php endif; ?></section>
	<section class="offer-panel" aria-label="<?php echo esc_attr__('Offer details and QR code', 'backstage-outreach'); ?>"><div class="offer-brand"><?php if (!empty($design['show_logo']) && $logo_url !== '') : ?><img class="logo" src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($site_name); ?>" data-flyer-asset><?php else : ?><p class="venue" data-flyer-fit data-min-font="9"><?php echo esc_html($site_name); ?></p><?php endif; ?></div><div class="offer-copy"><p class="offer" data-flyer-fit data-min-font="11"><strong><?php echo esc_html($headline); ?></strong></p><p class="business" data-flyer-fit data-min-font="8.5"><?php echo esc_html(sprintf(__('Available through %s', 'backstage-outreach'), $business_name)); ?></p></div>
	<?php if ($qr !== '') : ?><div class="qr-block"><div class="qr-wrap"><img class="qr" src="<?php echo esc_attr($qr); ?>" alt="<?php echo esc_attr__('QR code for this business offer', 'backstage-outreach'); ?>" data-flyer-asset></div><p class="scan"><?php echo esc_html($scan_instruction); ?></p></div><?php endif; ?>
	<div class="offer-details"><ul class="details"><li><strong><?php echo esc_html__('Events and dates:', 'backstage-outreach'); ?></strong> <?php echo esc_html($scope); ?></li><?php if ($expiry !== '') : ?><li><strong><?php echo esc_html__('Use by:', 'backstage-outreach'); ?></strong> <?php echo esc_html($expiry); ?></li><?php endif; ?></ul><?php if ($scarcity !== '') : ?><p class="note"><strong><?php echo esc_html__('Availability:', 'backstage-outreach'); ?></strong> <?php echo esc_html($scarcity); ?></p><?php endif; ?><p class="note"><?php echo esc_html($followup_instruction); ?></p></div>
	</section></div></main></div></body></html>
	<?php
	return (string) ob_get_clean();
}

function backstage_outreach_distribution_flyer_router(): void
{
	$token = backstage_outreach_flyer_request_token();
	if ($token === '') {
		return;
	}
	$distribution = backstage_outreach_distribution_flyer_context($token);
	nocache_headers();
	if (is_wp_error($distribution)) {
		backstage_outreach_render_public_offer_status(__('Offer unavailable', 'backstage-outreach'), $distribution->get_error_message(), $distribution->get_error_code() === 'partner_link_invalid' ? 404 : 410);
	}
	status_header(200);
	header('Content-Type: text/html; charset=' . get_option('blog_charset', 'UTF-8'));
	echo backstage_outreach_distribution_flyer_html($distribution); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Complete document escapes every dynamic value at construction.
	exit;
}
add_action('template_redirect', 'backstage_outreach_distribution_flyer_router', -3);

function backstage_outreach_partner_claim_insert_payload(array $payload, array $context): array
{
	if (empty($context['distribution_id'])) {
		return $payload;
	}
	$payload['data']['outreach_campaign_id'] = absint($context['campaign_id'] ?? 0);
	$payload['formats'][] = '%d';
	return $payload;
}
add_filter('bvmgr_pass_claims_claim_insert_payload', 'backstage_outreach_partner_claim_insert_payload', 20, 2);

function backstage_outreach_partner_claim_meta(array $meta, array $context): array
{
	if (!empty($context['distribution_id'])) {
		$meta['outreach_distribution_id'] = absint($context['distribution_id']);
		$meta['referring_business_id'] = absint($context['business_id'] ?? 0);
		$meta['outreach_campaign_id'] = absint($context['campaign_id'] ?? 0);
		$meta['pass_source_id'] = absint($context['source_id'] ?? 0);
	}
	return $meta;
}
add_filter('bvmgr_pass_claims_claim_meta', 'backstage_outreach_partner_claim_meta', 20, 2);

function backstage_outreach_existing_distribution_result(array $mapping): array
{
	global $wpdb;
	$claim_id = absint($mapping['pass_claim_id'] ?? 0);
	$claim = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id = %d', bvmgr_admission_table_pass_claims(), $claim_id), ARRAY_A);
	$entries = $wpdb->get_results($wpdb->prepare('SELECT * FROM %i WHERE pass_claim_id = %d ORDER BY id ASC', bvmgr_admission_table_entries(), $claim_id), ARRAY_A);
	if (!is_array($claim) || empty($entries)) {
		return array();
	}
	$event = bvmgr_pass_claims_get_event_plan_brief((int) $claim['event_plan_id']);
	$tokens = array();
	foreach ($entries as $index => $entry) {
		$token = (string) ($entry['admission_token'] ?? '');
		$tokens[] = array('entry_id' => (int) $entry['id'], 'token' => $token, 'reference' => 'GL-' . (int) $entry['id'], 'slot' => $index + 1);
	}
	$first = $entries[0];
	return array('claim_id' => $claim_id, 'entry_id' => (int) $first['id'], 'event_plan_id' => (int) $claim['event_plan_id'], 'event_title' => (string) ($event['title'] ?? ''), 'event_date' => (string) ($event['event_date'] ?? ''), 'venue_name' => (string) ($event['venue_name'] ?? ''), 'reference' => 'GL-' . (int) $first['id'], 'admission_token' => (string) ($first['admission_token'] ?? ''), 'admission_tokens' => $tokens, 'scan_url' => !empty($first['admission_token']) ? bvmgr_admission_scan_url((string) $first['admission_token']) : '', 'party_size' => count($entries), 'email_sent' => !empty($first['admission_emailed_at']), 'email_result' => array());
}

function backstage_outreach_partner_claim(array $distribution, array $event, array $input, string $submission_key)
{
	global $wpdb;
	$distribution_id = (int) $distribution['id'];
	$campaign_id = (int) $distribution['campaign_id'];
	$batch = (array) $distribution['batch'];
	$batch_id = (int) $batch['id'];
	$submission_hash = hash_hmac('sha256', $submission_key, wp_salt('nonce'));
	$identity = bvmgr_pass_claims_normalize_phone((string) ($input['phone'] ?? '')) . '|' . bvmgr_admission_normalize_email((string) ($input['email'] ?? ''));
	$identity_hash = hash_hmac('sha256', $identity, wp_salt('nonce'));
	$lock_name = 'bvm-pass-batch-' . $batch_id;
	if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $lock_name, 5)) !== 1) {
		return new WP_Error('claim_busy', __('Another claim is being processed. Please try again.', 'backstage-outreach'));
	}
	$committed = false;
	try {
		if ($wpdb->query('START TRANSACTION') === false) {
			return new WP_Error('claim_transaction_failed', __('The claim could not be started safely. Please retry.', 'backstage-outreach'));
		}
		$fresh_distribution = $wpdb->get_row($wpdb->prepare(
			'SELECT d.*, b.status AS business_status, m.status AS membership_status, c.status AS campaign_status, c.related_batch_id, c.related_source_id, c.total_admission_cap AS campaign_ticket_cap
			FROM %i d INNER JOIN %i b ON b.id=d.business_id INNER JOIN %i m ON m.source_id=d.source_id AND m.business_id=d.business_id INNER JOIN %i c ON c.id=d.campaign_id
			WHERE d.id=%d FOR UPDATE',
			backstage_outreach_business_table('campaign_businesses'), backstage_outreach_business_table('businesses'), backstage_outreach_business_table('source_businesses'), vms_admission_table_pass_outreach_campaigns(), $distribution_id
		), ARRAY_A);
		if (!is_array($fresh_distribution)
			|| (int) ($fresh_distribution['campaign_id'] ?? 0) !== $campaign_id
			|| (int) ($fresh_distribution['business_id'] ?? 0) !== (int) $distribution['business_id']
			|| (int) ($fresh_distribution['source_id'] ?? 0) !== (int) $distribution['source_id']
			|| (int) ($fresh_distribution['related_source_id'] ?? 0) !== (int) $distribution['source_id']
			|| (int) ($fresh_distribution['related_batch_id'] ?? 0) !== $batch_id) {
			$wpdb->query('ROLLBACK');
			return new WP_Error('partner_link_mismatch', __('This partner link no longer matches its campaign.', 'backstage-outreach'));
		}
		if ((string) ($fresh_distribution['status'] ?? '') !== 'active'
			|| (string) ($fresh_distribution['business_status'] ?? '') !== 'active'
			|| (string) ($fresh_distribution['membership_status'] ?? '') !== 'active') {
			$wpdb->query('ROLLBACK');
			return new WP_Error('partner_link_paused', __('This partner Guest Pass link is paused or revoked.', 'backstage-outreach'));
		}
		if ((string) ($fresh_distribution['campaign_status'] ?? '') !== 'active') {
			$wpdb->query('ROLLBACK');
			return new WP_Error('partner_campaign_inactive', __('This Guest Pass campaign is not currently active.', 'backstage-outreach'));
		}
		if (backstage_outreach_discount_distribution_type($fresh_distribution) !== 'complimentary') {
			$wpdb->query('ROLLBACK');
			return new WP_Error('partner_distribution_not_complimentary', __('This link is no longer configured for a complimentary Guest Pass.', 'backstage-outreach'));
		}
		if (!empty($fresh_distribution['expires_at'])) {
			try {
				$expires = new DateTimeImmutable((string) $fresh_distribution['expires_at'], wp_timezone());
				if ($expires->getTimestamp() <= time()) {
					$wpdb->query('ROLLBACK');
					return new WP_Error('partner_link_expired', __('This partner Guest Pass link has expired.', 'backstage-outreach'));
				}
			} catch (Exception $error) {
				$wpdb->query('ROLLBACK');
				return new WP_Error('partner_link_expired', __('This partner Guest Pass link has an invalid expiry.', 'backstage-outreach'));
			}
		}
		$fresh_batch = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id = %d FOR UPDATE', bvmgr_admission_table_pass_batches(), $batch_id), ARRAY_A);
		if (!is_array($fresh_batch) || (string) ($fresh_batch['status'] ?? '') !== 'active' || (int) ($fresh_batch['source_id'] ?? 0) !== (int) $distribution['source_id'] || sanitize_key((string) ($fresh_batch['value_type'] ?? '')) !== 'free') {
			$wpdb->query('ROLLBACK');
			return new WP_Error('partner_batch_inactive', __('This Guest Pass offer is not currently available.', 'backstage-outreach'));
		}
		$batch = $fresh_batch;
		$mappings = backstage_outreach_business_table('distribution_claims');
		$existing = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE distribution_id = %d AND submission_key_hash = %s FOR UPDATE', $mappings, $distribution_id, $submission_hash), ARRAY_A);
		if (is_array($existing) && (string) $existing['status'] === 'fulfilled') {
			$result = backstage_outreach_existing_distribution_result($existing);
			$committed = $wpdb->query('COMMIT') !== false;
			if (!$committed) {
				$wpdb->query('ROLLBACK');
				return new WP_Error('claim_commit_unknown', __('Your prior claim could not be confirmed. Retry with the same form or contact the venue.', 'backstage-outreach'));
			}
			return !empty($result) ? $result : new WP_Error('claim_recovery_failed', __('Your claim exists but could not be displayed. Please contact the venue.', 'backstage-outreach'));
		}
		$party_size = max(1, absint($input['party_size'] ?? 1));
		$cap = absint($fresh_distribution['admission_cap'] ?? 0);
		if ($cap > 0) {
			$free_used = (int) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(party_size),0) FROM %i WHERE distribution_id = %d AND status = 'fulfilled'", $mappings, $distribution_id));
			$paid_used = (int) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(ticket_quantity),0) FROM %i WHERE distribution_id=%d AND (status='paid' OR (status='pending' AND reservation_expires_at>%s))", backstage_outreach_business_table('paid_redemptions'), $distribution_id, backstage_outreach_business_now()));
			$used = $free_used + $paid_used;
			if ($used + $party_size > $cap) {
				$wpdb->query('ROLLBACK');
				return new WP_Error('business_capacity_limit', __('This business link has reached its admission limit.', 'backstage-outreach'));
			}
		}
		$now = backstage_outreach_business_now();
		$campaign_cap = absint($fresh_distribution['campaign_ticket_cap'] ?? 0);
		if ($campaign_cap > 0) {
			$campaign_free = (int) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(e.party_size),0) FROM %i e INNER JOIN %i pc ON pc.id=e.pass_claim_id WHERE pc.outreach_campaign_id=%d AND e.status<>'canceled'", bvmgr_admission_table_entries(), bvmgr_admission_table_pass_claims(), $campaign_id));
			$campaign_paid = (int) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(ticket_quantity),0) FROM %i WHERE campaign_id=%d AND (status='paid' OR (status='pending' AND reservation_expires_at>%s))", backstage_outreach_business_table('paid_redemptions'), $campaign_id, $now));
			if ($campaign_free + $campaign_paid + $party_size > $campaign_cap) {
				$wpdb->query('ROLLBACK');
				return new WP_Error('campaign_capacity_limit', __('This campaign has reached its combined ticket limit.', 'backstage-outreach'));
			}
		}
		$batch_cap = absint($fresh_batch['total_admission_cap'] ?? 0);
		if ($batch_cap > 0) {
			$batch_free = (int) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(party_size),0) FROM %i WHERE pass_batch_id=%d AND status<>'canceled'", bvmgr_admission_table_entries(), $batch_id));
			$batch_paid = (int) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(pr.ticket_quantity),0) FROM %i pr INNER JOIN %i c ON c.id=pr.campaign_id WHERE c.related_batch_id=%d AND (pr.status='paid' OR (pr.status='pending' AND pr.reservation_expires_at>%s))", backstage_outreach_business_table('paid_redemptions'), vms_admission_table_pass_outreach_campaigns(), $batch_id, $now));
			if ($batch_free + $batch_paid + $party_size > $batch_cap) {
				$wpdb->query('ROLLBACK');
				return new WP_Error('batch_capacity_limit', __('This pass batch has reached its combined ticket limit.', 'backstage-outreach'));
			}
		}
		if (!is_array($existing)) {
			$inserted = $wpdb->insert($mappings, array('distribution_id' => $distribution_id, 'campaign_id' => $campaign_id, 'source_id' => (int) $fresh_distribution['source_id'], 'business_id' => (int) $fresh_distribution['business_id'], 'party_size' => $party_size, 'submission_key_hash' => $submission_hash, 'identity_hash' => $identity_hash, 'status' => 'pending', 'created_at' => backstage_outreach_business_now()));
			if ($inserted === false) {
				$wpdb->query('ROLLBACK');
				return new WP_Error('claim_reservation_failed', __('The claim could not be reserved. Please retry.', 'backstage-outreach'));
			}
			$mapping_id = (int) $wpdb->insert_id;
		} else {
			$mapping_id = (int) $existing['id'];
		}
		$token = function_exists('bvmgr_pass_claims_create_internal_claim_token')
			? bvmgr_pass_claims_create_internal_claim_token($batch, 0)
			: new WP_Error('internal_claim_token_unavailable', __('This Guest Pass claim service is unavailable.', 'backstage-outreach'));
		if (is_wp_error($token)) {
			$wpdb->query('ROLLBACK');
			return $token;
		}
		$context = array('distribution_id' => $distribution_id, 'campaign_id' => $campaign_id, 'business_id' => (int) $fresh_distribution['business_id'], 'source_id' => (int) $fresh_distribution['source_id'], 'batch_lock_held' => true, 'defer_email' => true);
		$result = bvmgr_pass_claims_create_claim($token, $batch, $event, $input, $context);
		if (is_wp_error($result)) {
			$wpdb->query('ROLLBACK');
			return $result;
		}
		$updated = $wpdb->update($mappings, array('pass_token_id' => (int) $token['id'], 'pass_claim_id' => (int) $result['claim_id'], 'reservation_entry_id' => (int) $result['entry_id'], 'party_size' => (int) $result['party_size'], 'status' => 'fulfilled', 'fulfilled_at' => backstage_outreach_business_now()), array('id' => $mapping_id));
		if ($updated === false) {
			$wpdb->query('ROLLBACK');
			return new WP_Error('attribution_failed', __('The claim could not be safely attributed. Please retry.', 'backstage-outreach'));
		}
		$committed = $wpdb->query('COMMIT') !== false;
		if (!$committed) {
			$wpdb->query('ROLLBACK');
			return new WP_Error('claim_commit_unknown', __('The claim outcome could not be confirmed. Retry with the same form to recover it safely.', 'backstage-outreach'));
		}
		if (!empty($input['email']) && function_exists('bvmgr_admission_email_pass_result')) {
			try {
				$email_result = bvmgr_admission_email_pass_result((int) $result['entry_id'], 'partner_guest_pass_claim');
				$result['email_sent'] = !empty($email_result['sent']);
				$result['email_result'] = $email_result;
			} catch (Throwable $email_error) {
				$result['email_sent'] = false;
				$result['email_result'] = array('sent' => false, 'error' => 'mail_transport_exception');
			}
		}
		return $result;
	} catch (Throwable $error) {
		if (!$committed) {
			$wpdb->query('ROLLBACK');
		}
		return new WP_Error('claim_transaction_failed', __('The claim could not be completed safely. Please retry.', 'backstage-outreach'));
	} finally {
		$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
	}
}

function backstage_outreach_partner_form_html(array $distribution, array $events, array $posted, string $error, string $submission_key): string
{
	$batch = (array) $distribution['batch'];
	$global = function_exists('bvmgr_admission_settings') ? max(1, (int) (bvmgr_admission_settings()['max_party_size'] ?? 6)) : 6;
	$max = min($global, max(1, (int) ($batch['admissions_per_link'] ?? 1)));
	$html = '<h1>' . esc_html__('Claim Your Guest Pass', 'backstage-outreach') . '</h1>';
	$html .= '<p class="vms-pass-note"><strong>' . esc_html__('Referred by:', 'backstage-outreach') . '</strong> ' . esc_html((string) $distribution['business_name']) . '</p>';
	$html .= '<p class="vms-pass-meta">' . esc_html__('Enter the guest information below. The referring business is not the guest and its contact information is never copied into this form.', 'backstage-outreach') . '</p>';
	if ($error !== '') {
		$html .= '<p class="vms-pass-error">' . esc_html($error) . '</p>';
	}
	$html .= '<form method="post">' . wp_nonce_field('backstage_outreach_partner_claim_' . (int) $distribution['id'], '_backstage_partner_nonce', true, false) . '<input type="hidden" name="backstage_partner_submit" value="1"><input type="hidden" name="submission_key" value="' . esc_attr($submission_key) . '"><div class="vms-pass-grid">';
	$html .= '<label>' . esc_html__('First Name', 'backstage-outreach') . '<input name="first_name" value="' . esc_attr((string) $posted['first_name']) . '" required></label><label>' . esc_html__('Last Name', 'backstage-outreach') . '<input name="last_name" value="' . esc_attr((string) $posted['last_name']) . '" required></label><label>' . esc_html__('Phone', 'backstage-outreach') . '<input name="phone" value="' . esc_attr((string) $posted['phone']) . '" required></label><label>' . esc_html__('Email (optional)', 'backstage-outreach') . '<input type="email" name="email" value="' . esc_attr((string) $posted['email']) . '"></label>';
	$html .= '<label class="vms-pass-span-2">' . esc_html__('Select Event', 'backstage-outreach') . '<select name="event_plan_id" required><option value="">' . esc_html__('Choose an event', 'backstage-outreach') . '</option>';
	foreach ($events as $event) {
		$label = (string) ($event['title'] ?? __('Event', 'backstage-outreach')) . (!empty($event['event_date']) ? ' (' . bvmgr_pass_claims_format_public_date((string) $event['event_date']) . ')' : '');
		$html .= '<option value="' . esc_attr((string) ((int) $event['id'])) . '"' . selected((int) $posted['event_plan_id'], (int) $event['id'], false) . '>' . esc_html($label) . '</option>';
	}
	$html .= '</select></label><label class="vms-pass-span-2">' . esc_html__('Admissions', 'backstage-outreach') . '<input type="number" name="party_size" min="1" max="' . esc_attr((string) $max) . '" value="' . esc_attr((string) max(1, min($max, (int) $posted['party_size']))) . '"></label><label class="vms-pass-span-2 vms-pass-checkbox"><input type="checkbox" name="opt_in" value="1"' . checked(1, (int) $posted['opt_in'], false) . '> <span>' . esc_html__('Send me optional event updates.', 'backstage-outreach') . '</span></label></div><p class="vms-pass-actions"><button type="submit">' . esc_html__('Claim Guest Pass', 'backstage-outreach') . '</button></p></form>';
	return $html;
}

function backstage_outreach_partner_router(): void
{
	if (is_admin()) {
		return;
	}
	$token = backstage_outreach_partner_request_token();
	if ($token === '') {
		return;
	}
	$distribution = backstage_outreach_distribution_context($token);
	if (is_wp_error($distribution)) {
		$error_data = $distribution->get_error_data();
		$error_type = is_array($error_data) ? sanitize_key((string) ($error_data['distribution_type'] ?? '')) : '';
		$status_title = $error_type === 'coupon_backed' ? __('Admission Offer Unavailable', 'backstage-outreach') : ($error_type === 'complimentary' ? __('Guest Pass Unavailable', 'backstage-outreach') : __('Business Offer Unavailable', 'backstage-outreach'));
		backstage_outreach_render_public_offer_status($status_title, $distribution->get_error_message(), $distribution->get_error_code() === 'partner_link_invalid' ? 404 : 410);
	}
	if (backstage_outreach_discount_distribution_type($distribution) === 'coupon_backed') {
		backstage_outreach_discount_offer_router($distribution, $token);
		return;
	}
	$batch = (array) $distribution['batch'];
	$events = bvmgr_pass_claims_eligible_events_for_batch($batch);
	$campaign = vms_pass_outreach_get_campaign_by_id((int) $distribution['campaign_id']);
	if (is_array($campaign) && function_exists('vms_pass_outreach_filter_events_for_campaign')) {
		$events = vms_pass_outreach_filter_events_for_campaign($campaign, $events);
	}
	if (empty($events)) {
		backstage_outreach_render_public_offer_status(__('No Eligible Events', 'backstage-outreach'), __('There are no eligible events for this Guest Pass right now.', 'backstage-outreach'), 410);
	}
	$posted = array('first_name' => '', 'last_name' => '', 'phone' => '', 'email' => '', 'event_plan_id' => 0, 'party_size' => 1, 'opt_in' => 0);
	$error = '';
	$submission_key = wp_generate_uuid4();
	if (bvmgr_request_method() === 'post' && isset($_POST['backstage_partner_submit'])) {
		$submission_key = backstage_outreach_request_text($_POST, 'submission_key');
		$nonce = backstage_outreach_request_text($_POST, '_backstage_partner_nonce');
		if ($submission_key === '' || !wp_verify_nonce($nonce, 'backstage_outreach_partner_claim_' . (int) $distribution['id'])) {
			$error = __('Invalid or expired form. Refresh and try again.', 'backstage-outreach');
		} else {
			$posted = array('first_name' => backstage_outreach_request_text($_POST, 'first_name'), 'last_name' => backstage_outreach_request_text($_POST, 'last_name'), 'phone' => backstage_outreach_request_text($_POST, 'phone'), 'email' => sanitize_email(backstage_outreach_request_text($_POST, 'email')), 'event_plan_id' => backstage_outreach_request_absint($_POST, 'event_plan_id'), 'party_size' => backstage_outreach_request_absint($_POST, 'party_size', 1), 'opt_in' => backstage_outreach_request_text($_POST, 'opt_in') === '1' ? 1 : 0);
			$global_max = function_exists('bvmgr_admission_settings') ? max(1, (int) (bvmgr_admission_settings()['max_party_size'] ?? 6)) : 6;
			$max_party_size = min($global_max, max(1, (int) ($batch['admissions_per_link'] ?? 1)));
			$ip = bvmgr_request_remote_addr();
			$selected = null;
			foreach ($events as $event) {
				if ((int) $event['id'] === (int) $posted['event_plan_id']) {
					$selected = $event;
					break;
				}
			}
			if ((int) $posted['party_size'] < 1 || (int) $posted['party_size'] > $max_party_size) {
				$error = sprintf(__('Party size must be between 1 and %d.', 'backstage-outreach'), $max_party_size);
			} elseif ($ip !== '' && bvmgr_pass_claims_rate_limit_hit($ip, (string) $distribution['public_key'])) {
				$error = __('Too many attempts. Please try again shortly.', 'backstage-outreach');
			} elseif (!$selected) {
				$error = __('Choose a valid eligible event.', 'backstage-outreach');
			} else {
				$result = backstage_outreach_partner_claim($distribution, $selected, $posted, $submission_key);
				if (is_wp_error($result)) {
					$error = $result->get_error_message();
				} else {
					bvmgr_pass_claims_render_public_success_confirmation($result, (string) $posted['email']);
				}
			}
		}
	}
	bvmgr_pass_claims_render_public_shell(__('Claim Guest Pass', 'backstage-outreach'), static function () use ($distribution, $events, $posted, $error, $submission_key): void {
		echo backstage_outreach_partner_form_html($distribution, $events, $posted, $error, $submission_key);
	});
}
add_action('template_redirect', 'backstage_outreach_partner_router', -2);
