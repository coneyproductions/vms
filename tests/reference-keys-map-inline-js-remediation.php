<?php
declare(strict_types=1);

$pluginRoot = dirname(__DIR__);
$keysMapPath = $pluginRoot . '/includes/admin/reference/keys-map.php';
$assetPath = $pluginRoot . '/assets/js/vms-reference-keys-map.js';
$menuPath = $pluginRoot . '/includes/admin/menu.php';
$corePluginPath = $pluginRoot . '/includes/core/plugin.php';
$adminUiAssetsPath = $pluginRoot . '/includes/admin-ui/assets.php';

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

try {
	$keysMapSource = $readFile($keysMapPath);
	$assetSource = $readFile($assetPath);
	$menuSource = $readFile($menuPath);
	$corePluginSource = $readFile($corePluginPath);
	$adminUiAssetsSource = $readFile($adminUiAssetsPath);

	$assert(strpos($keysMapSource, '<script') === false, 'Reference Keys Map PHP should no longer emit an executable clipboard <script> block.');
	$assert(strpos($keysMapSource, 'document.execCommand(\'copy\')') === false, 'Reference Keys Map PHP should no longer contain the clipboard execCommand call.');
	$assert(strpos($keysMapSource, 'wp_add_inline_script') === false, 'Reference Keys Map PHP should not reintroduce executable clipboard behavior through wp_add_inline_script().');

	$assert(strpos($keysMapSource, 'function bvmgr_admin_reference_keys_map_enqueue_assets()') !== false, 'Reference Keys Map should declare a dedicated admin_enqueue_scripts callback.');
	$assert(strpos($keysMapSource, "add_action('admin_enqueue_scripts', 'bvmgr_admin_reference_keys_map_enqueue_assets', 50);") !== false, 'Reference Keys Map should register its page-specific enqueue callback.');
	$assert(strpos($keysMapSource, "return 'vms-reference-keys-map';") !== false, 'Reference Keys Map should retain the exact page slug for its dedicated asset gate.');
	$assert(preg_match('~\$page\s*!==\s*bvmgr_admin_reference_keys_map_page_slug\(\)~', $keysMapSource) === 1, 'Reference Keys Map enqueue should bail unless the current admin page matches the exact page slug.');
	$assert(strpos($keysMapSource, "BVMGR_PLUGIN_URL . 'assets/js/vms-reference-keys-map.js'") !== false, 'Reference Keys Map should enqueue the external asset from assets/js/vms-reference-keys-map.js.');
	$assert(substr_count($keysMapSource, "current_user_can('manage_options')") >= 2, 'Reference Keys Map should keep both the page capability guard and the enqueue capability guard.');

	$assert(
		preg_match(
			"~add_submenu_page\\(\\s*'vms-dashboard',\\s*__\\('Reference: Keys \\+ Identifiers', 'backstage-venue-manager'\\),\\s*__\\('Reference: Keys \\+ Identifiers', 'backstage-venue-manager'\\),\\s*'manage_options',\\s*'vms-reference-keys-map',\\s*'bvmgr_admin_reference_keys_map_page'\\s*\\);~s",
			$menuSource
		) === 1,
		'Reference Keys Map page registration should retain the existing parent, title, capability, slug, and callback.'
	);

	$assert(strpos($keysMapSource, 'id="vms-copy-keys-map"') !== false, 'Reference Keys Map should preserve the existing copy button ID.');
	$assert(strpos($keysMapSource, 'id="vms-keys-map-text"') !== false, 'Reference Keys Map should preserve the existing textarea ID.');
	$assert(strpos($keysMapSource, 'esc_textarea($out)') !== false, 'Reference Keys Map should preserve the textarea rendering path.');
	$assert(strpos($keysMapSource, '$out .= "Timestamp: " . gmdate(\'Y-m-d H:i\') . " UTC\\n";') !== false, 'Reference Keys Map should preserve the current textarea payload contract, including the UTC timestamp line.');

	$assert(strpos($keysMapSource, 'data-vms-copy-default-label="<?php echo esc_attr__(\'Copy to clipboard\', \'backstage-venue-manager\'); ?>"') !== false, 'Reference Keys Map should pass the default button label through an inert escaped data attribute.');
	$assert(strpos($keysMapSource, 'data-vms-copy-success-label="<?php echo esc_attr__(\'Copied\', \'backstage-venue-manager\'); ?>"') !== false, 'Reference Keys Map should pass the success button label through an inert escaped data attribute.');
	$assert(strpos($keysMapSource, 'data-vms-copy-failure-label="<?php echo esc_attr__(\'Copy failed\', \'backstage-venue-manager\'); ?>"') !== false, 'Reference Keys Map should pass the failure button label through an inert escaped data attribute.');

	$assert(file_exists($assetPath), 'Reference Keys Map external clipboard asset should exist.');
	$assert(strpos($assetSource, "document.getElementById('vms-copy-keys-map')") !== false, 'Reference Keys Map asset should target the existing copy button by ID.');
	$assert(strpos($assetSource, "document.getElementById('vms-keys-map-text')") !== false, 'Reference Keys Map asset should target the existing textarea by ID.');
	$assert(strpos($assetSource, "button.getAttribute('data-vms-copy-default-label')") !== false, 'Reference Keys Map asset should read the inert default label handoff.');
	$assert(strpos($assetSource, "button.getAttribute('data-vms-copy-success-label')") !== false, 'Reference Keys Map asset should read the inert success label handoff.');
	$assert(strpos($assetSource, "button.getAttribute('data-vms-copy-failure-label')") !== false, 'Reference Keys Map asset should read the inert failure label handoff.');
	$assert(strpos($assetSource, 'textarea.focus();') !== false, 'Reference Keys Map asset should preserve the textarea focus behavior before copying.');
	$assert(strpos($assetSource, 'textarea.select();') !== false, 'Reference Keys Map asset should preserve the textarea select behavior before copying.');
	$assert(strpos($assetSource, "document.execCommand('copy');") !== false, 'Reference Keys Map asset should preserve document.execCommand(\'copy\').');
	$assert(strpos($assetSource, '}, 1500);') !== false, 'Reference Keys Map asset should preserve the 1500 ms label-reset delay.');
	$assert(strpos($assetSource, 'if (!button || !textarea) {') !== false, 'Reference Keys Map asset should safely no-op when the expected DOM nodes are absent.');
	$assert(strpos($assetSource, "button.dataset.vmsKeysMapClipboardBound === '1'") !== false, 'Reference Keys Map asset should guard against duplicate listener binding.');
	$assert(strpos($assetSource, "button.dataset.vmsKeysMapClipboardBound = '1';") !== false, 'Reference Keys Map asset should mark the button after binding the listener.');
	$assert(substr_count($assetSource, "addEventListener('click'") === 1, 'Reference Keys Map asset should bind the click listener exactly once.');

	$assert(strpos($corePluginSource, 'vms-reference-keys-map') === false, 'Reference Keys Map asset should not be registered through the global all-admin core asset loader.');
	$assert(strpos($adminUiAssetsSource, 'vms-reference-keys-map') === false, 'Reference Keys Map asset should not be registered through the shared VMS admin UI asset loader.');

	fwrite(STDOUT, "reference keys map inline js remediation: PASS\n");
} catch (Throwable $e) {
	fwrite(STDERR, 'reference keys map inline js remediation: FAIL - ' . $e->getMessage() . "\n");
	exit(1);
}
