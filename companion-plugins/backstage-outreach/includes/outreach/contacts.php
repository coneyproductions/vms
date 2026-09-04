<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_outreach_contact_type_options')) {
	function vms_outreach_contact_type_options(): array
	{
		return array(
			'realtor' => __('Realtor', 'backstage-outreach'),
			'photographer' => __('Photographer', 'backstage-outreach'),
			'vendor_prospect' => __('Vendor Prospect', 'backstage-outreach'),
			'sponsor_prospect' => __('Sponsor Prospect', 'backstage-outreach'),
			'referral_partner_prospect' => __('Referral Partner Prospect', 'backstage-outreach'),
			'salon_stylist' => __('Salon / Stylist', 'backstage-outreach'),
			'title_company' => __('Title Company', 'backstage-outreach'),
			'local_business' => __('Local Business', 'backstage-outreach'),
			'other' => __('Other', 'backstage-outreach'),
		);
	}
}

if (!function_exists('vms_outreach_contact_status_options')) {
	function vms_outreach_contact_status_options(): array
	{
		return array(
			'new' => __('New', 'backstage-outreach'),
			'needs_review' => __('Needs Review', 'backstage-outreach'),
			'approved' => __('Approved', 'backstage-outreach'),
			'maybe' => __('Maybe', 'backstage-outreach'),
			'excluded' => __('Excluded', 'backstage-outreach'),
			'queued' => __('Queued', 'backstage-outreach'),
			'contacted' => __('Contacted', 'backstage-outreach'),
			'interested' => __('Interested', 'backstage-outreach'),
			'applied' => __('Applied', 'backstage-outreach'),
			'do_not_contact' => __('Do Not Contact', 'backstage-outreach'),
		);
	}
}

if (!function_exists('vms_outreach_default_contact_payload')) {
	function vms_outreach_default_contact_payload(): array
	{
		return array(
			'id' => 0,
			'business_name' => '',
			'contact_name' => '',
			'first_name' => '',
			'last_name' => '',
			'email' => '',
			'email_norm' => '',
			'phone' => '',
			'phone_norm' => '',
			'website' => '',
			'facebook_url' => '',
			'instagram_url' => '',
			'city' => '',
			'state' => '',
			'company_group' => '',
			'contact_type' => 'other',
			'tags' => '',
			'source' => '',
			'status' => 'new',
			'notes' => '',
			'created_by' => 0,
			'created_at' => '',
			'updated_by' => 0,
			'updated_at' => '',
			'suppression_id' => 0,
			'suppression_reason' => '',
			'suppression_scope' => '',
			'suppressed_at' => '',
		);
	}
}

if (!function_exists('vms_outreach_resolve_catalog_key')) {
	function vms_outreach_resolve_catalog_key(string $raw, array $options, string $default): string
	{
		$raw = trim($raw);
		if ($raw === '') {
			return $default;
		}

		$comparison = preg_replace('/[^a-z0-9]+/', '', strtolower($raw));
		$fallback = sanitize_key($raw);
		foreach ($options as $key => $label) {
			$key_compare = preg_replace('/[^a-z0-9]+/', '', strtolower((string) $key));
			$label_compare = preg_replace('/[^a-z0-9]+/', '', strtolower((string) $label));
			if ($comparison === $key_compare || $comparison === $label_compare || $fallback === (string) $key) {
				return (string) $key;
			}
		}

		return $default;
	}
}

if (!function_exists('vms_outreach_normalize_contact_row')) {
	function vms_outreach_normalize_contact_row(array $row): array
	{
		$row = array_merge(vms_outreach_default_contact_payload(), $row);
		$row['id'] = absint($row['id'] ?? 0);
		$row['business_name'] = sanitize_text_field((string) ($row['business_name'] ?? ''));
		$row['contact_name'] = sanitize_text_field((string) ($row['contact_name'] ?? ''));
		$row['first_name'] = sanitize_text_field((string) ($row['first_name'] ?? ''));
		$row['last_name'] = sanitize_text_field((string) ($row['last_name'] ?? ''));
		$row['email'] = sanitize_email((string) ($row['email'] ?? ''));
		$row['email_norm'] = sanitize_text_field((string) ($row['email_norm'] ?? ''));
		$row['phone'] = sanitize_text_field((string) ($row['phone'] ?? ''));
		$row['phone_norm'] = sanitize_text_field((string) ($row['phone_norm'] ?? ''));
		$row['website'] = esc_url_raw((string) ($row['website'] ?? ''));
		$row['facebook_url'] = esc_url_raw((string) ($row['facebook_url'] ?? ''));
		$row['instagram_url'] = esc_url_raw((string) ($row['instagram_url'] ?? ''));
		$row['city'] = sanitize_text_field((string) ($row['city'] ?? ''));
		$row['state'] = sanitize_text_field((string) ($row['state'] ?? ''));
		$row['company_group'] = sanitize_text_field((string) ($row['company_group'] ?? ''));
		$row['contact_type'] = sanitize_key((string) ($row['contact_type'] ?? 'other'));
		$row['tags'] = sanitize_text_field((string) ($row['tags'] ?? ''));
		$row['source'] = sanitize_text_field((string) ($row['source'] ?? ''));
		$row['status'] = sanitize_key((string) ($row['status'] ?? 'new'));
		$row['notes'] = sanitize_textarea_field((string) ($row['notes'] ?? ''));
		$row['created_by'] = absint($row['created_by'] ?? 0);
		$row['created_at'] = sanitize_text_field((string) ($row['created_at'] ?? ''));
		$row['updated_by'] = absint($row['updated_by'] ?? 0);
		$row['updated_at'] = sanitize_text_field((string) ($row['updated_at'] ?? ''));
		$row['suppression_id'] = absint($row['suppression_id'] ?? 0);
		$row['suppression_reason'] = sanitize_key((string) ($row['suppression_reason'] ?? ''));
		$row['suppression_scope'] = sanitize_key((string) ($row['suppression_scope'] ?? ''));
		$row['suppressed_at'] = sanitize_text_field((string) ($row['suppressed_at'] ?? ''));
		return $row;
	}
}

if (!function_exists('vms_outreach_contact_display_name')) {
	function vms_outreach_contact_display_name(array $contact): string
	{
		$contact = vms_outreach_normalize_contact_row($contact);
		if ($contact['contact_name'] !== '') {
			return $contact['contact_name'];
		}
		if ($contact['business_name'] !== '') {
			return $contact['business_name'];
		}
		if ($contact['email'] !== '') {
			return $contact['email'];
		}
		return __('Unnamed Contact', 'backstage-outreach');
	}
}

if (!function_exists('vms_outreach_contact_location_label')) {
	function vms_outreach_contact_location_label(array $contact): string
	{
		$city = sanitize_text_field((string) ($contact['city'] ?? ''));
		$state = sanitize_text_field((string) ($contact['state'] ?? ''));
		if ($city !== '' && $state !== '') {
			return $city . ', ' . $state;
		}
		return $city !== '' ? $city : $state;
	}
}

if (!function_exists('vms_outreach_contact_db_formats')) {
	function vms_outreach_contact_db_formats(array $data): array
	{
		$map = array(
			'business_name' => '%s',
			'contact_name' => '%s',
			'first_name' => '%s',
			'last_name' => '%s',
			'email' => '%s',
			'email_norm' => '%s',
			'phone' => '%s',
			'phone_norm' => '%s',
			'website' => '%s',
			'facebook_url' => '%s',
			'instagram_url' => '%s',
			'city' => '%s',
			'state' => '%s',
			'company_group' => '%s',
			'contact_type' => '%s',
			'tags' => '%s',
			'source' => '%s',
			'status' => '%s',
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

if (!function_exists('vms_outreach_sanitize_contact_payload')) {
	function vms_outreach_sanitize_contact_payload(array $raw)
	{
		$contact_name = sanitize_text_field((string) ($raw['contact_name'] ?? ''));
		$first_name = sanitize_text_field((string) ($raw['first_name'] ?? ''));
		$last_name = sanitize_text_field((string) ($raw['last_name'] ?? ''));
		if ($contact_name === '') {
			$contact_name = vms_outreach_compose_contact_name($first_name, $last_name);
		}
		if ($contact_name !== '' && ($first_name === '' || $last_name === '')) {
			list($derived_first, $derived_last) = vms_outreach_split_contact_name($contact_name);
			if ($first_name === '') {
				$first_name = $derived_first;
			}
			if ($last_name === '') {
				$last_name = $derived_last;
			}
		}

		$email = sanitize_email((string) ($raw['email'] ?? ''));
		$email_norm = vms_outreach_normalize_email($email);
		if ($email_norm === '') {
			return new WP_Error('invalid_email', __('A valid email address is required.', 'backstage-outreach'));
		}

		$contact_type = vms_outreach_resolve_catalog_key((string) ($raw['contact_type'] ?? ''), vms_outreach_contact_type_options(), 'other');
		$status = vms_outreach_resolve_catalog_key((string) ($raw['status'] ?? ''), vms_outreach_contact_status_options(), 'new');

		return array(
			'business_name' => sanitize_text_field((string) ($raw['business_name'] ?? '')),
			'contact_name' => $contact_name,
			'first_name' => $first_name,
			'last_name' => $last_name,
			'email' => $email,
			'email_norm' => $email_norm,
			'phone' => sanitize_text_field((string) ($raw['phone'] ?? '')),
			'phone_norm' => vms_outreach_normalize_phone((string) ($raw['phone'] ?? '')),
			'website' => vms_outreach_normalize_url_field((string) ($raw['website'] ?? '')),
			'facebook_url' => vms_outreach_normalize_url_field((string) ($raw['facebook_url'] ?? '')),
			'instagram_url' => vms_outreach_normalize_url_field((string) ($raw['instagram_url'] ?? '')),
			'city' => sanitize_text_field((string) ($raw['city'] ?? '')),
			'state' => sanitize_text_field((string) ($raw['state'] ?? '')),
			'company_group' => sanitize_text_field((string) ($raw['company_group'] ?? '')),
			'contact_type' => $contact_type,
			'tags' => vms_outreach_normalize_tags((string) ($raw['tags'] ?? '')),
			'source' => sanitize_text_field((string) ($raw['source'] ?? '')),
			'status' => $status,
			'notes' => sanitize_textarea_field((string) ($raw['notes'] ?? '')),
		);
	}
}

if (!function_exists('vms_outreach_merge_notes')) {
	function vms_outreach_merge_notes(string $existing_notes, string $incoming_notes): string
	{
		$existing_notes = trim($existing_notes);
		$incoming_notes = trim($incoming_notes);
		if ($incoming_notes === '') {
			return $existing_notes;
		}
		if ($existing_notes === '') {
			return $incoming_notes;
		}
		if (strpos($existing_notes, $incoming_notes) !== false) {
			return $existing_notes;
		}
		return $existing_notes . "\n\n" . $incoming_notes;
	}
}

if (!function_exists('vms_outreach_merge_contact_payload')) {
	function vms_outreach_merge_contact_payload(array $base, array $overlay, bool $overwrite_non_empty = false): array
	{
		$merged = $base;
		$fields = array(
			'business_name',
			'contact_name',
			'first_name',
			'last_name',
			'phone',
			'phone_norm',
			'website',
			'facebook_url',
			'instagram_url',
			'city',
			'state',
			'company_group',
			'contact_type',
			'source',
			'status',
		);

		foreach ($fields as $field) {
			$current = $merged[$field] ?? '';
			$incoming = $overlay[$field] ?? '';
			if (!vms_outreach_value_present($incoming)) {
				continue;
			}

			if ($overwrite_non_empty || !vms_outreach_value_present($current)) {
				$merged[$field] = $incoming;
			}
		}

		$merged['tags'] = vms_outreach_normalize_tags(array_filter(array(
			(string) ($base['tags'] ?? ''),
			(string) ($overlay['tags'] ?? ''),
		)));
		$merged['notes'] = vms_outreach_merge_notes((string) ($base['notes'] ?? ''), (string) ($overlay['notes'] ?? ''));
		$merged['email'] = sanitize_email((string) ($overlay['email'] ?? ($base['email'] ?? '')));
		$merged['email_norm'] = sanitize_text_field((string) ($overlay['email_norm'] ?? ($base['email_norm'] ?? '')));

		return $merged;
	}
}

if (!function_exists('vms_outreach_build_contact_query')) {
	function vms_outreach_build_contact_query(array $args): array
	{
		global $wpdb;
		$contacts = vms_outreach_table_contacts();
		$suppressions = vms_outreach_table_suppressions();

		$search = sanitize_text_field((string) ($args['search'] ?? ''));
		$status = sanitize_key((string) ($args['status'] ?? ''));
		$statuses = array_values(array_filter(array_map('sanitize_key', (array) ($args['statuses'] ?? array()))));
		$contact_type = sanitize_key((string) ($args['contact_type'] ?? ''));
		$suppressed = sanitize_key((string) ($args['suppressed'] ?? ''));
		$city = sanitize_text_field((string) ($args['city'] ?? ''));
		$source = sanitize_text_field((string) ($args['source'] ?? ''));
		$tag = sanitize_text_field((string) ($args['tag'] ?? ''));
		$limit = max(1, min(5000, absint($args['limit'] ?? 250)));

		$where = array('1=1');
		$params = array(vms_outreach_default_suppression_scope());

		if ($search !== '') {
			$like = '%' . $wpdb->esc_like($search) . '%';
			$where[] = '(c.business_name LIKE %s OR c.contact_name LIKE %s OR c.email LIKE %s OR c.phone LIKE %s OR c.city LIKE %s OR c.state LIKE %s OR c.company_group LIKE %s OR c.source LIKE %s OR c.tags LIKE %s)';
			for ($i = 0; $i < 9; $i += 1) {
				$params[] = $like;
			}
		}

		$valid_statuses = array_values(array_filter($statuses, static function (string $candidate): bool {
			return isset(vms_outreach_contact_status_options()[$candidate]);
		}));
		if (!empty($valid_statuses)) {
			$placeholders = implode(', ', array_fill(0, count($valid_statuses), '%s'));
			$where[] = "c.status IN ({$placeholders})";
			$params = array_merge($params, $valid_statuses);
		} elseif ($status !== '' && isset(vms_outreach_contact_status_options()[$status])) {
			$where[] = 'c.status = %s';
			$params[] = $status;
		}

		if ($contact_type !== '' && isset(vms_outreach_contact_type_options()[$contact_type])) {
			$where[] = 'c.contact_type = %s';
			$params[] = $contact_type;
		}

		if ($city !== '') {
			$where[] = 'c.city LIKE %s';
			$params[] = '%' . $wpdb->esc_like($city) . '%';
		}

		if ($source !== '') {
			$where[] = 'c.source LIKE %s';
			$params[] = '%' . $wpdb->esc_like($source) . '%';
		}

		if ($tag !== '') {
			$where[] = 'c.tags LIKE %s';
			$params[] = '%' . $wpdb->esc_like($tag) . '%';
		}

		if ($suppressed === 'yes') {
			$where[] = 's.id IS NOT NULL';
		} elseif ($suppressed === 'no') {
			$where[] = 's.id IS NULL';
		}

		$params[] = $limit;

		$sql = $wpdb->prepare(
			"SELECT c.*, s.id AS suppression_id, s.reason AS suppression_reason, s.scope AS suppression_scope, s.suppressed_at
			FROM {$contacts} c
			LEFT JOIN {$suppressions} s
				ON s.scope = %s
				AND s.email_norm = c.email_norm
			WHERE " . implode(' AND ', $where) . "
			ORDER BY COALESCE(c.updated_at, c.created_at) DESC, c.id DESC
			LIMIT %d",
			$params
		);

		return array($sql, $contacts);
	}
}

if (!function_exists('vms_outreach_get_contacts')) {
	function vms_outreach_get_contacts(array $args = array()): array
	{
		global $wpdb;
		list($sql) = vms_outreach_build_contact_query($args);
		$rows = $wpdb->get_results($sql, ARRAY_A);
		return array_map('vms_outreach_normalize_contact_row', (array) $rows);
	}
}

if (!function_exists('vms_outreach_get_contact_by_id')) {
	function vms_outreach_get_contact_by_id(int $contact_id): ?array
	{
		if ($contact_id <= 0) {
			return null;
		}

		global $wpdb;
		$contacts = vms_outreach_table_contacts();
		$suppressions = vms_outreach_table_suppressions();
		$row = $wpdb->get_row($wpdb->prepare(
			"SELECT c.*, s.id AS suppression_id, s.reason AS suppression_reason, s.scope AS suppression_scope, s.suppressed_at
			FROM {$contacts} c
			LEFT JOIN {$suppressions} s
				ON s.scope = %s
				AND s.email_norm = c.email_norm
			WHERE c.id = %d
			LIMIT 1",
			vms_outreach_default_suppression_scope(),
			$contact_id
		), ARRAY_A);

		return is_array($row) ? vms_outreach_normalize_contact_row($row) : null;
	}
}

if (!function_exists('vms_outreach_get_contact_by_email')) {
	function vms_outreach_get_contact_by_email(string $email): ?array
	{
		$email_norm = vms_outreach_normalize_email($email);
		if ($email_norm === '') {
			return null;
		}

		global $wpdb;
		$contacts = vms_outreach_table_contacts();
		$suppressions = vms_outreach_table_suppressions();
		$row = $wpdb->get_row($wpdb->prepare(
			"SELECT c.*, s.id AS suppression_id, s.reason AS suppression_reason, s.scope AS suppression_scope, s.suppressed_at
			FROM {$contacts} c
			LEFT JOIN {$suppressions} s
				ON s.scope = %s
				AND s.email_norm = c.email_norm
			WHERE c.email_norm = %s
			LIMIT 1",
			vms_outreach_default_suppression_scope(),
			$email_norm
		), ARRAY_A);

		return is_array($row) ? vms_outreach_normalize_contact_row($row) : null;
	}
}

if (!function_exists('vms_outreach_get_contacts_by_email_norms')) {
	function vms_outreach_get_contacts_by_email_norms(array $email_norms): array
	{
		$email_norms = array_values(array_filter(array_map('sanitize_text_field', $email_norms)));
		if (empty($email_norms)) {
			return array();
		}

		global $wpdb;
		$contacts = vms_outreach_table_contacts();
		$placeholders = implode(', ', array_fill(0, count($email_norms), '%s'));
		$sql = $wpdb->prepare(
			"SELECT * FROM {$contacts} WHERE email_norm IN ({$placeholders})",
			$email_norms
		);
		$rows = $wpdb->get_results($sql, ARRAY_A);
		$found = array();
		foreach ((array) $rows as $row) {
			$normalized = vms_outreach_normalize_contact_row((array) $row);
			$found[(string) $normalized['email_norm']] = $normalized;
		}
		return $found;
	}
}

if (!function_exists('vms_outreach_save_contact')) {
	function vms_outreach_save_contact(array $payload, int $user_id = 0, int $contact_id = 0)
	{
		$data = vms_outreach_sanitize_contact_payload($payload);
		if (is_wp_error($data)) {
			return $data;
		}

		global $wpdb;
		$table = vms_outreach_table_contacts();
		$existing = $contact_id > 0 ? vms_outreach_get_contact_by_id($contact_id) : vms_outreach_get_contact_by_email((string) $data['email']);
		$now = vms_outreach_now_mysql();

		if (is_array($existing)) {
			$update = $data;
			$update['updated_by'] = $user_id;
			$update['updated_at'] = $now;
			$result = $wpdb->update(
				$table,
				$update,
				array('id' => (int) $existing['id']),
				vms_outreach_contact_db_formats($update),
				array('%d')
			);
			if ($result === false) {
				return new WP_Error('contact_update_failed', __('Could not update the contact.', 'backstage-outreach'));
			}
			$contact = vms_outreach_get_contact_by_id((int) $existing['id']);
		} else {
			$insert = $data;
			$insert['created_by'] = $user_id;
			$insert['created_at'] = $now;
			$result = $wpdb->insert($table, $insert, vms_outreach_contact_db_formats($insert));
			if ($result === false) {
				return new WP_Error('contact_insert_failed', __('Could not save the contact.', 'backstage-outreach'));
			}
			$contact = vms_outreach_get_contact_by_id((int) $wpdb->insert_id);
		}

		if (!is_array($contact)) {
			return new WP_Error('contact_load_failed', __('The contact was saved, but could not be reloaded.', 'backstage-outreach'));
		}

		if ((string) ($contact['status'] ?? '') === 'do_not_contact') {
			$suppression = vms_outreach_upsert_suppression(array(
				'email' => (string) ($contact['email'] ?? ''),
				'reason' => 'do_not_contact',
				'scope' => vms_outreach_default_suppression_scope(),
				'source_contact_id' => (int) ($contact['id'] ?? 0),
				'source_label' => sprintf(
					/* translators: %s: contact display name */
					__('Status set to Do Not Contact from %s', 'backstage-outreach'),
					vms_outreach_contact_display_name($contact)
				),
				'notes' => __('Auto-created from contact status.', 'backstage-outreach'),
			), $user_id);
			if (is_wp_error($suppression)) {
				return $suppression;
			}
			$contact = vms_outreach_get_contact_by_id((int) ($contact['id'] ?? 0));
		}

		return $contact;
	}
}

if (!function_exists('vms_outreach_delete_contact')) {
	function vms_outreach_delete_contact(int $contact_id): bool
	{
		if ($contact_id <= 0) {
			return false;
		}
		global $wpdb;
		$table = vms_outreach_table_contacts();
		$result = $wpdb->delete($table, array('id' => $contact_id), array('%d'));
		return $result !== false;
	}
}

if (!function_exists('vms_outreach_contact_import_row_limit')) {
	function vms_outreach_contact_import_row_limit(): int
	{
		return 1500;
	}
}

if (!function_exists('vms_outreach_contact_import_max_file_bytes')) {
	function vms_outreach_contact_import_max_file_bytes(): int
	{
		return defined('MB_IN_BYTES') ? (2 * MB_IN_BYTES) : 2097152;
	}
}

if (!function_exists('vms_outreach_contact_import_mapping_key')) {
	function vms_outreach_contact_import_mapping_key(int $user_id): string
	{
		return 'vms_outreach_contact_import_mapping_' . max(0, $user_id);
	}
}

if (!function_exists('vms_outreach_set_contact_import_mapping')) {
	function vms_outreach_set_contact_import_mapping(int $user_id, array $payload): void
	{
		if ($user_id <= 0) {
			return;
		}
		set_transient(vms_outreach_contact_import_mapping_key($user_id), $payload, 30 * MINUTE_IN_SECONDS);
	}
}

if (!function_exists('vms_outreach_get_contact_import_mapping')) {
	function vms_outreach_get_contact_import_mapping(int $user_id): array
	{
		if ($user_id <= 0) {
			return array();
		}
		$data = get_transient(vms_outreach_contact_import_mapping_key($user_id));
		return is_array($data) ? $data : array();
	}
}

if (!function_exists('vms_outreach_clear_contact_import_mapping')) {
	function vms_outreach_clear_contact_import_mapping(int $user_id): void
	{
		if ($user_id <= 0) {
			return;
		}
		delete_transient(vms_outreach_contact_import_mapping_key($user_id));
	}
}

if (!function_exists('vms_outreach_contact_import_preview_key')) {
	function vms_outreach_contact_import_preview_key(int $user_id): string
	{
		return 'vms_outreach_contact_import_preview_' . max(0, $user_id);
	}
}

if (!function_exists('vms_outreach_set_contact_import_preview')) {
	function vms_outreach_set_contact_import_preview(int $user_id, array $payload): void
	{
		if ($user_id <= 0) {
			return;
		}
		set_transient(vms_outreach_contact_import_preview_key($user_id), $payload, 15 * MINUTE_IN_SECONDS);
	}
}

if (!function_exists('vms_outreach_get_contact_import_preview')) {
	function vms_outreach_get_contact_import_preview(int $user_id): array
	{
		if ($user_id <= 0) {
			return array();
		}
		$data = get_transient(vms_outreach_contact_import_preview_key($user_id));
		return is_array($data) ? $data : array();
	}
}

if (!function_exists('vms_outreach_clear_contact_import_preview')) {
	function vms_outreach_clear_contact_import_preview(int $user_id): void
	{
		if ($user_id <= 0) {
			return;
		}
		delete_transient(vms_outreach_contact_import_preview_key($user_id));
	}
}

if (!function_exists('vms_outreach_contact_csv_header_aliases')) {
	function vms_outreach_contact_csv_header_aliases(): array
	{
		return array(
			'business_name' => array('business', 'business name', 'company name', 'business_name', 'company'),
			'contact_name' => array('contact', 'contact name', 'name', 'full name', 'contact_name'),
			'first_name' => array('first', 'first name', 'firstname', 'first_name'),
			'last_name' => array('last', 'last name', 'lastname', 'last_name'),
			'email' => array('email', 'e-mail', 'email address', 'email_address'),
			'phone' => array('phone', 'mobile', 'cell', 'phone number', 'phone_number'),
			'website' => array('website', 'web', 'url', 'site'),
			'facebook_url' => array('facebook', 'facebook url', 'facebook_url', 'facebook link'),
			'instagram_url' => array('instagram', 'instagram url', 'instagram_url', 'instagram link'),
			'city' => array('city', 'town'),
			'state' => array('state', 'province', 'region'),
			'company_group' => array('group', 'company group', 'company/group', 'company_group'),
			'contact_type' => array('type', 'contact type', 'prospect type', 'category', 'contact_type'),
			'tags' => array('tags', 'tag', 'labels'),
			'source' => array('source', 'import source', 'lead source'),
			'status' => array('status', 'stage'),
			'notes' => array('notes', 'note', 'comments', 'comment'),
		);
	}
}

if (!function_exists('vms_outreach_contact_supported_import_fields')) {
	function vms_outreach_contact_supported_import_fields(): array
	{
		return array(
			'business_name' => __('Business Name', 'backstage-outreach'),
			'contact_name' => __('Contact Name', 'backstage-outreach'),
			'first_name' => __('First Name', 'backstage-outreach'),
			'last_name' => __('Last Name', 'backstage-outreach'),
			'email' => __('Email', 'backstage-outreach'),
			'phone' => __('Phone', 'backstage-outreach'),
			'website' => __('Website', 'backstage-outreach'),
			'facebook_url' => __('Facebook URL', 'backstage-outreach'),
			'instagram_url' => __('Instagram URL', 'backstage-outreach'),
			'city' => __('City', 'backstage-outreach'),
			'state' => __('State', 'backstage-outreach'),
			'company_group' => __('Company / Group', 'backstage-outreach'),
			'contact_type' => __('Type', 'backstage-outreach'),
			'tags' => __('Tags', 'backstage-outreach'),
			'source' => __('Source', 'backstage-outreach'),
			'status' => __('Status', 'backstage-outreach'),
			'notes' => __('Notes', 'backstage-outreach'),
		);
	}
}

if (!function_exists('vms_outreach_contact_import_mapping_options')) {
	function vms_outreach_contact_import_mapping_options(): array
	{
		return array(
			'' => __('Do Not Import', 'backstage-outreach'),
		) + vms_outreach_contact_supported_import_fields();
	}
}

if (!function_exists('vms_outreach_normalize_csv_header')) {
	function vms_outreach_normalize_csv_header(string $header): string
	{
		$header = strtolower(trim($header));
		$header = preg_replace('/[\s\-_\/]+/', ' ', $header);
		return is_string($header) ? trim($header) : '';
	}
}

if (!function_exists('vms_outreach_suggested_contact_csv_mapping')) {
	function vms_outreach_suggested_contact_csv_mapping(array $header_row): array
	{
		$aliases = vms_outreach_contact_csv_header_aliases();
		$mapping = array();
		$used = array();
		foreach (array_values($header_row) as $index => $header) {
			$mapping[$index] = '';
			$normalized = vms_outreach_normalize_csv_header((string) $header);
			if ($normalized === '') {
				continue;
			}
			foreach ($aliases as $field => $field_aliases) {
				if (isset($used[$field])) {
					continue;
				}
				if (in_array($normalized, array_map('vms_outreach_normalize_csv_header', (array) $field_aliases), true)) {
					$mapping[$index] = $field;
					$used[$field] = true;
					break;
				}
			}
		}
		return $mapping;
	}
}

if (!function_exists('vms_outreach_normalize_selected_contact_csv_mapping')) {
	function vms_outreach_normalize_selected_contact_csv_mapping(array $raw_mapping, array $header_row): array
	{
		$supported = vms_outreach_contact_supported_import_fields();
		$mapping = array();
		foreach (array_values($header_row) as $index => $header) {
			$field = sanitize_key((string) ($raw_mapping[$index] ?? ''));
			$mapping[$index] = isset($supported[$field]) ? $field : '';
		}
		return $mapping;
	}
}

if (!function_exists('vms_outreach_validate_selected_contact_csv_mapping')) {
	function vms_outreach_validate_selected_contact_csv_mapping(array $selected_mapping, array $header_row)
	{
		$selected_mapping = vms_outreach_normalize_selected_contact_csv_mapping($selected_mapping, $header_row);
		$used = array();
		foreach ($selected_mapping as $index => $field) {
			if ($field === '') {
				continue;
			}
			if (isset($used[$field])) {
				$labels = vms_outreach_contact_supported_import_fields();
				return new WP_Error(
					'contact_import_duplicate_mapping',
					sprintf(
						/* translators: %s: field label */
						__('Map each CSV column to a unique field. %s is selected more than once.', 'backstage-outreach'),
						(string) ($labels[$field] ?? $field)
					)
				);
			}
			$used[$field] = true;
		}
		if (!in_array('email', $selected_mapping, true)) {
			return new WP_Error('contact_import_email_mapping_required', __('Map one uploaded column to Email before previewing or importing.', 'backstage-outreach'));
		}
		return array(
			'selected_mapping' => $selected_mapping,
			'column_map' => array_filter($selected_mapping),
		);
	}
}

if (!function_exists('vms_outreach_csv_row_blank')) {
	function vms_outreach_csv_row_blank(array $row): bool
	{
		foreach ($row as $value) {
			if (trim((string) $value) !== '') {
				return false;
			}
		}
		return true;
	}
}

if (!function_exists('vms_outreach_contact_csv_sample_values')) {
	function vms_outreach_contact_csv_sample_values(array $header_row, array $data_rows): array
	{
		$samples = array();
		foreach (array_values($header_row) as $index => $header) {
			$samples[$index] = '';
			foreach ($data_rows as $row) {
				$values = isset($row['values']) && is_array($row['values']) ? $row['values'] : array();
				$value = sanitize_text_field((string) ($values[$index] ?? ''));
				if ($value !== '') {
					$samples[$index] = $value;
					break;
				}
			}
		}
		return $samples;
	}
}

if (!function_exists('vms_outreach_contact_csv_file_error_message')) {
	function vms_outreach_contact_csv_file_error_message(int $error_code): string
	{
		switch ($error_code) {
			case UPLOAD_ERR_NO_FILE:
				return __('Choose a CSV file to import.', 'backstage-outreach');
			case UPLOAD_ERR_INI_SIZE:
			case UPLOAD_ERR_FORM_SIZE:
				return __('The uploaded CSV file is too large.', 'backstage-outreach');
			default:
				return __('The CSV upload could not be processed.', 'backstage-outreach');
		}
	}
}

if (!function_exists('vms_outreach_parse_contact_import_csv')) {
	function vms_outreach_parse_contact_import_csv(string $file_path): array
	{
		$handle = fopen($file_path, 'rb');
		if ($handle === false) {
			return array('error' => new WP_Error('contact_import_open_failed', __('Could not open the uploaded CSV file.', 'backstage-outreach')));
		}

		$header_row = fgetcsv($handle);
		if (!is_array($header_row)) {
			fclose($handle);
			return array('error' => new WP_Error('contact_import_missing_header', __('The CSV file must include a header row.', 'backstage-outreach')));
		}

		$headers = array_values(array_map('sanitize_text_field', $header_row));
		$non_empty_headers = array_filter(array_map('vms_outreach_normalize_csv_header', $headers));
		if (empty($non_empty_headers)) {
			fclose($handle);
			return array('error' => new WP_Error('contact_import_headers_invalid', __('The CSV header row is missing readable column names.', 'backstage-outreach')));
		}

		$row_limit = vms_outreach_contact_import_row_limit();
		$data_rows = array();
		$blank_rows = 0;
		$row_number = 1;

		while (($row = fgetcsv($handle)) !== false) {
			$row_number += 1;
			$values = array_map(static function ($value): string {
				return is_scalar($value) ? (string) $value : '';
			}, (array) $row);
			if (vms_outreach_csv_row_blank($values)) {
				$blank_rows += 1;
				continue;
			}
			$data_rows[] = array(
				'row_number' => $row_number,
				'values' => array_values($values),
			);
			if (count($data_rows) > $row_limit) {
				fclose($handle);
				return array(
					'error' => new WP_Error(
						'contact_import_row_limit',
						sprintf(__('CSV imports are limited to %d contact rows per upload.', 'backstage-outreach'), $row_limit)
					),
				);
			}
		}

		fclose($handle);

		return array(
			'header_row' => $headers,
			'data_rows' => $data_rows,
			'blank_rows' => $blank_rows,
			'suggested_mapping' => vms_outreach_suggested_contact_csv_mapping($headers),
			'sample_values' => vms_outreach_contact_csv_sample_values($headers, $data_rows),
		);
	}
}

if (!function_exists('vms_outreach_validate_contact_import_file')) {
	function vms_outreach_validate_contact_import_file(array $file)
	{
		$error_code = absint($file['error'] ?? UPLOAD_ERR_NO_FILE);
		if ($error_code !== UPLOAD_ERR_OK) {
			return new WP_Error('contact_import_upload_error', vms_outreach_contact_csv_file_error_message($error_code));
		}

		$tmp_name = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';
		$file_size = absint($file['size'] ?? 0);
		$file_name = sanitize_file_name((string) ($file['name'] ?? ''));
		if ($tmp_name === '' || !is_uploaded_file($tmp_name)) {
			return new WP_Error('contact_import_upload_missing', __('The uploaded CSV could not be verified.', 'backstage-outreach'));
		}
		if ($file_name === '' || strtolower((string) pathinfo($file_name, PATHINFO_EXTENSION)) !== 'csv') {
			return new WP_Error('contact_import_invalid_type', __('Upload a CSV file with a .csv extension.', 'backstage-outreach'));
		}
		if ($file_size <= 0 || $file_size > vms_outreach_contact_import_max_file_bytes()) {
			return new WP_Error(
				'contact_import_file_too_large',
				sprintf(__('CSV uploads are limited to %d MB.', 'backstage-outreach'), max(1, (int) round(vms_outreach_contact_import_max_file_bytes() / 1048576)))
			);
		}

		return array(
			'tmp_name' => $tmp_name,
			'file_name' => $file_name,
			'file_size' => $file_size,
		);
	}
}

if (!function_exists('vms_outreach_parse_uploaded_contact_csv_for_mapping')) {
	function vms_outreach_parse_uploaded_contact_csv_for_mapping(array $file)
	{
		$file_info = vms_outreach_validate_contact_import_file($file);
		if (is_wp_error($file_info)) {
			return $file_info;
		}

		$parsed = vms_outreach_parse_contact_import_csv((string) ($file_info['tmp_name'] ?? ''));
		if (is_wp_error($parsed['error'] ?? null)) {
			return $parsed['error'];
		}

		$parsed['file_name'] = sanitize_file_name((string) ($file_info['file_name'] ?? ''));
		return $parsed;
	}
}

if (!function_exists('vms_outreach_build_contact_import_row_input')) {
	function vms_outreach_build_contact_import_row_input(array $values, array $column_map): array
	{
		$raw = array();
		foreach ($column_map as $index => $field) {
			if ($field === '') {
				continue;
			}
			$raw[$field] = (string) ($values[$index] ?? '');
		}
		return $raw;
	}
}

if (!function_exists('vms_outreach_sanitize_import_contact_payload')) {
	function vms_outreach_sanitize_import_contact_payload(array $raw)
	{
		$import_tags = vms_outreach_normalize_tags((string) ($raw['tags'] ?? ''));
		$type_raw = sanitize_text_field((string) ($raw['contact_type'] ?? ''));
		$type_key = vms_outreach_resolve_catalog_key($type_raw, vms_outreach_contact_type_options(), 'other');
		if ($type_raw !== '' && $type_key === 'other' && preg_replace('/[^a-z0-9]+/', '', strtolower($type_raw)) !== 'other') {
			$import_tags = vms_outreach_append_tag($import_tags, $type_raw);
		}
		$raw['contact_type'] = $type_key;

		$status_raw = sanitize_text_field((string) ($raw['status'] ?? ''));
		$raw['status'] = vms_outreach_resolve_catalog_key($status_raw, vms_outreach_contact_status_options(), 'new');
		$raw['tags'] = $import_tags;

		return vms_outreach_sanitize_contact_payload($raw);
	}
}

if (!function_exists('vms_outreach_preview_contact_import_from_parsed_csv')) {
	function vms_outreach_preview_contact_import_from_parsed_csv(array $parsed, array $selected_mapping)
	{
		$header_row = array_values((array) ($parsed['header_row'] ?? array()));
		$data_rows = (array) ($parsed['data_rows'] ?? array());
		$mapping = vms_outreach_validate_selected_contact_csv_mapping($selected_mapping, $header_row);
		if (is_wp_error($mapping)) {
			return $mapping;
		}

		$column_map = (array) ($mapping['column_map'] ?? array());
		$merged_rows = array();
		$duplicate_merge_count = 0;
		$invalid_rows = array();

		foreach ($data_rows as $row) {
			$row_number = absint($row['row_number'] ?? 0);
			$values = isset($row['values']) && is_array($row['values']) ? $row['values'] : array();
			$raw_input = vms_outreach_build_contact_import_row_input($values, $column_map);
			$email_norm = vms_outreach_normalize_email((string) ($raw_input['email'] ?? ''));
			if ($email_norm === '') {
				$invalid_rows[] = array(
					'row_number' => $row_number,
					'reason' => __('Missing or invalid email.', 'backstage-outreach'),
				);
				continue;
			}

			$sanitized = vms_outreach_sanitize_import_contact_payload($raw_input);
			if (is_wp_error($sanitized)) {
				$invalid_rows[] = array(
					'row_number' => $row_number,
					'reason' => $sanitized->get_error_message(),
				);
				continue;
			}

			$sanitized['row_numbers'] = array($row_number);
			if (isset($merged_rows[$email_norm])) {
				$merged_rows[$email_norm] = vms_outreach_merge_contact_payload($merged_rows[$email_norm], $sanitized, false);
				$merged_rows[$email_norm]['row_numbers'][] = $row_number;
				$duplicate_merge_count += 1;
				continue;
			}
			$merged_rows[$email_norm] = $sanitized;
		}

		$email_norms = array_keys($merged_rows);
		$existing_contacts = vms_outreach_get_contacts_by_email_norms($email_norms);
		$suppressions = vms_outreach_get_suppressions_by_email_norms($email_norms);

		$prepared_rows = array();
		$preview_rows = array();
		$new_count = 0;
		$update_count = 0;
		$suppressed_count = 0;

		foreach ($merged_rows as $email_norm => $candidate) {
			$existing = $existing_contacts[$email_norm] ?? null;
			$suppression = $suppressions[$email_norm] ?? null;
			$action = is_array($existing) ? 'update' : 'new';
			$final_payload = is_array($existing)
				? vms_outreach_merge_contact_payload($existing, $candidate, true)
				: $candidate;

			if ($action === 'new') {
				$new_count += 1;
			} else {
				$update_count += 1;
			}

			if (is_array($suppression)) {
				$suppressed_count += 1;
			}

			$prepared_rows[] = array(
				'action' => $action,
				'contact_id' => absint($existing['id'] ?? 0),
				'row_numbers' => array_values(array_map('absint', (array) ($candidate['row_numbers'] ?? array()))),
				'payload' => $final_payload,
				'suppression' => $suppression,
			);

			$preview_rows[] = array(
				'action' => $action,
				'row_numbers' => array_values(array_map('absint', (array) ($candidate['row_numbers'] ?? array()))),
				'email' => (string) ($candidate['email'] ?? ''),
				'contact_name' => (string) ($final_payload['contact_name'] ?? ''),
				'business_name' => (string) ($final_payload['business_name'] ?? ''),
				'contact_type' => (string) ($final_payload['contact_type'] ?? 'other'),
				'status' => (string) ($final_payload['status'] ?? 'new'),
				'suppressed' => is_array($suppression),
				'suppression_reason' => is_array($suppression) ? (string) ($suppression['reason'] ?? '') : '',
			);
		}

		return array(
			'file_name' => sanitize_file_name((string) ($parsed['file_name'] ?? '')),
			'total_rows' => count($data_rows),
			'blank_rows' => absint($parsed['blank_rows'] ?? 0),
			'selected_mapping' => array_values((array) ($mapping['selected_mapping'] ?? array())),
			'prepared_rows' => $prepared_rows,
			'preview_rows' => $preview_rows,
			'new_count' => $new_count,
			'update_count' => $update_count,
			'duplicate_merge_count' => $duplicate_merge_count,
			'suppressed_count' => $suppressed_count,
			'invalid_email_count' => count($invalid_rows),
			'invalid_rows' => $invalid_rows,
		);
	}
}

if (!function_exists('vms_outreach_commit_contact_import_preview')) {
	function vms_outreach_commit_contact_import_preview(array $preview, int $user_id = 0)
	{
		$prepared_rows = isset($preview['prepared_rows']) && is_array($preview['prepared_rows']) ? $preview['prepared_rows'] : array();
		if (empty($prepared_rows)) {
			return new WP_Error('contact_import_empty', __('There are no valid contacts ready to import.', 'backstage-outreach'));
		}

		$created = 0;
		$updated = 0;
		foreach ($prepared_rows as $row) {
			$contact_id = absint($row['contact_id'] ?? 0);
			$payload = isset($row['payload']) && is_array($row['payload']) ? $row['payload'] : array();
			$saved = vms_outreach_save_contact($payload, $user_id, $contact_id);
			if (is_wp_error($saved)) {
				return $saved;
			}
			if ((string) ($row['action'] ?? 'new') === 'update') {
				$updated += 1;
			} else {
				$created += 1;
			}
		}

		return array(
			'new_count' => $created,
			'update_count' => $updated,
			'duplicate_merge_count' => absint($preview['duplicate_merge_count'] ?? 0),
			'suppressed_count' => absint($preview['suppressed_count'] ?? 0),
			'invalid_email_count' => absint($preview['invalid_email_count'] ?? 0),
			'blank_rows' => absint($preview['blank_rows'] ?? 0),
		);
	}
}
