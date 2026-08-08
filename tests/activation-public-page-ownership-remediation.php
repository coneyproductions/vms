<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
	define('ABSPATH', dirname(__DIR__) . '/');
}
if (!defined('OBJECT')) {
	define('OBJECT', 'OBJECT');
}

final class WP_Post
{
	public function __construct(
		public int $ID,
		public string $post_status,
		public string $post_content
	) {
	}
}

function vms_test_fail(string $message): void
{
	throw new RuntimeException($message);
}

/**
 * @param mixed $expected
 * @param mixed $actual
 */
function vms_test_assert_same($expected, $actual, string $message): void
{
	if ($expected !== $actual) {
		vms_test_fail(
			$message
			. "\nExpected: " . var_export($expected, true)
			. "\nActual: " . var_export($actual, true)
		);
	}
}

function vms_test_reset(?WP_Post $page = null): void
{
	$GLOBALS['vms_test_page'] = $page;
	$GLOBALS['vms_test_options'] = array();
	$GLOBALS['vms_test_post_meta'] = array();
	$GLOBALS['vms_test_post_updates'] = array();
	$GLOBALS['vms_test_post_inserts'] = array();
	$GLOBALS['vms_test_next_post_id'] = 901;
}

function add_action(...$args): void
{
	unset($args);
}

function sanitize_title(string $value): string
{
	return strtolower(trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $value), '-'));
}

function sanitize_text_field(string $value): string
{
	return trim(strip_tags($value));
}

function sanitize_key(string $value): string
{
	return strtolower((string) preg_replace('/[^a-z0-9_-]/', '', $value));
}

function absint($value): int
{
	return abs((int) $value);
}

function get_page_by_path(string $slug, string $output = OBJECT, string $post_type = 'page')
{
	unset($slug, $output, $post_type);
	return $GLOBALS['vms_test_page'];
}

function get_option(string $key, $default = false)
{
	return array_key_exists($key, $GLOBALS['vms_test_options'])
		? $GLOBALS['vms_test_options'][$key]
		: $default;
}

function get_post_meta(int $post_id, string $key, bool $single = false)
{
	unset($single);
	return $GLOBALS['vms_test_post_meta'][$post_id][$key] ?? '';
}

function update_post_meta(int $post_id, string $key, $value): bool
{
	$GLOBALS['vms_test_post_meta'][$post_id][$key] = $value;
	return true;
}

function has_shortcode(string $content, string $tag): bool
{
	return str_contains($content, '[' . $tag)
		&& preg_match('/\[' . preg_quote($tag, '/') . '(?:\s|\]|\/)/', $content) === 1;
}

function wp_update_post(array $postarr)
{
	$GLOBALS['vms_test_post_updates'][] = $postarr;
	return (int) ($postarr['ID'] ?? 0);
}

function wp_insert_post(array $postarr, bool $wp_error = false)
{
	unset($wp_error);
	$GLOBALS['vms_test_post_inserts'][] = $postarr;
	return (int) $GLOBALS['vms_test_next_post_id'];
}

function is_wp_error($value): bool
{
	return false;
}

try {
	require_once dirname(__DIR__) . '/includes/activation.php';

	$spec = array(
		'slug' => 'vendor-portal',
		'title' => 'Vendor Portal',
		'content' => "[vms_vendor_portal]\n",
		'managed_key' => 'vendor_portal',
	);

	vms_test_reset(new WP_Post(10, 'publish', 'An unrelated company portal.'));
	$collision_id = vms_ensure_page_exists($spec);
	vms_test_assert_same(0, $collision_id, 'Activation must not adopt an unrelated page with a colliding slug.');
	vms_test_assert_same(array(), $GLOBALS['vms_test_post_updates'], 'A colliding page must not be rewritten.');
	vms_test_assert_same(array(), $GLOBALS['vms_test_post_meta'], 'A colliding page must not receive plugin ownership metadata.');

	vms_test_reset(new WP_Post(11, 'publish', 'Welcome [vms_vendor_portal]'));
	$recognized_id = vms_ensure_page_exists($spec);
	vms_test_assert_same(11, $recognized_id, 'A page already containing the required shortcode should be adopted.');
	vms_test_assert_same(array(), $GLOBALS['vms_test_post_updates'], 'Normal activation must not rewrite an adopted page.');
	vms_test_assert_same(
		'vendor_portal',
		$GLOBALS['vms_test_post_meta'][11]['_vms_managed_public_page'] ?? null,
		'An adopted shortcode page should receive a durable ownership marker.'
	);

	vms_test_reset(new WP_Post(12, 'publish', 'Operator-customized portal content.'));
	$GLOBALS['vms_test_options']['vms_page_vendor_portal'] = 12;
	$customized_id = vms_ensure_page_exists($spec);
	vms_test_assert_same(12, $customized_id, 'The stored plugin page should remain recognized after operator customization.');
	vms_test_assert_same(array(), $GLOBALS['vms_test_post_updates'], 'Activation must preserve managed page title, content, and status.');

	vms_test_reset(new WP_Post(13, 'trash', 'Operator-customized portal content.'));
	$GLOBALS['vms_test_options']['vms_page_vendor_portal'] = 13;
	$repair_spec = $spec;
	$repair_spec['repair_existing'] = true;
	$repaired_id = vms_ensure_page_exists($repair_spec);
	vms_test_assert_same(13, $repaired_id, 'Explicit repair should retain the managed page ID.');
	vms_test_assert_same(
		array(
			'ID' => 13,
			'post_title' => 'Vendor Portal',
			'post_content' => "[vms_vendor_portal]\n",
			'post_status' => 'draft',
		),
		$GLOBALS['vms_test_post_updates'][0] ?? null,
		'Explicit repair should restore only a recognized managed page.'
	);

	vms_test_reset();
	$new_id = vms_ensure_page_exists($spec);
	vms_test_assert_same(901, $new_id, 'A missing required public page should still be created.');
	vms_test_assert_same('publish', $GLOBALS['vms_test_post_inserts'][0]['post_status'] ?? null, 'New-page publication behavior changed unexpectedly.');
	vms_test_assert_same(
		'vendor_portal',
		$GLOBALS['vms_test_post_meta'][901]['_vms_managed_public_page'] ?? null,
		'New plugin pages should receive the ownership marker.'
	);

	fwrite(STDOUT, "activation public-page ownership remediation: PASS\n");
} catch (Throwable $throwable) {
	fwrite(STDERR, "activation public-page ownership remediation: FAIL\n" . $throwable->getMessage() . "\n");
	exit(1);
}
