<?php

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
define('VMSEB_URL', 'https://example.test/wp-content/plugins/vms-express-bar/');
define('VMSEB_VERSION', '0.6.30');

function add_filter(...$args): void { unset($args); }
function add_action(...$args): void { unset($args); }
function absint($value): int { return abs((int) $value); }
function get_option(string $key, $default = false) {
    if ($key === 'vmseb_settings') return array('tips_enabled' => 1, 'tips_max_custom_amount' => 100);
    if ($key === 'vmseb_bar_menu_defaults') return array();
    return $default;
}
function wc_get_price_decimals(): int { return 2; }
function post_type_exists(string $type): bool { unset($type); return false; }

require dirname(__DIR__) . '/includes/helpers.php';
require dirname(__DIR__) . '/includes/tips.php';

final class FakeProduct { public function get_price(): string { return '99'; } }
final class FakeCart {
    public function get_cart(): array {
        return array(
            array('_vms_express_bar' => 1, 'line_total' => 4.00, 'quantity' => 1, 'data' => new FakeProduct()),
            array('product_id' => 999, 'line_total' => 20.00, 'quantity' => 1, 'data' => new FakeProduct()),
        );
    }
}

function check(bool $condition, string $message): void {
    if (!$condition) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
}

$cart = new FakeCart();
check(vmseb_tips_cart_has_eligible_items($cart) === true, 'Express Bar item was not recognized');
check(abs(vmseb_tips_eligible_subtotal($cart) - 4.00) < 0.0001, 'tip base included non-Express-Bar items');
check(abs(vmseb_tips_calculate_amount($cart, 'pct:25') - 1.00) < 0.0001, '25% tip calculation is wrong');
check(vmseb_tips_normalize_selection('pct:99') === 'none', 'invalid percentage was accepted');
check(vmseb_tips_normalize_selection('custom:999') === 'custom:100.00', 'custom tip safety cap failed');

fwrite(STDOUT, "PASS: Express Bar tips 0.6.30\n");
