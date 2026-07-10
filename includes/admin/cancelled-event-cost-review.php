<?php

defined('ABSPATH') || exit;

if (!function_exists('vms_cancelled_cost_review_is_cancelled')) {
    function vms_cancelled_cost_review_is_cancelled(int $event_plan_id): bool
    {
        $status = function_exists('vms_event_plan_get_status')
            ? (string) vms_event_plan_get_status($event_plan_id, 'dashboard')
            : (string) get_post_meta($event_plan_id, '_vms_event_plan_status', true);
        $status = sanitize_key($status);
        if ($status === 'canceled') {
            $status = 'cancelled';
        }
        return $status === 'cancelled';
    }
}

if (!function_exists('vms_cancelled_cost_review_vendor_direct_cents')) {
    function vms_cancelled_cost_review_vendor_direct_cents(int $event_plan_id): int
    {
        if (function_exists('vms_goals_get_default_direct_costs_cents')) {
            return max(0, (int) vms_goals_get_default_direct_costs_cents($event_plan_id));
        }
        $manual = get_post_meta($event_plan_id, '_vms_event_direct_costs_cents', true);
        return ($manual !== '' && is_numeric($manual)) ? max(0, (int) $manual) : 0;
    }
}

if (!function_exists('vms_cancelled_cost_review_labor_cents')) {
    function vms_cancelled_cost_review_labor_cents(int $event_plan_id): int
    {
        if (function_exists('vms_event_profitability_get_labor_cost_cents')) {
            return max(0, (int) vms_event_profitability_get_labor_cost_cents($event_plan_id));
        }
        return 0;
    }
}

if (!function_exists('vms_cancelled_cost_review_add_metabox')) {
    function vms_cancelled_cost_review_add_metabox(): void
    {
        add_meta_box(
            'vms-cancelled-cost-review',
            __('Cancelled Event Cost Review', 'backstage-venue-manager'),
            'vms_cancelled_cost_review_render_metabox',
            'vms_event_plan',
            'side',
            'high'
        );
    }
}
add_action('add_meta_boxes_vms_event_plan', 'vms_cancelled_cost_review_add_metabox', 30);

if (!function_exists('vms_cancelled_cost_review_render_metabox')) {
    function vms_cancelled_cost_review_render_metabox(WP_Post $post): void
    {
        $event_plan_id = (int) $post->ID;
        if ($event_plan_id <= 0) {
            echo '<p>' . esc_html__('No event plan loaded.', 'backstage-venue-manager') . '</p>';
            return;
        }

        if (!vms_cancelled_cost_review_is_cancelled($event_plan_id)) {
            echo '<p>' . esc_html__('This review box appears when the event is marked Cancelled.', 'backstage-venue-manager') . '</p>';
            return;
        }

        $labor_cents = vms_cancelled_cost_review_labor_cents($event_plan_id);
        $vendor_direct_cents = vms_cancelled_cost_review_vendor_direct_cents($event_plan_id);
        $total_cents = $labor_cents + $vendor_direct_cents;
        $profitability_url = function_exists('vms_event_profitability_admin_url')
            ? vms_event_profitability_admin_url()
            : admin_url('admin.php?page=vms-event-profitability');

        echo '<div class="notice notice-warning inline"><p><strong>' . esc_html__('Cancelled plans should review estimated costs.', 'backstage-venue-manager') . '</strong></p>';
        if ($total_cents > 0) {
            echo '<p>' . esc_html__('This cancelled event still shows estimated costs. Zero out anything that was not actually owed so reporting does not overstate the loss.', 'backstage-venue-manager') . '</p>';
            echo '<ul class="ul-disc">';
            /* translators: %s: Formatted labor estimate amount. */
            echo '<li>' . esc_html(sprintf(__('Labor estimate: %s', 'backstage-venue-manager'), function_exists('vms_goals_fmt_money') ? vms_goals_fmt_money($labor_cents) : ('$' . number_format($labor_cents / 100, 2)))) . '</li>';
            /* translators: %s: Formatted vendor/direct estimate amount. */
            echo '<li>' . esc_html(sprintf(__('Vendor/direct estimate: %s', 'backstage-venue-manager'), function_exists('vms_goals_fmt_money') ? vms_goals_fmt_money($vendor_direct_cents) : ('$' . number_format($vendor_direct_cents / 100, 2)))) . '</li>';
            /* translators: %s: Formatted total loaded estimate amount. */
            echo '<li>' . esc_html(sprintf(__('Total still loaded: %s', 'backstage-venue-manager'), function_exists('vms_goals_fmt_money') ? vms_goals_fmt_money($total_cents) : ('$' . number_format($total_cents / 100, 2)))) . '</li>';
            echo '</ul>';
        } else {
            echo '<p>' . esc_html__('No estimated labor or vendor/direct costs are currently loaded on this cancelled event.', 'backstage-venue-manager') . '</p>';
        }
        echo '<p>' . esc_html__('Money changes are not auto-cleared here on purpose. Review the actual obligations first, then update the event plan accordingly.', 'backstage-venue-manager') . '</p>';
        echo '<p><a class="button button-secondary" href="' . esc_url($profitability_url) . '">' . esc_html__('Open profitability board', 'backstage-venue-manager') . '</a></p>';
        echo '</div>';
    }
}
