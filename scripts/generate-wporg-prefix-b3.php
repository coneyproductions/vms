<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/wporg-prefix-b3.php';
require_once __DIR__ . '/lib/wporg-prefix-b3-waves.php';

$root = dirname(__DIR__);
$mode = $argv[1] ?? '--check';
$mapPath = $root . '/' . BVMGR_WPORG_Prefix_B3::MAP_PATH;
$graphPath = $root . '/' . BVMGR_WPORG_Prefix_B3::GRAPH_PATH;
$progressPath = $root . '/' . BVMGR_WPORG_Prefix_B3::PROGRESS_PATH;
$wavesPath = $root . '/' . BVMGR_WPORG_Prefix_B3_Waves::PLAN_PATH;

try {
	if ($mode === '--freeze-map') {
		if (is_file($mapPath)) {
			throw new RuntimeException('Refusing to overwrite the immutable B3 function map.');
		}
		$manifest = BVMGR_WPORG_Prefix_B3::loadJson($root . '/docs/wporg-prefix-migration-manifest.json');
		$scanner = BVMGR_WPORG_Prefix_B3::loadJson($root . '/docs/wporg-prefix-scanner-inventory.json');
		$map = BVMGR_WPORG_Prefix_B3::freezeMap($root, $manifest, $scanner);
		if (file_put_contents($mapPath, BVMGR_WPORG_Prefix_B3::render($map)) === false) {
			throw new RuntimeException('Unable to write B3 function map.');
		}
		echo 'Wrote ' . BVMGR_WPORG_Prefix_B3::MAP_PATH . ".\n";
		exit(0);
	}

	$map = BVMGR_WPORG_Prefix_B3::loadJson($mapPath);
	BVMGR_WPORG_Prefix_B3::validateMap($map);
	BVMGR_WPORG_Prefix_B3::literalDecisionIndex($root, $map);
	if ($mode === '--write-graph') {
		$graph = BVMGR_WPORG_Prefix_B3::buildGraph($root, $map);
		if (file_put_contents($graphPath, BVMGR_WPORG_Prefix_B3::render($graph)) === false) {
			throw new RuntimeException('Unable to write B3 dependency graph.');
		}
		echo 'Wrote ' . BVMGR_WPORG_Prefix_B3::GRAPH_PATH . ".\n";
		exit(0);
	}
	if ($mode === '--write-waves') {
		$graph = BVMGR_WPORG_Prefix_B3::loadJson($graphPath);
		$waves = BVMGR_WPORG_Prefix_B3_Waves::build($map, $graph);
		if (file_put_contents($wavesPath, BVMGR_WPORG_Prefix_B3::render($waves)) === false) {
			throw new RuntimeException('Unable to write B3 wave plan.');
		}
		echo 'Wrote ' . BVMGR_WPORG_Prefix_B3_Waves::PLAN_PATH . ".\n";
		exit(0);
	}
	if ($mode === '--write-progress' || $mode === '--print-progress') {
		$progress = BVMGR_WPORG_Prefix_B3::progress($root, $map);
		$rendered = BVMGR_WPORG_Prefix_B3::render($progress);
		if ($mode === '--print-progress') {
			echo $rendered;
			exit(($progress['status'] ?? '') === 'PASS' ? 0 : 1);
		}
		if (file_put_contents($progressPath, $rendered) === false) {
			throw new RuntimeException('Unable to write B3 progress artifact.');
		}
		echo 'Wrote ' . BVMGR_WPORG_Prefix_B3::PROGRESS_PATH . '. Status: ' . ($progress['status'] ?? 'UNKNOWN') . ".\n";
		exit(($progress['status'] ?? '') === 'PASS' ? 0 : 1);
	}
	if ($mode === '--transform') {
		$waveFile = '';
		foreach (array_slice($argv, 2) as $argument) {
			if (str_starts_with($argument, '--functions-json=')) {
				$waveFile = substr($argument, strlen('--functions-json='));
			}
		}
		if ($waveFile === '') {
			throw new RuntimeException('--transform requires --functions-json=PATH.');
		}
		$names = BVMGR_WPORG_Prefix_B3::loadJson($waveFile);
		if (array_is_list($names) === false) {
			throw new RuntimeException('Wave function JSON must be a list of legacy identifiers.');
		}
		$report = BVMGR_WPORG_Prefix_B3::transform($root, $map, $names);
		echo BVMGR_WPORG_Prefix_B3::render($report);
		exit(0);
	}
	if (str_starts_with($mode, '--transform-test-literals-wave=')) {
		$waveId = substr($mode, strlen('--transform-test-literals-wave='));
		$plan = BVMGR_WPORG_Prefix_B3::loadJson($wavesPath);
		$wave = null;
		foreach ((array) ($plan['waves'] ?? array()) as $candidate) {
			if (($candidate['wave'] ?? '') === $waveId) {
				$wave = $candidate;
				break;
			}
		}
		if (!is_array($wave)) {
			throw new RuntimeException('Unknown B3 test-literal wave: ' . $waveId);
		}
		echo BVMGR_WPORG_Prefix_B3::render(BVMGR_WPORG_Prefix_B3::transformTestLiterals($root, $map, (array) $wave['legacy_functions']));
		exit(0);
	}
	if (str_starts_with($mode, '--transform-wave=')) {
		$waveId = substr($mode, strlen('--transform-wave='));
		$plan = BVMGR_WPORG_Prefix_B3::loadJson($wavesPath);
		$wave = null;
		foreach ((array) ($plan['waves'] ?? array()) as $candidate) {
			if (($candidate['wave'] ?? '') === $waveId) {
				$wave = $candidate;
				break;
			}
		}
		if (!is_array($wave)) {
			throw new RuntimeException('Unknown B3 wave: ' . $waveId);
		}
		$current = BVMGR_WPORG_Prefix_B3::progress($root, $map);
		foreach ((array) ($wave['legacy_functions'] ?? array()) as $legacy) {
			if (($current['function_states'][$legacy] ?? '') !== 'pending') {
				throw new RuntimeException($waveId . ' is not wholly pending at ' . $legacy . '.');
			}
		}
		$report = BVMGR_WPORG_Prefix_B3::transform($root, $map, (array) $wave['legacy_functions']);
		$report['wave'] = $waveId;
		$report['expected_counts'] = $wave['counts'];
		echo BVMGR_WPORG_Prefix_B3::render($report);
		exit(0);
	}
	if ($mode !== '--check') {
		throw new RuntimeException('Usage: php scripts/generate-wporg-prefix-b3.php [--freeze-map|--write-graph|--write-waves|--write-progress|--print-progress|--check|--transform-wave=W1|--transform --functions-json=PATH]');
	}
	$graph = BVMGR_WPORG_Prefix_B3::loadJson($graphPath);
	if (($graph['function_map_sha256'] ?? '') !== hash('sha256', BVMGR_WPORG_Prefix_B3::render($map))) {
		throw new RuntimeException('B3 dependency graph is stale for the frozen function map.');
	}
	$waves = BVMGR_WPORG_Prefix_B3::loadJson($wavesPath);
	if (BVMGR_WPORG_Prefix_B3::render($waves) !== BVMGR_WPORG_Prefix_B3::render(BVMGR_WPORG_Prefix_B3_Waves::build($map, $graph))) {
		throw new RuntimeException('B3 wave plan is stale for the frozen map or dependency graph.');
	}
	$progress = BVMGR_WPORG_Prefix_B3::progress($root, $map);
	if (($progress['status'] ?? '') !== 'PASS') {
		throw new RuntimeException('B3 progress gate failed with ' . count((array) ($progress['issues'] ?? array())) . ' issue(s).');
	}
	echo sprintf(
		"B3 function map, dependency graph, and progress gate passed (%d migrated / %d remaining).\n",
		(int) ($progress['counts']['migrated_unique_functions'] ?? 0),
		(int) ($progress['counts']['remaining_legacy_unique_functions'] ?? 0)
	);
} catch (Throwable $exception) {
	fwrite(STDERR, $exception->getMessage() . PHP_EOL);
	exit(1);
}
