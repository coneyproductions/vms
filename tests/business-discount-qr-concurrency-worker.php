<?php
/** @var array<int, string> $args */
defined('ABSPATH') || exit;

$mode = sanitize_key((string) ($args[0] ?? ''));
$fixture_key = sanitize_key((string) ($args[1] ?? ''));
$fixture = $fixture_key !== '' ? get_option($fixture_key, array()) : array();
if (!is_array($fixture) || !in_array($mode, array('paid', 'free'), true)) {
	echo "RACE_RESULT:lost:invalid worker fixture\n";
	return;
}

$start_at = (float) ($fixture['start_at'] ?? 0);
while (microtime(true) < $start_at) {
	usleep(1000);
}

if ($mode === 'paid') {
	try {
		backstage_outreach_discount_reserve_order(new WC_Order(absint($fixture['order_id'] ?? 0)));
		echo "RACE_RESULT:won\n";
	} catch (Throwable $error) {
		echo 'RACE_RESULT:lost:' . sanitize_text_field($error->getMessage()) . "\n";
	}
	return;
}

$context = backstage_outreach_distribution_context((string) ($fixture['free_token'] ?? ''));
$event = bvmgr_pass_claims_get_event_plan_brief(absint($fixture['event_plan_id'] ?? 0));
$result = is_array($context) && is_array($event) ? backstage_outreach_partner_claim($context, $event, array(
	'first_name' => 'Free', 'last_name' => 'Racer', 'phone' => '3125550199', 'email' => '', 'party_size' => 1, 'opt_in' => 0,
), 'shared-last-slot-race') : new WP_Error('fixture_reload_failed', 'Fixture reload failed.');
echo is_wp_error($result) ? 'RACE_RESULT:lost:' . sanitize_text_field($result->get_error_message()) . "\n" : "RACE_RESULT:won\n";
