<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$public = file_get_contents($root . '/includes/public.php');
$css = file_get_contents($root . '/assets/css/public.css');
$js = file_get_contents($root . '/assets/js/public.js');
if ($public === false || $css === false || $js === false) {
    fwrite(STDERR, "FAIL: unable to read Express Bar source assets\n");
    exit(1);
}

function check(bool $condition, string $message): void {
    if (!$condition) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
}

check(strpos($public, 'WC()->cart->get_cart_contents_count()') !== false, 'real WooCommerce cart count is not read');
check(strpos($public, 'WC()->cart->get_cart_total()') !== false, 'real WooCommerce cart total is not read');
check(strpos($public, 'data-vmseb-cart-count=') !== false, 'cart count is not exposed to the purchase bar');
check(strpos($public, 'data-vmseb-cart-url=') !== false, 'cart URL is not exposed to the purchase bar');
check(strpos($public, 'data-vmseb-ordering-open=') !== false, 'ordering-window state is not exposed to the purchase bar');
check(strpos($public, 'vmseb-purchase-bar__cart') !== false, 'persistent cart count/total display missing');
check(strpos($public, '>Review Order</button>') !== false, 'persistent Review Order label missing');

$emptyQuantities = strpos($public, 'if (empty($quantities))');
$windowGate = strpos($public, "if (empty(\$cfg['enabled']) || empty(\$window['is_open']))", $emptyQuantities ?: 0);
check($emptyQuantities !== false && $windowGate !== false && $emptyQuantities < $windowGate, 'existing-cart redirect must run before the Express Bar ordering-window gate');
check(strpos($public, "if (\$target === 'cart' && WC()->cart->get_cart_contents_count() > 0)", $emptyQuantities) !== false, 'server-side empty-selection cart redirect missing');
check(strpos($public, "class_exists('WC_Cart_Session')") !== false, 'late admin-post cart bootstrap is not hydrated from the WooCommerce session');
check(strpos($public, '$cart_session->get_cart_from_session();') !== false, 'late WooCommerce cart session hydration call is missing');

check(strpos($css, '.vmseb-builder-section--summary{grid-area:summary;align-self:start}') !== false, 'top summary is not in normal flow');
check(strpos($css, '.vmseb-builder-section--summary{grid-area:summary;position:sticky') === false, 'top summary remains sticky');
check(strpos($css, '.vmseb-mobile-sticky-action{display:flex;position:fixed') !== false, 'purchase bar is not persistent on all viewport sizes');
check(strpos($css, 'bottom:0') !== false, 'purchase bar is not bottom anchored');
check(strpos($css, 'safe-area-inset-bottom') !== false, 'purchase bar does not account for mobile safe area');
check(strpos($css, 'z-index:80') !== false, 'purchase bar navigation-safe stacking level changed');

check(strpos($js, 'const cartCount = mobileAction ?') !== false, 'client cart state binding missing');
check(strpos($js, 'const enabled = cartCount > 0 || (orderingOpen && totalCount > 0);') !== false, 'Review Order enablement does not include the existing cart');
check(strpos($js, 'if (pickupInput) pickupInput.required = totalCount > 0;') !== false, 'pickup-name browser validation is not limited to new Express Bar selections');
check(strpos($js, 'window.location.assign(cartUrl);') !== false, 'existing-cart Review Order navigation missing');
check(strpos($js, "mobileAction.classList.toggle('is-outside-shell', !visible)") !== false, 'purchase bar shell-boundary visibility guard missing');

fwrite(STDOUT, "PASS: persistent Review Order cart state 0.6.39\n");
