<?php
declare(strict_types=1);

$pluginRoot = dirname(__DIR__);
$eventPlansPath = $pluginRoot . '/includes/cpt/event-plans.php';
$compensationPath = $pluginRoot . '/includes/cpt/event-plans/partials/compensation.php';
$adminUiAssetsPath = $pluginRoot . '/includes/admin-ui/assets.php';
$compensationAssetPath = $pluginRoot . '/assets/js/vms-event-plan-compensation.js';

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
	$compensationSource = $readFile($compensationPath);
	$adminUiAssetsSource = $readFile($adminUiAssetsPath);
	$compensationAssetSource = $readFile($compensationAssetPath);

	foreach (array(
		'document.documentElement.classList.add(\'vms-js\');',
		'const actionButtons = Array.from(form.querySelectorAll(\'button[type="submit"][name="vms_event_plan_action"]\'));',
		'function renderAttendancePreview() {',
		'const section = document.getElementById(\'vms-pay-override-box\');',
		'const lowSummary = document.getElementById(\'vms-low-guarantee-summary\');',
		'document.addEventListener(\'vms_comp_options_updated\', () => {',
		'setButtonsDisabled(needsOverrideAck || needsLowAck || attendanceInvalid);',
	) as $removedInlineMarker) {
		$assert(
			strpos($eventPlansSource, $removedInlineMarker) === false,
			'Event Plan PHP should no longer own the migrated compensation-shell marker: ' . $removedInlineMarker
		);
	}

	foreach (array(
		'vms_get_event_plan_comp_options',
		'vms_get_venue_comp_defaults',
		'document.documentElement.classList.add(\'vms-js\');',
		'form.dataset.vmsCompStateBound === \'1\'',
		'postForm.dataset.vmsCompVenueDefaultsBound === \'1\'',
		'wrap.dataset.vmsCompOptionsBound === \'1\'',
		'vms-comp-ack-wrap',
		'vms-pay-override-box',
		'vms-pay-override-summary',
		'vms-low-guarantee-box',
		'vms-low-guarantee-summary',
		'vms_default_structure',
		'vms_default_label',
		'vms_flat_fee_amount_label_text',
		'vms-agent-fee-summary',
		'vms-attendance-bonus-preview',
		'vms_max_guarantee_available',
		'mark_ready',
		'publish_now',
		'lock_draft_pay',
		'document.dispatchEvent(new Event(\'vms_comp_options_updated\'));',
		'document.addEventListener(\'vms_comp_options_updated\', () => {',
		'setButtonsDisabled(needsOverrideAck || needsLowAck || attendanceInvalid);',
	) as $requiredCompMarker) {
		$assert(strpos($compensationAssetSource, $requiredCompMarker) !== false, 'Compensation asset should own the migrated compensation-shell marker: ' . $requiredCompMarker);
	}

	$assert(strpos($compensationAssetSource, 'window.VMS_EVENT_PLAN_COMPENSATION') === false, 'Compensation asset should not introduce a global configuration object.');
	$assert(strpos($compensationSource, 'id="vms-comp-options" data-nonce=') !== false, 'Compensation partial should retain the comp-options markup and nonce attribute.');
	$assert(strpos($compensationSource, 'id="vms-comp-tiles"') !== false, 'Compensation partial should retain the Draft Pay structure tile markup.');
	$assert(strpos($compensationSource, 'id="vms_flat_fee_amount_label_text"') !== false, 'Compensation partial should retain the Base Pay / Flat Fee label node.');
	$assert(strpos($compensationSource, 'id="vms-agent-fee-summary"') !== false, 'Compensation partial should retain the agent-fee summary node.');
	$assert(strpos($compensationSource, 'id="vms-attendance-bonus-preview"') !== false, 'Compensation partial should retain the attendance preview node.');
	$assert(strpos($eventPlansSource, 'const hiddenConfirm = document.getElementById(\'vms_cancel_bulk_retry_confirm\');') !== false, 'Adjacent cancellation confirmation controller should remain inline.');
	$assert(strpos($eventPlansSource, 'const btn = document.getElementById(\'vms_run_live_refunds_now_button\');') !== false, 'Adjacent live-refunds confirmation controller should remain inline.');
	$assert(strpos($adminUiAssetsSource, "'vms-event-plan-compensation'") !== false, 'Admin UI assets should retain the Event Plan compensation handle.');
	$assert(strpos($adminUiAssetsSource, "VMS_PLUGIN_URL . 'assets/js/vms-event-plan-compensation.js'") !== false, 'Admin UI assets should still point the compensation handle at assets/js/vms-event-plan-compensation.js.');
	$assert(strpos($adminUiAssetsSource, "in_array((string) \$screen->base, array('post', 'post-new'), true)") !== false, 'Compensation asset should remain restricted to post and post-new screens.');
	$assert(strpos($adminUiAssetsSource, "(string) (\$screen->post_type ?? '') === 'vms_event_plan'") !== false, 'Compensation asset should remain restricted to Event Plan edit/new screens.');
	$assert(substr_count($eventPlansSource, '<script') >= 3, 'WPORG-22 B1 should still have active inline Event Plan script blocks after the compensation-shell migration.');

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
		if (!is_string($contents) || strpos($contents, 'setButtonsDisabled(needsOverrideAck || needsLowAck || attendanceInvalid);') === false) {
			continue;
		}

		$assetOwnershipHits[] = substr($assetPath, strlen($pluginRoot) + 1);
	}
	sort($assetOwnershipHits);
	$assert($assetOwnershipHits === array('assets/js/vms-event-plan-compensation.js'), 'Only the dedicated compensation asset should own the compensation-shell controller. Found: ' . implode(', ', $assetOwnershipHits));

	fwrite(STDOUT, "event plan compensation shell inline js remediation: PASS\n");
} catch (Throwable $e) {
	fwrite(STDERR, 'event plan compensation shell inline js remediation: FAIL - ' . $e->getMessage() . "\n");
	exit(1);
}
