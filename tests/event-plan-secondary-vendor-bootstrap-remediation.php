<?php
declare(strict_types=1);

$pluginRoot = dirname(__DIR__);
$eventPlansPath = $pluginRoot . '/includes/cpt/event-plans.php';
$secondaryVendorsPath = $pluginRoot . '/includes/cpt/event-plans/partials/secondary-vendors.php';
$secondaryVendorAssetPath = $pluginRoot . '/assets/js/vms-event-plan-secondary-vendors.js';
$adminUiAssetsPath = $pluginRoot . '/includes/admin-ui/assets.php';
$shellAssetPath = $pluginRoot . '/assets/js/vms-event-plan-shell.js';

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
	$eventPlansSource = $readFile($eventPlansPath);
	$secondaryVendorsSource = $readFile($secondaryVendorsPath);
	$adminUiAssetsSource = $readFile($adminUiAssetsPath);
	$secondaryVendorAssetSource = $readFile($secondaryVendorAssetPath);
	$shellAssetSource = $readFile($shellAssetPath);

	$assert(
		preg_match('/<script\b(?![^>]*type=(["\'])application\/json\1)[^>]*>/i', $secondaryVendorsSource) !== 1,
		'Secondary Vendors partial should not contain an executable <script> block.'
	);
	$assert(substr_count($secondaryVendorsSource, '<script') === 1, 'Secondary Vendors partial should retain only the non-executable JSON script tag.');
	$assert(strpos($secondaryVendorsSource, '<script type="application/json" data-vms-secondary-config>') !== false, 'Secondary Vendors partial should retain the scoped application/json configuration payload.');
	$assert(strpos($secondaryVendorsSource, 'window.vmsEventPlanInitSecondaryVendors') === false, 'Secondary Vendors partial should not self-bootstrap the live initializer.');
	$assert(strpos($secondaryVendorsSource, 'id="vms-secondary-vendors-section"') !== false, 'Secondary Vendors section wrapper should remain present.');
	$assert(strpos($secondaryVendorsSource, 'data-vms-save-url=') !== false, 'Secondary Vendors save URL contract should remain present.');
	$assert(strpos($secondaryVendorsSource, 'data-vms-save-nonce=') !== false, 'Secondary Vendors save nonce contract should remain present.');
	$assert(strpos($secondaryVendorsSource, 'data-vms-save-post-id=') !== false, 'Secondary Vendors save post ID contract should remain present.');
	$assert(strpos($secondaryVendorsSource, 'data-vms-secondary-config') !== false, 'Secondary Vendors JSON config selector should remain present.');
	$assert(strpos($secondaryVendorsSource, 'name="vms_secondary_vendors_module_detached"') !== false, 'Detached module hidden field should remain present.');
	$assert(strpos($secondaryVendorsSource, 'name="vms_clear_secondary_vendors"') !== false, 'Secondary Vendors clear-intent hidden field should remain present.');
	$assert(strpos($secondaryVendorsSource, 'id="vms-secondary-vendor-groups"') !== false, 'Secondary Vendors group wrapper should remain present.');
	$assert(strpos($secondaryVendorsSource, 'id="vms-secondary-vendor-save"') !== false, 'Secondary Vendors save control should remain present.');
	$assert(strpos($secondaryVendorsSource, 'id="vms-secondary-vendor-group-template"') !== false, 'Secondary Vendors group template should remain present.');
	$assert(strpos($secondaryVendorsSource, 'id="vms-secondary-vendor-row-template"') !== false, 'Secondary Vendors row template should remain present.');
	$assert(strpos($secondaryVendorsSource, 'data-vms-secondary-row-indicators') !== false, 'Secondary Vendors row indicator markup should remain present.');

	$assert(strpos($eventPlansSource, 'window.vmsEventPlanInitSecondaryVendors = initSecondaryVendors;') === false, 'Event Plan source should no longer expose the live secondary-vendor initializer.');
	$assert(strpos($eventPlansSource, "section.dataset.vmsSecondaryInitBound === '1'") === false, 'Event Plan source should no longer retain the Secondary Vendors duplicate-init guard.');
	$assert(strpos($eventPlansSource, "section.dataset.vmsSecondaryInitBound = '1';") === false, 'Event Plan source should no longer mark the Secondary Vendors section as initialized.');
	$assert(strpos($secondaryVendorAssetSource, 'window.vmsEventPlanInitSecondaryVendors = initSecondaryVendors;') !== false, 'Dedicated Secondary Vendors asset should now expose the public compatibility initializer.');
	$assert(strpos($secondaryVendorAssetSource, "section.dataset.vmsSecondaryInitBound === '1'") !== false, 'Dedicated Secondary Vendors asset should retain duplicate-init protection.');
	$assert(strpos($secondaryVendorAssetSource, "section.dataset.vmsSecondaryInitBound = '1';") !== false, 'Dedicated Secondary Vendors asset should still mark the section as initialized.');
	$assert(strpos($secondaryVendorAssetSource, "document.addEventListener('DOMContentLoaded', function() {\n            initSecondaryVendors(document);") !== false || strpos($secondaryVendorAssetSource, "document.addEventListener('DOMContentLoaded', function() {\r\n            initSecondaryVendors(document);") !== false, 'Dedicated Secondary Vendors asset should still initialize on DOM ready.');
	$assert(strpos($secondaryVendorAssetSource, "    } else {\n        initSecondaryVendors(document);") !== false || strpos($secondaryVendorAssetSource, "    } else {\r\n        initSecondaryVendors(document);") !== false, 'Dedicated Secondary Vendors asset should still initialize after immediate render.');
	$assert(strpos($shellAssetSource, 'body.innerHTML = payload.data.html;') !== false, 'Shell lazy-load success path should still inject the rendered Secondary Vendors markup.');
	$assert(strpos($shellAssetSource, 'window.vmsEventPlanInitSecondaryVendors(body);') !== false, 'Shell lazy-load success path should still reinitialize Secondary Vendors after injecting markup.');
	$assert(strpos($eventPlansSource, 'window.vmsEventPlanInitSecondaryVendors(body);') === false, 'Event Plan PHP should no longer reinitialize Secondary Vendors after save-response markup replacement.');
	$assert(strpos($secondaryVendorAssetSource, 'window.vmsEventPlanInitSecondaryVendors(body);') !== false, 'Dedicated Secondary Vendors asset should still reinitialize itself after save-response markup replacement.');
	$assert(strpos($adminUiAssetsSource, "'vms-event-plan-secondary-vendors'") !== false, 'Admin UI assets should register the dedicated Secondary Vendors handle.');
	$assert(strpos($adminUiAssetsSource, "VMS_PLUGIN_URL . 'assets/js/vms-event-plan-secondary-vendors.js'") !== false, 'Admin UI assets should point the Secondary Vendors handle at the dedicated asset.');

	$bridgeHits = array();
	$runtimeIterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($pluginRoot . '/includes', FilesystemIterator::SKIP_DOTS)
	);
	foreach ($runtimeIterator as $fileInfo) {
		if (!$fileInfo instanceof SplFileInfo || !$fileInfo->isFile() || $fileInfo->getExtension() !== 'php') {
			continue;
		}

		$path = $fileInfo->getPathname();
		$contents = file_get_contents($path);
		if (!is_string($contents) || strpos($contents, 'window.vmsEventPlanInitSecondaryVendors(document)') === false) {
			continue;
		}

		$bridgeHits[] = substr($path, strlen($pluginRoot) + 1);
	}
	$assert($bridgeHits === array(), 'No first-party runtime PHP source should reintroduce the removed document-level Secondary Vendors bridge. Found: ' . implode(', ', $bridgeHits));

	$assetInitializerHits = array();
	$assetIterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($pluginRoot . '/assets', FilesystemIterator::SKIP_DOTS)
	);
	foreach ($assetIterator as $fileInfo) {
		if (!$fileInfo instanceof SplFileInfo || !$fileInfo->isFile() || $fileInfo->getExtension() !== 'js') {
			continue;
		}

		$assetPath = $fileInfo->getPathname();
		$contents = file_get_contents($assetPath);
		if (!is_string($contents)) {
			continue;
		}
		$definesInitializer = preg_match('/window\.vmsEventPlanInitSecondaryVendors\s*=(?!=)/', $contents) === 1
			|| preg_match('/function\s+initSecondaryVendors\s*\(/', $contents) === 1
			|| preg_match('/\b(?:const|let|var)\s+initSecondaryVendors\s*=/', $contents) === 1;
		if (!$definesInitializer) {
			continue;
		}

		$assetInitializerHits[] = substr($assetPath, strlen($pluginRoot) + 1);
	}
	$assert($assetInitializerHits === array('assets/js/vms-event-plan-secondary-vendors.js'), 'Only the dedicated Secondary Vendors asset should own the live initializer. Found: ' . implode(', ', $assetInitializerHits));
	$assert(file_exists($secondaryVendorAssetPath), 'This remediation slice should create a dedicated Secondary Vendors asset.');

	fwrite(STDOUT, "event plan secondary vendor bootstrap remediation: PASS\n");
} catch (Throwable $e) {
	fwrite(STDERR, 'event plan secondary vendor bootstrap remediation: FAIL - ' . $e->getMessage() . "\n");
	exit(1);
}
