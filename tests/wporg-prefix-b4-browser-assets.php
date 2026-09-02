<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/scripts/lib/wporg-prefix-b4.php';

$root = dirname(__DIR__);
$map = BVMGR_WPORG_Prefix_B4::loadJson($root . '/' . BVMGR_WPORG_Prefix_B4::MAP_PATH);
$failures = array();
$assert = static function (bool $condition, string $message) use (&$failures): void {
	if (!$condition) {
		$failures[] = $message;
	}
};

$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/scripts/apply-wporg-prefix-b4.php') . ' --check-browser-assets 2>&1';
$output = array();
$status = 0;
exec($command, $output, $status);
$assert($status === 0, 'Every frozen browser-global and asset-handle semantic site must use its canonical identifier: ' . implode(' ', $output));

$browserTargets = array_column((array) $map['categories']['browser_globals'], 'canonical_identifier');
$handleTargets = array_column((array) $map['categories']['asset_handles'], 'canonical_identifier');
$assert(count($browserTargets) === 29 && count(array_unique($browserTargets)) === 29, 'Browser cutover must expose exactly 29 distinct canonical BVMGR_* globals.');
$assert(count($handleTargets) === 64 && count(array_unique($handleTargets)) === 64, 'Asset cutover must expose exactly 64 distinct canonical bvmgr-* handles.');

$publicSources = array();
foreach ((array) $map['categories'] as $rows) {
	foreach ((array) $rows as $row) {
		foreach (array('registration_source_sites', 'dependency_sites', 'consumer_sites', 'producer_sites') as $siteField) {
			foreach ((array) ($row[$siteField] ?? array()) as $site) {
				$file = (string) ($site['file'] ?? '');
				if ($file !== '' && !isset($publicSources[$file])) {
					$publicSources[$file] = (string) file_get_contents($root . '/' . $file);
				}
			}
		}
	}
}
foreach ((array) $map['categories']['browser_globals'] as $row) {
	$legacy = (string) $row['legacy_identifier'];
	$canonical = (string) $row['canonical_identifier'];
	$combined = '';
	foreach (array('producer_sites', 'consumer_sites') as $siteField) {
		foreach ((array) ($row[$siteField] ?? array()) as $site) {
			$combined .= $publicSources[(string) $site['file']] ?? '';
		}
	}
	$assert(!str_contains($combined, $legacy), "Legacy browser global must be absent from its frozen shipped sites: {$legacy}.");
	$assert(str_contains($combined, $canonical), "Canonical browser global must be present in its frozen shipped sites: {$canonical}.");
}
foreach ((array) $map['categories']['asset_handles'] as $row) {
	$canonical = (string) $row['canonical_identifier'];
	$combined = '';
	foreach (array('registration_source_sites', 'dependency_sites', 'consumer_sites') as $siteField) {
		foreach ((array) ($row[$siteField] ?? array()) as $site) {
			$combined .= $publicSources[(string) $site['file']] ?? '';
		}
	}
	$assert(str_contains($combined, $canonical), "Canonical asset handle must be present in its frozen semantic graph: {$canonical}.");
	$assert(($row['known_addon_external_consumers'] ?? null) === array(), "No B4 handle alias may be introduced without a semantic add-on consumer: {$canonical}.");
}

$referAFriendManifest = (string) file_get_contents($root . '/docs/wporg-prefix-migration-manifest.json');
$assert(str_contains($referAFriendManifest, '"asset_handles": []'), 'Known-add-on evidence must keep Refer a Friend outside the B4 handle graph.');
$assert(!str_contains(implode("\n", $publicSources), "wp_register_style('vms-admin'"), 'No legacy vms-admin asset alias may be registered.');
$assert(!str_contains(implode("\n", $publicSources), "wp_register_script('vms-admin'"), 'No legacy vms-admin script alias may be registered.');

if ($failures !== array()) {
	fwrite(STDERR, "B4 browser/asset failures:\n- " . implode("\n- ", $failures) . "\n");
	exit(1);
}

echo "B4 browser-global and asset-handle cutover tests passed.\n";
