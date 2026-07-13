<?php
declare(strict_types=1);

$pluginRoot = dirname(__DIR__);
$eventPlansPath = $pluginRoot . '/includes/cpt/event-plans.php';
$timeLineupPath = $pluginRoot . '/includes/cpt/event-plans/partials/time-lineup.php';
$adminUiAssetsPath = $pluginRoot . '/includes/admin-ui/assets.php';
$primaryVendorAssetPath = $pluginRoot . '/assets/js/vms-event-plan-primary-vendor.js';
$workflowAssetPath = $pluginRoot . '/assets/js/vms-event-plan-workflow.js';
$titleAssetPath = $pluginRoot . '/assets/js/vms-event-plan-title.js';

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
	$timeLineupSource = $readFile($timeLineupPath);
	$adminUiAssetsSource = $readFile($adminUiAssetsPath);
	$primaryVendorAssetSource = $readFile($primaryVendorAssetPath);
	$workflowAssetSource = $readFile($workflowAssetPath);
	$titleAssetSource = $readFile($titleAssetPath);

	foreach (array(
		'const bypassSetBtn = document.getElementById(\'vms-tax-bypass-set\');',
		'const bypassClearBtn = document.getElementById(\'vms-tax-bypass-clear\');',
		'function updateSelectedOptionBypass(active, until, reason) {',
		'await postBypass(\'vms_tax_bypass_set\', {',
		'await postBypass(\'vms_tax_bypass_clear\', {',
		'Applying bypass…',
		'Clearing bypass…',
	) as $removedInlineMarker) {
		$assert(
			strpos($eventPlansSource, $removedInlineMarker) === false,
			'Event Plan PHP should no longer own the inline primary-vendor tax controller marker: ' . $removedInlineMarker
		);
	}

	foreach (array(
		'data-tax-ok=',
		'data-tax-bypass-active=',
		'data-tax-bypass-until=',
		'data-tax-bypass-reason=',
		'data-tax-missing=',
	) as $requiredOptionContract) {
		$assert(strpos($eventPlansSource, $requiredOptionContract) !== false, 'Event Plan PHP should retain the primary-vendor option contract: ' . $requiredOptionContract);
	}

	foreach (array(
		'id="vms-tax-status"',
		'id="vms-tax-bypass-inline"',
		'data-nonce="<?php echo esc_attr(wp_create_nonce(\'vms_tax_bypass_ajax\')); ?>"',
		'data-default-until="<?php echo esc_attr($tax_bypass_default_until); ?>"',
		'id="vms-tax-bypass-active-flag"',
		'id="vms-tax-bypass-until"',
		'id="vms-tax-bypass-reason"',
		'id="vms-tax-bypass-set"',
		'id="vms-tax-bypass-clear"',
		'id="vms-tax-bypass-msg"',
	) as $requiredMarkupMarker) {
		$assert(strpos($timeLineupSource, $requiredMarkupMarker) !== false, 'Time/lineup partial should retain the primary-vendor tax markup contract: ' . $requiredMarkupMarker);
	}

	foreach (array(
		'function initPrimaryVendorTaxController() {',
		'document.getElementById(\'vms_band_vendor_id\')',
		'document.getElementById(\'vms-tax-status\')',
		'document.getElementById(\'vms-tax-bypass-inline\')',
		'wrap.dataset.vmsPrimaryVendorTaxBound === \'1\'',
		'wrap.dataset.vmsPrimaryVendorTaxBound = \'1\';',
		'function findOptionByVendorId(vendorId) {',
		'data-tax-ok',
		'data-tax-bypass-active',
		'data-tax-bypass-until',
		'data-tax-bypass-reason',
		'data-tax-missing',
		'Select a Primary Vendor to see tax requirements.',
		'✅ Tax Profile Complete',
		'🟡 Tax Profile Bypass Active',
		'⚠️ Tax Profile Incomplete',
		'Ready/Publish is allowed while the bypass is active',
		'Needs attention — payments/exports blocked until complete or bypass set. Ready/Publish allowed.',
		'Select a vendor to manage bypass.',
		'Applying bypass…',
		'Clearing bypass…',
		'vms_tax_bypass_set',
		'vms_tax_bypass_clear',
		'form.append(\'nonce\', nonce);',
		'post_id: String(vendorId)',
		'until: until',
		'reason: reason',
		'if (activeRequest) {',
		'activeRequest.id !== requestId',
		'if (selectedVendorId() === vendorId) {',
		'document.addEventListener(\'DOMContentLoaded\', initPrimaryVendorTaxController, { once: true });',
	) as $requiredAssetMarker) {
		$assert(strpos($primaryVendorAssetSource, $requiredAssetMarker) !== false, 'Primary-vendor asset should own the migrated controller marker: ' . $requiredAssetMarker);
	}

	$assert(strpos($primaryVendorAssetSource, 'window.VMS_EVENT_PLAN_PRIMARY_VENDOR') === false, 'Primary-vendor asset should not introduce a global configuration object.');
	$assert(strpos($adminUiAssetsSource, 'wp_add_inline_script(') === false, 'Admin UI asset bootstrap should not reintroduce inline-script controller wiring.');
	$assert(
		preg_match("/wp_enqueue_script\\(\\s*'vms-event-plan-primary-vendor',\\s*VMS_PLUGIN_URL \\. 'assets\\/js\\/vms-event-plan-primary-vendor\\.js',\\s*array\\(\\),\\s*vms_admin_ui_asset_version\\(\\),\\s*true\\s*\\);/s", $adminUiAssetsSource) === 1,
		'Admin UI assets should register the primary-vendor asset under the dedicated handle with no dependencies.'
	);
	$assert(strpos($adminUiAssetsSource, "in_array((string) \$screen->base, array('post', 'post-new'), true)") !== false, 'Primary-vendor asset should remain restricted to post and post-new screens.');
	$assert(strpos($adminUiAssetsSource, "(string) (\$screen->post_type ?? '') === 'vms_event_plan'") !== false, 'Primary-vendor asset should remain restricted to Event Plan edit/new screens.');

	$assert(strpos($titleAssetSource, 'Primary Vendor changed. Update the title to match the selected Primary Vendor?') !== false, 'Title asset should remain the separate owner of title synchronization.');
	$assert(strpos($titleAssetSource, 'vms_tax_bypass_set') === false, 'Title asset should not absorb primary-vendor tax behavior.');

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
		if (!is_string($contents) || strpos($contents, 'vms_tax_bypass_set') === false) {
			continue;
		}

		$assetOwnershipHits[] = substr($assetPath, strlen($pluginRoot) + 1);
	}
	sort($assetOwnershipHits);
	$assert($assetOwnershipHits === array('assets/js/vms-event-plan-primary-vendor.js'), 'Only the dedicated primary-vendor asset should own the tax bypass AJAX controller. Found: ' . implode(', ', $assetOwnershipHits));

	$assert(strpos($eventPlansSource, 'const hiddenConfirm = document.getElementById(\'vms_cancel_bulk_retry_confirm\');') === false, 'Event Plan PHP should no longer retain the bulk-retry workflow controller.');
	$assert(strpos($eventPlansSource, 'const btn = document.getElementById(\'vms_run_live_refunds_now_button\');') === false, 'Event Plan PHP should no longer retain the live-refunds workflow controller.');
	$assert(strpos($workflowAssetSource, 'retry_cancellation_all') !== false, 'Workflow asset should now own the bulk-retry workflow selector.');
	$assert(strpos($workflowAssetSource, 'vms_run_live_refunds_now_button') !== false, 'Workflow asset should now own the live-refunds workflow selector.');
	$assert(strpos($workflowAssetSource, 'mark_cancelled') !== false, 'Workflow asset should now own the mark-cancelled workflow selector.');
	$assert(substr_count($eventPlansSource, '<script') === 0, 'Event Plan PHP should no longer contain executable inline workflow scripts after the final B1 migration slice.');

	fwrite(STDOUT, "event plan primary vendor tax inline js remediation: PASS\n");
} catch (Throwable $e) {
	fwrite(STDERR, 'event plan primary vendor tax inline js remediation: FAIL - ' . $e->getMessage() . "\n");
	exit(1);
}
