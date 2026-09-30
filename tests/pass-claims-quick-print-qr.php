<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
	define('ABSPATH', dirname(__DIR__) . '/');
}

function add_action($hook_name, $callback, $priority = 10, $accepted_args = 1): bool
{
	unset($hook_name, $callback, $priority, $accepted_args);
	return true;
}

function add_filter($hook_name, $callback, $priority = 10, $accepted_args = 1): bool
{
	unset($hook_name, $callback, $priority, $accepted_args);
	return true;
}

function home_url($path = ''): string
{
	return 'https://serenaderange.local' . (string) $path;
}

require_once dirname(__DIR__) . '/includes/modules/admissions/pass-claims.php';
require_once dirname(__DIR__) . '/includes/modules/admissions/local-qr.php';

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
	++$checks;
	if (!$condition) {
		throw new RuntimeException($message);
	}
};
$assert_png = static function (string $uri, string $message) use ($assert): void {
	$assert(str_starts_with($uri, 'data:image/png;base64,'), $message . ' should return a PNG data URI');
	$png = base64_decode(substr($uri, 22), true);
	$assert(is_string($png) && str_starts_with($png, "\x89PNG\r\n\x1a\n"), $message . ' should contain a valid PNG signature');
};

$admission_payload = 'vms-admission:' . str_repeat('a', 40);
$claim_token = str_repeat('b', 24) . '.' . str_repeat('c', 24);
$claim_url = 'https://serenaderange.local/pass/claim/' . $claim_token;

$assert_png(\BVMGR\Admissions\Local_QR::data_uri($admission_payload), 'Admissions QR payload');
$assert(\BVMGR\Admissions\Local_QR::data_uri($claim_url) === '', 'Admissions QR path must continue rejecting claim URLs');
$assert(\BVMGR\Admissions\Local_QR::claim_url_data_uri($admission_payload, home_url('/')) === '', 'Claim QR path must reject admissions bearer payloads');
$claim_qr = bvmgr_pass_claims_claim_qr_image_url($claim_url);
$assert_png($claim_qr, 'Guest Pass claim URL');
$other_claim_url = 'https://serenaderange.local/pass/claim/' . str_repeat('d', 24) . '.' . str_repeat('e', 24);
$other_claim_qr = bvmgr_pass_claims_claim_qr_image_url($other_claim_url);
$assert_png($other_claim_qr, 'Second Guest Pass claim URL');
$assert($claim_qr !== $other_claim_qr, 'Distinct claim URLs must produce distinct QR images');
$assert(bvmgr_pass_claims_claim_qr_image_url($claim_url . "\n") === '', 'Guest Pass claim helper must reject surrounding whitespace');

$subdirectory_url = 'https://example.test/wordpress/pass/claim/' . $claim_token;
$assert_png(\BVMGR\Admissions\Local_QR::claim_url_data_uri($subdirectory_url, 'https://example.test/wordpress/'), 'Subdirectory Guest Pass claim URL');

$invalid_claim_urls = array(
	'https://other.example/pass/claim/' . $claim_token,
	'https://user@serenaderange.local/pass/claim/' . $claim_token,
	'https://serenaderange.local:444/pass/claim/' . $claim_token,
	'https://serenaderange.local/pass/claim/' . $claim_token . '?source=print',
	'https://serenaderange.local/pass/claim/' . $claim_token . '#claim',
	'https://serenaderange.local/not-a-claim/' . $claim_token,
	'https://serenaderange.local/pass/claim/short.' . str_repeat('c', 24),
	'https://serenaderange.local/pass/claim/' . str_repeat('b', 24) . '.' . str_repeat('c', 23),
	"https://serenaderange.local/pass/claim/{$claim_token}\n",
	'javascript:alert(1)',
);
foreach ($invalid_claim_urls as $invalid_claim_url) {
	$assert(
		\BVMGR\Admissions\Local_QR::claim_url_data_uri($invalid_claim_url, home_url('/')) === '',
		'Unsafe or malformed claim URL must fail closed: ' . json_encode($invalid_claim_url)
	);
}

$source = file_get_contents(dirname(__DIR__) . '/includes/modules/admissions/pass-claims.php');
$assert(is_string($source) && str_contains($source, '$qr_url = bvmgr_pass_claims_claim_qr_image_url($claim_url);'), 'Quick Print handler must use the claim-only QR path');
$assert(is_string($source) && !str_contains($source, '$qr_url = bvmgr_pass_claims_qr_image_url($claim_url);'), 'Quick Print handler must not use the admissions QR path');

fwrite(STDOUT, 'PASS: ' . $checks . " Guest Pass Quick Print QR checks\n");
