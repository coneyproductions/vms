<?php
declare(strict_types=1);

$pluginRoot = dirname(__DIR__);
$eventPlansPath = $pluginRoot . '/includes/cpt/event-plans.php';
$adminUiAssetsPath = $pluginRoot . '/includes/admin-ui/assets.php';
$shellAssetPath = $pluginRoot . '/assets/js/vms-event-plan-shell.js';
$staffAssetPath = $pluginRoot . '/assets/js/vms-event-plan-staff.js';
$staffPartialPath = $pluginRoot . '/includes/cpt/event-plans/partials/staff.php';
$ticketingAssetPath = $pluginRoot . '/assets/admin-ticketing.js';

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

$findExecutableInlineScriptTags = static function (string $source): array {
	preg_match_all('~<script\b([^>]*)>~i', $source, $matches, PREG_SET_ORDER);
	$hits = array();
	foreach ($matches as $match) {
		$tag = (string) ($match[0] ?? '');
		$attrs = (string) ($match[1] ?? '');
		$isApplicationJson = stripos($attrs, 'type="application/json"') !== false
			|| stripos($attrs, "type='application/json'") !== false
			|| preg_match('~\btype\s*=\s*application/json(?:\s|$)~i', $attrs) === 1;
		if ($isApplicationJson) {
			continue;
		}
		$hits[] = $tag;
	}
	return $hits;
};

$findApplicationJsonScriptTags = static function (string $source, string $requiredMarker = ''): array {
	preg_match_all('~<script\b([^>]*)>~i', $source, $matches, PREG_SET_ORDER);
	$hits = array();
	foreach ($matches as $match) {
		$tag = (string) ($match[0] ?? '');
		$attrs = (string) ($match[1] ?? '');
		$isApplicationJson = stripos($attrs, 'type="application/json"') !== false
			|| stripos($attrs, "type='application/json'") !== false
			|| preg_match('~\btype\s*=\s*application/json(?:\s|$)~i', $attrs) === 1;
		if (!$isApplicationJson) {
			continue;
		}
		if ($requiredMarker !== '' && stripos($tag, $requiredMarker) === false) {
			continue;
		}
		$hits[] = $tag;
	}
	return $hits;
};

try {
	$eventPlansSource = $readFile($eventPlansPath);
	$adminUiAssetsSource = $readFile($adminUiAssetsPath);
	$shellAssetSource = $readFile($shellAssetPath);
	$staffAssetSource = $readFile($staffAssetPath);
	$staffPartialSource = $readFile($staffPartialPath);
	$ticketingAssetSource = $readFile($ticketingAssetPath);

	foreach (array(
		'function initStaff(root) {',
		'window.vmsEventPlanInitStaff = initStaff;',
		"initStaff(document);",
	) as $removedInlineMarker) {
		$assert(strpos($eventPlansSource, $removedInlineMarker) === false, 'Event Plan PHP should no longer own the staff controller marker: ' . $removedInlineMarker);
	}

	foreach (array(
		'function initStaff(root) {',
		'window.vmsEventPlanInitStaff = initStaff;',
		'[data-vms-staff-wrap="1"]',
		'[data-vms-role-assignment-input="1"]',
		'[data-vms-role-headcount-input="1"]',
		'[data-vms-role-threshold-input="1"]',
		'[data-vms-role-time-mode-input="1"]',
		'[data-vms-role-shift-start-input="1"]',
		'[data-vms-role-shift-end-input="1"]',
		'[data-vms-role-duration-input="1"]',
		'wrap.dataset.vmsStaffInit === \'1\'',
		'field.addEventListener(\'input\'',
		'field.addEventListener(\'change\'',
		'initStaff(document);',
	) as $requiredStaffMarker) {
		$assert(strpos($staffAssetSource, $requiredStaffMarker) !== false, 'Staff asset should own the migrated staff-controller marker: ' . $requiredStaffMarker);
	}

	$assert(strpos($staffAssetSource, 'var scope = root && root.querySelector ? root : document;') !== false, 'Staff asset should preserve root-scoped initialization compatibility.');
	$assert(strpos($shellAssetSource, 'window.vmsEventPlanInitStaff(body);') !== false, 'Shell asset should still invoke the staff initializer after lazy loading.');
	$assert(strpos($ticketingAssetSource, 'window.vmsEventPlanInitStaff') === false, 'Ticketing asset should not own the staff initializer.');
	$assert(strpos($staffPartialSource, 'data-vms-staff-wrap="1"') !== false, 'Staff markup should retain the live wrap selector contract.');
	$assert(strpos($staffPartialSource, 'data-vms-role-assignment-input="1"') !== false, 'Staff markup should retain the assignment selector contract.');
	$assert(strpos($staffPartialSource, 'data-vms-role-headcount-input="1"') !== false, 'Staff markup should retain the headcount selector contract.');
	$assert($findExecutableInlineScriptTags($eventPlansSource) === array(), 'Event Plan PHP should not emit executable inline <script> blocks.');
	$assert(count($findApplicationJsonScriptTags($eventPlansSource, 'data-vms-secondary-config')) === 2, 'Event Plan PHP should retain only the two inert Secondary Vendors application/json carriers.');
	$assert(strpos($adminUiAssetsSource, "'vms-event-plan-staff'") !== false, 'Admin UI assets should register the new Event Plan staff handle.');
	$assert(strpos($adminUiAssetsSource, "BVMGR_PLUGIN_URL . 'assets/js/vms-event-plan-staff.js'") !== false, 'Admin UI assets should point the staff handle at assets/js/vms-event-plan-staff.js.');
	$assert(strpos($adminUiAssetsSource, "in_array((string) \$screen->base, array('post', 'post-new'), true)") !== false, 'Staff asset should remain restricted to post and post-new screens.');
	$assert(strpos($adminUiAssetsSource, "(string) (\$screen->post_type ?? '') === 'vms_event_plan'") !== false, 'Staff asset should remain restricted to Event Plan edit/new screens.');

	$assetInitializerHits = array();
	$assetIterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($pluginRoot . '/assets', FilesystemIterator::SKIP_DOTS)
	);
	foreach ($assetIterator as $fileInfo) {
		if (!$fileInfo instanceof SplFileInfo || !$fileInfo->isFile() || $fileInfo->getExtension() !== 'js') {
			continue;
		}

		$assetPath = $fileInfo->getPathname();
		$contents = file_get_contents($assetPath);
		if (!is_string($contents) || strpos($contents, 'window.vmsEventPlanInitStaff = initStaff;') === false) {
			continue;
		}

		$assetInitializerHits[] = substr($assetPath, strlen($pluginRoot) + 1);
	}
	sort($assetInitializerHits);
	$assert($assetInitializerHits === array('assets/js/vms-event-plan-staff.js'), 'Only the dedicated staff asset should own the public staff initializer. Found: ' . implode(', ', $assetInitializerHits));

	fwrite(STDOUT, "event plan staff inline js remediation: PASS\n");
} catch (Throwable $e) {
	fwrite(STDERR, 'event plan staff inline js remediation: FAIL - ' . $e->getMessage() . "\n");
	exit(1);
}
