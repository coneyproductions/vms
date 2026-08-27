<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap-wordpress.php';
vms_tests_require_wordpress(__DIR__);

if (!class_exists('BVMGR_Admin_Event_Plans')) {
    require_once dirname(__DIR__) . '/backstage-venue-manager.php';
}

$assert = static function (bool $condition, string $message): void {
    if ($condition) {
        return;
    }

    throw new RuntimeException($message);
};

$createdPosts = array();
$scheduledMaintenancePairs = array();
$originalPost = $_POST ?? array();
$originalGet = $_GET ?? array();
$originalRequest = $_REQUEST ?? array();

$registerPost = static function (int $postId) use (&$createdPosts): int {
    $createdPosts[] = $postId;
    return $postId;
};

$registerMaintenancePair = static function (int $planId, int $tecId) use (&$scheduledMaintenancePairs): void {
    $scheduledMaintenancePairs[] = array($planId, $tecId);
};

$noticeKey = 'vms_event_plan_notices_' . get_current_user_id();
$clearNotices = static function () use ($noticeKey): void {
    delete_transient($noticeKey);
};

$cleanup = static function () use (&$createdPosts, &$scheduledMaintenancePairs, &$originalPost, &$originalGet, &$originalRequest, $clearNotices): void {
    foreach ($scheduledMaintenancePairs as $pair) {
        $planId = isset($pair[0]) ? (int) $pair[0] : 0;
        $tecId = isset($pair[1]) ? (int) $pair[1] : 0;
        if ($planId > 0 && $tecId > 0) {
            wp_clear_scheduled_hook('vms_event_plan_calendar_maintenance', array($planId, $tecId));
        }
    }

    foreach (array_reverse($createdPosts) as $postId) {
        wp_delete_post((int) $postId, true);
    }

    $_POST = $originalPost;
    $_GET = $originalGet;
    $_REQUEST = $originalRequest;
    $clearNotices();
};

try {
    wp_set_current_user(1);

    $assert(post_type_exists('tribe_events'), 'Expected The Events Calendar post type to be available.');
    $assert(current_user_can('edit_posts'), 'Expected test user to be able to edit posts.');
    $assert(false !== has_action('admin_post_vms_resync_event_to_calendar', 'bvmgr_handle_admin_post_resync_event_to_calendar'), 'Expected isolated Re-sync admin-post action to remain registered.');
    $assert(function_exists('bvmgr_event_plan_handle_resync_calendar_request'), 'Expected isolated Re-sync request helper to exist.');

    $createVendor = static function (string $title) use ($registerPost): int {
        $vendorId = wp_insert_post(array(
            'post_type' => 'vms_vendor',
            'post_status' => 'publish',
            'post_title' => $title,
        ), true);
        if (is_wp_error($vendorId) || (int) $vendorId <= 0) {
            throw new RuntimeException('Failed to create test vendor.');
        }

        return $registerPost((int) $vendorId);
    };

    $createStaff = static function (string $title) use ($registerPost): int {
        $staffId = wp_insert_post(array(
            'post_type' => 'vms_staff',
            'post_status' => 'publish',
            'post_title' => $title,
        ), true);
        if (is_wp_error($staffId) || (int) $staffId <= 0) {
            throw new RuntimeException('Failed to create test staff record.');
        }

        return $registerPost((int) $staffId);
    };

    $createPlan = static function (string $title, string $content = '') use ($registerPost): int {
        $planId = wp_insert_post(array(
            'post_type' => 'vms_event_plan',
            'post_status' => 'publish',
            'post_title' => $title,
            'post_content' => $content,
        ), true);
        if (is_wp_error($planId) || (int) $planId <= 0) {
            throw new RuntimeException('Failed to create test Event Plan.');
        }

        return $registerPost((int) $planId);
    };

    $createTecEvent = static function (string $title, string $content = '') use ($registerPost): int {
        $tecId = wp_insert_post(array(
            'post_type' => 'tribe_events',
            'post_status' => 'publish',
            'post_title' => $title,
            'post_content' => $content,
        ), true);
        if (is_wp_error($tecId) || (int) $tecId <= 0) {
            throw new RuntimeException('Failed to create test TEC event.');
        }

        $tecId = $registerPost((int) $tecId);
        update_post_meta($tecId, '_EventStartDate', '2026-01-01 20:00:00');
        update_post_meta($tecId, '_EventEndDate', '2026-01-01 23:00:00');
        update_post_meta($tecId, '_checkin_close_at', '2026-01-01 18:00:00');
        update_post_meta($tecId, '_EventOrganizerID', 777);

        return $tecId;
    };

    $normalizeValue = static function ($value) use (&$normalizeValue) {
        if (is_array($value)) {
            $isList = array_keys($value) === range(0, count($value) - 1);
            $next = array();
            foreach ($value as $key => $child) {
                $next[$key] = $normalizeValue($child);
            }
            if ($isList) {
                usort($next, static function ($left, $right): int {
                    return strcmp(wp_json_encode($left), wp_json_encode($right));
                });
            } else {
                ksort($next);
            }
            return $next;
        }

        return $value;
    };

    $tecMetaKey = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'tec_event_id') ?: '_vms_tec_event_id') : '_vms_tec_event_id';
    $checkinMetaKey = function_exists('bvmgr_event_plan_checkin_close_meta_key') ? bvmgr_event_plan_checkin_close_meta_key() : '_checkin_close_at';
    $legacyTicketKeys = function_exists('bvmgr_event_plan_legacy_ticket_meta_keys')
        ? (array) bvmgr_event_plan_legacy_ticket_meta_keys()
        : array('_vms_price_ga', '_vms_enable_tables');
    $ticketConfigKey = function_exists('vms_ticketing_v2_k') ? (string) vms_ticketing_v2_k('config') : '_vms_ticketing_v2_config';

    $capturePlanState = static function (int $planId) use ($legacyTicketKeys, $ticketConfigKey, $normalizeValue): array {
        $legacyMeta = array();
        foreach ($legacyTicketKeys as $legacyKey) {
            $legacyMeta[(string) $legacyKey] = get_post_meta($planId, (string) $legacyKey, true);
        }

        $secondaryIds = get_post_meta($planId, '_vms_secondary_vendor_ids', true);
        if (!is_array($secondaryIds)) {
            $secondaryIds = array();
        }

        $staffAssignments = get_post_meta($planId, '_vms_staff_assignments', true);
        if (!is_array($staffAssignments)) {
            $staffAssignments = array();
        }

        return $normalizeValue(array(
            'core_details' => array(
                'title' => (string) get_post_field('post_title', $planId),
                'content' => (string) get_post_field('post_content', $planId),
                'event_date' => (string) get_post_meta($planId, '_vms_event_date', true),
                'start_time' => (string) get_post_meta($planId, '_vms_start_time', true),
                'end_time' => (string) get_post_meta($planId, '_vms_end_time', true),
                'band_vendor_id' => (int) get_post_meta($planId, '_vms_band_vendor_id', true),
                'comp_structure' => (string) get_post_meta($planId, '_vms_comp_structure', true),
                'flat_fee_amount' => (string) get_post_meta($planId, '_vms_flat_fee_amount', true),
            ),
            'secondary_vendors' => array(
                'type' => (string) get_post_meta($planId, '_vms_secondary_vendor_type', true),
                'ids' => array_map('intval', $secondaryIds),
                'index_ids' => array_map('intval', (array) get_post_meta($planId, '_vms_secondary_vendor_id', false)),
            ),
            'ticketing_overrides' => array(
                'enabled_override' => (string) get_post_meta($planId, '_vms_ticketing_enabled_override', true),
                'layout_override' => (string) get_post_meta($planId, '_vms_ticket_ui_layout_override', true),
                'heading_override' => (string) get_post_meta($planId, '_vms_ticket_ui_addons_heading_override', true),
                'config' => get_post_meta($planId, $ticketConfigKey, true),
            ),
            'staffing' => array(
                'assignments' => $staffAssignments,
            ),
            'cancellation' => array(
                'policy' => (string) get_post_meta($planId, '_vms_cancel_policy', true),
                'reason_code' => (string) get_post_meta($planId, '_vms_cancel_reason_code', true),
                'reason_note' => (string) get_post_meta($planId, '_vms_cancel_reason_note', true),
                'vendor_message' => (string) get_post_meta($planId, '_vms_cancel_vendor_message', true),
            ),
            'legacy_ticket_meta' => $legacyMeta,
        ));
    };

    $captureResyncOwnedState = static function (int $planId, int $tecId): array {
        return array(
            'plan' => array(
                'tec_event_url' => (string) get_post_meta($planId, '_vms_tec_event_url', true),
                'last_sync_signature' => (string) get_post_meta($planId, '_vms_tec_last_sync_signature', true),
                'last_sync_at' => (int) get_post_meta($planId, '_vms_tec_last_sync_at', true),
                'calendar_maintenance_queued_at' => (int) get_post_meta($planId, '_vms_calendar_maintenance_queued_at', true),
                'calendar_maintenance_reason' => (string) get_post_meta($planId, '_vms_calendar_maintenance_reason', true),
            ),
            'tec' => array(
                'checkin_close_at' => (string) get_post_meta($tecId, '_checkin_close_at', true),
                'event_organizer_id' => (string) get_post_meta($tecId, '_EventOrganizerID', true),
                'title' => (string) get_post_field('post_title', $tecId),
                'content' => (string) get_post_field('post_content', $tecId),
                'start_date' => (string) get_post_meta($tecId, '_EventStartDate', true),
                'end_date' => (string) get_post_meta($tecId, '_EventEndDate', true),
                'start_time' => (string) get_post_meta($tecId, '_EventStartTime', true),
                'end_time' => (string) get_post_meta($tecId, '_EventEndTime', true),
            ),
        );
    };

    $seedLegacyTicketMeta = static function (int $planId) use ($legacyTicketKeys): void {
        foreach (array_slice($legacyTicketKeys, 0, 3) as $index => $legacyKey) {
            update_post_meta($planId, (string) $legacyKey, 'legacy_' . ($index + 1));
        }
    };

    $seedValidPlan = static function (int $planId, int $vendorId, int $staffId, int $secondaryVendorId, int $tecId) use ($tecMetaKey, $checkinMetaKey, $ticketConfigKey, $seedLegacyTicketMeta): void {
        update_post_meta($planId, '_vms_event_date', '2026-06-12');
        update_post_meta($planId, '_vms_start_time', '19:00');
        update_post_meta($planId, '_vms_end_time', '22:00');
        update_post_meta($planId, '_vms_band_vendor_id', $vendorId);
        update_post_meta($planId, '_vms_comp_structure', 'flat_fee');
        update_post_meta($planId, '_vms_flat_fee_amount', '500.00');
        update_post_meta($planId, '_vms_secondary_vendor_type', 'food_truck');
        update_post_meta($planId, '_vms_secondary_vendor_ids', array($secondaryVendorId));
        delete_post_meta($planId, '_vms_secondary_vendor_id');
        add_post_meta($planId, '_vms_secondary_vendor_id', $secondaryVendorId, false);
        update_post_meta($planId, '_vms_staff_assignments', array(
            97 => array($staffId),
        ));
        update_post_meta($planId, '_vms_ticketing_enabled_override', 'on');
        update_post_meta($planId, '_vms_ticket_ui_layout_override', 'progressive');
        update_post_meta($planId, '_vms_ticket_ui_addons_heading_override', 'Fire Pits & Tables');
        update_post_meta($planId, '_vms_cancel_policy', 'status_only');
        update_post_meta($planId, '_vms_cancel_reason_code', 'other');
        update_post_meta($planId, '_vms_cancel_reason_note', 'Do not alter this note.');
        update_post_meta($planId, '_vms_cancel_vendor_message', 'Do not alter this vendor message.');
        update_post_meta($planId, $tecMetaKey, $tecId);
        update_post_meta($planId, '_vms_tec_event_url', 'https://example.com/old-url');
        update_post_meta($planId, '_vms_tec_last_sync_signature', 'old_signature');
        update_post_meta($planId, '_vms_tec_last_sync_at', 1);
        update_post_meta($planId, $checkinMetaKey, '2026-06-12 18:00:00');
        delete_post_meta($planId, '_vms_calendar_maintenance_queued_at');
        delete_post_meta($planId, '_vms_calendar_maintenance_reason');
        update_post_meta($planId, $ticketConfigKey, array(
            'mode' => 'vms_managed',
            'tickets' => array(
                array(
                    'enabled' => true,
                    'title' => 'GA Ticket',
                ),
            ),
            'entitlements' => array(),
        ));
        $seedLegacyTicketMeta($planId);
    };

    $runBroadSaveWithLegacyResyncAction = static function (int $planId, array $overrides = array()) use ($assert): void {
        $reflection = new ReflectionClass('BVMGR_Admin_Event_Plans');
        /** @var BVMGR_Admin_Event_Plans $admin */
        $admin = $reflection->newInstanceWithoutConstructor();
        $defaults = array(
            'vms_event_plan_details_nonce' => wp_create_nonce('vms_save_event_plan_details'),
            'post_ID' => $planId,
            'original_post_status' => 'publish',
            'vms_event_plan_action' => 'resync_to_calendar',
        );
        $_POST = array_merge($defaults, $overrides);
        $_GET = array();
        $_REQUEST = $_POST;

        $admin->save_event_plan_meta($planId, get_post($planId));
        clean_post_cache($planId);
        $assert(true, 'Legacy broad-save neutralization helper should complete.');
    };

    $vendorId = $createVendor('Calendar Resync Primary Vendor');
    $secondaryVendorId = $createVendor('Calendar Resync Secondary Vendor');
    $staffId = $createStaff('Calendar Resync Staff');
    $planId = $createPlan('Saved Calendar Resync Plan Title', 'Saved Event Plan body copy.');
    $tecId = $createTecEvent('Old TEC Event Title', 'Old TEC event body.');
    $registerMaintenancePair($planId, $tecId);
    $seedValidPlan($planId, $vendorId, $staffId, $secondaryVendorId, $tecId);
    $hasEffectiveTickets = function_exists('bvmgr_event_plan_has_effective_tickets') && bvmgr_event_plan_has_effective_tickets($planId);

    $savedHookCount = 0;
    $savedHookProbe = static function () use (&$savedHookCount): void {
        $savedHookCount++;
    };
    add_action('vms_event_plan_saved', $savedHookProbe, 999, 2);

    $clearNotices();
    $beforePlanState = $capturePlanState($planId);
    $beforeResyncState = $captureResyncOwnedState($planId, $tecId);

    $successResult = bvmgr_event_plan_handle_resync_calendar_request(array(
        '_vms_resync_calendar_nonce' => wp_create_nonce('vms_resync_calendar'),
        'post_id' => $planId,
        'redirect_to' => admin_url('post.php?post=' . $planId . '&action=edit'),
        'source' => 'advanced_controls',
        'post_title' => 'Unsaved Title That Must Not Win',
        'content' => 'Unsaved content that must not be published to TEC.',
        'vms_event_date' => '2026-09-01',
        'vms_start_time' => '10:00',
        'vms_end_time' => '11:00',
        'vms_ticketing_enabled_override' => 'off',
        'vms_cancel_policy' => 'stop_sales_auto_refund',
    ), false);

    clean_post_cache($planId);
    clean_post_cache($tecId);

    $assert(!empty($successResult['ok']), 'Isolated Re-sync should succeed for a linked valid Event Plan.');
    $assert($successResult['notice_message'] === 'Calendar event re-synced successfully.', 'Success notice should match the existing Re-sync success copy.');

    $afterPlanState = $capturePlanState($planId);
    $afterResyncState = $captureResyncOwnedState($planId, $tecId);

    $assert(wp_json_encode($beforePlanState['core_details']) === wp_json_encode($afterPlanState['core_details']), 'Isolated Re-sync should not alter core Event Plan details.');
    $assert(wp_json_encode($beforePlanState['secondary_vendors']) === wp_json_encode($afterPlanState['secondary_vendors']), 'Isolated Re-sync should not alter Secondary Vendors.');
    $assert(wp_json_encode($beforePlanState['ticketing_overrides']) === wp_json_encode($afterPlanState['ticketing_overrides']), 'Isolated Re-sync should not alter ticketing overrides.');
    $assert(wp_json_encode($beforePlanState['staffing']) === wp_json_encode($afterPlanState['staffing']), 'Isolated Re-sync should not alter staffing data.');
    $assert(wp_json_encode($beforePlanState['cancellation']) === wp_json_encode($afterPlanState['cancellation']), 'Isolated Re-sync should not alter cancellation fields.');
    $assert(wp_json_encode($beforePlanState['legacy_ticket_meta']) === wp_json_encode($afterPlanState['legacy_ticket_meta']), 'Isolated Re-sync should not clean or alter legacy ticketing meta.');

    $assert($afterResyncState['plan']['tec_event_url'] === get_permalink($tecId), 'Isolated Re-sync should refresh the stored linked TEC event URL.');
    $assert($afterResyncState['plan']['last_sync_signature'] !== '' && $afterResyncState['plan']['last_sync_signature'] !== $beforeResyncState['plan']['last_sync_signature'], 'Isolated Re-sync should update the TEC sync signature.');
    $assert($afterResyncState['plan']['last_sync_at'] > $beforeResyncState['plan']['last_sync_at'], 'Isolated Re-sync should update the TEC sync timestamp.');
    $assert($afterResyncState['tec']['checkin_close_at'] === (string) get_post_meta($planId, $checkinMetaKey, true), 'Isolated Re-sync should sync the resolved Event Plan check-in close time to TEC.');
    if ($hasEffectiveTickets) {
        $assert($afterResyncState['plan']['calendar_maintenance_queued_at'] > 0, 'Isolated Re-sync should queue calendar maintenance when effective tickets exist.');
        $assert($afterResyncState['plan']['calendar_maintenance_reason'] === 'resync_to_calendar', 'Isolated Re-sync should preserve the calendar maintenance reason when maintenance applies.');
        $assert(false !== wp_next_scheduled('vms_event_plan_calendar_maintenance', array($planId, $tecId)), 'Isolated Re-sync should preserve the calendar maintenance scheduled event when maintenance applies.');
    } else {
        $assert($afterResyncState['plan']['calendar_maintenance_queued_at'] === $beforeResyncState['plan']['calendar_maintenance_queued_at'], 'Isolated Re-sync should leave calendar maintenance queue metadata unchanged when no effective tickets exist.');
        $assert($afterResyncState['plan']['calendar_maintenance_reason'] === $beforeResyncState['plan']['calendar_maintenance_reason'], 'Isolated Re-sync should leave calendar maintenance reason unchanged when no effective tickets exist.');
    }

    $assert($afterResyncState['tec']['event_organizer_id'] === '', 'Isolated Re-sync should still clear TEC organizer linkage metadata.');
    $assert($afterResyncState['tec']['title'] === 'Saved Calendar Resync Plan Title', 'TEC title should reflect the saved Event Plan title, not unsaved request data.');
    $assert($afterResyncState['tec']['content'] === 'Saved Event Plan body copy.', 'TEC content should reflect the saved Event Plan content, not unsaved request data.');
    $assert(strpos($afterResyncState['tec']['start_date'], '2026-06-12') === 0, 'TEC start date should reflect the saved Event Plan date.');
    $startTimeOutput = $afterResyncState['tec']['start_time'] !== ''
        ? $afterResyncState['tec']['start_time']
        : $afterResyncState['tec']['start_date'];
    $endTimeOutput = $afterResyncState['tec']['end_time'] !== ''
        ? $afterResyncState['tec']['end_time']
        : $afterResyncState['tec']['end_date'];
    $assert(strpos($startTimeOutput, '19:00') !== false, 'TEC start time should reflect the saved Event Plan start time.');
    $assert(strpos($endTimeOutput, '22:00') !== false, 'TEC end time should reflect the saved Event Plan end time.');
    $assert($savedHookCount === 0, 'Isolated Re-sync should not fire vms_event_plan_saved.');

    $clearNotices();
    $preNeutralizePlanState = $capturePlanState($planId);
    $preNeutralizeResyncState = $captureResyncOwnedState($planId, $tecId);
    $runBroadSaveWithLegacyResyncAction($planId, array(
        'vms_event_date' => '2027-01-01',
        'vms_start_time' => '08:00',
        'vms_end_time' => '09:00',
        'vms_ticketing_enabled_override' => 'off',
        'vms_cancel_vendor_message' => 'Unsaved cancellation message',
    ));
    $postNeutralizePlanState = $capturePlanState($planId);
    $postNeutralizeResyncState = $captureResyncOwnedState($planId, $tecId);
    $assert(wp_json_encode($preNeutralizePlanState) === wp_json_encode($postNeutralizePlanState), 'Legacy broad-save Re-sync action should now be neutralized before any unrelated Event Plan fields are saved.');
    $assert(wp_json_encode($preNeutralizeResyncState) === wp_json_encode($postNeutralizeResyncState), 'Legacy broad-save Re-sync action should no longer touch calendar sync state.');

    $missingLinkPlanId = $createPlan('Missing TEC Link Plan', 'Missing link body');
    update_post_meta($missingLinkPlanId, '_vms_event_date', '2026-06-13');
    update_post_meta($missingLinkPlanId, '_vms_start_time', '19:00');
    update_post_meta($missingLinkPlanId, '_vms_end_time', '21:00');
    update_post_meta($missingLinkPlanId, '_vms_band_vendor_id', $vendorId);
    update_post_meta($missingLinkPlanId, '_vms_comp_structure', 'flat_fee');
    update_post_meta($missingLinkPlanId, '_vms_flat_fee_amount', '250.00');
    $beforeMissingLinkState = $capturePlanState($missingLinkPlanId);
    $clearNotices();
    $missingLinkResult = bvmgr_event_plan_handle_resync_calendar_request(array(
        '_vms_resync_calendar_nonce' => wp_create_nonce('vms_resync_calendar'),
        'post_id' => $missingLinkPlanId,
        'redirect_to' => admin_url('post.php?post=' . $missingLinkPlanId . '&action=edit'),
        'source' => 'advanced_controls',
    ), false);
    $afterMissingLinkState = $capturePlanState($missingLinkPlanId);
    $assert(empty($missingLinkResult['ok']), 'Isolated Re-sync should fail when no linked TEC event exists.');
    $assert($missingLinkResult['notice_message'] === 'No linked calendar event found. Use “Publish Now” first.', 'Missing-link failure should preserve the existing Re-sync error copy.');
    $assert(wp_json_encode($beforeMissingLinkState) === wp_json_encode($afterMissingLinkState), 'Missing-link failure should not alter Event Plan fields.');

    $invalidPlanId = $createPlan('Validation Failure Plan', 'Validation failure body');
    $invalidTecId = $createTecEvent('Validation Failure TEC Event', 'Validation failure TEC body');
    $registerMaintenancePair($invalidPlanId, $invalidTecId);
    update_post_meta($invalidPlanId, $tecMetaKey, $invalidTecId);
    $beforeInvalidPlanState = $capturePlanState($invalidPlanId);
    $beforeInvalidTecState = $captureResyncOwnedState($invalidPlanId, $invalidTecId);
    $clearNotices();
    $validationResult = bvmgr_event_plan_handle_resync_calendar_request(array(
        '_vms_resync_calendar_nonce' => wp_create_nonce('vms_resync_calendar'),
        'post_id' => $invalidPlanId,
        'redirect_to' => admin_url('post.php?post=' . $invalidPlanId . '&action=edit'),
        'source' => 'advanced_controls',
    ), false);
    $afterInvalidPlanState = $capturePlanState($invalidPlanId);
    $afterInvalidTecState = $captureResyncOwnedState($invalidPlanId, $invalidTecId);
    $assert(empty($validationResult['ok']), 'Isolated Re-sync should fail validation for an incomplete Event Plan.');
    $assert(strpos($validationResult['notice_message'], 'Cannot re-sync:') === 0, 'Validation failure should preserve the existing Re-sync validation error prefix.');
    $assert(wp_json_encode($beforeInvalidPlanState) === wp_json_encode($afterInvalidPlanState), 'Validation failure should not alter Event Plan fields.');
    $assert(wp_json_encode($beforeInvalidTecState) === wp_json_encode($afterInvalidTecState), 'Validation failure should not alter TEC state.');

    remove_action('vms_event_plan_saved', $savedHookProbe, 999);

    fwrite(STDOUT, "event plan calendar resync isolated: PASS\n");
} catch (Throwable $e) {
    fwrite(STDERR, 'event plan calendar resync isolated: FAIL - ' . $e->getMessage() . "\n");
    $cleanup();
    exit(1);
}

$cleanup();
