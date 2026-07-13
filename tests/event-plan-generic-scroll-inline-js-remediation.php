<?php
declare(strict_types=1);

$pluginRoot = dirname(__DIR__);
$eventPlansPath = $pluginRoot . '/includes/cpt/event-plans.php';
$adminUiAssetsPath = $pluginRoot . '/includes/admin-ui/assets.php';
$shellAssetPath = $pluginRoot . '/assets/js/vms-event-plan-shell.js';
$ticketingAssetPath = $pluginRoot . '/assets/admin-ticketing.js';
$partialsDir = $pluginRoot . '/includes/cpt/event-plans/partials';

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
		$adminUiAssetsSource = $readFile($adminUiAssetsPath);
		$shellAssetSource = $readFile($shellAssetPath);
		$ticketingAssetSource = $readFile($ticketingAssetPath);

		$assert(strpos($eventPlansSource, '$scroll_to = (string) get_post_meta($post->ID, \'_vms_admin_scroll_to\', true);') !== false, 'Event Plan source should retain the server-side _vms_admin_scroll_to target lookup.');
		$assert(strpos($eventPlansSource, "delete_post_meta(\$post->ID, '_vms_admin_scroll_to');") !== false, 'Event Plan source should still clear the _vms_admin_scroll_to marker after rendering.');
		$assert(
			preg_match('~<script>\s*document\.addEventListener\(\'DOMContentLoaded\', function\(\) \{\s*const el = document\.getElementById\(\'<\?php echo esc_js\(\$scroll_to\); \?>\'\);~s', $eventPlansSource) !== 1,
			'Event Plan source should no longer emit the inline generic scroll helper.'
		);
	$assert(strpos($eventPlansSource, 'data-vms-scroll-target=') !== false, 'Event Plan source should hand off the generic scroll target through a non-executable data attribute.');
	$assert(strpos($eventPlansSource, 'data-vms-lazy-loading-label=') !== false, 'Event Plan source should keep the non-executable shell-label handoff on the stable basic-grid wrapper.');
	$assert(strpos($eventPlansSource, 'data-vms-lazy-error-label=') !== false, 'Event Plan source should keep the non-executable shell error-label handoff on the stable basic-grid wrapper.');
	$assert(substr_count($eventPlansSource, '<script') >= 5, 'This slice should not remove unrelated live Event Plan inline script blocks.');

	$assert(is_dir($partialsDir), 'Event Plan partial directory should still exist.');
	$partialScrollHits = array();
	$partialIterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($partialsDir, FilesystemIterator::SKIP_DOTS)
	);
	foreach ($partialIterator as $fileInfo) {
		if (!$fileInfo instanceof SplFileInfo || !$fileInfo->isFile() || $fileInfo->getExtension() !== 'php') {
			continue;
		}

		$contents = file_get_contents($fileInfo->getPathname());
		if (!is_string($contents) || strpos($contents, 'data-vms-scroll-target') === false) {
			continue;
		}

		$partialScrollHits[] = $fileInfo->getFilename();
	}
	$assert($partialScrollHits === array(), 'No Event Plan partial should own the generic scroll marker. Found: ' . implode(', ', $partialScrollHits));

	$assert(file_exists($shellAssetPath), 'The new Event Plan shell asset should exist.');
	$assert(strpos($shellAssetSource, "var root = document.querySelector('.vms-ep-basic-grid[data-vms-scroll-target]');") !== false, 'The shell asset should read the generic scroll marker from the stable Event Plan wrapper.');
	$assert(strpos($shellAssetSource, "var targetId = String(root.getAttribute('data-vms-scroll-target') || '').trim();") !== false, 'The shell asset should read the same target ID from the data attribute.');
	$assert(strpos($shellAssetSource, 'document.getElementById(targetId)') !== false, 'The shell asset should treat the target strictly as an element ID.');
	$assert(strpos($shellAssetSource, 'if (!root) return;') !== false, 'The shell asset should safely no-op when the Event Plan wrapper is missing.');
	$assert(strpos($shellAssetSource, 'if (!targetId) return;') !== false, 'The shell asset should safely no-op when no server scroll target was requested.');
	$assert(strpos($shellAssetSource, 'if (!target) return;') !== false, 'The shell asset should safely no-op when the target element does not exist.');
	$assert(strpos($shellAssetSource, 'window.setTimeout(function () {') !== false && strpos($shellAssetSource, '}, 150);') !== false, 'The shell asset should preserve the existing deferred scroll timing.');
	$assert(strpos($shellAssetSource, "target.scrollIntoView({ behavior: 'smooth', block: 'start' });") !== false, 'The shell asset should preserve the existing scroll options.');
	$assert(strpos($shellAssetSource, 'window.vmsEventPlanInitCollapsibleSection = initExistingSection;') !== false, 'The shell asset should also own the migrated collapsible-section helper.');
	$assert(strpos($shellAssetSource, 'focus(') === false, 'The generic scroll shell asset should not introduce focus behavior.');
	foreach (array('ajaxurl', 'XMLHttpRequest', 'wp.apiFetch') as $forbiddenToken) {
		$assert(strpos($shellAssetSource, $forbiddenToken) === false, 'The shell asset should remain passive and generic: ' . $forbiddenToken);
	}
	$assert(strpos($shellAssetSource, 'maybeFocusEventPlanTicketingArea') === false, 'The shell asset should not absorb the ticketing-specific focus helper.');

	$assert(strpos($adminUiAssetsSource, "'vms-event-plan-shell'") !== false, 'Admin UI assets should enqueue the new Event Plan shell asset.');
	$assert(strpos($adminUiAssetsSource, "VMS_PLUGIN_URL . 'assets/js/vms-event-plan-shell.js'") !== false, 'Admin UI assets should point the shell handle to assets/js/vms-event-plan-shell.js.');
	$assert(strpos($adminUiAssetsSource, "in_array((string) \$screen->base, array('post', 'post-new'), true)") !== false, 'The shell asset should stay scoped to post and post-new screens.');
	$assert(strpos($adminUiAssetsSource, "(string) (\$screen->post_type ?? '') === 'vms_event_plan'") !== false, 'The shell asset should stay scoped to Event Plan edit/new screens.');

	$assert(strpos($ticketingAssetSource, 'function maybeFocusEventPlanTicketingArea()') !== false, 'The migrated ticketing-focus helper should remain separately owned by admin-ticketing.js.');
	$assert(strpos($ticketingAssetSource, "new URL(window.location.href).searchParams.get('vms_ep_load_section')") !== false, 'The ticketing-focus helper should remain separate from the generic shell asset.');

	fwrite(STDOUT, "event plan generic scroll inline js remediation: PASS\n");
} catch (Throwable $e) {
	fwrite(STDERR, 'event plan generic scroll inline js remediation: FAIL - ' . $e->getMessage() . "\n");
	exit(1);
}
