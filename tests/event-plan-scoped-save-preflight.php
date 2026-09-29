<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap-wordpress.php';
vms_tests_require_wordpress(__DIR__);

$pluginRootEnv = getenv('BVMGR_TEST_PLUGIN_ROOT');
$pluginRoot = is_string($pluginRootEnv) && $pluginRootEnv !== '' ? realpath($pluginRootEnv) : dirname(__DIR__);
if (!is_string($pluginRoot) || !is_dir($pluginRoot)) {
    throw new RuntimeException('BVMGR_TEST_PLUGIN_ROOT must identify the exact plugin package under test.');
}
if (!class_exists('BVMGR_Admin_Event_Plans')) {
    require_once $pluginRoot . '/backstage-venue-manager.php';
}

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion ' . $assertions . ' failed: ' . $message);
    }
};

$createdPosts = array();
$originalPost = $_POST ?? array();
$registerPost = static function (int $postId) use (&$createdPosts): int {
    $createdPosts[] = $postId;
    return $postId;
};
$createPlan = static function (string $title) use ($registerPost): int {
    $postId = wp_insert_post(array(
        'post_type' => 'vms_event_plan',
        'post_status' => 'publish',
        'post_title' => $title,
    ), true);
    if (is_wp_error($postId) || (int) $postId <= 0) {
        throw new RuntimeException('Failed to create Event Plan fixture.');
    }
    return $registerPost((int) $postId);
};
$snapshotMeta = static function (int $postId, array $keys): array {
    $snapshot = array();
    foreach ($keys as $key) {
        $snapshot[(string) $key] = get_post_meta($postId, (string) $key, true);
    }
    return $snapshot;
};

try {
    wp_set_current_user(1);
    $assert(current_user_can('edit_posts'), 'Expected the fixture user to edit Event Plans.');

    $controller = new BVMGR_Admin_Event_Plans();
    $method = new ReflectionMethod(BVMGR_Admin_Event_Plans::class, 'run_event_plan_scoped_save');
    $method->setAccessible(true);
    $saveSection = static function (int $postId, string $scope, array $request) use ($controller, $method): array {
        $filtered = bvmgr_event_plan_filter_section_request($scope, $request);
        $result = $method->invoke($controller, $postId, $scope, $filtered);
        return is_array($result) ? $result : array();
    };
    $saveWholeForm = static function (int $postId, array $request) use ($controller): void {
        $savedPost = $_POST;
        $post = get_post($postId);
        if (!$post instanceof WP_Post) {
            throw new RuntimeException('Whole-form Event Plan fixture is unavailable.');
        }
        $request['post_ID'] = $postId;
        $request['original_post_status'] = (string) $post->post_status;
        $request['vms_secondary_vendors_module_detached'] = '1';
        $request['vms_staffing_lazy_unloaded'] = '1';
        $request['bvmgr_event_plan_details_nonce'] = wp_create_nonce('bvmgr_save_event_plan_details');
        $_POST = wp_slash($request);
        $GLOBALS['bvmgr_event_plan_request_cache_generation'] = max(0, (int) ($GLOBALS['bvmgr_event_plan_request_cache_generation'] ?? 0)) + 1;
        try {
            $controller->save_event_plan_meta($postId, $post);
        } finally {
            $_POST = $savedPost;
            $GLOBALS['bvmgr_event_plan_request_cache_generation'] = max(0, (int) ($GLOBALS['bvmgr_event_plan_request_cache_generation'] ?? 0)) + 1;
        }
    };
    $compensationRequest = static function (array $overrides = array()): array {
        return array_merge(array(
            'vms_auto_comp_venue' => '1',
            'vms_comp_structure' => 'flat_fee',
            'vms_flat_fee_amount' => '1200',
            'vms_commission_percent' => '',
            'vms_commission_mode' => 'artist_fee',
            'vms_deposit_amount' => '',
            'vms_deposit_status' => 'not_required',
            'vms_deposit_treatment' => 'creditable',
            'vms_final_payment_timing' => 'not_set',
            'vms_final_payment_method' => 'not_set',
        ), $overrides);
    };

    $publishedPlanId = $createPlan('Scoped occurrence lock fixture');
    update_post_meta($publishedPlanId, '_vms_event_date', '2026-10-10');
    update_post_meta($publishedPlanId, '_vms_venue_id', 41);
    update_post_meta($publishedPlanId, '_vms_event_plan_status', 'published');
    $blockedResult = $saveSection($publishedPlanId, 'basics', array(
        'vms_event_date' => '2026-10-11',
        'vms_venue_id' => '99',
    ));
    $assert(empty($blockedResult['ok']) && empty($blockedResult['verified']), 'A protected occurrence change must fail before Event Details mutation.');
    $assert((string) get_post_meta($publishedPlanId, '_vms_event_date', true) === '2026-10-10', 'Blocked Event Date changed.');
    $assert((int) get_post_meta($publishedPlanId, '_vms_venue_id', true) === 41, 'Venue changed despite a blocked Event Date.');

    $ticketPlanId = $createPlan('Scoped Ticketing validation fixture');
    $ticketKeys = array(
        '_vms_ticketing_sales_mode',
        '_vms_external_ticket_url',
        '_vms_external_ticket_provider',
        '_vms_event_relationship',
        '_vms_external_event_producer',
        '_vms_external_event_producer_website',
        '_vms_ticketing_enabled_override',
        '_vms_ticketing_ga_image_mode',
        '_vms_ticketing_ga_image_id',
    );
    $ticketState = array(
        '_vms_ticketing_sales_mode' => 'external',
        '_vms_external_ticket_url' => 'https://tickets.example.test/original',
        '_vms_external_ticket_provider' => 'Original Provider',
        '_vms_event_relationship' => 'hosted_third_party',
        '_vms_external_event_producer' => 'Original Producer',
        '_vms_external_event_producer_website' => 'https://producer.example.test/original',
        '_vms_ticketing_enabled_override' => 'on',
        '_vms_ticketing_ga_image_mode' => 'custom',
        '_vms_ticketing_ga_image_id' => 123,
    );
    foreach ($ticketState as $key => $value) {
        update_post_meta($ticketPlanId, $key, $value);
    }
    $ticketBefore = $snapshotMeta($ticketPlanId, $ticketKeys);
    $invalidExternalResult = $saveSection($ticketPlanId, 'ticketing_v2', array(
        'vms_ticketing_sales_mode' => 'serenade_range',
        'vms_external_ticket_url' => 'javascript:alert(1)',
        'vms_external_ticket_provider' => 'Changed Provider',
        'vms_event_relationship' => 'serenade_range_produced',
        'vms_external_event_producer' => 'Changed Producer',
        'vms_external_event_producer_website' => 'https://producer.example.test/changed',
        'vms_ticketing_enabled_override' => 'off',
        'vms_ticketing_ga_image_mode' => 'none',
        'vms_ticketing_ga_image_id' => '456',
    ));
    $assert(empty($invalidExternalResult['ok']) && empty($invalidExternalResult['verified']), 'Invalid external URL must fail Ticketing preflight.');
    $assert($snapshotMeta($ticketPlanId, $ticketKeys) === $ticketBefore, 'Invalid external URL partially mutated Ticketing destination state.');

    $invalidWebsiteResult = $saveSection($ticketPlanId, 'ticketing_v2', array(
        'vms_ticketing_sales_mode' => 'serenade_range',
        'vms_external_ticket_url' => 'https://tickets.example.test/changed',
        'vms_external_ticket_provider' => 'Changed Provider',
        'vms_event_relationship' => 'serenade_range_produced',
        'vms_external_event_producer' => 'Changed Producer',
        'vms_external_event_producer_website' => 'javascript:alert(1)',
        'vms_ticketing_enabled_override' => 'off',
        'vms_ticketing_ga_image_mode' => 'none',
        'vms_ticketing_ga_image_id' => '456',
    ));
    $assert(empty($invalidWebsiteResult['ok']) && empty($invalidWebsiteResult['verified']), 'Invalid presenter website must fail Ticketing preflight.');
    $assert($snapshotMeta($ticketPlanId, $ticketKeys) === $ticketBefore, 'Invalid presenter website partially mutated Ticketing destination state.');

    $compPlanId = $createPlan('Scoped Compensation validation fixture');
    $compKeys = array(
        '_vms_auto_comp',
        '_vms_auto_comp_venue',
        '_vms_comp_structure',
        '_vms_flat_fee_amount',
        '_vms_attendance_bonus_mode',
        '_vms_attendance_bonus_start_count',
        '_vms_attendance_bonus_step_size',
        '_vms_attendance_bonus_step_bonus',
        '_vms_attendance_bonus_max_bonus',
        '_vms_commission_percent',
        '_vms_commission_mode',
        '_vms_deposit_amount',
        '_vms_final_payment_timing',
    );
    $compState = array(
        '_vms_auto_comp' => '1',
        '_vms_auto_comp_venue' => '1',
        '_vms_comp_structure' => 'flat_fee',
        '_vms_flat_fee_amount' => '700',
        '_vms_commission_percent' => '10',
        '_vms_commission_mode' => 'artist_fee',
        '_vms_deposit_amount' => '100',
        '_vms_final_payment_timing' => 'day_of_event',
    );
    foreach ($compState as $key => $value) {
        update_post_meta($compPlanId, $key, $value);
    }
    $compBefore = $snapshotMeta($compPlanId, $compKeys);
    $invalidCompResult = $saveSection($compPlanId, 'compensation', array(
        'vms_auto_comp_venue' => '1',
        'vms_comp_structure' => 'attendance_bonus',
        'vms_flat_fee_amount' => '2500',
        'vms_attendance_bonus_mode' => 'step',
        'vms_attendance_bonus_start_count' => '25',
        'vms_attendance_bonus_step_size' => '0',
        'vms_attendance_bonus_step_bonus' => '100',
        'vms_attendance_bonus_max_bonus' => '500',
        'vms_commission_percent' => '20',
        'vms_commission_mode' => 'gross',
        'vms_deposit_amount' => '300',
        'vms_final_payment_timing' => 'fixed_date',
        'vms_final_payment_date' => '2026-10-01',
    ));
    $assert(empty($invalidCompResult['ok']) && empty($invalidCompResult['verified']), 'Invalid Attendance Bonus step size must fail Compensation preflight.');
    $assert($snapshotMeta($compPlanId, $compKeys) === $compBefore, 'Invalid Attendance Bonus step size partially mutated Compensation state.');

    $validPlanId = $createPlan('Scoped valid and isolation fixture');
    update_post_meta($validPlanId, '_vms_event_date', '2026-11-01');
    update_post_meta($validPlanId, '_vms_venue_id', 50);
    update_post_meta($validPlanId, '_vms_comp_structure', 'flat_fee');
    update_post_meta($validPlanId, '_vms_flat_fee_amount', 900);
    $validBasicsResult = $saveSection($validPlanId, 'basics', array(
        'vms_event_date' => '2026-11-02',
        'vms_venue_id' => '51',
        'vms_auto_title' => '1',
        'vms_comp_structure' => 'door_split',
        'vms_flat_fee_amount' => '5000',
    ));
    $assert(!empty($validBasicsResult['ok']) && !empty($validBasicsResult['verified']), 'Valid Event Details request did not pass postcondition verification.');
    $assert((string) get_post_meta($validPlanId, '_vms_event_date', true) === '2026-11-02' && (int) get_post_meta($validPlanId, '_vms_venue_id', true) === 51, 'Valid Event Details values did not persist.');
    $assert((string) get_post_meta($validPlanId, '_vms_comp_structure', true) === 'flat_fee' && (string) get_post_meta($validPlanId, '_vms_flat_fee_amount', true) === '900', 'Event Details save crossed into Compensation fields.');

    $validCompResult = $saveSection($validPlanId, 'compensation', array(
        'vms_auto_comp_venue' => '1',
        'vms_comp_structure' => 'attendance_bonus',
        'vms_flat_fee_amount' => '1200',
        'vms_attendance_bonus_mode' => 'step',
        'vms_attendance_bonus_start_count' => '20',
        'vms_attendance_bonus_step_size' => '10',
        'vms_attendance_bonus_step_bonus' => '75',
        'vms_attendance_bonus_max_bonus' => '450',
        'vms_commission_percent' => '',
        'vms_commission_mode' => 'artist_fee',
        'vms_deposit_amount' => '',
        'vms_deposit_status' => 'not_required',
        'vms_deposit_treatment' => 'creditable',
        'vms_final_payment_timing' => 'not_set',
        'vms_final_payment_method' => 'not_set',
    ));
    $assert(!empty($validCompResult['ok']) && !empty($validCompResult['verified']), 'Valid Compensation request did not pass postcondition verification.');
    $assert((string) get_post_meta($validPlanId, '_vms_attendance_bonus_step_size', true) === '10', 'Canonical valid Attendance Bonus step size did not persist.');
    $assert((string) get_post_meta($validPlanId, '_vms_event_date', true) === '2026-11-02', 'Compensation save crossed into Event Details fields.');
    $assert((string) get_post_meta($validPlanId, '_vms_auto_comp', true) === '1', 'Never-stored automatic compensation did not retain its enabled semantic default.');

    $autoCompEnabledPlanId = $createPlan('Scoped automatic compensation enabled fixture');
    update_post_meta($autoCompEnabledPlanId, '_vms_auto_comp', '1');
    update_post_meta($autoCompEnabledPlanId, '_vms_auto_comp_venue', '1');
    $preserveEnabledResult = $saveSection($autoCompEnabledPlanId, 'compensation', $compensationRequest());
    $assert(!empty($preserveEnabledResult['ok']) && !empty($preserveEnabledResult['verified']), 'Scoped Compensation verification failed while preserving enabled automatic compensation.');
    $assert((string) get_post_meta($autoCompEnabledPlanId, '_vms_auto_comp', true) === '1', 'Absent automatic-compensation input cleared an enabled state.');

    $autoCompDisabledPlanId = $createPlan('Scoped automatic compensation disabled fixture');
    update_post_meta($autoCompDisabledPlanId, '_vms_auto_comp', '0');
    update_post_meta($autoCompDisabledPlanId, '_vms_auto_comp_venue', '1');
    $preserveDisabledResult = $saveSection($autoCompDisabledPlanId, 'compensation', $compensationRequest());
    $assert(!empty($preserveDisabledResult['ok']) && !empty($preserveDisabledResult['verified']), 'Scoped Compensation verification failed while preserving disabled automatic compensation.');
    $assert((string) get_post_meta($autoCompDisabledPlanId, '_vms_auto_comp', true) === '0', 'Absent automatic-compensation input enabled a disabled state.');

    $explicitDisableResult = $saveSection($autoCompEnabledPlanId, 'compensation', $compensationRequest(array('vms_auto_comp' => '0')));
    $assert(!empty($explicitDisableResult['ok']) && (string) get_post_meta($autoCompEnabledPlanId, '_vms_auto_comp', true) === '0', 'Explicit zero did not disable automatic compensation.');
    $explicitEnableResult = $saveSection($autoCompDisabledPlanId, 'compensation', $compensationRequest(array('vms_auto_comp' => '1')));
    $assert(!empty($explicitEnableResult['ok']) && (string) get_post_meta($autoCompDisabledPlanId, '_vms_auto_comp', true) === '1', 'Explicit one did not enable automatic compensation.');

    update_post_meta($autoCompDisabledPlanId, '_vms_auto_comp_venue', '1');
    $venueAbsentRequest = $compensationRequest();
    unset($venueAbsentRequest['vms_auto_comp_venue']);
    $venueAbsentResult = $saveSection($autoCompDisabledPlanId, 'compensation', $venueAbsentRequest);
    $assert(!empty($venueAbsentResult['ok']) && (string) get_post_meta($autoCompDisabledPlanId, '_vms_auto_comp_venue', true) === '0', 'Absent venue automatic-compensation checkbox no longer disables that rendered checkbox setting.');
    $venuePresentResult = $saveSection($autoCompDisabledPlanId, 'compensation', $compensationRequest());
    $assert(!empty($venuePresentResult['ok']) && (string) get_post_meta($autoCompDisabledPlanId, '_vms_auto_comp_venue', true) === '1', 'Present venue automatic-compensation checkbox no longer enables that setting.');

    $wholeFormPlanId = $createPlan('Whole-form automatic compensation fixture');
    update_post_meta($wholeFormPlanId, '_vms_auto_comp', '1');
    update_post_meta($wholeFormPlanId, '_vms_event_date', '2026-12-01');
    $wholeFormRequest = $compensationRequest(array(
        'vms_event_date' => '2026-12-01',
        'vms_venue_id' => '0',
    ));
    $saveWholeForm($wholeFormPlanId, $wholeFormRequest);
    $assert((string) get_post_meta($wholeFormPlanId, '_vms_auto_comp', true) === '1', 'Whole-form save without automatic-compensation input cleared the stored state.');

    fwrite(STDOUT, 'PASS: ' . $assertions . " scoped Event Plan preflight assertions.\n");
} finally {
    $_POST = $originalPost;
    foreach (array_reverse($createdPosts) as $postId) {
        wp_delete_post((int) $postId, true);
    }
}
