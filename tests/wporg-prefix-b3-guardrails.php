<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/scripts/lib/wporg-prefix-b3.php';
require_once dirname(__DIR__) . '/scripts/lib/wporg-prefix-b3-waves.php';
require_once dirname(__DIR__) . '/scripts/lib/wporg-prefix-b4.php';

$root = dirname(__DIR__);
$failures = array();
$assert = static function (bool $condition, string $message) use (&$failures): void {
	if (!$condition) {
		$failures[] = $message;
	}
};

try {
	$map = BVMGR_WPORG_Prefix_B3::loadJson($root . '/' . BVMGR_WPORG_Prefix_B3::MAP_PATH);
	BVMGR_WPORG_Prefix_B3::validateMap($map);
	$assert(($map['source_commit'] ?? '') === '634211d1d5bbd250fc13b19d02f39acd4a4bc96b', 'Frozen B3 map must identify the exact authorized B2.5 starting commit.');
	$assert(($map['counts'] ?? array()) === array(
		'unique_functions' => 4521,
		'declaration_sites' => 4541,
		'duplicate_two_site_families' => 20,
		'canonical_targets' => 4521,
		'scanner_rows' => 4541,
	), 'Frozen B3 map must retain exact semantic/scanner totals.');
	$addonEntries = array_filter((array) $map['mappings'], static fn(array $entry): bool => (array) ($entry['known_addon_consumers'] ?? array()) !== array());
	$publicApis = array_filter((array) $map['mappings'], static fn(array $entry): bool => is_string($entry['public_api_family'] ?? null));
	$assert(count($addonEntries) === 55, 'Frozen B3 map must identify exactly 55 unique known-add-on-consumed functions.');
	$assert(count($publicApis) === 12, 'Frozen B3 map must identify exactly 12 public B3 function APIs.');
	$progress = BVMGR_WPORG_Prefix_B3::progress($root, $map);
	require_once __DIR__ . '/helpers/current-prefix-fixture.php';
	bvm_test_assert_current_prefix($root);
	$assert($progress['issues'] === array(
		array('type' => 'declaration_state', 'legacy' => 'vms_maybe_migrate_activated_legacy_plugin_basename', 'canonical' => 'bvmgr_maybe_migrate_activated_legacy_plugin_basename', 'legacy_sites' => 0, 'canonical_sites' => 0, 'expected_sites' => 1),
		array('type' => 'declaration_state', 'legacy' => 'vms_migrate_legacy_plugin_basename', 'canonical' => 'bvmgr_migrate_legacy_plugin_basename', 'legacy_sites' => 0, 'canonical_sites' => 0, 'expected_sites' => 1),
		array('type' => 'declaration_state', 'legacy' => 'vms_migrate_legacy_plugin_basename_values', 'canonical' => 'bvmgr_migrate_legacy_plugin_basename_values', 'legacy_sites' => 0, 'canonical_sites' => 0, 'expected_sites' => 1),
		array('type' => 'declaration_state', 'legacy' => 'vms_migrate_registered_legacy_plugin_basenames', 'canonical' => 'bvmgr_migrate_registered_legacy_plugin_basenames', 'legacy_sites' => 0, 'canonical_sites' => 0, 'expected_sites' => 1),
		array('type' => 'declaration_state', 'legacy' => 'vms_plugin_basename_compatibility_pair', 'canonical' => 'bvmgr_plugin_basename_compatibility_pair', 'legacy_sites' => 0, 'canonical_sites' => 0, 'expected_sites' => 1),
		array('type' => 'declaration_state', 'legacy' => 'vms_plugin_basename_compatibility_pairs', 'canonical' => 'bvmgr_plugin_basename_compatibility_pairs', 'legacy_sites' => 0, 'canonical_sites' => 0, 'expected_sites' => 1),
		array('type' => 'declaration_state', 'legacy' => 'vms_register_legacy_plugin_basename_compatibility', 'canonical' => 'bvmgr_register_legacy_plugin_basename_compatibility', 'legacy_sites' => 0, 'canonical_sites' => 0, 'expected_sites' => 1),
		array('type' => 'declaration_state', 'legacy' => 'vms_vendor_portal_maybe_load_data_tools_reporting', 'canonical' => 'bvmgr_vendor_portal_maybe_load_data_tools_reporting', 'legacy_sites' => 0, 'canonical_sites' => 0, 'expected_sites' => 1),
	), 'Only the eight explicitly retired current-line helpers may be absent; all other mapped references must resolve.');
	$counts = (array) ($progress['counts'] ?? array());
	$assert((int) ($counts['migrated_unique_functions'] ?? -1) + (int) ($counts['remaining_legacy_unique_functions'] ?? -1) === 4513, 'Current B3 unique progress after eight explicit retirements must reconcile exactly.');
	$assert((int) ($counts['migrated_declaration_sites'] ?? -1) + (int) ($counts['remaining_legacy_declaration_sites'] ?? -1) === 4533, 'Current B3 declaration-site progress after eight explicit retirements must reconcile exactly.');
	$graph = BVMGR_WPORG_Prefix_B3::loadJson($root . '/' . BVMGR_WPORG_Prefix_B3::GRAPH_PATH);
	$assert(($graph['counts']['nodes'] ?? null) === 4521, 'B3 dependency graph must contain every frozen function node.');
	$assert((int) ($graph['counts']['edges'] ?? 0) > 0, 'B3 dependency graph must contain direct/dynamic edges.');
	$waves = BVMGR_WPORG_Prefix_B3::loadJson($root . '/' . BVMGR_WPORG_Prefix_B3_Waves::PLAN_PATH);
	$freshWaves = BVMGR_WPORG_Prefix_B3_Waves::build($map, $graph);
	$assert(BVMGR_WPORG_Prefix_B3::render($waves) === BVMGR_WPORG_Prefix_B3::render($freshWaves), 'B3 wave plan must exactly match the frozen map and graph.');
	$assert(($waves['counts'] ?? array()) === array('waves' => 11, 'unique_functions' => 4521, 'declaration_sites' => 4541, 'duplicate_families' => 20), 'B3 wave plan must reconcile all authorized totals.');
	$w3Functions = array();
	foreach ((array) ($waves['waves'] ?? array()) as $wave) {
		if (($wave['wave'] ?? '') === 'W3') {
			$w3Functions = (array) ($wave['legacy_functions'] ?? array());
		}
	}
	$literalDecisions = BVMGR_WPORG_Prefix_B3::literalDecisionIndex($root, $map, $w3Functions, true);
	$assert(($literalDecisions['selected_counts'] ?? array()) === array('rename' => 13, 'retain' => 30), 'W3 must classify all 43 frozen exact-only literals as 13 function identities and 30 retained contracts.');
	$literalArtifact = BVMGR_WPORG_Prefix_B3::loadJson($root . '/' . BVMGR_WPORG_Prefix_B3::LITERAL_DECISIONS_PATH);
	// Frozen literal decisions remain historical data; current literal identities are checked by the accepted delta fixture above.
} catch (Throwable $exception) {
	$failures[] = $exception->getMessage();
}

if ($failures !== array()) {
	fwrite(STDERR, "B3 guardrail failures:\n- " . implode("\n- ", $failures) . "\n");
	exit(1);
}

echo "B3 frozen map, dependency, and hybrid progress guardrails passed.\n";
