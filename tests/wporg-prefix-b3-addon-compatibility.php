<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/scripts/lib/wporg-prefix-b3.php';
require_once dirname(__DIR__) . '/scripts/lib/wporg-prefix-inventory.php';

$failures = array();
$assert = static function (bool $condition, string $message) use (&$failures): void {
	if (!$condition) {
		$failures[] = $message;
	}
};
$run = static function (array $command, ?string $cwd = null): array {
	$process = proc_open($command, array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes, $cwd);
	if (!is_resource($process)) {
		return array('exit_code' => 255, 'stdout' => '', 'stderr' => 'Unable to start process.');
	}
	fclose($pipes[0]);
	$stdout = stream_get_contents($pipes[1]);
	$stderr = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	return array('exit_code' => proc_close($process), 'stdout' => (string) $stdout, 'stderr' => (string) $stderr);
};

$repositoryRoot = (string) realpath(dirname(__DIR__));
$configuredCoreRoot = getenv('BVMGR_B3_CORE_ROOT');
$coreRoot = (string) realpath(is_string($configuredCoreRoot) && $configuredCoreRoot !== '' ? $configuredCoreRoot : $repositoryRoot);
$configuredAddonRoot = getenv('BVMGR_B3_ADDON_ROOT');
$addonRoot = (string) realpath(is_string($configuredAddonRoot) && $configuredAddonRoot !== '' ? $configuredAddonRoot : '/private/tmp/bvm-wporg-b3-addon-isolation');
$installedCore = (string) realpath($repositoryRoot . '/../../vms');
$installedPluginRoot = $installedCore === '' ? '' : dirname($installedCore);

$assert($coreRoot === $repositoryRoot, 'B3 core resolution must target the current isolated repository root exactly.');
$assert($installedCore !== '' && $coreRoot !== $installedCore, 'B3 compatibility must never resolve the installed/live core tree.');
$assert($addonRoot !== '' && str_starts_with($addonRoot, '/private/tmp/'), 'B3 add-on compatibility must use an explicit disposable /private/tmp root.');

$provenanceCheck = $run(array(PHP_BINARY, $repositoryRoot . '/scripts/prepare-wporg-prefix-b3-addons.php', '--check'), $repositoryRoot);
$assert(
	$provenanceCheck['exit_code'] === 0,
	'Disposable add-on provenance check must pass: ' . trim($provenanceCheck['stdout'] . "\n" . $provenanceCheck['stderr'])
);

$map = BVMGR_WPORG_Prefix_B3::loadJson($repositoryRoot . '/' . BVMGR_WPORG_Prefix_B3::MAP_PATH);
$reportsArtifact = BVMGR_WPORG_Prefix_B3::loadJson($addonRoot . '/transform-reports.json');
$reports = array_column((array) ($reportsArtifact['addons'] ?? array()), null, 'slug');
$expectedSlugs = array('vms-events-slider', 'vms-fill-dates', 'vms-data-tools', 'vms-express-bar', 'vms-refer-a-friend');
$assert(array_keys($reports) === $expectedSlugs, 'B3 add-on reports must cover the five known add-ons in frozen order.');

$coreScan = BVMGR_WPORG_Prefix_Inventory::scan($coreRoot);
$coreFunctions = array_fill_keys(array_column((array) ($coreScan['symbols']['functions'] ?? array()), 'current_identifier'), true);
$candidateCount = 0;
$consumerEntryCount = 0;
$resolvedCanonical = array();

foreach ($expectedSlugs as $slug) {
	$report = (array) ($reports[$slug] ?? array());
	$workspace = (string) realpath($addonRoot . '/workspaces/' . $slug);
	$installedAddon = $installedPluginRoot === '' ? '' : (string) realpath($installedPluginRoot . '/' . $slug);
	$assert($workspace !== '' && str_starts_with($workspace, $addonRoot . '/workspaces/'), "{$slug} must resolve inside the disposable workspace root.");
	$assert($installedAddon !== '' && $workspace !== $installedAddon, "{$slug} disposable workspace must not resolve to its installed/live tree.");

	$names = array_values((array) ($report['consumer_functions'] ?? array()));
	$candidateCount += count($names);
	$inventory = BVMGR_WPORG_Prefix_B3::addonConsumerInventory($workspace, $map, $names);
	$assert($inventory === ($report['after_inventory'] ?? null), "{$slug} exact token inventory must match its frozen B3 transformation report.");
	$assert($inventory['legacy_references'] === array(), "{$slug} must contain no legacy callable references for its mapped core functions.");
	$assert($inventory['selected_declarations'] === array(), "{$slug} must not declare a selected core function.");
	$consumerEntryCount += count($inventory['canonical_references']);
	foreach (array_keys($inventory['canonical_references']) as $canonical) {
		$assert(isset($coreFunctions[$canonical]), "{$slug} canonical caller must resolve to a current core declaration: {$canonical}.");
		$resolvedCanonical[$canonical] = true;
	}

	$patchPath = (string) ($report['patch_path'] ?? '');
	$assert(is_file($patchPath), "{$slug} deterministic B3 patch artifact must exist.");
	$assert(hash_file('sha256', $patchPath) === ($report['patch_sha256'] ?? null), "{$slug} deterministic B3 patch hash must match its report.");
	$assert(!empty($report['retained_contract_literals_unchanged']), "{$slug} retained later-batch contract proof must be present.");

	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($workspace, FilesystemIterator::SKIP_DOTS));
	foreach ($iterator as $file) {
		if (!$file->isFile() || strtolower((string) $file->getExtension()) !== 'php') {
			continue;
		}
		$lint = $run(array(PHP_BINARY, '-l', (string) $file->getPathname()), $repositoryRoot);
		$assert($lint['exit_code'] === 0, "{$slug} PHP lint must pass: " . $file->getPathname());
	}
}

$assert($candidateCount === 65, 'The frozen add-on manifest must retain exactly 65 candidate consumption entries.');
$assert($consumerEntryCount === 63, 'Exact token resolution must prove 63 callable consumer entries plus two retained Fill Dates hook homonyms.');
$assert(count($resolvedCanonical) === 53, 'The five add-ons must resolve exactly 53 unique canonical core functions.');

if ($failures !== array()) {
	fwrite(STDERR, "B3 disposable add-on compatibility failures:\n- " . implode("\n- ", $failures) . "\n");
	exit(1);
}

echo "B3 disposable add-on compatibility passed for five add-ons, 63 callable entries, and 53 unique core functions.\n";
