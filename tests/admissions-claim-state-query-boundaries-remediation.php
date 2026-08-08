<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
	define('ABSPATH', dirname(__DIR__) . '/');
}

function vms_test_fail(string $message): void
{
	throw new RuntimeException($message);
}

function vms_test_assert_true(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}

	vms_test_fail($message);
}

/**
 * @param mixed $expected
 * @param mixed $actual
 */
function vms_test_assert_same($expected, $actual, string $message): void
{
	if ($expected === $actual) {
		return;
	}

	vms_test_fail(
		$message
		. "\nExpected: " . var_export($expected, true)
		. "\nActual: " . var_export($actual, true)
	);
}

function vms_test_assert_contains(string $needle, string $haystack, string $message): void
{
	vms_test_assert_true(
		strpos($haystack, $needle) !== false,
		$message . "\nNeedle: " . $needle . "\nHaystack: " . $haystack
	);
}

/**
 * @return array<string,string>
 */
function vms_test_parse_embedded_sections(): array
{
	$raw = substr((string) file_get_contents(__FILE__), __COMPILER_HALT_OFFSET__);
	$lines = preg_split('/\R/', trim($raw));
	$sections = array();
	$current = null;

	foreach ($lines as $line) {
		$line = rtrim((string) $line, "\r");
		if ($line === '') {
			if ($current !== null) {
				$sections[$current][] = '';
			}
			continue;
		}

		if (preg_match('/^==([A-Z0-9_]+)==$/', $line, $matches) === 1) {
			$current = $matches[1];
			$sections[$current] = array();
			continue;
		}

		vms_test_assert_true($current !== null, 'Embedded admissions boundary data must stay sectioned.');
		$sections[$current][] = $line;
	}

	$output = array();
	foreach ($sections as $name => $sectionLines) {
		$output[$name] = implode("\n", $sectionLines);
	}

	return $output;
}

/**
 * @return array<string,array<int,string>>
 */
function vms_test_parse_group_text(string $text): array
{
	$groups = array();
	$current = null;

	foreach (preg_split('/\R/', trim($text)) as $line) {
		$line = trim((string) $line);
		if ($line === '') {
			continue;
		}

		if (preg_match('/^\[(.+)\]$/', $line, $matches) === 1) {
			$current = $matches[1];
			$groups[$current] = array();
			continue;
		}

		vms_test_assert_true($current !== null, 'Boundary-group text must begin with a [boundary] header.');
		$groups[$current][] = $line;
	}

	ksort($groups, SORT_STRING);
	foreach ($groups as &$entries) {
		sort($entries, SORT_STRING);
	}
	unset($entries);

	return $groups;
}

/**
 * @param array<string,array<int,string>> $groups
 * @return array<int,string>
 */
function vms_test_flatten_groups(array $groups): array
{
	$inventory = array();
	foreach ($groups as $entries) {
		foreach ($entries as $entry) {
			$inventory[] = $entry;
		}
	}

	sort($inventory, SORT_STRING);
	return $inventory;
}

/**
 * @param array<int,string> $inventory
 * @return array<int,string>
 */
function vms_test_sort_inventory(array $inventory): array
{
	$sorted = array_values($inventory);
	sort($sorted, SORT_STRING);
	return $sorted;
}

/**
 * @param array<int,string> $groupNames
 */
function vms_test_format_group_list(array $groupNames): string
{
	$labels = array_values($groupNames);
	if ($labels === array()) {
		return '';
	}
	if (count($labels) === 1) {
		return $labels[0];
	}
	if (count($labels) === 2) {
		return $labels[0] . ' or ' . $labels[1];
	}

	$last = array_pop($labels);
	return implode(', ', $labels) . ', or ' . $last;
}

/**
 * @param array<int,string>               $actualInventory
 * @param array<string,array<int,string>> $expectedGroups
 * @return array<string,array<int,string>>
 */
function vms_test_reconcile_inventory_groups(array $actualInventory, array $expectedGroups, string $label): array
{
	$groupNames = array_keys($expectedGroups);
	for ($i = 0; $i < count($groupNames); $i++) {
		for ($j = $i + 1; $j < count($groupNames); $j++) {
			$overlap = array_values(array_intersect($expectedGroups[$groupNames[$i]], $expectedGroups[$groupNames[$j]]));
			vms_test_assert_same(
				array(),
				$overlap,
				$label . ' ownership must remain disjoint between ' . $groupNames[$i] . ' and ' . $groupNames[$j] . '.'
			);
		}
	}

	$duplicates = array_keys(
		array_filter(
			array_count_values($actualInventory),
			static function (int $count): bool {
				return $count > 1;
			}
		)
	);
	vms_test_assert_same(array(), $duplicates, $label . ' inventory should not contain duplicate entries.');

	$lookups = array();
	foreach ($expectedGroups as $groupName => $entries) {
		$lookups[$groupName] = array_fill_keys($entries, true);
	}

	$actualGroups = array();
	$classified = array();
	$unknown = array();
	foreach ($groupNames as $groupName) {
		$actualGroups[$groupName] = array();
	}

	foreach ($actualInventory as $entry) {
		$matchedGroup = null;
		foreach ($lookups as $groupName => $lookup) {
			if (isset($lookup[$entry])) {
				$matchedGroup = $groupName;
				break;
			}
		}

		if ($matchedGroup === null) {
			$unknown[] = $entry;
			continue;
		}

		$actualGroups[$matchedGroup][] = $entry;
		$classifications[] = $entry;
	}

	vms_test_assert_same(
		array(),
		$unknown,
		'Every ' . $label . ' entry must be classified as ' . vms_test_format_group_list($groupNames) . '.'
	);
	foreach ($expectedGroups as $groupName => $entries) {
		vms_test_assert_same($entries, $actualGroups[$groupName], 'The accepted ' . $label . ' inventory for ' . $groupName . ' should remain exact.');
	}

	$expectedUnion = vms_test_flatten_groups($expectedGroups);
	vms_test_assert_same(
		$expectedUnion,
		vms_test_sort_inventory($actualInventory),
		'The combined ' . $label . ' inventories should reconcile to the complete actual inventory.'
	);
	vms_test_assert_same($expectedUnion, vms_test_sort_inventory($classifications), 'The classified ' . $label . ' inventory should preserve the full actual inventory.');

	return $actualGroups;
}

/**
 * @return array<string,int>
 */
function vms_test_collect_pre_rule_counts(array $groups): array
{
	$counts = array();
	foreach (vms_test_flatten_groups($groups) as $entry) {
		$identity = explode('|', $entry, 2)[0];
		$code = substr($identity, (int) strrpos($identity, ':') + 1);
		$counts[$code] = ($counts[$code] ?? 0) + 1;
	}
	ksort($counts, SORT_STRING);
	return $counts;
}

/**
 * @return array<int,array{name:string,start:int,end:int}>
 */
function vms_test_parse_functions(string $source): array
{
	preg_match_all('/^[ \t]*function\s+([A-Za-z0-9_]+)\s*\(/m', $source, $matches, PREG_OFFSET_CAPTURE);
	$functions = array();
	foreach ($matches[1] as $index => $capture) {
		$name = $capture[0];
		$matchOffset = $matches[0][$index][1];
		$startLine = substr_count(substr($source, 0, $matchOffset), "\n") + 1;
		$bracePos = strpos($source, '{', $matchOffset);
		if ($bracePos === false) {
			continue;
		}
		$depth = 0;
		$length = strlen($source);
		for ($i = $bracePos; $i < $length; $i++) {
			$char = $source[$i];
			if ($char === '{') {
				$depth++;
			} elseif ($char === '}') {
				$depth--;
				if ($depth === 0) {
					$endLine = substr_count(substr($source, 0, $i + 1), "\n") + 1;
					$functions[] = array('name' => $name, 'start' => $startLine, 'end' => $endLine);
					break;
				}
			}
		}
	}

	return $functions;
}

function vms_test_extract_function(string $source, string $name): string
{
	if (preg_match('/^[ \t]*function\s+' . preg_quote($name, '/') . '\s*\(/m', $source, $match, PREG_OFFSET_CAPTURE) !== 1) {
		throw new RuntimeException('Unable to find function ' . $name . '.');
	}

	$start = $match[0][1];
	$bracePos = strpos($source, '{', $start);
	if ($bracePos === false) {
		throw new RuntimeException('Unable to find opening brace for ' . $name . '.');
	}

	$depth = 0;
	$length = strlen($source);
	for ($i = $bracePos; $i < $length; $i++) {
		$char = $source[$i];
		if ($char === '{') {
			$depth++;
		} elseif ($char === '}') {
			$depth--;
			if ($depth === 0) {
				return substr($source, $start, ($i + 1) - $start);
			}
		}
	}

	throw new RuntimeException('Unable to find closing brace for ' . $name . '.');
}

/**
 * @param array<int,array{name:string,start:int,end:int}> $functions
 */
function vms_test_find_boundary_name(array $functions, int $lineNumber): string
{
	foreach ($functions as $function) {
		if ($lineNumber >= $function['start'] && $lineNumber <= $function['end']) {
			return $function['name'];
		}
	}

	throw new RuntimeException('Unable to map line ' . $lineNumber . ' to a function boundary.');
}

function vms_test_project_g16_admissions_logging(string $source): string
{
	$historical = array(
		'admission_create_failed' => "error_log('VMS Admission create failed: ' . (string) \$wpdb->last_error);",
		'admission_update_failed' => "error_log('VMS Admission update failed: ' . (string) \$wpdb->last_error);",
		'admission_checkin_failed' => "error_log('VMS Admission checkin failed: ' . (string) \$wpdb->last_error);",
		'admission_uncheckin_failed' => "error_log('VMS Admission uncheckin failed: ' . (string) \$wpdb->last_error);",
	);

	foreach ($historical as $eventCode => $oldStatement) {
		vms_test_assert_same(1, substr_count($source, "'{$eventCode}'"), 'G16 admissions projection event inventory changed: ' . $eventCode);
		$eventOffset = strpos($source, "'{$eventCode}'");
		$callStart = strrpos(substr($source, 0, (int) $eventOffset), 'vms_record_operational_issue(');
		vms_test_assert_true($callStart !== false, 'G16 admissions projection call missing: ' . $eventCode);
		$open = strpos($source, '(', (int) $callStart);
		$depth = 0;
		$callEnd = null;
		for ($offset = (int) $open, $length = strlen($source); $offset < $length; $offset++) {
			if ($source[$offset] === '(') {
				$depth++;
			} elseif ($source[$offset] === ')') {
				$depth--;
				if ($depth === 0) {
					$callEnd = $offset + (($source[$offset + 1] ?? '') === ';' ? 2 : 1);
					break;
				}
			}
		}
		vms_test_assert_true(is_int($callEnd), 'G16 admissions projection call was not closed: ' . $eventCode);
		$currentCall = substr($source, (int) $callStart, (int) $callEnd - (int) $callStart);
		$count = 0;
		$source = str_replace($currentCall, $oldStatement, $source, $count);
		vms_test_assert_same(1, $count, 'G16 admissions projection must restore one historical statement: ' . $eventCode);
	}

	return $source;
}

/**
 * @param array<int,string> $relativePaths
 * @param array<string,bool> $wantedCodes
 * @return array{inventory:array<int,string>,groups:array<string,array<int,string>>}
 */
function vms_test_collect_actual_suppression_inventory(array $relativePaths, array $wantedCodes): array
{
	$pluginRoot = dirname(__DIR__);
	$inventory = array();
	$groups = array();

	foreach ($relativePaths as $relativePath) {
		$path = $pluginRoot . '/' . $relativePath;
		$source = (string) file_get_contents($path);
		if ($relativePath === 'includes/modules/admissions/rest.php') {
			$source = vms_test_project_g16_admissions_logging($source);
		}
		$functions = vms_test_parse_functions($source);
		$lines = preg_split('/\R/', $source);
		vms_test_assert_true(is_array($lines), 'Unable to split ' . $path . ' into projected lines.');

		foreach ($lines as $index => $line) {
			if (strpos($line, 'phpcs:ignore') === false) {
				continue;
			}
			if (preg_match('/phpcs:ignore\s+([^ ]+)/', $line, $matches) !== 1) {
				continue;
			}

			$matchedCodes = array();
			foreach (explode(',', trim($matches[1])) as $code) {
				$code = trim($code);
				if (isset($wantedCodes[$code])) {
					$matchedCodes[] = $code;
				}
			}
			if ($matchedCodes === array()) {
				continue;
			}

			$lineNumber = $index + 1;
			$boundaryName = vms_test_find_boundary_name($functions, $lineNumber);
			$boundaryKey = $relativePath . '::' . $boundaryName;
			$entry = $relativePath . ':' . $lineNumber . ':' . implode(',', $matchedCodes);
			$inventory[] = $entry;
			$groups[$boundaryKey][] = $entry;
		}
	}

	sort($inventory, SORT_STRING);
	ksort($groups, SORT_STRING);
	foreach ($groups as &$entries) {
		sort($entries, SORT_STRING);
	}
	unset($entries);

	return array(
		'inventory' => $inventory,
		'groups' => $groups,
	);
}

$sections = vms_test_parse_embedded_sections();
$preGroups = vms_test_parse_group_text($sections['PRE_BOUNDARY_GROUPS'] ?? '');
$suppressionGroups = vms_test_parse_group_text($sections['SUPPRESSION_GROUPS'] ?? '');

$expectedPreRuleCounts = array(
	'PluginCheck.Security.DirectDB.UnescapedDBParameter' => 39,
	'WordPress.DB.DirectDatabaseQuery.DirectQuery' => 82,
	'WordPress.DB.DirectDatabaseQuery.NoCaching' => 74,
	'WordPress.DB.PreparedSQL.InterpolatedNotPrepared' => 41,
	'WordPress.DB.PreparedSQL.NotPrepared' => 7,
	'WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare' => 5,
	'WordPress.DB.SlowDBQuery.slow_db_query_meta_key' => 3,
	'WordPress.DB.SlowDBQuery.slow_db_query_meta_query' => 4,
);

$relativePaths = array(
	'includes/modules/admissions/admin-ui.php',
	'includes/modules/admissions/admission-tokens.php',
	'includes/modules/admissions/audit.php',
	'includes/modules/admissions/db.php',
	'includes/modules/admissions/pass-claims.php',
	'includes/modules/admissions/rest.php',
	'includes/modules/admissions/vendor-guest-portal.php',
);
$fullSyncFiles = array(
	'includes/modules/admissions/admission-tokens.php',
	'includes/modules/admissions/audit.php',
	'includes/modules/admissions/rest.php',
);
$boundarySyncFiles = array(
	'includes/modules/admissions/admin-ui.php',
	'includes/modules/admissions/db.php',
	'includes/modules/admissions/pass-claims.php',
	'includes/modules/admissions/vendor-guest-portal.php',
);
$wantedCodes = array_fill_keys(array_keys($expectedPreRuleCounts), true);

vms_test_assert_same(39, count($preGroups), 'G9 baseline boundary inventory should remain exactly 39 boundaries.');
vms_test_assert_same(255, count(vms_test_flatten_groups($preGroups)), 'G9 baseline boundary inventory should remain exactly 255 rows.');
vms_test_assert_same($expectedPreRuleCounts, vms_test_collect_pre_rule_counts($preGroups), 'G9 baseline per-rule inventory should remain exact.');
vms_test_reconcile_inventory_groups(vms_test_flatten_groups($preGroups), $preGroups, 'G9 baseline boundary');

vms_test_assert_same(42, count($suppressionGroups), 'G9 suppression inventory should remain exactly 42 current boundaries.');
vms_test_assert_same(88, count(vms_test_flatten_groups($suppressionGroups)), 'G9 suppression inventory should remain exactly 88 explicit entries.');

$actualSuppression = vms_test_collect_actual_suppression_inventory($relativePaths, $wantedCodes);
vms_test_assert_same($suppressionGroups, $actualSuppression['groups'], 'The accepted G9 suppression inventory should remain exact.');
vms_test_reconcile_inventory_groups($actualSuppression['inventory'], $suppressionGroups, 'G9 suppression');

$inventedSuppression = $actualSuppression['inventory'];
$inventedSuppression[] = 'includes/modules/admissions/rest.php:999999:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching';
$inventedRejected = false;
try {
	vms_test_reconcile_inventory_groups($inventedSuppression, $suppressionGroups, 'G9 suppression');
} catch (RuntimeException $exception) {
	$inventedRejected = true;
	vms_test_assert_contains(
		'Every G9 suppression entry must be classified as',
		$exception->getMessage(),
		'Synthetic unclassified DirectQuery/NoCaching suppression should be rejected.'
	);
}
vms_test_assert_true($inventedRejected, 'Synthetic unclassified DirectQuery/NoCaching suppression should be rejected.');

$validLookingSuppression = $actualSuppression['inventory'];
$validLookingSuppression[] = 'includes/modules/admissions/pass-claims.php:999998:WordPress.DB.SlowDBQuery.slow_db_query_meta_value';
$validLookingRejected = false;
try {
	vms_test_reconcile_inventory_groups($validLookingSuppression, $suppressionGroups, 'G9 suppression');
} catch (RuntimeException $exception) {
	$validLookingRejected = true;
	vms_test_assert_contains(
		'Every G9 suppression entry must be classified as',
		$exception->getMessage(),
		'Synthetic valid-looking but unclassified suppression should be rejected.'
	);
}
vms_test_assert_true($validLookingRejected, 'Synthetic valid-looking but unclassified suppression should be rejected.');

$duplicateSuppression = $actualSuppression['inventory'];
$duplicateSuppression[] = $actualSuppression['inventory'][0];
$duplicateRejected = false;
try {
	vms_test_reconcile_inventory_groups($duplicateSuppression, $suppressionGroups, 'G9 suppression');
} catch (RuntimeException $exception) {
	$duplicateRejected = true;
	vms_test_assert_contains(
		'G9 suppression inventory should not contain duplicate entries.',
		$exception->getMessage(),
		'Synthetic duplicate suppression should be rejected.'
	);
}
vms_test_assert_true($duplicateRejected, 'Synthetic duplicate suppression should be rejected.');

$pluginRoot = dirname(__DIR__);
$livePluginRoot = dirname($pluginRoot, 2) . '/vms';
$repoSources = array();
$liveSources = array();

foreach ($relativePaths as $relativePath) {
	$repoPath = $pluginRoot . '/' . $relativePath;
	$livePath = $livePluginRoot . '/' . $relativePath;
	vms_test_assert_true(file_exists($repoPath), 'Missing repository admissions runtime file: ' . $relativePath);
	vms_test_assert_true(file_exists($livePath), 'Missing live admissions runtime file: ' . $relativePath);
	$repoSources[$relativePath] = (string) file_get_contents($repoPath);
	$liveSources[$relativePath] = (string) file_get_contents($livePath);
	vms_test_assert_true(strpos($repoSources[$relativePath], 'phpcs:disable') === false, 'No G9 runtime file may introduce a file-level or block-level PHPCS disable: ' . $relativePath);
}

foreach ($fullSyncFiles as $relativePath) {
	vms_test_assert_same(
		hash('sha256', $repoSources[$relativePath]),
		hash('sha256', $liveSources[$relativePath]),
		'Mirror/live parity should remain byte-identical for full-sync file ' . $relativePath . '.'
	);
}
foreach ($boundarySyncFiles as $relativePath) {
	vms_test_assert_true(
		hash('sha256', $repoSources[$relativePath]) !== hash('sha256', $liveSources[$relativePath]),
		'Mirror/live parity should remain boundary-scoped rather than full-file for ' . $relativePath . '.'
	);
}

foreach (array_keys($suppressionGroups) as $boundaryKey) {
	[$relativePath, $functionName] = explode('::', $boundaryKey, 2);
	if (in_array($relativePath, $fullSyncFiles, true)) {
		continue;
	}

	vms_test_assert_same(
		hash('sha256', vms_test_extract_function($repoSources[$relativePath], $functionName)),
		hash('sha256', vms_test_extract_function($liveSources[$relativePath], $functionName)),
		'Mirror/live owned-boundary parity should remain exact for ' . $boundaryKey . '.'
	);
}

fwrite(STDOUT, "Admissions claim-state query boundaries remediation OK.\n");

__halt_compiler();
==PRE_BOUNDARY_GROUPS==
[includes/modules/admissions/admin-ui.php::vms_admission_export_csv]
includes/modules/admissions/admin-ui.php:137:17:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/admin-ui.php:137:17:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/admin-ui.php:137:18:PluginCheck.Security.DirectDB.UnescapedDBParameter|c410348d
includes/modules/admissions/admin-ui.php:138:4:WordPress.DB.PreparedSQL.InterpolatedNotPrepared|656c686b

[includes/modules/admissions/admission-tokens.php::vms_admission_email_pass_result]
includes/modules/admissions/admission-tokens.php:195:16:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/admission-tokens.php:195:16:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/admission-tokens.php:195:17:PluginCheck.Security.DirectDB.UnescapedDBParameter|09df3a77
includes/modules/admissions/admission-tokens.php:195:40:WordPress.DB.PreparedSQL.InterpolatedNotPrepared|43a013ce
includes/modules/admissions/admission-tokens.php:306:17:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/admission-tokens.php:306:17:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e

[includes/modules/admissions/admission-tokens.php::vms_admission_ensure_entry_token]
includes/modules/admissions/admission-tokens.php:129:16:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/admission-tokens.php:129:16:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/admission-tokens.php:129:17:PluginCheck.Security.DirectDB.UnescapedDBParameter|44d4b9f5
includes/modules/admissions/admission-tokens.php:129:40:WordPress.DB.PreparedSQL.InterpolatedNotPrepared|c2032988
includes/modules/admissions/admission-tokens.php:139:24:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/admission-tokens.php:139:24:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/admission-tokens.php:151:30:PluginCheck.Security.DirectDB.UnescapedDBParameter|86d51db4
includes/modules/admissions/admission-tokens.php:151:35:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/admission-tokens.php:151:35:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/admission-tokens.php:151:53:WordPress.DB.PreparedSQL.InterpolatedNotPrepared|7b04e83f

[includes/modules/admissions/admission-tokens.php::vms_admission_event_comp_headcount]
includes/modules/admissions/admission-tokens.php:171:29:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/admission-tokens.php:171:29:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/admission-tokens.php:171:30:PluginCheck.Security.DirectDB.UnescapedDBParameter|ec347314
includes/modules/admissions/admission-tokens.php:171:53:WordPress.DB.PreparedSQL.InterpolatedNotPrepared|f0a6f206

[includes/modules/admissions/admission-tokens.php::vms_admission_group_entries]
includes/modules/admissions/admission-tokens.php:59:17:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/admission-tokens.php:59:17:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/admission-tokens.php:59:18:PluginCheck.Security.DirectDB.UnescapedDBParameter|e1335a11
includes/modules/admissions/admission-tokens.php:59:45:WordPress.DB.PreparedSQL.InterpolatedNotPrepared|c3c71666

[includes/modules/admissions/admission-tokens.php::vms_admission_scan_template_router]
includes/modules/admissions/admission-tokens.php:357:16:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/admission-tokens.php:357:16:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/admission-tokens.php:357:17:PluginCheck.Security.DirectDB.UnescapedDBParameter|923bf598
includes/modules/admissions/admission-tokens.php:357:40:WordPress.DB.PreparedSQL.InterpolatedNotPrepared|6d16d3dc

[includes/modules/admissions/audit.php::vms_admission_audit_log]
includes/modules/admissions/audit.php:39:19:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30

[includes/modules/admissions/db.php::vms_admission_maybe_upgrade_schema]
includes/modules/admissions/db.php:244:10:PluginCheck.Security.DirectDB.UnescapedDBParameter|5701b2ec
includes/modules/admissions/db.php:244:16:WordPress.DB.PreparedSQL.InterpolatedNotPrepared|f8992c11
includes/modules/admissions/db.php:244:9:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/db.php:244:9:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/db.php:246:17:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/db.php:246:17:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/db.php:246:18:PluginCheck.Security.DirectDB.UnescapedDBParameter|e893aa1d
includes/modules/admissions/db.php:246:30:WordPress.DB.PreparedSQL.InterpolatedNotPrepared|a23aaa5d
includes/modules/admissions/db.php:254:13:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/db.php:254:13:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/db.php:267:23:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/db.php:267:23:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/db.php:267:24:PluginCheck.Security.DirectDB.UnescapedDBParameter|e893aa1d
includes/modules/admissions/db.php:267:36:WordPress.DB.PreparedSQL.InterpolatedNotPrepared|5c5041cb

[includes/modules/admissions/pass-claims.php::vms_pass_claims_create_claim]
includes/modules/admissions/pass-claims.php:2610:17:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/pass-claims.php:2610:17:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/pass-claims.php:2610:18:PluginCheck.Security.DirectDB.UnescapedDBParameter|b46384bc
includes/modules/admissions/pass-claims.php:2610:39:WordPress.DB.PreparedSQL.InterpolatedNotPrepared|57e9c3a0
includes/modules/admissions/pass-claims.php:2633:11:PluginCheck.Security.DirectDB.UnescapedDBParameter|b46384bc
includes/modules/admissions/pass-claims.php:2633:13:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/pass-claims.php:2633:13:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/pass-claims.php:2633:32:WordPress.DB.PreparedSQL.InterpolatedNotPrepared|d10da656
includes/modules/admissions/pass-claims.php:2639:29:PluginCheck.Security.DirectDB.UnescapedDBParameter|9094d43e
includes/modules/admissions/pass-claims.php:2639:31:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/pass-claims.php:2639:31:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/pass-claims.php:2640:5:WordPress.DB.PreparedSQL.InterpolatedNotPrepared|7f660007
includes/modules/admissions/pass-claims.php:2645:12:PluginCheck.Security.DirectDB.UnescapedDBParameter|b46384bc
includes/modules/admissions/pass-claims.php:2645:17:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/pass-claims.php:2645:17:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/pass-claims.php:2645:33:WordPress.DB.PreparedSQL.InterpolatedNotPrepared|d10da656
includes/modules/admissions/pass-claims.php:2652:35:PluginCheck.Security.DirectDB.UnescapedDBParameter|9094d43e
includes/modules/admissions/pass-claims.php:2652:37:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/pass-claims.php:2652:37:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/pass-claims.php:2653:5:WordPress.DB.PreparedSQL.InterpolatedNotPrepared|8a2a3f8c
includes/modules/admissions/pass-claims.php:2658:12:PluginCheck.Security.DirectDB.UnescapedDBParameter|b46384bc
includes/modules/admissions/pass-claims.php:2658:17:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/pass-claims.php:2658:17:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/pass-claims.php:2658:33:WordPress.DB.PreparedSQL.InterpolatedNotPrepared|d10da656
includes/modules/admissions/pass-claims.php:2665:38:PluginCheck.Security.DirectDB.UnescapedDBParameter|7a42ffe7
includes/modules/admissions/pass-claims.php:2665:40:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/pass-claims.php:2665:40:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/pass-claims.php:2666:5:WordPress.DB.PreparedSQL.InterpolatedNotPrepared|e3b00053
includes/modules/admissions/pass-claims.php:2670:12:PluginCheck.Security.DirectDB.UnescapedDBParameter|b46384bc
includes/modules/admissions/pass-claims.php:2670:17:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/pass-claims.php:2670:17:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/pass-claims.php:2670:33:WordPress.DB.PreparedSQL.InterpolatedNotPrepared|d10da656
includes/modules/admissions/pass-claims.php:2678:25:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/pass-claims.php:2698:11:PluginCheck.Security.DirectDB.UnescapedDBParameter|b46384bc
includes/modules/admissions/pass-claims.php:2698:13:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/pass-claims.php:2698:13:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/pass-claims.php:2698:32:WordPress.DB.PreparedSQL.InterpolatedNotPrepared|d10da656
includes/modules/admissions/pass-claims.php:2716:29:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/pass-claims.php:2755:21:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/pass-claims.php:2755:21:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/pass-claims.php:2757:17:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/pass-claims.php:2757:17:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/pass-claims.php:2758:12:PluginCheck.Security.DirectDB.UnescapedDBParameter|b46384bc
includes/modules/admissions/pass-claims.php:2758:17:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/pass-claims.php:2758:17:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/pass-claims.php:2758:33:WordPress.DB.PreparedSQL.InterpolatedNotPrepared|d10da656
includes/modules/admissions/pass-claims.php:2776:9:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/pass-claims.php:2776:9:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/pass-claims.php:2784:25:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/pass-claims.php:2784:25:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/pass-claims.php:2798:13:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/pass-claims.php:2798:13:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/pass-claims.php:2805:13:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/pass-claims.php:2805:13:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/pass-claims.php:2807:17:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/pass-claims.php:2807:17:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e

[includes/modules/admissions/pass-claims.php::vms_pass_claims_eligible_events_for_batch]
includes/modules/admissions/pass-claims.php:2520:13:WordPress.DB.SlowDBQuery.slow_db_query_meta_key|bb14b124
includes/modules/admissions/pass-claims.php:2523:13:WordPress.DB.SlowDBQuery.slow_db_query_meta_query|0dafab7f

[includes/modules/admissions/pass-claims.php::vms_pass_claims_find_token_by_raw]
includes/modules/admissions/pass-claims.php:2444:16:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/pass-claims.php:2444:16:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/pass-claims.php:2444:17:PluginCheck.Security.DirectDB.UnescapedDBParameter|a891348c
includes/modules/admissions/pass-claims.php:2444:40:WordPress.DB.PreparedSQL.InterpolatedNotPrepared|caec3b4b

[includes/modules/admissions/pass-claims.php::vms_pass_claims_generate_tokens_for_batch]
includes/modules/admissions/pass-claims.php:1000:9:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/pass-claims.php:1000:9:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/pass-claims.php:929:9:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/pass-claims.php:929:9:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/pass-claims.php:932:25:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/pass-claims.php:947:17:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/pass-claims.php:947:17:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/pass-claims.php:960:17:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/pass-claims.php:960:17:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/pass-claims.php:965:24:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/pass-claims.php:965:24:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/pass-claims.php:973:17:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/pass-claims.php:973:17:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/pass-claims.php:986:25:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/pass-claims.php:986:25:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/pass-claims.php:986:26:PluginCheck.Security.DirectDB.UnescapedDBParameter|1c135b7c
includes/modules/admissions/pass-claims.php:987:4:WordPress.DB.PreparedSQL.InterpolatedNotPrepared|781a9882
includes/modules/admissions/pass-claims.php:996:13:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/pass-claims.php:996:13:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e

[includes/modules/admissions/pass-claims.php::vms_pass_claims_get_batch_by_id]
includes/modules/admissions/pass-claims.php:360:16:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/pass-claims.php:360:16:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e

[includes/modules/admissions/pass-claims.php::vms_pass_claims_get_batches]
includes/modules/admissions/pass-claims.php:335:17:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/pass-claims.php:335:17:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e

[includes/modules/admissions/pass-claims.php::vms_pass_claims_get_published_event_plans]
includes/modules/admissions/pass-claims.php:264:13:WordPress.DB.SlowDBQuery.slow_db_query_meta_key|bb14b124
includes/modules/admissions/pass-claims.php:267:13:WordPress.DB.SlowDBQuery.slow_db_query_meta_query|0dafab7f

[includes/modules/admissions/pass-claims.php::vms_pass_claims_get_source_by_id]
includes/modules/admissions/pass-claims.php:316:16:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/pass-claims.php:316:16:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e

[includes/modules/admissions/pass-claims.php::vms_pass_claims_get_sources]
includes/modules/admissions/pass-claims.php:287:21:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/pass-claims.php:287:21:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/pass-claims.php:295:21:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/pass-claims.php:295:21:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e

[includes/modules/admissions/pass-claims.php::vms_pass_claims_get_token_by_id]
includes/modules/admissions/pass-claims.php:380:16:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/pass-claims.php:380:16:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e

[includes/modules/admissions/pass-claims.php::vms_pass_claims_get_tokens]
includes/modules/admissions/pass-claims.php:402:21:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/pass-claims.php:402:21:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/pass-claims.php:422:21:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/pass-claims.php:422:21:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e

[includes/modules/admissions/pass-claims.php::vms_pass_claims_handle_batch_generate]
includes/modules/admissions/pass-claims.php:1191:19:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/pass-claims.php:1229:13:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/pass-claims.php:1229:13:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e

[includes/modules/admissions/pass-claims.php::vms_pass_claims_handle_source_save]
includes/modules/admissions/pass-claims.php:1088:21:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30

[includes/modules/admissions/pass-claims.php::vms_pass_claims_handle_token_status_change]
includes/modules/admissions/pass-claims.php:1309:28:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/pass-claims.php:1309:28:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/pass-claims.php:1345:20:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/pass-claims.php:1345:20:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e

[includes/modules/admissions/pass-claims.php::vms_pass_claims_reports_by_batch]
includes/modules/admissions/pass-claims.php:561:17:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/pass-claims.php:561:17:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e

[includes/modules/admissions/pass-claims.php::vms_pass_claims_reports_by_event]
includes/modules/admissions/pass-claims.php:644:17:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/pass-claims.php:644:17:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e

[includes/modules/admissions/pass-claims.php::vms_pass_claims_reports_by_source]
includes/modules/admissions/pass-claims.php:518:17:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/pass-claims.php:518:17:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e

[includes/modules/admissions/pass-claims.php::vms_pass_claims_reports_source_events]
includes/modules/admissions/pass-claims.php:609:17:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/pass-claims.php:609:17:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e

[includes/modules/admissions/rest.php::vms_admission_rest_checkin]
includes/modules/admissions/rest.php:562:16:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/rest.php:562:16:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/rest.php:562:17:PluginCheck.Security.DirectDB.UnescapedDBParameter|28551429
includes/modules/admissions/rest.php:562:40:WordPress.DB.PreparedSQL.InterpolatedNotPrepared|43a013ce
includes/modules/admissions/rest.php:587:4:WordPress.DB.PreparedSQL.InterpolatedNotPrepared|083a77bd
includes/modules/admissions/rest.php:603:20:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/rest.php:603:20:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/rest.php:603:21:PluginCheck.Security.DirectDB.UnescapedDBParameter|58e24ee4
includes/modules/admissions/rest.php:603:27:WordPress.DB.PreparedSQL.NotPrepared|af85f06d
includes/modules/admissions/rest.php:609:18:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/rest.php:609:18:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/rest.php:609:19:PluginCheck.Security.DirectDB.UnescapedDBParameter|28551429
includes/modules/admissions/rest.php:609:42:WordPress.DB.PreparedSQL.InterpolatedNotPrepared|43a013ce

[includes/modules/admissions/rest.php::vms_admission_rest_create]
includes/modules/admissions/rest.php:373:19:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/rest.php:405:16:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/rest.php:405:16:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/rest.php:405:17:PluginCheck.Security.DirectDB.UnescapedDBParameter|1759ade9
includes/modules/admissions/rest.php:405:40:WordPress.DB.PreparedSQL.InterpolatedNotPrepared|43a013ce

[includes/modules/admissions/rest.php::vms_admission_rest_event_plans_today]
includes/modules/admissions/rest.php:828:13:WordPress.DB.SlowDBQuery.slow_db_query_meta_query|0dafab7f

[includes/modules/admissions/rest.php::vms_admission_rest_list]
includes/modules/admissions/rest.php:317:17:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/rest.php:317:17:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/rest.php:317:18:PluginCheck.Security.DirectDB.UnescapedDBParameter|09aa6be0
includes/modules/admissions/rest.php:317:45:WordPress.DB.PreparedSQL.NotPrepared|af85f06d

[includes/modules/admissions/rest.php::vms_admission_rest_patch]
includes/modules/admissions/rest.php:432:16:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/rest.php:432:16:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/rest.php:432:17:PluginCheck.Security.DirectDB.UnescapedDBParameter|1bcbd61d
includes/modules/admissions/rest.php:432:40:WordPress.DB.PreparedSQL.InterpolatedNotPrepared|43a013ce
includes/modules/admissions/rest.php:526:15:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/rest.php:526:15:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/rest.php:535:18:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/rest.php:535:18:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/rest.php:535:19:PluginCheck.Security.DirectDB.UnescapedDBParameter|1bcbd61d
includes/modules/admissions/rest.php:535:42:WordPress.DB.PreparedSQL.InterpolatedNotPrepared|43a013ce

[includes/modules/admissions/rest.php::vms_admission_rest_scan]
includes/modules/admissions/rest.php:741:16:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/rest.php:741:16:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/rest.php:741:17:PluginCheck.Security.DirectDB.UnescapedDBParameter|bdd34273
includes/modules/admissions/rest.php:741:40:WordPress.DB.PreparedSQL.InterpolatedNotPrepared|080ed959
includes/modules/admissions/rest.php:741:40:WordPress.DB.PreparedSQL.InterpolatedNotPrepared|59ac319a
includes/modules/admissions/rest.php:741:87:WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare|8d320183

[includes/modules/admissions/rest.php::vms_admission_rest_summary]
includes/modules/admissions/rest.php:796:1:WordPress.DB.PreparedSQL.InterpolatedNotPrepared|0c4abceb
includes/modules/admissions/rest.php:800:19:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/rest.php:800:19:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/rest.php:800:20:PluginCheck.Security.DirectDB.UnescapedDBParameter|5d6d8f3b
includes/modules/admissions/rest.php:800:28:WordPress.DB.PreparedSQL.NotPrepared|af85f06d

[includes/modules/admissions/rest.php::vms_admission_rest_uncheckin]
includes/modules/admissions/rest.php:651:16:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/rest.php:651:16:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/rest.php:651:17:PluginCheck.Security.DirectDB.UnescapedDBParameter|7bb8c339
includes/modules/admissions/rest.php:651:40:WordPress.DB.PreparedSQL.InterpolatedNotPrepared|43a013ce
includes/modules/admissions/rest.php:672:5:WordPress.DB.PreparedSQL.InterpolatedNotPrepared|083a77bd
includes/modules/admissions/rest.php:686:5:WordPress.DB.PreparedSQL.InterpolatedNotPrepared|083a77bd
includes/modules/admissions/rest.php:699:20:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/rest.php:699:20:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/rest.php:699:21:PluginCheck.Security.DirectDB.UnescapedDBParameter|e130e037
includes/modules/admissions/rest.php:699:27:WordPress.DB.PreparedSQL.NotPrepared|af85f06d
includes/modules/admissions/rest.php:705:18:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/rest.php:705:18:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/rest.php:705:19:PluginCheck.Security.DirectDB.UnescapedDBParameter|7bb8c339
includes/modules/admissions/rest.php:705:42:WordPress.DB.PreparedSQL.InterpolatedNotPrepared|43a013ce

[includes/modules/admissions/vendor-guest-portal.php::vms_admission_find_duplicate_entry_count]
includes/modules/admissions/vendor-guest-portal.php:503:24:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/vendor-guest-portal.php:503:24:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/vendor-guest-portal.php:503:25:PluginCheck.Security.DirectDB.UnescapedDBParameter|6312accb
includes/modules/admissions/vendor-guest-portal.php:504:4:WordPress.DB.PreparedSQL.InterpolatedNotPrepared|7dcc87cc
includes/modules/admissions/vendor-guest-portal.php:504:62:WordPress.DB.PreparedSQL.NotPrepared|c9f2dae3
includes/modules/admissions/vendor-guest-portal.php:504:69:WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare|8d320183

[includes/modules/admissions/vendor-guest-portal.php::vms_admission_vendor_guest_comp_history]
includes/modules/admissions/vendor-guest-portal.php:652:17:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/vendor-guest-portal.php:652:17:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/vendor-guest-portal.php:652:18:PluginCheck.Security.DirectDB.UnescapedDBParameter|6d20f92f
includes/modules/admissions/vendor-guest-portal.php:653:4:WordPress.DB.PreparedSQL.InterpolatedNotPrepared|de1ac2e0
includes/modules/admissions/vendor-guest-portal.php:653:76:WordPress.DB.PreparedSQL.NotPrepared|c9f2dae3
includes/modules/admissions/vendor-guest-portal.php:653:97:WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare|8d320183
includes/modules/admissions/vendor-guest-portal.php:674:26:PluginCheck.Security.DirectDB.UnescapedDBParameter|6367241a
includes/modules/admissions/vendor-guest-portal.php:674:31:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/vendor-guest-portal.php:674:31:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/vendor-guest-portal.php:675:112:WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare|8d320183
includes/modules/admissions/vendor-guest-portal.php:675:6:WordPress.DB.PreparedSQL.InterpolatedNotPrepared|216b1db8
includes/modules/admissions/vendor-guest-portal.php:675:85:WordPress.DB.PreparedSQL.NotPrepared|66517ab8

[includes/modules/admissions/vendor-guest-portal.php::vms_admission_vendor_guest_entries_for_vendor]
includes/modules/admissions/vendor-guest-portal.php:725:17:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/vendor-guest-portal.php:725:17:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/vendor-guest-portal.php:725:18:PluginCheck.Security.DirectDB.UnescapedDBParameter|881a6334
includes/modules/admissions/vendor-guest-portal.php:726:4:WordPress.DB.PreparedSQL.InterpolatedNotPrepared|37567ce5
includes/modules/admissions/vendor-guest-portal.php:726:4:WordPress.DB.PreparedSQL.InterpolatedNotPrepared|dae271a9
includes/modules/admissions/vendor-guest-portal.php:726:77:WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare|8d320183

[includes/modules/admissions/vendor-guest-portal.php::vms_admission_vendor_guest_handle_submit]
includes/modules/admissions/vendor-guest-portal.php:1260:18:PluginCheck.Security.DirectDB.UnescapedDBParameter|0cbce063
includes/modules/admissions/vendor-guest-portal.php:1260:20:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/vendor-guest-portal.php:1260:20:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/vendor-guest-portal.php:1260:41:WordPress.DB.PreparedSQL.InterpolatedNotPrepared|43a013ce
includes/modules/admissions/vendor-guest-portal.php:1274:28:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/vendor-guest-portal.php:1274:28:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e
includes/modules/admissions/vendor-guest-portal.php:1428:39:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/vendor-guest-portal.php:1482:33:WordPress.DB.DirectDatabaseQuery.DirectQuery|d9bb2d30
includes/modules/admissions/vendor-guest-portal.php:1482:33:WordPress.DB.DirectDatabaseQuery.NoCaching|23daf51e

[includes/modules/admissions/vendor-guest-portal.php::vms_admission_vendor_guest_portal_events]
includes/modules/admissions/vendor-guest-portal.php:776:13:WordPress.DB.SlowDBQuery.slow_db_query_meta_key|bb14b124
includes/modules/admissions/vendor-guest-portal.php:778:13:WordPress.DB.SlowDBQuery.slow_db_query_meta_query|0dafab7f
==SUPPRESSION_GROUPS==
[includes/modules/admissions/admin-ui.php::vms_admission_export_csv]
includes/modules/admissions/admin-ui.php:137:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

[includes/modules/admissions/admission-tokens.php::vms_admission_email_pass_result]
includes/modules/admissions/admission-tokens.php:200:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
includes/modules/admissions/admission-tokens.php:312:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

[includes/modules/admissions/admission-tokens.php::vms_admission_ensure_entry_token]
includes/modules/admissions/admission-tokens.php:130:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
includes/modules/admissions/admission-tokens.php:141:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
includes/modules/admissions/admission-tokens.php:154:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

[includes/modules/admissions/admission-tokens.php::vms_admission_event_comp_headcount]
includes/modules/admissions/admission-tokens.php:175:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

[includes/modules/admissions/admission-tokens.php::vms_admission_group_entries]
includes/modules/admissions/admission-tokens.php:59:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

[includes/modules/admissions/admission-tokens.php::vms_admission_scan_template_router]
includes/modules/admissions/admission-tokens.php:364:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

[includes/modules/admissions/audit.php::vms_admission_audit_log]
includes/modules/admissions/audit.php:39:WordPress.DB.DirectDatabaseQuery.DirectQuery

[includes/modules/admissions/db.php::vms_admission_maybe_upgrade_schema]
includes/modules/admissions/db.php:244:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
includes/modules/admissions/db.php:247:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
includes/modules/admissions/db.php:256:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
includes/modules/admissions/db.php:270:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

[includes/modules/admissions/pass-claims.php::vms_pass_claims_create_claim]
includes/modules/admissions/pass-claims.php:2691:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
includes/modules/admissions/pass-claims.php:2706:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
includes/modules/admissions/pass-claims.php:2721:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
includes/modules/admissions/pass-claims.php:2736:WordPress.DB.DirectDatabaseQuery.DirectQuery
includes/modules/admissions/pass-claims.php:2775:WordPress.DB.DirectDatabaseQuery.DirectQuery
includes/modules/admissions/pass-claims.php:2815:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
includes/modules/admissions/pass-claims.php:2818:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
includes/modules/admissions/pass-claims.php:2838:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
includes/modules/admissions/pass-claims.php:2847:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
includes/modules/admissions/pass-claims.php:2862:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
includes/modules/admissions/pass-claims.php:2870:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
includes/modules/admissions/pass-claims.php:2873:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

[includes/modules/admissions/pass-claims.php::vms_pass_claims_eligible_events_for_batch]
includes/modules/admissions/pass-claims.php:2548:WordPress.DB.SlowDBQuery.slow_db_query_meta_key
includes/modules/admissions/pass-claims.php:2551:WordPress.DB.SlowDBQuery.slow_db_query_meta_query

[includes/modules/admissions/pass-claims.php::vms_pass_claims_find_token_by_raw]
includes/modules/admissions/pass-claims.php:2471:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

[includes/modules/admissions/pass-claims.php::vms_pass_claims_generate_tokens_for_batch]
includes/modules/admissions/pass-claims.php:1004:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
includes/modules/admissions/pass-claims.php:1016:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
includes/modules/admissions/pass-claims.php:1021:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
includes/modules/admissions/pass-claims.php:941:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
includes/modules/admissions/pass-claims.php:945:WordPress.DB.DirectDatabaseQuery.DirectQuery
includes/modules/admissions/pass-claims.php:961:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
includes/modules/admissions/pass-claims.php:975:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
includes/modules/admissions/pass-claims.php:981:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
includes/modules/admissions/pass-claims.php:990:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

[includes/modules/admissions/pass-claims.php::vms_pass_claims_get_batch_by_id]
includes/modules/admissions/pass-claims.php:364:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

[includes/modules/admissions/pass-claims.php::vms_pass_claims_get_batches]
includes/modules/admissions/pass-claims.php:338:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

[includes/modules/admissions/pass-claims.php::vms_pass_claims_get_published_event_plans]
includes/modules/admissions/pass-claims.php:264:WordPress.DB.SlowDBQuery.slow_db_query_meta_key
includes/modules/admissions/pass-claims.php:267:WordPress.DB.SlowDBQuery.slow_db_query_meta_query

[includes/modules/admissions/pass-claims.php::vms_pass_claims_get_source_by_id]
includes/modules/admissions/pass-claims.php:318:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

[includes/modules/admissions/pass-claims.php::vms_pass_claims_get_sources]
includes/modules/admissions/pass-claims.php:287:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
includes/modules/admissions/pass-claims.php:296:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

[includes/modules/admissions/pass-claims.php::vms_pass_claims_get_token_by_id]
includes/modules/admissions/pass-claims.php:385:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

[includes/modules/admissions/pass-claims.php::vms_pass_claims_get_tokens]
includes/modules/admissions/pass-claims.php:408:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
includes/modules/admissions/pass-claims.php:429:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

[includes/modules/admissions/pass-claims.php::vms_pass_claims_handle_batch_generate]
includes/modules/admissions/pass-claims.php:1214:WordPress.DB.DirectDatabaseQuery.DirectQuery
includes/modules/admissions/pass-claims.php:1253:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

[includes/modules/admissions/pass-claims.php::vms_pass_claims_handle_source_save]
includes/modules/admissions/pass-claims.php:1110:WordPress.DB.DirectDatabaseQuery.DirectQuery

[includes/modules/admissions/pass-claims.php::vms_pass_claims_handle_token_status_change]
includes/modules/admissions/pass-claims.php:1334:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
includes/modules/admissions/pass-claims.php:1371:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

[includes/modules/admissions/pass-claims.php::vms_pass_claims_lock_token_for_claim]
includes/modules/admissions/pass-claims.php:2632:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

[includes/modules/admissions/pass-claims.php::vms_pass_claims_reports_by_batch]
includes/modules/admissions/pass-claims.php:570:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

[includes/modules/admissions/pass-claims.php::vms_pass_claims_reports_by_event]
includes/modules/admissions/pass-claims.php:655:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

[includes/modules/admissions/pass-claims.php::vms_pass_claims_reports_by_source]
includes/modules/admissions/pass-claims.php:526:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

[includes/modules/admissions/pass-claims.php::vms_pass_claims_reports_source_events]
includes/modules/admissions/pass-claims.php:619:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

[includes/modules/admissions/pass-claims.php::vms_pass_claims_reset_token_unclaimed]
includes/modules/admissions/pass-claims.php:2644:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

[includes/modules/admissions/rest.php::vms_admission_rest_checkin]
includes/modules/admissions/rest.php:603:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

[includes/modules/admissions/rest.php::vms_admission_rest_create]
includes/modules/admissions/rest.php:388:WordPress.DB.DirectDatabaseQuery.DirectQuery

[includes/modules/admissions/rest.php::vms_admission_rest_entry_row]
includes/modules/admissions/rest.php:211:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

[includes/modules/admissions/rest.php::vms_admission_rest_event_plans_today]
includes/modules/admissions/rest.php:849:WordPress.DB.SlowDBQuery.slow_db_query_meta_query

[includes/modules/admissions/rest.php::vms_admission_rest_list]
includes/modules/admissions/rest.php:331:PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

[includes/modules/admissions/rest.php::vms_admission_rest_patch]
includes/modules/admissions/rest.php:542:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

[includes/modules/admissions/rest.php::vms_admission_rest_scan]
includes/modules/admissions/rest.php:756:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
includes/modules/admissions/rest.php:759:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

[includes/modules/admissions/rest.php::vms_admission_rest_summary]
includes/modules/admissions/rest.php:810:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

[includes/modules/admissions/rest.php::vms_admission_rest_uncheckin]
includes/modules/admissions/rest.php:689:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
includes/modules/admissions/rest.php:705:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

[includes/modules/admissions/vendor-guest-portal.php::vms_admission_find_duplicate_entry_count]
includes/modules/admissions/vendor-guest-portal.php:503:PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
includes/modules/admissions/vendor-guest-portal.php:505:WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
includes/modules/admissions/vendor-guest-portal.php:507:WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

[includes/modules/admissions/vendor-guest-portal.php::vms_admission_vendor_guest_comp_history]
includes/modules/admissions/vendor-guest-portal.php:656:PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
includes/modules/admissions/vendor-guest-portal.php:658:WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
includes/modules/admissions/vendor-guest-portal.php:660:WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
includes/modules/admissions/vendor-guest-portal.php:683:PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
includes/modules/admissions/vendor-guest-portal.php:685:WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
includes/modules/admissions/vendor-guest-portal.php:687:WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

[includes/modules/admissions/vendor-guest-portal.php::vms_admission_vendor_guest_entries_for_vendor]
includes/modules/admissions/vendor-guest-portal.php:739:PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
includes/modules/admissions/vendor-guest-portal.php:741:WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
includes/modules/admissions/vendor-guest-portal.php:743:WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

[includes/modules/admissions/vendor-guest-portal.php::vms_admission_vendor_guest_handle_submit]
includes/modules/admissions/vendor-guest-portal.php:1279:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
includes/modules/admissions/vendor-guest-portal.php:1294:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
includes/modules/admissions/vendor-guest-portal.php:1449:WordPress.DB.DirectDatabaseQuery.DirectQuery
includes/modules/admissions/vendor-guest-portal.php:1504:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

[includes/modules/admissions/vendor-guest-portal.php::vms_admission_vendor_guest_portal_events]
includes/modules/admissions/vendor-guest-portal.php:795:WordPress.DB.SlowDBQuery.slow_db_query_meta_key
includes/modules/admissions/vendor-guest-portal.php:797:WordPress.DB.SlowDBQuery.slow_db_query_meta_query
