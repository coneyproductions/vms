<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
	define('ABSPATH', dirname(__DIR__) . '/');
}

$test_custom_logo_id = 11344;
$test_custom_logo_is_image = true;
$test_custom_logo_src = array('https://example.test/uploads/site-logo-768x320.png', 768, 320, true);
$test_requested_logo_size = '';

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

function get_bloginfo($show = ''): string
{
	unset($show);
	return 'Example Venue';
}

function __($text, $domain = ''): string
{
	unset($domain);
	return (string) $text;
}

function absint($value): int
{
	return abs((int) $value);
}

function get_theme_mod($name, $default = false)
{
	global $test_custom_logo_id;
	return $name === 'custom_logo' ? $test_custom_logo_id : $default;
}

function wp_attachment_is_image($attachment_id): bool
{
	global $test_custom_logo_id, $test_custom_logo_is_image;
	return (int) $attachment_id === (int) $test_custom_logo_id && $test_custom_logo_is_image;
}

function wp_get_attachment_image_src($attachment_id, $size = 'thumbnail')
{
	global $test_custom_logo_id, $test_custom_logo_src, $test_requested_logo_size;
	$test_requested_logo_size = (string) $size;
	return (int) $attachment_id === (int) $test_custom_logo_id ? $test_custom_logo_src : false;
}

function esc_url_raw($url, $protocols = null): string
{
	unset($protocols);
	return preg_match('#\Ahttps?://#', (string) $url) === 1 ? (string) $url : '';
}

require_once dirname(__DIR__) . '/includes/modules/admissions/pass-claims.php';

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
	++$checks;
	if (!$condition) {
		throw new RuntimeException($message);
	}
};

$branding = bvmgr_pass_claims_print_branding();
$assert($branding['site_name'] === 'Example Venue', 'Site name should remain available for alt text and fallback');
$assert($branding['logo_url'] === 'https://example.test/uploads/site-logo-768x320.png', 'Valid custom logo URL should be returned');
$assert($branding['logo_width'] === 768 && $branding['logo_height'] === 320, 'Intrinsic custom logo dimensions should be retained');
$assert($branding['logo_size'] === 'medium_large', 'Custom logo should use the medium_large WordPress image size');
$assert($test_requested_logo_size === 'medium_large', 'Resolver should request the medium_large WordPress image size');

$test_custom_logo_is_image = false;
$branding = bvmgr_pass_claims_print_branding();
$assert($branding['logo_url'] === '', 'Non-image custom logo should fall back to the site name');
$assert($branding['site_name'] === 'Example Venue', 'Non-image fallback should preserve the site name');

$test_custom_logo_is_image = true;
$test_custom_logo_src = array('javascript:alert(1)', 768, 320, true);
$branding = bvmgr_pass_claims_print_branding();
$assert($branding['logo_url'] === '', 'Unsafe custom logo URL should fall back to the site name');

$test_custom_logo_src = array('https://example.test/uploads/site-logo.png', 0, 320, true);
$branding = bvmgr_pass_claims_print_branding();
$assert($branding['logo_url'] === '', 'Logo without usable dimensions should fall back to the site name');

$source = file_get_contents(dirname(__DIR__) . '/includes/modules/admissions/pass-claims.php');
$assert(is_string($source) && str_contains($source, 'if ((string) $branding[\'logo_url\'] !== \'\')'), 'Print page should conditionally render the logo');
$assert(is_string($source) && str_contains($source, '<div class="venue">'), 'Print page should retain the site-name fallback');
$assert(is_string($source) && str_contains($source, 'max-width:3in;max-height:.9in;width:auto;height:auto'), 'Print page should constrain the logo without stretching');

fwrite(STDOUT, 'PASS: ' . $checks . " Guest Pass Quick Print branding checks\n");
