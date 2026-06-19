<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/release-compatibility.php';

$options = array(
	'plugin_root' => dirname(__DIR__),
	'working_dir' => (string) getcwd(),
	'invocation' => 'php scripts/' . basename(__FILE__) . ' ' . implode(' ', array_slice($argv, 1)),
);

foreach (array_slice($argv, 1) as $argument) {
	if (strpos($argument, '--artifact=') === 0) {
		$options['artifact_path'] = substr($argument, strlen('--artifact='));
		continue;
	}
	if (strpos($argument, '--expected-sha256=') === 0) {
		$options['expected_sha256'] = substr($argument, strlen('--expected-sha256='));
		continue;
	}
	if (strpos($argument, '--baseline-artifact=') === 0) {
		$options['baseline_artifact_path'] = substr($argument, strlen('--baseline-artifact='));
		continue;
	}
	if (strpos($argument, '--wordpress-source=') === 0) {
		$options['wordpress_source_root'] = substr($argument, strlen('--wordpress-source='));
		continue;
	}
	if (strpos($argument, '--output-dir=') === 0) {
		$options['output_dir'] = substr($argument, strlen('--output-dir='));
		continue;
	}
	if (strpos($argument, '--scenarios=') === 0) {
		$options['scenario_ids'] = array_values(array_filter(array_map('trim', explode(',', substr($argument, strlen('--scenarios='))))));
		continue;
	}
	if (strpos($argument, '--php-memory-limit=') === 0) {
		$options['php_memory_limit'] = substr($argument, strlen('--php-memory-limit='));
		continue;
	}
	if (strpos($argument, '--wp-cli-timeout=') === 0) {
		$options['wp_cli_timeout_seconds'] = (int) substr($argument, strlen('--wp-cli-timeout='));
		continue;
	}
	if ($argument === '--force') {
		$options['force'] = true;
		continue;
	}
	if ($argument === '--matrix-only') {
		$options['run_clean_install_lifecycle'] = false;
		$options['run_upgrade'] = false;
		$options['run_migration_interruption'] = false;
		$options['run_uninstall'] = false;
		continue;
	}
	if ($argument === '--keep-failed-workspaces') {
		$options['keep_failed_workspaces'] = true;
		continue;
	}
}

if (empty($options['artifact_path'])) {
	fwrite(STDERR, "Usage: php scripts/test-release-compatibility.php --artifact=/path/to/vms-public-release.zip [--expected-sha256=...] [--baseline-artifact=...] [--wordpress-source=...] [--output-dir=...] [--scenarios=a,b,c] [--matrix-only] [--php-memory-limit=512M] [--wp-cli-timeout=180] [--force] [--keep-failed-workspaces]\n");
	exit(2);
}

try {
	$report = VMS_Release_Compatibility_Tooling::run($options);
	fwrite(STDOUT, VMS_Release_Compatibility_Tooling::generateTextReport($report));
	$status = (string) ($report['status'] ?? 'FAIL');
	if ($status === 'FAIL') {
		exit(1);
	}
	if ($status === 'BLOCKED') {
		exit(2);
	}
	exit(0);
} catch (Throwable $throwable) {
	fwrite(STDERR, $throwable->getMessage() . "\n");
	exit(1);
}
