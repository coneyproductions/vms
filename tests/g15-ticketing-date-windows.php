<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
	define('ABSPATH', __DIR__ . '/');
}
if (!defined('DAY_IN_SECONDS')) {
	define('DAY_IN_SECONDS', 86400);
}

// G15 ticketing date-window regression coverage is assembled below.
$GLOBALS['g15_assertion_count'] = 0;

function g15_assert(bool $condition, string $message): void
{
	$GLOBALS['g15_assertion_count']++;
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function g15_same($expected, $actual, string $message): void
{
	g15_assert(
		$expected === $actual,
		$message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true)
	);
}

$root = dirname(__DIR__);
$shadow_root = dirname($root, 2) . '/vms';
$provenance_dir = __DIR__ . '/fixtures/g14-g15-provenance-v2';
$provenance_path = $provenance_dir . '/provenance.json';
$provenance_hash = '464998c7c4788abe09e31ab2a61904ef6d5cf05f8741bb0445f9600a980e7338';
$content_manifest_path = $provenance_dir . '/historical-package-content-manifest.json';
$content_manifest_hash = '567acf156c2e3f8d9a06a97913eedf5a0513c91e76dd11e59f1d7dcf37adc1c5';
$historical_source_commit = '2c8f790b9128d547b8bc0a27a714253fb6671bea';

function g15_read(string $path): string
{
	$source = file_get_contents($path);
	if (!is_string($source) || $source === '') {
		throw new RuntimeException('Unable to read source: ' . $path);
	}
	return $source;
}

function g15_extract_function(string $source, string $name): string
{
	$sourceName = $name;
	$start = strpos($source, 'function ' . $sourceName . '(');
	if ($start === false && strpos($name, 'bvmgr_') === 0) {
		$sourceName = 'vms_' . substr($name, strlen('bvmgr_'));
		$start = strpos($source, 'function ' . $sourceName . '(');
	}
	$brace = $start === false ? false : strpos($source, '{', $start);
	if ($start === false || $brace === false) {
		throw new RuntimeException('Unable to locate function: ' . $name);
	}

	$depth = 1;
	for ($index = $brace + 1, $length = strlen($source); $index < $length; $index++) {
		$depth += $source[$index] === '{' ? 1 : 0;
		$depth -= $source[$index] === '}' ? 1 : 0;
		if ($depth === 0) {
			return substr($source, $start, ($index - $start) + 1);
		}
	}
	throw new RuntimeException('Unable to parse function: ' . $name);
}

/** @return array{legacy_to_canonical:array<string,string>,canonical_to_legacy:array<string,string>} */
function g15_load_b3_identifier_maps(string $root): array
{
	$map = json_decode(g15_read($root . '/docs/wporg-prefix-b3-function-map.json'), true);
	g15_assert(is_array($map) && ($map['schema_version'] ?? null) === 1, 'Unable to load the frozen B3 function map.');
	$legacyToCanonical = array();
	$canonicalToLegacy = array();
	foreach ((array) ($map['mappings'] ?? array()) as $entry) {
		$legacy = (string) ($entry['legacy_identifier'] ?? '');
		$canonical = (string) ($entry['canonical_identifier'] ?? '');
		if ($legacy === '' || $canonical === '') {
			continue;
		}
		$legacyToCanonical[$legacy] = $canonical;
		$canonicalToLegacy[$canonical] = $legacy;
	}
	g15_same(4521, count($legacyToCanonical), 'Frozen B3 function map count changed.');
	g15_same(4521, count($canonicalToLegacy), 'Frozen B3 reverse function map count changed.');

	return array('legacy_to_canonical' => $legacyToCanonical, 'canonical_to_legacy' => $canonicalToLegacy);
}

function g15_normalize_b3_identifiers(string $source): string
{
	return strtr($source, (array) ($GLOBALS['g15_b3_identifier_maps']['legacy_to_canonical'] ?? array()));
}

function g15_fragment_for_source_style(string $fragment, string $source): string
{
	$fragment = g15_normalize_b3_identifiers($fragment);
	if (strpos($source, 'function bvmgr_') !== false) {
		return $fragment;
	}

	return strtr($fragment, (array) ($GLOBALS['g15_b3_identifier_maps']['canonical_to_legacy'] ?? array()));
}

/**
 * @param array<int,array{current:string,historical:string,rows:int}> $specs
 * @return array{source:string,replacements:int,rows:int}
 */
function g15_project_historical_source(string $source, array $specs, string $label): array
{
	$replacements = 0;
	$rows = 0;
	foreach ($specs as $index => $spec) {
		g15_same(1, substr_count($source, $spec['current']), $label . ' projection fragment count changed at index ' . $index . '.');
		$count = 0;
		$source = str_replace($spec['current'], $spec['historical'], $source, $count);
		g15_same(1, $count, $label . ' projection must replace each G15 fragment once.');
		$replacements += $count;
		$rows += $spec['rows'];
	}
	return array('source' => $source, 'replacements' => $replacements, 'rows' => $rows);
}

function g15_prepare_eval_function(string $source, string $name, string $test_name, array $replacements = array()): string
{
	$function = g15_extract_function($source, $name);
	$count = 0;
	$function = str_replace('function ' . $name . '(', 'function ' . $test_name . '(', $function, $count);
	g15_same(1, $count, 'Unable to rename runtime function ' . $name . '.');
	foreach ($replacements as $current => $replacement) {
		$count = 0;
		$function = str_replace($current, $replacement, $function, $count);
		g15_same(1, $count, 'Unable to apply deterministic replacement in ' . $name . '.');
	}
	return $function;
}

function g15_project_g16_phase_logging(string $source, string $label): string
{
	$fixture = g15_read(__DIR__ . '/g16-operational-logging-group-c.php');
	$start = strpos($fixture, "\$g16c_reverse_specs['phase'] = array(");
	$end = strpos($fixture, "\n\$g16c_ticket_shutdown_historical", (int) $start);
	g15_assert($start !== false && $end !== false, $label . ' G16 PhaseB projection fixture bounds changed.');
	$code = substr($fixture, (int) $start, (int) $end - (int) $start);
	eval($code);
	g15_assert(isset($g16c_reverse_specs['phase']) && is_array($g16c_reverse_specs['phase']), $label . ' G16 PhaseB projection fixture failed to load.');
	foreach ($g16c_reverse_specs['phase'] as $index => $spec) {
		$current = g15_fragment_for_source_style($spec['current'], $source);
		$historical = g15_fragment_for_source_style($spec['historical'], $source);
		$currentCount = substr_count($source, $current);
		$historicalCount = substr_count($source, $historical);
		g15_assert(
			($currentCount === 1 && $historicalCount === 0) || ($currentCount === 0 && $historicalCount === 1),
			$label . ' G16 PhaseB current/historical fragment state changed at ' . $index . '.'
		);
		if ($currentCount === 1) {
			$source = str_replace($current, $historical, $source, $count);
			g15_same(1, $count, $label . ' G16 PhaseB reverse count changed at ' . $index . '.');
		}
	}
	return $source;
}

function g15_project_g16_monitor_logging(string $source, string $label): string
{
	if (
		strpos($source, 'function bvmgr_ticket_integrity_fatal_operation(') === false
		&& strpos($source, 'function vms_ticket_integrity_fatal_operation(') === false
	) {
		return $source;
	}

	$first = g15_extract_function($source, 'bvmgr_ticket_integrity_fatal_operation');
	$start = strpos($source, $first);
	$last = g15_extract_function($source, 'bvmgr_ticket_integrity_fatal_operational_context');
	$last_start = strpos($source, $last, (int) $start);
	g15_assert($start !== false && $last_start !== false, $label . ' G16 monitor helper bounds changed.');
	$block = substr($source, (int) $start, (int) $last_start - (int) $start + strlen($last));
	g15_same('95f1c890527cb19d2f69bc20c4a7b972fb4d77efcf5c76800b0f5a749dde310f', hash('sha256', $block), $label . ' current-candidate G16 monitor helper block changed.');
	$source = str_replace($block . "\n\n", '', $source, $count);
	g15_same(1, $count, $label . ' G16 monitor helper removal changed.');
	$current = g15_extract_function($source, 'bvmgr_ticket_integrity_fatal_guard_shutdown');
	g15_same('134a85c5aa91bb5c6a32fbe01a070f0cd74620a1c59c0bf6f57a74a37bcada6a', hash('sha256', $current), $label . ' current-candidate G16 monitor shutdown changed.');
	$fixture = g15_read(__DIR__ . '/g16-operational-logging-group-c.php');
	g15_same(1, preg_match('/\$g16c_ticket_shutdown_historical = \'([^\']+)\'/s', $fixture, $match), $label . ' G16 historical shutdown fixture changed.');
	$historical = base64_decode($match[1], true);
	g15_assert(is_string($historical) && $historical !== '', $label . ' G16 historical shutdown decode failed.');
	$historical = g15_fragment_for_source_style($historical, $source);
	$source = str_replace($current, $historical, $source, $count);
	g15_same(1, $count, $label . ' G16 shutdown reverse count changed.');
	return $source;
}

function g15_ends_with(string $haystack, string $needle): bool
{
	return $needle === '' || substr($haystack, -strlen($needle)) === $needle;
}

/** @return array{exit_code:int,stdout:string,stderr:string} */
function g15_run_command(array $command, ?string $cwd = null): array
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
	g15_assert(is_resource($process), 'Unable to start provenance command: ' . implode(' ', $command));
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

$GLOBALS['g15_b3_identifier_maps'] = g15_load_b3_identifier_maps($root);

$paths = array(
	'monitor' => 'includes/ticketing/ticket-integrity-monitor.php',
	'phase_b' => 'includes/integrations/ticketing-phase-b.php',
);
$sources = array('mirror' => array(), 'shadow' => array());
foreach ($paths as $key => $path) {
	$sources['mirror'][$key] = g15_read($root . '/' . $path);
	$sources['shadow'][$key] = g15_read($shadow_root . '/' . $path);
}

g15_same($provenance_hash, hash_file('sha256', $provenance_path), 'G14/G15 provenance-v2 fixture SHA-256 changed.');
g15_same($content_manifest_hash, hash_file('sha256', $content_manifest_path), 'Historical package content-manifest SHA-256 changed.');

$provenance = json_decode(g15_read($provenance_path), true);
$content_manifest = json_decode(g15_read($content_manifest_path), true);
g15_assert(is_array($provenance), 'G14/G15 provenance-v2 fixture must decode to an array.');
g15_assert(is_array($content_manifest), 'Historical package content manifest must decode to an array.');
g15_same(2, $provenance['schema_version'] ?? null, 'G14/G15 provenance schema version changed.');
g15_same('g14-g15-provenance-v2', $provenance['fixture_version'] ?? null, 'G14/G15 provenance fixture version changed.');
g15_same($historical_source_commit, $provenance['historical_source']['commit'] ?? null, 'Historical source identity changed.');
g15_same('1.2.0', $provenance['historical_source']['public_release_version'] ?? null, 'Historical public-release version changed.');
g15_same(
	'fec238a519108c7013659b4114e69e9aad93c5c6f864551d4290737d30a609e5',
	$provenance['historical_artifacts']['original_zip']['recorded_sha256'] ?? null,
	'Historical original ZIP identifier changed.'
);
g15_same(2054980, $provenance['historical_artifacts']['original_zip']['recorded_size_bytes'] ?? null, 'Historical original ZIP size changed.');
g15_same(
	'c5fe4d23b3cdf632f239632a23f2c58f9ccf7b8e293ff4b9e71f65101527aa17',
	$provenance['historical_artifacts']['strict_plugin_check_json']['recorded_sha256'] ?? null,
	'Historical original strict Plugin Check JSON identifier changed.'
);
g15_same($content_manifest_hash, $provenance['reproducible_content']['manifest_sha256'] ?? null, 'Provenance content-manifest identity changed.');
g15_same(372, $provenance['reproducible_content']['file_count'] ?? null, 'Provenance package file count changed.');

$gitIdentity = g15_run_command(array('git', '-C', $root, 'rev-parse', $historical_source_commit . '^{commit}'));
g15_same(0, $gitIdentity['exit_code'], 'Historical source commit is unavailable in repository history.');
g15_same($historical_source_commit, trim($gitIdentity['stdout']), 'Historical source commit resolves to an unexpected object.');

g15_same(1, $content_manifest['schema_version'] ?? null, 'Historical content-manifest schema version changed.');
g15_same('g14-public-package-content-v1', $content_manifest['manifest_version'] ?? null, 'Historical content-manifest version changed.');
g15_same($historical_source_commit, $content_manifest['historical_source']['commit'] ?? null, 'Content-manifest source identity changed.');
g15_same('backstage-venue-manager', $content_manifest['package']['root_name'] ?? null, 'Historical package root changed.');
g15_same(372, $content_manifest['package']['file_count'] ?? null, 'Historical content-manifest file count changed.');
g15_same(0, $content_manifest['package']['symlink_count'] ?? null, 'Historical content manifest must contain zero symlinks.');
g15_same('excluded', $content_manifest['content_identity']['filesystem_mtimes'] ?? null, 'Filesystem mtimes must remain outside historical content identity.');

$manifestFiles = $content_manifest['files'] ?? null;
g15_assert(is_array($manifestFiles), 'Historical content manifest files must be an array.');
g15_same(372, count($manifestFiles), 'Historical content manifest must list exactly 372 files.');
$previousManifestPath = '';
foreach ($manifestFiles as $index => $entry) {
	g15_assert(is_array($entry), 'Historical content-manifest entry must be an array at index ' . $index . '.');
	$entryPath = (string) ($entry['path'] ?? '');
	g15_assert($entryPath !== '' && $entryPath[0] !== '/' && strpos($entryPath, '\\') === false, 'Historical manifest path is not normalized at index ' . $index . '.');
	g15_assert($previousManifestPath === '' || strcmp($previousManifestPath, $entryPath) < 0, 'Historical manifest paths are not strictly sorted and unique.');
	g15_same('regular_file', $entry['type'] ?? null, 'Historical package contains an unsupported entry type at ' . $entryPath . '.');
	g15_same('0644', $entry['mode'] ?? null, 'Historical staged file mode changed at ' . $entryPath . '.');
	g15_assert(is_int($entry['size_bytes'] ?? null) && $entry['size_bytes'] >= 0, 'Historical file size is invalid at ' . $entryPath . '.');
	g15_assert(preg_match('/^[a-f0-9]{64}$/', (string) ($entry['sha256'] ?? '')) === 1, 'Historical file SHA-256 is invalid at ' . $entryPath . '.');
	g15_assert(!array_key_exists('mtime', $entry) && !array_key_exists('mtime_unix', $entry), 'Historical content identity must not include mtimes.');
	$previousManifestPath = $entryPath;
}

$scanEvidence = $provenance['historical_scan_evidence'] ?? null;
g15_assert(is_array($scanEvidence), 'Historical scan evidence must be present.');
g15_same($historical_source_commit, $scanEvidence['source_commit'] ?? null, 'Historical scan source identity changed.');
g15_same(181, $scanEvidence['totals']['findings'] ?? null, 'Authoritative finding total changed.');
g15_same(array('ERROR' => 139, 'WARNING' => 42), $scanEvidence['totals']['severity'] ?? null, 'Authoritative severity split changed.');

$date_code = 'WordPress.DateTime.RestrictedFunctions.date_date';
$date_message = 'date() is affected by runtime timezone changes which can cause date/time to be incorrectly displayed. Use gmdate() instead.';
$date_rows = $scanEvidence['datetime_findings'] ?? null;
g15_assert(is_array($date_rows), 'Historical DateTime rows must be an array.');
g15_same(0, $scanEvidence['totals']['database_sql_blockers'] ?? null, 'Authoritative DB/SQL blocker count must remain zero.');
g15_same(14, count($date_rows), 'Authoritative DateTime row count must remain 14.');
g15_same(14, $scanEvidence['totals']['datetime_findings'] ?? null, 'Authoritative DateTime total changed.');
g15_same(42, $scanEvidence['totals']['logging_findings'] ?? null, 'Authoritative logging row count must remain 42.');
foreach ($date_rows as $row) {
	g15_same($date_code, $row['code'] ?? null, 'Historical DateTime evidence contains an unexpected rule code.');
	g15_same($date_message, $row['message'] ?? null, 'Historical DateTime evidence contains an unexpected retained message.');
}

$expected_owned_rows = array(
	array('file' => $paths['phase_b'], 'line' => 1616, 'column' => 79, 'type' => 'ERROR', 'code' => $date_code),
	array('file' => $paths['phase_b'], 'line' => 3087, 'column' => 11, 'type' => 'ERROR', 'code' => $date_code),
	array('file' => $paths['monitor'], 'line' => 621, 'column' => 9, 'type' => 'ERROR', 'code' => $date_code),
	array('file' => $paths['monitor'], 'line' => 747, 'column' => 75, 'type' => 'ERROR', 'code' => $date_code),
	array('file' => $paths['monitor'], 'line' => 748, 'column' => 76, 'type' => 'ERROR', 'code' => $date_code),
);
$actual_owned_rows = array();
foreach ($date_rows as $row) {
	foreach ($paths as $path) {
		if (!g15_ends_with((string) ($row['file'] ?? ''), $path)) {
			continue;
		}
		$actual_owned_rows[] = array(
			'file' => $path,
			'line' => (int) ($row['line'] ?? 0),
			'column' => (int) ($row['column'] ?? 0),
			'type' => (string) ($row['type'] ?? ''),
			'code' => (string) ($row['code'] ?? ''),
		);
		break;
	}
}
$row_signature = static fn(array $row): string => implode(':', array($row['file'], $row['line'], $row['column'], $row['type'], $row['code']));
$expected_signatures = array_map($row_signature, $expected_owned_rows);
$actual_signatures = array_map($row_signature, $actual_owned_rows);
sort($expected_signatures);
sort($actual_signatures);
g15_same($expected_signatures, $actual_signatures, 'G15 P2 artifact ownership must remain exactly five DateTime rows.');
g15_same(9, count($date_rows) - count($actual_owned_rows), 'Exactly nine G15 date rows must remain outside this ticketing child.');
$fixtureOwnedRows = array_values(array_filter($date_rows, static fn(array $row): bool => ($row['classification'] ?? '') === 'ticketing_owned'));
$fixtureOutOfScopeRows = array_values(array_filter($date_rows, static fn(array $row): bool => ($row['classification'] ?? '') === 'out_of_scope'));
g15_same(5, count($fixtureOwnedRows), 'Provenance v2 must classify exactly five ticketing-owned DateTime rows.');
g15_same(9, count($fixtureOutOfScopeRows), 'Provenance v2 must classify exactly nine DateTime rows outside the ticketing child.');
g15_same(5, $scanEvidence['totals']['ticketing_owned_datetime_findings'] ?? null, 'Recorded ticketing-owned DateTime total changed.');
g15_same(9, $scanEvidence['totals']['out_of_scope_datetime_findings'] ?? null, 'Recorded out-of-scope DateTime total changed.');

$owned_functions = array(
	'monitor' => array('bvmgr_ticket_integrity_format_datetime', 'bvmgr_ticket_integrity_build_targets'),
	'phase_b' => array('bvmgr_ticketing_b_resolve_sales_window', 'bvmgr_ticketing_v2_get_plan_sales_window_defaults'),
);
foreach (array('mirror', 'shadow') as $tree) {
	$owned_source = '';
	foreach ($owned_functions as $source_key => $function_names) {
		foreach ($function_names as $function_name) {
			$owned_source .= "\n" . g15_extract_function($sources[$tree][$source_key], $function_name);
		}
	}
	g15_same(0, preg_match_all('/(?<![A-Za-z0-9_])date\s*\(/', $owned_source), $tree . ' owned functions must contain zero native date() calls.');
	g15_same(5, preg_match_all('/(?<![A-Za-z0-9_])wp_date\s*\(/', $owned_source), $tree . ' owned functions must contain exactly five wp_date() calls.');
	g15_assert(
		preg_match('/phpcs:(?:disable|enable|ignoreFile|ignore)[^\r\n]*(?:WordPress\.DateTime|RestrictedFunctions\.date_date)/i', $owned_source) !== 1,
		$tree . ' owned functions must not suppress DateTime findings.'
	);
	g15_assert(strpos($owned_source, "function_exists('wp_date')") === false, $tree . ' owned functions retain a dead wp_date() fallback.');
	g15_assert(strpos($owned_source, "function_exists('wp_timezone')") === false, $tree . ' owned functions retain a dead wp_timezone() fallback.');
}

$monitor_specs = array(
	array(
		'current' => "\treturn wp_date('Y-m-d g:i a', \$timestamp, wp_timezone());",
		'historical' => "\tif (function_exists('wp_date')) {\n\t\treturn wp_date('Y-m-d g:i a', \$timestamp, wp_timezone());\n\t}\n\n\treturn date('Y-m-d g:i a', \$timestamp);",
		'rows' => 1,
	),
	array(
		'current' => "\t\$tz = wp_timezone();\n\t\$start_date = wp_date('Y-m-d', \$now, \$tz);\n\t\$end_date = wp_date('Y-m-d', \$cutoff, \$tz);",
		'historical' => "\t\$tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');\n\t\$start_date = function_exists('wp_date') ? wp_date('Y-m-d', \$now, \$tz) : date('Y-m-d', \$now);\n\t\$end_date = function_exists('wp_date') ? wp_date('Y-m-d', \$cutoff, \$tz) : date('Y-m-d', \$cutoff);",
		'rows' => 2,
	),
);
$phase_specs = array(
	array(
		'current' => "    \$tz = wp_timezone();\n    \$now = wp_date('Y-m-d H:i:s', time(), \$tz);",
		'historical' => "    \$tz = function_exists('wp_timezone') ? wp_timezone() : null;\n    \$now = function_exists('wp_date') ? wp_date('Y-m-d H:i:s', time(), \$tz) : date('Y-m-d H:i:s');",
		'rows' => 1,
	),
	array(
		'current' => "    \$tz = wp_timezone();\n\n    \$sales_start = wp_date('Y-m-d H:i:s', time(), \$tz);",
		'historical' => "    \$tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');\n\n    \$sales_start = function_exists('wp_date')\n        ? wp_date('Y-m-d H:i:s', time(), \$tz)\n        : date('Y-m-d H:i:s');",
		'rows' => 1,
	),
);
$historical_hashes = array(
	'mirror' => array('monitor' => '59a49889939ee901146eda2a8bc044438ca6923213dd0f449a800c646af3b1f7', 'phase_b' => '2621df5d27cf64200eabc6f5ece2cc6fec144c5b707baa7fb6597cf20923412b'),
	'shadow' => array('monitor' => '1b5c31b238cc6dc0ddb8a03409d8fab69d070c4e447faf47705b30b7dbd41e7c', 'phase_b' => '4e501fdb014ab1416ea8b8db1ad2944cd675ff0d2c00d84daf0262c78a9aa78c'),
);

foreach (array('mirror', 'shadow') as $tree) {
	$monitor_pre_g16 = g15_project_g16_monitor_logging($sources[$tree]['monitor'], $tree . ':monitor');
	$phase_pre_g16 = g15_project_g16_phase_logging($sources[$tree]['phase_b'], $tree . ':phase_b');
	$monitor_projection = g15_project_historical_source($monitor_pre_g16, $monitor_specs, $tree . ':monitor');
	$phase_projection = g15_project_historical_source($phase_pre_g16, $phase_specs, $tree . ':phase_b');
	g15_same(2, $monitor_projection['replacements'], $tree . ' monitor projection replacement count changed.');
	g15_same(3, $monitor_projection['rows'], $tree . ' monitor projected row count changed.');
	g15_same(2, $phase_projection['replacements'], $tree . ' Phase B projection replacement count changed.');
	g15_same(2, $phase_projection['rows'], $tree . ' Phase B projected row count changed.');
	g15_same($historical_hashes[$tree]['monitor'], hash('sha256', $monitor_projection['source']), $tree . ' monitor changed outside the exact G15 projection.');
	g15_same($historical_hashes[$tree]['phase_b'], hash('sha256', $phase_projection['source']), $tree . ' Phase B changed outside the exact G15 projection.');
}

$mutated_monitor = str_replace("'post_status' => 'publish'", "'post_status' => 'draft'", $sources['mirror']['monitor'], $mutation_count);
g15_same(1, $mutation_count, 'Mutation control must alter one non-G15 monitor argument.');
$mutated_pre_g16 = g15_project_g16_monitor_logging($mutated_monitor, 'mutated:monitor');
$mutated_projection = g15_project_historical_source($mutated_pre_g16, $monitor_specs, 'mutated:monitor');
g15_assert(
	hash('sha256', $mutated_projection['source']) !== $historical_hashes['mirror']['monitor'],
	'Historical projection must reject a non-G15 runtime mutation.'
);

$mirror_build_annotations = array(
	" // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Ticket Integrity intentionally orders each published Event Plan batch by canonical event-date metadata across the configured date window.",
	" // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Ticket Integrity intentionally paginates the complete published, linked Event Plan set inside the configured date window before applying ticketing and activity checks.",
	"\t\t\t\t// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.SuppressFilters_suppress_filters -- Ticket Integrity scans require the canonical unfiltered event-plan dataset; query scope is bounded by published status, linked TEC event, the date window, and batch pagination.\n",
);
$mirror_build = g15_extract_function($sources['mirror']['monitor'], 'bvmgr_ticket_integrity_build_targets');
$shadow_build = g15_extract_function($sources['shadow']['monitor'], 'bvmgr_ticket_integrity_build_targets');
foreach ($mirror_build_annotations as $annotation) {
	g15_same(1, substr_count($mirror_build, $annotation), 'Mirror must retain each mirror-only query rationale.');
	g15_same(0, substr_count($shadow_build, $annotation), 'Shadow must not gain a mirror-only query rationale.');
	$mirror_build = str_replace($annotation, '', $mirror_build, $annotation_count);
	g15_same(1, $annotation_count, 'Mirror parity projection must strip each query rationale once.');
}
g15_same(
	g15_normalize_b3_identifiers($mirror_build),
	g15_normalize_b3_identifiers($shadow_build),
	'Target-builder behavior must match across mirror/shadow after rationale and B3 identifier projection.'
);
$mirrorFormatter = g15_normalize_b3_identifiers(g15_extract_function($sources['mirror']['monitor'], 'bvmgr_ticket_integrity_format_datetime'));
$shadowFormatter = g15_normalize_b3_identifiers(g15_extract_function($sources['shadow']['monitor'], 'bvmgr_ticket_integrity_format_datetime'));
$shadowFormatter = str_replace("__('Never', 'vms')", "__('Never', 'backstage-venue-manager')", $shadowFormatter, $domainProjectionCount);
g15_same(1, $domainProjectionCount, 'Shadow formatter must retain its one tree-specific legacy text domain.');
g15_same(
	$mirrorFormatter,
	$shadowFormatter,
	'Monitor formatter must match across mirror/shadow after B3 identifier and tree-specific text-domain projection.'
);
foreach ($owned_functions['phase_b'] as $function_name) {
	g15_same(
		g15_normalize_b3_identifiers(g15_extract_function($sources['mirror']['phase_b'], $function_name)),
		g15_normalize_b3_identifiers(g15_extract_function($sources['shadow']['phase_b'], $function_name)),
		'Phase B owned function must match across mirror/shadow after B3 identifier projection: ' . $function_name
	);
}
g15_assert($sources['mirror']['monitor'] !== $sources['shadow']['monitor'], 'Monitor whole-file divergence must remain preserved.');
g15_assert($sources['mirror']['phase_b'] !== $sources['shadow']['phase_b'], 'Phase B whole-file divergence must remain preserved.');

$GLOBALS['g15_now'] = 0;
$GLOBALS['g15_timezone_name'] = 'UTC';
$GLOBALS['g15_wp_date_calls'] = array();
$GLOBALS['g15_query_calls'] = array();
$GLOBALS['g15_query_queue'] = array();
$GLOBALS['g15_batch_size'] = 100;
$GLOBALS['g15_event_starts'] = array();
$GLOBALS['g15_event_ends'] = array();
$GLOBALS['g15_plan_anchors'] = array();

function g15_now(): int
{
	return (int) $GLOBALS['g15_now'];
}

function absint($value): int
{
	return abs((int) $value);
}

function sanitize_key($value): string
{
	$value = is_scalar($value) ? strtolower((string) $value) : '';
	$clean = preg_replace('/[^a-z0-9_\-]/', '', $value);
	return is_string($clean) ? $clean : '';
}

function __($text, $domain = ''): string
{
	unset($domain);
	return (string) $text;
}

function wp_timezone(): DateTimeZone
{
	return new DateTimeZone((string) $GLOBALS['g15_timezone_name']);
}

function wp_date(string $format, ?int $timestamp = null, ?DateTimeZone $timezone = null): string
{
	$timestamp = $timestamp ?? g15_now();
	$timezone = $timezone instanceof DateTimeZone ? $timezone : wp_timezone();
	$GLOBALS['g15_wp_date_calls'][] = array(
		'format' => $format,
		'timestamp' => $timestamp,
		'timezone' => $timezone->getName(),
	);
	return (new DateTimeImmutable('@' . $timestamp))->setTimezone($timezone)->format($format);
}

function apply_filters(string $hook_name, $value)
{
	if ($hook_name === 'vms_ticket_integrity_target_query_batch_size') {
		return $GLOBALS['g15_batch_size'];
	}
	return $value;
}

function bvmgr_ticket_integrity_get_settings(): array
{
	return array('days_ahead' => 30);
}

function bvmgr_ticketing_b_meta_key(string $field, string $fallback): string
{
	unset($field);
	return $fallback;
}

function get_post_meta(int $post_id, string $key, bool $single = false)
{
	unset($post_id, $key);
	return $single ? '' : array();
}

function wp_reset_postdata(): void
{
}

final class WP_Query
{
	/** @var mixed */
	public $posts;
	public int $max_num_pages;

	public function __construct(array $args)
	{
		$GLOBALS['g15_query_calls'][] = $args;
		$entry = $GLOBALS['g15_query_queue'] === array()
			? array('posts' => array(), 'max_num_pages' => 0)
			: array_shift($GLOBALS['g15_query_queue']);
		$this->posts = $entry['posts'] ?? array();
		$this->max_num_pages = (int) ($entry['max_num_pages'] ?? 0);
	}
}

function bvmgr_ticketing_b_get_tec_event_start(int $tec_event_id): string
{
	return (string) ($GLOBALS['g15_event_starts'][$tec_event_id] ?? '');
}

function bvmgr_ticketing_b_get_tec_event_end(int $tec_event_id): string
{
	return (string) ($GLOBALS['g15_event_ends'][$tec_event_id] ?? '');
}

function bvmgr_ticketing_v2_get_plan_event_anchor_datetimes(int $plan_id): array
{
	$anchors = $GLOBALS['g15_plan_anchors'][$plan_id] ?? array();
	return is_array($anchors) ? $anchors : array();
}

eval(g15_prepare_eval_function($sources['mirror']['monitor'], 'bvmgr_ticket_integrity_format_datetime', 'g15_ticket_integrity_format_datetime'));
eval(
	g15_prepare_eval_function(
		$sources['mirror']['monitor'],
		'bvmgr_ticket_integrity_build_targets',
		'g15_ticket_integrity_build_targets',
		array("\t\$now = time();" => "\t\$now = g15_now();")
	)
);
eval(g15_extract_function($sources['mirror']['phase_b'], 'bvmgr_ticketing_v2_normalize_sales_window_value'));
eval(g15_extract_function($sources['mirror']['phase_b'], 'bvmgr_ticketing_v2_normalize_relative_days'));
eval(g15_extract_function($sources['mirror']['phase_b'], 'bvmgr_ticketing_v2_relative_days_before_datetime'));
eval(
	g15_prepare_eval_function(
		$sources['mirror']['phase_b'],
		'bvmgr_ticketing_b_resolve_sales_window',
		'g15_ticketing_b_resolve_sales_window',
		array("wp_date('Y-m-d H:i:s', time(), \$tz)" => "wp_date('Y-m-d H:i:s', g15_now(), \$tz)")
	)
);
eval(
	g15_prepare_eval_function(
		$sources['mirror']['phase_b'],
		'bvmgr_ticketing_v2_get_plan_sales_window_defaults',
		'g15_ticketing_v2_get_plan_sales_window_defaults',
		array("wp_date('Y-m-d H:i:s', time(), \$tz)" => "wp_date('Y-m-d H:i:s', g15_now(), \$tz)")
	)
);

$format_timestamp = (new DateTimeImmutable('2026-03-08 05:30:00', new DateTimeZone('UTC')))->getTimestamp();
$GLOBALS['g15_timezone_name'] = 'UTC';
g15_same('Never', g15_ticket_integrity_format_datetime(0), 'Zero timestamp must retain the Never sentinel.');
g15_same('2026-03-08 5:30 am', g15_ticket_integrity_format_datetime(-$format_timestamp), 'Negative timestamps must retain absint normalization.');
$GLOBALS['g15_timezone_name'] = 'America/Chicago';
g15_same('2026-03-07 11:30 pm', g15_ticket_integrity_format_datetime($format_timestamp), 'Formatter must use the site timezone at the DST boundary.');

/** @return array<string,mixed> */
function g15_run_target_window(string $timezone_name, int $now, int $days_ahead): array
{
	$GLOBALS['g15_timezone_name'] = $timezone_name;
	$GLOBALS['g15_now'] = $now;
	$GLOBALS['g15_wp_date_calls'] = array();
	$GLOBALS['g15_query_calls'] = array();
	$GLOBALS['g15_query_queue'] = array(array('posts' => array(), 'max_num_pages' => 0));
	g15_ticket_integrity_build_targets(array('days_ahead' => $days_ahead));
	g15_same(1, count($GLOBALS['g15_query_calls']), 'Target scenario must issue one terminating WP_Query.');
	g15_same(2, count($GLOBALS['g15_wp_date_calls']), 'Target scenario must format exactly two bounds.');
	return $GLOBALS['g15_query_calls'][0];
}

$utc_now = (new DateTimeImmutable('2026-02-01 12:34:56', new DateTimeZone('UTC')))->getTimestamp();
$utc_args = g15_run_target_window('UTC', $utc_now, 7);
$utc_calls = $GLOBALS['g15_wp_date_calls'];
g15_same(7 * DAY_IN_SECONDS, $utc_calls[1]['timestamp'] - $utc_calls[0]['timestamp'], 'UTC horizon must remain exactly seven DAY_IN_SECONDS intervals.');
g15_same('2026-02-01', $utc_args['meta_query'][0]['value'][0] ?? null, 'UTC target start date changed.');
g15_same('2026-02-08', $utc_args['meta_query'][0]['value'][1] ?? null, 'UTC target end date changed.');

$expected_args = array(
	'post_type' => 'vms_event_plan',
	'post_status' => 'publish',
	'posts_per_page' => 100,
	'paged' => 1,
	'fields' => 'ids',
	'no_found_rows' => false,
	'meta_key' => '_vms_event_date',
	'orderby' => 'meta_value',
	'meta_type' => 'DATE',
	'order' => 'ASC',
	'meta_query' => array(
		array(
			'key' => '_vms_event_date',
			'value' => array('2026-02-01', '2026-02-08'),
			'compare' => 'BETWEEN',
			'type' => 'DATE',
		),
		array(
			'key' => '_vms_tec_event_id',
			'value' => 0,
			'compare' => '>',
			'type' => 'NUMERIC',
		),
	),
	'update_post_meta_cache' => false,
	'update_post_term_cache' => false,
	'cache_results' => false,
	'lazy_load_term_meta' => false,
	'suppress_filters' => true,
);
g15_same($expected_args, $utc_args, 'Complete Ticket Integrity target-query arguments changed.');

$spring_now = (new DateTimeImmutable('2026-03-08 00:30:00', new DateTimeZone('America/Chicago')))->getTimestamp();
$spring_args = g15_run_target_window('America/Chicago', $spring_now, 1);
$spring_calls = $GLOBALS['g15_wp_date_calls'];
g15_same(DAY_IN_SECONDS, $spring_calls[1]['timestamp'] - $spring_calls[0]['timestamp'], 'Spring DST horizon must remain exactly DAY_IN_SECONDS.');
g15_same(array('2026-03-08', '2026-03-09'), $spring_args['meta_query'][0]['value'] ?? null, 'Spring DST local date window changed.');

$fall_now = (new DateTimeImmutable('2026-11-01 00:30:00', new DateTimeZone('America/Chicago')))->getTimestamp();
$fall_args = g15_run_target_window('America/Chicago', $fall_now, 1);
$fall_calls = $GLOBALS['g15_wp_date_calls'];
g15_same(DAY_IN_SECONDS, $fall_calls[1]['timestamp'] - $fall_calls[0]['timestamp'], 'Fall DST horizon must remain exactly DAY_IN_SECONDS.');
g15_same(array('2026-11-01', '2026-11-01'), $fall_args['meta_query'][0]['value'] ?? null, 'Fall DST window must preserve exact-seconds arithmetic.');

$GLOBALS['g15_timezone_name'] = 'UTC';
$GLOBALS['g15_now'] = (new DateTimeImmutable('2026-01-15 12:00:00', new DateTimeZone('UTC')))->getTimestamp();
$GLOBALS['g15_event_starts'][10] = '2030-06-10T19:00';
$GLOBALS['g15_event_ends'][10] = '2030-06-10 22:00';
$GLOBALS['g15_event_starts'][20] = 'not-an-event-start';
$GLOBALS['g15_event_ends'][20] = 'not-an-event-end';
$now_string = '2026-01-15 12:00:00';

g15_same(
	array('start' => $now_string, 'end' => '2030-06-10 22:00:00'),
	g15_ticketing_b_resolve_sales_window(10, array()),
	'Blank Phase B window must retain now/event-end defaults and anchor normalization.'
);
g15_same(
	array('start' => $now_string, 'end' => '2030-06-10 20:00:00'),
	g15_ticketing_b_resolve_sales_window(10, array('sales_start' => '', 'sales_end' => '2030-06-10 20:00:00')),
	'One-sided Phase B end must retain the now start default.'
);
g15_same(
	array('start' => '2030-06-01 09:00:00', 'end' => '2030-06-10 22:00:00'),
	g15_ticketing_b_resolve_sales_window(10, array('sales_start' => '2030-06-01 09:00:00', 'sales_end' => '')),
	'One-sided Phase B start must retain the event-end default.'
);
g15_same(
	array('start' => $now_string, 'end' => $now_string),
	g15_ticketing_b_resolve_sales_window(20, array()),
	'Invalid Phase B anchors must fail closed to the existing now/now defaults.'
);
g15_same(
	array('start' => '2030-06-01 09:15:00', 'end' => '2030-06-08 21:30:00'),
	g15_ticketing_b_resolve_sales_window(10, array('sales_start' => '2030-06-01 09:15:00', 'sales_end' => '2030-06-08 21:30:00')),
	'Persisted Phase B window values inside the event boundary must remain unchanged.'
);
g15_same(
	array('start' => '2030-06-10 22:00:00', 'end' => '2030-06-10 22:00:00'),
	g15_ticketing_b_resolve_sales_window(10, array('sales_start' => '2030-06-11 00:00:00', 'sales_end' => '2030-06-12 00:00:00')),
	'Phase B must retain event-end and inverted-window clamps.'
);
g15_same(
	array('start' => '2030-06-08 19:00:00', 'end' => '2030-06-10 22:00:00'),
	g15_ticketing_b_resolve_sales_window(10, array('sales_start_relative_days' => '2', 'sales_end_relative_days' => '0')),
	'Phase B relative-day resolution must retain normalized event anchors.'
);

$GLOBALS['g15_plan_anchors'][30] = array('event_start' => '2031-05-01T19:30', 'event_end' => '2031-05-01 22:15');
$GLOBALS['g15_plan_anchors'][31] = array('event_start' => '2031-05-02T20:00', 'event_end' => 'invalid-end');
$GLOBALS['g15_plan_anchors'][32] = array('event_start' => 'invalid-start', 'event_end' => 'invalid-end');
g15_same(
	array('sales_start' => $now_string, 'sales_end' => ''),
	g15_ticketing_v2_get_plan_sales_window_defaults(0),
	'Invalid plan IDs must retain now/blank defaults.'
);
g15_same(
	array('sales_start' => $now_string, 'sales_end' => '2031-05-01 22:15:00'),
	g15_ticketing_v2_get_plan_sales_window_defaults(30),
	'Persisted plan event-end anchors must remain the sales-end default.'
);
g15_same(
	array('sales_start' => $now_string, 'sales_end' => '2031-05-02 20:00:00'),
	g15_ticketing_v2_get_plan_sales_window_defaults(31),
	'Invalid persisted event-end anchors must retain the event-start fallback.'
);
g15_same(
	array('sales_start' => $now_string, 'sales_end' => ''),
	g15_ticketing_v2_get_plan_sales_window_defaults(32),
	'Invalid persisted plan anchors must retain a blank sales-end default.'
);

fwrite(
	STDOUT,
	"PASS: {$GLOBALS['g15_assertion_count']} G15 ticketing provenance-v2, exact five-row inventory, site-local formatting, exact-seconds target windows, Phase B defaults/clamps, projections, and parity assertions.\n"
);
