<?php
declare(strict_types=1);

$repoRoot = dirname(__DIR__);
$staffingSource = (string) file_get_contents($repoRoot . '/includes/core/staffing.php');
$lifecycleUiSource = (string) file_get_contents($repoRoot . '/includes/core/staffing-lifecycle-ui.php');
$eventPlanSource = (string) file_get_contents($repoRoot . '/includes/cpt/event-plans.php');
$tasksUiSource = (string) file_get_contents($repoRoot . '/includes/modules/staff-tasks/authority-ui.php');

$assert = static function (bool $condition, string $message): void {
	if (!$condition) throw new RuntimeException($message);
};

$extractFunction = static function (string $source, string $name): string {
	$start = strpos($source, 'function ' . $name . '(');
	$brace = $start === false ? false : strpos($source, '{', $start);
	if ($start === false || $brace === false) throw new RuntimeException('Unable to find function ' . $name . '.');
	$depth = 1;
	for ($index = $brace + 1, $length = strlen($source); $index < $length; $index++) {
		$depth += $source[$index] === '{' ? 1 : 0;
		$depth -= $source[$index] === '}' ? 1 : 0;
		if ($depth === 0) return substr($source, $start, ($index - $start) + 1);
	}
	throw new RuntimeException('Unable to parse function ' . $name . '.');
};

function absint($value): int { return abs((int) $value); }
function sanitize_key($value): string { return strtolower((string) preg_replace('/[^a-z0-9_\-]/i', '', (string) $value)); }
function wp_json_encode($value): string { return (string) json_encode($value); }
function get_current_user_id(): int { return 17; }
function __($text, $domain = null): string { return (string) $text; }
function wp_unslash($value) { return $value; }
function sanitize_text_field($value): string { return trim(strip_tags((string) $value)); }
function esc_html__($text, $domain = null): string { return (string) $text; }
function esc_html($text): string { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
function esc_url($url): string { return (string) $url; }
function admin_url($path = ''): string { return 'https://example.test/wp-admin/' . ltrim((string) $path, '/'); }
function wp_nonce_field($action): void { echo '<input data-nonce="' . esc_html((string) $action) . '">'; }

$GLOBALS['test_slots'] = array();
$GLOBALS['test_thresholds'] = array();
$GLOBALS['test_templates'] = array();
$GLOBALS['test_matrix_error'] = '';
$GLOBALS['test_template_error'] = '';
$GLOBALS['test_verification_mismatch'] = false;
$GLOBALS['test_atomic_calls'] = 0;
$GLOBALS['test_matrix_calls'] = 0;
$GLOBALS['test_template_calls'] = 0;

function bvmgr_staffing_atomic(callable $operation): array
{
	$GLOBALS['test_atomic_calls']++;
	$snapshot = array(
		'slots' => $GLOBALS['test_slots'],
		'thresholds' => $GLOBALS['test_thresholds'],
		'templates' => $GLOBALS['test_templates'],
	);
	$GLOBALS['bvmgr_staffing_transaction'] = array('plans' => array(), 'events' => array(), 'saved_events' => array());
	try {
		$result = $operation();
	} catch (Throwable $e) {
		$result = array('ok' => false, 'error' => 'database_error');
	}
	unset($GLOBALS['bvmgr_staffing_transaction']);
	if (empty($result['ok'])) {
		$GLOBALS['test_slots'] = $snapshot['slots'];
		$GLOBALS['test_thresholds'] = $snapshot['thresholds'];
		$GLOBALS['test_templates'] = $snapshot['templates'];
		$result['rolled_back'] = true;
	}
	return $result;
}
function bvmgr_staffing_save_event_roles_matrix(
	int $eventPlanId,
	array $headcounts,
	array $assignments,
	array $timeModes = array(),
	array $shiftStarts = array(),
	array $shiftEnds = array(),
	array $startAnchorKeys = array(),
	array $startOffsetMinutes = array(),
	array $endAnchorKeys = array(),
	array $endOffsetMinutes = array(),
	array $durationMinutes = array(),
	?int $actorUserId = null,
	array $precomputedState = array()
): array {
	$GLOBALS['test_matrix_calls']++;
	$GLOBALS['test_slots'][$eventPlanId] = $precomputedState['desired_signature'] ?? array();
	if ($GLOBALS['test_matrix_error'] !== '') return array('ok' => false, 'error' => $GLOBALS['test_matrix_error']);
	return array('ok' => true, 'slot_count' => count($GLOBALS['test_slots'][$eventPlanId]));
}
function bvmgr_staffing_set_event_role_activation_thresholds(int $eventPlanId, array $thresholds): void
{
	$GLOBALS['test_thresholds'][$eventPlanId] = $thresholds;
}
function bvmgr_staffing_get_event_role_activation_thresholds(int $eventPlanId): array
{
	return $GLOBALS['test_thresholds'][$eventPlanId] ?? array();
}
function bvmgr_staffing_apply_template_to_event(int $eventPlanId, int $templateId, string $mode = 'merge_missing', ?int $actorUserId = null): array
{
	$GLOBALS['test_template_calls']++;
	$GLOBALS['test_templates'][$eventPlanId] = $templateId;
	if ($GLOBALS['test_template_error'] !== '') return array('ok' => false, 'error' => $GLOBALS['test_template_error']);
	return array('ok' => true, 'template_id' => $templateId, 'seeded' => 2, 'skipped' => 1, 'mode' => $mode);
}
function bvmgr_staffing_get_event_applied_template_id(int $eventPlanId): int
{
	return (int) ($GLOBALS['test_templates'][$eventPlanId] ?? 0);
}
function bvmgr_staffing_get_event_slots(int $eventPlanId, bool $includeCanceled = false): array
{
	return $GLOBALS['test_slots'][$eventPlanId] ?? array();
}
function bvmgr_staffing_current_event_roles_matrix_signature(array $slots, array $roleIds): array
{
	return !empty($GLOBALS['test_verification_mismatch']) ? array('mismatch' => true) : $slots;
}

eval($extractFunction($staffingSource, 'bvmgr_staffing_verify_event_plan_section_save'));
eval($extractFunction($staffingSource, 'bvmgr_staffing_save_event_plan_section'));
eval($extractFunction($lifecycleUiSource, 'bvmgr_staffing_lifecycle_message'));

$reset = static function (): void {
	$GLOBALS['test_slots'] = array(44 => array('existing' => array('status' => 'active', 'staff_ids' => array(9))));
	$GLOBALS['test_thresholds'] = array(44 => array(8 => 1));
	$GLOBALS['test_templates'] = array(44 => 3);
	$GLOBALS['test_matrix_error'] = '';
	$GLOBALS['test_template_error'] = '';
	$GLOBALS['test_verification_mismatch'] = false;
	$GLOBALS['test_atomic_calls'] = 0;
	$GLOBALS['test_matrix_calls'] = 0;
	$GLOBALS['test_template_calls'] = 0;
};

$baseInput = static function (array $state): array {
	return array(
		'headcounts' => array(8 => 2),
		'assignments' => array(8 => array(9, 10)),
		'time_modes' => array(8 => 'relative'),
		'start_anchor_keys' => array(8 => 'event_start'),
		'start_offset_minutes' => array(8 => -60),
		'duration_minutes' => array(8 => 240),
		'activation_thresholds' => array(8 => 25),
		'template_apply_now' => !empty($state['template_apply_requested']),
		'template_id' => (int) ($state['template_id'] ?? 0),
		'template_mode' => 'merge_missing',
		'request_state' => $state,
	);
};

$desired = array(
	8 => array(
		'headcount_needed' => 2,
		'staff_ids' => array(9, 10),
		'shift_time_mode' => 'relative',
		'start_anchor_key' => 'event_start',
		'start_offset_minutes' => -60,
		'duration_minutes' => 240,
	),
);
$matrixState = array(
	'has_staffing_change' => true,
	'matrix_dirty' => true,
	'thresholds_dirty' => true,
	'template_apply_requested' => false,
	'role_ids' => array(8),
	'desired_signature' => $desired,
	'desired_thresholds' => array(8 => 25),
);

$reset();
$result = bvmgr_staffing_save_event_plan_section(44, $baseInput($matrixState));
$assert(!empty($result['ok']) && !empty($result['verified']), 'Matrix/headcount/timing/threshold save should commit only after verification.');
$assert($GLOBALS['test_slots'][44] === $desired, 'Successful save should persist the intended matrix, headcount, and timing signature.');
$assert($GLOBALS['test_thresholds'][44] === array(8 => 25), 'Successful save should persist activation thresholds.');
$assert($GLOBALS['test_atomic_calls'] === 1 && $GLOBALS['test_matrix_calls'] === 1, 'Complete Staffing section save should use one atomic boundary and one matrix write.');

$reset();
$before = array($GLOBALS['test_slots'], $GLOBALS['test_thresholds'], $GLOBALS['test_templates']);
$GLOBALS['test_matrix_error'] = 'database_error';
$result = bvmgr_staffing_save_event_plan_section(44, $baseInput($matrixState));
$assert(empty($result['ok']) && !empty($result['rolled_back']) && ($result['stage'] ?? '') === 'matrix', 'Matrix failure should be reported and rolled back.');
$assert(array($GLOBALS['test_slots'], $GLOBALS['test_thresholds'], $GLOBALS['test_templates']) === $before, 'Matrix failure must preserve all prior Staffing state.');
$assert($GLOBALS['test_template_calls'] === 0, 'Matrix failure must stop before template application.');

$templateState = $matrixState + array('template_id' => 12);
$templateState['template_apply_requested'] = true;
$templateState['template_id'] = 12;
$reset();
$result = bvmgr_staffing_save_event_plan_section(44, $baseInput($templateState));
$assert(!empty($result['ok']) && ($GLOBALS['test_templates'][44] ?? 0) === 12, 'Template apply should commit and verify inside the section transaction.');
$assert((int) ($result['template']['seeded'] ?? 0) === 2, 'Successful template result should remain available for the truthful success notice.');

$reset();
$before = array($GLOBALS['test_slots'], $GLOBALS['test_thresholds'], $GLOBALS['test_templates']);
$GLOBALS['test_template_error'] = 'missing_template';
$result = bvmgr_staffing_save_event_plan_section(44, $baseInput($templateState));
$assert(empty($result['ok']) && !empty($result['rolled_back']) && ($result['stage'] ?? '') === 'template', 'Template failure should roll back the complete Staffing section.');
$assert(array($GLOBALS['test_slots'], $GLOBALS['test_thresholds'], $GLOBALS['test_templates']) === $before, 'Template failure must roll back earlier matrix and threshold writes.');

$reset();
$before = array($GLOBALS['test_slots'], $GLOBALS['test_thresholds'], $GLOBALS['test_templates']);
$GLOBALS['test_matrix_error'] = 'explicit_cancellation_required';
$result = bvmgr_staffing_save_event_plan_section(44, $baseInput($matrixState));
$assert(empty($result['ok']) && $GLOBALS['test_slots'][44] === $before[0][44], 'Active assignment cancellation guard should preserve the original lifecycle state.');
$assert(strpos(bvmgr_staffing_lifecycle_message('explicit_cancellation_required'), 'Cancel active assignments') !== false, 'Explicit cancellation failure should have actionable operator copy.');

$reset();
$before = array($GLOBALS['test_slots'], $GLOBALS['test_thresholds'], $GLOBALS['test_templates']);
$GLOBALS['test_verification_mismatch'] = true;
$result = bvmgr_staffing_save_event_plan_section(44, $baseInput($matrixState));
$assert(empty($result['ok']) && ($result['error'] ?? '') === 'staffing_verification_failed', 'Unverified Staffing state should fail closed.');
$assert(array($GLOBALS['test_slots'], $GLOBALS['test_thresholds'], $GLOBALS['test_templates']) === $before, 'Verification failure should roll back the complete Staffing section.');

foreach (array('staffing_busy', 'lifecycle_migration_required', 'transactional_engine_required', 'database_error', 'staffing_verification_failed', 'duplicate_review_required', 'assignment_unavailable', 'staff_ineligible', 'stale_assignment', 'forbidden', 'invalid_request') as $knownError) {
	$assert(strpos(bvmgr_staffing_lifecycle_message($knownError), 'assignment could not be changed') === false, 'Known failure should not use generic assignment fallback: ' . $knownError);
}

$GLOBALS['test_notices'] = array();
$GLOBALS['test_meta'] = array();
$GLOBALS['test_reopen'] = array();
function bvmgr_add_admin_notice(string $message, string $type = 'success'): void { $GLOBALS['test_notices'][] = array($type, $message); }
function update_post_meta($postId, $key, $value): void { $GLOBALS['test_meta'][(int) $postId][(string) $key] = $value; }
function bvmgr_event_plan_set_runtime_reopen_section_target(int $postId, string $section): void { $GLOBALS['test_reopen'][(int) $postId] = $section; }
eval($extractFunction($eventPlanSource, 'bvmgr_event_plan_handle_staffing_section_save_result'));

$assert(!bvmgr_event_plan_handle_staffing_section_save_result(44, array('ok' => false, 'error' => 'explicit_cancellation_required')), 'Failed section result should stop the Event Plan save.');
$assert(count($GLOBALS['test_notices']) === 1 && $GLOBALS['test_notices'][0][0] === 'error', 'Failed Staffing save should emit exactly one error notice.');
$assert(strpos($GLOBALS['test_notices'][0][1], 'Cancel active assignments') !== false, 'Failed Staffing notice should include the useful lifecycle reason.');
$assert(($GLOBALS['test_meta'][44]['_vms_admin_scroll_to'] ?? '') === 'vms_staff_assignments_present', 'Failed Staffing save should retain the Staff scroll target.');
$assert(($GLOBALS['test_reopen'][44] ?? '') === 'staff', 'Failed Staffing save should reopen the Staff section.');
$noticeCount = count($GLOBALS['test_notices']);
$assert(bvmgr_event_plan_handle_staffing_section_save_result(44, array('ok' => true, 'verified' => true)), 'Verified section result should permit the Event Plan save to continue.');
$assert(count($GLOBALS['test_notices']) === $noticeCount, 'Successful section-result handling should not manufacture another notice.');

$GLOBALS['test_notices'] = array();
$workflowAdvanced = false;
if (bvmgr_event_plan_handle_staffing_section_save_result(44, array('ok' => false, 'error' => 'lifecycle_migration_required'))) {
	$workflowAdvanced = true;
}
$assert(!$workflowAdvanced, 'Lifecycle/schema failure must not advance the requested Event Plan workflow action.');
$assert(count($GLOBALS['test_notices']) === 1 && $GLOBALS['test_notices'][0][0] === 'error', 'Lifecycle/schema failure should produce one Staffing error and no success notice.');
$assert(strpos($GLOBALS['test_notices'][0][1], 'administrator completes the staffing update') !== false, 'Lifecycle/schema failure should explain the required reliability update.');

$saveCall = strpos($eventPlanSource, 'bvmgr_staffing_save_event_plan_section((int) $post_id');
$failureBoundary = strpos($eventPlanSource, 'if (!bvmgr_event_plan_handle_staffing_section_save_result', $saveCall === false ? 0 : $saveCall);
$autoBehaviors = strpos($eventPlanSource, '// Auto behaviors', $failureBoundary === false ? 0 : $failureBoundary);
$workflowSwitch = strpos($eventPlanSource, 'switch ($action)', $autoBehaviors === false ? 0 : $autoBehaviors);
$returnBeforeWorkflow = $failureBoundary === false || $autoBehaviors === false ? false : strpos(substr($eventPlanSource, $failureBoundary, $autoBehaviors - $failureBoundary), 'return;');
$assert($saveCall !== false && $failureBoundary !== false && $autoBehaviors !== false && $workflowSwitch !== false && $returnBeforeWorkflow !== false, 'Event Plan save must stop after failed Staffing persistence and before workflow/auto-success actions, while verified success can continue to the requested action.');

$GLOBALS['test_screen'] = null;
$GLOBALS['test_can_manage'] = true;
$GLOBALS['test_tasks_ready'] = false;
function get_current_screen() { return $GLOBALS['test_screen']; }
function current_user_can($capability): bool { return !empty($GLOBALS['test_can_manage']); }
function bvmgr_tasks_authority_ready(): bool { return !empty($GLOBALS['test_tasks_ready']); }
eval($extractFunction($tasksUiSource, 'bvmgr_tasks_is_relevant_admin_screen'));
eval($extractFunction($tasksUiSource, 'bvmgr_tasks_upgrade_notice'));

$_GET = array();
$GLOBALS['test_screen'] = (object) array('id' => 'vms_event_plan');
ob_start();
bvmgr_tasks_upgrade_notice();
$eventPlanNotice = (string) ob_get_clean();
$assert($eventPlanNotice === '', 'Staff Tasks reliability notice must not appear on Event Plan screens.');

$GLOBALS['test_screen'] = (object) array('id' => 'backstage-venue-manager_page_vms-settings');
ob_start();
bvmgr_tasks_upgrade_notice();
$unrelatedNotice = (string) ob_get_clean();
$assert($unrelatedNotice === '', 'Staff Tasks reliability notice must not appear on unrelated BVM screens.');

$_GET = array('page' => 'vms-tasks');
ob_start();
bvmgr_tasks_upgrade_notice();
$taskNotice = (string) ob_get_clean();
$assert(strpos($taskNotice, 'Staff Tasks requires an explicit reliability update') !== false, 'Staff Tasks reliability notice should remain visible on its relevant screens.');
$assert(strpos($taskNotice, 'Update Staff Tasks reliability') !== false, 'Staff Tasks reliability action should remain available on its relevant screens.');

fwrite(STDOUT, "event plan Staffing save stabilization: PASS\n");
