<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_social_queue_statuses')) {
	function vms_social_queue_statuses(): array
	{
		return array('draft', 'queued', 'posting', 'posted', 'failed', 'canceled', 'needs_review');
	}
}

if (!function_exists('vms_social_valid_queue_status')) {
	function vms_social_valid_queue_status(string $status): string
	{
		$status = sanitize_key($status);
		$allowed = vms_social_queue_statuses();
		return in_array($status, $allowed, true) ? $status : 'draft';
	}
}

if (!function_exists('vms_social_account_get')) {
	/**
	 * @return array<string,mixed>|null
	 */
	function vms_social_account_get(int $account_id): ?array
	{
		$account_id = absint($account_id);
		if ($account_id <= 0) {
			return null;
		}
		global $wpdb;
		$table = vms_social_table_accounts();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Social account reads query the plugin-owned accounts table with %i/%d-prepared values so authentication state reflects immediate repository writes.
		$row = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id = %d', $table, $account_id), ARRAY_A);
		return is_array($row) ? $row : null;
	}
}

if (!function_exists('vms_social_account_rows')) {
	/**
	 * @return array<int,array<string,mixed>>
	 */
	function vms_social_account_rows(string $platform = ''): array
	{
		global $wpdb;
		$table = vms_social_table_accounts();
		$platform = sanitize_key($platform);
		if ($platform === '') {
			$query = $wpdb->prepare('SELECT * FROM %i ORDER BY id DESC', $table);
		} else {
			$query = $wpdb->prepare('SELECT * FROM %i WHERE platform = %s ORDER BY id DESC', $table, $platform);
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Social account lists execute the immediately prepared query variable so administration reflects immediate account mutations.
		$rows = $wpdb->get_results($query, ARRAY_A);
		return is_array($rows) ? $rows : array();
	}
}

if (!function_exists('vms_social_account_token_json')) {
	/**
	 * @return array<string,mixed>
	 */
	function vms_social_account_token_json(int $account_id): array
	{
		$account = vms_social_account_get($account_id);
		if (!is_array($account)) {
			return array();
		}
		$enc = (string) ($account['token_blob_enc'] ?? '');
		if ($enc === '') {
			return array();
		}
		return vms_social_decrypt_json($enc);
	}
}

if (!function_exists('vms_social_account_save')) {
	function vms_social_account_save(array $payload): int
	{
		global $wpdb;
		$table = vms_social_table_accounts();

		$id = absint($payload['id'] ?? 0);
		$platform = sanitize_key((string) ($payload['platform'] ?? ''));
		$label = sanitize_text_field((string) ($payload['label'] ?? ''));
		$auth_state = sanitize_key((string) ($payload['auth_state'] ?? 'error'));
		$meta_json = wp_json_encode((array) ($payload['meta_json'] ?? array()));
		$token_blob_enc = '';
		if (isset($payload['token_json']) && is_array($payload['token_json'])) {
			$token_blob_enc = vms_social_encrypt_json((array) $payload['token_json']);
		}

		$now = vms_social_now_mysql_utc();
		if ($id > 0) {
			$update = array(
				'platform' => $platform,
				'label' => $label,
				'auth_state' => $auth_state,
				'meta_json' => is_string($meta_json) ? $meta_json : '{}',
				'updated_at' => $now,
			);
			$formats = array('%s', '%s', '%s', '%s', '%s');
			if ($token_blob_enc !== '') {
				$update['token_blob_enc'] = $token_blob_enc;
				$formats[] = '%s';
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Social account updates write the plugin-owned accounts table directly so authentication and token state persist atomically in the current request.
			$wpdb->update($table, $update, array('id' => $id), $formats, array('%d'));
			return $id;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Social account creation persists normalized custom-table rows through wpdb::insert(); no core API preserves this repository contract.
		$wpdb->insert(
			$table,
			array(
				'platform' => $platform,
				'label' => $label,
				'auth_state' => $auth_state,
				'token_blob_enc' => $token_blob_enc,
				'meta_json' => is_string($meta_json) ? $meta_json : '{}',
				'created_at' => $now,
				'updated_at' => $now,
			),
			array('%s', '%s', '%s', '%s', '%s', '%s', '%s')
		);
		return (int) $wpdb->insert_id;
	}
}

if (!function_exists('vms_social_account_delete')) {
	function vms_social_account_delete(int $account_id): bool
	{
		global $wpdb;
		$table = vms_social_table_accounts();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Social account deletion removes the plugin-owned account row immediately; no core deletion API owns this custom table.
		$deleted = $wpdb->delete($table, array('id' => absint($account_id)), array('%d'));
		return (bool) $deleted;
	}
}

if (!function_exists('vms_social_account_set_auth_state')) {
	function vms_social_account_set_auth_state(int $account_id, string $auth_state, array $meta_patch = array()): bool
	{
		$account = vms_social_account_get($account_id);
		if (!is_array($account)) {
			return false;
		}

		$meta = json_decode((string) ($account['meta_json'] ?? ''), true);
		$meta = is_array($meta) ? $meta : array();
		$meta = array_merge($meta, $meta_patch);

		$saved = vms_social_account_save(array(
			'id' => $account_id,
			'platform' => (string) ($account['platform'] ?? ''),
			'label' => (string) ($account['label'] ?? ''),
			'auth_state' => sanitize_key($auth_state),
			'meta_json' => $meta,
		));

		return $saved > 0;
	}
}

if (!function_exists('vms_social_venue_map_rows')) {
	/**
	 * @return array<int,array<string,mixed>>
	 */
	function vms_social_venue_map_rows(int $venue_id = 0): array
	{
		global $wpdb;
		$table = vms_social_table_venue_map();
		$venue_id = absint($venue_id);
		if ($venue_id > 0) {
			$sql = $wpdb->prepare('SELECT * FROM %i WHERE venue_id = %d ORDER BY id DESC', $table, $venue_id);
		} else {
			$sql = $wpdb->prepare('SELECT * FROM %i ORDER BY id DESC', $table);
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Social venue-map lists execute the immediately prepared query variable so routing reflects immediate mapping mutations.
		$rows = $wpdb->get_results($sql, ARRAY_A);
		return is_array($rows) ? $rows : array();
	}
}

if (!function_exists('vms_social_venue_map_for_platform')) {
	/**
	 * @return array<string,mixed>|null
	 */
	function vms_social_venue_map_for_platform(int $venue_id, string $platform): ?array
	{
		$venue_id = absint($venue_id);
		$platform = sanitize_key($platform);
		if ($venue_id <= 0 || $platform === '') {
			return null;
		}

		global $wpdb;
		$table = vms_social_table_venue_map();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Social destination lookups query the plugin-owned venue mapping table with fully prepared identifiers and values so queue routing uses current mapping state.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE venue_id = %d AND platform = %s AND is_enabled = 1 ORDER BY id DESC LIMIT 1',
				$table,
				$venue_id,
				$platform
			),
			ARRAY_A
		);
		return is_array($row) ? $row : null;
	}
}

if (!function_exists('vms_social_venue_map_save')) {
	function vms_social_venue_map_save(array $payload): int
	{
		global $wpdb;
		$table = vms_social_table_venue_map();
		$id = absint($payload['id'] ?? 0);
		$now = vms_social_now_mysql_utc();
		$row = array(
			'venue_id' => absint($payload['venue_id'] ?? 0),
			'platform' => sanitize_key((string) ($payload['platform'] ?? '')),
			'account_id' => absint($payload['account_id'] ?? 0),
			'destination_id' => sanitize_text_field((string) ($payload['destination_id'] ?? '')),
			'is_enabled' => empty($payload['is_enabled']) ? 0 : 1,
			'default_template_id' => absint($payload['default_template_id'] ?? 0),
			'updated_at' => $now,
		);

		if ($id > 0) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Social venue-map updates write the plugin-owned mapping table directly so destination configuration persists immediately.
			$wpdb->update(
				$table,
				$row,
				array('id' => $id),
				array('%d', '%s', '%d', '%s', '%d', '%d', '%s'),
				array('%d')
			);
			return $id;
		}

		$row['created_at'] = $now;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Social venue-map creation persists normalized custom-table rows through wpdb::insert(); no core API preserves this repository contract.
		$wpdb->insert(
			$table,
			$row,
			array('%d', '%s', '%d', '%s', '%d', '%d', '%s', '%s')
		);
		return (int) $wpdb->insert_id;
	}
}

if (!function_exists('vms_social_venue_map_delete')) {
	function vms_social_venue_map_delete(int $id): bool
	{
		global $wpdb;
		$table = vms_social_table_venue_map();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Social venue-map deletion removes the plugin-owned mapping row immediately; no core deletion API owns this custom table.
		$deleted = $wpdb->delete($table, array('id' => absint($id)), array('%d'));
		return (bool) $deleted;
	}
}

if (!function_exists('vms_social_templates_all')) {
	/**
	 * @return array<int,array<string,mixed>>
	 */
	function vms_social_templates_all(string $platform = ''): array
	{
		global $wpdb;
		$table = vms_social_table_templates();
		$platform = sanitize_key($platform);
		if ($platform === '') {
			$query = $wpdb->prepare('SELECT * FROM %i ORDER BY platform ASC, id DESC', $table);
		} else {
			$query = $wpdb->prepare('SELECT * FROM %i WHERE platform = %s ORDER BY id DESC', $table, $platform);
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Social template lists execute the immediately prepared query variable so administration reflects immediate template mutations.
		$rows = $wpdb->get_results($query, ARRAY_A);
		return is_array($rows) ? $rows : array();
	}
}

if (!function_exists('vms_social_template_save')) {
	function vms_social_template_save(array $payload): int
	{
		global $wpdb;
		$table = vms_social_table_templates();
		$id = absint($payload['id'] ?? 0);
		$platform = sanitize_key((string) ($payload['platform'] ?? ''));
		$name = sanitize_text_field((string) ($payload['name'] ?? ''));
		$body = (string) ($payload['body'] ?? '');
		$is_default = empty($payload['is_default']) ? 0 : 1;
		$settings_json = wp_json_encode((array) ($payload['settings_json'] ?? array()));
		$now = vms_social_now_mysql_utc();

		if ($is_default && $platform !== '') {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Default-template reset writes the plugin-owned templates table with %i/%s-prepared values immediately before the replacement row is saved.
			$wpdb->query($wpdb->prepare('UPDATE %i SET is_default = 0 WHERE platform = %s', $table, $platform));
		}

		$row = array(
			'platform' => $platform,
			'name' => $name,
			'body' => $body,
			'settings_json' => is_string($settings_json) ? $settings_json : '{}',
			'is_default' => $is_default,
			'updated_at' => $now,
		);

		if ($id > 0) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Social template updates write the plugin-owned templates table directly so rendering configuration persists immediately.
			$wpdb->update($table, $row, array('id' => $id), array('%s', '%s', '%s', '%s', '%d', '%s'), array('%d'));
			return $id;
		}

		$row['created_at'] = $now;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Social template creation persists normalized custom-table rows through wpdb::insert(); no core API preserves this repository contract.
		$wpdb->insert($table, $row, array('%s', '%s', '%s', '%s', '%d', '%s', '%s'));
		return (int) $wpdb->insert_id;
	}
}

if (!function_exists('vms_social_template_delete')) {
	function vms_social_template_delete(int $id): bool
	{
		global $wpdb;
		$table = vms_social_table_templates();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Social template deletion removes the plugin-owned template row immediately; no core deletion API owns this custom table.
		$deleted = $wpdb->delete($table, array('id' => absint($id)), array('%d'));
		return (bool) $deleted;
	}
}

if (!function_exists('vms_social_queue_get')) {
	/**
	 * @return array<string,mixed>|null
	 */
	function vms_social_queue_get(int $queue_id): ?array
	{
		$queue_id = absint($queue_id);
		if ($queue_id <= 0) {
			return null;
		}
		global $wpdb;
		$table = vms_social_table_queue();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Social queue item reads query the plugin-owned queue table with %i/%d-prepared values so workers and controls observe current state transitions.
		$row = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id = %d', $table, $queue_id), ARRAY_A);
		return is_array($row) ? $row : null;
	}
}

if (!function_exists('vms_social_queue_latest_for_event')) {
	/**
	 * @return array<string,mixed>|null
	 */
	function vms_social_queue_latest_for_event(int $event_plan_id): ?array
	{
		$event_plan_id = absint($event_plan_id);
		if ($event_plan_id <= 0) {
			return null;
		}
		global $wpdb;
		$table = vms_social_table_queue();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Latest-event queue reads query the plugin-owned queue table with %i/%d-prepared values so event controls observe the newest repository mutation.
		$row = $wpdb->get_row(
			$wpdb->prepare('SELECT * FROM %i WHERE event_plan_id = %d ORDER BY id DESC LIMIT 1', $table, $event_plan_id),
			ARRAY_A
		);
		return is_array($row) ? $row : null;
	}
}

if (!function_exists('vms_social_queue_create')) {
	/**
	 * @param array<string,mixed> $payload
	 */
	function vms_social_queue_create(array $payload): int
	{
		global $wpdb;
		$table = vms_social_table_queue();

		$scheduled = (string) ($payload['scheduled_at_utc'] ?? '');
		if ($scheduled === '') {
			$scheduled = vms_social_now_mysql_utc();
		}

		$status = vms_social_valid_queue_status((string) ($payload['status'] ?? 'queued'));
		if ($status === 'draft') {
			$status = 'queued';
		}

		$snapshot = $payload['payload_snapshot_json'] ?? array();
		if (!is_string($snapshot)) {
			$snapshot = wp_json_encode($snapshot);
		}
		if (!is_string($snapshot)) {
			$snapshot = '{}';
		}

		$now = vms_social_now_mysql_utc();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Social queue creation persists the normalized snapshot and scheduling state through wpdb::insert(); no core API preserves this repository lifecycle.
		$wpdb->insert(
			$table,
			array(
				'event_plan_id' => absint($payload['event_plan_id'] ?? 0),
				'tec_event_id' => absint($payload['tec_event_id'] ?? 0),
				'venue_id' => absint($payload['venue_id'] ?? 0),
				'platform' => sanitize_key((string) ($payload['platform'] ?? 'mock')),
				'destination_id' => sanitize_text_field((string) ($payload['destination_id'] ?? '')),
				'template_id' => absint($payload['template_id'] ?? 0),
				'status' => $status,
				'scheduled_at_utc' => $scheduled,
				'attempts' => absint($payload['attempts'] ?? 0),
				'next_attempt_at_utc' => !empty($payload['next_attempt_at_utc']) ? (string) $payload['next_attempt_at_utc'] : null,
				'platform_post_id' => sanitize_text_field((string) ($payload['platform_post_id'] ?? '')),
				'payload_snapshot_json' => $snapshot,
				'last_error_code' => sanitize_key((string) ($payload['last_error_code'] ?? '')),
				'last_error_message' => sanitize_text_field((string) ($payload['last_error_message'] ?? '')),
				'created_by' => (int) ($payload['created_by'] ?? get_current_user_id()),
				'updated_by' => (int) ($payload['updated_by'] ?? get_current_user_id()),
				'created_at' => $now,
				'updated_at' => $now,
			),
			array('%d', '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s')
		);

		return (int) $wpdb->insert_id;
	}
}

if (!function_exists('vms_social_queue_list')) {
	/**
	 * @param array<string,mixed> $filters
	 * @return array<int,array<string,mixed>>
	 */
	function vms_social_queue_list(array $filters = array(), int $limit = 100): array
	{
		global $wpdb;
		$table = vms_social_table_queue();
		$where = array('1=1');
		$params = array();

		$status = sanitize_key((string) ($filters['status'] ?? ''));
		if ($status !== '') {
			$where[] = 'status = %s';
			$params[] = $status;
		}

		$platform = sanitize_key((string) ($filters['platform'] ?? ''));
		if ($platform !== '') {
			$where[] = 'platform = %s';
			$params[] = $platform;
		}

		$venue_id = absint($filters['venue_id'] ?? 0);
		if ($venue_id > 0) {
			$where[] = 'venue_id = %d';
			$params[] = $venue_id;
		}

		$event_plan_id = absint($filters['event_plan_id'] ?? 0);
		if ($event_plan_id > 0) {
			$where[] = 'event_plan_id = %d';
			$params[] = $event_plan_id;
		}

		$limit = max(1, min(500, $limit));
		$sql = 'SELECT * FROM %i WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT %d'; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Queue lists assemble only bounded literal filter fragments plus prepared identifier, value, and limit placeholders.
		array_unshift($params, $table);
		$params[] = $limit;
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Queue lists prepare only bounded literal filters and the plugin-owned queue-table identifier.
		$query = $wpdb->prepare($sql, $params);
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Queue lists read request-fresh custom-table state for worker/admin controls after queue mutations.
		$rows = $wpdb->get_results($query, ARRAY_A);
		return is_array($rows) ? $rows : array();
	}
}

if (!function_exists('vms_social_queue_due_items')) {
	/**
	 * @return array<int,array<string,mixed>>
	 */
	function vms_social_queue_due_items(int $limit = 20): array
	{
		global $wpdb;
		$table = vms_social_table_queue();
		$now = vms_social_now_mysql_utc();
		$limit = max(1, min(100, $limit));
		$sql = $wpdb->prepare(
			"SELECT * FROM %i
			 WHERE status = 'queued'
			   AND (scheduled_at_utc IS NULL OR scheduled_at_utc <= %s)
			   AND (next_attempt_at_utc IS NULL OR next_attempt_at_utc <= %s)
			 ORDER BY scheduled_at_utc ASC, id ASC
			 LIMIT %d",
			$table,
			$now,
			$now,
			$limit
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Due-item selection executes the immediately prepared query variable so ordering/retry eligibility remains current for workers.
		$rows = $wpdb->get_results($sql, ARRAY_A);
		return is_array($rows) ? $rows : array();
	}
}

if (!function_exists('vms_social_queue_claim')) {
	function vms_social_queue_claim(int $queue_id): bool
	{
		$queue_id = absint($queue_id);
		if ($queue_id <= 0) {
			return false;
		}
		global $wpdb;
		$table = vms_social_table_queue();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic queue claiming writes the plugin-owned queue table with fully prepared values and the queued-state predicate to preserve worker concurrency.
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE %i SET status = 'posting', updated_at = %s WHERE id = %d AND status = 'queued'",
				$table,
				vms_social_now_mysql_utc(),
				$queue_id
			)
		);
		return (int) $updated === 1;
	}
}

if (!function_exists('vms_social_queue_update')) {
	function vms_social_queue_update(int $queue_id, array $fields): bool
	{
		$queue_id = absint($queue_id);
		if ($queue_id <= 0 || empty($fields)) {
			return false;
		}

		$allowed = array(
			'status' => '%s',
			'attempts' => '%d',
			'next_attempt_at_utc' => '%s',
			'platform_post_id' => '%s',
			'payload_snapshot_json' => '%s',
			'last_error_code' => '%s',
			'last_error_message' => '%s',
			'updated_by' => '%d',
			'scheduled_at_utc' => '%s',
			'template_id' => '%d',
			'destination_id' => '%s',
		);

		$update = array();
		$formats = array();
		foreach ($fields as $k => $v) {
			if (!isset($allowed[$k])) {
				continue;
			}
			if ($k === 'status') {
				$v = vms_social_valid_queue_status((string) $v);
			}
			if ($k === 'last_error_code') {
				$v = sanitize_key((string) $v);
			}
			if ($k === 'last_error_message' || $k === 'destination_id' || $k === 'platform_post_id') {
				$v = sanitize_text_field((string) $v);
			}
			$update[$k] = $v;
			$formats[] = $allowed[$k];
		}

		if (empty($update)) {
			return false;
		}

		$update['updated_at'] = vms_social_now_mysql_utc();
		$formats[] = '%s';

		global $wpdb;
		$table = vms_social_table_queue();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Queue state transitions write only the bounded allowlist to the plugin-owned queue table so worker/admin mutations persist immediately.
		$ok = $wpdb->update($table, $update, array('id' => $queue_id), $formats, array('%d'));
		return $ok !== false;
	}
}

if (!function_exists('vms_social_queue_cancel')) {
	function vms_social_queue_cancel(int $queue_id): bool
	{
		return vms_social_queue_update(
			$queue_id,
			array(
				'status' => 'canceled',
				'next_attempt_at_utc' => null,
				'updated_by' => get_current_user_id(),
			)
		);
	}
}

if (!function_exists('vms_social_queue_retry')) {
	function vms_social_queue_retry(int $queue_id): bool
	{
		return vms_social_queue_update(
			$queue_id,
			array(
				'status' => 'queued',
				'next_attempt_at_utc' => null,
				'last_error_code' => '',
				'last_error_message' => '',
				'updated_by' => get_current_user_id(),
			)
		);
	}
}
