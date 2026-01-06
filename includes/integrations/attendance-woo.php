<?php
if (!defined('ABSPATH')) exit;

add_filter('vms_has_attended_event', 'vms_check_woo_tickets_for_attendance', 10, 3);
function vms_check_woo_tickets_for_attendance($has_attended, $event_id, $email)
{

    if ($has_attended) {
        return true; // short-circuit if another integration already approved
    }

    if (!class_exists('WooCommerce')) {
        return false;
    }

    if (empty($event_id) || empty($email)) {
        return false;
    }

    // 1. Find all ticket products tied to this TEC event
    $ticket_product_ids = vms_get_ticket_product_ids_for_event($event_id);
    if (empty($ticket_product_ids)) {
        return false;
    }

    // 2. Find Woo orders by billing email
    $orders = wc_get_orders(array(
        'billing_email' => $email,
        'status'        => array('completed', 'processing'),
        'limit'         => -1,
    ));

    if (empty($orders)) {
        return false;
    }

    // 3. Check if any order contains a ticket for this event
    foreach ($orders as $order) {
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            if (in_array($product_id, $ticket_product_ids, true)) {
                return true;
            }
        }
    }

    return false;
}
