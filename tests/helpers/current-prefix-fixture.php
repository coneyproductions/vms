<?php
/** Current declarations and retained exact literals, separate from frozen migration certificates. */
require_once dirname(__DIR__, 2) . '/scripts/lib/wporg-prefix-inventory.php';
function bvm_test_prefix_symbol_counts(array $symbols): array {
    $out = array();
    foreach ($symbols as $kind => $entries) {
        foreach ($entries as $entry) $out[$kind][$entry['current_identifier']] = count($entry['declaration_sites']);
        if (isset($out[$kind])) ksort($out[$kind]);
    }
    ksort($out); return $out;
}
function bvm_test_prefix_literals(string $root, array $decisions): array {
    $selected = array();
    foreach ($decisions as $decision) {
        $legacy = $decision['legacy_identifier'];
        $selected[$decision['file']][$legacy] = true;
        $selected[$decision['file']]['bvmgr_' . substr($legacy, 4)] = true;
    }
    $out = array();
    foreach ($selected as $file => $names) {
        $counts = array();
        foreach (token_get_all((string) file_get_contents($root . '/' . $file)) as $token) {
            if (!is_array($token) || $token[0] !== T_CONSTANT_ENCAPSED_STRING) continue;
            $name = substr($token[1], 1, -1);
            if (isset($names[$name])) $counts[$name] = ($counts[$name] ?? 0) + 1;
        }
        ksort($counts); $out[$file] = $counts;
    }
    ksort($out); return $out;
}
function bvm_test_assert_current_prefix(string $root, ?array $scan = null): array {
    $fixture = json_decode((string) file_get_contents(__DIR__ . '/../fixtures/current-prefix-authority.json'), true, 512, JSON_THROW_ON_ERROR);
    $manifest = json_decode((string) file_get_contents($root . '/docs/wporg-prefix-migration-manifest.json'), true, 512, JSON_THROW_ON_ERROR);
    $expected = bvm_test_prefix_symbol_counts($manifest['symbols']);
    foreach ($fixture['symbol_changes'] as $kind => $changes) {
        foreach ($changes['removed'] as $name => $count) {
            if (($expected[$kind][$name] ?? null) !== $count) throw new RuntimeException('Frozen removed declaration mismatch: ' . $name);
            unset($expected[$kind][$name]);
        }
        foreach ($changes['current'] as $name => $count) $expected[$kind][$name] = $count;
        ksort($expected[$kind]);
    }
    ksort($expected);
    $scan = $scan ?? BVMGR_WPORG_Prefix_Inventory::scan($root);
    if ($expected !== bvm_test_prefix_symbol_counts($scan['symbols'])) throw new RuntimeException('Current declaration inventory differs from the accepted authority delta.');
    $decisions = json_decode((string) file_get_contents($root . '/docs/wporg-prefix-b3-literal-decisions.json'), true, 512, JSON_THROW_ON_ERROR);
    if ($fixture['exact_literals'] !== bvm_test_prefix_literals($root, $decisions['decisions'])) throw new RuntimeException('Current retained exact literal contract changed.');
    return $fixture;
}
