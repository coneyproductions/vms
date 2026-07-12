<?php
declare(strict_types=1);

$pluginRoot = dirname(__DIR__);
$vendorPortalSource = file_get_contents($pluginRoot . '/includes/portal/vendor-portal.php');
$publicCalendarSource = file_get_contents($pluginRoot . '/assets/js/vms-public-calendar.js');
$modalAssetSource = file_get_contents($pluginRoot . '/assets/js/vms-portal-calendar-modal.js');
$assert = static function (bool $condition, string $message): void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
};
$collectRuntimePhpFiles = static function (string $pluginRoot): array {
	$files = array($pluginRoot . '/vendor-management-system.php', $pluginRoot . '/vms.php');
	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pluginRoot . '/includes', FilesystemIterator::SKIP_DOTS));
	foreach ($iterator as $fileInfo) {
		if ($fileInfo instanceof SplFileInfo && $fileInfo->isFile() && strtolower($fileInfo->getExtension()) === 'php') {
			$files[] = $fileInfo->getPathname();
		}
	}
	sort($files);
	return array_values(array_unique(array_filter($files, 'is_file')));
};
$findMatches = static function (array $files, string $pattern): array {
	$matches = array();
	foreach ($files as $file) {
		$source = file_get_contents($file);
		if (is_string($source) && $source !== '' && preg_match($pattern, $source)) {
			$matches[] = $file;
		}
	}
	return $matches;
};
$assert(is_string($vendorPortalSource) && $vendorPortalSource !== '', 'Vendor Portal source should be readable.');
$assert(is_string($publicCalendarSource) && $publicCalendarSource !== '', 'Public calendar asset should be readable.');
$assert(is_string($modalAssetSource) && $modalAssetSource !== '', 'Unused modal asset duplicate should remain present.');
$assert(strpos($vendorPortalSource, 'window.__vmsPortalModalInlineLoaded') === false, 'Vendor Portal source should no longer contain the dead modal inline-load guard.');
$assert(strpos($vendorPortalSource, 'window.VMSPortalCalendarModalOpen = function(trigger)') === false, 'Vendor Portal source should no longer assign the dead modal global opener.');
$assert(strpos($vendorPortalSource, 'data-vms-modal-title') === false, 'Vendor Portal source should no longer contain the dead data-vms-modal-* controller logic.');
$assert(strpos($vendorPortalSource, 'vms-av-event-trigger') === false, 'Vendor Portal source should no longer contain the dead modal trigger selector.');
$assert(strpos($vendorPortalSource, 'vms-portal-calendar-modal') === false, 'Vendor Portal source should no longer contain the dead modal DOM id or class contract.');
$runtimePhpFiles = $collectRuntimePhpFiles($pluginRoot);
$forbiddenRuntimePatterns = array('~\bvms-av-event-trigger\b~', '~data-vms-modal-[a-z0-9_-]+~i', '~\bVMSPortalCalendarModalOpen\b~', '~__vmsPortalModalInlineLoaded~', '~vms-portal-calendar-modal(?:\.js)?~');
foreach ($forbiddenRuntimePatterns as $pattern) {
	$matches = $findMatches($runtimePhpFiles, $pattern);
	$assert($matches === array(), 'First-party runtime PHP should not retain the dead modal contract. Pattern ' . $pattern . ' matched: ' . implode(', ', $matches));
}
$activeVendorPortalMarkers = array('vms-public-cal', 'vms-cal-entry', 'vms-cal-pop', "'vms-public-calendar'", 'assets/js/vms-public-calendar.js');
foreach ($activeVendorPortalMarkers as $marker) {
	$assert(strpos($vendorPortalSource, $marker) !== false, 'Vendor Portal source should preserve the active public-calendar path marker: ' . $marker);
}
$remainingInlineMarkers = array('function vmsSetNarrow()', 'function vmsPortalStripOpportunityTabs()', 'window.VMS_AV = window.VMS_AV || {};', 'var methods = document.querySelectorAll("details.vms-av-method");', 'var cookieName = "vms_av_open_ym";', 'document.querySelector(".vms-av-allvendors-wrap")');
foreach ($remainingInlineMarkers as $marker) {
	$assert(strpos($vendorPortalSource, $marker) !== false, 'Vendor Portal source should retain the unrelated inline script marker for this slice: ' . $marker);
}
$assert(strpos($publicCalendarSource, "const ROOT_SELECTOR = '.vms-public-cal';") !== false, 'Public calendar asset should still target the active Vendor Portal calendar root.');
$assert(strpos($publicCalendarSource, "const ENTRY_SELECTOR = '.vms-cal-entry';") !== false, 'Public calendar asset should still target the active Vendor Portal entry selector.');
$assert(strpos($publicCalendarSource, "const POP_SELECTOR = '.vms-cal-pop';") !== false, 'Public calendar asset should still target the active Vendor Portal popover selector.');
$assert(strpos($publicCalendarSource, 'function openEntry(entry)') !== false, 'Public calendar asset should still contain the active popover opener.');
$assert(strpos($publicCalendarSource, 'function placePopover(entry)') !== false, 'Public calendar asset should still contain the active popover placement logic.');
$assert(strpos($modalAssetSource, 'window.VMSPortalCalendarModalOpen = function(trigger)') !== false, 'Unused modal duplicate asset should remain unchanged in this slice.');
$assert(strpos($modalAssetSource, 'data-vms-modal-title') !== false, 'Unused modal duplicate asset should still contain the historical modal data-attribute contract.');
$assert(strpos($modalAssetSource, "e.target.closest('.vms-av-event-trigger')") !== false, 'Unused modal duplicate asset should still contain the historical trigger selector.');
fwrite(STDOUT, "Vendor Portal modal inline JS remediation OK.\n");
