<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$eventPlans = (string) file_get_contents($root . '/includes/cpt/event-plans.php');
$shell = (string) file_get_contents($root . '/assets/js/vms-event-plan-shell.js');
$schedule = (string) file_get_contents($root . '/includes/cpt/event-plans/partials/time-lineup.php');
$compensation = (string) file_get_contents($root . '/includes/cpt/event-plans/partials/compensation.php');
$compensationJs = (string) file_get_contents($root . '/assets/js/vms-event-plan-compensation.js');
$adminCss = (string) file_get_contents($root . '/assets/css/vms-admin.css');

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$legendPos = strpos($schedule, 'Primary Vendor availability');
$selectorPos = strpos($schedule, 'data-lineup-primary-vendor-select');
$namePos = strpos($schedule, 'Public name override');
$assert($selectorPos !== false && $legendPos > $selectorPos && $legendPos < $namePos, 'Primary availability legend must sit directly with the Primary Vendor selector.');
$assert(strpos($eventPlans, 'Availability for %s: [✓] Available, [✖] Not Available, [?] Unknown') !== false, 'Availability legend must include the saved date and explicit symbols.');

$assert(strpos($shell, 'async function openAndFocusSection(section, force)') !== false, 'Workspace must define one canonical open-and-focus path.');
$assert(strpos($shell, 'await waitForSectionLayout();') !== false, 'Navigation must wait for layout after lazy loading/opening.');
$assert(strpos($shell, 'scrollSectionWrapperIntoWorkingPosition(section);') !== false, 'Navigation must scroll the destination wrapper, not a stale inner anchor.');
$assert(strpos($shell, "document.getElementById('wpadminbar')") !== false, 'Navigation must account for the WordPress admin bar offset.');
$assert(strpos($shell, 'scrollSectionTargetIntoView') === false, 'The fixed-delay legacy scrolling path must be removed.');
$assert(substr_count($shell, 'openAndFocusSection(') >= 6, 'Save, requested reveal, action links, and section toggles must share open-and-focus.');

$assert(strpos($compensation, 'id="vms_deposit_status"') !== false, 'Deposit status remains present.');
$assert(substr_count($compensation, 'data-vms-deposit-details') === 4, 'Only Treatment, Due, Paid, and Notes must be conditional deposit details.');
$assert(strpos($compensationJs, "String(depositStatus.value || '') !== 'not_required'") !== false, 'Deposit detail visibility must be driven by the existing status value.');
$assert(strpos($adminCss, '[data-vms-deposit-details][hidden]') !== false, 'Hidden deposit details must be removed from layout.');

$assert(strpos($eventPlans, "'missing_keys' => \$missing_keys") !== false, 'Canonical pay-lock readiness must return keyed missing items.');
$assert(strpos($eventPlans, "if (in_array(\$scope, array('basics', 'schedule'), true))") !== false, 'Basics and Schedule saves must refresh pay-lock readiness.');
$assert(strpos($eventPlans, "'html' => bvmgr_event_plan_render_lock_pay_actions_html(\$lock_pay_state)") !== false, 'Scoped saves must return the canonical server-rendered pay-lock action.');
$assert(strpos($shell, 'applyLockPayState(result.lockPayState);') !== false, 'Workspace must refresh pay-lock actions in place after successful relevant saves.');
$assert(strpos($eventPlans, "array('date', 'venue')") !== false && strpos($eventPlans, "array('start_time', 'end_time', 'primary_vendor')") !== false, 'Pay-lock navigation must route exact missing items to their owning sections.');

echo "event plan operator acceptance follow-up passed {$checks} assertions.\n";
