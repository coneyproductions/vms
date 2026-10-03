<?php
defined('ABSPATH') || exit;

add_filter('pre_option_vms_discounts_tips_enabled', 'vmseb_disable_legacy_discount_tip_ui', 20, 3);
add_action('wp_enqueue_scripts', 'vmseb_tips_enqueue_assets', 20);
add_action('woocommerce_checkout_update_order_review', 'vmseb_tips_capture_checkout_selection', 20, 1);
add_action('woocommerce_cart_calculate_fees', 'vmseb_tips_add_fee', 40, 1);
add_action('woocommerce_review_order_before_order_total', 'vmseb_tips_render_checkout_picker', 15);
add_action('woocommerce_checkout_create_order_fee_item', 'vmseb_tips_mark_fee_item', 20, 4);
add_action('woocommerce_checkout_create_order', 'vmseb_tips_persist_order_meta', 35, 2);
add_action('woocommerce_checkout_order_created', 'vmseb_tips_convert_fee_to_product_line', 50, 1);
add_action('woocommerce_thankyou', 'vmseb_tips_clear_selection', 20);

if (!function_exists('vmseb_tips_enabled')) {
    function vmseb_tips_enabled(): bool
    {
        $settings = vmseb_get_settings();
        return !empty($settings['tips_enabled']);
    }
}

if (!function_exists('vmseb_disable_legacy_discount_tip_ui')) {
    /**
     * Express Bar 0.6.30+ owns its own gratuity UX. Keep the old Commerce Discounts
     * options intact for rollback/report compatibility, but prevent its picker/fee
     * from running at the same time.
     */
    function vmseb_disable_legacy_discount_tip_ui($pre_option, $option = '', $default = false)
    {
        unset($option, $default);
        return 'no';
    }
}

if (!function_exists('vmseb_tips_allowed_percentages')) {
    function vmseb_tips_allowed_percentages(): array
    {
        return array(15, 20, 25);
    }
}

if (!function_exists('vmseb_tips_max_custom_amount')) {
    function vmseb_tips_max_custom_amount(): float
    {
        $settings = vmseb_get_settings();
        return max(1.0, (float) ($settings['tips_max_custom_amount'] ?? 100));
    }
}

if (!function_exists('vmseb_tips_cart_item_is_eligible')) {
    function vmseb_tips_cart_item_is_eligible(array $cart_item): bool
    {
        return !empty($cart_item['_vms_express_bar'])
            || absint($cart_item['_vms_express_bar_event_plan_id'] ?? 0) > 0
            || !empty($cart_item['_vmseb_item_token']);
    }
}

if (!function_exists('vmseb_tips_cart_has_eligible_items')) {
    function vmseb_tips_cart_has_eligible_items($cart = null): bool
    {
        if ($cart === null && function_exists('WC') && WC()->cart) {
            $cart = WC()->cart;
        }
        if (!is_object($cart) || !method_exists($cart, 'get_cart')) {
            return false;
        }
        foreach ($cart->get_cart() as $cart_item) {
            if (is_array($cart_item) && vmseb_tips_cart_item_is_eligible($cart_item)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('vmseb_tips_eligible_subtotal')) {
    function vmseb_tips_eligible_subtotal($cart = null): float
    {
        if ($cart === null && function_exists('WC') && WC()->cart) {
            $cart = WC()->cart;
        }
        if (!is_object($cart) || !method_exists($cart, 'get_cart')) {
            return 0.0;
        }
        $subtotal = 0.0;
        foreach ($cart->get_cart() as $cart_item) {
            if (!is_array($cart_item) || !vmseb_tips_cart_item_is_eligible($cart_item)) {
                continue;
            }
            if (isset($cart_item['line_total'])) {
                $subtotal += max(0.0, (float) $cart_item['line_total']);
                continue;
            }
            $qty = max(0, (int) ($cart_item['quantity'] ?? 0));
            $product = $cart_item['data'] ?? null;
            if ($qty > 0 && is_object($product) && method_exists($product, 'get_price')) {
                $subtotal += max(0.0, (float) $product->get_price()) * $qty;
            }
        }
        $decimals = function_exists('wc_get_price_decimals') ? wc_get_price_decimals() : 2;
        return round($subtotal, $decimals);
    }
}

if (!function_exists('vmseb_tips_normalize_selection')) {
    function vmseb_tips_normalize_selection($raw): string
    {
        $raw = strtolower(trim((string) $raw));
        if ($raw === '' || $raw === 'none' || $raw === 'no_tip') {
            return 'none';
        }
        if (strpos($raw, 'pct:') === 0) {
            $percent = (int) substr($raw, 4);
            return in_array($percent, vmseb_tips_allowed_percentages(), true) ? 'pct:' . $percent : 'none';
        }
        if (strpos($raw, 'custom:') === 0) {
            $amount = (float) preg_replace('/[^0-9.]/', '', substr($raw, 7));
            $amount = max(0.0, min(vmseb_tips_max_custom_amount(), $amount));
            $decimals = function_exists('wc_get_price_decimals') ? wc_get_price_decimals() : 2;
            $amount = round($amount, $decimals);
            return $amount > 0 ? 'custom:' . number_format($amount, $decimals, '.', '') : 'none';
        }
        return 'none';
    }
}

if (!function_exists('vmseb_tips_get_selection')) {
    function vmseb_tips_get_selection(): string
    {
        if (!function_exists('WC') || !WC()->session) {
            return 'none';
        }
        return vmseb_tips_normalize_selection(WC()->session->get('vmseb_tip_selection', 'none'));
    }
}

if (!function_exists('vmseb_tips_set_selection')) {
    function vmseb_tips_set_selection(string $selection): void
    {
        if (!function_exists('WC') || !WC()->session) {
            return;
        }
        WC()->session->set('vmseb_tip_selection', vmseb_tips_normalize_selection($selection));
    }
}

if (!function_exists('vmseb_tips_calculate_amount')) {
    function vmseb_tips_calculate_amount($cart = null, ?string $selection = null): float
    {
        $selection = vmseb_tips_normalize_selection($selection ?? vmseb_tips_get_selection());
        $subtotal = vmseb_tips_eligible_subtotal($cart);
        $decimals = function_exists('wc_get_price_decimals') ? wc_get_price_decimals() : 2;
        if ($subtotal <= 0 || $selection === 'none') {
            return 0.0;
        }
        if (strpos($selection, 'pct:') === 0) {
            $percent = (int) substr($selection, 4);
            return in_array($percent, vmseb_tips_allowed_percentages(), true)
                ? round($subtotal * ($percent / 100), $decimals)
                : 0.0;
        }
        if (strpos($selection, 'custom:') === 0) {
            return round(max(0.0, min(vmseb_tips_max_custom_amount(), (float) substr($selection, 7))), $decimals);
        }
        return 0.0;
    }
}

if (!function_exists('vmseb_tips_enqueue_assets')) {
    function vmseb_tips_enqueue_assets(): void
    {
        if (!vmseb_tips_enabled() || !function_exists('is_checkout') || !is_checkout() || (function_exists('is_order_received_page') && is_order_received_page())) {
            return;
        }
        wp_enqueue_style('vmseb-tips', VMSEB_URL . 'assets/css/tips.css', array(), VMSEB_VERSION);
        wp_enqueue_script('vmseb-tips', VMSEB_URL . 'assets/js/tips.js', array('jquery'), VMSEB_VERSION, true);
    }
}

if (!function_exists('vmseb_tips_capture_checkout_selection')) {
    function vmseb_tips_capture_checkout_selection(string $posted_data): void
    {
        if (!vmseb_tips_enabled()) {
            return;
        }
        if (!vmseb_tips_cart_has_eligible_items()) {
            vmseb_tips_set_selection('none');
            return;
        }
        $data = array();
        parse_str($posted_data, $data);
        if (array_key_exists('vmseb_tip_selection', $data)) {
            vmseb_tips_set_selection((string) $data['vmseb_tip_selection']);
        }
    }
}

if (!function_exists('vmseb_tips_add_fee')) {
    function vmseb_tips_add_fee($cart): void
    {
        if ((is_admin() && !wp_doing_ajax()) || !vmseb_tips_enabled()) {
            return;
        }
        if (!vmseb_tips_cart_has_eligible_items($cart)) {
            vmseb_tips_set_selection('none');
            return;
        }
        $amount = vmseb_tips_calculate_amount($cart);
        if ($amount > 0 && is_object($cart) && method_exists($cart, 'add_fee')) {
            $cart->add_fee('Bar Staff Tip', $amount, false, '');
        }
    }
}

if (!function_exists('vmseb_tips_selection_summary')) {
    function vmseb_tips_selection_summary(string $selection, float $amount): string
    {
        $amount_text = function_exists('wc_price') ? wp_strip_all_tags(wc_price($amount)) : '$' . number_format($amount, 2);
        if (strpos($selection, 'pct:') === 0) {
            return sprintf('%d%% (%s)', (int) substr($selection, 4), $amount_text);
        }
        if (strpos($selection, 'custom:') === 0) {
            return sprintf('Custom (%s)', $amount_text);
        }
        return '';
    }
}

if (!function_exists('vmseb_tips_render_checkout_picker')) {
    function vmseb_tips_render_checkout_picker(): void
    {
        if (!vmseb_tips_enabled() || !vmseb_tips_cart_has_eligible_items()) {
            return;
        }
        $selection = vmseb_tips_get_selection();
        $subtotal = vmseb_tips_eligible_subtotal();
        $amount = vmseb_tips_calculate_amount(null, $selection);
        $has_tip = $amount > 0 && $selection !== 'none';
        $custom_selected = strpos($selection, 'custom:') === 0;
        $custom_amount = $custom_selected ? (float) substr($selection, 7) : 0.0;
        ?>
        <tr class="vmseb-tip-row">
            <td colspan="2">
                <section class="vmseb-tip-box" data-vmseb-tip-box>
                    <input type="hidden" name="vmseb_tip_selection" value="<?php echo esc_attr($selection); ?>" data-vmseb-tip-selection />
                    <?php if ($has_tip) : ?>
                        <div class="vmseb-tip-confirmation" data-vmseb-tip-confirmation>
                            <strong>✓ Tip added — <?php echo esc_html(vmseb_tips_selection_summary($selection, $amount)); ?></strong>
                            <button type="button" class="button" data-vmseb-tip-change>Change</button>
                        </div>
                    <?php endif; ?>
                    <div class="vmseb-tip-picker" data-vmseb-tip-picker <?php echo $has_tip ? 'hidden' : ''; ?>>
                        <div class="vmseb-tip-copy">
                            <strong>Add a tip for the bar staff?</strong>
                            <span>Optional. The percentage is calculated only on your Express Bar items.</span>
                        </div>
                        <div class="vmseb-tip-options" role="group" aria-label="Bar staff tip options">
                            <?php foreach (vmseb_tips_allowed_percentages() as $percent) : ?>
                                <?php
                                $candidate = round($subtotal * ($percent / 100), function_exists('wc_get_price_decimals') ? wc_get_price_decimals() : 2);
                                $key = 'pct:' . $percent;
                                ?>
                                <button type="button" class="button vmseb-tip-option<?php echo $selection === $key ? ' is-selected' : ''; ?>" data-vmseb-tip-option="<?php echo esc_attr($key); ?>" aria-pressed="<?php echo $selection === $key ? 'true' : 'false'; ?>">
                                    <span class="vmseb-tip-percent"><?php echo esc_html($percent . '%'); ?></span>
                                    <span class="vmseb-tip-dollar"><?php echo wp_kses_post(wc_price($candidate)); ?></span>
                                </button>
                            <?php endforeach; ?>
                            <button type="button" class="button vmseb-tip-custom-toggle<?php echo $custom_selected ? ' is-selected' : ''; ?>" data-vmseb-tip-custom-toggle aria-expanded="<?php echo $custom_selected ? 'true' : 'false'; ?>">Custom</button>
                            <button type="button" class="button vmseb-tip-option<?php echo $selection === 'none' ? ' is-selected' : ''; ?>" data-vmseb-tip-option="none" aria-pressed="<?php echo $selection === 'none' ? 'true' : 'false'; ?>">No tip</button>
                        </div>
                        <div class="vmseb-tip-custom" data-vmseb-tip-custom <?php echo $custom_selected ? '' : 'hidden'; ?>>
                            <label for="vmseb-tip-custom-amount">Custom tip amount</label>
                            <div class="vmseb-tip-custom-controls">
                                <span aria-hidden="true">$</span>
                                <input id="vmseb-tip-custom-amount" type="number" min="0" max="<?php echo esc_attr((string) vmseb_tips_max_custom_amount()); ?>" step="0.01" inputmode="decimal" value="<?php echo esc_attr($custom_amount > 0 ? number_format($custom_amount, 2, '.', '') : ''); ?>" placeholder="0.00" data-vmseb-tip-custom-amount />
                                <button type="button" class="button" data-vmseb-tip-custom-apply>Apply</button>
                            </div>
                            <small><?php echo esc_html(sprintf('Maximum custom tip: $%s', number_format(vmseb_tips_max_custom_amount(), 2))); ?></small>
                        </div>
                    </div>
                </section>
            </td>
        </tr>
        <?php
    }
}

if (!function_exists('vmseb_tips_mark_fee_item')) {
    function vmseb_tips_mark_fee_item($item, string $fee_key, $fee, $order): void
    {
        unset($fee_key, $order);
        if (!is_object($item) || !method_exists($item, 'add_meta_data')) {
            return;
        }
        $fee_name = is_object($fee) && isset($fee->name) ? (string) $fee->name : '';
        if ($fee_name !== 'Bar Staff Tip') {
            return;
        }
        $amount = vmseb_tips_calculate_amount();
        if ($amount <= 0) {
            return;
        }
        $item->add_meta_data('_vms_discounts_is_tip', 'yes', true);
        $item->add_meta_data('_vms_discounts_tip_amount', $amount, true);
        $item->add_meta_data('_vms_discounts_tip_scope', 'express_bar_orders', true);
        $item->add_meta_data('_vms_express_bar_tip_selection', vmseb_tips_get_selection(), true);
    }
}

if (!function_exists('vmseb_tips_persist_order_meta')) {
    function vmseb_tips_persist_order_meta($order, array $posted_data): void
    {
        unset($posted_data);
        if (!is_object($order) || !method_exists($order, 'update_meta_data')) {
            return;
        }
        $amount = vmseb_tips_calculate_amount();
        if ($amount <= 0) {
            return;
        }
        $order->update_meta_data('_vms_discounts_tip_amount', $amount);
        $order->update_meta_data('_vms_discounts_tip_label', 'Bar Staff Tip');
        $order->update_meta_data('_vms_discounts_tip_scope', 'express_bar_orders');
        $order->update_meta_data('_vms_express_bar_tip_selection', vmseb_tips_get_selection());
        $order->update_meta_data('_vms_express_bar_tip_eligible_subtotal', vmseb_tips_eligible_subtotal());
    }
}

if (!function_exists('vmseb_tips_clear_selection')) {
    function vmseb_tips_clear_selection($order_id = null): void
    {
        unset($order_id);
        vmseb_tips_set_selection('none');
    }
}


if (!function_exists('vmseb_tips_carrier_sku')) {
    function vmseb_tips_carrier_sku(): string
    {
        return 'VMS-ONLINE-TIP-CARRIER';
    }
}

if (!function_exists('vmseb_tips_get_carrier_product')) {
    /**
     * Return the hidden Woo product used only to represent online Express Bar
     * gratuity as a product line in the final order. The checkout still uses a
     * fee while the customer is choosing/reviewing the tip; productization is
     * intentionally delayed until after the order is created.
     */
    function vmseb_tips_get_carrier_product()
    {
        if (!function_exists('wc_get_product') || !class_exists('WC_Product_Simple')) {
            return null;
        }

        $product_id = absint(get_option('vmseb_online_tip_carrier_product_id', 0));
        $product = $product_id > 0 ? wc_get_product($product_id) : null;
        if (is_object($product) && method_exists($product, 'get_sku') && (string) $product->get_sku() === vmseb_tips_carrier_sku()) {
            return $product;
        }

        if (function_exists('wc_get_product_id_by_sku')) {
            $product_id = absint(wc_get_product_id_by_sku(vmseb_tips_carrier_sku()));
            $product = $product_id > 0 ? wc_get_product($product_id) : null;
            if (is_object($product)) {
                update_option('vmseb_online_tip_carrier_product_id', $product_id, false);
                update_post_meta($product_id, '_vms_product_role', 'online_tip');
                update_post_meta($product_id, '_vmseb_online_tip_carrier', 'yes');
                return $product;
            }
        }

        try {
            $product = new WC_Product_Simple();
            $product->set_name('Online Tip (Internal)');
            $product->set_sku(vmseb_tips_carrier_sku());
            $product->set_status('private');
            $product->set_catalog_visibility('hidden');
            $product->set_virtual(true);
            $product->set_tax_status('none');
            $product->set_regular_price('0');
            $product->set_price('0');
            $product->set_manage_stock(false);
            $product_id = absint($product->save());
        } catch (Throwable $e) {
            return null;
        }

        if ($product_id <= 0) {
            return null;
        }
        update_post_meta($product_id, '_vms_product_role', 'online_tip');
        update_post_meta($product_id, '_vmseb_online_tip_carrier', 'yes');
        update_option('vmseb_online_tip_carrier_product_id', $product_id, false);
        return wc_get_product($product_id);
    }
}

if (!function_exists('vmseb_tips_order_already_productized')) {
    function vmseb_tips_order_already_productized($order): bool
    {
        if (!is_object($order) || !method_exists($order, 'get_items')) {
            return false;
        }
        foreach ($order->get_items('line_item') as $item) {
            if (!is_object($item) || !method_exists($item, 'get_meta')) {
                continue;
            }
            if ((string) $item->get_meta('_vmseb_online_tip', true) === 'yes') {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('vmseb_tips_convert_fee_to_product_line')) {
    /**
     * Convert the persisted Express Bar tip fee to a hidden product line before
     * the payment gateway processes the order. WooCommerce Square can attach a
     * Square catalog variation only to WC_Order_Item_Product lines, so this
     * preserves the checkout UX/totals while allowing ONLINE TIPS reporting to
     * remain separate from native Square/POS gratuity.
     */
    function vmseb_tips_convert_fee_to_product_line($order): void
    {
        if (!is_object($order) || !method_exists($order, 'get_fees') || !method_exists($order, 'add_item')) {
            return;
        }
        if (vmseb_tips_order_already_productized($order)) {
            return;
        }

        $tip_fee = null;
        foreach ($order->get_fees() as $fee) {
            if (!is_object($fee)) {
                continue;
            }
            $marked = method_exists($fee, 'get_meta') && (string) $fee->get_meta('_vms_discounts_is_tip', true) === 'yes';
            $named = method_exists($fee, 'get_name') && (string) $fee->get_name() === 'Bar Staff Tip';
            if ($marked || $named) {
                $tip_fee = $fee;
                break;
            }
        }
        if (!$tip_fee) {
            return;
        }

        $amount = method_exists($tip_fee, 'get_total') ? (float) $tip_fee->get_total() : 0.0;
        if ($amount <= 0) {
            return;
        }

        $product = vmseb_tips_get_carrier_product();
        if (!is_object($product)) {
            if (method_exists($order, 'update_meta_data')) {
                $order->update_meta_data('_vmseb_online_tip_productize_error', 'carrier_product_unavailable');
                $order->save_meta_data();
            }
            return;
        }

        $original_total = method_exists($order, 'get_total') ? (float) $order->get_total() : 0.0;
        $selection = method_exists($tip_fee, 'get_meta') ? (string) $tip_fee->get_meta('_vms_express_bar_tip_selection', true) : '';
        if ($selection === '') {
            $selection = vmseb_tips_get_selection();
        }

        try {
            $item = new WC_Order_Item_Product();
            $item->set_product($product);
            $item->set_name('Bar Staff Tip');
            $item->set_quantity(1);
            $item->set_subtotal($amount);
            $item->set_total($amount);
            $item->set_subtotal_tax(0);
            $item->set_total_tax(0);
            $item->set_taxes(array('subtotal' => array(), 'total' => array()));
            $item->add_meta_data('_vmseb_online_tip', 'yes', true);
            $item->add_meta_data('_vms_discounts_is_tip', 'yes', true);
            $item->add_meta_data('_vms_discounts_tip_amount', $amount, true);
            $item->add_meta_data('_vms_discounts_tip_scope', 'express_bar_orders', true);
            $item->add_meta_data('_vms_express_bar_tip_selection', $selection, true);

            $fee_id = method_exists($tip_fee, 'get_id') ? absint($tip_fee->get_id()) : 0;
            if ($fee_id > 0 && method_exists($order, 'remove_item')) {
                $order->remove_item($fee_id);
            }
            $order->add_item($item);
            if (method_exists($order, 'set_total')) {
                $order->set_total($original_total);
            }
            $order->update_meta_data('_vmseb_online_tip_productized', 'yes');
            $order->save();
        } catch (Throwable $e) {
            $order->update_meta_data('_vmseb_online_tip_productize_error', sanitize_text_field($e->getMessage()));
            $order->save_meta_data();
        }
    }
}
