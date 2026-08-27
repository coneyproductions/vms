<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/wporg-prefix-b3.php';

final class BVMGR_WPORG_Prefix_B3_Addons
{
	private const TARGET = '/private/tmp/bvm-wporg-b3-addon-isolation';
	private const B2_ROOT = '/private/tmp/bvm-wporg-b2-addon-isolation';
	private const SLUGS = array('vms-events-slider', 'vms-fill-dates', 'vms-data-tools', 'vms-express-bar', 'vms-refer-a-friend');
	private const B2_REUSED = array('vms-events-slider', 'vms-data-tools', 'vms-express-bar');

	public static function run(string $repositoryRoot, string $mode): void
	{
		$map = BVMGR_WPORG_Prefix_B3::loadJson($repositoryRoot . '/' . BVMGR_WPORG_Prefix_B3::MAP_PATH);
		BVMGR_WPORG_Prefix_B3::validateMap($map);
		$manifest = BVMGR_WPORG_Prefix_B3::loadJson($repositoryRoot . '/docs/wporg-prefix-migration-manifest.json');
		$installedCore = realpath($repositoryRoot . '/../../vms');
		if ($installedCore === false) {
			throw new RuntimeException('Unable to resolve the read-only installed core tree.');
		}
		$installedBase = dirname($installedCore);
		if ($mode === '--prepare') {
			self::prepare($repositoryRoot, $installedBase);
			return;
		}
		if ($mode === '--transform') {
			self::transform($map, $manifest, $installedBase);
			return;
		}
		if ($mode === '--check') {
			self::check($map, $installedBase);
			return;
		}
		throw new RuntimeException('Usage: php scripts/prepare-wporg-prefix-b3-addons.php [--prepare|--transform|--check]');
	}

	private static function prepare(string $repositoryRoot, string $installedBase): void
	{
		if (file_exists(self::TARGET)) {
			throw new RuntimeException('Refusing to overwrite the existing disposable B3 add-on root.');
		}
		foreach (array('sources', 'pre-b3', 'workspaces', 'provenance', 'tests') as $directory) {
			self::makeDirectory(self::TARGET . '/' . $directory);
		}
		$baseline = BVMGR_WPORG_Prefix_B3::loadJson($repositoryRoot . '/docs/wporg-prefix-b3-untouched-tree-baseline.json');
		$expected = array();
		foreach ((array) ($baseline['trees'] ?? array()) as $tree) {
			$expected[(string) ($tree['logical_name'] ?? '')] = $tree;
		}
		$summary = array('artifact' => 'wporg-prefix-b3-addon-provenance', 'target_root' => self::TARGET, 'addons' => array());
		foreach (self::SLUGS as $slug) {
			$installed = $installedBase . '/' . $slug;
			if (!is_dir($installed)) {
				throw new RuntimeException('Missing installed add-on tree: ' . $slug);
			}
			$installedSnapshot = self::treeSnapshot($installed);
			$baselineKey = 'installed-' . $slug;
			if (($expected[$baselineKey]['sha256'] ?? '') !== $installedSnapshot['sha256'] || ($expected[$baselineKey]['file_count'] ?? null) !== $installedSnapshot['file_count']) {
				throw new RuntimeException('Installed add-on drifted from the frozen untouched-tree baseline: ' . $slug);
			}
			$sourceCopy = self::TARGET . '/sources/' . $slug;
			self::copyTree($installed, $sourceCopy);
			if (self::treeSnapshot($sourceCopy) !== $installedSnapshot) {
				throw new RuntimeException('Fresh source snapshot mismatch: ' . $slug);
			}
			$reused = in_array($slug, self::B2_REUSED, true);
			$workspaceSource = $sourceCopy;
			$b2PatchSha = null;
			if ($reused) {
				$b2Provenance = self::B2_ROOT . '/provenance/' . $slug;
				$b2HashLine = trim((string) file_get_contents($b2Provenance . '/source-tree.sha256'));
				$b2Hash = strtok($b2HashLine, " \t");
				if ($b2Hash !== $installedSnapshot['sha256']) {
					throw new RuntimeException('B2 source provenance is stale for ' . $slug);
				}
				$workspaceSource = self::B2_ROOT . '/workspaces/' . $slug;
				self::validateB2Workspace($slug, $workspaceSource, $b2Provenance . '/copy-files-after.sha256');
				$b2PatchSha = hash_file('sha256', $b2Provenance . '/compatibility.final.patch') ?: null;
			}
			$preB3 = self::TARGET . '/pre-b3/' . $slug;
			self::copyTree($workspaceSource, $preB3);
			$workspace = self::TARGET . '/workspaces/' . $slug;
			self::copyTree($preB3, $workspace);
			$entry = array(
				'slug' => $slug,
				'installed_source_read_only' => true,
				'installed_source' => $installedSnapshot,
				'fresh_source_copy' => self::treeSnapshot($sourceCopy),
				'b2_disposable_workspace_reused' => $reused,
				'b2_compatibility_patch_sha256' => $b2PatchSha,
				'workspace_before_b3' => self::treeSnapshot($preB3),
			);
			self::writeJson(self::TARGET . '/provenance/' . $slug . '.json', $entry);
			$summary['addons'][] = $entry;
		}
		self::writeJson(self::TARGET . '/provenance.json', $summary);
		echo "Prepared five fresh disposable B3 add-on workspaces with provenance.\n";
	}

	private static function transform(array $map, array $manifest, string $installedBase): void
	{
		if (!is_file(self::TARGET . '/provenance.json')) {
			throw new RuntimeException('Prepare disposable add-ons before transforming them.');
		}
		$addonContracts = array_column((array) ($manifest['known_addons'] ?? array()), null, 'slug');
		$aggregate = array('artifact' => 'wporg-prefix-b3-addon-transform-reports', 'addons' => array());
		foreach (self::SLUGS as $slug) {
			$workspace = self::TARGET . '/workspaces/' . $slug;
			$names = self::consumerNames($map, $slug);
			$retained = array_values(array_unique(array_merge(
				(array) ($addonContracts[$slug]['consumed_contracts']['hooks'] ?? array()),
				(array) ($addonContracts[$slug]['consumed_contracts']['physical_cpt_taxonomy_identifiers'] ?? array()),
				(array) ($addonContracts[$slug]['consumed_contracts']['asset_handles'] ?? array())
			)));
			$beforeRetained = self::literalCounts($workspace, $retained);
			$before = BVMGR_WPORG_Prefix_B3::addonConsumerInventory($workspace, $map, $names);
			$actualConsumers = array_keys($before['legacy_references']);
			$retainedHomonyms = array_values(array_intersect(array_diff($names, $actualConsumers), $retained));
			sort($retainedHomonyms, SORT_STRING);
			$unresolved = array_values(array_diff($names, $actualConsumers, $retainedHomonyms));
			if ($unresolved !== array()) {
				throw new RuntimeException('Unresolved add-on manifest consumers are not retained-contract homonyms: ' . $slug . ': ' . implode(', ', $unresolved));
			}
			$report = BVMGR_WPORG_Prefix_B3::transformAddonConsumers($workspace, $map, $names);
			$after = BVMGR_WPORG_Prefix_B3::addonConsumerInventory($workspace, $map, $names);
			$afterRetained = self::literalCounts($workspace, $retained);
			if ($beforeRetained !== $afterRetained) {
				throw new RuntimeException('Retained add-on contract literals changed during B3: ' . $slug);
			}
			if ($after['legacy_references'] !== array() || $after['selected_declarations'] !== array()) {
				throw new RuntimeException('Legacy reference or selected declaration remains in disposable add-on: ' . $slug);
			}
			$expectedCanonical = array();
			foreach ($actualConsumers as $legacy) {
				$expectedCanonical[] = 'bvmgr_' . substr($legacy, 4);
			}
			sort($expectedCanonical, SORT_STRING);
			$actualCanonical = array_keys($after['canonical_references']);
			sort($actualCanonical, SORT_STRING);
			if ($actualCanonical !== $expectedCanonical) {
				throw new RuntimeException('Canonical add-on consumer coverage mismatch: ' . $slug);
			}
			$installedSnapshot = self::treeSnapshot($installedBase . '/' . $slug);
			$provenance = BVMGR_WPORG_Prefix_B3::loadJson(self::TARGET . '/provenance/' . $slug . '.json');
			if (($provenance['installed_source'] ?? array()) !== $installedSnapshot) {
				throw new RuntimeException('Installed add-on changed during disposable transformation: ' . $slug);
			}
			$patchPath = self::TARGET . '/provenance/' . $slug . '-b3.patch';
			$patch = self::deterministicPatch(self::TARGET . '/pre-b3/' . $slug, $workspace, $slug);
			if ($patch === '') {
				throw new RuntimeException('B3 add-on transformation produced an empty patch: ' . $slug);
			}
			self::writeText($patchPath, $patch);
			$report += array(
				'slug' => $slug,
				'consumer_functions' => $names,
				'before_inventory' => $before,
				'after_inventory' => $after,
				'retained_contract_homonyms' => $retainedHomonyms,
				'retained_contract_literals_unchanged' => true,
				'patch_path' => $patchPath,
				'patch_sha256' => hash('sha256', $patch),
				'workspace_after_b3' => self::treeSnapshot($workspace),
			);
			self::writeJson(self::TARGET . '/provenance/' . $slug . '-b3-transform.json', $report);
			$aggregate['addons'][] = $report;
		}
		self::writeJson(self::TARGET . '/transform-reports.json', $aggregate);
		echo "Transformed all five disposable add-on consumers for B3.\n";
	}

	private static function check(array $map, string $installedBase): void
	{
		$aggregate = BVMGR_WPORG_Prefix_B3::loadJson(self::TARGET . '/transform-reports.json');
		$reports = array_column((array) ($aggregate['addons'] ?? array()), null, 'slug');
		foreach (self::SLUGS as $slug) {
			$workspace = (string) realpath(self::TARGET . '/workspaces/' . $slug);
			$source = (string) realpath(self::TARGET . '/sources/' . $slug);
			$preB3 = (string) realpath(self::TARGET . '/pre-b3/' . $slug);
			if (!str_starts_with($workspace, self::TARGET . '/') || !str_starts_with($source, self::TARGET . '/') || !str_starts_with($preB3, self::TARGET . '/')) {
				throw new RuntimeException('Disposable root resolution escaped for ' . $slug);
			}
			$provenance = BVMGR_WPORG_Prefix_B3::loadJson(self::TARGET . '/provenance/' . $slug . '.json');
			if (($provenance['installed_source'] ?? array()) !== self::treeSnapshot($installedBase . '/' . $slug)) {
				throw new RuntimeException('Installed add-on drift detected: ' . $slug);
			}
			if (($provenance['fresh_source_copy'] ?? array()) !== self::treeSnapshot($source)) {
				throw new RuntimeException('Disposable source provenance drift detected: ' . $slug);
			}
			if (($provenance['workspace_before_b3'] ?? array()) !== self::treeSnapshot($preB3)) {
				throw new RuntimeException('Disposable pre-B3 workspace provenance drift detected: ' . $slug);
			}
			$names = self::consumerNames($map, $slug);
			$inventory = BVMGR_WPORG_Prefix_B3::addonConsumerInventory($workspace, $map, $names);
			if ($inventory['legacy_references'] !== array() || $inventory['selected_declarations'] !== array() || $inventory['canonical_references'] !== ($reports[$slug]['after_inventory']['canonical_references'] ?? null)) {
				throw new RuntimeException('Disposable B3 consumer resolution failed: ' . $slug);
			}
			if (empty($reports[$slug]['retained_contract_literals_unchanged'])) {
				throw new RuntimeException('Missing retained-contract proof: ' . $slug);
			}
			if (($reports[$slug]['workspace_after_b3'] ?? array()) !== self::treeSnapshot($workspace)) {
				throw new RuntimeException('Disposable B3 workspace drift detected: ' . $slug);
			}
			$patch = self::deterministicPatch($preB3, $workspace, $slug);
			$patchPath = self::TARGET . '/provenance/' . $slug . '-b3.patch';
			if (!is_file($patchPath) || hash('sha256', $patch) !== ($reports[$slug]['patch_sha256'] ?? null) || (string) file_get_contents($patchPath) !== $patch) {
				throw new RuntimeException('Deterministic B3 patch provenance mismatch: ' . $slug);
			}
		}
		echo "Disposable B3 add-on provenance and exact consumer resolution passed for five add-ons.\n";
	}

	private static function consumerNames(array $map, string $slug): array
	{
		$names = array();
		foreach ((array) ($map['mappings'] ?? array()) as $mapping) {
			if (in_array($slug, (array) ($mapping['known_addon_consumers'] ?? array()), true)) {
				$names[] = (string) $mapping['legacy_identifier'];
			}
		}
		sort($names, SORT_STRING);
		return $names;
	}

	private static function literalCounts(string $root, array $values): array
	{
		$counts = array_fill_keys($values, 0);
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
		foreach ($iterator as $file) {
			if (!$file->isFile() || strtolower((string) $file->getExtension()) !== 'php') {
				continue;
			}
			foreach (token_get_all((string) file_get_contents((string) $file->getPathname())) as $token) {
				if (!is_array($token) || $token[0] !== T_CONSTANT_ENCAPSED_STRING) {
					continue;
				}
				$value = $token[1][0] === "'" ? str_replace(array('\\\\', "\\'"), array('\\', "'"), substr($token[1], 1, -1)) : stripcslashes(substr($token[1], 1, -1));
				if (array_key_exists($value, $counts)) {
					$counts[$value]++;
				}
			}
		}
		ksort($counts, SORT_STRING);
		return $counts;
	}

	private static function validateB2Workspace(string $slug, string $workspace, string $evidencePath): void
	{
		$seen = 0;
		foreach (file($evidencePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: array() as $line) {
			if (!preg_match('/^(?<hash>[a-f0-9]{64})  (?<path>.+)$/', $line, $match)) {
				throw new RuntimeException('Invalid B2 file-hash evidence for ' . $slug);
			}
			$marker = '/workspaces/' . $slug . '/';
			$position = strpos($match['path'], $marker);
			if ($position === false) {
				throw new RuntimeException('B2 evidence path escaped its workspace for ' . $slug);
			}
			$relative = substr($match['path'], $position + strlen($marker));
			if (!is_file($workspace . '/' . $relative) || hash_file('sha256', $workspace . '/' . $relative) !== $match['hash']) {
				throw new RuntimeException('B2 workspace freshness check failed for ' . $slug . ':' . $relative);
			}
			$seen++;
		}
		if ($seen !== self::treeSnapshot($workspace)['file_count']) {
			throw new RuntimeException('B2 workspace file-count evidence mismatch for ' . $slug);
		}
	}

	private static function treeSnapshot(string $root): array
	{
		$root = (string) realpath($root);
		$files = array();
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
		foreach ($iterator as $file) {
			if ($file->isLink()) {
				throw new RuntimeException('Symlink is not allowed in disposable add-on provenance: ' . $file->getPathname());
			}
			if ($file->isFile()) {
				$files[] = ltrim(str_replace('\\', '/', substr((string) $file->getPathname(), strlen($root))), '/');
			}
		}
		sort($files, SORT_STRING);
		$rows = '';
		foreach ($files as $file) {
			$rows .= hash_file('sha256', $root . '/' . $file) . '  ./' . $file . "\n";
		}
		return array('file_count' => count($files), 'sha256' => hash('sha256', $rows));
	}

	private static function copyTree(string $source, string $target): void
	{
		if (!str_starts_with($target, self::TARGET . '/')) {
			throw new RuntimeException('Refusing to copy outside the disposable B3 root.');
		}
		self::makeDirectory($target);
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
		foreach ($iterator as $file) {
			$relative = ltrim(str_replace('\\', '/', substr((string) $file->getPathname(), strlen($source))), '/');
			$destination = $target . '/' . $relative;
			if ($file->isLink()) {
				throw new RuntimeException('Refusing to copy add-on symlink: ' . $file->getPathname());
			}
			if ($file->isDir()) {
				self::makeDirectory($destination);
			} elseif (!copy((string) $file->getPathname(), $destination)) {
				throw new RuntimeException('Unable to copy disposable add-on file: ' . $relative);
			} else {
				chmod($destination, $file->getPerms() & 0777);
			}
		}
	}

	private static function deterministicPatch(string $before, string $after, string $slug): string
	{
		$command = array('git', 'diff', '--no-index', '--no-ext-diff', '--src-prefix=a/', '--dst-prefix=b/', '--', $before, $after);
		$process = proc_open($command, array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
		if (!is_resource($process)) {
			throw new RuntimeException('Unable to start deterministic add-on patch generation: ' . $slug);
		}
		fclose($pipes[0]);
		$stdout = stream_get_contents($pipes[1]);
		$stderr = stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		$exit = proc_close($process);
		if (!in_array($exit, array(0, 1), true)) {
			throw new RuntimeException('Deterministic add-on patch generation failed for ' . $slug . ': ' . trim((string) $stderr));
		}
		$patch = (string) $stdout;
		$beforeLabel = ltrim(str_replace('\\', '/', $before), '/');
		$afterLabel = ltrim(str_replace('\\', '/', $after), '/');
		$patch = str_replace(array('a/' . $beforeLabel, 'b/' . $afterLabel), array('a/' . $slug, 'b/' . $slug), $patch);
		return $patch;
	}

	private static function makeDirectory(string $path): void
	{
		if (!is_dir($path) && !mkdir($path, 0777, true) && !is_dir($path)) {
			throw new RuntimeException('Unable to create disposable directory: ' . $path);
		}
	}

	private static function writeJson(string $path, array $value): void
	{
		$json = BVMGR_WPORG_Prefix_B3::render($value);
		if (file_put_contents($path, $json) === false) {
			throw new RuntimeException('Unable to write disposable provenance: ' . $path);
		}
	}

	private static function writeText(string $path, string $value): void
	{
		if (file_put_contents($path, $value) === false) {
			throw new RuntimeException('Unable to write disposable provenance: ' . $path);
		}
	}
}

try {
	BVMGR_WPORG_Prefix_B3_Addons::run(dirname(__DIR__), $argv[1] ?? '--check');
} catch (Throwable $exception) {
	fwrite(STDERR, $exception->getMessage() . PHP_EOL);
	exit(1);
}
