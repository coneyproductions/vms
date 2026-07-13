<?php
declare(strict_types=1);

$pluginRoot = dirname(__DIR__);
$eventPlansPath = $pluginRoot . '/includes/cpt/event-plans.php';
$adminUiAssetsPath = $pluginRoot . '/includes/admin-ui/assets.php';
$shellAssetPath = $pluginRoot . '/assets/js/vms-event-plan-shell.js';
$staffAssetPath = $pluginRoot . '/assets/js/vms-event-plan-staff.js';
$ticketingAssetPath = $pluginRoot . '/assets/admin-ticketing.js';
$unexpectedAssetPath = $pluginRoot . '/assets/js/vms-event-plan-controller.js';

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
	$staffAssetSource = $readFile($staffAssetPath);
	$ticketingAssetSource = $readFile($ticketingAssetPath);

	foreach (array(
		'window.vmsEventPlanInitCollapsibleSection = initExistingSection;',
		'window.vmsEventPlanInitCollapsibleSections = initCollapsibleSections;',
		'window.vmsEventPlanPersistRequestedSection = persistRequestedSection;',
		'window.vmsEventPlanRevealRequestedSection = revealRequestedSection;',
		"const stateKey = 'vms_ep_sections_state_' + String(postId || 'new');",
		"params.set('action', 'vms_load_event_plan_admin_section');",
		"<?php echo esc_js(__('Loading section editor…', 'backstage-venue-manager')); ?>",
		"<?php echo esc_js(__('Unable to load this editor section right now. Refresh and try again.', 'backstage-venue-manager')); ?>",
	) as $removedInlineMarker) {
		$assert(strpos($eventPlansSource, $removedInlineMarker) === false, 'Event Plan PHP should no longer own the migrated shell-controller marker: ' . $removedInlineMarker);
	}

	foreach (array(
		'window.vmsEventPlanInitCollapsibleSection = initExistingSection;',
		'window.vmsEventPlanInitCollapsibleSections = initCollapsibleSections;',
		'window.vmsEventPlanPersistRequestedSection = persistRequestedSection;',
		'window.vmsEventPlanRevealRequestedSection = revealRequestedSection;',
		'vms_ep_sections_state_',
		'vms_ep_load_section',
		'vms_load_event_plan_admin_section',
		'secondary_vendors',
		'staff',
		'readiness_details',
		'.vms-ep-basic-grid[data-vms-scroll-target]',
		'data-vms-lazy-loading-label',
		'data-vms-lazy-error-label',
		'form.dataset.vmsCollapseDelegatedBound',
		'window.fetch(',
	) as $requiredShellMarker) {
		$assert(strpos($shellAssetSource, $requiredShellMarker) !== false, 'Shell asset should own the migrated shell-controller marker: ' . $requiredShellMarker);
	}

	$assert(strpos($eventPlansSource, 'data-vms-lazy-loading-label=') !== false, 'Event Plan PHP should provide the translated lazy-load loading label through a non-executable data attribute.');
	$assert(strpos($eventPlansSource, 'data-vms-lazy-error-label=') !== false, 'Event Plan PHP should provide the translated lazy-load error label through a non-executable data attribute.');
	$assert(strpos($shellAssetSource, 'maybeFocusEventPlanTicketingArea') === false, 'Shell asset should not absorb the ticketing-specific focus helper.');
	$assert(strpos($ticketingAssetSource, 'function maybeFocusEventPlanTicketingArea()') !== false, 'admin-ticketing.js should retain the ticketing-specific focus helper.');
	$assert(strpos($adminUiAssetsSource, "'vms-event-plan-shell'") !== false, 'Admin UI assets should retain the existing Event Plan shell handle.');
	$assert(strpos($adminUiAssetsSource, "VMS_PLUGIN_URL . 'assets/js/vms-event-plan-shell.js'") !== false, 'Admin UI assets should still point the shell handle at assets/js/vms-event-plan-shell.js.');
	$assert(strpos($adminUiAssetsSource, "in_array((string) \$screen->base, array('post', 'post-new'), true)") !== false, 'Shell asset should remain restricted to post and post-new screens.');
	$assert(strpos($adminUiAssetsSource, "(string) (\$screen->post_type ?? '') === 'vms_event_plan'") !== false, 'Shell asset should remain restricted to Event Plan edit/new screens.');
	$assert(strpos($eventPlansSource, 'window.vmsEventPlanInitStaff = initStaff;') === false, 'Staff controller ownership should move out of Event Plan PHP in this slice.');
	$assert(strpos($staffAssetSource, 'window.vmsEventPlanInitStaff = initStaff;') !== false, 'Dedicated staff asset should now own the public staff initializer.');
	$assert(strpos($eventPlansSource, 'window.vmsEventPlanInitSecondaryVendors = initSecondaryVendors;') !== false, 'Secondary Vendors should remain an active inline controller after the shell migration.');
	$assert(substr_count($eventPlansSource, '<script') >= 6, 'B1 should still have other active Event Plan inline script blocks after this shell-only slice.');
	$assert(!file_exists($unexpectedAssetPath), 'This slice should not create a second Event Plan shell/controller asset.');

	fwrite(STDOUT, "event plan shell controller inline js remediation: PASS\n");
} catch (Throwable $e) {
	fwrite(STDERR, 'event plan shell controller inline js remediation: FAIL - ' . $e->getMessage() . "\n");
	exit(1);
}
