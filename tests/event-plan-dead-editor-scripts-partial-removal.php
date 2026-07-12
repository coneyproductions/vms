<?php
declare(strict_types=1);

$pluginRoot = dirname(__DIR__);
$deletedPartialPath = $pluginRoot . '/includes/cpt/event-plans/partials/editor-scripts.php';
$eventPlansPath = $pluginRoot . '/includes/cpt/event-plans.php';
$secondaryVendorsPath = $pluginRoot . '/includes/cpt/event-plans/partials/secondary-vendors.php';
$compensationPath = $pluginRoot . '/includes/cpt/event-plans/partials/compensation.php';
$adminUiAssetsPath = $pluginRoot . '/includes/admin-ui/assets.php';
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

	$runtimeEditorScriptHits = array();
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
			continue;
		}

		$runtimeEditorScriptHits[] = substr($path, strlen($pluginRoot) + 1);
	}
	$assert($runtimeEditorScriptHits === array(), 'No first-party runtime PHP source should retain an editor-scripts reference. Found: ' . implode(', ', $runtimeEditorScriptHits));

	$eventPlansSource = $readFile($eventPlansPath);
	$secondaryVendorsSource = $readFile($secondaryVendorsPath);
	$compensationSource = $readFile($compensationPath);
	$adminUiAssetsSource = $readFile($adminUiAssetsPath);
	$ticketingBootstrapSource = $readFile($ticketingBootstrapPath);
	$staffTasksAdminUiSource = $readFile($staffTasksAdminUiPath);

	$assert(strpos($eventPlansSource, "'editor-scripts'") === false, 'Event Plan source should not request the deleted editor-scripts partial.');
	$assert(strpos($eventPlansSource, 'editor-scripts.php') === false, 'Event Plan source should not reference the deleted editor-scripts.php path.');
	$assert(strpos($eventPlansSource, "in_array(\$section, array('staff', 'secondary_vendors', 'readiness_details'), true)") !== false, 'Lazy section inventory should remain scoped to staff, secondary_vendors, and readiness_details.');

	$capturedPartials = array();
	if (preg_match_all("/capture_event_plan_partial\\(\\s*'([^']+)'/", $eventPlansSource, $matches)) {
		$capturedPartials = array_values(array_unique(array_map('strval', $matches[1])));
		sort($capturedPartials);
	}
	$assert(!in_array('editor-scripts', $capturedPartials, true), 'Dynamic Event Plan partial inventory should not include editor-scripts.');
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

	$assert(substr_count($eventPlansSource, '<script') >= 10, 'Event Plan source should still contain the current live inline script surface for later B1 slices.');
	$assert(strpos($eventPlansSource, 'window.vmsEventPlanPersistRequestedSection = persistRequestedSection;') !== false, 'Event Plan source should retain the live requested-section persistence helper.');
	$assert(strpos($eventPlansSource, 'window.vmsEventPlanRevealRequestedSection = revealRequestedSection;') !== false, 'Event Plan source should retain the live requested-section reveal helper.');
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
