<?php
defined('ABSPATH') || exit;

/**
 * VMS Square Sync Firewall.
 *
 * VMS/TEC admission products must remain Woo/VMS-owned even when Square is used
 * as the payment processor or as the catalog owner for normal bar/menu/merch
 * items. This file intentionally protects only ticket/admission/add-on products;
 * reusable Square-owned catalog items can still be used in event contexts.
 */

if (!function_exists('vms_square_firewall_square_meta_keys')) {
    /**
     * Square product-link metadata used by WooCommerce Square across recent builds.
     *
     * @return string[]
     */
    function vms_square_firewall_square_meta_keys(): array
    {
        return array(
            '_wc_square_synced',
            '_square_item_id',
            '_square_item_image_id',
            '_square_item_variation_id',
            '_square_item_version',
            '_square_item_variation_version',
            '_square_uploaded_image_id',
        );
    }
}

if (!function_exists('vms_square_firewall_protected_category_slugs')) {
    /**
     * @return string[]
     */
    function vms_square_firewall_protected_category_slugs(): array
    {
        $slugs = array('online-ticket', 'online-addon', 'tickets');
        return array_values(array_unique(array_filter(array_map('sanitize_title', (array) apply_filters('vms_square_firewall_protected_category_slugs', $slugs)))));
    }
}

if (!function_exists('vms_square_firewall_product_meta_key')) {
    function vms_square_firewall_product_meta_key(string $which): string
    {
        if (function_exists('vms_ticketing_v2_product_meta_key')) {
            $key = vms_ticketing_v2_product_meta_key($which);
            if (is_string($key) && $key !== '') {
                return $key;
            }
        }

        switch ($which) {
            case 'event_plan_id':
                return '_vms_event_plan_id';
            case 'tec_event_id':
                return '_vms_tec_event_id';
            case 'product_role':
                return '_vms_product_role';
            case 'ticketing_entitlement_id':
                return '_vms_ticketing_entitlement_id';
            case 'ticketing_marker_version':
                return '_vms_ticketing_marker_version';
            case 'ticketing_source_provider':
                return '_vms_ticketing_source_provider';
            default:
                return '';
        }
    }
}

if (!function_exists('vms_square_firewall_product_id_from_value')) {
    /**
     * @param mixed $value
     */
    function vms_square_firewall_product_id_from_value($value): int
    {
        if (is_numeric($value)) {
            return absint($value);
        }

        if (is_object($value)) {
            if (method_exists($value, 'get_id')) {
                return absint($value->get_id());
            }
            if (isset($value->ID)) {
                return absint($value->ID);
            }
            if (isset($value->id)) {
                return absint($value->id);
            }
            if (isset($value->product_id)) {
                return absint($value->product_id);
            }
        }

        if (is_array($value)) {
            foreach (array('product_id', 'id', 'ID') as $key) {
                if (isset($value[$key])) {
                    return absint($value[$key]);
                }
            }
        }

        return 0;
    }
}

if (!function_exists('vms_square_firewall_extract_product_id')) {
    /**
     * @param mixed ...$values
     */
    function vms_square_firewall_extract_product_id(...$values): int
    {
        foreach ($values as $value) {
            $id = vms_square_firewall_product_id_from_value($value);
            if ($id > 0) {
                return $id;
            }
        }
        return 0;
    }
}

if (!function_exists('vms_square_firewall_product_category_slugs')) {
    /**
     * @return string[]
     */
    function vms_square_firewall_product_category_slugs(int $product_id): array
    {
        $product_id = absint($product_id);
        if ($product_id <= 0 || !taxonomy_exists('product_cat')) {
            return array();
        }

        $ids = array($product_id);
        $parent_id = wp_get_post_parent_id($product_id);
        if ($parent_id > 0) {
            $ids[] = absint($parent_id);
        }

        $slugs = array();
        foreach (array_values(array_unique($ids)) as $id) {
            $terms = wp_get_object_terms($id, 'product_cat', array('fields' => 'slugs'));
            if (is_wp_error($terms) || !is_array($terms)) {
                continue;
            }
            foreach ($terms as $slug) {
                $slug = sanitize_title((string) $slug);
                if ($slug !== '') {
                    $slugs[] = $slug;
                }
            }
        }

        return array_values(array_unique($slugs));
    }
}

if (!function_exists('vms_square_firewall_get_sku')) {
    function vms_square_firewall_get_sku(int $product_id): string
    {
        $product_id = absint($product_id);
        if ($product_id <= 0) {
            return '';
        }

        if (function_exists('wc_get_product')) {
            try {
                $product = wc_get_product($product_id);
                if (is_object($product) && method_exists($product, 'get_sku')) {
                    return trim((string) $product->get_sku());
                }
            } catch (Throwable $e) {
                // Fall through to direct meta lookup.
            }
        }

        return trim((string) get_post_meta($product_id, '_sku', true));
    }
}

if (!function_exists('vms_square_firewall_is_protected_sku')) {
    function vms_square_firewall_is_protected_sku(string $sku): bool
    {
        $sku = trim($sku);
        if ($sku === '') {
            return false;
        }

        if (preg_match('/^VMS[-_]/i', $sku)) {
            return true;
        }

        return (bool) apply_filters('vms_square_firewall_is_protected_sku', false, $sku);
    }
}

if (!function_exists('vms_square_firewall_is_protected_role')) {
    function vms_square_firewall_is_protected_role(string $role): bool
    {
        $role = sanitize_key($role);
        if ($role === '') {
            return false;
        }

        $roles = array(
            'addon',
            'admission',
            'entitlement',
            'ga_ticket',
            'legacy_ticket',
            'pass',
            'rsvp',
            'ticket',
        );

        return in_array($role, (array) apply_filters('vms_square_firewall_protected_roles', $roles), true);
    }
}

if (!function_exists('vms_square_firewall_classify_product')) {
    /**
     * Returns a human-readable protection reason or an empty string when not protected.
     */
    function vms_square_firewall_classify_product(int $product_id): string
    {
        $product_id = absint($product_id);
        if ($product_id <= 0) {
            return '';
        }

        $post_type = get_post_type($product_id);
        if (!in_array($post_type, array('product', 'product_variation'), true)) {
            return '';
        }

        $sku = vms_square_firewall_get_sku($product_id);
        if (vms_square_firewall_is_protected_sku($sku)) {
            return 'vms_sku';
        }

        $role_key = vms_square_firewall_product_meta_key('product_role');
        $role = $role_key !== '' ? sanitize_key((string) get_post_meta($product_id, $role_key, true)) : '';
        if (vms_square_firewall_is_protected_role($role)) {
            return 'vms_product_role_' . $role;
        }

        $ent_key = vms_square_firewall_product_meta_key('ticketing_entitlement_id');
        if ($ent_key !== '' && sanitize_key((string) get_post_meta($product_id, $ent_key, true)) !== '') {
            return 'vms_ticketing_entitlement';
        }

        $category_slugs = vms_square_firewall_product_category_slugs($product_id);
        $protected_slugs = vms_square_firewall_protected_category_slugs();
        $matched_categories = array_values(array_intersect($category_slugs, $protected_slugs));
        if (!empty($matched_categories)) {
            return 'protected_category_' . implode('_', $matched_categories);
        }

        if (absint(get_post_meta($product_id, '_tribe_wooticket_for_event', true)) > 0) {
            return 'tec_woo_ticket_event_link';
        }
        if (absint(get_post_meta($product_id, '_tribe_ticket_capacity', true)) > 0 && absint(get_post_meta($product_id, '_tribe_wooticket_for_event', true)) > 0) {
            return 'tec_ticket_capacity';
        }

        $marker_key = vms_square_firewall_product_meta_key('ticketing_marker_version');
        $provider_key = vms_square_firewall_product_meta_key('ticketing_source_provider');
        $plan_key = vms_square_firewall_product_meta_key('event_plan_id');
        $has_vms_ticketing_marker = ($marker_key !== '' && get_post_meta($product_id, $marker_key, true) !== '')
            || ($provider_key !== '' && get_post_meta($product_id, $provider_key, true) !== '');
        if ($has_vms_ticketing_marker && $plan_key !== '' && absint(get_post_meta($product_id, $plan_key, true)) > 0) {
            return 'vms_ticketing_marker';
        }

        return (string) apply_filters('vms_square_firewall_classify_product', '', $product_id, array(
            'sku' => $sku,
            'role' => $role,
            'category_slugs' => $category_slugs,
        ));
    }
}

if (!function_exists('vms_square_firewall_is_protected_product')) {
    function vms_square_firewall_is_protected_product(int $product_id): bool
    {
        return vms_square_firewall_classify_product($product_id) !== '';
    }
}

if (!function_exists('vms_square_firewall_has_square_link')) {
    function vms_square_firewall_has_square_link(int $product_id): bool
    {
        $product_id = absint($product_id);
        if ($product_id <= 0) {
            return false;
        }

        foreach (vms_square_firewall_square_meta_keys() as $meta_key) {
            $value = get_post_meta($product_id, $meta_key, true);
            if ($value !== '' && $value !== array() && $value !== null) {
                if ($meta_key === '_wc_square_synced' && strtolower((string) $value) === 'no') {
                    continue;
                }
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('vms_square_firewall_set_square_sync_no')) {
    function vms_square_firewall_set_square_sync_no(int $product_id): bool
    {
        $product_id = absint($product_id);
        if ($product_id <= 0) {
            return false;
        }

        $changed = false;

        if (function_exists('wc_get_product') && class_exists('\WooCommerce\Square\Handlers\Product')) {
            try {
                $product = wc_get_product($product_id);
                if ($product instanceof WC_Product && \WooCommerce\Square\Handlers\Product::can_sync_with_square($product)) {
                    \WooCommerce\Square\Handlers\Product::set_synced_with_square($product, 'no');
                    $changed = true;
                }
            } catch (Throwable $e) {
                // Direct meta fallback below keeps the firewall effective.
            }
        }

        if ((string) get_post_meta($product_id, '_wc_square_synced', true) !== 'no') {
            update_post_meta($product_id, '_wc_square_synced', 'no');
            $changed = true;
        }

        return $changed;
    }
}

if (!function_exists('vms_square_firewall_protect_product')) {
    /**
     * @return array<string,mixed>
     */
    function vms_square_firewall_protect_product(int $product_id, bool $clear_square_meta = true): array
    {
        $product_id = absint($product_id);
        $reason = vms_square_firewall_classify_product($product_id);
        $result = array(
            'product_id' => $product_id,
            'protected' => false,
            'reason' => $reason,
            'sync_changed' => false,
            'meta_cleared' => 0,
        );

        if ($product_id <= 0 || $reason === '') {
            return $result;
        }

        $result['protected'] = true;
        $result['sync_changed'] = vms_square_firewall_set_square_sync_no($product_id);

        if ($clear_square_meta) {
            foreach (vms_square_firewall_square_meta_keys() as $meta_key) {
                if ($meta_key === '_wc_square_synced') {
                    continue;
                }
                if (metadata_exists('post', $product_id, $meta_key)) {
                    delete_post_meta($product_id, $meta_key);
                    $result['meta_cleared']++;
                }
            }
        }

        update_post_meta($product_id, '_vms_square_sync_protected', current_time('mysql'));
        update_post_meta($product_id, '_vms_square_sync_protection_reason', $reason);

        return $result;
    }
}

if (!function_exists('vms_square_firewall_protect_product_and_children')) {
    /**
     * @return array<int,array<string,mixed>>
     */
    function vms_square_firewall_protect_product_and_children(int $product_id, bool $clear_square_meta = true): array
    {
        $product_id = absint($product_id);
        if ($product_id <= 0) {
            return array();
        }

        $ids = array($product_id);
        $children = get_children(array(
            'post_parent' => $product_id,
            'post_type' => 'product_variation',
            'post_status' => array('publish', 'private'),
            'fields' => 'ids',
            'numberposts' => -1,
        ));
        if (is_array($children)) {
            foreach ($children as $child_id) {
                $ids[] = absint($child_id);
            }
        }

        $results = array();
        foreach (array_values(array_unique(array_filter($ids))) as $id) {
            $results[$id] = vms_square_firewall_protect_product($id, $clear_square_meta);
        }

        return $results;
    }
}

if (!function_exists('vms_square_firewall_on_product_save')) {
    /**
     * @param mixed $post_or_product
     */
    function vms_square_firewall_on_product_save($product_id, $post_or_product = null, $update = null): void
    {
        unset($post_or_product, $update);
        $product_id = absint($product_id);
        if ($product_id <= 0 || wp_is_post_autosave($product_id) || wp_is_post_revision($product_id)) {
            return;
        }

        if (vms_square_firewall_is_protected_product($product_id)) {
            vms_square_firewall_protect_product_and_children($product_id, true);
        }
    }
}
add_action('save_post_product', 'vms_square_firewall_on_product_save', 99, 3);
add_action('woocommerce_update_product', 'vms_square_firewall_on_product_save', 99, 1);

if (!function_exists('vms_square_firewall_on_marker_meta_change')) {
    /**
     * @param mixed $meta_id
     * @param mixed $meta_value
     */
    function vms_square_firewall_on_marker_meta_change($meta_id, int $object_id, string $meta_key, $meta_value = null): void
    {
        unset($meta_id, $meta_value);
        $object_id = absint($object_id);
        if ($object_id <= 0 || get_post_type($object_id) !== 'product') {
            return;
        }

        $watched = array_filter(array(
            vms_square_firewall_product_meta_key('product_role'),
            vms_square_firewall_product_meta_key('ticketing_entitlement_id'),
            vms_square_firewall_product_meta_key('ticketing_marker_version'),
            vms_square_firewall_product_meta_key('ticketing_source_provider'),
            '_tribe_wooticket_for_event',
            '_sku',
        ));

        if (!in_array($meta_key, $watched, true)) {
            return;
        }

        if (vms_square_firewall_is_protected_product($object_id)) {
            vms_square_firewall_protect_product_and_children($object_id, true);
        }
    }
}
add_action('added_post_meta', 'vms_square_firewall_on_marker_meta_change', 99, 4);
add_action('updated_post_meta', 'vms_square_firewall_on_marker_meta_change', 99, 4);

if (!function_exists('vms_square_firewall_filter_should_sync')) {
    /**
     * Generic guard for Square/Woo product-level sync filters across plugin versions.
     *
     * @param mixed $should_sync
     * @param mixed ...$args
     * @return mixed
     */
    function vms_square_firewall_filter_should_sync($should_sync, ...$args)
    {
        $product_id = vms_square_firewall_extract_product_id($should_sync, ...$args);
        if ($product_id > 0 && vms_square_firewall_is_protected_product($product_id)) {
            vms_square_firewall_protect_product($product_id, true);
            return false;
        }

        return $should_sync;
    }
}

foreach (array(
    'wc_square_should_sync_product',
    'wc_square_product_should_sync',
    'wc_square_product_syncable',
    'woocommerce_square_should_sync_product',
    'woocommerce_square_product_should_sync',
    'woocommerce_square_product_is_syncable',
) as $bvmgr_square_firewall_filter_name) {
    add_filter($bvmgr_square_firewall_filter_name, 'vms_square_firewall_filter_should_sync', 99, 10);
}
unset($bvmgr_square_firewall_filter_name);

if (!function_exists('vms_square_firewall_query_product_ids')) {
    /**
     * @return int[]
     */
    function vms_square_firewall_query_product_ids(int $after_id = 0, int $limit = 250): array
    {
        global $wpdb;

        $after_id = max(0, absint($after_id));
        $limit = max(1, min(1000, absint($limit)));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Incremental firewall enforcement reads the current product table directly with an ID cursor and a batch limit clamped to 1-1000.
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_type IN ('product', 'product_variation')
               AND post_status NOT IN ('trash', 'auto-draft', 'inherit')
               AND ID > %d
             ORDER BY ID ASC
             LIMIT %d",
            $after_id,
            $limit
        ));

        if (!is_array($ids)) {
            return array();
        }

        return array_values(array_filter(array_map('absint', $ids)));
    }
}

if (!function_exists('vms_square_firewall_scan_products')) {
    /**
     * @return array<string,mixed>
     */
    function vms_square_firewall_scan_products(bool $repair = false, int $limit = 5000): array
    {
        $limit = max(1, min(20000, absint($limit)));
        $checked = 0;
        $after_id = 0;
        $rows = array();
        $summary = array(
            'ts' => time(),
            'mode' => $repair ? 'repair' : 'scan',
            'checked' => 0,
            'protected_candidates' => 0,
            'already_safe' => 0,
            'had_square_links' => 0,
            'sync_yes' => 0,
            'meta_cleared' => 0,
            'repaired' => 0,
            'skipped' => 0,
            'rows' => array(),
        );

        while ($checked < $limit) {
            $batch = vms_square_firewall_query_product_ids($after_id, min(250, $limit - $checked));
            if (empty($batch)) {
                break;
            }

            foreach ($batch as $product_id) {
                $after_id = max($after_id, $product_id);
                $checked++;
                $summary['checked']++;

                $reason = vms_square_firewall_classify_product($product_id);
                if ($reason === '') {
                    $summary['skipped']++;
                    continue;
                }

                $summary['protected_candidates']++;
                $sku = vms_square_firewall_get_sku($product_id);
                $name = get_the_title($product_id);
                $sync_value = (string) get_post_meta($product_id, '_wc_square_synced', true);
                $has_link = vms_square_firewall_has_square_link($product_id);
                if ($has_link) {
                    $summary['had_square_links']++;
                }
                if (strtolower($sync_value) === 'yes') {
                    $summary['sync_yes']++;
                }

                $repair_result = null;
                if ($repair) {
                    $repair_result = vms_square_firewall_protect_product($product_id, true);
                    $cleared = (int) ($repair_result['meta_cleared'] ?? 0);
                    $summary['meta_cleared'] += $cleared;
                    if ($cleared > 0 || !empty($repair_result['sync_changed']) || $has_link || strtolower($sync_value) === 'yes') {
                        $summary['repaired']++;
                    }
                } elseif (!$has_link && strtolower($sync_value) !== 'yes') {
                    $summary['already_safe']++;
                }

                if (count($rows) < 200) {
                    $rows[] = array(
                        'product_id' => $product_id,
                        'name' => is_string($name) ? $name : '',
                        'sku' => $sku,
                        'reason' => $reason,
                        'sync_value' => $sync_value,
                        'had_square_link' => $has_link ? 1 : 0,
                        'meta_cleared' => $repair_result ? (int) ($repair_result['meta_cleared'] ?? 0) : 0,
                    );
                }
            }

            if (count($batch) < 250) {
                break;
            }
        }

        $summary['rows'] = $rows;
        return $summary;
    }
}

if (!function_exists('vms_square_firewall_auto_backfill_once')) {
    function vms_square_firewall_auto_backfill_once(): void
    {
        if (!is_admin()) {
            return;
        }

        $version = '2026-04-30-1';
        $version_option = 'vms_square_firewall_backfill_version';
        if ((string) get_option($version_option, '') === $version) {
            return;
        }
        $guard = function_exists('vms_admin_guard_begin')
            ? vms_admin_guard_begin('admin_init.square_firewall_backfill', array(
                'task' => 'square_firewall_backfill',
                'allow_action' => 'square_firewall_backfill',
                'lock_name' => 'square_firewall_backfill',
                'lock_ttl' => 120,
            ))
            : true;
        if ($guard === false) {
            return;
        }
        $guard_context = array(
            'cursor' => 0,
            'products_processed' => 0,
        );

        try {
            $cursor_option = 'vms_square_firewall_backfill_cursor';
            $cursor = absint(get_option($cursor_option, 0));
            $guard_context['cursor'] = $cursor;
            $ids = vms_square_firewall_query_product_ids($cursor, 100);
            if (empty($ids)) {
                update_option($version_option, $version, false);
                delete_option($cursor_option);
                return;
            }

            foreach ($ids as $product_id) {
                if (vms_square_firewall_is_protected_product($product_id)) {
                    vms_square_firewall_protect_product($product_id, true);
                }
                $cursor = max($cursor, absint($product_id));
                $guard_context['products_processed']++;
            }

            update_option($cursor_option, $cursor, false);
        } catch (Throwable $e) {
            // Best effort only. The explicit admin tool can be run if this stalls.
        } finally {
            if (is_array($guard) && function_exists('vms_admin_guard_finish')) {
                vms_admin_guard_finish($guard, $guard_context);
            }
        }
    }
}
add_action('admin_init', 'vms_square_firewall_auto_backfill_once', 57);
