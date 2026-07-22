<?php
declare(strict_types=1);

final class VMS_Public_Release_Tooling
{
	private const REQUIRED_RUNTIME_FILES = array(
		'vendor-management-system.php',
		'includes/bootstrap.php',
		'includes/core/plugin.php',
		'includes/db/migrations.php',
	);

	private const REQUIRED_RUNTIME_DIRECTORIES = array(
		'assets',
		'includes',
	);

	private const TEXT_SCAN_EXTENSIONS = array(
		'css',
		'csv',
		'html',
		'ini',
		'js',
		'json',
		'md',
		'php',
		'po',
		'pot',
		'svg',
		'txt',
		'xml',
		'yaml',
		'yml',
	);

	private const NESTED_ARCHIVE_EXTENSIONS = array(
		'7z',
		'bz2',
		'gz',
		'rar',
		'tar',
		'tgz',
		'xz',
		'zip',
	);

	private const PUBLIC_PLUGIN_SLUG = 'backstage-venue-manager';

	private const OPTIONAL_LOAD_SMOKE_SCENARIOS = array(
		array(
			'id' => 'wp-load-smoke-baseline',
			'label' => 'WP-CLI load smoke (baseline)',
			'skip_plugins' => array('vms'),
		),
		array(
			'id' => 'wp-load-smoke-without-woocommerce',
			'label' => 'WP-CLI load smoke (without WooCommerce)',
			'skip_plugins' => array('vms', 'woocommerce', 'woocommerce-square'),
		),
		array(
			'id' => 'wp-load-smoke-without-tec',
			'label' => 'WP-CLI load smoke (without The Events Calendar stack)',
			'skip_plugins' => array('vms', 'the-events-calendar', 'event-tickets', 'event-tickets-plus'),
		),
		array(
			'id' => 'wp-load-smoke-without-optional-ticketing',
			'label' => 'WP-CLI load smoke (without optional ticketing add-ons)',
			'skip_plugins' => array('vms', 'woocommerce-square', 'event-tickets-plus'),
		),
	);

	public static function build(array $config): array
	{
		$config = self::normalizeBuildConfig($config);
		$metadata = self::collectSourceMetadata($config['plugin_root']);
		$reportSeed = self::buildInitialReport($config, $metadata);
		$report = $reportSeed;
		$tempBuildDir = null;
		$temporaryPaths = array();
		$provenanceManifest = null;

		try {
			self::assertWritableOutputTargets($config, $reportSeed);

			$report['git'] = self::detectGitState($config['plugin_root']);
			if ($config['provenance_manifest_path'] !== '') {
				$provenanceManifest = self::loadProvenanceManifest($config['provenance_manifest_path']);
				$report['metadata']['provenance_manifest_path'] = (string) ($provenanceManifest['path'] ?? '');
			}
			$report['checks'] = array_merge(
				$report['checks'],
				self::runMetadataChecks(
					$config['plugin_root'],
					$metadata,
					$report['git'],
					!empty($config['allow_dirty']),
					!empty($config['dev_build'])
				)
			);

			if (!self::hasRequiredFailures($report['checks'])) {
				$report['checks'] = array_merge(
					$report['checks'],
					self::runReleaseRegressionChecks(
						$config['plugin_root'],
						$config['release_tests']
					)
				);
			}

			if (!self::hasRequiredFailures($report['checks'])) {
				$tempBuildDir = self::createTemporaryDirectory('vms-public-release-');
				$temporaryPaths[] = $tempBuildDir;
				$stagedRoot = $tempBuildDir . DIRECTORY_SEPARATOR . $metadata['public_plugin_slug'];
				$manifestPatterns = self::loadExcludeManifest($metadata['exclude_manifest']);

				$stageResult = self::stagePluginTree(
					$config['plugin_root'],
					$stagedRoot,
					$manifestPatterns,
					$provenanceManifest['files_by_path'] ?? array()
				);
				$report['checks'][] = self::check(
					'stage-source-tree',
					empty($stageResult['symlinks']) ? 'PASS' : 'FAIL',
					'Stage public-release source tree',
					empty($stageResult['symlinks'])
						? sprintf(
							'Staged %d files while honoring %s.',
							(int) $stageResult['file_count'],
							basename($metadata['exclude_manifest'])
						)
						: 'Encountered symlink entries that would make the package non-portable.',
					array(
						'required' => true,
						'details' => empty($stageResult['symlinks']) ? array() : array(
							'symlink_paths' => array_values($stageResult['symlinks']),
						),
					)
				);

				$report['checks'] = array_merge(
					$report['checks'],
					self::runSyntaxChecks($stagedRoot)
				);

				$directoryValidation = self::validateTarget(
					$stagedRoot,
					array(
						'plugin_slug' => $metadata['public_plugin_slug'],
						'manifest_path' => $metadata['exclude_manifest'],
					)
				);
				$report['checks'] = array_merge($report['checks'], $directoryValidation['checks']);
				if (is_array($provenanceManifest)) {
					$report['checks'] = array_merge(
						$report['checks'],
						self::runProvenanceSourceChecks($stagedRoot, $provenanceManifest, $metadata)
					);
				}

				$report['checks'] = array_merge(
					$report['checks'],
					self::runOptionalWpLoadSmokes(
						$config['plugin_root'],
						$stagedRoot,
						$metadata['public_plugin_slug']
					)
				);

				$report['checks'][] = self::check(
					'activation-portability-review',
					'WARN',
					'Activation-hook portability review',
					'The default pipeline does not execute activation hooks because that would mutate a WordPress site. Use a disposable site for any activation-hook smoke test.',
					array('required' => false)
				);

				if (!self::hasRequiredFailures($report['checks'])) {
					self::buildZipArtifact(
						$stagedRoot,
						$report['artifact']['zip_path'],
						isset($provenanceManifest['artifact']['root_mtime_unix']) ? (int) $provenanceManifest['artifact']['root_mtime_unix'] : null
					);
					$report['artifact']['created'] = true;
					$report['artifact']['size_bytes'] = filesize($report['artifact']['zip_path']) ?: 0;
					$report['artifact']['sha256'] = hash_file('sha256', $report['artifact']['zip_path']) ?: '';

					$zipValidation = self::validateTarget(
						$report['artifact']['zip_path'],
						array(
							'plugin_slug' => $metadata['public_plugin_slug'],
							'manifest_path' => $metadata['exclude_manifest'],
						)
					);
					$report['checks'] = array_merge($report['checks'], $zipValidation['checks']);
					if (is_array($provenanceManifest)) {
						$report['checks'] = array_merge(
							$report['checks'],
							self::runProvenanceArtifactChecks($report['artifact']['zip_path'], $provenanceManifest)
						);
					}
				}
			}

			$report['finished_at_utc'] = gmdate('c');
			$report['status'] = self::computeOverallStatus($report['checks']);
			$report['warnings'] = self::collectWarnings($report['checks']);
			$report['skipped_checks'] = self::collectByStatus($report['checks'], 'SKIP');

			self::writeReportFiles($report, $config['force']);

			return $report;
		} catch (Throwable $throwable) {
			$report['finished_at_utc'] = gmdate('c');
			$report['checks'][] = self::check(
				'build-exception',
				'FAIL',
				'Unhandled release build exception',
				self::sanitizeExceptionMessage($throwable->getMessage()),
				array('required' => true)
			);
			$report['status'] = 'FAIL';
			$report['warnings'] = self::collectWarnings($report['checks']);
			$report['skipped_checks'] = self::collectByStatus($report['checks'], 'SKIP');

			try {
				self::writeReportFiles($report, $config['force']);
			} catch (Throwable $reportThrowable) {
				$report['report_write_error'] = self::sanitizeExceptionMessage($reportThrowable->getMessage());
			}

			return $report;
		} finally {
			foreach ($temporaryPaths as $temporaryPath) {
				self::deletePath($temporaryPath);
			}
		}
	}

	public static function validateTarget(string $target, array $options = array()): array
	{
		$pluginSlug = self::normalizeSlug((string) ($options['plugin_slug'] ?? self::publicPluginSlug()));
		$manifestPath = isset($options['manifest_path']) && is_string($options['manifest_path']) && $options['manifest_path'] !== ''
			? $options['manifest_path']
			: dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'release-public-excludes.txt';
		$manifestPatterns = self::loadExcludeManifest($manifestPath);

		$checks = array();
		if (!file_exists($target)) {
			$checks[] = self::check(
				'target-readable',
				'FAIL',
				'Target exists and is readable',
				'Target not found: ' . basename($target),
				array('required' => true)
			);

			return array(
				'status' => 'FAIL',
				'checks' => $checks,
			);
		}

		$checks[] = self::check(
			'target-readable',
			is_readable($target) ? 'PASS' : 'FAIL',
			'Target exists and is readable',
			is_readable($target)
				? 'Validated target: ' . basename($target)
				: 'Target is not readable: ' . basename($target),
			array('required' => true)
		);

		if (is_dir($target)) {
			$packageRoot = self::resolvePackageDirectoryRoot($target, $pluginSlug);
			$checks = array_merge(
				$checks,
				self::validateDirectoryPackageRoot($packageRoot, $pluginSlug, $manifestPatterns)
			);
		} else {
			$checks = array_merge(
				$checks,
				self::validateZipArchive($target, $pluginSlug, $manifestPatterns)
			);
		}

		return array(
			'status' => self::hasRequiredFailures($checks) ? 'FAIL' : 'PASS',
			'checks' => $checks,
		);
	}

	public static function verifyProvenanceTarget(string $target, string $manifestPath): array
	{
		$manifest = self::loadProvenanceManifest($manifestPath);
		$slug = (string) ($manifest['slug'] ?? self::publicPluginSlug());
		$checks = array();
		$temporaryPaths = array();

		try {
			if (!file_exists($target)) {
				return array(
					'status' => 'FAIL',
					'checks' => array(
						self::check(
							'provenance-target-readable',
							'FAIL',
							'Provenance verification target exists',
							'Target not found: ' . basename($target),
							array('required' => true)
						),
					),
				);
			}

			$checks[] = self::check(
				'provenance-target-readable',
				is_readable($target) ? 'PASS' : 'FAIL',
				'Provenance verification target exists',
				is_readable($target)
					? 'Validated target: ' . basename($target)
					: 'Target is not readable: ' . basename($target),
				array('required' => true)
			);

			$packageRoot = $target;
			if (is_file($target)) {
				if (!class_exists('ZipArchive')) {
					$checks[] = self::check(
						'provenance-zip-support',
						'FAIL',
						'ZipArchive is available',
						'ZipArchive is required to verify provenance against a ZIP artifact.',
						array('required' => true)
					);

					return array(
						'status' => self::hasRequiredFailures($checks) ? 'FAIL' : 'PASS',
						'checks' => $checks,
					);
				}

				$tempDir = self::createTemporaryDirectory('vms-provenance-verify-');
				$temporaryPaths[] = $tempDir;
				$zip = new ZipArchive();
				$result = $zip->open($target);
				if ($result !== true) {
					$checks[] = self::check(
						'provenance-zip-readable',
						'FAIL',
						'ZIP opens for provenance verification',
						'Could not open the ZIP archive.',
						array('required' => true)
					);

					return array(
						'status' => self::hasRequiredFailures($checks) ? 'FAIL' : 'PASS',
						'checks' => $checks,
					);
				}
				if (!$zip->extractTo($tempDir)) {
					$zip->close();
					$checks[] = self::check(
						'provenance-zip-extractable',
						'FAIL',
						'ZIP extracts for provenance verification',
						'Could not extract the ZIP archive for provenance verification.',
						array('required' => true)
					);

					return array(
						'status' => self::hasRequiredFailures($checks) ? 'FAIL' : 'PASS',
						'checks' => $checks,
					);
				}
				$zip->close();
				$packageRoot = self::resolvePackageDirectoryRoot($tempDir, $slug);
				$checks = array_merge($checks, self::runProvenanceArtifactChecks($target, $manifest));
			} else {
				$packageRoot = self::resolvePackageDirectoryRoot($target, $slug);
			}

			$metadata = self::collectSourceMetadata($packageRoot);
			$checks = array_merge($checks, self::runProvenanceSourceChecks($packageRoot, $manifest, $metadata));

			return array(
				'status' => self::hasRequiredFailures($checks) ? 'FAIL' : 'PASS',
				'checks' => $checks,
			);
		} finally {
			foreach ($temporaryPaths as $temporaryPath) {
				self::deletePath($temporaryPath);
			}
		}
	}

	public static function renderTextReport(array $report): string
	{
		$lines = array();
		$lines[] = 'Status: ' . (string) ($report['status'] ?? 'FAIL');
		$lines[] = 'Artifact: ' . (string) (($report['artifact']['filename'] ?? null) ?: 'not created');
		$lines[] = 'Artifact Created: ' . (!empty($report['artifact']['created']) ? 'yes' : 'no');
		$lines[] = 'Artifact Size: ' . self::formatSize((int) ($report['artifact']['size_bytes'] ?? 0));
		$lines[] = 'SHA-256: ' . (string) (($report['artifact']['sha256'] ?? '') !== '' ? $report['artifact']['sha256'] : 'n/a');
		$lines[] = 'Build Timestamp (UTC): ' . (string) ($report['finished_at_utc'] ?? ($report['started_at_utc'] ?? 'n/a'));
		$lines[] = 'Plugin Version: ' . (string) (($report['metadata']['version'] ?? '') !== '' ? $report['metadata']['version'] : 'unknown');
		$lines[] = 'Plugin Slug: ' . (string) (($report['metadata']['slug'] ?? '') !== '' ? $report['metadata']['slug'] : 'unknown');
		$lines[] = 'Git Commit: ' . (string) (($report['git']['commit'] ?? '') !== '' ? $report['git']['commit'] : 'n/a');
		$lines[] = 'Git State: ' . (string) (($report['git']['state'] ?? '') !== '' ? $report['git']['state'] : 'unknown');
		$lines[] = 'Exclude Manifest: ' . basename((string) ($report['metadata']['exclude_manifest'] ?? 'release-public-excludes.txt'));
		$lines[] = '';
		$lines[] = 'Checks:';

		foreach ((array) ($report['checks'] ?? array()) as $check) {
			$status = (string) ($check['status'] ?? 'FAIL');
			$label = (string) ($check['label'] ?? ($check['id'] ?? 'check'));
			$message = (string) ($check['message'] ?? '');
			$lines[] = sprintf('- [%s] %s: %s', $status, $label, $message);
		}

		$warnings = (array) ($report['warnings'] ?? array());
		$lines[] = '';
		$lines[] = 'Warnings Requiring Manual Review:';
		if ($warnings === array()) {
			$lines[] = '- none';
		} else {
			foreach ($warnings as $warning) {
				$lines[] = '- ' . (string) ($warning['label'] ?? $warning['id'] ?? 'warning') . ': ' . (string) ($warning['message'] ?? '');
			}
		}

		$skipped = (array) ($report['skipped_checks'] ?? array());
		$lines[] = '';
		$lines[] = 'Skipped Checks:';
		if ($skipped === array()) {
			$lines[] = '- none';
		} else {
			foreach ($skipped as $skip) {
				$lines[] = '- ' . (string) ($skip['label'] ?? $skip['id'] ?? 'skip') . ': ' . (string) ($skip['message'] ?? '');
			}
		}

		return implode(PHP_EOL, $lines) . PHP_EOL;
	}

	public static function renderJsonReport(array $report): string
	{
		$json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
		if (!is_string($json)) {
			throw new RuntimeException('Failed to encode release report JSON.');
		}

		return $json . PHP_EOL;
	}

	public static function defaultReleaseTests(): array
	{
		return array(
			array(
				'id' => 'admissions-rest-permissions',
				'label' => 'Admissions REST permission regression',
				'path' => 'tests/admissions-rest-permissions.php',
				'required' => true,
			),
			array(
				'id' => 'ticket-claims-assignee-validation',
				'label' => 'Qualified-ticket assignee validation regression',
				'path' => 'tests/ticket-claims-assignee-validation.php',
				'required' => true,
			),
			array(
				'id' => 'ticket-checkout-safety-hardening',
				'label' => 'Ticket checkout safety regression',
				'path' => 'tests/ticket-checkout-safety-hardening.php',
				'required' => true,
			),
			array(
				'id' => 'event-plan-legacy-ticketing-integration-smoke',
				'label' => 'Legacy ticketing smoke regression',
				'path' => 'tests/event-plan-legacy-ticketing-integration-smoke.php',
				'required' => true,
			),
			array(
				'id' => 'event-plan-ticket-ui-overrides-isolated',
				'label' => 'Ticket UI isolation regression',
				'path' => 'tests/event-plan-ticket-ui-overrides-isolated.php',
				'required' => true,
			),
		);
	}

	private static function normalizeBuildConfig(array $config): array
	{
		$pluginRoot = isset($config['plugin_root']) && is_string($config['plugin_root']) && $config['plugin_root'] !== ''
			? $config['plugin_root']
			: dirname(__DIR__, 2);
		$pluginRoot = rtrim(self::realpathOrOriginal($pluginRoot), DIRECTORY_SEPARATOR);
		$outputDir = isset($config['output_dir']) && is_string($config['output_dir']) && $config['output_dir'] !== ''
			? $config['output_dir']
			: $pluginRoot . DIRECTORY_SEPARATOR . 'dist';
		$outputDir = self::normalizePath($outputDir);
		$provenanceManifestPath = isset($config['provenance_manifest_path']) && is_string($config['provenance_manifest_path']) && $config['provenance_manifest_path'] !== ''
			? self::normalizePath($config['provenance_manifest_path'])
			: '';

		return array(
			'plugin_root' => $pluginRoot,
			'output_dir' => $outputDir,
			'provenance_manifest_path' => $provenanceManifestPath,
			'force' => !empty($config['force']),
			'allow_dirty' => !empty($config['allow_dirty']),
			'dev_build' => !empty($config['dev_build']),
			'release_tests' => isset($config['release_tests']) && is_array($config['release_tests'])
				? $config['release_tests']
				: self::defaultReleaseTests(),
		);
	}

	private static function publicPluginSlug(): string
	{
		return self::normalizeSlug(self::PUBLIC_PLUGIN_SLUG);
	}

	private static function collectSourceMetadata(string $pluginRoot): array
	{
		$entryFile = $pluginRoot . DIRECTORY_SEPARATOR . 'vendor-management-system.php';
		$constantsFile = $pluginRoot . DIRECTORY_SEPARATOR . 'includes/core/registry/constants.php';
		$buildFile = $pluginRoot . DIRECTORY_SEPARATOR . 'vms-build.txt';
		$migrationsFile = $pluginRoot . DIRECTORY_SEPARATOR . 'includes/db/migrations.php';
		$excludeManifest = $pluginRoot . DIRECTORY_SEPARATOR . 'release-public-excludes.txt';
		$header = self::readPluginHeader($entryFile);
		$internalSlug = self::extractDefineValue($constantsFile, 'VMS_PLUGIN_SLUG') ?? basename($pluginRoot);
		$publicSlug = self::publicPluginSlug();
		$version = self::extractDefineValue($constantsFile, 'VMS_VERSION') ?? '';
		$buildVersion = is_readable($buildFile) ? trim((string) file_get_contents($buildFile)) : '';
		$migrationInfo = self::inspectVendorCoreMigrations($migrationsFile);
		$buildNotesFile = $pluginRoot . DIRECTORY_SEPARATOR . 'BUILD-NOTES-' . $version . '.md';
		$wpRoot = self::detectWordPressRoot($pluginRoot);

		return array(
			'entry_file' => $entryFile,
			'constants_file' => $constantsFile,
			'build_file' => $buildFile,
			'migrations_file' => $migrationsFile,
			'exclude_manifest' => $excludeManifest,
			'slug' => $publicSlug,
			'internal_plugin_slug' => self::normalizeSlug($internalSlug),
			'public_plugin_slug' => $publicSlug,
			'public_plugin_basename' => $publicSlug . '/vendor-management-system.php',
			'header_version' => (string) ($header['Version'] ?? ''),
			'header_text_domain' => (string) ($header['Text Domain'] ?? ''),
			'header_requires_php' => (string) ($header['Requires PHP'] ?? ''),
			'header_requires_wp' => (string) ($header['Requires at least'] ?? ''),
			'version' => $version,
			'build_version' => $buildVersion,
			'build_notes_file' => $buildNotesFile,
			'build_notes_exists' => is_readable($buildNotesFile),
			'migration' => $migrationInfo,
			'wp_root' => $wpRoot,
			'readme_file' => self::locateReadmeFile($pluginRoot),
		);
	}

	private static function buildInitialReport(array $config, array $metadata): array
	{
		$baseName = $metadata['public_plugin_slug'] . '-' . (($metadata['version'] !== '') ? $metadata['version'] : 'unknown-version') . '-public-release';
		if (!empty($config['dev_build'])) {
			$baseName .= '-dev';
		}

		return array(
			'status' => 'FAIL',
			'started_at_utc' => gmdate('c'),
			'finished_at_utc' => null,
			'metadata' => $metadata,
			'git' => array(
				'available' => false,
				'state' => 'unknown',
				'commit' => '',
				'repo_root' => '',
			),
			'artifact' => array(
				'created' => false,
				'filename' => $baseName . '.zip',
				'zip_path' => $config['output_dir'] . DIRECTORY_SEPARATOR . $baseName . '.zip',
				'report_json_path' => $config['output_dir'] . DIRECTORY_SEPARATOR . $baseName . '.report.json',
				'report_text_path' => $config['output_dir'] . DIRECTORY_SEPARATOR . $baseName . '.report.txt',
				'size_bytes' => 0,
				'sha256' => '',
			),
			'checks' => array(),
			'warnings' => array(),
			'skipped_checks' => array(),
		);
	}

	private static function assertWritableOutputTargets(array $config, array $report): void
	{
		if (!is_dir($config['output_dir']) && !mkdir($config['output_dir'], 0775, true) && !is_dir($config['output_dir'])) {
			throw new RuntimeException('Could not create output directory.');
		}

		if (!is_writable($config['output_dir'])) {
			throw new RuntimeException('Output directory is not writable.');
		}

		foreach (array(
			$report['artifact']['zip_path'],
			$report['artifact']['report_json_path'],
			$report['artifact']['report_text_path'],
		) as $targetPath) {
			if (file_exists($targetPath) && !$config['force']) {
				throw new RuntimeException(
					'Refusing to overwrite existing build artifact without --force: ' . basename((string) $targetPath)
				);
			}
		}

		$outputDirRelative = self::pathRelativeTo($config['output_dir'], $config['plugin_root']);
		if ($outputDirRelative !== null) {
			$manifestPatterns = self::loadExcludeManifest($report['metadata']['exclude_manifest']);
			if (self::firstMatchingPattern($outputDirRelative . '/', $manifestPatterns) === null) {
				throw new RuntimeException(
					'Custom output directories inside the plugin root must be excluded by release-public-excludes.txt.'
				);
			}
		}
	}

	private static function detectGitState(string $pluginRoot): array
	{
		if (!self::commandExists('git')) {
			return array(
				'available' => false,
				'state' => 'unavailable',
				'commit' => '',
				'repo_root' => '',
				'message' => 'git executable is not available.',
			);
		}

		$repoRoot = trim(self::runCommand(array('git', '-C', $pluginRoot, 'rev-parse', '--show-toplevel'))['stdout']);
		if ($repoRoot === '') {
			return array(
				'available' => false,
				'state' => 'unknown',
				'commit' => '',
				'repo_root' => '',
				'message' => 'Source directory is not inside a git worktree.',
			);
		}

		$commit = trim(self::runCommand(array('git', '-C', $pluginRoot, 'rev-parse', 'HEAD'))['stdout']);
		$statusOutput = self::runCommand(array('git', '-C', $pluginRoot, 'status', '--short', '--untracked-files=all'));
		$dirty = trim($statusOutput['stdout']) !== '';

		return array(
			'available' => true,
			'state' => $dirty ? 'dirty' : 'clean',
			'commit' => $commit,
			'repo_root' => $repoRoot,
			'message' => $dirty
				? 'Git worktree contains uncommitted or untracked changes.'
				: 'Git worktree is clean.',
		);
	}

	private static function runMetadataChecks(
		string $pluginRoot,
		array $metadata,
		array $git,
		bool $allowDirty,
		bool $devBuild
	): array
	{
		$checks = array();
		$publicPluginSlug = (string) ($metadata['public_plugin_slug'] ?? '');
		$internalPluginSlug = (string) ($metadata['internal_plugin_slug'] ?? '');
		$versions = array_filter(array(
			'plugin header' => $metadata['header_version'],
			'VMS_VERSION' => $metadata['version'],
			'vms-build.txt' => $metadata['build_version'],
		), static function ($value): bool {
			return trim((string) $value) !== '';
		});
		$uniqueVersions = array_values(array_unique(array_values($versions)));
		$checks[] = self::check(
			'version-consistency',
			(count($versions) === 3 && count($uniqueVersions) === 1) ? 'PASS' : 'FAIL',
			'Version marker consistency',
			(count($versions) === 3 && count($uniqueVersions) === 1)
				? 'Plugin header version, VMS_VERSION, and vms-build.txt are synchronized.'
				: 'Version markers are missing or inconsistent.',
			array(
				'required' => true,
				'details' => array('versions' => $versions),
			)
		);

		$checks[] = self::check(
			'slug-and-text-domain',
			($publicPluginSlug !== '' && $metadata['header_text_domain'] === $publicPluginSlug) ? 'PASS' : 'FAIL',
			'Plugin slug and text domain assumptions',
			($publicPluginSlug !== '' && $metadata['header_text_domain'] === $publicPluginSlug)
				? 'Public package slug and text domain both resolve to `' . $publicPluginSlug . '`.'
				: 'Plugin slug/text domain assumptions do not line up with the package root.',
			array(
				'required' => true,
				'details' => array(
					'public_plugin_slug' => $publicPluginSlug,
					'internal_plugin_slug' => $internalPluginSlug,
					'text_domain' => $metadata['header_text_domain'],
					'public_package_root' => $publicPluginSlug,
					'source_checkout_root' => basename($pluginRoot),
				),
			)
		);

		$migration = (array) ($metadata['migration'] ?? array());
		$latestFunction = (string) ($migration['latest_function'] ?? '');
		$activationCallsLatest = !empty($migration['activation_calls_latest']);
		$checks[] = self::check(
			'vendor-core-migration-consistency',
			($latestFunction !== '' && $activationCallsLatest) ? 'PASS' : 'FAIL',
			'Vendor-core migration consistency',
			($latestFunction !== '' && $activationCallsLatest)
				? 'Activation bootstrap calls the latest vendor-core migration `' . $latestFunction . '` without forcing schema versions to equal the plugin version.'
				: 'Activation bootstrap does not point at the latest vendor-core migration definition.',
			array(
				'required' => true,
				'details' => $migration,
			)
		);

		$checks[] = self::check(
			'build-notes-for-version',
			!empty($metadata['build_notes_exists']) ? 'PASS' : 'WARN',
			'Versioned build notes present',
			!empty($metadata['build_notes_exists'])
				? 'Found ' . basename((string) $metadata['build_notes_file']) . '.'
				: 'No version-matched BUILD-NOTES file was found for the current plugin version.',
			array('required' => false)
		);

		$checks[] = self::check(
			'readme-stable-tag',
			self::readmeStableTagStatus($metadata),
			'Readme stable tag consistency',
			self::readmeStableTagMessage($metadata),
			array('required' => false)
		);

		$checks[] = self::check(
			'plugin-header-requirements',
			(($metadata['header_requires_php'] !== '') && ($metadata['header_requires_wp'] !== '')) ? 'PASS' : 'WARN',
			'Minimum WordPress and PHP requirements declared',
			(($metadata['header_requires_php'] !== '') && ($metadata['header_requires_wp'] !== ''))
				? 'Plugin header declares both `Requires PHP` and `Requires at least`.'
				: 'Plugin header is missing one or both minimum-requirement fields. Manual review is required before public release.',
			array(
				'required' => false,
				'details' => array(
					'requires_php' => $metadata['header_requires_php'],
					'requires_wp' => $metadata['header_requires_wp'],
				),
			)
		);

		$checks[] = self::gitSafetyCheck($git, $allowDirty, $devBuild);

		return $checks;
	}

	private static function gitSafetyCheck(array $git, bool $allowDirty, bool $devBuild): array
	{
		if (!empty($git['available']) && ($git['state'] ?? '') === 'dirty') {
			if ($allowDirty) {
				return self::check(
					'git-dirty-policy',
					'WARN',
					'Dirty worktree policy',
					'Git worktree is dirty, and --allow-dirty explicitly permitted a flagged public artifact.',
					array('required' => false)
				);
			}

			if ($devBuild) {
				return self::check(
					'git-dirty-policy',
					'WARN',
					'Dirty worktree policy',
					'Git worktree is dirty, but --dev-build allows a flagged development artifact.',
					array('required' => false)
				);
			}

			return self::check(
				'git-dirty-policy',
				'FAIL',
				'Dirty worktree policy',
				'Git worktree is dirty. Use --allow-dirty only when you intentionally want a flagged release artifact.',
				array('required' => true)
			);
		}

		if (!empty($git['available']) && ($git['state'] ?? '') === 'clean') {
			return self::check(
				'git-dirty-policy',
				'PASS',
				'Dirty worktree policy',
				'Git worktree is clean.',
				array('required' => true)
			);
		}

		return self::check(
			'git-dirty-policy',
			'WARN',
			'Dirty worktree policy',
			'Git metadata is unavailable in this workspace, so the report marks the tree state as unknown.',
			array('required' => false)
		);
	}

	private static function runReleaseRegressionChecks(string $pluginRoot, array $tests): array
	{
		$checks = array();
		$extraEnv = array();
		$wpRoot = self::detectWordPressRoot($pluginRoot);
		if ($wpRoot !== null) {
			$extraEnv['VMS_TEST_WORDPRESS_ROOT'] = $wpRoot;
			$extraEnv['VMS_TEST_WP_LOAD'] = $wpRoot . DIRECTORY_SEPARATOR . 'wp-load.php';
		}

		foreach ($tests as $test) {
			$relativePath = (string) ($test['path'] ?? '');
			$label = (string) ($test['label'] ?? $relativePath);
			$required = !array_key_exists('required', $test) || !empty($test['required']);
			$scriptPath = $pluginRoot . DIRECTORY_SEPARATOR . ltrim($relativePath, '/');

			if (!is_readable($scriptPath)) {
				$checks[] = self::check(
					'test-' . (string) ($test['id'] ?? md5($relativePath)),
					$required ? 'FAIL' : 'SKIP',
					$label,
					$required
						? 'Required regression script is missing.'
						: 'Optional regression script is not present in this workspace.',
					array(
						'required' => $required,
						'details' => array('path' => $relativePath),
					)
				);
				continue;
			}

			$result = self::runCommand(array(self::phpBinary(), $scriptPath), $pluginRoot, $extraEnv);
			$checks[] = self::check(
				'test-' . (string) ($test['id'] ?? md5($relativePath)),
				$result['exit_code'] === 0 ? 'PASS' : 'FAIL',
				$label,
				$result['exit_code'] === 0
					? self::firstNonEmptyLine($result['stdout'], 'Regression check passed.')
					: self::firstNonEmptyLine($result['stderr'] . PHP_EOL . $result['stdout'], 'Regression check failed.'),
				array(
					'required' => $required,
					'details' => array('path' => $relativePath),
				)
			);
		}

		return $checks;
	}

	private static function runSyntaxChecks(string $stagedRoot): array
	{
		$checks = array();
		$phpFailures = array();
		$phpFiles = self::listFilesByExtension($stagedRoot, array('php'));
		foreach ($phpFiles as $absolutePath) {
			$result = self::runCommand(array(self::phpBinary(), '-l', $absolutePath), $stagedRoot);
			if ($result['exit_code'] !== 0) {
				$phpFailures[] = self::pathRelativeToOrBasename($absolutePath, $stagedRoot) . ': ' . self::firstNonEmptyLine($result['stderr'] . PHP_EOL . $result['stdout'], 'PHP lint failed.');
			}
		}
		$checks[] = self::check(
			'php-syntax-lint',
			$phpFailures === array() ? 'PASS' : 'FAIL',
			'PHP syntax lint on staged distributable files',
			$phpFailures === array()
				? sprintf('PHP lint passed for %d staged PHP files.', count($phpFiles))
				: 'PHP syntax lint failed for one or more staged files.',
			array(
				'required' => true,
				'details' => $phpFailures === array() ? array() : array('failures' => $phpFailures),
			)
		);

		if (!self::commandExists('node')) {
			$checks[] = self::check(
				'js-syntax-lint',
				'SKIP',
				'JavaScript syntax lint on staged distributable files',
				'Node.js is not available in this environment.',
				array('required' => false)
			);

			return $checks;
		}

		$jsFailures = array();
		$jsFiles = self::listFilesByExtension($stagedRoot, array('js'));
		foreach ($jsFiles as $absolutePath) {
			$result = self::runCommand(array('node', '--check', $absolutePath), $stagedRoot);
			if ($result['exit_code'] !== 0) {
				$jsFailures[] = self::pathRelativeToOrBasename($absolutePath, $stagedRoot) . ': ' . self::firstNonEmptyLine($result['stderr'] . PHP_EOL . $result['stdout'], 'JavaScript syntax check failed.');
			}
		}
		$checks[] = self::check(
			'js-syntax-lint',
			$jsFailures === array() ? 'PASS' : 'FAIL',
			'JavaScript syntax lint on staged distributable files',
			$jsFailures === array()
				? sprintf('Node syntax checks passed for %d staged JavaScript files.', count($jsFiles))
				: 'JavaScript syntax lint failed for one or more staged files.',
			array(
				'required' => true,
				'details' => $jsFailures === array() ? array() : array('failures' => $jsFailures),
			)
		);

		return $checks;
	}

	private static function runOptionalWpLoadSmokes(string $pluginRoot, string $stagedRoot, string $pluginSlug): array
	{
		$checks = array();
		$wpRoot = self::detectWordPressRoot($pluginRoot);
		if ($wpRoot === null) {
			foreach (self::OPTIONAL_LOAD_SMOKE_SCENARIOS as $scenario) {
				$checks[] = self::check(
					(string) $scenario['id'],
					'SKIP',
					(string) $scenario['label'],
					'Could not auto-detect a WordPress root for non-destructive WP-CLI load smokes.',
					array('required' => false)
				);
			}

			return $checks;
		}

		if (!self::commandExists('wp')) {
			foreach (self::OPTIONAL_LOAD_SMOKE_SCENARIOS as $scenario) {
				$checks[] = self::check(
					(string) $scenario['id'],
					'SKIP',
					(string) $scenario['label'],
					'WP-CLI is not available in this environment.',
					array('required' => false)
				);
			}

			return $checks;
		}

		$tempScript = tempnam(sys_get_temp_dir(), 'vms-load-smoke-');
		if ($tempScript === false) {
			foreach (self::OPTIONAL_LOAD_SMOKE_SCENARIOS as $scenario) {
				$checks[] = self::check(
					(string) $scenario['id'],
					'SKIP',
					(string) $scenario['label'],
					'Could not create a temporary WP-CLI smoke script.',
					array('required' => false)
				);
			}

			return $checks;
		}

		try {
			$pluginEntryFile = $stagedRoot . DIRECTORY_SEPARATOR . 'vendor-management-system.php';
			$scriptContents = <<<'PHP'
<?php
declare(strict_types=1);

$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';

$pluginFile = getenv('VMS_PUBLIC_RELEASE_PLUGIN_FILE');
if (!is_string($pluginFile) || $pluginFile === '' || !is_readable($pluginFile)) {
	fwrite(STDERR, "Staged plugin file is not readable.\n");
	exit(1);
}

require_once $pluginFile;
echo "LOAD_SMOKE_OK\n";
PHP;
			file_put_contents($tempScript, $scriptContents);

			foreach (self::OPTIONAL_LOAD_SMOKE_SCENARIOS as $scenario) {
				$skipPlugins = array_values(array_unique(array_map('trim', (array) ($scenario['skip_plugins'] ?? array()))));
				if (!in_array($pluginSlug, $skipPlugins, true)) {
					$skipPlugins[] = $pluginSlug;
				}

				$result = self::runCommand(
					array(
						'wp',
						'--path=' . $wpRoot,
						'--skip-plugins=' . implode(',', $skipPlugins),
						'eval-file',
						$tempScript,
					),
					$wpRoot,
					array(
						'VMS_PUBLIC_RELEASE_PLUGIN_FILE' => $pluginEntryFile,
					)
				);

				$checks[] = self::check(
					(string) $scenario['id'],
					($result['exit_code'] === 0 && strpos($result['stdout'], 'LOAD_SMOKE_OK') !== false) ? 'PASS' : 'FAIL',
					(string) $scenario['label'],
					($result['exit_code'] === 0 && strpos($result['stdout'], 'LOAD_SMOKE_OK') !== false)
						? 'Staged plugin loaded without an immediate fatal while skipping: ' . implode(', ', $skipPlugins)
						: self::firstNonEmptyLine($result['stderr'] . PHP_EOL . $result['stdout'], 'WP-CLI load smoke failed.'),
					array(
						'required' => true,
						'details' => array('skip_plugins' => $skipPlugins),
					)
				);
			}
		} finally {
			@unlink($tempScript);
		}

		return $checks;
	}

	private static function runProvenanceSourceChecks(string $packageRoot, array $manifest, array $metadata): array
	{
		$checks = array();
		$expectedVersion = (string) ($manifest['version'] ?? '');
		$expectedSlug = (string) ($manifest['slug'] ?? '');
		$actualVersion = (string) ($metadata['version'] ?? '');
		$actualHeaderVersion = (string) ($metadata['header_version'] ?? '');
		$actualSlug = (string) ($metadata['public_plugin_slug'] ?? '');

		$checks[] = self::check(
			'provenance-manifest-metadata',
			(
				($expectedVersion === '' || ($actualVersion === $expectedVersion && $actualHeaderVersion === $expectedVersion))
				&& ($expectedSlug === '' || $actualSlug === $expectedSlug)
			) ? 'PASS' : 'FAIL',
			'Provenance manifest metadata matches source tree',
			(
				($expectedVersion === '' || ($actualVersion === $expectedVersion && $actualHeaderVersion === $expectedVersion))
				&& ($expectedSlug === '' || $actualSlug === $expectedSlug)
			)
				? 'Provenance manifest version and slug match the current source tree.'
				: 'Provenance manifest metadata does not match the current source tree.',
			array(
				'required' => true,
				'details' => array(
					'expected_version' => $expectedVersion,
					'actual_version' => $actualVersion,
					'actual_header_version' => $actualHeaderVersion,
					'expected_slug' => $expectedSlug,
					'actual_slug' => $actualSlug,
				),
			)
		);

		$comparison = self::comparePackageToProvenance(
			(array) ($manifest['files_by_path'] ?? array()),
			self::collectPackageFileManifest($packageRoot)
		);

		$checks[] = self::check(
			'provenance-source-match',
			self::provenanceComparisonHasDiffs($comparison) ? 'FAIL' : 'PASS',
			'Packaged source matches provenance manifest',
			self::provenanceComparisonHasDiffs($comparison)
				? 'Packaged source differs from the provenance manifest.'
				: sprintf('Packaged source matches %d provenance-tracked files.', count((array) ($manifest['files_by_path'] ?? array()))),
			array(
				'required' => true,
				'details' => self::provenanceComparisonDetails($comparison),
			)
		);

		return $checks;
	}

	private static function runProvenanceArtifactChecks(string $zipPath, array $manifest): array
	{
		$expectedFilename = (string) (($manifest['artifact']['filename'] ?? '') ?: '');
		$expectedSha = strtolower((string) (($manifest['artifact']['sha256'] ?? '') ?: ''));
		$actualFilename = basename($zipPath);
		$actualSha = strtolower((string) (hash_file('sha256', $zipPath) ?: ''));
		$checks = array();

		$checks[] = self::check(
			'provenance-artifact-filename',
			($expectedFilename === '' || $expectedFilename === $actualFilename) ? 'PASS' : 'FAIL',
			'Built artifact filename matches provenance manifest',
			($expectedFilename === '' || $expectedFilename === $actualFilename)
				? 'Built artifact filename matches the provenance manifest.'
				: 'Built artifact filename does not match the provenance manifest.',
			array(
				'required' => true,
				'details' => array(
					'expected_filename' => $expectedFilename,
					'actual_filename' => $actualFilename,
				),
			)
		);

		$checks[] = self::check(
			'provenance-artifact-sha256',
			($expectedSha !== '' && hash_equals($expectedSha, $actualSha)) ? 'PASS' : 'FAIL',
			'Built artifact SHA-256 matches provenance manifest',
			($expectedSha !== '' && hash_equals($expectedSha, $actualSha))
				? 'Built artifact SHA-256 matches the provenance manifest.'
				: 'Built artifact SHA-256 does not match the provenance manifest.',
			array(
				'required' => true,
				'details' => array(
					'expected_sha256' => $expectedSha,
					'actual_sha256' => $actualSha,
				),
			)
		);

		return $checks;
	}

	private static function stagePluginTree(string $pluginRoot, string $stagedRoot, array $manifestPatterns, array $provenanceFiles = array()): array
	{
		$excludedEntries = array();
		$symlinkEntries = array();
		$fileCount = 0;

		if (!mkdir($stagedRoot, 0775, true) && !is_dir($stagedRoot)) {
			throw new RuntimeException('Could not create staged build directory.');
		}

		$directoryIterator = new RecursiveDirectoryIterator($pluginRoot, FilesystemIterator::SKIP_DOTS);
		$filter = new RecursiveCallbackFilterIterator(
			$directoryIterator,
			static function (SplFileInfo $fileInfo) use ($pluginRoot, $manifestPatterns, &$excludedEntries, &$symlinkEntries): bool {
				$relativePath = self::pathRelativeToOrBasename($fileInfo->getPathname(), $pluginRoot);
				if ($relativePath === '') {
					return true;
				}

				$matchPath = $fileInfo->isDir() ? $relativePath . '/' : $relativePath;
				$pattern = self::firstMatchingPattern($matchPath, $manifestPatterns);
				if ($pattern !== null) {
					$excludedEntries[] = array(
						'path' => $relativePath,
						'pattern' => $pattern,
					);
					return false;
				}

				if ($fileInfo->isLink()) {
					$symlinkEntries[] = $relativePath;
					return false;
				}

				return true;
			}
		);

		$iterator = new RecursiveIteratorIterator($filter, RecursiveIteratorIterator::SELF_FIRST);
		foreach ($iterator as $fileInfo) {
			/** @var SplFileInfo $fileInfo */
			$sourcePath = $fileInfo->getPathname();
			$relativePath = self::pathRelativeToOrBasename($sourcePath, $pluginRoot);
			if ($relativePath === '') {
				continue;
			}

			$destinationPath = $stagedRoot . DIRECTORY_SEPARATOR . $relativePath;
			if ($fileInfo->isDir()) {
				if (!is_dir($destinationPath) && !mkdir($destinationPath, 0775, true) && !is_dir($destinationPath)) {
					throw new RuntimeException('Could not create staged directory: ' . $relativePath);
				}
				continue;
			}

			$destinationDir = dirname($destinationPath);
			if (!is_dir($destinationDir) && !mkdir($destinationDir, 0775, true) && !is_dir($destinationDir)) {
				throw new RuntimeException('Could not create staged directory: ' . $relativePath);
			}

			if (!copy($sourcePath, $destinationPath)) {
				throw new RuntimeException('Could not copy staged file: ' . $relativePath);
			}

			$mtime = isset($provenanceFiles[$relativePath]['mtime_unix'])
				? (int) $provenanceFiles[$relativePath]['mtime_unix']
				: (int) (@filemtime($sourcePath) ?: 0);
			if (is_int($mtime) && $mtime > 0) {
				@touch($destinationPath, $mtime);
			}
			@chmod($destinationPath, 0644);
			$fileCount++;
		}

		return array(
			'excluded' => $excludedEntries,
			'symlinks' => array_values(array_unique($symlinkEntries)),
			'file_count' => $fileCount,
		);
	}

	private static function buildZipArtifact(string $stagedRoot, string $zipPath, ?int $rootMtimeUnix = null): void
	{
		if (!class_exists('ZipArchive')) {
			throw new RuntimeException('ZipArchive is required to create public-release artifacts.');
		}

		$zip = new ZipArchive();
		$result = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
		if ($result !== true) {
			throw new RuntimeException('Could not open zip artifact for writing.');
		}

		$slug = basename($stagedRoot);
		$zip->addEmptyDir($slug);
		if (method_exists($zip, 'setMtimeName') && is_int($rootMtimeUnix) && $rootMtimeUnix > 0) {
			$zip->setMtimeName($slug . '/', $rootMtimeUnix);
		}

		$files = self::listAllFiles($stagedRoot);
		sort($files, SORT_STRING);
		foreach ($files as $absolutePath) {
			$relativePath = self::pathRelativeToOrBasename($absolutePath, $stagedRoot);
			$zipPathName = $slug . '/' . $relativePath;
			if (!$zip->addFile($absolutePath, $zipPathName)) {
				$zip->close();
				throw new RuntimeException('Could not add staged file to zip: ' . $relativePath);
			}
			if (method_exists($zip, 'setMtimeName')) {
				$mtime = @filemtime($absolutePath);
				if (is_int($mtime) && $mtime > 0) {
					$zip->setMtimeName($zipPathName, $mtime);
				}
			}
		}

		$zip->close();
	}

	private static function validateDirectoryPackageRoot(string $packageRoot, string $pluginSlug, array $manifestPatterns): array
	{
		$checks = array();
		$checks[] = self::check(
			'single-top-level-root',
			basename($packageRoot) === $pluginSlug ? 'PASS' : 'FAIL',
			'Single top-level package root',
			basename($packageRoot) === $pluginSlug
				? 'Directory package root resolves to `' . $pluginSlug . '/`.'
				: 'Package directory does not resolve to the expected plugin slug root.',
			array('required' => true)
		);

		$checks = array_merge(
			$checks,
			self::validatePackageContents(
				new ArrayIterator(self::scanDirectoryEntries($packageRoot)),
				$packageRoot,
				$pluginSlug,
				$manifestPatterns,
				false
			)
		);

		return $checks;
	}

	private static function validateZipArchive(string $zipPath, string $pluginSlug, array $manifestPatterns): array
	{
		$checks = array();
		if (!class_exists('ZipArchive')) {
			return array(
				self::check(
					'zip-readable',
					'FAIL',
					'ZIP exists and is readable',
					'ZipArchive extension is not available.',
					array('required' => true)
				),
			);
		}

		$zip = new ZipArchive();
		$result = $zip->open($zipPath);
		if ($result !== true) {
			return array(
				self::check(
					'zip-readable',
					'FAIL',
					'ZIP exists and is readable',
					'Could not open the ZIP archive.',
					array('required' => true)
				),
			);
		}

		try {
			$checks[] = self::check(
				'zip-readable',
				'PASS',
				'ZIP exists and is readable',
				'ZIP archive opened successfully.',
				array('required' => true)
			);

			$entries = array();
			for ($index = 0; $index < $zip->numFiles; $index++) {
				$stat = $zip->statIndex($index);
				if (!is_array($stat) || !isset($stat['name'])) {
					continue;
				}
				$name = str_replace('\\', '/', (string) $stat['name']);
				$entries[] = array(
					'name' => $name,
					'size' => (int) ($stat['size'] ?? 0),
					'is_dir' => substr($name, -1) === '/',
					'index' => $index,
				);
			}

			$checks = array_merge(
				$checks,
				self::validatePackageContents(
					new ArrayIterator($entries),
					$zipPath,
					$pluginSlug,
					$manifestPatterns,
					true,
					$zip
				)
			);
		} finally {
			$zip->close();
		}

		return $checks;
	}

	private static function validatePackageContents(
		Traversable $entries,
		string $rootReference,
		string $pluginSlug,
		array $manifestPatterns,
		bool $isZip,
		?ZipArchive $zip = null
	): array {
		$checks = array();
		$topLevelRoots = array();
		$missingFiles = array();
		$missingDirs = array();
		$manifestViolations = array();
		$pathTraversalViolations = array();
		$localPathViolations = array();
		$localUrlViolations = array();
		$credentialViolations = array();
		$zeroByteRuntimeCode = array();
		$nestedArchives = array();
		$symlinkEntries = array();
		$presentFiles = array();
		$presentDirs = array();

		foreach ($entries as $entry) {
			$name = isset($entry['name']) ? str_replace('\\', '/', (string) $entry['name']) : '';
			if ($name === '') {
				continue;
			}

			if ($isZip) {
				if (!self::isSafeArchivePath($name)) {
					$pathTraversalViolations[] = $name;
					continue;
				}

				$segments = explode('/', trim($name, '/'));
				if ($segments !== array() && $segments[0] !== '') {
					$topLevelRoots[$segments[0]] = true;
				}
			}

			$relativePath = $isZip
				? self::zipRelativePathForSlug($name, $pluginSlug)
				: self::pathRelativeToOrBasename($name, $rootReference);
			if ($relativePath === null) {
				continue;
			}

			$matchPath = !empty($entry['is_dir']) ? $relativePath . '/' : $relativePath;
			$pattern = self::firstMatchingPattern($matchPath, $manifestPatterns);
			if ($pattern !== null) {
				$manifestViolations[] = $matchPath;
			}

			if (!empty($entry['is_dir'])) {
				$presentDirs[rtrim($relativePath, '/')] = true;
				continue;
			}

			$presentFiles[$relativePath] = true;
			self::registerAncestorDirectories($presentDirs, $relativePath);

			if ((int) ($entry['size'] ?? 0) === 0 && preg_match('/\.(php|js)$/i', $relativePath)) {
				$zeroByteRuntimeCode[] = $relativePath;
			}

			if (self::isNestedArchivePath($relativePath)) {
				$nestedArchives[] = $relativePath;
			}

			if ($isZip && $zip instanceof ZipArchive && self::zipEntryIsSymlink($zip, (int) ($entry['index'] ?? -1))) {
				$symlinkEntries[] = $name;
			}

			if (self::shouldScanTextContent($relativePath)) {
				$content = $isZip
					? (string) $zip?->getFromIndex((int) ($entry['index'] ?? -1))
					: (string) @file_get_contents($name);
				if ($content !== '') {
					if (self::containsDeveloperMachinePath($content)) {
						$localPathViolations[] = $relativePath;
					}
					if (self::containsLocalEnvironmentUrl($content)) {
						$localUrlViolations[] = $relativePath;
					}
					if (self::containsCredentialPattern($content)) {
						$credentialViolations[] = $relativePath;
					}
				}
			}
		}

		foreach (self::REQUIRED_RUNTIME_FILES as $requiredFile) {
			if (empty($presentFiles[$requiredFile])) {
				$missingFiles[] = $requiredFile;
			}
		}
		foreach (self::REQUIRED_RUNTIME_DIRECTORIES as $requiredDir) {
			if (empty($presentDirs[$requiredDir])) {
				$missingDirs[] = $requiredDir;
			}
		}

		if ($isZip) {
			$topLevelList = array_keys($topLevelRoots);
			sort($topLevelList, SORT_STRING);
			$checks[] = self::check(
				'single-top-level-root',
				$topLevelList === array($pluginSlug) ? 'PASS' : 'FAIL',
				'Single top-level ZIP directory',
				$topLevelList === array($pluginSlug)
					? 'Package contents are rooted at `' . $pluginSlug . '/` only.'
					: 'Package contains multiple or unexpected top-level roots.',
				array(
					'required' => true,
					'details' => array('top_level_roots' => $topLevelList),
				)
			);
		}

		$checks[] = self::check(
			'entry-file-present',
			empty($missingFiles) ? 'PASS' : 'FAIL',
			'Plugin entry file and required runtime files are present',
			empty($missingFiles)
				? 'All required runtime files are present.'
				: 'One or more required runtime files are missing.',
			array(
				'required' => true,
				'details' => empty($missingFiles) ? array() : array('missing_files' => $missingFiles),
			)
		);

		$checks[] = self::check(
			'required-runtime-directories',
			empty($missingDirs) ? 'PASS' : 'FAIL',
			'Required runtime directories are present',
			empty($missingDirs)
				? 'Required runtime directories are present.'
				: 'One or more required runtime directories are missing.',
			array(
				'required' => true,
				'details' => empty($missingDirs) ? array() : array('missing_directories' => $missingDirs),
			)
		);

		$checks[] = self::check(
			'manifest-excludes-honored',
			empty($manifestViolations) ? 'PASS' : 'FAIL',
			'release-public-excludes.txt rules are honored',
			empty($manifestViolations)
				? 'No manifest-excluded paths were found in the package.'
				: 'Manifest-excluded content was found in the package.',
			array(
				'required' => true,
				'details' => empty($manifestViolations) ? array() : array('paths' => array_values(array_unique($manifestViolations))),
			)
		);

		$checks[] = self::check(
			'archive-path-safety',
			empty($pathTraversalViolations) ? 'PASS' : 'FAIL',
			'Archive extraction cannot escape the destination directory',
			empty($pathTraversalViolations)
				? 'No path traversal patterns were found in the package.'
				: 'One or more archive entries can escape the destination directory.',
			array(
				'required' => true,
				'details' => empty($pathTraversalViolations) ? array() : array('paths' => array_values(array_unique($pathTraversalViolations))),
			)
		);

		$checks[] = self::check(
			'developer-machine-path-scan',
			empty($localPathViolations) ? 'PASS' : 'FAIL',
			'No developer-machine absolute paths appear in packaged text files',
			empty($localPathViolations)
				? 'No obvious developer-machine absolute paths were found.'
				: 'One or more packaged text files contain developer-machine path markers.',
			array(
				'required' => true,
				'details' => empty($localPathViolations) ? array() : array('files' => array_values(array_unique($localPathViolations))),
			)
		);

		$checks[] = self::check(
			'local-environment-url-scan',
			empty($localUrlViolations) ? 'PASS' : 'FAIL',
			'No obvious local environment URLs appear in packaged text files',
			empty($localUrlViolations)
				? 'No obvious local environment URLs were found.'
				: 'One or more packaged text files contain local-environment URL markers.',
			array(
				'required' => true,
				'details' => empty($localUrlViolations) ? array() : array('files' => array_values(array_unique($localUrlViolations))),
			)
		);

		$checks[] = self::check(
			'credential-pattern-scan',
			empty($credentialViolations) ? 'PASS' : 'FAIL',
			'No obvious credentials appear in packaged text files',
			empty($credentialViolations)
				? 'No high-confidence credential markers were found.'
				: 'One or more packaged text files contain credential-like markers.',
			array(
				'required' => true,
				'details' => empty($credentialViolations) ? array() : array('files' => array_values(array_unique($credentialViolations))),
			)
		);

		$checks[] = self::check(
			'zero-byte-runtime-code',
			empty($zeroByteRuntimeCode) ? 'PASS' : 'FAIL',
			'No zero-byte runtime PHP or JavaScript files exist',
			empty($zeroByteRuntimeCode)
				? 'No zero-byte runtime PHP or JavaScript files were found.'
				: 'One or more runtime PHP or JavaScript files are empty.',
			array(
				'required' => true,
				'details' => empty($zeroByteRuntimeCode) ? array() : array('files' => array_values(array_unique($zeroByteRuntimeCode))),
			)
		);

		$checks[] = self::check(
			'nested-archives',
			empty($nestedArchives) ? 'PASS' : 'FAIL',
			'No nested release archive is included',
			empty($nestedArchives)
				? 'No nested archive files were found.'
				: 'One or more nested archive files were found inside the package.',
			array(
				'required' => true,
				'details' => empty($nestedArchives) ? array() : array('files' => array_values(array_unique($nestedArchives))),
			)
		);

		$checks[] = self::check(
			'symlink-entries',
			empty($symlinkEntries) ? 'PASS' : 'FAIL',
			'No symlinks are packaged',
			empty($symlinkEntries)
				? 'No symlink entries were found in the package.'
				: 'One or more symlink entries were found in the package.',
			array(
				'required' => true,
				'details' => empty($symlinkEntries) ? array() : array('paths' => array_values(array_unique($symlinkEntries))),
			)
		);

		return $checks;
	}

	private static function resolvePackageDirectoryRoot(string $target, string $pluginSlug): string
	{
		$target = self::realpathOrOriginal($target);
		if (basename($target) === $pluginSlug) {
			return $target;
		}

		$nested = $target . DIRECTORY_SEPARATOR . $pluginSlug;
		if (is_dir($nested)) {
			return self::realpathOrOriginal($nested);
		}

		return $target;
	}

	private static function scanDirectoryEntries(string $packageRoot): array
	{
		$entries = array();
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($packageRoot, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::SELF_FIRST
		);
		foreach ($iterator as $fileInfo) {
			/** @var SplFileInfo $fileInfo */
			$entries[] = array(
				'name' => $fileInfo->getPathname(),
				'size' => $fileInfo->isFile() ? (int) ($fileInfo->getSize() ?: 0) : 0,
				'is_dir' => $fileInfo->isDir(),
				'is_link' => $fileInfo->isLink(),
				'index' => -1,
			);
		}

		return $entries;
	}

	private static function listFilesByExtension(string $root, array $extensions): array
	{
		$extensions = array_map('strtolower', $extensions);
		$files = array();
		foreach (self::listAllFiles($root) as $absolutePath) {
			$extension = strtolower((string) pathinfo($absolutePath, PATHINFO_EXTENSION));
			if (in_array($extension, $extensions, true)) {
				$files[] = $absolutePath;
			}
		}

		sort($files, SORT_STRING);
		return $files;
	}

	private static function listAllFiles(string $root): array
	{
		$files = array();
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::LEAVES_ONLY
		);
		foreach ($iterator as $fileInfo) {
			/** @var SplFileInfo $fileInfo */
			if ($fileInfo->isFile()) {
				$files[] = $fileInfo->getPathname();
			}
		}

		sort($files, SORT_STRING);
		return $files;
	}

	private static function collectPackageFileManifest(string $packageRoot): array
	{
		$files = array();
		foreach (self::listAllFiles($packageRoot) as $absolutePath) {
			$relativePath = self::pathRelativeToOrBasename($absolutePath, $packageRoot);
			$files[$relativePath] = array(
				'path' => $relativePath,
				'size_bytes' => (int) (filesize($absolutePath) ?: 0),
				'sha256' => strtolower((string) (hash_file('sha256', $absolutePath) ?: '')),
			);
		}

		ksort($files, SORT_STRING);
		return $files;
	}

	private static function comparePackageToProvenance(array $expectedFiles, array $actualFiles): array
	{
		$missing = array_values(array_diff(array_keys($expectedFiles), array_keys($actualFiles)));
		$extra = array_values(array_diff(array_keys($actualFiles), array_keys($expectedFiles)));
		$shaMismatches = array();
		$sizeMismatches = array();

		foreach ($expectedFiles as $path => $expected) {
			if (!isset($actualFiles[$path])) {
				continue;
			}

			$actual = (array) $actualFiles[$path];
			if (($expected['sha256'] ?? '') !== ($actual['sha256'] ?? '')) {
				$shaMismatches[] = $path;
			}
			if ((int) ($expected['size_bytes'] ?? 0) !== (int) ($actual['size_bytes'] ?? 0)) {
				$sizeMismatches[] = $path;
			}
		}

		sort($missing, SORT_STRING);
		sort($extra, SORT_STRING);
		sort($shaMismatches, SORT_STRING);
		sort($sizeMismatches, SORT_STRING);

		return array(
			'missing' => $missing,
			'extra' => $extra,
			'sha_mismatches' => $shaMismatches,
			'size_mismatches' => $sizeMismatches,
		);
	}

	private static function provenanceComparisonHasDiffs(array $comparison): bool
	{
		foreach (array('missing', 'extra', 'sha_mismatches', 'size_mismatches') as $key) {
			if (!empty($comparison[$key])) {
				return true;
			}
		}

		return false;
	}

	private static function provenanceComparisonDetails(array $comparison): array
	{
		$details = array();
		foreach (array(
			'missing' => 'missing_files',
			'extra' => 'extra_files',
			'sha_mismatches' => 'sha_mismatch_files',
			'size_mismatches' => 'size_mismatch_files',
		) as $sourceKey => $detailKey) {
			if (!empty($comparison[$sourceKey])) {
				$details[$detailKey] = array_values($comparison[$sourceKey]);
			}
		}

		return $details;
	}

	private static function loadProvenanceManifest(string $manifestPath): array
	{
		$resolvedPath = self::normalizePath($manifestPath);
		if (!is_readable($resolvedPath)) {
			throw new RuntimeException('Provenance manifest is missing or unreadable.');
		}

		$decoded = json_decode((string) file_get_contents($resolvedPath), true);
		if (!is_array($decoded)) {
			throw new RuntimeException('Provenance manifest is not valid JSON.');
		}

		$filesByPath = array();
		foreach ((array) ($decoded['files'] ?? array()) as $entry) {
			if (!is_array($entry)) {
				continue;
			}

			$path = ltrim(str_replace('\\', '/', (string) ($entry['path'] ?? '')), '/');
			if ($path === '') {
				throw new RuntimeException('Provenance manifest contains an entry without a path.');
			}

			$mtimeUnix = (int) ($entry['mtime_unix'] ?? 0);
			if ($mtimeUnix <= 0) {
				throw new RuntimeException('Provenance manifest contains an invalid mtime for `' . $path . '`.');
			}

			$filesByPath[$path] = array(
				'path' => $path,
				'size_bytes' => (int) ($entry['size_bytes'] ?? 0),
				'sha256' => strtolower((string) ($entry['sha256'] ?? '')),
				'mtime_unix' => $mtimeUnix,
			);
		}

		if ($filesByPath === array()) {
			throw new RuntimeException('Provenance manifest does not contain any package files.');
		}

		$decoded['path'] = $resolvedPath;
		$decoded['version'] = (string) ($decoded['version'] ?? '');
		$decoded['slug'] = self::normalizeSlug((string) ($decoded['slug'] ?? 'vms'));
		$decoded['artifact'] = is_array($decoded['artifact'] ?? null) ? $decoded['artifact'] : array();
		$decoded['artifact']['filename'] = (string) ($decoded['artifact']['filename'] ?? '');
		$decoded['artifact']['sha256'] = strtolower((string) ($decoded['artifact']['sha256'] ?? ''));
		$decoded['artifact']['root_mtime_unix'] = (int) ($decoded['artifact']['root_mtime_unix'] ?? 0);
		$decoded['files_by_path'] = $filesByPath;

		return $decoded;
	}

	private static function loadExcludeManifest(string $manifestPath): array
	{
		if (!is_readable($manifestPath)) {
			throw new RuntimeException('Release exclude manifest is missing or unreadable.');
		}

		$lines = file($manifestPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		if (!is_array($lines)) {
			throw new RuntimeException('Could not read the release exclude manifest.');
		}

		$patterns = array();
		foreach ($lines as $line) {
			$line = trim((string) $line);
			if ($line === '' || strpos($line, '#') === 0) {
				continue;
			}
			$patterns[] = ltrim(str_replace('\\', '/', $line), '/');
		}

		return $patterns;
	}

	private static function firstMatchingPattern(string $path, array $patterns): ?string
	{
		$path = ltrim(str_replace('\\', '/', $path), '/');
		foreach ($patterns as $pattern) {
			$pattern = ltrim(str_replace('\\', '/', (string) $pattern), '/');
			if ($pattern === '') {
				continue;
			}
			if (preg_match(self::globPatternToRegex($pattern), $path) === 1) {
				return $pattern;
			}
		}

		return null;
	}

	private static function globPatternToRegex(string $pattern): string
	{
		$isDirectoryPattern = substr($pattern, -1) === '/';
		$corePattern = $isDirectoryPattern ? rtrim($pattern, '/') : $pattern;
		$escaped = preg_quote($corePattern, '/');
		$escaped = str_replace('\*\*', '___DOUBLESTAR___', $escaped);
		$escaped = str_replace('\*', '[^\/]*', $escaped);
		$escaped = str_replace('___DOUBLESTAR___', '.*', $escaped);
		$escaped = str_replace('\?', '[^\/]', $escaped);
		if ($isDirectoryPattern) {
			return '/^' . $escaped . '(?:\/.*)?$/';
		}

		return '/^' . $escaped . '$/';
	}

	private static function readPluginHeader(string $filePath): array
	{
		if (!is_readable($filePath)) {
			return array();
		}

		$contents = (string) file_get_contents($filePath);
		$header = array();
		foreach (array('Plugin Name', 'Version', 'Text Domain', 'Requires PHP', 'Requires at least') as $field) {
			if (preg_match('/^[ \t\/*#@]*' . preg_quote($field, '/') . ':\s*(.+)$/mi', $contents, $matches) === 1) {
				$header[$field] = trim((string) $matches[1]);
			}
		}

		return $header;
	}

	private static function extractDefineValue(string $filePath, string $constantName): ?string
	{
		if (!is_readable($filePath)) {
			return null;
		}

		$contents = (string) file_get_contents($filePath);
		if (preg_match('/define\(\s*[\'"]' . preg_quote($constantName, '/') . '[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]\s*\)/', $contents, $matches) === 1) {
			return trim((string) $matches[1]);
		}

		return null;
	}

	private static function inspectVendorCoreMigrations(string $migrationsFile): array
	{
		$output = array(
			'latest_function' => '',
			'latest_schema_marker' => '',
			'activation_calls_latest' => false,
		);
		if (!is_readable($migrationsFile)) {
			return $output;
		}

		$contents = (string) file_get_contents($migrationsFile);
		if (preg_match_all('/function\s+(vms_db_migrate_vendor_core_v(\d+))\s*\(/', $contents, $functionMatches, PREG_SET_ORDER) > 0) {
			$latestVersion = -1;
			$latestFunction = '';
			foreach ($functionMatches as $functionMatch) {
				$version = (int) $functionMatch[2];
				if ($version > $latestVersion) {
					$latestVersion = $version;
					$latestFunction = (string) $functionMatch[1];
				}
			}
			$output['latest_function'] = $latestFunction;
			if ($latestVersion > 0) {
				$output['latest_schema_marker'] = 'vendor_core_v' . $latestVersion;
			}
		}

		$activationFile = dirname($migrationsFile) . '/../activation.php';
		if (is_readable($activationFile) && $output['latest_function'] !== '') {
			$activationContents = (string) file_get_contents($activationFile);
			$output['activation_calls_latest'] = strpos($activationContents, $output['latest_function']) !== false;
		}

		return $output;
	}

	private static function locateReadmeFile(string $pluginRoot): ?string
	{
		foreach (array('readme.txt', 'README.txt', 'README.md', 'readme.md') as $candidate) {
			$path = $pluginRoot . DIRECTORY_SEPARATOR . $candidate;
			if (is_readable($path)) {
				return $path;
			}
		}

		return null;
	}

	private static function readmeStableTagStatus(array $metadata): string
	{
		$readmeFile = $metadata['readme_file'] ?? null;
		if (!is_string($readmeFile) || $readmeFile === '') {
			return 'SKIP';
		}

		$contents = (string) file_get_contents($readmeFile);
		if (preg_match('/^Stable tag:\s*(.+)$/mi', $contents, $matches) !== 1) {
			return 'WARN';
		}

		return trim((string) $matches[1]) === (string) $metadata['version'] ? 'PASS' : 'FAIL';
	}

	private static function readmeStableTagMessage(array $metadata): string
	{
		$readmeFile = $metadata['readme_file'] ?? null;
		if (!is_string($readmeFile) || $readmeFile === '') {
			return 'No readme file is present, so there is no stable tag to verify.';
		}

		$contents = (string) file_get_contents($readmeFile);
		if (preg_match('/^Stable tag:\s*(.+)$/mi', $contents, $matches) !== 1) {
			return 'Readme file exists but does not declare a stable tag.';
		}

		$stableTag = trim((string) $matches[1]);
		if ($stableTag === (string) $metadata['version']) {
			return 'Readme stable tag matches the plugin version.';
		}

		return 'Readme stable tag does not match the plugin version.';
	}

	private static function detectWordPressRoot(string $pluginRoot): ?string
	{
		$current = $pluginRoot;
		while (true) {
			if (is_readable($current . DIRECTORY_SEPARATOR . 'wp-load.php')) {
				return $current;
			}
			$parent = dirname($current);
			if ($parent === $current) {
				return null;
			}
			$current = $parent;
		}
	}

	private static function runCommand(array $command, ?string $cwd = null, array $extraEnv = array()): array
	{
		$descriptorSpec = array(
			0 => array('pipe', 'r'),
			1 => array('pipe', 'w'),
			2 => array('pipe', 'w'),
		);
		$env = $extraEnv === array() ? null : array_merge(self::processEnvironment(), $extraEnv);
		$process = proc_open($command, $descriptorSpec, $pipes, $cwd, $env);
		if (!is_resource($process)) {
			return array(
				'exit_code' => 1,
				'stdout' => '',
				'stderr' => 'Could not start command: ' . implode(' ', $command),
			);
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

	private static function phpBinary(): string
	{
		$binary = defined('PHP_BINARY') ? (string) PHP_BINARY : '';
		return $binary !== '' ? $binary : 'php';
	}

	private static function processEnvironment(): array
	{
		$environment = array();
		foreach (array_merge($_SERVER, $_ENV) as $key => $value) {
			if (!is_string($key) || $key === '' || !is_scalar($value)) {
				continue;
			}
			$environment[$key] = (string) $value;
		}

		foreach (array('HOME', 'LANG', 'LC_ALL', 'LOGNAME', 'PATH', 'PWD', 'SHELL', 'TERM', 'TMPDIR', 'USER') as $key) {
			$value = getenv($key);
			if (is_string($value) && $value !== '') {
				$environment[$key] = $value;
			}
		}

		return $environment;
	}

	private static function commandExists(string $command): bool
	{
		$result = self::runCommand(array('sh', '-lc', 'command -v ' . escapeshellarg($command) . ' >/dev/null 2>&1'));
		return $result['exit_code'] === 0;
	}

	private static function check(string $id, string $status, string $label, string $message, array $options = array()): array
	{
		return array(
			'id' => $id,
			'status' => $status,
			'label' => $label,
			'message' => self::sanitizeExceptionMessage($message),
			'required' => array_key_exists('required', $options) ? (bool) $options['required'] : true,
			'details' => isset($options['details']) && is_array($options['details']) ? $options['details'] : array(),
		);
	}

	private static function hasRequiredFailures(array $checks): bool
	{
		foreach ($checks as $check) {
			if (($check['status'] ?? '') === 'FAIL' && !empty($check['required'])) {
				return true;
			}
		}

		return false;
	}

	private static function computeOverallStatus(array $checks): string
	{
		return self::hasRequiredFailures($checks) ? 'FAIL' : 'PASS';
	}

	private static function collectWarnings(array $checks): array
	{
		return self::collectByStatus($checks, 'WARN');
	}

	private static function collectByStatus(array $checks, string $status): array
	{
		$matches = array();
		foreach ($checks as $check) {
			if (($check['status'] ?? '') === $status) {
				$matches[] = $check;
			}
		}

		return $matches;
	}

	private static function writeReportFiles(array $report, bool $force): void
	{
		$jsonPath = (string) ($report['artifact']['report_json_path'] ?? '');
		$textPath = (string) ($report['artifact']['report_text_path'] ?? '');
		$zipPath = (string) ($report['artifact']['zip_path'] ?? '');

		foreach (array($jsonPath, $textPath, $zipPath) as $path) {
			if ($path === '') {
				continue;
			}
			$dir = dirname($path);
			if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
				throw new RuntimeException('Could not create output directory for report files.');
			}
			if ((basename($path) !== basename($zipPath) || empty($report['artifact']['created'])) && file_exists($path) && !$force) {
				throw new RuntimeException('Refusing to overwrite existing report file without --force: ' . basename($path));
			}
		}

		if (file_put_contents($jsonPath, self::renderJsonReport($report)) === false) {
			throw new RuntimeException('Could not write JSON release report.');
		}
		if (file_put_contents($textPath, self::renderTextReport($report)) === false) {
			throw new RuntimeException('Could not write text release report.');
		}
	}

	private static function createTemporaryDirectory(string $prefix): string
	{
		$base = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);
		$path = $base . DIRECTORY_SEPARATOR . $prefix . bin2hex(random_bytes(8));
		if (!mkdir($path, 0775, true) && !is_dir($path)) {
			throw new RuntimeException('Could not create a temporary build directory.');
		}

		return $path;
	}

	private static function deletePath(string $path): void
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

	private static function formatSize(int $bytes): string
	{
		if ($bytes <= 0) {
			return '0 B';
		}

		$units = array('B', 'KB', 'MB', 'GB');
		$size = (float) $bytes;
		$unitIndex = 0;
		while ($size >= 1024 && $unitIndex < count($units) - 1) {
			$size /= 1024;
			$unitIndex++;
		}

		return sprintf('%.2f %s', $size, $units[$unitIndex]);
	}

	private static function normalizeSlug(string $slug): string
	{
		$normalized = strtolower(trim($slug));
		$normalized = preg_replace('/[^a-z0-9_\-]/', '-', $normalized);
		return trim((string) $normalized, '-');
	}

	private static function realpathOrOriginal(string $path): string
	{
		$resolved = realpath($path);
		return $resolved === false ? self::normalizePath($path) : self::normalizePath($resolved);
	}

	private static function normalizePath(string $path): string
	{
		return rtrim(str_replace('\\', '/', $path), '/');
	}

	private static function pathRelativeTo(string $path, string $root): ?string
	{
		$path = self::normalizePath($path);
		$root = self::normalizePath($root);
		if ($path === $root) {
			return '';
		}
		if (strpos($path, $root . '/') !== 0) {
			return null;
		}

		return substr($path, strlen($root) + 1);
	}

	private static function pathRelativeToOrBasename(string $path, string $root): string
	{
		$relative = self::pathRelativeTo($path, $root);
		if ($relative !== null) {
			return $relative;
		}

		return basename($path);
	}

	private static function zipRelativePathForSlug(string $zipEntryName, string $pluginSlug): ?string
	{
		$zipEntryName = ltrim(str_replace('\\', '/', $zipEntryName), '/');
		if ($zipEntryName === $pluginSlug || $zipEntryName === $pluginSlug . '/') {
			return '';
		}
		if (strpos($zipEntryName, $pluginSlug . '/') !== 0) {
			return null;
		}

		return substr($zipEntryName, strlen($pluginSlug) + 1);
	}

	private static function shouldScanTextContent(string $relativePath): bool
	{
		$extension = strtolower((string) pathinfo($relativePath, PATHINFO_EXTENSION));
		return in_array($extension, self::TEXT_SCAN_EXTENSIONS, true);
	}

	private static function containsDeveloperMachinePath(string $contents): bool
	{
		return preg_match('~/(Users|home|var/folders)/[^\\s\'"<>]+~', $contents) === 1
			|| strpos($contents, 'Local Sites') !== false;
	}

	private static function containsLocalEnvironmentUrl(string $contents): bool
	{
		return preg_match('~https?://[^\\s\'"]*(localhost|127\\.0\\.0\\.1|\\.local\\b|serenade-range-local-test-site)~i', $contents) === 1;
	}

	private static function containsCredentialPattern(string $contents): bool
	{
		return preg_match('~-----BEGIN [A-Z ]*PRIVATE KEY-----~', $contents) === 1
			|| preg_match('~\\b(DB_PASSWORD|AUTH_KEY|SECURE_AUTH_KEY|SQUARE_ACCESS_TOKEN|AWS_SECRET_ACCESS_KEY|PRIVATE_KEY)\\b~', $contents) === 1
			|| preg_match('~://[^\\s/:]+:[^\\s/@]+@~', $contents) === 1;
	}

	private static function isNestedArchivePath(string $relativePath): bool
	{
		$extension = strtolower((string) pathinfo($relativePath, PATHINFO_EXTENSION));
		return in_array($extension, self::NESTED_ARCHIVE_EXTENSIONS, true);
	}

	private static function zipEntryIsSymlink(ZipArchive $zip, int $index): bool
	{
		if ($index < 0 || !method_exists($zip, 'getExternalAttributesIndex')) {
			return false;
		}

		$opsys = 0;
		$attributes = 0;
		if (!$zip->getExternalAttributesIndex($index, $opsys, $attributes, ZipArchive::OPSYS_UNIX)) {
			return false;
		}

		$fileMode = ($attributes >> 16) & 0xF000;
		return $fileMode === 0xA000;
	}

	private static function registerAncestorDirectories(array &$presentDirs, string $relativePath): void
	{
		$directory = str_replace('\\', '/', dirname($relativePath));
		if ($directory === '' || $directory === '.' || $directory === '/') {
			return;
		}

		$segments = array_values(array_filter(explode('/', trim($directory, '/')), 'strlen'));
		$current = '';
		foreach ($segments as $segment) {
			$current = $current === '' ? $segment : $current . '/' . $segment;
			$presentDirs[$current] = true;
		}
	}

	private static function isSafeArchivePath(string $path): bool
	{
		$path = str_replace('\\', '/', $path);
		if ($path === '' || strpos($path, "\0") !== false) {
			return false;
		}
		if ($path[0] === '/' || preg_match('/^[A-Za-z]:\//', $path) === 1) {
			return false;
		}

		$depth = 0;
		foreach (explode('/', $path) as $segment) {
			if ($segment === '' || $segment === '.') {
				continue;
			}
			if ($segment === '..') {
				$depth--;
				if ($depth < 0) {
					return false;
				}
				continue;
			}
			$depth++;
		}

		return true;
	}

	private static function sanitizeExceptionMessage(string $message): string
	{
		$message = trim($message);
		return $message !== '' ? $message : 'No detail provided.';
	}

	private static function firstNonEmptyLine(string $output, string $fallback): string
	{
		foreach (preg_split('/\R/', trim($output)) as $line) {
			$line = trim((string) $line);
			if ($line !== '') {
				return $line;
			}
		}

		return $fallback;
	}
}
