<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);
define('VMS_PLUGIN_URL', 'https://example.test/wp-content/plugins/backstage-venue-manager/');
define('VMS_VERSION', 'test-version');

$GLOBALS['vms_test_actions'] = array();
$GLOBALS['vms_test_current_screen'] = null;
$GLOBALS['vms_test_manage_options'] = false;
$GLOBALS['vms_test_styles'] = array();
$GLOBALS['vms_test_scripts'] = array();

function __(string $text, string $domain = ''): string
{
	unset($domain);
	return $text;
}

function add_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): void
{
	$GLOBALS['vms_test_actions'][$hook][$priority][] = array(
		'callback' => $callback,
		'accepted_args' => $accepted_args,
	);
}

function has_action(string $hook, $callback = false)
{
	if (!isset($GLOBALS['vms_test_actions'][$hook])) {
		return false;
	}

	foreach ($GLOBALS['vms_test_actions'][$hook] as $priority => $callbacks) {
		foreach ($callbacks as $entry) {
			if ($callback === false || $entry['callback'] === $callback) {
				return (int) $priority;
			}
		}
	}

	return false;
}

function get_current_screen()
{
	return $GLOBALS['vms_test_current_screen'];
}

function current_user_can(string $capability): bool
{
	unset($capability);
	return !empty($GLOBALS['vms_test_manage_options']);
}

function sanitize_key($value): string
{
	$sanitized = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $value));
	return is_string($sanitized) ? $sanitized : '';
}

function wp_unslash($value)
{
	return $value;
}

function wp_enqueue_style(string $handle, string $src = '', array $deps = array(), $ver = false, string $media = 'all'): void
{
	$GLOBALS['vms_test_styles'][$handle] = compact('src', 'deps', 'ver', 'media');
}

function wp_enqueue_script(string $handle, string $src = '', array $deps = array(), $ver = false, bool $in_footer = false): void
{
	$GLOBALS['vms_test_scripts'][$handle] = compact('src', 'deps', 'ver', 'in_footer');
}

function vms_asset_version(): string
{
	return 'test-asset-version';
}

require_once dirname(__DIR__) . '/includes/admin/staffing.php';

$pluginRoot = dirname(__DIR__);
$livePluginRoot = dirname(dirname($pluginRoot)) . '/vms';

$staffingPath = $pluginRoot . '/includes/admin/staffing.php';
$scriptPath = $pluginRoot . '/assets/js/vms-staffing-admin.js';
$stylePath = $pluginRoot . '/assets/css/vms-staffing-admin.css';
$staffCptPath = $pluginRoot . '/includes/cpt/staff.php';
$staffPortalPath = $pluginRoot . '/includes/portal/staff-portal.php';
$ledgerPath = $pluginRoot . '/docs/wporg-remediation-ledger.md';
$prereviewPath = $pluginRoot . '/docs/WPORG_PREREVIEW_REMEDIATION.md';
$corePluginPath = $pluginRoot . '/includes/core/plugin.php';
$adminUiAssetsPath = $pluginRoot . '/includes/admin-ui/assets.php';
$liveStaffingPath = $livePluginRoot . '/includes/admin/staffing.php';
$liveScriptPath = $livePluginRoot . '/assets/js/vms-staffing-admin.js';
$liveStylePath = $livePluginRoot . '/assets/css/vms-staffing-admin.css';

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

$resetRuntime = static function (): void {
	$GLOBALS['vms_test_current_screen'] = null;
	$GLOBALS['vms_test_manage_options'] = false;
	$GLOBALS['vms_test_styles'] = array();
	$GLOBALS['vms_test_scripts'] = array();
	$_GET = array();
};

try {
	$staffingSource = $readFile($staffingPath);
	$scriptSource = $readFile($scriptPath);
	$styleSource = $readFile($stylePath);
	$staffCptSource = $readFile($staffCptPath);
	$staffPortalSource = $readFile($staffPortalPath);
	$ledgerSource = $readFile($ledgerPath);
	$prereviewSource = $readFile($prereviewPath);
	$corePluginSource = $readFile($corePluginPath);
	$adminUiAssetsSource = $readFile($adminUiAssetsPath);
	$liveStaffingSource = $readFile($liveStaffingPath);
	$liveScriptSource = $readFile($liveScriptPath);
	$liveStyleSource = $readFile($liveStylePath);

	$assert(strpos($staffingSource, '<script') === false, 'Staffing admin PHP should no longer emit executable inline <script> blocks.');
	$assert(strpos($staffingSource, '<style') === false, 'Staffing admin PHP should no longer emit inline <style> blocks.');
	$assert(strpos($staffingSource, 'document.currentScript') === false, 'Staffing admin PHP should no longer own the currentScript helper bootstrap.');
	$assert(strpos($staffingSource, 'wp_add_inline_script(') === false, 'Staffing admin PHP should not reintroduce behavior through wp_add_inline_script().');
	$assert(strpos($staffingSource, 'wp_add_inline_style(') === false, 'Staffing admin PHP should not reintroduce styles through wp_add_inline_style().');

	$assert(has_action('admin_enqueue_scripts', 'vms_staffing_admin_enqueue_assets') === 50, 'Staffing admin should register a dedicated admin_enqueue_scripts callback at priority 50.');
	$assert(function_exists('vms_staffing_admin_screen_is_role_target'), 'Staffing admin should declare a dedicated Staff Roles screen helper.');
	$assert(function_exists('vms_staffing_admin_is_templates_page'), 'Staffing admin should declare a dedicated Staffing Templates page helper.');
	$assert(vms_staffing_admin_screen_is_role_target((object) array('base' => 'edit-tags', 'taxonomy' => 'vms_staff_role', 'post_type' => 'vms_staff')) === true, 'Staffing screen helper should allow the Staff Roles add/list screen.');
	$assert(vms_staffing_admin_screen_is_role_target((object) array('base' => 'term', 'taxonomy' => 'vms_staff_role', 'post_type' => 'vms_staff')) === true, 'Staffing screen helper should allow the Staff Roles term edit screen.');
	$assert(vms_staffing_admin_screen_is_role_target((object) array('base' => 'post', 'taxonomy' => 'vms_staff_role', 'post_type' => 'vms_staff')) === false, 'Staffing screen helper should reject non-taxonomy bases.');
	$assert(vms_staffing_admin_screen_is_role_target((object) array('base' => 'edit-tags', 'taxonomy' => 'category', 'post_type' => 'vms_staff')) === false, 'Staffing screen helper should reject non-Staff taxonomies.');
	$assert(vms_staffing_admin_screen_is_role_target((object) array('base' => 'edit-tags', 'taxonomy' => 'vms_staff_role', 'post_type' => 'vms_vendor')) === false, 'Staffing screen helper should reject non-Staff post types.');
	$assert(vms_staffing_admin_screen_is_role_target(null) === false, 'Staffing screen helper should safely reject a missing screen object.');

	$_GET['page'] = 'vms-staffing-templates';
	$assert(vms_staffing_admin_is_templates_page() === true, 'Staffing Templates helper should allow the exact page slug.');
	$_GET['page'] = 'vms-staffing-rollups';
	$assert(vms_staffing_admin_is_templates_page() === false, 'Staffing Templates helper should reject other staffing admin pages.');
	$_GET['page'] = array('vms-staffing-templates');
	$assert(vms_staffing_admin_is_templates_page() === false, 'Staffing Templates helper should reject invalid array page values.');
	$_GET = array();

	$resetRuntime();
	$GLOBALS['vms_test_current_screen'] = (object) array('base' => 'edit-tags', 'taxonomy' => 'vms_staff_role', 'post_type' => 'vms_staff');
	vms_staffing_admin_enqueue_assets();
	$assert($GLOBALS['vms_test_scripts'] === array(), 'Unauthorized users should not receive Staffing admin assets.');
	$assert($GLOBALS['vms_test_styles'] === array(), 'Unauthorized users should not receive Staffing templates styles.');

	$resetRuntime();
	$GLOBALS['vms_test_manage_options'] = true;
	$GLOBALS['vms_test_current_screen'] = (object) array('base' => 'edit-tags', 'taxonomy' => 'vms_staff_role', 'post_type' => 'vms_staff');
	vms_staffing_admin_enqueue_assets();
	$assert(isset($GLOBALS['vms_test_scripts']['vms-staffing-admin']), 'Staff Roles add/list screen should enqueue the dedicated Staffing admin script.');
	$assert(($GLOBALS['vms_test_scripts']['vms-staffing-admin']['src'] ?? '') === VMS_PLUGIN_URL . 'assets/js/vms-staffing-admin.js', 'Staffing admin script should use assets/js/vms-staffing-admin.js.');
	$assert(($GLOBALS['vms_test_scripts']['vms-staffing-admin']['deps'] ?? null) === array(), 'Staffing admin script should not add unnecessary dependencies.');
	$assert(($GLOBALS['vms_test_scripts']['vms-staffing-admin']['ver'] ?? null) === 'test-asset-version', 'Staffing admin script should use vms_asset_version() when available.');
	$assert(($GLOBALS['vms_test_scripts']['vms-staffing-admin']['in_footer'] ?? null) === true, 'Staffing admin script should load in the footer.');
	$assert($GLOBALS['vms_test_styles'] === array(), 'Staff Roles taxonomy screens should not enqueue the templates-only stylesheet.');

	$resetRuntime();
	$GLOBALS['vms_test_manage_options'] = true;
	$GLOBALS['vms_test_current_screen'] = (object) array('base' => 'term', 'taxonomy' => 'vms_staff_role', 'post_type' => 'vms_staff');
	vms_staffing_admin_enqueue_assets();
	$assert(isset($GLOBALS['vms_test_scripts']['vms-staffing-admin']), 'Staff Roles term edit screen should enqueue the dedicated Staffing admin script.');
	$assert($GLOBALS['vms_test_styles'] === array(), 'Staff Roles term edit screen should not enqueue the templates-only stylesheet.');

	$resetRuntime();
	$GLOBALS['vms_test_manage_options'] = true;
	$_GET['page'] = 'vms-staffing-templates';
	vms_staffing_admin_enqueue_assets();
	$assert(isset($GLOBALS['vms_test_scripts']['vms-staffing-admin']), 'Staffing Templates page should enqueue the dedicated Staffing admin script.');
	$assert(isset($GLOBALS['vms_test_styles']['vms-staffing-admin']), 'Staffing Templates page should enqueue the dedicated stylesheet.');
	$assert(($GLOBALS['vms_test_styles']['vms-staffing-admin']['src'] ?? '') === VMS_PLUGIN_URL . 'assets/css/vms-staffing-admin.css', 'Staffing admin stylesheet should use assets/css/vms-staffing-admin.css.');
	$assert(($GLOBALS['vms_test_styles']['vms-staffing-admin']['deps'] ?? null) === array(), 'Staffing admin stylesheet should not add unnecessary dependencies.');
	$assert(($GLOBALS['vms_test_styles']['vms-staffing-admin']['ver'] ?? null) === 'test-asset-version', 'Staffing admin stylesheet should use vms_asset_version() when available.');

	$resetRuntime();
	$GLOBALS['vms_test_manage_options'] = true;
	$GLOBALS['vms_test_current_screen'] = (object) array('base' => 'edit-tags', 'taxonomy' => 'category', 'post_type' => 'post');
	$_GET['page'] = 'dashboard';
	vms_staffing_admin_enqueue_assets();
	$assert($GLOBALS['vms_test_scripts'] === array(), 'Unrelated admin pages should not enqueue the Staffing admin script.');
	$assert($GLOBALS['vms_test_styles'] === array(), 'Unrelated admin pages should not enqueue the Staffing admin stylesheet.');

	$assert(strpos($staffingSource, 'function vms_staffing_admin_enqueue_assets(): void') !== false, 'Staffing admin source should declare the dedicated enqueue callback.');
	$assert(strpos($staffingSource, "VMS_PLUGIN_URL . 'assets/js/vms-staffing-admin.js'") !== false, 'Staffing admin source should point to assets/js/vms-staffing-admin.js.');
	$assert(strpos($staffingSource, "VMS_PLUGIN_URL . 'assets/css/vms-staffing-admin.css'") !== false, 'Staffing admin source should point to assets/css/vms-staffing-admin.css.');
	$assert(strpos($staffingSource, "add_action('admin_enqueue_scripts', 'vms_staffing_admin_enqueue_assets', 50);") !== false, 'Staffing admin source should register the enqueue callback.');
	$assert(strpos($staffingSource, "return \$page === 'vms-staffing-templates';") !== false, 'Staffing admin source should preserve the exact templates page slug.');
	$assert(strpos($staffingSource, "in_array((string) (\$screen->base ?? ''), array('edit-tags', 'term'), true)") !== false, 'Staffing admin source should preserve the exact Staff Roles taxonomy screen bases.');
	$assert(strpos($staffingSource, "(string) (\$screen->taxonomy ?? '') !== 'vms_staff_role'") !== false, 'Staffing admin source should preserve the exact Staff Roles taxonomy gate.');
	$assert(strpos($staffingSource, "return (string) (\$screen->post_type ?? '') === 'vms_staff';") !== false, 'Staffing admin source should preserve the exact Staff post type gate.');
	$assert(strpos($staffingSource, '<template data-vms-qualification-row-template="1">') !== false, 'Staffing admin source should render an inert qualification-row template.');
	$assert(strpos($staffingSource, '<template id="vms-tpl-slot-row-template">') !== false, 'Staffing admin source should render an inert slot-row template.');
	$assert(strpos($staffingSource, 'data-vms-qualification-builder="1"') !== false, 'Staffing admin source should preserve the qualification builder root selector.');
	$assert(strpos($staffingSource, 'data-vms-qualification-rows="1"') !== false, 'Staffing admin source should preserve the qualification rows selector.');
	$assert(strpos($staffingSource, 'data-vms-qualification-add="1"') !== false, 'Staffing admin source should preserve the qualification add-button selector.');
	$assert(strpos($staffingSource, 'data-vms-qualification-remove="1"') !== false, 'Staffing admin source should preserve the qualification remove-button selector.');
	$assert(strpos($staffingSource, 'id="vms-tpl-slots"') !== false, 'Staffing admin source should preserve the template slots root ID.');
	$assert(strpos($staffingSource, 'id="vms-tpl-add-row"') !== false, 'Staffing admin source should preserve the template add-row button ID.');
	$assert(strpos($staffingSource, 'data-vms-tpl-slot-row="1"') !== false, 'Staffing admin source should preserve the template row selector.');
	$assert(strpos($staffingSource, 'data-vms-tpl-time-mode-input="1"') !== false, 'Staffing admin source should preserve the template time-mode selector.');
	$assert(strpos($staffingSource, 'data-vms-tpl-absolute-field="1"') !== false, 'Staffing admin source should preserve the absolute-field marker.');
	$assert(strpos($staffingSource, 'data-vms-tpl-relative-field="1"') !== false, 'Staffing admin source should preserve the relative-field marker.');
	$assert(strpos($staffingSource, 'data-vms-tpl-end-field="1"') !== false, 'Staffing admin source should preserve the end-field marker.');
	$assert(strpos($staffingSource, 'data-vms-tpl-duration-input="1"') !== false, 'Staffing admin source should preserve the duration-input marker.');
	$assert(strpos($staffingSource, 'data-vms-tpl-absolute-warning') !== false, 'Staffing admin source should preserve the absolute-warning node.');

	$assert(file_exists($scriptPath), 'Staffing admin script asset should exist.');
	$assert(strpos($scriptSource, "builder.dataset.vmsQualificationInit === '1'") !== false, 'Staffing admin script should guard the qualification builder against duplicate initialization.');
	$assert(strpos($scriptSource, "builder.dataset.vmsQualificationInit = '1';") !== false, 'Staffing admin script should mark the qualification builder after initialization.');
	$assert(strpos($scriptSource, "builder.querySelector('[data-vms-qualification-row-template=\"1\"]')") !== false, 'Staffing admin script should read the inert qualification template selector.');
	$assert(strpos($scriptSource, "rowsWrap.querySelectorAll('[data-vms-qualification-row=\"1\"]')") !== false, 'Staffing admin script should preserve the qualification row selector.');
	$assert(strpos($scriptSource, "event.target.closest('[data-vms-qualification-remove=\"1\"]')") !== false, 'Staffing admin script should preserve delegated qualification remove handling.');
	$assert(strpos($scriptSource, "rows[0].querySelectorAll('input').forEach(function (input) {") !== false, 'Staffing admin script should preserve the last-row input reset behavior.');
	$assert(strpos($scriptSource, "select.value = 'warn';") !== false, 'Staffing admin script should preserve the last-row enforcement reset behavior.');
	$assert(strpos($scriptSource, "rowTemplate.innerHTML.replace(/__INDEX__/g, String(idx))") !== false, 'Staffing admin script should preserve the templated index replacement for new rows.');
	$assert(strpos($scriptSource, "document.getElementById('vms-tpl-slots')") !== false, 'Staffing admin script should target the existing template slots root.');
	$assert(strpos($scriptSource, "document.getElementById('vms-tpl-add-row')") !== false, 'Staffing admin script should target the existing add-row button.');
	$assert(strpos($scriptSource, "document.getElementById('vms-tpl-slot-row-template')") !== false, 'Staffing admin script should read the inert slot-row template.');
	$assert(strpos($scriptSource, "node.classList.toggle('vms-tpl-hidden', !show);") !== false, 'Staffing admin script should preserve the templates hidden-state class toggle.');
	$assert(strpos($scriptSource, "field.disabled = !show;") !== false, 'Staffing admin script should preserve disabled-state sync for hidden fields.');
	$assert(strpos($scriptSource, "duration > 0") !== false, 'Staffing admin script should preserve the duration short-circuit for end fields.');
	$assert(strpos($scriptSource, "absoluteWarning.classList.toggle('vms-hidden', !showWarning);") !== false, 'Staffing admin script should preserve the absolute-warning toggle behavior.');
	$assert(strpos($scriptSource, "event.target.closest('.vms-tpl-remove-row')") !== false, 'Staffing admin script should preserve delegated template-row removal handling.');
	$assert(strpos($scriptSource, "if (rows.length <= 1) {") !== false, 'Staffing admin script should preserve the one-row minimum guard.');
	$assert(strpos($scriptSource, "slotsWrap.appendChild(buildRow(rowCount()));") !== false, 'Staffing admin script should preserve the add-row append behavior.');
	$assert(strpos($scriptSource, "document.addEventListener('DOMContentLoaded', init, { once: true });") !== false, 'Staffing admin script should preserve safe initial-load behavior while the document is still loading.');

	$assert(file_exists($stylePath), 'Staffing admin stylesheet should exist.');
	$assert(strpos($styleSource, '#vms-tpl-slots{display:grid;gap:12px;margin-top:12px;}') !== false, 'Staffing admin stylesheet should own the template slots grid rules.');
	$assert(strpos($styleSource, '.vms-tpl-slot-card__row--identity{grid-template-columns:repeat(4,minmax(160px,1fr));}') !== false, 'Staffing admin stylesheet should own the identity-row layout rules.');
	$assert(strpos($styleSource, '.vms-tpl-hidden{display:none !important;}') !== false, 'Staffing admin stylesheet should preserve the templates hidden helper class.');
	$assert(strpos($styleSource, '@media (max-width: 1200px){') !== false, 'Staffing admin stylesheet should preserve the tablet responsive breakpoint.');
	$assert(strpos($styleSource, '@media (max-width: 782px){') !== false, 'Staffing admin stylesheet should preserve the mobile responsive breakpoint.');

	$assert(strpos($corePluginSource, 'vms-staffing-admin') === false, 'Staffing admin assets should not be registered through the global core admin asset loader.');
	$assert(strpos($adminUiAssetsSource, 'vms-staffing-admin') === false, 'Staffing admin assets should not be registered through the shared admin UI asset loader.');
	$assert(strpos($staffCptSource, 'function vms_staff_cpt_admin_enqueue_assets(): void') !== false, 'Staff CPT source should remain readable and unchanged by this slice.');
	$assert(strpos($staffPortalSource, 'function vms_staff_portal_safe_html(') !== false, 'Staff Portal source should remain readable and unchanged by this slice.');

	$assert(strpos($ledgerSource, '`WPORG-22R-L`') !== false, 'Ledger should record the Staffing admin residual closeout under WPORG-22R-L.');
	$assert(strpos($prereviewSource, '## WPORG-22R-L Result') !== false, 'Prereview remediation should include the Staffing admin closeout section.');

	$assert($staffingSource === $liveStaffingSource, 'Mirror/live Staffing admin PHP files should remain byte-for-byte synchronized.');
	$assert($scriptSource === $liveScriptSource, 'Mirror/live Staffing admin JS assets should remain byte-for-byte synchronized.');
	$assert($styleSource === $liveStyleSource, 'Mirror/live Staffing admin CSS assets should remain byte-for-byte synchronized.');

	fwrite(STDOUT, "staffing admin inline assets remediation: PASS\n");
} catch (Throwable $e) {
	fwrite(STDERR, 'staffing admin inline assets remediation: FAIL - ' . $e->getMessage() . "\n");
	exit(1);
}
