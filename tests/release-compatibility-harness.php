<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/scripts/lib/release-compatibility.php';

function vms_release_compat_test_assert(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}

	throw new RuntimeException($message);
}

function vms_release_compat_test_expect_exception(callable $callback, string $needle): void
{
	try {
		$callback();
	} catch (Throwable $throwable) {
		vms_release_compat_test_assert(
			strpos($throwable->getMessage(), $needle) !== false,
			'Unexpected exception message: ' . $throwable->getMessage()
		);
		return;
	}

	throw new RuntimeException('Expected exception containing: ' . $needle);
}

function vms_release_compat_test_temp_dir(string $prefix): string
{
	$path = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $prefix . bin2hex(random_bytes(6));
	if (!mkdir($path, 0775, true) && !is_dir($path)) {
		throw new RuntimeException('Could not create temp directory: ' . $path);
	}

	return $path;
}

function vms_release_compat_test_create_zip(string $zipPath, array $entries): void
{
	$dir = dirname($zipPath);
	if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
		throw new RuntimeException('Could not create ZIP directory: ' . $dir);
	}

	$zip = new ZipArchive();
	$opened = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
	if ($opened !== true) {
		throw new RuntimeException('Could not create ZIP fixture.');
	}

	foreach ($entries as $path => $contents) {
		if (substr($path, -1) === '/') {
			$zip->addEmptyDir($path);
			continue;
		}
		$zip->addFromString($path, $contents);
	}

	$zip->close();
}

function vms_release_compat_test_run(array $command, ?string $cwd = null): array
{
	$descriptorSpec = array(
		0 => array('pipe', 'r'),
		1 => array('pipe', 'w'),
		2 => array('pipe', 'w'),
	);
	$process = proc_open($command, $descriptorSpec, $pipes, $cwd);
	if (!is_resource($process)) {
		throw new RuntimeException('Could not start subprocess.');
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

$tests = array();

$tests['missing artifact is rejected'] = static function (): void {
	vms_release_compat_test_expect_exception(
		static function (): void {
			VMS_Release_Compatibility_Tooling::validateArtifactInput('/tmp/definitely-missing-vms-artifact.zip');
		},
		'Artifact ZIP not found'
	);
};

$tests['wrong root directory is rejected'] = static function (): void {
	$workspace = vms_release_compat_test_temp_dir('vms-release-compat-root-');
	try {
		$zipPath = $workspace . DIRECTORY_SEPARATOR . 'backstage-venue-manager-1.2.3-public-release.zip';
		vms_release_compat_test_create_zip($zipPath, array(
			'not-vms/' => '',
			'not-vms/plugin.php' => "<?php\n",
		));

		vms_release_compat_test_expect_exception(
			static function () use ($zipPath): void {
				VMS_Release_Compatibility_Tooling::inspectZipRoot($zipPath);
			},
			'one top-level backstage-venue-manager/ root directory'
		);
	} finally {
		VMS_Release_Compatibility_Tooling::deletePath($workspace);
	}
};

$tests['paths with spaces validate and CLI checksum mismatches fail cleanly'] = static function (): void {
	$workspace = vms_release_compat_test_temp_dir('vms release compat spaces ');
	try {
		$zipDir = $workspace . DIRECTORY_SEPARATOR . 'build output';
		$zipPath = $zipDir . DIRECTORY_SEPARATOR . 'backstage-venue-manager-9.9.9-public-release.zip';
		vms_release_compat_test_create_zip($zipPath, array(
			'backstage-venue-manager/' => '',
			'backstage-venue-manager/vendor-management-system.php' => "<?php\n/**\n * Plugin Name: VMS\n * Version: 9.9.9\n */\n",
			'backstage-venue-manager/includes/bootstrap.php' => "<?php\n",
			'backstage-venue-manager/includes/core/plugin.php' => "<?php\n",
			'backstage-venue-manager/includes/core/registry/constants.php' => "<?php\ndefine('VMS_VERSION', '9.9.9');\n",
			'backstage-venue-manager/includes/db/migrations.php' => "<?php\n",
			'backstage-venue-manager/assets/app.js' => "console.log('ok');\n",
			'backstage-venue-manager/uninstall.php' => "<?php\ndefined('WP_UNINSTALL_PLUGIN') || exit;\n",
			'backstage-venue-manager/vms-build.txt' => "9.9.9\n",
		));

		$artifact = VMS_Release_Compatibility_Tooling::validateArtifactInput($zipPath);
		vms_release_compat_test_assert(
			$artifact['root_directory'] === VMS_Release_Compatibility_Tooling::publicPluginSlug(),
			'Expected public package root directory.'
		);
		vms_release_compat_test_assert(
			$artifact['plugin_basename'] === VMS_Release_Compatibility_Tooling::publicPluginBasename(),
			'Expected public package basename.'
		);
		vms_release_compat_test_assert($artifact['version'] === '9.9.9', 'Expected version extraction to preserve ZIP version.');

		$command = array(
			PHP_BINARY,
			__DIR__ . '/../scripts/test-release-compatibility.php',
			'--artifact=' . $zipPath,
			'--expected-sha256=' . str_repeat('0', 64),
		);
		$result = vms_release_compat_test_run($command, dirname(__DIR__));
		vms_release_compat_test_assert($result['exit_code'] === 1, 'Checksum mismatch should exit nonzero.');
		vms_release_compat_test_assert(
			strpos($result['stderr'], 'Artifact checksum does not match the expected SHA-256.') !== false,
			'Expected a clean checksum mismatch message from the CLI wrapper.'
		);
	} finally {
		VMS_Release_Compatibility_Tooling::deletePath($workspace);
	}
};

$tests['recognized plugin basenames accept internal and public installs only'] = static function (): void {
	$recognized = VMS_Release_Compatibility_Tooling::recognizedPluginBasenames();
	vms_release_compat_test_assert(
		in_array(VMS_Release_Compatibility_Tooling::publicPluginBasename(), $recognized, true),
		'Expected public plugin basename to be recognized.'
	);
	vms_release_compat_test_assert(
		in_array(VMS_Release_Compatibility_Tooling::internalPluginBasename(), $recognized, true),
		'Expected internal plugin basename to be recognized.'
	);
	vms_release_compat_test_assert(
		VMS_Release_Compatibility_Tooling::isRecognizedPluginBasename(VMS_Release_Compatibility_Tooling::publicPluginBasename()),
		'Expected public plugin basename to pass recognition.'
	);
	vms_release_compat_test_assert(
		VMS_Release_Compatibility_Tooling::isRecognizedPluginBasename(VMS_Release_Compatibility_Tooling::internalPluginBasename()),
		'Expected internal plugin basename to pass recognition.'
	);
	vms_release_compat_test_assert(
		!VMS_Release_Compatibility_Tooling::isRecognizedPluginBasename('not-vms/vendor-management-system.php'),
		'Expected unrelated plugin basename to be rejected.'
	);
	vms_release_compat_test_assert(
		substr(VMS_Release_Compatibility_Tooling::publicPluginBasename(), -strlen(VMS_Release_Compatibility_Tooling::mainPluginFile())) === VMS_Release_Compatibility_Tooling::mainPluginFile(),
		'Expected the public basename to preserve the main plugin filename.'
	);
};

$tests['installed plugin basename resolution keeps build-version lookup stable across basenames'] = static function (): void {
	$workspace = vms_release_compat_test_temp_dir('vms release compat basenames ');
	try {
		$pluginsDir = $workspace . DIRECTORY_SEPARATOR . 'plugins';
		$publicDir = $pluginsDir . DIRECTORY_SEPARATOR . VMS_Release_Compatibility_Tooling::publicPluginSlug();
		$internalDir = $pluginsDir . DIRECTORY_SEPARATOR . VMS_Release_Compatibility_Tooling::internalPluginSlug();
		if (!mkdir($publicDir, 0775, true) || !mkdir($internalDir, 0775, true)) {
			throw new RuntimeException('Could not create plugin basename fixtures.');
		}
		file_put_contents($publicDir . DIRECTORY_SEPARATOR . VMS_Release_Compatibility_Tooling::mainPluginFile(), "<?php\n");
		file_put_contents($publicDir . DIRECTORY_SEPARATOR . 'vms-build.txt', "9.9.9\n");
		file_put_contents($internalDir . DIRECTORY_SEPARATOR . VMS_Release_Compatibility_Tooling::mainPluginFile(), "<?php\n");
		file_put_contents($internalDir . DIRECTORY_SEPARATOR . 'vms-build.txt', "8.8.8\n");

		$publicBasename = VMS_Release_Compatibility_Tooling::resolveInstalledPluginBasename(array(), $pluginsDir);
		vms_release_compat_test_assert(
			$publicBasename === VMS_Release_Compatibility_Tooling::publicPluginBasename(),
			'Expected the public package basename to be preferred when only installed files are available.'
		);
		$publicBuildPath = VMS_Release_Compatibility_Tooling::buildVersionPathForPluginBasename($pluginsDir, $publicBasename);
		vms_release_compat_test_assert(is_file($publicBuildPath), 'Expected public basename build-version path to resolve.');
		vms_release_compat_test_assert(trim((string) file_get_contents($publicBuildPath)) === '9.9.9', 'Expected public basename build-version lookup to remain stable.');

		$internalBasename = VMS_Release_Compatibility_Tooling::resolveInstalledPluginBasename(
			array(VMS_Release_Compatibility_Tooling::internalPluginBasename()),
			$pluginsDir
		);
		vms_release_compat_test_assert(
			$internalBasename === VMS_Release_Compatibility_Tooling::internalPluginBasename(),
			'Expected the internal active basename to remain recognized.'
		);
		$internalBuildPath = VMS_Release_Compatibility_Tooling::buildVersionPathForPluginBasename($pluginsDir, $internalBasename);
		vms_release_compat_test_assert(is_file($internalBuildPath), 'Expected internal basename build-version path to resolve.');
		vms_release_compat_test_assert(trim((string) file_get_contents($internalBuildPath)) === '8.8.8', 'Expected internal basename build-version lookup to remain stable.');
		vms_release_compat_test_assert(
			VMS_Release_Compatibility_Tooling::buildVersionPathForPluginBasename($pluginsDir, 'not-vms/vendor-management-system.php') === '',
			'Expected arbitrary plugin basenames to be rejected for build-version lookup.'
		);
	} finally {
		VMS_Release_Compatibility_Tooling::deletePath($workspace);
	}
};

$tests['scheduled work comparison detects duplicate cron and action scheduler jobs'] = static function (): void {
	$comparison = VMS_Release_Compatibility_Tooling::compareScheduledWorkSnapshots(
		array(
			'cron' => array(
				'owned_hooks' => array(
					'vms_hourly' => 1,
				),
			),
			'action_scheduler' => array(
				'owned_hooks' => array(
					'vms_async_sync' => 1,
				),
			),
		),
		array(
			'cron' => array(
				'owned_hooks' => array(
					'vms_hourly' => 2,
				),
			),
			'action_scheduler' => array(
				'owned_hooks' => array(
					'vms_async_sync' => 2,
				),
			),
		)
	);

	vms_release_compat_test_assert($comparison['stable'] === false, 'Duplicate scheduled work should be unstable.');
	vms_release_compat_test_assert(
		(int) ($comparison['cron']['duplicate_hooks']['vms_hourly'] ?? 0) === 2,
		'Expected duplicate WP-Cron hook detection.'
	);
	vms_release_compat_test_assert(
		(int) ($comparison['action_scheduler']['duplicate_hooks']['vms_async_sync'] ?? 0) === 2,
		'Expected duplicate Action Scheduler hook detection.'
	);
};

$tests['fixture preservation comparison detects regressions'] = static function (): void {
	$comparison = VMS_Release_Compatibility_Tooling::compareFixturePreservation(
		array(
			'checks' => array(
				'status' => array(
					'plan_exists' => true,
					'user_exists' => true,
				),
			),
			'preserved' => true,
		),
		array(
			'checks' => array(
				'status' => array(
					'plan_exists' => false,
					'user_exists' => true,
				),
			),
			'preserved' => false,
		)
	);

	vms_release_compat_test_assert($comparison['preserved'] === false, 'Fixture regression should fail preservation.');
	vms_release_compat_test_assert(isset($comparison['regressions']['plan_exists']), 'Expected plan_exists regression to be recorded.');
};

$tests['matrix scenario selection resolves aliases in canonical order'] = static function (): void {
	$selected = VMS_Release_Compatibility_Tooling::resolveMatrixScenarioSelection(array('e', 'woo-only', 'scenario-c-tec-only'));
	$ids = array_values(array_map(static function (array $scenario): string {
		return (string) $scenario['id'];
	}, $selected));

	vms_release_compat_test_assert(
		$ids === array(
			'scenario-b-woocommerce-only',
			'scenario-c-tec-only',
			'scenario-e-present-inactive',
		),
		'Expected matrix selection aliases to resolve in canonical scenario order.'
	);
};

$tests['matrix scenario selection rejects unknown aliases'] = static function (): void {
	vms_release_compat_test_expect_exception(
		static function (): void {
			VMS_Release_Compatibility_Tooling::resolveMatrixScenarioSelection(array('scenario-z'));
		},
		'Unknown matrix scenario selection'
	);
};

$tests['cleanup helper deletes nested directories with spaces'] = static function (): void {
	$workspace = vms_release_compat_test_temp_dir('vms cleanup helper ');
	$nestedDir = $workspace . DIRECTORY_SEPARATOR . 'nested path' . DIRECTORY_SEPARATOR . 'deeper';
	if (!mkdir($nestedDir, 0775, true) && !is_dir($nestedDir)) {
		throw new RuntimeException('Could not create nested cleanup fixture.');
	}
	file_put_contents($nestedDir . DIRECTORY_SEPARATOR . 'fixture.txt', "ok\n");

	VMS_Release_Compatibility_Tooling::deletePath($workspace);
	vms_release_compat_test_assert(!file_exists($workspace), 'Expected deletePath() to remove nested directory trees.');
};

$tests['text report generation handles partial failure payloads'] = static function (): void {
	$report = array(
		'status' => 'FAIL',
		'artifact' => array(
			'filename' => 'backstage-venue-manager-0.2.24.746-public-release.zip',
			'sha256' => 'abc123',
		),
		'baseline_artifact' => array(
			'filename' => 'vms-0.2.24.725-checkout-hot-path.zip',
		),
		'environment' => array(
			'wordpress_version' => '7.0',
			'php_version' => '8.5.3',
		),
		'scenarios' => array(
			array(
				'label' => 'A. WordPress with VMS only',
				'status' => 'FAIL',
			),
		),
		'clean_install_lifecycle' => array(
			'status' => 'PASS',
			'summary' => 'ok',
		),
		'upgrade' => array(
			'status' => 'BLOCKED',
			'summary' => 'baseline missing',
		),
		'migration_interruption' => array(
			'status' => 'WARN',
			'summary' => 'duplicate hook detected',
		),
		'uninstall' => array(
			'status' => 'PASS',
			'summary' => 'ok',
		),
		'proposed_compatibility' => array(
			'minimum_supported_php' => '7.4',
			'versions_actually_tested_wordpress' => '7.0',
		),
		'remaining_browser_qa' => 'Outstanding.',
	);

	$text = VMS_Release_Compatibility_Tooling::generateTextReport($report);
	vms_release_compat_test_assert(strpos($text, 'Overall status: FAIL') !== false, 'Expected FAIL status in text report.');
	vms_release_compat_test_assert(strpos($text, 'Upgrade: [BLOCKED] baseline missing') !== false, 'Expected blocked upgrade summary in text report.');
	vms_release_compat_test_assert(strpos($text, '- [FAIL] A. WordPress with VMS only') !== false, 'Expected scenario summary in text report.');
};

$failures = array();
foreach ($tests as $name => $test) {
	try {
		$test();
		fwrite(STDOUT, "[PASS] {$name}\n");
	} catch (Throwable $throwable) {
		$failures[] = $name . ': ' . $throwable->getMessage();
		fwrite(STDERR, "[FAIL] {$name}: {$throwable->getMessage()}\n");
	}
}

if ($failures !== array()) {
	exit(1);
}

fwrite(STDOUT, "Release compatibility harness self-test OK.\n");
