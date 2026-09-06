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
	'backstage-venue-manager' => 'backstage-venue-manager.php',
	'woocommerce' => 'woocommerce.php',
	'woocommerce-square' => 'woocommerce-square.php',
	'the-events-calendar' => 'the-events-calendar.php',
	'event-tickets' => 'event-tickets.php',
	'event-tickets-plus' => 'event-tickets-plus.php',
	'vms-events-slider' => 'vms-events-slider.php',
	'vms-fill-dates' => 'vms-fill-dates.php',
	'vms-data-tools' => 'vms-data-tools.php',
	'backstage-calendar-feeds' => 'backstage-calendar-feeds.php',
	'vms-express-bar' => 'vms-express-bar.php',
	'vms-refer-a-friend' => 'vms-refer-a-friend.php',
);
foreach ($contracts['plugins'] as $slug => $contract) {
	$entries[$slug] = basename((string) $contract['entry']);
}
$entries['drm-event-router'] = 'drm-event-router.php';

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
		'backstage-calendar-feeds' => 'accepted local 0.1.4 BVM navigation source; 0.1.3 predecessor excluded',
		'vms-data-tools' => 'accepted local 0.5.55 BVM reporting-provider source; 0.5.54 predecessor excluded',
		'vms-commerce-discounts' => 'local 0.2.13 source candidate derived from the exact frozen 0.2.12 archive; obsolete 0.2.11 excluded',
		'vms-sponsorships' => 'clean 0.1.28 successor candidate; active same-version-drifted 0.1.27 excluded',
		'vmsx-weather-risk' => 'local candidate derived from exact frozen 0.1.12 title-cleanup archive; installed 0.1.3 excluded',
		'drm-events-bridge' => 'immutable git archive of accepted 0.2.2 commit ' . (getenv('BVM_COMPAT_BRIDGE_COMMIT') ?: 'unknown') . '; dirty worktree excluded',
		'drm-event-router' => 'immutable git archive of current authoritative 0.1.3 commit ' . (getenv('BVM_COMPAT_ROUTER_COMMIT') ?: 'unknown'),
	),
	'frozen_baselines' => array(
		'vms-commerce-discounts' => array(
			'archive' => 'vms-commerce-discounts-0.2.12.zip',
			'archive_sha256' => '0cd5f4d2d0ce3dd9484d85442dff38783bfa45f17f46bdd942d1e9ba9962b001',
			'pristine_tree_sha256' => '3a3528dfa1ed5d76608f27504ffed4990d1c2c320f1897d4c0cb49200531a060',
			'pristine_file_count' => 26,
		),
		'vmsx-weather-risk' => array(
			'archive' => 'vmsx-weather-risk-0.1.12-title-cleanup.zip',
			'archive_sha256' => '5d57006c4ef190b5ac7786abce15f2585b4ffb302831451399224b0ef03ade28',
			'pristine_tree_sha256' => '131d3bace99b7860b1e1585c99b967604a00e0f0a13b6f71576d748c455111af',
			'pristine_file_count' => 35,
		),
	),
	'blocked' => $contracts['blocked'],
	'retired' => $contracts['retired'] ?? array(),
	'indirect' => $contracts['indirect'],
	'git_provenance' => array(
		'drm-events-bridge' => array(
			'commit' => getenv('BVM_COMPAT_BRIDGE_COMMIT') ?: '',
			'git_tree' => getenv('BVM_COMPAT_BRIDGE_TREE') ?: '',
			'bootstrap_sha256' => getenv('BVM_COMPAT_BRIDGE_BOOTSTRAP_SHA256') ?: '',
			'dirty_source_status_before_sha256' => getenv('BVM_COMPAT_BRIDGE_STATUS_BEFORE_SHA256') ?: '',
		),
		'drm-event-router' => array(
			'commit' => getenv('BVM_COMPAT_ROUTER_COMMIT') ?: '',
			'git_tree' => getenv('BVM_COMPAT_ROUTER_TREE') ?: '',
			'bootstrap_sha256' => getenv('BVM_COMPAT_ROUTER_BOOTSTRAP_SHA256') ?: '',
		),
	),
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
	if (isset($contracts['plugins'][$slug]) && $manifest['plugins'][$slug]['version'] !== (string) $contracts['plugins'][$slug]['version']) {
		fwrite(STDERR, "Staged plugin version does not match approved contract: {$slug}.\n");
		exit(1);
	}
}

$json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
if (file_put_contents($outputPath, $json) === false) {
	fwrite(STDERR, "Could not write the additional integration source manifest.\n");
	exit(1);
}

echo "Wrote additional integration source manifest: {$outputPath}\n";
