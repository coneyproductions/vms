<?php
/** Explicit retirement for referentially detached Staffing history. */
defined('ABSPATH') || exit;

/** @return array<string,array<int,array<string,mixed>>> */
function bvmgr_staffing_detached_history_rows(): array
{
	global $wpdb;
	$tables = array(
		'assignment' => bvmgr_staffing_table_name('assignments'),
		'event_slot' => bvmgr_staffing_table_name('event_slots'),
		'rollup'     => bvmgr_staffing_table_name('rollups'),
		'audit'      => bvmgr_staffing_table_name('audit'),
	);
	$rows = array();
	foreach ($tables as $domain => $table) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Explicit maintenance reads plugin-owned tables and must observe current rows.
		$value = $wpdb->get_results($wpdb->prepare('SELECT * FROM %i ORDER BY 1', $table), ARRAY_A);
		if ($wpdb->last_error !== '' || !is_array($value)) {
			throw new BVMGR_Staffing_Failure('database_error');
		}
		$rows[$domain] = $value;
	}
	return $rows;
}

/** @param array<string,mixed> $row */
function bvmgr_staffing_detached_history_row_checksum(array $row): string
{
	return hash('sha256', (string) wp_json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

/** @param array<int,array<string,mixed>> $audit_rows
 *  @param array<string,mixed> $row
 *  @return array{audit_ids:array<int,int>,event_plan_ids:array<int,int>}
 */
function bvmgr_staffing_detached_history_audit_context(string $domain, array $row, array $audit_rows): array
{
	$audit_ids = array();
	$event_plan_ids = array();
	$primary = $domain === 'assignment' ? 'assignment_id' : ($domain === 'event_slot' ? 'slot_id' : 'event_plan_id');
	$identity = (int) ($row[$primary] ?? 0);
	$matches = static function ($value) use (&$matches, $primary, $identity): bool {
		if (!is_array($value)) {
			return false;
		}
		if (isset($value[$primary]) && (int) $value[$primary] === $identity) {
			return true;
		}
		foreach ($value as $child) {
			if (is_array($child) && $matches($child)) {
				return true;
			}
		}
		return false;
	};
	foreach ($audit_rows as $audit) {
		$matched = $domain === 'rollup' && (int) ($audit['event_plan_id'] ?? 0) === $identity;
		foreach (array('before_json', 'after_json') as $column) {
			$decoded = json_decode((string) ($audit[$column] ?? ''), true);
			if (is_array($decoded) && $matches($decoded)) {
				$matched = true;
			}
		}
		if (!$matched) {
			continue;
		}
		$audit_ids[] = (int) ($audit['log_id'] ?? 0);
		if ((int) ($audit['event_plan_id'] ?? 0) > 0) {
			$event_plan_ids[] = (int) $audit['event_plan_id'];
		}
	}
	$audit_ids = array_values(array_unique(array_filter($audit_ids)));
	$event_plan_ids = array_values(array_unique(array_filter($event_plan_ids)));
	sort($audit_ids, SORT_NUMERIC);
	sort($event_plan_ids, SORT_NUMERIC);
	return array('audit_ids' => $audit_ids, 'event_plan_ids' => $event_plan_ids);
}

/**
 * Companion integrations with a verified work/payroll authority must return a
 * non-empty reason list from this filter. Core currently has no paid-payroll source.
 *
 * @param array<string,mixed> $row
 * @return array<int,string>
 */
function bvmgr_staffing_detached_history_dependencies(string $domain, array $row): array
{
	$dependencies = apply_filters('bvmgr_staffing_detached_history_dependencies', array(), $domain, $row);
	if (!is_array($dependencies)) {
		return array('invalid_dependency_provider');
	}
	return array_values(array_unique(array_filter(array_map('sanitize_key', $dependencies))));
}

/** @param array<string,mixed> $row
 *  @return array<int,string>
 */
function bvmgr_staffing_detached_history_replacements(array $row, array $context): array
{
	$replacements = apply_filters('bvmgr_staffing_detached_history_replacements', array(), $row, $context);
	if (!is_array($replacements)) {
		return array('invalid_replacement_provider');
	}
	return array_values(array_unique(array_filter(array_map('sanitize_text_field', $replacements))));
}

/** @param array<string,array<int,array<string,mixed>>> $candidates */
function bvmgr_staffing_detached_history_fingerprint(array $candidates): string
{
	$identities = array();
	foreach (array('assignment' => 'assignment_id', 'event_slot' => 'slot_id', 'rollup' => 'event_plan_id') as $domain => $primary) {
		foreach ((array) ($candidates[$domain] ?? array()) as $candidate) {
			$identities[] = array(
				'domain'   => $domain,
				'primary'  => (int) ($candidate['row'][$primary] ?? 0),
				'checksum' => (string) ($candidate['row_sha256'] ?? ''),
			);
		}
	}
	usort($identities, static function (array $a, array $b): int {
		return array($a['domain'], $a['primary']) <=> array($b['domain'], $b['primary']);
	});
	return hash('sha256', (string) wp_json_encode($identities, JSON_UNESCAPED_SLASHES));
}

/**
 * Read-only detached-history discovery. This never retires or repairs a row.
 *
 * @return array<string,mixed>
 */
function bvmgr_staffing_detached_history_preview(): array
{
	global $wpdb;
	if (!current_user_can('manage_options')) {
		return array('ok' => false, 'error' => 'forbidden');
	}
	try {
		$all = bvmgr_staffing_detached_history_rows();
	} catch (Throwable $e) {
		return array('ok' => false, 'error' => $e instanceof BVMGR_Staffing_Failure ? $e->getMessage() : 'database_error');
	}
	$slots = array_column($all['event_slot'], null, 'slot_id');
	$posts = array();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Eligibility requires exact post existence/type without cache ambiguity.
	$post_rows = $wpdb->get_results($wpdb->prepare('SELECT ID, post_type, post_status FROM %i ORDER BY ID', $wpdb->posts), ARRAY_A);
	if ($wpdb->last_error !== '' || !is_array($post_rows)) {
		return array('ok' => false, 'error' => 'database_error');
	}
	foreach ($post_rows as $post) {
		$posts[(int) $post['ID']] = $post;
	}

	$candidates = array('assignment' => array(), 'event_slot' => array(), 'rollup' => array());
	$blocked = array('assignment' => array(), 'event_slot' => array(), 'rollup' => array());
	foreach ($all['assignment'] as $row) {
		$assignment_id = (int) $row['assignment_id'];
		$slot_id = (int) $row['slot_id'];
		$staff_id = (int) $row['staff_id'];
		$slot = $slots[$slot_id] ?? null;
		$event_plan_id = is_array($slot) ? (int) $slot['event_plan_id'] : 0;
		$event_post = $event_plan_id > 0 ? ($posts[$event_plan_id] ?? null) : null;
		$staff_post = $posts[$staff_id] ?? null;
		$audit = bvmgr_staffing_detached_history_audit_context('assignment', $row, $all['audit']);
		if (!$slot && count($audit['event_plan_ids']) === 1) {
			$event_plan_id = (int) $audit['event_plan_ids'][0];
			$event_post = $posts[$event_plan_id] ?? null;
		}
		$reasons = array();
		$relationship_broken = !$slot || !$event_post || ($event_post['post_type'] ?? '') !== 'vms_event_plan';
		if (!$relationship_broken) {
			$reasons[] = 'relationship_live';
		}
		if (!$staff_post || ($staff_post['post_type'] ?? '') !== 'vms_staff') {
			$reasons[] = 'staff_missing_or_wrong_type';
		}
		if ($event_plan_id > 0 && $event_post && ($event_post['post_type'] ?? '') !== 'vms_event_plan') {
			$reasons[] = 'event_plan_wrong_post_type';
		}
		$status = (string) $row['status'];
		if ($status === 'proposed') {
			if (!$slot || $event_plan_id <= 0 || $event_post) {
				$reasons[] = 'proposed_requires_wholly_absent_event_plan';
			}
		} elseif ($status === 'canceled') {
			if (!$slot && count($audit['event_plan_ids']) !== 1) {
				$reasons[] = 'historical_event_plan_ambiguous';
			}
		} else {
			$reasons[] = $status === 'confirmed' ? 'confirmed_requires_review' : 'unsupported_lifecycle_status';
		}
		if (($row['actual_start_local'] ?? null) !== null && (string) $row['actual_start_local'] !== '') {
			$reasons[] = 'actual_work_present';
		}
		if (($row['actual_end_local'] ?? null) !== null && (string) $row['actual_end_local'] !== '') {
			$reasons[] = 'actual_work_present';
		}
		if (($row['pay_type_override'] ?? null) !== null && (string) $row['pay_type_override'] !== '') {
			$reasons[] = 'work_or_payment_dependency';
		}
		if (($row['pay_rate_override'] ?? null) !== null && (string) $row['pay_rate_override'] !== '') {
			$reasons[] = 'work_or_payment_dependency';
		}
		$dependencies = bvmgr_staffing_detached_history_dependencies('assignment', $row);
		if ($dependencies) {
			$reasons[] = 'work_or_payment_dependency';
		}
		$context = array('slot' => $slot, 'event_plan_id' => $event_plan_id, 'audit' => $audit);
		if (bvmgr_staffing_detached_history_replacements($row, $context)) {
			$reasons[] = 'canonical_replacement_available';
		}
		$entry = array(
			'row'                    => $row,
			'row_sha256'             => bvmgr_staffing_detached_history_row_checksum($row),
			'original_event_plan_id' => $event_plan_id,
			'source_audit_ids'       => $audit['audit_ids'],
			'dependencies'           => $dependencies,
			'evidence_classification'=> 'retained_category_b',
			'reason_code'            => !$slot ? 'detached_missing_slot' : 'detached_missing_event_plan',
		);
		$reasons = array_values(array_unique($reasons));
		if ($reasons) {
			$entry['reasons'] = $reasons;
			$blocked['assignment'][] = $entry;
		} else {
			$candidates['assignment'][] = $entry;
		}
	}

	$candidate_assignment_ids = array_map(static function (array $entry): int {
		return (int) $entry['row']['assignment_id'];
	}, $candidates['assignment']);
	$assignments_by_slot = array();
	foreach ($all['assignment'] as $assignment) {
		$assignments_by_slot[(int) $assignment['slot_id']][] = (int) $assignment['assignment_id'];
	}
	foreach ($all['event_slot'] as $row) {
		$slot_id = (int) $row['slot_id'];
		$event_plan_id = (int) $row['event_plan_id'];
		$event_post = $posts[$event_plan_id] ?? null;
		$reasons = array();
		if ($event_post) {
			$reasons[] = ($event_post['post_type'] ?? '') === 'vms_event_plan' ? 'event_plan_exists' : 'event_plan_wrong_post_type';
		}
		$assignment_ids = $assignments_by_slot[$slot_id] ?? array();
		$survivors = array_values(array_diff($assignment_ids, $candidate_assignment_ids));
		if ($survivors) {
			$reasons[] = 'surviving_assignment';
		}
		$dependencies = bvmgr_staffing_detached_history_dependencies('event_slot', $row);
		if ($dependencies) {
			$reasons[] = 'work_or_payment_dependency';
		}
		$audit = bvmgr_staffing_detached_history_audit_context('event_slot', $row, $all['audit']);
		$entry = array(
			'row'                    => $row,
			'row_sha256'             => bvmgr_staffing_detached_history_row_checksum($row),
			'original_event_plan_id' => $event_plan_id,
			'source_audit_ids'       => $audit['audit_ids'],
			'dependencies'           => $dependencies,
			'evidence_classification'=> 'detached_operational_row',
			'reason_code'            => 'detached_missing_event_plan',
		);
		$reasons = array_values(array_unique($reasons));
		if ($reasons) {
			$entry['reasons'] = $reasons;
			$blocked['event_slot'][] = $entry;
		} else {
			$candidates['event_slot'][] = $entry;
		}
	}

	$candidate_slot_ids = array_map(static function (array $entry): int {
		return (int) $entry['row']['slot_id'];
	}, $candidates['event_slot']);
	$slots_by_plan = array();
	foreach ($all['event_slot'] as $slot) {
		$slots_by_plan[(int) $slot['event_plan_id']][] = (int) $slot['slot_id'];
	}
	foreach ($all['rollup'] as $row) {
		$event_plan_id = (int) $row['event_plan_id'];
		$event_post = $posts[$event_plan_id] ?? null;
		$reasons = array();
		if ($event_post) {
			$reasons[] = ($event_post['post_type'] ?? '') === 'vms_event_plan' ? 'event_plan_exists' : 'event_plan_wrong_post_type';
		}
		$surviving_slots = array_values(array_diff($slots_by_plan[$event_plan_id] ?? array(), $candidate_slot_ids));
		if ($surviving_slots) {
			$reasons[] = 'surviving_slot_dependency';
		}
		$dependencies = bvmgr_staffing_detached_history_dependencies('rollup', $row);
		if ($dependencies) {
			$reasons[] = 'work_or_payment_dependency';
		}
		$audit = bvmgr_staffing_detached_history_audit_context('rollup', $row, $all['audit']);
		$entry = array(
			'row'                    => $row,
			'row_sha256'             => bvmgr_staffing_detached_history_row_checksum($row),
			'original_event_plan_id' => $event_plan_id,
			'source_audit_ids'       => $audit['audit_ids'],
			'dependencies'           => $dependencies,
			'evidence_classification'=> 'derived_cache',
			'reason_code'            => 'detached_missing_event_plan',
		);
		$reasons = array_values(array_unique($reasons));
		if ($reasons) {
			$entry['reasons'] = $reasons;
			$blocked['rollup'][] = $entry;
		} else {
			$candidates['rollup'][] = $entry;
		}
	}

	return array(
		'ok'          => true,
		'candidates'  => $candidates,
		'blocked'     => $blocked,
		'fingerprint' => bvmgr_staffing_detached_history_fingerprint($candidates),
		'counts'      => array_map('count', $candidates),
	);
}

/** @return array<int,array<string,mixed>> */
function bvmgr_staffing_detached_history_column_schema(string $table): array
{
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Lossless archival includes exact source column definitions.
	$columns = $wpdb->get_results($wpdb->prepare('SHOW COLUMNS FROM %i', $table), ARRAY_A);
	if ($wpdb->last_error !== '' || !is_array($columns)) {
		throw new BVMGR_Staffing_Failure('database_error');
	}
	return $columns;
}

/** @param array<string,mixed> $candidate
 *  @param array<int,array<string,mixed>> $columns
 *  @return array<string,mixed>
 */
function bvmgr_staffing_detached_history_archive_payload(string $domain, array $candidate, array $columns, string $batch_id, int $actor_id, string $archived_at): array
{
	$primary = $domain === 'assignment' ? 'assignment_id' : ($domain === 'event_slot' ? 'slot_id' : 'event_plan_id');
	$row = $candidate['row'];
	return array(
		'archive_format'          => 'bvmgr_detached_staffing_history',
		'archive_version'         => 1,
		'archive_batch_id'        => $batch_id,
		'archive_key'             => $domain . ':' . (int) $row[$primary] . ':' . $candidate['row_sha256'],
		'original_domain'         => $domain,
		'original_table'          => bvmgr_staffing_table_name($domain === 'assignment' ? 'assignments' : ($domain === 'event_slot' ? 'event_slots' : 'rollups')),
		'original_primary_key'    => array('column' => $primary, 'value' => (int) $row[$primary]),
		'original_event_plan_id'  => (int) ($candidate['original_event_plan_id'] ?? 0),
		'original_slot_id'        => isset($row['slot_id']) ? (int) $row['slot_id'] : ($domain === 'event_slot' ? (int) $row[$primary] : null),
		'original_staff_id'       => isset($row['staff_id']) ? (int) $row['staff_id'] : null,
		'original_status'         => isset($row['status']) ? (string) $row['status'] : null,
		'original_row'            => $row,
		'original_columns'        => $columns,
		'archive_reason_code'     => (string) $candidate['reason_code'],
		'original_row_sha256'     => (string) $candidate['row_sha256'],
		'actor_user_id'           => $actor_id,
		'archived_at'             => $archived_at,
		'source_audit_ids'        => array_values(array_map('intval', (array) ($candidate['source_audit_ids'] ?? array()))),
		'evidence_classification' => (string) $candidate['evidence_classification'],
	);
}

/**
 * Explicit administrator maintenance. The caller must pass the exact preview
 * it reviewed; the preview is independently recomputed inside the transaction.
 *
 * @param array<string,mixed> $expected_preview
 * @return array<string,mixed>
 */
function bvmgr_staffing_retire_detached_history(array $expected_preview, string $batch_id = ''): array
{
	global $wpdb;
	if (!current_user_can('manage_options') || bvmgr_staffing_transaction_active()) {
		return array('ok' => false, 'error' => 'forbidden');
	}
	if (get_class($wpdb) !== 'wpdb') {
		return array('ok' => false, 'error' => 'unsupported_database_adapter');
	}
	if (empty($expected_preview['ok']) || !isset($expected_preview['candidates'], $expected_preview['fingerprint'])) {
		return array('ok' => false, 'error' => 'invalid_preview');
	}
	if ($batch_id === '') {
		$batch_id = wp_generate_uuid4();
	}
	if (!preg_match('/^[a-zA-Z0-9-]{16,64}$/D', $batch_id)) {
		return array('ok' => false, 'error' => 'invalid_batch_id');
	}
	$original = $wpdb;
	$original->flush();
	$wpdb = new BVMGR_Staffing_Transaction_DB($original);
	$lock = bvmgr_staffing_lock_name();
	$locked = false;
	$started = false;
	$result = array();
	try {
		if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lock)) !== 1) {
			throw new BVMGR_Staffing_Failure('staffing_busy');
		}
		$locked = true;
		if ((int) $wpdb->get_var('SELECT @@autocommit') !== 1) {
			throw new BVMGR_Staffing_Failure('external_transaction');
		}
		bvmgr_staffing_require_transaction_schema(false);
		$wpdb->query('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
		$wpdb->query('START TRANSACTION');
		$started = true;
		$GLOBALS['bvmgr_staffing_transaction'] = array('events' => array(), 'plans' => array(), 'saved_events' => array());

		$expected_candidates = (array) $expected_preview['candidates'];
		$audit_table = bvmgr_staffing_table_name('audit');
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Idempotence verifies immutable archive identities under the maintenance lock.
		$prior_archives = $wpdb->get_results($wpdb->prepare('SELECT log_id, after_json FROM %i WHERE action=%s ORDER BY log_id', $audit_table, 'detached_history_archive'), ARRAY_A);
		$prior_keys = array();
		foreach ((array) $prior_archives as $archive) {
			$decoded = json_decode((string) $archive['after_json'], true);
			if (is_array($decoded) && ($decoded['archive_batch_id'] ?? '') === $batch_id && !empty($decoded['archive_key'])) {
				$prior_keys[(string) $decoded['archive_key']] = (int) $archive['log_id'];
			}
		}
		$expected_keys = array();
		foreach (array('assignment', 'event_slot', 'rollup') as $domain) {
			$primary = $domain === 'assignment' ? 'assignment_id' : ($domain === 'event_slot' ? 'slot_id' : 'event_plan_id');
			foreach ((array) ($expected_candidates[$domain] ?? array()) as $candidate) {
				$expected_keys[] = $domain . ':' . (int) $candidate['row'][$primary] . ':' . (string) $candidate['row_sha256'];
			}
		}
		if ($expected_keys && !array_diff($expected_keys, array_keys($prior_keys))) {
			$wpdb->finish_transaction('COMMIT');
			$started = false;
			return array('ok' => true, 'noop' => true, 'batch_id' => $batch_id, 'archive_log_ids' => array_values($prior_keys));
		}
		if ($prior_keys) {
			throw new BVMGR_Staffing_Failure('partial_retirement_detected');
		}

		$current_preview = bvmgr_staffing_detached_history_preview();
		if (empty($current_preview['ok']) || !hash_equals((string) $expected_preview['fingerprint'], (string) $current_preview['fingerprint'])) {
			throw new BVMGR_Staffing_Failure('retirement_preview_changed');
		}
		$actor_id = get_current_user_id();
		$archived_at = bvmgr_staffing_now_mysql_utc();
		$tables = array(
			'assignment' => bvmgr_staffing_table_name('assignments'),
			'event_slot' => bvmgr_staffing_table_name('event_slots'),
			'rollup'     => bvmgr_staffing_table_name('rollups'),
		);
		$schemas = array();
		foreach ($tables as $domain => $table) {
			$schemas[$domain] = bvmgr_staffing_detached_history_column_schema($table);
		}
		$archive_ids = array();
		$payloads = array();
		$audit_before = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i', $audit_table));
		foreach (array('assignment', 'event_slot', 'rollup') as $domain) {
			foreach ((array) ($current_preview['candidates'][$domain] ?? array()) as $candidate) {
				$payload = bvmgr_staffing_detached_history_archive_payload($domain, $candidate, $schemas[$domain], $batch_id, $actor_id, $archived_at);
				$json = wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
				$wpdb->insert($audit_table, array(
					'event_plan_id' => (int) $payload['original_event_plan_id'] > 0 ? (int) $payload['original_event_plan_id'] : null,
					'actor_user_id' => $actor_id,
					'action'        => 'detached_history_archive',
					'before_json'   => null,
					'after_json'    => $json,
					'created_at'    => $archived_at,
				));
				$log_id = (int) $wpdb->insert_id;
				$stored = $wpdb->get_row($wpdb->prepare('SELECT action, after_json FROM %i WHERE log_id=%d', $audit_table, $log_id), ARRAY_A);
				if (!$stored || $stored['action'] !== 'detached_history_archive' || !hash_equals(hash('sha256', $json), hash('sha256', (string) $stored['after_json']))) {
					throw new BVMGR_Staffing_Failure('archive_verification_failed');
				}
				$archive_ids[] = $log_id;
				$payloads[] = $payload;
			}
		}

		$deleted = array('assignment' => array(), 'event_slot' => array(), 'rollup' => array());
		foreach (array('assignment' => 'assignment_id', 'event_slot' => 'slot_id', 'rollup' => 'event_plan_id') as $domain => $primary) {
			foreach ((array) ($current_preview['candidates'][$domain] ?? array()) as $candidate) {
				$id = (int) $candidate['row'][$primary];
				if ($wpdb->delete($tables[$domain], array($primary => $id), array('%d')) !== 1) {
					throw new BVMGR_Staffing_Failure('retirement_delete_failed');
				}
				$deleted[$domain][] = $id;
			}
		}
		foreach ($deleted as $domain => $ids) {
			$primary = $domain === 'assignment' ? 'assignment_id' : ($domain === 'event_slot' ? 'slot_id' : 'event_plan_id');
			foreach ($ids as $id) {
				if ((int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE %i=%d', $tables[$domain], $primary, $id)) !== 0) {
					throw new BVMGR_Staffing_Failure('retirement_verification_failed');
				}
			}
		}
		if ((int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i', $audit_table)) !== $audit_before + count($archive_ids)) {
			throw new BVMGR_Staffing_Failure('archive_count_mismatch');
		}
		if ((int) $wpdb->get_var($wpdb->prepare('SELECT IS_USED_LOCK(%s)=CONNECTION_ID()', $lock)) !== 1) {
			throw new BVMGR_Staffing_Failure('staffing_lock_lost');
		}
		$wpdb->finish_transaction('COMMIT');
		$started = false;
		$result = array(
			'ok'              => true,
			'batch_id'        => $batch_id,
			'archive_log_ids'  => $archive_ids,
			'archive_payloads' => $payloads,
			'deleted'          => $deleted,
		);
	} catch (Throwable $e) {
		$rolled_back = false;
		if ($started) {
			try {
				$wpdb->finish_transaction('ROLLBACK');
				$rolled_back = true;
			} catch (Throwable $ignored) {
			}
		}
		$result = array('ok' => false, 'error' => $e instanceof BVMGR_Staffing_Failure ? $e->getMessage() : 'database_error', 'rolled_back' => $rolled_back);
	} finally {
		unset($GLOBALS['bvmgr_staffing_transaction']);
		if ($locked) {
			try {
				$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
			} catch (Throwable $ignored) {
			}
		}
		$wpdb = $original;
	}
	return $result;
}
