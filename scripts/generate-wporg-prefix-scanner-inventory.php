<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/wporg-prefix-scanner-inventory.php';

$root = dirname(__DIR__);
$artifactPath = $root . '/' . BVMGR_WPORG_Prefix_Scanner_Inventory::ARTIFACT_PATH;
$options = array(
	'mode' => '--check',
	'strict_json' => '',
	'historical_baseline' => '',
	'source_commit' => '',
	'package_sha256' => '',
);

foreach (array_slice($argv, 1) as $argument) {
	if (in_array($argument, array('--check', '--write', '--print', '--gate'), true)) {
		$options['mode'] = $argument;
		continue;
	}
	foreach (array('strict-json' => 'strict_json', 'historical-baseline' => 'historical_baseline', 'source-commit' => 'source_commit', 'package-sha256' => 'package_sha256') as $flag => $key) {
		$prefix = '--' . $flag . '=';
		if (str_starts_with($argument, $prefix)) {
			$options[$key] = substr($argument, strlen($prefix));
			continue 2;
		}
	}
	fwrite(STDERR, "Unknown argument: {$argument}\n");
	exit(2);
}

$usage = static function (): void {
	fwrite(STDERR, implode(PHP_EOL, array(
		'Usage:',
		'  php scripts/generate-wporg-prefix-scanner-inventory.php --check',
		'  php scripts/generate-wporg-prefix-scanner-inventory.php --write|--print --strict-json=PATH --historical-baseline=PATH --source-commit=SHA --package-sha256=SHA256',
		'  php scripts/generate-wporg-prefix-scanner-inventory.php --gate --strict-json=PATH',
	)) . PHP_EOL);
};

try {
	$manifest = BVMGR_WPORG_Prefix_Scanner_Inventory::loadJsonFile($root . '/docs/wporg-prefix-migration-manifest.json');
	$mode = (string) $options['mode'];
	if ($mode === '--check') {
		$inventory = BVMGR_WPORG_Prefix_Scanner_Inventory::loadJsonFile($artifactPath);
		BVMGR_WPORG_Prefix_Scanner_Inventory::validateArtifact($root, $inventory, $manifest);
		echo "Prefix scanner inventory is internally consistent.\n";
		exit(0);
	}

	if ($mode === '--gate') {
		if ($options['strict_json'] === '') {
			$usage();
			exit(2);
		}
		$inventory = BVMGR_WPORG_Prefix_Scanner_Inventory::loadJsonFile($artifactPath);
		$strictRows = BVMGR_WPORG_Prefix_Scanner_Inventory::loadJsonFile((string) $options['strict_json']);
		$gate = BVMGR_WPORG_Prefix_Scanner_Inventory::gate($root, $inventory, $strictRows, $manifest);
		echo BVMGR_WPORG_Prefix_Scanner_Inventory::render($gate);
		exit(($gate['status'] ?? '') === 'PASS' ? 0 : 1);
	}

	foreach (array('strict_json', 'historical_baseline', 'source_commit', 'package_sha256') as $required) {
		if ($options[$required] === '') {
			$usage();
			exit(2);
		}
	}
	$strictPath = (string) $options['strict_json'];
	$strictRows = BVMGR_WPORG_Prefix_Scanner_Inventory::loadJsonFile($strictPath);
	$historicalRows = BVMGR_WPORG_Prefix_Scanner_Inventory::loadJsonFile((string) $options['historical_baseline']);
	$artifact = BVMGR_WPORG_Prefix_Scanner_Inventory::build(
		$root,
		$strictRows,
		$historicalRows,
		$manifest,
		array(
			'source_commit' => (string) $options['source_commit'],
			'package_sha256' => (string) $options['package_sha256'],
			'strict_json_sha256' => hash_file('sha256', $strictPath) ?: '',
		)
	);
	$rendered = BVMGR_WPORG_Prefix_Scanner_Inventory::render($artifact);
	if ($mode === '--print') {
		echo $rendered;
		exit(0);
	}
	if (file_put_contents($artifactPath, $rendered) === false) {
		throw new RuntimeException('Unable to write ' . BVMGR_WPORG_Prefix_Scanner_Inventory::ARTIFACT_PATH . '.');
	}
	echo 'Wrote ' . BVMGR_WPORG_Prefix_Scanner_Inventory::ARTIFACT_PATH . ".\n";
} catch (BVMGR_WPORG_Prefix_Unmapped_Finding_Exception $exception) {
	fwrite(STDERR, 'Unmapped prefix finding: ' . $exception->getMessage() . PHP_EOL);
	fwrite(STDERR, BVMGR_WPORG_Prefix_Scanner_Inventory::render($exception->finding()));
	exit(1);
} catch (Throwable $exception) {
	fwrite(STDERR, $exception->getMessage() . PHP_EOL);
	exit(1);
}
