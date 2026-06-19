<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_express_bar_get_event_meta')) {
    function vms_express_bar_get_event_meta(int $event_plan_id): array
    {
        $enabled = (string) get_post_meta($event_plan_id, '_vms_express_bar_enabled', true) === '1';
        $product_ids_raw = (string) get_post_meta($event_plan_id, '_vms_express_bar_product_ids', true);
        $headline = (string) get_post_meta($event_plan_id, '_vms_express_bar_headline', true);
        $pickup = (string) get_post_meta($event_plan_id, '_vms_express_bar_pickup_instructions', true);

        $product_ids = array_values(array_filter(array_map('absint', preg_split('/[^0-9]+/', $product_ids_raw) ?: array())));
        $product_ids = array_values(array_unique(array_filter($product_ids)));

        return array(
            'enabled' => $enabled,
            'product_ids' => $product_ids,
            'headline' => $headline,
            'pickup_instructions' => $pickup,
        );
    }
}

if (!function_exists('vms_express_bar_shortcode')) {
    function vms_express_bar_shortcode(array $atts = array()): string
    {
        if (!class_exists('WooCommerce')) {
            return '<div class="vms-express-bar"><p>' . esc_html__('Express Bar requires WooCommerce.', 'vms') . '</p></div>';
        }

        $atts = shortcode_atts(array(
            'event_plan_id' => 0,
        ), $atts, 'vms_express_bar_menu');

        $event_plan_id = absint($atts['event_plan_id']);
        if ($event_plan_id <= 0) {
            global $post;
            if ($post instanceof WP_Post && $post->post_type === 'vms_event_plan') {
                $event_plan_id = (int) $post->ID;
            }
        }

        if ($event_plan_id <= 0 || get_post_type($event_plan_id) !== 'vms_event_plan') {
            return '<div class="vms-express-bar"><p>' . esc_html__('Invalid Event Plan.', 'vms') . '</p></div>';
        }

        $cfg = vms_express_bar_get_event_meta($event_plan_id);
        if (empty($cfg['enabled'])) {
            return '';
        }

        $product_ids = (array) ($cfg['product_ids'] ?? array());
        if (empty($product_ids)) {
            return '<div class="vms-express-bar"><p>' . esc_html__('Express Bar is enabled, but no products have been selected yet.', 'vms') . '</p></div>';
        }

        $event_title = get_the_title($event_plan_id);
        $headline = trim((string) ($cfg['headline'] ?: 'Skip the line. Order now and pick up at the bar. ID is checked in person before alcohol is handed over.'));
        $pickup = trim((string) ($cfg['pickup_instructions'] ?: 'Bring your order confirmation and a valid ID. Alcohol is not handed over until a staff member checks ID in person.'));

        ob_start();
        ?>
        <div class="vms-express-bar">
            <div class="vms-express-bar__header">
                <h3><?php echo esc_html($headline); ?></h3>
                <p><strong><?php echo esc_html($event_title); ?></strong></p>
                <p><?php echo esc_html($pickup); ?></p>
            </div>

            <div class="vms-express-bar__products">
                <?php foreach ($product_ids as $product_id) : ?>
                    <?php
                    $product = wc_get_product($product_id);
                    if (!$product) {
                        continue;
                    }
                    $permalink = get_permalink();
                    ?>
                    <div class="vms-express-bar__product">
                        <h4><?php echo esc_html($product->get_name()); ?></h4>
                        <?php if ($product->get_short_description()) : ?>
                            <p><?php echo wp_kses_post(wpautop($product->get_short_description())); ?></p>
                        <?php elseif ($product->get_description()) : ?>
                            <p><?php echo wp_kses_post(wp_trim_words($product->get_description(), 30)); ?></p>
                        <?php endif; ?>
                        <p><strong><?php echo wp_kses_post($product->get_price_html()); ?></strong></p>

                        <form method="post" action="<?php echo esc_url(wc_get_cart_url()); ?>" class="cart vms-express-bar__form">
                            <input type="hidden" name="add-to-cart" value="<?php echo (int) $product_id; ?>" />
                            <input type="hidden" name="quantity" value="1" />
                            <input type="hidden" name="vms_express_bar" value="1" />
                            <input type="hidden" name="vms_express_bar_event_plan_id" value="<?php echo (int) $event_plan_id; ?>" />
                            <input type="hidden" name="vms_express_bar_redirect" value="<?php echo esc_url($permalink ?: ''); ?>" />
                            <?php wp_nonce_field('vms_express_bar_add_' . $product_id, 'vms_express_bar_nonce'); ?>
                            <label>
                                <span><?php echo esc_html__('Quantity', 'vms'); ?></span><br />
                                <input type="number" name="vms_express_bar_quantity" min="1" max="20" step="1" value="1" inputmode="numeric" />
                            </label>
                            <p>
                                <label>
                                    <span><?php echo esc_html__('Pickup name (optional)', 'vms'); ?></span><br />
                                    <input type="text" name="vms_express_bar_pickup_name" value="" maxlength="120" />
                                </label>
                            </p>
                            <p>
                                <button type="submit" class="button alt"><?php echo esc_html__('Add to cart', 'vms'); ?></button>
                            </p>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}
add_shortcode('vms_express_bar_menu', 'vms_express_bar_shortcode');

if (!function_exists('vms_express_bar_capture_cart_item_data')) {
    function vms_express_bar_capture_cart_item_data(array $cart_item_data, int $product_id, int $variation_id): array
    {
        unset($variation_id);

        if (empty($_POST['vms_express_bar']) || empty($_POST['vms_express_bar_event_plan_id'])) {
            return $cart_item_data;
        }

        $nonce = isset($_POST['vms_express_bar_nonce']) ? sanitize_text_field(wp_unslash($_POST['vms_express_bar_nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'vms_express_bar_add_' . $product_id)) {
            return $cart_item_data;
        }

        $event_plan_id = absint($_POST['vms_express_bar_event_plan_id']);
        if ($event_plan_id <= 0 || get_post_type($event_plan_id) !== 'vms_event_plan') {
            return $cart_item_data;
        }

        $cfg = vms_express_bar_get_event_meta($event_plan_id);
        if (empty($cfg['enabled']) || !in_array($product_id, (array) $cfg['product_ids'], true)) {
            return $cart_item_data;
        }

        $qty = isset($_POST['vms_express_bar_quantity']) ? absint($_POST['vms_express_bar_quantity']) : 1;
        if ($qty <= 0) {
            $qty = 1;
        }
        $_REQUEST['quantity'] = $qty;
        $_POST['quantity'] = $qty;

        $pickup_name = isset($_POST['vms_express_bar_pickup_name']) ? sanitize_text_field(wp_unslash($_POST['vms_express_bar_pickup_name'])) : '';

        $cart_item_data['_vms_express_bar'] = 1;
        $cart_item_data['_vms_express_bar_event_plan_id'] = $event_plan_id;
        $cart_item_data['_vms_express_bar_event_plan_title'] = get_the_title($event_plan_id);
        if ($pickup_name !== '') {
            $cart_item_data['_vms_express_bar_pickup_name'] = $pickup_name;
        }

        return $cart_item_data;
    }
}
add_filter('woocommerce_add_cart_item_data', 'vms_express_bar_capture_cart_item_data', 10, 3);

if (!function_exists('vms_express_bar_validate_add_to_cart')) {
    function vms_express_bar_validate_add_to_cart(bool $passed, int $product_id, int $quantity): bool
    {
        unset($quantity);
        if (empty($_POST['vms_express_bar']) || empty($_POST['vms_express_bar_event_plan_id'])) {
            return $passed;
        }

        $event_plan_id = absint($_POST['vms_express_bar_event_plan_id']);
        $cfg = vms_express_bar_get_event_meta($event_plan_id);
        if (empty($cfg['enabled']) || !in_array($product_id, (array) $cfg['product_ids'], true)) {
            wc_add_notice(__('That product is not enabled for this event’s Express Bar menu.', 'vms'), 'error');
            return false;
        }

        return $passed;
    }
}
add_filter('woocommerce_add_to_cart_validation', 'vms_express_bar_validate_add_to_cart', 10, 3);

if (!function_exists('vms_express_bar_maybe_redirect_after_add')) {
    function vms_express_bar_maybe_redirect_after_add(string $url): string
    {
        if (empty($_REQUEST['vms_express_bar_redirect'])) {
            return $url;
        }
        $redirect = esc_url_raw(wp_unslash($_REQUEST['vms_express_bar_redirect']));
        return $redirect !== '' ? $redirect : $url;
    }
}
add_filter('woocommerce_add_to_cart_redirect', 'vms_express_bar_maybe_redirect_after_add');

if (!function_exists('vms_express_bar_cart_item_data')) {
    function vms_express_bar_cart_item_data(array $item_data, array $cart_item): array
    {
        if (!empty($cart_item['_vms_express_bar_event_plan_title'])) {
            $item_data[] = array(
                'name' => __('Express Bar Event', 'vms'),
                'value' => wc_clean((string) $cart_item['_vms_express_bar_event_plan_title']),
            );
        }
        if (!empty($cart_item['_vms_express_bar_pickup_name'])) {
            $item_data[] = array(
                'name' => __('Pickup Name', 'vms'),
                'value' => wc_clean((string) $cart_item['_vms_express_bar_pickup_name']),
            );
        }
        return $item_data;
    }
}
add_filter('woocommerce_get_item_data', 'vms_express_bar_cart_item_data', 10, 2);

if (!function_exists('vms_express_bar_add_order_meta')) {
    function vms_express_bar_add_order_meta(WC_Order $order, array $data): void
    {
        unset($data);
        $event_ids = array();
        foreach (WC()->cart ? WC()->cart->get_cart() : array() as $cart_item) {
            if (empty($cart_item['_vms_express_bar']) || empty($cart_item['_vms_express_bar_event_plan_id'])) {
                continue;
            }
            $event_ids[] = absint($cart_item['_vms_express_bar_event_plan_id']);
        }
        $event_ids = array_values(array_unique(array_filter($event_ids)));
        if (!empty($event_ids)) {
            $order->update_meta_data('_vms_express_bar_order', '1');
            $order->update_meta_data('_vms_express_bar_event_plan_ids', implode(',', $event_ids));
            $order->update_meta_data('_vms_express_bar_queue_status', 'new');
            $order->update_meta_data('_vms_express_bar_id_verified', '0');
        }
    }
}
add_action('woocommerce_checkout_create_order', 'vms_express_bar_add_order_meta', 10, 2);

if (!function_exists('vms_express_bar_add_order_line_item_meta')) {
    function vms_express_bar_add_order_line_item_meta(WC_Order_Item_Product $item, string $cart_item_key, array $values, WC_Order $order): void
    {
        unset($cart_item_key, $order);
        if (empty($values['_vms_express_bar'])) {
            return;
        }
        $item->add_meta_data('_vms_express_bar', '1', true);
        if (!empty($values['_vms_express_bar_event_plan_id'])) {
            $item->add_meta_data('_vms_express_bar_event_plan_id', absint($values['_vms_express_bar_event_plan_id']), true);
        }
        if (!empty($values['_vms_express_bar_event_plan_title'])) {
            $item->add_meta_data('_vms_express_bar_event_plan_title', sanitize_text_field((string) $values['_vms_express_bar_event_plan_title']), true);
        }
        if (!empty($values['_vms_express_bar_pickup_name'])) {
            $item->add_meta_data('_vms_express_bar_pickup_name', sanitize_text_field((string) $values['_vms_express_bar_pickup_name']), true);
        }
    }
}
add_action('woocommerce_checkout_create_order_line_item', 'vms_express_bar_add_order_line_item_meta', 10, 4);
