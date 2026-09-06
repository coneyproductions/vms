<?php
declare(strict_types=1);

$contracts = require __DIR__ . '/runtime-contracts.php';
$failures = array();
$assert = static function (bool $condition, string $message) use (&$failures): void {
	if (!$condition) {
		$failures[] = $message;
	}
};

$expectedAddons = array('events-slider', 'fill-dates', 'data-tools', 'express-bar', 'refer-a-friend');
$expectedVersions = array(
	'backstage-venue-manager' => '1.2.0',
	'events-slider' => '1.0.10',
	'fill-dates' => '0.1.8',
	'data-tools' => '0.5.55',
	'express-bar' => '0.6.24',
	'refer-a-friend' => '0.2.6',
);
$allowedClassifications = array('LEGACY_FALLBACK', 'GUARDED_OPTIONAL', 'ADDON_MIGRATION_GAP');
$expectedClassificationCounts = array(
	'LEGACY_FALLBACK' => 54,
	'GUARDED_OPTIONAL' => 7,
	'ADDON_MIGRATION_GAP' => 10,
);
$expectedKindCounts = array('functions' => 63, 'classes' => 2, 'constants' => 6);
$entries = array();
$uniqueFunctions = array();
$classificationCounts = array_fill_keys($allowedClassifications, 0);
$kindCounts = array_fill_keys(array_keys($expectedKindCounts), 0);

$assert(($contracts['schema_version'] ?? null) === 2, 'The official-five contract schema must be version 2.');
$assert(($contracts['supported_versions'] ?? null) === $expectedVersions, 'Supported package versions must match the reviewed runtime set.');

foreach ($expectedAddons as $addon) {
	foreach (array('functions', 'classes', 'constants') as $pluralKind) {
		$kind = array('functions' => 'function', 'classes' => 'class', 'constants' => 'constant')[$pluralKind];
		$historical = $contracts['historical_fallbacks'][$pluralKind][$addon] ?? null;
		$capabilities = $contracts['capabilities'][$addon][$pluralKind] ?? null;
		$assert(is_array($historical), 'Missing historical ' . $pluralKind . ' for ' . $addon . '.');
		$assert(is_array($capabilities), 'Missing canonical ' . $pluralKind . ' capabilities for ' . $addon . '.');
		if (!is_array($historical) || !is_array($capabilities)) {
			continue;
		}
		$assert(count($historical) === count(array_unique($historical)), 'Duplicate historical ' . $kind . ' within ' . $addon . '.');
		$assert(count($historical) === count($capabilities), 'Historical and canonical capability counts differ for ' . $addon . ' ' . $pluralKind . '.');

		foreach ($capabilities as $index => $capability) {
			$fallback = is_array($capability) ? ($capability['historical_fallback'] ?? null) : null;
			$canonical = is_array($capability) ? ($capability['canonical'] ?? null) : null;
			$expectedCanonical = $fallback;
			if ($kind === 'function' && is_string($fallback) && strpos($fallback, 'vms_') === 0) {
				$expectedCanonical = 'bvmgr_' . substr($fallback, 4);
			} elseif (($kind === 'class' || $kind === 'constant') && is_string($fallback) && strpos($fallback, 'VMS_') === 0) {
				$expectedCanonical = 'BVMGR_' . substr($fallback, 4);
			}

			$pattern = $kind === 'function'
				? '/^vms_[a-z0-9_]+$/'
				: ($kind === 'class' ? '/^VMS_[A-Za-z0-9_]+$/' : '/^VMS_[A-Z0-9_]+$/');
			$assert(($historical[$index] ?? null) === $fallback, 'Capability fallback order diverged for ' . $addon . ' ' . $pluralKind . '.');
			$assert(is_string($fallback) && preg_match($pattern, $fallback) === 1, 'Invalid historical ' . $kind . ': ' . var_export($fallback, true));
			$assert($canonical === $expectedCanonical, 'Canonical mapping is incorrect for ' . (string) $fallback . '.');
			$assert(($capability['provider'] ?? null) === 'canonical_bvm', 'Canonical BVM must be the required provider for ' . (string) $fallback . '.');
			$assert(in_array($capability['requirement_path'] ?? null, array('bootstrap', 'feature'), true), 'Requirement path missing for ' . (string) $fallback . '.');
			$classification = (string) ($capability['reconciliation_classification'] ?? '');
			$assert(in_array($classification, $allowedClassifications, true), 'Invalid reconciliation classification for ' . (string) $fallback . '.');
			if (isset($classificationCounts[$classification])) {
				$classificationCounts[$classification]++;
			}
			$kindCounts[$pluralKind]++;
			$entries[] = $addon . ':' . $kind . ':' . $fallback;
			if ($kind === 'function') {
				$uniqueFunctions[$fallback] = true;
			}
		}
	}

	foreach (array('hooks', 'hook_callbacks', 'provider_resolvers') as $group) {
		$assert(isset($contracts[$group][$addon]) && is_array($contracts[$group][$addon]), 'Missing ' . $group . ' contracts for ' . $addon . '.');
	}
}

$assert($kindCounts === $expectedKindCounts, 'Capability kinds must remain 63 functions, 2 classes, and 6 constants.');
$assert(count($entries) === 71, 'The reconciled capability inventory must contain exactly 71 add-on/symbol entries.');
$assert(count($uniqueFunctions) === 53, 'Historical callable evidence must contain exactly 53 unique BVM functions.');
$assert(count($entries) === count(array_unique($entries)), 'Reconciled capability entries must be unique.');
$assert($classificationCounts === $expectedClassificationCounts, 'Reconciliation classifications must remain 54/7/10.');

if ($failures !== array()) {
	fwrite(STDERR, "BVM add-on runtime contract failures:\n- " . implode("\n- ", $failures) . "\n");
	exit(1);
}

echo "BVM add-on runtime contracts passed: 71 canonical capabilities; 54 legacy fallbacks, 7 guarded optional, 10 migrated add-on gaps.\n";
