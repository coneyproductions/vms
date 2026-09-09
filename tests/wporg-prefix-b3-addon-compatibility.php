<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/scripts/lib/wporg-prefix-b3.php';
require_once __DIR__ . '/helpers/companion-source-fixture.php';
$root = dirname(__DIR__);
$map = BVMGR_WPORG_Prefix_B3::loadJson($root . '/' . BVMGR_WPORG_Prefix_B3::MAP_PATH);
$manifest = BVMGR_WPORG_Prefix_B3::loadJson($root . '/docs/wporg-prefix-migration-manifest.json');
$core = BVMGR_WPORG_Prefix_Inventory::scan($root);
$functions = array_fill_keys(array_column($core['symbols']['functions'], 'current_identifier'), true);
function bvm_test_extract_resolver(string $source, string $name): string {
    $tokens = token_get_all($source); $count = count($tokens);
    for ($i = 0; $i < $count; $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) continue;
        $j = $i + 1; while (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) $j++;
        if (!is_array($tokens[$j]) || $tokens[$j][1] !== $name) continue;
        $text = ''; $depth = 0; $opened = false;
        for (; $i < $count; $i++) {
            $t = $tokens[$i]; $text .= is_array($t) ? $t[1] : $t;
            if ($t === '{') { $depth++; $opened = true; }
            if ($t === '}' && --$depth === 0 && $opened) return $text;
        }
    }
    throw new RuntimeException('Missing actual companion resolver: ' . $name);
}
$resolvers = array(
    'vms-events-slider' => array('vms-events-slider.php', 'vms_events_slider_core_function'),
    'vms-fill-dates' => array('includes/core-compat.php', 'vms_fd_core_function'),
    'vms-data-tools' => array('includes/core-compat.php', 'vms_dt_core_function'),
    'vms-refer-a-friend' => array('includes/core-compat.php', 'vms_raf_core_function'),
);
$checked = 0;
foreach ($manifest['known_addons'] as $addon) {
    $slug = $addon['slug'];
    $copy = bvm_test_companion_runtime_copy(dirname($root, 2) . '/' . $slug);
    $names = array();
    foreach ($map['mappings'] as $mapping) if (in_array($slug, $mapping['known_addon_consumers'] ?? array(), true)) $names[] = $mapping['legacy_identifier'];
    if (!$names) throw new RuntimeException('Missing frozen consumer map: ' . $slug);
    // Current companions use canonical-first adapters. Characterize the real resolver, then
    // reproduce the historical direct-call migration with deterministic synthetic consumers.
    if (isset($resolvers[$slug])) {
        [$file, $resolver] = $resolvers[$slug];
        eval(bvm_test_extract_resolver((string) file_get_contents($copy . '/' . $file), $resolver));
        if ($resolver('vms_missing_fixture_capability') !== '') throw new RuntimeException('Absent capability must fail closed: ' . $slug);
        foreach ($names as $name) {
            $canonical = 'bvmgr_' . substr($name, 4);
            if (!isset($functions[$canonical])) throw new RuntimeException('Mapped core capability was removed: ' . $canonical);
            if (!function_exists($name)) eval('function ' . $name . '() {}');
            if (!function_exists($canonical)) eval('function ' . $canonical . '() {}');
            if ($resolver($name) !== $canonical) throw new RuntimeException('Actual companion must prefer canonical over legacy: ' . $slug . '/' . $name);
        }
    }
    $synthetic = '<?php ';
    foreach ($names as $name) $synthetic .= $name . '();';
    file_put_contents($copy . '/bvm-historical-direct-consumers.php', $synthetic);
    $before = BVMGR_WPORG_Prefix_B3::addonConsumerInventory($copy, $map, $names);
    $retained = array_merge($addon['consumed_contracts']['hooks'], $addon['consumed_contracts']['physical_cpt_taxonomy_identifiers'], $addon['consumed_contracts']['asset_handles']);
    $literal_counts = static function () use ($copy, $retained): array {
        $out = array_fill_keys($retained, 0);
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($copy, FilesystemIterator::SKIP_DOTS)) as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') continue;
            foreach (token_get_all((string) file_get_contents($file->getPathname())) as $token) {
                if (!is_array($token) || $token[0] !== T_CONSTANT_ENCAPSED_STRING) continue;
                $name = substr($token[1], 1, -1); if (isset($out[$name])) $out[$name]++;
            }
        }
        return $out;
    };
    $retained_before = $literal_counts();
    BVMGR_WPORG_Prefix_B3::transformAddonConsumers($copy, $map, $names);
    $after = BVMGR_WPORG_Prefix_B3::addonConsumerInventory($copy, $map, $names);
    if ($after['legacy_references'] || $after['selected_declarations']) throw new RuntimeException('Unresolved or colliding migrated consumer: ' . $slug);
    $expected = array_keys($before['canonical_references']);
    foreach (array_keys($before['legacy_references']) as $name) $expected[] = 'bvmgr_' . substr($name, 4);
    $expected = array_values(array_unique($expected)); sort($expected);
    $actual = array_keys($after['canonical_references']); sort($actual);
    if (!$actual || $actual !== $expected || array_diff($actual, array_keys($functions))) throw new RuntimeException('Migrated consumers do not resolve exactly to current core: ' . $slug . json_encode(array('before' => $before, 'expected' => $expected, 'actual' => $actual, 'missing_core' => array_diff($actual, array_keys($functions)))));
    if ($retained_before !== $literal_counts()) throw new RuntimeException('Transformation changed a retained contract: ' . $slug);
    BVMGR_WPORG_Prefix_B3::transformAddonConsumers($copy, $map, $names);
    if ($after !== BVMGR_WPORG_Prefix_B3::addonConsumerInventory($copy, $map, $names)) throw new RuntimeException('Repeated transformation changed consumer inventory: ' . $slug);
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($copy, FilesystemIterator::SKIP_DOTS)) as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') continue;
        $process = proc_open(array(PHP_BINARY, '-l', $file->getPathname()), array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
        if (!is_resource($process)) throw new RuntimeException('Cannot lint transformed companion');
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        if (proc_close($process) !== 0) throw new RuntimeException('Invalid transformed PHP: ' . $output);
    }
    $checked++;
}
if ($checked !== 5) throw new RuntimeException('All five explicit runtime companion fixtures are required');
echo "B3 current companion migration, resolution, retained-contract and idempotence checks passed for five fixtures.\n";
