<?php
declare(strict_types=1);

require_once __DIR__ . '/wporg-prefix-inventory.php';

/**
 * Deterministic semantic inventory for the Phase B4 browser/nonpersistent
 * contract migration. This library is release-excluded and performs no writes.
 */
final class BVMGR_WPORG_Prefix_B4
{
	public const MAP_PATH = 'docs/wporg-prefix-b4-identifier-map.json';
	public const RETIREMENT_PATH = 'docs/wporg-prefix-b4-compatibility-retirement.json';

	private const ASSET_CALLS = array(
		'bvmgr_enqueue_style_asset' => array('kind' => 'style', 'role' => 'source', 'handle_arg' => 0, 'deps_arg' => 2),
		'wp_add_inline_script' => array('kind' => 'script', 'role' => 'consumer', 'handle_arg' => 0),
		'wp_add_inline_style' => array('kind' => 'style', 'role' => 'consumer', 'handle_arg' => 0),
		'wp_dequeue_script' => array('kind' => 'script', 'role' => 'consumer', 'handle_arg' => 0),
		'wp_dequeue_style' => array('kind' => 'style', 'role' => 'consumer', 'handle_arg' => 0),
		'wp_deregister_script' => array('kind' => 'script', 'role' => 'consumer', 'handle_arg' => 0),
		'wp_deregister_style' => array('kind' => 'style', 'role' => 'consumer', 'handle_arg' => 0),
		'wp_enqueue_script' => array('kind' => 'script', 'role' => 'source', 'handle_arg' => 0, 'deps_arg' => 2),
		'wp_enqueue_style' => array('kind' => 'style', 'role' => 'source', 'handle_arg' => 0, 'deps_arg' => 2),
		'wp_localize_script' => array('kind' => 'script', 'role' => 'consumer', 'handle_arg' => 0),
		'wp_print_scripts' => array('kind' => 'script', 'role' => 'consumer', 'handle_arg' => 0),
		'wp_print_styles' => array('kind' => 'style', 'role' => 'consumer', 'handle_arg' => 0),
		'wp_register_script' => array('kind' => 'script', 'role' => 'source', 'handle_arg' => 0, 'deps_arg' => 2),
		'wp_register_style' => array('kind' => 'style', 'role' => 'source', 'handle_arg' => 0, 'deps_arg' => 2),
		'wp_script_add_data' => array('kind' => 'script', 'role' => 'consumer', 'handle_arg' => 0),
		'wp_script_is' => array('kind' => 'script', 'role' => 'consumer', 'handle_arg' => 0),
		'wp_set_script_translations' => array('kind' => 'script', 'role' => 'consumer', 'handle_arg' => 0),
		'wp_style_add_data' => array('kind' => 'style', 'role' => 'consumer', 'handle_arg' => 0),
		'wp_style_is' => array('kind' => 'style', 'role' => 'consumer', 'handle_arg' => 0),
	);

	private const NONCE_CALLS = array(
		'check_admin_referer' => array('role' => 'verifier', 'action_arg' => 0, 'field_arg' => 1),
		'check_ajax_referer' => array('role' => 'verifier', 'action_arg' => 0, 'field_arg' => 1),
		'wp_create_nonce' => array('role' => 'producer', 'action_arg' => 0),
		'wp_nonce_field' => array('role' => 'producer', 'action_arg' => 0, 'field_arg' => 1),
		'wp_nonce_url' => array('role' => 'producer', 'action_arg' => 1),
		'wp_verify_nonce' => array('role' => 'verifier', 'action_arg' => 1),
	);

	private const QUERY_CALLS = array(
		'add_rewrite_rule',
		'add_rewrite_tag',
		'get_query_var',
		'set_query_var',
	);

	private const BROWSER_CANONICAL = array(
		'VMS_DASH' => 'BVMGR_DASH',
		'VMS_TICKETING' => 'BVMGR_TICKETING',
		'VMS_TOURS' => 'BVMGR_TOURS',
		'VMS_TOURS_PAYLOAD' => 'BVMGR_TOURS_PAYLOAD',
		'VMS_Tour' => 'BVMGR_TOUR',
		'VMSPortalCalendarModalOpen' => 'BVMGR_PORTAL_CALENDAR_MODAL_OPEN',
		'VmsImageNormalize' => 'BVMGR_IMAGE_NORMALIZE',
		'__vmsMonthAccordionInit' => 'BVMGR_MONTH_ACCORDION_INIT',
		'__vmsMyTicketsNoticeObserver' => 'BVMGR_MY_TICKETS_NOTICE_OBSERVER',
		'__vmsNoticeLoadBound' => 'BVMGR_NOTICE_LOAD_BOUND',
		'__vmsTicketingFrontBundle' => 'BVMGR_TICKETING_FRONT_BUNDLE',
		'__vmsTopNavQuickMenuGlobalBound' => 'BVMGR_TOP_NAV_QUICK_MENU_GLOBAL_BOUND',
		'vmsAdminMenu' => 'BVMGR_ADMIN_MENU',
		'vmsAdmissionsAdmin' => 'BVMGR_ADMISSIONS_ADMIN',
		'vmsCompPackageAdmin' => 'BVMGR_COMP_PACKAGE_ADMIN',
		'vmsDoorCheckin' => 'BVMGR_DOOR_CHECKIN',
		'vmsEventPlanInitCollapsibleSection' => 'BVMGR_EVENT_PLAN_INIT_COLLAPSIBLE_SECTION',
		'vmsEventPlanInitCollapsibleSections' => 'BVMGR_EVENT_PLAN_INIT_COLLAPSIBLE_SECTIONS',
		'vmsEventPlanInitSecondaryVendors' => 'BVMGR_EVENT_PLAN_INIT_SECONDARY_VENDORS',
		'vmsEventPlanInitStaff' => 'BVMGR_EVENT_PLAN_INIT_STAFF',
		'vmsEventPlanPersistRequestedSection' => 'BVMGR_EVENT_PLAN_PERSIST_REQUESTED_SECTION',
		'vmsEventPlanRevealRequestedSection' => 'BVMGR_EVENT_PLAN_REVEAL_REQUESTED_SECTION',
		'vmsStaffCptAdmin' => 'BVMGR_STAFF_CPT_ADMIN',
		'vmsStatusNoticesAdmin' => 'BVMGR_STATUS_NOTICES_ADMIN',
		'vmsStatusNoticesData' => 'BVMGR_STATUS_NOTICES_DATA',
		'vmsTicketIntegrityAdmin' => 'BVMGR_TICKET_INTEGRITY_ADMIN',
		'vmsTicketingFront' => 'BVMGR_TICKETING_FRONT',
		'vmsVendorDefaultsAdmin' => 'BVMGR_VENDOR_DEFAULTS_ADMIN',
		'vmsVerificationUpload' => 'BVMGR_VERIFICATION_UPLOAD',
	);

	/**
	 * Exact custom nonce request fields proven from producer/read contexts.
	 * Two historical heavy-admin spellings intentionally converge on the same
	 * canonical field and remain separate legacy inbound rows.
	 */
	private const NONCE_FIELDS = array(
		'_vms_admin_heavy_nonce',
		'_vms_cb_nonce',
		'_vms_cc_promo_nonce',
		'_vms_ep_list_nonce',
		'_vms_headliner_promo_video_nonce',
		'_vms_headliner_promo_video_remove_nonce',
		'_vms_pass_claim_nonce',
		'_vms_resync_calendar_nonce',
		'_vms_vendor_app_resend_nonce',
		'_vms_vendor_guest_nonce',
		'_vms_vendor_interest_nonce',
		'_vms_vendor_link_request_nonce',
		'_vms_vendor_withdraw_nonce',
		'vms_add_dispatch_assignment_nonce',
		'vms_add_dispatch_nonce',
		'vms_admin_heavy_nonce',
		'vms_avail_nonce',
		'vms_comp_package_nonce',
		'vms_create_venue_from_template_nonce',
		'vms_current_venue_nonce',
		'vms_dash_venue_nonce',
		'vms_employee_packet_nonce',
		'vms_event_credit_nonce',
		'vms_event_plan_details_nonce',
		'vms_express_bar_nonce',
		'vms_express_bar_order_nonce',
		'vms_feedback_delete_nonce',
		'vms_feedback_nonce',
		'vms_feedback_settings_nonce',
		'vms_goals_event_finance_nonce',
		'vms_holidays_nonce',
		'vms_ics_nonce',
		'vms_pattern_nonce',
		'vms_preview_nonce',
		'vms_rating_details_nonce',
		'vms_rating_nonce',
		'vms_season_dates_nonce',
		'vms_social_event_panel_nonce',
		'vms_staff_avail_nonce',
		'vms_staff_certification_nonce',
		'vms_staff_employee_packet_nonce',
		'vms_staff_ics_nonce',
		'vms_staff_pattern_nonce',
		'vms_staff_qualifications_nonce',
		'vms_staff_tax_nonce',
		'vms_staff_user_link_nonce',
		'vms_staff_vendor_link_nonce',
		'vms_staff_worker_type_nonce',
		'vms_tax_admin_nonce',
		'vms_tax_bypass_nonce',
		'vms_techdocs_nonce',
		'vms_vendor_app_decision_nonce',
		'vms_vendor_apply_nonce',
		'vms_vendor_booking_onboarding_nonce',
		'vms_vendor_command_center_link_nonce',
		'vms_vendor_command_center_nonce',
		'vms_vendor_command_center_template_nonce',
		'vms_vendor_defaults_nonce',
		'vms_vendor_details_nonce',
		'vms_vendor_profile_nonce',
		'vms_vendor_public_profile_nonce',
		'vms_vendor_staff_link_nonce',
		'vms_vendor_tax_nonce',
		'vms_vendor_user_links_nonce',
		'vms_venue_comp_defaults_nonce',
		'vms_venue_default_times_nonce',
		'vms_venue_location_nonce',
		'vms_venue_schedule_nonce',
		'vms_venue_template_nonce',
		'vms_verification_allowances_nonce',
		'vms_verification_nonce',
		'vms_verification_programs_nonce',
		'vms_verification_upload_settings_nonce',
	);

	private const QUERY_VARS = array(
		'vms_add_dispatch_token',
		'vms_admission_scan_token',
		'vms_calendar_end',
		'vms_calendar_ics',
		'vms_calendar_start',
		'vms_calendar_vendor_id',
		'vms_calendar_venue',
		'vms_doc_module',
		'vms_doc_slug',
		'vms_event_feedback',
		'vms_feedback_submitted',
		'vms_pass_claim_token',
		'vms_vendor_app_confirm',
		'vms_vendor_profile',
	);

	public static function build(string $root): array
	{
		$root = rtrim((string) realpath($root), '/');
		if ($root === '' || !is_file($root . '/docs/wporg-prefix-migration-manifest.json')) {
			throw new RuntimeException('Invalid B4 repository root.');
		}

		$manifestPath = $root . '/docs/wporg-prefix-migration-manifest.json';
		$manifest = self::loadJson($manifestPath);
		$publicPhp = (array) (BVMGR_WPORG_Prefix_Inventory::scan($root)['public_php_files'] ?? array());
		$phpCalls = array();
		$callNames = array_values(array_unique(array_merge(
			array_keys(self::ASSET_CALLS),
			array_keys(self::NONCE_CALLS),
			self::QUERY_CALLS,
			array('wp_cli::add_command')
		)));

		foreach ($publicPhp as $file) {
			$phpCalls[$file] = self::calls((string) file_get_contents($root . '/' . $file), $callNames);
		}

		$browser = self::browserGlobals($root, $publicPhp, $phpCalls);
		$handles = self::assetHandles($phpCalls);
		$handleSummary = self::assetSummary($phpCalls, $handles);
		$nonces = self::nonces($root, $publicPhp, $phpCalls);
		$query = self::queryRewrite($root, $publicPhp, $phpCalls);
		$cli = self::cli($phpCalls);

		return array(
			'schema_version' => 1,
			'authority' => array(
				'phase' => 'B4',
				'controlling_manifest' => 'docs/wporg-prefix-migration-manifest.json',
				'authorized_start_manifest_sha256' => '000a59aa5167fba8a823280c62f6c9dcc592a0bf800b98e916ec696fa2c761e3',
				'authorized_start_manifest_note' => 'This is the pre-B4 manifest hash. The manifest is subsequently corrected to reference this frozen artifact, so no circular hash dependency is introduced.',
				'controlling_documents' => array(
					'docs/WPORG_PREFIX_MIGRATION_B0.md',
					'docs/WPORG_PREFIX_MIGRATION_B1.md',
					'docs/WPORG_PREFIX_MIGRATION_B2.md',
					'docs/WPORG_PREFIX_MIGRATION_B2_5.md',
					'docs/WPORG_PREFIX_MIGRATION_B3.md',
				),
				'authorized_starting_head' => 'bdd84df7bcbfcec65ee57fedf561bf4e167761f6',
				'canonical_forms' => array(
					'browser_globals' => 'BVMGR_*',
					'asset_handles' => 'bvmgr-*',
					'nonce_actions' => 'bvmgr_*',
					'nonce_fields' => '_bvmgr_* or bvmgr_* preserving the legacy leading-underscore shape',
					'query_vars' => 'bvmgr_*',
					'cli_root' => 'bvmgr',
				),
			),
			'manifest_scope' => self::manifestScope($manifest),
			'categories' => array(
				'browser_globals' => $browser,
				'asset_handles' => $handles,
				'nonce_actions' => $nonces['actions'],
				'nonce_fields' => $nonces['fields'],
				'query_vars' => $query['query_vars'],
				'rewrite_tags' => $query['rewrite_tags'],
				'rewrite_rules' => $query['rewrite_rules'],
				'cli_paths' => $cli,
			),
			'summary' => array(
				'browser_globals' => count($browser),
				'asset_handles' => count($handles),
				'asset_registration_call_sites' => $handleSummary['registration_call_sites'],
				'asset_resolved_source_sites' => $handleSummary['resolved_source_sites'],
				'asset_dependency_sites' => $handleSummary['dependency_sites'],
				'asset_consumer_sites' => $handleSummary['consumer_sites'],
				'nonce_static_actions' => count(array_filter($nonces['actions'], static fn(array $row): bool => $row['family_kind'] === 'static')),
				'nonce_dynamic_action_families' => count(array_filter($nonces['actions'], static fn(array $row): bool => $row['family_kind'] === 'dynamic')),
				'nonce_fields' => count($nonces['fields']),
				'query_vars' => count($query['query_vars']),
				'rewrite_tags' => count($query['rewrite_tags']),
				'rewrite_rules' => count($query['rewrite_rules']),
				'cli_paths' => count($cli),
			),
			'scope_boundary' => array(
				'completed_here' => array('browser globals', 'asset handles', 'nonce actions and custom fields', 'query vars and rewrites', 'CLI paths'),
				'deferred' => array('B5 persistent settings', 'B6 schedules/storage', 'B7 hooks/REST/AJAX/shortcodes/protocols', 'physical tables', 'stored CPT/taxonomy values', 'historical QR values'),
			),
		);
	}

	public static function retirementMap(array $map): array
	{
		$items = array();
		foreach ((array) ($map['categories']['asset_handles'] ?? array()) as $row) {
			if (empty($row['legacy_inbound_compatibility_required'])) {
				continue;
			}
			$items[] = self::retirementRow($row['legacy_identifier'], $row['canonical_identifier'], 'asset_handle_alias', 'Dependency-only alias prevents duplicate source loading while cached or external dependency graphs migrate.', 'At least one public compatibility release and a fresh known/unknown consumer audit.', 'No supported consumer depends on the legacy handle and one full compatibility release has elapsed.', false);
		}
		foreach ((array) ($map['categories']['nonce_actions'] ?? array()) as $row) {
			$items[] = self::retirementRow($row['legacy_identifier'], $row['canonical_identifier'], 'legacy_nonce_verification', 'Already-rendered or cached forms can contain a still-valid legacy nonce.', 'The maximum WordPress nonce validity window plus the longest known full-page/form cache TTL.', 'That bounded nonce/cache window has elapsed after all canonical producers shipped.', false);
		}
		foreach ((array) ($map['categories']['nonce_fields'] ?? array()) as $row) {
			$items[] = self::retirementRow($row['legacy_identifier'], $row['canonical_identifier'], 'legacy_nonce_field_read', 'Already-rendered or cached forms can submit the legacy request-field name.', 'The maximum WordPress nonce validity window plus the longest known full-page/form cache TTL.', 'That bounded nonce/cache window has elapsed after all canonical field producers shipped.', false);
		}
		foreach ((array) ($map['categories']['query_vars'] ?? array()) as $row) {
			$items[] = self::retirementRow($row['legacy_identifier'], $row['canonical_identifier'], 'legacy_query_inbound', 'Existing bookmarks, cached URLs, emails, and content may contain the legacy inbound query identifier.', 'Indefinite until a content/log/bookmark audit proves the legacy route unused.', 'A separately authorized compatibility review proves no supported inbound legacy URL remains.', true);
		}
		foreach ((array) ($map['categories']['cli_paths'] ?? array()) as $row) {
			$items[] = self::retirementRow($row['legacy_identifier'], $row['canonical_identifier'], 'legacy_cli_alias', 'Operator scripts may still invoke the legacy path.', 'At least one public compatibility release with a documented deprecation notice.', 'Operator automation audit confirms canonical paths and the deprecation window has elapsed.', false);
		}
		usort($items, static fn(array $a, array $b): int => array($a['compatibility_type'], $a['compatibility_identifier']) <=> array($b['compatibility_type'], $b['compatibility_identifier']));
		return array(
			'schema_version' => 1,
			'authority' => self::MAP_PATH,
			'items' => $items,
			'summary' => array(
				'total' => count($items),
				'temporary' => count(array_filter($items, static fn(array $row): bool => !$row['must_survive_indefinitely'])),
				'indefinite' => count(array_filter($items, static fn(array $row): bool => $row['must_survive_indefinitely'])),
			),
		);
	}

	public static function render(array $value): string
	{
		$json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		if (!is_string($json)) {
			throw new RuntimeException('Unable to encode B4 JSON artifact.');
		}
		return $json . PHP_EOL;
	}

	public static function loadJson(string $path): array
	{
		$decoded = json_decode((string) file_get_contents($path), true);
		if (!is_array($decoded)) {
			throw new RuntimeException('Invalid JSON: ' . $path);
		}
		return $decoded;
	}

	private static function manifestScope(array $manifest): array
	{
		$out = array();
		foreach ((array) ($manifest['categories'] ?? array()) as $category) {
			if (($category['planned_implementation_batch'] ?? '') !== 'B4') {
				continue;
			}
			$out[] = array(
				'id' => $category['id'],
				'b0_category' => $category['b0_category'],
				'semantic_inventory' => $category['semantic_inventory'],
				'canonical_target' => $category['canonical_target'],
				'b0_strategy' => $category['b0_strategy'],
				'compatibility_classification' => $category['compatibility_classification'],
			);
		}
		return $out;
	}

	private static function browserGlobals(string $root, array $publicPhp, array $phpCalls): array
	{
		$rows = array();
		foreach (self::BROWSER_CANONICAL as $legacy => $canonical) {
			$rows[$legacy] = array(
				'legacy_identifier' => $legacy,
				'canonical_identifier' => $canonical,
				'category' => 'browser_global',
				'producer_sites' => array(),
				'consumer_sites' => array(),
				'known_addon_external_consumers' => array(),
				'compatibility_strategy' => 'atomic producer/consumer cutover; no legacy alias because no known add-on consumer exists and versioned assets invalidate cached clients',
				'legacy_inbound_compatibility_required' => false,
			);
		}

		$files = array();
		foreach ($publicPhp as $file) {
			$files[] = $file;
		}
		foreach (self::recursiveFiles($root . '/assets', array('js')) as $path) {
			$files[] = substr($path, strlen($root) + 1);
		}
		sort($files, SORT_STRING);

		foreach ($files as $file) {
			$source = (string) file_get_contents($root . '/' . $file);
			foreach (self::BROWSER_CANONICAL as $legacy => $_canonical) {
				$pattern = '/(?<![A-Za-z0-9_$])' . preg_quote($legacy, '/') . '(?![A-Za-z0-9_$])/';
				if (!preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE)) {
					continue;
				}
				foreach ($matches[0] as $match) {
					$offset = (int) $match[1];
					$line = 1 + substr_count(substr($source, 0, $offset), "\n");
					$lineText = self::lineText($source, $offset);
					$isProducer = self::browserProducerLine($lineText, $legacy);
					$site = array('file' => $file, 'line' => $line, 'context' => trim($lineText));
					self::uniqueSite($rows[$legacy][$isProducer ? 'producer_sites' : 'consumer_sites'], $site);
				}
			}
		}

		foreach ($phpCalls as $file => $calls) {
			foreach ($calls as $call) {
				if ($call['name'] !== 'wp_localize_script' || !isset($call['args'][1])) {
					continue;
				}
				$name = self::literalArg($call['args'][1]);
				if ($name !== null && isset($rows[$name])) {
					self::uniqueSite($rows[$name]['producer_sites'], array('file' => $file, 'line' => $call['line'], 'context' => 'wp_localize_script object name'));
				}
			}
		}

		foreach ($rows as &$row) {
			self::sortSites($row['producer_sites']);
			self::sortSites($row['consumer_sites']);
		}
		unset($row);
		ksort($rows, SORT_STRING);
		return array_values($rows);
	}

	private static function assetHandles(array $phpCalls): array
	{
		$rows = array();
		foreach ($phpCalls as $file => $calls) {
			foreach ($calls as $call) {
				$config = self::ASSET_CALLS[$call['name']] ?? null;
				if ($config === null) {
					continue;
				}
				$handleArg = $call['args'][$config['handle_arg']] ?? null;
				$handle = is_array($handleArg) ? self::literalArg($handleArg) : null;
				if ($handle !== null && str_starts_with($handle, 'vms-')) {
					self::assetSite($rows, $handle, $config['kind'], $config['role'], $file, $call['line'], $call['name']);
				}
				if (is_array($handleArg) && in_array($call['name'], array('wp_print_scripts', 'wp_print_styles'), true)) {
					foreach (self::stringLiterals($handleArg) as $literal) {
						if (str_starts_with($literal['value'], 'vms-')) {
							self::assetSite($rows, $literal['value'], $config['kind'], $config['role'], $file, $literal['line'], $call['name']);
						}
					}
				}
				if (isset($config['deps_arg'], $call['args'][$config['deps_arg']])) {
					foreach (self::stringLiterals($call['args'][$config['deps_arg']]) as $literal) {
						if (str_starts_with($literal['value'], 'vms-')) {
							self::assetSite($rows, $literal['value'], $config['kind'], 'dependency', $file, $literal['line'], $call['name']);
						}
					}
				}
			}
		}

		// Two runtime status-notice handles are passed through a typed helper and
		// therefore are semantic producer arguments rather than direct WP call args.
		foreach (array('vms-notices-front-runtime', 'vms-notices-admin-runtime') as $handle) {
			self::assetSite($rows, $handle, 'script', 'source', 'includes/modules/status-notices/front.php', $handle === 'vms-notices-front-runtime' ? 187 : 202, 'bvmgr_status_notice_enqueue_runtime_assets');
		}
		self::assetSite($rows, 'vms-admin-ticketing', 'script', 'source', 'includes/integrations/ticketing.php', 392, 'dynamic $handle passed to wp_enqueue_script');
		self::assetSite($rows, 'vms-admin', 'style', 'dependency', 'includes/admin-ui/assets.php', 28, 'dynamic dependency array append');
		self::assetSite($rows, 'vms-ticketing-front', 'style', 'dependency', 'includes/integrations/ticketing-rules-v2.php', 7022, 'dynamic dependency array merge');

		foreach ($rows as &$row) {
			$row['asset_types'] = array_keys($row['asset_types']);
			sort($row['asset_types'], SORT_STRING);
			foreach (array('registration_source_sites', 'dependency_sites', 'consumer_sites') as $key) {
				self::sortSites($row[$key]);
			}
			$row['known_addon_external_consumers'] = array();
			$row['compatibility_strategy'] = 'atomic canonical producer/dependency/consumer cutover; no legacy alias because semantic add-on review found no external asset-API consumer';
			$row['minimum_safe_lifetime'] = 'not applicable; no compatibility alias is retained';
			$row['legacy_inbound_compatibility_required'] = false;
		}
		unset($row);
		ksort($rows, SORT_STRING);
		return array_values($rows);
	}

	private static function assetSummary(array $phpCalls, array $handles): array
	{
		$registrationCalls = 0;
		$literalRegistrationCalls = 0;
		$dynamicRegistrationCalls = 0;
		$dynamicSites = array(
			'includes/helpers.php:96' => true,
			'includes/integrations/ticketing.php:397' => true,
			'includes/modules/status-notices/front.php:162' => true,
		);
		foreach ($phpCalls as $file => $calls) {
			foreach ($calls as $call) {
				if (!in_array($call['name'], array('wp_enqueue_script', 'wp_enqueue_style', 'wp_register_script', 'wp_register_style'), true)) {
					continue;
				}
				$handle = isset($call['args'][0]) ? self::literalArg($call['args'][0]) : null;
				if ($handle !== null && str_starts_with($handle, 'vms-') && $handle !== 'vms-ma-tours') {
					$literalRegistrationCalls++;
				} elseif (isset($dynamicSites[$file . ':' . $call['line']])) {
					$dynamicRegistrationCalls++;
				}
			}
		}
		$registrationCalls = $literalRegistrationCalls + $dynamicRegistrationCalls;
		$summary = array(
			'registration_call_sites' => $registrationCalls,
			'literal_registration_call_sites' => $literalRegistrationCalls,
			'dynamic_registration_call_sites' => $dynamicRegistrationCalls,
			'resolved_source_sites' => 0,
			'dependency_sites' => 0,
			'consumer_sites' => 0,
		);
		foreach ($handles as $row) {
			$summary['resolved_source_sites'] += count($row['registration_source_sites']);
			$summary['dependency_sites'] += count($row['dependency_sites']);
			$summary['consumer_sites'] += count($row['consumer_sites']);
		}
		return $summary;
	}

	private static function nonces(string $root, array $publicPhp, array $phpCalls): array
	{
		$actions = array();
		$fields = array();
		foreach ($phpCalls as $file => $calls) {
			foreach ($calls as $call) {
				$config = self::NONCE_CALLS[$call['name']] ?? null;
				if ($config === null) {
					continue;
				}
				$actionArg = $call['args'][$config['action_arg']] ?? null;
				if (is_array($actionArg)) {
					$literal = self::literalArg($actionArg);
					$containsLegacy = self::argContainsPrefix($actionArg, 'vms_');
					if (($literal !== null && str_starts_with($literal, 'vms_')) || $containsLegacy) {
						$legacy = $literal !== null ? $literal : self::nonceFamilyPattern($actionArg);
						$canonical = self::canonicalNonceExpression($legacy);
						$key = ($literal !== null ? 'static:' : 'dynamic:') . $legacy;
						if (!isset($actions[$key])) {
							$actions[$key] = array(
								'legacy_identifier' => $legacy,
								'canonical_identifier' => $canonical,
								'category' => 'nonce_action',
								'family_kind' => $literal !== null ? 'static' : 'dynamic',
								'producer_sites' => array(),
								'verifier_sites' => array(),
								'known_addon_external_consumers' => array(),
								'compatibility_strategy' => 'canonical generation; canonical-first verification with exact deterministic legacy fallback',
								'minimum_safe_lifetime' => 'Maximum WordPress nonce validity window plus longest known page/form cache TTL.',
								'legacy_inbound_compatibility_required' => true,
							);
						}
						self::uniqueSite($actions[$key][$config['role'] === 'producer' ? 'producer_sites' : 'verifier_sites'], array('file' => $file, 'line' => $call['line'], 'context' => $call['name']));
					}
				}

				if (isset($config['field_arg'], $call['args'][$config['field_arg']])) {
					$field = self::literalArg($call['args'][$config['field_arg']]);
					if ($field !== null && self::legacyNonceField($field)) {
						self::nonceFieldSite($fields, $field, $config['role'], $file, $call['line'], $call['name']);
					}
				}
			}
		}

		$holidayKey = 'dynamic:vms_holidays_{*}';
		if (!isset($actions[$holidayKey])) {
			$actions[$holidayKey] = array(
				'legacy_identifier' => 'vms_holidays_{*}',
				'canonical_identifier' => 'bvmgr_holidays_{*}',
				'category' => 'nonce_action',
				'family_kind' => 'dynamic',
				'producer_sites' => array(
					array('file' => 'includes/admin/holidays.php', 'line' => 594, 'context' => 'wp_nonce_field static member'),
					array('file' => 'includes/admin/holidays.php', 'line' => 666, 'context' => 'wp_nonce_field static member'),
					array('file' => 'includes/admin/holidays.php', 'line' => 736, 'context' => 'wp_nonce_url static member'),
				),
				'verifier_sites' => array(array('file' => 'includes/admin/holidays.php', 'line' => 217, 'context' => 'indirect wp_verify_nonce action family')),
				'known_addon_external_consumers' => array(),
				'compatibility_strategy' => 'canonical generation; canonical-first verification with exact deterministic legacy fallback',
				'minimum_safe_lifetime' => 'Maximum WordPress nonce validity window plus longest known page/form cache TTL.',
				'legacy_inbound_compatibility_required' => true,
			);
		}

		foreach (self::NONCE_FIELDS as $field) {
			if (!isset($fields[$field])) {
				self::nonceFieldSite($fields, $field, 'verifier', '', 0, 'curated indirect/manual request-field contract');
				$fields[$field]['reader_sites'] = array();
			}
		}

		// Every exact custom request-field string occurrence is a producer/read
		// site candidate and is frozen so implementation cannot silently miss JS,
		// manual HTML, or request-array consumers outside the direct WP calls.
		foreach ($publicPhp as $file) {
			$source = (string) file_get_contents($root . '/' . $file);
			$tokens = token_get_all($source);
			foreach ($tokens as $token) {
				if (!is_array($token) || $token[0] !== T_CONSTANT_ENCAPSED_STRING) {
					continue;
				}
				$value = self::literalToken($token[1]);
				if (!isset($fields[$value])) {
					continue;
				}
				self::uniqueSite($fields[$value]['all_exact_sites'], array('file' => $file, 'line' => (int) $token[2], 'context' => 'exact request-field string'));
			}
		}

		foreach ($actions as &$row) {
			self::sortSites($row['producer_sites']);
			self::sortSites($row['verifier_sites']);
		}
		unset($row);
		foreach ($fields as &$row) {
			foreach (array('producer_sites', 'reader_sites', 'all_exact_sites') as $key) {
				self::sortSites($row[$key]);
			}
		}
		unset($row);
		ksort($actions, SORT_STRING);
		ksort($fields, SORT_STRING);
		return array('actions' => array_values($actions), 'fields' => array_values($fields));
	}

	private static function queryRewrite(string $root, array $publicPhp, array $phpCalls): array
	{
		$queryVars = array();
		$rewriteTags = array();
		$rewriteRules = array();
		foreach ($phpCalls as $file => $calls) {
			foreach ($calls as $call) {
				if ($call['name'] === 'get_query_var' || $call['name'] === 'set_query_var') {
					$value = isset($call['args'][0]) ? self::literalArg($call['args'][0]) : null;
					if ($value !== null && str_starts_with($value, 'vms_')) {
						self::querySite($queryVars, $value, $call['name'] === 'set_query_var' ? 'producer_sites' : 'consumer_sites', $file, $call['line'], $call['name']);
					}
				}
				if ($call['name'] === 'add_rewrite_tag' && isset($call['args'][0])) {
					$tag = self::literalArg($call['args'][0]);
					if ($tag !== null && preg_match('/^%vms_[a-z0-9_]+%$/', $tag)) {
						$rewriteTags[$tag] = array(
							'legacy_identifier' => $tag,
							'canonical_identifier' => str_replace('%vms_', '%bvmgr_', $tag),
							'category' => 'rewrite_tag',
							'registration_sites' => array(array('file' => $file, 'line' => $call['line'], 'context' => 'add_rewrite_tag')),
							'compatibility_strategy' => 'register canonical and legacy inbound tags during the compatibility period',
							'legacy_inbound_compatibility_required' => true,
						);
					}
				}
				if ($call['name'] === 'add_rewrite_rule') {
					$expr = self::expression($call['args'][1] ?? array());
					if (str_contains($expr, 'vms_')) {
						$key = $file . ':' . $call['line'];
						$rewriteRules[$key] = array(
							'legacy_identifier' => $expr,
							'canonical_identifier' => str_replace('vms_', 'bvmgr_', $expr),
							'category' => 'rewrite_rule',
							'registration_sites' => array(array('file' => $file, 'line' => $call['line'], 'context' => 'add_rewrite_rule')),
							'compatibility_strategy' => 'canonical rule emits the canonical query var; a separate legacy inbound rule remains during the compatibility period',
							'legacy_inbound_compatibility_required' => true,
						);
					}
				}
			}
		}

		// query_vars filters commonly append string values inside callbacks rather
		// than call a dedicated registration API. Freeze every exact vms_* string
		// which is also used by get_query_var, a rewrite tag, or a rewrite target.
		foreach (self::QUERY_VARS as $identifier) {
			if (!isset($queryVars[$identifier])) {
				self::querySite($queryVars, $identifier, 'all_exact_sites', '', 0, 'curated query-var contract');
				$queryVars[$identifier]['all_exact_sites'] = array();
			}
		}
		$candidates = self::QUERY_VARS;
		foreach ($rewriteTags as $tag => $_row) {
			$candidates[] = trim($tag, '%');
		}
		foreach ($rewriteRules as $row) {
			if (preg_match_all('/\bvms_[a-z0-9_]+\b/', $row['legacy_identifier'], $matches)) {
				$candidates = array_merge($candidates, $matches[0]);
			}
		}
		$candidates = array_values(array_unique($candidates));
		foreach ($publicPhp as $file) {
			$tokens = token_get_all((string) file_get_contents($root . '/' . $file));
			foreach ($tokens as $token) {
				if (!is_array($token) || $token[0] !== T_CONSTANT_ENCAPSED_STRING) {
					continue;
				}
				$value = self::literalToken($token[1]);
				if (!in_array($value, $candidates, true)) {
					continue;
				}
				self::querySite($queryVars, $value, 'all_exact_sites', $file, (int) $token[2], 'exact query identifier string');
			}
		}

		foreach ($queryVars as &$row) {
			foreach (array('registration_sites', 'producer_sites', 'consumer_sites', 'all_exact_sites') as $key) {
				self::sortSites($row[$key]);
			}
		}
		unset($row);
		ksort($queryVars, SORT_STRING);
		ksort($rewriteTags, SORT_STRING);
		ksort($rewriteRules, SORT_STRING);
		return array('query_vars' => array_values($queryVars), 'rewrite_tags' => array_values($rewriteTags), 'rewrite_rules' => array_values($rewriteRules));
	}

	private static function cli(array $phpCalls): array
	{
		$rows = array();
		foreach ($phpCalls as $file => $calls) {
			foreach ($calls as $call) {
				if ($call['name'] !== 'wp_cli::add_command' || !isset($call['args'][0])) {
					continue;
				}
				$path = self::literalArg($call['args'][0]);
				if ($path === null || ($path !== 'vms' && !str_starts_with($path, 'vms '))) {
					continue;
				}
				$rows[$path] = array(
					'legacy_identifier' => $path,
					'canonical_identifier' => 'bvmgr' . substr($path, 3),
					'category' => 'cli_path',
					'registration_sites' => array(array('file' => $file, 'line' => $call['line'], 'context' => 'WP_CLI::add_command')),
					'known_addon_external_consumers' => array(),
					'compatibility_strategy' => 'canonical command registration plus explicitly deprecated legacy alias using the identical command class',
					'minimum_safe_lifetime' => 'At least one public compatibility release and an operator automation audit.',
					'legacy_inbound_compatibility_required' => true,
				);
			}
		}
		ksort($rows, SORT_STRING);
		return array_values($rows);
	}

	private static function calls(string $source, array $wanted): array
	{
		$wanted = array_fill_keys(array_map('strtolower', $wanted), true);
		$tokens = token_get_all($source);
		$calls = array();
		$count = count($tokens);
		for ($i = 0; $i < $count; $i++) {
			$token = $tokens[$i];
			if (!is_array($token) || $token[0] !== T_STRING) {
				continue;
			}
			$name = strtolower($token[1]);
			$open = self::nextSignificantIndex($tokens, $i);
			if (($tokens[$open] ?? null) !== '(') {
				// Static calls such as WP_CLI::add_command().
				$sep = $open;
				$method = self::nextSignificantIndex($tokens, $sep);
				$methodOpen = self::nextSignificantIndex($tokens, $method);
				$separator = $tokens[$sep] ?? null;
				if (strtolower($token[1]) === 'wp_cli' && is_array($separator) && $separator[0] === T_DOUBLE_COLON && is_array($tokens[$method] ?? null) && strtolower($tokens[$method][1]) === 'add_command' && ($tokens[$methodOpen] ?? null) === '(') {
					$name = 'wp_cli::add_command';
					$open = $methodOpen;
				} else {
					continue;
				}
			}
			if (!isset($wanted[$name])) {
				continue;
			}
			$parsed = self::parseArgs($tokens, $open);
			if ($parsed === null) {
				continue;
			}
			$calls[] = array('name' => $name, 'line' => (int) $token[2], 'args' => $parsed['args']);
		}
		return $calls;
	}

	private static function parseArgs(array $tokens, int $open): ?array
	{
		$args = array();
		$current = array();
		$paren = 1;
		$bracket = 0;
		$brace = 0;
		for ($i = $open + 1, $count = count($tokens); $i < $count; $i++) {
			$token = $tokens[$i];
			$text = self::tokenText($token);
			if ($text === '(') {
				$paren++;
			} elseif ($text === ')') {
				$paren--;
				if ($paren === 0) {
					$args[] = $current;
					return array('args' => $args, 'close' => $i);
				}
			} elseif ($text === '[') {
				$bracket++;
			} elseif ($text === ']') {
				$bracket--;
			} elseif ($text === '{') {
				$brace++;
			} elseif ($text === '}') {
				$brace--;
			}
			if ($text === ',' && $paren === 1 && $bracket === 0 && $brace === 0) {
				$args[] = $current;
				$current = array();
				continue;
			}
			$current[] = $token;
		}
		return null;
	}

	private static function literalArg(array $arg): ?string
	{
		$significant = array_values(array_filter($arg, static function ($token): bool {
			return !(is_array($token) && in_array($token[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true));
		}));
		if (count($significant) !== 1 || !is_array($significant[0]) || $significant[0][0] !== T_CONSTANT_ENCAPSED_STRING) {
			return null;
		}
		return self::literalToken($significant[0][1]);
	}

	private static function stringLiterals(array $arg): array
	{
		$out = array();
		foreach ($arg as $token) {
			if (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING) {
				$out[] = array('value' => self::literalToken($token[1]), 'line' => (int) $token[2]);
			}
		}
		return $out;
	}

	private static function expression(array $arg): string
	{
		$text = '';
		foreach ($arg as $token) {
			$text .= self::tokenText($token);
		}
		$text = preg_replace('/\s+/', ' ', trim($text));
		return is_string($text) ? $text : '';
	}

	private static function nonceFamilyPattern(array $arg): string
	{
		$pattern = '';
		$wildcard = false;
		$bracketDepth = 0;
		foreach ($arg as $token) {
			if (is_array($token) && in_array($token[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
				continue;
			}
			$text = self::tokenText($token);
			if ($text === '[') {
				$bracketDepth++;
			} elseif ($text === ']') {
				$bracketDepth = max(0, $bracketDepth - 1);
			}
			if (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING && $bracketDepth === 0) {
				$pattern .= self::literalToken($token[1]);
				$wildcard = false;
				continue;
			}
			if (is_array($token) && defined('T_ENCAPSED_AND_WHITESPACE') && $token[0] === T_ENCAPSED_AND_WHITESPACE) {
				$pattern .= $token[1];
				$wildcard = false;
				continue;
			}
			if (in_array($text, array('.', '(', ')', '[', ']', "'", '"'), true)) {
				continue;
			}
			if (!$wildcard) {
				$pattern .= '{*}';
				$wildcard = true;
			}
		}
		return preg_replace('/(?:\{\*\})+/', '{*}', trim($pattern)) ?? trim($pattern);
	}

	private static function argContainsPrefix(array $arg, string $prefix): bool
	{
		foreach (self::stringLiterals($arg) as $literal) {
			if (str_contains($literal['value'], $prefix)) {
				return true;
			}
		}
		return false;
	}

	private static function canonicalNonceExpression(string $legacy): string
	{
		return preg_replace_callback('/(?<![A-Za-z0-9])_?vms_/', static function (array $match): string {
			return str_starts_with($match[0], '_') ? '_bvmgr_' : 'bvmgr_';
		}, $legacy) ?? $legacy;
	}

	private static function legacyNonceField(string $value): bool
	{
		return preg_match('/^_?vms_[a-z0-9_]+$/', $value) === 1;
	}

	private static function canonicalNonceField(string $legacy): string
	{
		if ($legacy === 'vms_admin_heavy_nonce') {
			return '_bvmgr_admin_heavy_nonce';
		}
		return str_starts_with($legacy, '_vms_') ? '_bvmgr_' . substr($legacy, 5) : 'bvmgr_' . substr($legacy, 4);
	}

	private static function nonceFieldSite(array &$rows, string $field, string $role, string $file, int $line, string $context): void
	{
		if (!isset($rows[$field])) {
			$rows[$field] = array(
				'legacy_identifier' => $field,
				'canonical_identifier' => self::canonicalNonceField($field),
				'category' => 'nonce_field',
				'producer_sites' => array(),
				'reader_sites' => array(),
				'all_exact_sites' => array(),
				'known_addon_external_consumers' => array(),
				'compatibility_strategy' => 'canonical field generation/read; request normalization copies legacy to absent canonical field only',
				'minimum_safe_lifetime' => 'Maximum WordPress nonce validity window plus longest known page/form cache TTL.',
				'legacy_inbound_compatibility_required' => true,
			);
		}
		self::uniqueSite($rows[$field][$role === 'producer' ? 'producer_sites' : 'reader_sites'], array('file' => $file, 'line' => $line, 'context' => $context));
	}

	private static function assetSite(array &$rows, string $handle, string $kind, string $role, string $file, int $line, string $context): void
	{
		if (!isset($rows[$handle])) {
			$rows[$handle] = array(
				'legacy_identifier' => $handle,
				'canonical_identifier' => 'bvmgr-' . substr($handle, 4),
				'category' => 'asset_handle',
				'asset_types' => array(),
				'registration_source_sites' => array(),
				'dependency_sites' => array(),
				'consumer_sites' => array(),
			);
		}
		$rows[$handle]['asset_types'][$kind] = true;
		$key = $role === 'source' ? 'registration_source_sites' : ($role === 'dependency' ? 'dependency_sites' : 'consumer_sites');
		self::uniqueSite($rows[$handle][$key], array('file' => $file, 'line' => $line, 'context' => $context));
	}

	private static function querySite(array &$rows, string $identifier, string $key, string $file, int $line, string $context): void
	{
		if (!isset($rows[$identifier])) {
			$rows[$identifier] = array(
				'legacy_identifier' => $identifier,
				'canonical_identifier' => 'bvmgr_' . substr($identifier, 4),
				'category' => 'query_var',
				'registration_sites' => array(),
				'producer_sites' => array(),
				'consumer_sites' => array(),
				'all_exact_sites' => array(),
				'known_addon_external_consumers' => array(),
				'compatibility_strategy' => 'new/generated URLs use canonical; canonical-first reads fall back to registered legacy inbound query var',
				'minimum_safe_lifetime' => 'Indefinite pending an inbound URL/content/log audit.',
				'legacy_inbound_compatibility_required' => true,
			);
		}
		self::uniqueSite($rows[$identifier][$key], array('file' => $file, 'line' => $line, 'context' => $context));
	}

	private static function retirementRow(string $legacy, string $canonical, string $type, string $reason, string $lifetime, string $removal, bool $indefinite): array
	{
		return array(
			'compatibility_identifier' => $legacy,
			'canonical_replacement' => $canonical,
			'compatibility_type' => $type,
			'reason_retained' => $reason,
			'minimum_safe_lifetime' => $lifetime,
			'removal_condition' => $removal,
			'must_survive_indefinitely' => $indefinite,
		);
	}

	private static function recursiveFiles(string $root, array $extensions): array
	{
		$out = array();
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
		foreach ($iterator as $file) {
			if (!$file->isFile() || !in_array(strtolower($file->getExtension()), $extensions, true)) {
				continue;
			}
			$out[] = $file->getPathname();
		}
		sort($out, SORT_STRING);
		return $out;
	}

	private static function browserProducerLine(string $line, string $legacy): bool
	{
		$quoted = preg_quote($legacy, '/');
		return preg_match('/(?:window\.)?' . $quoted . '\s*=\s*(?!=)/', $line) === 1
			|| str_contains($line, "'" . $legacy . "'") && str_contains($line, 'wp_localize_script')
			|| str_contains($line, 'window.' . $legacy . ' =');
	}

	private static function lineText(string $source, int $offset): string
	{
		$start = strrpos(substr($source, 0, $offset), "\n");
		$start = $start === false ? 0 : $start + 1;
		$end = strpos($source, "\n", $offset);
		$end = $end === false ? strlen($source) : $end;
		return substr($source, $start, $end - $start);
	}

	private static function nextSignificantIndex(array $tokens, int $index): int
	{
		for ($i = $index + 1, $count = count($tokens); $i < $count; $i++) {
			$token = $tokens[$i];
			if (is_array($token) && in_array($token[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
				continue;
			}
			return $i;
		}
		return $index;
	}

	private static function tokenText($token): string
	{
		return is_array($token) ? $token[1] : (string) $token;
	}

	private static function literalToken(string $literal): string
	{
		if (strlen($literal) < 2) {
			return $literal;
		}
		$value = substr($literal, 1, -1);
		return $literal[0] === "'" ? str_replace(array('\\\\', "\\'"), array('\\', "'"), $value) : stripcslashes($value);
	}

	private static function uniqueSite(array &$sites, array $site): void
	{
		$key = ($site['file'] ?? '') . ':' . ($site['line'] ?? 0) . ':' . ($site['context'] ?? '');
		foreach ($sites as $existing) {
			$existingKey = ($existing['file'] ?? '') . ':' . ($existing['line'] ?? 0) . ':' . ($existing['context'] ?? '');
			if ($existingKey === $key) {
				return;
			}
		}
		$sites[] = $site;
	}

	private static function sortSites(array &$sites): void
	{
		usort($sites, static fn(array $a, array $b): int => array($a['file'] ?? '', $a['line'] ?? 0, $a['context'] ?? '') <=> array($b['file'] ?? '', $b['line'] ?? 0, $b['context'] ?? ''));
	}
}
