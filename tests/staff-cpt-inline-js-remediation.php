<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);
define('VMS_PLUGIN_URL', 'https://example.test/wp-content/plugins/backstage-venue-manager/');
define('VMS_VERSION', 'test-version');

$GLOBALS['vms_test_current_screen'] = null;
$GLOBALS['vms_test_actions'] = array();
$GLOBALS['vms_test_scripts'] = array();
$GLOBALS['vms_test_localized_scripts'] = array();

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

function wp_enqueue_script(string $handle, string $src = '', array $deps = array(), $ver = false, bool $in_footer = false): void
{
    $GLOBALS['vms_test_scripts'][$handle] = compact('src', 'deps', 'ver', 'in_footer');
}

function wp_localize_script(string $handle, string $name, array $data): bool
{
    $GLOBALS['vms_test_localized_scripts'][$handle] = compact('name', 'data');
    return true;
}

function vms_asset_version(): string
{
    return 'test-asset-version';
}

require_once dirname(__DIR__) . '/includes/cpt/staff.php';

$pluginRoot = dirname(__DIR__);
$livePluginRoot = dirname(dirname($pluginRoot)) . '/vms';

$staffPath = $pluginRoot . '/includes/cpt/staff.php';
$assetPath = $pluginRoot . '/assets/js/vms-staff-cpt-admin.js';
$staffingPath = $pluginRoot . '/includes/admin/staffing.php';
$staffPortalPath = $pluginRoot . '/includes/portal/staff-portal.php';
$ledgerPath = $pluginRoot . '/docs/wporg-remediation-ledger.md';
$prereviewPath = $pluginRoot . '/docs/WPORG_PREREVIEW_REMEDIATION.md';
$corePluginPath = $pluginRoot . '/includes/core/plugin.php';
$adminUiAssetsPath = $pluginRoot . '/includes/admin-ui/assets.php';
$liveStaffPath = $livePluginRoot . '/includes/cpt/staff.php';
$liveAssetPath = $livePluginRoot . '/assets/js/vms-staff-cpt-admin.js';

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
    $GLOBALS['vms_test_scripts'] = array();
    $GLOBALS['vms_test_localized_scripts'] = array();
};

try {
    $staffSource = $readFile($staffPath);
    $assetSource = $readFile($assetPath);
    $staffingSource = $readFile($staffingPath);
    $staffPortalSource = $readFile($staffPortalPath);
    $ledgerSource = $readFile($ledgerPath);
    $prereviewSource = $readFile($prereviewPath);
    $corePluginSource = $readFile($corePluginPath);
    $adminUiAssetsSource = $readFile($adminUiAssetsPath);
    $liveStaffSource = $readFile($liveStaffPath);
    $liveAssetSource = $readFile($liveAssetPath);

    $assert(strpos($staffSource, '<script') === false, 'Staff CPT PHP should no longer emit an executable inline <script> block.');
    $assert(strpos($staffSource, 'document.getElementById(\'vms-staff-qualification-add\')') === false, 'Staff CPT PHP should no longer own the add-button JavaScript helper.');
    $assert(strpos($staffSource, 'wp_add_inline_script(') === false, 'Staff CPT PHP should not reintroduce the helper through wp_add_inline_script().');

    $assert(has_action('admin_enqueue_scripts', 'vms_staff_cpt_admin_enqueue_assets') === 50, 'Staff CPT should register a dedicated admin_enqueue_scripts callback at priority 50.');
    $assert(isset($GLOBALS['vms_test_actions']['add_meta_boxes_vms_staff'][10]) && count($GLOBALS['vms_test_actions']['add_meta_boxes_vms_staff'][10]) === 1, 'Staff CPT should keep the existing add_meta_boxes_vms_staff registration.');
    $assert(isset($GLOBALS['vms_test_actions']['save_post_vms_staff'][30]) && count($GLOBALS['vms_test_actions']['save_post_vms_staff'][30]) === 1, 'Staff CPT taxonomy save hook should remain registered at priority 30.');
    $assert(isset($GLOBALS['vms_test_actions']['save_post_vms_staff'][40]) && count($GLOBALS['vms_test_actions']['save_post_vms_staff'][40]) === 1, 'Staff qualifications save hook should remain registered at priority 40.');

    $assert(function_exists('vms_staff_cpt_admin_screen_is_target'), 'Staff CPT should declare a dedicated screen-gate helper.');
    $assert(vms_staff_cpt_admin_screen_is_target((object) array('base' => 'post', 'post_type' => 'vms_staff')) === true, 'Staff CPT screen gate should allow the Staff post edit screen.');
    $assert(vms_staff_cpt_admin_screen_is_target((object) array('base' => 'post-new', 'post_type' => 'vms_staff')) === true, 'Staff CPT screen gate should allow the Staff post-new screen.');
    $assert(vms_staff_cpt_admin_screen_is_target((object) array('base' => 'edit', 'post_type' => 'vms_staff')) === false, 'Staff CPT screen gate should reject non-post/non-post-new screens.');
    $assert(vms_staff_cpt_admin_screen_is_target((object) array('base' => 'post', 'post_type' => 'vms_vendor')) === false, 'Staff CPT screen gate should reject non-Staff post types.');
    $assert(vms_staff_cpt_admin_screen_is_target(null) === false, 'Staff CPT screen gate should safely reject a missing screen object.');

    $resetRuntime();
    $GLOBALS['vms_test_current_screen'] = (object) array('base' => 'post', 'post_type' => 'vms_staff');
    vms_staff_cpt_admin_enqueue_assets();
    $assert(isset($GLOBALS['vms_test_scripts']['vms-staff-cpt-admin']), 'Staff CPT should enqueue the dedicated asset on the Staff post edit screen.');
    $assert($GLOBALS['vms_test_scripts']['vms-staff-cpt-admin']['src'] === VMS_PLUGIN_URL . 'assets/js/vms-staff-cpt-admin.js', 'Staff CPT should enqueue the dedicated asset from assets/js/vms-staff-cpt-admin.js.');
    $assert($GLOBALS['vms_test_scripts']['vms-staff-cpt-admin']['deps'] === array(), 'Staff CPT should not add unnecessary JavaScript dependencies.');
    $assert($GLOBALS['vms_test_scripts']['vms-staff-cpt-admin']['ver'] === 'test-asset-version', 'Staff CPT should use vms_asset_version() for the asset version when available.');
    $assert($GLOBALS['vms_test_scripts']['vms-staff-cpt-admin']['in_footer'] === true, 'Staff CPT should load the dedicated asset in the footer.');
    $assert(isset($GLOBALS['vms_test_localized_scripts']['vms-staff-cpt-admin']), 'Staff CPT should localize only the inert labels needed by the asset.');
    $assert($GLOBALS['vms_test_localized_scripts']['vms-staff-cpt-admin']['name'] === 'vmsStaffCptAdmin', 'Staff CPT should use the dedicated inert config object name.');
    $localizedData = $GLOBALS['vms_test_localized_scripts']['vms-staff-cpt-admin']['data'];
    $assert(array_keys($localizedData) === array('labels', 'statusOptions'), 'Staff CPT should localize only labels and statusOptions.');
    $assert(array_keys($localizedData['labels']) === array('qualification', 'authority', 'credentialNumber', 'issueDate', 'expiration', 'status', 'proofUrl', 'reviewNote', 'remove'), 'Staff CPT should pass only the translated field labels through localized config.');
    $assert(array_column($localizedData['statusOptions'], 'value') === array('active', 'pending_verification', 'rejected', 'expired', 'inactive'), 'Staff CPT should preserve the existing status values and order in the inert config.');

    $resetRuntime();
    $GLOBALS['vms_test_current_screen'] = (object) array('base' => 'post-new', 'post_type' => 'vms_staff');
    vms_staff_cpt_admin_enqueue_assets();
    $assert(isset($GLOBALS['vms_test_scripts']['vms-staff-cpt-admin']), 'Staff CPT should enqueue the dedicated asset on the Staff post-new screen.');

    $resetRuntime();
    $GLOBALS['vms_test_current_screen'] = (object) array('base' => 'post', 'post_type' => 'vms_vendor');
    vms_staff_cpt_admin_enqueue_assets();
    $assert($GLOBALS['vms_test_scripts'] === array(), 'Staff CPT should not enqueue the asset on non-Staff edit screens.');

    $resetRuntime();
    $GLOBALS['vms_test_current_screen'] = (object) array('base' => 'edit', 'post_type' => 'vms_staff');
    vms_staff_cpt_admin_enqueue_assets();
    $assert($GLOBALS['vms_test_scripts'] === array(), 'Staff CPT should not enqueue the asset on non-post/non-post-new Staff screens.');

    $assert(strpos($corePluginSource, 'vms-staff-cpt-admin') === false, 'Staff CPT asset should not be registered through the global core admin asset loader.');
    $assert(strpos($adminUiAssetsSource, 'vms-staff-cpt-admin') === false, 'Staff CPT asset should not be registered through the shared admin UI asset loader.');

    $assert(strpos($staffSource, "register_post_type('vms_staff'") !== false, 'Staff CPT post-type registration should remain unchanged.');
    $assert(strpos($staffSource, "'vms-staff-qualifications'") !== false, 'Staff qualifications metabox registration should remain unchanged.');
    $assert(strpos($staffSource, "wp_nonce_field('vms_staff_qualifications_save', 'vms_staff_qualifications_nonce');") !== false, 'Staff qualifications nonce field should remain unchanged.');
    $assert(substr_count($staffSource, "current_user_can('edit_post', \$post_id)") >= 2, 'Staff CPT should preserve the existing edit_post capability checks.');
    $assert(strpos($staffSource, 'id="vms-staff-qualification-add"') !== false, 'Staff CPT should preserve the existing add button ID.');
    $assert(strpos($staffSource, 'id="vms-staff-qualifications-list"') !== false, 'Staff CPT should preserve the existing qualification list ID.');
    $assert(strpos($staffSource, 'data-vms-staff-qualification-list="1"') !== false, 'Staff CPT should preserve the existing qualification list data marker.');
    $assert(strpos($staffSource, 'data-vms-staff-qualification-row="1"') !== false, 'Staff CPT should preserve the existing qualification row data marker.');
    $assert(strpos($staffSource, 'class="button vms-staff-qualification-remove"') !== false, 'Staff CPT should preserve the existing remove button selector.');
    $assert(strpos($staffSource, "name=\"vms_staff_qualifications[<?php echo esc_attr((string) \$idx); ?>][storage_kind]\"") !== false, 'Staff CPT should preserve the storage_kind hidden field in rendered rows.');
    $assert(strpos($staffSource, "name=\"vms_staff_qualifications[<?php echo esc_attr((string) \$idx); ?>][proof_url]\"") !== false, 'Staff CPT should preserve the proof_url field name in rendered rows.');
    $assert(strpos($staffSource, "\$proof_download_url !== '' ? \$proof_download_url : \$proof_url") !== false, 'Staff CPT should preserve the proof download URL fallback in rendered rows.');

    $assert(file_exists($assetPath), 'Staff CPT dedicated asset should exist.');
    $assert(strpos($assetSource, "window.vmsStaffCptAdmin || {}") !== false, 'Staff CPT asset should read only the inert localized config object.');
    $assert(strpos($assetSource, "document.getElementById('vms-staff-qualification-add')") !== false, 'Staff CPT asset should target the existing add button by ID.');
    $assert(strpos($assetSource, "document.getElementById('vms-staff-qualifications-list')") !== false, 'Staff CPT asset should target the existing qualification list by ID.');
    $assert(strpos($assetSource, "wrap.dataset.vmsStaffQualificationBound === '1'") !== false, 'Staff CPT asset should prevent duplicate initialization on the qualification list root.');
    $assert(strpos($assetSource, "wrap.dataset.vmsStaffQualificationBound = '1';") !== false, 'Staff CPT asset should mark the qualification list root after binding listeners.');
    $assert(strpos($assetSource, "if (!addBtn || !wrap) {") !== false, 'Staff CPT asset should safely no-op when the expected Staff metabox DOM is absent.');
    $assert(strpos($assetSource, "wrap.querySelectorAll('[data-vms-staff-qualification-row=\"1\"]')") !== false, 'Staff CPT asset should preserve the existing qualification row selector.');
    $assert(strpos($assetSource, "event.target.closest('.vms-staff-qualification-remove')") !== false, 'Staff CPT asset should preserve delegated remove-button handling.');
    $assert(strpos($assetSource, "rows[0].querySelectorAll('input').forEach(function (input) {") !== false, 'Staff CPT asset should preserve the last-row input reset behavior.');
    $assert(strpos($assetSource, "sel.value = 'active';") !== false, 'Staff CPT asset should preserve the last-row status reset behavior.');
    $assert(strpos($assetSource, "wrap.appendChild(buildRow(idx, config));") !== false, 'Staff CPT asset should preserve the add-row append behavior.');
    $assert(strpos($assetSource, "createHidden(idx, 'storage_kind', '')") !== false, 'Staff CPT asset should preserve the storage_kind hidden field for new rows.');
    $assert(strpos($assetSource, "createHidden(idx, 'source', 'admin')") !== false, 'Staff CPT asset should preserve the source hidden field default for new rows.');
    $assert(strpos($assetSource, "createInputLabel(idx, 'proof_url', '', 'url', 'regular-text')") !== false, 'Staff CPT asset should preserve the proof_url field name for new rows.');
    $assert(strpos($assetSource, "createInputLabel(idx, 'notes', '', 'text', 'regular-text')") !== false, 'Staff CPT asset should preserve the notes field name for new rows.');
    $assert(strpos($assetSource, "document.addEventListener('DOMContentLoaded', init, { once: true });") !== false, 'Staff CPT asset should preserve safe initial-load behavior when the document is still loading.');

    $assert(strpos($staffingSource, "VMS_PLUGIN_URL . 'assets/js/vms-staffing-admin.js'") !== false, 'Staffing admin source should preserve its separate external asset boundary.');
    $assert(strpos($staffingSource, '<script') === false, 'Staffing admin source should remain free of inline executable <script> blocks.');
    $assert(strpos($staffPortalSource, 'data-vms-staff-availability="1"') !== false, 'Staff Portal source should preserve the inert availability form marker.');
    $assert(strpos($staffPortalSource, 'assets/js/vms-staff-portal.js') !== false, 'Staff Portal source should preserve its separate external availability asset boundary.');

    $assert(strpos($ledgerSource, '`WPORG-22R-K`') !== false, 'Ledger should record the Staff CPT residual closeout under WPORG-22R-K.');
    $assert(strpos($prereviewSource, '## WPORG-22R-K Result') !== false, 'Prereview remediation should include the Staff CPT closeout section.');

    $assert($staffSource === $liveStaffSource, 'Mirror/live Staff CPT PHP files should remain byte-for-byte synchronized.');
    $assert($assetSource === $liveAssetSource, 'Mirror/live Staff CPT JS assets should remain byte-for-byte synchronized.');

    fwrite(STDOUT, "staff cpt inline js remediation: PASS\n");
} catch (Throwable $e) {
    fwrite(STDERR, 'staff cpt inline js remediation: FAIL - ' . $e->getMessage() . "\n");
    exit(1);
}
