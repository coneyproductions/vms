<?php

if (!defined('ABSPATH')) {
    exit;
}

class VMS_Discounts_Mapping
{
    /**
     * @param array<string, mixed> $cart_item
     * @return array<string, mixed>
     */
    public function classify_cart_item(array $cart_item): array
    {
        $product = $this->get_product_from_cart_item($cart_item);
        $product_id = $this->get_product_id($cart_item, $product);
        if ($product_id <= 0) {
            return [
                'type' => 'other',
                'event_id' => 0,
                'product_id' => 0,
                'product' => $product,
            ];
        }

        $is_ticket = $this->is_ticket_product($cart_item, $product, $product_id);
        if ($is_ticket) {
            $event_id = $this->get_event_id_for_ticket($cart_item, $product, $product_id);
            return [
                'type' => ($event_id > 0) ? 'ticket' : 'other',
                'event_id' => $event_id,
                'product_id' => $product_id,
                'product' => $product,
            ];
        }

        $is_entitlement = $this->is_entitlement_product($cart_item, $product, $product_id);
        if ($is_entitlement) {
            $event_id = $this->get_event_id_for_entitlement($cart_item, $product, $product_id);
            return [
                'type' => ($event_id > 0) ? 'entitlement' : 'other',
                'event_id' => $event_id,
                'product_id' => $product_id,
                'product' => $product,
            ];
        }

        return [
            'type' => 'other',
            'event_id' => 0,
            'product_id' => $product_id,
            'product' => $product,
        ];
    }

    /**
     * @param array<string, mixed> $cart_item
     * @param mixed $product
     */
    protected function is_ticket_product(array $cart_item, $product, int $product_id): bool
    {
        $event_id = (int) get_post_meta($product_id, '_tribe_wooticket_for_event', true);
        $is_ticket = ($event_id > 0);

        $is_ticket = (bool) apply_filters('vms_discounts_is_ticket_product', $is_ticket, $cart_item, $product);

        return $is_ticket;
    }

    /**
     * @param array<string, mixed> $cart_item
     * @param mixed $product
     */
    protected function get_event_id_for_ticket(array $cart_item, $product, int $product_id): int
    {
        $event_id = (int) get_post_meta($product_id, '_tribe_wooticket_for_event', true);

        if ($event_id <= 0 && isset($cart_item['_tribe_wooticket_for_event'])) {
            $event_id = (int) $cart_item['_tribe_wooticket_for_event'];
        }

        $event_id = (int) apply_filters('vms_discounts_get_event_id_for_cart_item', $event_id, $cart_item, $product);

        return max(0, $event_id);
    }

    /**
     * @param array<string, mixed> $cart_item
     * @param mixed $product
     */
    protected function is_entitlement_product(array $cart_item, $product, int $product_id): bool
    {
        $role = sanitize_key((string) get_post_meta($product_id, '_vms_product_role', true));
        $is_entitlement = in_array($role, ['entitlement', 'addon'], true);

        if (!$is_entitlement) {
            foreach ($this->entitlement_event_meta_keys() as $meta_key) {
                $value = (int) get_post_meta($product_id, $meta_key, true);
                if ($value > 0) {
                    $is_entitlement = true;
                    break;
                }
            }
        }

        $is_entitlement = (bool) apply_filters('vms_discounts_is_entitlement_product', $is_entitlement, $cart_item, $product);

        return $is_entitlement;
    }

    /**
     * @param array<string, mixed> $cart_item
     * @param mixed $product
     */
    protected function get_event_id_for_entitlement(array $cart_item, $product, int $product_id): int
    {
        $event_id = 0;

        foreach ($this->entitlement_event_meta_keys() as $meta_key) {
            $value = (int) get_post_meta($product_id, $meta_key, true);
            if ($value > 0) {
                $event_id = $value;
                break;
            }
        }

        foreach ($this->entitlement_event_meta_keys() as $meta_key) {
            if ($event_id > 0) {
                break;
            }
            if (!isset($cart_item[$meta_key])) {
                continue;
            }
            $value = (int) $cart_item[$meta_key];
            if ($value > 0) {
                $event_id = $value;
                break;
            }
        }

        $event_id = (int) apply_filters('vms_discounts_get_event_id_for_entitlement', $event_id, $cart_item, $product);

        return max(0, $event_id);
    }

    /**
     * @return array<int, string>
     */
    protected function entitlement_event_meta_keys(): array
    {
        return [
            '_vms_event_plan_id',
            '_vms_event_id',
            '_vms_parent_event_id',
            '_vms_entitlement_event_id',
            '_sr_event_plan_id',
            '_sr_event_id',
        ];
    }

    /**
     * @param array<string, mixed> $cart_item
     * @param mixed $product
     */
    protected function get_product_id(array $cart_item, $product): int
    {
        $product_id = (int) ($cart_item['product_id'] ?? 0);

        if ($product_id <= 0 && is_object($product) && method_exists($product, 'get_id')) {
            $product_id = (int) $product->get_id();
        }

        return max(0, $product_id);
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
}
