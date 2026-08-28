<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/wporg-prefix-b4.php';

final class BVMGR_WPORG_Prefix_B4_Addons
{
	private const TARGET = '/private/tmp/bvm-wporg-b4-addon-isolation';
	private const EVIDENCE = 'docs/wporg-prefix-b4-addon-compatibility.json';
	private const SLUGS = array('vms-events-slider', 'vms-fill-dates', 'vms-data-tools', 'vms-express-bar', 'vms-refer-a-friend');

	public static function run(string $root, string $mode): void
	{
		$installedCore = realpath($root . '/../../vms');
		if ($installedCore === false) {
			throw new RuntimeException('Unable to resolve the read-only installed core tree.');
		}
		$installedBase = dirname($installedCore);
		$map = BVMGR_WPORG_Prefix_B4::loadJson($root . '/' . BVMGR_WPORG_Prefix_B4::MAP_PATH);
		$manifest = BVMGR_WPORG_Prefix_B4::loadJson($root . '/docs/wporg-prefix-migration-manifest.json');

		if ($mode === '--prepare') {
			self::prepare($root, $installedBase, $map, $manifest);
			return;
		}
		if ($mode === '--check') {
			self::check($root, $installedBase, $map, $manifest);
			return;
		}
		throw new RuntimeException('Usage: php scripts/prepare-wporg-prefix-b4-addons.php [--prepare|--check]');
	}

	private static function prepare(string $root, string $installedBase, array $map, array $manifest): void
	{
		if (file_exists(self::TARGET)) {
			throw new RuntimeException('Refusing to overwrite the existing disposable B4 add-on root.');
		}
		foreach (array('sources', 'pre-b4', 'workspaces', 'provenance', 'tests') as $directory) {
			self::makeDirectory(self::TARGET . '/' . $directory);
		}

		$evidence = self::evidence($root, $installedBase, $map, $manifest, true);
		self::writeJson(self::TARGET . '/provenance.json', $evidence);
		self::writeJson($root . '/' . self::EVIDENCE, $evidence);
		echo "Prepared five unchanged disposable B4 add-on workspaces with zero-impact provenance.\n";
	}

	private static function check(string $root, string $installedBase, array $map, array $manifest): void
	{
		$path = $root . '/' . self::EVIDENCE;
		if (!is_file($path) || !is_file(self::TARGET . '/provenance.json')) {
			throw new RuntimeException('Prepare the disposable B4 add-on evidence before checking it.');
		}
		$expected = BVMGR_WPORG_Prefix_B4::loadJson($path);
		$current = self::evidence($root, $installedBase, $map, $manifest, false);
		if ($current !== $expected || BVMGR_WPORG_Prefix_B4::loadJson(self::TARGET . '/provenance.json') !== $expected) {
			throw new RuntimeException('Disposable B4 add-on provenance or installed-tree immutability changed.');
		}
		echo "Disposable B4 add-on provenance and zero semantic impact passed for five add-ons.\n";
	}

	private static function evidence(string $root, string $installedBase, array $map, array $manifest, bool $copy): array
	{
		$lookups = self::lookups($map);
		$addons = array();
		foreach (self::SLUGS as $slug) {
			$installed = $installedBase . '/' . $slug;
			if (!is_dir($installed)) {
				throw new RuntimeException('Missing installed add-on tree: ' . $slug);
			}
			$source = self::TARGET . '/sources/' . $slug;
			$before = self::TARGET . '/pre-b4/' . $slug;
			$workspace = self::TARGET . '/workspaces/' . $slug;
			if ($copy) {
				self::copyTree($installed, $source);
				self::copyTree($source, $before);
				self::copyTree($before, $workspace);
			}
			foreach (array($source, $before, $workspace) as $path) {
				if (!is_dir($path)) {
					throw new RuntimeException('Missing disposable B4 add-on tree: ' . $path);
				}
			}

			$installedSnapshot = self::treeSnapshot($installed);
			$sourceSnapshot = self::treeSnapshot($source);
			$beforeSnapshot = self::treeSnapshot($before);
			$workspaceSnapshot = self::treeSnapshot($workspace);
			if ($installedSnapshot !== $sourceSnapshot || $sourceSnapshot !== $beforeSnapshot || $beforeSnapshot !== $workspaceSnapshot) {
				throw new RuntimeException('B4 disposable copies must remain byte-identical for ' . $slug);
			}
			$consumers = self::semanticConsumers($workspace, $lookups);
			if ($consumers !== array()) {
				throw new RuntimeException('Unexpected B4 semantic add-on consumer in ' . $slug . ': ' . json_encode($consumers, JSON_UNESCAPED_SLASHES));
			}

			$addons[] = array(
				'slug' => $slug,
				'installed_source_read_only' => true,
				'installed_source' => $installedSnapshot,
				'fresh_source_copy' => $sourceSnapshot,
				'workspace_before_b4' => $beforeSnapshot,
				'workspace_after_b4' => $workspaceSnapshot,
				'b4_semantic_consumers' => array(),
				'patch_required' => false,
				'patch_sha256' => null,
			);
		}

		$rafSource = (string) file_get_contents(self::TARGET . '/workspaces/vms-refer-a-friend/includes/class-vms-raf-plugin.php');
		if (substr_count($rafSource, "'vms-admin'") !== 1 || !str_contains($rafSource, 'detect_vms_parent_slug')) {
			throw new RuntimeException('Refer a Friend retained admin-parent-slug evidence changed.');
		}
		$addonContracts = array_column((array) ($manifest['known_addons'] ?? array()), null, 'slug');
		if (($addonContracts['vms-refer-a-friend']['consumed_contracts']['asset_handles'] ?? null) !== array()) {
			throw new RuntimeException('Manifest reintroduced the false Refer a Friend asset-handle classification.');
		}

		return array(
			'schema_version' => 1,
			'artifact' => 'wporg-prefix-b4-addon-compatibility',
			'authority' => array(
				'authorized_starting_head' => 'bdd84df7bcbfcec65ee57fedf561bf4e167761f6',
				'b4_identifier_map' => BVMGR_WPORG_Prefix_B4::MAP_PATH,
				'b4_identifier_map_sha256' => hash_file('sha256', $root . '/' . BVMGR_WPORG_Prefix_B4::MAP_PATH),
			),
			'disposable_root' => self::TARGET,
			'summary' => array(
				'addons' => 5,
				'b4_semantic_consumers' => 0,
				'patches_required' => 0,
				'installed_trees_modified' => 0,
			),
			'retained_homonyms' => array(
				array(
					'slug' => 'vms-refer-a-friend',
					'identifier' => 'vms-admin',
					'role' => 'admin_page_parent_slug_candidate',
					'asset_api_consumer' => false,
					'compatibility_action' => 'retain unchanged',
				),
			),
			'addons' => $addons,
		);
	}

	private static function lookups(array $map): array
	{
		$lookups = array();
		foreach (array('asset_handles', 'browser_globals', 'nonce_actions', 'nonce_fields', 'query_vars', 'rewrite_tags', 'rewrite_rules', 'cli_paths') as $category) {
			$lookups[$category] = array();
			foreach ((array) ($map['categories'][$category] ?? array()) as $row) {
				$legacy = trim((string) ($row['legacy_identifier'] ?? ''), "'");
				if ($legacy !== '') {
					$lookups[$category][$legacy] = true;
				}
			}
		}
		return $lookups;
	}

	private static function semanticConsumers(string $root, array $lookups): array
	{
		$apiCategories = array(
			'wp_enqueue_script' => array('asset_handles'), 'wp_register_script' => array('asset_handles'),
			'wp_enqueue_style' => array('asset_handles'), 'wp_register_style' => array('asset_handles'),
			'wp_dequeue_script' => array('asset_handles'), 'wp_deregister_script' => array('asset_handles'),
			'wp_dequeue_style' => array('asset_handles'), 'wp_deregister_style' => array('asset_handles'),
			'wp_script_is' => array('asset_handles'), 'wp_style_is' => array('asset_handles'),
			'wp_localize_script' => array('asset_handles', 'browser_globals'),
			'wp_add_inline_script' => array('asset_handles', 'browser_globals'), 'wp_add_inline_style' => array('asset_handles'),
			'wp_create_nonce' => array('nonce_actions'), 'wp_verify_nonce' => array('nonce_actions'),
			'wp_nonce_field' => array('nonce_actions', 'nonce_fields'), 'wp_nonce_url' => array('nonce_actions'),
			'check_admin_referer' => array('nonce_actions', 'nonce_fields'), 'check_ajax_referer' => array('nonce_actions', 'nonce_fields'),
			'get_query_var' => array('query_vars'), 'add_query_arg' => array('query_vars'),
			'add_rewrite_tag' => array('rewrite_tags', 'query_vars'), 'add_rewrite_rule' => array('rewrite_rules', 'query_vars'),
			'add_command' => array('cli_paths'),
		);
		$findings = array();
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
		foreach ($iterator as $file) {
			if (!$file->isFile()) {
				continue;
			}
			$path = (string) $file->getPathname();
			$relative = ltrim(str_replace('\\', '/', substr($path, strlen($root))), '/');
			$extension = strtolower((string) $file->getExtension());
			$contents = (string) file_get_contents($path);
			if (in_array($extension, array('js', 'mjs'), true)) {
				foreach (array_keys($lookups['browser_globals']) as $legacy) {
					if (preg_match('/(?<![A-Za-z0-9_$])' . preg_quote($legacy, '/') . '(?![A-Za-z0-9_$])/', $contents) === 1) {
						$findings[] = array('category' => 'browser_globals', 'identifier' => $legacy, 'file' => $relative);
					}
				}
				continue;
			}
			if ($extension !== 'php') {
				continue;
			}
			$tokens = token_get_all($contents);
			$count = count($tokens);
			for ($index = 0; $index < $count; $index++) {
				if (!is_array($tokens[$index]) || $tokens[$index][0] !== T_STRING) {
					continue;
				}
				$name = strtolower((string) $tokens[$index][1]);
				if (!isset($apiCategories[$name])) {
					continue;
				}
				$next = $index + 1;
				while ($next < $count && is_array($tokens[$next]) && in_array($tokens[$next][0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
					$next++;
				}
				if (($tokens[$next] ?? null) !== '(') {
					continue;
				}
				$depth = 0;
				$literals = array();
				for ($cursor = $next; $cursor < $count; $cursor++) {
					$token = $tokens[$cursor];
					if ($token === '(') {
						$depth++;
					} elseif ($token === ')') {
						$depth--;
						if ($depth === 0) {
							break;
						}
					} elseif (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING) {
						$literals[] = self::decodeLiteral((string) $token[1]);
					}
				}
				foreach ($apiCategories[$name] as $category) {
					foreach (array_keys($lookups[$category]) as $legacy) {
						foreach ($literals as $literal) {
							$dynamicStem = strstr($legacy, '{*}', true);
							$matched = $dynamicStem === false ? str_contains($literal, $legacy) : str_starts_with($literal, $dynamicStem);
							if ($matched) {
								$findings[] = array('category' => $category, 'identifier' => $legacy, 'file' => $relative, 'api' => $name);
								break;
							}
						}
					}
				}
			}
		}
		usort($findings, static fn(array $a, array $b): int => strcmp(json_encode($a), json_encode($b)));
		return $findings;
	}

	private static function decodeLiteral(string $literal): string
	{
		return $literal !== '' && $literal[0] === "'"
			? str_replace(array('\\\\', "\\'"), array('\\', "'"), substr($literal, 1, -1))
			: stripcslashes(substr($literal, 1, -1));
	}

	private static function treeSnapshot(string $root): array
	{
		$real = realpath($root);
		if ($real === false) {
			throw new RuntimeException('Unable to resolve add-on tree: ' . $root);
		}
		$files = array();
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($real, FilesystemIterator::SKIP_DOTS));
		foreach ($iterator as $file) {
			if ($file->isLink()) {
				throw new RuntimeException('Symlink is not allowed in add-on provenance: ' . $file->getPathname());
			}
			if ($file->isFile()) {
				$files[] = ltrim(str_replace('\\', '/', substr((string) $file->getPathname(), strlen($real))), '/');
			}
		}
		sort($files, SORT_STRING);
		$rows = '';
		foreach ($files as $file) {
			$rows .= hash_file('sha256', $real . '/' . $file) . '  ./' . $file . "\n";
		}
		return array('file_count' => count($files), 'sha256' => hash('sha256', $rows));
	}

	private static function copyTree(string $source, string $target): void
	{
		if (!str_starts_with($target, self::TARGET . '/')) {
			throw new RuntimeException('Refusing to copy outside the disposable B4 root.');
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

	private static function makeDirectory(string $path): void
	{
		if (!is_dir($path) && !mkdir($path, 0777, true) && !is_dir($path)) {
			throw new RuntimeException('Unable to create disposable directory: ' . $path);
		}
	}

	private static function writeJson(string $path, array $value): void
	{
		if (file_put_contents($path, BVMGR_WPORG_Prefix_B4::render($value)) === false) {
			throw new RuntimeException('Unable to write B4 add-on provenance: ' . $path);
		}
	}
}

try {
	BVMGR_WPORG_Prefix_B4_Addons::run(dirname(__DIR__), $argv[1] ?? '--check');
} catch (Throwable $exception) {
	fwrite(STDERR, $exception->getMessage() . PHP_EOL);
	exit(1);
}
