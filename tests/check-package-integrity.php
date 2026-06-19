<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/scripts/lib/public-release.php';

if ($argc < 2) {
	fwrite(STDERR, "Usage: php tests/check-package-integrity.php <path-to-vms-zip-or-staged-plugin-dir>\n");
	exit(2);
}

$target = (string) $argv[1];
$result = VMS_Public_Release_Tooling::validateTarget(
	$target,
	array(
		'plugin_slug' => 'vms',
		'manifest_path' => dirname(__DIR__) . '/release-public-excludes.txt',
	)
);

$failedChecks = array_values(array_filter(
	(array) ($result['checks'] ?? array()),
	static function ($check): bool {
		return is_array($check) && (($check['status'] ?? '') === 'FAIL');
	}
));

if ($failedChecks === array()) {
	fwrite(STDOUT, "Package integrity OK.\n");
	foreach ((array) ($result['checks'] ?? array()) as $check) {
		if (($check['status'] ?? '') !== 'PASS') {
			continue;
		}
		fwrite(STDOUT, sprintf(
			" - %s: %s\n",
			(string) ($check['label'] ?? $check['id'] ?? 'check'),
			(string) ($check['message'] ?? '')
		));
	}
	exit(0);
}

fwrite(STDERR, "Package integrity FAILED.\n");
foreach ($failedChecks as $check) {
	fwrite(STDERR, sprintf(
		" - %s: %s\n",
		(string) ($check['label'] ?? $check['id'] ?? 'check'),
		(string) ($check['message'] ?? '')
	));
	$details = (array) ($check['details'] ?? array());
	foreach (array('missing_files', 'missing_directories', 'paths', 'files', 'symlink_paths', 'failures') as $detailKey) {
		if (empty($details[$detailKey]) || !is_array($details[$detailKey])) {
			continue;
		}
		foreach ($details[$detailKey] as $detailValue) {
			fwrite(STDERR, '   * ' . (string) $detailValue . "\n");
		}
	}
}
exit(1);
