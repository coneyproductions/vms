<?php
/** Disposable WordPress runtime acceptance for mandatory Outreach unsubscribe. */

defined('ABSPATH') || exit;

function backstage_outreach_unsubscribe_runtime_assert(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

if (!function_exists('backstage_outreach_send_promotional_email')) {
	throw new RuntimeException('Backstage Outreach 1.2.19 must be active.');
}

global $wpdb;
$marker = wp_generate_password(10, false, false);
$emails = array(
	'prefetch-' . $marker . '@example.test',
	'browser-' . $marker . '@example.test',
	'existing-' . $marker . '@example.test',
	'delivery-' . $marker . '@example.test',
	'transport-' . $marker . '@example.test',
	'postal-' . $marker . '@example.test',
);
$mail = array();
$transport_ready = true;
$transport_filter = static function (array $state) use (&$transport_ready): array {
	return array(
		'ready' => $transport_ready,
		'method' => $transport_ready ? 'synthetic_verified_transport' : 'synthetic_unverified_transport',
		'message' => $transport_ready ? '' : 'Synthetic transport is intentionally unverified.',
	);
};
$postal_filter = static fn(string $address): string => 'Synthetic Venue, 100 Test Way, Example, TX 75001, US';
$mail_filter = static function ($return, array $atts) use (&$mail): bool {
	$mail[] = $atts;
	return true;
};
add_filter('backstage_outreach_mail_transport_readiness', $transport_filter, PHP_INT_MAX, 1);
add_filter('backstage_outreach_postal_address', $postal_filter, PHP_INT_MAX - 1, 1);
add_filter('pre_wp_mail', $mail_filter, PHP_INT_MAX, 2);

try {
	vms_outreach_maybe_upgrade_schema();
	backstage_outreach_unsubscribe_runtime_assert(get_option(vms_outreach_db_option_key()) === '1.2.0', 'Outreach base schema did not upgrade to 1.2.0.');
	backstage_outreach_unsubscribe_runtime_assert(backstage_outreach_unsubscribe_table_ready(), 'Opaque unsubscribe token storage is unavailable.');

	$link = backstage_outreach_issue_unsubscribe_token($emails[0], array('source_type' => 'campaign_recipient', 'source_id' => 101, 'campaign_id' => 990020));
	backstage_outreach_unsubscribe_runtime_assert(is_array($link), 'Could not issue an opaque signed unsubscribe link.');
	backstage_outreach_unsubscribe_runtime_assert(!str_contains((string) $link['url'], $emails[0]) && !str_contains(rawurldecode((string) $link['url']), $emails[0]), 'Unsubscribe URL exposed the recipient email.');
	$valid = backstage_outreach_validate_unsubscribe_token((string) $link['token'], (string) $link['signature']);
	backstage_outreach_unsubscribe_runtime_assert(is_array($valid) && (string) $valid['email_norm'] === vms_outreach_normalize_email($emails[0]), 'Valid signed unsubscribe link was rejected.');
	$forged_token = substr((string) $link['token'], 0, -1) . (((string) $link['token'])[-1] === 'A' ? 'B' : 'A');
	$forged_signature = substr((string) $link['signature'], 0, -1) . (((string) $link['signature'])[-1] === 'A' ? 'B' : 'A');
	backstage_outreach_unsubscribe_runtime_assert(is_wp_error(backstage_outreach_validate_unsubscribe_token($forged_token, (string) $link['signature'])), 'Forged token was accepted.');
	backstage_outreach_unsubscribe_runtime_assert(is_wp_error(backstage_outreach_validate_unsubscribe_token((string) $link['token'], $forged_signature)), 'Forged signature was accepted.');

	$before_prefetch = vms_outreach_get_suppression_by_email($emails[0]);
	$get_state = backstage_outreach_unsubscribe_page('GET', (string) $link['token'], (string) $link['signature']);
	$after_prefetch = vms_outreach_get_suppression_by_email($emails[0]);
	backstage_outreach_unsubscribe_runtime_assert($before_prefetch === null && $after_prefetch === null && $get_state['status'] === 'confirm' && !empty($get_state['show_form']), 'GET prefetch mutated suppression or skipped confirmation.');

	$one_click = backstage_outreach_unsubscribe_page('POST', (string) $link['token'], (string) $link['signature'], array('List-Unsubscribe' => 'One-Click'));
	$suppression = vms_outreach_get_suppression_by_email($emails[0]);
	backstage_outreach_unsubscribe_runtime_assert($one_click['status'] === 'success' && $one_click['http_status'] === 200 && is_array($suppression), 'RFC 8058 POST did not immediately create suppression.');
	backstage_outreach_unsubscribe_runtime_assert((string) $suppression['scope'] === 'global_outreach' && (string) $suppression['reason'] === 'unsubscribe_request', 'RFC 8058 suppression did not use required global scope/reason.');
	$repeat = backstage_outreach_unsubscribe_page('POST', (string) $link['token'], (string) $link['signature'], array('backstage_outreach_confirm' => '1'));
	backstage_outreach_unsubscribe_runtime_assert($repeat['status'] === 'already' && str_contains((string) $repeat['message'], 'already unsubscribed'), 'Repeated unsubscribe did not return the already-unsubscribed state.');

	$browser_link = backstage_outreach_issue_unsubscribe_token($emails[1], array('source_type' => 'business_distribution', 'source_id' => 202, 'campaign_id' => 990021));
	$browser_post = backstage_outreach_unsubscribe_page('POST', (string) $browser_link['token'], (string) $browser_link['signature'], array('backstage_outreach_confirm' => '1'));
	backstage_outreach_unsubscribe_runtime_assert($browser_post['status'] === 'success' && is_array(vms_outreach_get_suppression_by_email($emails[1])), 'Browser confirmation did not create suppression.');

	$existing = vms_outreach_upsert_suppression(array('email' => $emails[2], 'scope' => 'global_outreach', 'reason' => 'manual_admin', 'notes' => 'Synthetic compatibility fixture'), 0);
	$existing_link = backstage_outreach_issue_unsubscribe_token($emails[2], array('source_type' => 'party_distribution', 'source_id' => 303, 'campaign_id' => 990022));
	$existing_post = backstage_outreach_unsubscribe_page('POST', (string) $existing_link['token'], (string) $existing_link['signature'], array('List-Unsubscribe' => 'One-Click'));
	$existing_after = vms_outreach_get_suppression_by_email($emails[2]);
	backstage_outreach_unsubscribe_runtime_assert(is_array($existing) && $existing_post['status'] === 'already' && (string) $existing_after['reason'] === 'manual_admin', 'Existing global suppression was duplicated or weakened.');

	$sent = backstage_outreach_send_promotional_email(
		$emails[3],
		'Synthetic complimentary invitation',
		"Hello Synthetic Recipient,\n\nYour invitation is ready.",
		array('Content-Type: text/plain; charset=UTF-8'),
		array('source_type' => 'campaign_recipient', 'source_id' => 404, 'campaign_id' => 990023)
	);
	backstage_outreach_unsubscribe_runtime_assert(
		$sent === true && count($mail) === 1,
		'Mocked complimentary handoff was not accepted exactly once: ' . (is_wp_error($sent) ? $sent->get_error_code() . ' — ' . $sent->get_error_message() : var_export($sent, true)) . '; captured=' . count($mail)
	);
	$captured = $mail[0];
	$captured_headers = (array) ($captured['headers'] ?? array());
	$list_header = current(array_values(array_filter($captured_headers, static fn(string $header): bool => str_starts_with($header, 'List-Unsubscribe: <'))));
	backstage_outreach_unsubscribe_runtime_assert(is_string($list_header) && in_array('List-Unsubscribe-Post: List-Unsubscribe=One-Click', $captured_headers, true), 'RFC 8058 headers were missing from mocked delivery.');
	backstage_outreach_unsubscribe_runtime_assert(str_contains((string) $captured['message'], 'Unsubscribe from all Backstage Outreach promotional email: https://') && str_contains((string) $captured['message'], 'Postal address:'), 'Automatic footer or postal address was missing.');
	preg_match('/<([^>]+)>/', $list_header, $url_match);
	parse_str((string) parse_url((string) ($url_match[1] ?? ''), PHP_URL_QUERY), $url_query);
	$delivered_row = backstage_outreach_validate_unsubscribe_token((string) ($url_query['backstage-outreach-unsubscribe'] ?? ''), (string) ($url_query['signature'] ?? ''));
	backstage_outreach_unsubscribe_runtime_assert(is_array($delivered_row) && (string) $delivered_row['source_type'] === 'campaign_recipient', 'Delivered personalized header did not resolve to its synthetic recipient context.');

	$delivery_unsubscribe = backstage_outreach_unsubscribe_page('POST', (string) $url_query['backstage-outreach-unsubscribe'], (string) $url_query['signature'], array('List-Unsubscribe' => 'One-Click'));
	$blocked = backstage_outreach_send_promotional_email($emails[3], 'Deliberate resend', 'Must not hand off.', array(), array('source_type' => 'party_distribution', 'source_id' => 405, 'campaign_id' => 990099));
	backstage_outreach_unsubscribe_runtime_assert($delivery_unsubscribe['status'] === 'success' && is_wp_error($blocked) && $blocked->get_error_code() === 'outreach_suppressed' && count($mail) === 1, 'Campaign-wide suppression did not block a deliberate cross-campaign resend at final handoff.');

	$transport_ready = false;
	$transport_blocked = backstage_outreach_send_promotional_email($emails[4], 'Blocked transport', 'Must not hand off.');
	backstage_outreach_unsubscribe_runtime_assert(is_wp_error($transport_blocked) && $transport_blocked->get_error_code() === 'outreach_mail_transport_unverified' && count($mail) === 1, 'Unverified mail transport did not fail closed.');
	$transport_ready = true;
	$empty_postal = static fn(string $address): string => '';
	add_filter('backstage_outreach_postal_address', $empty_postal, PHP_INT_MAX, 1);
	$postal_blocked = backstage_outreach_send_promotional_email($emails[5], 'Blocked postal address', 'Must not hand off.');
	remove_filter('backstage_outreach_postal_address', $empty_postal, PHP_INT_MAX);
	backstage_outreach_unsubscribe_runtime_assert(is_wp_error($postal_blocked) && $postal_blocked->get_error_code() === 'outreach_postal_address_unavailable' && count($mail) === 1, 'Missing postal address did not fail closed.');

	if (function_exists('openssl_pkey_new')) {
		$key = openssl_pkey_new(array('private_key_bits' => 1024, 'private_key_type' => OPENSSL_KEYTYPE_RSA));
		$private = '';
		backstage_outreach_unsubscribe_runtime_assert($key !== false && openssl_pkey_export($key, $private), 'Could not create a synthetic DKIM key.');
		$phpmailer = new WP_PHPMailer(true);
		$phpmailer->DKIM_domain = 'example.test';
		$phpmailer->DKIM_selector = 'synthetic';
		$phpmailer->DKIM_private_string = $private;
		$phpmailer->setFrom('sender@example.test', 'Synthetic Sender');
		$phpmailer->addAddress('recipient@example.test');
		$phpmailer->Subject = 'Synthetic RFC 8058 signing';
		$phpmailer->Body = 'Synthetic body';
		$phpmailer->addCustomHeader('List-Unsubscribe', '<https://example.test/unsubscribe>');
		$phpmailer->addCustomHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
		backstage_outreach_require_dkim_unsubscribe_headers($phpmailer);
		backstage_outreach_unsubscribe_runtime_assert($phpmailer->preSend(), 'Synthetic DKIM message could not be prepared.');
		$mime = strtolower($phpmailer->getSentMIMEMessage());
		backstage_outreach_unsubscribe_runtime_assert(str_contains($mime, 'dkim-signature:') && str_contains($mime, 'list-unsubscribe') && str_contains($mime, 'list-unsubscribe-post'), 'DKIM signature did not cover both RFC 8058 headers.');
	}

	echo "Outreach unsubscribe runtime PASS: signed opaque links, forged-link rejection, GET prefetch safety, browser/RFC POST, idempotence, global suppression, mandatory footer/headers, final-gate blocking, and fail-closed infrastructure.\n";
} finally {
	remove_filter('pre_wp_mail', $mail_filter, PHP_INT_MAX);
	remove_filter('backstage_outreach_postal_address', $postal_filter, PHP_INT_MAX - 1);
	remove_filter('backstage_outreach_mail_transport_readiness', $transport_filter, PHP_INT_MAX);
	foreach ($emails as $email) {
		$suppression = vms_outreach_get_suppression_by_email($email);
		if (is_array($suppression)) {
			vms_outreach_remove_suppression((int) $suppression['id']);
		}
		$wpdb->delete(backstage_outreach_unsubscribe_table(), array('email_norm' => vms_outreach_normalize_email($email)), array('%s'));
	}
}
