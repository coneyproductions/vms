<?php
defined('ABSPATH') || exit;

/**
 * STAFF-01 / Phase A
 * Structured labor-per-event staffing roles:
 * - Role Catalog (taxonomy-backed with term meta)
 * - Staffing Templates + Template Slots
 * - Event Role Slots + Assignments
 * - Event Staffing Rollups
 * - Rebuild helpers
 */

if (!function_exists('vms_staffing_table_name')) {
	function vms_staffing_table_name(string $kind): string
	{
		global $wpdb;

		$map = array(
			'templates'      => defined('BVMGR_DB_TABLE_STAFFING_TEMPLATES_SUFFIX') ? BVMGR_DB_TABLE_STAFFING_TEMPLATES_SUFFIX : 'vms_staffing_templates',
			'template_slots' => defined('BVMGR_DB_TABLE_STAFFING_TEMPLATE_SLOTS_SUFFIX') ? BVMGR_DB_TABLE_STAFFING_TEMPLATE_SLOTS_SUFFIX : 'vms_staffing_template_slots',
			'event_slots'    => defined('BVMGR_DB_TABLE_EVENT_ROLE_SLOTS_SUFFIX') ? BVMGR_DB_TABLE_EVENT_ROLE_SLOTS_SUFFIX : 'vms_event_role_slots',
			'assignments'    => defined('BVMGR_DB_TABLE_EVENT_ROLE_ASSIGNMENTS_SUFFIX') ? BVMGR_DB_TABLE_EVENT_ROLE_ASSIGNMENTS_SUFFIX : 'vms_event_role_assignments',
			'rollups'        => defined('BVMGR_DB_TABLE_STAFFING_EVENT_ROLLUPS_SUFFIX') ? BVMGR_DB_TABLE_STAFFING_EVENT_ROLLUPS_SUFFIX : 'vms_staffing_event_rollups',
			'audit'          => defined('BVMGR_DB_TABLE_STAFFING_AUDIT_LOG_SUFFIX') ? BVMGR_DB_TABLE_STAFFING_AUDIT_LOG_SUFFIX : 'vms_staffing_audit_log',
		);

		$suffix = isset($map[$kind]) ? (string) $map[$kind] : '';
		if ($suffix === '') {
			return '';
		}
		return $wpdb->prefix . $suffix;
	}
}

if (!function_exists('vms_staffing_templates_have_attendance_band_columns')) {
	function vms_staffing_templates_have_attendance_band_columns(): bool
	{
		global $wpdb;

		$table = vms_staffing_table_name('templates');
		if ($table === '') {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- This request-local DESC probe gates custom-table compatibility behavior; no core API exposes the schema state and stale cache risks masking migrations.
		$columns = $wpdb->get_col($wpdb->prepare('DESC %i', $table), 0);
		if (!is_array($columns) || empty($columns)) {
			return false;
		}

		return in_array('min_headcount', $columns, true) && in_array('max_headcount', $columns, true);
	}
}

if (!function_exists('vms_staffing_ensure_template_attendance_band_schema')) {
	function vms_staffing_ensure_template_attendance_band_schema(): bool
	{
		if (vms_staffing_templates_have_attendance_band_columns()) {
			return true;
		}

		if (defined('BVMGR_PLUGIN_PATH')) {
			$path = BVMGR_PLUGIN_PATH . 'includes/db/migrations.php';
			if (file_exists($path)) {
				require_once $path;
			}
		}

		if (function_exists('bvmgr_db_migrate_vendor_core_v6')) {
			bvmgr_db_migrate_vendor_core_v6();
		} elseif (function_exists('bvmgr_db_migrate_vendor_core_v5')) {
			bvmgr_db_migrate_vendor_core_v5();
		}

		return vms_staffing_templates_have_attendance_band_columns();
	}
}

if (!function_exists('vms_staffing_template_slots_have_activation_threshold_column')) {
	function vms_staffing_template_slots_have_activation_threshold_column(): bool
	{
		global $wpdb;

		$table = vms_staffing_table_name('template_slots');
		if ($table === '') {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- This request-local DESC probe gates custom template-slot compatibility behavior; no core API exposes the schema state and stale cache risks masking migrations.
		$columns = $wpdb->get_col($wpdb->prepare('DESC %i', $table), 0);
		if (!is_array($columns) || empty($columns)) {
			return false;
		}

		return in_array('activation_threshold', $columns, true);
	}
}

if (!function_exists('vms_staffing_ensure_template_slot_activation_schema')) {
	function vms_staffing_ensure_template_slot_activation_schema(): bool
	{
		if (vms_staffing_template_slots_have_activation_threshold_column()) {
			return true;
		}

		if (defined('BVMGR_PLUGIN_PATH')) {
			$path = BVMGR_PLUGIN_PATH . 'includes/db/migrations.php';
			if (file_exists($path)) {
				require_once $path;
			}
		}

		if (function_exists('bvmgr_db_migrate_vendor_core_v6')) {
			bvmgr_db_migrate_vendor_core_v6();
		}

		return vms_staffing_template_slots_have_activation_threshold_column();
	}
}

if (!function_exists('vms_staffing_role_meta_defaults')) {
	function vms_staffing_role_meta_defaults(): array
	{
		return array(
			'is_critical'               => 0,
			'is_active'                 => 1,
			'default_headcount'         => 1,
			'default_pay_type'          => 'none',
			'default_rate'              => '',
			'default_notes'             => '',
			'required_qualifications'   => array(),
			'required_qualification_rules' => array(),
			'qualification_check_mode'  => 'warn',
		);
	}
}

if (!function_exists('vms_staffing_role_meta_get')) {
	function vms_staffing_role_meta_get(int $role_id): array
	{
		$role_id = absint($role_id);
		$d = vms_staffing_role_meta_defaults();
		if ($role_id <= 0) {
			return $d;
		}

		$is_critical = absint(get_term_meta($role_id, '_vms_staff_role_is_critical', true)) ? 1 : 0;
		$is_active = metadata_exists('term', $role_id, '_vms_staff_role_is_active')
			? (absint(get_term_meta($role_id, '_vms_staff_role_is_active', true)) ? 1 : 0)
			: 1;
		$default_headcount = absint(get_term_meta($role_id, '_vms_staff_role_default_headcount', true));
		if ($default_headcount <= 0) {
			$default_headcount = 1;
		}
		$default_pay_type = sanitize_key((string) get_term_meta($role_id, '_vms_staff_role_default_pay_type', true));
		if (!in_array($default_pay_type, array('hourly', 'flat', 'none'), true)) {
			$default_pay_type = 'none';
		}
		$default_rate_raw = get_term_meta($role_id, '_vms_staff_role_default_rate', true);
		$default_rate = '';
		if ($default_rate_raw !== '' && $default_rate_raw !== null && is_numeric($default_rate_raw)) {
			$r = max(0, (float) $default_rate_raw);
			$default_rate = number_format($r, 2, '.', '');
		}
		$default_notes = (string) get_term_meta($role_id, '_vms_staff_role_default_notes', true);
		$qualification_check_mode = vms_staffing_normalize_qualification_mode((string) get_term_meta($role_id, '_vms_staff_role_qualification_check_mode', true), 'warn');
		$required_qualifications_raw = get_term_meta($role_id, '_vms_staff_role_required_qualifications', true);
		$required_qualification_rules = vms_staffing_normalize_role_required_qualification_rules($required_qualifications_raw, $qualification_check_mode);
		$required_qualifications = array_values(array_map(static function (array $rule): string {
			return (string) ($rule['name'] ?? '');
		}, $required_qualification_rules));

		return array(
			'is_critical'                  => $is_critical,
			'is_active'                    => $is_active,
			'default_headcount'            => $default_headcount,
			'default_pay_type'             => $default_pay_type,
			'default_rate'                 => $default_rate,
			'default_notes'                => $default_notes,
			'required_qualifications'      => $required_qualifications,
			'required_qualification_rules' => $required_qualification_rules,
			'qualification_check_mode'     => $qualification_check_mode,
		);
	}
}

if (!function_exists('vms_staffing_role_meta_save')) {
	function vms_staffing_role_meta_save(int $role_id, array $in): void
	{
		$role_id = absint($role_id);
		if ($role_id <= 0) {
			return;
		}

		$is_critical = !empty($in['is_critical']) ? 1 : 0;
		$is_active = array_key_exists('is_active', $in) ? (!empty($in['is_active']) ? 1 : 0) : 1;
		$default_headcount = isset($in['default_headcount']) ? absint($in['default_headcount']) : 1;
		if ($default_headcount <= 0) {
			$default_headcount = 1;
		}
		$default_pay_type = isset($in['default_pay_type']) ? sanitize_key((string) $in['default_pay_type']) : 'none';
		if (!in_array($default_pay_type, array('hourly', 'flat', 'none'), true)) {
			$default_pay_type = 'none';
		}
		$default_rate = '';
		if (isset($in['default_rate']) && $in['default_rate'] !== '' && is_numeric($in['default_rate'])) {
			$default_rate = number_format(max(0, (float) $in['default_rate']), 2, '.', '');
		}
		$default_notes = isset($in['default_notes']) ? sanitize_textarea_field((string) $in['default_notes']) : '';
		$qualification_check_mode = isset($in['qualification_check_mode']) ? vms_staffing_normalize_qualification_mode((string) $in['qualification_check_mode'], 'warn') : 'warn';
		$required_qualifications_raw = isset($in['required_qualifications']) ? $in['required_qualifications'] : array();
		$required_qualification_rules = vms_staffing_normalize_role_required_qualification_rules($required_qualifications_raw, $qualification_check_mode);
		$required_qualifications = array_values(array_map(static function (array $rule): string {
			return (string) ($rule['name'] ?? '');
		}, $required_qualification_rules));
		$qualification_check_mode = vms_staffing_normalize_qualification_mode($qualification_check_mode, 'warn');

		update_term_meta($role_id, '_vms_staff_role_is_critical', $is_critical);
		update_term_meta($role_id, '_vms_staff_role_is_active', $is_active);
		update_term_meta($role_id, '_vms_staff_role_default_headcount', $default_headcount);
		update_term_meta($role_id, '_vms_staff_role_default_pay_type', $default_pay_type);
		if ($default_rate === '') {
			delete_term_meta($role_id, '_vms_staff_role_default_rate');
		} else {
			update_term_meta($role_id, '_vms_staff_role_default_rate', $default_rate);
		}
		if ($default_notes === '') {
			delete_term_meta($role_id, '_vms_staff_role_default_notes');
		} else {
			update_term_meta($role_id, '_vms_staff_role_default_notes', $default_notes);
		}
		if (empty($required_qualification_rules)) {
			delete_term_meta($role_id, '_vms_staff_role_required_qualifications');
		} else {
			update_term_meta($role_id, '_vms_staff_role_required_qualifications', $required_qualification_rules);
		}
		update_term_meta($role_id, '_vms_staff_role_qualification_check_mode', $qualification_check_mode);
	}
}

if (!function_exists('vms_staffing_get_role_catalog')) {
	function vms_staffing_get_role_catalog(bool $include_inactive = false): array
	{
		if (!taxonomy_exists('vms_staff_role')) {
			return array();
		}

		$terms = get_terms(array(
			'taxonomy'   => 'vms_staff_role',
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		));

		if (is_wp_error($terms) || empty($terms)) {
			return array();
		}

		$out = array();
		foreach ($terms as $term) {
			$role_id = isset($term->term_id) ? absint($term->term_id) : 0;
			if ($role_id <= 0) {
				continue;
			}
			$meta = vms_staffing_role_meta_get($role_id);
			if (!$include_inactive && empty($meta['is_active'])) {
				continue;
			}
			$out[] = array(
				'role_id'                => $role_id,
				'name'                   => (string) $term->name,
				'slug'                   => (string) $term->slug,
				'is_critical'            => !empty($meta['is_critical']) ? 1 : 0,
				'is_active'              => !empty($meta['is_active']) ? 1 : 0,
				'default_headcount'      => (int) $meta['default_headcount'],
				'default_pay_type'       => (string) $meta['default_pay_type'],
				'default_rate'           => ($meta['default_rate'] === '' ? null : (float) $meta['default_rate']),
				'default_notes'             => (string) $meta['default_notes'],
				'required_qualifications'     => isset($meta['required_qualifications']) && is_array($meta['required_qualifications']) ? array_values($meta['required_qualifications']) : array(),
				'required_qualification_rules'=> isset($meta['required_qualification_rules']) && is_array($meta['required_qualification_rules']) ? array_values($meta['required_qualification_rules']) : array(),
				'qualification_check_mode'    => isset($meta['qualification_check_mode']) ? (string) $meta['qualification_check_mode'] : 'warn',
			);
		}

		return $out;
	}
}

if (!function_exists('vms_staffing_role_map_by_id')) {
	function vms_staffing_role_map_by_id(bool $include_inactive = true): array
	{
		$rows = vms_staffing_get_role_catalog($include_inactive);
		$map = array();
		foreach ($rows as $r) {
			$rid = isset($r['role_id']) ? absint($r['role_id']) : 0;
			if ($rid <= 0) continue;
			$map[$rid] = $r;
		}
		return $map;
	}
}

if (!function_exists('vms_staffing_staff_role_match_for_role')) {
	function vms_staffing_staff_role_match_for_role(int $staff_id, int $role_id): array
	{
		static $role_term_cache = array();
		static $staff_term_cache = array();
		static $legacy_role_cache = array();

		$staff_id = absint($staff_id);
		$role_id = absint($role_id);
		if ($staff_id <= 0 || $role_id <= 0) {
			return array(
				'ok' => false,
				'source' => 'invalid',
				'reason' => __('Role eligibility could not be resolved.', 'backstage-venue-manager'),
			);
		}

		if (!isset($role_term_cache[$role_id])) {
			$term = get_term($role_id, 'vms_staff_role');
			$role_term_cache[$role_id] = ($term instanceof WP_Term) ? $term : null;
		}
		$role_term = $role_term_cache[$role_id];
		/* translators: %d: role ID. */
		$role_name = $role_term instanceof WP_Term ? (string) $role_term->name : sprintf(__('Role #%d', 'backstage-venue-manager'), $role_id);

		if (!isset($staff_term_cache[$staff_id])) {
			$term_ids = taxonomy_exists('vms_staff_role')
				? wp_get_post_terms($staff_id, 'vms_staff_role', array('fields' => 'ids'))
				: array();
			$staff_term_cache[$staff_id] = is_array($term_ids)
				? array_values(array_unique(array_filter(array_map('absint', $term_ids))))
				: array();
		}
		if (in_array($role_id, $staff_term_cache[$staff_id], true)) {
			return array(
				'ok' => true,
				'source' => 'taxonomy',
				'reason' => '',
			);
		}

		if (!isset($legacy_role_cache[$staff_id])) {
			$legacy_role_raw = (string) get_post_meta($staff_id, '_vms_staff_role', true);
			$legacy_role_parts = preg_split('/\s*,\s*/', $legacy_role_raw);
			$legacy_role_tokens = array();
			if (is_array($legacy_role_parts)) {
				foreach ($legacy_role_parts as $legacy_role_part) {
					$legacy_role_part = trim(sanitize_text_field((string) $legacy_role_part));
					if ($legacy_role_part === '') {
						continue;
					}
					$legacy_role_tokens[] = strtolower($legacy_role_part);
					$legacy_role_tokens[] = sanitize_title($legacy_role_part);
				}
			}
			$legacy_role_cache[$staff_id] = array_values(array_unique(array_filter($legacy_role_tokens, 'strlen')));
		}

		$role_tokens = array_values(array_unique(array_filter(array(
			$role_term instanceof WP_Term ? sanitize_title((string) $role_term->slug) : '',
			$role_term instanceof WP_Term ? sanitize_title((string) $role_term->name) : '',
			$role_term instanceof WP_Term ? strtolower((string) $role_term->name) : '',
		), 'strlen')));
		foreach ($role_tokens as $role_token) {
			if (in_array($role_token, $legacy_role_cache[$staff_id], true)) {
				return array(
					'ok' => true,
					'source' => 'legacy_role_meta',
					'reason' => '',
				);
			}
		}

		return array(
			'ok' => false,
			'source' => 'none',
			/* translators: %s: human-readable value used in this message. */
			'reason' => sprintf(__('Not marked eligible for %s.', 'backstage-venue-manager'), $role_name),
		);
	}
}

if (!function_exists('vms_staffing_staff_qualification_meta_key')) {
	function vms_staffing_staff_qualification_meta_key(): string
	{
		return '_vms_staff_qualifications';
	}
}

if (!function_exists('vms_staffing_normalize_qualification_name')) {
	function vms_staffing_normalize_qualification_name(string $name): string
	{
		$name = sanitize_text_field($name);
		$name = preg_replace('/\s+/', ' ', trim((string) $name));
		return (string) $name;
	}
}


if (!function_exists('vms_staffing_normalize_qualification_mode')) {
	function vms_staffing_normalize_qualification_mode(string $mode, string $fallback = 'warn'): string
	{
		$mode = sanitize_key($mode);
		if (!in_array($mode, array('warn', 'soft_block', 'hard_block'), true)) {
			$mode = sanitize_key($fallback);
		}
		if (!in_array($mode, array('warn', 'soft_block', 'hard_block'), true)) {
			$mode = 'warn';
		}
		return $mode;
	}
}

if (!function_exists('vms_staffing_qualification_mode_rank')) {
	function vms_staffing_qualification_mode_rank(string $mode): int
	{
		$mode = vms_staffing_normalize_qualification_mode($mode);
		$map = array(
			'warn' => 1,
			'soft_block' => 2,
			'hard_block' => 3,
		);
		return isset($map[$mode]) ? (int) $map[$mode] : 1;
	}
}

if (!function_exists('vms_staffing_normalize_role_required_qualification_rule')) {
	function vms_staffing_normalize_role_required_qualification_rule($row, string $fallback_mode = 'warn'): ?array
	{
		if (is_string($row)) {
			$row = array('name' => $row, 'mode' => $fallback_mode);
		}
		if (!is_array($row)) {
			return null;
		}
		$name = isset($row['name']) ? vms_staffing_normalize_qualification_name((string) $row['name']) : '';
		if ($name === '') {
			return null;
		}
		$mode = isset($row['mode']) ? (string) $row['mode'] : $fallback_mode;
		$mode = vms_staffing_normalize_qualification_mode($mode, $fallback_mode);
		return array(
			'name' => $name,
			'mode' => $mode,
		);
	}
}

if (!function_exists('vms_staffing_normalize_role_required_qualification_rules')) {
	function vms_staffing_normalize_role_required_qualification_rules($raw, string $fallback_mode = 'warn'): array
	{
		$rules = array();
		if (is_array($raw)) {
			foreach ($raw as $row) {
				$rule = vms_staffing_normalize_role_required_qualification_rule($row, $fallback_mode);
				if (!is_array($rule)) {
					continue;
				}
				$key = sanitize_title((string) $rule['name']);
				if ($key === '') {
					continue;
				}
				$rules[$key] = $rule;
			}
		} elseif (is_string($raw) && trim($raw) !== '') {
			$parts = preg_split('/[
,]+/', (string) $raw);
			foreach ((array) $parts as $part) {
				$rule = vms_staffing_normalize_role_required_qualification_rule((string) $part, $fallback_mode);
				if (!is_array($rule)) {
					continue;
				}
				$key = sanitize_title((string) $rule['name']);
				if ($key === '') {
					continue;
				}
				$rules[$key] = $rule;
			}
		}
		return array_values($rules);
	}
}

if (!function_exists('vms_staffing_staff_qualification_allowed_statuses')) {
	function vms_staffing_staff_qualification_allowed_statuses(): array
	{
		return array('active', 'pending_verification', 'rejected', 'expired', 'inactive');
	}
}

if (!function_exists('vms_staffing_staff_qualification_status_label')) {
	function vms_staffing_staff_qualification_status_label(string $status): string
	{
		$status = sanitize_key($status);
		if ($status === 'active') return __('Approved', 'backstage-venue-manager');
		if ($status === 'pending_verification') return __('Pending Review', 'backstage-venue-manager');
		if ($status === 'rejected') return __('Rejected', 'backstage-venue-manager');
		if ($status === 'expired') return __('Expired', 'backstage-venue-manager');
		if ($status === 'inactive') return __('Inactive', 'backstage-venue-manager');
		return __('Unknown', 'backstage-venue-manager');
	}
}

if (!function_exists('vms_staffing_staff_qualification_generate_id')) {
	function vms_staffing_staff_qualification_generate_id(): string
	{
		return 'qual_' . strtolower(wp_generate_password(12, false, false));
	}
}

if (!function_exists('vms_staffing_normalize_staff_qualification_row')) {
	function vms_staffing_normalize_staff_qualification_row(array $row): ?array
	{
		$name = isset($row['name']) ? vms_staffing_normalize_qualification_name((string) $row['name']) : '';
		if ($name === '') {
			return null;
		}

		$id = isset($row['id']) ? sanitize_key((string) $row['id']) : '';
		if ($id === '') {
			$id = vms_staffing_staff_qualification_generate_id();
		}

		$authority = isset($row['authority']) ? sanitize_text_field((string) $row['authority']) : '';
		$credential_number = isset($row['credential_number']) ? sanitize_text_field((string) $row['credential_number']) : '';
		$issue_date = isset($row['issue_date']) ? trim((string) $row['issue_date']) : '';
		$expiration_date = isset($row['expiration_date']) ? trim((string) $row['expiration_date']) : '';
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $issue_date)) {
			$issue_date = '';
		}
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiration_date)) {
			$expiration_date = '';
		}
		$status = isset($row['status']) ? sanitize_key((string) $row['status']) : 'active';
		if (!in_array($status, vms_staffing_staff_qualification_allowed_statuses(), true)) {
			$status = 'active';
		}

		$attachment_id = isset($row['attachment_id']) ? absint($row['attachment_id']) : 0;
		$storage_kind = isset($row['storage_kind']) ? sanitize_key((string) $row['storage_kind']) : '';
		if ($storage_kind !== 'private_file') {
			$storage_kind = $attachment_id > 0 ? 'attachment' : '';
		}

		$proof_url = isset($row['proof_url']) ? esc_url_raw((string) $row['proof_url']) : '';
		if ($proof_url === '' && $attachment_id > 0 && $storage_kind !== 'private_file') {
			$attachment_url = wp_get_attachment_url($attachment_id);
			if ($attachment_url) {
				$proof_url = esc_url_raw((string) $attachment_url);
			}
		}

		$notes = isset($row['notes']) ? sanitize_textarea_field((string) $row['notes']) : '';
		$source = isset($row['source']) ? sanitize_key((string) $row['source']) : 'admin';
		if (!in_array($source, array('admin', 'staff_portal', 'migration'), true)) {
			$source = 'admin';
		}
		$submitted_by = isset($row['submitted_by']) ? absint($row['submitted_by']) : 0;
		$submitted_at = isset($row['submitted_at']) ? absint($row['submitted_at']) : 0;
		$reviewed_by = isset($row['reviewed_by']) ? absint($row['reviewed_by']) : 0;
		$reviewed_at = isset($row['reviewed_at']) ? absint($row['reviewed_at']) : 0;

		return array(
			'id' => $id,
			'name' => $name,
			'authority' => $authority,
			'credential_number' => $credential_number,
			'issue_date' => ($issue_date === '' ? null : $issue_date),
			'expiration_date' => ($expiration_date === '' ? null : $expiration_date),
			'status' => $status,
			'proof_url' => ($proof_url === '' ? null : $proof_url),
			'attachment_id' => $attachment_id > 0 ? $attachment_id : null,
			'storage_kind' => ($storage_kind === '' ? null : $storage_kind),
			'notes' => ($notes === '' ? null : $notes),
			'source' => $source,
			'submitted_by' => $submitted_by > 0 ? $submitted_by : null,
			'submitted_at' => $submitted_at > 0 ? $submitted_at : null,
			'reviewed_by' => $reviewed_by > 0 ? $reviewed_by : null,
			'reviewed_at' => $reviewed_at > 0 ? $reviewed_at : null,
		);
	}
}

if (!function_exists('vms_staffing_get_staff_qualifications')) {
	function vms_staffing_get_staff_qualifications(int $staff_id): array
	{
		$staff_id = absint($staff_id);
		if ($staff_id <= 0) {
			return array();
		}
		$raw = get_post_meta($staff_id, vms_staffing_staff_qualification_meta_key(), true);
		if (!is_array($raw)) {
			return array();
		}
		$today = current_time('Y-m-d');
		$out = array();
		foreach ($raw as $row) {
			if (!is_array($row)) {
				continue;
			}
			$clean = vms_staffing_normalize_staff_qualification_row($row);
			if (!is_array($clean)) {
				continue;
			}
			$effective_status = (string) $clean['status'];
			$expiring_soon = false;
			if (!empty($clean['expiration_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $clean['expiration_date'])) {
				if ((string) $clean['expiration_date'] < $today) {
					$effective_status = 'expired';
				} else {
					$days_until = (int) floor((strtotime((string) $clean['expiration_date'] . ' 00:00:00') - strtotime($today . ' 00:00:00')) / DAY_IN_SECONDS);
					$expiring_soon = ($days_until >= 0 && $days_until <= 30);
				}
			}
			$clean['effective_status'] = $effective_status;
			$clean['expiring_soon'] = $expiring_soon ? 1 : 0;
			$clean['match_key'] = sanitize_title((string) $clean['name']);
			$clean['proof_download_url'] = '';
			$clean['proof_label'] = '';
			$attachment_id = absint($clean['attachment_id'] ?? 0);
			$qualification_id = sanitize_key((string) ($clean['id'] ?? ''));
			if ($attachment_id > 0 && $qualification_id !== '' && function_exists('bvmgr_private_staff_cert_download_url')) {
				$clean['proof_download_url'] = bvmgr_private_staff_cert_download_url($staff_id, $qualification_id);
			}
			if ($attachment_id > 0 && function_exists('bvmgr_private_staff_cert_file_payload')) {
				$payload = bvmgr_private_staff_cert_file_payload($staff_id, $clean);
				if (!is_wp_error($payload)) {
					$clean['proof_label'] = trim((string) ($payload['filename'] ?? ''));
				}
			}
			$out[] = $clean;
		}
		return $out;
	}
}

if (!function_exists('vms_staffing_save_staff_qualifications')) {
	function vms_staffing_save_staff_qualifications(int $staff_id, array $rows): void
	{
		$staff_id = absint($staff_id);
		if ($staff_id <= 0) {
			return;
		}

		$old_rows = vms_staffing_get_staff_qualifications($staff_id);
		$old_private_ids = array();
		foreach ($old_rows as $old_row) {
			if (!is_array($old_row)) {
				continue;
			}
			if (sanitize_key((string) ($old_row['storage_kind'] ?? '')) !== 'private_file') {
				continue;
			}
			$file_id = absint($old_row['attachment_id'] ?? 0);
			if ($file_id > 0) {
				$old_private_ids[] = $file_id;
			}
		}

		$clean = array();
		$new_private_ids = array();
		foreach ($rows as $row) {
			if (!is_array($row)) {
				continue;
			}
			$norm = vms_staffing_normalize_staff_qualification_row($row);
			if (is_array($norm)) {
				$clean[] = $norm;
				if (sanitize_key((string) ($norm['storage_kind'] ?? '')) === 'private_file') {
					$file_id = absint($norm['attachment_id'] ?? 0);
					if ($file_id > 0) {
						$new_private_ids[] = $file_id;
					}
				}
			}
		}

		$stale_private_ids = array_diff(array_unique($old_private_ids), array_unique($new_private_ids));
		if (!empty($stale_private_ids) && function_exists('bvmgr_private_files_delete')) {
			foreach ($stale_private_ids as $stale_private_id) {
				bvmgr_private_files_delete((int) $stale_private_id);
			}
		}

		if (empty($clean)) {
			delete_post_meta($staff_id, vms_staffing_staff_qualification_meta_key());
			return;
		}
		update_post_meta($staff_id, vms_staffing_staff_qualification_meta_key(), $clean);
	}
}

if (!function_exists('vms_staffing_get_staff_user')) {
	function vms_staffing_get_staff_user(int $staff_id): ?WP_User
	{
		$staff_id = absint($staff_id);
		if ($staff_id <= 0) { return null; }
		$user_id = absint(get_post_meta($staff_id, '_vms_linked_user_id', true));
		$user = $user_id > 0 ? get_user_by('id', $user_id) : null;
		if ($user instanceof WP_User) { return $user; }
		global $wpdb;
		$t_usermeta = (is_object($wpdb) && isset($wpdb->usermeta) && is_string($wpdb->usermeta) && $wpdb->usermeta !== '') ? $wpdb->usermeta : ((is_object($wpdb) && isset($wpdb->prefix) && is_string($wpdb->prefix)) ? $wpdb->prefix . 'usermeta' : '');
		if ($t_usermeta === '' || !is_object($wpdb) || !method_exists($wpdb, 'get_var') || !method_exists($wpdb, 'prepare')) { return null; }



		/* phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Staff user fallback reads one normalized reverse usermeta pointer with prepared identifier/filter values, and staffing flows must observe immediate link edits. */ $user_id = (int) $wpdb->get_var($wpdb->prepare('SELECT user_id FROM %i WHERE meta_key = %s AND meta_value = %s ORDER BY umeta_id ASC LIMIT 1', $t_usermeta, '_vms_staff_id', (string) $staff_id));
		$user = $user_id > 0 ? get_user_by('id', $user_id) : null;
		return $user instanceof WP_User ? $user : null;
	}
}

if (!function_exists('vms_staffing_staff_qualification_admin_recipients')) {
	function vms_staffing_staff_qualification_admin_recipients(int $staff_id, array $row, string $event): array
	{
		$emails = array();
		$site_admin = sanitize_email((string) get_option('admin_email'));
		if ($site_admin !== '' && is_email($site_admin)) {
			$emails[] = $site_admin;
		}

		$admin_users = get_users(array(
			'role' => 'administrator',
			'fields' => array('user_email'),
		));
		foreach ((array) $admin_users as $admin_user) {
			$email = isset($admin_user->user_email) ? sanitize_email((string) $admin_user->user_email) : '';
			if ($email !== '' && is_email($email)) {
				$emails[] = $email;
			}
		}

		$emails = apply_filters('vms_staff_qualification_admin_notification_recipients', $emails, $staff_id, $row, $event);
		$out = array();
		foreach ((array) $emails as $email) {
			$email = sanitize_email((string) $email);
			if ($email !== '' && is_email($email)) {
				$out[] = $email;
			}
		}
		return array_values(array_unique($out));
	}
}

if (!function_exists('vms_staffing_mail_headers')) {
	function vms_staffing_mail_headers(): array
	{
		$from_email = sanitize_email((string) get_option('admin_email'));
		if ($from_email === '' || !is_email($from_email)) {
			return array();
		}
		$site_name = wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES);
		$site_name = trim(preg_replace('/[
]+/', ' ', $site_name));
		if ($site_name === '') {
			$site_name = 'Backstage Venue Manager';
		}
		return array('From: ' . $site_name . ' <' . $from_email . '>');
	}
}

if (!function_exists('vms_staffing_staff_qualification_review_url')) {
	function vms_staffing_staff_qualification_review_url(int $staff_id): string
	{
		$staff_id = absint($staff_id);
		if ($staff_id <= 0) {
			return admin_url('edit.php?post_type=vms_staff');
		}
		return admin_url('post.php?post=' . $staff_id . '&action=edit#vms-staff-qualifications');
	}
}

if (!function_exists('vms_staffing_staff_qualification_mail_lines')) {
	function vms_staffing_staff_qualification_mail_lines(array $lines): string
	{
		$clean = array();
		foreach ($lines as $line) {
			$clean[] = wp_strip_all_tags((string) $line);
		}
		return implode("\n", $clean);
	}
}

if (!function_exists('vms_staffing_record_qualification_audit')) {
	function vms_staffing_record_qualification_audit(int $staff_id, string $action, array $row, ?int $actor_user_id = null): void
	{
		$staff_id = absint($staff_id);
		if ($staff_id <= 0) {
			return;
		}
		$audit = get_post_meta($staff_id, '_vms_staff_qualification_audit', true);
		if (!is_array($audit)) {
			$audit = array();
		}
		$audit[] = array(
			'action' => sanitize_key($action),
			'qualification_id' => isset($row['id']) ? sanitize_key((string) $row['id']) : '',
			'qualification' => isset($row['name']) ? sanitize_text_field((string) $row['name']) : '',
			'status' => isset($row['status']) ? sanitize_key((string) $row['status']) : '',
			'actor_user_id' => $actor_user_id ? absint($actor_user_id) : get_current_user_id(),
			'timestamp' => time(),
		);
		if (count($audit) > 100) {
			$audit = array_slice($audit, -100);
		}
		update_post_meta($staff_id, '_vms_staff_qualification_audit', $audit);
	}
}

if (!function_exists('vms_staffing_send_staff_qualification_submission_notifications')) {
	function vms_staffing_send_staff_qualification_submission_notifications(int $staff_id, array $row, ?int $submitter_user_id = null): void
	{
		$staff_id = absint($staff_id);
		$staff_name = get_the_title($staff_id);
		if ($staff_name === '') {
			$staff_name = __('Staff member', 'backstage-venue-manager');
		}
		$qualification = isset($row['name']) ? (string) $row['name'] : __('Certification', 'backstage-venue-manager');
		$expiration = !empty($row['expiration_date']) ? (string) $row['expiration_date'] : __('Not provided', 'backstage-venue-manager');
		$review_url = vms_staffing_staff_qualification_review_url($staff_id);

		$admin_body = vms_staffing_staff_qualification_mail_lines(array(
			/* translators: %s: a staff certification was submitted for review. */
			sprintf(__('A staff certification was submitted for review: %s', 'backstage-venue-manager'), $qualification),
			'',
			/* translators: %s: staff member. */
			sprintf(__('Staff member: %s', 'backstage-venue-manager'), $staff_name),
			/* translators: %s: certification. */
			sprintf(__('Certification: %s', 'backstage-venue-manager'), $qualification),
			/* translators: %s: expiration. */
			sprintf(__('Expiration: %s', 'backstage-venue-manager'), $expiration),
			/* translators: %s: review link URL. */
			sprintf(__('Review link: %s', 'backstage-venue-manager'), $review_url),
		));
		foreach (vms_staffing_staff_qualification_admin_recipients($staff_id, $row, 'submitted') as $email) {
			/* translators: %s: staff certification pending review. */
			wp_mail($email, sprintf(__('[Backstage Venue Manager] Staff certification pending review: %s', 'backstage-venue-manager'), $qualification), $admin_body, vms_staffing_mail_headers());
		}

		$user = $submitter_user_id ? get_user_by('id', absint($submitter_user_id)) : vms_staffing_get_staff_user($staff_id);
		if ($user instanceof WP_User && is_email($user->user_email)) {
			$staff_body = vms_staffing_staff_qualification_mail_lines(array(
				/* translators: %s: human-readable value used in this message. */
				sprintf(__('We received your %s certificate.', 'backstage-venue-manager'), $qualification),
				'',
				__('It is now pending review. We will notify you when it has been approved or if anything needs to be corrected.', 'backstage-venue-manager'),
				/* translators: %s: expiration date submitted. */
				$expiration !== __('Not provided', 'backstage-venue-manager') ? sprintf(__('Expiration date submitted: %s', 'backstage-venue-manager'), $expiration) : '',
			));
			/* translators: %s: human-readable value used in this message. */
			wp_mail($user->user_email, sprintf(__('We received your %s certificate', 'backstage-venue-manager'), $qualification), $staff_body, vms_staffing_mail_headers());
		}
	}
}

if (!function_exists('vms_staffing_send_staff_qualification_review_notification')) {
	function vms_staffing_send_staff_qualification_review_notification(int $staff_id, array $row, string $new_status): void
	{
		$new_status = sanitize_key($new_status);
		if (!in_array($new_status, array('active', 'rejected'), true)) {
			return;
		}
		$qualification = isset($row['name']) ? (string) $row['name'] : __('Certification', 'backstage-venue-manager');
		$expiration = !empty($row['expiration_date']) ? (string) $row['expiration_date'] : '';
		$notes = isset($row['notes']) ? trim((string) $row['notes']) : '';
		$status_event = $new_status === 'active' ? 'approved' : 'rejected';

		if ($new_status === 'active') {
			$lines = array(
				/* translators: %s: human-readable value used in this message. */
				sprintf(__('Your %s certificate has been approved.', 'backstage-venue-manager'), $qualification),
				/* translators: %s: expiration date on file. */
				$expiration !== '' ? sprintf(__('Expiration date on file: %s', 'backstage-venue-manager'), $expiration) : '',
			);
			/* translators: %s: human-readable value used in this message. */
			$subject = sprintf(__('Your %s certificate was approved', 'backstage-venue-manager'), $qualification);
		} else {
			$lines = array(
				/* translators: %s: human-readable value used in this message. */
				sprintf(__('Your %s certificate could not be approved yet.', 'backstage-venue-manager'), $qualification),
				/* translators: %s: reason. */
				$notes !== '' ? sprintf(__('Reason: %s', 'backstage-venue-manager'), $notes) : __('Please upload a replacement or contact the venue if you have questions.', 'backstage-venue-manager'),
			);
			/* translators: %s: human-readable value used in this message. */
			$subject = sprintf(__('Your %s certificate needs attention', 'backstage-venue-manager'), $qualification);
		}

		$user = vms_staffing_get_staff_user($staff_id);
		if ($user instanceof WP_User && is_email($user->user_email)) {
			wp_mail($user->user_email, $subject, vms_staffing_staff_qualification_mail_lines($lines), vms_staffing_mail_headers());
		}

		$staff_name = get_the_title($staff_id);
		if ($staff_name === '') {
			$staff_name = __('Staff member', 'backstage-venue-manager');
		}
		$admin_lines = array(
			/* translators: 1: certification review action such as approved or rejected, 2: certification name. */
			sprintf(__('Staff certification %1$s: %2$s', 'backstage-venue-manager'), $status_event, $qualification),
			'',
			/* translators: %s: staff member. */
			sprintf(__('Staff member: %s', 'backstage-venue-manager'), $staff_name),
			/* translators: %s: certification. */
			sprintf(__('Certification: %s', 'backstage-venue-manager'), $qualification),
			/* translators: %s: expiration. */
			$expiration !== '' ? sprintf(__('Expiration: %s', 'backstage-venue-manager'), $expiration) : '',
			/* translators: %s: reason. */
			($new_status === 'rejected' && $notes !== '') ? sprintf(__('Reason: %s', 'backstage-venue-manager'), $notes) : '',
			/* translators: %s: staff profile. */
			sprintf(__('Staff profile: %s', 'backstage-venue-manager'), vms_staffing_staff_qualification_review_url($staff_id)),
		);
		foreach (vms_staffing_staff_qualification_admin_recipients($staff_id, $row, $status_event) as $email) {
			/* translators: 1: certification review action such as approved or rejected, 2: certification name. */
			wp_mail($email, sprintf(__('[Backstage Venue Manager] Staff certification %1$s: %2$s', 'backstage-venue-manager'), $status_event, $qualification), vms_staffing_staff_qualification_mail_lines($admin_lines), vms_staffing_mail_headers());
		}
	}
}

if (!function_exists('vms_staffing_add_staff_qualification_submission')) {
	function vms_staffing_add_staff_qualification_submission(int $staff_id, array $row, int $submitter_user_id): array
	{
		$staff_id = absint($staff_id);
		$submitter_user_id = absint($submitter_user_id);
		if ($staff_id <= 0 || $submitter_user_id <= 0) {
			return array('ok' => false, 'message' => __('Invalid staff certification submission.', 'backstage-venue-manager'));
		}
		$row['id'] = isset($row['id']) ? sanitize_key((string) $row['id']) : vms_staffing_staff_qualification_generate_id();
		$row['status'] = 'pending_verification';
		$row['source'] = 'staff_portal';
		$row['submitted_by'] = $submitter_user_id;
		$row['submitted_at'] = time();
		$clean = vms_staffing_normalize_staff_qualification_row($row);
		if (!is_array($clean)) {
			return array('ok' => false, 'message' => __('Please enter the certification name before uploading.', 'backstage-venue-manager'));
		}
		$rows = vms_staffing_get_staff_qualifications($staff_id);
		$rows[] = $clean;
		vms_staffing_save_staff_qualifications($staff_id, $rows);
		vms_staffing_record_qualification_audit($staff_id, 'submitted', $clean, $submitter_user_id);
		vms_staffing_send_staff_qualification_submission_notifications($staff_id, $clean, $submitter_user_id);
		return array('ok' => true, 'row' => $clean);
	}
}

if (!function_exists('vms_staffing_save_staff_qualifications_with_review')) {
	function vms_staffing_save_staff_qualifications_with_review(int $staff_id, array $rows, ?int $actor_user_id = null): void
	{
		$staff_id = absint($staff_id);
		$actor_user_id = $actor_user_id ? absint($actor_user_id) : get_current_user_id();
		if ($staff_id <= 0) {
			return;
		}

		$old_rows = vms_staffing_get_staff_qualifications($staff_id);
		$old_by_id = array();
		foreach ($old_rows as $old) {
			$id = isset($old['id']) ? sanitize_key((string) $old['id']) : '';
			if ($id !== '') {
				$old_by_id[$id] = $old;
			}
		}

		$clean = array();
		$transitions = array();
		foreach ($rows as $row) {
			if (!is_array($row)) {
				continue;
			}
			$norm = vms_staffing_normalize_staff_qualification_row($row);
			if (!is_array($norm)) {
				continue;
			}
			$id = sanitize_key((string) ($norm['id'] ?? ''));
			$old_status = ($id !== '' && isset($old_by_id[$id]['status'])) ? sanitize_key((string) $old_by_id[$id]['status']) : '';
			$new_status = sanitize_key((string) ($norm['status'] ?? ''));
			if ($old_status !== '' && $old_status !== $new_status && in_array($new_status, array('active', 'rejected'), true)) {
				$norm['reviewed_by'] = $actor_user_id > 0 ? $actor_user_id : null;
				$norm['reviewed_at'] = time();
				$transitions[] = array('row' => $norm, 'status' => $new_status);
			}
			$clean[] = $norm;
		}

		vms_staffing_save_staff_qualifications($staff_id, $clean);

		foreach ($transitions as $transition) {
			$row = isset($transition['row']) && is_array($transition['row']) ? $transition['row'] : array();
			$status = isset($transition['status']) ? sanitize_key((string) $transition['status']) : '';
			vms_staffing_record_qualification_audit($staff_id, $status === 'active' ? 'approved' : 'rejected', $row, $actor_user_id);
			vms_staffing_send_staff_qualification_review_notification($staff_id, $row, $status);
		}
	}
}


if (!function_exists('vms_staffing_staff_qualification_status_counts')) {
	function vms_staffing_staff_qualification_status_counts(int $staff_id): array
	{
		$counts = array(
			'pending_verification' => 0,
			'active' => 0,
			'rejected' => 0,
			'expired' => 0,
			'inactive' => 0,
		);
		foreach (vms_staffing_get_staff_qualifications($staff_id) as $row) {
			$status = sanitize_key((string) ($row['status'] ?? 'active'));
			if ($status === '' || !array_key_exists($status, $counts)) {
				$status = 'active';
			}
			$counts[$status]++;
		}
		return $counts;
	}
}

if (!function_exists('vms_staffing_get_staff_qualification_review_items')) {
	function vms_staffing_get_staff_qualification_review_items(string $status = 'pending_verification'): array
	{
		$status = sanitize_key($status);
		if ($status === '') {
			$status = 'pending_verification';
		}
		$staff_ids = get_posts(array(
			'post_type' => 'vms_staff',
			'post_status' => array('publish', 'draft', 'pending', 'private'),
			'fields' => 'ids',
			'numberposts' => -1,
			'orderby' => 'title',
			'order' => 'ASC',
			'no_found_rows' => true,
		));
		$items = array();
		foreach ((array) $staff_ids as $staff_id) {
			$staff_id = absint($staff_id);
			foreach (vms_staffing_get_staff_qualifications($staff_id) as $row) {
				$row_status = sanitize_key((string) ($row['status'] ?? 'active'));
				if ($row_status !== $status) {
					continue;
				}
				$items[] = array(
					'staff_id' => $staff_id,
					'staff_name' => get_the_title($staff_id),
					'row' => $row,
				);
			}
		}
		return $items;
	}
}

if (!function_exists('vms_staffing_get_pending_staff_qualification_count')) {
	function vms_staffing_get_pending_staff_qualification_count(): int
	{
		return count(vms_staffing_get_staff_qualification_review_items('pending_verification'));
	}
}

if (!function_exists('vms_staffing_get_role_required_qualification_rules')) {
	function vms_staffing_get_role_required_qualification_rules(int $role_id): array
	{
		$role_meta = vms_staffing_role_meta_get($role_id);
		$rules = isset($role_meta['required_qualification_rules']) && is_array($role_meta['required_qualification_rules'])
			? $role_meta['required_qualification_rules']
			: array();
		$out = array();
		foreach ($rules as $rule) {
			if (!is_array($rule)) {
				continue;
			}
			$clean = vms_staffing_normalize_role_required_qualification_rule($rule, isset($role_meta['qualification_check_mode']) ? (string) $role_meta['qualification_check_mode'] : 'warn');
			if (!is_array($clean)) {
				continue;
			}
			$out[] = $clean;
		}
		return $out;
	}
}

if (!function_exists('vms_staffing_get_role_required_qualification_keys')) {
	function vms_staffing_get_role_required_qualification_keys(int $role_id): array
	{
		$rules = vms_staffing_get_role_required_qualification_rules($role_id);
		$keys = array();
		foreach ($rules as $rule) {
			$key = sanitize_title((string) ($rule['name'] ?? ''));
			if ($key !== '') {
				$keys[$key] = (string) ($rule['name'] ?? $key);
			}
		}
		return $keys;
	}
}

if (!function_exists('vms_staffing_staff_qualification_check_for_role')) {
	function vms_staffing_staff_qualification_check_for_role(int $staff_id, int $role_id): array
	{
		$rules = vms_staffing_get_role_required_qualification_rules($role_id);
		$role_meta = vms_staffing_role_meta_get($role_id);
		$fallback_mode = isset($role_meta['qualification_check_mode']) ? (string) $role_meta['qualification_check_mode'] : 'warn';
		if (empty($rules)) {
			return array(
				'ok' => true,
				'mode' => vms_staffing_normalize_qualification_mode($fallback_mode, 'warn'),
				'missing' => array(),
				'expired' => array(),
				'required' => array(),
				'missing_details' => array(),
				'expired_details' => array(),
			);
		}
		$qualifications = vms_staffing_get_staff_qualifications($staff_id);
		$active = array();
		$expired = array();
		foreach ($qualifications as $qual) {
			$key = isset($qual['match_key']) ? (string) $qual['match_key'] : '';
			if ($key === '') {
				continue;
			}
			$status = isset($qual['effective_status']) ? (string) $qual['effective_status'] : '';
			if ($status === 'expired') {
				$expired[$key] = (string) ($qual['name'] ?? $key);
			} elseif ($status === 'active') {
				$active[$key] = (string) ($qual['name'] ?? $key);
			}
		}
		$missing = array();
		$expired_missing = array();
		$missing_details = array();
		$expired_details = array();
		$effective_mode = 'warn';
		foreach ($rules as $rule) {
			$key = sanitize_title((string) ($rule['name'] ?? ''));
			if ($key === '' || isset($active[$key])) {
				continue;
			}
			$mode = vms_staffing_normalize_qualification_mode((string) ($rule['mode'] ?? $fallback_mode), $fallback_mode);
			if (vms_staffing_qualification_mode_rank($mode) > vms_staffing_qualification_mode_rank($effective_mode)) {
				$effective_mode = $mode;
			}
			$detail = array('name' => (string) ($rule['name'] ?? $key), 'mode' => $mode);
			if (isset($expired[$key])) {
				$expired_missing[] = $detail['name'];
				$expired_details[] = $detail;
			} else {
				$missing[] = $detail['name'];
				$missing_details[] = $detail;
			}
		}
		$has_issues = !empty($missing) || !empty($expired_missing);
		return array(
			'ok' => !$has_issues,
			'mode' => $has_issues ? $effective_mode : vms_staffing_normalize_qualification_mode($fallback_mode, 'warn'),
			'missing' => array_values($missing),
			'expired' => array_values($expired_missing),
			'required' => array_values(array_map(static function (array $rule): string { return (string) ($rule['name'] ?? ''); }, $rules)),
			'missing_details' => array_values($missing_details),
			'expired_details' => array_values($expired_details),
		);
	}
}

if (!function_exists('vms_staffing_staff_candidate_status_for_role')) {
	function vms_staffing_staff_candidate_status_for_role(int $staff_id, int $role_id): array
	{
		$role_match = vms_staffing_staff_role_match_for_role($staff_id, $role_id);
		$qualification = vms_staffing_staff_qualification_check_for_role($staff_id, $role_id);
		$hard_blocked = !empty($role_match['ok']) && empty($qualification['ok']) && ((string) ($qualification['mode'] ?? '') === 'hard_block');

		$ineligibility_reason = '';
		if (empty($role_match['ok'])) {
			$ineligibility_reason = isset($role_match['reason']) ? (string) $role_match['reason'] : __('Not marked eligible for this role.', 'backstage-venue-manager');
		} elseif ($hard_blocked) {
			$parts = array();
			if (!empty($qualification['missing'])) {
				/* translators: %s: human-readable value used in this message. */
				$parts[] = sprintf(__('missing %s', 'backstage-venue-manager'), implode(', ', array_map('strval', (array) $qualification['missing'])));
			}
			if (!empty($qualification['expired'])) {
				/* translators: %s: human-readable value used in this message. */
				$parts[] = sprintf(__('expired %s', 'backstage-venue-manager'), implode(', ', array_map('strval', (array) $qualification['expired'])));
			}
			$ineligibility_reason = !empty($parts)
				/* translators: %s: requires active qualifications. */
				? sprintf(__('Requires active qualifications: %s.', 'backstage-venue-manager'), implode('; ', $parts))
				: __('Requires an active hard-block qualification.', 'backstage-venue-manager');
		}

		return array(
			'eligible' => !empty($role_match['ok']) && !$hard_blocked,
			'role_match' => !empty($role_match['ok']),
			'role_match_source' => isset($role_match['source']) ? (string) $role_match['source'] : 'none',
			'role_match_reason' => isset($role_match['reason']) ? (string) $role_match['reason'] : '',
			'ineligibility_reason' => $ineligibility_reason,
			'qualification' => $qualification,
			'qualification_hard_blocked' => $hard_blocked,
		);
	}
}

if (!function_exists('vms_staffing_now_mysql_utc')) {
	function vms_staffing_now_mysql_utc(): string
	{
		return current_time('mysql', true);
	}
}

if (!function_exists('vms_staffing_audit_log')) {
	function vms_staffing_audit_log(string $action, ?int $event_plan_id = null, array $before = array(), array $after = array(), ?int $actor_user_id = null): void
	{
		global $wpdb;
		$table = vms_staffing_table_name('audit');
		if ($table === '') {
			return;
		}

		$action = sanitize_key($action);
		if ($action === '') {
			$action = 'unknown';
		}
		$event_plan_id = $event_plan_id !== null ? absint($event_plan_id) : null;
		$actor_user_id = $actor_user_id !== null ? absint($actor_user_id) : absint(get_current_user_id());
		if ($actor_user_id <= 0) {
			$actor_user_id = null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Staffing audit snapshots persist to a plugin-owned custom table through wpdb::insert(); no core API preserves this repository contract.
		$wpdb->insert(
			$table,
			array(
				'event_plan_id' => $event_plan_id,
				'actor_user_id' => $actor_user_id,
				'action'        => $action,
				'before_json'   => !empty($before) ? wp_json_encode($before) : null,
				'after_json'    => !empty($after) ? wp_json_encode($after) : null,
				'created_at'    => vms_staffing_now_mysql_utc(),
			),
			array('%d', '%d', '%s', '%s', '%s', '%s')
		);
	}
}

if (!function_exists('vms_staffing_get_templates')) {
	function vms_staffing_get_templates(array $filters = array()): array
	{
		global $wpdb;
		$t = vms_staffing_table_name('templates');
		if ($t === '') return array();

		$is_active_filter = array_key_exists('is_active', $filters) ? (!empty($filters['is_active']) ? 1 : 0) : -1;
		$auto_apply_filter = array_key_exists('auto_apply', $filters) ? (!empty($filters['auto_apply']) ? 1 : 0) : -1;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Staffing template lists read a custom repository with %i/%d-prepared identifiers and filters, and template edits must remain immediately visible without a persistent cache layer.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE (%d = -1 OR is_active = %d) AND (%d = -1 OR auto_apply_on_event_create = %d) ORDER BY priority DESC, template_id ASC',
				$t,
				$is_active_filter,
				$is_active_filter,
				$auto_apply_filter,
				$auto_apply_filter
			),
			ARRAY_A
		);
		return is_array($rows) ? $rows : array();
	}
}

if (!function_exists('vms_staffing_get_template')) {
	function vms_staffing_get_template(int $template_id): ?array
	{
		global $wpdb;
		$template_id = absint($template_id);
		if ($template_id <= 0) return null;

		$t = vms_staffing_table_name('templates');
		if ($t === '') return null;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Staffing template reads target a custom repository table with %i/%d-prepared identifiers and IDs, and template edits must remain immediately visible without a persistent cache layer.
		$row = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE template_id = %d', $t, $template_id), ARRAY_A);
		if (!is_array($row)) return null;

		$row['slots'] = vms_staffing_get_template_slots($template_id);
		return $row;
	}
}

if (!function_exists('vms_staffing_delete_template')) {
	function vms_staffing_delete_template(int $template_id, ?int $actor_user_id = null): bool
	{
		global $wpdb;
		$template_id = absint($template_id);
		if ($template_id <= 0) return false;

		$t_tpl = vms_staffing_table_name('templates');
		$t_slot = vms_staffing_table_name('template_slots');
		if ($t_tpl === '' || $t_slot === '') return false;

		$actor_user_id = $actor_user_id !== null ? absint($actor_user_id) : absint(get_current_user_id());
		$before = vms_staffing_get_template($template_id);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Staffing template deletion clears custom child rows directly before deleting the parent repository row, and no persistent cache contract safely spans this mutation pair.
		$wpdb->delete($t_slot, array('template_id' => $template_id), array('%d'));
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Staffing template deletion removes the plugin-owned parent repository row directly; no core API preserves this two-step cascade behavior.
		$deleted = $wpdb->delete($t_tpl, array('template_id' => $template_id), array('%d'));
		if ($deleted) {
			vms_staffing_audit_log('template_delete', null, is_array($before) ? $before : array(), array('template_id' => $template_id), $actor_user_id);
			return true;
		}
		return false;
	}
}

if (!function_exists('vms_staffing_get_template_slots')) {
	function vms_staffing_get_template_slots(int $template_id): array
	{
		global $wpdb;
		$template_id = absint($template_id);
		if ($template_id <= 0) return array();

		$t = vms_staffing_table_name('template_slots');
		if ($t === '') return array();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Template-slot reads target a custom repository table with %i/%d-prepared identifiers, and template edits must remain immediately visible without a persistent cache layer.
		$rows = $wpdb->get_results(
			$wpdb->prepare('SELECT * FROM %i WHERE template_id = %d ORDER BY template_slot_id ASC', $t, $template_id),
			ARRAY_A
		);
		return is_array($rows) ? $rows : array();
	}
}

if (!function_exists('vms_staffing_template_normalize_slot_row')) {
	function vms_staffing_template_normalize_slot_row(array $row): ?array
	{
		$role_id = isset($row['role_id']) ? absint($row['role_id']) : 0;
		if ($role_id <= 0) return null;

		$base_headcount = isset($row['base_headcount']) ? absint($row['base_headcount']) : 0;
		if ($base_headcount <= 0) $base_headcount = 1;

		$activation_threshold = isset($row['activation_threshold']) && $row['activation_threshold'] !== '' ? max(0, (int) $row['activation_threshold']) : 1;

		$shift_time_mode = isset($row['shift_time_mode']) ? sanitize_key((string) $row['shift_time_mode']) : 'absolute';
		if (!in_array($shift_time_mode, array('absolute', 'relative'), true)) {
			$shift_time_mode = 'absolute';
		}

		$shift_start_local = isset($row['shift_start_local']) ? trim((string) $row['shift_start_local']) : '';
		$shift_end_local = isset($row['shift_end_local']) ? trim((string) $row['shift_end_local']) : '';
		if (!preg_match('/^\d{2}:\d{2}$/', $shift_start_local)) $shift_start_local = '';
		if (!preg_match('/^\d{2}:\d{2}$/', $shift_end_local)) $shift_end_local = '';

		$start_anchor_key = isset($row['start_anchor_key']) ? sanitize_key((string) $row['start_anchor_key']) : '';
		$end_anchor_key = isset($row['end_anchor_key']) ? sanitize_key((string) $row['end_anchor_key']) : '';
		$allowed_anchor = array('event_start', 'event_end', 'a1', 'a2', 'a3', 'a4');
		if (!in_array($start_anchor_key, $allowed_anchor, true)) $start_anchor_key = '';
		if (!in_array($end_anchor_key, $allowed_anchor, true)) $end_anchor_key = '';

		$start_offset_minutes = isset($row['start_offset_minutes']) ? (int) $row['start_offset_minutes'] : 0;
		$end_offset_minutes = isset($row['end_offset_minutes']) ? (int) $row['end_offset_minutes'] : 0;
		$duration_minutes = isset($row['duration_minutes']) && $row['duration_minutes'] !== '' ? max(0, (int) $row['duration_minutes']) : null;
		$break_minutes = isset($row['break_minutes']) ? max(0, (int) $row['break_minutes']) : 0;

		$pay_type = isset($row['pay_type']) ? sanitize_key((string) $row['pay_type']) : 'inherit_role';
		if (!in_array($pay_type, array('inherit_role', 'hourly', 'flat', 'none'), true)) {
			$pay_type = 'inherit_role';
		}

		$pay_rate = null;
		if (isset($row['pay_rate']) && $row['pay_rate'] !== '' && is_numeric($row['pay_rate'])) {
			$pay_rate = max(0, (float) $row['pay_rate']);
		}

		$notes = isset($row['notes']) ? sanitize_textarea_field((string) $row['notes']) : '';
		$is_optional = !empty($row['is_optional']) ? 1 : 0;

		return array(
			'role_id'              => $role_id,
			'base_headcount'       => $base_headcount,
			'activation_threshold' => $activation_threshold,
			'shift_time_mode'      => $shift_time_mode,
			'shift_start_local'    => ($shift_start_local === '' ? null : $shift_start_local),
			'shift_end_local'      => ($shift_end_local === '' ? null : $shift_end_local),
			'start_anchor_key'     => ($start_anchor_key === '' ? null : $start_anchor_key),
			'start_offset_minutes' => $start_offset_minutes,
			'end_anchor_key'       => ($end_anchor_key === '' ? null : $end_anchor_key),
			'end_offset_minutes'   => $end_offset_minutes,
			'duration_minutes'     => $duration_minutes,
			'break_minutes'        => $break_minutes,
			'pay_type'             => $pay_type,
			'pay_rate'             => $pay_rate,
			'notes'                => ($notes === '' ? null : $notes),
			'is_optional'          => $is_optional,
		);
	}
}

if (!function_exists('vms_staffing_save_template')) {
	function vms_staffing_save_template(array $payload, ?int $actor_user_id = null): array
	{
		global $wpdb;

		$actor_user_id = $actor_user_id !== null ? absint($actor_user_id) : absint(get_current_user_id());
		$t_tpl = vms_staffing_table_name('templates');
		$t_slot = vms_staffing_table_name('template_slots');
		if ($t_tpl === '' || $t_slot === '') {
			return array('ok' => false, 'error' => 'missing_tables');
		}
		if (!vms_staffing_ensure_template_attendance_band_schema()) {
			return array('ok' => false, 'error' => 'template_schema_missing');
		}
		if (!vms_staffing_ensure_template_slot_activation_schema()) {
			return array('ok' => false, 'error' => 'template_slot_schema_missing');
		}

		$template_id = isset($payload['template_id']) ? absint($payload['template_id']) : 0;
		$name = isset($payload['name']) ? sanitize_text_field((string) $payload['name']) : '';
		if ($name === '') {
			return array('ok' => false, 'error' => 'missing_name');
		}

		$scope_venue_id = isset($payload['scope_venue_id']) ? absint($payload['scope_venue_id']) : null;
		if ($scope_venue_id !== null && $scope_venue_id <= 0) $scope_venue_id = null;
		$scope_day_of_week = null;
		if (isset($payload['scope_day_of_week']) && $payload['scope_day_of_week'] !== '') {
			$scope_day_of_week = (int) $payload['scope_day_of_week'];
			if ($scope_day_of_week < 0 || $scope_day_of_week > 6) $scope_day_of_week = null;
		}
		$scope_event_type = isset($payload['scope_event_type']) ? sanitize_key((string) $payload['scope_event_type']) : '';
		if ($scope_event_type === '') $scope_event_type = null;

		$priority = isset($payload['priority']) ? (int) $payload['priority'] : 100;
		$is_active = !empty($payload['is_active']) ? 1 : 0;
		$auto_apply = !empty($payload['auto_apply_on_event_create']) ? 1 : 0;
		$min_headcount = null;
		if (isset($payload['min_headcount']) && $payload['min_headcount'] !== '') {
			$min_headcount = max(0, (int) $payload['min_headcount']);
		}
		$max_headcount = null;
		if (isset($payload['max_headcount']) && $payload['max_headcount'] !== '') {
			$max_headcount = max(0, (int) $payload['max_headcount']);
		}
		if ($min_headcount !== null && $max_headcount !== null && $max_headcount < $min_headcount) {
			$max_headcount = $min_headcount;
		}

		$slots_in = isset($payload['slots']) && is_array($payload['slots']) ? $payload['slots'] : array();
		$slots = array();
		foreach ($slots_in as $row) {
			if (!is_array($row)) continue;
			$n = vms_staffing_template_normalize_slot_row($row);
			if (is_array($n)) $slots[] = $n;
		}

		$now = vms_staffing_now_mysql_utc();
		if ($template_id > 0) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Staffing template updates mutate a custom repository row through wpdb::update(); no core API is equivalent and no read cache applies to this immediate write path.
			$wpdb->update(
				$t_tpl,
				array(
					'name'                       => $name,
					'scope_venue_id'             => $scope_venue_id,
					'scope_day_of_week'          => $scope_day_of_week,
					'scope_event_type'           => $scope_event_type,
					'priority'                   => $priority,
					'is_active'                  => $is_active,
					'auto_apply_on_event_create' => $auto_apply,
					'min_headcount'              => $min_headcount,
					'max_headcount'              => $max_headcount,
					'updated_at'                 => $now,
					'updated_by'                 => $actor_user_id > 0 ? $actor_user_id : null,
				),
				array('template_id' => $template_id),
				array('%s', '%d', '%d', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%d'),
				array('%d')
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Staffing template inserts persist normalized custom-table rows through wpdb::insert(); no core API preserves this repository lifecycle.
			$wpdb->insert(
				$t_tpl,
				array(
					'name'                       => $name,
					'scope_venue_id'             => $scope_venue_id,
					'scope_day_of_week'          => $scope_day_of_week,
					'scope_event_type'           => $scope_event_type,
					'priority'                   => $priority,
					'is_active'                  => $is_active,
					'auto_apply_on_event_create' => $auto_apply,
					'min_headcount'              => $min_headcount,
					'max_headcount'              => $max_headcount,
					'created_at'                 => $now,
					'created_by'                 => $actor_user_id > 0 ? $actor_user_id : null,
					'updated_at'                 => $now,
					'updated_by'                 => $actor_user_id > 0 ? $actor_user_id : null,
				),
				array('%s', '%d', '%d', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%d', '%s', '%d')
			);
			$template_id = (int) $wpdb->insert_id;
		}

		if ($template_id <= 0) {
			return array('ok' => false, 'error' => 'save_failed');
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Staffing template saves replace the ordered custom child rows directly before reinserting the normalized slot set, and no persistent cache contract safely spans this mutation batch.
		$wpdb->delete($t_slot, array('template_id' => $template_id), array('%d'));
		foreach ($slots as $slot) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Staffing template saves persist normalized custom child rows through wpdb::insert(); no core API preserves this ordered slot repository contract.
			$wpdb->insert(
				$t_slot,
				array(
					'template_id'          => $template_id,
					'role_id'              => (int) $slot['role_id'],
					'base_headcount'       => (int) $slot['base_headcount'],
					'activation_threshold' => isset($slot['activation_threshold']) ? max(0, (int) $slot['activation_threshold']) : 1,
					'shift_time_mode'      => (string) $slot['shift_time_mode'],
					'shift_start_local'    => $slot['shift_start_local'],
					'shift_end_local'      => $slot['shift_end_local'],
					'start_anchor_key'     => $slot['start_anchor_key'],
					'start_offset_minutes' => (int) $slot['start_offset_minutes'],
					'end_anchor_key'       => $slot['end_anchor_key'],
					'end_offset_minutes'   => (int) $slot['end_offset_minutes'],
					'duration_minutes'     => $slot['duration_minutes'] !== null ? (int) $slot['duration_minutes'] : null,
					'break_minutes'        => (int) $slot['break_minutes'],
					'pay_type'             => (string) $slot['pay_type'],
					'pay_rate'             => $slot['pay_rate'] !== null ? (float) $slot['pay_rate'] : null,
					'notes'                => $slot['notes'],
					'is_optional'          => (int) $slot['is_optional'],
					'created_at'           => $now,
					'created_by'           => $actor_user_id > 0 ? $actor_user_id : null,
					'updated_at'           => $now,
					'updated_by'           => $actor_user_id > 0 ? $actor_user_id : null,
				),
				array('%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%d', '%s', '%f', '%s', '%d', '%s', '%d', '%s', '%d')
			);
		}

		vms_staffing_audit_log('template_save', null, array(), array('template_id' => $template_id, 'name' => $name, 'slot_count' => count($slots)), $actor_user_id);

		return array('ok' => true, 'template_id' => $template_id, 'slot_count' => count($slots));
	}
}

if (!function_exists('vms_staffing_pick_template_for_event')) {
	function vms_staffing_pick_template_for_event(int $venue_id, string $event_date_ymd, string $event_type = '', ?int $headcount = null): ?array
	{
		$venue_id = absint($venue_id);
		$event_date_ymd = trim($event_date_ymd);
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $event_date_ymd)) {
			return null;
		}

		$event_type = sanitize_key($event_type);
		$headcount = $headcount !== null ? max(0, (int) $headcount) : null;
		$dow = (int) wp_date('w', strtotime($event_date_ymd . ' 12:00:00'), wp_timezone());
		$templates = vms_staffing_get_templates(array('is_active' => 1, 'auto_apply' => 1));
		if (empty($templates)) {
			return null;
		}

		$candidates = array();
		foreach ($templates as $tpl) {
			if (!is_array($tpl)) continue;

			$tpl_venue = isset($tpl['scope_venue_id']) ? absint($tpl['scope_venue_id']) : 0;
			$tpl_dow = isset($tpl['scope_day_of_week']) && $tpl['scope_day_of_week'] !== null ? (int) $tpl['scope_day_of_week'] : null;
			$tpl_type = isset($tpl['scope_event_type']) ? sanitize_key((string) $tpl['scope_event_type']) : '';
			$tpl_min = (isset($tpl['min_headcount']) && $tpl['min_headcount'] !== null && $tpl['min_headcount'] !== '') ? max(0, (int) $tpl['min_headcount']) : null;
			$tpl_max = (isset($tpl['max_headcount']) && $tpl['max_headcount'] !== null && $tpl['max_headcount'] !== '') ? max(0, (int) $tpl['max_headcount']) : null;

			if ($tpl_venue > 0 && $tpl_venue !== $venue_id) {
				continue;
			}
			if ($tpl_dow !== null && $tpl_dow !== $dow) {
				continue;
			}
			if ($tpl_type !== '' && $tpl_type !== $event_type) {
				continue;
			}
			if ($headcount !== null) {
				if ($tpl_min !== null && $headcount < $tpl_min) {
					continue;
				}
				if ($tpl_max !== null && $headcount > $tpl_max) {
					continue;
				}
			}

			$score = 0;
			if ($tpl_venue > 0) $score += 4;
			if ($tpl_dow !== null) $score += 2;
			if ($tpl_type !== '') $score += 1;
			if ($tpl_min !== null || $tpl_max !== null) $score += 1;

			$tpl['__score'] = $score;
			$candidates[] = $tpl;
		}

		if (empty($candidates)) {
			return null;
		}

		usort($candidates, function ($a, $b) {
			$pa = isset($a['priority']) ? (int) $a['priority'] : 0;
			$pb = isset($b['priority']) ? (int) $b['priority'] : 0;
			if ($pa !== $pb) return ($pa > $pb) ? -1 : 1;

			$sa = isset($a['__score']) ? (int) $a['__score'] : 0;
			$sb = isset($b['__score']) ? (int) $b['__score'] : 0;
			if ($sa !== $sb) return ($sa > $sb) ? -1 : 1;

			$ia = isset($a['template_id']) ? (int) $a['template_id'] : 0;
			$ib = isset($b['template_id']) ? (int) $b['template_id'] : 0;
			if ($ia === $ib) return 0;
			return ($ia < $ib) ? -1 : 1;
		});

		return $candidates[0];
	}
}

if (!function_exists('vms_staffing_get_applied_template_meta_key')) {
	function vms_staffing_get_applied_template_meta_key(): string
	{
		return '_vms_staffing_template_applied';
	}
}

if (!function_exists('vms_staffing_get_event_applied_template_id')) {
	function vms_staffing_get_event_applied_template_id(int $event_plan_id): int
	{
		$event_plan_id = absint($event_plan_id);
		if ($event_plan_id <= 0) {
			return 0;
		}
		return absint(get_post_meta($event_plan_id, vms_staffing_get_applied_template_meta_key(), true));
	}
}

if (!function_exists('vms_staffing_set_event_applied_template_id')) {
	function vms_staffing_set_event_applied_template_id(int $event_plan_id, int $template_id, string $mode = 'auto'): void
	{
		$event_plan_id = absint($event_plan_id);
		$template_id = absint($template_id);
		if ($event_plan_id <= 0) {
			return;
		}
		if ($template_id <= 0) {
			delete_post_meta($event_plan_id, vms_staffing_get_applied_template_meta_key());
			delete_post_meta($event_plan_id, '_vms_staffing_template_applied_mode');
			return;
		}
		update_post_meta($event_plan_id, vms_staffing_get_applied_template_meta_key(), $template_id);
		update_post_meta($event_plan_id, '_vms_staffing_template_applied_mode', sanitize_key($mode));
	}
}

if (!function_exists('vms_staffing_get_recommended_template_for_event_plan')) {
	function vms_staffing_get_recommended_template_for_event_plan(int $event_plan_id): ?array
	{
		$event_plan_id = absint($event_plan_id);
		if ($event_plan_id <= 0) {
			return null;
		}
		$venue_id = absint(get_post_meta($event_plan_id, '_vms_venue_id', true));
		$event_date = (string) get_post_meta($event_plan_id, '_vms_event_date', true);
		$event_type = vms_staffing_pick_template_event_type($event_plan_id);
		$headcount_ctx = vms_staffing_get_event_plan_headcount_context($event_plan_id);
		$headcount = isset($headcount_ctx['headcount']) ? max(0, (int) $headcount_ctx['headcount']) : 0;
		if ($venue_id <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $event_date)) {
			return null;
		}
		return vms_staffing_pick_template_for_event($venue_id, $event_date, $event_type, $headcount);
	}
}

if (!function_exists('vms_staffing_apply_template_to_event')) {
	function vms_staffing_apply_template_to_event(int $event_plan_id, int $template_id, string $mode = 'merge_missing', ?int $actor_user_id = null): array
	{
		global $wpdb;
		$event_plan_id = absint($event_plan_id);
		$template_id = absint($template_id);
		$mode = sanitize_key($mode);
		if (!in_array($mode, array('merge_missing', 'replace_all'), true)) {
			$mode = 'merge_missing';
		}
		if ($event_plan_id <= 0 || $template_id <= 0) {
			return array('ok' => false, 'error' => 'invalid_context');
		}
		$t_slot = vms_staffing_table_name('event_slots');
		$t_asn = vms_staffing_table_name('assignments');
		if ($t_slot === '' || $t_asn === '') {
			return array('ok' => false, 'error' => 'missing_table');
		}
		$actor_user_id = $actor_user_id !== null ? absint($actor_user_id) : absint(get_current_user_id());
		$template = vms_staffing_get_template($template_id);
		if (!is_array($template)) {
			return array('ok' => false, 'error' => 'missing_template');
		}
		$tpl_slots = isset($template['slots']) && is_array($template['slots']) ? $template['slots'] : array();

		$existing_slots = bvmgr_staffing_get_event_slots($event_plan_id, true);
		$existing_by_role = array();
		foreach ($existing_slots as $slot) {
			if (!is_array($slot)) continue;
			$rid = isset($slot['role_id']) ? absint($slot['role_id']) : 0;
			$status = isset($slot['status']) ? sanitize_key((string) $slot['status']) : 'active';
			if ($rid > 0 && $status === 'active') {
				$existing_by_role[$rid] = $slot;
			}
		}

		$thresholds = function_exists('vms_staffing_get_event_role_activation_thresholds')
			? vms_staffing_get_event_role_activation_thresholds($event_plan_id)
			: array();
		if (!is_array($thresholds)) {
			$thresholds = array();
		}

		if ($mode === 'replace_all') {
			foreach ($existing_slots as $slot) {
				$slot_id = isset($slot['slot_id']) ? absint($slot['slot_id']) : 0;
				if ($slot_id <= 0) continue;
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Template replacement cancels active assignment rows in the plugin-owned repository before reseeding slots, and no persistent cache contract safely spans this mutation batch.
				$wpdb->query($wpdb->prepare(
					"UPDATE %i SET status = 'canceled', updated_at = %s, updated_by = %d WHERE slot_id = %d AND status IN ('proposed','confirmed')",
					$t_asn,
					vms_staffing_now_mysql_utc(),
					$actor_user_id > 0 ? $actor_user_id : 0,
					$slot_id
				));
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Template replacement clears the existing event-slot repository rows directly before reseeding the normalized set, and no persistent cache contract safely spans this mutation batch.
			$wpdb->delete($t_slot, array('event_plan_id' => $event_plan_id), array('%d'));
			$existing_by_role = array();
			$thresholds = array();
		}

		$now = vms_staffing_now_mysql_utc();
		$seeded = 0;
		$skipped = 0;
		foreach ($tpl_slots as $row) {
			if (!is_array($row)) continue;
			$role_id = isset($row['role_id']) ? absint($row['role_id']) : 0;
			$headcount = isset($row['base_headcount']) ? max(0, (int) $row['base_headcount']) : 0;
			$activation_threshold = isset($row['activation_threshold']) && $row['activation_threshold'] !== null && $row['activation_threshold'] !== ''
				? max(0, (int) $row['activation_threshold'])
				: 1;
			if ($role_id <= 0 || $headcount <= 0) continue;
			if ($mode === 'merge_missing' && isset($existing_by_role[$role_id])) {
				$skipped++;
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Template application persists normalized event-slot rows through wpdb::insert(); no core API preserves this staffing repository lifecycle.
			$wpdb->insert(
				$t_slot,
				array(
					'event_plan_id'         => $event_plan_id,
					'role_id'               => $role_id,
					'headcount_needed'      => $headcount,
					'shift_time_mode'       => isset($row['shift_time_mode']) ? (string) $row['shift_time_mode'] : 'absolute',
					'shift_start_local'     => isset($row['shift_start_local']) ? $row['shift_start_local'] : null,
					'shift_end_local'       => isset($row['shift_end_local']) ? $row['shift_end_local'] : null,
					'start_anchor_key'      => isset($row['start_anchor_key']) ? $row['start_anchor_key'] : null,
					'start_offset_minutes'  => isset($row['start_offset_minutes']) ? (int) $row['start_offset_minutes'] : 0,
					'end_anchor_key'        => isset($row['end_anchor_key']) ? $row['end_anchor_key'] : null,
					'end_offset_minutes'    => isset($row['end_offset_minutes']) ? (int) $row['end_offset_minutes'] : 0,
					'duration_minutes'      => isset($row['duration_minutes']) && $row['duration_minutes'] !== null ? (int) $row['duration_minutes'] : null,
					'break_minutes'         => isset($row['break_minutes']) ? (int) $row['break_minutes'] : 0,
					'pay_type'              => isset($row['pay_type']) ? (string) $row['pay_type'] : 'inherit_role',
					'pay_rate'              => isset($row['pay_rate']) && $row['pay_rate'] !== null ? (float) $row['pay_rate'] : null,
					'notes'                 => isset($row['notes']) ? $row['notes'] : null,
					'status'                => 'active',
					'created_at'            => $now,
					'created_by'            => $actor_user_id > 0 ? $actor_user_id : null,
					'updated_at'            => $now,
					'updated_by'            => $actor_user_id > 0 ? $actor_user_id : null,
				),
				array('%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%d', '%s', '%f', '%s', '%s', '%s', '%d', '%s', '%d')
			);
			$thresholds[$role_id] = $activation_threshold;
			$seeded++;
		}

		if (function_exists('vms_staffing_set_event_role_activation_thresholds')) {
			vms_staffing_set_event_role_activation_thresholds($event_plan_id, $thresholds);
		}
		vms_staffing_set_event_applied_template_id($event_plan_id, $template_id, $mode === 'replace_all' ? 'manual_replace' : 'manual_merge');
		vms_staffing_mark_rollup_dirty($event_plan_id, 'apply_template');
		vms_staffing_compute_rollup($event_plan_id);
		vms_staffing_audit_log('apply_template', $event_plan_id, array(), array('template_id' => $template_id, 'seeded' => $seeded, 'skipped' => $skipped, 'mode' => $mode), $actor_user_id);

		return array('ok' => true, 'template_id' => $template_id, 'seeded' => $seeded, 'skipped' => $skipped, 'mode' => $mode);
	}
}

if (!function_exists('vms_staffing_event_plan_datetime')) {
	function vms_staffing_event_plan_datetime(int $event_plan_id): array
	{
		$event_plan_id = absint($event_plan_id);
		$ymd = (string) get_post_meta($event_plan_id, '_vms_event_date', true);
		$start_hhmm = (string) get_post_meta($event_plan_id, '_vms_start_time', true);
		$end_hhmm = (string) get_post_meta($event_plan_id, '_vms_end_time', true);

		$tz = wp_timezone();
		$start_local = null;
		$end_local = null;
		if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) {
			if (preg_match('/^\d{2}:\d{2}$/', $start_hhmm)) {
				$start_local = DateTimeImmutable::createFromFormat('Y-m-d H:i', $ymd . ' ' . $start_hhmm, $tz);
			}
			if (preg_match('/^\d{2}:\d{2}$/', $end_hhmm)) {
				$end_local = DateTimeImmutable::createFromFormat('Y-m-d H:i', $ymd . ' ' . $end_hhmm, $tz);
			}
		}

		return array(
			'event_date_ymd' => $ymd,
			'start_hhmm'     => $start_hhmm,
			'end_hhmm'       => $end_hhmm,
			'start_local'    => $start_local instanceof DateTimeImmutable ? $start_local : null,
			'end_local'      => $end_local instanceof DateTimeImmutable ? $end_local : null,
		);
	}
}

if (!function_exists('vms_staffing_resolve_anchor_local')) {
	function vms_staffing_resolve_anchor_local(int $event_plan_id, string $anchor_key): ?DateTimeImmutable
	{
		$anchor_key = sanitize_key($anchor_key);
		$dt = vms_staffing_event_plan_datetime($event_plan_id);

		if ($anchor_key === 'event_start') {
			return $dt['start_local'] instanceof DateTimeImmutable ? $dt['start_local'] : null;
		}
		if ($anchor_key === 'event_end') {
			return $dt['end_local'] instanceof DateTimeImmutable ? $dt['end_local'] : null;
		}

		if (!in_array($anchor_key, array('a1', 'a2', 'a3', 'a4'), true)) {
			return null;
		}
		$ymd = (string) $dt['event_date_ymd'];
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) {
			return null;
		}

		$k = '_vms_event_' . $anchor_key . '_time';
		$hhmm = (string) get_post_meta($event_plan_id, $k, true);
		if (!preg_match('/^\d{2}:\d{2}$/', $hhmm)) {
			return null;
		}
		$local = DateTimeImmutable::createFromFormat('Y-m-d H:i', $ymd . ' ' . $hhmm, wp_timezone());
		return $local instanceof DateTimeImmutable ? $local : null;
	}
}

if (!function_exists('bvmgr_staffing_resolve_slot_window')) {
	function bvmgr_staffing_resolve_slot_window(int $event_plan_id, array $slot): array
	{
		$event_plan_id = absint($event_plan_id);
		if ($event_plan_id <= 0) {
			return array('start_local' => null, 'end_local' => null, 'start_ts' => null, 'end_ts' => null, 'duration_minutes' => null);
		}

		$mode = isset($slot['shift_time_mode']) ? sanitize_key((string) $slot['shift_time_mode']) : 'absolute';
		if (!in_array($mode, array('absolute', 'relative'), true)) {
			$mode = 'absolute';
		}

		$dt = vms_staffing_event_plan_datetime($event_plan_id);
		$ymd = (string) $dt['event_date_ymd'];
		$tz = wp_timezone();

		$start_local = null;
		$end_local = null;
		$duration = isset($slot['duration_minutes']) && $slot['duration_minutes'] !== null ? (int) $slot['duration_minutes'] : null;
		if ($mode === 'absolute' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) {
			$sh = isset($slot['shift_start_local']) ? trim((string) $slot['shift_start_local']) : '';
			$eh = isset($slot['shift_end_local']) ? trim((string) $slot['shift_end_local']) : '';
			if (preg_match('/^\d{2}:\d{2}$/', $sh)) {
				$start_local = DateTimeImmutable::createFromFormat('Y-m-d H:i', $ymd . ' ' . $sh, $tz);
			}
			if ($start_local instanceof DateTimeImmutable && $duration !== null && $duration > 0) {
				$end_local = $start_local->modify('+' . $duration . ' minutes');
			} elseif (preg_match('/^\d{2}:\d{2}$/', $eh)) {
				$end_local = DateTimeImmutable::createFromFormat('Y-m-d H:i', $ymd . ' ' . $eh, $tz);
			}
		} else {
			$start_anchor_key = isset($slot['start_anchor_key']) ? sanitize_key((string) $slot['start_anchor_key']) : '';
			$start_offset = isset($slot['start_offset_minutes']) ? (int) $slot['start_offset_minutes'] : 0;
			$start_anchor = vms_staffing_resolve_anchor_local($event_plan_id, $start_anchor_key);
			if (!$start_anchor instanceof DateTimeImmutable) {
				$start_anchor = $dt['start_local'] instanceof DateTimeImmutable ? $dt['start_local'] : null;
			}
			if ($start_anchor instanceof DateTimeImmutable) {
				$start_local = $start_anchor->modify(($start_offset >= 0 ? '+' : '') . $start_offset . ' minutes');
			}

			$end_anchor_key = isset($slot['end_anchor_key']) ? sanitize_key((string) $slot['end_anchor_key']) : '';
			$end_offset = isset($slot['end_offset_minutes']) ? (int) $slot['end_offset_minutes'] : 0;

			if ($start_local instanceof DateTimeImmutable && $duration !== null && $duration > 0) {
				$end_local = $start_local->modify('+' . $duration . ' minutes');
			} else {
				$end_anchor = vms_staffing_resolve_anchor_local($event_plan_id, $end_anchor_key);
				if (!$end_anchor instanceof DateTimeImmutable && $dt['end_local'] instanceof DateTimeImmutable) {
					$end_anchor = $dt['end_local'];
				}
				if ($end_anchor instanceof DateTimeImmutable) {
					$end_local = $end_anchor->modify(($end_offset >= 0 ? '+' : '') . $end_offset . ' minutes');
				}
			}
		}

		if (!$start_local instanceof DateTimeImmutable && $dt['start_local'] instanceof DateTimeImmutable) {
			$start_local = $dt['start_local'];
		}
		if (!$end_local instanceof DateTimeImmutable) {
			if ($dt['end_local'] instanceof DateTimeImmutable) {
				$end_local = $dt['end_local'];
			} elseif ($start_local instanceof DateTimeImmutable) {
				$end_local = $start_local;
			}
		}
		if ($start_local instanceof DateTimeImmutable && $end_local instanceof DateTimeImmutable && $end_local < $start_local) {
			$end_local = $start_local;
		}

		$start_ts = $start_local instanceof DateTimeImmutable ? (int) $start_local->getTimestamp() : null;
		$end_ts = $end_local instanceof DateTimeImmutable ? (int) $end_local->getTimestamp() : null;

		$duration_minutes = null;
		if (is_int($start_ts) && is_int($end_ts)) {
			$duration_minutes = max(0, (int) floor(($end_ts - $start_ts) / 60));
		}

		return array(
			'start_local'      => $start_local,
			'end_local'        => $end_local,
			'start_ts'         => $start_ts,
			'end_ts'           => $end_ts,
			'duration_minutes' => $duration_minutes,
		);
	}
}

if (!function_exists('vms_staffing_sync_assignment_shift_timestamps_for_slot')) {
	function vms_staffing_sync_assignment_shift_timestamps_for_slot(int $slot_id): void
	{
		global $wpdb;
		$slot_id = absint($slot_id);
		if ($slot_id <= 0) return;

		$t_slot = vms_staffing_table_name('event_slots');
		$t_asn = vms_staffing_table_name('assignments');
		if ($t_slot === '' || $t_asn === '') return;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Slot timestamp sync reads one custom repository row with %i/%d-prepared identifiers and IDs, and assignment updates must observe immediate slot edits.
		$slot = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE slot_id = %d', $t_slot, $slot_id), ARRAY_A);
		if (!is_array($slot) || empty($slot['event_plan_id'])) return;

		$window = bvmgr_staffing_resolve_slot_window((int) $slot['event_plan_id'], $slot);
		$start_ts = isset($window['start_ts']) ? $window['start_ts'] : null;
		$end_ts = isset($window['end_ts']) ? $window['end_ts'] : null;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Slot timestamp sync mutates the plugin-owned assignment repository directly after recalculating one slot window, and no persistent cache contract safely spans this immediate write path.
		$wpdb->query($wpdb->prepare(
			"UPDATE %i SET shift_start_ts = %s, shift_end_ts = %s, updated_at = %s, updated_by = %d WHERE slot_id = %d AND status IN ('proposed','confirmed')",
			$t_asn,
			$start_ts !== null ? (string) (int) $start_ts : null,
			$end_ts !== null ? (string) (int) $end_ts : null,
			vms_staffing_now_mysql_utc(),
			absint(get_current_user_id()),
			$slot_id
		));
	}
}

if (!function_exists('bvmgr_staffing_get_event_slots')) {
	function bvmgr_staffing_get_event_slots(int $event_plan_id, bool $include_canceled = false): array
	{
		global $wpdb;
		$event_plan_id = absint($event_plan_id);
		if ($event_plan_id <= 0) return array();

		$t_slot = vms_staffing_table_name('event_slots');
		$t_asn = vms_staffing_table_name('assignments');
		if ($t_slot === '' || $t_asn === '') return array();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Event-slot reads target a custom repository table with %i/%d-prepared identifiers and filters, and staffing/admin flows must observe request-fresh state after slot mutations.
		$slots = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE event_plan_id = %d AND (%d = 1 OR status = %s) ORDER BY slot_id ASC",
				$t_slot,
				$event_plan_id,
				$include_canceled ? 1 : 0,
				'active'
			),
			ARRAY_A
		);
		if (!is_array($slots) || empty($slots)) return array();

		$slot_ids = array_values(array_unique(array_filter(array_map(function ($s) {
			return isset($s['slot_id']) ? absint($s['slot_id']) : 0;
		}, $slots), function ($n) {
			return $n > 0;
		})));

		$assign_by_slot = array();
		if (!empty($slot_ids)) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Event-slot enrichment reads assignment rows from a custom repository with %i/%d-prepared identifiers and bounded slot IDs, and staffing/admin flows must observe request-fresh state after assignment mutations.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE slot_id IN (' . implode(', ', array_fill(0, count($slot_ids), '%d')) . ') ORDER BY assignment_id ASC',
					array_merge(array($t_asn), $slot_ids)
				),
				ARRAY_A
			);
			if (is_array($rows)) {
				foreach ($rows as $r) {
					$sid = isset($r['slot_id']) ? absint($r['slot_id']) : 0;
					if ($sid <= 0) continue;
					if (!isset($assign_by_slot[$sid])) $assign_by_slot[$sid] = array();
					$assign_by_slot[$sid][] = $r;
				}
			}
		}

		$role_map = vms_staffing_role_map_by_id(true);
		foreach ($slots as &$s) {
			$sid = isset($s['slot_id']) ? absint($s['slot_id']) : 0;
			$rid = isset($s['role_id']) ? absint($s['role_id']) : 0;
			$s['assignments'] = isset($assign_by_slot[$sid]) ? $assign_by_slot[$sid] : array();
			$s['role_name'] = isset($role_map[$rid]['name']) ? (string) $role_map[$rid]['name'] : __('Role', 'backstage-venue-manager');
			$s['role_meta'] = isset($role_map[$rid]) ? $role_map[$rid] : array();
		}
		unset($s);

		return $slots;
	}
}

if (!function_exists('vms_staffing_get_event_assigned_staff_map')) {
	function vms_staffing_get_event_assigned_staff_map(int $event_plan_id): array
	{
		$event_plan_id = absint($event_plan_id);
		if ($event_plan_id <= 0) {
			return array();
		}

		$assigned = array();
		$slots = bvmgr_staffing_get_event_slots($event_plan_id, true);
		if (!empty($slots)) {
			foreach ($slots as $slot_row) {
				if (!is_array($slot_row)) {
					continue;
				}
				$role_id = isset($slot_row['role_id']) ? absint($slot_row['role_id']) : 0;
				if ($role_id <= 0) {
					continue;
				}
				$assignment_rows = isset($slot_row['assignments']) && is_array($slot_row['assignments']) ? $slot_row['assignments'] : array();
				foreach ($assignment_rows as $assignment_row) {
					if (!is_array($assignment_row)) {
						continue;
					}
					$status = isset($assignment_row['status']) ? sanitize_key((string) $assignment_row['status']) : '';
					if (!in_array($status, array('proposed', 'confirmed'), true)) {
						continue;
					}
					$staff_id = isset($assignment_row['staff_id']) ? absint($assignment_row['staff_id']) : 0;
					if ($staff_id <= 0) {
						continue;
					}
					if (!isset($assigned[$role_id])) {
						$assigned[$role_id] = array();
					}
					$assigned[$role_id][] = $staff_id;
				}
			}
		}

		if (empty($assigned)) {
			$legacy = get_post_meta($event_plan_id, '_vms_staff_assignments', true);
			if (is_array($legacy)) {
				foreach ($legacy as $role_id => $staff_ids) {
					$role_id = absint($role_id);
					if ($role_id <= 0 || !is_array($staff_ids)) {
						continue;
					}
					$assigned[$role_id] = array_values(array_unique(array_filter(array_map('absint', $staff_ids))));
				}
			}
		}

		foreach ($assigned as $role_id => $staff_ids) {
			$assigned[$role_id] = array_values(array_unique(array_filter(array_map('absint', (array) $staff_ids))));
		}

		return $assigned;
	}
}

if (!function_exists('vms_staffing_pick_template_event_type')) {
	function vms_staffing_pick_template_event_type(int $event_plan_id): string
	{
		$event_type = (string) get_post_meta($event_plan_id, '_vms_event_type', true);
		$event_type = sanitize_key($event_type);
		return $event_type;
	}
}

if (!function_exists('vms_staffing_seed_event_slots_from_template')) {
	function vms_staffing_seed_event_slots_from_template(int $event_plan_id, bool $force = false, ?int $actor_user_id = null): array
	{
		global $wpdb;
		$event_plan_id = absint($event_plan_id);
		if ($event_plan_id <= 0) {
			return array('ok' => false, 'error' => 'invalid_event_plan');
		}

		$t_slot = vms_staffing_table_name('event_slots');
		if ($t_slot === '') {
			return array('ok' => false, 'error' => 'missing_table');
		}

		$actor_user_id = $actor_user_id !== null ? absint($actor_user_id) : absint(get_current_user_id());
		if (!$force) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Seed gating reads the custom event-slot repository with %i/%d-prepared identifiers before inserting, and request-fresh state is required after slot mutations.
			$count_active = (int) $wpdb->get_var($wpdb->prepare(
				"SELECT COUNT(*) FROM %i WHERE event_plan_id = %d AND status = 'active'",
				$t_slot,
				$event_plan_id
			));
			if ($count_active > 0) {
				return array('ok' => true, 'seeded' => 0, 'template_id' => 0, 'skipped' => 'slots_exist');
			}
		}

		$venue_id = absint(get_post_meta($event_plan_id, '_vms_venue_id', true));
		$event_date = (string) get_post_meta($event_plan_id, '_vms_event_date', true);
		$event_type = vms_staffing_pick_template_event_type($event_plan_id);

		if ($venue_id <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $event_date)) {
			return array('ok' => true, 'seeded' => 0, 'template_id' => 0, 'skipped' => 'missing_context');
		}

		$template = vms_staffing_pick_template_for_event($venue_id, $event_date, $event_type, 0);
		if (!is_array($template) || empty($template['template_id'])) {
			return array('ok' => true, 'seeded' => 0, 'template_id' => 0, 'skipped' => 'no_template');
		}

		$template_id = absint($template['template_id']);
		$tpl_slots = vms_staffing_get_template_slots($template_id);
		if (empty($tpl_slots)) {
			return array('ok' => true, 'seeded' => 0, 'template_id' => $template_id, 'skipped' => 'template_empty');
		}

		$now = vms_staffing_now_mysql_utc();
		$seeded = 0;
		foreach ($tpl_slots as $row) {
			if (!is_array($row)) continue;
			$role_id = isset($row['role_id']) ? absint($row['role_id']) : 0;
			if ($role_id <= 0) continue;
			$headcount = isset($row['base_headcount']) ? max(0, (int) $row['base_headcount']) : 0;
			if ($headcount <= 0) continue;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Template seeding persists normalized event-slot rows through wpdb::insert(); no core API preserves this staffing repository lifecycle.
			$wpdb->insert(
				$t_slot,
				array(
					'event_plan_id'         => $event_plan_id,
					'role_id'               => $role_id,
					'headcount_needed'      => $headcount,
					'shift_time_mode'       => isset($row['shift_time_mode']) ? (string) $row['shift_time_mode'] : 'absolute',
					'shift_start_local'     => isset($row['shift_start_local']) ? $row['shift_start_local'] : null,
					'shift_end_local'       => isset($row['shift_end_local']) ? $row['shift_end_local'] : null,
					'start_anchor_key'      => isset($row['start_anchor_key']) ? $row['start_anchor_key'] : null,
					'start_offset_minutes'  => isset($row['start_offset_minutes']) ? (int) $row['start_offset_minutes'] : 0,
					'end_anchor_key'        => isset($row['end_anchor_key']) ? $row['end_anchor_key'] : null,
					'end_offset_minutes'    => isset($row['end_offset_minutes']) ? (int) $row['end_offset_minutes'] : 0,
					'duration_minutes'      => isset($row['duration_minutes']) && $row['duration_minutes'] !== null ? (int) $row['duration_minutes'] : null,
					'break_minutes'         => isset($row['break_minutes']) ? (int) $row['break_minutes'] : 0,
					'pay_type'              => isset($row['pay_type']) ? (string) $row['pay_type'] : 'inherit_role',
					'pay_rate'              => isset($row['pay_rate']) && $row['pay_rate'] !== null ? (float) $row['pay_rate'] : null,
					'notes'                 => isset($row['notes']) ? $row['notes'] : null,
					'status'                => 'active',
					'created_at'            => $now,
					'created_by'            => $actor_user_id > 0 ? $actor_user_id : null,
					'updated_at'            => $now,
					'updated_by'            => $actor_user_id > 0 ? $actor_user_id : null,
				),
				array('%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%d', '%s', '%f', '%s', '%s', '%s', '%d', '%s', '%d')
			);
			$seeded++;
		}

		vms_staffing_set_event_applied_template_id($event_plan_id, $template_id, 'auto');
		vms_staffing_mark_rollup_dirty($event_plan_id, 'seed_from_template');
		vms_staffing_compute_rollup($event_plan_id);
		vms_staffing_audit_log('seed_from_template', $event_plan_id, array(), array('template_id' => $template_id, 'seeded' => $seeded), $actor_user_id);

		return array('ok' => true, 'seeded' => $seeded, 'template_id' => $template_id);
	}
}

if (!function_exists('vms_staffing_build_legacy_staff_assignments_from_slots')) {
	function vms_staffing_build_legacy_staff_assignments_from_slots(int $event_plan_id): array
	{
		$slots = bvmgr_staffing_get_event_slots($event_plan_id, false);
		$legacy = array();
		foreach ($slots as $slot) {
			$role_id = isset($slot['role_id']) ? absint($slot['role_id']) : 0;
			if ($role_id <= 0) continue;
			if (!isset($legacy[$role_id])) $legacy[$role_id] = array();
			$assignments = isset($slot['assignments']) && is_array($slot['assignments']) ? $slot['assignments'] : array();
			foreach ($assignments as $a) {
				$status = isset($a['status']) ? sanitize_key((string) $a['status']) : '';
				if (!in_array($status, array('proposed', 'confirmed'), true)) continue;
				$staff_id = isset($a['staff_id']) ? absint($a['staff_id']) : 0;
				if ($staff_id <= 0) continue;
				$legacy[$role_id][] = $staff_id;
			}
			$legacy[$role_id] = array_values(array_unique(array_filter(array_map('absint', $legacy[$role_id]), function ($n) {
				return $n > 0;
			})));
			if (empty($legacy[$role_id])) unset($legacy[$role_id]);
		}
		return $legacy;
	}
}

if (!function_exists('vms_staffing_role_activation_threshold_meta_key')) {
	function vms_staffing_role_activation_threshold_meta_key(): string
	{
		if (function_exists('bvmgr_meta_key')) {
			$key = (string) bvmgr_meta_key('event_plan', 'staff_role_activation_thresholds');
			if ($key !== '') {
				return $key;
			}
		}
		return '_vms_staff_role_activation_thresholds';
	}
}

if (!function_exists('vms_staffing_normalize_role_activation_thresholds')) {
	function vms_staffing_normalize_role_activation_thresholds(array $raw): array
	{
		$out = array();
		foreach ($raw as $role_id => $threshold) {
			$role_id = absint($role_id);
			if ($role_id <= 0) {
				continue;
			}
			$out[$role_id] = max(0, (int) $threshold);
		}
		ksort($out, SORT_NUMERIC);
		return $out;
	}
}

if (!function_exists('vms_staffing_get_event_role_activation_thresholds')) {
	function vms_staffing_get_event_role_activation_thresholds(int $event_plan_id): array
	{
		$event_plan_id = absint($event_plan_id);
		if ($event_plan_id <= 0) {
			return array();
		}

		$raw = get_post_meta($event_plan_id, vms_staffing_role_activation_threshold_meta_key(), true);
		if (!is_array($raw)) {
			return array();
		}

		return vms_staffing_normalize_role_activation_thresholds($raw);
	}
}

if (!function_exists('vms_staffing_set_event_role_activation_thresholds')) {
	function vms_staffing_set_event_role_activation_thresholds(int $event_plan_id, array $thresholds): void
	{
		$event_plan_id = absint($event_plan_id);
		if ($event_plan_id <= 0) {
			return;
		}

		$clean = vms_staffing_normalize_role_activation_thresholds($thresholds);
		if (empty($clean)) {
			delete_post_meta($event_plan_id, vms_staffing_role_activation_threshold_meta_key());
			return;
		}

		update_post_meta($event_plan_id, vms_staffing_role_activation_threshold_meta_key(), $clean);
	}
}


if (!function_exists('vms_staffing_extract_ticket_qty')) {
	function vms_staffing_extract_ticket_qty(array $stats): array
	{
		$qty = 0;
		$resolved = false;
		if (array_key_exists('qty_sold', $stats) && is_numeric($stats['qty_sold'])) {
			$qty = max(0, (int) $stats['qty_sold']);
			$resolved = true;
		} elseif (array_key_exists('qty', $stats) && is_numeric($stats['qty'])) {
			$qty = max(0, (int) $stats['qty']);
			$resolved = true;
		}

		return array(
			'qty' => $qty,
			'resolved' => $resolved,
		);
	}
}

if (!function_exists('vms_staffing_get_event_plan_ticket_product_ids')) {
	function vms_staffing_get_event_plan_ticket_product_ids(int $event_plan_id): array
	{
		$event_plan_id = absint($event_plan_id);
		if ($event_plan_id <= 0) {
			return array();
		}

		if (function_exists('vms_vendor_portal_get_ticket_product_ids')) {
			return array_values(array_unique(array_filter(array_map('absint', (array) vms_vendor_portal_get_ticket_product_ids($event_plan_id)))));
		}

		$pids = array();
		$k_pids = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'ticket_product_ids') : '_vms_ticket_product_ids_v1';
		if ($k_pids === '') {
			$k_pids = '_vms_ticket_product_ids_v1';
		}
		$stored = get_post_meta($event_plan_id, $k_pids, true);
		if (is_array($stored)) {
			$pids = array_merge($pids, $stored);
		}

		if (function_exists('vms_ticketing_get_manual_product_ids')) {
			$pids = array_merge($pids, (array) vms_ticketing_get_manual_product_ids($event_plan_id));
		} else {
			$k_manual = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'ticket_manual_product_ids') : '_vms_ticket_manual_product_ids_v1';
			if ($k_manual === '') {
				$k_manual = '_vms_ticket_manual_product_ids_v1';
			}
			$manual = get_post_meta($event_plan_id, $k_manual, true);
			if (is_array($manual)) {
				$pids = array_merge($pids, $manual);
			}
		}

		$tec_id = 0;
		if (function_exists('vms_ticketing_b_get_linked_tec_event_id')) {
			$tec_id = (int) vms_ticketing_b_get_linked_tec_event_id($event_plan_id);
		}
		if ($tec_id <= 0) {
			$k_tec = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'tec_event_id') : '_vms_tec_event_id';
			if ($k_tec === '') {
				$k_tec = '_vms_tec_event_id';
			}
			$tec_id = (int) get_post_meta($event_plan_id, $k_tec, true);
		}

		if ($tec_id > 0) {
			if (function_exists('vms_ticketing_b_get_event_ticket_products')) {
				$pids = array_merge($pids, (array) vms_ticketing_b_get_event_ticket_products($tec_id));
			} elseif (function_exists('vms_ticketing_get_ticket_product_ids_for_tec_event')) {
				$pids = array_merge($pids, (array) vms_ticketing_get_ticket_product_ids_for_tec_event($tec_id));
			}
		}

		$pids = array_values(array_unique(array_filter(array_map('absint', $pids))));
		sort($pids, SORT_NUMERIC);
		return $pids;
	}
}

if (!function_exists('vms_staffing_get_paid_ticket_product_ids')) {
	function vms_staffing_get_paid_ticket_product_ids(array $product_ids): array
	{
		$product_ids = array_values(array_unique(array_filter(array_map('absint', $product_ids))));
		if (empty($product_ids)) {
			return array();
		}

		if (function_exists('vms_vendor_portal_get_paid_ticket_product_ids')) {
			return array_values(array_unique(array_filter(array_map('absint', (array) vms_vendor_portal_get_paid_ticket_product_ids($product_ids)))));
		}

		$filtered = array();
		foreach ($product_ids as $product_id) {
			if (function_exists('vms_vendor_portal_product_is_paid_admission') && !vms_vendor_portal_product_is_paid_admission($product_id)) {
				continue;
			}
			$filtered[] = $product_id;
		}

		$filtered = array_values(array_unique($filtered));
		sort($filtered, SORT_NUMERIC);
		return $filtered;
	}
}

if (!function_exists('vms_staffing_get_event_plan_ticket_sales_snapshot')) {
	function vms_staffing_get_event_plan_ticket_sales_snapshot(int $event_plan_id): array
	{
		$event_plan_id = absint($event_plan_id);
		$snapshot = array(
			'resolved' => false,
			'qty' => 0,
			'source' => 'none',
		);
		if ($event_plan_id <= 0) {
			return $snapshot;
		}

		if (function_exists('vms_vendor_portal_get_ticket_sales_snapshot')) {
			$raw = (array) vms_vendor_portal_get_ticket_sales_snapshot($event_plan_id);
			$qty_meta = vms_staffing_extract_ticket_qty($raw);
			$resolved = !empty($qty_meta['resolved']) || !empty($raw['ticket_product_ids']) || !empty($raw['all_ticket_product_ids']);
			return array(
				'resolved' => (bool) $resolved,
				'qty' => max(0, (int) ($qty_meta['qty'] ?? 0)),
				'source' => sanitize_key((string) ($raw['source_mode'] ?? ($raw['provider'] ?? 'ticket_sales'))),
			);
		}

		$product_ids = vms_staffing_get_paid_ticket_product_ids(vms_staffing_get_event_plan_ticket_product_ids($event_plan_id));
		if (!empty($product_ids) && function_exists('vms_ticketing_compute_stats')) {
			$live = (array) vms_ticketing_compute_stats($product_ids);
			$qty_meta = vms_staffing_extract_ticket_qty($live);
			return array(
				'resolved' => true,
				'qty' => max(0, (int) ($qty_meta['qty'] ?? 0)),
				'source' => sanitize_key((string) ($live['provider'] ?? 'ticket_sales')),
			);
		}

		$ticket_key = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'ticket_stats') : '_vms_ticket_stats_v1';
		if ($ticket_key === '') {
			$ticket_key = '_vms_ticket_stats_v1';
		}
		$raw = get_post_meta($event_plan_id, $ticket_key, true);
		if (!is_array($raw)) {
			$raw = array();
		}
		$qty_meta = vms_staffing_extract_ticket_qty($raw);
		if (!empty($qty_meta['resolved'])) {
			$snapshot['resolved'] = true;
			$snapshot['qty'] = max(0, (int) ($qty_meta['qty'] ?? 0));
			$snapshot['source'] = 'ticket_stats_cache';
		}

		return $snapshot;
	}
}

if (!function_exists('vms_staffing_get_event_plan_headcount_context')) {
	function vms_staffing_get_event_plan_headcount_context(int $event_plan_id): array
	{
		$event_plan_id = absint($event_plan_id);
		$context = array(
			'wired' => false,
			'headcount' => 0,
			'source' => 'none',
			'label' => __('Anticipated guests', 'backstage-venue-manager'),
		);
		if ($event_plan_id <= 0) {
			return $context;
		}

		$ticket_snapshot = vms_staffing_get_event_plan_ticket_sales_snapshot($event_plan_id);
		$ticket_qty = max(0, (int) ($ticket_snapshot['qty'] ?? 0));
		$ticket_resolved = !empty($ticket_snapshot['resolved']);

		$admissions_headcount = 0;
			if (function_exists('vms_admission_table_entries')) {
				global $wpdb;
				$table = vms_admission_table_entries();
				if ($wpdb && is_string($table) && $table !== '') {
					static $table_exists_cache = array();
					if (!array_key_exists($table, $table_exists_cache)) {
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- This request-local admissions table-existence probe gates the headcount fallback, and no core API or persistent cache safely reflects custom-table creation during the request.
						$table_exists_cache[$table] = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table);
					}
					if (!empty($table_exists_cache[$table])) {
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Headcount context reads the admissions entries repository with request-fresh custom-table state and a %i/%d-prepared identifier plus event filter.
						$admissions_headcount = max(0, (int) $wpdb->get_var($wpdb->prepare(
							"SELECT COALESCE(SUM(CASE WHEN status <> 'canceled' THEN party_size ELSE 0 END), 0)
							 FROM %i
							 WHERE event_plan_id = %d",
							$table,
							$event_plan_id
						)));
					}
				}
			}

		$expected_total = max(0, $ticket_qty + $admissions_headcount);
		if ($ticket_resolved || $admissions_headcount > 0) {
			$context['wired'] = true;
			$context['headcount'] = $expected_total;
			$context['source'] = 'anticipated_guests';
			$context['label'] = __('Anticipated guests', 'backstage-venue-manager');
			return $context;
		}

		$true_meta_exists = metadata_exists('post', $event_plan_id, '_vms_true_headcount');
		$comp_true_meta_exists = metadata_exists('post', $event_plan_id, '_vms_comp_headcount_true');
		$true_headcount = max(0, (int) get_post_meta($event_plan_id, '_vms_true_headcount', true));
		$comp_true_headcount = max(0, (int) get_post_meta($event_plan_id, '_vms_comp_headcount_true', true));
		$true_total = max(0, $true_headcount + $comp_true_headcount);

		if ($true_meta_exists || $comp_true_meta_exists) {
			$context['wired'] = true;
			$context['headcount'] = $true_total;
			$context['source'] = 'anticipated_guests';
			$context['label'] = __('Anticipated guests', 'backstage-venue-manager');
		}

		return $context;
	}
}

if (!function_exists('vms_staffing_matrix_signature_time')) {
	function vms_staffing_matrix_signature_time($value): ?string
	{
		$value = trim((string) $value);
		return preg_match('/^\d{2}:\d{2}$/', $value) ? $value : null;
	}
}

if (!function_exists('vms_staffing_matrix_signature_anchor')) {
	function vms_staffing_matrix_signature_anchor($value): ?string
	{
		$value = sanitize_key((string) $value);
		$allowed = array('event_start', 'event_end', 'a1', 'a2', 'a3', 'a4');
		return in_array($value, $allowed, true) ? $value : null;
	}
}

if (!function_exists('vms_staffing_matrix_signature_rate')) {
	function vms_staffing_matrix_signature_rate($value)
	{
		if ($value === null || $value === '') {
			return null;
		}
		return round((float) $value, 4);
	}
}

if (!function_exists('vms_staffing_matrix_signature_staff_ids')) {
	function vms_staffing_matrix_signature_staff_ids(array $values): array
	{
		$ids = array_values(array_unique(array_filter(array_map('absint', $values), function ($n) {
			return $n > 0;
		})));
		sort($ids, SORT_NUMERIC);
		return $ids;
	}
}

if (!function_exists('vms_staffing_matrix_signature_entry')) {
	function vms_staffing_matrix_signature_entry(
		int $role_id,
		array $meta,
		int $headcount,
		array $staff_ids,
		string $mode,
		$shift_start,
		$shift_end,
		$start_anchor,
		int $start_offset,
		$end_anchor,
		int $end_offset,
		$duration,
		$pay_type,
		$pay_rate,
		$notes
	): array {
		$role_id = absint($role_id);
		$headcount = max(0, absint($headcount));
		$staff_ids = vms_staffing_matrix_signature_staff_ids($staff_ids);
		$mode = sanitize_key($mode);
		if (!in_array($mode, array('absolute', 'relative'), true)) {
			$mode = 'absolute';
		}

		$shift_start = vms_staffing_matrix_signature_time($shift_start);
		$shift_end = vms_staffing_matrix_signature_time($shift_end);
		$start_anchor = vms_staffing_matrix_signature_anchor($start_anchor);
		$end_anchor = vms_staffing_matrix_signature_anchor($end_anchor);
		$start_offset = (int) $start_offset;
		$end_offset = (int) $end_offset;
		$duration = ($duration === null || $duration === '') ? null : max(0, (int) $duration);

		if ($mode === 'absolute') {
			$start_anchor = null;
			$end_anchor = null;
			$start_offset = 0;
			$end_offset = 0;
		}

		if ($headcount <= 0 && !empty($staff_ids)) {
			$headcount = isset($meta['default_headcount']) ? max(1, (int) $meta['default_headcount']) : 1;
		}

		$pay_type = sanitize_key((string) $pay_type);
		if (!in_array($pay_type, array('hourly', 'flat', 'none'), true)) {
			$pay_type = 'none';
		}
		$notes = trim((string) $notes);

		return array(
			'role_id'              => $role_id,
			'headcount_needed'     => $headcount,
			'staff_ids'            => $staff_ids,
			'shift_time_mode'      => $mode,
			'shift_start_local'    => $shift_start,
			'shift_end_local'      => $shift_end,
			'start_anchor_key'     => $start_anchor,
			'start_offset_minutes' => $start_offset,
			'end_anchor_key'       => $end_anchor,
			'end_offset_minutes'   => $end_offset,
			'duration_minutes'     => $duration,
			'pay_type'             => $pay_type,
			'pay_rate'             => vms_staffing_matrix_signature_rate($pay_rate),
			'notes'                => $notes !== '' ? $notes : null,
		);
	}
}

if (!function_exists('vms_staffing_desired_event_roles_matrix_signature')) {
	function vms_staffing_desired_event_roles_matrix_signature(
		array $role_ids,
		array $role_map,
		array $headcounts,
		array $assignments,
		array $time_modes,
		array $shift_starts,
		array $shift_ends,
		array $start_anchor_keys,
		array $start_offset_minutes,
		array $end_anchor_keys,
		array $end_offset_minutes,
		array $duration_minutes
	): array {
		$out = array();
		foreach ($role_ids as $role_id) {
			$role_id = absint($role_id);
			if ($role_id <= 0) {
				continue;
			}
			$meta = isset($role_map[$role_id]) && is_array($role_map[$role_id]) ? $role_map[$role_id] : array();
			$headcount = isset($headcounts[$role_id]) ? max(0, absint($headcounts[$role_id])) : 0;
			$raw_staff = isset($assignments[$role_id]) && is_array($assignments[$role_id]) ? $assignments[$role_id] : array();
			$staff_ids = vms_staffing_matrix_signature_staff_ids($raw_staff);
			if ($headcount <= 0 && empty($staff_ids)) {
				continue;
			}

			$pay_type = isset($meta['default_pay_type']) ? (string) $meta['default_pay_type'] : 'none';
			$pay_rate = isset($meta['default_rate']) && $meta['default_rate'] !== null ? $meta['default_rate'] : null;
			$notes = isset($meta['default_notes']) ? (string) $meta['default_notes'] : '';

			$out[$role_id] = vms_staffing_matrix_signature_entry(
				$role_id,
				$meta,
				$headcount,
				$staff_ids,
				isset($time_modes[$role_id]) ? (string) $time_modes[$role_id] : 'absolute',
				isset($shift_starts[$role_id]) ? (string) $shift_starts[$role_id] : null,
				isset($shift_ends[$role_id]) ? (string) $shift_ends[$role_id] : null,
				isset($start_anchor_keys[$role_id]) ? (string) $start_anchor_keys[$role_id] : null,
				array_key_exists($role_id, $start_offset_minutes) && $start_offset_minutes[$role_id] !== '' ? (int) $start_offset_minutes[$role_id] : 0,
				isset($end_anchor_keys[$role_id]) ? (string) $end_anchor_keys[$role_id] : null,
				array_key_exists($role_id, $end_offset_minutes) && $end_offset_minutes[$role_id] !== '' ? (int) $end_offset_minutes[$role_id] : 0,
				array_key_exists($role_id, $duration_minutes) && $duration_minutes[$role_id] !== '' ? (int) $duration_minutes[$role_id] : null,
				$pay_type,
				$pay_rate,
				$notes
			);
		}
		ksort($out, SORT_NUMERIC);
		return $out;
	}
}

if (!function_exists('vms_staffing_current_event_roles_matrix_signature')) {
	function vms_staffing_current_event_roles_matrix_signature(array $slots, array $role_ids): array
	{
		$managed_role_ids = array();
		foreach ($role_ids as $rid) {
			$rid = absint($rid);
			if ($rid > 0) {
				$managed_role_ids[$rid] = true;
			}
		}
		$out = array();
		foreach ($slots as $slot) {
			if (!is_array($slot)) {
				continue;
			}
			$role_id = isset($slot['role_id']) ? absint($slot['role_id']) : 0;
			if ($role_id <= 0 || empty($managed_role_ids[$role_id]) || isset($out[$role_id])) {
				continue;
			}
			$status = isset($slot['status']) ? sanitize_key((string) $slot['status']) : 'active';
			if ($status !== 'active') {
				continue;
			}

			$staff_ids = array();
			$assignments = isset($slot['assignments']) && is_array($slot['assignments']) ? $slot['assignments'] : array();
			foreach ($assignments as $assignment) {
				if (!is_array($assignment)) {
					continue;
				}
				$assignment_status = isset($assignment['status']) ? sanitize_key((string) $assignment['status']) : 'proposed';
				if (!in_array($assignment_status, array('proposed', 'confirmed'), true)) {
					continue;
				}
				$staff_id = isset($assignment['staff_id']) ? absint($assignment['staff_id']) : 0;
				if ($staff_id > 0) {
					$staff_ids[] = $staff_id;
				}
			}

			$out[$role_id] = vms_staffing_matrix_signature_entry(
				$role_id,
				array(),
				isset($slot['headcount_needed']) ? (int) $slot['headcount_needed'] : 0,
				$staff_ids,
				isset($slot['shift_time_mode']) ? (string) $slot['shift_time_mode'] : 'absolute',
				isset($slot['shift_start_local']) ? $slot['shift_start_local'] : null,
				isset($slot['shift_end_local']) ? $slot['shift_end_local'] : null,
				isset($slot['start_anchor_key']) ? $slot['start_anchor_key'] : null,
				isset($slot['start_offset_minutes']) ? (int) $slot['start_offset_minutes'] : 0,
				isset($slot['end_anchor_key']) ? $slot['end_anchor_key'] : null,
				isset($slot['end_offset_minutes']) ? (int) $slot['end_offset_minutes'] : 0,
				isset($slot['duration_minutes']) ? $slot['duration_minutes'] : null,
				isset($slot['pay_type']) ? $slot['pay_type'] : 'none',
				isset($slot['pay_rate']) ? $slot['pay_rate'] : null,
				isset($slot['notes']) ? $slot['notes'] : ''
			);
		}
		ksort($out, SORT_NUMERIC);
		return $out;
	}
}

if (!function_exists('vms_staffing_event_context_meta_keys')) {
	function vms_staffing_event_context_meta_keys(): array
	{
		return array(
			'_vms_event_date',
			'_vms_start_time',
			'_vms_end_time',
			'_vms_venue_id',
			'_vms_event_type',
		);
	}
}

if (!function_exists('vms_staffing_plan_save_request_state_get')) {
	function vms_staffing_plan_save_request_state_get(int $event_plan_id): array
	{
		$event_plan_id = absint($event_plan_id);
		if ($event_plan_id <= 0) {
			return array();
		}

		$state = $GLOBALS['bvmgr_staffing_plan_save_request_state'] ?? array();
		return is_array($state[$event_plan_id] ?? null) ? $state[$event_plan_id] : array();
	}
}

if (!function_exists('vms_staffing_plan_save_request_state_set')) {
	function vms_staffing_plan_save_request_state_set(int $event_plan_id, array $state): void
	{
		$event_plan_id = absint($event_plan_id);
		if ($event_plan_id <= 0) {
			return;
		}

		if (!isset($GLOBALS['bvmgr_staffing_plan_save_request_state']) || !is_array($GLOBALS['bvmgr_staffing_plan_save_request_state'])) {
			$GLOBALS['bvmgr_staffing_plan_save_request_state'] = array();
		}

		$GLOBALS['bvmgr_staffing_plan_save_request_state'][$event_plan_id] = $state;
	}
}

if (!function_exists('vms_staffing_plan_save_request_state_dirty_reason')) {
	function vms_staffing_plan_save_request_state_dirty_reason(array $state): string
	{
		$dirty_categories = isset($state['dirty_categories']) && is_array($state['dirty_categories'])
			? array_values(array_unique(array_filter(array_map('sanitize_key', $state['dirty_categories']))))
			: array();

		return implode(',', $dirty_categories);
	}
}

if (!function_exists('vms_staffing_plan_save_context_dirty_keys')) {
	function vms_staffing_plan_save_context_dirty_keys(): array
	{
		if (!function_exists('bvmgr_event_plan_save_profiler_active') || !bvmgr_event_plan_save_profiler_active() || !function_exists('bvmgr_event_plan_save_profiler_state')) {
			return array();
		}

		$state = bvmgr_event_plan_save_profiler_state();
		$meta_keys = is_array($state['meta_keys'] ?? null) ? $state['meta_keys'] : array();
		$dirty = array();
		foreach (vms_staffing_event_context_meta_keys() as $meta_key) {
			if (isset($meta_keys[$meta_key])) {
				$dirty[] = sanitize_key((string) $meta_key);
			}
		}

		return array_values(array_unique(array_filter($dirty)));
	}
}

if (!function_exists('vms_staffing_plan_save_dirty_categories_from_signatures')) {
	function vms_staffing_plan_save_dirty_categories_from_signatures(array $current_signature, array $desired_signature): array
	{
		$dirty = array();
		$role_ids = array_values(array_unique(array_merge(array_keys($current_signature), array_keys($desired_signature))));
		foreach ($role_ids as $role_id) {
			$role_id = absint($role_id);
			if ($role_id <= 0) {
				continue;
			}

			$current = is_array($current_signature[$role_id] ?? null) ? $current_signature[$role_id] : array();
			$desired = is_array($desired_signature[$role_id] ?? null) ? $desired_signature[$role_id] : array();
			if ($current === $desired) {
				continue;
			}

			if (($current['staff_ids'] ?? array()) !== ($desired['staff_ids'] ?? array())) {
				$dirty[] = 'staff_assignment_changed';
			}

			if ((int) ($current['headcount_needed'] ?? 0) !== (int) ($desired['headcount_needed'] ?? 0)) {
				$dirty[] = 'staff_headcount_changed';
			}

			$time_fields = array(
				'shift_time_mode',
				'shift_start_local',
				'shift_end_local',
				'start_anchor_key',
				'start_offset_minutes',
				'end_anchor_key',
				'end_offset_minutes',
				'duration_minutes',
			);
			foreach ($time_fields as $field) {
				if (($current[$field] ?? null) !== ($desired[$field] ?? null)) {
					$dirty[] = 'staff_times_changed';
					break;
				}
			}
		}

		return array_values(array_unique(array_filter(array_map('sanitize_key', $dirty))));
	}
}

if (!function_exists('vms_staffing_assess_event_plan_save_request')) {
	function vms_staffing_assess_event_plan_save_request(
		int $event_plan_id,
		array $headcounts,
		array $assignments,
		array $time_modes = array(),
		array $shift_starts = array(),
		array $shift_ends = array(),
		array $start_anchor_keys = array(),
		array $start_offset_minutes = array(),
		array $end_anchor_keys = array(),
		array $end_offset_minutes = array(),
		array $duration_minutes = array(),
		array $activation_thresholds = array(),
		bool $template_apply_now = false,
		int $template_id = 0,
		string $template_mode = 'merge_missing'
	): array {
		$event_plan_id = absint($event_plan_id);
		if ($event_plan_id <= 0) {
			return array();
		}

		$role_map = vms_staffing_role_map_by_id(true);
		$role_ids = array();
		foreach (array_keys($headcounts) as $rid) $role_ids[] = absint($rid);
		foreach (array_keys($assignments) as $rid) $role_ids[] = absint($rid);
		foreach (array_keys($time_modes) as $rid) $role_ids[] = absint($rid);
		foreach (array_keys($shift_starts) as $rid) $role_ids[] = absint($rid);
		foreach (array_keys($shift_ends) as $rid) $role_ids[] = absint($rid);
		foreach (array_keys($start_anchor_keys) as $rid) $role_ids[] = absint($rid);
		foreach (array_keys($start_offset_minutes) as $rid) $role_ids[] = absint($rid);
		foreach (array_keys($end_anchor_keys) as $rid) $role_ids[] = absint($rid);
		foreach (array_keys($end_offset_minutes) as $rid) $role_ids[] = absint($rid);
		foreach (array_keys($duration_minutes) as $rid) $role_ids[] = absint($rid);
		$role_ids = array_values(array_unique(array_filter($role_ids, static function ($role_id) {
			return $role_id > 0;
		})));

		$before_slots = bvmgr_staffing_get_event_slots($event_plan_id, true);
		$desired_signature = vms_staffing_desired_event_roles_matrix_signature(
			$role_ids,
			$role_map,
			$headcounts,
			$assignments,
			$time_modes,
			$shift_starts,
			$shift_ends,
			$start_anchor_keys,
			$start_offset_minutes,
			$end_anchor_keys,
			$end_offset_minutes,
			$duration_minutes
		);
		$current_signature = vms_staffing_current_event_roles_matrix_signature($before_slots, $role_ids);
		$current_thresholds = function_exists('vms_staffing_get_event_role_activation_thresholds')
			? vms_staffing_get_event_role_activation_thresholds($event_plan_id)
			: array();
		$desired_thresholds = function_exists('vms_staffing_normalize_role_activation_thresholds')
			? vms_staffing_normalize_role_activation_thresholds($activation_thresholds)
			: array();

		$matrix_dirty = (wp_json_encode($desired_signature) !== wp_json_encode($current_signature));
		$thresholds_dirty = (wp_json_encode($desired_thresholds) !== wp_json_encode($current_thresholds));
		$dirty_categories = $matrix_dirty
			? vms_staffing_plan_save_dirty_categories_from_signatures($current_signature, $desired_signature)
			: array();
		if ($thresholds_dirty) {
			$dirty_categories[] = 'staff_activation_threshold_changed';
		}

		$template_id = absint($template_id);
		$template_mode = sanitize_key($template_mode);
		$template_apply_requested = ($template_apply_now && $template_id > 0);
		if ($template_apply_requested) {
			$dirty_categories[] = 'staffing_template_apply_requested';
		}

		$state = array(
			'has_staffing_change' => ($matrix_dirty || $thresholds_dirty || $template_apply_requested),
			'matrix_dirty' => $matrix_dirty,
			'thresholds_dirty' => $thresholds_dirty,
			'template_apply_requested' => $template_apply_requested,
			'template_id' => $template_id,
			'template_mode' => $template_mode,
			'dirty_categories' => array_values(array_unique(array_filter(array_map('sanitize_key', $dirty_categories)))),
			'role_ids' => $role_ids,
			'role_count' => count($role_ids),
			'current_signature' => $current_signature,
			'desired_signature' => $desired_signature,
			'current_thresholds' => is_array($current_thresholds) ? $current_thresholds : array(),
			'desired_thresholds' => is_array($desired_thresholds) ? $desired_thresholds : array(),
			'before_slots' => is_array($before_slots) ? $before_slots : array(),
		);

		vms_staffing_plan_save_request_state_set($event_plan_id, $state);
		return $state;
	}
}

if (!function_exists('vms_staffing_save_event_roles_matrix')) {
	function vms_staffing_save_event_roles_matrix(
		int $event_plan_id,
		array $headcounts,
		array $assignments,
		array $time_modes = array(),
		array $shift_starts = array(),
		array $shift_ends = array(),
		array $start_anchor_keys = array(),
		array $start_offset_minutes = array(),
		array $end_anchor_keys = array(),
		array $end_offset_minutes = array(),
		array $duration_minutes = array(),
		?int $actor_user_id = null,
		array $precomputed_state = array()
	): array {
		global $wpdb;
		$event_plan_id = absint($event_plan_id);
		if ($event_plan_id <= 0) {
			return array('ok' => false, 'error' => 'invalid_event_plan');
		}

		$t_slot = vms_staffing_table_name('event_slots');
		$t_asn = vms_staffing_table_name('assignments');
		if ($t_slot === '' || $t_asn === '') {
			return array('ok' => false, 'error' => 'missing_table');
		}

		$actor_user_id = $actor_user_id !== null ? absint($actor_user_id) : absint(get_current_user_id());
		$role_map = vms_staffing_role_map_by_id(true);
		$role_ids = array();

		foreach (array_keys($headcounts) as $rid) $role_ids[] = absint($rid);
		foreach (array_keys($assignments) as $rid) $role_ids[] = absint($rid);
		foreach (array_keys($time_modes) as $rid) $role_ids[] = absint($rid);
		foreach (array_keys($shift_starts) as $rid) $role_ids[] = absint($rid);
		foreach (array_keys($shift_ends) as $rid) $role_ids[] = absint($rid);
		foreach (array_keys($start_anchor_keys) as $rid) $role_ids[] = absint($rid);
		foreach (array_keys($start_offset_minutes) as $rid) $role_ids[] = absint($rid);
		foreach (array_keys($end_anchor_keys) as $rid) $role_ids[] = absint($rid);
		foreach (array_keys($end_offset_minutes) as $rid) $role_ids[] = absint($rid);
		foreach (array_keys($duration_minutes) as $rid) $role_ids[] = absint($rid);
		$role_ids = array_values(array_unique(array_filter($role_ids, function ($n) {
			return $n > 0;
		})));

		$before = is_array($precomputed_state['before_slots'] ?? null)
			? $precomputed_state['before_slots']
			: bvmgr_staffing_get_event_slots($event_plan_id, true);
		$desired_signature = is_array($precomputed_state['desired_signature'] ?? null)
			? $precomputed_state['desired_signature']
			: vms_staffing_desired_event_roles_matrix_signature(
				$role_ids,
				$role_map,
				$headcounts,
				$assignments,
				$time_modes,
				$shift_starts,
				$shift_ends,
				$start_anchor_keys,
				$start_offset_minutes,
				$end_anchor_keys,
				$end_offset_minutes,
				$duration_minutes
			);
		$current_signature = is_array($precomputed_state['current_signature'] ?? null)
			? $precomputed_state['current_signature']
			: vms_staffing_current_event_roles_matrix_signature($before, $role_ids);

		if (wp_json_encode($desired_signature) === wp_json_encode($current_signature)) {
			if (function_exists('bvmgr_event_plan_save_profiler_note_heavy_action')) {
				bvmgr_event_plan_save_profiler_note_heavy_action('staffing_event_roles_matrix', 'skipped', 'no_changes');
			}
			return array(
				'ok'               => true,
				'noop'             => true,
				'slot_count'       => count($desired_signature),
				'assignment_count' => array_sum(array_map(function ($row) {
					return isset($row['staff_ids']) && is_array($row['staff_ids']) ? count($row['staff_ids']) : 0;
				}, $desired_signature)),
				'rollup'           => null,
			);
		}

		if (function_exists('bvmgr_event_plan_save_profiler_mark_module')) {
			bvmgr_event_plan_save_profiler_mark_module('staffing', 'event_roles_matrix_changed');
		}
			if (function_exists('bvmgr_event_plan_save_profiler_note_heavy_action')) {
				bvmgr_event_plan_save_profiler_note_heavy_action('staffing_event_roles_matrix', 'triggered', 'payload_changed');
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Matrix saves read the existing custom event-slot repository with %i/%d-prepared identifiers before comparing and mutating the request-fresh slot set.
			$existing_rows = $wpdb->get_results($wpdb->prepare(
				"SELECT * FROM %i WHERE event_plan_id = %d ORDER BY slot_id ASC",
				$t_slot,
				$event_plan_id
			), ARRAY_A);
			$existing_by_role = array();
			if (is_array($existing_rows)) {
			foreach ($existing_rows as $r) {
				$rid = isset($r['role_id']) ? absint($r['role_id']) : 0;
				if ($rid <= 0) continue;
				if (!isset($existing_by_role[$rid])) $existing_by_role[$rid] = array();
				$existing_by_role[$rid][] = $r;
			}
		}

		$now = vms_staffing_now_mysql_utc();
		$slot_count = 0;
		$assignment_count = 0;

		foreach ($role_ids as $role_id) {
			$meta = isset($role_map[$role_id]) ? $role_map[$role_id] : array();
			$headcount = isset($headcounts[$role_id]) ? max(0, absint($headcounts[$role_id])) : 0;

			$raw_staff = isset($assignments[$role_id]) && is_array($assignments[$role_id]) ? $assignments[$role_id] : array();
			$staff_ids = array_values(array_unique(array_filter(array_map('absint', $raw_staff), function ($n) {
				return $n > 0;
			})));

			$mode = isset($time_modes[$role_id]) ? sanitize_key((string) $time_modes[$role_id]) : 'absolute';
			if (!in_array($mode, array('absolute', 'relative'), true)) {
				$mode = 'absolute';
			}
			$sh = isset($shift_starts[$role_id]) ? trim((string) $shift_starts[$role_id]) : '';
			$eh = isset($shift_ends[$role_id]) ? trim((string) $shift_ends[$role_id]) : '';
			if (!preg_match('/^\d{2}:\d{2}$/', $sh)) $sh = null;
			if (!preg_match('/^\d{2}:\d{2}$/', $eh)) $eh = null;
			$start_anchor = isset($start_anchor_keys[$role_id]) ? sanitize_key((string) $start_anchor_keys[$role_id]) : '';
			$end_anchor = isset($end_anchor_keys[$role_id]) ? sanitize_key((string) $end_anchor_keys[$role_id]) : '';
			$allowed_anchor = array('event_start', 'event_end', 'a1', 'a2', 'a3', 'a4');
			if (!in_array($start_anchor, $allowed_anchor, true)) {
				$start_anchor = null;
			} else {
				$start_anchor = (string) $start_anchor;
			}
			if (!in_array($end_anchor, $allowed_anchor, true)) {
				$end_anchor = null;
			} else {
				$end_anchor = (string) $end_anchor;
			}
			$start_offset = array_key_exists($role_id, $start_offset_minutes) && $start_offset_minutes[$role_id] !== '' ? (int) $start_offset_minutes[$role_id] : 0;
			$end_offset = array_key_exists($role_id, $end_offset_minutes) && $end_offset_minutes[$role_id] !== '' ? (int) $end_offset_minutes[$role_id] : 0;
			$duration = array_key_exists($role_id, $duration_minutes) && $duration_minutes[$role_id] !== '' ? max(0, (int) $duration_minutes[$role_id]) : null;
			if ($mode === 'absolute') {
				$start_anchor = null;
				$end_anchor = null;
				$start_offset = 0;
				$end_offset = 0;
			}

				$existing = isset($existing_by_role[$role_id]) && !empty($existing_by_role[$role_id]) ? $existing_by_role[$role_id][0] : null;
				$slot_id = $existing && isset($existing['slot_id']) ? absint($existing['slot_id']) : 0;

				if ($headcount <= 0 && empty($staff_ids)) {
					if ($slot_id > 0) {
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Matrix saves cancel obsolete custom event-slot rows directly, and no persistent cache safely spans the paired slot and assignment mutations.
						$wpdb->update(
							$t_slot,
							array(
								'headcount_needed' => 0,
							'status'           => 'canceled',
							'updated_at'       => $now,
							'updated_by'       => $actor_user_id > 0 ? $actor_user_id : null,
						),
						array('slot_id' => $slot_id),
							array('%d', '%s', '%s', '%d'),
							array('%d')
						);
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Matrix saves cancel active assignment rows in the plugin-owned repository immediately after canceling an obsolete slot, and no persistent cache safely spans this mutation pair.
						$wpdb->query($wpdb->prepare(
							"UPDATE %i SET status = 'canceled', updated_at = %s, updated_by = %d WHERE slot_id = %d AND status IN ('proposed','confirmed')",
							$t_asn,
							$now,
							$actor_user_id > 0 ? $actor_user_id : 0,
							$slot_id
						));
				}
				continue;
			}

			if ($headcount <= 0) {
				$headcount = isset($meta['default_headcount']) ? max(1, (int) $meta['default_headcount']) : 1;
			}

				$pay_type = isset($meta['default_pay_type']) ? (string) $meta['default_pay_type'] : 'none';
				if (!in_array($pay_type, array('hourly', 'flat', 'none'), true)) $pay_type = 'none';
				$pay_rate = isset($meta['default_rate']) && $meta['default_rate'] !== null ? (float) $meta['default_rate'] : null;
				$notes = isset($meta['default_notes']) ? (string) $meta['default_notes'] : '';

				if ($slot_id > 0) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Matrix saves update the custom event-slot repository directly so request-local slot, legacy-meta, and rollup recompute state stay in sync.
					$wpdb->update(
						$t_slot,
						array(
							'headcount_needed'      => $headcount,
						'shift_time_mode'       => $mode,
						'shift_start_local'     => $sh,
						'shift_end_local'       => $eh,
						'start_anchor_key'      => $start_anchor,
						'start_offset_minutes'  => $start_offset,
						'end_anchor_key'        => $end_anchor,
						'end_offset_minutes'    => $end_offset,
						'duration_minutes'      => $duration,
						'pay_type'              => $pay_type,
						'pay_rate'          => $pay_rate,
						'notes'             => $notes !== '' ? $notes : null,
						'status'            => 'active',
						'updated_at'        => $now,
						'updated_by'        => $actor_user_id > 0 ? $actor_user_id : null,
					),
					array('slot_id' => $slot_id),
						array('%d', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%s', '%f', '%s', '%s', '%s', '%d'),
						array('%d')
					);
				} else {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Matrix saves insert normalized custom event-slot rows directly; no core API preserves this staffing repository lifecycle.
					$wpdb->insert(
						$t_slot,
						array(
							'event_plan_id'         => $event_plan_id,
						'role_id'               => $role_id,
						'headcount_needed'      => $headcount,
						'shift_time_mode'       => $mode,
						'shift_start_local'     => $sh,
						'shift_end_local'       => $eh,
						'start_anchor_key'      => $start_anchor,
						'start_offset_minutes'  => $start_offset,
						'end_anchor_key'        => $end_anchor,
						'end_offset_minutes'    => $end_offset,
						'duration_minutes'      => $duration,
						'pay_type'              => $pay_type,
						'pay_rate'         => $pay_rate,
						'notes'            => $notes !== '' ? $notes : null,
						'status'           => 'active',
						'created_at'       => $now,
						'created_by'       => $actor_user_id > 0 ? $actor_user_id : null,
						'updated_at'       => $now,
						'updated_by'       => $actor_user_id > 0 ? $actor_user_id : null,
					),
					array('%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%s', '%f', '%s', '%s', '%s', '%d', '%s', '%d')
				);
				$slot_id = (int) $wpdb->insert_id;
			}

				if ($slot_id <= 0) {
					continue;
				}
				$slot_count++;

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Matrix saves read current assignment rows from the custom repository with %i/%d-prepared identifiers before reconciling the normalized staff set.
				$existing_asn = $wpdb->get_results($wpdb->prepare(
					'SELECT assignment_id, staff_id, status FROM %i WHERE slot_id = %d ORDER BY assignment_id ASC',
					$t_asn,
					$slot_id
				), ARRAY_A);
				$existing_by_staff = array();
				if (is_array($existing_asn)) {
				foreach ($existing_asn as $a) {
					$sid = isset($a['staff_id']) ? absint($a['staff_id']) : 0;
					if ($sid <= 0) continue;
					if (!isset($existing_by_staff[$sid])) {
						$existing_by_staff[$sid] = $a;
					}
				}
			}

				foreach ($staff_ids as $staff_id) {
					if (isset($existing_by_staff[$staff_id])) {
						$aid = isset($existing_by_staff[$staff_id]['assignment_id']) ? absint($existing_by_staff[$staff_id]['assignment_id']) : 0;
						if ($aid > 0) {
							// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Matrix saves revive matching assignment rows directly in the plugin-owned repository so the slot reconciliation observes immediate state.
							$wpdb->update(
								$t_asn,
								array(
									'status'     => 'proposed',
								'updated_at' => $now,
								'updated_by' => $actor_user_id > 0 ? $actor_user_id : null,
							),
							array('assignment_id' => $aid),
							array('%s', '%s', '%d'),
								array('%d')
							);
						}
					} else {
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Matrix saves insert new assignment rows directly into the plugin-owned repository; no core API preserves this lifecycle.
						$wpdb->insert(
							$t_asn,
							array(
								'slot_id'     => $slot_id,
							'staff_id'    => $staff_id,
							'status'      => 'proposed',
							'created_at'  => $now,
							'created_by'  => $actor_user_id > 0 ? $actor_user_id : null,
							'updated_at'  => $now,
							'updated_by'  => $actor_user_id > 0 ? $actor_user_id : null,
						),
						array('%d', '%d', '%s', '%s', '%d', '%s', '%d')
					);
				}
				$assignment_count++;
			}

				if (!empty($existing_by_staff)) {
					foreach ($existing_by_staff as $sid => $a) {
						if (in_array((int) $sid, $staff_ids, true)) continue;
						$aid = isset($a['assignment_id']) ? absint($a['assignment_id']) : 0;
						if ($aid <= 0) continue;
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Matrix saves cancel assignment rows omitted from the desired staff set directly in the repository so downstream rollup recompute sees immediate state.
						$wpdb->update(
							$t_asn,
							array(
								'status'     => 'canceled',
							'updated_at' => $now,
							'updated_by' => $actor_user_id > 0 ? $actor_user_id : null,
						),
						array('assignment_id' => $aid),
						array('%s', '%s', '%d'),
						array('%d')
					);
				}
			}

			vms_staffing_sync_assignment_shift_timestamps_for_slot($slot_id);
		}

		$legacy = vms_staffing_build_legacy_staff_assignments_from_slots($event_plan_id);
		if (!empty($legacy)) {
			update_post_meta($event_plan_id, '_vms_staff_assignments', $legacy);
		} else {
			delete_post_meta($event_plan_id, '_vms_staff_assignments');
		}

		vms_staffing_mark_rollup_dirty($event_plan_id, 'event_staffing_saved');
		$rollup = vms_staffing_compute_rollup($event_plan_id);
		$after = bvmgr_staffing_get_event_slots($event_plan_id, true);
		vms_staffing_audit_log('event_staffing_save', $event_plan_id, array('slots' => $before), array('slots' => $after), $actor_user_id);
		do_action('vms_staffing_event_saved', $event_plan_id);

		return array(
			'ok'               => true,
			'slot_count'       => $slot_count,
			'assignment_count' => $assignment_count,
			'rollup'           => $rollup,
		);
	}
}

if (!function_exists('vms_staffing_mark_rollup_dirty')) {
	function vms_staffing_mark_rollup_dirty(int $event_plan_id, string $reason = ''): void
	{
		global $wpdb;
		$event_plan_id = absint($event_plan_id);
		if ($event_plan_id <= 0) return;

		$t = vms_staffing_table_name('rollups');
		if ($t === '') return;

		$venue_id = absint(get_post_meta($event_plan_id, '_vms_venue_id', true));
		$status = function_exists('bvmgr_event_plan_get_status') ? (string) bvmgr_event_plan_get_status($event_plan_id, 'dashboard') : 'draft';
		$status = sanitize_key($status);
		if ($status === '') $status = 'draft';

		$dt = vms_staffing_event_plan_datetime($event_plan_id);
		$event_start_local = '';
		if (isset($dt['start_local']) && $dt['start_local'] instanceof DateTimeImmutable) {
			$event_start_local = $dt['start_local']->format('Y-m-d H:i:s');
		}
		$dirty_reason = sanitize_text_field($reason);
		if ($dirty_reason === '') $dirty_reason = 'manual';

		$now = vms_staffing_now_mysql_utc();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Rollup dirty-flag writes update the plugin-owned rollups repository with a %i-prepared identifier so rebuild and dashboard flows observe immediate request-fresh state.
		$wpdb->query($wpdb->prepare(
			"INSERT INTO %i (event_plan_id, venue_id, event_status, event_start_local, dirty, dirty_reason, computed_at, calc_version)
			 VALUES (%d, %d, %s, %s, 1, %s, %s, %s)
			 ON DUPLICATE KEY UPDATE
				venue_id = VALUES(venue_id),
				event_status = VALUES(event_status),
				event_start_local = VALUES(event_start_local),
				dirty = 1,
				dirty_reason = VALUES(dirty_reason)",
			$t,
			$event_plan_id,
			$venue_id > 0 ? $venue_id : 0,
			$status,
			$event_start_local !== '' ? $event_start_local : null,
			$dirty_reason,
			$now,
			'staffing_v1'
		));
	}
}

if (!function_exists('vms_staffing_estimate_slot_cost')) {
	function vms_staffing_estimate_slot_cost(array $slot, array $role_meta, int $event_plan_id): array
	{
		$pay_type = isset($slot['pay_type']) ? sanitize_key((string) $slot['pay_type']) : 'inherit_role';
		$slot_rate = isset($slot['pay_rate']) && $slot['pay_rate'] !== null && $slot['pay_rate'] !== '' && is_numeric($slot['pay_rate'])
			? (float) $slot['pay_rate']
			: null;

		$role_pay_type = isset($role_meta['default_pay_type']) ? sanitize_key((string) $role_meta['default_pay_type']) : 'none';
		if (!in_array($role_pay_type, array('hourly', 'flat', 'none'), true)) {
			$role_pay_type = 'none';
		}
		$role_rate = isset($role_meta['default_rate']) && $role_meta['default_rate'] !== null ? (float) $role_meta['default_rate'] : null;

		if ($pay_type === 'inherit_role') {
			$pay_type = $role_pay_type;
		}
		if (!in_array($pay_type, array('hourly', 'flat', 'none'), true)) {
			$pay_type = 'none';
		}
		$pay_rate = $slot_rate !== null ? $slot_rate : $role_rate;

		$headcount = isset($slot['headcount_needed']) ? max(0, (int) $slot['headcount_needed']) : 0;
		if ($headcount <= 0) {
			return array('known' => true, 'cost' => 0.0, 'hours' => 0.0);
		}

		if ($pay_type === 'none') {
			return array('known' => true, 'cost' => 0.0, 'hours' => 0.0);
		}
		if ($pay_rate === null || $pay_rate < 0) {
			return array('known' => false, 'cost' => null, 'hours' => null);
		}

		if ($pay_type === 'flat') {
			return array('known' => true, 'cost' => (float) $pay_rate * (float) $headcount, 'hours' => 0.0);
		}

		$window = bvmgr_staffing_resolve_slot_window($event_plan_id, $slot);
		$duration_minutes = isset($window['duration_minutes']) ? $window['duration_minutes'] : null;
		if ($duration_minutes === null) {
			return array('known' => false, 'cost' => null, 'hours' => null);
		}
		$hours = max(0, (float) $duration_minutes / 60.0) * (float) $headcount;
		return array('known' => true, 'cost' => (float) $pay_rate * $hours, 'hours' => $hours);
	}
}

if (!function_exists('vms_staffing_compute_rollup')) {
	function vms_staffing_compute_rollup(int $event_plan_id): array
	{
		global $wpdb;
		$event_plan_id = absint($event_plan_id);
		if ($event_plan_id <= 0) {
			return array('ok' => false, 'error' => 'invalid_event_plan');
		}

		$t_slot = vms_staffing_table_name('event_slots');
			$t_asn = vms_staffing_table_name('assignments');
			$t_roll = vms_staffing_table_name('rollups');
			if ($t_slot === '' || $t_asn === '' || $t_roll === '') {
				return array('ok' => false, 'error' => 'missing_table');
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Rollup recompute reads the custom event-slot repository with %i/%d-prepared identifiers so staffing reports and rebuilds observe request-fresh slot state.
			$slots = $wpdb->get_results($wpdb->prepare(
				"SELECT * FROM %i WHERE event_plan_id = %d AND status = 'active' ORDER BY slot_id ASC",
				$t_slot,
				$event_plan_id
			), ARRAY_A);
			if (!is_array($slots)) $slots = array();

		$slot_ids = array_values(array_unique(array_filter(array_map(function ($r) {
			return isset($r['slot_id']) ? absint($r['slot_id']) : 0;
		}, $slots), function ($n) {
			return $n > 0;
		})));

			$assignments_by_slot = array();
			if (!empty($slot_ids)) {
				$assignment_prepare_args = array_merge(array($t_asn), $slot_ids);
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Rollup recompute reads assignment rows with a %i-prepared repository identifier and a bounded prepared IN-list so slot grouping and ordering stay exact.
				$as_rows = $wpdb->get_results(
					$wpdb->prepare(
						'SELECT * FROM %i WHERE slot_id IN (' . implode(', ', array_fill(0, count($slot_ids), '%d')) . ') ORDER BY assignment_id ASC',
						$assignment_prepare_args
					),
					ARRAY_A
				);
				if (is_array($as_rows)) {
					foreach ($as_rows as $a) {
						$sid = isset($a['slot_id']) ? absint($a['slot_id']) : 0;
						if ($sid <= 0) continue;
					if (!isset($assignments_by_slot[$sid])) $assignments_by_slot[$sid] = array();
					$assignments_by_slot[$sid][] = $a;
				}
			}
		}

		$role_map = vms_staffing_role_map_by_id(true);
		$event_date = (string) get_post_meta($event_plan_id, '_vms_event_date', true);
		$venue_id = absint(get_post_meta($event_plan_id, '_vms_venue_id', true));
		$status = function_exists('bvmgr_event_plan_get_status') ? (string) bvmgr_event_plan_get_status($event_plan_id, 'dashboard') : 'draft';
		$status = sanitize_key($status);
		if ($status === '') $status = 'draft';

		$event_dt = vms_staffing_event_plan_datetime($event_plan_id);
		$event_start_local = isset($event_dt['start_local']) && $event_dt['start_local'] instanceof DateTimeImmutable
			? $event_dt['start_local']->format('Y-m-d H:i:s')
			: null;

		$slots_total = 0;
		$headcount_needed_total = 0;
		$headcount_filled_total = 0;
		$open_headcount_total = 0;
		$open_slots_count = 0;
		$critical_slots_total = 0;
		$critical_open_headcount = 0;
		$critical_open_slots_count = 0;
		$conflict_count = 0;
		$unavailable_assigned_count = 0;
		$missing_items = array();
		$conflict_items = array();
		$cost_known = true;
		$est_labor_cost_total = 0.0;
		$est_hours_total = 0.0;

		$confirmed_windows = array();
		$active_assignment_statuses = array('proposed', 'confirmed');

		foreach ($slots as $slot) {
			$slot_id = isset($slot['slot_id']) ? absint($slot['slot_id']) : 0;
			if ($slot_id <= 0) continue;

			$role_id = isset($slot['role_id']) ? absint($slot['role_id']) : 0;
			$role_meta = isset($role_map[$role_id]) ? $role_map[$role_id] : array();
			$role_name = isset($role_meta['name']) ? (string) $role_meta['name'] : __('Role', 'backstage-venue-manager');
			$is_critical = !empty($role_meta['is_critical']);

			$need = isset($slot['headcount_needed']) ? max(0, (int) $slot['headcount_needed']) : 0;
			$slots_total++;
			$headcount_needed_total += $need;
			if ($is_critical) {
				$critical_slots_total++;
			}

			$slot_assignments = isset($assignments_by_slot[$slot_id]) ? $assignments_by_slot[$slot_id] : array();
			$filled = 0;
			foreach ($slot_assignments as $a) {
				$a_status = isset($a['status']) ? sanitize_key((string) $a['status']) : '';
				if (!in_array($a_status, $active_assignment_statuses, true)) {
					continue;
				}
				$filled++;
				if ($a_status === 'confirmed') {
					$confirmed_windows[] = array(
						'assignment_id' => isset($a['assignment_id']) ? absint($a['assignment_id']) : 0,
						'staff_id'      => isset($a['staff_id']) ? absint($a['staff_id']) : 0,
						'start_ts'      => isset($a['shift_start_ts']) && $a['shift_start_ts'] !== null ? (int) $a['shift_start_ts'] : null,
						'end_ts'        => isset($a['shift_end_ts']) && $a['shift_end_ts'] !== null ? (int) $a['shift_end_ts'] : null,
					);
				}

				$staff_id = isset($a['staff_id']) ? absint($a['staff_id']) : 0;
				if ($staff_id > 0 && preg_match('/^\d{4}-\d{2}-\d{2}$/', $event_date)) {
					$manual = get_post_meta($staff_id, '_vms_availability_manual', true);
					if (is_array($manual) && isset($manual[$event_date]) && $manual[$event_date] === 'unavailable') {
						$unavailable_assigned_count++;
						if (count($conflict_items) < 6) {
							$conflict_items[] = array(
								'type'       => 'unavailable_assigned',
								'staff_id'   => $staff_id,
								'staff_name' => (string) get_the_title($staff_id),
								/* translators: %s: human-readable value used in this message. */
								'summary'    => sprintf(__('Assigned while unavailable (%s)', 'backstage-venue-manager'), $event_date),
							);
						}
					}
				}
			}

			$headcount_filled_total += $filled;
			$open = max(0, $need - $filled);
			if ($open > 0) {
				$open_headcount_total += $open;
				$open_slots_count++;
				$window = bvmgr_staffing_resolve_slot_window($event_plan_id, $slot);
				$missing_items[] = array(
					'role_id'     => $role_id,
					'role_name'   => $role_name,
					'need'        => $need,
					'filled'      => $filled,
					'open'        => $open,
					'is_critical' => $is_critical ? 1 : 0,
					'shift'       => array(
						'start' => ($window['start_local'] instanceof DateTimeImmutable) ? $window['start_local']->format('Y-m-d H:i:s') : null,
						'end'   => ($window['end_local'] instanceof DateTimeImmutable) ? $window['end_local']->format('Y-m-d H:i:s') : null,
					),
				);
				if ($is_critical) {
					$critical_open_headcount += $open;
					$critical_open_slots_count++;
				}
			}

			$cost_row = vms_staffing_estimate_slot_cost($slot, $role_meta, $event_plan_id);
			if (empty($cost_row['known'])) {
				$cost_known = false;
			} else {
				$est_labor_cost_total += isset($cost_row['cost']) ? (float) $cost_row['cost'] : 0.0;
				$est_hours_total += isset($cost_row['hours']) ? (float) $cost_row['hours'] : 0.0;
			}
		}

		// Overlap conflicts: confirmed only.
		foreach ($confirmed_windows as $w) {
			$staff_id = isset($w['staff_id']) ? absint($w['staff_id']) : 0;
			$aid = isset($w['assignment_id']) ? absint($w['assignment_id']) : 0;
			$start_ts = isset($w['start_ts']) ? $w['start_ts'] : null;
				$end_ts = isset($w['end_ts']) ? $w['end_ts'] : null;
				if ($staff_id <= 0 || $aid <= 0 || $start_ts === null || $end_ts === null) continue;
				if ($end_ts <= $start_ts) continue;

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Rollup recompute performs a bounded custom-table overlap count across assignments and slots with %i/%d-prepared identifiers and windows, and request-fresh state is required during recompute.
				$cnt = (int) $wpdb->get_var($wpdb->prepare(
					"SELECT COUNT(*) FROM %i a
					 INNER JOIN %i s ON s.slot_id = a.slot_id
					 WHERE a.staff_id = %d
					   AND a.status = 'confirmed'
					   AND a.assignment_id <> %d
					   AND s.event_plan_id <> %d
					   AND a.shift_start_ts IS NOT NULL
					   AND a.shift_end_ts IS NOT NULL
					   AND a.shift_start_ts < %d
					   AND a.shift_end_ts > %d",
					$t_asn,
					$t_slot,
					$staff_id,
					$aid,
					$event_plan_id,
					$end_ts,
				$start_ts
			));
			if ($cnt > 0) {
				$conflict_count++;
				if (count($conflict_items) < 6) {
					$conflict_items[] = array(
						'type'       => 'overlap_conflict',
						'staff_id'   => $staff_id,
						'staff_name' => (string) get_the_title($staff_id),
						/* translators: %d: number used in this message. */
						'summary'    => sprintf(__('Overlapping confirmed assignment (%d)', 'backstage-venue-manager'), $cnt),
					);
				}
			}
		}

		usort($missing_items, function ($a, $b) {
			$oa = isset($a['open']) ? (int) $a['open'] : 0;
			$ob = isset($b['open']) ? (int) $b['open'] : 0;
			if ($oa !== $ob) return ($oa > $ob) ? -1 : 1;
			$ca = !empty($a['is_critical']) ? 1 : 0;
			$cb = !empty($b['is_critical']) ? 1 : 0;
			if ($ca !== $cb) return ($ca > $cb) ? -1 : 1;
			$na = isset($a['role_name']) ? (string) $a['role_name'] : '';
			$nb = isset($b['role_name']) ? (string) $b['role_name'] : '';
			return strcmp($na, $nb);
		});

		$red_flag_reason_mask = 0;
		if ($critical_open_headcount > 0) {
			$red_flag_reason_mask |= 1; // CRITICAL_UNFILLED
		}
		if ($conflict_count > 0) {
			$red_flag_reason_mask |= 2; // OVERLAP_CONFLICT
		}
		if ($unavailable_assigned_count > 0) {
			$red_flag_reason_mask |= 4; // UNAVAILABLE_ASSIGNED
		}

		if ($headcount_needed_total <= 0) {
			$readiness_status = 'not_applicable';
		} elseif ($red_flag_reason_mask !== 0) {
			$readiness_status = 'red_flag';
		} elseif ($open_headcount_total > 0) {
			$readiness_status = 'needs_staff';
		} else {
			$readiness_status = 'ready';
		}

		$missing_summary = array(
			'open_headcount_total' => $open_headcount_total,
			'open_slots_count'     => $open_slots_count,
			'items'                => array_slice($missing_items, 0, 3),
		);
		$conflict_summary = array(
			'conflict_count' => $conflict_count + $unavailable_assigned_count,
			'items'          => array_slice($conflict_items, 0, 3),
		);

		$calc_data = array(
			'event_plan_id'             => $event_plan_id,
			'slots_total'               => $slots_total,
			'headcount_needed_total'    => $headcount_needed_total,
			'headcount_filled_total'    => $headcount_filled_total,
			'open_headcount_total'      => $open_headcount_total,
			'critical_open_headcount'   => $critical_open_headcount,
			'critical_open_slots_count' => $critical_open_slots_count,
			'conflict_count'            => $conflict_count,
			'unavailable_assigned_count'=> $unavailable_assigned_count,
			'mask'                      => $red_flag_reason_mask,
			'readiness_status'          => $readiness_status,
			'missing_summary'           => $missing_summary,
			'conflict_summary'          => $conflict_summary,
			);
			$calc_hash = md5(wp_json_encode($calc_data));
			$computed_at = vms_staffing_now_mysql_utc();

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Rollup recompute upserts the plugin-owned rollup repository directly with a %i-prepared identifier so dashboard and rebuild reads see the freshly computed state.
			$wpdb->query($wpdb->prepare(
				"INSERT INTO %i
				 (event_plan_id, venue_id, event_status, event_start_local, slots_total, headcount_needed_total, headcount_filled_total, open_headcount_total, open_slots_count, critical_slots_total, critical_open_headcount, critical_open_slots_count, conflict_count, unavailable_assigned_count, red_flag_reason_mask, readiness_status, est_labor_cost_total, est_hours_total, missing_summary_json, conflict_summary_json, calc_version, calc_hash, computed_at, dirty, dirty_reason)
				 VALUES (%d, %d, %s, %s, %d, %d, %d, %d, %d, %d, %d, %d, %d, %d, %d, %s, %s, %s, %s, %s, %s, %s, %s, 0, %s)
				 ON DUPLICATE KEY UPDATE
					venue_id = VALUES(venue_id),
				event_status = VALUES(event_status),
				event_start_local = VALUES(event_start_local),
				slots_total = VALUES(slots_total),
				headcount_needed_total = VALUES(headcount_needed_total),
				headcount_filled_total = VALUES(headcount_filled_total),
				open_headcount_total = VALUES(open_headcount_total),
				open_slots_count = VALUES(open_slots_count),
				critical_slots_total = VALUES(critical_slots_total),
				critical_open_headcount = VALUES(critical_open_headcount),
				critical_open_slots_count = VALUES(critical_open_slots_count),
				conflict_count = VALUES(conflict_count),
				unavailable_assigned_count = VALUES(unavailable_assigned_count),
				red_flag_reason_mask = VALUES(red_flag_reason_mask),
				readiness_status = VALUES(readiness_status),
				est_labor_cost_total = VALUES(est_labor_cost_total),
				est_hours_total = VALUES(est_hours_total),
				missing_summary_json = VALUES(missing_summary_json),
				conflict_summary_json = VALUES(conflict_summary_json),
				calc_version = VALUES(calc_version),
				calc_hash = VALUES(calc_hash),
					computed_at = VALUES(computed_at),
					dirty = 0,
					dirty_reason = ''",
				$t_roll,
				$event_plan_id,
				$venue_id > 0 ? $venue_id : 0,
				$status,
				$event_start_local,
			$slots_total,
			$headcount_needed_total,
			$headcount_filled_total,
			$open_headcount_total,
			$open_slots_count,
			$critical_slots_total,
			$critical_open_headcount,
			$critical_open_slots_count,
			$conflict_count,
			$unavailable_assigned_count,
			$red_flag_reason_mask,
			$readiness_status,
			$cost_known ? number_format($est_labor_cost_total, 2, '.', '') : null,
			$cost_known ? number_format($est_hours_total, 2, '.', '') : null,
			wp_json_encode($missing_summary),
			wp_json_encode($conflict_summary),
			'staffing_v1',
			$calc_hash,
			$computed_at,
			''
		));

		return array(
			'ok'                        => true,
			'event_plan_id'             => $event_plan_id,
			'slots_total'               => $slots_total,
			'headcount_needed_total'    => $headcount_needed_total,
			'headcount_filled_total'    => $headcount_filled_total,
			'open_headcount_total'      => $open_headcount_total,
			'open_slots_count'          => $open_slots_count,
			'critical_open_headcount'   => $critical_open_headcount,
			'critical_open_slots_count' => $critical_open_slots_count,
			'conflict_count'            => $conflict_count,
			'unavailable_assigned_count'=> $unavailable_assigned_count,
			'red_flag_reason_mask'      => $red_flag_reason_mask,
			'readiness_status'          => $readiness_status,
			'est_labor_cost_total'      => $cost_known ? (float) $est_labor_cost_total : null,
			'est_hours_total'           => $cost_known ? (float) $est_hours_total : null,
			'missing_summary'           => $missing_summary,
			'conflict_summary'          => $conflict_summary,
		);
	}
}

if (!function_exists('vms_staffing_get_rollup')) {
	function vms_staffing_get_rollup(int $event_plan_id): ?array
	{
			global $wpdb;
			$event_plan_id = absint($event_plan_id);
			if ($event_plan_id <= 0) return null;
			$t = vms_staffing_table_name('rollups');
			if ($t === '') return null;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Single rollup reads target the custom repository with a %i/%d-prepared identifier and event key, and admin/reporting flows must observe request-fresh state after rebuilds.
			$row = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE event_plan_id = %d', $t, $event_plan_id), ARRAY_A);
			return is_array($row) ? $row : null;
		}
	}

if (!function_exists('vms_staffing_dashboard_readiness_label')) {
	function vms_staffing_dashboard_readiness_label(string $status): string
	{
		$status = sanitize_key($status);
		if ($status === 'ready') return __('Ready', 'backstage-venue-manager');
		if ($status === 'needs_staff') return __('Needs Staff', 'backstage-venue-manager');
		if ($status === 'red_flag') return __('Red Flag', 'backstage-venue-manager');
		return __('N/A', 'backstage-venue-manager');
	}
}

if (!function_exists('vms_staffing_build_dashboard_response')) {
	function vms_staffing_build_dashboard_response(array $args = array()): array
	{
		$n = isset($args['staffing_n']) ? absint($args['staffing_n']) : 10;
		if (!in_array($n, array(5, 10, 20), true)) $n = 10;
		$venue_id = isset($args['venue_id']) ? (string) $args['venue_id'] : 'all';
		$include_drafts = !empty($args['include_drafts']);

		$today = wp_date('Y-m-d');
		$venue_filter = 0;
		if ($venue_id !== 'all') {
			$venue_filter = absint($venue_id);
		}

		global $wpdb;
		$candidate_ids = array();
		$t_posts = (is_object($wpdb) && isset($wpdb->posts) && is_string($wpdb->posts) && $wpdb->posts !== '') ? $wpdb->posts : '';
		$t_postmeta = (is_object($wpdb) && isset($wpdb->postmeta) && is_string($wpdb->postmeta) && $wpdb->postmeta !== '') ? $wpdb->postmeta : '';
		if ($t_posts !== '' && $t_postmeta !== '' && method_exists($wpdb, 'get_col') && method_exists($wpdb, 'prepare')) {
			/* phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Staffing dashboard candidate reads query request-fresh event-date postmeta with prepared identifiers and bounded status/date filters so rebuild state reflects immediate Event Plan edits. */
			$candidate_ids = $wpdb->get_col($wpdb->prepare('SELECT p.ID FROM %i AS pm INNER JOIN %i AS p ON p.ID = pm.post_id WHERE p.post_type = %s AND p.post_status IN (%s, %s, %s, %s, %s) AND pm.meta_key = %s AND pm.meta_value >= %s ORDER BY pm.meta_value ASC, p.ID ASC LIMIT %d', $t_postmeta, $t_posts, 'vms_event_plan', 'publish', 'draft', 'pending', 'private', 'future', '_vms_event_date', $today, 120));
		}
		if (!is_array($candidate_ids)) $candidate_ids = array();

		$items = array();
		foreach ($candidate_ids as $pid_raw) {
			$plan_id = absint($pid_raw);
			if ($plan_id <= 0) continue;

			if ($venue_filter > 0) {
				$plan_venue = absint(get_post_meta($plan_id, '_vms_venue_id', true));
				if ($plan_venue !== $venue_filter) continue;
			}

			if (function_exists('bvmgr_event_plan_should_include')) {
				if (!bvmgr_event_plan_should_include($plan_id, 'dashboard', array(
					'include_drafts'    => (bool) $include_drafts,
					'include_cancelled' => false,
				))) {
					continue;
				}
			}

			$roll = vms_staffing_get_rollup($plan_id);
			if (!is_array($roll) || !empty($roll['dirty'])) {
				vms_staffing_compute_rollup($plan_id);
				$roll = vms_staffing_get_rollup($plan_id);
			}
			if (!is_array($roll)) continue;

			$missing_summary = array();
			$conflict_summary = array();
			if (!empty($roll['missing_summary_json'])) {
				$m = json_decode((string) $roll['missing_summary_json'], true);
				if (is_array($m)) $missing_summary = $m;
			}
			if (!empty($roll['conflict_summary_json'])) {
				$c = json_decode((string) $roll['conflict_summary_json'], true);
				if (is_array($c)) $conflict_summary = $c;
			}

			$event_date = (string) get_post_meta($plan_id, '_vms_event_date', true);
			$start_time = (string) get_post_meta($plan_id, '_vms_start_time', true);
			$venue_id_int = absint(get_post_meta($plan_id, '_vms_venue_id', true));
			$venue_name = $venue_id_int > 0 ? (string) get_the_title($venue_id_int) : '';

			$items[] = array(
				'plan_id'                 => $plan_id,
				'title'                   => (string) get_the_title($plan_id),
				'event_date'              => $event_date,
				'start_time'              => $start_time,
				'venue_id'                => $venue_id_int,
				'venue_name'              => $venue_name,
				'readiness_status'        => (string) ($roll['readiness_status'] ?? 'not_applicable'),
				'readiness_label'         => vms_staffing_dashboard_readiness_label((string) ($roll['readiness_status'] ?? 'not_applicable')),
				'open_headcount_total'    => (int) ($roll['open_headcount_total'] ?? 0),
				'red_flag_reason_mask'    => (int) ($roll['red_flag_reason_mask'] ?? 0),
				'est_labor_cost_total'    => ($roll['est_labor_cost_total'] !== null && $roll['est_labor_cost_total'] !== '') ? (float) $roll['est_labor_cost_total'] : null,
				'est_labor_cost_total_fmt'=> ($roll['est_labor_cost_total'] !== null && $roll['est_labor_cost_total'] !== '') ? ('$' . number_format((float) $roll['est_labor_cost_total'], 2)) : 'Unknown',
				'missing_summary'         => $missing_summary,
				'conflict_summary'        => $conflict_summary,
				'edit_link'               => get_edit_post_link($plan_id, ''),
			);

			if (count($items) >= $n) {
				break;
			}
		}

		return array(
			'range'       => 'staffing',
			'range_start' => $today . ' 00:00:00',
			'range_end'   => $today . ' 23:59:59',
			'filters'     => array(
				'venue_id'       => $venue_id,
				'include_drafts' => $include_drafts ? 1 : 0,
				'staffing_n'     => $n,
			),
			'items'       => $items,
		);
	}
}

if (!function_exists('vms_staffing_collect_rebuild_plan_ids')) {
	function vms_staffing_collect_rebuild_plan_ids(array $filters = array()): array
	{
		$start = isset($filters['start_date']) ? (string) $filters['start_date'] : '';
		$end = isset($filters['end_date']) ? (string) $filters['end_date'] : '';
		$venue_id = isset($filters['venue_id']) ? absint($filters['venue_id']) : 0;
		$include_drafts = !empty($filters['include_drafts']);
		$include_cancelled = !empty($filters['include_cancelled']);

		global $wpdb;
		$ids = array();
		$t_posts = (is_object($wpdb) && isset($wpdb->posts) && is_string($wpdb->posts) && $wpdb->posts !== '') ? $wpdb->posts : '';
		$t_postmeta = (is_object($wpdb) && isset($wpdb->postmeta) && is_string($wpdb->postmeta) && $wpdb->postmeta !== '') ? $wpdb->postmeta : '';
		if ($t_posts !== '' && $t_postmeta !== '' && method_exists($wpdb, 'get_col') && method_exists($wpdb, 'prepare')) {
			$start_filter = preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) ? $start : '';
			$end_filter = preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) ? $end : '';
			/* phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Staffing rebuild candidate reads query request-fresh event-date and optional venue postmeta with prepared identifiers and bounded filters so bulk rollup rebuilds reflect immediate Event Plan edits. */
			$ids = $wpdb->get_col($wpdb->prepare('SELECT p.ID FROM %i AS date_meta INNER JOIN %i AS p ON p.ID = date_meta.post_id LEFT JOIN %i AS venue_meta ON venue_meta.post_id = p.ID AND venue_meta.meta_key = %s WHERE p.post_type = %s AND p.post_status IN (%s, %s, %s, %s, %s) AND date_meta.meta_key = %s AND (%s = %s OR date_meta.meta_value >= %s) AND (%s = %s OR date_meta.meta_value <= %s) AND (%d = 0 OR venue_meta.meta_value = %s) ORDER BY date_meta.meta_value ASC, p.ID ASC', $t_postmeta, $t_posts, $t_postmeta, '_vms_venue_id', 'vms_event_plan', 'publish', 'draft', 'pending', 'private', 'future', '_vms_event_date', $start_filter, '', $start_filter, $end_filter, '', $end_filter, $venue_id, (string) $venue_id));
		}
		if (!is_array($ids)) $ids = array();

		$out = array();
		foreach ($ids as $pid_raw) {
			$pid = absint($pid_raw);
			if ($pid <= 0) continue;
			if (function_exists('bvmgr_event_plan_should_include')) {
				$ok = bvmgr_event_plan_should_include($pid, 'dashboard', array(
					'include_drafts'    => (bool) $include_drafts,
					'include_cancelled' => (bool) $include_cancelled,
				));
				if (!$ok) continue;
			}
			$out[] = $pid;
		}
		return array_values(array_unique(array_map('absint', $out)));
	}
}

if (!function_exists('vms_staffing_rebuild_rollups')) {
	function vms_staffing_rebuild_rollups(array $filters = array(), bool $preview = false): array
	{
		$plan_ids = vms_staffing_collect_rebuild_plan_ids($filters);
		$run_id = wp_generate_uuid4();
		$result = array(
			'run_id'        => $run_id,
			'preview'       => $preview ? 1 : 0,
			'matched_count' => count($plan_ids),
			'rebuilt_count' => 0,
			'error_count'   => 0,
			'errors'        => array(),
			'filters'       => $filters,
		);

		if ($preview) {
			return $result;
		}

		foreach ($plan_ids as $pid) {
			$resp = vms_staffing_compute_rollup((int) $pid);
			if (empty($resp['ok'])) {
				$result['error_count']++;
				$result['errors'][] = array(
					'event_plan_id' => (int) $pid,
					'error'         => isset($resp['error']) ? (string) $resp['error'] : 'unknown_error',
				);
				continue;
			}
			$result['rebuilt_count']++;
		}

		vms_staffing_audit_log(
			'rollup_rebuild_run',
			null,
			array(),
			array(
				'run_id'        => $run_id,
				'filters'       => $filters,
				'matched_count' => $result['matched_count'],
				'rebuilt_count' => $result['rebuilt_count'],
				'error_count'   => $result['error_count'],
			),
			get_current_user_id()
		);

		return $result;
	}
}

if (!function_exists('vms_staffing_seed_event_slots_queue_hook')) {
	function vms_staffing_seed_event_slots_queue_hook(): string
	{
		return 'vms_staffing_seed_event_slots_queued';
	}
}

if (!function_exists('vms_staffing_queue_seed_event_slots')) {
	function vms_staffing_queue_seed_event_slots(int $event_plan_id, int $actor_user_id = 0, string $reason = 'event_plan_save'): void
	{
		$event_plan_id = absint($event_plan_id);
		if ($event_plan_id <= 0 || get_post_type($event_plan_id) !== 'vms_event_plan') {
			return;
		}

		$trace = function_exists('bvmgr_event_plan_perf_span_start')
			? bvmgr_event_plan_perf_span_start(
				'vms_staffing_queue_seed_event_slots',
				$event_plan_id,
				array(
					'job_name' => 'staffing_seed_template',
					'reason' => $reason,
				)
			)
			: '';
		$actor_user_id = function_exists('bvmgr_event_plan_capture_actor_user_id')
			? bvmgr_event_plan_capture_actor_user_id($event_plan_id, $actor_user_id, 'staffing_seed_queue')
			: absint($actor_user_id);

		if (function_exists('bvmgr_event_plan_has_effective_tickets') && !bvmgr_event_plan_has_effective_tickets($event_plan_id)) {
			if (function_exists('bvmgr_event_plan_perf_log')) {
				bvmgr_event_plan_perf_log(
					'vms_staffing_queue_seed_event_slots',
					$event_plan_id,
					array(
						'job_name' => 'staffing_seed_template',
						'reason' => $reason,
						'actor_user_id' => $actor_user_id,
						'skipped' => 1,
						'skip_reason' => 'no_effective_tickets',
						)
					);
				bvmgr_event_plan_perf_log(
					'event_plan_staffing_queue_meta',
					$event_plan_id,
					array(
						'phase' => 'skip',
						'skip_reason' => 'no_effective_tickets',
						'reason' => sanitize_key($reason),
					)
				);
			}
			if (function_exists('bvmgr_event_plan_perf_span_finish')) {
				bvmgr_event_plan_perf_span_finish('vms_staffing_queue_seed_event_slots', $event_plan_id, $trace, array('job_name' => 'staffing_seed_template', 'reason' => $reason, 'skipped' => 1));
			}
			return;
		}

		$hook = vms_staffing_seed_event_slots_queue_hook();
		$args = array($event_plan_id);
		$already_scheduled = (bool) wp_next_scheduled($hook, $args);
		$already_locked = function_exists('bvmgr_event_plan_perf_job_has_lock')
			? bvmgr_event_plan_perf_job_has_lock('staffing_seed_template', $event_plan_id)
			: false;
			$scheduled_now = false;
			if (!$already_locked && !$already_scheduled) {
				wp_schedule_single_event(time() + 180, $hook, $args);
				$scheduled_now = true;
				if (function_exists('bvmgr_event_plan_perf_job_set_lock')) {
					bvmgr_event_plan_perf_job_set_lock('staffing_seed_template', $event_plan_id, 'pending', 20 * MINUTE_IN_SECONDS);
				}
			}

			$queue_reason = sanitize_key($reason);
			$queue_meta_skipped = false;
			$existing_queue_state = sanitize_key((string) get_post_meta($event_plan_id, '_vms_staffing_seed_queue_state', true));
			$existing_queue_reason = sanitize_key((string) get_post_meta($event_plan_id, '_vms_staffing_seed_reason', true));
			$existing_queued_at = (int) get_post_meta($event_plan_id, '_vms_staffing_seed_queued_at', true);
			if (($already_locked || $already_scheduled) && $existing_queue_state === 'queued' && $existing_queue_reason === $queue_reason && $existing_queued_at > 0) {
				$queue_meta_skipped = true;
			} else {
				update_post_meta($event_plan_id, '_vms_staffing_seed_queue_state', 'queued');
				update_post_meta($event_plan_id, '_vms_staffing_seed_queued_at', time());
				update_post_meta($event_plan_id, '_vms_staffing_seed_actor_user_id', $actor_user_id);
				update_post_meta($event_plan_id, '_vms_staffing_seed_reason', $queue_reason);
			}

			if (function_exists('bvmgr_event_plan_perf_log')) {
				bvmgr_event_plan_perf_log(
					'vms_staffing_queue_seed_event_slots',
					$event_plan_id,
					array(
						'job_name' => 'staffing_seed_template',
						'reason' => $reason,
						'actor_user_id' => $actor_user_id,
						'already_scheduled' => $already_scheduled ? 1 : 0,
						'already_locked' => $already_locked ? 1 : 0,
						'scheduled_now' => $scheduled_now ? 1 : 0,
						'queue_meta_skipped' => $queue_meta_skipped ? 1 : 0,
					)
				);
				bvmgr_event_plan_perf_log(
					'event_plan_staffing_queue_meta',
					$event_plan_id,
					array(
						'phase' => $queue_meta_skipped ? 'skip' : 'run',
						'skip_reason' => $queue_meta_skipped ? 'already_queued_or_locked' : '',
						'reason' => $queue_reason,
						'already_scheduled' => $already_scheduled ? 1 : 0,
						'already_locked' => $already_locked ? 1 : 0,
						'scheduled_now' => $scheduled_now ? 1 : 0,
					)
				);
			}
		if (function_exists('bvmgr_event_plan_perf_span_finish')) {
			bvmgr_event_plan_perf_span_finish('vms_staffing_queue_seed_event_slots', $event_plan_id, $trace, array('job_name' => 'staffing_seed_template', 'reason' => $reason));
		}
	}
}

if (!function_exists('vms_staffing_run_queued_seed_event_slots')) {
	function vms_staffing_run_queued_seed_event_slots(int $event_plan_id): void
	{
		$event_plan_id = absint($event_plan_id);
		$trace = function_exists('bvmgr_event_plan_perf_span_start')
			? bvmgr_event_plan_perf_span_start('vms_staffing_run_queued_seed_event_slots', $event_plan_id, array('job_name' => 'staffing_seed_template'))
			: '';
		if ($event_plan_id <= 0 || get_post_type($event_plan_id) !== 'vms_event_plan') {
			if (function_exists('bvmgr_event_plan_perf_job_clear_lock')) {
				bvmgr_event_plan_perf_job_clear_lock('staffing_seed_template', $event_plan_id);
			}
			if (function_exists('bvmgr_event_plan_perf_span_finish')) {
				bvmgr_event_plan_perf_span_finish('vms_staffing_run_queued_seed_event_slots', $event_plan_id, $trace, array('job_name' => 'staffing_seed_template', 'skipped' => 1));
			}
			return;
		}

		$lock = function_exists('bvmgr_event_plan_perf_job_get_lock')
			? bvmgr_event_plan_perf_job_get_lock('staffing_seed_template', $event_plan_id)
			: array();
		if (($lock['state'] ?? '') === 'running') {
			if (function_exists('bvmgr_event_plan_perf_log')) {
				bvmgr_event_plan_perf_log(
					'vms_staffing_run_queued_seed_event_slots',
					$event_plan_id,
					array(
						'job_name' => 'staffing_seed_template',
						'skipped' => 1,
						'skip_reason' => 'job_already_running',
					)
				);
			}
			if (function_exists('bvmgr_event_plan_perf_span_finish')) {
				bvmgr_event_plan_perf_span_finish('vms_staffing_run_queued_seed_event_slots', $event_plan_id, $trace, array('job_name' => 'staffing_seed_template', 'skipped' => 1));
			}
			return;
		}

		if (function_exists('bvmgr_event_plan_has_effective_tickets') && !bvmgr_event_plan_has_effective_tickets($event_plan_id)) {
			update_post_meta($event_plan_id, '_vms_staffing_seed_queue_state', 'skipped');
			update_post_meta($event_plan_id, '_vms_staffing_seed_completed_at', time());
			if (function_exists('bvmgr_event_plan_perf_log')) {
				bvmgr_event_plan_perf_log(
					'vms_staffing_run_queued_seed_event_slots',
					$event_plan_id,
					array(
						'job_name' => 'staffing_seed_template',
						'skipped' => 1,
						'skip_reason' => 'no_effective_tickets',
					)
				);
			}
			if (function_exists('bvmgr_event_plan_perf_job_clear_lock')) {
				bvmgr_event_plan_perf_job_clear_lock('staffing_seed_template', $event_plan_id);
			}
			if (function_exists('bvmgr_event_plan_perf_span_finish')) {
				bvmgr_event_plan_perf_span_finish('vms_staffing_run_queued_seed_event_slots', $event_plan_id, $trace, array('job_name' => 'staffing_seed_template', 'skipped' => 1));
			}
			return;
		}

		if (function_exists('bvmgr_event_plan_perf_job_set_lock')) {
			bvmgr_event_plan_perf_job_set_lock('staffing_seed_template', $event_plan_id, 'running', 20 * MINUTE_IN_SECONDS);
		}

		$actor_user_id = absint(get_post_meta($event_plan_id, '_vms_staffing_seed_actor_user_id', true));
		try {
			update_post_meta($event_plan_id, '_vms_staffing_seed_queue_state', 'running');
			vms_staffing_seed_event_slots_from_template($event_plan_id, false, $actor_user_id > 0 ? $actor_user_id : null);
			update_post_meta($event_plan_id, '_vms_staffing_seed_queue_state', 'complete');
			update_post_meta($event_plan_id, '_vms_staffing_seed_completed_at', time());
		} finally {
			if (function_exists('bvmgr_event_plan_perf_job_clear_lock')) {
				bvmgr_event_plan_perf_job_clear_lock('staffing_seed_template', $event_plan_id);
			}
			if (function_exists('bvmgr_event_plan_perf_span_finish')) {
				bvmgr_event_plan_perf_span_finish('vms_staffing_run_queued_seed_event_slots', $event_plan_id, $trace, array('job_name' => 'staffing_seed_template'));
			}
		}
	}
}
add_action('vms_staffing_seed_event_slots_queued', 'vms_staffing_run_queued_seed_event_slots', 10, 1);

// Mark staffing rollup dirty when Event Plan saves actually touch staffing.
add_action('save_post_vms_event_plan', function ($post_id, $post, $update) {
	$deferred_state = function_exists('bvmgr_event_plan_save_profiler_deferred_state_for_post')
		? bvmgr_event_plan_save_profiler_deferred_state_for_post((int) $post_id)
		: array();
	$deferred_context = is_array($deferred_state['context'] ?? null) ? $deferred_state['context'] : array();
	$trace = function_exists('bvmgr_event_plan_perf_span_start')
		? bvmgr_event_plan_perf_span_start(
			'vms_staffing_rollup_dirty_on_save',
			(int) $post_id,
			array(
				'job_name' => 'staffing_rollup_dirty',
				'create' => $update ? 0 : 1,
				'update' => $update ? 1 : 0,
				'old_status' => sanitize_key((string) ($deferred_context['transition_old_status'] ?? '')),
				'new_status' => sanitize_key((string) ($deferred_context['transition_new_status'] ?? ($post instanceof WP_Post ? $post->post_status : ''))),
			)
		)
		: '';
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		if (function_exists('bvmgr_event_plan_perf_span_finish')) {
			bvmgr_event_plan_perf_span_finish('vms_staffing_rollup_dirty_on_save', (int) $post_id, $trace, array('job_name' => 'staffing_rollup_dirty', 'skipped' => 1));
		}
		return;
	}
	if (!($post instanceof WP_Post) || $post->post_type !== 'vms_event_plan') {
		if (function_exists('bvmgr_event_plan_perf_span_finish')) {
			bvmgr_event_plan_perf_span_finish('vms_staffing_rollup_dirty_on_save', (int) $post_id, $trace, array('job_name' => 'staffing_rollup_dirty', 'skipped' => 1));
		}
		return;
	}
	$post_id = absint($post_id);
	if ($post_id <= 0) {
		if (function_exists('bvmgr_event_plan_perf_span_finish')) {
			bvmgr_event_plan_perf_span_finish('vms_staffing_rollup_dirty_on_save', $post_id, $trace, array('job_name' => 'staffing_rollup_dirty', 'skipped' => 1));
		}
		return;
	}
	if (
		function_exists('bvmgr_event_plan_save_profiler_is_featured_image_only')
		&& bvmgr_event_plan_save_profiler_is_featured_image_only($post_id)
	) {
		if (function_exists('bvmgr_event_plan_save_profiler_note_heavy_action')) {
			bvmgr_event_plan_save_profiler_note_heavy_action('staffing_rollup_dirty', 'skipped', 'featured_image_only');
		}
		if (function_exists('bvmgr_event_plan_perf_span_finish')) {
			bvmgr_event_plan_perf_span_finish('vms_staffing_rollup_dirty_on_save', $post_id, $trace, array(
				'job_name' => 'staffing_rollup_dirty',
				'skipped' => 1,
				'skip_reason' => 'featured_image_only',
			));
		}
		return;
	}
	$request_state = function_exists('vms_staffing_plan_save_request_state_get')
		? vms_staffing_plan_save_request_state_get($post_id)
		: array();
	$request_state_has_matrix_change = !empty($request_state['matrix_dirty']);
	$request_state_dirty_reason = function_exists('vms_staffing_plan_save_request_state_dirty_reason')
		? vms_staffing_plan_save_request_state_dirty_reason($request_state)
		: '';
	if (!empty($request_state) && !$request_state_has_matrix_change) {
		if (function_exists('bvmgr_event_plan_perf_log')) {
			bvmgr_event_plan_perf_log(
				'event_plan_staffing_availability_conflict',
				$post_id,
				array(
					'phase' => 'skip',
					'skip_reason' => 'no_staffing_change',
					'dirty_reason' => '',
				)
			);
		}
		if (function_exists('bvmgr_event_plan_save_profiler_note_heavy_action')) {
			bvmgr_event_plan_save_profiler_note_heavy_action('staffing_rollup_dirty', 'skipped', 'no_staffing_change');
		}
		if (function_exists('bvmgr_event_plan_perf_span_finish')) {
			bvmgr_event_plan_perf_span_finish('vms_staffing_rollup_dirty_on_save', $post_id, $trace, array('job_name' => 'staffing_rollup_dirty', 'skipped' => 1, 'skip_reason' => 'no_staffing_change'));
		}
		return;
	}
	if (function_exists('bvmgr_event_plan_has_effective_tickets') && !bvmgr_event_plan_has_effective_tickets($post_id)) {
		if (function_exists('bvmgr_event_plan_perf_log')) {
			bvmgr_event_plan_perf_log(
				'event_plan_staffing_availability_conflict',
				$post_id,
				array(
					'phase' => 'skip',
					'skip_reason' => 'no_effective_tickets',
					'dirty_reason' => $request_state_dirty_reason,
				)
			);
		}
		if (function_exists('bvmgr_event_plan_save_profiler_note_heavy_action')) {
			bvmgr_event_plan_save_profiler_note_heavy_action('staffing_rollup_dirty', 'skipped', 'no_effective_tickets');
		}
		if (function_exists('bvmgr_event_plan_perf_span_finish')) {
			bvmgr_event_plan_perf_span_finish('vms_staffing_rollup_dirty_on_save', $post_id, $trace, array('job_name' => 'staffing_rollup_dirty', 'skipped' => 1, 'skip_reason' => 'no_effective_tickets'));
		}
		return;
	}
	if (function_exists('bvmgr_event_plan_save_profiler_active') && bvmgr_event_plan_save_profiler_active() && function_exists('bvmgr_event_plan_save_profiler_module_touched') && !bvmgr_event_plan_save_profiler_module_touched('staffing')) {
		if (function_exists('bvmgr_event_plan_save_profiler_note_heavy_action')) {
			bvmgr_event_plan_save_profiler_note_heavy_action('staffing_rollup_dirty', 'skipped', 'no_staffing_change');
		}
		if (function_exists('bvmgr_event_plan_perf_span_finish')) {
			bvmgr_event_plan_perf_span_finish('vms_staffing_rollup_dirty_on_save', $post_id, $trace, array('job_name' => 'staffing_rollup_dirty', 'skipped' => 1));
		}
		return;
	}
	if (function_exists('bvmgr_event_plan_save_profiler_note_heavy_action')) {
		bvmgr_event_plan_save_profiler_note_heavy_action('staffing_rollup_dirty', 'triggered', $request_state_dirty_reason !== '' ? $request_state_dirty_reason : 'staffing_changed');
	}
	if (function_exists('bvmgr_event_plan_perf_log')) {
		bvmgr_event_plan_perf_log(
			'event_plan_staffing_availability_conflict',
			$post_id,
			array(
				'phase' => 'run',
				'dirty_reason' => $request_state_dirty_reason !== '' ? $request_state_dirty_reason : 'staffing_changed',
			)
		);
	}
	vms_staffing_mark_rollup_dirty($post_id, 'event_plan_saved');
	if (function_exists('bvmgr_event_plan_perf_span_finish')) {
		bvmgr_event_plan_perf_span_finish('vms_staffing_rollup_dirty_on_save', $post_id, $trace, array('job_name' => 'staffing_rollup_dirty', 'dirty_reason' => $request_state_dirty_reason));
	}
}, 90, 3);

add_action('save_post_vms_event_plan', function ($post_id, $post, $update) {
	$deferred_state = function_exists('bvmgr_event_plan_save_profiler_deferred_state_for_post')
		? bvmgr_event_plan_save_profiler_deferred_state_for_post((int) $post_id)
		: array();
	$deferred_context = is_array($deferred_state['context'] ?? null) ? $deferred_state['context'] : array();
	$trace = function_exists('bvmgr_event_plan_perf_span_start')
		? bvmgr_event_plan_perf_span_start(
			'vms_staffing_seed_template_on_save',
			(int) $post_id,
			array(
				'job_name' => 'staffing_seed_template',
				'create' => $update ? 0 : 1,
				'update' => $update ? 1 : 0,
				'old_status' => sanitize_key((string) ($deferred_context['transition_old_status'] ?? '')),
				'new_status' => sanitize_key((string) ($deferred_context['transition_new_status'] ?? ($post instanceof WP_Post ? $post->post_status : ''))),
			)
		)
		: '';
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		if (function_exists('bvmgr_event_plan_perf_span_finish')) {
			bvmgr_event_plan_perf_span_finish('vms_staffing_seed_template_on_save', (int) $post_id, $trace, array('job_name' => 'staffing_seed_template', 'skipped' => 1));
		}
		return;
	}
	if (wp_is_post_revision($post_id)) {
		if (function_exists('bvmgr_event_plan_perf_span_finish')) {
			bvmgr_event_plan_perf_span_finish('vms_staffing_seed_template_on_save', (int) $post_id, $trace, array('job_name' => 'staffing_seed_template', 'skipped' => 1));
		}
		return;
	}
	if (!($post instanceof WP_Post) || $post->post_type !== 'vms_event_plan') {
		if (function_exists('bvmgr_event_plan_perf_span_finish')) {
			bvmgr_event_plan_perf_span_finish('vms_staffing_seed_template_on_save', (int) $post_id, $trace, array('job_name' => 'staffing_seed_template', 'skipped' => 1));
		}
		return;
	}
	$post_id = absint($post_id);
	if ($post_id <= 0) {
		if (function_exists('bvmgr_event_plan_perf_span_finish')) {
			bvmgr_event_plan_perf_span_finish('vms_staffing_seed_template_on_save', $post_id, $trace, array('job_name' => 'staffing_seed_template', 'skipped' => 1));
		}
		return;
	}
	if (
		function_exists('bvmgr_event_plan_save_profiler_is_featured_image_only')
		&& bvmgr_event_plan_save_profiler_is_featured_image_only($post_id)
	) {
		if (function_exists('bvmgr_event_plan_save_profiler_note_heavy_action')) {
			bvmgr_event_plan_save_profiler_note_heavy_action('staffing_seed_template', 'skipped', 'featured_image_only');
		}
		if (function_exists('bvmgr_event_plan_perf_span_finish')) {
			bvmgr_event_plan_perf_span_finish('vms_staffing_seed_template_on_save', $post_id, $trace, array(
				'job_name' => 'staffing_seed_template',
				'skipped' => 1,
				'skip_reason' => 'featured_image_only',
			));
		}
		return;
	}
	$request_state = function_exists('vms_staffing_plan_save_request_state_get')
		? vms_staffing_plan_save_request_state_get($post_id)
		: array();
	$request_state_has_matrix_change = !empty($request_state['matrix_dirty']);
	$request_state_dirty_reason = function_exists('vms_staffing_plan_save_request_state_dirty_reason')
		? vms_staffing_plan_save_request_state_dirty_reason($request_state)
		: '';
	$context_dirty_keys = function_exists('vms_staffing_plan_save_context_dirty_keys')
		? vms_staffing_plan_save_context_dirty_keys()
		: array();
	$seed_dirty_reasons = array();
	if ($request_state_has_matrix_change && $request_state_dirty_reason !== '') {
		$seed_dirty_reasons[] = $request_state_dirty_reason;
	}
	if (!empty($context_dirty_keys)) {
		$seed_dirty_reasons[] = 'context:' . implode(',', array_map('sanitize_key', $context_dirty_keys));
	}
	$seed_dirty_reason = implode(',', array_values(array_unique(array_filter($seed_dirty_reasons))));
	if (!empty($request_state) && !$request_state_has_matrix_change && empty($context_dirty_keys)) {
		if (function_exists('bvmgr_event_plan_perf_log')) {
			bvmgr_event_plan_perf_log(
				'event_plan_staffing_seed',
				$post_id,
				array(
					'phase' => 'skip',
					'dirty_branch' => 'skip',
					'skip_reason' => 'no_staffing_change',
					'context_dirty_keys' => array(),
				)
			);
			bvmgr_event_plan_perf_log(
				'event_plan_staffing_queue_meta',
				$post_id,
				array(
					'phase' => 'skip',
					'skip_reason' => 'no_staffing_change',
				)
			);
		}
		if (function_exists('bvmgr_event_plan_save_profiler_note_heavy_action')) {
			bvmgr_event_plan_save_profiler_note_heavy_action('staffing_seed_template', 'skipped', 'no_staffing_change');
		}
		if (function_exists('bvmgr_event_plan_perf_span_finish')) {
			bvmgr_event_plan_perf_span_finish('vms_staffing_seed_template_on_save', $post_id, $trace, array('job_name' => 'staffing_seed_template', 'skipped' => 1, 'skip_reason' => 'no_staffing_change'));
		}
		return;
	}
	if (!current_user_can('edit_post', $post_id)) {
		if (function_exists('bvmgr_event_plan_perf_span_finish')) {
			bvmgr_event_plan_perf_span_finish('vms_staffing_seed_template_on_save', $post_id, $trace, array('job_name' => 'staffing_seed_template', 'skipped' => 1));
		}
		return;
	}
	if (!function_exists('vms_staffing_seed_event_slots_from_template')) {
		if (function_exists('bvmgr_event_plan_perf_span_finish')) {
			bvmgr_event_plan_perf_span_finish('vms_staffing_seed_template_on_save', $post_id, $trace, array('job_name' => 'staffing_seed_template', 'skipped' => 1));
		}
		return;
	}
	if (function_exists('bvmgr_event_plan_capture_actor_user_id')) {
		bvmgr_event_plan_capture_actor_user_id($post_id, (int) get_current_user_id(), 'staffing_seed_save');
	}
	if (function_exists('bvmgr_event_plan_has_effective_tickets') && !bvmgr_event_plan_has_effective_tickets($post_id)) {
		if (function_exists('bvmgr_event_plan_perf_log')) {
			bvmgr_event_plan_perf_log(
				'event_plan_staffing_seed',
				$post_id,
				array(
					'phase' => 'skip',
					'dirty_branch' => 'skip',
					'skip_reason' => 'no_effective_tickets',
					'dirty_reason' => $seed_dirty_reason,
					'context_dirty_keys' => $context_dirty_keys,
				)
			);
			bvmgr_event_plan_perf_log(
				'event_plan_staffing_queue_meta',
				$post_id,
				array(
					'phase' => 'skip',
					'skip_reason' => 'no_effective_tickets',
				)
			);
		}
		if (function_exists('bvmgr_event_plan_save_profiler_note_heavy_action')) {
			bvmgr_event_plan_save_profiler_note_heavy_action('staffing_seed_template', 'skipped', 'no_effective_tickets');
		}
		if (function_exists('bvmgr_event_plan_perf_span_finish')) {
			bvmgr_event_plan_perf_span_finish('vms_staffing_seed_template_on_save', $post_id, $trace, array('job_name' => 'staffing_seed_template', 'skipped' => 1, 'skip_reason' => 'no_effective_tickets'));
		}
		return;
	}

	$should_seed = ($request_state_has_matrix_change || !empty($context_dirty_keys));
	if (!$should_seed && function_exists('bvmgr_event_plan_save_profiler_active') && bvmgr_event_plan_save_profiler_active() && function_exists('bvmgr_event_plan_save_profiler_module_touched') && function_exists('bvmgr_event_plan_save_profiler_meta_key_touched')) {
		$context_keys = vms_staffing_event_context_meta_keys();
		$should_seed = bvmgr_event_plan_save_profiler_module_touched('staffing') || bvmgr_event_plan_save_profiler_meta_key_touched($context_keys);
		if (!$should_seed) {
			if (function_exists('bvmgr_event_plan_perf_log')) {
				bvmgr_event_plan_perf_log(
					'event_plan_staffing_seed',
					$post_id,
					array(
						'phase' => 'skip',
						'dirty_branch' => 'skip',
						'skip_reason' => 'no_relevant_change',
						'context_dirty_keys' => $context_dirty_keys,
					)
				);
				bvmgr_event_plan_perf_log(
					'event_plan_staffing_queue_meta',
					$post_id,
					array(
						'phase' => 'skip',
						'skip_reason' => 'no_relevant_change',
					)
				);
			}
			if (function_exists('bvmgr_event_plan_save_profiler_note_heavy_action')) {
				bvmgr_event_plan_save_profiler_note_heavy_action('staffing_seed_template', 'skipped', 'no_relevant_change');
			}
			if (function_exists('bvmgr_event_plan_perf_span_finish')) {
				bvmgr_event_plan_perf_span_finish('vms_staffing_seed_template_on_save', $post_id, $trace, array('job_name' => 'staffing_seed_template', 'skipped' => 1, 'skip_reason' => 'no_relevant_change'));
			}
			return;
		}
	}

	if (function_exists('bvmgr_event_plan_perf_log')) {
		bvmgr_event_plan_perf_log(
			'event_plan_staffing_seed',
			$post_id,
			array(
				'phase' => 'run',
				'dirty_branch' => 'queue',
				'dirty_reason' => $seed_dirty_reason !== '' ? $seed_dirty_reason : 'staffing_or_context_changed',
				'context_dirty_keys' => $context_dirty_keys,
			)
		);
	}
	if (function_exists('bvmgr_event_plan_save_profiler_note_heavy_action')) {
		bvmgr_event_plan_save_profiler_note_heavy_action('staffing_seed_template', 'scheduled', $seed_dirty_reason !== '' ? $seed_dirty_reason : 'staffing_or_context_changed');
	}
	if (function_exists('vms_staffing_queue_seed_event_slots')) {
		vms_staffing_queue_seed_event_slots($post_id, (int) get_current_user_id(), 'event_plan_save');
	}
	if (function_exists('bvmgr_event_plan_perf_span_finish')) {
		bvmgr_event_plan_perf_span_finish('vms_staffing_seed_template_on_save', $post_id, $trace, array('job_name' => 'staffing_seed_template', 'dirty_reason' => $seed_dirty_reason));
	}
}, 95, 3);
