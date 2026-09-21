<?php
declare(strict_types=1);

const BVMGR_CERTIFIED_SOURCE_COMMIT = '0ca4eb0e505f26e16348b20cbfd54243c241ad80';
const BVMGR_CERTIFIED_SOURCE_BLOBS = array(
	'assets/css/vms-staffing-admin.css' => 'b16db0ab00ad1a9016b059f035ef0701b229dfbf',
	'assets/js/vms-staffing-admin.js' => '2f4c4e74a53b2ed579c4adba506d0143180bda4c',
	'includes/admin/staffing.php' => '1f61d3f529fb2a2cd17ba6253c118c4b68b4b4fc',
	'includes/core/event-plan-review.php' => 'f1bcd513049902d59554dc4344c0b510d6e1981f',
	'includes/integrations/ticketing-rules-v2.php' => '969b6b5f1c48faaf387bdfc13f59e0ca497f490a',
);

/** @return array{exit_code:int,stdout:string,stderr:string} */
function bvmgr_test_certified_source_git(array $arguments): array
{
	$root = dirname(__DIR__, 2);
	$command = array_merge(array('git', '-C', $root), $arguments);
	$process = proc_open(
		$command,
		array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')),
		$pipes
	);
	if (!is_resource($process)) {
		throw new RuntimeException('Unable to start Git for the certified-source fixture.');
	}
	fclose($pipes[0]);
	$stdout = stream_get_contents($pipes[1]);
	$stderr = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);

	return array(
		'exit_code' => proc_close($process),
		'stdout' => is_string($stdout) ? $stdout : '',
		'stderr' => is_string($stderr) ? $stderr : '',
	);
}

function bvmgr_test_certified_source(string $relativePath): string
{
	$expectedBlob = BVMGR_CERTIFIED_SOURCE_BLOBS[$relativePath] ?? '';
	if ($expectedBlob === '') {
		throw new RuntimeException('Path is outside the certified-source fixture allowlist: ' . $relativePath);
	}

	$object = bvmgr_test_certified_source_git(array('rev-parse', BVMGR_CERTIFIED_SOURCE_COMMIT . ':' . $relativePath));
	if ($object['exit_code'] !== 0 || trim($object['stdout']) !== $expectedBlob) {
		throw new RuntimeException('Certified-source blob identity mismatch for ' . $relativePath . '.');
	}

	$source = bvmgr_test_certified_source_git(array('show', BVMGR_CERTIFIED_SOURCE_COMMIT . ':' . $relativePath));
	if ($source['exit_code'] !== 0 || $source['stdout'] === '') {
		throw new RuntimeException('Unable to load certified-source fixture for ' . $relativePath . '.');
	}

	return $source['stdout'];
}
