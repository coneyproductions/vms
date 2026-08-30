<?php
declare(strict_types=1);

$contracts = require __DIR__ . '/additional-runtime-contracts.php';
$failures = array();
$assert = static function (bool $condition, string $message) use (&$failures): void {
	if (!$condition) {
		$failures[] = $message;
	}
};

$expected = array(
	'drm-calendar-intake',
	'vms-commerce-discounts',
	'vms-investor-portal',
	'vms-meta-ads',
	'vms-ops-console-premium',
	'vms-safety-pro',
	'vms-season-passes',
	'vms-sponsorships',
	'vmsx-checkout-policies',
	'vmsx-weather-risk',
);

$assert(array_keys((array) ($contracts['plugins'] ?? array())) === $expected, 'The runnable direct-integration set must remain explicit and ordered.');
$assert(array_keys((array) ($contracts['blocked'] ?? array())) === array('drm-events-bridge'), 'DRM Events Bridge must remain an explicit blocked candidate until its source is frozen.');
$assert(isset($contracts['indirect']['drm-event-router']), 'DRM Event Router must remain outside the direct runtime matrix.');

$requiredFields = array(
	'name', 'version', 'entry', 'dependency', 'companions', 'marker', 'detection',
	'functions', 'classes', 'constants', 'hook_callbacks', 'menus', 'fallback_menus',
	'notice', 'post_types', 'taxonomies', 'meta', 'options', 'tables',
	'rest_namespaces', 'ajax_actions', 'cron_hooks',
);

foreach ((array) ($contracts['plugins'] ?? array()) as $slug => $plugin) {
	foreach ($requiredFields as $field) {
		$assert(array_key_exists($field, $plugin), $slug . ' is missing contract field ' . $field . '.');
	}
	$assert(($plugin['entry'] ?? '') === $slug . '/' . basename((string) ($plugin['entry'] ?? '')), $slug . ' entry must use its staged public directory.');
	$assert(is_array($plugin['companions']['required'] ?? null) && is_array($plugin['companions']['optional'] ?? null), $slug . ' companion dependencies must be split into required and optional lists.');
	foreach (array('functions', 'classes', 'constants', 'post_types', 'taxonomies', 'meta', 'options', 'tables', 'rest_namespaces', 'ajax_actions', 'cron_hooks') as $listField) {
		$list = $plugin[$listField] ?? null;
		$assert(is_array($list), $slug . ' ' . $listField . ' must be an array.');
		if (is_array($list)) {
			$assert(count($list) === count(array_unique($list)), $slug . ' ' . $listField . ' contains duplicates.');
		}
	}
	if (isset($plugin['guarded_constants'])) {
		$assert(is_array($plugin['guarded_constants']), $slug . ' guarded_constants must be an array when present.');
		$assert(count($plugin['guarded_constants']) === count(array_unique($plugin['guarded_constants'])), $slug . ' guarded_constants contains duplicates.');
	}
	foreach (array('guarded_functions', 'companion_functions') as $optionalFunctionField) {
		if (!isset($plugin[$optionalFunctionField])) {
			continue;
		}
		$assert(is_array($plugin[$optionalFunctionField]), $slug . ' ' . $optionalFunctionField . ' must be an array when present.');
		$assert(count($plugin[$optionalFunctionField]) === count(array_unique($plugin[$optionalFunctionField])), $slug . ' ' . $optionalFunctionField . ' contains duplicates.');
		foreach ($plugin[$optionalFunctionField] as $function) {
			$assert(preg_match('/^vms_[a-z0-9_]+$/', (string) $function) === 1, $slug . ' has an invalid ' . $optionalFunctionField . ' contract: ' . (string) $function);
		}
	}
	foreach ((array) ($plugin['functions'] ?? array()) as $function) {
		$assert(preg_match('/^vms_[a-z0-9_]+$/', (string) $function) === 1, $slug . ' has an invalid BVM function contract: ' . (string) $function);
	}
}

if ($failures !== array()) {
	fwrite(STDERR, "Additional first-party runtime contract failures:\n- " . implode("\n- ", $failures) . "\n");
	exit(1);
}

$functionEntries = 0;
$uniqueFunctions = array();
foreach ($contracts['plugins'] as $plugin) {
	foreach ($plugin['functions'] as $function) {
		++$functionEntries;
		$uniqueFunctions[$function] = true;
	}
}

echo sprintf(
	"Additional first-party runtime contracts passed: %d runnable / 1 blocked / %d required function entries / %d unique required functions.\n",
	count($contracts['plugins']),
	$functionEntries,
	count($uniqueFunctions)
);
