<?php
declare(strict_types=1);

$pluginRoot = dirname(__DIR__);
$livePluginRoot = dirname(dirname($pluginRoot)) . '/vms';

$pagePath = $pluginRoot . '/includes/admin/data-tools/page-event-plan-import.php';
$assetPath = $pluginRoot . '/assets/js/vms-event-plan-import.js';
$shellPath = $pluginRoot . '/includes/admin-ui/shell.php';
$actionsPath = $pluginRoot . '/includes/admin/data-tools/actions-event-plan-import.php';
$enginePath = $pluginRoot . '/includes/services/event-plan-import/event-plan-import-engine.php';
$rowsPayloadTestPath = $pluginRoot . '/tests/event-plan-import-rows-payload-output-remediation.php';
$noticeTestPath = $pluginRoot . '/tests/administrator-explicit-notice-output-remediation.php';
$uploadApiTestPath = $pluginRoot . '/tests/event-plan-import-upload-api-remediation.php';
$corePluginPath = $pluginRoot . '/includes/core/plugin.php';
$adminUiAssetsPath = $pluginRoot . '/includes/admin-ui/assets.php';

$livePagePath = $livePluginRoot . '/includes/admin/data-tools/page-event-plan-import.php';
$liveShellPath = $livePluginRoot . '/includes/admin-ui/shell.php';
$liveAssetPath = $livePluginRoot . '/assets/js/vms-event-plan-import.js';

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
	$pageSource = $readFile($pagePath);
	$assetSource = $readFile($assetPath);
	$shellSource = $readFile($shellPath);
	$actionsSource = $readFile($actionsPath);
	$engineSource = $readFile($enginePath);
	$rowsPayloadTestSource = $readFile($rowsPayloadTestPath);
	$noticeTestSource = $readFile($noticeTestPath);
	$uploadApiTestSource = $readFile($uploadApiTestPath);
	$corePluginSource = $readFile($corePluginPath);
	$adminUiAssetsSource = $readFile($adminUiAssetsPath);
	$livePageSource = $readFile($livePagePath);
	$liveShellSource = $readFile($liveShellPath);
	$liveAssetSource = $readFile($liveAssetPath);

	$assert(strpos($pageSource, '<script') === false, 'Event Plan Import PHP should no longer emit an executable commit-selection <script> block.');
	$assert(strpos($pageSource, 'window.alert(') === false, 'Event Plan Import PHP should no longer contain the inline submit guard alert.');
	$assert(strpos($pageSource, 'wp_add_inline_script') === false, 'Event Plan Import PHP should not reintroduce executable commit-selection behavior through wp_add_inline_script().');

	$assert(strpos($pageSource, 'function vms_event_plan_import_page_slug(): string') !== false, 'Event Plan Import should declare a dedicated page-slug helper for the asset gate.');
	$assert(strpos($pageSource, "return 'vms-import-event-plans';") !== false, 'Event Plan Import should retain the exact hidden page slug.');
	$assert(strpos($pageSource, 'function vms_event_plan_import_enqueue_assets(): void') !== false, 'Event Plan Import should declare a dedicated admin_enqueue_scripts callback.');
	$assert(strpos($pageSource, "add_action('admin_enqueue_scripts', 'vms_event_plan_import_enqueue_assets', 50);") !== false, 'Event Plan Import should register the page-scoped enqueue callback.');
	$assert(preg_match('~\$page\s*!==\s*vms_event_plan_import_page_slug\(\)~', $pageSource) === 1, 'Event Plan Import enqueue should bail unless the current admin page matches the exact hidden page slug.');
	$assert(strpos($pageSource, "VMS_PLUGIN_URL . 'assets/js/vms-event-plan-import.js'") !== false, 'Event Plan Import should enqueue the external asset from assets/js/vms-event-plan-import.js.');
	$assert(substr_count($pageSource, "current_user_can('manage_options')") >= 2, 'Event Plan Import should keep both the page capability gate and the enqueue capability gate.');
	$assert(
		preg_match(
			"~add_submenu_page\\(\\s*null,\\s*__\\('Import Event Plans \\(CSV\\)', 'backstage-venue-manager'\\),\\s*__\\('Import Event Plans \\(CSV\\)', 'backstage-venue-manager'\\),\\s*'manage_options',\\s*vms_event_plan_import_page_slug\\(\\),\\s*'vms_event_plan_import_render_admin_page'\\s*\\);~s",
			$pageSource
		) === 1,
		'Event Plan Import should preserve the hidden submenu registration, labels, capability, slug, and callback.'
	);

	$assert(strpos($pageSource, 'id="vms-epcsv-commit-form"') !== false, 'Event Plan Import should preserve the commit form ID.');
	$assert(strpos($pageSource, 'class="vms-epcsv-row-check"') !== false, 'Event Plan Import should preserve the eligible-row checkbox selector.');
	$assert(strpos($pageSource, 'name="commit_scope" value="selected"') !== false, 'Event Plan Import should preserve the selected-scope radio control.');
	$assert(strpos($pageSource, 'name="commit_scope" value="all"') !== false, 'Event Plan Import should preserve the all-scope radio control.');
	$assert(strpos($pageSource, 'id="vms-epcsv-selected-count"') !== false, 'Event Plan Import should preserve the selected-count element.');
	$assert(strpos($pageSource, 'id="vms-epcsv-select-all"') !== false, 'Event Plan Import should preserve the select-all control.');
	$assert(strpos($pageSource, 'id="vms-epcsv-clear-all"') !== false, 'Event Plan Import should preserve the clear-all control.');
	$assert(strpos($pageSource, "data-vms-selected-required-message=\"' . esc_attr(\$selected_required_message) . '\"") !== false, 'Event Plan Import should pass the selected-required alert through an escaped inert data attribute.');
	$assert(strpos($pageSource, "__('Select at least one eligible row before committing selected rows.', 'backstage-venue-manager')") !== false, 'Event Plan Import should preserve the exact selected-required alert message.');
	$assert(strpos($pageSource, "(\$preview['rows_json_storage_key'] ?? (\$preview['rows_json_path'] ?? ''))") !== false, 'Event Plan Import should preserve the rows_json_storage_key to legacy rows_json_path fallback.');
	$assert(strpos($pageSource, "'notices_callback' => \$render_notice") !== false, 'Event Plan Import shell render should preserve the explicit notice callback wiring.');
	$assert(strpos($pageSource, 'vms_event_plan_import_render_rows_payload_error((string) $rows_payload->get_error_code());') !== false, 'Event Plan Import should preserve the package-owned rows-payload error renderer.');

	$assert(file_exists($assetPath), 'Event Plan Import external commit-selection asset should exist.');
	$assert(strpos($assetSource, "document.getElementById('vms-epcsv-commit-form')") !== false, 'Event Plan Import asset should target the existing commit form by ID.');
	$assert(strpos($assetSource, "form.querySelectorAll('.vms-epcsv-row-check')") !== false, 'Event Plan Import asset should target the eligible row selector.');
	$assert(strpos($assetSource, "form.querySelector('input[name=\"commit_scope\"][value=\"selected\"]')") !== false, 'Event Plan Import asset should target the selected-scope control.');
	$assert(strpos($assetSource, "form.querySelector('input[name=\"commit_scope\"][value=\"all\"]')") !== false, 'Event Plan Import asset should target the all-scope control.');
	$assert(strpos($assetSource, "document.getElementById('vms-epcsv-selected-count')") !== false, 'Event Plan Import asset should target the selected-count element.');
	$assert(strpos($assetSource, "document.getElementById('vms-epcsv-select-all')") !== false, 'Event Plan Import asset should target the select-all control.');
	$assert(strpos($assetSource, "document.getElementById('vms-epcsv-clear-all')") !== false, 'Event Plan Import asset should target the clear-all control.');
	$assert(strpos($assetSource, "form.getAttribute('data-vms-selected-required-message')") !== false, 'Event Plan Import asset should read the inert selected-required message handoff.');
	$assert(strpos($assetSource, 'if (!form) {') !== false, 'Event Plan Import asset should safely no-op when the commit form is absent.');
	$assert(strpos($assetSource, "form.dataset.vmsEventPlanImportBound === '1'") !== false, 'Event Plan Import asset should guard against duplicate listener binding.');
	$assert(strpos($assetSource, "form.dataset.vmsEventPlanImportBound = '1';") !== false, 'Event Plan Import asset should mark the form after binding listeners.');
	$assert(strpos($assetSource, 'function updateCount() {') !== false, 'Event Plan Import asset should preserve the selected-count updater.');
	$assert(strpos($assetSource, 'countNode.textContent = String(count);') !== false, 'Event Plan Import asset should preserve the selected-count text update.');
	$assert(strpos($assetSource, "btnAll.addEventListener('click'") !== false, 'Event Plan Import asset should preserve the select-all click handler.');
	$assert(strpos($assetSource, "btnClear.addEventListener('click'") !== false, 'Event Plan Import asset should preserve the clear-all click handler.');
	$assert(strpos($assetSource, "checkbox.addEventListener('change', updateCount);") !== false, 'Event Plan Import asset should preserve the change-driven selected-count updates.');
	$assert(strpos($assetSource, "form.addEventListener('submit', function (event) {") !== false, 'Event Plan Import asset should preserve the submit-time selection guard.');
	$assert(strpos($assetSource, 'var selectedCount = updateCount();') !== false, 'Event Plan Import asset should recompute the selected count at submit time.');
	$assert(strpos($assetSource, 'if (scopeSelected && scopeSelected.checked && selectedCount === 0) {') !== false, 'Event Plan Import asset should only block submits when selected scope is active with zero checked rows.');
	$assert(substr_count($assetSource, 'preventDefault();') === 1, 'Event Plan Import asset should cancel submission only for the zero-selected selected-scope branch.');
	$assert(strpos($assetSource, 'window.alert(selectedRequiredMessage);') !== false, 'Event Plan Import asset should preserve the selected-required alert path.');
	$assert(strpos($assetSource, 'if (scopeAll && scopeAll.checked) {') !== false, 'Event Plan Import asset should preserve the all-scope continuation branch.');
	$assert(strpos($assetSource, 'updateCount();') !== false, 'Event Plan Import asset should preserve the initial selected-count computation.');

	$assert(strpos($corePluginSource, 'vms-event-plan-import') === false, 'Event Plan Import asset should not be registered through the global core admin asset loader.');
	$assert(strpos($adminUiAssetsSource, 'vms-event-plan-import') === false, 'Event Plan Import asset should not be registered through the shared VMS admin UI asset loader.');

	$assert($pageSource === $livePageSource, 'Mirror and live Event Plan Import PHP should remain byte-for-byte synchronized.');
	$assert($shellSource === $liveShellSource, 'Mirror and live Administrator shell PHP should remain byte-for-byte synchronized.');
	$assert($assetSource === $liveAssetSource, 'Mirror and live Event Plan Import JS should remain byte-for-byte synchronized.');

	$assert(strpos($actionsSource, 'wp_handle_upload(') !== false, 'Event Plan Import upload API remediation should remain present in the actions boundary.');
	$assert(strpos($engineSource, "function vms_event_plan_import_set_notice(string \$type, string \$message): void") !== false, 'Event Plan Import notice and storage engine should remain present and readable.');
	$assert(strpos($rowsPayloadTestSource, 'rows_json_storage_key') !== false, 'Rows-payload regression coverage should still verify the storage-key fallback contract.');
	$assert(strpos($noticeTestSource, 'Event Plan Import shell call should supply the page-local explicit notice callback.') !== false, 'Explicit-notice regression coverage should still verify the Event Plan Import shell callback contract.');
	$assert(strpos($uploadApiTestSource, 'wp_handle_upload') !== false, 'Upload API regression coverage should remain present for Event Plan Import preview staging.');

	fwrite(STDOUT, "event plan import inline js remediation: PASS\n");
} catch (Throwable $e) {
	fwrite(STDERR, 'event plan import inline js remediation: FAIL - ' . $e->getMessage() . "\n");
	exit(1);
}
