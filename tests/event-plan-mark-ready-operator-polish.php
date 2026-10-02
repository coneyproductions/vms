<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$helpers = (string) file_get_contents($root . '/includes/helpers.php');
$eventPlans = (string) file_get_contents($root . '/includes/cpt/event-plans.php');
$workspaceStatus = (string) file_get_contents($root . '/includes/cpt/event-plans/partials/workspace-status.php');
$readinessDetails = (string) file_get_contents($root . '/includes/cpt/event-plans/partials/readiness-details.php');
$title = (string) file_get_contents($root . '/includes/cpt/event-plans/partials/title.php');
$helpJs = (string) file_get_contents($root . '/assets/admin-help-tooltips.js');
$css = (string) file_get_contents($root . '/assets/css/vms-admin.css');

function __($text, $domain = null): string { return (string) $text; }
function sanitize_key($value): string { return strtolower((string) preg_replace('/[^a-z0-9_\-]/i', '', (string) $value)); }
function sanitize_text_field($value): string { return trim(strip_tags((string) $value)); }
function absint($value): int { return abs((int) $value); }

$GLOBALS['vms_mark_ready_meta'] = array();
$GLOBALS['vms_mark_ready_posts'] = array();
$GLOBALS['vms_mark_ready_comp_default'] = array('has_default' => false);
$GLOBALS['vms_mark_ready_comp_options'] = array('max_guarantee' => 0.0);
function get_post_meta($postId, $key, $single = false) {
    $value = $GLOBALS['vms_mark_ready_meta'][(int) $postId][(string) $key] ?? '';
    return $single ? $value : ($value === '' ? array() : array($value));
}
function get_post($postId) {
    return $GLOBALS['vms_mark_ready_posts'][(int) $postId] ?? null;
}
function bvmgr_event_plan_vendor_exists(int $vendorId): bool {
    $post = get_post($vendorId);
    return is_object($post) && $post->post_type === 'vms_vendor' && $post->post_status !== 'trash';
}
function bvmgr_get_event_plan_effective_comp_default(int $venueId, string $eventDate): array {
    unset($venueId, $eventDate);
    return (array) $GLOBALS['vms_mark_ready_comp_default'];
}
function bvmgr_get_event_plan_comp_options(int $venueId, string $eventDate, int $vendorId): array {
    unset($venueId, $eventDate, $vendorId);
    return (array) $GLOBALS['vms_mark_ready_comp_options'];
}
function bvmgr_meta_key(string $group, string $field): string {
    unset($group);
    return $field === 'low_guarantee_ack' ? '_vms_low_guarantee_ack' : '';
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

foreach (array(
    'bvmgr_comp_default_field_is_configured',
    'bvmgr_comp_meaningful_default_diff_keys',
) as $functionName) {
    eval($extractFunction($helpers, $functionName));
}

foreach (array(
    'bvmgr_event_plan_classify_validation_blockers',
    'bvmgr_event_plan_compensation_readiness_evaluation',
    'bvmgr_event_plan_saved_compensation_readiness_blockers',
    'bvmgr_event_plan_mark_ready_blockers',
    'bvmgr_event_plan_mark_ready_blocker_message',
    'bvmgr_validate_event_plan',
) as $functionName) {
    eval($extractFunction($eventPlans, $functionName));
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

try {
    $incompletePlanId = 101;
    $blockers = bvmgr_event_plan_mark_ready_blockers($incompletePlanId);
    $codes = array_column($blockers, 'code');
    $labels = array_column($blockers, 'label');
    $assert(count($blockers) >= 5, 'An incomplete new Event Plan must report canonical Mark Ready blockers.');
    foreach (array('missing_event_date', 'missing_venue', 'missing_start_end_time', 'missing_flat_fee', 'missing_primary_vendor') as $code) {
        $assert(in_array($code, $codes, true), 'Missing expected blocker code: ' . $code);
    }
    foreach (array('Event Date', 'Venue', 'Start and End Time', 'Vendor Pay', 'Primary Vendor') as $label) {
        $assert(in_array($label, $labels, true), 'Missing expected blocker label: ' . $label);
    }

    $message = bvmgr_event_plan_mark_ready_blocker_message($blockers);
    $assert(strpos($message, "Can't mark Ready yet.") === 0, 'Mark Ready failure must start with actionable operator copy.');
    $assert(strpos($message, 'Missing:') !== false && strpos($message, 'Event Date') !== false && strpos($message, 'Venue') !== false && strpos($message, 'Primary Vendor') !== false, 'Missing-field failure must name its blockers.');

    $validVenue = (object) array('post_type' => 'vms_venue', 'post_status' => 'publish');
    $validVendor = (object) array('post_type' => 'vms_vendor', 'post_status' => 'publish');
    $GLOBALS['vms_mark_ready_posts'][74] = $validVenue;
    $GLOBALS['vms_mark_ready_posts'][88] = $validVendor;
    $GLOBALS['vms_mark_ready_meta'][102] = array(
        '_vms_event_date' => '2026-11-14',
        '_vms_venue_id' => 74,
        '_vms_start_time' => '19:00',
        '_vms_end_time' => '22:00',
        '_vms_comp_structure' => 'flat_fee',
        '_vms_flat_fee_amount' => '1200',
        '_vms_band_vendor_id' => 88,
    );
    $assert(bvmgr_event_plan_mark_ready_blockers(102) === array(), 'A complete saved Event Plan must not manufacture Mark Ready blockers.');

    $GLOBALS['vms_mark_ready_meta'][105] = $GLOBALS['vms_mark_ready_meta'][102];
    $GLOBALS['vms_mark_ready_meta'][105]['_vms_flat_fee_amount'] = '0';
    $assert(bvmgr_event_plan_mark_ready_blockers(105) === array(), 'An intentional zero Flat Fee must be valid and complete.');

    $GLOBALS['vms_mark_ready_meta'][106] = $GLOBALS['vms_mark_ready_meta'][102];
    $GLOBALS['vms_mark_ready_meta'][106]['_vms_flat_fee_amount'] = '-1';
    $negativeFlatCodes = array_column(bvmgr_event_plan_mark_ready_blockers(106), 'code');
    $assert(in_array('invalid_flat_fee', $negativeFlatCodes, true), 'A negative Flat Fee must remain invalid.');

    $GLOBALS['vms_mark_ready_meta'][107] = $GLOBALS['vms_mark_ready_meta'][105];
    $GLOBALS['vms_mark_ready_meta'][107]['_vms_comp_structure'] = 'flat_fee_door_split';
    $GLOBALS['vms_mark_ready_meta'][107]['_vms_door_split_percent'] = '20';
    $assert(bvmgr_event_plan_mark_ready_blockers(107) === array(), 'Flat Fee + Door Split must accept a zero Flat Fee with a valid split.');

    $GLOBALS['vms_mark_ready_meta'][108] = $GLOBALS['vms_mark_ready_meta'][107];
    $GLOBALS['vms_mark_ready_meta'][108]['_vms_door_split_percent'] = '0';
    $zeroSplitBlockers = bvmgr_event_plan_mark_ready_blockers(108);
    $assert(count($zeroSplitBlockers) === 1 && strpos((string) ($zeroSplitBlockers[0]['message'] ?? ''), 'between 1 and 100') !== false, 'A zero Door Split must remain invalid for Flat Fee + Door Split.');

    $GLOBALS['vms_mark_ready_comp_default'] = array(
        'has_default' => true,
        'structure' => 'flat_fee',
        'flat_fee_amount' => 1500,
    );
    $GLOBALS['vms_mark_ready_comp_options'] = array('max_guarantee' => 1800.0);
    $payBlockers = bvmgr_event_plan_mark_ready_blockers(102);
    $payCodes = array_column($payBlockers, 'code');
    $assert(in_array('pay_override_ack_required', $payCodes, true), 'Saved-state pay drift must remain a Mark Ready blocker.');
    $assert(in_array('low_guarantee_ack_required', $payCodes, true), 'Saved-state low guarantee must remain a Mark Ready blocker.');
    $GLOBALS['vms_mark_ready_meta'][102]['_vms_pay_override_ack'] = '1';
    $assert(bvmgr_event_plan_mark_ready_blockers(102) === array(), 'The existing single saved pay acknowledgment must continue satisfying both pay gates.');
    $GLOBALS['vms_mark_ready_comp_default'] = array('has_default' => false);
    $GLOBALS['vms_mark_ready_comp_options'] = array('max_guarantee' => 0.0);

    $GLOBALS['vms_mark_ready_meta'][103] = $GLOBALS['vms_mark_ready_meta'][102];
    $GLOBALS['vms_mark_ready_meta'][103]['_vms_band_vendor_id'] = 999;
    $integrityBlockers = bvmgr_event_plan_mark_ready_blockers(103);
    $primaryIntegrity = array_values(array_filter($integrityBlockers, static fn(array $blocker): bool => ($blocker['code'] ?? '') === 'invalid_primary_vendor'));
    $assert(count($primaryIntegrity) === 1 && ($primaryIntegrity[0]['kind'] ?? '') === 'integrity', 'A missing persisted vendor must remain distinguishable from ordinary incomplete setup.');

    $GLOBALS['vms_mark_ready_meta'][104] = $GLOBALS['vms_mark_ready_meta'][102];
    $GLOBALS['vms_mark_ready_meta'][104]['_vms_venue_id'] = 999;
    $venueIntegrity = array_values(array_filter(bvmgr_event_plan_mark_ready_blockers(104), static fn(array $blocker): bool => ($blocker['code'] ?? '') === 'invalid_venue'));
    $assert(count($venueIntegrity) === 1 && ($venueIntegrity[0]['kind'] ?? '') === 'integrity', 'A missing persisted venue must remain distinguishable from ordinary incomplete setup.');

    $assert(strpos($eventPlans, "'blocking_issue_count' => count(\$mark_ready_blockers)") !== false, 'Workspace blocking count must come from the canonical Mark Ready blocker set.');
    $assert(substr_count($eventPlans, 'bvmgr_event_plan_compensation_readiness_evaluation(') >= 3, 'Saved-state readiness and the canonical writer must share the same compensation-gate evaluator.');
    $assert(strpos($eventPlans, 'bvmgr_validate_event_plan($post_id, false)') !== false, 'Readiness rendering must call canonical validation without emitting action notices.');
    $assert(strpos($eventPlans, "'blockers' => \$blockers") !== false && strpos($eventPlans, "'kind' => \$has_integrity_blocker ? 'integrity' : 'readiness'") !== false, 'Saved-state Mark Ready failures must return structured blockers and failure kind.');
    $assert(strpos($eventPlans, 'The saved-state workflow action was blocked. Review the readiness and section status above.') === false, 'Generic contradictory Mark Ready copy must be removed.');
    $assert(strpos($eventPlans, "__('Mark Ready blockers', 'backstage-venue-manager')") !== false, 'Readiness details must name the Mark Ready blocker contract.');
    $assert(strpos($workspaceStatus, "workspace_status['blocking_issue_count']") !== false, 'Workspace status must continue rendering the canonical blocker count.');
    $assert(strpos($readinessDetails, 'No Mark Ready blockers') !== false, 'Readiness details must expose the same Mark Ready contract.');

    $assert(strpos($eventPlans, "bvmgr_help_icon(\n                    __('The Venue scopes this Event Plan") !== false, 'Venue scoping guidance must use the accessible help treatment.');
    $assert(strpos($eventPlans, 'Required. This scopes the event plan to a specific venue.') === false, 'Permanent Venue scoping copy must be removed.');
    $assert(substr_count($eventPlans, 'class="vms-ep-required"') >= 2, 'Event Date and Venue must retain visible required-field meaning.');
    $assert(strpos($title, "__('Automatic titles follow the selected Primary Vendor.") !== false, 'General automatic-title guidance must move into help.');
    $assert(strpos($title, 'No Primary Vendor is selected.') !== false && strpos($title, 'Auto-title is off.') !== false, 'Dynamic title state must remain inline.');
    $assert(strpos($eventPlans, 'Save Event Details to run authoritative holiday checks.') !== false, 'Dynamic Holiday save-first guidance must remain inline.');
    foreach (array('mouseenter', 'focus', 'click') as $interaction) {
        $assert(strpos($helpJs, "addEventListener('" . $interaction . "'") !== false, 'Help popovers must support ' . $interaction . '.');
    }
    $assert(strpos($css, 'grid-template-columns: repeat(2, minmax(220px, 1fr));') !== false, 'Event Details must retain a compact two-column primary layout.');
    $assert(strpos($title, 'vms-ep-basic-span') === false, 'Title must no longer consume a permanent full-width row.');

    fwrite(STDOUT, "event plan Mark Ready/operator polish: PASS\n");
} catch (Throwable $e) {
    fwrite(STDERR, 'event plan Mark Ready/operator polish: FAIL - ' . $e->getMessage() . "\n");
    exit(1);
}
