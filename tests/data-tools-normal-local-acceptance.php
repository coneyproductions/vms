<?php
/**
 * Read-only normal-local acceptance for the Data Tools 0.5.55 promotion.
 *
 * WP-CLI evaluates ordinary eval-file input inside its command method, so this
 * file intentionally has no file-level strict_types declaration. Run it with
 * all normal plugins/themes skipped and the cross-process containment guard
 * installed. The probe loads only canonical BVM and the selected Data Tools
 * source. Synthetic reporting fixtures exercise the provider and consumer
 * contracts without reading or writing event, order, ticket, vendor, or Square
 * business rows.
 */

defined('ABSPATH') || exit;

function wave3b2_dt_assert(bool $condition, string $message): void
{
	$GLOBALS['wave3b2_dt_assertion_count'] = (int) ($GLOBALS['wave3b2_dt_assertion_count'] ?? 0) + 1;
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

/** @return array{count:int,sha256:string} */
function wave3b2_dt_tree_receipt(string $root): array
{
	$root = rtrim(str_replace('\\', '/', $root), '/');
	$files = array();
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
	);
	foreach ($iterator as $file) {
		if (!$file instanceof SplFileInfo || !$file->isFile() || $file->isLink()) {
			continue;
		}
		$path = str_replace('\\', '/', $file->getPathname());
		$relative = substr($path, strlen($root) + 1);
		if ($relative === false || str_starts_with($relative, '.git/')) {
			continue;
		}
		$files[$relative] = $path;
	}
	ksort($files, SORT_STRING);
	$manifest = '';
	foreach ($files as $relative => $path) {
		$digest = hash_file('sha256', $path);
		wave3b2_dt_assert(is_string($digest), 'Could not hash a Data Tools source file.');
		$manifest .= $relative . "\0" . $digest . "\n";
	}

	return array(
		'count' => count($files),
		'sha256' => hash('sha256', $manifest),
	);
}

function wave3b2_dt_plugin_version(string $file): string
{
	$data = get_file_data($file, array('version' => 'Version'), 'plugin');
	return trim((string) ($data['version'] ?? ''));
}

/** @return array<string,array{value:string,autoload:string}> */
function wave3b2_dt_option_rows(): array
{
	global $wpdb;
	$rows = $wpdb->get_results(
		"SELECT option_name, option_value, autoload
		FROM {$wpdb->options}
		WHERE option_name REGEXP '^(vms_dt_|vms_square_)'
			OR option_name = 'vms_holidays'
			OR option_name REGEXP '^_transient_(timeout_)?vms_(dt|square)_'
			OR option_name IN ('active_plugins', 'cron', '{$wpdb->prefix}user_roles')
		ORDER BY option_name",
		ARRAY_A
	);
	$out = array();
	foreach ((array) $rows as $row) {
		$name = (string) ($row['option_name'] ?? '');
		if ($name === '') {
			continue;
		}
		$out[$name] = array(
			'value' => (string) ($row['option_value'] ?? ''),
			'autoload' => (string) ($row['autoload'] ?? ''),
		);
	}
	return $out;
}

/** @return array<string,array{value:string,autoload:string}> */
function wave3b2_dt_square_config_rows(): array
{
	global $wpdb;
	$rows = $wpdb->get_results(
		"SELECT option_name, option_value, autoload
		FROM {$wpdb->options}
		WHERE option_name LIKE 'wc_square%'
		ORDER BY option_name",
		ARRAY_A
	);
	$out = array();
	foreach ((array) $rows as $row) {
		$name = (string) ($row['option_name'] ?? '');
		if ($name === '') {
			continue;
		}
		$out[$name] = array(
			'value' => (string) ($row['option_value'] ?? ''),
			'autoload' => (string) ($row['autoload'] ?? ''),
		);
	}
	return $out;
}

/** @return array<string,string> */
function wave3b2_dt_table_checksums(): array
{
	global $wpdb;
	$tables = array(
		$wpdb->prefix . 'vms_square_catalog_map',
		$wpdb->prefix . 'vms_square_daily',
		$wpdb->prefix . 'vms_square_orders',
		$wpdb->prefix . 'vms_square_ticket_mirror_log',
		$wpdb->prefix . 'vms_vendor_claim_tokens',
		$wpdb->prefix . 'vms_vendor_invite_log',
		$wpdb->prefix . 'vms_vendor_invite_retention_audit',
		$wpdb->prefix . 'vms_vendor_opportunity_submissions',
	);
	$out = array();
	foreach ($tables as $table) {
		$exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
		if ((string) $exists !== $table) {
			$out[$table] = 'absent';
			continue;
		}
		$row = $wpdb->get_row('CHECKSUM TABLE `' . esc_sql($table) . '`', ARRAY_A);
		$out[$table] = (string) ($row['Checksum'] ?? '');
	}
	return $out;
}

function wave3b2_dt_require_plugin(string $relative): void
{
	$path = WP_PLUGIN_DIR . '/' . ltrim($relative, '/');
	wave3b2_dt_assert(is_readable($path), 'Required normal-local plugin entry is unreadable: ' . $relative);
	require_once $path;
}

/* Safe in-memory reporting fixtures used by the real Data Tools provider. */
$GLOBALS['wave3b2_dt_model_filters'] = array();
$GLOBALS['wave3b2_dt_square_filters'] = array();
$GLOBALS['wave3b2_dt_rollup_evidence'] = array();

function vms_dt_reporting_build_event_model(array $filters): array
{
	$GLOBALS['wave3b2_dt_model_filters'] = $filters;
	return array(
		'costs' => array(
			'paid_ticket_qty_total' => 11,
			'free_ticket_qty_excluded' => 2,
			'ticket_qty_total' => 13,
			'ticket_sales_total_cents' => 12345,
		),
		'summary' => array('total_ticket_qty' => 13, 'total_ticket_sales_cents' => 12345),
		'row' => array(
			'website_paid_ticket_qty' => 8,
			'square_paid_ticket_qty' => 3,
			'website_free_ticket_qty' => 1,
			'square_free_ticket_qty' => 1,
			'confidence_badges' => array('fixture-confidence'),
			'square_warnings' => array('fixture-warning'),
			'square_errors' => array(),
		),
	);
}

function vms_dt_reporting_build_website_detail_rows(int $event_plan_id): array
{
	return array(
		'ticket_rows' => array(
			array('sold_date' => '', 'quantity' => 5),
			array('sold_date' => '', 'quantity' => 3),
		),
	);
}

function vms_dt_reporting_build_square_line_evidence(int $event_plan_id, array $filters): array
{
	$GLOBALS['wave3b2_dt_square_filters'] = $filters;
	return array('warnings' => array('fixture-square-warning'), 'errors' => array());
}

function vms_dt_reporting_build_ticket_source_rollup(array $row, array $evidence = array()): array
{
	$GLOBALS['wave3b2_dt_rollup_evidence'] = $evidence;
	return array(
		'website_paid_ticket_qty' => 8,
		'website_paid_ticket_revenue_cents' => 10000,
		'website_free_ticket_qty' => 1,
		'square_ticket_qty' => 5,
		'square_paid_ticket_qty' => 3,
		'square_free_ticket_qty' => 2,
		'square_paid_ticket_revenue_cents' => 2345,
		'website_rows_seen' => 2,
		'square_rows_seen' => 5,
		'ticketed_attendance_qty' => 14,
		'paid_ticket_revenue_cents' => 12345,
		'free_ticket_qty_total' => 3,
		'paid_ticket_qty_total' => 11,
		'has_countable_data' => true,
	);
}

$mode = (string) getenv('WAVE3B2_DATA_TOOLS_MODE');
$expected_version = (string) getenv('WAVE3B2_DATA_TOOLS_EXPECTED_VERSION');
$expected_tree_hash = strtolower((string) getenv('WAVE3B2_DATA_TOOLS_EXPECTED_TREE_SHA256'));
$expected_by_mode = array(
	'baseline' => array(
		'version' => '0.5.54',
		'tree' => '7f997b79f675b2534188d44f750afb4bd4eec9d03db9f75b6020b0afe02e9039',
		'files' => 78,
		'provider' => false,
	),
	'candidate-diagnostic' => array(
		'version' => '0.5.55',
		'tree' => '8e36b9b6cf1e337627a86a782f2eafc49681d09b8711665e54ab78d6b005988b',
		'files' => 81,
		'provider' => true,
	),
	'acceptance' => array(
		'version' => '0.5.55',
		'tree' => '8e36b9b6cf1e337627a86a782f2eafc49681d09b8711665e54ab78d6b005988b',
		'files' => 81,
		'provider' => true,
	),
);

wave3b2_dt_assert(isset($expected_by_mode[$mode]), 'Mode must be baseline, candidate-diagnostic, or acceptance.');
$expectation = $expected_by_mode[$mode];
wave3b2_dt_assert($expected_version === $expectation['version'], 'Expected Data Tools version does not match the selected mode.');
wave3b2_dt_assert(
	preg_match('/\A[a-f0-9]{64}\z/', $expected_tree_hash) === 1
		&& hash_equals($expectation['tree'], $expected_tree_hash),
	'Expected Data Tools tree does not match the selected mode.'
);
wave3b2_dt_assert(defined('WP_ADMIN') && WP_ADMIN, 'Acceptance must run with the admin runtime preload.');
wave3b2_dt_assert(defined('WP_CLI') && WP_CLI, 'Acceptance must run through WP-CLI.');
wave3b2_dt_assert(version_compare(PHP_VERSION, '8.3.0', '>=') && version_compare(PHP_VERSION, '8.4.0', '<'), 'Acceptance must use site-matched PHP 8.3.');
wave3b2_dt_assert(did_action('plugins_loaded') > 0, 'WordPress did not complete its normal bootstrap.');

if (!function_exists('get_file_data')) {
	require_once ABSPATH . 'wp-includes/functions.php';
}

$options_before = hash('sha256', serialize(wave3b2_dt_option_rows()));
$square_config_before = hash('sha256', serialize(wave3b2_dt_square_config_rows()));
$tables_before = hash('sha256', serialize(wave3b2_dt_table_checksums()));
$active_before = hash('sha256', serialize(get_option('active_plugins', array())));
$cron_before = hash('sha256', serialize(get_option('cron', array())));

wave3b2_dt_require_plugin('backstage-venue-manager/backstage-venue-manager.php');
$data_tools_entry = WP_PLUGIN_DIR . '/vms-data-tools/vms-data-tools.php';
if ($mode === 'candidate-diagnostic') {
	$data_tools_entry = (string) getenv('WAVE3B2_DATA_TOOLS_SOURCE_ENTRY');
	wave3b2_dt_assert(is_readable($data_tools_entry), 'Accepted candidate entry is unavailable for diagnostic execution.');
	require_once $data_tools_entry;
} else {
	wave3b2_dt_require_plugin('vms-data-tools/vms-data-tools.php');
}

wave3b2_dt_assert(defined('BVMGR_VERSION') && BVMGR_VERSION === '1.3.1', 'Canonical BVM 1.3.1 did not load.');
wave3b2_dt_assert(defined('VMS_DT_VERSION') && VMS_DT_VERSION === $expected_version, 'Data Tools did not load the expected version.');
wave3b2_dt_assert(function_exists('bvmgr_reporting_resolve_event_ticket_sales'), 'BVM reporting-provider resolver is unavailable.');
wave3b2_dt_assert(function_exists('bvmgr_event_command_center_get_ticket_reporting_truth'), 'Event Command Center ticket path is unavailable.');
wave3b2_dt_assert(function_exists('bvmgr_vendor_portal_get_data_tools_sales_snapshot'), 'Vendor Portal reporting consumer is unavailable.');
wave3b2_dt_assert(function_exists('bvmgr_staffing_resolve_event_snapshot'), 'P0 staffing resolver is unavailable.');
wave3b2_dt_assert(function_exists('bvmgr_ticket_revenue_build_report'), 'P0 ticket-revenue fallback is unavailable.');

$tree = wave3b2_dt_tree_receipt(dirname($data_tools_entry));
wave3b2_dt_assert($tree['count'] === $expectation['files'], 'Data Tools file count changed.');
wave3b2_dt_assert(hash_equals($expected_tree_hash, $tree['sha256']), 'Data Tools tree does not match its accepted authority.');

$active_plugins = (array) get_option('active_plugins', array());
$data_tools_entries = array_values(array_filter(
	$active_plugins,
	static fn($plugin): bool => is_string($plugin) && str_starts_with($plugin, 'vms-data-tools/')
));
wave3b2_dt_assert($data_tools_entries === array('vms-data-tools/vms-data-tools.php'), 'Canonical Data Tools activation changed or duplicated.');
wave3b2_dt_assert(!in_array('vms/vendor-management-system.php', $active_plugins, true), 'Legacy VMS unexpectedly became active.');

$accepted_plugins = array(
	'backstage-calendar-feeds/backstage-calendar-feeds.php' => '0.1.4',
	'vmsx-weather-risk/vmsx-weather-risk.php' => '0.1.12',
	'vms-commerce-discounts/vms-commerce-discounts.php' => '0.2.13',
	'drm-calendar-intake/drm-calendar-intake.php' => '0.2.4',
	'drm-event-router/drm-event-router.php' => '0.1.3',
	'drm-events-bridge/drm-events-bridge.php' => '0.2.2',
	'vms-sponsorships/vms-sponsorships.php' => '0.1.28',
);
foreach ($accepted_plugins as $relative => $version) {
	wave3b2_dt_assert(wave3b2_dt_plugin_version(WP_PLUGIN_DIR . '/' . $relative) === $version, 'Accepted add-on version changed: ' . $relative);
	wave3b2_dt_assert(in_array($relative, $active_plugins, true), 'Accepted add-on activation changed: ' . $relative);
}

$ecc_source = (string) file_get_contents(WP_PLUGIN_DIR . '/backstage-venue-manager/includes/admin/event-command-center.php');
$portal_source = (string) file_get_contents(WP_PLUGIN_DIR . '/backstage-venue-manager/includes/portal/vendor-portal.php');
wave3b2_dt_assert(str_contains($ecc_source, 'return bvmgr_reporting_get_ticket_truth($plan_id);'), 'ECC does not consume the BVM reporting contract.');
wave3b2_dt_assert(str_contains($portal_source, 'bvmgr_reporting_resolve_event_ticket_sales'), 'Vendor Portal does not consume the BVM reporting contract.');
wave3b2_dt_assert(!str_contains($ecc_source, 'VMS_DT_ADMIN_DIR') && !str_contains($portal_source, 'VMS_DT_ADMIN_DIR'), 'BVM references the Data Tools implementation directory.');
wave3b2_dt_assert(preg_match('/vms_dt_reporting_[a-z0-9_]+\s*\(/', $ecc_source) !== 1, 'ECC directly calls a Data Tools reporting internal.');
wave3b2_dt_assert(preg_match('/vms_dt_reporting_[a-z0-9_]+\s*\(/', $portal_source) !== 1, 'Vendor Portal directly calls a Data Tools reporting internal.');

$providers = bvmgr_reporting_get_registered_providers();
$provider_result = array();
$vendor_result = array();
if (!$expectation['provider']) {
	wave3b2_dt_assert(!isset($providers['vms-data-tools']), 'Data Tools 0.5.54 unexpectedly registered the 0.5.55 provider.');
	wave3b2_dt_assert(!function_exists('vms_dt_register_bvm_reporting_provider'), 'Data Tools 0.5.54 unexpectedly contains the provider integration.');
} else {
	wave3b2_dt_assert(count($providers) === 1 && isset($providers['vms-data-tools']), 'Data Tools did not register exactly one provider.');
	$provider = (array) $providers['vms-data-tools'];
	wave3b2_dt_assert(($provider['version'] ?? '') === '0.5.55', 'Provider version provenance changed.');
	wave3b2_dt_assert((int) ($provider['contract_version'] ?? 0) === 1, 'Provider contract version changed.');
	wave3b2_dt_assert((int) ($provider['priority'] ?? 0) === 20, 'Provider priority changed.');
	wave3b2_dt_assert(($provider['capabilities'] ?? array()) === array('event_ticket_sales'), 'Provider capabilities changed.');
	wave3b2_dt_assert(($provider['callback'] ?? '') === 'vms_dt_bvm_reporting_provider', 'Provider callback identity changed.');
	wave3b2_dt_assert(vms_dt_register_bvm_reporting_provider(), 'Idempotent Data Tools registration failed.');
	wave3b2_dt_assert(count(bvmgr_reporting_get_registered_providers()) === 1, 'Idempotent registration duplicated the provider.');
	wave3b2_dt_assert(!bvmgr_reporting_register_provider(array(
		'id' => 'vms-data-tools',
		'version' => 'forbidden-duplicate',
		'contract_version' => 1,
		'capabilities' => array('event_ticket_sales'),
		'priority' => 1,
		'callback' => static fn(): array => array('available' => true, 'calculated' => true),
	)), 'BVM accepted a duplicate provider ID.');

	$provider_result = bvmgr_event_command_center_get_ticket_reporting_truth(2534);
	wave3b2_dt_assert(!empty($provider_result['available']) && !empty($provider_result['calculated']), 'Provider did not return a calculated ECC result.');
	wave3b2_dt_assert(($provider_result['provider_id'] ?? '') === 'vms-data-tools', 'ECC provider identity changed.');
	wave3b2_dt_assert(($provider_result['provider_version'] ?? '') === '0.5.55', 'ECC provider version changed.');
	wave3b2_dt_assert(($provider_result['source'] ?? '') === 'dt_reporting_model', 'ECC source provenance changed.');
	wave3b2_dt_assert((int) ($provider_result['paid_qty'] ?? -1) === 11, 'ECC paid quantity changed.');
	wave3b2_dt_assert((int) ($provider_result['free_qty'] ?? -1) === 2, 'ECC free quantity changed.');
	wave3b2_dt_assert((int) ($provider_result['total_qty'] ?? -1) === 13, 'ECC total quantity changed.');
	wave3b2_dt_assert((int) ($provider_result['revenue_cents'] ?? -1) === 12345, 'ECC revenue changed.');
	wave3b2_dt_assert(($GLOBALS['wave3b2_dt_model_filters']['square_scope_mode'] ?? '') === 'full_day', 'ECC Square scope changed.');

	$vendor_result = bvmgr_vendor_portal_get_data_tools_sales_snapshot(2534);
	wave3b2_dt_assert(($vendor_result['provider_id'] ?? '') === 'vms-data-tools', 'Vendor Portal provider identity changed.');
	wave3b2_dt_assert(($vendor_result['provider_version'] ?? '') === '0.5.55', 'Vendor Portal provider version changed.');
	wave3b2_dt_assert(($vendor_result['source'] ?? '') === 'data_tools_merged_ticket_sales', 'Vendor Portal source changed.');
	wave3b2_dt_assert((int) ($vendor_result['ticketed_attendance_qty'] ?? -1) === 14, 'Vendor Portal attendance changed.');
	wave3b2_dt_assert((int) ($vendor_result['paid_ticket_qty_total'] ?? -1) === 11, 'Vendor Portal paid quantity changed.');
	wave3b2_dt_assert((int) ($vendor_result['free_ticket_qty_total'] ?? -1) === 3, 'Vendor Portal free quantity changed.');
	wave3b2_dt_assert((int) ($vendor_result['sales_cents'] ?? -1) === 12345, 'Vendor Portal revenue changed.');
	wave3b2_dt_assert(($GLOBALS['wave3b2_dt_square_filters']['square_scope_mode'] ?? '') === 'full_day', 'Vendor Portal Square scope changed.');
	wave3b2_dt_assert(count((array) ($GLOBALS['wave3b2_dt_rollup_evidence']['website']['ticket_rows'] ?? array())) === 2, 'Website reporting rows did not reach the provider-owned rollup.');

	$included = array_map(static fn($path): string => str_replace('\\', '/', (string) $path), get_included_files());
	wave3b2_dt_assert(
		count(array_filter($included, static fn($path): bool => str_ends_with($path, '/vms-data-tools/includes/admin/page-revenue-intelligence.php'))) === 0,
		'Synthetic acceptance unexpectedly loaded live revenue implementation source.'
	);
	wave3b2_dt_assert(
		count(array_filter($included, static fn($path): bool => str_ends_with($path, '/vms-data-tools/includes/admin/page-reporting-module.php'))) === 0,
		'Synthetic acceptance unexpectedly loaded live reporting implementation source.'
	);
}

$options_after = hash('sha256', serialize(wave3b2_dt_option_rows()));
$square_config_after = hash('sha256', serialize(wave3b2_dt_square_config_rows()));
$tables_after = hash('sha256', serialize(wave3b2_dt_table_checksums()));
$active_after = hash('sha256', serialize(get_option('active_plugins', array())));
$cron_after = hash('sha256', serialize(get_option('cron', array())));
wave3b2_dt_assert(hash_equals($options_before, $options_after), 'Data Tools/options/capability state changed during acceptance.');
wave3b2_dt_assert(hash_equals($square_config_before, $square_config_after), 'WooCommerce Square configuration changed during acceptance.');
wave3b2_dt_assert(hash_equals($tables_before, $tables_after), 'Data Tools/Square table contents changed during acceptance.');
wave3b2_dt_assert(hash_equals($active_before, $active_after), 'Plugin activation changed during acceptance.');
wave3b2_dt_assert(hash_equals($cron_before, $cron_after), 'Cron changed during acceptance.');

echo wp_json_encode(array(
	'status' => 'PASS',
	'mode' => $mode,
	'assertions' => (int) $GLOBALS['wave3b2_dt_assertion_count'],
	'php_version' => PHP_VERSION,
	'data_tools_version' => VMS_DT_VERSION,
	'data_tools_files' => $tree['count'],
	'data_tools_tree_sha256' => $tree['sha256'],
	'active_data_tools_entries' => count($data_tools_entries),
	'provider_count' => isset($providers['vms-data-tools']) ? 1 : 0,
	'provider_id' => (string) ($provider_result['provider_id'] ?? ''),
	'provider_version' => (string) ($provider_result['provider_version'] ?? ''),
	'ecc_source' => (string) ($provider_result['source'] ?? ''),
	'vendor_portal_source' => (string) ($vendor_result['source'] ?? ''),
	'options_sha256' => $options_after,
	'square_config_sha256' => $square_config_after,
	'tables_sha256' => $tables_after,
	'active_plugins_sha256' => $active_after,
	'cron_sha256' => $cron_after,
), JSON_UNESCAPED_SLASHES) . "\n";
