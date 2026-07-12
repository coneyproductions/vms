<?php
declare(strict_types=1);

$pluginRoot = dirname(__DIR__);
$eventPlansPath = $pluginRoot . '/includes/cpt/event-plans.php';
$ticketingBootstrapPath = $pluginRoot . '/includes/integrations/ticketing.php';
$ticketingAssetPath = $pluginRoot . '/assets/admin-ticketing.js';
$shellAssetPath = $pluginRoot . '/assets/js/vms-event-plan-shell.js';
$unexpectedAssetPath = $pluginRoot . '/assets/js/vms-event-plan-ticketing-focus.js';

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
	$ticketingBootstrapSource = $readFile($ticketingBootstrapPath);
	$ticketingAssetSource = $readFile($ticketingAssetPath);
	$shellAssetSource = $readFile($shellAssetPath);

	$assert(strpos($eventPlansSource, '$ticketing_focus_requested') === false, 'Event Plan source should no longer define the inline ticketing-focus request variable.');
	$assert(strpos($eventPlansSource, "const ticketingBox = document.getElementById('vms_event_plan_ticketing_v2');") === false, 'Event Plan source should no longer emit the inline ticketing-focus helper.');
	$assert(strpos($eventPlansSource, "const focusTarget = ticketingBox.querySelector('#vms-ticketing-v2-source .button, #vms-ticketing-v2-source select, #vms-ticketing-v2-source input, #vms-ticketing-v2-source textarea, #vms-ticketing-v2-source a');") === false, 'Event Plan source should no longer emit the inline ticketing focus target query.');
	$assert(strpos($eventPlansSource, "get_post_meta(\$post->ID, '_vms_admin_scroll_to', true);") !== false, 'Generic server-requested scroll helper should remain present.');
	$assert(strpos($eventPlansSource, "delete_post_meta(\$post->ID, '_vms_admin_scroll_to');") !== false, 'Generic server-requested scroll helper should still clear the scroll marker.');
	$assert(strpos($eventPlansSource, 'Generic scroll helper migrated to vms-event-plan-shell.js:') === false, 'Event Plan source should not retain the obsolete generic-scroll migration marker comment.');
	$assert(strpos($eventPlansSource, 'data-vms-scroll-target=') !== false, 'Generic server-requested scroll helper should hand off its target through the non-executable scroll marker.');
	$assert(strpos($eventPlansSource, "'vms_event_plan_ticketing_v2'") !== false, 'Event Plan ticketing meta box target should remain present.');

	$assert(strpos($ticketingAssetSource, 'function maybeFocusEventPlanTicketingArea()') !== false, 'admin-ticketing.js should contain the migrated ticketing-focus helper.');
	$assert(strpos($ticketingAssetSource, "new URL(window.location.href).searchParams.get('vms_ep_load_section')") !== false, 'admin-ticketing.js should read the existing requested-section query parameter directly.');
	$assert(strpos($ticketingAssetSource, "requestedSection !== 'ticketing_v2'") !== false, 'admin-ticketing.js should keep ordinary Event Plan loads from triggering ticketing focus.');
	$assert(strpos($ticketingAssetSource, "document.getElementById('vms_event_plan_ticketing_v2')") !== false, 'admin-ticketing.js should target the existing Event Plan ticketing wrapper.');
	$assert(strpos($ticketingAssetSource, "ticketingBox.dataset.vmsTicketingFocusHandled === '1'") !== false, 'admin-ticketing.js should guard against duplicate ticketing-focus execution.');
	$assert(strpos($ticketingAssetSource, "ticketingBox.querySelector('#vms-ticketing-v2-source .button, #vms-ticketing-v2-source select, #vms-ticketing-v2-source input, #vms-ticketing-v2-source textarea, #vms-ticketing-v2-source a')") !== false, 'admin-ticketing.js should preserve the existing ticketing focus target selector.');
	$assert(strpos($ticketingAssetSource, "ticketingBox.scrollIntoView({ behavior: 'smooth', block: 'start' })") !== false, 'admin-ticketing.js should preserve the passive ticketing scroll behavior.');
	$assert(strpos($ticketingAssetSource, "focusTarget.focus({ preventScroll: true })") !== false, 'admin-ticketing.js should preserve the deferred focus behavior.');
	$assert(strpos($ticketingAssetSource, "document.addEventListener('DOMContentLoaded', maybeFocusEventPlanTicketingArea, { once: true });") !== false, 'admin-ticketing.js should initialize safely when DOM readiness is still pending.');
	$assert(strpos($ticketingAssetSource, 'maybeFocusEventPlanTicketingArea();') !== false, 'admin-ticketing.js should run the migrated helper immediately when the DOM is already ready.');

	$helperMatches = array();
	preg_match('/function maybeFocusEventPlanTicketingArea\\(\\) \\{([\\s\\S]*?)\\n  \\}/', $ticketingAssetSource, $helperMatches);
	$helperBody = isset($helperMatches[1]) ? (string) $helperMatches[1] : '';
	$assert($helperBody !== '', 'Expected to capture the migrated ticketing-focus helper body.');
	foreach (array('fetch(', 'ajaxurl', 'XMLHttpRequest', 'wp.apiFetch', 'submit(', 'vms_save_', 'cart') as $forbiddenToken) {
		$assert(strpos($helperBody, $forbiddenToken) === false, 'Migrated ticketing-focus helper should remain passive and must not add data-mutation behavior: ' . $forbiddenToken);
	}

	$assert(strpos($ticketingBootstrapSource, '$handle = \'vms-admin-ticketing\';') !== false, 'Ticketing bootstrap should retain the existing admin-ticketing handle.');
	$assert(strpos($ticketingBootstrapSource, 'assets/admin-ticketing.js') !== false, 'Ticketing bootstrap should still enqueue assets/admin-ticketing.js.');
	$assert(strpos($ticketingBootstrapSource, "if (!in_array(\$hook, array('post.php', 'post-new.php'), true)) {") !== false, 'admin-ticketing.js should remain enqueued on post editor screens, including Event Plan edit/new.');
	$assert(strpos($shellAssetSource, "var root = document.querySelector('.vms-ep-basic-grid[data-vms-scroll-target]');") !== false, 'The separate generic scroll shell asset should remain present.');
	$assert(strpos($shellAssetSource, 'document.getElementById(targetId)') !== false, 'The generic scroll shell asset should continue treating the marker as an element ID.');
	$assert(strpos($shellAssetSource, 'window.vmsEventPlanPersistRequestedSection = persistRequestedSection;') !== false, 'The separate shell asset should own the generic requested-section helper.');

	$assert(!file_exists($unexpectedAssetPath), 'This remediation slice should not create a new dedicated Event Plan ticketing-focus asset.');

	fwrite(STDOUT, "event plan ticketing focus inline js remediation: PASS\n");
} catch (Throwable $e) {
	fwrite(STDERR, 'event plan ticketing focus inline js remediation: FAIL - ' . $e->getMessage() . "\n");
	exit(1);
}
