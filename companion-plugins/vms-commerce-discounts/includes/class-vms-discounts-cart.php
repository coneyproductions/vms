<?php

if (!defined('ABSPATH')) {
    exit;
}

class VMS_Discounts_Cart
{
    public const SESSION_APPLIED_KEY = 'vms_discounts_applied';

    public const CART_ITEM_ORIGINAL_UNIT_PRICE_KEY = '_vms_discounts_original_unit_price';
    public const CART_ITEM_UNIT_DISCOUNT_KEY = '_vms_discounts_unit_discount';
    public const CART_ITEM_LINE_DISCOUNT_KEY = '_vms_discounts_line_discount';
    public const CART_ITEM_ADJUSTMENT_LABELS_KEY = '_vms_discounts_adjustment_labels';

    /** @var VMS_Discounts_Rules */
    protected $rules;

    /** @var VMS_Discounts_Mapping */
    protected $mapping;

    /** @var bool */
    protected $store_api_display_data_registered = false;

    public function __construct(VMS_Discounts_Rules $rules, VMS_Discounts_Mapping $mapping)
    {
        $this->rules = $rules;
        $this->mapping = $mapping;

        add_action('woocommerce_before_calculate_totals', [$this, 'restore_original_cart_item_prices'], 1, 1);
        add_action('woocommerce_before_calculate_totals', [$this, 'apply_cart_item_adjustments'], 20, 1);
        add_action('woocommerce_cart_totals_before_order_total', [$this, 'render_cart_discount_rows']);
        add_action('woocommerce_review_order_before_order_total', [$this, 'render_checkout_discount_rows']);
        add_filter('woocommerce_cart_item_price', [$this, 'filter_cart_item_price_html'], 10, 3);
        add_filter('woocommerce_cart_item_subtotal', [$this, 'filter_cart_item_subtotal_html'], 10, 3);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_discount_display_assets']);
    }

    /**
     * @param mixed $cart
     */
    public function restore_original_cart_item_prices($cart): void
    {
        if (!$this->is_supported_cart_context($cart)) {
            return;
        }

        if ($this->cart_is_empty($cart)) {
            $this->clear_applied_entries();
            return;
        }

        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (!is_array($cart_item)) {
                continue;
            }

            $product = $this->get_product_from_cart_item($cart_item);
            $original_unit_price = isset($cart_item[self::CART_ITEM_ORIGINAL_UNIT_PRICE_KEY])
                ? (float) $cart_item[self::CART_ITEM_ORIGINAL_UNIT_PRICE_KEY]
                : null;

            if ($original_unit_price !== null && is_object($product) && method_exists($product, 'set_price')) {
                $product->set_price(vms_discounts_format_internal_price(max(0.0, $original_unit_price)));
            }

            $this->clear_cart_item_adjustment_data($cart, (string) $cart_item_key);
        }
    }

    /**
     * @param mixed $cart
     */
    public function apply_cart_item_adjustments($cart): void
    {
        if (!$this->is_supported_cart_context($cart)) {
            return;
        }

        if ($this->cart_is_empty($cart)) {
            $this->clear_applied_entries();
            return;
        }

        $discount_contexts = $this->build_discount_contexts($cart);
        if (empty($discount_contexts)) {
            $this->clear_applied_entries();
            return;
        }

        $applied_entries = $this->collect_applied_entries_for_contexts($discount_contexts);
        if (empty($applied_entries)) {
            $this->clear_applied_entries();
            return;
        }

        $allocation = $this->build_discount_allocation_map($applied_entries, $discount_contexts);
        $allocated_entries = array_values(array_filter(vms_discounts_array($allocation['applied_entries'] ?? []), 'is_array'));

        if (empty($allocated_entries)) {
            $this->clear_applied_entries();
            return;
        }

        $this->apply_discounted_item_prices($cart, vms_discounts_array($allocation['items'] ?? []));
        $this->store_applied_entries($allocated_entries);
    }

    public function render_cart_discount_rows(): void
    {
        $this->render_discount_summary_rows();
    }

    public function render_checkout_discount_rows(): void
    {
        $this->render_discount_summary_rows();
    }

    /**
     * Keep classic Cart/Checkout item rows explicit when VMS changes the live cart price.
     *
     * @param string $price_html
     * @param array<string, mixed> $cart_item
     * @param string $cart_item_key
     */
    public function filter_cart_item_price_html($price_html, $cart_item, $cart_item_key): string
    {
        if (!is_array($cart_item)) {
            return (string) $price_html;
        }

        $display_data = $this->get_cart_item_discount_display_data($cart_item);
        if (empty($display_data['has_discount'])) {
            return (string) $price_html;
        }

        return '<span class="vms-discounts-cart-price vms-discounts-cart-price--unit">'
            . '<span class="vms-discounts-cart-price__row">'
            . '<del class="vms-discounts-cart-price__original">' . wp_kses_post((string) ($display_data['original_unit_price_html'] ?? '')) . '</del>'
            . '<ins class="vms-discounts-cart-price__current">' . wp_kses_post((string) ($display_data['discounted_unit_price_html'] ?? '')) . '</ins>'
            . '</span>'
            . '</span>';
    }

    /**
     * @param string $subtotal_html
     * @param array<string, mixed> $cart_item
     * @param string $cart_item_key
     */
    public function filter_cart_item_subtotal_html($subtotal_html, $cart_item, $cart_item_key): string
    {
        if (!is_array($cart_item)) {
            return (string) $subtotal_html;
        }

        $display_data = $this->get_cart_item_discount_display_data($cart_item);
        if (empty($display_data['has_discount'])) {
            return (string) $subtotal_html;
        }

        return '<span class="vms-discounts-cart-price vms-discounts-cart-price--line">'
            . '<span class="vms-discounts-cart-price__row">'
            . '<del class="vms-discounts-cart-price__original">' . wp_kses_post((string) ($display_data['original_line_total_html'] ?? '')) . '</del>'
            . '<ins class="vms-discounts-cart-price__current">' . wp_kses_post((string) ($display_data['discounted_line_total_html'] ?? '')) . '</ins>'
            . '</span>'
            . '</span>';
    }

    public function enqueue_discount_display_assets(): void
    {
        if (!function_exists('wp_enqueue_style')) {
            return;
        }

        $is_cart = function_exists('is_cart') && is_cart();
        $is_checkout = function_exists('is_checkout') && is_checkout();
        if (!$is_cart && !$is_checkout) {
            return;
        }

        wp_enqueue_style(
            'vms-commerce-discounts-display',
            VMS_DISCOUNTS_URL . 'assets/frontend/discounts-display.css',
            [],
            VMS_DISCOUNTS_VERSION
        );
    }

    public function register_store_api_cart_item_display_data(): void
    {
        if ($this->store_api_display_data_registered) {
            return;
        }

        if (!function_exists('woocommerce_store_api_register_endpoint_data')) {
            return;
        }

        if (!class_exists('Automattic\\WooCommerce\\StoreApi\\Schemas\\V1\\CartItemSchema')) {
            return;
        }

        $this->store_api_display_data_registered = true;

        woocommerce_store_api_register_endpoint_data([
            'endpoint' => Automattic\WooCommerce\StoreApi\Schemas\V1\CartItemSchema::IDENTIFIER,
            'namespace' => 'vms-commerce-discounts',
            'data_callback' => [$this, 'store_api_cart_item_display_data'],
            'schema_callback' => [$this, 'store_api_cart_item_display_schema'],
            'schema_type' => ARRAY_A,
        ]);
    }

    /**
     * @param array<string, mixed> $cart_item
     * @return array<string, mixed>
     */
    public function store_api_cart_item_display_data($cart_item): array
    {
        if (!is_array($cart_item)) {
            return ['has_discount' => false];
        }

        return $this->get_cart_item_discount_display_data($cart_item);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function store_api_cart_item_display_schema(): array
    {
        return [
            'has_discount' => [
                'description' => 'Whether this cart item has a VMS Commerce Discounts adjustment.',
                'type' => 'boolean',
                'readonly' => true,
            ],
            'label' => [
                'description' => 'Customer-facing discount label.',
                'type' => 'string',
                'readonly' => true,
            ],
            'original_unit_price_html' => [
                'description' => 'Formatted original unit price before the VMS discount for classic Woo templates.',
                'type' => 'string',
                'readonly' => true,
            ],
            'discounted_unit_price_html' => [
                'description' => 'Formatted discounted unit price after the VMS discount for classic Woo templates.',
                'type' => 'string',
                'readonly' => true,
            ],
            'original_line_total_html' => [
                'description' => 'Formatted original line total before the VMS discount for classic Woo templates.',
                'type' => 'string',
                'readonly' => true,
            ],
            'discounted_line_total_html' => [
                'description' => 'Formatted discounted line total after the VMS discount for classic Woo templates.',
                'type' => 'string',
                'readonly' => true,
            ],
            'line_discount_html' => [
                'description' => 'Formatted line discount amount for classic Woo templates.',
                'type' => 'string',
                'readonly' => true,
            ],
            'original_unit_price_text' => [
                'description' => 'Plain-text original unit price before the VMS discount for Woo Blocks filters.',
                'type' => 'string',
                'readonly' => true,
            ],
            'discounted_unit_price_text' => [
                'description' => 'Plain-text discounted unit price after the VMS discount for Woo Blocks filters.',
                'type' => 'string',
                'readonly' => true,
            ],
            'original_line_total_text' => [
                'description' => 'Plain-text original line total before the VMS discount for Woo Blocks filters.',
                'type' => 'string',
                'readonly' => true,
            ],
            'discounted_line_total_text' => [
                'description' => 'Plain-text discounted line total after the VMS discount for Woo Blocks filters.',
                'type' => 'string',
                'readonly' => true,
            ],
            'line_discount_text' => [
                'description' => 'Plain-text line discount amount for Woo Blocks filters.',
                'type' => 'string',
                'readonly' => true,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $cart_item
     * @return array<string, mixed>
     */
    protected function get_cart_item_discount_display_data(array $cart_item): array
    {
        $quantity = max(0, (int) ($cart_item['quantity'] ?? 0));
        $original_unit_price = isset($cart_item[self::CART_ITEM_ORIGINAL_UNIT_PRICE_KEY])
            ? max(0.0, (float) $cart_item[self::CART_ITEM_ORIGINAL_UNIT_PRICE_KEY])
            : 0.0;
        $unit_discount = isset($cart_item[self::CART_ITEM_UNIT_DISCOUNT_KEY])
            ? max(0.0, (float) $cart_item[self::CART_ITEM_UNIT_DISCOUNT_KEY])
            : 0.0;
        $line_discount = isset($cart_item[self::CART_ITEM_LINE_DISCOUNT_KEY])
            ? max(0.0, (float) $cart_item[self::CART_ITEM_LINE_DISCOUNT_KEY])
            : 0.0;

        if ($quantity <= 0 || $original_unit_price <= 0.0 || $unit_discount <= 0.0 || $line_discount <= 0.0) {
            return ['has_discount' => false];
        }

        $discounted_unit_price = max(0.0, $original_unit_price - $unit_discount);
        $original_line_total = max(0.0, $original_unit_price * $quantity);
        $discounted_line_total = max(0.0, $original_line_total - $line_discount);

        $labels = array_values(array_filter(vms_discounts_array($cart_item[self::CART_ITEM_ADJUSTMENT_LABELS_KEY] ?? []), 'is_string'));
        $label = implode(', ', array_unique(array_map('trim', $labels)));
        if ($label === '') {
            $label = 'Discount applied';
        }

        $original_unit_price_html = function_exists('wc_price') ? wc_price($original_unit_price) : (string) $original_unit_price;
        $discounted_unit_price_html = function_exists('wc_price') ? wc_price($discounted_unit_price) : (string) $discounted_unit_price;
        $original_line_total_html = function_exists('wc_price') ? wc_price($original_line_total) : (string) $original_line_total;
        $discounted_line_total_html = function_exists('wc_price') ? wc_price($discounted_line_total) : (string) $discounted_line_total;
        $line_discount_html = function_exists('wc_price') ? wc_price($line_discount) : (string) $line_discount;

        return [
            'has_discount' => true,
            'label' => $label,
            'original_unit_price' => round($original_unit_price, vms_discounts_price_decimals()),
            'discounted_unit_price' => round($discounted_unit_price, vms_discounts_price_decimals()),
            'original_line_total' => round($original_line_total, vms_discounts_price_decimals()),
            'discounted_line_total' => round($discounted_line_total, vms_discounts_price_decimals()),
            'line_discount' => round($line_discount, vms_discounts_price_decimals()),
            'original_unit_price_html' => $original_unit_price_html,
            'discounted_unit_price_html' => $discounted_unit_price_html,
            'original_line_total_html' => $original_line_total_html,
            'discounted_line_total_html' => $discounted_line_total_html,
            'line_discount_html' => $line_discount_html,
            'original_unit_price_text' => $this->price_html_to_text($original_unit_price_html),
            'discounted_unit_price_text' => $this->price_html_to_text($discounted_unit_price_html),
            'original_line_total_text' => $this->price_html_to_text($original_line_total_html),
            'discounted_line_total_text' => $this->price_html_to_text($discounted_line_total_html),
            'line_discount_text' => $this->price_html_to_text($line_discount_html),
        ];
    }

    protected function price_html_to_text(string $price_html): string
    {
        $text = $price_html;
        if (function_exists('wp_strip_all_tags')) {
            $text = wp_strip_all_tags($text);
        } else {
            $text = strip_tags($text);
        }

        $charset = function_exists('get_bloginfo') ? (string) get_bloginfo('charset') : 'UTF-8';
        if ($charset === '') {
            $charset = 'UTF-8';
        }

        $text = html_entity_decode((string) $text, ENT_QUOTES, $charset);
        $text = preg_replace('/\s+/', ' ', $text);

        return trim((string) $text);
    }

    protected function render_discount_summary_rows(): void
    {
        if (!function_exists('wc_price')) {
            return;
        }

        $rows = $this->get_display_discount_rows();
        if (empty($rows)) {
            return;
        }

        foreach ($rows as $row) {
            $label = (string) ($row['label'] ?? '');
            $amount = (float) ($row['amount'] ?? 0.0);

            if ($label === '' || $amount <= 0.0) {
                continue;
            }

            echo '<tr class="vms-discounts-summary">';
            echo '<th>' . esc_html($label) . '</th>';
            echo '<td data-title="' . esc_attr($label) . '">' . wp_kses_post(wc_price(-1 * $amount)) . '</td>';
            echo '</tr>';
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function get_display_discount_rows(): array
    {
        $rows_by_label = [];
        $label_order = [];

        foreach ($this->get_applied_entries_from_session() as $entry) {
            $public_label = trim((string) ($entry['public_label'] ?? ''));
            if ($public_label === '') {
                $public_label = 'Discount';
            }

            $label = sprintf('Discount: %s', $public_label);
            if (!isset($rows_by_label[$label])) {
                $rows_by_label[$label] = [
                    'label' => $label,
                    'amount' => 0.0,
                ];
                $label_order[] = $label;
            }

            $rows_by_label[$label]['amount'] += (float) ($entry['discount_amount'] ?? 0.0);
        }

        $rows = [];
        foreach ($label_order as $label) {
            if (!isset($rows_by_label[$label])) {
                continue;
            }

            $row = $rows_by_label[$label];
            $row['amount'] = round(max(0.0, (float) ($row['amount'] ?? 0.0)), vms_discounts_price_decimals());
            if ((float) $row['amount'] <= 0.0) {
                continue;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param array<string, array<string, mixed>> $discount_contexts
     * @return array<int, array<string, mixed>>
     */
    protected function collect_applied_entries_for_contexts(array $discount_contexts): array
    {
        $applied_entries = [];

        foreach ($discount_contexts as $context_key => $event_context) {
            $candidate_rules = $this->rules_for_context($event_context);
            if (empty($candidate_rules)) {
                continue;
            }

            $event_id = (int) ($event_context['event_id'] ?? 0);
            $context_key = (string) ($event_context['context_key'] ?? (string) $context_key);

            $stack_rules = [];
            $best_only_rules = [];

            foreach ($candidate_rules as $rule) {
                if (!vms_discounts_bool($rule['enabled'] ?? false)) {
                    continue;
                }

                $evaluation = $this->evaluate_rule_for_event($rule, $event_context);
                if (!vms_discounts_bool($evaluation['qualified'] ?? false)) {
                    continue;
                }

                $discount_amount = (float) ($evaluation['discount_amount'] ?? 0.0);
                if ($discount_amount <= 0.0) {
                    continue;
                }

                $entry = [
                    'rule' => $rule,
                    'discount_amount' => $discount_amount,
                    'qualified_units' => (int) ($evaluation['qualified_units'] ?? 1),
                    'applies_subtotal' => (float) ($evaluation['applies_subtotal'] ?? 0.0),
                ];

                if (($rule['stacking_mode'] ?? 'stack') === 'best_only') {
                    $best_only_rules[] = $entry;
                } else {
                    $stack_rules[] = $entry;
                }
            }

            $rules_to_apply = $stack_rules;

            if (!empty($best_only_rules)) {
                usort($best_only_rules, static function (array $a, array $b): int {
                    $discount_cmp = ((float) ($b['discount_amount'] ?? 0.0)) <=> ((float) ($a['discount_amount'] ?? 0.0));
                    if ($discount_cmp !== 0) {
                        return $discount_cmp;
                    }

                    $priority_cmp = ((int) ($a['rule']['priority'] ?? 100)) <=> ((int) ($b['rule']['priority'] ?? 100));
                    if ($priority_cmp !== 0) {
                        return $priority_cmp;
                    }

                    return strcmp((string) ($a['rule']['id'] ?? ''), (string) ($b['rule']['id'] ?? ''));
                });

                $rules_to_apply[] = $best_only_rules[0];
            }

            if (empty($rules_to_apply)) {
                continue;
            }

            usort($rules_to_apply, static function (array $a, array $b): int {
                $priority_cmp = ((int) ($a['rule']['priority'] ?? 100)) <=> ((int) ($b['rule']['priority'] ?? 100));
                if ($priority_cmp !== 0) {
                    return $priority_cmp;
                }

                return strcmp((string) ($a['rule']['id'] ?? ''), (string) ($b['rule']['id'] ?? ''));
            });

            $remaining_context_subtotal = max(0.0, (float) ($event_context['subtotal_event_total'] ?? 0.0));
            if ($remaining_context_subtotal <= 0.0) {
                continue;
            }

            foreach ($rules_to_apply as $entry) {
                if ($remaining_context_subtotal <= 0.0) {
                    break;
                }

                $rule = $entry['rule'];
                $raw_discount_amount = (float) ($entry['discount_amount'] ?? 0.0);
                if ($raw_discount_amount <= 0.0) {
                    continue;
                }

                $applied_discount = min($raw_discount_amount, $remaining_context_subtotal);
                $applied_discount = round(max(0.0, $applied_discount), vms_discounts_price_decimals());

                if ($applied_discount <= 0.0) {
                    continue;
                }

                $applied_entries[] = [
                    'event_id' => (int) $event_id,
                    'context_key' => $context_key,
                    'context_type' => (string) ($event_context['context_type'] ?? 'event'),
                    'rule_id' => (string) ($rule['id'] ?? ''),
                    'public_label' => $this->label_for_rule($rule),
                    'admin_label' => trim((string) ($rule['admin_label'] ?? '')),
                    'discount_amount' => $applied_discount,
                    'discount_type' => (string) ($rule['discount_type'] ?? ''),
                    'applies_to' => (string) ($rule['applies_to'] ?? ''),
                    'product_ids' => array_values(array_unique(array_map('intval', vms_discounts_array($rule['product_ids'] ?? [])))),
                    'qualified_units' => (int) ($entry['qualified_units'] ?? 1),
                    'source' => (string) ($rule['_origin'] ?? 'event'),
                    'timestamp' => vms_discounts_now(),
                ];

                $remaining_context_subtotal -= $applied_discount;
            }
        }

        return $applied_entries;
    }

    /**
     * @param array<int, array<string, mixed>> $applied_entries
     * @param array<string, array<string, mixed>> $discount_contexts
     * @return array<string, array<string, mixed>>
     */
    protected function build_discount_allocation_map(array $applied_entries, array $discount_contexts): array
    {
        $item_allocations = [];
        $final_entries = [];

        foreach ($applied_entries as $entry) {
            $context_key = (string) ($entry['context_key'] ?? '');
            if ($context_key === '') {
                $event_id = (int) ($entry['event_id'] ?? 0);
                $context_key = $event_id > 0 ? 'event:' . $event_id : 'order';
            }
            if ($context_key === '' || !isset($discount_contexts[$context_key])) {
                continue;
            }

            $targets = $this->build_eligible_targets_for_entry($entry, $discount_contexts[$context_key], $item_allocations);
            if (empty($targets)) {
                continue;
            }

            $entry_allocations = $this->allocate_discount_across_targets($targets, (float) ($entry['discount_amount'] ?? 0.0));
            if (empty($entry_allocations)) {
                continue;
            }

            $allocated_minor_units = 0;
            $affected_cart_item_keys = [];
            foreach ($entry_allocations as $cart_item_key => $line_discount_minor_units) {
                $line_discount_minor_units = max(0, (int) $line_discount_minor_units);
                if ($line_discount_minor_units <= 0) {
                    continue;
                }

                if (!isset($item_allocations[$cart_item_key])) {
                    $item_allocations[$cart_item_key] = [
                        'line_discount_minor_units' => 0,
                        'labels' => [],
                    ];
                }

                $item_allocations[$cart_item_key]['line_discount_minor_units'] += $line_discount_minor_units;
                $item_allocations[$cart_item_key]['labels'][$entry['public_label']] = true;
                $allocated_minor_units += $line_discount_minor_units;
                $affected_cart_item_keys[] = (string) $cart_item_key;
            }

            if ($allocated_minor_units <= 0) {
                continue;
            }

            $entry['discount_amount'] = vms_discounts_minor_units_to_amount($allocated_minor_units);
            $entry['affected_cart_item_keys'] = array_values(array_unique(array_filter($affected_cart_item_keys, 'is_string')));
            $final_entries[] = $entry;
        }

        $items = [];
        foreach ($item_allocations as $cart_item_key => $allocation) {
            $line_discount_minor_units = max(0, (int) ($allocation['line_discount_minor_units'] ?? 0));
            if ($line_discount_minor_units <= 0) {
                continue;
            }

            $items[$cart_item_key] = [
                'line_discount' => vms_discounts_minor_units_to_amount($line_discount_minor_units),
                'labels' => array_keys(vms_discounts_array($allocation['labels'] ?? [])),
            ];
        }

        return [
            'items' => $items,
            'applied_entries' => $final_entries,
        ];
    }

    /**
     * @param array<string, mixed> $entry
     * @param array<string, mixed> $event_context
     * @param array<string, array<string, mixed>> $item_allocations
     * @return array<int, array<string, mixed>>
     */
    protected function build_eligible_targets_for_entry(array $entry, array $event_context, array $item_allocations): array
    {
        $applies_to = (string) ($entry['applies_to'] ?? 'tickets');

        if ($applies_to === 'entitlements') {
            $source_items = vms_discounts_array($event_context['entitlement_items'] ?? []);
        } elseif ($applies_to === 'both' || $applies_to === 'all_products') {
            $source_items = vms_discounts_array($event_context['all_items'] ?? []);
        } elseif ($applies_to === 'selected_products') {
            $product_ids = array_values(array_unique(array_map('intval', vms_discounts_array($entry['product_ids'] ?? []))));
            $source_items = [];
            if (!empty($product_ids)) {
                foreach (vms_discounts_array($event_context['all_items'] ?? []) as $candidate_item) {
                    if (!is_array($candidate_item)) {
                        continue;
                    }
                    $product_id = (int) ($candidate_item['product_id'] ?? 0);
                    if (in_array($product_id, $product_ids, true)) {
                        $source_items[] = $candidate_item;
                    }
                }
            }
        } else {
            $source_items = vms_discounts_array($event_context['ticket_items'] ?? []);
        }

        $targets = [];

        foreach ($source_items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $cart_item_key = (string) ($item['cart_item_key'] ?? '');
            $quantity = max(0, (int) ($item['quantity'] ?? 0));
            $line_subtotal_minor_units = vms_discounts_amount_to_minor_units((float) ($item['line_subtotal'] ?? 0.0));

            if ($cart_item_key === '' || $quantity <= 0 || $line_subtotal_minor_units <= 0) {
                continue;
            }

            $already_allocated_minor_units = 0;
            if (isset($item_allocations[$cart_item_key])) {
                $already_allocated_minor_units = max(
                    0,
                    (int) ($item_allocations[$cart_item_key]['line_discount_minor_units'] ?? 0)
                );
            }

            $remaining_minor_units = max(0, $line_subtotal_minor_units - $already_allocated_minor_units);
            if ($remaining_minor_units <= 0) {
                continue;
            }

            $targets[] = [
                'cart_item_key' => $cart_item_key,
                'quantity' => $quantity,
                'remaining_minor_units' => $remaining_minor_units,
            ];
        }

        return $targets;
    }

    /**
     * @param array<int, array<string, mixed>> $targets
     * @return array<string, int>
     */
    protected function allocate_discount_across_targets(array $targets, float $discount_amount): array
    {
        $discount_minor_units = vms_discounts_amount_to_minor_units($discount_amount);
        $total_remaining_minor_units = 0;

        foreach ($targets as $target) {
            $total_remaining_minor_units += max(0, (int) ($target['remaining_minor_units'] ?? 0));
        }

        $discount_minor_units = min($discount_minor_units, $total_remaining_minor_units);
        if ($discount_minor_units <= 0 || empty($targets)) {
            return [];
        }

        $total_quantity = 0;
        foreach ($targets as $target) {
            $total_quantity += max(0, (int) ($target['quantity'] ?? 0));
        }

        $allocations = [];
        $remaining_discount_minor_units = $discount_minor_units;
        $remaining_basis_minor_units = $total_remaining_minor_units;
        $remaining_quantity = $total_quantity;
        $last_index = count($targets) - 1;

        foreach ($targets as $index => $target) {
            $cart_item_key = (string) ($target['cart_item_key'] ?? '');
            $target_basis = max(0, (int) ($target['remaining_minor_units'] ?? 0));
            $target_quantity = max(0, (int) ($target['quantity'] ?? 0));

            if ($cart_item_key === '' || $target_basis <= 0 || $remaining_discount_minor_units <= 0) {
                $allocations[$cart_item_key] = 0;
                $remaining_basis_minor_units -= $target_basis;
                $remaining_quantity -= $target_quantity;
                continue;
            }

            if ($index === $last_index) {
                $share = $remaining_discount_minor_units;
            } elseif ($remaining_basis_minor_units > 0) {
                $share = (int) round(
                    ($remaining_discount_minor_units * $target_basis) / $remaining_basis_minor_units,
                    0,
                    PHP_ROUND_HALF_UP
                );
            } elseif ($remaining_quantity > 0) {
                $share = (int) round(
                    ($remaining_discount_minor_units * $target_quantity) / $remaining_quantity,
                    0,
                    PHP_ROUND_HALF_UP
                );
            } else {
                $share = 0;
            }

            $share = max(0, min($share, $target_basis, $remaining_discount_minor_units));

            $allocations[$cart_item_key] = $share;
            $remaining_discount_minor_units -= $share;
            $remaining_basis_minor_units -= $target_basis;
            $remaining_quantity -= $target_quantity;
        }

        return array_filter($allocations, static function (int $value): bool {
            return $value > 0;
        });
    }

    /**
     * @param mixed $cart
     * @param array<string, array<string, mixed>> $allocation_map
     */
    protected function apply_discounted_item_prices($cart, array $allocation_map): void
    {
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (!is_array($cart_item)) {
                continue;
            }

            $product = $this->get_product_from_cart_item($cart_item);
            $quantity = max(0, (int) ($cart_item['quantity'] ?? 0));

            if ($quantity <= 0 || !is_object($product) || !method_exists($product, 'get_price') || !method_exists($product, 'set_price')) {
                continue;
            }

            $original_unit_price = max(0.0, (float) $product->get_price());
            $line_discount = 0.0;
            $labels = [];

            if (isset($allocation_map[$cart_item_key]) && is_array($allocation_map[$cart_item_key])) {
                $line_discount = max(0.0, (float) ($allocation_map[$cart_item_key]['line_discount'] ?? 0.0));
                $labels = array_values(array_filter(vms_discounts_array($allocation_map[$cart_item_key]['labels'] ?? []), 'is_string'));
            }

            $max_line_discount = max(0.0, $original_unit_price * $quantity);
            $line_discount = min($line_discount, $max_line_discount);

            if ($line_discount <= 0.0) {
                continue;
            }

            $unit_discount = $line_discount / $quantity;
            $adjusted_unit_price = max(0.0, $original_unit_price - $unit_discount);

            $product->set_price(vms_discounts_format_internal_price($adjusted_unit_price));

            $this->set_cart_item_adjustment_data($cart, (string) $cart_item_key, [
                self::CART_ITEM_ORIGINAL_UNIT_PRICE_KEY => $original_unit_price,
                self::CART_ITEM_UNIT_DISCOUNT_KEY => $unit_discount,
                self::CART_ITEM_LINE_DISCOUNT_KEY => $line_discount,
                self::CART_ITEM_ADJUSTMENT_LABELS_KEY => array_values(array_unique($labels)),
            ]);
        }
    }

    protected function label_for_rule(array $rule): string
    {
        $public_label = trim((string) ($rule['public_label'] ?? ''));
        if ($public_label === '') {
            $public_label = trim((string) ($rule['admin_label'] ?? ''));
        }
        if ($public_label === '') {
            $public_label = 'Discount';
        }

        return $public_label;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<int, array<string, mixed>>
     */
    protected function rules_for_context(array $context): array
    {
        $context_type = (string) ($context['context_type'] ?? 'event');
        if ($context_type === 'order') {
            return $this->global_order_rules();
        }

        $event_id = (int) ($context['event_id'] ?? 0);
        if ($event_id <= 0) {
            return [];
        }

        $event_rules = $this->rules->get_event_rules($event_id);
        $global_rules = $this->rules->global_rules_enabled() ? $this->rules->get_global_rules() : [];

        $block_global = $this->rules->event_blocks_global($event_rules);
        $merged = [];

        foreach ($event_rules as $rule) {
            if (($rule['scope'] ?? 'event_plus_global') === 'global_only') {
                continue;
            }
            $rule['_origin'] = 'event';
            $merged[] = $rule;
        }

        if (!$block_global) {
            foreach ($global_rules as $rule) {
                if (($rule['scope'] ?? 'event_plus_global') === 'event_only') {
                    continue;
                }
                if ($this->rule_should_run_order_wide($rule)) {
                    continue;
                }
                $rule['_origin'] = 'global';
                $merged[] = $rule;
            }
        }

        return $this->rules->sort_rules($merged);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function global_order_rules(): array
    {
        if (!$this->rules->global_rules_enabled()) {
            return [];
        }

        $rules = [];
        foreach ($this->rules->get_global_rules() as $rule) {
            if (($rule['scope'] ?? 'event_plus_global') === 'event_only') {
                continue;
            }
            if (!$this->rule_should_run_order_wide($rule)) {
                continue;
            }
            $rule['_origin'] = 'global';
            $rules[] = $rule;
        }

        return $this->rules->sort_rules($rules);
    }

    protected function rule_should_run_order_wide(array $rule): bool
    {
        $applies_to = (string) ($rule['applies_to'] ?? 'tickets');
        $qual_type = (string) ($rule['qual_type'] ?? 'ticket_qty');

        if (in_array($applies_to, ['selected_products', 'all_products'], true)) {
            return true;
        }

        return $qual_type === 'order_product_qty';
    }

    /**
     * @param array<string, mixed> $rule
     * @param array<string, mixed> $event_context
     * @return array<string, mixed>
     */
    protected function evaluate_rule_for_event(array $rule, array $event_context): array
    {
        $required_qty = max(1, (int) ($rule['required_qty'] ?? 1));
        $max_applications = max(1, (int) ($rule['max_applications_per_order'] ?? 1));

        $qual_type = (string) ($rule['qual_type'] ?? 'ticket_qty');
        $qual_have = 0;
        $qual_units = 0;

        if ($qual_type === 'ticket_qty') {
            $qual_have = (int) ($event_context['ticket_qty_total'] ?? 0);
            $qual_units = (int) floor($qual_have / $required_qty);
        } elseif ($qual_type === 'order_product_qty') {
            foreach (vms_discounts_array($event_context['all_items'] ?? []) as $item) {
                if ((float) ($item['line_subtotal'] ?? 0.0) <= 0.0) {
                    continue;
                }
                $qual_have += (int) ($item['quantity'] ?? 0);
            }
            $qual_units = (int) floor($qual_have / $required_qty);
        } elseif ($qual_type === 'product_group_qty') {
            $product_ids = array_values(array_unique(array_map('intval', vms_discounts_array($rule['product_ids'] ?? []))));
            if (empty($product_ids)) {
                return [
                    'qualified' => false,
                    'qualified_units' => 0,
                    'required_qty' => $required_qty,
                    'qual_have' => 0,
                    'applies_subtotal' => 0.0,
                    'discount_amount' => 0.0,
                ];
            }

            foreach (vms_discounts_array($event_context['all_items'] ?? []) as $item) {
                $product_id = (int) ($item['product_id'] ?? 0);
                if (!in_array($product_id, $product_ids, true)) {
                    continue;
                }
                if ((float) ($item['line_subtotal'] ?? 0.0) <= 0.0) {
                    continue;
                }
                $qual_have += (int) ($item['quantity'] ?? 0);
            }

            $qual_units = (int) floor($qual_have / $required_qty);
        } elseif ($qual_type === 'combo_qty') {
            $ticket_qty = (int) ($event_context['ticket_qty_total'] ?? 0);
            $entitlement_qty = (int) ($event_context['entitlement_qty_total'] ?? 0);
            $qual_have = min($ticket_qty, $entitlement_qty);
            $ticket_units = (int) floor($ticket_qty / $required_qty);
            $entitlement_units = (int) floor($entitlement_qty / $required_qty);
            $qual_units = min($ticket_units, $entitlement_units);
        } else {
            $qual_have = (int) ($event_context['ticket_qty_total'] ?? 0);
            $qual_units = (int) floor($qual_have / $required_qty);
        }

        if ($qual_units < 1) {
            return [
                'qualified' => false,
                'qualified_units' => 0,
                'required_qty' => $required_qty,
                'qual_have' => $qual_have,
                'applies_subtotal' => 0.0,
                'discount_amount' => 0.0,
            ];
        }

        $applications = min($qual_units, $max_applications);
        $applies_subtotal = $this->applies_subtotal_for_rule($rule, $event_context);

        if ($applies_subtotal <= 0.0) {
            return [
                'qualified' => false,
                'qualified_units' => $applications,
                'required_qty' => $required_qty,
                'qual_have' => $qual_have,
                'applies_subtotal' => 0.0,
                'discount_amount' => 0.0,
            ];
        }

        $amount = max(0.0, (float) ($rule['amount'] ?? 0.0));
        $discount_type = (string) ($rule['discount_type'] ?? 'percent');
        $discount_amount = 0.0;

        if ($discount_type === 'fixed_total') {
            $discount_amount = $amount * max(1, $applications);
        } elseif ($discount_type === 'fixed_per_unit') {
            $discount_amount = $amount * $applications;
        } else {
            $percent = min(100.0, $amount);
            $discount_amount = $applies_subtotal * ($percent / 100);
            if ($applications > 1) {
                $discount_amount *= $applications;
            }
        }

        $cap_amount = (float) ($rule['cap_amount'] ?? 0.0);
        if ($cap_amount > 0.0) {
            $discount_amount = min($discount_amount, $cap_amount);
        }

        $discount_amount = min($discount_amount, $applies_subtotal);
        $discount_amount = max(0.0, $discount_amount);

        return [
            'qualified' => ($discount_amount > 0.0),
            'qualified_units' => $applications,
            'required_qty' => $required_qty,
            'qual_have' => $qual_have,
            'applies_subtotal' => $applies_subtotal,
            'discount_amount' => $discount_amount,
        ];
    }

    /**
     * @param array<string, mixed> $rule
     * @param array<string, mixed> $event_context
     */
    protected function applies_subtotal_for_rule(array $rule, array $event_context): float
    {
        $applies_to = (string) ($rule['applies_to'] ?? 'tickets');

        if ($applies_to === 'entitlements') {
            return max(0.0, (float) ($event_context['subtotal_entitlements'] ?? 0.0));
        }

        if ($applies_to === 'both' || $applies_to === 'all_products') {
            return max(0.0, (float) ($event_context['subtotal_event_total'] ?? 0.0));
        }

        if ($applies_to === 'selected_products') {
            $product_ids = array_values(array_unique(array_map('intval', vms_discounts_array($rule['product_ids'] ?? []))));
            if (empty($product_ids)) {
                return 0.0;
            }

            $subtotal = 0.0;
            foreach (vms_discounts_array($event_context['all_items'] ?? []) as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $product_id = (int) ($item['product_id'] ?? 0);
                if (!in_array($product_id, $product_ids, true)) {
                    continue;
                }
                $subtotal += max(0.0, (float) ($item['line_subtotal'] ?? 0.0));
            }

            return $subtotal;
        }

        return max(0.0, (float) ($event_context['subtotal_tickets'] ?? 0.0));
    }

    /**
     * @param mixed $cart
     * @return array<string, array<string, mixed>>
     */
    protected function build_discount_contexts($cart): array
    {
        $contexts = [];
        $order_context = $this->empty_discount_context('order', 0, 'order');

        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (!is_array($cart_item)) {
                continue;
            }

            $quantity = max(0, (int) ($cart_item['quantity'] ?? 0));
            if ($quantity <= 0) {
                continue;
            }

            $classification = $this->mapping->classify_cart_item($cart_item);
            $type = (string) ($classification['type'] ?? 'other');
            $event_id = (int) ($classification['event_id'] ?? 0);
            $product_id = (int) ($classification['product_id'] ?? 0);
            if ($product_id <= 0) {
                continue;
            }

            $line_subtotal = $this->get_cart_item_line_subtotal($cart_item, $classification, $quantity);
            $is_paid_line = ($line_subtotal > 0.0);

            $normalized_item = [
                'cart_item_key' => (string) $cart_item_key,
                'product_id' => $product_id,
                'quantity' => $quantity,
                'line_subtotal' => $line_subtotal,
                'is_paid_line' => $is_paid_line,
                'type' => $type,
                'event_id' => $event_id,
            ];

            $this->add_item_to_discount_context($order_context, $normalized_item);

            if ($event_id <= 0 || !in_array($type, ['ticket', 'entitlement'], true)) {
                continue;
            }

            $context_key = 'event:' . $event_id;
            if (!isset($contexts[$context_key])) {
                $contexts[$context_key] = $this->empty_discount_context('event', $event_id, $context_key);
            }

            $this->add_item_to_discount_context($contexts[$context_key], $normalized_item);
        }

        if (!empty($order_context['all_items']) && !empty($this->global_order_rules())) {
            $contexts['order'] = $order_context;
        }

        return $contexts;
    }

    /**
     * @return array<string, mixed>
     */
    protected function empty_discount_context(string $context_type, int $event_id, string $context_key): array
    {
        return [
            'context_type' => $context_type,
            'context_key' => $context_key,
            'event_id' => $event_id,
            'ticket_items' => [],
            'entitlement_items' => [],
            'other_items' => [],
            'all_items' => [],
            'ticket_qty_total' => 0,
            'entitlement_qty_total' => 0,
            'other_qty_total' => 0,
            'subtotal_tickets' => 0.0,
            'subtotal_entitlements' => 0.0,
            'subtotal_other' => 0.0,
            'subtotal_event_total' => 0.0,
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $item
     */
    protected function add_item_to_discount_context(array &$context, array $item): void
    {
        $type = (string) ($item['type'] ?? 'other');
        $quantity = max(0, (int) ($item['quantity'] ?? 0));
        $line_subtotal = max(0.0, (float) ($item['line_subtotal'] ?? 0.0));
        $is_paid_line = ($line_subtotal > 0.0);

        $context['all_items'][] = $item;
        $context['subtotal_event_total'] += $line_subtotal;

        if ($type === 'ticket') {
            $context['ticket_items'][] = $item;
            if ($is_paid_line) {
                $context['ticket_qty_total'] += $quantity;
            }
            $context['subtotal_tickets'] += $line_subtotal;
            return;
        }

        if ($type === 'entitlement') {
            $context['entitlement_items'][] = $item;
            if ($is_paid_line) {
                $context['entitlement_qty_total'] += $quantity;
            }
            $context['subtotal_entitlements'] += $line_subtotal;
            return;
        }

        $context['other_items'][] = $item;
        if ($is_paid_line) {
            $context['other_qty_total'] += $quantity;
        }
        $context['subtotal_other'] += $line_subtotal;
    }

    /**
     * @param array<string, mixed> $cart_item
     * @param array<string, mixed> $classification
     */
    protected function get_cart_item_line_subtotal(array $cart_item, array $classification, int $quantity): float
    {
        if (isset($cart_item[self::CART_ITEM_ORIGINAL_UNIT_PRICE_KEY])) {
            return max(0.0, (float) $cart_item[self::CART_ITEM_ORIGINAL_UNIT_PRICE_KEY]) * $quantity;
        }

        $recorded_line_subtotal = $this->get_recorded_cart_item_line_subtotal($cart_item);
        if ($recorded_line_subtotal !== null) {
            return $recorded_line_subtotal;
        }

        $product = $classification['product'] ?? $this->get_product_from_cart_item($cart_item);
        if (is_object($product) && method_exists($product, 'get_price')) {
            $unit_price = max(0.0, (float) $product->get_price());
            if ($unit_price > 0.0) {
                return $unit_price * $quantity;
            }
        }

        return 0.0;
    }

    /**
     * Prefer Woo's recorded cart-line subtotal when available so externally comped lines
     * stay non-discountable even if the underlying product catalog price remains positive.
     *
     * @param array<string, mixed> $cart_item
     */
    protected function get_recorded_cart_item_line_subtotal(array $cart_item): ?float
    {
        if (array_key_exists('line_subtotal', $cart_item)) {
            $subtotal = max(0.0, (float) $cart_item['line_subtotal']);
            if ($this->discounts_should_use_tax_inclusive_basis()) {
                $subtotal += max(0.0, (float) ($cart_item['line_subtotal_tax'] ?? 0.0));
            }

            return $subtotal;
        }

        if (array_key_exists('line_total', $cart_item)) {
            $total = max(0.0, (float) $cart_item['line_total']);
            if ($this->discounts_should_use_tax_inclusive_basis()) {
                $total += max(0.0, (float) ($cart_item['line_tax'] ?? 0.0));
            }

            return $total;
        }

        return null;
    }

    protected function discounts_should_use_tax_inclusive_basis(): bool
    {
        return function_exists('wc_prices_include_tax') && wc_prices_include_tax();
    }

    /**
     * @param array<string, mixed> $cart_item
     * @return mixed
     */
    protected function get_product_from_cart_item(array $cart_item)
    {
        if (isset($cart_item['data']) && is_object($cart_item['data'])) {
            return $cart_item['data'];
        }

        return null;
    }

    /**
     * @param mixed $cart
     * @param array<string, mixed> $data
     */
    protected function set_cart_item_adjustment_data($cart, string $cart_item_key, array $data): void
    {
        if (!isset($cart->cart_contents[$cart_item_key]) || !is_array($cart->cart_contents[$cart_item_key])) {
            return;
        }

        foreach ($data as $meta_key => $value) {
            $cart->cart_contents[$cart_item_key][$meta_key] = $value;
        }
    }

    /**
     * @param mixed $cart
     */
    protected function clear_cart_item_adjustment_data($cart, string $cart_item_key): void
    {
        if (!isset($cart->cart_contents[$cart_item_key]) || !is_array($cart->cart_contents[$cart_item_key])) {
            return;
        }

        unset(
            $cart->cart_contents[$cart_item_key][self::CART_ITEM_ORIGINAL_UNIT_PRICE_KEY],
            $cart->cart_contents[$cart_item_key][self::CART_ITEM_UNIT_DISCOUNT_KEY],
            $cart->cart_contents[$cart_item_key][self::CART_ITEM_LINE_DISCOUNT_KEY],
            $cart->cart_contents[$cart_item_key][self::CART_ITEM_ADJUSTMENT_LABELS_KEY]
        );
    }

    /**
     * @param mixed $cart
     */
    protected function is_supported_cart_context($cart): bool
    {
        if (!is_object($cart) || !method_exists($cart, 'get_cart')) {
            return false;
        }

        if (function_exists('is_admin') && function_exists('wp_doing_ajax') && is_admin() && !wp_doing_ajax()) {
            return false;
        }

        return true;
    }

    /**
     * @param mixed $cart
     */
    protected function cart_is_empty($cart): bool
    {
        return method_exists($cart, 'is_empty') && $cart->is_empty();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_applied_entries_from_session(): array
    {
        if (!function_exists('WC')) {
            return [];
        }

        $wc = WC();
        if (!is_object($wc) || !isset($wc->session) || !is_object($wc->session) || !method_exists($wc->session, 'get')) {
            return [];
        }

        $raw = $wc->session->get(self::SESSION_APPLIED_KEY, []);

        return array_values(array_filter(vms_discounts_array($raw), 'is_array'));
    }

    /**
     * @param array<int, array<string, mixed>> $entries
     */
    protected function store_applied_entries(array $entries): void
    {
        if (!function_exists('WC')) {
            return;
        }

        $wc = WC();
        if (!is_object($wc) || !isset($wc->session) || !is_object($wc->session) || !method_exists($wc->session, 'set')) {
            return;
        }

        if (empty($entries)) {
            $wc->session->set(self::SESSION_APPLIED_KEY, []);
            return;
        }

        $wc->session->set(self::SESSION_APPLIED_KEY, $entries);
    }

    public function clear_applied_entries(): void
    {
        $this->store_applied_entries([]);
    }
}
