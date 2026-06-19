<?php
declare(strict_types=1);

final class VMS_Release_Compatibility_Tooling
{
	private const DEPENDENCIES = array(
		'woocommerce' => array(
			'label' => 'WooCommerce',
			'directory' => 'woocommerce',
			'main_file' => 'woocommerce.php',
			'optional' => false,
		),
		'the-events-calendar' => array(
			'label' => 'The Events Calendar',
			'directory' => 'the-events-calendar',
			'main_file' => 'the-events-calendar.php',
			'optional' => false,
		),
		'event-tickets' => array(
			'label' => 'Event Tickets',
			'directory' => 'event-tickets',
			'main_file' => 'event-tickets.php',
			'optional' => false,
		),
		'event-tickets-plus' => array(
			'label' => 'Event Tickets Plus',
			'directory' => 'event-tickets-plus',
			'main_file' => 'event-tickets-plus.php',
			'optional' => true,
		),
	);

	private const MATRIX_SCENARIOS = array(
		array(
			'id' => 'scenario-a-vms-only',
			'label' => 'A. WordPress with VMS only',
			'present_dependencies' => array(),
			'activate_before_vms' => array(),
			'activate_after_vms' => array(),
			'deactivate_after_vms' => array(),
		),
		array(
			'id' => 'scenario-b-woocommerce-only',
			'label' => 'B. VMS plus WooCommerce only',
			'present_dependencies' => array('woocommerce'),
			'activate_before_vms' => array('woocommerce'),
			'activate_after_vms' => array(),
			'deactivate_after_vms' => array(),
		),
		array(
			'id' => 'scenario-c-tec-only',
			'label' => 'C. VMS plus The Events Calendar only',
			'present_dependencies' => array('the-events-calendar'),
			'activate_before_vms' => array('the-events-calendar'),
			'activate_after_vms' => array(),
			'deactivate_after_vms' => array(),
		),
		array(
			'id' => 'scenario-d-supported-stack',
			'label' => 'D. VMS plus normal supported event/ticketing dependency set',
			'present_dependencies' => array('woocommerce', 'the-events-calendar', 'event-tickets', 'event-tickets-plus'),
			'activate_before_vms' => array('woocommerce', 'the-events-calendar', 'event-tickets', 'event-tickets-plus'),
			'activate_after_vms' => array(),
			'deactivate_after_vms' => array(),
		),
		array(
			'id' => 'scenario-e-present-inactive',
			'label' => 'E. Required plugins present but inactive',
			'present_dependencies' => array('woocommerce', 'the-events-calendar', 'event-tickets', 'event-tickets-plus'),
			'activate_before_vms' => array(),
			'activate_after_vms' => array(),
			'deactivate_after_vms' => array(),
		),
		array(
			'id' => 'scenario-f-activate-after-vms',
			'label' => 'F. Required plugins activated after VMS',
			'present_dependencies' => array('woocommerce', 'the-events-calendar', 'event-tickets', 'event-tickets-plus'),
			'activate_before_vms' => array(),
			'activate_after_vms' => array('woocommerce', 'the-events-calendar', 'event-tickets', 'event-tickets-plus'),
			'deactivate_after_vms' => array(),
		),
		array(
			'id' => 'scenario-g-optional-removed',
			'label' => 'G. Optional plugins deactivated while VMS remains active',
			'present_dependencies' => array('woocommerce', 'the-events-calendar', 'event-tickets', 'event-tickets-plus'),
			'activate_before_vms' => array('woocommerce', 'the-events-calendar', 'event-tickets', 'event-tickets-plus'),
			'activate_after_vms' => array(),
			'deactivate_after_vms' => array('event-tickets-plus'),
		),
	);

	private const MATRIX_SCENARIO_ALIASES = array(
		'a' => 'scenario-a-vms-only',
		'scenario-a' => 'scenario-a-vms-only',
		'vms-only' => 'scenario-a-vms-only',
		'b' => 'scenario-b-woocommerce-only',
		'scenario-b' => 'scenario-b-woocommerce-only',
		'woo-only' => 'scenario-b-woocommerce-only',
		'woocommerce-only' => 'scenario-b-woocommerce-only',
		'c' => 'scenario-c-tec-only',
		'scenario-c' => 'scenario-c-tec-only',
		'tec-only' => 'scenario-c-tec-only',
		'd' => 'scenario-d-supported-stack',
		'scenario-d' => 'scenario-d-supported-stack',
		'supported-stack' => 'scenario-d-supported-stack',
		'e' => 'scenario-e-present-inactive',
		'scenario-e' => 'scenario-e-present-inactive',
		'present-inactive' => 'scenario-e-present-inactive',
		'inactive-required' => 'scenario-e-present-inactive',
		'f' => 'scenario-f-activate-after-vms',
		'scenario-f' => 'scenario-f-activate-after-vms',
		'activate-after-vms' => 'scenario-f-activate-after-vms',
		'g' => 'scenario-g-optional-removed',
		'scenario-g' => 'scenario-g-optional-removed',
		'optional-removed' => 'scenario-g-optional-removed',
	);

	public const FIXTURE_OPTION = 'vms_compat_upgrade_fixture_manifest';

	public static function run(array $config): array
	{
		$config = self::normalizeConfig($config);
		$selectedMatrixScenarios = self::resolveMatrixScenarioSelection($config['scenario_ids']);
		$artifact = self::validateArtifactInput($config['artifact_path'], $config['expected_sha256']);
		$baselineArtifact = self::resolveBaselineArtifact(
			$config['baseline_artifact_path'],
			$config['plugin_root'],
			$config['plugins_workspace_root']
		);
		$environment = self::collectEnvironment($config);
		$runtimeReview = self::reviewRuntimeChanges($config['plugin_root'], $baselineArtifact['path']);

		$report = array(
			'status' => 'PASS',
			'generated_at_utc' => gmdate('c'),
			'plugin_root' => $config['plugin_root'],
			'artifact' => $artifact,
			'baseline_artifact' => $baselineArtifact,
			'environment' => $environment,
			'runtime_change_review' => $runtimeReview,
			'scenarios' => array(),
			'clean_install_lifecycle' => array('status' => 'SKIP'),
			'upgrade' => array('status' => 'SKIP'),
			'migration_interruption' => array('status' => 'SKIP'),
			'uninstall' => array('status' => 'SKIP'),
			'proposed_compatibility' => self::proposeCompatibilityMetadata($environment),
			'remaining_browser_qa' => 'Browser QA is still outstanding. This harness covers WP-CLI, authenticated wp-admin requests, and public PHP-request smoke tests only.',
			'command' => $config['invocation'],
			'harness' => array(
				'selected_matrix_scenarios' => array_values(array_map(static function (array $scenario): string {
					return (string) $scenario['id'];
				}, $selectedMatrixScenarios)),
				'php_memory_limit' => $config['php_memory_limit'],
				'wp_cli_timeout_seconds' => $config['wp_cli_timeout_seconds'],
			),
		);

		try {
			if ($config['run_matrix']) {
				foreach ($selectedMatrixScenarios as $scenario) {
					$report['scenarios'][] = self::runMatrixScenario($scenario, $config, $artifact, $environment);
				}
			}

			if ($config['run_clean_install_lifecycle']) {
				$report['clean_install_lifecycle'] = self::runCleanLifecycleScenario($config, $artifact, $environment);
			} else {
				$report['clean_install_lifecycle'] = array('status' => 'SKIP', 'summary' => 'Skipped by CLI selection.');
			}
			if ($config['run_upgrade']) {
				$report['upgrade'] = self::runUpgradeScenario($config, $artifact, $baselineArtifact, $environment);
			} else {
				$report['upgrade'] = array('status' => 'SKIP', 'summary' => 'Skipped by CLI selection.');
			}
			if ($config['run_migration_interruption']) {
				$report['migration_interruption'] = self::runMigrationInterruptionScenario($config, $artifact, $environment);
			} else {
				$report['migration_interruption'] = array('status' => 'SKIP', 'summary' => 'Skipped by CLI selection.');
			}
			if ($config['run_uninstall']) {
				$report['uninstall'] = self::runUninstallScenario($config, $artifact, $environment);
			} else {
				$report['uninstall'] = array('status' => 'SKIP', 'summary' => 'Skipped by CLI selection.');
			}
			$report['status'] = self::computeOverallStatus($report);
		} catch (Throwable $throwable) {
			$report['status'] = 'FAIL';
			$report['exception'] = array(
				'message' => self::redactText($throwable->getMessage(), array($config['plugin_root'], $environment['wordpress_source_root'])),
			);
		}

		$report['finished_at_utc'] = gmdate('c');
		unset($report['environment']['site_db_config']);
		self::writeReportFiles($config, $report);

		return $report;
	}

	public static function validateArtifactInput(string $artifactPath, ?string $expectedSha256 = null): array
	{
		$artifactPath = trim($artifactPath);
		if ($artifactPath === '' || !is_file($artifactPath)) {
			throw new RuntimeException('Artifact ZIP not found: ' . $artifactPath);
		}

		$zipRoot = self::inspectZipRoot($artifactPath);
		$sha256 = hash_file('sha256', $artifactPath);
		if (!is_string($sha256) || $sha256 === '') {
			throw new RuntimeException('Could not calculate artifact SHA-256.');
		}

		$checksumMatches = null;
		if ($expectedSha256 !== null && $expectedSha256 !== '') {
			$checksumMatches = hash_equals(strtolower($expectedSha256), strtolower($sha256));
			if (!$checksumMatches) {
				throw new RuntimeException('Artifact checksum does not match the expected SHA-256.');
			}
		}

		return array(
			'path' => $artifactPath,
			'filename' => basename($artifactPath),
			'sha256' => $sha256,
			'expected_sha256' => $expectedSha256,
			'checksum_matches_expected' => $checksumMatches,
			'size_bytes' => filesize($artifactPath) ?: 0,
			'root_directory' => $zipRoot['root_directory'],
			'version' => self::extractArtifactVersion($artifactPath),
		);
	}

	public static function inspectZipRoot(string $artifactPath): array
	{
		$zip = new ZipArchive();
		$opened = $zip->open($artifactPath);
		if ($opened !== true) {
			throw new RuntimeException('Could not open artifact ZIP: ' . basename($artifactPath));
		}

		$roots = array();
		for ($index = 0; $index < $zip->numFiles; $index++) {
			$entryName = (string) $zip->getNameIndex($index);
			if ($entryName === '') {
				continue;
			}
			$entryName = ltrim($entryName, '/');
			$parts = explode('/', $entryName);
			$root = $parts[0] ?? '';
			if ($root !== '') {
				$roots[$root] = true;
			}
		}
		$zip->close();

		$rootNames = array_values(array_keys($roots));
		sort($rootNames, SORT_STRING);
		if (count($rootNames) !== 1 || $rootNames[0] !== 'vms') {
			throw new RuntimeException('Artifact ZIP must contain exactly one top-level vms/ root directory.');
		}

		return array(
			'root_directory' => 'vms',
		);
	}

	public static function compareCronSnapshots(array $before, array $after): array
	{
		$beforeHooks = is_array($before['owned_hooks'] ?? null) ? (array) $before['owned_hooks'] : array();
		$afterHooks = is_array($after['owned_hooks'] ?? null) ? (array) $after['owned_hooks'] : array();
		$comparison = self::compareHookCountMaps($beforeHooks, $afterHooks);

		return array(
			'differences' => $comparison['differences'],
			'duplicate_hooks' => $comparison['duplicate_hooks'],
			'stable' => $comparison['stable'],
		);
	}

	public static function compareScheduledWorkSnapshots(array $beforeState, array $afterState): array
	{
		$cron = self::compareCronSnapshots(
			(array) ($beforeState['cron'] ?? array()),
			(array) ($afterState['cron'] ?? array())
		);
		$actionSchedulerBefore = (array) (($beforeState['action_scheduler'] ?? array())['owned_hooks'] ?? array());
		$actionSchedulerAfter = (array) (($afterState['action_scheduler'] ?? array())['owned_hooks'] ?? array());
		$actionSchedulerComparison = self::compareHookCountMaps($actionSchedulerBefore, $actionSchedulerAfter);

		return array(
			'cron' => $cron,
			'action_scheduler' => array(
				'differences' => $actionSchedulerComparison['differences'],
				'duplicate_hooks' => $actionSchedulerComparison['duplicate_hooks'],
				'stable' => $actionSchedulerComparison['stable'],
			),
			'stable' => $cron['stable'] && $actionSchedulerComparison['stable'],
		);
	}

	public static function compareFixturePreservation(array $beforeFixture, array $afterFixture): array
	{
		$beforeStatus = is_array($beforeFixture['checks']['status'] ?? null) ? (array) $beforeFixture['checks']['status'] : array();
		$afterStatus = is_array($afterFixture['checks']['status'] ?? null) ? (array) $afterFixture['checks']['status'] : array();
		$keys = array_values(array_unique(array_merge(array_keys($beforeStatus), array_keys($afterStatus))));
		$volatileKeys = array('scheduled_hooks_present');
		sort($keys, SORT_STRING);

		$regressions = array();
		foreach ($keys as $key) {
			if (in_array($key, $volatileKeys, true)) {
				continue;
			}
			$beforeValue = (bool) ($beforeStatus[$key] ?? false);
			$afterValue = (bool) ($afterStatus[$key] ?? false);
			if ($beforeValue && !$afterValue) {
				$regressions[$key] = array(
					'before' => $beforeValue,
					'after' => $afterValue,
				);
			}
		}

		return array(
			'regressions' => $regressions,
			'preserved' => $regressions === array() && !empty($afterFixture['preserved']),
		);
	}

	public static function generateTextReport(array $report): string
	{
		$lines = array();
		$lines[] = 'VMS Release Compatibility Report';
		$lines[] = '';
		$lines[] = 'Overall status: ' . (string) ($report['status'] ?? 'UNKNOWN');
		$lines[] = 'Artifact: ' . (string) ($report['artifact']['filename'] ?? '');
		$lines[] = 'SHA-256: ' . (string) ($report['artifact']['sha256'] ?? '');
		$lines[] = 'Baseline artifact: ' . (string) ($report['baseline_artifact']['filename'] ?? 'missing');
		$lines[] = 'WordPress tested: ' . (string) ($report['environment']['wordpress_version'] ?? '');
		$lines[] = 'PHP tested: ' . (string) ($report['environment']['php_version'] ?? '');
		$lines[] = '';
		$lines[] = 'Dependency matrix:';
		foreach ((array) ($report['scenarios'] ?? array()) as $scenario) {
			$lines[] = sprintf(
				'- [%s] %s',
				(string) ($scenario['status'] ?? 'UNKNOWN'),
				(string) ($scenario['label'] ?? $scenario['id'] ?? 'scenario')
			);
		}
		$lines[] = '';
		foreach (array('clean_install_lifecycle' => 'Clean install lifecycle', 'upgrade' => 'Upgrade', 'migration_interruption' => 'Migration interruption', 'uninstall' => 'Uninstall') as $key => $label) {
			$entry = (array) ($report[$key] ?? array());
			$lines[] = sprintf('%s: [%s] %s', $label, (string) ($entry['status'] ?? 'UNKNOWN'), (string) ($entry['summary'] ?? ''));
		}
		$lines[] = '';
		$lines[] = 'Proposed compatibility metadata:';
		foreach ((array) ($report['proposed_compatibility'] ?? array()) as $key => $value) {
			if (is_scalar($value) || $value === null) {
				$lines[] = sprintf('- %s: %s', (string) $key, (string) $value);
			}
		}
		$lines[] = '';
		$lines[] = 'Remaining browser QA:';
		$lines[] = (string) ($report['remaining_browser_qa'] ?? '');

		return implode("\n", $lines) . "\n";
	}

	private static function normalizeConfig(array $config): array
	{
		$pluginRoot = isset($config['plugin_root']) ? (string) $config['plugin_root'] : dirname(__DIR__, 2);
		$workingDir = isset($config['working_dir']) ? (string) $config['working_dir'] : ((string) getcwd() ?: $pluginRoot);
		$outputDir = isset($config['output_dir']) ? (string) $config['output_dir'] : ($pluginRoot . DIRECTORY_SEPARATOR . 'test-results');
		$artifactPath = isset($config['artifact_path']) ? (string) $config['artifact_path'] : '';
		if ($artifactPath === '') {
			throw new RuntimeException('Missing required --artifact path.');
		}
		$artifactPath = self::resolveFilesystemPath($artifactPath, $workingDir);
		$outputDir = self::resolveFilesystemPath($outputDir, $workingDir, false);
		$baselineArtifactPath = isset($config['baseline_artifact_path']) ? (string) $config['baseline_artifact_path'] : '';
		if ($baselineArtifactPath !== '') {
			$baselineArtifactPath = self::resolveFilesystemPath($baselineArtifactPath, $workingDir, false);
		}
		$wordpressSourceRoot = isset($config['wordpress_source_root']) ? (string) $config['wordpress_source_root'] : '';
		if ($wordpressSourceRoot !== '') {
			$wordpressSourceRoot = self::resolveFilesystemPath($wordpressSourceRoot, $workingDir, false);
		}
		$scenarioIds = isset($config['scenario_ids']) && is_array($config['scenario_ids']) ? $config['scenario_ids'] : array();
		$phpMemoryLimit = trim((string) ($config['php_memory_limit'] ?? '512M'));
		if ($phpMemoryLimit === '') {
			$phpMemoryLimit = '512M';
		}
		$wpCliTimeoutSeconds = (int) ($config['wp_cli_timeout_seconds'] ?? 180);
		if ($wpCliTimeoutSeconds < 30) {
			$wpCliTimeoutSeconds = 30;
		}

		return array(
			'plugin_root' => $pluginRoot,
			'plugins_workspace_root' => dirname($pluginRoot),
			'working_dir' => $workingDir,
			'output_dir' => $outputDir,
			'artifact_path' => $artifactPath,
			'expected_sha256' => isset($config['expected_sha256']) ? (string) $config['expected_sha256'] : null,
			'baseline_artifact_path' => $baselineArtifactPath,
			'wordpress_source_root' => $wordpressSourceRoot,
			'force' => !empty($config['force']),
			'keep_failed_workspaces' => !empty($config['keep_failed_workspaces']),
			'scenario_ids' => array_values(array_map('strval', $scenarioIds)),
			'run_matrix' => !array_key_exists('run_matrix', $config) || !empty($config['run_matrix']),
			'run_clean_install_lifecycle' => !array_key_exists('run_clean_install_lifecycle', $config) || !empty($config['run_clean_install_lifecycle']),
			'run_upgrade' => !array_key_exists('run_upgrade', $config) || !empty($config['run_upgrade']),
			'run_migration_interruption' => !array_key_exists('run_migration_interruption', $config) || !empty($config['run_migration_interruption']),
			'run_uninstall' => !array_key_exists('run_uninstall', $config) || !empty($config['run_uninstall']),
			'php_memory_limit' => $phpMemoryLimit,
			'wp_cli_timeout_seconds' => $wpCliTimeoutSeconds,
			'invocation' => isset($config['invocation']) ? (string) $config['invocation'] : '',
		);
	}

	public static function resolveMatrixScenarioSelection(array $requestedIds = array()): array
	{
		if ($requestedIds === array()) {
			return self::MATRIX_SCENARIOS;
		}

		$selectedIds = array();
		foreach ($requestedIds as $requestedId) {
			$normalized = strtolower(trim((string) $requestedId));
			if ($normalized === '') {
				continue;
			}
			$canonical = self::MATRIX_SCENARIO_ALIASES[$normalized] ?? $normalized;
			if (!preg_match('/^scenario-[a-g]-/', $canonical)) {
				throw new RuntimeException('Unknown matrix scenario selection: ' . $requestedId);
			}
			$selectedIds[$canonical] = true;
		}

		$selected = array();
		foreach (self::MATRIX_SCENARIOS as $scenario) {
			if (!empty($selectedIds[$scenario['id']])) {
				$selected[] = $scenario;
			}
		}
		if ($selected === array()) {
			throw new RuntimeException('No matching matrix scenarios were selected.');
		}

		return $selected;
	}

	private static function resolveBaselineArtifact(string $requestedPath, string $pluginRoot, string $pluginsWorkspaceRoot): array
	{
		$requestedPath = trim($requestedPath);
		if ($requestedPath !== '') {
			if (!is_file($requestedPath)) {
				return array(
					'path' => $requestedPath,
					'filename' => basename($requestedPath),
					'available' => false,
					'status' => 'BLOCKED',
				);
			}

			return array(
				'path' => $requestedPath,
				'filename' => basename($requestedPath),
				'available' => true,
				'status' => 'PASS',
			);
		}

		$candidates = array();
		foreach (array(
			$pluginsWorkspaceRoot . DIRECTORY_SEPARATOR . 'vms-0.2.24.725*.zip',
			$pluginRoot . DIRECTORY_SEPARATOR . 'dist' . DIRECTORY_SEPARATOR . 'vms-0.2.24.725*.zip',
		) as $pattern) {
			foreach ((array) glob($pattern) as $match) {
				if (is_string($match) && is_file($match)) {
					$candidates[] = $match;
				}
			}
		}
		if ($candidates !== array()) {
			usort($candidates, static function (string $left, string $right): int {
				return filemtime($right) <=> filemtime($left);
			});
			$resolved = (string) $candidates[0];
			return array(
				'path' => $resolved,
				'filename' => basename($resolved),
				'available' => true,
				'status' => 'PASS',
			);
		}

		return array(
			'path' => '',
			'filename' => '',
			'available' => false,
			'status' => 'BLOCKED',
		);
	}

	private static function collectEnvironment(array $config): array
	{
		$wordpressSourceRoot = self::discoverWordPressSourceRoot($config['wordpress_source_root'], $config['plugin_root']);
		$wpConfig = self::parseWpConfigFile($wordpressSourceRoot . DIRECTORY_SEPARATOR . 'wp-config.php', $wordpressSourceRoot);
		$dbInfo = self::inspectDatabaseServer($wpConfig);
		$dependencies = self::discoverDependencySources($config['plugins_workspace_root']);

		return array(
			'wordpress_source_root' => $wordpressSourceRoot,
			'wordpress_version' => self::parseWordPressVersion($wordpressSourceRoot),
			'php_version' => PHP_VERSION,
			'db' => $dbInfo,
			'wp_config' => array(
				'DB_NAME' => (string) ($wpConfig['DB_NAME'] ?? ''),
				'DB_USER' => (string) ($wpConfig['DB_USER'] ?? ''),
				'DB_HOST' => (string) ($wpConfig['DB_HOST'] ?? ''),
				'table_prefix' => (string) ($wpConfig['table_prefix'] ?? ''),
				'db_password_present' => !empty($wpConfig['DB_PASSWORD']),
			),
			'site_db_config' => $wpConfig,
			'dependencies' => $dependencies,
		);
	}

	private static function discoverWordPressSourceRoot(string $requestedRoot, string $pluginRoot): string
	{
		$requestedRoot = trim($requestedRoot);
		if ($requestedRoot !== '') {
			if (!is_file($requestedRoot . DIRECTORY_SEPARATOR . 'wp-load.php')) {
				throw new RuntimeException('WordPress source root does not contain wp-load.php: ' . $requestedRoot);
			}
			return $requestedRoot;
		}

		$candidate = $pluginRoot;
		for ($depth = 0; $depth < 10; $depth++) {
			if (is_file($candidate . DIRECTORY_SEPARATOR . 'wp-load.php')) {
				return $candidate;
			}
			$parent = dirname($candidate);
			if ($parent === $candidate) {
				break;
			}
			$candidate = $parent;
		}

		throw new RuntimeException('Could not auto-discover a WordPress source root. Use --wordpress-source.');
	}

	private static function resolveFilesystemPath(string $path, string $baseDir, bool $preferRealpath = true): string
	{
		if ($path === '') {
			return '';
		}
		if (preg_match('#^(?:/|[A-Za-z]:[\\\\/])#', $path) === 1) {
			$resolved = $preferRealpath ? realpath($path) : false;
			return is_string($resolved) && $resolved !== '' ? $resolved : $path;
		}

		$combined = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $path;
		$resolved = $preferRealpath ? realpath($combined) : false;
		return is_string($resolved) && $resolved !== '' ? $resolved : $combined;
	}

	private static function parseWpConfigFile(string $configPath, string $wordpressSourceRoot = ''): array
	{
		if (!is_file($configPath)) {
			throw new RuntimeException('Could not find wp-config.php: ' . $configPath);
		}

		$contents = (string) file_get_contents($configPath);
		$extractDefine = static function (string $constantName) use ($contents): string {
			$pattern = sprintf('/define\s*\(\s*[\'"]%s[\'"]\s*,\s*([\'"])(.*?)\1\s*\)\s*;/s', preg_quote($constantName, '/'));
			if (!preg_match($pattern, $contents, $matches)) {
				return '';
			}
			return stripcslashes((string) ($matches[2] ?? ''));
		};

		$tablePrefix = '';
		if (preg_match('/\$table_prefix\s*=\s*([\'"])(.*?)\1\s*;/s', $contents, $matches)) {
			$tablePrefix = stripcslashes((string) ($matches[2] ?? ''));
		}

		$dbHost = $extractDefine('DB_HOST');
		if ($dbHost === '') {
			$dbHost = self::discoverLocalAppDatabaseHost($wordpressSourceRoot);
		}

		return array(
			'DB_NAME' => $extractDefine('DB_NAME'),
			'DB_USER' => $extractDefine('DB_USER'),
			'DB_PASSWORD' => $extractDefine('DB_PASSWORD'),
			'DB_HOST' => $dbHost,
			'table_prefix' => $tablePrefix !== '' ? $tablePrefix : 'wp_',
		);
	}

	private static function discoverLocalAppDatabaseHost(string $wordpressSourceRoot): string
	{
		$home = getenv('HOME');
		if (!is_string($home) || $home === '') {
			return '';
		}

		$siteRoot = realpath(dirname(dirname($wordpressSourceRoot)));
		if (!is_string($siteRoot) || $siteRoot === '') {
			return '';
		}

		$sitesPath = $home . '/Library/Application Support/Local/sites.json';
		if (!is_readable($sitesPath)) {
			return '';
		}

		$raw = file_get_contents($sitesPath);
		$sites = is_string($raw) ? json_decode($raw, true) : null;
		if (!is_array($sites)) {
			return '';
		}

		foreach ($sites as $siteId => $site) {
			if (!is_array($site)) {
				continue;
			}
			$path = isset($site['path']) ? (string) $site['path'] : '';
			if ($path === '') {
				continue;
			}
			if (strpos($path, '~/') === 0) {
				$path = $home . substr($path, 1);
			}
			$realPath = realpath($path);
			if (!is_string($realPath) || $realPath !== $siteRoot) {
				continue;
			}

			$socket = $home . '/Library/Application Support/Local/run/' . $siteId . '/mysql/mysqld.sock';
			if (file_exists($socket)) {
				return 'localhost:' . $socket;
			}
		}

		return '';
	}

	private static function parseWordPressVersion(string $wordpressSourceRoot): string
	{
		$versionFile = $wordpressSourceRoot . DIRECTORY_SEPARATOR . 'wp-includes' . DIRECTORY_SEPARATOR . 'version.php';
		if (!is_file($versionFile)) {
			return '';
		}
		$contents = (string) file_get_contents($versionFile);
		if (!preg_match('/\$wp_version\s*=\s*[\'"]([^\'"]+)[\'"]\s*;/', $contents, $matches)) {
			return '';
		}
		return (string) ($matches[1] ?? '');
	}

	private static function inspectDatabaseServer(array $wpConfig): array
	{
		$hostInfo = self::parseDatabaseHost((string) ($wpConfig['DB_HOST'] ?? ''));
		$mysqli = mysqli_init();
		if (!$mysqli instanceof mysqli) {
			return array(
				'engine' => '',
				'version' => '',
			);
		}
		@$mysqli->real_connect(
			$hostInfo['host'],
			(string) ($wpConfig['DB_USER'] ?? ''),
			(string) ($wpConfig['DB_PASSWORD'] ?? ''),
			null,
			$hostInfo['port'],
			$hostInfo['socket']
		);
		if ($mysqli->connect_errno) {
			return array(
				'engine' => '',
				'version' => '',
			);
		}

		$version = $mysqli->server_info;
		$engine = '';
		$result = $mysqli->query("SHOW VARIABLES LIKE 'version_comment'");
		if ($result instanceof mysqli_result) {
			$row = $result->fetch_assoc();
			$engine = is_array($row) ? (string) ($row['Value'] ?? '') : '';
			$result->free();
		}
		$mysqli->close();

		return array(
			'engine' => $engine,
			'version' => $version,
			'host_mode' => $hostInfo['socket'] !== '' ? 'socket' : 'tcp',
		);
	}

	private static function discoverDependencySources(string $pluginsWorkspaceRoot): array
	{
		$dependencies = array();
		foreach (self::DEPENDENCIES as $slug => $definition) {
			$pluginDir = $pluginsWorkspaceRoot . DIRECTORY_SEPARATOR . (string) $definition['directory'];
			$mainFile = $pluginDir . DIRECTORY_SEPARATOR . (string) $definition['main_file'];
			$available = is_file($mainFile);
			$metadata = $available ? self::readPluginHeaderMetadata($mainFile) : array();
			$dependencies[$slug] = array(
				'label' => (string) $definition['label'],
				'slug' => $slug,
				'path' => $pluginDir,
				'main_file' => $mainFile,
				'available' => $available,
				'optional' => !empty($definition['optional']),
				'version' => (string) ($metadata['Version'] ?? ''),
				'requires_at_least' => (string) ($metadata['Requires at least'] ?? ''),
				'requires_php' => (string) ($metadata['Requires PHP'] ?? ''),
			);
		}

		return $dependencies;
	}

	private static function readPluginHeaderMetadata(string $mainFile): array
	{
		$contents = (string) file_get_contents($mainFile);
		$headers = array();
		foreach (array('Plugin Name', 'Version', 'Requires at least', 'Requires PHP', 'Requires Plugins', 'WC requires at least', 'WC tested up to') as $headerName) {
			if (preg_match('/^\s*\*\s+' . preg_quote($headerName, '/') . ':\s*(.+)$/mi', $contents, $matches)) {
				$headers[$headerName] = trim((string) ($matches[1] ?? ''));
			}
		}
		return $headers;
	}

	private static function reviewRuntimeChanges(string $pluginRoot, string $baselineArtifactPath): array
	{
		$baselineUninstall = $baselineArtifactPath !== '' && is_file($baselineArtifactPath)
			? self::readZipEntry($baselineArtifactPath, 'vms/uninstall.php')
			: '';
		$baselineVendorTech = $baselineArtifactPath !== '' && is_file($baselineArtifactPath)
			? self::readZipEntry($baselineArtifactPath, 'vms/includes/portal/vendor-tech-profile.php')
			: '';

		return array(
			'uninstall.php' => array(
				'baseline_size_bytes' => strlen($baselineUninstall),
				'current_size_bytes' => filesize($pluginRoot . '/uninstall.php') ?: 0,
				'explanation' => 'The previous public-release validation rejected zero-byte runtime PHP files. Replacing an empty uninstall stub with a direct-context guard keeps uninstall code inert unless WordPress defines WP_UNINSTALL_PLUGIN.',
				'belongs_in_release_engineering_changeset' => true,
				'preserves_existing_behavior' => true,
				'decision' => 'keep',
				'focused_test' => 'php tests/runtime-stub-guards.php',
			),
			'includes/portal/vendor-tech-profile.php' => array(
				'baseline_size_bytes' => strlen($baselineVendorTech),
				'current_size_bytes' => filesize($pluginRoot . '/includes/portal/vendor-tech-profile.php') ?: 0,
				'explanation' => 'This file was also zero-byte in the baseline package. The current guarded stub keeps direct access inert and keeps the package validator from treating the file as an accidental empty runtime artifact.',
				'belongs_in_release_engineering_changeset' => true,
				'preserves_existing_behavior' => true,
				'decision' => 'keep',
				'focused_test' => 'php tests/runtime-stub-guards.php',
			),
		);
	}

	private static function runMatrixScenario(array $scenario, array $config, array $artifact, array $environment): array
	{
		$site = self::createSiteHarness($scenario['id'], $config, $environment);
		$keepWorkspace = false;
		try {
			$missingDependencies = array();
			foreach ((array) $scenario['present_dependencies'] as $dependencySlug) {
				if (empty($environment['dependencies'][$dependencySlug]['available'])) {
					$missingDependencies[] = $dependencySlug;
				}
			}
			if ($missingDependencies !== array()) {
				return array(
					'id' => $scenario['id'],
					'label' => $scenario['label'],
					'status' => 'SKIP',
					'summary' => 'Dependency sources are unavailable in the local plugins workspace.',
					'missing_dependencies' => $missingDependencies,
				);
			}

			$site->provision();
			$site->copyDependencies((array) $scenario['present_dependencies'], (array) $environment['dependencies']);
			$site->installArtifact($artifact['path']);
			if (!empty($scenario['activate_before_vms'])) {
				$site->activatePlugins((array) $scenario['activate_before_vms']);
			}
			$beforeState = $site->collectState(self::FIXTURE_OPTION);
			$site->activatePlugins(array('vms'));
			if (!empty($scenario['activate_after_vms'])) {
				$site->activatePlugins((array) $scenario['activate_after_vms']);
			}
			if (!empty($scenario['deactivate_after_vms'])) {
				$site->deactivatePlugins((array) $scenario['deactivate_after_vms']);
			}

			$site->startServer();
			$login = $site->loginAdmin();
			$adminRequest = $site->requestAdminEventPlanList();
			$publicRequest = $site->requestPrimaryPublicPage();
			$healthState = $site->collectState(self::FIXTURE_OPTION);
			$pluginList = $site->pluginList();
			$logSummary = $site->readLogSummary();
			$scheduledWork = self::compareScheduledWorkSnapshots($beforeState, $healthState);
			$scheduledWorkHasDuplicates = !empty($scheduledWork['cron']['duplicate_hooks']) || !empty($scheduledWork['action_scheduler']['duplicate_hooks']);
			$loginProbeFailed = empty($login['ok']);
			$adminSmokeFailed = empty($adminRequest['ok']);
			$publicSmokeFailed = empty($publicRequest['ok']);

			$status = 'PASS';
			$summaryParts = array('Activation and smoke requests completed without a VMS fatal.');
			if ($adminSmokeFailed || $publicSmokeFailed) {
				$status = 'FAIL';
				$summaryParts[] = 'One or more authenticated/public smoke requests failed.';
			} elseif ($loginProbeFailed) {
				$status = self::mergeStatus($status, 'WARN');
				$summaryParts[] = 'The direct login probe did not confirm the session, but downstream admin/public smoke requests succeeded.';
			}
			if (!empty($logSummary['fatal_count'])) {
				$status = 'FAIL';
				$summaryParts[] = 'Fatal entries were written to the disposable logs.';
			} elseif (!empty($logSummary['warning_count']) || !empty($logSummary['deprecated_count'])) {
				$status = ($status === 'FAIL') ? 'FAIL' : 'WARN';
				$summaryParts[] = 'Warnings or deprecations were captured in disposable logs.';
			}
			if ($scheduledWorkHasDuplicates) {
				$status = self::mergeStatus($status, 'WARN');
				$summaryParts[] = 'Cron ownership or Action Scheduler duplicates appeared after activation.';
			} elseif (!$scheduledWork['stable']) {
				$summaryParts[] = 'First activation created scheduled work entries without duplicate ownership.';
			}
			$keepWorkspace = $status === 'FAIL' && !empty($config['keep_failed_workspaces']);

			return array(
				'id' => $scenario['id'],
				'label' => $scenario['label'],
				'status' => $status,
				'summary' => implode(' ', $summaryParts),
				'site' => array(
					'url' => $site->getSiteUrl(),
					'workspace_retained' => $status === 'FAIL' && !empty($config['keep_failed_workspaces']),
					'workspace_path' => $status === 'FAIL' && !empty($config['keep_failed_workspaces']) ? $site->getWorkspaceRoot() : '',
				),
				'dependencies_present' => (array) $scenario['present_dependencies'],
				'activate_before_vms' => (array) $scenario['activate_before_vms'],
				'activate_after_vms' => (array) $scenario['activate_after_vms'],
				'deactivate_after_vms' => (array) $scenario['deactivate_after_vms'],
				'login' => $login,
				'admin_request' => $adminRequest,
				'public_request' => $publicRequest,
				'plugin_list' => $pluginList,
				'log_summary' => $logSummary,
				'scheduled_work_comparison' => $scheduledWork,
				'health' => $healthState,
			);
		} catch (Throwable $throwable) {
			$keepWorkspace = !empty($config['keep_failed_workspaces']);
			return array(
				'id' => $scenario['id'],
				'label' => $scenario['label'],
				'status' => 'FAIL',
				'summary' => 'Scenario execution failed before completion.',
				'exception' => self::redactText($throwable->getMessage(), array($config['plugin_root'], $environment['wordpress_source_root'])),
				'site' => array(
					'workspace_retained' => $keepWorkspace,
					'workspace_path' => $keepWorkspace ? $site->getWorkspaceRoot() : '',
				),
			);
		} finally {
			$site->destroy($keepWorkspace);
		}
	}

	private static function runCleanLifecycleScenario(array $config, array $artifact, array $environment): array
	{
		$scenario = array(
			'id' => 'clean-install-lifecycle',
			'label' => 'Clean install lifecycle',
			'present_dependencies' => array('woocommerce', 'the-events-calendar', 'event-tickets', 'event-tickets-plus'),
			'activate_before_vms' => array('woocommerce', 'the-events-calendar', 'event-tickets', 'event-tickets-plus'),
		);
		$site = self::createSiteHarness($scenario['id'], $config, $environment);
		$keepWorkspace = false;
		try {
			$site->provision();
			$site->copyDependencies((array) $scenario['present_dependencies'], (array) $environment['dependencies']);
			$site->installArtifact($artifact['path']);
			$site->activatePlugins((array) $scenario['activate_before_vms']);
			$beforeActivation = $site->collectState(self::FIXTURE_OPTION);
			$site->activatePlugins(array('vms'));
			$seeded = $site->seedUpgradeFixtures(self::FIXTURE_OPTION);
			$afterActivation = $site->collectState(self::FIXTURE_OPTION);
			$site->deactivatePlugins(array('vms'));
			$afterDeactivation = $site->collectState(self::FIXTURE_OPTION);
			$site->activatePlugins(array('vms'));
			$afterReactivation = $site->collectState(self::FIXTURE_OPTION);
			$secondActivation = $site->activatePlugins(array('vms'));
			$afterSecondActivation = $site->collectState(self::FIXTURE_OPTION);
			$site->startServer();
			$login = $site->loginAdmin();
			$adminRequest = $site->requestAdminEventPlanList();
			$publicRequest = $site->requestPrimaryPublicPage();
			$logSummary = $site->readLogSummary();

			$deactivationFixture = self::compareFixturePreservation((array) ($afterActivation['fixture'] ?? array()), (array) ($afterDeactivation['fixture'] ?? array()));
			$reactivationFixture = self::compareFixturePreservation((array) ($afterActivation['fixture'] ?? array()), (array) ($afterReactivation['fixture'] ?? array()));
			$scheduledWorkAfterReactivation = self::compareScheduledWorkSnapshots($afterActivation, $afterReactivation);
			$scheduledWorkAfterSecondActivation = self::compareScheduledWorkSnapshots($afterReactivation, $afterSecondActivation);
			$loginProbeFailed = empty($login['ok']);
			$adminSmokeFailed = empty($adminRequest['ok']);
			$publicSmokeFailed = empty($publicRequest['ok']);

			$status = 'PASS';
			$summaryParts = array('Activation, deactivation, and reactivation completed on a disposable site.');
			if (!$deactivationFixture['preserved'] || !$reactivationFixture['preserved']) {
				$status = 'FAIL';
				$summaryParts[] = 'Fixture data did not survive lifecycle transitions intact.';
			}
			if (!$scheduledWorkAfterSecondActivation['stable']) {
				$status = self::mergeStatus($status, 'WARN');
				$summaryParts[] = 'Cron ownership or Action Scheduler state changed after reactivation, or duplicate jobs were detected.';
			}
			if (!empty($logSummary['fatal_count']) || $adminSmokeFailed || $publicSmokeFailed) {
				$status = 'FAIL';
				$summaryParts[] = 'A lifecycle smoke request failed or logged a fatal.';
			} elseif ($loginProbeFailed) {
				$status = self::mergeStatus($status, 'WARN');
				$summaryParts[] = 'The direct login probe did not confirm the session, but downstream admin/public smoke requests succeeded.';
			}
			if (empty($logSummary['fatal_count']) && (!empty($logSummary['warning_count']) || !empty($logSummary['deprecated_count']))) {
				$status = self::mergeStatus($status, 'WARN');
				$summaryParts[] = 'Warnings or deprecations were captured after lifecycle transitions.';
			}
			$keepWorkspace = $status === 'FAIL' && !empty($config['keep_failed_workspaces']);

			return array(
				'status' => $status,
				'summary' => implode(' ', $summaryParts),
				'fixture_seed' => $seeded,
				'before_activation' => $beforeActivation,
				'after_activation' => $afterActivation,
				'after_deactivation' => $afterDeactivation,
				'after_reactivation' => $afterReactivation,
				'after_second_activation' => $afterSecondActivation,
				'second_activation' => $secondActivation,
				'fixture_preservation_after_deactivation' => $deactivationFixture,
				'fixture_preservation_after_reactivation' => $reactivationFixture,
				'scheduled_work_after_reactivation' => $scheduledWorkAfterReactivation,
				'scheduled_work_after_second_activation' => $scheduledWorkAfterSecondActivation,
				'login' => $login,
				'admin_request' => $adminRequest,
				'public_request' => $publicRequest,
				'log_summary' => $logSummary,
				'workspace_retained' => $keepWorkspace,
				'workspace_path' => $keepWorkspace ? $site->getWorkspaceRoot() : '',
			);
		} catch (Throwable $throwable) {
			$keepWorkspace = !empty($config['keep_failed_workspaces']);
			return array(
				'status' => 'FAIL',
				'summary' => 'Clean lifecycle scenario failed.',
				'exception' => self::redactText($throwable->getMessage(), array($config['plugin_root'], $environment['wordpress_source_root'])),
				'workspace_retained' => $keepWorkspace,
				'workspace_path' => $keepWorkspace ? $site->getWorkspaceRoot() : '',
			);
		} finally {
			$site->destroy($keepWorkspace);
		}
	}

	private static function runUpgradeScenario(array $config, array $artifact, array $baselineArtifact, array $environment): array
	{
		if (empty($baselineArtifact['available'])) {
			return array(
				'status' => 'BLOCKED',
				'summary' => 'The production baseline ZIP is required for an authentic upgrade test and was not available to the harness.',
				'required_artifact' => $baselineArtifact['path'] !== '' ? $baselineArtifact['path'] : 'vms-0.2.24.725*.zip',
			);
		}

		$site = self::createSiteHarness('upgrade-from-0.2.24.725', $config, $environment);
		$keepWorkspace = false;
		try {
			$site->provision();
			$site->copyDependencies(array('woocommerce', 'the-events-calendar', 'event-tickets', 'event-tickets-plus'), (array) $environment['dependencies']);
			$site->installArtifact($baselineArtifact['path']);
			$site->activatePlugins(array('woocommerce', 'the-events-calendar', 'event-tickets', 'event-tickets-plus', 'vms'));
			$seeded = $site->seedUpgradeFixtures(self::FIXTURE_OPTION);
			$beforeUpgrade = $site->collectState(self::FIXTURE_OPTION);
			$site->installArtifact($artifact['path']);
			$site->activatePlugins(array('vms'));
			$afterUpgrade = $site->collectState(self::FIXTURE_OPTION);
			$site->installArtifact($artifact['path']);
			$site->activatePlugins(array('vms'));
			$afterSecondUpgrade = $site->collectState(self::FIXTURE_OPTION);
			$site->deactivatePlugins(array('vms'));
			$site->activatePlugins(array('vms'));
			$afterReactivation = $site->collectState(self::FIXTURE_OPTION);
			$site->startServer();
			$login = $site->loginAdmin();
			$adminRequest = $site->requestAdminEventPlanList();
			$publicPage = $site->requestPrimaryPublicPage();
			$eventRequest = $site->requestFixtureEventPage();
			$logSummary = $site->readLogSummary();

			$upgradePreservation = self::compareFixturePreservation((array) ($beforeUpgrade['fixture'] ?? array()), (array) ($afterUpgrade['fixture'] ?? array()));
			$secondUpgradePreservation = self::compareFixturePreservation((array) ($afterUpgrade['fixture'] ?? array()), (array) ($afterSecondUpgrade['fixture'] ?? array()));
			$reactivationPreservation = self::compareFixturePreservation((array) ($afterUpgrade['fixture'] ?? array()), (array) ($afterReactivation['fixture'] ?? array()));
			$scheduledWorkAfterUpgrade = self::compareScheduledWorkSnapshots($beforeUpgrade, $afterUpgrade);
			$scheduledWorkAfterSecondUpgrade = self::compareScheduledWorkSnapshots($afterUpgrade, $afterSecondUpgrade);
			$scheduledWorkAfterReactivation = self::compareScheduledWorkSnapshots($afterUpgrade, $afterReactivation);
			$loginProbeFailed = empty($login['ok']);
			$adminSmokeFailed = empty($adminRequest['ok']);
			$publicSmokeFailed = empty($publicPage['ok']);
			$eventSmokeFailed = empty($eventRequest['ok']);

			$status = 'PASS';
			$summaryParts = array('Baseline 0.2.24.725 upgraded to the public release ZIP on a disposable site.');
			if (!$upgradePreservation['preserved'] || !$secondUpgradePreservation['preserved'] || !$reactivationPreservation['preserved']) {
				$status = 'FAIL';
				$summaryParts[] = 'One or more representative fixture checks regressed across update or reactivation.';
			}
			if (!empty($logSummary['fatal_count']) || $adminSmokeFailed || $publicSmokeFailed || $eventSmokeFailed) {
				$status = 'FAIL';
				$summaryParts[] = 'Admin or frontend smoke requests failed after the upgrade.';
			} elseif ($loginProbeFailed) {
				$status = self::mergeStatus($status, 'WARN');
				$summaryParts[] = 'The direct login probe did not confirm the session, but downstream admin/public smoke requests succeeded.';
			}
			if (empty($logSummary['fatal_count']) && (!empty($logSummary['warning_count']) || !empty($logSummary['deprecated_count']))) {
				$status = self::mergeStatus($status, 'WARN');
				$summaryParts[] = 'Warnings or deprecations were captured after the upgrade.';
			}
			if (!$scheduledWorkAfterSecondUpgrade['stable'] || !$scheduledWorkAfterReactivation['stable']) {
				$status = ($status === 'FAIL') ? 'FAIL' : 'WARN';
				$summaryParts[] = 'Cron ownership or Action Scheduler state changed, or duplicate scheduling appeared after repeat upgrade paths.';
			}
			$keepWorkspace = $status === 'FAIL' && !empty($config['keep_failed_workspaces']);

			return array(
				'status' => $status,
				'summary' => implode(' ', $summaryParts),
				'fixture_seed' => $seeded,
				'before_upgrade' => $beforeUpgrade,
				'after_upgrade' => $afterUpgrade,
				'after_second_upgrade' => $afterSecondUpgrade,
				'after_reactivation' => $afterReactivation,
				'fixture_preservation_after_upgrade' => $upgradePreservation,
				'fixture_preservation_after_second_upgrade' => $secondUpgradePreservation,
				'fixture_preservation_after_reactivation' => $reactivationPreservation,
				'scheduled_work_after_upgrade' => $scheduledWorkAfterUpgrade,
				'scheduled_work_after_second_upgrade' => $scheduledWorkAfterSecondUpgrade,
				'scheduled_work_after_reactivation' => $scheduledWorkAfterReactivation,
				'login' => $login,
				'admin_request' => $adminRequest,
				'public_request' => $publicPage,
				'event_request' => $eventRequest,
				'log_summary' => $logSummary,
				'workspace_retained' => $keepWorkspace,
				'workspace_path' => $keepWorkspace ? $site->getWorkspaceRoot() : '',
			);
		} catch (Throwable $throwable) {
			$keepWorkspace = !empty($config['keep_failed_workspaces']);
			return array(
				'status' => 'FAIL',
				'summary' => 'Upgrade scenario failed.',
				'exception' => self::redactText($throwable->getMessage(), array($config['plugin_root'], $environment['wordpress_source_root'])),
				'workspace_retained' => $keepWorkspace,
				'workspace_path' => $keepWorkspace ? $site->getWorkspaceRoot() : '',
			);
		} finally {
			$site->destroy($keepWorkspace);
		}
	}

	private static function runMigrationInterruptionScenario(array $config, array $artifact, array $environment): array
	{
		$site = self::createSiteHarness('migration-interruption', $config, $environment);
		$keepWorkspace = false;
		try {
			$site->provision();
			$site->copyDependencies(array('woocommerce', 'the-events-calendar', 'event-tickets', 'event-tickets-plus'), (array) $environment['dependencies']);
			$site->installArtifact($artifact['path']);
			$site->activatePlugins(array('woocommerce', 'the-events-calendar', 'event-tickets', 'event-tickets-plus', 'vms'));
			$site->seedUpgradeFixtures(self::FIXTURE_OPTION);
			$beforeRollback = $site->collectState(self::FIXTURE_OPTION);
			$site->evalPhp(
				'update_option("vms_db_schema_version", "vendor_core_v6", false);' .
				'update_option("vms_admission_db_version", "1.3.0", false);' .
				'update_option("vms_ticketing_claims_db_schema_version", "", false);' .
				'echo "ok\n";'
			);
			$site->deactivatePlugins(array('vms'));
			$site->activatePlugins(array('vms'));
			$afterResume = $site->collectState(self::FIXTURE_OPTION);
			$site->startServer();
			$login = $site->loginAdmin();
			$publicRequest = $site->requestPrimaryPublicPage();
			$logSummary = $site->readLogSummary();
			$fixturePreservation = self::compareFixturePreservation((array) ($beforeRollback['fixture'] ?? array()), (array) ($afterResume['fixture'] ?? array()));
			$scheduledWork = self::compareScheduledWorkSnapshots($beforeRollback, $afterResume);
			$loginProbeFailed = empty($login['ok']);
			$publicSmokeFailed = empty($publicRequest['ok']);

			$status = 'PASS';
			$summaryParts = array('The harness simulated a partial migration marker rollback and reran activation/boot safely.');
			if (!$fixturePreservation['preserved']) {
				$status = 'FAIL';
				$summaryParts[] = 'Representative fixture state regressed after the migration-resume simulation.';
			}
			if (!empty($logSummary['fatal_count']) || $publicSmokeFailed) {
				$status = 'FAIL';
				$summaryParts[] = 'The resume path failed to boot cleanly.';
			} elseif ($loginProbeFailed) {
				$status = self::mergeStatus($status, 'WARN');
				$summaryParts[] = 'The direct login probe did not confirm the session, but the public smoke request succeeded.';
			}
			if (!$scheduledWork['stable']) {
				$status = ($status === 'FAIL') ? 'FAIL' : 'WARN';
				$summaryParts[] = 'Cron ownership or Action Scheduler state changed, or duplicate jobs appeared during the resume path.';
			}
			if (empty($logSummary['fatal_count']) && (!empty($logSummary['warning_count']) || !empty($logSummary['deprecated_count']))) {
				$status = self::mergeStatus($status, 'WARN');
				$summaryParts[] = 'Warnings or deprecations were captured during the resume path.';
			}
			$keepWorkspace = $status === 'FAIL' && !empty($config['keep_failed_workspaces']);

			return array(
				'status' => $status,
				'summary' => implode(' ', $summaryParts),
				'before_resume' => $beforeRollback,
				'after_resume' => $afterResume,
				'fixture_preservation' => $fixturePreservation,
				'scheduled_work_comparison' => $scheduledWork,
				'login' => $login,
				'public_request' => $publicRequest,
				'log_summary' => $logSummary,
				'simulation' => 'Rolled back schema marker options while leaving created tables in place, then reran the normal activation/boot path.',
				'workspace_retained' => $keepWorkspace,
				'workspace_path' => $keepWorkspace ? $site->getWorkspaceRoot() : '',
			);
		} catch (Throwable $throwable) {
			$keepWorkspace = !empty($config['keep_failed_workspaces']);
			return array(
				'status' => 'FAIL',
				'summary' => 'Migration interruption scenario failed.',
				'exception' => self::redactText($throwable->getMessage(), array($config['plugin_root'], $environment['wordpress_source_root'])),
				'workspace_retained' => $keepWorkspace,
				'workspace_path' => $keepWorkspace ? $site->getWorkspaceRoot() : '',
			);
		} finally {
			$site->destroy($keepWorkspace);
		}
	}

	private static function runUninstallScenario(array $config, array $artifact, array $environment): array
	{
		$site = self::createSiteHarness('uninstall', $config, $environment);
		$keepWorkspace = false;
		try {
			$site->provision();
			$site->copyDependencies(array('woocommerce', 'the-events-calendar', 'event-tickets', 'event-tickets-plus'), (array) $environment['dependencies']);
			$site->installArtifact($artifact['path']);
			$site->activatePlugins(array('woocommerce', 'the-events-calendar', 'event-tickets', 'event-tickets-plus', 'vms'));
			$site->seedUpgradeFixtures(self::FIXTURE_OPTION);
			$beforeDeactivate = $site->collectState(self::FIXTURE_OPTION);
			$site->deactivatePlugins(array('vms'));
			$afterDeactivate = $site->collectState(self::FIXTURE_OPTION);
			$site->deletePlugins(array('vms'));
			$afterDelete = $site->collectState(self::FIXTURE_OPTION);

			$deactivatePreservation = self::compareFixturePreservation((array) ($beforeDeactivate['fixture'] ?? array()), (array) ($afterDeactivate['fixture'] ?? array()));
			$deletePreservation = self::compareFixturePreservation((array) ($beforeDeactivate['fixture'] ?? array()), (array) ($afterDelete['fixture'] ?? array()));

			$status = 'PASS';
			$summaryParts = array('Deactivation and plugin deletion left representative data intact on the disposable site.');
			if (!$deactivatePreservation['preserved'] || !$deletePreservation['preserved']) {
				$status = 'FAIL';
				$summaryParts[] = 'Fixture data was removed or changed unexpectedly.';
			}
			$keepWorkspace = $status === 'FAIL' && !empty($config['keep_failed_workspaces']);

			return array(
				'status' => $status,
				'summary' => implode(' ', $summaryParts),
				'before_deactivate' => $beforeDeactivate,
				'after_deactivate' => $afterDeactivate,
				'after_delete' => $afterDelete,
				'fixture_preservation_after_deactivate' => $deactivatePreservation,
				'fixture_preservation_after_delete' => $deletePreservation,
				'uninstall_policy' => 'Current uninstall.php is a direct-context guard only; it does not delete plugin-owned data by default.',
				'workspace_retained' => $keepWorkspace,
				'workspace_path' => $keepWorkspace ? $site->getWorkspaceRoot() : '',
			);
		} catch (Throwable $throwable) {
			$keepWorkspace = !empty($config['keep_failed_workspaces']);
			return array(
				'status' => 'FAIL',
				'summary' => 'Uninstall scenario failed.',
				'exception' => self::redactText($throwable->getMessage(), array($config['plugin_root'], $environment['wordpress_source_root'])),
				'workspace_retained' => $keepWorkspace,
				'workspace_path' => $keepWorkspace ? $site->getWorkspaceRoot() : '',
			);
		} finally {
			$site->destroy($keepWorkspace);
		}
	}

	private static function createSiteHarness(string $scenarioId, array $config, array $environment): VMS_Release_Compatibility_Site
	{
		return new VMS_Release_Compatibility_Site(array(
			'scenario_id' => $scenarioId,
			'plugin_root' => $config['plugin_root'],
			'workspace_root' => self::createTemporaryDirectory('vms-release-compat-'),
			'wordpress_source_root' => $environment['wordpress_source_root'],
			'db_config' => (array) $environment['site_db_config'],
			'fixture_collect_script' => $config['plugin_root'] . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'compatibility' . DIRECTORY_SEPARATOR . 'collect-state.php',
			'fixture_seed_script' => $config['plugin_root'] . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'compatibility' . DIRECTORY_SEPARATOR . 'seed-upgrade-fixtures.php',
			'php_memory_limit' => $config['php_memory_limit'],
			'wp_cli_timeout_seconds' => $config['wp_cli_timeout_seconds'],
		));
	}

	private static function createTemporaryDirectory(string $prefix): string
	{
		$path = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $prefix . bin2hex(random_bytes(6));
		if (!mkdir($path, 0775, true) && !is_dir($path)) {
			throw new RuntimeException('Could not create temporary directory: ' . $path);
		}
		return $path;
	}

	public static function deletePath(string $path): void
	{
		if ($path === '' || !file_exists($path)) {
			return;
		}
		if (is_link($path) || is_file($path)) {
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

	public static function copyDirectory(string $source, string $destination): void
	{
		if (!is_dir($source)) {
			throw new RuntimeException('Directory copy source is missing: ' . $source);
		}
		if (!is_dir($destination) && !mkdir($destination, 0775, true) && !is_dir($destination)) {
			throw new RuntimeException('Could not create copy destination: ' . $destination);
		}
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::SELF_FIRST
		);
		foreach ($iterator as $item) {
			/** @var SplFileInfo $item */
			$relative = substr($item->getPathname(), strlen($source) + 1);
			$target = $destination . DIRECTORY_SEPARATOR . $relative;
			if ($item->isDir()) {
				if (!is_dir($target) && !mkdir($target, 0775, true) && !is_dir($target)) {
					throw new RuntimeException('Could not create destination directory: ' . $target);
				}
				continue;
			}
			$targetDir = dirname($target);
			if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
				throw new RuntimeException('Could not create destination directory: ' . $targetDir);
			}
			if (!copy($item->getPathname(), $target)) {
				throw new RuntimeException('Could not copy dependency file: ' . $relative);
			}
		}
	}

	public static function parseDatabaseHost(string $dbHost): array
	{
		$host = $dbHost;
		$port = 3306;
		$socket = '';
		if (preg_match('/^([^:]+):(\/.+)$/', $dbHost, $matches)) {
			$host = (string) $matches[1];
			$socket = (string) $matches[2];
		} elseif (preg_match('/^([^:]+):(\d+)$/', $dbHost, $matches)) {
			$host = (string) $matches[1];
			$port = (int) $matches[2];
		}

		return array(
			'host' => $host !== '' ? $host : 'localhost',
			'port' => $port,
			'socket' => $socket,
		);
	}

	private static function readZipEntry(string $zipPath, string $entryName): string
	{
		$zip = new ZipArchive();
		$opened = $zip->open($zipPath);
		if ($opened !== true) {
			return '';
		}
		$contents = $zip->getFromName($entryName);
		$zip->close();
		return is_string($contents) ? $contents : '';
	}

	private static function extractArtifactVersion(string $artifactPath): string
	{
		if (!preg_match('/vms-([0-9.]+(?:\.[0-9]+)*)/i', basename($artifactPath), $matches)) {
			return '';
		}
		return (string) ($matches[1] ?? '');
	}

	private static function writeReportFiles(array $config, array $report): void
	{
		$outputDir = $config['output_dir'];
		if (!is_dir($outputDir) && !mkdir($outputDir, 0775, true) && !is_dir($outputDir)) {
			throw new RuntimeException('Could not create compatibility report directory: ' . $outputDir);
		}

		$version = (string) ($report['artifact']['version'] ?? 'unknown');
		$prefix = 'vms-' . $version . '-release-compatibility';
		$jsonPath = $outputDir . DIRECTORY_SEPARATOR . $prefix . '.report.json';
		$textPath = $outputDir . DIRECTORY_SEPARATOR . $prefix . '.report.txt';

		if (!$config['force']) {
			foreach (array($jsonPath, $textPath) as $path) {
				if (file_exists($path)) {
					throw new RuntimeException('Compatibility report already exists. Re-run with --force to overwrite: ' . basename($path));
				}
			}
		}

		$sanitized = self::sanitizeReport($report, array($config['plugin_root'], (string) ($report['environment']['wordpress_source_root'] ?? '')));
		file_put_contents($jsonPath, self::jsonEncode($sanitized, true) . "\n");
		file_put_contents($textPath, self::generateTextReport($sanitized));
	}

	private static function sanitizeReport(array $report, array $paths): array
	{
		$sanitize = static function ($value) use (&$sanitize, $paths) {
			if (is_array($value)) {
				$next = array();
				foreach ($value as $key => $child) {
					if ($key === 'site_db_config' || $key === 'DB_PASSWORD') {
						continue;
					}
					$next[$key] = $sanitize($child);
				}
				return $next;
			}
			if (is_string($value)) {
				return VMS_Release_Compatibility_Tooling::redactText($value, $paths);
			}
			return $value;
		};

		return $sanitize($report);
	}

	public static function redactText(string $text, array $paths): string
	{
		$text = str_replace(array("\r\n", "\r"), "\n", $text);
		foreach ($paths as $path) {
			if (!is_string($path) || $path === '') {
				continue;
			}
			$text = str_replace($path, '[PATH]', $text);
		}
		$text = preg_replace('#/Users/[^/\n]+#', '[PATH]', $text) ?? $text;
		return $text;
	}

	public static function jsonEncode($value, bool $pretty = false): string
	{
		$options = JSON_UNESCAPED_SLASHES;
		if ($pretty) {
			$options |= JSON_PRETTY_PRINT;
		}
		$encoded = json_encode($value, $options);
		if (!is_string($encoded)) {
			throw new RuntimeException('Could not encode JSON payload.');
		}
		return $encoded;
	}

	private static function proposeCompatibilityMetadata(array $environment): array
	{
		$deps = (array) ($environment['dependencies'] ?? array());
		$woo = (array) ($deps['woocommerce'] ?? array());
		$tec = (array) ($deps['the-events-calendar'] ?? array());
		$eventTickets = (array) ($deps['event-tickets'] ?? array());
		$eventTicketsPlus = (array) ($deps['event-tickets-plus'] ?? array());

		return array(
			'minimum_technically_loadable_php' => '7.4 (static syntax and dependency header evidence; not lower-version runtime-tested by this harness)',
			'minimum_supported_php' => '7.4 if support is defined around the currently tested Woo/TEC stack',
			'versions_actually_tested_php' => (string) ($environment['php_version'] ?? ''),
			'minimum_technically_loadable_wordpress' => '6.8 if support is anchored to the current WooCommerce dependency floor; lower standalone VMS core floor remains unproven',
			'minimum_supported_wordpress' => '6.8 if shipping against WooCommerce ' . (string) ($woo['version'] ?? '') . ' as the supported commerce baseline',
			'versions_actually_tested_wordpress' => (string) ($environment['wordpress_version'] ?? ''),
			'proposed_requires_php_header' => 'defer header change until a lower-PHP runtime matrix is executed; evidence today supports 7.4 as the conservative floor',
			'proposed_requires_at_least_header' => 'defer header change until a lower-WordPress runtime matrix is executed; evidence today supports 6.8 as the supported-stack floor',
			'supported_woocommerce_range' => 'Tested exactly ' . (string) ($woo['version'] ?? 'unknown'),
			'supported_tec_range' => 'Tested exactly ' . (string) ($tec['version'] ?? 'unknown'),
			'supported_event_tickets_range' => 'Tested exactly ' . (string) ($eventTickets['version'] ?? 'unknown'),
			'supported_event_tickets_plus_range' => 'Tested exactly ' . (string) ($eventTicketsPlus['version'] ?? 'unknown'),
		);
	}

	private static function computeOverallStatus(array $report): string
	{
		$statuses = array();
		foreach ((array) ($report['scenarios'] ?? array()) as $scenario) {
			$statuses[] = (string) ($scenario['status'] ?? 'UNKNOWN');
		}
		foreach (array('clean_install_lifecycle', 'upgrade', 'migration_interruption', 'uninstall') as $key) {
			$statuses[] = (string) (($report[$key]['status'] ?? 'UNKNOWN'));
		}

		if (in_array('FAIL', $statuses, true)) {
			return 'FAIL';
		}
		if (in_array('BLOCKED', $statuses, true)) {
			return 'BLOCKED';
		}
		if (in_array('WARN', $statuses, true)) {
			return 'WARN';
		}
		return 'PASS';
	}

	private static function compareHookCountMaps(array $beforeHooks, array $afterHooks): array
	{
		$allHooks = array_values(array_unique(array_merge(array_keys($beforeHooks), array_keys($afterHooks))));
		$ephemeralHooks = array('vms_ticket_integrity_spot_scan');
		sort($allHooks, SORT_STRING);

		$differences = array();
		$duplicateHooks = array();
		foreach ($allHooks as $hook) {
			$beforeCount = (int) ($beforeHooks[$hook] ?? 0);
			$afterCount = (int) ($afterHooks[$hook] ?? 0);
			$ignoreDisappearance = in_array($hook, $ephemeralHooks, true) && $beforeCount > 0 && $afterCount === 0;
			if ($beforeCount !== $afterCount) {
				if (!$ignoreDisappearance) {
					$differences[$hook] = array(
						'before' => $beforeCount,
						'after' => $afterCount,
					);
				}
			}
			if ($afterCount > 1) {
				$duplicateHooks[$hook] = $afterCount;
			}
		}

		return array(
			'differences' => $differences,
			'duplicate_hooks' => $duplicateHooks,
			'stable' => $differences === array() && $duplicateHooks === array(),
		);
	}

	private static function mergeStatus(string $current, string $candidate): string
	{
		$severity = array(
			'PASS' => 0,
			'SKIP' => 1,
			'WARN' => 2,
			'BLOCKED' => 3,
			'FAIL' => 4,
		);
		$currentScore = $severity[$current] ?? 0;
		$candidateScore = $severity[$candidate] ?? 0;
		return $candidateScore > $currentScore ? $candidate : $current;
	}
}

final class VMS_Release_Compatibility_Site
{
	private string $scenarioId;
	private string $pluginRoot;
	private string $workspaceRoot;
	private string $wordpressSourceRoot;
	private array $dbConfig;
	private string $fixtureCollectScript;
	private string $fixtureSeedScript;
	private string $siteRoot;
	private string $siteUrl;
	private string $dbName;
	private string $tablePrefix;
	private string $adminUser;
	private string $adminPassword;
	private string $cookieFile;
	private string $debugLogPath;
	private string $serverLogPath;
	private string $routerScriptPath;
	private string $phpMemoryLimit;
	private int $wpCliTimeoutSeconds;
	private int $port;
	/** @var resource|null */
	private $serverProcess = null;
	/** @var array<int, resource> */
	private array $serverPipes = array();

	public function __construct(array $config)
	{
		$this->scenarioId = (string) $config['scenario_id'];
		$this->pluginRoot = (string) $config['plugin_root'];
		$this->workspaceRoot = (string) $config['workspace_root'];
		$this->wordpressSourceRoot = (string) $config['wordpress_source_root'];
		$this->dbConfig = (array) $config['db_config'];
		$this->fixtureCollectScript = (string) $config['fixture_collect_script'];
		$this->fixtureSeedScript = (string) $config['fixture_seed_script'];
		$this->siteRoot = $this->workspaceRoot . DIRECTORY_SEPARATOR . 'site';
		$this->port = $this->pickFreePort();
		$this->siteUrl = 'http://127.0.0.1:' . $this->port;
		$this->dbName = 'vms_rc_' . substr(preg_replace('/[^a-z0-9_]+/i', '_', strtolower($this->scenarioId)), 0, 18) . '_' . bin2hex(random_bytes(3));
		$this->tablePrefix = 'vrc_' . substr(bin2hex(random_bytes(3)), 0, 6) . '_';
		$this->adminUser = 'vms_admin_' . substr(bin2hex(random_bytes(4)), 0, 8);
		$this->adminPassword = bin2hex(random_bytes(16));
		$this->cookieFile = $this->workspaceRoot . DIRECTORY_SEPARATOR . 'cookies.txt';
		$this->debugLogPath = $this->siteRoot . DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'debug.log';
		$this->serverLogPath = $this->workspaceRoot . DIRECTORY_SEPARATOR . 'php-server.log';
		$this->routerScriptPath = $this->siteRoot . DIRECTORY_SEPARATOR . 'router.php';
		$this->phpMemoryLimit = trim((string) ($config['php_memory_limit'] ?? '512M'));
		$this->wpCliTimeoutSeconds = max(30, (int) ($config['wp_cli_timeout_seconds'] ?? 180));
	}

	public function getWorkspaceRoot(): string
	{
		return $this->workspaceRoot;
	}

	public function getSiteUrl(): string
	{
		return $this->siteUrl;
	}

	public function provision(): void
	{
		if (!is_dir($this->siteRoot) && !mkdir($this->siteRoot, 0775, true) && !is_dir($this->siteRoot)) {
			throw new RuntimeException('Could not create site root.');
		}
		$this->buildWordPressSkeleton();
		$this->writeRouterScript();
		$this->createDatabase();
		$this->writeWpConfig();
		$this->runWp(array(
			'core',
			'install',
			'--url=' . $this->siteUrl,
			'--title=VMS Compatibility ' . $this->scenarioId,
			'--admin_user=' . $this->adminUser,
			'--admin_password=' . $this->adminPassword,
			'--admin_email=' . $this->adminUser . '@example.test',
			'--skip-email',
		));
		$this->prepareHttpRouting();
		$this->activateDefaultTheme();
	}

	public function copyDependencies(array $slugs, array $dependencyDefinitions): void
	{
		$pluginsDir = $this->siteRoot . DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'plugins';
		foreach ($slugs as $slug) {
			$slug = (string) $slug;
			if ($slug === '') {
				continue;
			}
			$definition = (array) ($dependencyDefinitions[$slug] ?? array());
			$source = (string) ($definition['path'] ?? '');
			$destination = $pluginsDir . DIRECTORY_SEPARATOR . $slug;
			if (!is_dir($source)) {
				throw new RuntimeException('Dependency plugin directory is unavailable: ' . $slug);
			}
			VMS_Release_Compatibility_Tooling::copyDirectory($source, $destination);
		}
	}

	public function installArtifact(string $artifactPath): array
	{
		return $this->runWp(array('plugin', 'install', $artifactPath, '--force'));
	}

	public function activatePlugins(array $slugs): array
	{
		if ($slugs === array()) {
			return array('ok' => true, 'stdout' => '', 'stderr' => '', 'exit_code' => 0);
		}
		return $this->runWp(array_merge(array('plugin', 'activate'), array_values(array_map('strval', $slugs))));
	}

	public function deactivatePlugins(array $slugs): array
	{
		if ($slugs === array()) {
			return array('ok' => true, 'stdout' => '', 'stderr' => '', 'exit_code' => 0);
		}
		return $this->runWp(array_merge(array('plugin', 'deactivate'), array_values(array_map('strval', $slugs))));
	}

	public function deletePlugins(array $slugs): array
	{
		if ($slugs === array()) {
			return array('ok' => true, 'stdout' => '', 'stderr' => '', 'exit_code' => 0);
		}
		return $this->runWp(array_merge(array('plugin', 'delete'), array_values(array_map('strval', $slugs))));
	}

	public function pluginList(): array
	{
		$result = $this->runWp(array('plugin', 'list', '--format=json'));
		$decoded = json_decode((string) $result['stdout'], true);
		return is_array($decoded) ? $decoded : array();
	}

	public function seedUpgradeFixtures(string $fixtureOption): array
	{
		return $this->evalJsonFile($this->fixtureSeedScript, array(
			'fixture_option' => $fixtureOption,
			'prefix' => $this->scenarioId,
		));
	}

	public function collectState(string $fixtureOption): array
	{
		return $this->evalJsonFile($this->fixtureCollectScript, array(
			'fixture_option' => $fixtureOption,
		));
	}

	public function evalPhp(string $code): array
	{
		return $this->runWp(array('eval', $code));
	}

	public function startServer(): void
	{
		if (is_resource($this->serverProcess)) {
			return;
		}
		$this->prepareHttpRouting();
		$descriptorSpec = array(
			0 => array('pipe', 'r'),
			1 => array('file', $this->serverLogPath, 'a'),
			2 => array('file', $this->serverLogPath, 'a'),
		);
		$command = array(
			PHP_BINARY,
			'-d',
			'display_errors=0',
			'-d',
			'log_errors=1',
			'-d',
			'error_log=' . $this->serverLogPath,
			'-d',
			'memory_limit=' . $this->phpMemoryLimit,
			'-S',
			'127.0.0.1:' . $this->port,
			'-t',
			$this->siteRoot,
			$this->routerScriptPath,
		);
		$process = proc_open($command, $descriptorSpec, $pipes, $this->siteRoot);
		if (!is_resource($process)) {
			throw new RuntimeException('Could not start disposable PHP server.');
		}
		$this->serverProcess = $process;
		$this->serverPipes = $pipes;

		$deadline = microtime(true) + 10.0;
		while (microtime(true) < $deadline) {
			$response = $this->rawHttpRequest('GET', $this->siteUrl . '/wp-login.php');
			if (!empty($response['ok'])) {
				return;
			}
			usleep(150000);
		}

		throw new RuntimeException('Disposable PHP server did not become ready in time.');
	}

	public function loginAdmin(): array
	{
		$response = $this->rawHttpRequest(
			'POST',
			$this->siteUrl . '/wp-login.php',
			array(
				'form' => array(
					'log' => $this->adminUser,
					'pwd' => $this->adminPassword,
					'wp-submit' => 'Log In',
					'redirect_to' => $this->siteUrl . '/wp-admin/',
					'testcookie' => '1',
				),
			)
		);
		$followUp = $this->rawHttpRequest('GET', $this->siteUrl . '/wp-admin/');
		$body = (string) ($followUp['body'] ?? '');
		$originMismatchDetected = !$this->urlMatchesSiteOrigin((string) ($followUp['final_url'] ?? ''));
		$hasAdminMarker = strpos($body, 'wp-admin-bar') !== false
			|| strpos($body, 'wpadminbar') !== false
			|| strpos($body, 'adminmenuwrap') !== false
			|| strpos($body, 'wpbody-content') !== false;
		$looksLikeLoginForm = strpos($body, 'id="loginform"') !== false || strpos($body, 'name="log"') !== false;
		$ok = !empty($response['ok']) && !empty($followUp['ok']) && $hasAdminMarker && !$looksLikeLoginForm && !$originMismatchDetected;

		return array(
			'ok' => $ok,
			'status_code' => (int) ($followUp['status_code'] ?? 0),
			'final_url' => (string) ($followUp['final_url'] ?? ''),
			'origin_mismatch_detected' => $originMismatchDetected,
			'has_admin_marker' => $hasAdminMarker,
			'looks_like_login_form' => $looksLikeLoginForm,
		);
	}

	public function requestAdminEventPlanList(): array
	{
		$response = $this->rawHttpRequest('GET', $this->siteUrl . '/wp-admin/edit.php?post_type=vms_event_plan');
		return $this->normalizeHttpCheck($response);
	}

	public function requestPrimaryPublicPage(): array
	{
		$state = $this->collectState(VMS_Release_Compatibility_Tooling::FIXTURE_OPTION);
		$pageId = (int) (($state['plugin']['public_pages']['public_calendar']['id'] ?? 0));
		$pageSlug = (string) (($state['plugin']['public_pages']['public_calendar']['slug'] ?? ''));
		$url = $this->siteUrl . '/';
		if ($pageSlug !== '') {
			$url = $this->siteUrl . '/' . trim($pageSlug, '/') . '/';
		} elseif ($pageId > 0) {
			$url = $this->siteUrl . '/?page_id=' . $pageId;
		}
		$response = $this->rawHttpRequest('GET', $url);
		return $this->normalizeHttpCheck($response);
	}

	public function requestFixtureEventPage(): array
	{
		$state = $this->collectState(VMS_Release_Compatibility_Tooling::FIXTURE_OPTION);
		$eventId = (int) (($state['fixture']['manifest']['tec_event_id'] ?? 0));
		$eventPermalink = (string) (($state['fixture']['event_permalink'] ?? '') ?: ($state['fixture']['checks']['event_permalink'] ?? ''));
		if ($eventId <= 0) {
			return array(
				'ok' => false,
				'status_code' => 0,
				'final_url' => '',
				'body_excerpt' => '',
				'path_leak_detected' => false,
				'stack_trace_detected' => false,
			);
		}
		$url = $this->siteUrl . '/?post_type=tribe_events&p=' . $eventId;
		if ($eventPermalink !== '') {
			$parts = parse_url($eventPermalink);
			$path = (string) ($parts['path'] ?? '');
			$query = isset($parts['query']) ? ('?' . (string) $parts['query']) : '';
			if ($path !== '') {
				$url = rtrim($this->siteUrl, '/') . $path . $query;
			}
		}
		$response = $this->rawHttpRequest('GET', $url);
		return $this->normalizeHttpCheck($response);
	}

	public function readLogSummary(): array
	{
		$lines = array();
		foreach (array($this->debugLogPath, $this->serverLogPath) as $logPath) {
			if (!is_file($logPath)) {
				continue;
			}
			$contents = (string) file_get_contents($logPath);
			foreach (preg_split('/\r\n|\r|\n/', $contents) as $line) {
				$line = trim((string) $line);
				if ($line !== '') {
					$lines[] = $line;
				}
			}
		}

		$fatalCount = 0;
		$warningCount = 0;
		$deprecatedCount = 0;
		$sanitizedLines = array();
		foreach ($lines as $line) {
			$sanitized = VMS_Release_Compatibility_Tooling::redactText($line, array($this->pluginRoot, $this->wordpressSourceRoot, $this->workspaceRoot));
			if (
				strpos($sanitized, 'phar:///opt/homebrew/Cellar/wp-cli/') !== false ||
				strpos($sanitized, 'php-cli-tools/lib/cli/Colors.php') !== false ||
				strpos($sanitized, 'Case statements followed by a semicolon') !== false ||
				strpos($sanitized, 'Function curl_close() is deprecated') !== false ||
				strpos($sanitized, 'Function _load_textdomain_just_in_time was called') !== false
			) {
				continue;
			}
			if (stripos($sanitized, 'fatal error') !== false) {
				$fatalCount++;
			} elseif (stripos($sanitized, 'deprecated') !== false) {
				$deprecatedCount++;
			} elseif (stripos($sanitized, 'warning') !== false || stripos($sanitized, 'notice') !== false) {
				$warningCount++;
			}
			if (count($sanitizedLines) < 25) {
				$sanitizedLines[] = $sanitized;
			}
		}

		return array(
			'fatal_count' => $fatalCount,
			'warning_count' => $warningCount,
			'deprecated_count' => $deprecatedCount,
			'line_count' => count($lines),
			'sample' => $sanitizedLines,
		);
	}

	public function destroy(bool $keepWorkspace): void
	{
		$this->stopServer();
		if (!$keepWorkspace) {
			$this->dropDatabase();
		}
		if (!$keepWorkspace) {
			VMS_Release_Compatibility_Tooling::deletePath($this->workspaceRoot);
		}
	}

	private function buildWordPressSkeleton(): void
	{
		$sourceEntries = scandir($this->wordpressSourceRoot);
		if ($sourceEntries === false) {
			throw new RuntimeException('Could not read WordPress source root.');
		}

		foreach ($sourceEntries as $entry) {
			if ($entry === '.' || $entry === '..') {
				continue;
			}
			if (in_array($entry, array('wp-config.php', 'wp-content', 'vms', 'test-results', '.DS_Store'), true)) {
				continue;
			}
			$sourcePath = $this->wordpressSourceRoot . DIRECTORY_SEPARATOR . $entry;
			$targetPath = $this->siteRoot . DIRECTORY_SEPARATOR . $entry;
			if (is_dir($sourcePath)) {
				VMS_Release_Compatibility_Tooling::copyDirectory($sourcePath, $targetPath);
				continue;
			}
			if (!copy($sourcePath, $targetPath)) {
				throw new RuntimeException('Could not copy WordPress core entry: ' . $entry);
			}
		}

		$wpContentRoot = $this->siteRoot . DIRECTORY_SEPARATOR . 'wp-content';
		if (!is_dir($wpContentRoot) && !mkdir($wpContentRoot, 0775, true) && !is_dir($wpContentRoot)) {
			throw new RuntimeException('Could not create wp-content root.');
		}
		$sourceWpContent = $this->wordpressSourceRoot . DIRECTORY_SEPARATOR . 'wp-content';
		$wpContentEntries = scandir($sourceWpContent);
		if ($wpContentEntries === false) {
			throw new RuntimeException('Could not inspect source wp-content.');
		}
		$allowedWpContentEntries = array('index.php', 'themes', 'languages');
		foreach ($wpContentEntries as $entry) {
			if ($entry === '.' || $entry === '..') {
				continue;
			}
			if (!in_array($entry, $allowedWpContentEntries, true)) {
				continue;
			}
			if (in_array($entry, array('plugins', 'uploads', 'debug.log', 'cache', '.DS_Store'), true)) {
				continue;
			}
			$sourcePath = $sourceWpContent . DIRECTORY_SEPARATOR . $entry;
			$targetPath = $wpContentRoot . DIRECTORY_SEPARATOR . $entry;
			if (!symlink($sourcePath, $targetPath)) {
				throw new RuntimeException('Could not create wp-content symlink: ' . $entry);
			}
		}
		foreach (array('plugins', 'uploads') as $directory) {
			$path = $wpContentRoot . DIRECTORY_SEPARATOR . $directory;
			if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
				throw new RuntimeException('Could not create disposable wp-content/' . $directory . ' directory.');
			}
		}
	}

	private function writeRouterScript(): void
	{
		$script = <<<'PHP'
<?php
$requestPath = rawurldecode((string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'));
$candidate = __DIR__ . $requestPath;
if ($requestPath !== '/' && (is_file($candidate) || is_dir($candidate))) {
	return false;
}

require __DIR__ . '/index.php';
PHP;
		if (file_put_contents($this->routerScriptPath, $script . "\n") === false) {
			throw new RuntimeException('Could not write disposable PHP router.');
		}
	}

	private function prepareHttpRouting(): void
	{
		$this->runWp(array('option', 'update', 'home', $this->siteUrl));
		$this->runWp(array('option', 'update', 'siteurl', $this->siteUrl));
		$this->runWp(array('rewrite', 'structure', '/%postname%/'));
		$this->runWp(array('rewrite', 'flush'));
	}

	private function createDatabase(): void
	{
		$hostInfo = VMS_Release_Compatibility_Tooling::parseDatabaseHost((string) ($this->dbConfig['DB_HOST'] ?? ''));
		$mysqli = mysqli_init();
		if (!$mysqli instanceof mysqli) {
			throw new RuntimeException('Could not initialize mysqli.');
		}
		@$mysqli->real_connect(
			$hostInfo['host'],
			(string) ($this->dbConfig['DB_USER'] ?? ''),
			(string) ($this->dbConfig['DB_PASSWORD'] ?? ''),
			null,
			$hostInfo['port'],
			$hostInfo['socket']
		);
		if ($mysqli->connect_errno) {
			throw new RuntimeException('Could not connect to the local MySQL server for disposable DB creation.');
		}

		$dbName = str_replace('`', '``', $this->dbName);
		if (!$mysqli->query("CREATE DATABASE `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
			$message = $mysqli->error;
			$mysqli->close();
			throw new RuntimeException('Could not create disposable database: ' . $message);
		}
		$mysqli->close();
	}

	private function dropDatabase(): void
	{
		$hostInfo = VMS_Release_Compatibility_Tooling::parseDatabaseHost((string) ($this->dbConfig['DB_HOST'] ?? ''));
		$mysqli = mysqli_init();
		if (!$mysqli instanceof mysqli) {
			return;
		}
		@$mysqli->real_connect(
			$hostInfo['host'],
			(string) ($this->dbConfig['DB_USER'] ?? ''),
			(string) ($this->dbConfig['DB_PASSWORD'] ?? ''),
			null,
			$hostInfo['port'],
			$hostInfo['socket']
		);
		if ($mysqli->connect_errno) {
			return;
		}
		$dbName = str_replace('`', '``', $this->dbName);
		$mysqli->query("DROP DATABASE IF EXISTS `{$dbName}`");
		$mysqli->close();
	}

	private function writeWpConfig(): void
	{
		$salts = array(
			'AUTH_KEY',
			'SECURE_AUTH_KEY',
			'LOGGED_IN_KEY',
			'NONCE_KEY',
			'AUTH_SALT',
			'SECURE_AUTH_SALT',
			'LOGGED_IN_SALT',
			'NONCE_SALT',
		);
		$lines = array(
			'<?php',
			"define('DB_NAME', " . var_export($this->dbName, true) . ');',
			"define('DB_USER', " . var_export((string) ($this->dbConfig['DB_USER'] ?? ''), true) . ');',
			"define('DB_PASSWORD', " . var_export((string) ($this->dbConfig['DB_PASSWORD'] ?? ''), true) . ');',
			"define('DB_HOST', " . var_export((string) ($this->dbConfig['DB_HOST'] ?? ''), true) . ');',
			"define('DB_CHARSET', 'utf8mb4');",
			"define('DB_COLLATE', '');",
		);
		foreach ($salts as $salt) {
			$lines[] = "define('{$salt}', " . var_export(bin2hex(random_bytes(32)), true) . ');';
		}
		$lines = array_merge($lines, array(
			'$table_prefix = ' . var_export($this->tablePrefix, true) . ';',
			"define('WP_DEBUG', true);",
			"define('WP_DEBUG_LOG', " . var_export($this->debugLogPath, true) . ');',
			"define('WP_DEBUG_DISPLAY', false);",
			"define('WP_MEMORY_LIMIT', " . var_export($this->phpMemoryLimit, true) . ');',
			"define('WP_MAX_MEMORY_LIMIT', " . var_export($this->phpMemoryLimit, true) . ');',
			"define('FORCE_SSL_ADMIN', false);",
			"define('FS_METHOD', 'direct');",
			"define('AUTOMATIC_UPDATER_DISABLED', true);",
			"define('WP_HOME', " . var_export($this->siteUrl, true) . ');',
			"define('WP_SITEURL', " . var_export($this->siteUrl, true) . ');',
			'$_SERVER[\'HTTPS\'] = \'off\';',
			'$_SERVER[\'REQUEST_SCHEME\'] = \'http\';',
			'$_SERVER[\'HTTP_X_FORWARDED_PROTO\'] = \'http\';',
			'$_SERVER[\'SERVER_PORT\'] = ' . var_export((string) $this->port, true) . ';',
			'$_SERVER[\'HTTP_HOST\'] = ' . var_export('127.0.0.1:' . $this->port, true) . ';',
			'$_SERVER[\'SERVER_NAME\'] = \'127.0.0.1\';',
			"@ini_set('display_errors', '0');",
			"@ini_set('log_errors', '1');",
			"@ini_set('error_log', " . var_export($this->debugLogPath, true) . ');',
			"@ini_set('memory_limit', " . var_export($this->phpMemoryLimit, true) . ');',
			'error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);',
			"if (!defined('ABSPATH')) { define('ABSPATH', __DIR__ . '/'); }",
			"require_once ABSPATH . 'wp-settings.php';",
		));
		$configPath = $this->siteRoot . DIRECTORY_SEPARATOR . 'wp-config.php';
		if (file_put_contents($configPath, implode("\n", $lines) . "\n") === false) {
			throw new RuntimeException('Could not write disposable wp-config.php.');
		}
	}

	private function activateDefaultTheme(): void
	{
		foreach (array('twentytwentyfive', 'twentytwentyfour', 'twentytwentythree') as $themeSlug) {
			$result = $this->runWp(array('theme', 'activate', $themeSlug), true);
			if ($result['exit_code'] === 0) {
				return;
			}
		}
	}

	private function runWp(array $args, bool $allowFailure = false): array
	{
		$command = array_merge(
			array(
				PHP_BINARY,
				'-d',
				'memory_limit=' . $this->phpMemoryLimit,
				'-d',
				'display_errors=0',
				'-d',
				'log_errors=1',
				'-d',
				'error_reporting=' . (string) (E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED),
				self::resolveWpCliBinary(),
				'--path=' . $this->siteRoot,
			),
			array_values($args)
		);
		$result = $this->executeProcess($command, $this->siteRoot, $this->wpCliTimeoutSeconds);
		$stdout = trim((string) ($result['stdout'] ?? ''));
		$stderr = trim((string) ($result['stderr'] ?? ''));
		$stdout = $this->filterWpCliNoise($stdout);
		$stderr = $this->filterWpCliNoise($stderr);

		$commandLog = $this->formatCommandForLogs($command);
		$formattedResult = array(
			'ok' => (int) ($result['exit_code'] ?? 1) === 0,
			'exit_code' => (int) ($result['exit_code'] ?? 1),
			'stdout' => $stdout,
			'stderr' => $stderr,
			'command' => $commandLog,
			'timed_out' => !empty($result['timed_out']),
			'peak_rss_kb' => (int) ($result['peak_rss_kb'] ?? 0),
		);
		if ((int) ($result['exit_code'] ?? 1) !== 0 && !$allowFailure) {
			$failureParts = array('WP-CLI command failed: ' . $commandLog);
			if (!empty($result['timed_out'])) {
				$failureParts[] = 'Timed out after ' . $this->wpCliTimeoutSeconds . ' seconds.';
			}
			if (!empty($formattedResult['peak_rss_kb'])) {
				$failureParts[] = 'Peak RSS: ' . $formattedResult['peak_rss_kb'] . ' KB.';
			}
			$errorOutput = $stderr !== '' ? $stderr : $stdout;
			if ($errorOutput !== '') {
				$failureParts[] = $this->limitDiagnosticText($errorOutput);
			}
			throw new RuntimeException(implode("\n", $failureParts));
		}

		return $formattedResult;
	}

	private function executeProcess(array $command, string $cwd, int $timeoutSeconds): array
	{
		$descriptorSpec = array(
			0 => array('pipe', 'r'),
			1 => array('pipe', 'w'),
			2 => array('pipe', 'w'),
		);
		$env = array();
		foreach (array_merge($_SERVER, $_ENV) as $key => $value) {
			if (!is_string($key) || $key === '' || is_array($value) || is_object($value)) {
				continue;
			}
			$env[$key] = is_scalar($value) ? (string) $value : '';
		}
		$path = getenv('PATH');
		if (is_string($path) && $path !== '') {
			$env['PATH'] = $path;
		}

		$process = proc_open($command, $descriptorSpec, $pipes, $cwd, $env);
		if (!is_resource($process)) {
			throw new RuntimeException('Could not start subprocess.');
		}

		fclose($pipes[0]);
		stream_set_blocking($pipes[1], false);
		stream_set_blocking($pipes[2], false);

		$stdout = '';
		$stderr = '';
		$timedOut = false;
		$peakRssKb = 0;
		$deadline = microtime(true) + $timeoutSeconds;

		while (true) {
			$status = proc_get_status($process);
			$processPid = (int) ($status['pid'] ?? 0);
			if ($processPid > 0) {
				$peakRssKb = max($peakRssKb, $this->captureProcessPeakRssKb($processPid));
			}

			$read = array();
			if (!feof($pipes[1])) {
				$read[] = $pipes[1];
			}
			if (!feof($pipes[2])) {
				$read[] = $pipes[2];
			}
			if ($read !== array()) {
				$write = null;
				$except = null;
				@stream_select($read, $write, $except, 0, 200000);
				foreach ($read as $stream) {
					$chunk = stream_get_contents($stream);
					if (!is_string($chunk) || $chunk === '') {
						continue;
					}
					if ($stream === $pipes[1]) {
						$stdout .= $chunk;
					} else {
						$stderr .= $chunk;
					}
				}
			}

			if (!$status['running']) {
				break;
			}
			if (microtime(true) >= $deadline) {
				$timedOut = true;
				proc_terminate($process);
				usleep(250000);
				$status = proc_get_status($process);
				if (!empty($status['running'])) {
					proc_terminate($process, 9);
				}
				break;
			}
		}

		$stdoutRemainder = stream_get_contents($pipes[1]);
		$stderrRemainder = stream_get_contents($pipes[2]);
		if (is_string($stdoutRemainder) && $stdoutRemainder !== '') {
			$stdout .= $stdoutRemainder;
		}
		if (is_string($stderrRemainder) && $stderrRemainder !== '') {
			$stderr .= $stderrRemainder;
		}
		fclose($pipes[1]);
		fclose($pipes[2]);
		$exitCode = proc_close($process);

		return array(
			'exit_code' => $timedOut ? 124 : (int) $exitCode,
			'stdout' => $stdout,
			'stderr' => $stderr,
			'timed_out' => $timedOut,
			'peak_rss_kb' => $peakRssKb,
		);
	}

	private function captureProcessPeakRssKb(int $pid): int
	{
		$command = sprintf('ps -o rss= -p %d 2>/dev/null', $pid);
		$output = shell_exec($command);
		if (!is_string($output) || !preg_match('/(\d+)/', $output, $matches)) {
			return 0;
		}

		return (int) ($matches[1] ?? 0);
	}

	private function evalJsonFile(string $scriptPath, array $args): array
	{
		$result = $this->runWp(array('eval-file', $scriptPath, VMS_Release_Compatibility_Tooling::jsonEncode($args)));
		$decoded = json_decode((string) $result['stdout'], true);
		if (!is_array($decoded)) {
			throw new RuntimeException('Expected JSON output from ' . basename($scriptPath));
		}
		return $decoded;
	}

	private function rawHttpRequest(string $method, string $url, array $options = array()): array
	{
		$ch = curl_init($url);
		if ($ch === false) {
			throw new RuntimeException('Could not initialize cURL.');
		}

		$form = is_array($options['form'] ?? null) ? (array) $options['form'] : null;
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
		curl_setopt($ch, CURLOPT_TIMEOUT, 20);
		curl_setopt($ch, CURLOPT_USERAGENT, 'VMS-Release-Compatibility/1.0');
		if (strtoupper($method) === 'POST') {
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query((array) $form));
		}

		$body = curl_exec($ch);
		$curlError = curl_error($ch);
		$statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
		$finalUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

		return array(
			'ok' => $body !== false && $curlError === '',
			'status_code' => $statusCode,
			'body' => is_string($body) ? $body : '',
			'final_url' => $finalUrl,
			'curl_error' => $curlError,
		);
	}

	private function normalizeHttpCheck(array $response): array
	{
		$body = (string) ($response['body'] ?? '');
		$pathLeakDetected = preg_match('#/Users/|wp-content/plugins/.+\.php#', $body) === 1;
		$stackTraceDetected = stripos($body, 'Stack trace') !== false || stripos($body, 'Fatal error') !== false;
		$originMismatchDetected = !$this->urlMatchesSiteOrigin((string) ($response['final_url'] ?? ''));
		$ok = !empty($response['ok']) && (int) ($response['status_code'] ?? 0) >= 200 && (int) ($response['status_code'] ?? 0) < 400 && !$pathLeakDetected && !$stackTraceDetected && !$originMismatchDetected;

		return array(
			'ok' => $ok,
			'status_code' => (int) ($response['status_code'] ?? 0),
			'final_url' => (string) ($response['final_url'] ?? ''),
			'body_excerpt' => substr(strip_tags($body), 0, 300),
			'path_leak_detected' => $pathLeakDetected,
			'stack_trace_detected' => $stackTraceDetected,
			'origin_mismatch_detected' => $originMismatchDetected,
		);
	}

	private function stopServer(): void
	{
		if (!is_resource($this->serverProcess)) {
			return;
		}
		@proc_terminate($this->serverProcess);
		@proc_close($this->serverProcess);
		$this->serverProcess = null;
		$this->serverPipes = array();
	}

	private function pickFreePort(): int
	{
		$socket = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
		if (!is_resource($socket)) {
			throw new RuntimeException('Could not reserve a local HTTP port.');
		}
		$name = stream_socket_get_name($socket, false);
		fclose($socket);
		if (!is_string($name) || !preg_match('/:(\d+)$/', $name, $matches)) {
			throw new RuntimeException('Could not parse reserved local HTTP port.');
		}
		return (int) $matches[1];
	}

	private function formatCommandForLogs(array $args): string
	{
		$masked = array();
		foreach ($args as $arg) {
			$arg = (string) $arg;
			if (strpos($arg, '--admin_password=') === 0) {
				$masked[] = '--admin_password=[REDACTED]';
				continue;
			}
			$masked[] = $arg;
		}
		return implode(' ', $masked);
	}

	private function urlMatchesSiteOrigin(string $url): bool
	{
		if ($url === '') {
			return false;
		}

		$siteParts = parse_url($this->siteUrl);
		$urlParts = parse_url($url);
		if (!is_array($siteParts) || !is_array($urlParts)) {
			return false;
		}

		return (($siteParts['scheme'] ?? '') === ($urlParts['scheme'] ?? ''))
			&& (($siteParts['host'] ?? '') === ($urlParts['host'] ?? ''))
			&& ((int) ($siteParts['port'] ?? 0) === (int) ($urlParts['port'] ?? 0));
	}

	private function limitDiagnosticText(string $text, int $maxLines = 80): string
	{
		$lines = preg_split('/\r\n|\r|\n/', $text);
		if (!is_array($lines) || count($lines) <= $maxLines) {
			return $text;
		}

		$tail = array_slice($lines, -1 * $maxLines);
		return "[truncated]\n" . implode("\n", $tail);
	}

	private static function resolveWpCliBinary(): string
	{
		static $binary = null;
		if (is_string($binary) && $binary !== '') {
			return $binary;
		}

		$resolved = trim((string) shell_exec('command -v wp 2>/dev/null'));
		if ($resolved === '' || !is_file($resolved)) {
			throw new RuntimeException('Could not resolve the local wp command.');
		}
		$binary = $resolved;

		return $binary;
	}

	private function filterWpCliNoise(string $text): string
	{
		if ($text === '') {
			return '';
		}

		$filtered = array();
		foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
			$line = trim((string) $line);
			if ($line === '') {
				continue;
			}
			if (
				strpos($line, 'phar:///opt/homebrew/Cellar/wp-cli/') !== false ||
				strpos($line, 'php-cli-tools/lib/cli/Colors.php') !== false ||
				strpos($line, 'Case statements followed by a semicolon') !== false ||
				strpos($line, 'Using null as an array offset is deprecated') !== false ||
				strpos($line, 'Function _load_textdomain_just_in_time was called') !== false
			) {
				continue;
			}
			$filtered[] = $line;
		}

		return implode("\n", $filtered);
	}
}
