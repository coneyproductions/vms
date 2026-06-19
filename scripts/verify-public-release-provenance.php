<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/public-release.php';

$options = array(
	'target' => '',
	'manifest_path' => '',
);

for ($index = 1; $index < $argc; $index++) {
	$argument = (string) $argv[$index];
	switch ($argument) {
		case '--target':
			$index++;
			$options['target'] = isset($argv[$index]) ? (string) $argv[$index] : '';
			break;

		case '--manifest':
			$index++;
			$options['manifest_path'] = isset($argv[$index]) ? (string) $argv[$index] : '';
			break;

		case '--help':
		case '-h':
			fwrite(STDOUT, "Usage: php scripts/verify-public-release-provenance.php --target <zip-or-dir> --manifest <path-to-provenance-manifest>\n");
			exit(0);

		default:
			fwrite(STDERR, "Unknown argument: {$argument}\n");
			fwrite(STDERR, "Usage: php scripts/verify-public-release-provenance.php --target <zip-or-dir> --manifest <path-to-provenance-manifest>\n");
			exit(2);
	}
}

if ($options['target'] === '' || $options['manifest_path'] === '') {
	fwrite(STDERR, "Usage: php scripts/verify-public-release-provenance.php --target <zip-or-dir> --manifest <path-to-provenance-manifest>\n");
	exit(2);
}

try {
	$result = VMS_Public_Release_Tooling::verifyProvenanceTarget($options['target'], $options['manifest_path']);
	foreach ((array) ($result['checks'] ?? array()) as $check) {
		fwrite(STDOUT, sprintf(
			"[%s] %s: %s\n",
			(string) ($check['status'] ?? 'FAIL'),
			(string) ($check['label'] ?? $check['id'] ?? 'check'),
			(string) ($check['message'] ?? '')
		));
	}
	exit((($result['status'] ?? 'FAIL') === 'PASS') ? 0 : 1);
} catch (Throwable $throwable) {
	fwrite(STDERR, $throwable->getMessage() . "\n");
	exit(1);
}
