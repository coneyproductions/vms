<?php
/**
 * Read-only normal-local acceptance for Calendar Feeds promotion.
 *
 * WP-CLI 2.12.0 evaluates ordinary `eval-file` input inside a method. A
 * file-level strict_types declaration is therefore invalid here because it is
 * no longer the first statement in the evaluated compilation context.
 *
 * Run behind the test-containment guard with active plugins skipped. This
 * probe loads only canonical BVM, the DRM chain, and Calendar Feeds so normal
 * Local acceptance cannot wake unrelated add-on bookkeeping.
 */

defined('ABSPATH') || exit;

function wave3b1_calendar_assert(bool $condition, string $message): void
{
	$GLOBALS['wave3b1_calendar_assertion_count'] = (int) ($GLOBALS['wave3b1_calendar_assertion_count'] ?? 0) + 1;
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function wave3b1_calendar_option_fingerprint(): string
{
	$absent = '__wave3b1_absent__';
	$values = array();
	foreach (array('bcf_feed_profile_secrets', 'bcf_publication_ledger_v1', 'bcf_publication_ledger_lock_v1') as $name) {
		$values[$name] = get_option($name, $absent);
	}

	return hash('sha256', serialize($values));
}

/** @return array{count:int,sha256:string} */
function wave3b1_calendar_tree_receipt(string $root): array
{
	$root = rtrim(str_replace('\\', '/', $root), '/');
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
	);
	$files = array();
	foreach ($iterator as $file) {
		if (!$file instanceof SplFileInfo || !$file->isFile()) {
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
		wave3b1_calendar_assert(is_string($digest), 'Could not hash a Calendar Feeds source file.');
		$manifest .= $relative . "\0" . $digest . "\n";
	}

	return array(
		'count' => count($files),
		'sha256' => hash('sha256', $manifest),
	);
}

function wave3b1_calendar_plugin_version(string $file): string
{
	$data = get_file_data($file, array('version' => 'Version'), 'plugin');
	return trim((string) ($data['version'] ?? ''));
}

function wave3b1_calendar_load_required_plugin(string $relative): void
{
	$path = WP_PLUGIN_DIR . '/' . ltrim($relative, '/');
	wave3b1_calendar_assert(is_readable($path), 'A required normal-local plugin entry is unreadable: ' . $relative);
	require_once $path;
}

$mode = (string) getenv('WAVE3B1_CALENDAR_ACCEPTANCE_MODE');
$expected_version = (string) getenv('WAVE3B1_CALENDAR_EXPECTED_VERSION');
$expected_tree_hash = strtolower((string) getenv('WAVE3B1_CALENDAR_EXPECTED_TREE_SHA256'));
$expected_by_mode = array(
	'diagnostic' => array(
		'version' => '0.1.3',
		'tree' => 'c732bb48971360e892fd17834d4a1b018a1de27e58777635a12164e58eb5e1e8',
	),
	'candidate-diagnostic' => array(
		'version' => '0.1.4',
		'tree' => '20f2607ec61c62f2d7120cd99b657b46e15bc169f6d92d14f7951540e33fc20e',
	),
	'acceptance' => array(
		'version' => '0.1.4',
		'tree' => '20f2607ec61c62f2d7120cd99b657b46e15bc169f6d92d14f7951540e33fc20e',
	),
);

wave3b1_calendar_assert(isset($expected_by_mode[$mode]), 'Acceptance mode must be diagnostic, candidate-diagnostic, or acceptance.');
wave3b1_calendar_assert($expected_version === $expected_by_mode[$mode]['version'], 'Expected Calendar version does not match the selected acceptance mode.');
wave3b1_calendar_assert(
	preg_match('/\A[a-f0-9]{64}\z/', $expected_tree_hash) === 1
		&& hash_equals($expected_by_mode[$mode]['tree'], $expected_tree_hash),
	'Expected Calendar tree does not match the selected acceptance mode.'
);
wave3b1_calendar_assert(defined('WP_ADMIN') && WP_ADMIN, 'Acceptance must run with the admin runtime preload.');
wave3b1_calendar_assert(defined('WP_CLI') && WP_CLI, 'Acceptance must run through WP-CLI.');
wave3b1_calendar_assert(version_compare(PHP_VERSION, '8.3.0', '>=') && version_compare(PHP_VERSION, '8.4.0', '<'), 'Acceptance must use site-matched PHP 8.3.');
wave3b1_calendar_assert(did_action('plugins_loaded') > 0, 'WordPress did not complete its normal bootstrap.');

if (!function_exists('get_file_data')) {
	require_once ABSPATH . 'wp-includes/functions.php';
}

$option_fingerprint_before = wave3b1_calendar_option_fingerprint();
$active_fingerprint_before = hash('sha256', serialize(get_option('active_plugins', array())));
$cron_fingerprint_before = hash('sha256', serialize(get_option('cron', array())));

wave3b1_calendar_load_required_plugin('backstage-venue-manager/backstage-venue-manager.php');
wave3b1_calendar_load_required_plugin('drm-calendar-intake/drm-calendar-intake.php');
wave3b1_calendar_load_required_plugin('drm-event-router/drm-event-router.php');
wave3b1_calendar_load_required_plugin('drm-events-bridge/drm-events-bridge.php');
$calendar_entry = WP_PLUGIN_DIR . '/backstage-calendar-feeds/backstage-calendar-feeds.php';
if ($mode === 'candidate-diagnostic') {
	$calendar_entry = (string) getenv('WAVE3B1_CALENDAR_SOURCE_ENTRY');
	wave3b1_calendar_assert(is_readable($calendar_entry), 'The retained candidate entry is unavailable for diagnostic execution.');
	require_once $calendar_entry;
} else {
	wave3b1_calendar_load_required_plugin('backstage-calendar-feeds/backstage-calendar-feeds.php');
}

wave3b1_calendar_assert(defined('BCF_VERSION') && BCF_VERSION === $expected_version, 'Calendar Feeds did not load the expected version.');
wave3b1_calendar_assert(defined('BVMGR_VERSION') && BVMGR_VERSION === '1.2.0', 'Canonical BVM 1.2.0 did not load.');
wave3b1_calendar_assert(defined('DRM_CI_VERSION') && DRM_CI_VERSION === '0.2.4', 'DRM Calendar Intake 0.2.4 changed.');
wave3b1_calendar_assert(defined('DRM_ER_VERSION') && DRM_ER_VERSION === '0.1.3', 'DRM Event Router 0.1.3 changed.');
wave3b1_calendar_assert(defined('DRM_EVENTS_BRIDGE_VERSION') && DRM_EVENTS_BRIDGE_VERSION === '0.2.2', 'DRM Events Bridge 0.2.2 changed.');

$active_root = dirname($calendar_entry);
$tree = wave3b1_calendar_tree_receipt($active_root);
wave3b1_calendar_assert($tree['count'] === 17, 'Active Calendar Feeds file count changed.');
wave3b1_calendar_assert(hash_equals($expected_tree_hash, $tree['sha256']), 'Active Calendar Feeds tree does not match the selected acceptance authority.');

$active_plugins = (array) get_option('active_plugins', array());
$calendar_entries = array_values(array_filter(
	$active_plugins,
	static fn($plugin): bool => is_string($plugin) && str_starts_with($plugin, 'backstage-calendar-feeds/')
));
wave3b1_calendar_assert($calendar_entries === array('backstage-calendar-feeds/backstage-calendar-feeds.php'), 'Calendar Feeds canonical activation entry changed or duplicated.');

$version_files = array(
	'vms-data-tools/vms-data-tools.php' => '0.5.55',
	'vmsx-weather-risk/vmsx-weather-risk.php' => '0.1.12',
	'vms-commerce-discounts/vms-commerce-discounts.php' => '0.2.13',
	'vms-sponsorships/vms-sponsorships.php' => '0.1.28',
);
foreach ($version_files as $relative => $version) {
	wave3b1_calendar_assert(
		wave3b1_calendar_plugin_version(WP_PLUGIN_DIR . '/' . $relative) === $version,
		'An accepted add-on version changed: ' . $relative
	);
	wave3b1_calendar_assert(in_array($relative, $active_plugins, true), 'An accepted add-on activation entry changed: ' . $relative);
}

wave3b1_calendar_assert(function_exists('bvmgr_register_admin_page'), 'The canonical BVM page registry is unavailable.');
wave3b1_calendar_assert(function_exists('bvmgr_reporting_resolve_event_ticket_sales'), 'The Wave 3A BVM reporting-provider resolver is unavailable.');
wave3b1_calendar_assert(function_exists('bvmgr_event_command_center_get_ticket_reporting_truth'), 'The P0 Event Command Center ticket path is unavailable.');
wave3b1_calendar_assert(function_exists('bvmgr_staffing_resolve_event_snapshot'), 'The P0 staffing resolver is unavailable.');
wave3b1_calendar_assert(function_exists('bvmgr_ticket_revenue_build_report'), 'The BVM ticket-revenue fallback is unavailable.');
wave3b1_calendar_assert(function_exists('drm_ci_router_contract_version') && drm_ci_router_contract_version() === 2, 'DRM Intake router contract v2 is unavailable.');
wave3b1_calendar_assert(function_exists('drm_ci_router_source_discovery_version') && drm_ci_router_source_discovery_version() === 1, 'DRM Intake source-discovery contract v1 is unavailable.');
wave3b1_calendar_assert(defined('DRM_ER_PUBLIC_CONTRACT_VERSION') && DRM_ER_PUBLIC_CONTRACT_VERSION === 2, 'Router public contract v2 is unavailable.');

$registered_providers = function_exists('bvmgr_reporting_get_registered_providers')
	? bvmgr_reporting_get_registered_providers()
	: array();
wave3b1_calendar_assert(!isset($registered_providers['vms-data-tools']), 'Skipped Data Tools 0.5.55 unexpectedly registered its provider.');
wave3b1_calendar_assert(!defined('VMS_DT_VERSION'), 'Data Tools executed even though normal acceptance skips unrelated plugins.');

ConeyProductions\BackstageCalendarFeeds\Plugin::boot();
$plugin_reflection = new ReflectionClass(ConeyProductions\BackstageCalendarFeeds\Plugin::class);
$instance_property = $plugin_reflection->getProperty('instance');
$instance_property->setAccessible(true);
$calendar_plugin = $instance_property->getValue();
wave3b1_calendar_assert($calendar_plugin instanceof ConeyProductions\BackstageCalendarFeeds\Plugin, 'Calendar Feeds did not create its runtime instance.');
$calendar_plugin->register_rewrite();

$secret_store = new ConeyProductions\BackstageCalendarFeeds\Secret_Store();
$token = $secret_store->get_token(ConeyProductions\BackstageCalendarFeeds\Plugin::PROFILE_ID);
wave3b1_calendar_assert(!is_wp_error($token), 'The existing Calendar Feeds token is not decryptable.');
wave3b1_calendar_assert(strlen((string) $token) === 43, 'The existing Calendar Feeds token shape changed.');
wave3b1_calendar_assert($secret_store->validate(ConeyProductions\BackstageCalendarFeeds\Plugin::PROFILE_ID, (string) $token), 'The existing Calendar Feeds token no longer validates.');
unset($token);

$provider = new ConeyProductions\BackstageCalendarFeeds\DRM_Calendar_Intake_Provider();
$provider_health = $provider->health();
wave3b1_calendar_assert(!is_wp_error($provider_health), 'The DRM Intake provider health check failed.');
wave3b1_calendar_assert((int) ($provider_health['contract_version'] ?? 0) === 2, 'Calendar provider contract provenance changed.');
wave3b1_calendar_assert((int) ($provider_health['source_discovery_version'] ?? 0) === 1, 'Calendar source-discovery provenance changed.');

$profile = ConeyProductions\BackstageCalendarFeeds\bcf_get_profile(ConeyProductions\BackstageCalendarFeeds\Plugin::PROFILE_ID);
wave3b1_calendar_assert(is_array($profile), 'The Calendar Feeds profile is unavailable.');
$service = new ConeyProductions\BackstageCalendarFeeds\Feed_Service(
	$provider,
	new ConeyProductions\BackstageCalendarFeeds\Strict_Availability_Policy(),
	new ConeyProductions\BackstageCalendarFeeds\Supersession_Resolver(),
	new ConeyProductions\BackstageCalendarFeeds\Publication_Ledger(),
	new ConeyProductions\BackstageCalendarFeeds\Duplicate_Detector(),
	new ConeyProductions\BackstageCalendarFeeds\ICS_Formatter()
);
$feed = $service->build($profile, null, false);
wave3b1_calendar_assert(!is_wp_error($feed), 'Read-only Calendar feed projection failed.');
$ics = (string) ($feed['ics'] ?? '');
wave3b1_calendar_assert(str_starts_with($ics, "BEGIN:VCALENDAR\r\n"), 'ICS rendering no longer starts with VCALENDAR.');
wave3b1_calendar_assert(str_ends_with($ics, "END:VCALENDAR\r\n"), 'ICS rendering no longer ends with VCALENDAR.');
$ics_bytes = strlen($ics);
unset($ics, $feed);

global $wp_rewrite;
$route = '^backstage-calendar-feeds/([A-Za-z0-9_-]{43})\.ics$';
wave3b1_calendar_assert(isset($wp_rewrite->extra_rules_top[$route]), 'The tokenized ICS rewrite is not registered.');
wave3b1_calendar_assert((string) $wp_rewrite->extra_rules_top[$route] === 'index.php?bcf_feed_token=$matches[1]', 'The tokenized ICS rewrite destination changed.');

$page_bytes = 0;
$calendar_rows = array();
$calendar_top_rows = array();
if ($mode !== 'diagnostic') {
	wp_set_current_user(1);
	wave3b1_calendar_assert(current_user_can('manage_options'), 'The acceptance administrator lacks manage_options.');
	wave3b1_calendar_assert(did_action('admin_menu') === 0, 'admin_menu ran before the bounded acceptance probe.');
	$GLOBALS['menu'] = array();
	$GLOBALS['submenu'] = array();
	do_action('admin_menu');

	$menu_rows = static function (string $parent, string $slug): array {
		$rows = isset($GLOBALS['submenu'][$parent]) && is_array($GLOBALS['submenu'][$parent])
			? $GLOBALS['submenu'][$parent]
			: array();
		return array_values(array_filter($rows, static fn($row): bool => is_array($row) && isset($row[2]) && (string) $row[2] === $slug));
	};
	$top_rows = static function (string $slug): array {
		return array_values(array_filter((array) $GLOBALS['menu'], static fn($row): bool => is_array($row) && isset($row[2]) && (string) $row[2] === $slug));
	};

	$calendar_rows = $menu_rows('vms-dashboard', 'backstage');
	$calendar_top_rows = $top_rows('backstage');
	$all_calendar_rows = array();
	foreach (array_keys((array) $GLOBALS['submenu']) as $parent) {
		$all_calendar_rows = array_merge($all_calendar_rows, $menu_rows((string) $parent, 'backstage'));
	}
	wave3b1_calendar_assert(count($calendar_rows) === 1, 'Calendar Feeds did not register exactly once under the BVM parent.');
	wave3b1_calendar_assert(count($calendar_top_rows) === 0, 'Calendar Feeds created a duplicate top-level Backstage menu.');
	wave3b1_calendar_assert(count($all_calendar_rows) === 1, 'Calendar Feeds registered more than one physical submenu.');
	wave3b1_calendar_assert((string) ($calendar_rows[0][1] ?? '') === 'manage_options', 'Calendar Feeds menu capability changed.');

	$registry = bvmgr_admin_menu_registry();
	$entry = isset($registry['backstage']) && is_array($registry['backstage']) ? $registry['backstage'] : array();
	wave3b1_calendar_assert(($entry['id'] ?? '') === 'backstage-calendar-feeds', 'Calendar Feeds registry identity changed.');
	wave3b1_calendar_assert(($entry['source'] ?? '') === 'backstage-calendar-feeds', 'Calendar Feeds registry ownership changed.');
	wave3b1_calendar_assert(($entry['section'] ?? '') === 'events_schedule', 'Calendar Feeds registry section changed.');
	wave3b1_calendar_assert(($entry['capability'] ?? '') === 'manage_options', 'Calendar Feeds registry capability changed.');
	wave3b1_calendar_assert(!empty($entry['shell']) && !empty($entry['top_nav']) && !empty($entry['directory']), 'Calendar Feeds registry discovery/shell flags changed.');
	wave3b1_calendar_assert(isset($entry['callback']) && is_callable($entry['callback']), 'Calendar Feeds page callback is not callable.');

	$direct_url = admin_url('admin.php?page=backstage');
	wave3b1_calendar_assert((string) wp_parse_url($direct_url, PHP_URL_QUERY) === 'page=backstage', 'The direct Calendar Feeds admin URL changed.');
	$page_hook = get_plugin_page_hookname('backstage', 'vms-dashboard');
	wave3b1_calendar_assert($page_hook !== '' && has_action($page_hook) !== false, 'WordPress did not attach the Calendar Feeds page callback.');

	$_GET['page'] = 'backstage';
	if (function_exists('set_current_screen')) {
		set_current_screen($page_hook);
	}
	ob_start();
	call_user_func($entry['callback']);
	$page_markup = (string) ob_get_clean();
	wave3b1_calendar_assert(str_contains($page_markup, 'bcf-admin-shell'), 'The Calendar Feeds page did not render through its BVM shell.');
	wave3b1_calendar_assert(str_contains($page_markup, 'Calendar Feeds'), 'The Calendar Feeds page rendered no recognizable heading.');
	$page_bytes = strlen($page_markup);
	unset($page_markup);
} else {
	$registry = bvmgr_admin_menu_registry();
	wave3b1_calendar_assert(isset($registry['vms-admin-pages']), 'BVM registry bootstrap did not execute in diagnostic mode.');
}

$option_fingerprint_after = wave3b1_calendar_option_fingerprint();
$active_fingerprint_after = hash('sha256', serialize(get_option('active_plugins', array())));
$cron_fingerprint_after = hash('sha256', serialize(get_option('cron', array())));
wave3b1_calendar_assert(hash_equals($option_fingerprint_before, $option_fingerprint_after), 'Calendar option state changed during read-only acceptance.');
wave3b1_calendar_assert(hash_equals($active_fingerprint_before, $active_fingerprint_after), 'Plugin activation state changed during read-only acceptance.');
wave3b1_calendar_assert(hash_equals($cron_fingerprint_before, $cron_fingerprint_after), 'Cron state changed during read-only acceptance.');

echo wp_json_encode(array(
	'status' => 'PASS',
	'mode' => $mode,
	'assertions' => (int) $GLOBALS['wave3b1_calendar_assertion_count'],
	'php_version' => PHP_VERSION,
	'calendar_version' => BCF_VERSION,
	'calendar_files' => $tree['count'],
	'calendar_tree_sha256' => $tree['sha256'],
	'active_calendar_entries' => count($calendar_entries),
	'bvm_registry_entries' => $mode !== 'diagnostic' ? 1 : 0,
	'bvm_physical_submenus' => count($calendar_rows),
	'top_level_backstage_menus' => count($calendar_top_rows),
	'page_callback_bytes' => $page_bytes,
	'ics_bytes' => $ics_bytes,
	'drm_contract_version' => (int) $provider_health['contract_version'],
	'drm_source_discovery_version' => (int) $provider_health['source_discovery_version'],
	'data_tools_version' => wave3b1_calendar_plugin_version(WP_PLUGIN_DIR . '/vms-data-tools/vms-data-tools.php'),
), JSON_UNESCAPED_SLASHES) . "\n";
