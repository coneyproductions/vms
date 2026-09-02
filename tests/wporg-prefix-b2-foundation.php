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
$assert(is_array($manifest), 'B2 test requires the deterministic prefix manifest.');
if (!is_array($manifest)) {
	fwrite(STDERR, "B2 foundation failures:\n- Manifest did not decode.\n");
	exit(1);
}

$scan = BVMGR_WPORG_Prefix_Inventory::scan($root);
$symbols = (array) ($scan['symbols'] ?? array());
$b2 = (array) ($manifest['completed_batches']['B2'] ?? array());
$b2Map = (array) ($b2['symbol_map'] ?? array());
$b3 = (array) ($manifest['completed_batches']['B3'] ?? array());
$b3Counts = (array) ($b3['counts'] ?? array());
$b3Current = array();
foreach ((array) ($b3['symbol_map'] ?? array()) as $entry) {
	$b3Current[(string) ($entry['legacy_identifier'] ?? '')] = (string) ($entry['canonical_identifier'] ?? '');
}
$b3Name = static fn(string $legacy): string => $b3Current[$legacy] ?? $legacy;
$declared = array();
foreach ($symbols as $kind => $entries) {
	$declared[$kind] = array_fill_keys(array_column((array) $entries, 'current_identifier'), true);
}

$expectedUnique = array('classes' => 23, 'interfaces' => 1, 'constants' => 107, 'global_slots' => 44);
$expectedSites = array('classes' => 23, 'interfaces' => 1, 'constants' => 116, 'global_slots' => 232);
$mappedUnique = array_fill_keys(array_keys($expectedUnique), 0);
$mappedSites = array_fill_keys(array_keys($expectedSites), 0);
$legacyByKind = array();
foreach ($b2Map as $entry) {
	$kind = (string) ($entry['kind'] ?? '');
	$legacy = (string) ($entry['legacy_identifier'] ?? '');
	$canonical = (string) ($entry['canonical_identifier'] ?? '');
	$assert(isset($expectedUnique[$kind]), "Unexpected B2 symbol kind {$kind}.");
	if (!isset($expectedUnique[$kind])) {
		continue;
	}
	$mappedUnique[$kind]++;
	$mappedSites[$kind] += count((array) ($entry['declaration_sites'] ?? array()));
	$legacyByKind[$kind][$legacy] = true;
	$assert(isset($declared[$kind][$canonical]), "Canonical B2 symbol is not declared: {$kind}:{$canonical}.");
	$assert(!isset($declared[$kind][$legacy]), "Legacy B2 symbol is still declared: {$kind}:{$legacy}.");
}
$assert($mappedUnique === $expectedUnique, 'B2 map must contain exactly 23 classes, 1 interface, 107 constants, and 44 globals.');
$assert($mappedSites === $expectedSites, 'B2 map must retain the exact 23/1/116/232 declaration-site counts.');

foreach (array('classes', 'interfaces', 'constants') as $kind) {
	foreach (array_keys((array) ($declared[$kind] ?? array())) as $name) {
		$assert(str_starts_with($name, 'BVMGR_'), "Current {$kind} declaration must use BVMGR_: {$name}.");
	}
}
foreach (array_keys((array) ($declared['global_slots'] ?? array())) as $slot) {
	$plain = (string) preg_replace('/^(?:GLOBALS:|global:|loader:|template:)/', '', $slot);
	$assert(str_starts_with($plain, 'bvmgr_'), "Current plugin-owned global slot must use bvmgr_: {$slot}.");
}

$functionNames = array_keys((array) ($declared['functions'] ?? array()));
$b4SupportFunctions = array_values(array_filter((array) ($manifest['symbols']['functions'] ?? array()), static fn(array $entry): bool => ($entry['planned_implementation_batch'] ?? '') === 'B4'));
$postB4Functions = array_values(array_filter((array) ($manifest['symbols']['functions'] ?? array()), static fn(array $entry): bool => ($entry['planned_implementation_batch'] ?? '') === 'post-B4'));
$assert(count($b4SupportFunctions) === 8, 'B4 must add exactly six nonce and two query/rewrite compatibility functions after the frozen 4,521-function B3 map.');
$assert(count($postB4Functions) === 173, 'The integrated ticketing/reschedule/communications lineage must add exactly 173 canonical procedural functions after B4.');
$assert(count($functionNames) === 4702, 'B2/B3 plus B4 support and the integrated post-B4 features must contain exactly 4,702 procedural function identities.');
$assert(count(array_filter($functionNames, static fn(string $name): bool => str_starts_with($name, 'bvmgr_'))) === (int) ($b3Counts['migrated_unique_functions'] ?? -1) + 181, 'Canonical procedural declarations must equal exact B3 progress plus eight B4 support and 173 post-B4 functions.');
$assert(count(array_filter($functionNames, static fn(string $name): bool => str_starts_with($name, 'vms_'))) === (int) ($b3Counts['remaining_legacy_unique_functions'] ?? -1), 'Legacy procedural declarations must equal the exact B3 remainder.');

$collisionFunctions = array();
foreach (array_keys((array) ($legacyByKind['global_slots'] ?? array())) as $slot) {
	$legacy = (string) preg_replace('/^(?:GLOBALS:|global:|loader:)/', '', $slot);
	if (isset($declared['functions'][$legacy]) || isset($declared['functions']['bvmgr_' . substr($legacy, 4)])) {
		$collisionFunctions[] = $legacy;
	}
}
sort($collisionFunctions, SORT_STRING);
$assert($collisionFunctions === array(
	'vms_admin_menu_registry',
	'vms_event_details_sidebar_manual_rendered',
	'vms_event_details_sidebar_rendered',
	'vms_event_plan_perf_request_state',
	'vms_event_plan_runtime_redirect_targets',
	'vms_event_plan_save_profiler_state',
	'vms_ticket_mutation_audit_context_stack',
	'vms_vendor_profiles_event_sidebar_rendered',
), 'The eight function/global collision names must keep their B3 function side unchanged.');

$duplicates = array_keys((array) ($scan['dynamic_symbols']['duplicate_constant_families'] ?? array()));
$assert($duplicates === array(
	'BVMGR_ADMIN_PARENT_SLUG',
	'BVMGR_CPT_EVENT_PLAN',
	'BVMGR_DB_TABLE_VENDOR_USER_LINKS_SUFFIX',
	'BVMGR_SCH_CURRENT_SCOPE_META_KEY',
	'BVMGR_SCH_CURRENT_VENUE_META_KEY',
	'BVMGR_USER_PRIMARY_VENDOR_META_KEY',
	'BVMGR_VENDOR_APP_CPT',
	'BVMGR_VENDOR_CPT',
	'BVMGR_VENDOR_PRIMARY_USER_META_KEY',
), 'All nine guarded duplicate constant families must resolve under BVMGR_ symbols.');

$dynamicCounts = (array) ($manifest['php_inventory_counts']['dynamic_symbols'] ?? array());
$assert(($dynamicCounts['function_exists_unique'] ?? null) === 3485 && ($dynamicCounts['function_exists_occurrences'] ?? null) === 6560, 'Integrated post-B4 function_exists inventory must match the exact current feature lineage.');
$assert(($dynamicCounts['direct_literal_callbacks_unique'] ?? null) === 727 && ($dynamicCounts['direct_literal_callbacks_occurrences'] ?? null) === 783, 'Integrated post-B4 callback inventory must match the exact current feature lineage.');
$assert(($dynamicCounts['exact_type_literals_unique'] ?? null) === 20 && ($dynamicCounts['exact_type_literals_occurrences'] ?? null) === 45, 'Integrated WP-CLI class/type literals must match the exact current feature lineage.');

$publicSource = '';
foreach ((array) ($scan['public_php_files'] ?? array()) as $file) {
	$publicSource .= "\n" . (string) file_get_contents($root . '/' . $file);
}
foreach (array('classes', 'interfaces', 'constants') as $kind) {
	foreach (array_keys((array) ($legacyByKind[$kind] ?? array())) as $legacy) {
		$present = preg_match('/(?<![A-Za-z0-9_])' . preg_quote($legacy, '/') . '(?![A-Za-z0-9_])/', $publicSource) === 1;
		$assert(!$present, "Public runtime must contain no legacy B2 {$kind} reference: {$legacy}.");
	}
}
foreach (array_keys((array) ($legacyByKind['global_slots'] ?? array())) as $slot) {
	$legacy = (string) preg_replace('/^(?:GLOBALS:|global:|loader:)/', '', $slot);
	if (str_starts_with($slot, 'GLOBALS:')) {
		$present = preg_match('/\$GLOBALS\s*\[\s*[\'\"]' . preg_quote($legacy, '/') . '[\'\"]\s*\]/', $publicSource) === 1;
	} else {
		$present = preg_match('/\$' . preg_quote($legacy, '/') . '(?![A-Za-z0-9_])/', $publicSource) === 1;
	}
	$assert(!$present, "Public runtime must contain no legacy B2 global-slot reference: {$slot}.");
}
$assert(strpos($publicSource, "class_alias('VMS_") === false, 'The public package must not ship blanket VMS_* class aliases.');
$assert(strpos($publicSource, 'interface_alias') === false, 'The public package must not invent interface aliases.');

$literalValues = array();
if (preg_match_all('/define\(\s*[\'\"](BVMGR_[A-Z0-9_]+)[\'\"]\s*,\s*([\'\"])(.*?)\2\s*\)/s', $publicSource, $matches, PREG_SET_ORDER)) {
	foreach ($matches as $match) {
		$literalValues[$match[1]][$match[3]] = true;
	}
}
$expectedRetainedValues = array(
	'BVMGR_PLUGIN_SLUG' => 'vms',
	'BVMGR_TEXTDOMAIN' => 'backstage-venue-manager',
	'BVMGR_REST_NAMESPACE' => 'vms/v1',
	'BVMGR_CPT_EVENT_PLAN' => 'vms_event_plan',
	'BVMGR_CPT_VENDOR' => 'vms_vendor',
	'BVMGR_CPT_STAFF' => 'vms_staff',
	'BVMGR_VENDOR_APP_CPT' => 'vms_vendor_app',
	'BVMGR_VENDOR_APP_CPT_LEGACY' => 'vms_vendor_application',
	'BVMGR_VENUE_CPT' => 'vms_venue',
	'BVMGR_VENDOR_PRIMARY_USER_META_KEY' => '_vms_vendor_user_id',
	'BVMGR_USER_PRIMARY_VENDOR_META_KEY' => '_vms_vendor_id',
	'BVMGR_META_EVENT_PLAN_VENUE_ID_LEGACY' => '_vms_event_plan_venue_id',
	'BVMGR_SCH_CURRENT_VENUE_META_KEY' => '_vms_current_venue_id',
	'BVMGR_PRIVATE_FILES_TABLE_SUFFIX' => 'vms_private_files',
	'BVMGR_SOCIAL_CRON_HOOK' => 'vms_social_process_queue',
	'BVMGR_CALENDAR_FEED_CACHE_BUST_OPTION' => 'vms_calendar_feed_cache_bust',
);
foreach ($expectedRetainedValues as $constant => $value) {
	$assert(isset($literalValues[$constant][$value]), "B2 must retain {$constant} value {$value}.");
}
foreach ($literalValues as $constant => $values) {
	if (str_starts_with($constant, 'BVMGR_DB_TABLE_')) {
		foreach (array_keys($values) as $value) {
			$assert(str_starts_with($value, 'vms_'), "Physical table suffix must remain legacy storage: {$constant}={$value}.");
		}
	}
}

$main = (string) file_get_contents($root . '/backstage-venue-manager.php');
$legacyBridge = (string) file_get_contents($root . '/vendor-management-system.php');
$delegatingBridge = (string) file_get_contents($root . '/vms.php');
$assert(strpos($main, "define('BVMGR_PLUGIN_FILE', __FILE__)") !== false, 'Canonical bootstrap must define BVMGR_PLUGIN_FILE.');
$assert(strpos($main, "register_activation_hook(__FILE__, '" . $b3Name('vms_activate_plugin') . "')") !== false, 'Activation callback must use the exact current B3 identity.');
$assert(strpos($main, "register_deactivation_hook(__FILE__, '" . $b3Name('vms_deactivate_plugin') . "')") !== false, 'Deactivation callback must use the exact current B3 identity.');
$assert(strpos($legacyBridge, "define('BVMGR_LEGACY_PLUGIN_FILE', __FILE__)") !== false, 'Legacy basename bridge must use the canonical constant symbol.');
$assert(strpos($legacyBridge, '$bvmgr_canonical_plugin_file') !== false, 'Legacy basename bridge must use the canonical loader-local variable.');
$assert(strpos($legacyBridge, $b3Name('vms_register_legacy_plugin_basename_compatibility')) !== false, 'Legacy bridge must use the exact current basename compatibility callback.');
$assert(strpos($delegatingBridge, 'backstage-venue-manager.php') !== false, 'vms.php must continue delegating to the canonical Phase A bootstrap.');

if ($failures !== array()) {
	fwrite(STDERR, "B2 foundation failures:\n- " . implode("\n- ", $failures) . "\n");
	exit(1);
}

echo "B2 foundation symbol, value, registry, and bootstrap tests passed.\n";
