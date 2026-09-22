<?php
declare(strict_types=1);

// Load WordPress without the operational plugin set so this test exercises the
// candidate checkout rather than the installed Local BVM copy.
if (!defined('WP_INSTALLING')) {
    define('WP_INSTALLING', true);
}

require_once __DIR__ . '/bootstrap-wordpress.php';
vms_tests_require_wordpress(__DIR__);

if (!function_exists('bvmgr_ticketing_v2_front_ui_settings')) {
    require_once dirname(__DIR__) . '/backstage-venue-manager.php';
}
if (!function_exists('bvmgr_event_plan_is_externally_ticketed')) {
    require_once dirname(__DIR__) . '/includes/helpers.php';
}
if (!function_exists('bvmgr_ticketing_v2_front_ui_settings')) {
    require_once dirname(__DIR__) . '/includes/integrations/ticketing-rules-v2.php';
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$originalSettings = get_option('vms_settings', array());
$originalUserId = get_current_user_id();
$createdPostIds = array();

$createPost = static function (string $postType, string $title) use (&$createdPostIds): int {
    $postId = wp_insert_post(array(
        'post_type' => $postType,
        'post_status' => 'publish',
        'post_title' => $title,
    ), true);
    if (is_wp_error($postId) || (int) $postId <= 0) {
        throw new RuntimeException('Could not create ticket authority fixture: ' . $title);
    }
    $createdPostIds[] = (int) $postId;
    return (int) $postId;
};

try {
    wp_set_current_user(1);
    $assert(current_user_can('manage_options'), 'The fixture requires an administrator test user.');

    $classicPlanId = $createPost('vms_event_plan', 'Ticket UI Classic Authority Fixture');
    update_option('vms_settings', array_merge((array) $originalSettings, array(
        'ticket_ui_layout' => 'classic',
        'ticket_ui_v2_admin_preview' => 1,
    )));
    $classic = bvmgr_ticketing_v2_front_ui_settings($classicPlanId);
    $assert(($classic['layout'] ?? '') === 'classic', 'Classic layout was not selected.');
    $assert(empty($classic['effective_v2']), 'Administrator preview overrode Classic/Safe Mode authority.');

    $progressivePlanId = $createPost('vms_event_plan', 'Ticket UI Progressive Authority Fixture');
    update_option('vms_settings', array_merge((array) $originalSettings, array(
        'ticket_ui_layout' => 'progressive',
        'ticket_ui_v2_admin_preview' => 1,
    )));
    $progressive = bvmgr_ticketing_v2_front_ui_settings($progressivePlanId);
    $assert(!empty($progressive['effective_v2']) && !empty($progressive['is_progressive']), 'Progressive layout did not select unified ownership.');

    $overridePlanId = $createPost('vms_event_plan', 'Ticket UI Classic Override Authority Fixture');
    update_post_meta($overridePlanId, '_vms_ticket_ui_layout_override', 'classic');
    $classicOverride = bvmgr_ticketing_v2_front_ui_settings($overridePlanId);
    $assert(empty($classicOverride['effective_v2']), 'A Classic Event Plan override did not force native authority.');

    $nativePlanId = $createPost('vms_event_plan', 'Ticket UI Native Suppression Fixture');
    $eventId = $createPost('tribe_events', 'Ticket UI Native Event Fixture');
    update_post_meta($nativePlanId, '_vms_tec_event_id', $eventId);
    update_post_meta($nativePlanId, '_vms_event_plan_status', 'published');
    update_post_meta($nativePlanId, '_vms_ticketing_sales_mode', 'serenade_range');
    $assert(!bvmgr_tec_event_suppresses_native_ticketing($eventId), 'A published native Event Plan suppressed TEC ticket rendering.');

    update_post_meta($nativePlanId, '_vms_event_plan_status', 'cancelled');
    $assert(bvmgr_tec_event_suppresses_native_ticketing($eventId), 'Cancelled-event suppression changed.');

    update_post_meta($nativePlanId, '_vms_event_plan_status', 'published');
    update_post_meta($nativePlanId, '_vms_ticketing_sales_mode', 'external');
    $assert(bvmgr_tec_event_suppresses_native_ticketing($eventId), 'External-ticketing suppression changed.');

    update_post_meta($nativePlanId, '_vms_ticketing_sales_mode', 'serenade_range');
    $assert(!bvmgr_tec_event_suppresses_native_ticketing($eventId), 'Returning to native sales did not restore TEC authority.');

    $ticketingRulesSource = (string) file_get_contents(dirname(__DIR__) . '/includes/integrations/ticketing-rules-v2.php');
    foreach (array(
        '$front_script_version',
        '$fallback_script_version',
        '$post_cart_offer_script_version',
        '$progressive_script_version',
    ) as $versionVariable) {
        $assert(
            strpos($ticketingRulesSource, $versionVariable . " = function_exists('bvmgr_asset_version')") !== false,
            $versionVariable . ' must resolve from the public BVM release version.'
        );
    }
    $assert(
        strpos($ticketingRulesSource, 'filemtime($front_script_path)') === false
            && strpos($ticketingRulesSource, 'filemtime($fallback_script_path)') === false
            && strpos($ticketingRulesSource, 'filemtime($post_cart_offer_script_path)') === false
            && strpos($ticketingRulesSource, 'filemtime($progressive_script_path)') === false,
        'Ticket JavaScript asset versions must not override BVMGR_VERSION with filesystem mtimes.'
    );

    echo "Ticket UI Classic/Progressive authority and TEC suppression contracts passed.\n";
} finally {
    update_option('vms_settings', $originalSettings);
    wp_set_current_user($originalUserId);
    foreach (array_reverse($createdPostIds) as $postId) {
        wp_delete_post($postId, true);
    }
}
