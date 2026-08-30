<?php
declare(strict_types=1);

if ($argc !== 3) {
	fwrite(STDERR, "Usage: php commerce-square-activation-fixture.php <commerce-source-dir> <no-square|square|no-woocommerce>\n");
	exit(2);
}

[$script, $sourceDir, $scenario] = $argv;
unset($script);

$sourceDir = realpath($sourceDir) ?: '';
$entryFile = $sourceDir . '/vms-commerce-discounts.php';
if ($sourceDir === '' || !is_file($entryFile)) {
	fwrite(STDERR, "Commerce fixture source is missing its entry file.\n");
	exit(2);
}
if (!in_array($scenario, array('no-square', 'square', 'no-woocommerce'), true)) {
	fwrite(STDERR, "Unknown Commerce fixture scenario: {$scenario}\n");
	exit(2);
}

$GLOBALS['commerce_fixture_actions'] = array();
$GLOBALS['commerce_fixture_filters'] = array();
$GLOBALS['commerce_fixture_activation_callback'] = null;
$GLOBALS['commerce_fixture_options'] = array();

if (!defined('ABSPATH')) {
	define('ABSPATH', dirname($sourceDir) . '/');
}

function plugin_dir_path(string $file): string
{
	return dirname($file) . '/';
}

function plugin_dir_url(string $file): string
{
	return 'https://fixture.invalid/' . basename(dirname($file)) . '/';
}

/**
 * @param callable|array<int, mixed>|string $callback
 */
function register_activation_hook(string $file, $callback): void
{
	$GLOBALS['commerce_fixture_activation_callback'] = $callback;
}

/**
 * @param callable|array<int, mixed>|string $callback
 */
function add_action(string $hook, $callback, int $priority = 10, int $acceptedArgs = 1): void
{
	$GLOBALS['commerce_fixture_actions'][$hook][] = array(
		'callback' => $callback,
		'priority' => $priority,
		'accepted_args' => $acceptedArgs,
	);
}

/**
 * @param callable|array<int, mixed>|string $callback
 */
function add_filter(string $hook, $callback, int $priority = 10, int $acceptedArgs = 1): void
{
	$GLOBALS['commerce_fixture_filters'][$hook][] = array(
		'callback' => $callback,
		'priority' => $priority,
		'accepted_args' => $acceptedArgs,
	);
}

/**
 * @param mixed $default
 * @return mixed
 */
function get_option(string $key, $default = false)
{
	return array_key_exists($key, $GLOBALS['commerce_fixture_options'])
		? $GLOBALS['commerce_fixture_options'][$key]
		: $default;
}

/**
 * @param mixed $value
 */
function update_option(string $key, $value, bool $autoload = true): bool
{
	$GLOBALS['commerce_fixture_options'][$key] = $value;
	return true;
}

function current_user_can(string $capability): bool
{
	return $capability === 'manage_options';
}

function esc_html__(string $text, string $domain = 'default'): string
{
	return $text;
}

if ($scenario !== 'no-woocommerce') {
	eval('class WooCommerce {}');
}
if ($scenario === 'square') {
	eval('namespace WooCommerce\\Square\\Gateway\\API\\Requests; class Orders {}');
}

require $entryFile;

$activationCallback = $GLOBALS['commerce_fixture_activation_callback'];
if (!is_callable($activationCallback)) {
	fwrite(STDERR, "Commerce did not register a callable activation hook.\n");
	exit(1);
}

call_user_func($activationCallback);
VMS_Discounts_Loader::instance()->boot();

$noticeHtml = '';
foreach ((array) ($GLOBALS['commerce_fixture_actions']['admin_notices'] ?? array()) as $registration) {
	if (!is_callable($registration['callback'] ?? null)) {
		continue;
	}
	ob_start();
	call_user_func($registration['callback']);
	$noticeHtml .= (string) ob_get_clean();
}

$hookCounts = array();
foreach (array('wp_ajax_vms_discounts_search_products', 'wp_ajax_vms_discounts_set_tip') as $hook) {
	$hookCounts[$hook] = count((array) ($GLOBALS['commerce_fixture_actions'][$hook] ?? array()));
}
foreach (array('wc_payment_gateway_square_credit_card_get_order', 'wc_payment_gateway_square_cash_app_pay_get_order') as $hook) {
	$hookCounts[$hook] = count((array) ($GLOBALS['commerce_fixture_filters'][$hook] ?? array()));
}

$payload = array(
	'scenario' => $scenario,
	'activation_completed' => true,
	'migration_marker' => $GLOBALS['commerce_fixture_options']['_vms_discounts_migrated'] ?? null,
	'square_bridge_declared' => class_exists('VMS_Discounts_Square_Bridge', false),
	'square_request_declared' => class_exists('VMS_Discounts_Square_Order_Request', false),
	'hook_counts' => $hookCounts,
	'notice_html' => $noticeHtml,
);

echo 'COMMERCE_SQUARE_FIXTURE_JSON=' . base64_encode((string) json_encode($payload, JSON_UNESCAPED_SLASHES)) . "\n";
