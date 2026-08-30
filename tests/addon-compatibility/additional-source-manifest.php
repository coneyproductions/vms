<?php
declare(strict_types=1);

if ($argc !== 3) {
	fwrite(STDERR, "Usage: php additional-source-manifest.php <wordpress-root> <output-json>\n");
	exit(2);
}

$wordpressRoot = realpath($argv[1]);
$outputPath = $argv[2];
if (!is_string($wordpressRoot) || !is_file($wordpressRoot . '/wp-includes/version.php')) {
	fwrite(STDERR, "The isolated WordPress root could not be resolved.\n");
	exit(2);
}

$contracts = require __DIR__ . '/additional-runtime-contracts.php';
$pluginsRoot = $wordpressRoot . '/wp-content/plugins';
$entries = array(
	'backstage-venue-manager' => 'vendor-management-system.php',
	'woocommerce' => 'woocommerce.php',
	'woocommerce-square' => 'woocommerce-square.php',
	'the-events-calendar' => 'the-events-calendar.php',
	'event-tickets' => 'event-tickets.php',
	'event-tickets-plus' => 'event-tickets-plus.php',
	'vms-events-slider' => 'vms-events-slider.php',
	'vms-fill-dates' => 'vms-fill-dates.php',
	'vms-data-tools' => 'vms-data-tools.php',
	'vms-express-bar' => 'vms-express-bar.php',
	'vms-refer-a-friend' => 'vms-refer-a-friend.php',
);
foreach ($contracts['plugins'] as $slug => $contract) {
	$entries[$slug] = basename((string) $contract['entry']);
}

$treeHash = static function (string $root): array {
	$files = array();
	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
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
	$fields = array(
		'name' => 'Plugin Name',
		'version' => 'Version',
		'requires_wordpress' => 'Requires at least',
		'requires_php' => 'Requires PHP',
		'requires_plugins' => 'Requires Plugins',
	);
	$result = array();
	foreach ($fields as $key => $label) {
		$result[$key] = preg_match('/^[ \t\/*#@]*' . preg_quote($label, '/') . ':\s*(.+)$/mi', $source, $match) === 1 ? trim($match[1]) : '';
	}
	return $result;
};

$manifest = array(
	'wordpress_version' => '',
	'plugins' => array(),
	'source_selection' => array(
		'vms-commerce-discounts' => 'versioned installable archive 0.2.11; installed 0.2.4 and active temporary 0.2.9 copies excluded as stale',
		'vmsx-weather-risk' => 'latest versioned installable archive 0.1.12; installed active 0.1.3 copy excluded as stale',
		'drm-events-bridge' => 'not staged: blocked because the authoritative Git worktree is concurrently dirty',
	),
	'blocked' => $contracts['blocked'],
	'indirect' => $contracts['indirect'],
);

$versionSource = (string) file_get_contents($wordpressRoot . '/wp-includes/version.php');
if (preg_match('/\$wp_version\s*=\s*[\'\"]([^\'\"]+)/', $versionSource, $match) === 1) {
	$manifest['wordpress_version'] = $match[1];
}

foreach ($entries as $slug => $entryFile) {
	$root = $pluginsRoot . '/' . $slug;
	$entry = $root . '/' . $entryFile;
	if (!is_dir($root) || !is_file($entry)) {
		fwrite(STDERR, "Missing isolated plugin source: {$slug}/{$entryFile}\n");
		exit(1);
	}
	$manifest['plugins'][$slug] = array_merge(
		array('entry' => $slug . '/' . $entryFile, 'entry_sha256' => hash_file('sha256', $entry)),
		$pluginHeader($entry),
		$treeHash($root)
	);
}

$json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
if (file_put_contents($outputPath, $json) === false) {
	fwrite(STDERR, "Could not write the additional integration source manifest.\n");
	exit(1);
}

echo "Wrote additional integration source manifest: {$outputPath}\n";
