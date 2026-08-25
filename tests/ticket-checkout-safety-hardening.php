<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap-wordpress.php';
vms_tests_require_wordpress(__DIR__);

if (!class_exists('VMS_Admin_Event_Plans')) {
    require_once dirname(__DIR__) . '/backstage-venue-manager.php';
}

$assert = static function (bool $condition, string $message): void {
    if ($condition) {
        return;
    }

    throw new RuntimeException($message);
};

$createdPosts = array();
$createdUsers = array();
$registerPost = static function (int $postId) use (&$createdPosts): int {
    $createdPosts[] = $postId;
    return $postId;
};
$registerUser = static function (int $userId) use (&$createdUsers): int {
    $createdUsers[] = $userId;
    return $userId;
};

$cleanup = static function () use (&$createdPosts, &$createdUsers): void {
    if (function_exists('WC') && WC() && isset(WC()->cart) && WC()->cart) {
        WC()->cart->empty_cart();
    }

    foreach (array_reverse($createdPosts) as $postId) {
        wp_delete_post((int) $postId, true);
    }

    if (!function_exists('wp_delete_user')) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
    }
    foreach (array_reverse($createdUsers) as $userId) {
        wp_delete_user((int) $userId);
    }
};

try {
    wp_set_current_user(1);

    $assert(function_exists('vms_ticketing_v2_validate_product_sale_context'), 'Expected ticket sale-context helper to be loaded.');
    $assert(function_exists('vms_ticketing_v2_capture_cart_item_context'), 'Expected ticket cart-context capture helper to be loaded.');
    $assert(function_exists('vms_ticketing_v2_add_event_meta_to_cart_item'), 'Expected ticket cart item display helper to be loaded.');
    $assert(function_exists('vms_ticketing_claims_account_benefits_url'), 'Expected benefits URL helper to be loaded.');
    $assert(post_type_exists('tribe_events'), 'Expected The Events Calendar to be available.');
    $assert(class_exists('WooCommerce') && class_exists('WC_Product_Simple'), 'Expected WooCommerce to be available.');

    $planStatusKey = function_exists('vms_meta_key')
        ? (string) (vms_meta_key('event_plan', 'status') ?: '_vms_event_plan_status')
        : '_vms_event_plan_status';
    $planMetaKey = function_exists('vms_ticketing_v2_product_meta_key')
        ? (string) vms_ticketing_v2_product_meta_key('event_plan_id')
        : '_vms_event_plan_id';
    $roleMetaKey = function_exists('vms_ticketing_v2_product_meta_key')
        ? (string) vms_ticketing_v2_product_meta_key('product_role')
        : '_vms_product_role';
    $tecMetaKey = function_exists('vms_ticketing_b_meta_key')
        ? (string) vms_ticketing_b_meta_key('tec_event_id', '_vms_tec_event_id')
        : '_vms_tec_event_id';
    $visibilityMetaKey = function_exists('vms_ticketing_v2_product_meta_key')
        ? (string) vms_ticketing_v2_product_meta_key('ticketing_visibility_mode')
        : '_vms_ticketing_visibility_mode';
    $verifiedProgramMetaKey = function_exists('vms_ticketing_v2_product_meta_key')
        ? (string) vms_ticketing_v2_product_meta_key('ticketing_verified_program')
        : '_vms_ticketing_verified_program';
    $allowedProgramsMetaKey = function_exists('vms_ticketing_v2_product_meta_key')
        ? (string) vms_ticketing_v2_product_meta_key('ticketing_allowed_programs')
        : '_vms_ticketing_allowed_programs';
    $requireAssigneeEmailMetaKey = function_exists('vms_ticketing_v2_product_meta_key')
        ? (string) vms_ticketing_v2_product_meta_key('ticketing_require_assignee_email')
        : '_vms_ticketing_require_assignee_email';

    $errorNotices = static function (): array {
        if (!function_exists('wc_get_notices')) {
            return array();
        }

        $messages = array();
        foreach ((array) wc_get_notices('error') as $notice) {
            if (is_array($notice)) {
                $messages[] = trim(wp_strip_all_tags((string) ($notice['notice'] ?? '')));
            } else {
                $messages[] = trim(wp_strip_all_tags((string) $notice));
            }
        }

        return array_values(array_filter($messages, 'strlen'));
    };

    $futureStartTs = strtotime('+21 days 7:00pm');
    $futureEndTs = strtotime('+21 days 10:00pm');
    $pastStartTs = strtotime('-7 days 7:00pm');
    $pastEndTs = strtotime('-7 days 10:00pm');

    $planId = wp_insert_post(array(
        'post_type' => 'vms_event_plan',
        'post_status' => 'publish',
        'post_title' => 'Ticket Safety Hardening Plan',
    ), true);
    $assert(!is_wp_error($planId) && (int) $planId > 0, 'Failed to create test Event Plan.');
    $planId = $registerPost((int) $planId);

    update_post_meta($planId, $planStatusKey, 'published');
    update_post_meta($planId, '_vms_event_date', wp_date('Y-m-d', $futureStartTs));
    update_post_meta($planId, '_vms_start_time', wp_date('H:i', $futureStartTs));
    update_post_meta($planId, '_vms_end_time', wp_date('H:i', $futureEndTs));

    $eventId = wp_insert_post(array(
        'post_type' => 'tribe_events',
        'post_status' => 'publish',
        'post_title' => 'Ticket Safety Hardening Event',
    ), true);
    $assert(!is_wp_error($eventId) && (int) $eventId > 0, 'Failed to create test TEC event.');
    $eventId = $registerPost((int) $eventId);

    update_post_meta($eventId, '_EventStartDate', wp_date('Y-m-d H:i:s', $futureStartTs));
    update_post_meta($eventId, '_EventEndDate', wp_date('Y-m-d H:i:s', $futureEndTs));
    update_post_meta($planId, $tecMetaKey, $eventId);

    $product = new WC_Product_Simple();
    $product->set_name('Ticket Safety Hardening Ticket');
    $product->set_status('publish');
    $product->set_regular_price('30.00');
    $product->set_price('30.00');
    $productId = (int) $product->save();
    $assert($productId > 0, 'Failed to create test WooCommerce product.');
    $registerPost($productId);

    update_post_meta($productId, $planMetaKey, $planId);
    update_post_meta($productId, $roleMetaKey, 'ga_ticket');
    update_post_meta($productId, '_tribe_wooticket_for_event', $eventId);
    update_post_meta($productId, '_vms_ticket_key', 'veteran_admission');

    $liveContext = vms_ticketing_v2_validate_product_sale_context($productId, 0, 0, 'ga_ticket');
    $assert(!empty($liveContext['ok']), 'Live published event should remain purchasable.');

    $cartContext = vms_ticketing_v2_capture_cart_item_context(array(), $productId, 0);
    $snapshot = (array) ($cartContext['_vms_ticketing_context'] ?? array());
    $assert(absint($snapshot['event_plan_id'] ?? 0) === $planId, 'Cart context should persist the Event Plan ID.');
    $assert(absint($snapshot['tec_event_id'] ?? 0) === $eventId, 'Cart context should persist the TEC event ID.');
    $assert((string) ($snapshot['product_role'] ?? '') === 'ga_ticket', 'Cart context should persist the product role.');
    $assert(trim((string) ($snapshot['event_title_snapshot'] ?? '')) !== '', 'Cart context should persist an event title snapshot.');
    $assert(trim((string) ($snapshot['event_when_snapshot'] ?? '')) !== '', 'Cart context should persist an event time snapshot.');

    wp_set_current_user(0);
    $assert(vms_ticketing_claims_account_benefits_url() === '', 'Benefits URL should be hidden for logged-out contexts.');
    wp_set_current_user(1);

    $eligibleUserId = wp_insert_user(array(
        'user_login' => 'ticket-safety-eligible-' . wp_generate_password(8, false, false),
        'user_pass' => wp_generate_password(24, true, true),
        'user_email' => 'ticket-safety-eligible-' . wp_generate_password(6, false, false) . '@example.com',
        'role' => 'subscriber',
    ));
    $assert(!is_wp_error($eligibleUserId) && (int) $eligibleUserId > 0, 'Failed to create eligible verified user.');
    $eligibleUserId = $registerUser((int) $eligibleUserId);

    $nonQualifiedUserId = wp_insert_user(array(
        'user_login' => 'ticket-safety-basic-' . wp_generate_password(8, false, false),
        'user_pass' => wp_generate_password(24, true, true),
        'user_email' => 'ticket-safety-basic-' . wp_generate_password(6, false, false) . '@example.com',
        'role' => 'subscriber',
    ));
    $assert(!is_wp_error($nonQualifiedUserId) && (int) $nonQualifiedUserId > 0, 'Failed to create non-qualified user.');
    $nonQualifiedUserId = $registerUser((int) $nonQualifiedUserId);

    $assert(function_exists('vms_ticketing_verification_assign_program'), 'Expected verification program assignment helper to be loaded.');
    $assert(vms_ticketing_verification_assign_program($eligibleUserId, 'veteran', 'Ticket hardening regression', 1), 'Failed to assign veteran verification to the eligible user.');

    update_post_meta($productId, $visibilityMetaKey, 'verified');
    update_post_meta($productId, $verifiedProgramMetaKey, 'veteran');
    update_post_meta($productId, $allowedProgramsMetaKey, array('veteran'));
    update_post_meta($productId, $requireAssigneeEmailMetaKey, '0');

    wp_set_current_user(0);
    wc_clear_notices();
    $guestAddResult = vms_ticketing_v2_validate_add_to_cart(true, $productId, 1, 0, array(), array());
    $guestNotices = $errorNotices();
    $assert($guestAddResult === false, 'Guests should not be able to add verified tickets to cart.');
    $assert(!empty($guestNotices), 'Guest verified-ticket rejection should surface an actionable error.');

    wp_set_current_user($nonQualifiedUserId);
    wc_clear_notices();
    $ineligibleAddResult = vms_ticketing_v2_validate_add_to_cart(true, $productId, 1, 0, array(), array());
    $ineligibleNotices = $errorNotices();
    $assert($ineligibleAddResult === false, 'Non-qualified users should not be able to add verified tickets to cart.');
    $assert(!empty($ineligibleNotices), 'Non-qualified verified-ticket rejection should surface an actionable error.');

    wp_set_current_user($eligibleUserId);
    wc_clear_notices();
    $eligibleAddResult = vms_ticketing_v2_validate_add_to_cart(true, $productId, 1, 0, array(), array());
    $assert($eligibleAddResult === true, 'Eligible verified users should still be able to add verified tickets to cart.');

    if (function_exists('WC') && WC() && isset(WC()->cart) && WC()->cart) {
        WC()->cart->empty_cart();
        $cartAddKey = WC()->cart->add_to_cart($productId, 1);
        $assert($cartAddKey !== false, 'Eligible verified users should still be able to add verified tickets through Woo cart flow.');

        wp_set_current_user(0);
        wc_clear_notices();
        vms_ticketing_v2_enforce_ticket_visibility_rules();
        $staleGuestNotices = $errorNotices();
        $assert(!empty($staleGuestNotices), 'Guest checkout retries should be blocked when a verified ticket remains in cart.');

        wp_set_current_user($nonQualifiedUserId);
        wc_clear_notices();
        vms_ticketing_v2_enforce_ticket_visibility_rules();
        $staleMemberNotices = $errorNotices();
        $assert(!empty($staleMemberNotices), 'Non-qualified checkout retries should be blocked when a verified ticket remains in cart.');

        WC()->cart->empty_cart();
    }

    wp_set_current_user(1);

    update_post_meta($planId, $planStatusKey, 'ready');
    $readyContext = vms_ticketing_v2_validate_product_sale_context($productId, $planId, $eventId, 'ga_ticket');
    $assert(($readyContext['code'] ?? '') === 'event_plan_not_live', 'Ready plans should not allow ticket sales.');
    update_post_meta($planId, $planStatusKey, 'published');

    wp_update_post(array(
        'ID' => $eventId,
        'post_status' => 'draft',
    ));
    clean_post_cache($eventId);
    $draftEventContext = vms_ticketing_v2_validate_product_sale_context($productId, $planId, $eventId, 'ga_ticket');
    $assert(($draftEventContext['code'] ?? '') === 'event_unpublished', 'Draft TEC events should block ticket sales.');
    wp_update_post(array(
        'ID' => $eventId,
        'post_status' => 'publish',
    ));
    clean_post_cache($eventId);

    update_post_meta($planId, '_vms_event_date', wp_date('Y-m-d', $pastStartTs));
    update_post_meta($planId, '_vms_start_time', wp_date('H:i', $pastStartTs));
    update_post_meta($planId, '_vms_end_time', wp_date('H:i', $pastEndTs));
    update_post_meta($eventId, '_EventStartDate', wp_date('Y-m-d H:i:s', $pastStartTs));
    update_post_meta($eventId, '_EventEndDate', wp_date('Y-m-d H:i:s', $pastEndTs));
    $pastContext = vms_ticketing_v2_validate_product_sale_context($productId, $planId, $eventId, 'ga_ticket');
    $assert(($pastContext['code'] ?? '') === 'event_past', 'Past events should block ticket sales.');
    update_post_meta($planId, '_vms_event_date', wp_date('Y-m-d', $futureStartTs));
    update_post_meta($planId, '_vms_start_time', wp_date('H:i', $futureStartTs));
    update_post_meta($planId, '_vms_end_time', wp_date('H:i', $futureEndTs));
    update_post_meta($eventId, '_EventStartDate', wp_date('Y-m-d H:i:s', $futureStartTs));
    update_post_meta($eventId, '_EventEndDate', wp_date('Y-m-d H:i:s', $futureEndTs));

    update_post_meta($planId, '_vms_ticketing_enabled_override', 'off');
    $disabledContext = vms_ticketing_v2_validate_product_sale_context($productId, $planId, $eventId, 'ga_ticket');
    $assert(($disabledContext['code'] ?? '') === 'ticketing_disabled', 'Ticketing-disabled events should block ticket sales.');
    delete_post_meta($planId, '_vms_ticketing_enabled_override');

    delete_post_meta($productId, $planMetaKey);
    delete_post_meta($productId, '_tribe_wooticket_for_event');
    $detachedContext = vms_ticketing_v2_validate_product_sale_context($productId, $planId, $eventId, 'ga_ticket');
    $assert(($detachedContext['code'] ?? '') === 'product_detached', 'Detached products should be blocked by stored cart/event context.');

    $itemMeta = vms_ticketing_v2_add_event_meta_to_cart_item(array(), array(
        'product_id' => $productId,
        '_vms_ticketing_context' => $snapshot,
    ));
    $metaKeys = array_map(static function (array $row): string {
        return (string) ($row['key'] ?? '');
    }, $itemMeta);
    $assert(in_array('Event', $metaKeys, true), 'Cart item display should still show the event title from stored context.');
    $assert(in_array('When', $metaKeys, true), 'Cart item display should still show the event time from stored context.');

    fwrite(STDOUT, "ticket-checkout-safety-hardening: OK\n");
    $cleanup();
    exit(0);
} catch (Throwable $e) {
    $cleanup();
    fwrite(STDERR, "ticket-checkout-safety-hardening: FAIL\n" . $e->getMessage() . "\n");
    exit(1);
}
