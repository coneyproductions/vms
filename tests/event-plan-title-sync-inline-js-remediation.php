<?php
declare(strict_types=1);

$pluginRoot = dirname(__DIR__);
$eventPlansPath = $pluginRoot . '/includes/cpt/event-plans.php';
$adminUiAssetsPath = $pluginRoot . '/includes/admin-ui/assets.php';
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
	$titleAssetSource = $readFile($titleAssetPath);

	foreach (array(
		'const autoTitle = document.querySelector(\'input[name="vms_auto_title"]\');',
		'const previewEl = document.getElementById(\'vms_title_preview_text\');',
		'function buildTitle() {',
		'wp.data.dispatch(\'core/editor\').editPost({',
		'window.onbeforeunload = null;',
		'Primary Vendor changed. Update the title to match the selected Primary Vendor?',
	) as $removedInlineMarker) {
		$assert(strpos($eventPlansSource, $removedInlineMarker) === false, 'Event Plan PHP should no longer own the title-sync controller marker: ' . $removedInlineMarker);
	}

	foreach (array(
		'input[name="vms_auto_title"]',
		'vms_title_preview_text',
		'vms_title_lock_note',
		'data-vendor-title',
		'document.getElementById(\'title\')',
		'document.querySelector(\'textarea.editor-post-title__input\')',
		'document.querySelector(\'h1.editor-post-title__input\')',
		'wp.data.dispatch(\'core/editor\').editPost({',
		'window.onbeforeunload = null;',
		'Primary Vendor changed. Update the title to match the selected Primary Vendor?',
		'(auto-title disabled)',
		'(select Primary Vendor to preview)',
		'postForm.dataset.vmsTitleSyncBound === \'1\'',
	) as $requiredTitleMarker) {
		$assert(strpos($titleAssetSource, $requiredTitleMarker) !== false, 'Title asset should own the migrated title-sync marker: ' . $requiredTitleMarker);
	}

	$assert(strpos($titleAssetSource, 'window.VMS_EVENT_PLAN_TITLE') === false, 'Title asset should not introduce a global configuration object.');
	$assert(strpos($adminUiAssetsSource, "'vms-event-plan-title'") !== false, 'Admin UI assets should register the new Event Plan title handle.');
	$assert(strpos($adminUiAssetsSource, "BVMGR_PLUGIN_URL . 'assets/js/vms-event-plan-title.js'") !== false, 'Admin UI assets should point the title handle at assets/js/vms-event-plan-title.js.');
	$assert(strpos($adminUiAssetsSource, "in_array((string) \$screen->base, array('post', 'post-new'), true)") !== false, 'Title asset should remain restricted to post and post-new screens.');
	$assert(strpos($adminUiAssetsSource, "(string) (\$screen->post_type ?? '') === 'vms_event_plan'") !== false, 'Title asset should remain restricted to Event Plan edit/new screens.');
	$assert(strpos($eventPlansSource, 'const hiddenConfirm = document.getElementById(\'vms_cancel_bulk_retry_confirm\');') === false, 'The workflow confirmation controller should no longer remain inline.');
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
		if (!is_string($contents) || strpos($contents, 'Primary Vendor changed. Update the title to match the selected Primary Vendor?') === false) {
			continue;
		}

		$assetOwnershipHits[] = substr($assetPath, strlen($pluginRoot) + 1);
	}
	sort($assetOwnershipHits);
	$assert($assetOwnershipHits === array('assets/js/vms-event-plan-title.js'), 'Only the dedicated title asset should own the title-sync controller. Found: ' . implode(', ', $assetOwnershipHits));

	fwrite(STDOUT, "event plan title sync inline js remediation: PASS\n");
} catch (Throwable $e) {
	fwrite(STDERR, 'event plan title sync inline js remediation: FAIL - ' . $e->getMessage() . "\n");
	exit(1);
}
