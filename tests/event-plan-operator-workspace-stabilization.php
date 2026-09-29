<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$eventPlans = (string) file_get_contents($root . '/includes/cpt/event-plans.php');
$shell = (string) file_get_contents($root . '/assets/js/vms-event-plan-shell.js');
$secondary = (string) file_get_contents($root . '/assets/js/vms-event-plan-secondary-vendors.js');
$ticketing = (string) file_get_contents($root . '/assets/admin-ticketing.js');
$compensation = (string) file_get_contents($root . '/includes/cpt/event-plans/partials/compensation.php');
$workspaceStatus = (string) file_get_contents($root . '/includes/cpt/event-plans/partials/workspace-status.php');
$workflow = (string) file_get_contents($root . '/includes/cpt/event-plans/partials/workflow-status.php');

function __($text, $domain = null): string { return (string) $text; }
function sanitize_key($value): string { return strtolower((string) preg_replace('/[^a-z0-9_\-]/i', '', (string) $value)); }
function sanitize_text_field($value): string { return trim(strip_tags((string) $value)); }
function esc_url_raw($value, $protocols = null): string {
    unset($protocols);
    $value = trim((string) $value);
    return filter_var($value, FILTER_VALIDATE_URL) && preg_match('/^https?:\/\//i', $value) ? $value : '';
}
function bvmgr_event_plan_sanitize_external_ticket_url($value): string { return esc_url_raw($value); }
function bvmgr_event_plan_sanitize_external_event_producer_website($value): string { return esc_url_raw($value); }

$GLOBALS['vms_workspace_test_meta'] = array();
function get_post_meta($postId, $key, $single = false) {
    $value = $GLOBALS['vms_workspace_test_meta'][(int) $postId][(string) $key] ?? '';
    return $single ? $value : ($value === '' ? array() : array($value));
}
function bvmgr_event_plan_review_lineup_signature(array $rows): array {
    return array_values(array_map(static function (array $row): string {
        return implode('|', array(
            (string) ($row['role'] ?? ''),
            (string) ($row['vendor_id'] ?? 0),
            (string) ($row['set_start'] ?? ''),
            (string) ($row['set_end'] ?? ''),
        ));
    }, $rows));
}
function bvmgr_get_event_plan_deposit_terms(int $postId): array {
    return (array) ($GLOBALS['vms_workspace_test_meta'][$postId]['deposit_terms'] ?? array());
}
function bvmgr_get_event_plan_final_payment_terms(int $postId): array {
    return (array) ($GLOBALS['vms_workspace_test_meta'][$postId]['final_payment_terms'] ?? array());
}

$extractFunction = static function (string $source, string $name): string {
    $start = strpos($source, 'function ' . $name . '(');
    $brace = $start === false ? false : strpos($source, '{', $start);
    if ($start === false || $brace === false) {
        throw new RuntimeException('Unable to find function ' . $name . '.');
    }
    $depth = 1;
    for ($index = $brace + 1, $length = strlen($source); $index < $length; $index++) {
        $depth += $source[$index] === '{' ? 1 : 0;
        $depth -= $source[$index] === '}' ? 1 : 0;
        if ($depth === 0) {
            return substr($source, $start, ($index - $start) + 1);
        }
    }
    throw new RuntimeException('Unable to parse function ' . $name . '.');
};

eval($extractFunction($eventPlans, 'bvmgr_event_plan_section_registry'));
eval($extractFunction($eventPlans, 'bvmgr_event_plan_normalize_save_scope'));
eval($extractFunction($eventPlans, 'bvmgr_event_plan_filter_section_request'));
eval($extractFunction($eventPlans, 'bvmgr_event_plan_scoped_save_comparable_value'));
eval($extractFunction($eventPlans, 'bvmgr_event_plan_normalize_explicit_boolean'));
eval($extractFunction($eventPlans, 'bvmgr_event_plan_resolve_auto_comp_value'));
eval($extractFunction($eventPlans, 'bvmgr_event_plan_normalize_attendance_bonus_step_request'));
eval($extractFunction($eventPlans, 'bvmgr_event_plan_preflight_scoped_save_request'));
eval($extractFunction($eventPlans, 'bvmgr_event_plan_verify_scoped_save_postcondition'));

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

try {
    foreach (array('basics', 'schedule', 'compensation', 'secondary_vendors', 'staff', 'ticketing_v2', 'cancellation', 'readiness_details', 'advanced') as $section) {
        $assert(strpos($eventPlans, "'" . $section . "' => array(") !== false, 'Missing canonical workspace section: ' . $section);
    }

    $staffRegistryStart = strpos($eventPlans, "'staff' => array(");
    $ticketRegistryStart = strpos($eventPlans, "'ticketing_v2' => array(", $staffRegistryStart);
    $staffRegistry = substr($eventPlans, $staffRegistryStart, $ticketRegistryStart - $staffRegistryStart);
    $assert(strpos($staffRegistry, 'vms_staff_assignments') !== false, 'Staff scope must own Staffing fields.');
    $assert(strpos($staffRegistry, 'vms_comp_structure') === false, 'Staff scope must not own Compensation fields.');
    $assert(strpos($staffRegistry, 'vms_event_date') === false, 'Staff scope must not own Event Details fields.');

    $compRegistryStart = strpos($eventPlans, "'compensation' => array(");
    $vendorRegistryStart = strpos($eventPlans, "'secondary_vendors' => array(", $compRegistryStart);
    $compRegistry = substr($eventPlans, $compRegistryStart, $vendorRegistryStart - $compRegistryStart);
    $assert(strpos($compRegistry, 'vms_comp_structure') !== false, 'Compensation scope must own pay fields.');
    $assert(strpos($compRegistry, 'vms_staff_assignments') === false, 'Compensation scope must not own Staffing fields.');
    $assert(strpos($eventPlans, 'bvmgr_event_plan_filter_section_request($scope, $request)') !== false, 'Scoped endpoint must apply the canonical field whitelist.');
    $assert(strpos($eventPlans, "'code' => 'section_save_incomplete'") !== false && strpos($eventPlans, 'bvmgr_event_plan_verify_scoped_save_postcondition') !== false, 'Scoped saves must fail closed unless their persisted postconditions verify.');
    $assert(strpos($eventPlans, "empty(\$result['ok']) || empty(\$result['verified'])") !== false, 'Scoped endpoint must require an authoritative verified result.');
    $assert(strpos($eventPlans, '$save_compensation_scope = !$section_scoped_save || $section_save_scope === \'compensation\';') !== false, 'Legacy authority must gate compensation writes for scoped saves.');
    $assert(strpos($eventPlans, '$save_cancellation_scope = !$section_scoped_save || $section_save_scope === \'cancellation\';') !== false, 'Legacy authority must gate cancellation writes for scoped saves.');
    $assert(strpos($eventPlans, 'bvmgr_staffing_save_event_plan_section') !== false, 'Staffing scoped saves must retain the Phase B atomic authority.');
    $assert(strpos($eventPlans, 'bvmgr_event_plan_save_secondary_vendors_module') !== false, 'Additional Vendors must retain its isolated save authority.');

    $crossSectionRequest = array(
        'vms_staff_assignments_present' => '1',
        'vms_staff_assignments' => array(8 => array(19)),
        'vms_comp_structure' => 'flat_fee',
        'vms_flat_fee_amount' => '1500',
        'vms_event_date' => '2026-10-10',
        'vms_start_time' => '20:00',
    );
    $staffRequest = bvmgr_event_plan_filter_section_request('staff', $crossSectionRequest);
    $assert(isset($staffRequest['vms_staff_assignments']) && !isset($staffRequest['vms_comp_structure']) && !isset($staffRequest['vms_event_date']), 'Staff scope must discard Compensation and Event Details mutations.');
    $compRequest = bvmgr_event_plan_filter_section_request('compensation', $crossSectionRequest);
    $assert(isset($compRequest['vms_comp_structure']) && !isset($compRequest['vms_staff_assignments']) && !isset($compRequest['vms_event_date']), 'Compensation scope must discard Staffing and Event Details mutations.');
    $basicsRequest = bvmgr_event_plan_filter_section_request('basics', $crossSectionRequest);
    $assert(isset($basicsRequest['vms_event_date']) && !isset($basicsRequest['vms_start_time']) && !isset($basicsRequest['vms_comp_structure']), 'Event Details scope must discard Schedule and Compensation mutations.');

    $invalidTicketPreflight = bvmgr_event_plan_preflight_scoped_save_request('ticketing_v2', array(
        'vms_ticketing_sales_mode' => 'external',
        'vms_external_ticket_url' => 'javascript:alert(1)',
        'vms_external_ticket_provider' => 'Changed provider',
    ));
    $assert(empty($invalidTicketPreflight['ok']) && strpos((string) $invalidTicketPreflight['message'], 'external ticket URL') !== false, 'Ticketing must reject an invalid external URL before the writer runs.');
    $validTicketPreflight = bvmgr_event_plan_preflight_scoped_save_request('ticketing_v2', array(
        'vms_external_ticket_url' => 'https://tickets.example.test/show',
        'vms_external_event_producer_website' => 'https://producer.example.test/',
    ));
    $assert(!empty($validTicketPreflight['ok']) && ($validTicketPreflight['normalized']['external_ticket_url'] ?? '') === 'https://tickets.example.test/show', 'Valid Ticketing URLs must reach the writer in canonical form.');
    $invalidCompPreflight = bvmgr_event_plan_preflight_scoped_save_request('compensation', array(
        'vms_comp_structure' => 'attendance_bonus',
        'vms_attendance_bonus_mode' => 'step',
        'vms_attendance_bonus_step_size' => '0',
    ));
    $assert(empty($invalidCompPreflight['ok']) && strpos((string) $invalidCompPreflight['message'], 'Step Size') !== false, 'Compensation must reject an invalid Attendance Bonus step size before the writer runs.');
    $validCompPreflight = bvmgr_event_plan_preflight_scoped_save_request('compensation', array(
        'vms_comp_structure' => 'attendance_bonus',
        'vms_attendance_bonus_mode' => 'step',
        'vms_attendance_bonus_step_size' => '10.9',
    ));
    $assert(!empty($validCompPreflight['ok']) && ($validCompPreflight['normalized']['attendance_bonus']['step_size'] ?? 0) === 10, 'Valid Compensation step size must reuse the writer canonicalization.');
    $preflightPosition = strpos($eventPlans, 'bvmgr_event_plan_preflight_scoped_save_request($section_save_scope, $request)');
    $firstWriterPosition = strpos($eventPlans, '$original_status =', $preflightPosition);
    $assert($preflightPosition !== false && $firstWriterPosition !== false && $preflightPosition < $firstWriterPosition, 'Scoped validation must run before writer-side state capture and mutation.');

    $GLOBALS['vms_workspace_test_meta'][601]['_vms_auto_comp'] = '1';
    $assert(bvmgr_event_plan_resolve_auto_comp_value(601, array()) === '1', 'Absent automatic-compensation input must preserve enabled state.');
    $GLOBALS['vms_workspace_test_meta'][601]['_vms_auto_comp'] = '0';
    $assert(bvmgr_event_plan_resolve_auto_comp_value(601, array()) === '0', 'Absent automatic-compensation input must preserve disabled state.');
    $assert(bvmgr_event_plan_resolve_auto_comp_value(602, array()) === '1', 'Never-stored automatic-compensation state must retain its enabled semantic default.');
    $assert(bvmgr_event_plan_resolve_auto_comp_value(601, array('vms_auto_comp' => '0')) === '0', 'Explicit zero must disable automatic compensation.');
    $assert(bvmgr_event_plan_resolve_auto_comp_value(601, array('vms_auto_comp' => '1')) === '1', 'Explicit one must enable automatic compensation.');
    $assert(bvmgr_event_plan_resolve_auto_comp_value(601, array('vms_auto_comp' => 'off')) === '0', 'Explicit off must disable automatic compensation.');
    $assert(strpos($eventPlans, 'name="vms_auto_comp"') === false && strpos($compensation, 'name="vms_auto_comp"') === false, 'The current Event Plan UI must not imply an absent automatic-compensation checkbox submission.');
    $assert(strpos($compensation, 'name="vms_auto_comp_venue"') !== false, 'The rendered venue automatic-compensation checkbox must remain intact.');
    $assert(strpos($eventPlans, "\$save_compensation_scope && \$auto_comp === '1' && function_exists('bvmgr_maybe_apply_band_comp_defaults_to_plan')") !== false, 'Preserved enabled automatic compensation must retain its canonical behavior.');

    $planId = 501;
    $GLOBALS['vms_workspace_test_meta'][$planId] = array(
        '_vms_event_date' => '2026-10-10',
        '_vms_venue_id' => '24',
        '_vms_auto_title' => '1',
    );
    $verifiedBasics = bvmgr_event_plan_verify_scoped_save_postcondition($planId, 'basics', array(
        'meta' => array('_vms_event_date' => '2026-10-10', '_vms_venue_id' => 24, '_vms_auto_title' => '1'),
        'labels' => array('_vms_event_date' => 'Event Date'),
    ));
    $assert(!empty($verifiedBasics['ok']) && !empty($verifiedBasics['verified']), 'Successful Event Details state must verify.');

    $blockedDate = bvmgr_event_plan_verify_scoped_save_postcondition($planId, 'basics', array(
        'meta' => array('_vms_event_date' => '2026-10-11'),
        'labels' => array('_vms_event_date' => 'Event Date'),
    ));
    $assert(empty($blockedDate['ok']) && empty($blockedDate['verified']) && strpos((string) $blockedDate['message'], 'Event Date') !== false, 'A rejected protected Event Date must not verify as saved.');

    $invalidExternalUrl = bvmgr_event_plan_verify_scoped_save_postcondition($planId, 'ticketing_v2', array('meta' => array()), array(
        'Ticketing was not saved because the external ticket URL is invalid.',
    ));
    $assert(empty($invalidExternalUrl['ok']) && empty($invalidExternalUrl['verified']) && strpos((string) $invalidExternalUrl['message'], 'external ticket URL') !== false, 'An invalid external URL must not verify as Ticketing saved.');

    $GLOBALS['vms_workspace_test_meta'][$planId]['_vms_ticketing_sales_mode'] = 'external';
    $GLOBALS['vms_workspace_test_meta'][$planId]['_vms_external_ticket_url'] = 'https://tickets.example.test/show';
    $verifiedTicketing = bvmgr_event_plan_verify_scoped_save_postcondition($planId, 'ticketing_v2', array(
        'meta' => array(
            '_vms_ticketing_sales_mode' => 'external',
            '_vms_external_ticket_url' => 'https://tickets.example.test/show',
        ),
    ));
    $assert(!empty($verifiedTicketing['ok']) && !empty($verifiedTicketing['verified']), 'Successful Ticketing destination state must verify.');

    $GLOBALS['vms_workspace_test_meta'][$planId]['_vms_start_time'] = '19:00';
    $GLOBALS['vms_workspace_test_meta'][$planId]['_vms_end_time'] = '22:00';
    $GLOBALS['vms_workspace_test_meta'][$planId]['_vms_band_vendor_id'] = '77';
    $GLOBALS['vms_workspace_test_meta'][$planId]['_vms_lineup_entries_v1'] = array(
        array('role' => 'primary', 'vendor_id' => 77, 'set_start' => '19:00', 'set_end' => '22:00'),
    );
    $verifiedSchedule = bvmgr_event_plan_verify_scoped_save_postcondition($planId, 'schedule', array(
        'meta' => array('_vms_start_time' => '19:00', '_vms_end_time' => '22:00', '_vms_band_vendor_id' => 77),
        'lineup_signature' => array('primary|77|19:00|22:00'),
    ));
    $assert(!empty($verifiedSchedule['ok']) && !empty($verifiedSchedule['verified']), 'Successful Schedule & Lineup state must verify.');

    $GLOBALS['vms_workspace_test_meta'][$planId]['_vms_comp_structure'] = 'flat_fee';
    $GLOBALS['vms_workspace_test_meta'][$planId]['_vms_flat_fee_amount'] = '1500';
    $GLOBALS['vms_workspace_test_meta'][$planId]['deposit_terms'] = array(
        'deposit_amount' => '250', 'deposit_status' => 'unpaid', 'deposit_treatment' => 'creditable',
        'deposit_due_date' => '2026-10-01', 'deposit_paid_date' => '', 'deposit_notes' => '',
    );
    $GLOBALS['vms_workspace_test_meta'][$planId]['final_payment_terms'] = array(
        'final_payment_timing' => 'day_of_event', 'final_payment_days_after' => '', 'final_payment_date' => '',
        'final_payment_custom_text' => '', 'final_payment_method' => 'check', 'final_payment_method_other' => '',
    );
    $verifiedCompensation = bvmgr_event_plan_verify_scoped_save_postcondition($planId, 'compensation', array(
        'meta' => array('_vms_comp_structure' => 'flat_fee', '_vms_flat_fee_amount' => 1500.0),
        'deposit_terms' => $GLOBALS['vms_workspace_test_meta'][$planId]['deposit_terms'],
        'final_payment_terms' => $GLOBALS['vms_workspace_test_meta'][$planId]['final_payment_terms'],
    ));
    $assert(!empty($verifiedCompensation['ok']) && !empty($verifiedCompensation['verified']), 'Successful Compensation state must verify.');

    $GLOBALS['vms_workspace_test_meta'][$planId]['_vms_cancel_policy'] = 'status_only';
    $GLOBALS['vms_workspace_test_meta'][$planId]['_vms_cancel_reason_code'] = 'weather';
    $GLOBALS['vms_workspace_test_meta'][$planId]['_vms_cancel_reason_note'] = 'Storm warning';
    $GLOBALS['vms_workspace_test_meta'][$planId]['_vms_cancel_vendor_message'] = 'Please stand by.';
    $verifiedCancellation = bvmgr_event_plan_verify_scoped_save_postcondition($planId, 'cancellation', array(
        'meta' => array(
            '_vms_cancel_policy' => 'status_only', '_vms_cancel_reason_code' => 'weather',
            '_vms_cancel_reason_note' => 'Storm warning', '_vms_cancel_vendor_message' => 'Please stand by.',
        ),
    ));
    $assert(!empty($verifiedCancellation['ok']) && !empty($verifiedCancellation['verified']), 'Successful Cancellation saved fields must verify.');

    foreach (array(
        'editableSectionKeys',
        'if (activeSection && activeSection !== section)',
        'showDirtyPrompt(activeSection, section)',
        'Save & Open ',
        'Discard Changes & Open ',
        'Stay Here',
        "setSectionStatus(section, 'Saving…', 'saving')",
        "setSectionStatus(section, 'Save failed', 'failed')",
        'await openSection(target, true)',
        'Save &amp; Continue',
        "window.addEventListener('beforeunload'",
    ) as $marker) {
        $assert(strpos($shell, $marker) !== false, 'Workspace shell is missing acceptance marker: ' . $marker);
    }
    $assert(strpos($shell, 'if (activeSection && sectionDirty(activeSection.querySelector') !== false, 'Workflow actions must be blocked by active unsaved edits.');
    $assert(strpos($shell, 'resetSectionBaseline(section, true)') !== false, 'Successful section saves must reset only persisted-control baselines.');
    $assert(strpos($shell, 'sectionTransientDirty') !== false && strpos($shell, 'Unsaved action input') !== false, 'Transient cancellation action intent must remain visibly dirty after Save Changes.');
    $assert(strpos($shell, 'workflowActionConsumesTransient') !== false && strpos($shell, "'mark_cancelled', 'create_rescheduled_draft', 'retry_cancellation_all'") !== false, 'Guarded Cancellation actions must be able to consume transient inputs.');
    $assert(strpos($shell, 'await openSection(target, true)') !== false && strpos($shell, 'Action-only cancellation input remains unsaved') !== false, 'Save & Continue must stop before navigation when transient action intent remains.');
    $assert(substr_count($shell, "workflowParams.set('action', 'vms_event_plan_workflow_action')") === 1, 'Workflow actions must use the dedicated saved-state endpoint.');
    $assert(strpos($eventPlans, 'event_plan_saved_workflow_request') !== false, 'Workflow endpoint must rebuild its request from saved state.');
    $assert(strpos($eventPlans, "array('mark_ready', 'publish_now', 'retry_publish')") !== false, 'Saved-state workflow endpoint must expose non-destructive actions without bypassing cancellation safeguards.');
    $assert(strpos($workspaceStatus, 'data-vms-open-section="cancellation"') !== false, 'Status header must route cancellation through its guarded section.');
    $assert(strpos($workflow, 'value="mark_cancelled"') !== false, 'Cancellation section must retain the canonical guarded transition.');
    $assert(substr_count($workflow, 'data-vms-transient-action-control="1"') >= 2, 'Replacement date and refund confirmation must be marked as transient action controls.');
    $assert(strpos($workflow, 'Save Changes does not save them as Event Plan settings') !== false, 'Cancellation UI must explain the transient action-only contract.');
    $assert(strpos($eventPlans, "bvmgr_event_plan_schedule_deferred_calendar_publish(\$post_id, 'retry')") !== false, 'Retry Publishing must use the Phase A idempotent scheduler.');

    foreach (array('workflow_label', 'calendar_label', 'tec_label', 'ticketing_label', 'staffing_label', 'blocking_issue_count') as $statusField) {
        $assert(strpos($workspaceStatus, "workspace_status['" . $statusField . "']") !== false, 'Status header is missing: ' . $statusField);
    }
    $assert(strpos($eventPlans, "!empty(\$health['valid_queued']) || !empty(\$health['valid_running'])") !== false, 'Publishing state must use Phase A objective queue health.');
    $assert(strpos($workspaceStatus, 'Retry Publishing') !== false, 'Interrupted publication must expose Retry Publishing.');
    $assert(strpos($workspaceStatus, "disabled(!in_array") !== false && strpos($workspaceStatus, "!empty(\$workspace_status['publishing'])") !== false, 'Valid in-progress publication must disable duplicate Publish.');

    $assert(strpos($shell, "params.set('action', 'vms_load_event_plan_admin_section')") !== false, 'Lazy section loading must remain in the workspace shell.');
    $assert(strpos($secondary, "section: 'secondary_vendors', ok: true") !== false, 'Additional Vendors must report verified save completion to the shell.');
    $assert(strpos($ticketing, "section: 'ticketing_v2', ok: true") !== false, 'Ticketing Save Config must report verified save completion to the shell.');
    $assert(strpos($ticketing, 'vms-ticketing-v2-preview-sync-btn') !== false && strpos($ticketing, 'vms-ticketing-v2-commit-sync-btn') !== false, 'Ticketing Preview/Commit controls must remain intact.');
    $assert(strpos($workflow, 'Save Draft') === false, 'Ambiguous global Save Draft must be removed from normal workspace use.');
    $assert(stripos($eventPlans, 'staff tasks migration') === false, 'Staff Tasks migration notice must remain absent from Event Plans.');

    fwrite(STDOUT, "event plan operator workspace stabilization: PASS\n");
} catch (Throwable $e) {
    fwrite(STDERR, 'event plan operator workspace stabilization: FAIL - ' . $e->getMessage() . "\n");
    exit(1);
}
