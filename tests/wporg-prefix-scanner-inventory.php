<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/scripts/lib/wporg-prefix-scanner-inventory.php';

$root = dirname(__DIR__);
$failures = array();
$assert = static function (bool $condition, string $message) use (&$failures): void {
	if (!$condition) {
		$failures[] = $message;
	}
};

try {
	$manifest = BVMGR_WPORG_Prefix_Scanner_Inventory::loadJsonFile($root . '/docs/wporg-prefix-migration-manifest.json');
	$inventory = BVMGR_WPORG_Prefix_Scanner_Inventory::loadJsonFile($root . '/docs/wporg-prefix-scanner-inventory.json');
	$currentScanState = BVMGR_WPORG_Prefix_Scanner_Inventory::loadJsonFile($root . '/docs/wporg-current-scan-state.json');
	BVMGR_WPORG_Prefix_Scanner_Inventory::validateArtifact($root, $inventory, $manifest);

	$findings = (array) ($inventory['authoritative_prefix_findings'] ?? array());
	$historical = (array) ($inventory['historical_residual']['rows'] ?? array());
	$categoryCounts = (array) ($inventory['summary']['category_counts'] ?? array());
	$functionSites = array_sum(array_map(
		static fn(array $entry): int => count((array) ($entry['declaration_sites'] ?? array())),
		(array) ($manifest['symbols']['functions'] ?? array())
	));
	$hookCategory = array_values(array_filter(
		(array) ($manifest['categories'] ?? array()),
		static fn(array $category): bool => ($category['id'] ?? '') === 'hooks'
	));
	$semanticHookSites = (int) ($hookCategory[0]['semantic_inventory']['total'] ?? -1);
	$currentFunctionNames = array_fill_keys(array_column((array) ($manifest['symbols']['functions'] ?? array()), 'current_identifier'), true);
	$activeFindings = array_values(array_filter($findings, static function (array $finding) use ($currentFunctionNames): bool {
		return ($finding['category'] ?? '') !== BVMGR_WPORG_Prefix_Scanner_Inventory::CATEGORY_B3
			|| isset($currentFunctionNames[(string) ($finding['identifier'] ?? '')]);
	}));
	$remainingB3Sites = count(array_filter($activeFindings, static fn(array $finding): bool => ($finding['category'] ?? '') === BVMGR_WPORG_Prefix_Scanner_Inventory::CATEGORY_B3));

	$assert(
		($categoryCounts[BVMGR_WPORG_Prefix_Scanner_Inventory::CATEGORY_B3] ?? null) === $functionSites,
		'Every semantic B3 declaration site must map to one scanner finding.'
	);
	$assert(
		($categoryCounts[BVMGR_WPORG_Prefix_Scanner_Inventory::CATEGORY_B7] ?? null) === $semanticHookSites,
		'Every semantic B7 hook site/family must map to one scanner finding.'
	);
	$assert(
		($inventory['historical_residual']['count'] ?? null) === ($currentScanState['scan']['total'] ?? null),
		'Historical residual count must remain separate and equal the durable historical scan state.'
	);
	$assert(
		($inventory['historical_residual']['code_counts'] ?? array()) === ($currentScanState['scan']['code_counts'] ?? array()),
		'Historical residual code counts must remain byte-semantic with the durable historical scan state.'
	);
	$assert(($inventory['summary']['unmapped'] ?? null) === 0, 'Authoritative prefix scanner inventory must have zero unmapped findings.');
	$assert(($inventory['summary']['unexpected'] ?? null) === 0, 'Authoritative prefix scanner inventory must have zero unexpected findings.');
	$assert(
		count(array_unique(array_column($findings, 'finding_id'))) === count($findings),
		'Every scanner row must have one unique stable finding ID.'
	);
	$assert(
		($inventory['architecture']['semantic_migration_inventory_equals_raw_scanner_inventory'] ?? null) === false,
		'Semantic and raw scanner inventories must remain explicitly non-identical.'
	);

	$ledger = (array) ($manifest['complete_semantic_ledger'] ?? array());
	$assert(
		($ledger['historical_original_ratchet'] ?? array()) === array(
			'pre_B2' => 4696,
			'post_B2' => 4521,
			'reduction' => 175,
			'immutable' => true,
		),
		'Historical B2 ratchet must remain immutable evidence.'
	);
	$assert(
		($ledger['corrected_complete_counts'] ?? array()) === array('pre_B2' => 4737, 'post_B2' => 4562, 'post_B2_5' => 4521),
		'Complete semantic ledger must retain the corrected B2.5 counts separately.'
	);
	$assert(
		($ledger['originally_omitted'] ?? array()) === array('global_slots' => 41, 'token_sites' => 194, 'plugin_check_rows' => 57),
		'Complete semantic ledger must retain the exact originally omitted B2.5 scope.'
	);

	$strictRows = $historical;
	foreach ($activeFindings as $finding) {
		$strictRows[] = array_intersect_key($finding, array_flip(array('file', 'line', 'column', 'type', 'code', 'message', 'docs')));
	}
	$baselineGate = BVMGR_WPORG_Prefix_Scanner_Inventory::gate($root, $inventory, $strictRows, $manifest);
	$assert(($baselineGate['status'] ?? '') === 'PASS', 'Authoritative scanner rows must pass their own migration-aware gate.');
	$assert(($baselineGate['unexpected'] ?? null) === 0, 'Authoritative gate must have zero unexpected findings.');
	$assert(($baselineGate['unmapped'] ?? null) === 0, 'Authoritative gate must have zero unmapped findings.');

	$withoutOneB3 = $strictRows;
	foreach ($withoutOneB3 as $index => $row) {
		if (($row['code'] ?? '') === 'WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound') {
			unset($withoutOneB3[$index]);
			break;
		}
	}
	$withoutOneB3 = array_values($withoutOneB3);
	$progressGate = BVMGR_WPORG_Prefix_Scanner_Inventory::gate($root, $inventory, $withoutOneB3, $manifest);
	$assert(($progressGate['status'] ?? '') === 'PASS', 'Removing a mapped B3 finding must count as monotonic migration progress.');
	$alreadyMigratedSites = (int) ($manifest['completed_batches']['B3']['counts']['migrated_declaration_sites'] ?? 0);
	$assert(($progressGate['removed_authoritative_findings'] ?? 0) === $alreadyMigratedSites + 1, 'Monotonic gate must report prior B3 progress plus the one newly removed finding.');

	$unknownGlobalRows = $strictRows;
	$unknownGlobalRows[] = array(
		'file' => 'includes/bootstrap.php',
		'line' => 1,
		'column' => 1,
		'type' => 'WARNING',
		'code' => 'WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound',
		'message' => 'Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: &quot;$unreviewed_global&quot;.',
		'docs' => '',
	);
	$unknownGlobalGate = BVMGR_WPORG_Prefix_Scanner_Inventory::gate($root, $inventory, $unknownGlobalRows, $manifest);
	$assert(($unknownGlobalGate['status'] ?? '') === 'FAIL', 'A new genuine/unreviewed global candidate must fail the scanner gate.');
	$assert(($unknownGlobalGate['unmapped'] ?? 0) === 1, 'A new genuine/unreviewed global candidate must be exposed as unmapped.');

	$completedB3Manifest = $manifest;
	$completedB3Manifest['completed_batches']['B3'] = array('status' => 'complete');
	$completedB3Gate = BVMGR_WPORG_Prefix_Scanner_Inventory::gate($root, $inventory, $strictRows, $completedB3Manifest);
	$assert(($completedB3Gate['status'] ?? '') === 'FAIL', 'B3 findings must fail after B3 is marked complete.');
	$assert(count((array) ($completedB3Gate['completed_batch_residuals'] ?? array())) === $remainingB3Sites, 'All currently remaining B3 rows must be reported after B3 completion.');
} catch (Throwable $exception) {
	$failures[] = $exception->getMessage();
}

if ($failures !== array()) {
	fwrite(STDERR, "Prefix scanner inventory failures:\n- " . implode("\n- ", $failures) . "\n");
	exit(1);
}

echo "Prefix scanner inventory and migration-aware gate tests passed.\n";
