<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap-wordpress.php';
vms_tests_require_wordpress(__DIR__);

if (!class_exists('BVMGR_Admin_Event_Plans')) {
    require_once dirname(__DIR__) . '/vendor-management-system.php';
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$createdPosts = array();
$registerPost = static function (int $postId) use (&$createdPosts): int {
    $createdPosts[] = $postId;
    return $postId;
};

$cleanup = static function () use (&$createdPosts): void {
    foreach (array_reverse($createdPosts) as $postId) {
        wp_delete_post((int) $postId, true);
    }
    delete_transient('vms_event_plan_notices_' . get_current_user_id());
};

try {
    wp_set_current_user(1);
    $assert(function_exists('bvmgr_ticketing_v2_plan_calendar_alignment'), 'Calendar alignment guard is unavailable.');
    $assert(function_exists('bvmgr_ticketing_v2_sync_mapped_ticket_sales_windows_for_calendar_change'), 'Calendar-derived ticket sync is unavailable.');
    $assert(function_exists('bvmgr_ticketing_v2_has_purchasable_qualifying_native_ticket'), 'Native qualifying-ticket availability helper is unavailable.');
    $assert(class_exists('WC_Product_Simple'), 'WooCommerce is unavailable.');
    $assert(class_exists('Tribe__Tickets__Tickets'), 'Event Tickets is unavailable.');

    $oldStartTs = strtotime('+45 days 7:00pm');
    $oldEndTs = strtotime('+45 days 10:00pm');
    $newStartTs = strtotime('+52 days 7:00pm');
    $newEndTs = strtotime('+52 days 10:00pm');
    $oldDate = wp_date('Y-m-d', $oldStartTs);
    $newDate = wp_date('Y-m-d', $newStartTs);
    $salesStart = wp_date('Y-m-d H:i:s', strtotime('-1 day'));
    $oldEnd = $oldDate . ' 22:00:00';
    $newEnd = $newDate . ' 22:00:00';

    $planId = wp_insert_post(array(
        'post_type' => 'vms_event_plan',
        'post_status' => 'publish',
        'post_title' => 'Ticket date synchronization regression',
    ), true);
    $assert(!is_wp_error($planId) && (int) $planId > 0, 'Could not create Event Plan fixture.');
    $planId = $registerPost((int) $planId);

    $eventId = tribe_create_event(array(
        'post_status' => 'publish',
        'post_title' => 'Ticket date synchronization TEC fixture',
        'EventStartDate' => $oldDate,
        'EventEndDate' => $oldDate,
        'EventStartTime' => '19:00',
        'EventEndTime' => '22:00',
        'EventAllDay' => false,
    ));
    $assert(!is_wp_error($eventId) && (int) $eventId > 0, 'Could not create TEC fixture.');
    $eventId = $registerPost((int) $eventId);

    update_post_meta($planId, '_vms_event_plan_status', 'published');
    update_post_meta($planId, '_vms_event_date', $oldDate);
    update_post_meta($planId, '_vms_start_time', '19:00');
    update_post_meta($planId, '_vms_end_time', '22:00');
    update_post_meta($planId, '_vms_ticketing_enabled_override', 'on');
    update_post_meta($planId, '_vms_ticketing_sales_mode', 'serenade_range');
    update_post_meta($planId, '_vms_tec_event_id', $eventId);
    update_post_meta($eventId, '_EventTimezone', wp_timezone_string());
    update_post_meta($eventId, '_tribe_default_ticket_provider', 'Tribe__Tickets_Plus__Commerce__WooCommerce__Main');

    $ticket = new WC_Product_Simple();
    $ticket->set_name('General Admission');
    $ticket->set_status('publish');
    $ticket->set_regular_price('20');
    $ticket->set_price('20');
    $ticket->set_virtual(true);
    $ticket->set_catalog_visibility('hidden');
    $ticket->set_manage_stock(true);
    $ticket->set_stock_quantity(50);
    $ticketId = $registerPost((int) $ticket->save());
    $assert($ticketId > 0, 'Could not create ticket product fixture.');
    update_post_meta($ticketId, '_tribe_wooticket_for_event', $eventId);
    update_post_meta($ticketId, '_ticket_start_date', $salesStart);
    update_post_meta($ticketId, '_ticket_end_date', $oldEnd);
    update_post_meta($ticketId, '_vms_event_plan_id', $planId);
    update_post_meta($ticketId, '_vms_product_role', 'ga_ticket');
    update_post_meta($ticketId, '_vms_ticket_key', 'ga');

    $makeAddon = static function (string $name) use ($registerPost, $planId): int {
        $product = new WC_Product_Simple();
        $product->set_name($name);
        $product->set_status('publish');
        $product->set_regular_price('25');
        $product->set_price('25');
        $product->set_virtual(true);
        $product->set_catalog_visibility('hidden');
        $product->set_manage_stock(true);
        $product->set_stock_quantity(5);
        $productId = $registerPost((int) $product->save());
        update_post_meta($productId, '_vms_event_plan_id', $planId);
        update_post_meta($productId, '_vms_product_role', 'entitlement');
        return $productId;
    };
    $gatedAddonId = $makeAddon('Gated Table');
    $ungatedAddonId = $makeAddon('Ungated Pool');

    $configForEnd = static function (string $salesEnd) use ($salesStart): array {
        return array(
            'mode' => 'vms_managed',
            'tickets' => array(array(
                'enabled' => true,
                'ticket_key' => 'ga',
                'title' => 'General Admission',
                'price' => '20',
                'inventory_total' => 50,
                'sales_start' => $salesStart,
                'sales_end' => $salesEnd,
                'visibility_mode' => 'public',
                'counts_toward_unlock' => true,
            )),
            'entitlements' => array(
                array(
                    'enabled' => true,
                    'entitlement_id' => 'gated_table',
                    'label' => 'Gated Table',
                    'price' => '25',
                    'capacity' => 5,
                    'eligibility' => array('pool_key' => 'tables', 'min_ga_per_unit' => 2),
                ),
                array(
                    'enabled' => true,
                    'entitlement_id' => 'ungated_pool',
                    'label' => 'Ungated Pool',
                    'price' => '25',
                    'capacity' => 5,
                    'eligibility' => array('pool_key' => '', 'min_ga_per_unit' => 0),
                ),
            ),
        );
    };

    bvmgr_ticketing_v2_set_config($planId, $configForEnd($oldEnd));
    bvmgr_ticketing_v2_set_sync($planId, array(
        'map' => array(
            'ga' => array('woo_product_id' => $ticketId),
            'tickets' => array(
                'ga' => array(
                    'woo_product_id' => $ticketId,
                    'ticket_key' => 'ga',
                    'counts_toward_unlock' => 1,
                    'last_sync_hash' => bvmgr_ticketing_v2_hash_ticket(bvmgr_ticketing_v2_get_config($planId)['tickets'][0]),
                ),
            ),
            'entitlements' => array(
                'gated_table' => array('woo_product_id' => $gatedAddonId),
                'ungated_pool' => array('woo_product_id' => $ungatedAddonId),
            ),
        ),
    ));

    $alignment = bvmgr_ticketing_v2_plan_calendar_alignment($planId, $eventId);
    $assert(!empty($alignment['aligned']), 'Initial Event Plan and TEC occurrence should align: ' . wp_json_encode($alignment));
    $assert(bvmgr_ticketing_v2_has_purchasable_qualifying_native_ticket($eventId, $planId), 'Future native GA should be purchasable and qualifying.');
    $availableMarkup = bvmgr_ticketing_v2_render_entitlements_block($eventId, $planId);
    $assert(strpos($availableMarkup, 'Gated Table') !== false && strpos($availableMarkup, 'Ungated Pool') !== false, 'Available native GA should expose gated and ungated add-ons.');

    // Reproduce the defect: the plan/config move, while TEC and product windows remain old.
    update_post_meta($planId, '_vms_event_date', $newDate);
    bvmgr_ticketing_v2_set_config($planId, $configForEnd($newEnd));
    $mismatched = bvmgr_ticketing_v2_plan_calendar_alignment($planId, $eventId);
    $assert(!empty($mismatched['checkable']) && empty($mismatched['aligned']), 'Changed Event Plan should be detected as out of sync with TEC.');
    $preview = bvmgr_ticketing_v2_preview_sync($planId);
    $assert(!empty($preview['blocked']), 'Native ticket Preview must block while the calendar occurrence is stale.');
    delete_transient('vms_tix_v2_prev_' . sanitize_key((string) ($preview['preview_id'] ?? '')));
    $guardPreviewId = 'date_alignment_guard_' . wp_generate_password(8, false, false);
    set_transient('vms_tix_v2_prev_' . sanitize_key($guardPreviewId), array(
        'version' => 2,
        'plan_id' => $planId,
        'user_id' => get_current_user_id(),
        'tec_event_id' => $eventId,
        'mode' => 'vms_managed',
        'config_hash' => bvmgr_ticketing_v2_hash_config_for_sync(bvmgr_ticketing_v2_get_config($planId)),
        'actions' => array(),
        'blocked' => false,
    ), 5 * MINUTE_IN_SECONDS);
    $guardCommit = bvmgr_ticketing_v2_commit_sync($planId, $guardPreviewId, array('phase' => 'prepare'));
    $assert(strpos((string) wp_json_encode($guardCommit), 'calendar_event_out_of_sync') !== false, 'Commit did not independently reject stale calendar data.');
    delete_transient('vms_tix_v2_prev_' . sanitize_key($guardPreviewId));

    // Exercise the post-calendar phase used by Publish Now/Re-sync after TEC has
    // persisted the new occurrence.
    update_post_meta($eventId, '_EventStartDate', $newDate . ' 19:00:00');
    update_post_meta($eventId, '_EventEndDate', $newEnd);
    clean_post_cache($eventId);
    $assert(
        bvmgr_event_plan_sync_ticket_windows_after_calendar_change($planId, $eventId, true, false),
        'Pre-closure calendar-derived ticket synchronization failed.'
    );
    $alignedAfterPublish = bvmgr_ticketing_v2_plan_calendar_alignment($planId, $eventId);
    $assert(!empty($alignedAfterPublish['aligned']), 'Calendar occurrence did not align after Publish Now: ' . wp_json_encode($alignedAfterPublish));
    $assert((string) get_post_meta($ticketId, '_ticket_end_date', true) === $newEnd, 'Publish Now did not re-derive the mapped ticket sale end.');
    $repair = bvmgr_ticketing_v2_inspect_enabled_ticket_product($ticketId, bvmgr_ticketing_v2_get_config($planId)['tickets'][0]);
    $assert(empty($repair['needs_sales_window_repair']), 'Synchronized ticket still reports sale-window drift.');

    // The same helper must not create a general past-event reopening path.
    update_post_meta($ticketId, '_ticket_end_date', $oldEnd);
    $closedResult = bvmgr_ticketing_v2_sync_mapped_ticket_sales_windows_for_calendar_change($planId, $eventId, true);
    $assert(!empty($closedResult['skipped']) && ($closedResult['reason'] ?? '') === 'completed_event_not_reopened', 'Completed occurrence was not protected from automatic reopening.');
    $assert((string) get_post_meta($ticketId, '_ticket_end_date', true) === $oldEnd, 'Completed occurrence unexpectedly rewrote its ticket sale end.');
    $assert(bvmgr_event_plan_sync_ticket_windows_after_calendar_change($planId, $eventId, true, true), 'Completed occurrence guard wrapper failed.');
    $rescheduleGuard = get_post_meta($planId, '_vms_ticketing_reschedule_required_v1', true);
    $assert(is_array($rescheduleGuard) && absint($rescheduleGuard['tec_event_id'] ?? 0) === $eventId, 'Completed occurrence did not persist the explicit-reschedule guard.');
    $completedPreview = bvmgr_ticketing_v2_preview_sync($planId);
    $assert(!empty($completedPreview['blocked']), 'Completed occurrence guard did not block a later native ticket Preview.');
    delete_transient('vms_tix_v2_prev_' . sanitize_key((string) ($completedPreview['preview_id'] ?? '')));
    delete_post_meta($planId, '_vms_ticketing_reschedule_required_v1');

    // Expired qualifying tickets suppress only GA-gated add-ons in native mode.
    update_post_meta($ticketId, '_ticket_end_date', wp_date('Y-m-d H:i:s', strtotime('-1 hour')));
    bvmgr_ticketing_v2_invalidate_calendar_ticket_caches($eventId, array($ticketId));
    $assert(!bvmgr_ticketing_v2_has_purchasable_qualifying_native_ticket($eventId, $planId), 'Expired GA was incorrectly treated as purchasable.');
    $expiredMarkup = bvmgr_ticketing_v2_render_entitlements_block($eventId, $planId);
    $assert(strpos($expiredMarkup, 'Gated Table') === false, 'GA-gated add-on remained actionable without a purchasable qualifying ticket.');
    $assert(strpos($expiredMarkup, 'Ungated Pool') !== false, 'Ungated add-on disappeared with an unavailable qualifying ticket.');

    // External mode retains its accepted native add-on suppression; no cross-provider
    // add-on purchasing behavior is invented.
    update_post_meta($planId, '_vms_ticketing_sales_mode', 'external');
    update_post_meta($planId, '_vms_external_ticket_url', 'https://tickets.example.test/date-sync');
    $externalMarkup = bvmgr_ticketing_v2_render_entitlements_block($eventId, $planId);
    $assert($externalMarkup === '', 'External mode native add-on suppression changed.');

    fwrite(STDOUT, "PASS: pre-closure occurrence changes synchronize TEC/ticket windows, stale commits block, completed events do not reopen, and gated add-ons follow native ticket availability.\n");
} finally {
    $cleanup();
}
