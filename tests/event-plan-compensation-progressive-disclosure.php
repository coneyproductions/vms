<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$compensation = (string) file_get_contents($root . '/includes/cpt/event-plans/partials/compensation.php');
$helpers = (string) file_get_contents($root . '/includes/helpers.php');
$helpJs = (string) file_get_contents($root . '/assets/admin-help-tooltips.js');
$compensationJs = (string) file_get_contents($root . '/assets/js/vms-event-plan-compensation.js');
$css = (string) file_get_contents($root . '/assets/css/vms-admin.css');

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

try {
    $helpStrings = array(
        'Load Draft Pay from a tile, review it, then lock it for this event.',
        'These fields stay editable even after you load a default or package.',
        'When comparable guarantees are available, tile colors progress from lower guaranteed pay to higher guaranteed pay.',
        'What the vendor is paid for this event.',
        'Optional event-level deposit terms. These are separate from final pay and are included in Locked Pay snapshots for agreement packets.',
        'Leave blank when no deposit is required.',
        'Use this for plain-English details such as refund deadline, crediting rule, or special agreement context.',
        'When and how the remaining vendor payment is expected to be paid. These terms are captured in agreement snapshots.',
        'Used when Expected Final Payment is N days after event.',
        'Used when Expected Final Payment is Specific date.',
        'Used when Expected Final Payment is Custom timing.',
        'Used when Payment Method is Other.',
        'Separate event expense. Blank or 0 means none for this event.',
        'No attendance bonus is earned until attendance goes above this number.',
        'How many additional tickets are needed to earn each bonus step.',
        'The amount added each time a step is reached.',
        'The amount added for each ticket above the starting count.',
        'Optional cap on the total attendance bonus. Leave blank for no cap.',
    );
    foreach ($helpStrings as $helpString) {
        $position = strpos($compensation, $helpString);
        $assert($position !== false, 'Missing Compensation help text: ' . $helpString);
        $nearbySource = substr($compensation, max(0, $position - 180), 420);
        $assert(
            strpos($nearbySource, 'bvmgr_help_icon') !== false || strpos($nearbySource, '$vms_draft_pay_help') !== false,
            'Static Compensation guidance must be routed through accessible help: ' . $helpString
        );
    }

    $assert(strpos($compensation, 'class="description vms-mt-6">\n            <?php esc_html_e(\'Load Draft Pay') === false, 'Compensation Options guidance must not remain a permanent description paragraph.');
    $assert(strpos($compensation, 'vms-comp-field-help') === false, 'Static field guidance must no longer occupy permanent field-help rows.');
    $assert(strpos($compensation, 'vms-comp-structure-scale-legend') === false, 'Draft Pay color-scale guidance must not remain a permanent legend row.');
    $assert(strpos($compensation, '<div class="vms-ep-card vms-ep-card--blue">') === false, 'The permanent Compensation how-it-works card must be removed.');
    $assert(substr_count($compensation, 'bvmgr_help_icon(') >= 16, 'Compensation headings and field labels must expose the migrated guidance through help controls.');

    $assert(strpos($helpers, 'type="button" class="vms-help-icon"') !== false, 'Compensation help must reuse the canonical non-submitting help button.');
    $assert(strpos($helpers, 'aria-label=') !== false && strpos($helpers, 'aria-expanded="false"') !== false, 'Canonical help buttons must retain accessible state and labels.');
    foreach (array('mouseenter', 'focus', 'click') as $eventName) {
        $assert(strpos($helpJs, "addEventListener('" . $eventName . "'") !== false, 'Help controls must support ' . $eventName . '.');
    }
    $assert(strpos($css, '.vms-comp-field-label') !== false && strpos($css, 'align-items: center;') !== false, 'Field-label help controls must stay compact and label-adjacent.');

    foreach (array(
        'This event uses pay different from the Primary Vendor default',
        'Draft Pay differs from Locked Snapshot',
        '$vms_deposit_summary',
        '$vms_final_payment_summary',
        'vms-attendance-bonus-preview',
    ) as $dynamicMarker) {
        $assert(strpos($compensation, $dynamicMarker) !== false, 'Dynamic/current-event Compensation state must remain inline: ' . $dynamicMarker);
    }
    foreach (array(
        'name="vms_comp_structure"',
        'name="vms_deposit_amount"',
        'name="vms_deposit_status"',
        'name="vms_final_payment_timing"',
        'name="vms_final_payment_method"',
        'name="vms_commission_percent"',
        'name="vms_commission_mode"',
    ) as $controlMarker) {
        $assert(strpos($compensation, $controlMarker) !== false, 'Compensation persistence control must remain present: ' . $controlMarker);
    }
    foreach (array('days_after', 'fixed_date', 'custom') as $timing) {
        $assert(strpos($compensation, 'data-vms-final-payment-timing="' . $timing . '"') !== false, 'Conditional final-payment timing must remain intact: ' . $timing);
    }
    $assert(strpos($compensation, 'data-vms-final-payment-method="other"') !== false, 'Conditional Other Method field must remain intact.');

    $assert(
        preg_match('/id="vms_commission_percent"[^>]*\/>\s*%/', $compensation) !== 1,
        'Agent Fee input must not render a redundant literal percent suffix.'
    );
    $assert(
        strpos($compensation, 'id="vms-agent-fee-summary" class="vms-ep-card vms-ep-card--gray vms-mt-10" hidden') !== false,
        'Agent Fee summary must begin hidden so an empty card consumes no layout space.'
    );
    $assert(strpos($compensationJs, "agentFeeSummary.textContent = '';\n        agentFeeSummary.hidden = true;") !== false, 'Blank or zero Agent Fee must clear and hide its summary.');
    $assert(strpos($compensationJs, 'agentFeeSummary.hidden = false;') !== false, 'Positive Agent Fee must reveal its calculated summary.');
    $assert(strpos($compensationJs, 'No agent fee is currently set for this event.') === false, 'Zero Agent Fee must not render a permanent empty-state summary.');
    $assert(substr_count($compensation, 'Agent Fee help') === 1, 'Only the Agent Fee section heading must retain its help control.');
    $assert(strpos($compensation, 'Agent Fee percentage help') === false, 'Agent Fee percentage must not duplicate the heading help control.');
    $assert(strpos($compensation, "__('Base Pay ($)', 'backstage-venue-manager')") !== false, 'Base Pay label must include its currency unit.');
    $assert(strpos($compensation, "__('Flat Fee Amount ($)', 'backstage-venue-manager')") !== false, 'Flat Fee label must include its currency unit.');
    foreach (array(
        "fCommissionPercent.addEventListener('input', renderAgentFeeSummary);",
        "fCommissionPercent.addEventListener('change', renderAgentFeeSummary);",
        "fCommissionMode.addEventListener('change', renderAgentFeeSummary);",
    ) as $agentFeeBinding) {
        $assert(strpos($compensationJs, $agentFeeBinding) !== false, 'Agent Fee summary must refresh immediately through: ' . $agentFeeBinding);
    }
    foreach (array(
        'will be based on gross / settlement',
        'will be added on top once',
        'Guaranteed expense total:',
    ) as $positiveSummaryMarker) {
        $assert(strpos($compensationJs, $positiveSummaryMarker) !== false, 'Positive Agent Fee summary must preserve useful messaging: ' . $positiveSummaryMarker);
    }

    fwrite(STDOUT, "event plan compensation progressive disclosure: PASS\n");
} catch (Throwable $e) {
    fwrite(STDERR, 'event plan compensation progressive disclosure: FAIL - ' . $e->getMessage() . "\n");
    exit(1);
}
