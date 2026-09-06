<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_dt_vio_portal_notice_html')) {
    function vms_dt_vio_portal_notice_html(string $type, string $message): string
    {
        if (vms_dt_has_core_function('vms_portal_notice')) {
            return vms_dt_call_core_function('vms_portal_notice', $type, $message);
        }

        $class = ($type === 'success' || $type === 'warning') ? $type : 'error';
        return '<div class="vms-notice vms-notice-' . esc_attr($class) . '">' . esc_html($message) . '</div>';
    }
}

if (!function_exists('vms_dt_vio_vendor_portal_nav_link')) {
    /**
     * @param array<string,mixed> $context
     */
    function vms_dt_vio_vendor_portal_nav_link(string $current_tab, array $context): void
    {
        $base_url = (string) ($context['base_url'] ?? '');
        $vendor_id = absint($context['vendor_id'] ?? 0);
        if ($base_url === '' || $vendor_id <= 0) {
            return;
        }

        $url = add_query_arg(array(
            'vendor_id' => $vendor_id,
            'tab' => 'opportunities',
        ), $base_url);

        echo '<a class="' . ($current_tab === 'opportunities' ? 'is-active' : '') . '" href="' . esc_url($url) . '">' . esc_html__('Opportunities', 'vms-data-tools') . '</a>';
    }
}
add_action('vms_vendor_portal_nav_links', 'vms_dt_vio_vendor_portal_nav_link', 20, 2);

if (!function_exists('vms_dt_vio_render_vendor_portal_opportunities')) {
    /**
     * @param array<string,mixed> $context
     */
    function vms_dt_vio_render_vendor_portal_opportunities(array $context): void
    {
        $vendor_id = absint($context['vendor_id'] ?? 0);
        $current_tab = sanitize_key((string) ($context['tab'] ?? ''));
        $base_url = (string) ($context['base_url'] ?? '');

        if ($vendor_id <= 0 || $current_tab !== 'opportunities') {
            echo vms_dt_vio_portal_notice_html('error', __('Your vendor context could not be resolved for opportunities.', 'vms-data-tools'));
            return;
        }

        $messages = array(
            'success' => array(),
            'info' => array(),
            'error' => array(),
        );

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vms_dt_vio_portal_action'])) {
            $action = sanitize_key((string) wp_unslash($_POST['vms_dt_vio_portal_action']));
            if ($action === 'submit_interest') {
                check_admin_referer('vms_dt_vio_submit_interest', 'vms_dt_vio_interest_nonce');
                $event_plan_id = absint($_POST['event_plan_id'] ?? 0);
                $result = vms_dt_vio_create_interest_submission($vendor_id, $event_plan_id, array(
                    'actor_user_id' => absint(get_current_user_id()),
                ));

                if (is_wp_error($result)) {
                    $messages['error'][] = $result->get_error_message();
                } else {
                    $duplicate = !empty($result['duplicate']);
                    $status = vms_dt_vio_normalize_opportunity_status((string) ($result['status'] ?? 'pending'));
                    if ($duplicate && $status === 'accepted') {
                        $messages['info'][] = __('You were already accepted for this opportunity.', 'vms-data-tools');
                    } elseif ($duplicate) {
                        $messages['info'][] = __('Your interest was already submitted for this opportunity.', 'vms-data-tools');
                    } else {
                        $messages['success'][] = __('Your interest was submitted successfully.', 'vms-data-tools');
                    }
                }
            }
        }

        $vendor_type = vms_dt_vio_get_vendor_type_slug($vendor_id);
        if ($vendor_type === '') {
            echo '<div class="vms-portal-card">';
            echo '<h3>' . esc_html__('Opportunities', 'vms-data-tools') . '</h3>';
            echo vms_dt_vio_portal_notice_html('warning', __('Your vendor profile needs a vendor type before opportunities can be shown here.', 'vms-data-tools'));
            echo '</div>';
            return;
        }

        $vendor_type_label = '';
        $term = get_term_by('slug', $vendor_type, 'vms_vendor_type');
        if ($term instanceof WP_Term) {
            $vendor_type_label = (string) $term->name;
        }

        $opportunities = vms_dt_vio_find_open_dates(array(
            'vendor_type' => $vendor_type,
            'next_n' => 60,
            'lookahead_days' => 180,
            'include_primary_vendor' => false,
            'include_tentative' => false,
        ));
        $event_plan_ids = array_values(array_unique(array_filter(array_map('absint', wp_list_pluck($opportunities, 'event_plan_id')))));
        $submission_map = vms_dt_vio_get_vendor_submission_map($vendor_id, $event_plan_ids);

        echo '<div class="vms-portal-card vms-vio-opps-card">';
        echo '<h3>' . esc_html__('Opportunities', 'vms-data-tools') . '</h3>';
        echo '<p class="vms-muted vms-m0">' . esc_html__('Upcoming published opportunities are shown here. Use “I’m Interested” to raise your hand for an open date.', 'vms-data-tools') . '</p>';
        if ($vendor_type_label !== '') {
            echo '<p class="vms-muted vms-mt-8">' . sprintf(
                /* translators: %s is the vendor type label. */
                esc_html__('Showing opportunities for %s.', 'vms-data-tools'),
                esc_html($vendor_type_label)
            ) . '</p>';
        }

        foreach ($messages as $type => $items) {
            foreach ($items as $message) {
                echo vms_dt_vio_portal_notice_html($type, (string) $message);
            }
        }

        if (empty($opportunities)) {
            echo '<p class="vms-muted vms-mt-10">' . esc_html__('No open opportunities are available in the current window.', 'vms-data-tools') . '</p>';
            echo '</div>';
            return;
        }

        $tz = vms_dt_has_core_function('vms_get_timezone') ? vms_dt_call_core_function('vms_get_timezone') : wp_timezone();
        $date_format = (string) get_option('date_format', 'M j, Y');

        echo '<div class="vms-vio-opps-list">';
        foreach ($opportunities as $opportunity) {
            if (!is_array($opportunity)) {
                continue;
            }

            $event_plan_id = absint($opportunity['event_plan_id'] ?? 0);
            if ($event_plan_id <= 0) {
                continue;
            }

            $date_ts = absint($opportunity['date_ts'] ?? 0);
            $event_title = trim((string) ($opportunity['event_title'] ?? ''));
            $venue_name = trim((string) ($opportunity['venue_name'] ?? ''));
            $submission = isset($submission_map[$event_plan_id]) && is_array($submission_map[$event_plan_id])
                ? $submission_map[$event_plan_id]
                : null;
            $submission_status = is_array($submission) ? vms_dt_vio_normalize_opportunity_status((string) ($submission['status'] ?? 'pending')) : '';

            echo '<article class="vms-vio-opps-item">';
            echo '<div class="vms-vio-opps-item__meta">';
            echo '<div class="vms-vio-opps-item__date">' . esc_html($date_ts > 0 ? wp_date($date_format, $date_ts, $tz) : (string) ($opportunity['date_ymd'] ?? '')) . '</div>';
            if ($venue_name !== '') {
                echo '<div class="vms-vio-opps-item__venue">' . esc_html($venue_name) . '</div>';
            }
            echo '</div>';
            echo '<div class="vms-vio-opps-item__body">';
            echo '<h4 class="vms-vio-opps-item__title">' . esc_html($event_title !== '' ? $event_title : __('Untitled Event Plan', 'vms-data-tools')) . '</h4>';
            echo '<div class="vms-vio-opps-item__actions">';
            if ($submission_status !== '') {
                $label = (string) (vms_dt_vio_opportunity_status_labels()[$submission_status] ?? ucfirst($submission_status));
                echo '<span class="vms-vio-opps-status vms-vio-opps-status--' . esc_attr($submission_status) . '">' . esc_html($label) . '</span>';
            } else {
                echo '<form method="post" action="' . esc_url(add_query_arg(array(
                    'vendor_id' => $vendor_id,
                    'tab' => 'opportunities',
                ), $base_url)) . '">';
                wp_nonce_field('vms_dt_vio_submit_interest', 'vms_dt_vio_interest_nonce');
                echo '<input type="hidden" name="vms_dt_vio_portal_action" value="submit_interest">';
                echo '<input type="hidden" name="event_plan_id" value="' . esc_attr((string) $event_plan_id) . '">';
                echo '<button type="submit" class="button button-primary">' . esc_html__('I’m Interested', 'vms-data-tools') . '</button>';
                echo '</form>';
            }
            echo '</div>';
            echo '</div>';
            echo '</article>';
        }
        echo '</div>';
        echo '</div>';
    }
}

if (!function_exists('vms_dt_vio_vendor_portal_render_custom_tab')) {
    /**
     * @param array<string,mixed> $context
     */
    function vms_dt_vio_vendor_portal_render_custom_tab(bool $handled, string $tab, array $context): bool
    {
        if ($handled || $tab !== 'opportunities') {
            return $handled;
        }

        vms_dt_vio_render_vendor_portal_opportunities($context);
        return true;
    }
}
add_filter('vms_vendor_portal_render_custom_tab', 'vms_dt_vio_vendor_portal_render_custom_tab', 20, 3);
