<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);
define('VMS_PLUGIN_URL', 'https://example.test/wp-content/plugins/backstage-venue-manager/');
define('VMS_VERSION', 'test-version');

$GLOBALS['vms_test_manage_options'] = false;
$GLOBALS['vms_test_pending_count'] = 0;
$GLOBALS['vms_test_styles'] = array();
$GLOBALS['vms_test_scripts'] = array();
$GLOBALS['vms_test_localized_scripts'] = array();
$GLOBALS['vms_test_inline_styles'] = array();
$GLOBALS['vms_test_inline_scripts'] = array();

function __(string $text, string $domain = ''): string { unset($domain); return $text; }
function esc_html__(string $text, string $domain = ''): string { unset($domain); return $text; }
function sanitize_key($value): string { return preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $value)) ?: ''; }
function current_user_can(string $cap): bool { unset($cap); return !empty($GLOBALS['vms_test_manage_options']); }
function add_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): void { unset($hook, $callback, $priority, $accepted_args); }
function add_filter(string $hook, $callback, int $priority = 10, int $accepted_args = 1): void { unset($hook, $callback, $priority, $accepted_args); }
function add_submenu_page(...$args): void { unset($args); }
function wp_enqueue_style(string $handle, string $src = '', array $deps = array(), $ver = false, string $media = 'all'): void { $GLOBALS['vms_test_styles'][$handle] = array('src' => $src, 'deps' => $deps, 'ver' => $ver, 'media' => $media); }
function wp_enqueue_script(string $handle, string $src = '', array $deps = array(), $ver = false, bool $in_footer = false): void { $GLOBALS['vms_test_scripts'][$handle] = array('src' => $src, 'deps' => $deps, 'ver' => $ver, 'in_footer' => $in_footer); }
function wp_localize_script(string $handle, string $name, array $data): bool { $GLOBALS['vms_test_localized_scripts'][$handle] = array('name' => $name, 'data' => $data); return true; }
function wp_add_inline_style(string $handle, string $data): bool { $GLOBALS['vms_test_inline_styles'][$handle] = $data; return true; }
function wp_add_inline_script(string $handle, string $script, string $position = 'after'): bool { $GLOBALS['vms_test_inline_scripts'][$handle] = array('script' => $script, 'position' => $position); return true; }
function vms_add_dispatch_pending_portal_interest_count(): int { return (int) $GLOBALS['vms_test_pending_count']; }
function vms_asset_version(): string { return 'test-asset-version'; }

require_once dirname(__DIR__) . '/includes/modules/availability-date-dispatch/admin-ui.php';

$pluginRoot = dirname(__DIR__);
$livePluginRoot = dirname(dirname($pluginRoot)) . '/vms';

$addPath = $pluginRoot . '/includes/modules/availability-date-dispatch/admin-ui.php';
$publicPath = $pluginRoot . '/includes/modules/availability-date-dispatch/public.php';
$helpersPath = $pluginRoot . '/includes/modules/availability-date-dispatch/helpers.php';
$requestBuilderAssetPath = $pluginRoot . '/assets/js/vms-add-dispatch-admin.js';
$menuCssPath = $pluginRoot . '/assets/css/vms-admin-menu.css';
$menuJsPath = $pluginRoot . '/assets/js/vms-admin-menu.js';
$liveAddPath = $livePluginRoot . '/includes/modules/availability-date-dispatch/admin-ui.php';
$liveMenuCssPath = $livePluginRoot . '/assets/css/vms-admin-menu.css';
$liveMenuJsPath = $livePluginRoot . '/assets/js/vms-admin-menu.js';

$assert = static function (bool $condition, string $message): void {
	if ($condition) {
		return;
	}

	throw new RuntimeException($message);
};

$readFile = static function (string $path) use ($assert): string {
	$contents = @file_get_contents($path);
	$assert(is_string($contents) && $contents !== '', 'Expected readable source file: ' . $path);
	return $contents;
};

$currentRepoStatusPaths = static function () use ($assert): array {
	$output = array();
	$exitCode = 0;
	exec('git status --short --untracked-files=all -- . 2>&1', $output, $exitCode);
	$assert($exitCode === 0, 'git status --short should succeed for the focused diff check.');

	$paths = array();
	foreach ($output as $line) {
		$line = rtrim($line);
		if ($line === '') {
			continue;
		}

		$path = trim(substr($line, 3));
		if (strpos($path, ' -> ') !== false) {
			$parts = explode(' -> ', $path);
			$path = (string) end($parts);
		}
		$paths[] = $path;
	}

	sort($paths);
	return $paths;
};

$extractFunctionSource = static function (string $source, string $functionName) use ($assert): string {
	$tokens = token_get_all($source);
	$capturing = false;
	$seenName = false;
	$braceDepth = 0;
	$buffer = '';

	foreach ($tokens as $token) {
		$text = is_array($token) ? $token[1] : $token;

		if (!$capturing) {
			if (is_array($token) && $token[0] === T_FUNCTION) {
				$capturing = true;
				$seenName = false;
				$braceDepth = 0;
				$buffer = $text;
			}
			continue;
		}

		$buffer .= $text;
		if (!$seenName) {
			if ($text === '(') {
				$capturing = false;
				$buffer = '';
				continue;
			}
			if (is_array($token) && $token[0] === T_STRING) {
				if ($token[1] !== $functionName) {
					$capturing = false;
					$buffer = '';
					continue;
				}
				$seenName = true;
			}
			continue;
		}

		if ($text === '{') {
			$braceDepth++;
			continue;
		}

		if ($text === '}') {
			$braceDepth--;
			if ($braceDepth === 0) {
				return $buffer;
			}
		}
	}

	$assert(false, 'Unable to extract function source for ' . $functionName . '.');
	return '';
};

$resetRuntime = static function (): void {
	$GLOBALS['vms_test_styles'] = array();
	$GLOBALS['vms_test_scripts'] = array();
	$GLOBALS['vms_test_localized_scripts'] = array();
	$GLOBALS['vms_test_inline_styles'] = array();
	$GLOBALS['vms_test_inline_scripts'] = array();
};

try {
	$addSource = $readFile($addPath);
	$publicSource = $readFile($publicPath);
	$helpersSource = $readFile($helpersPath);
	$requestBuilderAssetSource = $readFile($requestBuilderAssetPath);
	$menuCssSource = $readFile($menuCssPath);
	$menuJsSource = $readFile($menuJsPath);
	$liveAddSource = $readFile($liveAddPath);
	$liveMenuCssSource = $readFile($liveMenuCssPath);
	$liveMenuJsSource = $readFile($liveMenuJsPath);

	$menuBadgeCssSource = $extractFunctionSource($addSource, 'vms_add_dispatch_render_menu_badge_css');
	$menuBadgeJsSource = $extractFunctionSource($addSource, 'vms_add_dispatch_render_menu_badge_js');
	$requestBuilderSource = $extractFunctionSource($addSource, 'vms_add_dispatch_render_request_builder');
	$enqueueSource = $extractFunctionSource($addSource, 'vms_add_dispatch_enqueue_admin_assets');

	$assert(strpos($menuBadgeCssSource, '<style>') === false, 'ADD menu-badge CSS function should no longer print a <style> block.');
	$assert(strpos($menuBadgeCssSource, '#adminmenu .vms-add-dispatch-alert-badge') === false, 'ADD menu-badge CSS function should no longer own the static badge rules inline.');
	$assert(strpos($menuBadgeCssSource, "wp_enqueue_style('vms-admin-menu');") !== false, 'ADD menu-badge CSS function should enqueue the shared admin-menu stylesheet under the existing gate.');
	$assert(strpos($addSource, "add_action('admin_head', 'vms_add_dispatch_render_menu_badge_css'") === false, 'ADD menu-badge CSS should no longer hook admin_head.');
	$assert(strpos($addSource, "add_action('admin_enqueue_scripts', 'vms_add_dispatch_render_menu_badge_css', 21, 0);") !== false, 'ADD menu-badge CSS should now hook admin_enqueue_scripts at priority 21.');

	$assert(strpos($menuBadgeJsSource, '<script>') === false, 'ADD menu-badge JS function should no longer print an executable <script> block.');
	$assert(strpos($menuBadgeJsSource, "VMS_PLUGIN_URL . 'assets/js/vms-admin-menu.js'") !== false, 'ADD menu-badge JS function should point to the external admin-menu asset.');
	$assert(strpos($menuBadgeJsSource, "wp_enqueue_script(\n\t\t\t'vms-admin-menu'") !== false || strpos($menuBadgeJsSource, "wp_enqueue_script(\r\n\t\t\t'vms-admin-menu'") !== false || strpos($menuBadgeJsSource, "wp_enqueue_script(\n            'vms-admin-menu'") !== false, 'ADD menu-badge JS function should enqueue the external admin-menu script.');
	$assert(strpos($menuBadgeJsSource, "wp_localize_script(\n\t\t\t'vms-admin-menu'") !== false || strpos($menuBadgeJsSource, "wp_localize_script(\r\n\t\t\t'vms-admin-menu'") !== false || strpos($menuBadgeJsSource, "wp_localize_script(\n            'vms-admin-menu'") !== false, 'ADD menu-badge JS function should hand off inert localized config.');
	$assert(strpos($addSource, "add_action('admin_footer', 'vms_add_dispatch_render_menu_badge_js'") === false, 'ADD menu-badge JS should no longer hook admin_footer.');
	$assert(strpos($addSource, "add_action('admin_enqueue_scripts', 'vms_add_dispatch_render_menu_badge_js', 50, 0);") !== false, 'ADD menu-badge JS should now hook admin_enqueue_scripts at priority 50.');

	$assert(strpos($addSource, 'wp_add_inline_style(') === false, 'ADD admin UI should not move the static badge CSS into wp_add_inline_style().');
	$assert(strpos($addSource, 'wp_add_inline_script(') === false, 'ADD admin UI should not move the menu-badge runtime into wp_add_inline_script().');
	$assert(strpos($menuBadgeCssSource, "current_user_can('manage_options')") !== false, 'ADD menu-badge CSS gate should preserve the manage_options capability boundary.');
	$assert(strpos($menuBadgeJsSource, "current_user_can('manage_options')") !== false, 'ADD menu-badge JS gate should preserve the manage_options capability boundary.');
	$assert(substr_count($addSource, 'vms_add_dispatch_current_pending_count()') >= 5, 'ADD admin UI should preserve the existing pending-count source helper.');
	$assert(strpos($menuBadgeCssSource, '$count = vms_add_dispatch_current_pending_count();') !== false, 'ADD menu-badge CSS gate should still read the pending count from vms_add_dispatch_current_pending_count().');
	$assert(strpos($menuBadgeJsSource, '$count = vms_add_dispatch_current_pending_count();') !== false, 'ADD menu-badge JS gate should still read the pending count from vms_add_dispatch_current_pending_count().');
	$assert(strpos($menuBadgeCssSource, 'if ($count <= 0) {') !== false, 'ADD menu-badge CSS gate should still bail when the pending count is zero.');
	$assert(strpos($menuBadgeJsSource, 'if ($count <= 0) {') !== false, 'ADD menu-badge JS gate should still bail when the pending count is zero.');

	$assert(
		preg_match(
			'~#adminmenu\s+\.vms-add-dispatch-alert-badge\s*\{\s*margin-left:\s*6px;\s*min-width:\s*18px;\s*height:\s*18px;\s*line-height:\s*18px;\s*border-radius:\s*999px;\s*background:\s*#d63638;\s*box-shadow:\s*none;\s*\}~',
			$menuCssSource
		) === 1,
		'The shared admin-menu stylesheet should own the exact ADD badge shell rules.'
	);
	$assert(
		preg_match(
			'~#adminmenu\s+\.vms-add-dispatch-alert-badge\s+\.pending-count\s*\{\s*display:\s*block;\s*min-width:\s*18px;\s*height:\s*18px;\s*line-height:\s*18px;\s*padding:\s*0\s+4px;\s*color:\s*#fff;\s*font-size:\s*11px;\s*font-weight:\s*700;\s*text-align:\s*center;\s*\}~',
			$menuCssSource
		) === 1,
		'The shared admin-menu stylesheet should own the exact ADD badge count rules.'
	);

	$assert(file_exists($menuJsPath), 'ADD menu-badge JS asset should exist.');
	$assert(strpos($menuJsSource, 'window.vmsAdminMenu && window.vmsAdminMenu.addDispatchBadge') !== false, 'ADD menu-badge asset should read the inert localized config object only.');
	$assert(strpos($menuJsSource, "parseInt(root.pendingCount || '0', 10)") !== false, 'ADD menu-badge asset should preserve the pending-count display through inert config.');
	$assert(strpos($menuJsSource, "' <span class=\"awaiting-mod vms-add-dispatch-alert-badge\"><span class=\"pending-count\">'") !== false, 'ADD menu-badge asset should preserve the exact badge markup and classes.');
	$assert(strpos($menuJsSource, "insertAdjacentHTML('beforeend', markup);") !== false, 'ADD menu-badge asset should preserve the existing badge insertion point.');
	$assert(strpos($menuJsSource, "el.innerHTML.indexOf('vms-add-dispatch-alert-badge') !== -1") !== false, 'ADD menu-badge asset should preserve duplicate-badge prevention.');
	$assert(strpos($menuJsSource, "applyBadge('#toplevel_page_vms-dashboard > a .wp-menu-name', markup);") !== false, 'ADD menu-badge asset should preserve the existing top-level menu selector.');
	$assert(strpos($menuJsSource, "applyBadge('#toplevel_page_vms-dashboard .wp-submenu li a[href*=\"page=vms-add-dispatch\"]', markup);") !== false, 'ADD menu-badge asset should preserve the existing submenu selector.');
	$assert(strpos($menuJsSource, "applyBadge('#toplevel_page_vms-dashboard .wp-submenu li.current a[href*=\"page=vms-add-dispatch\"]', markup);") !== false, 'ADD menu-badge asset should preserve the existing current-submenu selector.');
	$assert(strpos($menuJsSource, "if (!config) {") !== false, 'ADD menu-badge asset should safely no-op when config is absent.');
	$assert(strpos($menuJsSource, "if (!nodes.length) {") !== false, 'ADD menu-badge asset should safely no-op when selectors are absent.');
	$assert(strpos($menuJsSource, "document.readyState === 'loading'") !== false, 'ADD menu-badge asset should preserve the DOM-ready timing branch.');
	$assert(strpos($menuJsSource, "document.addEventListener('DOMContentLoaded', initAddDispatchBadge);") !== false, 'ADD menu-badge asset should preserve the deferred DOM-ready bootstrap.');

	$assert(
		preg_match(
			'~add_submenu_page\(\s*\'vms-dashboard\',\s*__\(\'ADD — Availability & Date Dispatch\', \'backstage-venue-manager\'\),\s*__\(\'ADD Dispatch\', \'backstage-venue-manager\'\),\s*\'manage_options\',\s*vms_add_dispatch_page_slug\(\),\s*\'vms_add_dispatch_render_admin_page\'\s*\);~s',
			$addSource
		) === 1,
		'ADD page registration should preserve the existing parent, labels, capability, slug helper, and callback.'
	);
	$assert(strpos($requestBuilderSource, '<script') === false, 'ADD request builder should remain externalized and should not regain an inline <script>.');
	$assert(strpos($enqueueSource, "VMS_PLUGIN_URL . 'assets/js/vms-add-dispatch-admin.js'") !== false, 'ADD request-builder asset reference should remain intact.');
	$assert(strpos($requestBuilderAssetSource, "root.dataset.vmsAddDispatchBound = '1';") !== false, 'ADD request-builder asset should remain unchanged.');
	$assert(strpos($publicSource, 'function vms_add_dispatch_render_public_shell(string $headline, string $content_html): void') !== false, 'ADD public shell should remain present and untouched in this slice.');
	$assert(strpos($helpersSource, 'function vms_add_dispatch_get_event_plan_need_scan(int $limit = 12, int $excluded_limit = 8, array $options = array()): array') !== false, 'ADD helper/query/open-needs logic should remain untouched in this slice.');

	$resetRuntime();
	$GLOBALS['vms_test_manage_options'] = false;
	$GLOBALS['vms_test_pending_count'] = 4;
	vms_add_dispatch_render_menu_badge_css();
	vms_add_dispatch_render_menu_badge_js();
	$assert($GLOBALS['vms_test_styles'] === array(), 'Unauthorized users should not enqueue the ADD menu-badge stylesheet.');
	$assert($GLOBALS['vms_test_scripts'] === array(), 'Unauthorized users should not enqueue the ADD menu-badge script.');
	$assert($GLOBALS['vms_test_localized_scripts'] === array(), 'Unauthorized users should not receive ADD menu-badge config.');

	$resetRuntime();
	$GLOBALS['vms_test_manage_options'] = true;
	$GLOBALS['vms_test_pending_count'] = 0;
	vms_add_dispatch_render_menu_badge_css();
	vms_add_dispatch_render_menu_badge_js();
	$assert($GLOBALS['vms_test_styles'] === array(), 'Zero-count runs should not enqueue the ADD menu-badge stylesheet.');
	$assert($GLOBALS['vms_test_scripts'] === array(), 'Zero-count runs should not enqueue the ADD menu-badge script.');
	$assert($GLOBALS['vms_test_localized_scripts'] === array(), 'Zero-count runs should not receive ADD menu-badge config.');

	$resetRuntime();
	$GLOBALS['vms_test_manage_options'] = true;
	$GLOBALS['vms_test_pending_count'] = 7;
	vms_add_dispatch_render_menu_badge_css();
	vms_add_dispatch_render_menu_badge_js();
	$assert(isset($GLOBALS['vms_test_styles']['vms-admin-menu']), 'Positive-count authorized runs should enqueue the shared admin-menu stylesheet.');
	$assert(isset($GLOBALS['vms_test_scripts']['vms-admin-menu']), 'Positive-count authorized runs should enqueue the ADD menu-badge script.');
	$assert(($GLOBALS['vms_test_scripts']['vms-admin-menu']['src'] ?? '') === VMS_PLUGIN_URL . 'assets/js/vms-admin-menu.js', 'ADD menu-badge script should use the expected asset path.');
	$assert(($GLOBALS['vms_test_scripts']['vms-admin-menu']['ver'] ?? '') === 'test-asset-version', 'ADD menu-badge script should use the asset-version helper fallback pattern.');
	$assert(($GLOBALS['vms_test_scripts']['vms-admin-menu']['in_footer'] ?? false) === true, 'ADD menu-badge script should remain footer-loaded.');
	$assert(($GLOBALS['vms_test_localized_scripts']['vms-admin-menu']['name'] ?? '') === 'vmsAdminMenu', 'ADD menu-badge config should use the inert localized object name.');
	$assert(($GLOBALS['vms_test_localized_scripts']['vms-admin-menu']['data']['addDispatchBadge']['pendingCount'] ?? 0) === 7, 'ADD menu-badge config should pass only the positive pending count.');
	$assert($GLOBALS['vms_test_inline_styles'] === array(), 'ADD menu-badge remediation should not rely on wp_add_inline_style().');
	$assert($GLOBALS['vms_test_inline_scripts'] === array(), 'ADD menu-badge remediation should not rely on wp_add_inline_script().');

	$assert($addSource === $liveAddSource, 'Mirror and live ADD admin PHP should remain byte-for-byte synchronized.');
	$assert($menuCssSource === $liveMenuCssSource, 'Mirror and live admin-menu CSS should remain byte-for-byte synchronized.');
	$assert($menuJsSource === $liveMenuJsSource, 'Mirror and live admin-menu JS should remain byte-for-byte synchronized.');

	$expectedChangedPaths = array(
		'assets/css/vms-admin-menu.css',
		'assets/js/vms-admin-menu.js',
		'docs/WPORG_PREREVIEW_REMEDIATION.md',
		'docs/wporg-remediation-ledger.md',
		'includes/modules/availability-date-dispatch/admin-ui.php',
		'tests/add-dispatch-menu-badge-inline-assets-remediation.php',
	);
	sort($expectedChangedPaths);
	$currentPaths = $currentRepoStatusPaths();
	$assert($currentPaths === $expectedChangedPaths, 'Only the six authorized mirror-repository files should be changed in the remediation diff.');
	$assert(!in_array('assets/js/vms-add-dispatch-admin.js', $currentPaths, true), 'ADD request-builder asset should remain untouched in this slice.');
	$assert(!in_array('includes/modules/availability-date-dispatch/public.php', $currentPaths, true), 'ADD public shell should remain untouched in this slice.');
	$assert(!in_array('includes/modules/availability-date-dispatch/helpers.php', $currentPaths, true), 'ADD helper/query/open-needs logic should remain untouched in this slice.');

	fwrite(STDOUT, "ADD menu-badge inline assets remediation OK.\n");
} catch (Throwable $e) {
	fwrite(STDERR, 'ADD menu-badge inline assets remediation: FAIL - ' . $e->getMessage() . "\n");
	exit(1);
}
