<?php
declare(strict_types=1);

$GLOBALS['g14v2_assertions'] = 0;

function g14v2_assert(bool $condition, string $message): void
{
	$GLOBALS['g14v2_assertions']++;
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function g14v2_same($expected, $actual, string $message): void
{
	g14v2_assert(
		$expected === $actual,
		$message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true)
	);
}

/** @return array{exit_code:int,stdout:string,stderr:string} */
function g14v2_run(array $command, ?string $cwd = null): array
{
	$process = proc_open(
		$command,
		array(
			0 => array('pipe', 'r'),
			1 => array('pipe', 'w'),
			2 => array('pipe', 'w'),
		),
		$pipes,
		$cwd
	);
	g14v2_assert(is_resource($process), 'Unable to start command: ' . implode(' ', $command));
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

function g14v2_delete_path(string $path): void
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
		} else {
			@unlink($item->getPathname());
		}
	}
	@rmdir($path);
}

function g14v2_read_json(string $path): array
{
	$contents = file_get_contents($path);
	g14v2_assert(is_string($contents) && $contents !== '', 'Unable to read fixture: ' . $path);
	$decoded = json_decode($contents, true);
	g14v2_assert(is_array($decoded), 'Fixture must decode as JSON: ' . $path);

	return $decoded;
}

$root = dirname(__DIR__);
$fixtureDir = __DIR__ . '/fixtures/g14-g15-provenance-v2';
$provenancePath = $fixtureDir . '/provenance.json';
$manifestPath = $fixtureDir . '/historical-package-content-manifest.json';
$provenanceHash = '464998c7c4788abe09e31ab2a61904ef6d5cf05f8741bb0445f9600a980e7338';
$manifestHash = '567acf156c2e3f8d9a06a97913eedf5a0513c91e76dd11e59f1d7dcf37adc1c5';
$historicalSource = '2c8f790b9128d547b8bc0a27a714253fb6671bea';
$historicalZipHash = 'fec238a519108c7013659b4114e69e9aad93c5c6f864551d4290737d30a609e5';
$historicalJsonHash = 'c5fe4d23b3cdf632f239632a23f2c58f9ccf7b8e293ff4b9e71f65101527aa17';

g14v2_same($provenanceHash, hash_file('sha256', $provenancePath), 'Provenance-v2 fixture SHA-256 changed.');
g14v2_same($manifestHash, hash_file('sha256', $manifestPath), 'Historical content-manifest SHA-256 changed.');
$provenance = g14v2_read_json($provenancePath);
$manifest = g14v2_read_json($manifestPath);

g14v2_same(2, $provenance['schema_version'] ?? null, 'Provenance schema version changed.');
g14v2_same('g14-g15-provenance-v2', $provenance['fixture_version'] ?? null, 'Provenance fixture version changed.');
g14v2_same($historicalSource, $provenance['historical_source']['commit'] ?? null, 'Historical source SHA changed.');
g14v2_same($historicalZipHash, $provenance['historical_artifacts']['original_zip']['recorded_sha256'] ?? null, 'Historical ZIP identifier changed.');
g14v2_same(2054980, $provenance['historical_artifacts']['original_zip']['recorded_size_bytes'] ?? null, 'Historical ZIP size changed.');
g14v2_same($historicalJsonHash, $provenance['historical_artifacts']['strict_plugin_check_json']['recorded_sha256'] ?? null, 'Historical strict JSON identifier changed.');
g14v2_same(
	'original artifact unavailable / byte-identical reconstruction impossible from retained evidence',
	$provenance['historical_artifacts']['original_zip']['status'] ?? null,
	'Historical ZIP availability status changed.'
);
g14v2_same('original artifact unavailable', $provenance['historical_artifacts']['strict_plugin_check_json']['status'] ?? null, 'Historical strict JSON availability status changed.');

g14v2_same(1, $manifest['schema_version'] ?? null, 'Content-manifest schema version changed.');
g14v2_same('g14-public-package-content-v1', $manifest['manifest_version'] ?? null, 'Content-manifest version changed.');
g14v2_same($historicalSource, $manifest['historical_source']['commit'] ?? null, 'Content-manifest source SHA changed.');
g14v2_same('1.2.0', $manifest['historical_source']['public_release_version'] ?? null, 'Historical release version changed.');
g14v2_same('backstage-venue-manager', $manifest['package']['root_name'] ?? null, 'Historical package root changed.');
g14v2_same(372, $manifest['package']['file_count'] ?? null, 'Historical package file count changed.');
g14v2_same(372, $manifest['package']['regular_file_count'] ?? null, 'Historical regular-file count changed.');
g14v2_same(0, $manifest['package']['symlink_count'] ?? null, 'Historical package symlink count changed.');
g14v2_same(0, $manifest['package']['other_entry_count'] ?? null, 'Historical package unsupported-entry count changed.');
g14v2_same('excluded', $manifest['content_identity']['filesystem_mtimes'] ?? null, 'Historical content identity must exclude mtimes.');
g14v2_same($manifestHash, $provenance['reproducible_content']['manifest_sha256'] ?? null, 'Provenance manifest link changed.');
g14v2_same(372, $provenance['reproducible_content']['file_count'] ?? null, 'Provenance file count changed.');

$files = $manifest['files'] ?? null;
g14v2_assert(is_array($files), 'Content manifest files must be an array.');
g14v2_same(372, count($files), 'Content manifest must list exactly 372 files.');
$paths = array();
foreach ($files as $index => $entry) {
	g14v2_assert(is_array($entry), 'Content-manifest entry must be an array at index ' . $index . '.');
	$path = (string) ($entry['path'] ?? '');
	g14v2_assert($path !== '' && $path[0] !== '/' && strpos($path, '\\') === false, 'Content-manifest path is not normalized at index ' . $index . '.');
	g14v2_same('regular_file', $entry['type'] ?? null, 'Unsupported historical entry type at ' . $path . '.');
	g14v2_same('0644', $entry['mode'] ?? null, 'Historical staged mode changed at ' . $path . '.');
	g14v2_assert(is_int($entry['size_bytes'] ?? null) && $entry['size_bytes'] >= 0, 'Historical size is invalid at ' . $path . '.');
	g14v2_assert(preg_match('/^[a-f0-9]{64}$/', (string) ($entry['sha256'] ?? '')) === 1, 'Historical SHA-256 is invalid at ' . $path . '.');
	g14v2_assert(!array_key_exists('mtime', $entry) && !array_key_exists('mtime_unix', $entry), 'Historical content entry includes an mtime at ' . $path . '.');
	$paths[] = $path;
}
$sortedPaths = $paths;
sort($sortedPaths, SORT_STRING);
g14v2_same($sortedPaths, $paths, 'Historical content-manifest paths are not deterministically sorted.');
g14v2_same(count($paths), count(array_unique($paths)), 'Historical content-manifest paths are not unique.');

$totals = $provenance['historical_scan_evidence']['totals'] ?? null;
g14v2_assert(is_array($totals), 'Historical scan totals must be present.');
g14v2_same(181, $totals['findings'] ?? null, 'Historical finding total changed.');
g14v2_same(array('ERROR' => 139, 'WARNING' => 42), $totals['severity'] ?? null, 'Historical severity split changed.');
g14v2_same(0, $totals['database_sql_blockers'] ?? null, 'Historical DB/SQL blocker total changed.');
g14v2_same(14, $totals['datetime_findings'] ?? null, 'Historical DateTime total changed.');
g14v2_same(42, $totals['logging_findings'] ?? null, 'Historical logging total changed.');
g14v2_same(5, $totals['ticketing_owned_datetime_findings'] ?? null, 'Historical ticketing-owned DateTime total changed.');
g14v2_same(9, $totals['out_of_scope_datetime_findings'] ?? null, 'Historical out-of-scope DateTime total changed.');

$dateRows = $provenance['historical_scan_evidence']['datetime_findings'] ?? null;
g14v2_assert(is_array($dateRows), 'Historical DateTime rows must be present.');
g14v2_same(14, count($dateRows), 'Historical DateTime evidence must contain 14 rows.');
$dateMessage = 'date() is affected by runtime timezone changes which can cause date/time to be incorrectly displayed. Use gmdate() instead.';
$signature = static fn(array $row): string => implode(':', array(
	(string) ($row['file'] ?? ''),
	(int) ($row['line'] ?? 0),
	(int) ($row['column'] ?? 0),
	(string) ($row['type'] ?? ''),
	(string) ($row['code'] ?? ''),
	(string) ($row['classification'] ?? ''),
));
$actualRows = array_map($signature, $dateRows);
foreach ($dateRows as $row) {
	g14v2_same($dateMessage, $row['message'] ?? null, 'Historical DateTime message changed.');
}
$expectedRows = array(
	'/privateincludes/integrations/ticketing-phase-b.php:1616:79:ERROR:WordPress.DateTime.RestrictedFunctions.date_date:ticketing_owned',
	'/privateincludes/integrations/ticketing-phase-b.php:3087:11:ERROR:WordPress.DateTime.RestrictedFunctions.date_date:ticketing_owned',
	'/privateincludes/portal/vendor-tax-profile.php:158:116:ERROR:WordPress.DateTime.RestrictedFunctions.date_date:out_of_scope',
	'/privateincludes/core/payables.php:88:16:ERROR:WordPress.DateTime.RestrictedFunctions.date_date:out_of_scope',
	'/privateincludes/core/payables.php:119:12:ERROR:WordPress.DateTime.RestrictedFunctions.date_date:out_of_scope',
	'/privateincludes/core/event-credits.php:844:84:ERROR:WordPress.DateTime.RestrictedFunctions.date_date:out_of_scope',
	'/privateincludes/modules/staff-tasks/notifications.php:147:17:ERROR:WordPress.DateTime.RestrictedFunctions.date_date:out_of_scope',
	'/privateincludes/modules/staff-tasks/notifications.php:234:11:ERROR:WordPress.DateTime.RestrictedFunctions.date_date:out_of_scope',
	'/privateincludes/modules/staff-tasks/notifications.php:237:11:ERROR:WordPress.DateTime.RestrictedFunctions.date_date:out_of_scope',
	'/privateincludes/modules/staff-tasks/notifications.php:239:10:ERROR:WordPress.DateTime.RestrictedFunctions.date_date:out_of_scope',
	'/privateincludes/modules/staff-tasks/notifications.php:252:12:ERROR:WordPress.DateTime.RestrictedFunctions.date_date:out_of_scope',
	'/privateincludes/ticketing/ticket-integrity-monitor.php:621:9:ERROR:WordPress.DateTime.RestrictedFunctions.date_date:ticketing_owned',
	'/privateincludes/ticketing/ticket-integrity-monitor.php:747:75:ERROR:WordPress.DateTime.RestrictedFunctions.date_date:ticketing_owned',
	'/privateincludes/ticketing/ticket-integrity-monitor.php:748:76:ERROR:WordPress.DateTime.RestrictedFunctions.date_date:ticketing_owned',
);
g14v2_same($expectedRows, $actualRows, 'Historical DateTime rows changed.');
$owned = array_values(array_filter($dateRows, static fn(array $row): bool => ($row['classification'] ?? '') === 'ticketing_owned'));
$outOfScope = array_values(array_filter($dateRows, static fn(array $row): bool => ($row['classification'] ?? '') === 'out_of_scope'));
g14v2_same(5, count($owned), 'Historical ticketing-owned row classification changed.');
g14v2_same(9, count($outOfScope), 'Historical out-of-scope row classification changed.');

$reproducibleJson = json_encode($provenance['reproducible_content'] ?? array(), JSON_UNESCAPED_SLASHES);
g14v2_assert(is_string($reproducibleJson), 'Unable to encode reproducible-content section.');
g14v2_assert(strpos($reproducibleJson, $historicalZipHash) === false, 'Historical ZIP identifier was confused with reproducible content identity.');
g14v2_assert(strpos($reproducibleJson, $historicalJsonHash) === false, 'Historical strict JSON identifier was confused with reproducible content identity.');
g14v2_assert($manifestHash !== $historicalZipHash && $manifestHash !== $historicalJsonHash, 'Reproducible manifest hash must remain distinct from historical artifact identifiers.');

$g15Source = (string) file_get_contents(__DIR__ . '/g15-ticketing-date-windows.php');
$fixtureSource = (string) file_get_contents($provenancePath);
g14v2_assert(strpos($g15Source, 'wporg-dbzero-g14.qulnlt') === false, 'G15 still depends on the lost temporary G14 artifact path.');
g14v2_assert(strpos($fixtureSource, 'wporg-dbzero-g14.qulnlt') === false, 'Provenance fixture contains a temporary artifact dependency.');

$tempRoot = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'bvmgr-g14v2-test-' . bin2hex(random_bytes(8));
$exportRoot = $tempRoot . '/source';
$tarPath = $tempRoot . '/source.tar';
$freshManifest = $tempRoot . '/fresh-manifest.json';
try {
	g14v2_assert(mkdir($exportRoot, 0775, true), 'Unable to create historical source export directory.');
	$archive = g14v2_run(array('git', '-C', $root, 'archive', '--format=tar', '--output=' . $tarPath, $historicalSource));
	g14v2_same(0, $archive['exit_code'], 'Unable to export exact historical source: ' . trim($archive['stderr']));
	$extract = g14v2_run(array('tar', '-xf', $tarPath, '-C', $exportRoot));
	g14v2_same(0, $extract['exit_code'], 'Unable to extract exact historical source: ' . trim($extract['stderr']));
	$generate = g14v2_run(
		array(
			PHP_BINARY,
			$root . '/scripts/generate-g14-g15-provenance-v2.php',
			'--source=' . $exportRoot,
			'--output=' . $freshManifest,
			'--allow-exported-source',
		),
		$root
	);
	g14v2_same(0, $generate['exit_code'], 'Fresh historical content-manifest generation failed: ' . trim($generate['stderr']));
	g14v2_same($manifestHash, hash_file('sha256', $freshManifest), 'Fresh historical package staging does not reproduce the committed manifest.');
	g14v2_same((string) file_get_contents($manifestPath), (string) file_get_contents($freshManifest), 'Fresh historical content manifest is not byte-identical.');
} finally {
	g14v2_delete_path($tempRoot);
}

fwrite(STDOUT, "PASS: {$GLOBALS['g14v2_assertions']} G14/G15 provenance-v2 migration assertions.\n");
