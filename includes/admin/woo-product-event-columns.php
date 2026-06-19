<?php
defined('ABSPATH') || exit;

/**
 * Woo Products list: add Event context for VMS ticket/entitlement products.
 *
 * Why:
 * - We keep product titles clean ("General Admission", "Veteran")
 * - Operators still need to identify which event a product belongs to
 */

add_filter('manage_edit-product_columns', 'vms_ticketing_v2_add_product_event_columns', 20, 1);
function vms_ticketing_v2_add_product_event_columns(array $cols): array
{
    $out = array();
    $inserted = false;

    foreach ($cols as $key => $label) {
        $out[$key] = $label;
        if ($key === 'name') {
            $out['vms_event'] = __('Event', 'vms');
            $out['vms_event_date'] = __('Event Date', 'vms');
            $out['vms_square_mirror'] = __('Square Mirror', 'vms');
            $inserted = true;
        }
    }

    if (!$inserted) {
        $out['vms_event'] = __('Event', 'vms');
        $out['vms_event_date'] = __('Event Date', 'vms');
        $out['vms_square_mirror'] = __('Square Mirror', 'vms');
    }

    return $out;
}

add_action('manage_product_posts_custom_column', 'vms_ticketing_v2_render_product_event_columns', 20, 2);
function vms_ticketing_v2_render_product_event_columns(string $column, int $post_id): void
{
    if (!in_array($column, array('vms_event', 'vms_event_date', 'vms_square_mirror'), true)) {
        return;
    }

    $product_id = absint($post_id);
    if ($product_id <= 0 || get_post_type($product_id) !== 'product') {
        echo '—';
        return;
    }

    if ($column === 'vms_square_mirror') {
        $is_relevant = vms_square_ticket_mirror_has_mirror_meta($product_id)
            || vms_square_ticket_mirror_product_role($product_id) === 'ga_ticket'
            || (function_exists('vms_square_firewall_is_protected_product') && vms_square_firewall_is_protected_product($product_id));

        if (!$is_relevant) {
            echo '—';
            return;
        }

        $state = vms_square_ticket_mirror_status_context($product_id);
        if (function_exists('vms_square_ticket_mirror_admin_status_badge')) {
            echo vms_square_ticket_mirror_admin_status_badge((string) ($state['status'] ?? 'not_mirrored'));
        } else {
            echo esc_html(vms_square_ticket_mirror_label_for_status((string) ($state['status'] ?? 'not_mirrored')));
        }
        return;
    }

    // Resolve TEC event ID for this product.
    $tec_event_id = 0;
    if (function_exists('vms_ticketing_v2_resolve_event_id_for_product')) {
        $tec_event_id = absint(vms_ticketing_v2_resolve_event_id_for_product($product_id));
    }
    if ($tec_event_id <= 0) {
        $tec_event_id = absint(get_post_meta($product_id, '_tribe_wooticket_for_event', true));
    }
    if ($tec_event_id <= 0 || get_post_type($tec_event_id) !== 'tribe_events') {
        echo '—';
        return;
    }

    if ($column === 'vms_event') {
        $title = trim((string) get_the_title($tec_event_id));
        if ($title === '') {
            echo '—';
            return;
        }

        $edit = function_exists('get_edit_post_link') ? (string) get_edit_post_link($tec_event_id) : '';
        if ($edit !== '') {
            echo '<a href="' . esc_url($edit) . '">' . esc_html($title) . '</a>';
        } else {
            echo esc_html($title);
        }
        return;
    }

    // Event date
    $date = '';
    if (function_exists('vms_ticketing_v2_format_event_date_for_product_title')) {
        $date = (string) vms_ticketing_v2_format_event_date_for_product_title($tec_event_id);
    }
    $date = trim($date);

    if ($date === '' && function_exists('tribe_get_start_date')) {
        $date = trim((string) tribe_get_start_date($tec_event_id, false, 'M j, Y'));
    }

    if ($date === '') {
        $raw = (string) get_post_meta($tec_event_id, '_EventStartDate', true);
        if ($raw !== '') {
            $ts = strtotime($raw);
            if ($ts) {
                $date = (string) wp_date('M j, Y', $ts, wp_timezone());
            }
        }
    }

    echo $date !== '' ? esc_html($date) : '—';
}
