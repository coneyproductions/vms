<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$eventPlans = (string) file_get_contents($root . '/includes/cpt/event-plans.php');
$shell = (string) file_get_contents($root . '/assets/js/vms-event-plan-shell.js');
$title = (string) file_get_contents($root . '/includes/cpt/event-plans/partials/title.php');
$titleJs = (string) file_get_contents($root . '/assets/js/vms-event-plan-title.js');
$schedule = (string) file_get_contents($root . '/includes/cpt/event-plans/partials/time-lineup.php');
$compensation = (string) file_get_contents($root . '/includes/cpt/event-plans/partials/compensation.php');
$compensationJs = (string) file_get_contents($root . '/assets/js/vms-event-plan-compensation.js');
$css = (string) file_get_contents($root . '/assets/css/vms-admin.css');

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

try {
    $assert(strpos($eventPlans, "'refresh_required' => \$scope === 'basics' ? 1 : 0") !== false, 'Event Details save must request an authoritative refresh.');
    $assert(strpos($eventPlans, 'data-vms-event-details-holiday') !== false, 'Holiday output must expose its authoritative derived-state container.');
    $assert(strpos($eventPlans, 'Save Event Details to run authoritative holiday checks.') !== false, 'Unsaved Event Details must explain that holiday checks require a save.');
    $assert(strpos($eventPlans, 'No holiday is configured for this venue on the selected date.') !== false, 'Saved dates with no holiday must retain an affirmative result.');
    $assert(strpos($eventPlans, "esc_html__('CLOSED'") !== false && strpos($eventPlans, "esc_html__('OPEN'") !== false, 'Holiday results must retain OPEN/CLOSED outcomes.');

    $assert(strpos($schedule, 'data-vms-schedule-date-status') !== false, 'Schedule must expose date-dependent availability state.');
    $assert(strpos($schedule, 'Save Event Details to check vendor availability.') !== false, 'Unsaved Event Date must direct the operator to save Event Details.');
    $assert(strpos($schedule, 'Set the Event Date to see vendor availability hints here.') === false, 'Stale initial-render availability copy must be removed.');
    $assert(strpos($shell, 'function updateEventDetailsDerivedState()') !== false, 'Workspace must invalidate derived Event Details and Schedule state while date/venue inputs are unsaved.');
    $assert(strpos($shell, 'refreshRequired: !!(payload.data && payload.data.refresh_required)') !== false, 'Workspace must honor the authoritative refresh response.');

    $assert(strpos($titleJs, "if (!String(opt.value || '').trim()) return '';") !== false, 'The empty Primary Vendor option must never become a title preview.');
    $assert(strpos($titleJs, '(select Primary Vendor to preview)') === false, 'Title preview must not render the select placeholder.');
    $assert(strpos($title, 'No Primary Vendor is selected.') !== false, 'Title control must explain its missing Primary Vendor state.');
    $assert(strpos($title, 'vms-ep-title-control') !== false && strpos($css, '.vms-ep-title-control') !== false, 'Title control must use the compact Event Details treatment.');

    $assert(strpos($schedule, 'vms_lineup_entries[primary][show_public]') === false, 'Primary Vendor must not render a misleading public visibility checkbox.');
    $assert(strpos($schedule, 'vms_lineup_entries[primary][show_portal]') === false, 'Primary Vendor must not render a misleading portal visibility checkbox.');
    $assert(substr_count($schedule, "esc_html_e('Public lineup'") >= 2, 'Supporting rows and their template must label public visibility clearly.');
    $assert(substr_count($schedule, "esc_html_e('Vendor portal'") >= 2, 'Supporting rows and their template must label portal visibility clearly.');

    $sequence = "['basics', 'schedule', 'compensation', 'secondary_vendors', 'staff', 'ticketing_v2', 'readiness_details']";
    $assert(strpos($shell, $sequence) !== false, 'Save & Continue must follow the requested guided sequence.');
    $assert(strpos($sequence, 'cancellation') === false, 'Cancellation must not be part of the guided sequence.');
    $continuePos = strpos($shell, 'Save &amp; Continue');
    $savePos = strpos($shell, '>Save Changes</button>', $continuePos);
    $discardPos = strpos($shell, '>Discard Changes</button>', $savePos);
    $assert($continuePos !== false && $savePos > $continuePos && $discardPos > $savePos, 'Section actions must order Save & Continue, Save Changes, then Discard Changes.');
    $assert(strpos($shell, 'scrollSectionTargetIntoView(String(target.dataset.sectionKey') !== false, 'Successful Save & Continue and dirty Save & Open must scroll to the destination.');

    foreach (array('days_after', 'fixed_date', 'custom') as $timing) {
        $assert(strpos($compensation, 'data-vms-final-payment-timing="' . $timing . '"') !== false, 'Missing conditional final-payment timing field: ' . $timing);
    }
    $assert(strpos($compensation, 'data-vms-final-payment-method="other"') !== false, 'Other Method must be conditional.');
    $assert(strpos($compensationJs, 'function initFinalPaymentConditionalFields()') !== false, 'Conditional final-payment behavior must initialize.');
    $assert(strpos($compensationJs, 'control.disabled = !visible') !== false, 'Hidden final-payment controls must be disabled without clearing their browser values.');
    $assert(strpos($css, 'align-self: start;') !== false && strpos($css, 'min-height: 0;') !== false, 'Compensation cards and fields must remain content-height.');

    $assert(strpos($schedule, '<details id="vms-tax-bypass-inline"') !== false, 'Tax bypass controls must be collapsed in a disclosure.');
    $assert(strpos($schedule, 'Manage Tax Bypass') !== false, 'Tax bypass disclosure must have an operator-facing label.');
    $assert(strpos($schedule, '<div id="vms-tax-status"></div>') < strpos($schedule, '<details id="vms-tax-bypass-inline"'), 'Tax Profile status must remain visible above the collapsed bypass controls.');

    fwrite(STDOUT, "event plan operator acceptance polish: PASS\n");
} catch (Throwable $e) {
    fwrite(STDERR, 'event plan operator acceptance polish: FAIL - ' . $e->getMessage() . "\n");
    exit(1);
}
