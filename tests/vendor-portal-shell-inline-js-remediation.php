<?php
declare(strict_types=1);

$pluginRoot = dirname(__DIR__);
$vendorPortalSource = file_get_contents($pluginRoot . '/includes/portal/vendor-portal.php');
$shellAssetSource = file_get_contents($pluginRoot . '/assets/js/vms-vendor-portal.js');
$publicCalendarSource = file_get_contents($pluginRoot . '/assets/js/vms-public-calendar.js');

$assert = static function (bool $condition, string $message): void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
};

$assert(is_string($vendorPortalSource) && $vendorPortalSource !== '', 'Vendor Portal source should be readable.');
$assert(is_string($shellAssetSource) && $shellAssetSource !== '', 'Vendor Portal shell asset should be readable.');
$assert(is_string($publicCalendarSource) && $publicCalendarSource !== '', 'Public calendar asset should be readable.');

$assert(!preg_match('~echo \'<script>\(function\(\)\{function vmsSetNarrow~', $vendorPortalSource), 'Vendor Portal source should no longer emit the inline narrow-layout script.');
$assert(!preg_match('~echo \'<script>\(function\(\)\{function vmsPortalStripOpportunityTabs~', $vendorPortalSource), 'Vendor Portal source should no longer emit the inline stale-nav cleanup script.');
$assert(!preg_match('~echo \'<script>\s*document\.addEventListener\("DOMContentLoaded", function \(\) \{\s*var wrap = document\.querySelector\("\.vms-av-allvendors-wrap"\);~s', $vendorPortalSource), 'Vendor Portal source should no longer emit the inline All Vendors accordion script.');
$assert(strpos($vendorPortalSource, 'onchange="this.form.submit()"') === false, 'Vendor Portal source should no longer contain inline submit-on-change attributes.');
$assert(substr_count($vendorPortalSource, 'data-vms-portal-submit-on-change="1"') === 3, 'Vendor Portal source should mark exactly three select controls for external submit-on-change handling.');
$assert(strpos($vendorPortalSource, "wp_enqueue_script('vms-vendor-portal'") !== false, 'Vendor Portal render path should enqueue the new shell asset.');
$assert(strpos($vendorPortalSource, 'assets/js/vms-vendor-portal.js') !== false, 'Vendor Portal render path should point at the new shell asset file.');

$requiredAssetMarkers = array(
	"document.getElementById('vms-portal-root')",
	"'vms-portal--narrow'",
	"'tab=opportunities'",
	"select[data-vms-portal-submit-on-change=\"1\"]",
	"'.vms-av-allvendors-wrap'",
	"'orientationchange'",
);
foreach ($requiredAssetMarkers as $marker) {
	$assert(strpos($shellAssetSource, $marker) !== false, 'Vendor Portal shell asset should contain marker: ' . $marker);
}

$forbiddenAssetMarkers = array('window.VMS_AV', 'vms_save_manual_availability_day', 'beforeunload', 'vms_av_open_method', 'vms_av_open_ym');
foreach ($forbiddenAssetMarkers as $marker) {
	$assert(strpos($shellAssetSource, $marker) === false, 'Vendor Portal shell asset should not contain later-slice behavior marker: ' . $marker);
}

$assert(strpos($shellAssetSource, 'if (!root) return;') !== false, 'Vendor Portal shell asset should no-op safely when the portal root is absent.');
$assert(strpos($shellAssetSource, 'if (!nav) return;') !== false, 'Vendor Portal shell asset should no-op safely when the portal nav is absent.');
$assert(strpos($shellAssetSource, 'if (!wrap || wrap.dataset.vmsPortalAccordionBound === \'1\') return;') !== false, 'Vendor Portal shell asset should no-op safely when the All Vendors wrapper is absent.');
$assert(strpos($shellAssetSource, 'if (!all.length) return;') !== false, 'Vendor Portal shell asset should no-op safely when no month accordions are present.');

$obsoletePhpMarkers = array('function vmsSetNarrow()', 'function vmsPortalStripOpportunityTabs()', 'window.VMS_AV = window.VMS_AV || {};', 'var methods = document.querySelectorAll("details.vms-av-method");', 'var cookieName = "vms_av_open_ym";', 'document.querySelector(".vms-av-allvendors-wrap")', 'window.addEventListener("beforeunload"', 'action: "vms_save_manual_availability_day"');
foreach ($obsoletePhpMarkers as $marker) {
	$assert(strpos($vendorPortalSource, $marker) === false, 'Vendor Portal source should not retain obsolete migration-marker text: ' . $marker);
}
$assert(strpos($vendorPortalSource, 'data-vms-portal-config="availability"') !== false, 'Vendor Portal source should preserve the scoped availability JSON payload.');
preg_match_all('~<script\b([^>]*)>(.*?)</script>~is', (string) $vendorPortalSource, $scriptMatches, PREG_SET_ORDER);
$assert($scriptMatches !== array(), 'Vendor Portal source should still contain the scoped JSON config payload.');
foreach ($scriptMatches as $scriptMatch) {
	$attrs = (string) ($scriptMatch[1] ?? '');
	$type = '';
	if (preg_match('~type=["\']([^"\']+)["\']~i', $attrs, $typeMatch)) {
		$type = strtolower(trim((string) ($typeMatch[1] ?? '')));
	}
	$assert($type === 'application/json', 'Vendor Portal source should not emit executable inline script tags.');
}

$activePathMarkers = array("'vms-public-calendar'", 'assets/js/vms-public-calendar.js', 'vms-public-cal', 'vms-cal-entry', 'vms-cal-pop');
foreach ($activePathMarkers as $marker) {
	$assert(strpos($vendorPortalSource, $marker) !== false || strpos($publicCalendarSource, $marker) !== false, 'Active public-calendar path marker should remain intact: ' . $marker);
}

fwrite(STDOUT, "Vendor Portal shell inline JS remediation OK.\n");
