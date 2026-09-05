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
	fwrite(STDERR, "Fill Dates admin-notice placement failures:\n- " . implode("\n- ", $failures) . "\n");
	exit(1);
}

$helpersFile = $addonRoot . '/includes/helpers.php';
$toursFile = $addonRoot . '/includes/tours.php';
$adminPageFile = $addonRoot . '/includes/admin-page.php';
foreach (array($helpersFile, $toursFile, $adminPageFile) as $sourceFile) {
	$assert(is_file($sourceFile), 'Required Fill Dates source must exist: ' . $sourceFile);
}
if ($failures !== array()) {
	fwrite(STDERR, "Fill Dates admin-notice placement failures:\n- " . implode("\n- ", $failures) . "\n");
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

$GLOBALS['bvm_fd_notice_test'] = array(
	'actions' => array(),
	'filters' => array(),
	'capabilities' => array('manage_options' => true),
	'post_types' => array(),
	'current_screen' => null,
	'returned_hook' => 'vms_page_vms-fill-dates',
);

function add_action(string $hook, $callback, int $priority = 10, int $acceptedArgs = 1): bool
{
	$GLOBALS['bvm_fd_notice_test']['actions'][$hook][] = array($callback, $priority, $acceptedArgs);
	return true;
}

function add_filter(string $hook, $callback, int $priority = 10, int $acceptedArgs = 1): bool
{
	$GLOBALS['bvm_fd_notice_test']['filters'][$hook][] = array($callback, $priority, $acceptedArgs);
	return true;
}

function __(string $text, string $domain = ''): string
{
	unset($domain);
	return $text;
}

function esc_html__(string $text, string $domain = ''): string
{
	return esc_html(__($text, $domain));
}

function esc_html($value): string
{
	return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function sanitize_key($value): string
{
	if (!is_scalar($value)) {
		return '';
	}
	$sanitized = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $value));
	return is_string($sanitized) ? $sanitized : '';
}

function post_type_exists(string $postType): bool
{
	return !empty($GLOBALS['bvm_fd_notice_test']['post_types'][$postType]);
}

function current_user_can(string $capability): bool
{
	return !empty($GLOBALS['bvm_fd_notice_test']['capabilities'][$capability]);
}

function get_current_screen()
{
	return $GLOBALS['bvm_fd_notice_test']['current_screen'];
}

function doing_action(string $hook = ''): bool
{
	unset($hook);
	return false;
}

function did_action(string $hook): int
{
	unset($hook);
	return 0;
}

function add_submenu_page(
	string $parentSlug,
	string $pageTitle,
	string $menuTitle,
	string $capability,
	string $menuSlug,
	$callback = '',
	$position = null
) {
	unset($parentSlug, $pageTitle, $menuTitle, $capability, $menuSlug, $callback, $position);
	return $GLOBALS['bvm_fd_notice_test']['returned_hook'];
}

function wp_enqueue_style(string $handle, string $source, array $dependencies = array(), $version = false): void
{
	unset($handle, $source, $dependencies, $version);
}

final class VMS_Tours_Service
{
	private static $instance;

	public static function instance(): self
	{
		if (!(self::$instance instanceof self)) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function refresh_filter_tours(): void
	{
	}
}

require_once $helpersFile;
require_once $toursFile;
require_once $adminPageFile;

$capture = static function ($callback): string {
	if (!is_callable($callback)) {
		return '';
	}
	ob_start();
	$callback();
	return (string) ob_get_clean();
};

$callbacksForHook = static function (string $hook): array {
	$rows = $GLOBALS['bvm_fd_notice_test']['actions'][$hook] ?? array();
	usort($rows, static fn(array $left, array $right): int => $left[1] <=> $right[1]);
	return array_column($rows, 0);
};

$runHook = static function (string $hook) use ($callbacksForHook): void {
	foreach ($callbacksForHook($hook) as $callback) {
		call_user_func($callback);
	}
};

$adminNoticeCallbacks = $callbacksForHook('admin_notices');
$assert(in_array('vms_fd_render_dependency_notice', $adminNoticeCallbacks, true), 'Fill Dates dependency warning must register on admin_notices.');
$assert(in_array('vms_fd_render_notice', $adminNoticeCallbacks, true), 'Fill Dates action-result notices must register on admin_notices.');
$assert(count(array_keys($adminNoticeCallbacks, 'vms_fd_render_dependency_notice', true)) === 1, 'Dependency warning must register exactly once.');
$assert(count(array_keys($adminNoticeCallbacks, 'vms_fd_render_notice', true)) === 1, 'Action-result notice renderer must register exactly once.');

// The dependency warning must remain discoverable even when WordPress cannot attach the submenu.
$GLOBALS['bvm_fd_notice_test']['returned_hook'] = false;
vms_fd_register_menu();
$GLOBALS['bvm_fd_notice_test']['post_types'] = array();
$GLOBALS['bvm_fd_notice_test']['current_screen'] = (object) array('id' => 'dashboard');
$_GET = array();
$nativeDependency = $capture(static function () use ($runHook): void {
	$runHook('admin_notices');
});
$assert(substr_count($nativeDependency, 'notice notice-error') === 1, 'Missing BVM must produce exactly one native dependency error notice.');
$assert(substr_count($nativeDependency, 'Backstage Venue Manager (BVM)') === 1, 'Dependency warning must accurately name Backstage Venue Manager (BVM) once.');
$assert(strpos($nativeDependency, 'Activate VMS') === false, 'Dependency warning must not retain the inaccurate Activate VMS wording.');

$pageDependency = $capture('vms_fd_render_admin_page_content');
$assert(strpos($pageDependency, 'class="notice') === false, 'The Fill Dates page-content callback must not emit dependency notice markup.');
$assert(strpos($pageDependency, 'Backstage Venue Manager (BVM)') === false, 'The dependency warning must not be duplicated inside page content.');

$GLOBALS['bvm_fd_notice_test']['capabilities']['manage_options'] = false;
$assert($capture('vms_fd_render_dependency_notice') === '', 'Dependency warning must not display to users who cannot manage Fill Dates.');
$GLOBALS['bvm_fd_notice_test']['capabilities']['manage_options'] = true;

// Successful registration establishes the Phase 2A authoritative screen hook.
$GLOBALS['bvm_fd_notice_test']['returned_hook'] = 'vms_page_vms-fill-dates';
vms_fd_register_menu();
$GLOBALS['bvm_fd_notice_test']['post_types'] = array(
	'vms_event_plan' => true,
	'vms_vendor' => true,
);
$GLOBALS['bvm_fd_notice_test']['current_screen'] = (object) array('id' => 'vms_page_vms-fill-dates');

$expectedNotices = array(
	'primary_assigned' => 'Primary vendor assigned.',
	'primary_cleared' => 'Primary vendor cleared.',
	'secondary_added' => 'Secondary vendor assignments updated.',
	'secondary_removed' => 'Secondary vendor assignments updated.',
);
foreach ($expectedNotices as $noticeKey => $expectedMessage) {
	$_GET = array('vms_fd_notice' => $noticeKey);
	$output = $capture(static function () use ($runHook): void {
		$runHook('admin_notices');
	});
	$assert(substr_count($output, 'notice notice-success is-dismissible') === 1, $noticeKey . ' must produce exactly one dismissible native success notice.');
	$assert(substr_count($output, $expectedMessage) === 1, $noticeKey . ' must preserve its existing success message exactly once.');
}

$_GET = array('vms_fd_notice' => 'primary_assigned');
$GLOBALS['bvm_fd_notice_test']['current_screen'] = (object) array('id' => 'dashboard');
$assert($capture('vms_fd_render_notice') === '', 'Action-result notices must not render on unrelated admin screens.');
$GLOBALS['bvm_fd_notice_test']['current_screen'] = (object) array('id' => 'vms_page_vms-fill-dates');
$GLOBALS['bvm_fd_notice_test']['capabilities']['manage_options'] = false;
$assert($capture('vms_fd_render_notice') === '', 'Action-result notices must not display to users who cannot manage Fill Dates.');
$GLOBALS['bvm_fd_notice_test']['capabilities']['manage_options'] = true;

$adminSource = (string) file_get_contents($adminPageFile);
$pageContentStart = strpos($adminSource, "if (!function_exists('vms_fd_render_admin_page_content'))");
$pageContentEnd = strpos($adminSource, "if (!function_exists('vms_fd_render_admin_page'))", is_int($pageContentStart) ? $pageContentStart + 1 : 0);
$pageContentSource = is_int($pageContentStart) && is_int($pageContentEnd)
	? substr($adminSource, $pageContentStart, $pageContentEnd - $pageContentStart)
	: '';
$assert($pageContentSource !== '', 'The Fill Dates page-content callback source must remain discoverable.');
$assert(strpos($adminSource, 'vms_fd_render_notice();') === false, 'The page-content callback must not invoke the native action-notice renderer.');
$assert(strpos($pageContentSource, 'class="notice') === false, 'The page-content callback source must not contain inline native notice markup.');
$assert(substr_count($adminSource, 'notice notice-warning inline') === 1, 'The contextual Needs Review warning must remain inline in Event Plan detail content.');
$assert(strpos($adminSource, "esc_html__('Needs Review', 'vms-fill-dates')") !== false, 'The contextual Needs Review content must remain in Fill Dates detail rendering.');

if ($failures !== array()) {
	fwrite(STDERR, "Fill Dates admin-notice placement failures:\n- " . implode("\n- ", $failures) . "\n");
	exit(1);
}

echo "Fill Dates native admin-notice placement passed.\n";
