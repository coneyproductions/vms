<?php
/** Regression for cross-event cancellation candidates caused by operational item metadata. */
declare(strict_types=1);

define('ABSPATH', __DIR__ . '/no-wordpress/');

$GLOBALS['fixture_meta'] = array(
    2535 => array('_vms_tec_event_id' => 7212, '_vms_event_date' => '2026-10-02'),
    7216 => array('_vms_event_plan_id' => 2535, '_vms_tec_event_id' => 7212, '_tribe_wooticket_for_event' => 7212),
    6996 => array('_vms_event_plan_id' => 6994, '_vms_tec_event_id' => 6995, '_tribe_wooticket_for_event' => 6995),
    8014 => array('_vms_product_role' => 'online_tip'),
);
$GLOBALS['fixture_post_types'] = array(2535 => 'vms_event_plan', 7212 => 'tribe_events', 6994 => 'vms_event_plan', 6995 => 'tribe_events');
$GLOBALS['fixture_orders'] = array();

function apply_filters($hook, $value, ...$args) { return $value; }
function absint($value) { return abs((int) $value); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function get_post_meta($post_id, $key, $single = true) { return $GLOBALS['fixture_meta'][$post_id][$key] ?? ''; }
function get_post_type($post_id) { return $GLOBALS['fixture_post_types'][$post_id] ?? ''; }
function get_the_title($post_id) { return $post_id === 2535 ? 'Sonny James Band' : ''; }
function wc_get_price_decimals() { return 2; }
function wc_get_is_paid_statuses() { return array('processing', 'completed'); }
function wc_get_order_statuses() { return array('wc-processing' => 'Processing', 'wc-completed' => 'Completed'); }
function wc_get_payment_gateway_by_order($order) { return new class { public function supports($feature) { return $feature === 'refunds'; } }; }
function wc_can_refund_order($order) { return true; }
function bvmgr_cancellation_get_event_refundable_product_ids($event_plan_id, $tec_event_id = 0) { return $event_plan_id === 2535 && $tec_event_id === 7212 ? array(7216) : array(); }
function bvmgr_cancellation_refund_product_role($product_id) { return $product_id === 7216 ? 'ga_ticket' : ($product_id === 8014 ? 'online_tip' : ''); }
function wc_get_orders($args) {
    $orders = array_values($GLOBALS['fixture_orders']);
    return (object) array('orders' => $orders, 'total' => count($orders), 'max_num_pages' => 1);
}

final class CancellationFixtureMeta
{
    public function __construct(private string $key, private $value) {}
    public function get_data(): array { return array('key' => $this->key, 'value' => $this->value); }
}

final class CancellationFixtureItem
{
    public function __construct(
        private int $product_id,
        private string $name,
        private float $total,
        private array $meta = array(),
        private string $type = 'line_item'
    ) {}
    public function get_type(): string { return $this->type; }
    public function get_product_id(): int { return $this->product_id; }
    public function get_variation_id(): int { return 0; }
    public function get_meta($key, $single = true) { return $this->meta[$key] ?? ''; }
    public function get_meta_data(): array {
        $out = array();
        foreach ($this->meta as $key => $value) $out[] = new CancellationFixtureMeta((string) $key, $value);
        return $out;
    }
    public function get_name(): string { return $this->name; }
    public function get_quantity(): float { return 1.0; }
    public function get_total(): float { return $this->total; }
    public function get_taxes(): array { return array('total' => array()); }
}

final class CancellationFixtureOrder
{
    public function __construct(private int $id, private array $items, private array $meta = array()) {}
    public function get_id(): int { return $this->id; }
    public function get_items($types = array()): array { return $this->items; }
    public function get_meta($key, $single = true) { return $this->meta[$key] ?? ''; }
    public function get_order_number(): string { return (string) $this->id; }
    public function get_total_refunded_for_item($item_id, $item_type = 'line_item'): float { return 0.0; }
    public function get_tax_refunded_for_item($item_id, $tax_id, $item_type = 'line_item'): float { return 0.0; }
    public function get_qty_refunded_for_item($item_id, $item_type = 'line_item'): float { return 0.0; }
    public function get_remaining_refund_amount(): float { return $this->get_total(); }
    public function get_total(): float { return array_sum(array_map(static fn($item) => $item->get_total(), $this->items)); }
    public function get_total_refunded(): float { return 0.0; }
    public function get_status(): string { return 'completed'; }
    public function get_currency(): string { return 'USD'; }
    public function get_formatted_billing_full_name(): string { return ''; }
    public function get_billing_email(): string { return ''; }
}

require dirname(__DIR__) . '/includes/core/cancellation-purchases.php';

function cancellation_hotfix_assert(string $name, bool $condition): void
{
    if (!$condition) throw new RuntimeException('FAIL: ' . $name);
    $GLOBALS['hotfix_checks'][] = $name;
}

$target_context = array('event_plan_id' => 2535, 'tec_event_id' => 7212, 'product_lookup' => array(7216 => true));
$providers = bvmgr_cancel_purchase_providers();
$tip = new CancellationFixtureItem(8014, 'Bar Staff Tip', 4.0, array('_vms_express_bar_tip_selection' => 'pct:20'));
$unknown = new CancellationFixtureItem(0, 'Unknown event component', 3.0, array('_custom_event_plan_id' => 'opaque'), 'fee');

foreach (array('_vms_express_bar_tip_selection', '_vms_express_bar_status', 'event_note', 'reservation_label') as $key) {
    cancellation_hotfix_assert($key . ' is operational metadata', !bvmgr_cancel_purchase_meta_key_is_event_identity($key));
}
foreach (array('event_plan_id', '_custom_event_plan_id', '_vms_express_bar_event_plan_id', '_custom_event_id', '_custom_tec_event_id', '_custom_tec_event_post_id', '_custom_occurrence_id', '_custom_reservation_id') as $key) {
    cancellation_hotfix_assert($key . ' is identity metadata', bvmgr_cancel_purchase_meta_key_is_event_identity($key));
}

$tip_identity = bvmgr_cancel_purchase_identity($tip, $target_context, $providers);
cancellation_hotfix_assert('tip selection does not create relationship hint', $tip_identity['state'] === 'unrelated');
$unknown_identity = bvmgr_cancel_purchase_identity($unknown, $target_context, $providers);
cancellation_hotfix_assert('unknown identity-style metadata remains unresolved', $unknown_identity['state'] === 'unresolved' && $unknown_identity['reason'] === 'unsupported_event_relationship');

$GLOBALS['fixture_orders'] = array(
    8013 => new CancellationFixtureOrder(8013, array(
        11 => new CancellationFixtureItem(6996, 'George Strait ticket', 20.0, array('_vms_event_plan_id' => 6994, '_vms_tec_event_post_id' => 6995)),
        12 => $tip,
    ), array('_vms_express_bar_event_plan_ids' => '6994')),
    9001 => new CancellationFixtureOrder(9001, array(
        21 => new CancellationFixtureItem(7216, 'Sonny James ticket', 20.0, array('_vms_event_plan_id' => 2535, '_vms_tec_event_post_id' => 7212)),
        22 => $tip,
        23 => $unknown,
    )),
    9002 => new CancellationFixtureOrder(9002, array(31 => $unknown)),
    9003 => new CancellationFixtureOrder(9003, array(41 => $unknown), array('_vms_event_plan_id' => '2535')),
);

$scope = bvmgr_cancel_purchase_discover(2535);
$candidate_ids = array_column($scope['candidates'], 'order_id');
cancellation_hotfix_assert('George Strait order is excluded from Sonny James cancellation', !in_array(8013, $candidate_ids, true));
cancellation_hotfix_assert('unresolved-only unanchored order is excluded', !in_array(9002, $candidate_ids, true));
cancellation_hotfix_assert('valid Sonny James order remains a candidate', in_array(9001, $candidate_ids, true));
cancellation_hotfix_assert('explicit matching order-level identity anchors unresolved order', in_array(9003, $candidate_ids, true));

$target_candidate = null;
foreach ($scope['candidates'] as $candidate) if ($candidate['order_id'] === 9001) $target_candidate = $candidate;
cancellation_hotfix_assert('target candidate was found', is_array($target_candidate));
cancellation_hotfix_assert('tip cannot independently enter target scope', array_column($target_candidate['line_items'], 'item_id') === array(21, 23));
cancellation_hotfix_assert('anchored target preserves unresolved component review', $target_candidate['line_items'][1]['state'] === 'unresolved' && $target_candidate['manual_review_reason'] === 'unresolved_components');

echo json_encode(array('passed' => count($GLOBALS['hotfix_checks']), 'checks' => $GLOBALS['hotfix_checks']), JSON_PRETTY_PRINT) . "\n";
