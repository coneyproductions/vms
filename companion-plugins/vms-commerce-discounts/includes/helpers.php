<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('vms_discounts_bool')) {
    /**
     * Normalize booleans from form/meta options.
     *
     * @param mixed $value
     */
    function vms_discounts_bool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return ((int) $value) === 1;
        }

        $value = strtolower(trim((string) $value));

        return in_array($value, ['1', 'yes', 'true', 'on'], true);
    }
}

if (!function_exists('vms_discounts_array')) {
    /**
     * Ensure value is returned as an array.
     *
     * @param mixed $value
     * @return array<mixed>
     */
    function vms_discounts_array($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            return (array) $value;
        }

        return [];
    }
}

if (!function_exists('vms_discounts_parse_json_rules')) {
    /**
     * Decode raw JSON and return array on success.
     *
     * @param string $raw
     * @return array<mixed>
     */
    function vms_discounts_parse_json_rules(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        return $decoded;
    }
}

if (!function_exists('vms_discounts_generate_rule_id')) {
    /**
     * Generate an ID stable enough for admin editing and fee keys.
     */
    function vms_discounts_generate_rule_id(): string
    {
        if (function_exists('wp_generate_uuid4')) {
            return (string) wp_generate_uuid4();
        }

        return uniqid('rule_', true);
    }
}

if (!function_exists('vms_discounts_now')) {
    /**
     * Current timestamp in site timezone when possible.
     */
    function vms_discounts_now(): string
    {
        if (function_exists('current_time')) {
            return (string) current_time('mysql');
        }

        return gmdate('Y-m-d H:i:s');
    }
}

if (!function_exists('vms_discounts_price_decimals')) {
    /**
     * Current WooCommerce price precision, or 2 as a safe fallback.
     */
    function vms_discounts_price_decimals(): int
    {
        if (function_exists('wc_get_price_decimals')) {
            return max(0, (int) wc_get_price_decimals());
        }

        return 2;
    }
}

if (!function_exists('vms_discounts_amount_to_minor_units')) {
    /**
     * Convert a decimal amount into store minor units.
     *
     * @param float|int|string $amount
     */
    function vms_discounts_amount_to_minor_units($amount): int
    {
        $precision = pow(10, vms_discounts_price_decimals());

        return (int) round(((float) $amount) * $precision);
    }
}

if (!function_exists('vms_discounts_minor_units_to_amount')) {
    /**
     * Convert store minor units back to a decimal amount.
     */
    function vms_discounts_minor_units_to_amount(int $amount): float
    {
        $precision = pow(10, vms_discounts_price_decimals());
        if ($precision <= 0) {
            return 0.0;
        }

        return ((float) $amount) / $precision;
    }
}

if (!function_exists('vms_discounts_format_internal_price')) {
    /**
     * Format a unit price using WooCommerce internal rounding precision.
     *
     * @param float|int|string $amount
     */
    function vms_discounts_format_internal_price($amount): string
    {
        $amount = (float) $amount;
        if (function_exists('wc_format_decimal')) {
            $precision = function_exists('wc_get_rounding_precision')
                ? wc_get_rounding_precision()
                : max(4, vms_discounts_price_decimals() + 2);

            return (string) wc_format_decimal($amount, $precision, false);
        }

        return number_format($amount, max(4, vms_discounts_price_decimals() + 2), '.', '');
    }
}

if (!function_exists('vms_discounts_clean_string_list')) {
    /**
     * Normalize a mixed value into a trimmed list of unique strings.
     *
     * @param mixed $value
     * @return array<int, string>
     */
    function vms_discounts_clean_string_list($value): array
    {
        $items = [];

        foreach (vms_discounts_array($value) as $maybe_item) {
            $item = trim((string) $maybe_item);
            if ($item === '') {
                continue;
            }

            $items[$item] = $item;
        }

        return array_values($items);
    }
}

if (!function_exists('vms_discounts_is_square_gateway')) {
    /**
     * Determine whether the payment method is a Square-managed gateway.
     */
    function vms_discounts_is_square_gateway(string $gateway_id): bool
    {
        return in_array($gateway_id, ['square_credit_card', 'square_cash_app_pay'], true);
    }
}

if (!function_exists('vms_discounts_get_square_mode')) {
    /**
     * Current Square discount mode.
     */
    function vms_discounts_get_square_mode(): string
    {
        $mode = sanitize_key((string) get_option(VMS_Discounts_Rules::SQUARE_MODE_OPTION_KEY, VMS_Discounts_Rules::SQUARE_MODE_NATIVE));

        if (!in_array($mode, [VMS_Discounts_Rules::SQUARE_MODE_NATIVE, VMS_Discounts_Rules::SQUARE_MODE_COMPATIBILITY], true)) {
            return VMS_Discounts_Rules::SQUARE_MODE_NATIVE;
        }

        return $mode;
    }
}

if (!function_exists('vms_discounts_square_native_enabled')) {
    /**
     * Whether the native Square bridge is the active mode.
     */
    function vms_discounts_square_native_enabled(): bool
    {
        return vms_discounts_get_square_mode() === VMS_Discounts_Rules::SQUARE_MODE_NATIVE;
    }
}

if (!function_exists('vms_discounts_label_from_entry')) {
    /**
     * Resolve a public-facing label for a discount entry.
     *
     * @param array<string, mixed> $entry
     */
    function vms_discounts_label_from_entry(array $entry): string
    {
        $label = trim((string) ($entry['public_label'] ?? ''));
        if ($label === '') {
            $label = trim((string) ($entry['admin_label'] ?? ''));
        }
        if ($label === '') {
            $label = 'Discount';
        }

        return $label;
    }
}

if (!function_exists('vms_discounts_default_square_label')) {
    /**
     * Build a stable Square-visible label for one-or-many VMS discounts.
     *
     * @param array<int, array<string, mixed>> $entries
     */
    function vms_discounts_default_square_label(array $entries): string
    {
        $labels = [];

        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $label = vms_discounts_label_from_entry($entry);
            $labels[$label] = $label;
        }

        if (count($labels) === 1) {
            return (string) reset($labels);
        }

        if (!empty($labels)) {
            return 'VMS Discounts';
        }

        return 'Discount';
    }
}

if (!function_exists('vms_discounts_debug_log')) {
    /**
     * Debug logger used by the Square bridge and ledger flows.
     *
     * @param array<string, mixed> $context
     */
    function vms_discounts_debug_log(string $message, array $context = []): void
    {
        if (!vms_discounts_bool(get_option(VMS_Discounts_Rules::DEBUG_OPTION_KEY, 'no'))) {
            return;
        }

        if (!function_exists('wc_get_logger')) {
            return;
        }

        $payload = $message;
        if (!empty($context)) {
            $json = wp_json_encode($context);
            if (is_string($json) && $json !== '') {
                $payload .= ' ' . $json;
            }
        }

        wc_get_logger()->debug('[vms-commerce-discounts][square-bridge] ' . $payload, [
            'source' => 'vms-commerce-discounts',
        ]);
    }
}
