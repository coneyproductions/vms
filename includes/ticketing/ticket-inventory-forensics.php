<?php
defined('ABSPATH') || exit;

function vms_ticket_inventory_forensics_schema_option_key(): string
{
	return defined('VMS_OPT_TICKET_INVENTORY_AUDIT_DB_SCHEMA_VERSION')
		? (string) VMS_OPT_TICKET_INVENTORY_AUDIT_DB_SCHEMA_VERSION
		: 'vms_ticket_inventory_audit_db_schema_version';
}

function vms_ticket_inventory_forensics_schema_target(): string
{
	return 'ticket_inventory_audit_v2';
}

function vms_ticket_inventory_forensics_table_name(): string
{
	global $wpdb;
	$suffix = defined('VMS_DB_TABLE_TICKET_INVENTORY_AUDIT_SUFFIX')
		? (string) VMS_DB_TABLE_TICKET_INVENTORY_AUDIT_SUFFIX
		: 'vms_ticket_inventory_audit';
	return $wpdb->prefix . $suffix;
}

function vms_ticket_inventory_forensics_maybe_upgrade_schema(): void
{
	$current = (string) get_option(vms_ticket_inventory_forensics_schema_option_key(), '');
	$target = vms_ticket_inventory_forensics_schema_target();
	if ($current === $target) {
		return;
	}

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	global $wpdb;
	$table = vms_ticket_inventory_forensics_table_name();
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE {$table} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		created_at_gmt DATETIME NOT NULL,
		plan_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
		tec_event_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
		event_title VARCHAR(255) NOT NULL DEFAULT '',
		product_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
		user_id BIGINT(20) UNSIGNED NULL,
		trigger_source VARCHAR(40) NOT NULL DEFAULT '',
		source_hook VARCHAR(120) NOT NULL DEFAULT '',
		source_function VARCHAR(160) NOT NULL DEFAULT '',
		mutation_key VARCHAR(120) NOT NULL DEFAULT '',
		product_role VARCHAR(40) NOT NULL DEFAULT '',
		change_type VARCHAR(80) NOT NULL DEFAULT '',
		result_status VARCHAR(20) NOT NULL DEFAULT '',
		derivation_source VARCHAR(120) NOT NULL DEFAULT '',
		confidence_level VARCHAR(20) NOT NULL DEFAULT '',
		expected_effect VARCHAR(20) NOT NULL DEFAULT '',
		reason_text TEXT NULL,
		summary_text TEXT NULL,
		before_json LONGTEXT NULL,
		after_json LONGTEXT NULL,
		details_json LONGTEXT NULL,
		PRIMARY KEY (id),
		KEY plan_id (plan_id),
		KEY tec_event_id (tec_event_id),
		KEY product_id (product_id),
		KEY created_at_gmt (created_at_gmt),
		KEY mutation_key (mutation_key),
		KEY product_role (product_role),
		KEY change_type (change_type),
		KEY result_status (result_status)
	) {$charset_collate};";

	dbDelta($sql);
	update_option(vms_ticket_inventory_forensics_schema_option_key(), $target, false);
}
add_action('plugins_loaded', 'vms_ticket_inventory_forensics_maybe_upgrade_schema', 11);

function vms_ticket_inventory_forensics_product_meta_keys(): array
{
	return array(
		'_stock',
		'_manage_stock',
		'_backorders',
		'_stock_status',
		'_tribe_ticket_capacity',
		'_global_stock_mode',
		'_global_stock_cap',
		'_ticket_start_date',
		'_ticket_end_date',
		'_vms_ticketing_capacity_v2',
		'_vms_ticketing_sold_qty_v2',
		'_vms_ticketing_remaining_v2',
		'_vms_ticketing_stock_reconciled_at_gmt',
		'_vms_ticketing_stock_reconcile_error',
		'_vms_ticketing_entitlement_capacity_v2',
		'_vms_ticketing_entitlement_sold_qty_v2',
		'_vms_ticketing_entitlement_remaining_v2',
		'_vms_ticketing_entitlement_stock_reconciled_at_gmt',
		'_vms_ticketing_entitlement_stock_reconcile_error',
		'_vms_ticketing_entitlement_oversold_by_v2',
	);
}

function vms_ticket_inventory_forensics_event_meta_keys(): array
{
	return array(
		'_tribe_ticket_use_global_stock',
		'_tribe_ticket_global_stock_level',
	);
}

function vms_ticket_inventory_forensics_is_relevant_meta_write(int $object_id, string $meta_key): bool
{
	$object_id = absint($object_id);
	$meta_key = (string) $meta_key;
	if ($object_id <= 0 || $meta_key === '') {
		return false;
	}

	$post_type = (string) get_post_type($object_id);
	if ($post_type === 'product') {
		return in_array($meta_key, vms_ticket_inventory_forensics_product_meta_keys(), true);
	}

	if ($post_type === 'tribe_events') {
		return in_array($meta_key, vms_ticket_inventory_forensics_event_meta_keys(), true);
	}

	return false;
}

function vms_ticket_inventory_forensics_current_context(): array
{
	if (function_exists('vms_ticket_mutation_audit_current_context')) {
		$context = vms_ticket_mutation_audit_current_context();
		return is_array($context) ? $context : array();
	}

	return array();
}

function vms_ticket_inventory_forensics_detect_source(): array
{
	if (function_exists('vms_ticket_mutation_audit_detect_source')) {
		$source = vms_ticket_mutation_audit_detect_source();
		return is_array($source) ? $source : array();
	}

	return array(
		'source_hook' => '',
		'source_function' => '',
	);
}

function vms_ticket_inventory_forensics_is_explicit_source_hook(string $source_hook): bool
{
	$source_hook = sanitize_key($source_hook);
	if ($source_hook === '') {
		return false;
	}

	if (
		$source_hook === 'transition_post_status'
		|| $source_hook === 'before_delete_post'
		|| $source_hook === 'delete_post'
		|| $source_hook === 'deleted_post'
		|| $source_hook === 'trash_post'
		|| strpos($source_hook, 'save_post') === 0
		|| strpos($source_hook, 'wp_ajax_') === 0
		|| strpos($source_hook, 'admin_post_') === 0
		|| strpos($source_hook, 'woocommerce_') === 0
		|| strpos($source_hook, 'tribe_tickets_') === 0
		|| strpos($source_hook, 'tribe_wootickets_') === 0
	) {
		return true;
	}

	return false;
}

function vms_ticket_inventory_forensics_guard_decision(
	string $hook_name,
	int $object_id = 0,
	string $meta_key = '',
	array $context = array(),
	string $source_hook = '',
	string $source_function = ''
): array {
	$hook_name = sanitize_key($hook_name);
	if ($hook_name === '') {
		$hook_name = 'ticket_inventory_forensics';
	}

	if (empty($context)) {
		$context = vms_ticket_inventory_forensics_current_context();
	}

	$source_hook = sanitize_key($source_hook !== '' ? $source_hook : (string) ($context['source_hook'] ?? ''));
	if ($source_hook === '' && function_exists('vms_ticket_mutation_audit_current_hook')) {
		$source_hook = vms_ticket_mutation_audit_current_hook();
	}

	$source_function = sanitize_text_field($source_function !== '' ? $source_function : (string) ($context['source_function'] ?? ''));
	$result = array(
		'allowed' => false,
		'reason' => 'unknown',
		'hook_name' => $hook_name,
		'object_id' => absint($object_id),
		'meta_key' => (string) $meta_key,
		'source_hook' => $source_hook,
		'source_function' => $source_function,
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

	if (vms_ticket_inventory_forensics_is_explicit_source_hook($source_hook)) {
		$result['allowed'] = true;
		$result['reason'] = 'inferred_mutation_source';
		return $result;
	}

	if (function_exists('vms_admin_guard_should_allow_heavy_block')) {
		$guard = (array) vms_admin_guard_should_allow_heavy_block(
			$hook_name,
			array(
				'task' => 'ticket_inventory_forensics',
				'allow_action' => 'ticket_inventory_forensics',
			)
		);
		$result['allowed'] = !empty($guard['allowed']);
		$result['reason'] = sanitize_key((string) ($guard['reason'] ?? 'unknown'));
		return $result;
	}

	$result['reason'] = is_admin() ? 'passive_admin_request' : 'non_mutation_request';
	return $result;
}

function vms_ticket_inventory_forensics_trace(string $decision, array $context = array(), float $started_at = 0.0): void
{
	$payload = array(
		'task' => 'ticket_inventory_forensics',
		'reason' => sanitize_key((string) ($context['reason'] ?? '')),
		'object_id' => absint($context['object_id'] ?? 0),
		'meta_key' => (string) ($context['meta_key'] ?? ''),
		'operation' => sanitize_key((string) ($context['operation'] ?? '')),
		'source_hook' => sanitize_key((string) ($context['source_hook'] ?? '')),
		'source_function' => sanitize_text_field((string) ($context['source_function'] ?? '')),
	);

	if (function_exists('vms_admin_guard_trace')) {
		vms_admin_guard_trace(
			sanitize_key((string) ($context['hook_name'] ?? 'ticket_inventory_forensics')),
			$decision,
			$payload,
			$started_at
		);
	}
}

function vms_ticket_inventory_forensics_trigger_source(array $context, string $source_hook, string $source_function): string
{
	if (function_exists('vms_ticket_mutation_audit_trigger_source')) {
		return (string) vms_ticket_mutation_audit_trigger_source($context, $source_hook, $source_function);
	}

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
		if (strpos($source_hook, 'save_post') === 0) {
			return 'save_hook';
		}
	}

	return 'unknown_internal';
}

function vms_ticket_inventory_forensics_result_label(string $result_status): string
{
	if (function_exists('vms_ticket_mutation_audit_result_label')) {
		$normalized = sanitize_key($result_status);
		if (in_array($normalized, array('success', 'no_op', 'partial', 'failed'), true)) {
			return (string) vms_ticket_mutation_audit_result_label($normalized);
		}
	}

	switch (sanitize_key($result_status)) {
		case 'success':
			return __('Success', 'backstage-venue-manager');
		case 'no_op':
			return __('No changes', 'backstage-venue-manager');
		case 'partial':
			return __('Partial', 'backstage-venue-manager');
		case 'skipped':
			return __('Skipped', 'backstage-venue-manager');
		case 'failed':
			return __('Failed', 'backstage-venue-manager');
		default:
			return __('Unknown', 'backstage-venue-manager');
	}
}

function vms_ticket_inventory_forensics_change_type_label(string $change_type): string
{
	switch (sanitize_key($change_type)) {
		case 'stock_zeroed':
			return __('Stock zeroed', 'backstage-venue-manager');
		case 'stock_restored':
			return __('Stock restored', 'backstage-venue-manager');
		case 'stock_quantity_changed':
			return __('Stock quantity changed', 'backstage-venue-manager');
		case 'manage_stock_enabled':
			return __('Manage stock enabled', 'backstage-venue-manager');
		case 'manage_stock_disabled':
			return __('Manage stock disabled', 'backstage-venue-manager');
		case 'stock_status_changed':
			return __('Stock status changed', 'backstage-venue-manager');
		case 'capacity_relinked':
			return __('Capacity / stock pool changed', 'backstage-venue-manager');
		case 'sale_window_changed':
			return __('Sale window changed', 'backstage-venue-manager');
		case 'availability_meta_normalized':
			return __('Availability meta normalized', 'backstage-venue-manager');
		case 'inventory_write_no_effect':
			return __('Inventory write had no effect', 'backstage-venue-manager');
		case 'repair_no_effect':
			return __('Repair had no effect', 'backstage-venue-manager');
		case 'repair_partial':
			return __('Repair changed some roles only', 'backstage-venue-manager');
		case 'repair_skipped_role':
			return __('Repair skipped a role', 'backstage-venue-manager');
		default:
			return __('Inventory mutation', 'backstage-venue-manager');
	}
}

function vms_ticket_inventory_forensics_normalize_confidence(string $confidence): string
{
	$confidence = sanitize_key($confidence);
	return in_array($confidence, array('authoritative', 'inferred', 'fallback', 'unknown'), true) ? $confidence : 'unknown';
}

function vms_ticket_inventory_forensics_normalize_expected_effect(string $effect): string
{
	$effect = sanitize_key($effect);
	return in_array($effect, array('reopen', 'close', 'preserve', 'unknown'), true) ? $effect : 'unknown';
}

function vms_ticket_inventory_forensics_confidence_label(string $confidence): string
{
	switch (vms_ticket_inventory_forensics_normalize_confidence($confidence)) {
		case 'authoritative':
			return __('Authoritative', 'backstage-venue-manager');
		case 'inferred':
			return __('Inferred', 'backstage-venue-manager');
		case 'fallback':
			return __('Fallback', 'backstage-venue-manager');
		default:
			return __('Unknown', 'backstage-venue-manager');
	}
}

function vms_ticket_inventory_forensics_expected_effect_label(string $effect): string
{
	switch (vms_ticket_inventory_forensics_normalize_expected_effect($effect)) {
		case 'reopen':
			return __('Reopen availability', 'backstage-venue-manager');
		case 'close':
			return __('Close availability', 'backstage-venue-manager');
		case 'preserve':
			return __('Preserve availability', 'backstage-venue-manager');
		default:
			return __('Unknown effect', 'backstage-venue-manager');
	}
}

function vms_ticket_inventory_forensics_cause_label(string $cause): string
{
	switch (sanitize_key($cause)) {
		case 'per_ticket_stock_corruption':
			return __('Per-ticket stock corruption', 'backstage-venue-manager');
		case 'shared_capacity_linkage_corruption':
			return __('Shared/event capacity linkage corruption', 'backstage-venue-manager');
		case 'sale_window_false_closure':
			return __('Sale-window false closure', 'backstage-venue-manager');
		case 'mixed_mode_corruption':
			return __('Mixed-mode corruption', 'backstage-venue-manager');
		case 'unknown_inventory_drift':
			return __('Unknown inventory drift', 'backstage-venue-manager');
		default:
			return __('Healthy / not flagged', 'backstage-venue-manager');
	}
}

function vms_ticket_inventory_forensics_role_label(string $role): string
{
	switch (sanitize_key($role)) {
		case 'standard_ticket':
			return __('Standard/public ticket', 'backstage-venue-manager');
		case 'qualified_ticket':
			return __('Verified/qualified ticket', 'backstage-venue-manager');
		case 'add_on':
		case 'entitlement':
		case 'addon':
			return __('Add-on', 'backstage-venue-manager');
		case 'ga_ticket':
		case 'ticket':
			return __('Ticket', 'backstage-venue-manager');
		case 'legacy_ticket':
			return __('Legacy ticket', 'backstage-venue-manager');
		default:
			return __('Ticket', 'backstage-venue-manager');
	}
}

function vms_ticket_inventory_forensics_mapping_label(string $mapping_state): string
{
	switch (sanitize_key($mapping_state)) {
		case 'ok':
			return __('Mapped', 'backstage-venue-manager');
		case 'unmapped':
			return __('Unmapped', 'backstage-venue-manager');
		case 'missing':
			return __('Missing product', 'backstage-venue-manager');
		case 'trash':
			return __('Trashed product', 'backstage-venue-manager');
		case 'not_product':
			return __('Wrong post type', 'backstage-venue-manager');
		case 'event_mismatch':
			return __('Wrong event link', 'backstage-venue-manager');
		case 'legacy_attached':
			return __('Legacy attached', 'backstage-venue-manager');
		case 'attached_untracked':
			return __('Attached / untracked', 'backstage-venue-manager');
		case 'mapped_only':
			return __('Mapped only', 'backstage-venue-manager');
		default:
			return __('Unknown', 'backstage-venue-manager');
	}
}

function vms_ticket_inventory_forensics_bool_label(bool $value): string
{
	return $value ? __('On', 'backstage-venue-manager') : __('Off', 'backstage-venue-manager');
}

function vms_ticket_inventory_forensics_display_quantity($value, string $empty = '—'): string
{
	if ($value === '' || $value === null) {
		return $empty;
	}

	if (is_numeric($value) && (int) $value === -1) {
		return __('Unlimited', 'backstage-venue-manager');
	}

	if (is_numeric($value)) {
		return (string) (int) $value;
	}

	return trim((string) $value) !== '' ? (string) $value : $empty;
}

function vms_ticket_inventory_forensics_post_type(int $object_id): string
{
	return $object_id > 0 ? (string) get_post_type($object_id) : '';
}

function vms_ticket_inventory_forensics_product_meta_key(string $which, string $fallback): string
{
	if (function_exists('vms_ticketing_v2_product_meta_key')) {
		$key = (string) vms_ticketing_v2_product_meta_key($which);
		if ($key !== '') {
			return $key;
		}
	}

	return $fallback;
}

function vms_ticket_inventory_forensics_find_plan_id_by_event(int $tec_event_id): int
{
	$tec_event_id = absint($tec_event_id);
	if ($tec_event_id <= 0) {
		return 0;
	}

	if (function_exists('vms_ticketing_v2_find_plan_id_by_tec_event_id')) {
		return absint(vms_ticketing_v2_find_plan_id_by_tec_event_id($tec_event_id));
	}

	if (function_exists('vms_get_event_plan_for_tec_event')) {
		return absint(vms_get_event_plan_for_tec_event($tec_event_id));
	}

	return 0;
}

function vms_ticket_inventory_forensics_resolve_scope(int $object_id): array
{
	$object_id = absint($object_id);
	if ($object_id <= 0) {
		return array();
	}

	$post_type = vms_ticket_inventory_forensics_post_type($object_id);
	$plan_id = 0;
	$tec_event_id = 0;
	$product_id = 0;

	if ($post_type === 'product') {
		$product_id = $object_id;
		$plan_id = absint(get_post_meta($product_id, vms_ticket_inventory_forensics_product_meta_key('event_plan_id', '_vms_event_plan_id'), true));
		$tec_event_id = absint(get_post_meta($product_id, '_tribe_wooticket_for_event', true));
		if ($tec_event_id <= 0) {
			$tec_event_id = absint(get_post_meta($product_id, vms_ticket_inventory_forensics_product_meta_key('tec_event_id', '_vms_tec_event_id'), true));
		}
		if ($plan_id <= 0 && $tec_event_id > 0) {
			$plan_id = vms_ticket_inventory_forensics_find_plan_id_by_event($tec_event_id);
		}
	} elseif ($post_type === 'tribe_events') {
		$tec_event_id = $object_id;
		$plan_id = vms_ticket_inventory_forensics_find_plan_id_by_event($tec_event_id);
	}

	$event_title = $tec_event_id > 0 ? (string) get_the_title($tec_event_id) : (string) get_the_title($object_id);

	return array(
		'post_type' => $post_type,
		'plan_id' => $plan_id,
		'tec_event_id' => $tec_event_id,
		'product_id' => $product_id,
		'event_title' => $event_title,
	);
}

function vms_ticket_inventory_forensics_meta_flag($value): bool
{
	$value = strtolower(trim((string) $value));
	return in_array($value, array('1', 'yes', 'true', 'on'), true);
}

function vms_ticket_inventory_forensics_parse_datetime(string $raw): int
{
	$raw = trim($raw);
	if ($raw === '') {
		return 0;
	}

	if (function_exists('vms_ticket_integrity_parse_wp_datetime')) {
		return absint(vms_ticket_integrity_parse_wp_datetime($raw));
	}

	$timezone = function_exists('wp_timezone') ? wp_timezone() : null;
	if ($timezone instanceof DateTimeZone) {
		try {
			$date = new DateTimeImmutable($raw, $timezone);
			return (int) $date->getTimestamp();
		} catch (Exception $exception) {
			unset($exception);
		}
	}

	$fallback = strtotime($raw);
	return $fallback ? (int) $fallback : 0;
}

function vms_ticket_inventory_forensics_build_window_state(string $start_raw, string $end_raw): array
{
	$now = time();
	$start_ts = vms_ticket_inventory_forensics_parse_datetime($start_raw);
	$end_ts = vms_ticket_inventory_forensics_parse_datetime($end_raw);
	$start_valid = ($start_raw === '' || $start_ts > 0);
	$end_valid = ($end_raw === '' || $end_ts > 0);
	$is_open = true;

	if ($start_valid && $start_ts > 0 && $now < $start_ts) {
		$is_open = false;
	}
	if ($end_valid && $end_ts > 0 && $now > $end_ts) {
		$is_open = false;
	}

	return array(
		'start_raw' => $start_raw,
		'end_raw' => $end_raw,
		'start_ts' => $start_ts,
		'end_ts' => $end_ts,
		'start_valid' => $start_valid,
		'end_valid' => $end_valid,
		'present' => ($start_raw !== '' || $end_raw !== ''),
		'is_open' => $is_open,
	);
}

function vms_ticket_inventory_forensics_load_ticket_object(int $product_id)
{
	$product_id = absint($product_id);
	if ($product_id <= 0 || !class_exists('Tribe__Tickets__Tickets') || !method_exists('Tribe__Tickets__Tickets', 'load_ticket_object')) {
		return null;
	}

	try {
		return Tribe__Tickets__Tickets::load_ticket_object($product_id);
	} catch (Throwable $throwable) {
		unset($throwable);
	}

	return null;
}

function vms_ticket_inventory_forensics_snapshot_event(int $tec_event_id): array
{
	$tec_event_id = absint($tec_event_id);
	$event_capacity = null;
	$event_available = null;
	$event_sold = null;

	if ($tec_event_id > 0 && function_exists('tribe_tickets_get_capacity')) {
		$event_capacity = tribe_tickets_get_capacity($tec_event_id);
	}
	if ($tec_event_id > 0 && function_exists('tribe_events_count_available_tickets')) {
		$event_available = tribe_events_count_available_tickets($tec_event_id);
	}
	if (
		$tec_event_id > 0
		&& class_exists('Tribe__Tickets__Tickets')
		&& method_exists('Tribe__Tickets__Tickets', 'get_event_attendees_count')
	) {
		try {
			$event_sold = Tribe__Tickets__Tickets::get_event_attendees_count($tec_event_id);
		} catch (Throwable $throwable) {
			unset($throwable);
			$event_sold = null;
		}
	}

	return array(
		'tec_event_id' => $tec_event_id,
		'global_stock_enabled_raw' => $tec_event_id > 0 ? (string) get_post_meta($tec_event_id, '_tribe_ticket_use_global_stock', true) : '',
		'global_stock_enabled' => $tec_event_id > 0 ? vms_ticket_inventory_forensics_meta_flag(get_post_meta($tec_event_id, '_tribe_ticket_use_global_stock', true)) : false,
		'global_stock_level_raw' => $tec_event_id > 0 ? get_post_meta($tec_event_id, '_tribe_ticket_global_stock_level', true) : '',
		'event_capacity' => $event_capacity,
		'event_available' => $event_available,
		'event_sold' => $event_sold,
	);
}

function vms_ticket_inventory_forensics_snapshot_product(int $product_id): array
{
	$scope = vms_ticket_inventory_forensics_resolve_scope($product_id);
	$wc_product = function_exists('wc_get_product') ? wc_get_product($product_id) : null;
	$ticket = vms_ticket_inventory_forensics_load_ticket_object($product_id);
	$post = $product_id > 0 ? get_post($product_id) : null;
	$post_status = $post instanceof WP_Post ? (string) $post->post_status : '';
	$product_role = sanitize_key((string) get_post_meta($product_id, vms_ticket_inventory_forensics_product_meta_key('product_role', '_vms_product_role'), true));
	$is_entitlement = ($product_role === 'entitlement' || $product_role === 'addon');
	$sale_state = vms_ticket_inventory_forensics_build_window_state(
		trim((string) get_post_meta($product_id, '_ticket_start_date', true)),
		trim((string) get_post_meta($product_id, '_ticket_end_date', true))
	);
	$event_snapshot = vms_ticket_inventory_forensics_snapshot_event(absint($scope['tec_event_id'] ?? 0));

	$stock_quantity = $wc_product && method_exists($wc_product, 'get_stock_quantity')
		? $wc_product->get_stock_quantity()
		: get_post_meta($product_id, '_stock', true);
	$stock_status = $wc_product && method_exists($wc_product, 'get_stock_status')
		? (string) $wc_product->get_stock_status()
		: (string) get_post_meta($product_id, '_stock_status', true);
	$managing_stock = $wc_product && method_exists($wc_product, 'managing_stock')
		? (bool) $wc_product->managing_stock()
		: vms_ticket_inventory_forensics_meta_flag(get_post_meta($product_id, '_manage_stock', true));
	$is_in_stock = $wc_product && method_exists($wc_product, 'is_in_stock')
		? (bool) $wc_product->is_in_stock()
		: null;
	$total_sales = max(0, (int) get_post_meta($product_id, 'total_sales', true));

	$tec_capacity = null;
	$tec_inventory = null;
	$tec_available = null;
	$tec_stock = null;
	if ($ticket) {
		try {
			$tec_capacity = method_exists($ticket, 'capacity') ? $ticket->capacity() : null;
			$tec_inventory = method_exists($ticket, 'inventory') ? $ticket->inventory() : null;
			$tec_available = method_exists($ticket, 'available') ? $ticket->available() : null;
			$tec_stock = method_exists($ticket, 'stock') ? $ticket->stock() : null;
		} catch (Throwable $throwable) {
			unset($throwable);
		}
	}

	$vms_capacity_key = $is_entitlement ? '_vms_ticketing_entitlement_capacity_v2' : '_vms_ticketing_capacity_v2';
	$vms_sold_key = $is_entitlement ? '_vms_ticketing_entitlement_sold_qty_v2' : '_vms_ticketing_sold_qty_v2';
	$vms_remaining_key = $is_entitlement ? '_vms_ticketing_entitlement_remaining_v2' : '_vms_ticketing_remaining_v2';
	$vms_reconciled_key = $is_entitlement ? '_vms_ticketing_entitlement_stock_reconciled_at_gmt' : '_vms_ticketing_stock_reconciled_at_gmt';
	$vms_error_key = $is_entitlement ? '_vms_ticketing_entitlement_stock_reconcile_error' : '_vms_ticketing_stock_reconcile_error';

	$vms_sold_qty = get_post_meta($product_id, $vms_sold_key, true);
	$resolved_sold = is_numeric($vms_sold_qty) ? (int) $vms_sold_qty : null;

	return array(
		'post_type' => 'product',
		'product_id' => absint($product_id),
		'plan_id' => absint($scope['plan_id'] ?? 0),
		'tec_event_id' => absint($scope['tec_event_id'] ?? 0),
		'event_title' => (string) ($scope['event_title'] ?? ''),
		'title' => $product_id > 0 ? (string) get_the_title($product_id) : '',
		'sku' => $product_id > 0 ? trim((string) get_post_meta($product_id, '_sku', true)) : '',
		'post_status' => $post_status,
		'role' => $product_role,
		'visibility_mode' => sanitize_key((string) get_post_meta($product_id, vms_ticket_inventory_forensics_product_meta_key('ticketing_visibility_mode', '_vms_ticketing_visibility_mode'), true)),
		'ticket_key' => sanitize_key((string) get_post_meta($product_id, vms_ticket_inventory_forensics_product_meta_key('ticketing_ticket_key', '_vms_ticketing_ticket_key'), true)),
		'entitlement_id' => sanitize_key((string) get_post_meta($product_id, vms_ticket_inventory_forensics_product_meta_key('ticketing_entitlement_id', '_vms_ticketing_entitlement_id'), true)),
		'linked_event_id' => absint(get_post_meta($product_id, '_tribe_wooticket_for_event', true)),
		'stock_quantity' => is_numeric($stock_quantity) ? (int) $stock_quantity : $stock_quantity,
		'manage_stock_raw' => (string) get_post_meta($product_id, '_manage_stock', true),
		'managing_stock' => $managing_stock,
		'backorders_raw' => (string) get_post_meta($product_id, '_backorders', true),
		'stock_status' => $stock_status,
		'is_in_stock' => $is_in_stock,
		'ticket_capacity_raw' => get_post_meta($product_id, '_tribe_ticket_capacity', true),
		'global_stock_mode' => sanitize_key((string) get_post_meta($product_id, '_global_stock_mode', true)),
		'global_stock_cap_raw' => get_post_meta($product_id, '_global_stock_cap', true),
		'sale_start_raw' => (string) $sale_state['start_raw'],
		'sale_end_raw' => (string) $sale_state['end_raw'],
		'sale_window_present' => !empty($sale_state['present']),
		'sale_window_open' => !empty($sale_state['is_open']),
		'sale_window_valid' => (!empty($sale_state['start_valid']) && !empty($sale_state['end_valid'])),
		'event_global_stock_enabled_raw' => (string) ($event_snapshot['global_stock_enabled_raw'] ?? ''),
		'event_global_stock_enabled' => !empty($event_snapshot['global_stock_enabled']),
		'event_global_stock_level_raw' => $event_snapshot['global_stock_level_raw'] ?? '',
		'event_capacity' => $event_snapshot['event_capacity'] ?? null,
		'event_available' => $event_snapshot['event_available'] ?? null,
		'event_sold' => $event_snapshot['event_sold'] ?? null,
		'tec_capacity' => $tec_capacity,
		'tec_inventory' => $tec_inventory,
		'tec_available' => $tec_available,
		'tec_stock' => $tec_stock,
		'total_sales' => $total_sales,
		'resolved_sold_qty' => $resolved_sold,
		'vms_capacity' => get_post_meta($product_id, $vms_capacity_key, true),
		'vms_sold_qty' => $vms_sold_qty,
		'vms_remaining' => get_post_meta($product_id, $vms_remaining_key, true),
		'vms_reconciled_at_gmt' => get_post_meta($product_id, $vms_reconciled_key, true),
		'vms_reconcile_error' => (string) get_post_meta($product_id, $vms_error_key, true),
	);
}

function vms_ticket_inventory_forensics_snapshot_object(int $object_id): array
{
	$object_id = absint($object_id);
	$scope = vms_ticket_inventory_forensics_resolve_scope($object_id);
	$post_type = (string) ($scope['post_type'] ?? '');

	if ($post_type === 'product') {
		return vms_ticket_inventory_forensics_snapshot_product($object_id);
	}

	if ($post_type === 'tribe_events') {
		$event_snapshot = vms_ticket_inventory_forensics_snapshot_event($object_id);
		return array_merge(
			array(
				'post_type' => 'tribe_events',
				'plan_id' => absint($scope['plan_id'] ?? 0),
				'tec_event_id' => absint($scope['tec_event_id'] ?? 0),
				'event_title' => (string) ($scope['event_title'] ?? ''),
				'product_id' => 0,
			),
			$event_snapshot
		);
	}

	return array();
}

function vms_ticket_inventory_forensics_snapshot_signature(array $snapshot): array
{
	return array(
		'post_type' => (string) ($snapshot['post_type'] ?? ''),
		'product_id' => absint($snapshot['product_id'] ?? 0),
		'plan_id' => absint($snapshot['plan_id'] ?? 0),
		'tec_event_id' => absint($snapshot['tec_event_id'] ?? 0),
		'stock_quantity' => $snapshot['stock_quantity'] ?? null,
		'manage_stock_raw' => (string) ($snapshot['manage_stock_raw'] ?? ''),
		'stock_status' => (string) ($snapshot['stock_status'] ?? ''),
		'ticket_capacity_raw' => $snapshot['ticket_capacity_raw'] ?? '',
		'global_stock_mode' => (string) ($snapshot['global_stock_mode'] ?? ''),
		'global_stock_cap_raw' => $snapshot['global_stock_cap_raw'] ?? '',
		'sale_start_raw' => (string) ($snapshot['sale_start_raw'] ?? ''),
		'sale_end_raw' => (string) ($snapshot['sale_end_raw'] ?? ''),
		'event_global_stock_enabled_raw' => (string) ($snapshot['event_global_stock_enabled_raw'] ?? ''),
		'event_global_stock_level_raw' => $snapshot['event_global_stock_level_raw'] ?? '',
		'event_capacity' => $snapshot['event_capacity'] ?? null,
		'event_available' => $snapshot['event_available'] ?? null,
		'event_sold' => $snapshot['event_sold'] ?? null,
		'tec_capacity' => $snapshot['tec_capacity'] ?? null,
		'tec_inventory' => $snapshot['tec_inventory'] ?? null,
		'tec_available' => $snapshot['tec_available'] ?? null,
		'tec_stock' => $snapshot['tec_stock'] ?? null,
		'vms_capacity' => $snapshot['vms_capacity'] ?? '',
		'vms_sold_qty' => $snapshot['vms_sold_qty'] ?? '',
		'vms_remaining' => $snapshot['vms_remaining'] ?? '',
		'vms_reconcile_error' => (string) ($snapshot['vms_reconcile_error'] ?? ''),
	);
}

function vms_ticket_inventory_forensics_snapshot_hash(array $snapshot): string
{
	$json = wp_json_encode(vms_ticket_inventory_forensics_snapshot_signature($snapshot));
	return sha1(is_string($json) ? $json : '');
}

function vms_ticket_inventory_forensics_snapshot_role_key(array $snapshot): string
{
	$role = sanitize_key((string) ($snapshot['role'] ?? ''));
	$visibility_mode = sanitize_key((string) ($snapshot['visibility_mode'] ?? ''));

	if ($role === 'entitlement' || $role === 'addon') {
		return 'add_on';
	}
	if ($visibility_mode === 'verified') {
		return 'qualified_ticket';
	}
	if ($role !== '' || !empty($snapshot['ticket_key'])) {
		return 'standard_ticket';
	}

	return 'unknown';
}

function vms_ticket_inventory_forensics_snapshot_role_label(string $role_key): string
{
	switch (sanitize_key($role_key)) {
		case 'standard_ticket':
			return __('Standard/public ticket', 'backstage-venue-manager');
		case 'qualified_ticket':
			return __('Verified/qualified ticket', 'backstage-venue-manager');
		case 'add_on':
			return __('Add-on', 'backstage-venue-manager');
		default:
			return __('Unknown', 'backstage-venue-manager');
	}
}

function vms_ticket_inventory_forensics_source_label(string $source): string
{
	$source = sanitize_key($source);
	switch ($source) {
		case 'authoritative_config':
			return __('Authoritative config', 'backstage-venue-manager');
		case 'authoritative_zero_capacity':
			return __('Authoritative zero-capacity branch', 'backstage-venue-manager');
		case 'sold_count_reconciliation':
			return __('Sold-count reconciliation', 'backstage-venue-manager');
		case 'ticket_sold_count_reconciliation':
			return __('Ticket sold-count reconciliation', 'backstage-venue-manager');
		case 'entitlement_scope_sold_count_reconciliation':
			return __('Add-on sold-count reconciliation', 'backstage-venue-manager');
		case 'legacy_plan_product_sold_count_reconciliation':
			return __('Legacy plan-product sold-count reconciliation', 'backstage-venue-manager');
		case 'manual_entitlement_stock_reconciliation':
			return __('Manual add-on stock reconciliation', 'backstage-venue-manager');
		case 'entitlement_capacity_seed':
			return __('Add-on capacity seed', 'backstage-venue-manager');
		case 'fallback_existing_state':
			return __('Fallback existing state', 'backstage-venue-manager');
		case 'ticket_existing_state_fallback':
			return __('Ticket existing-state fallback', 'backstage-venue-manager');
		case 'entitlement_existing_state_fallback':
			return __('Add-on existing-state fallback', 'backstage-venue-manager');
		case 'repair_branch_guardrail':
			return __('Repair branch guardrail', 'backstage-venue-manager');
		case 'legacy_normalization':
			return __('Legacy normalization', 'backstage-venue-manager');
		case 'repair_audit':
			return __('Repair audit', 'backstage-venue-manager');
		default:
			return __('Unknown', 'backstage-venue-manager');
	}
}

function vms_ticket_inventory_forensics_build_detail_payload(
	string $meta_key,
	array $before_snapshot,
	array $after_snapshot,
	array $context,
	string $result_status,
	string $source_function,
	string $trigger_source
): array {
	$role_key = vms_ticket_inventory_forensics_snapshot_role_key(!empty($after_snapshot) ? $after_snapshot : $before_snapshot);
	$after_error = trim((string) ($after_snapshot['vms_reconcile_error'] ?? ''));
	$before_stock = is_numeric($before_snapshot['stock_quantity'] ?? null) ? (int) $before_snapshot['stock_quantity'] : null;
	$after_stock = is_numeric($after_snapshot['stock_quantity'] ?? null) ? (int) $after_snapshot['stock_quantity'] : null;
	$before_status = sanitize_key((string) ($before_snapshot['stock_status'] ?? ''));
	$after_status = sanitize_key((string) ($after_snapshot['stock_status'] ?? ''));
	$before_manage_stock = !empty($before_snapshot['managing_stock']);
	$after_manage_stock = !empty($after_snapshot['managing_stock']);

	$derivation_source = sanitize_key((string) ($context['derivation_source'] ?? ''));
	$confidence = sanitize_key((string) ($context['confidence_level'] ?? ''));
	$reason = trim((string) ($context['reason_text'] ?? $context['summary_text'] ?? ''));
	$writer_branch = sanitize_key((string) ($context['writer_branch'] ?? ''));
	$result_health = sanitize_key((string) ($context['result_health'] ?? ''));

	if ($derivation_source === '' && $result_status === 'skipped') {
		$derivation_source = 'repair_audit';
		$confidence = 'authoritative';
		$reason = trim((string) ($context['summary_text'] ?? ''));
	} elseif ($derivation_source === '' && $after_error !== '') {
		$derivation_source = 'fallback_existing_state';
		$confidence = 'fallback';
		$reason = __('Sold quantity could not be derived cleanly, so the routine fell back to preserving or constraining the existing inventory state.', 'backstage-venue-manager');
	} elseif (
		$derivation_source === ''
		&&
		in_array($meta_key, array('_stock', '_stock_status', '_vms_ticketing_remaining_v2', '_vms_ticketing_entitlement_remaining_v2'), true)
		&& (
			strpos($source_function, 'apply_update_to_product') !== false
			|| strpos($source_function, 'upsert_entitlement_product') !== false
			|| strpos($source_function, 'restore_enabled_ticket_product') !== false
			|| in_array($trigger_source, array('preview_commit', 'rebuild'), true)
		)
	) {
		$derivation_source = 'sold_count_reconciliation';
		$confidence = 'authoritative';
		$reason = __('The write was derived from current ticket configuration plus reconciled sold counts.', 'backstage-venue-manager');
	} elseif (
		$derivation_source === ''
		&&
		in_array($meta_key, array('_tribe_ticket_capacity', '_global_stock_mode', '_global_stock_cap', '_manage_stock', '_ticket_start_date', '_ticket_end_date'), true)
		&& (strpos($source_function, 'apply_') !== false || strpos($source_function, 'upsert_') !== false || in_array($trigger_source, array('preview_commit', 'rebuild'), true))
	) {
		$derivation_source = 'authoritative_config';
		$confidence = 'authoritative';
		$reason = __('The write came directly from the authoritative VMS ticket configuration.', 'backstage-venue-manager');
	} elseif ($derivation_source === '' && (strpos($source_function, 'repair') !== false || strpos($source_function, 'legacy') !== false)) {
		$derivation_source = 'legacy_normalization';
		$confidence = 'inferred';
		$reason = __('The write was made while normalizing legacy or drifted ticket state during repair.', 'backstage-venue-manager');
	}

	if ($derivation_source === '') {
		$derivation_source = 'unknown';
	}
	if ($confidence === '') {
		$confidence = 'unknown';
	}
	if ($reason === '') {
		$reason = __('The write changed a live inventory-related field, but the exact derivation path still needs manual review.', 'backstage-venue-manager');
	}

	$expected_effect = sanitize_key((string) ($context['expected_effect'] ?? ''));
	if (($before_stock !== null && $after_stock !== null && $after_stock > $before_stock) || ($before_status === 'outofstock' && $after_status === 'instock')) {
		$expected_effect = 'reopen';
	} elseif (($before_stock !== null && $after_stock !== null && $after_stock < $before_stock) || ($before_status !== 'outofstock' && $after_status === 'outofstock')) {
		$expected_effect = 'close';
	} elseif ($before_stock === $after_stock || $before_status === $after_status) {
		$expected_effect = 'preserve';
	}

	return array(
		'product_role' => $role_key,
		'product_role_label' => vms_ticket_inventory_forensics_snapshot_role_label($role_key),
		'derivation_source' => $derivation_source,
		'derivation_source_label' => vms_ticket_inventory_forensics_source_label($derivation_source),
		'confidence_level' => vms_ticket_inventory_forensics_normalize_confidence($confidence),
		'confidence_label' => vms_ticket_inventory_forensics_confidence_label($confidence),
		'expected_effect' => vms_ticket_inventory_forensics_normalize_expected_effect($expected_effect),
		'expected_effect_label' => vms_ticket_inventory_forensics_expected_effect_label($expected_effect),
		'writer_branch' => $writer_branch,
		'result_health' => $result_health,
		'reason_text' => $reason,
		'old_value' => vms_ticket_inventory_forensics_display_quantity($before_snapshot['stock_quantity'] ?? $before_snapshot['ticket_capacity_raw'] ?? ''),
		'new_value' => vms_ticket_inventory_forensics_display_quantity($after_snapshot['stock_quantity'] ?? $after_snapshot['ticket_capacity_raw'] ?? ''),
		'old_stock_qty' => vms_ticket_inventory_forensics_display_quantity($before_stock),
		'new_stock_qty' => vms_ticket_inventory_forensics_display_quantity($after_stock),
		'old_stock_status' => $before_status !== '' ? $before_status : __('(empty)', 'backstage-venue-manager'),
		'new_stock_status' => $after_status !== '' ? $after_status : __('(empty)', 'backstage-venue-manager'),
		'old_manage_stock' => vms_ticket_inventory_forensics_bool_label($before_manage_stock),
		'new_manage_stock' => vms_ticket_inventory_forensics_bool_label($after_manage_stock),
	);
}

function vms_ticket_inventory_forensics_decode_json_field($value): array
{
	if (is_array($value)) {
		return $value;
	}
	if (!is_string($value) || trim($value) === '') {
		return array();
	}

	$decoded = json_decode($value, true);
	return is_array($decoded) ? $decoded : array();
}

function vms_ticket_inventory_forensics_pending_store(): array
{
	$store = $GLOBALS['vms_ticket_inventory_forensics_pending_meta'] ?? array();
	return is_array($store) ? $store : array();
}

function vms_ticket_inventory_forensics_enqueue_pending(string $operation, int $object_id, string $meta_key, array $row): void
{
	$store = vms_ticket_inventory_forensics_pending_store();
	$key = $operation . ':' . $object_id . ':' . $meta_key;
	if (!isset($store[$key]) || !is_array($store[$key])) {
		$store[$key] = array();
	}

	$store[$key][] = $row;
	$GLOBALS['vms_ticket_inventory_forensics_pending_meta'] = $store;
}

function vms_ticket_inventory_forensics_dequeue_pending(string $operation, int $object_id, string $meta_key): array
{
	$store = vms_ticket_inventory_forensics_pending_store();
	$key = $operation . ':' . $object_id . ':' . $meta_key;
	$row = array();

	if (!empty($store[$key]) && is_array($store[$key])) {
		$row = array_shift($store[$key]);
		if (empty($store[$key])) {
			unset($store[$key]);
		}
	}

	$GLOBALS['vms_ticket_inventory_forensics_pending_meta'] = $store;
	return is_array($row) ? $row : array();
}

function vms_ticket_inventory_forensics_capture_pre_meta_write($check, $object_id, $meta_key, $meta_value, $prev_value)
{
	unset($meta_value, $prev_value);

	$object_id = absint($object_id);
	$meta_key = (string) $meta_key;
	if (!vms_ticket_inventory_forensics_is_relevant_meta_write($object_id, $meta_key)) {
		return $check;
	}

	$started_at = microtime(true);
	$operation = current_filter() === 'add_post_metadata' ? 'add' : 'update';
	$context = vms_ticket_inventory_forensics_current_context();
	$source = vms_ticket_inventory_forensics_detect_source();
	$source_hook = (string) ($context['source_hook'] ?? $source['source_hook'] ?? '');
	$source_function = (string) ($context['source_function'] ?? $source['source_function'] ?? '');
	$guard = vms_ticket_inventory_forensics_guard_decision(current_filter(), $object_id, $meta_key, $context, $source_hook, $source_function);
	$trace_context = array_merge(
		$guard,
		array(
			'operation' => $operation,
		)
	);
	if (empty($guard['allowed'])) {
		if (is_admin()) {
			vms_ticket_inventory_forensics_trace('skipped', $trace_context, $started_at);
		}
		return $check;
	}

	vms_ticket_inventory_forensics_enqueue_pending(
		$operation,
		$object_id,
		$meta_key,
		array(
			'before_snapshot' => vms_ticket_inventory_forensics_snapshot_object($object_id),
			'context' => $context,
			'source_hook' => $source_hook,
			'source_function' => $source_function,
		)
	);
	if (is_admin()) {
		vms_ticket_inventory_forensics_trace('allowed', $trace_context, $started_at);
	}

	return $check;
}
add_filter('add_post_metadata', 'vms_ticket_inventory_forensics_capture_pre_meta_write', 10, 5);
add_filter('update_post_metadata', 'vms_ticket_inventory_forensics_capture_pre_meta_write', 10, 5);

function vms_ticket_inventory_forensics_capture_pre_meta_delete($check, $object_id, $meta_key, $meta_value, $delete_all)
{
	unset($meta_value, $delete_all);

	$object_id = absint($object_id);
	$meta_key = (string) $meta_key;
	if (!vms_ticket_inventory_forensics_is_relevant_meta_write($object_id, $meta_key)) {
		return $check;
	}

	$started_at = microtime(true);
	$context = vms_ticket_inventory_forensics_current_context();
	$source = vms_ticket_inventory_forensics_detect_source();
	$source_hook = (string) ($context['source_hook'] ?? $source['source_hook'] ?? '');
	$source_function = (string) ($context['source_function'] ?? $source['source_function'] ?? '');
	$guard = vms_ticket_inventory_forensics_guard_decision(current_filter(), $object_id, $meta_key, $context, $source_hook, $source_function);
	$trace_context = array_merge(
		$guard,
		array(
			'operation' => 'delete',
		)
	);
	if (empty($guard['allowed'])) {
		if (is_admin()) {
			vms_ticket_inventory_forensics_trace('skipped', $trace_context, $started_at);
		}
		return $check;
	}

	vms_ticket_inventory_forensics_enqueue_pending(
		'delete',
		$object_id,
		$meta_key,
		array(
			'before_snapshot' => vms_ticket_inventory_forensics_snapshot_object($object_id),
			'context' => $context,
			'source_hook' => $source_hook,
			'source_function' => $source_function,
		)
	);
	if (is_admin()) {
		vms_ticket_inventory_forensics_trace('allowed', $trace_context, $started_at);
	}

	return $check;
}
add_filter('delete_post_metadata', 'vms_ticket_inventory_forensics_capture_pre_meta_delete', 10, 5);

function vms_ticket_inventory_forensics_normalize_result_status(string $result_status): string
{
	$result_status = sanitize_key($result_status);
	return in_array($result_status, array('success', 'no_op', 'partial', 'skipped', 'failed'), true) ? $result_status : 'success';
}

function vms_ticket_inventory_forensics_change_type_for_meta(string $meta_key, array $before_snapshot, array $after_snapshot, string $result_status): string
{
	if ($result_status === 'no_op') {
		return 'inventory_write_no_effect';
	}

	switch ($meta_key) {
		case '_stock':
			$after_stock = $after_snapshot['stock_quantity'] ?? null;
			return (is_numeric($after_stock) && (int) $after_stock === 0) ? 'stock_zeroed' : 'stock_quantity_changed';
		case '_manage_stock':
			return !empty($after_snapshot['managing_stock']) ? 'manage_stock_enabled' : 'manage_stock_disabled';
		case '_stock_status':
		case '_backorders':
			return 'stock_status_changed';
		case '_tribe_ticket_capacity':
		case '_global_stock_mode':
		case '_global_stock_cap':
		case '_tribe_ticket_use_global_stock':
		case '_tribe_ticket_global_stock_level':
			return 'capacity_relinked';
		case '_ticket_start_date':
		case '_ticket_end_date':
			return 'sale_window_changed';
		default:
			return 'availability_meta_normalized';
	}
}

function vms_ticket_inventory_forensics_subject_label(array $scope, array $after_snapshot): string
{
	$product_id = absint($scope['product_id'] ?? 0);
	if ($product_id > 0) {
		$title = trim((string) ($after_snapshot['title'] ?? get_the_title($product_id)));
		/* translators: %d: product ID. */
		return $title !== '' ? $title : sprintf(__('Product #%d', 'backstage-venue-manager'), $product_id);
	}

	$event_id = absint($scope['tec_event_id'] ?? 0);
	$title = trim((string) ($scope['event_title'] ?? ''));
	if ($title !== '') {
		return $title;
	}

	/* translators: %d: event ID. */
	return $event_id > 0 ? sprintf(__('Event #%d', 'backstage-venue-manager'), $event_id) : __('Inventory object', 'backstage-venue-manager');
}

function vms_ticket_inventory_forensics_build_summary(string $change_type, string $meta_key, array $scope, array $before_snapshot, array $after_snapshot, string $result_status): string
{
	$subject = vms_ticket_inventory_forensics_subject_label($scope, $after_snapshot);
	if ($result_status === 'no_op') {
		/* translators: %s: human-readable value used in this message. */
		return sprintf(__('Inventory write had no effect for %s.', 'backstage-venue-manager'), $subject);
	}

	switch ($change_type) {
		case 'stock_zeroed':
			return sprintf(
				/* translators: 1: value 1 used in this message, 2: value 2 used in this message, 3: value 3 used in this message. */
				__('Stock changed %1$s -> %2$s for %3$s.', 'backstage-venue-manager'),
				vms_ticket_inventory_forensics_display_quantity($before_snapshot['stock_quantity'] ?? null),
				vms_ticket_inventory_forensics_display_quantity($after_snapshot['stock_quantity'] ?? null),
				$subject
			);
		case 'stock_quantity_changed':
			return sprintf(
				/* translators: 1: value 1 used in this message, 2: value 2 used in this message, 3: value 3 used in this message. */
				__('Stock quantity changed %1$s -> %2$s for %3$s.', 'backstage-venue-manager'),
				vms_ticket_inventory_forensics_display_quantity($before_snapshot['stock_quantity'] ?? null),
				vms_ticket_inventory_forensics_display_quantity($after_snapshot['stock_quantity'] ?? null),
				$subject
			);
		case 'manage_stock_enabled':
		case 'manage_stock_disabled':
			return sprintf(
				/* translators: 1: value 1 used in this message, 2: value 2 used in this message, 3: value 3 used in this message. */
				__('Manage stock changed %1$s -> %2$s for %3$s.', 'backstage-venue-manager'),
				vms_ticket_inventory_forensics_bool_label(!empty($before_snapshot['managing_stock'])),
				vms_ticket_inventory_forensics_bool_label(!empty($after_snapshot['managing_stock'])),
				$subject
			);
		case 'stock_status_changed':
			return sprintf(
				/* translators: 1: value 1 used in this message, 2: value 2 used in this message, 3: value 3 used in this message. */
				__('Stock status changed %1$s -> %2$s for %3$s.', 'backstage-venue-manager'),
				trim((string) ($before_snapshot['stock_status'] ?? '')) !== '' ? (string) ($before_snapshot['stock_status'] ?? '') : __('(empty)', 'backstage-venue-manager'),
				trim((string) ($after_snapshot['stock_status'] ?? '')) !== '' ? (string) ($after_snapshot['stock_status'] ?? '') : __('(empty)', 'backstage-venue-manager'),
				$subject
			);
		case 'capacity_relinked':
			$before_value = $before_snapshot['ticket_capacity_raw'] ?? $before_snapshot['event_global_stock_level_raw'] ?? '';
			$after_value = $after_snapshot['ticket_capacity_raw'] ?? $after_snapshot['event_global_stock_level_raw'] ?? '';
			return sprintf(
				/* translators: 1: value 1 used in this message, 2: value 2 used in this message, 3: value 3 used in this message, 4: value 4 used in this message. */
				__('Capacity-related field %1$s changed %2$s -> %3$s for %4$s.', 'backstage-venue-manager'),
				$meta_key,
				vms_ticket_inventory_forensics_display_quantity($before_value),
				vms_ticket_inventory_forensics_display_quantity($after_value),
				$subject
			);
		case 'sale_window_changed':
			/* translators: %s: human-readable value used in this message. */
			return sprintf(__('Sale window changed for %s.', 'backstage-venue-manager'), $subject);
		default:
			/* translators: 1: value 1 used in this message, 2: value 2 used in this message. */
			return sprintf(__('Availability-related field %1$s changed for %2$s.', 'backstage-venue-manager'), $meta_key, $subject);
	}
}

function vms_ticket_inventory_forensics_prune_logs(): void
{
	global $wpdb;
	$table = vms_ticket_inventory_forensics_table_name();
	$cutoff = gmdate('Y-m-d H:i:s', time() - (90 * DAY_IN_SECONDS));
	$wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE created_at_gmt < %s", $cutoff));
}

function vms_ticket_inventory_forensics_insert(array $row): int
{
	global $wpdb;

	if ((string) get_option(vms_ticket_inventory_forensics_schema_option_key(), '') !== vms_ticket_inventory_forensics_schema_target()) {
		return 0;
	}

	$table = vms_ticket_inventory_forensics_table_name();
	$ok = $wpdb->insert(
		$table,
		array(
			'created_at_gmt' => gmdate('Y-m-d H:i:s'),
			'plan_id' => absint($row['plan_id'] ?? 0),
			'tec_event_id' => absint($row['tec_event_id'] ?? 0),
			'event_title' => sanitize_text_field((string) ($row['event_title'] ?? '')),
			'product_id' => absint($row['product_id'] ?? 0),
			'user_id' => ($row['user_id'] ?? null) !== null ? absint($row['user_id']) : null,
			'trigger_source' => sanitize_key((string) ($row['trigger_source'] ?? '')),
			'source_hook' => sanitize_key((string) ($row['source_hook'] ?? '')),
			'source_function' => sanitize_text_field((string) ($row['source_function'] ?? '')),
			'mutation_key' => sanitize_key((string) ($row['mutation_key'] ?? '')),
			'product_role' => sanitize_key((string) ($row['product_role'] ?? '')),
			'change_type' => sanitize_key((string) ($row['change_type'] ?? '')),
			'result_status' => vms_ticket_inventory_forensics_normalize_result_status((string) ($row['result_status'] ?? 'success')),
			'derivation_source' => sanitize_key((string) ($row['derivation_source'] ?? '')),
			'confidence_level' => vms_ticket_inventory_forensics_normalize_confidence((string) ($row['confidence_level'] ?? 'unknown')),
			'expected_effect' => vms_ticket_inventory_forensics_normalize_expected_effect((string) ($row['expected_effect'] ?? 'unknown')),
			'reason_text' => sanitize_text_field((string) ($row['reason_text'] ?? '')),
			'summary_text' => sanitize_text_field((string) ($row['summary_text'] ?? '')),
			'before_json' => is_string($row['before_json'] ?? null) ? $row['before_json'] : wp_json_encode($row['before_json'] ?? array()),
			'after_json' => is_string($row['after_json'] ?? null) ? $row['after_json'] : wp_json_encode($row['after_json'] ?? array()),
			'details_json' => is_string($row['details_json'] ?? null) ? $row['details_json'] : wp_json_encode($row['details_json'] ?? array()),
		),
		array('%s', '%d', '%d', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
	);

	if ($ok === false) {
		return 0;
	}

	vms_ticket_inventory_forensics_prune_logs();
	return (int) $wpdb->insert_id;
}

function vms_ticket_inventory_forensics_record_post_meta_write(string $operation, int $object_id, string $meta_key): void
{
	$object_id = absint($object_id);
	$meta_key = (string) $meta_key;
	if (!vms_ticket_inventory_forensics_is_relevant_meta_write($object_id, $meta_key)) {
		return;
	}

	$started_at = microtime(true);
	$pending = vms_ticket_inventory_forensics_dequeue_pending($operation, $object_id, $meta_key);
	$context = is_array($pending['context'] ?? null) ? $pending['context'] : vms_ticket_inventory_forensics_current_context();
	$source_hook = sanitize_key((string) ($pending['source_hook'] ?? ''));
	$source_function = sanitize_text_field((string) ($pending['source_function'] ?? ''));

	if ($source_hook === '' || $source_function === '') {
		$source = vms_ticket_inventory_forensics_detect_source();
		if ($source_hook === '') {
			$source_hook = sanitize_key((string) ($source['source_hook'] ?? ''));
		}
		if ($source_function === '') {
			$source_function = sanitize_text_field((string) ($source['source_function'] ?? ''));
		}
	}
	$guard = vms_ticket_inventory_forensics_guard_decision(current_filter(), $object_id, $meta_key, $context, $source_hook, $source_function);
	$trace_context = array_merge(
		$guard,
		array(
			'operation' => $operation,
		)
	);
	if (empty($guard['allowed'])) {
		if (is_admin()) {
			vms_ticket_inventory_forensics_trace('skipped', $trace_context, $started_at);
		}
		return;
	}

	$before_snapshot = is_array($pending['before_snapshot'] ?? null) ? $pending['before_snapshot'] : vms_ticket_inventory_forensics_snapshot_object($object_id);
	$after_snapshot = vms_ticket_inventory_forensics_snapshot_object($object_id);
	$scope = vms_ticket_inventory_forensics_resolve_scope($object_id);

	$result_status = vms_ticket_inventory_forensics_snapshot_hash($before_snapshot) === vms_ticket_inventory_forensics_snapshot_hash($after_snapshot)
		? 'no_op'
		: vms_ticket_inventory_forensics_normalize_result_status((string) ($context['requested_result_status'] ?? 'success'));
	$change_type = vms_ticket_inventory_forensics_change_type_for_meta($meta_key, $before_snapshot, $after_snapshot, $result_status);
	$summary_text = vms_ticket_inventory_forensics_build_summary($change_type, $meta_key, $scope, $before_snapshot, $after_snapshot, $result_status);
	$trigger_source = vms_ticket_inventory_forensics_trigger_source($context, $source_hook, $source_function);
	$detail_payload = vms_ticket_inventory_forensics_build_detail_payload(
		$meta_key,
		$before_snapshot,
		$after_snapshot,
		$context,
		$result_status,
		$source_function,
		$trigger_source
	);

	vms_ticket_inventory_forensics_insert(
		array(
			'plan_id' => absint($scope['plan_id'] ?? 0),
			'tec_event_id' => absint($scope['tec_event_id'] ?? 0),
			'event_title' => (string) ($scope['event_title'] ?? ''),
			'product_id' => absint($scope['product_id'] ?? 0),
			'user_id' => get_current_user_id(),
			'trigger_source' => $trigger_source,
			'source_hook' => $source_hook,
			'source_function' => $source_function,
			'mutation_key' => $meta_key,
			'product_role' => (string) ($detail_payload['product_role'] ?? ''),
			'change_type' => $change_type,
			'result_status' => $result_status,
			'derivation_source' => (string) ($detail_payload['derivation_source'] ?? ''),
			'confidence_level' => (string) ($detail_payload['confidence_level'] ?? 'unknown'),
			'expected_effect' => (string) ($detail_payload['expected_effect'] ?? 'unknown'),
			'reason_text' => (string) ($detail_payload['reason_text'] ?? ''),
			'summary_text' => $summary_text,
			'before_json' => wp_json_encode($before_snapshot),
			'after_json' => wp_json_encode($after_snapshot),
			'details_json' => wp_json_encode($detail_payload),
		)
	);
	if (is_admin()) {
		vms_ticket_inventory_forensics_trace('finished', $trace_context, $started_at);
	}
}

function vms_ticket_inventory_forensics_log_direct_change(int $plan_id, array $args = array()): int
{
	$plan_id = absint($plan_id);
	if ($plan_id <= 0) {
		return 0;
	}

	$started_at = microtime(true);
	$product_id = absint($args['product_id'] ?? 0);
	$before_snapshot = is_array($args['before_snapshot'] ?? null)
		? $args['before_snapshot']
		: ($product_id > 0 ? vms_ticket_inventory_forensics_snapshot_product($product_id) : array());
	$after_snapshot = is_array($args['after_snapshot'] ?? null)
		? $args['after_snapshot']
		: ($product_id > 0 ? vms_ticket_inventory_forensics_snapshot_product($product_id) : $before_snapshot);
	$context = vms_ticket_inventory_forensics_current_context();
	$source = vms_ticket_inventory_forensics_detect_source();
	$source_hook = sanitize_key((string) ($args['source_hook'] ?? $context['source_hook'] ?? $source['source_hook'] ?? ''));
	$source_function = sanitize_text_field((string) ($args['source_function'] ?? $context['source_function'] ?? $source['source_function'] ?? ''));
	$mutation_key = sanitize_key((string) ($args['mutation_key'] ?? 'repair_state'));
	$guard = vms_ticket_inventory_forensics_guard_decision('ticket_inventory_forensics_direct_change', $product_id, $mutation_key, $context, $source_hook, $source_function);
	$trace_context = array_merge(
		$guard,
		array(
			'hook_name' => 'ticket_inventory_forensics_direct_change',
			'operation' => 'direct_change',
		)
	);
	if (empty($guard['allowed'])) {
		if (is_admin()) {
			vms_ticket_inventory_forensics_trace('skipped', $trace_context, $started_at);
		}
		return 0;
	}

	$trigger_source = vms_ticket_inventory_forensics_trigger_source($context, $source_hook, $source_function);
	$result_status = vms_ticket_inventory_forensics_normalize_result_status((string) ($args['result_status'] ?? 'success'));
	$change_type = sanitize_key((string) ($args['change_type'] ?? 'repair_no_effect'));
	$scope = vms_ticket_inventory_forensics_resolve_scope($product_id);
	$tec_event_id = absint($args['tec_event_id'] ?? $scope['tec_event_id'] ?? 0);
	$event_title = (string) ($args['event_title'] ?? $scope['event_title'] ?? get_the_title($tec_event_id ?: $plan_id));
	$detail_payload = array_merge(
		vms_ticket_inventory_forensics_build_detail_payload(
			$mutation_key,
			$before_snapshot,
			$after_snapshot,
			$context,
			$result_status,
			$source_function,
			$trigger_source
		),
		array(
			'product_role' => sanitize_key((string) ($args['product_role'] ?? '')) !== '' ? sanitize_key((string) ($args['product_role'] ?? '')) : vms_ticket_inventory_forensics_snapshot_role_key(!empty($after_snapshot) ? $after_snapshot : $before_snapshot),
			'derivation_source' => sanitize_key((string) ($args['derivation_source'] ?? '')) !== '' ? sanitize_key((string) ($args['derivation_source'] ?? '')) : sanitize_key((string) ($context['derivation_source'] ?? '')),
			'confidence_level' => sanitize_key((string) ($args['confidence_level'] ?? '')) !== '' ? sanitize_key((string) ($args['confidence_level'] ?? '')) : sanitize_key((string) ($context['confidence_level'] ?? '')),
			'expected_effect' => sanitize_key((string) ($args['expected_effect'] ?? '')) !== '' ? sanitize_key((string) ($args['expected_effect'] ?? '')) : sanitize_key((string) ($context['expected_effect'] ?? '')),
			'reason_text' => trim((string) ($args['reason_text'] ?? '')) !== '' ? trim((string) ($args['reason_text'] ?? '')) : trim((string) ($context['summary_text'] ?? '')),
			'writer_branch' => sanitize_key((string) ($args['writer_branch'] ?? '')) !== '' ? sanitize_key((string) ($args['writer_branch'] ?? '')) : sanitize_key((string) ($context['writer_branch'] ?? '')),
			'result_health' => sanitize_key((string) ($args['result_health'] ?? '')) !== '' ? sanitize_key((string) ($args['result_health'] ?? '')) : sanitize_key((string) ($context['result_health'] ?? '')),
			'skip_reason_code' => sanitize_key((string) ($args['skip_reason_code'] ?? '')) !== '' ? sanitize_key((string) ($args['skip_reason_code'] ?? '')) : sanitize_key((string) ($context['skip_reason_code'] ?? '')),
			'skip_expected' => isset($args['skip_expected']) ? (!empty($args['skip_expected']) ? 1 : 0) : (!empty($context['skip_expected']) ? 1 : 0),
			'skip_safety_driven' => isset($args['skip_safety_driven']) ? (!empty($args['skip_safety_driven']) ? 1 : 0) : (!empty($context['skip_safety_driven']) ? 1 : 0),
		)
	);

	if (empty($detail_payload['derivation_source'])) {
		$detail_payload['derivation_source'] = 'repair_audit';
	}
	$detail_payload['confidence_level'] = vms_ticket_inventory_forensics_normalize_confidence((string) ($detail_payload['confidence_level'] ?? 'unknown'));
	$detail_payload['expected_effect'] = vms_ticket_inventory_forensics_normalize_expected_effect((string) ($detail_payload['expected_effect'] ?? 'unknown'));
	$detail_payload['product_role_label'] = vms_ticket_inventory_forensics_snapshot_role_label((string) ($detail_payload['product_role'] ?? 'unknown'));
	$detail_payload['derivation_source_label'] = vms_ticket_inventory_forensics_source_label((string) ($detail_payload['derivation_source'] ?? 'unknown'));
	$detail_payload['confidence_label'] = vms_ticket_inventory_forensics_confidence_label((string) ($detail_payload['confidence_level'] ?? 'unknown'));
	$detail_payload['expected_effect_label'] = vms_ticket_inventory_forensics_expected_effect_label((string) ($detail_payload['expected_effect'] ?? 'unknown'));

	$summary_text = trim((string) ($args['summary_text'] ?? ''));
	if ($summary_text === '') {
		$summary_text = trim((string) ($detail_payload['reason_text'] ?? ''));
		if ($summary_text === '') {
			$summary_text = vms_ticket_inventory_forensics_change_type_label($change_type);
		}
	}

	$insert_id = vms_ticket_inventory_forensics_insert(
		array(
			'plan_id' => $plan_id,
			'tec_event_id' => $tec_event_id,
			'event_title' => $event_title,
			'product_id' => $product_id,
			'user_id' => get_current_user_id(),
			'trigger_source' => $trigger_source,
			'source_hook' => $source_hook,
			'source_function' => $source_function,
			'mutation_key' => $mutation_key,
			'product_role' => (string) ($detail_payload['product_role'] ?? ''),
			'change_type' => $change_type,
			'result_status' => $result_status,
			'derivation_source' => (string) ($detail_payload['derivation_source'] ?? ''),
			'confidence_level' => (string) ($detail_payload['confidence_level'] ?? 'unknown'),
			'expected_effect' => (string) ($detail_payload['expected_effect'] ?? 'unknown'),
			'reason_text' => (string) ($detail_payload['reason_text'] ?? ''),
			'summary_text' => $summary_text,
			'before_json' => wp_json_encode($before_snapshot),
			'after_json' => wp_json_encode($after_snapshot),
			'details_json' => wp_json_encode($detail_payload),
		)
	);
	if (is_admin()) {
		vms_ticket_inventory_forensics_trace('finished', $trace_context, $started_at);
	}

	return $insert_id;
}

function vms_ticket_inventory_forensics_after_add_post_meta($meta_id, $object_id, $meta_key, $meta_value): void
{
	unset($meta_id, $meta_value);
	vms_ticket_inventory_forensics_record_post_meta_write('add', absint($object_id), (string) $meta_key);
}
add_action('added_post_meta', 'vms_ticket_inventory_forensics_after_add_post_meta', 10, 4);

function vms_ticket_inventory_forensics_after_update_post_meta($meta_id, $object_id, $meta_key, $meta_value): void
{
	unset($meta_id, $meta_value);
	vms_ticket_inventory_forensics_record_post_meta_write('update', absint($object_id), (string) $meta_key);
}
add_action('updated_post_meta', 'vms_ticket_inventory_forensics_after_update_post_meta', 10, 4);

function vms_ticket_inventory_forensics_after_delete_post_meta($meta_ids, $object_id, $meta_key, $meta_value): void
{
	unset($meta_ids, $meta_value);
	vms_ticket_inventory_forensics_record_post_meta_write('delete', absint($object_id), (string) $meta_key);
}
add_action('deleted_post_meta', 'vms_ticket_inventory_forensics_after_delete_post_meta', 10, 4);

function vms_ticket_inventory_forensics_normalize_log_row($row): array
{
	if (!is_array($row)) {
		return array();
	}

	$created = trim((string) ($row['created_at_gmt'] ?? ''));
	$timestamp = $created !== '' ? strtotime($created . ' UTC') : 0;
	$before_snapshot = vms_ticket_inventory_forensics_decode_json_field($row['before_json'] ?? '');
	$after_snapshot = vms_ticket_inventory_forensics_decode_json_field($row['after_json'] ?? '');
	$details = vms_ticket_inventory_forensics_decode_json_field($row['details_json'] ?? '');
	$product_role = sanitize_key((string) ($row['product_role'] ?? $details['product_role'] ?? ''));
	$derivation_source = sanitize_key((string) ($row['derivation_source'] ?? $details['derivation_source'] ?? ''));
	$confidence_level = sanitize_key((string) ($row['confidence_level'] ?? $details['confidence_level'] ?? 'unknown'));
	$expected_effect = sanitize_key((string) ($row['expected_effect'] ?? $details['expected_effect'] ?? 'unknown'));
	$reason_text = trim((string) ($row['reason_text'] ?? $details['reason_text'] ?? ''));
	$writer_branch = sanitize_key((string) ($details['writer_branch'] ?? ''));
	$result_health = sanitize_key((string) ($details['result_health'] ?? ''));

	return array(
		'id' => absint($row['id'] ?? 0),
		'created_at_gmt' => $created,
		'timestamp_gmt' => $timestamp ? (int) $timestamp : 0,
		'plan_id' => absint($row['plan_id'] ?? 0),
		'tec_event_id' => absint($row['tec_event_id'] ?? 0),
		'event_title' => (string) ($row['event_title'] ?? ''),
		'product_id' => absint($row['product_id'] ?? 0),
		'user_id' => absint($row['user_id'] ?? 0),
		'trigger_source' => sanitize_key((string) ($row['trigger_source'] ?? '')),
		'source_hook' => sanitize_key((string) ($row['source_hook'] ?? '')),
		'source_function' => sanitize_text_field((string) ($row['source_function'] ?? '')),
		'mutation_key' => sanitize_key((string) ($row['mutation_key'] ?? '')),
		'product_role' => $product_role,
		'product_role_label' => vms_ticket_inventory_forensics_snapshot_role_label($product_role),
		'change_type' => sanitize_key((string) ($row['change_type'] ?? '')),
		'change_type_label' => vms_ticket_inventory_forensics_change_type_label((string) ($row['change_type'] ?? '')),
		'result_status' => vms_ticket_inventory_forensics_normalize_result_status((string) ($row['result_status'] ?? '')),
		'result_label' => vms_ticket_inventory_forensics_result_label((string) ($row['result_status'] ?? '')),
		'derivation_source' => $derivation_source,
		'derivation_source_label' => vms_ticket_inventory_forensics_source_label($derivation_source),
		'confidence_level' => vms_ticket_inventory_forensics_normalize_confidence($confidence_level),
		'confidence_label' => vms_ticket_inventory_forensics_confidence_label($confidence_level),
		'expected_effect' => vms_ticket_inventory_forensics_normalize_expected_effect($expected_effect),
		'expected_effect_label' => vms_ticket_inventory_forensics_expected_effect_label($expected_effect),
		'writer_branch' => $writer_branch,
		'result_health' => $result_health,
		'reason_text' => $reason_text,
		'before_snapshot' => $before_snapshot,
		'after_snapshot' => $after_snapshot,
		'details' => $details,
		'summary_text' => (string) ($row['summary_text'] ?? ''),
	);
}

function vms_ticket_inventory_forensics_recent_logs(int $plan_id, int $limit = 8, int $product_id = 0): array
{
	$plan_id = absint($plan_id);
	$product_id = absint($product_id);
	$limit = max(1, min(100, absint($limit)));

	if ($plan_id <= 0 || (string) get_option(vms_ticket_inventory_forensics_schema_option_key(), '') !== vms_ticket_inventory_forensics_schema_target()) {
		return array();
	}

	global $wpdb;
	$table = vms_ticket_inventory_forensics_table_name();
	if ($product_id > 0) {
		$rows = $wpdb->get_results(
			$wpdb->prepare("SELECT * FROM {$table} WHERE plan_id = %d AND product_id = %d ORDER BY id DESC LIMIT %d", $plan_id, $product_id, $limit),
			ARRAY_A
		);
	} else {
		$rows = $wpdb->get_results(
			$wpdb->prepare("SELECT * FROM {$table} WHERE plan_id = %d ORDER BY id DESC LIMIT %d", $plan_id, $limit),
			ARRAY_A
		);
	}

	if (!is_array($rows)) {
		return array();
	}

	return array_values(array_filter(array_map('vms_ticket_inventory_forensics_normalize_log_row', $rows)));
}

function vms_ticket_inventory_forensics_latest_log(int $plan_id, int $product_id = 0): array
{
	$logs = vms_ticket_inventory_forensics_recent_logs($plan_id, 1, $product_id);
	return !empty($logs[0]) && is_array($logs[0]) ? $logs[0] : array();
}

function vms_ticket_inventory_forensics_normalize_match_key(array $row): string
{
	$ticket_key = sanitize_key((string) ($row['ticket_key'] ?? ''));
	if ($ticket_key !== '') {
		return 'ticket:' . $ticket_key;
	}

	$entitlement_id = sanitize_key((string) ($row['entitlement_id'] ?? ''));
	if ($entitlement_id !== '') {
		return 'entitlement:' . $entitlement_id;
	}

	$title = strtolower(trim(wp_strip_all_tags((string) ($row['ticket_label'] ?? $row['title'] ?? ''))));
	$title = preg_replace('/[^a-z0-9]+/', ' ', $title);
	$title = is_string($title) ? trim($title) : '';
	if ($title === '') {
		return '';
	}

	return sanitize_key((string) ($row['role'] ?? 'ticket')) . ':' . sanitize_key($title);
}

function vms_ticket_inventory_forensics_numeric_value($value)
{
	return is_numeric($value) ? (int) $value : null;
}

function vms_ticket_inventory_forensics_row_role_key(array $ticket_snapshot, array $entitlement_snapshot, array $product_snapshot): string
{
	if (!empty($entitlement_snapshot)) {
		return 'add_on';
	}

	if (sanitize_key((string) ($ticket_snapshot['visibility_mode'] ?? '')) === 'verified') {
		return 'qualified_ticket';
	}

	$product_role = sanitize_key((string) ($product_snapshot['role'] ?? ''));
	if (in_array($product_role, array('entitlement', 'addon'), true)) {
		return 'add_on';
	}

	if (!empty($ticket_snapshot) || $product_role !== '' || !empty($product_snapshot['ticket_key'])) {
		return 'standard_ticket';
	}

	return 'unknown';
}

function vms_ticket_inventory_forensics_sold_source_label(string $source): string
{
	switch (sanitize_key($source)) {
		case 'vms_reconciled_meta':
			return __('VMS reconciled sold meta', 'backstage-venue-manager');
		case 'paid_order_scan':
			return __('Paid-order scan', 'backstage-venue-manager');
		case 'woo_total_sales_meta':
			return __('Woo total_sales meta only', 'backstage-venue-manager');
		default:
			return __('Not resolved', 'backstage-venue-manager');
	}
}

function vms_ticket_inventory_forensics_sellability_label(string $state): string
{
	switch (sanitize_key($state)) {
		case 'open':
			return __('Open in VMS config', 'backstage-venue-manager');
		case 'gated_open':
			return __('Qualified / gated open', 'backstage-venue-manager');
		case 'hidden_open':
			return __('Hidden add-on should remain available', 'backstage-venue-manager');
		case 'sold_out':
			return __('Correctly closed because sold out', 'backstage-venue-manager');
		case 'closed':
			return __('Closed by current intent', 'backstage-venue-manager');
		case 'missing':
			return __('Missing product state', 'backstage-venue-manager');
		default:
			return __('Needs review', 'backstage-venue-manager');
	}
}

function vms_ticket_inventory_forensics_agreement_label(string $status): string
{
	switch (sanitize_key($status)) {
		case 'correct_match':
			return __('Woo and TEC agree correctly', 'backstage-venue-manager');
		case 'sold_out_match':
			return __('Sold-out state is consistent', 'backstage-venue-manager');
		case 'match_but_wrong':
			return __('Woo and TEC agree, but both disagree with VMS intent', 'backstage-venue-manager');
		case 'diverged':
			return __('Woo and TEC disagree', 'backstage-venue-manager');
		default:
			return __('Needs review', 'backstage-venue-manager');
	}
}

function vms_ticket_inventory_forensics_verification_label(string $result): string
{
	switch (sanitize_key($result)) {
		case 'verified':
			return __('Woo and TEC both match VMS intent', 'backstage-venue-manager');
		case 'woo_verified':
			return __('Woo matches VMS intent', 'backstage-venue-manager');
		case 'sold_out_healthy':
			return __('Woo matches sold-aware VMS intent', 'backstage-venue-manager');
		case 'woo_mismatch':
			return __('Woo mismatch requires repair', 'backstage-venue-manager');
		case 'tec_followup':
			return __('Woo matches VMS intent, but TEC still disagrees', 'backstage-venue-manager');
		case 'woo_recorruption':
			return __('Woo re-corruption detected', 'backstage-venue-manager');
		default:
			return __('Manual review required', 'backstage-venue-manager');
	}
}

function vms_ticket_inventory_forensics_sellability_is_open(string $state)
{
	switch (sanitize_key($state)) {
		case 'open':
		case 'gated_open':
		case 'hidden_open':
			return true;
		case 'sold_out':
		case 'closed':
		case 'missing':
			return false;
		default:
			return null;
	}
}

function vms_ticket_inventory_forensics_reset_runtime_caches(): void
{
	$GLOBALS['vms_ticket_inventory_forensics_sold_context_cache'] = array();
}

function vms_ticket_inventory_forensics_resolve_sold_context(int $plan_id, array $product_snapshot, array $ticket_snapshot, array $entitlement_snapshot, string $role): array
{
	$cache = $GLOBALS['vms_ticket_inventory_forensics_sold_context_cache'] ?? array();
	if (!is_array($cache)) {
		$cache = array();
	}

	$product_id = absint($product_snapshot['product_id'] ?? 0);
	$entitlement_id = sanitize_key((string) ($entitlement_snapshot['entitlement_id'] ?? $product_snapshot['entitlement_id'] ?? ''));
	$sku = trim((string) ($product_snapshot['sku'] ?? ''));
	$cache_key = implode(':', array($plan_id, $product_id, $role, $entitlement_id, $sku));
	if (isset($cache[$cache_key]) && is_array($cache[$cache_key])) {
		return $cache[$cache_key];
	}

	$vms_sold_qty = vms_ticket_inventory_forensics_numeric_value($product_snapshot['vms_sold_qty'] ?? null);
	$meta_total_sales = max(0, (int) ($product_snapshot['total_sales'] ?? 0));
	$order_scan_sold_qty = null;
	$helper_ok = false;
	$helper_message = '';
	$ignored_total_sales = false;

	if ($product_id > 0) {
		$scan_result = array('ok' => false, 'message' => 'sold_qty_helper_unavailable');
		if ($role === 'add_on' && $plan_id > 0 && $entitlement_id !== '' && function_exists('vms_ticketing_v2_calc_sold_qty_for_entitlement_scope')) {
			$scan_result = vms_ticketing_v2_calc_sold_qty_for_entitlement_scope($plan_id, $entitlement_id, $sku, $product_id);
		} elseif (function_exists('vms_ticketing_v2_calc_sold_qty_for_product')) {
			$scan_result = vms_ticketing_v2_calc_sold_qty_for_product($product_id);
		}

		if (!empty($scan_result['ok'])) {
			$helper_ok = true;
			$order_scan_sold_qty = max(0, absint($scan_result['sold_qty'] ?? 0));
			$ignored_total_sales = !empty($scan_result['ignored_total_sales']) || !empty($scan_result['ignored_total_sales_count']);
			$helper_message = sanitize_key((string) ($scan_result['message'] ?? 'ok'));
		} else {
			$helper_message = sanitize_key((string) ($scan_result['message'] ?? 'sold_qty_helper_unavailable'));
		}
	}

	$resolved_sold_qty = $vms_sold_qty !== null ? $vms_sold_qty : $order_scan_sold_qty;
	$sold_source = 'unresolved';
	if ($vms_sold_qty !== null) {
		$sold_source = 'vms_reconciled_meta';
	} elseif ($order_scan_sold_qty !== null) {
		$sold_source = 'paid_order_scan';
	} elseif ($meta_total_sales > 0) {
		$sold_source = 'woo_total_sales_meta';
	}

	$cache[$cache_key] = array(
		'resolved_sold_qty' => $resolved_sold_qty,
		'vms_sold_qty' => $vms_sold_qty,
		'order_scan_sold_qty' => $order_scan_sold_qty,
		'woo_total_sales' => $meta_total_sales,
		'sold_source' => $sold_source,
		'sold_source_label' => vms_ticket_inventory_forensics_sold_source_label($sold_source),
		'helper_ok' => $helper_ok ? 1 : 0,
		'helper_message' => $helper_message,
		'ignored_total_sales' => $ignored_total_sales ? 1 : 0,
	);
	$GLOBALS['vms_ticket_inventory_forensics_sold_context_cache'] = $cache;

	return $cache[$cache_key];
}

function vms_ticket_inventory_forensics_build_intended_state(array $ticket_snapshot, array $entitlement_snapshot, array $product_snapshot, array $sold_context, string $role): array
{
	$remaining_hint = null;
	$capacity_hint = null;
	$state = 'unknown';
	$reason = __('The current VMS intent could not be derived cleanly for this row.', 'backstage-venue-manager');

	if ($role === 'add_on') {
		$capacity_hint = vms_ticket_inventory_forensics_numeric_value($entitlement_snapshot['capacity'] ?? null);
		if ($capacity_hint === null) {
			$capacity_hint = vms_ticket_inventory_forensics_numeric_value($product_snapshot['vms_capacity'] ?? null);
		}

		$remaining_hint = vms_ticket_inventory_forensics_numeric_value($product_snapshot['vms_remaining'] ?? null);
		if ($remaining_hint === null && $capacity_hint !== null && is_numeric($sold_context['resolved_sold_qty'] ?? null)) {
			$remaining_hint = max(0, $capacity_hint - max(0, (int) $sold_context['resolved_sold_qty']));
		}

		if ($capacity_hint !== null && $capacity_hint <= 0) {
			$state = 'closed';
			$reason = __('The authoritative add-on capacity is 0, so VMS intends this add-on to remain closed.', 'backstage-venue-manager');
		} elseif ($remaining_hint !== null && $remaining_hint <= 0) {
			$state = 'closed';
			$reason = __('The add-on has no remaining entitlement inventory after sold-count reconciliation.', 'backstage-venue-manager');
		} elseif ($capacity_hint !== null || $remaining_hint !== null) {
			$state = 'hidden_open';
			$reason = __('The authoritative entitlement config still expects this add-on to remain sellable.', 'backstage-venue-manager');
		}
	} elseif (!empty($ticket_snapshot)) {
		$visibility_mode = sanitize_key((string) ($ticket_snapshot['visibility_mode'] ?? 'public'));
		$config_is_open = !empty($ticket_snapshot['sales_state']['config_is_open']);
		$capacity_hint = vms_ticket_inventory_forensics_numeric_value($ticket_snapshot['inventory_total'] ?? null);
		if ($capacity_hint === null) {
			$capacity_hint = vms_ticket_inventory_forensics_numeric_value($product_snapshot['vms_capacity'] ?? null);
		}
		if ($capacity_hint === null) {
			$capacity_hint = vms_ticket_inventory_forensics_numeric_value($product_snapshot['ticket_capacity_raw'] ?? null);
		}

		$remaining_hint = vms_ticket_inventory_forensics_numeric_value($product_snapshot['vms_remaining'] ?? null);
		if ($remaining_hint === null && $capacity_hint !== null && is_numeric($sold_context['resolved_sold_qty'] ?? null)) {
			$remaining_hint = max(0, $capacity_hint - max(0, (int) $sold_context['resolved_sold_qty']));
		}

		if (!$config_is_open) {
			$state = 'closed';
			$reason = __('The current VMS sale-window rules intentionally close this ticket.', 'backstage-venue-manager');
		} elseif ($remaining_hint !== null && $remaining_hint <= 0) {
			$state = 'closed';
			$reason = __('The current VMS capacity-minus-sold calculation leaves no remaining ticket inventory.', 'backstage-venue-manager');
		} elseif ($visibility_mode === 'verified') {
			$state = 'gated_open';
			$reason = __('VMS intends this verified ticket to remain available for eligible buyers.', 'backstage-venue-manager');
		} else {
			$state = 'open';
			$reason = __('VMS intends this ticket to remain sellable on the public/live path.', 'backstage-venue-manager');
		}
	}

	return array(
		'state' => $state,
		'label' => vms_ticket_inventory_forensics_sellability_label($state),
		'is_open' => vms_ticket_inventory_forensics_sellability_is_open($state),
		'reason' => $reason,
		'capacity_hint' => $capacity_hint,
		'remaining_hint' => $remaining_hint,
	);
}

function vms_ticket_inventory_forensics_normalize_add_on_sold_out_state(
	array $intended_state,
	array $sold_context,
	array $woo_state,
	array $tec_state,
	string $role
): array {
	if (sanitize_key($role) !== 'add_on') {
		return $intended_state;
	}

	$capacity_hint = vms_ticket_inventory_forensics_numeric_value($intended_state['capacity_hint'] ?? null);
	if ($capacity_hint === null || $capacity_hint <= 0) {
		return $intended_state;
	}

	$remaining_hint = vms_ticket_inventory_forensics_numeric_value($intended_state['remaining_hint'] ?? null);
	$resolved_sold_qty = vms_ticket_inventory_forensics_numeric_value($sold_context['resolved_sold_qty'] ?? null);
	$woo_total_sales = max(0, absint($sold_context['woo_total_sales'] ?? 0));
	$woo_is_open = $woo_state['is_open'] ?? null;
	$tec_is_open = $tec_state['is_open'] ?? null;
	$closed_now = ($woo_is_open === false) && ($tec_is_open === false || $tec_is_open === null);

	$sold_out_by_remaining = ($remaining_hint !== null && $remaining_hint <= 0);
	$sold_out_by_reconciled_qty = ($resolved_sold_qty !== null && $resolved_sold_qty >= $capacity_hint);
	$sold_out_by_total_sales_fallback = $closed_now && $woo_total_sales >= $capacity_hint;

	if (!$sold_out_by_remaining && !$sold_out_by_reconciled_qty && !$sold_out_by_total_sales_fallback) {
		return $intended_state;
	}

	$reason = __('This add-on is correctly closed because sold-out evidence matches the current Woo/TEC state.', 'backstage-venue-manager');
	if ($sold_out_by_remaining) {
		$reason = __('This add-on has no remaining capacity after sold-count reconciliation, so the closed sellability state is healthy.', 'backstage-venue-manager');
	} elseif ($sold_out_by_reconciled_qty) {
		/* translators: 1: sold quantity, 2: capacity */
		$reason = sprintf(__('This add-on sold %1$d of %2$d capacity, so the closed sellability state is healthy.', 'backstage-venue-manager'), $resolved_sold_qty, $capacity_hint);
	} elseif ($sold_out_by_total_sales_fallback) {
		/* translators: 1: total sales count, 2: capacity */
		$reason = sprintf(__('Woo total_sales reports %1$d sale(s) against %2$d capacity, and Woo/TEC both resolve the add-on as closed, so this sold-out state is treated as healthy.', 'backstage-venue-manager'), $woo_total_sales, $capacity_hint);
	}

	$intended_state['state'] = 'sold_out';
	$intended_state['label'] = vms_ticket_inventory_forensics_sellability_label('sold_out');
	$intended_state['is_open'] = false;
	$intended_state['reason'] = $reason;
	$intended_state['sold_out_evidence'] = $sold_out_by_remaining
		? 'remaining_hint'
		: ($sold_out_by_reconciled_qty ? 'resolved_sold_qty' : 'woo_total_sales_fallback');

	return $intended_state;
}

function vms_ticket_inventory_forensics_build_woo_state(array $product_snapshot): array
{
	$post_type = (string) ($product_snapshot['post_type'] ?? '');
	$post_status = (string) ($product_snapshot['post_status'] ?? '');
	$stock_qty = vms_ticket_inventory_forensics_numeric_value($product_snapshot['stock_quantity'] ?? null);
	$stock_status = sanitize_key((string) ($product_snapshot['stock_status'] ?? ''));
	$managing_stock = !empty($product_snapshot['managing_stock']);
	$is_in_stock = array_key_exists('is_in_stock', $product_snapshot) ? $product_snapshot['is_in_stock'] : null;

	if ($post_type !== 'product' || $post_status === 'trash') {
		return array(
			'state' => 'missing',
			'label' => vms_ticket_inventory_forensics_sellability_label('missing'),
			'is_open' => false,
			'reason' => __('Woo does not currently expose a live product object for this row.', 'backstage-venue-manager'),
		);
	}

	$closed = false;
	if ($stock_status === 'outofstock') {
		$closed = true;
	} elseif ($managing_stock && $stock_qty !== null && $stock_qty <= 0) {
		$closed = true;
	} elseif ($is_in_stock === false) {
		$closed = true;
	}

	return array(
		'state' => $closed ? 'closed' : 'open',
		'label' => $closed ? __('Woo currently closes sellability', 'backstage-venue-manager') : __('Woo currently shows sellable inventory', 'backstage-venue-manager'),
		'is_open' => !$closed,
		'reason' => $closed
			? __('Woo stock / stock-status fields currently close this product.', 'backstage-venue-manager')
			: __('Woo stock / stock-status fields currently keep this product sellable.', 'backstage-venue-manager'),
	);
}

function vms_ticket_inventory_forensics_build_tec_state(array $product_snapshot): array
{
	$available_qty = vms_ticket_inventory_forensics_numeric_value($product_snapshot['tec_available'] ?? null);
	$inventory_qty = vms_ticket_inventory_forensics_numeric_value($product_snapshot['tec_inventory'] ?? null);
	$stock_qty = vms_ticket_inventory_forensics_numeric_value($product_snapshot['tec_stock'] ?? null);

	if ($available_qty !== null) {
		return array(
			'state' => ($available_qty > 0) ? 'open' : 'closed',
			'label' => ($available_qty > 0) ? __('TEC currently shows available inventory', 'backstage-venue-manager') : __('TEC currently resolves unavailable inventory', 'backstage-venue-manager'),
			'is_open' => ($available_qty > 0),
		);
	}

	if ($inventory_qty !== null) {
		return array(
			'state' => ($inventory_qty > 0) ? 'open' : 'closed',
			'label' => ($inventory_qty > 0) ? __('TEC inventory remains open', 'backstage-venue-manager') : __('TEC inventory resolves closed', 'backstage-venue-manager'),
			'is_open' => ($inventory_qty > 0),
		);
	}

	if ($stock_qty !== null) {
		return array(
			'state' => ($stock_qty > 0) ? 'open' : 'closed',
			'label' => ($stock_qty > 0) ? __('TEC stock remains open', 'backstage-venue-manager') : __('TEC stock resolves closed', 'backstage-venue-manager'),
			'is_open' => ($stock_qty > 0),
		);
	}

	return array(
		'state' => 'unknown',
		'label' => __('TEC did not expose a separate availability value', 'backstage-venue-manager'),
		'is_open' => null,
	);
}

function vms_ticket_inventory_forensics_build_verification(array $intended_state, array $woo_state, array $tec_state): array
{
	$intended_open = $intended_state['is_open'] ?? null;
	$woo_open = $woo_state['is_open'] ?? null;
	$tec_open = $tec_state['is_open'] ?? null;
	$intended_state_key = sanitize_key((string) ($intended_state['state'] ?? 'unknown'));

	$agreement_status = 'needs_review';
	if ($woo_open !== null && $tec_open !== null) {
		if ($woo_open === $tec_open && $intended_open !== null && $woo_open === $intended_open) {
			$agreement_status = 'correct_match';
		} elseif ($woo_open === $tec_open) {
			$agreement_status = 'match_but_wrong';
		} else {
			$agreement_status = 'diverged';
		}
	}

	$result = 'manual_review';
	$reason = __('The current Woo / TEC state still needs manual review.', 'backstage-venue-manager');
	if (
		$intended_state_key === 'sold_out'
		&& $woo_open === false
		&& ($tec_open === false || $tec_open === null)
	) {
		$agreement_status = 'sold_out_match';
		$result = 'sold_out_healthy';
		$reason = ($tec_open === false)
			? __('This add-on is correctly closed because sold out, and both Woo and TEC reflect that sold-aware state.', 'backstage-venue-manager')
			: __('This add-on is correctly closed because sold out, and Woo reflects that sold-aware state.', 'backstage-venue-manager');
	} elseif ($intended_open !== null && $woo_open !== null && $woo_open !== $intended_open) {
		$result = 'woo_mismatch';
		$reason = __('VMS intent and Woo inventory disagree. Repair should target Woo first.', 'backstage-venue-manager');
	} elseif ($intended_open !== null && $woo_open === $intended_open && $tec_open !== null && $tec_open !== $intended_open) {
		$result = 'tec_followup';
		$reason = __('Woo now matches VMS intent, but TEC availability still disagrees.', 'backstage-venue-manager');
	} elseif ($intended_open !== null && $woo_open === $intended_open && $tec_open === $intended_open) {
		$result = 'verified';
		$reason = __('Woo and TEC both match the current VMS intent for this row.', 'backstage-venue-manager');
	} elseif ($intended_open !== null && $woo_open === $intended_open && $tec_open === null) {
		$result = 'woo_verified';
		$reason = __('Woo matches VMS intent. TEC did not expose a separate availability signal for this row.', 'backstage-venue-manager');
	}

	return array(
		'agreement_status' => $agreement_status,
		'agreement_label' => vms_ticket_inventory_forensics_agreement_label($agreement_status),
		'verification_result' => $result,
		'verification_result_label' => vms_ticket_inventory_forensics_verification_label($result),
		'verification_reason' => $reason,
	);
}

function vms_ticket_inventory_forensics_build_ticket_rows(int $plan_id, array $args = array()): array
{
	$plan_id = absint($plan_id);
	if ($plan_id <= 0) {
		return array();
	}

	$context = is_array($args['context'] ?? null) ? $args['context'] : array();
	$ticket_snapshots = array_values(array_filter((array) ($args['ticket_snapshots'] ?? array()), 'is_array'));
	$entitlement_snapshots = array_values(array_filter((array) ($args['entitlement_snapshots'] ?? array()), 'is_array'));
	$recent_logs = array_values(array_filter((array) ($args['recent_logs'] ?? array()), 'is_array'));
	if (empty($recent_logs)) {
		$recent_logs = vms_ticket_inventory_forensics_recent_logs($plan_id, 24);
	}

	$latest_by_product = array();
	foreach ($recent_logs as $log) {
		$product_id = absint($log['product_id'] ?? 0);
		if ($product_id > 0 && !isset($latest_by_product[$product_id])) {
			$latest_by_product[$product_id] = $log;
		}
	}

	$ticket_by_product = array();
	foreach ($ticket_snapshots as $ticket_snapshot) {
		$product_id = absint($ticket_snapshot['mapped_product_id'] ?? 0);
		if ($product_id > 0) {
			$ticket_by_product[$product_id] = $ticket_snapshot;
		}
	}

	$entitlement_by_product = array();
	foreach ($entitlement_snapshots as $entitlement_snapshot) {
		$product_id = absint($entitlement_snapshot['mapped_product_id'] ?? 0);
		if ($product_id > 0) {
			$entitlement_by_product[$product_id] = $entitlement_snapshot;
		}
	}

	$product_ids = array_values(array_unique(array_filter(array_map(
		'absint',
		array_merge(
			(array) ($context['attached_product_ids'] ?? array()),
			(array) ($context['mapped_ticket_product_ids'] ?? array()),
			(array) ($context['mapped_entitlement_product_ids'] ?? array()),
			array_keys($ticket_by_product),
			array_keys($entitlement_by_product)
		)
	))));
	sort($product_ids, SORT_NUMERIC);

	$rows = array();
	foreach ($product_ids as $product_id) {
		$ticket_snapshot = is_array($ticket_by_product[$product_id] ?? null) ? $ticket_by_product[$product_id] : array();
		$entitlement_snapshot = is_array($entitlement_by_product[$product_id] ?? null) ? $entitlement_by_product[$product_id] : array();
		$product_snapshot = vms_ticket_inventory_forensics_snapshot_product($product_id);
		$latest_log = is_array($latest_by_product[$product_id] ?? null) ? $latest_by_product[$product_id] : array();

		$mapping_state = 'attached_untracked';
		if (!empty($ticket_snapshot)) {
			$mapping_state = sanitize_key((string) ($ticket_snapshot['mapping_state'] ?? 'ok'));
		} elseif (!empty($entitlement_snapshot)) {
			$mapping_state = sanitize_key((string) ($entitlement_snapshot['mapping_state'] ?? 'ok'));
		} elseif (!empty($product_snapshot['ticket_key']) || !empty($product_snapshot['entitlement_id']) || !empty($product_snapshot['role'])) {
			$mapping_state = 'legacy_attached';
		}

		$role = vms_ticket_inventory_forensics_row_role_key($ticket_snapshot, $entitlement_snapshot, $product_snapshot);
		$ticket_label = trim((string) ($ticket_snapshot['title'] ?? $entitlement_snapshot['label'] ?? $product_snapshot['title'] ?? ''));
		$stock_qty = $product_snapshot['stock_quantity'] ?? null;
		$available_qty = $product_snapshot['tec_available'] ?? null;
		$sold_context = vms_ticket_inventory_forensics_resolve_sold_context($plan_id, $product_snapshot, $ticket_snapshot, $entitlement_snapshot, $role);
		$intended_state = vms_ticket_inventory_forensics_build_intended_state($ticket_snapshot, $entitlement_snapshot, $product_snapshot, $sold_context, $role);
		$woo_state = vms_ticket_inventory_forensics_build_woo_state($product_snapshot);
		$tec_state = vms_ticket_inventory_forensics_build_tec_state($product_snapshot);
		$intended_state = vms_ticket_inventory_forensics_normalize_add_on_sold_out_state(
			$intended_state,
			$sold_context,
			$woo_state,
			$tec_state,
			$role
		);
		$verification = vms_ticket_inventory_forensics_build_verification($intended_state, $woo_state, $tec_state);
		$customer_facing = !empty($ticket_snapshot['customer_facing']);
		$intended_is_open = $intended_state['is_open'] ?? null;
		$expected_open = !empty($intended_is_open);
		$expected_remaining_hint = vms_ticket_inventory_forensics_numeric_value($intended_state['remaining_hint'] ?? null);
		$event_capacity = $product_snapshot['event_capacity'] ?? null;
		$event_sold = $product_snapshot['event_sold'] ?? null;
		$event_remaining = (is_numeric($event_capacity) && is_numeric($event_sold) && (int) $event_capacity >= 0)
			? max(0, (int) $event_capacity - max(0, (int) $event_sold))
			: null;
		$woo_is_open = $woo_state['is_open'] ?? null;
		$tec_is_open = $tec_state['is_open'] ?? null;
		$zero_available_conflict = (
			!empty($customer_facing)
			&& $expected_open
			&& $tec_is_open === false
			&& (($expected_remaining_hint !== null && $expected_remaining_hint > 0) || (is_numeric($event_remaining) && $event_remaining > 0))
		);

		$rows[] = array(
			'product_id' => $product_id,
			/* translators: %d: product ID. */
			'ticket_label' => $ticket_label !== '' ? $ticket_label : sprintf(__('Product #%d', 'backstage-venue-manager'), $product_id),
			'title' => (string) ($product_snapshot['title'] ?? ''),
			'sku' => (string) ($product_snapshot['sku'] ?? ''),
			'role' => $role,
			'role_label' => vms_ticket_inventory_forensics_role_label($role),
			'ticket_key' => sanitize_key((string) ($ticket_snapshot['ticket_key'] ?? $product_snapshot['ticket_key'] ?? '')),
			'entitlement_id' => sanitize_key((string) ($entitlement_snapshot['entitlement_id'] ?? $product_snapshot['entitlement_id'] ?? '')),
			'mapping_state' => $mapping_state,
			'mapping_label' => vms_ticket_inventory_forensics_mapping_label($mapping_state),
			'status' => (string) ($product_snapshot['post_status'] ?? ''),
			'stock_qty' => $stock_qty,
			'manage_stock' => !empty($product_snapshot['managing_stock']),
			'manage_stock_label' => vms_ticket_inventory_forensics_bool_label(!empty($product_snapshot['managing_stock'])),
			'stock_status' => (string) ($product_snapshot['stock_status'] ?? ''),
			'ticket_capacity' => $product_snapshot['ticket_capacity_raw'] ?? '',
			'ticket_capacity_resolved' => $product_snapshot['tec_capacity'] ?? null,
			'sold_qty' => $sold_context['resolved_sold_qty'] ?? null,
			'order_scan_sold_qty' => $sold_context['order_scan_sold_qty'] ?? null,
			'woo_total_sales' => $sold_context['woo_total_sales'] ?? 0,
			'sold_source' => (string) ($sold_context['sold_source'] ?? 'unresolved'),
			'sold_source_label' => (string) ($sold_context['sold_source_label'] ?? __('Not resolved', 'backstage-venue-manager')),
			'ignored_total_sales' => !empty($sold_context['ignored_total_sales']) ? 1 : 0,
			'available_qty' => $available_qty,
			'inventory_qty' => $product_snapshot['tec_inventory'] ?? null,
			'tec_stock_qty' => $product_snapshot['tec_stock'] ?? null,
			'customer_facing' => $customer_facing ? 1 : 0,
			'expected_open' => $expected_open ? 1 : 0,
			'live_window_open' => !empty($product_snapshot['sale_window_open']) ? 1 : 0,
			'standard_public' => (!empty($ticket_snapshot) && empty($entitlement_snapshot) && $customer_facing) ? 1 : 0,
			'qualified_ticket' => ($role === 'qualified_ticket') ? 1 : 0,
			'expected_remaining_hint' => $expected_remaining_hint,
			'vms_intended_sellability' => (string) ($intended_state['state'] ?? 'unknown'),
			'vms_intended_label' => (string) ($intended_state['label'] ?? __('Needs review', 'backstage-venue-manager')),
			'vms_intended_reason' => (string) ($intended_state['reason'] ?? ''),
			'woo_sellability' => (string) ($woo_state['state'] ?? 'unknown'),
			'woo_sellability_label' => (string) ($woo_state['label'] ?? __('Needs review', 'backstage-venue-manager')),
			'woo_sellability_reason' => (string) ($woo_state['reason'] ?? ''),
			'tec_sellability' => (string) ($tec_state['state'] ?? 'unknown'),
			'tec_sellability_label' => (string) ($tec_state['label'] ?? __('Needs review', 'backstage-venue-manager')),
			'agreement_status' => (string) ($verification['agreement_status'] ?? 'needs_review'),
			'agreement_label' => (string) ($verification['agreement_label'] ?? __('Needs review', 'backstage-venue-manager')),
			'verification_result' => (string) ($verification['verification_result'] ?? 'manual_review'),
			'verification_result_label' => (string) ($verification['verification_result_label'] ?? __('Manual review required', 'backstage-venue-manager')),
			'verification_reason' => (string) ($verification['verification_reason'] ?? ''),
			'global_stock_mode' => (string) ($product_snapshot['global_stock_mode'] ?? ''),
			'global_stock_cap' => $product_snapshot['global_stock_cap_raw'] ?? '',
			'event_global_stock_enabled' => !empty($product_snapshot['event_global_stock_enabled']) ? 1 : 0,
			'event_global_stock_level' => $product_snapshot['event_global_stock_level_raw'] ?? '',
			'event_capacity' => $event_capacity,
			'event_available' => $product_snapshot['event_available'] ?? null,
			'event_sold' => $event_sold,
			'event_remaining' => $event_remaining,
			'sale_start_raw' => (string) ($product_snapshot['sale_start_raw'] ?? ''),
			'sale_end_raw' => (string) ($product_snapshot['sale_end_raw'] ?? ''),
			'vms_capacity' => $product_snapshot['vms_capacity'] ?? '',
			'vms_sold_qty' => $product_snapshot['vms_sold_qty'] ?? '',
			'vms_remaining' => $product_snapshot['vms_remaining'] ?? '',
			'vms_reconcile_error' => (string) ($product_snapshot['vms_reconcile_error'] ?? ''),
			'last_changed_gmt' => absint($latest_log['timestamp_gmt'] ?? 0),
			'last_change_source' => trim((string) ($latest_log['source_function'] ?? $latest_log['source_hook'] ?? '')),
			'last_change_source_label' => trim((string) ($latest_log['derivation_source_label'] ?? '')),
			'last_change_type_label' => (string) ($latest_log['change_type_label'] ?? ''),
			'last_write_reason' => trim((string) ($latest_log['reason_text'] ?? '')),
			'last_change_confidence' => trim((string) ($latest_log['confidence_label'] ?? '')),
			'last_change_expected_effect' => trim((string) ($latest_log['expected_effect_label'] ?? '')),
			'last_change_trigger' => trim((string) ($latest_log['trigger_source'] ?? '')),
			'mismatch_flags' => array(
				'unexpected_zero_stock' => (
					$expected_open
					&& !empty($product_snapshot['managing_stock'])
					&& is_numeric($stock_qty)
					&& (int) $stock_qty <= 0
					&& (($expected_remaining_hint !== null && $expected_remaining_hint > 0) || (is_numeric($event_remaining) && $event_remaining > 0))
				),
				'unexpected_outofstock' => (
					$expected_open
					&& (string) ($product_snapshot['stock_status'] ?? '') === 'outofstock'
					&& (($expected_remaining_hint !== null && $expected_remaining_hint > 0) || (is_numeric($event_remaining) && $event_remaining > 0))
				),
				'sale_window_conflict' => (!empty($customer_facing) && $expected_open && !empty($product_snapshot['sale_window_present']) && empty($product_snapshot['sale_window_open'])),
				'zero_available' => ($tec_is_open === false),
				'zero_available_conflict' => $zero_available_conflict,
				'woo_mismatch' => ($intended_is_open !== null) && ($woo_is_open !== null) && ($woo_is_open !== $intended_is_open),
				'tec_mismatch' => ($intended_is_open !== null) && ($tec_is_open !== null) && ($tec_is_open !== $intended_is_open),
			),
		);
	}

	return $rows;
}

function vms_ticket_inventory_forensics_build_diff_fields(array $broken_row, array $healthy_row): array
{
	$fields = array(
		'vms_intended_label' => __('VMS Intended Sellability', 'backstage-venue-manager'),
		'woo_sellability_label' => __('Woo Sellability', 'backstage-venue-manager'),
		'tec_sellability_label' => __('TEC Sellability', 'backstage-venue-manager'),
		'stock_qty' => __('Stock Qty', 'backstage-venue-manager'),
		'manage_stock_label' => __('Manage Stock', 'backstage-venue-manager'),
		'stock_status' => __('Stock Status', 'backstage-venue-manager'),
		'ticket_capacity' => __('Ticket Capacity', 'backstage-venue-manager'),
		'sold_qty' => __('Sold', 'backstage-venue-manager'),
		'available_qty' => __('Available (TEC)', 'backstage-venue-manager'),
		'global_stock_mode' => __('Stock Mode', 'backstage-venue-manager'),
		'event_global_stock_level' => __('Event Shared Stock', 'backstage-venue-manager'),
		'sale_start_raw' => __('Sale Start', 'backstage-venue-manager'),
		'sale_end_raw' => __('Sale End', 'backstage-venue-manager'),
		'vms_remaining' => __('VMS Remaining', 'backstage-venue-manager'),
		'mapping_label' => __('Mapping Status', 'backstage-venue-manager'),
		'last_change_source' => __('Last Change Source', 'backstage-venue-manager'),
	);

	$diffs = array();
	foreach ($fields as $field => $label) {
		$broken_value = $broken_row[$field] ?? null;
		$healthy_value = $healthy_row[$field] ?? null;
		$broken_display = is_bool($broken_value)
			? vms_ticket_inventory_forensics_bool_label($broken_value)
			: vms_ticket_inventory_forensics_display_quantity($broken_value);
		$healthy_display = is_bool($healthy_value)
			? vms_ticket_inventory_forensics_bool_label($healthy_value)
			: vms_ticket_inventory_forensics_display_quantity($healthy_value);

		if ($broken_display === $healthy_display) {
			continue;
		}

		$diffs[] = array(
			'label' => $label,
			'broken' => $broken_display,
			'healthy' => $healthy_display,
		);
	}

	return $diffs;
}

function vms_ticket_inventory_forensics_find_healthy_baseline(int $plan_id): array
{
	$plan_id = absint($plan_id);
	if ($plan_id <= 0 || !function_exists('vms_ticket_integrity_get_sorted_events')) {
		return array();
	}

	foreach ((array) vms_ticket_integrity_get_sorted_events() as $event) {
		if (!is_array($event)) {
			continue;
		}

		$candidate_plan_id = absint($event['plan_id'] ?? 0);
		if ($candidate_plan_id <= 0 || $candidate_plan_id === $plan_id) {
			continue;
		}
		if (sanitize_key((string) ($event['status'] ?? '')) !== 'green') {
			continue;
		}
		if (sanitize_key((string) ($event['origin_classification'] ?? '')) !== 'vms_native') {
			continue;
		}

		return $event;
	}

	return array();
}

function vms_ticket_inventory_forensics_build_comparison(int $plan_id, array $args = array()): array
{
	$plan_id = absint($plan_id);
	if ($plan_id <= 0) {
		return array();
	}

	$broken_rows = array_values(array_filter((array) ($args['ticket_rows'] ?? array()), static function ($row): bool {
		return is_array($row) && !empty($row['standard_public']);
	}));
	if (empty($broken_rows)) {
		return array();
	}

	$healthy_event = is_array($args['healthy_event'] ?? null) ? $args['healthy_event'] : vms_ticket_inventory_forensics_find_healthy_baseline($plan_id);
	$healthy_plan_id = absint($healthy_event['plan_id'] ?? 0);
	if ($healthy_plan_id <= 0) {
		return array();
	}

	$healthy_context = function_exists('vms_ticket_integrity_build_context') ? vms_ticket_integrity_build_context($healthy_plan_id) : array();
	$product_cache = array();
	$healthy_ticket_snapshots = function_exists('vms_ticket_integrity_build_ticket_snapshots')
		? vms_ticket_integrity_build_ticket_snapshots($healthy_context, $product_cache)
		: array();
	$healthy_entitlement_snapshots = function_exists('vms_ticket_integrity_build_entitlement_snapshots')
		? vms_ticket_integrity_build_entitlement_snapshots($healthy_context, $product_cache)
		: array();
	$healthy_rows = vms_ticket_inventory_forensics_build_ticket_rows(
		$healthy_plan_id,
		array(
			'context' => $healthy_context,
			'ticket_snapshots' => $healthy_ticket_snapshots,
			'entitlement_snapshots' => $healthy_entitlement_snapshots,
			'recent_logs' => vms_ticket_inventory_forensics_recent_logs($healthy_plan_id, 12),
		)
	);

	$healthy_by_key = array();
	foreach ($healthy_rows as $row) {
		if (!is_array($row)) {
			continue;
		}

		$key = vms_ticket_inventory_forensics_normalize_match_key($row);
		if ($key !== '' && !isset($healthy_by_key[$key])) {
			$healthy_by_key[$key] = $row;
		}
	}

	$comparison_rows = array();
	foreach ($broken_rows as $row) {
		$key = vms_ticket_inventory_forensics_normalize_match_key($row);
		if ($key === '' || empty($healthy_by_key[$key])) {
			continue;
		}

		$healthy_row = $healthy_by_key[$key];
		$differences = vms_ticket_inventory_forensics_build_diff_fields($row, $healthy_row);
		if (empty($differences)) {
			continue;
		}

		$comparison_rows[] = array(
			'label' => (string) ($row['ticket_label'] ?? __('Ticket', 'backstage-venue-manager')),
			'differences' => $differences,
		);
	}

	return array(
		'healthy_plan_id' => $healthy_plan_id,
		'healthy_event_title' => (string) ($healthy_event['event_title'] ?? get_the_title($healthy_plan_id)),
		'healthy_origin_label' => function_exists('vms_ticket_mutation_audit_origin_label')
			? vms_ticket_mutation_audit_origin_label((string) ($healthy_event['origin_classification'] ?? 'vms_native'))
			: __('VMS-native', 'backstage-venue-manager'),
		'rows' => $comparison_rows,
	);
}

function vms_ticket_inventory_forensics_build_cluster_note(bool $zero_available_mismatch): array
{
	if (!$zero_available_mismatch || !function_exists('vms_ticket_integrity_get_sorted_events')) {
		return array('message' => '');
	}

	$imported = 0;
	$native = 0;
	$mixed = 0;

	foreach ((array) vms_ticket_integrity_get_sorted_events() as $event) {
		if (!is_array($event)) {
			continue;
		}

		$diag = is_array($event['inventory_diagnostics'] ?? null) ? $event['inventory_diagnostics'] : array();
		if (empty($diag['zero_available_mismatch'])) {
			continue;
		}

		$origin = sanitize_key((string) ($event['origin_classification'] ?? ''));
		if ($origin === 'imported_legacy') {
			$imported++;
		} elseif ($origin === 'vms_native') {
			$native++;
		} else {
			$mixed++;
		}
	}

	$message = '';
	if ($imported > 0 && $native === 0 && $mixed === 0) {
		$message = __('All currently flagged zero-availability events are imported legacy.', 'backstage-venue-manager');
	} elseif ($imported > 0 && $native === 0) {
		$message = __('Current zero-availability drift is clustering on imported legacy / mixed-history events.', 'backstage-venue-manager');
	} elseif ($native > 0 && $imported === 0) {
		$message = __('Current zero-availability drift is not limited to imported legacy events.', 'backstage-venue-manager');
	} elseif ($imported > 0 && $native > 0) {
		$message = __('Zero-availability drift is currently affecting both imported legacy and VMS-native events.', 'backstage-venue-manager');
	}

	return array(
		'imported_legacy' => $imported,
		'vms_native' => $native,
		'mixed' => $mixed,
		'message' => $message,
	);
}

function vms_ticket_inventory_forensics_detect_repeated_drift(int $plan_id, array $recent_logs, bool $zero_available_mismatch): array
{
	$plan_id = absint($plan_id);
	if ($plan_id <= 0 || !$zero_available_mismatch || !function_exists('vms_ticket_integrity_get_repair_report')) {
		return array('flagged' => false);
	}

	$last_repair = vms_ticket_integrity_get_repair_report($plan_id);
	if (empty($last_repair) || empty($last_repair['saved_at_gmt'])) {
		return array('flagged' => false);
	}

	$repair_ts = absint($last_repair['saved_at_gmt'] ?? 0);
	if ($repair_ts <= 0) {
		return array('flagged' => false);
	}

	$post_repair_logs = array_values(array_filter($recent_logs, static function ($row) use ($repair_ts): bool {
		return is_array($row) && absint($row['timestamp_gmt'] ?? 0) > $repair_ts;
	}));

	if (empty($post_repair_logs)) {
		return array('flagged' => false);
	}

	return array(
		'flagged' => true,
		'message' => __('This event still shows zero-availability drift after a prior rebuild, and later inventory writes were recorded afterward.', 'backstage-venue-manager'),
		'latest_log' => $post_repair_logs[0],
	);
}

function vms_ticket_inventory_forensics_classify_cause(array $ticket_rows, array $event_snapshot, array $issues): array
{
	$public_rows = array_values(array_filter($ticket_rows, static function ($row): bool {
		return is_array($row) && !empty($row['standard_public']) && !empty($row['expected_open']);
	}));
	$addon_rows = array_values(array_filter($ticket_rows, static function ($row): bool {
		return is_array($row) && sanitize_key((string) ($row['role'] ?? '')) === 'add_on';
	}));

	$event_capacity = $event_snapshot['event_capacity'] ?? null;
	$event_sold = $event_snapshot['event_sold'] ?? null;
	$event_remaining = (is_numeric($event_capacity) && is_numeric($event_sold) && (int) $event_capacity >= 0)
		? max(0, (int) $event_capacity - max(0, (int) $event_sold))
		: null;

	$per_ticket = false;
	$shared = false;
	$sale_window = false;
	$role_divergence = false;
	$reasons = array();

	foreach ($public_rows as $row) {
		$flags = is_array($row['mismatch_flags'] ?? null) ? $row['mismatch_flags'] : array();
		if (!empty($flags['unexpected_zero_stock']) || !empty($flags['unexpected_outofstock']) || !empty($flags['zero_available_conflict'])) {
			$per_ticket = true;
			/* translators: %s: human-readable value used in this message. */
			$reasons[] = sprintf(__('Ticket "%s" is reporting sold-out stock even though remaining capacity still exists.', 'backstage-venue-manager'), (string) ($row['ticket_label'] ?? __('Ticket', 'backstage-venue-manager')));
		}

		if (
			in_array((string) ($row['global_stock_mode'] ?? ''), array('global', 'capped'), true)
			&& is_numeric($event_remaining)
			&& $event_remaining > 0
			&& (
				(!empty($row['event_global_stock_enabled']) && is_numeric($row['event_global_stock_level']) && (int) $row['event_global_stock_level'] <= 0)
				|| (is_numeric($row['available_qty']) && (int) $row['available_qty'] <= 0)
			)
		) {
			$shared = true;
			$reasons[] = __('Shared/event capacity fields look out of sync with the remaining event capacity.', 'backstage-venue-manager');
		}

		if (!empty($flags['sale_window_conflict'])) {
			$sale_window = true;
			/* translators: %s: ticket label that appears open in config but closed by live sale dates. */
			$reasons[] = sprintf(__('Ticket "%s" looks open in config but closed by live sale dates.', 'backstage-venue-manager'), (string) ($row['ticket_label'] ?? __('Ticket', 'backstage-venue-manager')));
		}
	}

	$public_zero_rows = array_values(array_filter($public_rows, static function ($row): bool {
		return !empty($row['mismatch_flags']['zero_available_conflict']);
	}));
	$addon_positive_rows = array_values(array_filter($addon_rows, static function ($row): bool {
		return (is_numeric($row['available_qty'] ?? null) && (int) $row['available_qty'] > 0)
			|| (is_numeric($row['stock_qty'] ?? null) && (int) $row['stock_qty'] > 0);
	}));
	if (!empty($public_zero_rows) && !empty($addon_positive_rows)) {
		$role_divergence = true;
		$reasons[] = __('Admission tickets are resolving to zero while one or more add-ons still look available.', 'backstage-venue-manager');
	}

	$mapping_issue_present = false;
	foreach ($issues as $issue) {
		if (!is_array($issue)) {
			continue;
		}

		$category = sanitize_key((string) ($issue['category'] ?? ''));
		if (in_array($category, array('mapping', 'structure'), true)) {
			$mapping_issue_present = true;
			break;
		}
	}

	$flag_count = ($per_ticket ? 1 : 0) + ($shared ? 1 : 0) + ($sale_window ? 1 : 0) + ($mapping_issue_present ? 1 : 0) + ($role_divergence ? 1 : 0);
	$cause = 'healthy';
	if ($flag_count > 1) {
		$cause = 'mixed_mode_corruption';
	} elseif ($per_ticket) {
		$cause = 'per_ticket_stock_corruption';
	} elseif ($shared) {
		$cause = 'shared_capacity_linkage_corruption';
	} elseif ($sale_window) {
		$cause = 'sale_window_false_closure';
	} elseif (!empty($public_rows)) {
		$cause = 'unknown_inventory_drift';
	}

	return array(
		'cause' => $cause,
		'label' => vms_ticket_inventory_forensics_cause_label($cause),
		'role_divergence' => $role_divergence ? 1 : 0,
		'reasons' => array_values(array_unique(array_filter(array_map('strval', $reasons)))),
	);
}

function vms_ticket_inventory_forensics_recommended_action(string $cause, bool $zero_available_mismatch, string $origin_classification): string
{
	switch (sanitize_key($cause)) {
		case 'per_ticket_stock_corruption':
			return __('Review the most recent stock and stock-status writes for the affected tickets before running another rebuild.', 'backstage-venue-manager');
		case 'shared_capacity_linkage_corruption':
			return __('Compare the event shared-capacity fields and ticket stock modes with a healthy event; the capacity pool looks disconnected.', 'backstage-venue-manager');
		case 'sale_window_false_closure':
			return __('Review the live sale-start and sale-end fields. Operator config reads open, but product dates are closing availability.', 'backstage-venue-manager');
		case 'mixed_mode_corruption':
			return __('Inspect both the mutation diagnostics and the inventory logs together. This event shows more than one drift pattern at the same time.', 'backstage-venue-manager');
	}

	if ($zero_available_mismatch && $origin_classification === 'imported_legacy') {
		return __('Compare this imported legacy event against a healthy VMS-native event before changing live inventory values.', 'backstage-venue-manager');
	}

	if ($zero_available_mismatch) {
		return __('Compare the broken ticket rows against the healthy baseline to see which stock or capacity fields differ.', 'backstage-venue-manager');
	}

	return __('No immediate inventory repair signal is obvious. Keep this event under watch and review the latest inventory mutation if the state changes again.', 'backstage-venue-manager');
}

function vms_ticket_inventory_forensics_snapshot_is_open(array $snapshot): bool
{
	$stock_status = sanitize_key((string) ($snapshot['stock_status'] ?? ''));
	$stock_qty = vms_ticket_inventory_forensics_numeric_value($snapshot['stock_quantity'] ?? null);
	$manage_stock = !empty($snapshot['managing_stock']) || vms_ticket_inventory_forensics_meta_flag($snapshot['manage_stock_raw'] ?? '');
	$is_in_stock = array_key_exists('is_in_stock', $snapshot) ? $snapshot['is_in_stock'] : null;

	if ($stock_status === 'outofstock') {
		return false;
	}
	if ($manage_stock && $stock_qty !== null && $stock_qty <= 0) {
		return false;
	}
	if ($is_in_stock === false) {
		return false;
	}

	return true;
}

function vms_ticket_inventory_forensics_repair_entry_is_open(array $entry): bool
{
	$stock_status = sanitize_key((string) ($entry['final_stock_status'] ?? ''));
	$stock_qty = vms_ticket_inventory_forensics_numeric_value($entry['final_stock_qty'] ?? null);
	$manage_stock = !empty($entry['final_manage_stock']);

	if ($stock_status === 'outofstock') {
		return false;
	}
	if ($manage_stock && $stock_qty !== null && $stock_qty <= 0) {
		return false;
	}

	return true;
}

function vms_ticket_inventory_forensics_detect_woo_recorruption(array $ticket_rows, array $recent_logs, array $repair_report): array
{
	$repair_ts = absint($repair_report['saved_at_gmt'] ?? 0);
	if ($repair_ts <= 0 || empty($ticket_rows) || empty($recent_logs)) {
		return array(
			'flagged' => false,
			'rows' => $ticket_rows,
		);
	}

	$repair_entries_by_product = array();
	foreach ((array) ($repair_report['entries'] ?? array()) as $entry) {
		if (!is_array($entry)) {
			continue;
		}

		$product_id = absint($entry['product_id'] ?? 0);
		if ($product_id > 0 && !isset($repair_entries_by_product[$product_id])) {
			$repair_entries_by_product[$product_id] = $entry;
		}
	}

	$post_repair_log_by_product = array();
	foreach ($recent_logs as $log) {
		if (!is_array($log) || absint($log['timestamp_gmt'] ?? 0) <= $repair_ts) {
			continue;
		}

		$product_id = absint($log['product_id'] ?? 0);
		if ($product_id > 0 && !isset($post_repair_log_by_product[$product_id])) {
			$post_repair_log_by_product[$product_id] = $log;
		}
	}

	$flagged_rows = array();
	$latest_bad_write = array();
	foreach ($ticket_rows as $index => $row) {
		if (!is_array($row)) {
			continue;
		}

		$product_id = absint($row['product_id'] ?? 0);
		if ($product_id <= 0 || empty($repair_entries_by_product[$product_id]) || empty($post_repair_log_by_product[$product_id])) {
			continue;
		}
		if (sanitize_key((string) ($row['verification_result'] ?? '')) !== 'woo_mismatch') {
			continue;
		}

		$repair_entry = $repair_entries_by_product[$product_id];
		if (!vms_ticket_inventory_forensics_repair_entry_is_open($repair_entry)) {
			continue;
		}

		$bad_log = $post_repair_log_by_product[$product_id];
		$after_snapshot = is_array($bad_log['after_snapshot'] ?? null) ? $bad_log['after_snapshot'] : array();
		if (!empty($after_snapshot) && vms_ticket_inventory_forensics_snapshot_is_open($after_snapshot)) {
			continue;
		}

		$source_text = trim((string) ($bad_log['source_function'] ?? $bad_log['source_hook'] ?? ''));
		$ticket_rows[$index]['verification_result'] = 'woo_recorruption';
		$ticket_rows[$index]['verification_result_label'] = vms_ticket_inventory_forensics_verification_label('woo_recorruption');
		$ticket_rows[$index]['verification_reason'] = $source_text !== ''
			/* translators: %s: human-readable value used in this message. */
			? sprintf(__('Repair previously left Woo sellable, but a later write from %s closed it again.', 'backstage-venue-manager'), $source_text)
			: __('Repair previously left Woo sellable, but a later inventory write closed it again.', 'backstage-venue-manager');
		$ticket_rows[$index]['woo_recorruption'] = 1;
		$ticket_rows[$index]['last_good_woo_state'] = array(
			'stock_qty' => $repair_entry['final_stock_qty'] ?? null,
			'stock_status' => (string) ($repair_entry['final_stock_status'] ?? ''),
			'manage_stock' => array_key_exists('final_manage_stock', $repair_entry) ? absint($repair_entry['final_manage_stock']) : null,
		);
		$ticket_rows[$index]['latest_bad_write'] = $bad_log;

		$flagged_rows[] = $ticket_rows[$index];
		if (empty($latest_bad_write) || absint($bad_log['timestamp_gmt'] ?? 0) > absint($latest_bad_write['timestamp_gmt'] ?? 0)) {
			$latest_bad_write = array_merge($bad_log, array(
				'ticket_label' => (string) ($row['ticket_label'] ?? __('Ticket', 'backstage-venue-manager')),
				'product_id' => $product_id,
			));
		}
	}

	if (empty($flagged_rows)) {
		return array(
			'flagged' => false,
			'rows' => $ticket_rows,
		);
	}

	$source_text = trim((string) ($latest_bad_write['source_function'] ?? $latest_bad_write['source_hook'] ?? ''));
	$message = $source_text !== ''
		/* translators: %s: human-readable value used in this message. */
		? sprintf(__('Woo was repaired into a sellable state, but a later write from %s closed it again.', 'backstage-venue-manager'), $source_text)
		: __('Woo was repaired into a sellable state, but a later inventory write closed it again.', 'backstage-venue-manager');

	return array(
		'flagged' => true,
		'rows' => $ticket_rows,
		'count' => count($flagged_rows),
		'rows_flagged' => $flagged_rows,
		'latest_bad_write' => $latest_bad_write,
		'message' => $message,
		'writer_suspect' => $latest_bad_write,
	);
}

function vms_ticket_inventory_forensics_build_event_diagnostics(int $plan_id, array $args = array()): array
{
	$plan_id = absint($plan_id);
	if ($plan_id <= 0) {
		return array();
	}

	$context = is_array($args['context'] ?? null) ? $args['context'] : array();
	$issues = array_values(array_filter((array) ($args['issues'] ?? array()), 'is_array'));
	$repair_report = is_array($args['repair_report'] ?? null) ? $args['repair_report'] : array();
	$recent_logs = array_values(array_filter((array) ($args['recent_logs'] ?? array()), 'is_array'));
	if (empty($recent_logs)) {
		$recent_logs = vms_ticket_inventory_forensics_recent_logs($plan_id, 24);
	}

	$event_snapshot = vms_ticket_inventory_forensics_snapshot_event(absint($context['tec_event_id'] ?? 0));
	$ticket_rows = vms_ticket_inventory_forensics_build_ticket_rows(
		$plan_id,
		array(
			'context' => $context,
			'ticket_snapshots' => (array) ($args['ticket_snapshots'] ?? array()),
			'entitlement_snapshots' => (array) ($args['entitlement_snapshots'] ?? array()),
			'recent_logs' => $recent_logs,
		)
	);
	$recorruption = vms_ticket_inventory_forensics_detect_woo_recorruption($ticket_rows, $recent_logs, $repair_report);
	$ticket_rows = array_values(array_filter((array) ($recorruption['rows'] ?? $ticket_rows), 'is_array'));

	$public_rows = array_values(array_filter($ticket_rows, static function ($row): bool {
		return is_array($row) && !empty($row['standard_public']) && !empty($row['expected_open']);
	}));
	$public_zero_conflicts = array_values(array_filter($public_rows, static function ($row): bool {
		return is_array($row) && !empty($row['mismatch_flags']['zero_available_conflict']);
	}));
	$public_closed_conflicts = array_values(array_filter($public_rows, static function ($row): bool {
		if (!is_array($row)) {
			return false;
		}

		$flags = is_array($row['mismatch_flags'] ?? null) ? $row['mismatch_flags'] : array();
		$verification_result = sanitize_key((string) ($row['verification_result'] ?? ''));
		if (
			!empty($flags['unexpected_zero_stock'])
			|| !empty($flags['unexpected_outofstock'])
			|| !empty($flags['zero_available_conflict'])
			|| in_array($verification_result, array('woo_mismatch', 'woo_recorruption'), true)
		) {
			return true;
		}

		$expected_remaining = is_numeric($row['expected_remaining_hint'] ?? null) ? (int) $row['expected_remaining_hint'] : null;
		$event_remaining = is_numeric($row['event_remaining'] ?? null) ? (int) $row['event_remaining'] : null;
		$has_remaining = ($expected_remaining !== null && $expected_remaining > 0)
			|| ($event_remaining !== null && $event_remaining > 0);
		if (!$has_remaining) {
			return false;
		}

		$stock_qty = is_numeric($row['stock_qty'] ?? null) ? (int) $row['stock_qty'] : null;
		$stock_closed = (
			(!empty($row['manage_stock']) && $stock_qty !== null && $stock_qty <= 0)
			|| sanitize_key((string) ($row['stock_status'] ?? '')) === 'outofstock'
		);

		return $stock_closed;
	}));
	$all_public_zero = !empty($public_rows);
	foreach ($public_rows as $row) {
		if (sanitize_key((string) ($row['tec_sellability'] ?? 'unknown')) !== 'closed') {
			$all_public_zero = false;
			break;
		}
	}

	$event_capacity = $event_snapshot['event_capacity'] ?? null;
	$event_sold = $event_snapshot['event_sold'] ?? null;
	$zero_available_mismatch = (
		!empty($public_closed_conflicts)
		|| (
			is_numeric($event_capacity)
			&& (int) $event_capacity > 0
			&& is_numeric($event_sold)
			&& (int) $event_sold < (int) $event_capacity
			&& !empty($public_rows)
			&& ($all_public_zero || !empty($public_zero_conflicts))
			)
	);

	$verification_summary = array(
		'verified' => 0,
		'woo_verified' => 0,
		'sold_out_healthy' => 0,
		'woo_mismatch' => 0,
		'tec_followup' => 0,
		'woo_recorruption' => 0,
		'manual_review' => 0,
	);
	foreach ($ticket_rows as $row) {
		$result = sanitize_key((string) ($row['verification_result'] ?? 'manual_review'));
		if (!array_key_exists($result, $verification_summary)) {
			$result = 'manual_review';
		}
		$verification_summary[$result]++;
	}

	$woo_mismatch_rows = array_values(array_filter($ticket_rows, static function ($row): bool {
		return is_array($row) && in_array(sanitize_key((string) ($row['verification_result'] ?? '')), array('woo_mismatch', 'woo_recorruption'), true);
	}));
	$tec_followup_rows = array_values(array_filter($ticket_rows, static function ($row): bool {
		return is_array($row) && sanitize_key((string) ($row['verification_result'] ?? '')) === 'tec_followup';
	}));

	$upstream_writer_suspect = array();
	if (!empty($recorruption['writer_suspect']) && is_array($recorruption['writer_suspect'])) {
		$upstream_writer_suspect = $recorruption['writer_suspect'];
	} elseif (!empty($woo_mismatch_rows)) {
		foreach ($woo_mismatch_rows as $row) {
			if (!is_array($row)) {
				continue;
			}

			if (empty($upstream_writer_suspect) || absint($row['last_changed_gmt'] ?? 0) > absint($upstream_writer_suspect['timestamp_gmt'] ?? 0)) {
				$upstream_writer_suspect = array(
					'timestamp_gmt' => absint($row['last_changed_gmt'] ?? 0),
					'source_function' => (string) ($row['last_change_source'] ?? ''),
					'source_hook' => (string) ($row['last_change_trigger'] ?? ''),
					'reason_text' => (string) ($row['last_write_reason'] ?? ''),
					'ticket_label' => (string) ($row['ticket_label'] ?? __('Ticket', 'backstage-venue-manager')),
					'product_id' => absint($row['product_id'] ?? 0),
				);
			}
		}
	}

	$cause = vms_ticket_inventory_forensics_classify_cause($ticket_rows, $event_snapshot, $issues);
	$origin_classification = sanitize_key((string) ($args['origin_classification'] ?? ''));
	$comparison = vms_ticket_inventory_forensics_build_comparison(
		$plan_id,
		array(
			'ticket_rows' => $ticket_rows,
			'healthy_event' => is_array($args['healthy_event'] ?? null) ? $args['healthy_event'] : array(),
		)
	);
	$cluster = vms_ticket_inventory_forensics_build_cluster_note($zero_available_mismatch);
	$latest_log = !empty($recent_logs[0]) && is_array($recent_logs[0]) ? $recent_logs[0] : array();
	$repeated_drift = vms_ticket_inventory_forensics_detect_repeated_drift($plan_id, $recent_logs, $zero_available_mismatch);
	$addon_rows = array_values(array_filter($ticket_rows, static function ($row): bool {
		return is_array($row) && sanitize_key((string) ($row['role'] ?? '')) === 'add_on';
	}));
	$addon_divergence = (!empty($public_zero_conflicts) && !empty(array_filter($addon_rows, static function ($row): bool {
		return (is_numeric($row['available_qty'] ?? null) && (int) $row['available_qty'] > 0)
			|| (is_numeric($row['stock_qty'] ?? null) && (int) $row['stock_qty'] > 0);
	})));
	$recommended_action = vms_ticket_inventory_forensics_recommended_action((string) ($cause['cause'] ?? ''), $zero_available_mismatch, $origin_classification);
	if (!empty($recorruption['flagged'])) {
		$recommended_action = __('Repair Woo only after you block the later writer that is re-closing inventory. Review the latest conflicting Woo write first.', 'backstage-venue-manager');
	} elseif (!empty($tec_followup_rows)) {
		$recommended_action = __('Woo already matches VMS intent on at least one row. Investigate TEC-side availability on those rows before rewriting Woo again.', 'backstage-venue-manager');
	} elseif (!empty($upstream_writer_suspect['source_function']) || !empty($upstream_writer_suspect['source_hook'])) {
		$recommended_action = __('Review the latest conflicting Woo writer and its derivation reason before running another repair. The bad data source now looks attributable.', 'backstage-venue-manager');
	}

	return array(
		'event_capacity' => $event_capacity,
		'event_available' => $event_snapshot['event_available'] ?? null,
		'event_sold' => $event_sold,
		'event_global_stock_enabled' => !empty($event_snapshot['global_stock_enabled']) ? 1 : 0,
		'event_global_stock_level' => $event_snapshot['global_stock_level_raw'] ?? '',
		'zero_available_mismatch' => $zero_available_mismatch ? 1 : 0,
		'zero_available_ticket_count' => count($public_closed_conflicts),
		'all_public_tickets_zero' => $all_public_zero ? 1 : 0,
		'addon_divergence' => $addon_divergence ? 1 : 0,
		'woo_primary_mismatch' => !empty($woo_mismatch_rows) ? 1 : 0,
		'woo_primary_mismatch_count' => count($woo_mismatch_rows),
		'tec_followup_required' => !empty($tec_followup_rows) ? 1 : 0,
		'tec_followup_count' => count($tec_followup_rows),
		'woo_recorruption_detected' => !empty($recorruption['flagged']) ? 1 : 0,
		'suspected_cause' => (string) ($cause['cause'] ?? 'healthy'),
		'suspected_cause_label' => (string) ($cause['label'] ?? vms_ticket_inventory_forensics_cause_label('healthy')),
		'cause_reasons' => array_values((array) ($cause['reasons'] ?? array())),
		'verification_summary' => $verification_summary,
		'upstream_writer_suspect' => $upstream_writer_suspect,
		'ticket_rows' => $ticket_rows,
		'latest_inventory_mutation' => $latest_log,
		'recent_inventory_mutations' => $recent_logs,
		'repeated_inventory_drift' => $repeated_drift,
		'woo_recorruption' => $recorruption,
		'recommended_action' => $recommended_action,
		'healthy_comparison' => $comparison,
		'origin_cluster' => $cluster,
	);
}
