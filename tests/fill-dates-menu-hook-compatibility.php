<?php
declare(strict_types=1);

$failures = array();
$assert = static function (bool $condition, string $message) use (&$failures): void {
	if (!$condition) {
		$failures[] = $message;
	}
};

$repositoryRoot = dirname(__DIR__);
$configuredAddonRoot = getenv('BVM_FILL_DATES_ROOT');
$addonRoot = realpath(
	is_string($configuredAddonRoot) && $configuredAddonRoot !== ''
		? $configuredAddonRoot
		: $repositoryRoot . '/../../vms-fill-dates'
);

$assert(is_string($addonRoot) && $addonRoot !== '', 'The installed Fill Dates source root must resolve.');
if (!is_string($addonRoot) || $addonRoot === '') {
	fwrite(STDERR, "Fill Dates menu-hook compatibility failures:\n- " . implode("\n- ", $failures) . "\n");
	exit(1);
}

$adminPageFile = $addonRoot . '/includes/admin-page.php';
$toursFile = $addonRoot . '/includes/tours.php';
$assert(is_file($adminPageFile), 'The Fill Dates admin-page source must exist.');
$assert(is_file($toursFile), 'The Fill Dates tours source must exist.');
if (!is_file($adminPageFile) || !is_file($toursFile)) {
	fwrite(STDERR, "Fill Dates menu-hook compatibility failures:\n- " . implode("\n- ", $failures) . "\n");
	exit(1);
}

if (!defined('ABSPATH')) {
	define('ABSPATH', $repositoryRoot . '/');
}
if (!defined('VMS_FILL_DATES_URL')) {
	define('VMS_FILL_DATES_URL', 'https://example.test/wp-content/plugins/vms-fill-dates/');
}
if (!defined('VMS_FILL_DATES_VERSION')) {
	define('VMS_FILL_DATES_VERSION', 'test');
}

$GLOBALS['bvm_fd_test'] = array(
	'actions' => array(),
	'filters' => array(),
	'menu_calls' => array(),
	'returned_hook' => 'vms_page_vms-fill-dates',
	'enqueued_styles' => array(),
);

if (!function_exists('add_action')) {
	function add_action(string $hook, $callback, int $priority = 10, int $acceptedArgs = 1): void
	{
		$GLOBALS['bvm_fd_test']['actions'][] = array($hook, $callback, $priority, $acceptedArgs);
	}
}

if (!function_exists('add_filter')) {
	function add_filter(string $hook, $callback, int $priority = 10, int $acceptedArgs = 1): void
	{
		$GLOBALS['bvm_fd_test']['filters'][] = array($hook, $callback, $priority, $acceptedArgs);
	}
}

if (!function_exists('__')) {
	function __(string $text, string $domain = ''): string
	{
		unset($domain);
		return $text;
	}
}

if (!function_exists('doing_action')) {
	function doing_action(string $hook = ''): bool
	{
		unset($hook);
		return false;
	}
}

if (!function_exists('did_action')) {
	function did_action(string $hook): int
	{
		unset($hook);
		return 0;
	}
}

if (!function_exists('add_submenu_page')) {
	function add_submenu_page(
		string $parentSlug,
		string $pageTitle,
		string $menuTitle,
		string $capability,
		string $menuSlug,
		$callback = '',
		$position = null
	) {
		$GLOBALS['bvm_fd_test']['menu_calls'][] = array(
			'parent_slug' => $parentSlug,
			'page_title' => $pageTitle,
			'menu_title' => $menuTitle,
			'capability' => $capability,
			'menu_slug' => $menuSlug,
			'callback' => $callback,
			'position' => $position,
		);
		return $GLOBALS['bvm_fd_test']['returned_hook'];
	}
}

if (!function_exists('wp_enqueue_style')) {
	function wp_enqueue_style(string $handle, string $source, array $dependencies = array(), $version = false): void
	{
		$GLOBALS['bvm_fd_test']['enqueued_styles'][] = array($handle, $source, $dependencies, $version);
	}
}

if (!class_exists('VMS_Tours_Service')) {
	final class VMS_Tours_Service
	{
		private static $instance;

		public $refreshCount = 0;

		public static function instance(): self
		{
			if (!(self::$instance instanceof self)) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		public function refresh_filter_tours(): void
		{
			$this->refreshCount++;
		}
	}
}

require_once $toursFile;
require_once $adminPageFile;

$assert(function_exists('vms_fd_register_menu'), 'Fill Dates must expose its submenu registration callback.');
$assert(function_exists('vms_fd_enqueue_admin_assets'), 'Fill Dates must expose its admin asset callback.');
$assert(function_exists('vms_fd_register_tours'), 'Fill Dates must expose its tour registration callback.');
$assert(function_exists('vms_fd_admin_page_hook'), 'Fill Dates must expose one stored registered-hook source of truth.');

$tourContext = static function (array $tours): array {
	foreach ($tours as $tour) {
		if (is_array($tour) && ($tour['id'] ?? '') === 'vms_fill_dates_overview') {
			$contexts = isset($tour['contexts']) && is_array($tour['contexts']) ? $tour['contexts'] : array();
			return isset($contexts[0]) && is_array($contexts[0]) ? $contexts[0] : array();
		}
	}
	return array();
};

$GLOBALS['bvm_fd_test']['returned_hook'] = 'vms_page_vms-fill-dates';
vms_fd_register_menu();
$menuCall = $GLOBALS['bvm_fd_test']['menu_calls'][0] ?? array();
$assert(($menuCall['parent_slug'] ?? null) === 'vms-dashboard', 'Fill Dates must remain under the current BVM parent slug.');
$assert(($menuCall['page_title'] ?? null) === 'Fill Dates', 'Fill Dates page title must remain unchanged.');
$assert(($menuCall['menu_title'] ?? null) === 'Fill Dates', 'Fill Dates menu title must remain unchanged.');
$assert(($menuCall['capability'] ?? null) === 'manage_options', 'Fill Dates capability must remain unchanged.');
$assert(($menuCall['menu_slug'] ?? null) === 'vms-fill-dates', 'Fill Dates menu slug must remain unchanged.');
$assert(($menuCall['callback'] ?? null) === 'vms_fd_render_admin_page', 'Fill Dates page callback must remain unchanged.');

if (function_exists('vms_fd_admin_page_hook')) {
	$assert(vms_fd_admin_page_hook() === 'vms_page_vms-fill-dates', 'Fill Dates must record the current hook returned by WordPress.');
}
$assert(VMS_Tours_Service::instance()->refreshCount === 1, 'Successful submenu registration must refresh the already-loaded BVM tour registry once.');

$GLOBALS['bvm_fd_test']['enqueued_styles'] = array();
vms_fd_enqueue_admin_assets('vms_page_vms-fill-dates');
$assert(count($GLOBALS['bvm_fd_test']['enqueued_styles']) === 1, 'Fill Dates assets must load for the hook returned by WordPress.');
vms_fd_enqueue_admin_assets('vms-dashboard_page_vms-fill-dates');
$assert(count($GLOBALS['bvm_fd_test']['enqueued_styles']) === 1, 'Fill Dates assets must not load for the former predicted hook.');

$context = $tourContext(vms_fd_register_tours(array()));
$assert(($context['screen_id'] ?? null) === 'vms_page_vms-fill-dates', 'Fill Dates tour screen_id must use the returned WordPress hook.');
$assert(($context['page_hook'] ?? null) === 'vms_page_vms-fill-dates', 'Fill Dates tour page_hook must use the returned WordPress hook.');

// Simulate a future parent-menu label change. WordPress may return a different opaque hook,
// but Fill Dates should consume it without any add-on source change.
$GLOBALS['bvm_fd_test']['returned_hook'] = 'backstage-venue-manager_page_vms-fill-dates';
vms_fd_register_menu();
if (function_exists('vms_fd_admin_page_hook')) {
	$assert(vms_fd_admin_page_hook() === 'backstage-venue-manager_page_vms-fill-dates', 'Fill Dates must replace its stored hook with WordPress\'s latest return value.');
}
$GLOBALS['bvm_fd_test']['enqueued_styles'] = array();
vms_fd_enqueue_admin_assets('backstage-venue-manager_page_vms-fill-dates');
$assert(count($GLOBALS['bvm_fd_test']['enqueued_styles']) === 1, 'Fill Dates assets must follow a changed hook returned by WordPress.');
$context = $tourContext(vms_fd_register_tours(array()));
$assert(($context['screen_id'] ?? null) === 'backstage-venue-manager_page_vms-fill-dates', 'Fill Dates tour screen_id must follow a changed returned hook.');
$assert(($context['page_hook'] ?? null) === 'backstage-venue-manager_page_vms-fill-dates', 'Fill Dates tour page_hook must follow a changed returned hook.');

$GLOBALS['bvm_fd_test']['returned_hook'] = false;
vms_fd_register_menu();
if (function_exists('vms_fd_admin_page_hook')) {
	$assert(vms_fd_admin_page_hook() === '', 'A failed submenu registration must clear the stored hook without notices or fatals.');
}
$refreshCountAfterFailure = VMS_Tours_Service::instance()->refreshCount;
$assert($refreshCountAfterFailure === 2, 'A failed submenu registration must not refresh the BVM tour registry.');
$GLOBALS['bvm_fd_test']['enqueued_styles'] = array();
vms_fd_enqueue_admin_assets('backstage-venue-manager_page_vms-fill-dates');
$assert($GLOBALS['bvm_fd_test']['enqueued_styles'] === array(), 'A failed submenu registration must prevent Fill Dates asset loading.');
$assert(vms_fd_register_tours(array()) === array(), 'A failed submenu registration must not expose a Fill Dates tour with an invalid context.');

$adminSource = (string) file_get_contents($adminPageFile);
$tourSource = (string) file_get_contents($toursFile);
$assert(strpos($adminSource, "'vms-dashboard_page_vms-fill-dates'") === false, 'Fill Dates admin source must not hard-code the former predicted hook.');
$assert(strpos($adminSource, "'vms_page_vms-fill-dates'") === false, 'Fill Dates admin source must not hard-code the current returned hook.');
$assert(strpos($tourSource, "'vms-dashboard_page_vms-fill-dates'") === false, 'Fill Dates tour source must not hard-code the former predicted hook.');
$assert(strpos($tourSource, "'vms_page_vms-fill-dates'") === false, 'Fill Dates tour source must not hard-code the current returned hook.');

if ($failures !== array()) {
	fwrite(STDERR, "Fill Dates menu-hook compatibility failures:\n- " . implode("\n- ", $failures) . "\n");
	exit(1);
}

echo "Fill Dates returned menu-hook compatibility passed.\n";
