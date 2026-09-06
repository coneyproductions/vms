<?php

if (!defined('ABSPATH')) {
    exit;
}

class VMS_Discounts_Order
{
    public const META_APPLIED = '_vms_discounts_applied';
    public const META_LEDGER = '_vms_discounts_ledger';
    public const META_GROSS_SUBTOTAL = '_vms_discounts_gross_subtotal';
    public const META_DISCOUNT_TOTAL = '_vms_discounts_total';
    public const META_NET_SUBTOTAL = '_vms_discounts_net_subtotal';
    public const META_SQUARE_SYNC_STATUS = '_vms_discounts_square_sync_status';
    public const META_SQUARE_DISCOUNT_REFS = '_vms_discounts_square_discount_refs';
    public const META_PRICING_MODE = '_vms_discounts_pricing_mode';
    protected const META_LAST_NOTE_STATUS = '_vms_discounts_last_note_status';

    public const ITEM_META_CART_ITEM_KEY = '_vms_discounts_cart_item_key';
    public const ITEM_META_EVENT_ID = '_vms_discounts_event_id';
    public const ITEM_META_ORIGINAL_LINE_SUBTOTAL = '_vms_discounts_original_line_subtotal';

    public const SQUARE_SYNC_PENDING = 'pending';
    public const SQUARE_SYNC_OK = 'ok';
    public const SQUARE_SYNC_FAILED = 'failed';
    public const SQUARE_SYNC_COMPATIBILITY = 'compatibility_reduced_price';
    public const SQUARE_SYNC_NOT_APPLICABLE = 'not_applicable';

    /** @var VMS_Discounts_Cart */
    protected $cart;

    /** @var VMS_Discounts_Rules */
    protected $rules;

    /** @var VMS_Discounts_Mapping */
    protected $mapping;

    public function __construct(VMS_Discounts_Cart $cart, VMS_Discounts_Rules $rules, VMS_Discounts_Mapping $mapping)
    {
        $this->cart = $cart;
        $this->rules = $rules;
        $this->mapping = $mapping;

        add_action('woocommerce_checkout_create_order_line_item', [$this, 'capture_order_item_discount_meta'], 10, 4);
        add_action('woocommerce_checkout_create_order', [$this, 'persist_applied_discounts_meta'], 20, 2);
        add_action('woocommerce_admin_order_data_after_order_details', [$this, 'render_order_discount_panel']);
        add_action('woocommerce_thankyou', [$this, 'clear_cart_session']);
    }

    /**
     * @param mixed $item
     * @param string $cart_item_key
     * @param array<string, mixed> $values
     * @param mixed $order
     */
    public function capture_order_item_discount_meta($item, string $cart_item_key, array $values, $order): void
    {
        if (!is_object($item) || !method_exists($item, 'add_meta_data') || !method_exists($item, 'get_quantity')) {
            return;
        }

        $item->add_meta_data(self::ITEM_META_CART_ITEM_KEY, $cart_item_key, true);

        $classification = $this->mapping->classify_cart_item($values);
        $event_id = (int) ($classification['event_id'] ?? 0);
        if ($event_id > 0) {
            $item->add_meta_data(self::ITEM_META_EVENT_ID, $event_id, true);
        }

        $original_unit_price = isset($values[VMS_Discounts_Cart::CART_ITEM_ORIGINAL_UNIT_PRICE_KEY])
            ? (float) $values[VMS_Discounts_Cart::CART_ITEM_ORIGINAL_UNIT_PRICE_KEY]
            : 0.0;
        $unit_discount = isset($values[VMS_Discounts_Cart::CART_ITEM_UNIT_DISCOUNT_KEY])
            ? (float) $values[VMS_Discounts_Cart::CART_ITEM_UNIT_DISCOUNT_KEY]
            : 0.0;
        $line_discount = isset($values[VMS_Discounts_Cart::CART_ITEM_LINE_DISCOUNT_KEY])
            ? (float) $values[VMS_Discounts_Cart::CART_ITEM_LINE_DISCOUNT_KEY]
            : 0.0;
        $labels = vms_discounts_clean_string_list($values[VMS_Discounts_Cart::CART_ITEM_ADJUSTMENT_LABELS_KEY] ?? []);

        if ($original_unit_price > 0.0) {
            $item->add_meta_data(VMS_Discounts_Cart::CART_ITEM_ORIGINAL_UNIT_PRICE_KEY, $original_unit_price, true);

            $quantity = max(1, (int) $item->get_quantity());
            $original_line_subtotal = round($original_unit_price * $quantity, vms_discounts_price_decimals());
            $item->add_meta_data(self::ITEM_META_ORIGINAL_LINE_SUBTOTAL, $original_line_subtotal, true);
        }

        if ($unit_discount > 0.0) {
            $item->add_meta_data(VMS_Discounts_Cart::CART_ITEM_UNIT_DISCOUNT_KEY, $unit_discount, true);
        }

        if ($line_discount > 0.0) {
            $item->add_meta_data(VMS_Discounts_Cart::CART_ITEM_LINE_DISCOUNT_KEY, $line_discount, true);
        }

        if (!empty($labels)) {
            $item->add_meta_data(VMS_Discounts_Cart::CART_ITEM_ADJUSTMENT_LABELS_KEY, $labels, true);
        }
    }

    /**
     * @param mixed $order
     * @param array<string, mixed> $posted_data
     */
    public function persist_applied_discounts_meta($order, array $posted_data): void
    {
        $entries = $this->cart->get_applied_entries_from_session();
        if (empty($entries)) {
            return;
        }

        if (!is_object($order) || !method_exists($order, 'update_meta_data') || !method_exists($order, 'get_items')) {
            return;
        }

        $payment_method = sanitize_key((string) ($posted_data['payment_method'] ?? ''));
        $initial_status = $this->determine_initial_square_sync_status($payment_method);
        $pricing_mode = $this->determine_pricing_mode($payment_method);
        $summary = $this->build_order_discount_summary($order);
        $ledger = $this->build_ledger_entries($order, $entries, 'line_item');

        $order->update_meta_data(self::META_APPLIED, $entries);
        $order->update_meta_data(self::META_LEDGER, $ledger);
        $order->update_meta_data(self::META_GROSS_SUBTOTAL, $summary['gross_subtotal']);
        $order->update_meta_data(self::META_DISCOUNT_TOTAL, $summary['discount_total']);
        $order->update_meta_data(self::META_NET_SUBTOTAL, $summary['net_subtotal']);
        $order->update_meta_data(self::META_SQUARE_SYNC_STATUS, $initial_status);
        $order->update_meta_data(self::META_SQUARE_DISCOUNT_REFS, []);
        $order->update_meta_data(self::META_PRICING_MODE, $pricing_mode);

        if ($this->rules->should_add_order_note() && !$this->should_defer_order_note($payment_method)) {
            $this->maybe_add_order_note_for_status($order, $initial_status);
        }
    }

    /**
     * @param mixed $order
     * @param array<int, array<string, mixed>> $square_discount_refs
     */
    public function update_square_sync_result($order, string $status, array $square_discount_refs = [], string $scope_used = 'line_item'): void
    {
        if (!is_object($order) || !method_exists($order, 'update_meta_data')) {
            return;
        }

        $ledger = array_values(array_filter(vms_discounts_array($order->get_meta(self::META_LEDGER, true)), 'is_array'));
        $refs = array_values(array_filter($square_discount_refs, 'is_array'));
        $refs_by_name = [];

        foreach ($refs as $ref) {
            $name = trim((string) ($ref['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            if (!isset($refs_by_name[$name])) {
                $refs_by_name[$name] = [];
            }

            $refs_by_name[$name][] = $ref;
        }

        foreach ($ledger as &$row) {
            $label = vms_discounts_label_from_entry($row);
            $row['scope_used'] = $scope_used;

            if (isset($refs_by_name[$label])) {
                $row['square_discount_refs'] = $refs_by_name[$label];
            } elseif (isset($refs_by_name['VMS Discounts'])) {
                $row['square_discount_refs'] = $refs_by_name['VMS Discounts'];
            } elseif (count($refs) === 1) {
                $row['square_discount_refs'] = [$refs[0]];
            } else {
                $row['square_discount_refs'] = [];
            }

            if (!empty($row['square_discount_refs'])) {
                $first_ref = $row['square_discount_refs'][0];
                $row['square_discount_uid'] = (string) ($first_ref['uid'] ?? '');
            }
        }
        unset($row);

        $order->update_meta_data(self::META_LEDGER, $ledger);
        $order->update_meta_data(self::META_SQUARE_SYNC_STATUS, $status);
        $order->update_meta_data(self::META_SQUARE_DISCOUNT_REFS, $refs);

        if ($status === self::SQUARE_SYNC_COMPATIBILITY) {
            $order->update_meta_data(self::META_PRICING_MODE, VMS_Discounts_Rules::SQUARE_MODE_COMPATIBILITY);
        } elseif ($status === self::SQUARE_SYNC_OK) {
            $order->update_meta_data(self::META_PRICING_MODE, VMS_Discounts_Rules::SQUARE_MODE_NATIVE);
        }

        if (method_exists($order, 'save')) {
            $order->save();
        }
    }

    /**
     * @param mixed $order
     */
    public function maybe_add_order_note_for_status($order, string $status): void
    {
        if (!$this->rules->should_add_order_note()) {
            return;
        }

        if (!is_object($order) || !method_exists($order, 'add_order_note') || !method_exists($order, 'get_meta')) {
            return;
        }

        $last_status = (string) $order->get_meta(self::META_LAST_NOTE_STATUS, true);
        if ($last_status === $status) {
            return;
        }

        $note = $this->build_order_note_message($order, $status);
        if ($note === '') {
            return;
        }

        $order->add_order_note($note);
        $order->update_meta_data(self::META_LAST_NOTE_STATUS, $status);

        if (method_exists($order, 'save')) {
            $order->save();
        }
    }

    /**
     * @param mixed $order
     */
    public function render_order_discount_panel($order): void
    {
        if (!is_object($order) || !method_exists($order, 'get_meta')) {
            return;
        }

        $ledger = array_values(array_filter(vms_discounts_array($order->get_meta(self::META_LEDGER, true)), 'is_array'));
        if (empty($ledger)) {
            return;
        }

        $gross_subtotal = (float) $order->get_meta(self::META_GROSS_SUBTOTAL, true);
        $discount_total = (float) $order->get_meta(self::META_DISCOUNT_TOTAL, true);
        $net_subtotal = (float) $order->get_meta(self::META_NET_SUBTOTAL, true);
        $square_sync_status = (string) $order->get_meta(self::META_SQUARE_SYNC_STATUS, true);
        $pricing_mode = (string) $order->get_meta(self::META_PRICING_MODE, true);
        $gross_display = function_exists('wc_price') ? wc_price($gross_subtotal, ['currency' => $order->get_currency()]) : wc_format_decimal($gross_subtotal, vms_discounts_price_decimals());
        $discount_display = function_exists('wc_price') ? wc_price($discount_total, ['currency' => $order->get_currency()]) : wc_format_decimal($discount_total, vms_discounts_price_decimals());
        $net_display = function_exists('wc_price') ? wc_price($net_subtotal, ['currency' => $order->get_currency()]) : wc_format_decimal($net_subtotal, vms_discounts_price_decimals());

        echo '<div class="order_data_column">';
        echo '<h3>VMS Discount Ledger</h3>';
        echo '<p><strong>Gross eligible subtotal:</strong> ' . wp_kses_post($gross_display) . '</p>';
        echo '<p><strong>Total discount:</strong> ' . wp_kses_post($discount_display) . '</p>';
        echo '<p><strong>Net subtotal:</strong> ' . wp_kses_post($net_display) . '</p>';
        echo '<p><strong>Square sync:</strong> ' . esc_html($square_sync_status !== '' ? $square_sync_status : self::SQUARE_SYNC_NOT_APPLICABLE) . '</p>';
        echo '<p><strong>Pricing mode:</strong> ' . esc_html($pricing_mode !== '' ? $pricing_mode : VMS_Discounts_Rules::SQUARE_MODE_COMPATIBILITY) . '</p>';
        echo '<ul>';

        foreach ($ledger as $row) {
            $label = vms_discounts_label_from_entry($row);
            $event_id = (int) ($row['event_id'] ?? 0);
            $amount = (float) ($row['discount_amount'] ?? 0.0);
            $scope_used = (string) ($row['scope_used'] ?? 'line_item');
            $source = (string) ($row['source'] ?? 'event');
            $square_refs = vms_discounts_array($row['square_discount_refs'] ?? []);

            $context_label = $event_id > 0 ? sprintf('Event %d', $event_id) : 'Order-wide';
            $line = sprintf(
                '%s (%s): -%s [%s, source=%s]',
                $label,
                $context_label,
                wc_format_decimal($amount, vms_discounts_price_decimals()),
                $scope_used,
                $source
            );

            if (!empty($square_refs)) {
                $ref_labels = [];
                foreach ($square_refs as $ref) {
                    if (!is_array($ref)) {
                        continue;
                    }
                    $uid = trim((string) ($ref['uid'] ?? ''));
                    if ($uid === '') {
                        continue;
                    }
                    $ref_labels[] = $uid;
                }

                if (!empty($ref_labels)) {
                    $line .= ' [Square refs: ' . implode(', ', array_map('esc_html', $ref_labels)) . ']';
                }
            }

            echo '<li>' . esc_html($line) . '</li>';
        }

        echo '</ul>';
        echo '</div>';
    }

    /**
     * @param int|string $order_id
     */
    public function clear_cart_session($order_id): void
    {
        $this->cart->clear_applied_entries();
    }

    /**
     * @param mixed $order
     */
    public function build_order_note_message($order, string $status): string
    {
        if (!is_object($order) || !method_exists($order, 'get_meta')) {
            return '';
        }

        $ledger = array_values(array_filter(vms_discounts_array($order->get_meta(self::META_LEDGER, true)), 'is_array'));
        if (empty($ledger)) {
            return '';
        }

        $gross_subtotal = (float) $order->get_meta(self::META_GROSS_SUBTOTAL, true);
        $net_subtotal = (float) $order->get_meta(self::META_NET_SUBTOTAL, true);

        $summary_parts = [];
        foreach ($ledger as $row) {
            $label = vms_discounts_label_from_entry($row);
            $amount = (float) ($row['discount_amount'] ?? 0.0);
            $event_id = (int) ($row['event_id'] ?? 0);
            $scope_used = (string) ($row['scope_used'] ?? 'line_item');

            $context_label = $event_id > 0 ? sprintf('Event %d', $event_id) : 'Order-wide';
            $summary_parts[] = sprintf(
                '%s (%s): -%0.2f [scope=%s, square_sync=%s, gross=%0.2f, net=%0.2f]',
                $label,
                $context_label,
                $amount,
                $scope_used,
                $status,
                $gross_subtotal,
                $net_subtotal
            );
        }

        if (empty($summary_parts)) {
            return '';
        }

        return 'VMS Discounts applied: ' . implode('; ', $summary_parts);
    }

    /**
     * @param array<int, array<string, mixed>> $entries
     * @return array<int, array<string, mixed>>
     */
    protected function build_ledger_entries($order, array $entries, string $scope_used): array
    {
        $order_item_ids_by_cart_key = [];

        foreach ($order->get_items() as $item_id => $item) {
            if (!is_object($item) || !method_exists($item, 'get_meta')) {
                continue;
            }

            $cart_item_key = (string) $item->get_meta(self::ITEM_META_CART_ITEM_KEY, true);
            if ($cart_item_key === '') {
                continue;
            }

            $order_item_ids_by_cart_key[$cart_item_key] = (int) $item_id;
        }

        $ledger = [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $affected_order_item_ids = [];
            foreach (vms_discounts_array($entry['affected_cart_item_keys'] ?? []) as $cart_item_key) {
                $cart_item_key = (string) $cart_item_key;
                if ($cart_item_key === '' || !isset($order_item_ids_by_cart_key[$cart_item_key])) {
                    continue;
                }

                $affected_order_item_ids[] = (int) $order_item_ids_by_cart_key[$cart_item_key];
            }

            $ledger[] = [
                'event_id' => (int) ($entry['event_id'] ?? 0),
                'context_key' => (string) ($entry['context_key'] ?? ''),
                'context_type' => (string) ($entry['context_type'] ?? 'event'),
                'rule_id' => (string) ($entry['rule_id'] ?? ''),
                'public_label' => (string) ($entry['public_label'] ?? ''),
                'admin_label' => (string) ($entry['admin_label'] ?? ''),
                'discount_amount' => (float) ($entry['discount_amount'] ?? 0.0),
                'discount_type' => (string) ($entry['discount_type'] ?? ''),
                'applies_to' => (string) ($entry['applies_to'] ?? ''),
                'product_ids' => array_values(array_unique(array_map('intval', vms_discounts_array($entry['product_ids'] ?? [])))),
                'scope_used' => $scope_used,
                'qualified_units' => (int) ($entry['qualified_units'] ?? 1),
                'source' => (string) ($entry['source'] ?? 'event'),
                'affected_order_item_ids' => array_values(array_unique($affected_order_item_ids)),
                'affected_cart_item_keys' => array_values(array_unique(array_map('strval', vms_discounts_array($entry['affected_cart_item_keys'] ?? [])))),
                'square_discount_refs' => [],
                'timestamp' => (string) ($entry['timestamp'] ?? vms_discounts_now()),
            ];
        }

        return $ledger;
    }

    /**
     * @return array<string, float>
     */
    protected function build_order_discount_summary($order): array
    {
        $gross_subtotal = 0.0;

        foreach ($order->get_items() as $item) {
            if (!($item instanceof WC_Order_Item_Product)) {
                continue;
            }

            $gross_line_subtotal = (float) $item->get_meta(self::ITEM_META_ORIGINAL_LINE_SUBTOTAL, true);
            if ($gross_line_subtotal <= 0.0) {
                $gross_line_subtotal = (float) $item->get_subtotal();
                if (function_exists('wc_prices_include_tax') && wc_prices_include_tax()) {
                    $gross_line_subtotal += (float) $item->get_subtotal_tax();
                }
                $gross_line_subtotal += (float) $item->get_meta(VMS_Discounts_Cart::CART_ITEM_LINE_DISCOUNT_KEY, true);
            }

            $gross_subtotal += max(0.0, $gross_line_subtotal);
        }

        $discount_total = 0.0;
        foreach ($this->cart->get_applied_entries_from_session() as $entry) {
            $discount_total += max(0.0, (float) ($entry['discount_amount'] ?? 0.0));
        }

        $discount_total = round($discount_total, vms_discounts_price_decimals());
        $gross_subtotal = round($gross_subtotal, vms_discounts_price_decimals());

        return [
            'gross_subtotal' => $gross_subtotal,
            'discount_total' => $discount_total,
            'net_subtotal' => round(max(0.0, $gross_subtotal - $discount_total), vms_discounts_price_decimals()),
        ];
    }

    protected function determine_initial_square_sync_status(string $payment_method): string
    {
        if (!vms_discounts_is_square_gateway($payment_method)) {
            return self::SQUARE_SYNC_NOT_APPLICABLE;
        }

        if (!vms_discounts_square_native_enabled()) {
            return self::SQUARE_SYNC_COMPATIBILITY;
        }

        return self::SQUARE_SYNC_PENDING;
    }

    protected function determine_pricing_mode(string $payment_method): string
    {
        if (!vms_discounts_is_square_gateway($payment_method)) {
            return 'non_square';
        }

        if (!vms_discounts_square_native_enabled()) {
            return VMS_Discounts_Rules::SQUARE_MODE_COMPATIBILITY;
        }

        return VMS_Discounts_Rules::SQUARE_MODE_NATIVE;
    }

    protected function should_defer_order_note(string $payment_method): bool
    {
        return vms_discounts_is_square_gateway($payment_method) && vms_discounts_square_native_enabled();
    }
}
