<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$eventPlans = (string) file_get_contents($root . '/includes/cpt/event-plans.php');
$shell = (string) file_get_contents($root . '/assets/js/vms-event-plan-shell.js');
$secondary = (string) file_get_contents($root . '/assets/js/vms-event-plan-secondary-vendors.js');
$ticketing = (string) file_get_contents($root . '/assets/admin-ticketing.js');
$workspaceStatus = (string) file_get_contents($root . '/includes/cpt/event-plans/partials/workspace-status.php');
$workflow = (string) file_get_contents($root . '/includes/cpt/event-plans/partials/workflow-status.php');

function __($text, $domain = null): string { return (string) $text; }
function sanitize_key($value): string { return strtolower((string) preg_replace('/[^a-z0-9_\-]/i', '', (string) $value)); }

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
    $assert(strpos($eventPlans, "'code' => 'section_save_incomplete'") !== false && strpos($eventPlans, "'verified' => true") !== false, 'Scoped saves must fail closed unless the canonical handler reaches verified completion.');
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
        "window.addEventListener('beforeunload'",
    ) as $marker) {
        $assert(strpos($shell, $marker) !== false, 'Workspace shell is missing acceptance marker: ' . $marker);
    }
    $assert(strpos($shell, 'if (activeSection && sectionDirty(activeSection.querySelector') !== false, 'Workflow actions must be blocked by active unsaved edits.');
    $assert(substr_count($shell, "workflowParams.set('action', 'vms_event_plan_workflow_action')") === 1, 'Workflow actions must use the dedicated saved-state endpoint.');
    $assert(strpos($eventPlans, 'event_plan_saved_workflow_request') !== false, 'Workflow endpoint must rebuild its request from saved state.');
    $assert(strpos($eventPlans, "array('mark_ready', 'publish_now', 'retry_publish')") !== false, 'Saved-state workflow endpoint must expose non-destructive actions without bypassing cancellation safeguards.');
    $assert(strpos($workspaceStatus, 'data-vms-open-section="cancellation"') !== false, 'Status header must route cancellation through its guarded section.');
    $assert(strpos($workflow, 'value="mark_cancelled"') !== false, 'Cancellation section must retain the canonical guarded transition.');
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
