<?php
defined('ABSPATH') || exit;

function vms_square_ticket_mirror_schema_option_key(): string
{
    return 'vms_square_ticket_mirror_db_schema_version';
}

function vms_square_ticket_mirror_schema_target(): string
{
    return 'square_ticket_mirror_log_v1';
}

function vms_square_ticket_mirror_log_table_name(): string
{
    global $wpdb;
    return $wpdb->prefix . 'vms_square_ticket_mirror_log';
}

function vms_square_ticket_mirror_maybe_upgrade_schema(): void
{
    $current = (string) get_option(vms_square_ticket_mirror_schema_option_key(), '');
    $target = vms_square_ticket_mirror_schema_target();
    if ($current === $target) {
        return;
    }

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    global $wpdb;
    $table = vms_square_ticket_mirror_log_table_name();
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        product_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        event_plan_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        tec_event_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        action VARCHAR(60) NOT NULL DEFAULT '',
        status_before VARCHAR(40) NOT NULL DEFAULT '',
        status_after VARCHAR(40) NOT NULL DEFAULT '',
        item_id VARCHAR(120) NOT NULL DEFAULT '',
        variation_id VARCHAR(120) NOT NULL DEFAULT '',
        location_id VARCHAR(120) NOT NULL DEFAULT '',
        request_json LONGTEXT NULL,
        response_json LONGTEXT NULL,
        error_code VARCHAR(120) NOT NULL DEFAULT '',
        error_message TEXT NULL,
        actor_user_id BIGINT(20) UNSIGNED NULL,
        created_at_gmt DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY product_id (product_id),
        KEY event_plan_id (event_plan_id),
        KEY tec_event_id (tec_event_id),
        KEY action (action),
        KEY status_after (status_after),
        KEY created_at_gmt (created_at_gmt)
    ) {$charset_collate};";

    dbDelta($sql);
    update_option(vms_square_ticket_mirror_schema_option_key(), $target, false);
}
add_action('plugins_loaded', 'vms_square_ticket_mirror_maybe_upgrade_schema', 12);

function vms_square_ticket_mirror_product_meta_key(string $which): string
{
    if (function_exists('bvmgr_meta_key')) {
        $mapped = (string) bvmgr_meta_key('product', $which);
        if ($mapped !== '') {
            return $mapped;
        }
    }

    switch ($which) {
        case 'square_mirror_mode':
            return '_vms_square_mirror_mode';
        case 'square_mirror_status':
            return '_vms_square_mirror_status';
        case 'square_mirror_item_id':
            return '_vms_square_mirror_item_id';
        case 'square_mirror_variation_id':
            return '_vms_square_mirror_variation_id';
        case 'square_mirror_category_id':
            return '_vms_square_mirror_category_id';
        case 'square_mirror_location_id':
            return '_vms_square_mirror_location_id';
        case 'square_mirror_catalog_version':
            return '_vms_square_mirror_catalog_version';
        case 'square_mirror_source_hash':
            return '_vms_square_mirror_source_hash';
        case 'square_mirror_last_sync_gmt':
            return '_vms_square_mirror_last_sync_gmt';
        case 'square_mirror_last_error_code':
            return '_vms_square_mirror_last_error_code';
        case 'square_mirror_last_error_message':
            return '_vms_square_mirror_last_error_message';
        case 'square_mirror_last_retired_gmt':
            return '_vms_square_mirror_last_retired_gmt';
        case 'square_mirror_last_order_stamp_gmt':
            return '_vms_square_mirror_last_order_stamp_gmt';
        default:
            return '';
    }
}

function vms_square_ticket_mirror_event_plan_meta_key(string $which): string
{
    if (function_exists('bvmgr_meta_key')) {
        $mapped = (string) bvmgr_meta_key('event_plan', $which);
        if ($mapped !== '') {
            return $mapped;
        }
    }

    switch ($which) {
        case 'square_location_id':
            return '_vms_square_location_id';
        default:
            return '';
    }
}

function vms_square_ticket_mirror_status_labels(): array
{
    return array(
        'not_mirrored' => __('Not mirrored', 'backstage-venue-manager'),
        'mirrored' => __('Mirrored', 'backstage-venue-manager'),
        'mirror_stale' => __('Mirror stale', 'backstage-venue-manager'),
        'mirror_retired' => __('Mirror retired', 'backstage-venue-manager'),
        'mirror_error' => __('Mirror error', 'backstage-venue-manager'),
    );
}

function vms_square_ticket_mirror_normalize_status(string $status): string
{
    $status = sanitize_key($status);
    if (isset(vms_square_ticket_mirror_status_labels()[$status])) {
        return $status;
    }

    return 'not_mirrored';
}

function vms_square_ticket_mirror_label_for_status(string $status): string
{
    $status = vms_square_ticket_mirror_normalize_status($status);
    $labels = vms_square_ticket_mirror_status_labels();
    return (string) ($labels[$status] ?? $labels['not_mirrored']);
}

function vms_square_ticket_mirror_mode_value(): string
{
    return 'ticket_mirror';
}

function vms_square_ticket_mirror_now_gmt(): string
{
    return gmdate('Y-m-d H:i:s');
}

function vms_square_ticket_mirror_json_encode($value): string
{
    $encoded = wp_json_encode($value);
    return is_string($encoded) ? $encoded : '';
}

function vms_square_ticket_mirror_limit_text(string $value, int $max = 255): string
{
    $value = trim(wp_strip_all_tags($value));
    if ($value === '') {
        return '';
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($value) <= $max) {
            return $value;
        }
        return rtrim((string) mb_substr($value, 0, $max));
    }

    if (strlen($value) <= $max) {
        return $value;
    }

    return rtrim(substr($value, 0, $max));
}

function vms_square_ticket_mirror_sanitize_label(string $value): string
{
    if (function_exists('vms_ticketing_v2_sanitize_plain_text_label')) {
        return trim((string) vms_ticketing_v2_sanitize_plain_text_label($value));
    }

    return trim(sanitize_text_field($value));
}

function vms_square_ticket_mirror_canonical_product_id(int $product_id): int
{
    $product_id = absint($product_id);
    if ($product_id <= 0) {
        return 0;
    }

    $post_type = (string) get_post_type($product_id);
    if ($post_type === 'product_variation') {
        $parent_id = absint(wp_get_post_parent_id($product_id));
        if ($parent_id > 0) {
            return $parent_id;
        }
    }

    return $product_id;
}

function vms_square_ticket_mirror_get_product(int $product_id)
{
    if (!function_exists('wc_get_product')) {
        return null;
    }

    $product_id = absint($product_id);
    if ($product_id <= 0) {
        return null;
    }

    try {
        $product = wc_get_product($product_id);
    } catch (Throwable $e) {
        $product = null;
    }

    return $product instanceof WC_Product ? $product : null;
}

function vms_square_ticket_mirror_has_mirror_meta(int $product_id): bool
{
    $product_id = vms_square_ticket_mirror_canonical_product_id($product_id);
    if ($product_id <= 0) {
        return false;
    }

    foreach (array(
        'square_mirror_mode',
        'square_mirror_status',
        'square_mirror_item_id',
        'square_mirror_variation_id',
        'square_mirror_category_id',
        'square_mirror_location_id',
        'square_mirror_source_hash',
    ) as $field) {
        $meta_key = vms_square_ticket_mirror_product_meta_key($field);
        if ($meta_key !== '' && metadata_exists('post', $product_id, $meta_key)) {
            return true;
        }
    }

    return false;
}

function vms_square_ticket_mirror_get_meta(int $product_id, string $which): string
{
    $product_id = vms_square_ticket_mirror_canonical_product_id($product_id);
    $meta_key = vms_square_ticket_mirror_product_meta_key($which);
    if ($product_id <= 0 || $meta_key === '') {
        return '';
    }

    $value = get_post_meta($product_id, $meta_key, true);
    if (is_scalar($value)) {
        return trim((string) $value);
    }

    return '';
}

function vms_square_ticket_mirror_update_meta(int $product_id, string $which, $value): void
{
    $product_id = vms_square_ticket_mirror_canonical_product_id($product_id);
    $meta_key = vms_square_ticket_mirror_product_meta_key($which);
    if ($product_id <= 0 || $meta_key === '') {
        return;
    }

    update_post_meta($product_id, $meta_key, $value);
}

function vms_square_ticket_mirror_delete_meta(int $product_id, string $which): void
{
    $product_id = vms_square_ticket_mirror_canonical_product_id($product_id);
    $meta_key = vms_square_ticket_mirror_product_meta_key($which);
    if ($product_id <= 0 || $meta_key === '') {
        return;
    }

    delete_post_meta($product_id, $meta_key);
}

function vms_square_ticket_mirror_square_error_rows($response_or_data): array
{
    $rows = array();
    $data = $response_or_data;

    $normalize_error = static function ($error): array {
        if (is_object($error)) {
            $code = method_exists($error, 'getCode') ? $error->getCode() : '';
            $category = method_exists($error, 'getCategory') ? $error->getCategory() : '';
            $detail = method_exists($error, 'getDetail') ? $error->getDetail() : '';
            $field = method_exists($error, 'getField') ? $error->getField() : '';
        } elseif (is_array($error)) {
            $code = $error['code'] ?? '';
            $category = $error['category'] ?? '';
            $detail = $error['detail'] ?? '';
            $field = $error['field'] ?? '';
        } else {
            return array();
        }

        $code = trim((string) $code);
        $category = trim((string) $category);
        $detail = sanitize_text_field((string) $detail);
        $field = vms_square_ticket_mirror_limit_text(trim((string) $field), 255);
        if ($code === '' && $detail === '') {
            return array();
        }

        return array(
            'code' => $code !== '' ? $code : 'SQUARE_ERROR',
            'code_key' => sanitize_key($code !== '' ? $code : 'square_error'),
            'category' => $category,
            'field' => $field,
            'detail' => $detail !== '' ? $detail : __('Square API error', 'backstage-venue-manager'),
        );
    };

    if (is_object($response_or_data) && method_exists($response_or_data, 'get_errors')) {
        foreach ((array) $response_or_data->get_errors() as $error) {
            $row = $normalize_error($error);
            if (!empty($row)) {
                $rows[] = $row;
            }
        }
    }

    if (is_object($response_or_data) && method_exists($response_or_data, 'get_data')) {
        $data = $response_or_data->get_data();
    }

    if (is_array($data) && !empty($data['errors']) && is_array($data['errors'])) {
        foreach ($data['errors'] as $error) {
            $row = $normalize_error($error);
            if (!empty($row)) {
                $rows[] = $row;
            }
        }
    }

    if (is_object($data) && method_exists($data, 'getErrors')) {
        foreach ((array) $data->getErrors() as $error) {
            $row = $normalize_error($error);
            if (!empty($row)) {
                $rows[] = $row;
            }
        }
    }

    $unique = array();
    foreach ($rows as $row) {
        $hash = md5(vms_square_ticket_mirror_json_encode($row));
        $unique[$hash] = $row;
    }

    return array_values($unique);
}

function vms_square_ticket_mirror_square_error_summary($response_or_data): array
{
    $rows = vms_square_ticket_mirror_square_error_rows($response_or_data);
    if (!empty($rows)) {
        return array(
            'code' => (string) ($rows[0]['code'] ?? 'SQUARE_ERROR'),
            'message' => (string) ($rows[0]['detail'] ?? __('Square API error', 'backstage-venue-manager')),
            'category' => (string) ($rows[0]['category'] ?? ''),
            'field' => (string) ($rows[0]['field'] ?? ''),
            'rows' => $rows,
        );
    }

    return array(
        'code' => '',
        'message' => '',
        'category' => '',
        'field' => '',
        'rows' => array(),
    );
}

function vms_square_ticket_mirror_square_exception_debug(Throwable $exception): array
{
    $payload = array(
        'exception_class' => get_class($exception),
        'message' => sanitize_text_field($exception->getMessage()),
        'http_status' => 0,
        'errors' => array(),
    );

    if ($exception instanceof \Square\Exceptions\ApiException) {
        $payload['http_status'] = absint($exception->getCode());
        if ($exception->hasResponse()) {
            $raw_body = (string) $exception->getHttpResponse()->getRawBody();
            if ($raw_body !== '') {
                $decoded = json_decode($raw_body, true);
                if (is_array($decoded)) {
                    $payload['errors'] = vms_square_ticket_mirror_square_error_rows($decoded);
                }
            }
        }
    }

    if (empty($payload['errors'])) {
        $message = trim((string) $exception->getMessage());
        if (preg_match('/^\[([A-Z0-9_]+)\]\s*(.+)$/', $message, $matches)) {
            $payload['errors'][] = array(
                'code' => trim((string) $matches[1]),
                'code_key' => sanitize_key((string) $matches[1]),
                'category' => '',
                'field' => '',
                'detail' => sanitize_text_field((string) $matches[2]),
            );
        } elseif ($message !== '') {
            $payload['errors'][] = array(
                'code' => 'SQUARE_EXCEPTION',
                'code_key' => 'square_exception',
                'category' => '',
                'field' => '',
                'detail' => sanitize_text_field($message),
            );
        }
    }

    return $payload;
}

function vms_square_ticket_mirror_catalog_object_debug_shape($catalog_object): array
{
    $shape = array(
        'type' => '',
        'id' => '',
        'version' => 0,
    );

    if (!is_object($catalog_object) || !method_exists($catalog_object, 'getType')) {
        return $shape;
    }

    $shape['type'] = trim((string) $catalog_object->getType());
    $shape['id'] = method_exists($catalog_object, 'getId') ? trim((string) $catalog_object->getId()) : '';
    $shape['version'] = method_exists($catalog_object, 'getVersion') ? absint($catalog_object->getVersion()) : 0;

    if (method_exists($catalog_object, 'getPresentAtAllLocations')) {
        $shape['present_at_all_locations'] = $catalog_object->getPresentAtAllLocations();
    }
    if (method_exists($catalog_object, 'getPresentAtLocationIds')) {
        $shape['present_at_location_ids'] = array_values(array_filter(array_map('strval', (array) $catalog_object->getPresentAtLocationIds())));
    }
    if (method_exists($catalog_object, 'getAbsentAtLocationIds')) {
        $shape['absent_at_location_ids'] = array_values(array_filter(array_map('strval', (array) $catalog_object->getAbsentAtLocationIds())));
    }

    if ($shape['type'] === 'CATEGORY' && method_exists($catalog_object, 'getCategoryData')) {
        $category_data = $catalog_object->getCategoryData();
        $shape['category_data'] = array(
            'name' => is_object($category_data) && method_exists($category_data, 'getName') ? trim((string) $category_data->getName()) : '',
            'is_top_level' => is_object($category_data) && method_exists($category_data, 'getIsTopLevel') ? $category_data->getIsTopLevel() : null,
        );
        return $shape;
    }

    if ($shape['type'] !== 'ITEM') {
        return $shape;
    }

    $item_data = method_exists($catalog_object, 'getItemData') ? $catalog_object->getItemData() : null;
    if (!is_object($item_data)) {
        return $shape;
    }

    $categories = array();
    if (method_exists($item_data, 'getCategories')) {
        foreach ((array) $item_data->getCategories() as $category_ref) {
            if (is_object($category_ref) && method_exists($category_ref, 'getId')) {
                $categories[] = trim((string) $category_ref->getId());
            }
        }
    }

    $reporting_category_id = '';
    if (method_exists($item_data, 'getReportingCategory')) {
        $reporting_category = $item_data->getReportingCategory();
        if (is_object($reporting_category) && method_exists($reporting_category, 'getId')) {
            $reporting_category_id = trim((string) $reporting_category->getId());
        }
    }

    $shape['item_data'] = array(
        'name' => method_exists($item_data, 'getName') ? trim((string) $item_data->getName()) : '',
        'description_html_present' => method_exists($item_data, 'getDescriptionHtml') ? trim((string) $item_data->getDescriptionHtml()) !== '' : false,
        'is_archived' => method_exists($item_data, 'getIsArchived') ? $item_data->getIsArchived() : null,
        'available_online' => method_exists($item_data, 'getAvailableOnline') ? $item_data->getAvailableOnline() : null,
        'available_for_pickup' => method_exists($item_data, 'getAvailableForPickup') ? $item_data->getAvailableForPickup() : null,
        'available_electronically' => method_exists($item_data, 'getAvailableElectronically') ? $item_data->getAvailableElectronically() : null,
        'category_ids' => array_values(array_filter($categories)),
        'reporting_category_id' => $reporting_category_id,
    );

    $variations = method_exists($item_data, 'getVariations') ? (array) $item_data->getVariations() : array();
    if (!empty($variations[0]) && is_object($variations[0])) {
        $variation_object = $variations[0];
        $variation_data = method_exists($variation_object, 'getItemVariationData') ? $variation_object->getItemVariationData() : null;
        $price_money = is_object($variation_data) && method_exists($variation_data, 'getPriceMoney') ? $variation_data->getPriceMoney() : null;

        $shape['variation'] = array(
            'id' => method_exists($variation_object, 'getId') ? trim((string) $variation_object->getId()) : '',
            'version' => method_exists($variation_object, 'getVersion') ? absint($variation_object->getVersion()) : 0,
            'present_at_all_locations' => method_exists($variation_object, 'getPresentAtAllLocations') ? $variation_object->getPresentAtAllLocations() : null,
            'present_at_location_ids' => method_exists($variation_object, 'getPresentAtLocationIds') ? array_values(array_filter(array_map('strval', (array) $variation_object->getPresentAtLocationIds()))) : array(),
            'absent_at_location_ids' => method_exists($variation_object, 'getAbsentAtLocationIds') ? array_values(array_filter(array_map('strval', (array) $variation_object->getAbsentAtLocationIds()))) : array(),
            'item_variation_data' => array(
                'item_id' => is_object($variation_data) && method_exists($variation_data, 'getItemId') ? trim((string) $variation_data->getItemId()) : '',
                'name' => is_object($variation_data) && method_exists($variation_data, 'getName') ? trim((string) $variation_data->getName()) : '',
                'sku' => is_object($variation_data) && method_exists($variation_data, 'getSku') ? trim((string) $variation_data->getSku()) : '',
                'pricing_type' => is_object($variation_data) && method_exists($variation_data, 'getPricingType') ? trim((string) $variation_data->getPricingType()) : '',
                'track_inventory' => is_object($variation_data) && method_exists($variation_data, 'getTrackInventory') ? $variation_data->getTrackInventory() : null,
                'sellable' => is_object($variation_data) && method_exists($variation_data, 'getSellable') ? $variation_data->getSellable() : null,
                'stockable' => is_object($variation_data) && method_exists($variation_data, 'getStockable') ? $variation_data->getStockable() : null,
                'price_money' => array(
                    'amount' => is_object($price_money) && method_exists($price_money, 'getAmount') ? (int) $price_money->getAmount() : 0,
                    'currency' => is_object($price_money) && method_exists($price_money, 'getCurrency') ? trim((string) $price_money->getCurrency()) : '',
                ),
            ),
        );
    }

    return $shape;
}

function vms_square_ticket_mirror_catalog_object_debug_json($catalog_object): string
{
    return vms_square_ticket_mirror_json_encode(array(
        'object' => vms_square_ticket_mirror_catalog_object_debug_shape($catalog_object),
    ));
}

function vms_square_ticket_mirror_store_square_category_mapping(int $term_id, string $square_id, int $square_version): void
{
    $term_id = absint($term_id);
    if ($term_id <= 0 || !class_exists('\WooCommerce\Square\Handlers\Category')) {
        return;
    }

    \WooCommerce\Square\Handlers\Category::update_mapping($term_id, $square_id, $square_version);
    \WooCommerce\Square\Handlers\Category::update_square_meta($term_id, $square_id, $square_version);
}

function vms_square_ticket_mirror_get_square_context(bool $with_locations = false): array
{
    $context = array(
        'ok' => false,
        'error_code' => '',
        'error_message' => '',
        'plugin' => null,
        'settings' => null,
        'api' => null,
        'location_id' => '',
        'locations' => array(),
        'is_sandbox' => false,
    );

    if (!function_exists('wc_square')) {
        $context['error_code'] = 'square_plugin_missing';
        $context['error_message'] = __('WooCommerce Square is not loaded.', 'backstage-venue-manager');
        return $context;
    }

    try {
        $plugin = wc_square();
    } catch (Throwable $e) {
        $plugin = null;
    }

    if (!is_object($plugin) || !method_exists($plugin, 'get_settings_handler')) {
        $context['error_code'] = 'square_plugin_unavailable';
        $context['error_message'] = __('WooCommerce Square is unavailable.', 'backstage-venue-manager');
        return $context;
    }

    $settings = $plugin->get_settings_handler();
    if (!is_object($settings)) {
        $context['error_code'] = 'square_settings_unavailable';
        $context['error_message'] = __('WooCommerce Square settings are unavailable.', 'backstage-venue-manager');
        return $context;
    }

    $context['plugin'] = $plugin;
    $context['settings'] = $settings;
    $context['is_sandbox'] = method_exists($settings, 'is_sandbox') ? (bool) $settings->is_sandbox() : false;
    $context['location_id'] = method_exists($settings, 'get_location_id') ? trim((string) $settings->get_location_id()) : '';

    if (!method_exists($settings, 'is_connected') || !$settings->is_connected()) {
        $context['error_code'] = 'square_not_connected';
        $context['error_message'] = __('WooCommerce Square is not connected.', 'backstage-venue-manager');
        return $context;
    }

    if ($context['location_id'] === '') {
        $context['error_code'] = 'square_location_missing';
        $context['error_message'] = __('WooCommerce Square does not have a configured location.', 'backstage-venue-manager');
        return $context;
    }

    if ($with_locations && method_exists($settings, 'get_locations')) {
        try {
            $context['locations'] = (array) $settings->get_locations();
        } catch (Throwable $e) {
            $context['locations'] = array();
        }
    }

    if (method_exists($plugin, 'get_api')) {
        try {
            $context['api'] = $plugin->get_api();
        } catch (Throwable $e) {
            $context['api'] = null;
            $context['error_code'] = 'square_api_unavailable';
            $context['error_message'] = sanitize_text_field($e->getMessage());
            return $context;
        }
    }

    $context['ok'] = is_object($context['api']);
    if (!$context['ok']) {
        $context['error_code'] = 'square_api_unavailable';
        $context['error_message'] = __('WooCommerce Square API is unavailable.', 'backstage-venue-manager');
    }

    return $context;
}

function vms_square_ticket_mirror_target_category_name(): string
{
    return 'Online Ticket';
}

function vms_square_ticket_mirror_target_category_slug(): string
{
    return 'online-ticket';
}

function vms_square_ticket_mirror_ensure_local_category_term(): array
{
    $slug = vms_square_ticket_mirror_target_category_slug();
    $name = vms_square_ticket_mirror_target_category_name();

    $term = get_term_by('slug', $slug, 'product_cat');
    if ($term instanceof WP_Term) {
        return array(
            'ok' => true,
            'term_id' => absint($term->term_id),
            'slug' => (string) $term->slug,
            'name' => (string) $term->name,
            'created' => false,
        );
    }

    $term = get_term_by('name', $name, 'product_cat');
    if ($term instanceof WP_Term) {
        if ((string) $term->slug !== $slug) {
            wp_update_term((int) $term->term_id, 'product_cat', array('slug' => $slug));
            $term = get_term((int) $term->term_id, 'product_cat');
        }

        return array(
            'ok' => $term instanceof WP_Term,
            'term_id' => $term instanceof WP_Term ? absint($term->term_id) : 0,
            'slug' => $term instanceof WP_Term ? (string) $term->slug : $slug,
            'name' => $term instanceof WP_Term ? (string) $term->name : $name,
            'created' => false,
        );
    }

    $inserted = wp_insert_term($name, 'product_cat', array('slug' => $slug));
    if (is_wp_error($inserted)) {
        return array(
            'ok' => false,
            'term_id' => 0,
            'slug' => $slug,
            'name' => $name,
            'created' => false,
            'error_code' => 'local_category_create_failed',
            'error_message' => sanitize_text_field($inserted->get_error_message()),
        );
    }

    return array(
        'ok' => true,
        'term_id' => absint($inserted['term_id'] ?? 0),
        'slug' => $slug,
        'name' => $name,
        'created' => true,
    );
}

function vms_square_ticket_mirror_find_remote_category_by_name($api, string $target_name): array
{
    $cursor = '';

    while (true) {
        $response = $api->list_catalog($cursor, array('CATEGORY'));
        $summary = vms_square_ticket_mirror_square_error_summary($response);
        if ($summary['code'] !== '') {
            return array(
                'ok' => false,
                'square_id' => '',
                'square_version' => 0,
                'error_code' => (string) $summary['code'],
                'error_message' => (string) $summary['message'],
            );
        }

        $data = $response->get_data();
        if (!is_object($data) || !method_exists($data, 'getObjects')) {
            break;
        }

        foreach ((array) $data->getObjects() as $object) {
            if (!is_object($object) || !method_exists($object, 'getType') || $object->getType() !== 'CATEGORY') {
                continue;
            }

            $category_data = method_exists($object, 'getCategoryData') ? $object->getCategoryData() : null;
            $category_name = is_object($category_data) && method_exists($category_data, 'getName')
                ? trim((string) $category_data->getName())
                : '';

            if ($category_name !== $target_name) {
                continue;
            }

            return array(
                'ok' => true,
                'square_id' => trim((string) (method_exists($object, 'getId') ? $object->getId() : '')),
                'square_version' => absint(method_exists($object, 'getVersion') ? $object->getVersion() : 0),
                'object' => $object,
                'created' => false,
            );
        }

        $cursor = is_object($data) && method_exists($data, 'getCursor') ? trim((string) $data->getCursor()) : '';
        if ($cursor === '') {
            break;
        }
    }

    return array(
        'ok' => false,
        'square_id' => '',
        'square_version' => 0,
        'error_code' => 'square_category_not_found',
        'error_message' => __('Square category was not found.', 'backstage-venue-manager'),
    );
}

function vms_square_ticket_mirror_retrieve_remote_category($api, string $square_id): array
{
    $square_id = trim($square_id);
    if ($square_id === '' || strpos($square_id, '#') === 0) {
        return array(
            'ok' => false,
            'square_id' => '',
            'square_version' => 0,
            'error_code' => 'square_category_not_found',
            'error_message' => __('Square category was not found.', 'backstage-venue-manager'),
        );
    }

    try {
        $response = $api->retrieve_catalog_object($square_id, false);
    } catch (Throwable $e) {
        $exception = vms_square_ticket_mirror_square_exception_debug($e);
        $rows = (array) ($exception['errors'] ?? array());
        $first = !empty($rows[0]) && is_array($rows[0]) ? $rows[0] : array();
        return array(
            'ok' => false,
            'square_id' => '',
            'square_version' => 0,
            'error_code' => (string) ($first['code'] ?? 'square_category_retrieve_failed'),
            'error_message' => (string) ($first['detail'] ?? ($exception['message'] ?? __('Square category could not be retrieved.', 'backstage-venue-manager'))),
            'response_json' => vms_square_ticket_mirror_json_encode($exception),
        );
    }

    $summary = vms_square_ticket_mirror_square_error_summary($response);
    if ($summary['code'] !== '') {
        return array(
            'ok' => false,
            'square_id' => '',
            'square_version' => 0,
            'error_code' => (string) $summary['code'],
            'error_message' => (string) $summary['message'],
            'response_json' => vms_square_ticket_mirror_json_encode(array(
                'errors' => (array) ($summary['rows'] ?? array()),
            )),
        );
    }

    $data = $response->get_data();
    $object = is_object($data) && method_exists($data, 'getObject') ? $data->getObject() : null;
    if (!is_object($object) || !method_exists($object, 'getType') || $object->getType() !== 'CATEGORY') {
        return array(
            'ok' => false,
            'square_id' => '',
            'square_version' => 0,
            'error_code' => 'square_category_not_found',
            'error_message' => __('Square category was not found.', 'backstage-venue-manager'),
            'response_json' => vms_square_ticket_mirror_json_encode(array(
                'object' => vms_square_ticket_mirror_catalog_object_debug_shape($object),
            )),
        );
    }

    return array(
        'ok' => true,
        'square_id' => trim((string) (method_exists($object, 'getId') ? $object->getId() : '')),
        'square_version' => absint(method_exists($object, 'getVersion') ? $object->getVersion() : 0),
        'object' => $object,
        'response_json' => vms_square_ticket_mirror_json_encode(array(
            'object' => vms_square_ticket_mirror_catalog_object_debug_shape($object),
        )),
    );
}

function vms_square_ticket_mirror_resolve_square_category(bool $create_if_missing = false): array
{
    $local = vms_square_ticket_mirror_ensure_local_category_term();
    if (empty($local['ok'])) {
        return $local;
    }

    $term_id = absint($local['term_id'] ?? 0);
    $name = (string) ($local['name'] ?? vms_square_ticket_mirror_target_category_name());
    $result = array(
        'ok' => true,
        'term_id' => $term_id,
        'name' => $name,
        'slug' => (string) ($local['slug'] ?? vms_square_ticket_mirror_target_category_slug()),
        'square_id' => '',
        'square_version' => 0,
        'created_remote' => false,
        'resolution_path' => 'local_only',
        'mapping_square_id' => '',
        'mapping_square_version' => 0,
    );

    if ($term_id <= 0 || !class_exists('\WooCommerce\Square\Handlers\Category')) {
        return $result;
    }

    $mapping = \WooCommerce\Square\Handlers\Category::get_mapping($term_id);
    $square_id = trim((string) ($mapping['square_id'] ?? ''));
    $square_version = absint($mapping['square_version'] ?? 0);
    $result['mapping_square_id'] = $square_id;
    $result['mapping_square_version'] = $square_version;

    if ($square_id === '' && !$create_if_missing) {
        return $result;
    }

    $square = vms_square_ticket_mirror_get_square_context();
    if (empty($square['ok'])) {
        if ($square_id !== '' && !$create_if_missing) {
            $result['square_id'] = $square_id;
            $result['square_version'] = $square_version;
            $result['resolution_path'] = 'mapping_unvalidated';
            return $result;
        }

        return array_merge($result, array(
            'ok' => false,
            'error_code' => (string) ($square['error_code'] ?? 'square_unavailable'),
            'error_message' => (string) ($square['error_message'] ?? __('WooCommerce Square is unavailable.', 'backstage-venue-manager')),
        ));
    }

    if ($square_id !== '' && strpos($square_id, '#') !== 0) {
        $validated = vms_square_ticket_mirror_retrieve_remote_category($square['api'], $square_id);
        if (!empty($validated['ok']) && !empty($validated['square_id'])) {
            vms_square_ticket_mirror_store_square_category_mapping($term_id, (string) $validated['square_id'], (int) ($validated['square_version'] ?? 0));
            $result['square_id'] = (string) $validated['square_id'];
            $result['square_version'] = absint($validated['square_version'] ?? 0);
            $result['resolution_path'] = 'mapping_valid';
            return $result;
        }
    }

    $remote = vms_square_ticket_mirror_find_remote_category_by_name($square['api'], $name);
    if (!empty($remote['ok']) && !empty($remote['square_id'])) {
        vms_square_ticket_mirror_store_square_category_mapping($term_id, (string) $remote['square_id'], (int) ($remote['square_version'] ?? 0));
        $result['square_id'] = (string) $remote['square_id'];
        $result['square_version'] = absint($remote['square_version'] ?? 0);
        $result['resolution_path'] = $square_id !== '' ? 'mapping_stale_name_match' : 'name_match';
        return $result;
    }

    if (!$create_if_missing) {
        if ($square_id !== '') {
            vms_square_ticket_mirror_store_square_category_mapping($term_id, '', 0);
            $result['resolution_path'] = 'mapping_stale_unresolved';
        }
        return $result;
    }

    $category_object = new \Square\Models\CatalogObject('CATEGORY', '#vms_online_ticket_category_' . $term_id);
    $category_data = new \Square\Models\CatalogCategory();
    $category_data->setName($name);
    $category_data->setIsTopLevel(true);
    $category_object->setCategoryData($category_data);

    $request_json = vms_square_ticket_mirror_json_encode(vms_square_ticket_mirror_catalog_object_debug_shape($category_object));

    try {
        $response = $square['api']->upsert_catalog_object(
            function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('vmsstmcat_', true),
            $category_object
        );
    } catch (Throwable $e) {
        $exception = vms_square_ticket_mirror_square_exception_debug($e);
        $rows = (array) ($exception['errors'] ?? array());
        $first = !empty($rows[0]) && is_array($rows[0]) ? $rows[0] : array();
        return array_merge($result, array(
            'ok' => false,
            'error_code' => (string) ($first['code'] ?? 'square_category_upsert_failed'),
            'error_message' => (string) ($first['detail'] ?? ($exception['message'] ?? sanitize_text_field($e->getMessage()))),
            'request_json' => $request_json,
            'response_json' => vms_square_ticket_mirror_json_encode($exception),
        ));
    }

    $summary = vms_square_ticket_mirror_square_error_summary($response);
    if ($summary['code'] !== '') {
        return array_merge($result, array(
            'ok' => false,
            'error_code' => (string) $summary['code'],
            'error_message' => (string) $summary['message'],
            'request_json' => $request_json,
            'response_json' => vms_square_ticket_mirror_json_encode(array(
                'errors' => (array) ($summary['rows'] ?? array()),
            )),
        ));
    }

    $data = $response->get_data();
    $remote_object = is_object($data) && method_exists($data, 'getCatalogObject') ? $data->getCatalogObject() : null;
    $remote_square_id = is_object($remote_object) && method_exists($remote_object, 'getId') ? trim((string) $remote_object->getId()) : '';
    $remote_square_version = is_object($remote_object) && method_exists($remote_object, 'getVersion') ? absint($remote_object->getVersion()) : 0;

    if ($remote_square_id === '') {
        return array_merge($result, array(
            'ok' => false,
            'error_code' => 'square_category_missing_id',
            'error_message' => __('Square did not return a category ID.', 'backstage-venue-manager'),
            'request_json' => $request_json,
            'response_json' => vms_square_ticket_mirror_json_encode(array(
                'object' => vms_square_ticket_mirror_catalog_object_debug_shape($remote_object),
            )),
        ));
    }

    vms_square_ticket_mirror_store_square_category_mapping($term_id, $remote_square_id, $remote_square_version);

    $result['square_id'] = $remote_square_id;
    $result['square_version'] = $remote_square_version;
    $result['created_remote'] = true;
    $result['resolution_path'] = $square_id !== '' ? 'mapping_stale_created' : 'created_remote';

    return $result;
}

function vms_square_ticket_mirror_resolve_location(int $event_plan_id = 0, bool $validate_override = false): array
{
    $event_plan_id = absint($event_plan_id);
    $square = vms_square_ticket_mirror_get_square_context($validate_override && $event_plan_id > 0);
    $result = array(
        'ok' => false,
        'location_id' => '',
        'source' => '',
        'error_code' => (string) ($square['error_code'] ?? ''),
        'error_message' => (string) ($square['error_message'] ?? ''),
        'is_sandbox' => !empty($square['is_sandbox']),
    );

    if (empty($square['ok'])) {
        return $result;
    }

    $default_location_id = trim((string) ($square['location_id'] ?? ''));
    $location_id = $default_location_id;
    $source = 'woocommerce_square_default';
    $override = '';
    if ($event_plan_id > 0) {
        $override_key = vms_square_ticket_mirror_event_plan_meta_key('square_location_id');
        if ($override_key !== '') {
            $override = trim((string) get_post_meta($event_plan_id, $override_key, true));
        }
    }

    if ($override !== '') {
        $valid = false;
        $locations = is_array($square['locations'] ?? null) ? (array) $square['locations'] : array();
        if ($validate_override && !empty($locations)) {
            foreach ($locations as $location) {
                if (is_object($location) && method_exists($location, 'getId') && trim((string) $location->getId()) === $override) {
                    $valid = true;
                    break;
                }
            }
        } else {
            $valid = true;
        }

        if ($valid) {
            $location_id = $override;
            $source = 'event_plan_override';
        }
    }

    if ($location_id === '') {
        $result['error_code'] = 'square_location_missing';
        $result['error_message'] = __('No Square location could be resolved.', 'backstage-venue-manager');
        return $result;
    }

    $result['ok'] = true;
    $result['location_id'] = $location_id;
    $result['source'] = $source;
    $result['error_code'] = '';
    $result['error_message'] = '';

    return $result;
}

function vms_square_ticket_mirror_product_role(int $product_id): string
{
    $product_id = absint($product_id);
    if ($product_id <= 0) {
        return '';
    }

    if (function_exists('vms_ticketing_v2_product_role_for_naming')) {
        $role = sanitize_key((string) vms_ticketing_v2_product_role_for_naming($product_id));
        if ($role !== '') {
            return $role;
        }
    }

    if (function_exists('vms_ticketing_v2_product_meta_key') && function_exists('vms_ticketing_v2_meta_get')) {
        return sanitize_key((string) vms_ticketing_v2_meta_get($product_id, vms_ticketing_v2_product_meta_key('product_role')));
    }

    return sanitize_key((string) get_post_meta($product_id, '_vms_product_role', true));
}

function vms_square_ticket_mirror_included_roles(): array
{
    return array('ga_ticket', 'ticket', 'legacy_ticket');
}

function vms_square_ticket_mirror_ticket_label(int $product_id, int $plan_id = 0): string
{
    $product_id = vms_square_ticket_mirror_canonical_product_id($product_id);
    $plan_id = absint($plan_id);
    if ($product_id <= 0) {
        return '';
    }

    $label = '';
    if ($plan_id > 0 && function_exists('vms_ticketing_v2_ticket_config_for_product')) {
        $ticket = vms_ticketing_v2_ticket_config_for_product($product_id, $plan_id);
        $label = vms_square_ticket_mirror_sanitize_label((string) ($ticket['title'] ?? ''));
    }

    if ($label === '' && function_exists('vms_ticketing_v2_get_ticket_config_for_product_price')) {
        $ticket = vms_ticketing_v2_get_ticket_config_for_product_price($product_id);
        $label = vms_square_ticket_mirror_sanitize_label((string) ($ticket['title'] ?? ''));
    }

    if ($label === '' && function_exists('vms_ticketing_v2_sync_ticket_row_for_product')) {
        $row = vms_ticketing_v2_sync_ticket_row_for_product($product_id, $plan_id);
        $label = vms_square_ticket_mirror_sanitize_label((string) ($row['title'] ?? ''));
    }

    if ($label === '') {
        $raw_title = (string) get_the_title($product_id);
        if (function_exists('vms_ticketing_v2_normalize_admin_ticket_title_for_match')) {
            $raw_title = (string) vms_ticketing_v2_normalize_admin_ticket_title_for_match($raw_title);
        }
        $label = vms_square_ticket_mirror_sanitize_label($raw_title);
    }

    return $label;
}

function vms_square_ticket_mirror_effective_price_context(int $product_id, $product = null): array
{
    $product_id = vms_square_ticket_mirror_canonical_product_id($product_id);
    if (!($product instanceof WC_Product)) {
        $product = vms_square_ticket_mirror_get_product($product_id);
    }

    $price = 0.0;
    $ticket = array();

    if (function_exists('vms_ticketing_v2_get_ticket_config_for_product_price')) {
        $ticket = vms_ticketing_v2_get_ticket_config_for_product_price($product_id);
        if (!empty($ticket) && function_exists('vms_ticketing_v2_get_ticket_effective_price')) {
            $price = (float) vms_ticketing_v2_get_ticket_effective_price($ticket);
        }
    }

    if ($price <= 0 && $product instanceof WC_Product) {
        $price = max(0.0, (float) $product->get_price());
    }

    if ($price <= 0) {
        $price = max(0.0, (float) get_post_meta($product_id, '_price', true));
    }

    if (function_exists('wc_add_number_precision')) {
        $price_cents = max(0, absint(wc_add_number_precision($price)));
    } else {
        $price_cents = max(0, (int) round($price * 100));
    }

    return array(
        'price' => $price,
        'price_cents' => $price_cents,
        'currency' => function_exists('get_woocommerce_currency') ? (string) get_woocommerce_currency() : 'USD',
        'ticket' => $ticket,
    );
}

function vms_square_ticket_mirror_is_excluded_label(string $label): bool
{
    $label = strtolower(trim($label));
    if ($label === '') {
        return false;
    }

    return (bool) preg_match('/\b(child|children|kid|kids|comp|qualified|qualify|veteran|police|fire|emt|nurse|teacher|school|internal|door|walk[\s-]?up|pos|vip)\b/u', $label);
}

function vms_square_ticket_mirror_eligibility(int $product_id): array
{
    $product_id = vms_square_ticket_mirror_canonical_product_id($product_id);
    $result = array(
        'eligible' => false,
        'product_id' => $product_id,
        'reason_code' => '',
        'reason_message' => '',
        'role' => '',
        'visibility_mode' => 'public',
        'verified_program' => '',
        'event_plan_id' => 0,
        'tec_event_id' => 0,
        'sku' => '',
        'ticket_label' => '',
        'price' => 0.0,
        'price_cents' => 0,
        'currency' => function_exists('get_woocommerce_currency') ? (string) get_woocommerce_currency() : 'USD',
        'event_title' => '',
        'event_date' => '',
    );

    if ($product_id <= 0) {
        $result['reason_code'] = 'invalid_product';
        $result['reason_message'] = __('Product ID is invalid.', 'backstage-venue-manager');
        return $result;
    }

    $post_type = (string) get_post_type($product_id);
    if ($post_type !== 'product') {
        $result['reason_code'] = 'not_product';
        $result['reason_message'] = __('Product is not a WooCommerce product.', 'backstage-venue-manager');
        return $result;
    }

    $post_status = (string) get_post_status($product_id);
    if (in_array($post_status, array('draft', 'trash', 'auto-draft', 'pending'), true)) {
        $result['reason_code'] = 'product_inactive';
        $result['reason_message'] = __('Ticket product is inactive.', 'backstage-venue-manager');
        return $result;
    }

    $role = vms_square_ticket_mirror_product_role($product_id);
    $result['role'] = $role;
    if (!in_array($role, vms_square_ticket_mirror_included_roles(), true)) {
        $result['reason_code'] = 'role_excluded';
        $result['reason_message'] = __('Only paid public online ticket products are mirrored in Phase 1.', 'backstage-venue-manager');
        return $result;
    }

    $visibility = function_exists('vms_ticketing_v2_resolve_verified_ticket_context')
        ? (array) vms_ticketing_v2_resolve_verified_ticket_context($product_id)
        : array();
    $visibility_mode = sanitize_key((string) ($visibility['visibility_mode'] ?? 'public'));
    if (!in_array($visibility_mode, array('public', 'login', 'verified'), true)) {
        $visibility_mode = 'public';
    }
    $result['visibility_mode'] = $visibility_mode;
    $result['verified_program'] = sanitize_key((string) ($visibility['program'] ?? ''));
    if ($visibility_mode !== 'public') {
        $result['reason_code'] = 'visibility_excluded';
        $result['reason_message'] = __('Only public ticket products are mirrored in Phase 1.', 'backstage-venue-manager');
        return $result;
    }

    $plan_id = 0;
    if (function_exists('vms_ticketing_v2_resolve_plan_id_for_ticket_product')) {
        $plan_id = absint(vms_ticketing_v2_resolve_plan_id_for_ticket_product($product_id));
    }
    if ($plan_id <= 0 && function_exists('vms_ticketing_v2_product_meta_key') && function_exists('vms_ticketing_v2_meta_get')) {
        $plan_id = absint(vms_ticketing_v2_meta_get($product_id, vms_ticketing_v2_product_meta_key('event_plan_id')));
    }

    $event = function_exists('vms_ticketing_v2_resolve_event_snapshot_for_product')
        ? (array) vms_ticketing_v2_resolve_event_snapshot_for_product($product_id)
        : array();
    $result['event_plan_id'] = $plan_id > 0 ? $plan_id : absint($event['event_plan_id'] ?? 0);
    $result['tec_event_id'] = absint($event['tec_event_id'] ?? 0);
    $result['event_title'] = trim((string) ($event['title'] ?? ''));
    $result['event_date'] = trim((string) ($event['date'] ?? ''));

    if ($result['event_plan_id'] <= 0 || $result['tec_event_id'] <= 0) {
        $result['reason_code'] = 'missing_event_linkage';
        $result['reason_message'] = __('Ticket product is missing valid event linkage.', 'backstage-venue-manager');
        return $result;
    }

    if (function_exists('vms_ticketing_v2_disabled_ticket_config_for_product')) {
        $disabled = (array) vms_ticketing_v2_disabled_ticket_config_for_product($product_id, $result['event_plan_id']);
        if (!empty($disabled['disabled'])) {
            $result['reason_code'] = 'ticket_disabled_pending_sync';
            $result['reason_message'] = __('Ticket is disabled in saved config and pending retirement.', 'backstage-venue-manager');
            return $result;
        }
    }

    $sku = '';
    if (function_exists('vms_square_firewall_get_sku')) {
        $sku = trim((string) vms_square_firewall_get_sku($product_id));
    }
    if ($sku === '') {
        $product = vms_square_ticket_mirror_get_product($product_id);
        $sku = $product instanceof WC_Product ? trim((string) $product->get_sku()) : '';
    }
    $result['sku'] = $sku;
    if ($sku === '') {
        $result['reason_code'] = 'missing_sku';
        $result['reason_message'] = __('Ticket product does not have a SKU.', 'backstage-venue-manager');
        return $result;
    }

    $ticket_label = vms_square_ticket_mirror_ticket_label($product_id, $result['event_plan_id']);
    $result['ticket_label'] = $ticket_label;
    if ($ticket_label === '') {
        $result['reason_code'] = 'missing_ticket_label';
        $result['reason_message'] = __('Ticket label could not be resolved.', 'backstage-venue-manager');
        return $result;
    }

    if (vms_square_ticket_mirror_is_excluded_label($ticket_label)) {
        $result['reason_code'] = 'label_excluded';
        $result['reason_message'] = __('Ticket label matches a Phase 1 exclusion rule.', 'backstage-venue-manager');
        return $result;
    }

    if ((string) get_post_meta($product_id, '_vms_is_rsvp', true) === 'yes') {
        $result['reason_code'] = 'rsvp_excluded';
        $result['reason_message'] = __('RSVP / free public tickets are excluded from Phase 1 mirrors.', 'backstage-venue-manager');
        return $result;
    }

    $price_context = vms_square_ticket_mirror_effective_price_context($product_id);
    $result['price'] = (float) ($price_context['price'] ?? 0.0);
    $result['price_cents'] = max(0, absint($price_context['price_cents'] ?? 0));
    $result['currency'] = (string) ($price_context['currency'] ?? $result['currency']);
    if ($result['price_cents'] <= 0) {
        $result['reason_code'] = 'price_excluded';
        $result['reason_message'] = __('Free, comp, and zero-price tickets are excluded from Phase 1 mirrors.', 'backstage-venue-manager');
        return $result;
    }

    $result['eligible'] = true;
    return $result;
}

function vms_square_ticket_mirror_compose_name(array $eligibility): string
{
    $event_title = trim((string) ($eligibility['event_title'] ?? ''));
    $ticket_label = trim((string) ($eligibility['ticket_label'] ?? ''));
    $event_date = trim((string) ($eligibility['event_date'] ?? ''));

    $parts = array_filter(array(
        'Online Ticket',
        $event_title,
        $ticket_label,
        $event_date,
    ));

    return vms_square_ticket_mirror_limit_text(implode(' - ', $parts), 255);
}

function vms_square_ticket_mirror_build_source_model(int $product_id): array
{
    $product_id = vms_square_ticket_mirror_canonical_product_id($product_id);
    $eligibility = vms_square_ticket_mirror_eligibility($product_id);
    $category = vms_square_ticket_mirror_resolve_square_category(false);
    $location = vms_square_ticket_mirror_resolve_location(absint($eligibility['event_plan_id'] ?? 0), false);

    return array(
        'product_id' => $product_id,
        'event_plan_id' => absint($eligibility['event_plan_id'] ?? 0),
        'tec_event_id' => absint($eligibility['tec_event_id'] ?? 0),
        'role' => sanitize_key((string) ($eligibility['role'] ?? '')),
        'visibility_mode' => sanitize_key((string) ($eligibility['visibility_mode'] ?? 'public')),
        'verified_program' => sanitize_key((string) ($eligibility['verified_program'] ?? '')),
        'sku' => trim((string) ($eligibility['sku'] ?? '')),
        'price_cents' => max(0, absint($eligibility['price_cents'] ?? 0)),
        'currency' => trim((string) ($eligibility['currency'] ?? 'USD')),
        'ticket_label' => trim((string) ($eligibility['ticket_label'] ?? '')),
        'event_title' => trim((string) ($eligibility['event_title'] ?? '')),
        'event_date' => trim((string) ($eligibility['event_date'] ?? '')),
        'category_name' => (string) ($category['name'] ?? vms_square_ticket_mirror_target_category_name()),
        'category_id' => trim((string) ($category['square_id'] ?? '')),
        'category_term_id' => absint($category['term_id'] ?? 0),
        'category_resolution_path' => sanitize_key((string) ($category['resolution_path'] ?? 'local_only')),
        'category_mapping_square_id' => trim((string) ($category['mapping_square_id'] ?? '')),
        'category_mapping_square_version' => absint($category['mapping_square_version'] ?? 0),
        'location_id' => trim((string) ($location['location_id'] ?? '')),
        'location_source' => sanitize_key((string) ($location['source'] ?? '')),
        'eligible' => !empty($eligibility['eligible']) ? 1 : 0,
        'eligibility_reason_code' => sanitize_key((string) ($eligibility['reason_code'] ?? '')),
        'mirror_name' => vms_square_ticket_mirror_compose_name($eligibility),
    );
}

function vms_square_ticket_mirror_source_hash(array $model): string
{
    $hash_input = array(
        'product_id' => absint($model['product_id'] ?? 0),
        'event_plan_id' => absint($model['event_plan_id'] ?? 0),
        'tec_event_id' => absint($model['tec_event_id'] ?? 0),
        'role' => sanitize_key((string) ($model['role'] ?? '')),
        'visibility_mode' => sanitize_key((string) ($model['visibility_mode'] ?? 'public')),
        'verified_program' => sanitize_key((string) ($model['verified_program'] ?? '')),
        'sku' => trim((string) ($model['sku'] ?? '')),
        'price_cents' => max(0, absint($model['price_cents'] ?? 0)),
        'ticket_label' => trim((string) ($model['ticket_label'] ?? '')),
        'event_title' => trim((string) ($model['event_title'] ?? '')),
        'event_date' => trim((string) ($model['event_date'] ?? '')),
        'location_id' => trim((string) ($model['location_id'] ?? '')),
        'category_id' => trim((string) ($model['category_id'] ?? '')),
        'category_name' => trim((string) ($model['category_name'] ?? '')),
        'mirror_name' => trim((string) ($model['mirror_name'] ?? '')),
    );

    return hash('sha256', vms_square_ticket_mirror_json_encode($hash_input));
}

function vms_square_ticket_mirror_status_context(int $product_id): array
{
    $product_id = vms_square_ticket_mirror_canonical_product_id($product_id);
    $stored_status = vms_square_ticket_mirror_normalize_status(vms_square_ticket_mirror_get_meta($product_id, 'square_mirror_status'));
    $item_id = vms_square_ticket_mirror_get_meta($product_id, 'square_mirror_item_id');
    $variation_id = vms_square_ticket_mirror_get_meta($product_id, 'square_mirror_variation_id');
    $stored_source_hash = vms_square_ticket_mirror_get_meta($product_id, 'square_mirror_source_hash');
    $last_error_code = vms_square_ticket_mirror_get_meta($product_id, 'square_mirror_last_error_code');
    $last_error_message = vms_square_ticket_mirror_get_meta($product_id, 'square_mirror_last_error_message');

    $eligibility = vms_square_ticket_mirror_eligibility($product_id);
    $source_model = vms_square_ticket_mirror_build_source_model($product_id);
    $current_source_hash = vms_square_ticket_mirror_source_hash($source_model);

    $computed = 'not_mirrored';
    if ($stored_status === 'mirror_retired') {
        $computed = 'mirror_retired';
    } elseif ($stored_status === 'mirror_error' || $last_error_code !== '' || $last_error_message !== '') {
        $computed = 'mirror_error';
    } elseif ($item_id === '' || $variation_id === '') {
        $computed = vms_square_ticket_mirror_has_mirror_meta($product_id) ? 'mirror_stale' : 'not_mirrored';
    } elseif ($stored_status === 'mirror_stale') {
        $computed = 'mirror_stale';
    } elseif (empty($eligibility['eligible'])) {
        $computed = 'mirror_stale';
    } elseif ($stored_source_hash === '' || $stored_source_hash !== $current_source_hash) {
        $computed = 'mirror_stale';
    } else {
        $computed = 'mirrored';
    }

    return array(
        'product_id' => $product_id,
        'stored_status' => $stored_status,
        'status' => $computed,
        'status_label' => vms_square_ticket_mirror_label_for_status($computed),
        'mode' => vms_square_ticket_mirror_get_meta($product_id, 'square_mirror_mode'),
        'item_id' => $item_id,
        'variation_id' => $variation_id,
        'category_id' => vms_square_ticket_mirror_get_meta($product_id, 'square_mirror_category_id'),
        'location_id' => vms_square_ticket_mirror_get_meta($product_id, 'square_mirror_location_id'),
        'catalog_version' => absint(vms_square_ticket_mirror_get_meta($product_id, 'square_mirror_catalog_version')),
        'stored_source_hash' => $stored_source_hash,
        'current_source_hash' => $current_source_hash,
        'last_sync_gmt' => vms_square_ticket_mirror_get_meta($product_id, 'square_mirror_last_sync_gmt'),
        'last_retired_gmt' => vms_square_ticket_mirror_get_meta($product_id, 'square_mirror_last_retired_gmt'),
        'last_order_stamp_gmt' => vms_square_ticket_mirror_get_meta($product_id, 'square_mirror_last_order_stamp_gmt'),
        'last_error_code' => $last_error_code,
        'last_error_message' => $last_error_message,
        'eligibility' => $eligibility,
        'source_model' => $source_model,
    );
}

function vms_square_ticket_mirror_log(int $product_id, string $action, array $args = array()): void
{
    global $wpdb;

    $table = vms_square_ticket_mirror_log_table_name();
    $product_id = vms_square_ticket_mirror_canonical_product_id($product_id);
    $args = is_array($args) ? $args : array();

    $source_model = is_array($args['source_model'] ?? null) ? (array) $args['source_model'] : vms_square_ticket_mirror_build_source_model($product_id);
    $data = array(
        'product_id' => $product_id,
        'event_plan_id' => absint($args['event_plan_id'] ?? ($source_model['event_plan_id'] ?? 0)),
        'tec_event_id' => absint($args['tec_event_id'] ?? ($source_model['tec_event_id'] ?? 0)),
        'action' => sanitize_key($action),
        'status_before' => sanitize_key((string) ($args['status_before'] ?? '')),
        'status_after' => sanitize_key((string) ($args['status_after'] ?? '')),
        'item_id' => sanitize_text_field((string) ($args['item_id'] ?? '')),
        'variation_id' => sanitize_text_field((string) ($args['variation_id'] ?? '')),
        'location_id' => sanitize_text_field((string) ($args['location_id'] ?? '')),
        'request_json' => isset($args['request_json']) ? (string) $args['request_json'] : '',
        'response_json' => isset($args['response_json']) ? (string) $args['response_json'] : '',
        'error_code' => sanitize_key((string) ($args['error_code'] ?? '')),
        'error_message' => isset($args['error_message']) ? sanitize_text_field((string) $args['error_message']) : '',
        'actor_user_id' => get_current_user_id() ? absint(get_current_user_id()) : null,
        'created_at_gmt' => vms_square_ticket_mirror_now_gmt(),
    );

    $format = array('%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s');
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Append-only Square mirror diagnostics must be inserted immediately so the caller's action/result ordering remains authoritative.
    $wpdb->insert($table, $data, $format);
}

function vms_square_ticket_mirror_recent_logs(int $product_id, int $limit = 8): array
{
    global $wpdb;

    $product_id = vms_square_ticket_mirror_canonical_product_id($product_id);
    $limit = max(1, min(50, absint($limit)));
    if ($product_id <= 0) {
        return array();
    }

    $table = vms_square_ticket_mirror_log_table_name();
    $sql = $wpdb->prepare(
        "SELECT * FROM %i WHERE product_id = %d ORDER BY id DESC LIMIT %d",
        $table,
        $product_id,
        $limit
    );

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Request-fresh diagnostics read the immediately prepared identifier/value query for one product with a limit clamped to 1-50.
    $rows = $wpdb->get_results($sql, ARRAY_A);
    return is_array($rows) ? $rows : array();
}

function vms_square_ticket_mirror_set_error_state(int $product_id, string $error_code, string $error_message, array $args = array()): array
{
    $product_id = vms_square_ticket_mirror_canonical_product_id($product_id);
    $before = vms_square_ticket_mirror_status_context($product_id);

    vms_square_ticket_mirror_update_meta($product_id, 'square_mirror_mode', vms_square_ticket_mirror_mode_value());
    vms_square_ticket_mirror_update_meta($product_id, 'square_mirror_status', 'mirror_error');
    vms_square_ticket_mirror_update_meta($product_id, 'square_mirror_last_error_code', sanitize_key($error_code));
    vms_square_ticket_mirror_update_meta($product_id, 'square_mirror_last_error_message', sanitize_text_field($error_message));

    $after = vms_square_ticket_mirror_status_context($product_id);
    vms_square_ticket_mirror_log($product_id, (string) ($args['action'] ?? 'mirror_error'), array_merge($args, array(
        'status_before' => (string) ($before['status'] ?? ''),
        'status_after' => (string) ($after['status'] ?? 'mirror_error'),
        'item_id' => (string) ($args['item_id'] ?? ($before['item_id'] ?? '')),
        'variation_id' => (string) ($args['variation_id'] ?? ($before['variation_id'] ?? '')),
        'location_id' => (string) ($args['location_id'] ?? ($before['location_id'] ?? '')),
        'error_code' => $error_code,
        'error_message' => $error_message,
        'source_model' => $before['source_model'] ?? array(),
    )));

    return array(
        'ok' => false,
        'product_id' => $product_id,
        'status' => 'mirror_error',
        'error_code' => sanitize_key($error_code),
        'error_message' => sanitize_text_field($error_message),
    );
}

function vms_square_ticket_mirror_clear_error_state(int $product_id): array
{
    $product_id = vms_square_ticket_mirror_canonical_product_id($product_id);
    $before = vms_square_ticket_mirror_status_context($product_id);

    vms_square_ticket_mirror_delete_meta($product_id, 'square_mirror_last_error_code');
    vms_square_ticket_mirror_delete_meta($product_id, 'square_mirror_last_error_message');

    $after = vms_square_ticket_mirror_status_context($product_id);
    vms_square_ticket_mirror_update_meta($product_id, 'square_mirror_status', (string) ($after['status'] ?? 'not_mirrored'));

    vms_square_ticket_mirror_log($product_id, 'mirror_clear_error', array(
        'status_before' => (string) ($before['status'] ?? ''),
        'status_after' => (string) ($after['status'] ?? ''),
        'item_id' => (string) ($before['item_id'] ?? ''),
        'variation_id' => (string) ($before['variation_id'] ?? ''),
        'location_id' => (string) ($before['location_id'] ?? ''),
        'source_model' => $before['source_model'] ?? array(),
    ));

    return array(
        'ok' => true,
        'product_id' => $product_id,
        'status' => (string) ($after['status'] ?? 'not_mirrored'),
    );
}

function vms_square_ticket_mirror_extract_remote_item_state($catalog_object): array
{
    $item_id = is_object($catalog_object) && method_exists($catalog_object, 'getId') ? trim((string) $catalog_object->getId()) : '';
    $item_version = is_object($catalog_object) && method_exists($catalog_object, 'getVersion') ? absint($catalog_object->getVersion()) : 0;
    $variation_id = '';
    $variation_version = 0;

    $item_data = is_object($catalog_object) && method_exists($catalog_object, 'getItemData') ? $catalog_object->getItemData() : null;
    if (is_object($item_data) && method_exists($item_data, 'getVariations')) {
        $variations = (array) $item_data->getVariations();
        if (!empty($variations[0]) && is_object($variations[0])) {
            $variation_object = $variations[0];
            $variation_id = method_exists($variation_object, 'getId') ? trim((string) $variation_object->getId()) : '';
            $variation_version = method_exists($variation_object, 'getVersion') ? absint($variation_object->getVersion()) : 0;
        }
    }

    return array(
        'item_id' => $item_id,
        'item_version' => $item_version,
        'variation_id' => $variation_id,
        'variation_version' => $variation_version,
        'catalog_object' => $catalog_object,
    );
}

function vms_square_ticket_mirror_retrieve_remote_item(string $item_id): array
{
    $item_id = trim($item_id);
    if ($item_id === '') {
        return array(
            'ok' => false,
            'error_code' => 'missing_item_id',
            'error_message' => __('Square item ID is missing.', 'backstage-venue-manager'),
        );
    }

    $square = vms_square_ticket_mirror_get_square_context();
    if (empty($square['ok'])) {
        return array(
            'ok' => false,
            'error_code' => (string) ($square['error_code'] ?? 'square_unavailable'),
            'error_message' => (string) ($square['error_message'] ?? __('WooCommerce Square is unavailable.', 'backstage-venue-manager')),
        );
    }

    try {
        $response = $square['api']->retrieve_catalog_object($item_id, true);
    } catch (Throwable $e) {
        $exception = vms_square_ticket_mirror_square_exception_debug($e);
        $rows = (array) ($exception['errors'] ?? array());
        $first = !empty($rows[0]) && is_array($rows[0]) ? $rows[0] : array();
        return array(
            'ok' => false,
            'error_code' => (string) ($first['code'] ?? 'square_retrieve_failed'),
            'error_message' => (string) ($first['detail'] ?? ($exception['message'] ?? sanitize_text_field($e->getMessage()))),
            'response_json' => vms_square_ticket_mirror_json_encode($exception),
        );
    }

    $summary = vms_square_ticket_mirror_square_error_summary($response);
    if ($summary['code'] !== '') {
        return array(
            'ok' => false,
            'error_code' => (string) $summary['code'],
            'error_message' => (string) $summary['message'],
            'response_json' => vms_square_ticket_mirror_json_encode(array(
                'errors' => (array) ($summary['rows'] ?? array()),
            )),
        );
    }

    $data = $response->get_data();
    $object = is_object($data) && method_exists($data, 'getObject') ? $data->getObject() : null;
    if (!is_object($object) || !method_exists($object, 'getType') || $object->getType() !== 'ITEM') {
        return array(
            'ok' => false,
            'error_code' => 'square_item_missing',
            'error_message' => __('Square mirror item was not found.', 'backstage-venue-manager'),
            'response_json' => vms_square_ticket_mirror_catalog_object_debug_json($object),
        );
    }

    return array_merge(
        array(
            'ok' => true,
            'response_json' => vms_square_ticket_mirror_catalog_object_debug_json($object),
        ),
        vms_square_ticket_mirror_extract_remote_item_state($object)
    );
}

function vms_square_ticket_mirror_build_item_description(array $source_model): string
{
    $parts = array_filter(array(
        'VMS-managed Square ticket mirror.',
        !empty($source_model['event_title']) ? 'Event: ' . $source_model['event_title'] . '.' : '',
        !empty($source_model['ticket_label']) ? 'Ticket: ' . $source_model['ticket_label'] . '.' : '',
        'Source of truth: WooCommerce / VMS.',
        'Do not edit in Square.',
    ));

    return vms_square_ticket_mirror_limit_text(implode(' ', $parts), 1000);
}

function vms_square_ticket_mirror_build_item_object(int $product_id, array $source_model, array $category, array $location, array $remote_state = array())
{
    $existing_item_id = trim((string) ($remote_state['item_id'] ?? ''));
    $existing_item_version = absint($remote_state['item_version'] ?? 0);
    $existing_variation_id = trim((string) ($remote_state['variation_id'] ?? ''));
    $existing_variation_version = absint($remote_state['variation_version'] ?? 0);

    $item_object = new \Square\Models\CatalogObject(
        'ITEM',
        $existing_item_id !== '' ? $existing_item_id : '#vms_ticket_item_' . $product_id
    );
    if ($existing_item_version > 0) {
        $item_object->setVersion($existing_item_version);
    }
    $item_object->setPresentAtAllLocations(false);
    $item_object->setPresentAtLocationIds(array((string) $location['location_id']));
    $item_object->setAbsentAtLocationIds(array());

    $item_data = new \Square\Models\CatalogItem();
    $item_data->setName((string) $source_model['mirror_name']);
    $item_data->setDescriptionHtml(vms_square_ticket_mirror_build_item_description($source_model));
    $item_data->setIsArchived(false);
    $item_data->setAvailableOnline(false);
    $item_data->setAvailableForPickup(false);
    $item_data->setAvailableElectronically(false);

    $category_ref = null;
    $square_category_id = trim((string) ($category['square_id'] ?? ''));
    if ($square_category_id !== '') {
        $category_ref = new \Square\Models\CatalogObjectCategory();
        $category_ref->setId($square_category_id);
        $item_data->setCategories(array($category_ref));
        $item_data->setReportingCategory($category_ref);
    }

    $variation_object = new \Square\Models\CatalogObject(
        'ITEM_VARIATION',
        $existing_variation_id !== '' ? $existing_variation_id : '#vms_ticket_variation_' . $product_id
    );
    if ($existing_variation_version > 0) {
        $variation_object->setVersion($existing_variation_version);
    }
    $variation_object->setPresentAtAllLocations(false);
    $variation_object->setPresentAtLocationIds(array((string) $location['location_id']));
    $variation_object->setAbsentAtLocationIds(array());

    $variation_data = new \Square\Models\CatalogItemVariation();
    $variation_data->setItemId($item_object->getId());
    $variation_data->setName(vms_square_ticket_mirror_limit_text((string) ($source_model['ticket_label'] ?? 'Online Ticket'), 255));
    $variation_data->setSku((string) ($source_model['sku'] ?? ''));
    $variation_data->setPricingType(\Square\Models\CatalogPricingType::FIXED_PRICING);
    $variation_data->setTrackInventory(false);

    $money = new \Square\Models\Money();
    $money->setAmount(max(0, absint($source_model['price_cents'] ?? 0)));
    $money->setCurrency((string) ($source_model['currency'] ?? 'USD'));
    $variation_data->setPriceMoney($money);
    $variation_object->setItemVariationData($variation_data);

    $item_data->setVariations(array($variation_object));
    $item_object->setItemData($item_data);

    return $item_object;
}

function vms_square_ticket_mirror_sync_product(int $product_id, array $args = array()): array
{
    $product_id = vms_square_ticket_mirror_canonical_product_id($product_id);
    $before = vms_square_ticket_mirror_status_context($product_id);
    $eligibility = (array) ($before['eligibility'] ?? array());

    if (empty($eligibility['eligible'])) {
        $message = (string) ($eligibility['reason_message'] ?? __('Ticket is not eligible for Square mirroring.', 'backstage-venue-manager'));
        vms_square_ticket_mirror_log($product_id, 'mirror_skip', array(
            'status_before' => (string) ($before['status'] ?? ''),
            'status_after' => (string) ($before['status'] ?? ''),
            'item_id' => (string) ($before['item_id'] ?? ''),
            'variation_id' => (string) ($before['variation_id'] ?? ''),
            'location_id' => (string) ($before['location_id'] ?? ''),
            'error_code' => sanitize_key((string) ($eligibility['reason_code'] ?? 'not_eligible')),
            'error_message' => $message,
            'request_json' => vms_square_ticket_mirror_json_encode($before['source_model'] ?? array()),
            'source_model' => $before['source_model'] ?? array(),
        ));

        return array(
            'ok' => false,
            'product_id' => $product_id,
            'error_code' => sanitize_key((string) ($eligibility['reason_code'] ?? 'not_eligible')),
            'error_message' => $message,
        );
    }

    $category = vms_square_ticket_mirror_resolve_square_category(true);
    if (empty($category['ok']) || trim((string) ($category['square_id'] ?? '')) === '') {
        return vms_square_ticket_mirror_set_error_state(
            $product_id,
            sanitize_key((string) ($category['error_code'] ?? 'square_category_unavailable')),
            (string) ($category['error_message'] ?? __('Square category could not be resolved.', 'backstage-venue-manager')),
            array(
                'action' => 'mirror_error',
                'request_json' => vms_square_ticket_mirror_json_encode($before['source_model'] ?? array()),
                'response_json' => vms_square_ticket_mirror_json_encode($category),
                'source_model' => $before['source_model'] ?? array(),
            )
        );
    }

    $location = vms_square_ticket_mirror_resolve_location(absint($eligibility['event_plan_id'] ?? 0), true);
    if (empty($location['ok'])) {
        return vms_square_ticket_mirror_set_error_state(
            $product_id,
            sanitize_key((string) ($location['error_code'] ?? 'square_location_unavailable')),
            (string) ($location['error_message'] ?? __('Square location could not be resolved.', 'backstage-venue-manager')),
            array(
                'action' => 'mirror_error',
                'request_json' => vms_square_ticket_mirror_json_encode($before['source_model'] ?? array()),
                'response_json' => vms_square_ticket_mirror_json_encode($location),
                'source_model' => $before['source_model'] ?? array(),
            )
        );
    }

    $source_model = vms_square_ticket_mirror_build_source_model($product_id);
    $source_model['category_id'] = (string) ($category['square_id'] ?? '');
    $source_model['category_term_id'] = absint($category['term_id'] ?? 0);
    $source_model['category_name'] = (string) ($category['name'] ?? vms_square_ticket_mirror_target_category_name());
    $source_model['category_resolution_path'] = sanitize_key((string) ($category['resolution_path'] ?? ''));
    $source_model['category_mapping_square_id'] = trim((string) ($category['mapping_square_id'] ?? ''));
    $source_model['category_mapping_square_version'] = absint($category['mapping_square_version'] ?? 0);
    $source_model['location_id'] = (string) ($location['location_id'] ?? '');
    $source_model['location_source'] = (string) ($location['source'] ?? '');
    $source_hash = vms_square_ticket_mirror_source_hash($source_model);

    $remote_state = array();
    $stored_item_id = trim((string) ($before['item_id'] ?? ''));
    if ($stored_item_id !== '') {
        $retrieved_remote_state = vms_square_ticket_mirror_retrieve_remote_item($stored_item_id);
        if (empty($retrieved_remote_state['ok'])) {
            if ((string) ($retrieved_remote_state['error_code'] ?? '') !== 'square_item_missing') {
                return vms_square_ticket_mirror_set_error_state(
                    $product_id,
                    sanitize_key((string) ($retrieved_remote_state['error_code'] ?? 'square_retrieve_failed')),
                    (string) ($retrieved_remote_state['error_message'] ?? __('Square mirror item could not be retrieved.', 'backstage-venue-manager')),
                    array(
                        'action' => 'mirror_error',
                        'item_id' => $stored_item_id,
                        'variation_id' => (string) ($before['variation_id'] ?? ''),
                        'request_json' => vms_square_ticket_mirror_json_encode($source_model),
                        'response_json' => isset($retrieved_remote_state['response_json']) ? (string) $retrieved_remote_state['response_json'] : '',
                        'source_model' => $source_model,
                    )
                );
            }
        } else {
            $remote_state = $retrieved_remote_state;
        }
    }

    $square = vms_square_ticket_mirror_get_square_context();
    if (empty($square['ok'])) {
        return vms_square_ticket_mirror_set_error_state(
            $product_id,
            sanitize_key((string) ($square['error_code'] ?? 'square_unavailable')),
            (string) ($square['error_message'] ?? __('WooCommerce Square is unavailable.', 'backstage-venue-manager')),
            array(
                'action' => 'mirror_error',
                'request_json' => vms_square_ticket_mirror_json_encode($source_model),
                'source_model' => $source_model,
            )
        );
    }

    $catalog_object = vms_square_ticket_mirror_build_item_object($product_id, $source_model, $category, $location, $remote_state);
    $request_json = vms_square_ticket_mirror_catalog_object_debug_json($catalog_object);

    try {
        $response = $square['api']->upsert_catalog_object(
            function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('vmsstm_', true),
            $catalog_object
        );
    } catch (Throwable $e) {
        $exception = vms_square_ticket_mirror_square_exception_debug($e);
        $rows = (array) ($exception['errors'] ?? array());
        $first = !empty($rows[0]) && is_array($rows[0]) ? $rows[0] : array();
        return vms_square_ticket_mirror_set_error_state(
            $product_id,
            sanitize_key((string) ($first['code'] ?? 'square_upsert_failed')),
            (string) ($first['detail'] ?? ($exception['message'] ?? sanitize_text_field($e->getMessage()))),
            array(
                'action' => 'mirror_error',
                'request_json' => $request_json,
                'response_json' => vms_square_ticket_mirror_json_encode($exception),
                'source_model' => $source_model,
            )
        );
    }

    $summary = vms_square_ticket_mirror_square_error_summary($response);
    if ($summary['code'] !== '') {
        return vms_square_ticket_mirror_set_error_state(
            $product_id,
            (string) $summary['code'],
            (string) $summary['message'],
            array(
                'action' => 'mirror_error',
                'request_json' => $request_json,
                'response_json' => vms_square_ticket_mirror_json_encode(array(
                    'errors' => (array) ($summary['rows'] ?? array()),
                )),
                'source_model' => $source_model,
            )
        );
    }

    $data = $response->get_data();
    $remote_object = is_object($data) && method_exists($data, 'getCatalogObject') ? $data->getCatalogObject() : null;
    $remote = vms_square_ticket_mirror_extract_remote_item_state($remote_object);
    if (empty($remote['item_id']) || empty($remote['variation_id'])) {
        return vms_square_ticket_mirror_set_error_state(
            $product_id,
            'square_missing_ids',
            __('Square did not return both item and variation IDs for the ticket mirror.', 'backstage-venue-manager'),
            array(
                'action' => 'mirror_error',
                'request_json' => $request_json,
                'response_json' => vms_square_ticket_mirror_catalog_object_debug_json($remote_object),
                'source_model' => $source_model,
            )
        );
    }

    vms_square_ticket_mirror_update_meta($product_id, 'square_mirror_mode', vms_square_ticket_mirror_mode_value());
    vms_square_ticket_mirror_update_meta($product_id, 'square_mirror_item_id', (string) $remote['item_id']);
    vms_square_ticket_mirror_update_meta($product_id, 'square_mirror_variation_id', (string) $remote['variation_id']);
    vms_square_ticket_mirror_update_meta($product_id, 'square_mirror_category_id', (string) ($category['square_id'] ?? ''));
    vms_square_ticket_mirror_update_meta($product_id, 'square_mirror_location_id', (string) ($location['location_id'] ?? ''));
    vms_square_ticket_mirror_update_meta($product_id, 'square_mirror_catalog_version', (string) absint($remote['item_version'] ?? 0));
    vms_square_ticket_mirror_update_meta($product_id, 'square_mirror_source_hash', $source_hash);
    vms_square_ticket_mirror_update_meta($product_id, 'square_mirror_last_sync_gmt', vms_square_ticket_mirror_now_gmt());
    vms_square_ticket_mirror_update_meta($product_id, 'square_mirror_status', 'mirrored');
    vms_square_ticket_mirror_delete_meta($product_id, 'square_mirror_last_error_code');
    vms_square_ticket_mirror_delete_meta($product_id, 'square_mirror_last_error_message');

    $after = vms_square_ticket_mirror_status_context($product_id);
    $action = !empty($remote_state['item_id']) ? 'mirror_update' : 'mirror_create';
    vms_square_ticket_mirror_log($product_id, $action, array(
        'status_before' => (string) ($before['status'] ?? ''),
        'status_after' => (string) ($after['status'] ?? 'mirrored'),
        'item_id' => (string) ($remote['item_id'] ?? ''),
        'variation_id' => (string) ($remote['variation_id'] ?? ''),
        'location_id' => (string) ($location['location_id'] ?? ''),
        'request_json' => $request_json,
        'response_json' => vms_square_ticket_mirror_catalog_object_debug_json($remote_object),
        'source_model' => $source_model,
    ));

    return array(
        'ok' => true,
        'product_id' => $product_id,
        'status' => 'mirrored',
        'action' => $action,
        'item_id' => (string) ($remote['item_id'] ?? ''),
        'variation_id' => (string) ($remote['variation_id'] ?? ''),
        'location_id' => (string) ($location['location_id'] ?? ''),
        'category_id' => (string) ($category['square_id'] ?? ''),
    );
}

function vms_square_ticket_mirror_retire_product(int $product_id): array
{
    $product_id = vms_square_ticket_mirror_canonical_product_id($product_id);
    $before = vms_square_ticket_mirror_status_context($product_id);
    $item_id = trim((string) ($before['item_id'] ?? ''));

    if ($item_id === '') {
        return array(
            'ok' => false,
            'product_id' => $product_id,
            'error_code' => 'missing_item_id',
            'error_message' => __('Square mirror item ID is missing.', 'backstage-venue-manager'),
        );
    }

    $remote_state = vms_square_ticket_mirror_retrieve_remote_item($item_id);
    $location_id = trim((string) ($before['location_id'] ?? ''));
    if ($location_id === '') {
        $location = vms_square_ticket_mirror_resolve_location(absint($before['source_model']['event_plan_id'] ?? 0), true);
        $location_id = trim((string) ($location['location_id'] ?? ''));
    }

    if ($location_id === '') {
        return vms_square_ticket_mirror_set_error_state(
            $product_id,
            'square_location_missing',
            __('Square mirror location is missing.', 'backstage-venue-manager'),
            array(
                'action' => 'mirror_error',
                'item_id' => $item_id,
                'variation_id' => (string) ($before['variation_id'] ?? ''),
                'source_model' => $before['source_model'] ?? array(),
            )
        );
    }

    $square = vms_square_ticket_mirror_get_square_context();
    if (empty($square['ok'])) {
        return vms_square_ticket_mirror_set_error_state(
            $product_id,
            sanitize_key((string) ($square['error_code'] ?? 'square_unavailable')),
            (string) ($square['error_message'] ?? __('WooCommerce Square is unavailable.', 'backstage-venue-manager')),
            array(
                'action' => 'mirror_error',
                'item_id' => $item_id,
                'variation_id' => (string) ($before['variation_id'] ?? ''),
                'source_model' => $before['source_model'] ?? array(),
            )
        );
    }

    if (empty($remote_state['ok']) && (string) ($remote_state['error_code'] ?? '') === 'square_item_missing') {
        vms_square_ticket_mirror_update_meta($product_id, 'square_mirror_status', 'mirror_retired');
        vms_square_ticket_mirror_update_meta($product_id, 'square_mirror_last_retired_gmt', vms_square_ticket_mirror_now_gmt());
        vms_square_ticket_mirror_update_meta($product_id, 'square_mirror_last_sync_gmt', vms_square_ticket_mirror_now_gmt());
        vms_square_ticket_mirror_delete_meta($product_id, 'square_mirror_last_error_code');
        vms_square_ticket_mirror_delete_meta($product_id, 'square_mirror_last_error_message');

        $after = vms_square_ticket_mirror_status_context($product_id);
        vms_square_ticket_mirror_log($product_id, 'mirror_retire_missing_remote', array(
            'status_before' => (string) ($before['status'] ?? ''),
            'status_after' => (string) ($after['status'] ?? 'mirror_retired'),
            'item_id' => $item_id,
            'variation_id' => (string) ($before['variation_id'] ?? ''),
            'location_id' => $location_id,
            'error_code' => sanitize_key((string) ($remote_state['error_code'] ?? 'square_item_missing')),
            'error_message' => (string) ($remote_state['error_message'] ?? __('Square mirror item was already absent.', 'backstage-venue-manager')),
            'response_json' => isset($remote_state['response_json']) ? (string) $remote_state['response_json'] : '',
            'source_model' => $before['source_model'] ?? array(),
        ));

        return array(
            'ok' => true,
            'product_id' => $product_id,
            'status' => 'mirror_retired',
            'item_id' => $item_id,
            'variation_id' => (string) ($before['variation_id'] ?? ''),
            'location_id' => $location_id,
        );
    }

    if (empty($remote_state['ok'])) {
        return vms_square_ticket_mirror_set_error_state(
            $product_id,
            sanitize_key((string) ($remote_state['error_code'] ?? 'square_retrieve_failed')),
            (string) ($remote_state['error_message'] ?? __('Square mirror item could not be retrieved.', 'backstage-venue-manager')),
            array(
                'action' => 'mirror_error',
                'item_id' => $item_id,
                'variation_id' => (string) ($before['variation_id'] ?? ''),
                'location_id' => $location_id,
                'response_json' => isset($remote_state['response_json']) ? (string) $remote_state['response_json'] : '',
                'source_model' => $before['source_model'] ?? array(),
            )
        );
    }

    $catalog_object = $remote_state['catalog_object'];
    $catalog_object->setPresentAtAllLocations(false);
    $catalog_object->setPresentAtLocationIds(array());
    $catalog_object->setAbsentAtLocationIds(array($location_id));

    $item_data = is_object($catalog_object) && method_exists($catalog_object, 'getItemData') ? $catalog_object->getItemData() : null;
    if (is_object($item_data) && method_exists($item_data, 'setIsArchived')) {
        if (method_exists($item_data, 'getVariations')) {
            foreach ((array) $item_data->getVariations() as $variation_object) {
                if (!is_object($variation_object)) {
                    continue;
                }
                if (method_exists($variation_object, 'setPresentAtAllLocations')) {
                    $variation_object->setPresentAtAllLocations(false);
                }
                if (method_exists($variation_object, 'setPresentAtLocationIds')) {
                    $variation_object->setPresentAtLocationIds(array());
                }
                if (method_exists($variation_object, 'setAbsentAtLocationIds')) {
                    $variation_object->setAbsentAtLocationIds(array($location_id));
                }
            }
        }
        $item_data->setIsArchived(true);
        $catalog_object->setItemData($item_data);
    }

    $request_json = vms_square_ticket_mirror_catalog_object_debug_json($catalog_object);

    try {
        $response = $square['api']->upsert_catalog_object(
            function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('vmsstmret_', true),
            $catalog_object
        );
    } catch (Throwable $e) {
        $exception = vms_square_ticket_mirror_square_exception_debug($e);
        $rows = (array) ($exception['errors'] ?? array());
        $first = !empty($rows[0]) && is_array($rows[0]) ? $rows[0] : array();
        return vms_square_ticket_mirror_set_error_state(
            $product_id,
            sanitize_key((string) ($first['code'] ?? 'square_retire_failed')),
            (string) ($first['detail'] ?? ($exception['message'] ?? sanitize_text_field($e->getMessage()))),
            array(
                'action' => 'mirror_error',
                'item_id' => $item_id,
                'variation_id' => (string) ($before['variation_id'] ?? ''),
                'location_id' => $location_id,
                'request_json' => $request_json,
                'response_json' => vms_square_ticket_mirror_json_encode($exception),
                'source_model' => $before['source_model'] ?? array(),
            )
        );
    }

    $summary = vms_square_ticket_mirror_square_error_summary($response);
    if ($summary['code'] !== '') {
        return vms_square_ticket_mirror_set_error_state(
            $product_id,
            (string) $summary['code'],
            (string) $summary['message'],
            array(
                'action' => 'mirror_error',
                'item_id' => $item_id,
                'variation_id' => (string) ($before['variation_id'] ?? ''),
                'location_id' => $location_id,
                'request_json' => $request_json,
                'response_json' => vms_square_ticket_mirror_json_encode(array(
                    'errors' => (array) ($summary['rows'] ?? array()),
                )),
                'source_model' => $before['source_model'] ?? array(),
            )
        );
    }

    vms_square_ticket_mirror_update_meta($product_id, 'square_mirror_status', 'mirror_retired');
    vms_square_ticket_mirror_update_meta($product_id, 'square_mirror_last_retired_gmt', vms_square_ticket_mirror_now_gmt());
    vms_square_ticket_mirror_update_meta($product_id, 'square_mirror_last_sync_gmt', vms_square_ticket_mirror_now_gmt());
    vms_square_ticket_mirror_update_meta($product_id, 'square_mirror_location_id', $location_id);
    vms_square_ticket_mirror_delete_meta($product_id, 'square_mirror_last_error_code');
    vms_square_ticket_mirror_delete_meta($product_id, 'square_mirror_last_error_message');

    $after = vms_square_ticket_mirror_status_context($product_id);
    $retired_data = $response->get_data();
    $retired_object = is_object($retired_data) && method_exists($retired_data, 'getCatalogObject') ? $retired_data->getCatalogObject() : null;
    vms_square_ticket_mirror_log($product_id, 'mirror_retire', array(
        'status_before' => (string) ($before['status'] ?? ''),
        'status_after' => (string) ($after['status'] ?? 'mirror_retired'),
        'item_id' => $item_id,
        'variation_id' => (string) ($before['variation_id'] ?? ''),
        'location_id' => $location_id,
        'request_json' => $request_json,
        'response_json' => vms_square_ticket_mirror_catalog_object_debug_json($retired_object),
        'source_model' => $before['source_model'] ?? array(),
    ));

    return array(
        'ok' => true,
        'product_id' => $product_id,
        'status' => 'mirror_retired',
        'item_id' => $item_id,
        'variation_id' => (string) ($before['variation_id'] ?? ''),
        'location_id' => $location_id,
    );
}

function vms_square_ticket_mirror_should_log_order_item_skip(int $product_id): bool
{
    $product_id = vms_square_ticket_mirror_canonical_product_id($product_id);
    if ($product_id <= 0) {
        return false;
    }

    if (vms_square_ticket_mirror_has_mirror_meta($product_id)) {
        return true;
    }

    if (function_exists('vms_square_firewall_is_protected_product') && vms_square_firewall_is_protected_product($product_id)) {
        return true;
    }

    return in_array(vms_square_ticket_mirror_product_role($product_id), vms_square_ticket_mirror_included_roles(), true);
}

function vms_square_ticket_mirror_order_item_stamp_decision(int $product_id, string $existing_square_variation_id = ''): array
{
    $product_id = vms_square_ticket_mirror_canonical_product_id($product_id);
    $state = vms_square_ticket_mirror_status_context($product_id);
    $eligibility = (array) ($state['eligibility'] ?? array());

    $decision = array(
        'apply' => false,
        'product_id' => $product_id,
        'mirror_variation_id' => trim((string) ($state['variation_id'] ?? '')),
        'status' => (string) ($state['status'] ?? 'not_mirrored'),
        'reason_code' => '',
        'reason_message' => '',
        'log_skip' => vms_square_ticket_mirror_should_log_order_item_skip($product_id),
        'source_model' => $state['source_model'] ?? array(),
    );

    if ($product_id <= 0) {
        $decision['reason_code'] = 'invalid_product';
        $decision['reason_message'] = __('Order item does not resolve to a product.', 'backstage-venue-manager');
        return $decision;
    }

    if (empty($eligibility['eligible'])) {
        $decision['reason_code'] = sanitize_key((string) ($eligibility['reason_code'] ?? 'not_eligible'));
        $decision['reason_message'] = (string) ($eligibility['reason_message'] ?? __('Product is not eligible for ticket mirroring.', 'backstage-venue-manager'));
        return $decision;
    }

    if ((string) ($state['status'] ?? '') !== 'mirrored') {
        $decision['reason_code'] = 'mirror_status_' . sanitize_key((string) ($state['status'] ?? 'not_mirrored'));
        $decision['reason_message'] = sprintf(
            /* translators: %s: status label. */
            __('Mirror status is %s, so the order item was not stamped.', 'backstage-venue-manager'),
            vms_square_ticket_mirror_label_for_status((string) ($state['status'] ?? 'not_mirrored'))
        );
        return $decision;
    }

    if ($decision['mirror_variation_id'] === '') {
        $decision['reason_code'] = 'missing_variation_id';
        $decision['reason_message'] = __('Mirror variation ID is missing.', 'backstage-venue-manager');
        return $decision;
    }

    if ($existing_square_variation_id !== '' && $existing_square_variation_id === $decision['mirror_variation_id']) {
        $decision['reason_code'] = 'already_stamped';
        $decision['reason_message'] = __('Order item already carries the correct Square variation ID.', 'backstage-venue-manager');
        return $decision;
    }

    $decision['apply'] = true;
    return $decision;
}

function vms_square_ticket_mirror_maybe_stamp_checkout_item($item, array $context = array()): array
{
    if (!($item instanceof WC_Order_Item_Product)) {
        return array(
            'applied' => false,
            'reason_code' => 'not_product_item',
            'reason_message' => __('Order item is not a product line item.', 'backstage-venue-manager'),
        );
    }

    $product_id = absint($item->get_variation_id());
    if ($product_id <= 0) {
        $product_id = absint($item->get_product_id());
    }

    $existing = trim((string) $item->get_meta('_square_item_variation_id', true));
    $decision = vms_square_ticket_mirror_order_item_stamp_decision($product_id, $existing);

    if (!empty($decision['apply'])) {
        $item->update_meta_data('_square_item_variation_id', (string) $decision['mirror_variation_id']);
        $item->update_meta_data('_vms_square_mirror_stamped', '1');
        vms_square_ticket_mirror_update_meta($decision['product_id'], 'square_mirror_last_order_stamp_gmt', vms_square_ticket_mirror_now_gmt());

        vms_square_ticket_mirror_log($decision['product_id'], 'order_item_stamp', array(
            'status_before' => (string) ($decision['status'] ?? ''),
            'status_after' => (string) ($decision['status'] ?? ''),
            'variation_id' => (string) ($decision['mirror_variation_id'] ?? ''),
            'location_id' => sanitize_text_field((string) ($context['location_id'] ?? '')),
            'request_json' => vms_square_ticket_mirror_json_encode(array_merge($context, array(
                'existing_square_item_variation_id' => $existing,
                'product_id' => $decision['product_id'],
            ))),
            'response_json' => vms_square_ticket_mirror_json_encode(array(
                'stamped_square_item_variation_id' => (string) ($decision['mirror_variation_id'] ?? ''),
            )),
            'source_model' => $decision['source_model'] ?? array(),
        ));

        return array(
            'applied' => true,
            'product_id' => $decision['product_id'],
            'variation_id' => (string) ($decision['mirror_variation_id'] ?? ''),
        );
    }

    if (!empty($decision['log_skip'])) {
        vms_square_ticket_mirror_log($decision['product_id'], 'order_item_skip', array(
            'status_before' => (string) ($decision['status'] ?? ''),
            'status_after' => (string) ($decision['status'] ?? ''),
            'variation_id' => (string) ($decision['mirror_variation_id'] ?? ''),
            'request_json' => vms_square_ticket_mirror_json_encode(array_merge($context, array(
                'existing_square_item_variation_id' => $existing,
                'product_id' => $decision['product_id'],
            ))),
            'error_code' => (string) ($decision['reason_code'] ?? 'skip'),
            'error_message' => (string) ($decision['reason_message'] ?? __('Order item was not stamped.', 'backstage-venue-manager')),
            'source_model' => $decision['source_model'] ?? array(),
        ));
    }

    return array(
        'applied' => false,
        'product_id' => $decision['product_id'] ?? 0,
        'reason_code' => (string) ($decision['reason_code'] ?? ''),
        'reason_message' => (string) ($decision['reason_message'] ?? ''),
    );
}

function vms_square_ticket_mirror_stamp_checkout_line_item($item, $cart_item_key, $values, $order): void
{
    unset($cart_item_key, $values);
    $context = array(
        'hook' => 'woocommerce_checkout_create_order_line_item',
        'order_id' => is_object($order) && method_exists($order, 'get_id') ? absint($order->get_id()) : 0,
    );
    vms_square_ticket_mirror_maybe_stamp_checkout_item($item, $context);
}
add_action('woocommerce_checkout_create_order_line_item', 'vms_square_ticket_mirror_stamp_checkout_line_item', 5, 4);

function vms_square_ticket_mirror_stamp_new_order_item($item_id, $item, $order_id): void
{
    $item_id = absint($item_id);
    if ($item_id <= 0 || !($item instanceof WC_Order_Item_Product)) {
        return;
    }

    $already_stamped = trim((string) wc_get_order_item_meta($item_id, '_vms_square_mirror_stamped', true));
    $existing = trim((string) wc_get_order_item_meta($item_id, '_square_item_variation_id', true));
    if ($already_stamped === '1' && $existing !== '') {
        return;
    }

    $product_id = absint($item->get_variation_id());
    if ($product_id <= 0) {
        $product_id = absint($item->get_product_id());
    }

    $decision = vms_square_ticket_mirror_order_item_stamp_decision($product_id, $existing);
    if (!empty($decision['apply'])) {
        wc_update_order_item_meta($item_id, '_square_item_variation_id', (string) $decision['mirror_variation_id']);
        wc_update_order_item_meta($item_id, '_vms_square_mirror_stamped', '1');
        vms_square_ticket_mirror_update_meta($decision['product_id'], 'square_mirror_last_order_stamp_gmt', vms_square_ticket_mirror_now_gmt());

        vms_square_ticket_mirror_log($decision['product_id'], 'order_item_stamp', array(
            'status_before' => (string) ($decision['status'] ?? ''),
            'status_after' => (string) ($decision['status'] ?? ''),
            'variation_id' => (string) ($decision['mirror_variation_id'] ?? ''),
            'request_json' => vms_square_ticket_mirror_json_encode(array(
                'hook' => 'woocommerce_new_order_item',
                'order_id' => absint($order_id),
                'order_item_id' => $item_id,
                'existing_square_item_variation_id' => $existing,
                'product_id' => $decision['product_id'],
            )),
            'response_json' => vms_square_ticket_mirror_json_encode(array(
                'stamped_square_item_variation_id' => (string) ($decision['mirror_variation_id'] ?? ''),
            )),
            'source_model' => $decision['source_model'] ?? array(),
        ));
        return;
    }

    if (!empty($decision['log_skip'])) {
        vms_square_ticket_mirror_log($decision['product_id'], 'order_item_skip', array(
            'status_before' => (string) ($decision['status'] ?? ''),
            'status_after' => (string) ($decision['status'] ?? ''),
            'variation_id' => (string) ($decision['mirror_variation_id'] ?? ''),
            'request_json' => vms_square_ticket_mirror_json_encode(array(
                'hook' => 'woocommerce_new_order_item',
                'order_id' => absint($order_id),
                'order_item_id' => $item_id,
                'existing_square_item_variation_id' => $existing,
                'product_id' => $decision['product_id'],
            )),
            'error_code' => (string) ($decision['reason_code'] ?? 'skip'),
            'error_message' => (string) ($decision['reason_message'] ?? __('Order item was not stamped.', 'backstage-venue-manager')),
            'source_model' => $decision['source_model'] ?? array(),
        ));
    }
}
add_action('woocommerce_new_order_item', 'vms_square_ticket_mirror_stamp_new_order_item', 1, 3);
