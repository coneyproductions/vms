<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);
define('BVMGR_PLUGIN_URL', 'https://example.test/wp-content/plugins/backstage-venue-manager/');
define('BVMGR_VERSION', 'test-version');

$GLOBALS['vms_test_current_screen'] = null;
$GLOBALS['vms_test_actions'] = array();
$GLOBALS['vms_test_scripts'] = array();
$GLOBALS['vms_test_localized_scripts'] = array();

function __(string $text, string $domain = ''): string { unset($domain); return $text; }
function esc_html__(string $text, string $domain = ''): string { unset($domain); return $text; }
function add_meta_box(...$args): void { unset($args); }
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
function wp_enqueue_script(string $handle, string $src = '', array $deps = array(), $ver = false, bool $in_footer = false): void
{
    $GLOBALS['vms_test_scripts'][$handle] = compact('src', 'deps', 'ver', 'in_footer');
}
function wp_localize_script(string $handle, string $name, array $data): bool
{
    $GLOBALS['vms_test_localized_scripts'][$handle] = compact('name', 'data');
    return true;
}
function bvmgr_asset_version(): string
{
    return 'test-asset-version';
}

require_once dirname(__DIR__) . '/includes/admin/vendor-comp-packages.php';
require_once dirname(__DIR__) . '/includes/admin/vendor-details.php';

$pluginRoot = dirname(__DIR__);
$livePluginRoot = dirname(dirname($pluginRoot)) . '/vms';

$compPath = $pluginRoot . '/includes/admin/vendor-comp-packages.php';
$vendorPath = $pluginRoot . '/includes/admin/vendor-details.php';
$assetPath = $pluginRoot . '/assets/js/vms-compensation-admin.js';
$ledgerPath = $pluginRoot . '/docs/wporg-remediation-ledger.md';
$prereviewPath = $pluginRoot . '/docs/WPORG_PREREVIEW_REMEDIATION.md';
$staffingPath = $pluginRoot . '/includes/admin/staffing.php';
$staffPortalPath = $pluginRoot . '/includes/portal/staff-portal.php';
$addPublicPath = $pluginRoot . '/includes/modules/availability-date-dispatch/public.php';
$corePluginPath = $pluginRoot . '/includes/core/plugin.php';
$liveCompPath = $livePluginRoot . '/includes/admin/vendor-comp-packages.php';
$liveVendorPath = $livePluginRoot . '/includes/admin/vendor-details.php';
$liveAssetPath = $livePluginRoot . '/assets/js/vms-compensation-admin.js';

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

$resetRuntime = static function (): void {
    $GLOBALS['vms_test_scripts'] = array();
    $GLOBALS['vms_test_localized_scripts'] = array();
};

try {
    $compSource = $readFile($compPath);
    $vendorSource = $readFile($vendorPath);
    $assetSource = $readFile($assetPath);
    $ledgerSource = $readFile($ledgerPath);
    $prereviewSource = $readFile($prereviewPath);
    $staffingSource = $readFile($staffingPath);
    $staffPortalSource = $readFile($staffPortalPath);
    $addPublicSource = $readFile($addPublicPath);
    $corePluginSource = $readFile($corePluginPath);
    $liveCompSource = $readFile($liveCompPath);
    $liveVendorSource = $readFile($liveVendorPath);
    $liveAssetSource = $readFile($liveAssetPath);

    $compRenderSource = $extractFunctionSource($compSource, 'vms_render_comp_package_meta_box');
    $vendorRenderSource = $extractFunctionSource($vendorSource, 'vms_render_vendor_defaults_metabox');
    $compEnqueueSource = $extractFunctionSource($compSource, 'vms_comp_package_admin_enqueue_assets');
    $vendorEnqueueSource = $extractFunctionSource($vendorSource, 'vms_vendor_defaults_admin_enqueue_assets');
    $compScreenSource = $extractFunctionSource($compSource, 'vms_comp_package_admin_screen_is_target');
    $vendorScreenSource = $extractFunctionSource($vendorSource, 'vms_vendor_defaults_admin_screen_is_target');

    $assert(strpos($compRenderSource, '<script') === false, 'Comp Package metabox should no longer emit an executable inline <script>.');
    $assert(strpos($vendorRenderSource, '<script') === false, 'Vendor Defaults metabox should no longer emit an executable inline <script>.');
    $assert(strpos($compSource, 'wp_add_inline_script(') === false, 'Comp Package admin should not reintroduce the helper through wp_add_inline_script().');
    $assert(strpos($vendorSource, 'wp_add_inline_script(') === false, 'Vendor Defaults admin should not reintroduce the helper through wp_add_inline_script().');

    $assert(has_action('admin_enqueue_scripts', 'vms_comp_package_admin_enqueue_assets') === 50, 'Comp Package admin should register its screen-scoped enqueue callback at priority 50.');
    $assert(has_action('admin_enqueue_scripts', 'vms_vendor_defaults_admin_enqueue_assets') === 50, 'Vendor Defaults admin should register its screen-scoped enqueue callback at priority 50.');
    $assert(strpos($compEnqueueSource, "BVMGR_PLUGIN_URL . 'assets/js/vms-compensation-admin.js'") !== false, 'Comp Package admin should point to the shared compensation asset.');
    $assert(strpos($vendorEnqueueSource, "BVMGR_PLUGIN_URL . 'assets/js/vms-compensation-admin.js'") !== false, 'Vendor Defaults admin should point to the shared compensation asset.');
    $assert(strpos($compEnqueueSource, "'vmsCompPackageAdmin'") !== false, 'Comp Package admin should localize only the inert comp-package labels.');
    $assert(strpos($vendorEnqueueSource, "'vmsVendorDefaultsAdmin'") !== false, 'Vendor Defaults admin should localize only the inert vendor-defaults strings.');
    $assert(strpos($corePluginSource, 'vms-compensation-admin') === false, 'Compensation admin asset should not be loaded by the global core admin asset loader.');

    $assert(strpos($compScreenSource, "array('post', 'post-new')") !== false, 'Comp Package screen gate should stay limited to post/post-new.');
    $assert(strpos($compScreenSource, "=== 'vms_comp_package'") !== false, 'Comp Package screen gate should stay limited to the vms_comp_package post type.');
    $assert(strpos($vendorScreenSource, "array('post', 'post-new')") !== false, 'Vendor Defaults screen gate should stay limited to post/post-new.');
    $assert(strpos($vendorScreenSource, "=== 'vms_vendor'") !== false, 'Vendor Defaults screen gate should stay limited to the vms_vendor post type.');

    $assert(strpos($compSource, "register_post_type('vms_comp_package'") !== false, 'Comp Package CPT registration should remain unchanged.');
    $assert(strpos($compSource, "'vms_comp_package_details'") !== false, 'Comp Package metabox registration should remain unchanged.');
    $assert(strpos($compSource, "wp_nonce_field('vms_save_comp_package', 'vms_comp_package_nonce');") !== false, 'Comp Package nonce field should remain unchanged.');
    $assert(strpos($compSource, "add_action('save_post_vms_comp_package', function") !== false, 'Comp Package save hook should remain unchanged.');
    $assert(strpos($compSource, "name=\"vms_comp_type\"") !== false, 'Comp Package field names should remain unchanged.');
    $assert(strpos($compSource, "name=\"vms_flat_fee\"") !== false, 'Comp Package flat-fee field should remain unchanged.');
    $assert(strpos($compSource, "name=\"vms_attendance_bonus_mode\"") !== false, 'Comp Package attendance-bonus field should remain unchanged.');

    $assert(strpos($vendorSource, "'vms_vendor_defaults'") !== false, 'Vendor Defaults metabox registration should remain unchanged.');
    $assert(strpos($vendorSource, "wp_nonce_field('vms_save_vendor_defaults', 'vms_vendor_defaults_nonce');") !== false, 'Vendor Defaults nonce field should remain unchanged.');
    $assert(strpos($vendorSource, "add_action('save_post_vms_vendor', function") !== false, 'Vendor Defaults save hook should remain unchanged.');
    $assert(strpos($vendorSource, "name=\"vms_default_comp_structure\"") !== false, 'Vendor Defaults comp-structure field should remain unchanged.');
    $assert(strpos($vendorSource, "name=\"vms_default_comp_package_id\"") !== false, 'Vendor Defaults template field should remain unchanged.');
    $assert(strpos($vendorSource, "name=\"vms_default_attendance_bonus_mode\"") !== false, 'Vendor Defaults attendance-bonus field should remain unchanged.');
    $assert(strpos($vendorSource, "data-terms=\"<?php echo esc_attr((string) \$pkg_terms_json); ?>\"") !== false, 'Vendor Defaults should continue reading template terms from the existing option data attribute.');

    $assert(file_exists($assetPath), 'Shared compensation admin asset should exist.');
    $assert(strpos($assetSource, 'function initCompPackage()') !== false, 'Shared compensation asset should initialize the comp-package helper.');
    $assert(strpos($assetSource, 'function initVendorDefaults()') !== false, 'Shared compensation asset should initialize the vendor-defaults helper.');
    $assert(strpos($assetSource, "window[name]") !== false, 'Shared compensation asset should read only inert localized config objects.');
    $assert(strpos($assetSource, "const root = document.querySelector('.vms-comp-package-admin');") !== false, 'Comp Package asset should target the existing comp-package root.');
    $assert(strpos($assetSource, "root.dataset.vmsCompPackageBound === '1'") !== false, 'Comp Package asset should prevent duplicate initialization independently.');
    $assert(strpos($assetSource, "const typeSel = byId(root, 'vms_comp_type');") !== false, 'Comp Package asset should preserve the existing type selector.');
    $assert(strpos($assetSource, "const bonusModeSel = byId(root, 'vms_attendance_bonus_mode');") !== false, 'Comp Package asset should preserve the existing bonus-mode selector.');
    $assert(strpos($assetSource, "root.querySelectorAll('.vms-comp-package-block[data-show-when]')") !== false, 'Comp Package asset should preserve the existing structure blocks.');
    $assert(strpos($assetSource, "root.querySelectorAll('.vms-comp-package-mode-block[data-show-when-mode]')") !== false, 'Comp Package asset should preserve the existing mode blocks.');
    $assert(strpos($assetSource, "flatFeeRow.style.display = (typeValue === 'door_split') ? 'none' : '';") !== false, 'Comp Package asset should preserve the flat-fee toggle behavior.');
    $assert(strpos($assetSource, "flatLabelText.textContent = (typeValue === 'attendance_bonus')") !== false, 'Comp Package asset should preserve the Base Pay / Flat Fee label swap.');
    $assert(strpos($assetSource, "flatHelp.classList.toggle('vms-hidden', typeValue !== 'attendance_bonus');") !== false, 'Comp Package asset should preserve the attendance help toggle.');

    $assert(strpos($assetSource, "const root = document.querySelector('.vms-vendor-defaults-ui');") !== false, 'Vendor Defaults asset should target the existing vendor-defaults root.');
    $assert(strpos($assetSource, "root.dataset.vmsVendorDefaultsBound === '1'") !== false, 'Vendor Defaults asset should prevent duplicate initialization independently.');
    $assert(strpos($assetSource, "const structure = byId(root, 'vms_default_comp_structure');") !== false, 'Vendor Defaults asset should preserve the existing structure selector.');
    $assert(strpos($assetSource, "const templateSelect = byId(root, 'vms_default_comp_package_id');") !== false, 'Vendor Defaults asset should preserve the existing template selector.');
    $assert(strpos($assetSource, "const loadTemplateBtn = byId(root, 'vms-load-comp-template-btn');") !== false, 'Vendor Defaults asset should preserve the existing template copy button.');
    $assert(strpos($assetSource, "JSON.parse(option.getAttribute('data-terms') || '{}')") !== false, 'Vendor Defaults asset should preserve the existing template-terms data bridge.');
    $assert(strpos($assetSource, 'function renderTemplateUI()') !== false, 'Vendor Defaults asset should preserve the template preview renderer.');
    $assert(strpos($assetSource, 'function renderCurrentDefaultsSummary()') !== false, 'Vendor Defaults asset should preserve the current-defaults summary renderer.');
    $assert(strpos($assetSource, 'function renderAttendancePreview()') !== false, 'Vendor Defaults asset should preserve the attendance preview renderer.');
    $assert(strpos($assetSource, "summaryCard.innerHTML = [") !== false, 'Vendor Defaults asset should preserve the summary-card renderer.');
    $assert(strpos($assetSource, "templatePreview.innerHTML = [") !== false, 'Vendor Defaults asset should preserve the template preview renderer output.');
    $assert(strpos($assetSource, "previewTable.innerHTML =") !== false, 'Vendor Defaults asset should preserve the attendance preview table renderer.');
    $assert(strpos($assetSource, "loadTemplateBtn.addEventListener('click', function () {") !== false, 'Vendor Defaults asset should preserve the template copy behavior.');
    $assert(strpos($assetSource, "setValue('vms_default_comp_structure', terms.structure || 'flat_fee');") !== false, 'Vendor Defaults asset should preserve the copied structure field.');
    $assert(strpos($assetSource, "setValue('vms_default_attendance_bonus_max_bonus', terms.attendance_bonus_max_bonus);") !== false, 'Vendor Defaults asset should preserve the copied attendance-bonus fields.');
    $assert(strpos($assetSource, "if (!previewWrap || !previewFormula || !previewTable || !previewNote) {") !== false, 'Vendor Defaults asset should safely no-op when preview nodes are absent.');
    $assert(strpos($assetSource, "if (!templateSelect || !templatePreview) {") !== false, 'Vendor Defaults asset should safely no-op when template nodes are absent.');
    $assert(strpos($assetSource, "flatLabel.textContent = (currentStructure === 'attendance_bonus')") !== false, 'Vendor Defaults asset should preserve the Base Pay / Flat Fee field label swap.');
    $assert(strpos($assetSource, "previewWrap.classList.toggle('vms-hidden', !isAttendance);") !== false, 'Vendor Defaults asset should preserve the attendance-preview visibility behavior.');
    $assert(strpos($assetSource, "bonusBlock.classList.toggle('vms-hidden', !isAttendance);") !== false, 'Vendor Defaults asset should preserve the bonus-block visibility behavior.');
    $assert(strpos($assetSource, 'refresh();') !== false, 'Shared compensation asset should preserve the initial-state refresh behavior.');

    $assert(strpos($staffingSource, '<script') === false, 'Staffing admin source should remain externalized with no inline executable <script> blocks.');
    $assert(strpos($staffingSource, '<style') === false, 'Staffing admin source should remain externalized with no inline <style> blocks.');
    $assert(strpos($staffingSource, "BVMGR_PLUGIN_URL . 'assets/js/vms-staffing-admin.js'") !== false, 'Staffing admin source should preserve its dedicated external asset boundary.');
    $assert(strpos($staffPortalSource, 'window.VMS_STAFF_AV') === false, 'Staff Portal source should not reintroduce the old global availability bootstrap.');
    $assert(strpos($staffPortalSource, 'data-vms-staff-availability="1"') !== false, 'Staff Portal source should preserve the inert availability form marker.');
    $assert(strpos($staffPortalSource, 'assets/js/vms-staff-portal.js') !== false, 'Staff Portal source should preserve the dedicated availability asset boundary.');
    $assert(strpos($addPublicSource, '<style>') === false && strpos($addPublicSource, '<script') === false, 'ADD public shell should remain free of inline executable style/script emitters.');
    $assert(strpos($addPublicSource, 'assets/css/vms-add-dispatch-public-shell.css') !== false, 'ADD public shell should preserve the standalone stylesheet ownership.');

    $assert($liveCompSource !== '', 'Live Comp Package PHP should remain readable while the mirror-only remediation leaves ../../vms untouched.');
    $assert($liveVendorSource !== '', 'Live Vendor Defaults PHP should remain readable while the mirror-only remediation leaves ../../vms untouched.');
    $assert($assetSource === $liveAssetSource, 'Mirror/live compensation admin JS should remain byte-identical.');

    $resetRuntime();
    $GLOBALS['vms_test_current_screen'] = (object) array('base' => 'dashboard', 'post_type' => 'post');
    bvmgr_comp_package_admin_enqueue_assets();
    bvmgr_vendor_defaults_admin_enqueue_assets();
    $assert($GLOBALS['vms_test_scripts'] === array(), 'Shared compensation asset should not enqueue on unrelated admin screens.');
    $assert($GLOBALS['vms_test_localized_scripts'] === array(), 'Shared compensation asset should not localize config on unrelated admin screens.');

    $resetRuntime();
    $GLOBALS['vms_test_current_screen'] = (object) array('base' => 'post', 'post_type' => 'vms_comp_package');
    bvmgr_comp_package_admin_enqueue_assets();
    bvmgr_vendor_defaults_admin_enqueue_assets();
    $assert(isset($GLOBALS['vms_test_scripts']['bvmgr-compensation-admin']), 'Comp Package screen should enqueue the shared compensation asset.');
    $assert(($GLOBALS['vms_test_scripts']['bvmgr-compensation-admin']['src'] ?? '') === BVMGR_PLUGIN_URL . 'assets/js/vms-compensation-admin.js', 'Comp Package screen should use the expected compensation asset path.');
    $assert(($GLOBALS['vms_test_scripts']['bvmgr-compensation-admin']['ver'] ?? '') === 'test-asset-version', 'Comp Package screen should use the asset-version helper fallback pattern.');
    $assert(($GLOBALS['vms_test_scripts']['bvmgr-compensation-admin']['in_footer'] ?? false) === true, 'Comp Package screen should keep the shared compensation asset footer-loaded.');
    $assert(($GLOBALS['vms_test_localized_scripts']['bvmgr-compensation-admin']['name'] ?? '') === 'BVMGR_COMP_PACKAGE_ADMIN', 'Comp Package screen should localize only the comp-package labels.');
    $assert(($GLOBALS['vms_test_localized_scripts']['bvmgr-compensation-admin']['data']['labels']['basePay'] ?? '') === 'Base Pay', 'Comp Package screen should pass the Base Pay label through inert config.');
    $assert(($GLOBALS['vms_test_localized_scripts']['bvmgr-compensation-admin']['data']['labels']['flatFeeAmount'] ?? '') === 'Flat Fee Amount', 'Comp Package screen should pass the Flat Fee label through inert config.');

    $resetRuntime();
    $GLOBALS['vms_test_current_screen'] = (object) array('base' => 'post-new', 'post_type' => 'vms_vendor');
    bvmgr_comp_package_admin_enqueue_assets();
    bvmgr_vendor_defaults_admin_enqueue_assets();
    $assert(isset($GLOBALS['vms_test_scripts']['bvmgr-compensation-admin']), 'Vendor screen should enqueue the shared compensation asset.');
    $assert(($GLOBALS['vms_test_localized_scripts']['bvmgr-compensation-admin']['name'] ?? '') === 'BVMGR_VENDOR_DEFAULTS_ADMIN', 'Vendor screen should localize only the vendor-defaults strings.');
    $assert(($GLOBALS['vms_test_localized_scripts']['bvmgr-compensation-admin']['data']['strings']['selectedTemplateTitle'] ?? '') === 'Selected Template', 'Vendor screen should pass the vendor-defaults preview strings through inert config.');
    $assert(($GLOBALS['vms_test_localized_scripts']['bvmgr-compensation-admin']['data']['strings']['basePayMoney'] ?? '') === 'Base Pay ($)', 'Vendor screen should pass the Base Pay field label through inert config.');

    $assert(strpos($ledgerSource, 'WPORG-22R-F') !== false, 'Ledger should document the new WPORG-22R-F closeout.');
    $assert(strpos($prereviewSource, 'WPORG-22R-F') !== false, 'Prereview doc should document the new WPORG-22R-F closeout.');
    $assert(strpos($prereviewSource, '`WPORG-24` is now closed') !== false, 'Prereview doc should keep WPORG-24 closed.');
    $assert(strpos($prereviewSource, 'Review-10 Upload APIs Result') !== false, 'Prereview doc should keep Review-10 closed.');

    fwrite(STDOUT, "vendor compensation inline js remediation: PASS\n");
} catch (Throwable $e) {
    fwrite(STDERR, 'vendor compensation inline js remediation: FAIL - ' . $e->getMessage() . "\n");
    exit(1);
}
