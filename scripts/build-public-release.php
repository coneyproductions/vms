<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/public-release.php';

function vms_public_release_build_usage(): string
{
	return <<<'TXT'
Usage:
  php scripts/build-public-release.php [--output-dir <dir>] [--force] [--allow-dirty] [--dev-build] [--provenance-manifest <file>] [--skip-release-tests]

Options:
  --output-dir <dir>         Write the ZIP and release reports to this directory. Defaults to ./dist
  --provenance-manifest <f>  Normalize staged file mtimes from a provenance manifest and verify the built ZIP against it.
  --skip-release-tests       Skip standalone regression scripts that expect a local WordPress environment.
  --force                    Overwrite an existing ZIP or report with the same filename.
  --allow-dirty              Allow a final/public artifact from a dirty git worktree and mark it in the report.
  --dev-build                Allow a development artifact from a dirty git worktree and append -dev to the filename.
  --help, -h                 Show this help text.
TXT;
}

$config = array(
	'plugin_root' => dirname(__DIR__),
);

for ($index = 1; $index < $argc; $index++) {
	$argument = (string) $argv[$index];
	switch ($argument) {
		case '--output-dir':
			$index++;
			if (!isset($argv[$index])) {
				fwrite(STDERR, "--output-dir requires a value.\n");
				fwrite(STDERR, vms_public_release_build_usage() . "\n");
				exit(2);
			}
			$config['output_dir'] = (string) $argv[$index];
			break;

		case '--force':
			$config['force'] = true;
			break;

		case '--provenance-manifest':
			$index++;
			if (!isset($argv[$index])) {
				fwrite(STDERR, "--provenance-manifest requires a value.\n");
				fwrite(STDERR, vms_public_release_build_usage() . "\n");
				exit(2);
			}
			$config['provenance_manifest_path'] = (string) $argv[$index];
			break;

		case '--allow-dirty':
			$config['allow_dirty'] = true;
			break;

		case '--skip-release-tests':
			$config['release_tests'] = array();
			break;

		case '--dev-build':
			$config['dev_build'] = true;
			break;

		case '--help':
		case '-h':
			fwrite(STDOUT, vms_public_release_build_usage() . "\n");
			exit(0);

		default:
			fwrite(STDERR, "Unknown argument: {$argument}\n");
			fwrite(STDERR, vms_public_release_build_usage() . "\n");
			exit(2);
	}
}

$report = VMS_Public_Release_Tooling::build($config);
$textReportPath = (string) ($report['artifact']['report_text_path'] ?? '');
$jsonReportPath = (string) ($report['artifact']['report_json_path'] ?? '');
$zipPath = (string) ($report['artifact']['zip_path'] ?? '');

if (($report['status'] ?? 'FAIL') === 'PASS') {
	fwrite(STDOUT, "Public release build PASSED.\n");
	fwrite(STDOUT, "ZIP: {$zipPath}\n");
	fwrite(STDOUT, "Text report: {$textReportPath}\n");
	fwrite(STDOUT, "JSON report: {$jsonReportPath}\n");
	fwrite(STDOUT, VMS_Public_Release_Tooling::renderTextReport($report));
	exit(0);
}

fwrite(STDERR, "Public release build FAILED.\n");
if ($textReportPath !== '') {
	fwrite(STDERR, "Text report: {$textReportPath}\n");
}
if ($jsonReportPath !== '') {
	fwrite(STDERR, "JSON report: {$jsonReportPath}\n");
}
fwrite(STDERR, VMS_Public_Release_Tooling::renderTextReport($report));
exit(1);
