<?php

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');

$GLOBALS['bvm_sqr_test_actions'] = array();
$GLOBALS['bvm_sqr_test_filters'] = array();
$GLOBALS['bvm_sqr_test_order'] = null;
$GLOBALS['bvm_sqr_test_http_calls'] = array();
$GLOBALS['bvm_sqr_test_http_response'] = array('response' => array('code' => 200), 'body' => '{"order":{"id":"SQUARE-321"}}');
$GLOBALS['bvm_sqr_test_http_responses'] = array();
$GLOBALS['bvm_sqr_test_http_callback'] = null;
$GLOBALS['bvm_sqr_test_logs'] = array();

function add_action(...$args): void
{
    $GLOBALS['bvm_sqr_test_actions'][] = $args;
}

function add_filter(...$args): void
{
    $GLOBALS['bvm_sqr_test_filters'][] = $args;
}

function absint($value): int
{
    return abs((int) $value);
}

function sanitize_text_field($value): string
{
    return trim(strip_tags((string) $value));
}

function wp_json_encode($value): string
{
    return (string) json_encode($value);
}

class WP_Error
{
    private string $code;
    private string $message;
    private $data;

    public function __construct(string $code = '', string $message = '', $data = null)
    {
        $this->code = $code;
        $this->message = $message;
        $this->data = $data;
    }

    public function get_error_code(): string
    {
        return $this->code;
    }

    public function get_error_message(): string
    {
        return $this->message;
    }

    public function get_error_data()
    {
        return $this->data;
    }
}

function is_wp_error($value): bool
{
    return $value instanceof WP_Error;
}

class WC_Order_Item_Product
{
    private string $name;
    private array $meta;

    public function __construct(string $name, array $meta = array())
    {
        $this->name = $name;
        $this->meta = $meta;
    }

    public function get_meta(string $key, bool $single = true)
    {
        unset($single);
        return $this->meta[$key] ?? '';
    }

    public function get_name(): string
    {
        return $this->name;
    }
}

class BVM_SQR_Test_Order
{
    public string $square_order_id = '';
    private int $id;
    private array $items;

    public function __construct(int $id, array $items)
    {
        $this->id = $id;
        $this->items = $items;
    }

    public function get_id(): int
    {
        return $this->id;
    }

    public function get_order_number(): string
    {
        return (string) $this->id;
    }

    public function get_items(string $type): array
    {
        return $type === 'line_item' ? $this->items : array();
    }
}

class BVM_SQR_Test_Settings
{
    public function get_access_token(): string
    {
        return 'test-token';
    }

    public function is_sandbox(): bool
    {
        return true;
    }
}

class BVM_SQR_Test_Plugin
{
    public function get_settings_handler(): BVM_SQR_Test_Settings
    {
        return new BVM_SQR_Test_Settings();
    }
}

class BVM_SQR_Test_Logger
{
    public function error(string $message, array $context): void
    {
        $GLOBALS['bvm_sqr_test_logs'][] = array($message, $context);
    }
}

function wc_square(): BVM_SQR_Test_Plugin
{
    return new BVM_SQR_Test_Plugin();
}

function wc_get_order(int $order_id)
{
    $order = $GLOBALS['bvm_sqr_test_order'];
    return $order instanceof BVM_SQR_Test_Order && $order->get_id() === $order_id ? $order : false;
}

function wc_get_logger(): BVM_SQR_Test_Logger
{
    return new BVM_SQR_Test_Logger();
}

function wp_remote_request(string $url, array $args)
{
    $GLOBALS['bvm_sqr_test_http_calls'][] = array($url, $args);
    $callback = $GLOBALS['bvm_sqr_test_http_callback'];
    if (is_callable($callback)) {
        $GLOBALS['bvm_sqr_test_http_callback'] = null;
        $callback();
    }
    if (!empty($GLOBALS['bvm_sqr_test_http_responses'])) {
        return array_shift($GLOBALS['bvm_sqr_test_http_responses']);
    }
    return $GLOBALS['bvm_sqr_test_http_response'];
}

function wp_remote_retrieve_response_code($response): int
{
    return (int) ($response['response']['code'] ?? 0);
}

function wp_remote_retrieve_body($response): string
{
    return (string) ($response['body'] ?? '');
}

require dirname(__DIR__) . '/includes/core.php';

function bvm_sqr_test_check(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function bvm_sqr_test_reset(): void
{
    $ticket = new WC_Order_Item_Product(
        "2026-09-19 19:00 - Child's Admission (12 & under)",
        array(
            '_bvm_square_reporting_class' => 'online_ticket',
            '_bvm_square_reporting_variation_id' => 'JT77UDM7GOTTCEDDX6LBCGYT',
        )
    );
    $control = new WC_Order_Item_Product('Popcorn');

    $GLOBALS['bvm_sqr_test_order'] = new BVM_SQR_Test_Order(321, array($ticket, $control));
    $GLOBALS['bvm_sqr_test_http_calls'] = array();
    $GLOBALS['bvm_sqr_test_http_response'] = array(
        'response' => array('code' => 200),
        'body' => '{"order":{"id":"SQUARE-321","version":8}}',
    );
    $GLOBALS['bvm_sqr_test_http_callback'] = null;
    $GLOBALS['bvm_sqr_test_http_responses'] = array();
    $GLOBALS['bvm_sqr_test_logs'] = array();
}

function bvm_sqr_test_exchange(?string $catalog_id = 'JT77UDM7GOTTCEDDX6LBCGYT'): array
{
    $ticket_line = array(
        'uid' => 'ticket-line-uid',
        'name' => 'ONLINE TICKET',
        'quantity' => '2',
        'base_price_money' => array('amount' => 2500, 'currency' => 'USD'),
        'total_tax_money' => array('amount' => 200, 'currency' => 'USD'),
        'total_discount_money' => array('amount' => 500, 'currency' => 'USD'),
    );
    if ($catalog_id !== null) {
        $ticket_line['catalog_object_id'] = $catalog_id;
    }

    $square_order = array(
        'id' => 'SQUARE-321',
        'version' => 7,
        'reference_id' => '321',
        'line_items' => array(
            $ticket_line,
            array(
                'uid' => 'control-line-uid',
                'name' => 'Popcorn',
                'catalog_object_id' => 'SQUARE-POPCORN-VARIATION',
                'quantity' => '1',
                'base_price_money' => array('amount' => 600, 'currency' => 'USD'),
            ),
        ),
    );

    return array(
        array(
            'uri' => 'https://connect.squareupsandbox.com/v2/locations/LOCATION/orders/createOrder',
            'body' => json_encode(array('order' => array('reference_id' => '321'))),
        ),
        array(
            'code' => 200,
            'body' => json_encode(array('order' => $square_order)),
        ),
        $square_order,
    );
}

$registered_hook = array_values(array_filter(
    $GLOBALS['bvm_sqr_test_actions'],
    static fn(array $hook): bool => ($hook[0] ?? '') === 'wc_square_credit_card_api_request_performed'
));
bvm_sqr_test_check(count($registered_hook) === 1, 'Square create-order observer should be registered once');
bvm_sqr_test_check(($registered_hook[0][1] ?? '') === 'bvm_sqr_restore_square_line_names_after_create', 'observer callback should be correct');
bvm_sqr_test_check(($registered_hook[0][2] ?? 0) === 20 && ($registered_hook[0][3] ?? 0) === 3, 'observer priority and arity should match staging proof');

$registered_cash_hook = array_values(array_filter(
    $GLOBALS['bvm_sqr_test_actions'],
    static fn(array $hook): bool => ($hook[0] ?? '') === 'wc_square_cash_app_pay_api_request_performed'
));
bvm_sqr_test_check(count($registered_cash_hook) === 1, 'Cash App create-order observer should be registered once');
bvm_sqr_test_check(($registered_cash_hook[0][1] ?? '') === 'bvm_sqr_restore_square_line_names_after_create', 'Cash App should use the same fail-closed observer');
bvm_sqr_test_check(($registered_cash_hook[0][2] ?? 0) === 20 && ($registered_cash_hook[0][3] ?? 0) === 3, 'Cash App observer priority and arity should match credit card');

foreach (array('wc_payment_gateway_square_credit_card_get_order', 'wc_payment_gateway_square_cash_app_pay_get_order') as $filter_name) {
    $registered_filter = array_values(array_filter(
        $GLOBALS['bvm_sqr_test_filters'],
        static fn(array $filter): bool => ($filter[0] ?? '') === $filter_name
    ));
    bvm_sqr_test_check(count($registered_filter) === 1, $filter_name . ' should have one pre-created order observer');
    bvm_sqr_test_check(($registered_filter[0][1] ?? '') === 'bvm_sqr_restore_precreated_square_order_line_names', $filter_name . ' should use the pre-created order restorer');
    bvm_sqr_test_check(($registered_filter[0][2] ?? 0) === 20 && ($registered_filter[0][3] ?? 0) === 2, $filter_name . ' should run after the discount bridge');
}

// Eligible ticket: exact Woo name restored with catalog identity and totals omitted from the partial update.
bvm_sqr_test_reset();
[$request, $response, $square_order_before] = bvm_sqr_test_exchange();
bvm_sqr_restore_square_line_names_after_create($request, $response, null);
bvm_sqr_test_check(count($GLOBALS['bvm_sqr_test_http_calls']) === 1, 'eligible ticket should trigger one Square update');
[$url, $args] = $GLOBALS['bvm_sqr_test_http_calls'][0];
$payload = json_decode((string) $args['body'], true);
$update_lines = $payload['order']['line_items'] ?? array();
bvm_sqr_test_check($url === 'https://connect.squareupsandbox.com/v2/orders/SQUARE-321', 'sandbox order endpoint should be used');
bvm_sqr_test_check(($args['method'] ?? '') === 'PUT', 'Square update should use PUT');
bvm_sqr_test_check(($payload['order']['version'] ?? null) === 7, 'Square order version should be preserved');
bvm_sqr_test_check(count($update_lines) === 1, 'unrelated Square control line should not be included');
bvm_sqr_test_check(array_keys($update_lines[0]) === array('uid', 'name'), 'only uid and name may be updated');
bvm_sqr_test_check($update_lines[0]['uid'] === 'ticket-line-uid', 'existing Square line UID should be targeted');
bvm_sqr_test_check($update_lines[0]['name'] === "2026-09-19 19:00 - Child's Admission (12 & under)", 'exact Woo/BVM name should be restored');
bvm_sqr_test_check($square_order_before['line_items'][0]['catalog_object_id'] === 'JT77UDM7GOTTCEDDX6LBCGYT', 'stable reporting catalog variation should remain attached');
bvm_sqr_test_check($square_order_before['line_items'][1]['name'] === 'Popcorn', 'normal Square catalog control should remain unchanged');
bvm_sqr_test_check(!isset($update_lines[0]['quantity'], $update_lines[0]['base_price_money'], $update_lines[0]['total_tax_money'], $update_lines[0]['total_discount_money']), 'quantity, price, tax, and discount must not be changed');

// Normal paid GA, including a multiple-ticket quantity, keeps the exact event-aware name.
$paid_ga = new WC_Order_Item_Product(
    '2026-10-03 19:00 - General Admission',
    array(
        '_bvm_square_reporting_class' => 'online_ticket',
        '_bvm_square_reporting_variation_id' => 'TICKET-VARIATION',
    )
);
$paid_updates = bvm_sqr_build_square_line_name_updates(
    new BVM_SQR_Test_Order(401, array($paid_ga)),
    array('line_items' => array(array(
        'uid' => 'paid-ga',
        'name' => 'ONLINE TICKET',
        'catalog_object_id' => 'TICKET-VARIATION',
        'quantity' => '4',
        'base_price_money' => array('amount' => 2000, 'currency' => 'USD'),
    )))
);
bvm_sqr_test_check($paid_updates === array(array('uid' => 'paid-ga', 'name' => '2026-10-03 19:00 - General Admission')), 'paid and multiple GA tickets should resolve to the exact Woo name');

// A complimentary ticket is still eligible even when its Square price is zero.
$comp_ticket = new WC_Order_Item_Product(
    '2026-10-03 19:00 - Police, Fire Fighter, EMT Admission',
    array(
        '_bvm_square_reporting_class' => 'online_ticket',
        '_bvm_square_reporting_variation_id' => 'TICKET-VARIATION',
    )
);
$comp_updates = bvm_sqr_build_square_line_name_updates(
    new BVM_SQR_Test_Order(402, array($comp_ticket)),
    array('line_items' => array(array(
        'uid' => 'comp-ticket',
        'name' => 'ONLINE TICKET',
        'catalog_object_id' => 'TICKET-VARIATION',
        'quantity' => '2',
        'base_price_money' => array('amount' => 0, 'currency' => 'USD'),
    )))
);
bvm_sqr_test_check(($comp_updates[0]['name'] ?? '') === '2026-10-03 19:00 - Police, Fire Fighter, EMT Admission', 'complimentary tickets should not remain generic');

// Ticket and add-on lines resolve independently in the same order.
$addon = new WC_Order_Item_Product(
    '2026-10-03 19:00 - Table #06',
    array(
        '_bvm_square_reporting_class' => 'online_addon',
        '_bvm_square_reporting_variation_id' => 'ADDON-VARIATION',
    )
);
$combined_updates = bvm_sqr_build_square_line_name_updates(
    new BVM_SQR_Test_Order(403, array($paid_ga, $addon)),
    array('line_items' => array(
        array('uid' => 'ticket', 'name' => 'ONLINE TICKET', 'catalog_object_id' => 'TICKET-VARIATION'),
        array('uid' => 'addon', 'name' => 'ONLINE ADDON', 'catalog_object_id' => 'ADDON-VARIATION'),
    ))
);
bvm_sqr_test_check(count($combined_updates) === 2, 'ticket plus add-on should produce two independent name updates');
bvm_sqr_test_check(($combined_updates[0]['name'] ?? '') === '2026-10-03 19:00 - General Admission', 'combined ticket should resolve');
bvm_sqr_test_check(($combined_updates[1]['name'] ?? '') === '2026-10-03 19:00 - Table #06', 'combined add-on should resolve');

// Future-event identity must come from that order item's canonical Woo name.
$future_ticket = new WC_Order_Item_Product(
    '2026-10-24 19:00 - General Admission',
    array(
        '_bvm_square_reporting_class' => 'online_ticket',
        '_bvm_square_reporting_variation_id' => 'TICKET-VARIATION',
    )
);
$future_updates = bvm_sqr_build_square_line_name_updates(
    new BVM_SQR_Test_Order(404, array($future_ticket)),
    array('line_items' => array(array('uid' => 'future', 'name' => 'ONLINE TICKET', 'catalog_object_id' => 'TICKET-VARIATION')))
);
bvm_sqr_test_check(($future_updates[0]['name'] ?? '') === '2026-10-24 19:00 - General Admission', 'future-event ticket date should be preserved');

// A Square order pre-created by the native-discount bridge is retrieved and fixed before payment.
bvm_sqr_test_reset();
[$request, $response, $precreated_square_order] = bvm_sqr_test_exchange();
$precreated_order = $GLOBALS['bvm_sqr_test_order'];
$precreated_order->square_order_id = 'SQUARE-321';
$GLOBALS['bvm_sqr_test_http_responses'] = array(
    array('response' => array('code' => 200), 'body' => json_encode(array('order' => $precreated_square_order))),
    array('response' => array('code' => 200), 'body' => '{"order":{"id":"SQUARE-321","version":8}}'),
);
$returned_order = bvm_sqr_restore_precreated_square_order_line_names($precreated_order, null);
bvm_sqr_test_check($returned_order === $precreated_order, 'pre-created order filter must return the unchanged gateway order object');
bvm_sqr_test_check(count($GLOBALS['bvm_sqr_test_http_calls']) === 2, 'pre-created order should perform one GET and one narrow PUT');
bvm_sqr_test_check(($GLOBALS['bvm_sqr_test_http_calls'][0][1]['method'] ?? '') === 'GET', 'pre-created order should be retrieved first');
bvm_sqr_test_check(($GLOBALS['bvm_sqr_test_http_calls'][1][1]['method'] ?? '') === 'PUT', 'pre-created generic line should be restored before payment');

// A retry sees the already-correct name and performs no second PUT or order creation.
$precreated_square_order['version'] = 8;
$precreated_square_order['line_items'][0]['name'] = "2026-09-19 19:00 - Child's Admission (12 & under)";
$GLOBALS['bvm_sqr_test_http_responses'] = array(
    array('response' => array('code' => 200), 'body' => json_encode(array('order' => $precreated_square_order))),
);
bvm_sqr_restore_precreated_square_order_line_names($precreated_order, null);
bvm_sqr_test_check(count($GLOBALS['bvm_sqr_test_http_calls']) === 3, 'retry should add only one read and no duplicate update');
bvm_sqr_test_check(($GLOBALS['bvm_sqr_test_http_calls'][2][1]['method'] ?? '') === 'GET', 'retry should remain read-only after names are correct');

// A mismatched catalog object ID must fail closed.
bvm_sqr_test_reset();
[$request, $response] = bvm_sqr_test_exchange('DIFFERENT-SQUARE-VARIATION');
bvm_sqr_restore_square_line_names_after_create($request, $response, null);
bvm_sqr_test_check(count($GLOBALS['bvm_sqr_test_http_calls']) === 0, 'mismatched catalog ID should not update Square');

// A missing catalog object ID must also fail closed.
bvm_sqr_test_reset();
[$request, $response] = bvm_sqr_test_exchange(null);
bvm_sqr_restore_square_line_names_after_create($request, $response, null);
bvm_sqr_test_check(count($GLOBALS['bvm_sqr_test_http_calls']) === 0, 'missing catalog ID should not update Square');

// Re-entry during the update request must not cause a second update.
bvm_sqr_test_reset();
[$request, $response] = bvm_sqr_test_exchange();
$GLOBALS['bvm_sqr_test_http_callback'] = static function () use ($request, $response): void {
    bvm_sqr_restore_square_line_names_after_create($request, $response, null);
};
bvm_sqr_restore_square_line_names_after_create($request, $response, null);
bvm_sqr_test_check(count($GLOBALS['bvm_sqr_test_http_calls']) === 1, 're-entry guard should prevent recursive Square updates');

// Square API failure must be logged and swallowed so checkout/payment can continue.
bvm_sqr_test_reset();
[$request, $response] = bvm_sqr_test_exchange();
$GLOBALS['bvm_sqr_test_http_response'] = array(
    'response' => array('code' => 409),
    'body' => '{"errors":[{"detail":"Square version conflict"}]}',
);
$thrown = null;
try {
    bvm_sqr_restore_square_line_names_after_create($request, $response, null);
} catch (Throwable $e) {
    $thrown = $e;
}
bvm_sqr_test_check($thrown === null, 'Square failure should not escape into checkout/payment');
bvm_sqr_test_check(count($GLOBALS['bvm_sqr_test_logs']) === 1, 'Square failure should be logged once');
bvm_sqr_test_check(strpos($GLOBALS['bvm_sqr_test_logs'][0][0], 'Woo order #321') !== false, 'failure log should identify the Woo order');
bvm_sqr_test_check(strpos($GLOBALS['bvm_sqr_test_logs'][0][0], 'Square version conflict') !== false, 'failure log should contain the Square detail');
bvm_sqr_test_check(($GLOBALS['bvm_sqr_test_logs'][0][1]['source'] ?? '') === 'bvm-square-reporting', 'failure log should use the plugin source');

fwrite(STDOUT, "PASS: BVM Square Reporting line-name restoration\n");
