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
$assert(is_string($shellAssetSource) && $shellAssetSource !== '', 'Vendor Portal asset should be readable.');
$assert(is_string($publicCalendarSource) && $publicCalendarSource !== '', 'Public calendar asset should be readable.');

$assert(!preg_match('~<script>\s*\(function\(\)\s*\{\s*var methods = document\.querySelectorAll\("details\.vms-av-method"\);~s', $vendorPortalSource), 'Vendor Portal source should no longer emit the inline availability-method open-state script.');
$assert(!preg_match('~<script>\s*\(function\(\)\s*\{\s*var root = document\.getElementById\("vms-av"\);\s*if \(!root\) return;\s*var cookieName = "vms_av_open_ym";~s', $vendorPortalSource), 'Vendor Portal source should no longer emit the inline availability-month open-state script.');

$requiredAssetMarkers = array(
	"['vms', 'av', 'open', 'method'].join('_')",
	"['vms', 'av', 'open', 'ym'].join('_')",
	'window.localStorage',
	'details.vms-av-method[data-method]',
	'details.vms-av-method[data-method="manual"]',
	'.vms-av-month[data-ym]',
	'data-today-ym',
	'document.cookie',
	'scrollIntoView',
);
foreach ($requiredAssetMarkers as $marker) {
	$assert(strpos($shellAssetSource, $marker) !== false, 'Vendor Portal asset should contain open-state marker: ' . $marker);
}

$shellBehaviorMarkers = array(
	"document.getElementById('vms-portal-root')",
	"'vms-portal--narrow'",
	"'tab=opportunities'",
	"select[data-vms-portal-submit-on-change=\"1\"]",
	"'.vms-av-allvendors-wrap'",
);
foreach ($shellBehaviorMarkers as $marker) {
	$assert(strpos($shellAssetSource, $marker) !== false, 'Vendor Portal asset should still contain prior shell behavior marker: ' . $marker);
}

$forbiddenAssetMarkers = array('window.VMS_AV', 'ajaxUrl', 'previewNonce', 'previewVendor', 'nonce', 'vms_save_manual_availability_day', 'fetch(', 'beforeunload', 'updateMonthCounts');
foreach ($forbiddenAssetMarkers as $marker) {
	$assert(strpos($shellAssetSource, $marker) === false, 'Vendor Portal asset should not contain excluded autosave/AJAX marker: ' . $marker);
}

$remainingPhpMarkers = array(
	'window.VMS_AV = window.VMS_AV || {};',
	'var cfg = window.VMS_AV || {};',
	'action: "vms_save_manual_availability_day"',
	'window.addEventListener("beforeunload"',
);
foreach ($remainingPhpMarkers as $marker) {
	$assert(strpos($vendorPortalSource, $marker) !== false, 'Vendor Portal PHP should retain the later autosave marker: ' . $marker);
}

$markupMarkers = array('data-today-ym="', 'data-method="ics"', 'data-method="pattern"', 'data-method="manual"', 'data-ym="');
foreach ($markupMarkers as $marker) {
	$assert(strpos($vendorPortalSource, $marker) !== false, 'Vendor Portal markup should preserve availability state attribute marker: ' . $marker);
}

$assert(strpos($shellAssetSource, "catch (err) {\n      return '';\n    }") !== false, 'Vendor Portal asset should fail safely when storage or cookie reads are blocked.');
$assert(strpos($shellAssetSource, "catch (err) {}") !== false, 'Vendor Portal asset should fail safely when storage or cookie writes are blocked.');
$assert(strpos($shellAssetSource, "if (!availabilityRoot || availabilityRoot.dataset.vmsPortalMethodBound === '1') return;") !== false, 'Vendor Portal asset should no-op safely when the availability method section is absent or already bound.');
$assert(strpos($shellAssetSource, "if (!availabilityRoot || availabilityRoot.dataset.vmsPortalMonthBound === '1') return;") !== false, 'Vendor Portal asset should no-op safely when the month accordion is absent or already bound.');
$assert(strpos($shellAssetSource, "if (!target) target = availabilityRoot.querySelector('details.vms-av-method[data-method=\"manual\"]');") !== false, 'Vendor Portal asset should preserve the manual-method fallback.');
$assert(strpos($shellAssetSource, "openYm = preferredYm && byYm.has(preferredYm) ? preferredYm : todayYm && byYm.has(todayYm) ? todayYm : firstYm();") !== false, 'Vendor Portal asset should preserve the stored-month then today-month fallback order.');

$assert(strpos($vendorPortalSource, 'onchange="this.form.submit()"') === false, 'Vendor Portal source should still avoid inline submit handlers.');
$activePathMarkers = array("'vms-public-calendar'", 'assets/js/vms-public-calendar.js', 'vms-public-cal', 'vms-cal-entry', 'vms-cal-pop');
foreach ($activePathMarkers as $marker) {
	$assert(strpos($vendorPortalSource, $marker) !== false || strpos($publicCalendarSource, $marker) !== false, 'Active public-calendar path marker should remain intact: ' . $marker);
}

fwrite(STDOUT, "Vendor Portal availability open-state remediation OK.\n");
