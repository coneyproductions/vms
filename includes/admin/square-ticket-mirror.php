<?php
defined('ABSPATH') || exit;

function vms_square_ticket_mirror_admin_status_badge(string $status): string
{
    $status = vms_square_ticket_mirror_normalize_status($status);
    $tones = array(
        'not_mirrored' => '#50575e',
        'mirrored' => '#0a7d2c',
        'mirror_stale' => '#996800',
        'mirror_retired' => '#6a4fb3',
        'mirror_error' => '#b32d2e',
    );

    $color = (string) ($tones[$status] ?? '#50575e');
    $label = vms_square_ticket_mirror_label_for_status($status);

    return '<span style="display:inline-block;padding:2px 8px;border-radius:999px;background:' . esc_attr($color) . ';color:#fff;font-size:12px;line-height:1.6;">' . esc_html($label) . '</span>';
}

function vms_square_ticket_mirror_admin_redirect_url(int $product_id): string
{
    $product_id = absint($product_id);
    $edit_link = $product_id > 0 ? get_edit_post_link($product_id, 'raw') : '';
    if (is_string($edit_link) && $edit_link !== '') {
        return $edit_link;
    }

    return admin_url('edit.php?post_type=product');
}

function vms_square_ticket_mirror_register_metabox($post): void
{
    $product_id = is_object($post) && isset($post->ID) ? absint($post->ID) : 0;
    if ($product_id <= 0) {
        return;
    }

    $should_show = vms_square_ticket_mirror_has_mirror_meta($product_id)
        || vms_square_ticket_mirror_product_role($product_id) === 'ga_ticket'
        || (function_exists('vms_square_firewall_is_protected_product') && vms_square_firewall_is_protected_product($product_id));

    if (!$should_show) {
        return;
    }

    add_meta_box(
        'vms_square_ticket_mirror',
        __('Square Ticket Mirror', 'backstage-venue-manager'),
        'vms_square_ticket_mirror_render_metabox',
        'product',
        'side',
        'default'
    );
}
add_action('add_meta_boxes_product', 'vms_square_ticket_mirror_register_metabox', 10, 1);

function vms_square_ticket_mirror_render_metabox($post): void
{
    $product_id = is_object($post) && isset($post->ID) ? absint($post->ID) : 0;
    if ($product_id <= 0) {
        echo '<p>' . esc_html__('Product context is unavailable.', 'backstage-venue-manager') . '</p>';
        return;
    }

    $state = vms_square_ticket_mirror_status_context($product_id);
    $eligibility = (array) ($state['eligibility'] ?? array());
    $source_model = (array) ($state['source_model'] ?? array());
    $square = vms_square_ticket_mirror_get_square_context();
    $action_url = admin_url('admin-post.php');

    echo '<div class="vms-square-ticket-mirror">';
    echo '<p><strong>' . esc_html__('Status', 'backstage-venue-manager') . ':</strong> ' . wp_kses(
        vms_square_ticket_mirror_admin_status_badge((string) ($state['status'] ?? 'not_mirrored')),
        array(
            'span' => array(
                'style' => true,
            ),
        )
    ) . '</p>';

    if (!empty($eligibility['eligible'])) {
        echo '<p><strong>' . esc_html__('Eligibility', 'backstage-venue-manager') . ':</strong> ' . esc_html__('Eligible paid public online ticket', 'backstage-venue-manager') . '</p>';
    } else {
        echo '<p><strong>' . esc_html__('Eligibility', 'backstage-venue-manager') . ':</strong> ' . esc_html((string) ($eligibility['reason_message'] ?? __('Not eligible', 'backstage-venue-manager'))) . '</p>';
    }

    if (!empty($source_model['mirror_name'])) {
        echo '<p><strong>' . esc_html__('Mirror Name', 'backstage-venue-manager') . ':</strong><br />' . esc_html((string) $source_model['mirror_name']) . '</p>';
    }

    echo '<p><strong>' . esc_html__('SKU', 'backstage-venue-manager') . ':</strong> <code>' . esc_html((string) ($eligibility['sku'] ?? '')) . '</code></p>';
    echo '<p><strong>' . esc_html__('Square Category', 'backstage-venue-manager') . ':</strong> ' . esc_html(vms_square_ticket_mirror_target_category_name()) . '</p>';

    if (!empty($state['item_id'])) {
        echo '<p><strong>' . esc_html__('Square Item ID', 'backstage-venue-manager') . ':</strong><br /><code>' . esc_html((string) $state['item_id']) . '</code></p>';
    }
    if (!empty($state['variation_id'])) {
        echo '<p><strong>' . esc_html__('Square Variation ID', 'backstage-venue-manager') . ':</strong><br /><code>' . esc_html((string) $state['variation_id']) . '</code></p>';
    }
    if (!empty($state['location_id'])) {
        echo '<p><strong>' . esc_html__('Square Location', 'backstage-venue-manager') . ':</strong><br /><code>' . esc_html((string) $state['location_id']) . '</code></p>';
    } elseif (!empty($square['location_id'])) {
        echo '<p><strong>' . esc_html__('Square Location', 'backstage-venue-manager') . ':</strong><br /><code>' . esc_html((string) $square['location_id']) . '</code></p>';
    }

    if (!empty($state['last_sync_gmt'])) {
        echo '<p><strong>' . esc_html__('Last Sync (GMT)', 'backstage-venue-manager') . ':</strong><br />' . esc_html((string) $state['last_sync_gmt']) . '</p>';
    }
    if (!empty($state['last_order_stamp_gmt'])) {
        echo '<p><strong>' . esc_html__('Last Order Stamp (GMT)', 'backstage-venue-manager') . ':</strong><br />' . esc_html((string) $state['last_order_stamp_gmt']) . '</p>';
    }
    if (!empty($state['last_retired_gmt'])) {
        echo '<p><strong>' . esc_html__('Last Retired (GMT)', 'backstage-venue-manager') . ':</strong><br />' . esc_html((string) $state['last_retired_gmt']) . '</p>';
    }

    if (!empty($state['last_error_message'])) {
        echo '<div class="notice notice-error inline"><p><strong>' . esc_html__('Last Error', 'backstage-venue-manager') . ':</strong> ' . esc_html((string) $state['last_error_message']) . '</p></div>';
    }

    if (empty($square['ok'])) {
        echo '<div class="notice notice-warning inline"><p>' . esc_html((string) ($square['error_message'] ?? __('WooCommerce Square is unavailable.', 'backstage-venue-manager'))) . '</p></div>';
    }

    echo '<div style="display:grid;gap:8px;">';

    if (!empty($eligibility['eligible']) && empty($state['item_id'])) {
        echo '<form method="post" action="' . esc_url($action_url) . '">';
        wp_nonce_field('vms_square_ticket_mirror_action_' . $product_id . '_mirror');
        echo '<input type="hidden" name="action" value="vms_square_ticket_mirror_action" />';
        echo '<input type="hidden" name="mirror_action" value="mirror" />';
        echo '<input type="hidden" name="product_id" value="' . esc_attr((string) $product_id) . '" />';
        submit_button(__('Mirror This Ticket to Square', 'backstage-venue-manager'), 'primary', 'submit', false);
        echo '</form>';
    }

    if (!empty($eligibility['eligible']) && (!empty($state['item_id']) || in_array((string) ($state['status'] ?? ''), array('mirror_stale', 'mirror_error', 'mirror_retired'), true))) {
        echo '<form method="post" action="' . esc_url($action_url) . '">';
        wp_nonce_field('vms_square_ticket_mirror_action_' . $product_id . '_refresh');
        echo '<input type="hidden" name="action" value="vms_square_ticket_mirror_action" />';
        echo '<input type="hidden" name="mirror_action" value="refresh" />';
        echo '<input type="hidden" name="product_id" value="' . esc_attr((string) $product_id) . '" />';
        submit_button(__('Refresh Square Mirror', 'backstage-venue-manager'), 'secondary', 'submit', false);
        echo '</form>';
    }

    if (!empty($state['item_id']) && (string) ($state['status'] ?? '') !== 'mirror_retired') {
        echo '<form method="post" action="' . esc_url($action_url) . '">';
        wp_nonce_field('vms_square_ticket_mirror_action_' . $product_id . '_retire');
        echo '<input type="hidden" name="action" value="vms_square_ticket_mirror_action" />';
        echo '<input type="hidden" name="mirror_action" value="retire" />';
        echo '<input type="hidden" name="product_id" value="' . esc_attr((string) $product_id) . '" />';
        submit_button(__('Retire Square Mirror', 'backstage-venue-manager'), 'delete', 'submit', false, array(
            'onclick' => "return confirm('" . esc_js(__('Retire this mirrored Square item from the resolved location?', 'backstage-venue-manager')) . "');",
        ));
        echo '</form>';
    }

    if ((string) ($state['status'] ?? '') === 'mirror_error' || !empty($state['last_error_code']) || !empty($state['last_error_message'])) {
        echo '<form method="post" action="' . esc_url($action_url) . '">';
        wp_nonce_field('vms_square_ticket_mirror_action_' . $product_id . '_clear_error');
        echo '<input type="hidden" name="action" value="vms_square_ticket_mirror_action" />';
        echo '<input type="hidden" name="mirror_action" value="clear_error" />';
        echo '<input type="hidden" name="product_id" value="' . esc_attr((string) $product_id) . '" />';
        submit_button(__('Clear Mirror Error', 'backstage-venue-manager'), 'secondary', 'submit', false);
        echo '</form>';
    }

    echo '</div>';

    echo '<p class="description">' . esc_html__('Phase 1 adds manual mirror, refresh, retire, and order-item stamping. Automatic expired-ticket archive/cleanup remains a follow-up slice.', 'backstage-venue-manager') . '</p>';

    $logs = vms_square_ticket_mirror_recent_logs($product_id, 5);
    if (!empty($logs)) {
        echo '<hr />';
        echo '<p><strong>' . esc_html__('Recent Mirror Logs', 'backstage-venue-manager') . '</strong></p>';
        echo '<table class="widefat striped" style="font-size:12px;">';
        echo '<thead><tr><th>' . esc_html__('When', 'backstage-venue-manager') . '</th><th>' . esc_html__('Action', 'backstage-venue-manager') . '</th><th>' . esc_html__('Result', 'backstage-venue-manager') . '</th></tr></thead>';
        echo '<tbody>';
        foreach ($logs as $row) {
            if (!is_array($row)) {
                continue;
            }
            $when = trim((string) ($row['created_at_gmt'] ?? ''));
            $action = sanitize_key((string) ($row['action'] ?? ''));
            $result = sanitize_key((string) ($row['status_after'] ?? ''));
            if ($result === '') {
                $result = sanitize_key((string) ($row['error_code'] ?? ''));
            }

            echo '<tr>';
            echo '<td>' . esc_html($when) . '</td>';
            echo '<td><code>' . esc_html($action) . '</code></td>';
            echo '<td><code>' . esc_html($result) . '</code></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    echo '</div>';
}

function vms_square_ticket_mirror_handle_admin_action(): void
{
    $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
    $mirror_action = isset($_POST['mirror_action']) ? sanitize_key((string) wp_unslash($_POST['mirror_action'])) : '';

    if ($product_id <= 0 || $mirror_action === '') {
        wp_safe_redirect(admin_url('edit.php?post_type=product'));
        exit;
    }

    if (!current_user_can('edit_post', $product_id)) {
        wp_die(esc_html__('Insufficient permissions.', 'backstage-venue-manager'));
    }

    check_admin_referer('vms_square_ticket_mirror_action_' . $product_id . '_' . $mirror_action);

    $notice_type = 'success';
    $notice_message = '';

    if ($mirror_action === 'mirror' || $mirror_action === 'refresh') {
        $result = vms_square_ticket_mirror_sync_product($product_id, array(
            'manual' => true,
            'action' => $mirror_action,
        ));

        if (!empty($result['ok'])) {
            $notice_message = $mirror_action === 'mirror'
                ? __('Square mirror created for this ticket.', 'backstage-venue-manager')
                : __('Square mirror refreshed for this ticket.', 'backstage-venue-manager');
        } else {
            $notice_type = 'error';
            $notice_message = (string) ($result['error_message'] ?? __('Square mirror action failed.', 'backstage-venue-manager'));
        }
    } elseif ($mirror_action === 'retire') {
        $result = vms_square_ticket_mirror_retire_product($product_id);
        if (!empty($result['ok'])) {
            $notice_message = __('Square mirror retired for this ticket.', 'backstage-venue-manager');
        } else {
            $notice_type = 'error';
            $notice_message = (string) ($result['error_message'] ?? __('Square mirror retire failed.', 'backstage-venue-manager'));
        }
    } elseif ($mirror_action === 'clear_error') {
        $result = vms_square_ticket_mirror_clear_error_state($product_id);
        if (!empty($result['ok'])) {
            $notice_message = __('Square mirror error state cleared.', 'backstage-venue-manager');
        } else {
            $notice_type = 'error';
            $notice_message = __('Square mirror error state could not be cleared.', 'backstage-venue-manager');
        }
    } else {
        $notice_type = 'error';
        $notice_message = __('Unknown Square mirror action.', 'backstage-venue-manager');
    }

    if ($notice_message !== '' && function_exists('vms_add_admin_notice')) {
        vms_add_admin_notice($notice_message, $notice_type);
    }

    wp_safe_redirect(vms_square_ticket_mirror_admin_redirect_url($product_id));
    exit;
}
add_action('admin_post_vms_square_ticket_mirror_action', 'vms_square_ticket_mirror_handle_admin_action');
