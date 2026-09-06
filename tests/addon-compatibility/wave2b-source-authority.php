<?php
declare(strict_types=1);

$repoRoot = dirname(__DIR__, 2);
$pluginsRoot = dirname($repoRoot, 2);
$bridgeRoot = $pluginsRoot . '/drm-events-bridge';
$routerRoot = $pluginsRoot . '/drm-event-router';
$routerRepo = '/Users/treyconey/Downloads/drm-event-router-source/drm-event-router';
$intakeRoot = $pluginsRoot . '/drm-calendar-intake';
$sponsorshipsActive = $pluginsRoot . '/vms-sponsorships';
$sponsorshipsCandidate = $pluginsRoot . '/packages/vms-sponsorships';
$sponsorshipsArchive = $pluginsRoot . '/vms-sponsorships-0.1.27-rc1.zip';

$failures = array();
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$failures, &$assertions): void {
	++$assertions;
	if (!$condition) {
		$failures[] = $message;
	}
};

$run = static function (array $command): string {
	$descriptors = array(
		0 => array('pipe', 'r'),
		1 => array('pipe', 'w'),
		2 => array('pipe', 'w'),
	);
	$process = proc_open($command, $descriptors, $pipes);
	if (!is_resource($process)) {
		throw new RuntimeException('Could not start: ' . implode(' ', $command));
	}
	fclose($pipes[0]);
	$stdout = stream_get_contents($pipes[1]);
	$stderr = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	$exit = proc_close($process);
	if ($exit !== 0) {
		throw new RuntimeException(trim((string) $stderr) ?: 'Command failed: ' . implode(' ', $command));
	}
	return (string) $stdout;
};

$filesystemManifest = static function (string $root): array {
	$files = array();
	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
	foreach ($iterator as $fileInfo) {
		$path = $fileInfo->getPathname();
		if (!$fileInfo->isFile() || $fileInfo->isLink() || str_contains($path, '/.git/')) {
			continue;
		}
		$relative = str_replace('\\', '/', substr($path, strlen($root) + 1));
		$files[$relative] = hash_file('sha256', $path);
	}
	ksort($files, SORT_STRING);
	return $files;
};

$gitManifest = static function (string $root, string $commit) use ($run): array {
	$paths = preg_split('/\R/', trim($run(array('git', '-C', $root, 'ls-tree', '-r', '--name-only', $commit)))) ?: array();
	$files = array();
	foreach ($paths as $path) {
		if ($path === '') {
			continue;
		}
		$files[$path] = hash('sha256', $run(array('git', '-C', $root, 'show', $commit . ':' . $path)));
	}
	ksort($files, SORT_STRING);
	return $files;
};

$manifestHash = static function (array $files): string {
	$context = hash_init('sha256');
	foreach ($files as $relative => $hash) {
		hash_update($context, $relative . "\0" . $hash . "\n");
	}
	return hash_final($context);
};

$changedPaths = static function (array $first, array $second): array {
	$paths = array_unique(array_merge(array_keys($first), array_keys($second)));
	sort($paths, SORT_STRING);
	return array_values(array_filter($paths, static fn(string $path): bool => ($first[$path] ?? null) !== ($second[$path] ?? null)));
};

try {
	$bridgeActiveManifest = $filesystemManifest($bridgeRoot);
	$bridge021Manifest = $gitManifest($bridgeRoot, 'fc78595');
	$bridge022Manifest = $gitManifest($bridgeRoot, 'b1efcc974233a3b43c2a9efa30533c6688f87320');
	$assert($bridgeActiveManifest === $bridge021Manifest, 'The active Bridge worktree must be byte-equivalent to committed 0.2.1.');
	$assert($run(array('git', '-C', $bridgeRoot, 'rev-parse', 'b1efcc974233a3b43c2a9efa30533c6688f87320^{tree}')) === "3e2c1e49411c6811065f7f8eacca8fdaf736ed60\n", 'Bridge 0.2.2 Git tree changed.');
	$assert($manifestHash($bridge022Manifest) === '075878dc3628d5f6f26ce96e51ea328c0ce040ddf2b7036e3c18136029d979b3', 'Bridge 0.2.2 normalized source manifest changed.');
	$assert(($bridge022Manifest['drm-events-bridge.php'] ?? '') === '90ded5e059fe0974d465ae9627f8b12d36154b2b83a384b85238d6767028efed', 'Bridge 0.2.2 bootstrap changed.');
	$assert($changedPaths($bridge021Manifest, $bridge022Manifest) === array('AGENTS.md', 'README.md', 'drm-events-bridge.php', 'includes/rest.php', 'tests/router-provider-probe.php'), 'Bridge 0.2.1 to 0.2.2 changed-path set is not exact.');

	$routerManifest = $filesystemManifest($routerRoot);
	$routerCommitManifest = $gitManifest($routerRepo, '21fadbd00e2ccebeb42ef8cb0334160af6e8b288');
	$assert($routerManifest === $routerCommitManifest, 'Installed Router 0.1.3 must be byte-identical to commit 21fadbd.');
	$assert($run(array('git', '-C', $routerRepo, 'rev-parse', '21fadbd00e2ccebeb42ef8cb0334160af6e8b288^{tree}')) === "52b6e45421e0809526c46c34c7997ec024cc56df\n", 'Router 0.1.3 Git tree changed.');
	$assert(($routerManifest['drm-event-router.php'] ?? '') === '6758ce2f473493638778dc0fb45827672fd1f84663f41c20aea8f8e6431e6378', 'Router 0.1.3 bootstrap changed.');
	$assert(str_contains((string) file_get_contents($routerRoot . '/drm-event-router.php'), "DRM_ER_PUBLIC_CONTRACT_VERSION', 2"), 'Router 0.1.3 must expose public contract v2.');
	$assert(trim($run(array('git', '-C', $intakeRoot, 'rev-parse', 'HEAD'))) === '590f2ac40e346a5a1aae384a9e90b37d72b4cf80', 'Calendar Intake 0.2.4 authority moved.');
	$assert(trim($run(array('git', '-C', $intakeRoot, 'status', '--porcelain=v1', '--untracked-files=all'))) === '', 'Calendar Intake authority is dirty.');

	$sponsorshipsActiveManifest = $filesystemManifest($sponsorshipsActive);
	$sponsorshipsCandidateManifest = $filesystemManifest($sponsorshipsCandidate);
	$assert($manifestHash($sponsorshipsActiveManifest) === '02115019f565f226251d8f4d8d2a0944f43f56a43f877c49152aef825209c3b1', 'Active Sponsorships 0.1.27 tree changed during reconciliation.');
	$assert($manifestHash($sponsorshipsCandidateManifest) === 'a1ec835bc577d86955ea010789319a9edd638e1d79bdb731e837a6276a9e47f5', 'Sponsorships 0.1.28 candidate tree changed.');
	$assert(hash_file('sha256', $sponsorshipsArchive) === '4e77159f4802be78f80e7a70ef9d3e442b579e1229f859204fe40f33e56ea6ee', 'Frozen Sponsorships 0.1.27 RC archive changed.');
	$assert(trim($run(array('git', '-C', $sponsorshipsCandidate, 'rev-parse', 'HEAD'))) === '86e911f42e0d242ecb022453e4753ece6cbc1a84', 'Sponsorships repository base moved.');
	$expectedStatus = array(
		' M README.md',
		' M docs/CHANGELOG.md',
		' M includes/class-vms-sponsorships-admin.php',
		' M includes/class-vms-sponsorships-shortcodes.php',
		' M vms-sponsorships.php',
		'?? includes/core-compat.php',
	);
	$actualStatus = preg_split('/\R/', rtrim($run(array('git', '-C', $sponsorshipsCandidate, 'status', '--porcelain=v1', '--untracked-files=all')))) ?: array();
	$assert($actualStatus === $expectedStatus, 'Sponsorships candidate contains paths outside the reviewed successor delta.');
	foreach (array('includes/core-compat.php', 'includes/class-vms-sponsorships-admin.php', 'includes/class-vms-sponsorships-shortcodes.php') as $acceptedRuntimePath) {
		$assert(($sponsorshipsCandidateManifest[$acceptedRuntimePath] ?? '') === ($sponsorshipsActiveManifest[$acceptedRuntimePath] ?? ''), 'Accepted runtime file differs between active and candidate: ' . $acceptedRuntimePath);
	}
	$candidateBootstrap = (string) file_get_contents($sponsorshipsCandidate . '/vms-sponsorships.php');
	$activeBootstrap = (string) file_get_contents($sponsorshipsActive . '/vms-sponsorships.php');
	$assert(str_replace('0.1.28', '0.1.27', $candidateBootstrap) === $activeBootstrap, 'Sponsorships candidate bootstrap differs from accepted active source beyond the version bump.');
	$assert(str_contains($candidateBootstrap, 'Version: 0.1.28') && str_contains($candidateBootstrap, "VMS_SPONSORSHIPS_VERSION', '0.1.28"), 'Sponsorships successor version markers are incomplete.');
} catch (Throwable $exception) {
	$failures[] = $exception->getMessage();
}

if ($failures !== array()) {
	fwrite(STDERR, "Wave 2B source-authority failures:\n- " . implode("\n- ", $failures) . "\n");
	exit(1);
}

echo "Wave 2B source authority passed: {$assertions} assertions.\n";
