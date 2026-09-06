<?php

if (!defined('ABSPATH')) {
    exit;
}

class VMS_Discounts_UI
{
    /**
     * @param mixed $raw_rules
     * @return array<int, array<string, mixed>>
     */
    public static function rules_from_form($raw_rules): array
    {
        $rules = [];

        foreach (vms_discounts_array($raw_rules) as $maybe_rule) {
            if (!is_array($maybe_rule) && !is_object($maybe_rule)) {
                continue;
            }

            $row = vms_discounts_array($maybe_rule);

            $product_ids = self::parse_product_ids_csv((string) ($row['product_ids_csv'] ?? ''));
            if (empty($product_ids) && isset($row['product_ids'])) {
                foreach (vms_discounts_array($row['product_ids']) as $maybe_id) {
                    $pid = (int) $maybe_id;
                    if ($pid > 0) {
                        $product_ids[] = $pid;
                    }
                }
                $product_ids = array_values(array_unique($product_ids));
            }

            $rule = [
                'id' => sanitize_text_field((string) ($row['id'] ?? '')),
                'enabled' => vms_discounts_bool($row['enabled'] ?? false),
                'admin_label' => sanitize_text_field((string) ($row['admin_label'] ?? '')),
                'public_label' => sanitize_text_field((string) ($row['public_label'] ?? '')),
                'priority' => (int) ($row['priority'] ?? 100),
                'stacking_mode' => sanitize_key((string) ($row['stacking_mode'] ?? 'stack')),
                'scope' => sanitize_key((string) ($row['scope'] ?? 'event_plus_global')),
                'qual_type' => sanitize_key((string) ($row['qual_type'] ?? 'ticket_qty')),
                'required_qty' => max(1, (int) ($row['required_qty'] ?? 1)),
                'product_ids' => $product_ids,
                'discount_type' => sanitize_key((string) ($row['discount_type'] ?? 'percent')),
                'amount' => max(0.0, (float) ($row['amount'] ?? 0.0)),
                'applies_to' => sanitize_key((string) ($row['applies_to'] ?? 'tickets')),
                'max_applications_per_order' => max(1, (int) ($row['max_applications_per_order'] ?? 1)),
                'cap_amount' => max(0.0, (float) ($row['cap_amount'] ?? 0.0)),
                'show_progress_hint' => vms_discounts_bool($row['show_progress_hint'] ?? false),
                'progress_hint_template' => sanitize_text_field((string) ($row['progress_hint_template'] ?? '')),
            ];

            if (!self::rule_has_content($rule)) {
                continue;
            }

            $rules[] = $rule;
        }

        return array_values($rules);
    }

    /**
     * @param array<int, array<string, mixed>> $rules
     */
    public static function render_rules_builder(
        string $field_prefix,
        array $rules,
        string $advanced_json_name,
        string $advanced_toggle_name,
        string $advanced_heading = 'Advanced JSON'
    ): void {
        $prepared_rules = [];
        foreach ($rules as $rule) {
            if (!is_array($rule) && !is_object($rule)) {
                continue;
            }
            $prepared_rules[] = self::prepare_rule_for_form(vms_discounts_array($rule));
        }

        $raw_json = wp_json_encode($rules, JSON_PRETTY_PRINT);
        if (!is_string($raw_json)) {
            $raw_json = '[]';
        }

        ?>
        <div class="vms-discounts-rules-builder" data-name-prefix="<?php echo esc_attr($field_prefix); ?>">
            <div class="vms-discounts-intro">
                <p class="vms-discounts-intro-text">Build discounts using plain-language fields. You do not need to write JSON.</p>
                <button type="button" class="button button-primary" data-action="start-tour" data-tour-anchor="start-tour">Start Guided Walkthrough</button>
            </div>

            <details class="vms-discounts-glossary">
                <summary>Quick glossary (plain English)</summary>
                <ul>
                    <li><strong>Run order</strong>: Lower numbers run first. Example: 10 runs before 20.</li>
                    <li><strong>Use with other discounts</strong>: This discount can apply alongside others.</li>
                    <li><strong>Best discount only</strong>: If multiple qualify, only the single biggest one applies.</li>
                    <li><strong>Amount</strong>: This is <strong>%</strong> when style is Percent Off, and <strong>dollars</strong> for the dollar styles.</li>
                </ul>
            </details>

            <div class="vms-discounts-rules-toolbar">
                <button type="button" class="button button-secondary" data-action="add-rule" data-tour-anchor="add-rule">Add Discount Rule</button>
                <span class="vms-discounts-rules-toolbar-status" aria-live="polite"></span>
            </div>

            <div class="vms-discounts-rules-list">
                <?php foreach ($prepared_rules as $index => $rule) : ?>
                    <?php self::render_rule_row($field_prefix, (int) $index, $rule); ?>
                <?php endforeach; ?>
            </div>

            <template class="vms-discounts-rule-template">
                <?php self::render_rule_row($field_prefix, 0, self::default_rule(), true); ?>
            </template>

            <details class="vms-discounts-advanced" data-tour-anchor="advanced-json">
                <summary><?php echo esc_html($advanced_heading); ?></summary>
                <p class="description">Optional power-user mode. Keep this collapsed unless you specifically want raw JSON control.</p>
                <p>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($advanced_toggle_name); ?>" value="1" />
                        Use Advanced JSON override on save
                    </label>
                </p>
                <textarea name="<?php echo esc_attr($advanced_json_name); ?>" class="large-text code vms-discounts-json" rows="14"><?php echo esc_textarea($raw_json); ?></textarea>
                <p class="vms-discounts-advanced-actions">
                    <button type="button" class="button" data-action="refresh-advanced-json">Refresh JSON from form</button>
                    <button type="button" class="button" data-action="import-advanced-json">Load JSON into form</button>
                </p>
            </details>
        </div>
        <?php
    }

    /**
     * @param array<string, mixed> $rule
     */
    protected static function rule_has_content(array $rule): bool
    {
        return vms_discounts_bool($rule['enabled'] ?? false)
            || trim((string) ($rule['id'] ?? '')) !== ''
            || trim((string) ($rule['admin_label'] ?? '')) !== ''
            || trim((string) ($rule['public_label'] ?? '')) !== ''
            || ((float) ($rule['amount'] ?? 0.0)) > 0.0
            || !empty(vms_discounts_array($rule['product_ids'] ?? []))
            || trim((string) ($rule['progress_hint_template'] ?? '')) !== '';
    }

    /**
     * @return array<string, mixed>
     */
    protected static function default_rule(): array
    {
        return [
            'id' => '',
            'enabled' => false,
            'admin_label' => '',
            'public_label' => '',
            'priority' => 100,
            'stacking_mode' => 'stack',
            'scope' => 'event_plus_global',
            'qual_type' => 'ticket_qty',
            'required_qty' => 1,
            'product_ids' => [],
            'product_ids_csv' => '',
            'discount_type' => 'percent',
            'amount' => 0,
            'applies_to' => 'tickets',
            'max_applications_per_order' => 1,
            'cap_amount' => 0,
            'show_progress_hint' => false,
            'progress_hint_template' => '',
        ];
    }

    /**
     * @param array<string, mixed> $rule
     * @return array<string, mixed>
     */
    protected static function prepare_rule_for_form(array $rule): array
    {
        $rule = array_merge(self::default_rule(), $rule);

        $product_ids = [];
        foreach (vms_discounts_array($rule['product_ids']) as $maybe_id) {
            $pid = (int) $maybe_id;
            if ($pid > 0) {
                $product_ids[] = $pid;
            }
        }

        $product_ids = array_values(array_unique($product_ids));
        $rule['product_ids'] = $product_ids;
        $rule['product_ids_csv'] = implode(',', $product_ids);
        $rule['enabled'] = vms_discounts_bool($rule['enabled']);
        $rule['show_progress_hint'] = vms_discounts_bool($rule['show_progress_hint']);

        return $rule;
    }

    /**
     * @param array<string, mixed> $rule
     */
    protected static function render_rule_row(string $field_prefix, int $index, array $rule, bool $template = false): void
    {
        $row_class = 'vms-discounts-rule';
        if ($template) {
            $row_class .= ' vms-discounts-rule-is-template';
        }

        ?>
        <div class="<?php echo esc_attr($row_class); ?>" data-index="<?php echo esc_attr((string) $index); ?>">
            <div class="vms-discounts-rule-header">
                <div class="vms-discounts-rule-heading">
                    <div class="vms-discounts-rule-title-row">
                        <strong class="vms-discounts-rule-title">Discount Rule</strong>
                        <span class="vms-discounts-rule-status">Off</span>
                    </div>
                    <p class="vms-discounts-rule-summary">Turn this rule on, choose what unlocks it, then choose the savings.</p>
                </div>
                <div class="vms-discounts-rule-actions">
                    <button type="button" class="button button-small" data-action="toggle-rule-body" aria-expanded="true">Collapse</button>
                    <button type="button" class="button button-small" data-action="duplicate-rule">Duplicate</button>
                    <button type="button" class="button button-small" data-action="remove-rule">Remove</button>
                </div>
            </div>

            <input type="hidden" data-field="id" name="<?php echo esc_attr(self::field_name($field_prefix, $index, 'id')); ?>" value="<?php echo esc_attr((string) $rule['id']); ?>" />

            <div class="vms-discounts-rule-steps">
                <section class="vms-discounts-rule-step">
                    <div class="vms-discounts-step-header">
                        <span class="vms-discounts-step-kicker">Step 1</span>
                        <h4 class="vms-discounts-step-title">Turn it on and name it</h4>
                        <p class="vms-discounts-step-copy">Start with the team-facing and customer-facing names, then decide where this rule is allowed to run.</p>
                    </div>
                    <div class="vms-discounts-rule-grid vms-discounts-rule-grid--three-up">
                        <?php self::render_checkbox_field($field_prefix, $index, 'enabled', 'Turn this discount on', vms_discounts_bool($rule['enabled']), 'If off, the rule stays saved but will not apply.', 'enabled'); ?>
                        <?php self::render_text_field($field_prefix, $index, 'admin_label', 'Internal name (staff only)', (string) $rule['admin_label'], 'Only your team sees this in admin.', 'admin-label'); ?>
                        <?php self::render_text_field($field_prefix, $index, 'public_label', 'Name customers will see', (string) $rule['public_label'], 'Shown as the discount line in cart and checkout.', 'public-label'); ?>
                        <?php self::render_select_field($field_prefix, $index, 'scope', 'Where this rule can run', (string) $rule['scope'], [
                            'event_only' => 'This event only',
                            'event_plus_global' => 'This event + global defaults',
                            'global_only' => 'Global defaults only',
                        ], 'Choose whether this rule should be checked for the event, the global rules, or both.', 'scope'); ?>
                    </div>
                </section>

                <section class="vms-discounts-rule-step">
                    <div class="vms-discounts-step-header">
                        <span class="vms-discounts-step-kicker">Step 2</span>
                        <h4 class="vms-discounts-step-title">Choose what unlocks it</h4>
                        <p class="vms-discounts-step-copy">Tell VMS what the customer must add before this discount starts working.</p>
                    </div>
                    <div class="vms-discounts-rule-grid vms-discounts-rule-grid--two-up">
                        <?php self::render_select_field($field_prefix, $index, 'qual_type', 'What unlocks this discount?', (string) $rule['qual_type'], [
                            'ticket_qty' => 'Ticket count',
                            'product_group_qty' => 'Specific products count',
                            'order_product_qty' => 'Any paid product count',
                            'combo_qty' => 'Ticket + add-on combo',
                        ], 'Use ticket count for event tickets, specific products for named Woo products, any paid product count for whole-order product thresholds, or combo when tickets and add-ons must work together.', 'qualification'); ?>
                        <?php self::render_number_field($field_prefix, $index, 'required_qty', 'How many qualifying items are needed?', (string) $rule['required_qty'], '1', 'Example: enter 4 to unlock after 4 qualifying items.', 'required-qty'); ?>
                        <div class="vms-discounts-conditional" data-conditional="product-group">
                            <?php self::render_product_picker_field($field_prefix, $index, 'product_ids_csv', 'Specific products to count', (array) ($rule['product_ids'] ?? []), 'Only used with “Specific products count” and “Selected products only”. Search by product name or SKU, select one or more matching products, then add them to the rule.', 'product-ids'); ?>
                        </div>
                    </div>
                </section>

                <section class="vms-discounts-rule-step">
                    <div class="vms-discounts-step-header">
                        <span class="vms-discounts-step-kicker">Step 3</span>
                        <h4 class="vms-discounts-step-title">Define the savings</h4>
                        <p class="vms-discounts-step-copy">Choose the discount style, the amount, and what should receive the savings.</p>
                    </div>
                    <div class="vms-discounts-rule-grid vms-discounts-rule-grid--three-up">
                        <?php self::render_select_field($field_prefix, $index, 'discount_type', 'How should the discount be calculated?', (string) $rule['discount_type'], [
                            'fixed_total' => 'Dollar amount (once per unlock)',
                            'fixed_per_unit' => 'Dollar amount (per unlock)',
                            'percent' => 'Percent off',
                        ], 'Pick percent off or one of the dollar-based options.', 'discount-style'); ?>
                        <?php self::render_number_field($field_prefix, $index, 'amount', 'Discount amount', (string) $rule['amount'], '0.01', 'Percent style uses %. Dollar styles use dollars.', 'amount'); ?>
                        <?php self::render_select_field($field_prefix, $index, 'applies_to', 'What gets discounted?', (string) $rule['applies_to'], [
                            'tickets' => 'Tickets only',
                            'entitlements' => 'Add-ons / entitlements only',
                            'both' => 'Tickets + add-ons / entitlements',
                            'selected_products' => 'Selected products only',
                            'all_products' => 'All products in the order',
                        ], 'For product discounts, choose “Selected products only” to discount the products picked above, or “All products in the order” for a broader whole-order product discount.', 'applies-to'); ?>
                    </div>
                </section>

                <section class="vms-discounts-rule-step">
                    <div class="vms-discounts-step-header">
                        <span class="vms-discounts-step-kicker">Step 4</span>
                        <h4 class="vms-discounts-step-title">Set the order and guardrails</h4>
                        <p class="vms-discounts-step-copy">Control how this rule behaves when multiple discounts qualify and set safety limits so it does not over-apply.</p>
                    </div>
                    <div class="vms-discounts-rule-grid vms-discounts-rule-grid--two-up">
                        <?php self::render_number_field($field_prefix, $index, 'priority', 'Run order', (string) $rule['priority'], '1', 'Lower numbers run first. Example: 10 runs before 20.', 'priority'); ?>
                        <?php self::render_select_field($field_prefix, $index, 'stacking_mode', 'If multiple discounts qualify', (string) $rule['stacking_mode'], [
                            'stack' => 'Use with other discounts',
                            'best_only' => 'Apply best discount only',
                        ], 'Choose whether this rule can combine with other rules.', 'stacking'); ?>
                        <?php self::render_number_field($field_prefix, $index, 'max_applications_per_order', 'Maximum times this rule can apply per order', (string) $rule['max_applications_per_order'], '1', 'Prevents this rule from applying too many times in one order.', 'max-times'); ?>
                        <?php self::render_number_field($field_prefix, $index, 'cap_amount', 'Maximum total dollars this rule can discount', (string) $rule['cap_amount'], '0.01', 'Optional safety cap. Leave 0 for no extra cap.', 'cap-amount'); ?>
                    </div>
                </section>

                <section class="vms-discounts-rule-step">
                    <div class="vms-discounts-step-header">
                        <span class="vms-discounts-step-kicker">Step 5</span>
                        <h4 class="vms-discounts-step-title">Add customer messaging (optional)</h4>
                        <p class="vms-discounts-step-copy">Use this only if you want to show customers a progress hint before they qualify.</p>
                    </div>
                    <div class="vms-discounts-rule-grid vms-discounts-rule-grid--two-up">
                        <?php self::render_checkbox_field($field_prefix, $index, 'show_progress_hint', 'Show a customer progress message', vms_discounts_bool($rule['show_progress_hint']), 'Example: “Add 1 more ticket to unlock this discount.”', 'progress-toggle'); ?>
                        <div class="vms-discounts-conditional" data-conditional="progress-message">
                            <?php self::render_text_field($field_prefix, $index, 'progress_hint_template', 'Progress message text', (string) $rule['progress_hint_template'], 'Use plain customer-friendly language. Leave blank if you do not want a custom message.', 'progress-text'); ?>
                        </div>
                    </div>
                </section>
            </div>
        </div>
        <?php
    }

    protected static function render_text_field(
        string $prefix,
        int $index,
        string $field,
        string $label,
        string $value,
        string $help = '',
        string $tour_anchor = ''
    ): void {
        ?>
        <label class="vms-discounts-field"<?php echo ($tour_anchor !== '') ? ' data-tour-anchor="' . esc_attr($tour_anchor) . '"' : ''; ?>>
            <span><?php echo esc_html($label); ?></span>
            <input type="text" data-field="<?php echo esc_attr($field); ?>" name="<?php echo esc_attr(self::field_name($prefix, $index, $field)); ?>" value="<?php echo esc_attr($value); ?>" class="regular-text" />
            <?php if ($help !== '') : ?><small class="vms-discounts-help"><?php echo esc_html($help); ?></small><?php endif; ?>
        </label>
        <?php
    }

    protected static function render_number_field(
        string $prefix,
        int $index,
        string $field,
        string $label,
        string $value,
        string $step,
        string $help = '',
        string $tour_anchor = ''
    ): void {
        ?>
        <label class="vms-discounts-field"<?php echo ($tour_anchor !== '') ? ' data-tour-anchor="' . esc_attr($tour_anchor) . '"' : ''; ?>>
            <span><?php echo esc_html($label); ?></span>
            <input type="number" data-field="<?php echo esc_attr($field); ?>" name="<?php echo esc_attr(self::field_name($prefix, $index, $field)); ?>" value="<?php echo esc_attr($value); ?>" step="<?php echo esc_attr($step); ?>" class="small-text" />
            <?php if ($help !== '') : ?><small class="vms-discounts-help"><?php echo esc_html($help); ?></small><?php endif; ?>
        </label>
        <?php
    }

    /**
     * @param array<string, string> $options
     */
    protected static function render_select_field(
        string $prefix,
        int $index,
        string $field,
        string $label,
        string $value,
        array $options,
        string $help = '',
        string $tour_anchor = ''
    ): void {
        ?>
        <label class="vms-discounts-field"<?php echo ($tour_anchor !== '') ? ' data-tour-anchor="' . esc_attr($tour_anchor) . '"' : ''; ?>>
            <span><?php echo esc_html($label); ?></span>
            <select data-field="<?php echo esc_attr($field); ?>" name="<?php echo esc_attr(self::field_name($prefix, $index, $field)); ?>">
                <?php foreach ($options as $option_value => $option_label) : ?>
                    <option value="<?php echo esc_attr($option_value); ?>" <?php selected($value, $option_value); ?>><?php echo esc_html($option_label); ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($help !== '') : ?><small class="vms-discounts-help"><?php echo esc_html($help); ?></small><?php endif; ?>
        </label>
        <?php
    }

    protected static function render_checkbox_field(
        string $prefix,
        int $index,
        string $field,
        string $label,
        bool $checked_value,
        string $help = '',
        string $tour_anchor = ''
    ): void {
        ?>
        <label class="vms-discounts-field vms-discounts-field-checkbox"<?php echo ($tour_anchor !== '') ? ' data-tour-anchor="' . esc_attr($tour_anchor) . '"' : ''; ?>>
            <input type="checkbox" data-field="<?php echo esc_attr($field); ?>" name="<?php echo esc_attr(self::field_name($prefix, $index, $field)); ?>" value="1" <?php checked($checked_value); ?> />
            <span><?php echo esc_html($label); ?></span>
            <?php if ($help !== '') : ?><small class="vms-discounts-help"><?php echo esc_html($help); ?></small><?php endif; ?>
        </label>
        <?php
    }

    /**
     * @param array<int, int> $product_ids
     */
    protected static function render_product_picker_field(
        string $prefix,
        int $index,
        string $field,
        string $label,
        array $product_ids,
        string $help = '',
        string $tour_anchor = ''
    ): void {
        $items = self::get_product_picker_items($product_ids);
        $csv = implode(',', array_map('absint', $product_ids));
        ?>
        <div class="vms-discounts-field vms-discounts-field--product-picker"<?php echo ($tour_anchor !== '') ? ' data-tour-anchor="' . esc_attr($tour_anchor) . '"' : ''; ?>>
            <span><?php echo esc_html($label); ?></span>
            <input type="hidden" data-field="<?php echo esc_attr($field); ?>" name="<?php echo esc_attr(self::field_name($prefix, $index, $field)); ?>" value="<?php echo esc_attr($csv); ?>" />
            <div class="vms-discounts-product-picker">
                <input type="search" class="regular-text vms-discounts-product-search-input" placeholder="<?php echo esc_attr__('Search products by name or SKU', 'vms-commerce-discounts'); ?>" autocomplete="off" />
                <div class="vms-discounts-product-search-status" aria-live="polite"></div>
                <div class="vms-discounts-product-search-results" hidden></div>
                <div class="vms-discounts-product-selected">
                    <div class="vms-discounts-product-selected-label"><?php echo esc_html__('Selected products', 'vms-commerce-discounts'); ?></div>
                    <ul class="vms-discounts-product-selected-list">
                        <?php foreach ($items as $item) : ?>
                            <?php self::render_product_picker_item($item); ?>
                        <?php endforeach; ?>
                    </ul>
                    <p class="vms-discounts-product-empty"<?php echo !empty($items) ? ' hidden' : ''; ?>><?php echo esc_html__('No products selected yet.', 'vms-commerce-discounts'); ?></p>
                </div>
            </div>
            <?php if ($help !== '') : ?><small class="vms-discounts-help"><?php echo esc_html($help); ?></small><?php endif; ?>
        </div>
        <?php
    }

    /**
     * @param array<string, mixed> $item
     */
    protected static function render_product_picker_item(array $item): void
    {
        $product_id = absint($item['id'] ?? 0);
        if ($product_id <= 0) {
            return;
        }

        $label = sanitize_text_field((string) ($item['label'] ?? ''));
        $sku = sanitize_text_field((string) ($item['sku'] ?? ''));
        ?>
        <li class="vms-discounts-product-chip" data-product-id="<?php echo esc_attr((string) $product_id); ?>" data-product-label="<?php echo esc_attr($label); ?>" data-product-sku="<?php echo esc_attr($sku); ?>">
            <div class="vms-discounts-product-chip-copy">
                <strong><?php echo esc_html($label !== '' ? $label : ('Product #' . $product_id)); ?></strong>
                <span class="vms-discounts-product-chip-meta">
                    <?php if ($sku !== '') : ?>
                        <?php echo esc_html(sprintf(__('SKU %1$s | #%2$d', 'vms-commerce-discounts'), $sku, $product_id)); ?>
                    <?php else : ?>
                        <?php echo esc_html(sprintf(__('#%d', 'vms-commerce-discounts'), $product_id)); ?>
                    <?php endif; ?>
                </span>
            </div>
            <button type="button" class="button-link-delete" data-action="remove-product" data-product-id="<?php echo esc_attr((string) $product_id); ?>"><?php echo esc_html__('Remove', 'vms-commerce-discounts'); ?></button>
        </li>
        <?php
    }

    /**
     * @param array<int, int> $product_ids
     * @return array<int, array<string, mixed>>
     */
    protected static function get_product_picker_items(array $product_ids): array
    {
        $items = [];
        foreach ($product_ids as $product_id) {
            $item = self::get_product_picker_item(absint($product_id));
            if (!empty($item)) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    protected static function get_product_picker_item(int $product_id): array
    {
        $product_id = absint($product_id);
        if ($product_id <= 0) {
            return [];
        }

        $label = '';
        $sku = '';

        if (function_exists('wc_get_product')) {
            $product = wc_get_product($product_id);
            if ($product) {
                if (method_exists($product, 'get_name')) {
                    $label = (string) $product->get_name();
                }
                if (method_exists($product, 'get_sku')) {
                    $sku = (string) $product->get_sku();
                }
            }
        }

        if ($label === '') {
            $label = (string) get_the_title($product_id);
        }

        if ($label === '') {
            return [];
        }

        return [
            'id' => $product_id,
            'label' => $label,
            'sku' => $sku,
        ];
    }

    public static function ajax_search_products(): void
    {
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'forbidden'], 403);
        }

        check_ajax_referer('vms_discounts_search_products', 'nonce');

        $raw_ids = isset($_REQUEST['ids']) ? wp_unslash($_REQUEST['ids']) : '';
        $product_ids = [];
        if (is_array($raw_ids)) {
            foreach ($raw_ids as $maybe_id) {
                $pid = absint($maybe_id);
                if ($pid > 0) {
                    $product_ids[] = $pid;
                }
            }
        } elseif (is_string($raw_ids)) {
            $product_ids = self::parse_product_ids_csv($raw_ids);
        }
        $product_ids = array_values(array_unique(array_filter(array_map('absint', $product_ids))));

        if (!empty($product_ids)) {
            wp_send_json_success(['items' => self::get_product_picker_items($product_ids)]);
        }

        $query = isset($_REQUEST['q']) ? sanitize_text_field((string) wp_unslash($_REQUEST['q'])) : '';
        $query = trim($query);
        $query_length = function_exists('mb_strlen') ? mb_strlen($query) : strlen($query);
        if ($query === '' || $query_length < 2) {
            wp_send_json_success(['items' => []]);
        }

        $ids = [];
        $search_ids = get_posts([
            'post_type' => 'product',
            'post_status' => ['publish', 'future', 'draft', 'pending', 'private'],
            'posts_per_page' => 15,
            'fields' => 'ids',
            'no_found_rows' => true,
            's' => $query,
        ]);
        if (is_array($search_ids)) {
            $ids = array_merge($ids, array_map('absint', $search_ids));
        }

        $sku_ids = get_posts([
            'post_type' => 'product',
            'post_status' => ['publish', 'future', 'draft', 'pending', 'private'],
            'posts_per_page' => 15,
            'fields' => 'ids',
            'no_found_rows' => true,
            'meta_query' => [
                [
                    'key' => '_sku',
                    'value' => $query,
                    'compare' => 'LIKE',
                ],
            ],
        ]);
        if (is_array($sku_ids)) {
            $ids = array_merge($ids, array_map('absint', $sku_ids));
        }

        $ids = array_values(array_unique(array_filter($ids)));
        if (count($ids) > 15) {
            $ids = array_slice($ids, 0, 15);
        }

        wp_send_json_success(['items' => self::get_product_picker_items($ids)]);
    }

    protected static function field_name(string $prefix, int $index, string $field): string
    {
        return $prefix . '[' . $index . '][' . $field . ']';
    }

    /**
     * @return array<int, int>
     */
    protected static function parse_product_ids_csv(string $csv): array
    {
        $csv = trim($csv);
        if ($csv === '') {
            return [];
        }

        $parts = preg_split('/[\s,]+/', $csv);
        if (!is_array($parts)) {
            return [];
        }

        $ids = [];
        foreach ($parts as $part) {
            $pid = (int) trim((string) $part);
            if ($pid > 0) {
                $ids[] = $pid;
            }
        }

        return array_values(array_unique($ids));
    }
}
