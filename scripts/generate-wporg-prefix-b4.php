<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/wporg-prefix-b4.php';

$root = dirname(__DIR__);
$check = in_array('--check', $argv, true);
$mapPath = $root . '/' . BVMGR_WPORG_Prefix_B4::MAP_PATH;
$retirementPath = $root . '/' . BVMGR_WPORG_Prefix_B4::RETIREMENT_PATH;
if (!is_file($mapPath) || !is_file($retirementPath)) {
	throw new RuntimeException('The immutable B4 artifacts are missing. Recreate them only from the authorized starting HEAD after explicit recovery review.');
}
$map = BVMGR_WPORG_Prefix_B4::loadJson($mapPath);
$retirement = BVMGR_WPORG_Prefix_B4::retirementMap($map);
if ((string) file_get_contents($retirementPath) !== BVMGR_WPORG_Prefix_B4::render($retirement)) {
	throw new RuntimeException('B4 compatibility-retirement artifact is stale.');
}

$expectedSummary = array(
	'browser_globals' => 29,
	'asset_handles' => 64,
	'asset_registration_call_sites' => 99,
	'asset_resolved_source_sites' => 105,
	'asset_dependency_sites' => 34,
	'asset_consumer_sites' => 19,
	'nonce_static_actions' => 154,
	'nonce_dynamic_action_families' => 64,
	'nonce_fields' => 73,
	'query_vars' => 14,
	'rewrite_tags' => 4,
	'rewrite_rules' => 7,
	'cli_paths' => 3,
);
if (($map['summary'] ?? null) !== $expectedSummary) {
	throw new RuntimeException('Frozen B4 summary does not match the reviewed exact inventory.');
}
if (!$check) {
	throw new RuntimeException('The B4 map is immutable. Use --check; do not overwrite the frozen map.');
}

$summary = $map['summary'];
echo 'B4 map: browser=' . $summary['browser_globals']
	. ' handles=' . $summary['asset_handles']
	. ' nonce_static=' . $summary['nonce_static_actions']
	. ' nonce_dynamic=' . $summary['nonce_dynamic_action_families']
	. ' nonce_fields=' . $summary['nonce_fields']
	. ' query_vars=' . $summary['query_vars']
	. ' rewrite_tags=' . $summary['rewrite_tags']
	. ' rewrite_rules=' . $summary['rewrite_rules']
	. ' cli=' . $summary['cli_paths'] . PHP_EOL;
