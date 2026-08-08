<?php
declare(strict_types=1);

function vms_test_disclosure_fail(string $message): void
{
	throw new RuntimeException($message);
}

function vms_test_disclosure_assert_contains(string $needle, string $haystack, string $message): void
{
	if (!str_contains($haystack, $needle)) {
		vms_test_disclosure_fail($message . "\nMissing: " . $needle);
	}
}

try {
	$plugin_root = dirname(__DIR__);
	$readme = file_get_contents($plugin_root . '/readme.txt');
	$public_profile = file_get_contents($plugin_root . '/includes/public/templates/vendor-profile.php');
	$vendor_portal = file_get_contents($plugin_root . '/includes/portal/vendor-portal.php');
	if (!is_string($readme) || !is_string($public_profile) || !is_string($vendor_portal)) {
		vms_test_disclosure_fail('Could not read the external-service source inventory.');
	}

	vms_test_disclosure_assert_contains('wp_oembed_get($video_url', $public_profile, 'Public vendor profiles should remain part of the oEmbed service inventory.');
	vms_test_disclosure_assert_contains('wp_oembed_get($external_url', $vendor_portal, 'Vendor Portal promo video rendering should remain part of the oEmbed service inventory.');
	vms_test_disclosure_assert_contains('5. Vendor-selected video and oEmbed providers', $readme, 'The public readme must name the optional oEmbed service family.');
	vms_test_disclosure_assert_contains('WordPress may request the selected URL and its provider endpoints', $readme, 'The public readme must disclose server-side oEmbed discovery contact.');
	vms_test_disclosure_assert_contains("the visitor's browser may connect directly to the selected provider", $readme, 'The public readme must disclose browser contact with rendered embed providers.');
	vms_test_disclosure_assert_contains('https://wordpress.org/documentation/article/embeds/', $readme, 'The public readme must link to the WordPress embed-provider documentation.');

	fwrite(STDOUT, "external services disclosure remediation: PASS\n");
} catch (Throwable $throwable) {
	fwrite(STDERR, "external services disclosure remediation: FAIL\n" . $throwable->getMessage() . "\n");
	exit(1);
}
