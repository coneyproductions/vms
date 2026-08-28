<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap-wordpress.php';
vms_tests_require_wordpress(__DIR__);

$plugin_root_env = getenv('BVMGR_TEST_PLUGIN_ROOT');
$plugin_root = is_string($plugin_root_env) && $plugin_root_env !== '' ? realpath($plugin_root_env) : dirname(__DIR__);
if (!is_string($plugin_root) || !is_dir($plugin_root)) {
    throw new RuntimeException('BVMGR_TEST_PLUGIN_ROOT must identify the exact plugin package under test.');
}

if (!function_exists('bvmgr_ticketing_v2_get_config')) {
    require_once $plugin_root . '/vendor-management-system.php';
}
require_once $plugin_root . '/includes/core/event-reschedule.php';
require_once $plugin_root . '/includes/admin/event-reschedule.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion ' . $assertions . ' failed: ' . $message);
    }
};

$created_posts = array();
$created_orders = array();
$register_post = static function (int $post_id) use (&$created_posts): int {
    $created_posts[] = $post_id;
    return $post_id;
};
$register_order = static function ($order) use (&$created_orders) {
    if (is_object($order) && method_exists($order, 'get_id')) {
        $created_orders[] = (int) $order->get_id();
    }
    return $order;
};

$create_event = static function (string $title, string $date, string $start = '19:00', string $end = '21:00') use ($register_post): int {
    $event_id = wp_insert_post(array(
        'post_type' => 'tribe_events',
        'post_status' => 'publish',
        'post_title' => $title,
    ), true);
    if (is_wp_error($event_id) || (int) $event_id <= 0) {
        throw new RuntimeException('Could not create calendar-event fixture.');
    }
    $event_id = $register_post((int) $event_id);
    update_post_meta($event_id, '_EventStartDate', $date . ' ' . $start . ':00');
    update_post_meta($event_id, '_EventEndDate', $date . ' ' . $end . ':00');
    update_post_meta($event_id, '_EventStartDateUTC', get_gmt_from_date($date . ' ' . $start . ':00'));
    update_post_meta($event_id, '_EventEndDateUTC', get_gmt_from_date($date . ' ' . $end . ':00'));
    update_post_meta($event_id, '_EventTimezone', wp_timezone_string());
    return $event_id;
};

$create_plan = static function (string $title, int $event_id, string $date, string $workflow_status = 'published') use ($register_post): int {
    $plan_id = wp_insert_post(array(
        'post_type' => 'vms_event_plan',
        'post_status' => 'publish',
        'post_title' => $title,
    ), true);
    if (is_wp_error($plan_id) || (int) $plan_id <= 0) {
        throw new RuntimeException('Could not create Event Plan fixture.');
    }
    $plan_id = $register_post((int) $plan_id);
    update_post_meta($plan_id, '_vms_event_date', $date);
    update_post_meta($plan_id, '_vms_start_time', '19:00');
    update_post_meta($plan_id, '_vms_end_time', '21:00');
    update_post_meta($plan_id, '_vms_event_plan_start_datetime', $date . ' 19:00:00');
    update_post_meta($plan_id, '_vms_event_plan_end_datetime', $date . ' 21:00:00');
    update_post_meta($plan_id, '_vms_tec_event_id', $event_id);
    if ($workflow_status !== '') {
        update_post_meta($plan_id, '_vms_event_plan_status', $workflow_status);
    }
    return $plan_id;
};

$create_product = static function (string $name, int $plan_id, int $event_id, string $role, string $price, int $stock) use ($register_post): int {
    $product = new WC_Product_Simple();
    $product->set_name($name);
    $product->set_status('publish');
    $product->set_regular_price($price);
    $product->set_price($price);
    $product->set_virtual(true);
    $product->set_catalog_visibility('hidden');
    $product->set_manage_stock(true);
    $product->set_stock_quantity($stock);
    $product_id = $register_post((int) $product->save());
    update_post_meta($product_id, '_vms_event_plan_id', $plan_id);
    update_post_meta($product_id, '_vms_tec_event_id', $event_id);
    update_post_meta($product_id, '_vms_product_role', $role);
    return $product_id;
};

$add_order_item = static function ($order, int $product_id, int $quantity, string $old_date, string $old_when, array $assignments = array()): WC_Order_Item_Product {
    $product = wc_get_product($product_id);
    $item = new WC_Order_Item_Product();
    $item->set_product($product);
    $item->set_name((string) $product->get_name());
    $item->set_quantity($quantity);
    $line_total = (float) $product->get_price() * $quantity;
    $item->set_subtotal((string) $line_total);
    $item->set_total((string) $line_total);
    $item->add_meta_data('_vms_event_plan_id', (string) get_post_meta($product_id, '_vms_event_plan_id', true), true);
    $item->add_meta_data('_vms_tec_event_post_id', (string) get_post_meta($product_id, '_vms_tec_event_id', true), true);
    $item->add_meta_data('_vms_event_date_snapshot', $old_date, true);
    $item->add_meta_data('_vms_event_when_snapshot', $old_when, true);
    $item->add_meta_data('_vms_event_title_snapshot', 'Historical fixture event', true);
    $item->add_meta_data(__('When', 'backstage-venue-manager'), $old_when, true);
    if (!empty($assignments)) {
        $item->add_meta_data('_vms_claim_assignments', wp_json_encode($assignments), true);
    }
    $order->add_item($item);
    return $item;
};

$cleanup = static function () use (&$created_orders, &$created_posts): void {
    remove_all_actions('bvmgr_event_occurrence_before_verify');
    foreach (array_reverse($created_orders) as $order_id) {
        $order = function_exists('wc_get_order') ? wc_get_order((int) $order_id) : null;
        if ($order && method_exists($order, 'delete')) {
            $order->delete(true);
        } else {
            wp_delete_post((int) $order_id, true);
        }
    }
    foreach (array_reverse($created_posts) as $post_id) {
        wp_delete_post((int) $post_id, true);
    }
};

try {
    wp_set_current_user(1);
    $assert(class_exists('WC_Product_Simple') && function_exists('wc_create_order'), 'WooCommerce fixtures are unavailable.');

    // Published-date protection and lightweight no-sales correction.
    $draft_event_id = $create_event('Draft guard calendar fixture', '2026-10-02');
    $draft_plan_id = $create_plan('Draft guard Event Plan fixture', $draft_event_id, '2026-10-02', 'draft');
    $assert(update_post_meta($draft_plan_id, '_vms_event_date', '2026-10-03') !== false, 'Draft occurrence writes should remain allowed.');
    $assert((string) get_post_meta($draft_plan_id, '_vms_event_date', true) === '2026-10-03', 'Draft occurrence did not change normally.');

    update_post_meta($draft_plan_id, '_vms_event_plan_status', 'published');
    $blocked_update = update_post_meta($draft_plan_id, '_vms_event_date', '2026-10-04');
    $assert($blocked_update === false, 'Published occurrence direct update was not blocked.');
    $assert((string) get_post_meta($draft_plan_id, '_vms_event_date', true) === '2026-10-03', 'Blocked published update changed canonical data.');
    $assert(add_post_meta($draft_plan_id, '_vms_event_date', '2026-10-04') === false, 'Published occurrence duplicate metadata addition was not blocked.');
    $assert(count(get_post_meta($draft_plan_id, '_vms_event_date', false)) === 1, 'Blocked published metadata addition created a duplicate occurrence value.');
    $assert(delete_post_meta($draft_plan_id, '_vms_event_date') === false, 'Published occurrence deletion was not blocked.');
    global $wpdb;
    $event_date_mid = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT meta_id FROM %i WHERE post_id = %d AND meta_key = %s ORDER BY meta_id ASC LIMIT 1",
        $wpdb->postmeta,
        $draft_plan_id,
        '_vms_event_date'
    ));
    $assert($event_date_mid > 0 && update_metadata_by_mid('post', $event_date_mid, '2026-10-04') === false, 'Published occurrence metadata-by-ID update was not blocked.');
    $assert(delete_metadata_by_mid('post', $event_date_mid) === false, 'Published occurrence metadata-by-ID deletion was not blocked.');
    $assert(update_post_meta($draft_plan_id, '_vms_occurrence_unrelated_fixture', 'preserved') !== false, 'Published occurrence guard blocked an unrelated metadata write.');
    $locked_request = bvmgr_event_occurrence_lock_editor_request($draft_plan_id, array(
        'vms_event_date' => '2026-10-04',
        'vms_start_time' => '18:00',
        'vms_end_time' => '23:00',
    ));
    $assert(!empty($locked_request['blocked']), 'Crafted ordinary editor request was not identified.');
    $assert((string) $locked_request['request']['vms_event_date'] === '2026-10-03', 'Crafted request was not restored to stored date.');
    bvmgr_event_occurrence_authorized_write(static function () use ($draft_plan_id): void {
        update_post_meta($draft_plan_id, '_vms_event_date', '2026-10-02');
        update_post_meta($draft_plan_id, '_vms_event_plan_start_datetime', '2026-10-02 19:00:00');
        update_post_meta($draft_plan_id, '_vms_event_plan_end_datetime', '2026-10-02 21:00:00');
    });
    $assert((string) get_post_meta($draft_plan_id, '_vms_event_date', true) === '2026-10-02', 'Authorized occurrence write scope did not work.');

    $no_sales_preview = bvmgr_event_occurrence_preview($draft_plan_id, '2026-10-02 19:00', '2026-10-03 19:00', 'date_correction');
    $assert(!empty($no_sales_preview['allowed']) && $no_sales_preview['mode'] === 'forward', 'No-sales published correction preview should be allowed.');
    $assert((int) $no_sales_preview['counts']['admission_units'] === 0 && (int) $no_sales_preview['counts']['reservation_units'] === 0, 'No-sales preview found customer entitlements.');
    $unauthorized_apply = bvmgr_event_occurrence_apply($draft_plan_id, '2026-10-02 19:00', '2026-10-03 19:00', 'date_correction', 0);
    $assert(empty($unauthorized_apply['ok']) && (string) get_post_meta($draft_plan_id, '_vms_event_date', true) === '2026-10-02', 'Unauthorized service call changed a published occurrence.');

    $saved_post = $_POST;
    $_POST = array(
        'vms_event_plan_id' => (string) $draft_plan_id,
        'vms_old_start' => '2026-10-02T19:00',
        'vms_new_start' => '2026-10-03T19:00',
        'vms_occurrence_reason' => 'date_correction',
        'vms_occurrence_action' => 'preview',
    );
    $nonce_died = false;
    $wp_die_handler = static function (): callable {
        return static function (): void {
            throw new RuntimeException('Expected wp_die from missing occurrence nonce.');
        };
    };
    add_filter('wp_die_handler', $wp_die_handler);
    try {
        bvmgr_event_occurrence_admin_handle_change();
    } catch (RuntimeException $exception) {
        $nonce_died = true;
    } finally {
        remove_filter('wp_die_handler', $wp_die_handler);
        $_POST = $saved_post;
    }
    $assert($nonce_died && (string) get_post_meta($draft_plan_id, '_vms_event_date', true) === '2026-10-02', 'Missing admin nonce did not fail closed.');

    $no_sales_apply = bvmgr_event_occurrence_apply($draft_plan_id, '2026-10-02 19:00', '2026-10-03 19:00', 'date_correction', 1);
    $assert(!empty($no_sales_apply['ok']), 'No-sales controlled correction failed: ' . (string) ($no_sales_apply['message'] ?? ''));
    $assert((string) get_post_meta($draft_plan_id, '_vms_event_date', true) === '2026-10-03', 'No-sales correction did not update the plan.');
    $assert((string) get_post_meta($draft_event_id, '_EventStartDate', true) === '2026-10-03 19:00:00', 'No-sales correction did not update the calendar event.');
    $assert(count(bvmgr_event_occurrence_history($draft_plan_id)) === 1, 'No-sales correction did not append audit history.');
    $no_sales_repeat = bvmgr_event_occurrence_apply($draft_plan_id, '2026-10-02 19:00', '2026-10-03 19:00', 'date_correction', 1);
    $assert(!empty($no_sales_repeat['ok']) && !empty($no_sales_repeat['noop']), 'Repeated no-sales operation was not idempotent.');
    $assert(count(bvmgr_event_occurrence_history($draft_plan_id)) === 1, 'Idempotent replay duplicated audit history.');

    // Repair mode with paid/free-capable tickets, guest assignments, numbered reservations, and quantities.
    $old_date = '2026-09-19';
    $new_date = '2026-09-12';
    $event_id = $create_event('Reputation repair calendar fixture', $new_date);
    $plan_id = $create_plan('Reputation repair Event Plan fixture', $event_id, $new_date);
    $ticket_id = $create_product($old_date . ' 19:00 - Veteran / Youth Admission', $plan_id, $event_id, 'ga_ticket', '15', 80);
    $addon_id = $create_product($old_date . ' 19:00 - Fire Table #01', $plan_id, $event_id, 'entitlement', '25', 12);

    $order = $register_order(wc_create_order());
    $order->set_status('completed');
    $order->set_billing_first_name('Fixture');
    $order->set_billing_last_name('Purchaser');
    $order->set_billing_email('fixture@example.test');
    $assignments = array(
        array('seat' => 1, 'assignee_email' => 'guest-one@example.test'),
        array('seat' => 2, 'assignee_email' => 'guest-two@example.test'),
    );
    $ticket_item = $add_order_item($order, $ticket_id, 2, $old_date, 'Sat, Sep 19, 2026 7:00pm', $assignments);
    $addon_item = $add_order_item($order, $addon_id, 4, $old_date, 'Sat, Sep 19, 2026 7:00pm');
    $order->calculate_totals(false);
    $order->save();
    $ticket_item_id = (int) $ticket_item->get_id();
    $addon_item_id = (int) $addon_item->get_id();

    $attendee_ids = array();
    foreach (array('CHECKED-IN', '') as $index => $checked_in) {
        $attendee_id = wp_insert_post(array(
            'post_type' => 'tribe_wooticket',
            'post_status' => 'publish',
            'post_title' => 'Repair attendee ' . ($index + 1),
        ), true);
        if (is_wp_error($attendee_id) || (int) $attendee_id <= 0) {
            throw new RuntimeException('Could not create attendee fixture.');
        }
        $attendee_id = $register_post((int) $attendee_id);
        $attendee_ids[] = $attendee_id;
        update_post_meta($attendee_id, '_tribe_wooticket_event', $event_id);
        update_post_meta($attendee_id, '_tribe_wooticket_product', $ticket_id);
        update_post_meta($attendee_id, '_tribe_wooticket_order', (int) $order->get_id());
        update_post_meta($attendee_id, '_tribe_wooticket_order_item', $ticket_item_id);
        update_post_meta($attendee_id, '_tribe_wooticket_security_code', 'fixture-code-' . ($index + 1));
        if ($checked_in !== '') {
            update_post_meta($attendee_id, '_tribe_wooticket_checkedin', $checked_in);
        }
    }

    $financial_before = array(
        'order_id' => (int) $order->get_id(),
        'status' => (string) $order->get_status(),
        'total' => (string) $order->get_total(),
        'ticket_item_id' => $ticket_item_id,
        'ticket_qty' => (int) $ticket_item->get_quantity(),
        'addon_item_id' => $addon_item_id,
        'addon_qty' => (int) $addon_item->get_quantity(),
        'ticket_price' => (string) wc_get_product($ticket_id)->get_price(),
        'ticket_stock' => (int) wc_get_product($ticket_id)->get_stock_quantity(),
        'addon_price' => (string) wc_get_product($addon_id)->get_price(),
        'addon_stock' => (int) wc_get_product($addon_id)->get_stock_quantity(),
    );

    // A third, unrelated date must fail closed rather than being guessed into the operation.
    wc_update_order_item_meta($addon_item_id, '_vms_effective_event_start_local', '2026-09-26 19:00:00');
    $ambiguous_preview = bvmgr_event_occurrence_preview($plan_id, $old_date . ' 19:00', $new_date . ' 19:00', 'date_correction');
    $assert(empty($ambiguous_preview['allowed']) && !empty($ambiguous_preview['ambiguities']), 'Ambiguous linked occurrence did not fail closed: ' . wp_json_encode($ambiguous_preview));
    wc_delete_order_item_meta($addon_item_id, '_vms_effective_event_start_local');

    $preview = bvmgr_event_occurrence_preview($plan_id, $old_date . ' 19:00', $new_date . ' 19:00', 'date_correction');
    $assert(!empty($preview['allowed']) && $preview['mode'] === 'repair', 'Partially migrated event did not enter repair mode: ' . wp_json_encode($preview['ambiguities'] ?? array()));
    $notification = (array) ($preview['notification_rows'][0] ?? array());
    $assert(
        (int) $preview['counts']['orders'] === 1
        && (int) ($notification['order_id'] ?? 0) === (int) $order->get_id()
        && (string) ($notification['customer_name'] ?? '') === 'Fixture Purchaser'
        && (string) ($notification['customer_email'] ?? '') === 'fixture@example.test'
        && count((array) ($notification['entitlements'] ?? array())) === 2,
        'Repair preview order/notification detail changed.'
    );
    $assert((int) $preview['counts']['admission_units'] === 2, 'Repair preview admission count changed.');
    $assert((int) $preview['counts']['reservation_units'] === 4, 'Repair preview reservation count changed.');
    $assert((int) $preview['counts']['registered_assignments'] === 2, 'Registered guest assignment count changed.');
    $assert((int) $preview['counts']['numbered_reservation_units'] === 4, 'Numbered reservation count changed.');
    $assert((int) $preview['counts']['multi_quantity_lines'] === 2, 'Multi-quantity line impact changed.');
    $integrity_before = bvmgr_event_occurrence_integrity($plan_id);
    $assert((int) $integrity_before['mismatch_admission_units'] === 2 && (int) $integrity_before['mismatch_reservation_units'] === 4, 'Integrity checker did not report stale units by type.');

    $approved_fingerprint = bvmgr_event_occurrence_preview_fingerprint($preview);
    wp_update_post(array('ID' => $addon_id, 'post_title' => $old_date . ' 19:00 - Fire Table #01 changed after preview'));
    $stale_apply = bvmgr_event_occurrence_apply($plan_id, $old_date . ' 19:00', $new_date . ' 19:00', 'date_correction', 1, $approved_fingerprint);
    $assert(empty($stale_apply['ok']) && strpos((string) ($stale_apply['message'] ?? ''), 'stale') !== false, 'Apply accepted an approved preview after relevant product state changed.');
    $assert((string) wc_get_order_item_meta($ticket_item_id, '_vms_effective_event_start_local', true) === '' && bvmgr_event_occurrence_history($plan_id) === array(), 'Stale-preview refusal changed effective occurrence state or audit history.');
    wp_update_post(array('ID' => $addon_id, 'post_title' => $old_date . ' 19:00 - Fire Table #01'));

    // Inject a failure at the last possible pre-commit point to prove rollback of all writes.
    $throw_for_rollback = static function (): void {
        throw new RuntimeException('Fixture rollback injection.');
    };
    add_action('bvmgr_event_occurrence_before_verify', $throw_for_rollback, 10, 0);
    $rolled_back = bvmgr_event_occurrence_apply($plan_id, $old_date . ' 19:00', $new_date . ' 19:00', 'date_correction', 1);
    remove_action('bvmgr_event_occurrence_before_verify', $throw_for_rollback, 10);
    $assert(empty($rolled_back['ok']) && !empty($rolled_back['rolled_back']), 'Injected failure did not report transaction rollback.');
    $assert((string) wc_get_order_item_meta($ticket_item_id, '_vms_effective_event_start_local', true) === '', 'Rollback left effective order-item occurrence metadata behind.');
    $assert(strpos((string) get_the_title($ticket_id), $old_date) === 0, 'Rollback left the product title partially migrated.');
    $assert(bvmgr_event_occurrence_history($plan_id) === array(), 'Rollback left an audit entry behind.');

    $applied = bvmgr_event_occurrence_apply($plan_id, $old_date . ' 19:00', $new_date . ' 19:00', 'date_correction', 1);
    $assert(!empty($applied['ok']), 'Repair apply failed: ' . (string) ($applied['message'] ?? ''));
    $assert((string) get_post_meta($plan_id, '_vms_event_date', true) === $new_date, 'Repair mode incorrectly moved the canonical Event Plan away from the new date.');
    $assert((string) wc_get_order_item_meta($ticket_item_id, '_vms_event_date_snapshot', true) === $old_date, 'Historical purchase snapshot was overwritten.');
    $assert((string) wc_get_order_item_meta($ticket_item_id, '_vms_event_when_snapshot', true) === 'Sat, Sep 19, 2026 7:00pm', 'Historical When snapshot was overwritten.');
    $assert((string) wc_get_order_item_meta($ticket_item_id, '_vms_event_title_snapshot', true) === 'Historical fixture event', 'Historical event-title snapshot was overwritten.');
    $assert((string) wc_get_order_item_meta($ticket_item_id, '_vms_effective_event_start_local', true) === $new_date . ' 19:00:00', 'Ticket effective occurrence did not migrate.');
    $assert((string) wc_get_order_item_meta($addon_item_id, '_vms_effective_event_start_local', true) === $new_date . ' 19:00:00', 'Reservation effective occurrence did not migrate.');
    $ticket_title_after = (string) get_the_title($ticket_id);
    $addon_title_after = (string) get_the_title($addon_id);
    $assert(strpos($ticket_title_after, $old_date) === false && strpos($ticket_title_after, 'Veteran / Youth Admission') !== false, 'Ticket product title still exposes the old occurrence: ' . $ticket_title_after);
    $assert(strpos($addon_title_after, $old_date) === false && strpos($addon_title_after, 'Fire Table #01') !== false, 'Numbered reservation identity was not preserved in the updated title: ' . $addon_title_after);
    $assert(strpos((string) wc_get_order_item_meta($ticket_item_id, __('When', 'backstage-venue-manager'), true), 'Sep 12, 2026') !== false, 'Current order-item When display did not move to the new occurrence.');

    $order_after = wc_get_order((int) $order->get_id());
    $ticket_after = $order_after->get_item($ticket_item_id);
    $addon_after = $order_after->get_item($addon_item_id);
    $financial_after = array(
        'order_id' => (int) $order_after->get_id(),
        'status' => (string) $order_after->get_status(),
        'total' => (string) $order_after->get_total(),
        'ticket_item_id' => (int) $ticket_after->get_id(),
        'ticket_qty' => (int) $ticket_after->get_quantity(),
        'addon_item_id' => (int) $addon_after->get_id(),
        'addon_qty' => (int) $addon_after->get_quantity(),
        'ticket_price' => (string) wc_get_product($ticket_id)->get_price(),
        'ticket_stock' => (int) wc_get_product($ticket_id)->get_stock_quantity(),
        'addon_price' => (string) wc_get_product($addon_id)->get_price(),
        'addon_stock' => (int) wc_get_product($addon_id)->get_stock_quantity(),
    );
    $assert($financial_after === $financial_before, 'Order/product IDs, status, totals, quantities, prices, or stock changed.');
    $assert((string) $ticket_after->get_meta('_vms_claim_assignments', true) === wp_json_encode($assignments), 'Registered guest assignments changed.');
    $assert((string) get_post_meta($attendee_ids[0], '_tribe_wooticket_checkedin', true) === 'CHECKED-IN', 'Attendee check-in state changed.');
    $assert((string) get_post_meta($attendee_ids[0], '_tribe_wooticket_security_code', true) === 'fixture-code-1', 'Attendee verification identity changed.');
    $assert(absint(get_post_meta($attendee_ids[0], '_tribe_wooticket_event', true)) === $event_id, 'Attendee calendar linkage changed.');
    $assert(count(bvmgr_event_occurrence_history($plan_id)) === 1, 'Repair did not append exactly one audit entry.');
    $integrity_after = bvmgr_event_occurrence_integrity($plan_id);
    $assert(!empty($integrity_after['ok']) && (int) $integrity_after['mismatch_units'] === 0, 'Post-repair integrity did not pass.');
    $post_preview = bvmgr_event_occurrence_preview($plan_id, $old_date . ' 19:00', $new_date . ' 19:00', 'date_correction');
    $assert(!empty($post_preview['allowed']) && (int) $post_preview['counts']['line_items'] === 0, 'Post-repair dry run still targets current entitlements.');
    $repeat = bvmgr_event_occurrence_apply($plan_id, $old_date . ' 19:00', $new_date . ' 19:00', 'date_correction', 1);
    $assert(!empty($repeat['ok']) && !empty($repeat['noop']) && count(bvmgr_event_occurrence_history($plan_id)) === 1, 'Repair replay was not idempotent.');

    $service_source = (string) file_get_contents($plugin_root . '/includes/core/event-reschedule.php');
    $admin_source = (string) file_get_contents($plugin_root . '/includes/admin/event-reschedule.php');
    $editor_source = (string) file_get_contents($plugin_root . '/includes/cpt/event-plans.php');
    $report_source = (string) file_get_contents($plugin_root . '/includes/admin/event-day-report.php');
    $report_script_source = (string) file_get_contents($plugin_root . '/assets/js/vms-event-day-report.js');
    $report_style_source = (string) file_get_contents($plugin_root . '/assets/css/vms-event-day-report.css');
    $cli_source = (string) file_get_contents($plugin_root . '/includes/core/cli/event-reschedule.php');
    $assert(strpos($service_source, 'START TRANSACTION') !== false && strpos($service_source, 'ROLLBACK') !== false, 'Canonical service lacks explicit transaction/rollback semantics.');
    $assert(strpos($admin_source, 'check_admin_referer') !== false && strpos($admin_source, 'vms_occurrence_confirm') !== false, 'Admin workflow lacks nonce/confirmation enforcement.');
    $assert(strpos($editor_source, 'bvmgr_event_occurrence_lock_editor_request') !== false, 'Event Plan ordinary save is not wired to the server lock.');
    $assert(strpos($report_source, 'event_occurrence_date_mismatch') !== false, 'Event-Day report lacks occurrence-integrity warning integration.');
    $assert(strpos($report_source, 'wp_enqueue_style') !== false && strpos($report_source, 'wp_enqueue_script') !== false && strpos($report_source, 'BVMGR_PLUGIN_URL') !== false && !preg_match('/<(?:style|script)\b|\bonclick=/i', $report_source), 'Event-Day report assets are not externalized safely.');
    $assert(strpos($report_script_source, 'data-vms-edr-tab') !== false && strpos($report_style_source, '.vms-edr-issues li.is-error') !== false, 'Event-Day report CSS/JS assets are incomplete.');
    $assert(strpos($cli_source, "WP_CLI::add_command('bvmgr event reschedule'") !== false && strpos($cli_source, "WP_CLI::add_command('vms event reschedule'") !== false && strpos($cli_source, '--confirm=RESCHEDULE') !== false, 'CLI dry-run/apply interface is incomplete.');

    fwrite(STDOUT, 'PASS: ' . $assertions . " published-date lock/reschedule assertions.\n");
} finally {
    $cleanup();
}
