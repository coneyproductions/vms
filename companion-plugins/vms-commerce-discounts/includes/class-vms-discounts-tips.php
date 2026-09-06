<?php

if (!defined('ABSPATH')) {
    exit;
}

class VMS_Discounts_Tips
{
    public const ENABLED_OPTION_KEY = 'vms_discounts_tips_enabled';
    public const SCOPE_OPTION_KEY = 'vms_discounts_tips_scope';
    public const LABEL_OPTION_KEY = 'vms_discounts_tips_label';
    public const HEADING_OPTION_KEY = 'vms_discounts_tips_heading';
    public const DESCRIPTION_OPTION_KEY = 'vms_discounts_tips_description';
    public const PRESETS_OPTION_KEY = 'vms_discounts_tips_presets';
    public const ALLOW_CUSTOM_OPTION_KEY = 'vms_discounts_tips_allow_custom';
    public const TAXABLE_OPTION_KEY = 'vms_discounts_tips_taxable';
    public const MAX_AMOUNT_OPTION_KEY = 'vms_discounts_tips_max_amount';

    public const SESSION_AMOUNT_KEY = 'vms_discounts_tip_amount';

    public const META_ORDER_TIP_AMOUNT = '_vms_discounts_tip_amount';
    public const META_ORDER_TIP_LABEL = '_vms_discounts_tip_label';
    public const META_ORDER_TIP_SCOPE = '_vms_discounts_tip_scope';
    public const META_FEE_IS_TIP = '_vms_discounts_is_tip';

    public const SCOPE_ALL_ORDERS = 'all_orders';
    public const SCOPE_REGULAR_PRODUCT_ORDERS = 'regular_product_orders';
    public const SCOPE_EXPRESS_BAR_ORDERS = 'express_bar_orders';

    /** @var VMS_Discounts_Mapping */
    protected $mapping;

    public function __construct(VMS_Discounts_Mapping $mapping)
    {
        $this->mapping = $mapping;

        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('wp_ajax_vms_discounts_set_tip', [$this, 'ajax_set_tip']);
        add_action('wp_ajax_nopriv_vms_discounts_set_tip', [$this, 'ajax_set_tip']);
        add_action('woocommerce_cart_calculate_fees', [$this, 'maybe_add_tip_fee'], 30, 1);
        add_action('woocommerce_cart_totals_before_order_total', [$this, 'render_cart_tip_picker']);
        add_action('woocommerce_review_order_before_order_total', [$this, 'render_checkout_tip_picker']);
        add_action('woocommerce_checkout_create_order_fee_item', [$this, 'mark_tip_fee_item'], 10, 4);
        add_action('woocommerce_checkout_create_order', [$this, 'persist_order_tip_meta'], 30, 2);
        add_action('woocommerce_thankyou', [$this, 'clear_selected_tip']);
    }

    public static function is_enabled(): bool
    {
        return vms_discounts_bool(get_option(self::ENABLED_OPTION_KEY, 'no'));
    }

    public static function get_scope(): string
    {
        $scope = sanitize_key((string) get_option(self::SCOPE_OPTION_KEY, self::SCOPE_REGULAR_PRODUCT_ORDERS));
        if (!in_array($scope, [self::SCOPE_ALL_ORDERS, self::SCOPE_REGULAR_PRODUCT_ORDERS, self::SCOPE_EXPRESS_BAR_ORDERS], true)) {
            return self::SCOPE_REGULAR_PRODUCT_ORDERS;
        }

        return $scope;
    }

    public static function get_tip_label(): string
    {
        $label = trim((string) get_option(self::LABEL_OPTION_KEY, 'Tip / Gratuity'));
        return $label !== '' ? $label : 'Tip / Gratuity';
    }

    public static function get_heading(): string
    {
        $heading = trim((string) get_option(self::HEADING_OPTION_KEY, 'Add a tip for the crew?'));
        return $heading !== '' ? $heading : 'Add a tip for the crew?';
    }

    public static function get_description(): string
    {
        $description = trim((string) get_option(self::DESCRIPTION_OPTION_KEY, 'Optional. Thank you for supporting the team working your order.'));
        return $description;
    }

    /**
     * @return array<int, float>
     */
    public static function get_presets(): array
    {
        $raw = (string) get_option(self::PRESETS_OPTION_KEY, '1,3,5');
        return self::sanitize_presets($raw);
    }

    public static function custom_amount_allowed(): bool
    {
        return vms_discounts_bool(get_option(self::ALLOW_CUSTOM_OPTION_KEY, 'yes'));
    }

    public static function tips_are_taxable(): bool
    {
        return vms_discounts_bool(get_option(self::TAXABLE_OPTION_KEY, 'no'));
    }

    public static function get_max_amount(): float
    {
        $max = (float) get_option(self::MAX_AMOUNT_OPTION_KEY, 100);
        if ($max <= 0) {
            return 100.0;
        }

        return round($max, vms_discounts_price_decimals());
    }

    /**
     * @param mixed $raw
     * @return array<int, float>
     */
    public static function sanitize_presets($raw): array
    {
        if (is_array($raw)) {
            $raw = implode(',', $raw);
        }

        $parts = preg_split('/[\s,]+/', (string) $raw) ?: [];
        $amounts = [];

        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part === '') {
                continue;
            }

            $amount = (float) preg_replace('/[^0-9.]/', '', $part);
            if ($amount <= 0) {
                continue;
            }

            $amount = round($amount, vms_discounts_price_decimals());
            $amounts[(string) $amount] = $amount;
        }

        return array_values($amounts);
    }

    /**
     * @return array<string, string>
     */
    public static function scope_options(): array
    {
        return [
            self::SCOPE_REGULAR_PRODUCT_ORDERS => 'Regular product orders only (recommended for Express Bar today)',
            self::SCOPE_ALL_ORDERS => 'All WooCommerce orders',
            self::SCOPE_EXPRESS_BAR_ORDERS => 'Express Bar orders only when the cart is clearly flagged',
        ];
    }

    public function enqueue_frontend_assets(): void
    {
        if (!self::is_enabled()) {
            return;
        }

        if (!function_exists('is_cart') || !function_exists('is_checkout')) {
            return;
        }

        if (!is_cart() && !is_checkout()) {
            return;
        }

        wp_enqueue_style(
            'vms-discounts-tips',
            VMS_DISCOUNTS_URL . 'assets/frontend/discounts-tips.css',
            [],
            VMS_DISCOUNTS_VERSION
        );

        wp_enqueue_script(
            'vms-discounts-tips',
            VMS_DISCOUNTS_URL . 'assets/frontend/discounts-tips.js',
            ['jquery'],
            VMS_DISCOUNTS_VERSION,
            true
        );

        wp_localize_script('vms-discounts-tips', 'VMS_DISCOUNTS_TIPS', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vms_discounts_set_tip'),
            'i18n' => [
                'saving' => __('Updating tip…', 'vms-commerce-discounts'),
                'saved' => __('Tip updated.', 'vms-commerce-discounts'),
                'error' => __('The tip could not be updated. Please try again.', 'vms-commerce-discounts'),
            ],
        ]);
    }

    public function ajax_set_tip(): void
    {
        check_ajax_referer('vms_discounts_set_tip', 'nonce');

        if (!function_exists('WC') || !WC()->cart || !WC()->session) {
            wp_send_json_error([
                'message' => __('WooCommerce cart session is not available.', 'vms-commerce-discounts'),
            ], 400);
        }

        if (!self::is_enabled()) {
            $this->set_selected_tip_amount(0.0);
            wp_send_json_success([
                'amount' => 0.0,
                'message' => __('Tips are not enabled.', 'vms-commerce-discounts'),
            ]);
        }

        $amount = isset($_POST['amount']) ? wc_clean(wp_unslash($_POST['amount'])) : 0;
        $amount = $this->sanitize_selected_tip_amount($amount);

        if (!$this->cart_is_in_scope(WC()->cart)) {
            $amount = 0.0;
        }

        $this->set_selected_tip_amount($amount);

        if (method_exists(WC()->cart, 'calculate_totals')) {
            WC()->cart->calculate_totals();
        }

        wp_send_json_success([
            'amount' => $amount,
            'formatted' => function_exists('wc_price') ? wp_strip_all_tags(wc_price($amount)) : number_format($amount, 2),
            'message' => __('Tip updated.', 'vms-commerce-discounts'),
        ]);
    }

    /**
     * @param mixed $cart
     */
    public function maybe_add_tip_fee($cart): void
    {
        if (is_admin() && !wp_doing_ajax()) {
            return;
        }

        if (!self::is_enabled() || !$this->is_supported_cart($cart)) {
            return;
        }

        if (!$this->cart_is_in_scope($cart)) {
            $this->set_selected_tip_amount(0.0);
            return;
        }

        $amount = $this->get_selected_tip_amount();
        if ($amount <= 0.0) {
            return;
        }

        $label = self::get_tip_label();
        $taxable = self::tips_are_taxable();

        $cart->add_fee($label, $amount, $taxable, '');
    }

    public function render_cart_tip_picker(): void
    {
        $this->render_tip_picker_row('cart');
    }

    public function render_checkout_tip_picker(): void
    {
        $this->render_tip_picker_row('checkout');
    }

    /**
     * @param mixed $item
     * @param string $fee_key
     * @param mixed $fee
     * @param mixed $order
     */
    public function mark_tip_fee_item($item, string $fee_key, $fee, $order): void
    {
        if (!is_object($item) || !method_exists($item, 'add_meta_data')) {
            return;
        }

        $fee_name = is_object($fee) && isset($fee->name) ? (string) $fee->name : '';
        if ($fee_name !== self::get_tip_label()) {
            return;
        }

        $amount = $this->get_selected_tip_amount();
        if ($amount <= 0.0) {
            return;
        }

        $item->add_meta_data(self::META_FEE_IS_TIP, 'yes', true);
        $item->add_meta_data(self::META_ORDER_TIP_AMOUNT, $amount, true);
        $item->add_meta_data(self::META_ORDER_TIP_SCOPE, self::get_scope(), true);
    }

    /**
     * @param mixed $order
     * @param array<string, mixed> $posted_data
     */
    public function persist_order_tip_meta($order, array $posted_data): void
    {
        if (!is_object($order) || !method_exists($order, 'update_meta_data')) {
            return;
        }

        $amount = $this->get_selected_tip_amount();
        if ($amount <= 0.0) {
            return;
        }

        $order->update_meta_data(self::META_ORDER_TIP_AMOUNT, $amount);
        $order->update_meta_data(self::META_ORDER_TIP_LABEL, self::get_tip_label());
        $order->update_meta_data(self::META_ORDER_TIP_SCOPE, self::get_scope());
    }

    /**
     * @param mixed $order_id
     */
    public function clear_selected_tip($order_id = null): void
    {
        $this->set_selected_tip_amount(0.0);
    }

    protected function render_tip_picker_row(string $context): void
    {
        if (!self::is_enabled() || !function_exists('WC') || !WC()->cart) {
            return;
        }

        if (!$this->cart_is_in_scope(WC()->cart)) {
            return;
        }

        $selected_amount = $this->get_selected_tip_amount();
        $presets = self::get_presets();
        $allow_custom = self::custom_amount_allowed();
        $heading = self::get_heading();
        $description = self::get_description();
        $max_amount = self::get_max_amount();

        echo '<tr class="vms-discounts-tip-row vms-discounts-tip-row--' . esc_attr($context) . '">';
        echo '<th colspan="2">';
        echo '<div class="vms-discounts-tip-box" data-vms-tip-box data-context="' . esc_attr($context) . '" data-selected-amount="' . esc_attr((string) $selected_amount) . '">';
        echo '<div class="vms-discounts-tip-copy">';
        echo '<strong>' . esc_html($heading) . '</strong>';
        if ($description !== '') {
            echo '<span>' . esc_html($description) . '</span>';
        }
        echo '</div>';
        echo '<div class="vms-discounts-tip-controls" role="group" aria-label="' . esc_attr($heading) . '">';

        $this->render_tip_button(0.0, $selected_amount, __('No tip', 'vms-commerce-discounts'));
        foreach ($presets as $amount) {
            $this->render_tip_button($amount, $selected_amount, function_exists('wc_price') ? wp_strip_all_tags(wc_price($amount)) : '$' . number_format($amount, 2));
        }

        echo '</div>';

        if ($allow_custom) {
            $custom_is_selected = $selected_amount > 0.0 && !$this->amount_matches_preset($selected_amount, $presets);
            echo '<div class="vms-discounts-tip-custom">';
            echo '<label>';
            echo '<span>' . esc_html__('Custom tip', 'vms-commerce-discounts') . '</span>';
            echo '<input type="number" min="0" step="0.01" max="' . esc_attr((string) $max_amount) . '" inputmode="decimal" class="vms-discounts-tip-custom-input" value="' . esc_attr($custom_is_selected ? (string) $selected_amount : '') . '" placeholder="0.00" />';
            echo '</label>';
            echo '<button type="button" class="button vms-discounts-tip-custom-apply">' . esc_html__('Apply tip', 'vms-commerce-discounts') . '</button>';
            echo '</div>';
        }

        echo '<p class="vms-discounts-tip-status" aria-live="polite"></p>';
        echo '</div>';
        echo '</th>';
        echo '</tr>';
    }

    protected function render_tip_button(float $amount, float $selected_amount, string $label): void
    {
        $is_selected = abs($amount - $selected_amount) < 0.0001;
        $class = 'button vms-discounts-tip-button';
        if ($is_selected) {
            $class .= ' is-selected';
        }

        echo '<button type="button" class="' . esc_attr($class) . '" data-tip-amount="' . esc_attr((string) $amount) . '" aria-pressed="' . esc_attr($is_selected ? 'true' : 'false') . '">' . esc_html($label) . '</button>';
    }

    /**
     * @param mixed $cart
     */
    protected function is_supported_cart($cart): bool
    {
        return is_object($cart) && method_exists($cart, 'get_cart') && method_exists($cart, 'add_fee');
    }

    /**
     * @param mixed $cart
     */
    protected function cart_is_in_scope($cart): bool
    {
        if (!$this->is_supported_cart($cart)) {
            return false;
        }

        $scope = self::get_scope();
        if ($scope === self::SCOPE_ALL_ORDERS) {
            return !$this->cart_is_empty($cart);
        }

        if ($scope === self::SCOPE_EXPRESS_BAR_ORDERS) {
            return $this->cart_has_express_bar_marker($cart);
        }

        return $this->cart_has_regular_product($cart);
    }

    /**
     * @param mixed $cart
     */
    protected function cart_is_empty($cart): bool
    {
        if (!is_object($cart) || !method_exists($cart, 'is_empty')) {
            return true;
        }

        return (bool) $cart->is_empty();
    }

    /**
     * @param mixed $cart
     */
    protected function cart_has_regular_product($cart): bool
    {
        foreach ($cart->get_cart() as $cart_item) {
            if (!is_array($cart_item)) {
                continue;
            }

            $classification = $this->mapping->classify_cart_item($cart_item);
            if (($classification['type'] ?? 'other') === 'other' && (int) ($classification['product_id'] ?? 0) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param mixed $cart
     */
    protected function cart_has_express_bar_marker($cart): bool
    {
        $is_express_bar = false;

        foreach ($cart->get_cart() as $cart_item) {
            if (!is_array($cart_item)) {
                continue;
            }

            if ($this->cart_item_has_express_bar_marker($cart_item)) {
                $is_express_bar = true;
                break;
            }
        }

        return (bool) apply_filters('vms_discounts_tips_cart_is_express_bar', $is_express_bar, $cart);
    }

    /**
     * @param array<string, mixed> $cart_item
     */
    protected function cart_item_has_express_bar_marker(array $cart_item): bool
    {
        $truthy_keys = [
            'vms_express_bar',
            '_vms_express_bar',
            'vms_express_bar_order',
            '_vms_express_bar_order',
            'vmseb',
            '_vmseb',
            'vmseb_order_context',
            '_vmseb_order_context',
        ];

        foreach ($truthy_keys as $key) {
            if (isset($cart_item[$key]) && vms_discounts_bool($cart_item[$key])) {
                return true;
            }
        }

        $event_keys = [
            'vmseb_event_id',
            '_vmseb_event_id',
            'vms_express_bar_event_id',
            '_vms_express_bar_event_id',
            'vms_bar_event_id',
            '_vms_bar_event_id',
            'vms_express_bar_menu_id',
            '_vms_express_bar_menu_id',
            'vms_bar_menu_id',
            '_vms_bar_menu_id',
        ];

        foreach ($event_keys as $key) {
            if (isset($cart_item[$key]) && (int) $cart_item[$key] > 0) {
                return true;
            }
        }

        $product_id = (int) ($cart_item['product_id'] ?? 0);
        if ($product_id <= 0) {
            return false;
        }

        foreach (['_vms_product_channel', '_vms_order_channel', '_vms_source', '_vmseb_product', '_vms_express_bar_product'] as $meta_key) {
            $value = sanitize_key((string) get_post_meta($product_id, $meta_key, true));
            if (in_array($value, ['express_bar', 'express-bar', 'vmseb', 'bar'], true) || vms_discounts_bool($value)) {
                return true;
            }
        }

        $terms = wp_get_post_terms($product_id, ['product_cat', 'product_tag'], ['fields' => 'slugs']);
        if (!is_wp_error($terms)) {
            foreach (vms_discounts_array($terms) as $slug) {
                $slug = sanitize_title((string) $slug);
                if (strpos($slug, 'express-bar') !== false || strpos($slug, 'expressbar') !== false || strpos($slug, 'vmseb') !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param mixed $raw_amount
     */
    protected function sanitize_selected_tip_amount($raw_amount): float
    {
        $amount = (float) preg_replace('/[^0-9.]/', '', (string) $raw_amount);
        $amount = round(max(0.0, $amount), vms_discounts_price_decimals());
        $max_amount = self::get_max_amount();

        if ($amount > $max_amount) {
            $amount = $max_amount;
        }

        if ($amount <= 0.0) {
            return 0.0;
        }

        $presets = self::get_presets();
        if ($this->amount_matches_preset($amount, $presets)) {
            return $amount;
        }

        if (!self::custom_amount_allowed()) {
            return 0.0;
        }

        return $amount;
    }

    /**
     * @param array<int, float> $presets
     */
    protected function amount_matches_preset(float $amount, array $presets): bool
    {
        foreach ($presets as $preset) {
            if (abs($amount - (float) $preset) < 0.0001) {
                return true;
            }
        }

        return false;
    }

    protected function get_selected_tip_amount(): float
    {
        if (!function_exists('WC') || !WC()->session) {
            return 0.0;
        }

        return $this->sanitize_selected_tip_amount(WC()->session->get(self::SESSION_AMOUNT_KEY, 0));
    }

    protected function set_selected_tip_amount(float $amount): void
    {
        if (!function_exists('WC') || !WC()->session) {
            return;
        }

        $amount = $this->sanitize_selected_tip_amount($amount);
        WC()->session->set(self::SESSION_AMOUNT_KEY, $amount);
    }
}
