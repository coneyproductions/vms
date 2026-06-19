<?php
declare(strict_types=1);

function vms_runtime_stub_guard_assert(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}

	throw new RuntimeException($message);
}

function vms_runtime_stub_guard_run(array $command): array
{
	$descriptorSpec = array(
		0 => array('pipe', 'r'),
		1 => array('pipe', 'w'),
		2 => array('pipe', 'w'),
	);
	$process = proc_open($command, $descriptorSpec, $pipes, dirname(__DIR__));
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

$pluginRoot = dirname(__DIR__);
$phpBin = PHP_BINARY;

$outsideUninstall = vms_runtime_stub_guard_run(array($phpBin, $pluginRoot . '/uninstall.php'));
vms_runtime_stub_guard_assert($outsideUninstall['exit_code'] === 0, 'uninstall.php should exit cleanly outside uninstall context.');
vms_runtime_stub_guard_assert($outsideUninstall['stdout'] === '', 'uninstall.php should not emit output outside uninstall context.');
vms_runtime_stub_guard_assert($outsideUninstall['stderr'] === '', 'uninstall.php should not emit stderr outside uninstall context.');

$insideUninstall = vms_runtime_stub_guard_run(array(
	$phpBin,
	'-r',
	'define("WP_UNINSTALL_PLUGIN", true); include ' . var_export($pluginRoot . '/uninstall.php', true) . '; echo "ok\n";',
));
vms_runtime_stub_guard_assert($insideUninstall['exit_code'] === 0, 'uninstall.php should remain includable inside uninstall context.');
vms_runtime_stub_guard_assert(trim($insideUninstall['stdout']) === 'ok', 'uninstall.php should return control to WordPress uninstall context.');
vms_runtime_stub_guard_assert($insideUninstall['stderr'] === '', 'uninstall.php should not emit stderr inside uninstall context.');

$outsidePortalStub = vms_runtime_stub_guard_run(array($phpBin, $pluginRoot . '/includes/portal/vendor-tech-profile.php'));
vms_runtime_stub_guard_assert($outsidePortalStub['exit_code'] === 0, 'vendor-tech-profile.php should exit cleanly outside WordPress context.');
vms_runtime_stub_guard_assert($outsidePortalStub['stdout'] === '', 'vendor-tech-profile.php should not emit output outside WordPress context.');
vms_runtime_stub_guard_assert($outsidePortalStub['stderr'] === '', 'vendor-tech-profile.php should not emit stderr outside WordPress context.');

$insidePortalStub = vms_runtime_stub_guard_run(array(
	$phpBin,
	'-r',
	'define("ABSPATH", ' . var_export($pluginRoot . '/', true) . '); include ' . var_export($pluginRoot . '/includes/portal/vendor-tech-profile.php', true) . '; echo "ok\n";',
));
vms_runtime_stub_guard_assert($insidePortalStub['exit_code'] === 0, 'vendor-tech-profile.php should remain includable inside WordPress context.');
vms_runtime_stub_guard_assert(trim($insidePortalStub['stdout']) === 'ok', 'vendor-tech-profile.php should return control to the caller.');
vms_runtime_stub_guard_assert($insidePortalStub['stderr'] === '', 'vendor-tech-profile.php should not emit stderr inside WordPress context.');

fwrite(STDOUT, "Runtime stub guards OK.\n");
