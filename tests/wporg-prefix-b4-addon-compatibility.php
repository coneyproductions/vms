<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/scripts/prepare-wporg-prefix-b4-addons.php';
require_once __DIR__ . '/helpers/companion-source-fixture.php';
$root = dirname(__DIR__);
$map = BVMGR_WPORG_Prefix_B4::loadJson($root . '/' . BVMGR_WPORG_Prefix_B4::MAP_PATH);
$slugs = array('vms-events-slider', 'vms-fill-dates', 'vms-data-tools', 'vms-express-bar', 'vms-refer-a-friend');
foreach ($slugs as $slug) {
    $copy = bvm_test_companion_runtime_copy(dirname($root, 2) . '/' . $slug);
    if (BVMGR_WPORG_Prefix_B4_Addons::scanRuntimeConsumers($copy, $map) !== array()) throw new RuntimeException('Unreconciled B4 semantic consumer in ' . $slug);
    if ($slug === 'vms-refer-a-friend') {
        $source = (string) file_get_contents($copy . '/includes/class-vms-raf-plugin.php');
        if (substr_count($source, "'vms-admin'") !== 1 || !str_contains($source, 'detect_vms_parent_slug')) throw new RuntimeException('Retained admin parent slug is not an asset handle.');
    }
    // Detect a newly introduced real asset consumer without mistaking retained page slugs for handles.
    $handle = $map['categories']['asset_handles'][0]['legacy_identifier'];
    file_put_contents($copy . '/bvm-negative-control.php', '<?php wp_enqueue_script(' . var_export($handle, true) . ');');
    if (BVMGR_WPORG_Prefix_B4_Addons::scanRuntimeConsumers($copy, $map) === array()) throw new RuntimeException('B4 scanner missed a synthetic legacy asset consumer.');
    unlink($copy . '/bvm-negative-control.php');
    if (BVMGR_WPORG_Prefix_B4_Addons::scanRuntimeConsumers($copy, $map) !== array()) throw new RuntimeException('Scanner state leaked across positive/negative runs.');
}
echo "B4 current runtime consumer boundary passed for five explicit companion fixtures, with mutation controls.\n";
