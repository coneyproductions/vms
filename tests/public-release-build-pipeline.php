<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/scripts/lib/public-release.php';

function vms_public_release_test_assert(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}

	throw new RuntimeException($message);
}

function vms_public_release_test_find_check(array $checks, string $id): array
{
	foreach ($checks as $check) {
		if (is_array($check) && (($check['id'] ?? '') === $id)) {
			return $check;
		}
	}

	throw new RuntimeException('Could not find check: ' . $id);
}

function vms_public_release_test_temp_dir(string $prefix): string
{
	$path = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $prefix . bin2hex(random_bytes(6));
	if (!mkdir($path, 0775, true) && !is_dir($path)) {
		throw new RuntimeException('Could not create temp directory: ' . $path);
	}

	return $path;
}

function vms_public_release_test_delete_path(string $path): void
{
	if ($path === '' || !file_exists($path)) {
		return;
	}

	if (is_file($path) || is_link($path)) {
		@unlink($path);
		return;
	}

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ($iterator as $item) {
		/** @var SplFileInfo $item */
		if ($item->isDir() && !$item->isLink()) {
			@rmdir($item->getPathname());
			continue;
		}
		@unlink($item->getPathname());
	}
	@rmdir($path);
}

function vms_public_release_test_write_file(string $path, string $contents): void
{
	$dir = dirname($path);
	if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
		throw new RuntimeException('Could not create directory: ' . $dir);
	}
	if (file_put_contents($path, $contents) === false) {
		throw new RuntimeException('Could not write file: ' . $path);
	}
}

function vms_public_release_test_run(array $command, ?string $cwd = null): array
{
	$descriptorSpec = array(
		0 => array('pipe', 'r'),
		1 => array('pipe', 'w'),
		2 => array('pipe', 'w'),
	);
	$process = proc_open($command, $descriptorSpec, $pipes, $cwd);
	if (!is_resource($process)) {
		throw new RuntimeException('Could not start test command: ' . implode(' ', $command));
	}

	fclose($pipes[0]);
	$stdout = stream_get_contents($pipes[1]);
	$stderr = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	$exitCode = proc_close($process);

	return array(
		'exit_code' => (int) $exitCode,
		'stdout' => is_string($stdout) ? $stdout : '',
		'stderr' => is_string($stderr) ? $stderr : '',
	);
}

function vms_public_release_test_fixture(array $options = array()): string
{
	$workspace = vms_public_release_test_temp_dir('vms-release-fixture-');
	$pluginRoot = $workspace . DIRECTORY_SEPARATOR . 'vms';
	$version = (string) ($options['version'] ?? '1.2.3');
	$headerVersion = (string) ($options['header_version'] ?? $version);
	$constantsVersion = (string) ($options['constants_version'] ?? $version);
	$buildVersion = (string) ($options['build_version'] ?? $version);
	$readmeStableTag = (string) ($options['readme_stable_tag'] ?? $version);
	$omitFiles = array_values(array_map('strval', (array) ($options['omit_files'] ?? array())));

	$files = array(
		'vendor-management-system.php' => <<<PHP
<?php
/**
 * Plugin Name: VMS
 * Description: Test fixture.
 * Version: {$headerVersion}
 * Author: Test
 * Text Domain: vms
 * Requires at least: 6.5
 * Requires PHP: 8.1
 */

defined('ABSPATH') || exit;

require_once __DIR__ . '/includes/runtime-guards.php';
require_once __DIR__ . '/includes/activation.php';
require_once __DIR__ . '/includes/bootstrap.php';
PHP,
		'includes/runtime-guards.php' => "<?php\ndefined('ABSPATH') || exit;\n",
		'includes/activation.php' => <<<PHP
<?php
defined('ABSPATH') || exit;

if (function_exists('vms_db_migrate_vendor_core_v1')) {
	vms_db_migrate_vendor_core_v1();
}
PHP,
		'includes/bootstrap.php' => "<?php\ndefined('ABSPATH') || exit;\nrequire_once __DIR__ . '/core/plugin.php';\n",
		'includes/core/plugin.php' => "<?php\ndefined('ABSPATH') || exit;\n",
		'includes/core/registry/constants.php' => <<<PHP
<?php
defined('ABSPATH') || exit;

if (!defined('VMS_PLUGIN_SLUG')) {
	define('VMS_PLUGIN_SLUG', 'vms');
}

if (!defined('VMS_VERSION')) {
	define('VMS_VERSION', '{$constantsVersion}');
}
PHP,
		'includes/db/migrations.php' => <<<PHP
<?php
defined('ABSPATH') || exit;

function vms_db_migrate_vendor_core_v1(): void
{
	if (function_exists('update_option')) {
		update_option('vms_db_schema_version', 'vendor_core_v1');
	}
}
PHP,
		'assets/js/app.js' => "console.log('fixture');\n",
		'uninstall.php' => "<?php\ndefined('WP_UNINSTALL_PLUGIN') || exit;\n",
		'vms-build.txt' => $buildVersion . "\n",
		'BUILD-NOTES-' . $version . '.md' => "# Build Notes {$version}\n",
		'readme.txt' => "=== VMS ===\nStable tag: {$readmeStableTag}\n",
		'release-public-excludes.txt' => <<<TXT
# Test manifest
docs/
tests/
scripts/
provenance/
dist/
BUILD-NOTES-*.md
*.zip
**/*.zip
TXT,
	);

	foreach ($files as $relativePath => $contents) {
		if (in_array($relativePath, $omitFiles, true)) {
			continue;
		}
		vms_public_release_test_write_file($pluginRoot . DIRECTORY_SEPARATOR . $relativePath, $contents);
	}

	foreach ((array) ($options['extra_files'] ?? array()) as $relativePath => $contents) {
		if (is_string($contents)) {
			vms_public_release_test_write_file($pluginRoot . DIRECTORY_SEPARATOR . $relativePath, $contents);
		}
	}

	return $pluginRoot;
}

function vms_public_release_test_create_zip(string $zipPath, array $entries): void
{
	$dir = dirname($zipPath);
	if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
		throw new RuntimeException('Could not create zip directory.');
	}

	$zip = new ZipArchive();
	vms_public_release_test_assert(
		$zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true,
		'Could not create fixture zip.'
	);

	foreach ($entries as $path => $contents) {
		if (substr($path, -1) === '/') {
			$zip->addEmptyDir($path);
			continue;
		}
		$zip->addFromString($path, $contents);
	}

	$zip->close();
}

function vms_public_release_test_write_provenance_manifest(string $pluginRoot, string $manifestPath, string $artifactPath): void
{
	$version = trim((string) file_get_contents($pluginRoot . '/vms-build.txt'));
	$artifactSha256 = strtolower((string) (hash_file('sha256', $artifactPath) ?: ''));
	$rootMtimeUnix = 0;
	$excludePatterns = array('docs/', 'tests/', 'scripts/', 'provenance/', 'dist/', 'BUILD-NOTES-*.md', '*.zip', '**/*.zip');
	if (class_exists('ZipArchive')) {
		$zip = new ZipArchive();
		if ($zip->open($artifactPath) === true) {
			$stat = $zip->statName('vms/');
			if (is_array($stat) && isset($stat['mtime'])) {
				$rootMtimeUnix = (int) $stat['mtime'];
			}
			$zip->close();
		}
	}
	$files = array();
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($pluginRoot, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::LEAVES_ONLY
	);
	foreach ($iterator as $fileInfo) {
		/** @var SplFileInfo $fileInfo */
		if (!$fileInfo->isFile()) {
			continue;
		}

		$absolutePath = $fileInfo->getPathname();
		$relativePath = ltrim(str_replace('\\', '/', substr($absolutePath, strlen($pluginRoot))), '/');
		$excluded = false;
		foreach ($excludePatterns as $pattern) {
			if (substr($pattern, -1) === '/' && strpos($relativePath . '/', $pattern) === 0) {
				$excluded = true;
				break;
			}
			if (fnmatch($pattern, $relativePath, FNM_PATHNAME)) {
				$excluded = true;
				break;
			}
		}
		if ($excluded) {
			continue;
		}

		$files[] = array(
			'path' => $relativePath,
			'size_bytes' => (int) ($fileInfo->getSize() ?: 0),
			'sha256' => strtolower((string) (hash_file('sha256', $absolutePath) ?: '')),
			'mtime_unix' => (int) ($fileInfo->getMTime() ?: 0),
		);
	}

	usort(
		$files,
		static function (array $left, array $right): int {
			return strcmp((string) ($left['path'] ?? ''), (string) ($right['path'] ?? ''));
		}
	);

	$manifest = array(
		'schema' => 1,
		'slug' => 'vms',
		'version' => $version,
		'artifact' => array(
			'filename' => 'vms-' . $version . '-public-release.zip',
			'sha256' => strtolower($artifactSha256),
			'root_mtime_unix' => $rootMtimeUnix,
		),
		'files' => $files,
	);

	vms_public_release_test_write_file(
		$manifestPath,
		(string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
	);
}

function vms_public_release_test_fixture_package_entries(array $overrides = array()): array
{
	$version = (string) ($overrides['version'] ?? '1.2.3');
	$headerVersion = (string) ($overrides['header_version'] ?? $version);
	$constantsVersion = (string) ($overrides['constants_version'] ?? $version);
	$buildVersion = (string) ($overrides['build_version'] ?? $version);

	$entries = array(
		'vms/' => '',
		'vms/vendor-management-system.php' => <<<PHP
<?php
/**
 * Plugin Name: VMS
 * Version: {$headerVersion}
 * Text Domain: vms
 */
PHP,
		'vms/includes/bootstrap.php' => "<?php\n",
		'vms/includes/core/plugin.php' => "<?php\n",
		'vms/includes/core/registry/constants.php' => "<?php\ndefine('VMS_PLUGIN_SLUG', 'vms');\ndefine('VMS_VERSION', '{$constantsVersion}');\n",
		'vms/includes/db/migrations.php' => "<?php\nfunction vms_db_migrate_vendor_core_v1(): void {}\n",
		'vms/assets/js/app.js' => "console.log('ok');\n",
		'vms/uninstall.php' => "<?php\ndefined('WP_UNINSTALL_PLUGIN') || exit;\n",
		'vms/vms-build.txt' => $buildVersion . "\n",
	);

	foreach ($overrides as $path => $contents) {
		if (!is_string($path) || strpos($path, 'vms/') !== 0) {
			continue;
		}
		$entries[$path] = $contents;
	}

	return $entries;
}

$tests = array();

$tests['prohibited development file included in staged source'] = static function (): void {
	$pluginRoot = vms_public_release_test_fixture(array(
		'extra_files' => array(
			'tests/leak.php' => "<?php\n",
		),
	));
	try {
		$result = VMS_Public_Release_Tooling::validateTarget($pluginRoot, array(
			'plugin_slug' => 'vms',
			'manifest_path' => $pluginRoot . '/release-public-excludes.txt',
		));
		$check = vms_public_release_test_find_check($result['checks'], 'manifest-excludes-honored');
		vms_public_release_test_assert(($check['status'] ?? '') === 'FAIL', 'Expected manifest validation to fail on staged source leak.');
	} finally {
		vms_public_release_test_delete_path(dirname($pluginRoot));
	}
};

$tests['required runtime file missing'] = static function (): void {
	$pluginRoot = vms_public_release_test_fixture(array(
		'omit_files' => array('includes/core/plugin.php'),
	));
	try {
		$result = VMS_Public_Release_Tooling::validateTarget($pluginRoot, array(
			'plugin_slug' => 'vms',
			'manifest_path' => $pluginRoot . '/release-public-excludes.txt',
		));
		$check = vms_public_release_test_find_check($result['checks'], 'entry-file-present');
		vms_public_release_test_assert(($check['status'] ?? '') === 'FAIL', 'Expected missing runtime file to fail validation.');
	} finally {
		vms_public_release_test_delete_path(dirname($pluginRoot));
	}
};

$tests['multiple top-level ZIP directories'] = static function (): void {
	$pluginRoot = vms_public_release_test_fixture();
	$workspace = dirname($pluginRoot);
	$zipPath = $workspace . '/bad-multiple-roots.zip';
	try {
		$entries = vms_public_release_test_fixture_package_entries();
		$entries['oops/extra.txt'] = "bad\n";
		vms_public_release_test_create_zip($zipPath, $entries);
		$result = VMS_Public_Release_Tooling::validateTarget($zipPath, array(
			'plugin_slug' => 'vms',
			'manifest_path' => $pluginRoot . '/release-public-excludes.txt',
		));
		$check = vms_public_release_test_find_check($result['checks'], 'single-top-level-root');
		vms_public_release_test_assert(($check['status'] ?? '') === 'FAIL', 'Expected multiple top-level roots to fail.');
	} finally {
		vms_public_release_test_delete_path($workspace);
	}
};

$tests['nested archive included'] = static function (): void {
	$pluginRoot = vms_public_release_test_fixture();
	$workspace = dirname($pluginRoot);
	$zipPath = $workspace . '/nested-archive.zip';
	try {
		$entries = vms_public_release_test_fixture_package_entries(array(
			'vms/inner.zip' => 'not a real zip but still forbidden',
		));
		vms_public_release_test_create_zip($zipPath, $entries);
		$result = VMS_Public_Release_Tooling::validateTarget($zipPath, array(
			'plugin_slug' => 'vms',
			'manifest_path' => $pluginRoot . '/release-public-excludes.txt',
		));
		$check = vms_public_release_test_find_check($result['checks'], 'nested-archives');
		vms_public_release_test_assert(($check['status'] ?? '') === 'FAIL', 'Expected nested archive to fail validation.');
	} finally {
		vms_public_release_test_delete_path($workspace);
	}
};

$tests['version mismatch'] = static function (): void {
	$pluginRoot = vms_public_release_test_fixture(array(
		'header_version' => '1.2.4',
		'constants_version' => '1.2.3',
		'build_version' => '1.2.3',
	));
	$outputDir = vms_public_release_test_temp_dir('vms-release-output-');
	try {
		$report = VMS_Public_Release_Tooling::build(array(
			'plugin_root' => $pluginRoot,
			'output_dir' => $outputDir,
			'force' => true,
			'release_tests' => array(),
		));
		$check = vms_public_release_test_find_check($report['checks'], 'version-consistency');
		vms_public_release_test_assert(($check['status'] ?? '') === 'FAIL', 'Expected version mismatch to fail build preflight.');
		vms_public_release_test_assert(($report['status'] ?? '') === 'FAIL', 'Expected overall build to fail on version mismatch.');
	} finally {
		vms_public_release_test_delete_path(dirname($pluginRoot));
		vms_public_release_test_delete_path($outputDir);
	}
};

$tests['dirty-tree policy where testable'] = static function (): void {
	$gitAvailable = vms_public_release_test_run(array('sh', '-lc', 'command -v git >/dev/null 2>&1'));
	if ($gitAvailable['exit_code'] !== 0) {
		return;
	}

	$pluginRoot = vms_public_release_test_fixture();
	$outputDir = vms_public_release_test_temp_dir('vms-release-output-');
	try {
		$result = vms_public_release_test_run(array('git', '-C', $pluginRoot, 'init', '-q'));
		vms_public_release_test_assert($result['exit_code'] === 0, 'Could not initialize git fixture repository.');
		vms_public_release_test_run(array('git', '-C', $pluginRoot, 'config', 'user.email', 'fixture@example.com'));
		vms_public_release_test_run(array('git', '-C', $pluginRoot, 'config', 'user.name', 'Fixture'));
		vms_public_release_test_run(array('git', '-C', $pluginRoot, 'add', '.'));
		$result = vms_public_release_test_run(array('git', '-C', $pluginRoot, 'commit', '-qm', 'fixture'));
		vms_public_release_test_assert($result['exit_code'] === 0, 'Could not create fixture commit.');
		file_put_contents($pluginRoot . '/assets/js/app.js', "console.log('dirty');\n");

		$report = VMS_Public_Release_Tooling::build(array(
			'plugin_root' => $pluginRoot,
			'output_dir' => $outputDir,
			'force' => true,
			'release_tests' => array(),
		));
		$check = vms_public_release_test_find_check($report['checks'], 'git-dirty-policy');
		vms_public_release_test_assert(($check['status'] ?? '') === 'FAIL', 'Expected dirty git worktree to fail without flags.');
	} finally {
		vms_public_release_test_delete_path(dirname($pluginRoot));
		vms_public_release_test_delete_path($outputDir);
	}
};

$tests['excluded file accidentally packaged'] = static function (): void {
	$pluginRoot = vms_public_release_test_fixture();
	$workspace = dirname($pluginRoot);
	$zipPath = $workspace . '/excluded-file.zip';
	try {
		$entries = vms_public_release_test_fixture_package_entries(array(
			'vms/docs/private.md' => '# private',
		));
		vms_public_release_test_create_zip($zipPath, $entries);
		$result = VMS_Public_Release_Tooling::validateTarget($zipPath, array(
			'plugin_slug' => 'vms',
			'manifest_path' => $pluginRoot . '/release-public-excludes.txt',
		));
		$check = vms_public_release_test_find_check($result['checks'], 'manifest-excludes-honored');
		vms_public_release_test_assert(($check['status'] ?? '') === 'FAIL', 'Expected packaged docs to fail manifest validation.');
	} finally {
		vms_public_release_test_delete_path($workspace);
	}
};

$tests['valid package success and output path containing spaces'] = static function (): void {
	$pluginRoot = vms_public_release_test_fixture();
	$outputDir = vms_public_release_test_temp_dir('vms release output ');
	try {
		$report = VMS_Public_Release_Tooling::build(array(
			'plugin_root' => $pluginRoot,
			'output_dir' => $outputDir,
			'force' => true,
			'release_tests' => array(),
		));
		vms_public_release_test_assert(($report['status'] ?? '') === 'PASS', 'Expected valid fixture build to pass.');
		vms_public_release_test_assert(!empty($report['artifact']['created']), 'Expected valid fixture build to create a ZIP.');
		vms_public_release_test_assert(is_readable((string) $report['artifact']['zip_path']), 'Expected ZIP artifact to exist.');
		vms_public_release_test_assert(is_readable((string) $report['artifact']['report_text_path']), 'Expected text report to exist.');
		vms_public_release_test_assert(is_readable((string) $report['artifact']['report_json_path']), 'Expected JSON report to exist.');
	} finally {
		vms_public_release_test_delete_path(dirname($pluginRoot));
		vms_public_release_test_delete_path($outputDir);
	}
};

$tests['provenance manifest rebuild reproduces the expected artifact sha'] = static function (): void {
	$pluginRoot = vms_public_release_test_fixture();
	$baselineOutputDir = vms_public_release_test_temp_dir('vms-release-baseline-');
	$provenanceOutputDir = vms_public_release_test_temp_dir('vms-release-provenance-');
	$manifestPath = dirname($pluginRoot) . '/provenance.json';
	try {
		$baselineReport = VMS_Public_Release_Tooling::build(array(
			'plugin_root' => $pluginRoot,
			'output_dir' => $baselineOutputDir,
			'force' => true,
			'release_tests' => array(),
		));
		vms_public_release_test_assert(($baselineReport['status'] ?? '') === 'PASS', 'Expected baseline fixture build to pass.');

		vms_public_release_test_write_provenance_manifest(
			$pluginRoot,
			$manifestPath,
			(string) ($baselineReport['artifact']['zip_path'] ?? '')
		);

		$provenanceReport = VMS_Public_Release_Tooling::build(array(
			'plugin_root' => $pluginRoot,
			'output_dir' => $provenanceOutputDir,
			'force' => true,
			'release_tests' => array(),
			'provenance_manifest_path' => $manifestPath,
		));
		vms_public_release_test_assert(($provenanceReport['status'] ?? '') === 'PASS', 'Expected provenance-aware fixture build to pass.');
		vms_public_release_test_assert(
			($provenanceReport['artifact']['sha256'] ?? '') === ($baselineReport['artifact']['sha256'] ?? ''),
			'Expected provenance-aware build to reproduce the baseline artifact SHA-256.'
		);
	} finally {
		vms_public_release_test_delete_path(dirname($pluginRoot));
		vms_public_release_test_delete_path($baselineOutputDir);
		vms_public_release_test_delete_path($provenanceOutputDir);
	}
};

$tests['provenance manifest detects runtime source drift'] = static function (): void {
	$pluginRoot = vms_public_release_test_fixture();
	$outputDir = vms_public_release_test_temp_dir('vms-release-provenance-');
	$manifestPath = dirname($pluginRoot) . '/provenance.json';
	try {
		$baselineReport = VMS_Public_Release_Tooling::build(array(
			'plugin_root' => $pluginRoot,
			'output_dir' => $outputDir,
			'force' => true,
			'release_tests' => array(),
		));
		vms_public_release_test_assert(($baselineReport['status'] ?? '') === 'PASS', 'Expected baseline fixture build to pass before drift check.');

		vms_public_release_test_write_provenance_manifest(
			$pluginRoot,
			$manifestPath,
			(string) ($baselineReport['artifact']['zip_path'] ?? '')
		);
		vms_public_release_test_write_file($pluginRoot . '/assets/js/app.js', "console.log('drift');\n");

		$report = VMS_Public_Release_Tooling::build(array(
			'plugin_root' => $pluginRoot,
			'output_dir' => $outputDir,
			'force' => true,
			'release_tests' => array(),
			'provenance_manifest_path' => $manifestPath,
		));
		$check = vms_public_release_test_find_check($report['checks'], 'provenance-source-match');
		vms_public_release_test_assert(($check['status'] ?? '') === 'FAIL', 'Expected provenance source mismatch to fail the build.');
	} finally {
		vms_public_release_test_delete_path(dirname($pluginRoot));
		vms_public_release_test_delete_path($outputDir);
	}
};

$tests['failed build cleanup'] = static function (): void {
	$pluginRoot = vms_public_release_test_fixture(array(
		'extra_files' => array(
			'assets/js/empty.js' => '',
		),
	));
	$outputDir = vms_public_release_test_temp_dir('vms-release-output-');
	$before = glob(rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'vms-public-release-*');
	try {
		$report = VMS_Public_Release_Tooling::build(array(
			'plugin_root' => $pluginRoot,
			'output_dir' => $outputDir,
			'force' => true,
			'release_tests' => array(),
		));
		vms_public_release_test_assert(($report['status'] ?? '') === 'FAIL', 'Expected zero-byte JS file to fail build.');
		$after = glob(rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'vms-public-release-*');
		vms_public_release_test_assert(
			count(array_diff($after ?: array(), $before ?: array())) === 0,
			'Expected failed build cleanup to remove temporary build directories.'
		);
	} finally {
		vms_public_release_test_delete_path(dirname($pluginRoot));
		vms_public_release_test_delete_path($outputDir);
	}
};

$passed = 0;

foreach ($tests as $label => $callback) {
	$callback();
	$passed++;
}

fwrite(STDOUT, "Public release build pipeline tests OK ({$passed} cases).\n");
