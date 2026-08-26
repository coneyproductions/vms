<?php
declare(strict_types=1);

$pluginRoot = dirname(__DIR__);
$livePluginRoot = dirname(dirname($pluginRoot)) . '/vms';

$holidaysPath = $pluginRoot . '/includes/admin/holidays.php';
$taxBypassPath = $pluginRoot . '/includes/admin/tax-bypass.php';
$holidaysAssetPath = $pluginRoot . '/assets/js/vms-holidays-admin.js';
$taxBypassAssetPath = $pluginRoot . '/assets/js/vms-tax-bypass-admin.js';
$liveHolidaysPath = $livePluginRoot . '/includes/admin/holidays.php';
$liveTaxBypassPath = $livePluginRoot . '/includes/admin/tax-bypass.php';
$liveHolidaysAssetPath = $livePluginRoot . '/assets/js/vms-holidays-admin.js';
$liveTaxBypassAssetPath = $livePluginRoot . '/assets/js/vms-tax-bypass-admin.js';
$eventPlanImportPath = $pluginRoot . '/includes/admin/data-tools/page-event-plan-import.php';
$menuPath = $pluginRoot . '/includes/admin/menu.php';
$corePluginPath = $pluginRoot . '/includes/core/plugin.php';
$adminUiAssetsPath = $pluginRoot . '/includes/admin-ui/assets.php';

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
	$holidaysSource = $readFile($holidaysPath);
	$taxBypassSource = $readFile($taxBypassPath);
	$holidaysAssetSource = $readFile($holidaysAssetPath);
	$taxBypassAssetSource = $readFile($taxBypassAssetPath);
	$liveHolidaysSource = $readFile($liveHolidaysPath);
	$liveTaxBypassSource = $readFile($liveTaxBypassPath);
	$liveHolidaysAssetSource = $readFile($liveHolidaysAssetPath);
	$liveTaxBypassAssetSource = $readFile($liveTaxBypassAssetPath);
	$eventPlanImportSource = $readFile($eventPlanImportPath);
	$menuSource = $readFile($menuPath);
	$corePluginSource = $readFile($corePluginPath);
	$adminUiAssetsSource = $readFile($adminUiAssetsPath);

	$assert(strpos($holidaysSource, '<script') === false, 'Holidays PHP should no longer emit the executable bulk-selection <script> block.');
	$assert(strpos($taxBypassSource, '<script') === false, 'Tax Bypass PHP should no longer emit the executable required-field <script> block.');
	$assert(strpos($holidaysSource, 'wp_add_inline_script') === false, 'Holidays PHP should not reintroduce executable behavior through wp_add_inline_script().');
	$assert(strpos($taxBypassSource, 'wp_add_inline_script') === false, 'Tax Bypass PHP should not reintroduce executable behavior through wp_add_inline_script().');
	$assert(strpos($holidaysSource, 'document.querySelectorAll(".vms_holidays_row_cb")') === false, 'Holidays PHP should no longer own the row-checkbox controller.');
	$assert(strpos($taxBypassSource, "removeAttribute('required')") === false, 'Tax Bypass PHP should no longer own the required-field shim.');

	$assert(strpos($holidaysSource, 'function vms_admin_holidays_page_slug(): string') !== false, 'Holidays should declare a dedicated page-slug helper.');
	$assert(strpos($holidaysSource, "return 'vms-holidays';") !== false, 'Holidays should preserve the exact page slug.');
	$assert(strpos($holidaysSource, 'function vms_admin_holidays_enqueue_assets(): void') !== false, 'Holidays should declare a dedicated asset enqueue callback.');
	$assert(strpos($holidaysSource, "add_action('admin_enqueue_scripts', 'vms_admin_holidays_enqueue_assets', 50);") !== false, 'Holidays should register its page-specific enqueue callback.');
	$assert(preg_match('~\$page\s*!==\s*vms_admin_holidays_page_slug\(\)~', $holidaysSource) === 1, 'Holidays enqueue should bail unless the current admin page matches the exact Holidays slug.');
	$assert(strpos($holidaysSource, "BVMGR_PLUGIN_URL . 'assets/js/vms-holidays-admin.js'") !== false, 'Holidays should enqueue the external asset from assets/js/vms-holidays-admin.js.');
	$assert(substr_count($holidaysSource, "current_user_can('manage_options')") >= 2, 'Holidays should keep both the page capability guard and the enqueue capability guard.');
	$assert(
		preg_match(
			'~add_submenu_page\(\s*\$parent_slug,\s*__\(\'Holidays\', \'backstage-venue-manager\'\),\s*__\(\'Holidays\', \'backstage-venue-manager\'\),\s*\$capability,\s*\'vms-holidays\',\s*\'vms_admin_holidays_page\'\s*\);~s',
			$menuSource
		) === 1,
		'Holidays page registration should retain the existing parent, labels, capability variable, slug, and callback.'
	);
	$assert(strpos($holidaysSource, 'id="vms_holidays_select_all"') !== false, 'Holidays should preserve the controlling checkbox ID.');
	$assert(strpos($holidaysSource, 'class="vms_holidays_row_cb"') !== false, 'Holidays should preserve the row-checkbox selector.');
	$assert(strpos($holidaysSource, 'name="holiday_dates[]"') !== false, 'Holidays should preserve the bulk-delete checkbox payload contract.');

	$assert(file_exists($holidaysAssetPath), 'Holidays external asset should exist.');
	foreach (array(
		"document.getElementById('vms_holidays_select_all')",
		"document.querySelectorAll('.vms_holidays_row_cb')",
		"toggle.dataset.vmsHolidaysBulkBound === '1'",
		"toggle.dataset.vmsHolidaysBulkBound = '1';",
		"toggle.addEventListener('change', function () {",
		'boxes[i].checked = toggle.checked;',
		'if (!toggle) {',
	) as $requiredHolidaysAssetMarker) {
		$assert(strpos($holidaysAssetSource, $requiredHolidaysAssetMarker) !== false, 'Holidays asset should own the migrated controller marker: ' . $requiredHolidaysAssetMarker);
	}
	$assert(strpos($holidaysAssetSource, 'DOMContentLoaded') === false, 'Holidays asset should preserve the immediate post-markup execution model.');
	$assert(strpos($holidaysAssetSource, ':disabled') === false, 'Holidays asset should not add a new disabled-row filter.');
	$assert(strpos($holidaysAssetSource, '.disabled') === false, 'Holidays asset should not add new disabled-row branching.');

	$assert(strpos($taxBypassSource, "return array('vms_vendor', 'vms_staff');") !== false, 'Tax Bypass should preserve the exact supported post types.');
	$assert(strpos($taxBypassSource, 'function vms_tax_bypass_supported_screen($screen): bool') !== false, 'Tax Bypass should declare a dedicated supported-screen helper.');
	$assert(strpos($taxBypassSource, "in_array((string) (\$screen->base ?? ''), array('post', 'post-new'), true)") !== false, 'Tax Bypass should remain restricted to post and post-new screens.');
	$assert(strpos($taxBypassSource, "in_array((string) (\$screen->post_type ?? ''), vms_tax_bypass_supported_post_types(), true)") !== false, 'Tax Bypass should remain restricted to the supported Vendor/Staff post types.');
	$assert(strpos($taxBypassSource, 'add_action(\'admin_enqueue_scripts\', \'vms_admin_disable_required_for_tax_fields\', 50);') !== false, 'Tax Bypass should register the external asset gate on admin_enqueue_scripts.');
	$assert(strpos($taxBypassSource, "BVMGR_PLUGIN_URL . 'assets/js/vms-tax-bypass-admin.js'") !== false, 'Tax Bypass should enqueue the external asset from assets/js/vms-tax-bypass-admin.js.');
	$assert(substr_count($taxBypassSource, "current_user_can('manage_options')") >= 3, 'Tax Bypass should keep its existing admin capability boundaries plus the enqueue capability guard.');
	foreach (array(
		'name="vms_tax_bypass_enabled"',
		'name="vms_tax_bypass_until"',
		'name="vms_tax_bypass_reason"',
	) as $requiredTaxMarkupMarker) {
		$assert(strpos($taxBypassSource, $requiredTaxMarkupMarker) !== false, 'Tax Bypass metabox markup should preserve the field contract: ' . $requiredTaxMarkupMarker);
	}

	$assert(file_exists($taxBypassAssetPath), 'Tax Bypass external asset should exist.');
	foreach (array(
		"'input[name^=\"vms_tax_\"]'",
		"'select[name^=\"vms_tax_\"]'",
		"'input[name^=\"vms_addr\"]'",
		"'input[name=\"vms_city\"]'",
		"'input[name=\"vms_state\"]'",
		"'input[name=\"vms_zip\"]'",
		"'input[name=\"vms_payee_legal_name\"]'",
		"'select[name=\"vms_entity_type\"]'",
		"document.querySelectorAll(selectors.join(','))",
		"root.dataset.vmsTaxBypassRequiredBound === '1'",
		"root.dataset.vmsTaxBypassRequiredBound = '1';",
		"fields[i].removeAttribute('required');",
		"fields[i].removeAttribute('aria-required');",
		'if (!fields.length) {',
	) as $requiredTaxAssetMarker) {
		$assert(strpos($taxBypassAssetSource, $requiredTaxAssetMarker) !== false, 'Tax Bypass asset should own the migrated shim marker: ' . $requiredTaxAssetMarker);
	}
	$assert(strpos($taxBypassAssetSource, 'DOMContentLoaded') === false, 'Tax Bypass asset should preserve the immediate post-markup execution model.');
	$assert(strpos($taxBypassAssetSource, 'setAttribute(') === false, 'Tax Bypass asset should not add new required or ARIA attribute mutations.');

	$assert(strpos($corePluginSource, 'vms-holidays-admin') === false, 'Holidays asset should not be registered through the global core admin asset loader.');
	$assert(strpos($corePluginSource, 'vms-tax-bypass-admin') === false, 'Tax Bypass asset should not be registered through the global core admin asset loader.');
	$assert(strpos($adminUiAssetsSource, 'vms-holidays-admin') === false, 'Holidays asset should not be registered through the shared VMS admin UI asset loader.');
	$assert(strpos($adminUiAssetsSource, 'vms-tax-bypass-admin') === false, 'Tax Bypass asset should not be registered through the shared VMS admin UI asset loader.');

	$assert(strpos($eventPlanImportSource, 'vms-epcsv-select-all') !== false, 'Event Plan Import should retain its current select-all control contract.');
	$assert(strpos($eventPlanImportSource, 'Select at least one eligible row before committing selected rows.') !== false, 'Event Plan Import should retain its current preview-selection contract.');

	$assert($holidaysSource === $liveHolidaysSource, 'Mirror and live Holidays PHP should remain byte-for-byte synchronized.');
	$assert($taxBypassSource === $liveTaxBypassSource, 'Mirror and live Tax Bypass PHP should remain byte-for-byte synchronized.');
	$assert($holidaysAssetSource === $liveHolidaysAssetSource, 'Mirror and live Holidays JS should remain byte-for-byte synchronized.');
	$assert($taxBypassAssetSource === $liveTaxBypassAssetSource, 'Mirror and live Tax Bypass JS should remain byte-for-byte synchronized.');

	fwrite(STDOUT, "wporg 22r holidays tax bypass inline js remediation: PASS\n");
} catch (Throwable $e) {
	fwrite(STDERR, 'wporg 22r holidays tax bypass inline js remediation: FAIL - ' . $e->getMessage() . "\n");
	exit(1);
}
