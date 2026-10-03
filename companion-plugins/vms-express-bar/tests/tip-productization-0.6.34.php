<?php

declare(strict_types=1);

$source = file_get_contents(dirname(__DIR__) . '/includes/tips.php');
if ($source === false) {
    fwrite(STDERR, "FAIL: could not read tips.php\n");
    exit(1);
}

$checks = array(
    "woocommerce_checkout_order_created', 'vmseb_tips_convert_fee_to_product_line" => 'order-created conversion hook missing',
    "VMS-ONLINE-TIP-CARRIER" => 'protected tip carrier SKU missing',
    "_vms_product_role', 'online_tip'" => 'online_tip product role missing',
    "new WC_Order_Item_Product()" => 'tip is not converted to product order line',
    "_vmseb_online_tip', 'yes'" => 'tip order-line marker missing',
    "set_total(\$original_total)" => 'order-total preservation guard missing',
);

foreach ($checks as $needle => $message) {
    if (strpos($source, $needle) === false) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

fwrite(STDOUT, "PASS: Express Bar tip productization 0.6.34\n");
