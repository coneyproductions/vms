<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/scripts/lib/public-release.php';

$GLOBALS['bvmgr_repro_assertions'] = 0;

function bvmgr_repro_assert(bool $condition, string $message): void
{
	$GLOBALS['bvmgr_repro_assertions']++;
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function bvmgr_repro_same($expected, $actual, string $message): void
{
	bvmgr_repro_assert(
		$expected === $actual,
		$message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true)
	);
}

/** @return array{exit_code:int,stdout:string,stderr:string} */
function bvmgr_repro_run(array $command, ?string $cwd = null): array
{
	$process = proc_open(
		$command,
		array(
			0 => array('pipe', 'r'),
			1 => array('pipe', 'w'),
			2 => array('pipe', 'w'),
		),
		$pipes,
		$cwd
	);
	bvmgr_repro_assert(is_resource($process), 'Unable to start command: ' . implode(' ', $command));
	fclose($pipes[0]);
	$stdout = stream_get_contents($pipes[1]);
	$stderr = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);

	return array(
		'exit_code' => proc_close($process),
		'stdout' => is_string($stdout) ? $stdout : '',
		'stderr' => is_string($stderr) ? $stderr : '',
	);
}

function bvmgr_repro_write(string $path, string $contents): void
{
	$directory = dirname($path);
	if (!is_dir($directory)) {
		bvmgr_repro_assert(mkdir($directory, 0775, true), 'Unable to create fixture directory: ' . $directory);
	}
	bvmgr_repro_assert(file_put_contents($path, $contents) !== false, 'Unable to write fixture file: ' . $path);
}

function bvmgr_repro_delete_path(string $path): void
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
		} else {
			@unlink($item->getPathname());
		}
	}
	@rmdir($path);
}

function bvmgr_repro_touch_source(string $sourceRoot, int $timestamp): void
{
	$directoryIterator = new RecursiveDirectoryIterator($sourceRoot, FilesystemIterator::SKIP_DOTS);
	$filter = new RecursiveCallbackFilterIterator(
		$directoryIterator,
		static function (SplFileInfo $fileInfo): bool {
			return $fileInfo->getFilename() !== '.git';
		}
	);
	$iterator = new RecursiveIteratorIterator($filter, RecursiveIteratorIterator::CHILD_FIRST);
	foreach ($iterator as $item) {
		/** @var SplFileInfo $item */
		bvmgr_repro_assert(touch($item->getPathname(), $timestamp), 'Unable to assign source mtime: ' . $item->getPathname());
	}
	bvmgr_repro_assert(touch($sourceRoot, $timestamp), 'Unable to assign source-root mtime.');
}

/** @return array{order:array<int,string>,mtimes:array<string,int>,content_hashes:array<string,string>} */
function bvmgr_repro_zip_inventory(string $zipPath): array
{
	$zip = new ZipArchive();
	bvmgr_repro_same(true, $zip->open($zipPath), 'Unable to open reproducibility ZIP.');
	$order = array();
	$mtimes = array();
	$contentHashes = array();
	for ($index = 0; $index < $zip->numFiles; $index++) {
		$name = $zip->getNameIndex($index);
		$stat = $zip->statIndex($index);
		bvmgr_repro_assert(is_string($name) && $name !== '' && is_array($stat), 'Unable to inspect ZIP entry at index ' . $index . '.');
		$order[] = $name;
		$mtimes[$name] = (int) ($stat['mtime'] ?? 0);
		if (substr($name, -1) !== '/') {
			$contents = $zip->getFromIndex($index);
			bvmgr_repro_assert(is_string($contents), 'Unable to read ZIP entry: ' . $name);
			$contentHashes[$name] = hash('sha256', $contents);
		}
	}
	$zip->close();

	return array('order' => $order, 'mtimes' => $mtimes, 'content_hashes' => $contentHashes);
}

/** @return array<string,string> */
function bvmgr_repro_extracted_hashes(string $zipPath, string $extractRoot): array
{
	$zip = new ZipArchive();
	bvmgr_repro_same(true, $zip->open($zipPath), 'Unable to open ZIP for extraction.');
	bvmgr_repro_assert($zip->extractTo($extractRoot), 'Unable to extract reproducibility ZIP.');
	$zip->close();

	$hashes = array();
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($extractRoot, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::LEAVES_ONLY
	);
	foreach ($iterator as $fileInfo) {
		/** @var SplFileInfo $fileInfo */
		if (!$fileInfo->isFile()) {
			continue;
		}
		$path = $fileInfo->getPathname();
		$relativePath = ltrim(str_replace('\\', '/', substr($path, strlen($extractRoot))), '/');
		$hash = hash_file('sha256', $path);
		bvmgr_repro_assert(is_string($hash), 'Unable to hash extracted file: ' . $relativePath);
		$hashes[$relativePath] = $hash;
	}
	ksort($hashes, SORT_STRING);

	return $hashes;
}

function bvmgr_repro_restore_env(string $name, $value): void
{
	if ($value === false) {
		putenv($name);
	} else {
		putenv($name . '=' . $value);
	}
}

function bvmgr_repro_failure_summary(array $report): string
{
	$failures = array();
	foreach ((array) ($report['checks'] ?? array()) as $check) {
		if (($check['status'] ?? '') === 'FAIL') {
			$details = json_encode($check['details'] ?? array(), JSON_UNESCAPED_SLASHES);
			$failures[] = (string) ($check['id'] ?? 'unknown') . ': ' . (string) ($check['message'] ?? '') . ' ' . (is_string($details) ? $details : '');
		}
	}

	return $failures === array() ? 'no failing check was recorded' : implode('; ', $failures);
}

$tempRoot = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'bvmgr-release-repro-' . bin2hex(random_bytes(8));
$sourceRoot = $tempRoot . '/source';
$outputA = $tempRoot . '/build-a';
$outputB = $tempRoot . '/build-b';
$outputC = $tempRoot . '/build-source-date-epoch';
$oldSourceDateEpoch = getenv('SOURCE_DATE_EPOCH');
$oldAuthorDate = getenv('GIT_AUTHOR_DATE');
$oldCommitterDate = getenv('GIT_COMMITTER_DATE');
$oldTimezone = date_default_timezone_get();

try {
	$files = array(
		'backstage-venue-manager.php' => "<?php\n/**\n * Plugin Name: Backstage Venue Manager\n * Version: 1.2.3\n * Text Domain: backstage-venue-manager\n * Requires at least: 6.8\n * Requires PHP: 8.3\n */\ndefined('ABSPATH') || exit;\n",
		'includes/bootstrap.php' => "<?php\ndefined('ABSPATH') || exit;\nrequire_once __DIR__ . '/core/plugin.php';\n",
		'includes/core/plugin.php' => "<?php\ndefined('ABSPATH') || exit;\n",
		'includes/core/registry/constants.php' => "<?php\ndefined('ABSPATH') || exit;\ndefine('BVMGR_PLUGIN_SLUG', 'vms');\ndefine('BVMGR_VERSION', '1.2.3');\n",
		'includes/db/migrations.php' => "<?php\ndefined('ABSPATH') || exit;\nfunction bvmgr_db_migrate_vendor_core_v1(): void {}\n",
		'includes/activation.php' => "<?php\ndefined('ABSPATH') || exit;\nif (function_exists('bvmgr_db_migrate_vendor_core_v1')) { bvmgr_db_migrate_vendor_core_v1(); }\n",
		'assets/css/app.css' => ".bvmgr-repro { display: block; }\n",
		'readme.txt' => "=== Backstage Venue Manager ===\nStable tag: 1.2.3\n",
		'vms-build.txt' => "1.2.3\n",
		'BUILD-NOTES-1.2.3.md' => "# Build Notes 1.2.3\n",
		'release-public-excludes.txt' => "# Reproducibility fixture exclusions\n.git/\ntests/\nscripts/\ndocs/\ndist/\nBUILD-NOTES-*.md\n*.zip\n**/*.zip\n",
	);
	foreach ($files as $relativePath => $contents) {
		bvmgr_repro_write($sourceRoot . '/' . $relativePath, $contents);
	}

	foreach (array(
		array('git', '-C', $sourceRoot, 'init', '-q'),
		array('git', '-C', $sourceRoot, 'config', 'user.email', 'reproducibility@example.test'),
		array('git', '-C', $sourceRoot, 'config', 'user.name', 'Reproducibility Fixture'),
		array('git', '-C', $sourceRoot, 'add', '.'),
	) as $command) {
		$result = bvmgr_repro_run($command);
		bvmgr_repro_same(0, $result['exit_code'], 'Git fixture setup failed: ' . trim($result['stderr']));
	}
	putenv('GIT_AUTHOR_DATE=2024-04-05T06:07:05Z');
	putenv('GIT_COMMITTER_DATE=2024-04-05T06:07:05Z');
	$commit = bvmgr_repro_run(array('git', '-C', $sourceRoot, 'commit', '-qm', 'reproducible fixture'));
	bvmgr_repro_same(0, $commit['exit_code'], 'Unable to commit reproducibility fixture: ' . trim($commit['stderr']));
	bvmgr_repro_restore_env('GIT_AUTHOR_DATE', $oldAuthorDate);
	bvmgr_repro_restore_env('GIT_COMMITTER_DATE', $oldCommitterDate);
	putenv('SOURCE_DATE_EPOCH');

	bvmgr_repro_touch_source($sourceRoot, 946684800);
	date_default_timezone_set('America/Chicago');
	$reportA = VMS_Public_Release_Tooling::build(array(
		'plugin_root' => $sourceRoot,
		'output_dir' => $outputA,
		'force' => true,
		'release_tests' => array(),
	));
	bvmgr_repro_same('PASS', $reportA['status'] ?? null, 'Reproducibility build A failed: ' . bvmgr_repro_failure_summary($reportA));

	bvmgr_repro_touch_source($sourceRoot, 1893456000);
	date_default_timezone_set('Asia/Tokyo');
	$reportB = VMS_Public_Release_Tooling::build(array(
		'plugin_root' => $sourceRoot,
		'output_dir' => $outputB,
		'force' => true,
		'release_tests' => array(),
	));
	bvmgr_repro_same('PASS', $reportB['status'] ?? null, 'Reproducibility build B failed: ' . bvmgr_repro_failure_summary($reportB));

	$zipA = (string) ($reportA['artifact']['zip_path'] ?? '');
	$zipB = (string) ($reportB['artifact']['zip_path'] ?? '');
	bvmgr_repro_same(filesize($zipA), filesize($zipB), 'Identical-source ZIP sizes differ after source mtime changes.');
	bvmgr_repro_same(hash_file('sha256', $zipA), hash_file('sha256', $zipB), 'Identical-source ZIP SHA-256 differs after source mtime changes.');
	bvmgr_repro_same('git-commit-timestamp', $reportA['artifact']['timestamp_policy']['source'] ?? null, 'Build A did not use the Git commit timestamp fallback.');
	bvmgr_repro_same('git-commit-timestamp', $reportB['artifact']['timestamp_policy']['source'] ?? null, 'Build B did not use the Git commit timestamp fallback.');
	bvmgr_repro_same($reportA['artifact']['timestamp_policy'], $reportB['artifact']['timestamp_policy'], 'Identical-source timestamp-policy records differ.');
	$effectiveFallback = (int) ($reportA['artifact']['timestamp_policy']['effective_timestamp_unix'] ?? 0);
	bvmgr_repro_same(0, $effectiveFallback % 2, 'Fallback ZIP timestamp is not DOS-even-second safe.');

	$inventoryA = bvmgr_repro_zip_inventory($zipA);
	$inventoryB = bvmgr_repro_zip_inventory($zipB);
	bvmgr_repro_same($inventoryA['order'], $inventoryB['order'], 'Identical-source ZIP entry order differs.');
	bvmgr_repro_same($inventoryA['mtimes'], $inventoryB['mtimes'], 'Identical-source per-entry ZIP timestamps differ.');
	bvmgr_repro_same($inventoryA['content_hashes'], $inventoryB['content_hashes'], 'Identical-source ZIP entry contents differ.');
	foreach ($inventoryA['mtimes'] as $entryName => $entryMtime) {
		bvmgr_repro_same($effectiveFallback, $entryMtime, 'ZIP entry does not use the canonical fallback timestamp: ' . $entryName);
	}
	$extractedA = bvmgr_repro_extracted_hashes($zipA, $tempRoot . '/extract-a');
	$extractedB = bvmgr_repro_extracted_hashes($zipB, $tempRoot . '/extract-b');
	bvmgr_repro_same($extractedA, $extractedB, 'Extracted file hashes differ after source mtime changes.');

	putenv('SOURCE_DATE_EPOCH=1700000001');
	date_default_timezone_set('Pacific/Honolulu');
	$reportC = VMS_Public_Release_Tooling::build(array(
		'plugin_root' => $sourceRoot,
		'output_dir' => $outputC,
		'force' => true,
		'release_tests' => array(),
	));
	bvmgr_repro_same('PASS', $reportC['status'] ?? null, 'SOURCE_DATE_EPOCH build failed: ' . bvmgr_repro_failure_summary($reportC));
	bvmgr_repro_same('SOURCE_DATE_EPOCH', $reportC['artifact']['timestamp_policy']['source'] ?? null, 'Explicit SOURCE_DATE_EPOCH was not honored.');
	bvmgr_repro_same(1700000001, $reportC['artifact']['timestamp_policy']['input_timestamp_unix'] ?? null, 'SOURCE_DATE_EPOCH input was not recorded.');
	bvmgr_repro_same(1700000000, $reportC['artifact']['timestamp_policy']['effective_timestamp_unix'] ?? null, 'SOURCE_DATE_EPOCH was not normalized to an even second.');
	$zipC = (string) ($reportC['artifact']['zip_path'] ?? '');
	bvmgr_repro_assert(hash_file('sha256', $zipC) !== hash_file('sha256', $zipA), 'Changing SOURCE_DATE_EPOCH did not change ZIP metadata/hash.');
	$inventoryC = bvmgr_repro_zip_inventory($zipC);
	bvmgr_repro_same($inventoryA['order'], $inventoryC['order'], 'SOURCE_DATE_EPOCH changed ZIP entry ordering.');
	bvmgr_repro_same($inventoryA['content_hashes'], $inventoryC['content_hashes'], 'SOURCE_DATE_EPOCH changed ZIP runtime contents.');
	foreach ($inventoryC['mtimes'] as $entryName => $entryMtime) {
		bvmgr_repro_same(1700000000, $entryMtime, 'SOURCE_DATE_EPOCH was not applied to ZIP entry: ' . $entryName);
	}
	$extractedC = bvmgr_repro_extracted_hashes($zipC, $tempRoot . '/extract-c');
	bvmgr_repro_same($extractedA, $extractedC, 'SOURCE_DATE_EPOCH changed extracted runtime contents.');

	$status = bvmgr_repro_run(array('git', '-C', $sourceRoot, 'status', '--short'));
	bvmgr_repro_same(0, $status['exit_code'], 'Unable to inspect reproducibility fixture status.');
	bvmgr_repro_same('', trim($status['stdout']), 'Filesystem mtime manipulation made the source fixture dirty.');
} finally {
	bvmgr_repro_restore_env('SOURCE_DATE_EPOCH', $oldSourceDateEpoch);
	bvmgr_repro_restore_env('GIT_AUTHOR_DATE', $oldAuthorDate);
	bvmgr_repro_restore_env('GIT_COMMITTER_DATE', $oldCommitterDate);
	date_default_timezone_set($oldTimezone);
	bvmgr_repro_delete_path($tempRoot);
}

fwrite(STDOUT, "PASS: {$GLOBALS['bvmgr_repro_assertions']} reproducible public-release build assertions.\n");
