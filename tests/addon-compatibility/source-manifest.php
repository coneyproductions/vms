<?php
declare(strict_types=1);

if ($argc !== 3) {
	fwrite(STDERR, "Usage: php source-manifest.php <wordpress-root> <output-json>\n");
	exit(2);
}

$wordpressRoot = realpath($argv[1]);
$outputPath = $argv[2];
if (!is_string($wordpressRoot) || !is_file($wordpressRoot . '/wp-includes/version.php')) {
	fwrite(STDERR, "The isolated WordPress root could not be resolved.\n");
	exit(2);
}

$pluginsRoot = $wordpressRoot . '/wp-content/plugins';
$contracts = require __DIR__ . '/runtime-contracts.php';
$plugins = array(
	'backstage-venue-manager' => 'backstage-venue-manager.php',
	'vms-events-slider' => 'vms-events-slider.php',
	'vms-fill-dates' => 'vms-fill-dates.php',
	'vms-data-tools' => 'vms-data-tools.php',
	'vms-express-bar' => 'vms-express-bar.php',
	'vms-refer-a-friend' => 'vms-refer-a-friend.php',
	'woocommerce' => 'woocommerce.php',
	'the-events-calendar' => 'the-events-calendar.php',
);
$contractPackageForSlug = array(
	'backstage-venue-manager' => 'backstage-venue-manager',
	'vms-events-slider' => 'events-slider',
	'vms-fill-dates' => 'fill-dates',
	'vms-data-tools' => 'data-tools',
	'vms-express-bar' => 'express-bar',
	'vms-refer-a-friend' => 'refer-a-friend',
);

$treeHash = static function (string $root): array {
	$files = array();
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
	);
	foreach ($iterator as $fileInfo) {
		if (!$fileInfo->isFile() || $fileInfo->isLink()) {
			continue;
		}
		$relative = str_replace('\\', '/', substr($fileInfo->getPathname(), strlen($root) + 1));
		$files[$relative] = hash_file('sha256', $fileInfo->getPathname());
	}
	ksort($files, SORT_STRING);
	$context = hash_init('sha256');
	foreach ($files as $relative => $hash) {
		hash_update($context, $relative . "\0" . $hash . "\n");
	}
	return array('file_count' => count($files), 'sha256' => hash_final($context));
};

$pluginHeader = static function (string $path): array {
	$source = (string) file_get_contents($path);
	$name = '';
	$version = '';
	if (preg_match('/^[ \t\/*#@]*Plugin Name:\s*(.+)$/mi', $source, $match) === 1) {
		$name = trim($match[1]);
	}
	if (preg_match('/^[ \t\/*#@]*Version:\s*(.+)$/mi', $source, $match) === 1) {
		$version = trim($match[1]);
	}
	return array('name' => $name, 'version' => $version);
};

$manifest = array(
	'wordpress_version' => '',
	'contract_schema_version' => $contracts['schema_version'] ?? null,
	'supported_versions' => $contracts['supported_versions'] ?? array(),
	'plugins' => array(),
	'fill_dates_phase_2_files' => array(),
	'fixture_exclusions' => array(
		'legacy_vms' => !is_dir($pluginsRoot . '/vms'),
		'backstage_outreach' => !is_dir($pluginsRoot . '/backstage-outreach'),
	),
);

$versionSource = (string) file_get_contents($wordpressRoot . '/wp-includes/version.php');
if (preg_match('/\$wp_version\s*=\s*[\'\"]([^\'\"]+)/', $versionSource, $match) === 1) {
	$manifest['wordpress_version'] = $match[1];
}

foreach ($plugins as $slug => $entryFile) {
	$root = $pluginsRoot . '/' . $slug;
	$entry = $root . '/' . $entryFile;
	if (!is_dir($root) || !is_file($entry)) {
		fwrite(STDERR, "Missing isolated plugin source: {$slug}/{$entryFile}\n");
		exit(1);
	}
	$pluginIdentity = array_merge(
		array(
			'entry' => $slug . '/' . $entryFile,
			'entry_sha256' => hash_file('sha256', $entry),
		),
		$pluginHeader($entry),
		$treeHash($root)
	);
	$manifest['plugins'][$slug] = $pluginIdentity;
	if (isset($contractPackageForSlug[$slug])) {
		$package = $contractPackageForSlug[$slug];
		$expectedVersion = (string) ($contracts['supported_versions'][$package] ?? '');
		if ($expectedVersion === '' || $pluginIdentity['version'] !== $expectedVersion) {
			fwrite(
				STDERR,
				"Unsupported isolated package version for {$slug}: expected {$expectedVersion}, found {$pluginIdentity['version']}.\n"
			);
			exit(1);
		}
	}
}

foreach (array('includes/core-compat.php', 'includes/admin-page.php', 'includes/tours.php') as $relative) {
	$path = $pluginsRoot . '/vms-fill-dates/' . $relative;
	$manifest['fill_dates_phase_2_files'][$relative] = hash_file('sha256', $path);
}

$json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
if (file_put_contents($outputPath, $json) === false) {
	fwrite(STDERR, "Could not write source manifest.\n");
	exit(1);
}

echo "Wrote isolated source manifest: {$outputPath}\n";
