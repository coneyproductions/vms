<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/scripts/lib/wporg-prefix-b3.php';
require_once dirname(__DIR__) . '/scripts/lib/wporg-prefix-b3-waves.php';

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
	$assert(($progress['status'] ?? '') === 'PASS', 'Current B3 hybrid progress must resolve without stale/forward references.');
	$counts = (array) ($progress['counts'] ?? array());
	$assert((int) ($counts['migrated_unique_functions'] ?? -1) + (int) ($counts['remaining_legacy_unique_functions'] ?? -1) === 4521, 'B3 unique progress must reconcile exactly.');
	$assert((int) ($counts['migrated_declaration_sites'] ?? -1) + (int) ($counts['remaining_legacy_declaration_sites'] ?? -1) === 4541, 'B3 declaration-site progress must reconcile exactly.');
	$graph = BVMGR_WPORG_Prefix_B3::loadJson($root . '/' . BVMGR_WPORG_Prefix_B3::GRAPH_PATH);
	$assert(($graph['counts']['nodes'] ?? null) === 4521, 'B3 dependency graph must contain every frozen function node.');
	$assert((int) ($graph['counts']['edges'] ?? 0) > 0, 'B3 dependency graph must contain direct/dynamic edges.');
	$waves = BVMGR_WPORG_Prefix_B3::loadJson($root . '/' . BVMGR_WPORG_Prefix_B3_Waves::PLAN_PATH);
	$freshWaves = BVMGR_WPORG_Prefix_B3_Waves::build($map, $graph);
	$assert(BVMGR_WPORG_Prefix_B3::render($waves) === BVMGR_WPORG_Prefix_B3::render($freshWaves), 'B3 wave plan must exactly match the frozen map and graph.');
	$assert(($waves['counts'] ?? array()) === array('waves' => 11, 'unique_functions' => 4521, 'declaration_sites' => 4541, 'duplicate_families' => 20), 'B3 wave plan must reconcile all authorized totals.');
} catch (Throwable $exception) {
	$failures[] = $exception->getMessage();
}

if ($failures !== array()) {
	fwrite(STDERR, "B3 guardrail failures:\n- " . implode("\n- ", $failures) . "\n");
	exit(1);
}

echo "B3 frozen map, dependency, and hybrid progress guardrails passed.\n";
