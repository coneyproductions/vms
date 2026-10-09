<?php
/**
 * Canonical Outreach Party identities and reviewed legacy associations.
 *
 * Phase 1 is deliberately additive. Legacy operational rows remain authoritative
 * for campaigns, delivery, links, coupons, claims, and orders.
 */

defined('ABSPATH') || exit;

function backstage_outreach_party_schema_option_key(): string
{
	return 'backstage_outreach_party_db_version';
}

function backstage_outreach_party_schema_target(): string
{
	return '1.2.0';
}

function backstage_outreach_party_table(string $suffix): string
{
	global $wpdb;
	return $wpdb->prefix . 'vms_outreach_party_' . $suffix;
}

function backstage_outreach_party_schema_upgrade(): void
{
	if ((string) get_option(backstage_outreach_party_schema_option_key(), '') === backstage_outreach_party_schema_target()) {
		return;
	}

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	global $wpdb;
	$collate = $wpdb->get_charset_collate();

	dbDelta('CREATE TABLE ' . backstage_outreach_party_table('parties') . " (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		public_id CHAR(36) NOT NULL,
		party_type VARCHAR(24) NOT NULL,
		display_name VARCHAR(190) NOT NULL,
		given_name VARCHAR(120) NULL,
		family_name VARCHAR(120) NULL,
		organization_name VARCHAR(190) NULL,
		identifying_details VARCHAR(255) NULL,
		notes LONGTEXT NULL,
		status VARCHAR(24) NOT NULL DEFAULT 'active',
		provenance_type VARCHAR(40) NOT NULL DEFAULT 'manual',
		provenance_key VARCHAR(190) NULL,
		created_by BIGINT(20) UNSIGNED NOT NULL,
		created_at DATETIME NOT NULL,
		updated_by BIGINT(20) UNSIGNED NULL,
		updated_at DATETIME NULL,
		PRIMARY KEY (id),
		UNIQUE KEY public_id (public_id),
		KEY party_type_status (party_type, status),
		KEY display_name (display_name),
		KEY organization_name (organization_name),
		KEY provenance_key (provenance_key)
	) {$collate};");

	dbDelta('CREATE TABLE ' . backstage_outreach_party_table('contact_methods') . " (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		party_id BIGINT(20) UNSIGNED NOT NULL,
		method_type VARCHAR(32) NOT NULL,
		label VARCHAR(80) NULL,
		value VARCHAR(255) NOT NULL,
		value_norm VARCHAR(190) NOT NULL,
		is_primary TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
		status VARCHAR(24) NOT NULL DEFAULT 'active',
		provenance_type VARCHAR(40) NOT NULL DEFAULT 'manual',
		provenance_key VARCHAR(190) NULL,
		created_by BIGINT(20) UNSIGNED NOT NULL,
		created_at DATETIME NOT NULL,
		updated_by BIGINT(20) UNSIGNED NULL,
		updated_at DATETIME NULL,
		PRIMARY KEY (id),
		UNIQUE KEY party_method_value (party_id, method_type, value_norm),
		KEY method_value (method_type, value_norm),
		KEY party_status (party_id, status),
		KEY primary_method (party_id, method_type, is_primary)
	) {$collate};");

	dbDelta('CREATE TABLE ' . backstage_outreach_party_table('affiliations') . " (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		person_party_id BIGINT(20) UNSIGNED NOT NULL,
		organization_party_id BIGINT(20) UNSIGNED NOT NULL,
		relationship_role VARCHAR(80) NOT NULL DEFAULT 'member',
		status VARCHAR(24) NOT NULL DEFAULT 'active',
		starts_on DATE NULL,
		ends_on DATE NULL,
		provenance_type VARCHAR(40) NOT NULL DEFAULT 'manual',
		notes TEXT NULL,
		created_by BIGINT(20) UNSIGNED NOT NULL,
		created_at DATETIME NOT NULL,
		updated_by BIGINT(20) UNSIGNED NULL,
		updated_at DATETIME NULL,
		PRIMARY KEY (id),
		UNIQUE KEY person_organization_role (person_party_id, organization_party_id, relationship_role),
		KEY organization_status (organization_party_id, status),
		KEY person_status (person_party_id, status)
	) {$collate};");

	dbDelta('CREATE TABLE ' . backstage_outreach_party_table('sources') . " (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		party_id BIGINT(20) UNSIGNED NOT NULL,
		source_id BIGINT(20) UNSIGNED NOT NULL,
		status VARCHAR(24) NOT NULL DEFAULT 'active',
		provenance_type VARCHAR(40) NOT NULL DEFAULT 'manual',
		provenance_key VARCHAR(190) NULL,
		created_by BIGINT(20) UNSIGNED NOT NULL,
		created_at DATETIME NOT NULL,
		updated_by BIGINT(20) UNSIGNED NULL,
		updated_at DATETIME NULL,
		PRIMARY KEY (id),
		UNIQUE KEY party_source (party_id, source_id),
		KEY source_status (source_id, status),
		KEY party_status (party_id, status)
	) {$collate};");

	dbDelta('CREATE TABLE ' . backstage_outreach_party_table('campaign_roles') . " (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		party_id BIGINT(20) UNSIGNED NOT NULL,
		campaign_id BIGINT(20) UNSIGNED NOT NULL,
		role_type VARCHAR(40) NOT NULL,
		status VARCHAR(24) NOT NULL DEFAULT 'active',
		provenance_type VARCHAR(40) NOT NULL DEFAULT 'manual',
		created_by BIGINT(20) UNSIGNED NOT NULL,
		created_at DATETIME NOT NULL,
		updated_by BIGINT(20) UNSIGNED NULL,
		updated_at DATETIME NULL,
		PRIMARY KEY (id),
		UNIQUE KEY party_campaign_role (party_id, campaign_id, role_type),
		KEY campaign_status (campaign_id, status),
		KEY party_status (party_id, status)
	) {$collate};");

	dbDelta('CREATE TABLE ' . backstage_outreach_party_table('legacy_links') . " (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		party_id BIGINT(20) UNSIGNED NOT NULL,
		legacy_type VARCHAR(40) NOT NULL,
		legacy_id BIGINT(20) UNSIGNED NOT NULL,
		legacy_snapshot_hash CHAR(64) NOT NULL,
		match_reasons_json LONGTEXT NULL,
		provenance_type VARCHAR(40) NOT NULL DEFAULT 'reviewed_operator',
		reviewed_by BIGINT(20) UNSIGNED NOT NULL,
		reviewed_at DATETIME NOT NULL,
		created_at DATETIME NOT NULL,
		updated_at DATETIME NULL,
		PRIMARY KEY (id),
		UNIQUE KEY legacy_reference (legacy_type, legacy_id),
		UNIQUE KEY party_legacy (party_id, legacy_type, legacy_id),
		KEY party_id (party_id),
		KEY reviewed_at (reviewed_at)
	) {$collate};");

	dbDelta('CREATE TABLE ' . backstage_outreach_party_table('identity_audit') . " (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		action VARCHAR(40) NOT NULL,
		legacy_type VARCHAR(40) NULL,
		legacy_id BIGINT(20) UNSIGNED NULL,
		from_party_id BIGINT(20) UNSIGNED NULL,
		to_party_id BIGINT(20) UNSIGNED NULL,
		details_json LONGTEXT NULL,
		request_key CHAR(64) NOT NULL,
		operator_user_id BIGINT(20) UNSIGNED NOT NULL,
		created_at DATETIME NOT NULL,
		PRIMARY KEY (id),
		UNIQUE KEY request_key (request_key),
		KEY legacy_reference (legacy_type, legacy_id),
		KEY from_party (from_party_id),
		KEY to_party (to_party_id),
		KEY action_created (action, created_at)
	) {$collate};");

	dbDelta('CREATE TABLE ' . backstage_outreach_party_table('referral_distributions') . " (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		campaign_id BIGINT(20) UNSIGNED NOT NULL,
		source_id BIGINT(20) UNSIGNED NOT NULL,
		party_id BIGINT(20) UNSIGNED NOT NULL,
		public_key VARCHAR(64) NOT NULL,
		token_hash CHAR(64) NOT NULL,
		status VARCHAR(20) NOT NULL DEFAULT 'active',
		admission_cap INT(10) UNSIGNED NOT NULL DEFAULT 0,
		order_cap INT(10) UNSIGNED NOT NULL DEFAULT 0,
		coupon_id BIGINT(20) UNSIGNED NULL,
		coupon_code VARCHAR(100) NULL,
		eligible_event_ids_json LONGTEXT NULL,
		eligible_product_ids_json LONGTEXT NULL,
		configuration_hash CHAR(64) NOT NULL,
		expires_at DATETIME NULL,
		created_by BIGINT(20) UNSIGNED NOT NULL,
		created_at DATETIME NOT NULL,
		updated_by BIGINT(20) UNSIGNED NULL,
		updated_at DATETIME NULL,
		PRIMARY KEY (id),
		UNIQUE KEY campaign_party (campaign_id, party_id),
		UNIQUE KEY public_key (public_key),
		KEY campaign_status (campaign_id, status),
		KEY source_party (source_id, party_id)
	) {$collate};");

	dbDelta('CREATE TABLE ' . backstage_outreach_party_table('referral_redemptions') . " (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		distribution_id BIGINT(20) UNSIGNED NOT NULL,
		campaign_id BIGINT(20) UNSIGNED NOT NULL,
		source_id BIGINT(20) UNSIGNED NOT NULL,
		party_id BIGINT(20) UNSIGNED NOT NULL,
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
		KEY party_status (party_id, status),
		KEY coupon_id (coupon_id)
	) {$collate};");

	dbDelta('CREATE TABLE ' . backstage_outreach_party_table('contact_activities') . " (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		campaign_id BIGINT(20) UNSIGNED NOT NULL,
		distribution_id BIGINT(20) UNSIGNED NOT NULL,
		party_id BIGINT(20) UNSIGNED NOT NULL,
		activity_type VARCHAR(32) NOT NULL,
		activity_status VARCHAR(32) NOT NULL,
		contact_method VARCHAR(32) NULL,
		contact_value VARCHAR(255) NULL,
		subject_snapshot TEXT NULL,
		message_snapshot LONGTEXT NULL,
		content_hash CHAR(64) NULL,
		notes LONGTEXT NULL,
		request_key CHAR(64) NOT NULL,
		created_by BIGINT(20) UNSIGNED NOT NULL,
		created_at DATETIME NOT NULL,
		PRIMARY KEY (id),
		UNIQUE KEY request_key (request_key),
		KEY campaign_party (campaign_id, party_id),
		KEY distribution_status (distribution_id, activity_status),
		KEY party_created (party_id, created_at)
	) {$collate};");

	update_option(backstage_outreach_party_schema_option_key(), backstage_outreach_party_schema_target(), false);
}

function backstage_outreach_party_now(): string
{
	return function_exists('backstage_outreach_business_now') ? backstage_outreach_business_now() : current_time('mysql');
}

function backstage_outreach_party_types(): array
{
	return array(
		'person' => __('Person', 'backstage-outreach'),
		'organization' => __('Organization', 'backstage-outreach'),
	);
}

function backstage_outreach_party_statuses(): array
{
	return array(
		'active' => __('Active', 'backstage-outreach'),
		'needs_review' => __('Needs review', 'backstage-outreach'),
		'archived' => __('Archived', 'backstage-outreach'),
	);
}

function backstage_outreach_party_contact_types(): array
{
	return array(
		'email' => __('Email', 'backstage-outreach'),
		'phone' => __('Phone', 'backstage-outreach'),
		'website' => __('Website', 'backstage-outreach'),
		'facebook' => __('Facebook', 'backstage-outreach'),
		'instagram' => __('Instagram', 'backstage-outreach'),
		'linkedin' => __('LinkedIn', 'backstage-outreach'),
		'other' => __('Other', 'backstage-outreach'),
	);
}

function backstage_outreach_party_legacy_types(): array
{
	return array(
		'outreach_contact' => __('Legacy Outreach Contact', 'backstage-outreach'),
		'business' => __('Reusable Business', 'backstage-outreach'),
		'campaign_recipient' => __('Historical Campaign Recipient', 'backstage-outreach'),
	);
}

function backstage_outreach_party_default(): array
{
	return array(
		'id' => 0,
		'public_id' => '',
		'party_type' => 'person',
		'display_name' => '',
		'given_name' => '',
		'family_name' => '',
		'organization_name' => '',
		'identifying_details' => '',
		'notes' => '',
		'status' => 'active',
		'provenance_type' => 'manual',
		'provenance_key' => '',
		'created_by' => 0,
		'created_at' => '',
		'updated_by' => 0,
		'updated_at' => '',
	);
}

function backstage_outreach_party_normalize_row(array $row): array
{
	$row = array_merge(backstage_outreach_party_default(), $row);
	foreach (array('display_name', 'given_name', 'family_name', 'organization_name', 'identifying_details', 'provenance_type', 'provenance_key', 'created_at', 'updated_at') as $key) {
		$row[$key] = sanitize_text_field((string) ($row[$key] ?? ''));
	}
	$row['id'] = absint($row['id']);
	$row['public_id'] = sanitize_text_field((string) $row['public_id']);
	$row['party_type'] = sanitize_key((string) $row['party_type']);
	$row['status'] = sanitize_key((string) $row['status']);
	$row['notes'] = sanitize_textarea_field((string) $row['notes']);
	$row['created_by'] = absint($row['created_by']);
	$row['updated_by'] = absint($row['updated_by']);
	return $row;
}

function backstage_outreach_party_sanitize_payload(array $raw)
{
	$type = sanitize_key((string) ($raw['party_type'] ?? 'person'));
	if (!isset(backstage_outreach_party_types()[$type])) {
		return new WP_Error('invalid_party_type', __('Choose Person or Organization.', 'backstage-outreach'));
	}
	$given = sanitize_text_field((string) ($raw['given_name'] ?? ''));
	$family = sanitize_text_field((string) ($raw['family_name'] ?? ''));
	$organization = sanitize_text_field((string) ($raw['organization_name'] ?? ''));
	$display = sanitize_text_field((string) ($raw['display_name'] ?? ''));
	if ($display === '') {
		$display = $type === 'organization' ? $organization : trim($given . ' ' . $family);
	}
	if ($display === '') {
		return new WP_Error('party_name_required', __('Enter a display name for this Party.', 'backstage-outreach'));
	}
	$status = sanitize_key((string) ($raw['status'] ?? 'active'));
	if (!isset(backstage_outreach_party_statuses()[$status])) {
		$status = 'active';
	}
	return array(
		'party_type' => $type,
		'display_name' => $display,
		'given_name' => $type === 'person' ? ($given !== '' ? $given : null) : null,
		'family_name' => $type === 'person' ? ($family !== '' ? $family : null) : null,
		'organization_name' => $type === 'organization' ? ($organization !== '' ? $organization : $display) : null,
		'identifying_details' => ($details = sanitize_text_field((string) ($raw['identifying_details'] ?? ''))) !== '' ? $details : null,
		'notes' => ($notes = sanitize_textarea_field((string) ($raw['notes'] ?? ''))) !== '' ? $notes : null,
		'status' => $status,
		'provenance_type' => sanitize_key((string) ($raw['provenance_type'] ?? 'manual')) ?: 'manual',
		'provenance_key' => ($provenance = sanitize_text_field((string) ($raw['provenance_key'] ?? ''))) !== '' ? $provenance : null,
	);
}

function backstage_outreach_party_get(int $party_id): ?array
{
	global $wpdb;
	if ($party_id <= 0) {
		return null;
	}
	$row = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d', backstage_outreach_party_table('parties'), $party_id), ARRAY_A);
	return is_array($row) ? backstage_outreach_party_normalize_row($row) : null;
}

function backstage_outreach_party_audit(string $action, array $data, int $user_id, string $request_key = ''): bool
{
	global $wpdb;
	$request_key = trim($request_key);
	if (!preg_match('/^[a-f0-9]{64}$/', $request_key)) {
		$request_key = hash('sha256', wp_json_encode(array($action, $data, $user_id, wp_generate_uuid4(), microtime(true))));
	}
	$result = $wpdb->insert(
		backstage_outreach_party_table('identity_audit'),
		array(
			'action' => sanitize_key($action),
			'legacy_type' => ($legacy_type = sanitize_key((string) ($data['legacy_type'] ?? ''))) !== '' ? $legacy_type : null,
			'legacy_id' => absint($data['legacy_id'] ?? 0) ?: null,
			'from_party_id' => absint($data['from_party_id'] ?? 0) ?: null,
			'to_party_id' => absint($data['to_party_id'] ?? 0) ?: null,
			'details_json' => wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
			'request_key' => $request_key,
			'operator_user_id' => $user_id,
			'created_at' => backstage_outreach_party_now(),
		),
		array('%s', '%s', '%d', '%d', '%d', '%s', '%s', '%d', '%s')
	);
	if ($result !== false) {
		return true;
	}
	return (int) $wpdb->get_var($wpdb->prepare('SELECT id FROM %i WHERE request_key=%s', backstage_outreach_party_table('identity_audit'), $request_key)) > 0;
}

function backstage_outreach_party_save(array $raw, int $user_id, int $party_id = 0)
{
	global $wpdb;
	$payload = backstage_outreach_party_sanitize_payload($raw);
	if (is_wp_error($payload)) {
		return $payload;
	}
	$now = backstage_outreach_party_now();
	if ($party_id > 0) {
		$existing_party = backstage_outreach_party_get($party_id);
		if ($existing_party === null) {
			return new WP_Error('party_not_found', __('The selected Party no longer exists.', 'backstage-outreach'));
		}
		if ((string) $existing_party['party_type'] !== (string) $payload['party_type']) {
			$association_count = (int) $wpdb->get_var($wpdb->prepare(
				'SELECT (
					(SELECT COUNT(*) FROM %i WHERE (person_party_id=%d OR organization_party_id=%d) AND status=%s) +
					(SELECT COUNT(*) FROM %i WHERE party_id=%d AND status=%s) +
					(SELECT COUNT(*) FROM %i WHERE party_id=%d AND status=%s) +
					(SELECT COUNT(*) FROM %i WHERE party_id=%d)
				)',
				backstage_outreach_party_table('affiliations'), $party_id, $party_id, 'active',
				backstage_outreach_party_table('sources'), $party_id, 'active',
				backstage_outreach_party_table('campaign_roles'), $party_id, 'active',
				backstage_outreach_party_table('referral_distributions'), $party_id
			));
			if ($association_count > 0) {
				return new WP_Error('party_type_associations_exist', __('Remove or resolve this Party’s active affiliations, Source/campaign associations, and Partner offers before changing between Person and Organization.', 'backstage-outreach'));
			}
		}
		$payload['updated_by'] = $user_id;
		$payload['updated_at'] = $now;
		if ($wpdb->update(backstage_outreach_party_table('parties'), $payload, array('id' => $party_id)) === false) {
			return new WP_Error('party_save_failed', __('The Party could not be updated.', 'backstage-outreach'));
		}
		backstage_outreach_party_audit('party_updated', array('to_party_id' => $party_id), $user_id);
		return backstage_outreach_party_get($party_id);
	}
	$payload['public_id'] = wp_generate_uuid4();
	$payload['created_by'] = $user_id;
	$payload['created_at'] = $now;
	if (!$wpdb->insert(backstage_outreach_party_table('parties'), $payload)) {
		return new WP_Error('party_save_failed', __('The Party could not be created.', 'backstage-outreach'));
	}
	$party_id = (int) $wpdb->insert_id;
	backstage_outreach_party_audit('party_created', array('to_party_id' => $party_id), $user_id);
	return backstage_outreach_party_get($party_id);
}

function backstage_outreach_party_normalize_contact_value(string $type, string $value): string
{
	$type = sanitize_key($type);
	$value = trim($value);
	if ($type === 'email') {
		return function_exists('vms_outreach_normalize_email') ? vms_outreach_normalize_email($value) : strtolower(sanitize_email($value));
	}
	if ($type === 'phone') {
		return function_exists('vms_outreach_normalize_phone') ? vms_outreach_normalize_phone($value) : preg_replace('/\D+/', '', $value);
	}
	if (in_array($type, array('website', 'facebook', 'instagram', 'linkedin'), true)) {
		$url = esc_url_raw($value);
		return strtolower(untrailingslashit($url));
	}
	return strtolower(sanitize_text_field($value));
}

function backstage_outreach_party_get_contact_methods(int $party_id, bool $include_inactive = true): array
{
	global $wpdb;
	$sql = 'SELECT * FROM %i WHERE party_id=%d';
	$args = array(backstage_outreach_party_table('contact_methods'), $party_id);
	if (!$include_inactive) {
		$sql .= ' AND status=%s';
		$args[] = 'active';
	}
	$sql .= ' ORDER BY method_type ASC,is_primary DESC,id ASC';
	return array_values((array) $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A));
}

function backstage_outreach_party_save_contact_method(int $party_id, array $raw, int $user_id, int $method_id = 0)
{
	global $wpdb;
	if (backstage_outreach_party_get($party_id) === null) {
		return new WP_Error('party_not_found', __('The selected Party no longer exists.', 'backstage-outreach'));
	}
	$type = sanitize_key((string) ($raw['method_type'] ?? ''));
	if (!isset(backstage_outreach_party_contact_types()[$type])) {
		return new WP_Error('invalid_contact_method_type', __('Choose a supported contact method.', 'backstage-outreach'));
	}
	$value = trim((string) ($raw['value'] ?? ''));
	if ($type === 'email') {
		$value = sanitize_email($value);
		if ($value === '') {
			return new WP_Error('invalid_contact_method', __('Enter a valid email address.', 'backstage-outreach'));
		}
	} elseif (in_array($type, array('website', 'facebook', 'instagram', 'linkedin'), true)) {
		$value = esc_url_raw($value);
		if ($value === '') {
			return new WP_Error('invalid_contact_method', __('Enter a valid URL.', 'backstage-outreach'));
		}
	} else {
		$value = sanitize_text_field($value);
		if ($value === '') {
			return new WP_Error('invalid_contact_method', __('Enter a contact value.', 'backstage-outreach'));
		}
	}
	$norm = substr(backstage_outreach_party_normalize_contact_value($type, $value), 0, 190);
	if ($norm === '') {
		return new WP_Error('invalid_contact_method', __('Enter a usable contact value.', 'backstage-outreach'));
	}
	$status = sanitize_key((string) ($raw['status'] ?? 'active'));
	if (!in_array($status, array('active', 'inactive'), true)) {
		$status = 'active';
	}
	$is_primary = !empty($raw['is_primary']) ? 1 : 0;
	$now = backstage_outreach_party_now();
	$payload = array(
		'party_id' => $party_id,
		'method_type' => $type,
		'label' => ($label = sanitize_text_field((string) ($raw['label'] ?? ''))) !== '' ? $label : null,
		'value' => $value,
		'value_norm' => $norm,
		'is_primary' => $is_primary,
		'status' => $status,
		'provenance_type' => sanitize_key((string) ($raw['provenance_type'] ?? 'manual')) ?: 'manual',
		'provenance_key' => ($provenance = sanitize_text_field((string) ($raw['provenance_key'] ?? ''))) !== '' ? $provenance : null,
	);

	$wpdb->query('START TRANSACTION');
	try {
		if ($method_id > 0) {
			$current = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d AND party_id=%d FOR UPDATE', backstage_outreach_party_table('contact_methods'), $method_id, $party_id), ARRAY_A);
			if (!is_array($current)) {
				throw new RuntimeException('contact_method_not_found');
			}
			$payload['updated_by'] = $user_id;
			$payload['updated_at'] = $now;
			$result = $wpdb->update(backstage_outreach_party_table('contact_methods'), $payload, array('id' => $method_id, 'party_id' => $party_id));
		} else {
			$existing_id = (int) $wpdb->get_var($wpdb->prepare('SELECT id FROM %i WHERE party_id=%d AND method_type=%s AND value_norm=%s FOR UPDATE', backstage_outreach_party_table('contact_methods'), $party_id, $type, $norm));
			if ($existing_id > 0) {
				$method_id = $existing_id;
				$payload['updated_by'] = $user_id;
				$payload['updated_at'] = $now;
				$result = $wpdb->update(backstage_outreach_party_table('contact_methods'), $payload, array('id' => $method_id));
			} else {
				$payload['created_by'] = $user_id;
				$payload['created_at'] = $now;
				$result = $wpdb->insert(backstage_outreach_party_table('contact_methods'), $payload);
				$method_id = (int) $wpdb->insert_id;
			}
		}
		if ($result === false || $method_id <= 0) {
			throw new RuntimeException('contact_method_save_failed');
		}
		if ($is_primary) {
			$wpdb->query($wpdb->prepare('UPDATE %i SET is_primary=0,updated_by=%d,updated_at=%s WHERE party_id=%d AND method_type=%s AND id<>%d', backstage_outreach_party_table('contact_methods'), $user_id, $now, $party_id, $type, $method_id));
		}
		backstage_outreach_party_audit('contact_method_saved', array('to_party_id' => $party_id, 'method_id' => $method_id, 'method_type' => $type), $user_id);
		$wpdb->query('COMMIT');
	} catch (Throwable $exception) {
		$wpdb->query('ROLLBACK');
		$message = $exception->getMessage() === 'contact_method_not_found' ? __('The contact method no longer exists.', 'backstage-outreach') : __('The contact method could not be saved.', 'backstage-outreach');
		return new WP_Error($exception->getMessage(), $message);
	}
	return $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d', backstage_outreach_party_table('contact_methods'), $method_id), ARRAY_A);
}

function backstage_outreach_party_get_sources(int $party_id): array
{
	global $wpdb;
	return array_values((array) $wpdb->get_results($wpdb->prepare(
		'SELECT ps.*,s.source_name,s.status AS source_status FROM %i ps LEFT JOIN %i s ON s.id=ps.source_id WHERE ps.party_id=%d ORDER BY s.source_name ASC,ps.source_id ASC',
		backstage_outreach_party_table('sources'),
		bvmgr_admission_table_pass_sources(),
		$party_id
	), ARRAY_A));
}

function backstage_outreach_party_link_source(int $party_id, int $source_id, int $user_id, string $provenance = 'manual')
{
	global $wpdb;
	if (backstage_outreach_party_get($party_id) === null) {
		return new WP_Error('party_not_found', __('The selected Party no longer exists.', 'backstage-outreach'));
	}
	$source = $wpdb->get_row($wpdb->prepare('SELECT id FROM %i WHERE id=%d', bvmgr_admission_table_pass_sources(), $source_id), ARRAY_A);
	if (!is_array($source)) {
		return new WP_Error('source_not_found', __('The selected Source no longer exists.', 'backstage-outreach'));
	}
	$table = backstage_outreach_party_table('sources');
	$existing = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE party_id=%d AND source_id=%d', $table, $party_id, $source_id), ARRAY_A);
	$now = backstage_outreach_party_now();
	if (is_array($existing)) {
		$wpdb->update($table, array('status' => 'active', 'updated_by' => $user_id, 'updated_at' => $now), array('id' => absint($existing['id'])));
		return $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d', $table, absint($existing['id'])), ARRAY_A);
	}
	if (!$wpdb->insert($table, array('party_id' => $party_id, 'source_id' => $source_id, 'status' => 'active', 'provenance_type' => sanitize_key($provenance) ?: 'manual', 'created_by' => $user_id, 'created_at' => $now))) {
		return new WP_Error('party_source_save_failed', __('The Source association could not be saved.', 'backstage-outreach'));
	}
	$association_id = (int) $wpdb->insert_id;
	backstage_outreach_party_audit('source_linked', array('to_party_id' => $party_id, 'source_id' => $source_id, 'directory_only' => true), $user_id);
	return $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d', $table, $association_id), ARRAY_A);
}

function backstage_outreach_party_unlink_source(int $party_id, int $source_id, int $user_id): bool
{
	global $wpdb;
	$result = $wpdb->update(backstage_outreach_party_table('sources'), array('status' => 'inactive', 'updated_by' => $user_id, 'updated_at' => backstage_outreach_party_now()), array('party_id' => $party_id, 'source_id' => $source_id));
	if ($result !== false) {
		backstage_outreach_party_audit('source_unlinked', array('from_party_id' => $party_id, 'source_id' => $source_id, 'directory_only' => true), $user_id);
		return true;
	}
	return false;
}

function backstage_outreach_party_get_affiliations(int $party_id): array
{
	global $wpdb;
	return array_values((array) $wpdb->get_results($wpdb->prepare(
		'SELECT a.*,person.display_name AS person_name,organization.display_name AS organization_name FROM %i a INNER JOIN %i person ON person.id=a.person_party_id INNER JOIN %i organization ON organization.id=a.organization_party_id WHERE a.person_party_id=%d OR a.organization_party_id=%d ORDER BY a.status ASC,organization.display_name ASC,person.display_name ASC',
		backstage_outreach_party_table('affiliations'),
		backstage_outreach_party_table('parties'),
		backstage_outreach_party_table('parties'),
		$party_id,
		$party_id
	), ARRAY_A));
}

function backstage_outreach_party_save_affiliation(int $person_id, int $organization_id, string $role, int $user_id)
{
	global $wpdb;
	$person = backstage_outreach_party_get($person_id);
	$organization = backstage_outreach_party_get($organization_id);
	if (!$person || $person['party_type'] !== 'person' || !$organization || $organization['party_type'] !== 'organization') {
		return new WP_Error('invalid_affiliation_parties', __('Affiliations must connect a Person to an Organization.', 'backstage-outreach'));
	}
	$role = sanitize_text_field($role) ?: 'member';
	$table = backstage_outreach_party_table('affiliations');
	$existing = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE person_party_id=%d AND organization_party_id=%d AND relationship_role=%s', $table, $person_id, $organization_id, $role), ARRAY_A);
	$now = backstage_outreach_party_now();
	if (is_array($existing)) {
		$wpdb->update($table, array('status' => 'active', 'updated_by' => $user_id, 'updated_at' => $now), array('id' => absint($existing['id'])));
		return $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d', $table, absint($existing['id'])), ARRAY_A);
	}
	if (!$wpdb->insert($table, array('person_party_id' => $person_id, 'organization_party_id' => $organization_id, 'relationship_role' => $role, 'status' => 'active', 'provenance_type' => 'manual', 'created_by' => $user_id, 'created_at' => $now))) {
		return new WP_Error('affiliation_save_failed', __('The affiliation could not be saved.', 'backstage-outreach'));
	}
	$affiliation_id = (int) $wpdb->insert_id;
	backstage_outreach_party_audit('affiliation_linked', array('from_party_id' => $person_id, 'to_party_id' => $organization_id, 'relationship_role' => $role), $user_id);
	return $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d', $table, $affiliation_id), ARRAY_A);
}

function backstage_outreach_party_unlink_affiliation(int $affiliation_id, int $user_id): bool
{
	global $wpdb;
	$table = backstage_outreach_party_table('affiliations');
	$row = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d', $table, $affiliation_id), ARRAY_A);
	if (!is_array($row)) {
		return false;
	}
	$result = $wpdb->update($table, array('status' => 'inactive', 'updated_by' => $user_id, 'updated_at' => backstage_outreach_party_now()), array('id' => $affiliation_id));
	if ($result !== false) {
		backstage_outreach_party_audit('affiliation_unlinked', array('from_party_id' => absint($row['person_party_id']), 'to_party_id' => absint($row['organization_party_id']), 'affiliation_id' => $affiliation_id), $user_id);
		return true;
	}
	return false;
}

function backstage_outreach_party_save_campaign_role(int $party_id, int $campaign_id, string $role, int $user_id)
{
	global $wpdb;
	if (backstage_outreach_party_get($party_id) === null) {
		return new WP_Error('party_not_found', __('The selected Party no longer exists.', 'backstage-outreach'));
	}
	$campaign = $wpdb->get_row($wpdb->prepare('SELECT id FROM %i WHERE id=%d', vms_admission_table_pass_outreach_campaigns(), $campaign_id), ARRAY_A);
	if (!is_array($campaign)) {
		return new WP_Error('campaign_not_found', __('The selected campaign no longer exists.', 'backstage-outreach'));
	}
	$role = sanitize_key($role);
	if (!in_array($role, array('contacted', 'referrer', 'participant', 'sponsor', 'partner'), true)) {
		return new WP_Error('invalid_campaign_role', __('Choose a supported Party campaign role.', 'backstage-outreach'));
	}
	$table = backstage_outreach_party_table('campaign_roles');
	$existing = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE party_id=%d AND campaign_id=%d AND role_type=%s', $table, $party_id, $campaign_id, $role), ARRAY_A);
	$now = backstage_outreach_party_now();
	if (is_array($existing)) {
		$wpdb->update($table, array('status' => 'active', 'updated_by' => $user_id, 'updated_at' => $now), array('id' => absint($existing['id'])));
		return $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d', $table, absint($existing['id'])), ARRAY_A);
	}
	if (!$wpdb->insert($table, array('party_id' => $party_id, 'campaign_id' => $campaign_id, 'role_type' => $role, 'status' => 'active', 'provenance_type' => 'manual', 'created_by' => $user_id, 'created_at' => $now))) {
		return new WP_Error('campaign_role_save_failed', __('The campaign role could not be saved.', 'backstage-outreach'));
	}
	$role_id = (int) $wpdb->insert_id;
	backstage_outreach_party_audit('campaign_role_linked', array('to_party_id' => $party_id, 'campaign_id' => $campaign_id, 'role_type' => $role, 'directory_only' => true), $user_id);
	return $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d', $table, $role_id), ARRAY_A);
}

function backstage_outreach_party_get_campaign_roles(int $party_id): array
{
	global $wpdb;
	return array_values((array) $wpdb->get_results($wpdb->prepare(
		'SELECT r.*,c.campaign_name,c.status AS campaign_status FROM %i r LEFT JOIN %i c ON c.id=r.campaign_id WHERE r.party_id=%d ORDER BY r.id DESC',
		backstage_outreach_party_table('campaign_roles'),
		vms_admission_table_pass_outreach_campaigns(),
		$party_id
	), ARRAY_A));
}

function backstage_outreach_party_get_legacy_snapshot(string $legacy_type, int $legacy_id)
{
	global $wpdb;
	$legacy_type = sanitize_key($legacy_type);
	if (!isset(backstage_outreach_party_legacy_types()[$legacy_type]) || $legacy_id <= 0) {
		return new WP_Error('invalid_legacy_reference', __('Choose a supported historical record.', 'backstage-outreach'));
	}
	if ($legacy_type === 'outreach_contact') {
		$row = $wpdb->get_row($wpdb->prepare('SELECT id,contact_name,first_name,last_name,business_name,company_group,email,phone,status,created_at FROM %i WHERE id=%d', vms_outreach_table_contacts(), $legacy_id), ARRAY_A);
		if (!is_array($row)) {
			return new WP_Error('legacy_not_found', __('The legacy Outreach Contact was not found.', 'backstage-outreach'));
		}
		$snapshot = array('legacy_type' => $legacy_type, 'legacy_id' => $legacy_id, 'display_name' => (string) ($row['contact_name'] ?: trim((string) $row['first_name'] . ' ' . (string) $row['last_name'])), 'organization' => (string) ($row['business_name'] ?: $row['company_group']), 'email' => (string) $row['email'], 'phone' => (string) $row['phone'], 'status' => (string) $row['status'], 'campaign_id' => 0, 'source_id' => 0, 'created_at' => (string) $row['created_at']);
	} elseif ($legacy_type === 'business') {
		$row = $wpdb->get_row($wpdb->prepare('SELECT id,business_name,contact_name,email,phone,status,created_at FROM %i WHERE id=%d', backstage_outreach_business_table('businesses'), $legacy_id), ARRAY_A);
		if (!is_array($row)) {
			return new WP_Error('legacy_not_found', __('The reusable-business record was not found.', 'backstage-outreach'));
		}
		$snapshot = array('legacy_type' => $legacy_type, 'legacy_id' => $legacy_id, 'display_name' => (string) $row['business_name'], 'organization' => (string) $row['business_name'], 'contact_name' => (string) $row['contact_name'], 'email' => (string) $row['email'], 'phone' => (string) $row['phone'], 'status' => (string) $row['status'], 'campaign_id' => 0, 'source_id' => 0, 'created_at' => (string) $row['created_at']);
	} else {
		$row = $wpdb->get_row($wpdb->prepare('SELECT r.id,r.campaign_id,r.contact_id,r.first_name,r.last_name,r.full_name,r.email,r.phone,r.company,r.group_label,r.status,r.created_at,c.related_source_id FROM %i r LEFT JOIN %i c ON c.id=r.campaign_id WHERE r.id=%d', vms_admission_table_pass_outreach_recipients(), vms_admission_table_pass_outreach_campaigns(), $legacy_id), ARRAY_A);
		if (!is_array($row)) {
			return new WP_Error('legacy_not_found', __('The historical campaign recipient was not found.', 'backstage-outreach'));
		}
		$snapshot = array('legacy_type' => $legacy_type, 'legacy_id' => $legacy_id, 'display_name' => (string) ($row['full_name'] ?: trim((string) $row['first_name'] . ' ' . (string) $row['last_name'])), 'organization' => (string) ($row['company'] ?: $row['group_label']), 'email' => (string) $row['email'], 'phone' => (string) $row['phone'], 'status' => (string) $row['status'], 'campaign_id' => absint($row['campaign_id']), 'contact_id' => absint($row['contact_id']), 'source_id' => absint($row['related_source_id']), 'created_at' => (string) $row['created_at']);
	}
	$snapshot = array_map(static fn($value) => is_int($value) ? $value : sanitize_text_field((string) $value), $snapshot);
	$snapshot['snapshot_hash'] = hash('sha256', wp_json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
	return $snapshot;
}

function backstage_outreach_party_get_legacy_link(string $legacy_type, int $legacy_id): ?array
{
	global $wpdb;
	$row = $wpdb->get_row($wpdb->prepare('SELECT l.*,p.display_name,p.party_type,p.status AS party_status FROM %i l INNER JOIN %i p ON p.id=l.party_id WHERE l.legacy_type=%s AND l.legacy_id=%d', backstage_outreach_party_table('legacy_links'), backstage_outreach_party_table('parties'), sanitize_key($legacy_type), $legacy_id), ARRAY_A);
	return is_array($row) ? $row : null;
}

function backstage_outreach_party_get_legacy_links(int $party_id): array
{
	global $wpdb;
	return array_values((array) $wpdb->get_results($wpdb->prepare('SELECT * FROM %i WHERE party_id=%d ORDER BY reviewed_at DESC,id DESC', backstage_outreach_party_table('legacy_links'), $party_id), ARRAY_A));
}

function backstage_outreach_party_suggestions(array $snapshot, int $limit = 20): array
{
	global $wpdb;
	$email = backstage_outreach_party_normalize_contact_value('email', (string) ($snapshot['email'] ?? ''));
	$phone = backstage_outreach_party_normalize_contact_value('phone', (string) ($snapshot['phone'] ?? ''));
	$name = strtolower(sanitize_text_field((string) ($snapshot['display_name'] ?? '')));
	$organization = strtolower(sanitize_text_field((string) ($snapshot['organization'] ?? '')));
	$parties = backstage_outreach_party_table('parties');
	$methods = backstage_outreach_party_table('contact_methods');
	$conditions = array();
	$args = array($parties, $methods);
	if ($email !== '') {
		$conditions[] = '(m.method_type=%s AND m.value_norm=%s)';
		$args[] = 'email';
		$args[] = $email;
	}
	if ($phone !== '') {
		$conditions[] = '(m.method_type=%s AND m.value_norm=%s)';
		$args[] = 'phone';
		$args[] = $phone;
	}
	if ($name !== '') {
		$conditions[] = 'LOWER(p.display_name)=%s';
		$args[] = $name;
	}
	if ($organization !== '') {
		$conditions[] = 'LOWER(p.organization_name)=%s';
		$args[] = $organization;
	}
	if (!$conditions) {
		return array();
	}
	$args[] = max(1, min(100, $limit));
	$rows = $wpdb->get_results($wpdb->prepare('SELECT DISTINCT p.* FROM %i p LEFT JOIN %i m ON m.party_id=p.id AND m.status=%s WHERE p.status<>%s AND (' . implode(' OR ', $conditions) . ') ORDER BY p.display_name ASC LIMIT %d', array_merge(array($parties, $methods, 'active', 'archived'), array_slice($args, 2))), ARRAY_A);
	$suggestions = array();
	foreach ((array) $rows as $row) {
		$party = backstage_outreach_party_normalize_row($row);
		$methods_for_party = backstage_outreach_party_get_contact_methods($party['id'], false);
		$reasons = array();
		foreach ($methods_for_party as $method) {
			if ($email !== '' && (string) $method['method_type'] === 'email' && (string) $method['value_norm'] === $email) {
				$reasons[] = __('Exact normalized email match', 'backstage-outreach');
			}
			if ($phone !== '' && (string) $method['method_type'] === 'phone' && (string) $method['value_norm'] === $phone) {
				$reasons[] = __('Exact normalized phone match', 'backstage-outreach');
			}
		}
		if ($name !== '' && strtolower($party['display_name']) === $name) {
			$reasons[] = __('Exact display-name match', 'backstage-outreach');
		}
		if ($organization !== '' && strtolower($party['organization_name']) === $organization) {
			$reasons[] = __('Exact organization-name match', 'backstage-outreach');
		}
		$suggestions[] = array('party' => $party, 'reasons' => array_values(array_unique($reasons)), 'score' => count($reasons));
	}
	usort($suggestions, static fn(array $left, array $right): int => ($right['score'] <=> $left['score']) ?: strcmp($left['party']['display_name'], $right['party']['display_name']));
	$count = count($suggestions);
	foreach ($suggestions as &$suggestion) {
		$suggestion['ambiguous'] = $count > 1;
	}
	unset($suggestion);
	return $suggestions;
}

function backstage_outreach_party_confirm_legacy_link(int $party_id, string $legacy_type, int $legacy_id, string $expected_snapshot_hash, array $reasons, int $user_id, string $request_key = '')
{
	global $wpdb;
	if (backstage_outreach_party_get($party_id) === null) {
		return new WP_Error('party_not_found', __('The selected Party no longer exists.', 'backstage-outreach'));
	}
	$snapshot = backstage_outreach_party_get_legacy_snapshot($legacy_type, $legacy_id);
	if (is_wp_error($snapshot)) {
		return $snapshot;
	}
	if (!hash_equals((string) $snapshot['snapshot_hash'], sanitize_text_field($expected_snapshot_hash))) {
		return new WP_Error('legacy_snapshot_changed', __('The historical record changed after review. Review it again before linking.', 'backstage-outreach'));
	}
	$table = backstage_outreach_party_table('legacy_links');
	$legacy_type = sanitize_key($legacy_type);
	$wpdb->query('START TRANSACTION');
	try {
		$existing = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE legacy_type=%s AND legacy_id=%d FOR UPDATE', $table, $legacy_type, $legacy_id), ARRAY_A);
		if (is_array($existing)) {
			if (absint($existing['party_id']) !== $party_id) {
				throw new RuntimeException('legacy_already_linked');
			}
			$wpdb->query('COMMIT');
			return $existing;
		}
		$payload = array(
			'party_id' => $party_id,
			'legacy_type' => $legacy_type,
			'legacy_id' => $legacy_id,
			'legacy_snapshot_hash' => (string) $snapshot['snapshot_hash'],
			'match_reasons_json' => wp_json_encode(array_values(array_map('sanitize_text_field', $reasons)), JSON_UNESCAPED_UNICODE),
			'provenance_type' => 'reviewed_operator',
			'reviewed_by' => $user_id,
			'reviewed_at' => backstage_outreach_party_now(),
			'created_at' => backstage_outreach_party_now(),
		);
		if (!$wpdb->insert($table, $payload)) {
			throw new RuntimeException('legacy_link_failed');
		}
		$link_id = (int) $wpdb->insert_id;
		if (!backstage_outreach_party_audit('legacy_linked', array('legacy_type' => $legacy_type, 'legacy_id' => $legacy_id, 'to_party_id' => $party_id, 'snapshot_hash' => (string) $snapshot['snapshot_hash'], 'reasons' => $reasons), $user_id, $request_key)) {
			throw new RuntimeException('legacy_audit_failed');
		}
		$wpdb->query('COMMIT');
		return $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d', $table, $link_id), ARRAY_A);
	} catch (Throwable $exception) {
		$wpdb->query('ROLLBACK');
		$concurrent = null;
		for ($attempt = 0; $attempt < 5 && !is_array($concurrent); $attempt++) {
			$concurrent = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE legacy_type=%s AND legacy_id=%d', $table, $legacy_type, $legacy_id), ARRAY_A);
			if (!is_array($concurrent) && $exception->getMessage() === 'legacy_link_failed') {
				usleep(20000);
			}
		}
		if (is_array($concurrent) && absint($concurrent['party_id']) === $party_id) {
			return $concurrent;
		}
		if (is_array($concurrent) && absint($concurrent['party_id']) !== $party_id) {
			return new WP_Error('legacy_already_linked', __('That historical record is already linked to a different Party. Use the explicit correction action.', 'backstage-outreach'));
		}
		if ($exception->getMessage() === 'legacy_already_linked') {
			return new WP_Error('legacy_already_linked', __('That historical record is already linked to a different Party. Use the explicit correction action.', 'backstage-outreach'));
		}
		return new WP_Error('legacy_link_failed', __('The historical association could not be saved.', 'backstage-outreach'));
	}
}

function backstage_outreach_party_reassign_legacy_link(int $link_id, int $to_party_id, int $user_id, string $request_key = '')
{
	global $wpdb;
	if (backstage_outreach_party_get($to_party_id) === null) {
		return new WP_Error('party_not_found', __('The replacement Party no longer exists.', 'backstage-outreach'));
	}
	$table = backstage_outreach_party_table('legacy_links');
	$wpdb->query('START TRANSACTION');
	try {
		$link = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d FOR UPDATE', $table, $link_id), ARRAY_A);
		if (!is_array($link)) {
			throw new RuntimeException('legacy_link_not_found');
		}
		$from_party_id = absint($link['party_id']);
		if ($from_party_id === $to_party_id) {
			$wpdb->query('COMMIT');
			return $link;
		}
		if ($wpdb->update($table, array('party_id' => $to_party_id, 'reviewed_by' => $user_id, 'reviewed_at' => backstage_outreach_party_now(), 'updated_at' => backstage_outreach_party_now()), array('id' => $link_id)) === false) {
			throw new RuntimeException('legacy_link_correction_failed');
		}
		if (!backstage_outreach_party_audit('legacy_corrected', array('legacy_type' => (string) $link['legacy_type'], 'legacy_id' => absint($link['legacy_id']), 'from_party_id' => $from_party_id, 'to_party_id' => $to_party_id), $user_id, $request_key)) {
			throw new RuntimeException('legacy_audit_failed');
		}
		$wpdb->query('COMMIT');
		return $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d', $table, $link_id), ARRAY_A);
	} catch (Throwable $exception) {
		$wpdb->query('ROLLBACK');
		return new WP_Error($exception->getMessage(), __('The historical association could not be corrected.', 'backstage-outreach'));
	}
}

function backstage_outreach_party_unlink_legacy(int $link_id, int $user_id, string $request_key = ''): bool
{
	global $wpdb;
	$table = backstage_outreach_party_table('legacy_links');
	$wpdb->query('START TRANSACTION');
	try {
		$link = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d FOR UPDATE', $table, $link_id), ARRAY_A);
		if (!is_array($link)) {
			throw new RuntimeException('legacy_link_not_found');
		}
		if ($wpdb->delete($table, array('id' => $link_id), array('%d')) !== 1) {
			throw new RuntimeException('legacy_unlink_failed');
		}
		if (!backstage_outreach_party_audit('legacy_unlinked', array('legacy_type' => (string) $link['legacy_type'], 'legacy_id' => absint($link['legacy_id']), 'from_party_id' => absint($link['party_id'])), $user_id, $request_key)) {
			throw new RuntimeException('legacy_audit_failed');
		}
		$wpdb->query('COMMIT');
		return true;
	} catch (Throwable $exception) {
		$wpdb->query('ROLLBACK');
		if ($exception->getMessage() === 'legacy_link_not_found' && preg_match('/^[a-f0-9]{64}$/', $request_key)) {
			return (int) $wpdb->get_var($wpdb->prepare('SELECT id FROM %i WHERE request_key=%s AND action=%s', backstage_outreach_party_table('identity_audit'), $request_key, 'legacy_unlinked')) > 0;
		}
		return false;
	}
}

function backstage_outreach_party_get_directory(array $args = array()): array
{
	global $wpdb;
	$search = sanitize_text_field((string) ($args['search'] ?? ''));
	$type = sanitize_key((string) ($args['party_type'] ?? ''));
	$source_id = absint($args['source_id'] ?? 0);
	$missing_email = sanitize_key((string) ($args['missing_email'] ?? ''));
	$limit = max(1, min(100, absint($args['limit'] ?? 50)));
	$page = max(1, absint($args['page'] ?? 1));
	$offset = ($page - 1) * $limit;
	$parties = backstage_outreach_party_table('parties');
	$methods = backstage_outreach_party_table('contact_methods');
	$sources = backstage_outreach_party_table('sources');
	$affiliations = backstage_outreach_party_table('affiliations');
	$conditions = array('p.status<>%s');
	$condition_params = array('archived');
	if ($type !== '' && isset(backstage_outreach_party_types()[$type])) {
		$conditions[] = 'p.party_type=%s';
		$condition_params[] = $type;
	}
	if ($source_id > 0) {
		$conditions[] = 'EXISTS (SELECT 1 FROM %i psf WHERE psf.party_id=p.id AND psf.source_id=%d AND psf.status=%s)';
		$condition_params[] = $sources;
		$condition_params[] = $source_id;
		$condition_params[] = 'active';
	}
	if ($missing_email === 'yes') {
		$conditions[] = 'NOT EXISTS (SELECT 1 FROM %i me WHERE me.party_id=p.id AND me.method_type=%s AND me.status=%s)';
		$condition_params[] = $methods;
		$condition_params[] = 'email';
		$condition_params[] = 'active';
	} elseif ($missing_email === 'no') {
		$conditions[] = 'EXISTS (SELECT 1 FROM %i me WHERE me.party_id=p.id AND me.method_type=%s AND me.status=%s)';
		$condition_params[] = $methods;
		$condition_params[] = 'email';
		$condition_params[] = 'active';
	}
	if ($search !== '') {
		$like = '%' . $wpdb->esc_like($search) . '%';
		$conditions[] = '(p.display_name LIKE %s OR p.organization_name LIKE %s OR p.identifying_details LIKE %s OR EXISTS (SELECT 1 FROM %i sm WHERE sm.party_id=p.id AND (sm.value LIKE %s OR sm.label LIKE %s)) OR EXISTS (SELECT 1 FROM %i sa INNER JOIN %i so ON so.id=sa.organization_party_id WHERE sa.person_party_id=p.id AND so.display_name LIKE %s))';
		array_push($condition_params, $like, $like, $like, $methods, $like, $like, $affiliations, $parties, $like);
	}
	$sql = 'SELECT p.*,
		GROUP_CONCAT(DISTINCT CASE WHEN m.status=%s THEN CONCAT(m.method_type,\':\',m.value) END ORDER BY m.is_primary DESC,m.id ASC SEPARATOR \'||\') AS contact_summary,
		SUM(CASE WHEN m.method_type=%s AND m.status=%s THEN 1 ELSE 0 END) AS active_email_count,
		COUNT(DISTINCT CASE WHEN ps.status=%s THEN ps.source_id END) AS source_count
		FROM %i p
		LEFT JOIN %i m ON m.party_id=p.id
		LEFT JOIN %i ps ON ps.party_id=p.id
		WHERE ' . implode(' AND ', $conditions) . '
		GROUP BY p.id
		ORDER BY p.display_name ASC,p.id ASC LIMIT %d OFFSET %d';
	$final_params = array_merge(
		array('active', 'email', 'active', 'active', $parties, $methods, $sources),
		$condition_params,
		array($limit, $offset)
	);
	return array_values((array) $wpdb->get_results($wpdb->prepare($sql, $final_params), ARRAY_A));
}

function backstage_outreach_party_possible_duplicate_count(int $party_id): int
{
	global $wpdb;
	return (int) $wpdb->get_var($wpdb->prepare(
		'SELECT COUNT(DISTINCT other.party_id) FROM %i mine INNER JOIN %i other ON other.method_type=mine.method_type AND other.value_norm=mine.value_norm AND other.status=%s WHERE mine.party_id=%d AND mine.status=%s AND other.party_id<>mine.party_id',
		backstage_outreach_party_table('contact_methods'),
		backstage_outreach_party_table('contact_methods'),
		'active',
		$party_id,
		'active'
	));
}

function backstage_outreach_party_email_suppression_state(int $party_id): array
{
	$state = array();
	foreach (backstage_outreach_party_get_contact_methods($party_id, false) as $method) {
		if ((string) ($method['method_type'] ?? '') !== 'email') {
			continue;
		}
		$email = sanitize_email((string) ($method['value'] ?? ''));
		$state[] = array(
			'email' => $email,
			'suppressed' => $email !== '' && function_exists('vms_outreach_email_is_suppressed') ? vms_outreach_email_is_suppressed($email) : false,
		);
	}
	return $state;
}
