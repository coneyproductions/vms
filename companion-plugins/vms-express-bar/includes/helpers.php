<?php
defined('ABSPATH') || exit;

if (!function_exists('vmseb_is_vms_active')) {
    function vmseb_is_vms_active(): bool
    {
        return defined('BVMGR_PLUGIN_FILE')
            || defined('BVMGR_VERSION')
            || defined('VMS_PLUGIN_FILE')
            || defined('VMS_VERSION')
            || post_type_exists('vms_event_plan');
    }
}

if (!function_exists('vmseb_is_woocommerce_active')) {
    function vmseb_is_woocommerce_active(): bool
    {
        return class_exists('WooCommerce');
    }
}

if (!function_exists('vmseb_parent_menu_slug')) {
    function vmseb_parent_menu_slug(): string
    {
        return 'vms-dashboard';
    }
}

if (!function_exists('vmseb_default_settings')) {
    function vmseb_default_settings(): array
    {
        return array(
            'headline'              => 'Skip the line. Build your order here and pick it up at the bar.',
            'pickup_instructions'   => 'Bring your order confirmation and a valid ID. ID is checked in person before handoff.',
            'bucket_product_id'     => 0,
            'bucket_group_size'     => 4,
            'bucket_discount_label' => 'Eligible discounts are applied in cart/checkout.',
            'bucket_emoji'          => '🪣',
            'age_gate_enabled'      => 1,
            'age_gate_min_age'      => 21,
            'age_gate_title'        => 'Before ordering alcohol',
            'age_gate_message'      => 'Enter your birthday. Orders for alcohol require age confirmation here and a valid ID at pickup.',
            // Site-level relative ordering-window defaults. Explicit per-event windows still win.
            'default_window_auto_apply'           => 1,
            'default_window_open_minutes_before'  => 2880,
            'default_window_close_minutes_before' => 0,
            // Express Bar owns its checkout gratuity prompt.
            'tips_enabled'           => 1,
            'tips_max_custom_amount' => 100,
        );
    }
}

if (!function_exists('vmseb_get_settings')) {
    function vmseb_get_settings(): array
    {
        static $cached = null;
        if (is_array($cached)) {
            return $cached;
        }

        $stored = get_option('vmseb_settings', array());
        if (!is_array($stored)) {
            $stored = array();
        }

        // Soft migration from older defaults option.
        $legacy = get_option('vmseb_bar_menu_defaults', array());
        if (is_array($legacy)) {
            if (empty($stored['headline']) && !empty($legacy['headline'])) {
                $stored['headline'] = (string) $legacy['headline'];
            }
            if (empty($stored['pickup_instructions']) && !empty($legacy['pickup_instructions'])) {
                $stored['pickup_instructions'] = (string) $legacy['pickup_instructions'];
            }
            if (empty($stored['bucket_product_id']) && !empty($legacy['beer_bucket_product_id'])) {
                $stored['bucket_product_id'] = absint($legacy['beer_bucket_product_id']);
            }
            if (empty($stored['bucket_group_size']) && !empty($legacy['bucket_pick_count'])) {
                $stored['bucket_group_size'] = absint($legacy['bucket_pick_count']);
            }
        }

        $settings = array_merge(vmseb_default_settings(), $stored);
        $settings['headline'] = trim((string) ($settings['headline'] ?? ''));
        $settings['pickup_instructions'] = trim((string) ($settings['pickup_instructions'] ?? ''));
        $settings['bucket_product_id'] = absint($settings['bucket_product_id'] ?? 0);
        $settings['bucket_group_size'] = max(1, min(24, absint($settings['bucket_group_size'] ?? 4) ?: 4));
        $settings['bucket_discount_label'] = trim((string) ($settings['bucket_discount_label'] ?? ''));
        if ($settings['bucket_discount_label'] === '') {
            $settings['bucket_discount_label'] = 'Eligible discounts are applied in cart/checkout.';
        }
        $settings['bucket_emoji'] = trim((string) ($settings['bucket_emoji'] ?? ''));
        if ($settings['bucket_emoji'] === '') {
            $settings['bucket_emoji'] = '🪣';
        }
        $settings['age_gate_enabled'] = !empty($settings['age_gate_enabled']) ? 1 : 0;
        $settings['age_gate_min_age'] = max(1, min(99, absint($settings['age_gate_min_age'] ?? 21) ?: 21));
        $settings['age_gate_title'] = trim((string) ($settings['age_gate_title'] ?? ''));
        if ($settings['age_gate_title'] === '') {
            $settings['age_gate_title'] = 'Before ordering alcohol';
        }
        $settings['age_gate_message'] = trim((string) ($settings['age_gate_message'] ?? ''));
        if ($settings['age_gate_message'] === '') {
            $settings['age_gate_message'] = 'Enter your birthday. Orders for alcohol require age confirmation here and a valid ID at pickup.';
        }
        $settings['default_window_auto_apply'] = !empty($settings['default_window_auto_apply']) ? 1 : 0;
        $settings['default_window_open_minutes_before'] = max(0, min(10080, absint($settings['default_window_open_minutes_before'] ?? 2880)));
        $settings['default_window_close_minutes_before'] = max(0, min(1440, absint($settings['default_window_close_minutes_before'] ?? 0)));
        $settings['tips_enabled'] = !empty($settings['tips_enabled']) ? 1 : 0;
        $settings['tips_max_custom_amount'] = (float) ($settings['tips_max_custom_amount'] ?? 100);
        if ($settings['tips_max_custom_amount'] <= 0) {
            $settings['tips_max_custom_amount'] = 100.0;
        }
        $settings['tips_max_custom_amount'] = round(min(500.0, $settings['tips_max_custom_amount']), function_exists('wc_get_price_decimals') ? wc_get_price_decimals() : 2);

        $cached = $settings;
        return $settings;
    }
}


if (!function_exists('vmseb_get_selected_public_page_id')) {
    function vmseb_get_selected_public_page_id(): int
    {
        return absint(get_option('vmseb_public_page_id', 0));
    }
}

if (!function_exists('vmseb_page_contains_shortcode')) {
    function vmseb_page_contains_shortcode(int $page_id): bool
    {
        if ($page_id <= 0) {
            return false;
        }
        $post = get_post($page_id);
        if (!$post instanceof WP_Post || $post->post_type !== 'page' || $post->post_status === 'trash') {
            return false;
        }
        $content = (string) $post->post_content;
        return has_shortcode($content, 'vms_express_bar_menu') || strpos($content, '[vms_express_bar_menu') !== false;
    }
}

if (!function_exists('vmseb_set_public_page_id')) {
    function vmseb_set_public_page_id(int $page_id): void
    {
        $page_id = absint($page_id);
        if ($page_id <= 0) {
            delete_option('vmseb_public_page_id');
            return;
        }
        $post = get_post($page_id);
        if ($post instanceof WP_Post && $post->post_type === 'page' && $post->post_status !== 'trash') {
            update_option('vmseb_public_page_id', $page_id, false);
        }
    }
}

if (!function_exists('vmseb_find_public_page_id')) {
    function vmseb_find_public_page_id(bool $allow_content_scan = false): int
    {
        static $cache = array();
        $cache_key = $allow_content_scan ? 'scan' : 'fast';
        if (array_key_exists($cache_key, $cache)) {
            return (int) $cache[$cache_key];
        }

        $selected = vmseb_get_selected_public_page_id();
        if ($selected > 0) {
            $post = get_post($selected);
            if ($post instanceof WP_Post && $post->post_type === 'page' && $post->post_status !== 'trash') {
                $cache[$cache_key] = $selected;
                return $selected;
            }
        }

        $by_slug = get_page_by_path('express-bar', OBJECT, 'page');
        if ($by_slug instanceof WP_Post && $by_slug->post_status !== 'trash') {
            vmseb_set_public_page_id((int) $by_slug->ID);
            $cache[$cache_key] = (int) $by_slug->ID;
            return (int) $by_slug->ID;
        }

        if ($allow_content_scan) {
            $pages = get_posts(array(
                'post_type'        => 'page',
                'post_status'      => array('publish', 'draft', 'pending', 'private', 'future'),
                'posts_per_page'   => 50,
                'orderby'          => 'modified',
                'order'            => 'DESC',
                'suppress_filters' => false,
                'no_found_rows'    => true,
            ));
            foreach ($pages as $page) {
                if ($page instanceof WP_Post && vmseb_page_contains_shortcode((int) $page->ID)) {
                    vmseb_set_public_page_id((int) $page->ID);
                    $cache[$cache_key] = (int) $page->ID;
                    return (int) $page->ID;
                }
            }
        }

        $cache[$cache_key] = 0;
        return 0;
    }
}

if (!function_exists('vmseb_get_public_page_url')) {
    function vmseb_get_public_page_url(int $event_plan_id = 0): string
    {
        $page_id = vmseb_find_public_page_id(false);
        $url = $page_id > 0 ? get_permalink($page_id) : home_url('/express-bar/');
        if (!$url) {
            $url = home_url('/express-bar/');
        }
        if ($event_plan_id > 0) {
            $url = add_query_arg('event_plan_id', absint($event_plan_id), $url);
        }
        return (string) $url;
    }
}

if (!function_exists('vmseb_create_public_page')) {
    function vmseb_create_public_page(): int
    {
        $existing = vmseb_find_public_page_id(true);
        if ($existing > 0) {
            return $existing;
        }

        $by_slug = get_page_by_path('express-bar', OBJECT, 'page');
        if ($by_slug instanceof WP_Post && $by_slug->post_status !== 'trash') {
            vmseb_set_public_page_id((int) $by_slug->ID);
            return (int) $by_slug->ID;
        }

        $page_id = wp_insert_post(array(
            'post_title'   => 'Express Bar',
            'post_name'    => 'express-bar',
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'post_content' => '[vms_express_bar_menu]',
        ), true);

        if (is_wp_error($page_id) || absint($page_id) <= 0) {
            return 0;
        }

        vmseb_set_public_page_id((int) $page_id);
        return (int) $page_id;
    }
}

if (!function_exists('vmseb_get_current_public_url')) {
    function vmseb_get_current_public_url(): string
    {
        $scheme = is_ssl() ? 'https://' : 'http://';
        $host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
        $uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        if ($host === '' || $uri === '') {
            return get_permalink() ?: home_url('/');
        }
        return esc_url_raw($scheme . $host . $uri);
    }
}
if (!function_exists('vmseb_get_event_meta')) {
    function vmseb_get_event_meta(int $event_plan_id): array
    {
        $settings = vmseb_get_settings();
        $headline = trim((string) get_post_meta($event_plan_id, '_vms_express_bar_headline', true));
        $pickup = trim((string) get_post_meta($event_plan_id, '_vms_express_bar_pickup_instructions', true));

        return array(
            'enabled'             => (string) get_post_meta($event_plan_id, '_vms_express_bar_enabled', true) === '1',
            'headline'            => $headline !== '' ? $headline : $settings['headline'],
            'pickup_instructions' => $pickup !== '' ? $pickup : $settings['pickup_instructions'],
            'auto_embed'          => get_post_meta($event_plan_id, '_vmseb_auto_embed', true) !== '0',
            'opens_at'            => trim((string) get_post_meta($event_plan_id, '_vmseb_open_at', true)),
            'closes_at'           => trim((string) get_post_meta($event_plan_id, '_vmseb_close_at', true)),
        );
    }
}

if (!function_exists('vmseb_wp_timezone')) {
    function vmseb_wp_timezone(): DateTimeZone
    {
        if (function_exists('wp_timezone')) {
            return wp_timezone();
        }
        $tz_string = (string) get_option('timezone_string');
        if ($tz_string !== '') {
            return new DateTimeZone($tz_string);
        }
        $offset = (float) get_option('gmt_offset');
        $hours = (int) $offset;
        $minutes = (int) round(abs($offset - $hours) * 60);
        return new DateTimeZone(sprintf('%+03d:%02d', $hours, $minutes));
    }
}

if (!function_exists('vmseb_parse_local_datetime')) {
    function vmseb_parse_local_datetime(string $value): ?DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $tz = vmseb_wp_timezone();
        $formats = array('Y-m-d\TH:i', 'Y-m-d H:i:s', 'Y-m-d H:i');
        foreach ($formats as $format) {
            $dt = DateTimeImmutable::createFromFormat($format, $value, $tz);
            if ($dt instanceof DateTimeImmutable) {
                return $dt;
            }
        }
        try {
            return new DateTimeImmutable($value, $tz);
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('vmseb_get_event_default_window')) {
    function vmseb_get_event_default_window(int $event_plan_id): array
    {
        $settings = vmseb_get_settings();
        $start = function_exists('vmseb_get_event_plan_start_datetime') ? vmseb_get_event_plan_start_datetime($event_plan_id) : null;

        if (!empty($settings['default_window_auto_apply']) && $start instanceof DateTimeImmutable) {
            $open_before = max(0, absint($settings['default_window_open_minutes_before'] ?? 2880));
            $close_before = max(0, absint($settings['default_window_close_minutes_before'] ?? 0));
            $open = $open_before > 0 ? $start->sub(new DateInterval('PT' . $open_before . 'M')) : $start;
            $close = $close_before > 0 ? $start->sub(new DateInterval('PT' . $close_before . 'M')) : $start;
            if ($close < $open) {
                $close = $open;
            }
            return array('open' => $open, 'close' => $close);
        }

        $date = function_exists('vmseb_get_event_plan_date_value') ? vmseb_get_event_plan_date_value($event_plan_id) : trim((string) get_post_meta($event_plan_id, '_vms_event_date', true));
        if ($date === '') {
            return array('open' => null, 'close' => null);
        }
        $tz = vmseb_wp_timezone();
        $end_time = function_exists('vmseb_get_event_plan_end_time_value') ? vmseb_get_event_plan_end_time_value($event_plan_id) : '';
        $close = DateTimeImmutable::createFromFormat('Y-m-d H:i', $date . ' ' . ($end_time !== '' ? $end_time : '23:59'), $tz);
        return array('open' => null, 'close' => $close instanceof DateTimeImmutable ? $close : null);
    }
}

if (!function_exists('vmseb_format_open_time_for_customers')) {
    function vmseb_format_open_time_for_customers(DateTimeImmutable $opens_at): string
    {
        $today = wp_date('Y-m-d');
        $target_day = wp_date('Y-m-d', $opens_at->getTimestamp());
        if ($target_day === $today) {
            return wp_date('g:i a', $opens_at->getTimestamp());
        }
        return wp_date('M j, g:i a', $opens_at->getTimestamp());
    }
}

if (!function_exists('vmseb_format_customer_datetime')) {
    function vmseb_format_customer_datetime(?DateTimeImmutable $datetime): string
    {
        if (!$datetime instanceof DateTimeImmutable) {
            return '';
        }

        return wp_date('D, M j \a\t g:i A', $datetime->getTimestamp());
    }
}

if (!function_exists('vmseb_get_event_window_status')) {
    function vmseb_get_event_window_status(int $event_plan_id): array
    {
        $meta = vmseb_get_event_meta($event_plan_id);
        $raw_open = trim((string) ($meta['opens_at'] ?? ''));
        $raw_close = trim((string) ($meta['closes_at'] ?? ''));
        $has_explicit_open = $raw_open !== '';
        $has_explicit_close = $raw_close !== '';

        $opens_at = $has_explicit_open ? vmseb_parse_local_datetime($raw_open) : null;
        $closes_at = $has_explicit_close ? vmseb_parse_local_datetime($raw_close) : null;

        if (!$opens_at || !$closes_at) {
            $defaults = vmseb_get_event_default_window($event_plan_id);
            if (!$has_explicit_open && !empty($defaults['open'])) {
                $opens_at = $defaults['open'];
            }
            if (!$has_explicit_close && !empty($defaults['close'])) {
                $closes_at = $defaults['close'];
            }
        }

        $now = new DateTimeImmutable('now', vmseb_wp_timezone());
        $is_open = false;
        $phase = 'browse';
        $label = 'Browse Only';
        $message = 'Ordering opens during the event.';
        $customer_status = 'Not open yet';

        if (!$opens_at instanceof DateTimeImmutable) {
            if ($closes_at instanceof DateTimeImmutable && $now > $closes_at) {
                $phase = 'closed';
                $label = 'Closed';
                $message = 'Ordering has closed for this event.';
                $customer_status = 'Last call passed';
            }
        } elseif ($now < $opens_at) {
            $message = sprintf('Ordering opens at %s.', vmseb_format_open_time_for_customers($opens_at));
            $customer_status = 'Scheduled';
        } elseif ($closes_at instanceof DateTimeImmutable && $now > $closes_at) {
            $phase = 'closed';
            $label = 'Closed';
            $message = 'Ordering has closed for this event.';
            $customer_status = 'Last call passed';
        } else {
            $is_open = true;
            $phase = 'open';
            $label = 'Open';
            $message = $closes_at instanceof DateTimeImmutable
                ? sprintf('Ordering is open now. Last call at %s.', wp_date('g:i a', $closes_at->getTimestamp()))
                : 'Ordering is open now.';
            $customer_status = 'Open';
        }

        if ($phase === 'closed' && !($closes_at instanceof DateTimeImmutable)) {
            $customer_status = 'Closed';
        }

        return array(
            'is_open'                => $is_open,
            'phase'                  => $phase,
            'label'                  => $label,
            'message'                => $message,
            'opens_at'               => $opens_at,
            'closes_at'              => $closes_at,
            'customer_status'        => $customer_status,
            'opens_at_display'       => vmseb_format_customer_datetime($opens_at),
            'closes_at_display'      => vmseb_format_customer_datetime($closes_at),
            'status_field_label'     => 'Express Bar Status',
            'open_field_label'       => 'Drink pre-orders open',
            'last_call_field_label'  => 'Last call for drink pre-orders',
        );
    }
}

if (!function_exists('vmseb_get_catalog_candidates')) {
    function vmseb_get_catalog_candidates(): array
    {
        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }
        $cache = array();
        if (!vmseb_is_woocommerce_active() || !post_type_exists('product')) {
            return $cache;
        }

        $product_ids = get_posts(array(
            'post_type'        => 'product',
            'post_status'      => array('publish', 'private'),
            'posts_per_page'   => -1,
            'orderby'          => 'title',
            'order'            => 'ASC',
            'fields'           => 'ids',
            'suppress_filters' => false,
        ));

        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);
            if (!$product instanceof WC_Product) {
                continue;
            }

            if ($product->is_type('simple')) {
                $cache['p_' . $product_id] = vmseb_build_candidate_item($product, null);
                continue;
            }

            if ($product->is_type('variation')) {
                continue;
            }

            if ($product->is_type('variable')) {
                foreach ($product->get_available_variations() as $variation_data) {
                    $variation_id = absint($variation_data['variation_id'] ?? 0);
                    if ($variation_id <= 0) {
                        continue;
                    }
                    $variation = wc_get_product($variation_id);
                    if (!$variation instanceof WC_Product_Variation) {
                        continue;
                    }
                    $cache['v_' . $variation_id] = vmseb_build_candidate_item($product, $variation);
                }
            }
        }

        uasort($cache, static function(array $a, array $b): int {
            $catCmp = strcasecmp((string) ($a['default_category'] ?? ''), (string) ($b['default_category'] ?? ''));
            if ($catCmp !== 0) {
                return $catCmp;
            }
            return strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
        });

        return $cache;
    }
}

if (!function_exists('vmseb_build_candidate_item')) {
    function vmseb_build_candidate_item(WC_Product $product, ?WC_Product_Variation $variation = null): array
    {
        $is_variation = $variation instanceof WC_Product_Variation;
        $entity = $is_variation ? $variation : $product;
        $token = $is_variation ? 'v_' . $variation->get_id() : 'p_' . $product->get_id();
        $title = $is_variation ? $variation->get_name() : $product->get_name();
        $source_label = $product->get_name();
        $image_id = (int) $entity->get_image_id();
        if ($image_id <= 0) {
            $image_id = (int) $product->get_image_id();
        }
        $image_url = $image_id > 0 ? (string) wp_get_attachment_image_url($image_id, 'thumbnail') : '';
        $price = (float) wc_get_price_to_display($entity);
        $stock_qty = null;
        if ($entity->managing_stock()) {
            $raw_qty = $entity->get_stock_quantity();
            $stock_qty = $raw_qty === null ? null : max(0, (int) $raw_qty);
        }

        return array(
            'token'            => $token,
            'kind'             => $is_variation ? 'variation' : 'product',
            'product_id'       => (int) $product->get_id(),
            'variation_id'     => $is_variation ? (int) $variation->get_id() : 0,
            'title'            => $title,
            'source_label'     => $source_label,
            'price'            => $price,
            'price_html'       => $entity->get_price_html(),
            'image_url'        => $image_url,
            'image_id'         => $image_id,
            'default_category' => vmseb_guess_category_label($product, $variation),
            'purchasable'      => $entity->is_purchasable(),
            'stock_status'     => (string) $entity->get_stock_status(),
            'stock_qty'        => $stock_qty,
            'manage_stock'     => $entity->managing_stock(),
            'sku'              => (string) $entity->get_sku(),
        );
    }
}


if (!function_exists('vmseb_is_product_catalog_visible')) {
    function vmseb_is_product_catalog_visible(WC_Product $product): bool
    {
        $post_id = (int) $product->get_id();
        if ($post_id <= 0) {
            return false;
        }
        $post = get_post($post_id);
        if (!($post instanceof WP_Post) || !in_array((string) $post->post_type, array('product', 'product_variation'), true)) {
            return false;
        }
        $allowed_statuses = array('publish', 'private');
        if (!in_array((string) $post->post_status, $allowed_statuses, true)) {
            return false;
        }
        if ($product instanceof WC_Product_Variation) {
            $parent_id = (int) $product->get_parent_id();
            if ($parent_id <= 0) {
                return false;
            }
            $parent = get_post($parent_id);
            if (!($parent instanceof WP_Post) || !in_array((string) $parent->post_status, $allowed_statuses, true)) {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('vmseb_get_registry_prune_report')) {
    function vmseb_get_registry_prune_report(): array
    {
        $stored = get_option('vmseb_catalog_registry', array());
        if (!is_array($stored) || empty($stored)) {
            return array(
                'total' => 0,
                'stale' => 0,
                'kept' => 0,
                'stale_tokens' => array(),
            );
        }

        $stale = array();
        $kept = 0;
        foreach ($stored as $token => $cfg) {
            if (!is_array($cfg)) {
                $stale[] = (string) $token;
                continue;
            }
            $candidate = vmseb_get_candidate_by_token((string) $token);
            if (!$candidate) {
                $stale[] = (string) $token;
                continue;
            }
            $kept++;
        }

        return array(
            'total' => count($stored),
            'stale' => count($stale),
            'kept' => $kept,
            'stale_tokens' => $stale,
        );
    }
}

if (!function_exists('vmseb_prune_catalog_registry')) {
    function vmseb_prune_catalog_registry(): array
    {
        $stored = get_option('vmseb_catalog_registry', array());
        if (!is_array($stored) || empty($stored)) {
            return array('removed' => 0, 'kept' => 0);
        }

        $clean = array();
        $removed = 0;
        foreach ($stored as $token => $cfg) {
            if (!is_array($cfg) || !vmseb_get_candidate_by_token((string) $token)) {
                $removed++;
                continue;
            }
            $clean[(string) $token] = $cfg;
        }

        update_option('vmseb_catalog_registry', $clean, false);
        return array('removed' => $removed, 'kept' => count($clean));
    }
}

if (!function_exists('vmseb_guess_category_label')) {
    function vmseb_guess_category_label(WC_Product $product, ?WC_Product_Variation $variation = null): string
    {
        unset($variation);
        $terms = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'all'));
        if (!is_array($terms) || empty($terms)) {
            return '';
        }

        $skip = array('uncategorized', 'uncategorised');
        foreach ($terms as $term) {
            if (!$term instanceof WP_Term) {
                continue;
            }
            $slug = sanitize_title((string) $term->slug);
            if (in_array($slug, $skip, true)) {
                continue;
            }
            $name = trim((string) $term->name);
            if ($name !== '') {
                return $name;
            }
        }

        foreach ($terms as $term) {
            if ($term instanceof WP_Term) {
                $name = trim((string) $term->name);
                if ($name !== '') {
                    return $name;
                }
            }
        }

        return '';
    }
}

if (!function_exists('vmseb_default_age_gate_for_candidate')) {
    function vmseb_default_age_gate_for_candidate(array $candidate): int
    {
        $category = strtolower((string) ($candidate['default_category'] ?? ''));
        return preg_match('/beer|wine|seltzer|white claw|cider|cocktail|liquor|alcohol/', $category) ? 1 : 0;
    }
}

if (!function_exists('vmseb_build_legacy_registry_seed')) {
    function vmseb_build_legacy_registry_seed(): array
    {
        $registry = array();
        $legacy = get_option('vmseb_bar_menu_defaults', array());
        if (!is_array($legacy)) {
            return $registry;
        }
        $candidates = vmseb_get_catalog_candidates();
        $default_ids = array();
        if (!empty($legacy['product_ids'])) {
            $default_ids = preg_split('/[^0-9]+/', (string) $legacy['product_ids']) ?: array();
            $default_ids = array_map('absint', $default_ids);
        }
        foreach ($default_ids as $product_id) {
            $token = 'p_' . $product_id;
            if (isset($candidates[$token])) {
                $registry[$token] = array(
                    'enabled'            => 1,
                    'category'           => $candidates[$token]['default_category'],
                    'sort'               => 0,
                    'bucket_eligible'    => 0,
                    'age_gate'           => vmseb_default_age_gate_for_candidate($candidates[$token]),
                    'online_cap'         => 0,
                    'bar_only_threshold' => 0,
                );
            }
        }
        $beer_source_id = absint($legacy['beer_source_product_id'] ?? 0);
        if ($beer_source_id > 0) {
            foreach ($candidates as $token => $candidate) {
                if ((int) $candidate['product_id'] === $beer_source_id && $candidate['kind'] === 'variation') {
                    $registry[$token] = array(
                        'enabled'            => 1,
                        'category'           => $candidates[$token]['default_category'],
                        'sort'               => 0,
                        'bucket_eligible'    => 1,
                        'age_gate'           => 1,
                        'online_cap'         => 0,
                        'bar_only_threshold' => 0,
                    );
                }
            }
        }
        return $registry;
    }
}

if (!function_exists('vmseb_get_registry')) {
    function vmseb_get_registry(bool $refresh = false): array
    {
        static $cached = null;
        if ($refresh) {
            $cached = null;
        }
        if (is_array($cached)) {
            return $cached;
        }

        $stored = get_option('vmseb_catalog_registry', array());
        if (!is_array($stored) || empty($stored)) {
            $stored = vmseb_build_legacy_registry_seed();
        }

        $registry = array();
        foreach ($stored as $token => $item) {
            if (!is_array($item)) {
                continue;
            }
            $registry[(string) $token] = array(
                'enabled'            => !empty($item['enabled']) ? 1 : 0,
                'category'           => trim((string) ($item['category'] ?? '')),
                'sort'               => (int) ($item['sort'] ?? 0),
                'bucket_eligible'    => !empty($item['bucket_eligible']) ? 1 : 0,
                'age_gate'           => !empty($item['age_gate']) ? 1 : 0,
                'online_cap'         => max(0, absint($item['online_cap'] ?? 0)),
                'bar_only_threshold' => max(0, absint($item['bar_only_threshold'] ?? 0)),
            );
        }

        $cached = $registry;
        return $registry;
    }
}

if (!function_exists('vmseb_parse_catalog_token')) {
    function vmseb_parse_catalog_token(string $token): ?array
    {
        $token = trim($token);
        if (!preg_match('/^(p|v)_(\d+)$/', $token, $matches)) {
            return null;
        }

        return array(
            'kind' => $matches[1] === 'v' ? 'variation' : 'product',
            'id'   => absint($matches[2]),
        );
    }
}

if (!function_exists('vmseb_get_candidate_by_token')) {
    function vmseb_get_candidate_by_token(string $token): ?array
    {
        static $candidate_cache = array();
        $token = trim($token);
        if (array_key_exists($token, $candidate_cache)) {
            return $candidate_cache[$token];
        }

        $parsed = vmseb_parse_catalog_token($token);
        if (!$parsed || empty($parsed['id']) || !vmseb_is_woocommerce_active()) {
            $candidate_cache[$token] = null;
            return null;
        }

        if ($parsed['kind'] === 'variation') {
            $variation = wc_get_product((int) $parsed['id']);
            if (!$variation instanceof WC_Product_Variation || !vmseb_is_product_catalog_visible($variation)) {
                $candidate_cache[$token] = null;
                return null;
            }
            $parent = wc_get_product((int) $variation->get_parent_id());
            if (!$parent instanceof WC_Product || !vmseb_is_product_catalog_visible($parent)) {
                $candidate_cache[$token] = null;
                return null;
            }
            $candidate_cache[$token] = vmseb_build_candidate_item($parent, $variation);
            return $candidate_cache[$token];
        }

        $product = wc_get_product((int) $parsed['id']);
        if (!$product instanceof WC_Product || !vmseb_is_product_catalog_visible($product)) {
            $candidate_cache[$token] = null;
            return null;
        }

        $candidate_cache[$token] = vmseb_build_candidate_item($product, null);
        return $candidate_cache[$token];
    }
}

if (!function_exists('vmseb_apply_registry_to_candidate')) {
    function vmseb_apply_registry_to_candidate(array $candidate, array $cfg = array()): array
    {
        $woo_category = trim((string) ($candidate['default_category'] ?? ''));
        $legacy_category = trim((string) ($cfg['category'] ?? ''));
        $category = $woo_category !== '' ? $woo_category : $legacy_category;

        return array_merge($candidate, array(
            'enabled'            => !empty($cfg['enabled']) ? 1 : 0,
            'category'           => $category,
            'sort'               => (int) ($cfg['sort'] ?? 0),
            'bucket_eligible'    => !empty($cfg['bucket_eligible']) ? 1 : 0,
            'age_gate'           => array_key_exists('age_gate', $cfg) ? (!empty($cfg['age_gate']) ? 1 : 0) : vmseb_default_age_gate_for_candidate($candidate),
            'online_cap'         => max(0, absint($cfg['online_cap'] ?? 0)),
            'bar_only_threshold' => max(0, absint($cfg['bar_only_threshold'] ?? 0)),
        ));
    }
}

if (!function_exists('vmseb_get_catalog_item_by_token')) {
    function vmseb_get_catalog_item_by_token(string $token, bool $require_enabled = false): ?array
    {
        static $item_cache = array();
        $token = trim($token);
        $cache_key = ($require_enabled ? 'enabled:' : 'any:') . $token;
        if (array_key_exists($cache_key, $item_cache)) {
            return $item_cache[$cache_key];
        }

        $registry = vmseb_get_registry();
        if (empty($registry[$token])) {
            $item_cache[$cache_key] = null;
            return null;
        }
        if ($require_enabled && empty($registry[$token]['enabled'])) {
            $item_cache[$cache_key] = null;
            return null;
        }

        $candidate = vmseb_get_candidate_by_token($token);
        if (!$candidate) {
            $item_cache[$cache_key] = null;
            return null;
        }

        $item = vmseb_apply_registry_to_candidate($candidate, $registry[$token]);
        if ($require_enabled && empty($item['enabled'])) {
            $item_cache[$cache_key] = null;
            return null;
        }

        $item_cache[$cache_key] = $item;
        return $item;
    }
}

if (!function_exists('vmseb_get_catalog_items')) {
    function vmseb_get_catalog_items(bool $enabled_only = false): array
    {
        static $items_cache = array();
        $cache_key = $enabled_only ? 'enabled' : 'all';
        if (array_key_exists($cache_key, $items_cache)) {
            return $items_cache[$cache_key];
        }

        $items = array();
        if ($enabled_only) {
            foreach (vmseb_get_registry() as $token => $cfg) {
                if (empty($cfg['enabled'])) {
                    continue;
                }
                $item = vmseb_get_catalog_item_by_token((string) $token, true);
                if ($item) {
                    $items[(string) $token] = $item;
                }
            }
        } else {
            $candidates = vmseb_get_catalog_candidates();
            $registry = vmseb_get_registry();
            foreach ($candidates as $token => $candidate) {
                $items[$token] = vmseb_apply_registry_to_candidate($candidate, $registry[$token] ?? array());
            }
        }

        uasort($items, static function(array $a, array $b): int {
            $catCmp = strcasecmp((string) $a['category'], (string) $b['category']);
            if ($catCmp !== 0) {
                return $catCmp;
            }
            $sortCmp = ((int) $a['sort']) <=> ((int) $b['sort']);
            if ($sortCmp !== 0) {
                return $sortCmp;
            }
            return strcasecmp((string) $a['title'], (string) $b['title']);
        });

        $items_cache[$cache_key] = $items;
        return $items;
    }
}

if (!function_exists('vmseb_group_items_by_category')) {
    function vmseb_group_items_by_category(array $items): array
    {
        $grouped = array();
        foreach ($items as $token => $item) {
            $category = trim((string) ($item['category'] ?? ''));
            if ($category === '') {
                $category = 'Menu';
            }
            if (!isset($grouped[$category])) {
                $grouped[$category] = array();
            }
            $grouped[$category][$token] = $item;
        }
        return $grouped;
    }
}

if (!function_exists('vmseb_get_item_by_token')) {
    function vmseb_get_item_by_token(string $token): ?array
    {
        return vmseb_get_catalog_item_by_token($token, false);
    }
}

if (!function_exists('vmseb_selected_quantities_require_age_gate')) {
    function vmseb_selected_quantities_require_age_gate(array $selected_quantities): bool
    {
        foreach ($selected_quantities as $token => $qty) {
            if (max(0, absint($qty)) <= 0) {
                continue;
            }
            $item = vmseb_get_item_by_token((string) $token);
            if ($item && !empty($item['age_gate'])) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('vmseb_get_item_online_availability')) {
    function vmseb_get_item_online_availability(array $item): array
    {
        $available = null;
        if (!empty($item['manage_stock']) && $item['stock_qty'] !== null) {
            $available = max(0, (int) $item['stock_qty']);
        }
        if (!empty($item['online_cap'])) {
            $available = $available === null ? (int) $item['online_cap'] : min($available, (int) $item['online_cap']);
        }
        $bar_only = false;
        if ($available !== null && $available <= (int) ($item['bar_only_threshold'] ?? 0) && $available > 0) {
            $bar_only = true;
        }
        $orderable = !empty($item['enabled']) && !empty($item['purchasable']) && (string) ($item['stock_status'] ?? '') !== 'outofstock';
        if ($available !== null && $available <= 0) {
            $orderable = false;
        }
        if ($bar_only) {
            $orderable = false;
        }

        return array(
            'available' => $available,
            'bar_only'  => $bar_only,
            'orderable' => $orderable,
        );
    }
}

if (!function_exists('vmseb_parse_event_datetime_candidate')) {
    /**
     * Parse the variety of event date/time values VMS/TEC installs may store.
     * Returns null for empty or unparseable values so callers can fall back safely.
     */
    function vmseb_parse_event_datetime_candidate(string $date_value, string $time_value = ''): ?DateTimeImmutable
    {
        $date_value = trim($date_value);
        $time_value = trim($time_value);
        if ($date_value === '') {
            return null;
        }

        $tz = vmseb_wp_timezone();
        if (is_numeric($date_value) && (int) $date_value > 1000000000) {
            return (new DateTimeImmutable('@' . (int) $date_value))->setTimezone($tz);
        }

        $candidate = $date_value;
        if ($time_value !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_value)) {
            $candidate .= ' ' . $time_value;
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_value)) {
            $candidate .= ' 00:00';
        }

        $parsed = vmseb_parse_local_datetime($candidate);
        if ($parsed instanceof DateTimeImmutable) {
            return $parsed;
        }

        try {
            return new DateTimeImmutable($candidate, $tz);
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('vmseb_first_event_datetime_from_meta')) {
    /**
     * Find the first parseable event date/time from common VMS/TEC meta keys.
     */
    function vmseb_first_event_datetime_from_meta(int $event_plan_id, array $datetime_keys, array $date_keys = array(), array $time_keys = array()): ?DateTimeImmutable
    {
        foreach ($datetime_keys as $key) {
            $value = trim((string) get_post_meta($event_plan_id, (string) $key, true));
            $parsed = vmseb_parse_event_datetime_candidate($value);
            if ($parsed instanceof DateTimeImmutable) {
                return $parsed;
            }
        }

        foreach ($date_keys as $date_key) {
            $date_value = trim((string) get_post_meta($event_plan_id, (string) $date_key, true));
            if ($date_value === '') {
                continue;
            }
            $time_value = '';
            foreach ($time_keys as $time_key) {
                $candidate_time = trim((string) get_post_meta($event_plan_id, (string) $time_key, true));
                if ($candidate_time !== '') {
                    $time_value = $candidate_time;
                    break;
                }
            }
            $parsed = vmseb_parse_event_datetime_candidate($date_value, $time_value);
            if ($parsed instanceof DateTimeImmutable) {
                return $parsed;
            }
        }

        return null;
    }
}


if (!function_exists('vmseb_normalize_meta_scalar')) {
    /**
     * Return a readable scalar from WP meta that may be stored as a scalar or array.
     */
    function vmseb_normalize_meta_scalar($value): string
    {
        if (is_array($value)) {
            $value = reset($value);
        }
        if (is_object($value)) {
            return '';
        }
        return trim((string) $value);
    }
}

if (!function_exists('vmseb_get_first_event_meta_value')) {
    function vmseb_get_first_event_meta_value(int $event_plan_id, array $keys): string
    {
        foreach ($keys as $key) {
            $value = vmseb_normalize_meta_scalar(get_post_meta($event_plan_id, (string) $key, true));
            if ($value !== '') {
                return $value;
            }
        }
        return '';
    }
}

if (!function_exists('vmseb_event_plan_is_public_candidate')) {
    /**
     * Determine whether an Event Plan should be considered by the dedicated public page auto-selector.
     */
    function vmseb_event_plan_is_public_candidate(int $event_plan_id, bool $require_express_bar_enabled = true): bool
    {
        if ($event_plan_id <= 0 || get_post_type($event_plan_id) !== 'vms_event_plan') {
            return false;
        }
        if ($require_express_bar_enabled && (string) get_post_meta($event_plan_id, '_vms_express_bar_enabled', true) !== '1') {
            return false;
        }

        // Customer-facing Express Bar surfaces must never auto-select or expose
        // draft/private/pending Event Plans. WordPress publish status is authoritative.
        $wp_status = (string) get_post_status($event_plan_id);
        if ($wp_status !== 'publish') {
            return false;
        }

        $status = strtolower(vmseb_get_first_event_meta_value($event_plan_id, array('_vms_event_plan_status', '_vms_plan_status', 'vms_event_plan_status')));
        if (in_array($status, array('cancelled', 'canceled', 'trash', 'archived', 'draft', 'pending', 'private'), true)) {
            return false;
        }

        return true;
    }
}

if (!function_exists('vmseb_get_event_plan_linked_event_ids')) {
    /**
     * Return likely linked TEC/event post IDs for an Event Plan. Different VMS builds have
     * used different relationship meta keys, so keep this intentionally broad and safe.
     */
    function vmseb_get_event_plan_linked_event_ids(int $event_plan_id): array
    {
        $ids = array();
        foreach (array(
            '_vms_event_id',
            'vms_event_id',
            '_vms_tec_event_id',
            'vms_tec_event_id',
            '_vms_event_post_id',
            'vms_event_post_id',
            '_tribe_event_id',
            'tribe_event_id',
            '_event_id',
            'event_id',
        ) as $key) {
            $raw = get_post_meta($event_plan_id, $key, true);
            if (is_array($raw)) {
                foreach ($raw as $value) {
                    $id = absint($value);
                    if ($id > 0) {
                        $ids[] = $id;
                    }
                }
            } else {
                $id = absint($raw);
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }
}

if (!function_exists('vmseb_get_first_event_or_linked_meta_value')) {
    /**
     * Read an Event Plan meta value, falling back to linked event posts when present.
     */
    function vmseb_get_first_event_or_linked_meta_value(int $event_plan_id, array $keys): string
    {
        $value = vmseb_get_first_event_meta_value($event_plan_id, $keys);
        if ($value !== '') {
            return $value;
        }

        foreach (vmseb_get_event_plan_linked_event_ids($event_plan_id) as $linked_id) {
            $value = vmseb_get_first_event_meta_value($linked_id, $keys);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}

if (!function_exists('vmseb_get_event_plan_date_value')) {
    /**
     * Read the best available event date for public selection. Do not assume every
     * install stores the Event Plan date in the same meta key.
     */
    function vmseb_get_event_plan_date_value(int $event_plan_id): string
    {
        return vmseb_get_first_event_or_linked_meta_value($event_plan_id, array(
            '_vms_event_date',
            'vms_event_date',
            '_vms_event_plan_date',
            'vms_event_plan_date',
            '_vms_plan_date',
            'vms_plan_date',
            '_vms_date',
            'vms_date',
            '_vms_start_date',
            'vms_start_date',
            '_vms_event_start_date',
            'vms_event_start_date',
            '_event_date',
            'event_date',
            '_event_start_date',
            '_tribe_EventStartDate',
            '_EventStartDate',
        ));
    }
}

if (!function_exists('vmseb_get_event_plan_start_time_value')) {
    function vmseb_get_event_plan_start_time_value(int $event_plan_id): string
    {
        return vmseb_get_first_event_or_linked_meta_value($event_plan_id, array(
            '_vms_start_time',
            '_vms_event_start_time',
            'vms_start_time',
            'vms_event_start_time',
            '_event_start_time',
            '_EventStartTime',
        ));
    }
}

if (!function_exists('vmseb_get_event_plan_end_time_value')) {
    function vmseb_get_event_plan_end_time_value(int $event_plan_id): string
    {
        return vmseb_get_first_event_or_linked_meta_value($event_plan_id, array(
            '_vms_end_time',
            '_vms_event_end_time',
            'vms_end_time',
            'vms_event_end_time',
            '_event_end_time',
            '_EventEndTime',
        ));
    }
}

if (!function_exists('vmseb_get_event_plan_start_datetime')) {
    function vmseb_get_event_plan_start_datetime(int $event_plan_id): ?DateTimeImmutable
    {
        $date = vmseb_get_event_plan_date_value($event_plan_id);
        if ($date === '') {
            return null;
        }
        return vmseb_parse_event_datetime_candidate($date, vmseb_get_event_plan_start_time_value($event_plan_id));
    }
}

if (!function_exists('vmseb_get_event_plan_end_datetime')) {
    function vmseb_get_event_plan_end_datetime(int $event_plan_id): ?DateTimeImmutable
    {
        $date = vmseb_get_event_plan_date_value($event_plan_id);
        if ($date === '') {
            return null;
        }
        $end_time = vmseb_get_event_plan_end_time_value($event_plan_id);
        $parsed = $end_time !== '' ? vmseb_parse_event_datetime_candidate($date, $end_time) : null;
        if ($parsed instanceof DateTimeImmutable) {
            return $parsed;
        }
        $start = vmseb_get_event_plan_start_datetime($event_plan_id);
        if ($start instanceof DateTimeImmutable) {
            return $start->setTime(23, 59, 59);
        }
        return vmseb_parse_event_datetime_candidate($date, '23:59');
    }
}

if (!function_exists('vmseb_get_event_plan_public_event_post')) {
    function vmseb_get_event_plan_public_event_post(int $event_plan_id): ?WP_Post
    {
        $linked_ids = vmseb_get_event_plan_linked_event_ids($event_plan_id);
        if (empty($linked_ids)) {
            return null;
        }

        $preferred = null;
        foreach ($linked_ids as $linked_id) {
            $post = get_post($linked_id);
            if (!$post instanceof WP_Post || $post->post_status !== 'publish') {
                continue;
            }

            $type_object = get_post_type_object($post->post_type);
            if (!$type_object || empty($type_object->public)) {
                continue;
            }

            if ($post->post_type === 'tribe_events') {
                return $post;
            }

            if (!$preferred instanceof WP_Post) {
                $preferred = $post;
            }
        }

        return $preferred instanceof WP_Post ? $preferred : null;
    }
}

if (!function_exists('vmseb_get_event_banner_image_id')) {
    function vmseb_get_event_banner_image_id(int $event_plan_id): int
    {
        $post_ids = array();
        $public_event = vmseb_get_event_plan_public_event_post($event_plan_id);
        if ($public_event instanceof WP_Post) {
            $post_ids[] = (int) $public_event->ID;
        }
        $post_ids[] = $event_plan_id;

        foreach (array_values(array_unique(array_filter($post_ids))) as $post_id) {
            $image_id = (int) get_post_thumbnail_id($post_id);
            if ($image_id <= 0) {
                continue;
            }

            return $image_id;
        }

        return 0;
    }
}

if (!function_exists('vmseb_get_event_banner_image_url')) {
    function vmseb_get_event_banner_image_url(int $event_plan_id): string
    {
        $image_id = vmseb_get_event_banner_image_id($event_plan_id);
        if ($image_id <= 0) {
            return '';
        }

        return (string) wp_get_attachment_image_url($image_id, 'medium');
    }
}

if (!function_exists('vmseb_get_event_context')) {
    function vmseb_get_event_context(int $event_plan_id): array
    {
        $public_event = vmseb_get_event_plan_public_event_post($event_plan_id);
        $event_url = '';
        $event_title = get_the_title($event_plan_id);
        if ($public_event instanceof WP_Post) {
            $event_url = (string) get_permalink($public_event);
            $event_title = get_the_title($public_event);
        }

        $event_start = vmseb_get_event_plan_start_datetime($event_plan_id);

        return array(
            'title'            => $event_title,
            'url'              => $event_url ?: '',
            'date_display'     => vmseb_format_customer_datetime($event_start),
            'context_prefix'   => 'Ordering for:',
            'view_event_label' => 'View event page',
        );
    }
}

if (!function_exists('vmseb_rank_public_event_by_vms_date')) {
    /**
     * Prefer actual event date/time over WordPress post order for /express-bar/ auto-selection.
     */
    function vmseb_rank_public_event_by_vms_date(int $event_plan_id, DateTimeImmutable $now, bool $require_express_bar_enabled = true): ?array
    {
        if (!vmseb_event_plan_is_public_candidate($event_plan_id, $require_express_bar_enabled)) {
            return null;
        }

        $start = vmseb_get_event_plan_start_datetime($event_plan_id);
        $end = vmseb_get_event_plan_end_datetime($event_plan_id);
        if (!$start instanceof DateTimeImmutable && !$end instanceof DateTimeImmutable) {
            return null;
        }
        if ($start instanceof DateTimeImmutable && !$end instanceof DateTimeImmutable) {
            $end = $start->setTime(23, 59, 59);
        }
        if ($end instanceof DateTimeImmutable && $end < $now) {
            return null;
        }

        $today = $now->format('Y-m-d');
        $start_day = $start instanceof DateTimeImmutable ? $start->format('Y-m-d') : '';
        $end_day = $end instanceof DateTimeImmutable ? $end->format('Y-m-d') : '';

        if ($start instanceof DateTimeImmutable && $end instanceof DateTimeImmutable && $start <= $now && $end >= $now) {
            $group = 0; // Active now.
            $sort_ts = $end->getTimestamp();
        } elseif ($start_day === $today || $end_day === $today) {
            $group = 1; // Today, but not inside the event window yet/anymore.
            $sort_ts = $start instanceof DateTimeImmutable ? abs($start->getTimestamp() - $now->getTimestamp()) : $end->getTimestamp();
        } else {
            $group = 2; // Next future event.
            $sort_ts = $start instanceof DateTimeImmutable ? $start->getTimestamp() : $end->getTimestamp();
        }

        return array(
            'id'      => $event_plan_id,
            'group'   => $group,
            'sort_ts' => $sort_ts,
            'post_id' => $event_plan_id,
        );
    }
}

if (!function_exists('vmseb_get_event_public_selection_window')) {
    /**
     * Build a durable selection window for the dedicated public Express Bar page.
     * Explicit Express Bar open/close values win, then common event/TEC fields,
     * then the VMS event date with an end-of-day close.
     */
    function vmseb_get_event_public_selection_window(int $event_plan_id): array
    {
        $cfg = vmseb_get_event_meta($event_plan_id);
        $opens_at = !empty($cfg['opens_at']) ? vmseb_parse_local_datetime((string) $cfg['opens_at']) : null;
        $closes_at = !empty($cfg['closes_at']) ? vmseb_parse_local_datetime((string) $cfg['closes_at']) : null;

        if (!$opens_at instanceof DateTimeImmutable) {
            $opens_at = vmseb_first_event_datetime_from_meta(
                $event_plan_id,
                array('_vms_event_start_datetime', '_vms_event_start', '_vms_event_datetime', '_EventStartDate', '_event_start_date', '_tribe_EventStartDate'),
                array('_vms_event_date', '_event_date'),
                array('_vms_event_start_time', '_vms_start_time', '_event_start_time', '_EventStartTime')
            );
        }

        if (!$closes_at instanceof DateTimeImmutable) {
            $closes_at = vmseb_first_event_datetime_from_meta(
                $event_plan_id,
                array('_vms_event_end_datetime', '_vms_event_end', '_EventEndDate', '_event_end_date', '_tribe_EventEndDate'),
                array('_vms_event_date', '_event_date'),
                array('_vms_event_end_time', '_vms_end_time', '_event_end_time', '_EventEndTime')
            );
        }

        if ($opens_at instanceof DateTimeImmutable && (!$closes_at instanceof DateTimeImmutable || $closes_at <= $opens_at)) {
            $closes_at = $opens_at->setTime(23, 59, 59);
        }

        if (!$opens_at instanceof DateTimeImmutable) {
            $date = trim((string) get_post_meta($event_plan_id, '_vms_event_date', true));
            $opens_at = vmseb_parse_event_datetime_candidate($date);
        }

        if (!$closes_at instanceof DateTimeImmutable && $opens_at instanceof DateTimeImmutable) {
            $closes_at = $opens_at->setTime(23, 59, 59);
        }

        return array(
            'start' => $opens_at,
            'end'   => $closes_at,
        );
    }
}

if (!function_exists('vmseb_rank_public_event_candidate')) {
    function vmseb_rank_public_event_candidate(int $event_plan_id, DateTimeImmutable $now, bool $require_express_bar_enabled = true): ?array
    {
        if (!vmseb_event_plan_is_public_candidate($event_plan_id, $require_express_bar_enabled)) {
            return null;
        }

        $window = vmseb_get_event_public_selection_window($event_plan_id);
        $start = $window['start'] ?? null;
        $end = $window['end'] ?? null;

        if (!$start instanceof DateTimeImmutable && !$end instanceof DateTimeImmutable) {
            return null;
        }

        if ($end instanceof DateTimeImmutable && $end < $now) {
            return null;
        }

        if ($start instanceof DateTimeImmutable && !$end instanceof DateTimeImmutable) {
            $end = $start->setTime(23, 59, 59);
        }

        $now_day = $now->format('Y-m-d');
        $start_day = $start instanceof DateTimeImmutable ? $start->format('Y-m-d') : '';
        $end_day = $end instanceof DateTimeImmutable ? $end->format('Y-m-d') : '';

        $group = 2;
        if ($start instanceof DateTimeImmutable && $end instanceof DateTimeImmutable && $start <= $now && $end >= $now) {
            $group = 0;
        } elseif ($start_day === $now_day || $end_day === $now_day) {
            $group = 1;
        }

        $sort_ts = $start instanceof DateTimeImmutable ? $start->getTimestamp() : ($end instanceof DateTimeImmutable ? $end->getTimestamp() : PHP_INT_MAX);
        if ($group === 0 && $end instanceof DateTimeImmutable) {
            $sort_ts = $end->getTimestamp();
        }

        return array(
            'id'       => $event_plan_id,
            'group'    => $group,
            'sort_ts'  => $sort_ts,
            'post_id'  => $event_plan_id,
        );
    }
}

if (!function_exists('vmseb_sort_public_event_candidates')) {
    function vmseb_sort_public_event_candidates(array &$ranked): void
    {
        usort($ranked, static function (array $a, array $b): int {
            if ((int) $a['group'] !== (int) $b['group']) {
                return (int) $a['group'] <=> (int) $b['group'];
            }
            if ((int) $a['sort_ts'] !== (int) $b['sort_ts']) {
                return (int) $a['sort_ts'] <=> (int) $b['sort_ts'];
            }
            $a_enabled = !empty($a['express_bar_enabled']) ? 0 : 1;
            $b_enabled = !empty($b['express_bar_enabled']) ? 0 : 1;
            if ($a_enabled !== $b_enabled) {
                return $a_enabled <=> $b_enabled;
            }
            return (int) $a['post_id'] <=> (int) $b['post_id'];
        });
    }
}

if (!function_exists('vmseb_candidate_with_express_bar_flag')) {
    function vmseb_candidate_with_express_bar_flag(array $candidate, int $event_plan_id): array
    {
        $candidate['express_bar_enabled'] = (string) get_post_meta($event_plan_id, '_vms_express_bar_enabled', true) === '1';
        return $candidate;
    }
}

if (!function_exists('vmseb_resolve_public_event_plan_id')) {
    function vmseb_resolve_public_event_plan_id(): int
    {
        static $resolved = null;
        if ($resolved !== null) {
            return (int) $resolved;
        }

        if (!post_type_exists('vms_event_plan')) {
            $resolved = 0;
            return 0;
        }

        // The dedicated public page resolves only customer-visible, published,
        // Express Bar-enabled Event Plans. This prevents draft/test plans from
        // leaking into navigation-driven /express-bar/ traffic.
        $ids = get_posts(array(
            'post_type'        => 'vms_event_plan',
            'post_status'      => 'publish',
            'posts_per_page'   => 1000,
            'fields'           => 'ids',
            'orderby'          => 'ID',
            'order'            => 'ASC',
            'meta_key'         => '_vms_express_bar_enabled',
            'meta_value'       => '1',
            'no_found_rows'    => true,
            'suppress_filters' => true,
        ));

        $now = new DateTimeImmutable('now', vmseb_wp_timezone());
        $ranked = array();

        foreach ($ids as $id) {
            $event_id = absint($id);
            $candidate = vmseb_rank_public_event_by_vms_date($event_id, $now, true);
            if (is_array($candidate)) {
                $ranked[] = vmseb_candidate_with_express_bar_flag($candidate, $event_id);
            }
        }

        if (empty($ranked)) {
            foreach ($ids as $id) {
                $event_id = absint($id);
                $candidate = vmseb_rank_public_event_candidate($event_id, $now, true);
                if (is_array($candidate)) {
                    $ranked[] = vmseb_candidate_with_express_bar_flag($candidate, $event_id);
                }
            }
        }

        if (empty($ranked)) {
            $resolved = 0;
            return 0;
        }

        vmseb_sort_public_event_candidates($ranked);
        $resolved = absint($ranked[0]['id'] ?? 0);
        return (int) $resolved;
    }
}

if (!function_exists('vmseb_resolve_event_plan_id_from_post')) {
    function vmseb_resolve_event_plan_id_from_post(WP_Post $post): int
    {
        if ($post->post_type === 'vms_event_plan') {
            return (int) $post->ID;
        }
        if (function_exists('bvmgr_get_event_plan_for_tec_event')) {
            return (int) bvmgr_get_event_plan_for_tec_event((int) $post->ID);
        }
        if (function_exists('bvmgr_resolve_event_plan_for_tec_event')) {
            return (int) bvmgr_resolve_event_plan_for_tec_event((int) $post->ID);
        }
        if (function_exists('vms_get_event_plan_for_tec_event')) {
            return (int) vms_get_event_plan_for_tec_event((int) $post->ID);
        }
        if (function_exists('vms_resolve_event_plan_for_tec_event')) {
            return (int) vms_resolve_event_plan_for_tec_event((int) $post->ID);
        }
        return 0;
    }
}

if (!function_exists('vmseb_get_public_event_choices')) {
    /**
     * Return current/future public Express Bar Event Plans in customer-facing order.
     */
    function vmseb_get_public_event_choices(): array
    {
        if (!post_type_exists('vms_event_plan')) {
            return array();
        }

        $ids = get_posts(array(
            'post_type'        => 'vms_event_plan',
            'post_status'      => 'publish',
            'posts_per_page'   => 1000,
            'fields'           => 'ids',
            'orderby'          => 'ID',
            'order'            => 'ASC',
            'meta_key'         => '_vms_express_bar_enabled',
            'meta_value'       => '1',
            'no_found_rows'    => true,
            'suppress_filters' => true,
        ));

        $now = new DateTimeImmutable('now', vmseb_wp_timezone());
        $ranked = array();
        foreach ($ids as $id) {
            $event_id = absint($id);
            $candidate = vmseb_rank_public_event_by_vms_date($event_id, $now, true);
            if (!is_array($candidate)) {
                $candidate = vmseb_rank_public_event_candidate($event_id, $now, true);
            }
            if (!is_array($candidate)) {
                continue;
            }
            $ranked[] = vmseb_candidate_with_express_bar_flag($candidate, $event_id);
        }
        vmseb_sort_public_event_candidates($ranked);

        $choices = array();
        foreach ($ranked as $candidate) {
            $event_plan_id = absint($candidate['id'] ?? 0);
            if ($event_plan_id <= 0) {
                continue;
            }
            $context = vmseb_get_event_context($event_plan_id);
            $choices[] = array(
                'id'           => $event_plan_id,
                'title'        => (string) ($context['title'] ?? get_the_title($event_plan_id)),
                'url'          => (string) ($context['url'] ?? ''),
                'date_display' => (string) ($context['date_display'] ?? ''),
                'poster_url'   => vmseb_get_event_banner_image_url($event_plan_id),
                'bar_url'      => vmseb_get_public_page_url($event_plan_id),
            );
        }
        return $choices;
    }
}

if (!function_exists('vmseb_get_event_ticket_product_ids')) {
    function vmseb_get_event_ticket_product_ids(int $event_plan_id): array
    {
        $ids = get_post_meta($event_plan_id, '_vms_ticket_product_ids_v1', true);
        $ids = is_array($ids) ? array_values(array_unique(array_filter(array_map('absint', $ids)))) : array();
        if (!empty($ids)) {
            return $ids;
        }

        $fallback = get_posts(array(
            'post_type'        => 'product',
            'post_status'      => array('publish', 'private'),
            'posts_per_page'   => 100,
            'fields'           => 'ids',
            'meta_key'         => '_vms_event_plan_id',
            'meta_value'       => $event_plan_id,
            'no_found_rows'    => true,
            'suppress_filters' => true,
        ));
        foreach ($fallback as $product_id) {
            $product_id = absint($product_id);
            $role = strtolower((string) get_post_meta($product_id, '_vms_product_role', true));
            $tec_event_id = absint(get_post_meta($product_id, '_tribe_wooticket_for_event', true));
            if ($tec_event_id > 0 || strpos($role, 'ticket') !== false || strpos($role, 'admission') !== false) {
                $ids[] = $product_id;
            }
        }
        return array_values(array_unique(array_filter($ids)));
    }
}

if (!function_exists('vmseb_count_ticket_products_in_cart')) {
    function vmseb_count_ticket_products_in_cart(array $ticket_product_ids): int
    {
        if (empty($ticket_product_ids) || !function_exists('WC') || !WC()->cart) {
            return 0;
        }
        $lookup = array_fill_keys(array_map('absint', $ticket_product_ids), true);
        $count = 0;
        foreach (WC()->cart->get_cart() as $cart_item) {
            if (!is_array($cart_item)) {
                continue;
            }
            $product_id = absint($cart_item['product_id'] ?? 0);
            $variation_id = absint($cart_item['variation_id'] ?? 0);
            if (isset($lookup[$product_id]) || ($variation_id > 0 && isset($lookup[$variation_id]))) {
                $count += max(0, (int) ($cart_item['quantity'] ?? 0));
            }
        }
        return $count;
    }
}

if (!function_exists('vmseb_count_ticket_products_on_account')) {
    function vmseb_count_ticket_products_on_account(array $ticket_product_ids): int
    {
        if (empty($ticket_product_ids) || !is_user_logged_in() || !function_exists('wc_get_orders')) {
            return 0;
        }
        $lookup = array_fill_keys(array_map('absint', $ticket_product_ids), true);
        $user = wp_get_current_user();
        $order_ids = array();
        foreach ((array) wc_get_orders(array(
            'customer_id' => get_current_user_id(),
            'status'      => array('processing', 'completed', 'on-hold'),
            'limit'       => 100,
            'return'      => 'ids',
            'orderby'     => 'date',
            'order'       => 'DESC',
        )) as $order_id) {
            $order_ids[absint($order_id)] = true;
        }
        if ($user instanceof WP_User && !empty($user->user_email)) {
            foreach ((array) wc_get_orders(array(
                'billing_email' => sanitize_email((string) $user->user_email),
                'status'        => array('processing', 'completed', 'on-hold'),
                'limit'         => 100,
                'return'        => 'ids',
                'orderby'       => 'date',
                'order'         => 'DESC',
            )) as $order_id) {
                $order_ids[absint($order_id)] = true;
            }
        }

        $count = 0;
        foreach (array_keys($order_ids) as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order instanceof WC_Order) {
                continue;
            }
            foreach ($order->get_items('line_item') as $item_id => $item) {
                if (!$item instanceof WC_Order_Item_Product) {
                    continue;
                }
                $product_id = absint($item->get_product_id());
                $variation_id = absint($item->get_variation_id());
                if (!isset($lookup[$product_id]) && ($variation_id <= 0 || !isset($lookup[$variation_id]))) {
                    continue;
                }
                $qty = max(0, (int) $item->get_quantity());
                if (method_exists($order, 'get_qty_refunded_for_item')) {
                    $qty = max(0, $qty - abs((int) $order->get_qty_refunded_for_item($item_id)));
                }
                $count += $qty;
            }
        }
        return $count;
    }
}

if (!function_exists('vmseb_get_event_admission_state')) {
    function vmseb_get_event_admission_state(int $event_plan_id): array
    {
        $ticket_ids = vmseb_get_event_ticket_product_ids($event_plan_id);
        return array(
            'cart_count'    => vmseb_count_ticket_products_in_cart($ticket_ids),
            'account_count' => vmseb_count_ticket_products_on_account($ticket_ids),
            'logged_in'     => is_user_logged_in(),
            'verifiable'    => !empty($ticket_ids),
        );
    }
}

if (!function_exists('vmseb_get_cart_express_bar_event_summaries')) {
    function vmseb_get_cart_express_bar_event_summaries(int $exclude_event_plan_id = 0): array
    {
        if (!function_exists('WC') || !WC()->cart) {
            return array();
        }
        $events = array();
        foreach (WC()->cart->get_cart() as $cart_item) {
            if (!is_array($cart_item) || empty($cart_item['_vms_express_bar'])) {
                continue;
            }
            $event_plan_id = absint($cart_item['_vms_express_bar_event_plan_id'] ?? 0);
            if ($event_plan_id <= 0 || $event_plan_id === $exclude_event_plan_id) {
                continue;
            }
            if (!isset($events[$event_plan_id])) {
                $events[$event_plan_id] = array(
                    'id'    => $event_plan_id,
                    'title' => sanitize_text_field((string) ($cart_item['_vms_express_bar_event_plan_title'] ?? get_the_title($event_plan_id))),
                    'qty'   => 0,
                );
            }
            $events[$event_plan_id]['qty'] += max(0, (int) ($cart_item['quantity'] ?? 0));
        }
        return array_values($events);
    }
}

if (!function_exists('vmseb_build_cart_item_data_for_item')) {
    function vmseb_build_cart_item_data_for_item(int $event_plan_id, string $pickup_name, array $item, bool $age_gate_confirmed = false): array
    {
        return array(
            '_vms_express_bar'                  => 1,
            '_vms_express_bar_event_plan_id'    => $event_plan_id,
            '_vms_express_bar_event_plan_title' => get_the_title($event_plan_id),
            '_vms_express_bar_pickup_name'      => $pickup_name,
            '_vmseb_item_token'                 => (string) $item['token'],
            '_vmseb_item_label'                 => (string) $item['title'],
            '_vmseb_item_bucket_eligible'       => !empty($item['bucket_eligible']) ? '1' : '0',
            '_vmseb_item_age_gate'              => !empty($item['age_gate']) ? '1' : '0',
            '_vmseb_age_gate_confirmed'         => $age_gate_confirmed ? '1' : '0',
            '_vmseb_item_category'              => (string) $item['category'],
        );
    }
}

if (!function_exists('vmseb_get_bucket_product')) {
    function vmseb_get_bucket_product(): ?WC_Product
    {
        $settings = vmseb_get_settings();
        $product_id = absint($settings['bucket_product_id'] ?? 0);
        if ($product_id <= 0 || !vmseb_is_woocommerce_active()) {
            return null;
        }
        $product = wc_get_product($product_id);
        return $product instanceof WC_Product ? $product : null;
    }
}

if (!function_exists('vmseb_get_bucket_discount_snapshot')) {
    function vmseb_get_bucket_discount_snapshot(array $selected_quantities): array
    {
        $items = vmseb_get_catalog_items(true);
        $settings = vmseb_get_settings();
        $group_size = max(1, (int) $settings['bucket_group_size']);
        $eligible_count = 0;
        $regular_subtotal = 0.0;
        $eligible_prices = array();

        foreach ($selected_quantities as $token => $qty) {
            $qty = max(0, absint($qty));
            if ($qty <= 0 || empty($items[$token])) {
                continue;
            }
            $item = $items[$token];
            $regular_subtotal += ((float) $item['price']) * $qty;
            if (!empty($item['bucket_eligible'])) {
                $eligible_count += $qty;
                for ($i = 0; $i < $qty; $i++) {
                    $eligible_prices[] = (float) $item['price'];
                }
            }
        }

        rsort($eligible_prices, SORT_NUMERIC);
        $bucket_count = intdiv($eligible_count, $group_size);
        $bucket_product = vmseb_get_bucket_product();
        $bucket_price = $bucket_product instanceof WC_Product ? (float) wc_get_price_to_display($bucket_product) : 0.0;
        $discount = 0.0;
        if ($bucket_count > 0 && $bucket_price > 0) {
            $eligible_units_for_discount = array_slice($eligible_prices, 0, $bucket_count * $group_size);
            $discount = max(0.0, array_sum($eligible_units_for_discount) - ($bucket_count * $bucket_price));
        }

        return array(
            'regular_subtotal' => $regular_subtotal,
            'eligible_count'   => $eligible_count,
            'bucket_count'     => $bucket_count,
            'discount'         => $discount,
            'group_size'       => $group_size,
            'bucket_price'     => $bucket_price,
        );
    }
}

if (!function_exists('vmseb_get_cart_bucket_discount_snapshot')) {
    function vmseb_get_cart_bucket_discount_snapshot(): array
    {
        if (!vmseb_is_woocommerce_active() || !WC()->cart) {
            return array('bucket_count' => 0, 'discount' => 0.0, 'group_size' => 4);
        }
        $settings = vmseb_get_settings();
        $group_size = max(1, (int) $settings['bucket_group_size']);
        $eligible_prices = array();
        $eligible_count = 0;
        $bucket_product = vmseb_get_bucket_product();
        $bucket_price = $bucket_product instanceof WC_Product ? (float) wc_get_price_to_display($bucket_product) : 0.0;

        foreach (WC()->cart->get_cart() as $cart_item) {
            if (empty($cart_item['_vms_express_bar']) || empty($cart_item['_vmseb_item_bucket_eligible'])) {
                continue;
            }
            $qty = max(0, absint($cart_item['quantity'] ?? 0));
            if ($qty <= 0) {
                continue;
            }
            $eligible_count += $qty;
            $line_subtotal = isset($cart_item['line_subtotal']) ? (float) $cart_item['line_subtotal'] : 0.0;
            $unit_price = $qty > 0 ? ($line_subtotal / $qty) : 0.0;
            if ($unit_price <= 0 && isset($cart_item['data']) && $cart_item['data'] instanceof WC_Product) {
                $unit_price = (float) $cart_item['data']->get_price();
            }
            for ($i = 0; $i < $qty; $i++) {
                $eligible_prices[] = $unit_price;
            }
        }

        rsort($eligible_prices, SORT_NUMERIC);
        $bucket_count = intdiv($eligible_count, $group_size);
        $discount = 0.0;
        if ($bucket_count > 0 && $bucket_price > 0) {
            $discount = max(0.0, array_sum(array_slice($eligible_prices, 0, $bucket_count * $group_size)) - ($bucket_count * $bucket_price));
        }

        return array(
            'bucket_count' => $bucket_count,
            'discount'     => $discount,
            'group_size'   => $group_size,
        );
    }
}

if (!function_exists('vmseb_get_item_movement_summary_for_order')) {
    function vmseb_get_item_movement_summary_for_order(WC_Order $order): array
    {
        $summary = $order->get_meta('_vmseb_consumption_summary', true);
        return is_array($summary) ? $summary : array();
    }
}

if (!function_exists('vmseb_normalize_queue_status')) {
    function vmseb_normalize_queue_status(string $status): string
    {
        $status = sanitize_key($status);
        if ($status === 'new' || $status === '') {
            return 'pending';
        }
        if (in_array($status, array('pending', 'ready', 'completed'), true)) {
            return $status;
        }
        return 'pending';
    }
}

if (!function_exists('vmseb_collect_order_express_bar_data')) {
    function vmseb_collect_order_express_bar_data(WC_Order $order): array
    {
        $event_ids = array();
        $summary = array();
        $age_gate_confirmed = false;
        $has_bar_items = false;

        foreach ($order->get_items('line_item') as $item) {
            if ((string) $item->get_meta('_vms_express_bar', true) !== '1') {
                continue;
            }

            $has_bar_items = true;

            $event_id = absint($item->get_meta('_vms_express_bar_event_plan_id', true));
            if ($event_id > 0) {
                $event_ids[] = $event_id;
            }

            if ((string) $item->get_meta('_vmseb_age_gate_confirmed', true) === '1') {
                $age_gate_confirmed = true;
            }

            $token = sanitize_text_field((string) $item->get_meta('_vmseb_item_token', true));
            if ($token === '') {
                continue;
            }

            if (!isset($summary[$token])) {
                $label = sanitize_text_field((string) $item->get_meta('_vmseb_item_label', true));
                if ($label === '') {
                    $label = sanitize_text_field($item->get_name());
                }
                $summary[$token] = array(
                    'label' => $label,
                    'qty'   => 0,
                );
            }

            $summary[$token]['qty'] += max(1, absint($item->get_quantity()));
        }

        return array(
            'has_bar_items'       => $has_bar_items,
            'event_ids'           => array_values(array_unique($event_ids)),
            'summary'             => $summary,
            'age_gate_confirmed'  => $age_gate_confirmed,
        );
    }
}

if (!function_exists('vmseb_sync_order_meta_from_items')) {
    function vmseb_sync_order_meta_from_items(WC_Order $order): bool
    {
        $payload = vmseb_collect_order_express_bar_data($order);

        if (empty($payload['has_bar_items'])) {
            $order->delete_meta_data('_vms_express_bar_order');
            $order->delete_meta_data('_vms_express_bar_event_plan_ids');
            $order->delete_meta_data('_vms_express_bar_queue_status');
            $order->delete_meta_data('_vms_express_bar_id_verified');
            $order->delete_meta_data('_vmseb_consumption_summary');
            $order->delete_meta_data('_vmseb_age_gate_confirmed');
            return false;
        }

        $order->update_meta_data('_vms_express_bar_order', '1');

        if (!empty($payload['event_ids'])) {
            $order->update_meta_data('_vms_express_bar_event_plan_ids', implode(',', $payload['event_ids']));
        } else {
            $order->delete_meta_data('_vms_express_bar_event_plan_ids');
        }

        $queue_status = vmseb_normalize_queue_status((string) $order->get_meta('_vms_express_bar_queue_status', true));
        $order->update_meta_data('_vms_express_bar_queue_status', $queue_status);

        $id_verified = (string) $order->get_meta('_vms_express_bar_id_verified', true);
        $order->update_meta_data('_vms_express_bar_id_verified', $id_verified === '1' ? '1' : '0');

        if (!empty($payload['summary'])) {
            $order->update_meta_data('_vmseb_consumption_summary', $payload['summary']);
        } else {
            $order->delete_meta_data('_vmseb_consumption_summary');
        }

        if (!empty($payload['age_gate_confirmed'])) {
            $order->update_meta_data('_vmseb_age_gate_confirmed', '1');
        } else {
            $order->delete_meta_data('_vmseb_age_gate_confirmed');
        }

        return true;
    }
}
