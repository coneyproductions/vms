<?php
/**
 * Backstage Calendar Feeds 0.1.4 navigation and source-boundary regression.
 *
 * Run with: php tests/calendar-feeds-bvm-navigation.php
 */

namespace {
	if (!defined('ABSPATH')) {
		define('ABSPATH', __DIR__ . '/');
	}

	$GLOBALS['bcf_nav_hooks'] = array();
	$GLOBALS['bcf_nav_menus'] = array();
	$GLOBALS['bcf_nav_submenus'] = array();
	$GLOBALS['bcf_nav_registry'] = array();
	$GLOBALS['bcf_nav_registry_calls'] = 0;
	$GLOBALS['bcf_nav_legacy_calls'] = 0;

	function add_action($hook, $callback, $priority = 10): void
	{
		$GLOBALS['bcf_nav_hooks'][$hook][(int) $priority][] = $callback;
	}

	function add_filter($hook, $callback, $priority = 10): void
	{
		add_action($hook, $callback, $priority);
	}

	function do_action($hook): void
	{
		$callbacks = $GLOBALS['bcf_nav_hooks'][$hook] ?? array();
		ksort($callbacks);
		foreach ($callbacks as $priority_callbacks) {
			foreach ($priority_callbacks as $callback) {
				call_user_func($callback);
			}
		}
	}

	function add_menu_page($page_title, $menu_title, $capability, $menu_slug, $callback, $icon_url = '', $position = null): string
	{
		$GLOBALS['bcf_nav_menus'][] = compact('page_title', 'menu_title', 'capability', 'menu_slug', 'callback', 'icon_url', 'position');
		return 'toplevel_page_' . $menu_slug;
	}

	function add_submenu_page($parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback): string
	{
		$GLOBALS['bcf_nav_submenus'][] = compact('parent_slug', 'page_title', 'menu_title', 'capability', 'menu_slug', 'callback');
		return $parent_slug . '_page_' . $menu_slug;
	}

	function bcf_nav_assert($condition, string $message): void
	{
		if (!$condition) {
			fwrite(STDERR, "FAIL: {$message}\n");
			exit(1);
		}
	}

	function bcf_nav_define_bvm_contract(): void
	{
		if (function_exists('bvmgr_register_admin_page')) {
			return;
		}
		eval(<<<'PHP'
function bvmgr_register_admin_page(array $args): bool
{
	$GLOBALS['bcf_nav_registry_calls']++;
	$GLOBALS['bcf_nav_registry'][$args['slug']] = $args;
	return true;
}
PHP
		);
	}

	function bcf_nav_run_child(string $scenario): void
	{
		if ($scenario === 'bvm-first') {
			bcf_nav_define_bvm_contract();
		}
		if ($scenario === 'legacy-only') {
			eval(<<<'PHP'
function vms_register_admin_page(array $args): bool
{
	$GLOBALS['bcf_nav_legacy_calls']++;
	return true;
}
PHP
			);
		}

		\ConeyProductions\BackstageCalendarFeeds\Plugin::boot();

		if ($scenario === 'bvm-after') {
			bcf_nav_define_bvm_contract();
		}

		if ($scenario === 'bvm-first' || $scenario === 'bvm-after') {
			do_action('vms_admin_register_pages');
			do_action('vms_admin_register_pages');
			do_action('admin_menu');

			bcf_nav_assert($GLOBALS['bcf_nav_registry_calls'] === 1, $scenario . ' registers exactly once');
			bcf_nav_assert(count($GLOBALS['bcf_nav_registry']) === 1, $scenario . ' owns one BVM registry entry');
			bcf_nav_assert(count($GLOBALS['bcf_nav_menus']) === 0, $scenario . ' creates no duplicate top-level menu');
			bcf_nav_assert(count($GLOBALS['bcf_nav_submenus']) === 0, $scenario . ' leaves menu emission to BVM');

			$entry = $GLOBALS['bcf_nav_registry']['backstage'] ?? array();
			bcf_nav_assert(($entry['id'] ?? '') === 'backstage-calendar-feeds', $scenario . ' has stable registry identity');
			bcf_nav_assert(($entry['section'] ?? '') === 'events_schedule', $scenario . ' belongs to planning navigation');
			bcf_nav_assert(($entry['capability'] ?? '') === 'manage_options', $scenario . ' preserves capability');
			bcf_nav_assert(($entry['shell'] ?? false) === true, $scenario . ' participates in the BVM shell');
			bcf_nav_assert(($entry['register'] ?? false) === true, $scenario . ' emits the direct admin page');
			bcf_nav_assert(is_callable($entry['callback'] ?? null), $scenario . ' direct admin callback is callable');
		} else {
			do_action('admin_menu');
			bcf_nav_assert(count($GLOBALS['bcf_nav_menus']) === 1, $scenario . ' creates one standalone top-level menu');
			bcf_nav_assert(count($GLOBALS['bcf_nav_submenus']) === 1, $scenario . ' creates one standalone child page');
			bcf_nav_assert(($GLOBALS['bcf_nav_menus'][0]['menu_slug'] ?? '') === 'backstage', $scenario . ' preserves direct URL slug');
			bcf_nav_assert(($GLOBALS['bcf_nav_submenus'][0]['menu_slug'] ?? '') === 'backstage', $scenario . ' preserves child URL slug');
			bcf_nav_assert(($GLOBALS['bcf_nav_menus'][0]['capability'] ?? '') === 'manage_options', $scenario . ' preserves capability');
			bcf_nav_assert($GLOBALS['bcf_nav_legacy_calls'] === 0, $scenario . ' does not invent a legacy registry dependency');
		}
	}

	function bcf_nav_assert_source_contracts(): void
	{
		$root = dirname(__DIR__);
		$candidate = $root . '/companion-plugins/backstage-calendar-feeds';
		$installed = dirname($root, 2) . '/backstage-calendar-feeds';
		$unchanged = array(
			'includes/provider-interface.php',
			'includes/profile.php',
			'includes/class-drm-calendar-intake-provider.php',
			'includes/class-strict-availability-policy.php',
			'includes/class-supersession-resolver.php',
			'includes/class-publication-ledger.php',
			'includes/class-duplicate-detector.php',
			'includes/class-ics-formatter.php',
			'includes/class-feed-service.php',
			'includes/class-secret-store.php',
			'includes/class-admin-authorization.php',
			'tests/run.php',
		);

		foreach ($unchanged as $relative) {
			bcf_nav_assert(
				hash_file('sha256', $candidate . '/' . $relative) === hash_file('sha256', $installed . '/' . $relative),
				$relative . ' remains byte-identical to accepted 0.1.3'
			);
		}

		$bootstrap = (string) file_get_contents($candidate . '/backstage-calendar-feeds.php');
		$plugin = (string) file_get_contents($candidate . '/includes/class-plugin.php');
		bcf_nav_assert(strpos($bootstrap, 'Version: 0.1.4') !== false && strpos($bootstrap, "define( 'BCF_VERSION', '0.1.4' )") !== false, 'candidate version is 0.1.4');
		bcf_nav_assert(strpos($plugin, "'^backstage-calendar-feeds/([A-Za-z0-9_-]{43})\\.ics$'") !== false, 'public ICS route is unchanged');
		bcf_nav_assert(strpos($plugin, 'Admin_Authorization::can_rotate_secret( $can_manage, $nonce_valid )') !== false, 'token rotation authorization remains intact');
		bcf_nav_assert(strpos($plugin, "admin_url( 'admin.php?page=backstage' )") !== false, 'direct admin URL remains intact');
		bcf_nav_assert(strpos($plugin, "add_action( 'vms_admin_register_pages'") !== false, 'canonical BVM registry hook is used');
		bcf_nav_assert(strpos($plugin, 'vms_register_admin_page') === false, 'legacy page API is not required');
	}

}

namespace ConeyProductions\BackstageCalendarFeeds {
	final class Secret_Store {}
	final class DRM_Calendar_Intake_Provider {}
	final class Strict_Availability_Policy {}
	final class Supersession_Resolver {}
	final class Publication_Ledger {}
	final class Duplicate_Detector {}
	final class ICS_Formatter {}
	final class Feed_Service {
		public function __construct(...$args) {}
	}

	require_once dirname(__DIR__) . '/companion-plugins/backstage-calendar-feeds/includes/class-plugin.php';
}

namespace {
	if (isset($argv[1])) {
		bcf_nav_run_child((string) $argv[1]);
		exit(0);
	}

	bcf_nav_assert_source_contracts();
	foreach (array('bvm-first', 'bvm-after', 'standalone', 'legacy-only') as $scenario) {
		$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' ' . escapeshellarg($scenario);
		passthru($command, $status);
		bcf_nav_assert($status === 0, $scenario . ' subprocess passes');
	}

	echo "Calendar Feeds BVM navigation PASS\n";
}
