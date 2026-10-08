<?php
/** Worker for outreach-party-foundation-concurrency.php. */

defined('ABSPATH') || exit;
$fixture = get_option(sanitize_key((string) ($args[0] ?? '')), array());
$side = sanitize_key((string) ($args[1] ?? ''));
if (!is_array($fixture) || !in_array($side, array('a', 'b'), true)) {
	throw new RuntimeException('Party race fixture is unavailable.');
}
while (microtime(true) < (float) $fixture['start_at']) {
	usleep(1000);
}
$result = backstage_outreach_party_confirm_legacy_link(
	absint($fixture['party_' . $side]),
	'campaign_recipient',
	absint($fixture['recipient_id']),
	(string) $fixture['snapshot_hash'],
	array('Concurrent explicit operator review'),
	absint($fixture['user_id']),
	hash('sha256', (string) $fixture['marker'] . '|' . $side)
);
echo 'PARTY_RACE:' . (is_wp_error($result) ? 'blocked:' . $result->get_error_code() : 'won:' . absint($result['party_id'])) . "\n";
