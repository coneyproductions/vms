<?php
declare(strict_types=1);

const BVMGR_G14_SOURCE_COMMIT = '2c8f790b9128d547b8bc0a27a714253fb6671bea';
const BVMGR_G14_PACKAGE_ROOT = 'backstage-venue-manager';
const BVMGR_G14_RELEASE_VERSION = '1.2.0';

function bvmgr_g14_fail(string $message): void
{
	fwrite(STDERR, 'ERROR: ' . $message . PHP_EOL);
	exit(1);
}

/** @return array{exit_code:int,stdout:string,stderr:string} */
function bvmgr_g14_run(array $command, ?string $cwd = null): array
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
	if (!is_resource($process)) {
		throw new RuntimeException('Unable to start command: ' . implode(' ', $command));
	}
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

function bvmgr_g14_delete_path(string $path): void
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

/** @return array<int,string> */
function bvmgr_g14_exclusion_rules(string $manifestPath): array
{
	$lines = file($manifestPath, FILE_IGNORE_NEW_LINES);
	if (!is_array($lines)) {
		throw new RuntimeException('Unable to read release-public-excludes.txt.');
	}

	$rules = array();
	foreach ($lines as $line) {
		$line = trim((string) $line);
		if ($line === '' || strpos($line, '#') === 0) {
			continue;
		}
		$rules[] = ltrim(str_replace('\\', '/', $line), '/');
	}

	return $rules;
}

/** @return array<int,array{path:string,type:string,size_bytes:int,mode:string,sha256:string}> */
function bvmgr_g14_collect_files(string $stagedRoot): array
{
	$files = array();
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($stagedRoot, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::LEAVES_ONLY
	);
	foreach ($iterator as $fileInfo) {
		/** @var SplFileInfo $fileInfo */
		if ($fileInfo->isLink() || !$fileInfo->isFile()) {
			throw new RuntimeException('Historical package staging produced an unsupported entry: ' . $fileInfo->getPathname());
		}

		$absolutePath = $fileInfo->getPathname();
		$relativePath = ltrim(str_replace('\\', '/', substr($absolutePath, strlen($stagedRoot))), '/');
		$sha256 = hash_file('sha256', $absolutePath);
		if (!is_string($sha256) || $sha256 === '') {
			throw new RuntimeException('Unable to hash staged file: ' . $relativePath);
		}

		$files[] = array(
			'path' => $relativePath,
			'type' => 'regular_file',
			'size_bytes' => (int) $fileInfo->getSize(),
			'mode' => substr(sprintf('%04o', $fileInfo->getPerms() & 07777), -4),
			'sha256' => strtolower($sha256),
		);
	}

	usort(
		$files,
		static fn(array $left, array $right): int => strcmp((string) $left['path'], (string) $right['path'])
	);

	return $files;
}

$options = getopt('', array('source:', 'output:', 'allow-exported-source'));
$sourceRoot = isset($options['source']) && is_string($options['source']) ? realpath($options['source']) : false;
$outputPath = isset($options['output']) && is_string($options['output']) ? $options['output'] : '';
$allowExportedSource = array_key_exists('allow-exported-source', $options);

if (!is_string($sourceRoot) || $sourceRoot === '' || !is_dir($sourceRoot)) {
	bvmgr_g14_fail('Use --source with a readable historical source root.');
}
if ($outputPath === '') {
	bvmgr_g14_fail('Use --output with the deterministic manifest destination.');
}

$requiredFiles = array(
	'scripts/lib/public-release.php',
	'release-public-excludes.txt',
	'vms-build.txt',
);
foreach ($requiredFiles as $requiredFile) {
	if (!is_readable($sourceRoot . '/' . $requiredFile)) {
		bvmgr_g14_fail('Historical source is missing required file: ' . $requiredFile);
	}
}

$gitState = bvmgr_g14_run(array('git', '-C', $sourceRoot, 'rev-parse', '--show-toplevel'));
if ($gitState['exit_code'] === 0) {
	$repoRoot = realpath(trim($gitState['stdout']));
	if (!is_string($repoRoot) || $repoRoot !== $sourceRoot) {
		bvmgr_g14_fail('Historical source must be the root of its clean Git worktree.');
	}
	$head = bvmgr_g14_run(array('git', '-C', $sourceRoot, 'rev-parse', 'HEAD'));
	if ($head['exit_code'] !== 0 || trim($head['stdout']) !== BVMGR_G14_SOURCE_COMMIT) {
		bvmgr_g14_fail('Historical source HEAD does not match ' . BVMGR_G14_SOURCE_COMMIT . '.');
	}
	$status = bvmgr_g14_run(array('git', '-C', $sourceRoot, 'status', '--short', '--untracked-files=all'));
	if ($status['exit_code'] !== 0 || trim($status['stdout']) !== '') {
		bvmgr_g14_fail('Historical source worktree is not clean.');
	}
} elseif (!$allowExportedSource) {
	bvmgr_g14_fail('Historical source is not a Git worktree; --allow-exported-source is reserved for a verified git archive.');
}

if (trim((string) file_get_contents($sourceRoot . '/vms-build.txt')) !== BVMGR_G14_RELEASE_VERSION) {
	bvmgr_g14_fail('Historical public release version is not ' . BVMGR_G14_RELEASE_VERSION . '.');
}

$tempRoot = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'bvmgr-g14-manifest-' . bin2hex(random_bytes(8));
$stagedRoot = $tempRoot . DIRECTORY_SEPARATOR . BVMGR_G14_PACKAGE_ROOT;

try {
	require $sourceRoot . '/scripts/lib/public-release.php';
	$loadExcludes = new ReflectionMethod(VMS_Public_Release_Tooling::class, 'loadExcludeManifest');
	$stagePluginTree = new ReflectionMethod(VMS_Public_Release_Tooling::class, 'stagePluginTree');
	$excludeManifest = $sourceRoot . '/release-public-excludes.txt';
	$patterns = $loadExcludes->invoke(null, $excludeManifest);
	$stageResult = $stagePluginTree->invoke(null, $sourceRoot, $stagedRoot, $patterns, array());
	if (!is_array($stageResult) || !empty($stageResult['symlinks'])) {
		throw new RuntimeException('Canonical historical staging encountered symlink entries.');
	}

	$files = bvmgr_g14_collect_files($stagedRoot);
	if (count($files) !== 372 || (int) ($stageResult['file_count'] ?? 0) !== 372) {
		throw new RuntimeException('Canonical historical staging must produce exactly 372 regular files.');
	}

	$manifest = array(
		'schema_version' => 1,
		'manifest_version' => 'g14-public-package-content-v1',
		'historical_source' => array(
			'commit' => BVMGR_G14_SOURCE_COMMIT,
			'public_release_version' => BVMGR_G14_RELEASE_VERSION,
		),
		'package' => array(
			'root_name' => BVMGR_G14_PACKAGE_ROOT,
			'file_count' => 372,
			'regular_file_count' => 372,
			'symlink_count' => 0,
			'other_entry_count' => 0,
		),
		'staging' => array(
			'implementation' => 'VMS_Public_Release_Tooling::stagePluginTree',
			'exclude_manifest' => 'release-public-excludes.txt',
			'exclude_manifest_sha256' => hash_file('sha256', $excludeManifest),
			'exclusion_rules' => bvmgr_g14_exclusion_rules($excludeManifest),
			'regular_file_mode_policy' => '0644',
		),
		'content_identity' => array(
			'path_normalization' => 'forward-slash relative paths without package root prefix',
			'ordering' => 'bytewise ascending normalized path',
			'content_hash' => 'sha256',
			'filesystem_mtimes' => 'excluded',
		),
		'files' => $files,
	);

	$json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
	if (!is_string($json)) {
		throw new RuntimeException('Unable to encode deterministic historical manifest.');
	}
	$outputDir = dirname($outputPath);
	if (!is_dir($outputDir) && !mkdir($outputDir, 0775, true) && !is_dir($outputDir)) {
		throw new RuntimeException('Unable to create manifest output directory.');
	}
	if (file_put_contents($outputPath, $json . PHP_EOL) === false) {
		throw new RuntimeException('Unable to write deterministic historical manifest.');
	}

	fwrite(STDOUT, sprintf("Wrote %d files to %s\nSHA-256: %s\n", count($files), $outputPath, hash('sha256', $json . PHP_EOL)));
} catch (Throwable $throwable) {
	bvmgr_g14_fail($throwable->getMessage());
} finally {
	bvmgr_g14_delete_path($tempRoot);
}
