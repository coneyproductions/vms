<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_outreach_default_suppression_scope')) {
	function vms_outreach_default_suppression_scope(): string
	{
		return 'global_outreach';
	}
}

if (!function_exists('vms_outreach_suppression_scope_labels')) {
	function vms_outreach_suppression_scope_labels(): array
	{
		return array(
			'global_outreach' => __('Global Outreach', 'backstage-outreach'),
		);
	}
}

if (!function_exists('vms_outreach_suppression_reason_options')) {
	function vms_outreach_suppression_reason_options(): array
	{
		return array(
			'manual_admin' => __('Manual Admin', 'backstage-outreach'),
			'do_not_contact' => __('Do Not Contact', 'backstage-outreach'),
			'unsubscribe_request' => __('Unsubscribe Request', 'backstage-outreach'),
			'bounce' => __('Bounce', 'backstage-outreach'),
			'complaint' => __('Complaint', 'backstage-outreach'),
			'other' => __('Other', 'backstage-outreach'),
		);
	}
}

if (!function_exists('vms_outreach_default_suppression_payload')) {
	function vms_outreach_default_suppression_payload(): array
	{
		return array(
			'id' => 0,
			'email' => '',
			'email_norm' => '',
			'reason' => 'manual_admin',
			'scope' => vms_outreach_default_suppression_scope(),
			'source_contact_id' => 0,
			'source_campaign_id' => 0,
			'source_label' => '',
			'suppressed_at' => '',
			'notes' => '',
			'created_by' => 0,
			'created_at' => '',
			'updated_by' => 0,
			'updated_at' => '',
			'contact_id' => 0,
			'contact_name' => '',
			'business_name' => '',
			'contact_status' => '',
		);
	}
}

if (!function_exists('vms_outreach_normalize_suppression_row')) {
	function vms_outreach_normalize_suppression_row(array $row): array
	{
		$row = array_merge(vms_outreach_default_suppression_payload(), $row);
		$row['id'] = absint($row['id'] ?? 0);
		$row['email'] = sanitize_email((string) ($row['email'] ?? ''));
		$row['email_norm'] = sanitize_text_field((string) ($row['email_norm'] ?? ''));
		$row['reason'] = sanitize_key((string) ($row['reason'] ?? 'manual_admin'));
		$row['scope'] = sanitize_key((string) ($row['scope'] ?? vms_outreach_default_suppression_scope()));
		$row['source_contact_id'] = absint($row['source_contact_id'] ?? 0);
		$row['source_campaign_id'] = absint($row['source_campaign_id'] ?? 0);
		$row['source_label'] = sanitize_text_field((string) ($row['source_label'] ?? ''));
		$row['suppressed_at'] = sanitize_text_field((string) ($row['suppressed_at'] ?? ''));
		$row['notes'] = sanitize_textarea_field((string) ($row['notes'] ?? ''));
		$row['created_by'] = absint($row['created_by'] ?? 0);
		$row['created_at'] = sanitize_text_field((string) ($row['created_at'] ?? ''));
		$row['updated_by'] = absint($row['updated_by'] ?? 0);
		$row['updated_at'] = sanitize_text_field((string) ($row['updated_at'] ?? ''));
		$row['contact_id'] = absint($row['contact_id'] ?? 0);
		$row['contact_name'] = sanitize_text_field((string) ($row['contact_name'] ?? ''));
		$row['business_name'] = sanitize_text_field((string) ($row['business_name'] ?? ''));
		$row['contact_status'] = sanitize_key((string) ($row['contact_status'] ?? ''));
		return $row;
	}
}

if (!function_exists('vms_outreach_suppression_db_formats')) {
	function vms_outreach_suppression_db_formats(array $data): array
	{
		$map = array(
			'email' => '%s',
			'email_norm' => '%s',
			'reason' => '%s',
			'scope' => '%s',
			'source_contact_id' => '%d',
			'source_campaign_id' => '%d',
			'source_label' => '%s',
			'suppressed_at' => '%s',
			'notes' => '%s',
			'created_by' => '%d',
			'created_at' => '%s',
			'updated_by' => '%d',
			'updated_at' => '%s',
		);

		$formats = array();
		foreach (array_keys($data) as $key) {
			$formats[] = $map[$key] ?? '%s';
		}
		return $formats;
	}
}

if (!function_exists('vms_outreach_sanitize_suppression_payload')) {
	function vms_outreach_sanitize_suppression_payload(array $raw)
	{
		$email = sanitize_email((string) ($raw['email'] ?? ''));
		$email_norm = vms_outreach_normalize_email($email);
		if ($email_norm === '') {
			return new WP_Error('invalid_email', __('Enter a valid email address to suppress.', 'backstage-outreach'));
		}

		$scope = sanitize_key((string) ($raw['scope'] ?? vms_outreach_default_suppression_scope()));
		$scope_labels = vms_outreach_suppression_scope_labels();
		if (!isset($scope_labels[$scope])) {
			$scope = vms_outreach_default_suppression_scope();
		}

		$reason = sanitize_key((string) ($raw['reason'] ?? 'manual_admin'));
		$reason_options = vms_outreach_suppression_reason_options();
		if (!isset($reason_options[$reason])) {
			$reason = 'other';
		}

		return array(
			'email' => $email,
			'email_norm' => $email_norm,
			'reason' => $reason,
			'scope' => $scope,
			'source_contact_id' => absint($raw['source_contact_id'] ?? 0),
			'source_campaign_id' => absint($raw['source_campaign_id'] ?? 0),
			'source_label' => sanitize_text_field((string) ($raw['source_label'] ?? '')),
			'notes' => sanitize_textarea_field((string) ($raw['notes'] ?? '')),
		);
	}
}

if (!function_exists('vms_outreach_get_suppression_by_id')) {
	function vms_outreach_get_suppression_by_id(int $suppression_id): ?array
	{
		if ($suppression_id <= 0) {
			return null;
		}

		global $wpdb;
		$table = vms_outreach_table_suppressions();
		$contacts = vms_outreach_table_contacts();
		$row = $wpdb->get_row($wpdb->prepare(
			"SELECT s.*, c.id AS contact_id, c.contact_name, c.business_name, c.status AS contact_status
			FROM {$table} s
			LEFT JOIN {$contacts} c ON c.email_norm = s.email_norm
			WHERE s.id = %d
			LIMIT 1",
			$suppression_id
		), ARRAY_A);

		return is_array($row) ? vms_outreach_normalize_suppression_row($row) : null;
	}
}

if (!function_exists('vms_outreach_get_suppression_by_email')) {
	function vms_outreach_get_suppression_by_email(string $email, string $scope = '')
	{
		$email_norm = vms_outreach_normalize_email($email);
		if ($email_norm === '') {
			return null;
		}
		$scope = sanitize_key($scope !== '' ? $scope : vms_outreach_default_suppression_scope());

		global $wpdb;
		$table = vms_outreach_table_suppressions();
		$row = $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM {$table} WHERE scope = %s AND email_norm = %s LIMIT 1",
			$scope,
			$email_norm
		), ARRAY_A);

		return is_array($row) ? vms_outreach_normalize_suppression_row($row) : null;
	}
}

if (!function_exists('vms_outreach_get_suppressions_by_email_norms')) {
	function vms_outreach_get_suppressions_by_email_norms(array $email_norms, string $scope = ''): array
	{
		$email_norms = array_values(array_filter(array_map('sanitize_text_field', $email_norms)));
		if (empty($email_norms)) {
			return array();
		}

		$scope = sanitize_key($scope !== '' ? $scope : vms_outreach_default_suppression_scope());
		global $wpdb;
		$table = vms_outreach_table_suppressions();
		$placeholders = implode(', ', array_fill(0, count($email_norms), '%s'));
		$params = array_merge(array($scope), $email_norms);
		$sql = $wpdb->prepare(
			"SELECT * FROM {$table} WHERE scope = %s AND email_norm IN ({$placeholders})",
			$params
		);
		$rows = $wpdb->get_results($sql, ARRAY_A);
		$found = array();
		foreach ((array) $rows as $row) {
			$normalized = vms_outreach_normalize_suppression_row((array) $row);
			$found[(string) $normalized['email_norm']] = $normalized;
		}
		return $found;
	}
}

if (!function_exists('vms_outreach_email_is_suppressed')) {
	function vms_outreach_email_is_suppressed(string $email, string $scope = ''): bool
	{
		return is_array(vms_outreach_get_suppression_by_email($email, $scope));
	}
}

if (!function_exists('vms_outreach_upsert_suppression')) {
	function vms_outreach_upsert_suppression(array $raw, int $user_id = 0, int $suppression_id = 0)
	{
		$payload = vms_outreach_sanitize_suppression_payload($raw);
		if (is_wp_error($payload)) {
			return $payload;
		}

		global $wpdb;
		$table = vms_outreach_table_suppressions();
		$now = vms_outreach_now_mysql();
		$existing = $suppression_id > 0
			? vms_outreach_get_suppression_by_id($suppression_id)
			: vms_outreach_get_suppression_by_email((string) $payload['email'], (string) $payload['scope']);

		if (is_array($existing)) {
			$update = array(
				'email' => (string) $payload['email'],
				'email_norm' => (string) $payload['email_norm'],
				'reason' => (string) $payload['reason'],
				'scope' => (string) $payload['scope'],
				'source_contact_id' => (int) ($payload['source_contact_id'] ?? 0),
				'source_campaign_id' => (int) ($payload['source_campaign_id'] ?? 0),
				'source_label' => (string) ($payload['source_label'] ?? ''),
				'suppressed_at' => $now,
				'notes' => (string) ($payload['notes'] ?? ''),
				'updated_by' => $user_id,
				'updated_at' => $now,
			);
			$result = $wpdb->update(
				$table,
				$update,
				array('id' => (int) $existing['id']),
				vms_outreach_suppression_db_formats($update),
				array('%d')
			);
			if ($result === false) {
				return new WP_Error('suppression_update_failed', __('Could not update the suppression record.', 'backstage-outreach'));
			}
			return vms_outreach_get_suppression_by_id((int) $existing['id']);
		}

		$insert = array(
			'email' => (string) $payload['email'],
			'email_norm' => (string) $payload['email_norm'],
			'reason' => (string) $payload['reason'],
			'scope' => (string) $payload['scope'],
			'source_contact_id' => (int) ($payload['source_contact_id'] ?? 0),
			'source_campaign_id' => (int) ($payload['source_campaign_id'] ?? 0),
			'source_label' => (string) ($payload['source_label'] ?? ''),
			'suppressed_at' => $now,
			'notes' => (string) ($payload['notes'] ?? ''),
			'created_by' => $user_id,
			'created_at' => $now,
		);
		$result = $wpdb->insert($table, $insert, vms_outreach_suppression_db_formats($insert));
		if ($result === false) {
			return new WP_Error('suppression_insert_failed', __('Could not save the suppression record.', 'backstage-outreach'));
		}
		return vms_outreach_get_suppression_by_id((int) $wpdb->insert_id);
	}
}

if (!function_exists('vms_outreach_remove_suppression')) {
	function vms_outreach_remove_suppression(int $suppression_id): bool
	{
		if ($suppression_id <= 0) {
			return false;
		}

		global $wpdb;
		$table = vms_outreach_table_suppressions();
		$result = $wpdb->delete($table, array('id' => $suppression_id), array('%d'));
		return $result !== false;
	}
}

if (!function_exists('vms_outreach_get_suppressions')) {
	function vms_outreach_get_suppressions(array $args = array()): array
	{
		global $wpdb;
		$table = vms_outreach_table_suppressions();
		$contacts = vms_outreach_table_contacts();
		$scope = sanitize_key((string) ($args['scope'] ?? vms_outreach_default_suppression_scope()));
		$search = sanitize_text_field((string) ($args['search'] ?? ''));
		$limit = max(1, min(500, absint($args['limit'] ?? 250)));

		$where = array('s.scope = %s');
		$params = array($scope);
		if ($search !== '') {
			$like = '%' . $wpdb->esc_like($search) . '%';
			$where[] = '(s.email LIKE %s OR s.reason LIKE %s OR s.source_label LIKE %s OR c.contact_name LIKE %s OR c.business_name LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}
		$params[] = $limit;

		$sql = $wpdb->prepare(
			"SELECT s.*, c.id AS contact_id, c.contact_name, c.business_name, c.status AS contact_status
			FROM {$table} s
			LEFT JOIN {$contacts} c ON c.email_norm = s.email_norm
			WHERE " . implode(' AND ', $where) . "
			ORDER BY s.suppressed_at DESC, s.id DESC
			LIMIT %d",
			$params
		);

		$rows = $wpdb->get_results($sql, ARRAY_A);
		return array_map('vms_outreach_normalize_suppression_row', (array) $rows);
	}
}
