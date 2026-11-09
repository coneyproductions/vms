<?php
/**
 * Plugin Name: VMS Checkout Policies
 * Description: Requires acknowledgement of venue/event policies for event-scoped WooCommerce checkouts, including Square digital wallets and Checkout Blocks.
 * Version: 0.2.4-checkout.20261109.1
 * Author: Coney Productions
 * Text Domain: vmsx-checkout-policies
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 */

defined('ABSPATH') || exit;

define('VMSX_CP_VERSION', '0.2.4-checkout.20261109.1');
define('VMSX_CP_FILE', __FILE__);
define('VMSX_CP_PATH', plugin_dir_path(__FILE__));
define('VMSX_CP_URL', plugin_dir_url(__FILE__));

define('VMSX_CP_OPTION', 'vmsx_checkout_policies_settings');
define('VMSX_CP_SESSION_KEY', 'vmsx_checkout_policies_acceptance_v2');
define('VMSX_CP_NAMESPACE', 'vmsx-checkout-policies');

/**
 * Defaults are intentionally merged at read time so upgrades never overwrite
 * the venue's saved policy copy.
 */
function vmsx_cp_defaults(): array {
    return array(
        'enabled'             => 1,
        'summary_label'       => __('Event policies', 'vmsx-checkout-policies'),
        'checkbox_label'      => __('I have read and agree to the event policies.', 'vmsx-checkout-policies'),
        'policy_title'        => __('Event Policies', 'vmsx-checkout-policies'),
        'policy_body'         => '',
        'policy_version'      => '1.0',
        'open_by_default'     => 1,
        'store_ip_user_agent' => 0,
    );
}

function vmsx_cp_settings(): array {
    $saved = get_option(VMSX_CP_OPTION, array());
    return wp_parse_args(is_array($saved) ? $saved : array(), vmsx_cp_defaults());
}

function vmsx_cp_enabled(): bool {
    $settings = vmsx_cp_settings();
    return ! empty($settings['enabled']);
}

function vmsx_cp_policy_hash(): string {
    $settings = vmsx_cp_settings();
    $payload = array(
        'version' => (string) $settings['policy_version'],
        'title'   => (string) $settings['policy_title'],
        'label'   => (string) $settings['checkbox_label'],
        'body'    => (string) $settings['policy_body'],
    );
    return hash('sha256', wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

function vmsx_cp_has_bvm(): bool {
    return defined('BVMGR_VERSION') || function_exists('bvmgr_render_settings_page');
}

/**
 * Read product meta from a variation first, then its parent.
 */
function vmsx_cp_product_meta(int $product_id, string $key) {
    if ($product_id <= 0) {
        return '';
    }

    $value = get_post_meta($product_id, $key, true);
    if ($value !== '' && $value !== null) {
        return $value;
    }

    $parent_id = wp_get_post_parent_id($product_id);
    if ($parent_id > 0) {
        return get_post_meta($parent_id, $key, true);
    }

    return '';
}

/**
 * Detect both present-day BVM ticketing products and older TEC/VMS markers.
 */
function vmsx_cp_is_event_product(int $product_id): bool {
    if ($product_id <= 0) {
        return false;
    }

    foreach (array(
        '_vms_event_plan_id',
        '_vms_tec_event_id',
        '_vms_ticket_event_id',
        '_tribe_wooticket_for_event',
        '_vmsx_event_id',
        '_vmsx_ticket_id',
        '_vmsx_event_date',
        '_vmsx_event_start',
        '_vmsx_event_end',
        '_vmsx_event_venue',
        '_vmsx_event_location',
    ) as $key) {
        $value = vmsx_cp_product_meta($product_id, $key);
        if ($value !== '' && $value !== null && $value !== '0' && $value !== 0) {
            return true;
        }
    }

    $role = sanitize_key((string) vmsx_cp_product_meta($product_id, '_vms_product_role'));
    if (in_array($role, array('ga_ticket', 'ticket'), true)) {
        return true;
    }

    $legacy_type = sanitize_key((string) vmsx_cp_product_meta($product_id, '_vmsx_ticket_type'));
    if ($legacy_type !== '') {
        return true;
    }

    if (function_exists('wc_get_product')) {
        $product = wc_get_product($product_id);
        if ($product && in_array($product->get_type(), array('ticket', 'event'), true)) {
            return true;
        }
    }

    return false;
}

function vmsx_cp_cart_has_event_products(): bool {
    if (! vmsx_cp_enabled() || ! function_exists('WC') || ! WC()->cart) {
        return false;
    }

    foreach (WC()->cart->get_cart() as $item) {
        $product_id = ! empty($item['variation_id']) ? absint($item['variation_id']) : absint($item['product_id'] ?? 0);
        if (vmsx_cp_is_event_product($product_id)) {
            return true;
        }
    }

    return false;
}

/**
 * Scope acceptance to the event(s) represented by the current cart. Quantity
 * changes do not require re-acceptance; adding/removing an event does.
 */
function vmsx_cp_cart_scope_hash(): string {
    if (! function_exists('WC') || ! WC()->cart) {
        return '';
    }

    $tokens = array();
    foreach (WC()->cart->get_cart() as $item) {
        $product_id = ! empty($item['variation_id']) ? absint($item['variation_id']) : absint($item['product_id'] ?? 0);
        if (! vmsx_cp_is_event_product($product_id)) {
            continue;
        }

        $plan_id = absint(vmsx_cp_product_meta($product_id, '_vms_event_plan_id'));
        $event_id = absint(vmsx_cp_product_meta($product_id, '_vms_tec_event_id'));
        if (! $event_id) {
            $event_id = absint(vmsx_cp_product_meta($product_id, '_tribe_wooticket_for_event'));
        }
        if (! $event_id) {
            $event_id = absint(vmsx_cp_product_meta($product_id, '_vms_ticket_event_id'));
        }
        if (! $event_id) {
            $event_id = absint(vmsx_cp_product_meta($product_id, '_vmsx_event_id'));
        }

        if ($plan_id) {
            $tokens[] = 'plan:' . $plan_id;
        } elseif ($event_id) {
            $tokens[] = 'event:' . $event_id;
        } else {
            $tokens[] = 'product:' . $product_id;
        }
    }

    $tokens = array_values(array_unique($tokens));
    sort($tokens, SORT_STRING);

    return $tokens ? hash('sha256', implode('|', $tokens)) : '';
}

function vmsx_cp_get_acceptance(): array {
    if (! function_exists('WC') || ! WC()->session) {
        return array();
    }
    $value = WC()->session->get(VMSX_CP_SESSION_KEY, array());
    return is_array($value) ? $value : array();
}

function vmsx_cp_clear_acceptance(): void {
    if (function_exists('WC') && WC()->session) {
        WC()->session->__unset(VMSX_CP_SESSION_KEY);
    }
}

function vmsx_cp_set_acceptance(): void {
    if (! function_exists('WC') || ! WC()->session || ! vmsx_cp_cart_has_event_products()) {
        return;
    }

    WC()->session->set(VMSX_CP_SESSION_KEY, array(
        'accepted'    => 1,
        'policy_hash' => vmsx_cp_policy_hash(),
        'scope_hash'  => vmsx_cp_cart_scope_hash(),
        'accepted_at' => gmdate('Y-m-d H:i:s'),
    ));
}

function vmsx_cp_is_accepted(): bool {
    if (! vmsx_cp_cart_has_event_products()) {
        return true;
    }

    $acceptance = vmsx_cp_get_acceptance();
    $valid = ! empty($acceptance['accepted'])
        && hash_equals(vmsx_cp_policy_hash(), (string) ($acceptance['policy_hash'] ?? ''))
        && hash_equals(vmsx_cp_cart_scope_hash(), (string) ($acceptance['scope_hash'] ?? ''));

    if (! $valid && $acceptance) {
        vmsx_cp_clear_acceptance();
    }

    return $valid;
}

function vmsx_cp_policy_error_message(): string {
    return __('Please read and agree to the Event Policies before completing your purchase.', 'vmsx-checkout-policies');
}

/**
 * Square exposes a supported filter for the pages on which its digital wallets
 * render. Event products/cart must go through checkout so policy acceptance is
 * collected before Apple Pay/Google Pay can be used.
 */
function vmsx_cp_filter_square_wallet_pages(array $pages): array {
    if (! vmsx_cp_enabled()) {
        return $pages;
    }

    if (function_exists('is_cart') && is_cart() && vmsx_cp_cart_has_event_products()) {
        $pages = array_values(array_diff($pages, array('cart')));
    }

    if (function_exists('is_product') && is_product()) {
        $product_id = absint(get_queried_object_id());
        if ($product_id && vmsx_cp_is_event_product($product_id)) {
            $pages = array_values(array_diff($pages, array('product')));
        }
    }

    return $pages;
}
add_filter('wc_square_display_digital_wallet_on_pages', 'vmsx_cp_filter_square_wallet_pages', 20);

function vmsx_cp_body_classes(array $classes): array {
    if (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('order-pay')) {
        return $classes;
    }
    if (! vmsx_cp_cart_has_event_products()) {
        return $classes;
    }

    // Event carts must reach the real checkout before any express wallet can
    // start payment. This class also covers WooCommerce's block-based Cart,
    // whose express-payment registration bypasses Square's classic page filter.
    if (function_exists('is_cart') && is_cart()) {
        $classes[] = 'vmsx-policy-event-cart';
    }

    if (function_exists('is_checkout') && is_checkout()) {
        $classes[] = 'vmsx-policy-event-checkout';
        if (! vmsx_cp_is_accepted()) {
            $classes[] = 'vmsx-policy-locked';
        }
    }
    return $classes;
}
add_filter('body_class', 'vmsx_cp_body_classes', 20);

function vmsx_cp_render_policy_panel(): void {
    if (! vmsx_cp_cart_has_event_products()) {
        return;
    }

    $settings = vmsx_cp_settings();
    $accepted = vmsx_cp_is_accepted();
    ?>
    <section id="vmsx-checkout-policies" class="vmsx-checkout-policies <?php echo $accepted ? 'is-accepted' : 'is-required'; ?>" data-policy-hash="<?php echo esc_attr(vmsx_cp_policy_hash()); ?>" data-scope-hash="<?php echo esc_attr(vmsx_cp_cart_scope_hash()); ?>">
        <details class="vmsx-checkout-policies__details" <?php echo ! empty($settings['open_by_default']) ? 'open' : ''; ?>>
            <summary><?php echo esc_html((string) $settings['summary_label']); ?></summary>
            <div class="vmsx-checkout-policies__content">
                <?php if (! empty($settings['policy_title'])) : ?>
                    <strong class="vmsx-checkout-policies__title"><?php echo esc_html((string) $settings['policy_title']); ?></strong>
                <?php endif; ?>
                <div class="vmsx-checkout-policies__body"><?php echo wp_kses_post(wpautop((string) $settings['policy_body'])); ?></div>
                <?php if (! empty($settings['policy_version'])) : ?>
                    <small class="vmsx-checkout-policies__version"><?php echo esc_html(sprintf(__('Policy version %s', 'vmsx-checkout-policies'), (string) $settings['policy_version'])); ?></small>
                <?php endif; ?>
            </div>
        </details>
        <label class="vmsx-checkout-policies__accept">
            <input type="checkbox" id="vmsx-checkout-policies-accept" value="1" <?php checked($accepted); ?> />
            <span><?php echo esc_html((string) $settings['checkbox_label']); ?></span>
        </label>
        <div id="vmsx-checkout-policies-status" class="vmsx-checkout-policies__status" role="status" aria-live="polite"></div>
    </section>
    <?php
}
// Square checkout wallets render at priority 15. Policies must appear first.
add_action('woocommerce_before_checkout_form', 'vmsx_cp_render_policy_panel', 8);

/**
 * Mirror the browser checkbox into the standard checkout form. Square builds
 * its own AJAX payload and intentionally will not contain this field; that path
 * relies on the authoritative Woo session state instead.
 */
function vmsx_cp_render_classic_hidden_field(): void {
    if (! vmsx_cp_cart_has_event_products()) {
        return;
    }
    echo '<input type="hidden" id="vmsx-checkout-policies-form-accept" name="vmsx_checkout_policies_accept" value="' . (vmsx_cp_is_accepted() ? '1' : '0') . '" />';
}
add_action('woocommerce_checkout_before_customer_details', 'vmsx_cp_render_classic_hidden_field', 1);

function vmsx_cp_sync_classic_post_state(): void {
    if (! vmsx_cp_cart_has_event_products()) {
        return;
    }

    // Presence means this is the normal checkout form. Square's wallet payload
    // omits the custom field, so absence must NOT revoke a valid session grant.
    if (isset($_POST['vmsx_checkout_policies_accept'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $accepted = wc_string_to_bool(wc_clean(wp_unslash($_POST['vmsx_checkout_policies_accept']))); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ($accepted) {
            vmsx_cp_set_acceptance();
        } else {
            vmsx_cp_clear_acceptance();
        }
    }
}

function vmsx_cp_validate_classic_checkout(): void {
    if (! vmsx_cp_cart_has_event_products()) {
        return;
    }

    vmsx_cp_sync_classic_post_state();
    if (! vmsx_cp_is_accepted()) {
        wc_add_notice(vmsx_cp_policy_error_message(), 'error');
    }
}
add_action('woocommerce_checkout_process', 'vmsx_cp_validate_classic_checkout', 5);

function vmsx_cp_detect_checkout_type(): string {
    if (! empty($_POST['wc-square-digital-wallet-type'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
        return 'square_digital_wallet';
    }
    if (defined('REST_REQUEST') && REST_REQUEST) {
        return 'store_api_checkout';
    }
    return 'classic_checkout';
}

function vmsx_cp_apply_order_audit(WC_Order $order, string $checkout_type): void {
    if (! vmsx_cp_cart_has_event_products() || ! vmsx_cp_is_accepted()) {
        return;
    }

    $settings = vmsx_cp_settings();
    $acceptance = vmsx_cp_get_acceptance();

    $order->update_meta_data('_vmsx_checkout_policies_accepted', 'yes');
    $order->update_meta_data('_vmsx_checkout_policies_accepted_at', (string) ($acceptance['accepted_at'] ?? gmdate('Y-m-d H:i:s')));
    $order->update_meta_data('_vmsx_checkout_policies_version', (string) $settings['policy_version']);
    $order->update_meta_data('_vmsx_checkout_policies_title', (string) $settings['policy_title']);
    $order->update_meta_data('_vmsx_checkout_policies_checkbox_label', (string) $settings['checkbox_label']);
    $order->update_meta_data('_vmsx_checkout_policies_hash', vmsx_cp_policy_hash());
    $order->update_meta_data('_vmsx_checkout_policies_scope_hash', vmsx_cp_cart_scope_hash());
    $order->update_meta_data('_vmsx_checkout_policies_checkout_type', sanitize_key($checkout_type));
    $order->update_meta_data('_vmsx_checkout_policies_plugin_version', VMSX_CP_VERSION);

    if (! empty($settings['store_ip_user_agent'])) {
        $ip = class_exists('WC_Geolocation') ? WC_Geolocation::get_ip_address() : '';
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
        if ($ip !== '') {
            $order->update_meta_data('_vmsx_checkout_policies_ip', $ip);
        }
        if ($ua !== '') {
            $order->update_meta_data('_vmsx_checkout_policies_user_agent', $ua);
        }
    }
}

function vmsx_cp_store_classic_order_audit(WC_Order $order, array $data): void {
    vmsx_cp_apply_order_audit($order, vmsx_cp_detect_checkout_type());
}
add_action('woocommerce_checkout_create_order', 'vmsx_cp_store_classic_order_audit', 20, 2);

function vmsx_cp_clear_after_checkout($order_id = 0): void {
    vmsx_cp_clear_acceptance();
}
add_action('woocommerce_checkout_order_processed', 'vmsx_cp_clear_after_checkout', 50, 1);
add_action('woocommerce_store_api_checkout_order_processed', 'vmsx_cp_clear_after_checkout', 50, 1);
add_action('woocommerce_cart_emptied', 'vmsx_cp_clear_acceptance');

/** AJAX acceptance bridge *****************************************************/
function vmsx_cp_ajax_toggle(): void {
    check_ajax_referer('vmsx_checkout_policy_toggle', 'nonce');

    $intent = isset($_POST['intent']) ? sanitize_key(wp_unslash($_POST['intent'])) : 'set';
    if ($intent === 'status') {
        wp_send_json_success(array(
            'event_cart'  => vmsx_cp_cart_has_event_products(),
            'accepted'    => vmsx_cp_cart_has_event_products() ? vmsx_cp_is_accepted() : false,
            'policy_hash' => vmsx_cp_policy_hash(),
            'scope_hash'  => vmsx_cp_cart_scope_hash(),
        ));
    }

    $accepted = isset($_POST['accepted']) && wc_string_to_bool(wc_clean(wp_unslash($_POST['accepted'])));
    if ($accepted) {
        if (! vmsx_cp_cart_has_event_products()) {
            vmsx_cp_clear_acceptance();
            wp_send_json_error(array('message' => __('No event-scoped items are currently in the cart.', 'vmsx-checkout-policies')), 409);
        }
        vmsx_cp_set_acceptance();
    } else {
        vmsx_cp_clear_acceptance();
    }

    wp_send_json_success(array(
        'event_cart'  => vmsx_cp_cart_has_event_products(),
        'accepted'    => vmsx_cp_cart_has_event_products() ? vmsx_cp_is_accepted() : false,
        'policy_hash' => vmsx_cp_policy_hash(),
        'scope_hash'  => vmsx_cp_cart_scope_hash(),
    ));
}
add_action('wp_ajax_vmsx_checkout_policy_toggle', 'vmsx_cp_ajax_toggle');
add_action('wp_ajax_nopriv_vmsx_checkout_policy_toggle', 'vmsx_cp_ajax_toggle');

/** Checkout Block / Store API ************************************************/
function vmsx_cp_checkout_schema_identifier(): string {
    if (class_exists('Automattic\\WooCommerce\\StoreApi\\Schemas\\V1\\CheckoutSchema')) {
        return \Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema::IDENTIFIER;
    }
    if (class_exists('Automattic\\WooCommerce\\StoreApi\\Schemas\\CheckoutSchema')) {
        return \Automattic\WooCommerce\StoreApi\Schemas\CheckoutSchema::IDENTIFIER;
    }
    return 'checkout';
}

function vmsx_cp_register_store_api_extension(): void {
    if (! function_exists('woocommerce_store_api_register_endpoint_data')) {
        return;
    }

    woocommerce_store_api_register_endpoint_data(array(
        'endpoint'        => vmsx_cp_checkout_schema_identifier(),
        'namespace'       => VMSX_CP_NAMESPACE,
        'data_callback'   => function(): array {
            return array(
                'event_cart'  => vmsx_cp_cart_has_event_products(),
                'accepted'    => vmsx_cp_cart_has_event_products() ? vmsx_cp_is_accepted() : false,
                'policy_hash' => vmsx_cp_policy_hash(),
                'scope_hash'  => vmsx_cp_cart_scope_hash(),
            );
        },
        'schema_callback' => function(): array {
            return array(
                'event_cart' => array(
                    'description' => __('Whether this checkout contains event-scoped products.', 'vmsx-checkout-policies'),
                    'type'        => 'boolean',
                    'context'     => array('view', 'edit'),
                    'readonly'    => true,
                    'optional'    => true,
                ),
                'accepted' => array(
                    'description' => __('Whether the shopper accepted the current event policies.', 'vmsx-checkout-policies'),
                    'type'        => 'boolean',
                    'context'     => array('view', 'edit'),
                    'readonly'    => false,
                    'optional'    => true,
                ),
                'policy_hash' => array(
                    'type'     => 'string',
                    'context'  => array('view', 'edit'),
                    'readonly' => false,
                    'optional' => true,
                ),
                'scope_hash' => array(
                    'type'     => 'string',
                    'context'  => array('view', 'edit'),
                    'readonly' => false,
                    'optional' => true,
                ),
            );
        },
        'schema_type'     => ARRAY_A,
    ));
}
add_action('woocommerce_blocks_loaded', 'vmsx_cp_register_store_api_extension');

function vmsx_cp_store_api_update_order_from_request(WC_Order $order, WP_REST_Request $request): void {
    if (! vmsx_cp_cart_has_event_products()) {
        return;
    }

    $extensions = $request->get_param('extensions');
    $payload = is_array($extensions) && isset($extensions[VMSX_CP_NAMESPACE]) && is_array($extensions[VMSX_CP_NAMESPACE])
        ? $extensions[VMSX_CP_NAMESPACE]
        : array();

    $accepted = ! empty($payload['accepted']);
    $policy_hash = sanitize_text_field((string) ($payload['policy_hash'] ?? ''));
    $scope_hash = sanitize_text_field((string) ($payload['scope_hash'] ?? ''));

    if ($accepted && hash_equals(vmsx_cp_policy_hash(), $policy_hash) && hash_equals(vmsx_cp_cart_scope_hash(), $scope_hash)) {
        vmsx_cp_set_acceptance();
    } else {
        vmsx_cp_clear_acceptance();
    }

    if (! vmsx_cp_is_accepted()) {
        $message = vmsx_cp_policy_error_message();
        if (class_exists('Automattic\\WooCommerce\\StoreApi\\Exceptions\\RouteException')) {
            throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException('vmsx_checkout_policies_required', $message, 400);
        }
        throw new Exception($message);
    }

    vmsx_cp_apply_order_audit($order, 'store_api_checkout');
}
add_action('woocommerce_store_api_checkout_update_order_from_request', 'vmsx_cp_store_api_update_order_from_request', 10, 2);

/** Existing-order policy acknowledgement ************************************/
function vmsx_cp_order_requires_policy(WC_Order $order): bool {
    if (! vmsx_cp_enabled()) {
        return false;
    }
    foreach ($order->get_items('line_item') as $item) {
        if (vmsx_cp_is_event_product((int) ($item->get_variation_id() ?: $item->get_product_id()))) {
            return true;
        }
    }
    return false;
}

function vmsx_cp_order_policy_scope(WC_Order $order): string {
    $items = array();
    foreach ($order->get_items('line_item') as $id => $item) {
        $items[(int) $id] = array((int) $item->get_product_id(), (int) $item->get_variation_id(), (int) $item->get_quantity());
    }
    ksort($items);
    return hash('sha256', wp_json_encode(array($order->get_id(), $order->get_customer_id(), $order->get_currency(), $order->get_total(), $items)));
}

function vmsx_cp_order_pay_access(WC_Order $order): bool {
    $key = isset($_GET['key']) && is_string($_GET['key']) ? wc_clean(wp_unslash($_GET['key'])) : '';
    return $order->needs_payment() && current_user_can('pay_for_order', $order->get_id())
        && $key !== '' && hash_equals($order->get_order_key(), $key);
}

function vmsx_cp_current_pay_order() {
    $order = wc_get_order(absint(get_query_var('order-pay')));
    return $order instanceof WC_Order && vmsx_cp_order_pay_access($order) ? $order : false;
}

function vmsx_cp_render_order_pay_policy(): void {
    $order = vmsx_cp_current_pay_order();
    if (! $order || ! vmsx_cp_order_requires_policy($order)) {
        return;
    }
    $settings = vmsx_cp_settings();
    $accepted = $order->get_meta('_vmsx_checkout_policies_accepted') === 'yes'
        && hash_equals(vmsx_cp_policy_hash(), (string) $order->get_meta('_vmsx_checkout_policies_hash'))
        && hash_equals(vmsx_cp_order_policy_scope($order), (string) $order->get_meta('_vmsx_order_pay_policy_scope_hash'));
    ?>
    <section class="vmsx-checkout-policies vmsx-order-pay-policies" aria-labelledby="vmsx-order-policy-title">
        <h3 id="vmsx-order-policy-title"><?php echo esc_html((string) $settings['policy_title']); ?></h3>
        <p id="vmsx-order-policy-help"><?php esc_html_e('Please read and agree to the Event Policies before paying for this order.', 'vmsx-checkout-policies'); ?></p>
        <details class="vmsx-checkout-policies__details" <?php echo ! empty($settings['open_by_default']) ? 'open' : ''; ?>>
            <summary><?php echo esc_html((string) $settings['summary_label']); ?></summary>
            <div class="vmsx-checkout-policies__content"><?php echo wp_kses_post(wpautop((string) $settings['policy_body'])); ?></div>
        </details>
        <label class="vmsx-checkout-policies__accept" for="vmsx-order-pay-policy-accept">
            <input type="checkbox" id="vmsx-order-pay-policy-accept" name="vmsx_order_pay_policy_accept" value="1" required aria-required="true" aria-describedby="vmsx-order-policy-help" <?php checked($accepted); ?> />
            <span><?php echo esc_html((string) $settings['checkbox_label']); ?></span>
        </label>
        <input type="hidden" name="vmsx_order_pay_policy_hash" value="<?php echo esc_attr(vmsx_cp_policy_hash()); ?>" />
        <input type="hidden" name="vmsx_order_pay_policy_scope" value="<?php echo esc_attr(vmsx_cp_order_policy_scope($order)); ?>" />
        <?php wp_nonce_field('vmsx_order_pay_policy_' . $order->get_id(), 'vmsx_order_pay_policy_nonce'); ?>
    </section>
    <?php
}
add_action('woocommerce_pay_order_before_submit', 'vmsx_cp_render_order_pay_policy', 5);

function vmsx_cp_validate_order_pay_policy(WC_Order $order): void {
    if (! vmsx_cp_order_requires_policy($order)) {
        return;
    }
    // Woo has already checked its payment nonce, key and payable status. Retain
    // those checks and explicitly require the native account permission here.
    if (! vmsx_cp_order_pay_access($order)) {
        wc_add_notice(__('Please sign in to the account that owns this order before paying.', 'vmsx-checkout-policies'), 'error');
        return;
    }
    $fields = array();
    foreach (array('accept', 'hash', 'scope', 'nonce') as $field) {
        $name = 'vmsx_order_pay_policy_' . $field;
        $fields[$field] = isset($_POST[$name]) && is_string($_POST[$name]) ? wc_clean(wp_unslash($_POST[$name])) : '';
    }
    if (! wp_verify_nonce($fields['nonce'], 'vmsx_order_pay_policy_' . $order->get_id())
        || ! hash_equals(vmsx_cp_policy_hash(), $fields['hash'])
        || ! hash_equals(vmsx_cp_order_policy_scope($order), $fields['scope'])) {
        wc_add_notice(__('The order or Event Policies have changed. Please reload this payment page, then read and agree to the policies again.', 'vmsx-checkout-policies'), 'error');
        return;
    }
    if ($fields['accept'] !== '1') {
        wc_add_notice(vmsx_cp_policy_error_message(), 'error');
        return;
    }
    // Record acceptance independently of cart/session state, before the gateway
    // can authorize payment. Failed payment retries retain the same audit.
    $order->update_meta_data('_vmsx_checkout_policies_accepted', 'yes');
    $order->update_meta_data('_vmsx_checkout_policies_accepted_at', gmdate('Y-m-d H:i:s'));
    $settings = vmsx_cp_settings();
    $order->update_meta_data('_vmsx_checkout_policies_version', (string) $settings['policy_version']);
    foreach (array('title' => 'policy_title', 'checkbox_label' => 'checkbox_label') as $meta => $setting) {
        $order->update_meta_data('_vmsx_checkout_policies_' . $meta, (string) $settings[$setting]);
    }
    $order->update_meta_data('_vmsx_checkout_policies_hash', vmsx_cp_policy_hash());
    $order->update_meta_data('_vmsx_order_pay_policy_scope_hash', vmsx_cp_order_policy_scope($order));
    $order->update_meta_data('_vmsx_checkout_policies_checkout_type', 'order_pay');
    $order->update_meta_data('_vmsx_checkout_policies_plugin_version', VMSX_CP_VERSION);
    $order->update_meta_data('_vmsx_order_pay_policy_user_id', get_current_user_id());
    $order->save();
}
add_action('woocommerce_before_pay_action', 'vmsx_cp_validate_order_pay_policy', 1);

/** Front-end assets **********************************************************/
function vmsx_cp_enqueue_assets(): void {
    // Existing-order payments are authorized and validated against that order.
    // Cart policy controls belong only to cart/new-checkout flows; order-pay
    // has no cart acceptance panel and may be opened with an unrelated cart.
    if (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('order-pay')) {
        $order = vmsx_cp_current_pay_order();
        if ($order && vmsx_cp_order_requires_policy($order)) {
            wp_enqueue_style('vmsx-checkout-policies', VMSX_CP_URL . 'assets/checkout-policies.css', array(), VMSX_CP_VERSION);
        }
        return;
    }

    if (! vmsx_cp_cart_has_event_products()) {
        return;
    }

    $is_cart_page = function_exists('is_cart') && is_cart();
    $is_checkout_page = function_exists('is_checkout') && is_checkout()
        && (! function_exists('is_wc_endpoint_url') || ! is_wc_endpoint_url('order-received'));

    if (! $is_cart_page && ! $is_checkout_page) {
        return;
    }

    wp_enqueue_style(
        'vmsx-checkout-policies',
        VMSX_CP_URL . 'assets/checkout-policies.css',
        array(),
        VMSX_CP_VERSION
    );

    // The Cart Block has its own express-payment registration path that does
    // not honor Square's classic wc_square_display_digital_wallet_on_pages
    // filter. Load a tiny event-cart guard so late React renders stay hidden.
    if ($is_cart_page) {
        wp_enqueue_script(
            'vmsx-checkout-policies-cart-guard',
            VMSX_CP_URL . 'assets/cart-wallet-guard.js',
            array(),
            VMSX_CP_VERSION,
            true
        );
    }

    if (! $is_checkout_page) {
        return;
    }

    wp_enqueue_script(
        'vmsx-checkout-policies',
        VMSX_CP_URL . 'assets/checkout-policies.js',
        array('wp-data'),
        VMSX_CP_VERSION,
        true
    );

    $settings = vmsx_cp_settings();
    wp_localize_script('vmsx-checkout-policies', 'VMSXCheckoutPolicies', array(
        'ajaxUrl'       => admin_url('admin-ajax.php'),
        'nonce'         => wp_create_nonce('vmsx_checkout_policy_toggle'),
        'namespace'     => VMSX_CP_NAMESPACE,
        'accepted'      => vmsx_cp_is_accepted(),
        'policyHash'    => vmsx_cp_policy_hash(),
        'scopeHash'     => vmsx_cp_cart_scope_hash(),
        'summaryLabel'  => (string) $settings['summary_label'],
        'checkboxLabel' => (string) $settings['checkbox_label'],
        'policyTitle'   => (string) $settings['policy_title'],
        'policyBody'    => (string) $settings['policy_body'],
        'policyVersion' => (string) $settings['policy_version'],
        'openByDefault' => ! empty($settings['open_by_default']),
        'errorMessage'  => vmsx_cp_policy_error_message(),
        'requiredMessage' => __('Please accept the Event Policies above before placing your order.', 'vmsx-checkout-policies'),
        'policyPrerequisiteMessage' => __('Please agree to the Event Policies before placing your order. Required checkout details and payment must also be complete.', 'vmsx-checkout-policies'),
        'savingPoliciesMessage' => __('Saving Event Policies acceptance. Please wait.', 'vmsx-checkout-policies'),
        'reviewPoliciesMessage' => __('Review and accept Event Policies', 'vmsx-checkout-policies'),
        'processingTitle' => __('Processing your order…', 'vmsx-checkout-policies'),
        'processingMessage' => __('Please do not refresh, go back, or submit the order again. We will continue automatically when processing is complete.', 'vmsx-checkout-policies'),
    ));
}
add_action('wp_enqueue_scripts', 'vmsx_cp_enqueue_assets', 30);

/** Settings ******************************************************************/
function vmsx_cp_sanitize_settings($input): array {
    $defaults = vmsx_cp_defaults();
    $input = is_array($input) ? $input : array();

    return array(
        'enabled'             => ! empty($input['enabled']) ? 1 : 0,
        'summary_label'       => sanitize_text_field((string) ($input['summary_label'] ?? $defaults['summary_label'])),
        'checkbox_label'      => sanitize_text_field((string) ($input['checkbox_label'] ?? $defaults['checkbox_label'])),
        'policy_title'        => sanitize_text_field((string) ($input['policy_title'] ?? $defaults['policy_title'])),
        'policy_body'         => wp_kses_post((string) ($input['policy_body'] ?? '')),
        'policy_version'      => sanitize_text_field((string) ($input['policy_version'] ?? $defaults['policy_version'])),
        'open_by_default'     => ! empty($input['open_by_default']) ? 1 : 0,
        'store_ip_user_agent' => ! empty($input['store_ip_user_agent']) ? 1 : 0,
    );
}

function vmsx_cp_register_settings(): void {
    register_setting('vmsx_checkout_policies', VMSX_CP_OPTION, array(
        'type'              => 'array',
        'sanitize_callback' => 'vmsx_cp_sanitize_settings',
        'default'           => vmsx_cp_defaults(),
    ));
}
add_action('admin_init', 'vmsx_cp_register_settings');

function vmsx_cp_find_bvm_parent_slug(): string {
    global $menu;
    if (! is_array($menu)) {
        return '';
    }
    foreach ($menu as $entry) {
        $slug = isset($entry[2]) ? (string) $entry[2] : '';
        $label = isset($entry[0]) ? wp_strip_all_tags((string) $entry[0]) : '';
        if ($slug && (strpos($slug, 'bvmgr') !== false || strpos($slug, 'backstage-venue-manager') !== false || stripos($label, 'Backstage') !== false)) {
            return $slug;
        }
    }
    return '';
}

function vmsx_cp_admin_menu(): void {
    if (! current_user_can('manage_woocommerce')) {
        return;
    }

    $parent = '';
    if (vmsx_cp_has_bvm()) {
        $parent = vmsx_cp_find_bvm_parent_slug();
    } elseif (class_exists('WooCommerce')) {
        $parent = 'woocommerce';
    }

    // With BVM active, never create a misleading WooCommerce fallback. If the
    // BVM parent cannot be resolved, keep the page hidden but reachable through
    // the plugin-row Settings link.
    add_submenu_page(
        $parent !== '' ? $parent : null,
        __('Checkout Policies', 'vmsx-checkout-policies'),
        __('Checkout Policies', 'vmsx-checkout-policies'),
        'manage_woocommerce',
        'vmsx-checkout-policies',
        'vmsx_cp_render_settings_page'
    );
}
add_action('admin_menu', 'vmsx_cp_admin_menu', 999);

function vmsx_cp_plugin_action_links(array $links): array {
    $url = admin_url('admin.php?page=vmsx-checkout-policies');
    array_unshift($links, '<a href="' . esc_url($url) . '">' . esc_html__('Settings', 'vmsx-checkout-policies') . '</a>');
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(VMSX_CP_FILE), 'vmsx_cp_plugin_action_links');

function vmsx_cp_render_settings_page(): void {
    if (! current_user_can('manage_woocommerce')) {
        wp_die(esc_html__('You do not have permission to manage Checkout Policies.', 'vmsx-checkout-policies'));
    }
    $settings = vmsx_cp_settings();
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Checkout Policies', 'vmsx-checkout-policies'); ?></h1>
        <p><?php esc_html_e('Event-scoped carts must acknowledge these policies before standard checkout or an express wallet can complete payment.', 'vmsx-checkout-policies'); ?></p>
        <form method="post" action="options.php">
            <?php settings_fields('vmsx_checkout_policies'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e('Enabled', 'vmsx-checkout-policies'); ?></th>
                    <td><label><input type="checkbox" name="<?php echo esc_attr(VMSX_CP_OPTION); ?>[enabled]" value="1" <?php checked(! empty($settings['enabled'])); ?> /> <?php esc_html_e('Require Event Policies for event/ticket carts', 'vmsx-checkout-policies'); ?></label></td>
                </tr>
                <tr>
                    <th scope="row"><label for="vmsx-summary-label"><?php esc_html_e('Summary label', 'vmsx-checkout-policies'); ?></label></th>
                    <td><input class="regular-text" id="vmsx-summary-label" name="<?php echo esc_attr(VMSX_CP_OPTION); ?>[summary_label]" value="<?php echo esc_attr((string) $settings['summary_label']); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="vmsx-policy-title"><?php esc_html_e('Policy title', 'vmsx-checkout-policies'); ?></label></th>
                    <td><input class="regular-text" id="vmsx-policy-title" name="<?php echo esc_attr(VMSX_CP_OPTION); ?>[policy_title]" value="<?php echo esc_attr((string) $settings['policy_title']); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="vmsx-policy-body"><?php esc_html_e('Policy text', 'vmsx-checkout-policies'); ?></label></th>
                    <td><textarea class="large-text" rows="12" id="vmsx-policy-body" name="<?php echo esc_attr(VMSX_CP_OPTION); ?>[policy_body]"><?php echo esc_textarea((string) $settings['policy_body']); ?></textarea></td>
                </tr>
                <tr>
                    <th scope="row"><label for="vmsx-checkbox-label"><?php esc_html_e('Acceptance label', 'vmsx-checkout-policies'); ?></label></th>
                    <td><input class="large-text" id="vmsx-checkbox-label" name="<?php echo esc_attr(VMSX_CP_OPTION); ?>[checkbox_label]" value="<?php echo esc_attr((string) $settings['checkbox_label']); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="vmsx-policy-version"><?php esc_html_e('Policy version', 'vmsx-checkout-policies'); ?></label></th>
                    <td><input id="vmsx-policy-version" name="<?php echo esc_attr(VMSX_CP_OPTION); ?>[policy_version]" value="<?php echo esc_attr((string) $settings['policy_version']); ?>" /> <p class="description"><?php esc_html_e('Changing the version or policy copy invalidates any in-progress acceptance automatically.', 'vmsx-checkout-policies'); ?></p></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Display', 'vmsx-checkout-policies'); ?></th>
                    <td><label><input type="checkbox" name="<?php echo esc_attr(VMSX_CP_OPTION); ?>[open_by_default]" value="1" <?php checked(! empty($settings['open_by_default'])); ?> /> <?php esc_html_e('Open policy details by default', 'vmsx-checkout-policies'); ?></label></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Audit privacy', 'vmsx-checkout-policies'); ?></th>
                    <td><label><input type="checkbox" name="<?php echo esc_attr(VMSX_CP_OPTION); ?>[store_ip_user_agent]" value="1" <?php checked(! empty($settings['store_ip_user_agent'])); ?> /> <?php esc_html_e('Store IP address and browser user-agent with the order acknowledgement', 'vmsx-checkout-policies'); ?></label><p class="description"><?php esc_html_e('Leave disabled unless there is a specific reason to retain this data.', 'vmsx-checkout-policies'); ?></p></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

function vmsx_cp_missing_woocommerce_notice(): void {
    if (! current_user_can('activate_plugins')) {
        return;
    }
    echo '<div class="notice notice-error"><p>' . esc_html__('VMS Checkout Policies requires WooCommerce.', 'vmsx-checkout-policies') . '</p></div>';
}

function vmsx_cp_boot_dependency_check(): void {
    if (! class_exists('WooCommerce')) {
        add_action('admin_notices', 'vmsx_cp_missing_woocommerce_notice');
    }
}
add_action('plugins_loaded', 'vmsx_cp_boot_dependency_check', 20);
