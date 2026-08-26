<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/scripts/lib/wporg-prefix-inventory.php';
require_once dirname(__DIR__) . '/scripts/lib/wporg-prefix-migration-state.php';
require_once dirname(__DIR__) . '/scripts/lib/public-release.php';

$failures = array();
$assert = static function (bool $condition, string $message) use (&$failures): void {
	if (!$condition) {
		$failures[] = $message;
	}
};

$root = dirname(__DIR__);
$manifestPath = $root . '/docs/wporg-prefix-migration-manifest.json';
$releaseExcludes = array_values(array_filter(array_map('trim', file($root . '/release-public-excludes.txt') ?: array()), static function (string $line): bool {
	return $line !== '' && !str_starts_with($line, '#');
}));
$assert(in_array('docs/', $releaseExcludes, true), 'B1 manifest and documentation must remain outside the public package.');
$assert(in_array('scripts/', $releaseExcludes, true), 'B1 migration infrastructure must remain outside the public package.');
$assert(in_array('tests/', $releaseExcludes, true), 'B1 test infrastructure must remain outside the public package.');
$releaseTestIds = array_column(VMS_Public_Release_Tooling::defaultReleaseTests(), 'id');
$assert(in_array('wporg-prefix-manifest-guardrails', $releaseTestIds, true), 'Prefix guardrails must remain a required default release precondition.');
$assert(in_array('wporg-prefix-b2-foundation', $releaseTestIds, true), 'B2 foundation coverage must remain a required default release precondition.');
$manifest = json_decode((string) file_get_contents($manifestPath), true);
$assert(is_array($manifest), 'Machine-readable prefix manifest must decode.');
if (!is_array($manifest)) {
	fwrite(STDERR, "Prefix manifest guardrail failures:\n- Manifest did not decode.\n");
	exit(1);
}
$assert(($manifest['schema_version'] ?? null) === 2, 'B2 manifest schema must be version 2.');

$requiredCategories = array(
	'php_functions',
	'classes_interfaces',
	'constants',
	'globals',
	'hooks',
	'options',
	'post_meta',
	'user_meta',
	'transients',
	'tables',
	'cron',
	'action_scheduler',
	'rest',
	'ajax',
	'shortcodes',
	'handles',
	'cpt_taxonomy',
	'capabilities_roles',
	'nonces',
	'query_rewrite',
	'cli',
	'protocol_headers',
	'public_extension_apis',
);
$categoryFields = array(
	'current_identifier',
	'canonical_target',
	'b0_strategy',
	'compatibility_classification',
	'persistence_external_contract_status',
	'planned_implementation_batch',
	'do_not_rename_current',
);
$categoriesById = array();
foreach ((array) ($manifest['categories'] ?? array()) as $category) {
	$id = (string) ($category['id'] ?? '');
	$categoriesById[$id] = $category;
	foreach ($categoryFields as $field) {
		$assert(array_key_exists($field, $category), "Category {$id} must provide {$field}.");
	}
}
foreach ($requiredCategories as $requiredCategory) {
	$assert(isset($categoriesById[$requiredCategory]), "Manifest must include category {$requiredCategory}.");
}
$assert(count($categoriesById) === 25, 'Manifest must retain all 25 B0 categories A-Y.');
$assert(isset($categoriesById['namespaces'], $categoriesById['tests_tooling_assets']), 'Manifest must retain the namespace and tests/tooling categories.');

$expectedCounts = array(
	'public_php_files' => 271,
	'functions' => array('unique' => 4521, 'occurrences' => 4541),
	'classes' => array('unique' => 23, 'occurrences' => 23),
	'interfaces' => array('unique' => 1, 'occurrences' => 1),
	'constants' => array('unique' => 107, 'occurrences' => 116),
	'namespaces' => array('unique' => 0, 'occurrences' => 0),
	'global_slots' => array('unique' => 44, 'occurrences' => 232),
);
foreach ($expectedCounts as $key => $expected) {
	$assert(($manifest['php_inventory_counts'][$key] ?? null) === $expected, "Semantic count {$key} must match the B1 baseline.");
}

$dynamicExpected = array(
	'exact_function_literals_unique' => 3645,
	'exact_function_literals_occurrences' => 7363,
	'function_exists_unique' => 3310,
	'function_exists_occurrences' => 6338,
	'direct_literal_callbacks_unique' => 711,
	'direct_literal_callbacks_occurrences' => 767,
	'exact_type_literals_unique' => 16,
	'exact_type_literals_occurrences' => 25,
	'duplicate_function_families' => 20,
	'duplicate_constant_families' => 9,
);
foreach ($dynamicExpected as $key => $expected) {
	$assert(($manifest['php_inventory_counts']['dynamic_symbols'][$key] ?? null) === $expected, "Dynamic-symbol count {$key} must match the B2 intermediate state.");
}
$assert(
	array_keys((array) ($manifest['dynamic_symbols']['reflection_references'] ?? array())) === array('vms_get_active_season_dates'),
	'Reflection baseline must retain the exact vms_get_active_season_dates reference.'
);

$requiredEntryFields = array(
	'current_identifier',
	'canonical_target',
	'b0_strategy',
	'compatibility_classification',
	'persistence_external_contract_status',
	'planned_implementation_batch',
	'do_not_rename',
	'declaration_sites',
);
$currentProhibited = array();
$declaredByKind = array();
foreach ((array) ($manifest['symbols'] ?? array()) as $kind => $entries) {
	$targets = array();
	$declaredByKind[$kind] = array_fill_keys(array_column((array) $entries, 'current_identifier'), true);
	foreach ((array) $entries as $entry) {
		foreach ($requiredEntryFields as $field) {
			$assert(array_key_exists($field, $entry), "Symbol {$kind} entry must provide {$field}.");
		}
		$current = (string) ($entry['current_identifier'] ?? '');
		$target = $entry['canonical_target'] ?? null;
		if (is_string($target) && $target !== '') {
			$assert(!isset($targets[$target]), "Canonical target {$target} must be unique within {$kind}.");
			$targets[$target] = true;
		}
		$plain = (string) preg_replace('/^(?:GLOBALS:|global:|loader:)/', '', $current);
		if (preg_match('/^[A-Za-z][A-Za-z0-9]{1,2}_/', $plain) === 1) {
			$currentProhibited[] = $kind . ':' . $current;
		}
	}
}
sort($currentProhibited, SORT_STRING);
$assert(
	$currentProhibited === ($manifest['current_prohibited_globals'] ?? null),
	'Current prohibited-global inventory must exactly equal current semantic declarations.'
);
$prohibitedBaseline = (array) ($manifest['prohibited_global_baseline'] ?? array());
$assert(count($prohibitedBaseline) === 4696, 'Immutable B1 prohibited-global allowance set must contain exactly 4,696 declarations/slots.');
$assert(count($currentProhibited) === 4521, 'B2 must reduce the current prohibited-global set to exactly 4,521 B3 functions.');
$assert(array_diff($currentProhibited, $prohibitedBaseline) === array(), 'No new prohibited global declaration may appear outside the immutable B1 allowance set.');
$committedBaseline = json_decode((string) file_get_contents($root . '/docs/wporg-prefix-prohibited-global-baseline.json'), true);
$assert($prohibitedBaseline === $committedBaseline, 'Manifest ratchet allowance set must equal the separate immutable B1 baseline file.');

$completedB2 = (array) ($manifest['completed_batches']['B2'] ?? array());
$assert(($completedB2['status'] ?? null) === 'complete', 'Manifest must mark B2 complete.');
$assert(($completedB2['unique_symbols'] ?? null) === array('classes' => 23, 'interfaces' => 1, 'constants' => 107, 'global_slots' => 44), 'B2 unique-symbol map must remain exactly 23/1/107/44.');
$assert(($completedB2['declaration_sites'] ?? null) === array('classes' => 23, 'interfaces' => 1, 'constants' => 116, 'global_slots' => 232), 'B2 declaration-site map must remain exactly 23/1/116/232.');
$assert(($completedB2['forbidden_global_ratchet'] ?? null) === array('before' => 4696, 'after' => 4521, 'reduction' => 175), 'B2 ratchet must record the exact 4,696 to 4,521 reduction.');
$b2SymbolMap = (array) ($completedB2['symbol_map'] ?? array());
$assert(count($b2SymbolMap) === 175, 'B2 completed symbol map must contain exactly 175 unique legacy-to-canonical mappings.');
$expectedRemoved = array();
foreach ($b2SymbolMap as $entry) {
	$kind = (string) ($entry['kind'] ?? '');
	$legacy = (string) ($entry['legacy_identifier'] ?? '');
	$canonical = (string) ($entry['canonical_identifier'] ?? '');
	$expectedRemoved[] = $kind . ':' . $legacy;
	$assert($legacy !== '' && $canonical !== '' && $legacy !== $canonical, "B2 map entry {$kind} must provide distinct legacy and canonical identifiers.");
	$assert(isset($declaredByKind[$kind][$canonical]), "B2 canonical declaration must exist: {$kind}:{$canonical}.");
	$assert(!isset($declaredByKind[$kind][$legacy]), "B2 legacy declaration must be absent: {$kind}:{$legacy}.");
}
sort($expectedRemoved, SORT_STRING);
$actualRemoved = array_values(array_diff($prohibitedBaseline, $currentProhibited));
sort($actualRemoved, SORT_STRING);
$assert($actualRemoved === $expectedRemoved, 'The exact 175 ratchet removals must equal the completed B2 symbol map and no other declaration.');

foreach ((array) ($manifest['symbols'] ?? array()) as $kind => $entries) {
	foreach ((array) $entries as $entry) {
		$target = $entry['canonical_target'] ?? null;
		$current = (string) ($entry['current_identifier'] ?? '');
		if (is_string($target) && $target !== '' && $target !== $current) {
			$assert(!isset($declaredByKind[$kind][$target]), "Canonical target {$target} must not collide with an existing core declaration.");
		}
	}
}

$functionDeclarations = $declaredByKind['functions'] ?? array();
$externalDynamicContracts = array();
foreach ((array) ($manifest['dynamic_symbols']['function_resolution_requirements'] ?? array()) as $name => $requirements) {
	$requirement = $requirements[0] ?? array();
	if (($requirement['resolution_policy'] ?? '') !== 'core-current-or-canonical-must-resolve') {
		$externalDynamicContracts[] = $name;
		continue;
	}
	$canonical = $requirement['canonical_target'] ?? null;
	$assert(
		isset($functionDeclarations[$name]) || (is_string($canonical) && isset($functionDeclarations[$canonical])),
		"Dynamic function contract {$name} must resolve through its current or canonical declaration."
	);
}
$assert($externalDynamicContracts === array(
	'vms_dt_render_tools_home',
	'vms_dt_reporting_build_event_model',
	'vms_dt_reporting_build_square_line_evidence',
	'vms_dt_reporting_build_ticket_source_rollup',
	'vms_dt_reporting_build_website_detail_rows',
	'vms_format_currency',
	'vms_get_all_venue_ids',
	'vms_get_event_plan_id_by_date',
	'vms_ops_admin_render_presets_page',
	'vms_ops_admin_render_settings_page',
	'vms_ops_admin_render_teams_page',
	'vms_ops_default_settings',
	'vms_ops_ticket_apply_post_show_buffer',
	'vms_ops_ticket_post_show_scan_buffer_hours',
	'vms_render_docs_admin_page',
	'vms_sch_season_is_blackout_date',
	'vms_square_actuals_has_hard_errors',
	'vms_square_get_event_actuals',
	'vms_sync_tec_status_from_plan',
	'vms_vendor_flag_updated',
), 'External or dynamic function contracts must remain an exact reviewed 20-entry baseline.');

$fixture = <<<'PHP'
<?php
function abc_new_global() {}
function bvmgr_allowed_global() {}
$retained = 'vms_physical_table_name';
add_action('vms_legacy_hook', 'bvmgr_allowed_global');
PHP;
$fixtureDeclarations = BVMGR_WPORG_Prefix_Inventory::scanSource($fixture);
$fixtureProhibited = BVMGR_WPORG_Prefix_Inventory::prohibitedDeclarations($fixtureDeclarations);
$assert($fixtureProhibited === array('functions:abc_new_global'), 'Semantic ratchet must reject a new short-prefix declaration only.');

$legacyFixture = <<<'PHP'
<?php
function vms_new_unmanifested_global() {}
PHP;
$legacyProhibited = BVMGR_WPORG_Prefix_Inventory::prohibitedDeclarations(
	BVMGR_WPORG_Prefix_Inventory::scanSource($legacyFixture)
);
$assert($legacyProhibited === array('functions:vms_new_unmanifested_global'), 'New vms_* declarations must be detectable outside the baseline.');

$publicApis = (array) ($manifest['public_extension_apis'] ?? array());
$assert(count($publicApis) === 13, 'Public extension API map must contain exactly 13 entries/types.');
$publicApiNames = array_column($publicApis, 'current_identifier');
$assert(count(array_unique($publicApiNames)) === 13, 'Public extension API identifiers must be unique.');
foreach ($publicApis as $api) {
	$assert(!empty($api['requires_coordinated_cutover']), 'Each public extension API must declare coordinated cutover.');
	$assert(($api['compatibility_classification'] ?? '') === 'coordinated-cutover-no-public-package-legacy-wrapper', 'Public API policy must forbid public-package legacy wrappers.');
}
$providerApi = array_values(array_filter($publicApis, static fn(array $api): bool => ($api['type'] ?? '') === 'interface'));
$assert(count($providerApi) === 1 && ($providerApi[0]['legacy_identifier'] ?? null) === 'VMS_Social_Provider_Interface' && ($providerApi[0]['current_identifier'] ?? null) === 'BVMGR_Social_Provider_Interface', 'Public provider interface must record its completed B2 cutover exactly.');

$addons = (array) ($manifest['known_addons'] ?? array());
$assert(array_column($addons, 'slug') === array('vms-events-slider', 'vms-fill-dates', 'vms-data-tools', 'vms-express-bar', 'vms-refer-a-friend'), 'Known add-on list and order must remain authoritative.');
$assert(array_column($addons, 'remaining_batch_dependencies') === array(
	array('B2', 'B3', 'B7'),
	array('B3', 'B7'),
	array('B2', 'B3', 'B7'),
	array('B2', 'B3', 'B7'),
	array('B3', 'B4', 'B7'),
), 'Known add-on later-batch dependencies must match their exact consumed contract classes.');
$b2AddonMap = array();
foreach ($addons as $addon) {
	$assert(($addon['external_tree_modified'] ?? null) === false, 'B1 must not claim external add-on edits.');
	$assert(!empty($addon['consumed_contracts']), 'Each add-on must freeze consumed contracts.');
	foreach ((array) ($addon['consumed_contracts']['b2_php_symbols'] ?? array()) as $dependency) {
		$key = implode(':', array($addon['slug'], $dependency['kind'], $dependency['current_identifier']));
		$b2AddonMap[$key] = array(
			'canonical_target' => $dependency['canonical_target'],
			'evidence_files' => $dependency['evidence_files'],
		);
	}
}
ksort($b2AddonMap, SORT_STRING);
$assert($b2AddonMap === array(
	'vms-data-tools:classes:VMS_Vendor_Schema_Registry' => array(
		'canonical_target' => 'BVMGR_Vendor_Schema_Registry',
		'evidence_files' => array('includes/admin/page-vendor-import.php', 'includes/services/vendor-import/vendor-import-engine.php'),
	),
	'vms-data-tools:constants:VMS_USER_PRIMARY_VENDOR_META_KEY' => array(
		'canonical_target' => 'BVMGR_USER_PRIMARY_VENDOR_META_KEY',
		'evidence_files' => array('includes/vendor-invites/orchestrator.php'),
	),
	'vms-data-tools:constants:VMS_VENDOR_PRIMARY_USER_META_KEY' => array(
		'canonical_target' => 'BVMGR_VENDOR_PRIMARY_USER_META_KEY',
		'evidence_files' => array('includes/vendor-invites/helpers.php', 'includes/vendor-invites/orchestrator.php'),
	),
	'vms-data-tools:constants:VMS_VENUE_CPT' => array(
		'canonical_target' => 'BVMGR_VENUE_CPT',
		'evidence_files' => array('includes/admin/page-payables-export.php', 'includes/admin/page-revenue-intelligence.php'),
	),
	'vms-events-slider:constants:VMS_CALENDAR_FEED_CACHE_BUST_OPTION' => array(
		'canonical_target' => 'BVMGR_CALENDAR_FEED_CACHE_BUST_OPTION',
		'evidence_files' => array('vms-events-slider.php'),
	),
	'vms-express-bar:constants:VMS_PLUGIN_FILE' => array(
		'canonical_target' => 'BVMGR_PLUGIN_FILE',
		'evidence_files' => array('includes/helpers.php'),
	),
	'vms-express-bar:constants:VMS_VERSION' => array(
		'canonical_target' => 'BVMGR_VERSION',
		'evidence_files' => array('includes/helpers.php'),
	),
), 'B1 must freeze the exact complete seven-entry semantic B2 add-on dependency map.');

$map = BVMGR_Prefix_Compatibility_Map::fromFile($manifestPath);
$assert($map->canonicalFor('vms_register_admin_page') === 'bvmgr_register_admin_page', 'Compatibility map must resolve the known public Admin Page API.');
$assert($map->canonicalFor('VMS_Social_Provider_Interface') === 'BVMGR_Social_Provider_Interface', 'Compatibility map must resolve the public provider interface.');
$assert(BVMGR_Prefix_Compatibility_Map::readOrder('bvmgr_key', 'vms_key') === array('bvmgr_key', 'vms_key'), 'Dual-read order must be canonical first.');
$assert(BVMGR_Prefix_Compatibility_Map::writeTargets('bvmgr_key', 'vms_key', false) === array('bvmgr_key'), 'Canonical-write policy must not mirror by default.');
$assert(BVMGR_Prefix_Compatibility_Map::writeTargets('bvmgr_key', 'vms_key', true) === array('bvmgr_key', 'vms_key'), 'Rollback-safe policy may explicitly mirror.');
$assert(BVMGR_Prefix_Compatibility_Map::fireOrder('bvmgr_hook', 'vms_hook') === array('bvmgr_hook', 'vms_hook'), 'Dual-fire order must be canonical first.');

$command = escapeshellarg(PHP_BINARY) . ' -d memory_limit=1G ' . escapeshellarg($root . '/scripts/generate-wporg-prefix-manifest.php') . ' --check 2>&1';
$output = array();
$status = 0;
exec($command, $output, $status);
$assert($status === 0, 'Committed manifest must match a fresh semantic generation: ' . implode(' ', $output));

if ($failures !== array()) {
	fwrite(STDERR, "Prefix manifest guardrail failures:\n- " . implode("\n- ", $failures) . "\n");
	exit(1);
}

echo "Prefix manifest and semantic guardrail tests passed.\n";
