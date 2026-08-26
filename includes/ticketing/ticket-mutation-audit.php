<?php
defined('ABSPATH') || exit;

function vms_ticket_mutation_audit_schema_option_key(): string
{
	return defined('BVMGR_OPT_TICKET_MUTATION_AUDIT_DB_SCHEMA_VERSION')
		? (string) BVMGR_OPT_TICKET_MUTATION_AUDIT_DB_SCHEMA_VERSION
		: 'vms_ticket_mutation_audit_db_schema_version';
}

function vms_ticket_mutation_audit_schema_target(): string
{
	return 'ticket_mutation_audit_v1';
}

function vms_ticket_mutation_audit_table_name(): string
{
	global $wpdb;
	$suffix = defined('BVMGR_DB_TABLE_TICKET_MUTATION_AUDIT_SUFFIX')
		? (string) BVMGR_DB_TABLE_TICKET_MUTATION_AUDIT_SUFFIX
		: 'vms_ticket_mutation_audit';
	return $wpdb->prefix . $suffix;
}

function vms_ticket_mutation_audit_maybe_upgrade_schema(): void
{
	$current = (string) get_option(vms_ticket_mutation_audit_schema_option_key(), '');
	$target = vms_ticket_mutation_audit_schema_target();
	if ($current === $target) {
		return;
	}

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	global $wpdb;
	$table = vms_ticket_mutation_audit_table_name();
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE {$table} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		created_at_gmt DATETIME NOT NULL,
		plan_id BIGINT(20) UNSIGNED NOT NULL,
		tec_event_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
		event_title VARCHAR(255) NOT NULL DEFAULT '',
		user_id BIGINT(20) UNSIGNED NULL,
		trigger_source VARCHAR(40) NOT NULL DEFAULT '',
		source_hook VARCHAR(120) NOT NULL DEFAULT '',
		source_function VARCHAR(160) NOT NULL DEFAULT '',
		change_type VARCHAR(80) NOT NULL DEFAULT '',
		result_status VARCHAR(20) NOT NULL DEFAULT '',
		summary_text TEXT NULL,
		before_json LONGTEXT NULL,
		after_json LONGTEXT NULL,
		PRIMARY KEY (id),
		KEY plan_id (plan_id),
		KEY tec_event_id (tec_event_id),
		KEY created_at_gmt (created_at_gmt),
		KEY change_type (change_type),
		KEY result_status (result_status)
	) {$charset_collate};";

	dbDelta($sql);
	update_option(vms_ticket_mutation_audit_schema_option_key(), $target, false);
}
add_action('plugins_loaded', 'vms_ticket_mutation_audit_maybe_upgrade_schema', 10);

function vms_ticket_mutation_audit_context_stack(): array
{
	$stack = $GLOBALS['bvmgr_ticket_mutation_audit_context_stack'] ?? array();
	return is_array($stack) ? $stack : array();
}

function vms_ticket_mutation_audit_sanitize_context(array $context): array
{
	$clean = array();

	if (!empty($context['trigger_source'])) {
		$clean['trigger_source'] = sanitize_key((string) $context['trigger_source']);
	}
	if (!empty($context['source_hook'])) {
		$clean['source_hook'] = sanitize_key((string) $context['source_hook']);
	}
	if (!empty($context['source_function'])) {
		$clean['source_function'] = sanitize_text_field((string) $context['source_function']);
	}
	if (!empty($context['change_type'])) {
		$clean['change_type'] = sanitize_key((string) $context['change_type']);
	}
	if (!empty($context['summary_text'])) {
		$clean['summary_text'] = sanitize_text_field((string) $context['summary_text']);
	}
	if (!empty($context['requested_result_status'])) {
		$clean['requested_result_status'] = sanitize_key((string) $context['requested_result_status']);
	}
	if (!empty($context['derivation_source'])) {
		$clean['derivation_source'] = sanitize_key((string) $context['derivation_source']);
	}
	if (!empty($context['confidence_level'])) {
		$clean['confidence_level'] = sanitize_key((string) $context['confidence_level']);
	}
	if (!empty($context['expected_effect'])) {
		$clean['expected_effect'] = sanitize_key((string) $context['expected_effect']);
	}
	if (!empty($context['reason_text'])) {
		$clean['reason_text'] = sanitize_text_field((string) $context['reason_text']);
	}
	if (!empty($context['writer_branch'])) {
		$clean['writer_branch'] = sanitize_key((string) $context['writer_branch']);
	}
	if (!empty($context['result_health'])) {
		$clean['result_health'] = sanitize_key((string) $context['result_health']);
	}
	if (!empty($context['skip_reason_code'])) {
		$clean['skip_reason_code'] = sanitize_key((string) $context['skip_reason_code']);
	}
	if (isset($context['skip_expected'])) {
		$clean['skip_expected'] = !empty($context['skip_expected']) ? 1 : 0;
	}
	if (isset($context['skip_safety_driven'])) {
		$clean['skip_safety_driven'] = !empty($context['skip_safety_driven']) ? 1 : 0;
	}

	return $clean;
}

function vms_ticket_mutation_audit_push_context(array $context): void
{
	$stack = vms_ticket_mutation_audit_context_stack();
	$current = !empty($stack) ? end($stack) : array();
	if (!is_array($current)) {
		$current = array();
	}

	$stack[] = array_merge($current, vms_ticket_mutation_audit_sanitize_context($context));
	$GLOBALS['bvmgr_ticket_mutation_audit_context_stack'] = $stack;
}

function vms_ticket_mutation_audit_pop_context(): void
{
	$stack = vms_ticket_mutation_audit_context_stack();
	if (!empty($stack)) {
		array_pop($stack);
	}
	$GLOBALS['bvmgr_ticket_mutation_audit_context_stack'] = $stack;
}

function vms_ticket_mutation_audit_current_context(): array
{
	$stack = vms_ticket_mutation_audit_context_stack();
	if (empty($stack)) {
		return array();
	}

	$current = end($stack);
	return is_array($current) ? $current : array();
}

function vms_ticket_mutation_audit_skip_hooks(): array
{
	return array(
		'add_post_metadata',
		'update_post_metadata',
		'delete_post_metadata',
		'added_post_meta',
		'updated_post_meta',
		'deleted_post_meta',
	);
}

function vms_ticket_mutation_audit_current_hook(): string
{
	global $wp_current_filter;

	if (!is_array($wp_current_filter) || empty($wp_current_filter)) {
		return '';
	}

	$skip = vms_ticket_mutation_audit_skip_hooks();
	for ($index = count($wp_current_filter) - 1; $index >= 0; $index--) {
		$hook = sanitize_key((string) ($wp_current_filter[$index] ?? ''));
		if ($hook === '' || in_array($hook, $skip, true)) {
			continue;
		}
		return $hook;
	}

	return '';
}

function vms_ticket_mutation_audit_guard_decision(string $hook_name, int $object_id = 0, string $meta_key = ''): array
{
	$hook_name = sanitize_key($hook_name);
	if ($hook_name === '') {
		$hook_name = 'ticket_mutation_audit';
	}

	$context = vms_ticket_mutation_audit_current_context();
	$source_hook = sanitize_key((string) ($context['source_hook'] ?? ''));
	if ($source_hook === '') {
		$source_hook = vms_ticket_mutation_audit_current_hook();
	}

	$result = array(
		'allowed' => false,
		'reason' => 'unknown',
		'hook_name' => $hook_name,
		'object_id' => absint($object_id),
		'meta_key' => (string) $meta_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- This guard descriptor records the mutation metadata key; it does not configure a database query.
		'source_hook' => $source_hook,
		'source_function' => sanitize_text_field((string) ($context['source_function'] ?? '')),
	);

	if (function_exists('vms_admin_guard_heavy_hooks_disabled') && vms_admin_guard_heavy_hooks_disabled()) {
		$result['reason'] = 'constant_disabled';
		return $result;
	}

	if ((defined('WP_CLI') && WP_CLI) || (function_exists('wp_doing_cron') && wp_doing_cron())) {
		$result['allowed'] = true;
		$result['reason'] = 'non_admin_runtime';
		return $result;
	}

	if (!empty($context)) {
		$result['allowed'] = true;
		$result['reason'] = 'explicit_mutation_context';
		return $result;
	}

	if (function_exists('vms_admin_guard_should_allow_heavy_block')) {
		$guard = (array) vms_admin_guard_should_allow_heavy_block(
			$hook_name,
			array(
				'task' => 'ticket_mutation_audit',
				'allow_action' => 'ticket_mutation_audit',
			)
		);
		$result['allowed'] = !empty($guard['allowed']);
		$result['reason'] = sanitize_key((string) ($guard['reason'] ?? 'unknown'));
		return $result;
	}

	$result['reason'] = is_admin() ? 'passive_admin_request' : 'non_mutation_request';
	return $result;
}

function vms_ticket_mutation_audit_trace(string $decision, array $context = array(), float $started_at = 0.0): void
{
	$payload = array(
		'task' => 'ticket_mutation_audit',
		'reason' => sanitize_key((string) ($context['reason'] ?? '')),
		'object_id' => absint($context['object_id'] ?? 0),
		'meta_key' => (string) ($context['meta_key'] ?? ''), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- This trace payload records the mutation metadata key; it does not configure a database query.
		'operation' => sanitize_key((string) ($context['operation'] ?? '')),
		'source_hook' => sanitize_key((string) ($context['source_hook'] ?? '')),
		'source_function' => sanitize_text_field((string) ($context['source_function'] ?? '')),
	);

	if (function_exists('vms_admin_guard_trace')) {
		vms_admin_guard_trace(
			sanitize_key((string) ($context['hook_name'] ?? 'ticket_mutation_audit')),
			$decision,
			$payload,
			$started_at
		);
		return;
	}

	vms_record_operational_issue('ticket_mutation_audit_trace', array(
		'hook' => sanitize_key((string) ($context['hook_name'] ?? 'ticket_mutation_audit')),
		'action' => 'ticket_mutation_audit',
		'decision' => sanitize_key($decision),
		'reason' => $payload['reason'],
		'operation' => $payload['operation'],
		'plan_id' => $payload['object_id'],
	));
}

function vms_ticket_mutation_audit_resolve_source(array $context = array(), string $fallback_hook = ''): array
{
	$source_hook = sanitize_key((string) ($context['source_hook'] ?? ''));
	if ($source_hook === '') {
		$source_hook = sanitize_key($fallback_hook);
	}
	if ($source_hook === '') {
		$source_hook = vms_ticket_mutation_audit_current_hook();
	}

	$source_function = sanitize_text_field((string) ($context['source_function'] ?? ''));
	if ($source_function !== '') {
		return array(
			'source_hook' => $source_hook,
			'source_function' => $source_function,
		);
	}

	$source = vms_ticket_mutation_audit_detect_source();
	if ($source_hook === '') {
		$source_hook = sanitize_key((string) ($source['source_hook'] ?? ''));
	}

	return array(
		'source_hook' => $source_hook,
		'source_function' => sanitize_text_field((string) ($source['source_function'] ?? '')),
	);
}

function vms_ticket_mutation_audit_capture_source_trace(): array
{
	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- Mutation-source detection needs a bounded argument-free stack, and every frame is immediately reduced to a sanitized function identity.
	$trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 40);
	$projected = array();
	foreach ($trace as $frame) {
		if (!is_array($frame)) {
			continue;
		}
		$function = substr(sanitize_key((string) ($frame['function'] ?? '')), 0, 80);
		if ($function !== '') {
			$projected[] = array('function' => $function);
		}
	}

	return array_slice($projected, 0, 40);
}

function vms_ticket_mutation_audit_detect_source(array $trace = array()): array
{
	if (empty($trace)) {
		$trace = vms_ticket_mutation_audit_capture_source_trace();
	}

	$skip_functions = array(
		'apply_filters',
		'do_action',
		'do_action_ref_array',
		'add_post_meta',
		'update_post_meta',
		'delete_post_meta',
		'get_post_meta',
		'metadata_exists',
	);

	$source_function = '';
	foreach ($trace as $frame) {
		if (!is_array($frame)) {
			continue;
		}

		$function = (string) ($frame['function'] ?? '');
		if ($function === '' || strpos($function, 'vms_ticket_mutation_audit_') === 0 || in_array($function, $skip_functions, true)) {
			continue;
		}

		if (strpos($function, 'vms_') === 0 || strpos($function, 'tribe_') === 0) {
			$source_function = sanitize_text_field($function);
			break;
		}
	}

	return array(
		'source_hook' => vms_ticket_mutation_audit_current_hook(),
		'source_function' => $source_function,
	);
}

function vms_ticket_mutation_audit_trigger_source(array $context, string $source_hook, string $source_function): string
{
	$trigger = sanitize_key((string) ($context['trigger_source'] ?? ''));
	if ($trigger !== '') {
		return $trigger;
	}

	if ($source_hook !== '') {
		if (strpos($source_hook, 'wp_ajax_') === 0 || strpos($source_hook, 'admin_post_') === 0) {
			return 'manual_action';
		}
		if ($source_hook === 'transition_post_status') {
			return 'publish_transition';
		}
		if (strpos($source_hook, 'save_post') === 0 || in_array($source_hook, array('added_post_meta', 'updated_post_meta', 'deleted_post_meta'), true)) {
			return 'save_hook';
		}
		if (strpos($source_hook, 'vms_ticket_integrity_') === 0 || strpos($source_hook, 'cron') !== false || wp_doing_cron()) {
			return 'cron';
		}
	}

	if (strpos($source_function, 'import') !== false) {
		return 'import';
	}
	if (strpos($source_function, 'reconcile') !== false) {
		return 'reconciliation';
	}
	if (strpos($source_function, 'preview_sync') !== false || strpos($source_function, 'commit_sync') !== false) {
		return 'preview_commit';
	}
	if (strpos($source_function, 'rebuild') !== false || strpos($source_function, 'repair') !== false) {
		return 'rebuild';
	}

	return 'unknown_internal';
}

function vms_ticket_mutation_audit_relevant_meta_keys(): array
{
	$keys = array('_vms_ticketing_enabled_override');
	if (function_exists('vms_ticketing_v2_k')) {
		$keys[] = vms_ticketing_v2_k('config');
		$keys[] = vms_ticketing_v2_k('sync');
	}

	return array_values(array_unique(array_filter(array_map('strval', $keys))));
}

function vms_ticket_mutation_audit_is_relevant_meta_write(int $object_id, string $meta_key): bool
{
	$object_id = absint($object_id);
	if ($object_id <= 0 || $meta_key === '') {
		return false;
	}

	if (get_post_type($object_id) !== 'vms_event_plan') {
		return false;
	}

	return in_array($meta_key, vms_ticket_mutation_audit_relevant_meta_keys(), true);
}

function vms_ticket_mutation_audit_pending_store(): array
{
	$store = $GLOBALS['bvmgr_ticket_mutation_audit_pending_meta'] ?? array();
	return is_array($store) ? $store : array();
}

function vms_ticket_mutation_audit_enqueue_pending(string $operation, int $object_id, string $meta_key, array $row): void
{
	$store = vms_ticket_mutation_audit_pending_store();
	$key = $operation . ':' . $object_id . ':' . $meta_key;
	if (!isset($store[$key]) || !is_array($store[$key])) {
		$store[$key] = array();
	}
	$store[$key][] = $row;
	$GLOBALS['bvmgr_ticket_mutation_audit_pending_meta'] = $store;
}

function vms_ticket_mutation_audit_dequeue_pending(string $operation, int $object_id, string $meta_key): array
{
	$store = vms_ticket_mutation_audit_pending_store();
	$key = $operation . ':' . $object_id . ':' . $meta_key;
	$row = array();

	if (!empty($store[$key]) && is_array($store[$key])) {
		$row = array_shift($store[$key]);
		if (empty($store[$key])) {
			unset($store[$key]);
		}
	}

	$GLOBALS['bvmgr_ticket_mutation_audit_pending_meta'] = $store;
	return is_array($row) ? $row : array();
}

function vms_ticket_mutation_audit_capture_pre_meta_write($check, $object_id, $meta_key, $meta_value, $prev_value)
{
	unset($prev_value);

	$object_id = absint($object_id);
	$meta_key = (string) $meta_key;
	if (!vms_ticket_mutation_audit_is_relevant_meta_write($object_id, $meta_key)) {
		return $check;
	}

	$started_at = microtime(true);
	$operation = current_filter() === 'add_post_metadata' ? 'add' : 'update';
	$guard = vms_ticket_mutation_audit_guard_decision('ticket_mutation_audit_pre', $object_id, $meta_key);
	$guard['operation'] = $operation;
	if (empty($guard['allowed'])) {
		vms_ticket_mutation_audit_trace('skipped', $guard, $started_at);
		return $check;
	}

	// 0.2.24.656 performance guard: WordPress calls update_post_metadata
	// filters before its own no-change short-circuit. Avoid expensive audit
	// snapshot construction when the pending value is identical to the single
	// existing meta value. This is especially important for repeated Ticketing
	// v2 Save config clicks and Event Plan save retries.
	if (current_filter() === 'update_post_metadata') {
		$existing_values = get_metadata('post', $object_id, $meta_key, false);
		if (is_array($existing_values) && count($existing_values) === 1) {
			$existing_value = reset($existing_values);
			if (maybe_serialize($existing_value) === maybe_serialize($meta_value)) {
				return $check;
			}
		}
	}

	$context = vms_ticket_mutation_audit_current_context();
	$source = vms_ticket_mutation_audit_resolve_source($context, (string) ($guard['source_hook'] ?? ''));
	vms_ticket_mutation_audit_enqueue_pending(
		$operation,
		$object_id,
		$meta_key,
		array(
			'before_snapshot' => vms_ticket_mutation_audit_build_snapshot($object_id),
			'context' => $context,
			'source_hook' => (string) ($context['source_hook'] ?? $source['source_hook'] ?? ''),
				'source_function' => (string) ($context['source_function'] ?? $source['source_function'] ?? ''),
			)
	);
	vms_ticket_mutation_audit_trace('allowed', array_merge($guard, $source), $started_at);

	return $check;
}
add_filter('add_post_metadata', 'vms_ticket_mutation_audit_capture_pre_meta_write', 10, 5);
add_filter('update_post_metadata', 'vms_ticket_mutation_audit_capture_pre_meta_write', 10, 5);

function vms_ticket_mutation_audit_capture_pre_meta_delete($check, $object_id, $meta_key, $meta_value, $delete_all)
{
	unset($meta_value, $delete_all);

	$object_id = absint($object_id);
	$meta_key = (string) $meta_key;
	if (!vms_ticket_mutation_audit_is_relevant_meta_write($object_id, $meta_key)) {
		return $check;
	}

	$started_at = microtime(true);
	$guard = vms_ticket_mutation_audit_guard_decision('ticket_mutation_audit_pre_delete', $object_id, $meta_key);
	$guard['operation'] = 'delete';
	if (empty($guard['allowed'])) {
		vms_ticket_mutation_audit_trace('skipped', $guard, $started_at);
		return $check;
	}

	$context = vms_ticket_mutation_audit_current_context();
	$source = vms_ticket_mutation_audit_resolve_source($context, (string) ($guard['source_hook'] ?? ''));
	vms_ticket_mutation_audit_enqueue_pending(
		'delete',
		$object_id,
		$meta_key,
		array(
			'before_snapshot' => vms_ticket_mutation_audit_build_snapshot($object_id),
			'context' => $context,
			'source_hook' => (string) ($context['source_hook'] ?? $source['source_hook'] ?? ''),
				'source_function' => (string) ($context['source_function'] ?? $source['source_function'] ?? ''),
			)
	);
	vms_ticket_mutation_audit_trace('allowed', array_merge($guard, $source), $started_at);

	return $check;
}
add_filter('delete_post_metadata', 'vms_ticket_mutation_audit_capture_pre_meta_delete', 10, 5);

function vms_ticket_mutation_audit_origin_label(string $classification): string
{
	switch (sanitize_key($classification)) {
		case 'vms_native':
			return __('VMS-native', 'backstage-venue-manager');
		case 'imported_legacy':
			return __('Imported legacy', 'backstage-venue-manager');
		case 'mixed_or_reconciled':
			return __('Mixed / reconciled', 'backstage-venue-manager');
		default:
			return __('Unknown', 'backstage-venue-manager');
	}
}

function vms_ticket_mutation_audit_result_label(string $result_status): string
{
	switch (sanitize_key($result_status)) {
		case 'success':
			return __('Success', 'backstage-venue-manager');
		case 'no_op':
			return __('No changes', 'backstage-venue-manager');
		case 'partial':
			return __('Partial', 'backstage-venue-manager');
		case 'failed':
			return __('Failed', 'backstage-venue-manager');
		default:
			return __('Unknown', 'backstage-venue-manager');
	}
}

function vms_ticket_mutation_audit_trigger_label(string $trigger_source): string
{
	switch (sanitize_key($trigger_source)) {
		case 'manual_action':
			return __('Manual action', 'backstage-venue-manager');
		case 'cron':
			return __('Scheduled scan', 'backstage-venue-manager');
		case 'save_hook':
			return __('Save hook', 'backstage-venue-manager');
		case 'publish_transition':
			return __('Publish transition', 'backstage-venue-manager');
		case 'import':
			return __('Import', 'backstage-venue-manager');
		case 'preview_commit':
			return __('Preview / Commit', 'backstage-venue-manager');
		case 'rebuild':
			return __('Rebuild Ticket Config', 'backstage-venue-manager');
		case 'reconciliation':
			return __('Reconciliation', 'backstage-venue-manager');
		default:
			return __('Unknown / internal', 'backstage-venue-manager');
	}
}

function vms_ticket_mutation_audit_change_type_label(string $change_type): string
{
	switch (sanitize_key($change_type)) {
		case 'ticket_config_saved':
			return __('Ticket config saved', 'backstage-venue-manager');
		case 'ticket_template_applied':
			return __('Ticket template applied', 'backstage-venue-manager');
		case 'ticket_config_cleared':
			return __('Ticket config cleared', 'backstage-venue-manager');
		case 'preview_commit_applied':
			return __('Preview / Commit applied', 'backstage-venue-manager');
		case 'ticket_map_rebuilt':
			return __('Rebuild Ticket Config', 'backstage-venue-manager');
		case 'legacy_map_normalized':
			return __('Legacy mapping normalized', 'backstage-venue-manager');
		case 'event_save_sync':
			return __('Event save sync', 'backstage-venue-manager');
		case 'tec_ticket_reconciliation':
			return __('Ticket reconciliation', 'backstage-venue-manager');
		case 'sync_map_updated':
			return __('Sync map updated', 'backstage-venue-manager');
		case 'legacy_conflict_detected':
			return __('Legacy conflict detected', 'backstage-venue-manager');
		default:
			return __('Ticket mutation', 'backstage-venue-manager');
	}
}

function vms_ticket_mutation_audit_normalize_result_status(string $result_status): string
{
	$result_status = sanitize_key($result_status);
	return in_array($result_status, array('success', 'no_op', 'partial', 'failed'), true) ? $result_status : 'success';
}

function vms_ticket_mutation_audit_infer_change_type(string $meta_key, array $context, string $trigger_source, string $source_function): string
{
	$change_type = sanitize_key((string) ($context['change_type'] ?? ''));
	if ($change_type !== '') {
		return $change_type;
	}

	if (function_exists('vms_ticketing_v2_k') && $meta_key === vms_ticketing_v2_k('config')) {
		if (strpos($source_function, 'template') !== false) {
			return 'ticket_template_applied';
		}
		if ($trigger_source === 'import') {
			return 'legacy_map_normalized';
		}
		return 'ticket_config_saved';
	}

	if (function_exists('vms_ticketing_v2_k') && $meta_key === vms_ticketing_v2_k('sync')) {
		if ($trigger_source === 'reconciliation') {
			return 'tec_ticket_reconciliation';
		}
		if ($trigger_source === 'rebuild') {
			return 'ticket_map_rebuilt';
		}
		if ($trigger_source === 'preview_commit' || strpos($source_function, 'commit_sync') !== false) {
			return 'preview_commit_applied';
		}
		return 'sync_map_updated';
	}

	if ($meta_key === '_vms_ticketing_enabled_override') {
		return 'event_save_sync';
	}

	return 'ticket_config_saved';
}

function vms_ticket_mutation_audit_snapshot_signature(array $snapshot): array
{
	return array(
		'config_hash' => (string) ($snapshot['config']['hash'] ?? ''),
		'sync_hash' => (string) ($snapshot['sync_map']['hash'] ?? ''),
		'classification' => (string) ($snapshot['origin']['classification'] ?? ''),
		'mapped_product_ids' => array_values(array_map('absint', (array) ($snapshot['sync_map']['mapped_product_ids'] ?? array()))),
		'attached_product_ids' => array_values(array_map('absint', (array) ($snapshot['attached_product_ids'] ?? array()))),
		'legacy_leftover_ids' => array_values(array_map('absint', (array) ($snapshot['legacy_leftover_ids'] ?? array()))),
	);
}

function vms_ticket_mutation_audit_snapshot_hash(array $snapshot): string
{
	$json = wp_json_encode(vms_ticket_mutation_audit_snapshot_signature($snapshot));
	return sha1(is_string($json) ? $json : '');
}

function vms_ticket_mutation_audit_build_summary(string $change_type, string $result_status, array $before_snapshot, array $after_snapshot, array $context): string
{
	$summary = trim((string) ($context['summary_text'] ?? ''));
	if ($summary === '') {
		$summary = vms_ticket_mutation_audit_change_type_label($change_type);
	}

	if ($result_status === 'no_op') {
		return trim($summary . ' ' . __('No mapping snapshot change was recorded.', 'backstage-venue-manager'));
	}

	$parts = array($summary);

	$before_mapped = count((array) ($before_snapshot['sync_map']['mapped_product_ids'] ?? array()));
	$after_mapped = count((array) ($after_snapshot['sync_map']['mapped_product_ids'] ?? array()));
	if ($before_mapped !== $after_mapped) {
		/* translators: 1: number 1 used in this message, 2: number 2 used in this message. */
		$parts[] = sprintf(__('Mapped products %1$d -> %2$d.', 'backstage-venue-manager'), $before_mapped, $after_mapped);
	}

	$before_leftovers = count((array) ($before_snapshot['legacy_leftover_ids'] ?? array()));
	$after_leftovers = count((array) ($after_snapshot['legacy_leftover_ids'] ?? array()));
	if ($before_leftovers !== $after_leftovers) {
		/* translators: 1: number 1 used in this message, 2: number 2 used in this message. */
		$parts[] = sprintf(__('Legacy leftovers %1$d -> %2$d.', 'backstage-venue-manager'), $before_leftovers, $after_leftovers);
	}

	$before_origin = sanitize_key((string) ($before_snapshot['origin']['classification'] ?? ''));
	$after_origin = sanitize_key((string) ($after_snapshot['origin']['classification'] ?? ''));
	if ($before_origin !== '' && $after_origin !== '' && $before_origin !== $after_origin) {
		/* translators: %s: human-readable value used in this message. */
		$parts[] = sprintf(__('Origin now reads as %s.', 'backstage-venue-manager'), vms_ticket_mutation_audit_origin_label($after_origin));
	}

	return implode(' ', array_filter(array_map('trim', $parts)));
}

function vms_ticket_mutation_audit_prune_logs(): void
{
	global $wpdb;
	$table = vms_ticket_mutation_audit_table_name();
	$cutoff = gmdate('Y-m-d H:i:s', time() - (90 * DAY_IN_SECONDS));
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Ninety-day retention pruning deletes expired rows from the plugin-owned mutation audit table; no core API owns this repository.
	$wpdb->query($wpdb->prepare('DELETE FROM %i WHERE created_at_gmt < %s', $table, $cutoff));
}

function vms_ticket_mutation_audit_insert(array $row): int
{
	global $wpdb;

	if ((string) get_option(vms_ticket_mutation_audit_schema_option_key(), '') !== vms_ticket_mutation_audit_schema_target()) {
		return 0;
	}

	$table = vms_ticket_mutation_audit_table_name();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Mutation auditing persists one normalized row in the plugin-owned audit table through wpdb::insert(); no core API owns this repository.
	$ok = $wpdb->insert(
		$table,
		array(
			'created_at_gmt' => gmdate('Y-m-d H:i:s'),
			'plan_id' => absint($row['plan_id'] ?? 0),
			'tec_event_id' => absint($row['tec_event_id'] ?? 0),
			'event_title' => sanitize_text_field((string) ($row['event_title'] ?? '')),
			'user_id' => ($row['user_id'] ?? null) !== null ? absint($row['user_id']) : null,
			'trigger_source' => sanitize_key((string) ($row['trigger_source'] ?? '')),
			'source_hook' => sanitize_key((string) ($row['source_hook'] ?? '')),
			'source_function' => sanitize_text_field((string) ($row['source_function'] ?? '')),
			'change_type' => sanitize_key((string) ($row['change_type'] ?? '')),
			'result_status' => vms_ticket_mutation_audit_normalize_result_status((string) ($row['result_status'] ?? 'success')),
			'summary_text' => sanitize_text_field((string) ($row['summary_text'] ?? '')),
			'before_json' => is_string($row['before_json'] ?? null) ? $row['before_json'] : wp_json_encode($row['before_json'] ?? array()),
			'after_json' => is_string($row['after_json'] ?? null) ? $row['after_json'] : wp_json_encode($row['after_json'] ?? array()),
		),
		array('%s', '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
	);

	if ($ok === false) {
		return 0;
	}

	vms_ticket_mutation_audit_prune_logs();
	return (int) $wpdb->insert_id;
}

function vms_ticket_mutation_audit_log_direct_change(int $plan_id, array $args = array()): int
{
	$plan_id = absint($plan_id);
	if ($plan_id <= 0) {
		return 0;
	}

	$started_at = microtime(true);
	$guard = vms_ticket_mutation_audit_guard_decision('ticket_mutation_audit_direct', $plan_id, (string) ($args['meta_key'] ?? ''));
	$guard['operation'] = 'direct_log';
	if (empty($guard['allowed'])) {
		vms_ticket_mutation_audit_trace('skipped', $guard, $started_at);
		return 0;
	}

	$before_snapshot = is_array($args['before_snapshot'] ?? null) ? $args['before_snapshot'] : vms_ticket_mutation_audit_build_snapshot($plan_id);
	$after_snapshot = is_array($args['after_snapshot'] ?? null) ? $args['after_snapshot'] : vms_ticket_mutation_audit_build_snapshot($plan_id);
	$context = vms_ticket_mutation_audit_current_context();
	$source = vms_ticket_mutation_audit_resolve_source(
		array(
			'source_hook' => (string) ($args['source_hook'] ?? ($context['source_hook'] ?? '')),
			'source_function' => (string) ($args['source_function'] ?? ($context['source_function'] ?? '')),
		),
		(string) ($guard['source_hook'] ?? '')
	);
	$source_hook = sanitize_key((string) ($source['source_hook'] ?? ''));
	$source_function = sanitize_text_field((string) ($source['source_function'] ?? ''));
	$trigger_source = vms_ticket_mutation_audit_trigger_source($context, $source_hook, $source_function);
	$change_type = sanitize_key((string) ($args['change_type'] ?? $context['change_type'] ?? 'sync_map_updated'));
	$result_status = vms_ticket_mutation_audit_normalize_result_status((string) ($args['result_status'] ?? 'success'));
	$summary_text = sanitize_text_field((string) ($args['summary_text'] ?? vms_ticket_mutation_audit_build_summary($change_type, $result_status, $before_snapshot, $after_snapshot, $context)));

	$result = vms_ticket_mutation_audit_insert(
		array(
			'plan_id' => $plan_id,
			'tec_event_id' => absint($after_snapshot['tec_event_id'] ?? $before_snapshot['tec_event_id'] ?? 0),
			'event_title' => (string) ($after_snapshot['event_title'] ?? $before_snapshot['event_title'] ?? get_the_title($plan_id)),
			'user_id' => get_current_user_id(),
			'trigger_source' => $trigger_source,
			'source_hook' => $source_hook,
			'source_function' => $source_function,
			'change_type' => $change_type,
			'result_status' => $result_status,
			'summary_text' => $summary_text,
			'before_json' => wp_json_encode($before_snapshot),
				'after_json' => wp_json_encode($after_snapshot),
			)
	);
	vms_ticket_mutation_audit_trace('allowed', array_merge($guard, $source), $started_at);
	return $result;
}

function vms_ticket_mutation_audit_record_post_meta_write(string $operation, int $object_id, string $meta_key): void
{
	$object_id = absint($object_id);
	$meta_key = (string) $meta_key;
	if (!vms_ticket_mutation_audit_is_relevant_meta_write($object_id, $meta_key)) {
		return;
	}

	$started_at = microtime(true);
	$guard = vms_ticket_mutation_audit_guard_decision('ticket_mutation_audit_post', $object_id, $meta_key);
	$guard['operation'] = $operation;
	if (empty($guard['allowed'])) {
		vms_ticket_mutation_audit_trace('skipped', $guard, $started_at);
		return;
	}

	$pending = vms_ticket_mutation_audit_dequeue_pending($operation, $object_id, $meta_key);
	$before_snapshot = is_array($pending['before_snapshot'] ?? null) ? $pending['before_snapshot'] : vms_ticket_mutation_audit_build_snapshot($object_id);
	$after_snapshot = vms_ticket_mutation_audit_build_snapshot($object_id);
	$context = is_array($pending['context'] ?? null) ? $pending['context'] : vms_ticket_mutation_audit_current_context();
	$source_hook = sanitize_key((string) ($pending['source_hook'] ?? ''));
	$source_function = sanitize_text_field((string) ($pending['source_function'] ?? ''));
	if ($source_hook === '' || $source_function === '') {
		$source = vms_ticket_mutation_audit_resolve_source(
			array(
				'source_hook' => $source_hook,
				'source_function' => $source_function,
			),
			(string) ($guard['source_hook'] ?? '')
		);
		$source_hook = sanitize_key((string) ($source['source_hook'] ?? $source_hook));
		$source_function = sanitize_text_field((string) ($source['source_function'] ?? $source_function));
	}

	$trigger_source = vms_ticket_mutation_audit_trigger_source($context, $source_hook, $source_function);
	$change_type = vms_ticket_mutation_audit_infer_change_type($meta_key, $context, $trigger_source, $source_function);
	$result_status = vms_ticket_mutation_audit_snapshot_hash($before_snapshot) === vms_ticket_mutation_audit_snapshot_hash($after_snapshot)
		? 'no_op'
		: vms_ticket_mutation_audit_normalize_result_status((string) ($context['requested_result_status'] ?? 'success'));
	$summary_text = vms_ticket_mutation_audit_build_summary($change_type, $result_status, $before_snapshot, $after_snapshot, $context);

	vms_ticket_mutation_audit_insert(
		array(
			'plan_id' => $object_id,
			'tec_event_id' => absint($after_snapshot['tec_event_id'] ?? 0),
			'event_title' => (string) ($after_snapshot['event_title'] ?? get_the_title($object_id)),
			'user_id' => get_current_user_id(),
			'trigger_source' => $trigger_source,
			'source_hook' => $source_hook,
			'source_function' => $source_function,
			'change_type' => $change_type,
			'result_status' => $result_status,
			'summary_text' => $summary_text,
				'before_json' => wp_json_encode($before_snapshot),
				'after_json' => wp_json_encode($after_snapshot),
			)
	);
	vms_ticket_mutation_audit_trace(
		'allowed',
		array_merge(
			$guard,
			array(
				'source_hook' => $source_hook,
				'source_function' => $source_function,
			)
		),
		$started_at
	);
}

function vms_ticket_mutation_audit_after_add_post_meta($meta_id, $object_id, $meta_key, $meta_value): void
{
	unset($meta_id, $meta_value);
	vms_ticket_mutation_audit_record_post_meta_write('add', absint($object_id), (string) $meta_key);
}
add_action('added_post_meta', 'vms_ticket_mutation_audit_after_add_post_meta', 10, 4);

function vms_ticket_mutation_audit_after_update_post_meta($meta_id, $object_id, $meta_key, $meta_value): void
{
	unset($meta_id, $meta_value);
	vms_ticket_mutation_audit_record_post_meta_write('update', absint($object_id), (string) $meta_key);
}
add_action('updated_post_meta', 'vms_ticket_mutation_audit_after_update_post_meta', 10, 4);

function vms_ticket_mutation_audit_after_delete_post_meta($meta_ids, $object_id, $meta_key, $meta_value): void
{
	unset($meta_ids, $meta_value);
	vms_ticket_mutation_audit_record_post_meta_write('delete', absint($object_id), (string) $meta_key);
}
add_action('deleted_post_meta', 'vms_ticket_mutation_audit_after_delete_post_meta', 10, 4);

function vms_ticket_mutation_audit_sync_map_subset(array $sync_map): array
{
	$ticket_map = array();
	foreach ((array) ($sync_map['tickets'] ?? array()) as $ticket_key => $row) {
		if (!is_array($row)) {
			continue;
		}
		$ticket_key = sanitize_key((string) $ticket_key);
		if ($ticket_key === '') {
			continue;
		}
		$ticket_map[$ticket_key] = absint($row['woo_product_id'] ?? 0);
	}

	$entitlement_map = array();
	foreach ((array) ($sync_map['entitlements'] ?? array()) as $entitlement_id => $row) {
		if (!is_array($row)) {
			continue;
		}
		$entitlement_id = sanitize_key((string) $entitlement_id);
		if ($entitlement_id === '') {
			continue;
		}
		$entitlement_map[$entitlement_id] = absint($row['woo_product_id'] ?? 0);
	}

	$ga_map = is_array($sync_map['ga'] ?? null) ? $sync_map['ga'] : array();
	$ga_pid = absint($ga_map['woo_product_id'] ?? 0);

	$mapped_product_ids = array_values(array_unique(array_filter(array_merge(array_values($ticket_map), array_values($entitlement_map), $ga_pid > 0 ? array($ga_pid) : array()))));
	sort($mapped_product_ids, SORT_NUMERIC);

	$json = wp_json_encode(
		array(
			'ticket_map' => $ticket_map,
			'entitlement_map' => $entitlement_map,
			'ga_pid' => $ga_pid,
		)
	);

	return array(
		'ticket_map' => $ticket_map,
		'entitlement_map' => $entitlement_map,
		'ga_pid' => $ga_pid,
		'mapped_product_ids' => $mapped_product_ids,
		'hash' => sha1(is_string($json) ? $json : ''),
	);
}

function vms_ticket_mutation_audit_build_snapshot(int $plan_id): array
{
	$plan_id = absint($plan_id);
	if ($plan_id <= 0) {
		return array();
	}

	$tec_event_id = function_exists('vms_ticketing_b_get_linked_tec_event_id')
		? absint(vms_ticketing_b_get_linked_tec_event_id($plan_id))
		: absint(get_post_meta($plan_id, '_vms_tec_event_id', true));
	$event_title = $tec_event_id > 0 ? (string) get_the_title($tec_event_id) : (string) get_the_title($plan_id);
	$cfg = function_exists('vms_ticketing_v2_get_saved_config') ? vms_ticketing_v2_get_saved_config($plan_id) : array();
	$sync = function_exists('vms_ticketing_v2_get_sync') ? vms_ticketing_v2_get_sync($plan_id) : array();
	$sync_map = is_array($sync['map'] ?? null) ? $sync['map'] : array();
	$sync_subset = vms_ticket_mutation_audit_sync_map_subset($sync_map);

	$ticket_rows = array();
	$primary_ticket_keys = array();
	$qualified_ticket_keys = array();
	foreach ((array) ($cfg['tickets'] ?? array()) as $ticket_row) {
		if (!is_array($ticket_row)) {
			continue;
		}

		$ticket_key = sanitize_key((string) ($ticket_row['ticket_key'] ?? $ticket_row['key'] ?? ''));
		if ($ticket_key === '') {
			continue;
		}

		$ticket_rows[] = array(
			'ticket_key' => $ticket_key,
			'enabled' => !array_key_exists('enabled', $ticket_row) || !empty($ticket_row['enabled']) ? 1 : 0,
			'title' => trim((string) ($ticket_row['title'] ?? $ticket_key)),
			'visibility_mode' => sanitize_key((string) ($ticket_row['visibility_mode'] ?? 'public')),
			'counts_toward_unlock' => !empty($ticket_row['counts_toward_unlock']) ? 1 : 0,
			'mapped_product_id' => absint($sync_subset['ticket_map'][$ticket_key] ?? 0),
		);

		if (!empty($ticket_row['counts_toward_unlock'])) {
			$primary_ticket_keys[] = $ticket_key;
		}
		if (sanitize_key((string) ($ticket_row['visibility_mode'] ?? '')) === 'verified') {
			$qualified_ticket_keys[] = $ticket_key;
		}
	}

	$entitlement_rows = array();
	foreach ((array) ($cfg['entitlements'] ?? array()) as $entitlement_row) {
		if (!is_array($entitlement_row)) {
			continue;
		}

		$entitlement_id = sanitize_key((string) ($entitlement_row['entitlement_id'] ?? ''));
		if ($entitlement_id === '') {
			continue;
		}

		$entitlement_rows[] = array(
			'entitlement_id' => $entitlement_id,
			'enabled' => !empty($entitlement_row['enabled']) ? 1 : 0,
			'label' => trim((string) ($entitlement_row['label'] ?? $entitlement_id)),
			'mapped_product_id' => absint($sync_subset['entitlement_map'][$entitlement_id] ?? 0),
		);
	}

	$attached_product_ids = array();
	if ($tec_event_id > 0 && function_exists('vms_ticketing_b_get_event_ticket_products')) {
		$attached_product_ids = array_values(array_unique(array_filter(array_map('absint', (array) vms_ticketing_b_get_event_ticket_products($tec_event_id)))));
		sort($attached_product_ids, SORT_NUMERIC);
	}

	$all_product_ids = array_values(array_unique(array_merge($attached_product_ids, (array) $sync_subset['mapped_product_ids'])));
	sort($all_product_ids, SORT_NUMERIC);

	$product_summaries = array();
	$legacy_leftover_products = array();
	$untracked_products = array();
	$vms_managed_product_ids = array();
	$ticket_title_lookup = array();
	foreach ($ticket_rows as $row) {
		$ticket_title_lookup[sanitize_key((string) $row['ticket_key'])] = (string) ($row['title'] ?? '');
	}
	$entitlement_label_lookup = array();
	foreach ($entitlement_rows as $row) {
		$entitlement_label_lookup[sanitize_key((string) $row['entitlement_id'])] = (string) ($row['label'] ?? '');
	}

	foreach ($all_product_ids as $product_id) {
		$product_id = absint($product_id);
		$post = $product_id > 0 ? get_post($product_id) : null;
		$post_status = ($post instanceof WP_Post) ? (string) $post->post_status : 'missing';
		$post_type = ($post instanceof WP_Post) ? (string) $post->post_type : '';
		$sku = $product_id > 0 ? trim((string) get_post_meta($product_id, '_sku', true)) : '';
		$linked_event_id = $product_id > 0 ? absint(get_post_meta($product_id, '_tribe_wooticket_for_event', true)) : 0;
		$source_provider = ($product_id > 0 && function_exists('vms_ticketing_v2_product_meta_key'))
			? sanitize_key((string) get_post_meta($product_id, vms_ticketing_v2_product_meta_key('ticketing_source_provider'), true))
			: '';
		$source_plan_id = ($product_id > 0 && function_exists('vms_ticketing_v2_product_meta_key'))
			? absint(get_post_meta($product_id, vms_ticketing_v2_product_meta_key('ticketing_source_plan_id'), true))
			: 0;
		$ticket_key = ($product_id > 0 && function_exists('vms_ticketing_v2_product_meta_key'))
			? sanitize_key((string) get_post_meta($product_id, vms_ticketing_v2_product_meta_key('ticketing_ticket_key'), true))
			: '';
		$entitlement_id = ($product_id > 0 && function_exists('vms_ticketing_v2_product_meta_key'))
			? sanitize_key((string) get_post_meta($product_id, vms_ticketing_v2_product_meta_key('ticketing_entitlement_id'), true))
			: '';
		$product_role = ($product_id > 0 && function_exists('vms_ticketing_v2_product_meta_key'))
			? sanitize_key((string) get_post_meta($product_id, vms_ticketing_v2_product_meta_key('product_role'), true))
			: '';
		$total_sales = $product_id > 0 ? max(0, (int) get_post_meta($product_id, 'total_sales', true)) : 0;
		$catalog_visibility = function_exists('vms_ticket_integrity_product_catalog_visibility')
			? (string) vms_ticket_integrity_product_catalog_visibility($product_id)
			: '';
		$is_public = ($post_status === 'publish' && $catalog_visibility !== 'hidden');
		$is_attached = in_array($product_id, $attached_product_ids, true);
		$is_mapped = in_array($product_id, (array) $sync_subset['mapped_product_ids'], true);
		$is_legacy = (
			stripos($sku, 'SR-') === 0
			|| (string) get_post_meta($product_id, '_vms_legacy_retired', true) === '1'
			|| ($is_attached && !$is_mapped && ($source_provider === '' || $source_plan_id <= 0))
		);
		$is_vms_managed = (
			$source_provider === 'tec_tickets_woo'
			|| stripos($sku, 'VMS-TEC') === 0
			|| $source_plan_id === $plan_id
		);

		if ($is_vms_managed) {
			$vms_managed_product_ids[] = $product_id;
		}

		$summary = array(
			'product_id' => $product_id,
			'title' => $product_id > 0 ? (string) get_the_title($product_id) : '',
			'post_status' => $post_status,
			'post_type' => $post_type,
			'sku' => $sku,
			'linked_event_id' => $linked_event_id,
			'source_provider' => $source_provider,
			'source_plan_id' => $source_plan_id,
			'ticket_key' => $ticket_key,
			'entitlement_id' => $entitlement_id,
			'product_role' => $product_role,
			'total_sales' => $total_sales,
			'is_public' => $is_public ? 1 : 0,
			'is_attached' => $is_attached ? 1 : 0,
			'is_mapped' => $is_mapped ? 1 : 0,
			'is_legacy' => $is_legacy ? 1 : 0,
		);
		$product_summaries[] = $summary;

		if ($is_attached && !$is_mapped) {
			if ($is_legacy) {
				$legacy_leftover_products[] = $summary;
			} else {
				$untracked_products[] = $summary;
			}
		}
	}

	$import_key = function_exists('vms_event_plan_import_meta_key_import_key')
		? trim((string) get_post_meta($plan_id, vms_event_plan_import_meta_key_import_key(), true))
		: trim((string) get_post_meta($plan_id, '_vms_import_event_key', true));
	$tec_legacy_identifiers = ($tec_event_id > 0 && function_exists('vms_ticketing_get_tec_legacy_identifiers'))
		? (array) vms_ticketing_get_tec_legacy_identifiers($tec_event_id)
		: array();

	$config_json = wp_json_encode(
		array(
			'tickets' => $ticket_rows,
			'entitlements' => $entitlement_rows,
		)
	);
	$snapshot = array(
		'plan_id' => $plan_id,
		'tec_event_id' => $tec_event_id,
		'event_title' => $event_title,
		'event_status' => $tec_event_id > 0 ? (string) get_post_status($tec_event_id) : '',
		'mode' => sanitize_key((string) ($cfg['mode'] ?? '')),
		'has_saved_config' => !empty($cfg) ? 1 : 0,
		'import_key' => $import_key,
		'tec_legacy_identifiers' => $tec_legacy_identifiers,
		'config' => array(
			'hash' => function_exists('vms_ticketing_v2_hash_config_for_sync')
				? (string) vms_ticketing_v2_hash_config_for_sync($cfg)
				: sha1(is_string($config_json) ? $config_json : ''),
			'ticket_rows' => $ticket_rows,
			'entitlement_rows' => $entitlement_rows,
			'primary_ticket_keys' => array_values(array_unique($primary_ticket_keys)),
			'qualified_ticket_keys' => array_values(array_unique($qualified_ticket_keys)),
		),
		'sync_map' => $sync_subset,
		'attached_product_ids' => $attached_product_ids,
		'product_summaries' => array_values($product_summaries),
		'legacy_leftover_ids' => array_values(array_map('absint', wp_list_pluck($legacy_leftover_products, 'product_id'))),
		'legacy_leftover_products' => array_values($legacy_leftover_products),
		'untracked_products' => array_values($untracked_products),
		'vms_managed_product_ids' => array_values(array_unique(array_filter(array_map('absint', $vms_managed_product_ids)))),
	);

	$snapshot['origin'] = vms_ticket_mutation_audit_classify_snapshot($snapshot);

	return $snapshot;
}

function vms_ticket_mutation_audit_classify_snapshot(array $snapshot): array
{
	$import_key = trim((string) ($snapshot['import_key'] ?? ''));
	$legacy_identifiers = is_array($snapshot['tec_legacy_identifiers'] ?? null) ? $snapshot['tec_legacy_identifiers'] : array();
	$legacy_leftovers = is_array($snapshot['legacy_leftover_products'] ?? null) ? $snapshot['legacy_leftover_products'] : array();
	$untracked_products = is_array($snapshot['untracked_products'] ?? null) ? $snapshot['untracked_products'] : array();
	$vms_managed_ids = array_values(array_filter(array_map('absint', (array) ($snapshot['vms_managed_product_ids'] ?? array()))));
	$has_import_signal = ($import_key !== '' || !empty($legacy_identifiers));
	$has_legacy_leftovers = !empty($legacy_leftovers);
	$has_untracked_products = !empty($untracked_products);
	$has_vms_managed = !empty($vms_managed_ids) || !empty((array) ($snapshot['sync_map']['ticket_map'] ?? array())) || !empty((array) ($snapshot['sync_map']['entitlement_map'] ?? array()));

	$classification = 'unknown';
	$reasons = array();
	if ($import_key !== '') {
		$reasons[] = __('Event Plan carries an import key.', 'backstage-venue-manager');
	}
	if (!empty($legacy_identifiers)) {
		$reasons[] = __('Linked calendar event carries legacy import identifiers.', 'backstage-venue-manager');
	}
	if ($has_legacy_leftovers) {
		$reasons[] = __('Legacy-looking ticket products are still attached to the calendar event.', 'backstage-venue-manager');
	}
	if ($has_untracked_products) {
		$reasons[] = __('Extra ticket products are attached without a current VMS mapping.', 'backstage-venue-manager');
	}

	if ($has_import_signal || $has_legacy_leftovers || $has_untracked_products) {
		$classification = ($has_vms_managed && ($has_legacy_leftovers || $has_untracked_products))
			? 'mixed_or_reconciled'
			: 'imported_legacy';
	} elseif ($has_vms_managed || !empty((array) ($snapshot['config']['ticket_rows'] ?? array())) || empty((array) ($snapshot['attached_product_ids'] ?? array()))) {
		$classification = 'vms_native';
	} else {
		$classification = 'unknown';
	}

	return array(
		'classification' => $classification,
		'label' => vms_ticket_mutation_audit_origin_label($classification),
		'drift_risk' => ($classification === 'imported_legacy' || $classification === 'mixed_or_reconciled') ? 1 : 0,
		'reasons' => $reasons,
	);
}

function vms_ticket_mutation_audit_normalize_log_row($row): array
{
	if (!is_array($row)) {
		return array();
	}

	$created = trim((string) ($row['created_at_gmt'] ?? ''));
	$timestamp = $created !== '' ? strtotime($created . ' UTC') : 0;

	return array(
		'id' => absint($row['id'] ?? 0),
		'created_at_gmt' => $created,
		'timestamp_gmt' => $timestamp ? (int) $timestamp : 0,
		'plan_id' => absint($row['plan_id'] ?? 0),
		'tec_event_id' => absint($row['tec_event_id'] ?? 0),
		'event_title' => (string) ($row['event_title'] ?? ''),
		'user_id' => absint($row['user_id'] ?? 0),
		'trigger_source' => sanitize_key((string) ($row['trigger_source'] ?? '')),
		'source_hook' => sanitize_key((string) ($row['source_hook'] ?? '')),
		'source_function' => sanitize_text_field((string) ($row['source_function'] ?? '')),
		'change_type' => sanitize_key((string) ($row['change_type'] ?? '')),
		'change_type_label' => vms_ticket_mutation_audit_change_type_label((string) ($row['change_type'] ?? '')),
		'result_status' => vms_ticket_mutation_audit_normalize_result_status((string) ($row['result_status'] ?? '')),
		'result_label' => vms_ticket_mutation_audit_result_label((string) ($row['result_status'] ?? '')),
		'summary_text' => (string) ($row['summary_text'] ?? ''),
	);
}

function vms_ticket_mutation_audit_recent_logs(int $plan_id, int $limit = 5): array
{
	$plan_id = absint($plan_id);
	$limit = max(1, min(50, absint($limit)));
	if ($plan_id <= 0 || (string) get_option(vms_ticket_mutation_audit_schema_option_key(), '') !== vms_ticket_mutation_audit_schema_target()) {
		return array();
	}

	global $wpdb;
	$table = vms_ticket_mutation_audit_table_name();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Mutation history must read request-fresh plugin-owned audit rows so newly recorded state is immediately visible.
	$rows = $wpdb->get_results(
		$wpdb->prepare('SELECT * FROM %i WHERE plan_id = %d ORDER BY id DESC LIMIT %d', $table, $plan_id, $limit),
		ARRAY_A
	);

	if (!is_array($rows)) {
		return array();
	}

	return array_values(array_filter(array_map('vms_ticket_mutation_audit_normalize_log_row', $rows)));
}

function vms_ticket_mutation_audit_latest_log(int $plan_id): array
{
	$logs = vms_ticket_mutation_audit_recent_logs($plan_id, 1);
	return !empty($logs[0]) && is_array($logs[0]) ? $logs[0] : array();
}

function vms_ticket_mutation_audit_detect_repeated_drift(int $plan_id, array $issues, array $recent_logs = array()): array
{
	$plan_id = absint($plan_id);
	if ($plan_id <= 0) {
		return array('flagged' => false);
	}

	$open_issues = function_exists('vms_ticket_integrity_open_issues')
		? vms_ticket_integrity_open_issues($issues)
		: array_values(array_filter($issues, static function ($issue): bool {
			return is_array($issue) && sanitize_key((string) ($issue['status'] ?? 'open')) !== 'resolved';
		}));

	$mapping_issue_present = false;
	foreach ($open_issues as $issue) {
		if (!is_array($issue)) {
			continue;
		}
		$category = sanitize_key((string) ($issue['category'] ?? ''));
		if (in_array($category, array('mapping', 'structure', 'render', 'addons'), true)) {
			$mapping_issue_present = true;
			break;
		}
	}

	if (!$mapping_issue_present) {
		return array('flagged' => false);
	}

	if (empty($recent_logs)) {
		$recent_logs = vms_ticket_mutation_audit_recent_logs($plan_id, 12);
	}

	$repair_logs = array_values(array_filter($recent_logs, static function (array $row): bool {
		return sanitize_key((string) ($row['change_type'] ?? '')) === 'ticket_map_rebuilt';
	}));

	if (empty($repair_logs)) {
		return array('flagged' => false);
	}

	$latest_repair_id = absint($repair_logs[0]['id'] ?? 0);
	$post_repair_mutation = array();
	foreach ($recent_logs as $row) {
		if (!is_array($row)) {
			continue;
		}

		$row_id = absint($row['id'] ?? 0);
		if ($row_id <= $latest_repair_id) {
			continue;
		}

		$trigger_source = sanitize_key((string) ($row['trigger_source'] ?? ''));
		if (in_array($trigger_source, array('manual_action', 'rebuild'), true)) {
			continue;
		}

		$post_repair_mutation = $row;
		break;
	}

	$multiple_repairs = count($repair_logs) >= 2;
	if (empty($post_repair_mutation) && !$multiple_repairs) {
		return array('flagged' => false);
	}

	$severity = !empty($post_repair_mutation) ? 'red' : 'yellow';
	$message = !empty($post_repair_mutation)
		? __('This event developed mapping problems again after a prior repair, and a later non-manual process appears to have touched ticket relationships.', 'backstage-venue-manager')
		: __('This event has required repeated repair attempts and is still showing mapping drift.', 'backstage-venue-manager');

	return array(
		'flagged' => true,
		'severity' => $severity,
		'message' => $message,
		'latest_repair' => $repair_logs[0],
		'post_repair_mutation' => $post_repair_mutation,
	);
}

function vms_ticket_mutation_audit_build_event_diagnostics(int $plan_id, array $args = array()): array
{
	$plan_id = absint($plan_id);
	if ($plan_id <= 0) {
		return array();
	}

	$snapshot = is_array($args['snapshot'] ?? null) ? $args['snapshot'] : vms_ticket_mutation_audit_build_snapshot($plan_id);
	$recent_logs = vms_ticket_mutation_audit_recent_logs($plan_id, 6);
	$latest_log = !empty($recent_logs[0]) ? $recent_logs[0] : array();
	$last_repair = array();
	foreach ($recent_logs as $row) {
		if (sanitize_key((string) ($row['change_type'] ?? '')) === 'ticket_map_rebuilt') {
			$last_repair = $row;
			break;
		}
	}

	$issues = is_array($args['issues'] ?? null) ? $args['issues'] : array();
	$repeated_drift = vms_ticket_mutation_audit_detect_repeated_drift($plan_id, $issues, $recent_logs);
	$origin = is_array($snapshot['origin'] ?? null) ? $snapshot['origin'] : array();
	$legacy_leftovers = array_values((array) ($snapshot['legacy_leftover_products'] ?? array()));
	$untracked_products = array_values((array) ($snapshot['untracked_products'] ?? array()));
	$mapped_tickets = array_values((array) ($snapshot['config']['ticket_rows'] ?? array()));
	$public_path_healthy = empty(function_exists('vms_ticket_integrity_open_issues') ? vms_ticket_integrity_open_issues($issues) : $issues);

	$recommended_action = __('Review the latest mutation record before running another repair.', 'backstage-venue-manager');
	if (!empty($repeated_drift['flagged'])) {
		$recommended_action = __('This event is showing repeat drift. Watch the next non-manual mutation entry after repair to find the writer that is reintroducing the problem.', 'backstage-venue-manager');
	} elseif (sanitize_key((string) ($origin['classification'] ?? '')) === 'vms_native') {
		$recommended_action = __('This looks like a current mapping bug rather than imported residue. Start with the latest mutation source and compare before/after snapshots.', 'backstage-venue-manager');
	} elseif (!empty($legacy_leftovers) || !empty($untracked_products)) {
		$recommended_action = __('Treat this as a legacy cleanup case. Rebuild can normalize active mappings, but keep legacy leftovers under review instead of deleting them blindly.', 'backstage-venue-manager');
	} elseif (!empty($last_repair) && sanitize_key((string) ($last_repair['result_status'] ?? '')) === 'no_op') {
		$recommended_action = __('No mapping changes were applied during the last rebuild. Review the mutation history to see which save or sync path last touched this event.', 'backstage-venue-manager');
	}

	return array(
		'origin' => $origin,
		'latest_mutation' => $latest_log,
		'last_repair' => $last_repair,
		'recent_mutations' => $recent_logs,
		'repeated_drift' => $repeated_drift,
		'public_path_healthy' => $public_path_healthy ? 1 : 0,
		'active_mapped_tickets' => $mapped_tickets,
		'legacy_leftovers' => $legacy_leftovers,
		'untracked_products' => $untracked_products,
		'recommended_action' => $recommended_action,
	);
}
