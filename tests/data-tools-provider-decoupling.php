<?php
/**
 * BVM/Data Tools source-boundary and Woo/core fallback regression.
 *
 * Run with: php tests/data-tools-provider-decoupling.php
 */

declare(strict_types=1);

define('ABSPATH', __DIR__);

function decoupling_assert($condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function decoupling_same($expected, $actual, string $message): void
{
	decoupling_assert($expected === $actual, $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
}

function decoupling_extract_function(string $source, string $name): string
{
	$start = strpos($source, 'function ' . $name . '(');
	$brace = $start === false ? false : strpos($source, '{', $start);
	if ($start === false || $brace === false) {
		throw new RuntimeException('Unable to find function ' . $name . '.');
	}

	$depth = 1;
	for ($index = $brace + 1, $length = strlen($source); $index < $length; $index++) {
		$depth += $source[$index] === '{' ? 1 : 0;
		$depth -= $source[$index] === '}' ? 1 : 0;
		if ($depth === 0) {
			return substr($source, $start, ($index - $start) + 1);
		}
	}

	throw new RuntimeException('Unable to parse function ' . $name . '.');
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

function bvmgr_event_command_center_request_cache(int $plan_id, string $key, callable $resolver)
{
	unset($plan_id, $key);
	return $resolver();
}

function bvmgr_ticket_revenue_build_report(array $args): array
{
	$GLOBALS['decoupling_core_report_args'] = $args;
	return array(
		'rows' => array(
			array('item_kind' => 'ticket', 'quantity' => 5, 'refunded_quantity' => 1, 'net_subtotal_cents' => 6400),
			array('item_kind' => 'ticket', 'quantity' => 2, 'refunded_quantity' => 0, 'net_subtotal_cents' => 0),
			array('item_kind' => 'addon', 'quantity' => 9, 'refunded_quantity' => 0, 'net_subtotal_cents' => 18000),
		),
		'warnings' => array('Core fixture warning.'),
	);
}

$root = dirname(__DIR__);
$portalPath = $root . '/includes/portal/vendor-portal.php';
$commandCenterPath = $root . '/includes/admin/event-command-center.php';
$providerContractPath = $root . '/includes/core/reporting-providers.php';
$coreLoadPath = $root . '/includes/core/load.php';
$dataToolsCandidate = $root . '/companion-plugins/vms-data-tools';
$dataToolsProviderPath = $dataToolsCandidate . '/includes/integrations/bvm-reporting-provider.php';
$installedDataTools = dirname($root, 2) . '/vms-data-tools';
$installedCalendarFeeds = dirname($root, 2) . '/backstage-calendar-feeds';

$portal = (string) file_get_contents($portalPath);
$commandCenter = (string) file_get_contents($commandCenterPath);
$providerContract = (string) file_get_contents($providerContractPath);
$coreLoad = (string) file_get_contents($coreLoadPath);
$dataToolsProvider = (string) file_get_contents($dataToolsProviderPath);
$dataToolsBootstrap = (string) file_get_contents($dataToolsCandidate . '/includes/bootstrap.php');
$dataToolsEntry = (string) file_get_contents($dataToolsCandidate . '/vms-data-tools.php');
$installedDataToolsEntry = (string) file_get_contents($installedDataTools . '/vms-data-tools.php');
$installedCalendarEntry = (string) file_get_contents($installedCalendarFeeds . '/backstage-calendar-feeds.php');

foreach (array($portal, $commandCenter, $providerContract, $coreLoad, $dataToolsProvider, $dataToolsBootstrap, $dataToolsEntry) as $source) {
	decoupling_assert($source !== '', 'A required provider-decoupling source file was unreadable.');
}

decoupling_assert(strpos($portal, 'bvmgr_vendor_portal_maybe_load_data_tools_reporting') === false, 'The filesystem loader should be removed from Vendor Portal.');
decoupling_assert(strpos($portal, 'WP_PLUGIN_DIR') === false, 'Vendor Portal should not use a plugin directory as Data Tools activation authority.');
decoupling_assert(strpos($portal, 'VMS_DT_ADMIN_DIR') === false, 'Vendor Portal should not reference Data Tools implementation paths.');
decoupling_assert(preg_match('/vms_dt_reporting_[a-z0-9_]+\s*\(/', $portal) !== 1, 'Vendor Portal should not call Data Tools reporting internals.');
decoupling_assert(preg_match('/vms_dt_reporting_[a-z0-9_]+\s*\(/', $commandCenter) !== 1, 'Event Command Center should not call Data Tools reporting internals.');
decoupling_assert(strpos($commandCenter, 'bvmgr_reporting_resolve_event_ticket_sales') !== false, 'Event Command Center should consume the BVM provider contract.');
decoupling_assert(strpos($portal, 'bvmgr_reporting_resolve_event_ticket_sales') !== false, 'Vendor Portal should consume the BVM provider contract.');
decoupling_assert(strpos($providerContract, 'vms-data-tools') === false && strpos($providerContract, 'VMS_DT_') === false, 'The BVM contract should not know a provider implementation or path.');
decoupling_assert(strpos($coreLoad, "require_once __DIR__ . '/reporting-providers.php';") < strpos($coreLoad, "require_once __DIR__ . '/vendor-user-links.php';"), 'The BVM provider API should load before feature consumers.');

decoupling_assert(strpos($dataToolsBootstrap, "require_once VMS_DT_INCLUDES_DIR . 'integrations/bvm-reporting-provider.php';") !== false, 'Active Data Tools should own provider bootstrap.');
decoupling_assert(strpos($dataToolsProvider, "'id' => 'vms-data-tools'") !== false, 'Data Tools provider identity changed.');
decoupling_assert(strpos($dataToolsProvider, "'contract_version' => 1") !== false, 'Data Tools provider contract version changed.');
decoupling_assert(strpos($dataToolsProvider, "'scope' => 'vendor_portal'") === false, 'Provider scope should be read from BVM context, not hardcoded registration state.');
decoupling_assert(strpos($dataToolsEntry, 'Version: 0.5.55') !== false && strpos($dataToolsEntry, "define('VMS_DT_VERSION', '0.5.55')") !== false, 'Data Tools successor should be 0.5.55.');
decoupling_assert(strpos($installedDataToolsEntry, 'Version: 0.5.55') !== false, 'Installed Data Tools should remain on the accepted 0.5.55 promotion.');
decoupling_assert(strpos($installedCalendarEntry, 'Version: 0.1.4') !== false, 'Installed Calendar Feeds should remain on the accepted 0.1.4 promotion.');

$includedBefore = get_included_files();
require_once $providerContractPath;
eval(decoupling_extract_function($commandCenter, 'bvmgr_event_command_center_summarize_ticket_report_rows'));
eval(decoupling_extract_function($commandCenter, 'bvmgr_event_command_center_get_ticket_reporting_truth'));
$fallback = bvmgr_event_command_center_get_ticket_reporting_truth(2534);
$includedAfter = array_values(array_diff(get_included_files(), $includedBefore));
$dataToolsLoaded = array_values(array_filter(
	$includedAfter,
	static fn(string $path): bool => strpos(str_replace('\\', '/', $path), '/vms-data-tools/') !== false
));

decoupling_same(array(), $dataToolsLoaded, 'Inactive-but-files-present Data Tools source should not execute.');
decoupling_assert(!defined('VMS_DT_VERSION') && !function_exists('vms_dt_init') && !function_exists('vms_dt_bvm_reporting_provider'), 'Inactive Data Tools bootstrap should remain dormant.');
decoupling_same('core_ticket_revenue', $fallback['source'] ?? '', 'Woo/core fallback should win when no provider is registered.');
decoupling_same(true, $fallback['available'] ?? false, 'Woo/core fallback should be available.');
decoupling_same(true, $fallback['calculated'] ?? false, 'Woo/core valid-empty semantics should remain explicit.');
decoupling_same(4, $fallback['paid_qty'] ?? -1, 'Refund-adjusted paid quantity changed.');
decoupling_same(2, $fallback['free_qty'] ?? -1, 'Free quantity changed.');
decoupling_same(6, $fallback['total_qty'] ?? -1, 'Fallback total quantity changed.');
decoupling_same(6400, $fallback['revenue_cents'] ?? -1, 'Fallback revenue changed.');
decoupling_same(2534, $GLOBALS['decoupling_core_report_args']['event_plan_id'] ?? 0, 'Fallback report should remain Event Plan scoped.');

echo "Data Tools provider decoupling PASS\n";
