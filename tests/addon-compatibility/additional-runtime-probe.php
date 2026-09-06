<?php

if (!defined('ABSPATH')) {
	fwrite(STDERR, "This probe must run through a real WordPress bootstrap.\n");
	exit(2);
}

$scenarioId = isset($args[0]) ? (string) $args[0] : '';
$addon = isset($args[1]) ? (string) $args[1] : '';
$coreExpected = isset($args[2]) && (string) $args[2] === 'yes';
$woocommerceExpected = isset($args[3]) && (string) $args[3] === 'yes';
$companionState = isset($args[4]) ? (string) $args[4] : 'normal';
$loadOrder = isset($args[5]) ? (string) $args[5] : 'n/a';
$manifest = require __DIR__ . '/additional-runtime-contracts.php';
$allAddons = array_keys($manifest['plugins']);
$activePluginEntries = array_values((array) get_option('active_plugins', array()));
$targetAddons = $addon === 'all'
	? array_values(array_filter($allAddons, static fn(string $slug): bool => in_array((string) $manifest['plugins'][$slug]['entry'], $activePluginEntries, true)))
	: array($addon);

$result = array(
	'scenario' => $scenarioId,
	'addon' => $addon,
	'core_expected' => $coreExpected,
	'woocommerce_expected' => $woocommerceExpected,
	'companion_state' => $companionState,
	'load_order' => $loadOrder,
	'active_plugins' => $activePluginEntries,
	'checks' => array(),
	'runtime_errors' => array(),
	'doing_it_wrong' => array(),
	'identity' => array(),
	'menu' => array(),
	'notices' => '',
	'notices_by_owner' => array(),
	'rest_namespaces' => array(),
);

$check = static function (string $id, string $dimension, string $checkAddon, bool $passed, string $message, array $details = array()) use (&$result): void {
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
set_current_screen('dashboard');
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
	if (did_action('rest_api_init') === 0) {
		// rest_get_server() initializes the server and fires rest_api_init.
		// Calling the action around it would register every route twice.
		rest_get_server();
	}
} catch (Throwable $throwable) {
	$lifecycleException = array(
		'class' => get_class($throwable),
		'message' => $throwable->getMessage(),
		'file' => $relativePluginPath($throwable->getFile()),
		'line' => $throwable->getLine(),
	);
}

$check('lifecycle-no-exception', 'No Fatal', $addon, $lifecycleException === null, $lifecycleException === null ? 'Admin and REST registration lifecycles completed without an uncaught exception.' : 'A registration lifecycle raised an exception.', $lifecycleException ?? array());

$menuRows = static function (string $parent, string $slug): array {
	$rows = isset($GLOBALS['submenu'][$parent]) && is_array($GLOBALS['submenu'][$parent]) ? $GLOBALS['submenu'][$parent] : array();
	return array_values(array_filter($rows, static fn($row): bool => is_array($row) && isset($row[2]) && (string) $row[2] === $slug));
};
$topRows = static function (string $slug): array {
	$rows = isset($GLOBALS['menu']) && is_array($GLOBALS['menu']) ? $GLOBALS['menu'] : array();
	return array_values(array_filter($rows, static fn($row): bool => is_array($row) && isset($row[2]) && (string) $row[2] === $slug));
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

$result['menu'] = array(
	'top' => array_values(array_map(static fn($row): string => isset($row[2]) ? (string) $row[2] : '', (array) $GLOBALS['menu'])),
	'bvm' => array_values(array_map(static fn($row): string => isset($row[2]) ? (string) $row[2] : '', (array) ($GLOBALS['submenu']['vms-dashboard'] ?? array()))),
	'woocommerce' => array_values(array_map(static fn($row): string => isset($row[2]) ? (string) $row[2] : '', (array) ($GLOBALS['submenu']['woocommerce'] ?? array()))),
);

ob_start();
try {
	do_action('admin_notices');
} catch (Throwable $throwable) {
	$check('admin-notices-no-exception', 'Notices', $addon, false, 'admin_notices raised an exception.', array('class' => get_class($throwable), 'message' => $throwable->getMessage()));
}
$nativeNotices = (string) ob_get_clean();
$result['notices'] = preg_replace('/\s+/', ' ', wp_strip_all_tags($nativeNotices)) ?: '';
$noticeBlocks = array();
if (preg_match_all('#<div\b[^>]*\bnotice\b[^>]*>.*?</div>#is', $nativeNotices, $noticeMatches) > 0) {
	foreach ($noticeMatches[0] as $noticeHtml) {
		$noticeText = preg_replace('/\s+/', ' ', wp_strip_all_tags((string) $noticeHtml)) ?: '';
		if ($noticeText !== '') {
			$noticeBlocks[] = $noticeText;
		}
	}
}
foreach ((array) $manifest['plugins'] as $noticeOwner => $noticeContract) {
	$owned = array();
	foreach ($noticeBlocks as $noticeText) {
		foreach ((array) ($noticeContract['notice']['owner_patterns'] ?? array()) as $ownerPattern) {
			if ($ownerPattern !== '' && stripos($noticeText, (string) $ownerPattern) !== false) {
				$owned[] = $noticeText;
				break;
			}
		}
	}
	$result['notices_by_owner'][$noticeOwner] = array_values(array_unique($owned));
}

$coreLoaded = defined('BVMGR_PLUGIN_FILE') && defined('BVMGR_VERSION');
$bvmFile = $coreLoaded ? (string) BVMGR_PLUGIN_FILE : '';
$result['identity'] = array(
	'bvm_active' => $coreLoaded,
	'bvm_plugin_basename' => $bvmFile !== '' ? plugin_basename($bvmFile) : '',
	'bvm_version' => defined('BVMGR_VERSION') ? (string) BVMGR_VERSION : '',
	'historical_main_exists' => is_file(WP_PLUGIN_DIR . '/vms/vendor-management-system.php'),
	'nonexistent_bootstraps' => array(
		'vms.php' => is_file(WP_PLUGIN_DIR . '/vms.php'),
		'vms/vms.php' => is_file(WP_PLUGIN_DIR . '/vms/vms.php'),
		'backstage-venue-manager.php' => is_file(WP_PLUGIN_DIR . '/backstage-venue-manager.php'),
	),
	'bridge_bvm_detection' => in_array('drm-events-bridge', $targetAddons, true)
		? array(
			'mode' => 'none',
			'public_basename_required' => false,
			'historical_basename_required' => false,
			'optional_runtime_api_guards' => array('vms_event_plan_statuses', 'vms_meta_key', 'vms_event_plan_status_normalize'),
		)
		: null,
);

$check('core-presence', 'BVM Detection', $addon, $coreLoaded === $coreExpected, 'BVM runtime presence matched the scenario.');
if ($coreExpected) {
	$check('public-basename', 'BVM Detection', $addon, $result['identity']['bvm_plugin_basename'] === 'backstage-venue-manager/backstage-venue-manager.php', 'BVM used its public plugin basename.', $result['identity']);
	$check('public-version', 'BVM Detection', $addon, $result['identity']['bvm_version'] === '1.2.0', 'BVM exposed version 1.2.0.');
	$check('historical-core-absent', 'BVM Detection', $addon, !$result['identity']['historical_main_exists'] && !in_array('vms/vendor-management-system.php', $result['active_plugins'], true), 'Historical standalone VMS core was absent and inactive.');
	$check('nonexistent-bootstrap-identities-absent', 'BVM Detection', $addon, !in_array(true, $result['identity']['nonexistent_bootstraps'], true), 'Nonexistent bootstrap identities were absent.');
}

$calendarFeedsActive = in_array('backstage-calendar-feeds/backstage-calendar-feeds.php', $activePluginEntries, true);
if ($calendarFeedsActive) {
	$calendarRows = $menuRows($coreExpected ? 'vms-dashboard' : 'backstage', 'backstage');
	$calendarTopRows = $topRows('backstage');
	$calendarRegistry = function_exists('bvmgr_admin_menu_registry') ? bvmgr_admin_menu_registry() : array();
	$calendarEntry = (array) ($calendarRegistry['backstage'] ?? array());
	$check('calendar-feeds-version', 'APIs', 'backstage-calendar-feeds', defined('BCF_VERSION') && BCF_VERSION === '0.1.4', 'Calendar Feeds loaded the 0.1.4 candidate.');
	if ($coreExpected) {
		$check('calendar-feeds-bvm-registry', 'Menu/UI', 'backstage-calendar-feeds', count($calendarRows) === 1 && count($calendarTopRows) === 0 && ($calendarEntry['source'] ?? '') === 'backstage-calendar-feeds' && ($calendarEntry['capability'] ?? '') === 'manage_options' && !empty($calendarEntry['shell']), 'Calendar Feeds used one canonical BVM registry page without a duplicate top-level menu.', $calendarEntry);
	} else {
		$check('calendar-feeds-standalone-menu', 'Core-Absent Behavior', 'backstage-calendar-feeds', count($calendarTopRows) === 1 && count($calendarRows) === 1, 'Calendar Feeds remained reachable through its standalone Backstage menu.');
	}

	$calendarHealth = class_exists('ConeyProductions\\BackstageCalendarFeeds\\DRM_Calendar_Intake_Provider')
		? (new ConeyProductions\BackstageCalendarFeeds\DRM_Calendar_Intake_Provider())->health()
		: array('available' => false);
	$intakeExpected = in_array('drm-calendar-intake/drm-calendar-intake.php', $activePluginEntries, true);
	$calendarHealthAvailable = !is_wp_error($calendarHealth) && is_array($calendarHealth) && !empty($calendarHealth['available']);
	$calendarHealthDetails = is_wp_error($calendarHealth)
		? array('error_code' => $calendarHealth->get_error_code(), 'error_message' => $calendarHealth->get_error_message())
		: (array) $calendarHealth;
	$check('calendar-feeds-intake-contract', 'APIs', 'backstage-calendar-feeds', $calendarHealthAvailable === $intakeExpected, 'Calendar Feeds recognized DRM Calendar Intake presence or failed safely when it was unavailable.', $calendarHealthDetails);
}

$routes = rest_get_server()->get_routes();
foreach (array_keys($routes) as $route) {
	if (preg_match('#^/([^/]+/v[0-9]+)(?:/|$)#', (string) $route, $match) === 1) {
		$result['rest_namespaces'][$match[1]] = true;
	}
}
$result['rest_namespaces'] = array_keys($result['rest_namespaces']);
sort($result['rest_namespaces'], SORT_STRING);

if (in_array('drm-events-bridge', $targetAddons, true)) {
	$bridgeFunctions = array(
		'drm_events_bridge_get_router_status',
		'drm_events_bridge_get_upcoming_events',
		'drm_events_bridge_get_events_from_router',
		'drm_events_bridge_local_public_event_provider',
	);
	$missingBridgeFunctions = array_values(array_filter($bridgeFunctions, static fn(string $function): bool => !function_exists($function)));
	$check('bridge-runtime-api', 'APIs', 'drm-events-bridge', $missingBridgeFunctions === array(), 'Bridge runtime APIs loaded.', array('missing' => $missingBridgeFunctions));

	$routerExpected = $companionState !== 'missing-router';
	$routerStatus = function_exists('drm_events_bridge_get_router_status') ? drm_events_bridge_get_router_status() : array();
	$check(
		'bridge-router-state',
		'APIs',
		'drm-events-bridge',
		(bool) ($routerStatus['available'] ?? false) === $routerExpected
			&& (bool) ($routerStatus['compatible'] ?? false) === $routerExpected
			&& (int) ($routerStatus['contract_version'] ?? 0) === ($routerExpected ? 2 : 0),
		'Bridge recognized the exact Router contract or its intentional absence.',
		array('expected' => $routerExpected, 'actual' => $routerStatus)
	);

	$bridgeEvents = function_exists('drm_events_bridge_get_upcoming_events') ? drm_events_bridge_get_upcoming_events('', 12) : null;
	$check('bridge-empty-provider-safe', 'APIs', 'drm-events-bridge', is_array($bridgeEvents) && $bridgeEvents === array(), 'A fresh or missing Router returned a safe empty public feed without legacy BVM fallback.');

	$syntheticCandidate = array(
		'id' => 'drm-0123456789abcdef0123456789abcdef',
		'act_slug' => 'dalene-richelle-solo',
		'public_title' => 'Synthetic compatibility event',
		'start_datetime' => '2099-01-02T19:00:00-06:00',
		'end_datetime' => '2099-01-02T21:00:00-06:00',
		'timezone' => 'America/Chicago',
		'presentation' => 'normal',
		'venue_key' => 'compatibility-venue',
		'venue_name' => 'Compatibility Venue',
		'city' => 'Test City',
		'state' => 'TX',
	);
	$mapped = function_exists('drm_events_bridge_get_events_from_router')
		? drm_events_bridge_get_events_from_router('', 12, 2, static fn(): array => array($syntheticCandidate))
		: array();
	$expectedFields = array('id', 'act_slug', 'act_label', 'presentation', 'public_title', 'venue_name', 'city', 'state', 'start_datetime', 'end_datetime', 'timezone', 'ticket_url', 'event_url');
	$check(
		'bridge-router-mapping-contract',
		'APIs',
		'drm-events-bridge',
		count($mapped) === 1 && array_keys($mapped[0]) === $expectedFields && ($mapped[0]['presentation'] ?? '') === 'normal',
		'Bridge mapped one synthetic Router-approved candidate to the exact 13-field public contract.',
		array('actual_fields' => isset($mapped[0]) && is_array($mapped[0]) ? array_keys($mapped[0]) : array())
	);
	$syntheticCandidate['presentation'] = 'public';
	$aliasResult = function_exists('drm_events_bridge_get_events_from_router')
		? drm_events_bridge_get_events_from_router('', 12, 2, static fn(): array => array($syntheticCandidate))
		: null;
	$throwResult = function_exists('drm_events_bridge_get_events_from_router')
		? drm_events_bridge_get_events_from_router('', 12, 2, static function (): array { throw new RuntimeException('synthetic-provider-failure'); })
		: null;
	$check('bridge-provider-fail-closed', 'APIs', 'drm-events-bridge', $aliasResult === array() && $throwResult === array(), 'Presentation aliases and provider exceptions failed closed.');

	$routeEndpoints = (array) ($routes['/drm-events/v1/upcoming'] ?? array());
	$check('bridge-rest-route-single', 'APIs', 'drm-events-bridge', count($routeEndpoints) === 1, 'Bridge registered its public REST route exactly once.', array('endpoint_count' => count($routeEndpoints)));
	$check('bridge-provider-filter-priority', 'APIs', 'drm-events-bridge', has_filter('dalene_richelle_site_public_event_provider', 'drm_events_bridge_local_public_event_provider') === 5, 'Bridge registered its local provider adapter once at priority 5.');

	$settingsRows = $menuRows('options-general.php', 'drm-events-bridge');
	$allSettingsRows = $allSlugRows('drm-events-bridge');
	$check('bridge-settings-menu-single', 'Menu/UI', 'drm-events-bridge', count($settingsRows) === 1 && count($allSettingsRows) === 1, 'Bridge registered one Settings page without a menu collision.');
	$check('bridge-no-bvm-dependency-notice', 'Notices', 'drm-events-bridge', stripos($nativeNotices, 'Bridge requires') === false && stripos($nativeNotices, 'Activate BVM') === false, 'Bridge emitted no BVM dependency warning.');
	if (!$coreExpected) {
		$check('bridge-bvm-absent-supported', 'BVM-Absent', 'drm-events-bridge', !$coreLoaded && is_array($bridgeEvents), 'Bridge initialized without local BVM under its Router-authoritative architecture.');
	}
}

foreach ($targetAddons as $targetAddon) {
	$contract = $manifest['plugins'][$targetAddon] ?? null;
	if (!is_array($contract)) {
		$check('known-addon-' . $targetAddon, 'No Fatal', $targetAddon, false, 'Scenario referenced an unknown add-on contract.');
		continue;
	}

	$marker = $contract['marker'];
	$markerLoaded = defined((string) $marker['constant']);
	$markerValueMatches = $markerLoaded && ($marker['value'] === null || constant((string) $marker['constant']) === $marker['value']);
	$markerActual = $markerLoaded ? constant((string) $marker['constant']) : null;
	if ($markerLoaded && $marker['value'] === null) {
		$markerActual = 'defined';
	}
	$check('addon-loaded-' . $targetAddon, 'No Fatal', $targetAddon, $markerValueMatches, 'The selected add-on bootstrap and version marker loaded.', array('constant' => $marker['constant'], 'expected' => $marker['value'], 'actual' => $markerActual));

	$companionUnavailable = $targetAddon === 'vms-commerce-discounts' && $companionState === 'missing-woocommerce';
	if ($coreExpected && !$companionUnavailable) {
		$missingCanonicalProviders = array();
		foreach ((array) $contract['capabilities'] as $capability) {
			if (($capability['external_dependency_owner'] ?? '') !== 'backstage-venue-manager') {
				continue;
			}
			$provider = (string) ($capability['canonical_provider'] ?? '');
			if ($provider === '' || strpos((string) ($capability['guard'] ?? ''), 'optional function guard') !== false) {
				continue;
			}
			if (strpos($provider, 'bvmgr_') === 0) {
				$available = function_exists($provider);
			} elseif (preg_match('/^BVMGR_[A-Z0-9_]+$/', $provider) === 1) {
				$available = defined($provider);
			} else {
				// Descriptive WordPress/data providers are checked by their outcome tests.
				continue;
			}
			if (!$available) {
				$missingCanonicalProviders[] = array('capability' => $capability['id'] ?? '', 'provider' => $provider);
			}
		}
		$check('semantic-capabilities-' . $targetAddon, 'APIs', $targetAddon, $missingCanonicalProviders === array(), 'Semantic capabilities resolved through their canonical BVM providers without requiring legacy aliases.', array('missing' => $missingCanonicalProviders, 'checked' => count($contract['capabilities'])));

		$missingHooks = array();
		foreach ((array) $contract['hooks'] as $hookContract) {
			if (($hookContract['emission'] ?? '') !== 'bvm-emitted') {
				continue;
			}
			$hook = (string) ($hookContract['name'] ?? '');
			if (!in_array($hook, (array) ($manifest['hook_emission']['bvm_emitted'] ?? array()), true) || has_filter($hook) === false) {
				$missingHooks[] = (string) $hook;
			}
		}
		$check('runtime-hooks-' . $targetAddon, 'APIs', $targetAddon, $missingHooks === array(), 'BVM-emitted hook integrations had callbacks attached; historical/dead hooks were not counted as support.', array('missing' => $missingHooks));

		$missingDataStructures = array();
		foreach (array_intersect($contract['post_types'], array('vms_event_plan', 'vms_venue', 'vms_doc')) as $postType) {
			if (!post_type_exists($postType)) {
				$missingDataStructures[] = $postType;
			}
		}
		$check('data-contracts-' . $targetAddon, 'APIs', $targetAddon, $missingDataStructures === array(), 'Required live BVM post-type contracts were registered.', array('missing' => $missingDataStructures));

		$menuFailures = array();
		$menuHooks = array();
		$registry = isset($GLOBALS['bvmgr_admin_menu_registry']) && is_array($GLOBALS['bvmgr_admin_menu_registry']) ? $GLOBALS['bvmgr_admin_menu_registry'] : array();
		foreach ($contract['menus'] as $menu) {
			$rows = $menuRows((string) $menu['parent'], (string) $menu['slug']);
			$allRows = $allSlugRows((string) $menu['slug']);
			$menuHook = get_plugin_page_hookname((string) $menu['slug'], (string) $menu['parent']);
			$menuHooks[(string) $menu['slug']] = $menuHook;
			if (count($rows) !== 1 || count($allRows) !== 1) {
				$menuFailures[] = array('slug' => $menu['slug'], 'parent' => $menu['parent'], 'under_parent' => count($rows), 'all' => count($allRows));
				continue;
			}
			if (isset($menu['capability']) && (!isset($rows[0][1]) || (string) $rows[0][1] !== (string) $menu['capability'])) {
				$menuFailures[] = array('slug' => $menu['slug'], 'expected_capability' => $menu['capability'], 'actual_capability' => $rows[0][1] ?? null);
			}
			if ($menuHook === '' || has_action($menuHook) === false) {
				$menuFailures[] = array('slug' => $menu['slug'], 'expected_callback_hook' => $menuHook, 'callback_registered' => false);
			}
			if (!empty($menu['registry'])) {
				$registryEntry = $registry[(string) $menu['slug']] ?? null;
				if (!is_array($registryEntry)) {
					$menuFailures[] = array('slug' => $menu['slug'], 'registry_entry' => 'missing');
					continue;
				}
				if ((string) ($registryEntry['capability'] ?? '') !== (string) ($menu['capability'] ?? '')) {
					$menuFailures[] = array('slug' => $menu['slug'], 'registry_capability' => $registryEntry['capability'] ?? null, 'expected_capability' => $menu['capability'] ?? null);
				}
				$callback = $registryEntry['callback'] ?? null;
				$callbackMethod = is_array($callback) ? (string) ($callback[1] ?? '') : (is_string($callback) ? $callback : '');
				if (isset($menu['callback_method']) && $callbackMethod !== (string) $menu['callback_method']) {
					$menuFailures[] = array('slug' => $menu['slug'], 'registry_callback_method' => $callbackMethod, 'expected_callback_method' => $menu['callback_method']);
				}
			}
		}
		$check('menus-' . $targetAddon, 'Menu/UI', $targetAddon, $menuFailures === array(), 'Integration menus existed once with the intended parent, capability, callback, and canonical registry ownership where required.', array('failures' => $menuFailures, 'actual_page_hooks' => $menuHooks));

		$presentNotice = (string) ($contract['notice']['present'] ?? '');
		$ownedNoticeText = implode(' ', (array) ($result['notices_by_owner'][$targetAddon] ?? array()));
		$absentNotice = (string) ($contract['notice']['absent'] ?? '');
		$noticePassed = $presentNotice !== '' ? stripos($ownedNoticeText, $presentNotice) !== false : ($absentNotice === '' || stripos($ownedNoticeText, $absentNotice) === false);
		$check('owned-notices-' . $targetAddon, 'Notices', $targetAddon, $noticePassed, 'Only notices owned by the package under test were evaluated for its BVM dependency state.', array('owned_notices' => $result['notices_by_owner'][$targetAddon] ?? array()));

		$missingNamespaces = array_values(array_filter($contract['rest_namespaces'], static fn(string $namespace): bool => !in_array($namespace, $result['rest_namespaces'], true)));
		$check('rest-registration-' . $targetAddon, 'APIs', $targetAddon, $missingNamespaces === array(), 'Expected REST namespaces were registered without executing endpoints.', array('missing' => $missingNamespaces));
		$missingAjax = array_values(array_filter($contract['ajax_actions'], static fn(string $action): bool => has_action('wp_ajax_' . $action) === false));
		$check('ajax-registration-' . $targetAddon, 'APIs', $targetAddon, $missingAjax === array(), 'Expected AJAX callbacks were registered without dispatching actions.', array('missing' => $missingAjax));
		$missingCron = array_values(array_filter($contract['cron_hooks'], static fn(string $hook): bool => has_action($hook) === false));
		$check('cron-registration-' . $targetAddon, 'APIs', $targetAddon, $missingCron === array(), 'Expected cron callbacks were registered without running operational jobs.', array('missing' => $missingCron));
	}
}

if (!$coreExpected && $addon !== 'all' && isset($manifest['plugins'][$addon])) {
	$contract = $manifest['plugins'][$addon];
	$absentNotice = (string) ($contract['notice']['absent'] ?? '');
	$ownedNoticeText = implode(' ', (array) ($result['notices_by_owner'][$addon] ?? array()));
	$noticePassed = $absentNotice === '' || stripos($ownedNoticeText, $absentNotice) !== false;
	$check('core-absent-notice-' . $addon, 'BVM-Absent', $addon, $noticePassed, $absentNotice === '' ? 'The intended standalone/no-op state emitted no required BVM notice.' : 'The package-owned missing-BVM notice was emitted.', array('expected_fragment' => $absentNotice, 'owned_notices' => $result['notices_by_owner'][$addon] ?? array()));

	if ($addon === 'drm-calendar-intake') {
		$check('calendar-intake-standalone-menu', 'BVM-Absent', $addon, count($menuRows('edit.php?post_type=drm_calendar_item', 'drm-calendar-intake-settings')) === 1, 'Calendar Intake retained its quarantine UI without BVM.');
	} elseif ($addon === 'vms-commerce-discounts') {
		$expected = $woocommerceExpected ? 1 : 0;
		$check('commerce-standalone-menu', 'BVM-Absent', $addon, count($menuRows('woocommerce', 'vms-commerce-discounts')) === $expected, 'Commerce Discounts followed its WooCommerce dependency independently of BVM.');
	} elseif ($addon === 'vms-investor-portal') {
		$check('investor-standalone-menu', 'BVM-Absent', $addon, count($topRows('vms-investor-portal')) === 1, 'Investor Portal used its standalone top-level fallback.');
	} elseif ($addon === 'vms-meta-ads') {
		$check('meta-ads-disabled-without-core', 'BVM-Absent', $addon, count($allSlugRows('vms-ma-ads-builder')) === 0 && class_exists('VMS_Meta_Ads') && !VMS_Meta_Ads::is_module_enabled(), 'Meta Ads disabled its BVM-dependent UI without core.');
	} elseif ($addon === 'vms-season-passes') {
		$check('season-passes-disabled-without-core', 'BVM-Absent', $addon, count($allSlugRows('vms-season-passes')) === 0 && function_exists('vms_season_passes_should_boot') && !vms_season_passes_should_boot(), 'Season Passes gracefully skipped its dependent runtime without BVM.');
	} elseif ($addon === 'vms-sponsorships') {
		$check('sponsorships-standalone-menu', 'BVM-Absent', $addon, count($topRows('vms-sponsorships')) === 1, 'Sponsorships used its standalone top-level fallback.');
	} elseif ($addon === 'vmsx-checkout-policies') {
		$expected = $woocommerceExpected ? 1 : 0;
		$check('checkout-policies-standalone-menu', 'BVM-Absent', $addon, count($menuRows('woocommerce', 'vmsx-checkout-policies')) === $expected, 'Checkout Policies used its WooCommerce fallback only when WooCommerce was present.');
	} elseif ($addon === 'vmsx-weather-risk') {
		$check('weather-risk-disabled-without-core', 'BVM-Absent', $addon, count($allSlugRows('vms-weather-risk')) === 0 && class_exists('VMSX_Weather_Risk_Compatibility') && !VMSX_Weather_Risk_Compatibility::is_ready(), 'Weather Risk skipped BVM-dependent registration without core.');
	} else {
		$check('standalone-load-' . $addon, 'BVM-Absent', $addon, true, 'The add-on completed its declared no-op or standalone bootstrap without BVM.');
	}
}

if ($scenarioId === 'third-party-absent-vms-commerce-discounts') {
	$check('commerce-missing-woocommerce-notice', 'Notices', 'vms-commerce-discounts', stripos($nativeNotices, 'requires WooCommerce to be active') !== false && count($allSlugRows('vms-commerce-discounts')) === 0, 'Commerce Discounts emitted its WooCommerce-only dependency notice and registered no settings menu.');
}
if ($scenarioId === 'third-party-absent-square-vms-commerce-discounts') {
	$squareCallbacks = array(
		'wc_payment_gateway_square_credit_card_get_order' => has_filter('wc_payment_gateway_square_credit_card_get_order'),
		'wc_payment_gateway_square_cash_app_pay_get_order' => has_filter('wc_payment_gateway_square_cash_app_pay_get_order'),
	);
	$check(
		'commerce-missing-square-integration-unavailable',
		'Notices',
		'vms-commerce-discounts',
		stripos($ownedNoticeText = implode(' ', (array) ($result['notices_by_owner']['vms-commerce-discounts'] ?? array())), 'WooCommerce Square integration is unavailable') !== false
			&& count($allSlugRows('vms-commerce-discounts')) === 1
			&& !class_exists('VMS_Discounts_Square_Bridge', false)
			&& !class_exists('VMS_Discounts_Square_Order_Request', false)
			&& !in_array(true, $squareCallbacks, true)
			&& has_action('wp_ajax_vms_discounts_search_products') !== false,
		'Commerce 0.2.13 kept its non-Square runtime available while the optional Square bridge remained disabled.',
		array('owned_notice' => $ownedNoticeText, 'square_callbacks' => $squareCallbacks)
	);
}
if ($scenarioId === 'third-party-absent-vmsx-checkout-policies') {
	$check('checkout-policies-no-woocommerce-menu', 'Menu/UI', 'vmsx-checkout-policies', count($allSlugRows('vmsx-checkout-policies')) === 0, 'Checkout Policies registered no fallback menu without WooCommerce.');
}
if ($scenarioId === 'third-party-absent-vms-season-passes') {
	$check('season-passes-woocommerce-optional', 'Notices', 'vms-season-passes', count($menuRows('vms-dashboard', 'vms-season-passes')) === 1 && stripos($nativeNotices, 'WooCommerce') === false, 'Season Passes kept its BVM runtime available while optional WooCommerce was absent.');
}
if ($scenarioId === 'third-party-absent-vms-season-passes-ops') {
	$check('season-passes-ops-optional', 'APIs', 'vms-season-passes', function_exists('vms_season_passes_should_boot') && vms_season_passes_should_boot() && !function_exists('vms_ops_hash_scan_payload'), 'Season remained available without optional Ops scanner enrichment.');
}
if ($scenarioId === 'third-party-absent-vms-sponsorships') {
	$check('sponsorships-tec-optional', 'Notices', 'vms-sponsorships', count($menuRows('vms-dashboard', 'vms-sponsorships')) === 1 && stripos($nativeNotices, 'Events Calendar') === false, 'Sponsorships kept its administrative integration available while optional TEC was absent.');
}

if ($coreExpected && in_array('vms-season-passes', $targetAddons, true)) {
	$seasonProviders = array(
		'boot' => function_exists('vms_season_passes_should_boot') && vms_season_passes_should_boot(),
		'module' => function_exists('bvmgr_module_is_registered') && bvmgr_module_is_registered('season_passes'),
		'scanner' => function_exists('vms_season_passes_register_scanner_hooks'),
		'public' => function_exists('vms_season_passes_public_route_slug'),
		'admin' => function_exists('vms_season_passes_render_admin_page'),
		'tec_resolver' => function_exists('vms_season_passes_core_function') && vms_season_passes_core_function('vms_get_event_plan_for_tec_event') === 'bvmgr_get_event_plan_for_tec_event',
	);
	$check('season-runtime-providers', 'APIs', 'vms-season-passes', !in_array(false, $seasonProviders, true), 'Season loaded scanner, public, admin, module, and canonical TEC resolver providers.', $seasonProviders);
}
if ($coreExpected && in_array('vms-sponsorships', $targetAddons, true)) {
	$shortcodes = array('vms_sponsor_event', 'vms_sponsor_slot', 'vms_sponsor_banner', 'vms_sponsor_placeholder', 'vms_sponsor_email', 'vms_sponsor_season', 'vms_sponsor_inquiry', 'vms_sponsor_apply', 'vms_sponsor_asset_upload');
	$missingShortcodes = array_values(array_filter($shortcodes, static fn(string $shortcode): bool => !shortcode_exists($shortcode)));
	$check('sponsorship-current-behavior', 'APIs', 'vms-sponsorships', $missingShortcodes === array(), 'Sponsorship shortcodes remained registered after canonical registry migration.', array('missing' => $missingShortcodes));

	$callbackCount = static function (string $hook, string $class, string $method): int {
		$registered = $GLOBALS['wp_filter'][$hook] ?? null;
		if (!($registered instanceof WP_Hook)) {
			return 0;
		}
		$count = 0;
		foreach ($registered->callbacks as $callbacks) {
			foreach ($callbacks as $definition) {
				$callback = $definition['function'] ?? null;
				if (is_array($callback) && is_object($callback[0] ?? null) && $callback[0] instanceof $class && ($callback[1] ?? '') === $method) {
					++$count;
				}
			}
		}
		return $count;
	};
	$eventPlanHooks = array(
		'add_meta_boxes' => $callbackCount('add_meta_boxes', 'VMS_Sponsorships_Event_Plans', 'add_event_plan_meta_box'),
		'save_post' => $callbackCount('save_post', 'VMS_Sponsorships_Event_Plans', 'save_event_plan_meta'),
		'legacy_dead' => $callbackCount('vms_event_plan_after_modules', 'VMS_Sponsorships_Event_Plans', 'render_vms_event_plan_card'),
	);
	$check(
		'sponsorship-event-plan-hooks-single',
		'APIs',
		'vms-sponsorships',
		!in_array(0, $eventPlanHooks, true) && max($eventPlanHooks) === 1,
		'Sponsorship Event Plan callbacks registered once; the historical hook remains dormant rather than being revived by BVM.',
		$eventPlanHooks
	);

	if ($scenarioId === 'additional-vms-sponsorships-core-first') {
		global $wpdb;
		$repo = VMS_Sponsorships::instance()->repo;
		$packageId = 0;
		$applicationId = 0;
		$assignmentId = 0;
		$eventPlanId = 0;
		$previousPost = $_POST;
		$previousUser = get_current_user_id();
		$flowPassed = false;
		$capabilityPassed = false;
		$publicPassed = false;
		$duplicateFailedClosed = false;
		$invalidApplicationFailedClosed = false;
		$cleanupPassed = false;
		try {
			$eventPlanId = wp_insert_post(array(
				'post_type' => 'vms_event_plan',
				'post_status' => 'publish',
				'post_title' => 'Wave 2B synthetic sponsorship fixture',
			), true);
			if (is_wp_error($eventPlanId)) {
				throw new RuntimeException('Could not create the synthetic Event Plan.');
			}
			$packageId = $repo->upsert_package(array(
				'name' => 'Wave 2B synthetic package',
				'slug' => 'wave-2b-synthetic-package',
				'scope' => 'event',
				'base_price' => '125.00',
				'active' => 1,
				'public_display_enabled' => 1,
				'fulfillment_template' => wp_json_encode(array(array('key' => 'logo', 'label' => 'Synthetic logo placement'))),
			));
			$invalidApplication = $repo->create_application(array('business_name' => 'Invalid synthetic applicant', 'email' => 'not-an-email'));
			$invalidApplicationFailedClosed = is_wp_error($invalidApplication) && $invalidApplication->get_error_code() === 'vms_sponsorships_invalid_application';
			$applicationId = $repo->create_application(array(
				'business_name' => 'Wave 2B Synthetic Sponsor',
				'contact_name' => 'Synthetic Contact',
				'email' => 'sponsorship-flow@example.invalid',
				'event_id' => $eventPlanId,
				'requested_package_id' => $packageId,
				'status' => 'approved',
			));
			$assignmentId = $repo->create_assignment(array(
				'application_id' => $applicationId,
				'event_id' => $eventPlanId,
				'package_id' => $packageId,
				'assignment_scope' => 'event',
				'slot_key' => 'community',
				'status' => 'confirmed',
				'sponsor_display_name' => 'Wave 2B Synthetic Sponsor',
				'sponsor_url' => 'https://example.invalid/sponsor',
				'public_display_enabled' => 1,
				'physical_banner_included' => 0,
			));
			$duplicateAssignment = $repo->create_assignment(array(
				'application_id' => $applicationId,
				'event_id' => $eventPlanId,
				'sponsor_display_name' => 'Duplicate must fail',
			));
			$duplicateFailedClosed = is_wp_error($duplicateAssignment) && $duplicateAssignment->get_error_code() === 'vms_sponsorships_duplicate_application_assignment';
			$repo->increment_metric($assignmentId, 'synthetic_view', $eventPlanId, null, 2);
			$flowPassed = $packageId > 0
				&& $applicationId > 0
				&& $assignmentId > 0
				&& (int) ($repo->get_assignment_by_application_id($applicationId)->id ?? 0) === $assignmentId
				&& count($repo->get_fulfillment_items($assignmentId)) === 1
				&& (int) ($repo->get_metrics_summary($assignmentId)[0]->total ?? 0) === 2;

			wp_set_current_user(0);
			$_POST = array(
				'vms_sponsorships_event_plan_meta_nonce' => wp_create_nonce('vms_sponsorships_event_plan_meta'),
				'vms_sponsorship_value_tier' => 'premium',
				'vms_expected_attendance' => '250',
				'vms_sponsorship_price_multiplier' => '1.25',
			);
			do_action('save_post', $eventPlanId, get_post($eventPlanId));
			$unauthorizedStayedBlank = get_post_meta($eventPlanId, '_vms_sponsorship_value_tier', true) === '';
			$administrators = get_users(array('role' => 'administrator', 'number' => 1, 'fields' => 'ids'));
			wp_set_current_user((int) ($administrators[0] ?? 0));
			$_POST['vms_sponsorships_event_plan_meta_nonce'] = wp_create_nonce('vms_sponsorships_event_plan_meta');
			do_action('save_post', $eventPlanId, get_post($eventPlanId));
			$capabilityPassed = $unauthorizedStayedBlank
				&& get_post_meta($eventPlanId, '_vms_sponsorship_value_tier', true) === 'premium'
				&& (int) get_post_meta($eventPlanId, '_vms_expected_attendance', true) === 250;

			$applicationForm = do_shortcode('[vms_sponsor_apply]');
			$eventOutput = do_shortcode('[vms_sponsor_event event_id="' . $eventPlanId . '" slot="community"]');
			$publicPassed = strpos($applicationForm, '<form') !== false && strpos($eventOutput, 'Wave 2B Synthetic Sponsor') !== false;
		} catch (Throwable $exception) {
			$result['runtime_errors'][] = array('severity' => E_USER_WARNING, 'message' => $exception->getMessage(), 'file' => __FILE__, 'line' => __LINE__);
		} finally {
			$_POST = $previousPost;
			wp_set_current_user($previousUser);
			if ($assignmentId > 0) {
				$wpdb->delete($repo->table('fulfillment'), array('assignment_id' => $assignmentId));
				$wpdb->delete($repo->table('metrics'), array('assignment_id' => $assignmentId));
				$wpdb->delete($repo->table('assets'), array('assignment_id' => $assignmentId));
				$wpdb->delete($repo->table('assignments'), array('id' => $assignmentId));
			}
			if ($applicationId > 0) {
				$wpdb->delete($repo->table('applications'), array('id' => $applicationId));
			}
			if ($packageId > 0) {
				$wpdb->delete($repo->table('packages'), array('id' => $packageId));
			}
			if ($eventPlanId > 0) {
				wp_delete_post($eventPlanId, true);
			}
			$cleanupPassed = ($assignmentId <= 0 || (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . $repo->table('assignments') . ' WHERE id = %d', $assignmentId)) === 0)
				&& ($applicationId <= 0 || (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . $repo->table('applications') . ' WHERE id = %d', $applicationId)) === 0)
				&& ($packageId <= 0 || (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . $repo->table('packages') . ' WHERE id = %d', $packageId)) === 0)
				&& ($eventPlanId <= 0 || get_post($eventPlanId) === null);
		}
		$check('sponsorship-package-application-assignment-flow', 'APIs', 'vms-sponsorships', $flowPassed, 'Synthetic package, application, assignment, fulfillment, and metric flows completed in the disposable database.');
		$check('sponsorship-invalid-and-duplicate-fail-closed', 'APIs', 'vms-sponsorships', $invalidApplicationFailedClosed && $duplicateFailedClosed, 'Invalid applications and duplicate application assignments failed closed.');
		$check('sponsorship-event-plan-capability-gate', 'APIs', 'vms-sponsorships', $capabilityPassed, 'Event Plan metadata rejected an unauthenticated save and accepted a nonce-protected administrator save.');
		$check('sponsorship-public-forms-and-shortcodes', 'APIs', 'vms-sponsorships', $publicPassed, 'Public application and assigned-sponsor shortcodes rendered against synthetic fixture data.');
		$check('sponsorship-flow-cleanup', 'No Fatal', 'vms-sponsorships', $cleanupPassed, 'All synthetic Sponsorships flow rows and the Event Plan were removed before scenario completion.');
	}
}
if ($coreExpected && in_array('vmsx-checkout-policies', $targetAddons, true)) {
	$settingsSection = isset($GLOBALS['wp_settings_sections']['vms-settings']['vmsx_checkout_policies_section']);
	$fallbackRows = $allSlugRows('vmsx-checkout-policies');
	$check('checkout-settings-location', 'Menu/UI', 'vmsx-checkout-policies', $settingsSection && count($fallbackRows) === 0, 'Checkout registered its BVM settings section and suppressed the WooCommerce fallback location.', array('settings_section' => $settingsSection, 'fallback_rows' => count($fallbackRows)));
}
if ($coreExpected && in_array('vmsx-weather-risk', $targetAddons, true)) {
	$weatherProviders = array(
		'ready' => class_exists('VMSX_Weather_Risk_Compatibility') && VMSX_Weather_Risk_Compatibility::is_ready(),
		'module' => function_exists('bvmgr_module_is_registered') && bvmgr_module_is_registered('weather_risk'),
		'venue' => has_action('save_post_vms_venue', array('VMSX_Weather_Risk_Venue_Location', 'handle_venue_save')) !== false,
		'ajax' => has_action('wp_ajax_vmsx_weather_risk_refresh') !== false,
		'cron' => has_action('vmsx_weather_risk_refresh_cron') !== false,
	);
	$check('weather-runtime-providers', 'APIs', 'vmsx-weather-risk', !in_array(false, $weatherProviders, true), 'Weather loaded canonical readiness, module, venue, AJAX, and cron providers.', $weatherProviders);
	if ($companionState === 'missing-data-tools') {
		$check('weather-data-tools-optional', 'APIs', 'vmsx-weather-risk', !function_exists('vms_dt_reporting_ticket_pace_rows') && VMSX_Weather_Risk_Compatibility::is_ready(), 'Weather remained ready without optional Data Tools enrichment.');
	} elseif ($companionState === 'data-tools-present') {
		$check('weather-data-tools-enrichment', 'APIs', 'vmsx-weather-risk', function_exists('vms_dt_reporting_ticket_pace_rows') && VMSX_Weather_Risk_Compatibility::is_ready(), 'Weather remained ready with optional Data Tools enrichment available.');
	}
}

if ($coreExpected && in_array('vms-commerce-discounts', $targetAddons, true) && $woocommerceExpected && $companionState !== 'missing-woocommerce-square') {
	$hook = get_plugin_page_hookname('vms-commerce-discounts', 'woocommerce');
	wp_dequeue_style('vms-discounts-admin');
	wp_dequeue_script('vms-discounts-admin');
	do_action('admin_enqueue_scripts', $hook);
	$check('commerce-returned-hook-assets', 'Menu/UI', 'vms-commerce-discounts', wp_style_is('vms-discounts-admin', 'enqueued') && wp_script_is('vms-discounts-admin', 'enqueued'), 'Commerce Discounts used the actual WordPress-returned menu hook for assets.', array('hook' => $hook));
}

$ownedSlugs = array_merge(array('backstage-venue-manager', 'backstage-calendar-feeds'), $allAddons, array('vms-events-slider', 'vms-fill-dates', 'vms-data-tools', 'vms-express-bar', 'vms-refer-a-friend'));
$ownedPattern = '#^(' . implode('|', array_map(static fn(string $slug): string => preg_quote($slug, '#'), $ownedSlugs)) . ')/#';
$compatibilityRuntimeErrors = array_values(array_filter(
	$result['runtime_errors'],
	static function (array $error) use ($ownedPattern): bool {
		$severity = (int) ($error['severity'] ?? 0);
		return preg_match($ownedPattern, (string) ($error['file'] ?? '')) === 1
			&& in_array($severity, array(E_WARNING, E_NOTICE, E_USER_WARNING, E_USER_NOTICE, E_RECOVERABLE_ERROR), true);
	}
));
$check('owned-runtime-warnings', 'No Fatal', $addon, $compatibilityRuntimeErrors === array(), 'No BVM/first-party integration runtime warning or notice was captured during the exercised lifecycle.', array('errors' => $compatibilityRuntimeErrors));
$check('doing-it-wrong', 'No Fatal', $addon, $result['doing_it_wrong'] === array(), 'No WordPress doing_it_wrong event was captured during the exercised lifecycle.', array('events' => $result['doing_it_wrong']));

restore_error_handler();

$encoded = base64_encode((string) wp_json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
echo "BVM_COMPAT_RESULT_JSON={$encoded}\n";
