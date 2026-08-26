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
	$compensationSource = $readFile($compensationPath);
	$adminUiAssetsSource = $readFile($adminUiAssetsPath);
	$compensationAssetSource = $readFile($compensationAssetPath);

	foreach (array(
		'document.addEventListener(\'DOMContentLoaded\', () => {',
		'form.append(\'action\', \'vms_get_event_plan_comp_options\');',
		'const autoChk = document.getElementById(\'vms_auto_comp_venue\');',
		'form.append(\'action\', \'vms_get_venue_comp_defaults\');',
	) as $removedInlineMarker) {
		$assert(
			strpos($compensationSource, $removedInlineMarker) === false && strpos($eventPlansSource, $removedInlineMarker) === false,
			'Event Plan PHP should no longer own the compensation-refresh marker: ' . $removedInlineMarker
		);
	}

	foreach (array(
		'vms_get_event_plan_comp_options',
		'vms_get_venue_comp_defaults',
		'vms_comp_options_updated',
		'vms_comp_package_id',
		'vms_comp_selected_option',
		'vms_auto_comp_venue',
		'vms-venue-defaults-hint',
		'data-nonce',
		'postForm.dataset.vmsCompVenueDefaultsBound === \'1\'',
		'wrap.dataset.vmsCompOptionsBound === \'1\'',
		'document.dispatchEvent(new Event(\'vms_comp_options_updated\'));',
		'fetch(ajaxurl',
	) as $requiredCompMarker) {
		$assert(strpos($compensationAssetSource, $requiredCompMarker) !== false, 'Compensation asset should own the migrated compensation marker: ' . $requiredCompMarker);
	}

	$assert(strpos($compensationSource, 'id="vms-comp-options" data-nonce=') !== false, 'Compensation partial should retain the comp-options markup and nonce attribute.');
	$assert(strpos($eventPlansSource, "document.addEventListener('vms_comp_options_updated', () => {") === false, 'Event Plan PHP should no longer retain the migrated compensation consumer.');
	$assert(strpos($compensationAssetSource, "document.addEventListener('vms_comp_options_updated', () => {") !== false, 'Compensation asset should now own the vms_comp_options_updated consumer.');
	$assert(strpos($eventPlansSource, 'const btn = document.getElementById(\'vms_run_live_refunds_now_button\');') === false, 'Refund-related workflow controller should no longer remain inline.');
	$assert(strpos($adminUiAssetsSource, "'vms-event-plan-compensation'") !== false, 'Admin UI assets should register the new Event Plan compensation handle.');
	$assert(strpos($adminUiAssetsSource, "BVMGR_PLUGIN_URL . 'assets/js/vms-event-plan-compensation.js'") !== false, 'Admin UI assets should point the compensation handle at assets/js/vms-event-plan-compensation.js.');
	$assert(strpos($adminUiAssetsSource, "in_array((string) \$screen->base, array('post', 'post-new'), true)") !== false, 'Compensation asset should remain restricted to post and post-new screens.');
	$assert(strpos($adminUiAssetsSource, "(string) (\$screen->post_type ?? '') === 'vms_event_plan'") !== false, 'Compensation asset should remain restricted to Event Plan edit/new screens.');
	$assert($findExecutableInlineScriptTags($eventPlansSource) === array(), 'Event Plan PHP should not emit executable inline <script> blocks.');
	$assert(count($findApplicationJsonScriptTags($eventPlansSource, 'data-vms-secondary-config')) === 2, 'Event Plan PHP should retain only the two inert Secondary Vendors application/json carriers.');

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
		if (!is_string($contents) || strpos($contents, 'vms_get_event_plan_comp_options') === false) {
			continue;
		}

		$assetOwnershipHits[] = substr($assetPath, strlen($pluginRoot) + 1);
	}
	sort($assetOwnershipHits);
	$assert($assetOwnershipHits === array('assets/js/vms-event-plan-compensation.js'), 'Only the dedicated compensation asset should own the compensation refresh controllers. Found: ' . implode(', ', $assetOwnershipHits));

	fwrite(STDOUT, "event plan compensation refresh inline js remediation: PASS\n");
} catch (Throwable $e) {
	fwrite(STDERR, 'event plan compensation refresh inline js remediation: FAIL - ' . $e->getMessage() . "\n");
	exit(1);
}
