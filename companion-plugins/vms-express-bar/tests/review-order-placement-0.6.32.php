<?php

declare(strict_types=1);

$source = file_get_contents(dirname(__DIR__) . '/includes/public.php');
if ($source === false) {
    fwrite(STDERR, "FAIL: unable to read includes/public.php\n");
    exit(1);
}

function check(bool $condition, string $message): void {
    if (!$condition) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
}

$summaryStart = strpos($source, 'class="vmseb-builder-section vmseb-builder-section--summary"');
$statusStart = strpos($source, 'class="vmseb-builder-section vmseb-builder-section--status"');
check($summaryStart !== false && $statusStart !== false && $statusStart > $summaryStart, 'summary/status sections not found');
$summary = substr($source, $summaryStart, $statusStart - $summaryStart);
check(strpos($summary, 'data-vmseb-submit=') === false, 'top summary still contains a submit CTA');

$footerStart = strpos($source, 'class="vmseb-footer-actions');
check($footerStart !== false, 'footer action area not found');
$mobileActionStart = strpos($source, 'class="vmseb-mobile-sticky-action', $footerStart);
$footerEnd = $mobileActionStart !== false ? $mobileActionStart : ($footerStart + 900);
$footer = substr($source, $footerStart, $footerEnd - $footerStart);
check(substr_count($footer, 'data-vmseb-submit="cart"') === 1, 'footer Review Order action missing or duplicated');
check(substr_count($footer, 'data-vmseb-submit="checkout"') === 1, 'footer Checkout action missing or duplicated');

fwrite(STDOUT, "PASS: review order placement 0.6.32\n");
