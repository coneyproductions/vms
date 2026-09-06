<?php

if (!defined('ABSPATH')) {
    exit;
}

class VMS_Discounts_Rules
{
    public const EVENT_RULES_META_KEY = '_vms_discounts_rules';
    public const GLOBAL_RULES_OPTION_KEY = 'vms_discounts_global_rules';
    public const GLOBAL_ENABLED_OPTION_KEY = 'vms_discounts_global_enabled';
    public const DEBUG_OPTION_KEY = 'vms_discounts_debug';
    public const ORDER_NOTE_OPTION_KEY = 'vms_discounts_order_note';
    public const SQUARE_MODE_OPTION_KEY = 'vms_discounts_square_mode';
    public const MIGRATED_MARKER_KEY = '_vms_discounts_migrated';
    public const SQUARE_MODE_NATIVE = 'native_square_discount';
    public const SQUARE_MODE_COMPATIBILITY = 'compatibility_reduced_price';

    /**
     * @var array<int, array<int, array<string, mixed>>>
     */
    protected $event_cache = [];

    /**
     * @var array<int, array<string, mixed>>|null
     */
    protected $global_cache = null;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_event_rules(int $event_id): array
    {
        if ($event_id <= 0) {
            return [];
        }

        if (isset($this->event_cache[$event_id])) {
            return $this->event_cache[$event_id];
        }

        $raw = get_post_meta($event_id, self::EVENT_RULES_META_KEY, true);
        $rules = $this->normalize_rules($raw);
        $this->event_cache[$event_id] = $rules;

        return $rules;
    }

    /**
     * @param array<int, array<string, mixed>>|string $rules
     */
    public function save_event_rules(int $event_id, $rules): void
    {
        if ($event_id <= 0) {
            return;
        }

        $normalized = $this->normalize_rules($rules);
        update_post_meta($event_id, self::EVENT_RULES_META_KEY, $normalized);
        $this->event_cache[$event_id] = $normalized;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_global_rules(): array
    {
        if ($this->global_cache !== null) {
            return $this->global_cache;
        }

        $raw = get_option(self::GLOBAL_RULES_OPTION_KEY, []);
        $this->global_cache = $this->normalize_rules($raw);

        return $this->global_cache;
    }

    /**
     * @param array<int, array<string, mixed>>|string $rules
     */
    public function save_global_rules($rules): void
    {
        $normalized = $this->normalize_rules($rules);
        update_option(self::GLOBAL_RULES_OPTION_KEY, $normalized, false);
        $this->global_cache = $normalized;
    }

    public function global_rules_enabled(): bool
    {
        return vms_discounts_bool(get_option(self::GLOBAL_ENABLED_OPTION_KEY, 'yes'));
    }

    public function debug_enabled(): bool
    {
        return vms_discounts_bool(get_option(self::DEBUG_OPTION_KEY, 'no'));
    }

    public function should_add_order_note(): bool
    {
        return vms_discounts_bool(get_option(self::ORDER_NOTE_OPTION_KEY, 'no'));
    }

    /**
     * @param array<int, array<string, mixed>> $event_rules
     */
    public function event_blocks_global(array $event_rules): bool
    {
        foreach ($event_rules as $rule) {
            if (!vms_discounts_bool($rule['enabled'] ?? false)) {
                continue;
            }
            if (($rule['scope'] ?? 'event_plus_global') === 'event_only') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, array<string, mixed>>|string|mixed $raw
     * @return array<int, array<string, mixed>>
     */
    public function normalize_rules($raw): array
    {
        if (is_string($raw)) {
            $raw = vms_discounts_parse_json_rules($raw);
        }

        $rules = vms_discounts_array($raw);
        $normalized = [];

        foreach ($rules as $maybe_rule) {
            if (!is_array($maybe_rule) && !is_object($maybe_rule)) {
                continue;
            }
            $rule = $this->normalize_rule(vms_discounts_array($maybe_rule));
            $normalized[] = $rule;
        }

        return $this->sort_rules($normalized);
    }

    /**
     * @param array<string, mixed> $rule
     * @return array<string, mixed>
     */
    public function normalize_rule(array $rule): array
    {
        $id = sanitize_text_field((string) ($rule['id'] ?? ''));
        if ($id === '') {
            $id = vms_discounts_generate_rule_id();
        }

        $stacking_mode = sanitize_key((string) ($rule['stacking_mode'] ?? 'stack'));
        if (!in_array($stacking_mode, ['stack', 'best_only'], true)) {
            $stacking_mode = 'stack';
        }

        $scope = sanitize_key((string) ($rule['scope'] ?? 'event_plus_global'));
        if (!in_array($scope, ['event_only', 'event_plus_global', 'global_only'], true)) {
            $scope = 'event_plus_global';
        }

        $qual_type = sanitize_key((string) ($rule['qual_type'] ?? 'ticket_qty'));
        if (!in_array($qual_type, ['ticket_qty', 'product_group_qty', 'order_product_qty', 'combo_qty'], true)) {
            $qual_type = 'ticket_qty';
        }

        $discount_type = sanitize_key((string) ($rule['discount_type'] ?? 'percent'));
        if (!in_array($discount_type, ['fixed_total', 'fixed_per_unit', 'percent'], true)) {
            $discount_type = 'percent';
        }

        $applies_to = sanitize_key((string) ($rule['applies_to'] ?? 'tickets'));
        if (!in_array($applies_to, ['tickets', 'entitlements', 'both', 'selected_products', 'all_products'], true)) {
            $applies_to = 'tickets';
        }

        $required_qty = max(1, (int) ($rule['required_qty'] ?? 1));
        $priority = (int) ($rule['priority'] ?? 100);
        $amount = max(0.0, (float) ($rule['amount'] ?? 0.0));
        if ($discount_type === 'percent') {
            $amount = min(100.0, $amount);
        }

        $max_apps = max(1, (int) ($rule['max_applications_per_order'] ?? 1));
        $cap = (float) ($rule['cap_amount'] ?? 0.0);
        if ($cap <= 0) {
            $cap = 0.0;
        }

        $product_ids = [];
        $raw_product_ids = vms_discounts_array($rule['product_ids'] ?? []);
        foreach ($raw_product_ids as $maybe_id) {
            $pid = (int) $maybe_id;
            if ($pid > 0) {
                $product_ids[] = $pid;
            }
        }
        $product_ids = array_values(array_unique($product_ids));

        return [
            'id' => $id,
            'enabled' => vms_discounts_bool($rule['enabled'] ?? true),
            'admin_label' => sanitize_text_field((string) ($rule['admin_label'] ?? '')),
            'public_label' => sanitize_text_field((string) ($rule['public_label'] ?? '')),
            'priority' => $priority,
            'stacking_mode' => $stacking_mode,
            'scope' => $scope,
            'qual_type' => $qual_type,
            'required_qty' => $required_qty,
            'product_ids' => $product_ids,
            'discount_type' => $discount_type,
            'amount' => $amount,
            'applies_to' => $applies_to,
            'max_applications_per_order' => $max_apps,
            'cap_amount' => $cap,
            'show_progress_hint' => vms_discounts_bool($rule['show_progress_hint'] ?? false),
            'progress_hint_template' => sanitize_text_field((string) ($rule['progress_hint_template'] ?? '')),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rules
     * @return array<int, array<string, mixed>>
     */
    public function sort_rules(array $rules): array
    {
        usort($rules, static function (array $a, array $b): int {
            $priority_cmp = ((int) ($a['priority'] ?? 100)) <=> ((int) ($b['priority'] ?? 100));
            if ($priority_cmp !== 0) {
                return $priority_cmp;
            }

            return strcmp((string) ($a['id'] ?? ''), (string) ($b['id'] ?? ''));
        });

        return $rules;
    }

    public function maybe_migrate_legacy(): void
    {
        if (vms_discounts_bool(get_option(self::MIGRATED_MARKER_KEY, 0))) {
            return;
        }

        $existing = get_option(self::GLOBAL_RULES_OPTION_KEY, []);
        if (!empty(vms_discounts_array($existing))) {
            update_option(self::MIGRATED_MARKER_KEY, 1, false);
            return;
        }

        $legacy_enabled = get_option('vmsx_td_enabled', null);
        if ($legacy_enabled === null) {
            update_option(self::MIGRATED_MARKER_KEY, 1, false);
            return;
        }

        $enabled = vms_discounts_bool($legacy_enabled);
        if (!$enabled) {
            update_option(self::MIGRATED_MARKER_KEY, 1, false);
            return;
        }

        $legacy_discount_type = sanitize_key((string) get_option('vmsx_td_discount_type', 'percent'));
        $new_discount_type = ($legacy_discount_type === 'fixed_per_person') ? 'fixed_per_unit' : 'percent';

        $amount = ($new_discount_type === 'percent')
            ? (float) get_option('vmsx_td_percent', 10)
            : (float) get_option('vmsx_td_fixed_amount', 2.5);

        $required_qty = max(1, (int) get_option('vmsx_td_threshold', 4));

        $migrated_rule = $this->normalize_rule([
            'id' => 'legacy-' . vms_discounts_generate_rule_id(),
            'enabled' => true,
            'admin_label' => 'Legacy migrated rule',
            'public_label' => 'Ticket Discount',
            'priority' => 100,
            'stacking_mode' => 'stack',
            'scope' => 'event_plus_global',
            'qual_type' => 'ticket_qty',
            'required_qty' => $required_qty,
            'product_ids' => [],
            'discount_type' => $new_discount_type,
            'amount' => $amount,
            'applies_to' => 'tickets',
            'max_applications_per_order' => 1,
            'cap_amount' => 0,
            'show_progress_hint' => false,
            'progress_hint_template' => '',
        ]);

        $this->save_global_rules([$migrated_rule]);
        update_option(self::GLOBAL_ENABLED_OPTION_KEY, 'yes', false);
        update_option(self::MIGRATED_MARKER_KEY, 1, false);
    }
}
