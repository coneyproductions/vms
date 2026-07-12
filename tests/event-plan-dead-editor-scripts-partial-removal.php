<?php
declare(strict_types=1);

$pluginRoot = dirname(__DIR__);
$deletedPartialPath = $pluginRoot . '/includes/cpt/event-plans/partials/editor-scripts.php';
$deletedBasicDetailsPath = $pluginRoot . '/includes/cpt/event-plans/partials/basic-details.php';
$deletedSecondaryVendorsSectionPath = $pluginRoot . '/includes/cpt/event-plans/partials/secondary-vendors-section.php';
$eventPlansPath = $pluginRoot . '/includes/cpt/event-plans.php';
$secondaryVendorsPath = $pluginRoot . '/includes/cpt/event-plans/partials/secondary-vendors.php';
$compensationPath = $pluginRoot . '/includes/cpt/event-plans/partials/compensation.php';
$adminUiAssetsPath = $pluginRoot . '/includes/admin-ui/assets.php';
$shellAssetPath = $pluginRoot . '/assets/js/vms-event-plan-shell.js';
$ticketingBootstrapPath = $pluginRoot . '/includes/integrations/ticketing.php';
$staffTasksAdminUiPath = $pluginRoot . '/includes/modules/staff-tasks/admin-ui.php';

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
	$assert(!file_exists($deletedPartialPath), 'The dead Event Plan editor-scripts partial should be deleted.');
	$assert(!file_exists($deletedBasicDetailsPath), 'The dead Event Plan basic-details partial should be deleted.');
	$assert(!file_exists($deletedSecondaryVendorsSectionPath), 'The dead Event Plan secondary-vendors-section partial should be deleted.');

	$runtimeEditorScriptHits = array();
	$deadPartialCallerHits = array();
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($pluginRoot . '/includes', FilesystemIterator::SKIP_DOTS)
	);
	foreach ($iterator as $fileInfo) {
		if (!$fileInfo instanceof SplFileInfo || !$fileInfo->isFile() || $fileInfo->getExtension() !== 'php') {
			continue;
		}

		$path = $fileInfo->getPathname();
		$contents = file_get_contents($path);
		if (!is_string($contents) || strpos($contents, 'editor-scripts') === false) {
			$contents = is_string($contents) ? $contents : '';
		} else {
			$runtimeEditorScriptHits[] = substr($path, strlen($pluginRoot) + 1);
		}

		$deadPartialNeedles = array(
			"capture_event_plan_partial('basic-details'",
			"render_event_plan_partial('basic-details'",
			'basic-details.php',
			"capture_event_plan_partial('secondary-vendors-section'",
			"render_event_plan_partial('secondary-vendors-section'",
			'secondary-vendors-section.php',
		);
		foreach ($deadPartialNeedles as $needle) {
			if (strpos($contents, $needle) === false) {
				continue;
			}

			$deadPartialCallerHits[] = substr($path, strlen($pluginRoot) + 1) . ' :: ' . $needle;
		}
	}
	$assert($runtimeEditorScriptHits === array(), 'No first-party runtime PHP source should retain an editor-scripts reference. Found: ' . implode(', ', $runtimeEditorScriptHits));
	$assert($deadPartialCallerHits === array(), 'No first-party runtime PHP source should retain a deleted Event Plan partial capture/include path. Found: ' . implode(', ', $deadPartialCallerHits));

	$eventPlansSource = $readFile($eventPlansPath);
	$secondaryVendorsSource = $readFile($secondaryVendorsPath);
	$compensationSource = $readFile($compensationPath);
	$adminUiAssetsSource = $readFile($adminUiAssetsPath);
	$shellAssetSource = $readFile($shellAssetPath);
	$ticketingBootstrapSource = $readFile($ticketingBootstrapPath);
	$staffTasksAdminUiSource = $readFile($staffTasksAdminUiPath);

	$assert(strpos($eventPlansSource, "'editor-scripts'") === false, 'Event Plan source should not request the deleted editor-scripts partial.');
	$assert(strpos($eventPlansSource, 'editor-scripts.php') === false, 'Event Plan source should not reference the deleted editor-scripts.php path.');
	$assert(strpos($eventPlansSource, "'basic-details'") === false, 'Event Plan source should not request the deleted basic-details partial.');
	$assert(strpos($eventPlansSource, 'basic-details.php') === false, 'Event Plan source should not reference the deleted basic-details.php path.');
	$assert(strpos($eventPlansSource, "'secondary-vendors-section'") === false, 'Event Plan source should not request the deleted secondary-vendors-section partial.');
	$assert(strpos($eventPlansSource, 'secondary-vendors-section.php') === false, 'Event Plan source should not reference the deleted secondary-vendors-section.php path.');
	$assert(strpos($eventPlansSource, "in_array(\$section, array('staff', 'secondary_vendors', 'readiness_details'), true)") !== false, 'Lazy section inventory should remain scoped to staff, secondary_vendors, and readiness_details.');

	$capturedPartials = array();
	if (preg_match_all("/capture_event_plan_partial\\(\\s*'([^']+)'/", $eventPlansSource, $matches)) {
		$capturedPartials = array_values(array_unique(array_map('strval', $matches[1])));
		sort($capturedPartials);
	}
	$assert(!in_array('editor-scripts', $capturedPartials, true), 'Dynamic Event Plan partial inventory should not include editor-scripts.');
	$assert(!in_array('basic-details', $capturedPartials, true), 'Dynamic Event Plan partial inventory should not include basic-details.');
	$assert(!in_array('secondary-vendors-section', $capturedPartials, true), 'Dynamic Event Plan partial inventory should not include secondary-vendors-section.');
	$requiredCapturedPartials = array(
		'advanced-controls',
		'compensation',
		'readiness-details',
		'secondary-vendors',
		'staff',
		'ticketing-v2',
		'time-lineup',
		'title',
		'workflow-status',
	);
	foreach ($requiredCapturedPartials as $partial) {
		$assert(in_array($partial, $capturedPartials, true), 'Expected active Event Plan partial capture to remain present: ' . $partial);
	}

	$partialFiles = glob($pluginRoot . '/includes/cpt/event-plans/partials/*.php');
	$assert(is_array($partialFiles) && $partialFiles !== array(), 'Expected Event Plan partial inventory to remain available.');
	$partialBasenames = array_map('basename', $partialFiles);
	sort($partialBasenames);
	$assert(!in_array('editor-scripts.php', $partialBasenames, true), 'Static Event Plan partial inventory should not include editor-scripts.php.');
	$assert(!in_array('basic-details.php', $partialBasenames, true), 'Static Event Plan partial inventory should not include basic-details.php.');
	$assert(!in_array('secondary-vendors-section.php', $partialBasenames, true), 'Static Event Plan partial inventory should not include secondary-vendors-section.php.');
	foreach (array(
		'advanced-controls.php',
		'comp-ack.php',
		'compensation.php',
		'legacy-imported-ticketing-integration.php',
		'readiness-details.php',
		'secondary-vendors.php',
		'staff.php',
		'ticketing-v2.php',
		'time-lineup.php',
		'title.php',
		'workflow-status.php',
	) as $activePartialFile) {
		$assert(in_array($activePartialFile, $partialBasenames, true), 'Expected active Event Plan partial file to remain present: ' . $activePartialFile);
	}

	$assert(strpos($eventPlansSource, 'class="vms-ep-basic-grid"') !== false, 'Live Event Plan renderer should retain the inlined basic details grid.');
	$assert(strpos($eventPlansSource, 'This event plan lost its vendor (the vendor was deleted) and needs attention.') !== false, 'Live Event Plan renderer should retain the primary-vendor integrity notice.');
	$assert(strpos($eventPlansSource, 'Booking prefill: %s was added as the primary vendor. Review below, then save the Event Plan.') !== false, 'Live Event Plan renderer should retain the primary-vendor booking-prefill notice.');
	$assert(strpos($eventPlansSource, 'capture_event_plan_partial(\'secondary-vendors\'') !== false, 'Live Event Plan renderer should still capture the active secondary-vendors partial.');
	$assert(strpos($secondaryVendorsSource, 'id="vms-secondary-vendors-section"') !== false, 'Active secondary-vendors partial should retain the live section wrapper.');
	$assert(strpos($secondaryVendorsSource, 'data-vms-save-nonce') !== false, 'Active secondary-vendors partial should retain its save contract.');

	$assert(substr_count($eventPlansSource, '<script') >= 9, 'Event Plan source should still contain the current live inline script surface for later B1 slices.');
	$assert(strpos($eventPlansSource, 'window.vmsEventPlanPersistRequestedSection = persistRequestedSection;') === false, 'Event Plan source should no longer retain the migrated shell requested-section persistence helper.');
	$assert(strpos($eventPlansSource, 'window.vmsEventPlanRevealRequestedSection = revealRequestedSection;') === false, 'Event Plan source should no longer retain the migrated shell requested-section reveal helper.');
	$assert(strpos($shellAssetSource, 'window.vmsEventPlanPersistRequestedSection = persistRequestedSection;') !== false, 'Shell asset should now own the requested-section persistence helper.');
	$assert(strpos($shellAssetSource, 'window.vmsEventPlanRevealRequestedSection = revealRequestedSection;') !== false, 'Shell asset should now own the requested-section reveal helper.');
	$assert(strpos($eventPlansSource, 'window.vmsEventPlanInitStaff = initStaff;') !== false, 'Event Plan source should retain the live staff initializer.');
	$assert(strpos($eventPlansSource, 'window.vmsEventPlanInitSecondaryVendors = initSecondaryVendors;') !== false, 'Event Plan source should retain the live secondary-vendor initializer.');
	$assert(strpos($compensationSource, "document.dispatchEvent(new Event('vms_comp_options_updated'));") !== false, 'Compensation partial should retain the live comp-options update bridge.');
	$assert(strpos($secondaryVendorsSource, 'data-vms-secondary-config') !== false, 'Secondary Vendors partial should retain the live non-executable JSON configuration payload.');

	$assert(!file_exists($pluginRoot . '/assets/js/vms-event-plan-editor.js'), 'This dead-partial slice should not create a monolithic Event Plan editor asset.');
	$assert(file_exists($pluginRoot . '/assets/js/vms-lineup-schedule-admin.js'), 'Existing Event Plan lineup asset should remain present.');
	$assert(file_exists($pluginRoot . '/assets/admin-ticketing.js'), 'Existing Event Plan ticketing asset should remain present.');
	$assert(file_exists($pluginRoot . '/assets/js/vms-tasks-event-plan-metabox.js'), 'Existing Event Plan staff-task asset should remain present.');
	$assert(strpos($adminUiAssetsSource, "'vms-lineup-schedule-admin'") !== false, 'Admin UI enqueue path should still register the lineup Event Plan asset.');
	$assert(strpos($adminUiAssetsSource, 'assets/js/vms-lineup-schedule-admin.js') !== false, 'Admin UI enqueue path should still point to the lineup Event Plan asset.');
	$assert(strpos($ticketingBootstrapSource, "'vms-admin-ticketing'") !== false, 'Ticketing bootstrap should still enqueue the existing Event Plan ticketing asset.');
	$assert(strpos($ticketingBootstrapSource, 'assets/admin-ticketing.js') !== false, 'Ticketing bootstrap should still point to assets/admin-ticketing.js.');
	$assert(strpos($staffTasksAdminUiSource, "'vms-tasks-event-plan-metabox'") !== false, 'Staff Tasks admin UI should still enqueue the existing Event Plan staff-task asset.');
	$assert(strpos($staffTasksAdminUiSource, 'assets/js/vms-tasks-event-plan-metabox.js') !== false, 'Staff Tasks admin UI should still point to the existing Event Plan staff-task asset.');

	fwrite(STDOUT, "event plan dead editor-scripts partial removal: PASS\n");
} catch (Throwable $e) {
	fwrite(STDERR, 'event plan dead editor-scripts partial removal: FAIL - ' . $e->getMessage() . "\n");
	exit(1);
}
