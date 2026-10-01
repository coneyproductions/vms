<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap-wordpress.php';
vms_tests_require_wordpress(__DIR__);

if (!class_exists('BVMGR_Admin_Event_Plans')) {
    require_once dirname(__DIR__) . '/backstage-venue-manager.php';
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$createdPosts = array();
$originalGet = $_GET ?? array();
$cleanup = static function () use (&$createdPosts, &$originalGet): void {
    foreach (array_reverse($createdPosts) as $postId) {
        wp_delete_post((int) $postId, true);
    }
    $_GET = $originalGet;
};

$renderTicketing = static function (int $planId): string {
    $reflection = new ReflectionClass('BVMGR_Admin_Event_Plans');
    /** @var BVMGR_Admin_Event_Plans $admin */
    $admin = $reflection->newInstanceWithoutConstructor();
    $_GET['post'] = (string) $planId;
    $_GET['vms_ep_load_section'] = 'ticketing_v2';
    ob_start();
    $admin->render_event_plan_ticketing_v2_host_meta_box(get_post($planId));
    return (string) ob_get_clean();
};

try {
    wp_set_current_user(1);
    $assert(function_exists('bvmgr_ticketing_v2_get_admin_config_state'), 'Authoritative Ticketing admin-state helper is unavailable.');

    $planId = wp_insert_post(array(
        'post_type' => 'vms_event_plan',
        'post_status' => 'draft',
        'post_title' => 'Ticketing config state refresh regression',
    ), true);
    $assert(!is_wp_error($planId) && (int) $planId > 0, 'Could not create Event Plan fixture.');
    $planId = (int) $planId;
    $createdPosts[] = $planId;

    update_post_meta($planId, '_vms_event_date', wp_date('Y-m-d', strtotime('+45 days')));
    update_post_meta($planId, '_vms_start_time', '19:00');
    update_post_meta($planId, '_vms_end_time', '22:00');
    update_post_meta($planId, '_vms_ticketing_sales_mode', 'serenade_range');
    update_post_meta($planId, '_vms_ticketing_enabled_override', 'on');
    delete_post_meta($planId, bvmgr_ticketing_v2_k('config'));

    $defaultConfig = bvmgr_ticketing_v2_get_config($planId);
    $assert(bvmgr_ticketing_v2_get_saved_config($planId) === array(), 'Fresh plan must not report its editor defaults as persisted config.');
    $assert(($defaultConfig['mode'] ?? '') === 'read_only', 'Fresh editor fallback should remain safely read-only.');
    $assert(($defaultConfig['tickets'][0]['title'] ?? '') === 'GA Admission', 'Visible GA Admission row should come from the editor fallback before first save.');
    $assert((string) ($defaultConfig['tickets'][0]['price'] ?? '') === '20', 'Fresh GA fallback should retain its default price.');

    $emptyState = bvmgr_ticketing_v2_get_admin_config_state($planId);
    $assert(empty($emptyState['config_exists']), 'No-config state must be truthful before persistence.');
    $assert(!empty($emptyState['preview_available']), 'Preview may remain available because it saves the current draft config before previewing.');
    $emptyHtml = $renderTicketing($planId);
    $assert(strpos($emptyHtml, 'data-config-exists="0"') !== false, 'Initial full editor DOM must identify the no-config state.');

    $savedConfig = $defaultConfig;
    $savedConfig['mode'] = 'vms_managed';
    $savedConfig['tickets'][0]['price'] = '25';
    bvmgr_ticketing_v2_set_config($planId, $savedConfig);

    $authoritativeConfig = bvmgr_ticketing_v2_get_saved_config($planId);
    $savedState = bvmgr_ticketing_v2_get_admin_config_state($planId);
    $assert(!empty($savedState['config_exists']), 'Authoritative detector must become true after a valid config save.');
    $assert(($savedState['config_mode'] ?? '') === 'vms_managed', 'Saved managed mode must replace the fallback read-only mode.');
    $assert(($authoritativeConfig['mode'] ?? '') === 'vms_managed', 'Authoritative getter must return the persisted managed mode.');
    $assert((string) ($authoritativeConfig['tickets'][0]['price'] ?? '') === '25', 'Authoritative getter must return the persisted GA price.');
    $assert(!empty($savedState['preview_available']), 'Valid persisted config with all prerequisites should remain Preview-eligible.');

    $savedHtml = $renderTicketing($planId);
    $assert(strpos($savedHtml, 'data-config-exists="1"') !== false, 'Returning to Ticketing must render the saved-config state.');
    $assert(strpos($savedHtml, 'data-config-mode="vms_managed"') !== false, 'Returning to Ticketing must render the authoritative saved mode.');
    $assert(preg_match('/id="vms-ticketing-v2-preview-sync-btn"[^>]*disabled=/', $savedHtml) !== 1, 'Preview control must be enabled when authoritative prerequisites pass.');
    $assert(strpos($savedHtml, '&quot;price&quot;:&quot;25&quot;') !== false, 'Returning to Ticketing must render the same persisted GA price.');

    $_GET['vms_ep_load_section'] = 'workflow_publish';
    $afterRoutingState = bvmgr_ticketing_v2_get_admin_config_state($planId);
    $assert($afterRoutingState === $savedState, 'Workflow / Publish routing state must not alter Ticketing config authority.');

    update_post_meta($planId, '_vms_ticketing_enabled_override', 'off');
    $blockedState = bvmgr_ticketing_v2_get_admin_config_state($planId);
    $assert(empty($blockedState['preview_available']), 'Disabled Ticketing remains a genuine Preview safety prerequisite.');
    $blockedHtml = $renderTicketing($planId);
    $assert(preg_match('/id="vms-ticketing-v2-preview-sync-btn"[^>]*disabled="disabled"/', $blockedHtml) === 1, 'Fresh DOM must keep Preview disabled when Ticketing is genuinely disabled.');

    fwrite(STDOUT, "event plan Ticketing config state refresh integration: PASS\n");
} catch (Throwable $e) {
    fwrite(STDERR, 'event plan Ticketing config state refresh integration: FAIL - ' . $e->getMessage() . "\n");
    $cleanup();
    exit(1);
}

$cleanup();
exit(0);
