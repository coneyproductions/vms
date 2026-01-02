<?php
/**
 * Find the Event Plan associated with a TEC event.
 *
 * @param int $tec_event_id tribe_events post ID
 * @return int|null Event Plan ID or null if none
 */
function vms_get_event_plan_for_tec_event($tec_event_id) {
    $tec_event_id = (int) $tec_event_id;
    if (!$tec_event_id) {
        return null;
    }

    $plans = get_posts(array(
        'post_type'      => 'vms_event_plan',
        'posts_per_page' => 1,
        'post_status'    => array('publish', 'draft', 'pending'),
        'meta_query'     => array(
            array(
                'key'   => '_vms_tec_event_id',
                'value' => $tec_event_id,
            ),
        ),
        'fields'         => 'ids',
    ));

    if (empty($plans)) {
        return null;
    }

    return (int) $plans[0];
}

/**
 * Get Woo ticket product IDs for a TEC event.
 *
 * @param int $event_id
 * @return int[]
 */
function vms_get_ticket_product_ids_for_event($event_id) {
    $event_id = (int) $event_id;
    if (!$event_id) {
        return array();
    }

    $tickets = get_posts(array(
        'post_type'      => 'product',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'meta_query'     => array(
            array(
                'key'   => '_tribe_wooticket_for_event',
                'value' => $event_id,
            ),
        ),
        'fields'         => 'ids',
    ));

    return array_map('intval', $tickets);
}
