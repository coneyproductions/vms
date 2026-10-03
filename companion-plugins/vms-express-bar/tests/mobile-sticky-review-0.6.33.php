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

check(strpos($public, 'data-vmseb-mobile-action') !== false, 'mobile sticky action wrapper missing');
check(strpos($public, 'data-vmseb-mobile-review') !== false, 'mobile Review order button missing');
check(strpos($public, 'data-vmseb-mobile-review <?php disabled(!$cart_has_items); ?>') !== false, 'Review Order initial cart-state gate missing');
check(strpos($css, '.vmseb-mobile-sticky-action{display:flex;position:fixed') !== false, 'persistent fixed-bottom CTA rule missing');
check(strpos($css, 'position:fixed') !== false && strpos($css, 'bottom:0') !== false, 'mobile sticky CTA fixed-bottom rule missing');
check(strpos($css, '.vmseb-footer-actions{display:none}') !== false, 'mobile inline footer actions are not hidden');
check(strpos($css, 'safe-area-inset-bottom') !== false, 'mobile safe-area handling missing');
check(strpos($js, "const mobileReviewBtn = shell.querySelector('[data-vmseb-mobile-review]')") !== false, 'mobile CTA JS binding missing');
check(strpos($js, 'const enabled = cartCount > 0 || (orderingOpen && totalCount > 0);') !== false, 'mobile CTA cart/selection-state sync missing');

fwrite(STDOUT, "PASS: mobile sticky Review order 0.6.33\n");
