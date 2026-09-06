<?php

if (!defined('ABSPATH')) {
	fwrite(STDERR, "This probe must run through a real WordPress bootstrap.\n");
	exit(2);
}

$scenarioId = isset($args[0]) ? (string) $args[0] : '';
$addon = isset($args[1]) ? (string) $args[1] : '';
$coreExpected = isset($args[2]) && (string) $args[2] === 'yes';
$woocommerceExpected = isset($args[3]) && (string) $args[3] === 'yes';
$loadOrder = isset($args[4]) ? (string) $args[4] : 'n/a';
$contracts = require __DIR__ . '/runtime-contracts.php';
$officialAddons = array('events-slider', 'fill-dates', 'data-tools', 'express-bar', 'refer-a-friend');
$targetAddons = $addon === 'all' ? $officialAddons : array($addon);
$dataToolsDirectoryAbsent = $scenarioId === 'bvm-data-tools-directory-absent';

$result = array(
	'scenario' => $scenarioId,
	'addon' => $addon,
	'core_expected' => $coreExpected,
	'woocommerce_expected' => $woocommerceExpected,
	'load_order' => $loadOrder,
	'active_plugins' => array_values((array) get_option('active_plugins', array())),
	'checks' => array(),
	'runtime_errors' => array(),
	'doing_it_wrong' => array(),
	'identity' => array(),
	'menu' => array(),
	'notices' => '',
);

$check = static function (
	string $id,
	string $dimension,
	string $checkAddon,
	bool $passed,
	string $message,
	array $details = array()
) use (&$result): void {
	$result['checks'][] = array(
		'id' => $id,
		'dimension' => $dimension,
		'addon' => $checkAddon,
		'passed' => $passed,
		'message' => $message,
		'details' => $details,
	);
};

$relativePluginPath = static function (string $path): string {
	$normalized = str_replace('\\', '/', $path);
	$pluginRoot = rtrim(str_replace('\\', '/', WP_PLUGIN_DIR), '/') . '/';
	if (strpos($normalized, $pluginRoot) === 0) {
		return substr($normalized, strlen($pluginRoot));
	}
	$wpRoot = rtrim(str_replace('\\', '/', ABSPATH), '/') . '/';
	if (strpos($normalized, $wpRoot) === 0) {
		return 'wordpress/' . substr($normalized, strlen($wpRoot));
	}
	return basename($normalized);
};

set_error_handler(
	static function (int $severity, string $message, string $file, int $line) use (&$result, $relativePluginPath): bool {
		$result['runtime_errors'][] = array(
			'severity' => $severity,
			'message' => $message,
			'file' => $relativePluginPath($file),
			'line' => $line,
		);
		return false;
	}
);

add_action(
	'doing_it_wrong_run',
	static function ($functionName, $message, $version) use (&$result): void {
		$result['doing_it_wrong'][] = array(
			'function' => (string) $functionName,
			'message' => wp_strip_all_tags((string) $message),
			'version' => (string) $version,
		);
	},
	10,
	3
);

if (!function_exists('add_menu_page')) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}
if (!function_exists('set_current_screen')) {
	require_once ABSPATH . 'wp-admin/includes/screen.php';
}

wp_set_current_user(1);

// A real wp-admin request establishes a screen before screen-aware admin
// callbacks execute. WP-CLI does not, so provide that missing request context.
set_current_screen('dashboard');

// A fresh WooCommerce copy may schedule its onboarding redirect. This harness
// exercises integration contracts only and must not exit into an onboarding
// workflow while admin_init is under test.
add_filter('woocommerce_prevent_automatic_wizard_redirect', '__return_true', PHP_INT_MAX);

$GLOBALS['menu'] = array();
$GLOBALS['submenu'] = array();
$GLOBALS['admin_page_hooks'] = array();
$GLOBALS['_registered_pages'] = array();
$GLOBALS['_parent_pages'] = array();

$lifecycleException = null;
try {
	if (did_action('admin_init') === 0) {
		do_action('admin_init');
	}
	do_action('admin_menu', '');
} catch (Throwable $throwable) {
	$lifecycleException = array(
		'class' => get_class($throwable),
		'message' => $throwable->getMessage(),
		'file' => $relativePluginPath($throwable->getFile()),
		'line' => $throwable->getLine(),
	);
}

$check(
	'lifecycle-no-exception',
	'No Fatal',
	$addon,
	$lifecycleException === null,
	$lifecycleException === null ? 'Admin lifecycle completed without an uncaught exception.' : 'Admin lifecycle raised an exception.',
	$lifecycleException ?? array()
);

$menuRows = static function (string $parent, string $slug): array {
	$rows = isset($GLOBALS['submenu'][$parent]) && is_array($GLOBALS['submenu'][$parent])
		? $GLOBALS['submenu'][$parent]
		: array();
	return array_values(
		array_filter(
			$rows,
			static fn($row): bool => is_array($row) && isset($row[2]) && (string) $row[2] === $slug
		)
	);
};

$topRows = static function (string $slug): array {
	$rows = isset($GLOBALS['menu']) && is_array($GLOBALS['menu']) ? $GLOBALS['menu'] : array();
	return array_values(
		array_filter(
			$rows,
			static fn($row): bool => is_array($row) && isset($row[2]) && (string) $row[2] === $slug
		)
	);
};

$allSlugRows = static function (string $slug) use ($menuRows, $topRows): array {
	$rows = array();
	foreach ($topRows($slug) as $row) {
		$rows[] = array('parent' => '__top__', 'row' => $row);
	}
	foreach ((array) $GLOBALS['submenu'] as $parent => $_unused) {
		foreach ($menuRows((string) $parent, $slug) as $row) {
			$rows[] = array('parent' => (string) $parent, 'row' => $row);
		}
	}
	return $rows;
};

$hookFor = static function (string $slug, string $parent): string {
	return get_plugin_page_hookname($slug, $parent);
};

$callbackAttached = static function (string $hook, $callback): bool {
	return $hook !== '' && has_action($hook, $callback) !== false && is_callable($callback);
};

$result['menu'] = array(
	'top' => array_values(array_map(static fn($row): string => isset($row[2]) ? (string) $row[2] : '', (array) $GLOBALS['menu'])),
	'bvm' => array_values(array_map(static fn($row): string => isset($row[2]) ? (string) $row[2] : '', (array) ($GLOBALS['submenu']['vms-dashboard'] ?? array()))),
	'tools' => array_values(array_map(static fn($row): string => isset($row[2]) ? (string) $row[2] : '', (array) ($GLOBALS['submenu']['tools.php'] ?? array()))),
);

ob_start();
try {
	do_action('admin_notices');
} catch (Throwable $throwable) {
	$check(
		'admin-notices-no-exception',
		'Notices',
		$addon,
		false,
		'admin_notices raised an exception.',
		array('class' => get_class($throwable), 'message' => $throwable->getMessage())
	);
}
$nativeNotices = (string) ob_get_clean();
$result['notices'] = preg_replace('/\s+/', ' ', wp_strip_all_tags($nativeNotices)) ?: '';

$coreLoaded = defined('BVMGR_PLUGIN_FILE') && defined('BVMGR_VERSION');
$bvmFile = $coreLoaded ? (string) BVMGR_PLUGIN_FILE : '';
$result['identity'] = array(
	'bvm_active' => $coreLoaded,
	'bvm_plugin_basename' => $bvmFile !== '' ? plugin_basename($bvmFile) : '',
	'bvm_version' => defined('BVMGR_VERSION') ? (string) BVMGR_VERSION : '',
	'bvm_plugin_file' => $bvmFile !== '' ? 'wp-content/plugins/' . plugin_basename($bvmFile) : '',
	'historical_main_exists' => is_file(WP_PLUGIN_DIR . '/vms/vendor-management-system.php'),
	'nonexistent_bootstraps' => array(
		'vms.php' => is_file(WP_PLUGIN_DIR . '/vms.php'),
		'vms/vms.php' => is_file(WP_PLUGIN_DIR . '/vms/vms.php'),
		'backstage-venue-manager.php' => is_file(WP_PLUGIN_DIR . '/backstage-venue-manager.php'),
	),
);

$check('core-presence', 'BVM Recognized', $addon, $coreLoaded === $coreExpected, 'BVM runtime presence matched the scenario.');
if ($coreExpected) {
	$check('public-basename', 'BVM Recognized', $addon, $result['identity']['bvm_plugin_basename'] === 'backstage-venue-manager/backstage-venue-manager.php', 'BVM used its public plugin basename.', $result['identity']);
	$check('public-version', 'BVM Recognized', $addon, $result['identity']['bvm_version'] === '1.2.0', 'BVM exposed version 1.2.0.');
	$check('historical-core-absent', 'BVM Recognized', $addon, !$result['identity']['historical_main_exists'], 'Historical standalone VMS core was absent.');
	$check('nonexistent-bootstrap-identities-absent', 'BVM Recognized', $addon, !in_array(true, $result['identity']['nonexistent_bootstraps'], true), 'Nonexistent bootstrap identities were absent.');
	$check('historical-core-not-active', 'BVM Recognized', $addon, !in_array('vms/vendor-management-system.php', $result['active_plugins'], true), 'Historical standalone VMS core was not active.');
}

$loadedMarkers = array(
	'events-slider' => defined('VMS_EVENTS_SLIDER_VERSION'),
	'fill-dates' => defined('VMS_FILL_DATES_VERSION'),
	'data-tools' => defined('VMS_DT_VERSION'),
	'express-bar' => defined('VMSEB_VERSION'),
	'refer-a-friend' => defined('VMS_RAF_VERSION'),
);

$dataToolsActive = in_array('vms-data-tools/vms-data-tools.php', $result['active_plugins'], true);
if (!$dataToolsActive) {
	$dataToolsIncludedFiles = array_values(array_filter(
		get_included_files(),
		static fn(string $file): bool => strpos(str_replace('\\', '/', $file), '/wp-content/plugins/vms-data-tools/') !== false
	));
	$registeredReportingProviders = function_exists('bvmgr_reporting_get_registered_providers')
		? bvmgr_reporting_get_registered_providers()
		: array();
	$check(
		'data-tools-inactive-source-dormant',
		'No Fatal',
		$addon,
		!defined('VMS_DT_VERSION')
			&& !function_exists('vms_dt_init')
			&& !function_exists('vms_dt_bvm_reporting_provider')
			&& has_action('init', 'vms_dt_boot_plugin') === false
			&& $dataToolsIncludedFiles === array(),
		'Data Tools implementation source and bootstrap hooks stayed dormant while inactive.',
		array('included_files' => $dataToolsIncludedFiles)
	);
	$check(
		'data-tools-inactive-provider-absent',
		'BVM Recognized',
		$addon,
		!isset($registeredReportingProviders['vms-data-tools']),
		'Inactive Data Tools did not register a reporting provider.'
	);
}

if ($dataToolsDirectoryAbsent) {
	$missingResult = function_exists('bvmgr_reporting_resolve_event_ticket_sales')
		? bvmgr_reporting_resolve_event_ticket_sales(2534, array('scope' => 'event_command_center'))
		: array('available' => false);
	$check('data-tools-directory-absent', 'Core-Absent Behavior', 'data-tools', !is_dir(WP_PLUGIN_DIR . '/vms-data-tools'), 'The directory-absent scenario removed Data Tools from the disposable filesystem.');
	$check('data-tools-directory-absent-safe-result', 'Core-Absent Behavior', 'data-tools', empty($missingResult['available']) && empty($missingResult['provider_attempts']), 'BVM returned its safe provider-unavailable result without a Data Tools directory.');
}

$capabilityAvailable = static function (string $kind, string $name): bool {
	if ($kind === 'function') {
		return function_exists($name);
	}
	if ($kind === 'class') {
		return class_exists($name);
	}
	return defined($name);
};

$providerSelection = static function (string $targetAddon, string $kind, array $capability) use ($contracts, $capabilityAvailable): array {
	$historical = (string) ($capability['historical_fallback'] ?? '');
	$canonical = (string) ($capability['canonical'] ?? '');
	$canonicalAvailable = $capabilityAvailable($kind, $canonical);
	$historicalAvailable = $capabilityAvailable($kind, $historical);
	$resolver = (string) ($contracts['provider_resolvers'][$targetAddon][$kind] ?? '');
	$selected = false;
	$resolved = '';

	if ($canonicalAvailable && $resolver !== '' && is_callable($resolver)) {
		if ($kind === 'constant') {
			$selected = !$historicalAvailable && $resolver($historical, null) === constant($canonical);
			$resolved = $selected ? $canonical : '';
		} else {
			$resolved = (string) $resolver($historical);
			$selected = $resolved === $canonical;
		}
	} elseif ($targetAddon === 'express-bar' && $canonicalAvailable && !$historicalAvailable) {
		// Express Bar uses explicit canonical guards before its historical guards.
		// The immutable source fingerprint and the exercised public outcome below
		// jointly prove that only the canonical provider is available to that path.
		$selected = function_exists('vmseb_is_vms_active') && vmseb_is_vms_active();
		$resolved = $selected ? $canonical : '';
	}

	return array(
		'historical' => $historical,
		'canonical' => $canonical,
		'canonical_available' => $canonicalAvailable,
		'historical_available' => $historicalAvailable,
		'resolver' => $resolver,
		'resolved' => $resolved,
		'selected' => $selected,
		'requirement_path' => $capability['requirement_path'] ?? '',
		'reconciliation_classification' => $capability['reconciliation_classification'] ?? '',
	);
};

foreach ($targetAddons as $targetAddon) {
	$check('addon-loaded-' . $targetAddon, 'No Fatal', $targetAddon, !empty($loadedMarkers[$targetAddon]) || ($targetAddon === 'data-tools' && $dataToolsDirectoryAbsent), $dataToolsDirectoryAbsent ? 'The intentional directory-absent fixture remained unloaded.' : 'The add-on bootstrap completed.');
	if (!$coreExpected || ($targetAddon === 'data-tools' && $dataToolsDirectoryAbsent)) {
		continue;
	}

	$providerDetails = array();
	$missingCanonical = array();
	$wrongProvider = array();
	$declaredHistoricalFallbacks = array();
	foreach (array('functions' => 'function', 'classes' => 'class', 'constants' => 'constant') as $pluralKind => $kind) {
		foreach ((array) ($contracts['capabilities'][$targetAddon][$pluralKind] ?? array()) as $capability) {
			$selection = $providerSelection($targetAddon, $kind, (array) $capability);
			$providerDetails[] = $selection;
			if (!$selection['canonical_available']) {
				$missingCanonical[] = $selection['canonical'];
			}
			if (!$selection['selected']) {
				$wrongProvider[] = $selection['historical'];
			}
			if ($selection['historical_available']) {
				$declaredHistoricalFallbacks[] = $selection['historical'];
			}
		}
	}

	$check('canonical-capabilities-' . $targetAddon, 'BVM Recognized', $targetAddon, $missingCanonical === array(), 'All current canonical BVM capabilities were available.', array('missing' => $missingCanonical));
	$check('canonical-provider-' . $targetAddon, 'BVM Recognized', $targetAddon, $wrongProvider === array(), 'The add-on selected canonical BVM as its runtime provider.', array('unresolved' => $wrongProvider, 'capabilities' => $providerDetails));
	$check('legacy-fallbacks-not-required-' . $targetAddon, 'BVM Recognized', $targetAddon, $declaredHistoricalFallbacks === array(), 'Historical fallback declarations were not required from canonical BVM.', array('declared' => $declaredHistoricalFallbacks));

	$missingHooks = array();
	foreach ((array) ($contracts['hook_callbacks'][$targetAddon] ?? array()) as $hook => $callback) {
		if (is_array($callback) && isset($callback[0], $callback[1]) && is_string($callback[0]) && method_exists($callback[0], 'instance')) {
			$callback = array($callback[0]::instance(), $callback[1]);
		}
		if (has_filter((string) $hook, $callback) === false) {
			$missingHooks[] = (string) $hook;
		}
	}
	$check('runtime-hooks-' . $targetAddon, 'BVM Recognized', $targetAddon, $missingHooks === array(), 'Consumed BVM hook contracts had the add-on callbacks attached.', array('missing' => $missingHooks));
}

if ($coreExpected && in_array('fill-dates', $targetAddons, true)) {
	$rows = $menuRows('vms-dashboard', 'vms-fill-dates');
	$hook = function_exists('vms_fd_admin_page_hook') ? vms_fd_admin_page_hook() : '';
	$check('fill-dates-menu-single', 'Menu', 'fill-dates', count($rows) === 1 && count($allSlugRows('vms-fill-dates')) === 1, 'Fill Dates had one BVM submenu and no duplicate top-level menu.');
	$check('fill-dates-menu-capability', 'Menu', 'fill-dates', isset($rows[0][1]) && $rows[0][1] === 'manage_options', 'Fill Dates preserved its capability.');
	$check('fill-dates-hook-stored', 'Menu', 'fill-dates', $hook !== '' && $hook === $hookFor('vms-fill-dates', 'vms-dashboard'), 'Fill Dates stored WordPress\'s returned submenu hook.', array('hook' => $hook));
	$check('fill-dates-callback', 'Menu', 'fill-dates', $callbackAttached($hook, 'vms_fd_render_admin_page'), 'Fill Dates menu callback resolved.');
	wp_dequeue_style('vms-fill-dates-admin');
	do_action('admin_enqueue_scripts', $hook);
	$check('fill-dates-assets', 'Menu', 'fill-dates', wp_style_is('vms-fill-dates-admin', 'enqueued'), 'Fill Dates assets recognized the returned hook.');
	$tours = apply_filters('vms_register_tours', array());
	$tourContextMatches = false;
	foreach ((array) $tours as $tour) {
		if (!is_array($tour) || ($tour['id'] ?? '') !== 'vms_fill_dates_overview') {
			continue;
		}
		$context = isset($tour['contexts'][0]) && is_array($tour['contexts'][0]) ? $tour['contexts'][0] : array();
		$tourContextMatches = ($context['screen_id'] ?? '') === $hook && ($context['page_hook'] ?? '') === $hook;
	}
	$check('fill-dates-tour-hook', 'Menu', 'fill-dates', $tourContextMatches, 'Fill Dates tours recognized the returned hook.');
	$check('fill-dates-core-recognition', 'BVM Recognized', 'fill-dates', function_exists('vms_fd_vms_ready') && vms_fd_vms_ready(), 'Fill Dates recognized BVM post types.');
	$check('fill-dates-no-false-notice', 'Notices', 'fill-dates', strpos($nativeNotices, 'requires Backstage Venue Manager') === false, 'Fill Dates emitted no false missing-BVM notice.');

	$fixtureDayOffset = 30 + (abs(crc32($scenarioId)) % 300);
	$fixtureDate = wp_date('Y-m-d', strtotime('+' . $fixtureDayOffset . ' days'), wp_timezone());
	$fixturePlanId = wp_insert_post(array(
		'post_type' => 'vms_event_plan',
		'post_status' => 'draft',
		'post_title' => 'BVM compatibility fixture plan',
	), true);
	$fixtureCreated = !is_wp_error($fixturePlanId) && (int) $fixturePlanId > 0;
	$canonicalEvents = array();
	$fillEvents = array();
	$canonicalLimits = array();
	$fillLimits = array();
	if ($fixtureCreated) {
		$fixturePlanId = (int) $fixturePlanId;
		$dateKey = (string) bvmgr_meta_key('event_plan', 'date');
		$statusKey = (string) bvmgr_meta_key('event_plan', 'status');
		$slotLimitsKey = (string) bvmgr_meta_key('event_plan', 'slot_limits');
		update_post_meta($fixturePlanId, $dateKey !== '' ? $dateKey : '_vms_event_date', $fixtureDate);
		update_post_meta($fixturePlanId, $statusKey !== '' ? $statusKey : '_vms_event_plan_status', 'draft');
		update_post_meta($fixturePlanId, $slotLimitsKey !== '' ? $slotLimitsKey : '_vms_slot_limits', array('food_truck' => 1));
		bvmgr_calendar_feed_cache_bust();
		$calendarArgs = array(
			'start_date' => $fixtureDate,
			'end_date' => $fixtureDate,
			'context' => 'admin',
			'include_past' => true,
			'include_statuses' => array('draft', 'ready', 'published', 'tentative', 'confirmed'),
		);
		$canonicalEvents = bvmgr_get_calendar_events($calendarArgs);
		$fillEvents = vms_fd_collect_events($fixtureDate, $fixtureDate);
		$canonicalLimits = bvmgr_calendar_get_event_slot_limits($fixturePlanId, 0);
		$fillLimits = vms_fd_get_secondary_open_slots($fixturePlanId, 0, 'food_truck', 0);
	}
	$eventPlanIds = static function (array $events): array {
		$ids = array();
		foreach ($events as $event) {
			if (is_array($event) && !empty($event['event_plan_id'])) {
				$ids[] = absint($event['event_plan_id']);
			}
		}
		sort($ids);
		return array_values(array_unique($ids));
	};
	$canonicalEventIds = $eventPlanIds($canonicalEvents);
	$fillEventIds = $eventPlanIds($fillEvents);
	$check('fill-dates-canonical-calendar-outcome', 'BVM Recognized', 'fill-dates', $fixtureCreated && in_array((int) $fixturePlanId, $canonicalEventIds, true) && $fillEventIds === $canonicalEventIds, 'Fill Dates returned the same current-event population as canonical BVM.', array('canonical_ids' => $canonicalEventIds, 'fill_dates_ids' => $fillEventIds));
	$check('fill-dates-canonical-slot-outcome', 'BVM Recognized', 'fill-dates', ($canonicalLimits['food_truck'] ?? 0) === 1 && ($fillLimits['max'] ?? 0) === 1 && ($fillLimits['open'] ?? 0) === 1 && !empty($fillLimits['limited']), 'Fill Dates matched canonical BVM slot limits.', array('canonical' => $canonicalLimits, 'fill_dates' => $fillLimits));
	$registeredModule = function_exists('bvmgr_get_registered_module') ? bvmgr_get_registered_module('fill_dates') : null;
	$check('fill-dates-canonical-module-registration', 'BVM Recognized', 'fill-dates', is_array($registeredModule) && ($registeredModule['source'] ?? '') === 'addon', 'Fill Dates registered its module through canonical BVM.', array('module' => $registeredModule));
	$helpMarkup = function_exists('vms_fd_help_button') ? vms_fd_help_button() : '';
	$check('fill-dates-canonical-shell-help-tour', 'BVM Recognized', 'fill-dates', vms_fd_core_function('vms_admin_ui_render_shell') === 'bvmgr_admin_ui_render_shell' && vms_fd_core_function('vms_render_help_button') === 'bvmgr_render_help_button' && vms_fd_core_class('VMS_Tours_Service') === 'BVMGR_Tours_Service' && $helpMarkup !== '', 'Fill Dates selected canonical shell, guided help, and tour providers.');
	$safeMutationProviders = array(
		'cache' => vms_fd_core_function('vms_calendar_feed_cache_bust'),
		'review_get' => vms_fd_core_function('vms_event_plan_review_get_changes'),
		'review_touch' => vms_fd_core_function('vms_event_plan_review_touch'),
		'secondary_vendors' => vms_fd_core_function('vms_event_plan_set_secondary_vendors'),
	);
	$check('fill-dates-canonical-safe-mutation-selection', 'BVM Recognized', 'fill-dates', $safeMutationProviders === array(
		'cache' => 'bvmgr_calendar_feed_cache_bust',
		'review_get' => 'bvmgr_event_plan_review_get_changes',
		'review_touch' => 'bvmgr_event_plan_review_touch',
		'secondary_vendors' => 'bvmgr_event_plan_set_secondary_vendors',
	), 'Fill Dates selected canonical review, secondary-vendor, and cache callables without exercising assignment/review writes.', $safeMutationProviders);
	if ($fixtureCreated) {
		wp_delete_post((int) $fixturePlanId, true);
		bvmgr_calendar_feed_cache_bust();
	}
}

if ($coreExpected && in_array('data-tools', $targetAddons, true) && !$dataToolsDirectoryAbsent) {
	$bvmRows = $menuRows('vms-dashboard', 'vms-data-tools');
	$toolsRows = $menuRows('tools.php', 'vms-data-tools');
	$hook = $hookFor('vms-data-tools', 'vms-dashboard');
	$check('data-tools-core-recognition', 'BVM Recognized', 'data-tools', function_exists('vms_dt_is_vms_core_active') && vms_dt_is_vms_core_active(), 'Data Tools recognized BVM through its feature contract.');
	$check('data-tools-bvm-menu-single', 'Menu', 'data-tools', count($bvmRows) === 1, 'Data Tools had one usable BVM entry.');
	$check('data-tools-tools-menu-removed', 'Menu', 'data-tools', count($toolsRows) === 0 && count($allSlugRows('vms-data-tools')) === 1, 'The complete lifecycle removed the duplicate Tools entry.');
	$check('data-tools-capability', 'Menu', 'data-tools', isset($bvmRows[0][1]) && $bvmRows[0][1] === 'read', 'The BVM Data Tools bridge kept its intended read capability.');
	$check('data-tools-bridge-callback', 'Menu', 'data-tools', $callbackAttached($hook, 'bvmgr_admin_ui_render_data_tools_page') && is_callable('vms_dt_render_tools_home'), 'The canonical BVM Data Tools bridge and companion callback resolved.');
	$dataReportProvider = function_exists('vms_dt_core_function') ? vms_dt_core_function('vms_ticket_revenue_build_report') : '';
	$check('data-tools-canonical-surface', 'BVM Recognized', 'data-tools', vms_dt_core_function('vms_core') === 'bvmgr_core' && $dataReportProvider === 'bvmgr_ticket_revenue_build_report' && is_callable($dataReportProvider) && is_callable('vms_dt_render_tools_home'), 'Data Tools retained its canonical dependency, menu, and report surface.', array('report_provider' => $dataReportProvider));
	$check('data-tools-no-false-notice', 'Notices', 'data-tools', strpos($nativeNotices, 'VMS Core is not detected') === false, 'Data Tools emitted no false missing-core notice.');

	$reportingProviders = function_exists('bvmgr_reporting_get_registered_providers') ? bvmgr_reporting_get_registered_providers() : array();
	$dataToolsProvider = (array) ($reportingProviders['vms-data-tools'] ?? array());
	$check('data-tools-provider-single', 'BVM Recognized', 'data-tools', count(array_filter(array_keys($reportingProviders), static fn(string $id): bool => $id === 'vms-data-tools')) === 1, 'Data Tools registered exactly one reporting provider.');
	$check('data-tools-provider-provenance', 'BVM Recognized', 'data-tools', ($dataToolsProvider['version'] ?? '') === '0.5.55' && ($dataToolsProvider['contract_version'] ?? 0) === 1, 'The reporting provider exposed candidate version and contract provenance.', $dataToolsProvider);

	$fixturePlanId = wp_insert_post(array(
		'post_type' => 'vms_event_plan',
		'post_status' => 'draft',
		'post_title' => 'Data Tools provider empty-result fixture',
	), true);
	$emptyProviderResult = array();
	$vendorProviderResult = array();
	if (!is_wp_error($fixturePlanId) && (int) $fixturePlanId > 0) {
		$fixturePlanId = (int) $fixturePlanId;
		update_post_meta($fixturePlanId, '_vms_event_date', wp_date('Y-m-d', current_time('timestamp'), wp_timezone()));
		$emptyProviderResult = bvmgr_reporting_resolve_event_ticket_sales($fixturePlanId, array('scope' => 'event_command_center'));
		$vendorProviderResult = bvmgr_reporting_resolve_event_ticket_sales($fixturePlanId, array('scope' => 'vendor_portal'));
		wp_delete_post($fixturePlanId, true);
	}
	$check('data-tools-provider-valid-empty', 'BVM Recognized', 'data-tools', !empty($emptyProviderResult['available']) && !empty($emptyProviderResult['calculated']) && ($emptyProviderResult['provider_id'] ?? '') === 'vms-data-tools' && (int) ($emptyProviderResult['total_qty'] ?? -1) === 0, 'The active provider returned a valid calculated empty Event Command Center result.', $emptyProviderResult);
	$check('data-tools-provider-vendor-portal', 'BVM Recognized', 'data-tools', !empty($vendorProviderResult['available']) && !empty($vendorProviderResult['calculated']) && ($vendorProviderResult['source'] ?? '') === 'data_tools_merged_ticket_sales', 'The active provider resolved the vendor-portal website/Square reporting scope.', $vendorProviderResult);
}

if ($coreExpected && in_array('express-bar', $targetAddons, true)) {
	$expressRows = $menuRows('vms-dashboard', 'vms-express-bar');
	$barRows = $menuRows('vms-dashboard', 'vms-bar-menu');
	$expressHook = $hookFor('vms-express-bar', 'vms-dashboard');
	$barHook = $hookFor('vms-bar-menu', 'vms-dashboard');
	$storedHooks = function_exists('vmseb_admin_page_hooks') ? vmseb_admin_page_hooks() : array();
	$check('express-core-recognition', 'BVM Recognized', 'express-bar', function_exists('vmseb_is_vms_active') && vmseb_is_vms_active(), 'Express Bar recognized BVM.');
	$check('express-menu-single', 'Menu', 'express-bar', count($expressRows) === 1 && count($barRows) === 1 && count($allSlugRows('vms-express-bar')) === 1 && count($allSlugRows('vms-bar-menu')) === 1, 'Express Bar attached two single BVM submenus with no rogue top level.');
	$check('express-returned-hooks-stored', 'Menu', 'express-bar', ($storedHooks['vms-express-bar'] ?? '') === $expressHook && ($storedHooks['vms-bar-menu'] ?? '') === $barHook, 'Express Bar stored the opaque hook suffixes returned by WordPress.', array('expected' => array($expressHook, $barHook), 'stored' => $storedHooks));
	wp_dequeue_style('vmseb-admin');
	wp_dequeue_script('vmseb-admin');
	do_action('admin_enqueue_scripts', $expressHook);
	$expressAssets = wp_style_is('vmseb-admin', 'enqueued') && wp_script_is('vmseb-admin', 'enqueued');
	wp_dequeue_style('vmseb-admin');
	wp_dequeue_script('vmseb-admin');
	do_action('admin_enqueue_scripts', $barHook);
	$barAssets = wp_style_is('vmseb-admin', 'enqueued') && wp_script_is('vmseb-admin', 'enqueued');
	$check('express-assets-current-hooks', 'Menu', 'express-bar', $expressAssets && $barAssets, 'Express Bar assets loaded on both actual WordPress hooks.');
	wp_dequeue_style('vmseb-admin');
	wp_dequeue_script('vmseb-admin');
	do_action('admin_enqueue_scripts', 'dashboard');
	$check('express-assets-unrelated-screen', 'Menu', 'express-bar', !wp_style_is('vmseb-admin', 'enqueued') && !wp_script_is('vmseb-admin', 'enqueued'), 'Express Bar assets stayed off unrelated admin screens.');
	$fixturePostId = wp_insert_post(array('post_type' => 'post', 'post_status' => 'draft', 'post_title' => 'Express Bar compatibility fixture'), true);
	$fixturePlanId = wp_insert_post(array('post_type' => 'vms_event_plan', 'post_status' => 'draft', 'post_title' => 'Express Bar mapped fixture plan'), true);
	$publicRuntimeWorks = false;
	if (!is_wp_error($fixturePostId) && !is_wp_error($fixturePlanId) && (int) $fixturePostId > 0 && (int) $fixturePlanId > 0) {
		update_post_meta((int) $fixturePlanId, '_vms_tec_event_id', (int) $fixturePostId);
		$publicRuntimeWorks = vmseb_resolve_event_plan_id_from_post(get_post((int) $fixturePostId)) === (int) $fixturePlanId;
	}
	$check('express-canonical-public-runtime', 'BVM Recognized', 'express-bar', $publicRuntimeWorks && shortcode_exists('vms_express_bar_menu'), 'Express Bar public runtime resolved an Event Plan through canonical BVM and retained its shortcode.');
	if (!is_wp_error($fixturePlanId) && (int) $fixturePlanId > 0) {
		wp_delete_post((int) $fixturePlanId, true);
	}
	if (!is_wp_error($fixturePostId) && (int) $fixturePostId > 0) {
		wp_delete_post((int) $fixturePostId, true);
	}
	$check('express-woocommerce-state', 'Notices', 'express-bar', function_exists('vmseb_is_woocommerce_active') && vmseb_is_woocommerce_active() === $woocommerceExpected, 'Express Bar evaluated WooCommerce independently.');
	$check('express-no-false-bvm-notice', 'Notices', 'express-bar', strpos($nativeNotices, 'requires the VMS core plugin') === false, 'Express Bar emitted no false BVM dependency warning.');
	if ($woocommerceExpected) {
		$check('express-no-false-woocommerce-notice', 'Notices', 'express-bar', strpos($nativeNotices, 'requires WooCommerce') === false, 'Express Bar emitted no false WooCommerce dependency warning.');
	}
}

if ($coreExpected && in_array('refer-a-friend', $targetAddons, true)) {
	$rafSlugs = array('vms-raf', 'vms-raf-rewards', 'vms-raf-claims', 'vms-raf-referrals', 'vms-raf-settings', 'vms-raf-help');
	$registry = function_exists('bvmgr_admin_menu_registry') ? bvmgr_admin_menu_registry() : array();
	$registryOwnsRoutes = true;
	$menusSingle = true;
	foreach ($rafSlugs as $slug) {
		$registryOwnsRoutes = $registryOwnsRoutes && isset($registry[$slug]) && ($registry[$slug]['source'] ?? '') === 'vms-refer-a-friend';
		$menusSingle = $menusSingle && count($menuRows('vms-dashboard', $slug)) === 1 && count($allSlugRows($slug)) === 1;
	}
	$check('raf-registry-routes', 'Menu', 'refer-a-friend', $registryOwnsRoutes, 'BVM registry integration owned all intended RAF routes.');
	$check('raf-menu-single', 'Menu', 'refer-a-friend', $menusSingle && count($topRows('vms-raf')) === 0, 'RAF exposed registry-owned BVM submenus without a standalone top-level menu.');
	$rafCalendarUrl = function_exists('vms_raf_call_core_function') ? (string) vms_raf_call_core_function('vms_get_public_event_calendar_url') : '';
	$check('raf-canonical-surface', 'BVM Recognized', 'refer-a-friend', vms_raf_core_function('vms_register_admin_page') === 'bvmgr_register_admin_page' && vms_raf_core_function('vms_admin_ui_render_shell') === 'bvmgr_admin_ui_render_shell' && vms_raf_core_function('vms_get_public_event_calendar_url') === 'bvmgr_get_public_event_calendar_url' && $rafCalendarUrl !== '' && class_exists('VMS_RAF_Plugin'), 'RAF retained its canonical registry, shell, calendar URL, and records/admin surface.', array('calendar_url' => $rafCalendarUrl));
	$check('raf-no-false-notice', 'Notices', 'refer-a-friend', stripos($nativeNotices, 'refer-a-friend') === false, 'RAF introduced no BVM dependency warning.');
}

if ($coreExpected && in_array('events-slider', $targetAddons, true)) {
	$sliderMenuRows = array_filter(
		$allSlugRows('vms-events-slider'),
		static fn(array $row): bool => true
	);
	$check('events-slider-no-menu-dependency', 'Menu', 'events-slider', $sliderMenuRows === array(), 'Events Slider created no BVM admin-menu dependency.');
	$check('events-slider-no-false-notice', 'Notices', 'events-slider', stripos($nativeNotices, 'events slider') === false, 'Events Slider introduced no BVM dependency warning.');
	$sliderAtts = vms_events_slider_normalize_atts(array('limit' => 1));
	$sliderCacheKey = vms_events_slider_cache_key($sliderAtts);
	delete_transient($sliderCacheKey);
	$sliderFirstRender = vms_events_slider_shortcode($sliderAtts);
	$sliderSecondRender = vms_events_slider_shortcode($sliderAtts);
	$check('events-slider-public-render', 'BVM Recognized', 'events-slider', is_string($sliderFirstRender) && $sliderFirstRender !== '' && strpos($sliderFirstRender, 'VMS Events Slider') !== false, 'Events Slider produced its current public render outcome.');
	$check('events-slider-cache-path', 'BVM Recognized', 'events-slider', get_transient($sliderCacheKey) !== false && strpos($sliderSecondRender, 'cache: HIT') !== false, 'Events Slider exercised a cached public render through canonical BVM compatibility.');
	delete_transient($sliderCacheKey);
}

if (!$coreExpected && $addon === 'events-slider') {
	$check('events-slider-standalone-shortcode', 'Core-Absent Behavior', 'events-slider', shortcode_exists('vms_events_slider') && shortcode_exists('serenade_events_slider'), 'Events Slider retained its TEC-oriented shortcode behavior without BVM.');
	$check('events-slider-standalone-notice', 'Notices', 'events-slider', stripos($nativeNotices, 'BVM') === false && stripos($nativeNotices, 'VMS core') === false, 'Events Slider emitted no optional-BVM dependency warning.');
}

if (!$coreExpected && $addon === 'fill-dates') {
	$check('fill-dates-missing-core-recognition', 'Core-Absent Behavior', 'fill-dates', function_exists('vms_fd_vms_ready') && !vms_fd_vms_ready(), 'Fill Dates recognized that BVM was absent.');
	$check('fill-dates-native-dependency-notice', 'Notices', 'fill-dates', substr_count($nativeNotices, 'requires Backstage Venue Manager (BVM)') === 1 && substr_count($nativeNotices, 'notice notice-error') === 1, 'Fill Dates emitted exactly one native BVM dependency warning.');
	$check('fill-dates-dependency-copy', 'Core-Absent Behavior', 'fill-dates', strpos($nativeNotices, 'Activate VMS') === false, 'Fill Dates accurately named Backstage Venue Manager.');
}

if (!$coreExpected && $addon === 'data-tools' && !$dataToolsDirectoryAbsent) {
	$modulesStayedOut = !function_exists('vms_dt_render_tools_home') && has_action('admin_menu', 'vms_dt_register_admin_menu') === false;
	$check('data-tools-missing-core-recognition', 'Core-Absent Behavior', 'data-tools', function_exists('vms_dt_is_vms_core_active') && !vms_dt_is_vms_core_active(), 'Data Tools recognized that BVM was absent.');
	$check('data-tools-dependent-modules-skipped', 'Core-Absent Behavior', 'data-tools', $modulesStayedOut, 'Data Tools skipped BVM-dependent runtime modules.');
	$check('data-tools-missing-core-notice', 'Notices', 'data-tools', substr_count($nativeNotices, 'VMS Core is not detected') === 1, 'Data Tools emitted its dependency/bootstrap warning once.');
}

if (!$coreExpected && $addon === 'express-bar') {
	$check('express-missing-core-recognition', 'Core-Absent Behavior', 'express-bar', function_exists('vmseb_is_vms_active') && !vmseb_is_vms_active(), 'Express Bar recognized that BVM was absent.');
	$check('express-missing-core-notice', 'Notices', 'express-bar', substr_count($nativeNotices, 'requires the VMS core plugin') === 1, 'Express Bar emitted its BVM dependency warning once.');
	$check('express-core-not-woocommerce-confusion', 'Core-Absent Behavior', 'express-bar', !$woocommerceExpected || strpos($nativeNotices, 'requires WooCommerce') === false, 'Express Bar did not misclassify missing BVM as missing WooCommerce.');
}

if (!$coreExpected && $addon === 'refer-a-friend') {
	$rafSlugs = array('vms-raf', 'vms-raf-rewards', 'vms-raf-claims', 'vms-raf-referrals', 'vms-raf-settings', 'vms-raf-help');
	$fallbackComplete = count($topRows('vms-raf')) === 1;
	foreach (array_slice($rafSlugs, 1) as $slug) {
		$fallbackComplete = $fallbackComplete && count($menuRows('vms-raf', $slug)) === 1;
	}
	$check('raf-standalone-fallback-menu', 'Core-Absent Behavior', 'refer-a-friend', $fallbackComplete, 'RAF used its intended standalone top-level and child menus.');
	$check('raf-standalone-no-dependency-notice', 'Notices', 'refer-a-friend', stripos($nativeNotices, 'refer-a-friend') === false, 'RAF emitted no false BVM dependency warning.');
}

if ($coreExpected && $scenarioId === 'bvm-without-woocommerce-express-bar') {
	$check('express-bvm-present-woocommerce-absent', 'Notices', 'express-bar', function_exists('vmseb_is_vms_active') && vmseb_is_vms_active() && function_exists('vmseb_is_woocommerce_active') && !vmseb_is_woocommerce_active(), 'Express Bar distinguished present BVM from absent WooCommerce.');
	$check('express-woocommerce-only-notice', 'Notices', 'express-bar', substr_count($nativeNotices, 'requires WooCommerce') === 1 && strpos($nativeNotices, 'requires the VMS core plugin') === false, 'Express Bar emitted only its WooCommerce dependency warning.');
}

$compatibilityRuntimeErrors = array_values(
	array_filter(
		$result['runtime_errors'],
		static function (array $error): bool {
			$file = (string) ($error['file'] ?? '');
			$severity = (int) ($error['severity'] ?? 0);
			$isOwned = preg_match('#^(backstage-venue-manager|vms-events-slider|vms-fill-dates|vms-data-tools|vms-express-bar|vms-refer-a-friend)/#', $file) === 1;
			$isActionableSeverity = in_array($severity, array(E_WARNING, E_NOTICE, E_USER_WARNING, E_USER_NOTICE, E_RECOVERABLE_ERROR), true);
			return $isOwned && $isActionableSeverity;
		}
	)
);
$check('owned-runtime-warnings', 'No Fatal', $addon, $compatibilityRuntimeErrors === array(), 'No official-five/BVM runtime warning or notice was captured during the exercised lifecycle.', array('errors' => $compatibilityRuntimeErrors));
$check('doing-it-wrong', 'No Fatal', $addon, $result['doing_it_wrong'] === array(), 'No WordPress doing_it_wrong event was captured during the exercised lifecycle.', array('events' => $result['doing_it_wrong']));

restore_error_handler();

$encoded = base64_encode((string) wp_json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
echo "BVM_COMPAT_RESULT_JSON={$encoded}\n";
