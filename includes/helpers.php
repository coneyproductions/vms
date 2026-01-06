<?php
if (!defined('ABSPATH')) exit;

/**
 * Find the Event Plan associated with a TEC event.
 *
 * @param int $tec_event_id tribe_events post ID
 * @return int|null Event Plan ID or null if none
 */

function vms_label(string $key, string $default): string
{
    $opts = (array) get_option('vms_settings', array());
    $val  = isset($opts["label_$key"]) ? trim((string)$opts["label_$key"]) : '';
    return ($val !== '') ? $val : $default;
}

function vms_ui_text(string $key, string $default): string
{
    $opts = (array) get_option('vms_settings', array());
    $val  = isset($opts["ui_$key"]) ? trim((string)$opts["ui_$key"]) : '';
    return ($val !== '') ? $val : $default;
}

function vms_get_event_plan_for_tec_event($tec_event_id)
{
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
function vms_get_ticket_product_ids_for_event($event_id)
{
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

function vms_vendor_app_redirect($app_id, $result)
{
    $url = add_query_arg(array(
        'post'   => $app_id,
        'action' => 'edit',
        'vms_app_result' => $result,
    ), admin_url('post.php'));

    wp_safe_redirect($url);
    exit;
}

add_filter('get_avatar_url', 'vms_vendor_avatar_from_logo', 10, 3);
function vms_vendor_avatar_from_logo($url, $id_or_email, $args)
{
    // Identify user ID from the avatar call
    $user = false;

    if (is_numeric($id_or_email)) {
        $user = get_user_by('id', (int) $id_or_email);
    } elseif (is_object($id_or_email) && !empty($id_or_email->user_id)) {
        $user = get_user_by('id', (int) $id_or_email->user_id);
    } elseif (is_string($id_or_email)) {
        $user = get_user_by('email', $id_or_email);
    }

    if (!$user) return $url;

    // Only override for vendor users that are linked to a vendor profile
    $vendor_id = (int) get_user_meta($user->ID, '_vms_vendor_id', true);
    if (!$vendor_id) return $url;

    $thumb_id = get_post_thumbnail_id($vendor_id);
    if (!$thumb_id) return $url;

    $custom = wp_get_attachment_image_url($thumb_id, 'thumbnail');
    return $custom ? $custom : $url;
}

function vms_get_timezone_id(): string
{
    $opts = (array) get_option('vms_settings', array());
    $tz = isset($opts['timezone']) ? trim((string)$opts['timezone']) : '';

    if ($tz !== '') return $tz;

    $wp_tz = (string) get_option('timezone_string');
    if ($wp_tz !== '') return $wp_tz;

    return 'UTC';
}

function vms_get_timezone(): DateTimeZone
{
    return new DateTimeZone(vms_get_timezone_id());
}
