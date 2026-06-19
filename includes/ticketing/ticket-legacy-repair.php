<?php
defined('ABSPATH') || exit;

function vms_ticket_integrity_repair_status_label(string $status): string
{
	switch (sanitize_key($status)) {
		case 'repaired':
			return __('Repair completed', 'vms');
		case 'no_changes':
			return __('No changes were needed', 'vms');
		case 'partial_changes':
			return __('Repair made partial changes', 'vms');
		case 'partial':
			return __('Repair attempted but unresolved conflicts remain', 'vms');
		case 'blocked':
			return __('Repair could not proceed safely', 'vms');
		default:
			return __('Repair failed', 'vms');
	}
}

function vms_ticket_integrity_repair_report_meta_key(): string
{
	return '_vms_ticket_integrity_last_repair_v1';
}

function vms_ticket_integrity_get_repair_report(int $plan_id): array
{
	$plan_id = absint($plan_id);
	if ($plan_id <= 0) {
		return array();
	}

	$report = get_post_meta($plan_id, vms_ticket_integrity_repair_report_meta_key(), true);
	return is_array($report) ? $report : array();
}

function vms_ticket_integrity_save_repair_report(int $plan_id, array $report): void
{
	$plan_id = absint($plan_id);
	if ($plan_id <= 0) {
		return;
	}

	$report['saved_at_gmt'] = time();
	update_post_meta($plan_id, vms_ticket_integrity_repair_report_meta_key(), $report);
}

function vms_ticket_integrity_repair_role_label(string $role_key): string
{
	switch (sanitize_key($role_key)) {
		case 'standard_ticket':
			return __('Standard/public ticket', 'vms');
		case 'qualified_ticket':
			return __('Verified/qualified ticket', 'vms');
		case 'add_on':
			return __('Add-on', 'vms');
		default:
			return __('Unknown', 'vms');
	}
}

function vms_ticket_integrity_repair_role_key_for_action(array $preview_action, array $cfg_row = array()): string
{
	$scope = sanitize_key((string) ($preview_action['scope'] ?? ''));
	if ($scope === 'entitlement') {
		return 'add_on';
	}

	$visibility_mode = sanitize_key((string) ($cfg_row['visibility_mode'] ?? 'public'));
	return ($visibility_mode === 'verified') ? 'qualified_ticket' : 'standard_ticket';
}

function vms_ticket_integrity_repair_source_value(array $cfg_row, string $role_key): string
{
	if ($role_key === 'add_on') {
		$value = $cfg_row['capacity'] ?? $cfg_row['inventory_total'] ?? '';
		return ($value === '' || $value === null) ? '—' : (string) $value;
	}

	$value = $cfg_row['inventory_total'] ?? $cfg_row['capacity'] ?? '';
	return ($value === '' || $value === null) ? '—' : (string) $value;
}

function vms_ticket_integrity_repair_result_health_label(string $health): string
{
	$health = sanitize_key($health);
	if ($health !== '' && function_exists('vms_ticketing_v2_inventory_result_health_label')) {
		return (string) vms_ticketing_v2_inventory_result_health_label($health);
	}

	switch ($health) {
		case 'expected_sellable_state':
			return __('Write produced a sellable state', 'vms');
		case 'expected_closed_state':
			return __('Write produced a valid closed state', 'vms');
		case 'fallback_state_applied':
			return __('Write completed from a fallback branch', 'vms');
		case 'fallback_closed_state':
			return __('Fallback branch left the product closed', 'vms');
		case 'unexpected_closed_state':
			return __('Write completed but left the product unexpectedly closed', 'vms');
		default:
			return __('Not recorded', 'vms');
	}
}

function vms_ticket_integrity_repair_skip_reason_label(string $code): string
{
	switch (sanitize_key($code)) {
		case 'already_in_sync':
			return __('Config hash matched and no repair condition was active.', 'vms');
		case 'disabled_unmapped':
			return __('This role is disabled in config and there was no mapped product to unpublish.', 'vms');
		case 'mode_not_managed':
			return __('Ticketing mode was not VMS-managed, so the repair branch was not entered.', 'vms');
		case 'blocked_upstream':
			return __('Repair was blocked by an upstream guardrail before this role could be rewritten.', 'vms');
		case 'zero_stock_despite_sellable_config':
			return __('Live stock was 0 even though config still expects this ticket to remain sellable.', 'vms');
		case 'outofstock_despite_sellable_config':
			return __('Live stock status was out of stock even though config still expects this ticket to remain sellable.', 'vms');
		case 'zero_stock_despite_positive_capacity':
			return __('Live stock was 0 even though this add-on still has positive configured capacity.', 'vms');
		case 'outofstock_despite_positive_capacity':
			return __('Live stock status was out of stock even though this add-on still has positive configured capacity.', 'vms');
		default:
			return __('Skip reason was not recorded.', 'vms');
	}
}

function vms_ticket_integrity_repair_branch_status(array $preview_action, array $result_row = array()): string
{
	$action = sanitize_key((string) ($preview_action['action'] ?? 'noop'));
	if ($action === 'error' || ($action === 'skip' && !empty($preview_action['skip_safety_driven']))) {
		return 'blocked';
	}
	if (in_array($action, array('create', 'adopt', 'update', 'disable'), true) || !empty($result_row)) {
		return 'entered';
	}

	return 'not_entered';
}

function vms_ticket_integrity_repair_branch_status_label(string $status): string
{
	switch (sanitize_key($status)) {
		case 'entered':
			return __('Branch entered', 'vms');
		case 'blocked':
			return __('Branch blocked', 'vms');
		default:
			return __('Branch not entered', 'vms');
	}
}

function vms_ticket_integrity_repair_writer_branch_label(string $branch): string
{
	$branch = sanitize_key($branch);
	if ($branch === '') {
		return __('Not recorded', 'vms');
	}

	return ucwords(str_replace('_', ' ', $branch));
}

function vms_ticket_integrity_repair_role_group(string $role_key): array
{
	return array(
		'label' => vms_ticket_integrity_repair_role_label($role_key),
		'attempted' => 0,
		'succeeded' => 0,
		'skipped' => 0,
		'no_effect' => 0,
		'partial' => 0,
		'failed' => 0,
		'branch_entered' => 0,
		'branch_not_entered' => 0,
		'branch_blocked' => 0,
		'entries' => array(),
	);
}

function vms_ticket_integrity_repair_result_state(array $preview_action, array $result_row): string
{
	$action = sanitize_key((string) ($preview_action['action'] ?? 'noop'));
	if ($action === 'skip') {
		return 'skipped';
	}
	if ($action === 'noop') {
		return 'no_op';
	}
	if ($action === 'error') {
		return 'failed';
	}

	if (empty($result_row)) {
		return in_array($action, array('create', 'adopt', 'update', 'disable'), true) ? 'failed' : 'skipped';
	}

	if (empty($result_row['ok'])) {
		return 'failed';
	}

	$message = sanitize_key((string) ($result_row['message'] ?? ''));
	if ($message === 'noop') {
		return 'no_op';
	}

	$result_health = sanitize_key((string) ($result_row['result_health'] ?? ''));
	if (
		!empty($result_row['used_fallback'])
		|| in_array($result_health, array('fallback_state_applied', 'fallback_closed_state', 'unexpected_closed_state'), true)
	) {
		return 'partial';
	}

	return 'success';
}

function vms_ticket_integrity_repair_change_type_for_state(string $state): string
{
	switch (sanitize_key($state)) {
		case 'skipped':
			return 'repair_skipped_role';
		case 'failed':
			return 'repair_partial';
		case 'no_op':
			return 'repair_no_effect';
		default:
			return 'stock_restored';
	}
}

function vms_ticket_integrity_repair_role_entry_reason(array $preview_action, array $result_row): string
{
	$explicit_reason = trim((string) ($result_row['reason_text'] ?? ''));
	if ($explicit_reason !== '') {
		return $explicit_reason;
	}

	$preview_note = trim((string) ($preview_action['notes'] ?? ''));
	$skip_reason_code = sanitize_key((string) ($preview_action['skip_reason_code'] ?? ''));
	if ($skip_reason_code !== '') {
		return vms_ticket_integrity_repair_skip_reason_label($skip_reason_code);
	}

	$result_message = sanitize_key((string) ($result_row['message'] ?? ''));
	if (!empty($result_row) && $result_message !== '') {
		switch ($result_message) {
			case 'created':
				return __('The rebuild created a fresh product from authoritative config.', 'vms');
			case 'updated':
				return __('The rebuild updated the existing mapped product from authoritative config.', 'vms');
			case 'adopted':
				return __('The rebuild adopted an existing product and restamped it to the current event.', 'vms');
			case 'disabled':
				return __('The rebuild disabled a mapped product because the config no longer wants it live.', 'vms');
			case 'noop':
				return __('The rebuild found no effective change for this role after comparing the live product against config.', 'vms');
		}
	}

	if ($preview_note !== '') {
		return $preview_note;
	}

	return __('Repair outcome requires manual review.', 'vms');
}

function vms_ticket_integrity_repair_log_entry(int $plan_id, array $entry): void
{
	if (!function_exists('vms_ticket_inventory_forensics_log_direct_change')) {
		return;
	}

	$state = sanitize_key((string) ($entry['result_state'] ?? 'no_op'));
	$result_status = 'success';
	if ($state === 'skipped') {
		$result_status = 'skipped';
	} elseif ($state === 'failed') {
		$result_status = 'failed';
	} elseif ($state === 'no_op') {
		$result_status = 'no_op';
	}

	vms_ticket_inventory_forensics_log_direct_change(
		$plan_id,
		array(
			'product_id' => absint($entry['product_id'] ?? 0),
			'tec_event_id' => absint($entry['tec_event_id'] ?? 0),
			'event_title' => (string) ($entry['event_title'] ?? ''),
			'mutation_key' => 'repair_state',
			'change_type' => vms_ticket_integrity_repair_change_type_for_state($state),
			'result_status' => $result_status,
			'product_role' => (string) ($entry['role_key'] ?? 'unknown'),
			'derivation_source' => (string) ($entry['derivation_source'] ?? 'repair_audit'),
			'confidence_level' => (string) ($entry['confidence_level'] ?? 'authoritative'),
			'expected_effect' => (string) ($entry['expected_effect'] ?? 'unknown'),
			'reason_text' => (string) ($entry['reason_text'] ?? ''),
			'writer_branch' => (string) ($entry['writer_branch'] ?? ''),
			'result_health' => (string) ($entry['result_health'] ?? ''),
			'skip_reason_code' => (string) ($entry['skip_reason_code'] ?? ''),
			'skip_expected' => !empty($entry['skip_expected']) ? 1 : 0,
			'skip_safety_driven' => !empty($entry['skip_safety_driven']) ? 1 : 0,
			'summary_text' => (string) ($entry['summary_text'] ?? ''),
			'source_function' => 'vms_ticket_integrity_repair_event',
			'source_hook' => sanitize_key((string) current_filter()),
		)
	);
}

function vms_ticket_integrity_build_repair_report(int $plan_id, array $args = array()): array
{
	$plan_id = absint($plan_id);
	if ($plan_id <= 0) {
		return array();
	}

	$cfg = is_array($args['cfg'] ?? null) ? $args['cfg'] : array();
	$preview = is_array($args['preview'] ?? null) ? $args['preview'] : array();
	$commit = is_array($args['commit'] ?? null) ? $args['commit'] : array();
	$diagnostic_scan = is_array($args['diagnostic_scan'] ?? null) ? $args['diagnostic_scan'] : array();
	$repair_status = sanitize_key((string) ($args['repair_status'] ?? 'blocked'));
	$summary_text = trim((string) ($args['summary_text'] ?? ''));
	$tec_event_id = function_exists('vms_ticketing_b_get_linked_tec_event_id')
		? absint(vms_ticketing_b_get_linked_tec_event_id($plan_id))
		: absint(get_post_meta($plan_id, '_vms_tec_event_id', true));
	$event_title = $tec_event_id > 0 ? (string) get_the_title($tec_event_id) : (string) get_the_title($plan_id);

	$ticket_cfg_by_key = array();
	foreach ((array) ($cfg['tickets'] ?? array()) as $ticket_row) {
		if (!is_array($ticket_row)) {
			continue;
		}
		$ticket_key = sanitize_key((string) ($ticket_row['ticket_key'] ?? $ticket_row['key'] ?? ''));
		if ($ticket_key !== '') {
			$ticket_cfg_by_key[$ticket_key] = $ticket_row;
		}
	}

	$ent_cfg_by_id = array();
	foreach ((array) ($cfg['entitlements'] ?? array()) as $entitlement_row) {
		if (!is_array($entitlement_row)) {
			continue;
		}
		$ent_id = sanitize_key((string) ($entitlement_row['entitlement_id'] ?? ''));
		if ($ent_id !== '') {
			$ent_cfg_by_id[$ent_id] = $entitlement_row;
		}
	}

	$result_index = array();
	foreach ((array) ($commit['results'] ?? array()) as $result_row) {
		if (!is_array($result_row)) {
			continue;
		}
		$scope = sanitize_key((string) ($result_row['scope'] ?? ''));
		$key = ($scope === 'entitlement')
			? sanitize_key((string) ($result_row['entitlement_id'] ?? ''))
			: sanitize_key((string) ($result_row['ticket_key'] ?? ''));
		if ($scope !== '' && $key !== '') {
			$result_index[$scope . ':' . $key] = $result_row;
		}
	}

	$roles = array(
		'standard_ticket' => vms_ticket_integrity_repair_role_group('standard_ticket'),
		'qualified_ticket' => vms_ticket_integrity_repair_role_group('qualified_ticket'),
		'add_on' => vms_ticket_integrity_repair_role_group('add_on'),
	);

	$entries = array();
	foreach ((array) ($preview['actions'] ?? array()) as $preview_action) {
		if (!is_array($preview_action)) {
			continue;
		}

		$scope = sanitize_key((string) ($preview_action['scope'] ?? ''));
		$key = ($scope === 'entitlement')
			? sanitize_key((string) ($preview_action['entitlement_id'] ?? ''))
			: sanitize_key((string) ($preview_action['ticket_key'] ?? ''));
		if ($scope === '' || $key === '') {
			continue;
		}

		$cfg_row = ($scope === 'entitlement')
			? (is_array($ent_cfg_by_id[$key] ?? null) ? $ent_cfg_by_id[$key] : array())
			: (is_array($ticket_cfg_by_key[$key] ?? null) ? $ticket_cfg_by_key[$key] : array());
		$role_key = vms_ticket_integrity_repair_role_key_for_action($preview_action, $cfg_row);
		$result_row = is_array($result_index[$scope . ':' . $key] ?? null) ? $result_index[$scope . ':' . $key] : array();
		$result_state = vms_ticket_integrity_repair_result_state($preview_action, $result_row);
		$branch_status = vms_ticket_integrity_repair_branch_status($preview_action, $result_row);
		$product_id = absint($result_row['woo_product_id'] ?? $preview_action['woo_product_id'] ?? 0);
		$write_attempted = !in_array(sanitize_key((string) ($preview_action['action'] ?? 'noop')), array('skip', 'noop', 'error'), true);
		$skip_reason_code = sanitize_key((string) ($preview_action['skip_reason_code'] ?? ''));
		$derivation_source = sanitize_key((string) ($result_row['derivation_source'] ?? ($result_state === 'skipped' ? 'repair_branch_guardrail' : 'authoritative_config')));
		$confidence_level = sanitize_key((string) ($result_row['confidence_level'] ?? ($result_state === 'skipped' ? 'authoritative' : 'unknown')));
		$expected_effect = sanitize_key((string) ($result_row['expected_effect'] ?? (($result_state === 'success') ? 'reopen' : (($result_state === 'no_op') ? 'preserve' : 'unknown'))));
		$result_health = sanitize_key((string) ($result_row['result_health'] ?? ''));
		$writer_branch = sanitize_key((string) ($result_row['writer_branch'] ?? ''));
		$reason_text = vms_ticket_integrity_repair_role_entry_reason($preview_action, $result_row);
		$entry = array(
			'role_key' => $role_key,
			'role_label' => vms_ticket_integrity_repair_role_label($role_key),
			'label' => (string) ($preview_action['label'] ?? $key),
			'scope' => $scope,
			'config_key' => $key,
			'product_id' => $product_id,
			'preview_action' => sanitize_key((string) ($preview_action['action'] ?? 'noop')),
			'write_attempted' => $write_attempted ? 1 : 0,
			'branch_status' => $branch_status,
			'branch_status_label' => vms_ticket_integrity_repair_branch_status_label($branch_status),
			'skip_reason_code' => $skip_reason_code,
			'skip_reason_label' => $skip_reason_code !== '' ? vms_ticket_integrity_repair_skip_reason_label($skip_reason_code) : '',
			'skip_expected' => !empty($preview_action['skip_expected']) ? 1 : 0,
			'skip_safety_driven' => !empty($preview_action['skip_safety_driven']) ? 1 : 0,
			'result_state' => $result_state,
			'result_label' => vms_ticket_inventory_forensics_result_label($result_state),
			'message' => sanitize_key((string) ($result_row['message'] ?? '')),
			'source_value' => vms_ticket_integrity_repair_source_value($cfg_row, $role_key),
			'derivation_source' => $derivation_source,
			'derivation_source_label' => function_exists('vms_ticket_inventory_forensics_source_label') ? vms_ticket_inventory_forensics_source_label($derivation_source) : __('Authoritative config', 'vms'),
			'confidence_level' => $confidence_level !== '' ? $confidence_level : 'unknown',
			'confidence_label' => function_exists('vms_ticket_inventory_forensics_confidence_label') ? vms_ticket_inventory_forensics_confidence_label($confidence_level !== '' ? $confidence_level : 'unknown') : __('Unknown', 'vms'),
			'expected_effect' => $expected_effect !== '' ? $expected_effect : 'unknown',
			'expected_effect_label' => function_exists('vms_ticket_inventory_forensics_expected_effect_label') ? vms_ticket_inventory_forensics_expected_effect_label($expected_effect !== '' ? $expected_effect : 'unknown') : __('Unknown effect', 'vms'),
			'writer_branch' => $writer_branch,
			'writer_branch_label' => vms_ticket_integrity_repair_writer_branch_label($writer_branch),
			'result_health' => $result_health,
			'result_health_label' => vms_ticket_integrity_repair_result_health_label($result_health),
			'used_fallback' => !empty($result_row['used_fallback']) ? 1 : 0,
			'final_stock_qty' => $result_row['final_stock_qty'] ?? null,
			'final_stock_status' => (string) ($result_row['final_stock_status'] ?? ''),
			'final_manage_stock' => array_key_exists('final_manage_stock', $result_row) ? absint($result_row['final_manage_stock']) : null,
			'final_manage_stock_label' => array_key_exists('final_manage_stock', $result_row)
				? (function_exists('vms_ticket_inventory_forensics_bool_label') ? vms_ticket_inventory_forensics_bool_label(!empty($result_row['final_manage_stock'])) : (!empty($result_row['final_manage_stock']) ? __('Yes', 'vms') : __('No', 'vms')))
				: '—',
			'authoritative_data_missing' => !empty($result_row['used_fallback']) ? 1 : 0,
			'reason_text' => $reason_text,
			'summary_text' => sprintf('%s: %s', vms_ticket_integrity_repair_role_label($role_key), $reason_text),
			'tec_event_id' => $tec_event_id,
			'event_title' => $event_title,
		);

		if (!isset($roles[$role_key])) {
			$roles[$role_key] = vms_ticket_integrity_repair_role_group($role_key);
		}

		if ($write_attempted) {
			$roles[$role_key]['attempted']++;
		}
		if ($result_state === 'success') {
			$roles[$role_key]['succeeded']++;
		} elseif ($result_state === 'skipped') {
			$roles[$role_key]['skipped']++;
		} elseif ($result_state === 'no_op') {
			$roles[$role_key]['no_effect']++;
		} elseif ($result_state === 'partial') {
			$roles[$role_key]['partial']++;
		} else {
			$roles[$role_key]['failed']++;
		}
		if ($branch_status === 'entered') {
			$roles[$role_key]['branch_entered']++;
		} elseif ($branch_status === 'blocked') {
			$roles[$role_key]['branch_blocked']++;
		} else {
			$roles[$role_key]['branch_not_entered']++;
		}

		$roles[$role_key]['entries'][] = $entry;
		$entries[] = $entry;
	}

	$admission_success_count = (int) ($roles['standard_ticket']['succeeded'] ?? 0) + (int) ($roles['qualified_ticket']['succeeded'] ?? 0);
	$admission_attempt_count = (int) ($roles['standard_ticket']['attempted'] ?? 0) + (int) ($roles['qualified_ticket']['attempted'] ?? 0);
	$addon_success_count = (int) ($roles['add_on']['succeeded'] ?? 0);
	$detail_state = '';
	if ($repair_status === 'partial' && $addon_success_count > 0 && $admission_attempt_count > 0 && $admission_success_count === 0) {
		$detail_state = 'addons_only_changed';
	}

	$warnings = array_values(array_unique(array_filter(array_merge(
		array_values(array_map('strval', (array) ($preview['warnings'] ?? array()))),
		array_values(array_map('strval', (array) ($commit['warnings'] ?? array()))),
		array_values(array_map('strval', (array) ($commit['reconciliation']['warnings'] ?? array())))
	))));
	$fallback_entries = array_values(array_filter($entries, static function ($entry): bool {
		return is_array($entry) && !empty($entry['used_fallback']);
	}));
	$closed_state_entries = array_values(array_filter($entries, static function ($entry): bool {
		if (!is_array($entry)) {
			return false;
		}
		$health = sanitize_key((string) ($entry['result_health'] ?? ''));
		return in_array($health, array('unexpected_closed_state', 'fallback_closed_state'), true);
	}));
	$admission_skipped = array_values(array_filter($entries, static function ($entry): bool {
		if (!is_array($entry)) {
			return false;
		}
		$role = sanitize_key((string) ($entry['role_key'] ?? ''));
		return in_array($role, array('standard_ticket', 'qualified_ticket'), true)
			&& sanitize_key((string) ($entry['result_state'] ?? '')) === 'skipped';
	}));
	if (!empty($fallback_entries)) {
		$warnings[] = __('One or more repair writes used a fallback derivation path because sold quantities could not be resolved authoritatively.', 'vms');
	}
	if (!empty($closed_state_entries)) {
		$warnings[] = __('One or more repair writes completed but still left the product in a closed or out-of-stock state.', 'vms');
	}
	if ((int) ($roles['standard_ticket']['branch_entered'] ?? 0) < 1 && !empty($roles['standard_ticket']['entries'])) {
		$warnings[] = __('The standard/public ticket branch was not entered during the last rebuild.', 'vms');
	}
	if (!empty($admission_skipped)) {
		$warnings[] = __('One or more admission-ticket roles were skipped and still need manual review.', 'vms');
	}
	$warnings = array_values(array_unique(array_filter(array_map('strval', $warnings))));
	$inventory_diagnostics = is_array($diagnostic_scan['inventory_diagnostics'] ?? null) ? $diagnostic_scan['inventory_diagnostics'] : array();
	$verification_summary = is_array($inventory_diagnostics['verification_summary'] ?? null) ? $inventory_diagnostics['verification_summary'] : array();
	$woo_verification_status = 'needs_review';
	$woo_verification_label = __('Woo verification still needs review', 'vms');
	if (!empty($inventory_diagnostics['woo_recorruption_detected'])) {
		$woo_verification_status = 'recorrupted';
		$woo_verification_label = __('Woo was repaired, then later re-corrupted', 'vms');
	} elseif (!empty($inventory_diagnostics['woo_primary_mismatch'])) {
		$woo_verification_status = 'mismatch';
		$woo_verification_label = __('Woo still disagrees with VMS intent', 'vms');
	} elseif (
		absint($verification_summary['verified'] ?? 0) > 0
		|| absint($verification_summary['woo_verified'] ?? 0) > 0
		|| absint($verification_summary['sold_out_healthy'] ?? 0) > 0
	) {
		$woo_verification_status = 'verified';
		$woo_verification_label = (absint($verification_summary['sold_out_healthy'] ?? 0) > 0)
			? __('Woo matches sold-aware VMS intent', 'vms')
			: __('Woo matches current VMS intent', 'vms');
	}

	$tec_verification_status = 'needs_review';
	$tec_verification_label = __('TEC verification still needs review', 'vms');
	if (!empty($inventory_diagnostics['tec_followup_required'])) {
		$tec_verification_status = 'followup';
		$tec_verification_label = __('Woo looks correct, but TEC still disagrees', 'vms');
	} elseif (absint($verification_summary['verified'] ?? 0) > 0 || absint($verification_summary['sold_out_healthy'] ?? 0) > 0) {
		$tec_verification_status = 'verified';
		$tec_verification_label = (absint($verification_summary['sold_out_healthy'] ?? 0) > 0)
			? __('TEC also matches the sold-aware closed state', 'vms')
			: __('TEC also matches current VMS intent', 'vms');
	}

	$report = array(
		'plan_id' => $plan_id,
		'tec_event_id' => $tec_event_id,
		'event_title' => $event_title,
		'repair_status' => $repair_status,
		'repair_status_label' => vms_ticket_integrity_repair_status_label($repair_status),
		'summary_text' => $summary_text,
		'detail_state' => $detail_state,
		'detail_state_label' => ($detail_state === 'addons_only_changed')
			? __('Repair changed add-ons but not admission tickets.', 'vms')
			: '',
		'role_breakdown' => $roles,
		'entries' => $entries,
		'warnings' => $warnings,
		'preview_change_count' => absint($args['preview_change_count'] ?? 0),
		'remaining_issue_summary' => (string) ($diagnostic_scan['issue_summary'] ?? ''),
		'woo_verification_status' => $woo_verification_status,
		'woo_verification_label' => $woo_verification_label,
		'tec_verification_status' => $tec_verification_status,
		'tec_verification_label' => $tec_verification_label,
		'upstream_writer_suspect' => is_array($inventory_diagnostics['upstream_writer_suspect'] ?? null) ? $inventory_diagnostics['upstream_writer_suspect'] : array(),
		'woo_recorruption_detected' => !empty($inventory_diagnostics['woo_recorruption_detected']) ? 1 : 0,
	);

	foreach ($entries as $entry) {
		vms_ticket_integrity_repair_log_entry($plan_id, $entry);
	}

	return $report;
}

function vms_ticket_integrity_repair_product_is_valid(int $product_id, int $tec_event_id): bool
{
	$product_id = absint($product_id);
	$tec_event_id = absint($tec_event_id);
	if ($product_id <= 0 || get_post_type($product_id) !== 'product') {
		return false;
	}

	if ((string) get_post_status($product_id) === 'trash') {
		return false;
	}

	if ($tec_event_id > 0) {
		$linked_event_id = absint(get_post_meta($product_id, '_tribe_wooticket_for_event', true));
		if ($linked_event_id > 0 && $linked_event_id !== $tec_event_id) {
			return false;
		}
	}

	return true;
}


function vms_ticket_integrity_authoritative_product_sales_count(int $product_id): int
{
	$product_id = absint($product_id);
	if ($product_id <= 0) {
		return 0;
	}

	if (function_exists('vms_ticketing_v2_calc_sold_qty_for_product')) {
		$result = vms_ticketing_v2_calc_sold_qty_for_product($product_id);
		if (!empty($result['ok'])) {
			return max(0, absint($result['sold_qty'] ?? 0));
		}
	}

	return max(0, absint(get_post_meta($product_id, 'total_sales', true)));
}

function vms_ticket_integrity_duplicate_cleanup_build_plan(int $plan_id): array
{
	$plan_id = absint($plan_id);
	if ($plan_id <= 0) {
		return array('ok' => false, 'message' => 'invalid_plan');
	}

	if (!function_exists('vms_ticket_integrity_build_context')) {
		return array('ok' => false, 'message' => 'context_helper_missing');
	}

	$context = vms_ticket_integrity_build_context($plan_id);
	if (empty($context['plan_exists']) || empty($context['event_exists']) || empty($context['tec_event_id'])) {
		return array('ok' => false, 'message' => 'missing_plan_or_event');
	}

	$product_cache = array();
	$context['attached_products'] = array();
	foreach ((array) ($context['attached_product_ids'] ?? array()) as $product_id) {
		$context['attached_products'][] = vms_ticket_integrity_snapshot_product_cached((int) $product_id, $context, $product_cache);
	}
	$context['ticket_snapshots'] = vms_ticket_integrity_build_ticket_snapshots($context, $product_cache);

	$groups = array();
	foreach ((array) ($context['ticket_snapshots'] ?? array()) as $ticket_snapshot) {
		if (!is_array($ticket_snapshot)) {
			continue;
		}

		$ticket_key = sanitize_key((string) ($ticket_snapshot['ticket_key'] ?? ''));
		$title_token = vms_ticket_integrity_normalize_title_token((string) ($ticket_snapshot['title'] ?? ''));
		$group_key = $ticket_key !== '' ? 'key:' . $ticket_key : ($title_token !== '' ? 'title:' . $title_token : '');
		if ($group_key === '') {
			continue;
		}

		$groups[$group_key] = array(
			'group_key' => $group_key,
			'ticket_key' => $ticket_key,
			'title_token' => $title_token,
			'active' => $ticket_snapshot,
			'cfg_ticket' => function_exists('vms_ticket_integrity_find_ticket_config_row')
				? vms_ticket_integrity_find_ticket_config_row($context, $ticket_key, $title_token)
				: array(),
			'extras' => array(),
		);
	}

	foreach ((array) ($context['attached_products'] ?? array()) as $attached_product) {
		if (!is_array($attached_product)) {
			continue;
		}

		$product_id = absint($attached_product['product_id'] ?? 0);
		if ($product_id <= 0 || in_array($product_id, (array) ($context['mapped_all_product_ids'] ?? array()), true)) {
			continue;
		}
		if (($attached_product['post_type'] ?? '') !== 'product' || empty($attached_product['is_public'])) {
			continue;
		}
		if (sanitize_key((string) ($attached_product['role'] ?? '')) === 'addon') {
			continue;
		}

		$ticket_key = sanitize_key((string) ($attached_product['ticket_key'] ?? ''));
		$title_token = vms_ticket_integrity_normalize_title_token((string) ($attached_product['title'] ?? ''));
		$group_key = '';
		if ($ticket_key !== '' && isset($groups['key:' . $ticket_key])) {
			$group_key = 'key:' . $ticket_key;
		} elseif ($title_token !== '' && isset($groups['title:' . $title_token])) {
			$group_key = 'title:' . $title_token;
		}

		if ($group_key === '') {
			continue;
		}

		$attached_product['authoritative_sales'] = vms_ticket_integrity_authoritative_product_sales_count($product_id);
		$groups[$group_key]['extras'][] = $attached_product;
	}

	$candidates = array();
	$warnings = array();
	$action_counts = array(
		'retire_extra' => 0,
		'adopt_extra_retire_active' => 0,
		'adopt_extra' => 0,
		'manual_review' => 0,
	);

	foreach ($groups as $group) {
		$extras = array_values((array) ($group['extras'] ?? array()));
		if (empty($extras)) {
			continue;
		}

		$active = is_array($group['active'] ?? null) ? $group['active'] : array();
		$active_product = is_array($active['product'] ?? null) ? $active['product'] : array();
		$active_product_id = absint($active['mapped_product_id'] ?? 0);
		$active_total_sales = vms_ticket_integrity_authoritative_product_sales_count($active_product_id);
		$ticket_key = sanitize_key((string) ($group['ticket_key'] ?? ''));
		$title_token = (string) ($group['title_token'] ?? '');
		$cfg_ticket = is_array($group['cfg_ticket'] ?? null) ? $group['cfg_ticket'] : array();

		$sold_extras = array_values(array_filter($extras, static function (array $product): bool {
			return max(0, absint($product['authoritative_sales'] ?? 0)) > 0;
		}));
		$unsold_extras = array_values(array_filter($extras, static function (array $product): bool {
			return max(0, absint($product['authoritative_sales'] ?? 0)) <= 0;
		}));

		$adopted_product_id = 0;
		if (count($sold_extras) > 1) {
			$product_ids = array_values(array_filter(array_map(static function (array $product): int {
				return absint($product['product_id'] ?? 0);
			}, $sold_extras)));
			$warnings[] = sprintf(
				/* translators: 1: ticket title, 2: product id list */
				__('Multiple legacy duplicate ticket products with sales still match the active ticket “%1$s”: %2$s. Manual review is required.', 'vms'),
				(string) ($active['title'] ?? $ticket_key ?: __('Ticket', 'vms')),
				implode(', ', array_map(static function (int $product_id): string {
					return '#' . $product_id;
				}, $product_ids))
			);
			$action_counts['manual_review']++;
		} elseif (count($sold_extras) === 1) {
			$sold_extra = $sold_extras[0];
			$sold_extra_id = absint($sold_extra['product_id'] ?? 0);
			$sold_extra_sales = max(0, absint($sold_extra['authoritative_sales'] ?? 0));

			if ($active_total_sales > 0 && $active_product_id > 0 && $active_product_id !== $sold_extra_id) {
				$warnings[] = sprintf(
					/* translators: 1: active product id, 2: duplicate product id, 3: ticket title */
					__('Both the current mapped product #%1$d and the duplicate legacy product #%2$d have sales for “%3$s”. Cleanup was blocked for that ticket because retiring either sold path automatically would be unsafe.', 'vms'),
					$active_product_id,
					$sold_extra_id,
					(string) ($active['title'] ?? $ticket_key ?: __('Ticket', 'vms'))
				);
				$action_counts['manual_review']++;
			} else {
				$action = ($active_product_id > 0 && $active_product_id !== $sold_extra_id) ? 'adopt_extra_retire_active' : 'adopt_extra';
				$candidates[] = array(
					'action' => $action,
					'ticket_key' => $ticket_key,
					'title_token' => $title_token,
					'ticket_title' => (string) ($active['title'] ?? ($sold_extra['title'] ?? $ticket_key ?: __('Ticket', 'vms'))),
					'legacy_product_id' => $sold_extra_id,
					'legacy_title' => (string) ($sold_extra['title'] ?? ''),
					'legacy_total_sales' => $sold_extra_sales,
					'active_product_id' => $active_product_id,
					'active_title' => (string) ($active_product['title'] ?? $active['title'] ?? ''),
					'active_total_sales' => $active_total_sales,
					'cfg_ticket' => $cfg_ticket,
				);
				$adopted_product_id = $sold_extra_id;
				$action_counts[$action]++;
			}
		}

		foreach ($unsold_extras as $unsold_extra) {
			$legacy_product_id = absint($unsold_extra['product_id'] ?? 0);
			if ($legacy_product_id <= 0) {
				continue;
			}
			if ($legacy_product_id === $adopted_product_id || $legacy_product_id === $active_product_id) {
				continue;
			}

			$candidates[] = array(
				'action' => 'retire_extra',
				'ticket_key' => $ticket_key,
				'title_token' => $title_token,
				'ticket_title' => (string) ($active['title'] ?? ($unsold_extra['title'] ?? $ticket_key ?: __('Ticket', 'vms'))),
				'legacy_product_id' => $legacy_product_id,
				'legacy_title' => (string) ($unsold_extra['title'] ?? ''),
				'legacy_total_sales' => 0,
				'active_product_id' => $adopted_product_id > 0 ? $adopted_product_id : $active_product_id,
				'active_title' => $adopted_product_id > 0 ? (string) ($active['title'] ?? ($unsold_extra['title'] ?? '')) : (string) ($active_product['title'] ?? $active['title'] ?? ''),
				'active_total_sales' => $adopted_product_id > 0 ? max(0, absint($sold_extras[0]['authoritative_sales'] ?? 0)) : $active_total_sales,
				'cfg_ticket' => $cfg_ticket,
			);
			$action_counts['retire_extra']++;
		}
	}

	return array(
		'ok' => true,
		'context' => $context,
		'candidates' => $candidates,
		'warnings' => $warnings,
		'action_counts' => $action_counts,
	);
}

function vms_ticket_integrity_duplicate_cleanup_summary(array $result): string
{
	$summary_bits = array();
	$retired = array_values((array) ($result['retired'] ?? array()));
	$adopted = array_values((array) ($result['adopted'] ?? array()));

	if (!empty($retired)) {
		$summary_bits[] = sprintf(_n('%d duplicate product retired', '%d duplicate products retired', count($retired), 'vms'), count($retired));
	}
	if (!empty($adopted)) {
		$summary_bits[] = sprintf(_n('%d sold legacy ticket promoted back into the active map', '%d sold legacy tickets promoted back into the active map', count($adopted), 'vms'), count($adopted));
	}
	if (empty($summary_bits)) {
		return __('No duplicate cleanup actions were needed.', 'vms');
	}

	return implode('; ', $summary_bits) . '.';
}

function vms_ticket_integrity_duplicate_cleanup_run(int $plan_id, array $args = array()): array
{
	$plan = vms_ticket_integrity_duplicate_cleanup_build_plan($plan_id);
	if (empty($plan['ok'])) {
		return $plan;
	}

	$context = is_array($plan['context'] ?? null) ? $plan['context'] : array();
	$candidates = array_values((array) ($plan['candidates'] ?? array()));
	$warnings = array_values((array) ($plan['warnings'] ?? array()));
	$action_counts = is_array($plan['action_counts'] ?? null) ? $plan['action_counts'] : array();

	if (empty($candidates) && !empty($warnings)) {
		return array(
			'ok' => true,
			'status' => 'blocked',
			'summary_text' => __('Duplicate cleanup was blocked for one or more sold ticket paths. Review warnings and clean those manually.', 'vms'),
			'warnings' => $warnings,
			'retired' => array(),
			'adopted' => array(),
			'action_counts' => $action_counts,
		);
	}

	if (empty($candidates)) {
		return array(
			'ok' => false,
			'message' => 'no_duplicate_cleanup_candidates',
		);
	}

	$plan_id = absint($context['plan_id'] ?? $plan_id);
	$tec_event_id = absint($context['tec_event_id'] ?? 0);
	$sync = function_exists('vms_ticketing_v2_get_sync') ? (array) vms_ticketing_v2_get_sync($plan_id) : array();
	if (!is_array($sync['map'] ?? null)) {
		$sync['map'] = array();
	}
	if (!is_array($sync['map']['tickets'] ?? null)) {
		$sync['map']['tickets'] = array();
	}

	$retired = array();
	$adopted = array();
	$errors = array();
	$sync_changed = false;
	$before_snapshot = function_exists('vms_ticket_mutation_audit_build_snapshot') ? vms_ticket_mutation_audit_build_snapshot($plan_id) : array();

	foreach ($candidates as $candidate) {
		$action = sanitize_key((string) ($candidate['action'] ?? ''));
		$ticket_key = sanitize_key((string) ($candidate['ticket_key'] ?? ''));
		$ticket_title = (string) ($candidate['ticket_title'] ?? ($ticket_key !== '' ? $ticket_key : __('Ticket', 'vms')));
		$legacy_product_id = absint($candidate['legacy_product_id'] ?? 0);
		$active_product_id = absint($candidate['active_product_id'] ?? 0);
		$cfg_ticket = is_array($candidate['cfg_ticket'] ?? null) ? $candidate['cfg_ticket'] : array();

		if ($legacy_product_id <= 0) {
			continue;
		}

		if ($action === 'retire_extra') {
			if ($active_product_id <= 0) {
				$errors[] = sprintf(
					__('Skipped retiring duplicate product #%1$d for “%2$s” because no active canonical product could be determined.', 'vms'),
					$legacy_product_id,
					$ticket_title
				);
				continue;
			}

			$ok = function_exists('vms_ticketing_v2_retire_legacy_duplicate_product')
				? vms_ticketing_v2_retire_legacy_duplicate_product($legacy_product_id, $active_product_id, 'ticket_integrity_duplicate_cleanup')
				: false;
			if ($ok) {
				$retired[] = array(
					'product_id' => $legacy_product_id,
					'canonical_product_id' => $active_product_id,
					'ticket_title' => $ticket_title,
					'reason' => 'duplicate_cleanup',
				);
			} else {
				$errors[] = sprintf(
					__('Failed to retire duplicate product #%1$d for “%2$s”.', 'vms'),
					$legacy_product_id,
					$ticket_title
				);
			}
			continue;
		}

		if (!in_array($action, array('adopt_extra', 'adopt_extra_retire_active'), true)) {
			continue;
		}

		if ($ticket_key === '') {
			$errors[] = sprintf(
				__('Skipped promoting sold legacy product #%1$d for “%2$s” because the active ticket key could not be resolved safely.', 'vms'),
				$legacy_product_id,
				$ticket_title
			);
			continue;
		}

		$current_row = is_array($sync['map']['tickets'][$ticket_key] ?? null) ? $sync['map']['tickets'][$ticket_key] : array();
		$current_row['provider'] = (string) ($current_row['provider'] ?? 'tec_tickets_woo');
		$current_row['ticket_key'] = $ticket_key;
		$current_row['woo_product_id'] = $legacy_product_id;
		$current_row['tec_ticket_id'] = $legacy_product_id;
		$current_row['sync_status'] = 'synced';
		$current_row['last_sync_at'] = time();
		$current_row['last_error'] = '';
		if (!empty($cfg_ticket)) {
			$current_row['label'] = (string) ($cfg_ticket['title'] ?? ($current_row['label'] ?? $ticket_title));
			$current_row['counts_toward_unlock'] = !empty($cfg_ticket['counts_toward_unlock']) ? 1 : 0;
			$current_row['max_qty_per_order'] = max(0, absint($cfg_ticket['max_qty_per_order'] ?? 0));
			$current_row['visibility_mode'] = sanitize_key((string) ($cfg_ticket['visibility_mode'] ?? 'public'));
			$current_row['verified_program'] = sanitize_key((string) ($cfg_ticket['verified_program'] ?? ''));
			$current_row['allowed_programs'] = function_exists('vms_ticketing_v2_normalize_allowed_programs')
				? vms_ticketing_v2_normalize_allowed_programs($cfg_ticket['allowed_programs'] ?? array(), $current_row['verified_program'])
				: array_values(array_filter(array_map('sanitize_key', (array) ($cfg_ticket['allowed_programs'] ?? array()))));
			$current_row['allow_direct_grants'] = !empty($cfg_ticket['allow_direct_grants']) ? 1 : 0;
			$current_row['claim_grant_type'] = sanitize_key((string) ($cfg_ticket['claim_grant_type'] ?? 'event_ticket_eligibility'));
			$current_row['claims_per_assignee'] = max(0, absint($cfg_ticket['claims_per_assignee'] ?? 1));
			$current_row['require_assignee_email'] = !empty($cfg_ticket['require_assignee_email']) ? 1 : 0;
			if (function_exists('vms_ticketing_v2_hash_ticket')) {
				$current_row['last_sync_hash'] = (string) vms_ticketing_v2_hash_ticket($cfg_ticket);
			}
		}
		$sync['map']['tickets'][$ticket_key] = $current_row;
		$sync_changed = true;

		if (is_array($sync['map']['ga'] ?? null) && absint($sync['map']['ga']['woo_product_id'] ?? 0) === $active_product_id) {
			$sync['map']['ga']['woo_product_id'] = $legacy_product_id;
			$sync['map']['ga']['tec_ticket_id'] = $legacy_product_id;
			$sync_changed = true;
		}

		if (function_exists('vms_ticketing_v2_stamp_product_markers')) {
			vms_ticketing_v2_stamp_product_markers($legacy_product_id, $plan_id, $tec_event_id, 'ticket');
		}
		if (!empty($cfg_ticket) && function_exists('vms_ticketing_v2_stamp_ticket_runtime_meta')) {
			vms_ticketing_v2_stamp_ticket_runtime_meta($legacy_product_id, $tec_event_id, $cfg_ticket);
		}
		update_post_meta($legacy_product_id, '_tribe_wooticket_for_event', $tec_event_id);
		delete_post_meta($legacy_product_id, '_vms_legacy_retired');
		delete_post_meta($legacy_product_id, '_vms_legacy_retired_reason');
		delete_post_meta($legacy_product_id, '_vms_legacy_duplicate_of');

		$adopted[] = array(
			'product_id' => $legacy_product_id,
			'replaced_product_id' => $active_product_id,
			'ticket_title' => $ticket_title,
		);

		if ($action === 'adopt_extra_retire_active' && $active_product_id > 0 && $active_product_id !== $legacy_product_id) {
			$ok = function_exists('vms_ticketing_v2_retire_legacy_duplicate_product')
				? vms_ticketing_v2_retire_legacy_duplicate_product($active_product_id, $legacy_product_id, 'ticket_integrity_superseded_active_duplicate')
				: false;
			if ($ok) {
				$retired[] = array(
					'product_id' => $active_product_id,
					'canonical_product_id' => $legacy_product_id,
					'ticket_title' => $ticket_title,
					'reason' => 'superseded_active_duplicate',
				);
			} else {
				$errors[] = sprintf(
					__('Promoted sold legacy product #%1$d for “%2$s”, but failed to retire the newer duplicate product #%3$d.', 'vms'),
					$legacy_product_id,
					$ticket_title,
					$active_product_id
				);
			}
		}
	}

	if ($sync_changed && function_exists('vms_ticketing_v2_set_sync')) {
		$sync_audit_pushed = false;
		if (function_exists('vms_ticket_mutation_audit_push_context')) {
			vms_ticket_mutation_audit_push_context(array(
				'trigger_source' => 'rebuild',
				'change_type' => 'ticket_duplicate_cleanup',
				'source_function' => sanitize_key((string) ($args['source_function'] ?? 'vms_ticket_integrity_duplicate_cleanup_run')),
				'source_hook' => sanitize_key((string) current_filter()),
				'summary_text' => __('Updated the ticket sync map while reconciling duplicate legacy products.', 'vms'),
			));
			$sync_audit_pushed = true;
		}
		vms_ticketing_v2_set_sync($plan_id, $sync);
		if ($sync_audit_pushed && function_exists('vms_ticket_mutation_audit_pop_context')) {
			vms_ticket_mutation_audit_pop_context();
		}
	}

	$warnings = array_values(array_unique(array_filter(array_merge($warnings, $errors), static function ($value): bool {
		return is_string($value) && trim($value) !== '';
	})));

	$status = 'complete';
	if (!empty($errors)) {
		$status = !empty($retired) || !empty($adopted) ? 'partial' : 'blocked';
	}

	$summary_text = vms_ticket_integrity_duplicate_cleanup_summary(array(
		'retired' => $retired,
		'adopted' => $adopted,
	));
	if ($status === 'blocked' && empty($retired) && empty($adopted)) {
		$summary_text = __('Duplicate cleanup was blocked for one or more sold ticket paths. Review warnings and clean those manually.', 'vms');
	}

	$after_snapshot = function_exists('vms_ticket_mutation_audit_build_snapshot') ? vms_ticket_mutation_audit_build_snapshot($plan_id) : $before_snapshot;
	if (($sync_changed || !empty($retired) || !empty($adopted)) && function_exists('vms_ticket_mutation_audit_log_direct_change')) {
		vms_ticket_mutation_audit_log_direct_change(
			$plan_id,
			array(
				'change_type' => 'ticket_duplicate_cleanup',
				'result_status' => ($status === 'complete') ? 'success' : (($status === 'partial') ? 'partial' : 'failed'),
				'summary_text' => $summary_text,
				'before_snapshot' => $before_snapshot,
				'after_snapshot' => $after_snapshot,
				'source_function' => sanitize_key((string) ($args['source_function'] ?? 'vms_ticket_integrity_duplicate_cleanup_run')),
				'source_hook' => sanitize_key((string) current_filter()),
			)
		);
	}

	return array(
		'ok' => true,
		'status' => $status,
		'summary_text' => $summary_text,
		'warnings' => $warnings,
		'retired' => $retired,
		'adopted' => $adopted,
		'action_counts' => $action_counts,
		'sync_changed' => $sync_changed,
	);
}

function vms_ticket_integrity_repair_normalize_sync_map(int $plan_id, array $cfg, array $sync): array
{
	$plan_id = absint($plan_id);
	$tec_event_id = function_exists('vms_ticketing_b_get_linked_tec_event_id')
		? absint(vms_ticketing_b_get_linked_tec_event_id($plan_id))
		: absint(get_post_meta($plan_id, '_vms_tec_event_id', true));
	$sync = is_array($sync) ? $sync : array();
	$sync_map = is_array($sync['map'] ?? null) ? $sync['map'] : array();
	$changed = false;
	$notes = array();

	$allowed_ticket_keys = array();
	foreach ((array) ($cfg['tickets'] ?? array()) as $ticket_row) {
		if (!is_array($ticket_row)) {
			continue;
		}
		$ticket_key = sanitize_key((string) ($ticket_row['ticket_key'] ?? $ticket_row['key'] ?? ''));
		if ($ticket_key !== '') {
			$allowed_ticket_keys[$ticket_key] = true;
		}
	}

	$normalized_ticket_map = array();
	foreach ((array) ($sync_map['tickets'] ?? array()) as $ticket_key => $row) {
		if (!is_array($row)) {
			continue;
		}
		$ticket_key = sanitize_key((string) $ticket_key);
		if ($ticket_key === '' || !isset($allowed_ticket_keys[$ticket_key])) {
			$changed = true;
			continue;
		}

		$product_id = absint($row['woo_product_id'] ?? 0);
		if ($product_id > 0 && !vms_ticket_integrity_repair_product_is_valid($product_id, $tec_event_id)) {
			$changed = true;
			$notes[] = sprintf(__('Removed stale mapped ticket reference for %s.', 'vms'), $ticket_key);
			continue;
		}

		$normalized_ticket_map[$ticket_key] = $row;
	}
	$sync_map['tickets'] = $normalized_ticket_map;

	$allowed_entitlement_ids = array();
	foreach ((array) ($cfg['entitlements'] ?? array()) as $entitlement_row) {
		if (!is_array($entitlement_row)) {
			continue;
		}
		$entitlement_id = sanitize_key((string) ($entitlement_row['entitlement_id'] ?? ''));
		if ($entitlement_id !== '') {
			$allowed_entitlement_ids[$entitlement_id] = true;
		}
	}

	$normalized_entitlement_map = array();
	foreach ((array) ($sync_map['entitlements'] ?? array()) as $entitlement_id => $row) {
		if (!is_array($row)) {
			continue;
		}
		$entitlement_id = sanitize_key((string) $entitlement_id);
		if ($entitlement_id === '' || !isset($allowed_entitlement_ids[$entitlement_id])) {
			$changed = true;
			continue;
		}

		$product_id = absint($row['woo_product_id'] ?? 0);
		if ($product_id > 0 && !vms_ticket_integrity_repair_product_is_valid($product_id, $tec_event_id)) {
			$changed = true;
			$notes[] = sprintf(__('Removed stale mapped add-on reference for %s.', 'vms'), $entitlement_id);
			continue;
		}

		$normalized_entitlement_map[$entitlement_id] = $row;
	}
	$sync_map['entitlements'] = $normalized_entitlement_map;

	$ga_row = is_array($sync_map['ga'] ?? null) ? $sync_map['ga'] : array();
	$ga_product_id = absint($ga_row['woo_product_id'] ?? 0);
	if ($ga_product_id > 0 && !vms_ticket_integrity_repair_product_is_valid($ga_product_id, $tec_event_id)) {
		unset($sync_map['ga']);
		$changed = true;
		$notes[] = __('Removed stale primary GA mapping reference.', 'vms');
	}

	$sync['map'] = $sync_map;

	return array(
		'changed' => $changed,
		'sync' => $sync,
		'notes' => array_values(array_unique(array_filter(array_map('strval', $notes)))),
	);
}

function vms_ticket_integrity_repair_event(int $plan_id, array $args = array()): array
{
	$plan_id = absint($plan_id);
	if ($plan_id <= 0) {
		return array('ok' => false, 'message' => 'invalid_plan');
	}

	if (!function_exists('vms_ticketing_v2_get_config') || !function_exists('vms_ticketing_v2_get_sync') || !function_exists('vms_ticketing_v2_preview_sync') || !function_exists('vms_ticketing_v2_commit_sync')) {
		return array('ok' => false, 'message' => 'sync_helpers_missing');
	}

	$before_snapshot = function_exists('vms_ticket_mutation_audit_build_snapshot')
		? vms_ticket_mutation_audit_build_snapshot($plan_id)
		: array();
	$cfg = vms_ticketing_v2_get_config($plan_id);
	$mode = sanitize_key((string) ($cfg['mode'] ?? ''));
	if ($mode !== 'vms_managed') {
		$after_snapshot = function_exists('vms_ticket_mutation_audit_build_snapshot')
			? vms_ticket_mutation_audit_build_snapshot($plan_id)
			: $before_snapshot;
		if (function_exists('vms_ticket_mutation_audit_log_direct_change')) {
			vms_ticket_mutation_audit_log_direct_change(
				$plan_id,
				array(
					'change_type' => 'ticket_map_rebuilt',
					'result_status' => 'failed',
					'summary_text' => __('Repair could not proceed safely because this event is not using VMS-managed ticketing.', 'vms'),
					'before_snapshot' => $before_snapshot,
					'after_snapshot' => $after_snapshot,
					'source_function' => 'vms_ticket_integrity_repair_event',
					'source_hook' => sanitize_key((string) current_filter()),
				)
			);
		}

		vms_ticket_integrity_save_repair_report(
			$plan_id,
			vms_ticket_integrity_build_repair_report(
				$plan_id,
				array(
					'cfg' => $cfg,
					'repair_status' => 'blocked',
					'summary_text' => __('Repair could not proceed safely because this event is not using VMS-managed ticketing.', 'vms'),
				)
			)
		);

		return array(
			'ok' => true,
			'repair_status' => 'blocked',
			'summary_text' => __('Repair could not proceed safely because this event is not using VMS-managed ticketing.', 'vms'),
			'changed' => false,
		);
	}

	$sync = vms_ticketing_v2_get_sync($plan_id);
	$normalization = vms_ticket_integrity_repair_normalize_sync_map($plan_id, $cfg, $sync);
	$changed = false;
	if (!empty($normalization['changed'])) {
		if (function_exists('vms_ticket_mutation_audit_push_context')) {
			vms_ticket_mutation_audit_push_context(
				array(
					'trigger_source' => 'rebuild',
					'change_type' => 'legacy_map_normalized',
					'source_function' => 'vms_ticket_integrity_repair_event',
					'source_hook' => sanitize_key((string) current_filter()),
					'summary_text' => __('Normalized stale mapping references before running rebuild.', 'vms'),
				)
			);
		}

		vms_ticketing_v2_set_sync($plan_id, (array) ($normalization['sync'] ?? array()));

		if (function_exists('vms_ticket_mutation_audit_pop_context')) {
			vms_ticket_mutation_audit_pop_context();
		}

		$changed = true;
	}

	$preview = vms_ticketing_v2_preview_sync($plan_id);
	if (empty($preview['ok'])) {
		$after_snapshot = function_exists('vms_ticket_mutation_audit_build_snapshot')
			? vms_ticket_mutation_audit_build_snapshot($plan_id)
			: $before_snapshot;
		$result_status = $changed ? 'partial' : 'failed';
		$summary_text = $changed
			? __('Repair normalized stale mapping references, but the preview step could not continue cleanly.', 'vms')
			: __('Repair could not proceed safely because the preview step did not complete cleanly.', 'vms');
		if (function_exists('vms_ticket_mutation_audit_log_direct_change')) {
			vms_ticket_mutation_audit_log_direct_change(
				$plan_id,
				array(
					'change_type' => 'ticket_map_rebuilt',
					'result_status' => $result_status,
					'summary_text' => $summary_text,
					'before_snapshot' => $before_snapshot,
					'after_snapshot' => $after_snapshot,
					'source_function' => 'vms_ticket_integrity_repair_event',
					'source_hook' => sanitize_key((string) current_filter()),
				)
			);
		}

		vms_ticket_integrity_save_repair_report(
			$plan_id,
			vms_ticket_integrity_build_repair_report(
				$plan_id,
				array(
					'cfg' => $cfg,
					'preview' => $preview,
					'repair_status' => $changed ? 'partial' : 'blocked',
					'summary_text' => $summary_text,
				)
			)
		);

		return array(
			'ok' => true,
			'repair_status' => $changed ? 'partial' : 'blocked',
			'summary_text' => $summary_text,
			'changed' => $changed,
			'message' => sanitize_key((string) ($preview['message'] ?? 'preview_failed')),
		);
	}

	if (!empty($preview['blocked'])) {
		$after_snapshot = function_exists('vms_ticket_mutation_audit_build_snapshot')
			? vms_ticket_mutation_audit_build_snapshot($plan_id)
			: $before_snapshot;
		$summary_text = __('Repair could not proceed safely because the current ticket configuration is still blocked by unresolved conflicts.', 'vms');
		if (function_exists('vms_ticket_mutation_audit_log_direct_change')) {
			vms_ticket_mutation_audit_log_direct_change(
				$plan_id,
				array(
					'change_type' => 'ticket_map_rebuilt',
					'result_status' => $changed ? 'partial' : 'failed',
					'summary_text' => $summary_text,
					'before_snapshot' => $before_snapshot,
					'after_snapshot' => $after_snapshot,
					'source_function' => 'vms_ticket_integrity_repair_event',
					'source_hook' => sanitize_key((string) current_filter()),
				)
			);
		}

		vms_ticket_integrity_save_repair_report(
			$plan_id,
			vms_ticket_integrity_build_repair_report(
				$plan_id,
				array(
					'cfg' => $cfg,
					'preview' => $preview,
					'repair_status' => $changed ? 'partial' : 'blocked',
					'summary_text' => $summary_text,
				)
			)
		);

		return array(
			'ok' => true,
			'repair_status' => $changed ? 'partial' : 'blocked',
			'summary_text' => $summary_text,
			'changed' => $changed,
		);
	}

	$preview_actions = is_array($preview['actions'] ?? null) ? $preview['actions'] : array();
	$preview_change_count = 0;
	foreach ($preview_actions as $action_row) {
		if (!is_array($action_row)) {
			continue;
		}
		if (!in_array((string) ($action_row['action'] ?? 'noop'), array('noop', 'skip'), true)) {
			$preview_change_count++;
		}
	}

	$preview_id = sanitize_key((string) ($preview['preview_id'] ?? ''));
	if ($preview_id === '') {
		return array('ok' => false, 'message' => 'missing_preview_id');
	}

	$commit = vms_ticketing_v2_commit_sync($plan_id, $preview_id);
	if (empty($commit['ok'])) {
		$after_snapshot = function_exists('vms_ticket_mutation_audit_build_snapshot')
			? vms_ticket_mutation_audit_build_snapshot($plan_id)
			: $before_snapshot;
		$result_status = $changed ? 'partial' : 'failed';
		$summary_text = __('Repair attempted to normalize and commit ticket mappings, but the commit step did not finish cleanly.', 'vms');
		if (function_exists('vms_ticket_mutation_audit_log_direct_change')) {
			vms_ticket_mutation_audit_log_direct_change(
				$plan_id,
				array(
					'change_type' => 'ticket_map_rebuilt',
					'result_status' => $result_status,
					'summary_text' => $summary_text,
					'before_snapshot' => $before_snapshot,
					'after_snapshot' => $after_snapshot,
					'source_function' => 'vms_ticket_integrity_repair_event',
					'source_hook' => sanitize_key((string) current_filter()),
				)
			);
		}

		vms_ticket_integrity_save_repair_report(
			$plan_id,
			vms_ticket_integrity_build_repair_report(
				$plan_id,
				array(
					'cfg' => $cfg,
					'preview' => $preview,
					'commit' => $commit,
					'preview_change_count' => $preview_change_count,
					'repair_status' => $changed ? 'partial' : 'blocked',
					'summary_text' => $summary_text,
				)
			)
		);

		return array(
			'ok' => true,
			'repair_status' => $changed ? 'partial' : 'blocked',
			'summary_text' => $summary_text,
			'changed' => $changed,
			'message' => sanitize_key((string) ($commit['message'] ?? 'commit_failed')),
		);
	}

	$after_snapshot = function_exists('vms_ticket_mutation_audit_build_snapshot')
		? vms_ticket_mutation_audit_build_snapshot($plan_id)
		: $before_snapshot;
	$changed = $changed || (
		function_exists('vms_ticket_mutation_audit_snapshot_hash')
		&& vms_ticket_mutation_audit_snapshot_hash($before_snapshot) !== vms_ticket_mutation_audit_snapshot_hash($after_snapshot)
	);

	$repair_status = 'repaired';
	$result_status = 'success';
	$summary_text = __('Repair completed and the mapping snapshot changed.', 'vms');

	if (!$changed && $preview_change_count < 1) {
		$repair_status = 'no_changes';
		$result_status = 'no_op';
		$summary_text = __('No mapping changes were applied. The event still contains the same ticket snapshot after rebuild.', 'vms');
	}

	$diagnostic_scan = function_exists('vms_ticket_integrity_scan_event_record')
		? vms_ticket_integrity_scan_event_record($plan_id, array('trigger' => 'repair_diagnostic'))
		: array();
	$remaining_issues = is_array($diagnostic_scan['issues'] ?? null) ? $diagnostic_scan['issues'] : array();
	$remaining_open = array();
	foreach ((function_exists('vms_ticket_integrity_open_issues') ? vms_ticket_integrity_open_issues($remaining_issues) : $remaining_issues) as $issue) {
		if (!is_array($issue)) {
			continue;
		}
		$category = sanitize_key((string) ($issue['category'] ?? ''));
		if (in_array($category, array('mapping', 'structure', 'render', 'addons'), true)) {
			$remaining_open[] = $issue;
		}
	}

	if (!empty($remaining_open)) {
		$repair_status = 'partial';
		$result_status = 'partial';
		$summary_text = __('Repair attempted real mapping changes, but unresolved legacy or mapping conflicts still remain.', 'vms');
	}

	$repair_report = vms_ticket_integrity_build_repair_report(
		$plan_id,
		array(
			'cfg' => $cfg,
			'preview' => $preview,
			'commit' => $commit,
			'preview_change_count' => $preview_change_count,
			'diagnostic_scan' => $diagnostic_scan,
			'repair_status' => $repair_status,
			'summary_text' => $summary_text,
		)
	);
	if (($repair_report['detail_state'] ?? '') === 'addons_only_changed') {
		$repair_status = 'partial';
		$result_status = 'partial';
		$summary_text = __('Inventory values for add-ons were updated, but admission-ticket stock could not be safely recalculated. The event may still appear sold out.', 'vms');
		$repair_report['repair_status'] = $repair_status;
		$repair_report['repair_status_label'] = vms_ticket_integrity_repair_status_label($repair_status);
		$repair_report['summary_text'] = $summary_text;
	} else {
		$total_attempted = 0;
		$total_succeeded = 0;
		$total_skipped = 0;
		$total_no_effect = 0;
		$total_partial = 0;
		$total_failed = 0;
		foreach ((array) ($repair_report['role_breakdown'] ?? array()) as $role_group) {
			if (!is_array($role_group)) {
				continue;
			}

			$total_attempted += absint($role_group['attempted'] ?? 0);
			$total_succeeded += absint($role_group['succeeded'] ?? 0);
			$total_skipped += absint($role_group['skipped'] ?? 0);
			$total_no_effect += absint($role_group['no_effect'] ?? 0);
			$total_partial += absint($role_group['partial'] ?? 0);
			$total_failed += absint($role_group['failed'] ?? 0);
		}

		if (
			$repair_status === 'repaired'
			&& $total_attempted > 0
			&& (
				$total_failed > 0
				|| $total_partial > 0
				|| $total_skipped > 0
				|| ($total_succeeded > 0 && $total_no_effect > 0)
			)
		) {
			$repair_status = 'partial_changes';
			$result_status = 'partial';
			$summary_text = __('Repair made partial changes. One or more ticket roles were skipped, unchanged, or still need review.', 'vms');
			$repair_report['repair_status'] = $repair_status;
			$repair_report['repair_status_label'] = vms_ticket_integrity_repair_status_label($repair_status);
			$repair_report['summary_text'] = $summary_text;
		}
	}

	vms_ticket_integrity_save_repair_report($plan_id, $repair_report);

	if (function_exists('vms_ticket_mutation_audit_log_direct_change')) {
		vms_ticket_mutation_audit_log_direct_change(
			$plan_id,
			array(
				'change_type' => 'ticket_map_rebuilt',
				'result_status' => $result_status,
				'summary_text' => $summary_text,
				'before_snapshot' => $before_snapshot,
				'after_snapshot' => $after_snapshot,
				'source_function' => 'vms_ticket_integrity_repair_event',
				'source_hook' => sanitize_key((string) current_filter()),
			)
		);
	}

	return array(
		'ok' => true,
		'repair_status' => $repair_status,
		'summary_text' => $summary_text,
		'changed' => $changed,
		'preview_change_count' => $preview_change_count,
		'normalization' => $normalization,
		'commit' => $commit,
		'diagnostic_scan' => $diagnostic_scan,
		'repair_report' => $repair_report,
	);
}
