<?php
defined('ABSPATH') || exit;

add_action('dynamic_sidebar_before', 'vms_public_event_sidebar_render_before_widgets', 5, 2);

if (!function_exists('vms_public_event_sidebar_targets')) {
    function vms_public_event_sidebar_targets(int $event_id): array
    {
        $targets = apply_filters('vms_public_event_sidebar_targets', array('sidebar-primary'), $event_id);
        if (!is_array($targets)) {
            $targets = array('sidebar-primary');
        }

        $clean = array();
        foreach ($targets as $target) {
            $target = sanitize_key((string) $target);
            if ($target !== '') {
                $clean[] = $target;
            }
        }

        return array_values(array_unique($clean));
    }
}

if (!function_exists('vms_public_event_sidebar_wrap_module')) {
    function vms_public_event_sidebar_wrap_module(string $markup, string $slug): string
    {
        $markup = trim($markup);
        if ($markup === '') {
            return '';
        }

        $slug = sanitize_html_class($slug);
        if ($slug === '') {
            $slug = 'module';
        }

        return '<section class="widget widget_block vms-event-sidebar-widget vms-event-sidebar-widget--' . esc_attr($slug) . '" data-vms-event-sidebar-widget="' . esc_attr($slug) . '">' . $markup . '</section>';
    }
}

if (!function_exists('vms_public_event_sidebar_render_stack')) {
    function vms_public_event_sidebar_render_stack(int $event_id): string
    {
        $event_id = absint($event_id);
        if ($event_id <= 0 || get_post_type($event_id) !== 'tribe_events') {
            return '';
        }

        $modules = array();

        if (function_exists('vms_event_details_sidebar_rendered') && !vms_event_details_sidebar_rendered($event_id)) {
            $details_markup = function_exists('vms_event_details_render_card')
                ? (string) vms_event_details_render_card($event_id, true, '', 'sidebar')
                : '';
            if ($details_markup !== '') {
                vms_event_details_mark_sidebar_rendered($event_id);
                $modules[] = vms_public_event_sidebar_wrap_module($details_markup, 'event-details');
            }
        }

        $vendor_markup = function_exists('vms_vendor_profiles_render_event_vendor_sidebar')
            ? (string) vms_vendor_profiles_render_event_vendor_sidebar($event_id)
            : '';
        if ($vendor_markup !== '') {
            $modules[] = vms_public_event_sidebar_wrap_module($vendor_markup, 'vendor-groups');
        }

        if (empty($modules)) {
            return '';
        }

        return '<div class="vms-event-sidebar-stack" data-vms-event-sidebar-stack="1">' . implode('', $modules) . '</div>';
    }
}

if (!function_exists('vms_public_event_sidebar_render_before_widgets')) {
    function vms_public_event_sidebar_render_before_widgets($index, $has_widgets): void
    {
        if (is_admin() || !function_exists('is_singular') || !is_singular('tribe_events')) {
            return;
        }

        $event_id = (int) get_queried_object_id();
        if ($event_id <= 0 || get_post_type($event_id) !== 'tribe_events') {
            return;
        }

        $index = sanitize_key((string) $index);
        if ($index === '' || !in_array($index, vms_public_event_sidebar_targets($event_id), true)) {
            return;
        }

        if (!(bool) apply_filters('vms_public_event_sidebar_auto_render', true, $event_id, $index, (bool) $has_widgets)) {
            return;
        }

        static $rendered = array();
        $render_key = $event_id . ':' . $index;
        if (!empty($rendered[$render_key])) {
            return;
        }

        $markup = vms_public_event_sidebar_render_stack($event_id);
        if ($markup === '') {
            return;
        }

        $rendered[$render_key] = true;
        echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
}
