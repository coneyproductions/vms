<?php
/** Destructive retirement coverage runs only in the hard-bound Staffing fixture. */
require __DIR__ . '/bootstrap.php';

$source_db = $wpdb;
$wpdb = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
$wpdb->set_prefix('bvm_detached_history_');
$created = array();
$checks = 0;

function detached_check($condition, string $label): void
{
	global $checks;
	if (!$condition) {
		throw new RuntimeException($label);
	}
	$checks++;
}

function detached_ids(array $preview, string $domain, string $primary): array
{
	$ids = array_map(static function (array $entry) use ($primary): int {
		return (int) $entry['row'][$primary];
	}, (array) ($preview['candidates'][$domain] ?? array()));
	sort($ids, SORT_NUMERIC);
	return $ids;
}

try {
	$source_tables = array(
		'assignments' => $source_db->prefix . BVMGR_DB_TABLE_EVENT_ROLE_ASSIGNMENTS_SUFFIX,
		'event_slots' => $source_db->prefix . BVMGR_DB_TABLE_EVENT_ROLE_SLOTS_SUFFIX,
		'audit' => $source_db->prefix . BVMGR_DB_TABLE_STAFFING_AUDIT_LOG_SUFFIX,
		'rollups' => $source_db->prefix . BVMGR_DB_TABLE_STAFFING_EVENT_ROLLUPS_SUFFIX,
		'posts' => $source_db->posts,
		'postmeta' => $source_db->postmeta,
		'terms' => $source_db->terms,
		'term_taxonomy' => $source_db->term_taxonomy,
	);
	foreach ($source_tables as $kind => $source) {
		$target = in_array($kind, array('posts', 'postmeta', 'terms', 'term_taxonomy'), true)
			? $wpdb->$kind
			: bvmgr_staffing_table_name($kind);
		detached_check(!$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $target)), 'fresh detached-history fixture table');
		detached_check($wpdb->query($wpdb->prepare('CREATE TABLE %i LIKE %i', $target, $source)) !== false, 'clone detached-history schema');
		$created[] = $target;
	}

	$assignments = bvmgr_staffing_table_name('assignments');
	$slots = bvmgr_staffing_table_name('event_slots');
	$audit = bvmgr_staffing_table_name('audit');
	$rollups = bvmgr_staffing_table_name('rollups');
	if (in_array('revision', $wpdb->get_col($wpdb->prepare('SHOW COLUMNS FROM %i', $assignments)), true)) {
		$wpdb->query($wpdb->prepare('ALTER TABLE %i DROP COLUMN revision', $assignments));
	}
	foreach (array('lifecycle_operation', 'lifecycle_assignment') as $index) {
		if (in_array($index, array_column($wpdb->get_results($wpdb->prepare('SHOW INDEX FROM %i', $audit), ARRAY_A), 'Key_name'), true)) {
			$wpdb->query($wpdb->prepare('ALTER TABLE %i DROP INDEX %i', $audit, $index));
		}
	}
	foreach (array('assignment_id', 'operation_id') as $column) {
		if (in_array($column, $wpdb->get_col($wpdb->prepare('SHOW COLUMNS FROM %i', $audit)), true)) {
			$wpdb->query($wpdb->prepare('ALTER TABLE %i DROP COLUMN %i', $audit, $column));
		}
	}

	$now = '2030-01-02 03:04:05';
	$insert_post = static function (int $id, string $type, string $title) use ($wpdb, $now): void {
		$wpdb->insert($wpdb->posts, array(
			'ID' => $id, 'post_author' => 1, 'post_date' => $now, 'post_date_gmt' => $now,
			'post_content' => '', 'post_title' => $title, 'post_excerpt' => '', 'post_status' => 'publish',
			'comment_status' => 'closed', 'ping_status' => 'closed', 'post_password' => '', 'post_name' => sanitize_title($title),
			'to_ping' => '', 'pinged' => '', 'post_modified' => $now, 'post_modified_gmt' => $now,
			'post_content_filtered' => '', 'post_parent' => 0, 'guid' => '', 'menu_order' => 0,
			'post_type' => $type, 'post_mime_type' => '', 'comment_count' => 0,
		));
	};
	$insert_post(2001, 'vms_staff', 'Detached Staff');
	$insert_post(3001, 'vms_event_plan', 'Live Event Plan');
	$wpdb->insert($wpdb->terms, array('term_id' => 4001, 'name' => 'Detached role', 'slug' => 'detached-role'));
	$wpdb->insert($wpdb->term_taxonomy, array('term_id' => 4001, 'taxonomy' => 'vms_staff_role', 'description' => ''));

	$slot = static function (int $id, int $plan) use ($wpdb, $slots, $now): void {
		$wpdb->insert($slots, array('slot_id' => $id, 'event_plan_id' => $plan, 'role_id' => 4001,
			'headcount_needed' => 1, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now));
	};
	$assignment = static function (int $id, int $slot_id, string $status, array $extra = array()) use ($wpdb, $assignments, $now): void {
		$wpdb->insert($assignments, array_merge(array('assignment_id' => $id, 'slot_id' => $slot_id, 'staff_id' => 2001,
			'status' => $status, 'created_at' => $now, 'updated_at' => $now), $extra));
	};
	$rollup = static function (int $plan) use ($wpdb, $rollups): void {
		$wpdb->insert($rollups, array('event_plan_id' => $plan, 'dirty' => 1, 'dirty_reason' => 'detached_fixture'));
	};

	$slot(1001, 3001);
	$slot(1002, 9001);
	$slot(1003, 9002);
	$slot(1004, 9003);
	$slot(1005, 9004);
	$slot(1006, 9005);
	$assignment(1, 1001, 'proposed');
	$assignment(2, 1002, 'proposed');
	$assignment(3, 1999, 'canceled');
	$assignment(4, 1003, 'confirmed', array('shift_start_ts' => 1900000000, 'shift_end_ts' => 1900003600));
	$assignment(5, 1004, 'proposed', array('actual_start_local' => '2030-01-02 01:00:00'));
	$assignment(6, 1005, 'proposed', array('pay_rate_override' => '125.00'));
	$rollup(3001);
	$rollup(9001);
	$rollup(9002);
	$rollup(9005);
	$original_snapshot = array('slots' => array(array('slot_id' => '1999', 'event_plan_id' => '9006',
		'assignments' => array(array('assignment_id' => '3', 'slot_id' => '1999', 'staff_id' => '2001', 'status' => 'canceled')))));
	$wpdb->insert($audit, array('event_plan_id' => 9006, 'actor_user_id' => 1, 'action' => 'event_staffing_save',
		'before_json' => null, 'after_json' => wp_json_encode($original_snapshot), 'created_at' => $now));
	$original_audit = $wpdb->get_results($wpdb->prepare('SELECT * FROM %i ORDER BY log_id', $audit), ARRAY_A);

	$preview = bvmgr_staffing_detached_history_preview();
	detached_check(!empty($preview['ok']), 'detached preview succeeds');
	detached_check(detached_ids($preview, 'assignment', 'assignment_id') === array(2, 3), 'only Proposed missing-plan and Canceled missing-slot assignments qualify');
	detached_check(detached_ids($preview, 'event_slot', 'slot_id') === array(1002, 1006), 'unassigned and same-batch detached slots qualify');
	detached_check(detached_ids($preview, 'rollup', 'event_plan_id') === array(9001, 9005), 'only dependency-free detached rollups qualify');
	$blocked_by_id = array();
	foreach ($preview['blocked']['assignment'] as $entry) {
		$blocked_by_id[(int) $entry['row']['assignment_id']] = $entry['reasons'];
	}
	detached_check(in_array('relationship_live', $blocked_by_id[1], true), 'valid live assignment blocked');
	detached_check(in_array('confirmed_requires_review', $blocked_by_id[4], true), 'Confirmed detached assignment blocked');
	detached_check(in_array('actual_work_present', $blocked_by_id[5], true), 'worked detached assignment blocked');
	detached_check(in_array('work_or_payment_dependency', $blocked_by_id[6], true), 'paid detached assignment blocked');
	$surviving_slot_blocks = array();
	foreach ($preview['blocked']['event_slot'] as $entry) {
		if (in_array('surviving_assignment', (array) $entry['reasons'], true)) {
			$surviving_slot_blocks[] = (int) $entry['row']['slot_id'];
		}
	}
	detached_check(!array_diff(array(1003, 1004, 1005), $surviving_slot_blocks), 'surviving assignments block detached slots');
	detached_check(in_array('event_plan_exists', (array) $preview['blocked']['event_slot'][0]['reasons'], true), 'existing-plan slot is blocked');

	// Remove policy-blocking fixtures after proving their disposition. The successful
	// transaction below then proves that retirement clears every orphan blocker.
	foreach (array(4, 5, 6) as $id) {
		$wpdb->delete($assignments, array('assignment_id' => $id));
	}
	foreach (array(1003, 1004, 1005) as $id) {
		$wpdb->delete($slots, array('slot_id' => $id));
	}
	$wpdb->delete($rollups, array('event_plan_id' => 9002));
	$preview = bvmgr_staffing_detached_history_preview();
	detached_check(detached_ids($preview, 'assignment', 'assignment_id') === array(2, 3), 'successful fixture assignment preview stable');

	$queries = array();
	$fail_delete = static function ($sql) use (&$queries, $assignments) {
		$queries[] = $sql;
		if (preg_match('/^DELETE FROM `' . preg_quote($assignments, '/') . '`/i', $sql)) {
			return 'DELETE FROM `bvm_missing_transaction_table` WHERE assignment_id=2';
		}
		return $sql;
	};
	add_filter('query', $fail_delete);
	try {
		$failed = bvmgr_staffing_retire_detached_history($preview, 'detached-test-failure-0001');
	} finally {
		remove_filter('query', $fail_delete);
	}
	detached_check(empty($failed['ok']) && !empty($failed['rolled_back']), 'simulated delete failure rolls back');
	detached_check((int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE assignment_id IN (2,3)', $assignments)) === 2, 'failed batch retains source assignments');
	detached_check($wpdb->get_results($wpdb->prepare('SELECT * FROM %i ORDER BY log_id', $audit), ARRAY_A) === $original_audit, 'failed batch rolls back archive inserts');

	$queries = array();
	$capture = static function ($sql) use (&$queries) { $queries[] = $sql; return $sql; };
	add_filter('query', $capture);
	try {
		$result = bvmgr_staffing_retire_detached_history($preview, 'detached-test-success-0001');
	} finally {
		remove_filter('query', $capture);
	}
	detached_check(!empty($result['ok']) && empty($result['noop']), 'detached retirement commits');
	detached_check($result['deleted']['assignment'] === array(2, 3), 'only authorized assignments removed');
	detached_check($result['deleted']['event_slot'] === array(1002, 1006), 'only authorized slots removed');
	detached_check($result['deleted']['rollup'] === array(9001, 9005), 'only authorized rollups removed');
	detached_check(count($result['archive_log_ids']) === 6, 'one archival audit row per source row');
	$first_archive = null;
	$first_delete = null;
	foreach ($queries as $index => $sql) {
		if ($first_archive === null && stripos($sql, 'INSERT INTO `' . $audit . '`') === 0) $first_archive = $index;
		if ($first_delete === null && stripos($sql, 'DELETE FROM `' . $assignments . '`') === 0) $first_delete = $index;
	}
	detached_check($first_archive !== null && $first_delete !== null && $first_archive < $first_delete, 'archives are inserted before operational deletion');
	foreach ($result['archive_payloads'] as $payload) {
		detached_check($payload['archive_format'] === 'bvmgr_detached_staffing_history' && $payload['archive_version'] === 1, 'archive contract/version');
		detached_check(hash_equals($payload['original_row_sha256'], bvmgr_staffing_detached_history_row_checksum($payload['original_row'])), 'archive contains exact checksummed source row');
		detached_check(!empty($payload['original_columns']), 'archive retains source column schema');
	}
	$preserved = $wpdb->get_results($wpdb->prepare("SELECT * FROM %i WHERE action<>'detached_history_archive' ORDER BY log_id", $audit), ARRAY_A);
	detached_check($preserved === $original_audit, 'all original audit rows remain byte-equivalent');
	$retry = bvmgr_staffing_retire_detached_history($preview, 'detached-test-success-0001');
	detached_check(!empty($retry['ok']) && !empty($retry['noop']) && count($retry['archive_log_ids']) === 6, 'same batch retry is idempotent');

	$gate = bvmgr_staffing_lifecycle_preflight();
	detached_check(!empty($gate['ok']) && empty($gate['orphan_assignment_ids']) && empty($gate['population']['orphan_slots']) && empty($gate['population']['invalid_rollups']), 'retirement clears orphan preflight blockers');
	$migration = bvmgr_staffing_migrate_lifecycle();
	detached_check(!empty($migration['ok']), 'official lifecycle migration succeeds after retirement');
	$gate = bvmgr_staffing_lifecycle_preflight();
	detached_check(!empty($gate['ok']) && $gate['schema'] === 'ready', 'post-migration schema is ready');

	echo 'PASS ' . $checks . " detached Staffing history retirement assertions\n";
} finally {
	foreach (array_reverse($created) as $table) {
		$wpdb->query($wpdb->prepare('DROP TABLE %i', $table));
	}
	$wpdb = $source_db;
}
