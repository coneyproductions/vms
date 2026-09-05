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
	$w3Functions = array();
	foreach ((array) ($waves['waves'] ?? array()) as $wave) {
		if (($wave['wave'] ?? '') === 'W3') {
			$w3Functions = (array) ($wave['legacy_functions'] ?? array());
		}
	}
	$literalDecisions = BVMGR_WPORG_Prefix_B3::literalDecisionIndex($root, $map, $w3Functions, true);
	$assert(($literalDecisions['selected_counts'] ?? array()) === array('rename' => 13, 'retain' => 30), 'W3 must classify all 43 frozen exact-only literals as 13 function identities and 30 retained contracts.');
	$literalArtifact = BVMGR_WPORG_Prefix_B3::loadJson($root . '/' . BVMGR_WPORG_Prefix_B3::LITERAL_DECISIONS_PATH);
	$postB4IntegratedLineReconciliations = array(
		'includes/cpt/event-plans.php:14317' => 14439,
		'includes/cpt/event-plans.php:14358' => 14480,
		'includes/cpt/event-plans.php:14514' => 14636,
		'includes/cpt/event-plans.php:14774' => 14946,
		'includes/cpt/event-plans.php:14800' => 14982,
		'includes/cpt/event-plans.php:14847' => 15030,
		'includes/cpt/event-plans.php:14877' => 15073,
		'includes/cpt/event-plans.php:14889' => 15085,
		'includes/cpt/event-plans.php:14920' => 15116,
		'includes/cpt/event-plans.php:14946' => 15152,
		'includes/cpt/event-plans.php:14977' => 15196,
		'includes/cpt/event-plans.php:15373' => 15596,
		'includes/cpt/event-plans.php:15380' => 15603,
		'includes/cpt/event-plans.php:15912' => 16234,
		'includes/cpt/event-plans.php:15924' => 16246,
		'includes/integrations/ticketing-phase-b.php:2383' => 2623,
		'includes/integrations/ticketing-phase-b.php:2805' => 3045,
		'includes/integrations/ticketing-phase-b.php:2823' => 3070,
		'includes/integrations/ticketing-phase-b.php:2857' => 3104,
		'includes/integrations/ticketing-phase-b.php:2875' => 3122,
		'includes/integrations/ticketing-phase-b.php:2890' => 3137,
		'includes/integrations/ticketing-phase-b.php:4680' => 4927,
		'includes/integrations/ticketing-phase-b.php:7323' => 7600,
		'includes/integrations/ticketing-phase-b.php:7391' => 7668,
		'includes/integrations/ticketing-phase-b.php:7458' => 7735,
		'includes/integrations/ticketing-phase-b.php:7526' => 7803,
		'includes/integrations/ticketing-phase-b.php:9678' => 10054,
		'includes/integrations/ticketing-phase-b.php:9875' => 10251,
		'includes/integrations/ticketing-phase-b.php:10040' => 10416,
		'includes/integrations/ticketing-phase-b.php:10190' => 10566,
		'includes/integrations/ticketing-rules-v2.php:2336' => 2429,
		'includes/integrations/ticketing-rules-v2.php:5434' => 5557,
		'includes/modules/admissions/vendor-guest-portal.php:1109' => 1122,
		'includes/public/event-details.php:810' => 978,
		'includes/public/event-details.php:1192' => 1400,
	);
	$assert(count($postB4IntegratedLineReconciliations) === 35, 'Post-B4 integration must reconcile exactly 35 shifted frozen literal sites.');
	$b4Map = BVMGR_WPORG_Prefix_B4::loadJson($root . '/' . BVMGR_WPORG_Prefix_B4::MAP_PATH);
	$b4NonceSites = array();
	foreach ((array) ($b4Map['categories']['nonce_actions'] ?? array()) as $row) {
		if (($row['family_kind'] ?? '') !== 'static') {
			continue;
		}
		foreach (array_merge((array) ($row['producer_sites'] ?? array()), (array) ($row['verifier_sites'] ?? array())) as $site) {
			$b4NonceSites[(string) $site['file'] . ':' . (int) $site['line'] . ':' . (string) $row['legacy_identifier']] = (string) $row['canonical_identifier'];
		}
	}
	foreach ((array) ($literalArtifact['decisions'] ?? array()) as $decision) {
		$legacy = (string) ($decision['legacy_identifier'] ?? '');
		$canonical = 'bvmgr_' . substr($legacy, 4);
		$file = (string) ($decision['file'] ?? '');
		$frozenLine = (int) ($decision['line'] ?? 0);
		$frozenLocation = $file . ':' . $frozenLine;
		$currentLine = $postB4IntegratedLineReconciliations[$frozenLocation] ?? $frozenLine;
		$siteKey = $frozenLocation . ':' . $legacy;
		$expected = $b4NonceSites[$siteKey] ?? (($decision['decision'] ?? '') === 'rename' && ($progress['function_states'][$legacy] ?? '') === 'migrated' ? $canonical : $legacy);
		$lines = file($root . '/' . $file) ?: array();
		$line = (string) ($lines[$currentLine - 1] ?? '');
		$assert(str_contains($line, "'" . $expected . "'") || str_contains($line, '"' . $expected . '"'), 'B3 exact-literal decision must resolve to its current expected identity: ' . $legacy . ' at ' . $file . ':' . $currentLine . ' (frozen line ' . $frozenLine . ')');
	}
} catch (Throwable $exception) {
	$failures[] = $exception->getMessage();
}

if ($failures !== array()) {
	fwrite(STDERR, "B3 guardrail failures:\n- " . implode("\n- ", $failures) . "\n");
	exit(1);
}

echo "B3 frozen map, dependency, and hybrid progress guardrails passed.\n";
