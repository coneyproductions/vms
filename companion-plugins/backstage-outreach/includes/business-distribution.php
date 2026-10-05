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
	$name = sanitize_text_field($read('business_name'));
	if ($name === '') {
		return new WP_Error('business_name_required', __('Business name is required.', 'backstage-outreach'));
	}
	$email = sanitize_email($read('email'));
	if ($read('email') !== '' && $email === '') {
		return new WP_Error('business_email_invalid', __('Enter a valid email address or leave it blank.', 'backstage-outreach'));
	}
	return array(
		'business_name' => $name,
		'contact_name' => sanitize_text_field($read('contact_name')),
		'email' => $email,
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
	$headers = fgetcsv($handle);
	if (!is_array($headers)) {
		fclose($handle);
		return new WP_Error('csv_header_missing', __('The CSV must contain a header row.', 'backstage-outreach'));
	}
	$headers = array_map(static fn($v) => sanitize_key((string) $v), $headers);
	$allowed = array('external_id', 'business_name', 'contact_name', 'email', 'phone', 'website', 'address_line', 'city', 'state', 'postal_code', 'notes');
	$rows = array();
	while (($values = fgetcsv($handle)) !== false) {
		$row = array();
		foreach ($headers as $index => $header) {
			if ($header !== '' && in_array($header, $allowed, true)) {
				$row[$header] = (string) ($values[$index] ?? '');
			}
		}
		if (array_filter($row, static fn($v) => trim((string) $v) !== '')) {
			$rows[] = $row;
			if (count($rows) > 1000) {
				fclose($handle);
				return new WP_Error('csv_row_limit', __('The CSV contains more than 1,000 non-empty rows. Split it into smaller reviewed imports.', 'backstage-outreach'));
			}
		}
	}
	fclose($handle);
	return $rows;
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
		$upload = bvmgr_upload_read_file($_FILES, 'business_csv');
		if (!is_wp_error($upload)) {
			$upload = bvmgr_validate_uploaded_file($upload, array('allowed_mimes' => array('csv' => 'text/csv', 'txt' => 'text/plain'), 'max_bytes' => 2 * MB_IN_BYTES));
		}
		$rows = is_wp_error($upload) ? $upload : backstage_outreach_csv_rows((string) $upload['tmp_name']);
		if (is_wp_error($rows)) {
			backstage_outreach_business_message($rows->get_error_message(), 'error');
		} else {
			$valid = array();
			$errors = array();
			foreach ($rows as $index => $row) {
				$payload = backstage_outreach_business_payload($row);
				if (is_wp_error($payload)) {
					$errors[] = sprintf(__('Row %1$d: %2$s', 'backstage-outreach'), $index + 2, $payload->get_error_message());
					continue;
				}
				$valid[] = array('payload' => $payload, 'external_id' => sanitize_text_field((string) ($row['external_id'] ?? '')), 'snapshot' => $row);
			}
			set_transient($key, array('source_id' => $source_id, 'rows' => $valid, 'errors' => $errors), 10 * MINUTE_IN_SECONDS);
		}
		backstage_outreach_business_redirect(array('source_id' => $source_id, 'import_preview' => 1));
	}
	$preview = get_transient($key);
	if (!is_array($preview) || (int) ($preview['source_id'] ?? 0) !== $source_id) {
		backstage_outreach_business_message(__('Import preview expired. Preview the CSV again.', 'backstage-outreach'), 'error');
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

	echo '<section class="vms-pass-card"><h3>' . esc_html__('Import Businesses', 'backstage-outreach') . '</h3><p class="description">' . esc_html__('Preview is required. Supported headers: external_id, business_name, contact_name, email, phone, website, address_line, city, state, postal_code, notes.', 'backstage-outreach') . '</p><form method="post" enctype="multipart/form-data" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="backstage_outreach_business_import"><input type="hidden" name="source_id" value="' . esc_attr((string) $source_id) . '"><input type="hidden" name="import_mode" value="preview">';
	wp_nonce_field('backstage_outreach_business_import');
	echo '<input type="file" name="business_csv" accept=".csv,text/csv" required> <button class="button">' . esc_html__('Preview CSV', 'backstage-outreach') . '</button></form>';
	$import = get_transient('backstage_outreach_business_import_' . get_current_user_id());
	if (is_array($import) && (int) ($import['source_id'] ?? 0) === $source_id) {
		echo '<p><strong>' . esc_html(sprintf(__('%1$d valid rows; %2$d errors.', 'backstage-outreach'), count((array) $import['rows']), count((array) $import['errors']))) . '</strong></p><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="backstage_outreach_business_import"><input type="hidden" name="source_id" value="' . esc_attr((string) $source_id) . '"><input type="hidden" name="import_mode" value="commit">';
		if (!empty($import['rows'])) { echo '<p class="description">' . esc_html(implode(', ', array_map(static fn($row) => (string) ($row['payload']['business_name'] ?? ''), array_slice((array) $import['rows'], 0, 20)))) . '</p>'; }
		if (!empty($import['errors'])) { echo '<ul class="ul-disc"><li>' . implode('</li><li>', array_map('esc_html', (array) $import['errors'])) . '</li></ul>'; }
		wp_nonce_field('backstage_outreach_business_import');
		echo '<button class="button button-primary">' . esc_html__('Commit CSV Import', 'backstage-outreach') . '</button></form>';
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
	if ($mode !== 'commit') {
		$configuration = backstage_outreach_discount_offer_configuration($campaign, $batch, $distribution_type);
		if (is_wp_error($configuration)) {
			backstage_outreach_business_message($configuration->get_error_message(), 'error');
			wp_safe_redirect(vms_pass_outreach_admin_page_url(array('campaign_id' => $campaign_id)) . '#backstage-outreach-partners');
			exit;
		}
		set_transient($preview_key, array(
			'business_ids' => $selected,
			'distribution_type' => $distribution_type,
			'admission_cap' => $cap,
			'order_cap' => $order_cap,
			'expires_at' => $expires_at,
			'configuration_digest' => backstage_outreach_discount_configuration_digest($configuration),
		), 30 * MINUTE_IN_SECONDS);
		backstage_outreach_business_message(sprintf(_n('%d business is ready for review. No links were changed.', '%d businesses are ready for review. No links were changed.', count($selected), 'backstage-outreach'), count($selected)));
		wp_safe_redirect(vms_pass_outreach_admin_page_url(array('campaign_id' => $campaign_id)) . '#backstage-outreach-partners');
		exit;
	}
	$preview = get_transient($preview_key);
	if (!is_array($preview)) {
		backstage_outreach_business_message(__('The review expired. Review the business selection again before saving links.', 'backstage-outreach'), 'error');
		wp_safe_redirect(vms_pass_outreach_admin_page_url(array('campaign_id' => $campaign_id)) . '#backstage-outreach-partners');
		exit;
	}
	$selected = array_values(array_intersect(array_map('absint', (array) ($preview['business_ids'] ?? array())), $allowed));
	$distribution_type = sanitize_key((string) ($preview['distribution_type'] ?? 'complimentary'));
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
		if (function_exists('bvmgr_admission_audit_log')) {
			bvmgr_admission_audit_log(0, null, 'outreach_partner_distributions_save', get_current_user_id(), 'admin', array('campaign_id' => $campaign_id, 'source_id' => $source_id, 'business_ids' => $selected, 'distribution_type' => $distribution_type, 'admission_cap' => $cap, 'order_cap' => $order_cap, 'eligible_event_ids' => $configuration['event_ids'] ?? array(), 'eligible_product_ids' => $configuration['product_ids'] ?? array(), 'expires_at' => $expires_at));
		}
	}
	$failure_message = $revoked_selected
		? __('A revoked distribution cannot be resumed. Remove it from the reviewed selection; a future reissue must use a newly rotated link.', 'backstage-outreach')
		: ($save_error !== '' ? $save_error : __('Partner distributions could not be saved.', 'backstage-outreach'));
	backstage_outreach_business_message($ok ? __('Partner distributions saved. No invitations were sent.', 'backstage-outreach') : $failure_message, $ok ? 'info' : 'error');
	wp_safe_redirect(vms_pass_outreach_admin_page_url(array('campaign_id' => $campaign_id)) . '#backstage-outreach-partners');
	exit;
}
add_action('admin_post_backstage_outreach_campaign_businesses', 'backstage_outreach_handle_campaign_businesses');

function backstage_outreach_distribution_rows(int $campaign_id): array
{
	global $wpdb;
	$rows = $wpdb->get_results($wpdb->prepare('SELECT d.*, b.business_name, b.contact_name, b.email, b.phone FROM %i d INNER JOIN %i b ON b.id = d.business_id WHERE d.campaign_id = %d ORDER BY b.business_name ASC', backstage_outreach_business_table('campaign_businesses'), backstage_outreach_business_table('businesses'), $campaign_id), ARRAY_A);
	return is_array($rows) ? $rows : array();
}

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
						$configuration = is_array($campaign) && is_array($batch) ? backstage_outreach_discount_offer_configuration($campaign, $batch, 'coupon_backed') : new WP_Error('offer_configuration_missing', __('The offer configuration could not be loaded.', 'backstage-outreach'));
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
	$row = $wpdb->get_row($wpdb->prepare('SELECT d.*, b.business_name, c.campaign_name, c.admissions_per_recipient FROM %i d INNER JOIN %i b ON b.id=d.business_id INNER JOIN %i c ON c.id=d.campaign_id WHERE d.id=%d', backstage_outreach_business_table('campaign_businesses'), backstage_outreach_business_table('businesses'), vms_admission_table_pass_outreach_campaigns(), $id), ARRAY_A);
	if (!is_array($row)) { wp_die(esc_html__('Distribution not found.', 'backstage-outreach')); }
	$url = backstage_outreach_distribution_url($row);
	$qr = bvmgr_pass_claims_claim_qr_image_url($url);
	$is_coupon = backstage_outreach_discount_distribution_type($row) === 'coupon_backed';
	$eyebrow = $is_coupon ? __('Serenade Range Neighborhood Offer', 'backstage-outreach') : __('Serenade Range Guest Pass', 'backstage-outreach');
	$alt = $is_coupon ? __('Reusable Neighborhood Offer QR', 'backstage-outreach') : __('Reusable partner Guest Pass QR', 'backstage-outreach');
	$callout = $is_coupon ? sprintf(__('Scan for 50%% off admission for up to %d people', 'backstage-outreach'), max(1, absint($row['admissions_per_recipient'] ?? 1))) : __('Scan to claim your own Guest Pass', 'backstage-outreach');
	$note = $is_coupon
		? __('This reusable marketing QR opens the offer and applies its managed coupon through normal ticket checkout. It is not an admission credential.', 'backstage-outreach')
		: __('This marketing QR is reusable. It is not an admission credential; each guest receives separate gate QR credentials after claiming.', 'backstage-outreach');
	nocache_headers();
	echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>' . esc_html((string) $row['business_name']) . '</title><style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;margin:0;color:#17202a}.sheet{max-width:520px;margin:30px auto;padding:34px;text-align:center;border:1px solid #d9e2ef;border-radius:16px}.eyebrow{text-transform:uppercase;letter-spacing:.12em;color:#146b55;font-weight:700}.qr{width:320px;max-width:90%;height:auto}.note{color:#526174}.actions{margin:20px}@media print{.actions{display:none}.sheet{border:0;margin:0 auto}}</style></head><body><div class="actions"><button onclick="window.print()">' . esc_html__('Print', 'backstage-outreach') . '</button></div><main class="sheet"><p class="eyebrow">' . esc_html($eyebrow) . '</p><h1>' . esc_html((string) $row['business_name']) . '</h1><p>' . esc_html((string) $row['campaign_name']) . '</p><img class="qr" src="' . esc_attr($qr) . '" alt="' . esc_attr($alt) . '"><p><strong>' . esc_html($callout) . '</strong></p><p class="note">' . esc_html($note) . '</p></main></body></html>';
	exit;
}
add_action('admin_post_backstage_outreach_distribution_print', 'backstage_outreach_handle_distribution_print');

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
	$default_type = is_array($active_row) ? backstage_outreach_discount_distribution_type($active_row) : 'complimentary';
	$default_cap = is_array($active_row) ? absint($active_row['admission_cap'] ?? 0) : 0;
	$default_order_cap = is_array($active_row) ? absint($active_row['order_cap'] ?? 0) : 0;
	$default_expiry = is_array($active_row) && !empty($active_row['expires_at']) ? str_replace(' ', 'T', substr((string) $active_row['expires_at'], 0, 16)) : '';
	$preview = get_transient(backstage_outreach_campaign_business_preview_key($campaign_id));
	echo '<section id="backstage-outreach-partners" class="vms-pass-card" data-vms-tour="outreach-business-distribution"><h2>' . esc_html__('Shared Business QR Distribution', 'backstage-outreach') . '</h2>';
	if (function_exists('backstage_outreach_help_button')) {
		echo '<p class="vms-pass-actions">' . backstage_outreach_help_button(
			'backstage-outreach.business-qr-setup',
			'outreach-business-help',
			__('Business QR Setup Help', 'backstage-outreach')
		) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Button HTML is produced by BVM's escaped help-button renderer.
	}
	echo '<p class="description">' . esc_html__('Each selected business receives one reusable signed referral link. Choose complimentary claims or a native WooCommerce 50% coupon offer. Opening a link never counts as a completed claim or paid redemption.', 'backstage-outreach') . '</p>';
	if ($source_id <= 0 || absint($campaign['related_batch_id'] ?? 0) <= 0) {
		echo '<div class="notice notice-warning inline"><p>' . esc_html__('Save this campaign with both a Tracking Source and a Use Existing Batch / Invite Link Pool selection first. Create the missing Source or batch on the Guest Passes screen, then return here.', 'backstage-outreach') . '</p></div></section>';
		return;
	}
	if (empty($businesses)) {
		echo '<div class="notice notice-warning inline"><p>' . esc_html__('No active businesses are linked to this campaign Source. Open the Source, add or reactivate a business, then return here to select it.', 'backstage-outreach') . '</p></div>';
	}
	echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" data-vms-tour="outreach-business-selection"><input type="hidden" name="action" value="backstage_outreach_campaign_businesses"><input type="hidden" name="campaign_id" value="' . esc_attr((string) $campaign_id) . '"><input type="hidden" name="distribution_mode" value="preview">';
	wp_nonce_field('backstage_outreach_campaign_businesses');
	echo '<p><label><input type="checkbox" data-backstage-select-all> ' . esc_html__('Select all active businesses', 'backstage-outreach') . '</label></p><div class="vms-pass-grid">';
	foreach ($businesses as $business) {
		$id = (int) $business['id'];
		$checked = isset($by_business[$id]) && (string) $by_business[$id]['status'] === 'active';
		echo '<label class="vms-pass-checkbox"><input type="checkbox" name="business_ids[]" value="' . esc_attr((string) $id) . '"' . checked($checked, true, false) . ' data-backstage-business> <span><strong>' . esc_html((string) $business['business_name']) . '</strong><br><small>' . esc_html((string) $business['contact_name']) . '</small></span></label>';
	}
	echo '<label data-vms-tour="outreach-distribution-type">' . esc_html__('Distribution type', 'backstage-outreach') . '<select name="distribution_type"><option value="complimentary"' . selected($default_type, 'complimentary', false) . '>' . esc_html__('Complimentary Guest Pass', 'backstage-outreach') . '</option><option value="coupon_backed"' . selected($default_type, 'coupon_backed', false) . '>' . esc_html__('Neighborhood Offer — 50% off admission', 'backstage-outreach') . '</option></select><span class="description">' . esc_html__('Neighborhood Offers use a managed native 50% coupon and may share an active Free Guest Pass capacity batch or use a Percent Off batch set to exactly 50%. Complimentary keeps the existing free-only claim path.', 'backstage-outreach') . '</span></label>';
	echo '<label data-vms-tour="outreach-business-limits">' . esc_html__('Optional ticket cap per business', 'backstage-outreach') . '<input type="number" min="0" name="admission_cap" value="' . esc_attr((string) $default_cap) . '"><span class="description">' . esc_html__('For coupon offers, counts discounted ticket quantities. For complimentary links, counts reserved admissions. 0 uses only campaign-wide limits.', 'backstage-outreach') . '</span></label>';
	echo '<label>' . esc_html__('Optional paid-order cap per business', 'backstage-outreach') . '<input type="number" min="0" name="order_cap" value="' . esc_attr((string) $default_order_cap) . '"><span class="description">' . esc_html__('Applied as the managed coupon usage limit. 0 means unlimited orders subject to ticket limits.', 'backstage-outreach') . '</span></label>';
	echo '<label>' . esc_html__('Optional distribution expiry', 'backstage-outreach') . '<input type="datetime-local" name="expires_at" value="' . esc_attr($default_expiry) . '"></label></div>';
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
		echo '<div class="notice notice-info inline" data-vms-tour="outreach-reviewed-preview"><p><strong>' . esc_html__('Review partner distribution changes', 'backstage-outreach') . '</strong></p><p>' . esc_html(sprintf(_n('%d active business link will remain or become active.', '%d active business links will remain or become active.', count($preview_names), 'backstage-outreach'), count($preview_names))) . ' ' . esc_html__('Previously linked businesses omitted from this selection will be paused. No customer invitations are sent.', 'backstage-outreach') . '</p>';
		if (!empty($preview_names)) {
			echo '<p>' . esc_html(implode(', ', $preview_names)) . '</p>';
		}
		$type_label = sanitize_key((string) ($preview['distribution_type'] ?? 'complimentary')) === 'coupon_backed' ? __('Neighborhood Offer — 50% off admission', 'backstage-outreach') : __('Complimentary Guest Pass', 'backstage-outreach');
		echo '<p>' . esc_html(sprintf(__('Type: %1$s · Per-business ticket cap: %2$d · Paid-order cap: %3$d · Expiry: %4$s', 'backstage-outreach'), $type_label, absint($preview['admission_cap'] ?? 0), absint($preview['order_cap'] ?? 0), (string) ($preview['expires_at'] ?? '') !== '' ? (string) $preview['expires_at'] : __('none', 'backstage-outreach'))) . '</p><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="backstage_outreach_campaign_businesses"><input type="hidden" name="campaign_id" value="' . esc_attr((string) $campaign_id) . '"><input type="hidden" name="distribution_mode" value="commit">';
		wp_nonce_field('backstage_outreach_campaign_businesses');
		echo '<button class="button button-primary">' . esc_html__('Save Reviewed Links', 'backstage-outreach') . '</button></form></div>';
	}
	if (!empty($rows)) {
		echo '<table class="widefat striped" data-vms-tour="outreach-business-results"><thead><tr><th>' . esc_html__('Business', 'backstage-outreach') . '</th><th>' . esc_html__('Status', 'backstage-outreach') . '</th><th>' . esc_html__('Referral Link / QR', 'backstage-outreach') . '</th><th>' . esc_html__('Results', 'backstage-outreach') . '</th></tr></thead><tbody>';
		global $wpdb;
		foreach ($rows as $row) {
			$url = backstage_outreach_distribution_url($row);
			$qr = function_exists('bvmgr_pass_claims_claim_qr_image_url') ? bvmgr_pass_claims_claim_qr_image_url($url) : '';
			$is_coupon = backstage_outreach_discount_distribution_type($row) === 'coupon_backed';
			$stats = $is_coupon ? backstage_outreach_discount_paid_stats((int) $row['id']) : $wpdb->get_row($wpdb->prepare("SELECT COUNT(DISTINCT dc.pass_claim_id) claims, COUNT(DISTINCT e.id) admissions, COALESCE(SUM(e.checked_in_qty),0) checked_in FROM %i dc LEFT JOIN %i e ON e.pass_claim_id=dc.pass_claim_id WHERE dc.distribution_id = %d AND dc.status = 'fulfilled'", backstage_outreach_business_table('distribution_claims'), bvmgr_admission_table_entries(), (int) $row['id']), ARRAY_A);
			$coupon_uses = $is_coupon && absint($row['coupon_id'] ?? 0) > 0 && class_exists('WC_Coupon') ? absint((new WC_Coupon(absint($row['coupon_id'])))->get_usage_count()) : 0;
			echo '<tr><td><strong>' . esc_html((string) $row['business_name']) . '</strong><div class="description">' . esc_html($is_coupon ? __('Neighborhood Offer — 50% off admission', 'backstage-outreach') : __('Complimentary Guest Pass', 'backstage-outreach')) . ($is_coupon && !empty($row['coupon_code']) ? ' · ' . esc_html((string) $row['coupon_code']) : '') . '</div></td><td>' . esc_html((string) $row['status']) . '</td><td data-vms-tour="outreach-qr-actions"><input class="regular-text" readonly value="' . esc_attr($url) . '" data-backstage-copy-value> <button type="button" class="button button-small" data-backstage-copy>' . esc_html__('Copy Link', 'backstage-outreach') . '</button>';
			if ($qr !== '') {
				echo ' <a class="button button-small" href="' . esc_url($qr) . '" target="_blank" rel="noopener" download>' . esc_html__('QR Download', 'backstage-outreach') . '</a>';
			}
			$print = wp_nonce_url(add_query_arg(array('action' => 'backstage_outreach_distribution_print', 'distribution_id' => (int) $row['id']), admin_url('admin-post.php')), 'backstage_outreach_distribution_print_' . (int) $row['id']);
			echo ' <a class="button button-small" href="' . esc_url($print) . '" target="_blank">' . esc_html__('Printable QR', 'backstage-outreach') . '</a>';
			if ((string) $row['status'] !== 'revoked') {
				$next_status = (string) $row['status'] === 'active' ? 'paused' : 'active';
				$status_url = wp_nonce_url(add_query_arg(array('action' => 'backstage_outreach_distribution_status', 'distribution_id' => (int) $row['id'], 'status' => $next_status), admin_url('admin-post.php')), 'backstage_outreach_distribution_status_' . (int) $row['id'] . '_' . $next_status);
				$revoke_url = wp_nonce_url(add_query_arg(array('action' => 'backstage_outreach_distribution_status', 'distribution_id' => (int) $row['id'], 'status' => 'revoked'), admin_url('admin-post.php')), 'backstage_outreach_distribution_status_' . (int) $row['id'] . '_revoked');
				echo ' <span data-vms-tour="outreach-offer-lifecycle"><a class="button button-small" href="' . esc_url($status_url) . '">' . esc_html($next_status === 'active' ? __('Resume', 'backstage-outreach') : __('Pause', 'backstage-outreach')) . '</a> <a class="button button-small" href="' . esc_url($revoke_url) . '" onclick="return confirm(' . esc_attr(wp_json_encode(__('Revoke this reusable link? Existing customer passes remain valid.', 'backstage-outreach'))) . ');">' . esc_html__('Revoke', 'backstage-outreach') . '</a></span>';
			} else {
				echo ' <span class="description" data-vms-tour="outreach-offer-lifecycle">' . esc_html__('Revocation is permanent for this link.', 'backstage-outreach') . '</span>';
			}
			$currency = sanitize_text_field((string) ($stats['currency'] ?? ''));
			$revenue = trim(($currency !== '' ? $currency . ' ' : '') . number_format_i18n((float) ($stats['order_revenue'] ?? 0), 2));
			$results = $is_coupon
				? sprintf(__('%1$d coupon uses · %2$d paid orders · %3$d discounted tickets · %4$s net order revenue · %5$d review', 'backstage-outreach'), $coupon_uses, (int) ($stats['paid_orders'] ?? 0), (int) ($stats['discounted_tickets'] ?? 0), $revenue, (int) ($stats['review_required_orders'] ?? 0))
				: sprintf(__('%1$d claims · %2$d reserved admissions · %3$d checked in', 'backstage-outreach'), (int) ($stats['claims'] ?? 0), (int) ($stats['admissions'] ?? 0), (int) ($stats['checked_in'] ?? 0));
			echo '</td><td>' . esc_html($results) . '</td></tr>';
		}
		echo '</tbody></table><p><a class="button" href="' . esc_url(wp_nonce_url(add_query_arg(array('action' => 'backstage_outreach_distribution_export', 'campaign_id' => $campaign_id), admin_url('admin-post.php')), 'backstage_outreach_distribution_export_' . $campaign_id)) . '">' . esc_html__('Export Business Links / QRs', 'backstage-outreach') . '</a></p>';
	}
	echo '<script>(function(){var all=document.querySelector("[data-backstage-select-all]");if(all){all.addEventListener("change",function(){document.querySelectorAll("[data-backstage-business]").forEach(function(el){el.checked=all.checked;});});}document.querySelectorAll("[data-backstage-copy]").forEach(function(btn){btn.addEventListener("click",function(){var input=btn.parentNode.querySelector("[data-backstage-copy-value]");if(input&&navigator.clipboard){navigator.clipboard.writeText(input.value);btn.textContent="Copied";}});});})();</script></section>';
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
	fputcsv($out, array('campaign_id', 'campaign_name', 'source_id', 'business_id', 'business_name', 'distribution_type', 'status', 'coupon_id', 'coupon_code', 'coupon_uses', 'paid_orders', 'discounted_ticket_quantity', 'coupon_discount_total', 'eligible_ticket_gross_total', 'admission_revenue', 'order_revenue', 'refunded_total', 'refunded_orders', 'cancelled_orders', 'review_required_orders', 'currency', 'complimentary_claims', 'complimentary_admissions', 'partner_link', 'qr_image_url'));
	foreach (backstage_outreach_distribution_rows($campaign_id) as $row) {
		$url = backstage_outreach_distribution_url($row);
		$qr = function_exists('bvmgr_pass_claims_claim_qr_image_url') ? bvmgr_pass_claims_claim_qr_image_url($url) : '';
		$paid = backstage_outreach_discount_paid_stats((int) $row['id']);
		global $wpdb;
		$free = $wpdb->get_row($wpdb->prepare("SELECT COUNT(DISTINCT pass_claim_id) claims, COALESCE(SUM(party_size),0) admissions FROM %i WHERE distribution_id=%d AND status='fulfilled'", backstage_outreach_business_table('distribution_claims'), (int) $row['id']), ARRAY_A);
		$coupon_uses = absint($row['coupon_id'] ?? 0) > 0 && class_exists('WC_Coupon') ? absint((new WC_Coupon(absint($row['coupon_id'])))->get_usage_count()) : 0;
		fputcsv($out, array($campaign_id, backstage_outreach_csv_safe((string) $campaign['campaign_name']), (int) $row['source_id'], (int) $row['business_id'], backstage_outreach_csv_safe((string) $row['business_name']), backstage_outreach_discount_distribution_type($row), (string) $row['status'], absint($row['coupon_id'] ?? 0), backstage_outreach_csv_safe((string) ($row['coupon_code'] ?? '')), $coupon_uses, (int) ($paid['paid_orders'] ?? 0), (int) ($paid['discounted_tickets'] ?? 0), (float) ($paid['discount_total'] ?? 0), (float) ($paid['eligible_ticket_gross_total'] ?? 0), (float) ($paid['admission_revenue'] ?? 0), (float) ($paid['order_revenue'] ?? 0), (float) ($paid['refunded_total'] ?? 0), (int) ($paid['refunded_orders'] ?? 0), (int) ($paid['cancelled_orders'] ?? 0), (int) ($paid['review_required_orders'] ?? 0), backstage_outreach_csv_safe((string) ($paid['currency'] ?? '')), (int) ($free['claims'] ?? 0), (int) ($free['admissions'] ?? 0), $url, $qr));
	}
	fclose($out);
	exit;
}
add_action('admin_post_backstage_outreach_distribution_export', 'backstage_outreach_handle_distribution_export');

function backstage_outreach_register_partner_route(): void
{
	add_rewrite_tag('%backstage_outreach_partner_token%', '([^&]+)');
	add_rewrite_rule('^guest-pass/partner/([^/]+)/?$', 'index.php?backstage_outreach_partner_token=$matches[1]', 'top');
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
		return new WP_Error('partner_link_paused', $is_coupon ? __('This Neighborhood Offer is paused or revoked.', 'backstage-outreach') : __('This partner Guest Pass link is paused or revoked.', 'backstage-outreach'), $error_data);
	}
	if ((string) $row['campaign_status'] !== 'active') {
		return new WP_Error('partner_campaign_inactive', $is_coupon ? __('This Neighborhood Offer campaign is not currently active.', 'backstage-outreach') : __('This Guest Pass campaign is not currently active.', 'backstage-outreach'), $error_data);
	}
	if ((int) $row['source_id'] !== (int) $row['related_source_id']) {
		return new WP_Error('partner_link_mismatch', __('This partner link no longer matches its campaign Source.', 'backstage-outreach'), $error_data);
	}
	if (!empty($row['expires_at'])) {
		try {
			$expires = new DateTimeImmutable((string) $row['expires_at'], wp_timezone());
			if ($expires->getTimestamp() <= time()) {
				return new WP_Error('partner_link_expired', $is_coupon ? __('This Neighborhood Offer has expired.', 'backstage-outreach') : __('This partner Guest Pass link has expired.', 'backstage-outreach'), $error_data);
			}
		} catch (Exception $error) {
			return new WP_Error('partner_link_expired', $is_coupon ? __('This Neighborhood Offer has an invalid expiry.', 'backstage-outreach') : __('This partner Guest Pass link has an invalid expiry.', 'backstage-outreach'), $error_data);
		}
	}
	$batch = bvmgr_pass_claims_get_batch_by_id((int) $row['related_batch_id']);
	if (!$batch || (string) ($batch['status'] ?? '') !== 'active' || (int) ($batch['source_id'] ?? 0) !== (int) $row['source_id']) {
		return new WP_Error('partner_batch_inactive', $is_coupon ? __('This Neighborhood Offer is not currently available.', 'backstage-outreach') : __('This Guest Pass offer is not currently available.', 'backstage-outreach'), $error_data);
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
		$token = $wpdb->get_row($wpdb->prepare("SELECT * FROM %i WHERE batch_id = %d AND status = 'unclaimed' ORDER BY id ASC LIMIT 1 FOR UPDATE", bvmgr_admission_table_pass_tokens(), $batch_id), ARRAY_A);
		if (!is_array($token)) {
			$wpdb->query('ROLLBACK');
			return new WP_Error('campaign_exhausted', __('This Guest Pass campaign has no passes remaining.', 'backstage-outreach'));
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
		$status_label = $error_type === 'coupon_backed' ? __('Neighborhood Offer', 'backstage-outreach') : ($error_type === 'complimentary' ? __('Guest Pass', 'backstage-outreach') : __('Business Offer', 'backstage-outreach'));
		$status_title = $error_type === 'coupon_backed' ? __('Neighborhood Offer Unavailable', 'backstage-outreach') : ($error_type === 'complimentary' ? __('Guest Pass Unavailable', 'backstage-outreach') : __('Business Offer Unavailable', 'backstage-outreach'));
		bvmgr_pass_claims_render_public_status_screen($status_label, $status_title, $distribution->get_error_message());
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
		bvmgr_pass_claims_render_public_status_screen(__('Guest Pass', 'backstage-outreach'), __('No Eligible Events', 'backstage-outreach'), __('There are no eligible events for this Guest Pass right now.', 'backstage-outreach'));
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
