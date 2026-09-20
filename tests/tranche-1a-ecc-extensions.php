<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);

$GLOBALS['t1a_ecc_actions'] = array();
$GLOBALS['t1a_ecc_filters'] = array();
$GLOBALS['t1a_ecc_assets'] = 0;
$GLOBALS['t1a_ecc_renders'] = 0;
$GLOBALS['t1a_ecc_context'] = array();
$GLOBALS['t1a_ecc_caps'] = array('manage_options' => true, 'forbidden_cap' => false);

function t1a_ecc_assert(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function t1a_ecc_same($expected, $actual, string $message): void
{
	t1a_ecc_assert($expected === $actual, $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
}

function absint($value): int
{
	return abs((int) $value);
}

function sanitize_key(string $value): string
{
	return preg_replace('/[^a-z0-9_-]/', '', strtolower($value)) ?? '';
}

function sanitize_text_field(string $value): string
{
	return trim(strip_tags($value));
}

function esc_html(string $value): string
{
	return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function esc_attr(string $value): string
{
	return esc_html($value);
}

function __(string $value, string $domain = ''): string
{
	unset($domain);
	return $value;
}

function esc_html__(string $value, string $domain = ''): string
{
	return esc_html(__($value, $domain));
}

function wp_unslash($value)
{
	return $value;
}

function add_action(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): void
{
	$GLOBALS['t1a_ecc_actions'][$hook][$priority][] = array($callback, $accepted_args);
}

function do_action(string $hook, ...$args): void
{
	$callbacks = $GLOBALS['t1a_ecc_actions'][$hook] ?? array();
	ksort($callbacks);
	foreach ($callbacks as $rows) {
		foreach ($rows as [$callback, $accepted_args]) {
			$callback(...array_slice($args, 0, $accepted_args));
		}
	}
}

function add_filter(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): void
{
	$GLOBALS['t1a_ecc_filters'][$hook][$priority][] = array($callback, $accepted_args);
}

function apply_filters(string $hook, $value, ...$args)
{
	$callbacks = $GLOBALS['t1a_ecc_filters'][$hook] ?? array();
	ksort($callbacks);
	foreach ($callbacks as $rows) {
		foreach ($rows as [$callback, $accepted_args]) {
			$value = $callback(...array_slice(array_merge(array($value), $args), 0, $accepted_args));
		}
	}
	return $value;
}

function get_post_meta(int $post_id, string $key, bool $single = false)
{
	if ($post_id === 501 && $key === '_vms_venue_id') {
		return 20;
	}
	return $single ? '' : array();
}

function current_user_can(string $capability): bool
{
	return !empty($GLOBALS['t1a_ecc_caps'][$capability]);
}

function bvmgr_current_user_can_operate_context(string $capability, int $event_plan_id = 0, int $venue_id = 0, array $context = array()): bool
{
	unset($event_plan_id, $venue_id, $context);
	return current_user_can($capability);
}

function bvmgr_request_read_key(array $source, string $key): string
{
	return isset($source[$key]) && !is_array($source[$key]) ? sanitize_key((string) $source[$key]) : '';
}

function bvmgr_request_read_absint(array $source, string $key): int
{
	return isset($source[$key]) && !is_array($source[$key]) ? absint($source[$key]) : 0;
}

function bvmgr_event_command_center_render_metric(string $label, string $value, string $sub = ''): void
{
	echo '<metric label="' . esc_attr($label) . '" value="' . esc_attr($value) . '" sub="' . esc_attr($sub) . '"></metric>';
}

require dirname(__DIR__) . '/includes/core/ecc-extensions.php';

add_action('bvmgr_ecc_register_extensions', static function (): void {
	bvmgr_register_ecc_extension(array(
		'id' => 'queue',
		'title' => 'Live Queue',
		'order' => 20,
		'capability' => 'manage_options',
		'source' => 'test-addon',
		'summary_callback' => static function (array $context): array {
			$GLOBALS['t1a_ecc_context'] = $context;
			return array('label' => 'Open', 'value' => '3', 'sub' => 'Event ' . $context['event_plan_id']);
		},
		'render_callback' => static function (): void {
			$GLOBALS['t1a_ecc_renders']++;
			echo '<p>Queue body</p>';
		},
		'assets_callback' => static function (): void {
			$GLOBALS['t1a_ecc_assets']++;
		},
	));
	bvmgr_register_ecc_extension(array(
		'id' => 'empty',
		'title' => 'Empty Panel',
		'order' => 10,
		'render_callback' => static function (): void {
			echo '<p>Must not render</p>';
		},
		'empty_callback' => static fn(): bool => true,
		'empty_message' => 'No requests yet.',
	));
	bvmgr_register_ecc_extension(array(
		'id' => 'error',
		'title' => 'Error Panel',
		'order' => 30,
		'render_callback' => static function (): void {
			throw new RuntimeException('Expected test failure');
		},
		'error_message' => 'Panel unavailable.',
	));
	bvmgr_register_ecc_extension(array(
		'id' => 'denied',
		'title' => 'Denied Panel',
		'order' => 5,
		'capability' => 'forbidden_cap',
		'render_callback' => static function (): void {
			echo '<p>Denied body</p>';
		},
	));
});

add_filter('bvmgr_ecc_extensions', static fn(): array => array());
ob_start();
bvmgr_ecc_render_registered_extensions(501, array('header' => array('label' => 'Test Event')));
$empty_html = (string) ob_get_clean();
t1a_ecc_same('', $empty_html, 'ECC output changed when no extensions were registered.');
$GLOBALS['t1a_ecc_filters']['bvmgr_ecc_extensions'] = array();

$context = bvmgr_ecc_extension_context(501, array('header' => array('label' => 'Test Event')));
$extensions = bvmgr_ecc_get_extensions($context, true);
t1a_ecc_same(array('empty', 'queue', 'error'), array_column($extensions, 'id'), 'ECC extensions were not ordered or capability-filtered correctly.');
t1a_ecc_same(20, $context['venue_id'], 'ECC context did not include the Event Plan venue.');
t1a_ecc_same(false, bvmgr_register_ecc_extension(array('id' => 'queue', 'title' => 'Duplicate')), 'Duplicate ECC registration must fail.');
t1a_ecc_same(false, bvmgr_register_ecc_extension(array('id' => 'bad', 'title' => 'Bad', 'render_callback' => 'not_callable')), 'Invalid ECC callback must fail registration.');

ob_start();
bvmgr_ecc_render_registered_extensions(501, array('header' => array('label' => 'Test Event')));
$html = (string) ob_get_clean();
t1a_ecc_assert(strpos($html, 'Extension Summary') !== false, 'Summary region did not render.');
t1a_ecc_assert(strpos($html, 'label="Open" value="3"') !== false, 'Summary metric did not render through the ECC metric contract.');
t1a_ecc_same(1, substr_count($html, 'Queue body'), 'Registered ECC panel did not render exactly once.');
t1a_ecc_same(1, $GLOBALS['t1a_ecc_renders'], 'Registered ECC callback did not run exactly once.');
t1a_ecc_same(501, $GLOBALS['t1a_ecc_context']['event_plan_id'] ?? 0, 'ECC callback received the wrong Event Plan ID.');
t1a_ecc_same('Test Event', $GLOBALS['t1a_ecc_context']['payload']['header']['label'] ?? '', 'ECC callback did not receive the current ECC payload.');
t1a_ecc_assert(strpos($html, 'No requests yet.') !== false, 'ECC empty state did not render.');
t1a_ecc_assert(strpos($html, 'Must not render') === false, 'Empty ECC panel rendered its body.');
t1a_ecc_assert(strpos($html, 'Panel unavailable.') !== false, 'ECC callback exception did not render the configured error state.');
t1a_ecc_assert(strpos($html, 'Denied body') === false, 'Capability-denied ECC panel rendered.');

$_GET = array('page' => 'other-page', 'plan_id' => '501');
do_action('admin_enqueue_scripts');
t1a_ecc_same(0, $GLOBALS['t1a_ecc_assets'], 'ECC assets loaded outside the ECC page.');
$_GET = array('page' => 'vms-event-command-center', 'plan_id' => '501');
do_action('admin_enqueue_scripts');
t1a_ecc_same(1, $GLOBALS['t1a_ecc_assets'], 'Visible ECC extension assets did not load exactly once.');

echo "Tranche 1A ECC-extension tests passed.\n";
