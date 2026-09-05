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

$coreLoaded = defined('VMS_PLUGIN_FILE') && defined('VMS_VERSION');
$bvmFile = $coreLoaded ? (string) VMS_PLUGIN_FILE : '';
$result['identity'] = array(
	'bvm_active' => $coreLoaded,
	'bvm_plugin_basename' => $bvmFile !== '' ? plugin_basename($bvmFile) : '',
	'bvm_version' => defined('VMS_VERSION') ? (string) VMS_VERSION : '',
	'vms_plugin_file' => $bvmFile !== '' ? 'wp-content/plugins/' . plugin_basename($bvmFile) : '',
	'historical_main_exists' => is_file(WP_PLUGIN_DIR . '/vms/vendor-management-system.php'),
	'nonexistent_bootstraps' => array(
		'vms.php' => is_file(WP_PLUGIN_DIR . '/vms.php'),
		'vms/vms.php' => is_file(WP_PLUGIN_DIR . '/vms/vms.php'),
		'backstage-venue-manager.php' => is_file(WP_PLUGIN_DIR . '/backstage-venue-manager.php'),
	),
);

$check('core-presence', 'BVM Recognized', $addon, $coreLoaded === $coreExpected, 'BVM runtime presence matched the scenario.');
if ($coreExpected) {
	$check('public-basename', 'BVM Recognized', $addon, $result['identity']['bvm_plugin_basename'] === 'backstage-venue-manager/vendor-management-system.php', 'BVM used its public plugin basename.', $result['identity']);
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

foreach ($targetAddons as $targetAddon) {
	$check('addon-loaded-' . $targetAddon, 'No Fatal', $targetAddon, !empty($loadedMarkers[$targetAddon]), 'The add-on bootstrap completed.');
	if (!$coreExpected) {
		continue;
	}

	$missingFunctions = array_values(
		array_filter(
			(array) ($contracts['functions'][$targetAddon] ?? array()),
			static fn(string $function): bool => !function_exists($function)
		)
	);
	$missingClasses = array_values(
		array_filter(
			(array) ($contracts['classes'][$targetAddon] ?? array()),
			static fn(string $class): bool => !class_exists($class)
		)
	);
	$missingConstants = array_values(
		array_filter(
			(array) ($contracts['constants'][$targetAddon] ?? array()),
			static fn(string $constant): bool => !defined($constant)
		)
	);

	$check('runtime-functions-' . $targetAddon, 'BVM Recognized', $targetAddon, $missingFunctions === array(), 'All consumed BVM function contracts were declared at runtime.', array('missing' => $missingFunctions, 'checked' => count((array) $contracts['functions'][$targetAddon])));
	$check('runtime-classes-' . $targetAddon, 'BVM Recognized', $targetAddon, $missingClasses === array(), 'All consumed BVM class contracts were declared at runtime.', array('missing' => $missingClasses));
	$check('runtime-constants-' . $targetAddon, 'BVM Recognized', $targetAddon, $missingConstants === array(), 'All consumed BVM constant contracts were declared at runtime.', array('missing' => $missingConstants));

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
}

if ($coreExpected && in_array('data-tools', $targetAddons, true)) {
	$bvmRows = $menuRows('vms-dashboard', 'vms-data-tools');
	$toolsRows = $menuRows('tools.php', 'vms-data-tools');
	$hook = $hookFor('vms-data-tools', 'vms-dashboard');
	$check('data-tools-core-recognition', 'BVM Recognized', 'data-tools', function_exists('vms_dt_is_vms_core_active') && vms_dt_is_vms_core_active(), 'Data Tools recognized BVM through its feature contract.');
	$check('data-tools-bvm-menu-single', 'Menu', 'data-tools', count($bvmRows) === 1, 'Data Tools had one usable BVM entry.');
	$check('data-tools-tools-menu-removed', 'Menu', 'data-tools', count($toolsRows) === 0 && count($allSlugRows('vms-data-tools')) === 1, 'The complete lifecycle removed the duplicate Tools entry.');
	$check('data-tools-capability', 'Menu', 'data-tools', isset($bvmRows[0][1]) && $bvmRows[0][1] === 'read', 'The BVM Data Tools bridge kept its intended read capability.');
	$check('data-tools-bridge-callback', 'Menu', 'data-tools', $callbackAttached($hook, 'vms_admin_ui_render_data_tools_page') && is_callable('vms_dt_render_tools_home'), 'The BVM Data Tools bridge and companion callback resolved.');
	$check('data-tools-no-false-notice', 'Notices', 'data-tools', strpos($nativeNotices, 'VMS Core is not detected') === false, 'Data Tools emitted no false missing-core notice.');
}

if ($coreExpected && in_array('express-bar', $targetAddons, true)) {
	$expressRows = $menuRows('vms-dashboard', 'vms-express-bar');
	$barRows = $menuRows('vms-dashboard', 'vms-bar-menu');
	$expressHook = $hookFor('vms-express-bar', 'vms-dashboard');
	$barHook = $hookFor('vms-bar-menu', 'vms-dashboard');
	$check('express-core-recognition', 'BVM Recognized', 'express-bar', function_exists('vmseb_is_vms_active') && vmseb_is_vms_active(), 'Express Bar recognized BVM.');
	$check('express-menu-single', 'Menu', 'express-bar', count($expressRows) === 1 && count($barRows) === 1 && count($allSlugRows('vms-express-bar')) === 1 && count($allSlugRows('vms-bar-menu')) === 1, 'Express Bar attached two single BVM submenus with no rogue top level.');
	$check('express-hooks-current', 'Menu', 'express-bar', $expressHook === 'vms_page_vms-express-bar' && $barHook === 'vms_page_vms-bar-menu', 'WordPress returned the hook suffixes currently reconstructed by Express Bar.', array('express' => $expressHook, 'bar_menu' => $barHook));
	wp_dequeue_style('vmseb-admin');
	wp_dequeue_script('vmseb-admin');
	do_action('admin_enqueue_scripts', $expressHook);
	$expressAssets = wp_style_is('vmseb-admin', 'enqueued') && wp_script_is('vmseb-admin', 'enqueued');
	wp_dequeue_style('vmseb-admin');
	wp_dequeue_script('vmseb-admin');
	do_action('admin_enqueue_scripts', $barHook);
	$barAssets = wp_style_is('vmseb-admin', 'enqueued') && wp_script_is('vmseb-admin', 'enqueued');
	$check('express-assets-current-hooks', 'Menu', 'express-bar', $expressAssets && $barAssets, 'Express Bar assets loaded on both actual WordPress hooks.');
	$check('express-woocommerce-state', 'Notices', 'express-bar', function_exists('vmseb_is_woocommerce_active') && vmseb_is_woocommerce_active() === $woocommerceExpected, 'Express Bar evaluated WooCommerce independently.');
	$check('express-no-false-bvm-notice', 'Notices', 'express-bar', strpos($nativeNotices, 'requires the VMS core plugin') === false, 'Express Bar emitted no false BVM dependency warning.');
	if ($woocommerceExpected) {
		$check('express-no-false-woocommerce-notice', 'Notices', 'express-bar', strpos($nativeNotices, 'requires WooCommerce') === false, 'Express Bar emitted no false WooCommerce dependency warning.');
	}
}

if ($coreExpected && in_array('refer-a-friend', $targetAddons, true)) {
	$rafSlugs = array('vms-raf', 'vms-raf-rewards', 'vms-raf-claims', 'vms-raf-referrals', 'vms-raf-settings', 'vms-raf-help');
	$registry = function_exists('vms_admin_menu_registry') ? vms_admin_menu_registry() : array();
	$registryOwnsRoutes = true;
	$menusSingle = true;
	foreach ($rafSlugs as $slug) {
		$registryOwnsRoutes = $registryOwnsRoutes && isset($registry[$slug]) && ($registry[$slug]['source'] ?? '') === 'vms-refer-a-friend';
		$menusSingle = $menusSingle && count($menuRows('vms-dashboard', $slug)) === 1 && count($allSlugRows($slug)) === 1;
	}
	$check('raf-registry-routes', 'Menu', 'refer-a-friend', $registryOwnsRoutes, 'BVM registry integration owned all intended RAF routes.');
	$check('raf-menu-single', 'Menu', 'refer-a-friend', $menusSingle && count($topRows('vms-raf')) === 0, 'RAF exposed registry-owned BVM submenus without a standalone top-level menu.');
	$check('raf-no-false-notice', 'Notices', 'refer-a-friend', stripos($nativeNotices, 'refer-a-friend') === false, 'RAF introduced no BVM dependency warning.');
}

if ($coreExpected && in_array('events-slider', $targetAddons, true)) {
	$sliderMenuRows = array_filter(
		$allSlugRows('vms-events-slider'),
		static fn(array $row): bool => true
	);
	$check('events-slider-no-menu-dependency', 'Menu', 'events-slider', $sliderMenuRows === array(), 'Events Slider created no BVM admin-menu dependency.');
	$check('events-slider-no-false-notice', 'Notices', 'events-slider', stripos($nativeNotices, 'events slider') === false, 'Events Slider introduced no BVM dependency warning.');
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

if (!$coreExpected && $addon === 'data-tools') {
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
