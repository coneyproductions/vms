<?php
defined('ABSPATH') || exit;

if (!function_exists('bvmgr_admission_offers_db_option_key')) {
	function bvmgr_admission_offers_db_option_key(): string
	{
		return 'vms_admission_offers_db_version';
	}
}

if (!function_exists('bvmgr_admission_offers_db_version_target')) {
	function bvmgr_admission_offers_db_version_target(): string
	{
		return '1.1.0';
	}
}

if (!function_exists('bvmgr_admission_offers_table')) {
	function bvmgr_admission_offers_table(string $kind): string
	{
		global $wpdb;
		$tables = array(
			'offers' => 'vms_admission_offers',
			'eligibility' => 'vms_admission_offer_eligibility',
			'claims' => 'vms_admission_offer_claims',
			'identities' => 'vms_admission_offer_claim_identities',
			'reservations' => 'vms_admission_offer_reservations',
			'checkouts' => 'vms_admission_offer_checkouts',
			'allocations' => 'vms_admission_offer_order_allocations',
			'fulfillments' => 'vms_admission_offer_fulfillments',
			'events' => 'vms_admission_offer_events',
		);

		return isset($tables[$kind]) ? $wpdb->prefix . $tables[$kind] : '';
	}
}

if (!function_exists('bvmgr_admission_offers_table_names')) {
	/** @return array<string,string> */
	function bvmgr_admission_offers_table_names(): array
	{
		$result = array();
		foreach (array('offers', 'eligibility', 'claims', 'identities', 'reservations', 'checkouts', 'allocations', 'fulfillments', 'events') as $kind) {
			$result[$kind] = bvmgr_admission_offers_table($kind);
		}

		return $result;
	}
}

if (!function_exists('bvmgr_admission_offers_schema_sql')) {
	/** @return array<string,string> */
	function bvmgr_admission_offers_schema_sql(): array
	{
		global $wpdb;
		$tables = bvmgr_admission_offers_table_names();
		$charset_collate = $wpdb->get_charset_collate();
		$engine = 'ENGINE=InnoDB';

		return array(
			'offers' => "CREATE TABLE {$tables['offers']} (
				id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				public_id VARCHAR(40) NOT NULL,
				name VARCHAR(190) NOT NULL,
				offer_type VARCHAR(20) NOT NULL,
				percent_basis_points SMALLINT(5) UNSIGNED NULL,
				fixed_amount_minor BIGINT(20) UNSIGNED NULL,
				currency CHAR(3) NULL,
				status VARCHAR(20) NOT NULL DEFAULT 'draft',
				source_id BIGINT(20) UNSIGNED NULL,
				max_qty_per_claim SMALLINT(5) UNSIGNED NOT NULL DEFAULT 1,
				capacity_total BIGINT(20) UNSIGNED NULL,
				reservation_ttl_seconds INT(10) UNSIGNED NOT NULL DEFAULT 1200,
				claim_expires_at DATETIME NULL,
				stacking_policy VARCHAR(32) NOT NULL DEFAULT 'exclusive',
				identity_policy_json LONGTEXT NOT NULL,
				identity_policy_version INT(10) UNSIGNED NOT NULL DEFAULT 1,
				state_version BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
				created_by BIGINT(20) UNSIGNED NOT NULL,
				created_at DATETIME NOT NULL,
				updated_by BIGINT(20) UNSIGNED NULL,
				updated_at DATETIME NULL,
				ended_at DATETIME NULL,
				revoked_at DATETIME NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY public_id (public_id),
				KEY offer_status (status, offer_type),
				KEY source_status (source_id, status)
			) {$engine} {$charset_collate};",
			'eligibility' => "CREATE TABLE {$tables['eligibility']} (
				id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				offer_id BIGINT(20) UNSIGNED NOT NULL,
				scope_type VARCHAR(20) NOT NULL,
				mode VARCHAR(10) NOT NULL DEFAULT 'include',
				event_plan_id BIGINT(20) UNSIGNED NULL,
				venue_id BIGINT(20) UNSIGNED NULL,
				start_date DATE NULL,
				end_date DATE NULL,
				season_key VARCHAR(120) NULL,
				eligibility_key CHAR(64) NOT NULL,
				created_by BIGINT(20) UNSIGNED NOT NULL,
				created_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY offer_eligibility (offer_id, eligibility_key),
				KEY event_plan_scope (event_plan_id, mode),
				KEY venue_scope (venue_id, mode),
				KEY date_scope (start_date, end_date),
				KEY season_scope (season_key, mode)
			) {$engine} {$charset_collate};",
			'claims' => "CREATE TABLE {$tables['claims']} (
				id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				public_id VARCHAR(40) NOT NULL,
				offer_id BIGINT(20) UNSIGNED NOT NULL,
				event_plan_id BIGINT(20) UNSIGNED NOT NULL,
				quantity SMALLINT(5) UNSIGNED NOT NULL,
				status VARCHAR(24) NOT NULL DEFAULT 'claimed',
				claimant_first_name VARCHAR(120) NULL,
				claimant_last_name VARCHAR(120) NULL,
				claimant_email VARCHAR(190) NULL,
				claimant_email_norm VARCHAR(190) NULL,
				claimant_phone VARCHAR(40) NULL,
				claimant_phone_norm VARCHAR(40) NULL,
				claimant_account_id BIGINT(20) UNSIGNED NULL,
				attribution_source VARCHAR(120) NULL,
				distribution_provider VARCHAR(80) NULL,
				distribution_mode VARCHAR(40) NULL,
				campaign_ref VARCHAR(190) NULL,
				distribution_subject_ref VARCHAR(190) NULL,
				access_secret_hash CHAR(64) NOT NULL,
				expires_at DATETIME NULL,
				state_version BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
				created_by BIGINT(20) UNSIGNED NULL,
				created_at DATETIME NOT NULL,
				updated_by BIGINT(20) UNSIGNED NULL,
				updated_at DATETIME NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY public_id (public_id),
				UNIQUE KEY access_secret_hash (access_secret_hash),
				KEY offer_status (offer_id, status),
				KEY event_status (event_plan_id, status),
				KEY distribution_context (distribution_provider, distribution_mode),
				KEY distribution_campaign (distribution_provider, campaign_ref),
				KEY distribution_subject (distribution_provider, distribution_subject_ref)
			) {$engine} {$charset_collate};",
			'identities' => "CREATE TABLE {$tables['identities']} (
				id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				claim_id BIGINT(20) UNSIGNED NOT NULL,
				offer_id BIGINT(20) UNSIGNED NOT NULL,
				identity_scope_key VARCHAR(191) NOT NULL,
				identity_type VARCHAR(32) NOT NULL,
				hash_key_version SMALLINT(5) UNSIGNED NOT NULL,
				identity_hash CHAR(64) NOT NULL,
				created_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY scoped_identity (offer_id, identity_scope_key, identity_type, hash_key_version, identity_hash),
				KEY claim_identity (claim_id, identity_type),
				KEY scope_versions (offer_id, identity_scope_key, identity_type, hash_key_version)
			) {$engine} {$charset_collate};",
			'reservations' => "CREATE TABLE {$tables['reservations']} (
				id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				public_id VARCHAR(40) NOT NULL,
				offer_id BIGINT(20) UNSIGNED NOT NULL,
				claim_id BIGINT(20) UNSIGNED NOT NULL,
				event_plan_id BIGINT(20) UNSIGNED NOT NULL,
				quantity SMALLINT(5) UNSIGNED NOT NULL,
				state VARCHAR(24) NOT NULL DEFAULT 'held',
				idempotency_key_hash CHAR(64) NOT NULL,
				expires_at DATETIME NOT NULL,
				order_attached_at DATETIME NULL,
				consumed_at DATETIME NULL,
				released_at DATETIME NULL,
				expired_at DATETIME NULL,
				exception_code VARCHAR(80) NULL,
				state_version BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY public_id (public_id),
				UNIQUE KEY offer_idempotency (offer_id, idempotency_key_hash),
				KEY offer_capacity (offer_id, state, expires_at),
				KEY claim_state (claim_id, state),
				KEY event_state (event_plan_id, state)
			) {$engine} {$charset_collate};",
			'checkouts' => "CREATE TABLE {$tables['checkouts']} (
				id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				public_id VARCHAR(40) NOT NULL,
				offer_id BIGINT(20) UNSIGNED NOT NULL,
				claim_id BIGINT(20) UNSIGNED NOT NULL,
				reservation_id BIGINT(20) UNSIGNED NULL,
				state VARCHAR(24) NOT NULL DEFAULT 'checkout_started',
				checkout_provider VARCHAR(80) NULL,
				checkout_ref VARCHAR(190) NULL,
				order_provider VARCHAR(80) NULL,
				order_ref VARCHAR(190) NULL,
				currency CHAR(3) NOT NULL,
				eligible_subtotal_minor BIGINT(20) UNSIGNED NOT NULL,
				discount_minor BIGINT(20) UNSIGNED NOT NULL,
				total_minor BIGINT(20) UNSIGNED NOT NULL,
				state_version BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NULL,
				paid_at DATETIME NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY public_id (public_id),
				UNIQUE KEY checkout_provider_ref (checkout_provider, checkout_ref),
				KEY claim_state (claim_id, state),
				KEY order_lookup (order_provider, order_ref)
			) {$engine} {$charset_collate};",
			'allocations' => "CREATE TABLE {$tables['allocations']} (
				id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				checkout_id BIGINT(20) UNSIGNED NOT NULL,
				claim_id BIGINT(20) UNSIGNED NOT NULL,
				offer_id BIGINT(20) UNSIGNED NOT NULL,
				event_plan_id BIGINT(20) UNSIGNED NOT NULL,
				order_provider VARCHAR(80) NOT NULL,
				order_ref VARCHAR(190) NOT NULL,
				order_item_ref VARCHAR(190) NOT NULL,
				product_ref VARCHAR(190) NULL,
				quantity SMALLINT(5) UNSIGNED NOT NULL,
				eligible_subtotal_minor BIGINT(20) UNSIGNED NOT NULL,
				discount_minor BIGINT(20) UNSIGNED NOT NULL,
				currency CHAR(3) NOT NULL,
				created_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY order_item_claim (order_provider, order_ref, order_item_ref, claim_id),
				KEY checkout_id (checkout_id),
				KEY event_plan_id (event_plan_id)
			) {$engine} {$charset_collate};",
			'fulfillments' => "CREATE TABLE {$tables['fulfillments']} (
				id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				public_id VARCHAR(40) NOT NULL,
				offer_id BIGINT(20) UNSIGNED NOT NULL,
				claim_id BIGINT(20) UNSIGNED NOT NULL,
				event_plan_id BIGINT(20) UNSIGNED NOT NULL,
				quantity SMALLINT(5) UNSIGNED NOT NULL,
				state VARCHAR(24) NOT NULL DEFAULT 'pending',
				fulfillment_provider VARCHAR(80) NOT NULL,
				provider_ref VARCHAR(190) NULL,
				credential_ref VARCHAR(190) NULL,
				state_version BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
				fulfilled_at DATETIME NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY public_id (public_id),
				UNIQUE KEY provider_fulfillment (fulfillment_provider, provider_ref),
				KEY claim_state (claim_id, state),
				KEY event_state (event_plan_id, state)
			) {$engine} {$charset_collate};",
			'events' => "CREATE TABLE {$tables['events']} (
				id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				entity_type VARCHAR(32) NOT NULL,
				entity_id BIGINT(20) UNSIGNED NOT NULL,
				event_key VARCHAR(190) NOT NULL,
				previous_state VARCHAR(24) NULL,
				new_state VARCHAR(24) NULL,
				actor_user_id BIGINT(20) UNSIGNED NULL,
				actor_type VARCHAR(40) NULL,
				provider VARCHAR(80) NULL,
				payload_redacted LONGTEXT NULL,
				occurred_at DATETIME NOT NULL,
				created_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY entity_event (entity_type, entity_id, event_key),
				KEY entity_time (entity_type, entity_id, occurred_at),
				KEY occurred_at (occurred_at)
			) {$engine} {$charset_collate};",
		);
	}
}

if (!function_exists('bvmgr_admission_offers_db_ready')) {
	function bvmgr_admission_offers_db_ready(): bool
	{
		global $wpdb;
		foreach (bvmgr_admission_offers_table_names() as $table) {
			$table_like = $wpdb->esc_like($table);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Dormant module readiness checks its own custom tables directly.
			$exists = (string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_like));
			if ($exists !== $table) {
				return false;
			}
		}

		return true;
	}
}

if (!function_exists('bvmgr_admission_offers_prepare_110_upgrade')) {
	function bvmgr_admission_offers_prepare_110_upgrade(string $current): void
	{
		if ($current !== '' && version_compare($current, '1.1.0', '>=')) {
			return;
		}
		global $wpdb;
		$checks = array(
			'identities' => array('scoped_identity', array('offer_id', 'identity_scope_key', 'identity_type', 'hash_key_version', 'identity_hash')),
			'allocations' => array('order_item_claim', array('order_provider', 'order_ref', 'order_item_ref', 'claim_id')),
		);
		foreach ($checks as $kind => [$index_name, $expected_columns]) {
			$table = bvmgr_admission_offers_table($kind);
			$exists = (string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
			if ($exists !== $table) {
				continue;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Version-gated inspection of a plugin-owned index before dbDelta.
			$rows = $wpdb->get_results($wpdb->prepare('SHOW INDEX FROM %i WHERE Key_name = %s', $table, $index_name), ARRAY_A);
			if (!is_array($rows) || $rows === array()) {
				continue;
			}
			usort($rows, static fn(array $left, array $right): int => (int) $left['Seq_in_index'] <=> (int) $right['Seq_in_index']);
			$columns = array_map(static fn(array $row): string => (string) $row['Column_name'], $rows);
			$has_prefix = array_filter($rows, static fn(array $row): bool => !empty($row['Sub_part'])) !== array();
			if ($columns === $expected_columns && !$has_prefix) {
				continue;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- dbDelta cannot replace a same-named index with a changed shape.
			if ($wpdb->query($wpdb->prepare('ALTER TABLE %i DROP INDEX %i', $table, $index_name)) === false) {
				throw new BVMGR_Admission_Offer_Domain_Exception('schema_index_upgrade_failed');
			}
		}
	}
}

if (!function_exists('bvmgr_admission_offers_maybe_upgrade_schema')) {
	function bvmgr_admission_offers_maybe_upgrade_schema(): void
	{
		$current = (string) get_option(bvmgr_admission_offers_db_option_key(), '');
		$target = bvmgr_admission_offers_db_version_target();
		if ($current === $target && bvmgr_admission_offers_db_ready()) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		bvmgr_admission_offers_prepare_110_upgrade($current);
		foreach (bvmgr_admission_offers_schema_sql() as $sql) {
			dbDelta($sql);
		}

		if (bvmgr_admission_offers_db_ready()) {
			update_option(bvmgr_admission_offers_db_option_key(), $target, false);
		}
	}
}
