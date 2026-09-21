<?php
declare(strict_types=1);

ini_set('memory_limit', '1G');

require_once __DIR__ . '/lib/wporg-prefix-inventory.php';
require_once __DIR__ . '/lib/wporg-prefix-b3.php';

$root = dirname(__DIR__);
$target = $root . '/tests/fixtures/current-prefix-authority.json';
$write = in_array('--write', $argv, true);
$check = in_array('--check', $argv, true);
if ($write === $check) {
	fwrite(STDERR, "Use exactly one of --write or --check.\n");
	exit(2);
}

$decode = static function (string $path): array {
	$decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
	if (!is_array($decoded)) {
		throw new RuntimeException('Expected JSON object: ' . $path);
	}
	return $decoded;
};
$counts = static function (array $symbols): array {
	$out = array();
	foreach ($symbols as $kind => $entries) {
		foreach ((array) $entries as $entry) {
			$name = (string) ($entry['current_identifier'] ?? '');
			if ($name !== '') {
				$out[$kind][$name] = count((array) ($entry['declaration_sites'] ?? array()));
			}
		}
		if (isset($out[$kind])) {
			ksort($out[$kind], SORT_STRING);
		}
	}
	ksort($out, SORT_STRING);
	return $out;
};
$literalCounts = static function (array $decisions) use ($root): array {
	$selected = array();
	foreach ($decisions as $decision) {
		$file = (string) ($decision['file'] ?? '');
		$legacy = (string) ($decision['legacy_identifier'] ?? '');
		if ($file === '' || !str_starts_with($legacy, 'vms_')) {
			continue;
		}
		$selected[$file][$legacy] = true;
		$selected[$file]['bvmgr_' . substr($legacy, 4)] = true;
	}
	$out = array();
	foreach ($selected as $file => $names) {
		$source = file_get_contents($root . '/' . $file);
		if (!is_string($source)) {
			throw new RuntimeException('Unable to read literal source: ' . $file);
		}
		$fileCounts = array();
		foreach (token_get_all($source) as $token) {
			if (!is_array($token) || $token[0] !== T_CONSTANT_ENCAPSED_STRING) {
				continue;
			}
			$name = substr($token[1], 1, -1);
			if (isset($names[$name])) {
				$fileCounts[$name] = ($fileCounts[$name] ?? 0) + 1;
			}
		}
		ksort($fileCounts, SORT_STRING);
		$out[$file] = $fileCounts;
	}
	ksort($out, SORT_STRING);
	return $out;
};

$manifest = $decode($root . '/docs/wporg-prefix-migration-manifest.json');
$decisions = $decode($root . '/docs/wporg-prefix-b3-literal-decisions.json');
$baseline = $counts((array) ($manifest['symbols'] ?? array()));
$currentScan = BVMGR_WPORG_Prefix_Inventory::scan($root);
$current = $counts((array) ($currentScan['symbols'] ?? array()));
$b3Map = BVMGR_WPORG_Prefix_B3::loadJson($root . '/' . BVMGR_WPORG_Prefix_B3::MAP_PATH);
$b3Progress = BVMGR_WPORG_Prefix_B3::progress($root, $b3Map);
$changes = array();
foreach (array_unique(array_merge(array_keys($baseline), array_keys($current))) as $kind) {
	$removed = array();
	$addedOrChanged = array();
	foreach ((array) ($baseline[$kind] ?? array()) as $name => $count) {
		if (!array_key_exists($name, (array) ($current[$kind] ?? array()))) {
			$removed[$name] = $count;
		}
	}
	foreach ((array) ($current[$kind] ?? array()) as $name => $count) {
		if (($baseline[$kind][$name] ?? null) !== $count) {
			$addedOrChanged[$name] = $count;
		}
	}
	if ($removed !== array() || $addedOrChanged !== array()) {
		ksort($removed, SORT_STRING);
		ksort($addedOrChanged, SORT_STRING);
		$changes[$kind] = array('removed' => $removed, 'current' => $addedOrChanged);
	}
}
ksort($changes, SORT_STRING);

$authority = array(
	'runtime_authority' => '0ca4eb0e505f26e16348b20cbfd54243c241ad80',
	'frozen_manifest_commit' => '85a1a16',
	'scope' => 'Certified unified source declaration and retained-literal authority. Generated deterministically from the frozen migration manifest plus the certified current runtime; historical migration certificates remain immutable.',
	'symbol_changes' => $changes,
	'exact_literals' => $literalCounts((array) ($decisions['decisions'] ?? array())),
	'b3_progress' => array(
		'counts' => (array) ($b3Progress['counts'] ?? array()),
		'issues' => (array) ($b3Progress['issues'] ?? array()),
	),
);
$json = json_encode($authority, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";

if ($write) {
	if (file_put_contents($target, $json) === false) {
		throw new RuntimeException('Unable to write current-prefix authority fixture.');
	}
	fwrite(STDOUT, "Wrote tests/fixtures/current-prefix-authority.json\n");
	exit(0);
}

if ((string) file_get_contents($target) !== $json) {
	fwrite(STDERR, "Current-prefix authority fixture is stale; run this script with --write.\n");
	exit(1);
}
fwrite(STDOUT, "Current-prefix authority fixture is reproducible.\n");
