<?php
defined('ABSPATH') || exit;

if (!function_exists('vmseb_get_linked_tec_event_id_for_cache')) {
    function vmseb_get_linked_tec_event_id_for_cache(int $event_plan_id): int
    {
        $event_plan_id = absint($event_plan_id);
        if ($event_plan_id <= 0) {
            return 0;
        }

        foreach (array('bvmgr_get_plan_tec_event_id', 'vms_get_plan_tec_event_id') as $resolver) {
            if (!function_exists($resolver)) {
                continue;
            }
            $tec_event_id = absint($resolver($event_plan_id));
            if ($tec_event_id > 0) {
                return $tec_event_id;
            }
        }

        return absint(get_post_meta($event_plan_id, '_vms_tec_event_id', true));
    }
}

if (!function_exists('vmseb_invalidate_public_cache_post_ids')) {
    function vmseb_invalidate_public_cache_post_ids(array $post_ids): array
    {
        $post_ids = array_values(array_unique(array_filter(array_map('absint', $post_ids))));
        $has_exact_wp_super_cache_purge = function_exists('get_current_url_supercache_dir')
            && function_exists('prune_super_cache');
        if (!$has_exact_wp_super_cache_purge && !function_exists('clean_post_cache')) {
            return array();
        }

        foreach ($post_ids as $post_id) {
            if ($has_exact_wp_super_cache_purge) {
                $cache_dir = get_current_url_supercache_dir($post_id);
                if (is_string($cache_dir) && $cache_dir !== '') {
                    // Force-delete the exact page directory. WP Super Cache's normal
                    // post-edit path can retain a stale .needs-rebuild response when
                    // cache_rebuild_files is enabled, which is precisely what this
                    // integration must avoid after a visibility/configuration change.
                    prune_super_cache($cache_dir, true);
                }
                continue;
            }
            clean_post_cache($post_id);
        }

        do_action('vmseb_public_cache_invalidated', $post_ids);
        return $post_ids;
    }
}

if (!function_exists('vmseb_public_cache_post_ids_for_event_plan')) {
    function vmseb_public_cache_post_ids_for_event_plan(int $event_plan_id, bool $include_public_page = true): array
    {
        $event_plan_id = absint($event_plan_id);
        if ($event_plan_id <= 0) {
            return array();
        }

        $post_ids = array($event_plan_id, vmseb_get_linked_tec_event_id_for_cache($event_plan_id));
        if ($include_public_page && function_exists('vmseb_find_public_page_id')) {
            $post_ids[] = vmseb_find_public_page_id(false);
        }

        return array_values(array_unique(array_filter(array_map('absint', $post_ids))));
    }
}

if (!function_exists('vmseb_invalidate_event_plan_public_cache')) {
    function vmseb_invalidate_event_plan_public_cache(int $event_plan_id): array
    {
        return vmseb_invalidate_public_cache_post_ids(
            vmseb_public_cache_post_ids_for_event_plan($event_plan_id, true)
        );
    }
}

if (!function_exists('vmseb_invalidate_dedicated_public_cache')) {
    function vmseb_invalidate_dedicated_public_cache(): array
    {
        if (!function_exists('vmseb_find_public_page_id')) {
            return array();
        }

        return vmseb_invalidate_public_cache_post_ids(array(vmseb_find_public_page_id(false)));
    }
}

if (!function_exists('vmseb_cache_sensitive_event_plan_ids')) {
    function vmseb_cache_sensitive_event_plan_ids(): array
    {
        $event_plan_ids = get_posts(array(
            'post_type'        => 'vms_event_plan',
            'post_status'      => 'any',
            'posts_per_page'   => 1000,
            'fields'           => 'ids',
            'meta_key'         => '_vms_express_bar_enabled',
            'meta_value'       => '1',
            'orderby'          => 'ID',
            'order'            => 'ASC',
            'no_found_rows'    => true,
            'suppress_filters' => true,
        ));

        $eligible = array();
        foreach ($event_plan_ids as $event_plan_id) {
            $event_plan_id = absint($event_plan_id);
            if ($event_plan_id <= 0) {
                continue;
            }
            if ((string) get_post_meta($event_plan_id, '_vms_express_bar_enabled', true) !== '1') {
                continue;
            }
            if ((string) get_post_meta($event_plan_id, '_vmseb_auto_embed', true) === '0') {
                continue;
            }
            $eligible[] = $event_plan_id;
        }

        return $eligible;
    }
}

if (!function_exists('vmseb_maybe_invalidate_public_cache_on_version_change')) {
    function vmseb_maybe_invalidate_public_cache_on_version_change(): void
    {
        if (!defined('VMSEB_VERSION')) {
            return;
        }

        $option_name = 'vmseb_public_cache_version';
        if ((string) get_option($option_name, '') === (string) VMSEB_VERSION) {
            return;
        }

        $post_ids = array();
        foreach (vmseb_cache_sensitive_event_plan_ids() as $event_plan_id) {
            $post_ids = array_merge(
                $post_ids,
                vmseb_public_cache_post_ids_for_event_plan((int) $event_plan_id, false)
            );
        }
        if (function_exists('vmseb_find_public_page_id')) {
            $post_ids[] = vmseb_find_public_page_id(false);
        }

        vmseb_invalidate_public_cache_post_ids($post_ids);
        update_option($option_name, (string) VMSEB_VERSION, false);
    }
}
