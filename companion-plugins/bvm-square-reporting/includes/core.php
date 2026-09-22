<?php
defined('ABSPATH') || exit;

/**
 * BVM Square Reporting
 *
 * Design goals:
 * - Keep event-specific tickets/add-ons/rentals out of the normal Square catalog sync.
 * - Reuse four stable Square catalog variations strictly for reporting identity.
 * - Stamp only Woo order items, never the source Woo product.
 * - Preserve WooCommerce as the source of order price, quantity, tax, and event detail.
 */

add_action('woocommerce_checkout_create_order_line_item', 'bvm_sqr_stamp_checkout_line_item', 100, 4);
add_action('woocommerce_new_order_item', 'bvm_sqr_stamp_new_order_item', 100, 3);
add_action('woocommerce_checkout_order_created', 'bvm_sqr_stamp_created_order_items', 200, 1);
add_action('wc_square_credit_card_api_request_performed', 'bvm_sqr_restore_square_line_names_after_create', 20, 3);

if (!function_exists('bvm_sqr_classes')) {
    function bvm_sqr_classes(): array
    {
        return array(
            'online_ticket' => array(
                'label' => 'ONLINE TICKET',
                'sku' => 'BVM-REPORT-ONLINE-TICKET',
                'aliases' => array('ONLINE TICKET', 'ONLINE TICKETS', 'Online Ticket', 'Online Tickets'),
            ),
            'online_addon' => array(
                'label' => 'ONLINE ADDON',
                'sku' => 'BVM-REPORT-ONLINE-ADDON',
                'aliases' => array('ONLINE ADDON', 'ONLINE ADDONS', 'Online Addon', 'Online Add-on', 'Online Add-ons'),
            ),
            'rental' => array(
                'label' => 'RENTAL',
                'sku' => 'BVM-REPORT-RENTAL',
                'aliases' => array('RENTAL', 'RENTALS', 'Rental', 'Rentals'),
            ),
            'online_tips' => array(
                'label' => 'ONLINE TIPS',
                'sku' => 'BVM-REPORT-ONLINE-TIPS',
                'aliases' => array('ONLINE TIPS', 'ONLINE TIP', 'Online Tips', 'Online Tip'),
            ),
        );
    }
}

if (!function_exists('bvm_sqr_environment')) {
    function bvm_sqr_environment(): string
    {
        $settings = get_option('wc_square_settings', array());
        if (is_array($settings) && isset($settings['enable_sandbox'])) {
            return ((string) $settings['enable_sandbox'] === 'yes') ? 'sandbox' : 'production';
        }

        if (function_exists('wc_square')) {
            try {
                $plugin = wc_square();
                $handler = is_object($plugin) && method_exists($plugin, 'get_settings_handler') ? $plugin->get_settings_handler() : null;
                if (is_object($handler) && method_exists($handler, 'is_sandbox')) {
                    return $handler->is_sandbox() ? 'sandbox' : 'production';
                }
            } catch (Throwable $e) {
                // Fall through.
            }
        }

        return 'production';
    }
}

if (!function_exists('bvm_sqr_get_objects')) {
    function bvm_sqr_get_objects(?string $environment = null): array
    {
        $environment = $environment ?: bvm_sqr_environment();
        $all = get_option('bvm_square_reporting_objects', array());
        if (!is_array($all)) {
            $all = array();
        }
        $objects = isset($all[$environment]) && is_array($all[$environment]) ? $all[$environment] : array();
        return $objects;
    }
}

if (!function_exists('bvm_sqr_store_objects')) {
    function bvm_sqr_store_objects(array $objects, ?string $environment = null): void
    {
        $environment = $environment ?: bvm_sqr_environment();
        $all = get_option('bvm_square_reporting_objects', array());
        if (!is_array($all)) {
            $all = array();
        }
        $all[$environment] = $objects;
        update_option('bvm_square_reporting_objects', $all, false);
    }
}

if (!function_exists('bvm_sqr_square_context')) {
    function bvm_sqr_square_context(): array
    {
        $context = array(
            'ok' => false,
            'environment' => bvm_sqr_environment(),
            'location_id' => '',
            'api' => null,
            'error' => '',
        );

        if (!function_exists('wc_square')) {
            $context['error'] = 'WooCommerce Square is not loaded.';
            return $context;
        }

        try {
            $plugin = wc_square();
            $settings = is_object($plugin) && method_exists($plugin, 'get_settings_handler') ? $plugin->get_settings_handler() : null;
            if (!is_object($settings)) {
                $context['error'] = 'WooCommerce Square settings are unavailable.';
                return $context;
            }

            if (method_exists($settings, 'is_connected') && !$settings->is_connected()) {
                $context['error'] = 'WooCommerce Square is not connected.';
                return $context;
            }

            $context['environment'] = method_exists($settings, 'is_sandbox') && $settings->is_sandbox() ? 'sandbox' : 'production';
            $context['location_id'] = method_exists($settings, 'get_location_id') ? trim((string) $settings->get_location_id()) : '';
            if ($context['location_id'] === '') {
                $context['error'] = 'WooCommerce Square has no active location.';
                return $context;
            }

            $context['api'] = method_exists($plugin, 'get_api') ? $plugin->get_api() : null;
            if (!is_object($context['api'])) {
                $context['error'] = 'WooCommerce Square API is unavailable.';
                return $context;
            }

            $context['ok'] = true;
            return $context;
        } catch (Throwable $e) {
            $context['error'] = sanitize_text_field($e->getMessage());
            return $context;
        }
    }
}

if (!function_exists('bvm_sqr_error_summary')) {
    function bvm_sqr_error_summary($response): string
    {
        $errors = array();
        if (is_object($response) && method_exists($response, 'get_errors')) {
            $errors = (array) $response->get_errors();
        }
        $data = is_object($response) && method_exists($response, 'get_data') ? $response->get_data() : null;
        if (empty($errors) && is_object($data) && method_exists($data, 'getErrors')) {
            $errors = (array) $data->getErrors();
        }
        if (empty($errors) && is_array($data) && !empty($data['errors']) && is_array($data['errors'])) {
            $errors = $data['errors'];
        }
        if (empty($errors)) {
            return '';
        }

        $first = reset($errors);
        if (is_object($first)) {
            $code = method_exists($first, 'getCode') ? (string) $first->getCode() : '';
            $detail = method_exists($first, 'getDetail') ? (string) $first->getDetail() : '';
            return trim($code . ($code !== '' && $detail !== '' ? ': ' : '') . $detail);
        }
        if (is_array($first)) {
            $code = (string) ($first['code'] ?? '');
            $detail = (string) ($first['detail'] ?? $first['message'] ?? '');
            return trim($code . ($code !== '' && $detail !== '' ? ': ' : '') . $detail);
        }
        return sanitize_text_field((string) $first);
    }
}

if (!function_exists('bvm_sqr_normalized_name')) {
    function bvm_sqr_normalized_name(string $name): string
    {
        $name = strtolower(trim(wp_strip_all_tags($name)));
        $name = preg_replace('/[^a-z0-9]+/', ' ', $name);
        return trim((string) $name);
    }
}

if (!function_exists('bvm_sqr_find_category')) {
    function bvm_sqr_find_category($api, array $definition): array
    {
        $targets = array();
        foreach ((array) ($definition['aliases'] ?? array()) as $alias) {
            $targets[] = bvm_sqr_normalized_name((string) $alias);
        }
        $targets[] = bvm_sqr_normalized_name((string) ($definition['label'] ?? ''));
        $targets = array_values(array_unique(array_filter($targets)));

        $cursor = '';
        do {
            try {
                $response = $api->list_catalog($cursor, array('CATEGORY'));
            } catch (Throwable $e) {
                return array('ok' => false, 'error' => sanitize_text_field($e->getMessage()));
            }
            $error = bvm_sqr_error_summary($response);
            if ($error !== '') {
                return array('ok' => false, 'error' => $error);
            }
            $data = is_object($response) && method_exists($response, 'get_data') ? $response->get_data() : null;
            $objects = is_object($data) && method_exists($data, 'getObjects') ? (array) $data->getObjects() : array();
            foreach ($objects as $object) {
                if (!is_object($object) || !method_exists($object, 'getType') || $object->getType() !== 'CATEGORY') {
                    continue;
                }
                $cat = method_exists($object, 'getCategoryData') ? $object->getCategoryData() : null;
                $name = is_object($cat) && method_exists($cat, 'getName') ? trim((string) $cat->getName()) : '';
                if ($name === '' || !in_array(bvm_sqr_normalized_name($name), $targets, true)) {
                    continue;
                }
                return array(
                    'ok' => true,
                    'found' => true,
                    'category_id' => trim((string) (method_exists($object, 'getId') ? $object->getId() : '')),
                    'category_version' => (int) (method_exists($object, 'getVersion') ? $object->getVersion() : 0),
                    'category_name' => $name,
                );
            }
            $cursor = is_object($data) && method_exists($data, 'getCursor') ? trim((string) $data->getCursor()) : '';
        } while ($cursor !== '');

        return array('ok' => true, 'found' => false);
    }
}

if (!function_exists('bvm_sqr_create_category')) {
    function bvm_sqr_create_category($api, string $key, array $definition): array
    {
        if (!class_exists('\\Square\\Models\\CatalogObject') || !class_exists('\\Square\\Models\\CatalogCategory')) {
            return array('ok' => false, 'error' => 'Square SDK catalog classes are unavailable.');
        }

        $object = new \Square\Models\CatalogObject('CATEGORY', '#bvm_sqr_category_' . sanitize_key($key));
        $data = new \Square\Models\CatalogCategory();
        $data->setName((string) $definition['label']);
        if (method_exists($data, 'setIsTopLevel')) {
            $data->setIsTopLevel(true);
        }
        $object->setCategoryData($data);

        try {
            $response = $api->upsert_catalog_object(wp_generate_uuid4(), $object);
        } catch (Throwable $e) {
            return array('ok' => false, 'error' => sanitize_text_field($e->getMessage()));
        }
        $error = bvm_sqr_error_summary($response);
        if ($error !== '') {
            return array('ok' => false, 'error' => $error);
        }
        $payload = is_object($response) && method_exists($response, 'get_data') ? $response->get_data() : null;
        $remote = is_object($payload) && method_exists($payload, 'getCatalogObject') ? $payload->getCatalogObject() : null;
        $id = is_object($remote) && method_exists($remote, 'getId') ? trim((string) $remote->getId()) : '';
        if ($id === '') {
            return array('ok' => false, 'error' => 'Square did not return a category ID.');
        }
        return array(
            'ok' => true,
            'found' => true,
            'created' => true,
            'category_id' => $id,
            'category_version' => (int) (method_exists($remote, 'getVersion') ? $remote->getVersion() : 0),
            'category_name' => (string) $definition['label'],
        );
    }
}

if (!function_exists('bvm_sqr_find_item_by_sku')) {
    function bvm_sqr_find_item_by_sku($api, string $sku): array
    {
        $cursor = '';
        do {
            try {
                $response = $api->list_catalog($cursor, array('ITEM'));
            } catch (Throwable $e) {
                return array('ok' => false, 'error' => sanitize_text_field($e->getMessage()));
            }
            $error = bvm_sqr_error_summary($response);
            if ($error !== '') {
                return array('ok' => false, 'error' => $error);
            }
            $data = is_object($response) && method_exists($response, 'get_data') ? $response->get_data() : null;
            $objects = is_object($data) && method_exists($data, 'getObjects') ? (array) $data->getObjects() : array();
            foreach ($objects as $object) {
                if (!is_object($object) || !method_exists($object, 'getType') || $object->getType() !== 'ITEM') {
                    continue;
                }
                $item_data = method_exists($object, 'getItemData') ? $object->getItemData() : null;
                $variations = is_object($item_data) && method_exists($item_data, 'getVariations') ? (array) $item_data->getVariations() : array();
                foreach ($variations as $variation) {
                    $variation_data = is_object($variation) && method_exists($variation, 'getItemVariationData') ? $variation->getItemVariationData() : null;
                    $remote_sku = is_object($variation_data) && method_exists($variation_data, 'getSku') ? trim((string) $variation_data->getSku()) : '';
                    if ($remote_sku !== $sku) {
                        continue;
                    }
                    return array(
                        'ok' => true,
                        'found' => true,
                        'item_id' => trim((string) (method_exists($object, 'getId') ? $object->getId() : '')),
                        'item_version' => (int) (method_exists($object, 'getVersion') ? $object->getVersion() : 0),
                        'variation_id' => trim((string) (method_exists($variation, 'getId') ? $variation->getId() : '')),
                        'variation_version' => (int) (method_exists($variation, 'getVersion') ? $variation->getVersion() : 0),
                    );
                }
            }
            $cursor = is_object($data) && method_exists($data, 'getCursor') ? trim((string) $data->getCursor()) : '';
        } while ($cursor !== '');

        return array('ok' => true, 'found' => false);
    }
}

if (!function_exists('bvm_sqr_create_item')) {
    function bvm_sqr_create_item($api, string $key, array $definition, string $category_id, string $location_id): array
    {
        if (!class_exists('\\Square\\Models\\CatalogObject') || !class_exists('\\Square\\Models\\CatalogItem') || !class_exists('\\Square\\Models\\CatalogItemVariation')) {
            return array('ok' => false, 'error' => 'Square SDK catalog classes are unavailable.');
        }

        $item_object = new \Square\Models\CatalogObject('ITEM', '#bvm_sqr_item_' . sanitize_key($key));
        $item_object->setPresentAtAllLocations(false);
        $item_object->setPresentAtLocationIds(array($location_id));
        $item_object->setAbsentAtLocationIds(array());

        $item_data = new \Square\Models\CatalogItem();
        $item_data->setName((string) $definition['label']);
        if (method_exists($item_data, 'setDescriptionHtml')) {
            $item_data->setDescriptionHtml('BVM reporting carrier. Source of truth: WooCommerce / Backstage Venue Manager. Do not use as a POS sale item.');
        }
        if (method_exists($item_data, 'setIsArchived')) {
            $item_data->setIsArchived(false);
        }
        if (method_exists($item_data, 'setAvailableOnline')) {
            $item_data->setAvailableOnline(false);
        }
        if (method_exists($item_data, 'setAvailableForPickup')) {
            $item_data->setAvailableForPickup(false);
        }
        if (method_exists($item_data, 'setAvailableElectronically')) {
            $item_data->setAvailableElectronically(false);
        }

        if ($category_id !== '' && class_exists('\\Square\\Models\\CatalogObjectCategory')) {
            $category_ref = new \Square\Models\CatalogObjectCategory();
            $category_ref->setId($category_id);
            if (method_exists($item_data, 'setCategories')) {
                $item_data->setCategories(array($category_ref));
            }
            if (method_exists($item_data, 'setReportingCategory')) {
                $item_data->setReportingCategory($category_ref);
            }
        }

        $variation_object = new \Square\Models\CatalogObject('ITEM_VARIATION', '#bvm_sqr_variation_' . sanitize_key($key));
        $variation_object->setPresentAtAllLocations(false);
        $variation_object->setPresentAtLocationIds(array($location_id));
        $variation_object->setAbsentAtLocationIds(array());

        $variation_data = new \Square\Models\CatalogItemVariation();
        $variation_data->setItemId($item_object->getId());
        $variation_data->setName((string) $definition['label']);
        $variation_data->setSku((string) $definition['sku']);
        if (class_exists('\\Square\\Models\\CatalogPricingType')) {
            $variation_data->setPricingType(\Square\Models\CatalogPricingType::VARIABLE_PRICING);
        } else {
            $variation_data->setPricingType('VARIABLE_PRICING');
        }
        $variation_data->setTrackInventory(false);
        $variation_object->setItemVariationData($variation_data);
        $item_data->setVariations(array($variation_object));
        $item_object->setItemData($item_data);

        try {
            $response = $api->upsert_catalog_object(wp_generate_uuid4(), $item_object);
        } catch (Throwable $e) {
            return array('ok' => false, 'error' => sanitize_text_field($e->getMessage()));
        }
        $error = bvm_sqr_error_summary($response);
        if ($error !== '') {
            return array('ok' => false, 'error' => $error);
        }
        $payload = is_object($response) && method_exists($response, 'get_data') ? $response->get_data() : null;
        $remote = is_object($payload) && method_exists($payload, 'getCatalogObject') ? $payload->getCatalogObject() : null;
        $item_id = is_object($remote) && method_exists($remote, 'getId') ? trim((string) $remote->getId()) : '';
        $remote_data = is_object($remote) && method_exists($remote, 'getItemData') ? $remote->getItemData() : null;
        $variations = is_object($remote_data) && method_exists($remote_data, 'getVariations') ? (array) $remote_data->getVariations() : array();
        $variation = !empty($variations[0]) && is_object($variations[0]) ? $variations[0] : null;
        $variation_id = is_object($variation) && method_exists($variation, 'getId') ? trim((string) $variation->getId()) : '';
        if ($item_id === '' || $variation_id === '') {
            return array('ok' => false, 'error' => 'Square did not return the reporting item and variation IDs.');
        }

        return array(
            'ok' => true,
            'found' => true,
            'created' => true,
            'item_id' => $item_id,
            'item_version' => (int) (method_exists($remote, 'getVersion') ? $remote->getVersion() : 0),
            'variation_id' => $variation_id,
            'variation_version' => (int) (method_exists($variation, 'getVersion') ? $variation->getVersion() : 0),
        );
    }
}

if (!function_exists('bvm_sqr_ensure_objects')) {
    function bvm_sqr_ensure_objects(): array
    {
        $context = bvm_sqr_square_context();
        if (empty($context['ok'])) {
            return array('ok' => false, 'error' => (string) ($context['error'] ?? 'Square is unavailable.'));
        }

        $environment = (string) $context['environment'];
        $objects = bvm_sqr_get_objects($environment);
        $results = array();

        foreach (bvm_sqr_classes() as $key => $definition) {
            $category = bvm_sqr_find_category($context['api'], $definition);
            if (empty($category['ok'])) {
                $results[$key] = array('ok' => false, 'error' => (string) ($category['error'] ?? 'Could not search Square categories.'));
                continue;
            }
            if (empty($category['found'])) {
                $category = bvm_sqr_create_category($context['api'], $key, $definition);
            }
            if (empty($category['ok']) || empty($category['category_id'])) {
                $results[$key] = array('ok' => false, 'error' => (string) ($category['error'] ?? 'Could not provision Square category.'));
                continue;
            }

            $item = bvm_sqr_find_item_by_sku($context['api'], (string) $definition['sku']);
            if (empty($item['ok'])) {
                $results[$key] = array('ok' => false, 'error' => (string) ($item['error'] ?? 'Could not search Square reporting items.'));
                continue;
            }
            if (empty($item['found'])) {
                $item = bvm_sqr_create_item($context['api'], $key, $definition, (string) $category['category_id'], (string) $context['location_id']);
            }
            if (empty($item['ok']) || empty($item['variation_id'])) {
                $results[$key] = array('ok' => false, 'error' => (string) ($item['error'] ?? 'Could not provision Square reporting item.'));
                continue;
            }

            $objects[$key] = array(
                'label' => (string) $definition['label'],
                'sku' => (string) $definition['sku'],
                'category_id' => (string) $category['category_id'],
                'category_name' => (string) ($category['category_name'] ?? $definition['label']),
                'item_id' => (string) $item['item_id'],
                'variation_id' => (string) $item['variation_id'],
                'location_id' => (string) $context['location_id'],
                'updated_gmt' => gmdate('Y-m-d H:i:s'),
            );
            $results[$key] = array(
                'ok' => true,
                'category_created' => !empty($category['created']),
                'item_created' => !empty($item['created']),
                'category_id' => (string) $category['category_id'],
                'item_id' => (string) $item['item_id'],
                'variation_id' => (string) $item['variation_id'],
            );
        }

        bvm_sqr_store_objects($objects, $environment);
        $failed = array_filter($results, static function ($row) {
            return empty($row['ok']);
        });

        return array(
            'ok' => empty($failed),
            'environment' => $environment,
            'location_id' => (string) $context['location_id'],
            'results' => $results,
            'objects' => $objects,
            'error' => empty($failed) ? '' : 'One or more reporting classes could not be provisioned.',
        );
    }
}

if (!function_exists('bvm_sqr_class_for_role')) {
    function bvm_sqr_class_for_role(string $role): string
    {
        $role = sanitize_key($role);
        if (in_array($role, array('ga_ticket', 'ticket', 'legacy_ticket', 'admission'), true)) {
            return 'online_ticket';
        }
        if (in_array($role, array('entitlement', 'addon', 'add_on', 'event_addon'), true)) {
            return 'online_addon';
        }
        if (in_array($role, array('rental', 'amenity_rental'), true)) {
            return 'rental';
        }
        if (in_array($role, array('online_tip', 'express_bar_tip'), true)) {
            return 'online_tips';
        }
        return '';
    }
}

if (!function_exists('bvm_sqr_classify_product')) {
    function bvm_sqr_classify_product(int $product_id): string
    {
        $product_id = absint($product_id);
        if ($product_id <= 0) {
            return '';
        }
        if (get_post_type($product_id) === 'product_variation') {
            $parent_id = absint(wp_get_post_parent_id($product_id));
            if ($parent_id > 0) {
                $product_id = $parent_id;
            }
        }

        $role = sanitize_key((string) get_post_meta($product_id, '_vms_product_role', true));
        $class = bvm_sqr_class_for_role($role);
        if ($class !== '') {
            return $class;
        }

        if ((string) get_post_meta($product_id, '_vmseb_online_tip_carrier', true) === 'yes') {
            return 'online_tips';
        }
        if ((string) get_post_meta($product_id, '_bvm_rental_product', true) === 'yes') {
            return 'rental';
        }

        if (taxonomy_exists('product_cat')) {
            $slugs = wp_get_object_terms($product_id, 'product_cat', array('fields' => 'slugs'));
            if (!is_wp_error($slugs) && is_array($slugs)) {
                $slugs = array_map('sanitize_title', $slugs);
                if (array_intersect($slugs, array('online-ticket', 'online-tickets', 'tickets'))) {
                    return 'online_ticket';
                }
                if (array_intersect($slugs, array('online-addon', 'online-addons', 'event-addon', 'event-addons'))) {
                    return 'online_addon';
                }
                if (array_intersect($slugs, array('rental', 'rentals'))) {
                    return 'rental';
                }
                if (array_intersect($slugs, array('online-tip', 'online-tips'))) {
                    return 'online_tips';
                }
            }
        }

        return '';
    }
}

if (!function_exists('bvm_sqr_classify_order_item')) {
    function bvm_sqr_classify_order_item($item): string
    {
        if (!is_object($item)) {
            return '';
        }

        if (method_exists($item, 'get_meta')) {
            if ((string) $item->get_meta('_vms_discounts_is_tip', true) === 'yes' || (string) $item->get_meta('_vmseb_online_tip', true) === 'yes') {
                return 'online_tips';
            }
        }

        if (!($item instanceof WC_Order_Item_Product)) {
            return '';
        }
        $product_id = absint($item->get_variation_id());
        if ($product_id <= 0) {
            $product_id = absint($item->get_product_id());
        }
        return bvm_sqr_classify_product($product_id);
    }
}

if (!function_exists('bvm_sqr_variation_id')) {
    function bvm_sqr_variation_id(string $class): string
    {
        $objects = bvm_sqr_get_objects();
        return isset($objects[$class]['variation_id']) ? trim((string) $objects[$class]['variation_id']) : '';
    }
}

if (!function_exists('bvm_sqr_stamp_item_object')) {
    function bvm_sqr_stamp_item_object($item): bool
    {
        if (!($item instanceof WC_Order_Item_Product)) {
            return false;
        }
        $class = bvm_sqr_classify_order_item($item);
        if ($class === '') {
            return false;
        }
        $variation_id = bvm_sqr_variation_id($class);
        if ($variation_id === '') {
            return false;
        }

        $item->update_meta_data('_square_item_variation_id', $variation_id);
        $item->update_meta_data('_bvm_square_reporting_class', $class);
        $item->update_meta_data('_bvm_square_reporting_variation_id', $variation_id);
        return true;
    }
}

if (!function_exists('bvm_sqr_stamp_checkout_line_item')) {
    function bvm_sqr_stamp_checkout_line_item($item, $cart_item_key, $values, $order): void
    {
        unset($cart_item_key, $values, $order);
        bvm_sqr_stamp_item_object($item);
    }
}



if (!function_exists('bvm_sqr_stamp_created_order_items')) {
    /**
     * Final reconciliation before the checkout hands the order to the payment
     * gateway. This also catches product lines added after the initial
     * cart-to-order copy (for example the Express Bar online-tip carrier).
     *
     * Persisted order items are stamped through the order-item meta API instead
     * of the potentially stale in-memory item object. That keeps this pass
     * idempotent and prevents duplicate reporting meta when another hook has
     * already stamped a newly-added line.
     */
    function bvm_sqr_stamp_created_order_items($order): void
    {
        if (!is_object($order) || !method_exists($order, 'get_items')) {
            return;
        }
        foreach ($order->get_items('line_item') as $item) {
            if (!($item instanceof WC_Order_Item_Product)) {
                continue;
            }

            $class = bvm_sqr_classify_order_item($item);
            if ($class === '') {
                continue;
            }
            $variation_id = bvm_sqr_variation_id($class);
            if ($variation_id === '') {
                continue;
            }

            $item_id = method_exists($item, 'get_id') ? absint($item->get_id()) : 0;
            if ($item_id > 0) {
                $stored_square = trim((string) wc_get_order_item_meta($item_id, '_square_item_variation_id', true));
                $stored_class = trim((string) wc_get_order_item_meta($item_id, '_bvm_square_reporting_class', true));
                $stored_reporting = trim((string) wc_get_order_item_meta($item_id, '_bvm_square_reporting_variation_id', true));

                if ($stored_square === $variation_id && $stored_class === $class && $stored_reporting === $variation_id) {
                    continue;
                }

                wc_update_order_item_meta($item_id, '_square_item_variation_id', $variation_id);
                wc_update_order_item_meta($item_id, '_bvm_square_reporting_class', $class);
                wc_update_order_item_meta($item_id, '_bvm_square_reporting_variation_id', $variation_id);
                continue;
            }

            bvm_sqr_stamp_item_object($item);
        }
    }
}
if (!function_exists('bvm_sqr_stamp_new_order_item')) {
    function bvm_sqr_stamp_new_order_item($item_id, $item, $order_id): void
    {
        unset($order_id);
        $item_id = absint($item_id);
        if ($item_id <= 0 || !($item instanceof WC_Order_Item_Product)) {
            return;
        }
        $class = bvm_sqr_classify_order_item($item);
        if ($class === '') {
            return;
        }
        $variation_id = bvm_sqr_variation_id($class);
        if ($variation_id === '') {
            return;
        }
        wc_update_order_item_meta($item_id, '_square_item_variation_id', $variation_id);
        wc_update_order_item_meta($item_id, '_bvm_square_reporting_class', $class);
        wc_update_order_item_meta($item_id, '_bvm_square_reporting_variation_id', $variation_id);
    }
}

if (!function_exists('bvm_sqr_restore_square_line_names_after_create')) {
    /**
     * Restore Woo/BVM line names after WooCommerce Square creates an order.
     *
     * WooCommerce Square omits the Woo order-item name when a catalog variation
     * ID is supplied. BVM intentionally supplies a stable reporting variation,
     * so Square otherwise displays the generic carrier name in reports.
     *
     * @param mixed $request_data  WooCommerce Square request log data.
     * @param mixed $response_data WooCommerce Square response log data.
     * @param mixed $api           WooCommerce Square API instance.
     */
    function bvm_sqr_restore_square_line_names_after_create($request_data, $response_data, $api): void
    {
        static $running = false;

        unset($api);

        if ($running || !is_array($request_data) || !is_array($response_data)) {
            return;
        }

        $running = true;

        try {
            $result = bvm_sqr_maybe_restore_square_line_names($request_data, $response_data);
        } catch (Throwable $e) {
            $result = new WP_Error('bvm_sqr_line_name_exception', $e->getMessage());
        } finally {
            $running = false;
        }

        if (is_wp_error($result)) {
            bvm_sqr_log_line_name_error($result);
        }
    }
}

if (!function_exists('bvm_sqr_maybe_restore_square_line_names')) {
    /**
     * Build and submit the narrow Square line-name update when the response is
     * an eligible BVM reporting order.
     *
     * @return array|WP_Error|null Update response, an update error, or null when
     *                             the request must be ignored.
     */
    function bvm_sqr_maybe_restore_square_line_names(array $request_data, array $response_data)
    {
        $uri = isset($request_data['uri']) ? (string) $request_data['uri'] : '';
        if (strpos($uri, 'createOrder') === false) {
            return null;
        }

        $response_body = isset($response_data['body']) ? (string) $response_data['body'] : '';
        $response = json_decode($response_body, true);
        if (
            !is_array($response)
            || empty($response['order']['id'])
            || !isset($response['order']['version'])
            || empty($response['order']['line_items'])
            || !is_array($response['order']['line_items'])
        ) {
            return null;
        }

        $square_order = $response['order'];
        $reference_id = isset($square_order['reference_id']) ? trim((string) $square_order['reference_id']) : '';

        if ($reference_id === '' && !empty($request_data['body'])) {
            $request_body = json_decode((string) $request_data['body'], true);
            if (is_array($request_body) && isset($request_body['order']['reference_id'])) {
                $reference_id = trim((string) $request_body['order']['reference_id']);
            }
        }

        if ($reference_id === '' || !ctype_digit($reference_id)) {
            return null;
        }

        $order = wc_get_order(absint($reference_id));
        if (!is_object($order) || !method_exists($order, 'get_order_number') || (string) $order->get_order_number() !== $reference_id) {
            return null;
        }

        $woo_items = array_values($order->get_items('line_item'));
        $square_items = array_values($square_order['line_items']);
        $updates = array();

        foreach ($woo_items as $index => $item) {
            if (!($item instanceof WC_Order_Item_Product) || !isset($square_items[$index]) || !is_array($square_items[$index])) {
                continue;
            }

            $reporting_class = trim((string) $item->get_meta('_bvm_square_reporting_class', true));
            $reporting_variation_id = trim((string) $item->get_meta('_bvm_square_reporting_variation_id', true));
            if ($reporting_class === '' || $reporting_variation_id === '') {
                continue;
            }

            $square_line = $square_items[$index];
            $square_catalog_id = isset($square_line['catalog_object_id']) ? trim((string) $square_line['catalog_object_id']) : '';

            // Fail closed rather than ever renaming the wrong Square line.
            if ($square_catalog_id === '' || $square_catalog_id !== $reporting_variation_id || empty($square_line['uid'])) {
                continue;
            }

            // Preserve the historical Woo/BVM item-name contract exactly.
            $desired_name = (string) $item->get_name();
            $current_name = isset($square_line['name']) ? (string) $square_line['name'] : '';
            if ($desired_name === '' || $current_name === $desired_name) {
                continue;
            }

            // A partial line update preserves catalog identity and every total.
            $updates[] = array(
                'uid' => (string) $square_line['uid'],
                'name' => $desired_name,
            );
        }

        if (empty($updates)) {
            return null;
        }

        $result = bvm_sqr_update_square_order_line_names(
            (string) $square_order['id'],
            (int) $square_order['version'],
            $updates
        );

        if (is_wp_error($result)) {
            $order_id = method_exists($order, 'get_id') ? absint($order->get_id()) : absint($reference_id);
            return new WP_Error(
                $result->get_error_code(),
                $result->get_error_message(),
                array('order_id' => $order_id)
            );
        }

        return $result;
    }
}

if (!function_exists('bvm_sqr_update_square_order_line_names')) {
    /**
     * Apply only uid/name fields to existing Square lines.
     *
     * @return array|WP_Error
     */
    function bvm_sqr_update_square_order_line_names(string $square_order_id, int $version, array $line_items)
    {
        if (!function_exists('wc_square')) {
            return new WP_Error('bvm_sqr_square_missing', 'WooCommerce Square is unavailable.');
        }

        $plugin = wc_square();
        $settings = is_object($plugin) && method_exists($plugin, 'get_settings_handler') ? $plugin->get_settings_handler() : null;
        if (!is_object($settings)) {
            return new WP_Error('bvm_sqr_settings_missing', 'WooCommerce Square settings are unavailable.');
        }

        $access_token = method_exists($settings, 'get_access_token') ? trim((string) $settings->get_access_token()) : '';
        if ($access_token === '') {
            return new WP_Error('bvm_sqr_token_missing', 'WooCommerce Square access token is unavailable.');
        }

        $is_sandbox = method_exists($settings, 'is_sandbox') && $settings->is_sandbox();
        $base_url = $is_sandbox ? 'https://connect.squareupsandbox.com/v2' : 'https://connect.squareup.com/v2';
        $payload = array(
            'idempotency_key' => 'bvm-name-' . substr(
                sha1($square_order_id . '|' . $version . '|' . wp_json_encode($line_items)),
                0,
                36
            ),
            'order' => array(
                'version' => $version,
                'line_items' => array_values($line_items),
            ),
        );

        $response = wp_remote_request(
            $base_url . '/orders/' . rawurlencode($square_order_id),
            array(
                'method' => 'PUT',
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json',
                    'Square-Version' => '2025-01-23',
                ),
                'body' => wp_json_encode($payload),
                'timeout' => 30,
            )
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        if ($status !== 200) {
            $detail = '';
            if (is_array($body) && !empty($body['errors'][0]['detail'])) {
                $detail = sanitize_text_field((string) $body['errors'][0]['detail']);
            }
            if ($detail === '') {
                $detail = sprintf('Square returned HTTP %d while restoring the line-item name.', $status);
            }
            return new WP_Error('bvm_sqr_square_update_failed', $detail);
        }

        return is_array($body) ? $body : array();
    }
}

if (!function_exists('bvm_sqr_log_line_name_error')) {
    function bvm_sqr_log_line_name_error($error): void
    {
        if (!is_wp_error($error) || !function_exists('wc_get_logger')) {
            return;
        }

        $logger = wc_get_logger();
        if (!is_object($logger) || !method_exists($logger, 'error')) {
            return;
        }

        $data = $error->get_error_data();
        $order_id = is_array($data) && !empty($data['order_id']) ? absint($data['order_id']) : 0;
        $subject = $order_id > 0 ? sprintf('Woo order #%d', $order_id) : 'a Woo order';

        $logger->error(
            sprintf(
                'Could not restore Square reporting line names for %s: %s',
                $subject,
                $error->get_error_message()
            ),
            array('source' => 'bvm-square-reporting')
        );
    }
}
