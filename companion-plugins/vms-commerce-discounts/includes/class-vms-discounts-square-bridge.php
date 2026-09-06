<?php

if (!defined('ABSPATH')) {
    exit;
}

class VMS_Discounts_Square_Bridge
{
    /** @var VMS_Discounts_Order */
    protected $order_service;

    public function __construct(VMS_Discounts_Order $order_service)
    {
        $this->order_service = $order_service;

        add_filter('wc_payment_gateway_square_credit_card_get_order', [$this, 'maybe_prepare_square_order'], 10, 2);
        add_filter('wc_payment_gateway_square_cash_app_pay_get_order', [$this, 'maybe_prepare_square_order'], 10, 2);
    }

    /**
     * @param mixed $order
     * @param mixed $gateway
     * @return mixed
     */
    public function maybe_prepare_square_order($order, $gateway)
    {
        if (!vms_discounts_square_native_enabled()) {
            return $order;
        }

        if (!($order instanceof WC_Order)) {
            return $order;
        }

        $ledger = array_values(array_filter(vms_discounts_array($order->get_meta(VMS_Discounts_Order::META_LEDGER, true)), 'is_array'));
        if (empty($ledger)) {
            return $order;
        }

        $existing_square_order_id = isset($order->square_order_id) ? (string) $order->square_order_id : '';
        if ($existing_square_order_id === '') {
            $existing_square_order_id = $this->get_gateway_order_meta($gateway, $order, 'square_order_id');
        }

        if ($existing_square_order_id !== '') {
            $order->square_order_id = $existing_square_order_id;
            return $order;
        }

        try {
            $square_order = $this->create_square_order($order);
            $square_discount_refs = $this->build_square_discount_refs($square_order);
            $square_total_money = $square_order->getTotalMoney();
            $square_total_amount = is_object($square_total_money) && method_exists($square_total_money, 'getAmount')
                ? (int) $square_total_money->getAmount()
                : 0;
            $square_total = $square_total_amount > 0
                ? (float) $square_total_amount / max(1, pow(10, vms_discounts_price_decimals()))
                : (float) $order->get_total();

            if (method_exists($gateway, 'update_order_meta')) {
                $gateway->update_order_meta($order, 'square_order_id', (string) $square_order->getId());
                $gateway->update_order_meta($order, 'square_location_id', (string) $square_order->getLocationId());
                $gateway->update_order_meta($order, 'square_version', defined('WC_SQUARE_PLUGIN_VERSION') ? WC_SQUARE_PLUGIN_VERSION : '5.2.0');
            }

            $order->square_order_id = (string) $square_order->getId();
            $order->square_version = defined('WC_SQUARE_PLUGIN_VERSION') ? WC_SQUARE_PLUGIN_VERSION : '5.2.0';
            $order->payment_total = number_format($square_total, vms_discounts_price_decimals(), '.', '');

            if (method_exists($order, 'save')) {
                $order->save();
            }

            $this->order_service->update_square_sync_result(
                $order,
                VMS_Discounts_Order::SQUARE_SYNC_OK,
                $square_discount_refs,
                'line_item'
            );
            $this->order_service->maybe_add_order_note_for_status($order, VMS_Discounts_Order::SQUARE_SYNC_OK);

            vms_discounts_debug_log('Square order pre-created successfully.', [
                'order_id' => $order->get_id(),
                'square_order_id' => (string) $square_order->getId(),
                'discount_refs' => $square_discount_refs,
                'gateway' => is_object($gateway) && method_exists($gateway, 'get_id') ? (string) $gateway->get_id() : '',
                'payment_nonce_present' => $this->order_has_square_nonce($order) ? 'yes' : 'no',
                'mode' => vms_discounts_get_square_mode(),
            ]);
        } catch (Throwable $e) {
            $this->order_service->update_square_sync_result($order, VMS_Discounts_Order::SQUARE_SYNC_FAILED, [], 'line_item');
            $this->order_service->maybe_add_order_note_for_status($order, VMS_Discounts_Order::SQUARE_SYNC_FAILED);

            $message = __('VMS discounts could not be synchronized to Square as native discounts. The checkout was stopped so accounting shape is not silently changed.', 'vms-commerce-discounts');

            vms_discounts_debug_log('Square order bridge failed.', [
                'order_id' => $order->get_id(),
                'gateway' => is_object($gateway) && method_exists($gateway, 'get_id') ? (string) $gateway->get_id() : '',
                'payment_nonce_present' => $this->order_has_square_nonce($order) ? 'yes' : 'no',
                'message' => $e->getMessage(),
            ]);

            if (function_exists('wc_add_notice')) {
                wc_add_notice($message, 'error');
            }

            throw new RuntimeException($message, 0, $e);
        }

        return $order;
    }

    protected function create_square_order(WC_Order $order): \Square\Models\Order
    {
        if (!class_exists('\Square\SquareClient') || !class_exists('\WooCommerce\Square\Gateway\API\Requests\Orders')) {
            throw new RuntimeException('WooCommerce Square SDK is not available for the VMS discount bridge.');
        }

        $location_id = (string) wc_square()->get_settings_handler()->get_location_id();
        if ($location_id === '') {
            throw new RuntimeException('Square location is not configured.');
        }

        $working_order = wc_get_order($order->get_id());
        if (!($working_order instanceof WC_Order)) {
            throw new RuntimeException('Unable to load order for Square bridge.');
        }

        $label_map = $this->prepare_working_order_for_square($working_order);
        $default_label = vms_discounts_default_square_label(
            array_values(array_filter(vms_discounts_array($order->get_meta(VMS_Discounts_Order::META_LEDGER, true)), 'is_array'))
        );

        // Match Woo Square's request-builder expectations without mutating the saved Woo order.
        $working_order->unique_transaction_ref = 'vms-discounts-bridge-' . $order->get_id();

        $request = new VMS_Discounts_Square_Order_Request($this->build_square_client());
        $request->set_vms_create_order_data($location_id, $working_order, $label_map, $default_label);

        vms_discounts_debug_log('Preparing native Square discount request.', [
            'order_id' => $order->get_id(),
            'default_label' => $default_label,
            'line_item_labels' => $label_map,
        ]);

        $response = call_user_func_array(
            [$request->get_square_api(), $request->get_square_api_method()],
            $request->get_square_api_args()
        );

        if (!($response instanceof \Square\Http\ApiResponse)) {
            throw new RuntimeException('Square bridge did not receive a valid API response.');
        }

        if (!$response->isSuccess()) {
            throw new RuntimeException($this->format_square_errors($response->getErrors()));
        }

        $create_order_response = $response->getResult();
        if (!is_object($create_order_response) || !method_exists($create_order_response, 'getOrder')) {
            throw new RuntimeException('Square bridge response did not include an order payload.');
        }

        $square_order = $create_order_response->getOrder();
        if (!($square_order instanceof \Square\Models\Order)) {
            throw new RuntimeException('Square bridge response did not contain a valid Square order.');
        }

        return $square_order;
    }

    /**
     * @return array<int, array<int, string>>
     */
    protected function prepare_working_order_for_square(WC_Order $order): array
    {
        $labels_by_product_index = [];

        foreach ($order->get_items() as $item) {
            if (!($item instanceof WC_Order_Item_Product)) {
                continue;
            }

            $labels = vms_discounts_clean_string_list($item->get_meta(VMS_Discounts_Cart::CART_ITEM_ADJUSTMENT_LABELS_KEY, true));
            $line_discount = (float) $item->get_meta(VMS_Discounts_Cart::CART_ITEM_LINE_DISCOUNT_KEY, true);
            $original_line_subtotal = (float) $item->get_meta(VMS_Discounts_Order::ITEM_META_ORIGINAL_LINE_SUBTOTAL, true);

            if ($original_line_subtotal <= 0.0) {
                $original_line_subtotal = (float) $item->get_subtotal() + $line_discount;
            }

            if ($original_line_subtotal > 0.0 && $line_discount > 0.0) {
                $item->set_subtotal(wc_format_decimal($original_line_subtotal, vms_discounts_price_decimals()));
            }

            $labels_by_product_index[] = $labels;
        }

        return $labels_by_product_index;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function build_square_discount_refs(\Square\Models\Order $square_order): array
    {
        $refs = [];

        foreach (vms_discounts_array($square_order->getDiscounts()) as $discount) {
            if (!is_object($discount) || !method_exists($discount, 'getUid')) {
                continue;
            }

            $uid = (string) $discount->getUid();
            if ($uid === '') {
                continue;
            }

            $amount_money = method_exists($discount, 'getAmountMoney') ? $discount->getAmountMoney() : null;
            $amount_minor = is_object($amount_money) && method_exists($amount_money, 'getAmount')
                ? (int) $amount_money->getAmount()
                : 0;

            $refs[$uid] = [
                'uid' => $uid,
                'name' => method_exists($discount, 'getName') ? (string) $discount->getName() : '',
                'scope' => method_exists($discount, 'getScope') ? (string) $discount->getScope() : '',
                'amount_minor' => $amount_minor,
            ];
        }

        return array_values($refs);
    }

    protected function format_square_errors($errors): string
    {
        $messages = [];

        foreach (vms_discounts_array($errors) as $error) {
            if (!is_object($error) || !method_exists($error, 'getDetail')) {
                continue;
            }

            $code = method_exists($error, 'getCode') ? (string) $error->getCode() : '';
            $detail = (string) $error->getDetail();
            $messages[] = trim(($code !== '' ? '[' . $code . '] ' : '') . $detail);
        }

        if (empty($messages)) {
            return 'Square returned an unknown discount-bridge error.';
        }

        return implode(' | ', $messages);
    }

    protected function build_square_client(): \Square\SquareClient
    {
        return new \Square\SquareClient([
            'accessToken' => wc_square()->get_settings_handler()->get_access_token(),
            'environment' => wc_square()->get_settings_handler()->is_sandbox()
                ? \Square\Environment::SANDBOX
                : \Square\Environment::PRODUCTION,
        ]);
    }

    /**
     * @param mixed $gateway
     */
    protected function get_gateway_order_meta($gateway, WC_Order $order, string $key): string
    {
        if (!is_object($gateway) || !method_exists($gateway, 'get_order_meta')) {
            return '';
        }

        return (string) $gateway->get_order_meta($order, $key);
    }

    protected function order_has_square_nonce(WC_Order $order): bool
    {
        if (!isset($order->payment) || !is_object($order->payment) || !isset($order->payment->nonce) || !is_object($order->payment->nonce)) {
            return false;
        }

        foreach (['credit_card', 'cash_app_pay'] as $property) {
            if (!empty($order->payment->nonce->{$property})) {
                return true;
            }
        }

        return false;
    }
}

class VMS_Discounts_Square_Order_Request extends \WooCommerce\Square\Gateway\API\Requests\Orders
{
    /** @var array<int, array<int, string>> */
    protected $line_item_labels = [];

    /** @var string */
    protected $default_discount_name = 'VMS Discounts';

    /**
     * @param array<int, array<int, string>> $line_item_labels
     */
    public function set_vms_create_order_data(string $location_id, WC_Order $order, array $line_item_labels, string $default_discount_name): void
    {
        $this->line_item_labels = $line_item_labels;
        $this->default_discount_name = trim($default_discount_name) !== '' ? $default_discount_name : 'VMS Discounts';

        parent::set_create_order_data($location_id, $order);
        $this->rename_discount_objects();
    }

    protected function rename_discount_objects(): void
    {
        if (!($this->square_request instanceof \Square\Models\CreateOrderRequest)) {
            return;
        }

        $order_model = $this->square_request->getOrder();
        if (!($order_model instanceof \Square\Models\Order)) {
            return;
        }

        $discounts = vms_discounts_array($order_model->getDiscounts());
        if (empty($discounts)) {
            return;
        }

        $uid_to_name = [];
        $line_items = array_values(vms_discounts_array($order_model->getLineItems()));
        $product_line_count = count($this->line_item_labels);

        foreach ($line_items as $index => $line_item) {
            if ($index >= $product_line_count) {
                break;
            }

            if (!($line_item instanceof \Square\Models\OrderLineItem)) {
                continue;
            }

            $applied_discounts = vms_discounts_array($line_item->getAppliedDiscounts());
            if (empty($applied_discounts)) {
                continue;
            }

            $label = $this->resolve_label_for_line($this->line_item_labels[$index] ?? []);

            foreach ($applied_discounts as $applied_discount) {
                if (!($applied_discount instanceof \Square\Models\OrderLineItemAppliedDiscount)) {
                    continue;
                }

                $uid = (string) $applied_discount->getUid();
                if ($uid === '') {
                    continue;
                }

                $uid_to_name[$uid] = $label;
            }
        }

        foreach ($discounts as $discount) {
            if (!($discount instanceof \Square\Models\OrderLineItemDiscount)) {
                continue;
            }

            $uid = (string) $discount->getUid();
            $discount->setName($uid_to_name[$uid] ?? $this->default_discount_name);
        }

        $order_model->setDiscounts($discounts);
        $this->square_request->setOrder($order_model);
    }

    /**
     * @param mixed $labels
     */
    protected function resolve_label_for_line($labels): string
    {
        $labels = vms_discounts_clean_string_list($labels);

        if (count($labels) === 1) {
            return $labels[0];
        }

        if (!empty($labels)) {
            return 'VMS Discounts';
        }

        return $this->default_discount_name;
    }
}
