<?php
declare(strict_types=1);

$repoRoot = dirname(__DIR__, 2);
$archive = $repoRoot . '/docs/addon-compatibility/artifacts/vms-commerce-discounts-0.2.12.zip';
if (!is_file($archive) || hash_file('sha256', $archive) !== '0cd5f4d2d0ce3dd9484d85442dff38783bfa45f17f46bdd942d1e9ba9962b001') {
	fwrite(STDERR, "The authoritative Commerce 0.2.12 artifact is missing or changed.\n");
	exit(2);
}

$expectedVersion = getenv('BVM_COMMERCE_EXPECTED_VERSION') ?: '0.2.13';
$sourceRoot = getenv('BVM_COMMERCE_SOURCE_DIR') ?: $repoRoot . '/companion-plugins/vms-commerce-discounts';
$sourceRoot = realpath($sourceRoot) ?: '';
$entryFile = $sourceRoot . '/vms-commerce-discounts.php';
if (!is_file($entryFile)) {
	fwrite(STDERR, "The Commerce {$expectedVersion} source candidate is missing.\n");
	exit(2);
}
$entrySource = (string) file_get_contents($entryFile);
if (preg_match('/^Version:\s*' . preg_quote($expectedVersion, '/') . '\s*$/m', $entrySource) !== 1
	|| strpos($entrySource, "define('VMS_DISCOUNTS_VERSION', '{$expectedVersion}')") === false) {
	fwrite(STDERR, "The Commerce candidate header or runtime version does not match {$expectedVersion}.\n");
	exit(2);
}

if (!defined('ABSPATH')) {
	define('ABSPATH', $repoRoot . '/');
}
define('ARRAY_A', 'ARRAY_A');
define('VMS_DISCOUNTS_VERSION', $expectedVersion);
define('VMS_DISCOUNTS_PATH', $sourceRoot . '/');
define('VMS_DISCOUNTS_URL', 'https://fixture.invalid/vms-commerce-discounts/');

$GLOBALS['commerce_actions'] = array();
$GLOBALS['commerce_filters'] = array();
$GLOBALS['commerce_options'] = array();
$GLOBALS['commerce_post_meta'] = array();
$GLOBALS['commerce_assets'] = array();
$GLOBALS['commerce_submenu_hook'] = 'woocommerce_page_vms-commerce-discounts';

function add_action(string $hook, $callback, int $priority = 10, int $acceptedArgs = 1): void
{
	$GLOBALS['commerce_actions'][$hook][] = array($callback, $priority, $acceptedArgs);
}

function add_filter(string $hook, $callback, int $priority = 10, int $acceptedArgs = 1): void
{
	$GLOBALS['commerce_filters'][$hook][] = array($callback, $priority, $acceptedArgs);
}

function apply_filters(string $hook, $value, ...$args)
{
	return $value;
}

function sanitize_text_field(string $value): string
{
	return trim(strip_tags($value));
}

function sanitize_key(string $value): string
{
	return strtolower((string) preg_replace('/[^a-z0-9_\-]/i', '', $value));
}

function get_post_meta(int $postId, string $key, bool $single = false)
{
	return $GLOBALS['commerce_post_meta'][$postId][$key] ?? ($single ? '' : array());
}

function update_post_meta(int $postId, string $key, $value): bool
{
	$GLOBALS['commerce_post_meta'][$postId][$key] = $value;
	return true;
}

function get_option(string $key, $default = false)
{
	return array_key_exists($key, $GLOBALS['commerce_options']) ? $GLOBALS['commerce_options'][$key] : $default;
}

function update_option(string $key, $value, bool $autoload = true): bool
{
	$GLOBALS['commerce_options'][$key] = $value;
	return true;
}

function current_time(string $type): string
{
	return '2026-09-05 12:00:00';
}

function wp_generate_uuid4(): string
{
	return '00000000-0000-4000-8000-000000000001';
}

function wc_get_price_decimals(): int
{
	return 2;
}

function wc_get_rounding_precision(): int
{
	return 4;
}

function wc_format_decimal($amount, int $precision = 2, bool $trimZeros = false): string
{
	return number_format((float) $amount, $precision, '.', '');
}

function wc_prices_include_tax(): bool
{
	return false;
}

function wc_price($amount, array $args = array()): string
{
	return '<span class="amount">$' . number_format((float) $amount, 2, '.', '') . '</span>';
}

function wp_kses_post(string $value): string
{
	return $value;
}

function wp_strip_all_tags(string $value): string
{
	return strip_tags($value);
}

function get_bloginfo(string $field): string
{
	return $field === 'charset' ? 'UTF-8' : '';
}

function esc_html(string $value): string
{
	return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function esc_attr(string $value): string
{
	return esc_html($value);
}

function is_admin(): bool
{
	return false;
}

function wp_doing_ajax(): bool
{
	return false;
}

function add_submenu_page(string $parent, string $pageTitle, string $menuTitle, string $capability, string $slug, $callback): string
{
	return $GLOBALS['commerce_submenu_hook'];
}

function wp_enqueue_style(string $handle, string $src, array $deps = array(), $version = false): void
{
	$GLOBALS['commerce_assets'][] = array('style', $handle, $src, $version);
}

function wp_enqueue_script(string $handle, string $src, array $deps = array(), $version = false, bool $footer = false): void
{
	$GLOBALS['commerce_assets'][] = array('script', $handle, $src, $version);
}

function wp_localize_script(string $handle, string $objectName, array $data): void
{
	$GLOBALS['commerce_assets'][] = array('localized', $handle, $objectName, $data);
}

function admin_url(string $path = ''): string
{
	return 'https://fixture.invalid/wp-admin/' . ltrim($path, '/');
}

function wp_create_nonce(string $action): string
{
	return 'nonce-' . $action;
}

final class CommerceFixtureSession
{
	private array $values = array();

	public function get(string $key, $default = null)
	{
		return array_key_exists($key, $this->values) ? $this->values[$key] : $default;
	}

	public function set(string $key, $value): void
	{
		$this->values[$key] = $value;
	}
}

$GLOBALS['commerce_wc'] = (object) array('session' => new CommerceFixtureSession());
function WC(): object
{
	return $GLOBALS['commerce_wc'];
}

final class CommerceFixtureProduct
{
	public function __construct(private int $id, private float $price)
	{
	}

	public function get_id(): int
	{
		return $this->id;
	}

	public function get_price(): string
	{
		return (string) $this->price;
	}

	public function set_price($price): void
	{
		$this->price = (float) $price;
	}
}

final class CommerceFixtureCart
{
	public function __construct(public array $cart_contents)
	{
	}

	public function get_cart(): array
	{
		return $this->cart_contents;
	}

	public function is_empty(): bool
	{
		return $this->cart_contents === array();
	}
}

class WC_Order_Item_Product
{
	private array $meta = array();

	public function __construct(private int $quantity, private float $subtotal)
	{
	}

	public function add_meta_data(string $key, $value, bool $unique = false): void
	{
		$this->meta[$key] = $value;
	}

	public function get_meta(string $key, bool $single = false)
	{
		return $this->meta[$key] ?? '';
	}

	public function get_quantity(): int
	{
		return $this->quantity;
	}

	public function get_subtotal(): float
	{
		return $this->subtotal;
	}

	public function get_subtotal_tax(): float
	{
		return 0.0;
	}
}

final class CommerceFixtureOrder
{
	public array $meta = array();

	public function __construct(private array $items)
	{
	}

	public function get_items(): array
	{
		return $this->items;
	}

	public function update_meta_data(string $key, $value): void
	{
		$this->meta[$key] = $value;
	}

	public function get_meta(string $key, bool $single = false)
	{
		return $this->meta[$key] ?? '';
	}
}

foreach (array('helpers.php', 'class-vms-discounts-rules.php', 'class-vms-discounts-mapping.php', 'class-vms-discounts-cart.php', 'class-vms-discounts-order.php', 'class-vms-discounts-admin.php', 'class-vms-discounts-settings.php') as $file) {
	require $sourceRoot . '/includes/' . $file;
}

$failures = array();
$assert = static function (bool $condition, string $message) use (&$failures): void {
	if (!$condition) {
		$failures[] = $message;
	}
};
$approximately = static fn($actual, $expected): bool => abs((float) $actual - (float) $expected) < 0.001;

$eventRule = array(
	'id' => 'four-paid-tickets',
	'enabled' => true,
	'admin_label' => 'Four paid tickets',
	'public_label' => 'Group Discount',
	'priority' => 10,
	'stacking_mode' => 'stack',
	'scope' => 'event_only',
	'qual_type' => 'ticket_qty',
	'required_qty' => 4,
	'product_ids' => array(),
	'discount_type' => 'percent',
	'amount' => 10,
	'applies_to' => 'tickets',
	'max_applications_per_order' => 1,
	'cap_amount' => 0,
);
$GLOBALS['commerce_post_meta'][501][VMS_Discounts_Rules::EVENT_RULES_META_KEY] = array($eventRule);
$GLOBALS['commerce_post_meta'][101]['_tribe_wooticket_for_event'] = 501;
$GLOBALS['commerce_post_meta'][103]['_tribe_wooticket_for_event'] = 501;
$GLOBALS['commerce_post_meta'][202]['_vms_product_role'] = 'addon';
$GLOBALS['commerce_post_meta'][202]['_vms_event_plan_id'] = 501;

$rules = new VMS_Discounts_Rules();
$mapping = new VMS_Discounts_Mapping();
$cartService = new VMS_Discounts_Cart($rules, $mapping);
$orderService = new VMS_Discounts_Order($cartService, $rules, $mapping);

$normalized = $rules->normalize_rule(array(
	'enabled' => 'yes',
	'stacking_mode' => 'invalid',
	'scope' => 'invalid',
	'qual_type' => 'invalid',
	'discount_type' => 'percent',
	'amount' => 125,
	'applies_to' => 'invalid',
	'required_qty' => 0,
));
$assert($normalized['stacking_mode'] === 'stack' && $normalized['scope'] === 'event_plus_global', 'Rule enum normalization changed.');
$assert($normalized['qual_type'] === 'ticket_qty' && $normalized['applies_to'] === 'tickets', 'Rule qualification/applicability defaults changed.');
$assert($normalized['amount'] === 100.0 && $normalized['required_qty'] === 1, 'Rule percentage/quantity bounds changed.');

$paidProduct = new CommerceFixtureProduct(101, 20.0);
$cart = new CommerceFixtureCart(array(
	'paid' => array('product_id' => 101, 'quantity' => 4, 'line_subtotal' => 80.0, 'line_total' => 80.0, 'data' => $paidProduct),
));
$cartService->apply_cart_item_adjustments($cart);
$entries = $cartService->get_applied_entries_from_session();
$assert($approximately($paidProduct->get_price(), 18.0), 'Four paid tickets did not receive the expected 10% adjusted unit price.');
$assert(count($entries) === 1 && $approximately($entries[0]['discount_amount'] ?? 0, 8.0), 'Four paid tickets did not persist one $8 session discount.');
$assert($approximately($cart->cart_contents['paid'][VMS_Discounts_Cart::CART_ITEM_LINE_DISCOUNT_KEY] ?? 0, 8.0), 'The paid cart line did not retain its $8 allocation.');

$classicPrice = $cartService->filter_cart_item_price_html('$18.00', $cart->cart_contents['paid'], 'paid');
$assert(strpos($classicPrice, '<del') !== false && strpos($classicPrice, '<ins') !== false, 'Classic cart price did not show original and discounted prices.');
$blockData = $cartService->store_api_cart_item_display_data($cart->cart_contents['paid']);
$assert(($blockData['has_discount'] ?? false) === true && ($blockData['discounted_line_total'] ?? null) === 72.0, 'Store API display data did not retain the discounted line total.');
$assert(strpos((string) ($blockData['discounted_line_total_text'] ?? ''), '<') === false, 'Store API plain-text display data leaked markup.');
$assert(!isset($GLOBALS['commerce_actions']['woocommerce_blocks_loaded']), 'The intentionally retired Woo Blocks display injection was re-registered.');

ob_start();
$cartService->render_checkout_discount_rows();
$checkoutHtml = (string) ob_get_clean();
$assert(strpos($checkoutHtml, 'Discount: Group Discount') !== false && strpos($checkoutHtml, '$-8.00') !== false, 'Checkout summary did not render the expected discount row.');

$orderItem = new WC_Order_Item_Product(4, 72.0);
$orderService->capture_order_item_discount_meta($orderItem, 'paid', $cart->cart_contents['paid'], null);
$order = new CommerceFixtureOrder(array(77 => $orderItem));
$orderService->persist_applied_discounts_meta($order, array('payment_method' => 'cod'));
$assert($approximately($order->meta[VMS_Discounts_Order::META_GROSS_SUBTOTAL] ?? 0, 80.0), 'Order persistence lost the gross ticket subtotal.');
$assert($approximately($order->meta[VMS_Discounts_Order::META_DISCOUNT_TOTAL] ?? 0, 8.0), 'Order persistence lost the discount total.');
$assert($approximately($order->meta[VMS_Discounts_Order::META_NET_SUBTOTAL] ?? 0, 72.0), 'Order persistence lost the net ticket subtotal.');
$assert(($order->meta[VMS_Discounts_Order::META_SQUARE_SYNC_STATUS] ?? '') === VMS_Discounts_Order::SQUARE_SYNC_NOT_APPLICABLE, 'Non-Square order status changed.');
$ledger = $order->meta[VMS_Discounts_Order::META_LEDGER] ?? array();
$assert(($ledger[0]['affected_order_item_ids'] ?? array()) === array(77), 'Order ledger did not map the discounted cart line to its order item.');

$cartService->restore_original_cart_item_prices($cart);
$assert($approximately($paidProduct->get_price(), 20.0), 'Cart recalculation did not restore the original unit price first.');
$assert(!isset($cart->cart_contents['paid'][VMS_Discounts_Cart::CART_ITEM_LINE_DISCOUNT_KEY]), 'Cart recalculation left stale line adjustment metadata.');
$cartService->apply_cart_item_adjustments($cart);
$assert($approximately($paidProduct->get_price(), 18.0), 'Repeated cart calculation was not deterministic.');

$belowProduct = new CommerceFixtureProduct(101, 20.0);
$below = new CommerceFixtureCart(array(
	'paid' => array('product_id' => 101, 'quantity' => 3, 'line_subtotal' => 60.0, 'line_total' => 60.0, 'data' => $belowProduct),
));
$cartService->apply_cart_item_adjustments($below);
$assert($approximately($belowProduct->get_price(), 20.0) && $cartService->get_applied_entries_from_session() === array(), 'Three paid tickets incorrectly qualified for the four-ticket rule.');

$paidThreeProduct = new CommerceFixtureProduct(101, 20.0);
$compedProduct = new CommerceFixtureProduct(103, 20.0);
$mixed = new CommerceFixtureCart(array(
	'paid' => array('product_id' => 101, 'quantity' => 3, 'line_subtotal' => 60.0, 'line_total' => 60.0, 'data' => $paidThreeProduct),
	'comped' => array('product_id' => 103, 'quantity' => 1, 'line_subtotal' => 0.0, 'line_total' => 0.0, 'data' => $compedProduct),
));
$cartService->apply_cart_item_adjustments($mixed);
$assert(
	$cartService->get_applied_entries_from_session() === array()
		&& $approximately($paidThreeProduct->get_price(), 20.0)
		&& $approximately($compedProduct->get_price(), 20.0),
	'An explicitly zero/comped ticket line incorrectly counted toward the four-paid-ticket threshold.'
);

$paidFourProduct = new CommerceFixtureProduct(101, 20.0);
$extraCompedProduct = new CommerceFixtureProduct(103, 20.0);
$mixedQualified = new CommerceFixtureCart(array(
	'paid' => array('product_id' => 101, 'quantity' => 4, 'line_subtotal' => 80.0, 'line_total' => 80.0, 'data' => $paidFourProduct),
	'comped' => array('product_id' => 103, 'quantity' => 1, 'line_subtotal' => 0.0, 'line_total' => 0.0, 'data' => $extraCompedProduct),
));
$cartService->apply_cart_item_adjustments($mixedQualified);
$mixedQualifiedEntries = $cartService->get_applied_entries_from_session();
$assert(
	$approximately($paidFourProduct->get_price(), 18.0)
		&& $approximately($extraCompedProduct->get_price(), 20.0)
		&& count($mixedQualifiedEntries) === 1
		&& $approximately($mixedQualifiedEntries[0]['discount_amount'] ?? 0, 8.0)
		&& !isset($mixedQualified->cart_contents['comped'][VMS_Discounts_Cart::CART_ITEM_LINE_DISCOUNT_KEY]),
	'Four paid tickets plus one comp did not discount only the four paid tickets.'
);

$paidTwoProduct = new CommerceFixtureProduct(101, 20.0);
$compedTwoProduct = new CommerceFixtureProduct(103, 20.0);
$twoPaidTwoComped = new CommerceFixtureCart(array(
	'paid' => array('product_id' => 101, 'quantity' => 2, 'line_subtotal' => 40.0, 'line_total' => 40.0, 'data' => $paidTwoProduct),
	'comped' => array('product_id' => 103, 'quantity' => 2, 'line_subtotal' => 0.0, 'line_total' => 0.0, 'data' => $compedTwoProduct),
));
$cartService->apply_cart_item_adjustments($twoPaidTwoComped);
$assert(
	$cartService->get_applied_entries_from_session() === array()
		&& $approximately($paidTwoProduct->get_price(), 20.0)
		&& $approximately($compedTwoProduct->get_price(), 20.0),
	'Two paid tickets plus two comps incorrectly qualified.'
);

$allCompedProduct = new CommerceFixtureProduct(103, 20.0);
$allComped = new CommerceFixtureCart(array(
	'comped' => array('product_id' => 103, 'quantity' => 4, 'line_subtotal' => 0.0, 'line_total' => 0.0, 'data' => $allCompedProduct),
));
$cartService->apply_cart_item_adjustments($allComped);
$assert($cartService->get_applied_entries_from_session() === array() && $approximately($allCompedProduct->get_price(), 20.0), 'An all-comp/free cart incorrectly qualified.');

$explicitTotalZeroProduct = new CommerceFixtureProduct(103, 20.0);
$explicitTotalZero = new CommerceFixtureCart(array(
	'comped' => array('product_id' => 103, 'quantity' => 4, 'line_total' => 0.0, 'data' => $explicitTotalZeroProduct),
));
$cartService->apply_cart_item_adjustments($explicitTotalZero);
$assert($cartService->get_applied_entries_from_session() === array() && $approximately($explicitTotalZeroProduct->get_price(), 20.0), 'An explicit zero line total incorrectly fell back to catalog price.');

$absentSubtotalProduct = new CommerceFixtureProduct(101, 20.0);
$absentSubtotal = new CommerceFixtureCart(array(
	'paid' => array('product_id' => 101, 'quantity' => 4, 'data' => $absentSubtotalProduct),
));
$cartService->apply_cart_item_adjustments($absentSubtotal);
$absentEntries = $cartService->get_applied_entries_from_session();
$assert(
	$approximately($absentSubtotalProduct->get_price(), 18.0)
		&& count($absentEntries) === 1
		&& $approximately($absentEntries[0]['discount_amount'] ?? 0, 8.0),
	'Absent transaction subtotal data did not fall back to the positive live product price.'
);

$absentFreeProduct = new CommerceFixtureProduct(103, 0.0);
$absentFree = new CommerceFixtureCart(array(
	'free' => array('product_id' => 103, 'quantity' => 4, 'data' => $absentFreeProduct),
));
$cartService->apply_cart_item_adjustments($absentFree);
$assert($cartService->get_applied_entries_from_session() === array() && $approximately($absentFreeProduct->get_price(), 0.0), 'A catalog-zero line with absent transaction data incorrectly qualified.');

$discountedPositiveProduct = new CommerceFixtureProduct(101, 10.0);
$discountedPositive = new CommerceFixtureCart(array(
	'paid' => array('product_id' => 101, 'quantity' => 4, 'line_subtotal' => 40.0, 'line_total' => 40.0, 'data' => $discountedPositiveProduct),
));
$cartService->apply_cart_item_adjustments($discountedPositive);
$discountedPositiveEntries = $cartService->get_applied_entries_from_session();
$assert(
	$approximately($discountedPositiveProduct->get_price(), 9.0)
		&& count($discountedPositiveEntries) === 1
		&& $approximately($discountedPositiveEntries[0]['discount_amount'] ?? 0, 4.0),
	'A discounted-but-positive multi-quantity line did not remain paid and eligible.'
);

$remainingPaidProduct = new CommerceFixtureProduct(101, 20.0);
$removedProduct = new CommerceFixtureProduct(103, 20.0);
$canceledProduct = new CommerceFixtureProduct(103, 20.0);
$removedQuantities = new CommerceFixtureCart(array(
	'paid' => array('product_id' => 101, 'quantity' => 3, 'line_subtotal' => 60.0, 'line_total' => 60.0, 'data' => $remainingPaidProduct),
	'removed' => array('product_id' => 103, 'quantity' => 0, 'line_subtotal' => 20.0, 'line_total' => 20.0, 'data' => $removedProduct),
	'canceled' => array('product_id' => 103, 'quantity' => -1, 'line_subtotal' => 20.0, 'line_total' => 20.0, 'data' => $canceledProduct),
));
$cartService->apply_cart_item_adjustments($removedQuantities);
$assert(
	$cartService->get_applied_entries_from_session() === array()
		&& $approximately($remainingPaidProduct->get_price(), 20.0),
	'Removed/canceled nonpositive quantities incorrectly counted toward qualification.'
);

$ticketClass = $mapping->classify_cart_item(array('product_id' => 101, 'data' => new CommerceFixtureProduct(101, 20.0)));
$entitlementClass = $mapping->classify_cart_item(array('product_id' => 202, 'data' => new CommerceFixtureProduct(202, 10.0)));
$otherClass = $mapping->classify_cart_item(array('product_id' => 303, 'data' => new CommerceFixtureProduct(303, 5.0)));
$assert(($ticketClass['type'] ?? '') === 'ticket' && ($ticketClass['event_id'] ?? 0) === 501, 'Ticket-to-event mapping changed.');
$assert(($entitlementClass['type'] ?? '') === 'entitlement' && ($entitlementClass['event_id'] ?? 0) === 501, 'Entitlement-to-event mapping changed.');
$assert(($otherClass['type'] ?? '') === 'other' && ($otherClass['event_id'] ?? -1) === 0, 'Unrelated product mapping changed.');

$rules->save_event_rules(501, array($eventRule));
$rules->save_global_rules(array($eventRule));
$assert(count($GLOBALS['commerce_post_meta'][501][VMS_Discounts_Rules::EVENT_RULES_META_KEY] ?? array()) === 1, 'Admin event-rule persistence changed.');
$assert(count($GLOBALS['commerce_options'][VMS_Discounts_Rules::GLOBAL_RULES_OPTION_KEY] ?? array()) === 1, 'Global settings rule persistence changed.');

$admin = new VMS_Discounts_Admin($rules);
$settings = new VMS_Discounts_Settings($rules);
$settings->register_page();
$settings->enqueue_assets('wrong_hook');
$assert($GLOBALS['commerce_assets'] === array(), 'Commerce settings assets loaded outside their returned menu hook.');
$settings->enqueue_assets($GLOBALS['commerce_submenu_hook']);
$assetHandles = array_column($GLOBALS['commerce_assets'], 1);
$assert(in_array('vms-discounts-admin', $assetHandles, true), 'Commerce settings assets did not load on their returned menu hook.');
$assetSources = implode("\n", array_map(static fn(array $row): string => is_string($row[2] ?? null) ? $row[2] : '', $GLOBALS['commerce_assets']));
$assert(strpos($assetSources, 'tmp-vms-commerce') === false, 'Commerce assets depend on the currently active temporary basename.');
$assert(isset($GLOBALS['commerce_actions']['add_meta_boxes'], $GLOBALS['commerce_actions']['save_post'], $GLOBALS['commerce_actions']['admin_menu']), 'Commerce admin/settings hooks were not registered.');

if ($failures !== array()) {
	fwrite(STDERR, "Commerce {$expectedVersion} business-contract failures:\n- " . implode("\n- ", $failures) . "\n");
	exit(1);
}

echo "Commerce {$expectedVersion} cart/checkout/order/rules/mapping/admin/classic/Blocks and paid/comp boundary contracts passed.\n";
