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
		do_action('rest_api_init', rest_get_server());
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

$coreLoaded = defined('VMS_PLUGIN_FILE') && defined('VMS_VERSION');
$bvmFile = $coreLoaded ? (string) VMS_PLUGIN_FILE : '';
$result['identity'] = array(
	'bvm_active' => $coreLoaded,
	'bvm_plugin_basename' => $bvmFile !== '' ? plugin_basename($bvmFile) : '',
	'bvm_version' => defined('VMS_VERSION') ? (string) VMS_VERSION : '',
	'historical_main_exists' => is_file(WP_PLUGIN_DIR . '/vms/vendor-management-system.php'),
	'nonexistent_bootstraps' => array(
		'vms.php' => is_file(WP_PLUGIN_DIR . '/vms.php'),
		'vms/vms.php' => is_file(WP_PLUGIN_DIR . '/vms/vms.php'),
		'backstage-venue-manager.php' => is_file(WP_PLUGIN_DIR . '/backstage-venue-manager.php'),
	),
);

$check('core-presence', 'BVM Detection', $addon, $coreLoaded === $coreExpected, 'BVM runtime presence matched the scenario.');
if ($coreExpected) {
	$check('public-basename', 'BVM Detection', $addon, $result['identity']['bvm_plugin_basename'] === 'backstage-venue-manager/vendor-management-system.php', 'BVM used its public plugin basename.', $result['identity']);
	$check('public-version', 'BVM Detection', $addon, $result['identity']['bvm_version'] === '1.2.0', 'BVM exposed version 1.2.0.');
	$check('historical-core-absent', 'BVM Detection', $addon, !$result['identity']['historical_main_exists'] && !in_array('vms/vendor-management-system.php', $result['active_plugins'], true), 'Historical standalone VMS core was absent and inactive.');
	$check('nonexistent-bootstrap-identities-absent', 'BVM Detection', $addon, !in_array(true, $result['identity']['nonexistent_bootstraps'], true), 'Nonexistent bootstrap identities were absent.');
}

$routes = rest_get_server()->get_routes();
foreach (array_keys($routes) as $route) {
	if (preg_match('#^/([^/]+/v[0-9]+)(?:/|$)#', (string) $route, $match) === 1) {
		$result['rest_namespaces'][$match[1]] = true;
	}
}
$result['rest_namespaces'] = array_keys($result['rest_namespaces']);
sort($result['rest_namespaces'], SORT_STRING);

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

	$companionUnavailable = $targetAddon === 'vms-commerce-discounts' && in_array($companionState, array('missing-woocommerce', 'missing-woocommerce-square'), true);
	if ($coreExpected && !$companionUnavailable) {
		$missingFunctions = array_values(array_filter($contract['functions'], static fn(string $function): bool => !function_exists($function)));
		$missingClasses = array_values(array_filter($contract['classes'], static fn(string $class): bool => !class_exists($class)));
		$missingConstants = array_values(array_filter($contract['constants'], static fn(string $constant): bool => !defined($constant)));
		$check('runtime-functions-' . $targetAddon, 'APIs', $targetAddon, $missingFunctions === array(), 'Consumed BVM function contracts were declared at runtime.', array('missing' => $missingFunctions, 'checked' => count($contract['functions'])));
		$check('runtime-classes-' . $targetAddon, 'APIs', $targetAddon, $missingClasses === array(), 'Consumed BVM class contracts were declared at runtime.', array('missing' => $missingClasses));
		$check('runtime-constants-' . $targetAddon, 'APIs', $targetAddon, $missingConstants === array(), 'Consumed BVM constant contracts were declared at runtime.', array('missing' => $missingConstants));

		$missingHooks = array();
		foreach ($contract['hook_callbacks'] as $hook => $_callback) {
			if (has_filter((string) $hook) === false) {
				$missingHooks[] = (string) $hook;
			}
		}
		$check('runtime-hooks-' . $targetAddon, 'APIs', $targetAddon, $missingHooks === array(), 'Declared BVM hook integrations had callbacks attached.', array('missing' => $missingHooks));

		$missingDataStructures = array();
		foreach (array_intersect($contract['post_types'], array('vms_event_plan', 'vms_venue', 'vms_doc')) as $postType) {
			if (!post_type_exists($postType)) {
				$missingDataStructures[] = $postType;
			}
		}
		$check('data-contracts-' . $targetAddon, 'APIs', $targetAddon, $missingDataStructures === array(), 'Required live BVM post-type contracts were registered.', array('missing' => $missingDataStructures));

		$menuFailures = array();
		$menuHooks = array();
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
		}
		$check('menus-' . $targetAddon, 'Menu/UI', $targetAddon, $menuFailures === array(), 'Expected integration menus existed once under their intended parent with their intended capability and a registered callback.', array('failures' => $menuFailures, 'actual_page_hooks' => $menuHooks));

		$presentNotice = (string) ($contract['notice']['present'] ?? '');
		$check('no-false-bvm-notice-' . $targetAddon, 'Notices', $targetAddon, $presentNotice === '' || stripos($nativeNotices, $presentNotice) !== false, $presentNotice === '' ? 'No BVM-missing notice was expected while BVM was present.' : 'The expected BVM-present notice was emitted.');
		if ($presentNotice === '') {
			$falseMissing = preg_match('/(?:requires|activate|install)[^<]{0,80}(?:Backstage Venue Manager|VMS Core|VMS core|Venue Management System)/i', $nativeNotices) === 1;
			$check('no-generic-false-bvm-notice-' . $targetAddon, 'Notices', $targetAddon, !$falseMissing, 'No false BVM/core dependency warning was emitted while BVM was present.', array('notice' => $result['notices']));
		}

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
	$noticePassed = $absentNotice === '' || stripos($nativeNotices, $absentNotice) !== false;
	$check('core-absent-notice-' . $addon, 'BVM-Absent', $addon, $noticePassed, $absentNotice === '' ? 'The intended standalone/no-op state emitted no required BVM notice.' : 'The intended native missing-BVM notice was emitted.', array('expected_fragment' => $absentNotice, 'notice' => $result['notices']));

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
	if (getenv('BVM_COMPAT_COMMERCE_SQUARE_CONTRACT') === 'phase5a') {
		$squareCallbacks = array(
			'wc_payment_gateway_square_credit_card_get_order' => has_filter('wc_payment_gateway_square_credit_card_get_order'),
			'wc_payment_gateway_square_cash_app_pay_get_order' => has_filter('wc_payment_gateway_square_cash_app_pay_get_order'),
		);
		$check(
			'commerce-missing-square-integration-unavailable',
			'Notices',
			'vms-commerce-discounts',
			stripos($nativeNotices, 'WooCommerce Square integration is unavailable') !== false
				&& count($allSlugRows('vms-commerce-discounts')) === 1
				&& !class_exists('VMS_Discounts_Square_Bridge', false)
				&& !class_exists('VMS_Discounts_Square_Order_Request', false)
				&& !in_array(true, $squareCallbacks, true)
				&& has_action('wp_ajax_vms_discounts_search_products') !== false,
			'Commerce Discounts kept its non-Square runtime available while declaring the Square-specific integration unavailable.',
			array('notice' => $result['notices'], 'square_callbacks' => $squareCallbacks)
		);
	} else {
		$check('commerce-missing-square-fails-closed', 'Notices', 'vms-commerce-discounts', stripos($nativeNotices, 'failed to initialize') !== false && count($allSlugRows('vms-commerce-discounts')) === 0, 'Commerce Discounts failed closed at runtime when WooCommerce Square was missing.', array('notice' => $result['notices']));
	}
}
if ($scenarioId === 'third-party-absent-vmsx-checkout-policies') {
	$check('checkout-policies-no-woocommerce-menu', 'Menu/UI', 'vmsx-checkout-policies', count($allSlugRows('vmsx-checkout-policies')) === 0, 'Checkout Policies registered no fallback menu without WooCommerce.');
}
if ($scenarioId === 'third-party-absent-vms-season-passes') {
	$check('season-passes-woocommerce-optional', 'Notices', 'vms-season-passes', count($menuRows('vms-dashboard', 'vms-season-passes')) === 1 && stripos($nativeNotices, 'WooCommerce') === false, 'Season Passes kept its BVM runtime available while optional WooCommerce was absent.');
}
if ($scenarioId === 'third-party-absent-vms-sponsorships') {
	$check('sponsorships-tec-optional', 'Notices', 'vms-sponsorships', count($menuRows('vms-dashboard', 'vms-sponsorships')) === 1 && stripos($nativeNotices, 'Events Calendar') === false, 'Sponsorships kept its administrative integration available while optional TEC was absent.');
}

if ($coreExpected && in_array('vms-commerce-discounts', $targetAddons, true) && $woocommerceExpected && $companionState !== 'missing-woocommerce-square') {
	$hook = get_plugin_page_hookname('vms-commerce-discounts', 'woocommerce');
	wp_dequeue_style('vms-discounts-admin');
	wp_dequeue_script('vms-discounts-admin');
	do_action('admin_enqueue_scripts', $hook);
	$check('commerce-returned-hook-assets', 'Menu/UI', 'vms-commerce-discounts', wp_style_is('vms-discounts-admin', 'enqueued') && wp_script_is('vms-discounts-admin', 'enqueued'), 'Commerce Discounts used the actual WordPress-returned menu hook for assets.', array('hook' => $hook));
}

$ownedSlugs = array_merge(array('backstage-venue-manager'), $allAddons, array('vms-events-slider', 'vms-fill-dates', 'vms-data-tools', 'vms-express-bar', 'vms-refer-a-friend'));
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
