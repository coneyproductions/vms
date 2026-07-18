<?php
declare(strict_types=1);

$pluginRoot = dirname(__DIR__);
$livePluginRoot = dirname(dirname($pluginRoot)) . '/vms';

$staffPath = $pluginRoot . '/includes/modules/staff-tasks/admin-ui.php';
$addPath = $pluginRoot . '/includes/modules/availability-date-dispatch/admin-ui.php';
$publicPath = $pluginRoot . '/includes/modules/availability-date-dispatch/public.php';
$staffAssetPath = $pluginRoot . '/assets/js/vms-tasks-admin-pages.js';
$addAssetPath = $pluginRoot . '/assets/js/vms-add-dispatch-admin.js';
$eventPlanMetaboxAssetPath = $pluginRoot . '/assets/js/vms-tasks-event-plan-metabox.js';
$liveStaffPath = $livePluginRoot . '/includes/modules/staff-tasks/admin-ui.php';
$liveAddPath = $livePluginRoot . '/includes/modules/availability-date-dispatch/admin-ui.php';
$liveStaffAssetPath = $livePluginRoot . '/assets/js/vms-tasks-admin-pages.js';
$liveAddAssetPath = $livePluginRoot . '/assets/js/vms-add-dispatch-admin.js';

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

$currentRepoStatusPaths = static function () use ($assert): array {
	$output = array();
	$exitCode = 0;
	exec('git status --short --untracked-files=all -- . 2>&1', $output, $exitCode);
	$assert($exitCode === 0, 'git status --short should succeed for the focused diff check.');

	$paths = array();
	foreach ($output as $line) {
		$line = rtrim($line);
		if ($line === '') {
			continue;
		}

		$path = trim(substr($line, 3));
		if (strpos($path, ' -> ') !== false) {
			$parts = explode(' -> ', $path);
			$path = (string) end($parts);
		}
		$paths[] = $path;
	}

	sort($paths);
	return $paths;
};

$extractFunctionSource = static function (string $source, string $functionName) use ($assert): string {
	$tokens = token_get_all($source);
	$capturing = false;
	$seenName = false;
	$braceDepth = 0;
	$buffer = '';

	foreach ($tokens as $token) {
		$text = is_array($token) ? $token[1] : $token;

		if (!$capturing) {
			if (is_array($token) && $token[0] === T_FUNCTION) {
				$capturing = true;
				$seenName = false;
				$braceDepth = 0;
				$buffer = $text;
			}
			continue;
		}

		$buffer .= $text;
		if (!$seenName) {
			if ($text === '(') {
				$capturing = false;
				$buffer = '';
				continue;
			}
			if (is_array($token) && $token[0] === T_STRING) {
				if ($token[1] !== $functionName) {
					$capturing = false;
					$buffer = '';
					continue;
				}
				$seenName = true;
			}
			continue;
		}

		if ($text === '{') {
			$braceDepth++;
			continue;
		}

		if ($text === '}') {
			$braceDepth--;
			if ($braceDepth === 0) {
				return $buffer;
			}
		}
	}

	$assert(false, 'Unable to extract function source for ' . $functionName . '.');
	return '';
};

try {
	$staffSource = $readFile($staffPath);
	$addSource = $readFile($addPath);
	$publicSource = $readFile($publicPath);
	$staffAssetSource = $readFile($staffAssetPath);
	$addAssetSource = $readFile($addAssetPath);
	$liveStaffSource = $readFile($liveStaffPath);
	$liveAddSource = $readFile($liveAddPath);
	$liveStaffAssetSource = $readFile($liveStaffAssetPath);
	$liveAddAssetSource = $readFile($liveAddAssetPath);

	$tasksPageSource = $extractFunctionSource($staffSource, 'vms_tasks_render_tasks_page');
	$checklistsPageSource = $extractFunctionSource($staffSource, 'vms_tasks_render_checklist_templates_page');
	$staffEnqueueSource = $extractFunctionSource($staffSource, 'vms_tasks_admin_enqueue_page_assets');
	$staffAssetPagesSource = $extractFunctionSource($staffSource, 'vms_tasks_admin_page_asset_pages');
	$addEnqueueSource = $extractFunctionSource($addSource, 'vms_add_dispatch_enqueue_admin_assets');
	$requestBuilderSource = $extractFunctionSource($addSource, 'vms_add_dispatch_render_request_builder');
	$menuBadgeCssSource = $extractFunctionSource($addSource, 'vms_add_dispatch_render_menu_badge_css');
	$menuBadgeJsSource = $extractFunctionSource($addSource, 'vms_add_dispatch_render_menu_badge_js');

	$assert(strpos($tasksPageSource, '<script') === false, 'Staff Tasks page renderer should no longer emit the executable create-task inline <script>.');
	$assert(strpos($tasksPageSource, 'document.getElementById("vms_tasks_one_off_event")') === false, 'Staff Tasks page renderer should no longer own the create-task DOM controller.');
	$assert(strpos($checklistsPageSource, '<script') === false, 'Checklist Templates page renderer should no longer emit the executable scope inline <script>.');
	$assert(strpos($checklistsPageSource, 'syncChecklistContext') === false, 'Checklist Templates page renderer should no longer own the scope-change controller.');
	$assert(strpos($staffSource, 'wp_add_inline_script') === false, 'Staff Tasks admin UI should not reintroduce the helpers through wp_add_inline_script().');

	$assert(strpos($staffSource, 'function vms_tasks_admin_page_asset_pages(): array') !== false, 'Staff Tasks should declare the exact page list helper for the new asset gate.');
	$assert(strpos($staffAssetPagesSource, "'vms-tasks'") !== false, 'Staff Tasks asset page list should include the Tasks page.');
	$assert(strpos($staffAssetPagesSource, "'vms-checklist-templates'") !== false, 'Staff Tasks asset page list should include the Checklist Templates page.');
	$assert(strpos($staffAssetPagesSource, "'vms-task-templates'") === false, 'Staff Tasks asset page list should stay limited to the two targeted pages.');
	$assert(strpos($staffEnqueueSource, "!in_array(\$page, vms_tasks_admin_page_asset_pages(), true)") !== false, 'Staff Tasks enqueue should bail unless the current page matches the exact targeted page list.');
	$assert(strpos($staffEnqueueSource, 'vms_tasks_current_user_can_manage_all()') !== false, 'Staff Tasks enqueue should preserve the manage-all capability boundary.');
	$assert(strpos($staffEnqueueSource, "VMS_PLUGIN_URL . 'assets/js/vms-tasks-admin-pages.js'") !== false, 'Staff Tasks enqueue should point to the external admin-pages asset.');
	$assert(strpos($staffSource, "add_action('admin_enqueue_scripts', 'vms_tasks_admin_enqueue_page_assets', 60);") !== false, 'Staff Tasks should register the page-scoped enqueue callback.');

	$assert(
		preg_match(
			'~add_submenu_page\(\s*\$parent,\s*__\(\'Tasks\', \'backstage-venue-manager\'\),\s*__\(\'Tasks\', \'backstage-venue-manager\'\),\s*\$menu_cap,\s*\'vms-tasks\',\s*\'vms_tasks_render_tasks_page\'\s*\);~s',
			$staffSource
		) === 1,
		'Staff Tasks should preserve the Tasks page registration, capability variable, slug, and callback.'
	);
	$assert(
		preg_match(
			'~add_submenu_page\(\s*\$parent,\s*__\(\'Checklist Templates\', \'backstage-venue-manager\'\),\s*__\(\'Checklist Templates\', \'backstage-venue-manager\'\),\s*\$menu_cap,\s*\'vms-checklist-templates\',\s*\'vms_tasks_render_checklist_templates_page\'\s*\);~s',
			$staffSource
		) === 1,
		'Staff Tasks should preserve the Checklist Templates page registration, capability variable, slug, and callback.'
	);

	foreach (array(
		'id="vms_tasks_one_off_event"',
		'id="vms_tasks_create_venue_row"',
		'id="vms_tasks_one_off_assignment_mode"',
		'id="vms_tasks_one_off_assignment_scheduled"',
		'id="vms_tasks_one_off_repeatable_checklist"',
		'data-scope="',
		'id="vms_tasks_one_off_recurrence_pattern"',
		'id="vms_tasks_one_off_recurrence_n_days"',
		'id="vms_tasks_one_off_recurrence_note"',
		'id="vms_tasks_checklist_scope"',
		'id="vms_tasks_checklist_apply_mode_row"',
		'id="vms_tasks_checklist_venue_row"',
		'id="vms_tasks_checklist_event_type_row"',
		'id="vms_tasks_apply_mode"',
	) as $requiredStaffMarkupMarker) {
		$assert(strpos($staffSource, $requiredStaffMarkupMarker) !== false, 'Staff Tasks should preserve the existing markup selector contract: ' . $requiredStaffMarkupMarker);
	}

	$assert(file_exists($staffAssetPath), 'Staff Tasks external admin-pages asset should exist.');
	foreach (array(
		'function initTasksPage()',
		'function initChecklistTemplatesPage()',
		"document.getElementById('vms_tasks_one_off_event')",
		"document.getElementById('vms_tasks_create_venue_row')",
		"document.getElementById('vms_tasks_one_off_assignment_mode')",
		"document.getElementById('vms_tasks_one_off_assignment_scheduled')",
		"document.getElementById('vms_tasks_one_off_repeatable_checklist')",
		"document.getElementById('vms_tasks_one_off_recurrence_pattern')",
		"document.getElementById('vms_tasks_one_off_recurrence_n_days')",
		"document.getElementById('vms_tasks_one_off_recurrence_note')",
		"eventSelect.dataset.vmsTasksCreateBound === '1'",
		"eventSelect.dataset.vmsTasksCreateBound = '1';",
		"scopeSelect.dataset.vmsTasksChecklistBound === '1'",
		"scopeSelect.dataset.vmsTasksChecklistBound = '1';",
		'if (!eventSelect || !venueRow || !assignmentMode || !scheduledOption || !checklistSelect || !recurrencePattern || !recurrenceNDays || !recurrenceNote) {',
		'if (!scopeSelect || !applyModeRow || !venueRow || !eventTypeRow || !applyModeSelect) {',
		"var hasEvent = parseInt(eventSelect.value || '0', 10) > 0;",
		"var scope = hasEvent ? 'event' : 'general';",
		"scheduledOption.hidden = !hasEvent;",
		"recurrencePattern.disabled = hasEvent;",
		"recurrenceNDays.disabled = hasEvent;",
		"recurrencePattern.value = 'none';",
		"recurrenceNDays.style.display = recurrencePattern.value === 'every_n_days' && !hasEvent ? '' : 'none';",
		"optionScope = option.getAttribute('data-scope');",
		"option.hidden = optionScope !== scope;",
		"checklistSelect.value = '0';",
		"scopeSelect.value === 'general'",
		"applyModeSelect.value = 'default_all_events';",
	) as $requiredStaffAssetMarker) {
		$assert(strpos($staffAssetSource, $requiredStaffAssetMarker) !== false, 'Staff Tasks asset should preserve the migrated behavior marker: ' . $requiredStaffAssetMarker);
	}

	$assert(file_exists($eventPlanMetaboxAssetPath), 'Existing Staff Tasks Event Plan metabox asset should remain present.');
	$assert(strpos($staffSource, "'vms-tasks-event-plan-metabox'") !== false, 'Staff Tasks admin UI should still enqueue the Event Plan metabox asset.');
	$assert(strpos($staffSource, 'assets/js/vms-tasks-event-plan-metabox.js') !== false, 'Staff Tasks admin UI should still point to the Event Plan metabox asset.');

	$assert(strpos($requestBuilderSource, '<script') === false, 'ADD request builder should no longer emit the executable recipient-review inline <script>.');
	$assert(strpos($requestBuilderSource, 'document.currentScript.previousElementSibling') === false, 'ADD request builder should no longer own the inline currentScript bootstrap.');
	$assert(strpos($addSource, 'wp_add_inline_script') === false, 'ADD admin UI should not reintroduce the request-builder helper through wp_add_inline_script().');

	$assert(strpos($addEnqueueSource, "\$page !== vms_add_dispatch_page_slug() && !\$is_event_plan") !== false, 'ADD admin enqueue should preserve the existing page-or-Event-Plan style gate.');
	$assert(strpos($addEnqueueSource, "VMS_PLUGIN_URL . 'assets/css/vms-add-dispatch-admin.css'") !== false, 'ADD admin enqueue should preserve the existing stylesheet ownership.');
	$assert(strpos($addEnqueueSource, "\$page === vms_add_dispatch_page_slug() && current_user_can('manage_options')") !== false, 'ADD request-builder asset should load only on the exact ADD page under the existing capability boundary.');
	$assert(strpos($addEnqueueSource, "VMS_PLUGIN_URL . 'assets/js/vms-add-dispatch-admin.js'") !== false, 'ADD admin enqueue should point to the external request-builder asset.');
	$assert(
		preg_match(
			'~add_submenu_page\(\s*\'vms-dashboard\',\s*__\(\'ADD — Availability & Date Dispatch\', \'backstage-venue-manager\'\),\s*__\(\'ADD Dispatch\', \'backstage-venue-manager\'\),\s*\'manage_options\',\s*vms_add_dispatch_page_slug\(\),\s*\'vms_add_dispatch_render_admin_page\'\s*\);~s',
			$addSource
		) === 1,
		'ADD page registration should preserve the existing parent, labels, capability, slug helper, and callback.'
	);

	foreach (array(
		'data-vms-add-eligible-count',
		'data-vms-add-selected-count',
		'data-vms-add-select-all',
		'data-vms-add-clear-all',
		'data-vms-add-send-button',
		"'class' => 'vms-add-recipient-row'",
		'class="vms-add-recipient-checkbox"',
		'data-vms-add-state',
		'data-vms-add-contactable',
		'data-vms-add-base-selectable',
		'data-vms-add-previously-contacted',
		'data-vms-add-default-detail',
		'data-vms-add-no-email-detail',
		'data-vms-add-no-response-detail',
		'data-vms-add-tentative-detail',
		'data-vms-add-previous-detail',
		'name="include_unknown"',
		'name="include_no_response"',
		'name="include_tentative"',
		'name="include_previously_contacted"',
		'data-vms-add-decision-label',
		'data-vms-add-decision-detail',
	) as $requiredAddMarkupMarker) {
		$assert(strpos($requestBuilderSource, $requiredAddMarkupMarker) !== false, 'ADD request builder should preserve the existing selector/data contract: ' . $requiredAddMarkupMarker);
	}

	$assert(file_exists($addAssetPath), 'ADD external request-builder asset should exist.');
	foreach (array(
		"document.querySelector('[data-vms-add-send-button]')",
		'root = sendButton.form;',
		"root.dataset.vmsAddDispatchBound === '1'",
		"root.dataset.vmsAddDispatchBound = '1';",
		"filterForm = root.previousElementSibling;",
		"var field = filterForm ? filterForm.querySelector('[name=\"' + name + '\"]') : null;",
		"root.querySelectorAll('input[type=\"hidden\"][name=\"' + name + '\"]')",
		"row.querySelector('[data-vms-add-decision-detail]')",
		"row.querySelector('[data-vms-add-decision-label]')",
		"row.getAttribute('data-vms-add-contactable') !== '1'",
		"row.getAttribute('data-vms-add-base-selectable') !== '1'",
		"state === 'no-response' && !filterChecked('include_no_response')",
		"state === 'tentative' && !filterChecked('include_tentative')",
		"row.getAttribute('data-vms-add-previously-contacted') === '1' && !filterChecked('include_previously_contacted')",
		"root.querySelectorAll('.vms-add-recipient-row')",
		"root.querySelectorAll('.vms-add-recipient-checkbox')",
		"sendButton.disabled = selected <= 0;",
		"syncHidden('include_no_response', filterChecked('include_no_response'));",
		"syncHidden('include_unknown', filterChecked('include_no_response'));",
		"syncHidden('include_tentative', filterChecked('include_tentative'));",
		"syncHidden('include_previously_contacted', filterChecked('include_previously_contacted'));",
		"checkboxNodes[i].checked = true;",
		"checkboxNodes[i].checked = false;",
		"fields[i].addEventListener('change', update);",
		'if (!sendButton) {',
		'if (!root) {',
	) as $requiredAddAssetMarker) {
		$assert(strpos($addAssetSource, $requiredAddAssetMarker) !== false, 'ADD asset should preserve the migrated behavior marker: ' . $requiredAddAssetMarker);
	}

	$assert(strpos($menuBadgeCssSource, '<style>') !== false, 'ADD menu-badge CSS emitter should remain present.');
	$assert(strpos($menuBadgeCssSource, '#adminmenu .vms-add-dispatch-alert-badge') !== false, 'ADD menu-badge CSS emitter should preserve the existing badge selector.');
	$assert(strpos($menuBadgeJsSource, '<script>(function(){var markup=') !== false, 'ADD menu-badge JS emitter should remain inline for the separate WPORG-22R-I slice.');
	$assert(strpos($menuBadgeJsSource, 'applyBadge("#toplevel_page_vms-dashboard > a .wp-menu-name")') !== false, 'ADD menu-badge JS emitter should preserve the existing top-level badge selector.');
	$assert(strpos($menuBadgeJsSource, 'applyBadge("#toplevel_page_vms-dashboard .wp-submenu li a[href*=\\"page=vms-add-dispatch\\"]")') !== false, 'ADD menu-badge JS emitter should preserve the existing submenu badge selector.');
	$assert(strpos($menuBadgeJsSource, 'document.readyState==="loading"') !== false, 'ADD menu-badge JS emitter should preserve the existing DOM-ready branch.');

	$assert(strpos($publicSource, 'function vms_add_dispatch_render_public_shell(string $headline, string $content_html): void') !== false, 'ADD public shell should remain present and untouched in this slice.');

	$assert($staffSource === $liveStaffSource, 'Mirror and live Staff Tasks PHP should remain byte-for-byte synchronized.');
	$assert($addSource === $liveAddSource, 'Mirror and live ADD admin PHP should remain byte-for-byte synchronized.');
	$assert($staffAssetSource === $liveStaffAssetSource, 'Mirror and live Staff Tasks JS should remain byte-for-byte synchronized.');
	$assert($addAssetSource === $liveAddAssetSource, 'Mirror and live ADD JS should remain byte-for-byte synchronized.');

	$expectedChangedPaths = array(
		'assets/js/vms-add-dispatch-admin.js',
		'assets/js/vms-tasks-admin-pages.js',
		'docs/WPORG_PREREVIEW_REMEDIATION.md',
		'docs/wporg-remediation-ledger.md',
		'includes/modules/availability-date-dispatch/admin-ui.php',
		'includes/modules/staff-tasks/admin-ui.php',
		'tests/wporg-22r-module-admin-helpers-inline-js-remediation.php',
	);
	sort($expectedChangedPaths);
	$currentPaths = $currentRepoStatusPaths();
	$assert($currentPaths === $expectedChangedPaths, 'Only the seven authorized mirror-repository files should be changed in the remediation diff.');
	$assert(!in_array('includes/modules/availability-date-dispatch/public.php', $currentPaths, true), 'ADD public shell runtime should remain untouched in this slice.');
	$assert(!in_array('assets/js/vms-tasks-event-plan-metabox.js', $currentPaths, true), 'Event Plan metabox asset should remain untouched in this slice.');

	fwrite(STDOUT, "wporg 22r module admin helpers inline js remediation: PASS\n");
} catch (Throwable $e) {
	fwrite(STDERR, 'wporg 22r module admin helpers inline js remediation: FAIL - ' . $e->getMessage() . "\n");
	exit(1);
}
