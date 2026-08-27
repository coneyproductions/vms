<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);
define('BVMGR_PLUGIN_URL', 'https://example.test/wp-content/plugins/backstage-venue-manager/');
define('BVMGR_VERSION', 'test-version');

$GLOBALS['vms_test_options'] = array();
$GLOBALS['vms_test_screen'] = (object) array('post_type' => '');
$GLOBALS['vms_test_styles'] = array();
$GLOBALS['vms_test_scripts'] = array();

function sanitize_key($value): string
{
	if (!is_scalar($value)) {
		return '';
	}

	return preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $value)) ?: '';
}

function wp_unslash($value)
{
	return $value;
}

function get_option(string $option, $default = false)
{
	return array_key_exists($option, $GLOBALS['vms_test_options']) ? $GLOBALS['vms_test_options'][$option] : $default;
}

function get_current_screen()
{
	return $GLOBALS['vms_test_screen'];
}

function wp_enqueue_style(string $handle, string $src = '', array $deps = array(), $ver = false, string $media = 'all'): void
{
	$GLOBALS['vms_test_styles'][$handle] = compact('src', 'deps', 'ver', 'media');
}

function wp_enqueue_script(string $handle, string $src = '', array $deps = array(), $ver = false, bool $in_footer = false): void
{
	$GLOBALS['vms_test_scripts'][$handle] = compact('src', 'deps', 'ver', 'in_footer');
}

function bvmgr_request_read_key(array $source, string $key): string
{
	if (!array_key_exists($key, $source) || is_array($source[$key])) {
		return '';
	}

	return sanitize_key((string) wp_unslash($source[$key]));
}

function vms_test_find_matching_brace(string $code, int $start): int
{
	$length = strlen($code);
	$depth = 0;

	for ($i = $start; $i < $length; $i++) {
		$char = $code[$i];
		if ($char === '{') {
			$depth++;
			continue;
		}
		if ($char !== '}') {
			continue;
		}

		$depth--;
		if ($depth === 0) {
			return $i;
		}
	}

	throw new RuntimeException('Matching brace not found.');
}

function vms_test_extract_named_function(string $path, string $functionName): string
{
	$code = (string) file_get_contents($path);
	$marker = 'function ' . $functionName . '(';
	$functionPos = strpos($code, $marker);
	if ($functionPos === false) {
		throw new RuntimeException('Function not found: ' . $functionName);
	}

	$brace = strpos($code, '{', $functionPos);
	if ($brace === false) {
		throw new RuntimeException('Function brace not found: ' . $functionName);
	}

	$end = vms_test_find_matching_brace($code, $brace);
	return substr($code, $functionPos, $end - $functionPos + 1);
}

function vms_test_extract_inline_closure(string $path, string $marker): string
{
	$code = (string) file_get_contents($path);
	$markerPos = strpos($code, $marker);
	if ($markerPos === false) {
		throw new RuntimeException('Marker not found: ' . $marker);
	}

	$functionPos = strpos($code, 'function', $markerPos);
	if ($functionPos === false) {
		throw new RuntimeException('Closure not found for marker: ' . $marker);
	}

	$brace = strpos($code, '{', $functionPos);
	if ($brace === false) {
		throw new RuntimeException('Closure brace not found for marker: ' . $marker);
	}

	$end = vms_test_find_matching_brace($code, $brace);
	return substr($code, $functionPos, $end - $functionPos + 1);
}

$assert = static function (bool $condition, string $message): void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
};

$resetAssets = static function (): void {
	$GLOBALS['vms_test_styles'] = array();
	$GLOBALS['vms_test_scripts'] = array();
};

$pluginRoot = dirname(__DIR__);
$vendorApplicationsPath = $pluginRoot . '/includes/vendor-applications.php';
$adminCssPath = $pluginRoot . '/assets/css/vms-admin.css';
$corePluginPath = $pluginRoot . '/includes/core/plugin.php';

$vendorApplicationsSource = file_get_contents($vendorApplicationsPath);
$adminCssSource = file_get_contents($adminCssPath);

$assert(is_string($vendorApplicationsSource) && $vendorApplicationsSource !== '', 'Vendor Applications source should be readable.');
$assert(is_string($adminCssSource) && $adminCssSource !== '', 'Admin stylesheet source should be readable.');
$assert(strpos($vendorApplicationsSource, 'vms_vendor_applications_admin_css') === false, 'Vendor Applications should not retain the removed inline admin CSS emitter.');
$assert(strpos($vendorApplicationsSource, "admin_head-edit.php") === false, 'Vendor Applications should not hook admin_head-edit.php for CSS anymore.');
$assert(strpos($vendorApplicationsSource, '<style') === false, 'Vendor Applications should not print inline style tags.');
$assert(strpos($vendorApplicationsSource, '<span class="vms-status-pill ') !== false, 'Vendor Applications should keep the status-pill markup.');

eval(vms_test_extract_named_function($vendorApplicationsPath, 'vms_vendor_app_status_pill_class'));

$assert(vms_vendor_app_status_pill_class('pending') === 'vms-pill-yellow', 'Pending applications should keep the yellow pill class.');
$assert(vms_vendor_app_status_pill_class('holding') === 'vms-pill-blue', 'Holding applications should keep the blue pill class.');
$assert(vms_vendor_app_status_pill_class('approved') === 'vms-pill-green', 'Approved applications should keep the green pill class.');
$assert(vms_vendor_app_status_pill_class('rejected') === 'vms-pill-red', 'Rejected applications should keep the red pill class.');
$assert(vms_vendor_app_status_pill_class('unknown') === 'vms-pill-grey', 'Fallback applications should keep the grey pill class.');

$requiredCssSnippets = array(
	'body.post-type-vms_vendor_app .vms-status-pill',
	'body.post-type-vms_vendor_application .vms-status-pill',
	'body.vms-cpt-vms_vendor_app .vms-status-pill',
	'body.vms-cpt-vms_vendor_application .vms-status-pill',
	'padding: 2px 8px;',
	'border-radius: 999px;',
	'body.post-type-vms_vendor_app .vms-pill-blue',
	'background: #dbeafe;',
	'color: #1e3a8a;',
	'body.post-type-vms_vendor_app .vms-pill-yellow',
	'background: #fef3c7;',
	'color: #92400e;',
	'body.post-type-vms_vendor_app .vms-pill-green',
	'background: #dcfce7;',
	'color: #166534;',
	'body.post-type-vms_vendor_app .vms-pill-red',
	'background: #fee2e2;',
	'color: #991b1b;',
	'body.post-type-vms_vendor_app .vms-pill-grey',
	'background: #f3f4f6;',
	'color: #374151;',
);

foreach ($requiredCssSnippets as $snippet) {
	$assert(strpos($adminCssSource, $snippet) !== false, 'Admin stylesheet should contain Vendor Applications remediation snippet: ' . $snippet);
}

$adminEnqueue = eval(
	'return ' . vms_test_extract_inline_closure($corePluginPath, "add_action('admin_enqueue_scripts', function (\$hook_suffix = ''): void {") . ';'
);

$_GET = array();
$GLOBALS['vms_test_screen'] = (object) array('post_type' => 'post');
$resetAssets();
$adminEnqueue('edit.php');
$assert($GLOBALS['vms_test_styles'] === array(), 'Non-VMS admin screens should not enqueue the VMS admin stylesheet bundle.');

$_GET = array();
$GLOBALS['vms_test_screen'] = (object) array('post_type' => 'vms_vendor_app');
$resetAssets();
$adminEnqueue('edit.php');
$assert(isset($GLOBALS['vms_test_styles']['vms-shared']), 'Vendor Applications admin screens should enqueue vms-shared.');
$assert(isset($GLOBALS['vms_test_styles']['vms-ui']), 'Vendor Applications admin screens should enqueue vms-ui.');
$assert(isset($GLOBALS['vms_test_styles']['vms-admin']), 'Vendor Applications admin screens should enqueue vms-admin.');
$assert(($GLOBALS['vms_test_styles']['vms-admin']['src'] ?? '') === 'https://example.test/wp-content/plugins/backstage-venue-manager/assets/css/vms-admin.css', 'Vendor Applications admin screens should use the external admin stylesheet asset.');

$_GET = array();
$GLOBALS['vms_test_screen'] = (object) array('post_type' => 'vms_vendor_application');
$resetAssets();
$adminEnqueue('edit.php');
$assert(isset($GLOBALS['vms_test_styles']['vms-admin']), 'Legacy Vendor Applications admin screens should still enqueue vms-admin.');

fwrite(STDOUT, "Vendor Applications admin CSS remediation OK.\n");
