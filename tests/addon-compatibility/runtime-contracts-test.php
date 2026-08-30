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
$entries = array();
$uniqueFunctions = array();

foreach ($expectedAddons as $addon) {
	$functions = $contracts['functions'][$addon] ?? null;
	$assert(is_array($functions), 'Missing function contract list for ' . $addon . '.');
	if (!is_array($functions)) {
		continue;
	}

	$assert(count($functions) === count(array_unique($functions)), 'Duplicate function contract within ' . $addon . '.');
	foreach ($functions as $function) {
		$assert(is_string($function) && preg_match('/^vms_[a-z0-9_]+$/', $function) === 1, 'Invalid function contract: ' . var_export($function, true));
		$entries[] = $addon . ':' . $function;
		$uniqueFunctions[$function] = true;
	}

	foreach (array('classes', 'constants', 'hooks', 'hook_callbacks') as $group) {
		$assert(isset($contracts[$group][$addon]) && is_array($contracts[$group][$addon]), 'Missing ' . $group . ' contracts for ' . $addon . '.');
	}
}

$assert(count($entries) === 63, 'Historical callable-consumption inventory must contain exactly 63 add-on/function entries.');
$assert(count($uniqueFunctions) === 53, 'Historical callable-consumption inventory must contain exactly 53 unique BVM functions.');
$assert(count($entries) === count(array_unique($entries)), 'Callable-consumption entries must be unique.');

if ($failures !== array()) {
	fwrite(STDERR, "BVM add-on runtime contract failures:\n- " . implode("\n- ", $failures) . "\n");
	exit(1);
}

echo "BVM add-on runtime contracts passed: 63 entries / 53 unique functions.\n";
