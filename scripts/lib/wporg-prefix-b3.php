<?php
declare(strict_types=1);

require_once __DIR__ . '/wporg-prefix-inventory.php';

/**
 * Deterministic Phase B3 function-map, dependency, progress, and transform tooling.
 *
 * This library is release-excluded. It operates only on the frozen B3 symbol map;
 * retained hooks, keys, handles, routes, and other vms_* contract strings are not
 * transformation candidates.
 */
final class BVMGR_WPORG_Prefix_B3
{
	public const MAP_PATH = 'docs/wporg-prefix-b3-function-map.json';
	public const GRAPH_PATH = 'docs/wporg-prefix-b3-dependency-graph.json';
	public const PROGRESS_PATH = 'docs/wporg-prefix-b3-progress.json';
	public const LITERAL_DECISIONS_PATH = 'docs/wporg-prefix-b3-literal-decisions.json';

	private const CALLABLE_ARGUMENTS = array(
		'add_action' => 1,
		'add_filter' => 1,
		'add_shortcode' => 1,
		'register_activation_hook' => 1,
		'register_deactivation_hook' => 1,
		'add_meta_box' => 2,
		'add_menu_page' => 4,
		'add_submenu_page' => 5,
		'add_dashboard_page' => 4,
		'add_management_page' => 4,
		'add_options_page' => 4,
		'add_plugins_page' => 4,
		'add_theme_page' => 4,
		'add_users_page' => 4,
		'add_settings_field' => 2,
		'add_settings_section' => 2,
		'register_setting' => 2,
		'array_map' => 0,
		'array_filter' => 1,
		'usort' => 1,
		'uasort' => 1,
		'uksort' => 1,
		'call_user_func' => 0,
		'call_user_func_array' => 0,
		'is_callable' => 0,
		'function_exists' => 0,
		'preg_replace_callback' => 1,
		'set_error_handler' => 0,
		'set_exception_handler' => 0,
		'spl_autoload_register' => 0,
		'register_shutdown_function' => 0,
		'register_tick_function' => 0,
		'add_feed' => 1,
		'wp_ajax' => 1,
	);

	private const REGISTRY_CALLBACK_KEYS = array(
		'callback',
		'permission_callback',
		'sanitize_callback',
		'auth_callback',
		'render_callback',
		'notices_callback',
		'content_callback',
	);

	public static function loadJson(string $path): array
	{
		$decoded = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
		if (!is_array($decoded)) {
			throw new RuntimeException('Invalid or missing JSON artifact: ' . $path);
		}
		return $decoded;
	}

	public static function render(array $value): string
	{
		$json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		if (!is_string($json)) {
			throw new RuntimeException('Unable to encode B3 artifact.');
		}
		return $json . PHP_EOL;
	}

	public static function freezeMap(string $root, array $manifest, array $scannerInventory): array
	{
		$root = self::root($root);
		$functions = (array) ($manifest['symbols']['functions'] ?? array());
		$publicApis = array();
		foreach ((array) ($manifest['public_extension_apis'] ?? array()) as $api) {
			if (($api['type'] ?? '') !== 'function') {
				continue;
			}
			$legacy = (string) ($api['legacy_identifier'] ?? $api['current_identifier'] ?? '');
			$publicApis[$legacy] = (string) ($api['family'] ?? '');
		}
		$addonConsumers = array();
		foreach ((array) ($manifest['known_addons'] ?? array()) as $addon) {
			$slug = (string) ($addon['slug'] ?? '');
			foreach ((array) ($addon['consumed_contracts']['core_php_functions'] ?? array()) as $function) {
				$addonConsumers[(string) $function][] = $slug;
			}
		}
		$scannerRows = array();
		foreach ((array) ($scannerInventory['authoritative_prefix_findings'] ?? array()) as $finding) {
			if (($finding['category'] ?? '') !== 'REQUIRED_MIGRATION_B3') {
				continue;
			}
			$scannerRows[(string) ($finding['identifier'] ?? '')][] = array(
				'finding_id' => (string) ($finding['finding_id'] ?? ''),
				'file' => (string) ($finding['file'] ?? ''),
				'line' => (int) ($finding['line'] ?? 0),
			);
		}

		$dynamicKinds = array(
			'function_exists' => (array) ($manifest['dynamic_symbols']['function_exists_checks'] ?? array()),
			'callback' => (array) ($manifest['dynamic_symbols']['direct_literal_callbacks'] ?? array()),
			'reflection' => (array) ($manifest['dynamic_symbols']['reflection_references'] ?? array()),
			'exact_literal' => (array) ($manifest['dynamic_symbols']['exact_function_literals'] ?? array()),
		);

		$mappings = array();
		$targets = array();
		$declarationSiteCount = 0;
		$duplicateCount = 0;
		$scannerCount = 0;
		foreach ($functions as $function) {
			$legacy = (string) ($function['current_identifier'] ?? '');
			$canonical = (string) ($function['canonical_target'] ?? '');
			$sites = array_values((array) ($function['declaration_sites'] ?? array()));
			if (!str_starts_with($legacy, 'vms_') || $canonical !== 'bvmgr_' . substr($legacy, 4)) {
				throw new RuntimeException('B3 map contains a non-canonical mapping: ' . $legacy . ' -> ' . $canonical);
			}
			if (isset($targets[$canonical])) {
				throw new RuntimeException('Duplicate B3 canonical target: ' . $canonical);
			}
			$targets[$canonical] = true;
			if (count($sites) > 2) {
				throw new RuntimeException('Unexpected B3 declaration multiplicity: ' . $legacy);
			}
			if (count($sites) === 2) {
				$duplicateCount++;
			}
			$declarationSiteCount += count($sites);
			$scanner = array_values((array) ($scannerRows[$legacy] ?? array()));
			if (count($scanner) !== count($sites)) {
				throw new RuntimeException('Scanner/declaration mismatch for ' . $legacy);
			}
			$scannerCount += count($scanner);
			$dynamic = array();
			foreach ($dynamicKinds as $kind => $map) {
				$dynamic[$kind] = array_values((array) ($map[$legacy] ?? array()));
			}
			$addons = array_values(array_unique((array) ($addonConsumers[$legacy] ?? array())));
			sort($addons, SORT_STRING);
			$mappings[] = array(
				'legacy_identifier' => $legacy,
				'canonical_identifier' => $canonical,
				'declaration_sites' => $sites,
				'duplicate_family' => count($sites) === 2,
				'public_api_family' => $publicApis[$legacy] ?? null,
				'known_addon_consumers' => $addons,
				'scanner_findings' => $scanner,
				'baseline_dynamic_sites' => $dynamic,
			);
		}
		usort($mappings, static fn(array $a, array $b): int => $a['legacy_identifier'] <=> $b['legacy_identifier']);

		$currentNames = array_fill_keys(array_column($functions, 'current_identifier'), true);
		$collisions = array_values(array_intersect(array_keys($targets), array_keys($currentNames)));
		if ($collisions !== array()) {
			throw new RuntimeException('Canonical collisions already exist: ' . implode(', ', $collisions));
		}
		if (count($mappings) !== 4521 || $declarationSiteCount !== 4541 || $duplicateCount !== 20 || $scannerCount !== 4541) {
			throw new RuntimeException(sprintf(
				'Unexpected B3 map totals: %d functions, %d sites, %d duplicates, %d scanner rows.',
				count($mappings),
				$declarationSiteCount,
				$duplicateCount,
				$scannerCount
			));
		}

		return array(
			'schema_version' => 1,
			'artifact' => 'wporg-prefix-b3-function-map',
			'authority' => 'docs/WPORG_PREFIX_MIGRATION_B0.md through docs/WPORG_PREFIX_MIGRATION_B2_5.md',
			'source_commit' => trim((string) shell_exec('git -C ' . escapeshellarg($root) . ' rev-parse HEAD 2>/dev/null')),
			'baseline_manifest_sha256' => hash_file('sha256', $root . '/docs/wporg-prefix-migration-manifest.json') ?: '',
			'baseline_scanner_inventory_sha256' => hash_file('sha256', $root . '/docs/wporg-prefix-scanner-inventory.json') ?: '',
			'canonical_rule' => 'vms_<suffix> -> bvmgr_<suffix>',
			'counts' => array(
				'unique_functions' => count($mappings),
				'declaration_sites' => $declarationSiteCount,
				'duplicate_two_site_families' => $duplicateCount,
				'canonical_targets' => count($targets),
				'scanner_rows' => $scannerCount,
			),
			'mappings' => $mappings,
		);
	}

	public static function validateMap(array $map): void
	{
		if (($map['schema_version'] ?? null) !== 1 || ($map['artifact'] ?? '') !== 'wporg-prefix-b3-function-map') {
			throw new RuntimeException('Unsupported B3 function-map artifact.');
		}
		$mappings = (array) ($map['mappings'] ?? array());
		$legacy = array_column($mappings, 'legacy_identifier');
		$canonical = array_column($mappings, 'canonical_identifier');
		$sites = array_sum(array_map(static fn(array $entry): int => count((array) ($entry['declaration_sites'] ?? array())), $mappings));
		$duplicates = count(array_filter($mappings, static fn(array $entry): bool => !empty($entry['duplicate_family'])));
		$scannerRows = array_sum(array_map(static fn(array $entry): int => count((array) ($entry['scanner_findings'] ?? array())), $mappings));
		if (count($mappings) !== 4521 || count(array_unique($legacy)) !== 4521 || count(array_unique($canonical)) !== 4521 || $sites !== 4541 || $duplicates !== 20 || $scannerRows !== 4541) {
			throw new RuntimeException('Frozen B3 function-map totals are invalid.');
		}
		$sorted = $legacy;
		sort($sorted, SORT_STRING);
		if ($legacy !== $sorted) {
			throw new RuntimeException('Frozen B3 function map must remain sorted by legacy identifier.');
		}
		foreach ($mappings as $entry) {
			$old = (string) ($entry['legacy_identifier'] ?? '');
			$new = (string) ($entry['canonical_identifier'] ?? '');
			if (!str_starts_with($old, 'vms_') || $new !== 'bvmgr_' . substr($old, 4)) {
				throw new RuntimeException('Invalid frozen B3 mapping: ' . $old);
			}
		}
	}

	public static function buildGraph(string $root, array $map): array
	{
		$root = self::root($root);
		self::validateMap($map);
		$byAny = self::mappingIndex($map);
		$inventory = BVMGR_WPORG_Prefix_Inventory::scan($root);
		$edges = array();
		$referenceCounts = array();
		foreach ((array) ($inventory['public_php_files'] ?? array()) as $file) {
			$scan = self::scanFile($root . '/' . $file, $file, $byAny, false);
			foreach ($scan['references'] as $reference) {
				$legacy = $byAny[$reference['identifier']]['legacy_identifier'];
				$referenceCounts[$legacy][$reference['kind']] = ($referenceCounts[$legacy][$reference['kind']] ?? 0) + 1;
				$caller = (string) ($reference['caller'] ?? '');
				if ($caller === '' || !isset($byAny[$caller])) {
					continue;
				}
				$callerLegacy = $byAny[$caller]['legacy_identifier'];
				$key = $callerLegacy . "\0" . $legacy . "\0" . $reference['kind'];
				if (!isset($edges[$key])) {
					$edges[$key] = array(
						'caller' => $callerLegacy,
						'callee' => $legacy,
						'kind' => $reference['kind'],
						'occurrences' => 0,
						'files' => array(),
					);
				}
				$edges[$key]['occurrences']++;
				$edges[$key]['files'][$file] = true;
			}
		}
		$edgeRows = array_values($edges);
		foreach ($edgeRows as &$edge) {
			$edge['files'] = array_keys($edge['files']);
			sort($edge['files'], SORT_STRING);
		}
		unset($edge);
		usort($edgeRows, static fn(array $a, array $b): int => array($a['caller'], $a['callee'], $a['kind']) <=> array($b['caller'], $b['callee'], $b['kind']));
		ksort($referenceCounts, SORT_STRING);
		foreach ($referenceCounts as &$counts) {
			ksort($counts, SORT_STRING);
		}
		unset($counts);

		return array(
			'schema_version' => 1,
			'artifact' => 'wporg-prefix-b3-dependency-graph',
			'function_map_sha256' => hash('sha256', self::render($map)),
			'counts' => array(
				'nodes' => count((array) ($map['mappings'] ?? array())),
				'edges' => count($edgeRows),
				'direct_call_occurrences' => array_sum(array_column(array_filter($edgeRows, static fn(array $edge): bool => $edge['kind'] === 'direct_call'), 'occurrences')),
			),
			'reference_counts_by_function' => $referenceCounts,
			'edges' => $edgeRows,
		);
	}

	public static function progress(string $root, array $map): array
	{
		$root = self::root($root);
		self::validateMap($map);
		$inventory = BVMGR_WPORG_Prefix_Inventory::scan($root);
		$current = array();
		foreach ((array) ($inventory['symbols']['functions'] ?? array()) as $entry) {
			$current[(string) ($entry['current_identifier'] ?? '')] = count((array) ($entry['declaration_sites'] ?? array()));
		}
		$byAny = self::mappingIndex($map);
		$states = array();
		$issues = array();
		$pendingUnique = 0;
		$pendingSites = 0;
		$migratedUnique = 0;
		$migratedSites = 0;
		foreach ((array) ($map['mappings'] ?? array()) as $entry) {
			$legacy = (string) $entry['legacy_identifier'];
			$canonical = (string) $entry['canonical_identifier'];
			$expected = count((array) $entry['declaration_sites']);
			$oldSites = (int) ($current[$legacy] ?? 0);
			$newSites = (int) ($current[$canonical] ?? 0);
			if ($oldSites === $expected && $newSites === 0) {
				$status = 'pending';
				$pendingUnique++;
				$pendingSites += $expected;
			} elseif ($oldSites === 0 && $newSites === $expected) {
				$status = 'migrated';
				$migratedUnique++;
				$migratedSites += $expected;
			} else {
				$status = 'invalid';
				$issues[] = array('type' => 'declaration_state', 'legacy' => $legacy, 'canonical' => $canonical, 'legacy_sites' => $oldSites, 'canonical_sites' => $newSites, 'expected_sites' => $expected);
			}
			$states[$legacy] = $status;
		}

		$referenceCounts = array();
		foreach ((array) ($inventory['public_php_files'] ?? array()) as $file) {
			$scan = self::scanFile($root . '/' . $file, $file, $byAny, false);
			foreach ($scan['references'] as $reference) {
				$mapping = $byAny[$reference['identifier']];
				$legacy = (string) $mapping['legacy_identifier'];
				$expectedName = ($states[$legacy] ?? '') === 'migrated' ? $mapping['canonical_identifier'] : $legacy;
				$referenceCounts[$reference['kind']] = ($referenceCounts[$reference['kind']] ?? 0) + 1;
				if ($reference['identifier'] !== $expectedName) {
					$issues[] = array(
						'type' => 'stale_or_forward_reference',
						'file' => $file,
						'line' => $reference['line'],
						'kind' => $reference['kind'],
						'found' => $reference['identifier'],
						'expected' => $expectedName,
					);
				}
			}
		}
		ksort($states, SORT_STRING);
		ksort($referenceCounts, SORT_STRING);

		return array(
			'schema_version' => 1,
			'artifact' => 'wporg-prefix-b3-progress',
			'function_map_sha256' => hash('sha256', self::render($map)),
			'status' => $issues === array() ? 'PASS' : 'FAIL',
			'counts' => array(
				'baseline_unique_functions' => 4521,
				'baseline_declaration_sites' => 4541,
				'migrated_unique_functions' => $migratedUnique,
				'migrated_declaration_sites' => $migratedSites,
				'remaining_legacy_unique_functions' => $pendingUnique,
				'remaining_legacy_declaration_sites' => $pendingSites,
				'prohibited_semantic_surface' => $pendingUnique,
			),
			'reference_counts' => $referenceCounts,
			'function_states' => $states,
			'issues' => $issues,
		);
	}

	/**
	 * Apply one exact frozen-map wave to public PHP plus current tests/tooling.
	 * Returns a deterministic transformation report.
	 */
	public static function transform(string $root, array $map, array $legacyNames): array
	{
		$root = self::root($root);
		self::validateMap($map);
		$all = self::mappingIndex($map);
		$selected = array();
		foreach (array_values(array_unique($legacyNames)) as $legacy) {
			if (!isset($all[$legacy]) || $all[$legacy]['legacy_identifier'] !== $legacy) {
				throw new RuntimeException('Unknown or non-legacy B3 wave symbol: ' . $legacy);
			}
			$selected[$legacy] = $all[$legacy];
			$selected[$all[$legacy]['canonical_identifier']] = $all[$legacy];
		}
		$literalDecisions = self::literalDecisionIndex($root, $map, $legacyNames, true);
		$inventory = BVMGR_WPORG_Prefix_Inventory::scan($root);
		$files = array_fill_keys((array) ($inventory['public_php_files'] ?? array()), true);
		foreach (array('tests', 'scripts') as $directory) {
			$path = $root . '/' . $directory;
			if (!is_dir($path)) {
				continue;
			}
			$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
			foreach ($iterator as $file) {
				if ($file->isFile() && strtolower((string) $file->getExtension()) === 'php') {
					$relative = ltrim(str_replace('\\', '/', substr((string) $file->getPathname(), strlen($root))), '/');
					$files[$relative] = true;
				}
			}
		}
		ksort($files, SORT_STRING);
		$changed = array();
		$totals = array();
		foreach (array_keys($files) as $file) {
			$absolute = $root . '/' . $file;
			$source = (string) file_get_contents($absolute);
			$scan = self::scanSource($source, $file, $selected, true, false, $literalDecisions['rename_sites']);
			$replacements = array();
			foreach (array_merge($scan['declarations'], $scan['references']) as $candidate) {
				$identifier = (string) $candidate['identifier'];
				if (!str_starts_with($identifier, 'vms_') || !isset($selected[$identifier])) {
					continue;
				}
				$replacement = (string) $selected[$identifier]['canonical_identifier'];
				if ($candidate['kind'] === 'string_reference') {
					$quote = $source[$candidate['offset']] ?? "'";
					$replacement = $quote . $replacement . $quote;
				}
				$replacements[] = array(
					'offset' => (int) $candidate['offset'],
					'length' => (int) $candidate['length'],
					'replacement' => $replacement,
					'kind' => $candidate['kind'],
				);
			}
			if ($replacements === array()) {
				continue;
			}
			usort($replacements, static fn(array $a, array $b): int => $b['offset'] <=> $a['offset']);
			$seen = array();
			foreach ($replacements as $replacement) {
				$key = $replacement['offset'] . ':' . $replacement['length'];
				if (isset($seen[$key])) {
					continue;
				}
				$seen[$key] = true;
				$source = substr_replace($source, $replacement['replacement'], $replacement['offset'], $replacement['length']);
				$totals[$replacement['kind']] = ($totals[$replacement['kind']] ?? 0) + 1;
			}
			if (file_put_contents($absolute, $source) === false) {
				throw new RuntimeException('Unable to write transformed file: ' . $file);
			}
			$changed[] = $file;
		}
		ksort($totals, SORT_STRING);
		return array(
			'schema_version' => 1,
			'artifact' => 'wporg-prefix-b3-transformation-report',
			'mapped_functions' => count($legacyNames),
			'changed_files' => $changed,
			'replacement_counts' => $totals,
		);
	}

	/**
	 * Validate frozen exact-literal decisions and return the selected-wave index.
	 * Exact literals not already proven by function_exists/callback/reflection must
	 * be explicitly classified before a wave can transform.
	 */
	public static function literalDecisionIndex(string $root, array $map, array $legacyNames = array(), bool $requireSelected = false): array
	{
		$root = self::root($root);
		$artifact = self::loadJson($root . '/' . self::LITERAL_DECISIONS_PATH);
		if (($artifact['artifact'] ?? '') !== 'wporg-prefix-b3-literal-decisions' || ($artifact['schema_version'] ?? null) !== 1) {
			throw new RuntimeException('Invalid B3 exact-literal decision artifact header.');
		}
		$all = self::mappingIndex($map);
		$selected = array_fill_keys(array_values(array_unique($legacyNames)), true);
		$known = array();
		$exactOnly = array();
		foreach ((array) ($map['mappings'] ?? array()) as $mapping) {
			$legacy = (string) ($mapping['legacy_identifier'] ?? '');
			$proven = array();
			foreach (array('function_exists', 'callback', 'reflection') as $kind) {
				foreach ((array) ($mapping['baseline_dynamic_sites'][$kind] ?? array()) as $site) {
					$proven[(string) ($site['file'] ?? '') . ':' . (int) ($site['line'] ?? 0)] = true;
				}
			}
			foreach ((array) ($mapping['baseline_dynamic_sites']['exact_literal'] ?? array()) as $site) {
				$location = (string) ($site['file'] ?? '') . ':' . (int) ($site['line'] ?? 0);
				if (!isset($proven[$location])) {
					$exactOnly[$legacy . '|' . (string) ($site['file'] ?? '') . '|' . (int) ($site['line'] ?? 0)] = true;
				}
			}
		}
		$rename = array();
		$counts = array('rename' => 0, 'retain' => 0);
		$selectedCounts = array('rename' => 0, 'retain' => 0);
		foreach ((array) ($artifact['decisions'] ?? array()) as $decision) {
			$legacy = (string) ($decision['legacy_identifier'] ?? '');
			$file = (string) ($decision['file'] ?? '');
			$line = (int) ($decision['line'] ?? 0);
			$action = (string) ($decision['decision'] ?? '');
			$key = $legacy . '|' . $file . '|' . $line;
			if (!isset($all[$legacy]) || $all[$legacy]['legacy_identifier'] !== $legacy || !isset($exactOnly[$key])) {
				throw new RuntimeException('B3 literal decision does not match a frozen exact-only site: ' . $key);
			}
			if (isset($known[$key]) || !in_array($action, array('rename', 'retain'), true) || trim((string) ($decision['reason'] ?? '')) === '') {
				throw new RuntimeException('Invalid or duplicate B3 literal decision: ' . $key);
			}
			$known[$key] = $action;
			$counts[$action]++;
			if (isset($selected[$legacy])) {
				$selectedCounts[$action]++;
			}
			if ($action === 'rename') {
				$rename[$key] = true;
			}
		}
		if ($requireSelected) {
			foreach ($exactOnly as $key => $_value) {
				$legacy = substr($key, 0, (int) strpos($key, '|'));
				if (isset($selected[$legacy]) && !isset($known[$key])) {
					throw new RuntimeException('Unresolved B3 exact function-literal decision for selected wave: ' . $key);
				}
			}
		}
		ksort($rename, SORT_STRING);
		return array('rename_sites' => $rename, 'decisions' => $known, 'counts' => $counts, 'selected_counts' => $selectedCounts);
	}

	/** Update source-introspection literals in tests that do not bind to the untouched live tree. */
	public static function transformTestLiterals(string $root, array $map, array $legacyNames): array
	{
		$root = self::root($root);
		$artifact = self::loadJson($root . '/' . self::LITERAL_DECISIONS_PATH);
		$retainedSites = array();
		foreach ((array) ($artifact['test_literal_retained_sites'] ?? array()) as $site) {
			$key = (string) ($site['file'] ?? '') . '|' . (int) ($site['line'] ?? 0) . '|' . (string) ($site['legacy_identifier'] ?? '');
			if ($key === '|0|' || isset($retainedSites[$key]) || trim((string) ($site['reason'] ?? '')) === '') {
				throw new RuntimeException('Invalid or duplicate B3 retained test-literal site: ' . $key);
			}
			$retainedSites[$key] = true;
		}
		$all = self::mappingIndex($map);
		$replacements = array();
		foreach (array_values(array_unique($legacyNames)) as $legacy) {
			if (!isset($all[$legacy]) || $all[$legacy]['legacy_identifier'] !== $legacy) {
				throw new RuntimeException('Unknown test-literal B3 wave symbol: ' . $legacy);
			}
			$replacements[$legacy] = (string) $all[$legacy]['canonical_identifier'];
		}
		uksort($replacements, static fn(string $a, string $b): int => strlen($b) <=> strlen($a) ?: strcmp($a, $b));
		$pattern = '/(?<![A-Za-z0-9_])(?:' . implode('|', array_map(static fn(string $name): string => preg_quote($name, '/'), array_keys($replacements))) . ')(?![A-Za-z0-9_])/';
		$changed = array();
		$count = 0;
		$testsRoot = $root . '/tests';
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($testsRoot, FilesystemIterator::SKIP_DOTS));
		foreach ($iterator as $file) {
			if (!$file->isFile() || strtolower((string) $file->getExtension()) !== 'php') {
				continue;
			}
			$relative = ltrim(str_replace('\\', '/', substr((string) $file->getPathname(), strlen($root))), '/');
			$source = (string) file_get_contents((string) $file->getPathname());
			if (str_contains($source, '../../vms') || str_contains($source, "dirname(dirname(\$pluginRoot)) . '/vms'")) {
				continue;
			}
			$tokens = token_get_all($source);
			$offset = 0;
			$candidates = array();
			foreach ($tokens as $token) {
				$text = is_array($token) ? (string) $token[1] : (string) $token;
				if (is_array($token) && in_array($token[0], array(T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE), true)) {
					$localCount = 0;
					$line = (int) $token[2];
					$updated = preg_replace_callback(
						$pattern,
						static function (array $match) use ($replacements, $retainedSites, $relative, $line, &$localCount): string {
							if (isset($retainedSites[$relative . '|' . $line . '|' . $match[0]])) {
								return $match[0];
							}
							$localCount++;
							return $replacements[$match[0]];
						},
						$text
					);
					if (is_string($updated) && $localCount > 0) {
						$candidates[] = array('offset' => $offset, 'length' => strlen($text), 'replacement' => $updated);
						$count += $localCount;
					}
				}
				$offset += strlen($text);
			}
			if ($candidates === array()) {
				continue;
			}
			usort($candidates, static fn(array $a, array $b): int => $b['offset'] <=> $a['offset']);
			foreach ($candidates as $candidate) {
				$source = substr_replace($source, $candidate['replacement'], $candidate['offset'], $candidate['length']);
			}
			if (file_put_contents((string) $file->getPathname(), $source) === false) {
				throw new RuntimeException('Unable to write transformed test literals: ' . $file->getPathname());
			}
			$changed[] = $relative;
		}
		sort($changed, SORT_STRING);
		return array('artifact' => 'wporg-prefix-b3-test-literal-transform', 'mapped_functions' => count($legacyNames), 'changed_files' => $changed, 'replacement_count' => $count);
	}

	/** Apply selected core function identities to one disposable add-on consumer. */
	public static function transformAddonConsumers(string $addonRoot, array $map, array $legacyNames): array
	{
		$addonRoot = self::root($addonRoot);
		self::validateMap($map);
		$all = self::mappingIndex($map);
		$selected = array();
		foreach (array_values(array_unique($legacyNames)) as $legacy) {
			if (!isset($all[$legacy]) || $all[$legacy]['legacy_identifier'] !== $legacy) {
				throw new RuntimeException('Unknown add-on B3 consumer symbol: ' . $legacy);
			}
			$selected[$legacy] = $all[$legacy];
			$selected[(string) $all[$legacy]['canonical_identifier']] = $all[$legacy];
		}
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($addonRoot, FilesystemIterator::SKIP_DOTS));
		$files = array();
		foreach ($iterator as $file) {
			if ($file->isFile() && strtolower((string) $file->getExtension()) === 'php') {
				$files[] = ltrim(str_replace('\\', '/', substr((string) $file->getPathname(), strlen($addonRoot))), '/');
			}
		}
		sort($files, SORT_STRING);
		$changed = array();
		$seen = array();
		$totals = array();
		$transformedSources = array();
		foreach ($files as $file) {
			$absolute = $addonRoot . '/' . $file;
			$source = (string) file_get_contents($absolute);
			$scan = self::scanSource($source, $file, $selected, true, true);
			if ($scan['declarations'] !== array()) {
				throw new RuntimeException('Disposable add-on declares a selected core function: ' . $file . ':' . $scan['declarations'][0]['line']);
			}
			$replacements = array();
			foreach ($scan['references'] as $candidate) {
				$identifier = (string) $candidate['identifier'];
				if (!str_starts_with($identifier, 'vms_') || !isset($selected[$identifier])) {
					continue;
				}
				$seen[$identifier] = true;
				$replacement = (string) $selected[$identifier]['canonical_identifier'];
				if ($candidate['kind'] === 'string_reference') {
					$quote = $source[$candidate['offset']] ?? "'";
					$replacement = $quote . $replacement . $quote;
				}
				$replacements[] = array('offset' => (int) $candidate['offset'], 'length' => (int) $candidate['length'], 'replacement' => $replacement, 'kind' => $candidate['kind']);
			}
			if ($replacements === array()) {
				continue;
			}
			usort($replacements, static fn(array $a, array $b): int => $b['offset'] <=> $a['offset']);
			$positions = array();
			foreach ($replacements as $replacement) {
				$key = $replacement['offset'] . ':' . $replacement['length'];
				if (isset($positions[$key])) {
					continue;
				}
				$positions[$key] = true;
				$source = substr_replace($source, $replacement['replacement'], $replacement['offset'], $replacement['length']);
				$totals[$replacement['kind']] = ($totals[$replacement['kind']] ?? 0) + 1;
			}
			$transformedSources[$absolute] = $source;
			$changed[] = $file;
		}
		foreach ($transformedSources as $absolute => $source) {
			if (file_put_contents($absolute, $source) === false) {
				throw new RuntimeException('Unable to write disposable add-on file: ' . $absolute);
			}
		}
		ksort($totals, SORT_STRING);
		$matched = array_keys($seen);
		sort($matched, SORT_STRING);
		$unmatched = array_values(array_diff(array_values(array_unique($legacyNames)), $matched));
		sort($unmatched, SORT_STRING);
		return array(
			'artifact' => 'wporg-prefix-b3-addon-transform',
			'mapped_functions' => count(array_unique($legacyNames)),
			'matched_function_consumers' => $matched,
			'unmatched_manifest_candidates' => $unmatched,
			'changed_files' => $changed,
			'replacement_counts' => $totals,
		);
	}

	public static function addonConsumerInventory(string $addonRoot, array $map, array $legacyNames): array
	{
		$addonRoot = self::root($addonRoot);
		self::validateMap($map);
		$all = self::mappingIndex($map);
		$selected = array();
		foreach (array_values(array_unique($legacyNames)) as $legacy) {
			if (!isset($all[$legacy]) || $all[$legacy]['legacy_identifier'] !== $legacy) {
				throw new RuntimeException('Unknown add-on inventory symbol: ' . $legacy);
			}
			$selected[$legacy] = $all[$legacy];
			$selected[(string) $all[$legacy]['canonical_identifier']] = $all[$legacy];
		}
		$counts = array('legacy_references' => array(), 'canonical_references' => array(), 'selected_declarations' => array());
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($addonRoot, FilesystemIterator::SKIP_DOTS));
		foreach ($iterator as $file) {
			if (!$file->isFile() || strtolower((string) $file->getExtension()) !== 'php') {
				continue;
			}
			$relative = ltrim(str_replace('\\', '/', substr((string) $file->getPathname(), strlen($addonRoot))), '/');
			$scan = self::scanSource((string) file_get_contents((string) $file->getPathname()), $relative, $selected, false, true);
			foreach ($scan['declarations'] as $declaration) {
				$counts['selected_declarations'][] = array('file' => $relative, 'line' => $declaration['line'], 'identifier' => $declaration['identifier']);
			}
			foreach ($scan['references'] as $reference) {
				$identifier = (string) $reference['identifier'];
				$bucket = str_starts_with($identifier, 'vms_') ? 'legacy_references' : 'canonical_references';
				$counts[$bucket][$identifier] = ($counts[$bucket][$identifier] ?? 0) + 1;
			}
		}
		ksort($counts['legacy_references'], SORT_STRING);
		ksort($counts['canonical_references'], SORT_STRING);
		return $counts;
	}

	private static function mappingIndex(array $map): array
	{
		$index = array();
		foreach ((array) ($map['mappings'] ?? array()) as $entry) {
			$index[(string) $entry['legacy_identifier']] = $entry;
			$index[(string) $entry['canonical_identifier']] = $entry;
		}
		return $index;
	}

	private static function scanFile(string $absolute, string $relative, array $index, bool $forTransform): array
	{
		return self::scanSource((string) file_get_contents($absolute), $relative, $index, $forTransform);
	}

	private static function scanSource(string $source, string $file, array $index, bool $forTransform, bool $consumerMode = false, array $approvedLiteralSites = array()): array
	{
		$tokens = token_get_all($source);
		$offsets = array();
		$offset = 0;
		foreach ($tokens as $i => $token) {
			$offsets[$i] = $offset;
			$offset += strlen(is_array($token) ? $token[1] : $token);
		}
		$declarationSites = array();
		$literalSites = array();
		foreach ($index as $name => $entry) {
			if ($name !== ($entry['legacy_identifier'] ?? null)) {
				continue;
			}
			foreach ((array) ($entry['declaration_sites'] ?? array()) as $site) {
				$declarationSites[$name . '|' . (string) ($site['file'] ?? '') . '|' . (int) ($site['line'] ?? 0)] = true;
			}
			foreach (array('function_exists', 'callback', 'reflection') as $kind) {
				foreach ((array) ($entry['baseline_dynamic_sites'][$kind] ?? array()) as $site) {
					$literalSites[$name . '|' . (string) ($site['file'] ?? '') . '|' . (int) ($site['line'] ?? 0)] = true;
				}
			}
		}
		foreach ($approvedLiteralSites as $key => $_approved) {
			$literalSites[$key] = true;
		}
		$declarations = array();
		$references = array();
		$callStack = array();
		$pendingCall = null;
		$pendingFunction = null;
		$declarationTokenIndexes = array();
		$functionStack = array();
		$classDepths = array();
		$pendingClass = false;
		$brace = 0;
		$count = count($tokens);
		for ($i = 0; $i < $count; $i++) {
			$token = $tokens[$i];
			if (is_array($token)) {
				$id = $token[0];
				$text = $token[1];
				$line = (int) $token[2];
				if (in_array($id, array(T_CLASS, T_INTERFACE, T_TRAIT), true)) {
					$previous = self::previousSignificant($tokens, $i);
					if (!(is_array($previous) && $previous[0] === T_DOUBLE_COLON)) {
						$pendingClass = true;
					}
				}
				if ($id === T_FUNCTION) {
					$nameIndex = self::nextNameIndex($tokens, $i);
					$pendingFunction = $nameIndex !== null && is_array($tokens[$nameIndex]) ? (string) $tokens[$nameIndex][1] : '';
					if ($nameIndex !== null) {
						$declarationTokenIndexes[$nameIndex] = true;
					}
				}
				if ($id === T_STRING && isset($index[$text])) {
					$previous = self::previousSignificant($tokens, $i);
					$next = self::nextSignificant($tokens, $i);
					$isDeclaration = isset($declarationTokenIndexes[$i]);
					if ($isDeclaration && $classDepths === array()) {
						$legacy = (string) $index[$text]['legacy_identifier'];
						$authorized = isset($declarationSites[$legacy . '|' . $file . '|' . $line]);
					if ($authorized || $consumerMode || ($forTransform && str_starts_with($file, 'tests/'))) {
							$declarations[] = array('kind' => 'declaration', 'identifier' => $text, 'file' => $file, 'line' => $line, 'offset' => $offsets[$i], 'length' => strlen($text), 'caller' => '');
						}
					} elseif ($next === '(' && !self::isMethodOrTypeContext($previous)) {
						$references[] = array('kind' => 'direct_call', 'identifier' => $text, 'file' => $file, 'line' => $line, 'offset' => $offsets[$i], 'length' => strlen($text), 'caller' => self::currentCaller($functionStack));
						$pendingCall = strtolower($text);
					} elseif ($next === '(') {
						$pendingCall = strtolower($text);
					}
				} elseif ($id === T_STRING && self::nextSignificant($tokens, $i) === '(') {
					$pendingCall = strtolower($text);
				}
				if ($id === T_CONSTANT_ENCAPSED_STRING) {
					$value = self::literal($text);
					$legacy = isset($index[$value]) ? (string) $index[$value]['legacy_identifier'] : '';
					// Exact literals are authorized by the frozen B2.5 site inventory. This
					// avoids mistaking a retained bvmgr_* key/global-slot contract for the
					// canonical target of an unrelated function with the same suffix.
					if ($legacy !== '' && (isset($literalSites[$legacy . '|' . $file . '|' . $line]) || self::isProvenStringReference($tokens, $i, $callStack, $brace, $file))) {
						$references[] = array('kind' => 'string_reference', 'identifier' => $value, 'file' => $file, 'line' => $line, 'offset' => $offsets[$i], 'length' => strlen($text), 'caller' => self::currentCaller($functionStack));
					}
				}
				continue;
			}

			if ($token === '(') {
				$callStack[] = array('name' => $pendingCall, 'argument' => 0, 'brace' => $brace);
				$pendingCall = null;
			} elseif ($token === ')') {
				array_pop($callStack);
			} elseif ($token === ',' && $callStack !== array()) {
				$callStack[count($callStack) - 1]['argument']++;
			} elseif ($token === '{') {
				$brace++;
				if ($pendingClass) {
					$classDepths[] = $brace;
					$pendingClass = false;
				}
				if ($pendingFunction !== null) {
					$functionStack[] = array('depth' => $brace, 'name' => $classDepths === array() ? $pendingFunction : '');
					$pendingFunction = null;
				}
			} elseif ($token === '}') {
				if ($functionStack !== array() && end($functionStack)['depth'] === $brace) {
					array_pop($functionStack);
				}
				if ($classDepths !== array() && end($classDepths) === $brace) {
					array_pop($classDepths);
				}
				$brace--;
			} elseif ($token === ';') {
				$pendingFunction = null;
			} elseif (!ctype_space($token) && $token !== '&') {
				$pendingCall = null;
			}
		}
		return array('declarations' => $declarations, 'references' => $references);
	}

	private static function isProvenStringReference(array $tokens, int $index, array $callStack, int $brace, string $file): bool
	{
		$frame = $callStack === array() ? null : end($callStack);
		if (is_array($frame) && $frame['brace'] === $brace) {
			$name = (string) ($frame['name'] ?? '');
			$argument = (int) ($frame['argument'] ?? -1);
			if (isset(self::CALLABLE_ARGUMENTS[$name]) && self::CALLABLE_ARGUMENTS[$name] === $argument) {
				return true;
			}
			if ($name === 'reflectionfunction' && $argument === 0) {
				return true;
			}
			if (str_starts_with($file, 'tests/') && $argument === 1 && str_contains($name, 'extract') && str_contains($name, 'function')) {
				return true;
			}
		}
		$previousIndex = self::previousSignificantIndex($tokens, $index);
		if ($previousIndex !== null && (is_array($tokens[$previousIndex]) ? $tokens[$previousIndex][0] === T_DOUBLE_ARROW : $tokens[$previousIndex] === '=>')) {
			$keyIndex = self::previousSignificantIndex($tokens, $previousIndex);
			if ($keyIndex !== null && is_array($tokens[$keyIndex]) && $tokens[$keyIndex][0] === T_CONSTANT_ENCAPSED_STRING) {
				return in_array(self::literal((string) $tokens[$keyIndex][1]), self::REGISTRY_CALLBACK_KEYS, true);
			}
		}
		return false;
	}

	private static function isMethodOrTypeContext($previous): bool
	{
		if (!is_array($previous)) {
			return false;
		}
		$blocked = array(T_FUNCTION, T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NEW);
		if (defined('T_NULLSAFE_OBJECT_OPERATOR')) {
			$blocked[] = constant('T_NULLSAFE_OBJECT_OPERATOR');
		}
		return in_array($previous[0], $blocked, true);
	}

	private static function currentCaller(array $functionStack): string
	{
		if ($functionStack === array()) {
			return '';
		}
		return (string) end($functionStack)['name'];
	}

	private static function nextNameIndex(array $tokens, int $index): ?int
	{
		$count = count($tokens);
		for ($i = $index + 1; $i < $count; $i++) {
			$token = $tokens[$i];
			$ampersandTokens = array();
			foreach (array('T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG', 'T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG') as $constant) {
				if (defined($constant)) {
					$ampersandTokens[] = constant($constant);
				}
			}
			if (
				$token === '&'
				|| (is_array($token) && in_array($token[0], array_merge(array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), $ampersandTokens), true))
			) {
				continue;
			}
			if (is_array($token) && $token[0] === T_STRING) {
				return $i;
			}
			return null;
		}
		return null;
	}

	private static function previousSignificant(array $tokens, int $index)
	{
		$i = self::previousSignificantIndex($tokens, $index);
		return $i === null ? null : $tokens[$i];
	}

	private static function previousSignificantIndex(array $tokens, int $index): ?int
	{
		for ($i = $index - 1; $i >= 0; $i--) {
			if (is_array($tokens[$i]) && in_array($tokens[$i][0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
				continue;
			}
			return $i;
		}
		return null;
	}

	private static function nextSignificant(array $tokens, int $index)
	{
		$count = count($tokens);
		for ($i = $index + 1; $i < $count; $i++) {
			if (is_array($tokens[$i]) && in_array($tokens[$i][0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
				continue;
			}
			return $tokens[$i];
		}
		return null;
	}

	private static function literal(string $literal): string
	{
		if (strlen($literal) < 2) {
			return $literal;
		}
		$quote = $literal[0];
		$value = substr($literal, 1, -1);
		return $quote === "'" ? str_replace(array("\\\\", "\\'"), array("\\", "'"), $value) : stripcslashes($value);
	}

	private static function root(string $root): string
	{
		$real = realpath($root);
		if ($real === false || !is_dir($real)) {
			throw new RuntimeException('Unreadable B3 root: ' . $root);
		}
		return rtrim(str_replace('\\', '/', $real), '/');
	}
}
