<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$plugin = (string) file_get_contents($root . '/companion-plugins/backstage-outreach/backstage-outreach.php');
$bootstrap = (string) file_get_contents($root . '/companion-plugins/backstage-outreach/includes/outreach/outreach.php');
$db = (string) file_get_contents($root . '/companion-plugins/backstage-outreach/includes/outreach/db.php');
$unsubscribe = (string) file_get_contents($root . '/companion-plugins/backstage-outreach/includes/outreach/unsubscribe.php');
$recipients = (string) file_get_contents($root . '/companion-plugins/backstage-outreach/includes/admissions/outreach-recipients.php');
$business = (string) file_get_contents($root . '/companion-plugins/backstage-outreach/includes/business-distribution.php');
$party = (string) file_get_contents($root . '/companion-plugins/backstage-outreach/includes/party-bulk-workflows.php');

$checks = array(
	'Focused release is Outreach 1.2.19.1' => str_contains($plugin, 'Version: 1.2.19.1') && str_contains($plugin, "BACKSTAGE_OUTREACH_VERSION', '1.2.19.1'"),
	'Unsubscribe runtime loads after suppression and before send paths' => strpos($bootstrap, "'/suppression.php'") < strpos($bootstrap, "'/unsubscribe.php'") && str_contains($bootstrap, "'/unsubscribe.php'"),
	'Base schema adds only an opaque token ledger' => str_contains($db, "return '1.2.0';") && str_contains($db, 'CREATE TABLE {$unsubscribe_tokens}') && str_contains($db, 'UNIQUE KEY token_hash') && !str_contains($db, 'unsubscribe_email_token'),
	'Links use random opaque tokens plus a durable HMAC signature' => str_contains($unsubscribe, 'random_bytes(32)') && str_contains($unsubscribe, "hash_hmac('sha256', 'v1|' . \$token") && str_contains($unsubscribe, "hash('sha256', \$token)"),
	'Public links contain no recipient email parameter' => !str_contains($unsubscribe, "'email' => \$email,\n\t\t\t),\n\t\t\thome_url") && str_contains($unsubscribe, "'backstage-outreach-unsubscribe' => \$token"),
	'GET only validates and renders confirmation state' => str_contains($unsubscribe, "if (\$method === 'GET')") && strpos($unsubscribe, "if (\$method === 'GET')") < strpos($unsubscribe, 'backstage_outreach_confirm_unsubscribe($row)'),
	'Browser and RFC one-click POST are explicit confirmation paths' => str_contains($unsubscribe, "\$post['List-Unsubscribe']") && str_contains($unsubscribe, "hash_equals('One-Click'") && str_contains($unsubscribe, "\$post['backstage_outreach_confirm']"),
	'Confirmed opt-out uses the existing global suppression API and reason' => str_contains($unsubscribe, 'vms_outreach_upsert_suppression') && str_contains($unsubscribe, "'reason' => 'unsubscribe_request'") && str_contains($unsubscribe, 'vms_outreach_default_suppression_scope'),
	'Existing suppression returns an understandable idempotent state' => str_contains($unsubscribe, "'status' => 'already'") && str_contains($unsubscribe, 'already unsubscribed'),
	'Public response is no-store responsive and does not redirect' => str_contains($unsubscribe, 'Cache-Control: no-store') && str_contains($unsubscribe, '@media(max-width:390px)') && !str_contains($unsubscribe, 'wp_redirect(') && !str_contains($unsubscribe, 'wp_safe_redirect('),
	'Every promotional handoff uses the mandatory wrapper' => substr_count($recipients, 'backstage_outreach_send_promotional_email(') === 1 && substr_count($business, 'backstage_outreach_send_promotional_email(') === 1 && substr_count($party, 'backstage_outreach_send_promotional_email(') === 1,
	'Only the mandatory wrapper calls wp_mail' => substr_count($unsubscribe, 'wp_mail(') === 1 && !str_contains($recipients, 'wp_mail(') && !str_contains($business, 'wp_mail(') && !str_contains($party, 'wp_mail('),
	'Footer is mandatory while RFC 8058 headers require verified signing' => str_contains($unsubscribe, 'backstage_outreach_promotional_footer') && str_contains($unsubscribe, 'if ($advertise_rfc8058)') && str_contains($unsubscribe, 'signs_rfc8058_headers') && str_contains($unsubscribe, 'List-Unsubscribe: <') && str_contains($unsubscribe, 'List-Unsubscribe-Post: List-Unsubscribe=One-Click'),
	'PHPMailer is required to sign both RFC headers' => str_contains($unsubscribe, "array('List-Unsubscribe', 'List-Unsubscribe-Post')") && str_contains($unsubscribe, 'DKIM_extraHeaders') && str_contains($unsubscribe, 'openssl_pkey_get_private'),
	'Unverified transport permits web unsubscribe unless policy requires RFC 8058' => str_contains($unsubscribe, "apply_filters('backstage_outreach_require_rfc8058', false") && str_contains($unsubscribe, '$require_rfc8058 && !$advertise_rfc8058'),
	'Final suppression check and recipient lock precede wp_mail' => substr_count($unsubscribe, 'vms_outreach_email_is_suppressed($email)') >= 2 && strpos($unsubscribe, 'SELECT GET_LOCK') < strpos($unsubscribe, 'wp_mail('),
	'Unavailable endpoint signing storage suppression and postal address fail closed' => str_contains($unsubscribe, 'outreach_unsubscribe_endpoint_unavailable') && str_contains($unsubscribe, 'unsubscribe_storage_unavailable') && str_contains($unsubscribe, 'outreach_suppression_unavailable') && str_contains($unsubscribe, 'outreach_postal_address_unavailable'),
	'MailPoet subscriber state is not referenced or mutated' => stripos($unsubscribe . $recipients . $business . $party, 'mailpoet') === false,
);

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
if ($failed) {
	fwrite(STDERR, "Outreach unsubscribe source checks failed:\n- " . implode("\n- ", $failed) . "\n");
	exit(1);
}

echo 'Outreach unsubscribe source PASS (' . count($checks) . " assertions)\n";
