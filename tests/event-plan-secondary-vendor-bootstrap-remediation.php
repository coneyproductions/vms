<?php
declare(strict_types=1);

$pluginRoot = dirname(__DIR__);
$eventPlansPath = $pluginRoot . '/includes/cpt/event-plans.php';
$secondaryVendorsPath = $pluginRoot . '/includes/cpt/event-plans/partials/secondary-vendors.php';
$secondaryVendorAssetPath = $pluginRoot . '/assets/js/vms-secondary-vendors.js';

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

	$assert(strpos($eventPlansSource, 'window.vmsEventPlanInitSecondaryVendors = initSecondaryVendors;') !== false, 'Event Plan source should still expose the live secondary-vendor initializer.');
	$assert(strpos($eventPlansSource, "section.dataset.vmsSecondaryInitBound === '1'") !== false, 'Secondary Vendors initializer should retain duplicate-init protection.');
	$assert(strpos($eventPlansSource, "section.dataset.vmsSecondaryInitBound = '1';") !== false, 'Secondary Vendors initializer should still mark the section as initialized.');
	$assert(strpos($eventPlansSource, "document.addEventListener('DOMContentLoaded', function() {\n\t                    initSecondaryVendors(document);") !== false || strpos($eventPlansSource, "document.addEventListener('DOMContentLoaded', function() {\r\n\t                    initSecondaryVendors(document);") !== false, 'Full-page Event Plan boot should still initialize Secondary Vendors on DOM ready.');
	$assert(strpos($eventPlansSource, "            } else {\n\t                initSecondaryVendors(document);") !== false || strpos($eventPlansSource, "            } else {\r\n\t                initSecondaryVendors(document);") !== false, 'Full-page Event Plan boot should still initialize Secondary Vendors after immediate render.');
	$assert(strpos($eventPlansSource, 'body.innerHTML = payload.data.html;') !== false, 'Lazy-section success path should still inject the rendered Secondary Vendors markup.');
	$assert(substr_count($eventPlansSource, 'window.vmsEventPlanInitSecondaryVendors(body);') >= 2, 'Secondary Vendors initializer should still run after lazy-load and save-response markup replacement.');

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
		if (!is_string($contents) || strpos($contents, 'vmsEventPlanInitSecondaryVendors') === false) {
			continue;
		}

		$assetInitializerHits[] = substr($assetPath, strlen($pluginRoot) + 1);
	}
	$assert($assetInitializerHits === array(), 'No JavaScript asset should own or duplicate the live Secondary Vendors initializer. Found: ' . implode(', ', $assetInitializerHits));
	$assert(!file_exists($secondaryVendorAssetPath), 'This remediation slice should not create a dedicated Secondary Vendors asset.');

	fwrite(STDOUT, "event plan secondary vendor bootstrap remediation: PASS\n");
} catch (Throwable $e) {
	fwrite(STDERR, 'event plan secondary vendor bootstrap remediation: FAIL - ' . $e->getMessage() . "\n");
	exit(1);
}
