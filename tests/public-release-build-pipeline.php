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

function vms_public_release_test_default_release_excludes(): array
{
	return array(
		'docs/',
		'tests/',
		'scripts/',
		'dist/',
		'BUILD-NOTES-*.md',
		'*.zip',
		'**/*.zip',
	);
}

function vms_public_release_test_release_excludes_text(array $lines): string
{
	return "# Test manifest\n" . implode("\n", $lines) . "\n";
}

function vms_public_release_test_invoke_private_static(string $method, array $args = array())
{
	$reflection = new ReflectionMethod(VMS_Public_Release_Tooling::class, $method);

	return $reflection->invokeArgs(null, $args);
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

function vms_public_release_test_public_slug(): string
{
	return 'backstage-venue-manager';
}

function vms_public_release_test_public_basename(): string
{
	return vms_public_release_test_public_slug() . '/backstage-venue-manager.php';
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
	$textDomain = (string) ($options['text_domain'] ?? vms_public_release_test_public_slug());
	$internalPluginSlug = (string) ($options['internal_plugin_slug'] ?? 'vms');
	$omitFiles = array_values(array_map('strval', (array) ($options['omit_files'] ?? array())));
	$releaseExcludeLines = array_values(array_map('strval', (array) ($options['release_exclude_lines'] ?? vms_public_release_test_default_release_excludes())));

	$files = array(
		'backstage-venue-manager.php' => <<<PHP
<?php
/**
 * Plugin Name: VMS
 * Description: Test fixture.
 * Version: {$headerVersion}
 * Author: Test
 * Text Domain: {$textDomain}
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

if (function_exists('bvmgr_db_migrate_vendor_core_v1')) {
	bvmgr_db_migrate_vendor_core_v1();
}
PHP,
		'includes/bootstrap.php' => "<?php\ndefined('ABSPATH') || exit;\nrequire_once __DIR__ . '/core/plugin.php';\n",
		'includes/core/plugin.php' => "<?php\ndefined('ABSPATH') || exit;\n",
		'includes/core/registry/constants.php' => <<<PHP
<?php
defined('ABSPATH') || exit;

if (!defined('BVMGR_PLUGIN_SLUG')) {
	define('BVMGR_PLUGIN_SLUG', '{$internalPluginSlug}');
}

if (!defined('BVMGR_VERSION')) {
	define('BVMGR_VERSION', '{$constantsVersion}');
}
PHP,
		'includes/db/migrations.php' => <<<PHP
<?php
defined('ABSPATH') || exit;

function bvmgr_db_migrate_vendor_core_v1(): void
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
		'release-public-excludes.txt' => vms_public_release_test_release_excludes_text($releaseExcludeLines),
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

function vms_public_release_test_read_zip_entries(string $zipPath): array
{
	$zip = new ZipArchive();
	if ($zip->open($zipPath) !== true) {
		throw new RuntimeException('Could not open ZIP fixture for inspection.');
	}

	$entries = array();
	for ($index = 0; $index < $zip->numFiles; $index++) {
		$name = $zip->getNameIndex($index);
		if (is_string($name) && $name !== '') {
			$entries[] = str_replace('\\', '/', $name);
		}
	}
	$zip->close();

	sort($entries, SORT_STRING);
	return $entries;
}

function vms_public_release_test_read_zip_file(string $zipPath, string $entryPath): string
{
	$zip = new ZipArchive();
	if ($zip->open($zipPath) !== true) {
		throw new RuntimeException('Could not open ZIP fixture for file inspection.');
	}

	$contents = $zip->getFromName($entryPath);
	$zip->close();

	if (!is_string($contents)) {
		throw new RuntimeException('Could not read ZIP entry: ' . $entryPath);
	}

	return $contents;
}

function vms_public_release_test_write_provenance_manifest(string $pluginRoot, string $manifestPath, string $artifactPath): void
{
	$version = trim((string) file_get_contents($pluginRoot . '/vms-build.txt'));
	$artifactSha256 = strtolower((string) (hash_file('sha256', $artifactPath) ?: ''));
	$publicSlug = vms_public_release_test_public_slug();
	$rootMtimeUnix = 0;
	$excludePatterns = array('docs/', 'tests/', 'scripts/', 'provenance/', 'dist/', 'BUILD-NOTES-*.md', '*.zip', '**/*.zip');
	if (class_exists('ZipArchive')) {
		$zip = new ZipArchive();
		if ($zip->open($artifactPath) === true) {
			$stat = $zip->statName($publicSlug . '/');
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
		'slug' => $publicSlug,
		'version' => $version,
		'artifact' => array(
			'filename' => $publicSlug . '-' . $version . '-public-release.zip',
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
	$textDomain = (string) ($overrides['text_domain'] ?? vms_public_release_test_public_slug());
	$internalPluginSlug = (string) ($overrides['internal_plugin_slug'] ?? 'vms');
	$publicSlug = vms_public_release_test_public_slug();

	$entries = array(
		$publicSlug . '/' => '',
		$publicSlug . '/backstage-venue-manager.php' => <<<PHP
<?php
/**
 * Plugin Name: VMS
 * Version: {$headerVersion}
 * Text Domain: {$textDomain}
 */
PHP,
		$publicSlug . '/includes/bootstrap.php' => "<?php\n",
		$publicSlug . '/includes/core/plugin.php' => "<?php\n",
		$publicSlug . '/includes/core/registry/constants.php' => "<?php\ndefine('BVMGR_PLUGIN_SLUG', '" . addslashes($internalPluginSlug) . "');\ndefine('BVMGR_VERSION', '{$constantsVersion}');\n",
		$publicSlug . '/includes/db/migrations.php' => "<?php\nfunction bvmgr_db_migrate_vendor_core_v1(): void {}\n",
		$publicSlug . '/assets/js/app.js' => "console.log('ok');\n",
		$publicSlug . '/uninstall.php' => "<?php\ndefined('WP_UNINSTALL_PLUGIN') || exit;\n",
		$publicSlug . '/vms-build.txt' => $buildVersion . "\n",
	);

	foreach ($overrides as $path => $contents) {
		if (!is_string($path) || strpos($path, $publicSlug . '/') !== 0) {
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
			'plugin_slug' => vms_public_release_test_public_slug(),
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
			'plugin_slug' => vms_public_release_test_public_slug(),
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
			'plugin_slug' => vms_public_release_test_public_slug(),
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
			vms_public_release_test_public_slug() . '/inner.zip' => 'not a real zip but still forbidden',
		));
		vms_public_release_test_create_zip($zipPath, $entries);
		$result = VMS_Public_Release_Tooling::validateTarget($zipPath, array(
			'plugin_slug' => vms_public_release_test_public_slug(),
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
			vms_public_release_test_public_slug() . '/docs/private.md' => '# private',
		));
		vms_public_release_test_create_zip($zipPath, $entries);
		$result = VMS_Public_Release_Tooling::validateTarget($zipPath, array(
			'plugin_slug' => vms_public_release_test_public_slug(),
			'manifest_path' => $pluginRoot . '/release-public-excludes.txt',
		));
		$check = vms_public_release_test_find_check($result['checks'], 'manifest-excludes-honored');
		vms_public_release_test_assert(($check['status'] ?? '') === 'FAIL', 'Expected packaged docs to fail manifest validation.');
	} finally {
		vms_public_release_test_delete_path($workspace);
	}
};

$tests['repository exclusion manifest contains a narrow AGENTS rule'] = static function (): void {
	$manifestPath = dirname(__DIR__) . '/release-public-excludes.txt';
	$manifestLines = file($manifestPath, FILE_IGNORE_NEW_LINES);
	vms_public_release_test_assert(is_array($manifestLines), 'Expected repository release-public-excludes.txt to be readable.');

	$patterns = array();
	foreach ($manifestLines as $line) {
		$line = trim((string) $line);
		if ($line === '' || strpos($line, '#') === 0) {
			continue;
		}
		$patterns[] = $line;
	}

	vms_public_release_test_assert(in_array('AGENTS.md', $patterns, true), 'Expected repository exclusion manifest to exclude AGENTS.md exactly.');
	vms_public_release_test_assert(in_array('includes/safety/', $patterns, true), 'Expected the dormant, unbootstrapped Safety prototype to stay outside public packages.');
	vms_public_release_test_assert(!in_array('*.md', $patterns, true), 'Expected repository exclusion manifest to avoid a broad *.md wildcard.');
	vms_public_release_test_assert(!in_array('**/*.md', $patterns, true), 'Expected repository exclusion manifest to avoid a broad **/*.md wildcard.');
	vms_public_release_test_assert(!in_array('readme.txt', $patterns, true), 'Expected repository exclusion manifest to keep readme.txt packaged.');
};

$tests['agent instructions are excluded from staged and packaged public builds'] = static function (): void {
	$repositoryAgentsPath = dirname(__DIR__) . '/AGENTS.md';
	vms_public_release_test_assert(is_readable($repositoryAgentsPath), 'Expected repository AGENTS.md to exist.');

	$pluginRoot = vms_public_release_test_fixture(array(
		'release_exclude_lines' => array_merge(vms_public_release_test_default_release_excludes(), array('AGENTS.md')),
		'extra_files' => array(
			'AGENTS.md' => "# Internal instructions\n",
			'LICENSE.txt' => "GPL\n",
		),
	));
	$workspace = dirname($pluginRoot);
	$outputDir = vms_public_release_test_temp_dir('vms-release-output-');
	$stagedRoot = $workspace . '/staged/' . vms_public_release_test_public_slug();
	try {
		$manifestPatterns = vms_public_release_test_invoke_private_static('loadExcludeManifest', array($pluginRoot . '/release-public-excludes.txt'));
		$stageResult = vms_public_release_test_invoke_private_static('stagePluginTree', array($pluginRoot, $stagedRoot, $manifestPatterns, array()));

		$agentsExcluded = false;
		foreach ((array) ($stageResult['excluded'] ?? array()) as $entry) {
			if (($entry['path'] ?? '') === 'AGENTS.md' && ($entry['pattern'] ?? '') === 'AGENTS.md') {
				$agentsExcluded = true;
				break;
			}
		}

		vms_public_release_test_assert($agentsExcluded, 'Expected stagePluginTree() to exclude AGENTS.md through the manifest.');
		vms_public_release_test_assert(!file_exists($stagedRoot . '/AGENTS.md'), 'Expected staged public package root to omit AGENTS.md.');

		$report = VMS_Public_Release_Tooling::build(array(
			'plugin_root' => $pluginRoot,
			'output_dir' => $outputDir,
			'force' => true,
			'release_tests' => array(),
		));
		vms_public_release_test_assert(
			($report['status'] ?? '') === 'PASS',
			'Expected valid public build with AGENTS exclusion to pass: ' . json_encode(array_values(array_filter((array) ($report['checks'] ?? array()), static fn(array $check): bool => ($check['status'] ?? '') === 'FAIL')))
		);
		vms_public_release_test_assert(!empty($report['artifact']['created']), 'Expected AGENTS exclusion build to create a ZIP.');

		$zipEntries = vms_public_release_test_read_zip_entries((string) $report['artifact']['zip_path']);
		vms_public_release_test_assert(in_array(vms_public_release_test_public_basename(), $zipEntries, true), 'Expected packaged plugin bootstrap file to remain present.');
		vms_public_release_test_assert(in_array(vms_public_release_test_public_slug() . '/readme.txt', $zipEntries, true), 'Expected readme.txt to remain packaged.');
		vms_public_release_test_assert(in_array(vms_public_release_test_public_slug() . '/LICENSE.txt', $zipEntries, true), 'Expected LICENSE.txt to remain packaged when present.');
		foreach ($zipEntries as $entryName) {
			vms_public_release_test_assert(substr($entryName, -10) !== '/AGENTS.md', 'Expected no packaged path ending in /AGENTS.md.');
		}
	} finally {
		vms_public_release_test_delete_path($workspace);
		vms_public_release_test_delete_path($outputDir);
	}
};

$tests['internal agent instructions fail the build when exclusion is bypassed'] = static function (): void {
	$pluginRoot = vms_public_release_test_fixture(array(
		'extra_files' => array(
			'AGENTS.md' => "# Internal instructions\n",
		),
	));
	$workspace = dirname($pluginRoot);
	$outputDir = vms_public_release_test_temp_dir('vms-release-output-');
	$harnessPath = $workspace . '/run-build.php';
	$reportPath = $workspace . '/build-report.json';
	try {
		$harness = <<<PHP
<?php
declare(strict_types=1);
require_once %s;
\$report = VMS_Public_Release_Tooling::build(array(
	'plugin_root' => %s,
	'output_dir' => %s,
	'force' => true,
	'release_tests' => array(),
));
file_put_contents(%s, json_encode(\$report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
exit((\$report['status'] ?? 'FAIL') === 'PASS' ? 0 : 1);
PHP;
		vms_public_release_test_write_file(
			$harnessPath,
			sprintf(
				$harness,
				var_export(dirname(__DIR__) . '/scripts/lib/public-release.php', true),
				var_export($pluginRoot, true),
				var_export($outputDir, true),
				var_export($reportPath, true)
			)
		);

		$result = vms_public_release_test_run(array(PHP_BINARY, $harnessPath));
		vms_public_release_test_assert($result['exit_code'] !== 0, 'Expected build harness to exit nonzero when AGENTS.md reaches staged validation.');

		$reportJson = file_get_contents($reportPath);
		$report = json_decode(is_string($reportJson) ? $reportJson : '', true);
		vms_public_release_test_assert(is_array($report), 'Expected build harness to write a JSON report.');

		$check = vms_public_release_test_find_check((array) ($report['checks'] ?? array()), 'internal-instruction-files-excluded');
		vms_public_release_test_assert(($check['status'] ?? '') === 'FAIL', 'Expected internal instruction guard to fail when AGENTS.md is staged.');
		vms_public_release_test_assert(
			($check['message'] ?? '') === 'Internal development instruction file must not be included in the public package: AGENTS.md',
			'Expected internal instruction guard diagnostic to name AGENTS.md exactly.'
		);
		vms_public_release_test_assert(empty($report['artifact']['created']), 'Expected failing AGENTS build to avoid reporting a created ZIP.');
		vms_public_release_test_assert(!is_readable((string) ($report['artifact']['zip_path'] ?? '')), 'Expected no readable ZIP artifact after AGENTS guard failure.');
		vms_public_release_test_assert(glob($outputDir . '/*.zip') === array(), 'Expected no ZIP files to remain after AGENTS guard failure.');
	} finally {
		vms_public_release_test_delete_path($workspace);
		vms_public_release_test_delete_path($outputDir);
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
		vms_public_release_test_assert(
			($report['artifact']['filename'] ?? '') === 'backstage-venue-manager-1.2.3-public-release.zip',
			'Expected public artifact filename to use the WordPress.org slug.'
		);
		vms_public_release_test_assert(
			($report['metadata']['public_plugin_slug'] ?? '') === vms_public_release_test_public_slug(),
			'Expected report metadata to record the explicit public plugin slug.'
		);
		vms_public_release_test_assert(
			($report['metadata']['internal_plugin_slug'] ?? '') === 'vms',
			'Expected the internal plugin slug to remain vms.'
		);
		vms_public_release_test_assert(
			($report['metadata']['header_text_domain'] ?? '') === vms_public_release_test_public_slug(),
			'Expected the fixture header text domain to match the public slug.'
		);
		$slugCheck = vms_public_release_test_find_check($report['checks'], 'slug-and-text-domain');
		vms_public_release_test_assert(($slugCheck['status'] ?? '') === 'PASS', 'Expected explicit public slug/text-domain validation to pass.');
		$zipEntries = vms_public_release_test_read_zip_entries((string) $report['artifact']['zip_path']);
		vms_public_release_test_assert($zipEntries !== array(), 'Expected the public-release ZIP to contain entries.');
		foreach ($zipEntries as $entryName) {
			vms_public_release_test_assert(
				strpos($entryName, vms_public_release_test_public_slug() . '/') === 0,
				'Expected every ZIP entry to use the public package root.'
			);
			vms_public_release_test_assert(strpos($entryName, 'vms/') !== 0, 'Expected no internal vms/ public package root.');
			vms_public_release_test_assert(strpos($entryName, 'vms-github-reconcile/') !== 0, 'Expected no source checkout root in the public package.');
		}
		vms_public_release_test_assert(
			in_array(vms_public_release_test_public_basename(), $zipEntries, true),
			'Expected the public package bootstrap path to exist.'
		);
		vms_public_release_test_assert(
			!in_array('vms/vendor-management-system.php', $zipEntries, true),
			'Expected the internal bootstrap basename to stay out of the public ZIP.'
		);
	} finally {
		vms_public_release_test_delete_path(dirname($pluginRoot));
		vms_public_release_test_delete_path($outputDir);
	}
};

$tests['text domain mismatch fails the explicit public package slug check'] = static function (): void {
	$pluginRoot = vms_public_release_test_fixture(array(
		'text_domain' => 'vms',
	));
	$outputDir = vms_public_release_test_temp_dir('vms-release-output-');
	try {
		$report = VMS_Public_Release_Tooling::build(array(
			'plugin_root' => $pluginRoot,
			'output_dir' => $outputDir,
			'force' => true,
			'release_tests' => array(),
		));
		$check = vms_public_release_test_find_check($report['checks'], 'slug-and-text-domain');
		vms_public_release_test_assert(($check['status'] ?? '') === 'FAIL', 'Expected text-domain mismatch to fail the explicit public slug check.');
		vms_public_release_test_assert(($report['status'] ?? '') === 'FAIL', 'Expected the build to fail on a public slug/text-domain mismatch.');
	} finally {
		vms_public_release_test_delete_path(dirname($pluginRoot));
		vms_public_release_test_delete_path($outputDir);
	}
};

$tests['internal plugin slug changes do not redefine the public package slug'] = static function (): void {
	$pluginRoot = vms_public_release_test_fixture(array(
		'internal_plugin_slug' => 'vmstest',
	));
	$outputDir = vms_public_release_test_temp_dir('vms-release-output-');
	try {
		$report = VMS_Public_Release_Tooling::build(array(
			'plugin_root' => $pluginRoot,
			'output_dir' => $outputDir,
			'force' => true,
			'release_tests' => array(),
		));
		vms_public_release_test_assert(($report['status'] ?? '') === 'PASS', 'Expected the build to preserve the explicit public slug when the internal slug changes.');
		vms_public_release_test_assert(
			($report['metadata']['internal_plugin_slug'] ?? '') === 'vmstest',
			'Expected the internal fixture slug to be reported separately.'
		);
		vms_public_release_test_assert(
			($report['metadata']['public_plugin_slug'] ?? '') === vms_public_release_test_public_slug(),
			'Expected the public package slug to remain fixed.'
		);
		vms_public_release_test_assert(
			($report['artifact']['filename'] ?? '') === 'backstage-venue-manager-1.2.3-public-release.zip',
			'Expected the public artifact filename to remain independent from the internal slug.'
		);
		$zipEntries = vms_public_release_test_read_zip_entries((string) $report['artifact']['zip_path']);
		vms_public_release_test_assert(
			in_array(vms_public_release_test_public_basename(), $zipEntries, true),
			'Expected the public bootstrap path to remain fixed when the internal slug changes.'
		);
	} finally {
		vms_public_release_test_delete_path(dirname($pluginRoot));
		vms_public_release_test_delete_path($outputDir);
	}
};

$tests['repository public boundary packages the current 1.2.0 public release markers'] = static function (): void {
	$pluginRoot = dirname(__DIR__);
	$outputDir = vms_public_release_test_temp_dir('vms release current boundary ');
	try {
		$report = VMS_Public_Release_Tooling::build(array(
			'plugin_root' => $pluginRoot,
			'output_dir' => $outputDir,
			'force' => true,
			'allow_dirty' => true,
			'release_tests' => array(),
		));
		vms_public_release_test_assert(($report['status'] ?? '') === 'PASS', 'Expected the current repository public build to pass.');
		vms_public_release_test_assert(
			($report['metadata']['header_version'] ?? '') === '1.2.0',
			'Expected the current repository header version to resolve to 1.2.0.'
		);
		vms_public_release_test_assert(
			($report['metadata']['version'] ?? '') === '1.2.0',
			'Expected the current repository BVMGR_VERSION to resolve to 1.2.0.'
		);
		vms_public_release_test_assert(
			($report['metadata']['build_version'] ?? '') === '1.2.0',
			'Expected the current repository build marker to resolve to 1.2.0.'
		);
		vms_public_release_test_assert(
			($report['artifact']['filename'] ?? '') === 'backstage-venue-manager-1.2.0-public-release.zip',
			'Expected the current repository artifact filename to derive from 1.2.0.'
		);

		$zipPath = (string) ($report['artifact']['zip_path'] ?? '');
		$zipEntries = vms_public_release_test_read_zip_entries($zipPath);
		vms_public_release_test_assert(
			in_array(vms_public_release_test_public_slug() . '/', $zipEntries, true),
			'Expected the public ZIP root directory to remain backstage-venue-manager/.'
		);

		$packagedFiles = array_values(array_filter($zipEntries, static function (string $entryName): bool {
			return substr($entryName, -1) !== '/';
		}));
		vms_public_release_test_assert(count($packagedFiles) === 381, 'Expected the integrated repository public package boundary to contain the 375 B4 files plus exactly six event-occurrence runtime files.');

		foreach ($zipEntries as $entryName) {
			vms_public_release_test_assert(substr($entryName, -10) !== '/AGENTS.md', 'Expected AGENTS.md to stay out of the packaged public ZIP.');
			vms_public_release_test_assert(strpos($entryName, '/outreach/') === false, 'Expected Outreach runtime paths to stay out of the packaged public ZIP.');
			vms_public_release_test_assert(strpos($entryName, '/includes/safety/') === false, 'Expected the dormant Safety prototype to stay out of the packaged public ZIP.');
		}

		$packagedHeader = vms_public_release_test_read_zip_file($zipPath, vms_public_release_test_public_basename());
		$packagedLegacyBridge = vms_public_release_test_read_zip_file($zipPath, vms_public_release_test_public_slug() . '/vendor-management-system.php');
		$packagedConstants = vms_public_release_test_read_zip_file($zipPath, vms_public_release_test_public_slug() . '/includes/core/registry/constants.php');
		$packagedReadme = vms_public_release_test_read_zip_file($zipPath, vms_public_release_test_public_slug() . '/readme.txt');
		$packagedBuild = vms_public_release_test_read_zip_file($zipPath, vms_public_release_test_public_slug() . '/vms-build.txt');

		vms_public_release_test_assert(strpos($packagedHeader, 'Version: 1.2.0') !== false, 'Expected the packaged plugin header version to resolve to 1.2.0.');
		vms_public_release_test_assert($packagedLegacyBridge !== '', 'Expected the headerless legacy filename bridge to remain in the public package.');
		vms_public_release_test_assert(preg_match('/^\s*\*\s*Plugin Name:/m', $packagedLegacyBridge) !== 1, 'Expected the legacy filename bridge to avoid a duplicate plugin header.');
		vms_public_release_test_assert(
			strpos($packagedConstants, "define('BVMGR_VERSION', '1.2.0');") !== false,
			'Expected the packaged BVMGR_VERSION constant to resolve to 1.2.0.'
		);
		vms_public_release_test_assert(strpos($packagedReadme, 'Stable tag: 1.2.0') !== false, 'Expected the packaged readme stable tag to resolve to 1.2.0.');
		vms_public_release_test_assert(substr_count($packagedReadme, '= 1.2.0 =') >= 2, 'Expected the packaged readme to contain the 1.2.0 changelog and upgrade-notice sections.');
		vms_public_release_test_assert(trim($packagedBuild) === '1.2.0', 'Expected the packaged build marker to resolve to 1.2.0.');

		vms_public_release_test_assert(
			$packagedHeader === (string) file_get_contents($pluginRoot . '/backstage-venue-manager.php'),
			'Expected the packaged plugin header file to match the mirror source.'
		);
		vms_public_release_test_assert(
			$packagedConstants === (string) file_get_contents($pluginRoot . '/includes/core/registry/constants.php'),
			'Expected the packaged constants file to match the mirror source.'
		);
		vms_public_release_test_assert(
			$packagedReadme === (string) file_get_contents($pluginRoot . '/readme.txt'),
			'Expected the packaged readme to match the mirror source.'
		);
		vms_public_release_test_assert(
			$packagedBuild === (string) file_get_contents($pluginRoot . '/vms-build.txt'),
			'Expected the packaged build marker file to match the mirror source.'
		);
	} finally {
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

$tests['wordpress bootstrap resolver finds nested wp-load'] = static function (): void {
	$pluginRoot = vms_public_release_test_fixture();
	$workspace = dirname($pluginRoot);
	$nestedPluginRoot = $workspace . '/app/public/wp-content/plugins/packages/vms';
	try {
		if (!mkdir(dirname($nestedPluginRoot), 0775, true) && !is_dir(dirname($nestedPluginRoot))) {
			throw new RuntimeException('Could not create nested plugin fixture path.');
		}
		vms_public_release_test_assert(rename($pluginRoot, $nestedPluginRoot), 'Could not move plugin fixture into nested path.');
		vms_public_release_test_write_file(
			$workspace . '/app/public/wp-load.php',
			"<?php\ndefine('ABSPATH', __DIR__ . '/');\necho \"FAKE_WP_LOAD_OK\\n\";\n"
		);
		vms_public_release_test_write_file(
			$nestedPluginRoot . '/tests/bootstrap-smoke.php',
			"<?php\ndeclare(strict_types=1);\nrequire_once " . var_export(dirname(__FILE__) . '/bootstrap-wordpress.php', true) . ";\nvms_tests_require_wordpress(__DIR__);\necho defined('ABSPATH') ? \"BOOTSTRAP_OK\\n\" : \"BOOTSTRAP_FAIL\\n\";\n"
		);

		$result = vms_public_release_test_run(array(PHP_BINARY, $nestedPluginRoot . '/tests/bootstrap-smoke.php'));
		vms_public_release_test_assert($result['exit_code'] === 0, 'Expected nested bootstrap smoke to succeed.');
		vms_public_release_test_assert(strpos($result['stdout'], 'FAKE_WP_LOAD_OK') !== false, 'Expected fake wp-load.php to be loaded.');
		vms_public_release_test_assert(strpos($result['stdout'], 'BOOTSTRAP_OK') !== false, 'Expected bootstrap helper to define ABSPATH.');
	} finally {
		vms_public_release_test_delete_path($workspace);
	}
};

$passed = 0;

foreach ($tests as $label => $callback) {
	$callback();
	$passed++;
}

fwrite(STDOUT, "Public release build pipeline tests OK ({$passed} cases).\n");
