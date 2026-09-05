<?php
declare(strict_types=1);

$pluginRoot = dirname(__DIR__);
$eventPlansPath = $pluginRoot . '/includes/cpt/event-plans.php';
$secondaryVendorsPath = $pluginRoot . '/includes/cpt/event-plans/partials/secondary-vendors.php';
$adminUiAssetsPath = $pluginRoot . '/includes/admin-ui/assets.php';
$secondaryVendorAssetPath = $pluginRoot . '/assets/js/vms-event-plan-secondary-vendors.js';
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

	foreach (array(
		'window.vmsEventPlanInitSecondaryVendors = initSecondaryVendors;',
		"section.dataset.vmsSecondaryInitBound === '1'",
		"section.dataset.vmsSecondaryInitBound = '1';",
		"group.dataset.vmsSecondaryGroupBound === '1'",
		"row.dataset.vmsSecondaryRowBound === '1'",
		"const configNode = section.querySelector('[data-vms-secondary-config]');",
		"params.set('action', 'vms_save_event_plan_secondary_vendors');",
		"window.vmsEventPlanPersistRequestedSection('secondary_vendors');",
		'window.vmsEventPlanInitSecondaryVendors(body);',
	) as $removedInlineMarker) {
		$assert(
			strpos($eventPlansSource, $removedInlineMarker) === false,
			'Event Plan PHP should no longer own the migrated Secondary Vendors runtime marker: ' . $removedInlineMarker
		);
	}

	foreach (array(
		'window.vmsEventPlanInitSecondaryVendors = initSecondaryVendors;',
		"section.dataset.vmsSecondaryInitBound === '1'",
		"section.dataset.vmsSecondaryInitBound = '1';",
		"group.dataset.vmsSecondaryGroupBound === '1'",
		"row.dataset.vmsSecondaryRowBound === '1'",
		'#vms-secondary-vendors-section',
		'#vms-secondary-vendor-groups',
		'#vms-secondary-vendor-add-group',
		'#vms-secondary-vendor-clear',
		'#vms-secondary-vendor-save',
		'#vms-secondary-vendor-group-template',
		'#vms-secondary-vendor-row-template',
		'[data-vms-secondary-config]',
		'[data-vms-secondary-save-status]',
		'section.dataset.vmsSaveUrl',
		'section.dataset.vmsSaveNonce',
		'section.dataset.vmsSavePostId',
		'vendorTypeOptionsForGroup',
		'nextAvailableType',
		'updateGroupCapacityOverride',
		'vms-secondary-vendor-group-over-capacity-override',
		'vms-secondary-vendor-group-needed-slots',
		'vms-secondary-vendor-group-open-for-dispatch',
		'vms-secondary-vendor-add-new-link',
		'data-vms-secondary-row-indicators',
		"params.set('action', 'vms_save_event_plan_secondary_vendors');",
		"params.set('post_id', String(postId));",
		"params.set('nonce', saveNonce);",
		"params.set('vms_clear_secondary_vendors', clearInput ? String(clearInput.value || '0') : '0');",
		'vms_secondary_vendor_assignments[${groupIndex}][type_slug]',
		'vms_secondary_vendor_assignments[${groupIndex}][mode]',
		'vms_secondary_vendor_assignments[${groupIndex}][slot_limit]',
		'vms_secondary_vendor_assignments[${groupIndex}][allow_over_capacity]',
		'vms_secondary_vendor_assignments[${groupIndex}][needed_slots]',
		'vms_secondary_vendor_assignments[${groupIndex}][open_for_dispatch]',
		'vms_secondary_vendor_assignments[${groupIndex}][vendor_ids][]',
		'application/x-www-form-urlencoded; charset=UTF-8',
		"window.vmsEventPlanPersistRequestedSection('secondary_vendors');",
		'window.vmsEventPlanInitCollapsibleSection(collapsibleSection);',
		'window.vmsEventPlanInitSecondaryVendors(body);',
		'secondary_vendor_save_failed',
		'secondary_vendor_render_target_missing',
	) as $requiredAssetMarker) {
		$assert(strpos($secondaryVendorAssetSource, $requiredAssetMarker) !== false, 'Secondary Vendors asset should own the migrated runtime marker: ' . $requiredAssetMarker);
	}

	$assert(strpos($secondaryVendorAssetSource, 'window.VMS_EVENT_PLAN_SECONDARY_VENDORS') === false, 'Secondary Vendors asset should not introduce a global configuration object.');
	$assert(
		preg_match('/<script\b(?![^>]*type=(["\'])application\/json\1)[^>]*>/i', $secondaryVendorsSource) !== 1,
		'Secondary Vendors partial should not contain an executable <script> block.'
	);
	$assert(substr_count($secondaryVendorsSource, '<script') === 1, 'Secondary Vendors partial should retain only the inert JSON script tag.');
	$assert(strpos($secondaryVendorsSource, '<script type="application/json" data-vms-secondary-config>') !== false, 'Secondary Vendors partial should retain the inert application/json configuration payload.');
	$assert(strpos($secondaryVendorsSource, 'data-vms-save-url=') !== false, 'Secondary Vendors partial should retain the save URL contract.');
	$assert(strpos($secondaryVendorsSource, 'data-vms-save-nonce=') !== false, 'Secondary Vendors partial should retain the save nonce contract.');
	$assert(strpos($secondaryVendorsSource, 'data-vms-save-post-id=') !== false, 'Secondary Vendors partial should retain the save post ID contract.');
	$assert(strpos($secondaryVendorsSource, 'name="vms_secondary_vendors_module_detached"') !== false, 'Secondary Vendors partial should retain the detached-module marker.');
	$assert(strpos($secondaryVendorsSource, 'name="vms_clear_secondary_vendors"') !== false, 'Secondary Vendors partial should retain the clear-intent field.');
	$assert(strpos($secondaryVendorsSource, 'id="vms-secondary-vendor-group-template"') !== false, 'Secondary Vendors partial should retain the group template.');
	$assert(strpos($secondaryVendorsSource, 'id="vms-secondary-vendor-row-template"') !== false, 'Secondary Vendors partial should retain the row template.');
	$assert(strpos($shellAssetSource, 'window.vmsEventPlanInitSecondaryVendors(body);') !== false, 'Shell asset should still call the Secondary Vendors compatibility initializer after lazy loading.');
	$assert(strpos($adminUiAssetsSource, "'vms-event-plan-secondary-vendors'") !== false, 'Admin UI assets should register the Secondary Vendors handle.');
	$assert(strpos($adminUiAssetsSource, "BVMGR_PLUGIN_URL . 'assets/js/vms-event-plan-secondary-vendors.js'") !== false, 'Admin UI assets should point the Secondary Vendors handle at the new asset.');
	$assert(strpos($adminUiAssetsSource, "in_array((string) \$screen->base, array('post', 'post-new'), true)") !== false, 'Secondary Vendors asset should remain restricted to post and post-new screens.');
	$assert(strpos($adminUiAssetsSource, "(string) (\$screen->post_type ?? '') === 'vms_event_plan'") !== false, 'Secondary Vendors asset should remain restricted to Event Plan edit/new screens.');
	$assert(strpos($eventPlansSource, 'const hiddenConfirm = document.getElementById(\'vms_cancel_bulk_retry_confirm\');') === false, 'Bulk-cancellation retry confirmation should no longer remain inline.');
	$assert(strpos($eventPlansSource, 'const btn = document.getElementById(\'vms_run_live_refunds_now_button\');') === false, 'Live-refunds confirmation should no longer remain inline.');
	$assert(
		preg_match('/<script\b(?![^>]*type=(["\'])application\/json\1)[^>]*>/i', $eventPlansSource) !== 1,
		'Event Plan PHP should not contain executable inline Event Plan script blocks after the workflow migration.'
	);
	$assert(substr_count($eventPlansSource, '<script') === 1, 'Event Plan PHP should retain only the inert Secondary Vendors application/json script tag after the workflow migration.');
	$assert(strpos($eventPlansSource, '<script type="application/json" data-vms-secondary-config>') !== false, 'Event Plan PHP should retain the inert Secondary Vendors application/json configuration payload.');

	$assetOwnershipHits = array();
	$assetIterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($pluginRoot . '/assets', FilesystemIterator::SKIP_DOTS)
	);
	foreach ($assetIterator as $fileInfo) {
		if (!$fileInfo instanceof SplFileInfo || !$fileInfo->isFile() || $fileInfo->getExtension() !== 'js') {
			continue;
		}

		$assetPath = $fileInfo->getPathname();
		$contents = file_get_contents($assetPath);
		if (!is_string($contents) || strpos($contents, 'window.vmsEventPlanInitSecondaryVendors = initSecondaryVendors;') === false) {
			continue;
		}

		$assetOwnershipHits[] = substr($assetPath, strlen($pluginRoot) + 1);
	}
	sort($assetOwnershipHits);
	$assert($assetOwnershipHits === array('assets/js/vms-event-plan-secondary-vendors.js'), 'Only the dedicated Secondary Vendors asset should own the public initializer. Found: ' . implode(', ', $assetOwnershipHits));

	fwrite(STDOUT, "event plan secondary vendor inline js remediation: PASS\n");
} catch (Throwable $e) {
	fwrite(STDERR, 'event plan secondary vendor inline js remediation: FAIL - ' . $e->getMessage() . "\n");
	exit(1);
}
