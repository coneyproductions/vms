<?php
declare(strict_types=1);

$pluginRoot = dirname(__DIR__);
$eventPlansPath = $pluginRoot . '/includes/cpt/event-plans.php';
$workflowStatusPath = $pluginRoot . '/includes/cpt/event-plans/partials/workflow-status.php';
$secondaryVendorsPath = $pluginRoot . '/includes/cpt/event-plans/partials/secondary-vendors.php';
$adminUiAssetsPath = $pluginRoot . '/includes/admin-ui/assets.php';
$workflowAssetPath = $pluginRoot . '/assets/js/vms-event-plan-workflow.js';
$primaryVendorAssetPath = $pluginRoot . '/assets/js/vms-event-plan-primary-vendor.js';

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
	$workflowStatusSource = $readFile($workflowStatusPath);
	$secondaryVendorsSource = $readFile($secondaryVendorsPath);
	$adminUiAssetsSource = $readFile($adminUiAssetsPath);
	$workflowAssetSource = $readFile($workflowAssetPath);
	$primaryVendorAssetSource = $readFile($primaryVendorAssetPath);

	foreach (array(
		'const hiddenConfirm = document.getElementById(\'vms_cancel_bulk_retry_confirm\');',
		'const btn = form.querySelector(\'button[type="submit"][name="vms_event_plan_action"][value="retry_cancellation_all"]\');',
		'window.confirm(\'Refund execution is currently failed or blocked. Retrying all steps may attempt refund execution again. Continue?\')',
		'const btn = document.getElementById(\'vms_run_live_refunds_now_button\');',
		'window.location.href = href;',
		'window.alert(\'Unable to start the live refund action because the request link is missing.\');',
		'const autoRefundConfirmField = document.getElementById(\'vms_cancel_auto_refund_confirmed\');',
		'const btn = form.querySelector(\'button[type="submit"][name="vms_event_plan_action"][value="mark_cancelled"]\');',
		'let message = \'Are you sure you want to mark this event as Cancelled?\';',
	) as $removedInlineMarker) {
		$assert(
			strpos($eventPlansSource, $removedInlineMarker) === false,
			'Event Plan PHP should no longer own the inline workflow-confirmation marker: ' . $removedInlineMarker
		);
	}

	$assert(
		preg_match('/<script\b(?![^>]*type=(["\'])application\/json\1)[^>]*>/i', $eventPlansSource) !== 1,
		'Event Plan PHP should no longer emit executable inline <script> blocks.'
	);
	$assert(substr_count($eventPlansSource, '<script') === 0, 'Event Plan PHP should no longer contain any <script> tag after the workflow migration.');

	foreach (array(
		'data-vms-requires-refund-confirm=',
		'id="vms_cancel_bulk_retry_confirm"',
		'id="vms_run_live_refunds_now_button"',
	) as $requiredEventPlansMarkup) {
		$assert(strpos($eventPlansSource, $requiredEventPlansMarkup) !== false, 'Event Plan PHP should retain the workflow markup contract: ' . $requiredEventPlansMarkup);
	}

	foreach (array(
		'id="vms_cancel_policy"',
		'id="vms_cancel_auto_refund_confirmed"',
		'id="vms_reschedule_event_date"',
		'value="mark_cancelled"',
	) as $requiredWorkflowMarkup) {
		$assert(strpos($workflowStatusSource, $requiredWorkflowMarkup) !== false, 'Workflow-status partial should retain the workflow contract: ' . $requiredWorkflowMarkup);
	}

	foreach (array(
		'function initBulkCancellationRetryConfirmation() {',
		'function initLiveRefundConfirmation() {',
		'function initMarkCancelledConfirmation() {',
		'vms_cancel_bulk_retry_confirm',
		'retry_cancellation_all',
		'data-vms-requires-refund-confirm',
		'Refund execution is currently failed or blocked. Retrying all steps may attempt refund execution again. Continue?',
		'button.dataset.vmsWorkflowRetryBound === \'1\'',
		'vms_run_live_refunds_now_button',
		'Run LIVE refunds now for this already-cancelled event? This does not save the Event Plan. VMS will attempt WooCommerce gateway refunds for remaining eligible ticket lines and queue anything unsafe for manual review.',
		'window.alert(\'Unable to start the live refund action because the request link is missing.\');',
		'button.classList.add(\'disabled\');',
		'button.setAttribute(\'aria-disabled\', \'true\');',
		'button.style.pointerEvents = \'none\';',
		'window.location.href = href;',
		'button.dataset.vmsWorkflowLiveRefundBound === \'1\'',
		'mark_cancelled',
		'vms_reschedule_event_date',
		'vms_cancel_policy',
		'vms_cancel_auto_refund_confirmed',
		'stop_sales_auto_refund',
		'stop_sales_auto_refund_remove_attendees',
		'Are you sure you want to mark this event as Cancelled?',
		'VMS will also create a linked Draft Event Plan for ',
		'This will attempt LIVE payment refunds for matching ticket orders through WooCommerce. Mixed orders will refund only the cancelled event ticket lines when possible, and anything unsafe will be queued for manual review.',
		'button.dataset.vmsWorkflowMarkCancelledBound === \'1\'',
		'event.preventDefault();',
		'event.stopPropagation();',
		'document.addEventListener(\'DOMContentLoaded\', initWorkflowControllers, { once: true });',
	) as $requiredWorkflowMarker) {
		$assert(strpos($workflowAssetSource, $requiredWorkflowMarker) !== false, 'Workflow asset should own the migrated workflow-confirmation marker: ' . $requiredWorkflowMarker);
	}

	$assert(strpos($workflowAssetSource, 'window.VMS_EVENT_PLAN_WORKFLOW') === false, 'Workflow asset should not introduce a global configuration object.');
	$assert(strpos($workflowAssetSource, 'vms_tax_bypass_set') === false, 'Workflow asset should not absorb primary-vendor tax behavior.');
	$assert(strpos($primaryVendorAssetSource, 'vms_tax_bypass_set') !== false, 'Primary-vendor asset should remain the owner of tax-bypass behavior.');

	$assert(
		preg_match("/wp_enqueue_script\\(\\s*'vms-event-plan-workflow',\\s*VMS_PLUGIN_URL \\. 'assets\\/js\\/vms-event-plan-workflow\\.js',\\s*array\\(\\),\\s*vms_admin_ui_asset_version\\(\\),\\s*true\\s*\\);/s", $adminUiAssetsSource) === 1,
		'Admin UI assets should register the workflow asset under the dedicated handle with no dependencies.'
	);
	$assert(strpos($adminUiAssetsSource, "in_array((string) \$screen->base, array('post', 'post-new'), true)") !== false, 'Workflow asset should remain restricted to post and post-new screens.');
	$assert(strpos($adminUiAssetsSource, "(string) (\$screen->post_type ?? '') === 'vms_event_plan'") !== false, 'Workflow asset should remain restricted to Event Plan edit/new screens.');
	$assert(strpos($adminUiAssetsSource, 'wp_add_inline_script(') === false, 'Admin UI assets should not reintroduce workflow bootstrap through wp_add_inline_script().');

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
		if (!is_string($contents) || strpos($contents, 'Refund execution is currently failed or blocked. Retrying all steps may attempt refund execution again. Continue?') === false) {
			continue;
		}

		$assetOwnershipHits[] = substr($assetPath, strlen($pluginRoot) + 1);
	}
	sort($assetOwnershipHits);
	$assert($assetOwnershipHits === array('assets/js/vms-event-plan-workflow.js'), 'Only the dedicated workflow asset should own the workflow confirmation prompts. Found: ' . implode(', ', $assetOwnershipHits));

	$partialFiles = glob($pluginRoot . '/includes/cpt/event-plans/partials/*.php');
	$assert(is_array($partialFiles) && $partialFiles !== array(), 'Expected Event Plan partials to remain present.');
	$partialExecutableScriptHits = array();
	$partialScriptTagHits = array();
	foreach ($partialFiles as $partialPath) {
		$contents = $readFile($partialPath);
		if (strpos($contents, '<script') === false) {
			continue;
		}

		if (preg_match('/<script\b(?![^>]*type=(["\'])application\/json\1)[^>]*>/i', $contents) === 1) {
			$partialExecutableScriptHits[] = basename($partialPath);
			continue;
		}

		$partialScriptTagHits[] = basename($partialPath);
	}
	sort($partialExecutableScriptHits);
	sort($partialScriptTagHits);
	$assert($partialExecutableScriptHits === array(), 'No Event Plan partial should retain an executable inline <script> block. Found: ' . implode(', ', $partialExecutableScriptHits));
	$assert($partialScriptTagHits === array('secondary-vendors.php'), 'Only the Secondary Vendors partial should retain an inert script payload. Found: ' . implode(', ', $partialScriptTagHits));
	$assert(strpos($secondaryVendorsSource, '<script type="application/json" data-vms-secondary-config>') !== false, 'Secondary Vendors partial should retain the inert application/json configuration payload.');

	fwrite(STDOUT, "event plan workflow confirmations inline js remediation: PASS\n");
} catch (Throwable $e) {
	fwrite(STDERR, 'event plan workflow confirmations inline js remediation: FAIL - ' . $e->getMessage() . "\n");
	exit(1);
}
