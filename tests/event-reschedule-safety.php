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
require_once $plugin_root . '/includes/admin/event-day-report.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion ' . $assertions . ' failed: ' . $message);
    }
};

$event_day_assertions = 0;
$event_day_assert = static function (bool $condition, string $message) use (&$event_day_assertions): void {
    $event_day_assertions++;
    if (!$condition) {
        throw new RuntimeException('Event-Day assertion ' . $event_day_assertions . ' failed: ' . $message);
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

$add_legacy_order_item = static function ($order, int $product_id, int $quantity, string $line_name, string $snapshot_date): WC_Order_Item_Product {
    $product = wc_get_product($product_id);
    $item = new WC_Order_Item_Product();
    $item->set_product($product);
    $item->set_name($line_name);
    $item->set_quantity($quantity);
    $line_total = (float) $product->get_price() * $quantity;
    $item->set_subtotal((string) $line_total);
    $item->set_total((string) $line_total);
    $item->add_meta_data('_vms_event_plan_id', (string) get_post_meta($product_id, '_vms_event_plan_id', true), true);
    $item->add_meta_data('_vms_tec_event_post_id', (string) get_post_meta($product_id, '_vms_tec_event_id', true), true);
    $item->add_meta_data('_vms_event_date_snapshot', $snapshot_date, true);
    $item->add_meta_data('_vms_event_title_snapshot', 'Synthetic legacy fixture event', true);
    $order->add_item($item);
    return $item;
};

$cleanup = static function () use (&$created_orders, &$created_posts): void {
    remove_all_actions('bvmgr_event_occurrence_before_verify');
    remove_all_actions('bvmgr_event_occurrence_name_reconciliation_before_verify');
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

    // Legacy date-only snapshots are interpreted in place, per item, without order-note inference.
    $legacy_old_date = '2026-09-19';
    $legacy_current_date = '2026-09-12';
    $legacy_event_id = $create_event('Legacy snapshot calendar fixture', $legacy_current_date);
    $legacy_plan_id = $create_plan('Legacy snapshot Event Plan fixture', $legacy_event_id, $legacy_current_date);
    $legacy_ticket_id = $create_product($legacy_current_date . ' 19:00 - General Admission', $legacy_plan_id, $legacy_event_id, 'ga_ticket', '20', 100);
    $legacy_addon_id = $create_product($legacy_current_date . ' 19:00 - Kiddie Pool', $legacy_plan_id, $legacy_event_id, 'entitlement', '10', 20);

    $legacy_order = $register_order(wc_create_order());
    $legacy_order->set_status('completed');
    $legacy_order->set_billing_first_name('Mixed');
    $legacy_order->set_billing_last_name('Legacy Fixture');
    $legacy_order->set_billing_email('mixed-legacy@example.test');
    $legacy_current_item = $add_legacy_order_item(
        $legacy_order,
        $legacy_ticket_id,
        10,
        $legacy_current_date . ' 19:00 - General Admission',
        'Sep 12, 2026'
    );
    $legacy_stale_item = $add_legacy_order_item(
        $legacy_order,
        $legacy_addon_id,
        4,
        $legacy_old_date . ' 19:00 - Kiddie Pool',
        'Sep 19, 2026'
    );
    $legacy_order->calculate_totals(false);
    $legacy_order->save();
    $legacy_order->add_order_note('Synthetic history mentions Sep 12, 2026 admissions.');
    $legacy_order->add_order_note('Synthetic history also mentions Sep 19, 2026 reservations.');
    $legacy_current_item_id = (int) $legacy_current_item->get_id();
    $legacy_stale_item_id = (int) $legacy_stale_item->get_id();

    $legacy_item_state = static function (int $item_id): array {
        $item = new WC_Order_Item_Product($item_id);
        return array(
            'name' => (string) $item->get_name(),
            'snapshot' => (string) wc_get_order_item_meta($item_id, '_vms_event_date_snapshot', true),
            'when_snapshot' => (string) wc_get_order_item_meta($item_id, '_vms_event_when_snapshot', true),
            'when' => (string) wc_get_order_item_meta($item_id, __('When', 'backstage-venue-manager'), true),
            'effective_start' => (string) wc_get_order_item_meta($item_id, '_vms_effective_event_start_local', true),
            'effective_end' => (string) wc_get_order_item_meta($item_id, '_vms_effective_event_end_local', true),
            'effective_timezone' => (string) wc_get_order_item_meta($item_id, '_vms_effective_event_timezone', true),
            'original_name' => (string) wc_get_order_item_meta($item_id, '_vms_original_order_item_name_snapshot', true),
            'operation_id' => (string) wc_get_order_item_meta($item_id, '_vms_occurrence_operation_id', true),
        );
    };
    $legacy_current_state_before = $legacy_item_state($legacy_current_item_id);
    $legacy_stale_state_before = $legacy_item_state($legacy_stale_item_id);
    $assert(
        $legacy_current_state_before['when_snapshot'] === ''
        && $legacy_current_state_before['when'] === ''
        && $legacy_current_state_before['effective_start'] === ''
        && $legacy_current_state_before['original_name'] === ''
        && $legacy_current_state_before['operation_id'] === '',
        'Synthetic current item does not reproduce the legacy production metadata shape.'
    );

    $legacy_notes = wc_get_order_notes(array('order_id' => (int) $legacy_order->get_id()));
    $legacy_note_text = implode(' ', array_map(
        static fn($note): string => is_object($note) ? (string) ($note->content ?? '') : '',
        (array) $legacy_notes
    ));
    $assert(
        strpos($legacy_note_text, 'Sep 12, 2026') !== false && strpos($legacy_note_text, 'Sep 19, 2026') !== false,
        'Mixed-order fixture does not contain both order-level historical dates.'
    );

    $legacy_sales = BVMGR_Ticket_Revenue_Service::get_sales_result(array(
        'event_plan_ids' => array($legacy_plan_id),
        'order_statuses' => array('completed'),
        'include_unresolved' => true,
        'include_refunded_lines' => true,
    ));
    $legacy_rows_by_item = array();
    foreach ((array) ($legacy_sales['rows'] ?? array()) as $legacy_row) {
        $legacy_rows_by_item[(int) ($legacy_row['order_item_id'] ?? 0)] = $legacy_row;
    }
    $legacy_current_resolver_snapshot = (string) ($legacy_rows_by_item[$legacy_current_item_id]['raw_linkage_snapshot']['event_date_snapshot'] ?? '');
    $assert(
        $legacy_current_resolver_snapshot === $legacy_current_date
        && $legacy_current_resolver_snapshot !== '2026-09-11',
        'Resolver diagnostic timezone-shifted the legacy September 12 snapshot: ' . $legacy_current_resolver_snapshot
    );
    $assert(
        (string) ($legacy_rows_by_item[$legacy_current_item_id]['line_kind'] ?? '') === 'ticket'
        && (string) ($legacy_rows_by_item[$legacy_stale_item_id]['line_kind'] ?? '') === 'addon',
        'Legacy mixed-order items lost their admission/reservation resolver classifications.'
    );

    $legacy_integrity = bvmgr_event_occurrence_integrity($legacy_plan_id);
    $assert(
        (int) $legacy_integrity['mismatch_units'] === 4
        && (int) $legacy_integrity['mismatch_admission_units'] === 0
        && (int) $legacy_integrity['mismatch_reservation_units'] === 4,
        'Integrity did not classify the legacy current admissions independently from the stale reservation: ' . wp_json_encode($legacy_integrity)
    );

    $legacy_preview = bvmgr_event_occurrence_preview(
        $legacy_plan_id,
        $legacy_old_date . ' 19:00',
        $legacy_current_date . ' 19:00',
        'date_correction'
    );
    $legacy_target_ids = array_values(array_map(
        static fn(array $row): int => (int) ($row['order_item_id'] ?? 0),
        (array) ($legacy_preview['rows'] ?? array())
    ));
    $assert(
        !empty($legacy_preview['allowed'])
        && empty($legacy_preview['ambiguities'])
        && (int) $legacy_preview['counts']['admission_units'] === 0
        && (int) $legacy_preview['counts']['reservation_units'] === 4
        && !in_array($legacy_current_item_id, $legacy_target_ids, true)
        && in_array($legacy_stale_item_id, $legacy_target_ids, true),
        'Backward preview did not leave the legacy-current admission untouched while targeting the stale reservation: ' . wp_json_encode($legacy_preview)
    );
    $assert(
        $legacy_item_state($legacy_current_item_id) === $legacy_current_state_before
        && $legacy_item_state($legacy_stale_item_id) === $legacy_stale_state_before,
        'Integrity or dry-run inspection rewrote legacy order-item metadata.'
    );

    $legacy_event_day_model = bvmgr_event_day_report_build_model($legacy_plan_id);
    $assert(
        (int) ($legacy_event_day_model['occurrence_integrity']['mismatch_admission_units'] ?? -1) === 0
        && (int) ($legacy_event_day_model['occurrence_integrity']['mismatch_reservation_units'] ?? -1) === 4,
        'Event-Day integrity did not preserve mixed-order item-level classification.'
    );

    wc_update_order_item_meta($legacy_current_item_id, '_vms_event_date_snapshot', 'Sep 19, 2026');
    $legacy_current_as_old = new WC_Order_Item_Product($legacy_current_item_id);
    $legacy_current_as_old->set_name($legacy_old_date . ' 19:00 - General Admission');
    $legacy_current_as_old->save();
    $legacy_old_admission_preview = bvmgr_event_occurrence_preview(
        $legacy_plan_id,
        $legacy_old_date . ' 19:00',
        $legacy_current_date . ' 19:00',
        'date_correction'
    );
    $assert(
        !empty($legacy_old_admission_preview['allowed'])
        && (int) $legacy_old_admission_preview['counts']['admission_units'] === 10
        && (int) $legacy_old_admission_preview['counts']['reservation_units'] === 4,
        'Supported legacy September 19 admission snapshot was not eligible under existing repair rules.'
    );

    wc_update_order_item_meta($legacy_current_item_id, '_vms_event_date_snapshot', 'Sep 12, 2026');
    $legacy_conflicting_name = new WC_Order_Item_Product($legacy_current_item_id);
    $legacy_conflicting_name->set_name($legacy_old_date . ' 19:00 - General Admission');
    $legacy_conflicting_name->save();
    $legacy_conflict_integrity = bvmgr_event_occurrence_integrity($legacy_plan_id);
    $assert(
        (int) $legacy_conflict_integrity['mismatch_admission_units'] === 10,
        'A conflicting retained line-name date did not fail safely through integrity.'
    );

    $legacy_restored_current = new WC_Order_Item_Product($legacy_current_item_id);
    $legacy_restored_current->set_name($legacy_current_date . ' 19:00 - General Admission');
    $legacy_restored_current->save();
    wc_update_order_item_meta($legacy_stale_item_id, '_vms_event_date_snapshot', '');
    $legacy_unknown_item = new WC_Order_Item_Product($legacy_stale_item_id);
    $legacy_unknown_item->set_name('Kiddie Pool');
    $legacy_unknown_item->save();
    $legacy_unknown_preview = bvmgr_event_occurrence_preview(
        $legacy_plan_id,
        $legacy_old_date . ' 19:00',
        $legacy_current_date . ' 19:00',
        'date_correction'
    );
    $assert(
        empty($legacy_unknown_preview['allowed']) && !empty($legacy_unknown_preview['ambiguities']),
        'A truly unknown item occurrence was inferred from mixed order-level notes instead of failing closed.'
    );

    wc_update_order_item_meta($legacy_stale_item_id, '_vms_event_date_snapshot', 'Sep 19, 2026');
    $legacy_restored_stale = new WC_Order_Item_Product($legacy_stale_item_id);
    $legacy_restored_stale->set_name($legacy_old_date . ' 19:00 - Kiddie Pool');
    $legacy_restored_stale->save();
    $assert(
        $legacy_item_state($legacy_current_item_id) === $legacy_current_state_before
        && $legacy_item_state($legacy_stale_item_id) === $legacy_stale_state_before,
        'Legacy mixed-order fixtures were not restored after conflict/unknown safety checks.'
    );

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

    $event_day_assert(
        function_exists('bvmgr_ticket_sales_resolver_active_attendee_post_statuses')
        && function_exists('bvmgr_event_day_report_build_model')
        && function_exists('bvmgr_event_day_report_render_document'),
        'Canonical resolver and Event-Day report runtime did not load.'
    );
    $active_attendee_statuses = bvmgr_ticket_sales_resolver_active_attendee_post_statuses();
    $event_day_assert(
        in_array('publish', $active_attendee_statuses, true)
        && in_array('private', $active_attendee_statuses, true)
        && !in_array('trash', $active_attendee_statuses, true),
        'Canonical active-attendee resolver changed the supported status boundary.'
    );
    $event_day_woo_before = bvmgr_event_day_report_collect_woo_sources($plan_id, array('ids' => array(), 'references' => array()));
    $event_day_source_model_before = bvmgr_event_day_report_build_model_from_sources(array(
        'event_plan_id' => $plan_id,
        'title' => 'Reputation repair Event Plan fixture',
        'event_date' => $new_date,
        'schedule_label' => $new_date . ' · 19:00–21:00',
    ), array('woo_result' => $event_day_woo_before));
    $event_day_assert(
        (int) ($event_day_source_model_before['totals']['expected'] ?? 0) === 2
        && (int) ($event_day_source_model_before['totals']['reservation_units'] ?? 0) === 4,
        'Event-Day guest or reservation calculations changed before repair.'
    );
    $event_day_model_before = bvmgr_event_day_report_build_model($plan_id);
    $event_day_issue_codes_before = array_values(array_filter(array_map(
        static fn($issue): string => is_array($issue) ? (string) ($issue['code'] ?? '') : '',
        (array) ($event_day_model_before['issues'] ?? array())
    )));
    $event_day_assert(
        in_array('event_occurrence_date_mismatch', $event_day_issue_codes_before, true)
        && (int) ($event_day_model_before['occurrence_integrity']['mismatch_admission_units'] ?? 0) === 2
        && (int) ($event_day_model_before['occurrence_integrity']['mismatch_reservation_units'] ?? 0) === 4,
        'Event-Day report lost the reschedule-integrity mismatch warning.'
    );
    ob_start();
    bvmgr_event_day_report_render_document($event_day_model_before, 'full', false);
    $event_day_markup_before = (string) ob_get_clean();
    $event_day_assert(
        strpos($event_day_markup_before, 'Fixture Purchaser') !== false
        && strpos($event_day_markup_before, 'Fire Table #01') !== false,
        'Event-Day render lost guest or reservation rows.'
    );
    $event_day_assert(
        strpos($event_day_markup_before, 'Date mismatch detected: 2 admissions and 4 reservations') !== false,
        'Event-Day render did not expose the occurrence mismatch warning.'
    );

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
    $assert(function_exists('bvmgr_event_communication_get_ledger') && bvmgr_event_communication_get_ledger($plan_id, (string) ($rolled_back['operation_id'] ?? '')) === array(), 'Rollback left a communication ledger behind.');

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
    $assert((string) (new WC_Order_Item_Product($ticket_item_id))->get_name() === $new_date . ' 19:00 - Veteran / Youth Admission', 'Future reschedule APPLY did not set the canonical current ticket line name.');
    $assert((string) (new WC_Order_Item_Product($addon_item_id))->get_name() === $new_date . ' 19:00 - Fire Table #01', 'Future reschedule APPLY did not set the canonical numbered reservation line name.');
    $assert((string) wc_get_order_item_meta($ticket_item_id, '_vms_original_order_item_name_snapshot', true) === $old_date . ' 19:00 - Veteran / Youth Admission', 'Future reschedule APPLY did not preserve the original ticket line name snapshot.');
    $assert((string) wc_get_order_item_meta($addon_item_id, '_vms_original_order_item_name_snapshot', true) === $old_date . ' 19:00 - Fire Table #01', 'Future reschedule APPLY did not preserve the original reservation line name snapshot.');
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
    $event_day_model_after = bvmgr_event_day_report_build_model($plan_id);
    $event_day_issue_codes_after = array_values(array_filter(array_map(
        static fn($issue): string => is_array($issue) ? (string) ($issue['code'] ?? '') : '',
        (array) ($event_day_model_after['issues'] ?? array())
    )));
    $event_day_assert(
        !in_array('event_occurrence_date_mismatch', $event_day_issue_codes_after, true)
        && !empty($event_day_model_after['occurrence_integrity']['ok']),
        'Event-Day mismatch warning did not clear after the controlled repair.'
    );
    $event_day_woo_after = bvmgr_event_day_report_collect_woo_sources($plan_id, array('ids' => array(), 'references' => array()));
    $event_day_source_model_after = bvmgr_event_day_report_build_model_from_sources(array(
        'event_plan_id' => $plan_id,
        'title' => 'Reputation repair Event Plan fixture',
        'event_date' => $new_date,
        'schedule_label' => $new_date . ' · 19:00–21:00',
    ), array('woo_result' => $event_day_woo_after));
    $event_day_assert(
        ($event_day_source_model_after['totals'] ?? array()) === ($event_day_source_model_before['totals'] ?? array())
        && count((array) ($event_day_source_model_after['parties'] ?? array())) === count((array) ($event_day_source_model_before['parties'] ?? array()))
        && count((array) ($event_day_source_model_after['reservations'] ?? array())) === count((array) ($event_day_source_model_before['reservations'] ?? array())),
        'Event-Day guest or reservation calculations changed across repair.'
    );

    // Reproduce already-repaired items whose effective occurrence and immutable
    // snapshots are correct but whose current Woo display name lost its date prefix.
    $operation_id = (string) ($applied['operation_id'] ?? '');
    $new_payload = (array) ($applied['preview']['new'] ?? array());
    $reconcile_types = array(
        'ga' => array('label' => 'General Admission', 'role' => 'ga_ticket', 'price' => '20', 'stock' => 100),
        'youth' => array('label' => 'Youth Ticket', 'role' => 'ga_ticket', 'price' => '12', 'stock' => 80),
        'child' => array('label' => "Children's Admission", 'role' => 'ga_ticket', 'price' => '8', 'stock' => 60),
        'veteran' => array('label' => 'Veteran Admission', 'role' => 'ga_ticket', 'price' => '15', 'stock' => 70),
        'kiddie_pool' => array('label' => 'Kiddie Pool', 'role' => 'entitlement', 'price' => '10', 'stock' => 20),
        'fire_table' => array('label' => 'Fire Table #02', 'role' => 'entitlement', 'price' => '25', 'stock' => 12),
    );
    $reconcile_order = $register_order(wc_create_order());
    $reconcile_order->set_status('completed');
    $reconcile_order->set_billing_first_name('Name');
    $reconcile_order->set_billing_last_name('Reconciliation Fixture');
    $reconcile_order->set_billing_email('name-reconciliation@example.test');
    $reconcile_items = array();
    foreach ($reconcile_types as $key => $fixture) {
        $product_id = $create_product(
            $new_date . ' 19:00 - ' . (string) $fixture['label'],
            $plan_id,
            $event_id,
            (string) $fixture['role'],
            (string) $fixture['price'],
            (int) $fixture['stock']
        );
        $item = $add_order_item(
            $reconcile_order,
            $product_id,
            1,
            $old_date,
            'Sat, Sep 19, 2026 7:00pm'
        );
        $reconcile_items[$key] = array(
            'item' => $item,
            'product_id' => $product_id,
            'label' => (string) $fixture['label'],
        );
    }
    $reconcile_order->calculate_totals(false);
    $reconcile_order->save();
    $reconcile_effective_start = bvmgr_event_occurrence_parse_local($new_date . ' 19:00');
    if (!($reconcile_effective_start instanceof DateTimeImmutable)) {
        throw new RuntimeException('Could not build the reconciliation effective-occurrence fixture.');
    }
    foreach ($reconcile_items as $key => &$fixture) {
        $fixture['item_id'] = (int) $fixture['item']->get_id();
        $fixture['expected_name'] = $new_date . ' 19:00 - ' . $fixture['label'];
        $fixture['original_name'] = $old_date . ' 19:00 - ' . $fixture['label'];
        wc_update_order_item_meta($fixture['item_id'], '_vms_original_order_item_name_snapshot', $fixture['original_name']);
        wc_update_order_item_meta($fixture['item_id'], '_vms_effective_event_date', (string) ($new_payload['date'] ?? ''));
        wc_update_order_item_meta($fixture['item_id'], '_vms_effective_event_when', bvmgr_event_occurrence_effective_when_label($reconcile_effective_start));
        wc_update_order_item_meta($fixture['item_id'], '_vms_effective_event_start_local', (string) ($new_payload['start_local'] ?? ''));
        wc_update_order_item_meta($fixture['item_id'], '_vms_effective_event_end_local', (string) ($new_payload['end_local'] ?? ''));
        wc_update_order_item_meta($fixture['item_id'], '_vms_effective_event_start_utc', (string) ($new_payload['start_utc'] ?? ''));
        wc_update_order_item_meta($fixture['item_id'], '_vms_effective_event_end_utc', (string) ($new_payload['end_utc'] ?? ''));
        wc_update_order_item_meta($fixture['item_id'], '_vms_occurrence_operation_id', $operation_id);
        wc_update_order_item_meta($fixture['item_id'], __('When', 'backstage-venue-manager'), bvmgr_event_occurrence_effective_when_label($reconcile_effective_start));
        $incorrect_item = new WC_Order_Item_Product($fixture['item_id']);
        $incorrect_item->set_name($fixture['label']);
        $incorrect_item->save();
    }
    unset($fixture);

    $ordinary_order = $register_order(wc_create_order());
    $ordinary_order->set_status('completed');
    $ordinary_ga = $add_order_item(
        $ordinary_order,
        (int) $reconcile_items['ga']['product_id'],
        1,
        $new_date,
        'Sat, Sep 12, 2026 7:00pm'
    );
    $ordinary_fire = $add_order_item(
        $ordinary_order,
        (int) $reconcile_items['fire_table']['product_id'],
        1,
        $new_date,
        'Sat, Sep 12, 2026 7:00pm'
    );
    $ordinary_order->calculate_totals(false);
    $ordinary_order->save();
    $ordinary_ga_id = (int) $ordinary_ga->get_id();
    $ordinary_fire_id = (int) $ordinary_fire->get_id();
    $ordinary_state_before = array(
        $ordinary_ga_id => (new WC_Order_Item_Product($ordinary_ga_id))->get_name(),
        $ordinary_fire_id => (new WC_Order_Item_Product($ordinary_fire_id))->get_name(),
    );

    $wrong_operation_preview = bvmgr_event_occurrence_name_reconciliation_preview($plan_id, '11111111-1111-4111-8111-111111111111');
    $assert(empty($wrong_operation_preview['allowed']) && !empty($wrong_operation_preview['ambiguities']), 'Name reconciliation accepted an operation ID not recorded for the Event Plan.');

    $fire_item_id = (int) $reconcile_items['fire_table']['item_id'];
    $number_conflict_item = new WC_Order_Item_Product($fire_item_id);
    $number_conflict_item->set_name('Fire Table #03');
    $number_conflict_item->save();
    $number_conflict_preview = bvmgr_event_occurrence_name_reconciliation_preview($plan_id, $operation_id);
    $assert(empty($number_conflict_preview['allowed']) && (int) $number_conflict_preview['counts']['unsafe_rows'] >= 1, 'Numbered reservation identity conflict did not fail closed.');
    $number_conflict_item = new WC_Order_Item_Product($fire_item_id);
    $number_conflict_item->set_name((string) $reconcile_items['fire_table']['label']);
    $number_conflict_item->save();

    $ga_item_id = (int) $reconcile_items['ga']['item_id'];
    wc_update_order_item_meta($ga_item_id, '_vms_original_order_item_name_snapshot', $old_date . ' 19:00 - Youth Ticket');
    $historical_conflict_preview = bvmgr_event_occurrence_name_reconciliation_preview($plan_id, $operation_id);
    $assert(empty($historical_conflict_preview['allowed']) && (int) $historical_conflict_preview['counts']['unsafe_rows'] >= 1, 'Historical original-name identity conflict did not fail closed.');
    wc_update_order_item_meta($ga_item_id, '_vms_original_order_item_name_snapshot', (string) $reconcile_items['ga']['original_name']);

    $youth_item_id = (int) $reconcile_items['youth']['item_id'];
    wc_update_order_item_meta($youth_item_id, '_vms_effective_event_start_local', '2026-09-26 19:00:00');
    $occurrence_conflict_preview = bvmgr_event_occurrence_name_reconciliation_preview($plan_id, $operation_id);
    $assert(empty($occurrence_conflict_preview['allowed']) && (int) $occurrence_conflict_preview['counts']['unsafe_rows'] >= 1, 'Conflicting effective occurrence did not fail closed.');
    wc_update_order_item_meta($youth_item_id, '_vms_effective_event_start_local', (string) $new_payload['start_local']);

    $reconciliation_preview = bvmgr_event_occurrence_name_reconciliation_preview($plan_id, $operation_id);
    $assert(
        !empty($reconciliation_preview['allowed'])
        && (int) $reconciliation_preview['counts']['eligible_changes'] === 6
        && (int) $reconciliation_preview['counts']['already_canonical'] === 2
        && (int) $reconciliation_preview['counts']['ignored_operation_rows'] >= 2,
        'Operation-scoped name reconciliation did not distinguish eligible, canonical, and unrelated legacy/current rows: ' . wp_json_encode($reconciliation_preview)
    );
    foreach ($reconcile_items as $fixture) {
        $preview_rows = array_values(array_filter((array) $reconciliation_preview['rows'], static function (array $row) use ($fixture): bool {
            return (int) ($row['order_item_id'] ?? 0) === (int) $fixture['item_id'];
        }));
        $preview_row = (array) ($preview_rows[0] ?? array());
        $assert(
            (string) ($preview_row['current_name'] ?? '') === (string) $fixture['label']
            && (string) ($preview_row['proposed_name'] ?? '') === (string) $fixture['expected_name']
            && (string) ($preview_row['current_effective_occurrence'] ?? '') === $new_date . ' 19:00:00'
            && (string) ($preview_row['historical_original_name_snapshot'] ?? '') === (string) $fixture['original_name']
            && !empty($preview_row['safe']),
            'Required reconciliation preview fields changed for ' . (string) $fixture['label'] . ': ' . wp_json_encode($preview_row)
        );
    }

    $resolver_before_reconciliation = BVMGR_Ticket_Revenue_Service::get_sales_result(array(
        'event_plan_ids' => array($plan_id),
        'order_statuses' => array('completed'),
        'include_unresolved' => true,
        'include_refunded_lines' => true,
    ));
    $entitlement_shape_before = array(
        'rows' => count((array) ($resolver_before_reconciliation['rows'] ?? array())),
        'quantity' => array_sum(array_map(static fn(array $row): int => (int) ($row['qty'] ?? 0), (array) ($resolver_before_reconciliation['rows'] ?? array()))),
    );
    $reconciliation_fingerprint = bvmgr_event_occurrence_name_reconciliation_fingerprint($reconciliation_preview);
    $rollback_reconciliation = static function (): void {
        throw new RuntimeException('Fixture name-reconciliation rollback injection.');
    };
    add_action('bvmgr_event_occurrence_name_reconciliation_before_verify', $rollback_reconciliation, 10, 0);
    $rolled_back_reconciliation = bvmgr_event_occurrence_name_reconciliation_apply($plan_id, $operation_id, 1, $reconciliation_fingerprint);
    remove_action('bvmgr_event_occurrence_name_reconciliation_before_verify', $rollback_reconciliation, 10);
    $assert(empty($rolled_back_reconciliation['ok']) && !empty($rolled_back_reconciliation['rolled_back']), 'Injected name-reconciliation failure did not roll back.');
    foreach ($reconcile_items as $fixture) {
        $assert((string) (new WC_Order_Item_Product((int) $fixture['item_id']))->get_name() === (string) $fixture['label'], 'Rollback left a partial current-name change for ' . (string) $fixture['label'] . '.');
    }

    $reconciliation_preview = bvmgr_event_occurrence_name_reconciliation_preview($plan_id, $operation_id);
    $reconciled = bvmgr_event_occurrence_name_reconciliation_apply(
        $plan_id,
        $operation_id,
        1,
        bvmgr_event_occurrence_name_reconciliation_fingerprint($reconciliation_preview)
    );
    $assert(!empty($reconciled['ok']) && count((array) $reconciled['changed_order_item_ids']) === 6, 'Current-name reconciliation did not apply all six representative changes: ' . (string) ($reconciled['message'] ?? ''));
    foreach ($reconcile_items as $fixture) {
        $item_id = (int) $fixture['item_id'];
        $assert((string) (new WC_Order_Item_Product($item_id))->get_name() === (string) $fixture['expected_name'], 'Canonical reconciled name changed for ' . (string) $fixture['label'] . '.');
        $assert((string) wc_get_order_item_meta($item_id, '_vms_original_order_item_name_snapshot', true) === (string) $fixture['original_name'], 'Historical original name was overwritten for ' . (string) $fixture['label'] . '.');
        $assert((string) wc_get_order_item_meta($item_id, '_vms_effective_event_start_local', true) === $new_date . ' 19:00:00', 'Effective occurrence changed during name reconciliation for ' . (string) $fixture['label'] . '.');
    }
    $assert(
        (new WC_Order_Item_Product($ordinary_ga_id))->get_name() === $ordinary_state_before[$ordinary_ga_id]
        && (new WC_Order_Item_Product($ordinary_fire_id))->get_name() === $ordinary_state_before[$ordinary_fire_id],
        'Operation-scoped reconciliation touched ordinary current-occurrence lines.'
    );
    $assert(
        (new WC_Order_Item_Product((int) $reconcile_items['ga']['item_id']))->get_name() === (new WC_Order_Item_Product($ordinary_ga_id))->get_name(),
        'Repaired and ordinary GA lines do not share the canonical current display name.'
    );
    $assert(
        (new WC_Order_Item_Product((int) $reconcile_items['fire_table']['item_id']))->get_name() === (new WC_Order_Item_Product($ordinary_fire_id))->get_name(),
        'Repaired and ordinary numbered reservations do not share the canonical current display name.'
    );

    $resolver_after_reconciliation = BVMGR_Ticket_Revenue_Service::get_sales_result(array(
        'event_plan_ids' => array($plan_id),
        'order_statuses' => array('completed'),
        'include_unresolved' => true,
        'include_refunded_lines' => true,
    ));
    $entitlement_shape_after = array(
        'rows' => count((array) ($resolver_after_reconciliation['rows'] ?? array())),
        'quantity' => array_sum(array_map(static fn(array $row): int => (int) ($row['qty'] ?? 0), (array) ($resolver_after_reconciliation['rows'] ?? array()))),
    );
    $assert($entitlement_shape_after === $entitlement_shape_before, 'Name reconciliation created or removed an entitlement.');
    $resolver_names = array();
    foreach ((array) ($resolver_after_reconciliation['rows'] ?? array()) as $resolver_row) {
        $resolver_names[(int) ($resolver_row['order_item_id'] ?? 0)] = (string) ($resolver_row['product_name'] ?? '');
    }
    $assert(
        ($resolver_names[(int) $reconcile_items['ga']['item_id']] ?? '') === ($resolver_names[$ordinary_ga_id] ?? '')
        && ($resolver_names[(int) $reconcile_items['fire_table']['item_id']] ?? '') === ($resolver_names[$ordinary_fire_id] ?? ''),
        'Canonical sales resolver still exposes split GA or reservation labels after reconciliation.'
    );
    $revenue_report = bvmgr_ticket_revenue_build_report(array(
        'event_plan_id' => $plan_id,
        'order_statuses' => array('completed'),
    ));
    $revenue_names = array();
    foreach ((array) ($revenue_report['rows'] ?? array()) as $revenue_row) {
        $revenue_names[(int) ($revenue_row['line_item_id'] ?? 0)] = (string) ($revenue_row['item_name'] ?? '');
    }
    $assert(
        ($revenue_names[(int) $reconcile_items['ga']['item_id']] ?? '') === ($revenue_names[$ordinary_ga_id] ?? '')
        && ($revenue_names[(int) $reconcile_items['fire_table']['item_id']] ?? '') === ($revenue_names[$ordinary_fire_id] ?? ''),
        'BVM revenue/export rows still expose split GA or reservation labels after reconciliation.'
    );
    $reconciliation_repeat = bvmgr_event_occurrence_name_reconciliation_apply($plan_id, $operation_id, 1);
    $assert(
        !empty($reconciliation_repeat['ok'])
        && !empty($reconciliation_repeat['noop'])
        && empty($reconciliation_repeat['changed_order_item_ids'])
        && count(bvmgr_event_occurrence_history($plan_id)) === 1,
        'Name reconciliation replay was not an audit-preserving no-op.'
    );

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
    fwrite(STDOUT, 'PASS: ' . $event_day_assertions . " Event-Day load/render assertions.\n");
} finally {
    $cleanup();
}
