<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap-wordpress.php';
$pluginRootEnv = getenv('BVMGR_TEST_PLUGIN_ROOT');
$pluginRoot = is_string($pluginRootEnv) && $pluginRootEnv !== '' ? realpath($pluginRootEnv) : dirname(__DIR__);
if (!is_string($pluginRoot) || !is_dir($pluginRoot)) {
    throw new RuntimeException('BVMGR_TEST_PLUGIN_ROOT must identify the exact plugin package under test.');
}
$withoutInstalledBvm = static function ($plugins): array {
    return array_values(array_filter((array) $plugins, static function ($plugin): bool {
        return basename((string) $plugin) !== 'backstage-venue-manager.php';
    }));
};
$GLOBALS['wp_filter']['option_active_plugins'][10][] = array(
    'function' => $withoutInstalledBvm,
    'accepted_args' => 1,
);
vms_tests_require_wordpress(__DIR__);
require_once $pluginRoot . '/backstage-venue-manager.php';
remove_filter('option_active_plugins', $withoutInstalledBvm);

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion ' . $assertions . ' failed: ' . $message);
    }
};

$createdPosts = array();
$originalPost = $_POST ?? array();
$originalUserId = get_current_user_id();
$adminUserIds = get_users(array('role' => 'administrator', 'number' => 1, 'fields' => 'ID'));
$adminUserId = !empty($adminUserIds) ? (int) $adminUserIds[0] : 1;

$registerPost = static function (int $postId) use (&$createdPosts): int {
    $createdPosts[] = $postId;
    return $postId;
};
$cleanup = static function () use (&$createdPosts, $originalPost, $originalUserId): void {
    foreach (array_reverse($createdPosts) as $postId) {
        wp_delete_post((int) $postId, true);
    }
    wp_set_current_user($originalUserId);
    $_POST = $originalPost;
};

try {
    wp_set_current_user($adminUserId);
    foreach (array('vms_event_plan', 'vms_venue') as $postType) {
        if (!post_type_exists($postType)) {
            register_post_type($postType, array('public' => false, 'show_ui' => true, 'label' => $postType));
        }
    }

    $venueId = wp_insert_post(array(
        'post_type' => 'vms_venue',
        'post_status' => 'publish',
        'post_title' => 'Schedule readiness regression venue',
    ), true);
    $assert(!is_wp_error($venueId) && (int) $venueId > 0, 'Failed to create Venue fixture.');
    $venueId = $registerPost((int) $venueId);

    $planId = wp_insert_post(array(
        'post_type' => 'vms_event_plan',
        'post_status' => 'draft',
        'post_title' => 'No-primary Schedule save regression',
    ), true);
    $assert(!is_wp_error($planId) && (int) $planId > 0, 'Failed to create Event Plan fixture.');
    $planId = $registerPost((int) $planId);

    $controller = new BVMGR_Admin_Event_Plans();
    $saveMethod = new ReflectionMethod(BVMGR_Admin_Event_Plans::class, 'run_event_plan_scoped_save');
    $saveMethod->setAccessible(true);
    $saveSchedule = static function (array $request) use ($controller, $saveMethod, $planId): array {
        $filtered = bvmgr_event_plan_filter_section_request('schedule', $request);
        $result = $saveMethod->invoke($controller, $planId, 'schedule', $filtered);
        return is_array($result) ? $result : array();
    };

    $emptySubmission = bvmgr_event_plan_resolve_primary_vendor_submission($planId, array('vms_band_vendor_id' => ''));
    $assert(($emptySubmission['effective_vendor_id'] ?? -1) === 0, 'An empty Primary Vendor request must normalize deterministically to zero when no vendor is saved.');
    $assert(empty($emptySubmission['should_write']), 'A blank Primary Vendor selector must not be interpreted as an explicit clear operation.');

    $scheduleResult = $saveSchedule(array(
        'vms_start_time' => '19:00',
        'vms_end_time' => '22:00',
        'vms_band_vendor_id' => '',
        'vms_lineup_present' => '1',
        'vms_lineup_entries' => array(
            'primary' => array(
                'role' => 'primary',
                'sort_order' => '0',
                'vendor_id' => '',
                'set_start' => '',
                'set_end' => '',
            ),
        ),
    ));
    $assert(!empty($scheduleResult['ok']) && !empty($scheduleResult['verified']), 'Schedule with no Primary Vendor must verify valid Start/End persistence: ' . wp_json_encode($scheduleResult));
    $assert((string) get_post_meta($planId, '_vms_start_time', true) === '19:00', 'Start Time was not persisted.');
    $assert((string) get_post_meta($planId, '_vms_end_time', true) === '22:00', 'End Time was not persisted.');
    $assert((int) get_post_meta($planId, '_vms_band_vendor_id', true) === 0, 'Canonical empty Primary Vendor state must remain zero.');

    $verifiedEmptyVendor = bvmgr_event_plan_verify_scoped_save_postcondition($planId, 'schedule', array(
        'meta' => array(
            '_vms_start_time' => '19:00',
            '_vms_end_time' => '22:00',
            '_vms_band_vendor_id' => 0,
        ),
    ));
    $assert(!empty($verifiedEmptyVendor['ok']), 'Persisted zero and requested empty Primary Vendor semantics must compare equally.');
    $blockerCodes = array_column(bvmgr_event_plan_mark_ready_blockers($planId), 'code');
    $assert(in_array('missing_primary_vendor', $blockerCodes, true), 'Primary Vendor must remain a canonical Readiness blocker after Schedule persistence succeeds.');

    $readinessBefore = bvmgr_event_plan_readiness_response_state($planId);
    $assert((int) ($readinessBefore['blocking_issue_count'] ?? -1) === count((array) ($readinessBefore['blockers'] ?? array())), 'Readiness response count must come from its canonical blocker payload.');
    $assert(strpos((string) ($readinessBefore['blocking_issue_label'] ?? ''), (string) $readinessBefore['blocking_issue_count']) !== false, 'Readiness response must include its server-rendered count label.');

    $basicsResult = $saveMethod->invoke($controller, $planId, 'basics', bvmgr_event_plan_filter_section_request('basics', array(
        'vms_event_date' => '2027-06-12',
        'vms_venue_id' => (string) $venueId,
    )));
    $assert(is_array($basicsResult) && !empty($basicsResult['ok']) && !empty($basicsResult['verified']), 'Event Details fixture save must verify before readiness refresh.');
    $readinessAfter = bvmgr_event_plan_readiness_response_state($planId);
    $assert((int) $readinessAfter['blocking_issue_count'] < (int) $readinessBefore['blocking_issue_count'], 'Canonical readiness response must reflect newly persisted scoped-save state immediately.');
    $readinessAfterCodes = array_column((array) $readinessAfter['blockers'], 'code');
    $assert(!in_array('missing_event_date', $readinessAfterCodes, true) && !in_array('missing_venue', $readinessAfterCodes, true), 'Saved Event Details blockers must disappear from the canonical readiness response.');
    $assert(in_array('missing_primary_vendor', $readinessAfterCodes, true), 'Canonical readiness refresh must continue reporting the unsatisfied Primary Vendor blocker.');

    update_post_meta($planId, '_vms_start_time', '18:00');
    update_post_meta($planId, '_vms_end_time', '21:00');
    update_post_meta($planId, '_vms_band_vendor_id', 0);
    $corruptOnce = true;
    $corruptEndTime = static function ($metaId, $objectId, $metaKey) use (&$corruptOnce, $planId): void {
        if ($corruptOnce && (int) $objectId === $planId && (string) $metaKey === '_vms_end_time') {
            $corruptOnce = false;
            update_post_meta($planId, '_vms_end_time', '23:59');
        }
    };
    add_action('updated_post_meta', $corruptEndTime, 10, 3);
    $failedResult = $saveSchedule(array(
        'vms_start_time' => '19:30',
        'vms_end_time' => '22:30',
        'vms_band_vendor_id' => '',
        'vms_lineup_present' => '1',
        'vms_lineup_entries' => array(),
    ));
    remove_action('updated_post_meta', $corruptEndTime, 10);
    $assert(empty($failedResult['ok']) && ($failedResult['recovery'] ?? '') === 'rolled_back', 'A true Schedule postcondition failure must report verified rollback recovery.');
    $assert((string) get_post_meta($planId, '_vms_start_time', true) === '18:00', 'Rollback did not restore the prior Start Time.');
    $assert((string) get_post_meta($planId, '_vms_end_time', true) === '21:00', 'Rollback did not restore the prior End Time.');
    $assert(strpos((string) ($failedResult['message'] ?? ''), 'restored') !== false, 'Schedule failure must give an explicit recovery result rather than claiming no values changed.');

    $eventPlansSource = (string) file_get_contents($pluginRoot . '/includes/cpt/event-plans.php');
    $assert(strpos($eventPlansSource, "\$response['readiness_state'] = \$this->build_event_plan_readiness_refresh_state(\$post_id)") !== false, 'Verified non-Ticketing scoped-save responses must include complete canonical readiness state.');
    $assert(strpos($eventPlansSource, 'unset($this->event_plan_admin_boot_cache[$post_id]);') !== false, 'Post-save readiness refresh must discard request-local pre-save boot data.');
    foreach (array("'warning_count'", "'warning_label'", "'has_details'", "'details_html'", "'mark_ready_enabled'", "'publish_enabled'") as $refreshField) {
        $assert(strpos($eventPlansSource, "\$state[" . $refreshField . "]") !== false, 'Complete readiness refresh is missing field ' . $refreshField . '.');
    }
    $assert(strpos($eventPlansSource, "if (\$scope !== 'ticketing_v2')") !== false, 'Ticketing must retain its isolated two-stage save behavior without a premature readiness refresh.');
    $assert(strpos($eventPlansSource, "'ticketing_v2' => array(") !== false, 'Ticketing section ownership must remain present and unchanged.');

    fwrite(STDOUT, 'PASS: ' . $assertions . " Schedule save/readiness follow-up assertions.\n");
} finally {
    $cleanup();
}
