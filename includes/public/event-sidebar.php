<?php
defined('ABSPATH') || exit;

add_action('dynamic_sidebar_before', 'bvmgr_public_event_sidebar_track_context_before', 4, 2);
add_action('dynamic_sidebar_before', 'bvmgr_public_event_sidebar_render_before_widgets', 5, 2);
add_action('dynamic_sidebar_after', 'bvmgr_public_event_sidebar_track_context_after', 999, 2);

if (!function_exists('bvmgr_public_event_sidebar_targets')) {
    function bvmgr_public_event_sidebar_targets(int $event_id): array
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

if (!function_exists('bvmgr_public_event_sidebar_track_context_before')) {
    function bvmgr_public_event_sidebar_track_context_before($index, $has_widgets): void
    {
        unset($has_widgets);

        $index = sanitize_key((string) $index);
        if ($index === '') {
            return;
        }

        $stack = isset($GLOBALS['bvmgr_public_event_sidebar_active_indexes']) && is_array($GLOBALS['bvmgr_public_event_sidebar_active_indexes'])
            ? $GLOBALS['bvmgr_public_event_sidebar_active_indexes']
            : array();
        $stack[] = $index;
        $GLOBALS['bvmgr_public_event_sidebar_active_indexes'] = $stack;
    }
}

if (!function_exists('bvmgr_public_event_sidebar_track_context_after')) {
    function bvmgr_public_event_sidebar_track_context_after($index, $has_widgets): void
    {
        unset($has_widgets);

        $index = sanitize_key((string) $index);
        if ($index === '') {
            return;
        }

        $stack = isset($GLOBALS['bvmgr_public_event_sidebar_active_indexes']) && is_array($GLOBALS['bvmgr_public_event_sidebar_active_indexes'])
            ? $GLOBALS['bvmgr_public_event_sidebar_active_indexes']
            : array();
        for ($offset = count($stack) - 1; $offset >= 0; $offset--) {
            if (($stack[$offset] ?? '') !== $index) {
                continue;
            }

            array_splice($stack, $offset, 1);
            break;
        }

        $GLOBALS['bvmgr_public_event_sidebar_active_indexes'] = $stack;
    }
}

if (!function_exists('bvmgr_public_event_sidebar_current_index')) {
    function bvmgr_public_event_sidebar_current_index(): string
    {
        $stack = isset($GLOBALS['bvmgr_public_event_sidebar_active_indexes']) && is_array($GLOBALS['bvmgr_public_event_sidebar_active_indexes'])
            ? $GLOBALS['bvmgr_public_event_sidebar_active_indexes']
            : array();
        if (empty($stack)) {
            return '';
        }

        return sanitize_key((string) end($stack));
    }
}

if (!function_exists('bvmgr_public_event_sidebar_is_rendering_target')) {
    function bvmgr_public_event_sidebar_is_rendering_target(int $event_id): bool
    {
        $event_id = absint($event_id);
        if ($event_id <= 0) {
            return false;
        }

        $index = bvmgr_public_event_sidebar_current_index();
        return $index !== '' && in_array($index, bvmgr_public_event_sidebar_targets($event_id), true);
    }
}

if (!function_exists('bvmgr_public_event_sidebar_wrap_module')) {
    function bvmgr_public_event_sidebar_wrap_module(string $markup, string $slug): string
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

if (!function_exists('bvmgr_public_event_sidebar_render_stack')) {
    function bvmgr_public_event_sidebar_render_stack(int $event_id): string
    {
        $event_id = absint($event_id);
        if ($event_id <= 0 || get_post_type($event_id) !== 'tribe_events') {
            return '';
        }

        $modules = array();

        $details_rendered = function_exists('bvmgr_event_details_sidebar_rendered') && bvmgr_event_details_sidebar_rendered($event_id);
        $details_manual = function_exists('bvmgr_event_details_sidebar_manual_rendered') && bvmgr_event_details_sidebar_manual_rendered($event_id);
        if (!$details_rendered && !$details_manual) {
            $details_markup = function_exists('bvmgr_event_details_render_card')
                ? (string) bvmgr_event_details_render_card($event_id, true, '', 'sidebar')
                : '';
            if ($details_markup !== '') {
                bvmgr_event_details_mark_sidebar_rendered($event_id);
                $modules[] = bvmgr_public_event_sidebar_wrap_module($details_markup, 'event-details');
            }
        }

        $vendor_markup = function_exists('bvmgr_vendor_profiles_render_event_vendor_sidebar')
            ? (string) bvmgr_vendor_profiles_render_event_vendor_sidebar($event_id)
            : '';
        if ($vendor_markup !== '') {
            $modules[] = bvmgr_public_event_sidebar_wrap_module($vendor_markup, 'vendor-groups');
        }

        if (empty($modules)) {
            return '';
        }

        return '<div class="vms-event-sidebar-stack" data-vms-event-sidebar-stack="1">' . implode('', $modules) . '</div>';
    }
}

if (!function_exists('bvmgr_public_event_sidebar_render_before_widgets')) {
    function bvmgr_public_event_sidebar_render_before_widgets($index, $has_widgets): void
    {
        if (is_admin() || !function_exists('is_singular') || !is_singular('tribe_events')) {
            return;
        }

        $event_id = (int) get_queried_object_id();
        if ($event_id <= 0 || get_post_type($event_id) !== 'tribe_events') {
            return;
        }

        $index = sanitize_key((string) $index);
        if ($index === '' || !in_array($index, bvmgr_public_event_sidebar_targets($event_id), true)) {
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

        $markup = bvmgr_public_event_sidebar_render_stack($event_id);
        if ($markup === '') {
            return;
        }

        $rendered[$render_key] = true;
        echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
}
