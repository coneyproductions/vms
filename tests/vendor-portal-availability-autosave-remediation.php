<?php
declare(strict_types=1);

$pluginRoot = dirname(__DIR__);
$vendorPortalSource = file_get_contents($pluginRoot . '/includes/portal/vendor-portal.php');
$shellAssetSource = file_get_contents($pluginRoot . '/assets/js/vms-vendor-portal.js');
$staffPortalSource = file_get_contents($pluginRoot . '/includes/portal/staff-portal.php');
$staffPortalAssetSource = file_get_contents($pluginRoot . '/assets/js/vms-staff-portal.js');
$publicCalendarSource = file_get_contents($pluginRoot . '/assets/js/vms-public-calendar.js');

$assert = static function (bool $condition, string $message): void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
};

try {
		$assert(is_string($vendorPortalSource) && $vendorPortalSource !== '', 'Vendor Portal source should be readable.');
		$assert(is_string($shellAssetSource) && $shellAssetSource !== '', 'Vendor Portal asset should be readable.');
		$assert(is_string($staffPortalSource) && $staffPortalSource !== '', 'Staff Portal source should be readable.');
		$assert(is_string($staffPortalAssetSource) && $staffPortalAssetSource !== '', 'Staff Portal asset should be readable.');
		$assert(is_string($publicCalendarSource) && $publicCalendarSource !== '', 'Public calendar asset should be readable.');

	$assert(!preg_match('~echo \'<script>\s*window\.VMS_AV~', $vendorPortalSource), 'Vendor Portal source should no longer emit an executable window.VMS_AV config block.');
	$assert(!preg_match('~<script>\s*\(function\(\)\s*\{\s*document\.documentElement\.classList\.add\("vms-js"\);~s', $vendorPortalSource), 'Vendor Portal source should no longer emit the inline manual availability autosave controller.');
	$assert(strpos($vendorPortalSource, 'wp_add_inline_script(') === false, 'Vendor Portal source should not replace the controller with wp_add_inline_script().');
	$assert(strpos($vendorPortalSource, 'wp_localize_script(') === false, 'Vendor Portal source should not replace the controller with wp_localize_script().');
	$assert(strpos($vendorPortalSource, 'type="application/json"') !== false, 'Vendor Portal source should render a non-executable JSON config payload.');
	$assert(strpos($vendorPortalSource, "'ajax' => (string) admin_url('admin-ajax.php')") !== false, 'Vendor Portal source should include the AJAX endpoint in the scoped autosave payload.');
	$assert(strpos($vendorPortalSource, "'token' => (string) \$avail_ajax_nonce") !== false, 'Vendor Portal source should include the availability nonce in the scoped autosave payload.');
	$assert(strpos($vendorPortalSource, "'previewId' => (int) \$preview_vendor_id") !== false, 'Vendor Portal source should include the preview vendor id in the scoped autosave payload.');
	$assert(strpos($vendorPortalSource, "'previewToken' => \$preview_vendor_id > 0") !== false, 'Vendor Portal source should include the preview nonce in the scoped autosave payload.');
	$assert(strpos($vendorPortalSource, "wp_json_encode(\$autosave_config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)") !== false, 'Vendor Portal source should safely JSON-encode the autosave payload.');
	$assert(strpos($vendorPortalSource, "data-vms-portal-config=\"availability\"") !== false, 'Vendor Portal source should scope the autosave payload to the availability UI.');

	$requiredAssetMarkers = array(
		"['vms', 'save', 'manual', 'availability', 'day'].join('_')",
		"['before', 'unload'].join('')",
		'data-vms-portal-config="availability"',
		"JSON.parse(node.textContent || '{}')",
		'Array.isArray(payload)',
		'window.fetch',
		'dirtyDates = new Set()',
		"button.classList.add('vms-av-save-failed')",
		'refreshMonthCounts(button)',
		'iconForSource(source)',
		'ariaForAvailability(visual, source)',
		'if (!config) return;',
		"payload[REQUEST_KEYS.previewId] = config.previewId;",
		"payload[REQUEST_KEYS.previewToken] = config.previewToken;",
	);
	foreach ($requiredAssetMarkers as $marker) {
		$assert(strpos($shellAssetSource, $marker) !== false, 'Vendor Portal asset should contain autosave marker: ' . $marker);
	}

	$assert(strpos($shellAssetSource, 'window.VMS_AV') === false, 'Vendor Portal asset should not depend on a global autosave config object.');
	$assert(strpos($shellAssetSource, 'wp_localize_script') === false, 'Vendor Portal asset should not contain localized-script remnants.');
	$assert(strpos($shellAssetSource, 'wp_add_inline_script') === false, 'Vendor Portal asset should not contain inline-script remnants.');

		$assert(strpos($staffPortalSource, 'window.VMS_STAFF_AV') === false, 'Staff Portal source should not reintroduce the old global availability bootstrap.');
		$assert(strpos($staffPortalSource, 'assets/js/vms-staff-portal.js') !== false, 'Staff Portal source should preserve the external availability asset boundary.');
		$assert(strpos($staffPortalSource, 'data-vms-staff-availability="1"') !== false, 'Staff Portal source should preserve the inert availability form marker.');
		$assert(strpos($staffPortalSource, 'data-vms-staff-availability-ajax-url') !== false, 'Staff Portal source should preserve the inert availability AJAX handoff.');
		$assert(strpos($staffPortalSource, 'data-vms-staff-availability-nonce') !== false, 'Staff Portal source should preserve the inert availability nonce handoff.');
		$assert(strpos($staffPortalAssetSource, "FORM_SELECTOR = '.vms-staff-av-form[data-vms-staff-availability=\"1\"]'") !== false, 'Staff Portal asset should own the availability form selector.');
		$assert(strpos($staffPortalAssetSource, "root.dataset.staffAvailabilityBound === '1'") !== false, 'Staff Portal asset should prevent duplicate availability initialization.');
		$assert(strpos($staffPortalAssetSource, "root.dataset.staffAvailabilityBound = '1';") !== false, 'Staff Portal asset should mark the availability root after binding.');
		$assert(strpos($staffPortalAssetSource, "action: SAVE_ACTION") !== false, 'Staff Portal asset should own the availability save action payload.');
		$assert(strpos($staffPortalSource, 'vms_staff_save_manual_availability_day') !== false, 'Staff Portal autosave handler contract should remain intact.');

	$activePathMarkers = array("'vms-public-calendar'", 'assets/js/vms-public-calendar.js', 'vms-public-cal', 'vms-cal-entry', 'vms-cal-pop');
	foreach ($activePathMarkers as $marker) {
		$assert(strpos($vendorPortalSource, $marker) !== false || strpos($publicCalendarSource, $marker) !== false, 'Active public-calendar path marker should remain intact: ' . $marker);
	}

	fwrite(STDOUT, "Vendor Portal availability autosave remediation OK.\n");
} catch (Throwable $e) {
	fwrite(STDERR, 'Vendor Portal availability autosave remediation FAIL - ' . $e->getMessage() . "\n");
	exit(1);
}
