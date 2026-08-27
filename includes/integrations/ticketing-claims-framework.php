<?php
defined('ABSPATH') || exit;

if (!function_exists('bvmgr_ticketing_claims_db_schema_option_key')) {
	function bvmgr_ticketing_claims_db_schema_option_key(): string
	{
		return defined('BVMGR_OPT_TICKETING_CLAIMS_DB_SCHEMA_VERSION')
			? (string) BVMGR_OPT_TICKETING_CLAIMS_DB_SCHEMA_VERSION
			: 'vms_ticketing_claims_db_schema_version';
	}
}

if (!function_exists('bvmgr_ticketing_claims_db_schema_target')) {
	function bvmgr_ticketing_claims_db_schema_target(): string
	{
		return defined('BVMGR_TICKETING_CLAIMS_DB_SCHEMA_VERSION')
			? (string) BVMGR_TICKETING_CLAIMS_DB_SCHEMA_VERSION
			: 'ticketing_claims_v1';
	}
}

if (!function_exists('bvmgr_ticketing_claims_table_direct_grants')) {
	function bvmgr_ticketing_claims_table_direct_grants(): string
	{
		global $wpdb;
		$suffix = defined('BVMGR_DB_TABLE_TICKETING_DIRECT_GRANTS_SUFFIX')
			? (string) BVMGR_DB_TABLE_TICKETING_DIRECT_GRANTS_SUFFIX
			: 'vms_ticketing_direct_grants';
		return $wpdb->prefix . $suffix;
	}
}

if (!function_exists('bvmgr_ticketing_claims_table_reservations')) {
	function bvmgr_ticketing_claims_table_reservations(): string
	{
		global $wpdb;
		$suffix = defined('BVMGR_DB_TABLE_TICKETING_CLAIM_RESERVATIONS_SUFFIX')
			? (string) BVMGR_DB_TABLE_TICKETING_CLAIM_RESERVATIONS_SUFFIX
			: 'vms_ticketing_claim_reservations';
		return $wpdb->prefix . $suffix;
	}
}

if (!function_exists('bvmgr_ticketing_claims_table_log')) {
	function bvmgr_ticketing_claims_table_log(): string
	{
		global $wpdb;
		$suffix = defined('BVMGR_DB_TABLE_TICKETING_CLAIM_LOG_SUFFIX')
			? (string) BVMGR_DB_TABLE_TICKETING_CLAIM_LOG_SUFFIX
			: 'vms_ticketing_claim_log';
		return $wpdb->prefix . $suffix;
	}
}

if (!function_exists('bvmgr_ticketing_claims_maybe_upgrade_schema')) {
	function bvmgr_ticketing_claims_maybe_upgrade_schema(): void
	{
		$current = (string) get_option(bvmgr_ticketing_claims_db_schema_option_key(), '');
		$target = bvmgr_ticketing_claims_db_schema_target();
		if ($current === $target) {
			return;
		}

		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();

		$grants = bvmgr_ticketing_claims_table_direct_grants();
		$reservations = bvmgr_ticketing_claims_table_reservations();
		$claim_log = bvmgr_ticketing_claims_table_log();

		$sql_grants = "CREATE TABLE {$grants} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			event_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			grant_type VARCHAR(64) NOT NULL DEFAULT 'event_ticket_eligibility',
			ticket_product_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			ticket_key VARCHAR(191) NOT NULL DEFAULT '',
			credential_program VARCHAR(64) NOT NULL DEFAULT '',
			qty_limit INT(10) UNSIGNED NOT NULL DEFAULT 1,
			qty_used INT(10) UNSIGNED NOT NULL DEFAULT 0,
			status VARCHAR(32) NOT NULL DEFAULT 'active',
			note TEXT NULL,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			created_by BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			updated_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_by BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			KEY event_user_status (event_id, user_id, status),
			KEY grant_type (grant_type),
			KEY ticket_product_id (ticket_product_id),
			KEY ticket_key (ticket_key),
			KEY credential_program (credential_program)
		) {$charset};";

		$sql_reservations = "CREATE TABLE {$reservations} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			reservation_token VARCHAR(64) NOT NULL DEFAULT '',
			status VARCHAR(32) NOT NULL DEFAULT 'reserved',
			event_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			ticket_product_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			ticket_key VARCHAR(191) NOT NULL DEFAULT '',
			buyer_user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			assignee_user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			assignee_email VARCHAR(190) NOT NULL DEFAULT '',
			rule_path VARCHAR(64) NOT NULL DEFAULT '',
			direct_grant_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			cart_item_key VARCHAR(191) NOT NULL DEFAULT '',
			session_key VARCHAR(191) NOT NULL DEFAULT '',
			order_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			expires_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			released_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			meta_json LONGTEXT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY reservation_token (reservation_token),
			KEY event_assignee_status (event_id, assignee_user_id, status),
			KEY expires_at (expires_at),
			KEY order_id (order_id),
			KEY direct_grant_id (direct_grant_id)
		) {$charset};";

		$sql_log = "CREATE TABLE {$claim_log} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			event_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			ticket_product_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			ticket_key VARCHAR(191) NOT NULL DEFAULT '',
			buyer_user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			assignee_user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			assignee_email VARCHAR(190) NOT NULL DEFAULT '',
			rule_path VARCHAR(64) NOT NULL DEFAULT '',
			direct_grant_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			result VARCHAR(32) NOT NULL DEFAULT 'failure',
			reason_code VARCHAR(64) NOT NULL DEFAULT '',
			message TEXT NULL,
			context_json LONGTEXT NULL,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY (id),
			KEY event_id (event_id),
			KEY assignee_user_id (assignee_user_id),
			KEY buyer_user_id (buyer_user_id),
			KEY result_reason (result, reason_code),
			KEY created_at (created_at)
		) {$charset};";

		dbDelta($sql_grants);
		dbDelta($sql_reservations);
		dbDelta($sql_log);

		update_option(bvmgr_ticketing_claims_db_schema_option_key(), $target, false);
	}
}
add_action('plugins_loaded', 'bvmgr_ticketing_claims_maybe_upgrade_schema', 9);

if (!function_exists('bvmgr_ticketing_claims_sanitize_program_list')) {
	/**
	 * @param mixed $raw
	 * @return string[]
	 */
	function bvmgr_ticketing_claims_sanitize_program_list($raw): array
	{
		$list = array();
		if (is_array($raw)) {
			$list = $raw;
		} elseif (is_string($raw)) {
			$list = preg_split('/[\s,]+/', $raw) ?: array();
		}

		$out = array();
		foreach ($list as $entry) {
			$key = sanitize_key((string) $entry);
			if ($key === '') {
				continue;
			}
			$out[$key] = $key;
		}

		if (function_exists('bvmgr_ticketing_verification_programs')) {
			$known = array_keys((array) bvmgr_ticketing_verification_programs());
			$known_map = array();
			foreach ($known as $known_program) {
				$known_key = sanitize_key((string) $known_program);
				if ($known_key !== '') {
					$known_map[$known_key] = true;
				}
			}

			$filtered = array();
			foreach ($out as $program_key => $program_value) {
				if (isset($known_map[$program_key])) {
					$filtered[$program_key] = $program_value;
				}
			}
			$out = $filtered;
		}

		return array_values($out);
	}
}

if (!function_exists('bvmgr_ticketing_claims_normalize_allowed_programs')) {
	/**
	 * @param mixed $raw
	 * @return string[]
	 */
	function bvmgr_ticketing_claims_normalize_allowed_programs($raw, string $legacy_program = ''): array
	{
		$programs = bvmgr_ticketing_claims_sanitize_program_list($raw);
		if (!empty($programs)) {
			return $programs;
		}

		$legacy = sanitize_key($legacy_program);
		if ($legacy !== '') {
			$programs = bvmgr_ticketing_claims_sanitize_program_list(array($legacy));
		}
		return $programs;
	}
}

if (!function_exists('bvmgr_ticketing_claims_truthy')) {
	/**
	 * @param mixed $value
	 */
	function bvmgr_ticketing_claims_truthy($value, bool $default = false): bool
	{
		$raw = strtolower(trim((string) $value));
		if ($raw === '') {
			return $default;
		}
		if (in_array($raw, array('0', 'false', 'no', 'off'), true)) {
			return false;
		}
		return true;
	}
}

if (!function_exists('bvmgr_ticketing_claims_program_labels')) {
	/**
	 * @param string[] $programs
	 * @return string[]
	 */
	function bvmgr_ticketing_claims_program_labels(array $programs): array
	{
		$labels = array();
		foreach ($programs as $program) {
			$key = sanitize_key((string) $program);
			if ($key === '') {
				continue;
			}
			if (function_exists('bvmgr_ticketing_verification_program_label')) {
				$labels[] = bvmgr_ticketing_verification_program_label($key);
			} else {
				$labels[] = ucwords(str_replace('_', ' ', $key));
			}
		}
		return array_values(array_filter(array_unique(array_map('trim', $labels))));
	}
}

if (!function_exists('bvmgr_ticketing_claims_allowed_grant_types')) {
	/**
	 * @return string[]
	 */
	function bvmgr_ticketing_claims_allowed_grant_types(): array
	{
		return array(
			'event_ticket_eligibility',
			'event_free_admit',
			'credential_benefit_override',
			'event_grant',
		);
	}
}

if (!function_exists('bvmgr_ticketing_claims_grant_type_labels')) {
	/**
	 * @return array<string,string>
	 */
	function bvmgr_ticketing_claims_grant_type_labels(): array
	{
		return array(
			'event_ticket_eligibility' => __('Event Ticket Eligibility', 'backstage-venue-manager'),
			'event_free_admit' => __('Free Admission', 'backstage-venue-manager'),
			'credential_benefit_override' => __('Credential Benefit Override', 'backstage-venue-manager'),
			'event_grant' => __('Event Grant', 'backstage-venue-manager'),
		);
	}
}

if (!function_exists('bvmgr_ticketing_claims_grant_type_label')) {
	function bvmgr_ticketing_claims_grant_type_label(string $grant_type): string
	{
		$grant_type = sanitize_key($grant_type);
		$labels = bvmgr_ticketing_claims_grant_type_labels();
		if ($grant_type !== '' && isset($labels[$grant_type])) {
			return (string) $labels[$grant_type];
		}
		return ucwords(str_replace('_', ' ', $grant_type));
	}
}

if (!function_exists('bvmgr_ticketing_claims_grant_status_label')) {
	function bvmgr_ticketing_claims_grant_status_label(string $status): string
	{
		$status = sanitize_key($status);
		$labels = array(
			'active' => __('Active', 'backstage-venue-manager'),
			'reserved' => __('Reserved', 'backstage-venue-manager'),
			'used' => __('Used', 'backstage-venue-manager'),
			'expired' => __('Expired', 'backstage-venue-manager'),
			'revoked' => __('Revoked', 'backstage-venue-manager'),
		);
		return $labels[$status] ?? ucwords(str_replace('_', ' ', $status));
	}
}

if (!function_exists('bvmgr_ticketing_claims_grant_status_explanation')) {
	function bvmgr_ticketing_claims_grant_status_explanation(string $status): string
	{
		$status = sanitize_key($status);
		$map = array(
			'active' => __('Available to use for a qualifying ticket.', 'backstage-venue-manager'),
			'reserved' => __('Temporarily held in a pending cart and can be released if checkout is abandoned.', 'backstage-venue-manager'),
			'used' => __('Already consumed for this event benefit.', 'backstage-venue-manager'),
			'expired' => __('No longer valid because the grant expired.', 'backstage-venue-manager'),
			'revoked' => __('Disabled by an operator and unavailable for future use.', 'backstage-venue-manager'),
		);
		return $map[$status] ?? __('Status explanation unavailable.', 'backstage-venue-manager');
	}
}

if (!function_exists('bvmgr_ticketing_claims_grant_reservation_counts')) {
	/**
	 * @return array<string,int>
	 */
	function bvmgr_ticketing_claims_grant_reservation_counts(int $grant_id): array
	{
		$grant_id = absint($grant_id);
		if ($grant_id <= 0) {
			return array();
		}

		global $wpdb;
		$table = bvmgr_ticketing_claims_table_reservations();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Ticketing Claims reservation counts read the plugin-owned reservations table with %i/%d-prepared values so grant state reflects immediate reservation mutations.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT status, COUNT(1) AS cnt FROM %i WHERE direct_grant_id = %d GROUP BY status',
				$table,
				$grant_id
			),
			ARRAY_A
		);
		if (!is_array($rows)) {
			return array();
		}

		$counts = array();
		foreach ($rows as $row) {
			if (!is_array($row)) {
				continue;
			}
			$status = sanitize_key((string) ($row['status'] ?? ''));
			if ($status === '') {
				continue;
			}
			$counts[$status] = max(0, absint($row['cnt'] ?? 0));
		}

		return $counts;
	}
}

if (!function_exists('bvmgr_ticketing_claims_resolve_grant_status')) {
	/**
	 * @param array<string,mixed> $grant
	 * @param array<string,int>|null $reservation_counts
	 */
	function bvmgr_ticketing_claims_resolve_grant_status(array $grant, ?array $reservation_counts = null): string
	{
		$raw_status = sanitize_key((string) ($grant['status'] ?? 'active'));
		$qty_limit = max(0, absint($grant['qty_limit'] ?? 0));
		$qty_used = max(0, absint($grant['qty_used'] ?? 0));

		if (in_array($raw_status, array('revoked', 'expired', 'used'), true)) {
			return $raw_status;
		}
		if ($qty_limit > 0 && $qty_used >= $qty_limit) {
			return 'used';
		}

		if ($reservation_counts === null) {
			$reservation_counts = bvmgr_ticketing_claims_grant_reservation_counts(absint($grant['id'] ?? 0));
		}
		$reserved_count = max(0, absint($reservation_counts['reserved'] ?? 0));
		if ($reserved_count > 0 || $raw_status === 'reserved') {
			return 'reserved';
		}

		return 'active';
	}
}

if (!function_exists('bvmgr_ticketing_claims_find_direct_grant_diagnostic')) {
	/**
	 * @param array<string,mixed> $args
	 * @return array<string,mixed>|null
	 */
	function bvmgr_ticketing_claims_find_direct_grant_diagnostic(array $args): ?array
	{
		$user_id = absint($args['user_id'] ?? 0);
		$event_id = absint($args['event_id'] ?? 0);
		$product_id = absint($args['ticket_product_id'] ?? 0);
		$ticket_key = sanitize_key((string) ($args['ticket_key'] ?? ''));
		$grant_type = sanitize_key((string) ($args['grant_type'] ?? 'event_ticket_eligibility'));
		$allowed_programs = bvmgr_ticketing_claims_sanitize_program_list($args['allowed_programs'] ?? array());

		if ($user_id <= 0 || $event_id <= 0 || !function_exists('bvmgr_ticketing_claims_get_direct_grants')) {
			return null;
		}
		if ($grant_type === '') {
			$grant_type = 'event_ticket_eligibility';
		}

		$rows = bvmgr_ticketing_claims_get_direct_grants(array(
			'user_id' => $user_id,
			'event_id' => $event_id,
			'status' => 'any',
			'limit' => 120,
		));
		if (empty($rows)) {
			return null;
		}

		$allowed_types = ($grant_type === 'event_ticket_eligibility')
			? array('event_ticket_eligibility', 'event_free_admit', 'credential_benefit_override', 'event_grant')
			: array($grant_type);

		$matches = array();
		foreach ($rows as $row) {
			if (!is_array($row)) {
				continue;
			}

			$row_type = sanitize_key((string) ($row['grant_type'] ?? ''));
			if (!in_array($row_type, $allowed_types, true)) {
				continue;
			}

			$row_ticket_product_id = absint($row['ticket_product_id'] ?? 0);
			if ($product_id > 0 && $row_ticket_product_id > 0 && $row_ticket_product_id !== $product_id) {
				continue;
			}

			$row_ticket_key = sanitize_key((string) ($row['ticket_key'] ?? ''));
			if ($ticket_key !== '' && $row_ticket_key !== '' && $row_ticket_key !== $ticket_key) {
				continue;
			}

			$row_program = sanitize_key((string) ($row['credential_program'] ?? ''));
			if (!empty($allowed_programs) && $row_program !== '' && !in_array($row_program, $allowed_programs, true)) {
				continue;
			}

			$matches[] = $row;
		}

		if (empty($matches)) {
			return null;
		}

		usort($matches, static function (array $a, array $b): int {
			$time_a = strtotime((string) ($a['updated_at'] ?? ($a['created_at'] ?? ''))) ?: 0;
			$time_b = strtotime((string) ($b['updated_at'] ?? ($b['created_at'] ?? ''))) ?: 0;
			if ($time_a === $time_b) {
				return absint($b['id'] ?? 0) <=> absint($a['id'] ?? 0);
			}
			return $time_b <=> $time_a;
		});

		return $matches[0];
	}
}

if (!function_exists('bvmgr_ticketing_claims_find_active_direct_grant')) {
	/**
	 * @param array<string,mixed> $args
	 * @return array<string,mixed>|null
	 */
	function bvmgr_ticketing_claims_find_active_direct_grant(array $args): ?array
	{
		global $wpdb;
		$table = bvmgr_ticketing_claims_table_direct_grants();

		$user_id = absint($args['user_id'] ?? 0);
		$event_id = absint($args['event_id'] ?? 0);
		$product_id = absint($args['ticket_product_id'] ?? 0);
		$ticket_key = sanitize_key((string) ($args['ticket_key'] ?? ''));
		$grant_type = sanitize_key((string) ($args['grant_type'] ?? 'event_ticket_eligibility'));
		$allowed_programs = bvmgr_ticketing_claims_sanitize_program_list($args['allowed_programs'] ?? array());

		if ($user_id <= 0 || $event_id <= 0) {
			return null;
		}
		if ($grant_type === '') {
			$grant_type = 'event_ticket_eligibility';
		}

		$where = array(
			'user_id = %d',
			'event_id = %d',
			"status = 'active'",
			'(qty_limit = 0 OR qty_used < qty_limit)',
		);
		$params = array($user_id, $event_id);

		if ($product_id > 0) {
			$where[] = '(ticket_product_id = 0 OR ticket_product_id = %d)';
			$params[] = $product_id;
		}
		if ($ticket_key !== '') {
			$where[] = "(ticket_key = '' OR ticket_key = %s)";
			$params[] = $ticket_key;
		}

		if ($grant_type === 'event_ticket_eligibility') {
			$where[] = "(grant_type = %s OR grant_type = 'event_free_admit' OR grant_type = 'credential_benefit_override' OR grant_type = 'event_grant')";
			$params[] = $grant_type;
		} else {
			$where[] = 'grant_type = %s';
			$params[] = $grant_type;
		}

		if (!empty($allowed_programs)) {
			$program_placeholders = implode(', ', array_fill(0, count($allowed_programs), '%s'));
			$where[] = "(credential_program = '' OR credential_program IN ({$program_placeholders}))";
			$params = array_merge($params, $allowed_programs);
		}

		$sql = 'SELECT * FROM %i WHERE ' . implode(' AND ', $where) . " ORDER BY (ticket_product_id > 0) DESC, (ticket_key <> '') DESC, id DESC LIMIT 1"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Ticketing Claims grant lookups assemble only bounded literal WHERE fragments plus a %i-prepared custom-table identifier.
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Ticketing Claims grant lookups prepare only bounded literal WHERE fragments, sanitized program placeholders, and the plugin-owned grants table identifier.
		$prepared = $wpdb->prepare($sql, array_merge(array($table), $params));
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Ticketing Claims grant lookups read the plugin-owned grants table so eligibility checks see fresh reservation/grant state after writes.
		$row = $wpdb->get_row($prepared, ARRAY_A);
		return is_array($row) ? $row : null;
	}
}

if (!function_exists('bvmgr_ticketing_claims_resolve_eligibility')) {
	/**
	 * @param array<string,mixed> $args
	 * @return array<string,mixed>
	 */
	function bvmgr_ticketing_claims_resolve_eligibility(array $args): array
	{
		$user_id = absint($args['user_id'] ?? 0);
		$event_id = absint($args['event_id'] ?? 0);
		$product_id = absint($args['ticket_product_id'] ?? 0);
		$ticket_key = sanitize_key((string) ($args['ticket_key'] ?? ''));
		$legacy_program = sanitize_key((string) ($args['legacy_program'] ?? ''));
		$allowed_programs = bvmgr_ticketing_claims_normalize_allowed_programs($args['allowed_programs'] ?? array(), $legacy_program);
		$allow_direct_grants = bvmgr_ticketing_claims_truthy($args['allow_direct_grants'] ?? false, false);
		$grant_type = sanitize_key((string) ($args['grant_type'] ?? 'event_ticket_eligibility'));
		if ($grant_type === '') {
			$grant_type = 'event_ticket_eligibility';
		}

		$result = array(
			'eligible' => false,
			'reason_code' => 'not_eligible',
			'matched_rule_path' => '',
			'matched_program' => '',
			'matched_grant_id' => 0,
			'message' => __('This account is not eligible for this ticket.', 'backstage-venue-manager'),
			'diagnostics' => array(
				'user_id' => $user_id,
				'event_id' => $event_id,
				'ticket_product_id' => $product_id,
				'ticket_key' => $ticket_key,
				'allowed_programs' => $allowed_programs,
				'allow_direct_grants' => $allow_direct_grants ? 1 : 0,
				'grant_type' => $grant_type,
			),
		);

		if ($user_id <= 0) {
			$result['reason_code'] = 'login_required';
			$result['message'] = __('Please log in to claim this ticket.', 'backstage-venue-manager');
			return $result;
		}

		if (empty($allowed_programs) && !$allow_direct_grants) {
			$result['reason_code'] = 'no_rule_path';
			$result['message'] = __('No credential rule is configured for this ticket.', 'backstage-venue-manager');
			return $result;
		}

		if (!empty($allowed_programs) && function_exists('bvmgr_ticketing_get_user_verified_programs')) {
			$verified_programs = array_values(array_unique(array_filter(array_map('sanitize_key', (array) bvmgr_ticketing_get_user_verified_programs($user_id)))));
			foreach ($allowed_programs as $program) {
				if (in_array($program, $verified_programs, true)) {
					$result['eligible'] = true;
					$result['reason_code'] = 'ok';
					$result['matched_rule_path'] = 'credential_program';
					$result['matched_program'] = $program;
					$result['message'] = __('Eligible via approved credential.', 'backstage-venue-manager');
					return $result;
				}
			}
		}

		if ($allow_direct_grants && $event_id > 0) {
			$grant = bvmgr_ticketing_claims_find_active_direct_grant(array(
				'user_id' => $user_id,
				'event_id' => $event_id,
				'ticket_product_id' => $product_id,
				'ticket_key' => $ticket_key,
				'grant_type' => $grant_type,
				'allowed_programs' => $allowed_programs,
			));
			if (is_array($grant) && !empty($grant['id'])) {
				$result['eligible'] = true;
				$result['reason_code'] = 'ok';
				$result['matched_rule_path'] = 'event_direct_grant';
				$result['matched_grant_id'] = absint($grant['id']);
				$result['message'] = __('Eligible via direct event grant.', 'backstage-venue-manager');
				return $result;
			}
		}

		$direct_grant_diagnostic = null;
		if ($allow_direct_grants && $event_id > 0 && function_exists('bvmgr_ticketing_claims_find_direct_grant_diagnostic')) {
			$direct_grant_diagnostic = bvmgr_ticketing_claims_find_direct_grant_diagnostic(array(
				'user_id' => $user_id,
				'event_id' => $event_id,
				'ticket_product_id' => $product_id,
				'ticket_key' => $ticket_key,
				'grant_type' => $grant_type,
				'allowed_programs' => $allowed_programs,
			));
			if (is_array($direct_grant_diagnostic) && !empty($direct_grant_diagnostic['id'])) {
				$resolved_status = function_exists('bvmgr_ticketing_claims_resolve_grant_status')
					? bvmgr_ticketing_claims_resolve_grant_status($direct_grant_diagnostic)
					: sanitize_key((string) ($direct_grant_diagnostic['status'] ?? ''));
				$result['matched_grant_id'] = absint($direct_grant_diagnostic['id']);
				$result['matched_rule_path'] = 'event_direct_grant';
				if ($resolved_status === 'revoked') {
					$result['reason_code'] = 'grant_revoked';
					$result['message'] = __('This event grant was revoked by an operator.', 'backstage-venue-manager');
					return $result;
				}
				if ($resolved_status === 'expired') {
					$result['reason_code'] = 'grant_expired';
					$result['message'] = __('This event grant is expired and no longer available.', 'backstage-venue-manager');
					return $result;
				}
				if ($resolved_status === 'reserved') {
					$result['reason_code'] = 'grant_reserved';
					$result['message'] = __('This benefit is currently reserved in another pending cart.', 'backstage-venue-manager');
					return $result;
				}
				if ($resolved_status === 'used') {
					$result['reason_code'] = 'grant_used';
					$result['message'] = __('This account has already used its benefit for this event.', 'backstage-venue-manager');
					return $result;
				}
			}
		}

		if (!empty($allowed_programs) && !$allow_direct_grants) {
			$labels = bvmgr_ticketing_claims_program_labels($allowed_programs);
			$label_text = !empty($labels) ? implode(', ', $labels) : __('required credential', 'backstage-venue-manager');
			$result['reason_code'] = 'credential_not_approved';
			/* translators: %s: human-readable value used in this message. */
			$result['message'] = sprintf(__('This account is not approved for %s.', 'backstage-venue-manager'), $label_text);
			return $result;
		}

		if (empty($allowed_programs) && $allow_direct_grants) {
			$result['reason_code'] = 'direct_grant_missing';
			$result['message'] = __('No active direct grant was found for this account and event.', 'backstage-venue-manager');
			return $result;
		}

		$result['reason_code'] = 'credential_or_grant_missing';
		$result['message'] = __('This account is not approved for the required credential and has no active direct grant for this event.', 'backstage-venue-manager');
		return $result;
	}
}

if (!function_exists('bvmgr_ticketing_claims_log_result')) {
	/**
	 * @param array<string,mixed> $args
	 */
	function bvmgr_ticketing_claims_log_result(array $args): int
	{
		global $wpdb;
		$table = bvmgr_ticketing_claims_table_log();

		$event_id = absint($args['event_id'] ?? 0);
		$product_id = absint($args['ticket_product_id'] ?? 0);
		$ticket_key = sanitize_key((string) ($args['ticket_key'] ?? ''));
		$buyer_user_id = absint($args['buyer_user_id'] ?? 0);
		$assignee_user_id = absint($args['assignee_user_id'] ?? 0);
		$assignee_email = sanitize_email((string) ($args['assignee_email'] ?? ''));
		$rule_path = sanitize_key((string) ($args['rule_path'] ?? ''));
		$direct_grant_id = absint($args['direct_grant_id'] ?? 0);
		$result = sanitize_key((string) ($args['result'] ?? 'failure'));
		$reason_code = sanitize_key((string) ($args['reason_code'] ?? ''));
		$message = sanitize_text_field((string) ($args['message'] ?? ''));
		$context = $args['context'] ?? array();
		$context_json = is_array($context) ? wp_json_encode($context) : '';
		$context_json = is_string($context_json) ? $context_json : '';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Ticketing Claims audit logging persists normalized custom-table rows through wpdb::insert(); no core API preserves this repository lifecycle.
		$ok = $wpdb->insert(
			$table,
			array(
				'event_id' => $event_id,
				'ticket_product_id' => $product_id,
				'ticket_key' => $ticket_key,
				'buyer_user_id' => $buyer_user_id,
				'assignee_user_id' => $assignee_user_id,
				'assignee_email' => $assignee_email,
				'rule_path' => $rule_path,
				'direct_grant_id' => $direct_grant_id,
				'result' => $result,
				'reason_code' => $reason_code,
				'message' => $message,
				'context_json' => $context_json,
				'created_at' => current_time('mysql', true),
			),
			array(
				'%d',
				'%d',
				'%s',
				'%d',
				'%d',
				'%s',
				'%s',
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
			)
		);
		if ($ok === false) {
			return 0;
		}
		return absint($wpdb->insert_id);
	}
}

if (!function_exists('bvmgr_ticketing_claims_create_direct_grant')) {
	/**
	 * @param array<string,mixed> $grant
	 */
	function bvmgr_ticketing_claims_create_direct_grant(array $grant): int
	{
		global $wpdb;
		$table = bvmgr_ticketing_claims_table_direct_grants();

		$event_id = absint($grant['event_id'] ?? 0);
		$user_id = absint($grant['user_id'] ?? 0);
		if ($event_id <= 0 || $user_id <= 0) {
			return 0;
		}

		$grant_type = sanitize_key((string) ($grant['grant_type'] ?? 'event_ticket_eligibility'));
		if ($grant_type === '') {
			$grant_type = 'event_ticket_eligibility';
		}

		$ticket_product_id = absint($grant['ticket_product_id'] ?? 0);
		$ticket_key = sanitize_key((string) ($grant['ticket_key'] ?? ''));
		$credential_program = sanitize_key((string) ($grant['credential_program'] ?? ''));
		$qty_limit = max(0, absint($grant['qty_limit'] ?? 1));
		$status = sanitize_key((string) ($grant['status'] ?? 'active'));
		if (!in_array($status, bvmgr_ticketing_claims_allowed_grant_statuses(), true)) {
			$status = 'active';
		}
		$note = sanitize_text_field((string) ($grant['note'] ?? ''));
		$actor_user_id = absint($grant['actor_user_id'] ?? 0);
		$now = current_time('mysql', true);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Ticketing Claims direct grant creation persists normalized custom-table rows through wpdb::insert(); no core API preserves this repository lifecycle.
		$ok = $wpdb->insert(
			$table,
			array(
				'event_id' => $event_id,
				'user_id' => $user_id,
				'grant_type' => $grant_type,
				'ticket_product_id' => $ticket_product_id,
				'ticket_key' => $ticket_key,
				'credential_program' => $credential_program,
				'qty_limit' => $qty_limit,
				'qty_used' => 0,
				'status' => $status,
				'note' => $note,
				'created_at' => $now,
				'created_by' => $actor_user_id,
				'updated_at' => $now,
				'updated_by' => $actor_user_id,
			),
			array(
				'%d',
				'%d',
				'%s',
				'%d',
				'%s',
				'%s',
				'%d',
				'%d',
				'%s',
				'%s',
				'%s',
				'%d',
				'%s',
				'%d',
			)
		);
		if ($ok === false) {
			return 0;
		}

		return absint($wpdb->insert_id);
	}
}

if (!function_exists('bvmgr_ticketing_claims_allowed_grant_statuses')) {
	/**
	 * @return string[]
	 */
	function bvmgr_ticketing_claims_allowed_grant_statuses(): array
	{
		return array('active', 'reserved', 'used', 'revoked', 'expired');
	}
}

if (!function_exists('bvmgr_ticketing_claims_get_direct_grant')) {
	/**
	 * @return array<string,mixed>|null
	 */
	function bvmgr_ticketing_claims_get_direct_grant(int $grant_id): ?array
	{
		$grant_id = absint($grant_id);
		if ($grant_id <= 0) {
			return null;
		}

		global $wpdb;
		$table = bvmgr_ticketing_claims_table_direct_grants();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Ticketing Claims grant reads query the plugin-owned grants table with %i/%d-prepared values so admin and runtime flows reload fresh grant state after writes.
		$row = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id = %d', $table, $grant_id), ARRAY_A);
		return is_array($row) ? $row : null;
	}
}

if (!function_exists('bvmgr_ticketing_claims_get_direct_grants')) {
	/**
	 * @param array<string,mixed> $args
	 * @return array<int,array<string,mixed>>
	 */
	function bvmgr_ticketing_claims_get_direct_grants(array $args = array()): array
	{
		global $wpdb;
		$table = bvmgr_ticketing_claims_table_direct_grants();

		$event_id = absint($args['event_id'] ?? 0);
		$user_id = absint($args['user_id'] ?? 0);
		$status = sanitize_key((string) ($args['status'] ?? ''));
		$grant_type = sanitize_key((string) ($args['grant_type'] ?? ''));
		$ticket_product_id = absint($args['ticket_product_id'] ?? 0);
		$ticket_key = sanitize_key((string) ($args['ticket_key'] ?? ''));
		$credential_program = sanitize_key((string) ($args['credential_program'] ?? ''));
		$limit = max(1, min(500, absint($args['limit'] ?? 100)));
		$offset = max(0, absint($args['offset'] ?? 0));

		$where = array('1=1');
		$params = array();
		if ($event_id > 0) {
			$where[] = 'event_id = %d';
			$params[] = $event_id;
		}
		if ($user_id > 0) {
			$where[] = 'user_id = %d';
			$params[] = $user_id;
		}
		if ($status !== '' && $status !== 'any' && in_array($status, bvmgr_ticketing_claims_allowed_grant_statuses(), true)) {
			$where[] = 'status = %s';
			$params[] = $status;
		}
		if ($grant_type !== '') {
			$where[] = 'grant_type = %s';
			$params[] = $grant_type;
		}
		if ($ticket_product_id > 0) {
			$where[] = 'ticket_product_id = %d';
			$params[] = $ticket_product_id;
		}
		if ($ticket_key !== '') {
			$where[] = 'ticket_key = %s';
			$params[] = $ticket_key;
		}
		if ($credential_program !== '') {
			$where[] = 'credential_program = %s';
			$params[] = $credential_program;
		}

		$sql = 'SELECT * FROM %i WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d'; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Ticketing Claims grant list queries assemble only bounded literal WHERE fragments plus prepared identifier and pagination placeholders.
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Ticketing Claims grant list queries prepare only bounded literal WHERE fragments and the plugin-owned grants table identifier.
		$prepared = $wpdb->prepare($sql, array_merge(array($table), $params, array($limit, $offset)));
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Ticketing Claims grant list reads query the plugin-owned grants table so admin/customer views reload fresh grant state after writes.
		$rows = $wpdb->get_results($prepared, ARRAY_A);
		return is_array($rows) ? $rows : array();
	}
}

if (!function_exists('bvmgr_ticketing_claims_update_direct_grant_note')) {
	function bvmgr_ticketing_claims_update_direct_grant_note(int $grant_id, string $note, int $actor_user_id = 0): bool
	{
		$grant_id = absint($grant_id);
		if ($grant_id <= 0) {
			return false;
		}

		global $wpdb;
		$table = bvmgr_ticketing_claims_table_direct_grants();
		$note = sanitize_text_field($note);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Ticketing Claims grant-note updates write the plugin-owned grants table directly through wpdb::update(); no core API preserves this repository lifecycle.
		$ok = $wpdb->update(
			$table,
			array(
				'note' => $note,
				'updated_at' => current_time('mysql', true),
				'updated_by' => absint($actor_user_id),
			),
			array('id' => $grant_id),
			array('%s', '%s', '%d'),
			array('%d')
		);

		return $ok !== false;
	}
}

if (!function_exists('bvmgr_ticketing_claims_set_direct_grant_status')) {
	function bvmgr_ticketing_claims_set_direct_grant_status(int $grant_id, string $status, int $actor_user_id = 0): bool
	{
		$grant_id = absint($grant_id);
		$status = sanitize_key($status);
		if ($grant_id <= 0 || !in_array($status, bvmgr_ticketing_claims_allowed_grant_statuses(), true)) {
			return false;
		}

		$current = bvmgr_ticketing_claims_get_direct_grant($grant_id);
		if (!is_array($current)) {
			return false;
		}
		$old_status = sanitize_key((string) ($current['status'] ?? 'active'));
		$allowed_transitions = array(
			'active' => array('active', 'reserved', 'used', 'revoked', 'expired'),
			'reserved' => array('reserved', 'active', 'used', 'revoked', 'expired'),
			'used' => array('used', 'active', 'revoked'),
			'expired' => array('expired', 'active', 'revoked'),
			'revoked' => array('revoked', 'active', 'expired'),
		);
		$allowed_next = $allowed_transitions[$old_status] ?? array($old_status, 'active', 'revoked', 'expired');
		if (!in_array($status, $allowed_next, true)) {
			return false;
		}

		global $wpdb;
		$table = bvmgr_ticketing_claims_table_direct_grants();
		$update = array(
			'status' => $status,
			'updated_at' => current_time('mysql', true),
			'updated_by' => absint($actor_user_id),
		);
		$update_formats = array('%s', '%s', '%d');
		if ($status === 'used') {
			$qty_limit = max(0, absint($current['qty_limit'] ?? 0));
			$current_used = max(0, absint($current['qty_used'] ?? 0));
			$update['qty_used'] = $qty_limit > 0 ? max($current_used, $qty_limit) : max($current_used, 1);
			$update_formats[] = '%d';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Ticketing Claims grant-status transitions write the plugin-owned grants table directly through wpdb::update(); no core API preserves this repository lifecycle.
		$ok = $wpdb->update(
			$table,
			$update,
			array('id' => $grant_id),
			$update_formats,
			array('%d')
		);
		return $ok !== false;
	}
}

if (!function_exists('bvmgr_ticketing_claims_get_reservation')) {
	/**
	 * @return array<string,mixed>|null
	 */
	function bvmgr_ticketing_claims_get_reservation(int $reservation_id): ?array
	{
		$reservation_id = absint($reservation_id);
		if ($reservation_id <= 0) {
			return null;
		}
		global $wpdb;
		$table = bvmgr_ticketing_claims_table_reservations();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Ticketing Claims reservation reads query the plugin-owned reservations table with %i/%d-prepared values so admin/runtime flows reload fresh reservation state after writes.
		$row = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id = %d', $table, $reservation_id), ARRAY_A);
		return is_array($row) ? $row : null;
	}
}

if (!function_exists('bvmgr_ticketing_claims_get_reservations')) {
	/**
	 * @param array<string,mixed> $args
	 * @return array<int,array<string,mixed>>
	 */
	function bvmgr_ticketing_claims_get_reservations(array $args = array()): array
	{
		global $wpdb;
		$table = bvmgr_ticketing_claims_table_reservations();

		$event_id = absint($args['event_id'] ?? 0);
		$buyer_user_id = absint($args['buyer_user_id'] ?? 0);
		$assignee_user_id = absint($args['assignee_user_id'] ?? 0);
		$direct_grant_id = absint($args['direct_grant_id'] ?? 0);
		$status = sanitize_key((string) ($args['status'] ?? ''));
		$assignee_email = sanitize_email((string) ($args['assignee_email'] ?? ''));
		$ticket_product_id = absint($args['ticket_product_id'] ?? 0);
		$ticket_key = sanitize_key((string) ($args['ticket_key'] ?? ''));
		$limit = max(1, min(500, absint($args['limit'] ?? 100)));
		$offset = max(0, absint($args['offset'] ?? 0));

		$where = array('1=1');
		$params = array();
		if ($event_id > 0) {
			$where[] = 'event_id = %d';
			$params[] = $event_id;
		}
		if ($buyer_user_id > 0) {
			$where[] = 'buyer_user_id = %d';
			$params[] = $buyer_user_id;
		}
		if ($assignee_user_id > 0) {
			$where[] = 'assignee_user_id = %d';
			$params[] = $assignee_user_id;
		}
		if ($direct_grant_id > 0) {
			$where[] = 'direct_grant_id = %d';
			$params[] = $direct_grant_id;
		}
		if ($status !== '' && $status !== 'any') {
			$where[] = 'status = %s';
			$params[] = $status;
		}
		if ($assignee_email !== '') {
			$where[] = 'assignee_email = %s';
			$params[] = $assignee_email;
		}
		if ($ticket_product_id > 0) {
			$where[] = 'ticket_product_id = %d';
			$params[] = $ticket_product_id;
		}
		if ($ticket_key !== '') {
			$where[] = 'ticket_key = %s';
			$params[] = $ticket_key;
		}

		$sql = 'SELECT * FROM %i WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d'; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Ticketing Claims reservation list queries assemble only bounded literal WHERE fragments plus prepared identifier and pagination placeholders.
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Ticketing Claims reservation list queries prepare only bounded literal WHERE fragments and the plugin-owned reservations table identifier.
		$prepared = $wpdb->prepare($sql, array_merge(array($table), $params, array($limit, $offset)));
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Ticketing Claims reservation list reads query the plugin-owned reservations table so admin/runtime flows reload fresh reservation state after writes.
		$rows = $wpdb->get_results($prepared, ARRAY_A);
		return is_array($rows) ? $rows : array();
	}
}

if (!function_exists('bvmgr_ticketing_claims_release_reservation')) {
	function bvmgr_ticketing_claims_release_reservation(int $reservation_id, int $actor_user_id = 0): bool
	{
		$row = bvmgr_ticketing_claims_get_reservation($reservation_id);
		if (!$row || sanitize_key((string) ($row['status'] ?? '')) !== 'reserved') {
			return false;
		}

		global $wpdb;
		$table = bvmgr_ticketing_claims_table_reservations();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Ticketing Claims reservation releases write the plugin-owned reservations table directly through wpdb::update(); no core API preserves this repository lifecycle.
		$ok = $wpdb->update(
			$table,
			array(
				'status' => 'released',
				'released_at' => current_time('mysql', true),
			),
			array('id' => absint($row['id'] ?? 0)),
			array('%s', '%s'),
			array('%d')
		);
		if ($ok === false) {
			return false;
		}

		$grant_id = absint($row['direct_grant_id'] ?? 0);
		if ($grant_id > 0) {
			$grants_table = bvmgr_ticketing_claims_table_direct_grants();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Ticketing Claims reservation releases write the plugin-owned grants table directly with %i/%s/%d-prepared values so grant usage is restored in the same request.
			$wpdb->query(
				$wpdb->prepare(
					'UPDATE %i
					 SET qty_used = IF(qty_used > 0, qty_used - 1, 0),
						 updated_at = %s,
						 updated_by = %d
					 WHERE id = %d',
					$grants_table,
					current_time('mysql', true),
					absint($actor_user_id),
					$grant_id
				)
			);
		}

		return true;
	}
}

if (!function_exists('bvmgr_ticketing_claims_get_logs')) {
	/**
	 * @param array<string,mixed> $args
	 * @return array<int,array<string,mixed>>
	 */
	function bvmgr_ticketing_claims_get_logs(array $args = array()): array
	{
		global $wpdb;
		$table = bvmgr_ticketing_claims_table_log();

		$event_id = absint($args['event_id'] ?? 0);
		$ticket_product_id = absint($args['ticket_product_id'] ?? 0);
		$ticket_key = sanitize_key((string) ($args['ticket_key'] ?? ''));
		$buyer_user_id = absint($args['buyer_user_id'] ?? 0);
		$assignee_user_id = absint($args['assignee_user_id'] ?? 0);
		$assignee_email = sanitize_email((string) ($args['assignee_email'] ?? ''));
		$result = sanitize_key((string) ($args['result'] ?? ''));
		$reason_code = sanitize_key((string) ($args['reason_code'] ?? ''));
		$rule_path = sanitize_key((string) ($args['rule_path'] ?? ''));
		$direct_grant_only = !empty($args['direct_grant_only']);
		$credential_program = sanitize_key((string) ($args['credential_program'] ?? ''));
		$reservation_status = sanitize_key((string) ($args['reservation_status'] ?? ''));
		$limit = max(1, min(500, absint($args['limit'] ?? 100)));
		$offset = max(0, absint($args['offset'] ?? 0));

		$where = array('1=1');
		$params = array();
		if ($event_id > 0) {
			$where[] = 'event_id = %d';
			$params[] = $event_id;
		}
		if ($ticket_product_id > 0) {
			$where[] = 'ticket_product_id = %d';
			$params[] = $ticket_product_id;
		}
		if ($ticket_key !== '') {
			$where[] = 'ticket_key = %s';
			$params[] = $ticket_key;
		}
		if ($buyer_user_id > 0) {
			$where[] = 'buyer_user_id = %d';
			$params[] = $buyer_user_id;
		}
		if ($assignee_user_id > 0) {
			$where[] = 'assignee_user_id = %d';
			$params[] = $assignee_user_id;
		}
		if ($assignee_email !== '') {
			$where[] = 'assignee_email = %s';
			$params[] = $assignee_email;
		}
		if ($result !== '' && $result !== 'any') {
			$where[] = 'result = %s';
			$params[] = $result;
		}
		if ($reason_code !== '') {
			$where[] = 'reason_code = %s';
			$params[] = $reason_code;
		}
		if ($rule_path !== '') {
			$where[] = 'rule_path = %s';
			$params[] = $rule_path;
		}
		if ($credential_program !== '') {
			$where[] = '(context_json LIKE %s OR context_json LIKE %s)';
			$params[] = '%"matched_program":"' . $credential_program . '"%';
			$params[] = '%"allowed_programs":["' . $credential_program . '"%';
		}
		if ($direct_grant_only) {
			$where[] = '(direct_grant_id > 0 OR rule_path = %s)';
			$params[] = 'event_direct_grant';
		}
		if ($reservation_status !== '' && $reservation_status !== 'any') {
			$where[] = 'context_json LIKE %s';
			$params[] = '%"reservation_status":"' . $reservation_status . '"%';
		}

		$sql = 'SELECT * FROM %i WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d'; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Ticketing Claims log list queries assemble only bounded literal WHERE fragments plus prepared identifier and pagination placeholders.
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Ticketing Claims log list queries prepare only bounded literal WHERE fragments and the plugin-owned log table identifier.
		$prepared = $wpdb->prepare($sql, array_merge(array($table), $params, array($limit, $offset)));
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Ticketing Claims log list reads query the plugin-owned audit table so admin views reload fresh validation history after writes.
		$rows = $wpdb->get_results($prepared, ARRAY_A);
		return is_array($rows) ? $rows : array();
	}
}

if (!function_exists('bvmgr_ticketing_claims_decode_context_json')) {
	/**
	 * @return array<string,mixed>
	 */
	function bvmgr_ticketing_claims_decode_context_json(string $raw): array
	{
		$raw = trim($raw);
		if ($raw === '') {
			return array();
		}
		$decoded = json_decode($raw, true);
		return is_array($decoded) ? $decoded : array();
	}
}

if (!function_exists('bvmgr_ticketing_claims_friendly_reason')) {
	function bvmgr_ticketing_claims_friendly_reason(string $reason_code, string $message = ''): string
	{
		$reason_code = sanitize_key($reason_code);
		$message = trim(sanitize_text_field($message));
		if ($message !== '') {
			return $message;
		}

		$map = array(
			'ok' => __('Validation successful.', 'backstage-venue-manager'),
			'login_required' => __('Email must belong to a logged-in account.', 'backstage-venue-manager'),
			'no_rule_path' => __('No credential rule is configured for this ticket.', 'backstage-venue-manager'),
			'credential_not_approved' => __('Registered account exists but is not approved for this credential type.', 'backstage-venue-manager'),
			'direct_grant_missing' => __('No active direct event grant was found for this account.', 'backstage-venue-manager'),
			'grant_used' => __('This account has already used its benefit for this event.', 'backstage-venue-manager'),
			'grant_reserved' => __('This benefit is currently reserved in another pending cart.', 'backstage-venue-manager'),
			'grant_expired' => __('This event grant is expired and no longer available.', 'backstage-venue-manager'),
			'grant_revoked' => __('This event grant was revoked by an operator.', 'backstage-venue-manager'),
			'credential_or_grant_missing' => __('This account is not approved and has no direct grant for this event.', 'backstage-venue-manager'),
			'not_eligible' => __('This account is not eligible for this ticket.', 'backstage-venue-manager'),
			'grant_created' => __('Event benefit grant created.', 'backstage-venue-manager'),
			'grant_updated' => __('Event benefit grant updated.', 'backstage-venue-manager'),
			'grant_consumed' => __('Event benefit grant marked used.', 'backstage-venue-manager'),
			'grant_repaired_restored' => __('Event benefit grant restored to active.', 'backstage-venue-manager'),
			'grant_reopened' => __('Direct event grant reopened.', 'backstage-venue-manager'),
			'grant_note_updated' => __('Grant note updated.', 'backstage-venue-manager'),
			'self_apply_attempt' => __('Customer attempted to apply their own benefit.', 'backstage-venue-manager'),
			'recent_claim_helper_used' => __('Customer reused a recent claimant email helper.', 'backstage-venue-manager'),
			'reservation_created' => __('Reservation created.', 'backstage-venue-manager'),
			'reservation_released' => __('Reservation released.', 'backstage-venue-manager'),
			'consumption_completed' => __('Consumption completed.', 'backstage-venue-manager'),
			'manual_repair' => __('Manual repair action applied.', 'backstage-venue-manager'),
		);
		return $map[$reason_code] ?? ($reason_code !== '' ? ucwords(str_replace('_', ' ', $reason_code)) : __('No reason provided.', 'backstage-venue-manager'));
	}
}

if (!function_exists('bvmgr_ticketing_claims_recent_assignee_emails_for_buyer')) {
	/**
	 * @return string[]
	 */
	function bvmgr_ticketing_claims_recent_assignee_emails_for_buyer(int $buyer_user_id, int $limit = 5, int $event_id = 0): array
	{
		$buyer_user_id = absint($buyer_user_id);
		$event_id = absint($event_id);
		if ($buyer_user_id <= 0) {
			return array();
		}

		$limit = max(1, min(30, absint($limit)));

		global $wpdb;
		$table = bvmgr_ticketing_claims_table_log();
		$where = array(
			'buyer_user_id = %d',
			"assignee_email <> ''",
			"result = 'success'",
		);
		$params = array($buyer_user_id);
		if ($event_id > 0) {
			$where[] = 'event_id = %d';
			$params[] = $event_id;
		}

		$sql = 'SELECT assignee_email FROM %i WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC, id DESC LIMIT %d'; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Ticketing Claims helper-email lookups assemble only bounded literal WHERE fragments plus prepared identifier/limit placeholders.
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Ticketing Claims helper-email lookups prepare only bounded literal WHERE fragments and the plugin-owned log table identifier.
		$prepared = $wpdb->prepare($sql, array_merge(array($table), $params, array(250)));
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Ticketing Claims helper-email reads query the plugin-owned log table so customer flows can surface request-fresh prior assignee history.
		$rows = $wpdb->get_col($prepared);
		if (!is_array($rows) || empty($rows)) {
			return array();
		}

		$seen = array();
		$out = array();
		foreach ($rows as $raw_email) {
			$email = sanitize_email((string) $raw_email);
			if ($email === '' || isset($seen[$email])) {
				continue;
			}
			$seen[$email] = true;
			$out[] = $email;
			if (count($out) >= $limit) {
				break;
			}
		}

		return $out;
	}
}
