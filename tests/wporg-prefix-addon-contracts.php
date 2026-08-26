<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/scripts/lib/wporg-prefix-inventory.php';

$failures = array();
$assert = static function (bool $condition, string $message) use (&$failures): void {
	if (!$condition) {
		$failures[] = $message;
	}
};

$root = dirname(__DIR__);
$manifest = json_decode((string) file_get_contents($root . '/docs/wporg-prefix-migration-manifest.json'), true);
$addons = is_array($manifest) ? (array) ($manifest['known_addons'] ?? array()) : array();
$coreFunctions = array_fill_keys(
	array_column((array) ($manifest['symbols']['functions'] ?? array()), 'current_identifier'),
	true
);
$liveTree = realpath($root . '/../../vms');
$pluginsRoot = is_string($liveTree) ? dirname($liveTree) : '';
$checked = 0;
$coreCanonicalByKind = array();
foreach ((array) ($manifest['symbols'] ?? array()) as $kind => $entries) {
	foreach ((array) $entries as $entry) {
		if (is_string($entry['canonical_target'] ?? null) && $entry['canonical_target'] !== '') {
			$coreCanonicalByKind[$kind][$entry['canonical_target']] = true;
		}
	}
}

foreach ($addons as $addon) {
	$slug = (string) ($addon['slug'] ?? '');
	$addonRoot = $pluginsRoot !== '' ? $pluginsRoot . '/' . $slug : '';
	if ($addonRoot === '' || !is_dir($addonRoot)) {
		continue;
	}
	$checked++;
	$sources = '';
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($addonRoot, FilesystemIterator::SKIP_DOTS)
	);
	foreach ($iterator as $file) {
		if ($file->isFile() && strtolower((string) $file->getExtension()) === 'php') {
			$sources .= "\n" . (string) file_get_contents((string) $file->getPathname());
		}
	}

	foreach ((array) ($addon['evidence_files'] ?? array()) as $relativeFile) {
		$assert(is_file($addonRoot . '/' . $relativeFile), "{$slug} evidence file must exist: {$relativeFile}.");
	}
	foreach ((array) ($addon['consumed_contracts']['core_php_functions'] ?? array()) as $function) {
		$assert(isset($coreFunctions[$function]), "{$slug} mapped core function must exist in the semantic manifest: {$function}.");
		$assert(preg_match('/\b' . preg_quote((string) $function, '/') . '\b/', $sources) === 1, "{$slug} must still consume mapped core function {$function}.");
	}
	foreach (array('hooks', 'physical_cpt_taxonomy_identifiers', 'asset_handles') as $contractType) {
		foreach ((array) ($addon['consumed_contracts'][$contractType] ?? array()) as $identifier) {
			$assert(strpos($sources, (string) $identifier) !== false, "{$slug} must still contain mapped {$contractType} contract {$identifier}.");
		}
	}
	$addonDeclarations = BVMGR_WPORG_Prefix_Inventory::scanSource($sources, $slug . '/combined.php');
	foreach ($coreCanonicalByKind as $kind => $canonicalTargets) {
		$collisions = array_intersect_key($canonicalTargets, (array) ($addonDeclarations[$kind] ?? array()));
		$assert($collisions === array(), "{$slug} must not declare a {$kind} symbol that collides with a planned core canonical target.");
	}
}

if ($pluginsRoot !== '') {
	$assert($checked === 5, 'All five known installed add-ons must be checked when the live plugin root is available.');
}

$referApi = null;
foreach ((array) ($manifest['public_extension_apis'] ?? array()) as $api) {
	if (($api['current_identifier'] ?? '') === 'vms_register_admin_page') {
		$referApi = $api;
		break;
	}
}
$assert(is_array($referApi), 'Public API map must contain vms_register_admin_page.');
$assert(($referApi['known_addon_consumers'] ?? null) === array('vms-refer-a-friend'), 'Known consumer for Admin Page Registry must be frozen exactly.');

if ($failures !== array()) {
	fwrite(STDERR, "Prefix add-on contract failures:\n- " . implode("\n- ", $failures) . "\n");
	exit(1);
}

echo "Prefix add-on contract tests passed";
echo $checked > 0 ? " for {$checked} installed add-ons.\n" : " (external add-on trees unavailable; frozen manifest checked).\n";
