<?php
declare(strict_types=1);

$pluginRoot = dirname(__DIR__);
$staffPortalSource = file_get_contents($pluginRoot . '/includes/portal/staff-portal.php');
$staffPortalAsset = file_get_contents($pluginRoot . '/assets/js/vms-staff-portal.js');
$staffingAdminSource = file_get_contents($pluginRoot . '/includes/admin/staffing.php');
$staffCptSource = file_get_contents($pluginRoot . '/includes/cpt/staff.php');

$assert = static function (bool $condition, string $message): void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
};

try {
	$assert(is_string($staffPortalSource) && $staffPortalSource !== '', 'Staff Portal source should be readable.');
	$assert(is_string($staffPortalAsset) && $staffPortalAsset !== '', 'Staff Portal asset should be readable.');
	$assert(is_string($staffingAdminSource) && $staffingAdminSource !== '', 'Staffing admin source should remain readable.');
	$assert(is_string($staffCptSource) && $staffCptSource !== '', 'Staff CPT source should remain readable.');

	$assert(strpos($staffPortalSource, "wp_enqueue_script('vms-staff-portal'") !== false, 'Staff Portal shortcode should enqueue the new Staff Portal asset.');
	$assert(strpos($staffPortalSource, 'assets/js/vms-staff-portal.js') !== false, 'Staff Portal shortcode should point at the new Staff Portal asset path.');
	$assert(strpos($staffPortalSource, '$tab === \'availability\'') !== false, 'Staff Portal asset should load only through the availability tab lifecycle.');
	$assert(strpos($staffPortalSource, 'data-vms-staff-availability="1"') !== false, 'Staff Portal manual-availability form should expose the inert availability marker.');
	$assert(strpos($staffPortalSource, 'data-vms-staff-availability-ajax-url="') !== false, 'Staff Portal manual-availability form should expose the inert AJAX URL attribute.');
	$assert(strpos($staffPortalSource, 'data-vms-staff-availability-nonce="') !== false, 'Staff Portal manual-availability form should expose the inert nonce attribute.');
	$assert(strpos($staffPortalSource, 'window.VMS_STAFF_AV') === false, 'Staff Portal source should no longer emit a global executable window.VMS_STAFF_AV config block.');
	$assert(strpos($staffPortalSource, "action: 'vms_staff_save_manual_availability_day'") === false, 'Staff Portal source should no longer emit the inline autosave controller.');
	$assert(strpos($staffPortalSource, 'wp_add_inline_script(') === false, 'Staff Portal source should not replace the controller with wp_add_inline_script().');
	$assert(strpos($staffPortalSource, 'wp_localize_script(') === false, 'Staff Portal source should not replace the controller with wp_localize_script().');
	$assert(strpos($staffPortalSource, 'vms_staff_save_manual_availability_day_ajax') !== false, 'Staff Portal AJAX save handler should remain present.');
	$assert(strpos($staffPortalSource, "check_ajax_referer('vms_staff_avail_ajax', 'nonce');") !== false, 'Staff Portal AJAX nonce verification boundary should remain unchanged.');

	preg_match_all('~<script\b([^>]*)>(.*?)</script>~is', $staffPortalSource, $scriptMatches, PREG_SET_ORDER);
	$assert($scriptMatches === array(), 'Staff Portal source should not emit executable or inert inline script tags after this extraction.');

	$requiredAssetMarkers = array(
		"document.getElementById('vms-portal-root')",
		'.vms-staff-av-form[data-vms-staff-availability="1"]',
		"['vms', 'staff', 'save', 'manual', 'availability', 'day'].join('_')",
		"['before', 'unload'].join('')",
		"root.dataset.staffAvailabilityBound === '1'",
		'root.dataset.staffAvailabilityBound = \'1\'',
		'window.fetch',
		'new URLSearchParams(params).toString()',
		'dirtyDates = new Set()',
		"button.classList.add('vms-av-save-failed')",
		"setStatus(statusEl, 'Saving\\u2026')",
		"counts.textContent = active + ' active | ' + unavailable + ' U | ' + available + ' A | ' + working + ' W';",
		"button.closest('.vms-av-month')",
	);
	foreach ($requiredAssetMarkers as $marker) {
		$assert(strpos($staffPortalAsset, $marker) !== false, 'Staff Portal asset should contain marker: ' . $marker);
	}

	$assert(strpos($staffPortalAsset, 'window.VMS_STAFF_AV') === false, 'Staff Portal asset should not depend on a global availability object.');
	$assert(strpos($staffPortalAsset, 'wp_add_inline_script') === false, 'Staff Portal asset should not contain inline-script remnants.');
	$assert(strpos($staffPortalAsset, 'wp_localize_script') === false, 'Staff Portal asset should not contain localized-script remnants.');
	$assert(strpos($staffPortalAsset, 'if (!form || root.dataset.staffAvailabilityBound === \'1\') return;') !== false, 'Staff Portal asset should no-op safely when the availability form is absent and prevent duplicate initialization.');
	$assert(strpos($staffPortalAsset, 'if (!root) return;') !== false, 'Staff Portal asset should no-op safely when the portal root is absent.');

	$assert(strpos($staffingAdminSource, "VMS_PLUGIN_URL . 'assets/js/vms-staffing-admin.js'") !== false, 'Staffing admin source should remain unchanged by the Staff Portal slice.');
	$assert(strpos($staffingAdminSource, 'data-vms-qualification-builder="1"') !== false, 'Staffing admin qualification-builder markup should remain unchanged by the Staff Portal slice.');
	$assert(strpos($staffCptSource, "VMS_PLUGIN_URL . 'assets/js/vms-staff-cpt-admin.js'") !== false, 'Staff CPT source should remain unchanged by the Staff Portal slice.');

	fwrite(STDOUT, "Staff Portal inline JS remediation OK.\n");
} catch (Throwable $e) {
	fwrite(STDERR, 'Staff Portal inline JS remediation FAIL - ' . $e->getMessage() . "\n");
	exit(1);
}
