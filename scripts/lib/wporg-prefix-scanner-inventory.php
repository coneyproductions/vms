<?php
declare(strict_types=1);

/**
 * Deterministic reconciliation for packaged PrefixAllGlobals findings.
 *
 * The semantic prefix manifest and the raw Plugin Check finding inventory are
 * deliberately separate. This class joins them where a semantic identifier
 * exists and preserves evidence-backed scanner exceptions as individual rows.
 */
final class BVMGR_WPORG_Prefix_Scanner_Inventory
{
	public const SCHEMA_VERSION = 1;
	public const ARTIFACT_PATH = 'docs/wporg-prefix-scanner-inventory.json';

	public const CATEGORY_B3 = 'REQUIRED_MIGRATION_B3';
	public const CATEGORY_B7 = 'REQUIRED_MIGRATION_B7';
	public const CATEGORY_METHOD_SCOPE = 'SCANNER_FALSE_POSITIVE_METHOD_SCOPE';
	public const CATEGORY_EXTERNAL = 'THIRD_PARTY_OR_CORE_CONTRACT';

	private const PREFIX_CODE = 'WordPress.NamingConventions.PrefixAllGlobals.';
	private const FUNCTION_CODE = self::PREFIX_CODE . 'NonPrefixedFunctionFound';
	private const HOOK_CODE = self::PREFIX_CODE . 'NonPrefixedHooknameFound';
	private const DYNAMIC_HOOK_CODE = self::PREFIX_CODE . 'DynamicHooknameFound';
	private const VARIABLE_CODE = self::PREFIX_CODE . 'NonPrefixedVariableFound';

	private const METHOD_SCOPE_CONTROLLER = 'includes/cpt/event-plans.php';
	private const METHOD_SCOPE_CLASS = 'BVMGR_Admin_Event_Plans';
	private const METHOD_SCOPE_METHOD = 'render_event_plan_partial';
	private const METHOD_SCOPE_FILES = array(
		'includes/cpt/event-plans/partials/advanced-controls.php',
		'includes/cpt/event-plans/partials/comp-ack.php',
		'includes/cpt/event-plans/partials/compensation.php',
		'includes/cpt/event-plans/partials/legacy-imported-ticketing-integration.php',
		'includes/cpt/event-plans/partials/readiness-details.php',
		'includes/cpt/event-plans/partials/secondary-vendors.php',
		'includes/cpt/event-plans/partials/staff.php',
		'includes/cpt/event-plans/partials/ticketing-v2.php',
		'includes/cpt/event-plans/partials/time-lineup.php',
		'includes/cpt/event-plans/partials/workflow-status.php',
	);

	private const EXTERNAL_HOOK_OWNERS = array(
		'event_ticket_woo_attendee_created' => 'The Events Calendar / Event Tickets compatibility contract',
		'event_tickets_woocommerce_ticket_created' => 'The Events Calendar / Event Tickets compatibility contract',
		'event_tickets_woocommerce_tickets_generated' => 'The Events Calendar / Event Tickets compatibility contract',
		'event_tickets_woocommerce_tickets_generated_for_product' => 'The Events Calendar / Event Tickets compatibility contract',
		'the_content' => 'WordPress core hook contract',
		'wootickets_attendee_insert_args' => 'The Events Calendar / legacy WooTickets compatibility contract',
	);

	private const DYNAMIC_B7_CONTRACTS = array(
		'includes/modules/staff-tasks/notifications.php|$event' => 'vms_tasks_emit_notification_event dynamic event family',
	);

	/**
	 * @return array<string,mixed>
	 */
	public static function loadJsonFile(string $path): array
	{
		$contents = is_file($path) ? file_get_contents($path) : false;
		if (!is_string($contents)) {
			throw new RuntimeException('Unreadable JSON artifact: ' . $path);
		}

		$decoded = json_decode($contents, true);
		if (!is_array($decoded)) {
			throw new RuntimeException('Invalid JSON artifact: ' . $path);
		}

		return $decoded;
	}

	/**
	 * @param array<int,array<string,mixed>> $strictRows
	 * @param array<int,array<string,mixed>> $historicalBaselineRows
	 * @param array<string,mixed>            $manifest
	 * @param array<string,string>           $source
	 * @return array<string,mixed>
	 */
	public static function build(
		string $root,
		array $strictRows,
		array $historicalBaselineRows,
		array $manifest,
		array $source
	): array {
		$root = self::root($root);
		self::validateSemanticManifest($manifest);
		self::validateMethodScopeRuntime($root);

		$strictRows = self::normalizeRows($strictRows);
		$historicalBaselineRows = self::normalizeRows($historicalBaselineRows);
		$historicalCurrent = array_values(array_filter(
			$strictRows,
			static fn(array $row): bool => !str_starts_with((string) $row['code'], self::PREFIX_CODE)
		));
		$prefixRows = array_values(array_filter(
			$strictRows,
			static fn(array $row): bool => str_starts_with((string) $row['code'], self::PREFIX_CODE)
		));

		if ($historicalCurrent !== $historicalBaselineRows) {
			throw new RuntimeException('Historical 125-row residual baseline changed; prefix inventory generation refused.');
		}

		$findings = self::classifyPrefixRows($root, $prefixRows, $manifest);
		$artifact = array(
			'schema_version' => self::SCHEMA_VERSION,
			'artifact' => 'wporg-prefix-scanner-inventory',
			'architecture' => array(
				'semantic_migration_inventory_equals_raw_scanner_inventory' => false,
				'semantic_manifest' => 'docs/wporg-prefix-migration-manifest.json',
				'historical_scan_state' => 'docs/wporg-current-scan-state.json',
				'description' => 'Semantic identifiers, scanner finding sites, and historical non-prefix residuals remain distinct evidence sets.',
			),
			'source' => array(
				'source_commit' => (string) ($source['source_commit'] ?? ''),
				'package_sha256' => (string) ($source['package_sha256'] ?? ''),
				'strict_json_sha256' => (string) ($source['strict_json_sha256'] ?? ''),
				'manifest_sha256' => hash('sha256', self::encode($manifest)),
			),
			'classification_definitions' => self::classificationDefinitions(),
			'historical_residual' => self::historicalEvidence($historicalBaselineRows),
			'authoritative_prefix_findings' => $findings,
			'summary' => self::summary($findings, $historicalBaselineRows),
			'method_scope_contract' => array(
				'controller' => self::METHOD_SCOPE_CONTROLLER,
				'class' => self::METHOD_SCOPE_CLASS,
				'method' => self::METHOD_SCOPE_METHOD,
				'include_executes_in_method_scope' => true,
				'allowed_partial_files' => self::METHOD_SCOPE_FILES,
			),
			'external_hook_owners' => self::EXTERNAL_HOOK_OWNERS,
			'migration_gate' => self::gateSummary($findings, $manifest, array(), array()),
		);

		self::validateArtifact($root, $artifact, $manifest);
		return $artifact;
	}

	/**
	 * Evaluate a later strict scan without rewriting the authoritative B2.5 rows.
	 * Removals are monotonic progress; a new stable finding identity is unexpected.
	 *
	 * @param array<string,mixed>            $inventory
	 * @param array<int,array<string,mixed>> $strictRows
	 * @param array<string,mixed>            $manifest
	 * @return array<string,mixed>
	 */
	public static function gate(string $root, array $inventory, array $strictRows, array $manifest): array
	{
		$root = self::root($root);
		self::validateArtifactStructure($root, $inventory, $manifest);
		$strictRows = self::normalizeRows($strictRows);
		$historicalRows = array_values(array_filter(
			$strictRows,
			static fn(array $row): bool => !str_starts_with((string) $row['code'], self::PREFIX_CODE)
		));
		$prefixRows = array_values(array_filter(
			$strictRows,
			static fn(array $row): bool => str_starts_with((string) $row['code'], self::PREFIX_CODE)
		));

		$expectedHistorical = (array) ($inventory['historical_residual']['rows'] ?? array());
		$historicalMismatch = $historicalRows !== $expectedHistorical;
		$unmapped = array();
		try {
			$currentFindings = self::classifyPrefixRows($root, $prefixRows, $manifest);
		} catch (BVMGR_WPORG_Prefix_Unmapped_Finding_Exception $exception) {
			$currentFindings = array();
			$unmapped[] = $exception->finding();
		}

		$baselineById = self::indexFindings((array) ($inventory['authoritative_prefix_findings'] ?? array()));
		$currentById = self::indexFindings($currentFindings);
		$unexpected = array_values(array_diff(array_keys($currentById), array_keys($baselineById)));
		$removed = array_values(array_diff(array_keys($baselineById), array_keys($currentById)));
		sort($unexpected, SORT_STRING);
		sort($removed, SORT_STRING);

		$gate = self::gateSummary($currentFindings, $manifest, $unexpected, $unmapped);
		$baselineCounts = self::countsBy((array) ($inventory['authoritative_prefix_findings'] ?? array()), 'category');
		$currentCounts = self::countsBy($currentFindings, 'category');
		$categoryIncreases = array();
		foreach ($currentCounts as $category => $count) {
			$baselineCount = (int) ($baselineCounts[$category] ?? 0);
			if ($count > $baselineCount) {
				$categoryIncreases[$category] = array('baseline' => $baselineCount, 'current' => $count);
			}
		}
		$gate['category_increases'] = $categoryIncreases;
		$gate['historical_residual_exact'] = !$historicalMismatch;
		$gate['removed_authoritative_findings'] = count($removed);
		$gate['status'] = (
			!$historicalMismatch
			&& $unexpected === array()
			&& $unmapped === array()
			&& ($gate['completed_batch_residuals'] ?? array()) === array()
			&& ($gate['category_increases'] ?? array()) === array()
		) ? 'PASS' : 'FAIL';

		return $gate;
	}

	/**
	 * @param array<string,mixed> $artifact
	 * @param array<string,mixed> $manifest
	 */
	public static function validateArtifact(string $root, array $artifact, array $manifest): void
	{
		$root = self::root($root);
		self::validateArtifactStructure($root, $artifact, $manifest);
		if (($artifact['source']['manifest_sha256'] ?? '') !== hash('sha256', self::encode($manifest))) {
			throw new RuntimeException('Prefix scanner inventory was not generated from the current semantic manifest.');
		}
		$findings = (array) ($artifact['authoritative_prefix_findings'] ?? array());
		self::validateFunctionFindings($findings, $manifest);
		self::validateHookFindingCount($findings, $manifest);
		self::validateMigrationGate($artifact, $manifest);
	}

	/**
	 * Structural validation remains valid after later batches remove findings
	 * and update the semantic manifest.
	 *
	 * @param array<string,mixed> $artifact
	 * @param array<string,mixed> $manifest
	 */
	private static function validateArtifactStructure(string $root, array $artifact, array $manifest): void
	{
		if (($artifact['schema_version'] ?? null) !== self::SCHEMA_VERSION) {
			throw new RuntimeException('Unsupported prefix scanner inventory schema.');
		}
		if (($artifact['artifact'] ?? '') !== 'wporg-prefix-scanner-inventory') {
			throw new RuntimeException('Unexpected prefix scanner artifact type.');
		}

		self::validateSemanticManifest($manifest);
		self::validateMethodScopeRuntime($root);
		$historical = (array) ($artifact['historical_residual']['rows'] ?? array());
		$findings = (array) ($artifact['authoritative_prefix_findings'] ?? array());
		if ($historical !== self::normalizeRows($historical)) {
			throw new RuntimeException('Historical residual rows are not deterministically normalized.');
		}
		if (($artifact['historical_residual'] ?? array()) !== self::historicalEvidence($historical)) {
			throw new RuntimeException('Historical residual evidence summary is stale.');
		}
		if (($artifact['summary'] ?? array()) !== self::summary($findings, $historical)) {
			throw new RuntimeException('Prefix scanner inventory summary is stale.');
		}

		$indexed = self::indexFindings($findings);
		if (count($indexed) !== count($findings)) {
			throw new RuntimeException('Prefix scanner finding IDs must be unique.');
		}
		self::validateMethodFindings($findings);
		self::validateExternalFindings($findings);
	}

	/**
	 * @param array<string,mixed> $artifact
	 */
	public static function render(array $artifact): string
	{
		return self::encode($artifact);
	}

	/**
	 * @param array<int,array<string,mixed>> $rows
	 * @param array<string,mixed>            $manifest
	 * @return array<int,array<string,mixed>>
	 */
	private static function classifyPrefixRows(string $root, array $rows, array $manifest): array
	{
		$functionSites = array();
		foreach ((array) ($manifest['symbols']['functions'] ?? array()) as $function) {
			$identifier = (string) ($function['current_identifier'] ?? '');
			foreach ((array) ($function['declaration_sites'] ?? array()) as $site) {
				$key = $identifier . '|' . (string) ($site['file'] ?? '') . '|' . (int) ($site['line'] ?? 0);
				$functionSites[$key] = true;
			}
		}

		$prepared = array();
		foreach ($rows as $row) {
			$identifier = self::findingIdentifier($row);
			$prepared[] = $row + array('identifier' => $identifier);
		}
		usort($prepared, static function (array $a, array $b): int {
			return array(
				$a['code'], $a['file'], $a['identifier'], $a['line'], $a['column'], $a['type'], $a['message'], $a['docs'],
			) <=> array(
				$b['code'], $b['file'], $b['identifier'], $b['line'], $b['column'], $b['type'], $b['message'], $b['docs'],
			);
		});

		$occurrences = array();
		$findings = array();
		foreach ($prepared as $row) {
			$code = (string) $row['code'];
			$file = (string) $row['file'];
			$line = (int) $row['line'];
			$identifier = (string) $row['identifier'];
			$occurrenceGroup = $code . '|' . $file . '|' . $identifier;
			$occurrences[$occurrenceGroup] = ($occurrences[$occurrenceGroup] ?? 0) + 1;
			$occurrence = $occurrences[$occurrenceGroup];
			$classification = null;

			if ($code === self::FUNCTION_CODE) {
				$siteKey = $identifier . '|' . $file . '|' . $line;
				if (!isset($functionSites[$siteKey])) {
					throw self::unmapped($row, 'Function scanner row does not match the semantic manifest declaration site.');
				}
				$classification = array(
					'category' => self::CATEGORY_B3,
					'semantic_identifier' => 'functions:' . $identifier,
					'owning_batch' => 'B3',
					'disposition' => 'EXPECTED_REMAINING_PREFIX_MIGRATION_FINDING',
					'evidence' => 'Exact identifier/file/line join to docs/wporg-prefix-migration-manifest.json.',
				);
			} elseif ($code === self::HOOK_CODE && isset(self::EXTERNAL_HOOK_OWNERS[$identifier])) {
				$classification = array(
					'category' => self::CATEGORY_EXTERNAL,
					'semantic_identifier' => 'external-hook:' . $identifier,
					'owning_batch' => null,
					'disposition' => 'APPROVED_SCANNER_EXCEPTION',
					'evidence' => self::EXTERNAL_HOOK_OWNERS[$identifier],
				);
			} elseif ($code === self::HOOK_CODE && str_starts_with($identifier, 'vms_')) {
				$classification = array(
					'category' => self::CATEGORY_B7,
					'semantic_identifier' => 'hooks:' . $identifier,
					'owning_batch' => 'B7',
					'disposition' => 'EXPECTED_REMAINING_PREFIX_MIGRATION_FINDING',
					'evidence' => 'Plugin-owned literal vms_* hook; canonical-first dual-fire belongs to B7.',
				);
			} elseif ($code === self::DYNAMIC_HOOK_CODE) {
				$dynamicKey = $file . '|' . $identifier;
				if (!isset(self::DYNAMIC_B7_CONTRACTS[$dynamicKey])) {
					throw self::unmapped($row, 'Unreviewed dynamic hook scanner row.');
				}
				$classification = array(
					'category' => self::CATEGORY_B7,
					'semantic_identifier' => 'dynamic-hook:' . self::DYNAMIC_B7_CONTRACTS[$dynamicKey],
					'owning_batch' => 'B7',
					'disposition' => 'EXPECTED_REMAINING_PREFIX_MIGRATION_FINDING',
					'evidence' => 'Exact reviewed dynamic custom-hook family.',
				);
			} elseif ($code === self::VARIABLE_CODE && in_array($file, self::METHOD_SCOPE_FILES, true)) {
				$classification = array(
					'category' => self::CATEGORY_METHOD_SCOPE,
					'semantic_identifier' => 'method-local:' . self::METHOD_SCOPE_CLASS . '::' . self::METHOD_SCOPE_METHOD . ':' . $identifier,
					'owning_batch' => null,
					'disposition' => 'APPROVED_SCANNER_EXCEPTION',
					'evidence' => 'Partial executes through include inside the private render_event_plan_partial() method.',
				);
			} else {
				throw self::unmapped($row, 'Unexpected PrefixAllGlobals row; possible genuine plugin-owned global or unreviewed contract.');
			}

			$identity = $code . "\0" . $file . "\0" . $identifier . "\0" . $occurrence;
			$findings[] = array(
				'finding_id' => hash('sha256', $identity),
				'code' => $code,
				'file' => $file,
				'line' => $line,
				'column' => (int) $row['column'],
				'type' => (string) $row['type'],
				'identifier' => $identifier,
				'occurrence' => $occurrence,
				'message' => (string) $row['message'],
				'docs' => (string) $row['docs'],
			) + $classification;
		}

		usort($findings, static fn(array $a, array $b): int => strcmp((string) $a['finding_id'], (string) $b['finding_id']));
		return $findings;
	}

	/**
	 * @param array<string,mixed> $row
	 */
	private static function findingIdentifier(array $row): string
	{
		$message = (string) ($row['message'] ?? '');
		if (!preg_match('/Found: &quot;(?<identifier>[^&]+)&quot;\.?/', $message, $matches)) {
			throw self::unmapped($row, 'Unable to extract exact identifier from Plugin Check message.');
		}
		return html_entity_decode((string) $matches['identifier'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
	}

	/**
	 * @param array<int,array<string,mixed>> $rows
	 * @return array<int,array<string,mixed>>
	 */
	private static function normalizeRows(array $rows): array
	{
		$normalized = array();
		foreach ($rows as $row) {
			if (!is_array($row)) {
				throw new RuntimeException('Plugin Check rows must be JSON objects.');
			}
			$file = str_replace('\\', '/', (string) ($row['file'] ?? ''));
			$packageMarker = '/backstage-venue-manager/';
			if (str_contains($file, $packageMarker)) {
				$file = substr($file, (int) strrpos($file, $packageMarker) + strlen($packageMarker));
			}
			$file = ltrim($file, '/');
			$normalized[] = array(
				'file' => $file,
				'line' => (int) ($row['line'] ?? 0),
				'column' => (int) ($row['column'] ?? 0),
				'type' => (string) ($row['type'] ?? ''),
				'code' => (string) ($row['code'] ?? ''),
				'message' => (string) ($row['message'] ?? ''),
				'docs' => (string) ($row['docs'] ?? ''),
			);
		}
		usort($normalized, static function (array $a, array $b): int {
			return array_values($a) <=> array_values($b);
		});
		return $normalized;
	}

	/**
	 * @param array<int,array<string,mixed>> $rows
	 * @return array<string,mixed>
	 */
	private static function historicalEvidence(array $rows): array
	{
		return array(
			'count' => count($rows),
			'rows_sha256' => hash('sha256', self::encode($rows)),
			'code_counts' => self::countsBy($rows, 'code'),
			'rows' => $rows,
		);
	}

	/**
	 * @param array<int,array<string,mixed>> $findings
	 * @param array<int,array<string,mixed>> $historicalRows
	 * @return array<string,mixed>
	 */
	private static function summary(array $findings, array $historicalRows): array
	{
		$categories = self::countsBy($findings, 'category');
		$migration = ($categories[self::CATEGORY_B3] ?? 0) + ($categories[self::CATEGORY_B7] ?? 0);
		$exceptions = ($categories[self::CATEGORY_METHOD_SCOPE] ?? 0) + ($categories[self::CATEGORY_EXTERNAL] ?? 0);
		return array(
			'historical_residual_findings' => count($historicalRows),
			'prefix_findings' => count($findings),
			'total_findings' => count($historicalRows) + count($findings),
			'prefix_code_counts' => self::countsBy($findings, 'code'),
			'category_counts' => $categories,
			'expected_remaining_prefix_migration_findings' => $migration,
			'approved_scanner_exceptions' => $exceptions,
			'unexpected' => 0,
			'unmapped' => 0,
		);
	}

	/**
	 * @param array<int,array<string,mixed>> $findings
	 * @param array<string,mixed>            $manifest
	 * @param array<int,string>              $unexpected
	 * @param array<int,array<string,mixed>> $unmapped
	 * @return array<string,mixed>
	 */
	private static function gateSummary(array $findings, array $manifest, array $unexpected, array $unmapped): array
	{
		$counts = self::countsBy($findings, 'category');
		$completed = array();
		foreach ((array) ($manifest['completed_batches'] ?? array()) as $batch => $state) {
			if (($state['status'] ?? '') === 'complete') {
				$completed[] = (string) $batch;
			}
		}
		$completedResiduals = array();
		foreach ($findings as $finding) {
			$batch = (string) ($finding['owning_batch'] ?? '');
			if ($batch !== '' && in_array($batch, $completed, true)) {
				$completedResiduals[] = (string) $finding['finding_id'];
			}
		}

		return array(
			'status' => ($unexpected === array() && $unmapped === array() && $completedResiduals === array()) ? 'PASS' : 'FAIL',
			'completed_batches' => $completed,
			'current_category_counts' => $counts,
			'expected_remaining_prefix_migration_findings' => ($counts[self::CATEGORY_B3] ?? 0) + ($counts[self::CATEGORY_B7] ?? 0),
			'approved_scanner_exceptions' => ($counts[self::CATEGORY_METHOD_SCOPE] ?? 0) + ($counts[self::CATEGORY_EXTERNAL] ?? 0),
			'unexpected' => count($unexpected),
			'unexpected_finding_ids' => $unexpected,
			'unmapped' => count($unmapped),
			'unmapped_findings' => $unmapped,
			'completed_batch_residuals' => $completedResiduals,
			'category_increases' => array(),
		);
	}

	/**
	 * @param array<int,array<string,mixed>> $rows
	 * @return array<string,int>
	 */
	private static function countsBy(array $rows, string $field): array
	{
		$counts = array();
		foreach ($rows as $row) {
			$value = (string) ($row[$field] ?? '');
			$counts[$value] = ($counts[$value] ?? 0) + 1;
		}
		ksort($counts, SORT_STRING);
		return $counts;
	}

	/**
	 * @param array<int,array<string,mixed>> $findings
	 * @return array<string,array<string,mixed>>
	 */
	private static function indexFindings(array $findings): array
	{
		$indexed = array();
		foreach ($findings as $finding) {
			$indexed[(string) ($finding['finding_id'] ?? '')] = $finding;
		}
		ksort($indexed, SORT_STRING);
		return $indexed;
	}

	/**
	 * @param array<int,array<string,mixed>> $findings
	 * @param array<string,mixed>            $manifest
	 */
	private static function validateFunctionFindings(array $findings, array $manifest): void
	{
		$sites = array();
		foreach ((array) ($manifest['symbols']['functions'] ?? array()) as $function) {
			foreach ((array) ($function['declaration_sites'] ?? array()) as $site) {
				$sites[] = array(
					'identifier' => (string) ($function['current_identifier'] ?? ''),
					'file' => (string) ($site['file'] ?? ''),
					'line' => (int) ($site['line'] ?? 0),
				);
			}
		}
		$scannerSites = array();
		foreach ($findings as $finding) {
			if (($finding['category'] ?? '') !== self::CATEGORY_B3) {
				continue;
			}
			$scannerSites[] = array(
				'identifier' => (string) ($finding['identifier'] ?? ''),
				'file' => (string) ($finding['file'] ?? ''),
				'line' => (int) ($finding['line'] ?? 0),
			);
		}
		self::sortSemanticSites($sites);
		self::sortSemanticSites($scannerSites);
		if ($sites !== $scannerSites) {
			throw new RuntimeException('B3 scanner rows must exactly equal all semantic function declaration sites.');
		}
	}

	/**
	 * @param array<int,array<string,mixed>> $findings
	 */
	private static function validateMethodFindings(array $findings): void
	{
		foreach ($findings as $finding) {
			if (($finding['category'] ?? '') !== self::CATEGORY_METHOD_SCOPE) {
				continue;
			}
			if (!in_array((string) ($finding['file'] ?? ''), self::METHOD_SCOPE_FILES, true)) {
				throw new RuntimeException('Method-scope scanner exception escaped its exact partial-file boundary.');
			}
		}
	}

	/**
	 * @param array<int,array<string,mixed>> $findings
	 */
	private static function validateExternalFindings(array $findings): void
	{
		$identifiers = array();
		foreach ($findings as $finding) {
			if (($finding['category'] ?? '') === self::CATEGORY_EXTERNAL) {
				$identifiers[] = (string) ($finding['identifier'] ?? '');
			}
		}
		sort($identifiers, SORT_STRING);
		$expected = array_keys(self::EXTERNAL_HOOK_OWNERS);
		sort($expected, SORT_STRING);
		if ($identifiers !== $expected) {
			throw new RuntimeException('Third-party/core hook inventory must remain the exact reviewed six contracts.');
		}
	}

	/**
	 * @param array<int,array<string,mixed>> $findings
	 * @param array<string,mixed>            $manifest
	 */
	private static function validateHookFindingCount(array $findings, array $manifest): void
	{
		$hookCategory = array_values(array_filter(
			(array) ($manifest['categories'] ?? array()),
			static fn(array $category): bool => ($category['id'] ?? '') === 'hooks'
		));
		$semanticHookSites = (int) ($hookCategory[0]['semantic_inventory']['total'] ?? -1);
		$scannerHookSites = count(array_filter(
			$findings,
			static fn(array $finding): bool => ($finding['category'] ?? '') === self::CATEGORY_B7
		));
		if ($semanticHookSites < 0 || $scannerHookSites !== $semanticHookSites) {
			throw new RuntimeException('B7 scanner rows must exactly equal the semantic hook-site inventory.');
		}
	}

	/**
	 * @param array<string,mixed> $artifact
	 * @param array<string,mixed> $manifest
	 */
	private static function validateMigrationGate(array $artifact, array $manifest): void
	{
		$expected = self::gateSummary(
			(array) ($artifact['authoritative_prefix_findings'] ?? array()),
			$manifest,
			array(),
			array()
		);
		if (($artifact['migration_gate'] ?? array()) !== $expected) {
			throw new RuntimeException('Migration-aware scanner gate summary is stale.');
		}
	}

	/**
	 * @param array<string,mixed> $manifest
	 */
	private static function validateSemanticManifest(array $manifest): void
	{
		$b2 = (array) ($manifest['completed_batches']['B2'] ?? array());
		if (($b2['status'] ?? '') !== 'complete') {
			throw new RuntimeException('Scanner inventory requires the completed B2 semantic checkpoint.');
		}

		$b25 = (array) ($manifest['completed_batches']['B2_5'] ?? array());
		if (($b25['status'] ?? '') !== 'complete'
			|| ($b25['unique_symbols']['global_slots'] ?? null) !== 41
			|| ($b25['declaration_sites']['global_slots'] ?? null) !== 194
			|| ($b25['scanner_rows_eliminated'] ?? null) !== 57
			|| ($b25['scanner_missed_semantic_slots'] ?? null) !== 1
			|| count((array) ($b25['symbol_map'] ?? array())) !== 41
		) {
			throw new RuntimeException('Completed B2.5 semantic map does not match its exact coordinator-owned contract.');
		}
		foreach ((array) $b25['symbol_map'] as $entry) {
			$legacy = (string) ($entry['legacy_identifier'] ?? '');
			if (($entry['kind'] ?? '') !== 'global_slots'
				|| (!str_starts_with($legacy, 'template:') && !str_starts_with($legacy, 'loader:'))
				|| str_contains($legacy, 'vms_')
			) {
				throw new RuntimeException('B2.5 symbol map must use explicit template:/loader: old identifiers without invented vms_* names.');
			}
		}

		$ledger = (array) ($manifest['complete_semantic_ledger'] ?? array());
		$expectedHistorical = array('pre_B2' => 4696, 'post_B2' => 4521, 'reduction' => 175, 'immutable' => true);
		$expectedCorrected = array('pre_B2' => 4737, 'post_B2' => 4562, 'post_B2_5' => 4521);
		$expectedOmitted = array('global_slots' => 41, 'token_sites' => 194, 'plugin_check_rows' => 57);
		if (trim((string) ($ledger['measurement'] ?? '')) === ''
			|| ($ledger['historical_original_ratchet'] ?? array()) !== $expectedHistorical
			|| ($ledger['corrected_complete_counts'] ?? array()) !== $expectedCorrected
			|| ($ledger['originally_omitted'] ?? array()) !== $expectedOmitted
		) {
			throw new RuntimeException('Complete semantic ledger does not preserve the historical ratchet and corrected counts.');
		}
	}

	private static function validateMethodScopeRuntime(string $root): void
	{
		foreach (self::METHOD_SCOPE_FILES as $file) {
			if (!is_file($root . '/' . $file)) {
				throw new RuntimeException('Missing reviewed Event Plan partial: ' . $file);
			}
		}

		$controllerPath = $root . '/' . self::METHOD_SCOPE_CONTROLLER;
		$source = is_file($controllerPath) ? file_get_contents($controllerPath) : false;
		if (!is_string($source)) {
			throw new RuntimeException('Unreadable Event Plan partial controller.');
		}
		if (!self::methodContainsInclude($source, self::METHOD_SCOPE_CLASS, self::METHOD_SCOPE_METHOD)) {
			throw new RuntimeException('Event Plan partial include is no longer provably inside the reviewed private method.');
		}
	}

	private static function methodContainsInclude(string $source, string $className, string $methodName): bool
	{
		$tokens = token_get_all($source);
		$brace = 0;
		$pendingClass = null;
		$pendingFunction = null;
		$classStack = array();
		$functionStack = array();
		foreach ($tokens as $index => $token) {
			if (is_array($token)) {
				$id = $token[0];
				if ($id === T_CLASS) {
					$pendingClass = self::nextTokenName($tokens, $index);
				} elseif ($id === T_FUNCTION) {
					$pendingFunction = self::nextTokenName($tokens, $index);
				} elseif (in_array($id, array(T_INCLUDE, T_INCLUDE_ONCE, T_REQUIRE, T_REQUIRE_ONCE), true)) {
					$currentClass = $classStack === array() ? null : end($classStack)['name'];
					$currentFunction = $functionStack === array() ? null : end($functionStack)['name'];
					if ($currentClass === $className && $currentFunction === $methodName) {
						return true;
					}
				}
				continue;
			}
			if ($token === '{') {
				$brace++;
				if ($pendingClass !== null) {
					$classStack[] = array('name' => $pendingClass, 'depth' => $brace);
					$pendingClass = null;
				}
				if ($pendingFunction !== null) {
					$functionStack[] = array('name' => $pendingFunction, 'depth' => $brace);
					$pendingFunction = null;
				}
			} elseif ($token === '}') {
				if ($functionStack !== array() && end($functionStack)['depth'] === $brace) {
					array_pop($functionStack);
				}
				if ($classStack !== array() && end($classStack)['depth'] === $brace) {
					array_pop($classStack);
				}
				$brace--;
			} elseif ($token === ';') {
				$pendingFunction = null;
			}
		}
		return false;
	}

	/**
	 * @param array<int,array|string> $tokens
	 */
	private static function nextTokenName(array $tokens, int $index): ?string
	{
		for ($cursor = $index + 1, $count = count($tokens); $cursor < $count; $cursor++) {
			$token = $tokens[$cursor];
			if (is_array($token) && in_array($token[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
				continue;
			}
			return is_array($token) && $token[0] === T_STRING ? $token[1] : null;
		}
		return null;
	}

	/**
	 * @param array<int,array<string,mixed>> $sites
	 */
	private static function sortSemanticSites(array &$sites): void
	{
		usort($sites, static fn(array $a, array $b): int => array($a['identifier'], $a['file'], $a['line']) <=> array($b['identifier'], $b['file'], $b['line']));
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	private static function classificationDefinitions(): array
	{
		return array(
			self::CATEGORY_B3 => array('owning_batch' => 'B3', 'submission_blocker_until_batch' => true),
			self::CATEGORY_B7 => array('owning_batch' => 'B7', 'submission_blocker_until_batch' => true),
			self::CATEGORY_METHOD_SCOPE => array('owning_batch' => null, 'submission_blocker_until_batch' => false),
			self::CATEGORY_EXTERNAL => array('owning_batch' => null, 'submission_blocker_until_batch' => false),
		);
	}

	/**
	 * @param array<string,mixed> $row
	 */
	private static function unmapped(array $row, string $reason): BVMGR_WPORG_Prefix_Unmapped_Finding_Exception
	{
		return new BVMGR_WPORG_Prefix_Unmapped_Finding_Exception($reason, $row);
	}

	private static function root(string $root): string
	{
		$real = realpath($root);
		if ($real === false || !is_dir($real)) {
			throw new RuntimeException('Unreadable plugin root: ' . $root);
		}
		return rtrim(str_replace('\\', '/', $real), '/');
	}

	/**
	 * @param mixed $value
	 */
	private static function encode($value): string
	{
		$json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		if (!is_string($json)) {
			throw new RuntimeException('Unable to encode prefix scanner inventory JSON.');
		}
		return $json . PHP_EOL;
	}
}

final class BVMGR_WPORG_Prefix_Unmapped_Finding_Exception extends RuntimeException
{
	/** @var array<string,mixed> */
	private array $scannerFinding;

	/**
	 * @param array<string,mixed> $scannerFinding
	 */
	public function __construct(string $message, array $scannerFinding)
	{
		parent::__construct($message);
		$this->scannerFinding = $scannerFinding;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function finding(): array
	{
		return $this->scannerFinding;
	}
}
