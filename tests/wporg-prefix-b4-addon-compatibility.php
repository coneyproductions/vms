<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$evidencePath = $root . '/docs/wporg-prefix-b4-addon-compatibility.json';
$evidence = is_file($evidencePath) ? json_decode((string) file_get_contents($evidencePath), true) : null;
if (!is_array($evidence)) {
	fwrite(STDERR, "Missing B4 add-on compatibility evidence.\n");
	exit(1);
}

$summary = (array) ($evidence['summary'] ?? array());
if ($summary !== array('addons' => 5, 'b4_semantic_consumers' => 0, 'patches_required' => 0, 'installed_trees_modified' => 0)) {
	fwrite(STDERR, "B4 add-on summary changed.\n");
	exit(1);
}

$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/scripts/prepare-wporg-prefix-b4-addons.php') . ' --check 2>&1';
$output = array();
$status = 0;
exec($command, $output, $status);
if ($status !== 0) {
	fwrite(STDERR, "B4 add-on compatibility failed: " . implode(' ', $output) . "\n");
	exit(1);
}

echo "B4 disposable add-on compatibility passed for five unchanged add-ons.\n";
