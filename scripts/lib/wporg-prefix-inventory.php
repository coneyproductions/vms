<?php
declare(strict_types=1);

/**
 * Token-based inventory for the WordPress.org prefix migration.
 *
 * This file is release-excluded. Retained strings such as table names, hooks,
 * routes, and fixtures are intentionally outside this declaration scanner.
 */
final class BVMGR_WPORG_Prefix_Inventory
{
	private const ROOT_FILES = array(
		'backstage-venue-manager.php',
		'vendor-management-system.php',
		'vms.php',
		'uninstall.php',
	);

	private const LOADER_GLOBALS = array(
		'vms_canonical_plugin_file',
		'vms_optional_bootstrap_files',
		'vms_optional_bootstrap_file',
		'vms_ref_keys_map',
		'vms_square_firewall_filter_name',
	);

	private const CALLBACK_ARGUMENTS = array(
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
		'register_rest_route' => 2,
		'register_setting' => 2,
	);

	public static function scan(string $root): array
	{
		$root = self::root($root);
		$files = self::files($root);
		$declarations = self::emptyDeclarations();
		foreach ($files as $file) {
			self::scanDeclarations($file, (string) file_get_contents($root . '/' . $file), $declarations);
		}
		self::sortMaps($declarations);
		$dynamic = self::scanDynamic($root, $files, $declarations);

		return array(
			'public_php_files' => $files,
			'counts' => self::counts($files, $declarations, $dynamic),
			'symbols' => self::symbolManifest($declarations),
			'dynamic_symbols' => $dynamic,
		);
	}

	public static function scanSource(string $source, string $label = 'fixture.php'): array
	{
		$declarations = self::emptyDeclarations();
		self::scanDeclarations($label, $source, $declarations);
		self::sortMaps($declarations);
		return $declarations;
	}

	public static function prohibitedDeclarations(array $declarations): array
	{
		$result = array();
		foreach (array('functions', 'classes', 'interfaces', 'traits', 'enums', 'constants') as $kind) {
			foreach (array_keys($declarations[$kind] ?? array()) as $name) {
				if (self::shortPrefix((string) $name)) {
					$result[] = $kind . ':' . $name;
				}
			}
		}
		foreach (array_keys($declarations['global_slots'] ?? array()) as $slot) {
			$name = (string) preg_replace('/^(?:GLOBALS:|global:|loader:)/', '', (string) $slot);
			if (self::shortPrefix($name)) {
				$result[] = 'global_slots:' . $slot;
			}
		}
		sort($result, SORT_STRING);
		return $result;
	}

	public static function canonicalTarget(string $kind, string $name): ?string
	{
		if ($kind === 'functions' && str_starts_with($name, 'vms_')) {
			return 'bvmgr_' . substr($name, 4);
		}
		if (in_array($kind, array('classes', 'interfaces', 'traits', 'enums', 'constants'), true) && str_starts_with($name, 'VMS_')) {
			return 'BVMGR_' . substr($name, 4);
		}
		if ($kind === 'global_slots') {
			return preg_replace('/vms_/', 'bvmgr_', $name, 1);
		}
		return null;
	}

	private static function root(string $root): string
	{
		$real = realpath($root);
		if ($real === false || !is_dir($real)) {
			throw new RuntimeException('Unreadable plugin root: ' . $root);
		}
		return rtrim(str_replace('\\', '/', $real), '/');
	}

	private static function files(string $root): array
	{
		$files = array();
		foreach (self::ROOT_FILES as $file) {
			if (is_file($root . '/' . $file)) {
				$files[] = $file;
			}
		}
		foreach (array('admin', 'includes') as $directory) {
			$path = $root . '/' . $directory;
			if (!is_dir($path)) {
				continue;
			}
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
			);
			foreach ($iterator as $file) {
				if (!$file->isFile() || strtolower((string) $file->getExtension()) !== 'php') {
					continue;
				}
				$relative = ltrim(str_replace('\\', '/', substr((string) $file->getPathname(), strlen($root))), '/');
				if (!str_starts_with($relative, 'includes/safety/')) {
					$files[] = $relative;
				}
			}
		}
		$files = array_values(array_unique($files));
		sort($files, SORT_STRING);
		return $files;
	}

	private static function emptyDeclarations(): array
	{
		return array(
			'functions' => array(),
			'classes' => array(),
			'interfaces' => array(),
			'traits' => array(),
			'enums' => array(),
			'constants' => array(),
			'namespaces' => array(),
			'global_slots' => array(),
		);
	}

	private static function scanDeclarations(string $file, string $source, array &$out): void
	{
		$tokens = token_get_all($source);
		$brace = 0;
		$classDepths = array();
		$functionDepths = array();
		$pendingClass = false;
		$pendingFunction = false;

		foreach ($tokens as $index => $token) {
			if (is_array($token)) {
				$id = $token[0];
				$text = $token[1];
				$line = (int) $token[2];
				if ($id === T_CURLY_OPEN || $id === T_DOLLAR_OPEN_CURLY_BRACES) {
					$brace++;
					continue;
				}
				if (self::isTypeToken($id)) {
					$previous = self::previous($tokens, $index);
					if ((is_array($previous) && $previous[0] === T_DOUBLE_COLON) || $previous === '::') {
						continue;
					}
					$anonymous = is_array($previous) && $previous[0] === T_NEW;
					$name = $anonymous ? null : self::nextName($tokens, $index);
					if (!$anonymous && $name !== null) {
						self::site($out[self::typeKind($id)], $name, $file, $line);
					}
					$pendingClass = true;
					continue;
				}
				if ($id === T_FUNCTION) {
					$name = self::nextName($tokens, $index);
					if ($name !== null && $classDepths === array()) {
						self::site($out['functions'], $name, $file, $line);
					}
					$pendingFunction = true;
					continue;
				}
				if ($id === T_NAMESPACE) {
					self::site($out['namespaces'], self::namespaceName($tokens, $index), $file, $line);
					continue;
				}
				if ($id === T_CONST && $classDepths === array()) {
					foreach (self::constNames($tokens, $index) as $name) {
						if (self::pluginConstant($name)) {
							self::site($out['constants'], $name, $file, $line);
						}
					}
					continue;
				}
				if ($id === T_STRING && strcasecmp($text, 'define') === 0) {
					$name = self::firstStringArgument($tokens, $index);
					if ($name !== null && self::pluginConstant($name)) {
						self::site($out['constants'], $name, $file, $line);
					}
					continue;
				}
				if ($id === T_GLOBAL) {
					for ($cursor = $index + 1, $count = count($tokens); $cursor < $count; $cursor++) {
						$current = $tokens[$cursor];
						if ($current === ';') {
							break;
						}
						if (is_array($current) && $current[0] === T_VARIABLE) {
							$name = ltrim($current[1], '$');
							if (self::ownedLowerPrefix($name)) {
								self::site($out['global_slots'], 'global:' . $name, $file, (int) $current[2]);
							}
						}
					}
					continue;
				}
				if ($id === T_VARIABLE && $text === '$GLOBALS') {
					$name = self::arrayStringKey($tokens, $index);
					if ($name !== null && self::ownedLowerPrefix($name)) {
						self::site($out['global_slots'], 'GLOBALS:' . $name, $file, $line);
					}
				}
				if ($id === T_VARIABLE && $classDepths === array() && $functionDepths === array()) {
					$name = ltrim($text, '$');
					if (in_array($name, self::LOADER_GLOBALS, true)) {
						self::site($out['global_slots'], 'loader:' . $name, $file, $line);
					}
				}
				continue;
			}

			if ($token === '{') {
				$brace++;
				if ($pendingClass) {
					$classDepths[] = $brace;
					$pendingClass = false;
				}
				if ($pendingFunction) {
					$functionDepths[] = $brace;
					$pendingFunction = false;
				}
			} elseif ($token === '}') {
				if ($functionDepths !== array() && end($functionDepths) === $brace) {
					array_pop($functionDepths);
				}
				if ($classDepths !== array() && end($classDepths) === $brace) {
					array_pop($classDepths);
				}
				$brace--;
			} elseif ($token === ';') {
				$pendingFunction = false;
			}
		}
	}

	private static function scanDynamic(string $root, array $files, array $declarations): array
	{
		$functions = array_fill_keys(array_keys($declarations['functions']), true);
		$types = array_fill_keys(array_merge(
			array_keys($declarations['classes']),
			array_keys($declarations['interfaces']),
			array_keys($declarations['traits']),
			array_keys($declarations['enums'])
		), true);
		$literalFunctions = array();
		$functionExists = array();
		$callbacks = array();
		$reflection = array();
		$literalTypes = array();
		$callTokens = self::callTokens();

		foreach ($files as $file) {
			$tokens = token_get_all((string) file_get_contents($root . '/' . $file));
			$stack = array();
			$pendingCall = null;
			$brace = 0;
			foreach ($tokens as $index => $token) {
				if (is_array($token)) {
					$id = $token[0];
					$text = $token[1];
					$line = (int) $token[2];
					if ($id === T_CURLY_OPEN || $id === T_DOLLAR_OPEN_CURLY_BRACES) {
						$brace++;
						continue;
					}
					if (in_array($id, $callTokens, true) && self::nextSignificant($tokens, $index) === '(') {
						$pendingCall = strtolower($text);
					}
					if ($id === T_CONSTANT_ENCAPSED_STRING) {
						$value = self::literal($text);
						$frame = self::nearestCall($stack);
						if (isset($functions[$value])) {
							self::site($literalFunctions, $value, $file, $line);
						}
						if ($frame !== null && self::ownedLowerPrefix($value)) {
							if ($frame['name'] === 'function_exists') {
								self::site($functionExists, $value, $file, $line);
							}
							if (
								isset(self::CALLBACK_ARGUMENTS[$frame['name']])
								&& $frame['argument'] === self::CALLBACK_ARGUMENTS[$frame['name']]
								&& $frame['brace'] === $brace
							) {
								self::site($callbacks, $value, $file, $line, array('registrar' => $frame['name']));
							}
							if ($frame['name'] === 'reflectionfunction') {
								self::site($reflection, $value, $file, $line);
							}
						}
						if (isset($types[$value])) {
							self::site($literalTypes, $value, $file, $line);
						}
					}
					continue;
				}
				if ($token === '(') {
					$stack[] = array('name' => $pendingCall, 'argument' => 0, 'brace' => $brace);
					$pendingCall = null;
				} elseif ($token === ')') {
					array_pop($stack);
				} elseif ($token === ',' && $stack !== array()) {
					$stack[count($stack) - 1]['argument']++;
				} elseif ($token === '{') {
					$brace++;
				} elseif ($token === '}') {
					$brace--;
				} elseif (!ctype_space($token) && $token !== '&') {
					$pendingCall = null;
				}
			}
		}

		$dynamic = array(
			'exact_function_literals' => $literalFunctions,
			'function_exists_checks' => $functionExists,
			'direct_literal_callbacks' => $callbacks,
			'reflection_references' => $reflection,
			'exact_type_literals' => $literalTypes,
			'duplicate_function_families' => self::duplicates($declarations['functions']),
			'duplicate_constant_families' => self::duplicates($declarations['constants']),
		);
		$dynamic['function_resolution_requirements'] = self::resolutionRequirements(
			array($functionExists, $callbacks, $reflection),
			$functions
		);
		self::sortMaps($dynamic);
		return $dynamic;
	}

	private static function resolutionRequirements(array $maps, array $declaredFunctions): array
	{
		$names = array();
		foreach ($maps as $map) {
			foreach (array_keys($map) as $name) {
				$names[$name] = true;
			}
		}
		ksort($names, SORT_STRING);

		$result = array();
		foreach (array_keys($names) as $name) {
			$result[$name][] = array(
				'current_identifier' => $name,
				'canonical_target' => self::canonicalTarget('functions', $name),
				'resolution_policy' => isset($declaredFunctions[$name])
					? 'core-current-or-canonical-must-resolve'
					: 'external-or-dynamic-contract-map-only',
			);
		}
		return $result;
	}

	private static function symbolManifest(array $declarations): array
	{
		$result = array();
		foreach (array('functions', 'classes', 'interfaces', 'traits', 'enums', 'constants', 'global_slots') as $kind) {
			$result[$kind] = array();
			foreach ($declarations[$kind] as $name => $sites) {
				$result[$kind][] = array(
					'current_identifier' => $name,
					'canonical_target' => self::canonicalTarget($kind, $name),
					'b0_strategy' => array(1),
					'compatibility_classification' => $kind === 'functions'
						? 'direct-rename-no-public-package-wrapper'
						: 'atomic-global-symbol-rename',
					'persistence_external_contract_status' => 'nonpersistent-global-php',
					'planned_implementation_batch' => $kind === 'functions' ? 'B3' : 'B2',
					'do_not_rename' => false,
					'declaration_sites' => $sites,
				);
			}
		}
		return $result;
	}

	private static function counts(array $files, array $declarations, array $dynamic): array
	{
		$result = array('public_php_files' => count($files));
		foreach (array_keys($declarations) as $kind) {
			$result[$kind] = array('unique' => count($declarations[$kind]), 'occurrences' => self::siteCount($declarations[$kind]));
		}
		$result['dynamic_symbols'] = array(
			'exact_function_literals_unique' => count($dynamic['exact_function_literals']),
			'exact_function_literals_occurrences' => self::siteCount($dynamic['exact_function_literals']),
			'function_exists_unique' => count($dynamic['function_exists_checks']),
			'function_exists_occurrences' => self::siteCount($dynamic['function_exists_checks']),
			'direct_literal_callbacks_unique' => count($dynamic['direct_literal_callbacks']),
			'direct_literal_callbacks_occurrences' => self::siteCount($dynamic['direct_literal_callbacks']),
			'exact_type_literals_unique' => count($dynamic['exact_type_literals']),
			'exact_type_literals_occurrences' => self::siteCount($dynamic['exact_type_literals']),
			'duplicate_function_families' => count($dynamic['duplicate_function_families']),
			'duplicate_constant_families' => count($dynamic['duplicate_constant_families']),
		);
		return $result;
	}

	private static function duplicates(array $map): array
	{
		return array_filter($map, static fn(array $sites): bool => count($sites) > 1);
	}

	private static function site(array &$map, string $name, string $file, int $line, array $extra = array()): void
	{
		$map[$name][] = array_merge(array('file' => $file, 'line' => $line), $extra);
	}

	private static function sortMaps(array &$maps): void
	{
		foreach ($maps as &$map) {
			ksort($map, SORT_STRING);
			foreach ($map as &$sites) {
				usort($sites, static fn(array $a, array $b): int => array($a['file'], $a['line'], $a['registrar'] ?? '') <=> array($b['file'], $b['line'], $b['registrar'] ?? ''));
			}
			unset($sites);
		}
		unset($map);
	}

	private static function siteCount(array $map): int
	{
		return array_sum(array_map('count', $map));
	}

	private static function isTypeToken(int $id): bool
	{
		$ids = array(T_CLASS, T_INTERFACE, T_TRAIT);
		if (defined('T_ENUM')) {
			$ids[] = T_ENUM;
		}
		return in_array($id, $ids, true);
	}

	private static function typeKind(int $id): string
	{
		return $id === T_CLASS ? 'classes' : ($id === T_INTERFACE ? 'interfaces' : ($id === T_TRAIT ? 'traits' : 'enums'));
	}

	private static function nextName(array $tokens, int $index): ?string
	{
		for ($cursor = $index + 1, $count = count($tokens); $cursor < $count; $cursor++) {
			$token = $tokens[$cursor];
			if (is_array($token) && in_array($token[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
				continue;
			}
			if ($token === '&' || (is_array($token) && str_starts_with(token_name($token[0]), 'T_AMPERSAND'))) {
				continue;
			}
			return is_array($token) && $token[0] === T_STRING ? $token[1] : null;
		}
		return null;
	}

	private static function previous(array $tokens, int $index)
	{
		for ($cursor = $index - 1; $cursor >= 0; $cursor--) {
			$token = $tokens[$cursor];
			if (is_array($token) && in_array($token[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
				continue;
			}
			return $token;
		}
		return null;
	}

	private static function nextSignificant(array $tokens, int $index)
	{
		for ($cursor = $index + 1, $count = count($tokens); $cursor < $count; $cursor++) {
			$token = $tokens[$cursor];
			if (is_array($token) && in_array($token[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
				continue;
			}
			return $token;
		}
		return null;
	}

	private static function namespaceName(array $tokens, int $index): string
	{
		$name = '';
		$ids = self::callTokens();
		$ids[] = T_NS_SEPARATOR;
		for ($cursor = $index + 1, $count = count($tokens); $cursor < $count; $cursor++) {
			$token = $tokens[$cursor];
			if ($token === ';' || $token === '{') {
				break;
			}
			if (is_array($token) && in_array($token[0], $ids, true)) {
				$name .= $token[1];
			}
		}
		return $name;
	}

	private static function constNames(array $tokens, int $index): array
	{
		$names = array();
		$expect = true;
		for ($cursor = $index + 1, $count = count($tokens); $cursor < $count; $cursor++) {
			$token = $tokens[$cursor];
			if ($token === ';') {
				break;
			}
			if ($token === ',') {
				$expect = true;
			} elseif ($expect && is_array($token) && $token[0] === T_STRING) {
				$names[] = $token[1];
				$expect = false;
			}
		}
		return $names;
	}

	private static function firstStringArgument(array $tokens, int $index): ?string
	{
		$cursor = $index + 1;
		$count = count($tokens);
		while ($cursor < $count && is_array($tokens[$cursor]) && in_array($tokens[$cursor][0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
			$cursor++;
		}
		if (($tokens[$cursor] ?? null) !== '(') {
			return null;
		}
		$cursor++;
		while ($cursor < $count && is_array($tokens[$cursor]) && in_array($tokens[$cursor][0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
			$cursor++;
		}
		$token = $tokens[$cursor] ?? null;
		return is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING ? self::literal($token[1]) : null;
	}

	private static function arrayStringKey(array $tokens, int $index): ?string
	{
		$cursor = $index + 1;
		$count = count($tokens);
		while ($cursor < $count && is_array($tokens[$cursor]) && in_array($tokens[$cursor][0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
			$cursor++;
		}
		if (($tokens[$cursor] ?? null) !== '[') {
			return null;
		}
		$cursor++;
		while ($cursor < $count && is_array($tokens[$cursor]) && in_array($tokens[$cursor][0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
			$cursor++;
		}
		$token = $tokens[$cursor] ?? null;
		return is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING ? self::literal($token[1]) : null;
	}

	private static function literal(string $literal): string
	{
		if (strlen($literal) < 2) {
			return $literal;
		}
		$value = substr($literal, 1, -1);
		return $literal[0] === "'" ? str_replace(array("\\\\", "\\'"), array("\\", "'"), $value) : stripcslashes($value);
	}

	private static function callTokens(): array
	{
		$ids = array(T_STRING);
		foreach (array('T_NAME_QUALIFIED', 'T_NAME_FULLY_QUALIFIED', 'T_NAME_RELATIVE') as $name) {
			if (defined($name)) {
				$ids[] = constant($name);
			}
		}
		return $ids;
	}

	private static function nearestCall(array $stack): ?array
	{
		for ($index = count($stack) - 1; $index >= 0; $index--) {
			if ($stack[$index]['name'] !== null) {
				return $stack[$index];
			}
		}
		return null;
	}

	private static function pluginConstant(string $name): bool
	{
		return str_starts_with($name, 'VMS_') || str_starts_with($name, 'BVMGR_');
	}

	private static function ownedLowerPrefix(string $name): bool
	{
		return str_starts_with($name, 'vms_') || str_starts_with($name, 'bvmgr_');
	}

	private static function shortPrefix(string $name): bool
	{
		return preg_match('/^[A-Za-z][A-Za-z0-9]{1,2}_/', $name) === 1;
	}
}
