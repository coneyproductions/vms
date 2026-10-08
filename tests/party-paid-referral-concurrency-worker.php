<?php
/** @var array<int,string> $args */
defined('ABSPATH') || exit;

$fixture_key = sanitize_key((string) ($args[0] ?? ''));
$index = absint($args[1] ?? 0);
$fixture = $fixture_key !== '' ? get_option($fixture_key, array()) : array();
if (!is_array($fixture) || empty($fixture['order_ids'][$index])) {
	echo "PARTY_RACE:lost:invalid fixture\n";
	return;
}
while (microtime(true) < (float) ($fixture['start_at'] ?? 0)) {
	usleep(1000);
}
try {
	backstage_outreach_discount_reserve_order(new WC_Order(absint($fixture['order_ids'][$index])));
	echo "PARTY_RACE:won\n";
} catch (Throwable $error) {
	echo 'PARTY_RACE:lost:' . sanitize_text_field($error->getMessage()) . "\n";
}
