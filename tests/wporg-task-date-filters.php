<?php
/** Real MySQL regression; supervised disposable database only. */
if (getenv('BVM_DISPOSABLE_DB_GUARDED') !== '1'
	|| DB_NAME !== 'bvm_wporg'
	|| DB_HOST !== 'localhost:' . getenv('BVM_DISPOSABLE_DB_SOCKET')
	|| strpos(ABSPATH, '/private/tmp/bvm-wporg-readiness-') !== 0) {
	throw new RuntimeException('Supervised disposable readiness database required');
}

global $wpdb;
$table = bvmgr_tasks_table_name('task_instances');
$event_id = wp_insert_post(array(
	'post_type' => 'vms_event_plan',
	'post_title' => 'Disposable date-window probe',
	'post_status' => 'draft',
));
if (!$event_id || is_wp_error($event_id)) {
	throw new RuntimeException('Fixture creation failed');
}

$original_sql_mode = (string) $wpdb->get_var('SELECT @@SESSION.sql_mode');
$strict_sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

try {
	$wpdb->query($wpdb->prepare('SET SESSION sql_mode = %s', $strict_sql_mode));
	$active_modes = array_filter(explode(',', (string) $wpdb->get_var('SELECT @@SESSION.sql_mode')));
	$required_modes = explode(',', $strict_sql_mode);
	if (array_diff($required_modes, $active_modes)) {
		throw new RuntimeException('Strict SQL mode was not established');
	}

	foreach (array(null, '2030-01-10 12:00:00', '2030-02-10 12:00:00') as $due) {
		$inserted = $wpdb->insert(
			$table,
			array(
				'event_id' => $event_id,
				'title' => 'Synthetic date probe',
				'due_at_local' => $due,
				'created_at' => '2030-01-01 00:00:00',
				'updated_at' => '2030-01-01 00:00:00',
			)
		);
		if ($inserted !== 1) {
			throw new RuntimeException('Task fixture failed');
		}
	}

	foreach (array(
		array(array(), 3),
		array(array('due_before' => ''), 3),
		array(array('due_before' => '2030-01-31 23:59:59'), 1),
		array(array('due_after' => '2030-02-01 00:00:00'), 1),
		array(array('due_after' => '2030-01-01 00:00:00', 'due_before' => '2030-01-31 23:59:59'), 1),
	) as $case) {
		$filters = array_merge(array('event_id' => $event_id), $case[0]);
		$rows = bvmgr_tasks_get_instances($filters);
		if ($wpdb->last_error !== '' || count($rows) !== $case[1]) {
			throw new RuntimeException('List date-window query failed');
		}
		$count = bvmgr_tasks_count_instances($filters);
		if ($wpdb->last_error !== '' || $count !== $case[1]) {
			throw new RuntimeException('Count date-window query failed');
		}
	}
} finally {
	$wpdb->query($wpdb->prepare('SET SESSION sql_mode = %s', $original_sql_mode));
	$wpdb->delete($table, array('event_id' => $event_id), array('%d'));
	wp_delete_post($event_id, true);
}

echo 'PASS 10 real-MySQL task date-window cases under ' . $strict_sql_mode . "; disabled and active filters preserve results without SQL errors\n";
