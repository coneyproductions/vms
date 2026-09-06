<?php
/**
 * Isolated VMS Data Tools 0.5.55 provider and load-order regression.
 *
 * Run with: php tests/data-tools-reporting-provider.php
 */

declare(strict_types=1);

define('ABSPATH', __DIR__);
define('VMS_DT_VERSION', '0.5.55');
define('VMS_DT_ADMIN_DIR', dirname(__DIR__) . '/companion-plugins/vms-data-tools/includes/admin/');

$GLOBALS['dt_provider_hooks'] = array();
$GLOBALS['dt_provider_model'] = array();
$GLOBALS['dt_provider_website'] = array();
$GLOBALS['dt_provider_square'] = array();
$GLOBALS['dt_provider_throw'] = false;
$GLOBALS['dt_provider_rollup_evidence'] = array();

function add_action($hook, $callback, $priority = 10): void
{
	$GLOBALS['dt_provider_hooks'][$hook][(int) $priority][] = $callback;
}

function do_action($hook): void
{
	$callbacks = $GLOBALS['dt_provider_hooks'][$hook] ?? array();
	ksort($callbacks);
	foreach ($callbacks as $priority_callbacks) {
		foreach ($priority_callbacks as $callback) {
			call_user_func($callback);
		}
	}
}

function sanitize_key($value): string
{
	return (string) preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $value));
}

function wp_strip_all_tags($value): string
{
	return strip_tags((string) $value);
}

function absint($value): int
{
	return abs((int) $value);
}

function __(string $text, string $domain = ''): string
{
	unset($domain);
	return $text;
}

function get_post_meta(int $post_id, string $key, bool $single = false)
{
	unset($post_id, $single);
	return $key === '_vms_event_date' ? '2026-09-05' : '';
}

function current_time(string $type)
{
	return $type === 'timestamp' ? 1788584400 : '2026-09-05 00:00:00';
}

function wp_timezone(): DateTimeZone
{
	return new DateTimeZone('America/Chicago');
}

function wp_date(string $format, $timestamp = null, $timezone = null): string
{
	unset($timestamp, $timezone);
	return $format === 'M j, Y g:ia' ? 'Sep 5, 2026 12:00am' : '2026-09-05';
}

function vms_dt_reporting_build_event_model(array $filters): array
{
	if ($GLOBALS['dt_provider_throw']) {
		throw new RuntimeException('Synthetic Data Tools model failure.');
	}
	$GLOBALS['dt_provider_model_filters'] = $filters;
	return $GLOBALS['dt_provider_model'];
}

function vms_dt_reporting_build_website_detail_rows(int $event_plan_id): array
{
	unset($event_plan_id);
	return $GLOBALS['dt_provider_website'];
}

function vms_dt_reporting_build_square_line_evidence(int $event_plan_id, array $filters): array
{
	unset($event_plan_id);
	$GLOBALS['dt_provider_square_filters'] = $filters;
	return $GLOBALS['dt_provider_square'];
}

function vms_dt_reporting_build_ticket_source_rollup(array $row, array $evidence = array()): array
{
	unset($row);
	$GLOBALS['dt_provider_rollup_evidence'] = $evidence;
	return (array) ($GLOBALS['dt_provider_square']['rollup'] ?? array());
}

function dt_provider_assert($condition, string $message): void
{
	if (!$condition) {
		fwrite(STDERR, "FAIL: {$message}\n");
		exit(1);
	}
}

function dt_provider_same($expected, $actual, string $message): void
{
	dt_provider_assert($expected === $actual, $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
}

function dt_provider_run(string $order): void
{
	$bvm_contract = dirname(__DIR__) . '/includes/core/reporting-providers.php';
	$dt_provider = dirname(__DIR__) . '/companion-plugins/vms-data-tools/includes/integrations/bvm-reporting-provider.php';

	if ($order === 'bvm-first') {
		require_once $bvm_contract;
		require_once $dt_provider;
	} else {
		require_once $dt_provider;
		require_once $bvm_contract;
	}
	do_action('plugins_loaded');

	$providers = bvmgr_reporting_get_registered_providers();
	dt_provider_same(1, count($providers), $order . ' should register exactly one provider.');
	dt_provider_same('0.5.55', $providers['vms-data-tools']['version'] ?? '', $order . ' should expose candidate provenance.');
	dt_provider_same(1, $providers['vms-data-tools']['contract_version'] ?? 0, $order . ' contract version changed.');

	$GLOBALS['dt_provider_model'] = array(
		'costs' => array(
			'paid_ticket_qty_total' => 22,
			'free_ticket_qty_excluded' => 5,
			'ticket_qty_total' => 27,
			'ticket_sales_total_cents' => 45678,
		),
		'summary' => array(),
		'row' => array(
			'website_paid_ticket_qty' => 20,
			'website_free_ticket_qty' => 4,
			'square_paid_ticket_qty' => 2,
			'square_free_ticket_qty' => 1,
			'confidence_badges' => array('website_reconciled'),
			'square_warnings' => array('Square fixture warning.'),
		),
	);
	$event = bvmgr_reporting_resolve_event_ticket_sales(2534, array('scope' => 'event_command_center'));
	dt_provider_assert($event['available'] && $event['calculated'], $order . ' Event Command Center result should calculate.');
	dt_provider_same('vms-data-tools', $event['provider_id'], $order . ' provider identity changed.');
	dt_provider_same('dt_reporting_model', $event['source'], $order . ' Event Command Center source changed.');
	dt_provider_same(22, $event['paid_qty'], $order . ' paid quantity changed.');
	dt_provider_same(5, $event['free_qty'], $order . ' free quantity changed.');
	dt_provider_same(27, $event['total_qty'], $order . ' total quantity changed.');
	dt_provider_same(45678, $event['revenue_cents'], $order . ' revenue changed.');
	dt_provider_same('full_day', $GLOBALS['dt_provider_model_filters']['square_scope_mode'] ?? '', $order . ' Square scope changed.');

	$GLOBALS['dt_provider_model'] = array('costs' => array(), 'summary' => array(), 'row' => array());
	$valid_empty = bvmgr_reporting_resolve_event_ticket_sales(2535, array('scope' => 'event_command_center'));
	dt_provider_assert($valid_empty['available'] && $valid_empty['calculated'], $order . ' valid empty result should remain calculated.');
	dt_provider_same(0, $valid_empty['total_qty'], $order . ' valid empty result should stay zero.');

	$GLOBALS['dt_provider_website'] = array(
		'ticket_rows' => array(
			array('quantity' => 20, 'refunded_quantity' => 0, 'net_subtotal_cents' => 38002, 'sold_date' => '2026-09-01'),
			array('quantity' => 4, 'refunded_quantity' => 0, 'net_subtotal_cents' => 0, 'sold_datetime' => '2026-09-02 12:00:00'),
			array('quantity' => 7, 'refunded_quantity' => 0, 'net_subtotal_cents' => 14000, 'sold_date' => '2026-09-06'),
		),
	);
	$GLOBALS['dt_provider_square'] = array(
		'ticket_rows' => array(array('source_label' => 'Door register')),
		'warnings' => array('Square fixture warning.'),
		'errors' => array(),
		'rollup' => array(
			'website_paid_ticket_qty' => 20,
			'website_free_ticket_qty' => 4,
			'website_paid_ticket_revenue_cents' => 38002,
			'website_rows_seen' => 2,
			'square_ticket_qty' => 3,
			'square_paid_ticket_qty' => 2,
			'square_free_ticket_qty' => 1,
			'square_paid_ticket_revenue_cents' => 4000,
			'square_rows_seen' => 1,
			'paid_ticket_qty_total' => 22,
			'free_ticket_qty_total' => 5,
			'ticketed_attendance_qty' => 27,
			'paid_ticket_revenue_cents' => 42002,
			'has_countable_data' => true,
		),
	);
	$vendor = bvmgr_reporting_resolve_event_ticket_sales(2534, array('scope' => 'vendor_portal'));
	dt_provider_assert($vendor['available'] && $vendor['calculated'], $order . ' vendor result should calculate.');
	dt_provider_same('data_tools_merged_ticket_sales', $vendor['source'], $order . ' vendor source changed.');
	dt_provider_same(27, $vendor['headcount'], $order . ' vendor headcount changed.');
	dt_provider_same(22, $vendor['paid_ticket_qty_total'], $order . ' vendor paid count changed.');
	dt_provider_same(5, $vendor['free_ticket_qty_total'], $order . ' vendor free count changed.');
	dt_provider_same(2, $vendor['door_paid_qty'], $order . ' Square paid count changed.');
	dt_provider_same(1, $vendor['door_free_qty'], $order . ' Square free count changed.');
	dt_provider_same(42002, $vendor['sales_cents'], $order . ' website + Square revenue changed.');
	dt_provider_same(2, count($GLOBALS['dt_provider_rollup_evidence']['website']['ticket_rows'] ?? array()), $order . ' post-event website rows should be excluded.');
	dt_provider_same('full_day', $GLOBALS['dt_provider_square_filters']['square_scope_mode'] ?? '', $order . ' vendor Square scope changed.');

	$GLOBALS['dt_provider_throw'] = true;
	$failed = bvmgr_reporting_resolve_event_ticket_sales(2536, array('scope' => 'event_command_center'));
	dt_provider_assert(!$failed['available'] && !$failed['calculated'], $order . ' provider exception should fail safely.');
	dt_provider_same('exception', $failed['provider_attempts'][0]['status'] ?? '', $order . ' exception should remain observable.');
	dt_provider_assert(strpos(implode(' ', $failed['errors']), 'Synthetic Data Tools model failure') !== false, $order . ' provider failure diagnostic changed.');
}

if (isset($argv[1])) {
	dt_provider_run((string) $argv[1]);
	exit(0);
}

foreach (array('bvm-first', 'data-tools-first') as $order) {
	$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' ' . escapeshellarg($order);
	passthru($command, $status);
	dt_provider_assert($status === 0, $order . ' subprocess should pass.');
}

echo "Data Tools reporting provider PASS\n";
