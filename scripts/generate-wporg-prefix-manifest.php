<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/wporg-prefix-inventory.php';
require_once __DIR__ . '/lib/wporg-prefix-b3.php';
require_once __DIR__ . '/lib/wporg-prefix-b3-waves.php';

final class BVMGR_WPORG_Prefix_Manifest_Generator
{
	public const MANIFEST = 'docs/wporg-prefix-migration-manifest.json';
	public const PROHIBITED_BASELINE = 'docs/wporg-prefix-prohibited-global-baseline.json';

	public static function build(string $root): array
	{
		$inventory = BVMGR_WPORG_Prefix_Inventory::scan($root);
		$b3 = self::reconcileB3($root, $inventory);
		$inventory = $b3['inventory'];
		self::classifyPostB4Symbols($inventory['symbols']);
		$publicApis = self::publicApis($b3['current_names']);
		self::markPublicApis($inventory['symbols'], $publicApis);
		$currentProhibited = self::prohibitedBaseline($inventory['symbols']);
		$prohibitedBaseline = self::loadProhibitedBaseline($root);

		return array(
			'schema_version' => 5,
			'authority' => array(
				'document' => 'docs/WPORG_PREFIX_MIGRATION_B0.md',
				'supplied_b0_sha256' => '7893dc878cff48e86a981771c0e52f3119a4a3202307c73ab24817bb863f3dc9',
				'b0_persistence_commit' => '3fb278c',
				'description' => 'Authoritative Phase B0 design after reviewer clarification that global prefixes require at least four characters.',
			),
			'b0_evidence_corrections' => array(
				array(
					'category' => 'php_functions',
					'b0_reported' => array('unique' => 4519, 'declarations' => 4539),
					'b1_semantic_inventory' => array('unique' => 4521, 'declarations' => 4541),
					'identifiers' => array('vms_event_plan_runtime_redirect_targets', 'vms_module_registry'),
					'cause' => 'The B0 probe did not skip the PHP ampersand token before by-reference function names.',
					'architecture_impact' => 'none; both use Strategy 1 in B3 and add no new compatibility class.',
				),
			),
			'b1_corrections' => array(
				array(
					'id' => 'complete-b2-addon-dependency-map',
					'cause' => 'The original B1 add-on map inventoried functions, hooks, retained physical identifiers, and handles but did not semantically compare add-on PHP against every B2-owned class, interface, constant, and request-global symbol.',
					'correction' => 'A token-based five-add-on rescan freezes seven exact B2 dependencies across vms-events-slider, vms-data-tools, and vms-express-bar.',
					'architecture_impact' => 'B2 requires isolated coordinated add-on cutovers before the core symbol rename; no public-package legacy aliases are introduced.',
				),
				array(
					'id' => 'complete-b2-global-slot-inventory',
					'cause' => 'The original B1 inventory used a bounded loader-name list and did not model the global WordPress template-loader scope of the vendor-profile template.',
					'correction' => 'B2.5 records and prefixes 38 vendor-template globals plus three loader globals through an explicit old-to-new map.',
					'architecture_impact' => 'The historical B2 map and ratchet remain immutable; a separate complete semantic ledger and scanner-row inventory govern B2.5 and later batches.',
				),
			),
			'b4_evidence_corrections' => self::b4EvidenceCorrections(),
			'b4_identifier_inventory' => self::b4IdentifierInventory($root),
			'b4_addon_compatibility' => self::b4AddonCompatibility($root),
			'canonical_prefix_family' => array(
				'procedural_php_hooks_options' => 'bvmgr_',
				'constants_low_churn_classes' => 'BVMGR_',
				'future_namespaced_oo' => 'BackstageVenueManager\\',
				'asset_handles' => 'bvmgr-',
				'rest_namespace' => 'backstage-venue-manager/v1',
				'protocol_headers' => 'X-Backstage-Venue-Manager-*',
			),
			'strategy_definitions' => self::strategies(),
			'categories' => self::categories(),
			'php_inventory_counts' => $inventory['counts'],
			'symbols' => $inventory['symbols'],
			'dynamic_symbols' => $inventory['dynamic_symbols'],
			'prohibited_global_baseline' => $prohibitedBaseline,
			'current_prohibited_globals' => $currentProhibited,
			'complete_semantic_ledger' => self::completeSemanticLedger($b3['counts']),
			'completed_batches' => array(
				'B2' => self::completedB2($inventory['symbols'], $prohibitedBaseline, $currentProhibited),
				'B2_5' => self::completedB2_5($inventory['symbols']),
				'B3' => self::completedB3($root, $b3),
			),
			'public_extension_apis' => $publicApis,
			'known_addons' => self::knownAddons($b3['current_names']),
			'migration_state' => self::migrationState(),
			'compatibility_policies' => self::compatibilityPolicies(),
			'private_bridge_contract' => array(
				'create_now' => false,
				'public_package_distribution' => 'forbidden',
				'trigger' => 'A proven unknown private integration cannot coordinate the canonical cutover.',
				'distribution' => 'Separately distributed private compatibility plugin only.',
				'behavior' => 'Map only the frozen 13 legacy PHP entry points/types to canonical equivalents after canonical availability checks.',
				'persistence_changes' => false,
				'removal_gate' => 'All identified private consumers have moved to canonical APIs.',
			),
			'do_not_rename' => self::doNotRename(),
		);
	}

	public static function currentProhibitedBaseline(string $root): array
	{
		$inventory = BVMGR_WPORG_Prefix_Inventory::scan($root);
		return self::prohibitedBaseline($inventory['symbols']);
	}

	public static function render(array $manifest): string
	{
		$json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		if (!is_string($json)) {
			throw new RuntimeException('Unable to encode prefix manifest.');
		}
		return $json . PHP_EOL;
	}

	private static function b4EvidenceCorrections(): array
	{
		return array(
			array(
				'id' => 'complete-browser-global-inventory',
				'previous_summary' => array('confirmed_window_VMS_globals' => 5),
				'corrected_inventory' => array('plugin_owned_browser_globals' => 29),
				'cause' => 'B0 counted only uppercase window.VMS_* contracts and omitted shipped localized, camel-case API, and request-local window properties.',
				'architecture_impact' => 'Additive B4 producer/consumer inventory only; no B0-B3 runtime architecture changes.',
			),
			array(
				'id' => 'semantic-asset-handle-inventory',
				'previous_summary' => array('unique' => 66, 'registration_sites' => 99, 'concrete_consumers' => 27),
				'corrected_inventory' => array('unique' => 64, 'registration_sites' => 99, 'dependency_sites' => 34),
				'cause' => 'The historical probe did not freeze row-level handle membership, missed seven data-flow dependency sites, and overcounted two identities.',
				'architecture_impact' => 'The approved bvmgr-* target remains; only exact membership and consumer accounting change.',
			),
			array(
				'id' => 'refer-a-friend-admin-slug-not-handle',
				'previous_classification' => 'vms-refer-a-friend consumes asset handle vms-admin',
				'corrected_classification' => 'The sole exact occurrence is a retained admin-page parent slug; semantic asset-API consumers are zero.',
				'evidence' => 'Disposable add-on includes/class-vms-raf-plugin.php detect_vms_parent_slug() candidate list; enqueue dependency arrays are empty.',
				'architecture_impact' => 'No B4 add-on patch and no dependency-only legacy handle alias are required.',
			),
			array(
				'id' => 'exact-nonce-action-inventory',
				'previous_summary' => array('static_actions' => 156, 'dynamic_action_families' => 64, 'total' => 220),
				'corrected_inventory' => array('static_actions' => 154, 'dynamic_action_families' => 64, 'total' => 218),
				'cause' => 'The historical total was hard-coded without row identities. Token scans at the manifest-introduction commit and authorized B4 starting HEAD both produce the same 154 static actions; no two missing identifiers exist.',
				'architecture_impact' => 'The approved nonce compatibility strategy remains unchanged; invented rows are forbidden.',
			),
		);
	}

	private static function b4IdentifierInventory(string $root): array
	{
		$relative = 'docs/wporg-prefix-b4-identifier-map.json';
		$path = $root . '/' . $relative;
		if (!is_file($path)) {
			throw new RuntimeException('Missing frozen B4 identifier map: ' . $relative);
		}
		$decoded = json_decode((string) file_get_contents($path), true);
		if (!is_array($decoded) || !isset($decoded['summary'])) {
			throw new RuntimeException('Invalid frozen B4 identifier map: ' . $relative);
		}
		return array(
			'artifact' => $relative,
			'sha256' => hash_file('sha256', $path),
			'summary' => $decoded['summary'],
			'frozen_from_head' => 'bdd84df7bcbfcec65ee57fedf561bf4e167761f6',
			'implementation_state' => 'complete',
		);
	}

	private static function b4AddonCompatibility(string $root): array
	{
		$relative = 'docs/wporg-prefix-b4-addon-compatibility.json';
		$path = $root . '/' . $relative;
		if (!is_file($path)) {
			throw new RuntimeException('Missing B4 add-on compatibility evidence: ' . $relative);
		}
		$decoded = json_decode((string) file_get_contents($path), true);
		if (!is_array($decoded) || !isset($decoded['summary'])) {
			throw new RuntimeException('Invalid B4 add-on compatibility evidence: ' . $relative);
		}
		return array(
			'artifact' => $relative,
			'sha256' => hash_file('sha256', $path),
			'summary' => $decoded['summary'],
		);
	}

	private static function strategies(): array
	{
		return array(
			'1' => 'Direct rename with no persisted/external compatibility concern.',
			'2' => 'Rename plus a deliberately bounded temporary compatibility alias.',
			'3' => 'Dual read with canonical new writes.',
			'4' => 'Versioned, idempotent data/schema migration.',
			'5' => 'Dual fire or dual register for transitional contracts.',
			'6' => 'Retain the historical physical storage identifier.',
			'7' => 'Remove an obsolete identifier after its evidence-based gate.',
			'8' => 'Human/reviewer decision required; use sparingly.',
		);
	}

	private static function categories(): array
	{
		return array(
			self::category('php_functions', 'A', array('unique' => 4521, 'declarations' => 4541), 'vms_* global function declarations', 'bvmgr_*', array(1), 'no public-package legacy wrappers', 'global PHP; plausibly externally callable', 'B3', false, 'Critical'),
			self::category('classes_interfaces', 'B', array('classes' => 23, 'interfaces' => 1, 'traits' => 0, 'enums' => 0), 'VMS_* global types', 'BVMGR_*; future OO uses BackstageVenueManager\\', array(1, 8), 'coordinated cutover for the public provider interface', 'global PHP type; one plausible public interface', 'B2', false, 'High'),
			self::category('constants', 'C', array('unique' => 107, 'definitions' => 116), 'VMS_* PHP constants', 'BVMGR_* PHP names; values follow their owning category', array(1), 'no blanket legacy constant aliases', 'global PHP names; values may be persistent/external', 'B2', false, 'High'),
			self::category('namespaces', 'D', array('unique' => 0), 'none', 'BackstageVenueManager\\ for future OO', array(1), 'no existing namespace conversion in B2/B3', 'nonpersistent PHP', 'future', false, 'Medium'),
			self::category('globals', 'E', array('GLOBALS_slots' => 35, 'direct_globals' => 4, 'loader_temporaries' => 8, 'template_globals' => 38, 'total' => 85), 'plugin-owned shared request globals', 'bvmgr_*', array(1), 'atomic reader/writer rename', 'request-local nonpersistent state', 'B2/B2.5', false, 'Medium'),
			self::category('hooks', 'F', array('filters' => 152, 'literal_actions' => 23, 'task_actions' => 4, 'dynamic_families' => 1, 'dormant_filters' => 2, 'total' => 182), 'vms_* plugin-owned custom hooks', 'bvmgr_*', array(5, 7), 'canonical-first dual-fire; internal listeners canonical only', 'external WordPress contract', 'B7', true, 'High'),
			self::category('options', 'G', array('static' => 115, 'dynamic_families' => 2, 'site_options' => 0), 'vms_* option families', 'bvmgr_*', array(3, 4, 6), 'canonical-first reads and canonical writes after copy', 'persistent per-site state', 'B5', true, 'Critical'),
			self::category('post_meta', 'H', array('static' => 610, 'dynamic_families' => 3), '_vms_* and related plugin-owned keys', '_bvmgr_* where migration is justified', array(3, 4, 6), 'retain query/join/relationship keys when physical migration is disproportionate', 'persistent content state', 'B5', true, 'Critical'),
			self::category('user_meta', 'I', array('static' => 24, 'dynamic_families' => 9), '_vms_* and vms_* keys', '_bvmgr_* or bvmgr_*', array(3, 4, 6), 'network-global usermeta requires site-aware authorization tests', 'persistent network-global user state', 'B5', true, 'High'),
			self::category('transients', 'J', array('static' => 8, 'dynamic_families' => 26, 'site_transients' => 0), 'vms_* transient families', 'bvmgr_*', array(1, 3), 'expire disposable values; bridge locks/jobs through maximum TTL', 'temporary persistent state', 'B5', true, 'Medium'),
			self::category('tables', 'K', array('physical_suffixes' => 40), '40 physical vms_* table suffixes', 'BVMGR_* accessors returning legacy physical names', array(6), 'physical names retained', 'persistent database schema', 'B6', true, 'Critical'),
			self::category('cron', 'L', array('active' => 22, 'cleanup_only' => 1, 'total' => 23), 'vms_* cron hooks', 'bvmgr_* except retained cleanup-only identifier', array(4, 5, 6, 7), 'schedule new before clearing recurring legacy; temporarily listen on both', 'persisted scheduler contract', 'B6', true, 'High'),
			self::category('action_scheduler', 'M', array('hooks' => 3, 'active_groups' => 2), 'vms_* hooks and vms-* groups', 'bvmgr_* hooks and backstage-venue-manager-* groups', array(3, 5, 6), 'drain old queued rows; never rewrite scheduler tables', 'persisted external scheduler contract', 'B6', true, 'High'),
			self::category('rest', 'N', array('namespaces' => 1, 'routes' => 16, 'registrations' => 17), 'vms/v1', 'backstage-venue-manager/v1', array(5), 'dual-register identical handlers, permissions, and schemas', 'public HTTP contract', 'B7', true, 'High'),
			self::category('ajax', 'O', array('actions' => 41, 'registrations' => 45, 'nopriv' => 4), 'vms_* action values', 'bvmgr_*', array(5), 'dual-register identical callbacks and preserve auth/nonces/responses', 'public/admin HTTP contract', 'B7', true, 'High'),
			self::category('shortcodes', 'P', array('total' => 17, 'legacy_prefixed' => 16, 'unprefixed_legacy_noop' => 1), 'vms_* plus event_ticket_button', 'add bvmgr_* aliases', array(5, 6), 'retain stored legacy tags indefinitely pending content audit', 'stored public content contract', 'B7', true, 'High'),
			self::category('handles', 'Q', array('unique' => 64, 'registration_sites' => 99, 'dependency_sites' => 34), 'vms-*', 'bvmgr-*', array(2), 'dependency-only aliases only where a semantic external asset-API consumer is proven; current known-add-on set has none', 'public dependency graph contract', 'B4', true, 'Medium-High'),
			self::category('cpt_taxonomy', 'R', array('cpts' => 15, 'taxonomies' => 3, 'legacy_cpt_aliases' => 2, 'further_semantic_uses' => 651), 'vms_* physical identifiers', 'canonical accessors returning retained values', array(6), 'do not physically rename', 'persistent WordPress storage and URL contract', 'B6', true, 'Critical'),
			self::category('capabilities_roles', 'S', array('capabilities' => 15, 'static_roles' => 3, 'dynamic_role_families' => 1), 'vms_* grants and roles', 'bvmgr_*', array(4), 'temporary dual authorization; retain legacy grants for rollback', 'persistent authorization state', 'B5', true, 'Critical'),
			self::category('nonces', 'T', array('static_actions' => 154, 'dynamic_action_families' => 64, 'action_families_total' => 218, 'custom_field_names' => 73), 'vms_* actions and custom fields', 'bvmgr_* actions and _bvmgr_* fields', array(3, 5), 'canonical generation with bounded legacy verification/read', 'cached form/security contract; not durable data', 'B4', true, 'Medium'),
			self::category('query_rewrite', 'U', array('query_vars' => 14, 'registrations' => 15, 'rules' => 7, 'rewrite_tags' => 4, 'consumers' => 27), 'vms_* inbound identifiers', 'bvmgr_*', array(5), 'keep legacy inbound URLs and flush once under migration guard', 'public URL contract and rewrite state', 'B4', true, 'High'),
			self::category('cli', 'V', array('command_paths' => 3), 'vms command paths', 'bvmgr command paths', array(5), 'temporary deprecated legacy command aliases', 'operator-facing external contract', 'B4', true, 'Low-Medium'),
			self::category('protocol_headers', 'W', array('headers' => 5, 'protocol_markers' => 1), 'X-VMS-* and vms-admission:', 'X-Backstage-Venue-Manager-* and canonical admission marker', array(3, 5, 6), 'permanently accept issued QR protocol values', 'external protocol/signature contract', 'B7', true, 'High'),
			self::category('public_extension_apis', 'X', array('semantic_families' => 6, 'entry_points_types' => 13), '13 named vms_*/VMS_* PHP contracts', 'bvmgr_*/BVMGR_*', array(1, 8), 'coordinated cutover; private bridge only if proven necessary', 'plausible external PHP API', 'B2/B3 coordinated', false, 'High'),
			self::category('tests_tooling_assets', 'Y', array('identifier_bearing_tests' => 195, 'scripts' => 5, 'docs' => 147, 'root_historical_tooling_files' => 150, 'shipped_assets' => 27, 'browser_globals_assigned_to_B4' => 29), 'mixed current and historical identifiers', 'canonical current tooling; retained historical evidence/fixtures', array(1, 6, 7), 'reason-coded residuals only; browser-global contracts are carved into B4 by the controlling B4 authorization', 'mixed release-excluded and shipped clients', 'B8 except browser globals in B4', true, 'High'),
		);
	}

	private static function category(
		string $id,
		string $b0Category,
		array $inventory,
		string $current,
		string $target,
		array $strategy,
		string $compatibility,
		string $status,
		string $batch,
		bool $doNotRename,
		string $risk
	): array {
		return array(
			'id' => $id,
			'b0_category' => $b0Category,
			'semantic_inventory' => $inventory,
			'current_identifier' => $current,
			'canonical_target' => $target,
			'b0_strategy' => $strategy,
			'compatibility_classification' => $compatibility,
			'persistence_external_contract_status' => $status,
			'planned_implementation_batch' => $batch,
			'do_not_rename_current' => $doNotRename,
			'risk' => $risk,
		);
	}

	private static function reconcileB3(string $root, array $inventory): array
	{
		$mapPath = $root . '/' . BVMGR_WPORG_Prefix_B3::MAP_PATH;
		if (!is_file($mapPath)) {
			return array(
				'inventory' => $inventory,
				'current_names' => array(),
				'states' => array(),
				'counts' => array(
					'baseline_unique_functions' => 4521,
					'baseline_declaration_sites' => 4541,
					'migrated_unique_functions' => 0,
					'migrated_declaration_sites' => 0,
					'remaining_legacy_unique_functions' => 4521,
					'remaining_legacy_declaration_sites' => 4541,
				),
				'map' => array(),
			);
		}

		$map = BVMGR_WPORG_Prefix_B3::loadJson($mapPath);
		BVMGR_WPORG_Prefix_B3::validateMap($map);
		$currentFunctions = array();
		foreach ((array) ($inventory['symbols']['functions'] ?? array()) as $entry) {
			$currentFunctions[(string) $entry['current_identifier']] = $entry;
		}
		$functions = array();
		$currentNames = array();
		$states = array();
		$migratedUnique = 0;
		$migratedSites = 0;
		$remainingUnique = 0;
		$remainingSites = 0;
		$mappedFunctionNames = array();
		foreach ((array) ($map['mappings'] ?? array()) as $mapping) {
			$legacy = (string) $mapping['legacy_identifier'];
			$canonical = (string) $mapping['canonical_identifier'];
			$mappedFunctionNames[$legacy] = true;
			$mappedFunctionNames[$canonical] = true;
			$legacyEntry = $currentFunctions[$legacy] ?? null;
			$canonicalEntry = $currentFunctions[$canonical] ?? null;
			if (is_array($legacyEntry) === is_array($canonicalEntry)) {
				throw new RuntimeException('B3 manifest reconciliation requires exactly one declaration identity for ' . $legacy . '.');
			}
			$entry = is_array($canonicalEntry) ? $canonicalEntry : $legacyEntry;
			$sites = count((array) ($entry['declaration_sites'] ?? array()));
			$expectedSites = count((array) ($mapping['declaration_sites'] ?? array()));
			if ($sites !== $expectedSites) {
				throw new RuntimeException('B3 declaration-site count drift for ' . $legacy . '.');
			}
			$entry['canonical_target'] = $canonical;
			$entry['b0_strategy'] = array(1);
			$entry['compatibility_classification'] = 'direct-rename-no-public-package-wrapper';
			$entry['persistence_external_contract_status'] = 'nonpersistent-global-php';
			$entry['planned_implementation_batch'] = 'B3';
			$entry['do_not_rename'] = false;
			if (is_array($canonicalEntry)) {
				$entry['legacy_identifier'] = $legacy;
				$entry['migration_status'] = 'complete';
				$states[$legacy] = 'migrated';
				$currentNames[$legacy] = $canonical;
				$migratedUnique++;
				$migratedSites += $sites;
			} else {
				unset($entry['legacy_identifier'], $entry['migration_status']);
				$states[$legacy] = 'pending';
				$currentNames[$legacy] = $legacy;
				$remainingUnique++;
				$remainingSites += $sites;
			}
			$functions[] = $entry;
		}
		foreach ($currentFunctions as $name => $entry) {
			if (isset($mappedFunctionNames[$name])) {
				continue;
			}
			$sites = (array) ($entry['declaration_sites'] ?? array());
			$isB4Support = str_starts_with($name, 'bvmgr_')
				&& $sites !== array()
				&& count(array_filter($sites, static fn(array $site): bool => ($site['file'] ?? '') === 'includes/core/prefix-b4-compat.php')) === count($sites);
			$isPostB4CanonicalAddition = str_starts_with($name, 'bvmgr_')
				&& $sites !== array()
				&& count(array_filter($sites, static fn(array $site): bool => str_starts_with((string) ($site['file'] ?? ''), 'includes/'))) === count($sites);
			if (!$isB4Support && !$isPostB4CanonicalAddition) {
				throw new RuntimeException('Post-B3 function declaration is not assigned to B4 support infrastructure: ' . $name . '.');
			}
			$entry['canonical_target'] = $name;
			$entry['b0_strategy'] = $isB4Support ? array(5) : array(1);
			$entry['compatibility_classification'] = $isB4Support
				? 'B4 canonical compatibility infrastructure'
				: 'post-B4 canonical feature addition';
			$entry['persistence_external_contract_status'] = $isB4Support
				? 'internal nonpersistent compatibility runtime'
				: 'nonpersistent global PHP introduced after B4';
			$entry['planned_implementation_batch'] = $isB4Support ? 'B4' : 'post-B4';
			$entry['do_not_rename'] = true;
			$functions[] = $entry;
		}
		usort($functions, static fn(array $a, array $b): int => (string) $a['current_identifier'] <=> (string) $b['current_identifier']);
		$inventory['symbols']['functions'] = $functions;
		$inventory['dynamic_symbols'] = self::reconcileB3Dynamic((array) $inventory['dynamic_symbols'], $map, $currentNames);
		$inventory['counts']['dynamic_symbols'] = self::dynamicCounts($inventory['dynamic_symbols']);

		return array(
			'inventory' => $inventory,
			'current_names' => $currentNames,
			'states' => $states,
			'counts' => array(
				'baseline_unique_functions' => 4521,
				'baseline_declaration_sites' => 4541,
				'migrated_unique_functions' => $migratedUnique,
				'migrated_declaration_sites' => $migratedSites,
				'remaining_legacy_unique_functions' => $remainingUnique,
				'remaining_legacy_declaration_sites' => $remainingSites,
			),
			'map' => $map,
		);
	}

	private static function reconcileB3Dynamic(array $dynamic, array $map, array $currentNames): array
	{
		$keys = array(
			'exact_literal' => 'exact_function_literals',
			'function_exists' => 'function_exists_checks',
			'callback' => 'direct_literal_callbacks',
			'reflection' => 'reflection_references',
		);
		$rebuilt = array_fill_keys(array_values($keys), array());
		$mappedNames = array();
		foreach ((array) ($map['mappings'] ?? array()) as $mapping) {
			$legacy = (string) $mapping['legacy_identifier'];
			$current = (string) ($currentNames[$legacy] ?? $legacy);
			$mappedNames[$legacy] = true;
			$mappedNames[(string) $mapping['canonical_identifier']] = true;
			foreach ($keys as $mapKey => $manifestKey) {
				$sites = array_values((array) ($mapping['baseline_dynamic_sites'][$mapKey] ?? array()));
				if ($sites !== array()) {
					$rebuilt[$manifestKey][$current] = $sites;
				}
			}
		}
		// Preserve reviewed external/dynamic contracts that are not core declarations.
		foreach (array('function_exists_checks', 'direct_literal_callbacks', 'reflection_references') as $manifestKey) {
			foreach ((array) ($dynamic[$manifestKey] ?? array()) as $name => $sites) {
				if (!isset($mappedNames[$name])) {
					$rebuilt[$manifestKey][$name] = $sites;
				}
			}
		}
		foreach ($rebuilt as $key => $value) {
			ksort($value, SORT_STRING);
			$dynamic[$key] = $value;
		}
		$duplicates = array();
		foreach ((array) ($map['mappings'] ?? array()) as $mapping) {
			if (empty($mapping['duplicate_family'])) {
				continue;
			}
			$legacy = (string) $mapping['legacy_identifier'];
			$duplicates[$currentNames[$legacy] ?? $legacy] = $mapping['declaration_sites'];
		}
		ksort($duplicates, SORT_STRING);
		$dynamic['duplicate_function_families'] = $duplicates;

		$requirements = array();
		$requiredLegacy = array();
		foreach ((array) ($map['mappings'] ?? array()) as $mapping) {
			$legacy = (string) $mapping['legacy_identifier'];
			$dynamicSites = (array) ($mapping['baseline_dynamic_sites'] ?? array());
			if ((array) ($dynamicSites['function_exists'] ?? array()) === array()
				&& (array) ($dynamicSites['callback'] ?? array()) === array()
				&& (array) ($dynamicSites['reflection'] ?? array()) === array()) {
				continue;
			}
			$current = (string) ($currentNames[$legacy] ?? $legacy);
			$requirements[$current][] = array(
				'current_identifier' => $current,
				'canonical_target' => (string) $mapping['canonical_identifier'],
				'resolution_policy' => 'core-current-or-canonical-must-resolve',
			);
			$requiredLegacy[$legacy] = true;
		}
		foreach ((array) ($dynamic['function_resolution_requirements'] ?? array()) as $name => $entries) {
			if (!isset($mappedNames[$name])) {
				$requirements[$name] = $entries;
			}
		}
		ksort($requirements, SORT_STRING);
		$dynamic['function_resolution_requirements'] = $requirements;
		return $dynamic;
	}

	private static function classifyPostB4Symbols(array &$symbols): void
	{
		$expected = array(
			'classes' => array(
				'BVMGR_CLI_Event_Integrity_Command',
				'BVMGR_CLI_Event_Reschedule_Command',
			),
			'global_slots' => array(
				'GLOBALS:bvmgr_event_occurrence_admin_form_plan_id',
				'GLOBALS:bvmgr_event_occurrence_last_blocked_write',
				'GLOBALS:bvmgr_event_occurrence_write_depth',
				'GLOBALS:bvmgr_event_plan_request_cache_generation',
				'GLOBALS:bvmgr_external_ticketing_panel_rendered',
			),
		);
		foreach ($expected as $kind => $names) {
			$found = array();
			foreach ($symbols[$kind] as &$entry) {
				$name = (string) ($entry['current_identifier'] ?? '');
				if (!in_array($name, $names, true)) {
					continue;
				}
				$found[] = $name;
				$entry['canonical_target'] = $name;
				$entry['b0_strategy'] = array(1);
				$entry['compatibility_classification'] = 'post-B4 canonical feature addition';
				$entry['persistence_external_contract_status'] = 'nonpersistent global PHP introduced after B4';
				$entry['planned_implementation_batch'] = 'post-B4';
				$entry['do_not_rename'] = true;
				unset($entry['legacy_identifier'], $entry['migration_status']);
			}
			unset($entry);
			sort($found, SORT_STRING);
			$sortedExpected = $names;
			sort($sortedExpected, SORT_STRING);
			if ($found !== $sortedExpected) {
				throw new RuntimeException('Post-B4 canonical ' . $kind . ' additions do not match the exact integrated set.');
			}
		}
	}

	private static function dynamicCounts(array $dynamic): array
	{
		$siteCount = static fn(array $map): int => array_sum(array_map('count', $map));
		return array(
			'exact_function_literals_unique' => count((array) ($dynamic['exact_function_literals'] ?? array())),
			'exact_function_literals_occurrences' => $siteCount((array) ($dynamic['exact_function_literals'] ?? array())),
			'function_exists_unique' => count((array) ($dynamic['function_exists_checks'] ?? array())),
			'function_exists_occurrences' => $siteCount((array) ($dynamic['function_exists_checks'] ?? array())),
			'direct_literal_callbacks_unique' => count((array) ($dynamic['direct_literal_callbacks'] ?? array())),
			'direct_literal_callbacks_occurrences' => $siteCount((array) ($dynamic['direct_literal_callbacks'] ?? array())),
			'exact_type_literals_unique' => count((array) ($dynamic['exact_type_literals'] ?? array())),
			'exact_type_literals_occurrences' => $siteCount((array) ($dynamic['exact_type_literals'] ?? array())),
			'duplicate_function_families' => count((array) ($dynamic['duplicate_function_families'] ?? array())),
			'duplicate_constant_families' => count((array) ($dynamic['duplicate_constant_families'] ?? array())),
		);
	}

	private static function publicApis(array $currentNames = array()): array
	{
		$definitions = array(
			array('Admin Page Registry', 'function', 'vms_register_admin_page', 'bvmgr_register_admin_page', array('vms-refer-a-friend')),
			array('Tours Registry', 'function', 'vms_register_tour', 'bvmgr_register_tour', array()),
			array('Tours Registry', 'function', 'vms_get_registered_tours', 'bvmgr_get_registered_tours', array()),
			array('Tours Registry', 'function', 'vms_get_tour_registry', 'bvmgr_get_tour_registry', array()),
			array('Season Availability', 'function', 'vms_sch_is_venue_open_on_date', 'bvmgr_sch_is_venue_open_on_date', array()),
			array('Season Availability', 'function', 'vms_sch_season_generate_active_dates', 'bvmgr_sch_season_generate_active_dates', array()),
			array('Docs Registry', 'function', 'vms_docs_sources', 'bvmgr_docs_sources', array()),
			array('Docs Registry', 'function', 'vms_docs_index', 'bvmgr_docs_index', array()),
			array('Social Providers', 'interface', 'VMS_Social_Provider_Interface', 'BVMGR_Social_Provider_Interface', array()),
			array('Social Providers', 'function', 'vms_social_get_providers', 'bvmgr_social_get_providers', array()),
			array('Social Providers', 'function', 'vms_social_get_provider', 'bvmgr_social_get_provider', array()),
			array('Notifications', 'function', 'vms_notify_get_providers', 'bvmgr_notify_get_providers', array()),
			array('Notifications', 'function', 'vms_notify_user', 'bvmgr_notify_user', array()),
		);
		return array_map(static function (array $definition) use ($currentNames): array {
			$completedB2 = $definition[1] === 'interface';
			$completedB3 = $definition[1] === 'function' && ($currentNames[$definition[2]] ?? $definition[2]) === $definition[3];
			$completed = $completedB2 || $completedB3;
			$entry = array(
				'family' => $definition[0],
				'type' => $definition[1],
				'current_identifier' => $completed ? $definition[3] : $definition[2],
				'canonical_target' => $definition[3],
				'b0_strategy' => array(1, 8),
				'compatibility_classification' => 'coordinated-cutover-no-public-package-legacy-wrapper',
				'persistence_external_contract_status' => 'plausible-external-php-api',
				'planned_implementation_batch' => $definition[1] === 'interface' ? 'B2' : 'B3',
				'do_not_rename' => false,
				'known_addon_consumers' => $definition[4],
				'requires_coordinated_cutover' => true,
			);
			if ($completed) {
				$entry['legacy_identifier'] = $definition[2];
				$entry['migration_status'] = 'complete';
			}
			return $entry;
		}, $definitions);
	}

	private static function markPublicApis(array &$symbols, array $publicApis): void
	{
		$lookup = array();
		foreach ($publicApis as $api) {
			$lookup[$api['legacy_identifier'] ?? $api['current_identifier']] = $api;
		}
		foreach ($symbols as &$entries) {
			foreach ($entries as &$entry) {
				$lookupName = $entry['legacy_identifier'] ?? $entry['current_identifier'];
				if (isset($lookup[$lookupName])) {
					$entry['b0_strategy'] = array(1, 8);
					$entry['compatibility_classification'] = 'coordinated-cutover-no-public-package-legacy-wrapper';
					$entry['persistence_external_contract_status'] = 'plausible-external-php-api';
				}
			}
			unset($entry);
		}
		unset($entries);
	}

	private static function completedB2(array $symbols, array $baseline, array $currentProhibited): array
	{
		$symbolMap = array();
		$unique = array('classes' => 0, 'interfaces' => 0, 'constants' => 0, 'global_slots' => 0);
		$sites = array_fill_keys(array_keys($unique), 0);
		foreach (array_keys($unique) as $kind) {
			foreach ((array) ($symbols[$kind] ?? array()) as $entry) {
				if (($entry['migration_status'] ?? '') !== 'complete' || ($entry['planned_implementation_batch'] ?? '') !== 'B2') {
					continue;
				}
				$unique[$kind]++;
				$sites[$kind] += count((array) ($entry['declaration_sites'] ?? array()));
				$symbolMap[] = array(
					'kind' => $kind,
					'legacy_identifier' => $entry['legacy_identifier'],
					'canonical_identifier' => $entry['current_identifier'],
					'declaration_sites' => $entry['declaration_sites'],
				);
			}
		}
		usort($symbolMap, static fn(array $a, array $b): int => array($a['kind'], $a['legacy_identifier']) <=> array($b['kind'], $b['legacy_identifier']));
		return array(
			'status' => 'complete',
			'scope' => 'bootstrap, global classes/interface, constants, request-global slots, and central registries',
			'unique_symbols' => $unique,
			'declaration_sites' => $sites,
			'symbol_map' => $symbolMap,
			'forbidden_global_ratchet' => array(
				'before' => count($baseline),
				'after' => 4521,
				'reduction' => count($baseline) - 4521,
			),
			'b3_procedural_functions_renamed' => 0,
		);
	}

	private static function completedB2_5(array $symbols): array
	{
		$symbolMap = array();
		foreach ((array) ($symbols['global_slots'] ?? array()) as $entry) {
			if (($entry['migration_status'] ?? '') !== 'complete' || ($entry['planned_implementation_batch'] ?? '') !== 'B2.5') {
				continue;
			}
			$symbolMap[] = array(
				'kind' => 'global_slots',
				'legacy_identifier' => $entry['legacy_identifier'],
				'canonical_identifier' => $entry['current_identifier'],
				'declaration_sites' => $entry['declaration_sites'],
			);
		}
		usort($symbolMap, static fn(array $a, array $b): int => $a['legacy_identifier'] <=> $b['legacy_identifier']);
		$siteCount = array_sum(array_map(static fn(array $entry): int => count((array) $entry['declaration_sites']), $symbolMap));
		return array(
			'status' => 'complete',
			'scope' => 'correct the 38 vendor-template globals and three loader globals omitted from the original B1/B2 semantic inventory',
			'unique_symbols' => array('global_slots' => count($symbolMap)),
			'declaration_sites' => array('global_slots' => $siteCount),
			'scanner_rows_eliminated' => 57,
			'scanner_missed_semantic_slots' => 1,
			'symbol_map' => $symbolMap,
			'b3_procedural_functions_renamed' => 0,
		);
	}

	private static function completedB3(string $root, array $b3): array
	{
		$waveByFunction = array();
		$waveStatus = array();
		$planPath = $root . '/' . BVMGR_WPORG_Prefix_B3_Waves::PLAN_PATH;
		if (is_file($planPath)) {
			$plan = BVMGR_WPORG_Prefix_B3::loadJson($planPath);
			foreach ((array) ($plan['waves'] ?? array()) as $wave) {
				$id = (string) ($wave['wave'] ?? '');
				$total = count((array) ($wave['legacy_functions'] ?? array()));
				$migrated = 0;
				foreach ((array) ($wave['legacy_functions'] ?? array()) as $legacy) {
					$waveByFunction[$legacy] = $id;
					if (($b3['states'][$legacy] ?? '') === 'migrated') {
						$migrated++;
					}
				}
				$waveStatus[$id] = array(
					'migrated_unique_functions' => $migrated,
					'total_unique_functions' => $total,
					'status' => $migrated === 0 ? 'pending' : ($migrated === $total ? 'complete' : 'invalid_partial'),
				);
			}
		}
		$symbolMap = array();
		foreach ((array) ($b3['map']['mappings'] ?? array()) as $mapping) {
			$legacy = (string) $mapping['legacy_identifier'];
			if (($b3['states'][$legacy] ?? '') !== 'migrated') {
				continue;
			}
			$symbolMap[] = array(
				'legacy_identifier' => $legacy,
				'canonical_identifier' => (string) $mapping['canonical_identifier'],
				'wave' => $waveByFunction[$legacy] ?? null,
				'declaration_sites' => $mapping['declaration_sites'],
			);
		}
		$counts = (array) $b3['counts'];
		return array(
			'status' => (int) ($counts['remaining_legacy_unique_functions'] ?? 0) === 0 ? 'complete' : 'in_progress',
			'scope' => 'all frozen plugin-owned procedural PHP functions; direct rename with no public-package legacy wrappers',
			'counts' => $counts,
			'prohibited_global_ratchet' => array(
				'before' => 4521,
				'after' => (int) ($counts['remaining_legacy_unique_functions'] ?? 0),
				'reduction' => (int) ($counts['migrated_unique_functions'] ?? 0),
			),
			'wave_status' => $waveStatus,
			'symbol_map' => $symbolMap,
			'legacy_wrappers' => 0,
		);
	}

	private static function completeSemanticLedger(array $b3Counts): array
	{
		return array(
			'measurement' => 'unique prohibited global PHP semantic identifiers/slots; scanner rows are tracked separately',
			'historical_original_ratchet' => array(
				'pre_B2' => 4696,
				'post_B2' => 4521,
				'reduction' => 175,
				'immutable' => true,
			),
			'corrected_complete_counts' => array(
				'pre_B2' => 4737,
				'post_B2' => 4562,
				'post_B2_5' => 4521,
			),
			'originally_omitted' => array(
				'global_slots' => 41,
				'token_sites' => 194,
				'plugin_check_rows' => 57,
			),
			'current_B3' => $b3Counts,
		);
	}

	private static function prohibitedBaseline(array $symbols): array
	{
		$result = array();
		foreach ($symbols as $kind => $entries) {
			foreach ($entries as $entry) {
				$name = (string) $entry['current_identifier'];
				$plain = (string) preg_replace('/^(?:GLOBALS:|global:|loader:)/', '', $name);
				if (preg_match('/^[A-Za-z][A-Za-z0-9]{1,2}_/', $plain) === 1) {
					$result[] = $kind . ':' . $name;
				}
			}
		}
		sort($result, SORT_STRING);
		return $result;
	}

	private static function loadProhibitedBaseline(string $root): array
	{
		$path = $root . '/' . self::PROHIBITED_BASELINE;
		$decoded = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
		if (!is_array($decoded) || array_values(array_filter($decoded, 'is_string')) !== $decoded) {
			throw new RuntimeException('Invalid or missing immutable prohibited-global baseline: ' . self::PROHIBITED_BASELINE);
		}
		$normalized = array_values(array_unique($decoded));
		sort($normalized, SORT_STRING);
		if ($decoded !== $normalized) {
			throw new RuntimeException('Immutable prohibited-global baseline must be sorted and unique.');
		}
		return $decoded;
	}

	private static function knownAddons(array $currentNames = array()): array
	{
		$addons = array(
			self::addon(
				'vms-events-slider',
				array(
					self::b2Symbol('constants', 'VMS_CALENDAR_FEED_CACHE_BUST_OPTION', 'BVMGR_CALENDAR_FEED_CACHE_BUST_OPTION', array('vms-events-slider.php')),
				),
				array('vms_calendar_feed_cache_bust', 'vms_event_plan_get_public_reschedule_destination', 'vms_event_plan_get_status', 'vms_get_event_plan_for_tec_event', 'vms_meta_key', 'vms_resolve_event_plan_for_tec_event', 'vms_tec_is_cancelled_event', 'vms_ticketing_b_meta_key', 'vms_ticketing_v2_find_plan_id_by_tec_event_id'),
				array('save_post_vms_event_plan'),
				array('vms_event_plan'),
				array(),
				array('vms-events-slider.php'),
				array('B2', 'B3', 'B7')
			),
			self::addon(
				'vms-fill-dates',
				array(),
				array('vms_admin_ui_active_cluster', 'vms_admin_ui_nav_clusters', 'vms_admin_ui_render_shell', 'vms_calendar_assignment_status_for_plan', 'vms_calendar_feed_cache_bust', 'vms_calendar_get_event_slot_limits', 'vms_calendar_plan_vendor_ids', 'vms_calendar_vendor_primary_type', 'vms_event_plan_get_status', 'vms_event_plan_review_clean_text', 'vms_event_plan_review_get_changes', 'vms_event_plan_review_source_label', 'vms_event_plan_review_touch', 'vms_event_plan_set_secondary_vendors', 'vms_get_calendar_events', 'vms_meta_key', 'vms_register_module', 'vms_render_help_button'),
				array('vms_admin_ui_nav_clusters', 'vms_admin_ui_shell_pages', 'vms_admin_ui_active_cluster', 'vms_register_tours'),
				array('vms_event_plan', 'vms_vendor', 'vms_venue', 'vms_vendor_type'),
				array(),
				array('vms-fill-dates.php', 'includes/helpers.php', 'includes/tours.php'),
				array('B3', 'B7')
			),
			self::addon(
				'vms-data-tools',
				array(
					self::b2Symbol('classes', 'VMS_Vendor_Schema_Registry', 'BVMGR_Vendor_Schema_Registry', array('includes/admin/page-vendor-import.php', 'includes/services/vendor-import/vendor-import-engine.php')),
					self::b2Symbol('constants', 'VMS_USER_PRIMARY_VENDOR_META_KEY', 'BVMGR_USER_PRIMARY_VENDOR_META_KEY', array('includes/vendor-invites/orchestrator.php')),
					self::b2Symbol('constants', 'VMS_VENDOR_PRIMARY_USER_META_KEY', 'BVMGR_VENDOR_PRIMARY_USER_META_KEY', array('includes/vendor-invites/helpers.php', 'includes/vendor-invites/orchestrator.php')),
					self::b2Symbol('constants', 'VMS_VENUE_CPT', 'BVMGR_VENUE_CPT', array('includes/admin/page-payables-export.php', 'includes/admin/page-revenue-intelligence.php')),
				),
				array('vms_admin_guard_current_screen_id', 'vms_admin_guard_request_uri', 'vms_calculate_attendance_bonus_payout', 'vms_calendar_get_event_slot_limits', 'vms_core', 'vms_event_plan_get_status', 'vms_event_plan_set_secondary_vendors', 'vms_event_plan_status_label', 'vms_event_plan_status_normalize', 'vms_get_event_plan_comp_terms', 'vms_get_timezone', 'vms_meta_key', 'vms_normalize_email_cell', 'vms_payables_build_bills_for_export', 'vms_portal_notice', 'vms_pretty_structure_label', 'vms_resource_fingerprint_add_marker', 'vms_resource_fingerprint_flag', 'vms_resource_fingerprint_span_finish', 'vms_resource_fingerprint_span_start', 'vms_staffing_get_event_slots', 'vms_staffing_resolve_slot_window', 'vms_ticket_revenue_available_statuses', 'vms_ticket_revenue_build_report', 'vms_ticket_revenue_cents_to_decimal', 'vms_ticket_revenue_event_key', 'vms_ticket_revenue_is_valid_ymd', 'vms_ticket_revenue_normalize_args', 'vms_ticket_revenue_normalize_ymd', 'vms_ticket_revenue_wp_now_ymd', 'vms_vendor_portal_get_count_breakdown', 'vms_vendor_portal_get_progress_headcount_context', 'vms_vendor_schema'),
				array('vms_register_tours', 'vms_vendor_portal_nav_links', 'vms_vendor_portal_render_custom_tab', 'vms_register_docs_sources', 'vms_square_nightly_sync'),
				array('vms_event_plan', 'vms_vendor', 'vms_venue', 'vms_vendor_type'),
				array(),
				array('includes/services/upsert.php', 'includes/bootstrap.php', 'includes/vendor-invites/open-dates.php', 'includes/vendor-invites/portal.php', 'includes/docs-register.php', 'includes/admin/page-vendor-import.php', 'includes/services/vendor-import/vendor-import-engine.php', 'includes/vendor-invites/helpers.php', 'includes/vendor-invites/orchestrator.php', 'includes/admin/page-payables-export.php', 'includes/admin/page-revenue-intelligence.php'),
				array('B2', 'B3', 'B7')
			),
			self::addon(
				'vms-express-bar',
				array(
					self::b2Symbol('constants', 'VMS_PLUGIN_FILE', 'BVMGR_PLUGIN_FILE', array('includes/helpers.php')),
					self::b2Symbol('constants', 'VMS_VERSION', 'BVMGR_VERSION', array('includes/helpers.php')),
				),
				array('vms_get_event_plan_for_tec_event', 'vms_resolve_event_plan_for_tec_event'),
				array('add_meta_boxes_vms_event_plan', 'save_post_vms_event_plan', 'vms_admin_ui_nav_cluster_items'),
				array('vms_event_plan'),
				array(),
				array('includes/admin.php', 'includes/helpers.php'),
				array('B2', 'B3', 'B7')
			),
			self::addon(
				'vms-refer-a-friend',
				array(),
				array('vms_admin_ui_render_shell', 'vms_get_public_event_calendar_url', 'vms_register_admin_page'),
				array('vms_admin_register_pages'),
				array(),
				array(),
				array('includes/class-vms-raf-plugin.php'),
				array('B3', 'B7')
			),
		);
		foreach ($addons as &$addon) {
			foreach ($addon['consumed_contracts']['core_php_functions'] as &$function) {
				$function = $currentNames[$function] ?? $function;
			}
			unset($function);
		}
		unset($addon);
		return $addons;
	}

	private static function addon(string $slug, array $b2Symbols, array $functions, array $hooks, array $storage, array $handles, array $evidenceFiles, array $batches): array
	{
		return array(
			'slug' => $slug,
			'consumed_contracts' => array(
				'b2_php_symbols' => $b2Symbols,
				'core_php_functions' => $functions,
				'hooks' => $hooks,
				'physical_cpt_taxonomy_identifiers' => $storage,
				'asset_handles' => $handles,
			),
			'preparation_performed' => 'Frozen exact consumers through a token-based semantic scan and canonical compatibility policy; external add-on source remains unchanged.',
			'future_tolerance' => array(
				'b2_php_symbols' => 'Affected add-ons require an isolated coordinated identifier cutover before B2 core lands; no public-package aliases.',
				'php_functions' => 'Coordinated add-on update required before the corresponding B3 core slice; no public-package wrappers.',
				'hooks' => 'Core dual-fire policy in B7; CPT-generated hooks remain because physical CPT values are retained.',
				'physical_identifiers' => 'Already tolerant because B0 Strategy 6 retains these values.',
				'handles' => 'Core dependency-only legacy alias in B4 where consumed.',
			),
			'remaining_batch_dependencies' => $batches,
			'external_tree_modified' => false,
			'evidence_files' => $evidenceFiles,
		);
	}

	private static function b2Symbol(string $kind, string $current, string $canonical, array $evidenceFiles): array
	{
		return array(
			'kind' => $kind,
			'current_identifier' => $current,
			'canonical_target' => $canonical,
			'evidence_files' => $evidenceFiles,
		);
	}

	private static function migrationState(): array
	{
		return array(
			'marker' => 'bvmgr_prefix_migration_version',
			'journal' => 'bvmgr_prefix_migration_state',
			'coupled_to_plugin_version' => false,
			'scope' => 'per-site option state',
			'network_model' => 'Enumerate sites and run the same site-local state machine; no network-wide marker may mask an incomplete site.',
			'idempotency' => 'Completed step IDs and the final marker are checked before work; reruns skip verified steps.',
			'interruption_retry' => 'Persist current step and completed steps before/after each transition; retry the incomplete step.',
			'copy_before_cutover' => true,
			'legacy_deletion_first_release' => false,
			'rollback' => 'Retain legacy reads and values; mirror only explicitly safe writes or use a tested reverse projector.',
			'state_values' => array('pending', 'running', 'interrupted', 'complete'),
		);
	}

	private static function compatibilityPolicies(): array
	{
		return array(
			'canonical_legacy_mapping' => 'Every entry/family provides current_identifier and canonical_target.',
			'dual_read' => 'Read canonical first, then exact legacy identifier while its compatibility gate is active.',
			'canonical_write' => 'Write canonical after copy verification; optionally mirror only rollback-safe values.',
			'dual_fire_register' => 'Canonical contract first, then deprecated legacy contract with identical arguments/handlers.',
			'deprecation_tracking' => 'Reason-coded per family; never use a blanket residual allowlist.',
			'no_public_php_wrappers' => true,
		);
	}

	private static function doNotRename(): array
	{
		return array(
			array('identifier' => 'Backstage Venue Manager', 'reason' => 'canonical public name'),
			array('identifier' => 'backstage-venue-manager', 'reason' => 'canonical slug and text domain'),
			array('identifier' => 'backstage-venue-manager.php', 'reason' => 'canonical main plugin filename'),
			array('identifier' => 'vendor-management-system.php and vms.php', 'reason' => 'Phase A basename compatibility literals'),
			array('identifier' => 'vms-build.txt', 'reason' => 'release compatibility marker during prefix batches'),
			array('identifier' => 'live /vms/ Plugin URI', 'reason' => 'verified public route until separately changed'),
			array('identifier' => 'all 40 physical custom tables', 'reason' => 'Strategy 6 storage invariance'),
			array('identifier' => '15 CPTs, 3 taxonomies, and 2 legacy CPT aliases', 'reason' => 'Strategy 6 WordPress storage identity'),
			array('identifier' => 'stored legacy shortcode tags', 'reason' => 'content compatibility'),
			array('identifier' => 'vms-admission:', 'reason' => 'already-issued QR payload compatibility'),
			array('identifier' => 'vms_square_nightly_sync', 'reason' => 'cleanup-only historical scheduler identifier'),
			array('identifier' => 'external SKU/protocol values and operational VMS-managed terminology', 'reason' => 'persisted/integration behavior'),
			array('identifier' => 'third-party hooks/options/meta/tables/handles/roles/capabilities', 'reason' => 'not plugin-owned'),
			array('identifier' => 'CSS/body selectors derived from retained CPT/taxonomy/admin slugs', 'reason' => 'owned by retained physical identifiers'),
			array('identifier' => 'historical evidence and legacy-upgrade fixtures', 'reason' => 'audit and compatibility evidence'),
			array('identifier' => 'release-excluded includes/safety/', 'reason' => 'separate dormant prototype decision'),
		);
	}
}

$root = dirname(__DIR__);
$mode = $argv[1] ?? '--check';
$manifestPath = $root . '/' . BVMGR_WPORG_Prefix_Manifest_Generator::MANIFEST;
$baselinePath = $root . '/' . BVMGR_WPORG_Prefix_Manifest_Generator::PROHIBITED_BASELINE;

if ($mode === '--write-baseline') {
	if (file_exists($baselinePath)) {
		fwrite(STDERR, "Refusing to overwrite the immutable B1 prohibited-global baseline.\n");
		exit(1);
	}
	$baseline = BVMGR_WPORG_Prefix_Manifest_Generator::currentProhibitedBaseline($root);
	$baselineJson = json_encode($baseline, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
	if (file_put_contents($baselinePath, $baselineJson) === false) {
		fwrite(STDERR, "Unable to write the prohibited-global baseline.\n");
		exit(1);
	}
	echo "Wrote " . BVMGR_WPORG_Prefix_Manifest_Generator::PROHIBITED_BASELINE . ".\n";
	exit(0);
}

$rendered = BVMGR_WPORG_Prefix_Manifest_Generator::render(
	BVMGR_WPORG_Prefix_Manifest_Generator::build($root)
);

if ($mode === '--print') {
	echo $rendered;
	exit(0);
}
if ($mode === '--write') {
	if (file_put_contents($manifestPath, $rendered) === false) {
		fwrite(STDERR, "Unable to write {$manifestPath}.\n");
		exit(1);
	}
	echo "Wrote " . BVMGR_WPORG_Prefix_Manifest_Generator::MANIFEST . ".\n";
	exit(0);
}
if ($mode !== '--check') {
	fwrite(STDERR, "Usage: php scripts/generate-wporg-prefix-manifest.php [--check|--print|--write|--write-baseline]\n");
	exit(2);
}

$committed = is_file($manifestPath) ? (string) file_get_contents($manifestPath) : '';
if (!hash_equals(hash('sha256', $rendered), hash('sha256', $committed))) {
	fwrite(STDERR, "Prefix migration manifest is stale. Run with --write and inspect the semantic diff.\n");
	exit(1);
}

echo "Prefix migration manifest is current.\n";
